<?php
require_once '../../tenant/guest_logging_process/vendor/autoload.php'; // Adjust path as needed
use Dompdf\Dompdf;
use Dompdf\Options;

include_once '../../includes/database.php'; // Adjust path as needed

// Fetch deleted users
$sql = "SELECT first_name, last_name, email, role, date_registered
        FROM userall WHERE account_status = 'Deleted'";
$result_query = mysqli_query($db_connection, $sql);

// Build HTML
$html = '
<h2 style="text-align: center;">Deleted User Accounts</h2>
<table border="1" cellspacing="0" cellpadding="5" width="100%">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th>Tenant Name</th>
            <th>Email Address</th>
            <th>Role</th>
            <th>Date Registered</th>
        </tr>
    </thead>
    <tbody>';

while ($row = mysqli_fetch_assoc($result_query)) {
    $html .= '<tr>
        <td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>
        <td>' . htmlspecialchars($row['email']) . '</td>
        <td>' . htmlspecialchars($row['role']) . '</td>
        <td>' . htmlspecialchars($row['date_registered']) . '</td>
    </tr>';
}

$html .= '</tbody></table>';

// Create Dompdf instance
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// Setup paper size and orientation
$dompdf->setPaper('A4', 'portrait');

// Render and stream PDF
$dompdf->render();
$dompdf->stream("deleted_user_accounts_" . date("Y-m-d") . ".pdf", ["Attachment" => false]); // Open in browser
exit;
