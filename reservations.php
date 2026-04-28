<?php
// reservations.php — Full Reservation Management
require_once 'includes/auth.php';
Auth::check();
$db = db();

$action  = $_GET['action'] ?? 'list';
$res_id  = (int)($_GET['id'] ?? 0);
$msg     = '';
$error   = '';

// ── Handle POST Actions ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';

    // CREATE
    if ($post_action === 'create') {
        $guest_id     = (int)$_POST['guest_id'];
        $room_id      = (int)$_POST['room_id'];
        $check_in     = $_POST['check_in_date'];
        $check_out    = $_POST['check_out_date'];
        $adults       = (int)$_POST['adults'];
        $children     = (int)$_POST['children'];
        $special      = trim($_POST['special_request'] ?? '');

        if (!$guest_id || !$check_in || !$check_out) {
            $error = 'Please fill all required fields.';
        } else {
            $new_id = $db->insert(
                "INSERT INTO reservations
                 (guest_id, room_id, check_in_date, check_out_date, adults, children, special_request, status, created_by)
                 VALUES (?,?,?,?,?,?,?,'Inquiry',?)",
                'iiissiisi',
                $guest_id, $room_id ?: null, $check_in, $check_out,
                $adults, $children, $special, Auth::id()
            );
            // Create folio
            $db->insert(
                "INSERT INTO folios (reservation_id, status) VALUES (?, 'Open')",
                'i', $new_id
            );
            // Log state
            $db->insert(
                "INSERT INTO reservation_state_log (reservation_id, from_state, to_state, changed_by)
                 VALUES (?, NULL, 'Inquiry', ?)",
                'ii', $new_id, Auth::id()
            );
            AuditLogger::log('CREATE_RESERVATION', 'reservations', $new_id, null, compact('guest_id','room_id','check_in','check_out'));
            header("Location: reservations.php?id={$new_id}&success=Reservation+created"); exit;
        }
    }

    // CHANGE STATE
    if ($post_action === 'setState') {
        $rid       = (int)$_POST['reservation_id'];
        $new_state = $_POST['new_state'];
        $old       = $db->fetchOne("SELECT status FROM reservations WHERE reservation_id=?", 'i', $rid);
        $allowed   = [
            'Inquiry'     => ['Confirmed','Cancelled'],
            'Confirmed'   => ['CheckedIn','Cancelled','NoShow'],
            'CheckedIn'   => ['CheckedOut'],
            'CheckedOut'  => ['FolioClosed'],
        ];
        if ($old && in_array($new_state, $allowed[$old['status']] ?? [])) {
            $db->execute("UPDATE reservations SET status=?, updated_at=NOW() WHERE reservation_id=?", 'si', $new_state, $rid);
            $db->insert("INSERT INTO reservation_state_log (reservation_id, from_state, to_state, changed_by) VALUES (?,?,?,?)",
                        'sssi', $rid, $old['status'], $new_state, Auth::id());

            // If CheckedIn: mark room as Occupied
            if ($new_state === 'CheckedIn') {
                $res = $db->fetchOne("SELECT room_id FROM reservations WHERE reservation_id=?", 'i', $rid);
                if ($res['room_id']) {
                    $db->execute("UPDATE rooms SET status='Occupied' WHERE room_id=?", 'i', $res['room_id']);
                }
            }
            // If CheckedOut: mark room as Dirty, create HK task
            if ($new_state === 'CheckedOut') {
                $res = $db->fetchOne("SELECT room_id FROM reservations WHERE reservation_id=?", 'i', $rid);
                if ($res['room_id']) {
                    $db->execute("UPDATE rooms SET status='Dirty' WHERE room_id=?", 'i', $res['room_id']);
                    $db->insert("INSERT INTO hk_tasks (room_id, type, status, priority) VALUES (?, 'Cleaning', 'Pending', 3)",
                                'i', $res['room_id']);
                }
            }
            AuditLogger::log("STATE_{$new_state}", 'reservations', $rid, $old['status'], $new_state);
            header("Location: reservations.php?id={$rid}&success=Status+updated+to+{$new_state}"); exit;
        }
        $error = 'Invalid state transition.';
    }
}

// ── Data ─────────────────────────────────────────
// Single reservation view
$reservation = null;
$folio       = null;
$state_log   = [];
$charges     = [];

if ($res_id) {
    $reservation = $db->fetchOne("
        SELECT r.*, g.name guest_name, g.email, g.vip_status, g.loyalty_points,
               rm.room_number, rt.name room_type, rt.base_price
        FROM reservations r
        JOIN guests g ON r.guest_id=g.guest_id
        LEFT JOIN rooms rm ON r.room_id=rm.room_id
        LEFT JOIN room_types rt ON rm.type_id=rt.type_id
        WHERE r.reservation_id=?", 'i', $res_id);
    if ($reservation) {
        $folio = $db->fetchOne("SELECT * FROM folios WHERE reservation_id=?", 'i', $res_id);
        $state_log = $db->fetchAll("
            SELECT sl.*, s.name changed_by_name
            FROM reservation_state_log sl
            LEFT JOIN staff s ON sl.changed_by=s.staff_id
            WHERE sl.reservation_id=? ORDER BY sl.changed_at ASC", 'i', $res_id);
        if ($folio) {
            $charges = $db->fetchAll("
                SELECT * FROM folio_charges WHERE folio_id=? ORDER BY charged_at DESC",
                'i', $folio['folio_id']);
        }
    }
}

// List
$filter_status = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');
$where = "WHERE 1=1";
$params = [];
$types  = '';
if ($filter_status) { $where .= " AND r.status=?"; $params[] = $filter_status; $types .= 's'; }
if ($search)        { $where .= " AND (g.name LIKE ? OR g.email LIKE ? OR rm.room_number LIKE ?)";
                      $like = "%{$search}%"; $params = array_merge($params, [$like,$like,$like]); $types .= 'sss'; }

$reservations = $db->fetchAll("
    SELECT r.reservation_id, g.name guest_name, g.vip_status,
           rm.room_number, r.check_in_date, r.check_out_date, r.status,
           DATEDIFF(r.check_out_date, r.check_in_date) nights
    FROM reservations r
    JOIN guests g ON r.guest_id=g.guest_id
    LEFT JOIN rooms rm ON r.room_id=rm.room_id
    {$where}
    ORDER BY r.created_at DESC LIMIT 50",
    $types, ...$params
);

// Dropdowns
$guests     = $db->fetchAll("SELECT guest_id, name, email FROM guests WHERE blacklisted=0 ORDER BY name");
$avail_rooms = $db->fetchAll("SELECT rm.room_id, rm.room_number, rm.floor, rt.name type_name, rt.base_price FROM rooms rm JOIN room_types rt ON rm.type_id=rt.type_id WHERE rm.status='Ready' ORDER BY rm.room_number");

function statusBadge($s) {
  $map = ['Inquiry'=>'badge-gray','Confirmed'=>'badge-blue','CheckedIn'=>'badge-green',
          'CheckedOut'=>'badge-amber','Cancelled'=>'badge-red','NoShow'=>'badge-red','FolioClosed'=>'badge-gold'];
  return "<span class='badge ".($map[$s]??'badge-gray')."'>{$s}</span>";
}

$state_transitions = [
    'Inquiry'   => ['Confirmed','Cancelled'],
    'Confirmed' => ['CheckedIn','Cancelled','NoShow'],
    'CheckedIn' => ['CheckedOut'],
    'CheckedOut'=> ['FolioClosed'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BHMS — Reservations</title>
<link rel="stylesheet" href="css/style.css">
<style>
.filter-bar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:20px; }
.state-timeline { display:flex; flex-direction:column; gap:0; }
.state-item { display:flex; align-items:flex-start; gap:14px; position:relative; padding-bottom:18px; }
.state-item:last-child { padding-bottom:0; }
.state-dot { width:10px; height:10px; border-radius:50%; background:var(--gold); margin-top:4px; flex-shrink:0; }
.state-line { position:absolute; left:4px; top:14px; bottom:0; width:2px; background:var(--border); }
.state-item:last-child .state-line { display:none; }
.nights-chip {
  background:var(--bg-panel);
  border:1px solid var(--border);
  border-radius:20px;
  padding:2px 10px;
  font-size:11px;
  color:var(--text-muted);
}
.detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.detail-item .label { font-size:11px; color:var(--text-dim); text-transform:uppercase; letter-spacing:0.08em; margin-bottom:4px; }
.detail-item .value { font-size:14px; color:var(--text-primary); font-weight:500; }
</style>
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>

  <div class="main">
    <div class="topbar">
      <div class="topbar-title">
        <?php if ($reservation): ?>
          Reservation #<?= $res_id ?>
        <?php elseif ($action==='new'): ?>
          New Reservation
        <?php else: ?>
          Reservations
        <?php endif; ?>
      </div>
      <div class="topbar-actions">
        <?php if ($reservation || $action==='new'): ?>
        <a href="reservations.php" class="btn btn-outline btn-sm">← Back to list</a>
        <?php else: ?>
        <a href="reservations.php?action=new" class="btn btn-gold btn-sm">+ New Reservation</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="content">
      <?php if ($msg): ?>
      <div style="background:var(--green-dim);border:1px solid rgba(46,204,143,0.3);color:var(--green);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
      <div style="background:var(--red-dim);border:1px solid rgba(224,92,92,0.3);color:var(--red);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php // ═══════ NEW RESERVATION FORM ═══════
      if ($action === 'new'): ?>
      <div class="card">
        <div class="card-header"><div class="card-title">Create New Reservation</div></div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Guest *</label>
                <select name="guest_id" class="form-control" required>
                  <option value="">— Select guest —</option>
                  <?php foreach ($guests as $g): ?>
                  <option value="<?= $g['guest_id'] ?>"><?= htmlspecialchars($g['name']) ?> (<?= htmlspecialchars($g['email']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Room (optional — auto-assign)</label>
                <select name="room_id" class="form-control">
                  <option value="">— Auto Allocate —</option>
                  <?php foreach ($avail_rooms as $rm): ?>
                  <option value="<?= $rm['room_id'] ?>">
                    <?= $rm['room_number'] ?> — <?= $rm['type_name'] ?> (EGP <?= number_format($rm['base_price']) ?>/night)
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Check-In Date *</label>
                <input type="date" name="check_in_date" class="form-control"
                       min="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Check-Out Date *</label>
                <input type="date" name="check_out_date" class="form-control"
                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Adults</label>
                <input type="number" name="adults" class="form-control" value="1" min="1" max="6">
              </div>
              <div class="form-group">
                <label class="form-label">Children</label>
                <input type="number" name="children" class="form-control" value="0" min="0" max="6">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Special Request</label>
              <textarea name="special_request" class="form-control" rows="3"
                        placeholder="Dietary needs, room preferences, special occasions..."></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
              <a href="reservations.php" class="btn btn-outline">Cancel</a>
              <button type="submit" class="btn btn-gold">Create Reservation</button>
            </div>
          </form>
        </div>
      </div>

      <?php // ═══════ SINGLE RESERVATION ═══════
      elseif ($reservation): ?>
      <div style="display:grid;grid-template-columns:1fr 360px;gap:20px">

        <!-- Left: details + timeline -->
        <div style="display:flex;flex-direction:column;gap:20px">

          <!-- Header card -->
          <div class="card">
            <div class="card-header">
              <div>
                <div class="card-title">
                  <?= htmlspecialchars($reservation['guest_name']) ?>
                  <?php if ($reservation['vip_status']): ?>
                  <span style="color:var(--gold);margin-left:6px">★ VIP</span>
                  <?php endif; ?>
                </div>
                <div style="font-size:12px;color:var(--text-dim);margin-top:2px"><?= htmlspecialchars($reservation['email']) ?></div>
              </div>
              <?= statusBadge($reservation['status']) ?>
            </div>
            <div class="card-body">
              <div class="detail-grid">
                <div class="detail-item"><div class="label">Room</div><div class="value"><?= $reservation['room_number'] ?? 'Not assigned' ?></div></div>
                <div class="detail-item"><div class="label">Room Type</div><div class="value"><?= $reservation['room_type'] ?? '—' ?></div></div>
                <div class="detail-item"><div class="label">Check-In</div><div class="value"><?= $reservation['check_in_date'] ?></div></div>
                <div class="detail-item"><div class="label">Check-Out</div><div class="value"><?= $reservation['check_out_date'] ?></div></div>
                <div class="detail-item"><div class="label">Nights</div><div class="value"><?= (new DateTime($reservation['check_in_date']))->diff(new DateTime($reservation['check_out_date']))->days ?></div></div>
                <div class="detail-item"><div class="label">Guests</div><div class="value"><?= $reservation['adults'] ?> adults, <?= $reservation['children'] ?> children</div></div>
              </div>
              <?php if ($reservation['special_request']): ?>
              <hr class="divider">
              <div style="font-size:12px;color:var(--text-dim);margin-bottom:4px">Special Request</div>
              <div style="font-size:13px;color:var(--text-primary)"><?= htmlspecialchars($reservation['special_request']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <!-- State Transitions -->
          <?php $transitions = $state_transitions[$reservation['status']] ?? []; ?>
          <?php if (!empty($transitions)): ?>
          <div class="card">
            <div class="card-header"><div class="card-title">Update Status</div></div>
            <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap">
              <?php foreach ($transitions as $ns):
                $btnClass = match($ns) {
                  'CheckedIn'   => 'btn-success',
                  'Cancelled','NoShow' => 'btn-danger',
                  'CheckedOut'  => 'btn-outline',
                  default       => 'btn-gold',
                };
              ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="setState">
                <input type="hidden" name="reservation_id" value="<?= $res_id ?>">
                <input type="hidden" name="new_state" value="<?= $ns ?>">
                <button type="submit" class="btn <?= $btnClass ?>" onclick="return confirm('Change status to <?= $ns ?>?')">
                  → <?= $ns ?>
                </button>
              </form>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Folio Charges -->
          <?php if ($folio): ?>
          <div class="card">
            <div class="card-header">
              <div class="card-title">Folio #<?= $folio['folio_id'] ?></div>
              <div style="display:flex;align-items:center;gap:12px">
                <?= statusBadge($folio['status']) ?>
                <a href="folios.php?id=<?= $folio['folio_id'] ?>" class="btn btn-outline btn-sm">Manage →</a>
              </div>
            </div>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Description</th><th>Type</th><th>Amount</th><th>Date</th></tr></thead>
                <tbody>
                  <?php foreach ($charges as $c): ?>
                  <tr>
                    <td><?= htmlspecialchars($c['description']) ?></td>
                    <td><span class="badge badge-gray"><?= $c['charge_type'] ?></span></td>
                    <td style="font-weight:500">EGP <?= number_format($c['amount'], 2) ?></td>
                    <td style="color:var(--text-dim)"><?= date('d M', strtotime($c['charged_at'])) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($charges)): ?>
                  <tr><td colspan="4" style="text-align:center;color:var(--text-dim);padding:20px">No charges yet</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <div style="padding:14px 16px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:20px">
              <span style="color:var(--text-dim);font-size:13px">Total</span>
              <span style="font-family:'Cormorant Garamond',serif;font-size:20px;color:var(--text-primary)">
                EGP <?= number_format($folio['total_amount'], 2) ?>
              </span>
            </div>
          </div>
          <?php endif; ?>

        </div><!-- /left -->

        <!-- Right: State timeline -->
        <div>
          <div class="card">
            <div class="card-header"><div class="card-title">State History</div></div>
            <div class="card-body">
              <div class="state-timeline">
                <?php foreach ($state_log as $log): ?>
                <div class="state-item">
                  <div class="state-dot"></div>
                  <div class="state-line"></div>
                  <div>
                    <?php if ($log['from_state']): ?>
                    <div style="font-size:12px;color:var(--text-dim)"><?= $log['from_state'] ?> →</div>
                    <?php endif; ?>
                    <div style="font-size:13px;font-weight:500;color:var(--text-primary)"><?= $log['to_state'] ?></div>
                    <div style="font-size:11px;color:var(--text-dim)">
                      by <?= htmlspecialchars($log['changed_by_name'] ?? 'System') ?>
                      · <?= date('d M H:i', strtotime($log['changed_at'])) ?>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /two-col -->

      <?php // ═══════ RESERVATIONS LIST ═══════
      else: ?>

      <!-- Filter Bar -->
      <form method="GET" class="filter-bar">
        <div class="search-box" style="flex:1;min-width:200px">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" class="form-control" placeholder="Search guest, room..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <select name="status" class="form-control" style="width:auto">
          <option value="">All Statuses</option>
          <?php foreach (['Inquiry','Confirmed','CheckedIn','CheckedOut','Cancelled','NoShow','FolioClosed'] as $s): ?>
          <option value="<?= $s ?>" <?= $filter_status===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline">Filter</button>
        <a href="reservations.php" class="btn btn-ghost">Clear</a>
      </form>

      <div class="card">
        <div class="card-header">
          <div class="card-title">All Reservations</div>
          <span style="font-size:12px;color:var(--text-dim)"><?= count($reservations) ?> records</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th><th>Guest</th><th>Room</th>
                <th>Check-In</th><th>Check-Out</th><th>Nights</th>
                <th>Status</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reservations as $r): ?>
              <tr>
                <td style="color:var(--text-dim)"><?= $r['reservation_id'] ?></td>
                <td>
                  <?= htmlspecialchars($r['guest_name']) ?>
                  <?php if ($r['vip_status']): ?><span style="color:var(--gold)"> ★</span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($r['room_number'] ?? '—') ?></td>
                <td><?= $r['check_in_date'] ?></td>
                <td><?= $r['check_out_date'] ?></td>
                <td><span class="nights-chip"><?= $r['nights'] ?> nights</span></td>
                <td><?= statusBadge($r['status']) ?></td>
                <td>
                  <a href="reservations.php?id=<?= $r['reservation_id'] ?>" class="btn btn-ghost btn-sm">View</a>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($reservations)): ?>
              <tr><td colspan="8" style="text-align:center;color:var(--text-dim);padding:40px">No reservations found</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php endif; ?>

    </div><!-- /content -->
  </div><!-- /main -->
</div>

<div class="toast-container" id="toasts"></div>
<script>
const p = new URLSearchParams(location.search);
const toast = (msg, type) => {
  const icons = {success:'✓', error:'✕'};
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<span class="toast-icon">${icons[type]||'ℹ'}</span><span class="toast-msg">${msg}</span>`;
  document.getElementById('toasts').appendChild(el);
  setTimeout(() => el.remove(), 4000);
};
if (p.get('success')) toast(p.get('success'), 'success');
if (p.get('error'))   toast(p.get('error'),   'error');
</script>
</body>
</html>
