<?php
session_start();
include 'logged_admin.php';

// Initialize variables
$success = null;
$error = null;
$event_id = null;
$event = null;
$gd_enabled = extension_loaded('gd');

// Get event ID from URL
if (isset($_GET['event_id'])) {
    $event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
    
    if ($event_id === false || $event_id <= 0) {
        $error = "Invalid event ID";
    } else {
        // Get database connection
        $conn = new mysqli("localhost", "username", "password", "alumni_portal");
        if ($conn->connect_error) {
            $error = "Database connection failed: " . $conn->connect_error;
        } else {
            // Get event details
            $stmt = $conn->prepare("SELECT * FROM alumni_gallery_events WHERE id = ?");
            if (!$stmt) {
                $error = "Prepare failed: " . $conn->error;
            } else {
                $stmt->bind_param("i", $event_id);
                if (!$stmt->execute()) {
                    $error = "Execute failed: " . $stmt->error;
                } else {
                    $result = $stmt->get_result();
                    $event = $result->fetch_assoc();
                    if (!$event) {
                        $error = "Event not found";
                    }
                }
                $stmt->close();
            }
            $conn->close();
        }
    }
} else {
    $error = "No event specified";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_photos']) && $event_id) {
    try {
        // Validate file uploads
        if (!isset($_FILES["gallery_images"]) || count($_FILES["gallery_images"]["name"]) == 0) {
            throw new Exception("No images were uploaded");
        }
        
        $target_dir = "uploads/gallery/";
        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0755, true)) {
                throw new Exception("Failed to create upload directory");
            }
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
            
            // Handle thumbnail creation
            $thumbnail_path = $target_file; // Default to original if GD not available
            
            if ($gd_enabled) {
                $thumbnail_path = $target_dir . 'thumb_' . $new_image_name;
                if (!createThumbnail($target_file, $thumbnail_path, 300)) {
                    $thumbnail_path = $target_file; // Fallback to original
                }
            }
            
            $uploaded_files[] = [
                'target_file' => $target_file,
                'thumbnail_path' => $thumbnail_path
            ];
        }
        
        // Insert all photos into database
        $conn = new mysqli("localhost", "username", "password", "alumni_portal");
        if ($conn->connect_error) {
            throw new Exception("Database connection failed: " . $conn->connect_error);
        }
        
        $stmt = $conn->prepare("INSERT INTO alumni_gallery_photos (event_id, image_path, thumbnail_path) VALUES (?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        foreach ($uploaded_files as $file) {
            $stmt->bind_param("iss", $event_id, $file['target_file'], $file['thumbnail_path']);
            
            if (!$stmt->execute()) {
                // Clean up uploaded files if DB insert fails
                foreach ($uploaded_files as $f) {
                    if (file_exists($f['target_file'])) unlink($f['target_file']);
                    if (file_exists($f['thumbnail_path']) && $f['thumbnail_path'] != $f['target_file']) {
                        unlink($f['thumbnail_path']);
                    }
                }
                
                throw new Exception("Database error: " . $stmt->error);
            }
        }
        
        $success = "Successfully added " . count($uploaded_files) . " photos to the event!";
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        if (isset($stmt)) $stmt->close();
        if (isset($conn)) $conn->close();
    }
}

function createThumbnail($src, $dest, $targetWidth) {
    if (!function_exists('imagecreatetruecolor')) return false;
    
    $info = getimagesize($src);
    if ($info === false) return false;
    
    $type = $info[2];
    
    switch ($type) {
        case IMAGETYPE_JPEG: $source_image = imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG: $source_image = imagecreatefrompng($src); break;
        case IMAGETYPE_GIF: $source_image = imagecreatefromgif($src); break;
        case IMAGETYPE_WEBP: $source_image = imagecreatefromwebp($src); break;
        default: return false;
    }
    
    if (!$source_image) return false;
    
    $width = $info[0];
    $height = $info[1];
    $targetHeight = (int)($height * ($targetWidth / $width));
    $virtual_image = imagecreatetruecolor($targetWidth, $targetHeight);
    
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($virtual_image, imagecolorallocatealpha($virtual_image, 0, 0, 0, 127));
        imagealphablending($virtual_image, false);
        imagesavealpha($virtual_image, true);
    }
    
    imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
    
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG: $result = imagejpeg($virtual_image, $dest, 85); break;
        case IMAGETYPE_PNG: $result = imagepng($virtual_image, $dest, 8); break;
        case IMAGETYPE_GIF: $result = imagegif($virtual_image, $dest); break;
        case IMAGETYPE_WEBP: $result = imagewebp($virtual_image, $dest, 85); break;
    }
    
    imagedestroy($source_image);
    imagedestroy($virtual_image);
    
    return $result;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Photos | MIT Alumni Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .admin-bg { background-color: #f8f9fa; min-height: 100vh; }
        .card { border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .card-header { background-color: #002c59; color: white; }
        .file-preview-container { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        .file-preview { position: relative; width: 100px; height: 100px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; }
        .file-preview img { width: 100%; height: 100%; object-fit: cover; }
        .file-preview .remove-btn { 
            position: absolute; top: 2px; right: 2px; width: 20px; height: 20px;
            background-color: rgba(255,0,0,0.7); color: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px;
        }
        .event-thumbnail { max-width: 200px; max-height: 200px; object-fit: cover; border-radius: 4px; }
    </style>
</head>
<body class="admin-bg">
    <nav class="navbar sticky-top navbar-expand-lg navbar-dark mb-5" style="background-color: #002c59;">
        <div class="container">
            <a class="navbar-brand" href="main_menu_admin.php">
                <i class="bi bi-building me-2"></i>MIT Alumni Portal
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="manage_gallery.php"><i class="bi bi-images"></i> Gallery</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Add Photos to Gallery Event</h2>
            <a href="manage_gallery.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Gallery
            </a>
        </div>
        
        <?php if (!$gd_enabled): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                GD library is not enabled. Thumbnails will not be generated.
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($event): ?>
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Event Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-start">
                                <?php
                                $conn = new mysqli("localhost", "username", "password", "alumni_portal");
                                $thumbnail_path = null;
                                if (!$conn->connect_error) {
                                    $stmt = $conn->prepare("SELECT thumbnail_path FROM alumni_gallery_photos WHERE event_id = ? LIMIT 1");
                                    if ($stmt) {
                                        $stmt->bind_param("i", $event_id);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        $photo = $result->fetch_assoc();
                                        $thumbnail_path = $photo['thumbnail_path'] ?? null;
                                        $stmt->close();
                                    }
                                    $conn->close();
                                }
                                ?>
                                
                                <?php if ($thumbnail_path): ?>
                                    <img src="<?php echo htmlspecialchars($thumbnail_path); ?>" class="event-thumbnail me-3">
                                <?php else: ?>
                                    <div class="bg-light text-center p-3 rounded me-3" style="width: 200px; height: 200px;">
                                        <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                        <small class="d-block text-muted">No photos yet</small>
                                    </div>
                                <?php endif; ?>
                                
                                <div>
                                    <h4><?php echo htmlspecialchars($event['event_name']); ?></h4>
                                    <p class="text-muted mb-1">
                                        <i class="bi bi-calendar me-1"></i>
                                        <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                                    </p>
                                    <?php if (!empty($event['description'])): ?>
                                        <p class="mt-2"><?php echo htmlspecialchars($event['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Add New Photos</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">Select Images *</label>
                                    <input type="file" class="form-control" name="gallery_images[]" multiple accept="image/*" required>
                                    <small class="text-muted">Max 10MB per file. Formats: JPG, PNG, GIF, WebP</small>
                                    <div class="file-preview-container mt-2" id="imagePreviews"></div>
                                </div>
                                <button type="submit" name="add_photos" class="btn btn-primary w-100">
                                    <i class="bi bi-upload me-2"></i>Upload Photos
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="gallery_images[]"]').addEventListener('change', function(e) {
                const container = document.getElementById('imagePreviews');
                container.innerHTML = '';
                
                Array.from(e.target.files).forEach((file, i) => {
                    if (!file.type.match('image.*')) return;
                    
                    const reader = new FileReader();
                    const preview = document.createElement('div');
                    preview.className = 'file-preview';
                    
                    reader.onload = function(e) {
                        preview.innerHTML = `
                            <img src="${e.target.result}">
                            <div class="remove-btn" onclick="this.parentNode.remove()">×</div>
                        `;
                        container.appendChild(preview);
                    };
                    reader.readAsDataURL(file);
                });
            });
        });
    </script>
</body>
</html>