<?php
ob_start();
session_start();

function send($arr){
    ob_end_clean();
    while(ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

set_error_handler(function($no,$str){ send(['success'=>false,'message'=>"PHP Error: $str"]); });
set_exception_handler(function($e){ send(['success'=>false,'message'=>$e->getMessage()]); });

if(empty($_SESSION['user_id'])) send(['success'=>false,'message'=>'Not logged in']);
if(($_SESSION['role']??'manager')!=='owner') send(['success'=>false,'message'=>'Owner only: managers cannot delete transactions']);

require_once __DIR__.'/db.php';

if(!isset($conn)||$conn->connect_error) send(['success'=>false,'message'=>'DB connection failed']);

function del($conn,$id){
    $id=(int)$id;
    if($id<=0) return false;
    $a=$conn->prepare("DELETE FROM sale_items WHERE sale_id=?");
    if($a){$a->bind_param('i',$id);$a->execute();$a->close();}
    $b=$conn->prepare("DELETE FROM sales WHERE id=?");
    if(!$b) return false;
    $b->bind_param('i',$id);
    $b->execute();
    $ok=$b->affected_rows>0;
    $b->close();
    return $ok;
}


if(!empty($_POST['ids'])){
    $ids=json_decode($_POST['ids'],true);
    if(!is_array($ids)||!count($ids)) send(['success'=>false,'message'=>'Empty IDs']);
    $ids=array_filter(array_map('intval',$ids),fn($v)=>$v>0);
    $n=0;
    foreach($ids as $id) if(del($conn,$id)) $n++;
    $conn->close();
    send(['success'=>true,'message'=>"$n transaction(s) deleted."]);
}


if(!empty($_POST['id'])){
    $id=(int)$_POST['id'];
    if($id<=0) send(['success'=>false,'message'=>'Bad ID']);
    $ok=del($conn,$id);
    $conn->close();
    send($ok
        ? ['success'=>true,'message'=>'Deleted successfully.']
        : ['success'=>false,'message'=>"ID $id not found."]);
}

send(['success'=>false,'message'=>'No id or ids posted']);