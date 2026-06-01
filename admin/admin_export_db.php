<?php
/**
 * /admin/admin_export_db.php — Full DB backup (no mysqldump required)
 * Output: backup_<dbname>_YYYYMMDD_HHMMSS.sql.gz
 */
if (session_status() === PHP_SESSION_NONE) session_start();
include '../includes/config.php';

// --- Admin-only guard ---
if (
  !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true ||
  (($_SESSION['role'] ?? '') !== 'Admin')
) {
  http_response_code(403);
  exit('Forbidden');
}

// --- helpers ---
function qi($name) { return '`' . str_replace('`', '``', $name) . '`'; }

function dump_table(mysqli $conn, string $table, callable $emit): void {
  // DDL
  $ddl = $conn->query("SHOW CREATE TABLE " . qi($table));
  if ($ddl && $row = $ddl->fetch_assoc()) {
    $create = $row['Create Table'] ?? array_values($row)[1] ?? '';
    $emit("\n-- -----------------------------------------------------\n");
    $emit("-- Table structure for {$table}\n\n");
    $emit("DROP TABLE IF EXISTS " . qi($table) . ";\n");
    $emit($create . ";\n");
  }
  $ddl?->free();

  // Count
  $res = $conn->query("SELECT COUNT(*) AS c FROM " . qi($table));
  $count = ($res && $r = $res->fetch_assoc()) ? (int)$r['c'] : 0;
  $res?->free();
  if ($count === 0) return;

  // Columns
  $colsRes = $conn->query("SHOW COLUMNS FROM " . qi($table));
  $cols = [];
  while ($c = $colsRes->fetch_assoc()) $cols[] = $c['Field'];
  $colsRes->free();
  $colsList = implode(',', array_map('qi', $cols));

  // Data (batched)
  $emit("\n-- Data for {$table} ({$count} rows)\n");
  $batch = 500;
  for ($offset = 0; $offset < $count; $offset += $batch) {
    $q = "SELECT * FROM " . qi($table) . " LIMIT {$batch} OFFSET {$offset}";
    if (!$r = $conn->query($q)) break;

    $first = true;
    while ($row = $r->fetch_assoc()) {
      $vals = [];
      foreach ($cols as $c) {
        $v = $row[$c];
        if ($v === null) { $vals[] = "NULL"; continue; }
        $v = $conn->real_escape_string((string)$v);
        $v = str_replace(["\r\n","\r"], ["\n","\n"], $v);
        $vals[] = "'{$v}'";
      }
      if ($first) {
        $emit("INSERT INTO " . qi($table) . " ({$colsList}) VALUES\n");
        $first = false;
      } else {
        $emit(",\n");
      }
      $emit("(" . implode(',', $vals) . ")");
    }
    if (!$first) $emit(";\n");
    $r->free();
  }
}

// --- start stream ---
$DB_NAME = $conn->query("SELECT DATABASE() AS db")->fetch_assoc()['db'] ?? 'database';
$now = (new DateTime('now'))->format('Ymd_His');
$filename = "backup_{$DB_NAME}_{$now}.sql.gz";

header('Content-Type: application/gzip');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: no-store');

$gzip_level = 6;
$buf = '';
$emit = function(string $s) use (&$buf, $gzip_level) {
  $buf .= $s;
  if (strlen($buf) >= 1_000_000) { // flush about 1MB
    echo gzencode($buf, $gzip_level);
    $buf = '';
    @ob_flush(); @flush();
  }
};

// header
$emit("-- Database backup\n");
$emit("-- DB: {$DB_NAME}\n-- Generated: " . date('c') . "\n\n");
$emit("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
$emit("SET time_zone = '+00:00';\n");
$emit("START TRANSACTION;\n");
$emit("SET foreign_key_checks=0;\n");

// tables (sorted)
$tables = [];
if ($res = $conn->query("SHOW TABLES")) {
  while ($row = $res->fetch_array(MYSQLI_NUM)) $tables[] = $row[0];
  $res->free();
}
sort($tables);

foreach ($tables as $t) dump_table($conn, $t, $emit);

$emit("SET foreign_key_checks=1;\nCOMMIT;\n");

// final flush
if ($buf !== '') {
  echo gzencode($buf, $gzip_level);
  $buf = '';
}

// optional: audit log
if ($conn && $conn->ping()) {
  $uid = (int)($_SESSION['user_id'] ?? 0);
  @$conn->query("INSERT INTO audit_logs (user_id, action) VALUES ($uid, 'ADMIN: Downloaded full DB backup')");
}
