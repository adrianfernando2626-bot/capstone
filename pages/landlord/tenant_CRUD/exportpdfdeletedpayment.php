<?php
session_start();
require_once '../../includes/database.php';
require_once '../../tenant/guest_logging_process/vendor/autoload.php'; // Adjust path as needed

use Dompdf\Dompdf;
use Dompdf\Options;

$building_id = $_SESSION['building_id'];
$options = new Options();
$options->set('defaultFont', 'Arial');
$dompdf = new Dompdf($options);

$sql = "SELECT a.expected_amount_due, a.paid_on, a.payment_method, b.status, b.payment_status, us.first_name, us.last_name
                        FROM payment a 
                        JOIN payment_status b ON b.payment_id = a.payment_id
                        JOIN contract c ON c.contract_id = a.contract_id
                        JOIN userall us ON us.user_id = c.user_id
                        JOIN room r ON r.room_id = us.room_id
                        WHERE TRIM(LOWER(b.payment_status)) = 'archived' AND r.building_id = $building_id";

$result = mysqli_query($db_connection, $sql);

$html = '<h2 style="text-align:center;">Archived Payment Records</h2>';
$html .= '<table border="1" cellpadding="10" cellspacing="0" width="100%">';
$html .= '<thead>
<tr>
    <th>Tenant Name</th>
    <th>Amount</th>
    <th>Paid On</th>
    <th>Payment Method</th>
    <th>Payment Status</th>
</tr>
</thead><tbody>';

while ($row = mysqli_fetch_assoc($result)) {
    $html .= '<tr>
        <td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>
        <td>' . htmlspecialchars($row['expected_amount_due']) . '</td>
        <td>' . htmlspecialchars(date("F j, Y", strtotime($row['paid_on']))) . '</td>
        <td>' . htmlspecialchars($row['payment_method']) . '</td>
        <td>' . htmlspecialchars($row['status']) . '</td>
    </tr>';
}

$html .= '</tbody></table>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("archived_payments.pdf", array("Attachment" => false));
unset($_SESSION['building_id']);
exit;
