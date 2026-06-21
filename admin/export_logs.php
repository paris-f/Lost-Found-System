<?php
// admin/export_logs.php
// Download audit logs as CSV or JSON (with optional filters)

require_once '../includes/config.php';

// Auth: admin only
if (
  !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true ||
  (($_SESSION['role'] ?? '') !== 'Admin')
) {
  http_response_code(403);
  exit('Forbidden');
}

// --- Inputs ---
$format   = strtolower(trim($_GET['format'] ?? 'csv')); // csv | json
$from     = trim($_GET['from'] ?? '');                  // YYYY-MM-DD
$to       = trim($_GET['to'] ?? '');                    // YYYY-MM-DD
$user_id  = trim($_GET['user_id'] ?? '');               // int or ''
$csrf     = $_GET['csrf'] ?? '';

// CSRF (optional but recommended if you added it in the form)
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrf)) {
  // Comment the next two lines if you decide not to use CSRF.
  http_response_code(400);
  exit('Invalid request token');
}

// Build WHERE clause safely
$where = [];
$types = '';
$params = [];

// Date range: use inclusive day range
if ($from !== '') {
  $where[] = 'a.timestamp >= ?';
  $types  .= 's';
  $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
  $where[] = 'a.timestamp <= ?';
  $types  .= 's';
  $params[] = $to . ' 23:59:59';
}

// User filter
if ($user_id !== '' && ctype_digit($user_id)) {
  $where[] = 'a.user_id = ?';
  $types  .= 'i';
  $params[] = (int)$user_id;
}

$sql = "
  SELECT a.timestamp, u.username, a.action
  FROM audit_logs a
  LEFT JOIN users u ON u.user_id = a.user_id
";
if ($where) {
  $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY a.timestamp ASC';

$stmt = $conn->prepare($sql);
if ($stmt === false) {
  http_response_code(500);
  exit('Failed to prepare statement');
}

if ($types !== '') {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
  $rows[] = [
    'timestamp' => $row['timestamp'],
    'user'      => $row['username'] ?? '',
    'action'    => $row['action'],
  ];
}

$today = date('Ymd_His');
$base  = "audit_logs_{$today}";

// Output
if ($format === 'json') {
  header('Content-Type: application/json; charset=UTF-8');
  header('Content-Disposition: attachment; filename="'.$base.'.json"');
  echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  exit;
}

// Default: CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$base.'.csv"');
// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
// Header
fputcsv($out, ['Timestamp', 'User', 'Action']);
// Rows
foreach ($rows as $r) {
  fputcsv($out, [$r['timestamp'], $r['user'], $r['action']]);
}
fclose($out);
exit;
