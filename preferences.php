<?php
// preferences.php — Guest Preference Logger (UC-12 / UC-10)
require_once 'includes/auth.php';
Auth::check();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';

    if ($pa === 'add') {
        $guest_id = (int)$_POST['guest_id'];
        $type     = trim($_POST['type']);
        $value    = trim($_POST['value']);
        $redirect = $_POST['redirect'] ?? 'preferences.php';
        if (!$guest_id || !$type || !$value) {
            header("Location: preferences.php?error=All+fields+required"); exit;
        }
        $exists = $db->fetchOne("SELECT pref_id FROM preferences WHERE guest_id=? AND type=?", 'is', $guest_id, $type);
        if ($exists) {
            $db->execute("UPDATE preferences SET value=?, updated_at=NOW() WHERE guest_id=? AND type=?", 'sis', $value, $guest_id, $type);
            $msg = 'Preference+updated';
        } else {
            $db->insert("INSERT INTO preferences (guest_id, type, value) VALUES (?,?,?)", 'iss', $guest_id, $type, $value);
            $msg = 'Preference+saved';
        }
        AuditLogger::log('SAVE_PREFERENCE','preferences',$guest_id,null,compact('type','value'));
        header("Location: {$redirect}&success={$msg}"); exit;
    }

    if ($pa === 'delete') {
        $pref_id = (int)$_POST['pref_id'];
        $redirect= $_POST['redirect'] ?? 'preferences.php';
        $old = $db->fetchOne("SELECT type,value FROM preferences WHERE pref_id=?", 'i', $pref_id);
        $db->execute("DELETE FROM preferences WHERE pref_id=?", 'i', $pref_id);
        AuditLogger::log('DELETE_PREFERENCE','preferences',$pref_id,$old,null);
        header("Location: {$redirect}&success=Preference+removed"); exit;
    }

    if ($pa === 'clear_guest') {
        $guest_id = (int)$_POST['guest_id'];
        $db->execute("DELETE FROM preferences WHERE guest_id=?", 'i', $guest_id);
        AuditLogger::log('CLEAR_PREFERENCES','preferences',$guest_id);
        header("Location: preferences.php?success=All+preferences+cleared"); exit;
    }
}

$search       = trim($_GET['q'] ?? '');
$filter_type  = $_GET['type'] ?? '';
$filter_guest = (int)($_GET['guest_id'] ?? 0);

$where = "WHERE 1=1"; $params = []; $types = '';
if ($search)       { $where .= " AND (g.name LIKE ? OR p.value LIKE ? OR p.type LIKE ?)"; $like="%{$search}%"; $params=array_merge($params,[$like,$like,$like]); $types.='sss'; }
if ($filter_type)  { $where .= " AND p.type=?";     $params[]=$filter_type;  $types.='s'; }
if ($filter_guest) { $where .= " AND p.guest_id=?"; $params[]=$filter_guest; $types.='i'; }

$prefs = $db->fetchAll("
    SELECT p.pref_id, p.type, p.value, p.guest_id, p.updated_at,
           g.name guest_name, g.vip_status, g.email
    FROM preferences p
    JOIN guests g ON p.guest_id=g.guest_id
    {$where}
    ORDER BY g.name, p.type LIMIT 200",
    $types, ...$params
);

$stats = [
    'total'  => $db->fetchOne("SELECT COUNT(*) c FROM preferences")['c'] ?? 0,
    'guests' => $db->fetchOne("SELECT COUNT(DISTINCT guest_id) c FROM preferences")['c'] ?? 0,
    'types'  => $db->fetchOne("SELECT COUNT(DISTINCT type) c FROM preferences")['c'] ?? 0,
];

$pref_types = $db->fetchAll("SELECT DISTINCT type FROM preferences ORDER BY type");
$guests = $db->fetchAll("SELECT guest_id,name,email,vip_status FROM guests WHERE anonymized=0 ORDER BY name");

$common_types = ['Pillow Type','Room Temperature','Floor Preference','Dietary Requirement',
                 'Allergies','Newspaper','Wake-Up Call','Extra Towels','Room Fragrance',
                 'Minibar Preference','Transport Need','Special Occasion'];

$grouped = [];
foreach ($prefs as $p) {
    $grouped[$p['guest_id']]['info']    = ['name'=>$p['guest_name'],'vip_status'=>$p['vip_status'],'email'=>$p['email'],'guest_id'=>$p['guest_id']];
    $grouped[$p['guest_id']]['prefs'][] = $p;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>BHMS — Preferences</title>
<link rel="stylesheet" href="css/style.css">
<style>
.pref-tag{display:inline-flex;align-items:center;gap:7px;background:var(--bg-panel);border:1px solid var(--border);border-radius:20px;padding:5px 12px;font-size:12px;color:var(--text-muted);transition:var(--transition)}
.pref-tag:hover{border-color:var(--border-gold)}
.pref-tag .ptype{color:var(--text-dim);font-size:10px;text-transform:uppercase;letter-spacing:.06em}
.pref-tag .pval{color:var(--text-primary);font-weight:500}
.pref-tag .pdel{background:none;border:none;color:var(--text-dim);cursor:pointer;font-size:14px;line-height:1;padding:0 0 0 4px;transition:var(--transition)}
.pref-tag .pdel:hover{color:var(--red)}
.guest-block{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:14px;overflow:hidden}
.guest-block-header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border);background:var(--bg-panel)}
.guest-block-prefs{padding:14px 18px;display:flex;flex-wrap:wrap;gap:8px;min-height:48px}
.type-chip{display:inline-flex;align-items:center;background:var(--bg-panel);border:1px solid var(--border);border-radius:6px;padding:3px 10px;font-size:11px;color:var(--text-dim);cursor:pointer;transition:var(--transition);text-decoration:none}
.type-chip:hover{border-color:var(--gold-dim);color:var(--gold)}
</style>
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">Guest Preferences</div>
      <div class="topbar-actions">
        <button onclick="document.getElementById('addModal').classList.add('show')" class="btn btn-gold btn-sm">+ Add Preference</button>
      </div>
    </div>
    <div class="content">

      <!-- Stats -->
      <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
        <div class="stat-card"><div class="stat-icon">⭐</div><div class="stat-label">Total Preferences</div><div class="stat-value"><?= $stats['total'] ?></div></div>
        <div class="stat-card"><div class="stat-icon">👤</div><div class="stat-label">Guests Profiled</div><div class="stat-value"><?= $stats['guests'] ?></div></div>
        <div class="stat-card"><div class="stat-icon">🏷</div><div class="stat-label">Unique Types</div><div class="stat-value"><?= $stats['types'] ?></div></div>
      </div>

      <!-- Filter Bar -->
      <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
        <div class="search-box" style="flex:1;min-width:180px">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" class="form-control" placeholder="Search guest, type, or value..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <select name="type" class="form-control" style="width:auto">
          <option value="">All Types</option>
          <?php foreach ($pref_types as $pt): ?>
          <option value="<?= htmlspecialchars($pt['type']) ?>" <?= $filter_type===$pt['type']?'selected':'' ?>><?= htmlspecialchars($pt['type']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="guest_id" class="form-control" style="width:auto">
          <option value="">All Guests</option>
          <?php foreach ($guests as $g): ?>
          <option value="<?= $g['guest_id'] ?>" <?= $filter_guest==$g['guest_id']?'selected':'' ?>><?= htmlspecialchars($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline">Filter</button>
        <a href="preferences.php" class="btn btn-ghost">Clear</a>
      </form>

      <!-- Quick type filters -->
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:20px;align-items:center">
        <span style="font-size:11px;color:var(--text-dim);margin-right:4px">Quick:</span>
        <?php foreach (array_slice($common_types,0,8) as $ct): ?>
        <a href="?type=<?= urlencode($ct) ?>" class="type-chip"><?= htmlspecialchars($ct) ?></a>
        <?php endforeach; ?>
      </div>

      <!-- Grouped by Guest -->
      <?php if (empty($grouped)): ?>
      <div style="text-align:center;padding:60px;color:var(--text-dim);background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius)">
        No preferences found.
        <br><button onclick="document.getElementById('addModal').classList.add('show')" class="btn btn-gold btn-sm" style="margin-top:16px">+ Add First Preference</button>
      </div>
      <?php else: ?>
      <?php foreach ($grouped as $gid => $group): $info = $group['info']; ?>
      <div class="guest-block">
        <div class="guest-block-header">
          <div style="display:flex;align-items:center;gap:12px">
            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--gold-dim),var(--gold));display:flex;align-items:center;justify-content:center;font-family:'Cormorant Garamond',serif;font-size:16px;color:var(--bg-deep);font-weight:600"><?= strtoupper(substr($info['name'],0,1)) ?></div>
            <div>
              <a href="guests.php?id=<?= $gid ?>" style="font-size:14px;font-weight:600;color:var(--text-primary);text-decoration:none"><?= htmlspecialchars($info['name']) ?></a>
              <?php if ($info['vip_status']): ?><span class="badge badge-gold" style="margin-left:6px;font-size:10px">VIP</span><?php endif; ?>
              <div style="font-size:11px;color:var(--text-dim)"><?= htmlspecialchars($info['email']) ?></div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <span style="font-size:12px;color:var(--text-dim)"><?= count($group['prefs']) ?> pref(s)</span>
            <button onclick="document.getElementById('modalGuestSel').value=<?= $gid ?>;document.getElementById('addModal').classList.add('show')" class="btn btn-outline btn-sm">+ Add</button>
            <?php if (Auth::can(['Manager','Admin'])): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="clear_guest">
              <input type="hidden" name="guest_id" value="<?= $gid ?>">
              <button type="submit" class="btn btn-ghost btn-sm" onclick="return confirm('Clear all?')" style="color:var(--text-dim)">Clear all</button>
            </form>
            <?php endif; ?>
          </div>
        </div>
        <div class="guest-block-prefs">
          <?php foreach ($group['prefs'] as $p): ?>
          <span class="pref-tag">
            <span class="ptype"><?= htmlspecialchars($p['type']) ?>:</span>
            <span class="pval"><?= htmlspecialchars($p['value']) ?></span>
            <button class="pdel" title="Edit" onclick="openEdit(<?= $gid ?>,'<?= addslashes($p['type']) ?>','<?= addslashes($p['value']) ?>')">✎</button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="pref_id" value="<?= $p['pref_id'] ?>">
              <input type="hidden" name="redirect" value="preferences.php">
              <button type="submit" class="pdel" title="Delete" onclick="return confirm('Remove?')">×</button>
            </form>
          </span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-backdrop" id="addModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Add / Update Preference</div>
      <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('show')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="redirect" value="preferences.php">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Guest *</label>
          <select name="guest_id" id="modalGuestSel" class="form-control" required>
            <option value="">— Select guest —</option>
            <?php foreach ($guests as $g): ?>
            <option value="<?= $g['guest_id'] ?>"><?= htmlspecialchars($g['name']) ?><?= $g['vip_status']?' ★':'' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Preference Type *</label>
          <input type="text" name="type" id="modalType" class="form-control" list="typeList" required placeholder="e.g. Pillow Type, Dietary...">
          <datalist id="typeList"><?php foreach ($common_types as $ct): ?><option value="<?= htmlspecialchars($ct) ?>"><?php endforeach; ?></datalist>
        </div>
        <div class="form-group"><label class="form-label">Value *</label>
          <input type="text" name="value" id="modalValue" class="form-control" required placeholder="e.g. Feather pillow, Vegetarian...">
        </div>
        <div style="margin-top:4px">
          <div style="font-size:11px;color:var(--text-dim);margin-bottom:8px">Quick type:</div>
          <div style="display:flex;flex-wrap:wrap;gap:6px">
            <?php foreach ($common_types as $ct): ?>
            <button type="button" class="type-chip" onclick="document.getElementById('modalType').value='<?= addslashes($ct) ?>'">
              <?= htmlspecialchars($ct) ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addModal').classList.remove('show')">Cancel</button>
        <button type="submit" class="btn btn-gold">Save Preference</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-backdrop" id="editModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Edit Preference</div>
      <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('show')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="redirect" value="preferences.php">
      <input type="hidden" name="guest_id" id="editGuestId">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Type</label>
          <input type="text" name="type" id="editType" class="form-control" list="typeList" required></div>
        <div class="form-group"><label class="form-label">Value</label>
          <input type="text" name="value" id="editValue" class="form-control" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('editModal').classList.remove('show')">Cancel</button>
        <button type="submit" class="btn btn-gold">Update</button>
      </div>
    </form>
  </div>
</div>

<div class="toast-container" id="toasts"></div>
<script>
function openEdit(guestId, type, value) {
  document.getElementById('editGuestId').value = guestId;
  document.getElementById('editType').value    = type;
  document.getElementById('editValue').value   = value;
  document.getElementById('editModal').classList.add('show');
}
const p = new URLSearchParams(location.search);
const toast = (msg, type) => {
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = '<span class="toast-icon">' + (type==='success'?'✓':'✕') + '</span><span class="toast-msg">' + msg + '</span>';
  document.getElementById('toasts').appendChild(el);
  setTimeout(() => el.remove(), 4000);
};
if (p.get('success')) toast(decodeURIComponent(p.get('success').replace(/\+/g,' ')), 'success');
if (p.get('error'))   toast(decodeURIComponent(p.get('error').replace(/\+/g,' ')),   'error');
</script>
</body>
</html>
