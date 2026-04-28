<?php
// feedback.php — Post-Stay Feedback Loop
require_once 'includes/auth.php';
Auth::check();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';

    if ($pa === 'submit') {
        $guest_id      = (int)$_POST['guest_id'];
        $reservation_id= (int)$_POST['reservation_id'];
        $rating        = (int)$_POST['rating'];
        $comment       = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            header("Location: feedback.php?error=Invalid+rating"); exit;
        }
        $id = $db->insert(
            "INSERT INTO feedback (guest_id,reservation_id,rating,comment) VALUES (?,?,?,?)",
            'iiis', $guest_id, $reservation_id, $rating, $comment
        );
        // Auto-flag if rating <= 2 (negative — could trigger service recovery)
        if ($rating <= 2) {
            $db->insert(
                "INSERT INTO audit_logs (staff_id,action,table_name,record_id,new_value) VALUES (?,?,?,?,?)",
                'issis', Auth::id(), 'NEGATIVE_FEEDBACK_ALERT', 'feedback', $id,
                json_encode(['rating'=>$rating,'comment'=>$comment])
            );
        }
        AuditLogger::log('SUBMIT_FEEDBACK','feedback',$id,null,compact('guest_id','rating'));
        header("Location: feedback.php?success=Feedback+recorded"); exit;
    }
}

// Data
$filter_rating = $_GET['rating'] ?? '';
$search        = trim($_GET['q'] ?? '');

$where  = "WHERE 1=1";
$params = [];
$types  = '';

if ($filter_rating) {
    $where  .= " AND fb.rating=?";
    $params[]= (int)$filter_rating;
    $types  .= 'i';
}
if ($search) {
    $where  .= " AND (g.name LIKE ? OR fb.comment LIKE ?)";
    $like    = "%{$search}%";
    $params  = array_merge($params, [$like, $like]);
    $types  .= 'ss';
}

$feedbacks = $db->fetchAll("
    SELECT fb.*, g.name guest_name, g.email, g.vip_status,
           rm.room_number, rt.name room_type,
           r.check_in_date, r.check_out_date
    FROM feedback fb
    JOIN guests g ON fb.guest_id=g.guest_id
    JOIN reservations r ON fb.reservation_id=r.reservation_id
    LEFT JOIN rooms rm ON r.room_id=rm.room_id
    LEFT JOIN room_types rt ON rm.type_id=rt.type_id
    {$where}
    ORDER BY fb.submitted_at DESC LIMIT 80",
    $types, ...$params
);

// Stats
$stats = [
    'total'   => $db->fetchOne("SELECT COUNT(*) c FROM feedback")['c'] ?? 0,
    'avg'     => $db->fetchOne("SELECT ROUND(AVG(rating),1) a FROM feedback")['a'] ?? 0,
    'positive'=> $db->fetchOne("SELECT COUNT(*) c FROM feedback WHERE rating >= 4")['c'] ?? 0,
    'negative'=> $db->fetchOne("SELECT COUNT(*) c FROM feedback WHERE rating <= 2")['c'] ?? 0,
];

// Rating distribution
$dist = $db->fetchAll("SELECT rating, COUNT(*) cnt FROM feedback GROUP BY rating ORDER BY rating DESC");

// Eligible reservations (checked-out, no feedback yet)
$eligible = $db->fetchAll("
    SELECT r.reservation_id, g.guest_id, g.name guest_name, rm.room_number, r.check_out_date
    FROM reservations r
    JOIN guests g ON r.guest_id=g.guest_id
    LEFT JOIN rooms rm ON r.room_id=rm.room_id
    WHERE r.status IN ('CheckedOut','FolioClosed')
    AND r.reservation_id NOT IN (SELECT reservation_id FROM feedback)
    ORDER BY r.check_out_date DESC LIMIT 30
");

function stars(int $rating): string {
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}
function ratingColor(int $r): string {
    return match(true) {
        $r >= 4 => 'var(--green)',
        $r == 3 => 'var(--amber)',
        default => 'var(--red)'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>BHMS — Feedback</title>
<link rel="stylesheet" href="css/style.css">
<style>
.stars-display { color: var(--gold); letter-spacing: 2px; font-size: 16px; }
.rating-bar-wrap { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
.rating-bar-track { flex:1; height:6px; background:var(--bg-panel); border-radius:3px; }
.rating-bar-fill { height:6px; border-radius:3px; background:var(--gold); }
.avg-score { font-family:'Cormorant Garamond',serif; font-size:56px; color:var(--text-primary); line-height:1; }
.comment-box { font-size:13px; color:var(--text-muted); font-style:italic; line-height:1.5; max-width:300px; }
.feedback-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); padding:18px; margin-bottom:12px; }
.feedback-card.negative { border-left:3px solid var(--red); }
.feedback-card.positive { border-left:3px solid var(--green); }
</style>
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">💬 Guest Feedback</div>
      <div class="topbar-actions">
        <button onclick="document.getElementById('addModal').classList.add('show')" class="btn btn-gold btn-sm">+ Record Feedback</button>
      </div>
    </div>
    <div class="content">

      <!-- Stats Row -->
      <div style="display:grid;grid-template-columns:200px 1fr;gap:20px;margin-bottom:28px">

        <!-- Average Score -->
        <div class="card" style="text-align:center;padding:24px">
          <div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.1em;margin-bottom:12px">Average Rating</div>
          <div class="avg-score"><?= $stats['avg'] ?: '—' ?></div>
          <div class="stars-display" style="margin-top:6px">
            <?= $stats['avg'] ? stars((int)round((float)$stats['avg'])) : '—' ?>
          </div>
          <div style="font-size:12px;color:var(--text-dim);margin-top:8px"><?= $stats['total'] ?> reviews</div>
        </div>

        <!-- Distribution -->
        <div class="card">
          <div class="card-header"><div class="card-title">Rating Distribution</div>
            <div style="display:flex;gap:10px">
              <span class="badge badge-green">Positive (4-5★): <?= $stats['positive'] ?></span>
              <span class="badge badge-red">Negative (1-2★): <?= $stats['negative'] ?></span>
            </div>
          </div>
          <div class="card-body">
            <?php
            $dist_map = array_column($dist, 'cnt', 'rating');
            $max_cnt  = $dist ? max(array_column($dist, 'cnt')) : 1;
            foreach ([5,4,3,2,1] as $star):
              $cnt = $dist_map[$star] ?? 0;
              $pct = $max_cnt > 0 ? ($cnt / $max_cnt * 100) : 0;
            ?>
            <div class="rating-bar-wrap">
              <span style="font-size:13px;color:var(--gold);min-width:32px"><?= $star ?>★</span>
              <div class="rating-bar-track">
                <div class="rating-bar-fill" style="width:<?= $pct ?>%"></div>
              </div>
              <span style="font-size:12px;color:var(--text-muted);min-width:28px;text-align:right"><?= $cnt ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Filter Bar -->
      <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
        <div class="search-box" style="flex:1;min-width:180px">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" class="form-control" placeholder="Search guest or comment..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <select name="rating" class="form-control" style="width:auto">
          <option value="">All Ratings</option>
          <?php for ($i=5;$i>=1;$i--): ?>
          <option value="<?= $i ?>" <?= $filter_rating==$i?'selected':'' ?>><?= $i ?> Star<?= $i>1?'s':'' ?></option>
          <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-outline">Filter</button>
        <a href="feedback.php" class="btn btn-ghost">Clear</a>
      </form>

      <!-- Feedback List -->
      <div style="display:flex;flex-direction:column;gap:0">
        <?php foreach ($feedbacks as $fb):
          $cardClass = $fb['rating'] >= 4 ? 'positive' : ($fb['rating'] <= 2 ? 'negative' : '');
        ?>
        <div class="feedback-card <?= $cardClass ?>">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px">
            <div style="flex:1">
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                <span style="font-weight:600;font-size:14px"><?= htmlspecialchars($fb['guest_name']) ?></span>
                <?php if ($fb['vip_status']): ?><span class="badge badge-gold">★ VIP</span><?php endif; ?>
                <span class="stars-display" style="font-size:14px;color:<?= ratingColor($fb['rating']) ?>"><?= stars($fb['rating']) ?></span>
              </div>
              <div style="font-size:12px;color:var(--text-dim);margin-bottom:8px">
                Room <?= htmlspecialchars($fb['room_number'] ?? '—') ?>
                · <?= $fb['room_type'] ?>
                · Stayed <?= $fb['check_in_date'] ?> → <?= $fb['check_out_date'] ?>
              </div>
              <?php if ($fb['comment']): ?>
              <div class="comment-box">"<?= htmlspecialchars($fb['comment']) ?>"</div>
              <?php endif; ?>
            </div>
            <div style="text-align:right;flex-shrink:0">
              <div style="font-family:'Cormorant Garamond',serif;font-size:32px;color:<?= ratingColor($fb['rating']) ?>"><?= $fb['rating'] ?>/5</div>
              <div style="font-size:11px;color:var(--text-dim)"><?= date('d M Y', strtotime($fb['submitted_at'])) ?></div>
              <?php if ($fb['rating'] <= 2): ?>
              <span class="badge badge-red" style="margin-top:6px">Needs Attention</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($feedbacks)): ?>
        <div style="text-align:center;color:var(--text-dim);padding:40px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius)">No feedback found</div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<!-- Add Feedback Modal -->
<div class="modal-backdrop" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Record Guest Feedback</div>
      <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('show')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="submit">
      <div class="modal-body">
        <!-- Select reservation -->
        <div class="form-group">
          <label class="form-label">Guest & Reservation *</label>
          <select name="reservation_id" class="form-control" required onchange="setGuest(this)">
            <option value="">— Select checked-out guest —</option>
            <?php foreach ($eligible as $e): ?>
            <option value="<?= $e['reservation_id'] ?>" data-guest="<?= $e['guest_id'] ?>">
              <?= htmlspecialchars($e['guest_name']) ?> — Room <?= $e['room_number'] ?> (<?= $e['check_out_date'] ?>)
            </option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="guest_id" id="guestIdField">
        </div>

        <!-- Star Rating -->
        <div class="form-group">
          <label class="form-label">Rating *</label>
          <div style="display:flex;gap:8px;margin-top:4px" id="starRow">
            <?php for ($i=1;$i<=5;$i++): ?>
            <label style="cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:4px">
              <input type="radio" name="rating" value="<?= $i ?>" style="display:none" <?= $i===5?'checked':'' ?>>
              <span class="star-btn" data-val="<?= $i ?>"
                style="font-size:28px;color:<?= $i<=5?'var(--gold)':'var(--text-dim)' ?>;cursor:pointer;transition:.15s"
                onclick="selectStar(<?= $i ?>)">★</span>
              <span style="font-size:10px;color:var(--text-dim)"><?= ['','Poor','Fair','Good','Great','Excellent'][$i] ?></span>
            </label>
            <?php endfor; ?>
          </div>
        </div>

        <!-- Comment -->
        <div class="form-group">
          <label class="form-label">Comment</label>
          <textarea name="comment" class="form-control" rows="4" placeholder="Guest feedback, suggestions, compliments or complaints..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addModal').classList.remove('show')">Cancel</button>
        <button type="submit" class="btn btn-gold">Save Feedback</button>
      </div>
    </form>
  </div>
</div>

<div class="toast-container" id="toasts"></div>
<script>
function setGuest(sel) {
  const opt = sel.options[sel.selectedIndex];
  document.getElementById('guestIdField').value = opt.dataset.guest || '';
}

function selectStar(val) {
  document.querySelectorAll('.star-btn').forEach((s, i) => {
    s.style.color = (i < val) ? 'var(--gold)' : 'var(--text-dim)';
    s.style.transform = (i < val) ? 'scale(1.15)' : 'scale(1)';
  });
  document.querySelectorAll('input[name="rating"]')[val-1].checked = true;
}
// Init stars
selectStar(5);

const p = new URLSearchParams(location.search);
const toast = (msg, type) => {
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<span class="toast-icon">${type==='success'?'✓':'✕'}</span><span class="toast-msg">${msg}</span>`;
  document.getElementById('toasts').appendChild(el);
  setTimeout(() => el.remove(), 4000);
};
if (p.get('success')) toast(p.get('success'), 'success');
if (p.get('error'))   toast(p.get('error'),   'error');
</script>
</body>
</html>
