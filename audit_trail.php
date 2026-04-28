<?php
// audit_trail.php — Immutable System Audit Log
require_once 'includes/auth.php';
Auth::requireRole(['Manager','Admin','Accountant','NightAuditor']);
$db = db();

$search = trim($_GET['q'] ?? '');
$from   = $_GET['from'] ?? date('Y-m-d');
$to     = $_GET['to']   ?? date('Y-m-d');
$action_filter = $_GET['action'] ?? '';

$where  = "WHERE al.timestamp BETWEEN ? AND DATE_ADD(?,INTERVAL 1 DAY)";
$params = [$from, $to];
$types  = 'ss';

if ($search) {
    $where .= " AND (al.action LIKE ? OR al.table_name LIKE ? OR s.name LIKE ?)";
    $like   = "%{$search}%";
    $params = array_merge($params, [$like,$like,$like]);
    $types .= 'sss';
}
if ($action_filter) {
    $where .= " AND al.action LIKE ?";
    $params[] = "%{$action_filter}%";
    $types .= 's';
}

$logs = $db->fetchAll("
    SELECT al.log_id, al.action, al.table_name, al.record_id,
           al.old_value, al.new_value, al.ip_address, al.timestamp,
           s.name staff_name, s.role staff_role
    FROM audit_logs al
    LEFT JOIN staff s ON al.staff_id=s.staff_id
    {$where}
    ORDER BY al.timestamp DESC LIMIT 200",
    $types, ...$params
);

$total = $db->fetchOne("SELECT COUNT(*) c FROM audit_logs WHERE timestamp BETWEEN ? AND DATE_ADD(?,INTERVAL 1 DAY)", 'ss', $from, $to)['c'] ?? 0;

// Distinct action types for filter
$action_types = $db->fetchAll("SELECT DISTINCT action FROM audit_logs ORDER BY action");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>BHMS — Audit Trail</title>
<link rel="stylesheet" href="css/style.css">
<style>
.log-action{font-family:monospace;font-size:11px;background:var(--bg-panel);border:1px solid var(--border);border-radius:3px;padding:2px 7px;color:var(--blue)}
.log-table{font-size:11px;color:var(--text-dim)}
.immutable-banner{background:rgba(91,156,246,.06);border:1px solid rgba(91,156,246,.2);border-radius:var(--radius-sm);padding:12px 16px;color:var(--blue);font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px}
pre.json-val{font-family:monospace;font-size:11px;color:var(--text-muted);white-space:pre-wrap;word-break:break-all;max-width:200px;max-height:60px;overflow:hidden}
</style>
</head>
<body>
<div class="wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-title">📜 Audit Trail</div>
      <div class="topbar-actions">
        <button onclick="window.print()" class="btn btn-outline btn-sm">🖨 Export</button>
      </div>
    </div>
    <div class="content">

      <div class="immutable-banner">
        🔒 <strong>Read-Only Log</strong> — All entries are immutable. No log can be modified or deleted by any user.
      </div>

      <!-- Filters -->
      <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:flex-end">
        <div class="search-box" style="flex:1;min-width:180px">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" class="form-control" placeholder="Search action, table, staff..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div style="display:flex;align-items:center;gap:6px">
          <input type="date" name="from" class="form-control" value="<?= $from ?>" style="width:auto">
          <span style="color:var(--text-dim)">→</span>
          <input type="date" name="to" class="form-control" value="<?= $to ?>" style="width:auto">
        </div>
        <select name="action" class="form-control" style="width:auto">
          <option value="">All Actions</option>
          <?php foreach ($action_types as $at): ?>
          <option value="<?= $at['action'] ?>" <?= $action_filter===$at['action']?'selected':'' ?>><?= $at['action'] ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline">Filter</button>
        <a href="audit_trail.php" class="btn btn-ghost">Clear</a>
      </form>

      <!-- Stats -->
      <div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap">
        <div style="padding:10px 18px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm);font-size:13px">
          <span style="color:var(--text-dim)">Total today:</span>
          <span style="font-family:'Cormorant Garamond',serif;font-size:20px;margin-left:8px;color:var(--text-primary)"><?= $total ?></span>
        </div>
        <div style="padding:10px 18px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm);font-size:13px">
          <span style="color:var(--text-dim)">Showing:</span>
          <span style="font-family:'Cormorant Garamond',serif;font-size:20px;margin-left:8px;color:var(--text-primary)"><?= count($logs) ?></span>
        </div>
      </div>

      <!-- Log Table -->
      <div class="card">
        <div class="card-header"><div class="card-title">Audit Log Entries</div></div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Timestamp</th>
                <th>Staff</th>
                <th>Action</th>
                <th>Table</th>
                <th>Record ID</th>
                <th>Old Value</th>
                <th>New Value</th>
                <th>IP</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($logs as $log): ?>
              <tr>
                <td style="color:var(--text-dim);font-size:11px"><?= $log['log_id'] ?></td>
                <td style="white-space:nowrap;font-size:12px;color:var(--text-muted)">
                  <?= date('d M Y', strtotime($log['timestamp'])) ?><br>
                  <span style="color:var(--text-dim)"><?= date('H:i:s', strtotime($log['timestamp'])) ?></span>
                </td>
                <td>
                  <div style="font-size:13px"><?= htmlspecialchars($log['staff_name'] ?? 'System') ?></div>
                  <div style="font-size:11px;color:var(--text-dim)"><?= htmlspecialchars($log['staff_role'] ?? '') ?></div>
                </td>
                <td><span class="log-action"><?= htmlspecialchars($log['action']) ?></span></td>
                <td><span class="log-table"><?= htmlspecialchars($log['table_name'] ?? '—') ?></span></td>
                <td style="text-align:center;color:var(--text-dim)"><?= $log['record_id'] ?? '—' ?></td>
                <td>
                  <?php if ($log['old_value']): ?>
                  <pre class="json-val"><?= htmlspecialchars($log['old_value']) ?></pre>
                  <?php else: ?><span style="color:var(--text-dim);font-size:11px">—</span><?php endif; ?>
                </td>
                <td>
                  <?php if ($log['new_value']): ?>
                  <pre class="json-val"><?= htmlspecialchars($log['new_value']) ?></pre>
                  <?php else: ?><span style="color:var(--text-dim);font-size:11px">—</span><?php endif; ?>
                </td>
                <td style="font-size:11px;color:var(--text-dim)"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($logs)): ?>
              <tr><td colspan="9" style="text-align:center;color:var(--text-dim);padding:40px">No log entries found for the selected period</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>
</body></html>
