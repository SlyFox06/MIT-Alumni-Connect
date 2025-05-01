<?php
include 'db_controller.php';
$conn->select_db("atharv");
session_start();
include 'logged_user.php';

// Mark all notifications as read when viewing all
$conn->query("UPDATE notifications SET is_read = TRUE WHERE user_email = '{$_SESSION['logged_account']['email']}'");

// Get all notifications
$notifications = $conn->query("SELECT * FROM notifications 
                              WHERE user_email = '{$_SESSION['logged_account']['email']}'
                              ORDER BY created_at DESC");

// Get events user has signed up for
$signedUpEvents = $conn->query("SELECT e.* FROM event_table e
                               JOIN event_registration_table r ON e.id = r.event_id
                               WHERE r.participant_email = '{$_SESSION['logged_account']['email']}'
                               ORDER BY e.event_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | MIT Alumni Portal</title>
    <link rel="stylesheet" href="css/styles.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --accent-color: #2e59d9;
        }
        
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand span {
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .card {
            width: 100%;
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .notification-card {
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            border-left: 4px solid transparent;
        }
        
        .notification-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }
        
        .unread {
            background-color: rgba(78, 115, 223, 0.05);
            border-left: 4px solid var(--primary-color);
        }
        
        .unread .card-title {
            font-weight: 600;
        }
        
        .event-card {
            border-left: 4px solid var(--primary-color);
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .nav-main-active {
            color: var(--primary-color) !important;
            font-weight: 500;
        }
        
        .nav-bi {
            font-size: 1.2rem;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 20px;
            height: 20px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-notification {
            transition: all 0.3s;
            letter-spacing: 0.5px;
        }
        
        .btn-notification:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .empty-state {
            transition: all 0.3s;
        }
        
        .empty-state:hover {
            transform: scale(1.02);
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .bounce-in {
            animation: bounceIn 0.6s;
        }
        
        @keyframes bounceIn {
            0% { transform: scale(0.8); opacity: 0; }
            50% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>
<body>
   <!-- Navigation Bar -->
   <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="#">
                <span>MIT</span> atharv
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="view_alumni.php"><i class="bi bi-people nav-bi"></i> Alumni</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_events.php"><i class="bi bi-calendar-event me-1"></i> Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_advertisements.php"><i class="bi bi-briefcase me-1"></i> Opportunities</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_gallery.php"><i class="bi bi-images me-1"></i> Gallery</a>
                    </li>
                    <li class="nav-item position-relative">
                        <a class="nav-link nav-main-active" href="notifications.php"><i class="bi bi-bell me-1"></i> Notifications</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_profile.php?email=<?php echo htmlspecialchars($email); ?>"><i class="bi bi-person me-1"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row">
            <div class="col-md-8">
                <h2 class="mb-4 animate__animated animate__fadeInDown">Your Notifications</h2>
                
                <?php if($notifications->num_rows > 0): ?>
                    <div class="notification-list">
                        <?php while($note = $notifications->fetch_assoc()): ?>
                            <div class="card notification-card mb-3 animate__animated animate__fadeIn <?= $note['is_read'] ? '' : 'unread' ?>"
                                 style="animation-delay: <?= $i * 0.1 ?>s">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <h5 class="card-title"><?= htmlspecialchars($note['message']) ?></h5>
                                        <span class="notification-time"><?= $note['created_at'] ?></span>
                                    </div>
                                    <a href="<?= htmlspecialchars($note['link']) ?>" class="btn btn-sm btn-outline-primary mt-2 btn-notification">
                                        <i class="bi bi-arrow-right-circle me-1"></i> View Details
                                    </a>
                                </div>
                            </div>
                        <?php $i++; endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="card empty-state animate__animated animate__pulse">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-bell-slash-fill" style="font-size: 3rem; color: #adb5bd;"></i>
                            <h5 class="card-title mt-4">No notifications yet</h5>
                            <p class="card-text text-muted">When you have notifications, they'll appear here</p>
                            <a href="view_events.php" class="btn btn-primary mt-3 btn-notification">
                                <i class="bi bi-calendar-event me-1"></i> Browse Events
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4">
                <div class="card animate__animated animate__fadeInRight">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i> Your Upcoming Events</h5>
                    </div>
                    <div class="card-body">
                        <?php if($signedUpEvents->num_rows > 0): 
                            $hasUpcoming = false;
                            while($event = $signedUpEvents->fetch_assoc()): 
                                $eventDate = new DateTime($event['event_date']);
                                $isUpcoming = $eventDate > new DateTime();
                                if($isUpcoming):
                                    $hasUpcoming = true;
                        ?>
                                <div class="card event-card mb-3 animate__animated animate__fadeIn">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2"><?= htmlspecialchars($event['title']) ?></h6>
                                        <p class="card-text text-muted mb-1">
                                            <i class="bi bi-calendar"></i> <?= $eventDate->format('M j, Y') ?>
                                        </p>
                                        <p class="card-text text-muted">
                                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['location']) ?>
                                        </p>
                                        <a href="view_events.php" class="btn btn-sm btn-primary btn-notification">
                                            <i class="bi bi-eye me-1"></i> View Event
                                        </a>
                                    </div>
                                </div>
                            <?php endif; endwhile; 
                            if(!$hasUpcoming): ?>
                                <div class="text-center py-4 animate__animated animate__fadeIn">
                                    <i class="bi bi-calendar-x" style="font-size: 2rem; color: #adb5bd;"></i>
                                    <p class="mt-3 mb-0 text-muted">No upcoming events</p>
                                    <a href="view_events.php" class="btn btn-sm btn-outline-primary mt-3">
                                        Find Events
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-4 animate__animated animate__fadeIn">
                                <i class="bi bi-calendar-x" style="font-size: 2rem; color: #adb5bd;"></i>
                                <p class="mt-3 mb-0 text-muted">No upcoming events</p>
                                <a href="view_events.php" class="btn btn-sm btn-outline-primary mt-3">
                                    Find Events
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add animation to notification cards on hover
        document.addEventListener('DOMContentLoaded', function() {
            const notificationCards = document.querySelectorAll('.notification-card');
            notificationCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.12)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '0 4px 20px rgba(0,0,0,0.08)';
                });
            });
            
            // Add pulse animation to empty state if present
            const emptyState = document.querySelector('.empty-state');
            if (emptyState) {
                setInterval(() => {
                    emptyState.classList.add('animate__pulse');
                    setTimeout(() => {
                        emptyState.classList.remove('animate__pulse');
                    }, 1000);
                }, 5000);
            }
            
            // Mark notification as read when clicked
            const unreadNotifications = document.querySelectorAll('.unread');
            unreadNotifications.forEach(notification => {
                notification.addEventListener('click', function() {
                    this.classList.remove('unread');
                });
            });
        });
    </script>
</body>
</html>