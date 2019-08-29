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
$last_contact_date = mysqli_escape_string($conn,$_REQUEST['last_contact_date']);
$last_checked_log = mysqli_escape_string($conn,$_REQUEST['last_checked_log']);
$comment = mysqli_escape_string($conn,$_REQUEST['comment']);
$valid = intval($_REQUEST['valid']);

// 获取当前修改ID记录修改前的旧邮箱名称
$sql = "SELECT email FROM customers WHERE id=$id";
$rs = mysqli_query($conn,$sql);
if($row = mysqli_fetch_row($rs)){
	$old_email=$row[0];

	// 将 msg_addrs 表中所有旧邮件地址替换为新邮件地址
	if ($old_email and $email){
		$sql="UPDATE msg_addrs SET address='$email' WHERE address='$old_email'";
		mysqli_query($conn,$sql);
	}
}

// 修改 customers 表中的客户记录
$sql = "UPDATE customers SET name='$name',email='$email',importance=$importance,company='$company',country='$country',address='$address',phone='$phone',website='$website',comment='$comment',last_checked_log='$last_checked_log',valid=$valid WHERE id=$id";
mysqli_query($conn,$sql);
echo json_encode(array(
	'id' => $id,
	'name' => stripslashes($name),
	'email' => stripslashes($email),
	'importance' => $importance,
	'company' => stripslashes($company),
	'country' => stripslashes($country),
	'address' => stripslashes($address),
	'phone' => stripslashes($phone),
	'website' => stripslashes($website),
	'last_contact_date' => $last_contact_date,
	'last_checked_log' => $last_checked_log,
	'comment' => stripslashes($comment),
	'valid' => $valid
));

mysqli_close($conn);
?>