<?php
if (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}
if (file_exists('../../includes/database.php')) {
    include_once('../../includes/database.php');
}

$id = $_GET['id'] ?? 0;


$sql1 = "DELETE FROM guest_logs WHERE id = $id";

if (mysqli_query($db_connection, $sql1)) {

    $file_qr_img = 'qrcodes/qr_' . $id . '.png';
    $file_qr_pdf = 'pdfs/guest_pass_' . $id . '.pdf';

    if (file_exists($file_qr_img) & file_exists($file_qr_pdf)) {
        if (unlink($file_qr_img) & unlink($file_qr_pdf)) {
            header("Location: ../tenantguestlog.php?message=guest_deleted");
            exit();
        } else {
            echo "Error deleting the file.";
        }
    } else {
        echo "File does not exist.";
    }
} else {
    die("Error deleting account: " . mysqli_error($db_connection));
}
