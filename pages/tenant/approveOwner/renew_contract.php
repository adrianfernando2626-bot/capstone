<?php
if (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}
if (file_exists('../../includes/database.php')) {
    include_once('../../includes/database.php');
}
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $user_id = $_POST['user_id'] ?? 0;

    $sql = 'SELECT room_id FROM userall  
            WHERE user_id = ' . $user_id;
    $rs = mysqli_query($db_connection, $sql);
    $rw = mysqli_fetch_array($rs);

    $room_id = $rw['room_id'];

    $query_room = $pdo->prepare("SELECT room_amount, capacity, room_status FROM room WHERE room_id = ?");
    $query_room->execute([$room_id]);
    $room_data = $query_room->fetch(PDO::FETCH_ASSOC);
    $roomCapacity = (int) $room_data['capacity'];
    $roomStatus = $room_data['room_status'];

    $query_tenant_count = $pdo->prepare("SELECT COUNT(*) FROM userall WHERE desired_room = ? AND role = 'Tenant'");
    $query_tenant_count->execute([$selectedRoom]);
    $tenantCount = (int) $query_tenant_count->fetchColumn();

    if ($tenantCount >= $roomCapacity || $roomStatus === 'Occupied') {
        $_SESSION['warning_log_in'] = "The room capacity has been reached or it is already occupied, you cannot renew your contract.";
        header("Location: ../../login.php");
        exit();
    } else {
        $sql = $pdo->prepare("UPDATE user SET account_status = 'Waiting to Renew', desired_room = ?, room_id = null WHERE user_id = ?");
        $sql->execute([$room_id, $user_id]);
        header("Location: ../../login.php?message=renewal_success");
        exit();
    }
}
