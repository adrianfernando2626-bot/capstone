<?php
require_once '../../tenant/guest_logging_process/vendor/autoload.php'; // Adjust path as needed

include_once '../../includes/database.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Prepare Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Arial');
$dompdf = new Dompdf($options);

// Fetch data
$sql = "SELECT guest.*, r.room_number, CONCAT(us.first_name, ' ',us.last_name) AS name
                      FROM guest_logs guest
                      JOIN guest_pass gp ON gp.id = guest.id
                      JOIN userall us ON us.user_id = gp.user_id
                      JOIN room r ON us.room_id = r.room_id
                      WHERE us.account_status = 'Active'";
$result_query = mysqli_query($db_connection, $sql);

// Build HTML content
$html = '
    <h2 style="text-align:center;">Guest Logs Report</h2>
    <table border="1" cellspacing="0" cellpadding="5" width="100%">
        <thead>
            <tr style="background-color: #f2f2f2;">
                  <th>Room</th>
                  <th>Tenant Name</th>
                  <th>Time In</th>
                  <th>Time Out</th>
            </tr>
        </thead>
        <tbody>';

while ($row = mysqli_fetch_assoc($result_query)) {
    $html .= '
        <tr>
            <td>' . htmlspecialchars($row['room_number']) . '</td>
            <td>' . htmlspecialchars($row['name']) . '</td>
            <td>' . htmlspecialchars($row['time_in']) . '</td>';
    if ($row['time_out']) {
        $html .= '<td>' . htmlspecialchars($row['time_out']) . '</td>';
    } else {
        $html .= '<td>Not Available</td>';
    }

    $html .= '</tr>';
}

$html .= '
        </tbody>
    </table>';

// Load & render PDF
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape'); // Optional: change to portrait if you want
$dompdf->render();

// Output
$dompdf->stream('Guest_Logs_Report.pdf', ["Attachment" => false]);
exit;
