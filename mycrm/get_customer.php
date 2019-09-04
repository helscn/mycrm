<?php
include 'conn.php';

$searchType = isset($_REQUEST['searchType']) ? strval($_REQUEST['searchType']) : 'email';
$searchValue = isset($_REQUEST['searchValue']) ? strval($_REQUEST['searchValue']) : '%';
$onlyValid = isset($_REQUEST['onlyValid']) ? strval($_REQUEST['onlyValid']) : 'false';
$type = isset($_REQUEST['type']) ? strval($_REQUEST['type']) : 'json';

// 是否只显示有效数据或待确认数据
if($onlyValid=='true'){
	$onlyValid='and valid>=1';
}else{
	$onlyValid='';
}

// 在搜索值前后增加%以实现模糊查找
if($searchValue != '%'){
	$searchValue='%' . $searchValue . '%';
	$searchValue=str_replace('%%','%',$searchValue);
}

// 如果筛选类型为名字、公司名或地址时，去除比较字符串中的空格
if($searchType=='name' or $searchType=='company' or $searchType=='address'){
	$searchType="replace($searchType,' ','')";
	$searchValue=str_replace(' ','',$searchValue);
}
if($searchType=='importance' and preg_match("/\d/",$searchValue)){
	$searchValue=str_replace('%','',$searchValue);
	if(!preg_match("/^([><]?=|[><])[0-5]$/",$searchValue)){
		$searchType.='=';
	}
}else{
	$searchType.=' like ';
	$searchValue="'$searchValue'";
}

if($type=='json'){
	$page = isset($_POST['page']) ? intval($_POST['page']) : 1;
	$rows = isset($_POST['rows']) ? intval($_POST['rows']) : 10;
	$sort = isset($_POST['sort']) ? strval($_POST['sort']) : 'email';
	$order = isset($_POST['order']) ? strval($_POST['order']) : 'asc';
	$offset = ($page-1)*$rows;
	
	$result = array();
	$rs = mysqli_query($conn,"select count(*) from customers where $searchType $searchValue $onlyValid");
	$row = mysqli_fetch_row($rs);
	$result["total"] = $row[0];

	$items = array();
	$sql="select * from customers  where $searchType $searchValue $onlyValid order by $sort $order limit $offset,$rows";
	$rs = mysqli_query($conn,$sql); 
	while($row = mysqli_fetch_object($rs)){
		array_push($items, $row);
	}
	$result["rows"] = $items;
	echo json_encode($result);
}else if($type=='csv'){
	$items = array();
	$sql="select * from customers  where $searchType $searchValue $onlyValid";
	$rs = mysqli_query($conn,$sql); 
	while($row = mysqli_fetch_object($rs)){
		array_push($items,
			array(
				(!$row->name) ? 'Sir' : $row->name,
				$row->email,
				$row->importance,
				$row->company,
				$row->country,
				$row->address,
				$row->phone,
				$row->website,
				$row->comment,
				$row->last_contact_date,
				$row->valid
			)
		);
	}
	$headerlist=array(
        'Name',
        'Email',
        'Importance',
        'Company',
        'Country',
        'Address',
        'Phone',
        'Website',
        'Comment',
        'Last_contact_date',
        'Valid'
	);
	include 'functions.php';
	csv_export($items, $headerlist, "export_filtered",'utf-8');
}
mysqli_close($conn);
?>