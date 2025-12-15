<?php
require_once 'tenant/guest_logging_process/vendor/autoload.php';
include_once 'includes/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_GET['user_ids']) || empty($_GET['user_ids'])) {
    header("Location: login.php?message=no_ids_found");
    exit();
}

$userIds = explode(",", $_GET['user_ids']);
$placeholders = implode(',', array_fill(0, count($userIds), '?'));

// 1. Update deletion_approval for selected tenants
$stmt = $pdo->prepare("UPDATE user SET deletion_approval = 'Approved' WHERE user_id IN ($placeholders)");
$stmt->execute($userIds);

// 2. Get room_ids & building_ids for those users
$stmtRoom = $pdo->prepare("SELECT DISTINCT r.room_id, r.building_id 
                           FROM user u 
                           JOIN room r ON u.room_id = r.room_id
                           WHERE u.user_id IN ($placeholders)");
$stmtRoom->execute($userIds);
$room_buildings = $stmtRoom->fetchAll(PDO::FETCH_ASSOC);

// Collect building_ids
$buildingIds = array_column($room_buildings, 'building_id');

if (!empty($buildingIds)) {
    $inBuildings = implode(',', array_fill(0, count($buildingIds), '?'));

    // 3. Get Landlords for these buildings
    $stmtLandlords = $pdo->prepare("SELECT user_id, email 
                                    FROM userall 
                                    WHERE role = 'Landlord' 
                                    AND building_id IN ($inBuildings)");
    $stmtLandlords->execute($buildingIds);
    $landlords = $stmtLandlords->fetchAll(PDO::FETCH_ASSOC);

    // 4. Send email notifications
    foreach ($landlords as $landlord) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'adrianfernando2626@gmail.com';
            $mail->Password = 'cxwqqwktqevyogmt'; // use app password
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('adrianfernando2626@gmail.com', 'System Admin');
            $mail->addAddress($landlord['email']);
            $mail->isHTML(true);
            $mail->Subject = 'Tenant Deletion Approval Granted';
            $mail->Body = "Dear Landlord,<br><br>
            The building admin has approved your request to delete certain tenant accounts.<br>
            You may now proceed to perform the deletion from your dashboard.<br><br>
            Regards,<br>System Admin";

            $mail->send();
        } catch (Exception $e) {
            error_log("Email to landlord failed: " . $mail->ErrorInfo);
        }
    }
}

// 5. Redirect back
header("Location: login.php?message=update_approval_success");
exit();
