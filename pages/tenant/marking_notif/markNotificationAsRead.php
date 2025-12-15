<?php
// Include database connection
include_once '../../includes/database.php'; // adjust if needed

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notif_title'])) {
    $notifTitle = $_POST['notif_title'];

    // Assuming you also have session started and user_id available
    session_start();
    $user_id = $_SESSION['user_id']; // Adjust if different

    $stmt = $pdo->prepare("UPDATE notification SET notif_status = 'read' WHERE notif_title = ? AND user_id = ?");
    if ($stmt->execute([$notifTitle, $user_id])) {
        echo "success";
    } else {
        echo "fail";
    }
} else {
    echo "invalid";
}
