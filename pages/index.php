<?php
if (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}
require_once 'tenant/guest_logging_process/vendor/autoload.php';
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$host = "localhost";
$username = "root";
$password = "";
$database = "apartment";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$today = date("Y-m-d");

try {
    // ✅ Check last contract expiration execution date
    $check = $db_connection->prepare("SELECT setting_value FROM settings WHERE setting_key = 'last_expiration_check'");
    $check->execute();
    $check->bind_result($last_check);
    $hasResult = $check->fetch();
    $check->close();

    if (!$hasResult || $last_check !== $today) {
        // 🔹 Expire old contracts
        $update = $db_connection->prepare("UPDATE contract SET contract_status = 'Expired', update_status = 'Approved' WHERE end_date < ?");
        $update->bind_param("s", $today);
        $update->execute();
        $affected = $update->affected_rows;
        $update->close();

        // 🔹 Update expired room and user status
        $select_room = $db_connection->prepare("
            SELECT a.room_id, c.user_id
            FROM contract c
            JOIN userall a ON a.user_id = c.user_id
            WHERE c.contract_status = 'Expired'
        ");
        $select_room->execute();
        $result = $select_room->get_result();

        while ($row = $result->fetch_assoc()) {
            $room_id = $row['room_id'];
            $user_id = $row['user_id'];

            $update_room = $db_connection->prepare("UPDATE room SET room_status = 'Available' WHERE room_id = ?");
            $update_room->bind_param("i", $room_id);
            $update_room->execute();
            $update_room->close();

            $update_user = $db_connection->prepare("UPDATE user SET account_status = 'Renewal for Contract', desired_room = 0 WHERE user_id = ?");
            $update_user->bind_param("i", $user_id);
            $update_user->execute();
            $update_user->close();
        }
        $select_room->close();

        // ✅ Update last run date in settings
        $upsert = $db_connection->prepare("
            INSERT INTO settings (setting_key, setting_value)
            VALUES ('last_expiration_check', ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $upsert->bind_param("s", $today);
        $upsert->execute();
        $upsert->close();

        $_SESSION['contract_update_msg'] = "$affected contract(s) marked as expired automatically today.";
    } else {
        $_SESSION['contract_update_msg'] = "Contracts were already checked for expiration earlier today.";
    }
} catch (mysqli_sql_exception $e) {
    error_log("Contract expiration update error: " . $e->getMessage());
    $_SESSION['contract_update_msg'] = "An error occurred while updating contract statuses.";
}

/* ----------------------------------------------------------
   EMAIL FUNCTION
---------------------------------------------------------- */
function sendEmail($toEmail, $toName, $subject, $body)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'adrianfernando2626@gmail.com';
        $mail->Password = 'cxwqqwktqevyogmt';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('adrianfernando2626@gmail.com', 'Tenant Management');
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        echo "✅ Email sent to $toEmail<br>";
    } catch (Exception $e) {
        echo "❌ Failed to send email to $toEmail. Error: {$mail->ErrorInfo}<br>";
    }
}

/* ----------------------------------------------------------
   DAILY NOTIFICATION CHECK (all notifications only once/day)
---------------------------------------------------------- */
try {
    $checkNotif = $db_connection->prepare("SELECT setting_value FROM settings WHERE setting_key = 'last_notification_check'");
    $checkNotif->execute();
    $checkNotif->bind_result($lastNotifCheck);
    $hasNotif = $checkNotif->fetch();
    $checkNotif->close();

    if (!$hasNotif || $lastNotifCheck !== $today) {

        /* --------------------------------------------------
           1️⃣ Monthly Rent Report Reminder (for Admin/Landlord)
        -------------------------------------------------- */
        if ((int)date('j') >= 25) {
            $sql = "SELECT * FROM userall WHERE role IN ('Admin', 'Landlord')";
            $result = mysqli_query($db_connection, $sql);

            while ($row = mysqli_fetch_assoc($result)) {
                $user_id = $row['user_id'];
                $landlord_email = $row['email'];
                $landlord_name = $row['first_name'] . " " . $row['last_name'];

                $subject = "Monthly Rent Report Reminder";
                $body = "
                    <h3>End-of-Month Rent Report Reminder</h3>
                    <p>Dear <strong>$landlord_name</strong>,</p>
                    <p>This is a friendly reminder that the current month is about to end.</p>
                    <p>Please download the <strong>monthly rent report PDF</strong> from your admin dashboard for accurate tracking.</p>
                    <br>
                    <p>Thank you!<br>Tenant Management System</p>
                ";
                sendEmail($landlord_email, $landlord_name, $subject, $body);

                $notif_text = "End-of-Month Rent Report Reminder: Please download your rent report PDF for this month.";
                $pdo->prepare("INSERT INTO notification (user_id, notif_title, notif_text, date_created, notif_status) VALUES (?, '📊 Monthly Rent Report Reminder', ?, NOW(), 'unread')")->execute([$user_id, $notif_text]);
            }
        }

        /* --------------------------------------------------
           2️⃣ Overdue Rent Notifications
        -------------------------------------------------- */
        $todayDate = date('Y-m-d');
        $query = "SELECT p.payment_id, p.due_date, rp.expected_amount_due, pi.email, pi.first_name, u.user_id
                  FROM payment p
                  JOIN rent_payment rp ON rp.payment_id = p.payment_id
                  JOIN payment_status ps ON p.payment_id = ps.payment_id
                  JOIN contract c ON p.contract_id = c.contract_id
                  JOIN user u ON c.user_id = u.user_id
                  JOIN personal_info pi ON u.user_id = pi.user_id
                  WHERE ps.status = 'UNPAID' AND p.due_date < ? AND u.account_status = 'Active'";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $todayDate);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $user_id = $row['user_id'];
            $email = $row['email'];
            $first_name = $row['first_name'];
            $due_date = $row['due_date'];
            $expected_amount_due = number_format($row['expected_amount_due'], 2);

            $notifText = "🚨 Hi $first_name, your rent of PHP $expected_amount_due was due on $due_date and is still unpaid. Please settle it as soon as possible.";
            $insertNotif = $conn->prepare("INSERT INTO notification (user_id, notif_title, notif_text, date_created, notif_status) VALUES (?, 'Overdue Rent Notice', ?, NOW(), 'unread')");
            $insertNotif->bind_param("is", $user_id, $notifText);
            $insertNotif->execute();
            $insertNotif->close();

            $subject = "Overdue Rent Reminder";
            $body = "
                <h3>Overdue Rent Notice</h3>
                <p>Dear <strong>$first_name</strong>,</p>
                <p>Your rent of <strong>₱$expected_amount_due</strong> was due on <strong>$due_date</strong> and is still <strong>unpaid</strong>.</p>
                <p>Please settle it as soon as possible to avoid penalties.</p>
                <br><p>Thank you!<br>Tenant Management System</p>
            ";
            sendEmail($email, $first_name, $subject, $body);
        }
        $stmt->close();

        /* --------------------------------------------------
           3️⃣ Rent Due Soon (7-day countdown reminders)
        -------------------------------------------------- */
        $daysToNotify = [7, 6, 5, 4, 3, 2, 1];
        $today = new DateTime();

        foreach ($daysToNotify as $daysBefore) {
            $targetDate = (clone $today)->modify("+{$daysBefore} days")->format('Y-m-d');

            $query = "SELECT p.due_date, rp.expected_amount_due, pi.email, pi.first_name, u.user_id
                      FROM payment p
                      JOIN rent_payment rp ON rp.payment_id = p.payment_id
                      JOIN payment_status ps ON p.payment_id = ps.payment_id
                      JOIN contract c ON p.contract_id = c.contract_id
                      JOIN user u ON c.user_id = u.user_id
                      JOIN personal_info pi ON u.user_id = pi.user_id
                      WHERE p.due_date = ? AND ps.status = 'UNPAID' AND u.account_status = 'Active'";

            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $targetDate);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $user_id = $row['user_id'];
                $email = $row['email'];
                $first_name = $row['first_name'];
                $due_date = $row['due_date'];
                $expected_amount_due = number_format($row['expected_amount_due'], 2);

                $notifText = "📢 Hi $first_name, your rent of PHP $expected_amount_due is due in $daysBefore day(s) on $due_date.";
                $insertNotif = $conn->prepare("INSERT INTO notification (user_id, notif_title, notif_text, date_created, notif_status) VALUES (?, 'Rent Due Reminder', ?, NOW(), 'unread')");
                $insertNotif->bind_param("is", $user_id, $notifText);
                $insertNotif->execute();
                $insertNotif->close();

                $subject = "Rent Due Reminder - $daysBefore day(s) left";
                $body = "
                    <h3>Rent Due Reminder</h3>
                    <p>Dear <strong>$first_name</strong>,</p>
                    <p>This is a reminder that your rent of <strong>₱$expected_amount_due</strong> is due in <strong>$daysBefore day(s)</strong> on <strong>$due_date</strong>.</p>
                    <p>Please settle it on time to avoid penalties.</p>
                    <br><p>Thank you!<br>Tenant Management System</p>
                ";
                sendEmail($email, $first_name, $subject, $body);
            }
            $stmt->close();
        }

        // ✅ Update last notification date in settings
        $todayStr = date('Y-m-d'); // Convert to plain date string

        $updateNotif = $db_connection->prepare("
    INSERT INTO settings (setting_key, setting_value)
    VALUES ('last_notification_check', ?)
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
");
        $updateNotif->bind_param("s", $todayStr);
        $updateNotif->execute();
        $updateNotif->close();

        $_SESSION['notification'] = "All notifications sent successfully today.";
    } else {
        $_SESSION['notification'] = "Notifications were already sent today.";
    }
} catch (mysqli_sql_exception $e) {
    error_log("Notification process error: " . $e->getMessage());
    $_SESSION['notification'] = "An error occurred while sending notifications.";
}

$conn->close();
header('Location: login.php?message=' . urlencode($_SESSION['contract_update_msg'] . " | " . $_SESSION['notification']));
exit;
