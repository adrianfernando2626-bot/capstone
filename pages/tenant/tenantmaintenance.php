<?php
if (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}
if (file_exists('../includes/database.php')) {
    include_once('../includes/database.php');
}
session_start();
$user_id = $_SESSION['user_id'];

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'guest_logging_process/vendor/autoload.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Tenant') {
    header("Location: ../login.php");
    exit();
}
$warning = "";
if (isset($_GET['message']) && $_GET['message'] === 'request_updated') {
    $warning = 'Request Updated';
}

if (isset($_GET['message']) && $_GET['message'] === 'request_deleted') {
    $warning = 'Request Deleted';
}

if (isset($_GET['message']) && $_GET['message'] === 'request_inserted') {
    $warning = 'Request Submitted';
}

$sql = 'SELECT us.*, r.room_number, r.building_id FROM userall us
JOIN room r ON r.room_id = us.room_id
WHERE user_id = ' . $user_id;
$rs = mysqli_query($db_connection, $sql);
$rw = mysqli_fetch_array($rs);
$room_number = $rw['room_number'];
$building_id = $rw['building_id'];

$sqllandlord = 'SELECT * FROM userall
WHERE role = "Landlord" AND building_id = ' . $building_id;
$rslandlord = mysqli_query($db_connection, $sqllandlord);
$landlord = mysqli_fetch_array($rslandlord);
$landlord_name = $landlord['first_name'] . " " . $landlord['last_name'];
$landlordemail =  $landlord['email'];
$landlord_id = $landlord['user_id'];
$room_id = $rw['room_id'];

$rowsPerPage = isset($_GET['rowsPerPage']) ? (int) $_GET['rowsPerPage'] : 5;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

$offset = ($page - 1) * $rowsPerPage;

$limitClause = '';
if (!isset($_GET['viewAll'])) {
    $limitClause = "LIMIT $rowsPerPage OFFSET $offset";
}

if (isset($_POST['submit'])) {
    $issue_type = $_POST["issue_type"] ?? '';
    $description = $_POST["description"] ?? '';
    $status = 'Pending';
    $date_requested = date("Y-m-d");
    $name = $rw['first_name'] . " " . $rw['last_name'];

    try {
        if ($description & $issue_type) {
            $pdo->prepare("INSERT INTO maintenance_request (room_id, issue_type, description, date_requested) VALUES(?,?,?,?)")->execute([$room_id, $issue_type, $description, $date_requested]);
            $maintenance_request_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO status_request (maintenance_request_id, update_message) VALUES(?,?)")->execute([$maintenance_request_id, $status]);
        }
        $mail = new PHPMailer(true);
        try {

            $body = "Dear $landlord_name,<br><br>
                                   A new request has been sent by <strong>$name</strong> from Room <strong>$room_number</strong> about: 
                                   <br><br>'$issue_type'<br><br>
                                   If you have any concern, you can update the request or contact the admin.
                                   <br><br>Thank you.<br><br>
                                   <strong>This is a generated system email, please do not reply</strong>";
            $body_desciption = "A new request has been sent by<strong>$name</strong> from Room <strong>$room_number:</strong>about: 
                                   <br>'$issue_type'<br>
                                   '$description'";
            $pdo->prepare("INSERT INTO notification 
                               (user_id, notif_title, notif_text, date_created, notif_status) 
                               VALUES (?, 'Maintenance and Request', ?, NOW(), 'unread')")
                ->execute([$landlord_id, $body_desciption]);
            $name = $rw['first_name'] . " " . $rw['last_name'];
            $email = $rw['email'];
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'adrianfernando2626@gmail.com';
            $mail->Password = 'cxwqqwktqevyogmt';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('adrianfernando2626@gmail.com', 'Tenant Management');
            $mail->addAddress($landlordemail, $landlord_name);
            $mail->isHTML(true);
            $mail->Subject = "New Request from $room_number";
            $mail->Body = $body;

            $mail->send();
            header("Location: tenantmaintenance.php?message=request_inserted");
            exit();
            session_destroy();
        } catch (Exception $e) {
            error_log("Failed to send email to $email: " . $mail->ErrorInfo);
        }
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
    <title>Maintenance Request Management</title>
    <link rel="stylesheet" href="../css/rule4.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
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
                    window.location.href = '../logout.php?status=logout';
                } else {
                    location.reload();
                }
            });
        }

        function confirmDelete(maintenance_request_id) {

            Swal.fire({
                icon: 'warning',
                title: 'Delete Request',
                text: 'Are you sure you want to cancel this request?',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'maintenance_CRUD/delete_maintenance.php?status=maintenance&id=' + maintenance_request_id;
                }
            });
        }

        function confirmBulkDelete() {
            const deleteBtn = document.getElementById('bulkDeleteBtn');
            if (deleteBtn.disabled) {
                return; // Prevent SweetAlert if button is disabled
            }

            const bulkDeleteForm = document.getElementById('bulkDeleteForm');
            Swal.fire({
                icon: 'warning',
                title: 'Delete Requests',
                text: 'Are you sure you want to Delete these Requests?',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    bulkDeleteForm.submit();
                }
            });
        }

        function confirmBulkUnselectedDelete() {
            const bulkDeleteForm = document.getElementById('bulkUnselectedDeleteForm');
            Swal.fire({
                icon: 'warning',
                title: 'Delete Requests',
                text: 'Are you sure you want to Delete these Requests?',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    bulkDeleteForm.submit();
                }
            });
        }
    </script>

</head>

<body>
    <div class="side-bar">
        <div class="burger-btn" id="burgerBtn">
            <i class="fas fa-bars"></i>
        </div>
        <a href="tenantdashboard.php" class="logo">
            <img src="../images/Logo No Background Black.png" alt="" class="logo-img">
        </a>
        <ul class="nav-link">
            <li><a href="tenantdashboard.php"><i class="fas fa-th-large"></i>
                    <p>DashBoard</p>
                </a></li>
            <li><a href="#"><i class="fas fa-file-contract"></i>
                    <p>Maintenance Request</p>
                </a></li>
            <li><a href="tenantguestlog.php"><i class="fas fa-file-contract"></i>
                    <p>Guest Pass</p>
                </a></li>
            <li><a href="tenantprofile.php"><i class="fas fa-cog"></i>
                    <p>User Profile </p>
                </a></li>
            <li><a onclick="logout()"><i class="fas fa-sign-out-alt"></i>
                    <p> Logout</p>
                </a></li>
            <div class="active"></div>
        </ul>
    </div>

    <main class="main">
        <div class="topbar">
            <div>
                <h1>Maintenance<br>
                    and Complaint</h1>
            </div>
            <?php if (!empty($warning)): ?>
                <div class="alert alert-warning"><?php echo htmlspecialchars($warning); ?></div>
            <?php endif; ?>
            <div class="topbar-right">
                <button class="help-btn" id="helpBtn" title="Help Center" onclick="openHelpModal();">
                    <i class="fas fa-question-circle"></i>
                    <span>Help</span>
                </button>
                <div class="user-info">
                    <div class="position-relative">
                        <i class="bi bi-bell fs-3" id="bell-icon" style="cursor: pointer;"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php
                            $sql123 = 'SELECT COUNT(*) as total_notification FROM notification WHERE user_id = ' . $user_id . ' AND notif_status = "unread"';
                            $rs123  = mysqli_query($db_connection, $sql123);
                            $rw123 = mysqli_fetch_array($rs123);
                            ?>
                            <?php echo $rw123['total_notification']; ?>
                            <span class=" visually-hidden">unread messages</span>
                        </span>
                    </div>
                    <div id="notification-container" style="display: none; position: absolute; top: 60px; right: 160px; background-color: #fff; border: 1px solid #ccc; border-radius: 10px; width: 400px; box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1); z-index: 999;">
                        <div style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">
                            <h3>Notifications</h3>
                        </div>
                        <?php
                        $sql = "
                                SELECT n.*
                                FROM notification n
                                INNER JOIN (
                                    SELECT MAX(notif_id) AS max_id
                                    FROM notification
                                    WHERE user_id = ?
                                    GROUP BY notif_title
                                ) AS latest
                                ON n.notif_id = latest.max_id
                                WHERE n.user_id = ? AND n.notif_status != 'Archived'
                                ORDER BY n.date_created DESC
                            ";
                        $stmt = $db_connection->prepare($sql);
                        $stmt->execute([$user_id, $user_id]);
                        $notifications = $stmt->get_result();
                        if ($notifications && $notifications->num_rows > 0):
                            while ($notification = $notifications->fetch_assoc()):
                                $notif_id = $notification['notif_id'];
                                $notif_title = $notification['notif_title'];

                                $today = new DateTime();
                                $created = new DateTime($notification['date_created']);
                                $interval = $created->diff($today);
                                $daysAgo = $interval->d;

                                $text_stmt = $db_connection->prepare("SELECT notif_text FROM notification WHERE notif_title = ? AND user_id = ? ORDER BY date_created ASC");
                                $text_stmt->execute([$notif_title, $user_id]);
                                $notif_texts = $text_stmt->get_result();
                        ?>
                                <div id="notification-title-<?php echo $notif_id; ?>"
                                    class="notification-title"
                                    data-title="<?php echo htmlspecialchars($notif_title, ENT_QUOTES); ?>"
                                    style="padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
                                    <h6 style="margin: 0;"><strong><?php echo $notif_title; ?></strong></h6>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <small style="color: gray;"><?php echo $daysAgo; ?> day<?php echo $daysAgo > 1 ? 's' : ''; ?> ago</small>
                                        <?php
                                        if ($notification['notif_status'] === 'unread'): ?>
                                            <span class="badge bg-light text-success notif-dot">
                                                <i class="fas fa-circle text-primary me-1"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>


                                <div id="notification-container-body-<?php echo $notif_id; ?>" class="notif-body" style="display: none;">
                                    <?php while ($notif_text = $notif_texts->fetch_assoc()): ?>
                                        <div class="notif-item">
                                            <p class="notif-text"><?php echo $notif_text['notif_text']; ?></p>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                        <?php
                            endwhile;
                        endif;
                        ?>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            const bellIcon = document.getElementById('bell-icon');
                            const notificationContainer = document.getElementById('notification-container');

                            const badge = document.querySelector('.badge.bg-danger'); // badge element

                            bellIcon.addEventListener('click', (e) => {
                                e.stopPropagation(); // Prevent triggering outside click listener
                                const isVisible = notificationContainer.style.display === 'block';
                                notificationContainer.style.display = isVisible ? 'none' : 'block';
                            });

                            document.addEventListener('click', (e) => {
                                if (!bellIcon.contains(e.target) && !notificationContainer.contains(e.target)) {
                                    notificationContainer.style.display = 'none';
                                }
                            });

                            const titles = document.querySelectorAll('.notification-title');
                            titles.forEach(title => {
                                title.addEventListener('click', () => {
                                    const notifTitle = title.dataset.title;

                                    const notifId = title.id.split('-')[2];
                                    const body = document.getElementById(`notification-container-body-${notifId}`);
                                    if (body) {
                                        body.style.display = (body.style.display === 'none' || body.style.display === '') ? 'block' : 'none';
                                    }

                                    fetch('marking_notif/markNotificationAsRead.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/x-www-form-urlencoded'
                                            },
                                            body: `notif_title=${encodeURIComponent(notifTitle)}`
                                        })
                                        .then(response => response.text())
                                        .then(result => {
                                            fetch('marking_notif/getUnreadCount.php')
                                                .then(res => res.text())
                                                .then(newCount => {
                                                    if (badge) badge.textContent = newCount;
                                                });

                                            const dot = title.querySelector('.notif-dot');
                                            if (dot) dot.remove();

                                            // 🔽 Update badge counter
                                            if (badge) {
                                                let count = parseInt(badge.textContent.trim(), 10);
                                                if (!isNaN(count) && count > 0) {
                                                    badge.textContent = count - 1;
                                                }
                                            }
                                        })
                                        .catch(err => console.error('Error:', err));
                                });
                            });
                        });
                    </script>
                    <span><?php echo $rw['first_name'] . " " . $rw['last_name']; ?></span>
                    <img class="rounded-circle"
                        width="150px" src="../images/<?php echo $rw['img']; ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <form action="tenantmaintenance.php" method="POST" class="p-4 border rounded bg-light shadow-sm">
                <div class="row mb-3">
                    <div class="col">
                        <select class="form-select" style="min-width: 180px;" name="issue_type" required>
                            <option selected disabled value="">Issue Category</option>
                            <option>Plumbing</option>
                            <option>Electrical</option>
                            <option>Noise</option>
                            <option>Appliance</option>
                            <option>Other Issues</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <textarea placeholder="Description Box" class="form-control" name="description" rows="5" required></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary" name="submit">Submit</button>
                </div>
            </form>
        </div>



        </div>

        <div class="container mt-4">
            <div class="d-flex justify-content-center mb-3">
                <form method="get" class="d-flex gap-2">
                    <input type="text" class="form-control" name="search" placeholder="Search..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" />
                    <button class="btn btn-primary" type="submit">Search</button>
                </form>
            </div>


            <form id="tenantCheckboxForm">
                <div class="table-responsive">
                    <table id="rulesTable" class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="text-align: center;">
                                    <input type="checkbox" id="selectAllCheckbox">
                                </th>
                                <th>Issue Type</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Date Requested</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php

                            try {
                                $search = $_GET['search'] ?? '';
                                $searchClause = '';
                                if (!empty($search)) {
                                    $searchSafe = mysqli_real_escape_string($db_connection, $search);
                                    $searchClause = "(
                            a.maintenance_request_id LIKE '%$searchSafe%' OR
                            a.issue_type LIKE '%$searchSafe%' OR 
                            a.description LIKE '%$searchSafe%' OR 
                            a.date_requested LIKE '%$searchSafe%' OR 
                            b.update_message LIKE '%$searchSafe%'
                        )";
                                }

                                $whereParts = [];
                                if (!empty($searchClause)) {
                                    $whereParts[] = $searchClause;
                                }

                                $finalWhere = "WHERE a.room_id IN (SELECT m.room_id 
                                    FROM userall m 
                                    WHERE m.user_id = $user_id) AND b.update_message != 'Archived'";
                                if (!empty($whereParts)) {
                                    $finalWhere .= 'AND (' . implode(' OR ', $whereParts) . ')';
                                }

                                $sql = "SELECT  a.*, b.update_message         
        FROM maintenance_request a 
        JOIN status_request b ON b.maintenance_request_id = a.maintenance_request_id
        $finalWhere
        ORDER BY a.maintenance_request_id $limitClause";

                                $result_query = mysqli_query($db_connection, $sql);
                                while ($result = mysqli_fetch_array($result_query)) {

                                    echo '<tr>
                    <td class="text-center">
                    <input type="checkbox" class="tenant-checkbox" name="selectedRequest[]" value="' . $result['maintenance_request_id'] . '">                                           
                    </td> 
                    <td>' . $result['issue_type'] . '</td>
                    <td>' . $result['description'] . '</td>
                    <td>' . $result['update_message'] . '</td>
                    <td>' . $result['date_requested'] . '</td>
                 
            </tr>';
                                }
                            } catch (Exception $e) {
                                echo "Error: " . $e->getMessage();
                            }
                            $countQuery = "SELECT COUNT(*) as total FROM userall c
                                JOIN maintenance_request a ON a.room_id = c.room_id
                                JOIN status_request b ON b.maintenance_request_id = a.maintenance_request_id
                                $finalWhere";
                            $countResult = mysqli_query($db_connection, $countQuery);


                            $totalRows = mysqli_fetch_assoc($countResult)['total'];
                            $totalPages = ceil($totalRows / $rowsPerPage);
                            $filter_status = "";


                            ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <div class="action-buttons">
                <form id="bulkEditForm" method="post" action="maintenance_CRUD/edit_maintenance_bulk.php">
                    <button type="submit" name="bulkUpdate" class="btn btn-edit" id="bulkEditBtn" disabled>
                        ✏️ Edit Selected
                    </button>
                </form>

                <form id="bulkDeleteForm" method="post" action="maintenance_CRUD/delete_maintenance_bulk.php">
                    <button type="submit" name="bulkDelete" class="btn btn-delete" id="bulkDeleteBtn" onclick="confirmBulkDelete()" disabled>
                        🗑️ Delete Selected
                    </button>
                </form>

                <form id="bulkEditUnselectedForm" method="post" action="maintenance_CRUD/edit_maintenance_bulk.php">
                    <button type="submit" name="UnselectedbulkUpdate" class="btn btn-edit" id="bulkEditUnselectedBtn">
                        ✏️ Edit Unselected
                    </button>
                </form>

                <form id="bulkUnselectedDeleteForm" method="post" action="maintenance_CRUD/delete_maintenance_bulk.php">
                    <button type="button" name="bulkDelete" class="btn btn-delete" id="bulkUnselectedDeleteBtn" onclick="confirmBulkUnselectedDelete()">
                        🗑️ Delete Unselected
                    </button>
                </form>
            </div>


            <?php if (!isset($_GET['viewAll'])): ?>

                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?rowsPerPage=<?= $rowsPerPage ?>&page=<?= $page - 1 ?>">Previous</a>
                    </li>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?rowsPerPage=<?= $rowsPerPage ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?rowsPerPage=<?= $rowsPerPage ?>&page=<?= $page + 1 ?>">Next</a>
                    </li>
                </ul>

            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <form method="get" id="filterForm" class="d-flex gap-2 align-items-center">

                    <button type="submit" class="btn btn-primary" name="viewAll" value="1">View All Request</button>
                </form>
            </div>
        </div>
    </main>

    <div id="helpModal" class="help-modal" aria-hidden="true">
        <div class="help-modal-content" role="dialog" aria-modal="true" aria-labelledby="helpTitle">
            <div class="help-modal-header">
                <h2 id="helpTitle"><i class="fa-solid fa-graduation-cap"></i> Help Center - System Tutorial</h2>
                <button id="helpClose" class="help-close" aria-label="Close">&times;</button>
            </div>

            <div class="help-modal-body">

                <div class="tutorial-content">
                    <!-- Step 1 -->
                    <div id="step1" class="tutorial-step">
                        <div class="step-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                        <h3>How to Add Maintenance and Complaint</h3>
                        <div class="step-layout">
                            <div class="step-content">
                                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                    Step 1: Submit a Maintenance or Complaint Request
                                </h4>
                                <img src="../images/arrow_addmainte_tenant_help.png" alt="Add Building Screenshot" class="step-image">
                                <p>Select an <strong>Issue Category</strong> and describe the problem in the <strong>Description Box</strong>. Once done, click <strong>Submit</strong> to send your request for review.</p>

                            </div>
                            <div class="step-highlight">
                                <i class="fa-solid fa-lightbulb"></i>
                                <span>You can access this help anytime by clicking the Help button.</span>
                            </div>
                        </div>



                    </div>



                    <div id="step2" class="tutorial-step">
                        <div class="step-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                        <h3>How to Edit Maintenance and Complaint Request</h3>
                        <div class="step-layout">
                            <div class="step-content">
                                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                    Step 1: Select a Maintenance Request
                                </h4>
                                <img src="../images/arrow_editmainte_tenant_help.png" alt="Add Building Screenshot" class="step-image">
                                <p>
                                    Click the <strong>checkbox</strong> beside the request you want to manage.
                                    Once selected, options like <strong>Edit</strong> or <strong>Delete</strong> become available below the table.
                                </p>

                            </div>
                        </div>

                        <div class="step-layout">
                            <div class="step-content">
                                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                    Step 2: Edit the Selected Request
                                </h4>
                                <img src="../images/edit_mainte_tenant_help.png" alt="Add Building Screenshot" class="step-image">
                                <p>
                                    After clicking <strong>Edit Selected</strong>, an edit form will appear.
                                    You can update the <strong>Issue Type</strong>, <strong>Description</strong>, or <strong>Date Requested</strong>,
                                    then click <strong>Save All Changes</strong> to apply the updates.
                                </p>


                                <div class="step-highlight">
                                    <i class="fa-solid fa-lightbulb"></i>
                                    <span>You can access this help anytime by clicking the Help button.</span>
                                </div>
                            </div>
                        </div>




                    </div>

                    <div id="step3" class="tutorial-step">
                        <div class="step-icon"><i class="fa-solid fa-file-contract"></i></div>
                        <h3>How to Generate Guest Pass</h3>
                        <div class="step-layout">
                            <div class="step-content">
                                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                    Step 1: Fill Out the Guest Pass Form
                                </h4>
                                <img src="../images/guest_qrpass_help.png" alt="Add Building Screenshot" class="step-image">
                                <p>
                                    Enter the guest’s <strong>Name</strong> and <strong>Email</strong> in the form.
                                    After filling in the details, you can <strong>Email PDF</strong>, <strong>Print</strong>, or <strong>Download PDF</strong> for the guest’s pass.
                                </p>

                            </div>
                        </div>

                        <div class="step-layout">
                            <div class="step-content">
                                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                    Step 2: Edit the Selected Request
                                </h4>
                                <img src="../images/edit_mainte_tenant_help.png" alt="Add Building Screenshot" class="step-image">
                                <p>
                                    After clicking <strong>Edit Selected</strong>, an edit form will appear.
                                    You can update the <strong>Issue Type</strong>, <strong>Description</strong>, or <strong>Date Requested</strong>,
                                    then click <strong>Save All Changes</strong> to apply the updates.
                                </p>


                                <div class="step-highlight">
                                    <i class="fa-solid fa-lightbulb"></i>
                                    <span>You can access this help anytime by clicking the Help button.</span>
                                </div>
                            </div>
                        </div>




                    </div>




                </div>
            </div>

            <div class="help-modal-footer">
                <button id="helpPrev" class="help-btn-secondary" disabled>Previous</button>
                <div style="flex:1"></div>
                <button id="helpNext" class="help-btn-primary">Next</button>
                <button id="helpFinish" class="help-btn-primary" style="display:none">Finish</button>
            </div>
        </div>
    </div>

    <script>
        // Simple function to open help modal
        function openHelpModal() {
            const helpModal = document.getElementById('helpModal');
            helpModal.classList.add('open');
            helpModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            // Call initModal if it exists
            if (typeof initModal === 'function') {
                initModal();
            }
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const helpBtn = document.getElementById('helpBtn');
            const helpModal = document.getElementById('helpModal');
            const helpClose = document.getElementById('helpClose');
            const helpNext = document.getElementById('helpNext');
            const helpPrev = document.getElementById('helpPrev');
            const helpFinish = document.getElementById('helpFinish');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');

            let currentStep = 1;
            const totalSteps = 2;

            function openModal() {
                helpModal.classList.add('open');
                helpModal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';

                currentStep = 1; // ensure step is reset
                updateStepUI(); // show Step 1
            }

            function closeModal() {
                helpModal.classList.remove('open');
                helpModal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            console.log("Showing step", currentStep);

            function updateStepUI() {
                // hide all
                for (let i = 1; i <= totalSteps; i++) {
                    const el = document.getElementById('step' + i);
                    if (el) {
                        el.classList.remove('active');
                        el.style.display = 'none';
                    }
                }
                // show current
                const cur = document.getElementById('step' + currentStep);
                if (cur) {
                    cur.classList.add('active');
                    cur.style.display = 'block';
                }

                // progress
                const pct = (currentStep / totalSteps) * 100;
                if (progressFill) progressFill.style.width = pct + '%';
                if (progressText) progressText.textContent = `Step ${currentStep} of ${totalSteps}`;

                // buttons
                if (helpPrev) helpPrev.disabled = currentStep === 1;
                if (helpNext) helpNext.style.display = currentStep === totalSteps ? 'none' : 'inline-block';
                if (helpFinish) helpFinish.style.display = currentStep === totalSteps ? 'inline-block' : 'none';
            }

            window.changeStep = function(direction) {
                const newStep = currentStep + (direction || 0);
                if (newStep < 1 || newStep > totalSteps) return;
                currentStep = newStep;
                updateStepUI();
            };

            const cur = document.getElementById('step' + currentStep);
            if (cur) {
                cur.classList.add('active');
                cur.style.display = 'block';
            }

            helpBtn.addEventListener('click', openModal);
            helpClose.addEventListener('click', closeModal);
            helpNext.addEventListener('click', () => changeStep(1));
            helpPrev.addEventListener('click', () => changeStep(-1));
            helpFinish.addEventListener('click', closeModal);

            // click outside (backdrop)
            helpModal.addEventListener('click', (e) => {
                if (e.target === helpModal) closeModal();
            });

            // keyboard
            document.addEventListener('keydown', (e) => {
                if (!helpModal.classList.contains('open')) return;
                if (e.key === 'Escape') closeModal();
                if (e.key === 'ArrowRight') changeStep(1);
                if (e.key === 'ArrowLeft') changeStep(-1);
            });

            // run once to ensure no stray hidden state
            initModal();
        });
    </script>
    <script src="../js/script.js"></script>
    <script>
        const tenantCheckboxes = document.querySelectorAll('.tenant-checkbox');
        const editForm = document.getElementById('bulkEditForm');
        const editUnselectedForm = document.getElementById('bulkEditUnselectedForm');
        const deleteForm = document.getElementById('bulkDeleteForm');
        const deleteUnselectedForm = document.getElementById('bulkUnselectedDeleteForm');
        const editBtn = document.getElementById('bulkEditBtn');
        const deleteBtn = document.getElementById('bulkDeleteBtn');
        const unselectedBtn = document.querySelector('#bulkEditUnselectedForm button');
        const deleteUnselectedBtn = document.getElementById('bulkUnselectedDeleteBtn');
        const selectAll = document.getElementById("selectAllCheckbox");

        function syncCheckboxes() {
            // Clear old hidden inputs
            [editForm, editUnselectedForm, deleteForm, deleteUnselectedForm].forEach(form => {
                form.querySelectorAll('input[name="selectedRequest[]"]').forEach(el => el.remove());
            });

            let anyChecked = false;
            let anyUnchecked = false;

            tenantCheckboxes.forEach(cb => {
                if (cb.checked) {
                    anyChecked = true;

                    // Selected -> Edit + Delete
                    const selectedInput = document.createElement('input');
                    selectedInput.type = 'hidden';
                    selectedInput.name = 'selectedRequest[]';
                    selectedInput.value = cb.value;
                    editForm.appendChild(selectedInput);

                    const deleteInput = selectedInput.cloneNode(true);
                    deleteForm.appendChild(deleteInput);
                } else {
                    anyUnchecked = true;

                    // Unselected -> Unselected Edit + Unselected Delete
                    const unselectedInput = document.createElement('input');
                    unselectedInput.type = 'hidden';
                    unselectedInput.name = 'selectedRequest[]';
                    unselectedInput.value = cb.value;
                    editUnselectedForm.appendChild(unselectedInput);

                    const delUnselectedInput = unselectedInput.cloneNode(true);
                    deleteUnselectedForm.appendChild(delUnselectedInput);
                }
            });

            // Enable/disable buttons
            editBtn.disabled = !anyChecked;
            deleteBtn.disabled = !anyChecked;
            unselectedBtn.disabled = !anyUnchecked;
            deleteUnselectedBtn.disabled = !anyUnchecked;

            // 🔹 Update Select All checkbox dynamically
            selectAll.checked = [...tenantCheckboxes].every(cb => cb.checked);
        }

        // 🔹 Handle Select All clicks
        selectAll.addEventListener("change", function() {
            tenantCheckboxes.forEach(cb => cb.checked = this.checked);
            syncCheckboxes();
        });

        // 🔹 Handle individual tenant checkbox clicks
        tenantCheckboxes.forEach(cb => {
            cb.addEventListener('change', syncCheckboxes);
        });

        // Run once on load (populate forms correctly)
        syncCheckboxes();
    </script>

</body>

</html>