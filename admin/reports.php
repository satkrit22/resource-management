<?php
require_once '../config/auth.php';
requireLogin();
requireAdmin();

$db = getDBConnection();

// Get date range from query params or default to last 30 days
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Booking statistics
$stmt = $db->prepare("SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM bookings 
    WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$bookingStats = $stmt->fetch();

// Resource utilization
$stmt = $db->prepare("SELECT 
    r.name,
    r.category,
    COUNT(b.id) as booking_count,
    SUM(CASE WHEN b.status = 'approved' THEN 
        TIMESTAMPDIFF(HOUR, b.start_datetime, b.end_datetime) 
    ELSE 0 END) as total_hours
    FROM resources r
    LEFT JOIN bookings b ON r.id = b.resource_id 
        AND DATE(b.start_datetime) BETWEEN ? AND ?
    GROUP BY r.id
    ORDER BY booking_count DESC");
$stmt->execute([$startDate, $endDate]);
$resourceUtilization = $stmt->fetchAll();

// Daily booking trends
$stmt = $db->prepare("SELECT 
    DATE(created_at) as date,
    COUNT(*) as count
    FROM bookings
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC");
$stmt->execute([$startDate, $endDate]);
$dailyTrends = $stmt->fetchAll();

// Category distribution
$stmt = $db->prepare("SELECT 
    r.category,
    COUNT(b.id) as booking_count
    FROM bookings b
    JOIN resources r ON b.resource_id = r.id
    WHERE DATE(b.start_datetime) BETWEEN ? AND ?
    GROUP BY r.category
    ORDER BY booking_count DESC");
$stmt->execute([$startDate, $endDate]);
$categoryDistribution = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Resource Management System</title>
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
                        <h1 class="page-title">Reports & Analytics</h1>
                        <p class="page-subtitle">System usage statistics and insights</p>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-secondary" onclick="exportReport()">
                            <i class="fas fa-download"></i>
                            Export PDF
                        </button>
                        <button class="btn btn-secondary" onclick="exportCSV()">
                            <i class="fas fa-file-csv"></i>
                            Export CSV
                        </button>
                    </div>
                </div>
                
                <!-- Date Range Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="filters-grid">
                            <div class="form-group">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i>
                                    Apply Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Booking Statistics -->
                <div class="stats-grid-4">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $bookingStats['total_bookings']; ?></div>
                            <div class="stat-label">Total Bookings</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $bookingStats['approved']; ?></div>
                            <div class="stat-label">Approved</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $bookingStats['pending']; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $bookingStats['rejected'] + $bookingStats['cancelled']; ?></div>
                            <div class="stat-label">Rejected/Cancelled</div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="grid-2">
                    <!-- Daily Trends -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Booking Trends</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="trendsChart" height="250"></canvas>
                        </div>
                    </div>
                    
                    <!-- Category Distribution -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Category Distribution</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Resource Utilization -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Resource Utilization</h3>
                    </div>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Resource Name</th>
                                    <th>Category</th>
                                    <th>Total Bookings</th>
                                    <th>Total Hours</th>
                                    <th>Utilization</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($resourceUtilization)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted p-4">No data available</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($resourceUtilization as $resource): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($resource['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($resource['category']); ?></td>
                                    <td><?php echo $resource['booking_count']; ?></td>
                                    <td><?php echo number_format($resource['total_hours'], 1); ?> hrs</td>
                                    <td>
                                        <?php 
                                        $maxHours = 168; // Hours in a week
                                        $utilization = min(100, ($resource['total_hours'] / $maxHours) * 100);
                                        ?>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $utilization; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo number_format($utilization, 1); ?>%</small>
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
        // Daily Trends Chart
        const trendsCtx = document.getElementById('trendsChart');
        if (trendsCtx) {
            new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($dailyTrends, 'date')); ?>,
                    datasets: [{
                        label: 'Bookings',
                        data: <?php echo json_encode(array_column($dailyTrends, 'count')); ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
        
        // Category Distribution Chart
        const categoryCtx = document.getElementById('categoryChart');
        if (categoryCtx) {
            new Chart(categoryCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($categoryDistribution, 'category')); ?>,
                    datasets: [{
                        label: 'Bookings',
                        data: <?php echo json_encode(array_column($categoryDistribution, 'booking_count')); ?>,
                        backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
        
        function exportReport() {
            showToast('PDF export functionality coming soon', 'info');
        }
        
        function exportCSV() {
            window.location.href = '../api/export.php?type=csv&start=<?php echo $startDate; ?>&end=<?php echo $endDate; ?>';
        }
    </script>
</body>
</html>
