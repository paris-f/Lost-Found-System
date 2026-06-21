<?php
include_once '../includes/config.php';

// ---- Auth ----
if (!is_admin()) {
    header('Location: ../index.php');
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$admin_id = (int)($_SESSION['user_id'] ?? 0);
$messageHtml = '';

// ---- Handle POST (update or delete) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $token)) {
        $messageHtml = '<div class="alert error">Security check failed. Please refresh and try again.</div>';
    } else {
        $target_id = (int)($_POST['user_id'] ?? 0);
        if ($target_id === $admin_id) {
            $messageHtml = '<div class="alert error">You cannot modify or delete your own admin account.</div>';
        } elseif ($target_id > 0) {
            // --- Update role ---
            if (isset($_POST['new_role'])) {
                $role = $_POST['new_role'];
                if (!in_array($role, ['Student','Staff','Admin'], true)) {
                    $messageHtml = '<div class="alert error">Invalid role.</div>';
                } else {
                    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $role, $target_id);
                    if ($stmt->execute()) {
                        $messageHtml = '<div class="alert success">User ID '.$target_id.' role updated to '.htmlspecialchars($role).'.</div>';
                        log_action($admin_id, "Updated user ID $target_id to $role");
                    } else {
                        $messageHtml = '<div class="alert error">Error updating role.</div>';
                    }
                    $stmt->close();
                }
            }

            // --- Delete user ---
            if (isset($_POST['delete_user'])) {
                $conn->query("DELETE FROM audit_logs WHERE user_id = $target_id");
                $conn->query("DELETE FROM claims WHERE claimer_id = $target_id");
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $target_id);
                if ($stmt->execute()) {
                    $messageHtml = '<div class="alert success">User ID '.$target_id.' deleted successfully.</div>';
                    log_action($admin_id, "Deleted user ID $target_id");
                } else {
                    $messageHtml = '<div class="alert error">Could not delete user (check related data).</div>';
                }
                $stmt->close();
            }
        }
    }
}

// ---- Fetch users ----
$result = $conn->query("SELECT user_id, username, email, role, created_at FROM users ORDER BY user_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Deadlock</title>
    <link rel="stylesheet" href="../assets/user_management.css">
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

<div class="container">
    <h2>User Management</h2>
    <?= $messageHtml ?>

    <p>Manage roles and accounts for all users registered in the system.</p>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Current Role</th>
                <th>Registered</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($user = $result->fetch_assoc()): 
                $isSelf = ((int)$user['user_id'] === $admin_id);
                $role = htmlspecialchars($user['role']);
            ?>
            <tr>
                <td><?= htmlspecialchars($user['user_id']) ?></td>
                <td><?= htmlspecialchars($user['username']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td>
                    <form method="post" action="user_management.php" style="display:flex; gap:8px; align-items:center;">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                        <select name="new_role" <?= $isSelf ? 'disabled' : '' ?>>
                            <option value="Student" <?= $role==='Student'?'selected':'' ?>>Student</option>
                            <option value="Staff"   <?= $role==='Staff'  ?'selected':'' ?>>Staff</option>
                            <option value="Admin"   <?= $role==='Admin'  ?'selected':'' ?>>Admin</option>
                        </select>
                        <span class="badge <?= $role ?>"><?= $role ?></span>
                        <button type="submit" class="btn-update" <?= $isSelf ? 'disabled' : '' ?>>Update</button>
                    </form>
                </td>
                <td><?= htmlspecialchars(substr($user['created_at'], 0, 10)) ?></td>
                <td>
                    <form method="post" action="user_management.php" 
                        onsubmit="return confirm('Are you sure you want to delete this user?');">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                        <button type="submit" name="delete_user" class="btn-delete" <?= $isSelf ? 'disabled' : '' ?>>Delete</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No users found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
