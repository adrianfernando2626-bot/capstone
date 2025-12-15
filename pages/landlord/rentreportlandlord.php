<?php

if (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}
if (file_exists('../includes/database.php')) {
    include_once('../includes/database.php');
}

session_start();
$user_id_landlord = $_SESSION['user_id'];
$warning = "";
$stmtlandlord = $pdo->prepare("SELECT tenant_priviledge, payment_priviledge, building_id  FROM userall WHERE user_id = ?");
$stmtlandlord->execute([$user_id_landlord]);
$landlord = $stmtlandlord->fetch(PDO::FETCH_ASSOC);
$disabled_button = "";
if ($landlord['tenant_priviledge'] === 'Not Approved') {
    $disabled_button = "disabled";
    $warning = "It seems you do not have the approval from the Owner to download the payment report";
} else {
    $disabled_button = "";
    $warning = "";
}

$host = "localhost";
$username = "root";
$password = "";
$database = "apartment";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Landlord') {
    header("Location: login.php");
    exit();
}
$filterMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$filterRoom = isset($_GET['room']) ? $_GET['room'] : '';
$currentYear = date('Y');

$where = "WHERE MONTH(p.due_date) = '$filterMonth' AND YEAR(p.due_date) = '$currentYear'";
if (!empty($filterRoom)) {
    $where .= " AND r.room_number = '$filterRoom'";
}

$query = "SELECT pi.first_name, pi.last_name, r.room_number, p.due_date, rp.expected_amount_due, rp.tenant_payment, p.paid_on,
                 CASE
                     WHEN p.paid_on IS NULL THEN 'Unpaid'
                     WHEN p.paid_on <= p.due_date THEN 'On-Time'
                     ELSE 'Late'
                 END AS status
          FROM payment p
          JOIN contract c ON p.contract_id = c.contract_id
           JOIN rent_payment rp ON p.payment_id = rp.payment_id
          JOIN user u ON c.user_id = u.user_id
          JOIN personal_info pi ON u.user_id = pi.user_id
          JOIN room r ON u.room_id = r.room_id
          $where
          ORDER BY p.due_date DESC";

$payments = $conn->query($query);

$onTimeCount = $conn->query("    SELECT COUNT(*) AS count
    FROM payment p 
    JOIN rent_payment rp ON rp.payment_id = p.payment_id
    JOIN payment_status ps ON ps.payment_id = p.payment_id
    WHERE MONTH(p.due_date) = $filterMonth AND YEAR(p.due_date) = $currentYear
    AND p.paid_on IS NOT NULL AND p.paid_on <= p.due_date AND ps.is_active = 1")->fetch_assoc()['count'];

$lateCount = $conn->query("    SELECT COUNT(*) AS count
    FROM payment p 
    JOIN rent_payment rp ON p.payment_id = rp.payment_id
    JOIN payment_status ps ON ps.payment_id = p.payment_id
    WHERE MONTH(p.due_date) = $filterMonth AND YEAR(p.due_date) = $currentYear
    AND p.paid_on IS NOT NULL AND p.paid_on > p.due_date AND ps.is_active = 1")->fetch_assoc()['count'];

$unpaidCount = $conn->query("    SELECT COUNT(*) AS count
    FROM payment p
    JOIN payment_status ps ON ps.payment_id = p.payment_id
    JOIN rent_payment rp ON p.payment_id = rp.payment_id
    WHERE MONTH(p.due_date) = $filterMonth AND YEAR(p.due_date) = $currentYear
    AND p.paid_on IS NULL AND ps.status = 'UNPAID' AND ps.is_active = 1")->fetch_assoc()['count'];

$totalTenants = $conn->query("SELECT COUNT(*) AS count FROM user WHERE role = 'tenant' AND account_status = 'Active'")->fetch_assoc()['count'];

$totalExpected = $conn->query("SELECT SUM(rp.expected_amount_due) AS total 
                            FROM payment p JOIN rent_payment rp ON p.payment_id = rp.payment_id
                            JOIN payment_status ps ON ps.payment_id = p.payment_id
                            WHERE MONTH(p.due_date) = '$filterMonth'
                            AND YEAR(p.due_date) = '$currentYear' AND ps.is_active = 1")->fetch_assoc()['total'] ?? 0;

$totalCollected = $conn->query("SELECT SUM(rp.tenant_payment) AS total 
                                FROM payment p 
                                JOIN rent_payment rp ON p.payment_id = rp.payment_id 
                                JOIN payment_status ps ON ps.payment_id = p.payment_id
                                WHERE p.paid_on IS NOT NULL 
                                AND MONTH(p.due_date) = '$filterMonth' 
                                AND YEAR(p.due_date) = '$currentYear' AND ps.is_active = 1")->fetch_assoc()['total'] ?? 0;

$roomOptions = $conn->query("SELECT DISTINCT room_number FROM room ORDER BY room_number");
$selectedMonth = $filterMonth;
$selectedRoom = $filterRoom;
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent Collection Report</title>
    <link rel="stylesheet" href="../css/styledashowner4.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="main">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 1rem;">
            <h1 class="page-title mb-4" style="font-size: 1.8rem; font-weight: 600; margin: 0;">
                Rent Collection Report -
                <?php echo date('F', mktime(0, 0, 0, $filterMonth, 1)); ?>
                <?php echo $currentYear; ?>
            </h1>

            <button id="helpBtn" title="Help Center" onclick="openHelpModal();"
                style="background-color: #007bff; color: white; border: none; border-radius: 8px;
               padding: 8px 14px; display: flex; align-items: center; gap: 6px; 
               cursor: pointer; font-weight: 500; transition: background-color 0.2s ease;">
                <i class="fas fa-question-circle" style="font-size: 1rem;"></i>
                <span>Help</span>
            </button>
        </div>

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

        <!-- KPI Cards -->
        <div class="cards">
            <div class="card bg-danger-subtle">
                <i class="fas fa-users"></i>
                <h3><?php echo $totalTenants; ?></h3>
                <p>Total Tenants</p>
            </div>
            <div class="card bg-warning-subtle">
                <i class="fas fa-briefcase"></i>
                <h3><?php echo $onTimeCount; ?></h3>
                <p>Payments on Time</p>
            </div>
            <div class="card bg-info-subtle">
                <i class="fas fa-user-lock"></i>
                <h3><?php echo $lateCount; ?></h3>
                <p>Late Payments</p>
            </div>
            <div class="card bg-light-subtle">
                <i class="fas fa-clock"></i>
                <h3><?php echo $unpaidCount; ?></h3>
                <p>Unpaid Tenants</p>
            </div>

            <?php if ($landlord['payment_priviledge'] === 'Approved'): ?>
                <div class="card bg-success-subtle">
                    <i class="fas fa-wallet"></i>
                    <h3>PHP <?php echo number_format($totalCollected, 2); ?></h3>
                    <p>Total Collected Amount</p>
                </div>
                <div class="card bg-warning-subtle">
                    <i class="fas fa-money-bill-wave"></i>
                    <h3>PHP <?php echo number_format($totalExpected, 2); ?></h3>
                    <p>Total Expected Amount</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($warning)): ?>
            <div class="alert alert-warning mt-3"><?php echo htmlspecialchars($warning); ?></div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="filter-section mb-4 d-flex flex-wrap align-items-end gap-3">
            <form method="GET" class="d-flex flex-wrap gap-3">
                <div>
                    <label class="form-label">Filter by Month</label>
                    <select name="month" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo ($filterMonth == str_pad($m, 2, '0', STR_PAD_LEFT)) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">Filter by Room</label>
                    <select name="room" class="form-select">
                        <option value="">All</option>
                        <?php while ($row = $roomOptions->fetch_assoc()): ?>
                            <option value="<?php echo $row['room_number']; ?>" <?php echo ($filterRoom == $row['room_number']) ? 'selected' : ''; ?>>
                                <?php echo $row['room_number']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="align-self-end">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>

        <!-- Chart Container -->
        <div class="chart-container mb-4">
            <canvas id="myChart"></canvas>
            <script src="../buildingowner/script_rent_report.js"></script>
        </div>

        <!-- Tenant Table -->
        <div class="report-content mb-4">
            <div class="tenant-table">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Tenant Name</th>
                                <th>Room</th>
                                <th>Due Date</th>
                                <th>Expected Amount</th>
                                <th>Room Payment</th>
                                <th>Paid On</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $payments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                                    <td><?php echo $row['room_number']; ?></td>
                                    <td><?php echo $row['due_date']; ?></td>
                                    <td>PHP <?php echo number_format($row['expected_amount_due'], 2); ?></td>
                                    <td>PHP <?php echo number_format($row['tenant_payment'], 2); ?></td>
                                    <td><?php echo $row['paid_on'] ?? '—'; ?></td>
                                    <td><span class="badge bg-<?php echo strtolower($row['status']) === 'unpaid' ? 'danger' : (strtolower($row['status']) === 'late' ? 'warning' : 'success'); ?>"><?php echo $row['status']; ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Export Button -->
        <div class="report-buttons mb-5 d-flex justify-content-end">
            <form method="GET" action="export_pdf.php" target="_blank">
                <input type="hidden" name="month" value="<?php echo $filterMonth; ?>">
                <input type="hidden" name="room" value="<?php echo $filterRoom; ?>">
                <input type="hidden" name="totalTenants" value="<?php echo $totalTenants; ?>">
                <input type="hidden" name="onTime" value="<?php echo $onTimeCount; ?>">
                <input type="hidden" name="late" value="<?php echo $lateCount; ?>">
                <input type="hidden" name="unpaid" value="<?php echo $unpaidCount; ?>">
                <input type="hidden" name="expected" value="<?php echo $totalExpected; ?>">
                <input type="hidden" name="collected" value="<?php echo $totalCollected; ?>">
                <button type="submit" class="btn btn-success" <?php echo $disabled_button; ?>>Export as PDF</button>
            </form>
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
                        <div class="step-icon"><i class="fa-solid fa-user-pen"></i></div>
                        <h3>Report Management Page</h3>
                        <div class="step-layout">
                            <div class="step-content">
                                <img src="../images/rent_report_landlord_help.png" alt="Add Building Screenshot" class="step-image">
                                <p>The <strong>Rent Collection Report</strong> page displays a summary of rental payments for the selected month. It shows the total tenants, payments on time, late and unpaid tenants, as well as total collected and expected amounts. Users can filter reports by month or room, view payment details in a table, and export the report as a PDF.</p>
                            </div>
                        </div>


                        <div class="step-layout">
                            <div class="step-content">
                                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                    Step 1: Apply Filter
                                </h4>
                                <img src="../images/arrow_rent_report_landlord_help.png" alt="Add Building Screenshot" class="step-image">
                                <p>The <strong>Apply Filters</strong> button lets users filter the rent collection report by month or room. After selecting the desired filters, clicking this button updates the report, chart, and table to display the relevant rent data.</p>




                            </div>
                        </div>
                    </div>
                </div>


                <!-- Step 2 -->
                <div id="step2" class="tutorial-step">
                    <div class="step-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                    <h3>How to Generate Report</h3>
                    <div class="step-layout">
                        <div class="step-content">
                            <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                Step 1: Click the "Export as PDF" button
                            </h4>
                            <img src="../images/arrow_generate_report_landlord_help.png" alt="Add Building Screenshot" class="step-image">
                            <p>Click the <strong>“Export as PDF”</strong> button to download the rent collection report as a PDF file.</p>
                        </div>
                    </div>


                    <div class="step-highlight">
                        <i class="fa-solid fa-lightbulb"></i>
                        <span>You can access this help anytime by clicking the Help button.</span>
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
</body>

</html>