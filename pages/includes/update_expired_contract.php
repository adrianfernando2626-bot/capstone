<?php
if (file_exists('database.php')) {
    include_once('database.php');
}
session_start();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$today = date("Y-m-d");

try {
    // Check last execution date
    $check = $db_connection->prepare("SELECT setting_value FROM settings WHERE setting_key = 'last_expiration_check'");
    $check->execute();
    $check->bind_result($last_check);
    $hasResult = $check->fetch();
    $check->close();

    if (!$hasResult || $last_check !== $today) {
        // Run update for expired contracts
        $update = $db_connection->prepare("UPDATE contract SET contract_status = 'Expired', update_status = 'Approved' WHERE end_date < ?");
        $update->bind_param("s", $today);
        $update->execute();
        $affected = $update->affected_rows;
        $update->close();
        // Run update for room expired contracts
        // Select all expired contracts with room_id and user_id
        $select_room = $db_connection->prepare("SELECT a.room_id, c.user_id
                                                FROM contract c
                                                JOIN userall a ON a.user_id = c.user_id
                                                WHERE c.contract_status = 'Expired'");
        $select_room->execute();
        $result = $select_room->get_result();

        while ($row = $result->fetch_assoc()) {
            $room_id = $row['room_id'];
            $user_id = $row['user_id'];

            // Update room status
            $update_room = $db_connection->prepare("UPDATE room SET room_status = 'Available' WHERE room_id = ?");
            $update_room->bind_param("i", $room_id);
            $update_room->execute();
            $update_room->close();

            // Update user account status
            $update_user = $db_connection->prepare("UPDATE user SET account_status = 'Renewal for Contract', desired_room = 0 WHERE user_id = ?");
            $update_user->bind_param("i", $user_id);
            $update_user->execute();
            $update_user->close();
        }
        $select_room->close();


        // Update last run date in settings
        $upsert = $db_connection->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES ('last_expiration_check', ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $upsert->bind_param("s", $today);
        $upsert->execute();
        $upsert->close();

        $_SESSION['contract_update_msg'] = "$affected contract(s) marked as expired automatically today.";

        switch (strtolower($role)) {
            case 'tenant':
                header("Location: ../tenant/tenantdashboard.php");

                break;
            case 'landlord':
                header("Location: ../landlord/landlord_dashboard.php");
                break;
            case 'admin':
                header("Location: ../buildingowner/ownerdashboard.php");

                break;
        }
        exit();
    } else {

        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = $role;
        unset($_SESSION['pending_user_id'], $_SESSION['pending_role']);
        $_SESSION['contract_update_msg'] = "Contracts were already checked for expiration earlier today";


        switch (strtolower($role)) {
            case 'tenant':
                header("Location: ../tenant/tenantdashboard.php");

                break;
            case 'landlord':
                header("Location: ../landlord/landlord_dashboard.php");
                break;
            case 'admin':
                header("Location: ../buildingowner/ownerdashboard.php");

                break;
        }
        exit();
        // Already ran today; skip update
    }
} catch (mysqli_sql_exception $e) {
    error_log("Contract expiration update error: " . $e->getMessage());
    $_SESSION['contract_update_msg'] = "An error occurred while updating contract statuses.";
}
