<?php
session_start();
if (file_exists('../includes/database.php')) {
    include_once('../includes/database.php');
}
if (!isset($_SESSION['user_id']) || !isset($_SESSION['verified_otp']) ||  !isset($_SESSION['role'])) {
    header("Location: landlordprofile.php");
    exit();
}

$host = "localhost";
$username = "root";
$password = "";
$database = "apartment";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";
$success = "";
$role = $_SESSION['role'];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = "❌ Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "❌ Password must be at least 6 characters.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $user_id = $_SESSION['user_id'];
        $stmt2 = $pdo->prepare("
                SELECT credential_id 
                FROM credential 
                WHERE user_id = ?
                LIMIT 1
            ");
        $stmt2->execute([$user_id]);
        $credential_id = $stmt2->fetchColumn();

        $stmt = $conn->prepare("UPDATE credential SET password = ? WHERE credential_id = ?");
        $stmt->bind_param("si", $hashed_password, $credential_id);

        if ($stmt->execute()) {
            unset($_SESSION['verified_otp']); // clear OTP check
            $success = "✅ Password successfully changed.";
            switch (strtolower($role)) {
                case 'tenant':
                    header("Location: ../tenant/tenantprofile.php?message=password_change");

                    break;
                case 'landlord':
                    header("Location: ../landlord/landlordprofile.php?message=password_change");
                    break;
                case 'admin':
                    header("Location: ../buildingowner/ownerprofile.php?message=password_change");

                    break;
            }
            exit();
        } else {
            $error = "❌ Failed to update password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Change Password</title>
    <link rel="stylesheet" href="../css/forgot.css">
</head>

<body>
    <div class="container">
        <h2>Change Password</h2>
        <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <?php if (!empty($success)) echo "<p style='color:green;'>$success</p>"; ?>

        <form method="POST">
            <label>New Password:</label>
            <input type="password" name="new_password" id="new_password" required>

            <label>Confirm New Password:</label>
            <input type="password" name="confirm_password" id="confirm_password" required>

            <label><input type="checkbox" onclick="togglePassword()"> Show Password</label>

            <button type="submit">Change Password</button>
        </form>

        <script>
            function togglePassword() {
                const newPass = document.getElementById("new_password");
                const confirmPass = document.getElementById("confirm_password");

                const isVisible = newPass.type === "text";

                newPass.type = isVisible ? "password" : "text";
                confirmPass.type = isVisible ? "password" : "text";
            }
        </script>


    </div>
</body>

</html>