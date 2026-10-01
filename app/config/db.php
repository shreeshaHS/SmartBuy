<?php
require __DIR__ . '/env.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

// Force strict SQL mode (safer for production)
$conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

function db_prepare(string $sql): mysqli_stmt {
  global $conn;
  return $conn->prepare($sql);
}

function db_query(string $sql): mysqli_result {
  global $conn;
  return $conn->query($sql);
}

function db_exec(string $sql): void {
  global $conn;
  $conn->query($sql);
}
