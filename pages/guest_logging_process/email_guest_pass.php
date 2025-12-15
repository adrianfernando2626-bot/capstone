<?php
require_once 'vendor/autoload.php';
require_once 'lib/phpqrcode/qrlib.php';
session_start();

use Dompdf\Dompdf;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$pdo = new PDO("mysql:host=localhost;dbname=apartment", "root", "");

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) die("Missing ID");


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $stmt = $pdo->prepare("SELECT * FROM guest_pass WHERE user_id = ?");
  $stmt->execute([$user_id]);
  $guest = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$guest) die("Guest not found");

  // Assume you have guest email field in table
  $email = $_POST['email'] ?? null;
  $name = $_POST['name'] ?? null;
  $id = $guest['id'] ?? null;
  if (!$email) die("No guest email found.");

  // Generate PDF
  $qr_token = $guest['qr_token'];
  $qrData = "TOKEN:$qr_token";
  ob_start();
  QRcode::png($qrData, null, QR_ECLEVEL_L, 4);
  $qrBase64 = base64_encode(ob_get_clean());


  $file = "Guest_Pass_{$guest['id']}.pdf";

  $mail = new PHPMailer(true);
  try {
    //Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'adrianfernando2626@gmail.com';
    $mail->Password   = 'cxwqqwktqevyogmt';
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    //Recipients
    $mail->setFrom('adrianfernando2626@gmail.com', 'Guest Pass System');
    $mail->addAddress($email, $name);

    //Attachments
    $mail->addAttachment("pdfs/guest_pass_$id.pdf");

    //Content
    $mail->isHTML(true);
    $mail->Subject = 'Your Guest Pass';
    $mail->Body = "Guest pass sent on behalf of {$rw['email']}<br><br>Please find your guest pass attached.<br><br><strong>This is a generated system email do not reply</strong>";


    $mail->send();
    header("Location: ../tenantguestlog.php?message=guest_emailed");
  } catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
  }
}
