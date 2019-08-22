<?php
include 'conn.php';

$id = intval($_REQUEST['id']);
$name = mysqli_escape_string($conn,$_REQUEST['name']);
$email = mysqli_escape_string($conn,$_REQUEST['email']);
$importance = intval($_REQUEST['importance']);
$company = mysqli_escape_string($conn,$_REQUEST['company']);
$country = mysqli_escape_string($conn,$_REQUEST['country']);
$address = mysqli_escape_string($conn,$_REQUEST['address']);
$phone = mysqli_escape_string($conn,$_REQUEST['phone']);
$website = mysqli_escape_string($conn,$_REQUEST['website']);
$last_contact_date= mysqli_escape_string($conn,$_REQUEST['last_contact_date']);
$comment = mysqli_escape_string($conn,$_REQUEST['comment']);
$valid = isset($_REQUEST['valid']) ? intval($_REQUEST['valid']) : 1;

$sql = "insert into customers(name,email,importance,company,country,address,phone,website,comment,valid) values ('$name','$email',$importance,'$company','$country','$address','$phone','$website','$comment',$valid)";
$result=mysqli_query($conn,$sql);
if($result===false ){
    echo "无法添加客户记录至数据库中，请检查邮箱地址是否有重复记录：<br><br>".mysqli_error($conn);
}else{
	echo 1;
}
mysqli_close($conn);
?>