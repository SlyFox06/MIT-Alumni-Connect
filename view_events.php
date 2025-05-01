<?php
include 'db_controller.php';
$conn->select_db("atharv");

session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_account'])) {
    header('Location: login.php');
    exit();
}

// Get user role safely
$userRole = $_SESSION['logged_account']['role'] ?? 'Guest';

// Flash message handling
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    $flash_mode = $_SESSION['flash_mode'] ?? 'alert-info';
    unset($_SESSION['flash']);
    unset($_SESSION['flash_mode']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['event_id'])) {
    $eventId = $_POST['event_id'];
    $responses = $_POST['custom_response'] ?? [];
    $userEmail = $_SESSION['logged_account']['email'];
    
    try {
        // Check if already registered
        $checkStmt = $conn->prepare("SELECT * FROM event_registration_table WHERE event_id = ? AND participant_email = ?");
        $checkStmt->bind_param("is", $eventId, $userEmail);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['flash'] = "You are already registered for this event!";
            $_SESSION['flash_mode'] = "alert-warning";
        } else {
            // Insert registration into database
            $stmt = $conn->prepare("INSERT INTO event_registration_table (event_id, participant_email) VALUES (?, ?)");
            $stmt->bind_param("is", $eventId, $userEmail);
            $stmt->execute();
            
            $_SESSION['flash'] = "Successfully registered for the event!";
            $_SESSION['flash_mode'] = "alert-success";
            
            // Create notification
            $eventInfo = $conn->query("SELECT title, event_date FROM event_table WHERE id = $eventId")->fetch_assoc();
            $message = "You signed up for: ".$eventInfo['title']." (".date('M j, Y', strtotime($eventInfo['event_date'])).")";
            $conn->query("INSERT INTO notifications (user_email, message, link) VALUES ('$userEmail', '$message', 'view_events.php')");
            
            // Create reminder notifications
            $reminderDate = date('Y-m-d', strtotime($eventInfo['event_date'] . ' -1 day'));
            $conn->query("INSERT INTO notifications (user_email, message, link, created_at) VALUES (
                '$userEmail', 
                'Reminder: ".$eventInfo['title']." is tomorrow!', 
                'view_events.php',
                '$reminderDate 18:30:00'
            )");
        }
        
        header("Location: view_events.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['flash'] = "Error registering for event: " . $e->getMessage();
        $_SESSION['flash_mode'] = "alert-danger";
    }
}

// Apply filters
$filterType = $_GET['filterType'] ?? 'All';
$filterTime = $_GET['filterTime'] ?? 'All';
$search = trim($_GET['search'] ?? '');

// Build query conditions
$conditions = [];
$params = [];
$types = '';

if ($filterType != 'All') {
    $conditions[] = "type = ?";
    $params[] = $filterType;
    $types .= 's';
}

if ($filterTime != 'All') {
    date_default_timezone_set('Asia/Kuching');
    $todayDate = date('Y-m-d');
    if ($filterTime == 'Upcoming') {
        $conditions[] = "event_date >= ?";
        $params[] = $todayDate;
        $types .= 's';
    } elseif ($filterTime == 'Past') {
        $conditions[] = "event_date < ?";
        $params[] = $todayDate;
        $types .= 's';
    }
}

if (!empty($search)) {
    $searchTerm = "%$search%";
    $conditions[] = "(LOWER(title) LIKE ? OR LOWER(location) LIKE ? OR LOWER(description) LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

// Build final query
$whereClause = empty($conditions) ? "" : "WHERE " . implode(" AND ", $conditions);
$query = "SELECT * FROM event_table $whereClause ORDER BY event_date DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$events = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Events</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Add Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <!-- Add AOS (Animate On Scroll) -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4f6cff;
            --secondary: #3f37c9;
            --accent: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7ff;
            color: var(--dark);
            padding-top: 70px;
        }
        
        /* Navbar with animation */
        .navbar {
            background-color: #343a40 !important;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar.scrolled {
            padding: 0.5rem 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .nav-bi {
            -webkit-text-stroke: 0.5px;
        }
        
        .nav-main-active {
            font-weight: 500;
            color: white !important;
            position: relative;
        }
        
        .nav-main-active::after {
            content: '';
            position: absolute;
            width: 70%;
            height: 2px;
            background: white;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            animation: navUnderline 0.5s ease forwards;
        }
        
        @keyframes navUnderline {
            from { width: 0; }
            to { width: 70%; }
        }
        
        /* Event cards with enhanced animations */
        .event-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transform: perspective(1000px) rotateX(0deg) rotateY(0deg);
            position: relative;
        }
        
        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.1) 0%, rgba(76, 201, 240, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }
        
        .event-card:hover {
            transform: translateY(-8px) perspective(1000px) rotateX(2deg) rotateY(2deg);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .event-card:hover::before {
            opacity: 1;
        }
        
        .event-card:hover .card-title {
            color: var(--primary);
        }
        
        .card-img-top {
            height: 200px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .event-card:hover .card-img-top {
            transform: scale(1.05);
        }
        
        /* Button animations */
        .btn-primary {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.4);
        }
        
        .btn-primary::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%, -50%);
            transform-origin: 50% 50%;
        }
        
        .btn-primary:focus:not(:active)::after {
            animation: ripple 0.6s ease-out;
        }
        
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(20, 20);
                opacity: 0;
            }
        }
        
        /* Badge animations */
        .badge {
            transition: all 0.3s ease;
        }
        
        .badge:hover {
            transform: scale(1.05);
        }
        
        /* Notification badge animation */
        .notification-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* Registration form animation */
        .registration-form {
            display: none;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-top: 15px;
            animation: fadeIn 0.3s ease-out forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Floating animation for featured events */
        .featured-event {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
            100% { transform: translateY(0px); }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary);
        }
        
        /* Back to top button */
        #backToTop {
            transition: all 0.3s ease;
        }
        
        #backToTop:hover {
            transform: translateY(-3px) rotate(5deg);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand animate__animated animate__fadeInLeft" href="main_menu.php">MIT Alumni Portal</a>
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
                        <ul class="dropdown-menu dropdown-menu-end animate__animated animate__fadeIn" aria-labelledby="notificationDropdown">
                            <?php
                            $notifications = $conn->query("SELECT * FROM notifications 
                                                           WHERE user_email = '{$_SESSION['logged_account']['email']}'
                                                           ORDER BY created_at DESC LIMIT 5");
                            while($note = $notifications->fetch_assoc()): ?>
                                <li>
                                    <a class="dropdown-item <?= $note['is_read'] ? '' : 'fw-bold' ?>" href="<?= $note['link'] ?>">
                                        <?= htmlspecialchars($note['message']) ?>
                                        <small class="text-muted"><?= date('M j, g:i a', strtotime($note['created_at'])) ?></small>
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
                        <ul class="dropdown-menu dropdown-menu-end animate__animated animate__fadeIn" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="view_profile.php?email=<?= htmlspecialchars($_SESSION['logged_account']['email']) ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
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
    <div class="container my-3 animate__animated animate__fadeIn">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a class="breadcrumb-link text-secondary" href="main_menu.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Events/News</li>
            </ol>
        </nav>
    </div>

    <div class="container mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up">
            <h1 class="animate__animated animate__fadeInDown">Events/News</h1>
            <div>
                <?php if ($userRole === 'Admin'): ?>
                    <a href="add_event.php" class="btn btn-success me-2 hover-effect">
                        <i class="bi bi-plus-circle me-1"></i> Add Event
                    </a>
                <?php endif; ?>
                <a href="add_event_user.php" class="btn btn-primary hover-effect">
                    <i class="bi bi-plus-lg me-1"></i> Add Advertisement
                </a>
            </div>
        </div>
        
        <!-- Flash Messages -->
        <?php if (isset($flash)): ?>
            <div class="alert alert-<?= $flash_mode ?> alert-dismissible fade show animate__animated animate__fadeIn">
                <?= $flash ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Filter Section -->
        <div class="card mb-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Type</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="filterType" id="filterType1" value="All" <?= $filterType === 'All' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary" for="filterType1">All</label>
                            
                            <input type="radio" class="btn-check" name="filterType" id="filterType2" value="Event" <?= $filterType === 'Event' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-success" for="filterType2">Events</label>
                            
                            <input type="radio" class="btn-check" name="filterType" id="filterType3" value="News" <?= $filterType === 'News' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-warning" for="filterType3">News</label>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Time</label>
                        <select class="form-select" name="filterTime">
                            <option value="All" <?= $filterTime === 'All' ? 'selected' : '' ?>>All</option>
                            <option value="Upcoming" <?= $filterTime === 'Upcoming' ? 'selected' : '' ?>>Upcoming</option>
                            <option value="Past" <?= $filterTime === 'Past' ? 'selected' : '' ?>>Past</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Search events..." value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                            <?php if (!empty($search)): ?>
                                <a href="view_events.php" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Events/News Listing -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php if (empty($events)): ?>
                <div class="col-12" data-aos="fade-up">
                    <div class="alert alert-info animate__animated animate__pulse">
                        No events found matching your criteria.
                    </div>
                </div>
            <?php else: ?>
                <?php 
                $count = 0;
                foreach ($events as $event): 
                    $count++;
                    // Check if current user is registered for this event
                    $isRegistered = false;
                    if ($event['type'] === 'Event' && isset($_SESSION['logged_account']['email'])) {
                        $checkReg = $conn->prepare("SELECT * FROM event_registration_table WHERE event_id = ? AND participant_email = ?");
                        $checkReg->bind_param("is", $event['id'], $_SESSION['logged_account']['email']);
                        $checkReg->execute();
                        $isRegistered = $checkReg->get_result()->num_rows > 0;
                    }
                    
                    // Parse custom fields if they exist
                    $customFields = [];
                    if (!empty($event['custom_fields'])) {
                        $customFields = json_decode($event['custom_fields'], true);
                    }
                    
                    // Determine if event is featured (first 3 events)
                    $featuredClass = ($count <= 3) ? 'featured-event' : '';
                ?>
                    <div class="col" data-aos="fade-up" data-aos-delay="<?= $count * 50 ?>">
                        <div class="card h-100 event-card <?= $featuredClass ?>">
                            <img src="images/<?= htmlspecialchars($event['photo']) ?>" class="card-img-top" alt="<?= htmlspecialchars($event['title']) ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="badge <?= $event['type'] === 'Event' ? 'bg-success' : 'bg-warning' ?>">
                                        <?= htmlspecialchars($event['type']) ?>
                                    </span>
                                    <small class="text-muted"><?= date('M j, Y', strtotime($event['event_date'])) ?></small>
                                </div>
                                <h5 class="card-title"><?= htmlspecialchars($event['title']) ?></h5>
                                <p class="card-text"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['location']) ?></p>
                                <p class="card-text"><?= htmlspecialchars(substr($event['description'], 0, 100)) ?>...</p>
                                
                                <?php if (!empty($customFields)): ?>
                                    <div class="mb-3">
                                        <h6>Registration Fields:</h6>
                                        <div class="custom-fields-list">
                                            <?php foreach ($customFields as $field): ?>
                                                <div class="mb-2">
                                                    <strong><?= htmlspecialchars($field['label']) ?></strong>
                                                    <span class="badge bg-light text-dark ms-2"><?= htmlspecialchars($field['type']) ?></span>
                                                    <?php if ($field['required']): ?>
                                                        <span class="badge bg-danger ms-1">Required</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-white">
                                <div class="d-flex justify-content-between">
                                    <a href="event_details.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-outline-primary hover-effect">
                                        Details
                                    </a>
                                    <?php if ($userRole === 'Admin'): ?>
                                        <div>
                                            <a href="edit_event.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-outline-secondary hover-effect">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="delete_event.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-outline-danger hover-effect" onclick="return confirm('Delete this event?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Registration Section -->
                                <?php if ($event['type'] === 'Event'): ?>
                                    <div class="mt-3">
                                        <?php if ($isRegistered): ?>
                                            <button class="btn btn-success btn-sm w-100" disabled>
                                                <i class="bi bi-check-circle"></i> Registered
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-primary btn-sm w-100 signup-btn hover-effect" data-event-id="<?= $event['id'] ?>">
                                                Sign Up
                                            </button>
                                            
                                            <div class="registration-form" id="form-<?= $event['id'] ?>">
                                                <form method="POST">
                                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                                    
                                                    <?php if (!empty($customFields)): ?>
                                                        <?php foreach ($customFields as $index => $field): ?>
                                                            <div class="form-group">
                                                                <label class="form-label">
                                                                    <?= htmlspecialchars($field['label']) ?>
                                                                    <?php if ($field['required']): ?><span class="text-danger">*</span><?php endif; ?>
                                                                </label>
                                                                
                                                                <?php switch($field['type']):
                                                                    case 'text': ?>
                                                                        <input type="text" name="custom_response[<?= $index ?>]" class="form-control form-control-sm" <?= $field['required'] ? 'required' : '' ?>>
                                                                        <?php break; ?>
                                                                    
                                                                    case 'textarea': ?>
                                                                        <textarea name="custom_response[<?= $index ?>]" class="form-control form-control-sm" <?= $field['required'] ? 'required' : '' ?>></textarea>
                                                                        <?php break; ?>
                                                                    
                                                                    case 'checkbox': ?>
                                                                        <div class="form-check">
                                                                            <input type="checkbox" name="custom_response[<?= $index ?>]" class="form-check-input" value="1" <?= $field['required'] ? 'required' : '' ?>>
                                                                            <label class="form-check-label"><?= htmlspecialchars($field['label']) ?></label>
                                                                        </div>
                                                                        <?php break; ?>
                                                                    
                                                                    case 'radio': ?>
                                                                        <?php foreach ($field['options'] as $option): ?>
                                                                            <div class="form-check">
                                                                                <input type="radio" name="custom_response[<?= $index ?>]" class="form-check-input" value="<?= htmlspecialchars($option) ?>" <?= $field['required'] ? 'required' : '' ?>>
                                                                                <label class="form-check-label"><?= htmlspecialchars($option) ?></label>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                        <?php break; ?>
                                                                    
                                                                    case 'select': ?>
                                                                        <select name="custom_response[<?= $index ?>]" class="form-select form-select-sm" <?= $field['required'] ? 'required' : '' ?>>
                                                                            <option value="">-- Select --</option>
                                                                            <?php foreach ($field['options'] as $option): ?>
                                                                                <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                        <?php break; ?>
                                                                <?php endswitch; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                    
                                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                                                        <button type="submit" class="btn btn-primary btn-sm hover-effect">Submit</button>
                                                        <button type="button" class="btn btn-outline-secondary btn-sm cancel-btn hover-effect" data-event-id="<?= $event['id'] ?>">Cancel</button>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button id="backToTop" class="btn btn-primary rounded-circle position-fixed bottom-0 end-0 m-4 p-3 shadow-lg animate__animated animate__fadeInUp">
        <i class="bi bi-arrow-up"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Add AOS JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            once: true, // whether animation should happen only once - while scrolling down
            duration: 600, // values from 0 to 3000, with step 50ms
            easing: 'ease-out-quad', // default easing for AOS animations
        });
        
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        // Back to top button
        const backToTopButton = document.getElementById('backToTop');
        
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopButton.style.display = 'block';
                backToTopButton.classList.add('animate__fadeIn');
                backToTopButton.classList.remove('animate__fadeOut');
            } else {
                backToTopButton.classList.add('animate__fadeOut');
                backToTopButton.classList.remove('animate__fadeIn');
            }
        });
        
        backToTopButton.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Show registration form
        document.querySelectorAll('.signup-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const eventId = this.dataset.eventId;
                this.style.display = 'none';
                document.getElementById(`form-${eventId}`).style.display = 'block';
            });
        });
        
        // Hide registration form
        document.querySelectorAll('.cancel-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const eventId = this.dataset.eventId;
                document.getElementById(`form-${eventId}`).style.display = 'none';
                document.querySelector(`.signup-btn[data-event-id="${eventId}"]`).style.display = 'block';
            });
        });
        
        // Button hover effects
        document.querySelectorAll('.hover-effect').forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px)';
                this.style.boxShadow = '0 7px 20px rgba(67, 97, 238, 0.3)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });
        
        // Ripple effect for buttons
        document.querySelectorAll('.btn-primary, .btn-outline-primary').forEach(button => {
            button.addEventListener('click', function(e) {
                // Create ripple element
                const ripple = document.createElement('span');
                ripple.classList.add('ripple-effect');
                
                // Set position of ripple
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                // Style the ripple
                ripple.style.left = `${x}px`;
                ripple.style.top = `${y}px`;
                
                // Add ripple to button
                this.appendChild(ripple);
                
                // Remove ripple after animation
                setTimeout(() => {
                    ripple.remove();
                }, 1000);
            });
        });
    </script>
</body>
</html>