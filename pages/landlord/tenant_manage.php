<?php
error_log(">>> PHP error log test");
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../tenant/guest_logging_process/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists('includes/database.php')) {
  include_once('includes/database.php');
}
if (file_exists('../includes/database.php')) {
  include_once('../includes/database.php');
}
session_start();
$user_id = $_SESSION['user_id'];

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Landlord') {
  header("Location: ../login.php");
  exit();
}
if (isset($_SESSION['rent'])) {
  echo $_SESSION['rent'];
  unset($_SESSION['rent']);
}
$total_rent = 0;

$warning = "";
$user_id_landlord = $_SESSION['user_id'];
$stmtlandlord = $pdo->prepare("SELECT us.tenant_priviledge, us.building_id, b.name, b.number_of_floors FROM userall us JOIN building b ON b.building_id = us.building_id WHERE us.user_id = ?");
$stmtlandlord->execute([$user_id_landlord]);
$landlord = $stmtlandlord->fetch(PDO::FETCH_ASSOC);
$disabled_button = "";
if ($landlord['tenant_priviledge'] === 'Not Approved') {
  $disabled_button = "disabled";
  $warning = "It seems you do not have the approval from the Owner to download the payment report";
} else {
  $disabled_button = "";
  $warning = "";
} // Handle payment history request
$building_name = $landlord['name'];
$number_of_floors = $landlord['number_of_floors'];
$showUpdateAlert = false;
if (isset($_SESSION['update_success'])) {
  $showUpdateAlert = true;
  unset($_SESSION['update_success']);
}

$host = "localhost";
$username = "root";
$password = "";
$database = "apartment";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$sql = 'SELECT * FROM userall WHERE user_id = ' . $user_id;
$rs = mysqli_query($db_connection, $sql);
$rw = mysqli_fetch_array($rs);
// set floor number
$floor_number = 1;
if (isset($_GET['number_of_floors'])) {
  $_SESSION['number_of_floors'] = $_GET['number_of_floors'] ?? '';
}
$number_of_floors_get = $_SESSION['number_of_floors'] ?? '';
if (!empty($number_of_floors_get)) {
  $floor_number = $number_of_floors_get;
  $_SESSION['number_of_floors'] = $_GET['number_of_floors'] ?? 0;
}
$floor_number_equals = 'AND r.floor_number = ' . $floor_number;

if (!empty($number_of_floors_get)) {
  if ($number_of_floors_get === 'All') {
    $floor_number_equals = "";
  }
}
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$building_id = $landlord['building_id'];
$_SESSION['building_id'] = $building_id;

$query = "SELECT 
  u.user_id,
    u.account_status,
    pi.first_name,
    pi.last_name,
    pi.phone_number,
    pi.email,
    pi.img,
    CONCAT(pi.first_name, ' ', pi.last_name, ' ', pi.suffix) AS tenant_name,
    r.room_id,
    r.room_number,
    r.capacity,
    c.contract_id,
    c.start_date,
    c.end_date,
    c.contract_status AS contract_status,
    p.payment_id,
    p.due_date,
    rp.expected_amount_due,
    rp.tenant_payment,
    ps.status
FROM user u
JOIN personal_info pi ON u.user_id = pi.user_id 
JOIN room r ON u.room_id = r.room_id  
JOIN contract c ON u.user_id = c.user_id
 JOIN payment p ON p.contract_id = c.contract_id
JOIN rent_payment rp ON rp.payment_id = p.payment_id
JOIN payment_status ps ON ps.payment_id = p.payment_id
WHERE LOWER(u.role) = 'tenant' 
  $floor_number_equals
  AND u.account_status = 'Active' 
  AND r.building_id = $building_id
  AND ps.is_active = 1 
  AND MONTH(p.due_date) = MONTH(CURDATE()) 
  AND YEAR(p.due_date) = YEAR(CURDATE())
";

// Inject search if available
if (!empty($search)) {
  $search_safe = $conn->real_escape_string($search);
  $query .= " AND (
        pi.first_name LIKE '%$search_safe%' OR
        pi.last_name LIKE '%$search_safe%' OR
        CONCAT(pi.first_name, ' ', pi.last_name) LIKE '%$search_safe%' OR
        pi.phone_number LIKE '%$search_safe%' OR
        r.room_number LIKE '%$search_safe%'
    )";
}

$query .= " ORDER BY p.due_date DESC";


$result = $conn->query($query);
$tenants = [];
while ($row = $result->fetch_assoc()) {
  $status = strtolower($row['account_status']) === 'active' ? '<span class="badge bg-light text-success"><i class="fas fa-circle text-success me-1"></i> Active</span>' : '<span class="badge bg-light text-danger"><i class="fas fa-circle text-danger me-1"></i> Inactive</span>';
  $payment_status = '<span class="badge bg-warning text-dark">Due</span>';
  if (isset($row['status'])) {
    switch ($row['status']) {
      case 'PAID':
        $payment_status = '<span class="badge bg-success text-light">Paid</span>';
        break;
      case 'UNPAID':
        $payment_status = '<span class="badge bg-warning text-light">Unpaid</span>';
        break;
      case 'LATE':
        $payment_status = '<span class="badge bg-danger text-light">Late</span>';
        break;
      case 'LAST':
        $payment_status = '<span class="badge bg-primary text-light">Last Due</span>';
        break;
      default:
        $payment_status = '<span class="badge bg-secondary text-light">Unknown</span>';
    }
  }

  $tenants[] = [
    'id' => $row['user_id'],
    'tenant_name' => $row['tenant_name'],
    'capacity' => $row['capacity'],
    'room_id' => $row['room_id'],
    'room' => $row['room_number'],
    'contact' => $row['phone_number'],
    'movein' => $row['start_date'],
    'lease' => $row['end_date'],
    'due_date' => $row['due_date'],
    'expected_rent' => $row['expected_amount_due'],
    'rent' => $row['tenant_payment'],
    'status_literal' => $row['status'],
    'status' => $status,
    'status_raw' => strtolower($row['account_status']),
    'payment' => $payment_status,
    'payment_raw' => strtolower(strip_tags($payment_status)),
    'disable_button_for_submit' => $row['status'],
    'email' => $row['email'],
    'img' => $row['img']
  ];
}
if (!$result) {
  die("SQL Error: " . $conn->error);
}

function getOrdinalSuffix($n)
{
  // If the input is the string "All", handle it separately
  if ($n === "All") {
    return $n;
  }

  // Handle numeric values with ordinal suffixes
  if (!in_array(($n % 100), array(11, 12, 13))) {
    switch ($n % 10) {
      case 1:
        return $n . 'st';
      case 2:
        return $n . 'nd';
      case 3:
        return $n . 'rd';
    }
  }
  return $n . 'th';
}



?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Room Management</title>
  <link rel="stylesheet" href="../css/styledashowner4.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <style>
    .custom-modal {
      max-width: 1000px;
    }
  </style>

  <script>
    function confirmPermanentDelete() {
      const deleteBtn = document.getElementById('deletePermanent');
      if (deleteBtn.disabled) {
        return; // Prevent SweetAlert if button is disabled
      }
      Swal.fire({
        icon: 'warning',
        title: 'Delete Account',
        text: 'Are you sure you want to delete all the data to this Payments, this will not be reverted?',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = 'tenant_CRUD/delete_permanent_payment.php';
        }
      });
    }



    function confirmMarkasRead() {
      const markasreadform = document.getElementById('markasreadform');

      markasreadform
      Swal.fire({
        icon: 'warning',
        title: 'Mark as Read',
        text: 'Are you sure you want to mark as the selected maintenance request?',
        showCancelButton: true,
        confirmButtonText: 'Yes, mark it!',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          markasreadform.submit();
        }
      });
    }
  </script>
  <script>
    function gotoscan() {
      window.location.href = 'scan_guest_log/scan_guest.php';
    }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const tenantRequestModal = document.getElementById("tenantRequestModal");

      if (tenantRequestModal) {
        tenantRequestModal.addEventListener("shown.bs.modal", function() {
          const selectAllCheckbox = document.getElementById("selectAllCheckboxRequest");
          const checkboxes = document.querySelectorAll(".tenant-checkbox");
          const markAsReadBtn = document.getElementById("markAsReadBtn");

          if (!selectAllCheckbox || !markAsReadBtn) return; // Prevent null errors

          // Function to update button state
          function updateButtonState() {
            const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
            markAsReadBtn.disabled = !anyChecked;
          }

          // Select all checkbox
          selectAllCheckbox.addEventListener("change", function() {
            checkboxes.forEach(cb => (cb.checked = selectAllCheckbox.checked));
            updateButtonState();
          });

          // Individual checkboxes
          checkboxes.forEach((checkbox) => {
            checkbox.addEventListener("change", function() {
              if (!this.checked) {
                selectAllCheckbox.checked = false;
              } else if (Array.from(checkboxes).every((cb) => cb.checked)) {
                selectAllCheckbox.checked = true;
              }
              updateButtonState();
            });
          });

          // Initialize state
          updateButtonState();
        });
      }
    });
  </script>


</head>
<a href="tenant_CRUD/edit_room_utilities.php?id="></a>

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

  <main class="main p-4">
    <div class="topbar">
      <div>
        <h1>
          <span>Room</span><br>
          <span>Management</span>
        </h1>
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
              <span class="visually-hidden">unread messages</span>
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
                    <?php if ($notification['notif_status'] === 'unread'): ?>
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
              const badge = document.querySelector('.badge.bg-danger');

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
          <img class="rounded-circle" width="150px" src="../images/<?php echo $rw['img']; ?>">
        </div>
      </div>
    </div>


    <div class="d-flex gap-2 mb-3">

      <button class="btn-primary" data-bs-toggle="modal" data-bs-target="#deletedUsersModal">Logs</button>
      <div class="position-relative">
        <button class="btn-primary" data-bs-toggle="modal" data-bs-target="#tenantRequestModal">Requests</button>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
          <?php
          $sql123 = 'SELECT COUNT(*) as total_request 
                            FROM maintenance_request mr 
                            JOIN status_request sr 
                            ON mr.maintenance_request_id = sr.maintenance_request_id
                            WHERE sr.update_message = "Pending"';
          $rs123  = mysqli_query($db_connection, $sql123);
          $rw123 = mysqli_fetch_array($rs123);
          ?>
          <?php echo $rw123['total_request']; ?>
          <span class=" visually-hidden">pending request</span>
        </span>
      </div>

      <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paymenttableModal">
        Payment
      </button>
    </div>

    <div class="row">
      <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-center">
        <h4 class="mb-3 mb-md-0"><?php echo $building_name; ?> <strong><?php echo "(" . getOrdinalSuffix($floor_number); ?><?php echo ($floor_number === "All" ? ' floors)' : ' floor)'); ?></strong></h4>
        <div class="d-flex flex-column flex-md-row justify-content-end align-items-center gap-2">
          <form method="GET" action="tenant_manage.php" class="d-flex">
            <div class="input-group input-group-sm">
              <input type="text" name="search" placeholder="Search.." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" class="form-control">
              <button type="submit" class="btn btn-primary btn-sm">Search</button>
            </div>
          </form>

          <form method="GET" action="tenant_manage.php" class="d-flex">
            <div class="input-group input-group-sm">
              <select name="number_of_floors" id="number_of_floors" required class="form-select">
                <option value="" disabled selected> --- Select Floor ---</option>
                <?php
                for ($i = $number_of_floors; $i >= 1; $i--) {
                  echo "<option value='$i'>$i</option>";
                }
                ?>
                <option value="All">All Rooms</option>
              </select>
              <button type="filter" class="btn btn-primary btn-sm">Filter</button>
            </div>
          </form>
        </div>
      </div>
      <?php
      $room_groups = [];
      $room_modals = "";
      $individual_modal_msg = '';


      // Group tenants by room_id (assuming $t['room_id'] exists)
      foreach ($tenants as $t) {
        $room_groups[$t['room_id']]['room_number'] = $t['room'];
        $room_groups[$t['room_id']]['tenants'][] = $t;
      }

      foreach ($room_groups as $room_id => $roomData):
        $total_rent = 0;
        $total_expected_rent = 0;
      ?>
        <div class="col-md-6 mb-4">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
              <h5 class="card-title mb-3">Room <?= $roomData['room_number'] ?></h5>

              <h6 class="text-muted">Tenants:</h6>
              <ul class="list-unstyled" style="letter-spacing: normal;">
                <div class="row g-3">
                  <?php

                  foreach ($roomData['tenants'] as $tenant):
                    $total_rent += $tenant['rent'];
                    $total_expected_rent += $tenant['expected_rent'];
                  ?>
                    <div class="col-md-6">
                      <li class="d-flex flex-column align-items-center text-center p-3 border rounded shadow-sm">
                        <img src="../images/<?= $tenant['img'] ?>"
                          class="rounded-circle mb-2"
                          width="80" height="80"
                          style="object-fit: cover;">
                        <div style="line-height: 1.1;">
                          <strong><?= $tenant['tenant_name'] ?></strong><br>

                        </div>
                        <button class="btn btn-sm btn-outline-success mt-2"
                          data-bs-toggle="modal"
                          title="Send Notification Tenant"
                          data-bs-target="#sendMessageModalIndividual<?= $tenant['id'] ?>">🔔</button>
                      </li>
                    </div>

                  <?php
                    // Append modal HTML for each tenant
                    $individual_modal_msg .= '
                                          <div class="modal fade" id="sendMessageModalIndividual' . $tenant['id'] . '" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered modal-md">
                                              <div class="modal-content">
                                                <div class="modal-header border-0">
                                                  <h5 class="modal-title">Send Message - to ' . $tenant['tenant_name'] . '</h5>
                                                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="notification/send_single_notification.php" method="POST">
                                                  <input type="hidden" name="user_id" value="' . $tenant['id'] . '">
                                                  <div class="modal-body">
                                                    <label class="mt-2">Title</label>
                                                    <input type="text" name="notif_title" class="form-control" placeholder="Add Subject">
                                                    <label class="mt-2">Message</label>
                                                    <textarea name="notif_text" class="form-control" placeholder="Add message"></textarea>
                                                  </div>
                                                  <div class="modal-footer border-0">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Send</button>
                                                  </div>
                                                </form>
                                              </div>
                                            </div>
                                          </div>';
                  endforeach;

                  ?>
                  <!-- Show totals for this room -->
                </div>
              </ul>

              <div class="d-flex justify-content-end gap-2 mt -3">
                <button class="btn btn-sm btn-outline-success" title="Send Notification Room" data-bs-toggle="modal" data-bs-target="#sendMessageModalRoom<?= $room_id ?>">🔔</button>

              </div>
            </div>
          </div>
        </div>
      <?php
        $room_modals = '<div class="modal fade" id="sendMessageModalRoom' . $room_id . '" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-md">
                        <div class="modal-content">
                          <div class="modal-header border-0">
                            <h5 class="modal-title">Send Message - Room ' . $roomData['room_number'] . '</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <form action="notification/send_single_notification.php" method="POST">
                            <input type="hidden" name="room_id" value="' . $room_id . '">
                            <div class="modal-body">
                              <label class="mt-2">Title</label>
                              <input type="text" name="notif_title" class="form-control" placeholder="Add Subject">
                              <label class="mt-2">Message</label>
                              <textarea name="notif_text" class="form-control" placeholder="Add message"></textarea>
                            </div>
                            <div class="modal-footer border-0">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn btn-primary">Send</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>';

        echo $room_modals;
        echo $individual_modal_msg;
      endforeach;



      ?>

    </div>

  </main>


  <div class="modal fade" id="paymenttableModal" tabindex="-1" aria-labelledby="paymenttableModal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header border-bottom border-2">
          <h4 class="modal-title" id="paymentarchivedModalLabel"><strong>Edit Payment</strong></h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <!-- Action Buttons -->
          <div class="d-flex flex-wrap gap-2 mb-4">
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#paymentarchivedModal"><i class="fas fa-trash-alt me-2"></i>Archived </button>
          </div>
          <form method="GET" class="mb-3">
            <input type="hidden" name="building_id" value="<?php echo htmlspecialchars($building_id); ?>">
            <div class="input-group">
              <input type="text" name="search_payment" class="form-control"
                placeholder="Search..."
                value="<?php echo isset($_GET['search_payment']) ? htmlspecialchars($_GET['search_payment']) : ''; ?>">
              <button type="submit" class="btn btn-primary">Search</button>
            </div>
          </form>
          <!-- Payment Table -->
          <div class="table-responsive">
            <table class="table table-bordered align-middle table-hover">
              <thead class="table-primary text-center">
                <tr>
                  <th>Room</th>
                  <th>Payment Date</th>
                  <th>Due Date</th>
                  <th>Rent Amount</th>
                  <th>Payment</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody class="text-center">
                <?php
                if ($landlord['tenant_priviledge'] === 'Not Approved'):
                  echo '<tr><td colspan="5">No records available</td></tr>';
                else:
                  $search = isset($_GET['search_payment']) ? trim($_GET['search_payment']) : '';

                  $sql = "SELECT 
                              r.room_id,
                              r.room_number,
                              c.start_date,
                              c.end_date,
                              p.paid_on,
                              p.due_date,
                              SUM(rp.expected_amount_due) AS expected_amount_due,
                              SUM(rp.tenant_payment) AS tenant_payment,
                              ps.status
                          FROM userall u
                          JOIN room r ON u.room_id = r.room_id  
                          JOIN contract c ON u.user_id = c.user_id
                          JOIN payment p ON p.contract_id = c.contract_id
                          JOIN rent_payment rp ON rp.payment_id = p.payment_id
                          JOIN payment_status ps ON ps.payment_id = p.payment_id
                          WHERE LOWER(u.role) = 'tenant' 
                            AND u.account_status = 'Active' 
                            AND r.building_id = $building_id
                            AND ps.is_active = 1 
                            AND MONTH(p.due_date) = MONTH(CURDATE()) 
                            AND YEAR(p.due_date) = YEAR(CURDATE())
                            
                          ";

                  if (!empty($search)) {
                    $search_escaped = mysqli_real_escape_string($db_connection, $search);
                    $sql .= " AND (
                            r.room_number LIKE '%$search_escaped%' 
                            OR p.paid_on LIKE '%$search_escaped%' 
                            OR p.due_date LIKE '%$search_escaped%'
                            OR rp.expected_amount_due LIKE '%$search_escaped%'
                            OR rp.tenant_payment LIKE '%$search_escaped%'
                            OR ps.status LIKE '%$search_escaped%'
                          )";
                  }

                  $sql .= " GROUP BY u.room_id
                            ORDER BY r.room_status DESC";
                  $result_query = mysqli_query($db_connection, $sql);

                  if (!$result_query) {
                    die("SQL Error: " . mysqli_error($db_connection) . "<br>Query: " . $sql);
                  }
                  $room_modals_payment = "";


                  while ($result = mysqli_fetch_array($result_query)):
                    $room_id_payment_table = $result['room_id'];
                    $status_color = match ($result['status']) {
                      'PAID'   => 'success',
                      'UNPAID' => 'warning',
                      'LAST'   => 'primary',
                      'LATE'   => 'danger',
                      default  => 'secondary',
                    };
                    echo '<tr>
                          <td>' . htmlspecialchars($result['room_number']) . '</td>
                      <td>' . ($result['paid_on'] ? date("F j, Y", strtotime($result['paid_on'])) : 'Not Available') . '</td>
                          <td>' . ($result['due_date'] ? date("F j, Y", strtotime($result['due_date'])) : 'Not Available') . '</td>
                          <td>' . htmlspecialchars($result['expected_amount_due']) . '</td>
                          <td>' . htmlspecialchars($result['tenant_payment']) . '</td>
                          <td>
                              <span class="badge bg-light text-' . $status_color . '">
                                <i class="fas fa-circle text-' . $status_color . ' me-1"></i>' . $result['status'] . '
                              </span>
                          </td>
                          ';
                    $showbutton = "";
                    if ($result['status'] === "PAID" || $result['status'] === "LATE") {
                      $showbutton = '<button class="btn btn-sm btn-outline-primary" title="Open Advance Payment" onClick="confirmAdvancePayment(' . $room_id_payment_table . ')">Add Advance Payment 🏠</button>';
                    } elseif ($result['status'] === "UNPAID") {
                      $showbutton = '<button class="btn btn-sm btn-outline-primary" title="Mark Rent Payment" data-bs-toggle="modal" data-bs-target="#editTenantModal' . $room_id_payment_table . '">Mark Rent Payment 🏠</button>';
                    } else {
                      $showbutton = '<button class="btn btn-sm btn-outline-primary" disabled>Error 🏠</button>';
                    }
                    echo ' <td><button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" title="View Payments History" data-bs-target="#viewPaymentModal' . $room_id_payment_table . '">Payment History 🧾</button>
                    ' . $showbutton . '
                      <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" title="View Utility Payments" data-bs-target="#editutilityModal' . $room_id_payment_table . '">Mark Utility Payment💡</button>
                    </td>
                    </tr>';
                    $old = $_SESSION['old_input'][$room_id_payment_table] ?? [];
                    $stmtlastdue = $pdo->prepare("SELECT MAX(p.due_date) AS latest_due_date
                                      FROM payment p
                                      JOIN contract c ON c.contract_id = p.contract_id
                                      JOIN userall us ON us.user_id = c.user_id
                                      WHERE us.room_id = ?
                                      GROUP BY us.room_id");
                    $stmtlastdue->execute([$room_id_payment_table]);
                    $showMonth = $stmtlastdue->fetch(PDO::FETCH_ASSOC);
                    $next_due = date("F Y", strtotime($showMonth['latest_due_date']));
                    $room_modals_payment .= '
                    <div class="modal fade" id="modalAdvancePayment' . $room_id_payment_table . '" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-md">
                            <div class="modal-content">
                                <div class="modal-header border-0">
                                    <h5 class="modal-title"><strong>Advance Payment</strong> - Room ' . htmlspecialchars($result['room_number']) . ' for (' . htmlspecialchars($next_due) . ') </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form action="tenant_CRUD/edit_tenant_management.php" method="POST">
                                    <input type="hidden" name="room_id_advance" value="' . htmlspecialchars($room_id_payment_table) . '">
                                    <div class="modal-body">
                                        <div class="mb-2">
                                            <label>Date of Contract Started:</label>
                                            <input type="date" name="start_date" class="form-control" id="start_date" value="' . htmlspecialchars($result['start_date']) . '" readonly> 
                                        </div>
                                        <div class="mb-2">
                                            <label>Date of Contract Expiration:</label>
                                            <input type="date" name="end_date" class="form-control" id="end_date" value="' . htmlspecialchars($result['end_date']) . '" readonly> 
                                        </div>
                                        <div class="mb-2">
                                            <label class="mt-2">Advance Payment Amount:</label>
                                            <input type="text" name="advance_payment" class="form-control" placeholder="Enter Amount" value="' . htmlspecialchars($_SESSION["form_data"][$room_id_payment_table]["advance_payment"] ?? "") . '" required>
                                        </div>
                                        <div class="mb-2">
                                            <label>Date of Advance Payment:</label>
                                            <input type="datetime-local" name="advance_paid_on" class="form-control" value="' . htmlspecialchars($_SESSION["form_data"][$room_id_payment_table]["advance_paid_on"] ?? "") . '" required>
                                        </div>
                                        <div class="mb-2">
                                            <label>Payment Method:</label>
                                            <select name="payment_method" class="form-select" required>
                                                <option value="" disabled selected>-- Select Method --</option>
                                                <option value="Cash" ' . (isset($_SESSION["form_data"][$room_id_payment_table]["payment_method"]) && $_SESSION["form_data"][$room_id_payment_table]["payment_method"] == "Cash" ? "selected" : "") . '>Cash</option>
                                                <option value="GCASH" ' . (isset($_SESSION["form_data"][$room_id_payment_table]["payment_method"]) && $_SESSION["form_data"][$room_id_payment_table]["payment_method"] == "GCASH" ? "selected" : "") . '>GCash</option>
                                                <option value="Bank Transfer" ' . (isset($_SESSION["form_data"][$room_id_payment_table]["payment_method"]) && $_SESSION["form_data"][$room_id_payment_table]["payment_method"] == "Bank Transfer" ? "selected" : "") . '>Bank Transfer</option>
                                                <option value="Other" ' . (isset($_SESSION["form_data"][$room_id_payment_table]["payment_method"]) && $_SESSION["form_data"][$room_id_payment_table]["payment_method"] == "Other" ? "selected" : "") . '>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-0">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Add Payment</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';


                    $room_modals_payment .= '
                    <div class="modal fade" id="editutilityModal' . $room_id_payment_table . '" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-md">
                            <div class="modal-content">
                                <form action="tenant_CRUD/edit_tenant_management.php" method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Utility Payment - Room ' . htmlspecialchars($result['room_number']) . '</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="room_id_utility" value="' . htmlspecialchars($room_id_payment_table) . '">
                                        <div class="mb-2">
                                            <label>Date of Contract Started:</label>
                                            <input type="date" name="start_date" class="form-control" id="start_date" value="' . htmlspecialchars($result['start_date']) . '" readonly> 
                                        </div>
                                        <div class="mb-2">
                                            <label>Date of Contract Expiration:</label>
                                            <input type="date" name="end_date" class="form-control" id="end_date" value="' . htmlspecialchars($result['end_date']) . '" readonly> 
                                        </div>
                                        <div class="mb-2">
                                            <label>Due Date :</label>
                                            <input type="date" id="utility_due_date" name="utility_due_date" class="form-control" required value="' . htmlspecialchars($_SESSION["form_data"][$room_id_payment_table]["utility_due_date"] ?? "") . '">
                                        </div>
                                        <div class="mb-2">
                                            <label>Utility Type:</label>
                                            <select name="utility_type_id" class="form-select" required>
                                                <option value="" disabled selected>-- Select Utility Type --</option>';
                    // No change needed here for the loop
                    $optionsql = "SELECT * from utility_type";
                    $rs12345 = mysqli_query($db_connection, $optionsql);
                    while ($rw12345 = mysqli_fetch_array($rs12345)) {
                      $room_modals_payment .= '
                                                    <option value="' . htmlspecialchars($rw12345['utility_type_id']) . '" ' . (isset($_SESSION["form_data"][$room_id_payment_table]["utility_type_id"]) && $_SESSION["form_data"][$room_id_payment_table]["utility_type_id"] == "utility_type_id" ? "selected" : "") . '>' . htmlspecialchars($rw12345['utility_name']) . '</option>';
                    }
                    $room_modals_payment .= '
                                            </select>
                                        </div>  
                                        <div class="mb-2">
                                            <label>Amount :</label>
                                            <input type="number" step="0.01" name="amount" class="form-control" value="' . htmlspecialchars($_SESSION["form_data"][$room_id_payment_table]["amount"] ?? "") . '" required>
                                        </div>
                                        <div class="mb-2">
                                            <label>Mark as Paid (Utility):</label>
                                            <input type="datetime-local" id="utility_paid_on" name="utility_paid_on" class="form-control" required value="' . htmlspecialchars($_SESSION["form_data"][$room_id_payment_table]["utility_paid_on"] ?? "") . '">
                                        </div>
                                        <div class="mb-2">
                                            <label>Payment Method:</label>
                                            <select name="payment_method_utility" class="form-select" required>
                                                <option value="" disabled selected>-- Select Method --</option>
                                                <option value="Cash" ' . (isset($_SESSION["form_data"][$room_id_payment_table]["payment_method_utility"]) && $_SESSION["form_data"][$room_id_payment_table]["payment_method_utility"] == "Cash" ? "selected" : "") . '>Cash</option>
                                                <option value="GCASH" ' . (isset($_SESSION["form_data"][$room_id_payment_table]["payment_method_utility"]) && $_SESSION["form_data"][$room_id_payment_table]["payment_method_utility"] == "GCASH" ? "selected" : "") . '>GCash</option>
                                                <option value="Bank Transfer" ' . (isset($_SESSION["form_data"][$room_id_payment_table]["payment_method_utility"]) && $_SESSION["form_data"][$room_id_payment_table]["payment_method_utility"] == "Bank Transfer" ? "selected" : "") . '>Bank Transfer</option>
                                                <option value="Other" ' . (isset($_SESSION["form_data"][$room_id_payment_table]["payment_method_utility"]) && $_SESSION["form_data"][$room_id_payment_table]["payment_method_utility"] == "Other" ? "selected" : "") . '>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Submit</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>';


                    $room_modals_payment .= '
                    <div class="modal fade" id="editTenantModal' . $room_id_payment_table . '" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-md">
                            <div class="modal-content">
                                <form action="tenant_CRUD/edit_tenant_management.php" method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Payment - Room ' . htmlspecialchars($result['room_number']) . '</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="room_id" value="' . htmlspecialchars($room_id_payment_table) . '">
                                        <div class="mb-2">
                                            <label>Due Date:</label>
                                            <input type="date" name="due_date" class="form-control" id="due_date" value="' . htmlspecialchars($result['due_date']) . '" readonly> 
                                        </div>
                                        <div class="mb-2">
                                            <label>Date of Contract Started:</label>
                                            <input type="date" name="start_date" class="form-control" id="start_date" value="' . htmlspecialchars($result['start_date']) . '" readonly> 
                                        </div>
                                        <div class="mb-2">
                                            <label>Date of Contract Expiration:</label>
                                            <input type="date" name="end_date" class="form-control" id="end_date" value="' . htmlspecialchars($result['end_date']) . '" readonly> 
                                        </div>
                                        <div class="mb-2">
                                            <label>Amount Due:</label>
                                            <input type="number" step="0.01" name="expected_amount_due" class="form-control" value="' . htmlspecialchars($result['expected_amount_due']) . '" readonly>
                                        </div>
                                        <div class="mb-2">
                                            <label>Mark as Paid (Date):</label>
                                            <input type="datetime-local" id="paid_on" name="paid_on" class="form-control" required value="' . htmlspecialchars($_SESSION["form_data"][$room_id_payment_table]["paid_on"] ?? "") . '">
                                        </div>
                                        <div class="mb-2">
                                            <label>Payment Method:</label>
                                            <select name="payment_method" class="form-select" required>
                                                <option value="" disabled selected>-- Select Method --</option>
                                                <option value="Cash" ' . (isset($_SESSION["form_data"][$room_id_payment_table]["payment_method"]) && $_SESSION["form_data"][$room_id_payment_table]["payment_method"] == "Cash" ? "selected" : "") . '>Cash</option>
                                                <option value="GCASH" ' . (isset($_SESSION["form_data"][$room_id_payment_table]["payment_method"]) && $_SESSION["form_data"][$room_id_payment_table]["payment_method"] == "GCASH" ? "selected" : "") . '>GCash</option>
                                                <option value="Bank Transfer" ' . (isset($_SESSION["form_data"][$room_id_payment_table]["payment_method"]) && $_SESSION["form_data"][$room_id_payment_table]["payment_method"] == "Bank Transfer" ? "selected" : "") . '>Bank Transfer</option>
                                                <option value="Other" ' . (isset($_SESSION["form_data"][$room_id_payment_table]["payment_method"]) && $_SESSION["form_data"][$room_id_payment_table]["payment_method"] == "Other" ? "selected" : "") . '>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Submit</button>
                                    </div>  
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    
              <div class="modal fade" id="viewPaymentModal' . $room_id_payment_table . '" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl custom-modal">
                  <div class="modal-content">
                    
                    <div class="modal-header border-0">
                      <h5 class="modal-title">Payment History - Room ' . htmlspecialchars($result['room_number']) . '</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                      <!-- First Toggle Buttons -->
                      <div class="btn-group w-100 mb-3" role="group">
                        <button class="btn btn-primary w-50" id="btnRoom' . $room_id_payment_table . '" onclick="showRoomTable(' . $room_id_payment_table . ')">
                          Room Payment
                        </button>
                        <button class="btn btn-outline-primary w-50" id="btnUtility' . $room_id_payment_table . '" onclick="showUtilitySection(' . $room_id_payment_table . ')">
                          Utility Payment
                        </button>
                      </div>

                      <!-- ROOM PAYMENT TABLE -->
                      <div id="roomTable' . $room_id_payment_table . '">
                        <div class="table-responsive">
                          <table class="table table-striped table-hover align-middle">
                            <thead class="table-primary">
                              <tr>
                                <th>Payment Date</th>
                                <th>Due Date</th>
                                <th>Expected Rent Amount</th>
                                <th>Room Payment</th>
                                <th>Status</th>
                              </tr>
                            </thead>
                            <tbody>';

                    $res = $conn->query("SELECT 
                                      p.payment_id, 
                                      p.paid_on, 
                                      p.due_date, 
                                      SUM(rp.expected_amount_due) AS total_expected_rent, 
                                      SUM(rp.tenant_payment) AS total_rent,
                                      ps.status
                                    FROM payment p
                                    JOIN payment_status ps ON p.payment_id = ps.payment_id
                                    JOIN rent_payment rp ON rp.payment_id = p.payment_id
                                    JOIN contract c ON p.contract_id = c.contract_id
                                    JOIN userall us ON us.user_id = c.user_id
                                    WHERE us.room_id = $room_id_payment_table
                                    AND ps.is_active = 1
                                    GROUP BY p.due_date
                                    ORDER BY p.due_date DESC");

                    if ($res->num_rows > 0) {
                      while ($row = $res->fetch_assoc()) {
                        $status_color = match ($row['status']) {
                          'PAID'   => 'success',
                          'UNPAID' => 'warning',
                          'LAST'   => 'primary',
                          'LATE'   => 'danger',
                          default  => 'secondary',
                        };
                        $room_modals_payment .= '
                    <tr>
                      <td>' . ($row['paid_on'] ? date("F j, Y", strtotime($row['paid_on'])) : 'Not Available') . '</td>
                      <td>' . date("F j, Y", strtotime($row['due_date'])) . '</td>
                      <td>₱' . number_format($row['total_expected_rent'], 2) . '</td>
                      <td>₱' . number_format($row['total_rent'], 2) . '</td>
                      <td>
                        <span class="badge bg-light text-' . $status_color . '">
                          <i class="fas fa-circle text-' . $status_color . ' me-1"></i>' . $row['status'] . '
                        </span>
                      </td>
                    </tr>';
                      }
                    } else {
                      $room_modals_payment .= '<tr><td colspan="5" class="text-center text-muted">No room payment history found</td></tr>';
                    }

                    $room_modals_payment .= '
                            </tbody>
                          </table>
                        </div>
                      </div>

                      <!-- UTILITY PAYMENT SECTION -->
                      <div id="utilitySection' . $room_id_payment_table . '" style="display:none;">';

                    $resTypes = $conn->query("SELECT DISTINCT ut.utility_type_id, uttype.utility_name 
                                        FROM utility_payment ut
                                        JOIN utility_type uttype ON uttype.utility_type_id = ut.utility_type_id
                                        JOIN payment p ON p.payment_id = ut.payment_id
                                        JOIN contract c ON p.contract_id = c.contract_id
                                        JOIN userall us ON us.user_id = c.user_id
                                        WHERE us.room_id = $room_id_payment_table");

                    if ($resTypes->num_rows > 0) {
                      // Utility type buttons
                      $room_modals_payment .= '<div class="btn-group w-100 mb-3" role="group">';
                      while ($type = $resTypes->fetch_assoc()) {
                        $room_modals_payment .= '
                    <button class="btn btn-outline-success w-25" id="btnUtil' . $room_id_payment_table . '_' . $type['utility_type_id'] . '" 
                      onclick="showUtilityTable(' . $room_id_payment_table . ', ' . $type['utility_type_id'] . ')">
                      ' . $type['utility_name'] . '
                    </button>';
                      }
                      $room_modals_payment .= '</div>';

                      // Utility tables
                      $resTypes->data_seek(0); // reset pointer
                      while ($type = $resTypes->fetch_assoc()) {
                        $room_modals_payment .= '
                    <div id="utilityTable' . $room_id_payment_table . '_' . $type['utility_type_id'] . '" style="display:none;">
                      <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                          <thead class="table-success">
                            <tr>
                              <th>Amount</th>
                              <th>Date of Payment</th>
                              <th>Utility Due Date</th>
                              <th>Status</th>
                              <th>Payment Method</th>
                              <th>Action</th>
                            </tr>
                          </thead>
                          <tbody>';

                        $res2 = $conn->query("SELECT    
                                          ut.utility_payment_id,
                                          uttype.utility_name,
                                          SUM(ut.amount) AS amount,
                                          p.paid_on,
                                          ps.status,
                                          p.due_date,
                                          ut.utility_type_id,
                                          p.payment_method
                                        FROM utility_payment ut
                                        JOIN utility_type uttype ON uttype.utility_type_id = ut.utility_type_id
                                        JOIN payment p ON p.payment_id = ut.payment_id
                                        JOIN payment_status ps ON p.payment_id = ps.payment_id                               
                                        JOIN contract c ON p.contract_id = c.contract_id
                                        JOIN userall us ON us.user_id = c.user_id
                                        WHERE us.room_id = $room_id_payment_table
                                        AND ut.utility_type_id = " . $type['utility_type_id'] . "
                                        GROUP BY p.due_date 
                                        ORDER BY p.due_date DESC");

                        if ($res2->num_rows > 0) {
                          while ($row2 = $res2->fetch_assoc()) {
                            $util_color = match ($row2['status']) {
                              'PAID'   => 'success',
                              'UNPAID' => 'warning',
                              'LAST'   => 'primary',
                              'LATE'   => 'danger',
                              default  => 'secondary',
                            };
                            $room_modals_payment .= '
                        <tr>
                          <td>₱' . number_format($row2['amount'], 2) . '</td>
                          <td>' . date("F j, Y", strtotime($row2['paid_on'])) . '</td>
                          <td>' . date("F j, Y", strtotime($row2['due_date'])) . '</td>
                          <td>
                            <span class="badge bg-light text-' . $util_color . '">
                              <i class="fas fa-circle text-' . $util_color . ' me-1"></i>' . ($row2['status'] ?: 'Not Available') . '
                            </span>
                          </td>
                          <td>' . $row2['payment_method'] . '</td>
                          <td>
                            <a href="tenant_CRUD/edit_room_utilities.php?room_id=' . $room_id_payment_table . '&date=' . $row2['due_date'] . '&id=' . $row2['utility_type_id'] . '" 
                              class="btn btn-sm btn-success">Edit</a>
                          </td>
                        </tr>';
                          }
                        } else {
                          $room_modals_payment .= '<tr><td colspan="5" class="text-center text-muted">No utility payment history found</td></tr>';
                        }

                        $room_modals_payment .= '
                          </tbody>
                        </table>
                      </div>
                    </div>';
                      }
                    } else {
                      $room_modals_payment .= '<p class="text-center text-muted">No utility types found for this room</p>';
                    }

                    $room_modals_payment .= '
                      </div> <!-- end Utility Section -->

                    </div> 
                  </div>
                </div> 
              </div>
                    ';
                  endwhile;
                endif;

                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php
  if (!empty($room_modals_payment)) {
    echo $room_modals_payment;
  }
  unset($_SESSION['old_input']);
  ?>


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
            <h3>How to Send Notification on tenant</h3>
            <div class="step-layout">
              <div class="step-content">
                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                  Step 1: Click the “🔔” Button
                </h4>
                <img src="../images/arrow_sendnotif_help.png" alt="Add Building Screenshot" class="step-image">
                <p>Click the <strong>notification icon</strong> to open a modal for sending a notification to the tenant.</p>
              </div>
            </div>

            <div class="step-layout">
              <div class="step-content">
                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                  Step 2: Sending Notification to Tenant
                </h4>
                <img src="../images/sending_notiftenant_help.png" alt="Add Building Screenshot" class="step-image">
                <p>The <strong>Send Message</strong> popup allows the landlord to send a direct message to the tenant. They can enter a <strong>Title</strong> and a <strong>Message</strong>, then click <strong>Send</strong> to deliver it or <strong>Cancel</strong> to close the window.</p>
              </div>
              <div class="step-highlight">
                <i class="fa-solid fa-lightbulb"></i>
                <span>You can access this help anytime by clicking the Help button.</span>
              </div>
            </div>


          </div>
          <div id="step2" class="tutorial-step">
            <div class="step-icon"><i class="fa-solid fa-user-plus"></i></div>
            <h3>How to Send Notification on Room</h3>
            <div class="step-layout">
              <div class="step-content">
                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                  Step 1: Click the “🔔” Button
                </h4>
                <img src="../images/arrow_sendnotif_landlord_help.png" alt="Add Building Screenshot" class="step-image">
                <p>Click the <strong>notification icon</strong> to open a modal for sending a notification to the landlord.</p>
              </div>
            </div>

            <div class="step-layout">
              <div class="step-content">
                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                  Step 2: Sending Notification by Room
                </h4>
                <img src="../images/sending_notiflandlord_help.png" alt="Add Building Screenshot" class="step-image">
                <p>The <strong>Send Message</strong> popup allows the landlord to send a message to all tenants in <strong>Room C101</strong>. They can enter a <strong>Title</strong> and a <strong>Message</strong>, then click <strong>Send</strong> to deliver it or <strong>Cancel</strong> to close the popup.</p>




                <div class="step-highlight">
                  <i class="fa-solid fa-lightbulb"></i>
                  <span>You can access this help anytime by clicking the Help button.</span>
                </div>
              </div>
            </div>

          </div>

          <div id="step3" class="tutorial-step">
            <div class="step-icon"><i class="fa-solid fa-file-contract"></i></div>
            <h3>How to View Payment History by Room</h3>
            <div class="step-layout">
              <div class="step-content">
                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                  Step 1: Click the "Payment" Button
                </h4>
                <img src="../images/arrow_payment_help.png" alt="Add Building Screenshot" class="step-image">
                <p>The <strong>Payment</strong> button lets the landlord view or manage the payment records of tenants in the selected room or apartment.</p>
              </div>
            </div>

            <div class="step-layout">
              <div class="step-content">
                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                  Step 2: Click the "🧾" button
                </h4>
                <img src="../images/arrow_viewpayment_help.png" alt="Add Building Screenshot" class="step-image">
                <p>Click the <strong>edit icon</strong> under the Actions column to view the tenant’s payment details.</p>


              </div>
            </div>

            <div class="step-layout">
              <div class="step-content">
                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                  Step 3: View Room Payment History
                </h4>
                <img src="../images/view_paymenthistory_help.png" alt="Add Building Screenshot" class="step-image">
                <p>Click the <strong>payment history</strong> icon to view all <strong>room</strong> and <strong>utility payments</strong> with their due dates and statuses.</p>



                <div class="step-highlight">
                  <i class="fa-solid fa-lightbulb"></i>
                  <span>You can access this help anytime by clicking the Help button.</span>
                </div>
              </div>
            </div>


          </div>

          <div id="step4" class="tutorial-step">
            <div class="step-icon"><i class="fa-solid fa-user-pen"></i></div>
            <h3>How to Manage Maintenance Request</h3>
            <div class="step-layout">
              <div class="step-content">
                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                  Step 1: Click the "Request" button
                </h4>
                <img src="../images/arrow_mainte_help.png" alt="Add Building Screenshot" class="step-image">
                <p>
                  Click the <strong>payment history</strong> icon to view all <strong>room</strong> and <strong>utility payments</strong> with their due dates and statuses.
                </p>

              </div>

              <div class="step-layout">
                <div class="step-content">
                  <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                    Step 2: Select a Request then click the "Marked Selected as Read"
                  </h4>
                  <img src="../images/arrow_resolvemainte_help.png" alt="Add Building Screenshot" class="step-image">
                  <p>
                    In the <strong>Maintenance Request</strong> modal, select issues and click <strong>Mark Selected as Read</strong> to update their status.</p>





                  <div class="step-highlight">
                    <i class="fa-solid fa-lightbulb"></i>
                    <span>You can access this help anytime by clicking the Help button.</span>
                  </div>
                </div>
              </div>
            </div>



          </div>

          <div id="step5" class="tutorial-step">
            <div class="step-icon"><i class="fa-solid fa-user-pen"></i></div>
            <h3>How to Scan and show the guest logs</h3>
            <div class="step-layout">
              <div class="step-content">
                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                  Step 1: Click the "Logs" button
                </h4>
                <img src="../images/arrow_scanguest_help.png" alt="Add Building Screenshot" class="step-image">
                <p>
                  Click the <strong>Logs</strong> button to open a modal where you can view guest logs and scan their QR code for check-in or check-out.
                </p>
              </div>
            </div>

            <div class="step-layout">
              <div class="step-content">
                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                  Step 2: Click the "Scan Guest QR Code" button
                </h4>
                <img src="../images/arrow_scanbutton_help.png" alt="Add Building Screenshot" class="step-image">
                <p>
                  Click <strong>Scan Guest QR Code</strong> to open the scanner and record guest check-in or check-out details automatically.
                </p>





                <div class="step-highlight">
                  <i class="fa-solid fa-lightbulb"></i>
                  <span>You can access this help anytime by clicking the Help button.</span>
                </div>
              </div>
            </div>

            <div class="step-layout">
              <div class="step-content">
                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                  Step 3: Click the "Generate PDF" button
                </h4>
                <img src="../images/arrow_reportpdf_help.png" alt="Add Building Screenshot" class="step-image">
                <p>
                  Click <strong>Generate PDF</strong> to download a report of all guest logs, including room details, dates, and time records.
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
      const totalSteps = 5;

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

  <div class="modal fade" id="paymentarchivedModal" tabindex="-1" aria-labelledby="paymentarchivedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header border-bottom border-2">
          <h4 class="modal-title" id="paymentarchivedModalLabel"><strong>Payment Archived</strong></h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <!-- Action Buttons -->
          <div class="d-flex flex-wrap gap-2 mb-4">
            <form action="tenant_CRUD/exportpdfdeletedpayment.php" method="post" target="_blank">
              <button type="submit" class="btn btn-outline-danger" <?php echo $disabled_button; ?>>
                <i class="fas fa-file-pdf me-2"></i>Generate PDF
              </button>
            </form>
            <button type="button" id="deletePermanent" class="btn btn-outline-warning" onclick="confirmPermanentDelete()" <?php echo $disabled_button; ?>>
              <i class="fas fa-trash-alt me-2"></i>Permanent Delete
            </button>
          </div>
          <form method="GET" class="mb-3">
            <input type="hidden" name="building_id" value="<?php echo htmlspecialchars($building_id); ?>">
            <div class="input-group">
              <input type="text" name="search_deleted_payment" class="form-control"
                placeholder="Search tenant, email, payment, or room"
                value="<?php echo isset($_GET['search_deleted_payment']) ? htmlspecialchars($_GET['search_deleted_payment']) : ''; ?>">
              <button type="submit" class="btn btn-primary">Search</button>
            </div>
          </form>
          <!-- Payment Table -->
          <div class="table-responsive">
            <table class="table table-bordered align-middle table-hover">
              <thead class="table-primary text-center">
                <tr>
                  <th>Tenant Name</th>
                  <th>Amount</th>
                  <th>Payment Date</th>
                  <th>Payment Method</th>
                  <th>Payment Status</th>
                </tr>
              </thead>
              <tbody class="text-center">
                <?php
                if ($landlord['tenant_priviledge'] === 'Not Approved') {
                  echo '<tr><td colspan="5">No records available</td></tr>';
                } else {
                  $search = isset($_GET['search_deleted_payment']) ? trim($_GET['search_deleted_payment']) : '';

                  $sql = "SELECT rp.expected_amount_due, a.paid_on, a.payment_method, 
                      b.status,
                      us.first_name, us.last_name, us.email, 
                      r.room_number
                FROM payment a 
                JOIN payment_status b ON b.payment_id = a.payment_id
                JOIN rent_payment rp ON rp.payment_id = a.payment_id
                JOIN contract c ON c.contract_id = a.contract_id
                JOIN userall us ON us.user_id = c.user_id
                JOIN room r ON r.room_id = us.room_id
                WHERE b.is_active = 0 
                  AND r.building_id = $building_id";

                  if (!empty($search)) {
                    $search_escaped = mysqli_real_escape_string($db_connection, $search);
                    $sql .= " AND (
                us.first_name LIKE '%$search_escaped%' 
                OR us.last_name LIKE '%$search_escaped%' 
                OR us.email LIKE '%$search_escaped%'
                OR a.payment_method LIKE '%$search_escaped%'
                OR r.room_number LIKE '%$search_escaped%'
              )";
                  }
                  $result_query = mysqli_query($db_connection, $sql);

                  if (!$result_query) {
                    die("SQL Error: " . mysqli_error($db_connection) . "<br>Query: " . $sql);
                  }

                  while ($result = mysqli_fetch_array($result_query)) {
                    echo '<tr>
                          <td>' . htmlspecialchars($result['first_name'] . ' ' . $result['last_name']) . '</td>
                          <td>' . htmlspecialchars($result['expected_amount_due']) . '</td>
                          <td>' . htmlspecialchars($result['paid_on']) . '</td>
                          <td>' . htmlspecialchars($result['payment_method']) . '</td>
                          <td>' . htmlspecialchars($result['status']) . '</td>
                        </tr>';
                  }
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>


  <div class="modal fade" id="tenantRequestModal" tabindex="-1" aria-labelledby="tenantRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header border-bottom border-2">
          <h4 class="modal-title" id="tenantRequestModalLabel"><strong>Maintainance Request</strong></h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <form method="post" id="markasreadform" action="tenant_CRUD/mark_as_read.php">
            <!-- Action Button -->
            <div class="mb-3">
              <button type="submit" id="markAsReadBtn" class="btn btn-primary w-100" disabled>
                <i class="fas fa-check-circle me-2"></i>Mark Selected as Read
              </button>
            </div>

            <!-- Table -->
            <div class="table-responsive">
              <table class="table table-bordered align-middle table-hover">
                <thead class="table-primary text-center">
                  <tr>
                    <th style="text-align: center;">
                      <input type="checkbox" id="selectAllCheckboxRequest">
                    </th>
                    <th>Room Number</th>
                    <th>Issue</th>
                    <th>Description</th>
                    <th>Date Requested</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody class="text-center">
                  <?php
                  $sql = "SELECT a.*, r.room_number, c.* 
                        FROM maintenance_request a 
                        JOIN room r ON a.room_id = r.room_id
                        JOIN status_request c ON c.maintenance_request_id = a.maintenance_request_id
                        WHERE c.update_message = 'Pending' OR c.update_message = 'Not Solved' OR c.update_message = 'Issue Happens Again'";

                  $result_query = mysqli_query($db_connection, $sql);
                  while ($result = mysqli_fetch_array($result_query)) {
                    echo '<tr>
                          <td>
                            <input type="checkbox" name="selected_requests[]" class="tenant-checkbox" value="' . $result['maintenance_request_id'] . '">
                          </td>
                          <td>' . htmlspecialchars($result['room_number']) . '</td>
                          <td>' . htmlspecialchars($result['issue_type']) . '</td>
                          <td>' . htmlspecialchars($result['description']) . '</td>
                          <td>' . htmlspecialchars($result['date_requested']) . '</td>
                          <td>' . htmlspecialchars($result['update_message']) . '</td>
                        </tr>';
                  }
                  ?>
                </tbody>
              </table>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="deletedUsersModal" tabindex="-1" aria-labelledby="deletedUsersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header border-bottom border-2">
          <h5 class="modal-title" id="deletedUsersModalLabel">Guest Logs</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <!-- Buttons -->
          <div class="d-flex flex-wrap gap-2 mb-4">
            <form action="tenant_CRUD/exportpdfguest.php" method="post" target="_blank">
              <button type="submit" class="btn btn-outline-danger">
                <i class="fas fa-file-pdf me-2"></i>Generate PDF
              </button>
            </form>
            <button type="button" class="btn btn-outline-success" onclick="gotoscan()">
              <i class="fas fa-qrcode me-2"></i>Scan Guest QR Code
            </button>
          </div>

          <!-- Table -->
          <div class="table-responsive">
            <table class="table table-bordered align-middle table-hover">
              <thead class="table-primary text-center">
                <tr>
                  <th>Room</th>
                  <th>Tenant Name</th>
                  <th>Time In</th>
                  <th>Time Out</th>
                </tr>
              </thead>
              <tbody class="text-center">
                <?php
                $sql = "SELECT guest.*, r.room_number, CONCAT(us.first_name, ' ',us.last_name) AS name
                      FROM guest_logs guest
                      JOIN guest_pass gp ON gp.id = guest.id
                      JOIN userall us ON us.user_id = gp.user_id
                      JOIN room r ON us.room_id = r.room_id
                      WHERE us.account_status = 'Active' AND r.building_id = $building_id
                      ORDER BY guest_log_id DESC LIMIT 20 ";

                $result_query = mysqli_query($db_connection, $sql);
                while ($result = mysqli_fetch_array($result_query)) {
                  echo '<tr>
                        <td>' . htmlspecialchars($result['room_number']) . '</td>
                        <td>' . htmlspecialchars($result['name']) . '</td>
                        <td>' . htmlspecialchars($result['time_in']) . '</td>';
                  if ($result['time_out']) {
                    echo '
                        <td>' . htmlspecialchars($result['time_out']) . '</td>';
                  } else {
                    echo '
                        <td>Not Available</td>';
                  }
                  echo '</tr>';
                }
                ?>

              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>




  <?php if (isset($_GET['message']) && $_GET['message'] === 'payment_deleted'): ?>
    <script>
      Swal.fire({
        icon: 'success',
        title: 'Payments Deleted',
        text: 'Payment Archived has been Cleared.',
        confirmButtonText: 'OK'
      });
    </script>
  <?php endif; ?>

  <?php if (isset($_GET['message']) && $_GET['message'] === 'marked_as_read'): ?>
    <script>
      Swal.fire({
        icon: 'success',
        title: 'Mark as Read',
        text: 'All selected request has been Marked as Read.',
        confirmButtonText: 'OK'
      });
    </script>
  <?php endif; ?>
  <?php if (isset($_GET['message']) && $_GET['message'] === 'payment_utility'): ?>
    <script>
      Swal.fire({
        icon: 'success',
        title: 'Updating Success',
        text: 'Payment has been saved',
        confirmButtonText: 'OK'
      });
    </script>
  <?php endif; ?>
  <?php if (isset($_GET['message']) && $_GET['message'] === 'last_due'): ?>
    <script>
      Swal.fire({
        icon: 'warning',
        title: 'Last Due Date',
        text: 'This is the last due date of for these room tenants.',
        confirmButtonText: 'OK'
      });
    </script>
  <?php endif; ?>
  <?php if (isset($_GET['message']) && $_GET['message'] === 'update_success'): ?>
    <script>
      Swal.fire({
        icon: 'success',
        title: 'Updating Successful',
        text: 'Payment has been Saved',
        confirmButtonText: 'OK'
      });
    </script>
  <?php endif; ?>
  <?php if (isset($_GET['message']) && $_GET['message'] === 'message_success'): ?>
    <script>
      Swal.fire({
        icon: 'success',
        title: 'Message Sent Successful',
        text: 'Notification has been sent',
        confirmButtonText: 'OK'
      });
    </script>
  <?php endif; ?>
  <?php if (isset($_GET['message']) && $_GET['message'] === 'warning_internet'): ?>
    <script>
      Swal.fire({
        icon: 'warning',
        title: 'Message Sending Failure',
        text: 'Internet is required for multiple sending of notification',
        confirmButtonText: 'OK'
      });
    </script>
  <?php endif; ?>
  <?php if (isset($_GET['message']) && $_GET['message'] === 'exceed_advance_rent'): ?>
    <script>
      Swal.fire({
        icon: 'warning',
        title: 'Exceed Value',
        text: 'You are trying to insert a value that exceed the rent payment',
        confirmButtonText: 'OK'
      });
    </script>
  <?php endif; ?>
  <?php if (isset($_GET['message']) && $_GET['message'] === 'update_advance_success'): ?>
    <script>
      Swal.fire({
        icon: 'success',
        title: 'Updating Successful',
        text: 'Advance Payment has been Saved',
        confirmButtonText: 'OK'
      });
    </script>
  <?php endif; ?>
  <?php if (isset($_GET['message']) && $_GET['message'] === 'value_date_error'): ?>
    <script>
      Swal.fire({
        icon: 'warning',
        title: 'Insertion Date',
        text: 'Please select a date within 3 months before the start date of contract and 3 after the end of contract.',
        confirmButtonText: 'OK'
      });
    </script>
  <?php endif; ?>
  <?php if (isset($_GET['message']) && $_GET['message'] === 'value_date_error_utility'): ?>
    <script>
      Swal.fire({
        icon: 'warning',
        title: 'Insertion Date',
        text: 'Please select a date within equal or higher date to the start date of contract and 3 after the end of contract.',
        confirmButtonText: 'OK'
      });
    </script>
  <?php endif; ?>
  <?php if (isset($_GET['message']) && $_GET['message'] === 'same_type'): ?>
    <script>
      Swal.fire({
        icon: 'warning',
        title: 'Same Type',
        text: 'Please select a utility type that you do not filled up yet for this month.',
        confirmButtonText: 'OK'
      });
    </script>
  <?php endif; ?>


  <script>
    function showRoomTable(roomId) {
      // Hide utility section
      document.getElementById("utilitySection" + roomId).style.display = "none";
      // Show room table
      document.getElementById("roomTable" + roomId).style.display = "block";

      // Active button states
      document.getElementById("btnRoom" + roomId).classList.add("btn-primary");
      document.getElementById("btnRoom" + roomId).classList.remove("btn-outline-primary");
      document.getElementById("btnUtility" + roomId).classList.remove("btn-primary");
      document.getElementById("btnUtility" + roomId).classList.add("btn-outline-primary");
    }

    function showUtilitySection(roomId) {
      // Hide room table
      document.getElementById("roomTable" + roomId).style.display = "none";
      // Show utility section
      document.getElementById("utilitySection" + roomId).style.display = "block";

      // Active button states
      document.getElementById("btnUtility" + roomId).classList.add("btn-primary");
      document.getElementById("btnUtility" + roomId).classList.remove("btn-outline-primary");
      document.getElementById("btnRoom" + roomId).classList.remove("btn-primary");
      document.getElementById("btnRoom" + roomId).classList.add("btn-outline-primary");
    }

    function showUtilityTable(roomId, typeId) {
      // Hide all utility tables inside this modal
      let tables = document.querySelectorAll("#utilitySection" + roomId + " [id^=utilityTable" + roomId + "_]");
      tables.forEach(function(tbl) {
        tbl.style.display = "none";
      });

      // Show the selected table
      document.getElementById("utilityTable" + roomId + "_" + typeId).style.display = "block";

      // Reset all utility type buttons
      let buttons = document.querySelectorAll("#utilitySection" + roomId + " [id^=btnUtil" + roomId + "_]");
      buttons.forEach(function(btn) {
        btn.classList.remove("btn-success");
        btn.classList.add("btn-outline-success");
      });

      // Highlight the active button
      let activeBtn = document.getElementById("btnUtil" + roomId + "_" + typeId);
      activeBtn.classList.add("btn-success");
      activeBtn.classList.remove("btn-outline-success");
    }
  </script>
  <script>
    function confirmAdvancePayment(room_id) {
      const modalAdvancePayment = new bootstrap.Modal(document.getElementById("modalAdvancePayment" + room_id));

      Swal.fire({
        icon: "question",
        title: "Advance Payment",
        text: "It seems this room has paid the current month rental due, do you want to add an advance payment for the next month of rent?",
        showCancelButton: true,
        confirmButtonText: "Yes, add it!",
        cancelButtonText: "Cancel"
      }).then((result) => {
        if (result.isConfirmed) {
          modalAdvancePayment.show();
        }
      });
    }
  </script>

  <script src="../js/script.js"></script>
  <script src="../js/tenantmanage.js"></script>

</body>

</html>