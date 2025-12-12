<?php
require_once 'config/auth.php';
requireLogin();

$db = getDBConnection();

// Get categories
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Get resources with filters
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

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
$resources = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources - Resource Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Dashboard</a>
                        <span class="separator">/</span>
                        <span class="current">Resources</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <?php if (isAdmin()): ?>
                    <a href="add-resource.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        <span>Add Resource</span>
                    </a>
                    <?php endif; ?>
                </div>
            </header>
            
            <!-- Page Content -->
            <div class="page-content">
                <div class="page-header d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">Resources</h1>
                        <p class="page-subtitle">Browse and book available resources</p>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="d-flex gap-3 flex-wrap align-items-center">
                            <div class="search-box" style="max-width: none; flex: 1; min-width: 200px;">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" placeholder="Search resources..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <select name="category" class="form-control" style="width: auto; min-width: 150px;">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <select name="status" class="form-control" style="width: auto; min-width: 130px;">
                                <option value="">All Status</option>
                                <option value="available" <?php echo $status == 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="maintenance" <?php echo $status == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="retired" <?php echo $status == 'retired' ? 'selected' : ''; ?>>Retired</option>
                            </select>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i>
                                Filter
                            </button>
                            
                            <?php if ($search || $category || $status): ?>
                            <a href="resources.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Clear
                            </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <!-- Resources Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
                    <?php if (empty($resources)): ?>
                    <div class="card" style="grid-column: 1 / -1;">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-box-open"></i>
                            </div>
                            <div class="empty-state-title">No resources found</div>
                            <div class="empty-state-text">Try adjusting your filters or search terms</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($resources as $resource): ?>
                    <div class="resource-card">
                        <div class="resource-card-image">
                            <i class="fas fa-<?php echo $resource['category_icon'] ?? 'box'; ?>"></i>
                        </div>
                        <div class="resource-card-body">
                            <h4 class="resource-card-title"><?php echo htmlspecialchars($resource['name']); ?></h4>
                            <div class="resource-card-category">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($resource['category_name'] ?? 'Uncategorized'); ?>
                            </div>
                            <div class="resource-card-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($resource['location'] ?? 'Not specified'); ?>
                            </div>
                            <?php if ($resource['capacity'] > 1): ?>
                            <div class="resource-card-location mt-1">
                                <i class="fas fa-users"></i>
                                Capacity: <?php echo $resource['capacity']; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="resource-card-footer">
                            <span class="status-badge <?php echo $resource['status']; ?>">
                                <?php echo ucfirst($resource['status']); ?>
                            </span>
                            <div class="d-flex gap-2">
                                <?php if ($resource['status'] === 'available'): ?>
                                <a href="book-resource.php?resource=<?php echo $resource['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-calendar-plus"></i>
                                    Book
                                </a>
                                <?php endif; ?>
                                <?php if (isAdmin()): ?>
                                <a href="edit-resource.php?id=<?php echo $resource['id']; ?>" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="assets/js/app.js"></script>
</body>
</html>
