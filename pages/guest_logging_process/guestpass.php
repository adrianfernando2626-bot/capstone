<?php
require_once 'lib/phpqrcode/qrlib.php';

$pdo = new PDO("mysql:host=localhost;dbname=apartment", "root", "");

$id = $_GET['id'] ?? null;
if (!$id) die("Missing ID");

$stmt = $pdo->prepare("SELECT * FROM guest_logs WHERE id = ?");
$stmt->execute([$id]);
$guest = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$guest) die("Guest not found");
$qr_token = $guest['qr_token'];
$qrData = "TOKEN:$qr_token";

ob_start();
QRcode::png($qrData, null, QR_ECLEVEL_L, 4);
$qrBase64 = base64_encode(ob_get_clean());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $visit_datetime = $_POST['visit_datetime'] ?? '';
    $email = $_POST['email'] ?? '';
    try {
        if ($name & $purpose & $visit_datetime & $email) {
            $pdo->prepare("UPDATE guest_logs 
                            SET name=?, purpose=?, visit_datetime=?, email=?  
                            WHERE id=?")->execute([$name, $purpose, $visit_datetime, $email, $id]);
        } else {
            echo "may mali";
        }
        header("Location: ../tenantguestlog.php?message=guest_updated");
        exit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Database error: " . $e->getMessage());
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../../css/guestpass.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
</head>

<body>
    <div class="main-container">
        <div class="guest-pass-container">
            <img src="../../images/Copy of Logo No Background.png" alt="Logo" class="logo">
            <h2>Guest Pass</h2>

            <form method="post">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($guest['name']) ?>">

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($guest['email']) ?>">

                <label for="purpose">Purpose:</label>
                <textarea id="purpose" name="purpose"><?= htmlspecialchars($guest['purpose']) ?></textarea>

                <label for="date">Date:</label>
                <input type="datetime-local" id="visit_datetime" name="visit_datetime" value="<?= htmlspecialchars($guest['visit_datetime']) ?>">

                <div class="qr-code">
                    <img src="data:image/png;base64,<?= $qrBase64 ?>" alt="QR Code">
                    <!--dito ung qr code-->
                </div>

                <p class="note">Please present this pass at the entrance. Thank you!</p>

                <button class="btn update" type="submit">Update</button>
                <a href="../tenantguestlog.php" class="btn back">Back</a>
            </form>


        </div>


        <div class="actions">
            <button class="btn" onclick="window.print()" style="background-color: #007bff;">🖨️ Print</button>
            <a href="generate_pdf.php?id=<?= $guest['id'] ?>" target="_blank">
                <button class="btn" style="background-color: #28a745;">📄 Download PDF</button>
            </a>
            <a href="email_guest_pass.php?id=<?= $guest['id'] ?>">
                <button class="btn" style="background-color: #6c757d;">📧 Email PDF</button>
            </a>
        </div>
    </div>

</html>