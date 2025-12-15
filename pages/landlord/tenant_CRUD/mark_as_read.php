
<?php
include_once '../../includes/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['selected_requests']) && is_array($_POST['selected_requests'])) {
        $selected = $_POST['selected_requests'];

        $stmt = $pdo->prepare("UPDATE status_request SET update_message = 'Read' WHERE maintenance_request_id = ?");

        foreach ($selected as $request_id) {
            $stmt->execute([$request_id]);
        }

        header("Location: ../tenant_manage.php?message=marked_as_read");
        exit;
    } else {
        header("Location: ../tenant_manage.php?message=no_selection");
        exit;
    }
} else {
    header("Location: ../tenant_manage.php");
    exit;
}
?>
