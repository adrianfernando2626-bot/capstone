<?php
require_once '../../tenant/guest_logging_process/vendor/autoload.php';
include_once '../../includes/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$selectedTenants = $_POST['selected_tenant'] ?? [];

if (empty($selectedTenants)) {
    die("No tenants selected for deletion.");
}

$placeholders = implode(',', array_fill(0, count($selectedTenants), '?'));
$stmt = $pdo->prepare("SELECT user_id, first_name, last_name, email, room_id, deletion_approval 
                       FROM userall WHERE user_id IN ($placeholders)");
$stmt->execute($selectedTenants);
$tenants = $stmt->fetchAll();

$roomUpdateStmt = $pdo->prepare("UPDATE room SET room_status = 'Available' WHERE room_id = ?");
$contractUpdateStmt = $pdo->prepare("UPDATE contract SET contract_status = 'Deleted' WHERE user_id = ?");
$userUpdateStmt = $pdo->prepare("UPDATE user SET account_status = 'Deleted', room_id = null, desired_room = null WHERE user_id = ?");
$statusRequestUpdateStmt = $pdo->prepare("UPDATE status_request SET update_message = 'Archived' WHERE maintenance_request_id = ?");
$paymentStatusUpdateStmt = $pdo->prepare("UPDATE payment_status SET payment_status = 'Archived' WHERE payment_id = ?");
$notifyUpdateStmt = $pdo->prepare("UPDATE notification SET notif_status = 'Archived' WHERE user_id = ?");

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
    // Arrays to collect names & ids
    $notApprovedTenants = [];
    $notApprovedIds = [];

    foreach ($tenants as $tenant) {
        $user_id = $tenant['user_id'];
        $room_id = $tenant['room_id'];
        $email   = $tenant['email'];
        $deletion_approval = $tenant['deletion_approval'];
        $name    = $tenant['first_name'] . ' ' . $tenant['last_name'];

        if ($deletion_approval === 'Approved') {
            // If admin already approved deletion, perform deletion
            $roomUpdateStmt->execute([$room_id]);
            $contractUpdateStmt->execute([$user_id]);
            $userUpdateStmt->execute([$user_id]);
            $notifyUpdateStmt->execute([$user_id]);

            // Archive maintenance requests
            $stmtMaintenance = $pdo->prepare("SELECT maintenance_request_id FROM maintenance_request WHERE room_id = ?");
            $stmtMaintenance->execute([$room_id]);
            $maintenance_ids = $stmtMaintenance->fetchAll(PDO::FETCH_COLUMN);
            foreach ($maintenance_ids as $mid) {
                $statusRequestUpdateStmt->execute([$mid]);
            }

            // Archive payments
            $stmtContract = $pdo->prepare("SELECT contract_id FROM contract WHERE user_id = ?");
            $stmtContract->execute([$user_id]);
            $contract_ids = $stmtContract->fetchAll(PDO::FETCH_COLUMN);

            $stmtPayment = $pdo->prepare("SELECT payment_id FROM payment WHERE contract_id = ?");
            foreach ($contract_ids as $cid) {
                $stmtPayment->execute([$cid]);
                $payment_ids = $stmtPayment->fetchAll(PDO::FETCH_COLUMN);

                foreach ($payment_ids as $pid) {
                    $paymentStatusUpdateStmt->execute([$pid]);
                }
            }

            // Notify tenant of deletion
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'adrianfernando2626@gmail.com';
                $mail->Password = 'cxwqqwktqevyogmt';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('adrianfernando2626@gmail.com', 'Landlord');
                $mail->addAddress($email, $name);
                $mail->isHTML(true);
                $mail->Subject = 'Account Deletion Notice';
                $mail->Body = "Dear $name,<br><br>Your account has been deleted from our system.<br><br>If this is a mistake, please contact the building administration.";

                $mail->send();
            } catch (Exception $e) {
                error_log("Email to $email failed: " . $mail->ErrorInfo);
            }
        } else {
            // Collect not approved tenants
            $notApprovedTenants[] = $name;
            $notApprovedIds[] = $user_id;
        }
    }

    // If there are tenants not approved, notify the Admin
    if (!empty($notApprovedTenants)) {
        $stmtlandlordadmin = $pdo->prepare("SELECT email, CONCAT(first_name, ' ', last_name) AS admin_name 
                                            FROM userall WHERE role = 'Admin' LIMIT 1");
        $stmtlandlordadmin->execute();
        $admin = $stmtlandlordadmin->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            $tenantList = implode("<br> - ", $notApprovedTenants);
            $idList = implode(",", $notApprovedIds); // pass to URL

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
                $mail->addAddress($admin['email'], $admin['admin_name']);
                $mail->isHTML(true);
                $mail->Subject = 'Tenant Deletion Approval Required';
                $mail->Body = "Dear <strong>Admin</strong>,<br><br>
                A landlord is trying to delete the following tenant accounts without your approval:<br>
                <br> - $tenantList
                <br><br>
                Click here to <a href='http://localhost/capstone_semifinal(edited)/capstone_semifinal/pages/approved_deletion.php?user_ids=$idList'>approve</a> the deletion.<br><br>
                Or <a href='http://localhost/capstone_semifinal(edited)/capstone_semifinal/pages/login.php'>Login</a> to review.";

                $mail->send();
            } catch (Exception $e) {
                error_log("Email to admin failed: " . $mail->ErrorInfo);
            }
        }
    }
}

header("Location: ../user_access.php?message=accounts_deleted");
exit();
