<?php
// includes/sidebar.php
$current = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <span class="brand">BHMS</span>
    <span class="sub">Boutique Hotel Suite</span>
  </div>

  <nav class="sidebar-nav">

    <div class="nav-section-title">Front Desk</div>
    <a href="dashboard.php" class="nav-item <?= $current==='dashboard'?'active':'' ?>">
      <span class="icon">⊞</span> Dashboard
    </a>
    <a href="reservations.php" class="nav-item <?= $current==='reservations'?'active':'' ?>">
      <span class="icon">📋</span> Reservations
      <span class="nav-badge amber" id="badge-res">0</span>
    </a>
    <a href="checkin.php" class="nav-item <?= $current==='checkin'?'active':'' ?>">
      <span class="icon">🔑</span> Check-In / Out
    </a>
    <a href="rooms.php" class="nav-item <?= $current==='rooms'?'active':'' ?>">
      <span class="icon">🏨</span> Room Status
    </a>

    <div class="nav-section-title">Guests</div>
    <a href="guests.php" class="nav-item <?= $current==='guests'?'active':'' ?>">
      <span class="icon">👤</span> Guest Profiles
    </a>
    <a href="preferences.php" class="nav-item <?= $current==='preferences'?'active':'' ?>">
      <span class="icon">⭐</span> Preferences
    </a>
    <a href="feedback.php" class="nav-item <?= $current==='feedback'?'active':'' ?>">
      <span class="icon">💬</span> Feedback
    </a>

    <div class="nav-section-title">Operations</div>
    <a href="housekeeping.php" class="nav-item <?= $current==='housekeeping'?'active':'' ?>">
      <span class="icon">🧹</span> Housekeeping
      <span class="nav-badge" id="badge-hk">0</span>
    </a>
    <a href="maintenance.php" class="nav-item <?= $current==='maintenance'?'active':'' ?>">
      <span class="icon">🔧</span> Maintenance
    </a>
    <a href="inventory.php" class="nav-item <?= $current==='inventory'?'active':'' ?>">
      <span class="icon">📦</span> Inventory
    </a>

    <div class="nav-section-title">Billing</div>
    <a href="folios.php" class="nav-item <?= $current==='folios'?'active':'' ?>">
      <span class="icon">💳</span> Folios & Billing
    </a>
    <a href="services.php" class="nav-item <?= $current==='services'?'active':'' ?>">
      <span class="icon">🛎</span> Services
    </a>
    <a href="reports.php" class="nav-item <?= $current==='reports'?'active':'' ?>">
      <span class="icon">📊</span> Reports
    </a>

    <div class="nav-section-title">System</div>
    <a href="night_audit.php" class="nav-item <?= $current==='night_audit'?'active':'' ?>">
      <span class="icon">🌙</span> Night Audit
    </a>
    <a href="staff.php" class="nav-item <?= $current==='staff'?'active':'' ?>">
      <span class="icon">👥</span> Staff & Roles
    </a>
    <a href="audit_trail.php" class="nav-item <?= $current==='audit_trail'?'active':'' ?>">
      <span class="icon">📜</span> Audit Trail
    </a>

  </nav>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar">
        <?= strtoupper(substr($_SESSION['staff_name'] ?? 'A', 0, 1)) ?>
      </div>
      <div class="user-info">
        <div class="name"><?= htmlspecialchars($_SESSION['staff_name'] ?? 'Admin') ?></div>
        <div class="role"><?= htmlspecialchars($_SESSION['staff_role'] ?? 'Manager') ?></div>
      </div>
    </div>
  </div>
</aside>


