<?php
include 'db_controller.php';
$conn->select_db("atharv");
session_start();
include 'logged_user.php';

// Handle Sign Up form submission
$signup_message = '';
$signup_status = '';
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['event_id'])) {
    $event_id = $_POST['event_id'];
    $participant_email = $_SESSION['logged_account']['email'];

    // Get event details
    $event_stmt = $conn->prepare("SELECT * FROM event_table WHERE id = ?");
    $event_stmt->bind_param("i", $event_id);
    $event_stmt->execute();
    $event = $event_stmt->get_result()->fetch_assoc();
    $event_stmt->close();

    // Check if already registered
    $check_stmt = $conn->prepare("SELECT * FROM event_registration_table WHERE event_id = ? AND participant_email = ?");
    $check_stmt->bind_param("is", $event_id, $participant_email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $signup_message = "You're already signed up for this event!";
        $signup_status = "error";
    } else {
        // Register the user
        $stmt = $conn->prepare("INSERT INTO event_registration_table (event_id, participant_email) VALUES (?, ?)");
        $stmt->bind_param("is", $event_id, $participant_email);
        
        if ($stmt->execute()) {
            $signup_message = "You have successfully signed up for '".$event['title']."'!";
            $signup_status = "success";
            
            // Create immediate notification
            $notification_message = "You signed up for: " . $event['title'] . " (".date('M j, Y', strtotime($event['event_date'])).")";
            $notification_link = "view_events.php";
            
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_email, message, link) VALUES (?, ?, ?)");
            $notif_stmt->bind_param("sss", $participant_email, $notification_message, $notification_link);
            $notif_stmt->execute();
            $notif_stmt->close();
            
            // Create reminder notification if event is upcoming
            $event_date = new DateTime($event['event_date']);
            $today = new DateTime();
            
            if ($event_date > $today) {
                // Reminder 1 day before
                $reminder_date = (clone $event_date)->modify('-1 day')->format('Y-m-d H:i:s');
                $reminder_message = "Reminder: " . $event['title'] . " is tomorrow!";
                
                $reminder_stmt = $conn->prepare("INSERT INTO notifications (user_email, message, link, created_at) VALUES (?, ?, ?, ?)");
                $reminder_stmt->bind_param("ssss", $participant_email, $reminder_message, $notification_link, $reminder_date);
                $reminder_stmt->execute();
                $reminder_stmt->close();
                
                // Additional reminder 1 hour before (optional)
                $reminder_date = (clone $event_date)->modify('-1 hour')->format('Y-m-d H:i:s');
                $reminder_message = "Starts soon: " . $event['title'] . " at ".date('g:i a', strtotime($event['event_date']));
                
                $reminder_stmt = $conn->prepare("INSERT INTO notifications (user_email, message, link, created_at) VALUES (?, ?, ?, ?)");
                $reminder_stmt->bind_param("ssss", $participant_email, $reminder_message, $notification_link, $reminder_date);
                $reminder_stmt->execute();
                $reminder_stmt->close();
            }
        } else {
            $signup_message = "Error signing up for the event: " . $stmt->error;
            $signup_status = "error";
        }
        $stmt->close();
    }
    $check_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events/News</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        /* Navbar Styles */
        .navbar {
            background: linear-gradient(135deg, #4361ee, #3f37c9);
            box-shadow: 0 4px 20px rgba(67, 97, 238, 0.15);
            padding: 1rem 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: white;
            font-size: 1.5rem;
        }
        
        .nav-link {
            font-weight: 500;
            color: rgba(255,255,255,0.8);
            padding: 0.5rem 1rem;
            margin: 0 0.2rem;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .nav-main-active {
            color: white !important;
            background: rgba(255,255,255,0.2) !important;
        }
        
        .nav-bi {
            font-size: 1.2rem;
        }
        
        .navbar-toggler {
            border: none;
            color: rgba(255,255,255,0.8);
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* Card Styles */
        .card {
            width: 100%;
            border: none;
            box-shadow: 0 2px 2px rgba(0,0,0,.08), 0 0 6px rgba(0,0,0,.05);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .event-image {
            height: 200px;
            object-fit: cover;
        }
        .signup-btn {
            margin: 15px;
        }
        .upcoming-badge {
            background-color: #0d6efd;
        }
        .past-badge {
            background-color: #6c757d;
        }
        
        /* Notification Bell */
        .notification-badge {
            font-size: 0.6rem;
            top: 5px;
            right: 5px;
        }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="main_menu.php">MIT Alumni</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="main_menu.php"><i class="bi bi-house-door nav-bi"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_alumni.php"><i class="bi bi-people nav-bi"></i> Alumni</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-main-active" href="view_events.php"><i class="bi bi-calendar-event-fill nav-bi"></i> Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_advertisements.php"><i class="bi bi-megaphone nav-bi"></i> Opportunities</a>
                    </li>
                </ul>
                
                <!-- Right-aligned items -->
                <ul class="navbar-nav">
                    <!-- Notification Bell -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell-fill nav-bi"></i>
                            <?php
                            $unreadCount = $conn->query("SELECT COUNT(*) as count FROM notifications 
                                                        WHERE user_email = '{$_SESSION['logged_account']['email']}' AND is_read = FALSE")
                                                ->fetch_assoc()['count'];
                            if($unreadCount > 0): ?>
                                <span class="position-absolute notification-badge badge rounded-pill bg-danger">
                                    <?= $unreadCount ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                            <?php
                            $notifications = $conn->query("SELECT * FROM notifications 
                                                           WHERE user_email = '{$_SESSION['logged_account']['email']}'
                                                           ORDER BY created_at DESC LIMIT 5");
                            while($note = $notifications->fetch_assoc()): ?>
                                <li>
                                    <a class="dropdown-item <?= $note['is_read'] ? '' : 'fw-bold' ?>" href="<?= $note['link'] ?>">
                                        <?= htmlspecialchars($note['message']) ?>
                                        <small class="text-muted"><?= $note['created_at'] ?></small>
                                    </a>
                                </li>
                            <?php endwhile; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="view_notifications.php">View All Notifications</a></li>
                        </ul>
                    </li>
                    
                    <!-- User Profile Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle nav-bi"></i> 
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="update_profile.php?email=<?= htmlspecialchars($_SESSION['logged_account']['email']) ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb -->
    <div class="container my-3">
        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a class="breadcrumb-link text-secondary link-underline link-underline-opacity-0" href="main_menu.php">Home</a></li>
                <li class="breadcrumb-item breadcrumb-active" aria-current="page">Events/News</li>
            </ol>
        </nav>
    </div>

    <div class="container mb-5">
        <h1 class="<?php echo (isset($_GET['filterType']) || isset($_GET['filterTime']) || isset($_GET['search'])) ? NULL : 'slide-left' ?>">Events/News</h1>
        
        <!-- Sign Up Status Message -->
        <?php if (!empty($signup_message)) : ?>
            <div class="alert alert-<?php echo $signup_status === 'success' ? 'success' : 'danger'; ?> animate__animated animate__fadeIn">
                <?= $signup_message ?>
                <button type="button" class="btn-close float-end" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Filter -->
        <div class="container mt-3 py-3 px-4 card bg-white fw-medium <?php echo (isset($_GET['filterType']) || isset($_GET['filterTime']) || isset($_GET['search'])) ? NULL : 'slide-left' ?>">
            <form id="eventsFilterForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="GET">
                <!-- Type (All, Events, News) -->
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="filterType" id="filterType1" value="All" <?php echo (isset($_GET['filterType']) && $_GET['filterType'] == 'All') ? 'checked' : ((!isset($_GET['filterTime'])) ? 'checked' : NULL ) ?>>
                    <label class="form-check-label" for="filterType1">All</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="filterType" id="filterType2" value="Event" <?php echo (isset($_GET['filterType']) && $_GET['filterType'] == 'Event') ? 'checked' : NULL ?>>
                    <label class="form-check-label badge text-bg-success" for="filterType2">Events</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="filterType" id="filterType3" value="News" <?php echo (isset($_GET['filterType']) && $_GET['filterType'] == 'News') ? 'checked' : NULL ?>>
                    <label class="form-check-label badge text-bg-warning" for="filterType3">News</label>
                </div>

                <!-- Time (All, Past, Upcoming) -->
                <div class="form-check-inline ms-4">
                    <div class="input-group">
                        <label class="input-group-text" for="filterTime"><i class="bi bi-clock-history" style="-webkit-text-stroke: 0.25px;"></i></label>
                        <select class="form-select fw-medium" id="filterTime" name="filterTime" aria-label="Time filter">
                            <option value="All" class="fw-medium" <?php echo (isset($_GET['filterTime']) && $_GET['filterTime'] == 'All') ? 'selected' : NULL ?>>All</option>
                            <option value="Past" class="fw-medium" <?php echo (isset($_GET['filterTime']) && $_GET['filterTime'] == 'Past') ? 'selected' : NULL ?>>Past</option>
                            <option value="Upcoming" class="fw-medium" <?php echo (isset($_GET['filterTime']) && $_GET['filterTime'] == 'Upcoming') ? 'selected' : NULL ?>>Upcoming</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary fw-medium mb-1">Display List</button>
                <a href="add_event_user.php" class="btn btn-success fw-medium mb-1"><i class="bi bi-plus-circle me-1"></i>Add Event</a>

                <!-- Search Box -->
                <div class="form-check-inline me-0 float-end">
                    <div class="input-group">
                        <input type="text" class="form-control py-2" placeholder="Search events" name="search" aria-label="Search" aria-describedby="button-addon2" value="<?php echo (isset($_GET['search'])) ? trim($_GET['search']) : NULL; ?>">
                        <button class="btn btn-primary px-3 py-2" type="submit" id="button-addon2"><i class="bi bi-search"></i></button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Events/News -->
        <div class="row row-cols-1 mt-4 px-0 mx-0">
            <?php 
                $filterType = "";
                $filterTime = "";
                $filterSearch = "";

                // Type filter for Events/News
                if (isset($_GET['filterType']) && $_GET['filterType'] != 'All')
                    $filterType = "type = '" . $_GET['filterType'] . "'";

                // Time filter for Events/News
                if (isset($_GET['filterTime']) && $_GET['filterTime'] != 'All') {
                    date_default_timezone_set('Asia/Kuching');
                    $todayDate = date('Y-m-d');
                    if ($_GET['filterTime'] == 'Upcoming')
                        $filterTime = "event_date >= '" . $todayDate . "'";
                    elseif ($_GET['filterTime'] == 'Past')
                        $filterTime = "event_date < '" . $todayDate . "'";
                }

                // Search filter for Events/News
                if (isset($_GET['search']) && $_GET['search'] != "") {
                    $trimSearch = strtolower(trim($_GET['search']));
                    $filterSearch = "(
                        LOWER(title) LIKE '%$trimSearch%' OR 
                        LOWER(location) LIKE '%$trimSearch%' OR 
                        LOWER(description) LIKE '%$trimSearch%' OR 
                        LOWER(event_date) LIKE '%$trimSearch%' OR 
                        DATE_FORMAT(event_date, '%a, %e %b %Y') LIKE '%$trimSearch%' OR 
                        LOWER(type) LIKE '%$trimSearch%'
                        )";
                }

                // Puts WHERE and AND to the query appropriately
                $conditions = array_filter([$filterType, $filterTime, $filterSearch]);
                if (!empty($conditions))
                    $whereClause = "WHERE " . implode(" AND ", $conditions);
                else
                    $whereClause = "";

                // Query the database with the WHERE from previous
                $allEventsNews = $conn->query("SELECT e.*, 
                                              (SELECT COUNT(*) FROM event_registration_table 
                                               WHERE event_id = e.id AND participant_email = '{$_SESSION['logged_account']['email']}') as is_registered
                                              FROM event_table e $whereClause ORDER BY e.id DESC");

                // Load the events/news if there's at least 1
                if ($allEventsNews && $allEventsNews->num_rows > 0) {
                    while ($eventsNews = $allEventsNews->fetch_assoc()) {
                        // Format date
                        $date = new DateTime($eventsNews['event_date']);
                        $formattedDate = strtoupper($date->format('D, j M Y'));
                        $isUpcoming = $date > new DateTime();
            ?>
                <div class="col mb-4 px-0 mx-0 animate__animated animate__fadeIn">
                    <div class="card">
                        <div class="row">
                            <div class="col-auto">
                                <div class="image-container-events">
                                    <img src="images/<?php echo $eventsNews['photo']; ?>" class="img-fluid event-image" alt="event_image">
                                </div>
                            </div>
                            <div class="col d-flex flex-column">
                                <div class="card-body px-2 flex-grow-1 me-4">
                                    <?php 
                                        echo "<span class='fw-medium fs-6'>".$formattedDate."</span>". 
                                             (($eventsNews['type'] == 'Event') ? 
                                             "<span class='badge text-bg-success mt-1 float-end'>Events</span>" : 
                                             "<span class='badge text-bg-warning mt-1 float-end'>News</span>")."
                                            <br/>
                                            <span class='card-title h3'>".$eventsNews['title']."</span>
                                            <br/>
                                            <span class='card-text fw-medium text-secondary'>".$eventsNews['location']."</span>
                                            <br/><br/>
                                            <span class='card-text'>".$eventsNews['description']."</span>";
                                    ?>
                                </div>
                                <?php if ($eventsNews['type'] == 'Event') { ?>
                                    <div class="signup-btn">
                                        <?php if ($eventsNews['is_registered']): ?>
                                            <button class="btn btn-success" disabled>
                                                <i class="bi bi-check-circle"></i> Signed Up
                                            </button>
                                            <?php if ($isUpcoming): ?>
                                                <span class="badge upcoming-badge ms-2">Upcoming</span>
                                            <?php else: ?>
                                                <span class="badge past-badge ms-2">Past Event</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#signupModal" data-id="<?= $eventsNews['id'] ?>" data-title="<?= htmlspecialchars($eventsNews['title']) ?>">
                                                <i class="bi bi-person-plus"></i> Sign Up
                                            </button>
                                            <?php if ($isUpcoming): ?>
                                                <span class="badge upcoming-badge ms-2">Upcoming</span>
                                            <?php else: ?>
                                                <span class="badge past-badge ms-2">Past Event</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php 
                    }
                // No result from filter
                } elseif (isset($_GET['filterType']) || isset($_GET['filterTime']) || isset($_GET['search']) && $_GET['search'] != "") { ?>
                    <div class="text-center slide-left">
                        <div class="row align-items-center ps-2 py-2">
                            <div class="col-12"><h5 class="fw-bold text-secondary">No events/news available from your filter</h5></div>
                        </div>
                    </div>
                <!-- Simply no result -->
                <?php } else { ?>
                    <div class="text-center slide-left">
                        <div class="row align-items-center ps-2 py-2">
                            <div class="col-12"><h5 class="fw-bold text-secondary">No events/news available</h5></div>
                        </div>
                    </div>
            <?php } 
                $conn->close();
            ?>
        </div>
    </div>

    <!-- Sign Up Modal -->
    <div class="modal fade" id="signupModal" tabindex="-1" aria-labelledby="signupModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
          <div class="modal-header">
            <h5 class="modal-title" id="signupModalLabel">Sign Up for Event</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="event_id" id="eventId">
            <p>You are signing up with your account email: <strong><?php echo htmlspecialchars($_SESSION['logged_account']['email']); ?></strong></p>
            <p>Click "Confirm Sign Up" to register for this event.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Confirm Sign Up</button>
          </div>
        </form>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Sign Up Modal
        var signupModal = document.getElementById('signupModal');
        signupModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var eventId = button.getAttribute('data-id');
            var modalTitle = signupModal.querySelector('.modal-title');
            var inputEventId = signupModal.querySelector('#eventId');

            modalTitle.textContent = 'Sign Up for: ' + button.getAttribute('data-title');
            inputEventId.value = eventId;
        });

        // Auto-close alerts after 5 seconds
        window.setTimeout(function() {
            $(".alert").fadeTo(500, 0).slideUp(500, function(){
                $(this).remove(); 
            });
        }, 5000);
    </script>
</body>
</html>