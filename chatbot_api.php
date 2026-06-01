<?php
/**
 * chatbot_api.php — Lost & Found Assistant (color-aware)
 * - Intent: "I lost ..." → search item_type='Found'; "I found ..." → search 'Lost'
 * - Color extraction (white, black, blue, grey/gray, navy→blue, etc.)
 * - Two-pass search:
 *      Pass 1: color + keywords
 *      Pass 2 (fallback): keywords only (if pass 1 returns 0)
 * - Claim link only for Found items that are NOT already Claimed
 * - Logs query in audit_logs (best effort)
 */

include_once 'includes/config.php';
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  echo json_encode(["response" => "Please log in to use the assistant."]);
  exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
function reply($text, $matches = [], $extra = []) {
  echo json_encode(["response"=>$text,"matches"=>$matches,"extra"=>$extra]);
  exit;
}

/* ---------- Color dictionary & helpers ---------- */
$COLOR_SYNS = [
  'grey' => 'gray',
  'navy' => 'blue',
  'sky'  => 'blue',
  'aqua' => 'blue',
  'teal' => 'green',
  'maroon' => 'red',
  'burgundy' => 'red',
  'crimson' => 'red',
  'lime' => 'green',
  'gold' => 'yellow',
  'silver' => 'gray',
  'violet' => 'purple',
];

$COLOR_LIST = [
  'black','white','blue','red','green','yellow','gray','grey','purple','pink',
  'orange','brown','beige','cream','navy','maroon','teal','aqua','gold','silver',
  'burgundy','crimson','lime','violet','cyan','magenta'
];

function norm($s){ return trim(mb_strtolower($s ?? '')); }
function extract_color_token($raw, $COLOR_LIST, $COLOR_SYNS) {
  $msg = ' '.$raw.' '; // pad for regex word boundaries
  $re = '/\b(' . implode('|', array_map('preg_quote', $COLOR_LIST)) . ')\b/i';
  if (preg_match($re, $msg, $m)) {
    $c = strtolower($m[1]);
    if (isset($COLOR_SYNS[$c])) $c = $COLOR_SYNS[$c];
    if ($c === 'grey') $c = 'gray';
    return $c;
  }
  return null;
}

/* ---------- Input & audit ---------- */
$msg_raw = isset($_POST['message']) ? trim((string)$_POST['message']) : '';
if ($msg_raw === '') reply("Tell me what you lost or found (e.g., 'I lost my white iPhone 13').");

if ($user_id > 0) { // best-effort audit
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  if ($al = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)")) {
    $action = "Used Chatbot to search: " . mb_substr($msg_raw, 0, 120);
    $al->bind_param("iss", $user_id, $action, $ip);
    $al->execute(); $al->close();
  }
}

/* ---------- Intent ---------- */
$intent = null;
if (preg_match('/\b(lost|lose|missing)\b/i', $msg_raw))  $intent = 'lost';
if (preg_match('/\b(found|picked up|discovered)\b/i', $msg_raw)) $intent = $intent ?? 'found';
$type_to_search = ($intent === 'lost') ? 'Found' : (($intent === 'found') ? 'Lost' : null);

/* ---------- Keywords ---------- */
preg_match_all('/\b[0-9A-Za-z]{3,}\b/u', $msg_raw, $m);
$words = array_map('strtolower', $m[0] ?? []);
$stop  = ['i','my','the','a','an','in','at','by','to','on','of','and','for','was','is','am','were','it','this','that','with','near','around','from','there'];
$keywords = array_values(array_diff($words, $stop));

/* Extract color (normalized) */
$color = extract_color_token($msg_raw, $COLOR_LIST, $COLOR_SYNS);

/* If nothing useful, nudge */
if (!$type_to_search && empty($keywords)) {
  reply("Hi! Tell me what you lost/found (e.g., “I lost a blue phone near library”).");
}

/* ---------- Search builder (as a function so we can do two passes) ---------- */
function run_search(mysqli $conn, ?string $type_to_search, array $keywords, ?string $color, bool $enforceColor) {
  $where  = [];
  $params = [];
  $types  = "";

  if ($type_to_search) {
    $where[]  = "LOWER(item_type) = LOWER(?)";
    $params[] = $type_to_search;
    $types   .= "s";
  }

  // color-aware filter
  if ($enforceColor && $color) {
    // Match explicit tags like "[COLOR: White]" OR plain mentions
    $where[] = "(
        description REGEXP CONCAT('\\\\[COLOR: *', ?, ' *\\\\]') 
        OR item_name LIKE ?
        OR description LIKE ?
      )";
    $params[] = $color;
    $kwc = "%$color%";
    $params[] = $kwc; $params[] = $kwc;
    $types   .= "sss";
  }

  if (!empty($keywords)) {
    $likes = [];
    foreach ($keywords as $kw) {
      $likes[] = "(item_name LIKE ? OR description LIKE ? OR category LIKE ? OR location LIKE ?)";
      $k = "%$kw%";
      array_push($params, $k,$k,$k,$k);
      $types .= "ssss";
    }
    $where[] = '(' . implode(' OR ', $likes) . ')';
  }

  $sql = "SELECT item_id, item_type, item_name, category, description, location, status, report_date
          FROM items
          " . ($where ? "WHERE ".implode(" AND ", $where) : "") . "
          ORDER BY COALESCE(report_date, item_id) DESC
          LIMIT 10";

  $stmt = $conn->prepare($sql);
  if (!$stmt) return ['err' => "Database prepare failed: ".$conn->error];

  if (!empty($params)) $stmt->bind_param($types, ...$params);
  if (!$stmt->execute()) return ['err' => "Database execute failed: ".$stmt->error];

  $res = $stmt->get_result();
  $rows = [];
  while ($r = $res->fetch_assoc()) {
    $isFound   = strcasecmp($r['item_type'], 'Found') === 0;
    $isClaimed = strcasecmp($r['status'] ?? '', 'Claimed') === 0;

    $link = null;
    if ($isFound && !$isClaimed) $link = "claim.php?item_id=".(int)$r['item_id'];

    $rows[] = [
      "id"        => (int)$r['item_id'],
      "name"      => $r['item_name'],
      "type"      => $r['item_type'],
      "category"  => $r['category'],
      "location"  => $r['location'],
      "status"    => $r['status'],
      "description_snippet" => mb_strimwidth((string)$r['description'], 0, 80, '…'),
      "link"      => $link
    ];
  }
  $stmt->close();
  return ['rows'=>$rows];
}

/* ---------- Pass 1: enforce color (if any) ---------- */
$pass1 = run_search($conn, $type_to_search, $keywords, $color, true);
if (isset($pass1['err'])) reply($pass1['err']);

if (!empty($pass1['rows'])) {
  $intro = ($intent === 'lost')
          ? "I searched our Found items".($color ? " (color: $color)" : "")." and found ".count($pass1['rows'])." possible match(es):"
          : (($intent === 'found')
              ? "I searched Lost reports".($color ? " (color: $color)" : "")." and found ".count($pass1['rows'])." possible match(es):"
              : "Here are some items that match".($color ? " (color: $color)" : "").":");
  reply($intro, $pass1['rows'], ["pass"=>"color+keywords"]);
}

/* ---------- Pass 2: broaden (ignore color) if nothing found ---------- */
$pass2 = run_search($conn, $type_to_search, $keywords, null, false);
if (isset($pass2['err'])) reply($pass2['err']);

if (!empty($pass2['rows'])) {
  $intro = ($intent === 'lost')
          ? "No exact color match; I broadened the search and found ".count($pass2['rows'])." possible match(es):"
          : (($intent === 'found')
              ? "No exact color match; I broadened the Lost reports and found ".count($pass2['rows'])." possible match(es):"
              : "No exact color match; here are broader matches:");
  reply($intro, $pass2['rows'], ["pass"=>"keywords-only","color_attempted"=>$color]);
}

/* ---------- Nothing found ---------- */
if ($intent === 'lost')  reply("Your item has not been reported found yet.".($color ? " (color tried: $color)" : ""));
if ($intent === 'found') reply("No one has reported losing that yet. Please log it under 'Report Found'.");
reply("I couldn’t find anything. Try a bit more detail (color, place, item).");
