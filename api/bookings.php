<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

requireLogin();

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'calendar') {
            getCalendarEvents();
        } elseif ($action === 'single' && isset($_GET['id'])) {
            getBooking($_GET['id']);
        } elseif ($action === 'pending') {
            getPendingBookings();
        } elseif ($action === 'my-bookings') {
            getMyBookings();
        } else {
            getBookings();
        }
        break;
        
    case 'POST':
        if ($action === 'approve') {
            requireAdmin();
            approveBooking();
        } elseif ($action === 'reject') {
            requireAdmin();
            rejectBooking();
        } else {
            createBooking();
        }
        break;
        
    case 'PUT':
        updateBooking();
        break;
        
    case 'DELETE':
        cancelBooking();
        break;
        
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getBookings() {
    global $db;
    
    $status = $_GET['status'] ?? null;
    $resourceId = $_GET['resource_id'] ?? null;
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    $sql = "SELECT b.*, r.name as resource_name, u.full_name as user_name, u.department,
                   a.full_name as approver_name
            FROM bookings b
            JOIN resources r ON b.resource_id = r.id
            JOIN users u ON b.user_id = u.id
            LEFT JOIN users a ON b.approved_by = a.id
            WHERE 1=1";
    $params = [];
    
    // Non-admins can only see their own bookings
    if (!isAdmin()) {
        $sql .= " AND b.user_id = ?";
        $params[] = $_SESSION['user_id'];
    }
    
    if ($status) {
        $sql .= " AND b.status = ?";
        $params[] = $status;
    }
    
    if ($resourceId) {
        $sql .= " AND b.resource_id = ?";
        $params[] = $resourceId;
    }
    
    if ($startDate) {
        $sql .= " AND b.start_datetime >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $sql .= " AND b.end_datetime <= ?";
        $params[] = $endDate;
    }
    
    $sql .= " ORDER BY b.start_datetime DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function getCalendarEvents() {
    global $db;
    
    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-t');
    $resourceId = $_GET['resource_id'] ?? null;
    
    $sql = "SELECT b.*, r.name as resource_name, u.full_name as user_name
            FROM bookings b
            JOIN resources r ON b.resource_id = r.id
            JOIN users u ON b.user_id = u.id
            WHERE b.status IN ('pending', 'approved')
            AND b.start_datetime >= ? AND b.end_datetime <= ?";
    $params = [$start, $end];
    
    if ($resourceId) {
        $sql .= " AND b.resource_id = ?";
        $params[] = $resourceId;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
    
    // Format for calendar
    $events = array_map(function($booking) {
        $colors = [
            'pending' => '#f59e0b',
            'approved' => '#10b981',
            'rejected' => '#ef4444',
            'cancelled' => '#6b7280',
            'completed' => '#3b82f6'
        ];
        
        return [
            'id' => $booking['id'],
            'title' => $booking['title'] . ' - ' . $booking['resource_name'],
            'start' => $booking['start_datetime'],
            'end' => $booking['end_datetime'],
            'color' => $colors[$booking['status']] ?? '#6b7280',
            'extendedProps' => [
                'status' => $booking['status'],
                'resource_name' => $booking['resource_name'],
                'user_name' => $booking['user_name'],
                'description' => $booking['description']
            ]
        ];
    }, $bookings);
    
    echo json_encode($events);
}

function getBooking($id) {
    global $db;
    
    $stmt = $db->prepare("SELECT b.*, r.name as resource_name, u.full_name as user_name 
                          FROM bookings b 
                          JOIN resources r ON b.resource_id = r.id 
                          JOIN users u ON b.user_id = u.id 
                          WHERE b.id = ?");
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    
    if ($booking) {
        // Check permission
        if (!isAdmin() && $booking['user_id'] != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }
        echo json_encode(['success' => true, 'data' => $booking]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
    }
}

function getPendingBookings() {
    global $db;
    
    requireAdmin();
    
    $stmt = $db->prepare("SELECT b.*, r.name as resource_name, u.full_name as user_name, u.department
                          FROM bookings b
                          JOIN resources r ON b.resource_id = r.id
                          JOIN users u ON b.user_id = u.id
                          WHERE b.status = 'pending'
                          ORDER BY b.created_at ASC");
    $stmt->execute();
    
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function getMyBookings() {
    global $db;
    
    $stmt = $db->prepare("SELECT b.*, r.name as resource_name
                          FROM bookings b
                          JOIN resources r ON b.resource_id = r.id
                          WHERE b.user_id = ?
                          ORDER BY b.start_datetime DESC");
    $stmt->execute([$_SESSION['user_id']]);
    
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function createBooking() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['resource_id']) || !isset($data['start_datetime']) || !isset($data['end_datetime'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Check availability
    $stmt = $db->prepare("SELECT COUNT(*) as conflicts FROM bookings 
                          WHERE resource_id = ? 
                          AND status IN ('pending', 'approved')
                          AND ((start_datetime <= ? AND end_datetime > ?) 
                               OR (start_datetime < ? AND end_datetime >= ?)
                               OR (start_datetime >= ? AND end_datetime <= ?))");
    $stmt->execute([
        $data['resource_id'],
        $data['end_datetime'], $data['start_datetime'],
        $data['end_datetime'], $data['start_datetime'],
        $data['start_datetime'], $data['end_datetime']
    ]);
    
    if ($stmt->fetch()['conflicts'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Resource is not available for the selected time slot']);
        return;
    }
    
    $stmt = $db->prepare("INSERT INTO bookings (resource_id, user_id, title, description, start_datetime, end_datetime, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $result = $stmt->execute([
        $data['resource_id'],
        $_SESSION['user_id'],
        $data['title'],
        $data['description'] ?? null,
        $data['start_datetime'],
        $data['end_datetime']
    ]);
    
    if ($result) {
        $bookingId = $db->lastInsertId();
        
        // Get resource name for notification
        $stmt = $db->prepare("SELECT name FROM resources WHERE id = ?");
        $stmt->execute([$data['resource_id']]);
        $resource = $stmt->fetch();
        
        // Notify admins about new booking
        $stmt = $db->prepare("SELECT id FROM users WHERE role = 'admin'");
        $stmt->execute();
        $admins = $stmt->fetchAll();
        
        foreach ($admins as $admin) {
            createNotification(
                $admin['id'],
                'New Booking Request',
                "{$_SESSION['full_name']} has requested to book {$resource['name']}",
                'booking'
            );
        }
        
        logActivity($_SESSION['user_id'], 'create', 'booking', $bookingId, "Created booking for {$resource['name']}");
        
        echo json_encode(['success' => true, 'message' => 'Booking request submitted', 'id' => $bookingId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create booking']);
    }
}

function approveBooking() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Booking ID required']);
        return;
    }
    
    // Get booking details
    $stmt = $db->prepare("SELECT b.*, r.name as resource_name, u.id as user_id FROM bookings b JOIN resources r ON b.resource_id = r.id JOIN users u ON b.user_id = u.id WHERE b.id = ?");
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        return;
    }
    
    $stmt = $db->prepare("UPDATE bookings SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$_SESSION['user_id'], $id]);
    
    if ($result) {
        // Notify user
        createNotification(
            $booking['user_id'],
            'Booking Approved',
            "Your booking for {$booking['resource_name']} has been approved",
            'approval'
        );
        
        logActivity($_SESSION['user_id'], 'approve', 'booking', $id, "Approved booking for {$booking['resource_name']}");
        
        echo json_encode(['success' => true, 'message' => 'Booking approved']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to approve booking']);
    }
}

function rejectBooking() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    $reason = $data['reason'] ?? 'No reason provided';
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Booking ID required']);
        return;
    }
    
    // Get booking details
    $stmt = $db->prepare("SELECT b.*, r.name as resource_name, u.id as user_id FROM bookings b JOIN resources r ON b.resource_id = r.id JOIN users u ON b.user_id = u.id WHERE b.id = ?");
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        return;
    }
    
    $stmt = $db->prepare("UPDATE bookings SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?");
    $result = $stmt->execute([$_SESSION['user_id'], $reason, $id]);
    
    if ($result) {
        // Notify user
        createNotification(
            $booking['user_id'],
            'Booking Rejected',
            "Your booking for {$booking['resource_name']} has been rejected. Reason: $reason",
            'rejection'
        );
        
        logActivity($_SESSION['user_id'], 'reject', 'booking', $id, "Rejected booking for {$booking['resource_name']}");
        
        echo json_encode(['success' => true, 'message' => 'Booking rejected']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject booking']);
    }
}

function updateBooking() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Booking ID required']);
        return;
    }
    
    // Check permission
    $stmt = $db->prepare("SELECT user_id, status FROM bookings WHERE id = ?");
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    
    if (!$booking || (!isAdmin() && $booking['user_id'] != $_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    if ($booking['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Can only edit pending bookings']);
        return;
    }
    
    $stmt = $db->prepare("UPDATE bookings SET title = ?, description = ?, start_datetime = ?, end_datetime = ? WHERE id = ?");
    $result = $stmt->execute([
        $data['title'],
        $data['description'] ?? null,
        $data['start_datetime'],
        $data['end_datetime'],
        $id
    ]);
    
    if ($result) {
        logActivity($_SESSION['user_id'], 'update', 'booking', $id, "Updated booking");
        echo json_encode(['success' => true, 'message' => 'Booking updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update booking']);
    }
}

function cancelBooking() {
    global $db;
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Booking ID required']);
        return;
    }
    
    // Check permission
    $stmt = $db->prepare("SELECT user_id FROM bookings WHERE id = ?");
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    
    if (!$booking || (!isAdmin() && $booking['user_id'] != $_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    $stmt = $db->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        logActivity($_SESSION['user_id'], 'cancel', 'booking', $id, "Cancelled booking");
        echo json_encode(['success' => true, 'message' => 'Booking cancelled']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel booking']);
    }
}
?>
