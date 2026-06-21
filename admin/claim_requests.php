<?php
include_once '../includes/config.php';

// --- Auth ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] ?? '') !== 'Admin') {
  header('Location: ../index.php');
  exit;
}

if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$admin_id = (int)($_SESSION['user_id'] ?? 0);
$flash = '';

// --- Handle Approve/Reject ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['claim_id'], $_POST['csrf'])) {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    $flash = '<div class="alert alert-error">Security check failed. Please refresh and try again.</div>';
  } else {
    $claim_id = (int)$_POST['claim_id'];
    $new_status = $_POST['action'] === 'Approve' ? 'Approved' : 'Rejected';

    // 1) Update claim status
    $stmt = $conn->prepare("UPDATE claims SET status = ? WHERE claim_id = ?");
    $stmt->bind_param("si", $new_status, $claim_id);
    if ($stmt->execute()) {
      // 2) If approved, mark item as Claimed
      if ($new_status === 'Approved') {
        $stmt2 = $conn->prepare("SELECT item_id FROM claims WHERE claim_id = ?");
        $stmt2->bind_param("i", $claim_id);
        $stmt2->execute();
        $res = $stmt2->get_result();
        if ($row = $res->fetch_assoc()) {
          $item_id = (int)$row['item_id'];
          $stmt3 = $conn->prepare("UPDATE items SET status = 'Claimed' WHERE item_id = ?");
          $stmt3->bind_param("i", $item_id);
          $stmt3->execute();
          $stmt3->close();
          log_action($admin_id, "Item ID $item_id marked as Claimed (Claim $claim_id).");
        }
        $stmt2->close();
      }
      $flash = '<div class="alert alert-ok">Claim #'.htmlspecialchars($claim_id).' has been '.$new_status.'.</div>';
      log_action($admin_id, "Claim ID $claim_id set to $new_status.");
    } else {
      $flash = '<div class="alert alert-error">Failed to update claim. Try again.</div>';
    }
    $stmt->close();
  }
}

// --- Fetch pending claims ---
$sql = "SELECT c.claim_id, c.item_id, c.claim_details, c.claim_date,
               i.item_name,
               u.username AS claimer_name
        FROM claims c
        JOIN items i ON c.item_id = i.item_id
        JOIN users u ON c.claimer_id = u.user_id
        WHERE c.status = 'Pending'
        ORDER BY c.claim_date ASC";
$claims = $conn->query($sql);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Pending Claims | Deadlock</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/claim_requests.css?v=1">
</head>
<body>

<center><header class="lf-topbar">
  <div class="lf-wrap">
    <div class="lf-title">Lost &amp; Found • Admin</div>
    <nav>
      <a href="../admin/admin_dashboard.php" class="lf-back">← Back to Admin Panel</a>
    </nav>
  </div>
</header></center>

  
  <main class="wrap">
    <header class="page-head">
      <h1 class="page-title">Pending Claim Requests</h1>
      <p class="page-sub">Review and action claim requests submitted by users.</p>
    </header>

    <?= $flash ?>

    <?php if ($claims && $claims->num_rows > 0): ?>
      <section class="claim-list">
        <?php while ($c = $claims->fetch_assoc()): ?>
          <article class="claim-card">
            <div class="claim-head">
              <h2 class="claim-title">
                Claim #<?= htmlspecialchars($c['claim_id']) ?> · <?= htmlspecialchars($c['item_name']) ?>
              </h2>
              <span class="claim-meta">Item ID: <?= htmlspecialchars($c['item_id']) ?></span>
            </div>

            <dl class="claim-details">
              <div>
                <dt>Claimer</dt>
                <dd><?= htmlspecialchars($c['claimer_name']) ?></dd>
              </div>
              <div>
                <dt>Date</dt>
                <dd><?= htmlspecialchars($c['claim_date']) ?></dd>
              </div>
              <div class="full">
                <dt>Details</dt>
                <dd><?= nl2br(htmlspecialchars($c['claim_details'] ?: 'N/A')) ?></dd>
              </div>
            </dl>

            <div class="actions">
              <form method="post" action="claim_requests.php">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <input type="hidden" name="claim_id" value="<?= (int)$c['claim_id'] ?>">
                <button class="btn btn-approve" name="action" value="Approve">Approve</button>
              </form>
              <form method="post" action="claim_requests.php" onsubmit="return confirm('Reject this claim?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <input type="hidden" name="claim_id" value="<?= (int)$c['claim_id'] ?>">
                <button class="btn btn-reject" name="action" value="Reject">Reject</button>
              </form>
            </div>
          </article>
        <?php endwhile; ?>
      </section>
    <?php else: ?>
      <section class="empty">
        <div class="empty-card">
          <h3>No pending claims 🎉</h3>
          <p>You’re all caught up. New requests will appear here for approval.</p>
        </div>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
<?php $conn->close(); ?>
