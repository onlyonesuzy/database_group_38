<?php
require_once("db_connect.php");
session_start();

if (!isset($_POST['functionname']) || !isset($_POST['arguments'])) {
  return;
}

// 提取参数 (前端传过来的是数组)
$item_id = $_POST['arguments'][0];
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo "error: not logged in";
    exit();
}

if ($_POST['functionname'] == "add_to_watchlist") {
    // 1. 添加关注
    // 使用 INSERT IGNORE 或检查存在性，这里因为我们在数据库设了 UNIQUE KEY，直接插就行
    // 如果重复插入会报错，但我们可以捕获或者忽略
    $stmt = $conn->prepare("INSERT IGNORE INTO Watchlist (user_id, auction_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $item_id);
    
    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "failure";
    }
    $stmt->close();
}
else if ($_POST['functionname'] == "remove_from_watchlist") {
    // 2. 取消关注
    $stmt = $conn->prepare("DELETE FROM Watchlist WHERE user_id = ? AND auction_id = ?");
    $stmt->bind_param("ii", $user_id, $item_id);
    
    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "failure";
    }
    $stmt->close();
}

$conn->close();
?>