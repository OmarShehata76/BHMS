<?php
// folios.php — Folio & Billing Management
require_once 'includes/auth.php';
Auth::check();
$db = db();

$folio_id = (int)($_GET['id'] ?? 0);
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';

    if ($pa === 'add_charge') {
        $fid   = (int)$_POST['folio_id'];
        $desc  = trim($_POST['description']);
        $amt   = (float)$_POST['amount'];
        $type  = $_POST['charge_type'];
        $db->insert("INSERT INTO folio_charges (folio_id, description, amount, charge_type, charged_by) VALUES (?,?,?,?,?)",
                    'isdsi', $fid, $desc, $amt, $type, Auth::id());
        // Recalculate total
        $total = $db->fetchOne("SELECT COALESCE(SUM(amount),0) s FROM folio_charges WHERE folio_id=?", 'i', $fid)['s'];
        $tax   = round($total * 0.14, 2);
        $db->execute("UPDATE folios SET total_amount=?, tax_amount=? WHERE folio_id=?", 'ddi', $total+$tax, $tax, $fid);
        AuditLogger::log('ADD_CHARGE','folio_charges',$fid,null,compact('desc','amt','type'));
        header("Location: folios.php?id={$fid}&success=Charge+added"); exit;
    }

    if ($pa === 'process_payment') {
        $fid    = (int)$_POST['folio_id'];
        $amount = (float)$_POST['amount'];
        $method = $_POST['method'];
        $curr   = $_POST['currency'] ?? 'EGP';
        $f_amt  = (float)($_POST['foreign_amount'] ?? 0);
        $rate   = (float)($_POST['exchange_rate']  ?? 1);

        $pay_id = $db->insert(
            "INSERT INTO payments (folio_id,amount,foreign_amount,exchange_rate,currency,method,status,processed_by)
             VALUES (?,?,?,?,?,?,'Completed',?)",
            'idddssi', $fid, $amount, $f_amt ?: null, $rate ?: null, $curr, $method, Auth::id()
        );
        // Check if fully paid
        $total_paid = $db->fetchOne("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE folio_id=? AND status='Completed'", 'i', $fid)['s'];
        $folio = $db->fetchOne("SELECT total_amount FROM folios WHERE folio_id=?", 'i', $fid);
        if ($total_paid >= $folio['total_amount']) {
            $db->execute("UPDATE folios SET status='Closed', closed_at=NOW() WHERE folio_id=?", 'i', $fid);
            // Mark reservation as FolioClosed
            $res = $db->fetchOne("SELECT reservation_id FROM folios WHERE folio_id=?", 'i', $fid);
            $db->execute("UPDATE reservations SET status='FolioClosed' WHERE reservation_id=?", 'i', $res['reservation_id']);
        } else {
            $db->execute("UPDATE folios SET status='PartiallyPaid' WHERE folio_id=?", 'i', $fid);
        }
        AuditLogger::log('PROCESS_PAYMENT','payments',$pay_id,null,compact('amount','method','curr'));
        header("Location: folios.php?id={$fid}&success=Payment+processed"); exit;
    }

    if ($pa === 'close_folio') {
        $fid = (int)$_POST['folio_id'];
        $db->execute("UPDATE folios SET status='Closed', closed_at=NOW() WHERE folio_id=?", 'i', $fid);
        AuditLogger::log('CLOSE_FOLIO','folios',$fid);
        header("Location: folios.php?id={$fid}&success=Folio+closed"); exit;
    }
}

// Single folio
$folio = null; $charges = []; $payments = []; $reservation = null;
if ($folio_id) {
    $folio = $db->fetchOne("
        SELECT f.*, r.reservation_id, r.check_in_date, r.check_out_date, r.status res_status,
               g.name guest_name, g.email, g.vip_status,
               rm.room_number, rt.name room_type
        FROM folios f
        JOIN reservations r ON f.reservation_id=r.reservation_id
        JOIN guests g ON r.guest_id=g.guest_id
        LEFT JOIN rooms rm ON r.room_id=rm.room_id
        LEFT JOIN room_types rt ON rm.type_id=rt.type_id
        WHERE f.folio_id=?", 'i', $folio_id);
    if ($folio) {
        $charges  = $db->fetchAll("SELECT fc.*, s.name charged_by_name FROM folio_charges fc LEFT JOIN staff s ON fc.charged_by=s.staff_id WHERE folio_id=? ORDER BY charged_at DESC", 'i', $folio_id);
        $payments = $db->fetchAll("SELECT p.*, s.name processed_by_name FROM payments p LEFT JOIN staff s ON p.processed_by=s.staff_id WHERE folio_id=? ORDER BY paid_at DESC", 'i', $folio_id);
    }
}

// List
$folios = $db->fetchAll("
    SELECT f.folio_id, f.total_amount, f.status, f.currency, f.created_at,
           g.name guest_name, g.vip_status, rm.room_number, r.check_in_date, r.check_out_date
    FROM folios f
    JOIN reservations r ON f.reservation_id=r.reservation_id
    JOIN guests g ON r.guest_id=g.guest_id
    LEFT JOIN rooms rm ON r.room_id=rm.room_id
    ORDER BY f.created_at DESC LIMIT 60
");

$total_paid_on_folio = 0;
if ($folio_id && !empty($payments)) {
    $total_paid_on_folio = array_sum(array_column(array_filter($payments, fn($p)=>$p['status']==='Completed'), 'amount'));
}
$balance_due = $folio ? max(0, $folio['total_amount'] - $total_paid_on_folio) : 0;

function statusBadge($s) {
    $map=['Open'=>'badge-blue','PartiallyPaid'=>'badge-amber','Closed'=>'badge-green'];
    return "<span class='badge ".($map[$s]??'badge-gray')."'>{$s}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>BHMS — Folios</title>
<link rel="stylesheet" href="css/style.css">
<style>
.folio-total{font-family:'Cormorant Garamond',serif;font-size:36px;color:var(--text-primary)}
.pay-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)}
.pay-row:last-child{border-bottom:none}
@media print{.sidebar,.topbar,.btn,.modal-backdrop{display:none!important}.main{margin:0!important}.card{box-shadow:none!important;border:1px solid #ddd!important}}
</style>
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title"><?= $folio ? 'Folio #'.$folio_id : 'Folios & Billing' ?></div>
      <div class="topbar-actions">
        <?php if ($folio): ?>
        <a href="folios.php" class="btn btn-outline btn-sm">← Back</a>
        <button onclick="window.print()" class="btn btn-outline btn-sm">🖨 Print Invoice</button>
        <?php endif; ?>
      </div>
    </div>
    <div class="content">

      <?php if ($folio): ?>
      <!-- ═══ SINGLE FOLIO ═══ -->

      <!-- Print header (hidden on screen) -->
      <div style="display:none" class="print-header">
        <h1 style="font-family:serif">BHMS — Invoice</h1>
        <p>Folio #<?= $folio_id ?> | <?= date('d M Y') ?></p>
        <hr>
      </div>

      <div style="display:grid;grid-template-columns:1fr 320px;gap:20px">
        <div style="display:flex;flex-direction:column;gap:20px">

          <!-- Guest / Reservation Info -->
          <div class="card">
            <div class="card-header">
              <div>
                <div class="card-title"><?= htmlspecialchars($folio['guest_name']) ?><?php if ($folio['vip_status']): ?><span style="color:var(--gold);margin-left:6px">★ VIP</span><?php endif; ?></div>
                <div style="font-size:12px;color:var(--text-dim)"><?= htmlspecialchars($folio['email']) ?></div>
              </div>
              <?= statusBadge($folio['status']) ?>
            </div>
            <div class="card-body">
              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                <div><div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.08em">Room</div><div style="font-size:14px;font-weight:500"><?= $folio['room_number'] ?? '—' ?></div></div>
                <div><div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.08em">Check-In</div><div style="font-size:14px;font-weight:500"><?= $folio['check_in_date'] ?></div></div>
                <div><div style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.08em">Check-Out</div><div style="font-size:14px;font-weight:500"><?= $folio['check_out_date'] ?></div></div>
              </div>
            </div>
          </div>

          <!-- Charges -->
          <div class="card">
            <div class="card-header">
              <div class="card-title">Charges</div>
              <?php if ($folio['status'] !== 'Closed'): ?>
              <button onclick="document.getElementById('addChargeModal').classList.add('show')" class="btn btn-outline btn-sm">+ Add Charge</button>
              <?php endif; ?>
            </div>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Description</th><th>Type</th><th>Charged By</th><th>Date</th><th>Amount</th></tr></thead>
                <tbody>
                  <?php foreach ($charges as $c): ?>
                  <tr>
                    <td><?= htmlspecialchars($c['description']) ?></td>
                    <td><span class="badge badge-gray"><?= $c['charge_type'] ?></span></td>
                    <td style="color:var(--text-muted);font-size:12px"><?= htmlspecialchars($c['charged_by_name'] ?? 'System') ?></td>
                    <td style="color:var(--text-dim);font-size:12px"><?= date('d M Y H:i', strtotime($c['charged_at'])) ?></td>
                    <td style="font-weight:600;text-align:right">EGP <?= number_format($c['amount'],2) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($charges)): ?>
                  <tr><td colspan="5" style="text-align:center;color:var(--text-dim);padding:24px">No charges yet</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <!-- Totals -->
            <div style="padding:16px 20px;border-top:1px solid var(--border)">
              <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--text-muted);margin-bottom:6px">
                <span>Subtotal</span>
                <span>EGP <?= number_format($folio['total_amount'] - $folio['tax_amount'], 2) ?></span>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--text-muted);margin-bottom:10px">
                <span>Tax (14% VAT)</span>
                <span>EGP <?= number_format($folio['tax_amount'], 2) ?></span>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border);padding-top:10px">
                <span style="font-size:13px;font-weight:600;color:var(--text-primary)">Total</span>
                <span style="font-family:'Cormorant Garamond',serif;font-size:28px;color:var(--text-primary)">EGP <?= number_format($folio['total_amount'],2) ?></span>
              </div>
            </div>
          </div>

          <!-- Payments -->
          <div class="card">
            <div class="card-header"><div class="card-title">Payments</div></div>
            <div class="card-body">
              <?php foreach ($payments as $p): ?>
              <div class="pay-row">
                <div>
                  <div style="font-size:13px;font-weight:500"><?= $p['method'] ?> — <?= $p['currency'] ?></div>
                  <div style="font-size:11px;color:var(--text-dim)"><?= htmlspecialchars($p['processed_by_name'] ?? 'System') ?> · <?= date('d M Y H:i', strtotime($p['paid_at'])) ?></div>
                  <?php if ($p['foreign_amount']): ?>
                  <div style="font-size:11px;color:var(--text-dim)"><?= $p['foreign_amount'] ?> <?= $p['currency'] ?> @ <?= $p['exchange_rate'] ?></div>
                  <?php endif; ?>
                </div>
                <div style="text-align:right">
                  <div style="font-weight:600;color:var(--green)">EGP <?= number_format($p['amount'],2) ?></div>
                  <span class="badge badge-<?= $p['status']==='Completed'?'green':($p['status']==='Refunded'?'amber':'red') ?>"><?= $p['status'] ?></span>
                </div>
              </div>
              <?php endforeach; ?>
              <?php if (empty($payments)): ?>
              <div style="text-align:center;color:var(--text-dim);padding:16px">No payments recorded</div>
              <?php endif; ?>
            </div>
          </div>

        </div><!-- /left -->

        <!-- Right: Summary + Actions -->
        <div style="display:flex;flex-direction:column;gap:16px">
          <!-- Balance -->
          <div class="card">
            <div class="card-header"><div class="card-title">Balance</div></div>
            <div style="padding:24px;text-align:center">
              <div style="font-size:12px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px">Amount Due</div>
              <div class="folio-total" style="color:<?= $balance_due>0?'var(--red)':'var(--green)' ?>">
                EGP <?= number_format($balance_due, 2) ?>
              </div>
              <div style="margin-top:8px">
                <div style="font-size:12px;color:var(--text-dim)">Paid: EGP <?= number_format($total_paid_on_folio,2) ?></div>
                <div style="font-size:12px;color:var(--text-dim)">Total: EGP <?= number_format($folio['total_amount'],2) ?></div>
              </div>
            </div>
          </div>

          <!-- Process Payment -->
          <?php if ($folio['status'] !== 'Closed' && $balance_due > 0): ?>
          <div class="card">
            <div class="card-header"><div class="card-title">Process Payment</div></div>
            <div class="card-body">
              <form method="POST" id="payForm">
                <input type="hidden" name="action" value="process_payment">
                <input type="hidden" name="folio_id" value="<?= $folio_id ?>">
                <div class="form-group">
                  <label class="form-label">Method</label>
                  <select name="method" class="form-control" id="payMethod" onchange="toggleForeign()">
                    <option>Cash</option><option>CreditCard</option>
                    <option value="ForeignCash">Foreign Cash</option><option>Split</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Amount (EGP)</label>
                  <input type="number" name="amount" class="form-control" step="0.01" value="<?= $balance_due ?>" required>
                </div>
                <!-- Foreign currency section -->
                <div id="foreignSection" style="display:none">
                  <div style="border:1px solid var(--border-gold);border-radius:var(--radius-sm);padding:12px;margin-bottom:12px">
                    <div style="font-size:11px;color:var(--gold);margin-bottom:10px">Multi-Currency Settlement</div>
                    <div class="form-group">
                      <label class="form-label">Foreign Currency</label>
                      <select name="currency" class="form-control">
                        <option value="USD">USD</option><option value="EUR">EUR</option>
                        <option value="GBP">GBP</option><option value="SAR">SAR</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Foreign Amount</label>
                      <input type="number" name="foreign_amount" class="form-control" step="0.01" placeholder="0.00">
                    </div>
                    <div class="form-group">
                      <label class="form-label">Exchange Rate (to EGP)</label>
                      <input type="number" name="exchange_rate" class="form-control" step="0.0001" placeholder="e.g. 48.50">
                    </div>
                  </div>
                </div>
                <button type="submit" class="btn btn-gold" style="width:100%">Process Payment</button>
              </form>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($folio['status'] === 'Open' && $balance_due <= 0): ?>
          <form method="POST">
            <input type="hidden" name="action" value="close_folio">
            <input type="hidden" name="folio_id" value="<?= $folio_id ?>">
            <button type="submit" class="btn btn-gold btn-lg" style="width:100%">Close Folio ✓</button>
          </form>
          <?php endif; ?>
        </div>

      </div><!-- /two-col -->

      <!-- Add Charge Modal -->
      <div class="modal-backdrop" id="addChargeModal">
        <div class="modal">
          <div class="modal-header"><div class="modal-title">Add Charge</div>
            <button class="modal-close" onclick="document.getElementById('addChargeModal').classList.remove('show')">×</button>
          </div>
          <form method="POST">
            <input type="hidden" name="action" value="add_charge">
            <input type="hidden" name="folio_id" value="<?= $folio_id ?>">
            <div class="modal-body">
              <div class="form-group"><label class="form-label">Description *</label><input type="text" name="description" class="form-control" required placeholder="e.g. Spa massage, Room service..."></div>
              <div class="form-row">
                <div class="form-group"><label class="form-label">Amount (EGP) *</label><input type="number" name="amount" class="form-control" step="0.01" min="0.01" required></div>
                <div class="form-group"><label class="form-label">Type</label>
                  <select name="charge_type" class="form-control">
                    <option value="Room">Room</option><option value="Service">Service</option>
                    <option value="Minibar">Minibar</option><option value="Tax">Tax</option>
                    <option value="Penalty">Penalty</option><option value="Other">Other</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline" onclick="document.getElementById('addChargeModal').classList.remove('show')">Cancel</button>
              <button type="submit" class="btn btn-gold">Add Charge</button>
            </div>
          </form>
        </div>
      </div>

      <?php else: ?>
      <!-- ═══ FOLIO LIST ═══ -->
      <div class="card">
        <div class="card-header"><div class="card-title">All Folios</div></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>#</th><th>Guest</th><th>Room</th><th>Check-In</th><th>Check-Out</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($folios as $f): ?>
              <tr>
                <td style="color:var(--text-dim)">#<?= $f['folio_id'] ?></td>
                <td><?= htmlspecialchars($f['guest_name']) ?><?php if ($f['vip_status']): ?><span style="color:var(--gold)"> ★</span><?php endif; ?></td>
                <td><?= htmlspecialchars($f['room_number'] ?? '—') ?></td>
                <td><?= $f['check_in_date'] ?></td>
                <td><?= $f['check_out_date'] ?></td>
                <td style="font-weight:600">EGP <?= number_format($f['total_amount'],2) ?></td>
                <td><?= statusBadge($f['status']) ?></td>
                <td><a href="folios.php?id=<?= $f['folio_id'] ?>" class="btn btn-ghost btn-sm">View →</a></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($folios)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-dim);padding:30px">No folios found</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<div class="toast-container" id="toasts"></div>
<script>
function toggleForeign() {
  const m = document.getElementById('payMethod').value;
  document.getElementById('foreignSection').style.display = m === 'ForeignCash' ? 'block' : 'none';
}
const p = new URLSearchParams(location.search);
if (p.get('success')) {
  const el = document.createElement('div');
  el.className = 'toast success';
  el.innerHTML = '<span class="toast-icon">✓</span><span class="toast-msg">' + p.get('success') + '</span>';
  document.getElementById('toasts').appendChild(el);
  setTimeout(() => el.remove(), 4000);
}
</script>
</body></html>
