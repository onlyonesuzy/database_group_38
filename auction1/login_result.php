<?php
session_start();
require_once('db_connect.php');

// Get input (trim to match registration)
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    header("Location: index.php?login_error=empty_fields");
    exit();
}

// Query user (case-insensitive email match)
$stmt = $conn->prepare("SELECT user_id, username, password, role FROM Users WHERE LOWER(email) = LOWER(?)");
if (!$stmt) {
    header("Location: index.php?login_error=db_error");
    exit();
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Login success: set session
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['account_type'] = $user['role'];
        
        header("Location: index.php?login_success=1");
    } else {
        header("Location: index.php?login_error=wrong_password");
    }
} else {
    header("Location: index.php?login_error=user_not_found");
}

$stmt->close();
$conn->close();
exit();
?>
