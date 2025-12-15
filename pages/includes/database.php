<?php

$db_server = "localhost";
$db_user =  "root";
$db_pass = "";
$db_name = "apartment";
global $db_connection;


$db_connection = mysqli_connect($db_server, $db_user, $db_pass,) or die('Failed to connect');
$db = mysqli_select_db($db_connection, $db_name);

function GetValue($sql_query)
{
    global $db_connection;
    $result = mysqli_query($db_connection, $sql_query);

    if (!$result) {
        die("Database query failed: " . mysqli_error($db_connection));
    }
    if ($row = mysqli_fetch_array($result)) {
        return $row[0];
    } else {
        return null;
    }
}

function isDBtableExist($db_name, $table)
{
    return GetValue("SELECT COUNT(*) 
    FROM information_schema.tables
    WHERE table_schema = '" . $db_name . "'
     AND table_name = '" . $table . "' 
     LIMIT 1;") + 0;
}


if (!isDBtableExist($db_name, 'building')) {
    mysqli_query($db_connection, 'CREATE TABLE building(
    building_id int(255) not null auto_increment,
    name varchar(255) unique not null,
    latitude DECIMAL(12,10),
    longitude DECIMAL(13,10),
    street VARCHAR(255),
    barangay VARCHAR(255),
    city VARCHAR(255),
    province VARCHAR(255),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    number_of_floors varchar(255) not null,
    building_is_active TINYINT(1) not null default 0,
    primary key(building_id));');
}
if (!isDBtableExist($db_name, 'room')) {
    mysqli_query($db_connection, 'CREATE TABLE room(
    room_id int(255) not null auto_increment,
    building_id int(255) not null,
    floor_number int(255) not null,
    room_amount decimal(10,2) not null,
    room_number varchar(255) not null,
    capacity int(255) not null,
    room_status varchar(255) not null,
    primary key(room_id))');
}

if (!isDBtableExist($db_name, 'user')) {
    mysqli_query($db_connection, 'CREATE TABLE user(
    user_id int(255) not null auto_increment,  
    room_id int(100),
    building_id int(100),
    desired_room int(255),                
    role varchar(255) not null,
    date_registered date not null,
    account_status varchar(255) not null,
    payment_priviledge varchar(255) not null,
    tenant_priviledge varchar(255) not null,
    deletion_approval varchar(255),
    primary key(user_id))');
}

if (!isDBtableExist($db_name, 'personal_info')) {
    mysqli_query($db_connection, 'CREATE TABLE personal_info(
    info_id int(255) not null auto_increment,
    user_id int(255) not null,
    first_name varchar(255) not null,
    last_name varchar(255) not null,
    middle_name varchar(255),
    suffix varchar(255),
    birthdate date not null,
    email varchar(255) not null,
    gender varchar(255),
    address varchar(100) not null,
    phone_number varchar(100) not null,
    img text not null,
    primary key(info_id))');
}

if (!isDBtableExist($db_name, 'credential')) {
    mysqli_query($db_connection, 'CREATE TABLE credential(
    credential_id int(255) not null auto_increment,
    user_id int(255) not null,
    password varchar(255) not null,
    primary key(credential_id));');
}

if (!isDBtableExist($db_name, 'settings')) {
    mysqli_query($db_connection, 'CREATE TABLE settings(
    setting_key VARCHAR(100),
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    primary key(setting_key));');
}
if (!isDBtableExist($db_name, 'notification')) {
    mysqli_query($db_connection, 'CREATE TABLE notification(
    notif_id int(255) not null auto_increment,
    user_id int(255) not null,
    notif_title varchar(255) not null,
    notif_text text not null,
    date_created date not null,
    notif_status varchar(255) not null,
    primary key(notif_id));');
}
if (!isDBtableExist($db_name, 'maintenance_request')) {
    mysqli_query($db_connection, 'CREATE TABLE maintenance_request(
    maintenance_request_id int(255) not null auto_increment,
    room_id int(255) not null,
    issue_type varchar(255) not null,
    description text not null,
    date_requested date not null,
    primary key(maintenance_request_id));');
}

if (!isDBtableExist($db_name, 'status_request')) {
    mysqli_query($db_connection, 'CREATE TABLE status_request(
    status_request_id  int(255) not null auto_increment,
    maintenance_request_id int(255) not null,
    update_message varchar(255) not null,
    updated_at date,
    primary key(status_request_id));');
}

if (!isDBtableExist($db_name, 'contract')) {
    mysqli_query($db_connection, 'CREATE TABLE contract(
    contract_id int(255) not null auto_increment,
    user_id int(255),
    start_date date not null,
    end_date date not null,
    terms text not null,
    duration varchar(255) not null,
    contract_status varchar(255) not null,
    update_status varchar(255) not null,
    primary key(contract_id));');
}
if (!isDBtableExist($db_name, 'payment')) {
    mysqli_query($db_connection, 'CREATE TABLE payment(
    payment_id int(255) not null auto_increment,
    contract_id int(255) not null,
    paid_on DATETIME,
    due_date DATE,
    payment_method varchar(255),
    primary key(payment_id));');
}

if (!isDBtableExist($db_name, 'rent_payment')) {
    mysqli_query($db_connection, 'CREATE TABLE rent_payment(
    rent_payment_id int(255) not null auto_increment,
    payment_id int(255) not null,
    expected_amount_due decimal(10,2),
    tenant_payment decimal(10,2) not null,
    balance decimal(10,2),
    primary key(rent_payment_id));');
}

if (!isDBtableExist($db_name, 'utility_payment')) {
    mysqli_query($db_connection, 'CREATE TABLE utility_payment(
    utility_payment_id int(255) not null auto_increment,
    payment_id int(255) not null,
    utility_type_id int(255) not null,
    amount decimal(10,2) not null,
    primary key(utility_payment_id));');
}

if (!isDBtableExist($db_name, 'payment_status')) {
    mysqli_query($db_connection, 'CREATE TABLE payment_status(
    status_id int(255) not null auto_increment,
    payment_id int(255) not null,
    status varchar(50) not null,
    status_date date not null,
    is_active TINYINT(1) not null default 1,
    primary key(status_id));');
}

if (!isDBtableExist($db_name, 'utility_type')) {
    mysqli_query($db_connection, 'CREATE TABLE utility_type(
    utility_type_id int(255) not null auto_increment,
    building_id int(255) not null,
    utility_name varchar(100) not null unique,
    primary key(utility_type_id));');
}

if (!isDBtableExist($db_name, 'system_settings')) {
    mysqli_query($db_connection, 'CREATE TABLE system_settings(
    key_name VARCHAR(100),
    key_value TEXT,
    primary key(key_name));');
}

if (!isDBtableExist($db_name, 'otp')) {
    mysqli_query($db_connection, 'CREATE TABLE otp(
    otp_id int(255) NOT NULL auto_increment,
    user_id int(255) NOT NULL,
    otp_code varchar(255) NOT NULL,
    used tinyint(1) DEFAULT 0,
    expires_at datetime NOT NULL,
    created_at timestamp NULL DEFAULT current_timestamp(),
    primary key(otp_id));');
}

if (!isDBtableExist($db_name, 'guest_pass')) {
    mysqli_query($db_connection, 'CREATE TABLE guest_pass(
    guest_pass_id INT AUTO_INCREMENT,
    user_id int(255) not null,
    qr_token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    primary key(guest_pass_id));');
}

if (!isDBtableExist($db_name, 'guest_logs')) {
    mysqli_query($db_connection, 'CREATE TABLE guest_logs(
    guest_log_id  INT AUTO_INCREMENT,
    guest_pass_id int not null,
    date VARCHAR(100) NOT NULL,
    time_in TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    time_out TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    primary key(guest_log_id));');
}

if (!isDBtableExist($db_name, 'rules')) {
    mysqli_query($db_connection, 'CREATE TABLE rules(
    rules_id INT AUTO_INCREMENT,
    building_id int(255) not null,
    title VARCHAR(100) not null,
    rules_description TEXT not null,
    rules_status VARCHAR(255) not null,
    primary key(rules_id));');
}
$host = '127.0.0.1';
$db   = 'apartment';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
