/**
 * BHMS — AJAX Room Status Updater
 * CS251 Technical Requirement: "AJAX Calls (in one scenario)"
 * Scenario: Real-time room status update from housekeeping floor map
 */

const RoomStatusAJAX = {

    updateStatus: function(roomId, newStatus, callback) {
        const btn = document.querySelector(`[data-room-id="${roomId}"]`);
        if (btn) { btn.disabled = true; btn.textContent = 'Updating...'; }

        const formData = new FormData();
        formData.append('room_id', roomId);
        formData.append('status',  newStatus);

        fetch('ajax_room_status.php', { method: 'POST', body: formData })
        .then(r => { if (!r.ok) throw new Error('Network error'); return r.json(); })
        .then(data => {
            console.log('AJAX Response:', data);
            if (data.success) {
                RoomStatusAJAX.updateRoomCell(roomId, newStatus);
                RoomStatusAJAX.updateBadges(data.pending_hk, data.dirty_rooms);
                showToast(data.message, 'success');
                if (typeof callback === 'function') callback(data);
            } else {
                showToast(data.message || 'Update failed', 'error');
            }
        })
        .catch(err => { showToast('Connection error', 'error'); console.error(err); })
        .finally(() => { if (btn) { btn.disabled = false; btn.textContent = newStatus; } });
    },

    updateRoomCell: function(roomId, status) {
        const cell = document.querySelector(`.room-cell[data-room-id="${roomId}"]`);
        if (!cell) return;
        cell.classList.remove('status-ready','status-occupied','status-dirty','status-cleaning','status-ooo');
        const map = { Ready:'status-ready', Occupied:'status-occupied', Dirty:'status-dirty', InCleaning:'status-cleaning', Inspecting:'status-cleaning', OutOfOrder:'status-ooo' };
        if (map[status]) cell.classList.add(map[status]);
        const lbl = cell.querySelector('.room-status-label');
        if (lbl) lbl.textContent = status;
    },

    updateBadges: function(pendingHK, dirtyRooms) {
        const hk = document.getElementById('badge-hk');
        const dr = document.getElementById('badge-dirty');
        if (hk) hk.textContent = pendingHK;
        if (dr) dr.textContent = dirtyRooms;
    },

    bindSelectDropdowns: function() {
        document.querySelectorAll('.ajax-status-select').forEach(sel => {
            sel.addEventListener('change', function() {
                if (this.dataset.roomId && this.value)
                    RoomStatusAJAX.updateStatus(this.dataset.roomId, this.value);
            });
        });
    },

    bindQuickButtons: function() {
        document.querySelectorAll('.ajax-status-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.dataset.roomId && this.dataset.status)
                    RoomStatusAJAX.updateStatus(this.dataset.roomId, this.dataset.status);
            });
        });
    },

    init: function() { this.bindSelectDropdowns(); this.bindQuickButtons(); }
};

function showToast(message, type = 'info') {
    const icons = { success: '✓', error: '✕', info: 'i' };
    const container = document.getElementById('toasts');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<span class="toast-icon">' + (icons[type]||'i') + '</span><span class="toast-msg">' + message + '</span>';
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

document.addEventListener('DOMContentLoaded', () => RoomStatusAJAX.init());
