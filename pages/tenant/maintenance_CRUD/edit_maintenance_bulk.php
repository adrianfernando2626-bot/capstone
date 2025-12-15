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
// ✅ Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['requests'])) {
    foreach ($_POST['requests'] as $requestData) {
        $id = $requestData['maintenance_request_id'];
        $issue_type = $requestData['issue_type'];
        $description = $requestData['description'];
        $update_message = $requestData['update_message'];

        $stmt = $pdo->prepare("UPDATE maintenance_request SET issue_type = ?, description = ? WHERE maintenance_request_id = ?");
        $stmt->execute([$issue_type, $description, $id]);

        $stmtstatus = $pdo->prepare("UPDATE status_request SET update_message = ? WHERE maintenance_request_id = ?");
        $stmtstatus->execute([$update_message, $id]);
    }

    header("Location: ../tenantmaintenance.php?message=request_updated");
    exit();
}

// ✅ Display selected requests
$selectedRequests = $_POST['selectedRequest'] ?? [];

if (empty($selectedRequests)) {
    die("No maintenance requests selected.");
}

$placeholders = implode(',', array_fill(0, count($selectedRequests), '?'));
$stmt = $pdo->prepare("SELECT m.*, st.update_message  
 FROM maintenance_request m 
 JOIN status_request st ON m.maintenance_request_id = st.maintenance_request_id
 WHERE m.maintenance_request_id IN ($placeholders)");
$stmt->execute($selectedRequests);
$requests = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Maintenance Requests</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/addcontent.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function logout() {
            var msg = 'Are you sure you want to logout?';
            Swal.fire({
                icon: 'question',
                title: 'Log Out',
                text: msg,
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../logout.php?status=logout';
                } else {
                    location.reload();
                }
            });
        }
    </script>
    <style>
        .container {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .main-card {
            flex: 1 1 800px;
        }

        .profile-section {
            background-color: #f8f9fa;
            /* light gray, complements Bootstrap light theme */
            padding: 20px;
            border-radius: 15px;
            width: 300px;
            height: fit-content;
            align-self: center;
            /* Center it vertically */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .profile-card {
            text-align: center;
            margin-bottom: 20px;
        }

        .profile-card .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
            border: 3px solid #ddd;
        }

        .activity-panel h4 {
            margin-bottom: 10px;
        }

        .activity-panel ul {
            list-style: none;
            padding: 0;
        }

        .activity-panel li {
            margin-bottom: 8px;
            font-size: 0.95rem;
            color: #333;
        }

        .activity-panel i {
            margin-right: 8px;
            color: #007bff;
        }
    </style>
    <title>Edit Selected Maintenance Request</title>
</head>

<body class="bg-light">
    <div class="container my-5 d-flex justify-content-center gap-4 flex-wrap">
        <div class="card shadow main-card" style="max-width: 800px;">
            <div class="card-body">
                <div class="form-header">
                    <i class="fas fa-clipboard-list fa-2x"></i>
                    <h1>Edit Selected Request</h1>
                </div>
                <form class="rule-form" method="post">
                    <?php foreach ($requests as $request):
                        $id = $request['maintenance_request_id'];
                    ?>
                        <div class="card mb-4 p-3 shadow-sm">
                            <h5>Request #<?= htmlspecialchars($id) ?></h5>

                            <input type="hidden" name="requests[<?= $id ?>][maintenance_request_id]" value="<?= $id ?>">

                            <label>Issue Type:
                                <select name="requests[<?= $id ?>][issue_type]" id="issue_type">
                                    <?php
                                    $status_option = ['Plumbing', 'Electrical', 'Noise', 'Appliance', 'Other'];

                                    echo "<option value='" . $request['issue_type'] . "' selected>" . $request['issue_type'] . "</option>";
                                    foreach ($status_option as $option) {
                                        if ($option !== htmlspecialchars($request['issue_type'])) {
                                            echo "<option value='" . $option . "'>" . $option . "</option>";
                                        }
                                    }
                                    ?>
                                </select>

                            </label><br>

                            <label>Description:
                                <textarea name="requests[<?= $id ?>][description]" class="form-control" required><?= htmlspecialchars($request['description']) ?></textarea>
                            </label><br>

                            <label>Status:
                                <select name="requests[<?= $id ?>][update_message]" class="form-select">
                                    <option value="" selected disabled>-- Select Status --</option>
                                    <?php
                                    $status_options = ['Resolved', 'Not Solved', 'Pending'];
                                    foreach ($status_options as $option) {
                                        $selected = ($option === $request['update_message']) ? 'selected' : '';
                                        echo "<option value='$option' $selected>$option</option>";
                                    }
                                    ?>
                                </select>
                            </label><br>

                        </div>
                    <?php endforeach; ?>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary">Save All Changes</button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='../tenantmaintenance.php'">
                            Cancel
                        </button>
                    </div>


                </form>
            </div>

        </div>

    </div>
    <script src="../../js/script.js"></script>

</body>

</html>