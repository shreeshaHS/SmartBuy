<?php
require_once __DIR__ . '/../../config/bootstrap.php';
if(!isPost()) json_response(['ok'=>false,'error'=>'POST required'],405);
require_csrf_json();
unset($_SESSION['coupon']);
json_response(['ok'=>true]);
