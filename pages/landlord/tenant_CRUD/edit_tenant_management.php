<?php
require_once '../../tenant/guest_logging_process/vendor/autoload.php';
include_once '../../includes/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

$host = "localhost";
$username = "root";
$password = "";
$database = "apartment";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_id'])) {
    $room_id = intval($_POST['room_id']);
    $expected_amount_due = $_POST['expected_amount_due'];
    $sql = 'SELECT capacity, room_amount FROM room WHERE room_id = ' . $room_id;
    $rs = mysqli_query($db_connection, $sql);
    $rw = mysqli_fetch_array($rs);
    $room_amount = $rw['room_amount'];
    $capacity = $rw['capacity'];
    $paid_on = $_POST['paid_on'];
    $due_date = $_POST['due_date'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $payment_method = $_POST['payment_method'];
    $today = date('Y-m-d');

    $min_allowed = date('Y-m-d', strtotime('-3 months', strtotime($start_date)));
    $max_allowed = date('Y-m-d', strtotime('+3 months', strtotime($end_date)));

    if (strtotime($paid_on) < strtotime($min_allowed) || strtotime($paid_on) > strtotime($max_allowed)) {
        $_SESSION["form_data"][$room_id] = $_POST;
        header("Location: ../tenant_manage.php?message=value_date_error&room=$room_id");
        exit;
    }
    try {
        // ✅ Get all tenants in the room
        $tenantRes = $conn->query("
            SELECT u.user_id, pi.email, pi.first_name 
            FROM userall u
            JOIN personal_info pi ON pi.user_id = u.user_id
            WHERE u.room_id = $room_id AND u.role = 'Tenant' AND u.account_status = 'Active'
        ");

        while ($tenant = $tenantRes->fetch_assoc()) {
            $tenant_id = $tenant['user_id'];
            $to = $tenant['email'];
            $name = $tenant['first_name'];

            // ✅ Get active contract
            $contractRes = $conn->query("SELECT contract_id FROM contract WHERE user_id = $tenant_id AND contract_status = 'Active' LIMIT 1");
            if (!$contractRes->num_rows) continue; // skip if no active contract
            $contract = $contractRes->fetch_assoc();
            $contract_id = $contract['contract_id'];

            $latestPaymentRes = $conn->query("SELECT p.*, rp.tenant_payment FROM payment p 
                                                JOIN rent_payment rp ON rp.payment_id = p.payment_id 
                                                WHERE p.contract_id = $contract_id AND p.due_date = '$due_date'
                                                ORDER BY p.due_date DESC LIMIT 1");
            if (!$latestPaymentRes->num_rows) continue;
            $latestPayment = $latestPaymentRes->fetch_assoc();
            $tenant_payment = $latestPayment['tenant_payment'];

            $currentDue = $latestPayment['due_date'];

            $nextDue = date('Y-m-d', strtotime('+1 month', strtotime($currentDue)));

            $statusValue = (strtotime($paid_on) > strtotime($currentDue)) ? 'LATE' : 'PAID';
            if ($currentDue === $end_date) {
                $statusValue = "LAST";
            }
            echo $tenant_payment;
            if ((float)$tenant_payment >= 0) {
                echo 'Meron na';

                $rent = $expected_amount_due / $capacity;

                // ✅ Update latest payment row
                $updatePayment = $conn->prepare("UPDATE payment 
                SET paid_on = ?, payment_method = ?
                WHERE payment_id = ?");
                $updatePayment->bind_param("ssi", $paid_on, $payment_method, $latestPayment['payment_id']);
                $updatePayment->execute();

                $updaterentPayment = $conn->prepare("UPDATE rent_payment 
                SET tenant_payment = tenant_payment + ?, expected_amount_due = expected_amount_due - ?
                WHERE payment_id = ?");
                $updaterentPayment->bind_param("ddi", $rent, $rent, $latestPayment['payment_id']);
                $updaterentPayment->execute();


                $updateStatus = $conn->prepare("UPDATE payment_status 
                SET status = ?, status_date = ?
                WHERE payment_id = ?");
                $updateStatus->bind_param("ssi", $statusValue, $today, $latestPayment['payment_id']);
                $updateStatus->execute();



                // ✅ Notify tenant (DB + Email)
                $string_date = date("F j, Y", strtotime($paid_on));
                $notif_text = "Hi $name,\n\nYour rent payment has been marked as $statusValue on $string_date.\nPayment Method: $payment_method\n\n- Apartment Management";
                $conn->query("INSERT INTO notification (user_id, notif_title, notif_text, date_created, notif_status) 
                          VALUES ($tenant_id, 'Payment Update', '$notif_text', NOW(), 'unread')");

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
                    $mail->Subject = 'Payment Confirmation';
                    $mail->Body = $notif_text;

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Email to {$to} failed: " . $mail->ErrorInfo);
                }
                $checkNext = $conn->query("SELECT * FROM payment WHERE contract_id = $contract_id AND due_date = '$nextDue'");
                if ($checkNext->num_rows === 0) {
                    if ($currentDue !== $end_date) {
                        $stmt = $conn->prepare("INSERT INTO payment (contract_id, due_date) VALUES (?, ?)");
                        $stmt->bind_param("is", $contract_id, $nextDue);
                        $stmt->execute();
                        $newPaymentId = $conn->insert_id;

                        $stmt = $conn->prepare("INSERT INTO rent_payment (payment_id, expected_amount_due, tenant_payment) VALUES (?, ?, 0)");
                        $stmt->bind_param("id", $newPaymentId, $rent);
                        $stmt->execute();

                        $conn->query("INSERT INTO payment_status (payment_id, status, status_date, is_active) 
                    VALUES ($newPaymentId, 'UNPAID', '$today', 1)");
                    }
                }
            }
        }

        if ($currentDue === $end_date) {
            header('Location: ../tenant_manage.php?message=last_due');
            exit;
        }
        header('Location: ../tenant_manage.php?message=update_success');
        exit;
    } catch (mysqli_sql_exception $e) {
        error_log("DB Error: " . $e->getMessage());
        $_SESSION['update_error'] = "Something went wrong during payment update.";
        header('Location: ../tenant_manage.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_id_advance'])) {
    $room_id_advance = intval($_POST['room_id_advance']);
    $advance_payment = $_POST['advance_payment'];
    $sql = 'SELECT capacity FROM room WHERE room_id = ' . $room_id_advance;
    $rs = mysqli_query($db_connection, $sql);
    $rw = mysqli_fetch_array($rs);
    $capacity = $rw['capacity'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $rent = $advance_payment / $capacity;
    $individual_rent = $advance_payment / $capacity;
    $paid_on = $_POST['advance_paid_on'];
    $payment_method = $_POST['payment_method'];
    $today = date('Y-m-d');
    $min_allowed = date('Y-m-d', strtotime('-3 months', strtotime($start_date)));
    $max_allowed = date('Y-m-d', strtotime('+3 months', strtotime($end_date)));

    if (strtotime($paid_on) < strtotime($min_allowed) || strtotime($paid_on) > strtotime($max_allowed)) {
        $_SESSION["form_data"][$room_id] = $_POST;
        header("Location: ../tenant_manage.php?message=value_date_error&room=$room_id_advance");
        exit;
    }
    try {
        // ✅ Get all tenants in the room
        $tenantRes = $conn->query("
            SELECT u.user_id, pi.email, pi.first_name, r.room_amount 
            FROM userall u
            JOIN personal_info pi ON pi.user_id = u.user_id
            JOIN room r ON u.room_id = r.room_id
            WHERE u.room_id = $room_id_advance AND u.role = 'Tenant' AND u.account_status = 'Active'
        ");

        while ($tenant = $tenantRes->fetch_assoc()) {
            $tenant_id = $tenant['user_id'];
            $to = $tenant['email'];
            $name = $tenant['first_name'];
            $room_amount = $tenant['room_amount'] / $capacity;

            // ✅ Get active contract
            $contractRes = $conn->query("SELECT contract_id FROM contract WHERE user_id = $tenant_id AND contract_status = 'Active' LIMIT 1");
            if (!$contractRes->num_rows) continue; // skip if no active contract
            $contract = $contractRes->fetch_assoc();
            $contract_id = $contract['contract_id'];

            // ✅ Get latest payment for this contract
            $latestPaymentRes = $conn->query("SELECT p.*, rp.expected_amount_due FROM payment p JOIN rent_payment rp ON rp.payment_id = p.payment_id WHERE p.contract_id = $contract_id ORDER BY p.due_date DESC LIMIT 1");
            $latestPayment = $latestPaymentRes->fetch_assoc();
            $currentDue = $latestPayment['due_date'];

            $EmailcurrentDue = date("F j, Y", strtotime($currentDue));
            $nextDue = date('Y-m-d', strtotime('+1 month', strtotime($currentDue)));

            $statusValue = (strtotime($paid_on) > strtotime($currentDue)) ? 'LATE' : 'PAID';
            if ($currentDue === $end_date) {
                $statusValue = "LAST";
            }
            // ✅ Notify tenant (DB + Email)

            $string_date = date("F j, Y", strtotime($paid_on));
            $notif_text = "Hi $name,\n\nYour advance rent payment has been marked set to $rent for $EmailcurrentDue.\nPayment Method: $payment_method\n\n- Apartment Management";
            $conn->query("INSERT INTO notification (user_id, notif_title, notif_text, date_created, notif_status) 
                          VALUES ($tenant_id, 'Advance Payment Update', '$notif_text', NOW(), 'unread')");


            if ($rent == $latestPayment['expected_amount_due']) {
                $updatePayment = $conn->prepare("UPDATE payment 
                SET paid_on = ?, payment_method = ?
                WHERE payment_id = ?");
                $updatePayment->bind_param("ssi", $paid_on, $payment_method, $latestPayment['payment_id']);
                $updatePayment->execute();

                $updaterentPayment = $conn->prepare("UPDATE rent_payment 
                SET tenant_payment = tenant_payment + ?, expected_amount_due = expected_amount_due - ?
                WHERE payment_id = ?");
                $updaterentPayment->bind_param("ddi", $rent, $rent, $latestPayment['payment_id']);
                $updaterentPayment->execute();


                $updateStatus = $conn->prepare("UPDATE payment_status 
                SET status = ?, status_date = ?
                WHERE payment_id = ?");
                $updateStatus->bind_param("ssi", $statusValue, $today, $latestPayment['payment_id']);
                $updateStatus->execute();


                // ✅ Insert next due if not exists
                $checkNext = $conn->query("SELECT * FROM payment WHERE contract_id = $contract_id AND due_date = '$nextDue'");
                if ($checkNext->num_rows === 0) {
                    if ($currentDue !== $end_date) {
                        $stmt = $conn->prepare("INSERT INTO payment (contract_id, due_date) VALUES (?, ?)");
                        $stmt->bind_param("is", $contract_id, $nextDue);
                        $stmt->execute();
                        $newPaymentId = $conn->insert_id;

                        $stmt = $conn->prepare("INSERT INTO rent_payment (payment_id, expected_amount_due, tenant_payment) VALUES (?, ?, 0)");
                        $stmt->bind_param("id", $newPaymentId, $room_amount);
                        $stmt->execute();

                        $conn->query("INSERT INTO payment_status (payment_id, status, status_date, is_active) 
                    VALUES ($newPaymentId, 'UNPAID', '$today', 1)");
                    }
                }
            } elseif ($rent < $latestPayment['expected_amount_due']) {
                $updatePayment = $conn->prepare("UPDATE payment 
                SET paid_on = ?, payment_method = ?
                WHERE payment_id = ?");
                $updatePayment->bind_param("ssi", $paid_on, $payment_method, $latestPayment['payment_id']);
                $updatePayment->execute();

                $updaterentPayment = $conn->prepare("UPDATE rent_payment 
                SET tenant_payment = tenant_payment + ?, expected_amount_due = expected_amount_due - ?
                WHERE payment_id = ?");
                $updaterentPayment->bind_param("ddi", $rent, $rent, $latestPayment['payment_id']);
                $updaterentPayment->execute();
            } elseif ($rent > $latestPayment['expected_amount_due']) {
                $_SESSION["form_data"][$room_id] = $_POST;
                header('Location: ../tenant_manage.php?message=exceed_advance_rent');
                exit;
            }

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
                $mail->Subject = 'Advance Payment Confirmation';
                $mail->Body = $notif_text;

                $mail->send();
            } catch (Exception $e) {
                error_log("Email to {$to} failed: " . $mail->ErrorInfo);
            }
        }
        if ($currentDue === $end_date) {
            header('Location: ../tenant_manage.php?message=last_due');
            exit;
        }
        header('Location: ../tenant_manage.php?message=update_advance_success');
        exit;
    } catch (mysqli_sql_exception $e) {
        error_log("DB Error: " . $e->getMessage());
        $_SESSION['update_error'] = "Something went wrong during payment update.";
        header('Location: ../tenant_manage.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_id_utility'])) {
    $room_id_utility = intval($_POST['room_id_utility']);
    $utility_amount = $_POST['amount'];
    $sql = 'SELECT capacity FROM room WHERE room_id = ' . $room_id_utility;
    $rs = mysqli_query($db_connection, $sql);
    $rw = mysqli_fetch_array($rs);
    $capacity = $rw['capacity'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $payment_method = $_POST['payment_method_utility'];
    $utility_due_date = $_POST['utility_due_date'];
    $utility_type_id = $_POST['utility_type_id'];
    $amount = $utility_amount / $capacity;
    $paid_on = $_POST['utility_paid_on'];
    $today = date('Y-m-d');
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
        $tenantRes = $conn->query("
            SELECT u.user_id, pi.email, pi.first_name, r.room_amount 
            FROM userall u
            JOIN personal_info pi ON pi.user_id = u.user_id
            JOIN room r ON u.room_id = r.room_id
            WHERE u.room_id = $room_id_utility AND u.role = 'Tenant' AND u.account_status = 'Active'
        ");

        while ($tenant = $tenantRes->fetch_assoc()) {
            $tenant_id = $tenant['user_id'];
            $to = $tenant['email'];
            $name = $tenant['first_name'];

            // ✅ Get active contract
            $contractRes = $conn->query("SELECT contract_id FROM contract WHERE user_id = $tenant_id AND contract_status = 'Active' LIMIT 1");
            if (!$contractRes->num_rows) continue; // skip if no active contract
            $contract = $contractRes->fetch_assoc();
            $contract_id = $contract['contract_id'];

            $latestPaymentRes = $conn->query("SELECT * FROM payment WHERE contract_id = $contract_id ORDER BY due_date DESC LIMIT 1");
            if (!$latestPaymentRes->num_rows) continue;
            $latestPayment = $latestPaymentRes->fetch_assoc();

            // ✅ Get latest payment for this contract
            $latestPaymentRes123 = $conn->query("
                                            SELECT ut.* 
                                            FROM utility_payment ut
                                            JOIN payment p ON ut.payment_id = p.payment_id
                                            WHERE p.contract_id = $contract_id
                                            AND ut.utility_type_id = $utility_type_id
                                            AND MONTH(p.due_date) = MONTH(DATE('$utility_due_date'))
                                            AND YEAR(p.due_date) = YEAR(DATE('$utility_due_date'))
                                        LIMIT 1");

            if ($latestPaymentRes123 && $latestPaymentRes123->num_rows > 0) {
                $_SESSION["form_data"][$room_id_utility] = $_POST;
                header("Location: ../tenant_manage.php?message=same_type&room=$room_id_utility");
                exit;
            }
            $EmailcurrentDue = date("F j, Y", strtotime($utility_due_date));

            $statusValue = (strtotime($paid_on) > strtotime($utility_due_date)) ? 'LATE' : 'PAID';
            if ($utility_due_date >= $end_date) {
                $statusValue = "LAST";
            }
            // ✅ Notify tenant (DB + Email)

            $string_date = date("F j, Y", strtotime($paid_on));


            $stmt = $conn->prepare("INSERT INTO payment (contract_id, due_date, paid_on, payment_method) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $contract_id, $utility_due_date, $paid_on, $payment_method);
            $stmt->execute();
            $paymentId = $conn->insert_id;

            $stmtutility = $conn->prepare("INSERT INTO utility_payment (payment_id, utility_type_id, amount) VALUES (?, ?, ?)");
            $stmtutility->bind_param("iid", $paymentId, $utility_type_id, $amount);
            $stmtutility->execute();

            $conn->query("INSERT INTO payment_status (payment_id, status, status_date, is_active) 
                    VALUES ($paymentId, '$statusValue', '$today', 1)");

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
        unset($_SESSION["form_data"][$room_id_utility]);
        header('Location: ../tenant_manage.php?message=payment_utility');
        exit;
    } catch (mysqli_sql_exception $e) {
        error_log("DB Error: " . $e->getMessage());
        $_SESSION['update_error'] = "Something went wrong during payment update.";
        header('Location: ../tenant_manage.php');
        exit;
    }
}
