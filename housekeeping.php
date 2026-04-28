<?php
// housekeeping.php
require_once 'includes/auth.php';
Auth::check();
$db = db();

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';

    if ($pa === 'create_task') {
        $room_id = (int)$_POST['room_id'];
        $type    = $_POST['type'];
        $priority= (int)$_POST['priority'];
        $assigned= (int)($_POST['assigned_to'] ?? 0) ?: null;
        $notes   = trim($_POST['notes'] ?? '');
        $id = $db->insert("INSERT INTO hk_tasks (room_id,assigned_to,type,status,priority,notes) VALUES (?,?,'".htmlspecialchars($type)."','Pending',?,?)",
            'iiis', $room_id, $assigned, $priority, $notes);
        // Update room status
        $db->execute("UPDATE rooms SET status='InCleaning' WHERE room_id=?", 'i', $room_id);
        AuditLogger::log('CREATE_HK_TASK','hk_tasks',$id,null,compact('room_id','type'));
        header("Location: housekeeping.php?success=Task+created"); exit;
    }

    if ($pa === 'update_status') {
        $task_id    = (int)$_POST['task_id'];
        $new_status = $_POST['new_status'];
        $score      = isset($_POST['score']) ? (int)$_POST['score'] : null;
        $old = $db->fetchOne("SELECT * FROM hk_tasks WHERE task_id=?", 'i', $task_id);
        $db->execute("UPDATE hk_tasks SET status=?, score=?, completed_at=IF(?='Done',NOW(),NULL) WHERE task_id=?",
            'sisi', $new_status, $score, $new_status, $task_id);
        // Observer: if Done → room Inspecting; if inspection Done → room Ready
        if ($new_status === 'Done' && $old['type'] === 'Cleaning') {
            $db->execute("UPDATE rooms SET status='Inspecting' WHERE room_id=?", 'i', $old['room_id']);
            // Auto-create inspection task
            $db->insert("INSERT INTO hk_tasks (room_id,type,status,priority) VALUES (?,'Inspection','Pending',2)", 'i', $old['room_id']);
        }
        if ($new_status === 'Done' && $old['type'] === 'Inspection') {
            $db->execute("UPDATE rooms SET status='Ready' WHERE room_id=?", 'i', $old['room_id']);
        }
        AuditLogger::log('UPDATE_HK_TASK','hk_tasks',$task_id,$old['status'],$new_status);
        header("Location: housekeeping.php?success=Task+updated"); exit;
    }

    if ($pa === 'assign') {
        $task_id   = (int)$_POST['task_id'];
        $staff_id  = (int)$_POST['staff_id'];
        $db->execute("UPDATE hk_tasks SET assigned_to=? WHERE task_id=?", 'ii', $staff_id, $task_id);
        header("Location: housekeeping.php?success=Task+assigned"); exit;
    }
}

// Data
$filter = $_GET['filter'] ?? 'active';
$where  = $filter === 'active' ? "WHERE h.status IN ('Pending','InProgress')" :
          ($filter === 'done'  ? "WHERE h.status='Done'" : "WHERE 1=1");

$tasks = $db->fetchAll("
    SELECT h.*, rm.room_number, rm.floor, rt.name room_type, s.name hk_name
    FROM hk_tasks h
    JOIN rooms rm ON h.room_id=rm.room_id
    JOIN room_types rt ON rm.type_id=rt.type_id
    LEFT JOIN staff s ON h.assigned_to=s.staff_id
    {$where}
    ORDER BY h.priority DESC, h.created_at ASC
    LIMIT 80
");

$rooms_dirty   = $db->fetchAll("SELECT rm.room_id, rm.room_number, rt.name type_name FROM rooms rm JOIN room_types rt ON rm.type_id=rt.type_id WHERE rm.status IN ('Dirty','InCleaning','Inspecting') ORDER BY rm.room_number");
$housekeepers  = $db->fetchAll("SELECT staff_id, name FROM staff WHERE role IN ('Housekeeper','Supervisor') AND active=1 ORDER BY name");

$stats = [
    'pending'    => $db->fetchOne("SELECT COUNT(*) c FROM hk_tasks WHERE status='Pending'")['c']    ?? 0,
    'inprogress' => $db->fetchOne("SELECT COUNT(*) c FROM hk_tasks WHERE status='InProgress'")['c'] ?? 0,
    'done_today' => $db->fetchOne("SELECT COUNT(*) c FROM hk_tasks WHERE status='Done' AND DATE(completed_at)=CURDATE()")['c'] ?? 0,
    'dirty_rooms'=> $db->fetchOne("SELECT COUNT(*) c FROM rooms WHERE status='Dirty'")['c']          ?? 0,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>BHMS — Housekeeping</title>
<link rel="stylesheet" href="css/style.css">
<style>
.priority-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600}
.p1{color:var(--text-dim)} .p2{color:var(--amber)} .p3{color:var(--red)} .p4{color:#FF3B30}
.task-row-p3 td,.task-row-p4 td{background:rgba(224,92,92,.03)}
</style>
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">Housekeeping</div>
      <div class="topbar-actions">
        <button onclick="document.getElementById('createModal').classList.add('show')" class="btn btn-gold btn-sm">+ New Task</button>
      </div>
    </div>
    <div class="content">
      <!-- Stats -->
      <div class="stat-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-label">Pending</div><div class="stat-value"><?= $stats['pending'] ?></div></div>
        <div class="stat-card"><div class="stat-icon">🔄</div><div class="stat-label">In Progress</div><div class="stat-value"><?= $stats['inprogress'] ?></div></div>
        <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-label">Done Today</div><div class="stat-value"><?= $stats['done_today'] ?></div></div>
        <div class="stat-card"><div class="stat-icon">🧹</div><div class="stat-label">Dirty Rooms</div><div class="stat-value"><?= $stats['dirty_rooms'] ?></div></div>
      </div>

      <!-- Filter tabs -->
      <div style="display:flex;gap:8px;margin-bottom:20px">
        <?php foreach (['active'=>'Active','done'=>'Completed','all'=>'All'] as $k=>$v): ?>
        <a href="?filter=<?= $k ?>" class="btn <?= $filter===$k?'btn-gold':'btn-outline' ?> btn-sm"><?= $v ?></a>
        <?php endforeach; ?>
      </div>

      <!-- Tasks Table -->
      <div class="card">
        <div class="card-header"><div class="card-title">Tasks</div><span style="font-size:12px;color:var(--text-dim)"><?= count($tasks) ?> tasks</span></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Room</th><th>Floor</th><th>Type</th><th>Priority</th><th>Assigned To</th><th>Status</th><th>Score</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($tasks as $t):
                $pLabels = [1=>'Low',2=>'Medium',3=>'High',4=>'Critical'];
              ?>
              <tr class="task-row-p<?= $t['priority'] ?>">
                <td><strong><?= htmlspecialchars($t['room_number']) ?></strong><div style="font-size:11px;color:var(--text-dim)"><?= htmlspecialchars($t['room_type']) ?></div></td>
                <td style="color:var(--text-muted)">F<?= $t['floor'] ?></td>
                <td><?= $t['type'] ?></td>
                <td><span class="priority-badge p<?= $t['priority'] ?>">● <?= $pLabels[$t['priority']] ?? $t['priority'] ?></span></td>
                <td><?= htmlspecialchars($t['hk_name'] ?? '—') ?></td>
                <td><span class="badge badge-<?= $t['status']==='Done'?'green':($t['status']==='InProgress'?'blue':'amber') ?>"><?= $t['status'] ?></span></td>
                <td><?= $t['score'] ? $t['score'].'/10' : '—' ?></td>
                <td style="color:var(--text-dim);font-size:12px"><?= date('d M H:i', strtotime($t['created_at'])) ?></td>
                <td>
                  <?php if ($t['status'] !== 'Done'): ?>
                  <!-- Quick assign -->
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="assign">
                    <input type="hidden" name="task_id" value="<?= $t['task_id'] ?>">
                    <select name="staff_id" class="form-control" style="display:inline;width:auto;padding:4px 8px;font-size:12px" onchange="this.form.submit()">
                      <option value="">Assign…</option>
                      <?php foreach ($housekeepers as $hk): ?>
                      <option value="<?= $hk['staff_id'] ?>" <?= $t['assigned_to']==$hk['staff_id']?'selected':'' ?>><?= htmlspecialchars($hk['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                  <!-- Update status -->
                  <form method="POST" style="display:inline;margin-left:6px">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="task_id" value="<?= $t['task_id'] ?>">
                    <?php if ($t['type'] === 'Inspection'): ?>
                    <input type="hidden" name="score" id="score_<?= $t['task_id'] ?>" value="8">
                    <?php endif; ?>
                    <select name="new_status" class="form-control" style="display:inline;width:auto;padding:4px 8px;font-size:12px" onchange="this.form.submit()">
                      <option value="">Update…</option>
                      <?php if ($t['status']==='Pending'): ?>
                      <option value="InProgress">Start</option>
                      <?php endif; ?>
                      <?php if (in_array($t['status'],['Pending','InProgress'])): ?>
                      <option value="Done">Mark Done</option>
                      <?php endif; ?>
                      <option value="Skipped">Skip</option>
                    </select>
                  </form>
                  <?php else: ?>
                  <span style="color:var(--text-dim);font-size:12px"><?= $t['completed_at'] ? date('d M H:i',strtotime($t['completed_at'])) : 'Done' ?></span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($tasks)): ?>
              <tr><td colspan="9" style="text-align:center;color:var(--green);padding:40px">✓ All clear — no pending tasks</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Create Task Modal -->
<div class="modal-backdrop" id="createModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">New Housekeeping Task</div>
      <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('show')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create_task">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Room *</label>
          <select name="room_id" class="form-control" required>
            <option value="">— Select room —</option>
            <?php foreach ($rooms_dirty as $r): ?>
            <option value="<?= $r['room_id'] ?>"><?= $r['room_number'] ?> — <?= $r['type_name'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Task Type</label>
            <select name="type" class="form-control">
              <option>Cleaning</option><option>Inspection</option><option>Turndown</option><option>DeepClean</option><option>Special</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Priority</label>
            <select name="priority" class="form-control">
              <option value="1">Low</option><option value="2" selected>Medium</option>
              <option value="3">High</option><option value="4">Critical</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label">Assign To</label>
          <select name="assigned_to" class="form-control">
            <option value="">— Unassigned —</option>
            <?php foreach ($housekeepers as $hk): ?>
            <option value="<?= $hk['staff_id'] ?>"><?= htmlspecialchars($hk['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Special instructions..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('createModal').classList.remove('show')">Cancel</button>
        <button type="submit" class="btn btn-gold">Create Task</button>
      </div>
    </form>
  </div>
</div>

<div class="toast-container" id="toasts"></div>
<script>
const p=new URLSearchParams(location.search);
if(p.get('success')){const el=document.createElement('div');el.className='toast success';el.innerHTML='<span class="toast-icon">✓</span><span class="toast-msg">'+p.get('success')+'</span>';document.getElementById('toasts').appendChild(el);setTimeout(()=>el.remove(),4000)}
</script>
</body></html>
