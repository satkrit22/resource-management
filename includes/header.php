<header class="top-header">
    <div class="header-left">
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="breadcrumb">
            <i class="fas fa-home"></i>
            <span><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></span>
        </div>
    </div>
    
    <div class="header-right">
        <!-- Notifications -->
        <div class="header-icon" id="notificationBtn">
            <i class="fas fa-bell"></i>
            <span class="notification-badge">3</span>
        </div>
        
        <!-- User Menu -->
        <div class="header-user">
            <div class="user-avatar-sm">
                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
            </div>
            <span class="user-name-sm"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        </div>
    </div>
</header>

<script>
// Toggle sidebar on mobile
document.getElementById('menuToggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
});

// Notification dropdown placeholder
document.getElementById('notificationBtn')?.addEventListener('click', function() {
    showToast('No new notifications', 'info');
});
</script>
