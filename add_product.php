<?php
ob_start(); session_start();
function sendJson($a){ob_end_clean();header('Content-Type: application/json');echo json_encode($a);exit;}
if(empty($_SESSION['user_id'])) sendJson(['success'=>false,'message'=>'Not logged in']);
if(($_SESSION['role']??'manager')!=='owner') sendJson(['success'=>false,'message'=>'Owner only: cannot add products']);
include 'db.php';
$catId=(int)($_POST['category_id']??0);
$desc=trim($_POST['description']??'');
$unit=trim($_POST['unit']??'');
$code=trim($_POST['code']??'');
$cost=(float)($_POST['unit_cost']??0);
$price=(float)($_POST['selling_price']??0);
$qty=(int)($_POST['initial_quantity']??0);
$thresh=(int)($_POST['reorder_threshold']??5);
if(!$catId||!$desc||!$unit) sendJson(['success'=>false,'message'=>'Category, description and unit are required.']);
$stmt=$conn->prepare("INSERT INTO products (category_id,description,unit,code,unit_cost,selling_price,initial_quantity,reorder_threshold) VALUES (?,?,?,?,?,?,?,?)");
$stmt->bind_param('isssddi i',$catId,$desc,$unit,$code,$cost,$price,$qty,$thresh);
$stmt->close();
$stmt=$conn->prepare("INSERT INTO products (category_id,description,unit,code,unit_cost,selling_price,initial_quantity,reorder_threshold) VALUES (?,?,?,?,?,?,?,?)");
$stmt->bind_param('isssddi',$catId,$desc,$unit,$code,$cost,$price,$qty,$thresh);
if($stmt->execute()) sendJson(['success'=>true,'message'=>"Product '$desc' added."]);
else sendJson(['success'=>false,'message'=>'Failed: '.$conn->error]);