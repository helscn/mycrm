<?php
// 数据库连接参数
// $conn = mysqli_connect('服务器:端口','数据库账号','数据库密码','数据库名字');
$conn = mysqli_connect('localhost:3306','root','root','my_crm');


if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn,'utf8');
?>