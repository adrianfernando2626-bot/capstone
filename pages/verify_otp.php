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

$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $enteredOtp = trim($_POST['otp']);
    $currentTime = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("SELECT c.credential_id 
    FROM user u 
    JOIN personal_info pi ON u.user_id = pi.user_id 
    JOIN credential c ON u.user_id = c.user_id 
    WHERE pi.email = ?");

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $user_id = $user['credential_id'];

        $stmt = $conn->prepare("SELECT * FROM otp WHERE user_id = ? AND otp_code = ? AND expires_at >= ? AND used = 0");
        $stmt->bind_param("iss", $user_id, $enteredOtp, $currentTime);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $conn->query("UPDATE otp SET used = 1 WHERE user_id = $user_id");

            $_SESSION['reset_user_id'] = $user_id;
            header("Location: reset_password.php");
            exit();
        } else {
            $error = "❌ Invalid or expired OTP.";
        }
    } else {
        $error = "❌ User not found.";
    }
}
if (isset($_GET['resend']) && isset($_SESSION['reset_email'])) {
    $query = "SELECT u.user_id, pi.first_name, c.credential_id 
    FROM user u 
    JOIN personal_info pi ON u.user_id = pi.user_id 
    JOIN credential c ON u.user_id = c.user_id 
    WHERE pi.email = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
        $first_name = $row['first_name'];
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $stmtDel = $conn->prepare("DELETE FROM otp WHERE user_id = ?");
        $stmtDel->bind_param("i", $user_id);
        $stmtDel->execute();


        $stmtInsert = $conn->prepare("INSERT INTO otp (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
        $stmtInsert->bind_param("iss", $user_id, $otp, $expires);
        $stmtInsert->execute();


        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username   = 'adrianfernando2626@gmail.com';
            $mail->Password   = 'cxwqqwktqevyogmt';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('buanmoncarlo725@gmail.com', 'Tenant System');
            $mail->addAddress($email, $first_name);
            $mail->isHTML(true);
            $mail->Subject = 'Your New OTP for Password Reset';
            $mail->Body = "<p>Hi $first_name,</p><p>Your new OTP is: <strong>$otp</strong><br>This OTP expires in 10 minutes.</p>";

            $mail->send();

            // ✅ Redirect to avoid resending on refresh
            $_SESSION['success_message'] = "✅ OTP has been resent to your email.";
            header("Location: verify_otp.php");
            exit();
        } catch (Exception $e) {
            $error = "❌ Failed to resend OTP. Error: {$mail->ErrorInfo}";
        }
    } else {
        $error = "❌ Email not found.";
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Verify OTP</title>
    <link rel="stylesheet" href="css/forgot.css">
</head>

<body>
    <div class="container">
        <h2>Verify OTP</h2>

        <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <?php
        if (!empty($_SESSION['success_message'])) {
            echo "<p style='color:green;'>" . $_SESSION['success_message'] . "</p>";
            unset($_SESSION['success_message']);
        }
        ?>

        <form method="POST">
            <label for="otp">Enter 6-digit OTP</label><br>
            <input type="text" name="otp" maxlength="6" required><br><br>
            <button type="submit" name="verify">Verify</button>
            <button type="button" id="resendBtn">Resend OTP</button>
        </form>
    </div>

    <script>
        document.getElementById('resendBtn').addEventListener('click', function() {
            window.location.href = 'verify_otp.php?resend=1';
        });
    </script>
</body>

</html>