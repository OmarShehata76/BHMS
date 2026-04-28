<?php
// guests.php — Guest Profile Management
require_once 'includes/auth.php';
Auth::check();
$db = db();

$action   = $_GET['action'] ?? 'list';
$guest_id = (int)($_GET['id'] ?? 0);
$error    = '';

// ── POST Handlers ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';

    if ($pa === 'create' || $pa === 'update') {
        $name      = trim($_POST['name']);
        $email     = trim($_POST['email']);
        $phone     = trim($_POST['phone'] ?? '');
        $dob       = $_POST['dob'] ?? null;
        $nat       = trim($_POST['nationality'] ?? '');
        $passport  = trim($_POST['passport_no'] ?? '');
        $vip       = isset($_POST['vip_status']) ? 1 : 0;

        if (!$name || !$email) { $error = 'Name and email are required.'; }
        else {
            if ($pa === 'create') {
                $id = $db->insert(
                    "INSERT INTO guests (name,email,phone,dob,nationality,passport_no,vip_status) VALUES (?,?,?,?,?,?,?)",
                    'ssssssi', $name,$email,$phone,$dob,$nat,$passport,$vip
                );
                AuditLogger::log('CREATE_GUEST','guests',$id,null,compact('name','email'));
                header("Location: guests.php?id={$id}&success=Guest+created"); exit;
            } else {
                $gid = (int)$_POST['guest_id'];
                $old = $db->fetchOne("SELECT * FROM guests WHERE guest_id=?", 'i', $gid);
                $db->execute(
                    "UPDATE guests SET name=?,email=?,phone=?,dob=?,nationality=?,passport_no=?,vip_status=?,updated_at=NOW() WHERE guest_id=?",
                    'ssssssii', $name,$email,$phone,$dob,$nat,$passport,$vip,$gid
                );
                AuditLogger::log('UPDATE_GUEST','guests',$gid,$old,compact('name','email','vip'));
                header("Location: guests.php?id={$gid}&success=Profile+updated"); exit;
            }
        }
    }

    if ($pa === 'blacklist') {
        $gid    = (int)$_POST['guest_id'];
        $reason = trim($_POST['reason']);
        $db->execute("UPDATE guests SET blacklisted=1, blacklist_reason=? WHERE guest_id=?", 'si', $reason, $gid);
        AuditLogger::log('BLACKLIST_GUEST','guests',$gid,null,$reason);
        header("Location: guests.php?id={$gid}&success=Guest+blacklisted"); exit;
    }

    if ($pa === 'anonymize') {
        Auth::requireRole(['Manager','Admin']);
        $gid = (int)$_POST['guest_id'];
        // Check no active reservations
        $active = $db->fetchOne("SELECT COUNT(*) c FROM reservations WHERE guest_id=? AND status IN ('Inquiry','Confirmed','CheckedIn')", 'i', $gid);
        if ($active['c'] > 0) { $error = 'Cannot anonymize: guest has active reservations.'; }
        else {
            $anon = 'ANONYMIZED_'.time();
            $db->execute("UPDATE guests SET name=?,email=?,phone=NULL,dob=NULL,passport_no=NULL,nationality=NULL,anonymized=1 WHERE guest_id=?",
                         'ssi', $anon, $anon.'@deleted.local', $gid);
            AuditLogger::log('ANONYMIZE_GUEST','guests',$gid,null,'GDPR Request');
            header("Location: guests.php?success=Guest+data+anonymized"); exit;
        }
    }
}

// Single guest
$guest      = null;
$stays      = [];
$prefs      = [];
$feedbacks  = [];
if ($guest_id) {
    $guest     = $db->fetchOne("SELECT * FROM guests WHERE guest_id=?", 'i', $guest_id);
    $stays     = $db->fetchAll("SELECT r.*, rm.room_number, rt.name room_type, f.total_amount, f.status folio_status FROM reservations r LEFT JOIN rooms rm ON r.room_id=rm.room_id LEFT JOIN room_types rt ON rm.type_id=rt.type_id LEFT JOIN folios f ON r.reservation_id=f.reservation_id WHERE r.guest_id=? ORDER BY r.check_in_date DESC", 'i', $guest_id);
    $prefs     = $db->fetchAll("SELECT * FROM preferences WHERE guest_id=? ORDER BY type", 'i', $guest_id);
    $feedbacks = $db->fetchAll("SELECT * FROM feedback WHERE guest_id=? ORDER BY submitted_at DESC", 'i', $guest_id);
    $total_spend = $db->fetchOne("SELECT COALESCE(SUM(f.total_amount),0) s FROM folios f JOIN reservations r ON f.reservation_id=r.reservation_id WHERE r.guest_id=? AND f.status='Closed'", 'i', $guest_id)['s'] ?? 0;
    $total_nights = $db->fetchOne("SELECT COALESCE(SUM(DATEDIFF(r.check_out_date,r.check_in_date)),0) s FROM reservations r WHERE r.guest_id=? AND r.status='FolioClosed'", 'i', $guest_id)['s'] ?? 0;
}

// List
$search = trim($_GET['q'] ?? '');
$filter_vip = $_GET['vip'] ?? '';
$where = "WHERE anonymized=0";
$params = []; $types = '';
if ($search) { $where .= " AND (name LIKE ? OR email LIKE ?)"; $like="%{$search}%"; $params=[$like,$like]; $types='ss'; }
if ($filter_vip) { $where .= " AND vip_status=1"; }
$guests = $db->fetchAll("SELECT guest_id,name,email,phone,vip_status,loyalty_points,loyalty_tier,blacklisted FROM guests {$where} ORDER BY name LIMIT 60", $types, ...$params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>BHMS — Guests</title>
<link rel="stylesheet" href="css/style.css">
<style>
.pref-tag{display:inline-flex;align-items:center;gap:6px;background:var(--bg-panel);border:1px solid var(--border);border-radius:20px;padding:4px 12px;font-size:12px;color:var(--text-muted)}
.stat-mini{text-align:center;padding:16px}
.stat-mini .v{font-family:'Cormorant Garamond',serif;font-size:30px;color:var(--text-primary)}
.stat-mini .l{font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.08em}
</style>
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title"><?= $guest ? htmlspecialchars($guest['name']) : ($action==='new'?'New Guest':'Guest Profiles') ?></div>
      <div class="topbar-actions">
        <?php if ($guest || $action==='new'): ?>
        <a href="guests.php" class="btn btn-outline btn-sm">← Back</a>
        <?php else: ?>
        <a href="guests.php?action=new" class="btn btn-gold btn-sm">+ Add Guest</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="content">
      <?php if ($error): ?><div style="background:var(--red-dim);border:1px solid rgba(224,92,92,.3);color:var(--red);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px"><?= $error ?></div><?php endif; ?>

      <?php if ($action==='new'): ?>
      <!-- ── New Guest Form ── -->
      <div class="card"><div class="card-header"><div class="card-title">New Guest Profile</div></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="create">
          <div class="form-row">
            <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
            <div class="form-group"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control"></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Nationality</label><input type="text" name="nationality" class="form-control"></div>
            <div class="form-group"><label class="form-label">Passport / ID</label><input type="text" name="passport_no" class="form-control"></div>
          </div>
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
              <input type="checkbox" name="vip_status" style="width:16px;height:16px;accent-color:var(--gold)">
              <span class="form-label" style="margin:0">Mark as VIP Guest</span>
            </label>
          </div>
          <div style="display:flex;gap:10px;justify-content:flex-end">
            <a href="guests.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-gold">Create Profile</button>
          </div>
        </form>
      </div></div>

      <?php elseif ($guest): ?>
      <!-- ── Guest Detail ── -->
      <div style="display:grid;grid-template-columns:1fr 300px;gap:20px">
        <div style="display:flex;flex-direction:column;gap:20px">

          <!-- Profile card -->
          <div class="card">
            <div class="card-header">
              <div class="card-title">Profile</div>
              <div style="display:flex;gap:8px">
                <?php if ($guest['blacklisted']): ?>
                <span class="badge badge-red">Blacklisted</span>
                <?php endif; ?>
                <?php if ($guest['vip_status']): ?>
                <span class="badge badge-gold">★ VIP</span>
                <?php endif; ?>
                <span class="badge badge-<?= strtolower($guest['loyalty_tier']) === 'gold' ? 'gold' : 'gray' ?>"><?= $guest['loyalty_tier'] ?></span>
              </div>
            </div>
            <div class="card-body">
              <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="guest_id" value="<?= $guest_id ?>">
                <div class="form-row">
                  <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($guest['name']) ?>" required></div>
                  <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($guest['email']) ?>" required></div>
                </div>
                <div class="form-row">
                  <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($guest['phone'] ?? '') ?>"></div>
                  <div class="form-group"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control" value="<?= $guest['dob'] ?? '' ?>"></div>
                </div>
                <div class="form-row">
                  <div class="form-group"><label class="form-label">Nationality</label><input type="text" name="nationality" class="form-control" value="<?= htmlspecialchars($guest['nationality'] ?? '') ?>"></div>
                  <div class="form-group"><label class="form-label">Passport / ID</label><input type="text" name="passport_no" class="form-control" value="<?= htmlspecialchars($guest['passport_no'] ?? '') ?>"></div>
                </div>
                <div class="form-group">
                  <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
                    <input type="checkbox" name="vip_status" style="width:16px;height:16px;accent-color:var(--gold)" <?= $guest['vip_status']?'checked':'' ?>>
                    <span class="form-label" style="margin:0">VIP Status</span>
                  </label>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end">
                  <button type="submit" class="btn btn-gold">Save Changes</button>
                </div>
              </form>
            </div>
          </div>

          <!-- Preferences -->
          <div class="card">
            <div class="card-header"><div class="card-title">Preferences</div>
              <button onclick="document.getElementById('addPrefModal').classList.add('show')" class="btn btn-outline btn-sm">+ Add</button>
            </div>
            <div class="card-body" style="display:flex;flex-wrap:wrap;gap:8px">
              <?php foreach ($prefs as $p): ?>
              <span class="pref-tag"><span style="color:var(--text-dim)"><?= htmlspecialchars($p['type']) ?>:</span><?= htmlspecialchars($p['value']) ?></span>
              <?php endforeach; ?>
              <?php if (empty($prefs)): ?><span style="color:var(--text-dim);font-size:13px">No preferences logged yet.</span><?php endif; ?>
            </div>
          </div>

          <!-- Stay History -->
          <div class="card">
            <div class="card-header"><div class="card-title">Stay History</div></div>
            <div class="table-wrap">
              <table>
                <thead><tr><th>#</th><th>Room</th><th>Check-In</th><th>Check-Out</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($stays as $s): ?>
                  <tr>
                    <td><?= $s['reservation_id'] ?></td>
                    <td><?= htmlspecialchars($s['room_number'] ?? '—') ?></td>
                    <td><?= $s['check_in_date'] ?></td>
                    <td><?= $s['check_out_date'] ?></td>
                    <td>EGP <?= number_format($s['total_amount'] ?? 0) ?></td>
                    <td><span class="badge badge-gray"><?= $s['status'] ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($stays)): ?><tr><td colspan="6" style="text-align:center;color:var(--text-dim);padding:20px">No stays yet</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Right sidebar -->
        <div style="display:flex;flex-direction:column;gap:16px">
          <div class="card">
            <div class="card-header"><div class="card-title">Lifetime Value</div></div>
            <div style="display:flex;flex-wrap:wrap">
              <div class="stat-mini" style="flex:1"><div class="v"><?= count($stays) ?></div><div class="l">Total Stays</div></div>
              <div class="stat-mini" style="flex:1"><div class="v"><?= $total_nights ?></div><div class="l">Nights</div></div>
              <div class="stat-mini" style="width:100%"><div class="v" style="font-size:22px">EGP <?= number_format($total_spend) ?></div><div class="l">Total Spend</div></div>
              <div class="stat-mini" style="width:100%"><div class="v" style="font-size:22px"><?= $guest['loyalty_points'] ?></div><div class="l">Loyalty Points</div></div>
            </div>
          </div>

          <!-- Danger zone -->
          <?php if (!$guest['blacklisted'] && Auth::can(['Manager','Admin'])): ?>
          <div class="card" style="border-color:rgba(224,92,92,.2)">
            <div class="card-header"><div class="card-title" style="color:var(--red)">Danger Zone</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
              <button onclick="document.getElementById('blacklistModal').classList.add('show')" class="btn btn-danger btn-sm">Blacklist Guest</button>
              <form method="POST" onsubmit="return confirm('Permanently anonymize this guest\'s personal data? This cannot be undone.')">
                <input type="hidden" name="action" value="anonymize">
                <input type="hidden" name="guest_id" value="<?= $guest_id ?>">
                <button type="submit" class="btn btn-danger btn-sm" style="width:100%">GDPR: Anonymize Data</button>
              </form>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Add Preference Modal -->
      <div class="modal-backdrop" id="addPrefModal">
        <div class="modal">
          <div class="modal-header"><div class="modal-title">Add Preference</div>
            <button class="modal-close" onclick="document.getElementById('addPrefModal').classList.remove('show')">×</button>
          </div>
          <form method="POST" action="preferences.php">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="guest_id" value="<?= $guest_id ?>">
            <input type="hidden" name="redirect" value="guests.php?id=<?= $guest_id ?>">
            <div class="modal-body">
              <div class="form-group"><label class="form-label">Type</label>
                <select name="type" class="form-control">
                  <option>Pillow Type</option><option>Room Temperature</option><option>Floor Preference</option>
                  <option>Dietary Requirement</option><option>Newspaper</option><option>Allergies</option><option>Other</option>
                </select>
              </div>
              <div class="form-group"><label class="form-label">Value</label>
                <input type="text" name="value" class="form-control" placeholder="e.g. Feather pillow, Vegetarian..."></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="document.getElementById('addPrefModal').classList.remove('show')">Cancel</button>
              <button type="submit" class="btn btn-gold">Save Preference</button></div>
          </form>
        </div>
      </div>

      <!-- Blacklist Modal -->
      <div class="modal-backdrop" id="blacklistModal">
        <div class="modal">
          <div class="modal-header"><div class="modal-title" style="color:var(--red)">Blacklist Guest</div>
            <button class="modal-close" onclick="document.getElementById('blacklistModal').classList.remove('show')">×</button>
          </div>
          <form method="POST">
            <input type="hidden" name="action" value="blacklist">
            <input type="hidden" name="guest_id" value="<?= $guest_id ?>">
            <div class="modal-body">
              <div class="form-group"><label class="form-label">Reason *</label>
                <textarea name="reason" class="form-control" rows="3" required placeholder="Reason for blacklisting..."></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline" onclick="document.getElementById('blacklistModal').classList.remove('show')">Cancel</button>
              <button type="submit" class="btn btn-danger">Confirm Blacklist</button>
            </div>
          </form>
        </div>
      </div>

      <?php else: ?>
      <!-- ── Guest List ── -->
      <form method="GET" class="filter-bar" style="display:flex;gap:10px;margin-bottom:20px">
        <div class="search-box" style="flex:1"><span class="search-icon">🔍</span>
          <input type="text" name="q" class="form-control" placeholder="Search name or email..." value="<?= htmlspecialchars($search) ?>"></div>
        <label style="display:flex;align-items:center;gap:8px;color:var(--text-muted);font-size:13px;cursor:pointer">
          <input type="checkbox" name="vip" value="1" <?= $filter_vip?'checked':'' ?> style="accent-color:var(--gold)"> VIP only
        </label>
        <button type="submit" class="btn btn-outline">Filter</button>
        <a href="guests.php" class="btn btn-ghost">Clear</a>
      </form>
      <div class="card">
        <div class="card-header"><div class="card-title">All Guests</div><span style="font-size:12px;color:var(--text-dim)"><?= count($guests) ?> records</span></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Tier</th><th>Points</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($guests as $g): ?>
              <tr>
                <td style="color:var(--text-dim)"><?= $g['guest_id'] ?></td>
                <td><?= htmlspecialchars($g['name']) ?> <?php if ($g['vip_status']): ?><span style="color:var(--gold)">★</span><?php endif; ?></td>
                <td style="color:var(--text-muted)"><?= htmlspecialchars($g['email']) ?></td>
                <td style="color:var(--text-muted)"><?= htmlspecialchars($g['phone'] ?? '—') ?></td>
                <td><span class="badge badge-gray"><?= $g['loyalty_tier'] ?></span></td>
                <td><?= number_format($g['loyalty_points']) ?></td>
                <td><?php if ($g['blacklisted']): ?><span class="badge badge-red">Blacklisted</span><?php else: ?><span class="badge badge-green">Active</span><?php endif; ?></td>
                <td><a href="guests.php?id=<?= $g['guest_id'] ?>" class="btn btn-ghost btn-sm">View</a></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($guests)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-dim);padding:30px">No guests found</td></tr><?php endif; ?>
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
const p=new URLSearchParams(location.search);
const toast=(msg,type)=>{const el=document.createElement('div');el.className=`toast ${type}`;el.innerHTML=`<span class="toast-icon">${type==='success'?'✓':'✕'}</span><span class="toast-msg">${msg}</span>`;document.getElementById('toasts').appendChild(el);setTimeout(()=>el.remove(),4000)};
if(p.get('success'))toast(p.get('success'),'success');
if(p.get('error'))toast(p.get('error'),'error');
</script>
</body></html>
