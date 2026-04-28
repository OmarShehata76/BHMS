<?php
// staff.php — Staff & Role Management
require_once 'includes/auth.php';
Auth::requireRole(['Manager','Admin']);
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';
    if ($pa === 'create') {
        $name  = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role  = $_POST['role'];
        $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $id = $db->insert("INSERT INTO staff (name,email,password_hash,role) VALUES (?,?,?,?)", 'ssss', $name,$email,$pass,$role);
        AuditLogger::log('CREATE_STAFF','staff',$id,null,compact('name','role'));
        header("Location: staff.php?success=Staff+member+created"); exit;
    }
    if ($pa === 'toggle_active') {
        $sid = (int)$_POST['staff_id'];
        $db->execute("UPDATE staff SET active = NOT active WHERE staff_id=?", 'i', $sid);
        header("Location: staff.php?success=Status+toggled"); exit;
    }
    if ($pa === 'change_role') {
        $sid  = (int)$_POST['staff_id'];
        $role = $_POST['role'];
        $db->execute("UPDATE staff SET role=? WHERE staff_id=?", 'si', $role, $sid);
        AuditLogger::log('CHANGE_ROLE','staff',$sid,null,$role);
        header("Location: staff.php?success=Role+updated"); exit;
    }
    if ($pa === 'reset_password') {
        $sid  = (int)$_POST['staff_id'];
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $db->execute("UPDATE staff SET password_hash=? WHERE staff_id=?", 'si', $pass, $sid);
        AuditLogger::log('RESET_PASSWORD','staff',$sid);
        header("Location: staff.php?success=Password+reset"); exit;
    }
}

$staff_list = $db->fetchAll("SELECT staff_id,name,email,role,active,last_login,created_at FROM staff ORDER BY name");
$roles = ['Receptionist','Housekeeper','Supervisor','Manager','Accountant','Admin','NightAuditor'];

$role_perms = [
    'Receptionist' => ['Check-in/out','Reservations','Guest profiles','Room status view'],
    'Housekeeper'  => ['HK Tasks','Room status update','Lost & Found'],
    'Supervisor'   => ['HK Tasks','Inspection','Staff task view'],
    'Manager'      => ['All of above','Reports','Staff management','Night Audit','Pricing'],
    'Accountant'   => ['Folios','Payments','Reports','Audit Trail'],
    'Admin'        => ['Full system access','RBAC','GDPR','Settings'],
    'NightAuditor' => ['Night Audit Simulator','Reports','Audit Trail'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>BHMS — Staff</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">Staff & Role Management</div>
      <div class="topbar-actions">
        <button onclick="document.getElementById('createModal').classList.add('show')" class="btn btn-gold btn-sm">+ Add Staff</button>
      </div>
    </div>
    <div class="content">

      <!-- Role Permissions Overview -->
      <div class="card" style="margin-bottom:24px">
        <div class="card-header"><div class="card-title">Role Permissions (RBAC)</div></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:0">
          <?php foreach ($role_perms as $role=>$perms): ?>
          <div style="padding:16px 18px;border-right:1px solid var(--border);border-bottom:1px solid var(--border)">
            <div style="font-size:12px;font-weight:600;color:var(--gold);margin-bottom:8px;letter-spacing:.05em"><?= $role ?></div>
            <?php foreach ($perms as $p): ?>
            <div style="font-size:11px;color:var(--text-muted);display:flex;align-items:center;gap:5px;margin-bottom:3px">
              <span style="color:var(--green);font-size:9px">●</span><?= $p ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Staff Table -->
      <div class="card">
        <div class="card-header"><div class="card-title">All Staff Members</div><span style="font-size:12px;color:var(--text-dim)"><?= count($staff_list) ?> members</span></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($staff_list as $s): ?>
              <tr>
                <td style="color:var(--text-dim)"><?= $s['staff_id'] ?></td>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td style="color:var(--text-muted)"><?= htmlspecialchars($s['email']) ?></td>
                <td>
                  <!-- Inline role change -->
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="change_role">
                    <input type="hidden" name="staff_id" value="<?= $s['staff_id'] ?>">
                    <select name="role" class="form-control" style="padding:4px 8px;font-size:12px;width:auto;display:inline" onchange="this.form.submit()">
                      <?php foreach ($roles as $r): ?>
                      <option value="<?= $r ?>" <?= $s['role']===$r?'selected':'' ?>><?= $r ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                </td>
                <td style="font-size:12px;color:var(--text-dim)"><?= $s['last_login'] ? date('d M Y H:i', strtotime($s['last_login'])) : 'Never' ?></td>
                <td><?php if ($s['active']): ?><span class="badge badge-green">Active</span><?php else: ?><span class="badge badge-red">Disabled</span><?php endif; ?></td>
                <td style="display:flex;gap:6px">
                  <!-- Toggle active -->
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="staff_id" value="<?= $s['staff_id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm"><?= $s['active']?'Disable':'Enable' ?></button>
                  </form>
                  <!-- Reset password -->
                  <button onclick="openReset(<?= $s['staff_id'] ?>,'<?= htmlspecialchars(addslashes($s['name'])) ?>')" class="btn btn-outline btn-sm">Reset Pwd</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Create Staff Modal -->
<div class="modal-backdrop" id="createModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Add Staff Member</div>
      <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('show')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Role *</label>
          <select name="role" class="form-control" required>
            <?php foreach ($roles as $r): ?><option value="<?= $r ?>"><?= $r ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" minlength="8" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('createModal').classList.remove('show')">Cancel</button>
        <button type="submit" class="btn btn-gold">Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-backdrop" id="resetModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Reset Password — <span id="resetName"></span></div>
      <button class="modal-close" onclick="document.getElementById('resetModal').classList.remove('show')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="staff_id" id="resetStaffId">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">New Password *</label><input type="password" name="password" class="form-control" minlength="8" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('resetModal').classList.remove('show')">Cancel</button>
        <button type="submit" class="btn btn-gold">Reset</button>
      </div>
    </form>
  </div>
</div>

<div class="toast-container" id="toasts"></div>
<script>
function openReset(id, name) {
  document.getElementById('resetStaffId').value = id;
  document.getElementById('resetName').textContent = name;
  document.getElementById('resetModal').classList.add('show');
}
const p=new URLSearchParams(location.search);
if(p.get('success')){const el=document.createElement('div');el.className='toast success';el.innerHTML='<span class="toast-icon">✓</span><span class="toast-msg">'+p.get('success')+'</span>';document.getElementById('toasts').appendChild(el);setTimeout(()=>el.remove(),4000)}
</script>
</body></html>
