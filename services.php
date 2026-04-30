<?php
// services.php — Services Catalog Management (Spa, Café, Minibar, Tours)
require_once 'includes/auth.php';
Auth::check();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';

    if ($pa === 'create') {
        $name     = trim($_POST['name']);
        $category = $_POST['category'];
        $price    = (float)$_POST['price'];
        $cancel_hrs = (int)($_POST['cancellation_hrs'] ?? 24);
        $penalty  = (float)($_POST['penalty_pct'] ?? 50);
        $id = $db->insert(
            "INSERT INTO services (name,category,price,cancellation_hrs,penalty_pct,active) VALUES (?,?,?,?,?,1)",
            'ssdid', $name, $category, $price, $cancel_hrs, $penalty
        );
        AuditLogger::log('CREATE_SERVICE','services',$id,null,compact('name','category','price'));
        header("Location: services.php?success=Service+created"); exit;
    }

    if ($pa === 'update') {
        $sid      = (int)$_POST['service_id'];
        $name     = trim($_POST['name']);
        $price    = (float)$_POST['price'];
        $cancel_hrs = (int)$_POST['cancellation_hrs'];
        $penalty  = (float)$_POST['penalty_pct'];
        $active   = isset($_POST['active']) ? 1 : 0;
        $old = $db->fetchOne("SELECT * FROM services WHERE service_id=?", 'i', $sid);
        $db->execute(
            "UPDATE services SET name=?,price=?,cancellation_hrs=?,penalty_pct=?,active=? WHERE service_id=?",
            'sdidii', $name, $price, $cancel_hrs, $penalty, $active, $sid
        );
        AuditLogger::log('UPDATE_SERVICE','services',$sid,$old,compact('name','price'));
        header("Location: services.php?success=Service+updated"); exit;
    }

    if ($pa === 'post_to_room') {
        // POS Bridge — post service charge to guest folio
        $service_id    = (int)$_POST['service_id'];
        $reservation_id= (int)$_POST['reservation_id'];
        $qty           = max(1, (int)$_POST['quantity']);

        $service = $db->fetchOne("SELECT * FROM services WHERE service_id=? AND active=1", 'i', $service_id);
        $res     = $db->fetchOne("SELECT r.reservation_id, f.folio_id, g.name guest_name FROM reservations r JOIN folios f ON r.reservation_id=f.reservation_id JOIN guests g ON r.guest_id=g.guest_id WHERE r.reservation_id=? AND r.status='CheckedIn'", 'i', $reservation_id);

        if (!$service) { header("Location: services.php?error=Service+not+found"); exit; }
        if (!$res)     { header("Location: services.php?error=No+active+reservation+found"); exit; }

        $amount = $service['price'] * $qty;
        $db->insert(
            "INSERT INTO folio_charges (folio_id,service_id,description,amount,charge_type,charged_by) VALUES (?,?,?,?,'Service',?)",
            'iisdi', $res['folio_id'], $service_id,
            "{$service['name']} × {$qty}", $amount, Auth::id()
        );
        // Recalculate folio total
        $total = $db->fetchOne("SELECT COALESCE(SUM(amount),0) s FROM folio_charges WHERE folio_id=?", 'i', $res['folio_id'])['s'];
        $tax   = round($total * 0.14, 2);
        $db->execute("UPDATE folios SET total_amount=?, tax_amount=? WHERE folio_id=?", 'ddi', $total+$tax, $tax, $res['folio_id']);
        AuditLogger::log('POS_CHARGE','folio_charges',$res['folio_id'],null,compact('service_id','amount'));
        header("Location: services.php?success=Charge+posted+to+room+{$res['guest_name']}"); exit;
    }
}

// Data
$filter_cat = $_GET['cat'] ?? '';
$services = $db->fetchAll(
    "SELECT * FROM services " . ($filter_cat ? "WHERE category='{$filter_cat}'" : "") . " ORDER BY category, name"
);

// Active reservations for POS
$active_res = $db->fetchAll("
    SELECT r.reservation_id, g.name guest_name, rm.room_number
    FROM reservations r
    JOIN guests g ON r.guest_id=g.guest_id
    LEFT JOIN rooms rm ON r.room_id=rm.room_id
    WHERE r.status='CheckedIn'
    ORDER BY rm.room_number
");

$categories = ['Spa','Cafe','Minibar','Tour','Laundry','Other'];

$stats = [
    'total'    => $db->fetchOne("SELECT COUNT(*) c FROM services WHERE active=1")['c'] ?? 0,
    'revenue'  => $db->fetchOne("SELECT COALESCE(SUM(fc.amount),0) s FROM folio_charges fc WHERE fc.charge_type='Service' AND DATE(fc.charged_at)=CURDATE()")['s'] ?? 0,
    'today'    => $db->fetchOne("SELECT COUNT(*) c FROM folio_charges WHERE charge_type='Service' AND DATE(charged_at)=CURDATE()")['c'] ?? 0,
];

$categoryIcons = ['Spa'=>'💆','Cafe'=>'☕','Minibar'=>'🍷','Tour'=>'🗺','Laundry'=>'👔','Other'=>'🛎'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>BHMS — Services</title>
<link rel="stylesheet" href="css/style.css">
<style>
.service-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:18px;transition:var(--transition);cursor:pointer}
.service-card:hover{border-color:var(--border-gold);transform:translateY(-2px);box-shadow:var(--shadow-gold)}
.service-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}
.service-icon{font-size:28px;margin-bottom:10px}
.service-name{font-size:14px;font-weight:600;color:var(--text-primary);margin-bottom:4px}
.service-price{font-family:'Cormorant Garamond',serif;font-size:22px;color:var(--gold)}
.service-meta{font-size:11px;color:var(--text-dim);margin-top:6px}
.cat-tab{cursor:pointer}
</style>
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">🛎 Services Catalog</div>
      <div class="topbar-actions">
        <button onclick="document.getElementById('posModal').classList.add('show')" class="btn btn-outline btn-sm">💳 Post to Room</button>
        <?php if (Auth::can(['Manager','Admin'])): ?>
        <button onclick="document.getElementById('createModal').classList.add('show')" class="btn btn-gold btn-sm">+ Add Service</button>
        <?php endif; ?>
      </div>
    </div>
    <div class="content">

      <!-- Stats -->
      <div class="stat-grid" style="grid-template-columns:repeat(3,1fr)">
        <div class="stat-card"><div class="stat-icon">🛎</div><div class="stat-label">Active Services</div><div class="stat-value"><?= $stats['total'] ?></div></div>
        <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-label">Service Revenue Today</div><div class="stat-value" style="font-size:24px">EGP <?= number_format($stats['revenue']) ?></div></div>
        <div class="stat-card"><div class="stat-icon">📋</div><div class="stat-label">Charges Today</div><div class="stat-value"><?= $stats['today'] ?></div></div>
      </div>

      <!-- Category Filter -->
      <div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap">
        <a href="services.php" class="btn <?= !$filter_cat?'btn-gold':'btn-outline' ?> btn-sm">All</a>
        <?php foreach ($categories as $cat): ?>
        <a href="services.php?cat=<?= $cat ?>" class="btn <?= $filter_cat===$cat?'btn-gold':'btn-outline' ?> btn-sm">
          <?= $categoryIcons[$cat] ?? '🛎' ?> <?= $cat ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Services Grid -->
      <div class="service-grid">
        <?php foreach ($services as $s): ?>
        <div class="service-card <?= !$s['active']?'opacity-50':'' ?>"
             onclick="openEdit(<?= $s['service_id'] ?>,'<?= htmlspecialchars(addslashes($s['name'])) ?>',<?= $s['price'] ?>,<?= $s['cancellation_hrs'] ?>,<?= $s['penalty_pct'] ?>,<?= $s['active'] ?>,'<?= $s['category'] ?>')">
          <div class="service-icon"><?= $categoryIcons[$s['category']] ?? '🛎' ?></div>
          <div class="service-name"><?= htmlspecialchars($s['name']) ?></div>
          <div class="service-price">EGP <?= number_format($s['price'], 2) ?></div>
          <div class="service-meta">
            <span class="badge badge-<?= $s['active']?'green':'red' ?>"><?= $s['active']?'Active':'Inactive' ?></span>
            <span style="margin-left:8px">Cancel: <?= $s['cancellation_hrs'] ?>h | Penalty: <?= $s['penalty_pct'] ?>%</span>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($services)): ?>
        <div style="grid-column:span 4;text-align:center;color:var(--text-dim);padding:40px">No services found</div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<!-- POS: Post to Room Modal -->
<div class="modal-backdrop" id="posModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">💳 Post Service to Room</div>
      <button class="modal-close" onclick="document.getElementById('posModal').classList.remove('show')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="post_to_room">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Active Reservation *</label>
          <select name="reservation_id" class="form-control" required>
            <option value="">— Select guest —</option>
            <?php foreach ($active_res as $r): ?>
            <option value="<?= $r['reservation_id'] ?>">Room <?= $r['room_number'] ?> — <?= htmlspecialchars($r['guest_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Service *</label>
          <select name="service_id" class="form-control" required id="posServiceSel" onchange="updatePrice()">
            <option value="">— Select service —</option>
            <?php foreach ($services as $s): if (!$s['active']) continue; ?>
            <option value="<?= $s['service_id'] ?>" data-price="<?= $s['price'] ?>">
              <?= $categoryIcons[$s['category']] ?? '' ?> <?= htmlspecialchars($s['name']) ?> — EGP <?= number_format($s['price'],2) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Quantity</label>
          <input type="number" name="quantity" id="posQty" class="form-control" value="1" min="1" max="20" onchange="updatePrice()">
        </div>
        <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;text-align:center">
          <div style="font-size:12px;color:var(--text-dim)">Total Amount</div>
          <div id="posTotal" style="font-family:'Cormorant Garamond',serif;font-size:32px;color:var(--gold)">EGP 0.00</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('posModal').classList.remove('show')">Cancel</button>
        <button type="submit" class="btn btn-gold">Post Charge to Room</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Service Modal -->
<?php if (Auth::can(['Manager','Admin'])): ?>
<div class="modal-backdrop" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="editModalTitle">Edit Service</div>
      <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('show')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="service_id" id="editId">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Service Name</label>
          <input type="text" name="name" id="editName" class="form-control" required></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Price (EGP)</label>
            <input type="number" name="price" id="editPrice" class="form-control" step="0.01" min="0" required></div>
          <div class="form-group"><label class="form-label">Cancel Window (hrs)</label>
            <input type="number" name="cancellation_hrs" id="editCancel" class="form-control" min="0"></div>
        </div>
        <div class="form-group"><label class="form-label">Cancellation Penalty %</label>
          <input type="number" name="penalty_pct" id="editPenalty" class="form-control" step="0.01" min="0" max="100"></div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
            <input type="checkbox" name="active" id="editActive" style="width:16px;height:16px;accent-color:var(--gold)">
            <span class="form-label" style="margin:0">Service is Active</span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('editModal').classList.remove('show')">Cancel</button>
        <button type="submit" class="btn btn-gold">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Create Service Modal -->
<?php if (Auth::can(['Manager','Admin'])): ?>
<div class="modal-backdrop" id="createModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Add New Service</div>
      <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('show')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Service Name *</label>
            <input type="text" name="name" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Category *</label>
            <select name="category" class="form-control" required>
              <?php foreach ($categories as $cat): ?><option value="<?= $cat ?>"><?= $cat ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Price (EGP) *</label>
            <input type="number" name="price" class="form-control" step="0.01" min="0.01" required></div>
          <div class="form-group"><label class="form-label">Cancel Window (hrs)</label>
            <input type="number" name="cancellation_hrs" class="form-control" value="24" min="0"></div>
        </div>
        <div class="form-group"><label class="form-label">Cancellation Penalty %</label>
          <input type="number" name="penalty_pct" class="form-control" value="50" step="0.01" min="0" max="100"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('createModal').classList.remove('show')">Cancel</button>
        <button type="submit" class="btn btn-gold">Create Service</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="toast-container" id="toasts"></div>
<script>
function updatePrice() {
  const sel = document.getElementById('posServiceSel');
  const qty = parseInt(document.getElementById('posQty').value) || 1;
  const price = parseFloat(sel.options[sel.selectedIndex]?.dataset?.price || 0);
  document.getElementById('posTotal').textContent = 'EGP ' + (price * qty).toFixed(2);
}

function openEdit(id, name, price, cancel, penalty, active, cat) {
  document.getElementById('editId').value      = id;
  document.getElementById('editModalTitle').textContent = 'Edit: ' + name;
  document.getElementById('editName').value    = name;
  document.getElementById('editPrice').value   = price;
  document.getElementById('editCancel').value  = cancel;
  document.getElementById('editPenalty').value = penalty;
  document.getElementById('editActive').checked = active == 1;
  document.getElementById('editModal').classList.add('show');
}

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
</body></html>
