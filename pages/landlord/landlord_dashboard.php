<?php
if (file_exists('includes/database.php')) {
    include_once('includes/database.php'); // expects $db_connection
}
if (file_exists('../includes/database.php')) {
    include_once('../includes/database.php'); // expects $db_connection
}

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Landlord') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$host = "localhost";
$username = "root";
$password = "";
$database = "apartment";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get profile info for header (uses $db_connection from includes)
$sql = 'SELECT * FROM userall WHERE user_id = ' . (int)$user_id;
$rs  = mysqli_query($db_connection, $sql);
$rw  = mysqli_fetch_array($rs);

// Time scopes
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m'); // monthly — default current month
$currentYear   = (int)date('Y');
$selectedMonthName = date('F', mktime(0, 0, 0, $selectedMonth, 10));

// KPI: total tenants (case-insensitive)
$tenantsQuery  = $conn->query("SELECT COUNT(*) AS total FROM user WHERE LOWER(role) = 'tenant' AND account_status = 'Active'");
$totalTenants  = (int)$tenantsQuery->fetch_assoc()['total'];
$onTimeQuery = $conn->prepare("
    SELECT COUNT(*) AS count
    FROM payment p 
    JOIN rent_payment rp ON rp.payment_id = p.payment_id
    JOIN payment_status ps ON ps.payment_id = p.payment_id
    WHERE MONTH(p.due_date) = ? AND YEAR(p.due_date) = ?
    AND p.paid_on IS NOT NULL AND p.paid_on <= p.due_date AND ps.is_active = 1
");
$onTimeQuery->bind_param("ii", $selectedMonth, $currentYear);
$onTimeQuery->execute();
$onTimePayments = (int)$onTimeQuery->get_result()->fetch_assoc()['count'];

// Late (paid_on set and > due_date) for selected month/year
$lateQuery = $conn->prepare("
    SELECT COUNT(*) AS count
    FROM payment p 
    JOIN rent_payment rp ON p.payment_id = rp.payment_id
    JOIN payment_status ps ON ps.payment_id = p.payment_id
    WHERE MONTH(p.due_date) = ? AND YEAR(p.due_date) = ?
    AND p.paid_on IS NOT NULL AND p.paid_on > p.due_date AND ps.is_active = 1
");
$lateQuery->bind_param("ii", $selectedMonth, $currentYear);
$lateQuery->execute();
$latePayments = (int)$lateQuery->get_result()->fetch_assoc()['count'];

// Unpaid (no payment recorded) for selected month/year
$unpaidQuery = $conn->prepare("
    SELECT COUNT(*) AS count
    FROM payment p
    JOIN payment_status ps ON ps.payment_id = p.payment_id
    JOIN rent_payment rp ON p.payment_id = rp.payment_id
    WHERE MONTH(p.due_date) = ? AND YEAR(p.due_date) = ?
    AND p.paid_on IS NULL AND ps.status = 'UNPAID' AND ps.is_active = 1
");
$unpaidQuery->bind_param("ii", $selectedMonth, $currentYear);
$unpaidQuery->execute();
$unpaidPayments = (int)$unpaidQuery->get_result()->fetch_assoc()['count'];

// Chart data for payment pie
$paymentLabels = ['On-Time', 'Late', 'Unpaid'];
$paymentData   = [$onTimePayments, $latePayments, $unpaidPayments];

// ===== Maintenance (Pending/Resolved) =====
$pendingComplaints = (int)$conn->query("
    SELECT COUNT(*) AS count
    FROM maintenance_request a
    JOIN status_request b ON a.maintenance_request_id = b.maintenance_request_id
    WHERE b.update_message = 'Pending'
")->fetch_assoc()['count'];

$resolvedComplaints = (int)$conn->query("
    SELECT COUNT(*) AS count
    FROM maintenance_request a
    JOIN status_request b ON a.maintenance_request_id = b.maintenance_request_id
    WHERE b.update_message = 'Resolved'
")->fetch_assoc()['count'];

$complaintLabels = ['Pending', 'Resolved'];
$complaintData   = [$pendingComplaints, $resolvedComplaints];

// ===== Room Popularity (Most Rented) =====
$roomLabels = [];
$roomData   = [];
$sqlRoom = "
    SELECT 
    r.room_number, 
    COUNT(c.contract_id) AS total_rented
FROM contract c
JOIN user u ON c.user_id = u.user_id
JOIN room r ON u.room_id = r.room_id
WHERE LOWER(u.role) = 'tenant'
  AND YEAR(c.start_date) = YEAR(CURDATE())
GROUP BY r.room_number
ORDER BY total_rented DESC;

";
$resRoom = $conn->query($sqlRoom);
while ($row = $resRoom->fetch_assoc()) {
    $roomLabels[] = $row['room_number'];
    $roomData[]   = (int)$row['total_rented'];
}

// ===== Tenant Registrations (per month in current year) =====
$tenantLabels = [];
$tenantData   = [];
for ($m = 1; $m <= 12; $m++) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS count
        FROM user
        WHERE LOWER(role) = 'tenant' AND MONTH(date_registered) = ? AND YEAR(date_registered) = ?
    ");
    $stmt->bind_param("ii", $m, $currentYear);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $tenantLabels[] = date('M', mktime(0, 0, 0, $m, 10));
    $tenantData[]   = (int)$result['count'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Landlord Dashboard</title>
    <link rel="stylesheet" href="../css/styledashowner4.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="side-bar">
        <div class="burger-btn" id="burgerBtn">
            <i class="fas fa-bars"></i>
        </div>
        <a href="landlord_dashboard.php" class="logo">
            <img src="../images/Logo No Background Black.png" alt="" class="logo-img">
            <img src="../images/Copy of Logo No Background.png" alt="" class="logo-icon">
        </a>
        <ul class="nav-link">
            <li><a href="landlord_dashboard.php"><i class="fas fa-th-large"></i>
                    <p>Dashboard</p>
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
            <li><a href="logout.php" id="logoutBtn" class="logout-btn"><i class="fas fa-sign-out-alt"></i>
                    <p>Logout</p>
                </a></li>
        </ul>
    </div>

    <main class="main">
        <div class="topbar">
            <div>
                <h2>Welcome Landlord</h2>
                <p><?php echo date('D, d M Y'); ?></p>
            </div>

            <?php if (!empty($_SESSION['contract_update_msg']) && !empty($_SESSION['notification'])): ?>
                <div class="alert alert-warning"><?php echo htmlspecialchars($_SESSION['contract_update_msg']); ?> <?php echo htmlspecialchars($_SESSION['notification']); ?></div>
            <?php endif;
            unset($_SESSION['contract_update_msg']); ?>
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
                    <div id="notification-container"
                        style="display: none; position: absolute; top: 60px; right: 16px; background-color: #fff; border: 1px solid #ccc; border-radius: 10px; width: min(85vw, 600px); max-height: 60vh; overflow-y: auto; box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1); z-index: 999;">

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

                                $text_stmt = $db_connection->prepare("
                SELECT notif_text 
                FROM notification 
                WHERE notif_title = ? AND user_id = ? 
                ORDER BY date_created ASC
            ");
                                $text_stmt->execute([$notif_title, $user_id]);
                                $notif_texts = $text_stmt->get_result();
                        ?>
                                <div id="notification-title-<?php echo $notif_id; ?>"
                                    class="notification-title"
                                    data-title="<?php echo htmlspecialchars($notif_title, ENT_QUOTES); ?>"
                                    style="padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; cursor: pointer;">

                                    <h6 style="margin: 0;"><strong><?php echo $notif_title; ?></strong></h6>

                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <small style="color: gray;">
                                            <?php echo $daysAgo; ?> day<?php echo $daysAgo > 1 ? 's' : ''; ?> ago
                                        </small>
                                        <?php if ($notification['notif_status'] === 'unread'): ?>
                                            <span class="badge bg-light text-success notif-dot">
                                                <i class="fas fa-circle text-primary me-1"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div id="notification-container-body-<?php echo $notif_id; ?>"
                                    class="notif-body"
                                    style="display: none;">
                                    <?php while ($notif_text = $notif_texts->fetch_assoc()): ?>
                                        <div class="notif-item">
                                            <p class="notif-text"><?php echo $notif_text['notif_text']; ?></p>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php
                            endwhile;
                        else:
                            ?>
                            <div style="padding: 10px; text-align: center; color: gray;">
                                No notification available
                            </div>
                        <?php endif; ?>
                    </div>

                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            const bellIcon = document.getElementById('bell-icon');
                            const notificationContainer = document.getElementById('notification-container');
                            const badge = document.querySelector('.badge.bg-danger'); // badge element

                            bellIcon.addEventListener('click', (e) => {
                                e.stopPropagation();
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
                                        // 🔽 Use class toggle; CSS controls layout/display
                                        body.classList.toggle('open');
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

        <div class="cards">
            <div class="card" style="background-color: #ffe5e5;">
                <i class="fas fa-users"></i>
                <h3><?php echo $totalTenants; ?></h3>
                <p>Total Tenants</p>
            </div>
            <div class="card" style="background-color: #fff3cd;" onclick="window.location.href='tenant_manage.php'">
                <i class="fas fa-briefcase"></i>
                <h3><?php echo $onTimePayments; ?></h3>
                <p>Payments on Time</p>
            </div>
            <div class="card" style="background-color: #e0f7fa;" onclick="window.location.href='tenant_manage.php'">
                <i class="fas fa-user-lock"></i>
                <h3><?php echo $latePayments; ?></h3>
                <p>Late Payments</p>
            </div>
            <div class="card" style="
                background: linear-gradient(to right, #e3f2fd, #fff3e0); 
                color: #333;
                border: 1px solid #ddd;
                border-radius: 10px;
                padding: 20px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);">
                <h4>System Notification</h4>
                <p><?php echo $pendingComplaints; ?> pending maintenance requests</p>
                <p>More system info here...</p>
            </div>
        </div>

        <div class="charts-wrapper">
            <button id="prevBtn" class="carousel-btn prev" aria-label="Previous">❮</button>

            <div class="charts" id="chartsCarousel">
                <div class="chart-card">
                    <h4>Room Popularity (Most Rented <?php echo $currentYear; ?>)</h4>
                    <canvas id="roomChart"></canvas>
                </div>

                <div class="chart-card">
                    <h4>Tenant Registrations (<?php echo $currentYear; ?>)</h4>
                    <canvas id="tenantChart"></canvas>
                </div>

                <div class="chart-card">
                    <h4>Payment Status (<?php echo $selectedMonthName . ' ' . $currentYear; ?>)</h4>
                    <canvas id="paymentChart"></canvas>
                </div>

                <div class="chart-card">
                    <h4>Maintenance Complaints</h4>
                    <canvas id="complaintChart"></canvas>
                </div>
            </div>

            <button id="nextBtn" class="carousel-btn next" aria-label="Next">❯</button>
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
                        <div class="step-icon"><i class="fa-solid fa-compass"></i></div>
                        <h3>System Navigation Guide</h3>
                        <p>Welcome to your apartment management system! This tutorial shows how to navigate key features.</p>
                        <div class="step-highlight" style="max-width:720px;margin:12px auto;padding:12px;background:#eaf4ff;border-radius:8px;">
                            <i class="fa-solid fa-lightbulb" style="color:var(--primary-color);margin-right:8px;"></i>
                            <span style="color:var(--muted-color);">You can access this help anytime by clicking the Help button.</span>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div id="step2" class="tutorial-step">
                        <div class="step-icon"><i class="fa-solid fa-bars"></i></div>
                        <h3>Main Navigation Menu</h3>
                        <p>The left sidebar is your main navigation hub. Click items to go to sections.</p>
                        <ul class="tutorial-list">
                            <li><strong>Dashboard:</strong> Overview page</li>
                            <li><strong>User Access:</strong> Tenant accounts & permissions</li>
                            <li><strong>Tenant Management:</strong> Manage tenant leases and information</li>
                            <li><strong>Billing Notification:</strong> Send and manage rent or utility billing alerts</li>
                            <li><strong>Report Management:</strong> Generate system reports</li>
                            <li><strong>User Account:</strong> Manage user roles</li>

                        </ul>
                    </div>

                    <!-- Step 3 -->
                    <div id="step3" class="tutorial-step">
                        <div class="step-icon"><i class="fa-solid fa-window-maximize"></i></div>
                        <h3>Top Navigation Bar</h3>
                        <p>Top bar contains date, notifications and profile controls.</p>
                    </div>

                    <!-- Step 4 -->
                    <div id="step4" class="tutorial-step">
                        <div class="step-icon"><i class="fa-solid fa-tachometer-alt"></i></div>
                        <h3>Dashboard Overview</h3>
                        <p>The dashboard displays key insights such as total tenants, on-time and late payments,
                            and system notifications. It also includes charts for room popularity, tenant registrations,
                            payment status, and maintenance complaints for quick monitoring.</p>
                    </div>


                    <!-- Step 5 -->
                    <div id="step5" class="tutorial-step">
                        <div class="step-icon"><i class="fa-solid fa-route"></i></div>
                        <h3>Page Navigation Patterns</h3>
                        <p>Look for breadcrumbs, back buttons, tabs, filters and pagination on many pages.</p>
                    </div>

                    <!-- Step 6 -->
                    <div id="step6" class="tutorial-step">
                        <div class="step-icon"><i class="fa-solid fa-rocket"></i></div>
                        <h3>Quick Navigation Tips</h3>
                        <p>Use Tab to move fields, bookmark pages, and use the mobile hamburger menu for navigation.</p>
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
            const totalSteps = 6;

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
    <script>
        new Chart(document.getElementById('roomChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($roomLabels); ?>,
                datasets: [{
                    label: 'Contracts',
                    data: <?php echo json_encode($roomData); ?>,
                    backgroundColor: '#42a5f5'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Tenant Registrations (line)
        new Chart(document.getElementById('tenantChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($tenantLabels); ?>,
                datasets: [{
                    label: 'New Tenants',
                    data: <?php echo json_encode($tenantData); ?>,
                    borderColor: '#66bb6a',
                    backgroundColor: '#c8e6c9',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true
            }
        });

        // Payment Status (monthly pie)
        new Chart(document.getElementById('paymentChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($paymentLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($paymentData); ?>,
                    backgroundColor: ['#66bb6a', '#ef5350', '#9e9e9e']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Maintenance Complaints (pie)
        new Chart(document.getElementById('complaintChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($complaintLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($complaintData); ?>,
                    backgroundColor: ['#ffa726', '#42a5f5']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        new Chart(document.getElementById('roomChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($roomLabels); ?>,
                datasets: [{
                    label: 'Contracts',
                    data: <?php echo json_encode($roomData); ?>,
                    backgroundColor: '#42a5f5'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });


        new Chart(document.getElementById('tenantChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($tenantLabels); ?>,
                datasets: [{
                    label: 'New Tenants',
                    data: <?php echo json_encode($tenantData); ?>,
                    borderColor: '#66bb6a',
                    backgroundColor: '#c8e6c9',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true
            }
        });


        new Chart(document.getElementById('paymentChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($paymentLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($paymentData); ?>,
                    backgroundColor: ['#66bb6a', '#ef5350', '#9e9e9e']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });



        new Chart(document.getElementById('complaintChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($complaintLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($complaintData); ?>,
                    backgroundColor: ['#ffa726', '#42a5f5']
                }]
            },
            options: {
                responsive: true
            }
        });
    </script>
    <script src="../js/script.js"></script>
</body>

</html>