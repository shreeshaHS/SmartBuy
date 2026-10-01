<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$category_id = (int)($_GET['category_id'] ?? 0);
if ($category_id <= 0) { echo json_encode([]); exit; }

$stmt = db_prepare("SELECT id,name FROM subcategories WHERE category_id=? AND is_active=1 ORDER BY name");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($data);
