<?php
session_start();
if (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}
if (!isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_password.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = "❌ Passwords do not match.";
    } else {
        $host = "localhost";
        $username = "root";
        $password = "";
        $database = "apartment";

        $conn = new mysqli($host, $username, $password, $database);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $user_id = $_SESSION['reset_user_id'];

        $stmt = $conn->prepare("UPDATE credential SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);

        if ($stmt->execute()) {
            unset($_SESSION['reset_user_id']);
            $_SESSION['success_message'] = "✅ Password reset successful. You can now log in.";
            header("Location: login.php?reset=success");
            exit();
        } else {
            $error = "❌ Failed to reset password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="css/forgot.css">
</head>

<body>
    <div class="container">
        <h2>Reset Password</h2>

        <?php if (!empty($error)): ?>
            <p style="color:red;"><?= $error ?></p>
        <?php endif; ?>

        <form method="POST">
            <label>New Password:</label>
            <input type="password" name="new_password" id="reset_password" placeholder="New Password" required />

            <label>Confirm New Password:</label>
            <input type="password" name="confirm_password" id="confirm_reset_password" placeholder="Confirm Password" required />

            <div class="show-password-container">
                <input type="checkbox" id="show-reset-password" />
                <label for="show-reset-password">Show Password</label>
            </div>

            <button type="submit">Reset Password</button>
        </form>
    </div>

    <script>
        const resetPassword = document.getElementById("reset_password");
        const confirmPassword = document.getElementById("confirm_reset_password");
        const showPasswordCheckbox = document.getElementById("show-reset-password");

        showPasswordCheckbox.addEventListener("change", function() {
            const type = this.checked ? "text" : "password";
            resetPassword.type = type;
            confirmPassword.type = type;
        });
    </script>
</body>

</html>