<?php
include 'conn.php';
include 'functions.php';

$type = isset($_REQUEST['type']) ? strval($_REQUEST['type']) : 'all';
$followup_days=get_config($conn,'followup_days');
$followup_importance_operators=get_config($conn,'followup_importance_operators');
$followup_importance=get_config($conn,'followup_importance');

if ($type=='all'){
    $sql="SELECT * FROM customers";
} elseif ($type=='followup'){
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
        )";
} elseif ($type=='valid'){
    $sql="SELECT * FROM customers WHERE valid>=1 and last_checked_date is not null";
} elseif ($type=='invalid'){
    $sql="SELECT * FROM customers WHERE valid=0";
} else {
    die("Unknow export type.");
}

$items = array();
$rs = mysqli_query($conn,$sql); 
while($row = mysqli_fetch_object($rs)){
    array_push($items,
        array(
            (!$row->name and $type=='followup') ? 'Sir' : $row->name,
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
mysqli_close($conn);
if ($type=='followup'){
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
}else{
    $headerlist=array(
        '联系人',
        '邮箱',
        '客户评级',
        '公司',
        '国家',
        '地址',
        '电话',
        '网站',
        '备注',
        '最近联系时间',
        '有效性'
    );
}

csv_export($items, $headerlist, "export_{$type}");