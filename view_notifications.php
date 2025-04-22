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
    <style>
        .card {
            width: 100%;
            border: none;
            box-shadow: 0 4px 4px rgba(0,0,0,.2);
            margin-bottom: 20px;
        }
        .notification-card {
            transition: transform 0.2s;
        }
        .notification-card:hover {
            transform: translateY(-3px);
        }
        .event-card {
            border-left: 4px solid #0d6efd;
        }
        .unread {
            background-color: #f8f9fa;
        }
        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .nav-main-active {
            color: #0d6efd !important;
            font-weight: 500;
        }
        .nav-bi {
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
   <!-- Navigation Bar -->
   <nav class="navbar navbar-expand-lg navbar-light bg-light">
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
                    <li class="nav-item">
                        <a class="nav-link" href="update_profile.php?email=<?php echo htmlspecialchars($email); ?>"><i class="bi bi-person me-1"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row">
            <div class="col-md-8">
                <h2 class="mb-4">Your Notifications</h2>
                
                <?php if($notifications->num_rows > 0): ?>
                    <div class="notification-list">
                        <?php while($note = $notifications->fetch_assoc()): ?>
                            <div class="card notification-card mb-3 <?= $note['is_read'] ? '' : 'unread' ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <h5 class="card-title"><?= htmlspecialchars($note['message']) ?></h5>
                                        <span class="notification-time"><?= $note['created_at'] ?></span>
                                    </div>
                                    <a href="<?= htmlspecialchars($note['link']) ?>" class="btn btn-sm btn-outline-primary mt-2">View Details</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="bi bi-bell-slash" style="font-size: 2rem; color: #6c757d;"></i>
                            <h5 class="card-title mt-3">No notifications yet</h5>
                            <p class="card-text">When you have notifications, they'll appear here</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Your Upcoming Events</h5>
                    </div>
                    <div class="card-body">
                        <?php if($signedUpEvents->num_rows > 0): ?>
                            <?php while($event = $signedUpEvents->fetch_assoc()): 
                                $eventDate = new DateTime($event['event_date']);
                                $isUpcoming = $eventDate > new DateTime();
                                if($isUpcoming):
                            ?>
                                <div class="card event-card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2"><?= htmlspecialchars($event['title']) ?></h6>
                                        <p class="card-text text-muted mb-1">
                                            <i class="bi bi-calendar"></i> <?= $eventDate->format('M j, Y') ?>
                                        </p>
                                        <p class="card-text text-muted">
                                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['location']) ?>
                                        </p>
                                        <a href="view_events.php" class="btn btn-sm btn-primary">View Event</a>
                                    </div>
                                </div>
                            <?php endif; endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-calendar-x" style="font-size: 1.5rem; color: #6c757d;"></i>
                                <p class="mt-2 mb-0">No upcoming events</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>