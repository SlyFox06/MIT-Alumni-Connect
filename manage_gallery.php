<?php
session_start();
require_once 'logged_admin.php';
require_once 'db_controller.php';

// Check GD library availability
$gd_info = gd_info();
$gd_enabled = extension_loaded('gd') && function_exists('gd_info');
$webp_supported = $gd_enabled && isset($gd_info['WebP Support']) ? $gd_info['WebP Support'] : false;

// Get pending account approvals count
$pendingCount = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM account_table WHERE status = 'Pending'");
if ($stmt->execute()) {
    $result = $stmt->get_result();
    $pendingCount = $result->fetch_row()[0];
}
$stmt->close();

// Function to handle adding a new gallery event
function handleAddEvent() {
    global $conn;
    
    // Validate inputs
    if (empty($_POST['event_name']) || empty($_POST['event_date']) || empty($_FILES['cover_image'])) {
        throw new Exception("All fields are required.");
    }
    
    // Process image upload
    $targetDir = "uploads/gallery_events/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = basename($_FILES["cover_image"]["name"]);
    $targetFile = $targetDir . uniqid() . '_' . $fileName;
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    // Check if image file is an actual image
    $check = getimagesize($_FILES["cover_image"]["tmp_name"]);
    if ($check === false) {
        throw new Exception("File is not an image.");
    }
    
    // Check file size (5MB max)
    if ($_FILES["cover_image"]["size"] > 5000000) {
        throw new Exception("Sorry, your file is too large. Max 5MB allowed.");
    }
    
    // Allow certain file formats
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($imageFileType, $allowedTypes)) {
        throw new Exception("Sorry, only JPG, JPEG, PNG & GIF files are allowed.");
    }
    
    // Upload file
    if (!move_uploaded_file($_FILES["cover_image"]["tmp_name"], $targetFile)) {
        throw new Exception("Sorry, there was an error uploading your file.");
    }
    
    // Insert into database
    $stmt = $conn->prepare("INSERT INTO alumni_gallery_events (event_name, event_date, image_path, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $_POST['event_name'], $_POST['event_date'], $targetFile, $_POST['description']);
    
    if (!$stmt->execute()) {
        unlink($targetFile); // Remove uploaded file if DB insert fails
        throw new Exception("Database error: " . $stmt->error);
    }
    
    $stmt->close();
    
    $_SESSION['flash_mode'] = "alert-success";
    $_SESSION['flash'] = "Gallery event added successfully!";
    header("Location: manage_gallery.php");
    exit();
}

// Function to handle adding photos to an event
function handleAddPhotos() {
    global $conn, $gd_enabled;
    
    if (empty($_POST['event_id']) || empty($_FILES['gallery_images'])) {
        throw new Exception("Event ID and photos are required.");
    }
    
    $eventId = $_POST['event_id'];
    $targetDir = "uploads/gallery_photos/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    // Create thumbs directory if it doesn't exist
    $thumbDir = $targetDir . 'thumbs/';
    if (!file_exists($thumbDir)) {
        mkdir($thumbDir, 0777, true);
    }
    
    $uploadedFiles = [];
    $files = $_FILES['gallery_images'];
    $fileCount = count($files['name']);
    
    // Limit to 20 files at once
    if ($fileCount > 20) {
        throw new Exception("You can upload a maximum of 20 files at once.");
    }
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue; // Skip files with errors
        }
        
        $fileName = basename($files["name"][$i]);
        $targetFile = $targetDir . uniqid() . '_' . $fileName;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        
        // Check if image file is an actual image
        $check = getimagesize($files["tmp_name"][$i]);
        if ($check === false) {
            continue; // Skip non-image files
        }
        
        // Check file size (5MB max)
        if ($files["size"][$i] > 5000000) {
            continue; // Skip files that are too large
        }
        
        // Allow certain file formats
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($imageFileType, $allowedTypes)) {
            continue; // Skip disallowed file types
        }
        
        // Upload file
        if (move_uploaded_file($files["tmp_name"][$i], $targetFile)) {
            $thumbnailPath = null;
            
            // Create thumbnail if GD is enabled
            if ($gd_enabled) {
                $thumbnailPath = createThumbnail($targetFile, $targetDir);
            }
            
            $uploadedFiles[] = [
                'path' => $targetFile,
                'thumb' => $thumbnailPath
            ];
        }
    }
    
    if (empty($uploadedFiles)) {
        throw new Exception("No valid images were uploaded.");
    }
    
    // Insert into database
    $stmt = $conn->prepare("INSERT INTO alumni_gallery_photos (event_id, image_path, thumbnail_path) VALUES (?, ?, ?)");
    
    foreach ($uploadedFiles as $file) {
        $stmt->bind_param("iss", $eventId, $file['path'], $file['thumb']);
        if (!$stmt->execute()) {
            // If one insert fails, continue with others but note the error
            error_log("Failed to insert photo: " . $stmt->error);
        }
    }
    
    $stmt->close();
    
    $_SESSION['flash_mode'] = "alert-success";
    $_SESSION['flash'] = count($uploadedFiles) . " photos added successfully!" . (!$gd_enabled ? " (Thumbnails not created - GD library not available)" : "");
    header("Location: manage_gallery.php");
    exit();
}

// Function to create thumbnail
function createThumbnail($sourcePath, $targetDir) {
    $thumbDir = $targetDir . 'thumbs/';
    $thumbnailPath = $thumbDir . 'thumb_' . basename($sourcePath);
    
    // Get original image dimensions
    list($width, $height) = getimagesize($sourcePath);
    
    // Calculate thumbnail dimensions (300px width, maintain aspect ratio)
    $newWidth = 300;
    $newHeight = (int)($height * ($newWidth / $width));
    
    // Create image resource based on file type
    $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case 'png':
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case 'gif':
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        default:
            return null;
    }
    
    if (!$sourceImage) {
        return null;
    }
    
    // Create thumbnail
    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG/GIF
    if ($extension == 'png' || $extension == 'gif') {
        imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
    }
    
    imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Save thumbnail
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($thumbnail, $thumbnailPath, 85);
            break;
        case 'png':
            imagepng($thumbnail, $thumbnailPath, 8);
            break;
        case 'gif':
            imagegif($thumbnail, $thumbnailPath);
            break;
    }
    
    // Free memory
    imagedestroy($sourceImage);
    imagedestroy($thumbnail);
    
    return $thumbnailPath;
}

// Function to update event status
function handleUpdateStatus() {
    global $conn;
    
    if (empty($_POST['id'])) {
        throw new Exception("Event ID is required.");
    }
    
    $id = $_POST['id'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE alumni_gallery_events SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $isActive, $id);
    
    if (!$stmt->execute()) {
        throw new Exception("Database error: " . $stmt->error);
    }
    
    $stmt->close();
    
    $_SESSION['flash_mode'] = "alert-success";
    $_SESSION['flash'] = "Event status updated successfully!";
    header("Location: manage_gallery.php");
    exit();
}

// Function to delete an event and its photos
function handleDeleteEvent() {
    global $conn;
    
    if (empty($_POST['id'])) {
        throw new Exception("Event ID is required.");
    }
    
    $id = $_POST['id'];
    
    // First get all photo paths to delete files later
    $photoPaths = [];
    $stmt = $conn->prepare("SELECT image_path, thumbnail_path FROM alumni_gallery_photos WHERE event_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $photoPaths[] = $row['image_path'];
        if (!empty($row['thumbnail_path'])) {
            $photoPaths[] = $row['thumbnail_path'];
        }
    }
    $stmt->close();
    
    // Get event cover image path
    $stmt = $conn->prepare("SELECT image_path FROM alumni_gallery_events WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    $stmt->close();
    
    if ($event) {
        $coverPath = $event['image_path'];
    }
    
    // Delete photos from database
    $stmt = $conn->prepare("DELETE FROM alumni_gallery_photos WHERE event_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    // Delete event from database
    $stmt = $conn->prepare("DELETE FROM alumni_gallery_events WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if (!$stmt->execute()) {
        throw new Exception("Database error: " . $stmt->error);
    }
    $stmt->close();
    
    // Delete files
    if (!empty($coverPath) && file_exists($coverPath)) {
        unlink($coverPath);
    }
    
    foreach ($photoPaths as $path) {
        if (!empty($path) && file_exists($path)) {
            unlink($path);
        }
    }
    
    $_SESSION['flash_mode'] = "alert-success";
    $_SESSION['flash'] = "Event and all its photos deleted successfully!";
    header("Location: manage_gallery.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add_gallery_event'])) {
            handleAddEvent();
        } elseif (isset($_POST['add_photos'])) {
            handleAddPhotos();
        } elseif (isset($_POST['update_status'])) {
            handleUpdateStatus();
        } elseif (isset($_POST['delete_event'])) {
            handleDeleteEvent();
        }
    } catch (Exception $e) {
        $_SESSION['flash_mode'] = "alert-danger";
        $_SESSION['flash'] = $e->getMessage();
        header("Location: manage_gallery.php");
        exit();
    }
}

// Get all gallery events for dropdown and listing
$gallery_events = [];
$stmt = $conn->prepare("SELECT id, event_name, event_date, image_path, is_active, description 
                       FROM alumni_gallery_events 
                       ORDER BY event_date DESC");
if (!$stmt->execute()) {
    throw new Exception("Database error: " . $stmt->error);
}
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $gallery_events[] = $row;
}
$stmt->close();

// Get photo counts for each event
$event_photo_counts = [];
if (!empty($gallery_events)) {
    $event_ids = array_column($gallery_events, 'id');
    $placeholders = implode(',', array_fill(0, count($event_ids), '?'));
    
    $stmt = $conn->prepare("SELECT event_id, COUNT(*) as photo_count 
                           FROM alumni_gallery_photos 
                           WHERE event_id IN ($placeholders) 
                           GROUP BY event_id");
    $stmt->bind_param(str_repeat('i', count($event_ids)), ...$event_ids);
    if (!$stmt->execute()) {
        throw new Exception("Database error: " . $stmt->error);
    }
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $event_photo_counts[$row['event_id']] = $row['photo_count'];
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Gallery | MIT Alumni Portal</title>
    
    <link rel="stylesheet" href="css/styles.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" />
    
    <style>
        :root {
            --primary-color: #002c59;
            --secondary-color: #f8f9fa;
            --accent-color: #0d6efd;
            --card-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            --card-hover-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            --transition-speed: 0.3s;
        }
        
        body.admin-bg {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .nav-admin-link {
            color: rgba(255, 255, 255, 0.85);
            border-radius: 4px;
            transition: all var(--transition-speed) ease;
            padding: 0.5rem 1rem;
        }
        
        .nav-admin-link:hover, .nav-main-admin-active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .nav-main-admin-active {
            font-weight: 500;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            transition: all var(--transition-speed) ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .event-card {
            height: 100%;
        }
        
        .event-cover {
            height: 200px;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
        }
        
        .photo-count-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            font-size: 0.75rem;
        }
        
        .upload-container {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all var(--transition-speed) ease;
            background-color: rgba(13, 110, 253, 0.05);
        }
        
        .upload-container:hover {
            border-color: var(--accent-color);
            background-color: rgba(13, 110, 253, 0.1);
        }
        
        .badge-status-active {
            background-color: #198754;
        }
        
        .badge-status-inactive {
            background-color: #6c757d;
        }
        
        .form-switch .form-check-input {
            width: 2.5em;
            height: 1.5em;
        }
        
        .breadcrumb-link {
            color: #6c757d;
            text-decoration: none;
        }
        
        .breadcrumb-link:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }
        
        .breadcrumb-active {
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .image-preview {
            max-height: 150px;
            object-fit: contain;
            border-radius: 8px;
            display: none;
        }
        
        .status-toggle {
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .nav-admin-link {
                padding: 0.5rem 1rem !important;
            }
            
            .navbar-nav {
                margin-bottom: 1rem;
            }
            
            .logout-container {
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
            }
            
            .upload-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body class="admin-bg">
    <!-- Enhanced Navbar -->
    <nav class="navbar sticky-top navbar-expand-lg mb-4" style="background-color: var(--primary-color);">
        <div class="container">
            <a class="navbar-brand mx-0 mb-0 h1 text-light fw-bold" href="main_menu_admin.php">
                <i class="bi bi-building-gear me-2"></i>MIT Alumni Portal
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-4" href="main_menu_admin.php">
                            <i class="bi bi-house-door-fill me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-4" href="manage_accounts.php">
                            <i class="bi bi-people me-1 position-relative"></i>
                            Accounts
                            <?php if ($pendingCount > 0) { ?> 
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger small-badge"><?php echo $pendingCount; ?></span>
                            <?php } ?>
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-4" href="manage_events.php">
                            <i class="bi bi-calendar-event me-1"></i> Events
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-4" href="manage_advertisements.php">
                            <i class="bi bi-megaphone me-1"></i> Ads
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link nav-main-admin-active px-3 px-lg-4" href="manage_gallery.php">
                            <i class="bi bi-images me-1"></i> Gallery
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-4" href="manage_success_stories.php">
                            <i class="bi bi-trophy me-1"></i> Stories
                        </a>
                    </li>
                </ul>
                
                <div class="logout-container d-flex">
                    <form action="logout.php" method="post">
                        <button type="submit" class="btn logout-btn rounded-pill px-3">
                            <i class="bi bi-box-arrow-right me-1"></i> Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb -->
    <div class="container my-3">
        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a class="breadcrumb-link text-secondary link-underline link-underline-opacity-0" href="main_menu_admin.php">Home</a></li>
                <li class="breadcrumb-item breadcrumb-active" aria-current="page">Manage Gallery</li>
            </ol>
        </nav>
    </div>

    <!-- Flash message -->
    <div class="container-fluid">
        <?php if (isset($_SESSION['flash_mode']) && isset($_SESSION['flash'])) { ?>
            <div class="row justify-content-center position-absolute top-1 start-50 translate-middle">
                <div class="col-auto">
                    <div class="alert <?php echo htmlspecialchars($_SESSION['flash_mode']); ?> mt-4 py-2 fade-out-alert row align-items-center" role="alert">
                        <i class="bi <?php echo ($_SESSION['flash_mode']) == "alert-success" ? "bi-check-circle" : ($_SESSION['flash_mode'] == "alert-primary" || $_SESSION['flash_mode'] == "alert-secondary" ? "bi-info-circle" : ($_SESSION['flash_mode'] == "alert-warning" ? "bi-exclamation-triangle" : ($_SESSION['flash_mode'] == "alert-danger" ? "bi-exclamation-octagon" : ""))); ?> login-bi col-auto px-0"></i>
                        <div class="col ms-1"><?php echo $_SESSION['flash']; ?></div>
                    </div>
                    <div id="flash-message-container"></div>
                </div>
            </div>
            <?php 
            unset($_SESSION['flash_mode']); 
            unset($_SESSION['flash']); 
            ?>
        <?php } ?>
    </div>

    <div class="container mb-5">
        <div class="row slide-left">
            <div class="col"><h1>Manage Gallery</h1></div>
            <div class="col-auto align-self-center">
                <button class="btn btn-primary fw-medium px-4 py-2" data-bs-toggle="modal" data-bs-target="#addEventModal">
                    <i class="bi bi-plus-lg me-2" style="-webkit-text-stroke: 0.25px;"></i>Add Event
                </button>
            </div>
        </div>

        <?php if (!$gd_enabled): ?>
            <div class="alert alert-warning mt-3">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> GD library is not enabled. Thumbnail generation will be skipped.
            </div>
        <?php endif; ?>

        <div class="row mt-4">
            <div class="col-lg-8">
                <div class="card bg-white mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Gallery Events</h4>
                        
                        <?php if (empty($gallery_events)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-images text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3">No gallery events found. Create your first event to get started.</p>
                            </div>
                        <?php else: ?>
                            <div class="row row-cols-1 row-cols-md-2 g-4">
                                <?php foreach ($gallery_events as $event): ?>
                                    <div class="col">
                                        <div class="card event-card h-100">
                                            <div class="position-relative">
                                                <img src="<?= str_replace('\\', '/', htmlspecialchars($event['image_path'])) ?>" class="card-img-top event-cover" alt="Event cover">
                                                <span class="badge bg-primary rounded-pill photo-count-badge">
                                                    <?= $event_photo_counts[$event['id']] ?? 0 ?> photos
                                                </span>
                                            </div>
                                            <div class="card-body">
                                                <h5 class="card-title"><?= htmlspecialchars($event['event_name']) ?></h5>
                                                <p class="card-text text-muted small">
                                                    <?= date('F j, Y', strtotime($event['event_date'])) ?>
                                                </p>
                                                <?php if (!empty($event['description'])): ?>
                                                    <p class="card-text text-truncate"><?= htmlspecialchars($event['description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-footer bg-transparent border-top-0">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="id" value="<?= $event['id'] ?>">
                                                        <div class="form-check form-switch status-toggle">
                                                            <input class="form-check-input" type="checkbox" name="is_active" id="status_<?= $event['id'] ?>" 
                                                                <?= $event['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()">
                                                            <input type="hidden" name="update_status" value="1">
                                                            <label class="form-check-label" for="status_<?= $event['id'] ?>">
                                                                <span class="badge <?= $event['is_active'] ? 'badge-status-active' : 'badge-status-inactive' ?>">
                                                                    <?= $event['is_active'] ? 'Active' : 'Inactive' ?>
                                                                </span>
                                                            </label>
                                                        </div>
                                                    </form>
                                                    
                                                    <div class="btn-group">
                                                        <a href="view_gallery.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addPhotosModal" 
                                                            data-event-id="<?= $event['id'] ?>" data-event-name="<?= htmlspecialchars($event['event_name']) ?>">
                                                            <i class="bi bi-plus-lg"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="id" value="<?= $event['id'] ?>">
                                                            <button type="submit" name="delete_event" class="btn btn-sm btn-outline-danger" title="Delete" 
                                                                onclick="return confirm('Are you sure you want to delete this event and all its photos?')">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card bg-white mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Quick Stats</h4>
                        
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                <i class="bi bi-calendar-event text-primary" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?= count($gallery_events) ?></h5>
                                <small class="text-muted">Total Events</small>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-success bg-opacity-10 p-3 rounded-circle me-3">
                                <i class="bi bi-image text-success" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?= array_sum($event_photo_counts) ?></h5>
                                <small class="text-muted">Total Photos</small>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center">
                            <div class="bg-info bg-opacity-10 p-3 rounded-circle me-3">
                                <i class="bi bi-check-circle text-info" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?= count(array_filter($gallery_events, function($e) { return $e['is_active']; })) ?></h5>
                                <small class="text-muted">Active Events</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($gallery_events)): ?>
                <div class="card bg-white">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Recent Events</h4>
                        
                        <div class="list-group list-group-flush">
                            <?php 
                            $recent_events = array_slice($gallery_events, 0, 3);
                            foreach ($recent_events as $event): 
                            ?>
                                <a href="view_gallery.php?id=<?= $event['id'] ?>" class="list-group-item list-group-item-action border-0 px-0 py-3">
                                    <div class="d-flex align-items-center">
                                        <img src="<?= str_replace('\\', '/', htmlspecialchars($event['image_path'])) ?>" class="rounded me-3" width="60" height="60" style="object-fit: cover;">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($event['event_name']) ?></h6>
                                            <small class="text-muted"><?= date('M j, Y', strtotime($event['event_date'])) ?></small>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Event Modal -->
    <div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addEventModalLabel">Add New Gallery Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="event_name" class="form-label">Event Name</label>
                            <input type="text" class="form-control" id="event_name" name="event_name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="event_date" class="form-label">Event Date</label>
                                <input type="date" class="form-control" id="event_date" name="event_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="cover_image" class="form-label">Cover Image</label>
                                <input type="file" class="form-control" id="cover_image" name="cover_image" accept="image/*" required>
                                <small class="text-muted">Recommended size: 1200x800px (Max 5MB)</small>
                                <img id="coverPreview" class="img-fluid mt-2 image-preview" alt="Cover preview">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_gallery_event" class="btn btn-primary">Create Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Photos Modal -->
    <div class="modal fade" id="addPhotosModal" tabindex="-1" aria-labelledby="addPhotosModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPhotosModalLabel">Add Photos to Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Event</label>
                            <input type="text" class="form-control" id="eventNameDisplay" readonly>
                            <input type="hidden" name="event_id" id="eventIdInput">
                        </div>
                        <div class="mb-3">
                            <label for="gallery_images" class="form-label">Photos</label>
                            <div class="upload-container">
                                <i class="bi bi-images fs-1 text-muted mb-2"></i>
                                <p class="text-muted">Drag & drop your photos here or click to browse</p>
                                <input type="file" class="form-control d-none" id="gallery_images" name="gallery_images[]" multiple accept="image/*" required>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('gallery_images').click()">
                                    Select Photos
                                </button>
                            </div>
                            <div id="fileList" class="mt-2 small"></div>
                            <small class="text-muted">Max 20 photos at once (Max 5MB each)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_photos" class="btn btn-primary">Add Photos</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script>
        // Initialize modals
        const addPhotosModal = document.getElementById('addPhotosModal');
        if (addPhotosModal) {
            addPhotosModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const eventId = button.getAttribute('data-event-id');
                const eventName = button.getAttribute('data-event-name');
                
                document.getElementById('eventIdInput').value = eventId;
                document.getElementById('eventNameDisplay').value = eventName;
            });
        }

        // Cover image preview
        document.getElementById('cover_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('coverPreview');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });

        // Multiple file selection handling
        document.getElementById('gallery_images').addEventListener('change', function(e) {
            const files = e.target.files;
            const fileList = document.getElementById('fileList');
            
            if (files.length > 0) {
                let html = '<p>Selected files:</p><ul class="list-unstyled">';
                
                for (let i = 0; i < Math.min(files.length, 20); i++) {
                    html += `<li>${files[i].name} (${(files[i].size / 1024 / 1024).toFixed(2)} MB)</li>`;
                }
                
                if (files.length > 20) {
                    html += `<li class="text-danger">+ ${files.length - 20} more files (only first 20 will be uploaded)</li>`;
                }
                
                html += '</ul>';
                fileList.innerHTML = html;
            } else {
                fileList.innerHTML = '';
            }
        });

        // Drag and drop for upload containers
        const uploadContainers = document.querySelectorAll('.upload-container');
        
        uploadContainers.forEach(container => {
            const input = container.querySelector('input[type="file"]');
            
            container.addEventListener('dragover', (e) => {
                e.preventDefault();
                container.style.borderColor = '#0d6efd';
                container.style.backgroundColor = 'rgba(13, 110, 253, 0.1)';
            });
            
            container.addEventListener('dragleave', () => {
                container.style.borderColor = '#dee2e6';
                container.style.backgroundColor = 'rgba(13, 110, 253, 0.05)';
            });
            
            container.addEventListener('drop', (e) => {
                e.preventDefault();
                container.style.borderColor = '#dee2e6';
                container.style.backgroundColor = 'rgba(13, 110, 253, 0.05)';
                
                if (e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files;
                    
                    // Trigger change event
                    const event = new Event('change');
                    input.dispatchEvent(event);
                }
            });
        });

        // Ignore confirm form resubmission after POST request
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>