<?php
if (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}
if (file_exists('../includes/database.php')) {
    include_once('../includes/database.php');
}
$warning = "";
$warning_for_view_contract = '';

$disabled_inputs = '';
$disabled_inputs_for_contract = '';

session_start();
$user_id = $_SESSION['user_id'];
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Tenant') {
    header("Location: ../login.php");
    exit();
}

$sql = 'SELECT a.*, r.* FROM userall a 
JOIN room r ON a.room_id = r.room_id
WHERE a.user_id = ' . $user_id;
$rs = mysqli_query($db_connection, $sql);
$rw = mysqli_fetch_array($rs);
$building_id = $rw['building_id'];
$room_id = $rw['room_id'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Dashboard</title>
    <link rel="stylesheet" href="../css/styletenantdash.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <style>
        .custom-modal {
            max-width: 950px;
        }
    </style>
    <script>
        function gotopayment() {
            window.location.href = "tenantpayment.php";
        }

        function gotomaintenance() {
            window.location.href = "tenantmaintenance.php";
        }

        function gotoeditmaintenance(maintenance_request_id) {
            window.location.href = "maintenance_CRUD/edit_maintenance.php?id=" + maintenance_request_id;
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

        function confirmRenew(contract_id) {
            const renewalButton = document.getElementById('renewalButton');
            if (renewalButton.disabled) {
                return; // Prevent SweetAlert if button is disabled
            }
            const renewalForm = document.getElementById('renewForm');
            var msg = 'Are you sure, you want to renew your contract to this apartment';
            Swal.fire({
                icon: 'question',
                title: 'Renew',
                text: msg,
                showCancelButton: true,
                confirmButtonText: 'Renew',
                cancelButtonText: 'Cancel',
            }).then((result) => {
                if (result.isConfirmed) {
                    renewalForm.submit();
                } else {
                    location.reload();
                }
            });
        }

        function confirmApproval(contract_id) {
            const approvalbtn = document.getElementById('approvalButton');
            if (approvalbtn.disabled) {
                return; // Prevent SweetAlert if button is disabled
            }

            var msg = 'Are you sure you want to Approve the Owner to Terminate your contract anytime, because contract is a sensitive document, this will not be reverted, and your account will be deleted?';
            Swal.fire({
                icon: 'question',
                title: 'Approve',
                text: msg,
                showCancelButton: true,
                confirmButtonText: 'Approve',
                cancelButtonText: 'Cancel',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'approveOwner/approve_owner.php?id=' + contract_id;
                } else {
                    location.reload();
                }
            });
        }

        function confirmDelete(maintenance_request_id) {

            Swal.fire({
                icon: 'warning',
                title: 'Delete Request',
                text: 'Are you sure you want to delete this request?',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'maintenance_CRUD/delete_maintenance.php?status=dashboard&id=' + maintenance_request_id;
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
            <img src="../images/Copy of Logo No Background.png" alt="" class="logo-icon">
        </a>
        <ul class="nav-link">
            <li><a href="#"><i class="fas fa-th-large"></i>
                    <p>DashBoard</p>
                </a></li>
            <li><a href="tenantmaintenance.php"><i class="fas fa-file-contract"></i>
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
                <h2>Welcome <?php echo $rw['first_name'] ?></h2>
                <p>Tue, 20 May 2025</p>
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

        <div class="dashboard-cards">
            <div class="card rent">
                <?php

                $sql_lease_end = "SELECT end_date FROM contract WHERE user_id = $user_id LIMIT 1";

                $rs_end_date = mysqli_query($db_connection, $sql_lease_end);
                $end_date = mysqli_fetch_assoc($rs_end_date);
                // Get last payment made
                $sql_paid = "SELECT p.paid_on, p.due_date, rp.expected_amount_due, rp.tenant_payment FROM payment p 
                            JOIN rent_payment rp ON p.payment_id = rp.payment_id
                                WHERE p.contract_id IN (SELECT contract_id FROM contract WHERE user_id = $user_id)
                                AND p.paid_on IS NULL 
                                ORDER BY p.due_date DESC LIMIT 1";

                $rs_paid = mysqli_query($db_connection, $sql_paid);
                $last_paid = mysqli_fetch_assoc($rs_paid);
                // Now show in HTML
                ?>
                <h3>Rent Payment Status (<?php echo date("M Y"); ?>)</h3>
                <p><strong>Last Paid:</strong> <?php echo isset($last_paid['paid_on']) ? date("F j, Y", strtotime($last_paid['paid_on'])) : 'Not yet paid'; ?></p>
                <p><strong>Due Date:</strong> <?php echo isset($last_paid['due_date']) ? date("F j, Y", strtotime($last_paid['due_date'])) : 'None'; ?></p>
                <p><strong>Expected Amount Due:</strong> <?php echo $last_paid['expected_amount_due'] ?? '0'; ?></p>
                <p><strong>Total Rent Payment:</strong> <?php echo $last_paid['tenant_payment'] ?? '0'; ?></p>


                <div class="card-buttons">
                    <button class="btn-outline-white" data-bs-toggle="modal" data-bs-target="#paymentHistoryModal">View History</button>
                </div>
            </div>

            <div class="card lease">
                <h3>Lease Agreement</h3>

                <p><strong>Lease End:</strong> <?php echo date("F Y", strtotime($end_date['end_date'])); ?></p>
                <div class="card-buttons">
                    <button class="btn-outline-dark" data-bs-toggle="modal" data-bs-target="#tenantContractModal">View Contract</button>
                    <button class="btn-outline-dark" data-bs-toggle="modal" data-bs-target="#roomDetailsModal">
                        View Room Details
                    </button>
                </div>
            </div>

            <div class="card maintenance">
                <?php

                $sql1 = 'SELECT b.date_requested, b.issue_type, c.update_message, b.maintenance_request_id
                            FROM maintenance_request b
                            JOIN status_request c ON b.maintenance_request_id = c.maintenance_request_id
                            JOIN userall us ON us.room_id = b.room_id
                            WHERE b.room_id = ' . $room_id . ' AND us.user_id = ' . $user_id . '
                            ORDER BY b.maintenance_request_id DESC LIMIT 1';
                $rs1 = mysqli_query($db_connection, $sql1);
                if ($rs1) {
                    $rw1 = mysqli_fetch_assoc($rs1);
                } else {
                    echo "MySQLi Error: " . mysqli_error($db_connection);
                }

                ?>
                <h3>Maintenance Request </h3>
                <p><strong>Your Last Request:</strong> <?php echo $rw1['issue_type'] ?? 'No Request Yet' ?></p>
                <div class="card-buttons">
                    <?php
                    $disable = "";
                    $maintenance_request_id = 0;
                    if (!empty($rw1['maintenance_request_id']) && $rw1['maintenance_request_id'] != 0) {
                        $maintenance_request_id = $rw1['maintenance_request_id'];
                    } else {
                        $disable = "disabled";
                    }
                    ?>
                    <button class="btn-outline-dark" onclick="gotomaintenance()">Create New</button>
                </div>
            </div>
        </div>

        <div class="info-box">
            <div class="info-header">
                <div>
                    <h4>Complaints Status</h4>

                    <p class="info-date">Date: <span><?php echo isset($rw1['date_requested']) ? date("F j, Y", strtotime($rw1['date_requested'])) : 'No Request Yet'; ?></span></p>
                    <ul class="info-list">
                        <li>
                            <img src="https://cdn.jsdelivr.net/npm/lucide-static/icons/alert-triangle.svg" alt="alert"
                                class="icon-sm" />
                            Your Last Complaint: “<?php echo $rw1['issue_type'] ?? 'No Request Yet' ?>”
                        </li>
                        <li>
                            <?php
                            $color_status = empty($rw1['update_message']) ? "gray" : ($rw1['update_message'] === "Pending" ? "yellow" : "green");

                            ?>
                            <span class="dot <?php echo $color_status; ?>"></span>

                            <strong>Status: </strong><span> <?php echo $rw1['update_message'] ?? 'No Request Yet' ?></span>
                        </li>
                    </ul>
                </div>
                <div class="info-actions">
                    <?php
                    $onclick_edit_span_request = "";
                    if ($maintenance_request_id === 0) {
                        $onclick_edit_span_request = "";
                    } else {
                        $onclick_edit_span_request = 'onclick="gotoeditmaintenance( ' . $maintenance_request_id . ')"';
                    }
                    $onclick_delete_span_request = "";
                    if ($maintenance_request_id === 0) {
                        $onclick_delete_span_request = "";
                    } else {
                        $onclick_delete_span_request = 'onclick="confirmDelete( ' . $maintenance_request_id . ')"';
                    }
                    ?>

                    <span class="delete-icon" <?php echo $onclick_delete_span_request; ?>>&#128465;</span>
                    <button class="btn-primary" onclick="gotomaintenance()">File New Complaint</button>
                </div>
            </div>
        </div>

        <div class="info-box">
            <div class="info-header">
                <div>
                    <h4>Apartment Rules</h4>
                    <?php

                    $rules_query = 'SELECT * FROM rules WHERE rules_status = "Active" AND building_id = ' . $building_id . ' ORDER BY rules_id ASC LIMIT 2';
                    $rules_result = mysqli_query($db_connection, $rules_query);

                    if ($rules_result && mysqli_num_rows($rules_result) > 0) {
                        while ($rule = mysqli_fetch_assoc($rules_result)) {
                    ?>
                            <p>
                                <img src="https://cdn.jsdelivr.net/npm/lucide-static/icons/alert-triangle.svg" alt="alert" class="icon-sm" />
                                <span><strong><?= htmlspecialchars($rule['title']) ?></strong></span>
                            </p>
                            <ul class="info-list">
                                <li>- <?= htmlspecialchars($rule['rules_description']) ?></li>
                            </ul>
                    <?php
                        }
                    } else {
                        echo "<p>No rules found.</p>";
                    }
                    ?>
                </div>

                <div class="info-actions">
                    <button class="btn-primary" data-bs-toggle="modal" data-bs-target="#deletedUsersModal">View All Rules</button>
                </div>
            </div>
        </div>


    </main>





    <!-- Deleted Users Modal -->
    <div class="modal fade" id="deletedUsersModal" tabindex="-1" aria-labelledby="deletedUsersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletedUsersModalLabel">Apartment Rules</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

                </div>
                <div class="modal-body">

                    <?php
                    $rules_query = 'SELECT * FROM rules WHERE rules_status = "Active" AND building_id = ' . $building_id . ' ORDER BY rules_id ASC';
                    $rules_result = mysqli_query($db_connection, $rules_query);

                    if ($rules_result && mysqli_num_rows($rules_result) > 0) {
                        while ($rule = mysqli_fetch_assoc($rules_result)) {
                    ?>
                            <p>
                                <img src="https://cdn.jsdelivr.net/npm/lucide-static/icons/alert-triangle.svg" alt="alert" class="icon-sm" />
                                <span><?= htmlspecialchars($rule['title']) ?></span>
                            </p>
                            <ul class="info-list">
                                <li>- <?= htmlspecialchars($rule['rules_description']) ?></li>
                            </ul>
                    <?php
                        }
                    } else {
                        echo "<p>No rules found.</p>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tenantContractModal" tabindex="-1" aria-labelledby="deletedUsersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content p-4 border border-primary rounded-4">
                <div class="modal-header border-0">
                    <h2 class="modal-title fw-bold" id="renewalModalLabel">
                        <em> Contract</em>
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

                </div>
                <div class="modal-body pt-0">

                    <?php

                    $owner_query = 'SELECT *
                    FROM userall WHERE role = "Admin"';
                    $rs_owner = mysqli_query($db_connection, $owner_query);
                    $owner = mysqli_fetch_assoc($rs_owner);

                    $rules_query = 'SELECT a.*, c.* 
                    FROM userall a 
                    JOIN contract c  ON a.user_id = c.user_id
                    WHERE a.user_id = ' . $user_id;
                    $contract_query = mysqli_query($db_connection, $rules_query);
                    $contract = mysqli_fetch_assoc($contract_query);


                    $termLabels = [
                        'deposit_return' => '- Security deposit is refundable upon move-out, minus any damages.',
                        'on_time_rent' => '- Rent must be paid on or before the due date each month.',
                        'no_subleasing' => '- Subleasing without landlord approval is not allowed.',
                        'utility_responsibility' => '- Tenant is responsible for all utility bills.',
                        'notice_required' => '- A 30-day notice is required before moving out.',
                        'property_care' => '- Tenant must maintain cleanliness and avoid damaging the property.'
                    ];

                    $termsArray = explode(', ', $contract['terms']);
                    $displayTerms = array_map(function ($term) use ($termLabels) {
                        return $termLabels[$term] ?? $term;
                    }, $termsArray);

                    if ($contract['update_status'] === 'Approved') {
                        $warning_for_view_contract = 'You have already given your consent to the owner';
                        $disabled_inputs_for_contract = 'disabled';
                    }
                    ?>

                    <div class="mb-3">

                        <?php if (!empty($warning_for_view_contract)): ?>
                            <div class="alert alert-warning"><?php echo htmlspecialchars($warning_for_view_contract); ?></div>
                        <?php endif; ?>
                        <label for="Name" class="form-label fw-semibold">Building Owner Name: </label>
                        <input type="text" class="form-control" id="ownername" value="<?= htmlspecialchars($owner['first_name']) ?> <?= htmlspecialchars($owner['last_name']) ?>" readonly>

                    </div>

                    <div class="mb-3">
                        <label for="Name" class="form-label fw-semibold">Room Number: </label>
                        <?php

                        $sql12345 = 'SELECT r.room_number FROM userall a
                JOIN room r ON a.room_id = r.room_id
                WHERE a.user_id = ' . $user_id;
                        $rs = mysqli_query($db_connection, $sql12345);
                        $room_number = mysqli_fetch_array($rs);
                        ?>
                        <input type="text" class="form-control" id="tenantname" value="<?= htmlspecialchars($room_number['room_number']) ?>" readonly>


                    </div>

                    <div class="mb-3">
                        <label for="Name" class="form-label fw-semibold">Tenant Name: </label>
                        <input type="text" class="form-control" id="tenantname" value="<?= htmlspecialchars($contract['first_name']) ?> <?= htmlspecialchars($contract['last_name']) ?>" readonly>


                    </div>

                    <div class="mb-3">
                        <label for="Date" class="form-label fw-semibold">Lease Agreement Start Date: </label>
                        <input type="date" class="form-control" id="originalStart" placeholder="dd/mm/yyyy" value="<?= htmlspecialchars($contract['start_date']) ?>" readonly>

                    </div>

                    <div class="mb-3">
                        <label for="Terms" class="form-label fw-semibold">Lease Agreement End Date: </label>
                        <input type="date" class="form-control" id="originalEnd" placeholder="dd/mm/yyyy" value="<?= htmlspecialchars($contract['end_date']) ?>" readonly>
                    </div>

                    <div class="mb-4">
                        <label for="Terms"><strong>Terms:</strong> </label><br><?= implode('<br>', array_map('htmlspecialchars', $displayTerms)) ?>

                    </div>

                    <?php
                    if ($contract['update_status'] === 'Approved') {
                        $warning_for_view_contract = 'You have already given your consent to the owner';
                        $disabled_inputs_for_contract = 'disabled';
                    } elseif ($contract['update_status'] === 'Not Approved') {
                        $warning_for_view_contract = '';
                        $disabled_inputs_for_contract = '';
                        echo ' <button class="btn btn-primary px-4" id="approvalButton" onclick="confirmApproval(' . $contract["contract_id"] . ')" ' . $disabled_inputs_for_contract . ' >Terminate Contract</button>';
                    }
                    ?>

                </div>
            </div>
        </div>
    </div>

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
                        <div class="step-icon"><i class="fa-solid fa-building"></i></div>
                        <h3>How to View Payment History</h3>
                        <div class="step-layout">
                            <div class="step-content">
                                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                    Step 1: Click the “View History” Button
                                </h4>
                                <img src="../images/arrow_history_help.png" alt="Add Building Screenshot" class="step-image">
                                <p>
                                    Clicking the <strong>View History</strong> button shows the tenant’s complete rent payment records, including previous and current transactions.
                                </p>

                            </div>
                        </div>


                        <div class="step-layout">
                            <div class="step-content">
                                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                    Step 2: Viewing Payment History
                                </h4>
                                <img src="../images/view_history_tenant_help.png" alt="Add Building Screenshot" class="step-image">
                                <p>
                                    The <strong>Billing History</strong> modal shows the tenant’s previous and current rent payments, including <strong>due dates</strong>, <strong>amounts</strong>, <strong>payment methods</strong>, and <strong>statuses</strong>.
                                </p>





                                <div class="step-highlight">
                                    <i class="fa-solid fa-lightbulb"></i>
                                    <span>You can access this help anytime by clicking the Help button.</span>
                                </div>
                            </div>
                        </div>
                    </div>



                    <div id="step2" class="tutorial-step">
                        <div class="step-icon"><i class="fa-solid fa-file-contract"></i></div>
                        <h3>How to View Room Details</h3>
                        <div class="step-layout">
                            <div class="step-content">
                                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                    Step 1: Click the "Room Details" Button
                                </h4>
                                <img src="../images/arrow_view_room_details_help.png" alt="Add Building Screenshot" class="step-image">
                                <p>
                                    The dashboard gives tenants a quick overview of their rent status, lease details, maintenance requests, and complaints.
                                    You can click <strong>View Room Details</strong> to see specific room information.
                                </p>

                            </div>
                        </div>

                        <div class="step-layout">
                            <div class="step-content">
                                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                    Step 2: Viewing Room Details
                                </h4>
                                <img src="../images/view_roomdetails_help.png" alt="Add Building Screenshot" class="step-image">
                                <p>
                                    Shows room info like tenant, status, and monthly rate.
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
                        <h3>How to View All Rules of Apartment</h3>
                        <div class="step-layout">
                            <div class="step-content">
                                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                    Step 1: Click the "View All Rules" Button
                                </h4>
                                <img src="../images/arrow_viewallrules_help.png" alt="Add Building Screenshot" class="step-image">
                                <p>Shows all apartment rules for tenants to review.</p>


                            </div>
                        </div>



                        <div class="step-highlight">
                            <i class="fa-solid fa-lightbulb"></i>
                            <span>You can access this help anytime by clicking the Help button.</span>
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
            const totalSteps = 3;

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

    <div class="modal fade" id="paymentHistoryModal" tabindex="-1" aria-labelledby="paymentHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg custom-modal">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <?php

                    $sql = 'SELECT r.room_number FROM userall a
                JOIN room r ON a.room_id = r.room_id
                WHERE a.user_id = ' . $user_id;
                    $rs = mysqli_query($db_connection, $sql);
                    $result = mysqli_fetch_array($rs);


                    $sql1 = 'SELECT COALESCE(SUM(rp.tenant_payment),0) AS Total_balance FROM payment pay
                    JOIN rent_payment rp ON pay.payment_id = rp.payment_id
                JOIN contract c ON c.contract_id = pay.contract_id
                JOIN userall us ON c.user_id = us.user_id
                WHERE paid_on IS NOT NULL AND c.user_id = ' . $user_id;
                    $rs1 = mysqli_query($db_connection, $sql1);
                    $resultpay = mysqli_fetch_array($rs1);
                    ?>
                    <div>
                        <h2 class="modal-title fw-bold m-0" id="paymentHistoryModalLabel">Billing History</h2>
                        <div class="text-muted small">Room <?= htmlspecialchars($result['room_number']) ?> · <?= date("F Y") ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body pt-3">

                    <form method="get" class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" name="search" placeholder="Search by date, amount, status, method" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" />
                            <button class="btn btn-primary" type="submit">Search</button>
                        </div>
                    </form>

                    <div class="table-responsive modal-table-wrap">
                        <table class="table align-middle table-hover mb-0">
                            <thead class="table-light sticky-header">
                                <tr>
                                    <th>Last Payment</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                    <th class="text-end">Expected Amount</th>
                                    <th class="text-end">Paid Amount</th>
                                    <th>Method</th>
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
                            e.paid_on LIKE '%$searchSafe%' OR 
                            e.due_date LIKE '%$searchSafe%' OR 
                            rp.expected_amount_due LIKE '%$searchSafe%' OR 
                            e.payment_method LIKE '%$searchSafe%' OR
                            f.status LIKE '%$searchSafe%'
                        )";
                                    }

                                    $whereParts = [];
                                    if (!empty($searchClause)) {
                                        $whereParts[] = $searchClause;
                                    }

                                    $finalWhere = "WHERE a.user_id = $user_id";
                                    if (!empty($whereParts)) {
                                        $finalWhere .= ' AND (' . implode(' OR ', $whereParts) . ')';
                                    }


                                    $sql = "SELECT e.paid_on, rp.expected_amount_due, rp.tenant_payment, e.payment_method, e.due_date, f.status
                                            FROM contract a
                                            JOIN payment e ON e.contract_id = a.contract_id
                                            JOIN rent_payment rp ON e.payment_id = rp.payment_id
                                            JOIN payment_status f ON e.payment_id = f.payment_id
                                            $finalWhere
                                            ORDER BY e.due_date DESC";

                                    $result_query = mysqli_query($db_connection, $sql);
                                    if ($result_query && mysqli_num_rows($result_query) > 0) {
                                        while ($row = mysqli_fetch_array($result_query)) {
                                            $status_color = 'secondary';
                                            if ($row['status'] === 'PAID') {
                                                $status_color = 'success';
                                            } elseif ($row['status'] === 'UNPAID') {
                                                $status_color = 'warning';
                                            } elseif ($row['status'] === 'LATE') {
                                                $status_color = 'danger';
                                            } elseif ($row['status'] === 'LAST') {
                                                $status_color = 'primary';
                                            }

                                            echo '<tr>';
                                            echo isset($row['paid_on'])
                                                ? '<td>' . date("F j, Y", strtotime($row['paid_on'])) . '</td>'
                                                : '<td><span class="text-muted">Not Available</span></td>';
                                            echo '<td><span class="badge bg-light text-' . $status_color . '"><i class="fas fa-circle text-' . $status_color . ' me-1"></i> ' . $row['status'] . '</span></td>';
                                            echo '<td>' . date("F j, Y", strtotime($row['due_date'])) . '</td>';
                                            echo '<td class="text-end">' . number_format((float)$row['expected_amount_due'], 2) . '</td>';
                                            echo '<td class="text-end">' . number_format((float)$row['tenant_payment'], 2) . '</td>';
                                            echo isset($row['payment_method'])
                                                ? '<td>' . htmlspecialchars($row['payment_method']) . '</td>'
                                                : '<td><span class="text-muted">Not Available</span></td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="6" class="text-center text-muted py-4">No billing records found.</td></tr>';
                                    }
                                } catch (Exception $e) {
                                    echo '<tr><td colspan="6">Error: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="fw-semibold">
                            Total Paid: <span class="badge bg-success-subtle text-success border border-success-subtle">₱ <?= number_format((float)$resultpay['Total_balance'], 2) ?></span>
                        </div>
                        <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="roomDetailsModal" tabindex="-1" aria-labelledby="roomDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content p-4">
                <div class="modal-header border-0">
                    <h2 class="modal-title fw-bold" id="roomDetailsModalLabel">Room Details</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body pt-0">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Room:</label>
                        <p class="mb-1"><?= htmlspecialchars($rw['room_number']) ?></p>
                    </div>
                    <hr style="border: 0; border-top: 1px solid #ddd; margin: 8px 0;">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Assigned Tenant:</label>
                        <?php

                        $sql123 = 'SELECT * FROM userall WHERE room_id = ' . $rw['room_id'];
                        $result_ques = mysqli_query($db_connection, $sql123);

                        while ($result = mysqli_fetch_array($result_ques)) {
                            echo '<p class="mb-1">- ' . $result["first_name"] . ' ' . $result["last_name"] . '</p>';
                        }
                        ?>

                    </div>

                    <div class="mb-3">
                        <?php
                        $noutility = '';

                        $query_paid = "SELECT f.status
               FROM contract a
               JOIN payment e ON e.contract_id = a.contract_id
               JOIN rent_payment rp ON rp.payment_id = e.payment_id
               JOIN payment_status f ON e.payment_id = f.payment_id
               WHERE a.user_id = $user_id
                 AND MONTH(e.due_date) = MONTH(CURRENT_DATE())
                 AND YEAR(e.due_date) = YEAR(CURRENT_DATE())
                 ORDER BY e.due_date DESC";

                        $query_paid_result = mysqli_query($db_connection, $query_paid);
                        $result_paid = mysqli_fetch_array($query_paid_result);

                        $color_status = "";
                        if ($result_paid['status'] === "PAID") {
                            $color_status = "success";
                        } elseif ($result_paid['status'] === "UNPAID") {
                            $color_status = "warning";
                        } elseif ($result_paid['status'] === "LATE") {
                            $color_status = "danger";
                        } elseif ($result_paid['status'] === "LAST") {
                            $color_status = "primary";
                        }


                        ?>
                        <hr style="border: 0; border-top: 1px solid #ddd; margin: 8px 0;">
                        <label class="form-label fw-semibold">Current Room Payment Status (<?php echo (date("M Y")) ?>):</label>
                        <p class="mb-1">

                            <span class="badge bg-<?= $color_status ?>"><?= htmlspecialchars($result_paid['status']) ?></span>
                        </p>
                        <hr style="border: 0; border-top: 1px solid #ddd; margin: 8px 0;">

                        <label class="form-label fw-semibold">Utility Payment Status (<?php echo (date("M Y")) ?>):</label>
                        <hr style="border: 0; border-top: 1px solid #ddd; margin: 8px 0;">
                        <?php
                        $query_paid = "SELECT utt.*, ps.status, p.paid_on, p.due_date, ut.amount
                        FROM payment p
                            JOIN payment_status ps ON p.payment_id = ps.payment_id
                            JOIN utility_payment ut ON ut.payment_id = p.payment_id
                            JOIN utility_type utt ON utt.utility_type_id = ut.utility_type_id
                            JOIN contract c ON c.contract_id = p.contract_id
                            WHERE c.user_id = $user_id
                                AND MONTH(p.due_date) = MONTH(CURRENT_DATE())
                                AND YEAR(p.due_date) = YEAR(CURRENT_DATE())
                                ORDER BY p.due_date DESC";

                        $query_paid_result = mysqli_query($db_connection, $query_paid);

                        while ($result_paid = mysqli_fetch_array($query_paid_result)):

                            if ($result_paid['status'] === "PAID") {
                                $color_status_utility = "success";
                            } elseif ($result_paid['status'] === "LATE") {
                                $color_status_utility = "danger";
                            } elseif ($result_paid['status'] === "UNPAID") {
                                $color_status_utility = "warning";
                            }
                            if (empty($result_paid['status'])) {
                                $noutility = "<strong>Not paid yet to any utility bills</strong>";
                                $color_status_utility = "secondary";
                            }
                        ?>
                            <p class="mb-1">
                                <span><strong><?= htmlspecialchars($result_paid['utility_name']) ?>:</strong></span><br>
                                <span>Payment Amount: <?= htmlspecialchars($result_paid['amount']) ?></span><br>
                                <span>Payment Date: <?= htmlspecialchars(date('F j Y', strtotime($result_paid['paid_on']))) ?></span><br>
                                <span>Due Date: <?= htmlspecialchars(date('F j Y', strtotime($result_paid['due_date']))) ?></span><br>
                                <span>Status:
                                    <span class="badge bg-<?= $color_status_utility ?>">
                                        <?= htmlspecialchars($result_paid['status']) ?><?= $noutility ?>
                                    </span>
                                </span><br>
                            </p>

                            <hr style="border: 0; border-top: 1px solid #ddd; margin: 8px 0;">

                        <?php endwhile; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Monthly Rate:</label>
                        <p class="mb-1"><?= htmlspecialchars($rw['room_amount']) ?></p>
                    </div>

                    <div class="text-end">
                        <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <script src="../js/script.js"></script>
    <script src="../js/datatable.js"></script>
    <?php if (isset($_GET['message']) && $_GET['message'] === 'request_deleted'): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Request Canceled',
                text: 'The maintenance request has been deleted',
                confirmButtonText: 'OK'
            });
        </script>
    <?php endif; ?>
    <?php if (isset($_GET['message']) && $_GET['message'] === 'approval_success'): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Approval Success',
                text: 'You have given your consent to the Building Owner to edit your contract details',
                confirmButtonText: 'OK'
            });
        </script>
    <?php endif; ?>
</body>

</html>