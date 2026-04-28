<?php
// night_audit.php — Night Audit Simulator
require_once 'includes/auth.php';
Auth::requireRole(['NightAuditor','Manager','Admin']);
$db = db();

$result = null;
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';

    if ($pa === 'run_simulation') {
        $audit_date   = $_POST['audit_date'] ?? date('Y-m-d');
        $is_sim       = 1; // Always simulation mode — never touches live data
        $errors_found = 0;
        $report       = [];

        // 1. Get all checked-in reservations
        $active_res = $db->fetchAll("
            SELECT r.reservation_id, r.guest_id, r.room_id, f.folio_id, f.total_amount,
                   rt.base_price, g.name guest_name, rm.room_number
            FROM reservations r
            JOIN folios f ON r.reservation_id=f.reservation_id
            JOIN rooms rm ON r.room_id=rm.room_id
            JOIN room_types rt ON rm.type_id=rt.type_id
            JOIN guests g ON r.guest_id=g.guest_id
            WHERE r.status='CheckedIn'
        ");

        $total_simulated_revenue = 0;

        foreach ($active_res as $res) {
            // Simulate posting daily room rate
            $daily_charge = $res['base_price'];
            $tax          = round($daily_charge * 0.14, 2); // 14% VAT
            $total_charge = $daily_charge + $tax;
            $total_simulated_revenue += $total_charge;

            // Check folio balance
            $folio_charges_sum = $db->fetchOne("SELECT COALESCE(SUM(amount),0) s FROM folio_charges WHERE folio_id=?", 'i', $res['folio_id'])['s'] ?? 0;
            $expected = $res['total_amount'];
            $diff = abs($folio_charges_sum - $expected);
            $has_error = $diff > 0.01;
            if ($has_error) $errors_found++;

            $report[] = [
                'reservation_id' => $res['reservation_id'],
                'guest'          => $res['guest_name'],
                'room'           => $res['room_number'],
                'base_rate'      => $daily_charge,
                'tax'            => $tax,
                'total_charge'   => $total_charge,
                'folio_balance'  => $expected,
                'charges_sum'    => $folio_charges_sum,
                'balance_diff'   => $diff,
                'has_error'      => $has_error,
            ];
        }

        // No-show check
        $no_shows = $db->fetchAll("
            SELECT r.reservation_id, g.name guest_name
            FROM reservations r
            JOIN guests g ON r.guest_id=g.guest_id
            WHERE r.status='Confirmed' AND r.check_in_date < CURDATE()
        ");

        // Summary stats
        $summary = [
            'audit_date'          => $audit_date,
            'is_simulation'       => true,
            'active_stays'        => count($active_res),
            'simulated_revenue'   => $total_simulated_revenue,
            'errors_found'        => $errors_found,
            'no_shows'            => count($no_shows),
        ];

        // Save audit record (simulation only)
        $audit_id = $db->insert(
            "INSERT INTO night_audits (run_by, audit_date, is_simulation, status, total_revenue, errors_found, report_data)
             VALUES (?, ?, 1, 'Completed', ?, ?, ?)",
            'isdis',
            Auth::id(), $audit_date, $total_simulated_revenue, $errors_found,
            json_encode(compact('summary','report','no_shows'))
        );

        AuditLogger::log('NIGHT_AUDIT_SIM','night_audits',$audit_id,null,$summary);

        $result = compact('summary','report','no_shows','audit_id');
    }
}

// Past audits
$past_audits = $db->fetchAll("
    SELECT na.*, s.name run_by_name
    FROM night_audits na
    JOIN staff s ON na.run_by=s.staff_id
    ORDER BY na.created_at DESC LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>BHMS — Night Audit</title>
<link rel="stylesheet" href="css/style.css">
<style>
.audit-result{background:var(--bg-panel);border:1px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:20px}
.error-row td{background:rgba(224,92,92,.04)!important}
.ok-row td{background:rgba(46,204,143,.03)!important}
.sim-banner{background:rgba(201,168,76,.08);border:1px solid var(--border-gold);border-radius:var(--radius-sm);padding:12px 16px;color:var(--gold);font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:10px}
</style>
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">🌙 Night Audit Simulator</div>
    </div>
    <div class="content">

      <div class="sim-banner">
        🔒 <strong>Simulation Mode Only</strong> — This audit runs in a safe test environment. No live data will be modified.
      </div>

      <!-- Run Simulation -->
      <div class="card" style="margin-bottom:24px">
        <div class="card-header"><div class="card-title">Run Audit Simulation</div></div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="run_simulation">
            <div class="form-row" style="align-items:flex-end">
              <div class="form-group">
                <label class="form-label">Audit Date</label>
                <input type="date" name="audit_date" class="form-control" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="form-group">
                <button type="submit" class="btn btn-gold btn-lg" style="width:100%">▶ Run Simulation</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Simulation Result -->
      <?php if ($result): ?>
      <div class="audit-result">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
          <div>
            <div class="gold-line"></div>
            <div style="font-family:'Cormorant Garamond',serif;font-size:22px">Simulation Report #<?= $result['audit_id'] ?></div>
            <div style="font-size:12px;color:var(--text-dim)"><?= $result['summary']['audit_date'] ?></div>
          </div>
          <?php if ($result['summary']['errors_found'] > 0): ?>
          <span class="badge badge-red" style="font-size:14px"><?= $result['summary']['errors_found'] ?> Error(s) Found</span>
          <?php else: ?>
          <span class="badge badge-green" style="font-size:14px">✓ Balanced</span>
          <?php endif; ?>
        </div>

        <!-- KPI row -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
          <div style="text-align:center;padding:14px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm)">
            <div style="font-family:'Cormorant Garamond',serif;font-size:30px"><?= $result['summary']['active_stays'] ?></div>
            <div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.08em">Active Stays</div>
          </div>
          <div style="text-align:center;padding:14px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm)">
            <div style="font-family:'Cormorant Garamond',serif;font-size:22px">EGP <?= number_format($result['summary']['simulated_revenue'],2) ?></div>
            <div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.08em">Simulated Revenue</div>
          </div>
          <div style="text-align:center;padding:14px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm)">
            <div style="font-family:'Cormorant Garamond',serif;font-size:30px;color:<?= $result['summary']['errors_found']>0?'var(--red)':'var(--green)' ?>"><?= $result['summary']['errors_found'] ?></div>
            <div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.08em">Discrepancies</div>
          </div>
          <div style="text-align:center;padding:14px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm)">
            <div style="font-family:'Cormorant Garamond',serif;font-size:30px;color:<?= $result['summary']['no_shows']>0?'var(--amber)':'var(--green)' ?>"><?= $result['summary']['no_shows'] ?></div>
            <div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.08em">No-Shows</div>
          </div>
        </div>

        <!-- Folio Balance Report -->
        <div style="font-family:'Cormorant Garamond',serif;font-size:18px;margin-bottom:12px">Folio Balance Report</div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>#Res</th><th>Guest</th><th>Room</th><th>Base Rate</th><th>Tax (14%)</th><th>Total Charge</th><th>Folio Total</th><th>Diff</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($result['report'] as $r): ?>
              <tr class="<?= $r['has_error']?'error-row':'ok-row' ?>">
                <td><?= $r['reservation_id'] ?></td>
                <td><?= htmlspecialchars($r['guest']) ?></td>
                <td><?= $r['room'] ?></td>
                <td>EGP <?= number_format($r['base_rate'],2) ?></td>
                <td>EGP <?= number_format($r['tax'],2) ?></td>
                <td style="font-weight:500">EGP <?= number_format($r['total_charge'],2) ?></td>
                <td>EGP <?= number_format($r['folio_balance'],2) ?></td>
                <td style="color:<?= $r['has_error']?'var(--red)':'var(--green)' ?>;font-weight:600">
                  <?= $r['has_error'] ? '± EGP '.number_format($r['balance_diff'],2) : '✓' ?>
                </td>
                <td><?= $r['has_error'] ? '<span class="badge badge-red">Error</span>' : '<span class="badge badge-green">OK</span>' ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($result['report'])): ?>
              <tr><td colspan="9" style="text-align:center;color:var(--text-dim);padding:20px">No active stays to audit</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- No-Shows -->
        <?php if (!empty($result['no_shows'])): ?>
        <div style="margin-top:20px;padding:16px;background:rgba(240,160,64,.06);border:1px solid rgba(240,160,64,.2);border-radius:var(--radius-sm)">
          <div style="font-size:13px;font-weight:600;color:var(--amber);margin-bottom:10px">⚠ No-Show Reservations (Confirmed but past check-in date)</div>
          <?php foreach ($result['no_shows'] as $ns): ?>
          <div style="font-size:13px;color:var(--text-muted);margin-bottom:4px">
            #<?= $ns['reservation_id'] ?> — <?= htmlspecialchars($ns['guest_name']) ?>
            <a href="reservations.php?id=<?= $ns['reservation_id'] ?>" class="btn btn-ghost btn-sm" style="margin-left:8px">Handle →</a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div style="margin-top:20px;padding:12px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm);font-size:12px;color:var(--text-dim)">
          🔒 This simulation did not modify any live records. Data remains unchanged.
        </div>
      </div>
      <?php endif; ?>

      <!-- Past Audits -->
      <div class="card">
        <div class="card-header"><div class="card-title">Past Audit Runs</div></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>#</th><th>Date</th><th>Run By</th><th>Mode</th><th>Revenue</th><th>Errors</th><th>Status</th><th>Time</th></tr></thead>
            <tbody>
              <?php foreach ($past_audits as $a): ?>
              <tr>
                <td style="color:var(--text-dim)"><?= $a['audit_id'] ?></td>
                <td><?= $a['audit_date'] ?></td>
                <td><?= htmlspecialchars($a['run_by_name']) ?></td>
                <td><span class="badge badge-amber">Simulation</span></td>
                <td>EGP <?= number_format($a['total_revenue'],2) ?></td>
                <td style="color:<?= $a['errors_found']>0?'var(--red)':'var(--green)' ?>"><?= $a['errors_found'] ?></td>
                <td><span class="badge badge-green"><?= $a['status'] ?></span></td>
                <td style="font-size:12px;color:var(--text-dim)"><?= date('d M Y H:i',strtotime($a['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($past_audits)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-dim);padding:20px">No past audits</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
</body></html>
