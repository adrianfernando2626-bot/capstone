<?php
if (file_exists('includes/database.php')) {
    include_once('includes/database.php');
}
if (file_exists('../includes/database.php')) {
    include_once('../includes/database.php');
}



ob_start();
session_start();

require_once 'tenant/guest_logging_process/vendor/autoload.php'; // adjust if needed

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;



if (isset($_GET['message']) && $_GET['message'] === 'account_deleted') {
    $warning = "Your account was successfully deleted";
}
if (isset($_GET['status']) && $_GET['status'] === 'logout') {
    session_unset();
    session_destroy();
}


// Load session messages and form values
$old = $_SESSION['old_input'] ?? [];
$warning_sign_up = $_SESSION['warning_sign_up'] ?? '';
$warning_log_in = $_SESSION['warning_log_in'] ?? '';
unset($_SESSION['old_input'], $_SESSION['warning_sign_up'], $_SESSION['warning_log_in']);

$host = "localhost";
$username = "root";
$password = "";
$database = "apartment";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}



// Already in your login.php — just add this check early
$checkAdmin = $conn->query("SELECT COUNT(*) as count FROM user WHERE role = 'Admin'");
$adminExists = $checkAdmin->fetch_assoc()['count'] > 0;

$admin_warning = $_SESSION['admin_signup_warning'] ?? '';
$admin_success = $_SESSION['admin_signup_success'] ?? '';
unset($_SESSION['admin_signup_warning'], $_SESSION['admin_signup_success']);


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = trim($_POST['password']);
    $remember = isset($_POST['remember']); // this should match checkbox name="remember"

    $stmt = $conn->prepare("SELECT c.user_id, c.password, u.role, pi.email, pi.first_name, u.account_status 
                            FROM credential c
                            JOIN user u ON c.user_id = u.user_id
                            JOIN personal_info pi ON u.user_id = pi.user_id
                            WHERE pi.email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();



    if ($user) {
        if ($user['account_status'] === 'Renewal for Contract') {
            $_SESSION['show_renewal_swal'] = true;
            $_SESSION['renew_user_id'] = $user['user_id'];
            header("Location: login.php");
            exit();
        } else {
            if ($user['account_status'] === 'Active') {
                if (password_verify($password, $user['password'])) {

                    // ✅ Check if cookie exists (already remembered) — SKIP OTP
                    $remember_cookie = "remember_user_" . $user['user_id'];
                    if (isset($_COOKIE[$remember_cookie])) {
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['role'] = $user['role'];
                        header("Location: includes/update_expired_contract.php");
                        exit();
                    }

                    // ✅ If no cookie, proceed to OTP logic
                    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

                    $stmt = $conn->prepare("INSERT INTO otp (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $user['user_id'], $otp, $expires);
                    $stmt->execute();
                    $_SESSION['pending_user_id'] = $user['user_id'];
                    $_SESSION['pending_role'] = $user['role'];
                    $_SESSION['otp_email'] = $user['email'];
                    $_SESSION['remember_me'] = $remember;
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'adrianfernando2626@gmail.com';
                        $mail->Password = 'cxwqqwktqevyogmt';
                        $mail->SMTPSecure = 'tls';
                        $mail->Port = 587;

                        $mail->setFrom('adrianfernando2626@gmail.com', 'Tenant Management');
                        $mail->addAddress($user['email'], $user['first_name']);
                        $mail->isHTML(true);
                        $mail->Subject = 'Login Verification OTP';
                        $mail->Body = "<p>Hi {$user['first_name']},</p><p>Your login OTP is: <strong>$otp</strong><br>This will expire in 5 minutes.</p>";
                        $mail->send();

                        if (!headers_sent()) {
                            header("Location: verify_login_otp.php");
                            exit();
                        } else {
                            echo "Redirect failed. Headers already sent.";
                            exit();
                        }
                    } catch (Exception $e) {
                        $_SESSION['warning_log_in'] = "No internet connection";
                        header("Location: login.php?message=" . $_SESSION['warning_log_in']);
                        exit();
                    }
                } else {
                    $_SESSION['warning_log_in'] = "Incorrect password.";
                    header("Location: login.php");
                    exit();
                }
            } else {
                if ($user['account_status'] === 'Pending') {
                    $_SESSION['warning_log_in'] = "Your account is not active yet.";
                } elseif ($user['account_status'] === 'Deleted') {
                    $_SESSION['warning_log_in'] = "Your account has been deleted.";
                } elseif ($user['account_status'] === 'Inactive') {
                    $_SESSION['warning_log_in'] = "Your account is inactive.";
                }
                header("Location: login.php");
                exit();
            }
        }
    } else {
        $_SESSION['warning_log_in'] = "No user found.";
        header("Location: login.php");
        exit();
    }

    $stmt->close();
} ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="css/style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900&display=swap"
        rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.75);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .modal-content {
            background: #fff;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .modal-content h2 {
            margin-bottom: 1rem;
        }

        .modal-content input {
            width: 100%;
            padding: 0.6rem;
            margin-bottom: 0.8rem;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .modal-content button {
            width: 100%;
            padding: 0.7rem;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;

        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            width: 100%;
            padding-right: 2.5rem;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 1.2rem;
            user-select: none;
        }
    </style>
    <script>
        document.querySelectorAll('.toggle-password').forEach(function(icon) {
            icon.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);

                if (input.type === 'password') {
                    input.type = 'text';
                    this.classList.remove('bi-eye-slash');
                    this.classList.add('bi-eye');
                } else {
                    input.type = 'password';
                    this.classList.remove('bi-eye');
                    this.classList.add('bi-eye-slash');
                }
            });
        });
    </script>
    <title>Login Page</title>
</head>

<body>
    <div class="container" id="container">
        <!-- Sign Up -->
        <div class="sign-up">
            <form id="signupForm" action="signup.php" method="POST" enctype="multipart/form-data">

                <div id="signup1">
                    <h1>Create Account</h1>
                    <h3>Personal Information</h3>

                    <?php if ($warning_sign_up): ?>
                        <p style="color: red;"><?php echo $warning_sign_up; ?></p>
                    <?php endif; ?>

                    <input type="text" name="last_name" id="first_name" value="<?php echo htmlspecialchars($old['last_name'] ?? ''); ?>" placeholder="Last Name" class="inputs" required />
                    <input type="text" name="first_name" id="last_name" value="<?php echo htmlspecialchars($old['first_name'] ?? ''); ?>" placeholder="First Name" class="inputs" required />
                    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($old['middle_name'] ?? ''); ?>" placeholder="Middle Name (optional)" class="inputs" />
                    <input type="text" name="suffix" value="<?php echo htmlspecialchars($old['suffix'] ?? ''); ?>" placeholder="Suffix (e.g Jr., Sr.) (optional)" class="inputs" />
                    <input type="text" name="phone_number" id="phone_number" value="<?php echo htmlspecialchars($old['phone_number'] ?? '+639'); ?>" placeholder="Phone Number (Start at +639)" class="inputs" required />
                    <input type="text" name="address" id="address" value="<?php echo htmlspecialchars($old['address'] ?? ''); ?>" placeholder="Address" class="inputs" required />

                    <label for="birthdate">Birthdate: <span style="color: red;">(18 years old and above only)</span></label>
                    <input type="date" name="birthdate" id="birthdate" value="<?php echo htmlspecialchars($old['birthdate'] ?? ''); ?>" placeholder="Date of Birth" class="inputs" required />

                    <label for="gender">Gender:</label>
                    <select id="gender" class="inputs" name="gender" required>
                        <option value="Male" <?php echo (isset($old['gender']) && $old['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo (isset($old['gender']) && $old['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Nonbinary" <?php echo (isset($old['gender']) && $old['gender'] === 'nonbinary') ? 'selected' : ''; ?>>Non-binary</option>
                        <option value="Prefer_not_to_say" <?php echo (isset($old['gender']) && $old['gender'] === 'prefer_not_to_say') ? 'selected' : ''; ?>>Prefer not to say</option>
                        <option value="Other" <?php echo (isset($old['gender']) && $old['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>

                    <button id="signup-next-1">Next</button>

                    <p class="account-link">
                        Already have an account?
                        <a href="#" id="signInToggle">Sign In</a>
                    </p>

                </div>
                <div id="signup2">
                    <h1>User Credentials</h1>

                    <input type="email" name="email" value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" placeholder="Email" required />
                    <input type="password" name="password" id="password" value="<?php echo htmlspecialchars($old['password'] ?? ''); ?>" placeholder="Create Password" required />
                    <input type="password" name="confirm_password_tenant" id="confirm_password" value="<?php echo htmlspecialchars($old['confirm_password_tenant'] ?? ''); ?>" placeholder="Confirm Password" required />

                    <div class="show-password-container">
                        <input type="checkbox" id="show-password" name="show_password" <?php echo (!empty($old['show_password'])) ? 'checked' : ''; ?> />
                        <label for="show-password">Show Password</label>
                    </div>

                    <button id="signup-previous-2">Previous</button>
                    <button id="signup-next-2">Next</button>

                    <p class="account-link">
                        Already have an account?
                        <a href="login.php">Sign In</a>
                    </p>
                </div>
                <div id="signup3">
                    <h1>Choose Room</h1>
                    <label for="my_image">Profile Picture:</label>
                    <input type="file" name="my_image" id="my_image" required /> <br>

                    <label for="room_list" class="form-label">Available Rooms:</label>
                    <select id="desired_room" class="inputs" name="desired_room" required>
                        <option value="" disabled selected>- Select a Room -</option>

                        <?php
                        $stmt = $conn->prepare("
                            SELECT r.room_id, r.room_number, r.capacity, r.room_amount, r.floor_number,
                                COALESCE(COUNT(u.desired_room), 0) AS total_reserved
                            FROM room r
                            LEFT JOIN userall u
                                ON r.room_id = u.desired_room
                                AND (u.account_status = 'Active' OR u.account_status = 'Pending')
                            WHERE r.room_status = 'Available'
                            GROUP BY r.room_id
                            ORDER BY r.room_number ASC
                        ");
                        $stmt->execute();
                        $result = $stmt->get_result();

                        while ($room = $result->fetch_assoc()) {
                            $total_reserved = $room['total_reserved'];
                            $capacity = $room['capacity'];
                            $disabled = ($total_reserved >= $capacity) ? 'disabled' : '';
                            $selected = (isset($old['desired_room']) && $old['desired_room'] == $room['room_id']) ? 'selected' : '';

                            echo '<option value="' . htmlspecialchars($room['room_id']) . '" ' . $selected . ' ' . $disabled . '>';
                            echo 'Room Number: ' . htmlspecialchars($room['room_number']);
                            echo ' | Capacity: ' . htmlspecialchars($total_reserved) . ' / ' . htmlspecialchars($capacity);
                            echo ' | Room Price: ' . htmlspecialchars($room['room_amount']);
                            echo ' | Floor Number: ' . htmlspecialchars($room['floor_number']);
                            echo '</option>';
                        }
                        $conn->close();
                        ?>
                    </select>


                    <div class="show-password-container">
                        <input type="checkbox" id="termsConditions" name="terms_agreed" <?php echo (!empty($old['terms_agreed'])) ? 'checked' : ''; ?> required />
                        <label for="termsConditions">
                            I agree to the <a href="#" id="show-terms" style="color: blue; text-decoration: underline;">Terms and Conditions</a>
                        </label>
                    </div>

                    <button id="signup-previous-3">Previous</button>
                    <button type="submit">Sign Up</button>

                    <p class="account-link">
                        Already have an account?
                        <a href="login.php">Sign In</a>
                    </p>

                </div>
            </form>
        </div>

        <div class="sign-in">
            <form action="login.php" method="POST">

                <h1>Sign In</h1>
                <input type="email" name="email" value="<?php echo htmlspecialchars($old['email_login'] ?? ''); ?>"
                    placeholder="Email" required />
                <input type="password" name="password"
                    value="<?php echo htmlspecialchars($old['password_login'] ?? ''); ?>" id="login_password"
                    placeholder="Password" required />

                <div class="show-password-container">
                    <input type="checkbox" name="remember" id="remember" value="1">
                    <label for="remember">Remember Me</label>
                </div>


                <div class="show-password-container">
                    <input type="checkbox" id="show-login-password" />
                    <label for="show-login-password">Show Password</label>
                </div>
                <a href="#" id="forgot-password-link">Forgot password?</a>
                <button type="submit">Sign In</button>
                <?php if ($warning_log_in): ?>
                    <p style="color: red;"><?php echo $warning_log_in; ?></p>
                <?php endif; ?>

                <p style="color: green;">
                    <?php
                    if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
                        echo "Password reset successful. You can now log in.";
                    }
                    ?>
                </p>

                <!-- Toggle para sa sign up -->
                <p class="account-link">
                    Don't have an account or want to reserve a room ? <a href="#" id="signUpToggle">Sign Up</a>
                </p>

            </form>
        </div>

        <div class="forgot-password">
            <form action="forgot_password.php" method="POST">
                <h2>Forgot Password</h2>
                <input type="email" name="email" placeholder="Enter your email" required />
                <button type="submit">Send OTP</button>
                <button id="back-to-login">Back to Login</button>
            </form>
        </div>

        <div class="terms-and-conditions">
            <h2>Terms and Conditions</h2>
            <p>1. User Access Eligibility: Only authorized employees with valid login credentials may use the EMS.
                Account Responsibility: Users are responsible for the confidentiality of their login credentials and all
                activities under their account.
                Access Restrictions: Use the EMS only for job-related tasks. Unauthorized access or misuse will result
                in disciplinary action.</p>
            <p>2. System Usage Permitted Use: Use the EMS only for work-related purposes in line with company policies.
                Prohibited Use: Do not engage in illegal activities, share harmful content, or attempt unauthorized
                access.</p>
            <p>3. Data Privacy & Security Data Use: The EMS collects and processes employee data for work-related
                purposes. By using the EMS, you consent to this data collection.
                Confidentiality: Maintain the confidentiality of all data accessed through the EMS.</p>
            <p>4. Intellectual Property All content in the EMS is owned by OKX lang ako and is protected by copyright.
                Users may only access the system for authorized purposes.
            <p>5. Monitoring The company may monitor EMS activity to ensure compliance with these terms and to protect
                the system..</p>
            <button id="back-to-signup">Back to Signup</button>
        </div>
    </div>

    <!-- Form for contract renewal -->
    <?php if (isset($_SESSION['renew_user_id'])): ?>
        <form action="tenant/approveOwner/renew_contract.php" method="post" id="renewForm">
            <input type="hidden" name="user_id" value="<?php echo $_SESSION['renew_user_id']; ?>">
        </form>
    <?php endif; ?>

    <script src="js/script.js"></script>
    <?php if (isset($_SESSION['show_renewal_swal']) && $_SESSION['show_renewal_swal'] === true): ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'question',
                    title: 'Expired Contract',
                    text: 'It seems your contract is already expired. Do you want to request a renewal of contract from the building owner?',
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'No',
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('renewForm').submit();
                    } else {
                        location.reload();
                    }
                });
            });
        </script>
        <?php unset($_SESSION['show_renewal_swal'], $_SESSION['renew_user_id']); ?>
    <?php endif; ?>
    <?php if (isset($_GET['message']) && $_GET['message'] === 'renewal_success'): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Renewal Success',
                text: 'Your request has been sent to the building owner, please wait for the response',
                confirmButtonText: 'OK'
            });
        </script>
    <?php endif; ?>
    <?php if (isset($_GET['message']) && $_GET['message'] === 'signed_up_successful'): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Sign Up Success',
                text: 'Successfully creation of account, please wait for the building owner to make you contract',
                confirmButtonText: 'OK'
            });
        </script>
    <?php endif; ?>
    <?php if (isset($_GET['message']) && $_GET['message'] === 'update_approval_success'): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Approval Success',
                text: 'Successfully approving the landlord to delete the tenant accounts',
                confirmButtonText: 'OK'
            });
        </script>
    <?php endif; ?>
    <?php ob_end_flush(); ?>

    <!-- Admin Initial -->
    <?php if (!$adminExists): ?>
        <div class="modal-overlay">
            <div class="modal-content">
                <h2>Initial Admin Signup</h2>

                <?php if ($admin_warning): ?>
                    <p style="color:red;"><?php echo $admin_warning; ?></p>
                <?php endif; ?>

                <?php if ($admin_success): ?>
                    <script>
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: '<?php echo $admin_success; ?>',
                        }).then(() => {
                            location.reload();
                        });
                    </script>
                <?php endif; ?>

                <form action="admin_signup_process.php" method="POST">
                    <input type="text" name="first_name" placeholder="First Name" required />
                    <input type="text" name="last_name" placeholder="Last Name" required />
                    <input type="email" name="email" placeholder="Email" required />
                    <input type="text" name="phone_number"
                        value="+639" placeholder="Phone Number (Start at +639)" required>
                    <input type="password" name="password" id="admin_password" placeholder="Password" required />
                    <input type="password" name="confirm_password_admin" id="confirm_admin_password"
                        placeholder="Confirm Password" required />

                    <input type="checkbox" id="show-login-password-admin" />
                    <label for="show-login-password-admin">Show Password</label>
                    <button type="submit">Create Admin</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <script src="js/script.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Get references to each signup section
            const signup1 = document.getElementById("signup1");
            const signup2 = document.getElementById("signup2");
            const signup3 = document.getElementById("signup3");

            // Get all navigation buttons
            const next1 = document.getElementById("signup-next-1");
            const prev2 = document.getElementById("signup-previous-2");
            const next2 = document.getElementById("signup-next-2");
            const prev3 = document.getElementById("signup-previous-3");

            // STEP 1 → STEP 2
            next1.addEventListener("click", function(e) {
                e.preventDefault(); // stop form from submitting
                signup1.style.display = "none";
                signup2.style.display = "block";
                signup3.style.display = "none";
            });

            // STEP 2 → STEP 1
            prev2.addEventListener("click", function(e) {
                e.preventDefault();
                signup1.style.display = "block";
                signup2.style.display = "none";
                signup3.style.display = "none";
            });

            // STEP 2 → STEP 3
            next2.addEventListener("click", function(e) {
                e.preventDefault();
                signup1.style.display = "none";
                signup2.style.display = "none";
                signup3.style.display = "block";
            });

            // STEP 3 → STEP 2
            prev3.addEventListener("click", function(e) {
                e.preventDefault();
                signup1.style.display = "none";
                signup2.style.display = "block";
                signup3.style.display = "none";
            });
        });
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const signInForm = document.querySelector(".sign-in");
            const signUpForm = document.querySelector(".sign-up");
            const forgotPasswordForm = document.querySelector(".forgot-password");

            const signUpToggle = document.getElementById("signUpToggle");
            const signInToggle = document.getElementById("signInToggle");
            const forgotPasswordLink = document.getElementById("forgot-password-link");
            const backToLoginBtn = document.getElementById("back-to-login");

            // ✅ Sign-up step sections
            const step1 = document.getElementById("signup1");
            const step2 = document.getElementById("signup2");
            const step3 = document.getElementById("signup3");

            // Function to show only one sign-up step at a time
            const showStep = (stepToShow) => {
                [step1, step2, step3].forEach(step => step.style.display = "none");
                stepToShow.style.display = "block";
            };

            // Default: show step 1
            showStep(step1);

            // Step navigation
            document.getElementById("signup-next-1").addEventListener("click", (e) => {
                e.preventDefault();
                showStep(step2);
            });

            document.getElementById("signup-previous-2").addEventListener("click", (e) => {
                e.preventDefault();
                showStep(step1);
            });

            document.getElementById("signup-next-2").addEventListener("click", (e) => {
                e.preventDefault();
                showStep(step3);
            });

            document.getElementById("signup-previous-3").addEventListener("click", (e) => {
                e.preventDefault();
                showStep(step2);
            });

            // Function to show specific main form (sign-in / sign-up / forgot)
            const showForm = (formName) => {
                if (signInForm) signInForm.style.display = "none";
                if (signUpForm) signUpForm.style.display = "none";
                if (forgotPasswordForm) forgotPasswordForm.style.display = "none";

                if (formName === "signUp") {
                    signUpForm.style.display = "block";
                    showStep(step1); // ✅ Always start from step 1 when opening sign-up
                } else if (formName === "forgotPassword") {
                    forgotPasswordForm.style.display = "block";
                } else {
                    signInForm.style.display = "block";
                }
            };

            // Sign up toggle
            if (signUpToggle) {
                signUpToggle.addEventListener("click", (e) => {
                    e.preventDefault();
                    showForm("signUp");
                });
            }

            // Sign in toggle
            if (signInToggle) {
                signInToggle.addEventListener("click", (e) => {
                    e.preventDefault();
                    showForm("signIn");
                });
            }

            // Forgot password toggle
            if (forgotPasswordLink) {
                forgotPasswordLink.addEventListener("click", (e) => {
                    e.preventDefault();
                    showForm("forgotPassword");
                });
            }

            // Back to login button
            if (backToLoginBtn) {
                backToLoginBtn.addEventListener("click", (e) => {
                    e.preventDefault();
                    showForm("signIn");
                });
            }

            // ✅ Automatically show sign-up if ?signup=true in URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get("signup") === "true") {
                showForm("signUp");
            } else {
                showForm("signIn");
            }
        });
    </script>

    <?php if (isset($_GET['admin_signup_success']) && $_GET['admin_signup_success'] === 'success'): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Admin Creation Account',
                text: 'You have created your successfully, you can now sign in',
                confirmButtonText: 'OK'
            });
        </script>
    <?php endif; ?>

</body>
<!-- JS para sa toggle sa sign up/sign in -->

</html>