<?php

if (!isset($_POST['qrdata'])) {
    echo "No QR data received.";
    exit;
}

$qr = $_POST['qrdata'];

// Establish database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=apartment", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage();
    exit;
}

// ✅ Set PHP timezone to Philippines (Asia/Manila)
date_default_timezone_set('Asia/Manila');

if (preg_match('/TOKEN:([a-f0-9]+)/i', $qr, $matches)) {
    $token = $matches[1];

    // Step 1: Find guest pass details and guest ID
    $stmt = $pdo->prepare("
        SELECT 
            CONCAT(us.first_name, ' ', us.last_name) AS name, 
            r.room_number, 
            r.floor_number, 
            gp.id AS guest_pass_id
        FROM guest_pass gp 
        JOIN userall us ON us.user_id = gp.user_id 
        JOIN room r ON us.room_id = r.room_id 
        WHERE gp.qr_token = ?
    ");
    $stmt->execute([$token]);
    $guest = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($guest) {
        $guest_pass_id = $guest['guest_pass_id'];

        // ✅ Create DateTime in Asia/Manila timezone
        $current_datetime = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $current_datetime_str = $current_datetime->format('Y-m-d H:i:s');
        $current_date_str = $current_datetime->format('Y-m-d');

        $stmt_check = $pdo->prepare("
            SELECT time_in, time_out, created_at
            FROM guest_logs 
            WHERE id = ? AND DATE(time_in) = ?
        ");
        $stmt_check->execute([$guest_pass_id, $current_date_str]);
        $existing_log = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($existing_log) {
            $last_scan_time = $existing_log['time_out'] ? $existing_log['time_out'] : $existing_log['time_in'];
            $last_scan_datetime = new DateTime($last_scan_time, new DateTimeZone('Asia/Manila'));

            // Compute total seconds difference instead of minutes
            $interval_seconds = abs($current_datetime->getTimestamp() - $last_scan_datetime->getTimestamp());

            if ($interval_seconds < 30) {
                echo "⚠️ Guest Pass Found Already! Please wait for 30 seconds before scanning again.";
                exit;
            }

            if (!$existing_log['time_out']) {
                // Update log with checkout time
                $stmt_update = $pdo->prepare("
                    UPDATE guest_logs 
                    SET time_out = ? 
                    WHERE id = ? AND DATE(time_in) = ?
                ");
                $stmt_update->execute([$current_datetime_str, $guest_pass_id, $current_date_str]);

                echo "<h3>✅ Guest Check-out Successful</h3>";
                echo "<strong>Name:</strong> " . htmlspecialchars($guest['name']) . "<br>";
                echo "<strong>Time Out:</strong> " . $current_datetime_str;
            } else {
                echo "You have already checked out today.";
            }
        } else {
            // New check-in log
            $stmt_insert = $pdo->prepare("
                INSERT INTO guest_logs(id, time_in) VALUES(?, ?)
            ");
            $stmt_insert->execute([$guest_pass_id, $current_datetime_str]);

            echo "<h3>✅ Guest Check-in Successful</h3>";
            echo "<strong>Name:</strong> " . htmlspecialchars($guest['name']) . "<br>";
            echo "<strong>Room:</strong> " . htmlspecialchars($guest['room_number']) . "<br>";
            echo "<strong>Floor Number:</strong> " . htmlspecialchars($guest['floor_number']) . "<br>";
            echo "<strong>Time In:</strong> " . $current_datetime_str;
        }
    } else {
        echo "Guest not found.";
    }
} else {
    echo "Invalid QR data.";
}
