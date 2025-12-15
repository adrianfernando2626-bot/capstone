<?php
if (file_exists('includes/database.php')) {
  include_once('includes/database.php');
}
if (file_exists('../../includes/database.php')) {
  include_once('../../includes/database.php');
}
session_start();
$user_id = $_SESSION['user_id'];

$sql = 'SELECT * FROM userall WHERE user_id = ' . $user_id;
$rs = mysqli_query($db_connection, $sql);
$rw = mysqli_fetch_array($rs);

require_once 'lib/phpqrcode/qrlib.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Database connection
$pdo = new PDO("mysql:host=localhost;dbname=apartment", "root", "");

// Retrieve form data
$name = $_POST['name'];
$email = $_POST['email'];
$purpose = $_POST['purpose'];
$visit_datetime = $_POST['visit_datetime'];

// Generate unique QR token
$qr_token = bin2hex(random_bytes(10));

// Insert guest data into database
$stmt = $pdo->prepare("INSERT INTO guest_logs (user_id, name, email, purpose, visit_datetime, qr_token) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$user_id, $name, $email, $purpose, $visit_datetime, $qr_token]);

$guest_id = $pdo->lastInsertId();
$qr_content = "TOKEN:$qr_token";

$qr_path = "qrcodes/qr_$guest_id.png";
QRcode::png($qr_content, $qr_path, QR_ECLEVEL_L, 4);

// Convert QR code to base64
$qr_base64 = base64_encode(file_get_contents($qr_path));
$qr_image = 'data:image/png;base64,' . $qr_base64;

// Generate PDF
$dompdf = new Dompdf();
$html = "
  <h2>Guest Pass</h2>
  <p><strong>Name:</strong> $name</p>
  <p><strong>Purpose:</strong> $purpose</p>
  <p><strong>Date and Time:</strong> $visit_datetime</p>
  <img src='$qr_image' alt='QR Code' style='width:150px;'>
";
$dompdf->loadHtml($html);
$dompdf->setPaper('A5', 'portrait');
$dompdf->render();
$pdf_output = $dompdf->output();
file_put_contents("pdfs/guest_pass_$guest_id.pdf", $pdf_output);


// Send email with PDF attachment
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
  $mail->addAttachment("pdfs/guest_pass_$guest_id.pdf");

  //Content
  $mail->isHTML(true);
  $mail->Subject = 'Your Guest Pass';
  $mail->Body = "Guest pass sent on behalf of {$rw['email']}<br><br>Please find your guest pass attached.<br><br><strong>This is a generated system email do not reply</strong>";


  $mail->send();
  header("Location: ../tenantguestlog.php?message=guest_inserted");
} catch (Exception $e) {
  echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
