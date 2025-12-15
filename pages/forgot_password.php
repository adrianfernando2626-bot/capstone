<?php
session_start();
date_default_timezone_set('Asia/Manila'); // PHP timezone
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'tenant/guest_logging_process/vendor/autoload.php';

$host = "localhost";
$username = "root";
$password = "";
$database = "apartment";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $stmt = $conn->prepare("SELECT us.first_name, us.user_id 
    FROM userall us 
    JOIN credential c ON c.user_id = us.user_id
    WHERE us.email = ? LIMIT 1");

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $user_id = $user['user_id'];
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $stmtDel = $conn->prepare("DELETE FROM otp WHERE user_id = ?");
        $stmtDel->bind_param("i", $user_id);
        $stmtDel->execute();


        $stmt = $conn->prepare("INSERT INTO otp (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $otp, $expires_at);
        $stmt->execute();

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
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Reset Your Password';
            $mail->Body = "<p>Hi {$user['first_name']},</p><p>Your OTP is: <strong>$otp</strong><br>This will expire in 10 minutes.</p>";

            $_SESSION['reset_email'] = $email;
            $mail->send();
            header("Location: verify_otp.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['warning_log_in'] = "Email error: {$mail->ErrorInfo}";
            header("Location: login.php?message=" . $_SESSION['warning_log_in']);
            exit();
        }
    } else {
        $_SESSION['warning_log_in'] = "Email not found.";
        header("Location: login.php?message=" . $_SESSION['warning_log_in']);
        exit();
    }
    $stmt->close();
}
