<?php
require_once '../config/auth.php';
requireLogin();
requireAdmin();

$db = getDBConnection();

// Get comprehensive stats for admin
$stats = [];

// Resources stats
$stmt = $db->query("SELECT COUNT(*) as total FROM resources");
$stats['total_resources'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM resources WHERE status = 'available'");
$stats['available_resources'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM resources WHERE status = 'booked'");
$stats['booked_resources'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM resources WHERE status = 'maintenance'");
$stats['maintenance_resources'] = $stmt->fetch()['total'];

// Bookings stats
$stmt = $db->query("SELECT COUNT(*) as total FROM bookings");
$stats['total_bookings'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'pending'");
$stats['pending_bookings'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'approved'");
$stats['approved_bookings'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE DATE(start_datetime) = CURDATE()");
$stats['today_bookings'] = $stmt->fetch()['total'];

// Users stats
$stmt = $db->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
$stats['admin_users'] = $stmt->fetch()['total'];

// Recent activity
$stmt = $db->prepare("SELECT b.*, r.name as resource_name, r.category, u.full_name as user_name 
                      FROM bookings b 
                      JOIN resources r ON b.resource_id = r.id 
                      JOIN users u ON b.user_id = u.id 
                      ORDER BY b.created_at DESC LIMIT 10");
$stmt->execute();
$recentActivity = $stmt->fetchAll();

// Popular resources
$stmt = $db->query("SELECT r.name, r.category, COUNT(b.id) as booking_count 
                    FROM resources r 
                    LEFT JOIN bookings b ON r.id = b.resource_id 
                    GROUP BY r.id 
                    ORDER BY booking_count DESC 
                    LIMIT 5");
$popularResources = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Resource Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-wrapper">
        <?php include '../includes/header.php'; ?>
        
        <main class="main-content">
            <div class="page-content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Admin Dashboard</h1>
                        <p class="page-subtitle">Complete system overview and management</p>
                    </div>
                    <div class="page-actions">
                        <a href="reports.php" class="btn btn-secondary">
                            <i class="fas fa-download"></i>
                            Export Report
                        </a>
                        <a href="../add-resource.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Add Resource
                        </a>
                    </div>
                </div>
                
                <!-- Overview Stats -->
                <div class="stats-grid-4">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $stats['total_resources']; ?></div>
                            <div class="stat-label">Total Resources</div>
                        </div>
                        <div class="stat-footer">
                            <span class="text-success"><i class="fas fa-check"></i> <?php echo $stats['available_resources']; ?> available</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $stats['pending_bookings']; ?></div>
                            <div class="stat-label">Pending Approvals</div>
                        </div>
                        <div class="stat-footer">
                            <a href="approvals.php" class="stat-link">Review now <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $stats['total_bookings']; ?></div>
                            <div class="stat-label">Total Bookings</div>
                        </div>
                        <div class="stat-footer">
                            <span class="text-muted"><i class="fas fa-check-circle"></i> <?php echo $stats['approved_bookings']; ?> approved</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="stat-footer">
                            <span class="text-muted"><i class="fas fa-user-shield"></i> <?php echo $stats['admin_users']; ?> admins</span>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="grid-2">
                    <!-- Resource Status Chart -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Resource Status</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="resourceStatusChart" height="250"></canvas>
                        </div>
                    </div>
                    
                    <!-- Popular Resources -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Most Booked Resources</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($popularResources)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <div class="empty-state-text">No booking data yet</div>
                            </div>
                            <?php else: ?>
                            <div class="resource-list">
                                <?php foreach ($popularResources as $resource): ?>
                                <div class="resource-item">
                                    <div class="resource-info">
                                        <div class="resource-name"><?php echo htmlspecialchars($resource['name']); ?></div>
                                        <div class="resource-category"><?php echo htmlspecialchars($resource['category']); ?></div>
                                    </div>
                                    <div class="resource-count">
                                        <span class="badge badge-primary"><?php echo $resource['booking_count']; ?> bookings</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activity</h3>
                        <a href="../bookings.php" class="btn btn-secondary btn-sm">View All</a>
                    </div>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Resource</th>
                                    <th>User</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentActivity)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted p-4">No activity yet</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($recentActivity as $booking): ?>
                                <tr>
                                    <td>#<?php echo $booking['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($booking['resource_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($booking['category']); ?></small>
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
                                    <td>
                                        <?php if ($booking['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-success" onclick="approveBooking(<?php echo $booking['id']; ?>)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="rejectBooking(<?php echo $booking['id']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" onclick="viewBooking(<?php echo $booking['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        // Resource Status Chart
        const ctx = document.getElementById('resourceStatusChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Available', 'Booked', 'Maintenance'],
                    datasets: [{
                        data: [
                            <?php echo $stats['available_resources']; ?>,
                            <?php echo $stats['booked_resources']; ?>,
                            <?php echo $stats['maintenance_resources']; ?>
                        ],
                        backgroundColor: ['#10b981', '#3b82f6', '#f59e0b'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        function approveBooking(id) {
            if (confirm('Approve this booking?')) {
                fetch('../api/bookings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'approve', booking_id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Booking approved successfully', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.message || 'Failed to approve booking', 'error');
                    }
                });
            }
        }
        
        function rejectBooking(id) {
            if (confirm('Reject this booking?')) {
                fetch('../api/bookings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reject', booking_id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Booking rejected', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.message || 'Failed to reject booking', 'error');
                    }
                });
            }
        }
        
        function viewBooking(id) {
            window.location.href = `../bookings.php?id=${id}`;
        }
    </script>
</body>
</html>
