<?php
include_once 'includes/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header('Location: index.php'); exit;
}

/* header + css */
$page_css   = '<link rel="stylesheet" href="assets/dashboard.css?v=13">';
$body_class = 'dash';
include 'includes/header.php';

/* ---------- Filters ---------- */
$search_term = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$item_type   = isset($_GET['type'])   ? trim((string)$_GET['type'])   : '';

$where = [];
$bind  = [];
$types = "";

/* Search across name/desc/location/category */
if ($search_term !== '') {
  $where[] = "(item_name LIKE ? OR description LIKE ? OR location LIKE ? OR category LIKE ?)";
  $kw = "%{$search_term}%";
  array_push($bind, $kw, $kw, $kw, $kw);
  $types .= "ssss";
}

/* Item type filter (Lost/Found) */
if ($item_type === 'Lost' || $item_type === 'Found') {
  $where[] = "LOWER(item_type) = LOWER(?)";
  $bind[]  = $item_type; $types .= "s";
}

$where_sql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/* Sort by report_date, falling back to item_id */
$sql = "SELECT 
          item_id, item_type, item_name, category, description, location, item_image,
          COALESCE(report_date, item_id) AS sort_key,
          report_date, status
        FROM items
        $where_sql
        ORDER BY sort_key DESC";

$stmt = $conn->prepare($sql);
if ($stmt && $types !== "") $stmt->bind_param($types, ...$bind);

$result = null; $err = null;
if ($stmt) {
  if ($stmt->execute()) $result = $stmt->get_result();
  else $err = $stmt->error;
  $stmt->close();
} else {
  $err = $conn->error;
}
?>
<div class="wrap">
  <h1 class="page-title">Dashboard – Browse Items</h1>

  <form class="filter-bar" method="get" action="dashboard.php">
    <div class="field">
      <label for="search">Search Keywords</label>
      <input
        type="text"
        id="search"
        name="search"
        placeholder="e.g., wallet, keys, blue backpack"
        value="<?= htmlspecialchars($search_term) ?>">
    </div>

    <div class="field">
      <label for="type">Item Type</label>
      <select id="type" name="type">
        <option value="">All Items</option>
        <option value="Lost"  <?= $item_type === 'Lost'  ? 'selected' : '' ?>>Lost Items</option>
        <option value="Found" <?= $item_type === 'Found' ? 'selected' : '' ?>>Found Items</option>
      </select>
    </div>

    <div class="actions">
      <button class="btn primary" type="submit">Filter/Search</button>
      <a class="btn muted" href="dashboard.php">Reset</a>
    </div>
  </form>

  <?php if ($result && $result->num_rows > 0): ?>
    <section class="item-grid">
      <?php while ($row = $result->fetch_assoc()):
        $card_class = strtolower($row['item_type']);
        $img = !empty($row['item_image'])
          ? 'images/' . htmlspecialchars($row['item_image'])
          : 'assets/placeholder.png';

        $isFound    = strcasecmp($row['item_type'], 'Found') === 0;
        $isClaimed  = strcasecmp($row['status'] ?? '', 'Claimed') === 0;
        $claimUrl   = 'claim.php?item_id=' . (int)$row['item_id'];
      ?>
        <article class="item-card <?= $card_class ?>">
          <header class="item-head">
            <h3 class="item-title"><?= htmlspecialchars($row['item_name']) ?></h3>
            <span class="badge"><?= htmlspecialchars($row['item_type']) ?></span>
          </header>

          <img class="item-img" src="<?= $img ?>" alt="<?= htmlspecialchars($row['item_name']) ?>">

          <dl class="item-meta">
            <div>
              <dt>Category</dt>
              <dd><?= htmlspecialchars($row['category'] ?: '—') ?></dd>
            </div>
            <div>
              <dt>Location</dt>
              <dd><?= htmlspecialchars($row['location'] ?: '—') ?></dd>
            </div>
            <div>
              <dt>Status</dt>
              <dd><?= htmlspecialchars($row['status'] ?: '—') ?></dd>
            </div>
            <div>
              <dt>Date Reported</dt>
              <dd><?= htmlspecialchars($row['report_date'] ?: '—') ?></dd>
            </div>
          </dl>

          <p class="desc">
            <?= htmlspecialchars(mb_strimwidth((string)$row['description'], 0, 140, '…')) ?>
          </p>

          <?php if ($isFound): ?>
            <?php if ($isClaimed): ?>
              <button class="btn" type="button" disabled style="opacity:.6; cursor:not-allowed;">Already Claimed</button>
            <?php else: ?>
              <a class="btn primary" href="<?= $claimUrl ?>">View / Claim</a>
            <?php endif; ?>
          <?php endif; ?>
        </article>
      <?php endwhile; ?>
    </section>
  <?php else: ?>
    <div class="empty">
      <?php if ($err): ?>
        <p>Couldn’t load items (DB error): <?= htmlspecialchars($err) ?></p>
      <?php else: ?>
        <p>No items found matching your criteria. Try adjusting the filters.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Chatbot -->
<button id="chatbot-button" class="chat-fab" aria-label="Open chatbot">💬</button>
<div id="chatbot-modal" class="chatbot-modal" role="dialog" aria-modal="true" aria-label="L&F Assistant">
  <div class="chat-header">
    <span>L&F Assistant</span>
    <button class="chat-close" aria-label="Close">&times;</button>
  </div>
  <div id="chat-messages" class="chat-body">
    <div class="msg-bot"><strong>Bot:</strong> Hello! Describe the item (e.g., “iPhone 13”, “Wallets”, “How do I claim?”).</div>
  </div>
  <div class="chat-input-area">
    <input id="chat-input" type="text" placeholder="Type your message...">
    <button id="chat-send" class="btn primary" type="button">Send</button>
  </div>
</div>

<script>
  const fab   = document.getElementById('chatbot-button');
  const modal = document.getElementById('chatbot-modal');
  const closeBtn = modal.querySelector('.chat-close');
  const input = document.getElementById('chat-input');
  const feed  = document.getElementById('chat-messages');

  const openChat  = () => { modal.style.display = 'flex'; input.focus(); };
  const closeChat = () => { modal.style.display = 'none'; };
  fab.addEventListener('click', openChat);
  closeBtn.addEventListener('click', closeChat);

  document.getElementById('chat-send').addEventListener('click', sendMessage);
  input.addEventListener('keypress', e => { if (e.key === 'Enter') sendMessage(); });

  function appendMsg(cls, html) {
    const d = document.createElement('div');
    d.className = cls;
    d.innerHTML = html;
    feed.appendChild(d);
    feed.scrollTop = feed.scrollHeight;
  }
  function escapeHtml(s){
    return (s||'').replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
  }

  function renderMatch(m){
    const title = m.name || m.title || ('Item #' + (m.id ?? ''));
    const meta  = [
      (m.type ? `Type: ${escapeHtml(m.type)}` : ''),
      (m.category ? `Category: ${escapeHtml(m.category)}` : ''),
      (m.color ? `Color: ${escapeHtml(m.color)}` : ''),
      (m.location ? `Location: ${escapeHtml(m.location)}` : ''),
      (m.status ? `Status: ${escapeHtml(m.status)}` : '')
    ].filter(Boolean).join(' · ');

    const desc = m.description_snippet ? `<div>${escapeHtml(m.description_snippet)}</div>` : '';
    const link = m.link ? `<a href="${m.link}" class="btn btn-sm btn-success" style="margin-top:6px;">View / Claim</a>` : '';

    return `
      <li style="margin:8px 0; border:1px solid #e6e6e6; border-radius:10px; padding:10px;">
        <div style="font-weight:600">${escapeHtml(title)}</div>
        <div style="font-size:.9rem; color:#616161">${meta}</div>
        ${desc}
        ${link}
      </li>
    `;
  }

  function sendMessage(){
    const msg = (input.value || '').trim(); if (!msg) return;
    appendMsg('msg-user','<strong>You:</strong> ' + escapeHtml(msg));
    input.value='';

    const id='typing-'+Date.now();
    appendMsg('msg-bot', `<span id="${id}"><strong>Bot:</strong> *is thinking...*</span>`);

    fetch('chatbot_api.php', {
      method:'POST',
      headers:{ 'Content-Type':'application/x-www-form-urlencoded' },
      body:'message='+encodeURIComponent(msg)
    })
    .then(r => r.json())
    .then(data => {
      const t = document.getElementById(id); if (t) t.parentElement.remove();
      const responseText = (data && data.response) ? data.response : 'Okay.';
      appendMsg('msg-bot','<strong>Bot:</strong> ' + escapeHtml(responseText));
      const matches = (data && Array.isArray(data.matches)) ? data.matches : [];
      if (matches.length){
        let html = '<ul style="list-style:none; padding:0; margin:8px 0 0;">';
        for (const m of matches) html += renderMatch(m);
        html += '</ul>';
        appendMsg('msg-bot', html);
      }
    })
    .catch(() => {
      const t = document.getElementById(id); if (t) t.parentElement.remove();
      appendMsg('msg-bot','<strong>Bot:</strong> Sorry, I hit a system error. Check Network tab or PHP errors.');
    });
  }
</script>

<?php $conn->close(); ?>
