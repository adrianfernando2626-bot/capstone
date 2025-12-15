<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'tenant/guest_logging_process/vendor/autoload.php';
if (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}
if (file_exists('../includes/database.php')) {
    include_once('../includes/database.php');
}

session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $_SESSION['old_input'] = $_POST;

    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $name = $first_name . ' ' . $last_name;
    $middle_name = $_POST['middle_name'] ?? '';
    $email = $_POST['email'];
    $phone_number = $_POST['phone_number'];
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $gender = $_POST['gender'];
    $building_id = $_POST['building_id'];
    $role = 'Landlord';
    $date_registered = date('Y-m-d');
    $account_status = $_POST['account_status'];

    $payment_privilege = isset($_POST['payment_privilege']) ? $_POST['payment_privilege'] : 'Not Approved';
    $tenant_privilege = isset($_POST['tenant_privilege']) ? $_POST['tenant_privilege'] : 'Not Approved';

    if ($password !== $confirm_password) {
        $_SESSION['show_modal'] = 'landlordlistModal';
        $_SESSION['warning_signing_landlord'] = "Passwords do not match.";
        header("Location: buildingowner/tenantmanage.php");
        exit();
    }
    $checkStmt = $db_connection->prepare("SELECT * FROM userall WHERE building_id = ? AND account_status = 'Active' AND role = 'Landlord'");
    $checkStmt->bind_param("i", $building_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['show_modal'] = 'landlordlistModal';
        $_SESSION['warning_signing_landlord'] = "A landlord has already been set to this building";
        header("Location: buildingowner/tenantmanage.php");
        exit();
    }
    $checkStmt = $db_connection->prepare("SELECT * FROM userall WHERE email = ? AND account_status != 'Deleted'");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['show_modal'] = 'landlordlistModal';
        $_SESSION['warning_signing_landlord'] = "Email already exists. Please use another.";
        header("Location: buildingowner/tenantmanage.php");
        exit();
    }
    $checkStmt->close();

    if (
        strlen($phone_number) === 13 &&
        substr($phone_number, 0, 4) === '+639' &&
        strlen($password) >= 8
    ) {
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $db_connection->begin_transaction();

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmtUser = $db_connection->prepare("INSERT INTO user (building_id, role, date_registered, account_status, payment_priviledge, tenant_priviledge) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtUser->bind_param("isssss", $building_id,  $role, $date_registered, $account_status, $payment_privilege, $tenant_privilege);
            $stmtUser->execute();
            $user_id = $stmtUser->insert_id;
            $stmtUser->close();

            $stmtInfo = $db_connection->prepare("INSERT INTO personal_info (user_id, first_name, last_name, middle_name, email, gender, phone_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtInfo->bind_param("issssss", $user_id, $first_name, $last_name, $middle_name, $email, $gender, $phone_number);
            $stmtInfo->execute();
            $stmtInfo->close();

            $stmtCreds = $db_connection->prepare("INSERT INTO credential (user_id, password) VALUES (?, ?)");
            $stmtCreds->bind_param("is", $user_id, $hashed_password);
            $stmtCreds->execute();
            $stmtCreds->close();
            $buildingstmt = $pdo->prepare("UPDATE building SET building_is_active = 1 WHERE building_id = ?");
            $buildingstmt->execute([$building_id]);

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'adrianfernando2626@gmail.com';
                $mail->Password = 'cxwqqwktqevyogmt';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('adrianfernando2626@gmail.com', 'Owner');
                $mail->addAddress($email, $name);
                $mail->isHTML(true);
                $mail->Subject = 'Account Update Notification';
                $mail->Body = "Dear $name,<br><br>Congrats! You have been chosen as of the landlord/landlady to Tita Ria's Apartment<br><br>This is your login credentials:<br><br>Username: $email<br><br>Password: $password<br><br>You may now log in to your account: <a href='localhost/capstone/pages/login.php'>Click here to login</a><br><br><strong>This is a generated system email do not reply</strong><br><br>Thank you.";

                $mail->send();
            } catch (Exception $e) {
                error_log("Email failed for $email: " . $mail->ErrorInfo);
            }

            $db_connection->commit();
            unset($_SESSION['old_input']);
            header("Location: buildingowner/tenantmanage.php?message=signing_successful");
            exit();
        } catch (mysqli_sql_exception $e) {
            $db_connection->rollback();
            $_SESSION['warning_signing_landlord'] = "Database error: " . $e->getMessage();
            header("Location: buildingowner/tenantmanage.php");
            exit();
        }
    } else {
        $_SESSION['show_modal'] = 'landlordlistModal';
        $_SESSION['warning_signing_landlord'] = match (true) {
            strlen($password) < 8 => "Password must be at least 8 characters long.",
            strlen($phone_number) !== 13 => "Please input a valid contact number.",
            substr($phone_number, 0, 4) !== '+639' => "Please insert a valid phone number that starts with +639",
            default => "Validation failed. Please try again.",
        };
        header("Location: buildingowner/tenantmanage.php");
        exit();
    }
}
