<?php

$para = isset($_REQUEST['para']) ? strval($_REQUEST['para']) : NULL;

include 'conn.php';
if(!$para){
	$sql= "select parameter,value from config";
	$items=array();
	mysqli_query($conn,$sql);
	$rs = mysqli_query($conn,$sql); 
	while($row = mysqli_fetch_object($rs)){
		array_push($items, $row);
	}
	echo json_encode($items);
	mysqli_close($conn);
}else{
	$sql = "select parameter,value from config where parameter='$para'";
	mysqli_query($conn,$sql);
	$rs = mysqli_query($conn,$sql); 
	$row = mysqli_fetch_object($rs);
	if($row){
		echo json_encode(array($para=>($row->value)));
	}else{
		echo '{}';
	}
	mysqli_close($conn);
}
?>