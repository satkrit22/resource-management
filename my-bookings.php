<?php
require_once 'config/auth.php';
requireLogin();

$page_title = 'My Bookings';
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
                        <span class="current">My Bookings</span>
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

            <!-- Status Summary Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3 id="pendingCount">0</h3>
                        <p>Pending</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon approved">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3 id="approvedCount">0</h3>
                        <p>Approved</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon rejected">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3 id="rejectedCount">0</h3>
                        <p>Rejected</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon cancelled">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div class="stat-content">
                        <h3 id="cancelledCount">0</h3>
                        <p>Cancelled</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-body">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label>Status</label>
                            <select id="filterStatus" class="form-control" onchange="applyFilters()">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Time Period</label>
                            <select id="filterPeriod" class="form-control" onchange="applyFilters()">
                                <option value="">All Time</option>
                                <option value="upcoming">Upcoming</option>
                                <option value="past">Past</option>
                                <option value="today">Today</option>
                                <option value="this-week">This Week</option>
                                <option value="this-month">This Month</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Resource Type</label>
                            <select id="filterType" class="form-control" onchange="applyFilters()">
                                <option value="">All Types</option>
                                <option value="room">Meeting Room</option>
                                <option value="equipment">Equipment</option>
                                <option value="vehicle">Vehicle</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bookings Cards -->
            <div id="bookingsContainer">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p>Loading your bookings...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Cancel Booking</h3>
                <button class="close-modal" onclick="closeCancelModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this booking?</p>
                <div class="form-group">
                    <label>Reason for Cancellation (Optional)</label>
                    <textarea id="cancelReason" class="form-control" rows="4" placeholder="Please provide a reason..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeCancelModal()">No, Keep It</button>
                <button class="btn btn-danger" onclick="confirmCancel()">Yes, Cancel Booking</button>
            </div>
        </div>
    </div>

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
        let myBookings = [];
        let currentCancelId = null;

        // Load bookings on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadMyBookings();
        });

        async function loadMyBookings() {
            try {
                const response = await fetch('api/bookings.php?my=true');
                const result = await response.json();

                if (result.success) {
                    myBookings = result.bookings;
                    updateStatusCounts();
                    displayBookings(myBookings);
                } else {
                    showToast('Failed to load bookings', 'error');
                }
            } catch (error) {
                console.error('Error loading bookings:', error);
                showToast('Error loading bookings', 'error');
            }
        }

        function updateStatusCounts() {
            const counts = {
                pending: 0,
                approved: 0,
                rejected: 0,
                cancelled: 0
            };

            myBookings.forEach(booking => {
                if (counts.hasOwnProperty(booking.status)) {
                    counts[booking.status]++;
                }
            });

            document.getElementById('pendingCount').textContent = counts.pending;
            document.getElementById('approvedCount').textContent = counts.approved;
            document.getElementById('rejectedCount').textContent = counts.rejected;
            document.getElementById('cancelledCount').textContent = counts.cancelled;
        }

        function displayBookings(bookings) {
            const container = document.getElementById('bookingsContainer');
            
            if (bookings.length === 0) {
                container.innerHTML = `
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-times fa-3x" style="color: var(--text-muted); margin-bottom: 1rem;"></i>
                            <h3>No bookings found</h3>
                            <p>You haven't made any bookings yet.</p>
                            <a href="book-resource.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create Your First Booking
                            </a>
                        </div>
                    </div>
                `;
                return;
            }

            container.innerHTML = bookings.map(booking => {
                const statusClass = `badge-${booking.status}`;
                const isPending = booking.status === 'pending';
                const isUpcoming = new Date(booking.booking_date) >= new Date();
                
                let statusIcon = 'fa-clock';
                if (booking.status === 'approved') statusIcon = 'fa-check-circle';
                else if (booking.status === 'rejected') statusIcon = 'fa-times-circle';
                else if (booking.status === 'cancelled') statusIcon = 'fa-ban';

                return `
                    <div class="card booking-card">
                        <div class="card-body">
                            <div class="booking-card-header">
                                <div>
                                    <h3>${booking.resource_name}</h3>
                                    <p class="text-muted">
                                        <i class="fas fa-tag"></i> ${booking.resource_type}
                                        ${booking.location ? ` | <i class="fas fa-map-marker-alt"></i> ${booking.location}` : ''}
                                    </p>
                                </div>
                                <span class="badge ${statusClass}">
                                    <i class="fas ${statusIcon}"></i> ${booking.status}
                                </span>
                            </div>

                            <div class="booking-card-info">
                                <div class="info-item">
                                    <i class="fas fa-calendar"></i>
                                    <div>
                                        <strong>Date</strong>
                                        <p>${formatDate(booking.booking_date)}</p>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-clock"></i>
                                    <div>
                                        <strong>Time</strong>
                                        <p>${booking.start_time} - ${booking.end_time}</p>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-clipboard"></i>
                                    <div>
                                        <strong>Purpose</strong>
                                        <p>${booking.purpose || 'Not specified'}</p>
                                    </div>
                                </div>
                            </div>

                            ${booking.remarks ? `
                                <div class="booking-remarks">
                                    <strong><i class="fas fa-comment"></i> Admin Remarks:</strong>
                                    <p>${booking.remarks}</p>
                                </div>
                            ` : ''}

                            <div class="booking-card-actions">
                                <button class="btn btn-secondary btn-sm" onclick="viewBooking(${booking.id})">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                ${isPending && isUpcoming ? `
                                    <button class="btn btn-danger btn-sm" onclick="openCancelModal(${booking.id})">
                                        <i class="fas fa-times"></i> Cancel Booking
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function applyFilters() {
            const status = document.getElementById('filterStatus').value;
            const period = document.getElementById('filterPeriod').value;
            const type = document.getElementById('filterType').value;

            let filtered = myBookings;

            if (status) {
                filtered = filtered.filter(b => b.status === status);
            }

            if (type) {
                filtered = filtered.filter(b => b.resource_type === type);
            }

            if (period) {
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                filtered = filtered.filter(b => {
                    const bookingDate = new Date(b.booking_date);
                    bookingDate.setHours(0, 0, 0, 0);

                    switch(period) {
                        case 'upcoming':
                            return bookingDate >= today;
                        case 'past':
                            return bookingDate < today;
                        case 'today':
                            return bookingDate.getTime() === today.getTime();
                        case 'this-week':
                            const weekEnd = new Date(today);
                            weekEnd.setDate(weekEnd.getDate() + 7);
                            return bookingDate >= today && bookingDate <= weekEnd;
                        case 'this-month':
                            return bookingDate.getMonth() === today.getMonth() && 
                                   bookingDate.getFullYear() === today.getFullYear();
                        default:
                            return true;
                    }
                });
            }

            displayBookings(filtered);
        }

        function openCancelModal(bookingId) {
            currentCancelId = bookingId;
            document.getElementById('cancelReason').value = '';
            document.getElementById('cancelModal').style.display = 'block';
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
            currentCancelId = null;
        }

        async function confirmCancel() {
            const reason = document.getElementById('cancelReason').value;

            try {
                const response = await fetch('api/bookings.php', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: currentCancelId,
                        status: 'cancelled',
                        remarks: reason || 'Cancelled by user'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showToast('Booking cancelled successfully', 'success');
                    closeCancelModal();
                    loadMyBookings();
                } else {
                    showToast(result.message || 'Failed to cancel booking', 'error');
                }
            } catch (error) {
                console.error('Error cancelling booking:', error);
                showToast('Error cancelling booking', 'error');
            }
        }

        async function viewBooking(bookingId) {
            const booking = myBookings.find(b => b.id === bookingId);
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
                        <div class="detail-item">
                            <span class="detail-label">Created:</span>
                            <span class="detail-value">${formatDateTime(booking.created_at)}</span>
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
            return date.toLocaleDateString('en-US', { 
                weekday: 'long',
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function showToast(message, type = 'info') {
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
            const cancelModal = document.getElementById('cancelModal');
            const viewModal = document.getElementById('viewModal');
            
            if (event.target === cancelModal) {
                closeCancelModal();
            }
            if (event.target === viewModal) {
                closeViewModal();
            }
        }
    </script>
</body>
</html>
