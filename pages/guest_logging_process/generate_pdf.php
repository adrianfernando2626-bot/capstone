<?php
require_once 'vendor/autoload.php';
require_once 'lib/phpqrcode/qrlib.php';
session_start();

use Dompdf\Dompdf;

$pdo = new PDO("mysql:host=localhost;dbname=apartment", "root", "");

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) die("Missing ID");

$stmt = $pdo->prepare("SELECT gp.*, r.room_number, CONCAT(us.first_name, ' ', last_name) AS name FROM guest_pass gp
                       JOIN userall us ON us.user_id = gp.user_id
                       JOIN room r ON us.room_id = r.room_id
                       WHERE gp.user_id = ?");
$stmt->execute([$user_id]);
$guest = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$guest) die("Guest not found");
$qr_token = $guest['qr_token'];
$qrData = "TOKEN:$qr_token";
ob_start();
QRcode::png($qrData, null, QR_ECLEVEL_L, 4);
$qrBase64 = base64_encode(ob_get_clean());

$html = "
<style>
  body { font-family: Arial; text-align: center; align-items: center;}
  .card { border: 2px solid #000; padding: 20px; width: 100%; }
  .logo { width: 100px; }
  .footer { margin-top: 20px; font-size: 12px; color: gray; }
</style>
<div class='card'>
  <h2>Guest Pass</h2>
  <p><strong>Name:</strong> {$guest['name']}</p>
  <p><strong>Room Number:</strong> {$guest['room_number']}</p>
  <img src='data:image/png;base64,{$qrBase64}'><br>
  <div class='footer'>Please show this pass to the building guard. Thank you.</div>
</div>
";

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A5', 'portrait');
$dompdf->render();
$dompdf->stream("guest_pass_{$guest['id']}.pdf", ["Attachment" => false]);
