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
        getNotifications();
        break;
    case 'PUT':
        markAsRead();
        break;
    case 'DELETE':
        deleteNotification();
        break;
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getNotifications() {
    global $db;
    
    $unreadOnly = isset($_GET['unread']);
    
    $sql = "SELECT * FROM notifications WHERE user_id = ?";
    $params = [$_SESSION['user_id']];
    
    if ($unreadOnly) {
        $sql .= " AND is_read = FALSE";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT 50";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // Get unread count
    $countStmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $countStmt->execute([$_SESSION['user_id']]);
    $unreadCount = $countStmt->fetch()['count'];
    
    echo json_encode([
        'success' => true, 
        'data' => $stmt->fetchAll(),
        'unread_count' => $unreadCount
    ]);
}

function markAsRead() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['id'])) {
        // Mark single notification as read
        $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['id'], $_SESSION['user_id']]);
    } else {
        // Mark all as read
        $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
}

function deleteNotification() {
    global $db;
    
    $id = $_GET['id'] ?? null;
    
    if ($id) {
        $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Notification deleted']);
}
?>
