<?php
if (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}
if (file_exists('../includes/database.php')) {
    include_once('../includes/database.php');
}

$sql = "SELECT * from payment_summary";
$res = $db_connection->query($sql);
$data = [];
while ($row = $res->fetch_assoc()) {
    array_push($data, $row);
}


echo json_encode($data);
