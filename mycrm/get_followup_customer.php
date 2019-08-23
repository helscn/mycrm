<?php
include 'conn.php';
include 'functions.php';

$page = isset($_POST['page']) ? intval($_POST['page']) : 1;
$rows = isset($_POST['rows']) ? intval($_POST['rows']) : 10;
$sort = isset($_POST['sort']) ? strval($_POST['sort']) : 'email';
$order = isset($_POST['order']) ? strval($_POST['order']) : 'asc';
$offset = ($page-1)*$rows;
 
$followup_days=get_config($conn,'followup_days');
$followup_importance_operators=get_config($conn,'followup_importance_operators');
$followup_importance=get_config($conn,'followup_importance');

$sql="SELECT count(*) FROM customers 
WHERE(datediff(CURRENT_TIMESTAMP,last_contact_date)>$followup_days
	OR last_contact_date is NULL) 
	AND valid>=1 
	AND importance $followup_importance_operators $followup_importance
	AND ( company=''
		OR company IN (
			SELECT company FROM customers
			WHERE valid>=1
			GROUP BY company 
			HAVING 
				datediff(CURRENT_TIMESTAMP,MAX(last_contact_date))>$followup_days OR
				MAX(last_contact_date) is NULL
		)
	)";

$rs = mysqli_query($conn,$sql);
$row = mysqli_fetch_row($rs);
$result = array();
$result["total"] = $row[0];


$sql="SELECT * FROM customers 
	WHERE(datediff(CURRENT_TIMESTAMP,last_contact_date)>$followup_days
		OR last_contact_date is NULL) 
		AND valid>=1 
		AND importance $followup_importance_operators $followup_importance
		AND ( company=''
			OR company IN (
				SELECT company FROM customers
				WHERE valid>=1
				GROUP BY company 
				HAVING 
					datediff(CURRENT_TIMESTAMP,MAX(last_contact_date))>$followup_days OR
					MAX(last_contact_date) is NULL
			)
		)
	ORDER BY $sort $order LIMIT $offset,$rows	
";
	
$items = array();
$rs = mysqli_query($conn,$sql); 
while($row = mysqli_fetch_object($rs)){
	array_push($items, $row);
}
$result["rows"] = $items;
echo json_encode($result);
mysqli_close($conn);
?>