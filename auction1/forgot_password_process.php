<?php
session_start();
require_once('db_connect.php');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Cancel and start over
if ($action === 'cancel') {
    unset($_SESSION['reset_email'], $_SESSION['reset_code'], $_SESSION['reset_expires'], $_SESSION['reset_user_id'], $_SESSION['code_verified']);
    header('Location: forgot_password.php');
    exit();
}

// STEP 1: Send verification code
if ($action === 'send_code') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: forgot_password.php?error=invalid_email');
        exit();
    }
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT user_id, username FROM Users WHERE LOWER(email) = LOWER(?)");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        header('Location: forgot_password.php?error=user_not_found');
        exit();
    }
    
    // Generate 6-digit code
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Store in session
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_code'] = $code;
    $_SESSION['reset_expires'] = $expires;
    $_SESSION['reset_user_id'] = $user['user_id'];
    unset($_SESSION['code_verified']);
    
    header('Location: forgot_password.php');
    exit();
}

// STEP 2: Verify code
if ($action === 'verify_code') {
    $entered_code = trim($_POST['code'] ?? '');
    
    if (!isset($_SESSION['reset_code']) || !isset($_SESSION['reset_expires'])) {
        header('Location: forgot_password.php');
        exit();
    }
    
    // Check if expired
    $expires = new DateTime($_SESSION['reset_expires']);
    $now = new DateTime();
    
    if ($now >= $expires) {
        unset($_SESSION['reset_email'], $_SESSION['reset_code'], $_SESSION['reset_expires'], $_SESSION['reset_user_id']);
        header('Location: forgot_password.php?error=expired');
        exit();
    }
    
    // Verify code
    if ($entered_code !== $_SESSION['reset_code']) {
        header('Location: forgot_password.php?error=wrong_code');
        exit();
    }
    
    // Code is correct - move to step 3
    $_SESSION['code_verified'] = true;
    header('Location: forgot_password.php');
    exit();
}

// STEP 3: Reset password
if ($action === 'reset_password') {
    if (!isset($_SESSION['code_verified']) || $_SESSION['code_verified'] !== true || !isset($_SESSION['reset_user_id'])) {
        header('Location: forgot_password.php');
        exit();
    }
    
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate passwords match
    if ($password !== $confirm_password) {
        header('Location: forgot_password.php?error=mismatch');
        exit();
    }
    
    // Validate password strength
    if (strlen($password) < 6) {
        header('Location: forgot_password.php?error=weak');
        exit();
    }
    
    // Hash and update password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $user_id = $_SESSION['reset_user_id'];
    
    $update_stmt = $conn->prepare("UPDATE Users SET password = ? WHERE user_id = ?");
    $update_stmt->bind_param('si', $hashed_password, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Clear all reset session data
    unset($_SESSION['reset_email'], $_SESSION['reset_code'], $_SESSION['reset_expires'], $_SESSION['reset_user_id'], $_SESSION['code_verified']);
    
    header('Location: forgot_password.php?success=password_reset');
    exit();
}

// Default: redirect to forgot password page
header('Location: forgot_password.php');
exit();
?>
