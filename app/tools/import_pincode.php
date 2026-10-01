<?php
require_once __DIR__ . '/../config/bootstrap.php';
global $conn;

requireLogin();
requireRole('admin');

set_time_limit(0);
ini_set('memory_limit', '1024M');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// =====================================================
// CONFIG
// =====================================================
$csvPath = __DIR__ . '/../../public/uploads/pin.csv';   // project root pincode.csv
$BATCH = 1000;

// If your CSV has NO header (like the data row you showed), keep this TRUE
$FORCE_NO_HEADER = true;

// India Post CSV (no header) column positions:
// 0 circle,1 region,2 division,3 officename,4 pincode,5 officetype,6 delivery,7 district,8 statename,9 lat,10 lon
$IDX_PIN = 4;
$IDX_DELIVERY = 6;
$IDX_DISTRICT = 7;
$IDX_STATE = 8;

// =====================================================
// CHECK CSV FILE
// =====================================================
if (!file_exists($csvPath)) {
  echo "<div style='font-family:Arial;padding:16px'>
    <h2>CSV not found ❌</h2>
    <p>Expected:</p>
    <pre>".h($csvPath)."</pre>
    <p>Put your file here:</p>
    <pre>C:\\xampp\\htdocs\\smartbuy2.O\\pincode.csv</pre>
  </div>";
  exit;
}

// Optional clear
if (isset($_GET['clear']) && $_GET['clear'] === '1') {
  $conn->query("TRUNCATE TABLE pincodes");
  echo "<div style='font-family:Arial;padding:10px;background:#e8ffe8;border:1px solid #9be59b'>
    pincodes table cleared ✅
  </div>";
}

// Detect delimiter
function detectDelimiter(string $line): string {
  $delims = [",", "\t", ";", "|"];
  $best = ",";
  $max = 0;
  foreach ($delims as $d) {
    $c = substr_count($line, $d);
    if ($c > $max) { $max = $c; $best = $d; }
  }
  return $best;
}

// Normalize pin
function normPin($v): string {
  $p = preg_replace('/\D+/', '', (string)$v);
  return $p;
}

// Determine serviceable from "Delivery"/"Non-Delivery"
function parseServiceable($v): int {
  $t = strtolower(trim((string)$v));
  // common values in India Post dump: "Delivery", "Non-Delivery"
  if ($t === 'delivery' || $t === 'yes' || $t === 'y' || $t === '1' || $t === 'true') return 1;
  if ($t === 'non-delivery' || $t === 'nondelivery' || $t === 'no' || $t === 'n' || $t === '0' || $t === 'false') return 0;
  // default: assume serviceable
  return 1;
}

// =====================================================
// OPEN CSV
// =====================================================
$fp = fopen($csvPath, 'r');
if (!$fp) die("Cannot open CSV");

// Read first line to detect delimiter
$firstLine = fgets($fp);
if ($firstLine === false) die("CSV empty");
$delimiter = detectDelimiter($firstLine);

// rewind to start for real processing
rewind($fp);

// If file might have header, we auto-detect header unless FORCE_NO_HEADER
$hasHeader = false;
$firstRow = fgetcsv($fp, 0, $delimiter);
if (!$firstRow) die("CSV empty/invalid");

// If not forcing no header, try detect: if column 4 is NOT a 6-digit pin, assume header
if (!$FORCE_NO_HEADER) {
  $maybePin = normPin($firstRow[$IDX_PIN] ?? '');
  if (preg_match('/^\d{6}$/', $maybePin)) {
    $hasHeader = false; // data
  } else {
    $hasHeader = true;  // header
  }
}

// If header exists, read next row as first data row
if ($hasHeader) {
  $firstRow = fgetcsv($fp, 0, $delimiter);
}

// =====================================================
// PREPARED INSERT
// =====================================================
$sql = "INSERT INTO pincodes (pincode, district, statename, delivery_days, is_serviceable)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
          district=VALUES(district),
          statename=VALUES(statename),
          delivery_days=VALUES(delivery_days),
          is_serviceable=VALUES(is_serviceable)";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Prepare failed: ".$conn->error);

// =====================================================
// IMPORT LOOP
// =====================================================
$inserted = 0;
$read = 0;
$start = microtime(true);

function processRow(mysqli_stmt $stmt, array $row, int $IDX_PIN, int $IDX_DELIVERY, int $IDX_DISTRICT, int $IDX_STATE, &$inserted) {
  $pin = normPin($row[$IDX_PIN] ?? '');
  if (!preg_match('/^\d{6}$/', $pin)) return;

  $district = trim((string)($row[$IDX_DISTRICT] ?? ''));
  $state    = trim((string)($row[$IDX_STATE] ?? ''));

  $serviceable = parseServiceable($row[$IDX_DELIVERY] ?? '');
  $days = ($serviceable === 1) ? 5 : 0;

  $stmt->bind_param("sssii", $pin, $district, $state, $days, $serviceable);
  $stmt->execute();
  $inserted++;
}

// process first data row we already read
processRow($stmt, $firstRow, $IDX_PIN, $IDX_DELIVERY, $IDX_DISTRICT, $IDX_STATE, $inserted);
$read++;

// process remaining
while (($row = fgetcsv($fp, 0, $delimiter)) !== false) {
  $read++;
  processRow($stmt, $row, $IDX_PIN, $IDX_DELIVERY, $IDX_DISTRICT, $IDX_STATE, $inserted);
}

$stmt->close();
fclose($fp);

$sec = round(microtime(true) - $start, 2);

echo "<div style='font-family:Arial;padding:16px'>
  <h2>✅ Pincode import completed</h2>
  <p><b>CSV:</b> ".h($csvPath)."</p>
  <p><b>Delimiter:</b> ".h($delimiter)."</p>
  <p><b>Has header:</b> ".($hasHeader ? "YES" : "NO")."</p>
  <p><b>Rows read:</b> ".(int)$read."</p>
  <p><b>Inserted/updated:</b> ".(int)$inserted."</p>
  <p><b>Time:</b> {$sec}s</p>

  <hr>
  <p><a href='".h(BASE_URL)."tools/import_pincode?clear=1'>Clear pincodes table</a></p>
  <p><a href='".h(BASE_URL)."admin/dashboard'>Back to Admin Dashboard</a></p>
</div>";