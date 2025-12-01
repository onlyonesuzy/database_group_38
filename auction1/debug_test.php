<?php
// debug_test.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "1. 开始测试... <br>";

// 测试数据库连接文件
require_once('db_connect.php');
echo "2. db_connect.php 没有问题! <br>";

// 测试 Header 文件 (最可能出问题的地方)
require_once('header.php');
echo "3. header.php 没有问题! <br>";

echo "4. 如果你看到这句话，说明基础文件都没问题。";
?>