<?php
require_once 'config/auth.php';
requireLogin();

$db = getDBConnection();

// Get resources for filter
$stmt = $db->query("SELECT id, name FROM resources ORDER BY name");
$resources = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Resource Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
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
                        <span class="current">Calendar</span>
                    </div>
                </div>
                
                <div class="header-right">
                    <select id="resourceFilter" class="form-control" style="width: auto; min-width: 200px;">
                        <option value="">All Resources</option>
                        <?php foreach ($resources as $resource): ?>
                        <option value="<?php echo $resource['id']; ?>"><?php echo htmlspecialchars($resource['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <a href="book-resource.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        <span>New Booking</span>
                    </a>
                </div>
            </header>
            
            <div class="page-content">
                <div class="page-header">
                    <h1 class="page-title">Booking Calendar</h1>
                    <p class="page-subtitle">View all bookings in calendar format</p>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex gap-3 mb-4">
                            <div class="d-flex align-items-center gap-2">
                                <span style="width: 12px; height: 12px; background: #f59e0b; border-radius: 2px;"></span>
                                <span style="font-size: 0.8125rem;">Pending</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span style="width: 12px; height: 12px; background: #10b981; border-radius: 2px;"></span>
                                <span style="font-size: 0.8125rem;">Approved</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span style="width: 12px; height: 12px; background: #ef4444; border-radius: 2px;"></span>
                                <span style="font-size: 0.8125rem;">Rejected</span>
                            </div>
                        </div>
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Event Details Modal -->
    <div class="modal-overlay" id="eventModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="eventTitle">Booking Details</h3>
                <button class="modal-close" onclick="closeModal('eventModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="eventDetails">
                <!-- Event details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('eventModal')">Close</button>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        let calendar;
        
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: function(info, successCallback, failureCallback) {
                    const resourceId = document.getElementById('resourceFilter').value;
                    let url = `api/bookings.php?action=calendar&start=${info.startStr}&end=${info.endStr}`;
                    if (resourceId) url += `&resource_id=${resourceId}`;
                    
                    fetch(url)
                        .then(response => response.json())
                        .then(data => successCallback(data))
                        .catch(error => failureCallback(error));
                },
                eventClick: function(info) {
                    showEventDetails(info.event);
                },
                height: 'auto'
            });
            
            calendar.render();
            
            // Resource filter change
            document.getElementById('resourceFilter').addEventListener('change', function() {
                calendar.refetchEvents();
            });
        });
        
        function showEventDetails(event) {
            document.getElementById('eventTitle').textContent = event.title;
            
            const props = event.extendedProps;
            document.getElementById('eventDetails').innerHTML = `
                <div class="mb-3">
                    <strong>Resource:</strong> ${props.resource_name}
                </div>
                <div class="mb-3">
                    <strong>Booked By:</strong> ${props.user_name}
                </div>
                <div class="mb-3">
                    <strong>Time:</strong><br>
                    ${new Date(event.start).toLocaleString()} - ${new Date(event.end).toLocaleString()}
                </div>
                <div class="mb-3">
                    <strong>Status:</strong> 
                    <span class="status-badge ${props.status}">${props.status.charAt(0).toUpperCase() + props.status.slice(1)}</span>
                </div>
                ${props.description ? `<div class="mb-3"><strong>Description:</strong><br>${props.description}</div>` : ''}
            `;
            
            openModal('eventModal');
        }
    </script>
</body>
</html>
