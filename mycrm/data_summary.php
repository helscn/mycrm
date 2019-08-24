<?php
include 'conn.php';

if($_REQUEST['type']=='importance'){
    $data=array();
    $type=array('无效客户','有效客户','待确认客户');
    $color=array('#ED561B','#50B432','#058DC7');
    for ($valid=0; $valid<=2; $valid++){
        $item=array();
        $item['name']=$type[$valid];
        $item['color']=$color[$valid];
        $count=array(0,0,0,0,0,0);
        $sql="SELECT importance,count(*) AS count FROM customers WHERE valid=$valid GROUP BY importance";
        $rs = mysqli_query($conn,$sql);
    
        while($row = mysqli_fetch_object($rs)){
            $count[$row->importance]=intval($row->count);
        }
        $item['data']=$count;
        array_push($data,$item);
    }
    echo json_encode($data);
}else if($_REQUEST['type']=='messages'){
    $series=array();
    $sender=array();
    $receive=array();
    $categories=array();
    $sql="SELECT date_format(date,'%YW%u') as week,sum(case when type='sendto' then 1 else 0 end) as sendto,sum(case when type='receive' then 1 else 0 end) as receive FROM messages GROUP BY week ORDER BY week LIMIT 0,53";
    $rs = mysqli_query($conn,$sql);
    while($row = mysqli_fetch_object($rs)){
        array_push($categories,$row->week);
        array_push($sender,intval($row->sendto));
        array_push($receive,intval($row->receive));
    }
    array_push($series,array("name"=>"发送邮件","color"=>"#058DC7","data"=>$sender));
    array_push($series,array("name"=>"接收邮件","color"=>"#50B432","data"=>$receive));
    $data=array("categories"=>$categories,"series"=>$series);
    echo json_encode($data);
}
mysqli_close($conn);