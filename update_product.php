<?php
ob_start(); session_start();
function sendJson($a){ob_end_clean();header('Content-Type: application/json');echo json_encode($a);exit;}
if(empty($_SESSION['user_id'])) sendJson(['success'=>false,'message'=>'Not logged in']);
if(($_SESSION['role']??'manager')!=='owner') sendJson(['success'=>false,'message'=>'Owner only: cannot edit products']);
include 'db.php';
$id=(int)($_POST['product_id']??0);
$catId=(int)($_POST['category_id']??0);
$desc=trim($_POST['description']??'');
$unit=trim($_POST['unit']??'');
$code=trim($_POST['code']??'');
$cost=(float)($_POST['unit_cost']??0);
$price=(float)($_POST['selling_price']??0);
$qty=(int)($_POST['initial_quantity']??0);
$thresh=(int)($_POST['reorder_threshold']??5);
if(!$id||!$catId||!$desc||!$unit) sendJson(['success'=>false,'message'=>'Missing required fields.']);
$stmt=$conn->prepare("UPDATE products SET category_id=?,description=?,unit=?,code=?,unit_cost=?,selling_price=?,initial_quantity=?,reorder_threshold=? WHERE product_id=?");
$stmt->bind_param('isssddi i i',$catId,$desc,$unit,$code,$cost,$price,$qty,$thresh,$id);
$stmt->close();
$stmt=$conn->prepare("UPDATE products SET category_id=?,description=?,unit=?,code=?,unit_cost=?,selling_price=?,initial_quantity=?,reorder_threshold=? WHERE product_id=?");
$stmt->bind_param('isssddiii',$catId,$desc,$unit,$code,$cost,$price,$qty,$thresh,$id);
if($stmt->execute()&&$stmt->affected_rows>=0) sendJson(['success'=>true,'message'=>"Product '$desc' updated."]);
else sendJson(['success'=>false,'message'=>'Update failed: '.$conn->error]);