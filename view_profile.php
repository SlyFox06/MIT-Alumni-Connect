<?php
// Start output buffering at the VERY TOP
ob_start();

// Include database connection
include 'db_controller.php';
$conn->select_db("atharv");

// Start session and set headers BEFORE any output
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if user is logged in
if (!isset($_SESSION['logged_account'])) {
    header('Location: login.php');
    exit();
}

// Generate CSRF token for the form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Safely get and validate user data
$userEmail = filter_var($_SESSION['logged_account']['email'] ?? '', FILTER_SANITIZE_EMAIL);
$userRole = $_SESSION['logged_account']['role'] ?? 'Guest';

// Include other files AFTER headers are set
include 'logged_user.php';
include 'get_alumnus_by_email.php';

// Initialize variables to prevent warnings
$userData = [];
$eventsCount = $newsCount = $adsCount = 0;
$eventsPercent = $newsPercent = $adsPercent = 0;
$recentEvents = $recentAds = null;
$flash = $flash_mode = null;

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_picture'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash'] = "CSRF token validation failed";
        $_SESSION['flash_mode'] = "alert-danger";
        header("Location: view_profile.php");
        exit();
    }

    try {
        $targetDir = "uploads/profile_pics/";
        if (!file_exists($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                throw new Exception("Failed to create directory");
            }
        }

        // Validate file
        $fileName = basename($_FILES['profile_picture']['name']);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        
        // Check if image file is an actual image
        $check = getimagesize($_FILES['profile_picture']['tmp_name']);
        if ($check === false) {
            throw new Exception("File is not an image");
        }

        // Check file size (max 2MB)
        if ($_FILES['profile_picture']['size'] > 2000000) {
            throw new Exception("File is too large (max 2MB)");
        }

        // Allow certain file formats
        $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
        if (!in_array($fileType, $allowTypes)) {
            throw new Exception("Only JPG, JPEG, PNG & GIF files are allowed");
        }

        // Generate unique filename to prevent overwrites
        $newFileName = uniqid() . '.' . $fileType;
        $targetFilePath = $targetDir . $newFileName;

        // Upload file to server
        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetFilePath)) {
            throw new Exception("Error uploading file");
        }
        
        // Update database using prepared statement
        $stmt = $conn->prepare("UPDATE user_table SET profile_image = ? WHERE email = ?");
        $stmt->bind_param("ss", $newFileName, $userEmail);
        if (!$stmt->execute()) {
            throw new Exception("Database update failed");
        }
        $stmt->close();
        
        $_SESSION['flash'] = "Profile picture updated successfully!";
        $_SESSION['flash_mode'] = "alert-success";
    } catch (Exception $e) {
        $_SESSION['flash'] = "Error: " . $e->getMessage();
        $_SESSION['flash_mode'] = "alert-danger";
    }
    
    header("Location: view_profile.php");
    exit();
}

// Get user data safely using prepared statements
if (!empty($userEmail)) {
    try {
        // Get user info
        $stmt = $conn->prepare("SELECT * FROM user_table WHERE email = ?");
        $stmt->bind_param("s", $userEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        $userData = $result->fetch_assoc() ?? [];
        $stmt->close();
        
        // Get event statistics
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM event_registration_table WHERE participant_email = ?");
        $stmt->bind_param("s", $userEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        $eventsCount = $result->fetch_assoc()['count'] ?? 0;
        $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM event_registration_table er JOIN event_table e ON er.event_id = e.id WHERE er.participant_email = ? AND e.type = 'News'");
        $stmt->bind_param("s", $userEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        $newsCount = $result->fetch_assoc()['count'] ?? 0;
        $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM advertisement_table WHERE advertiser = ?");
        $stmt->bind_param("s", $userEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        $adsCount = $result->fetch_assoc()['count'] ?? 0;
        $stmt->close();

        // Calculate percentages
        $eventsPercent = min(($eventsCount / 20) * 100, 100);
        $newsPercent = min(($newsCount / 20) * 100, 100);
        $adsPercent = min(($adsCount / 20) * 100, 100);

        // Get recent events
        $stmt = $conn->prepare("SELECT e.* FROM event_table e JOIN event_registration_table er ON e.id = er.event_id WHERE er.participant_email = ? ORDER BY e.event_date DESC LIMIT 3");
        $stmt->bind_param("s", $userEmail);
        $stmt->execute();
        $recentEvents = $stmt->get_result();
        // Don't close yet - we'll use the result later

        // Get recent ads
        $stmt2 = $conn->prepare("SELECT * FROM advertisement_table WHERE advertiser = ? ORDER BY date_added DESC LIMIT 3");
        $stmt2->bind_param("s", $userEmail);
        $stmt2->execute();
        $recentAds = $stmt2->get_result();
        // Don't close yet - we'll use the result later

    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['flash'] = "Error loading profile data";
        $_SESSION['flash_mode'] = "alert-danger";
    }
}

// Flash message handling
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    $flash_mode = $_SESSION['flash_mode'] ?? 'alert-info';
    unset($_SESSION['flash']);
    unset($_SESSION['flash_mode']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .profile-pic {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .profile-header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            padding: 2rem 0;
            margin-bottom: 2rem;
            color: white;
            border-radius: 0 0 20px 20px;
        }
        .dashboard-card {
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            border: none;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .progress {
            height: 10px;
            border-radius: 5px;
        }
        .progress-bar {
            background-color: #6a11cb;
        }
        .stat-card {
            border-left: 4px solid #6a11cb;
        }
        .upload-btn {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        .upload-btn input[type=file] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="main_menu.php">MIT Alumni Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="main_menu.php"><i class="bi bi-house-door"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_events.php"><i class="bi bi-calendar-event"></i> Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_advertisements.php"><i class="bi bi-megaphone"></i> Opportunities</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="view_profile.php"><i class="bi bi-person-circle"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Header -->
    <div class="profile-header text-center">
        <div class="container">
            <!-- Profile Picture with Upload Form -->
            <form id="profilePicForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="position-relative d-inline-block">
                    <img src="<?= !empty($userData['profile_image']) ? 'uploads/profile_pics/' . htmlspecialchars($userData['profile_image']) : 'https://via.placeholder.com/150?text=Profile' ?>" 
                         class="profile-pic mb-3" 
                         alt="Profile Picture">
                    <label class="upload-btn position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2">
                        <i class="bi bi-camera"></i>
                        <input type="file" name="profile_picture" id="profile_picture" accept="image/*" onchange="document.getElementById('profilePicForm').submit()">
                    </label>
                </div>
            </form>
            
            <h2><?= htmlspecialchars($userData['first_name'] ?? '') . ' ' . htmlspecialchars($userData['last_name'] ?? '') ?></h2>
            <p class="mb-1"><?= htmlspecialchars($userData['email'] ?? '') ?></p>
            
            <div class="mt-3">
                <a href="update_profile.php?email=<?= urlencode($userEmail) ?>" class="btn btn-primary">Edit Profile</a>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Flash Messages -->
        <?php if (isset($flash)): ?>
            <div class="alert alert-<?= $flash_mode ?> alert-dismissible fade show">
                <?= $flash ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Dashboard Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h4 class="card-title"><i class="bi bi-speedometer2 me-2"></i>Your Dashboard</h4>
                        <hr>
                        
                        <div class="row">
                            <!-- Events Attended -->
                            <div class="col-md-3 mb-3">
                                <div class="card stat-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">Events Attended</h5>
                                        <h2 class="text-primary"><?= $eventsCount ?></h2>
                                        <div class="progress mt-2">
                                            <div class="progress-bar" role="progressbar" style="width: <?= $eventsPercent ?>%" 
                                                 aria-valuenow="<?= $eventsPercent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small class="text-muted"><?= round($eventsPercent) ?>% of monthly goal</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- News Read -->
                            <div class="col-md-3 mb-3">
                                <div class="card stat-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">News Read</h5>
                                        <h2 class="text-primary"><?= $newsCount ?></h2>
                                        <div class="progress mt-2">
                                            <div class="progress-bar" role="progressbar" style="width: <?= $newsPercent ?>%" 
                                                 aria-valuenow="<?= $newsPercent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small class="text-muted"><?= round($newsPercent) ?>% of monthly goal</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Opportunities Posted -->
                            <div class="col-md-3 mb-3">
                                <div class="card stat-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">Opportunities Posted</h5>
                                        <h2 class="text-primary"><?= $adsCount ?></h2>
                                        <div class="progress mt-2">
                                            <div class="progress-bar" role="progressbar" style="width: <?= $adsPercent ?>%" 
                                                 aria-valuenow="<?= $adsPercent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small class="text-muted"><?= round($adsPercent) ?>% of monthly goal</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Additional Stats Placeholder -->
                            <div class="col-md-3 mb-3">
                                <div class="card stat-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">Your Activity</h5>
                                        <h2 class="text-primary"><?= $eventsCount + $newsCount + $adsCount ?></h2>
                                        <div class="progress mt-2">
                                            <div class="progress-bar" role="progressbar" style="width: <?= ($eventsPercent + $newsPercent + $adsPercent) / 3 ?>%" 
                                                 aria-valuenow="<?= ($eventsPercent + $newsPercent + $adsPercent) / 3 ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small class="text-muted">Overall engagement</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Section -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card dashboard-card h-100">
                    <div class="card-body">
                        <h4 class="card-title"><i class="bi bi-calendar-event me-2"></i>Recent Events Attended</h4>
                        <hr>
                        <?php if ($recentEvents && $recentEvents->num_rows > 0): ?>
                            <ul class="list-group list-group-flush">
                                <?php while($event = $recentEvents->fetch_assoc()): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6><?= htmlspecialchars($event['title']) ?></h6>
                                            <small class="text-muted"><?= date('M j, Y', strtotime($event['event_date'])) ?></small>
                                        </div>
                                        <a href="event_details.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <div class="alert alert-info">No events attended yet.</div>
                        <?php endif; ?>
                        <div class="mt-3">
                            <a href="view_events.php" class="btn btn-outline-primary btn-sm">View All Events</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card dashboard-card h-100">
                    <div class="card-body">
                        <h4 class="card-title"><i class="bi bi-megaphone me-2"></i>Your Recent Opportunities</h4>
                        <hr>
                        <?php if ($recentAds && $recentAds->num_rows > 0): ?>
                            <ul class="list-group list-group-flush">
                                <?php while($ad = $recentAds->fetch_assoc()): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6><?= htmlspecialchars($ad['title']) ?></h6>
                                            <small class="text-muted">Posted on <?= date('M j, Y', strtotime($ad['date_added'])) ?></small>
                                        </div>
                                        <a href="advertisement_details.php?id=<?= $ad['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <div class="alert alert-info">No opportunities posted yet.</div>
                        <?php endif; ?>
                        <div class="mt-3">
                            <a href="view_advertisements.php" class="btn btn-outline-primary btn-sm">View All Opportunities</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit form when profile picture is selected
        document.getElementById('profile_picture').addEventListener('change', function() {
            if (this.files && this.files[0]) {
                // Show preview
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.profile-pic').src = e.target.result;
                }
                reader.readAsDataURL(this.files[0]);
                
                // Submit form
                document.getElementById('profilePicForm').submit();
            }
        });
    </script>
</body>
</html>
<?php 
// Close any open database connections
if (isset($stmt)) $stmt->close();
if (isset($stmt2)) $stmt2->close();
if (isset($conn)) $conn->close();

ob_end_flush(); 
?>