<?php
require_once 'config/auth.php';
requireLogin();

$page_title = 'All Bookings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Resource Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <header class="header">
                <div class="header-left">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Dashboard</a>
                        <span class="separator">/</span>
                        <span class="current">Bookings</span>
                    </div>
                </div>
                
            </header>
        
        <div class="container">
            <div class="page-header">
                <h1><?php echo $page_title; ?></h1>
                <div class="header-actions">
                    <a href="book-resource.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Booking
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-body">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label>Status</label>
                            <select id="filterStatus" class="form-control">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Resource Type</label>
                            <select id="filterType" class="form-control">
                                <option value="">All Types</option>
                                <option value="room">Meeting Room</option>
                                <option value="equipment">Equipment</option>
                                <option value="vehicle">Vehicle</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date Range</label>
                            <input type="date" id="filterDateFrom" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <input type="date" id="filterDateTo" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button onclick="applyFilters()" class="btn btn-secondary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bookings Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Resource</th>
                                    <th>User</th>
                                    <th>Date & Time</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="bookingsTable">
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <i class="fas fa-spinner fa-spin"></i> Loading bookings...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Standardized approval modal structure -->
    <!-- Approval Modal -->
    <div id="approvalModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="approvalModalTitle">Approve Booking</h3>
                <button class="close-modal" onclick="closeApprovalModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p id="approvalModalMessage">Are you sure you want to approve this booking?</p>
                <div class="form-group">
                    <label>Remarks (Optional)</label>
                    <textarea id="approvalRemarks" class="form-control" rows="4" placeholder="Add any comments or instructions..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeApprovalModal()">Cancel</button>
                <button id="confirmApprovalBtn" class="btn btn-primary" onclick="confirmApproval()">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Standardized view modal structure -->
    <!-- View Details Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3>Booking Details</h3>
                <button class="close-modal" onclick="closeViewModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        let currentBookings = [];
        let currentAction = null;
        let currentBookingId = null;

        // Load bookings on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadBookings();
        });

        async function loadBookings() {
            try {
                const response = await fetch('api/bookings.php');
                const result = await response.json();

                if (result.success) {
                    currentBookings = result.bookings;
                    displayBookings(currentBookings);
                } else {
                    showToast('Failed to load bookings', 'error');
                }
            } catch (error) {
                console.error('Error loading bookings:', error);
                showToast('Error loading bookings', 'error');
            }
        }

        function displayBookings(bookings) {
            const tbody = document.getElementById('bookingsTable');
            
            if (bookings.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">No bookings found</td></tr>';
                return;
            }

            tbody.innerHTML = bookings.map(booking => {
                const statusClass = `badge-${booking.status}`;
                const isAdmin = <?php echo $_SESSION['role'] === 'admin' ? 'true' : 'false'; ?>;
                
                let actions = `
                    <button class="btn-icon" onclick="viewBooking(${booking.id})" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                `;

                if (isAdmin && booking.status === 'pending') {
                    actions += `
                        <button class="btn-icon btn-success" onclick="openApprovalModal(${booking.id}, 'approved')" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn-icon btn-danger" onclick="openApprovalModal(${booking.id}, 'rejected')" title="Reject">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                }

                return `
                    <tr>
                        <td>#${booking.id}</td>
                        <td>
                            <div class="resource-info">
                                <strong>${booking.resource_name}</strong>
                                <small>${booking.resource_type}</small>
                            </div>
                        </td>
                        <td>
                            <div class="user-info">
                                ${booking.username}
                                <small>${booking.email}</small>
                            </div>
                        </td>
                        <td>
                            <div>
                                <i class="fas fa-calendar"></i> ${formatDate(booking.booking_date)}
                                <br>
                                <i class="fas fa-clock"></i> ${booking.start_time} - ${booking.end_time}
                            </div>
                        </td>
                        <td>${booking.purpose || '-'}</td>
                        <td><span class="badge ${statusClass}">${booking.status}</span></td>
                        <td>
                            <div class="action-buttons">
                                ${actions}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function applyFilters() {
            const status = document.getElementById('filterStatus').value;
            const type = document.getElementById('filterType').value;
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;

            let filtered = currentBookings;

            if (status) {
                filtered = filtered.filter(b => b.status === status);
            }

            if (type) {
                filtered = filtered.filter(b => b.resource_type === type);
            }

            if (dateFrom) {
                filtered = filtered.filter(b => b.booking_date >= dateFrom);
            }

            if (dateTo) {
                filtered = filtered.filter(b => b.booking_date <= dateTo);
            }

            displayBookings(filtered);
        }

        function openApprovalModal(bookingId, action) {
            currentBookingId = bookingId;
            currentAction = action;

            const modal = document.getElementById('approvalModal');
            const title = document.getElementById('approvalModalTitle');
            const message = document.getElementById('approvalModalMessage');
            const btn = document.getElementById('confirmApprovalBtn');

            if (action === 'approved') {
                title.textContent = 'Approve Booking';
                message.textContent = 'Are you sure you want to approve this booking?';
                btn.textContent = 'Approve';
                btn.className = 'btn btn-success';
            } else {
                title.textContent = 'Reject Booking';
                message.textContent = 'Are you sure you want to reject this booking?';
                btn.textContent = 'Reject';
                btn.className = 'btn btn-danger';
            }

            document.getElementById('approvalRemarks').value = '';
            modal.style.display = 'block';
        }

        function closeApprovalModal() {
            document.getElementById('approvalModal').style.display = 'none';
        }

        async function confirmApproval() {
            const remarks = document.getElementById('approvalRemarks').value;

            try {
                const response = await fetch('api/bookings.php', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: currentBookingId,
                        status: currentAction,
                        remarks: remarks
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showToast(`Booking ${currentAction} successfully`, 'success');
                    closeApprovalModal();
                    loadBookings();
                } else {
                    showToast(result.message || 'Failed to update booking', 'error');
                }
            } catch (error) {
                console.error('Error updating booking:', error);
                showToast('Error updating booking', 'error');
            }
        }

        async function viewBooking(bookingId) {
            const booking = currentBookings.find(b => b.id === bookingId);
            if (!booking) return;

            const modal = document.getElementById('viewModal');
            const body = document.getElementById('viewModalBody');

            body.innerHTML = `
                <div class="booking-details-grid">
                    <div class="detail-section">
                        <h4>Resource Information</h4>
                        <div class="detail-item">
                            <span class="detail-label">Resource Name:</span>
                            <span class="detail-value">${booking.resource_name}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Type:</span>
                            <span class="detail-value">${booking.resource_type}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Location:</span>
                            <span class="detail-value">${booking.location || 'N/A'}</span>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4>Booking Information</h4>
                        <div class="detail-item">
                            <span class="detail-label">Booking Date:</span>
                            <span class="detail-value">${formatDate(booking.booking_date)}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Time:</span>
                            <span class="detail-value">${booking.start_time} - ${booking.end_time}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value"><span class="badge badge-${booking.status}">${booking.status}</span></span>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4>User Information</h4>
                        <div class="detail-item">
                            <span class="detail-label">Name:</span>
                            <span class="detail-value">${booking.username}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value">${booking.email}</span>
                        </div>
                    </div>

                    <div class="detail-section full-width">
                        <h4>Purpose</h4>
                        <p>${booking.purpose || 'No purpose specified'}</p>
                    </div>

                    ${booking.remarks ? `
                    <div class="detail-section full-width">
                        <h4>Admin Remarks</h4>
                        <p>${booking.remarks}</p>
                    </div>
                    ` : ''}
                </div>
            `;

            modal.style.display = 'block';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        function showToast(message, type = 'info') {
            // Simple toast notification
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('show');
            }, 100);

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const approvalModal = document.getElementById('approvalModal');
            const viewModal = document.getElementById('viewModal');
            
            if (event.target === approvalModal) {
                closeApprovalModal();
            }
            if (event.target === viewModal) {
                closeViewModal();
            }
        }
    </script>
</body>
</html>
