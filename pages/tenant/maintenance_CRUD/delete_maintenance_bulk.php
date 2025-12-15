<?php
include_once '../../includes/database.php';

$selectedRequest = $_POST['selectedRequest'] ?? [];

if (empty($selectedRequest)) {
    die("No request selected for deletion.");
}

// Fetch maintenance data
$placeholders = implode(',', array_fill(0, count($selectedRequest), '?'));
$stmt = $pdo->prepare("SELECT maintenance_request_id FROM maintenance_request WHERE maintenance_request_id IN ($placeholders)");
$stmt->execute($selectedRequest);
$maintenance_request_id = $stmt->fetchAll();

// Prepare delete statement
$maintenanceDeleteStmt = $pdo->prepare("DELETE FROM maintenance_request WHERE maintenance_request_id = ?");
$statusrequestDeleteStmt = $pdo->prepare("DELETE FROM status_request WHERE maintenance_request_id = ?");

// Delete each rule
foreach ($maintenance_request_id as $maintenance) {
    $maintenance_request_id = $maintenance['maintenance_request_id'];

    $maintenanceDeleteStmt->execute([$maintenance_request_id]);
    $statusrequestDeleteStmt->execute([$maintenance_request_id]);
}

header("Location: ../tenantmaintenance.php?message=request_deleted");
exit();
