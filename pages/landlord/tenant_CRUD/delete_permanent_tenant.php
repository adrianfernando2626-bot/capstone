<?php
include_once '../../includes/database.php';
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
    try {
        $pdo->beginTransaction();

        $stmtMaintenance = $pdo->prepare("SELECT user_id, room_id FROM user WHERE account_status = 'Deleted'");
        $stmtMaintenance->execute();
        $user_ids = $stmtMaintenance->fetchAll(PDO::FETCH_ASSOC);

        $deleteUser = $pdo->prepare("DELETE FROM user WHERE user_id = ?");
        $deletePersonal = $pdo->prepare("DELETE FROM personal_info WHERE user_id = ?");
        $deleteCredential = $pdo->prepare("DELETE FROM credential WHERE user_id = ?");
        $deleteGuest = $pdo->prepare("DELETE FROM guest_logs WHERE user_id = ?");
        $deleteContract = $pdo->prepare("DELETE FROM contract WHERE user_id = ?");
        $deletePayment = $pdo->prepare("DELETE FROM payment WHERE contract_id = ?");
        $deletePaymentStatus = $pdo->prepare("DELETE FROM payment_status WHERE payment_id = ?");
        $deleteMaintenance = $pdo->prepare("DELETE FROM maintenance_request WHERE room_id = ?");
        $deleteStatusRequest = $pdo->prepare("DELETE FROM status_request WHERE maintenance_request_id = ?");
        $deleteNotify = $pdo->prepare("DELETE FROM notification WHERE user_id = ?");


        $stmtContract = $pdo->prepare("SELECT contract_id FROM contract WHERE user_id = ?");
        $stmtPayment = $pdo->prepare("SELECT payment_id FROM payment WHERE contract_id = ?");
        $stmtMaintenanceId = $pdo->prepare("SELECT maintenance_request_id FROM maintenance_request WHERE room_id = ?");

        foreach ($user_ids as $row) {
            $user_id = $row['user_id'];
            $room_id = $row['room_id'];

            $stmtMaintenanceId->execute([$room_id]);
            $maintenance_ids = $stmtMaintenanceId->fetchAll(PDO::FETCH_COLUMN);
            foreach ($maintenance_ids as $mid) {
                $deleteStatusRequest->execute([$mid]);
            }

            $deleteMaintenance->execute([$room_id]);

            $stmtContract->execute([$user_id]);
            $contract_ids = $stmtContract->fetchAll(PDO::FETCH_COLUMN);

            foreach ($contract_ids as $contract_id) {
                $stmtPayment->execute([$contract_id]);
                $payment_ids = $stmtPayment->fetchAll(PDO::FETCH_COLUMN);

                foreach ($payment_ids as $payment_id) {
                    $deletePaymentStatus->execute([$payment_id]);
                }

                $deletePayment->execute([$contract_id]);
            }

            $deleteContract->execute([$user_id]);
            $deleteCredential->execute([$user_id]);
            $deletePersonal->execute([$user_id]);
            $deleteGuest->execute([$user_id]);
            $deleteNotify->execute([$user_id]);
            $deleteUser->execute([$user_id]);
        }

        $pdo->commit();
        header("Location: ../user_access.php?message=accounts_deleted");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Failed to delete users: " . $e->getMessage();
        exit();
    }
}
