<?php

$pdo = new PDO("mysql:host=localhost;dbname=apartment", "root", "");

$token = $_GET['token'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM guest_logs WHERE qr_token = ?");
$stmt->execute([$token]);
$guest = $stmt->fetch(PDO::FETCH_ASSOC);

if ($guest) {
    echo "<h2>Welcome, {$guest['name']}!</h2>";
    echo "<p>Purpose: {$guest['purpose']}</p>";
    echo "<p>Visit Time: {$guest['visit_datetime']}</p>";
    echo "<p>Entry logged successfully.</p>";
} else {
    echo "Invalid QR code.";
}
