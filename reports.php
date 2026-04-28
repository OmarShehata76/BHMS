<?php
// reports.php — Reports & Analytics
require_once 'includes/auth.php';
Auth::requireRole(['Manager','Admin','Accountant']);
$db = db();

$type = $_GET['type'] ?? 'overview';
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Data by report type
$data = [];

if ($type === 'overview') {
    $data['revenue']    = $db->fetchOne("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='Completed' AND paid_at BETWEEN ? AND DATE_ADD(?,INTERVAL 1 DAY)", 'ss', $from, $to)['s'] ?? 0;
    $data['res_count']  = $db->fetchOne("SELECT COUNT(*) c FROM reservations WHERE created_at BETWEEN ? AND DATE_ADD(?,INTERVAL 1 DAY)", 'ss', $from, $to)['c'] ?? 0;
    $data['checkins']   = $db->fetchOne("SELECT COUNT(*) c FROM reservations WHERE status='CheckedIn' AND check_in_date BETWEEN ? AND ?", 'ss', $from, $to)['c'] ?? 0;
    $data['no_shows']   = $db->fetchOne("SELECT COUNT(*) c FROM reservations WHERE status='NoShow' AND check_in_date BETWEEN ? AND ?", 'ss', $from, $to)['c'] ?? 0;
    $data['avg_nights'] = $db->fetchOne("SELECT ROUND(AVG(DATEDIFF(check_out_date,check_in_date)),1) a FROM reservations WHERE check_in_date BETWEEN ? AND ?", 'ss', $from, $to)['a'] ?? 0;

    $data['daily'] = $db->fetchAll("
        SELECT DATE(paid_at) d, SUM(amount) revenue, COUNT(*) txn
        FROM payments WHERE status='Completed' AND paid_at BETWEEN ? AND DATE_ADD(?,INTERVAL 1 DAY)
        GROUP BY DATE(paid_at) ORDER BY d", 'ss', $from, $to);

    $data['by_type'] = $db->fetchAll("
        SELECT rt.name room_type, COUNT(r.reservation_id) cnt, COALESCE(SUM(f.total_amount),0) revenue
        FROM reservations r
        JOIN rooms rm ON r.room_id=rm.room_id
        JOIN room_types rt ON rm.type_id=rt.type_id
        LEFT JOIN folios f ON r.reservation_id=f.reservation_id
        WHERE r.check_in_date BETWEEN ? AND ?
        GROUP BY rt.type_id ORDER BY revenue DESC", 'ss', $from, $to);
}

if ($type === 'housekeeping') {
    $data['tasks'] = $db->fetchAll("
        SELECT s.name hk_name, COUNT(h.task_id) total, SUM(h.status='Done') done,
               ROUND(AVG(h.score),1) avg_score
        FROM hk_tasks h
        LEFT JOIN staff s ON h.assigned_to=s.staff_id
        WHERE h.created_at BETWEEN ? AND DATE_ADD(?,INTERVAL 1 DAY)
        GROUP BY h.assigned_to ORDER BY avg_score DESC", 'ss', $from, $to);
}

if ($type === 'guests') {
    $data['top_guests'] = $db->fetchAll("
        SELECT g.guest_id, g.name, g.email, g.vip_status, g.loyalty_tier,
               COUNT(r.reservation_id) stays,
               COALESCE(SUM(f.total_amount),0) total_spend
        FROM guests g
        LEFT JOIN reservations r ON g.guest_id=r.guest_id
        LEFT JOIN folios f ON r.reservation_id=f.reservation_id AND f.status='Closed'
        WHERE g.anonymized=0
        GROUP BY g.guest_id
        ORDER BY total_spend DESC LIMIT 20", '', );
    $data['nationality'] = $db->fetchAll("SELECT nationality, COUNT(*) cnt FROM guests WHERE nationality IS NOT NULL AND anonymized=0 GROUP BY nationality ORDER BY cnt DESC LIMIT 10");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>BHMS — Reports</title>
<link rel="stylesheet" href="css/style.css">
<style>
.chart-bar-wrap{display:flex;flex-direction:column;gap:8px}
.chart-bar-item{display:flex;align-items:center;gap:12px}
.chart-bar-label{min-width:90px;font-size:12px;color:var(--text-muted);text-align:right}
.chart-bar-track{flex:1;height:8px;background:var(--bg-panel);border-radius:4px}
.chart-bar-fill{height:8px;border-radius:4px;background:linear-gradient(90deg,var(--gold-dim),var(--gold))}
.chart-bar-val{min-width:70px;font-size:12px;color:var(--text-primary);text-align:right}
.mini-line{display:flex;align-items:flex-end;gap:3px;height:60px}
.mini-line-bar{flex:1;background:var(--gold);border-radius:2px 2px 0 0;opacity:.7;transition:height .3s ease;min-height:2px}
</style>
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">Reports & Analytics</div>
      <div class="topbar-actions">
        <button onclick="window.print()" class="btn btn-outline btn-sm">🖨 Print</button>
      </div>
    </div>
    <div class="content">

      <!-- Filter Form -->
      <form method="GET" style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap">
        <div style="display:flex;gap:8px">
          <?php foreach (['overview'=>'Overview','housekeeping'=>'Housekeeping','guests'=>'Guests'] as $k=>$v): ?>
          <a href="?type=<?= $k ?>&from=<?= $from ?>&to=<?= $to ?>" class="btn <?= $type===$k?'btn-gold':'btn-outline' ?> btn-sm"><?= $v ?></a>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="type" value="<?= $type ?>">
        <div style="display:flex;align-items:center;gap:8px">
          <label class="form-label" style="margin:0;white-space:nowrap">From</label>
          <input type="date" name="from" class="form-control" value="<?= $from ?>" style="width:auto">
          <label class="form-label" style="margin:0">To</label>
          <input type="date" name="to" class="form-control" value="<?= $to ?>" style="width:auto">
          <button type="submit" class="btn btn-outline btn-sm">Apply</button>
        </div>
      </form>

      <?php if ($type === 'overview'): ?>
      <!-- Overview KPIs -->
      <div class="stat-grid">
        <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-label">Revenue</div><div class="stat-value" style="font-size:26px">EGP <?= number_format($data['revenue']) ?></div></div>
        <div class="stat-card"><div class="stat-icon">📋</div><div class="stat-label">Reservations</div><div class="stat-value"><?= $data['res_count'] ?></div></div>
        <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-label">Check-Ins</div><div class="stat-value"><?= $data['checkins'] ?></div></div>
        <div class="stat-card"><div class="stat-icon">❌</div><div class="stat-label">No-Shows</div><div class="stat-value"><?= $data['no_shows'] ?></div></div>
        <div class="stat-card"><div class="stat-icon">🌙</div><div class="stat-label">Avg Nights</div><div class="stat-value"><?= $data['avg_nights'] ?></div></div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px">
        <!-- Daily Revenue Chart -->
        <div class="card">
          <div class="card-header"><div class="card-title">Daily Revenue</div></div>
          <div class="card-body">
            <?php if (!empty($data['daily'])):
              $max = max(array_column($data['daily'],'revenue')) ?: 1; ?>
            <div class="mini-line">
              <?php foreach ($data['daily'] as $d):
                $pct = min(100, $d['revenue'] / $max * 100); ?>
              <div class="mini-line-bar" style="height:<?= $pct ?>%" title="<?= $d['d'] ?>: EGP <?= number_format($d['revenue'],2) ?>"></div>
              <?php endforeach; ?>
            </div>
            <div style="margin-top:12px">
              <?php foreach (array_slice($data['daily'], -5) as $d): ?>
              <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted);margin-bottom:4px">
                <span><?= $d['d'] ?></span>
                <span style="color:var(--text-primary)">EGP <?= number_format($d['revenue'],2) ?></span>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?><div style="text-align:center;color:var(--text-dim);padding:20px">No revenue data</div><?php endif; ?>
          </div>
        </div>

        <!-- By Room Type -->
        <div class="card">
          <div class="card-header"><div class="card-title">Revenue by Room Type</div></div>
          <div class="card-body">
            <?php
            $max_rev = !empty($data['by_type']) ? max(array_column($data['by_type'],'revenue')) : 1;
            foreach ($data['by_type'] as $rt):
              $pct = $max_rev > 0 ? min(100, $rt['revenue'] / $max_rev * 100) : 0;
            ?>
            <div class="chart-bar-item" style="margin-bottom:14px">
              <span class="chart-bar-label"><?= htmlspecialchars($rt['room_type']) ?></span>
              <div class="chart-bar-track"><div class="chart-bar-fill" style="width:<?= $pct ?>%"></div></div>
              <span class="chart-bar-val">EGP <?= number_format($rt['revenue']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($data['by_type'])): ?><div style="text-align:center;color:var(--text-dim);padding:20px">No data</div><?php endif; ?>
          </div>
        </div>
      </div>

      <?php elseif ($type === 'housekeeping'): ?>
      <div class="card">
        <div class="card-header"><div class="card-title">Housekeeper Performance</div></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Staff Member</th><th>Total Tasks</th><th>Completed</th><th>Completion Rate</th><th>Avg Score</th></tr></thead>
            <tbody>
              <?php foreach ($data['tasks'] as $t):
                $rate = $t['total'] > 0 ? round($t['done'] / $t['total'] * 100) : 0;
              ?>
              <tr>
                <td><?= htmlspecialchars($t['hk_name'] ?? 'Unassigned') ?></td>
                <td><?= $t['total'] ?></td>
                <td><?= $t['done'] ?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:8px">
                    <div style="width:80px;height:4px;background:var(--bg-panel);border-radius:2px">
                      <div style="width:<?= $rate ?>%;height:4px;border-radius:2px;background:<?= $rate>=80?'var(--green)':($rate>=50?'var(--amber)':'var(--red)') ?>"></div>
                    </div>
                    <span style="font-size:12px;color:var(--text-muted)"><?= $rate ?>%</span>
                  </div>
                </td>
                <td><?php if ($t['avg_score']): ?><span style="font-weight:600;color:<?= $t['avg_score']>=8?'var(--green)':($t['avg_score']>=6?'var(--amber)':'var(--red)') ?>"><?= $t['avg_score'] ?>/10</span><?php else: ?>—<?php endif; ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($data['tasks'])): ?><tr><td colspan="5" style="text-align:center;color:var(--text-dim);padding:20px">No data</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php elseif ($type === 'guests'): ?>
      <div style="display:grid;grid-template-columns:1fr 280px;gap:20px">
        <div class="card">
          <div class="card-header"><div class="card-title">Top Guests by Lifetime Value</div></div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Guest</th><th>Tier</th><th>Stays</th><th>Total Spend</th></tr></thead>
              <tbody>
                <?php foreach ($data['top_guests'] as $i=>$g): ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:8px">
                      <span style="font-family:'Cormorant Garamond',serif;font-size:16px;color:var(--text-dim);min-width:20px"><?= $i+1 ?>.</span>
                      <div>
                        <div><?= htmlspecialchars($g['name']) ?><?php if ($g['vip_status']): ?><span style="color:var(--gold)"> ★</span><?php endif; ?></div>
                        <div style="font-size:11px;color:var(--text-dim)"><?= htmlspecialchars($g['email']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td><span class="badge badge-gray"><?= $g['loyalty_tier'] ?></span></td>
                  <td><?= $g['stays'] ?></td>
                  <td style="font-weight:600">EGP <?= number_format($g['total_spend']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">By Nationality</div></div>
          <div class="card-body chart-bar-wrap">
            <?php $max_n = !empty($data['nationality']) ? max(array_column($data['nationality'],'cnt')) : 1;
            foreach ($data['nationality'] as $n):
              $pct = min(100, $n['cnt'] / $max_n * 100); ?>
            <div class="chart-bar-item">
              <span class="chart-bar-label"><?= htmlspecialchars($n['nationality']) ?></span>
              <div class="chart-bar-track"><div class="chart-bar-fill" style="width:<?= $pct ?>%"></div></div>
              <span class="chart-bar-val"><?= $n['cnt'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($data['nationality'])): ?><div style="text-align:center;color:var(--text-dim)">No data</div><?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
</body></html>
