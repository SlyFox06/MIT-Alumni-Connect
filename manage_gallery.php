<?php
session_start();
include 'logged_admin.php';
include 'db_controller.php';

// Check if GD is available
$gd_enabled = extension_loaded('gd') && function_exists('gd_info');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_gallery'])) {
        $event_name = $_POST['event_name'];
        $event_date = $_POST['event_date'];
        $description = $_POST['description'];
        
        // Image upload handling
        $target_dir = "uploads/gallery/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $image_name = basename($_FILES["gallery_image"]["name"]);
        $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
        $new_image_name = uniqid() . '.' . $image_ext;
        $target_file = $target_dir . $new_image_name;
        
        // Check if image file is valid
        $check = getimagesize($_FILES["gallery_image"]["tmp_name"]);
        if ($check !== false) {
            if (move_uploaded_file($_FILES["gallery_image"]["tmp_name"], $target_file)) {
                // Handle thumbnail creation based on GD availability
                if ($gd_enabled) {
                    // Create thumbnail using GD
                    $thumbnail_path = $target_dir . 'thumb_' . $new_image_name;
                    if (!createThumbnail($target_file, $thumbnail_path, 300)) {
                        $thumbnail_path = $target_file; // Fallback to original
                    }
                } else {
                    // GD not available - use original image as thumbnail
                    $thumbnail_path = $target_file;
                }
                
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO alumni_gallery_events (event_name, event_date, description, image_path, thumbnail_path, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("sssss", $event_name, $event_date, $description, $target_file, $thumbnail_path);
                
                if ($stmt->execute()) {
                    $success = "Gallery item added successfully!";
                } else {
                    $error = "Error adding gallery item: " . $stmt->error;
                }
            } else {
                $error = "Sorry, there was an error uploading your file.";
            }
        } else {
            $error = "File is not an image.";
        }
    } elseif (isset($_POST['update_status'])) {
        $id = $_POST['id'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE alumni_gallery_events SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $is_active, $id);
        
        if ($stmt->execute()) {
            $success = "Status updated successfully!";
        } else {
            $error = "Error updating status: " . $stmt->error;
        }
    } elseif (isset($_POST['delete_item'])) {
        $id = $_POST['id'];
        
        // First get paths to delete files
        $stmt = $conn->prepare("SELECT image_path, thumbnail_path FROM alumni_gallery_events WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // Delete files (only if they exist and are different)
        if (file_exists($row['image_path'])) {
            unlink($row['image_path']);
        }
        if (file_exists($row['thumbnail_path']) && $row['thumbnail_path'] != $row['image_path']) {
            unlink($row['thumbnail_path']);
        }
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM alumni_gallery_events WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = "Item deleted successfully!";
        } else {
            $error = "Error deleting item: " . $stmt->error;
        }
    }
}

// Function to create thumbnail (only used if GD is available)
function createThumbnail($src, $dest, $targetWidth) {
    if (!extension_loaded('gd')) {
        return false;
    }
    
    $info = getimagesize($src);
    
    if ($info[2] == IMAGETYPE_JPEG) {
        $source_image = imagecreatefromjpeg($src);
    } elseif ($info[2] == IMAGETYPE_PNG) {
        $source_image = imagecreatefrompng($src);
    } elseif ($info[2] == IMAGETYPE_GIF) {
        $source_image = imagecreatefromgif($src);
    } else {
        return false;
    }
    
    $width = $info[0];
    $height = $info[1];
    
    $targetHeight = floor($height * ($targetWidth / $width));
    $virtual_image = imagecreatetruecolor($targetWidth, $targetHeight);
    
    imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
    
    if ($info[2] == IMAGETYPE_JPEG) {
        imagejpeg($virtual_image, $dest);
    } elseif ($info[2] == IMAGETYPE_PNG) {
        imagepng($virtual_image, $dest);
    } elseif ($info[2] == IMAGETYPE_GIF) {
        imagegif($virtual_image, $dest);
    }
    
    imagedestroy($source_image);
    imagedestroy($virtual_image);
    
    return true;
}

// Get all gallery items
$gallery_items = [];
$stmt = $conn->prepare("SELECT * FROM alumni_gallery_events ORDER BY event_date DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $gallery_items[] = $row;
}
$stmt->close();
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
        .gallery-image {
            max-height: 200px;
            object-fit: cover;
        }
        @media (max-width: 768px) {
            .mobile-stack {
                flex-direction: column;
            }
            .mobile-stack .col-md-4, 
            .mobile-stack .col-md-8 {
                width: 100%;
                max-width: 100%;
            }
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
                <i class="bi bi-exclamation-triangle-fill"></i> GD library is not enabled. Thumbnails will not be generated.
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="row mobile-stack">
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Add New Gallery Item</h5>
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
                                <label for="gallery_image" class="form-label">Image</label>
                                <input type="file" class="form-control" id="gallery_image" name="gallery_image" accept="image/*" required>
                                <small class="text-muted">Max size: 5MB. Supported formats: JPG, PNG, GIF</small>
                            </div>
                            <button type="submit" name="add_gallery" class="btn btn-primary">Add to Gallery</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Gallery Items</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($gallery_items)): ?>
                            <p class="text-muted">No gallery items found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Event</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($gallery_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($item['thumbnail_path'])): ?>
                                                        <img src="<?php echo htmlspecialchars($item['thumbnail_path']); ?>" class="thumbnail" alt="Thumbnail">
                                                    <?php else: ?>
                                                        <span class="text-muted">No image</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['event_name']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($item['event_date'])); ?></td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="is_active" id="status_<?php echo $item['id']; ?>" 
                                                                <?php echo $item['is_active'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                                                            <input type="hidden" name="update_status" value="1">
                                                        </div>
                                                    </form>
                                                </td>
                                                <td>
                                                    <a href="view_gallery.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                        <button type="submit" name="delete_item" class="btn btn-sm btn-danger" title="Delete" 
                                                            onclick="return confirm('Are you sure you want to delete this item?')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client-side validation for file upload
        document.getElementById('gallery_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB
            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
            
            if (file.size > maxSize) {
                alert('File size must be less than 5MB');
                e.target.value = '';
            }
            
            if (!validTypes.includes(file.type)) {
                alert('Only JPG, PNG, and GIF images are allowed');
                e.target.value = '';
            }
        });
    </script>
</body>
</html>