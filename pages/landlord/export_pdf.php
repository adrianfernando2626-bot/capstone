<?php
require_once '../tenant/guest_logging_process/vendor/autoload.php';

use Dompdf\Dompdf;

$host = "localhost";
$username = "root";
$password = "";
$database = "apartment";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Get filters
$month = $_GET['month'] ?? date('m');
$room = $_GET['room'] ?? '';
$monthName = date('F', mktime(0, 0, 0, $month, 1));
$year = date('Y');
$html = "<style>
    body { font-family: DejaVu Sans, sans-serif; }
    table, th, td { font-family: DejaVu Sans, sans-serif; }
</style>";

// Get summary from GET
$totalTenants = $_GET['totalTenants'] ?? 0;
$onTime = $_GET['onTime'] ?? 0;
$late = $_GET['late'] ?? 0;
$unpaid = $_GET['unpaid'] ?? 0;
$expected = $_GET['expected'] ?? 0;
$collected = $_GET['collected'] ?? 0;

// Start HTML output
$html = "<h2 style='text-align:center;'>Rent Report - $monthName $year</h2>";

// Summary section
$html .= "
<h3>Monthly Summary</h3>
<table border='1' cellspacing='0' cellpadding='5' width='100%' style='margin-bottom: 20px;'>
    <tr><th>Total Tenants</th><td>$totalTenants</td></tr>
    <tr><th>Payments On Time</th><td>$onTime</td></tr>
    <tr><th>Late Payments</th><td>$late</td></tr>
    <tr><th>Unpaid</th><td>$unpaid</td></tr>
    <tr><th>Total Expected</th><td>$expected</td></tr>
    <tr><th>Total Collected</th><td>$collected</td></tr>
</table>
";

// Apply filters
$where = "WHERE MONTH(p.due_date) = ? AND YEAR(p.due_date) = YEAR(CURDATE())";
$params = [$month];
$types = "s";

if (!empty($room)) {
    $where .= " AND r.room_number = ?";
    $params[] = $room;
    $types .= "s";
}

// Get payment data
$sql = "SELECT pi.first_name, pi.last_name, r.room_number, p.due_date, rp.expected_amount_due, rp.tenant_payment, p.paid_on
        FROM payment p
        JOIN contract c ON p.contract_id = c.contract_id
        JOIN rent_payment rp ON p.payment_id = rp.payment_id
        JOIN user u ON c.user_id = u.user_id
        JOIN personal_info pi ON u.user_id = pi.user_id
        JOIN room r ON u.room_id = r.room_id
        $where
        ORDER BY u.room_id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Table header
$html .= '<h3>Tenant Payment Records</h3>';
$html .= '<table border="1" cellspacing="0" cellpadding="5" width="100%">';
$html .= '<tr><th>Tenant</th><th>Room</th><th>Due Date</th><th>Expected Amount</th><th>Collected Amount</th><th>Paid On</th><th>Status</th></tr>';

// Table data
while ($row = $result->fetch_assoc()) {
    $due = date('M d, Y', strtotime($row['due_date']));
    $paid = $row['paid_on'] ? date('M d, Y', strtotime($row['paid_on'])) : '—';
    // Format paid_on to date only
    $paid_on_date = $row['paid_on'] ? date('Y-m-d', strtotime($row['paid_on'])) : null;
    // Determine status
    $status = 'Unpaid';
    $bgColor = 'background-color: #facc15; color: #000;';
    if ($paid_on_date) {
        if ($paid_on_date <= $row['due_date']) {
            $status = 'On-Time';
            $bgColor = 'background-color: #22c55e; color: #fff;';
        } elseif ($paid_on_date > $row['due_date']) {
            $status = 'Late';
            $bgColor = 'background-color: #ef4444; color: #fff;';
        }
    }

    $statusHtml = "<span style='display:inline-block; padding:4px 10px; border-radius:6px; font-weight:bold; $bgColor'>$status</span>";

    $html .= "<tr>
        <td>{$row['first_name']} {$row['last_name']}</td>
        <td>{$row['room_number']}</td>
        <td>$due</td>
        <td>{$row['expected_amount_due']}</td>
        <td>{$row['tenant_payment']}</td>
        <td>$paid</td>
        <td style='text-align:center;'>$statusHtml</td>
    </tr>";
}
$html .= '</table>';

// Render PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("rent-report-$monthName-$year.pdf", ["Attachment" => false]);
