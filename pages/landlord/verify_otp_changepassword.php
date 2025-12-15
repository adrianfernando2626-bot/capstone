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
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../tenant/guest_logging_process/vendor/autoload.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) die("User not logged in.");

// Get user info
$query = "SELECT pi.email, pi.first_name, pi.role FROM userall pi WHERE pi.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$email = $user['email'];
$first_name = $user['first_name'];
$role = $user['role'];

$error = "";
$success = "";

// Handle OTP verification
if (isset($_POST['verify'])) {
    $otp_input = trim($_POST['otp']);

    $check = $conn->prepare("SELECT * FROM otp WHERE user_id = ? AND otp_code = ? AND expires_at > NOW()");
    $check->bind_param("is", $user_id, $otp_input);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // OTP is valid
        $_SESSION['verified_otp'] = true;
        $_SESSION['role'] = $role;
        header("Location: change_password.php"); // your password change form
        exit();
    } else {
        $error = "❌ Invalid or expired OTP.";
    }
}

// Handle Resend OTP
if (isset($_GET['resend']) && $_GET['resend'] == 1) {
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Clear old OTPs
    $conn->query("DELETE FROM otp WHERE user_id = $user_id");

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
        $mail->Subject = '🔄 Your OTP has been resent';
        $mail->Body = "<p>Hi $first_name,</p><p>Your new OTP is: <strong>$otp</strong>. It will expire in 10 minutes.</p>";

        $mail->send();
        $success = "✅ A new OTP has been sent to your email.";
    } catch (Exception $e) {
        $error = "❌ Failed to resend OTP: {$mail->ErrorInfo}";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Verify OTP</title>
    <link rel="stylesheet" href="../css/forgot.css">
</head>

<body>
    <div class="container">
        <h2>Verify OTP</h2>
        <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <?php if (!empty($success)) echo "<p style='color:green;'>$success</p>"; ?>

        <form method="POST">
            <label>Enter 6-digit OTP:</label><br>
            <input type="text" name="otp" maxlength="6" required><br><br>
            <button type="submit" name="verify">Verify</button>
            <button type="button" onclick="resendOTP()">Resend OTP</button>
            <?php
            $role = "";
            if ($_SESSION['role'] === 'Tenant') {
                $role = "../tenant/tenantprofile";
            } elseif ($_SESSION['role'] === 'Landlord') {
                $role = "landlordprofile";
            } elseif ($_SESSION['role'] === 'Admin') {
                $role = "../buildingowner/ownerprofile";
            }
            ?>
            <button type="button" onclick="window.location.href='<?php echo $role; ?>.php'">Back</button>
        </form>
    </div>

    <script>
        function resendOTP() {
            window.location.href = 'verify_otp_changepassword.php?resend=1';
        }
    </script>
</body>

</html>