<?php
include_once('../../includes/database.php');


$sql = "SELECT COUNT(*) as total_notification 
        FROM notification 
        WHERE user_id = ? AND notif_status = 'unread'";
$stmt = $db_connection->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo $result['total_notification'];
