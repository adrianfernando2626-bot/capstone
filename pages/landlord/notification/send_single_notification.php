<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../../tenant/guest_logging_process/vendor/autoload.php';
$host = "localhost";
$username = "root";
$password = "";
$database = "apartment";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    echo "DB connection failed.";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['room_id'])) {
    $room_id = $_POST['room_id'];
    $notif_title = $_POST['notif_title'];
    $notif_text = $_POST['notif_text'];
    $tenantRes = $conn->query("
            SELECT u.user_id
            FROM userall u
            WHERE u.room_id = $room_id AND u.role = 'Tenant' AND u.account_status = 'Active'
        ");

    while ($tenant = $tenantRes->fetch_assoc()) {
        $user_id = $tenant['user_id'];
        $query =    "
                    SELECT pi.*, p.*
                    FROM user u
                    JOIN contract c ON u.user_id = c.user_id
                    JOIN payment p ON c.contract_id = p.contract_id
                    JOIN personal_info pi ON u.user_id = pi.user_id
                    WHERE u.user_id = ?
                    ORDER BY p.due_date DESC LIMIT 1
                    ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $email = $row['email'];
            $name = $row['first_name'];
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
                $mail->addAddress($email, $name);

                $mail->isHTML(true);
                $mail->Subject = "Message from Landlord";
                $mail->Body = "
                <p>Hi <strong>$name</strong>,</p>
                <p>About <strong>$notif_title</strong></p>
                <p>'$notif_text.'</p>
                <p><br>Thank you,<br>Apartment Management System</p>
            ";
                $mail->send();
            } catch (Exception $e) {
                echo "Notification saved. Email failed: {$mail->ErrorInfo}";
                header('Location: ../tenant_manage.php?message=warning_internet');
                exit;
            }
            $insert = $conn->prepare("INSERT INTO notification (user_id, notif_title, notif_text, date_created, notif_status) VALUES (?, ?, ?, NOW(), 'unread')");
            $insert->bind_param("iss", $user_id, $notif_title, $notif_text);
            $insert->execute();
            $insert->close();
        } else {
            echo "No info found for this user.";
        }

        $stmt->close();
    }
    header("Location: ../tenant_manage.php?message=message_success");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $notif_title = $_POST['notif_title'];
    $notif_text = $_POST['notif_text'];

    $query = "
    SELECT pi.email, pi.first_name, rp.expected_amount_due, p.due_date
    FROM user u
    JOIN contract c ON u.user_id = c.user_id
    JOIN payment p ON c.contract_id = p.contract_id
    JOIN rent_payment rp ON rp.payment_id = p.payment_id
    JOIN personal_info pi ON u.user_id = pi.user_id
    WHERE u.user_id = ?
    ORDER BY p.due_date DESC LIMIT 1
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $email = $row['email'];
        $name = $row['first_name'];

        $insert = $conn->prepare("INSERT INTO notification (user_id, notif_title, notif_text, date_created, notif_status) VALUES (?, ?, ?, NOW(), 'unread')");
        $insert->bind_param("iss", $user_id, $notif_title, $notif_text);
        $insert->execute();
        $insert->close();

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
            $mail->addAddress($email, $name);

            $mail->isHTML(true);
            $mail->Subject = "Message from Landlord";
            $mail->Body = "
                <p>Hi <strong>$name</strong>,</p>
                <p>About <strong>$notif_title</strong></p>
                <p>'$notif_text.'</p>
                <p><br>Thank you,<br>Apartment Management System</p>
            ";

            $mail->send();
            header("Location: ../tenant_manage.php?message=message_success");
            exit;
        } catch (Exception $e) {
            echo "Notification saved. Email failed: {$mail->ErrorInfo}";
            header('Location: ../tenant_manage.php?message=message_success');
            exit;
        }
    } else {
        echo "No info found for this user.";
    }

    $stmt->close();
}
$conn->close();
