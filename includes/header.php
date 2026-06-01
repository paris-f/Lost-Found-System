<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Lost & Found Tracking System</title>
  <?php
  $BASE = '/DEADLOCK';

  // Global CSS (always load)
  echo '<link rel="stylesheet" href="'.$BASE.'/assets/style.css?v=3">';
  echo '<link rel="stylesheet" href="'.$BASE.'/assets/nav.css?v=2">';

  // Page-specific CSS if defined
  if (!empty($page_css)) {
    $page_css_abs = preg_replace(
      '~href=["\'](?!https?://|/)([^"\']+)~i',
      'href="'.$BASE.'/$1',
      $page_css
    );
    echo $page_css_abs;
  }

  echo "\n<!-- header.php injected at ".date('H:i:s')." -->\n";
  ?>

</head>
<?php
// Body class (optional per page)
$body_class = isset($body_class) ? trim($body_class) : '';

// Helper function for active link highlighting
$is_active = function (string $file): string {
  $path = $_SERVER['SCRIPT_NAME'] ?? '';
  return (strpos($path, '/' . $file) !== false) ? ' active' : '';
};
?>

<body class="<?= htmlspecialchars($body_class) ?>">
 <center> <header class="main-nav">
    <nav class="nav-wrap">
      <a class="nav-brand" href="<?= $BASE ?>/dashboard.php">Lost &amp; Found</a>
      <div class="nav-links">
        <a class="nav-link<?= $is_active('dashboard.php') ?>" href="<?= $BASE ?>/dashboard.php">Home/Dashboard</a>
        <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
          <a class="nav-link<?= $is_active('report_lost.php') ?>" href="<?= $BASE ?>/report_lost.php">Report Lost</a>
          <a class="nav-link<?= $is_active('report_found.php') ?>" href="<?= $BASE ?>/report_found.php">Report Found</a>
          <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
            <a class="nav-link<?= $is_active('admin_dashboard.php') ?>" href="<?= $BASE ?>/admin/admin_dashboard.php">Admin Panel</a>
          <?php endif; ?>
          <a class="nav-link" href="<?= $BASE ?>/logout.php">Logout (<?= htmlspecialchars($_SESSION['username']); ?>)</a>
        <?php else: ?>
          <a class="nav-link<?= $is_active('../login.php') ?>" href="<?= $BASE ?>/login.php">Login</a>
        <?php endif; ?>
      </div>
    </nav>
  </header></center>
  <main>
