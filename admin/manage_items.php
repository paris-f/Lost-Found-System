<?php
// Page-specific CSS (admin base + manage_items overrides)
$page_css = '
  <link rel="stylesheet" href="assets/admin.css?v=8">
  <link rel="stylesheet" href="assets/manage_items.css?v=8">
';
$body_class = 'admin';

include '../includes/config.php';

// Auth: admin only
if (
  !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true ||
  ($_SESSION['role'] ?? '') !== 'Admin'
) {
  header('Location: ../index.php');
  exit;
}

// Inject head/body start (this echoes $page_css via the base-injector)
include '../includes/header.php';

$message = ''; // For success/error messages

/* ---------------------------------- */
/* ITEM DELETION LOGIC                */
/* ---------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item_id'])) {
  $item_id = (int)$_POST['delete_item_id'];

  // Start transaction for atomicity (ensures claims and item are deleted or neither are)
  $conn->begin_transaction();

  try {
    // 1. Delete associated claims first (Foreign Key constraint requires this)
    $sql_delete_claims = "DELETE FROM claims WHERE item_id = ?";
    if ($stmt_claims = $conn->prepare($sql_delete_claims)) {
      $stmt_claims->bind_param("i", $item_id);
      $stmt_claims->execute();
      $stmt_claims->close();
    } else {
      throw new Exception("Database error preparing claims delete statement.");
    }

    // 2. Delete the item
    $sql_delete_item = "DELETE FROM items WHERE item_id = ?";
    if ($stmt_item = $conn->prepare($sql_delete_item)) {
      $stmt_item->bind_param("i", $item_id);
      $stmt_item->execute();

      if ($conn->affected_rows > 0) {
        // 3. Log the action
        $user_id = (int)($_SESSION['user_id'] ?? 0);
        $action  = "ADMIN: Deleted item_id: " . $item_id;
        $ip      = $conn->real_escape_string($_SERVER['REMOTE_ADDR'] ?? '');
        $conn->query("INSERT INTO audit_logs (user_id, action, ip_address) VALUES ($user_id, '$action', '$ip')");

        $conn->commit(); // Success: Commit transaction
        $message = '<p class="success-msg">Item ID ' . (int)$item_id . ' and associated claims deleted successfully.</p>';
      } else {
        $conn->rollback(); // Item not found
        $message = '<p class="error-msg">Error: Item not found or already deleted.</p>';
      }
      $stmt_item->close();
    } else {
      throw new Exception("Database error preparing item delete statement.");
    }
  } catch (Exception $e) {
    $conn->rollback(); // Failure: Rollback transaction
    $message = '<p class="error-msg">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
  }
}

/* ---------------------------------- */
/* FETCH ALL ITEMS                    */
/* ---------------------------------- */
$sql_items = "
  SELECT
    i.item_id, i.item_type, i.item_name, i.description, i.status, i.report_date,
    u.username AS reporter_username
  FROM items i
  JOIN users u ON u.user_id = i.user_id
  ORDER BY i.report_date DESC
";
$items_result = $conn->query($sql_items);
$item_count   = $items_result ? $items_result->num_rows : 0;
?>


  <?= $message ?>

  <h3 class="section-title">Reported Items List (<?= (int)$item_count ?> Total)</h3>

  <table class="data-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Type</th>
        <th>Item Details</th>
        <th>Status</th>
        <th>Reporter</th>
        <th>Date</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($items_result && $items_result->num_rows > 0): ?>
        <?php while ($item = $items_result->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($item['item_id']) ?></td>
            <td><?= htmlspecialchars($item['item_type']) ?></td>
            <td>
              <strong><?= htmlspecialchars($item['item_name']) ?></strong>
              <?php
                // Safer: trim first, then escape for display
                $desc = (string)($item['description'] ?? '');
                $snippet = mb_strlen($desc) > 80 ? mb_substr($desc, 0, 80) . '…' : $desc;
              ?>
              <p class="muted small-desc"><?= htmlspecialchars($snippet) ?></p>
            </td>
            <td>
              <span class="status-badge status-<?= htmlspecialchars(strtolower($item['status'])) ?>">
                <?= htmlspecialchars($item['status']) ?>
              </span>
            </td>
            <td><?= htmlspecialchars($item['reporter_username']) ?></td>
            <td><?= htmlspecialchars($item['report_date']) ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('WARNING: Are you sure you want to PERMANENTLY delete item ID <?= (int)$item['item_id'] ?>? This is irreversible and will delete all associated claims.');">
                <input type="hidden" name="delete_item_id" value="<?= (int)$item['item_id'] ?>">
                <button type="submit" class="btn btn-delete">Delete</button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="7">No items have been reported yet.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
if ($items_result) { $items_result->free(); }
// optional: $conn->close();
include '../includes/footer.php';
