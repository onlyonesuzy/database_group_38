<?php
session_start();
require_once('db_connect.php');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: myaccount.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$password = $_POST['password'] ?? '';

if (empty($password)) {
    echo '<div style="text-align:center; margin-top:50px;">Password is required to delete your account.</div>';
    header("refresh:2;url=myaccount.php");
    exit();
}

// Verify password
$pwd_stmt = $conn->prepare("SELECT password FROM Users WHERE user_id = ?");
$pwd_stmt->bind_param("i", $user_id);
$pwd_stmt->execute();
$result = $pwd_stmt->get_result();
$user = $result->fetch_assoc();
$pwd_stmt->close();

if (!$user || !password_verify($password, $user['password'])) {
    echo '<div style="text-align:center; margin-top:50px;">Incorrect password. Account deletion cancelled.</div>';
    header("refresh:2;url=myaccount.php");
    exit();
}

// Delete the user account (cascade will handle related records)
$delete_stmt = $conn->prepare("DELETE FROM Users WHERE user_id = ?");
$delete_stmt->bind_param("i", $user_id);

if ($delete_stmt->execute()) {
    // Clear session
    session_unset();
    session_destroy();
    
    echo '<div style="text-align:center; margin-top:50px;">';
    echo '<h3>Account Deleted</h3>';
    echo '<p>Your account has been permanently deleted.</p>';
    echo '<p>Redirecting to homepage...</p>';
    echo '</div>';
    header("refresh:3;url=index.php");
} else {
    echo '<div style="text-align:center; margin-top:50px;">Failed to delete account. Please try again.</div>';
    header("refresh:2;url=myaccount.php");
}

$delete_stmt->close();
$conn->close();
?>

