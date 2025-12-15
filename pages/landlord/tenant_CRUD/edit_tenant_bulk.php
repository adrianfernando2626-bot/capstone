<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../../tenant/guest_logging_process/vendor/autoload.php';

if (file_exists('../../includes/database.php')) {
    include_once('../../includes/database.php');
} elseif (file_exists('../includes/database.php')) {
    include_once('../includes/database.php');
} elseif (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}

session_start();
$user_id_landlord = $_SESSION['user_id'];
$warning = "";
$stmtlandlord = $pdo->prepare("SELECT tenant_priviledge FROM userall WHERE user_id = ?");
$stmtlandlord->execute([$user_id_landlord]);
$landlord = $stmtlandlord->fetch(PDO::FETCH_ASSOC);
$disabled_button = "";
if ($landlord['tenant_priviledge'] === 'Not Approved') {
    $disabled_button = "disabled";
    $warning = "It seems you do not have the approval from the Owner to edit the tenant account status";
} else {
    $disabled_button = "";
    $warning = "";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tenants'])) {
    $updates = $_POST['tenants'] ?? [];

    foreach ($updates as $user_id => $data) {
        $status = $data['account_status'] ?? '';
        if (in_array($status, ['Active', 'Inactive', 'Pending'])) {
            $updateStmt = $pdo->prepare("UPDATE user SET account_status = ? WHERE user_id = ?");
            $updateStmt->execute([$status, $user_id]);


            $userStmt = $pdo->prepare("SELECT first_name, last_name, email FROM userall WHERE user_id = ?");
            $userStmt->execute([$user_id]);
            $tenant = $userStmt->fetch();

            if ($tenant) {
                $email = $tenant['email'];
                $name = $tenant['first_name'] . ' ' . $tenant['last_name'];

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'adrianfernando2626@gmail.com';
                    $mail->Password = 'cxwqqwktqevyogmt';
                    $mail->SMTPSecure = 'tls';
                    $mail->Port = 587;

                    $mail->setFrom('adrianfernando2626@gmail.com', 'Owner');
                    $mail->addAddress($email, $name);
                    $mail->isHTML(true);
                    $mail->Subject = 'Account Status Updated';
                    $mail->Body = "Dear $name,<br><br>Your account status has been updated to <strong>$status</strong>.<br><br>Thank you.";

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Email failed for $email: " . $mail->ErrorInfo);
                }
            }
        }
    }

    header("Location: ../user_access.php?message=accounts_updated");
    exit();
}

$selectedTenants = $_POST['selected_tenant'] ?? [];

if (empty($selectedTenants)) {
    die("No tenants selected.");
}

$placeholders = implode(',', array_fill(0, count($selectedTenants), '?'));
$stmt = $pdo->prepare("SELECT user_id, first_name, last_name, email, account_status FROM userall WHERE user_id IN ($placeholders)");
$stmt->execute($selectedTenants);
$tenants = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edit Selected Tenants</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="../../css/addcontent.css">

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

    <div class="container my-5">
        <div class="card mx-auto shadow" style="max-width: 800px;">
            <div class="card-body">
                <div class="form-header">
                    <i class="fas fa-clipboard-list fa-2x"></i>
                    <h1>Edit Selected Tenants</h1>
                </div>
                <?php if (!empty($warning)): ?>
                    <div class="alert alert-warning"><?php echo htmlspecialchars($warning); ?></div>
                <?php endif; ?>
                <form method="post">
                    <?php foreach ($tenants as $tenant): ?>
                        <div class="card mb-3 p-3 shadow-sm">
                            <h5><?= htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']) ?></h5>
                            <input type="hidden" name="tenants[<?= $tenant['user_id'] ?>][user_id]" value="<?= $tenant['user_id'] ?>">
                            <label>Account Status:
                                <select name="tenants[<?= $tenant['user_id'] ?>][account_status]" class="form-control">
                                    <option value="Active" <?= $tenant['account_status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                    <option value="Inactive" <?= $tenant['account_status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </label>
                        </div>
                    <?php endforeach; ?>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" name="bulkUpdate" class="add-btn" <?php echo $disabled_button; ?>>
                            Save All Changes
                        </button>
                        <button type="button" class="back-btn" onclick="window.location.href='../user_access.php'">
                            Cancel
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
    <script src="../../js/script.js"></script>

</body>

</html>