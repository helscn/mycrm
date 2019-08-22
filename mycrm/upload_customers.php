<?php

// 获取文件资源
if (empty($_FILES['csv_file']) && $_FILES['csv_file']['error'] != 0) {
    die('文件上传错误！');
} else {
    $file_name = $_FILES['csv_file']['tmp_name'];
}

if($file_name == '')
{
    die("请选择要上传的csv文件！");
}

// 以只读的方式打开文件
$handle = fopen($file_name, 'r');
if($handle === FALSE) die("打开文件资源失败");

// setlocale() 函数设置地区信息（地域信息）。
@setlocale(LC_ALL, 'zh_CN');

// CSV对应的字段名
include 'conn.php';

$csv_header = array('name','email','importance','company','country','address','phone','website','comment','last_contact_date','valid');
$count=0;

while(($data = fgetcsv($handle)) !== FALSE)
{
    $row = array();
    foreach ($csv_header as $k => $v)
    {
        $row[$v] = mysqli_escape_string($conn,trim(iconv('gbk','utf-8', ltrim($data[$k], '`'))));
    }

    $vals='';

    //当CSV记录中的字段不为空时更新已有记录
    if($row['name']!=''){
        $row['name']=$row['name'];
        $vals.=",name='{$row['name']}'";
    }
    if($row['company']!=''){
        $row['company']=$row['company'];
        $vals.=",company='{$row['company']}'";
    }
    if($row['country']!=''){
        $row['country']=$row['country'];
        $vals.=",country='{$row['country']}'";
    }
    if($row['address']!=''){
        $row['address']=$row['address'];
        $vals.=",address='{$row['address']}'";
    }
    if($row['phone']!=''){
        $row['phone']=$row['phone'];
        $vals.=",phone='{$row['phone']}'";
    }
    if($row['website']!=''){
        $row['website']=$row['website'];
        $vals.=",website='{$row['website']}'";
    }
    if($row['comment']!=''){
        $row['comment']=$row['comment'];
        $vals.=",comment='{$row['comment']}'";
    }

    //importance不为空时更新已有记录的importance
    if($row['importance']!=''){
        $row['importance']=intval($row['importance']);
        $vals.=",importance={$row['importance']}";
    }else{
        $row['importance']=0;   //importance默认为0
    }

    //valid不为空时更新已有记录的valid
    if($row['valid']!=''){
        $row['valid']=intval($row['valid']);
        $vals.=",valid={$row['valid']}";
    }else{
        $row['valid']=1;    //valid默认为1
    }

    preg_match_all( "/[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})/i",$row['email'], $matches);
    foreach ($matches[0] as $mail){
        $sql="
        INSERT INTO customers (name,email,importance,company,country,address,phone,website,comment,valid)
            VALUES ('{$row['name']}','$mail',{$row['importance']},'{$row['company']}','{$row['country']}','{$row['address']}','{$row['phone']}','{$row['website']}','{$row['comment']}',{$row['valid']})
            ON DUPLICATE KEY UPDATE email='{$row['email']}'
        ";
        $sql.=$vals;
        mysqli_query($conn,$sql);
        $count+=1;
    }
}

// 关闭资源
fclose($handle);
mysqli_close($conn);
echo $count;