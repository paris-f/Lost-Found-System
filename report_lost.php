<?php
// report_lost.php
include_once 'includes/config.php';

// Require login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header('Location: index.php');
  exit;
}

$message      = '';
$message_type = ''; // success | error
$item_type    = 'Lost';
$errors       = [];

// Helper to safely echo posted values back into the form
function posted($key, $default = '') {
  return isset($_POST[$key]) ? htmlspecialchars((string)$_POST[$key]) : $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Read & sanitize
  $item_name       = trim($conn->real_escape_string($_POST['item_name'] ?? ''));
  $category        = trim($conn->real_escape_string($_POST['category'] ?? ''));
  $description     = trim($conn->real_escape_string($_POST['description'] ?? ''));
  $location        = trim($conn->real_escape_string($_POST['location'] ?? ''));
  $dominant_color  = trim($conn->real_escape_string($_POST['dominant_color'] ?? 'N/A'));
  $detected_object = trim($conn->real_escape_string($_POST['detected_object'] ?? 'N/A'));

  $user_id     = (int)($_SESSION['user_id'] ?? 0);
  $report_date = date('Y-m-d');
  $item_image  = null;

  // Server-side required checks + new content validation
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

  // Optional image upload (allowlist + safe path)
  if (empty($errors) && !empty($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
      $dir = __DIR__ . '/images/';
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

      $ext      = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
      $allowed  = ['jpg','jpeg','png','gif','webp'];
      if (!in_array($ext, $allowed, true)) {
        $errors['item_image'] = 'Unsupported image type. Use JPG, PNG, GIF, or WEBP.';
      } else {
        $fname = uniqid('item_', true) . '.' . $ext;
        $dest  = $dir . $fname;
        if (move_uploaded_file($_FILES['item_image']['tmp_name'], $dest)) {
          $item_image = $fname; // store relative filename
        } else {
          $errors['item_image'] = 'Image upload failed. Please try again.';
        }
      }
    } else {
      $errors['item_image'] = 'Image upload error.';
    }
  }

  // Insert when valid
  if (empty($errors)) {
    // Keep schema: append metadata to description for searchability
    $full_description = trim($description . ' [COLOR: ' . ($dominant_color ?: 'N/A') . '] [OBJECT: ' . ($detected_object ?: 'N/A') . ']');

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
        $message      = 'Lost item reported successfully! Metadata captured for matching.';
        $message_type = 'success';
        if (function_exists('log_action')) {
          log_action($user_id, 'Reported lost item: ' . $item_name);
        }
        // reset form after success
        $_POST = [];
      } else {
        $message      = 'Database error: ' . htmlspecialchars($stmt->error);
        $message_type = 'error';
      }
      $stmt->close();
    } else {
      $message      = 'Failed to prepare database statement.';
      $message_type = 'error';
    }
  } else {
    // Build error list
    $message = "<strong>Please fix the following:</strong><ul style='margin:8px 0 0 18px'>";
    foreach ($errors as $msg) { $message .= '<li>' . htmlspecialchars($msg) . '</li>'; }
    $message .= '</ul>';
    $message_type = 'error';
  }
}

// Page-specific CSS + body class (keep your stylesheet)
$page_css   = '<link rel="stylesheet" href="assets/form.css?v=3">';
$body_class = 'page-lost';

include 'includes/header.php';
?>

<div class="wrap">
  <h1 class="page-title">Report a Lost Item (Optional)</h1>
  <div class="accent-line"></div>

  <?php if (!empty($message)): ?>
    <div class="alert <?= $message_type === 'success' ? 'alert-ok' : 'alert-bad' ?>">
      <?= $message /* already escaped where needed above */ ?>
    </div>
  <?php endif; ?>

  <form class="card form-grid" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="post" enctype="multipart/form-data">
    <div class="field">
      <label for="item_name">Item Name</label>
      <input id="item_name" name="item_name" type="text" required value="<?= posted('item_name') ?>">
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

    <div class="field field-full">
      <label for="description">Detailed Description</label>
      <textarea id="description" name="description" rows="4" required><?= posted('description') ?></textarea>
      <small class="help">Include brand, model, markings, stickers, and any unique identifiers.</small>
    </div>

    <div class="field field-full">
      <label for="location">Last Seen Location</label>
      <input id="location" name="location" type="text" required placeholder="e.g., Library, Cafeteria" value="<?= posted('location') ?>">
    </div>

    <div class="divider"><b>Image (optional)</b></div>

    <div class="field">
      <label for="item_image">Upload Image (optional)</label>
      <input id="item_image" name="item_image" type="file" accept="image/*">
      <small class="muted">If you add an image, we’ll auto-fill color/object to improve matching.</small>
      <?php if (!empty($errors['item_image'])): ?>
        <div class="inline-error"><?= htmlspecialchars($errors['item_image']) ?></div>
      <?php endif; ?>
    </div>

    <div class="field">
      <label for="dominant_color">Dominant Colour (auto)</label>
      <input id="dominant_color" name="dominant_color" type="text"
         placeholder="Auto-detected after choosing an image"
         value="<?= posted('dominant_color') ?>">
      <small class="muted">You can edit this if needed.</small>
    </div>
        <?php
          $colors = ['N/A (No Image)' => 'N/A', 'Red' => 'Red', 'Blue' => 'Blue', 'Black' => 'Black', 'Silver/Gray' => 'Silver', 'Brown' => 'Brown'];
          $cur    = posted('dominant_color', 'N/A');
          foreach ($colors as $label => $val) {
            $sel = ($cur === $val) ? 'selected' : '';
            echo "<option value=\"{$val}\" {$sel}>{$label}</option>";
          }
        ?>
      </select>
    </div>
      
      </select>
    </div>

    <div class="actions field-full">
      <button class="btn primary" type="submit">Submit Lost Report</button>
    </div>

    <img id="ai_preview" alt="" style="display:none;max-width:0;max-height:0;">
<canvas id="ai_canvas" style="display:none;"></canvas>

  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.21.0/dist/tf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.3/dist/coco-ssd.min.js"></script>

<script>
(function(){
  const fileInput   = document.getElementById('item_image');
  const imgEl       = document.getElementById('ai_preview');
  const canvas      = document.getElementById('ai_canvas');
  const colorField  = document.getElementById('dominant_color');

  // Simple palette of readable colour names (RGB)
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
    let best = {name:'Gray', d: 1e9};
    for (const c of PALETTE) {
      const dr = r - c.r, dg = g - c.g, db = b - c.b;
      const d = dr*dr + dg*dg + db*db;
      if (d < best.d) best = {name: c.name, d};
    }
    return best.name;
  }

  function getDominantColorFromCanvas() {
    const ctx = canvas.getContext('2d', {willReadFrequently: true});
    const { width, height } = canvas;
    const data = ctx.getImageData(0, 0, width, height).data;

    const buckets = new Map();
    const step = 4 * 4; // sample every 4th pixel
    for (let i = 0; i < data.length; i += step) {
      const r = data[i], g = data[i+1], b = data[i+2], a = data[i+3];
      if (a < 16) continue;
      const R = r >> 4, G = g >> 4, B = b >> 4;
      const key = (R<<8) | (G<<4) | B;
      buckets.set(key, (buckets.get(key) || 0) + 1);
    }

    let bestKey = null, bestCount = -1;
    for (const [key, count] of buckets.entries()) {
      if (count > bestCount) { bestKey = key; bestCount = count; }
    }
    if (bestKey === null) return 'N/A';

    const Rb = (bestKey >> 8) & 0xF, Gb = (bestKey >> 4) & 0xF, Bb = bestKey & 0xF;
    let sumR=0, sumG=0, sumB=0, n=0;
    for (let i = 0; i < data.length; i += step) {
      const r = data[i], g = data[i+1], b = data[i+2], a = data[i+3];
      if (a < 16) continue;
      if ((r>>4)===Rb && (g>>4)===Gb && (b>>4)===Bb) {
        sumR+=r; sumG+=g; sumB+=b; n++;
      }
    }
    if (!n) return 'N/A';
    const avgR = Math.round(sumR/n), avgG = Math.round(sumG/n), avgB = Math.round(sumB/n);
    return nearestNamedColor(avgR, avgG, avgB);
  }

  async function analyze(file) {
    const url = URL.createObjectURL(file);
    imgEl.onload = async () => {
      const maxSide = 256;
      let w = imgEl.naturalWidth, h = imgEl.naturalHeight;
      const scale = Math.min(1, maxSide / Math.max(w, h));
      w = Math.max(1, Math.round(w * scale));
      h = Math.max(1, Math.round(h * scale));
      canvas.width = w; canvas.height = h;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(imgEl, 0, 0, w, h);

      try {
        const name = getDominantColorFromCanvas();
        if (name && name !== 'N/A') colorField.value = name;
      } catch (e) {
        console.warn('Color analysis failed:', e);
      }

      URL.revokeObjectURL(url);
    };
    imgEl.src = url;
  }

  fileInput.addEventListener('change', () => {
    if (!fileInput.files || fileInput.files.length === 0) {
      colorField.value = 'N/A';
      return;
    }
    const file = fileInput.files[0];
    if (!/^image\//.test(file.type)) {
      colorField.value = 'N/A';
      return;
    }
    analyze(file);
  });
})();
</script>


<?php include 'includes/footer.php'; ?>