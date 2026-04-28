<?php
// maintenance.php — Maintenance Work-Order Management
require_once 'includes/auth.php';
Auth::check();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';

    if ($pa === 'create') {
        $room_id  = (int)$_POST['room_id'];
        $issue    = trim($_POST['issue']);
        $priority = $_POST['priority'];
        $id = $db->insert(
            "INSERT INTO maintenance_requests (room_id, reported_by, issue, priority, status) VALUES (?,?,?,?,'Open')",
            'iiss', $room_id, Auth::id(), $issue, $priority
        );
        // If Critical → mark room Out of Order
        if ($priority === 'Critical') {
            $db->execute("UPDATE rooms SET status='OutOfOrder' WHERE room_id=?", 'i', $room_id);
        }
        AuditLogger::log('CREATE_MAINTENANCE','maintenance_requests',$id,null,compact('room_id','issue','priority'));
        header("Location: maintenance.php?success=Work+order+created"); exit;
    }

    if ($pa === 'assign') {
        $req_id  = (int)$_POST['request_id'];
        $staff_id= (int)$_POST['staff_id'];
        $db->execute("UPDATE maintenance_requests SET assigned_to=?, status='InProgress' WHERE request_id=?", 'ii', $staff_id, $req_id);
        AuditLogger::log('ASSIGN_MAINTENANCE','maintenance_requests',$req_id,null,$staff_id);
        header("Location: maintenance.php?success=Assigned+to+technician"); exit;
    }

    if ($pa === 'resolve') {
        $req_id     = (int)$_POST['request_id'];
        $resolution = trim($_POST['resolution']);
        $old = $db->fetchOne("SELECT * FROM maintenance_requests WHERE request_id=?", 'i', $req_id);
        $db->execute(
            "UPDATE maintenance_requests SET status='Resolved', resolution=?, resolved_at=NOW() WHERE request_id=?",
            'si', $resolution, $req_id
        );
        // If room was OOO → set back to Ready
        if ($old && $old['room_id']) {
            $room = $db->fetchOne("SELECT status FROM rooms WHERE room_id=?", 'i', $old['room_id']);
            if ($room && $room['status'] === 'OutOfOrder') {
                $db->execute("UPDATE rooms SET status='Ready' WHERE room_id=?", 'i', $old['room_id']);
            }
        }
        AuditLogger::log('RESOLVE_MAINTENANCE','maintenance_requests',$req_id,'Open','Resolved');
        header("Location: maintenance.php?success=Issue+resolved"); exit;
    }

    if ($pa === 'escalate') {
        $req_id = (int)$_POST['request_id'];
        $db->execute("UPDATE maintenance_requests SET priority='Critical', status='Escalated' WHERE request_id=?", 'i', $req_id);
        $req = $db->fetchOne("SELECT room_id FROM maintenance_requests WHERE request_id=?", 'i', $req_id);
        if ($req['room_id']) {
            $db->execute("UPDATE rooms SET status='OutOfOrder' WHERE room_id=?", 'i', $req['room_id']);
        }
        AuditLogger::log('ESCALATE_MAINTENANCE','maintenance_requests',$req_id,'Open','Critical');
        header("Location: maintenance.php?success=Escalated+to+Critical"); exit;
    }
}

// Data
$filter = $_GET['filter'] ?? 'open';
$where = match($filter) {
    'open'     => "WHERE mr.status IN ('Open','InProgress','Escalated')",
    'resolved' => "WHERE mr.status='Resolved'",
    default    => "WHERE 1=1"
};

$requests = $db->fetchAll("
    SELECT mr.*, rm.room_number, rm.floor, rt.name room_type,
           r.name reporter_name, a.name assigned_name
    FROM maintenance_requests mr
    JOIN rooms rm ON mr.room_id=rm.room_id
    JOIN room_types rt ON rm.type_id=rt.type_id
    LEFT JOIN staff r ON mr.reported_by=r.staff_id
    LEFT JOIN staff a ON mr.assigned_to=a.staff_id
    {$where}
    ORDER BY FIELD(mr.priority,'Critical','High','Medium','Low'), mr.created_at ASC
    LIMIT 80
");

$rooms       = $db->fetchAll("SELECT rm.room_id, rm.room_number, rt.name type_name FROM rooms rm JOIN room_types rt ON rm.type_id=rt.type_id ORDER BY rm.room_number");
$technicians = $db->fetchAll("SELECT staff_id, name FROM staff WHERE role IN ('Supervisor','Manager','Admin') AND active=1 ORDER BY name");

$stats = [
    'open'     => $db->fetchOne("SELECT COUNT(*) c FROM maintenance_requests WHERE status='Open'")['c']      ?? 0,
    'progress' => $db->fetchOne("SELECT COUNT(*) c FROM maintenance_requests WHERE status='InProgress'")['c'] ?? 0,
    'critical' => $db->fetchOne("SELECT COUNT(*) c FROM maintenance_requests WHERE priority='Critical' AND status!='Resolved'")['c'] ?? 0,
    'resolved' => $db->fetchOne("SELECT COUNT(*) c FROM maintenance_requests WHERE status='Resolved' AND DATE(resolved_at)=CURDATE()")['c'] ?? 0,
];

$pColors = ['Low'=>'var(--text-dim)','Medium'=>'var(--amber)','High'=>'var(--red)','Critical'=>'#FF3B30'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>BHMS — Maintenance</title>
<link rel="stylesheet" href="css/style.css">
<style>
.priority-tag{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600}
.issue-text{max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:13px}
</style>
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">🔧 Maintenance</div>
      <div class="topbar-actions">
        <button onclick="document.getElementById('createModal').classList.add('show')" class="btn btn-gold btn-sm">+ New Work Order</button>
      </div>
    </div>
    <div class="content">

      <!-- Stats -->
      <div class="stat-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="stat-card"><div class="stat-icon">🔓</div><div class="stat-label">Open</div><div class="stat-value"><?= $stats['open'] ?></div></div>
        <div class="stat-card"><div class="stat-icon">🔄</div><div class="stat-label">In Progress</div><div class="stat-value"><?= $stats['progress'] ?></div></div>
        <div class="stat-card" style="border-color:<?= $stats['critical']>0?'rgba(255,59,48,.3)':'var(--border)' ?>">
          <div class="stat-icon">🚨</div><div class="stat-label">Critical</div>
          <div class="stat-value" style="color:<?= $stats['critical']>0?'var(--red)':'var(--text-primary)' ?>"><?= $stats['critical'] ?></div>
        </div>
        <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-label">Resolved Today</div><div class="stat-value"><?= $stats['resolved'] ?></div></div>
      </div>

      <!-- Filter -->
      <div style="display:flex;gap:8px;margin-bottom:20px">
        <?php foreach(['open'=>'Active','resolved'=>'Resolved','all'=>'All'] as $k=>$v): ?>
        <a href="?filter=<?= $k ?>" class="btn <?= $filter===$k?'btn-gold':'btn-outline' ?> btn-sm"><?= $v ?></a>
        <?php endforeach; ?>
      </div>

      <!-- Table -->
      <div class="card">
        <div class="card-header"><div class="card-title">Work Orders</div><span style="font-size:12px;color:var(--text-dim)"><?= count($requests) ?> records</span></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>#</th><th>Room</th><th>Issue</th><th>Priority</th><th>Reported By</th><th>Assigned To</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($requests as $r): ?>
              <tr>
                <td style="color:var(--text-dim)"><?= $r['request_id'] ?></td>
                <td>
                  <strong><?= $r['room_number'] ?></strong>
                  <div style="font-size:11px;color:var(--text-dim)"><?= $r['room_type'] ?> · F<?= $r['floor'] ?></div>
                </td>
                <td><div class="issue-text" title="<?= htmlspecialchars($r['issue']) ?>"><?= htmlspecialchars($r['issue']) ?></div></td>
                <td><span class="priority-tag" style="color:<?= $pColors[$r['priority']] ?? 'var(--text-dim)' ?>">● <?= $r['priority'] ?></span></td>
                <td style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($r['reporter_name'] ?? '—') ?></td>
                <td>
                  <?php if ($r['status'] !== 'Resolved'): ?>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="assign">
                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                    <select name="staff_id" class="form-control" style="padding:4px 8px;font-size:12px;width:auto;display:inline" onchange="this.form.submit()">
                      <option value="">Assign…</option>
                      <?php foreach ($technicians as $t): ?>
                      <option value="<?= $t['staff_id'] ?>" <?= $r['assigned_to']==$t['staff_id']?'selected':'' ?>><?= htmlspecialchars($t['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                  <?php else: ?>
                  <span style="font-size:12px;color:var(--text-dim)"><?= htmlspecialchars($r['assigned_name'] ?? '—') ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php
                  $sc = match($r['status']) {
                    'Open'      => 'badge-amber',
                    'InProgress'=> 'badge-blue',
                    'Resolved'  => 'badge-green',
                    'Escalated' => 'badge-red',
                    default     => 'badge-gray'
                  };
                  ?><span class="badge <?= $sc ?>"><?= $r['status'] ?></span>
                </td>
                <td style="font-size:11px;color:var(--text-dim)"><?= date('d M H:i', strtotime($r['created_at'])) ?></td>
                <td>
                  <?php if ($r['status'] !== 'Resolved'): ?>
                  <button onclick="openResolve(<?= $r['request_id'] ?>)" class="btn btn-success btn-sm">Resolve</button>
                  <?php if ($r['priority'] !== 'Critical'): ?>
                  <form method="POST" style="display:inline;margin-left:4px">
                    <input type="hidden" name="action" value="escalate">
                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Escalate to Critical?')">Escalate</button>
                  </form>
                  <?php endif; ?>
                  <?php else: ?>
                  <span style="font-size:11px;color:var(--text-dim)"><?= $r['resolved_at'] ? date('d M H:i',strtotime($r['resolved_at'])) : '' ?></span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($requests)): ?>
              <tr><td colspan="9" style="text-align:center;color:var(--green);padding:40px">✓ No active maintenance issues</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Create Modal -->
<div class="modal-backdrop" id="createModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">New Work Order</div>
      <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('show')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Room *</label>
          <select name="room_id" class="form-control" required>
            <option value="">— Select room —</option>
            <?php foreach ($rooms as $rm): ?>
            <option value="<?= $rm['room_id'] ?>"><?= $rm['room_number'] ?> — <?= $rm['type_name'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Issue Description *</label>
          <textarea name="issue" class="form-control" rows="3" required placeholder="Describe the issue in detail..."></textarea>
        </div>
        <div class="form-group"><label class="form-label">Priority *</label>
          <select name="priority" class="form-control" required>
            <option value="Low">Low</option>
            <option value="Medium" selected>Medium</option>
            <option value="High">High</option>
            <option value="Critical">Critical (marks room Out of Order)</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('createModal').classList.remove('show')">Cancel</button>
        <button type="submit" class="btn btn-gold">Create Work Order</button>
      </div>
    </form>
  </div>
</div>

<!-- Resolve Modal -->
<div class="modal-backdrop" id="resolveModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Resolve Issue</div>
      <button class="modal-close" onclick="document.getElementById('resolveModal').classList.remove('show')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="resolve">
      <input type="hidden" name="request_id" id="resolveId">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Resolution Description *</label>
          <textarea name="resolution" class="form-control" rows="3" required placeholder="Describe how the issue was resolved..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('resolveModal').classList.remove('show')">Cancel</button>
        <button type="submit" class="btn btn-success">Mark Resolved</button>
      </div>
    </form>
  </div>
</div>

<div class="toast-container" id="toasts"></div>
<script>
function openResolve(id) {
  document.getElementById('resolveId').value = id;
  document.getElementById('resolveModal').classList.add('show');
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
