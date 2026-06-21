<?php
// report_found.php
include_once 'includes/config.php';

// Gatekeep
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header('Location: index.php');
  exit;
}

$message   = '';
$item_type = 'Found';
$errors    = [];

// Helper to safely echo posted values back into the form
function posted($key, $default = '') {
  return isset($_POST[$key]) ? htmlspecialchars((string)$_POST[$key]) : $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // --- Read & sanitize inputs ---
  $item_name   = trim($conn->real_escape_string($_POST['item_name'] ?? ''));
  $category    = trim($conn->real_escape_string($_POST['category'] ?? ''));
  $description = trim($conn->real_escape_string($_POST['description'] ?? ''));
  $location    = trim($conn->real_escape_string($_POST['location'] ?? ''));
  $dom_color   = trim($conn->real_escape_string($_POST['dominant_color'] ?? 'N/A'));

  $user_id     = (int)($_SESSION['user_id'] ?? 0);
  $report_date = date('Y-m-d');
  $item_image  = null;

  // --- Server-side validation (never rely on client only) ---
  if ($item_name === '')   { $errors['item_name']   = 'Item name is required.'; }
  else if (strlen($item_name) < 3) { $errors['item_name']   = 'Item name must be at least 3 characters.'; }
  // Ensure it contains at least one letter and is not just numbers/symbols
  else if (!preg_match('/[a-zA-Z]/', $item_name)) { $errors['item_name']   = 'Item name must contain letters.'; }

  if ($category === '')    { $errors['category']    = 'Please select a category.'; }

  if ($description === '') { $errors['description'] = 'Description is required.'; }
  else if (strlen($description) < 10) { $errors['description'] = 'Description must be at least 10 characters.'; }
  else if (!preg_match('/[a-zA-Z]/', $description)) { $errors['description']   = 'Description must contain letters.'; }

  if ($location === '')    { $errors['location']    = 'Location is required.'; }
  else if (strlen($location) < 3) { $errors['location'] = 'Location must be at least 3 characters.'; }
  else if (!preg_match('/[a-zA-Z]/', $location)) { $errors['location']   = 'Location must contain letters.'; }

  // --- Image upload (optional) ---
  if (empty($errors) && !empty($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
      $dir = __DIR__ . '/images/';
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

      $ext = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
      // Basic allowlist
      $allowed = ['jpg','jpeg','png','gif','webp'];
      if (!in_array($ext, $allowed, true)) {
        $errors['item_image'] = 'Unsupported image type. Use JPG, PNG, GIF, or WEBP.';
      } else {
        $fname = uniqid('item_', true) . '.' . $ext;
        $dest  = $dir . $fname;
        if (move_uploaded_file($_FILES['item_image']['tmp_name'], $dest)) {
          $item_image = $fname; // saved relative name; we’ll reference images/item_xxx from HTML
        } else {
          $errors['item_image'] = 'Image upload failed. Please try again.';
        }
      }
    } else {
      $errors['item_image'] = 'Image upload error.';
    }
  }

  // --- If validation passed, insert record ---
  if (empty($errors)) {
    // Keep schema unchanged: append metadata into description for searchability
    $full_description = trim($description . ' [COLOR: ' . ($dom_color !== '' ? $dom_color : 'N/A') . ']');

    $sql = "INSERT INTO items
              (user_id, item_type, item_name, category, description, location, item_image, report_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = $conn->prepare($sql)) {
      $stmt->bind_param(
        'isssssss',
        $user_id,
        $item_type,
        $item_name,
        $category,
        $full_description,
        $location,
        $item_image,
        $report_date
      );
      if ($stmt->execute()) {
        $message = "<div class='alert success'>Found item reported. Detected colour: <strong>" . htmlspecialchars($dom_color ?: 'N/A') . "</strong>.</div>";
        if (function_exists('log_action')) {
          log_action($user_id, 'Reported found item: ' . $item_name);
        }
        // Clear POST so the form resets after a success
        $_POST = [];
      } else {
        $message = "<div class='alert error'>Database error: " . htmlspecialchars($stmt->error) . "</div>";
      }
      $stmt->close();
    } else {
      $message = "<div class='alert error'>Failed to prepare database statement.</div>";
    }
  } else {
    // Build an error message list
    $message = "<div class='alert error'><strong>Please fix the following:</strong><ul style='margin:8px 0 0 18px'>";
    foreach ($errors as $msg) {
      $message .= "<li>" . htmlspecialchars($msg) . "</li>";
    }
    $message .= "</ul></div>";
  }
}

// Page-specific CSS injection for header.php
$page_css   = '<link rel="stylesheet" href="assets/report_found.css?v=4">';
$body_class = 'page-report-found';

include 'includes/header.php';
?>

<div class="wrap">
  <h1 class="page-title">Report a Found Item</h1>
  <?= $message ?>

  <form class="found-form" method="post" action="report_found.php" enctype="multipart/form-data">
    <div class="field">
      <label for="item_name">Item Name</label>
      <input type="text" id="item_name" name="item_name" required value="<?= posted('item_name') ?>">
    </div>

    <div class="field">
      <label for="category">Category</label>
      <select id="category" name="category" required>
        <option value="" disabled <?= posted('category') === '' ? 'selected' : '' ?>>-- Select a category --</option>
        <?php
          $cats = ['Electronics','Clothing','Bags','Keys','Documents','Other'];
          foreach ($cats as $c) {
            $sel = posted('category') === $c ? 'selected' : '';
            echo "<option value=\"{$c}\" {$sel}>{$c}</option>";
          }
        ?>
      </select>
    </div>

    <div class="field">
      <label for="description">Detailed Description (where/when it was found)</label>
      <textarea id="description" name="description" rows="4" required><?= posted('description') ?></textarea>
    </div>

    <div class="field">
      <label for="location">Location Found</label>
      <input type="text" id="location" name="location" placeholder="e.g., Room 301, North Hallway" required value="<?= posted('location') ?>">
    </div>

    <div class="field-row two">
      <div class="field">
        <label for="item_image">Optional Image</label>
        <input type="file" id="item_image" name="item_image" accept="image/*">
        <small class="hint">Choose an image to auto-detect the dominant colour.</small>
        <?php if (!empty($errors['item_image'])): ?>
          <div class="inline-error"><?= htmlspecialchars($errors['item_image']) ?></div>
        <?php endif; ?>
      </div>

      <div class="field">
        <label for="dominant_color_text">Detected Dominant Colour</label>
        <div class="color-readout">
          <span id="color_swatch" class="swatch" aria-hidden="true"></span>
          <input
            type="text"
            id="dominant_color_text"
            readonly
            placeholder="No image selected"
            value="<?= posted('dominant_color', '') ?>"
          >
        </div>
        <input type="hidden" id="dominant_color" name="dominant_color" value="<?= posted('dominant_color', 'N/A') ?>">
      </div>
    </div>

    <div class="actions">
      <button type="submit" class="btn primary">Submit Found Report</button>
    </div>
  </form>

  <canvas id="analyser" width="1" height="1" class="u-hidden"></canvas>
</div>

<script>
(function() {
  const fileInput   = document.getElementById('item_image');
  const nameInput   = document.getElementById('dominant_color_text');
  const hiddenField = document.getElementById('dominant_color');
  const swatchEl    = document.getElementById('color_swatch');
  const canvas      = document.getElementById('analyser');

  // Palette used on the Lost page (readable names)
  const PALETTE = [
    {name:'Black',  r:  0, g:  0, b:  0},
    {name:'White',  r:255, g:255, b:255},
    {name:'Gray',   r:128, g:128, b:128},
    {name:'Silver', r:192, g:192, b:192},
    {name:'Red',    r:220, g: 20, b: 60},
    {name:'Green',  r: 34, g:139, b: 34},
    {name:'Blue',   r: 30, g:144, b:255},
    {name:'Yellow', r:255, g:215, b:  0},
    {name:'Orange', r:255, g:140, b:  0},
    {name:'Brown',  r:139, g: 69, b: 19},
    {name:'Purple', r:138, g: 43, b:226},
    {name:'Pink',   r:255, g:105, b:180},
    {name:'Beige',  r:245, g:245, b:220}
  ];

  function nearestNamedColor(r, g, b) {
    let best = {name:'Gray', d: Infinity};
    for (const c of PALETTE) {
      const dr = r - c.r, dg = g - c.g, db = b - c.b;
      const d = dr*dr + dg*dg + db*db;
      if (d < best.d) best = {name: c.name, d};
    }
    return best.name;
  }

  function setOutputs(name, rgb) {
    nameInput.value   = name || 'N/A';
    hiddenField.value = name || 'N/A';
    if (rgb && Array.isArray(rgb)) {
      swatchEl.style.background = `rgb(${rgb[0]}, ${rgb[1]}, ${rgb[2]})`;
      swatchEl.classList.add('has-color');
    } else {
      swatchEl.style.background = 'transparent';
      swatchEl.classList.remove('has-color');
    }
  }

  async function detectDominantColor(file) {
    // Load image
    const dataUrl = await new Promise((res, rej) => {
      const fr = new FileReader();
      fr.onload = () => res(fr.result);
      fr.onerror = rej;
      fr.readAsDataURL(file);
    });

    const img = new Image();
    img.src = dataUrl;
    await new Promise(res => img.onload = res);

    // Draw to small canvas
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const maxSide = 256;
    let w = img.naturalWidth || img.width;
    let h = img.naturalHeight || img.height;
    const scale = Math.min(1, maxSide / Math.max(w, h));
    w = Math.max(1, Math.round(w * scale));
    h = Math.max(1, Math.round(h * scale));
    canvas.width = w; canvas.height = h;
    ctx.drawImage(img, 0, 0, w, h);

    // Build coarse histogram (same approach as Lost page)
    const { data } = ctx.getImageData(0, 0, w, h);
    const buckets = new Map();
    const step = 4 * 4; // sample every 4th pixel to speed up
    for (let i = 0; i < data.length; i += step) {
      const r = data[i], g = data[i+1], b = data[i+2], a = data[i+3];
      if (a < 16) continue;                // ignore near-transparent
      if (r < 3 && g < 3 && b < 3) continue;   // drop extreme black noise
      if (r > 252 && g > 252 && b > 252) continue; // drop extreme white noise
      const R = r >> 4, G = g >> 4, B = b >> 4; // 16 levels per channel
      const key = (R<<8) | (G<<4) | B;
      buckets.set(key, (buckets.get(key) || 0) + 1);
    }

    // Pick dominant bucket
    let bestKey = null, bestCount = -1;
    for (const [key, count] of buckets.entries()) {
      if (count > bestCount) { bestKey = key; bestCount = count; }
    }
    if (bestKey === null) return setOutputs('N/A');

    // Average all pixels in that bucket
    const Rb = (bestKey >> 8) & 0xF, Gb = (bestKey >> 4) & 0xF, Bb = bestKey & 0xF;
    let sumR=0, sumG=0, sumB=0, n=0;
    for (let i = 0; i < data.length; i += step) {
      const r = data[i], g = data[i+1], b = data[i+2], a = data[i+3];
      if (a < 16) continue;
      if ((r>>4)===Rb && (g>>4)===Gb && (b>>4)===Bb) {
        sumR += r; sumG += g; sumB += b; n++;
      }
    }
    if (!n) return setOutputs('N/A');

    const avgR = Math.round(sumR/n);
    const avgG = Math.round(sumG/n);
    const avgB = Math.round(sumB/n);
    const name = nearestNamedColor(avgR, avgG, avgB);
    setOutputs(name, [avgR, avgG, avgB]);
  }

  // Initial render if value already present (after failed submit)
  (function initFromExisting() {
    const existing = hiddenField.value;
    if (existing && existing !== 'N/A') {
      nameInput.value = existing;
      // we don’t know the exact RGB; keep swatch transparent
    }
  })();

  fileInput?.addEventListener('change', async () => {
    if (!fileInput.files || !fileInput.files[0]) {
      setOutputs('N/A');
      return;
    }
    const file = fileInput.files[0];
    if (!/^image\//.test(file.type)) {
      setOutputs('N/A');
      return;
    }
    try {
      await detectDominantColor(file);
    } catch (e) {
      console.warn('Dominant colour detection failed:', e);
      setOutputs('Unknown');
    }
  });
})();
</script>


<?php include 'includes/footer.php'; ?>