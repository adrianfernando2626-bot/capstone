<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Landlord') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "apartment");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$query = "SELECT n.*, pi.first_name, pi.last_name
          FROM notification n
          JOIN user u ON n.user_id = u.user_id
          JOIN personal_info pi ON u.user_id = pi.user_id
          ORDER BY n.notif_id DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Billing Notifications</title>
    <link rel="stylesheet" href="../css/styledashowner4.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="side-bar">

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
    <div class="main">
        <div class="main-header">
            <div class="burger-btn" id="burgerBtn">
                <i class="fas fa-bars"></i>
            </div>
            <h2>Billing Notification Logs</h2>
        </div>
        <table class="tenant-table">
            <thead>
                <tr>
                    <th>Receiver</th>
                    <th>Title</th>
                    <th>Message</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['notif_title']); ?></td>
                        <td><?php echo htmlspecialchars($row['notif_text']); ?></td>
                        <td><?php echo htmlspecialchars($row['date_created']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const sidebar = document.querySelector(".side-bar");

            sidebar.classList.add("collapsed");

            sidebar.addEventListener("mouseenter", function() {
                sidebar.classList.remove("collapsed");
            });

            sidebar.addEventListener("mouseleave", function() {
                sidebar.classList.add("collapsed");
            });
        });
    </script>
    <script src="../js/script.js"></script>
</body>

</html>

<?php $conn->close(); ?>