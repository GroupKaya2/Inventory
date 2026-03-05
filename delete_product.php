<?php
ob_start();
session_start();
function sendJson($a){ob_end_clean();header('Content-Type: application/json');echo json_encode($a);exit;}
if(empty($_SESSION['user_id'])) sendJson(['success'=>false,'message'=>'Not logged in']);
if(($_SESSION['role']??'manager')!=='owner') sendJson(['success'=>false,'message'=>'Owner only: cannot delete products']);

include 'db.php';
$id=(int)($_POST['product_id']??0);
if($id<=0) sendJson(['success'=>false,'message'=>'Invalid ID']);

$chk=$conn->prepare("SELECT COUNT(*) AS c FROM sale_items WHERE product_id=?");
$chk->bind_param('i',$id);
$chk->execute();
$cnt=$chk->get_result()->fetch_assoc()['c'];
$chk->close();
if($cnt>0) sendJson(['success'=>false,'message'=>'Cannot delete: product has existing sales records.']);

$stmt=$conn->prepare("DELETE FROM products WHERE product_id=?");
$stmt->bind_param('i',$id);
if($stmt->execute()&&$stmt->affected_rows>0)
    sendJson(['success'=>true,'message'=>'Deleted successfully.']);
else
    sendJson(['success'=>false,'message'=>'Product not found.']);