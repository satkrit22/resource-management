<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            getResources();
        } elseif ($action === 'single' && isset($_GET['id'])) {
            getResource($_GET['id']);
        } elseif ($action === 'categories') {
            getCategories();
        } elseif ($action === 'availability') {
            checkAvailability();
        } else {
            getResources();
        }
        break;
        
    case 'POST':
        requireAdmin();
        createResource();
        break;
        
    case 'PUT':
        requireAdmin();
        updateResource();
        break;
        
    case 'DELETE':
        requireAdmin();
        deleteResource();
        break;
        
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getResources() {
    global $db;
    
    $category = $_GET['category'] ?? null;
    $status = $_GET['status'] ?? null;
    $search = $_GET['search'] ?? null;
    
    $sql = "SELECT r.*, c.name as category_name, c.icon as category_icon 
            FROM resources r 
            LEFT JOIN categories c ON r.category_id = c.id 
            WHERE 1=1";
    $params = [];
    
    if ($category) {
        $sql .= " AND r.category_id = ?";
        $params[] = $category;
    }
    
    if ($status) {
        $sql .= " AND r.status = ?";
        $params[] = $status;
    }
    
    if ($search) {
        $sql .= " AND (r.name LIKE ? OR r.description LIKE ? OR r.location LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY r.name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function getResource($id) {
    global $db;
    
    $stmt = $db->prepare("SELECT r.*, c.name as category_name FROM resources r LEFT JOIN categories c ON r.category_id = c.id WHERE r.id = ?");
    $stmt->execute([$id]);
    $resource = $stmt->fetch();
    
    if ($resource) {
        echo json_encode(['success' => true, 'data' => $resource]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Resource not found']);
    }
}

function getCategories() {
    global $db;
    
    $stmt = $db->query("SELECT * FROM categories ORDER BY name");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function checkAvailability() {
    global $db;
    
    $resourceId = $_GET['resource_id'] ?? null;
    $startDate = $_GET['start'] ?? null;
    $endDate = $_GET['end'] ?? null;
    $excludeBookingId = $_GET['exclude'] ?? null;
    
    if (!$resourceId || !$startDate || !$endDate) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }
    
    $sql = "SELECT COUNT(*) as conflicts FROM bookings 
            WHERE resource_id = ? 
            AND status IN ('pending', 'approved')
            AND ((start_datetime <= ? AND end_datetime > ?) 
                 OR (start_datetime < ? AND end_datetime >= ?)
                 OR (start_datetime >= ? AND end_datetime <= ?))";
    $params = [$resourceId, $endDate, $startDate, $endDate, $startDate, $startDate, $endDate];
    
    if ($excludeBookingId) {
        $sql .= " AND id != ?";
        $params[] = $excludeBookingId;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true, 
        'available' => $result['conflicts'] == 0,
        'conflicts' => $result['conflicts']
    ]);
}

function createResource() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $db->prepare("INSERT INTO resources (name, category_id, description, location, capacity, status, specifications) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([
        $data['name'],
        $data['category_id'],
        $data['description'] ?? null,
        $data['location'] ?? null,
        $data['capacity'] ?? 1,
        $data['status'] ?? 'available',
        $data['specifications'] ?? null
    ]);
    
    if ($result) {
        $id = $db->lastInsertId();
        logActivity($_SESSION['user_id'], 'create', 'resource', $id, "Created resource: {$data['name']}");
        echo json_encode(['success' => true, 'message' => 'Resource created', 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create resource']);
    }
}

function updateResource() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Resource ID required']);
        return;
    }
    
    $stmt = $db->prepare("UPDATE resources SET name = ?, category_id = ?, description = ?, location = ?, capacity = ?, status = ?, specifications = ? WHERE id = ?");
    $result = $stmt->execute([
        $data['name'],
        $data['category_id'],
        $data['description'] ?? null,
        $data['location'] ?? null,
        $data['capacity'] ?? 1,
        $data['status'] ?? 'available',
        $data['specifications'] ?? null,
        $id
    ]);
    
    if ($result) {
        logActivity($_SESSION['user_id'], 'update', 'resource', $id, "Updated resource: {$data['name']}");
        echo json_encode(['success' => true, 'message' => 'Resource updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update resource']);
    }
}

function deleteResource() {
    global $db;
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Resource ID required']);
        return;
    }
    
    // Get resource name for logging
    $stmt = $db->prepare("SELECT name FROM resources WHERE id = ?");
    $stmt->execute([$id]);
    $resource = $stmt->fetch();
    
    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        logActivity($_SESSION['user_id'], 'delete', 'resource', $id, "Deleted resource: {$resource['name']}");
        echo json_encode(['success' => true, 'message' => 'Resource deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete resource']);
    }
}
?>
