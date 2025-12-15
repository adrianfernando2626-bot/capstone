<?php
if (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}
if (file_exists('../includes/database.php')) {
    include_once('../includes/database.php');
}
session_start();
$user_id = $_SESSION['user_id'];
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Tenant') {
    header("Location: ../login.php");
    exit();
}
if (isset($_GET['message']) && $_GET['message'] === 'password_change') {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Password Updated',
                text: 'Your password is successfully updated.'
            });
        });
    </script>";
}
if (isset($_GET['message']) && $_GET['message'] === 'account_updated') {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Account Updated',
                text: 'Your account was successfully updated.'
            });
        });
    </script>";
}

if (isset($_GET['message']) && $_GET['message'] === 'account_image_updated') {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Account Image Updated',
                text: 'Your account image was successfully updated.'
            });
        });
    </script>";
}


$warning = "";
$sql = 'SELECT * FROM userall WHERE user_id = ' . $user_id;
$rs = mysqli_query($db_connection, $sql);
$rw = mysqli_fetch_array($rs);



if (isset($_POST['updatepic']) && isset($_FILES['my_image'])) {

    $img_name = $_FILES['my_image']['name'];
    $img_size = $_FILES['my_image']['size'];
    $tmp_name = $_FILES['my_image']['tmp_name'];
    $error = $_FILES['my_image']['error'];

    if ($error === 0) {
        if ($img_size > 250000) {
            $warning = "The file size is too large";
        } else {
            $img_ex = pathinfo($img_name, PATHINFO_EXTENSION);
            $img_ex_lc = strtolower($img_ex);
            $allowed_exs = array("jpg", "jpeg", "png", "jfif");

            if (in_array($img_ex_lc, $allowed_exs)) {
                $new_image_name = uniqid("IMG-", true) . "." . $img_ex_lc;
                $img_upload_path = '../images/' . $new_image_name;
                move_uploaded_file($tmp_name, $img_upload_path);

                $sql2 = "UPDATE personal_info SET 
                img = '" . $new_image_name . "'
                WHERE user_id = $user_id";


                if (mysqli_query($db_connection, $sql2)) {
                    echo "<script>
                            window.location.href = 'tenantprofile.php?message=account_image_updated';
                            </script>";
                    exit();
                    session_destroy();
                } else {
                    echo mysqli_error($db_connection);
                }
            } else {
                $warning = "It only accepts images";
            }
        }
    } else {

        $warning = "Error Uploading the File";
    }
}
if (isset($_POST['updated_account'])) {

    $update_user_id = mysqli_real_escape_string($db_connection, $user_id);


    $first_name = $_POST['copy_first_name'];
    $last_name = $_POST['copy_last_name'];
    $middle_name = $_POST['copy_middle_name'];
    $email = $_POST['copy_email'];
    $birthdate = $_POST['copy_birthdate'];
    $address = $_POST['copy_address'];
    $phone_number = ($_POST['copy_phone_number']);

    $today = new DateTime();
    $birthdate_obj = DateTime::createFromFormat('Y-m-d', $birthdate);
    $ageInterval = $birthdate_obj->diff($today);
    $age = $ageInterval->y;


    if (strlen($phone_number) === 13 && substr($phone_number, 0, 4) === '+639' && $age >= 18) {


        $sql2 = "UPDATE personal_info SET 
        first_name = '" . $first_name . "',
        last_name = '" . $last_name . "',
        middle_name = '" . $middle_name . "',
        birthdate = '" . $birthdate . "',
        email = '" . $email . "',
        address = '" . $address . "',
        phone_number ='" . $phone_number . "'    
        WHERE user_id = $update_user_id";

        if (mysqli_query($db_connection, $sql2)) {
            echo "<script>
            window.location.href = 'tenantprofile.php?message=account_updated';
        </script>";
        } else {
            echo mysqli_error($db_connection);
        }
    } else {
        if (strlen($phone_number) !== 13) {
            $warning = "Please input a valid contact number";
        } elseif (substr($phone_number, 0, 4) !== '+639') {
            $warning = "Please insert a valid phone number that starts with +639";
        } elseif ($age < 18) {
            $warning = "You must be at least 18 years old.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Profile</title>
    <link rel="stylesheet" href="../css/styledashowner4.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

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

        function confirmUpdate() {
            Swal.fire({
                icon: 'warning',
                title: 'Updating Account',
                text: 'Are you sure you want to update your account?',
                showCancelButton: true,
                confirmButtonText: 'Yes, update it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById("updated_account").value = "1";

                    const visibleValue = document.getElementById("first_name").value;
                    document.getElementById("copy_first_name").value = visibleValue;

                    const visibleValue1 = document.getElementById("last_name").value;
                    document.getElementById("copy_last_name").value = visibleValue1;

                    const visibleValue2 = document.getElementById("middle_name").value;
                    document.getElementById("copy_middle_name").value = visibleValue2;

                    const visibleValue3 = document.getElementById("email").value;
                    document.getElementById("copy_email").value = visibleValue3;

                    const visibleValue4 = document.getElementById("address").value;
                    document.getElementById("copy_address").value = visibleValue4;

                    const visibleValue5 = document.getElementById("birthdate").value;
                    document.getElementById("copy_birthdate").value = visibleValue5;

                    const visibleValue8 = document.getElementById("phone_number").value;
                    document.getElementById("copy_phone_number").value = visibleValue8;

                    document.getElementById('updateForm').submit();
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
                <h2>Welcome <?php echo $rw['first_name']; ?></h2>
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

        <div class="user-account-container">
            <form action="tenantprofile.php"
                method="post"
                enctype="multipart/form-data">

                <div class="header-row">
                    <div class="profile-box">
                        <img class="rounded-circle mt-5"
                            width="150px" src="../images/<?php echo $rw['img']; ?>">
                        <input type="hidden" name="user_id" value="<?php echo $rw['user_id']; ?>">
                        <div>
                            <div class="name"><?php echo $rw['first_name']; ?></div>
                            <div class="email"><?php echo $rw['email']; ?></div>
                        </div>
                        <?php if (!empty($warning)): ?>
                            <div class="alert alert-warning"><?php echo htmlspecialchars($warning); ?></div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="edit-button" name="update" onclick="confirmUpdate()">Edit</button>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Choose Profile Image</label>
                        <input type="file" name="my_image" class="form-control">
                        <input style="margin-top:10px;" type="submit" name="updatepic" class="edit-button" value="Update your Profile Image">
                    </div>
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" class="form-control"
                            placeholder="Enter First Name" value="<?php echo $rw['first_name']; ?>" name="first_name" id="first_name">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text"
                            class="form-control" value="<?php echo $rw['last_name']; ?>" placeholder="Enter Last Name" name="last_name" id="last_name">
                    </div>
                    <div class="form-group">
                        <label>Midddle Name</label>
                        <input type="text"
                            class="form-control" value="<?php echo $rw['middle_name']; ?>" placeholder="Enter Middle Name" name="middle_name" id="middle_name">
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text"
                            class="form-control" placeholder="Status" value="<?php echo $rw['role']; ?>" name="role" id="role" readonly>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email"
                            class="form-control" placeholder="Enter Email Address" value="<?php echo $rw['email']; ?>" name="email" id="email">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" class="form-control" placeholder="Enter Phone Number"
                            value="<?php echo $rw['phone_number']; ?>" name="phone_number" id="phone_number">

                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text"
                            class="form-control" placeholder="Enter Address" value="<?php echo $rw['address']; ?>" name="address" id="address">

                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date"
                            class="form-control" value="<?php echo $rw['birthdate']; ?>" name="birthdate" id="birthdate">

                    </div>

            </form>
            <div class="form-group">
                <form method="POST" action="../landlord/send_otp.php">
                    <input type="submit" class="edit-button" value="Change Password">
                </form>
            </div>
        </div>

        <div class="email-section">
            <h3>My Email Address</h3>
            <div class="email-box">
                <div class="email-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div>
                    <div class="email"><?php echo $rw['email']; ?></div>
                    <?php
                    $today = new DateTime();
                    $month_obj = DateTime::createFromFormat('Y-m-d', $rw['date_registered']);
                    $monthInterval = $month_obj->diff($today);
                    $month = $monthInterval->m;
                    ?>
                    <div class="date"> <?php echo $month; ?> month<?php echo $month > 1 ? 's' : ''; ?> ago</div>

                </div>
            </div>
        </div>

        <form id="updateForm" method="POST" action="tenantprofile.php">
            <input type="hidden" name="updated_account" id="updated_account" value="1">
            <input type="hidden" id="copy_first_name" name="copy_first_name">
            <input type="hidden" id="copy_last_name" name="copy_last_name">
            <input type="hidden" id="copy_middle_name" name="copy_middle_name">
            <input type="hidden" id="copy_email" name="copy_email">
            <input type="hidden" id="copy_address" name="copy_address">
            <input type="text" id="copy_phone_number" name="copy_phone_number" hidden>
            <input type="hidden" id="copy_birthdate" name="copy_birthdate">
        </form>
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
                        <div class="step-icon"><i class="fa-solid fa-user"></i></div>
                        <h3>Profile Page</h3>
                        <div class="step-layout">
                            <div class="step-content">
                                <h4 style="margin-bottom: 16px; color: var(--font-color); font-size: 18px; font-weight: 600;">
                                    Step 1: Update Your Profile Information
                                </h4>
                                <img src="../images/tenant_profile_help.png" alt="Add Building Screenshot" class="step-image">
                                <p>This page lets you view and update your profile information. You can edit your details, upload a profile image, or change your password.</p>
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
            const totalSteps = 1;

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