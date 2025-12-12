<?php
require_once '../config/auth.php';
requireLogin();
requireAdmin();

$db = getDBConnection();
$type = $_GET['type'] ?? 'csv';
$startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end'] ?? date('Y-m-d');

// Get booking data
$stmt = $db->prepare("SELECT b.*, r.name as resource_name, r.category, u.full_name as user_name, u.email as user_email
                      FROM bookings b
                      JOIN resources r ON b.resource_id = r.id
                      JOIN users u ON b.user_id = u.id
                      WHERE DATE(b.created_at) BETWEEN ? AND ?
                      ORDER BY b.created_at DESC");
$stmt->execute([$startDate, $endDate]);
$bookings = $stmt->fetchAll();

if ($type === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="bookings_report_' . $startDate . '_to_' . $endDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, ['ID', 'Resource', 'Category', 'User', 'Email', 'Title', 'Start Date', 'End Date', 'Status', 'Created At']);
    
    // CSV data
    foreach ($bookings as $booking) {
        fputcsv($output, [
            $booking['id'],
            $booking['resource_name'],
            $booking['category'],
            $booking['user_name'],
            $booking['user_email'],
            $booking['title'],
            $booking['start_datetime'],
            $booking['end_datetime'],
            $booking['status'],
            $booking['created_at']
        ]);
    }
    
    fclose($output);
    exit;
}
