<?php
require_once __DIR__ . '/../../config/bootstrap.php';
global $conn;

header('Content-Type: application/json; charset=utf-8');

$subcategory_id = (int)($_GET['subcategory_id'] ?? 0);
if ($subcategory_id <= 0) { echo json_encode([]); exit; }

/**
 * IMPORTANT:
 * This must return ONLY rows from subcat_specs for that subcategory_id.
 * If you still see "all specs", then your subcat_specs table has wrong/duplicate mappings.
 */

try {
  // --- detect columns in spec_fields ---
  $specCols = [];
  $r = $conn->query("SHOW COLUMNS FROM spec_fields");
  while ($row = $r->fetch_assoc()) $specCols[] = $row['Field'];

  $has_input = in_array('input_type', $specCols, true);
  $has_unit  = in_array('unit', $specCols, true);
  $has_opts  = in_array('options_json', $specCols, true);

  // Always safe aliases (avoid: Unknown column f.input_type)
  $col_input = $has_input ? "f.input_type" : "'text'";
  $col_unit  = $has_unit  ? "f.unit"       : "NULL";
  $col_opts  = $has_opts  ? "f.options_json" : "NULL";

  // --- detect columns in subcat_specs ---
  $ssCols = [];
  $r2 = $conn->query("SHOW COLUMNS FROM subcat_specs");
  while ($row = $r2->fetch_assoc()) $ssCols[] = $row['Field'];

  $has_req  = in_array('is_required', $ssCols, true);
  $has_sort = in_array('sort_order', $ssCols, true);

  $col_req  = $has_req  ? "ss.is_required" : "0";
  $col_sort = $has_sort ? "ss.sort_order"  : "0";

  $sql = "
    SELECT
      f.id,
      f.label,
      {$col_input} AS input_type,
      {$col_unit}  AS unit,
      {$col_opts}  AS options_json,
      {$col_req}   AS is_required
    FROM subcat_specs ss
    INNER JOIN spec_fields f ON f.id = ss.spec_field_id
    WHERE ss.subcategory_id = ?
    ORDER BY {$col_sort} ASC, f.label ASC
  ";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $subcategory_id);
  $stmt->execute();
  $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  echo json_encode($data, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  // fail safe
  echo json_encode([]);
}