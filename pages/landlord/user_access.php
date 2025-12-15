<?php
if (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}
if (file_exists('../includes/database.php')) {
    include_once('../includes/database.php');
}
session_start();
$user_id_landlord = $_SESSION['user_id'];
$message = $_GET['message'] ?? '';
$warning = $_SESSION['warning_approval'] ?? "";
$user_id_landlord = $_SESSION['user_id'];
$stmtlandlord = $pdo->prepare("SELECT tenant_priviledge FROM userall WHERE user_id = ?");
$stmtlandlord->execute([$user_id_landlord]);
$landlord = $stmtlandlord->fetch(PDO::FETCH_ASSOC);
$disabled_button = "";


$sql = 'SELECT * FROM userall WHERE user_id = ' . $user_id_landlord;
$rs = mysqli_query($db_connection, $sql);
$rw = mysqli_fetch_array($rs);
$landlord_tenant_priviledge = $landlord['tenant_priviledge'];

if ($landlord['tenant_priviledge'] === 'Not Approved') {
    $disabled_button = "disabled";
    $warning = "It seems you do not have the approval from the Owner to edit and delete the account status";
} else {
    $disabled_button = "";
    $warning = "";
}
if (isset($_GET['message']) && $_GET['message'] === 'account_updated') {
    $warning = 'Account Updated';
}
if (isset($_GET['message']) && $_GET['message'] === 'accounts_updated') {
    $warning = 'Accounts Updated';
}

if (isset($_GET['message']) && $_GET['message'] === 'account_deleted_permanent') {
    $warning = 'Account Permanently Deleted';
}
if (isset($_GET['message']) && $_GET['message'] === 'accounts_deleted') {
    $warning = 'Approved Accounts has been Deleted and Not approved accounts has notify the admin for approval';
}
$rowsPerPage = isset($_GET['rowsPerPage']) ? (int) $_GET['rowsPerPage'] : 5;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

$offset = ($page - 1) * $rowsPerPage;

$limitClause = '';
if (!isset($_GET['viewAll'])) {
    $limitClause = "LIMIT $rowsPerPage OFFSET $offset";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Access Management Landlord</title>
    <link rel="stylesheet" href="../css/rule4.css">
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
        function confirmDelete(user_id) {
            const deleteBtn = document.getElementById('deletebtn');
            if (deleteBtn.disabled) {
                return; // Prevent SweetAlert if button is disabled
            }
            Swal.fire({
                icon: 'warning',
                title: 'Delete Account',
                text: 'Are you sure you want to delete this account?',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'tenant_CRUD/delete_tenant.php?id=' + user_id;
                }
            });
        }

        function confirmPermanentDelete() {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Account',
                text: 'Are you sure you want to delete all the data to this accounts, this will not be reverted?',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'tenant_CRUD/delete_permanent_tenant.php';
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
                title: 'Delete Accounts',
                text: 'Are you sure you want to Delete these Accounts?',
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
                title: 'Delete Tenants',
                text: 'Are you sure you want to Delete these Tenants?',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    bulkDeleteForm.submit();
                }
            });
        }

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
    </script>

</head>

<body>
    <div class="side-bar">
        <div class="burger-btn" id="burgerBtn">
            <i class="fas fa-bars"></i>
        </div>
        <a href="landlord_dashboard.php" class="logo">
            <img src="../images/Logo No Background Black.png" alt="" class="logo-img">
        </a>
        <ul class="nav-link">

            <li><a href="landlord_dashboard.php"><i class="fas fa-th-large"></i>
                    <p>DashBoard</p>
                </a></li>
            <li><a href="user_access.php"><i class="fas fa-users"></i>
                    <p>User Access Management</p>
                </a></li>
            <li><a href="tenant_manage.php"><i class="fas fa-user-check"></i>
                    <p>Room Management</p>
                </a></li>
            <li><a href="notification_log.php"><i class="fas fa-bell"></i>
                    <p>Billing Notifications</p>
                </a></li>
            <li><a href="rentreportlandlord.php"><i class="fas fa-file-lines"></i>
                    <p>Report Management</p>
                </a></li>
            <li><a href="landlordprofile.php"><i class="fas fa-cog"></i>
                    <p>User Account</p>
                </a></li>
            <li><a href="logout.php" id="logoutBtn" class="logout-btn "><i class="fas fa-sign-out-alt"></i>
                    <p> Logout</p>
                </a></li>
        </ul>
    </div>

    <main class="main">
        <div class="topbar">
            <div>
                <h1>User <br>
                    Management</h1>
            </div>

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
                            $sql123 = 'SELECT COUNT(*) as total_notification FROM notification WHERE user_id = ' . $user_id_landlord . ' AND notif_status = "unread"';
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
                        $stmt->execute([$user_id_landlord, $user_id_landlord]);
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
                                $text_stmt->execute([$notif_title, $user_id_landlord]);
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

                                    fetch('../tenant/marking_notif/markNotificationAsRead.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/x-www-form-urlencoded'
                                            },
                                            body: `notif_title=${encodeURIComponent(notifTitle)}`
                                        })
                                        .then(response => response.text())
                                        .then(result => {
                                            fetch('../tenant/marking_notif/getUnreadCount.php')
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
        <?php if (!empty($warning)): ?>
            <div class="alert alert-warning"><?php echo htmlspecialchars($warning); ?></div>
        <?php endif; ?>

        <div class="container mt-4">
            <div class="d-flex justify-content-center mb-3">
                <form method="get" class="d-flex gap-2" style="max-width: 600px; width: 100%;">
                    <input type="text" class="form-control" name="search" placeholder="Search..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
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
                                <th>Tenant Name</th>
                                <th>Room</th>
                                <th>Email Address</th>


                                <th>Status</th>
                                <?php if ($landlord['tenant_priviledge'] === 'Approved'): ?>
                                    <th>Deletion Approval (Owner)</th>
                                <?php endif; ?>
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
                            a.first_name LIKE '%$searchSafe%' OR 
                            a.last_name LIKE '%$searchSafe%' OR 
                            b.room_number LIKE '%$searchSafe%' OR 
                            a.email LIKE '%$searchSafe%' OR
                            a.account_status LIKE '%$searchSafe%' 

                        )";
                                }

                                $whereParts = [];
                                if (!empty($searchClause)) {
                                    $whereParts[] = $searchClause;
                                }

                                $finalWhere = 'WHERE a.account_status = "Active" OR a.account_status = "Inactive" OR a.account_status = "Pending"';
                                if (!empty($whereParts)) {
                                    $finalWhere = 'WHERE a.account_status = "Active" OR a.account_status = "Inactive" OR a.account_status = "Pending" AND' . implode(' AND ', $whereParts);
                                }

                                $sql = "SELECT a.*, b.room_number
                        FROM userall a
                        JOIN room b ON b.room_id = a.room_id
                                $finalWhere
                                ORDER BY a.first_name $limitClause";



                                $result_query = mysqli_query($db_connection, $sql);
                                while ($result = mysqli_fetch_array($result_query)) {

                                    echo '<tr>
                        <td class="text-center">
                            <input type="checkbox" class="tenant-checkbox" value="' . $result['user_id'] . '">
                        </td>
                            <td> ' . $result['first_name'] . ' ' . $result['last_name'] . '</td>
                    <td>' . $result['room_number'] . '</td>
                    <td>' . $result['email'] . '</td>';

                                    if ($result['account_status'] === 'Active') {
                                        $account_status_color = 'success';
                                    } elseif ($result['account_status'] === 'Inactive') {
                                        $account_status_color = 'danger';
                                    } elseif ($result['account_status'] === 'Pending') {
                                        $account_status_color = 'warning';
                                    }

                                    if ($result['deletion_approval'] === 'Not Approved') {
                                        $approval_color = 'warning';
                                        $text_color = 'black';
                                    } elseif ($result['deletion_approval'] === 'Approved') {
                                        $approval_color = 'success';
                                        $text_color = 'light';
                                    }
                                    echo '<td>
                            <span class="badge bg-light text-' . $account_status_color . '">
                            <i class="fas fa-circle text-' . $account_status_color . ' me-1"></i> ' . $result['account_status'] . '
                            </span></td>';
                                    if ($landlord['tenant_priviledge'] === "Approved") {
                                        echo '<td><span class="badge bg-' . $approval_color . ' text-' . $text_color . '" >' . $result['deletion_approval'] . '
                            </span>
                       </td>';
                                    }
                                    echo  '</tr>';
                                }

                                $countQuery = "SELECT COUNT(*) as total FROM userall a
               JOIN room b ON b.room_id = a.room_id
               $finalWhere";
                                $countResult = mysqli_query($db_connection, $countQuery);


                                $totalRows = mysqli_fetch_assoc($countResult)['total'];
                                $totalPages = ceil($totalRows / $rowsPerPage);
                                $filter_status = "";
                            } catch (Exception $e) {
                                echo "Error: " . $e->getMessage();
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </form>

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
                                <div class="step-icon"><i class="fa-solid fa-users-gear"></i></div>
                                <h3>User Management Page</h3>
                                <div class="step-layout">
                                    <div class="step-content">
                                        <img src="../images/usermanage_landlord_help.png" alt="Add Building Screenshot" class="step-image">
                                        <p>The <strong>User Management</strong> page allows administrators to manage all users within the system. You can view, edit, or delete user accounts, and monitor their status and deletion approval. This section also includes options to manage specific user groups such as <strong>tenants</strong> and <strong>landlords</strong>, as well as view deleted or pending tenant accounts. Use the search bar to quickly locate users by name or email.</p>
                                    </div>
                                </div>

                                <div class="step-layout">
                                    <div class="step-content">
                                        <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                            How to Edit User
                                        </h4>
                                        <img src="../images/arrow_edituser_landlord_help.png" alt="Add Building Screenshot" class="step-image">
                                        <p>Use these buttons to edit or delete users. You can edit or delete selected users, or even edit the unselected ones using the Edit Unselected option.</p>





                                    </div>
                                </div>

                                <div class="step-layout">
                                    <div class="step-content">
                                        <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                            Edit User Page
                                        </h4>
                                        <img src="../images/edit_page_help.png" alt="Add Building Screenshot" class="step-image">
                                        <p>When you click “Edit Selected” or “Edit Unselected,” this window will appear. It allows you to view and update tenant details, change account status, or approve deletion. You can then save changes or cancel using the buttons below.</p>





                                        <div class="step-highlight">
                                            <i class="fa-solid fa-lightbulb"></i>
                                            <span>You can access this help anytime by clicking the Help button.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- Step 2 -->
                        <div id="step2" class="tutorial-step">

                            <div class="step-icon"><i class="fa-solid fa-user-pen"></i></div>
                            <h3>How to Show Deleted User</h3>
                            <div class="step-layout">
                                <div class="step-content">
                                    <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                        Step 1: Click the "Show Deleted User"
                                    </h4>
                                    <img src="../images/arrow_deleteduser_help.png" alt="Add Building Screenshot" class="step-image">
                                    <p>Click the <strong>“Show Deleted Users”</strong> button to view all tenant accounts that have been previously deleted. This feature allows you to review, restore, or permanently remove users from the system if needed.</p>
                                </div>
                            </div>

                            <div class="step-layout">
                                <div class="step-content">
                                    <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                        Step 2: Deleted User Modal
                                    </h4>
                                    <img src="../images/deleted_user_help.png" alt="Add Building Screenshot" class="step-image">
                                    <p>This window shows deleted user accounts. You can search for users, export the list as a PDF, or permanently delete selected accounts.</p>




                                    <div class="step-highlight">
                                        <i class="fa-solid fa-lightbulb"></i>
                                        <span>You can access this help anytime by clicking the Help button.</span>
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

            <!-- Delete Form -->
            <div class="action-buttons">
                <form id="bulkEditForm" method="post" action="tenant_CRUD/edit_tenant_bulk.php">
                    <button type="submit" name="bulkUpdate" class="btn btn-edit" id="bulkEditBtn" disabled>
                        ✏️ Edit Selected
                    </button>
                </form>

                <form id="bulkDeleteForm" method="post" action="tenant_CRUD/delete_tenant_bulk.php">
                    <button type="submit" name="bulkDelete" class="btn btn-delete" id="bulkDeleteBtn" onclick="confirmBulkDelete()" disabled>
                        🗑️ Delete Selected
                    </button>
                </form>

                <form id="bulkEditUnselectedForm" method="post" action="tenant_CRUD/edit_tenant_bulk.php">
                    <button type="submit" name="UnselectedbulkUpdate" class="btn btn-edit" id="bulkEditUnselectedBtn">
                        ✏️ Edit Unselected
                    </button>
                </form>

                <form id="bulkUnselectedDeleteForm" method="post" action="tenant_CRUD/delete_tenant_bulk.php">
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

                    <button type="submit" class="btn btn-primary" name="viewAll" value="1">View All Tenants</button>
                </form>
                <button type="button" class="btn btn-secondary mb-3" data-bs-toggle="modal" data-bs-target="#deletedUsersModal">
                    Show Deleted Users
                </button>

            </div>
        </div>
    </main>
    <div class="modal fade" id="deletedUsersModal" tabindex="-1" aria-labelledby="deletedUsersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content shadow-sm border-0">
                <!-- Header -->
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-semibold" id="deletedUsersModalLabel">Deleted User Accounts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Body -->
                <div class="modal-body py-4 px-4">
                    <!-- Action Buttons -->
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <form action="tenant_CRUD/exportpdfdeletedaccounts.php" target="_blank" method="post">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-file-contract me-2"></i> Generate PDF
                            </button>
                        </form>

                        <button type="button" class="btn btn-outline-danger" onclick="confirmPermanentDelete()">
                            <i class="fas fa-trash-alt me-2"></i> Permanent Delete
                        </button>
                    </div>
                    <form method="GET" class="mb-3">
                        <div class="input-group">
                            <input type="text" name="search_deleted_user" class="form-control" placeholder="Search tenant, or email"
                                value="<?php echo isset($_GET['search_deleted_user']) ? htmlspecialchars($_GET['search_deleted_user']) : ''; ?>">
                            <button type="submit" class="btn btn-primary">Search</button>
                        </div>
                    </form>
                    <!-- Deleted Users Table -->
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered text-center align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Email Address</th>
                                    <th>Date Registered</th>

                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $search = isset($_GET['search_deleted_user']) ? trim($_GET['search_deleted_user']) : '';

                                $sql = "SELECT first_name, last_name, email, role, date_registered
                                        FROM userall 
                                        WHERE account_status = 'Deleted'";

                                if (!empty($search)) {
                                    $search_escaped = mysqli_real_escape_string($db_connection, $search);
                                    $sql .= " AND (
                                                first_name LIKE '%$search_escaped%' 
                                                OR last_name LIKE '%$search_escaped%' 
                                                OR email LIKE '%$search_escaped%'
                                                OR role LIKE '%$search_escaped%'
                                            )";
                                }

                                $result_query = mysqli_query($db_connection, $sql);
                                while ($result = mysqli_fetch_array($result_query)) {
                                    echo '<tr>
                                    <td>' . htmlspecialchars($result['first_name'] . ' ' . $result['last_name']) . '</td>
                                    <td>' . htmlspecialchars($result['email']) . '</td>
                                    <td>' . htmlspecialchars($result['date_registered']) . '</td>
                                </tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
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
                form.querySelectorAll('input[name="selected_tenant[]"]').forEach(el => el.remove());
            });

            let anyChecked = false;
            let anyUnchecked = false;

            tenantCheckboxes.forEach(cb => {
                if (cb.checked) {
                    anyChecked = true;

                    // Selected -> Edit + Delete
                    const selectedInput = document.createElement('input');
                    selectedInput.type = 'hidden';
                    selectedInput.name = 'selected_tenant[]';
                    selectedInput.value = cb.value;
                    editForm.appendChild(selectedInput);

                    const deleteInput = selectedInput.cloneNode(true);
                    deleteForm.appendChild(deleteInput);
                } else {
                    anyUnchecked = true;

                    // Unselected -> Unselected Edit + Unselected Delete
                    const unselectedInput = document.createElement('input');
                    unselectedInput.type = 'hidden';
                    unselectedInput.name = 'selected_tenant[]';
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
    <script src="../js/script.js"></script>
</body>

</html>