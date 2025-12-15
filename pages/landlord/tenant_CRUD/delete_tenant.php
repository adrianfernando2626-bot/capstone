<?php
if (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}
if (file_exists('../../includes/database.php')) {
    include_once('../../includes/database.php');
}
require_once '../../tenant/guest_logging_process/vendor/autoload.php';
include_once '../../includes/database.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$id = $_GET['id'] ?? 0;
session_start();
$user_id_landlord = $_SESSION['user_id'];
$stmtlandlord = $pdo->prepare("SELECT tenant_priviledge FROM userall WHERE user_id = ?");
$stmtlandlord->execute([$user_id_landlord]);
$landlord = $stmtlandlord->fetch(PDO::FETCH_ASSOC);
if ($landlord['tenant_priviledge'] === 'Not Approved') {
    $_SESSION['warning_approval'] = "It seems you do not have the approval from the Owner to delete the tenant account";
    header("Location: ../user_access.php");
    exit();
} else {
    if ($id) {
        $user_id = $tenants['user_id'];
        $room_id = $tenants['room_id'];
        $email = $tenants['email'];
        $name = $tenants['first_name'] . ' ' . $tenants['last_name'];

        // 1. Update room status
        $roomUpdateStmt->execute([$room_id]);

        // 2. Update contract status
        $contractUpdateStmt->execute([$user_id]);

        // 3. Update user account status
        $userUpdateStmt->execute([$user_id]);

        // 4. Get maintenance_request_ids by room
        $stmtMaintenance = $pdo->prepare("SELECT maintenance_request_id FROM maintenance_request WHERE room_id = ?");
        $stmtMaintenance->execute([$room_id]);
        $maintenance_ids = $stmtMaintenance->fetchAll(PDO::FETCH_COLUMN);

        // 5. Update all maintenance status to Archived
        foreach ($maintenance_ids as $mid) {
            $statusRequestUpdateStmt->execute([$mid]);
        }

        // 6. Get contract_ids by user
        $stmtContract = $pdo->prepare("SELECT contract_id FROM contract WHERE user_id = ?");
        $stmtContract->execute([$user_id]);
        $contract_ids = $stmtContract->fetchAll(PDO::FETCH_COLUMN);

        // 7. Get and update all related payment statuses
        $stmtPayment = $pdo->prepare("SELECT payment_id FROM payment WHERE contract_id = ?");
        foreach ($contract_ids as $cid) {
            $stmtPayment->execute([$cid]);
            $payment_ids = $stmtPayment->fetchAll(PDO::FETCH_COLUMN);

            foreach ($payment_ids as $pid) {
                $paymentStatusUpdateStmt->execute([$pid]);
            }
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'adrianfernando2626@gmail.com';
            $mail->Password = 'cxwqqwktqevyogmt';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('adrianfernando2626@gmail.com', 'Building Owner');
            $mail->addAddress($email, $name);
            $mail->isHTML(true);
            $mail->Subject = 'Account Deletion Notice';
            $mail->Body = "Dear $name,<br><br>Your account has been deleted from our system.<br><br>If this is a mistake, please contact the building administration.";

            $mail->send();
        } catch (Exception $e) {
            error_log("Email to $email failed: " . $mail->ErrorInfo);
        }
        header("Location: ../user_access.php?message=account_deleted");
        exit();
    } else {
        die("No tenant selected for deletion.");
    }
}

$sql = "SELECT user_id, first_name, last_name, email, room_id FROM userall WHERE user_id = " . $id;
$rs = mysqli_query($db_connection, $sql);
$tenants = mysqli_fetch_array($rs);

$roomUpdateStmt = $pdo->prepare("UPDATE room SET status = 'Available' WHERE room_id = ?");
$contractUpdateStmt = $pdo->prepare("UPDATE contract SET status = 'Deleted' WHERE user_id = ?");
$userUpdateStmt = $pdo->prepare("UPDATE user SET account_status = 'Deleted', room_id = null, desired_room = null WHERE user_id = ?");
$statusRequestUpdateStmt = $pdo->prepare("UPDATE status_request SET update_message = 'Archived' WHERE maintenance_request_id = ?");
$paymentStatusUpdateStmt = $pdo->prepare("UPDATE payment_status SET payment_status = 'Archived' WHERE payment_id = ?");
