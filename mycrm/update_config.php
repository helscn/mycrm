<?php
include 'conn.php';

$followup_days = intval($_REQUEST['followup_days']);
$followup_importance_operators = mysqli_escape_string($conn,strval($_REQUEST['followup_importance_operators']));
$followup_importance = intval($_REQUEST['followup_importance']);
$theme = mysqli_escape_string($conn,strval($_REQUEST['theme']));

if($followup_days){
	$sql= "update config set value=$followup_days where parameter='followup_days'";
	mysqli_query($conn,$sql);
}
if($followup_importance_operators){
	$sql= "update config set value='$followup_importance_operators' where parameter='followup_importance_operators'";
	mysqli_query($conn,$sql);
}
if($followup_importance){
	$sql= "update config set value=$followup_importance where parameter='followup_importance'";
	mysqli_query($conn,$sql);
}
if($theme){
	$sql= "update config set value='$theme' where parameter='theme'";
	mysqli_query($conn,$sql);
}
echo 1;

// $sql= "select parameter,value from config";
// $items=array();
// mysqli_query($conn,$sql);
// $rs = mysqli_query($conn,$sql); 
// while($row = mysqli_fetch_object($rs)){
// 	array_push($items, $row);
// }
// mysqli_close($conn);
// echo json_encode($items);
?>