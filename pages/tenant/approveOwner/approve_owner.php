
<?php
if (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}
if (file_exists('../../includes/database.php')) {
    include_once('../../includes/database.php');
}
session_start();
$user_id = $_SESSION['user_id'];

$id = $_GET['id'] ?? 0;
if ($id) {
    $sql = $pdo->prepare("UPDATE contract SET update_status = 'Approved' WHERE contract_id = ?");
    $sql->execute([$id]);

    header("Location: ../tenantdashboard.php?message=approval_success");
    exit();
}
