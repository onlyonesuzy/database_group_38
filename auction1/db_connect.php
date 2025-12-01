<?php
// Set default timezone (change this to match your local timezone)
date_default_timezone_set('Europe/London');

$servername = "localhost";
$username = "root";      // XAMPP 默认用户名
$password = "";          // XAMPP 默认密码通常为空
$dbname = "test"; // Database name

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

require_once(__DIR__ . '/schema_bootstrap.php');
bootstrapAuctionSchema($conn);
?>