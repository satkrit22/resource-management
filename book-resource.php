<?php
require_once 'config/auth.php';
requireLogin();

$db = getDBConnection();

// Get resources
$stmt = $db->query("SELECT r.*, c.name as category_name FROM resources r LEFT JOIN categories c ON r.category_id = c.id WHERE r.status = 'available' ORDER BY r.name");
$resources = $stmt->fetchAll();

$selectedResource = $_GET['resource'] ?? '';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resourceId = $_POST['resource_id'] ?? '';
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $startDate = $_POST['start_date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $endTime = $_POST['end_time'] ?? '';
    
    $startDatetime = $startDate . ' ' . $startTime . ':00';
    $endDatetime = $endDate . ' ' . $endTime . ':00';
    
    // Validate
    if (strtotime($endDatetime) <= strtotime($startDatetime)) {
        $error = 'End time must be after start time';
    } else {
        // Check availability
        $stmt = $db->prepare("SELECT COUNT(*) as conflicts FROM bookings 
                              WHERE resource_id = ? 
                              AND status IN ('pending', 'approved')
                              AND ((start_datetime <= ? AND end_datetime > ?) 
                                   OR (start_datetime < ? AND end_datetime >= ?)
                                   OR (start_datetime >= ? AND end_datetime <= ?))");
        $stmt->execute([$resourceId, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $startDatetime, $endDatetime]);
        
        if ($stmt->fetch()['conflicts'] > 0) {
            $error = 'This resource is not available for the selected time slot';
        } else {
            // Create booking
            $stmt = $db->prepare("INSERT INTO bookings (resource_id, user_id, title, description, start_datetime, end_datetime, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $result = $stmt->execute([$resourceId, $_SESSION['user_id'], $title, $description, $startDatetime, $endDatetime]);
            
            if ($result) {
                // Get resource name
                $stmt = $db->prepare("SELECT name FROM resources WHERE id = ?");
                $stmt->execute([$resourceId]);
                $resourceName = $stmt->fetch()['name'];
                
                // Notify admins
                $stmt = $db->prepare("SELECT id FROM users WHERE role = 'admin'");
                $stmt->execute();
                $admins = $stmt->fetchAll();
                
                foreach ($admins as $admin) {
                    createNotification($admin['id'], 'New Booking Request', "{$_SESSION['full_name']} has requested to book $resourceName", 'booking');
                }
                
                logActivity($_SESSION['user_id'], 'create', 'booking', $db->lastInsertId(), "Created booking for $resourceName");
                
                $success = 'Booking request submitted successfully! Waiting for approval.';
            } else {
                $error = 'Failed to create booking. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Resource - Resource Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="header-left">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Dashboard</a>
                        <span class="separator">/</span>
                        <a href="resources.php">Resources</a>
                        <span class="separator">/</span>
                        <span class="current">Book Resource</span>
                    </div>
                </div>
            </header>
            
            <div class="page-content">
                <div class="page-header">
                    <h1 class="page-title">Book a Resource</h1>
                    <p class="page-subtitle">Fill in the details to request a booking</p>
                </div>
                
                <div class="card" style="max-width: 700px;">
                    <div class="card-body">
                        <?php if ($success): ?>
                        <div class="alert alert-success mb-4">
                            <i class="fas fa-check-circle alert-icon"></i>
                            <div class="alert-content"><?php echo $success; ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger mb-4">
                            <i class="fas fa-exclamation-circle alert-icon"></i>
                            <div class="alert-content"><?php echo $error; ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <label class="form-label">Select Resource <span class="required">*</span></label>
                                <select name="resource_id" class="form-control" required id="resourceSelect">
                                    <option value="">Choose a resource...</option>
                                    <?php foreach ($resources as $resource): ?>
                                    <option value="<?php echo $resource['id']; ?>" 
                                            <?php echo $selectedResource == $resource['id'] ? 'selected' : ''; ?>
                                            data-category="<?php echo htmlspecialchars($resource['category_name']); ?>"
                                            data-location="<?php echo htmlspecialchars($resource['location']); ?>">
                                        <?php echo htmlspecialchars($resource['name']); ?> 
                                        (<?php echo htmlspecialchars($resource['category_name']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Booking Title <span class="required">*</span></label>
                                <input type="text" name="title" class="form-control" placeholder="e.g., Team Meeting, Project Presentation" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Provide additional details about your booking..."></textarea>
                            </div>
                            
                            <div class="d-flex gap-3">
                                <div class="form-group" style="flex: 1;">
                                    <label class="form-label">Start Date <span class="required">*</span></label>
                                    <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label class="form-label">Start Time <span class="required">*</span></label>
                                    <input type="time" name="start_time" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-3">
                                <div class="form-group" style="flex: 1;">
                                    <label class="form-label">End Date <span class="required">*</span></label>
                                    <input type="date" name="end_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label class="form-label">End Time <span class="required">*</span></label>
                                    <input type="time" name="end_time" class="form-control" required>
                                </div>
                            </div>
                            
                            <div id="availabilityCheck" class="mb-4" style="display: none;">
                                <!-- Availability status will be shown here -->
                            </div>
                            
                            <div class="d-flex gap-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-calendar-check"></i>
                                    Submit Booking Request
                                </button>
                                <a href="resources.php" class="btn btn-secondary btn-lg">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="assets/js/app.js"></script>
    <script>
        // Auto-fill end date when start date changes
        document.querySelector('input[name="start_date"]').addEventListener('change', function() {
            const endDate = document.querySelector('input[name="end_date"]');
            if (!endDate.value) {
                endDate.value = this.value;
            }
        });
    </script>
</body>
</html>
