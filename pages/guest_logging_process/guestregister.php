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


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../../css/addcontent.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
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
                    window.location.href = '../../logout.php?status=logout';
                } else {
                    location.reload();
                }
            });
        }
    </script>
</head>

<body>
    <div class="side-bar collapsed">
        <a href="" class="logo">
            <img src="" alt="" class="logo-img">
            <img src="" alt="" class="logo-icon">
        </a>
        <ul class="nav-link">
            <li><a href="../tenantdashboard.php"><i class="fas fa-th-large"></i>
                    <p>DashBoard</p>
                </a></li>
            <li><a href="../tenantprofile.php"><i class="fas fa-cog"></i>
                    <p>User Profile </p>
                </a></li>
            <li><a href="../tenantmaintenance.php"><i class="fas fa-file-contract"></i>
                    <p>Maintenance</p>
                </a></li>
            <li><a href="../tenantguestlog.php"><i class="fas fa-file-contract"></i>
                    <p>Guest Log</p>
                </a></li>
            <li><a onclick="logout()"><i class="fas fa-sign-out-alt"></i>
                    <p> Logout</p>
                </a></li>
            <div class="active"></div>
        </ul>
    </div>

    <main class="main-content">
        <div class="form-section">
            <div class="form-header">
                <i class="fas fa-clipboard-list fa-2x"></i>
                <h1>Guest Registration</h1>
            </div>
            <form action="process_registration.php" class="rule-form" method="post">
                <label for="description">Full name</label>
                <input type="text" name="name" id="name" placeholder="Enter Full name">

                <label for="description">Email Address</label>
                <input type="text" name="email" id="email" placeholder="Enter Email">

                <label for="description">Purpose of Visit</label>
                <textarea id="purpose" name="purpose" placeholder="Enter Purpose of Visit"></textarea>

                <label for="start-date">Date of Visit</label>
                <input type="datetime-local" name="visit_datetime" id="start-date" />


                <button type="submit" class="add-btn">Submit</button>
                <a href="../tenantguestlog.php" class="back-btn">Back</a>
            </form>
        </div>

        <!-- Right Panel -->
        <div class="profile-section">
            <div class="profile-card">
                <img src="../../images/<?php echo $rw['img'] ?>" alt="Profile Picture" class="avatar">
                <h3><?php echo $rw['first_name'] ?> <?php echo $rw['last_name'] ?></h3>
                <p><?php echo $rw['email'] ?></p>
            </div>

            <div class="activity-panel">
                <h4>Recent Activity</h4>
                <ul>
                    <?php
                    $sql = 'SELECT a.*, b.name FROM change_log a
                            JOIN guest_logs b ON b.id = a.record_id
                            WHERE a.action_type IN ("INSERT", "UPDATE") AND table_name = "guest_logs" AND b.user_id = ' . $user_id . '
                            ORDER BY a.changed_at DESC LIMIT 4';

                    $result = mysqli_query($db_connection, $sql);

                    while ($row = mysqli_fetch_assoc($result)):
                        if ($row['name']) {
                            if ($row['action_type'] === 'INSERT') {
                                echo '<li><i class="fas fa-plus"></i> Added New Guest "' . $row['name'] . '"</li>';
                            } elseif ($row['action_type'] === 'UPDATE') {
                                echo '<li><i class="fas fa-pencil-alt"></i> Edited the Guest Details "' . $row['name'] . '"</li>';
                            }
                        } else {
                            echo '<li><i class="fas fa-exclamation-circle"></i> Guest data not found (may have been deleted)</li>';
                        }
                    endwhile;
                    ?>
                </ul>

            </div>
        </div>

    </main>
    <script src="../../js/script.js"></script>
</body>

</html>