    <?php
    require_once 'tenant/guest_logging_process/vendor/autoload.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    if (file_exists('includes/database.php')) {
        include_once('includes/database.php');
    }
    if (file_exists('../includes/database.php')) {
        include_once('../includes/database.php');
    }
    session_start();
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "apartment";

    $conn = new mysqli($host, $username, $password, $database);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }


    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $_SESSION['old_input'] = $_POST;

        $img_name = $_FILES['my_image']['name'];
        $img_size = $_FILES['my_image']['size'];
        $tmp_name = $_FILES['my_image']['tmp_name'];
        $error = $_FILES['my_image']['error'];

        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $middle_name = $_POST['middle_name'];
        $suffix = $_POST['suffix'];
        $email = $_POST['email'];
        $phone_number = $_POST['phone_number'];
        $address = $_POST['address'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password_tenant'];
        $birthdate = $_POST['birthdate'];
        $gender = $_POST['gender'];
        $role = 'Tenant';
        $desired_room = $_POST['desired_room'];
        $date_registered = date('Y-m-d');
        $account_status = 'Pending';
        $terms = $_POST['terms_agreed'];
        $deletion_approval = "Not Approved";

        if (empty($terms)) {
            $_SESSION['warning_sign_up'] = "You must agree to the terms and conditions.";
            header("Location: login.php?signup=true&message=" . $_SESSION['warning_sign_up']);
            exit();
        }


        $querycheckemail2 = "SELECT COUNT(desired_room) as total_desired_room FROM user WHERE desired_room = '$desired_room'";
        $rscheckemail2 = mysqli_query($db_connection, $querycheckemail2);
        $check_rs2 = mysqli_fetch_array($rscheckemail2);

        $querycheckemail1 = "SELECT capacity FROM room WHERE room_id = '$desired_room'";
        $rscheckemail1 = mysqli_query($db_connection, $querycheckemail1);
        $check_rs1 = mysqli_fetch_array($rscheckemail1);

        $birthdate_obj = DateTime::createFromFormat('Y-m-d', $birthdate);
        $age = $birthdate_obj->diff(new DateTime())->y;

        $checkStmt = $conn->prepare("SELECT * FROM userall WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        if ($password !== $confirm_password) {
            $_SESSION['warning_sign_up'] = "Passwords do not match.";
            header("Location: login.php?signup=true&message=" . $_SESSION['warning_sign_up']);
            exit();
        }
        if ($result->num_rows > 0) {
            $_SESSION['warning_sign_up'] = "Email already exists. Please use another.";
            header("Location: login.php?signup=true&message=" . $_SESSION['warning_sign_up']);
            exit();
        }
        $checkStmt->close();
        if ($check_rs1['capacity'] <= $check_rs2['total_desired_room']) {
            $_SESSION['warning_sign_up'] = 'The reservation for that room is not available.';
            header("Location: login.php?signup=true&message=" . $_SESSION['warning_sign_up']);
            exit();
        } else {
            if ($error === 0) {
                if ($img_size > 125000) {
                    $_SESSION['warning_sign_up'] = 'File size too large';
                    header("Location: login.php?signup=true&message=" . $_SESSION['warning_sign_up']);
                    exit();
                } else {
                    $img_ex_lc = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));
                    $allowed_exs = ["jpg", "jpeg", "png"];
                    if (in_array($img_ex_lc, $allowed_exs)) {
                        $new_image_name = uniqid("IMG-", true) . "." . $img_ex_lc;
                        $img_upload_path = 'images/' . $new_image_name;
                        move_uploaded_file($tmp_name, $img_upload_path);

                        if (
                            strlen($phone_number) === 13 &&
                            substr($phone_number, 0, 4) === '+639' &&
                            strlen($password) >= 8 &&
                            $age >= 18
                        ) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                            $stmtUser = $conn->prepare("INSERT INTO user (role, desired_room, date_registered, account_status, deletion_approval) VALUES (?, ?, ?, ?, ?)");
                            $stmtUser->bind_param("sisss", $role, $desired_room, $date_registered, $account_status, $deletion_approval);
                            $stmtUser->execute();
                            $user_id = $stmtUser->insert_id;
                            $stmtUser->close();

                            $stmtInfo = $conn->prepare("INSERT INTO personal_info (user_id, first_name, last_name, middle_name, suffix, birthdate, email, gender, address, phone_number, img) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmtInfo->bind_param("issssssssss", $user_id, $first_name, $last_name, $middle_name, $suffix, $birthdate, $email, $gender, $address, $phone_number, $new_image_name);
                            $stmtInfo->execute();
                            $stmtInfo->close();

                            $stmtCreds = $conn->prepare("INSERT INTO credential (user_id, password) VALUES (?, ?)");
                            $stmtCreds->bind_param("is", $user_id, $hashed_password);
                            $stmtCreds->execute();
                            $stmtCreds->close();

                            $emailsadmin = "SELECT CONCAT(first_name, ' ', last_name) AS admin_name, user_id, first_name, email FROM userall WHERE role = 'Admin' LIMIT 1";
                            $rscheckadmin = mysqli_query($db_connection, $emailsadmin);
                            $admin = mysqli_fetch_array($rscheckadmin);

                            $notif_text = "New tenant has reserved one of your rooms ( " . $first_name . " " . $last_name . ")<br>";


                            $pdo->prepare("INSERT INTO notification 
                               (user_id, notif_title, notif_text, date_created, notif_status) 
                               VALUES (?, 'Pending Tenants', ?, NOW(), 'unread')")
                                ->execute([$admin['user_id'], $notif_text]);

                            $mail = new PHPMailer(true);
                            try {
                                $mail->isSMTP();
                                $mail->Host       = 'smtp.gmail.com';
                                $mail->SMTPAuth   = true;
                                $mail->Username   = 'adrianfernando2626@gmail.com';
                                $mail->Password   = 'cxwqqwktqevyogmt';
                                $mail->SMTPSecure = 'tls';
                                $mail->Port       = 587;

                                $mail->setFrom('adrianfernando2626@gmail.com', 'New Pending Tenants');
                                $mail->addAddress($admin['email'], $admin['admin_name']);

                                $mail->isHTML(true);
                                $mail->Subject = 'New Reserved Tenants';
                                $mail->Body    = "Dear " . htmlspecialchars($admin['first_name']) . ",<br><br>" .
                                    "New tenant has reserved one of your rooms ( " . $first_name . " " . $last_name . ")<br>" .
                                    "You may log in your account: <a href='localhost/capstone/pages/login.php'>Click here to login</a><br><br>" .
                                    "To create new contracts for this tenant" .
                                    "<strong>This is an automated system message — please do not reply.</strong>";

                                $mail->send();
                            } catch (Exception $e) {
                                error_log("Email to {$admin['email']} failed: " . $mail->ErrorInfo);
                            }

                            unset($_SESSION['old_input']);
                            header("Location: login.php?message=signed_up_successful");
                            exit();
                        } else {
                            $_SESSION['warning_sign_up'] = match (true) {
                                strlen($password) < 8 => "Password must be at least 8 characters long.",
                                strlen($phone_number) !== 13 => "Please input a valid contact number.",
                                substr($phone_number, 0, 4) !== '+639' => "Please insert a valid phone number that starts with +639",
                                $age < 18 => "You must be at least 18 years old.",
                            };
                            header("Location: login.php?signup=true&message=" . $_SESSION['warning_sign_up']);
                            exit();
                        }
                    } else {
                        $_SESSION['warning_sign_up'] = 'It only accepts image files.';
                        header("Location: login.php?signup=true&message=" . $_SESSION['warning_sign_up']);
                        exit();
                    }
                }
            } else {
                $_SESSION['warning_sign_up'] = 'Please upload a profile picture.';
                header("Location: login.php?signup=true&message=" . $_SESSION['warning_sign_up']);
                exit();
            }
        }
    }
