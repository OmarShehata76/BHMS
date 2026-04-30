<?php
// rooms.php — Room Status Floor Map
require_once 'includes/auth.php';
Auth::check();
$db = db();

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = $_POST['action'] ?? '';
    if ($pa === 'update_status') {
        $room_id = (int)$_POST['room_id'];
        $status  = $_POST['status'];
        $old = $db->fetchOne("SELECT status FROM rooms WHERE room_id=?", 'i', $room_id);
        $db->execute("UPDATE rooms SET status=?, updated_at=NOW() WHERE room_id=?", 'si', $status, $room_id);
        AuditLogger::log('UPDATE_ROOM_STATUS','rooms',$room_id,$old['status'],$status);
        header("Location: rooms.php?success=Room+status+updated"); exit;
    }
    if ($pa === 'add_room') {
        Auth::requireRole(['Manager','Admin']);
        $type_id = (int)$_POST['type_id'];
        $number  = trim($_POST['room_number']);
        $floor   = (int)$_POST['floor'];
        $db->insert("INSERT INTO rooms (type_id,room_number,floor,status) VALUES (?,'".htmlspecialchars($number)."',?,'Ready')", 'ii', $type_id, $floor);
        header("Location: rooms.php?success=Room+added"); exit;
    }
}

$rooms      = $db->fetchAll("SELECT rm.*,rt.name type_name,rt.base_price, g.name guest_name FROM rooms rm JOIN room_types rt ON rm.type_id=rt.type_id LEFT JOIN reservations r ON rm.room_id=r.room_id AND r.status='CheckedIn' LEFT JOIN guests g ON r.guest_id=g.guest_id ORDER BY rm.floor,rm.room_number");
$room_types = $db->fetchAll("SELECT * FROM room_types ORDER BY base_price");

// Group by floor
$floors = [];
foreach ($rooms as $r) $floors[$r['floor']][] = $r;
ksort($floors);

$status_info = [
  'Ready'      => ['color'=>'var(--green)', 'label'=>'Ready',       'class'=>'status-ready'],
  'Occupied'   => ['color'=>'var(--blue)',  'label'=>'Occupied',     'class'=>'status-occupied'],
  'Dirty'      => ['color'=>'var(--amber)', 'label'=>'Dirty',        'class'=>'status-dirty'],
  'InCleaning' => ['color'=>'var(--gold)',  'label'=>'In Cleaning',  'class'=>'status-cleaning'],
  'Inspecting' => ['color'=>'var(--amber)', 'label'=>'Inspecting',   'class'=>'status-cleaning'],
  'OutOfOrder' => ['color'=>'var(--red)',   'label'=>'Out of Order', 'class'=>'status-ooo'],
  'Clean'      => ['color'=>'var(--green)', 'label'=>'Clean',        'class'=>'status-ready'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>BHMS — Rooms</title>
<link rel="stylesheet" href="css/style.css">
<style>
.floor-label{font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:500;color:var(--text-primary);margin-bottom:12px;display:flex;align-items:center;gap:12px}
.floor-label::after{content:'';flex:1;height:1px;background:var(--border)}
.room-cell .guest{font-size:10px;color:var(--text-dim);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:110px}
</style>
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">Room Status Map</div>
      <div class="topbar-actions">
        <?php if (Auth::can(['Manager','Admin'])): ?>
        <button onclick="document.getElementById('addRoomModal').classList.add('show')" class="btn btn-outline btn-sm">+ Add Room</button>
        <?php endif; ?>
      </div>
    </div>
    <div class="content">

      <!-- Legend -->
      <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px">
        <?php foreach ($status_info as $s=>$info): ?>
        <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-muted)">
          <span style="width:10px;height:10px;border-radius:50%;background:<?= $info['color'] ?>;display:inline-block"></span>
          <?= $info['label'] ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Floor Map -->
      <?php foreach ($floors as $floor => $room_list): ?>
      <div class="floor-label">Floor <?= $floor ?></div>
      <div class="room-grid" style="margin-bottom:28px">
        <?php foreach ($room_list as $r):
          $si = $status_info[$r['status']] ?? ['class'=>'','label'=>$r['status'],'color'=>'var(--text-dim)'];
        ?>
        <div class="room-cell <?= $si['class'] ?>"
             onclick="openRoomModal(<?= $r['room_id'] ?>,'<?= $r['room_number'] ?>','<?= $r['status'] ?>','<?= htmlspecialchars(addslashes($r['type_name'])) ?>')">
          <span class="room-number"><?= $r['room_number'] ?></span>
          <div class="room-type"><?= htmlspecialchars($r['type_name']) ?></div>
          <div style="margin-top:6px">
            <span style="font-size:10px;color:<?= $si['color'] ?>;font-weight:600"><?= $si['label'] ?></span>
          </div>
          <?php if ($r['guest_name']): ?>
          <div class="guest">👤 <?= htmlspecialchars($r['guest_name']) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>

    </div>
  </div>
</div>

<!-- Room Detail / Status Update Modal -->
<div class="modal-backdrop" id="roomModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modalRoomTitle">Room</div>
      <button class="modal-close" onclick="document.getElementById('roomModal').classList.remove('show')">×</button>
    </div>
    <form method="POST" id="roomForm">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="room_id" id="modalRoomId">
      <div class="modal-body">
        <div style="margin-bottom:16px">
          <div style="font-size:12px;color:var(--text-dim)">Room Type</div>
          <div id="modalRoomType" style="font-size:14px;color:var(--text-primary);margin-top:2px"></div>
        </div>
        <div style="margin-bottom:16px">
          <div style="font-size:12px;color:var(--text-dim)">Current Status</div>
          <div id="modalRoomStatus" style="font-size:14px;font-weight:600;margin-top:2px"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Update Status</label>
          <select name="status" id="modalStatusSelect" class="form-control">
            <option value="Ready">Ready</option>
            <option value="Occupied">Occupied</option>
            <option value="Dirty">Dirty</option>
            <option value="InCleaning">In Cleaning</option>
            <option value="Inspecting">Inspecting</option>
            <option value="OutOfOrder">Out of Order</option>
            <option value="Clean">Clean</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('roomModal').classList.remove('show')">Close</button>
        <?php if (Auth::can(['Manager','Admin','Receptionist'])): ?>
        <button type="submit" class="btn btn-gold">Update Status</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Add Room Modal -->
<div class="modal-backdrop" id="addRoomModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Add New Room</div>
      <button class="modal-close" onclick="document.getElementById('addRoomModal').classList.remove('show')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_room">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Room Number</label><input type="text" name="room_number" class="form-control" placeholder="e.g. 305" required></div>
        <div class="form-group"><label class="form-label">Floor</label><input type="number" name="floor" class="form-control" min="1" max="50" required></div>
        <div class="form-group"><label class="form-label">Room Type</label>
          <select name="type_id" class="form-control" required>
            <?php foreach ($room_types as $rt): ?>
            <option value="<?= $rt['type_id'] ?>"><?= htmlspecialchars($rt['name']) ?> — EGP <?= number_format($rt['base_price']) ?>/night</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addRoomModal').classList.remove('show')">Cancel</button>
        <button type="submit" class="btn btn-gold">Add Room</button>
      </div>
    </form>
  </div>
</div>

<div class="toast-container" id="toasts"></div>
<script>
function openRoomModal(id, number, status, type) {
  document.getElementById('modalRoomId').value = id;
  document.getElementById('modalRoomTitle').textContent = 'Room ' + number;
  document.getElementById('modalRoomType').textContent = type;
  document.getElementById('modalRoomStatus').textContent = status;
  document.getElementById('modalStatusSelect').value = status;
  document.getElementById('roomModal').classList.add('show');
}
const p=new URLSearchParams(location.search);
if(p.get('success')){const el=document.createElement('div');el.className='toast success';el.innerHTML='<span class="toast-icon">✓</span><span class="toast-msg">'+p.get('success')+'</span>';document.getElementById('toasts').appendChild(el);setTimeout(()=>el.remove(),4000)}
</script>
</body></html>
