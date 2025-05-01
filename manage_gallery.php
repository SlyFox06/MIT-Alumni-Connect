<?php
session_start();
include 'logged_admin.php';
include 'db_controller.php';

// Enhanced security checks
if (!isset($_SESSION['logged_account'])) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access denied");
}

// Initialize variables
$stmt = null;
$success = null;
$error = null;
$gd_enabled = false;

// Check if GD is available
if (extension_loaded('gd')) {
    $gd_info = gd_info();
    $gd_enabled = true;
}

// Handle form submission with enhanced validation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add_gallery'])) {
            // Input validation
            $event_name = filter_input(INPUT_POST, 'event_name', FILTER_SANITIZE_STRING);
            $event_date = filter_input(INPUT_POST, 'event_date', FILTER_SANITIZE_STRING);
            $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
            
            if (empty($event_name)) {
                throw new Exception("Event name is required");
            }
            
            if (!strtotime($event_date)) {
                throw new Exception("Invalid event date");
            }
            
            // First create an event entry
            $stmt = $conn->prepare("INSERT INTO alumni_gallery_events (event_name, event_date, description) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $event_name, $event_date, $description);
            
            if (!$stmt->execute()) {
                throw new Exception("Database error: " . $stmt->error);
            }
            
            $event_id = $stmt->insert_id;
            
            // File upload handling with more security
            $target_dir = "uploads/gallery/";
            if (!file_exists($target_dir)) {
                if (!mkdir($target_dir, 0755, true)) {
                    throw new Exception("Failed to create upload directory");
                }
            }
            
            // Validate file uploads
            if (!isset($_FILES["gallery_images"]) || count($_FILES["gallery_images"]["name"]) == 0) {
                throw new Exception("No images were uploaded");
            }
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $uploaded_files = [];
            
            // Process each uploaded file
            foreach ($_FILES["gallery_images"]["tmp_name"] as $key => $tmp_name) {
                if ($_FILES["gallery_images"]["error"][$key] != UPLOAD_ERR_OK) {
                    throw new Exception("Error uploading file: " . $_FILES["gallery_images"]["name"][$key]);
                }
                
                $image_name = basename($_FILES["gallery_images"]["name"][$key]);
                $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
                
                if (!in_array($image_ext, $allowed_exts)) {
                    throw new Exception("Only JPG, JPEG, PNG, GIF, and WebP files are allowed");
                }
                
                // Verify image
                $check = getimagesize($tmp_name);
                if ($check === false) {
                    throw new Exception("Uploaded file is not an image: " . $image_name);
                }
                
                // Check file size (10MB limit)
                if ($_FILES["gallery_images"]["size"][$key] > 10000000) {
                    throw new Exception("File size exceeds 10MB limit: " . $image_name);
                }
                
                // Generate unique filename
                $new_image_name = uniqid('img_', true) . '.' . $image_ext;
                $target_file = $target_dir . $new_image_name;
                
                // Move uploaded file
                if (!move_uploaded_file($tmp_name, $target_file)) {
                    throw new Exception("Failed to move uploaded file: " . $image_name);
                }
                
                // Handle thumbnail creation with guaranteed fallback
                $thumbnail_path = $target_file; // Default to original
                
                if ($gd_enabled) {
                    $thumbnail_path = $target_dir . 'thumb_' . $new_image_name;
                    if (!createThumbnail($target_file, $thumbnail_path, 300)) {
                        $thumbnail_path = $target_file; // Fallback to original
                    }
                }
                
                // Ensure we always have a thumbnail path
                $thumbnail_path = $thumbnail_path ?: $target_file;
                
                // Store file info for DB insertion
                $uploaded_files[] = [
                    'target_file' => $target_file,
                    'thumbnail_path' => $thumbnail_path
                ];
            }
            
            // Insert all photos into database
            $stmt = $conn->prepare("INSERT INTO alumni_gallery_photos (event_id, image_path, thumbnail_path) VALUES (?, ?, ?)");
            
            foreach ($uploaded_files as $file) {
                // Final verification before insertion
                if (empty($file['thumbnail_path'])) {
                    $file['thumbnail_path'] = $file['target_file'];
                }
                
                $stmt->bind_param("iss", $event_id, $file['target_file'], $file['thumbnail_path']);
                
                if (!$stmt->execute()) {
                    // Clean up uploaded files if DB insert fails
                    foreach ($uploaded_files as $f) {
                        if (file_exists($f['target_file'])) unlink($f['target_file']);
                        if (file_exists($f['thumbnail_path']) && $f['thumbnail_path'] != $f['target_file']) {
                            unlink($f['thumbnail_path']);
                        }
                    }
                    
                    // Delete the event too
                    $conn->query("DELETE FROM alumni_gallery_events WHERE id = $event_id");
                    
                    throw new Exception("Database error: " . $stmt->error);
                }
            }
            
            $success = "Gallery event created with " . count($uploaded_files) . " photos!";
            
        } elseif (isset($_POST['update_status'])) {
            // Validate status update
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id === false || $id <= 0) {
                throw new Exception("Invalid item ID");
            }
            
            $stmt = $conn->prepare("UPDATE alumni_gallery_events SET is_active = ? WHERE id = ?");
            $stmt->bind_param("ii", $is_active, $id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update status: " . $stmt->error);
            }
            
            $success = "Status updated successfully!";
            
        } elseif (isset($_POST['delete_item'])) {
            // Validate delete request
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            
            if ($id === false || $id <= 0) {
                throw new Exception("Invalid item ID");
            }
            
            // Get file paths before deletion
            $stmt = $conn->prepare("SELECT image_path, thumbnail_path FROM alumni_gallery_photos WHERE event_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $files_to_delete = [];
            while ($row = $result->fetch_assoc()) {
                $files_to_delete[] = [
                    'image_path' => $row['image_path'],
                    'thumbnail_path' => $row['thumbnail_path']
                ];
            }
            
            if (count($files_to_delete) === 0) {
                throw new Exception("No photos found for this event");
            }
            
            // The foreign key constraint with ON DELETE CASCADE will automatically
            // delete photos when the event is deleted, but we need to delete the files manually
            $stmt = $conn->prepare("DELETE FROM alumni_gallery_events WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete item: " . $stmt->error);
            }
            
            // Delete the actual files
            foreach ($files_to_delete as $file) {
                if (file_exists($file['image_path'])) {
                    unlink($file['image_path']);
                }
                
                if (file_exists($file['thumbnail_path']) && $file['thumbnail_path'] != $file['image_path']) {
                    unlink($file['thumbnail_path']);
                }
            }
            
            $success = "Item and all its photos deleted successfully!";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    } finally {
        if ($stmt && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
    }
}

// Enhanced thumbnail creation function
function createThumbnail($src, $dest, $targetWidth) {
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }
    
    $info = getimagesize($src);
    if ($info === false) {
        return false;
    }
    
    // Determine image type
    $type = $info[2];
    
    // Create image from source based on type
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($src);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($src);
            break;
        case IMAGETYPE_GIF:
            $source_image = imagecreatefromgif($src);
            break;
        case IMAGETYPE_WEBP:
            $source_image = imagecreatefromwebp($src);
            break;
        default:
            return false;
    }
    
    if (!$source_image) {
        return false;
    }
    
    $width = $info[0];
    $height = $info[1];
    
    // Calculate proportional height
    $targetHeight = (int)($height * ($targetWidth / $width));
    
    // Create thumbnail
    $virtual_image = imagecreatetruecolor($targetWidth, $targetHeight);
    
    // Preserve transparency for PNG/GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($virtual_image, imagecolorallocatealpha($virtual_image, 0, 0, 0, 127));
        imagealphablending($virtual_image, false);
        imagesavealpha($virtual_image, true);
    }
    
    // Resize image
    imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
    
    // Save thumbnail based on original type
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($virtual_image, $dest, 85);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($virtual_image, $dest, 8);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($virtual_image, $dest);
            break;
        case IMAGETYPE_WEBP:
            $result = imagewebp($virtual_image, $dest, 85);
            break;
    }
    
    // Clean up
    imagedestroy($source_image);
    imagedestroy($virtual_image);
    
    return $result;
}

// Get all gallery items with event and photo data
$gallery_items = [];
$query = "SELECT e.*, 
          (SELECT COUNT(*) FROM alumni_gallery_photos WHERE event_id = e.id) as photo_count,
          (SELECT thumbnail_path FROM alumni_gallery_photos WHERE event_id = e.id LIMIT 1) as thumbnail_path
          FROM alumni_gallery_events e 
          ORDER BY e.event_date DESC";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $gallery_items[] = $row;
    }
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MIT Alumni Portal - Manage Gallery">
    <meta name="author" content="MIT Alumni Portal">
    
    <title>Manage Gallery | MIT Alumni Portal</title>
    
    <!-- Favicon -->
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <style>
        :root {
            --primary-color: #002c59;
            --secondary-color: #f8f9fa;
            --accent-color: #0d6efd;
            --danger-color: #dc3545;
            --success-color: #198754;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--secondary-color);
            min-height: 100vh;
        }
        
        .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .thumbnail {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .thumbnail:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            background-color: white;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-bottom: none;
            padding: 1.25rem;
        }
        
        .file-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .file-preview {
            position: relative;
            width: 100px;
            height: 100px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .file-preview:hover {
            transform: scale(1.05);
        }
        
        .file-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .file-preview .remove-btn {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 20px;
            height: 20px;
            background-color: rgba(255,0,0,0.7);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s ease;
        }
        
        .file-preview .remove-btn:hover {
            background-color: rgba(255,0,0,0.9);
            transform: scale(1.1);
        }
        
        .btn-action {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        
        .btn-action:hover {
            transform: scale(1.1);
        }
        
        .status-toggle {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .status-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--success-color);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 0.2em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner-border 0.75s linear infinite;
        }
        
        @keyframes spinner-border {
            to { transform: rotate(360deg); }
        }
        
        /* Fade animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .slide-up {
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Pulse animation for active elements */
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(13, 110, 253, 0); }
            100% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-header {
                padding: 1rem;
            }
            
            .btn-action {
                width: 32px;
                height: 32px;
            }
            
            .thumbnail {
                max-width: 80px;
                max-height: 80px;
            }
        }
    </style>
</head>
<body class="admin-bg">
    <!-- Navigation Bar -->
    <nav class="navbar sticky-top navbar-expand-lg navbar-dark mb-5" style="background-color: var(--primary-color);">
        <div class="container">
            <a class="navbar-brand mx-0 mb-0 h1" href="main_menu_admin.php">
                <i class="bi bi-building me-2"></i>MIT Alumni Portal
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse me-5" id="navbarSupportedContent">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-5" href="main_menu_admin.php" title="Home">
                            <i class="bi bi-house-door-fill nav-bi"></i>
                            <span class="d-lg-none ms-2">Home</span>
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-5" href="manage_accounts.php" title="Manage Accounts">
                            <i class="bi bi-people nav-bi-admin position-relative">
                                <?php if (isset($pendingCount) && $pendingCount > 0) { ?> 
                                <span class="position-absolute top-0 start-100 badge rounded-pill bg-danger fst-normal fw-medium small-badge">
                                    <?php echo $pendingCount; ?>
                                </span>
                                <?php } ?>
                            </i>
                            <span class="d-lg-none ms-2">Accounts</span>
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-5" href="manage_events.php" title="Manage Events">
                            <i class="bi bi-calendar-event nav-bi-admin"></i>
                            <span class="d-lg-none ms-2">Events</span>
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-5" href="manage_advertisements.php" title="Manage Ads">
                            <i class="bi bi-megaphone nav-bi-admin"></i>
                            <span class="d-lg-none ms-2">Ads</span>
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link nav-main-admin-active px-3 px-lg-5" href="manage_gallery.php" title="Manage Gallery">
                            <i class="bi bi-images nav-bi-admin"></i>
                            <span class="d-lg-none ms-2">Gallery</span>
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-5" href="manage_success_stories.php" title="Success Stories">
                            <i class="bi bi-trophy nav-bi-admin"></i>
                            <span class="d-lg-none ms-2">Stories</span>
                        </a>
                    </li>
                </ul>
            </div>
            <?php include 'nav_user.php' ?>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4 animate__animated animate__fadeIn">
            <h2 class="mb-0">Manage Photo Gallery</h2>
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#helpModal">
                <i class="bi bi-question-circle"></i> Help
            </button>
        </div>
        
        <?php if (!$gd_enabled): ?>
            <div class="alert alert-warning d-flex align-items-center animate__animated animate__fadeIn">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div>
                    <strong>GD library is not enabled.</strong> Thumbnails will not be generated. 
                    Contact your system administrator to enable the GD extension for better performance.
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeIn">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card h-100 animate__animated animate__fadeInLeft">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Add New Gallery Event</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="galleryForm">
                            <div class="mb-3">
                                <label for="event_name" class="form-label">Event Name *</label>
                                <input type="text" class="form-control" id="event_name" name="event_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="event_date" class="form-label">Event Date *</label>
                                <input type="date" class="form-control" id="event_date" name="event_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Optional description..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="gallery_images" class="form-label">Event Images *</label>
                                <input type="file" class="form-control" id="gallery_images" name="gallery_images[]" multiple accept="image/*" required>
                                <small class="text-muted">Max file size: 10MB each. Allowed formats: JPG, PNG, GIF, WebP</small>
                                <div class="file-preview-container mt-2" id="imagePreviews"></div>
                            </div>
                            <button type="submit" name="add_gallery" class="btn btn-primary w-100" id="submitBtn">
                                <i class="bi bi-upload me-2"></i>Create Gallery Event
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card animate__animated animate__fadeInRight">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-images me-2"></i>Gallery Events</h5>
                            <span class="badge bg-primary rounded-pill"><?php echo count($gallery_items); ?> events</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($gallery_items)): ?>
                            <div class="text-center py-5 animate__animated animate__fadeIn">
                                <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                <h5 class="mt-3 text-muted">No gallery events found</h5>
                                <p class="text-muted">Add your first gallery event using the form on the left</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Cover</th>
                                            <th>Event</th>
                                            <th>Date</th>
                                            <th>Photos</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($gallery_items as $item): ?>
                                            <tr class="animate__animated animate__fadeIn">
                                                <td>
                                                    <?php if ($item['thumbnail_path']): ?>
                                                    <img src="<?php echo htmlspecialchars($item['thumbnail_path']); ?>" 
                                                         class="thumbnail" 
                                                         alt="<?php echo htmlspecialchars($item['event_name']); ?> thumbnail"
                                                         data-bs-toggle="tooltip" 
                                                         data-bs-title="View event photos">
                                                    <?php else: ?>
                                                    <div class="bg-light text-center p-2 rounded" style="width: 100px; height: 100px;">
                                                        <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                                                        <small class="d-block text-muted">No image</small>
                                                    </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['event_name']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($item['event_date'])); ?></td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo $item['photo_count']; ?> photos</span>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="is_active" 
                                                                   id="status_<?php echo $item['id']; ?>" 
                                                                   <?php echo $item['is_active'] ? 'checked' : ''; ?> 
                                                                   onchange="this.form.submit()">
                                                            <input type="hidden" name="update_status" value="1">
                                                            <label class="form-check-label" for="status_<?php echo $item['id']; ?>"></label>
                                                        </div>
                                                    </form>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <a href="view_gallery.php?id=<?php echo $item['id']; ?>" 
                                                           class="btn-action btn btn-info" 
                                                           title="View Photos"
                                                           data-bs-toggle="tooltip">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="add_photos.php?event_id=<?php echo $item['id']; ?>" 
                                                           class="btn-action btn btn-success" 
                                                           title="Add More Photos"
                                                           data-bs-toggle="tooltip">
                                                            <i class="bi bi-plus"></i>
                                                        </a>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                            <button type="submit" name="delete_item" 
                                                                    class="btn-action btn btn-danger" 
                                                                    title="Delete Event & All Photos"
                                                                    data-bs-toggle="tooltip"
                                                                    onclick="return confirm('Are you sure you want to delete this event and all its photos? This action cannot be undone.')">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="helpModalLabel"><i class="bi bi-question-circle me-2"></i>Gallery Management Help</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>Adding Gallery Events</h6>
                    <p>To add a new gallery event:</p>
                    <ol>
                        <li>Fill in the event name and date</li>
                        <li>Optionally add a description</li>
                        <li>Select one or more images (max 10MB each)</li>
                        <li>Click "Create Gallery Event"</li>
                    </ol>
                    
                    <h6 class="mt-4">Managing Events</h6>
                    <ul>
                        <li><strong>Toggle Status:</strong> Use the switch to show/hide events from public view</li>
                        <li><strong>View Photos:</strong> Click the eye icon to view all photos in the event</li>
                        <li><strong>Add Photos:</strong> Click the plus icon to add more photos to an existing event</li>
                        <li><strong>Delete:</strong> Click the trash icon to permanently remove an event and all its photos</li>
                    </ul>
                    
                    <div class="alert alert-info mt-4">
                        <i class="bi bi-info-circle me-2"></i>
                        You can upload multiple images at once when creating a new event. For best results, upload high-quality images in JPEG or PNG format.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it!</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        // Enable Bootstrap tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Image preview for multiple file upload
            document.getElementById('gallery_images').addEventListener('change', function(event) {
                const files = event.target.files;
                const previewContainer = document.getElementById('imagePreviews');
                previewContainer.innerHTML = '';
                
                if (files.length > 0) {
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        if (file.type.match('image.*')) {
                            const reader = new FileReader();
                            const previewDiv = document.createElement('div');
                            previewDiv.className = 'file-preview animate__animated animate__fadeIn';
                            
                            const img = document.createElement('img');
                            const removeBtn = document.createElement('div');
                            removeBtn.className = 'remove-btn';
                            removeBtn.innerHTML = '×';
                            removeBtn.onclick = function() {
                                // Animation when removing
                                previewDiv.classList.remove('animate__fadeIn');
                                previewDiv.classList.add('animate__fadeOut');
                                
                                setTimeout(() => {
                                    // Remove this file from the file input
                                    const dt = new DataTransfer();
                                    for (let j = 0; j < files.length; j++) {
                                        if (j !== i) {
                                            dt.items.add(files[j]);
                                        }
                                    }
                                    event.target.files = dt.files;
                                    previewDiv.remove();
                                }, 300);
                            };
                            
                            reader.onload = function(e) {
                                img.src = e.target.result;
                                previewDiv.appendChild(img);
                                previewDiv.appendChild(removeBtn);
                                previewContainer.appendChild(previewDiv);
                            }
                            reader.readAsDataURL(file);
                        }
                    }
                }
            });
            
            // Set today's date as default for event date
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('event_date').value = today;
            
            // Form submission loading indicator
            const form = document.getElementById('galleryForm');
            const submitBtn = document.getElementById('submitBtn');
            
            if (form) {
                form.addEventListener('submit', function() {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
                });
            }
            
            // Add animation to alerts when they're dismissed
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.addEventListener('close.bs.alert', function () {
                    this.classList.add('animate__animated', 'animate__fadeOut');
                });
            });
        });
        
        // Add animation to elements when they come into view
        const animateOnScroll = function() {
            const elements = document.querySelectorAll('.animate__animated');
            
            elements.forEach(element => {
                const elementPosition = element.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;
                
                if (elementPosition < windowHeight - 100) {
                    const animationClass = element.classList.item(1); // Get the animation class
                    element.classList.add(animationClass);
                }
            });
        };
        
        window.addEventListener('scroll', animateOnScroll);
        window.addEventListener('load', animateOnScroll);
    </script>
</body>
</html>