<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../../tenant/guest_logging_process/vendor/autoload.php';

if (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}
if (file_exists('../../includes/database.php')) {
    include_once('../../includes/database.php');
}
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Landlord') {
    header("Location: ../../login.php");
    exit();
}
$room_id = $_GET['room_id'] ?? 0;
$date = $_GET['date'] ?? '';
$id = $_GET['id'] ?? '';

$stmt = $pdo->prepare("SELECT    
                            ut.utility_payment_id,
                            uttype.utility_name,
                            SUM(ut.amount) AS amount,
                            p.paid_on,
                            ps.status,
                            p.due_date,
                            p.payment_method    
                          FROM utility_payment ut
                          JOIN payment p ON p.payment_id = ut.payment_id                               
                          JOIN utility_type uttype ON uttype.utility_type_id = ut.utility_type_id
                          JOIN payment_status ps ON p.payment_id = ps.payment_id                               
                          JOIN contract c ON p.contract_id = c.contract_id
                          JOIN userall us ON us.user_id = c.user_id
                          WHERE us.room_id = ?
                          AND p.due_date = ?
                          AND ut.utility_type_id = ?
                          GROUP BY p.due_date
                          ORDER BY p.due_date DESC");
$stmt->execute([$room_id, $date, $id]);
$inputs = $stmt->fetch();

$stmtpreparetenants = $pdo->prepare("SELECT room_number from room WHERE room_id = ?");
$stmtpreparetenants->execute([$room_id]);
$tenants = $stmtpreparetenants->fetch();

$tenantRes = $db_connection->query("
            SELECT u.user_id, pi.email, pi.first_name, r.room_amount 
            FROM userall u
            JOIN personal_info pi ON pi.user_id = u.user_id
            JOIN room r ON u.room_id = r.room_id
            WHERE u.room_id = $room_id AND u.role = 'Tenant' AND u.account_status = 'Active'
        ");
$tenant = $tenantRes->fetch_assoc();
$tenant_id = $tenant['user_id'];
$contractRes = $db_connection->query("SELECT * FROM contract WHERE user_id = $tenant_id AND contract_status = 'Active' LIMIT 1");
$contract = $contractRes->fetch_assoc();
$contract_id = $contract['contract_id'];



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $utility_amount = $_POST['amount'];
    $sql = 'SELECT capacity FROM room WHERE room_id = ' . $room_id;
    $rs = mysqli_query($db_connection, $sql);
    $rw = mysqli_fetch_array($rs);
    $capacity = $rw['capacity'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $utility_due_date = $_POST['utility_due_date'];
    $amount = $utility_amount / $capacity;
    $paid_on = $_POST['paid_on'];
    $payment_method = $_POST['payment_method'];
    $today = date('Y-m-d');
    $room_id = $_GET['room_id'] ?? 0;
    $date = $_GET['date'] ?? '';
    $id = $_GET['id'] ?? '';
    $min_allowed = date('Y-m-d', strtotime($start_date)); // Contract start date
    $max_allowed = date('Y-m-d', strtotime('+1 month', strtotime($end_date))); // 1 month after contract end

    if (strtotime($paid_on) < strtotime($min_allowed) || strtotime($paid_on) > strtotime($max_allowed)) {
        $_SESSION["form_data"][$room_id] = $_POST;
        header("Location: ../tenant_manage.php?message=value_date_error_utility&room=$room_id");
        exit;
    }
    if (strtotime($utility_due_date) < strtotime($min_allowed) || strtotime($utility_due_date) > strtotime($max_allowed)) {
        $_SESSION["form_data"][$room_id] = $_POST;
        header("Location: ../tenant_manage.php?message=value_date_error_utility&room=$room_id");
        exit;
    }

    try {
        // ✅ Get all tenants in the room
        $tenantRes = $db_connection->query("
            SELECT u.user_id, pi.email, pi.first_name, r.room_amount 
            FROM userall u
            JOIN personal_info pi ON pi.user_id = u.user_id
            JOIN room r ON u.room_id = r.room_id
            WHERE u.room_id = $room_id AND u.role = 'Tenant' AND u.account_status = 'Active'
        ");

        while ($tenant = $tenantRes->fetch_assoc()) {
            $tenant_id = $tenant['user_id'];
            $to = $tenant['email'];
            $name = $tenant['first_name'];
            $room_amount = $tenant['room_amount'] / $capacity;

            // ✅ Get active contract
            $contractRes = $db_connection->query("SELECT contract_id FROM contract WHERE user_id = $tenant_id AND status = 'Active' LIMIT 1");
            if (!$contractRes->num_rows) continue; // skip if no active contract
            $contract = $contractRes->fetch_assoc();
            $contract_id = $contract['contract_id'];

            $latestPaymentRes = $db_connection->query("
                SELECT p.payment_id 
                FROM payment p
                JOIN utility_payment ut ON ut.payment_id = p.payment_id
                WHERE p.contract_id = $contract_id
                AND ut.utility_type_id = $id
                AND p.due_date = '$date'
                LIMIT 1
            ");
            $latestPayment = $latestPaymentRes->fetch_assoc();

            $EmailcurrentDue = date("F j, Y", strtotime($utility_due_date));
            $statusValue = '';

            $paidOnDate = date('Y-m-d', strtotime($paid_on));
            $dueDate    = date('Y-m-d', strtotime($utility_due_date));

            if ($paidOnDate > $dueDate) {
                $statusValue = 'LATE';
            } else {
                $statusValue = 'PAID';
            }

            if ($dueDate >= date('Y-m-d', strtotime($end_date))) {
                $statusValue = "LAST";
            }
            if ($utility_due_date >= $end_date) {
                $statusValue = "LAST";
            }
            // ✅ Notify tenant (DB + Email)

            $string_date = date("F j, Y", strtotime($paid_on));


            // Update utility_payment
            $stmt1 = $pdo->prepare("
                UPDATE payment 
                SET due_date = ?, paid_on = ?, payment_method = ? 
                WHERE payment_id = ?
            ");
            $stmt1->execute([$utility_due_date, $paid_on, $payment_method, $latestPayment['payment_id']]);

            $stmt = $pdo->prepare("
                UPDATE utility_payment 
                SET amount = ?
                WHERE payment_id = ?
            ");
            $stmt->execute([$amount, $latestPayment['payment_id']]);
            // Update payment_status
            $stmt3 = $pdo->prepare("
                UPDATE payment_status 
                SET status = ?, status_date = ? 
                WHERE payment_id = ?
            ");
            $stmt3->execute([$statusValue, $today, $latestPayment['payment_id']]);
            $notif_text = "Hi $name,\n\nYour utility payment has been recorded with the following details:\n\n" .
                "Amount: $amount\n" .
                "Due Date: $utility_due_date\n" .
                "Payment Method: $payment_method]\n" .
                "Payment Date: $paid_on\n\n" .
                "- Apartment Management";

            $db_connection->query("INSERT INTO notification (user_id, notif_title, notif_text, date_created, notif_status) 
                       VALUES ($tenant_id, 'Utility Payment Update', '$notif_text', NOW(), 'unread')");

            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username   = 'adrianfernando2626@gmail.com';
                $mail->Password   = 'cxwqqwktqevyogmt';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('adrianfernando2626@gmail.com', 'Tenant Management');
                $mail->addAddress($to, $name);
                $mail->Subject = 'Utility Payment Confirmation';
                $mail->Body = $notif_text;

                $mail->send();
            } catch (Exception $e) {
                error_log("Email to {$to} failed: " . $mail->ErrorInfo);
            }
        }
        if ($utility_due_date >= $end_date) {
            header('Location: ../tenant_manage.php?message=last_due');
            exit;
        }
        header('Location: ../tenant_manage.php?message=payment_utility');
        exit;
    } catch (mysqli_sql_exception $e) {
        error_log("DB Error: " . $e->getMessage());
        $_SESSION['update_error'] = "Something went wrong during payment update.";
        header('Location: ../tenant_manage.php');
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../../css/addcontent1.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
        function logout() {
            var msg = 'Are you sure you want to logout?';
            Swal.fire({
                icon: 'question',
                title: 'Log Out',
                text: msg,
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../logout.php?status=logout';
                } else {
                    location.reload();
                }
            });
        }
    </script>
</head>

<body class="bg-light">
    <div class="side-bar collapsed">
        <a href="" class="logo">
            <img src="" alt="" class="logo-img">
            <img src="" alt="" class="logo-icon">
        </a>
        <ul class="nav-link">
            <li><a href="../landlord_dashboard.php"><i class="fas fa-th-large"></i>
                    <p>DashBoard</p>
                </a></li>
            <li><a href="../user_access.php"><i class="fas fa-users"></i>
                    <p>User Access Management</p>
                </a></li>
            <li><a href="../tenant_manage.php"><i class="fas fa-user-check"></i>
                    <p>Room Management</p>
                </a></li>
            <li><a href="../notification_log.php"><i class="fas fa-bell"></i>
                    <p>Billing Notifications</p>
                </a></li>
            <li><a href="../rentreportlandlord.php"><i class="fas fa-file-lines"></i>
                    <p>Report Management</p>
                </a></li>
            <li><a href="../landlordprofile.php"><i class="fas fa-cog"></i>
                    <p>User Account</p>
                </a></li>
            <li><a onclick="logout()"><i class="fas fa-sign-out-alt"></i>
                    <p> Logout</p>
                </a></li>
        </ul>
    </div>
    <main class="main-content">
        <div class="form-section">
            <div class="form-header">
                <i class="fas fa-clipboard-list fa-2x"></i>
                <h1>Edit Utility Bills (<?php echo $inputs['utility_name']; ?>)</h1>
            </div>
            <?php if (!empty($warning)): ?>
                <div class="alert alert-warning"><?php echo htmlspecialchars($warning); ?></div>
            <?php endif;
            // Load saved form data if redirected from validation error
            $form_data = $_SESSION["form_data"][$room_id] ?? [];
            ?>

            <form class="rule-form" method="post">
                <h3>Room <?php echo $tenants['room_number'] ?></h3>

                <label>Start Date of Contract</label>
                <input type="date" name="start_date"
                    value="<?php echo $contract['start_date']; ?>" readonly>

                <label>End Date of Contract</label>
                <input type="date" name="end_date"
                    value="<?php echo $contract['end_date']; ?>" readonly>

                <label>Date of Payment</label>
                <input type="datetime-local" name="paid_on"
                    value="<?php echo $form_data['paid_on'] ?? $inputs['paid_on']; ?>" required>

                <label>Due Date</label>
                <input type="date" name="utility_due_date"
                    value="<?php echo $form_data['utility_due_date'] ?? $inputs['due_date']; ?>" required>

                <label class="form-check-label">Amount</label>
                <input type="number" step="0.01" name="amount"
                    value="<?php echo $form_data['amount'] ?? $inputs['amount']; ?>" required>

                <label>Payment Method:</label>
                <select name="payment_method" class="form-select" required>
                    <option value="" disabled
                        <?php echo empty($form_data["payment_method"] ?? $inputs["payment_method"]) ? "selected" : ""; ?>>
                        -- Select Method --
                    </option>
                    <?php
                    $methods = ["Cash", "GCASH", "Bank Transfer", "Other"];
                    foreach ($methods as $method) {
                        $selected = '';
                        if (isset($form_data['payment_method'])) {
                            $selected = ($form_data['payment_method'] == $method) ? 'selected' : '';
                        } elseif (($inputs["payment_method"] ?? '') == $method) {
                            $selected = 'selected';
                        }
                        echo "<option value='$method' $selected>$method</option>";
                    }
                    ?>
                </select>

                <button type="submit" class="add-btn">Update</button>
                <a href="../tenant_manage.php" class="back-btn">Cancel</a>
            </form>
            <?php unset($_SESSION["form_data"][$room_id]); ?>

    </main>
    <script src="../../js/script.js"></script>
    <?php if (isset($_GET['message']) && $_GET['message'] === 'value_date_error_utility'): ?>
        <script>
            Swal.fire({
                icon: 'warning',
                title: 'Insertion Date',
                text: 'Please select a date within equal or higher date to the start date of contract and 3 after the end of contract.',
                confirmButtonText: 'OK'
            });
        </script>
    <?php endif; ?>
</body>

</html>