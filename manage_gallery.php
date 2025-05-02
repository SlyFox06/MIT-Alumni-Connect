<?php
session_start();
require_once 'logged_admin.php';
require_once 'db_controller.php';

// Check GD library availability
$gd_info = gd_info();
$gd_enabled = extension_loaded('gd') && function_exists('gd_info');
$webp_supported = $gd_enabled && isset($gd_info['WebP Support']) ? $gd_info['WebP Support'] : false;

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
        $_SESSION['error'] = $e->getMessage();
        header("Location: manage_gallery.php");
        exit();
    }
}

// Function to handle adding new event
function handleAddEvent() {
    global $conn;
    
    $event_name = trim($_POST['event_name']);
    $event_date = $_POST['event_date'];
    $description = trim($_POST['description']);
    
    // Validate inputs
    if (empty($event_name) || empty($event_date)) {
        throw new Exception("Event name and date are required");
    }
    
    // Process cover image upload
    $cover_image = processImageUpload('cover_image', 'uploads/gallery/events/', 1200, 800);
    
    // Insert into database
    $stmt = $conn->prepare("INSERT INTO alumni_gallery_events 
                          (event_name, event_date, description, image_path, created_at) 
                          VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $event_name, $event_date, $description, $cover_image['path']);
    
    if (!$stmt->execute()) {
        @unlink($cover_image['path']);
        @unlink($cover_image['thumbnail']);
        throw new Exception("Error saving event to database: " . $stmt->error);
    }
    
    $_SESSION['success'] = "Gallery event created successfully!";
    header("Location: manage_gallery.php");
    exit();
}

// Function to handle adding photos
function handleAddPhotos() {
    global $conn;
    
    $event_id = (int)$_POST['event_id'];
    
    // Validate event exists
    $stmt = $conn->prepare("SELECT id FROM alumni_gallery_events WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    
    if (!$stmt->get_result()->num_rows) {
        throw new Exception("Invalid event selected");
    }
    
    // Process file uploads
    $uploaded_files = [];
    $errors = [];
    
    foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['gallery_images']['error'][$key] !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading file: " . $_FILES['gallery_images']['name'][$key];
            continue;
        }
        
        try {
            $image = processImageUpload(
                ['name' => $_FILES['gallery_images']['name'][$key], 
                'tmp_name' => $tmp_name,
                'error' => $_FILES['gallery_images']['error'][$key]
            ], 
                'uploads/gallery/photos/', 
                1600, 
                1200
            );
            
            // Insert into database
            $stmt = $conn->prepare("INSERT INTO alumni_gallery_photos 
                                   (event_id, image_path, thumbnail_path, upload_date) 
                                   VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iss", $event_id, $image['path'], $image['thumbnail']);
            
            if ($stmt->execute()) {
                $uploaded_files[] = $image['path'];
            } else {
                throw new Exception("Error saving photo to database");
            }
            
        } catch (Exception $e) {
            $errors[] = $e->getMessage() . " (" . $_FILES['gallery_images']['name'][$key] . ")";
            if (isset($image)) {
                @unlink($image['path']);
                @unlink($image['thumbnail']);
            }
        }
    }
    
    if (!empty($uploaded_files)) {
        $msg = "Successfully uploaded " . count($uploaded_files) . " photos";
        if (!empty($errors)) {
            $msg .= " (with " . count($errors) . " errors)";
        }
        $_SESSION['success'] = $msg;
    }
    
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
    }
    
    header("Location: manage_gallery.php");
    exit();
}

// Function to handle status updates
function handleUpdateStatus() {
    global $conn;
    
    $id = (int)$_POST['id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE alumni_gallery_events SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $is_active, $id);
    
    if (!$stmt->execute()) {
        throw new Exception("Error updating status: " . $stmt->error);
    }
    
    $_SESSION['success'] = "Status updated successfully!";
    header("Location: manage_gallery.php");
    exit();
}

// Function to handle event deletion
function handleDeleteEvent() {
    global $conn;
    
    $id = (int)$_POST['id'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Get cover image path
        $stmt = $conn->prepare("SELECT image_path FROM alumni_gallery_events WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
        
        if (!$event) {
            throw new Exception("Event not found");
        }
        
        // Get all photo paths for this event
        $stmt = $conn->prepare("SELECT image_path, thumbnail_path FROM alumni_gallery_photos WHERE event_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM alumni_gallery_photos WHERE event_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE FROM alumni_gallery_events WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        // Delete files
        if (file_exists($event['image_path'])) {
            @unlink($event['image_path']);
        }
        
        foreach ($photos as $photo) {
            if (file_exists($photo['image_path'])) {
                @unlink($photo['image_path']);
            }
            if (file_exists($photo['thumbnail_path']) && $photo['thumbnail_path'] != $photo['image_path']) {
                @unlink($photo['thumbnail_path']);
            }
        }
        
        $conn->commit();
        $_SESSION['success'] = "Event and all associated photos deleted successfully!";
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
    header("Location: manage_gallery.php");
    exit();
}

/**
 * Process image upload with validation and thumbnail creation
 */
function processImageUpload($file_input, $target_dir, $max_width = 1600, $max_height = 1200) {
    global $gd_enabled, $webp_supported;
    
    // Create target directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    // Handle both direct file input and array-style input
    if (is_string($file_input) && isset($_FILES[$file_input])) {
        $file = $_FILES[$file_input];
    } elseif (is_array($file_input)) {
        $file = $file_input;
    } else {
        throw new Exception("Invalid file input");
    }
    
    // Validate upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload error: " . $file['error']);
    }
    
    // Validate image
    $image_info = getimagesize($file['tmp_name']);
    if (!$image_info) {
        throw new Exception("File is not a valid image");
    }
    
    $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
    if ($webp_supported) {
        $allowed_types[] = IMAGETYPE_WEBP;
    }
    
    if (!in_array($image_info[2], $allowed_types)) {
        throw new Exception("Only JPG, PNG, GIF" . ($webp_supported ? ", and WebP" : "") . " images are allowed");
    }
    
    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("Image size exceeds maximum limit of 5MB");
    }
    
    // Generate unique filename with proper extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('img_') . '.' . $ext;
    $target_file = $target_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $target_file)) {
        throw new Exception("Failed to move uploaded file");
    }
    
    // Process image (resize if needed and create thumbnail)
    $result = [
        'path' => $target_file,
        'thumbnail' => $target_file // fallback to original if GD not available
    ];
    
    if ($gd_enabled) {
        try {
            // Resize main image if it's too large
            list($width, $height) = $image_info;
            
            if ($width > $max_width || $height > $max_height) {
                $resized_file = $target_dir . 'resized_' . $filename;
                if (resizeImage($target_file, $resized_file, $max_width, $max_height)) {
                    // Replace original with resized version
                    unlink($target_file);
                    rename($resized_file, $target_file);
                }
            }
            
            // Create thumbnail
            $thumbnail_file = $target_dir . 'thumb_' . $filename;
            if (createThumbnail($target_file, $thumbnail_file, 300)) {
                $result['thumbnail'] = $thumbnail_file;
            }
            
        } catch (Exception $e) {
            // If image processing fails, we'll still use the original
            error_log("Image processing error: " . $e->getMessage());
        }
    }
    
    return $result;
}

/**
 * Create thumbnail image
 */
function createThumbnail($src, $dest, $targetWidth, $targetHeight = null) {
    if (!extension_loaded('gd')) return false;
    
    $info = getimagesize($src);
    if (!$info) return false;
    
    list($width, $height, $type) = $info;
    
    // Calculate target height if not specified
    if ($targetHeight === null) {
        $targetHeight = (int)($height * ($targetWidth / $width));
    }
    
    // Create image from source
    switch ($type) {
        case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG: $source = imagecreatefrompng($src); break;
        case IMAGETYPE_GIF: $source = imagecreatefromgif($src); break;
        case IMAGETYPE_WEBP: $source = imagecreatefromwebp($src); break;
        default: return false;
    }
    
    if (!$source) return false;
    
    $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);
    
    // Handle transparency for PNG and GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
    }
    
    // Resize image
    imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
    
    // Save image
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG: $result = imagejpeg($thumbnail, $dest, 85); break;
        case IMAGETYPE_PNG: $result = imagepng($thumbnail, $dest, 8); break;
        case IMAGETYPE_GIF: $result = imagegif($thumbnail, $dest); break;
        case IMAGETYPE_WEBP: $result = imagewebp($thumbnail, $dest, 85); break;
    }
    
    imagedestroy($source);
    imagedestroy($thumbnail);
    
    return $result;
}

/**
 * Resize image while maintaining aspect ratio
 */
function resizeImage($src, $dest, $maxWidth, $maxHeight) {
    if (!extension_loaded('gd')) return false;
    
    $info = getimagesize($src);
    if (!$info) return false;
    
    list($width, $height, $type) = $info;
    
    // Calculate new dimensions
    $ratio = $width / $height;
    
    if ($width > $maxWidth || $height > $maxHeight) {
        if ($maxWidth / $maxHeight > $ratio) {
            $newWidth = $maxHeight * $ratio;
            $newHeight = $maxHeight;
        } else {
            $newWidth = $maxWidth;
            $newHeight = $maxWidth / $ratio;
        }
    } else {
        // Image is smaller than max dimensions - no resize needed
        return false;
    }
    
    // Create image from source
    switch ($type) {
        case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG: $source = imagecreatefrompng($src); break;
        case IMAGETYPE_GIF: $source = imagecreatefromgif($src); break;
        case IMAGETYPE_WEBP: $source = imagecreatefromwebp($src); break;
        default: return false;
    }
    
    if (!$source) return false;
    
    $resized = imagecreatetruecolor($newWidth, $newHeight);
    
    // Handle transparency for PNG and GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($resized, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
    }
    
    // Resize image
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Save image
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG: $result = imagejpeg($resized, $dest, 85); break;
        case IMAGETYPE_PNG: $result = imagepng($resized, $dest, 8); break;
        case IMAGETYPE_GIF: $result = imagegif($resized, $dest); break;
        case IMAGETYPE_WEBP: $result = imagewebp($resized, $dest, 85); break;
    }
    
    imagedestroy($source);
    imagedestroy($resized);
    
    return $result;
}

// Get all gallery events for dropdown and listing
$gallery_events = [];
$stmt = $conn->prepare("SELECT id, event_name, event_date, image_path, is_active 
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        /* Navigation Styles */
        .nav-admin-link {
            color: #ffffff80;
            transition: color 0.3s;
        }
        .nav-admin-link:hover, .nav-main-admin-active {
            color: white !important;
        }
        .nav-bi, .nav-bi-admin {
            font-size: 1.5rem;
        }
        .small-badge {
            font-size: 0.5rem;
            padding: 0.2em 0.4em;
        }
        
        /* Gallery Management Styles */
        .admin-bg {
            background-color: #f8f9fa;
        }
        .thumbnail {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
        }
        .cover-image {
            max-height: 200px;
            object-fit: cover;
        }
        .event-card {
            transition: transform 0.2s;
        }
        .event-card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            background-color: #002c59;
            color: white;
        }
        .alert {
            margin-top: 20px;
        }
        .form-switch .form-check-input {
            width: 2.5em;
            height: 1.5em;
        }
        .photo-count-badge {
            position: absolute;
            top: -10px;
            right: -10px;
        }
        .upload-container {
            border: 2px dashed #dee2e6;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .upload-container:hover {
            border-color: #0d6efd;
        }
    </style>
</head>
<body class="admin-bg">
    <!-- Navigation Bar -->
    <nav class="navbar sticky-top navbar-expand-lg mb-5" style="background-color: #002c59;">
        <div class="container">
            <a class="navbar-brand mx-0 mb-0 h1 text-light" href="main_menu_admin.php">MIT Alumni Portal</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse me-5" id="navbarSupportedContent">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-5" href="main_menu_admin.php"><i class="bi bi-house-door-fill nav-bi"></i></a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-5" href="manage_accounts.php"><i class="bi bi-people nav-bi-admin position-relative">
                            <?php if (isset($pendingCount) && $pendingCount > 0) { ?> 
                            <span class="position-absolute top-0 start-100 badge rounded-pill bg-danger fst-normal fw-medium small-badge">
                                <?php echo $pendingCount; ?>
                            </span>
                            <?php } ?>
                        </i></a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-5" href="manage_events.php"><i class="bi bi-calendar-event nav-bi-admin"></i></a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-5" href="manage_advertisements.php"><i class="bi bi-megaphone nav-bi-admin"></i></a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link nav-main-admin-active px-5" href="manage_gallery.php"><i class="bi bi-images nav-bi-admin"></i></a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-5" href="manage_success_stories.php"><i class="bi bi-trophy nav-bi-admin"></i></a>
                    </li>
                </ul>
            </div>
            
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-5">
        <h2 class="mb-4">Manage Photo Gallery</h2>
        
        <?php if (!$gd_enabled): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i> GD library is not enabled. Image processing features are limited.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['errors']) && is_array($_SESSION['errors'])): ?>
            <div class="alert alert-danger">
                <h5>Some errors occurred:</h5>
                <ul class="mb-0">
                    <?php foreach ($_SESSION['errors'] as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                    <?php unset($_SESSION['errors']); ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Add New Gallery Event</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="event_name" class="form-label">Event Name</label>
                                <input type="text" class="form-control" id="event_name" name="event_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="event_date" class="form-label">Event Date</label>
                                <input type="date" class="form-control" id="event_date" name="event_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="cover_image" class="form-label">Cover Image</label>
                                <div class="upload-container">
                                    <i class="bi bi-image fs-1 text-muted mb-2"></i>
                                    <p class="text-muted">Drag & drop your image here or click to browse</p>
                                    <input type="file" class="form-control d-none" id="cover_image" name="cover_image" accept="image/*" required>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('cover_image').click()">
                                        Select Image
                                    </button>
                                </div>
                                <small class="text-muted">Recommended size: 1200x800px (Max 5MB)</small>
                            </div>
                            <button type="submit" name="add_gallery_event" class="btn btn-primary w-100">Create Event</button>
                        </form>
                        
                        <hr>
                        
                        <h5>Add Photos to Event</h5>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="event_id" class="form-label">Select Event</label>
                                <select class="form-select" id="event_id" name="event_id" required>
                                    <option value="">-- Select Event --</option>
                                    <?php foreach ($gallery_events as $event): ?>
                                        <option value="<?= $event['id'] ?>"><?= htmlspecialchars($event['event_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                                <small class="text-muted">Max 20 photos at once (Max 5MB each)</small>
                            </div>
                            <button type="submit" name="add_photos" class="btn btn-primary w-100">Add Photos</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Gallery Events</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($gallery_events)): ?>
                            <p class="text-muted">No gallery events found. Create your first event using the form on the left.</p>
                        <?php else: ?>
                            <div class="row row-cols-1 row-cols-md-2 g-4">
                                <?php foreach ($gallery_events as $event): ?>
                                    <div class="col">
                                        <div class="card event-card h-100">
                                            <div class="position-relative">
                                                <img src="<?= str_replace('\\', '/', htmlspecialchars($event['image_path'])) ?>" class="card-img-top cover-image" alt="Event cover">
                                                <span class="badge bg-primary rounded-pill photo-count-badge">
                                                    <?= $event_photo_counts[$event['id']] ?? 0 ?> photos
                                                </span>
                                            </div>
                                            <div class="card-body">
                                                <h5 class="card-title"><?= htmlspecialchars($event['event_name']) ?></h5>
                                                <p class="card-text text-muted">
                                                    <?= date('F j, Y', strtotime($event['event_date'])) ?>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="id" value="<?= $event['id'] ?>">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="is_active" id="status_<?= $event['id'] ?>" 
                                                                <?= $event['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()">
                                                            <input type="hidden" name="update_status" value="1">
                                                            <label class="form-check-label" for="status_<?= $event['id'] ?>">
                                                                <?= $event['is_active'] ? 'Active' : 'Inactive' ?>
                                                            </label>
                                                        </div>
                                                    </form>
                                                    
                                                    <div>
                                                        <a href="view_gallery.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-info" title="View">
                                                            <i class="bi bi-eye"></i> View
                                                        </a>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="id" value="<?= $event['id'] ?>">
                                                            <button type="submit" name="delete_event" class="btn btn-sm btn-danger" title="Delete" 
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File input handling for better UX
        document.addEventListener('DOMContentLoaded', function() {
            // Handle cover image selection
            const coverInput = document.getElementById('cover_image');
            const coverContainer = coverInput.closest('.upload-container');
            
            coverInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    coverContainer.querySelector('p').textContent = this.files[0].name;
                }
            });
            
            // Handle multiple photo selection
            const photosInput = document.getElementById('gallery_images');
            const photosContainer = photosInput.closest('.upload-container');
            
            photosInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const fileCount = this.files.length > 1 ? 
                        `${this.files.length} files selected` : 
                        this.files[0].name;
                    photosContainer.querySelector('p').textContent = fileCount;
                }
            });
            
            // Drag and drop functionality
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
                    container.style.backgroundColor = '';
                });
                
                container.addEventListener('drop', (e) => {
                    e.preventDefault();
                    container.style.borderColor = '#dee2e6';
                    container.style.backgroundColor = '';
                    
                    if (e.dataTransfer.files.length) {
                        input.files = e.dataTransfer.files;
                        
                        // Trigger change event
                        const event = new Event('change');
                        input.dispatchEvent(event);
                    }
                });
            });
            
            // Limit multiple file selection to 20 files
            document.getElementById('gallery_images').addEventListener('change', function(e) {
                if (this.files.length > 20) {
                    alert('You can upload a maximum of 20 files at once. Only the first 20 files will be selected.');
                    this.value = '';
                    this.closest('.upload-container').querySelector('p').textContent = 
                        'Drag & drop your photos here or click to browse';
                }
            });
        });
    </script>
</body>
</html>