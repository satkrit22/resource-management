<?php
require_once 'config/auth.php';
requireAdmin();

$db = getDBConnection();

// Get pending bookings
$stmt = $db->prepare("SELECT b.*, r.name as resource_name, r.location, u.full_name as user_name, u.email, u.department
                      FROM bookings b
                      JOIN resources r ON b.resource_id = r.id
                      JOIN users u ON b.user_id = u.id
                      WHERE b.status = 'pending'
                      ORDER BY b.created_at ASC");
$stmt->execute();
$pendingBookings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approvals - Resource Management System</title>
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
                        <span class="current">Approvals</span>
                    </div>
                </div>
            </header>
            
            <div class="page-content">
                <div class="page-header">
                    <h1 class="page-title">Pending Approvals</h1>
                    <p class="page-subtitle">Review and manage booking requests</p>
                </div>
                
                <?php if (empty($pendingBookings)): ?>
                <div class="card">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="empty-state-title">No pending approvals</div>
                        <div class="empty-state-text">All booking requests have been processed</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Resource</th>
                                    <th>Requested By</th>
                                    <th>Booking Details</th>
                                    <th>Date & Time</th>
                                    <th>Requested</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingBookings as $booking): ?>
                                <tr id="booking-<?php echo $booking['id']; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($booking['resource_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($booking['location']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($booking['user_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($booking['department']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($booking['title']); ?></strong>
                                        <?php if ($booking['description']): ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($booking['description'], 0, 50)); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($booking['start_datetime'])); ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo date('h:i A', strtotime($booking['start_datetime'])); ?> - 
                                            <?php echo date('h:i A', strtotime($booking['end_datetime'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y h:i A', strtotime($booking['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-success btn-sm" onclick="approveBooking(<?php echo $booking['id']; ?>)">
                                                <i class="fas fa-check"></i>
                                                Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="showRejectModal(<?php echo $booking['id']; ?>)">
                                                <i class="fas fa-times"></i>
                                                Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal-overlay" id="rejectModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Reject Booking</h3>
                <button class="modal-close" onclick="closeModal('rejectModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rejectBookingId">
                <div class="form-group">
                    <label class="form-label">Reason for Rejection</label>
                    <textarea id="rejectReason" class="form-control" rows="3" placeholder="Please provide a reason for rejecting this booking..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                <button class="btn btn-danger" onclick="rejectBooking()">
                    <i class="fas fa-times"></i>
                    Reject Booking
                </button>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
    <script>
        function approveBooking(id) {
            if (!confirm('Are you sure you want to approve this booking?')) return;
            
            fetch('api/bookings.php?action=approve', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('booking-' + id).remove();
                    showToast('Booking approved successfully', 'success');
                    
                    // Check if table is empty
                    if (document.querySelectorAll('tbody tr').length === 0) {
                        location.reload();
                    }
                } else {
                    showToast(data.message || 'Failed to approve booking', 'error');
                }
            })
            .catch(error => {
                showToast('An error occurred', 'error');
            });
        }
        
        function showRejectModal(id) {
            document.getElementById('rejectBookingId').value = id;
            document.getElementById('rejectReason').value = '';
            openModal('rejectModal');
        }
        
        function rejectBooking() {
            const id = document.getElementById('rejectBookingId').value;
            const reason = document.getElementById('rejectReason').value;
            
            fetch('api/bookings.php?action=reject', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, reason: reason })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal('rejectModal');
                    document.getElementById('booking-' + id).remove();
                    showToast('Booking rejected', 'success');
                    
                    if (document.querySelectorAll('tbody tr').length === 0) {
                        location.reload();
                    }
                } else {
                    showToast(data.message || 'Failed to reject booking', 'error');
                }
            })
            .catch(error => {
                showToast('An error occurred', 'error');
            });
        }
    </script>
</body>
</html>
