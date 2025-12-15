<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'tenant/guest_logging_process/vendor/autoload.php';

$host = "localhost";
$username = "root";
$password = "";
$database = "apartment";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);


$error = $success = "";

if (!isset($_SESSION['pending_user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['pending_user_id'];
$role = $_SESSION['pending_role'];


if (isset($_POST['verify'])) {
    if (!empty(trim($_POST['otp']))) {
        $otp = $_POST['otp'];
        $stmt = $conn->prepare("SELECT otp_code, expires_at FROM otp WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("i", $user_id,);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $expires = new DateTime($row['expires_at']);
            $now = new DateTime();
            if ($otp === $row['otp_code'] && $now < $expires) {
                // OTP valid

                // ✅ If user checked Remember Me during login, set cookie for 24h
                if (!empty($_SESSION['remember_me'])) {
                    $cookie_name = "remember_user_" . $user_id;
                    setcookie($cookie_name, "yes", time() + (86400 * 30), "/"); // 1 day = 86400 seconds kapag 1 month lagyan lang ng * 30
                    unset($_SESSION['remember_me']); // optional: cleanup   
                }

                // ✅ Cleanup OTP session

                $_SESSION['user_id'] = $user_id;
                $_SESSION['role'] = $role;
                unset($_SESSION['pending_user_id'], $_SESSION['pending_role']);

                header("Location: includes/update_expired_contract.php");
                exit();
            } else {
                $error = "Invalid or expired OTP.";
            }
        }
    } else {
        $error = "Please insert an OTP";
    }
} elseif (isset($_POST['resend'])) {
    // Fetch email
    $stmt = $conn->prepare("SELECT pi.email, pi.first_name FROM personal_info pi JOIN user u ON pi.user_id = u.user_id WHERE u.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $email = $row['email'];
        $first_name = $row['first_name'];
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        $insert = $conn->prepare("INSERT INTO otp (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
        $insert->bind_param("iss", $user_id, $otp, $expires);
        $insert->execute();

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
            $mail->Subject = 'Resent OTP - Tenant Login';
            $mail->Body = "<p>Hello $first_name,</p><p>Your new OTP is <strong>$otp</strong> and it expires in 5 minutes.</p>";
            $mail->send();
            $success = "OTP resent successfully.";
        } catch (Exception $e) {
            $error = "Failed to resend OTP.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Login OTP</title>
    <link rel="stylesheet" href="css/forgot.css">
</head>

<body>
    <div class="container">
        <h2>OTP Verification</h2>
        <p>Please check your email for a 6-digit code.</p>

        <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
        <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>

        <form method="POST">
            <input type="text" name="otp" placeholder="Enter OTP" maxlength="6">
            <div class="buttons">
                <button type="submit" name="verify">Verify</button>
                <button type="submit" name="resend" class="resend">Resend OTP</button>

            </div>
        </form>
    </div>
</body>

</html>