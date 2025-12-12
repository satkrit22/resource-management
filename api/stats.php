<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

requireLogin();

$db = getDBConnection();
$action = $_GET['action'] ?? 'dashboard';

switch ($action) {
    case 'dashboard':
        getDashboardStats();
        break;
    case 'usage':
        getUsageStats();
        break;
    case 'category-usage':
        getCategoryUsage();
        break;
    case 'monthly-bookings':
        getMonthlyBookings();
        break;
    case 'top-resources':
        getTopResources();
        break;
    case 'recent-activity':
        getRecentActivity();
        break;
    default:
        getDashboardStats();
}

function getDashboardStats() {
    global $db;
    
    // Total resources
    $stmt = $db->query("SELECT COUNT(*) as total FROM resources");
    $totalResources = $stmt->fetch()['total'];
    
    // Available resources
    $stmt = $db->query("SELECT COUNT(*) as total FROM resources WHERE status = 'available'");
    $availableResources = $stmt->fetch()['total'];
    
    // Total bookings
    $stmt = $db->query("SELECT COUNT(*) as total FROM bookings");
    $totalBookings = $stmt->fetch()['total'];
    
    // Pending approvals
    $stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'pending'");
    $pendingApprovals = $stmt->fetch()['total'];
    
    // Active bookings (approved and ongoing)
    $stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'approved' AND start_datetime <= NOW() AND end_datetime >= NOW()");
    $activeBookings = $stmt->fetch()['total'];
    
    // Today's bookings
    $stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE DATE(start_datetime) = CURDATE() AND status IN ('pending', 'approved')");
    $todayBookings = $stmt->fetch()['total'];
    
    // Total users
    $stmt = $db->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch()['total'];
    
    // This month's bookings
    $stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $monthlyBookings = $stmt->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_resources' => $totalResources,
            'available_resources' => $availableResources,
            'total_bookings' => $totalBookings,
            'pending_approvals' => $pendingApprovals,
            'active_bookings' => $activeBookings,
            'today_bookings' => $todayBookings,
            'total_users' => $totalUsers,
            'monthly_bookings' => $monthlyBookings
        ]
    ]);
}

function getUsageStats() {
    global $db;
    
    $period = $_GET['period'] ?? 'month';
    
    $dateCondition = match($period) {
        'week' => "AND b.start_datetime >= DATE_SUB(NOW(), INTERVAL 1 WEEK)",
        'month' => "AND b.start_datetime >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
        'quarter' => "AND b.start_datetime >= DATE_SUB(NOW(), INTERVAL 3 MONTH)",
        'year' => "AND b.start_datetime >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
        default => "AND b.start_datetime >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
    };
    
    $stmt = $db->query("SELECT r.id, r.name, c.name as category,
                        COUNT(b.id) as total_bookings,
                        SUM(TIMESTAMPDIFF(HOUR, b.start_datetime, b.end_datetime)) as total_hours,
                        COUNT(CASE WHEN b.status = 'approved' THEN 1 END) as approved_bookings,
                        COUNT(CASE WHEN b.status = 'rejected' THEN 1 END) as rejected_bookings
                        FROM resources r
                        LEFT JOIN categories c ON r.category_id = c.id
                        LEFT JOIN bookings b ON r.id = b.resource_id $dateCondition
                        GROUP BY r.id
                        ORDER BY total_bookings DESC");
    
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function getCategoryUsage() {
    global $db;
    
    $stmt = $db->query("SELECT c.name, c.icon, COUNT(b.id) as booking_count,
                        SUM(TIMESTAMPDIFF(HOUR, b.start_datetime, b.end_datetime)) as total_hours
                        FROM categories c
                        LEFT JOIN resources r ON c.id = r.category_id
                        LEFT JOIN bookings b ON r.id = b.resource_id AND b.status = 'approved'
                        GROUP BY c.id
                        ORDER BY booking_count DESC");
    
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function getMonthlyBookings() {
    global $db;
    
    $stmt = $db->query("SELECT 
                        DATE_FORMAT(start_datetime, '%Y-%m') as month,
                        COUNT(*) as total,
                        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
                        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
                        FROM bookings
                        WHERE start_datetime >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                        GROUP BY DATE_FORMAT(start_datetime, '%Y-%m')
                        ORDER BY month ASC");
    
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function getTopResources() {
    global $db;
    
    $limit = $_GET['limit'] ?? 5;
    
    $stmt = $db->prepare("SELECT r.name, c.name as category, COUNT(b.id) as booking_count
                          FROM resources r
                          LEFT JOIN categories c ON r.category_id = c.id
                          LEFT JOIN bookings b ON r.id = b.resource_id AND b.status = 'approved'
                          GROUP BY r.id
                          ORDER BY booking_count DESC
                          LIMIT ?");
    $stmt->execute([(int)$limit]);
    
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function getRecentActivity() {
    global $db;
    
    $limit = $_GET['limit'] ?? 10;
    
    $stmt = $db->prepare("SELECT a.*, u.full_name as user_name
                          FROM activity_log a
                          LEFT JOIN users u ON a.user_id = u.id
                          ORDER BY a.created_at DESC
                          LIMIT ?");
    $stmt->execute([(int)$limit]);
    
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}
?>
