<?php
session_start();
require_once('db_connect.php');

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate token
if (empty($token)) {
    header('Location: forgot_password.php');
    exit();
}

// Validate passwords match
if ($password !== $confirm_password) {
    header('Location: reset_password.php?token=' . urlencode($token) . '&error=mismatch');
    exit();
}

// Validate password strength
if (strlen($password) < 6) {
    header('Location: reset_password.php?token=' . urlencode($token) . '&error=weak');
    exit();
}

// Verify token and get user
$stmt = $conn->prepare("SELECT user_id, reset_token_expires FROM Users WHERE reset_token = ?");
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: reset_password.php?token=' . urlencode($token));
    exit();
}

// Check if token has expired
$expires = new DateTime($user['reset_token_expires']);
$now = new DateTime();

if ($now >= $expires) {
    header('Location: reset_password.php?token=' . urlencode($token));
    exit();
}

// Hash new password and update
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$update_stmt = $conn->prepare("UPDATE Users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE user_id = ?");
$update_stmt->bind_param('si', $hashed_password, $user['user_id']);
$update_stmt->execute();
$update_stmt->close();

$conn->close();

// Redirect to success page
header('Location: reset_password.php?success=password_reset');
exit();
?>

