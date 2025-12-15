<?php
session_start();
if (file_exists('../includes/database.php')) {
    include_once('../includes/database.php');
}

date_default_timezone_set('Asia/Manila');

$host = "localhost";
$username = "root";
$password = "";
$database = "apartment";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../tenant/guest_logging_process/vendor/autoload.php';



$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("User not logged in.");
}

// Get email and name from DB
$query = "SELECT pi.email, pi.first_name FROM personal_info pi WHERE pi.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $email = $row['email'];
    $first_name = $row['first_name'];
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));


    // Remove old OTPs if any
    $conn->query("DELETE FROM otp WHERE user_id = $user_id");

    // Insert new OTP
    $stmtInsert = $conn->prepare("INSERT INTO otp (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
    $stmtInsert->bind_param("iss", $user_id, $otp, $expires);
    $stmtInsert->execute();

    // Save email for verification step
    $_SESSION['reset_email'] = $email;

    // Send email with OTP
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username   = 'adrianfernando2626@gmail.com';
        $mail->Password   = 'cxwqqwktqevyogmt';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('adrianfernando2626@gmail.com', 'Tenant Management');
        $mail->addAddress($email, $first_name);
        $mail->isHTML(true);
        $mail->Subject = '🔒 OTP for Changing Password';
        $mail->Body = "<p>Hi $first_name,</p><p>Your OTP to change your password is: <strong>$otp</strong><br>This will expire in 10 minutes.</p>";

        $mail->send();

        header("Location: verify_otp_changepassword.php");
        exit();
    } catch (Exception $e) {
        echo "Failed to send OTP. Error: " . $mail->ErrorInfo;
    }
} else {
    echo "❌ User not found.";
}
