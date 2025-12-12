<?php
// Get pending approvals count for badge
$db = getDBConnection();
$stmt = $db->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'");
$pendingCount = $stmt->fetch()['count'];

// Get current page
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-cubes"></i>
        </div>
        <span class="sidebar-title">ResourceHub</span>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Main Menu</div>
            <a href="dashboard.php" class="nav-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="resources.php" class="nav-link <?php echo $currentPage === 'resources.php' ? 'active' : ''; ?>">
                <i class="fas fa-box"></i>
                <span>Resources</span>
            </a>
            <a href="bookings.php" class="nav-link <?php echo $currentPage === 'bookings.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Bookings</span>
            </a>
            <a href="calendar.php" class="nav-link <?php echo $currentPage === 'calendar.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-week"></i>
                <span>Calendar</span>
            </a>
            <a href="my-bookings.php" class="nav-link <?php echo $currentPage === 'my-bookings.php' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                <span>My Bookings</span>
            </a>
        </div>
        
        <?php if (isAdmin()): ?>
        <div class="nav-section">
            <div class="nav-section-title">Administration</div>
            <a href="approvals.php" class="nav-link <?php echo $currentPage === 'approvals.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-check"></i>
                <span>Approvals</span>
                <?php if ($pendingCount > 0): ?>
                <span class="badge"><?php echo $pendingCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="add-resource.php" class="nav-link <?php echo $currentPage === 'add-resource.php' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Add Resource</span>
            </a>
            <a href="reports.php" class="nav-link <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="users.php" class="nav-link <?php echo $currentPage === 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                <div class="user-role"><?php echo ucfirst($_SESSION['role']); ?></div>
            </div>
            <a href="logout.php" class="btn btn-ghost btn-icon" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>
