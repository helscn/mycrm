
<?php
include 'conn.php';

$page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
$rows = isset($_REQUEST['rows']) ? intval($_REQUEST['rows']) : 10;
$offset = ($page-1)*$rows;
$email = isset($_REQUEST['email']) ? mysqli_escape_string($conn,strval($_REQUEST['email'])) : NULL;
$type = isset($_REQUEST['type']) ? strval($_REQUEST['type']) : 'messages';  // messages or count

if($type=='count'){
    if($email){
        $rs = mysqli_query($conn,"SELECT count(*) FROM messages,msg_addrs WHERE messages.id=msg_addrs.msg_id AND address='$email'");
    }else{
        $rs = mysqli_query($conn,"SELECT count(*) FROM messages WHERE type='system'");
    }
    $row = mysqli_fetch_row($rs);
    echo $row[0];
}else{
    if($email){
        $sql="SELECT sender,date,type,subject,content FROM messages,msg_addrs WHERE messages.id=msg_addrs.msg_id AND address='$email' ORDER BY date DESC LIMIT $offset,$rows";
    }else{
        $sql="SELECT sender,date,type,subject,content FROM messages WHERE type='system' ORDER BY date DESC LIMIT $offset,$rows";
    }

    echo '
    <div class="my_msg_list">
        <ul class="msg_list_ul">
    ';

    $rs = mysqli_query($conn,$sql);
    while($row = mysqli_fetch_row($rs)){
        $sender=htmlentities($row[0],ENT_QUOTES);
        $date=$row[1];
        $msg_type=$row[2];
        if($msg_type=='receive'){
            $addr=<<<EOF
                <a href="mailto:$email">$sender($email)</a>
EOF;
        }else{
            $addr=$sender;
        }

        $subject=htmlentities($row[3],ENT_QUOTES);
        $content=$row[4];
        $msg = <<<EOF
        <li class="msg_list_ul_li">
        <span class="msg_type_{$msg_type}">
            $addr
        </span>
        <span class="msg_info_box">
            <span class="msg_title">
                $subject
            </span>
        </span>
        <div class="msg_right">
            <em class="data-time">
                $date
            </em>
        </div>
        <div class="msg_content">
            $content
        </div>
        </li>
EOF;
        echo $msg;
    }
}
mysqli_close($conn);