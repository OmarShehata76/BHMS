<?php
// dashboard.php
require_once 'includes/auth.php';
Auth::check();
$db = db();

// KPI Stats
$stats = [
  'occupied'    => $db->fetchOne("SELECT COUNT(*) c FROM rooms WHERE status='Occupied'")['c']   ?? 0,
  'total_rooms' => $db->fetchOne("SELECT COUNT(*) c FROM rooms")['c']                           ?? 0,
  'checkins'    => $db->fetchOne("SELECT COUNT(*) c FROM reservations WHERE status='Confirmed' AND check_in_date=CURDATE()")['c'] ?? 0,
  'checkouts'   => $db->fetchOne("SELECT COUNT(*) c FROM reservations WHERE status='CheckedIn' AND check_out_date=CURDATE()")['c'] ?? 0,
  'hk_pending'  => $db->fetchOne("SELECT COUNT(*) c FROM hk_tasks WHERE status='Pending'")['c'] ?? 0,
  'revenue_today'=> $db->fetchOne("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='Completed' AND DATE(paid_at)=CURDATE()")['s'] ?? 0,
  'vip_today'   => $db->fetchOne("SELECT COUNT(*) c FROM reservations r JOIN guests g ON r.guest_id=g.guest_id WHERE g.vip_status=1 AND r.check_in_date=CURDATE()")['c'] ?? 0,
  'maintenance' => $db->fetchOne("SELECT COUNT(*) c FROM maintenance_requests WHERE status='Open'")['c'] ?? 0,
];
$occupancy_pct = $stats['total_rooms'] > 0 ? round($stats['occupied'] / $stats['total_rooms'] * 100) : 0;

// Recent reservations
$recent_res = $db->fetchAll("
  SELECT r.reservation_id, g.name guest_name, g.vip_status,
         rm.room_number, r.check_in_date, r.check_out_date, r.status
  FROM reservations r
  JOIN guests g ON r.guest_id=g.guest_id
  LEFT JOIN rooms rm ON r.room_id=rm.room_id
  ORDER BY r.created_at DESC LIMIT 8
");

// Room status summary
$room_status = $db->fetchAll("
  SELECT status, COUNT(*) cnt FROM rooms GROUP BY status ORDER BY cnt DESC
");

// Pending HK tasks
$hk_tasks = $db->fetchAll("
  SELECT h.task_id, rm.room_number, h.type, h.priority, h.status,
         s.name assigned_to
  FROM hk_tasks h
  JOIN rooms rm ON h.room_id=rm.room_id
  LEFT JOIN staff s ON h.assigned_to=s.staff_id
  WHERE h.status IN ('Pending','InProgress')
  ORDER BY h.priority DESC, h.created_at ASC
  LIMIT 6
");

function statusBadge(string $s): string {
  $map = [
    'Inquiry'     => 'badge-gray',
    'Confirmed'   => 'badge-blue',
    'CheckedIn'   => 'badge-green',
    'CheckedOut'  => 'badge-amber',
    'Cancelled'   => 'badge-red',
    'NoShow'      => 'badge-red',
    'FolioClosed' => 'badge-gold',
    'Pending'     => 'badge-amber',
    'InProgress'  => 'badge-blue',
    'Done'        => 'badge-green',
    'Ready'       => 'badge-green',
    'Dirty'       => 'badge-amber',
    'Occupied'    => 'badge-blue',
    'InCleaning'  => 'badge-gold',
    'Inspecting'  => 'badge-amber',
    'OutOfOrder'  => 'badge-red',
  ];
  $cls = $map[$s] ?? 'badge-gray';
  return "<span class='badge {$cls}'>{$s}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BHMS — Dashboard</title>
<link rel="stylesheet" href="css/style.css">
<style>
.occ-bar-wrap { background: var(--bg-panel); border-radius: 20px; height: 6px; margin-top: 10px; }
.occ-bar { height: 6px; border-radius: 20px; background: linear-gradient(90deg, var(--gold-dim), var(--gold)); transition: width 1s ease; }
.quick-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap: 20px; margin-top: 24px; }
.priority-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.p1 { background: var(--text-dim); }
.p2 { background: var(--amber); }
.p3 { background: var(--red); }
.p4 { background: #FF3B30; box-shadow: 0 0 8px rgba(255,59,48,0.5); }
.vip-star { color: var(--gold); margin-left: 4px; }
.time-badge {
  font-family: 'Cormorant Garamond', serif;
  font-size: 13px;
  color: var(--text-dim);
}
</style>
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>

  <div class="main">
    <!-- Topbar -->
    <div class="topbar">
      <div>
        <div class="topbar-title">Dashboard</div>
      </div>
      <div class="topbar-actions">
        <span class="time-badge" id="clock"></span>
        <a href="reservations.php?action=new" class="btn btn-gold btn-sm">+ New Reservation</a>
        <?php if (Auth::can(['Manager','Admin'])): ?>
        <a href="night_audit.php" class="btn btn-outline btn-sm">🌙 Night Audit</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Content -->
    <div class="content">

      <!-- KPI Stats -->
      <div class="stat-grid">

        <div class="stat-card">
          <div class="stat-icon">🏨</div>
          <div class="stat-label">Occupancy Rate</div>
          <div class="stat-value"><?= $occupancy_pct ?>%</div>
          <div class="occ-bar-wrap">
            <div class="occ-bar" style="width:<?= $occupancy_pct ?>%"></div>
          </div>
          <div style="font-size:11px;color:var(--text-dim);margin-top:6px">
            <?= $stats['occupied'] ?> / <?= $stats['total_rooms'] ?> rooms occupied
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">📥</div>
          <div class="stat-label">Today's Check-Ins</div>
          <div class="stat-value"><?= $stats['checkins'] ?></div>
          <div class="stat-change up">↑ Arrivals expected today</div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">📤</div>
          <div class="stat-label">Today's Check-Outs</div>
          <div class="stat-value"><?= $stats['checkouts'] ?></div>
          <div class="stat-change">Departures today</div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">💰</div>
          <div class="stat-label">Today's Revenue</div>
          <div class="stat-value" style="font-size:28px">
            EGP <?= number_format($stats['revenue_today'], 0) ?>
          </div>
          <div class="stat-change up">↑ Payments completed</div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">⭐</div>
          <div class="stat-label">VIP Arrivals Today</div>
          <div class="stat-value"><?= $stats['vip_today'] ?></div>
          <div class="stat-change" style="color:var(--gold)">★ Priority service required</div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">🧹</div>
          <div class="stat-label">HK Tasks Pending</div>
          <div class="stat-value"><?= $stats['hk_pending'] ?></div>
          <?php if ($stats['hk_pending'] > 0): ?>
          <div class="stat-change down">⚠ Requires attention</div>
          <?php else: ?>
          <div class="stat-change up">✓ All clear</div>
          <?php endif; ?>
        </div>

      </div><!-- /stat-grid -->

      <!-- Quick Panels -->
      <div class="quick-grid">

        <!-- Recent Reservations -->
        <div class="card" style="grid-column: span 2">
          <div class="card-header">
            <div class="card-title">Recent Reservations</div>
            <a href="reservations.php" class="btn btn-ghost btn-sm">View all →</a>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#ID</th>
                  <th>Guest</th>
                  <th>Room</th>
                  <th>Check-In</th>
                  <th>Check-Out</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recent_res as $r): ?>
                <tr>
                  <td style="color:var(--text-dim)">#<?= $r['reservation_id'] ?></td>
                  <td>
                    <?= htmlspecialchars($r['guest_name']) ?>
                    <?php if ($r['vip_status']): ?><span class="vip-star">★</span><?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($r['room_number'] ?? '—') ?></td>
                  <td><?= $r['check_in_date'] ?></td>
                  <td><?= $r['check_out_date'] ?></td>
                  <td><?= statusBadge($r['status']) ?></td>
                  <td>
                    <a href="reservations.php?id=<?= $r['reservation_id'] ?>" class="btn btn-ghost btn-sm">View</a>
                    <?php if ($r['status'] === 'Confirmed'): ?>
                    <a href="checkin.php?id=<?= $r['reservation_id'] ?>" class="btn btn-success btn-sm">Check-In</a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent_res)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-dim);padding:30px">No reservations found</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Room Status Summary -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">Room Status</div>
            <a href="rooms.php" class="btn btn-ghost btn-sm">Floor map →</a>
          </div>
          <div class="card-body">
            <?php
            $status_colors = [
              'Ready'      => 'var(--green)',
              'Occupied'   => 'var(--blue)',
              'Dirty'      => 'var(--amber)',
              'InCleaning' => 'var(--gold)',
              'Inspecting' => 'var(--amber)',
              'OutOfOrder' => 'var(--red)',
              'Clean'      => 'var(--green)',
            ];
            foreach ($room_status as $rs):
              $color = $status_colors[$rs['status']] ?? 'var(--text-dim)';
            ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
              <div style="display:flex;align-items:center;gap:10px">
                <span style="width:10px;height:10px;border-radius:50%;background:<?= $color ?>;display:inline-block"></span>
                <span style="font-size:13px;color:var(--text-primary)"><?= $rs['status'] ?></span>
              </div>
              <div style="display:flex;align-items:center;gap:10px">
                <div style="width:80px;height:4px;background:var(--bg-panel);border-radius:2px">
                  <div style="width:<?= min(100, $rs['cnt'] / $stats['total_rooms'] * 100) ?>%;height:4px;border-radius:2px;background:<?= $color ?>"></div>
                </div>
                <span style="font-family:'Cormorant Garamond',serif;font-size:18px;color:var(--text-primary);min-width:24px;text-align:right"><?= $rs['cnt'] ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Pending HK Tasks -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">Housekeeping</div>
            <a href="housekeeping.php" class="btn btn-ghost btn-sm">All tasks →</a>
          </div>
          <div class="card-body">
            <?php foreach ($hk_tasks as $t): ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
              <span class="priority-dot p<?= $t['priority'] ?>"></span>
              <div style="flex:1">
                <div style="font-size:13px;font-weight:500">Room <?= htmlspecialchars($t['room_number']) ?> — <?= $t['type'] ?></div>
                <div style="font-size:11px;color:var(--text-dim)"><?= htmlspecialchars($t['assigned_to'] ?? 'Unassigned') ?></div>
              </div>
              <?= statusBadge($t['status']) ?>
            </div>
            <?php endforeach; ?>
            <?php if (empty($hk_tasks)): ?>
            <div style="text-align:center;color:var(--green);padding:20px">✓ All rooms cleared</div>
            <?php endif; ?>
          </div>
        </div>

      </div><!-- /quick-grid -->

    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /wrapper -->

<!-- Toast container -->
<div class="toast-container" id="toasts"></div>

<script>
// Live clock
function tick() {
  const now = new Date();
  document.getElementById('clock').textContent =
    now.toLocaleDateString('en-GB', {weekday:'short', day:'2-digit', month:'short'}) +
    '  ' + now.toLocaleTimeString('en-GB', {hour:'2-digit', minute:'2-digit'});
}
tick(); setInterval(tick, 1000);

// Toast helper
function showToast(msg, type='info') {
  const icons = {success:'✓', error:'✕', info:'ℹ'};
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = `<span class="toast-icon">${icons[type]}</span><span class="toast-msg">${msg}</span>`;
  document.getElementById('toasts').appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

// Check for alerts from URL
const params = new URLSearchParams(location.search);
if (params.get('success')) showToast(params.get('success'), 'success');
if (params.get('error'))   showToast(params.get('error'),   'error');
</script>
</body>
</html>
