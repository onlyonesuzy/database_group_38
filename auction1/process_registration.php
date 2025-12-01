<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// TODO: Extract $_POST variables, check they're OK, and attempt to create
// an account. Notify user of success/failure and redirect/give navigation 
// options.
require_once('header.php');
require_once('db_connect.php'); // 引入数据库连接

// 提取 POST 变量
$accountType = $_POST['accountType'] ?? '';
$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirmation = $_POST['passwordConfirmation'] ?? '';

// 简单的后端验证
$errors = [];
$validAccountTypes = ['buyer', 'seller', 'both'];

if ($email === '' || $username === '' || $password === '' || $passwordConfirmation === '') {
    $errors[] = "All fields are required.";
}

if (!in_array($accountType, $validAccountTypes, true)) {
    $errors[] = "Please choose whether you want to be a buyer, seller, or both.";
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please enter a valid email address.";
} else {
    $emailDomain = substr(strrchr($email, "@"), 1);
    if (strpos($emailDomain, '.') === false) {
        $errors[] = "Email domain must contain a dot.";
    }
    $blockedDomains = ['mailinator.com', 'tempmail.com', '10minutemail.com'];
    if (in_array(strtolower($emailDomain), $blockedDomains)) {
        $errors[] = "Disposable email domains are not allowed.";
    }
}

if (!preg_match('/^[a-zA-Z0-9_\\-\\.]{3,}$/', $username)) {
    $errors[] = "Username must be at least 3 characters and contain only letters, numbers, dashes, underscores, or dots.";
}

if ($password !== $passwordConfirmation) {
    $errors[] = "Passwords do not match.";
}

$commonPasswords = ['password', '12345678', 'qwerty', 'letmein', 'welcome', 'abc123'];
if (strlen($password) < 8) {
    $errors[] = "Password must be at least 8 characters.";
}
if (!preg_match('/[A-Z]/', $password)) {
    $errors[] = "Password must contain at least one uppercase letter.";
}
if (!preg_match('/[a-z]/', $password)) {
    $errors[] = "Password must contain at least one lowercase letter.";
}
if (!preg_match('/\\d/', $password)) {
    $errors[] = "Password must contain at least one number.";
}
if (!preg_match('/[^A-Za-z0-9]/', $password)) {
    $errors[] = "Password must include at least one special character.";
}
if (stripos($password, $username) !== false || stripos($password, explode('@', $email)[0]) !== false) {
    $errors[] = "Password cannot contain your username or email handle.";
}
if (in_array(strtolower($password), $commonPasswords)) {
    $errors[] = "Password is too common. Choose something more unique.";
}

// 如果有错误，显示错误并停止
if (!empty($errors)) {
    echo '<div class="container my-5"><div class="alert alert-danger">';
    foreach ($errors as $error) {
        echo '<p>' . htmlspecialchars($error) . '</p>';
    }
    echo '<a href="register.php">Go back</a></div></div>';
    require_once('footer.php');
    exit();
}

// 检查 Email 或 Username 是否已存在
$checkStmt = $conn->prepare("SELECT user_id FROM Users WHERE email = ? OR username = ?");
$checkStmt->bind_param("ss", $email, $username);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    echo '<div class="container my-5"><div class="alert alert-danger">Email or Username already exists. <a href="register.php">Try again</a></div></div>';
    $checkStmt->close();
    require_once('footer.php');
    exit();
}
$checkStmt->close();

// 密码加密 (非常重要！)
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// 插入新用户
$insertStmt = $conn->prepare("INSERT INTO Users (username, email, password, role) VALUES (?, ?, ?, ?)");
$insertStmt->bind_param("ssss", $username, $email, $passwordHash, $accountType);

if ($insertStmt->execute()) {
    echo '<div class="container my-5"><div class="alert alert-success">Registration successful! You can now <a href="#" data-toggle="modal" data-target="#loginModal">Login</a>.</div></div>';
} else {
    echo '<div class="container my-5"><div class="alert alert-danger">Error: ' . $conn->error . '</div></div>';
}

$insertStmt->close();
$conn->close();

require_once('footer.php');
?>