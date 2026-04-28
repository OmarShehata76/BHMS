<?php
// checkin.php — Dedicated Check-In / Check-Out Screen
require_once 'includes/auth.php';
Auth::requireRole(['Receptionist','Manager','Admin']);
$db = db();

$mode       = $_GET['mode'] ?? 'checkin';
$res_id     = (int)($_GET['id'] ?? 0);
$error      = '';
$reservation= null;

if ($res_id) {
    $reservation = $db->fetchOne("
        SELECT r.*, g.name guest_name, g.email, g.phone, g.vip_status,
               g.loyalty_points, g.loyalty_tier,
               rm.room_number, rm.floor, rt.name room_type, rt.base_price,
               f.folio_id, f.total_amount, f.status folio_status
        FROM reservations r
        JOIN guests g ON r.guest_id=g.guest_id
        LEFT JOIN rooms rm ON r.room_id=rm.room_id
        LEFT JOIN room_types rt ON rm.type_id=rt.type_id
        LEFT JOIN folios f ON r.reservation_id=f.reservation_id
        WHERE r.reservation_id=?", 'i', $res_id
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';

    if ($pa === 'checkin') {
        $rid     = (int)$_POST['reservation_id'];
        $room_id = (int)$_POST['room_id'];
        $res = $db->fetchOne("SELECT * FROM reservations WHERE reservation_id=? AND status='Confirmed'", 'i', $rid);
        if (!$res) {
            $error = 'Reservation not found or not in Confirmed status.';
        } else {
            if ($room_id) {
                $db->execute("UPDATE reservations SET room_id=? WHERE reservation_id=?", 'ii', $room_id, $rid);
                $db->execute("UPDATE rooms SET status='Occupied' WHERE room_id=?", 'i', $room_id);
            }
            $db->execute("UPDATE reservations SET status='CheckedIn', updated_at=NOW() WHERE reservation_id=?", 'i', $rid);
            $db->insert("INSERT INTO reservation_state_log (reservation_id,from_state,to_state,changed_by) VALUES (?,?,?,?)",
                'sssi', $rid, 'Confirmed', 'CheckedIn', Auth::id());
            $key = strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
            $used_room = $room_id ?: $res['room_id'];
            if ($used_room) {
                $db->execute("UPDATE rooms SET digital_key=? WHERE room_id=?", 'si', $key, $used_room);
            }
            $folio = $db->fetchOne("SELECT folio_id FROM folios WHERE reservation_id=?", 'i', $rid);
            if ($folio && $used_room) {
                $nights = max(1,(new DateTime($res['check_out_date']))->diff(new DateTime($res['check_in_date']))->days);
                $room_rate = $db->fetchOne("SELECT rt.base_price FROM rooms rm JOIN room_types rt ON rm.type_id=rt.type_id WHERE rm.room_id=?", 'i', $used_room)['base_price'] ?? 0;
                $room_total = $room_rate * $nights;
                $db->insert("INSERT INTO folio_charges (folio_id,description,amount,charge_type,charged_by) VALUES (?,?,?,'Room',?)",
                    'isdi', $folio['folio_id'], "Room Rate x {$nights} nights", $room_total, Auth::id());
                $tax = round($room_total * 0.14, 2);
                $db->execute("UPDATE folios SET total_amount=?, tax_amount=? WHERE folio_id=?", 'ddi', $room_total+$tax, $tax, $folio['folio_id']);
            }
            AuditLogger::log('CHECKIN','reservations',$rid,'Confirmed','CheckedIn');
            header("Location: checkin.php?id={$rid}&mode=checkin&success=1&key=".urlencode($key)); exit;
        }
    }

    if ($pa === 'checkout') {
        $rid = (int)$_POST['reservation_id'];
        $res = $db->fetchOne("SELECT * FROM reservations WHERE reservation_id=? AND status='CheckedIn'", 'i', $rid);
        if (!$res) { $error = 'Reservation not found or not checked in.'; }
        else {
            $db->execute("UPDATE reservations SET status='CheckedOut', updated_at=NOW() WHERE reservation_id=?", 'i', $rid);
            $db->insert("INSERT INTO reservation_state_log (reservation_id,from_state,to_state,changed_by) VALUES (?,?,?,?)",
                'sssi', $rid, 'CheckedIn', 'CheckedOut', Auth::id());
            if ($res['room_id']) {
                $db->execute("UPDATE rooms SET status='Dirty', digital_key=NULL WHERE room_id=?", 'i', $res['room_id']);
                $db->insert("INSERT INTO hk_tasks (room_id,type,status,priority) VALUES (?,'Cleaning','Pending',3)", 'i', $res['room_id']);
            }
            AuditLogger::log('CHECKOUT','reservations',$rid,'CheckedIn','CheckedOut');
            $folio = $db->fetchOne("SELECT folio_id FROM folios WHERE reservation_id=?", 'i', $rid);
            $fid = $folio['folio_id'] ?? 0;
            header("Location: folios.php?id={$fid}&success=Guest+checked+out"); exit;
        }
    }
}

$arrivals = $db->fetchAll("
    SELECT r.reservation_id, g.name, g.vip_status, rm.room_number, r.check_in_date, r.check_out_date, r.status
    FROM reservations r JOIN guests g ON r.guest_id=g.guest_id
    LEFT JOIN rooms rm ON r.room_id=rm.room_id
    WHERE r.status='Confirmed' AND r.check_in_date=CURDATE()
    ORDER BY g.vip_status DESC, g.name");

$departures = $db->fetchAll("
    SELECT r.reservation_id, g.name, g.vip_status, rm.room_number, r.check_out_date, f.total_amount
    FROM reservations r JOIN guests g ON r.guest_id=g.guest_id
    LEFT JOIN rooms rm ON r.room_id=rm.room_id
    LEFT JOIN folios f ON r.reservation_id=f.reservation_id
    WHERE r.status='CheckedIn' AND r.check_out_date=CURDATE()
    ORDER BY g.vip_status DESC, g.name");

$avail_rooms = $db->fetchAll("SELECT rm.room_id, rm.room_number, rt.name type_name, rt.base_price FROM rooms rm JOIN room_types rt ON rm.type_id=rt.type_id WHERE rm.status='Ready' ORDER BY rm.room_number");
$success_key = $_GET['key'] ?? null;
$success     = $_GET['success'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>BHMS — Check-In / Out</title>
<link rel="stylesheet" href="css/style.css">
<style>
.key-box{background:linear-gradient(135deg,rgba(201,168,76,.1),rgba(201,168,76,.05));border:1px solid var(--border-gold);border-radius:var(--radius);padding:24px;text-align:center}
.key-value{font-family:monospace;font-size:28px;font-weight:700;color:var(--gold);letter-spacing:.15em;margin:12px 0}
.tab-active{background:var(--gold)!important;color:var(--bg-deep)!important;border-color:var(--gold)!important}
</style>
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">Check-In / Check-Out</div>
      <div class="topbar-actions">
        <a href="?mode=checkin" class="btn btn-outline btn-sm <?= $mode==='checkin'?'tab-active':'' ?>">Check-In</a>
        <a href="?mode=checkout" class="btn btn-outline btn-sm <?= $mode==='checkout'?'tab-active':'' ?>">Check-Out</a>
      </div>
    </div>
    <div class="content">

      <?php if ($error): ?>
      <div style="background:var(--red-dim);border:1px solid rgba(224,92,92,.3);color:var(--red);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($success && $success_key): ?>
      <div class="key-box" style="margin-bottom:24px">
        <div style="font-size:13px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.1em">Check-In Successful — Digital Room Key</div>
        <div class="key-value"><?= htmlspecialchars($success_key) ?></div>
        <div style="font-size:12px;color:var(--text-dim)">Hand this code to the guest. Key is valid for the duration of their stay.</div>
        <button onclick="window.print()" class="btn btn-outline btn-sm" style="margin-top:12px">Print Key Card</button>
      </div>
      <?php endif; ?>

      <?php if ($reservation && !$success): ?>
      <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;margin-bottom:24px">
        <div class="card">
          <div class="card-header">
            <div>
              <div class="card-title"><?= htmlspecialchars($reservation['guest_name']) ?><?php if ($reservation['vip_status']): ?><span style="color:var(--gold)"> * VIP</span><?php endif; ?></div>
              <div style="font-size:12px;color:var(--text-dim)"><?= $reservation['email'] ?> - <?= $reservation['phone'] ?></div>
            </div>
            <span class="badge badge-<?= $reservation['status']==='Confirmed'?'blue':'green' ?>"><?= $reservation['status'] ?></span>
          </div>
          <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px">
              <div><div style="font-size:11px;color:var(--text-dim)">Room</div><div style="font-size:16px;font-weight:600"><?= $reservation['room_number'] ?? 'TBD' ?></div></div>
              <div><div style="font-size:11px;color:var(--text-dim)">Check-In</div><div style="font-weight:500"><?= $reservation['check_in_date'] ?></div></div>
              <div><div style="font-size:11px;color:var(--text-dim)">Check-Out</div><div style="font-weight:500"><?= $reservation['check_out_date'] ?></div></div>
            </div>

            <?php if ($reservation['status'] === 'Confirmed'): ?>
            <form method="POST">
              <input type="hidden" name="action" value="checkin">
              <input type="hidden" name="reservation_id" value="<?= $res_id ?>">
              <div class="form-group">
                <label class="form-label">Assign / Confirm Room</label>
                <select name="room_id" class="form-control">
                  <option value="">Keep current (<?= $reservation['room_number'] ?? 'none' ?>)</option>
                  <?php foreach ($avail_rooms as $rm): ?>
                  <option value="<?= $rm['room_id'] ?>"><?= $rm['room_number'] ?> - <?= $rm['type_name'] ?> (EGP <?= number_format($rm['base_price']) ?>/night)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn btn-gold btn-lg" style="width:100%">Confirm Check-In and Generate Key</button>
            </form>

            <?php elseif ($reservation['status'] === 'CheckedIn'): ?>
            <div style="background:var(--amber-dim);border:1px solid rgba(240,160,64,.2);border-radius:var(--radius-sm);padding:14px;margin-bottom:16px">
              <div style="font-size:13px;font-weight:600;color:var(--amber)">Balance: EGP <?= number_format($reservation['total_amount'] ?? 0, 2) ?></div>
            </div>
            <div style="display:flex;gap:10px">
              <form method="POST" style="flex:1">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="reservation_id" value="<?= $res_id ?>">
                <button type="submit" class="btn btn-outline btn-lg" style="width:100%">Check-Out</button>
              </form>
              <a href="folios.php?id=<?= $reservation['folio_id'] ?>" class="btn btn-gold btn-lg" style="flex:1;justify-content:center">Go to Billing</a>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Guest Preferences</div></div>
          <div class="card-body">
            <?php $prefs = $db->fetchAll("SELECT type, value FROM preferences WHERE guest_id=?", 'i', $reservation['guest_id']);
            foreach ($prefs as $p): ?>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid var(--border)">
              <span style="font-size:11px;color:var(--text-dim)"><?= htmlspecialchars($p['type']) ?></span>
              <span style="font-size:12px;font-weight:500"><?= htmlspecialchars($p['value']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($prefs)): ?><div style="color:var(--text-dim);font-size:13px">No preferences on file.</div><?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <div class="card">
          <div class="card-header"><div class="card-title">Today's Arrivals</div><span class="badge badge-blue"><?= count($arrivals) ?></span></div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Guest</th><th>Room</th><th>Action</th></tr></thead>
              <tbody>
                <?php foreach ($arrivals as $a): ?>
                <tr>
                  <td><?= htmlspecialchars($a['name']) ?><?php if ($a['vip_status']): ?><span style="color:var(--gold)"> *</span><?php endif; ?></td>
                  <td><?= htmlspecialchars($a['room_number'] ?? 'TBD') ?></td>
                  <td><a href="checkin.php?id=<?= $a['reservation_id'] ?>&mode=checkin" class="btn btn-success btn-sm">Check-In</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($arrivals)): ?><tr><td colspan="3" style="text-align:center;color:var(--text-dim);padding:20px">No arrivals today</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Today's Departures</div><span class="badge badge-amber"><?= count($departures) ?></span></div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Guest</th><th>Room</th><th>Balance</th><th>Action</th></tr></thead>
              <tbody>
                <?php foreach ($departures as $d): ?>
                <tr>
                  <td><?= htmlspecialchars($d['name']) ?><?php if ($d['vip_status']): ?><span style="color:var(--gold)"> *</span><?php endif; ?></td>
                  <td><?= htmlspecialchars($d['room_number'] ?? '-') ?></td>
                  <td style="font-weight:600">EGP <?= number_format($d['total_amount'] ?? 0) ?></td>
                  <td><a href="checkin.php?id=<?= $d['reservation_id'] ?>&mode=checkout" class="btn btn-outline btn-sm">Check-Out</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($departures)): ?><tr><td colspan="4" style="text-align:center;color:var(--text-dim);padding:20px">No departures today</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body></html>
