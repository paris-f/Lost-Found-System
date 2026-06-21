<?php
// admin/index.php (Admin Dashboard)

$page_css   = '<link rel="stylesheet" href="assets/admin.css?v=8">'; // <-- no ../
$body_class = 'admin';

include '../includes/config.php';
include '../includes/header.php';

// Auth: admin only
if (
  !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true ||
  (($_SESSION['role'] ?? '') !== 'Admin')
) {
  header('Location: ../index.php');
  exit;
}

/* Page styling hook for header.php (kept if your header reads these after include) */
$page_css   = '<link rel="stylesheet" href="../assets/admin.css?v=7">';
$body_class = 'admin';

/* ---- Counts (fully guarded) ---- */
$counts = [
  'total_users'   => 0,
  'total_items'   => 0,
  'pending_items' => 0,
  'pending_claims'=> 0,
  'total_logs'    => 0,
];

$sql_counts = "
  SELECT
    (SELECT COUNT(*) FROM users)                                  AS total_users,
    (SELECT COUNT(*) FROM items)                                  AS total_items,
    (SELECT COUNT(*) FROM items   WHERE status = 'Pending')       AS pending_items,
    (SELECT COUNT(*) FROM claims  WHERE status = 'Pending')       AS pending_claims,
    (SELECT COUNT(*) FROM audit_logs)                             AS total_logs
";

if ($r = $conn->query($sql_counts)) {
  $row = $r->fetch_assoc() ?: [];
  foreach ($counts as $k => $v) {
    $counts[$k] = isset($row[$k]) ? (int)$row[$k] : 0;
  }
  $r->free();
}

/* ---- Latest logs (guarded) ---- */
$sql_logs = "
  SELECT a.timestamp, u.username, a.action
  FROM audit_logs a
  JOIN users u ON u.user_id = a.user_id
  ORDER BY a.timestamp DESC
  LIMIT 15
";
$logs_result = $conn->query($sql_logs);   // can be false; we check below

// --- Export helpers (CSRF + users list) ---
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$users_rs = $conn->query("SELECT user_id, username FROM users ORDER BY username ASC");
?>
<div class="wrap">
  <div class="page-hero">
    <h2>Admin Dashboard</h2>
    <p>Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'admin') ?>. This is your central control panel.</p>
  </div>

  <?php
    $resolved = max(0, $counts['total_items'] - $counts['pending_items']);
    $pct      = $counts['total_items'] > 0 ? round(100 * $resolved / $counts['total_items']) : 0;
  ?>

  <div class="stat-grid">
    <div class="stat info">
      <div class="row">
        <div class="icon">👥</div>
        <div>
          <div class="num"><?= $counts['total_users'] ?></div>
          <div class="label">Total Users</div>
        </div>
      </div>
      <a class="cta" href="user_management.php">Manage Users</a>
    </div>

    <div class="stat warn">
      <div class="row">
        <div class="icon">📩</div>
        <div>
          <div class="num"><?= $counts['pending_claims'] ?></div>
          <div class="label">Pending Claims</div>
        </div>
      </div>
      <a class="cta" href="claim_requests.php">Review Claims</a>
    </div>

    <div class="stat good">
      <div class="row">
        <div class="icon">📦</div>
        <div>
          <div class="num"><?= $counts['total_items'] ?></div>
          <div class="label">Total Items</div>
        </div>
      </div>
    <a class="cta" href="manage_items.php">Manage Items</a>
    </div>
  </div>

  <h3 class="section-title">Latest Audit Logs (Last 15 Actions)</h3>
  <p class="muted" style="margin: 6px 0 14px;">
    Total Logged Actions: <?= $counts['total_logs'] ?>. Tracks logins, role changes, and reporting actions.
  </p>

  <!-- ===== Export Bar (CSV/JSON with filters) ===== -->
  <form class="export-bar" action="export_logs.php" method="get" target="_blank">
  <div class="user">
    <label class="muted" for="user_id">User</label>
    <select id="user_id" name="user_id">
      <option value="">All users</option>
      <?php if ($users_rs && $users_rs->num_rows): ?>
        <?php while ($u = $users_rs->fetch_assoc()): ?>
          <option value="<?= (int)$u['user_id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
        <?php endwhile; ?>
      <?php endif; ?>
    </select>
  </div>

  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

  <div class="actions">
    <button type="submit" name="format" value="csv" class="btn csv">Download CSV</button>
    <button type="submit" name="format" value="json" class="btn">Download JSON</button>

  <button type="button" class="btn" onclick="window.location.href='admin_export_db.php'">
      Backup DB (.sql.gz)
    </button>  </div>
</form>

  <table>
    <thead>
      <tr>
        <th>Timestamp</th>
        <th>User</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($logs_result && $logs_result->num_rows > 0): ?>
        <?php while ($log = $logs_result->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($log['timestamp']) ?></td>
            <td><?= htmlspecialchars($log['username']) ?></td>
            <td><?= htmlspecialchars($log['action']) ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="3">No audit logs found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
// optional: $conn->close();
// footer closes main/body/html if your header/footer templates handle it
?>
