<?php
// inventory.php — Linen & Consumable Inventory Management
require_once 'includes/auth.php';
Auth::check();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';

    if ($pa === 'create') {
        $name      = trim($_POST['name']);
        $category  = trim($_POST['category']);
        $quantity  = (int)$_POST['quantity'];
        $threshold = (int)$_POST['threshold'];
        $unit      = trim($_POST['unit']);
        $id = $db->insert(
            "INSERT INTO inventory (name,category,quantity,threshold,unit) VALUES (?,?,?,?,?)",
            'ssii s', $name, $category, $quantity, $threshold, $unit
        );
        AuditLogger::log('CREATE_INVENTORY','inventory',$id,null,compact('name','quantity'));
        header("Location: inventory.php?success=Item+added"); exit;
    }

    if ($pa === 'restock') {
        $item_id = (int)$_POST['item_id'];
        $qty_add = (int)$_POST['qty_add'];
        $old = $db->fetchOne("SELECT quantity FROM inventory WHERE item_id=?", 'i', $item_id);
        $db->execute("UPDATE inventory SET quantity = quantity + ? WHERE item_id=?", 'ii', $qty_add, $item_id);
        AuditLogger::log('RESTOCK_INVENTORY','inventory',$item_id,$old['quantity'],$old['quantity']+$qty_add);
        header("Location: inventory.php?success=Stock+updated"); exit;
    }

    if ($pa === 'deduct') {
        $item_id = (int)$_POST['item_id'];
        $qty_use = (int)$_POST['qty_use'];
        $item = $db->fetchOne("SELECT quantity, name FROM inventory WHERE item_id=?", 'i', $item_id);
        if ($item['quantity'] < $qty_use) {
            header("Location: inventory.php?error=Insufficient+stock+for+{$item['name']}"); exit;
        }
        $db->execute("UPDATE inventory SET quantity = quantity - ? WHERE item_id=?", 'ii', $qty_use, $item_id);
        AuditLogger::log('DEDUCT_INVENTORY','inventory',$item_id,$item['quantity'],$item['quantity']-$qty_use);
        header("Location: inventory.php?success=Stock+deducted"); exit;
    }

    if ($pa === 'update_threshold') {
        $item_id   = (int)$_POST['item_id'];
        $threshold = (int)$_POST['threshold'];
        $db->execute("UPDATE inventory SET threshold=? WHERE item_id=?", 'ii', $threshold, $item_id);
        header("Location: inventory.php?success=Threshold+updated"); exit;
    }
}

// Data
$filter = $_GET['filter'] ?? 'all';
$where  = $filter === 'low' ? "WHERE quantity <= threshold" : "WHERE 1=1";
$items  = $db->fetchAll("SELECT * FROM inventory {$where} ORDER BY category, name");

$stats = [
    'total'    => $db->fetchOne("SELECT COUNT(*) c FROM inventory")['c'] ?? 0,
    'low'      => $db->fetchOne("SELECT COUNT(*) c FROM inventory WHERE quantity <= threshold")['c'] ?? 0,
    'critical' => $db->fetchOne("SELECT COUNT(*) c FROM inventory WHERE quantity = 0")['c'] ?? 0,
];

$categories = array_unique(array_column($items, 'category'));
$filter_cat = $_GET['cat'] ?? '';
if ($filter_cat) {
    $items = array_filter($items, fn($i) => $i['category'] === $filter_cat);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>BHMS — Inventory</title>
<link rel="stylesheet" href="css/style.css">
<style>
.stock-bar-wrap{width:100%;height:6px;background:var(--bg-panel);border-radius:3px;margin-top:4px}
.stock-bar{height:6px;border-radius:3px;transition:width .4s ease}
.bar-ok{background:var(--green)}
.bar-low{background:var(--amber)}
.bar-critical{background:var(--red)}
</style>
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">📦 Inventory</div>
      <div class="topbar-actions">
        <?php if (Auth::can(['Manager','Admin','Supervisor'])): ?>
        <button onclick="document.getElementById('createModal').classList.add('show')" class="btn btn-gold btn-sm">+ Add Item</button>
        <?php endif; ?>
      </div>
    </div>
    <div class="content">

      <!-- Stats -->
      <div class="stat-grid" style="grid-template-columns:repeat(3,1fr)">
        <div class="stat-card"><div class="stat-icon">📦</div><div class="stat-label">Total Items</div><div class="stat-value"><?= $stats['total'] ?></div></div>
        <div class="stat-card" style="border-color:<?= $stats['low']>0?'rgba(240,160,64,.3)':'var(--border)' ?>">
          <div class="stat-icon">⚠</div><div class="stat-label">Low Stock</div>
          <div class="stat-value" style="color:<?= $stats['low']>0?'var(--amber)':'var(--text-primary)' ?>"><?= $stats['low'] ?></div>
        </div>
        <div class="stat-card" style="border-color:<?= $stats['critical']>0?'rgba(224,92,92,.3)':'var(--border)' ?>">
          <div class="stat-icon">🚨</div><div class="stat-label">Out of Stock</div>
          <div class="stat-value" style="color:<?= $stats['critical']>0?'var(--red)':'var(--text-primary)' ?>"><?= $stats['critical'] ?></div>
        </div>
      </div>

      <!-- Low stock alert banner -->
      <?php if ($stats['low'] > 0): ?>
      <div style="background:rgba(240,160,64,.08);border:1px solid rgba(240,160,64,.25);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px">
        <span style="font-size:18px">⚠</span>
        <span style="font-size:13px;color:var(--amber)"><?= $stats['low'] ?> item(s) are at or below reorder threshold. Restock recommended.</span>
        <a href="?filter=low" class="btn btn-outline btn-sm" style="margin-left:auto">View Low Stock</a>
      </div>
      <?php endif; ?>

      <!-- Filters -->
      <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
        <a href="inventory.php" class="btn <?= $filter==='all'&&!$filter_cat?'btn-gold':'btn-outline' ?> btn-sm">All</a>
        <a href="inventory.php?filter=low" class="btn <?= $filter==='low'?'btn-gold':'btn-outline' ?> btn-sm">⚠ Low Stock</a>
        <?php foreach ($categories as $cat): ?>
        <a href="inventory.php?cat=<?= urlencode($cat) ?>" class="btn <?= $filter_cat===$cat?'btn-gold':'btn-outline' ?> btn-sm"><?= htmlspecialchars($cat) ?></a>
        <?php endforeach; ?>
      </div>

      <!-- Inventory Table -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Stock Levels</div>
          <span style="font-size:12px;color:var(--text-dim)"><?= count($items) ?> items</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Item</th><th>Category</th><th>Unit</th><th>Stock Level</th><th>Threshold</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($items as $item):
                $pct = $item['threshold'] > 0 ? min(100, $item['quantity'] / ($item['threshold'] * 2) * 100) : 100;
                $barClass = $item['quantity'] == 0 ? 'bar-critical' : ($item['quantity'] <= $item['threshold'] ? 'bar-low' : 'bar-ok');
                $statusClass = $item['quantity'] == 0 ? 'badge-red' : ($item['quantity'] <= $item['threshold'] ? 'badge-amber' : 'badge-green');
                $statusLabel = $item['quantity'] == 0 ? 'Out of Stock' : ($item['quantity'] <= $item['threshold'] ? 'Low Stock' : 'OK');
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                <td><span class="badge badge-gray"><?= htmlspecialchars($item['category']) ?></span></td>
                <td style="color:var(--text-muted)"><?= htmlspecialchars($item['unit']) ?></td>
                <td>
                  <div style="font-weight:600;font-size:16px"><?= number_format($item['quantity']) ?></div>
                  <div class="stock-bar-wrap"><div class="stock-bar <?= $barClass ?>" style="width:<?= $pct ?>%"></div></div>
                </td>
                <td style="color:var(--text-muted)"><?= number_format($item['threshold']) ?></td>
                <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                <td>
                  <div style="display:flex;gap:6px;align-items:center">
                    <!-- Restock -->
                    <form method="POST" style="display:flex;gap:4px;align-items:center">
                      <input type="hidden" name="action" value="restock">
                      <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                      <input type="number" name="qty_add" class="form-control" style="width:60px;padding:4px 8px;font-size:12px" placeholder="qty" min="1">
                      <button type="submit" class="btn btn-success btn-sm">+ Restock</button>
                    </form>
                    <!-- Deduct -->
                    <form method="POST" style="display:flex;gap:4px;align-items:center">
                      <input type="hidden" name="action" value="deduct">
                      <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                      <input type="number" name="qty_use" class="form-control" style="width:60px;padding:4px 8px;font-size:12px" placeholder="qty" min="1" max="<?= $item['quantity'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm">− Use</button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($items)): ?>
              <tr><td colspan="7" style="text-align:center;color:var(--text-dim);padding:30px">No items found</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Item Modal -->
<div class="modal-backdrop" id="createModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Add Inventory Item</div>
      <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('show')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Item Name *</label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. Bath Towels"></div>
          <div class="form-group"><label class="form-label">Category *</label>
            <input type="text" name="category" class="form-control" required placeholder="Linen, Minibar, Consumable..."></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Initial Quantity *</label>
            <input type="number" name="quantity" class="form-control" min="0" required></div>
          <div class="form-group"><label class="form-label">Unit</label>
            <input type="text" name="unit" class="form-control" value="pcs" placeholder="pcs, sets, kg..."></div>
        </div>
        <div class="form-group"><label class="form-label">Reorder Threshold *</label>
          <input type="number" name="threshold" class="form-control" min="1" required placeholder="Alert when stock drops below this number"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('createModal').classList.remove('show')">Cancel</button>
        <button type="submit" class="btn btn-gold">Add Item</button>
      </div>
    </form>
  </div>
</div>

<div class="toast-container" id="toasts"></div>
<script>
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
