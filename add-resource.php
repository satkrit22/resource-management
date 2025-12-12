<?php
require_once 'config/auth.php';
requireAdmin();

$db = getDBConnection();

// Get categories
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $categoryId = $_POST['category_id'] ?? '';
    $description = sanitize($_POST['description'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 1);
    $status = $_POST['status'] ?? 'available';
    $specifications = sanitize($_POST['specifications'] ?? '');
    
    if (empty($name)) {
        $error = 'Resource name is required';
    } else {
        $stmt = $db->prepare("INSERT INTO resources (name, category_id, description, location, capacity, status, specifications) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$name, $categoryId ?: null, $description, $location, $capacity, $status, $specifications]);
        
        if ($result) {
            logActivity($_SESSION['user_id'], 'create', 'resource', $db->lastInsertId(), "Created resource: $name");
            $success = 'Resource created successfully!';
        } else {
            $error = 'Failed to create resource';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Resource - Resource Management System</title>
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
                        <span class="current">Add Resource</span>
                    </div>
                </div>
            </header>
            
            <div class="page-content">
                <div class="page-header">
                    <h1 class="page-title">Add New Resource</h1>
                    <p class="page-subtitle">Create a new resource for booking</p>
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
                                <label class="form-label">Resource Name <span class="required">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g., Conference Room A, Dell Laptop #1" required>
                            </div>
                            
                            <div class="d-flex gap-3">
                                <div class="form-group" style="flex: 1;">
                                    <label class="form-label">Category</label>
                                    <select name="category_id" class="form-control">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-control">
                                        <option value="available">Available</option>
                                        <option value="maintenance">Under Maintenance</option>
                                        <option value="retired">Retired</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-3">
                                <div class="form-group" style="flex: 2;">
                                    <label class="form-label">Location</label>
                                    <input type="text" name="location" class="form-control" placeholder="e.g., Building A, Floor 2">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label class="form-label">Capacity</label>
                                    <input type="number" name="capacity" class="form-control" value="1" min="1">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Describe the resource..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Specifications</label>
                                <textarea name="specifications" class="form-control" rows="3" placeholder="Technical specifications, features, etc."></textarea>
                            </div>
                            
                            <div class="d-flex gap-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-plus"></i>
                                    Create Resource
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
</body>
</html>
