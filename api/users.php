<?php
require_once '../config/auth.php';
requireLogin();
requireAdmin();

header('Content-Type: application/json');

$db = getDBConnection();
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)");
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt->execute([
                $data['username'],
                $data['email'],
                $passwordHash,
                $data['full_name'],
                $data['role']
            ]);
            echo json_encode(['success' => true, 'message' => 'User created successfully']);
            break;
            
        case 'update':
            if (!empty($data['password'])) {
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, full_name = ?, role = ? WHERE id = ?");
                $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt->execute([
                    $data['username'],
                    $data['email'],
                    $passwordHash,
                    $data['full_name'],
                    $data['role'],
                    $data['user_id']
                ]);
            } else {
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, role = ? WHERE id = ?");
                $stmt->execute([
                    $data['username'],
                    $data['email'],
                    $data['full_name'],
                    $data['role'],
                    $data['user_id']
                ]);
            }
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            break;
            
        case 'delete':
            // Check if user has bookings
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ?");
            $stmt->execute([$data['user_id']]);
            $bookingCount = $stmt->fetch()['count'];
            
            if ($bookingCount > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete user with existing bookings']);
            } else {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$data['user_id']]);
                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
