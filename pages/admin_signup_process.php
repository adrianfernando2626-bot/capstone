<?php
session_start();
$host = "localhost";
$username = "root";
$password = "";
$database = "apartment";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$first = trim($_POST['first_name']);
$last = trim($_POST['last_name']);
$email = trim($_POST['email']);
$phone_number = trim($_POST['phone_number']);
$pass = $_POST['password'];
$confirm = $_POST['confirm_password_admin'];

if ($pass !== $confirm) {
    $_SESSION['admin_signup_warning'] = "Passwords do not match.";
    header("Location: login.php");
    exit();
}

// Check if email already exists
$stmt = $conn->prepare("SELECT * FROM personal_info WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $_SESSION['admin_signup_warning'] = "Email is already used.";
    header("Location: login.php");
    exit();
}
if (strlen($phone_number) !== 13) {
    $_SESSION['admin_signup_warning'] = "Please input a valid contact number";
    header("Location: login.php");
    exit();
}
if (substr($phone_number, 0, 4) !== '+639') {
    $_SESSION['admin_signup_warning'] = "Please insert a valid phone number that starts with +639";
    header("Location: login.php");
    exit();
}


// Insert into user
$role = 'Admin';
$today = date('Y-m-d');
$status = 'Active';
$payment_priv = 'Approved';
$tenant_priv = 'Approved';

$stmt = $conn->prepare("INSERT INTO user (role, date_registered, account_status, payment_priviledge, tenant_priviledge) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $role, $today, $status, $payment_priv, $tenant_priv);
$stmt->execute();
$user_id = $conn->insert_id;

// Insert into personal_info
$gender = "Prefer_not_to_say"; // default
$birthdate = '2000-01-01';
$address = 'N/A';
$img = 'default.png';

$stmt = $conn->prepare("INSERT INTO personal_info (user_id, first_name, last_name, middle_name, birthdate, email, gender, address, phone_number, img)
VALUES (?, ?, ?, '', ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issssssss", $user_id, $first, $last, $birthdate, $email, $gender, $address, $phone_number, $img);
$stmt->execute();

// Insert into credential
$hashed = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO credential (user_id, password) VALUES (?, ?)");
$stmt->bind_param("is", $user_id, $hashed);
$stmt->execute();

// Optional: mark in system_settings that admin was initialized
$conn->query("INSERT INTO system_settings (key_name, key_value) VALUES ('admin_initialized', '1')");

$_SESSION['admin_signup_success'] = "Admin account created successfully!";
header("Location: login.php?admin_signup_success=success");
exit();
