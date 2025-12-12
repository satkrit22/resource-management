<?php
require_once 'config/auth.php';
requireLogin();

$db = getDBConnection();

// Get stats
$stats = [];

$stmt = $db->query("SELECT COUNT(*) as total FROM resources");
$stats['total_resources'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM resources WHERE status = 'available'");
$stats['available_resources'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'pending'");
$stats['pending_approvals'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE DATE(start_datetime) = CURDATE() AND status IN ('pending', 'approved')");
$stats['today_bookings'] = $stmt->fetch()['total'];

// Get recent bookings
$stmt = $db->prepare("SELECT b.*, r.name as resource_name, u.full_name as user_name 
                      FROM bookings b 
                      JOIN resources r ON b.resource_id = r.id 
                      JOIN users u ON b.user_id = u.id 
                      ORDER BY b.created_at DESC LIMIT 5");
$stmt->execute();
$recentBookings = $stmt->fetchAll();

// Get notifications count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmt->execute([$_SESSION['user_id']]);
$unreadNotifications = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Resource Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
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
                    <a href="dashboard.php" class="nav-link active">
                        <i class="fas fa-th-large"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="resources.php" class="nav-link">
                        <i class="fas fa-box"></i>
                        <span>Resources</span>
                    </a>
                    <a href="bookings.php" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Bookings</span>
                    </a>
                    <a href="calendar.php" class="nav-link">
                        <i class="fas fa-calendar-week"></i>
                        <span>Calendar</span>
                    </a>
                </div>
                
                <?php if (isAdmin()): ?>
                <div class="nav-section">
                    <div class="nav-section-title">Administration</div>
                    <a href="approvals.php" class="nav-link">
                        <i class="fas fa-clipboard-check"></i>
                        <span>Approvals</span>
                        <?php if ($stats['pending_approvals'] > 0): ?>
                        <span class="badge"><?php echo $stats['pending_approvals']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="reports.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                    <a href="settings.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
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
                        <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                        <div class="user-role"><?php echo ucfirst($_SESSION['role']); ?></div>
                    </div>
                    <a href="logout.php" class="btn btn-ghost btn-icon" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="breadcrumb">
                        <span class="current">Dashboard</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search resources...">
                    </div>
                    
                    <div class="dropdown" id="notificationDropdown">
                        <button class="header-btn" onclick="toggleDropdown('notificationDropdown')">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadNotifications > 0): ?>
                            <span class="notification-dot"></span>
                            <?php endif; ?>
                        </button>
                        <div class="notification-dropdown">
                            <div class="notification-header">
                                <h4>Notifications</h4>
                                <a href="#" onclick="markAllRead()">Mark all read</a>
                            </div>
                            <div class="notification-list" id="notificationList">
                                <!-- Loaded via JavaScript -->
                            </div>
                            <div class="notification-footer">
                                <a href="notifications.php">View all notifications</a>
                            </div>
                        </div>
                    </div>
                    
                    <a href="book-resource.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        <span>New Booking</span>
                    </a>
                </div>
            </header>
            
            <!-- Page Content -->
            <div class="page-content">
                <div class="page-header">
                    <h1 class="page-title">Welcome back, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>!</h1>
                    <p class="page-subtitle">Here's what's happening with your resources today.</p>
                </div>
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $stats['total_resources']; ?></div>
                            <div class="stat-label">Total Resources</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $stats['available_resources']; ?></div>
                            <div class="stat-label">Available Now</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $stats['pending_approvals']; ?></div>
                            <div class="stat-label">Pending Approvals</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $stats['today_bookings']; ?></div>
                            <div class="stat-label">Today's Bookings</div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Grid -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
                    <!-- Recent Bookings -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Bookings</h3>
                            <a href="bookings.php" class="btn btn-secondary btn-sm">View All</a>
                        </div>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Resource</th>
                                        <th>Booked By</th>
                                        <th>Date & Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentBookings)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted p-4">No bookings found</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($recentBookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['resource_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($booking['title']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($booking['start_datetime'])); ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('h:i A', strtotime($booking['start_datetime'])); ?> - 
                                                <?php echo date('h:i A', strtotime($booking['end_datetime'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $booking['status']; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">Quick Actions</h3>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-column gap-2">
                                    <a href="book-resource.php" class="btn btn-primary w-100">
                                        <i class="fas fa-plus"></i>
                                        Book a Resource
                                    </a>
                                    <a href="resources.php" class="btn btn-secondary w-100">
                                        <i class="fas fa-search"></i>
                                        Browse Resources
                                    </a>
                                    <a href="my-bookings.php" class="btn btn-secondary w-100">
                                        <i class="fas fa-list"></i>
                                        My Bookings
                                    </a>
                                    <?php if (isAdmin()): ?>
                                    <a href="add-resource.php" class="btn btn-secondary w-100">
                                        <i class="fas fa-box"></i>
                                        Add New Resource
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Today's Schedule -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Today's Schedule</h3>
                            </div>
                            <div class="card-body" id="todaySchedule">
                                <!-- Loaded via JavaScript -->
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-calendar-day"></i>
                                    </div>
                                    <div class="empty-state-title">No bookings today</div>
                                    <div class="empty-state-text">Your schedule is clear!</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="assets/js/app.js"></script>
    <script>
        // Load notifications
        loadNotifications();
        
        // Load today's schedule
        loadTodaySchedule();
        
        function loadNotifications() {
            fetch('api/notifications.php?unread=1')
                .then(response => response.json())
                .then(data => {
                    const list = document.getElementById('notificationList');
                    if (data.data && data.data.length > 0) {
                        list.innerHTML = data.data.slice(0, 5).map(n => `
                            <div class="notification-item ${n.is_read ? '' : 'unread'}">
                                <div class="notification-icon ${getNotificationIconClass(n.type)}">
                                    <i class="fas ${getNotificationIcon(n.type)}"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">${n.title}</div>
                                    <div class="notification-text">${n.message}</div>
                                    <div class="notification-time">${timeAgo(n.created_at)}</div>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        list.innerHTML = '<div class="p-4 text-center text-muted">No new notifications</div>';
                    }
                });
        }
        
        function loadTodaySchedule() {
            const today = new Date().toISOString().split('T')[0];
            fetch(`api/bookings.php?action=calendar&start=${today}&end=${today}T23:59:59`)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('todaySchedule');
                    if (data && data.length > 0) {
                        container.innerHTML = data.slice(0, 4).map(b => `
                            <div class="d-flex align-items-center gap-3 mb-3 p-2" style="background: var(--gray-50); border-radius: var(--border-radius);">
                                <div style="width: 4px; height: 40px; background: ${b.color}; border-radius: 2px;"></div>
                                <div style="flex: 1; min-width: 0;">
                                    <div class="fw-medium truncate">${b.title}</div>
                                    <div class="text-muted" style="font-size: 0.8125rem;">
                                        ${formatTime(b.start)} - ${formatTime(b.end)}
                                    </div>
                                </div>
                            </div>
                        `).join('');
                    }
                });
        }
        
        function getNotificationIcon(type) {
            const icons = {
                'booking': 'fa-calendar-plus',
                'approval': 'fa-check-circle',
                'rejection': 'fa-times-circle',
                'reminder': 'fa-bell',
                'system': 'fa-info-circle'
            };
            return icons[type] || 'fa-info-circle';
        }
        
        function getNotificationIconClass(type) {
            const classes = {
                'booking': 'badge-info',
                'approval': 'badge-success',
                'rejection': 'badge-danger',
                'reminder': 'badge-warning',
                'system': 'badge-secondary'
            };
            return classes[type] || 'badge-secondary';
        }
        
        function formatTime(datetime) {
            return new Date(datetime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        }
        
        function timeAgo(datetime) {
            const seconds = Math.floor((new Date() - new Date(datetime)) / 1000);
            const intervals = { year: 31536000, month: 2592000, week: 604800, day: 86400, hour: 3600, minute: 60 };
            for (const [unit, value] of Object.entries(intervals)) {
                const interval = Math.floor(seconds / value);
                if (interval >= 1) return `${interval} ${unit}${interval > 1 ? 's' : ''} ago`;
            }
            return 'Just now';
        }
    </script>
</body>
</html>
