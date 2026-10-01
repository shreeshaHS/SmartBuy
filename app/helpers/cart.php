<?php
// Session cart helper

function cart_init(){
  if(session_status() === PHP_SESSION_NONE) session_start();
  if(!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
}

function cart_items(){
  cart_init();
  return $_SESSION['cart'];
}

function cart_add($productId, $qty = 1){
  cart_init();
  $productId = (int)$productId;
  $qty = max(1, (int)$qty);

  if(isset($_SESSION['cart'][$productId])){
    $_SESSION['cart'][$productId] += $qty;
  } else {
    $_SESSION['cart'][$productId] = $qty;
  }
}

function cart_update($productId, $qty){
  cart_init();
  $productId = (int)$productId;
  $qty = (int)$qty;

  if($qty <= 0){
    unset($_SESSION['cart'][$productId]);
  } else {
    $_SESSION['cart'][$productId] = $qty;
  }
}

function cart_remove($productId){
  cart_init();
  unset($_SESSION['cart'][(int)$productId]);
}

function cart_count(){
  cart_init();
  return array_sum($_SESSION['cart']);
}

function cart_clear(){
  cart_init();
  $_SESSION['cart'] = [];
}
