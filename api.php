<?php
require __DIR__.'/config.php';
$action=$_GET['action']??'';
$input=json_decode(file_get_contents('php://input'),true)??[];
$mutating=['register','login','logout','create_order','save_product','delete_product','update_order','add_review','upload_image'];
if(in_array($action,$mutating,true)) verify_csrf();
switch($action){
case 'upload_image':
require_admin();
if(!isset($_FILES['image']) || $_FILES['image']['error']!==UPLOAD_ERR_OK) json_out(['error'=>'Choose an image file to upload.'],422);
$file=$_FILES['image']; if($file['size']>5*1024*1024) json_out(['error'=>'Image must be 5 MB or smaller.'],422);
$fi=new finfo(FILEINFO_MIME_TYPE); $mime=$fi->file($file['tmp_name']); $types=['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
if(!isset($types[$mime])) json_out(['error'=>'Use a JPG, PNG, GIF or WEBP image.'],422);
$dir=__DIR__.'/uploads/products'; if(!is_dir($dir)&&!mkdir($dir,0755,true)) json_out(['error'=>'Could not create upload folder.'],500);
$name=bin2hex(random_bytes(16)).'.'.$types[$mime]; if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)) json_out(['error'=>'Image upload failed.'],500);
json_out(['ok'=>true,'image_url'=>'uploads/products/'.$name]);
case 'csrf': json_out(['token'=>csrf_token()]);
case 'products':
$q=trim($_GET['q']??''); $cat=trim($_GET['category']??''); $params=[];
$sql="SELECT p.*,COALESCE(AVG(r.rating),0) rating,COUNT(r.id) review_count FROM products p LEFT JOIN reviews r ON r.product_id=p.id WHERE p.active=1";
if($q!==''){ $sql.=" AND (p.title LIKE ? OR p.brand LIKE ? OR p.category LIKE ?)"; $params=array_merge($params,["%$q%","%$q%","%$q%"]); }
if($cat!==''&&$cat!=='All'){ $sql.=" AND p.category=?"; $params[]=$cat; }
$sql.=" GROUP BY p.id ORDER BY p.id"; $st=$pdo->prepare($sql);$st->execute($params);json_out($st->fetchAll());
case 'categories': json_out($pdo->query("SELECT category,COUNT(*) total FROM products WHERE active=1 GROUP BY category ORDER BY category")->fetchAll());
case 'me': json_out(['user'=>$_SESSION['user']??null,'csrf'=>csrf_token()]);
case 'register':
$email=strtolower(trim($input['email']??''));$name=trim($input['name']??'');$pw=(string)($input['password']??'');
if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pw)<8)json_out(['error'=>'Enter a name, valid email and password of at least 8 characters.'],422);
try{$st=$pdo->prepare("INSERT INTO users(name,email,password_hash) VALUES(?,?,?)");$st->execute([$name,$email,password_hash($pw,PASSWORD_DEFAULT)]);}catch(PDOException $e){json_out(['error'=>'Email already registered.'],409);}
session_regenerate_id(true);$_SESSION['user']=['id'=>(int)$pdo->lastInsertId(),'name'=>$name,'email'=>$email,'role'=>'customer'];json_out(['user'=>$_SESSION['user'],'csrf'=>csrf_token()]);
case 'login':
$email=strtolower(trim($input['email']??''));$pw=(string)($input['password']??'');$st=$pdo->prepare("SELECT id,name,email,password_hash,role FROM users WHERE email=?");$st->execute([$email]);$u=$st->fetch();
if(!$u||!password_verify($pw,$u['password_hash']))json_out(['error'=>'Incorrect email or password.'],401);
session_regenerate_id(true);$_SESSION['user']=['id'=>(int)$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']];json_out(['user'=>$_SESSION['user'],'csrf'=>csrf_token()]);
case 'logout':$_SESSION=[];session_destroy();json_out(['ok'=>true]);
case 'create_order':
$items=$input['items']??[];$name=trim($input['name']??'');$email=strtolower(trim($input['email']??''));$phone=trim($input['phone']??'');$address=trim($input['address']??'');
if(!is_array($items)||!count($items)||count($items)>100||!$name||!filter_var($email,FILTER_VALIDATE_EMAIL)||!$phone||!$address)json_out(['error'=>'Check your cart and complete all delivery details.'],422);
$qtys=[];foreach($items as $it){$id=(int)($it['id']??0);$q=(int)($it['quantity']??0);if($id<1||$q<1||$q>20)json_out(['error'=>'Invalid item quantity. Maximum 20 per product.'],422);$qtys[$id]=($qtys[$id]??0)+$q;if($qtys[$id]>20)json_out(['error'=>'Maximum 20 per product.'],422);}
try{$pdo->beginTransaction();$subtotal=0;$lines=[];
foreach($qtys as $id=>$q){$st=$pdo->prepare("SELECT id,title,price,stock FROM products WHERE id=? AND active=1 FOR UPDATE");$st->execute([$id]);$p=$st->fetch();if(!$p||(int)$p['stock']<$q)throw new RuntimeException('A product is out of stock or has insufficient stock.');$subtotal+=(float)$p['price']*$q;$lines[]=[$p,$q];}
$delivery=$subtotal>=50000?0:350;$total=$subtotal+$delivery;$code='EARN-'.strtoupper(bin2hex(random_bytes(4)));$uid=$_SESSION['user']['id']??null;
$st=$pdo->prepare("INSERT INTO orders(order_code,user_id,customer_name,email,phone,address,payment_method,subtotal,delivery_fee,total) VALUES(?,?,?,?,?,?,?,?,?,?)");$st->execute([$code,$uid,$name,$email,$phone,$address,'Cash on delivery',$subtotal,$delivery,$total]);$oid=(int)$pdo->lastInsertId();
foreach($lines as [$p,$q]){$pdo->prepare("INSERT INTO order_items(order_id,product_id,product_title,price,quantity) VALUES(?,?,?,?,?)")->execute([$oid,$p['id'],$p['title'],$p['price'],$q]);$pdo->prepare("UPDATE products SET stock=stock-? WHERE id=?")->execute([$q,$p['id']]);}
$pdo->commit();json_out(['ok'=>true,'order_code'=>$code,'subtotal'=>$subtotal,'delivery_fee'=>$delivery,'total'=>$total,'status'=>'Pending']);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();json_out(['error'=>$e instanceof RuntimeException?$e->getMessage():'Could not save order.'],422);}
case 'my_orders':
if(empty($_SESSION['user']))json_out(['error'=>'Sign in to view orders.'],401);
$st=$pdo->prepare("SELECT order_code,status,total,created_at FROM orders WHERE user_id=? ORDER BY id DESC");$st->execute([$_SESSION['user']['id']]);json_out($st->fetchAll());
case 'add_review':
if(empty($_SESSION['user']))json_out(['error'=>'Sign in to leave a review.'],401);
$id=(int)($input['product_id']??0);$rating=(int)($input['rating']??0);$txt=trim($input['review_text']??'');
if($id<1||$rating<1||$rating>5||$txt===''||mb_strlen($txt)>1000)json_out(['error'=>'Enter a rating from 1 to 5 and a review under 1000 characters.'],422);
$st=$pdo->prepare("SELECT id FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_id=? AND o.user_id=? AND o.status='Delivered' LIMIT 1");$st->execute([$id,$_SESSION['user']['id']]);if(!$st->fetch())json_out(['error'=>'Reviews are available after a delivered purchase.'],403);
$st=$pdo->prepare("INSERT INTO reviews(product_id,user_id,rating,review_text) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating),review_text=VALUES(review_text)");$st->execute([$id,$_SESSION['user']['id'],$rating,$txt]);json_out(['ok'=>true]);
case 'admin_stats': require_admin();$products=(int)$pdo->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn();$orders=(int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();$pending=(int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='Pending'")->fetchColumn();$revenue=(float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN('Confirmed','Shipped','Delivered')")->fetchColumn();$daily=$pdo->query("SELECT DATE(created_at) day,SUM(total) revenue FROM orders WHERE status IN('Confirmed','Shipped','Delivered') AND created_at>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY day")->fetchAll();json_out(compact('products','orders','pending','revenue','daily'));
case 'admin_products': require_admin();json_out($pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll());
case 'admin_orders': require_admin();json_out($pdo->query("SELECT o.*,GROUP_CONCAT(CONCAT(oi.product_title,' ×',oi.quantity) SEPARATOR ', ') items FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id GROUP BY o.id ORDER BY o.id DESC")->fetchAll());
case 'save_product':
require_admin();$id=(int)($input['id']??0);$title=trim($input['title']??'');$cat=trim($input['category']??'');$brand=trim($input['brand']??'');$price=(float)($input['price']??-1);$stock=(int)($input['stock']??-1);$img=trim($input['image_url']??'');$desc=trim($input['description']??'');
if(!$title||!$cat||!$brand||$price<=0||$stock<0||($img!==''&&!filter_var($img,FILTER_VALIDATE_URL)))json_out(['error'=>'Enter valid product details, price, stock and image URL.'],422);
if($id){$st=$pdo->prepare("UPDATE products SET title=?,category=?,brand=?,price=?,stock=?,image_url=?,description=? WHERE id=?");$st->execute([$title,$cat,$brand,$price,$stock,$img,$desc,$id]);}else{$st=$pdo->prepare("INSERT INTO products(title,category,brand,price,stock,image_url,description) VALUES(?,?,?,?,?,?,?)");$st->execute([$title,$cat,$brand,$price,$stock,$img,$desc]);}json_out(['ok'=>true]);
case 'delete_product':require_admin();$id=(int)($input['id']??0);$pdo->prepare("UPDATE products SET active=0 WHERE id=?")->execute([$id]);json_out(['ok'=>true]);
case 'update_order':
require_admin();$id=(int)($input['id']??0);$status=$input['status']??'';if(!in_array($status,['Pending','Confirmed','Shipped','Delivered','Cancelled'],true))json_out(['error'=>'Invalid order status.'],422);$pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$status,$id]);json_out(['ok'=>true]);
default:json_out(['error'=>'Unknown API action.'],404);
}
