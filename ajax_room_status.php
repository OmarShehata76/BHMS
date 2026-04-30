<?php
// ajax_room_status.php — AJAX endpoint for real-time room status update
// Required by CS251 Technical Spec: "AJAX Calls (in one scenario)"
require_once 'includes/auth.php';
Auth::check();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$db      = db();
$room_id = (int)($_POST['room_id'] ?? 0);
$status  = $_POST['status'] ?? '';

$allowed_statuses = ['Ready','Dirty','InCleaning','Inspecting','OutOfOrder','Occupied'];

if (!$room_id || !in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid room ID or status']);
    exit;
}

// Check room exists
$room = $db->fetchOne("SELECT room_id, room_number, status FROM rooms WHERE room_id=?", 'i', $room_id);
if (!$room) {
    echo json_encode(['success' => false, 'message' => 'Room not found']);
    exit;
}

$old_status = $room['status'];

// Update room status
$affected = $db->execute(
    "UPDATE rooms SET status=?, updated_at=NOW() WHERE room_id=?",
    'si', $status, $room_id
);

if ($affected > 0) {
    // Log to audit trail
    AuditLogger::log('AJAX_ROOM_STATUS_UPDATE', 'rooms', $room_id, $old_status, $status);

    // If room set to Dirty — auto-create HK task
    if ($status === 'Dirty') {
        $db->insert(
            "INSERT INTO hk_tasks (room_id, type, status, priority) VALUES (?, 'Cleaning', 'Pending', 3)",
            'i', $room_id
        );
    }

    // Return updated room info + count of pending HK tasks
    $pending_hk = $db->fetchOne("SELECT COUNT(*) c FROM hk_tasks WHERE status='Pending'")['c'] ?? 0;
    $dirty_rooms = $db->fetchOne("SELECT COUNT(*) c FROM rooms WHERE status='Dirty'")['c'] ?? 0;

    echo json_encode([
        'success'     => true,
        'message'     => "Room {$room['room_number']} updated to {$status}",
        'room_id'     => $room_id,
        'room_number' => $room['room_number'],
        'old_status'  => $old_status,
        'new_status'  => $status,
        'pending_hk'  => $pending_hk,
        'dirty_rooms' => $dirty_rooms,
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
