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
$gd_enabled = extension_loaded('gd');

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

            // Validate thumbnail upload
            if (!isset($_FILES['event_thumbnail']) || $_FILES['event_thumbnail']['error'] != UPLOAD_ERR_OK) {
                throw new Exception("Event thumbnail is required");
            }

            // Process thumbnail
            $thumbnail_name = basename($_FILES['event_thumbnail']['name']);
            $thumbnail_ext = strtolower(pathinfo($thumbnail_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($thumbnail_ext, $allowed_exts)) {
                throw new Exception("Only JPG, JPEG, PNG, GIF, and WebP files are allowed for thumbnails");
            }
            
            $check = getimagesize($_FILES['event_thumbnail']['tmp_name']);
            if ($check === false) {
                throw new Exception("Uploaded thumbnail is not an image");
            }
            
            if ($_FILES['event_thumbnail']['size'] > 5000000) { // 5MB limit for thumbnail
                throw new Exception("Thumbnail size exceeds 5MB limit");
            }
            
            $target_dir = "uploads/gallery/";
            if (!file_exists($target_dir)) {
                if (!mkdir($target_dir, 0755, true)) {
                    throw new Exception("Failed to create upload directory");
                }
            }
            
            $new_thumbnail_name = uniqid('thumb_', true) . '.' . $thumbnail_ext;
            $thumbnail_path = $target_dir . $new_thumbnail_name;
            
            if (!move_uploaded_file($_FILES['event_thumbnail']['tmp_name'], $thumbnail_path)) {
                throw new Exception("Failed to move uploaded thumbnail");
            }
            
            // Create event entry with thumbnail
            $stmt = $conn->prepare("INSERT INTO alumni_gallery_events (event_name, event_date, description, thumbnail_path) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $event_name, $event_date, $description, $thumbnail_path);
            
            if (!$stmt->execute()) {
                // Clean up uploaded thumbnail if DB insert fails
                if (file_exists($thumbnail_path)) {
                    unlink($thumbnail_path);
                }
                throw new Exception("Database error: " . $stmt->error);
            }
            
            $event_id = $stmt->insert_id;
            $success = "Gallery event created successfully! You can now add photos.";
            
            // Redirect to add photos page
            header("Location: add_photos.php?event_id=" . $event_id);
            exit();
            
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
            $stmt = $conn->prepare("SELECT thumbnail_path FROM alumni_gallery_events WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $event = $result->fetch_assoc();
            
            if (!$event) {
                throw new Exception("Event not found");
            }
            
            // Get all photos for this event
            $stmt = $conn->prepare("SELECT image_path, thumbnail_path FROM alumni_gallery_photos WHERE event_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $files_to_delete = [];
            
            // Add event thumbnail to files to delete
            if (!empty($event['thumbnail_path'])) {
                $files_to_delete[] = $event['thumbnail_path'];
            }
            
            // Add all photos to files to delete
            while ($row = $result->fetch_assoc()) {
                if (!empty($row['image_path'])) {
                    $files_to_delete[] = $row['image_path'];
                }
                if (!empty($row['thumbnail_path']) && $row['thumbnail_path'] != $row['image_path']) {
                    $files_to_delete[] = $row['thumbnail_path'];
                }
            }
            
            // Delete the event (photos will be deleted automatically due to ON DELETE CASCADE)
            $stmt = $conn->prepare("DELETE FROM alumni_gallery_events WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete item: " . $stmt->error);
            }
            
            // Delete the actual files
            foreach ($files_to_delete as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            
            $success = "Event and all its photos deleted successfully!";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    } finally {
        if ($stmt) {
            $stmt->close();
        }
    }
}

// Get all gallery items with event and photo data
$gallery_items = [];
$query = "SELECT e.*, 
          (SELECT COUNT(*) FROM alumni_gallery_photos WHERE event_id = e.id) as photo_count
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
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <style>
        .admin-bg {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        
        .thumbnail {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        
        .card-header {
            background-color: #002c59;
            color: white;
            border-bottom: none;
            padding: 1.25rem;
        }
        
        .thumbnail-preview {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }
        
        .btn-action {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        @media (max-width: 768px) {
            .card-header {
                padding: 1rem;
            }
            
            .btn-action {
                width: 32px;
                height: 32px;
            }
        }
    </style>
</head>
<body class="admin-bg">
    <!-- Navigation Bar -->
    <nav class="navbar sticky-top navbar-expand-lg navbar-dark mb-5" style="background-color: #002c59;">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Manage Photo Gallery</h2>
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#helpModal">
                <i class="bi bi-question-circle"></i> Help
            </button>
        </div>
        
        <?php if (!$gd_enabled): ?>
            <div class="alert alert-warning d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div>
                    <strong>GD library is not enabled.</strong> Thumbnails will not be generated. 
                    Contact your system administrator to enable the GD extension for better performance.
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Create New Event</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="eventForm">
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
                                <label for="event_thumbnail" class="form-label">Event Thumbnail *</label>
                                <input type="file" class="form-control" id="event_thumbnail" name="event_thumbnail" accept="image/*" required>
                                <small class="text-muted">Max file size: 5MB. Allowed formats: JPG, PNG, GIF, WebP</small>
                                <img id="thumbnailPreview" class="thumbnail-preview mt-2">
                            </div>
                            <button type="submit" name="add_gallery" class="btn btn-primary w-100">
                                <i class="bi bi-save me-2"></i>Create Event
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-images me-2"></i>Gallery Events</h5>
                            <span class="badge bg-primary rounded-pill"><?php echo count($gallery_items); ?> events</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($gallery_items)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                <h5 class="mt-3 text-muted">No gallery events found</h5>
                                <p class="text-muted">Create your first gallery event using the form on the left</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Thumbnail</th>
                                            <th>Event</th>
                                            <th>Date</th>
                                            <th>Photos</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($gallery_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($item['thumbnail_path'])): ?>
                                                    <img src="<?php echo htmlspecialchars($item['thumbnail_path']); ?>" 
                                                         class="thumbnail" 
                                                         alt="<?php echo htmlspecialchars($item['event_name']); ?> thumbnail">
                                                    <?php else: ?>
                                                    <div class="bg-light text-center p-2 rounded" style="width: 100px; height: 100px;">
                                                        <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                                                        <small class="d-block text-muted">No thumbnail</small>
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
                                                           title="View Event"
                                                           data-bs-toggle="tooltip">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="add_photos.php?event_id=<?php echo $item['id']; ?>" 
                                                           class="btn-action btn btn-success" 
                                                           title="Add Photos"
                                                           data-bs-toggle="tooltip">
                                                            <i class="bi bi-plus"></i>
                                                        </a>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                            <button type="submit" name="delete_item" 
                                                                    class="btn-action btn btn-danger" 
                                                                    title="Delete Event"
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
                    <h6>Creating Gallery Events</h6>
                    <p>To create a new gallery event:</p>
                    <ol>
                        <li>Fill in the event name and date (required)</li>
                        <li>Optionally add a description</li>
                        <li>Upload a thumbnail image (required, max 5MB)</li>
                        <li>Click "Create Event"</li>
                        <li>You'll be redirected to add photos to the event</li>
                    </ol>
                    
                    <h6 class="mt-4">Managing Events</h6>
                    <ul>
                        <li><strong>Toggle Status:</strong> Use the switch to show/hide events from public view</li>
                        <li><strong>View Event:</strong> Click the eye icon to view the event details and photos</li>
                        <li><strong>Add Photos:</strong> Click the plus icon to add more photos to an existing event</li>
                        <li><strong>Delete:</strong> Click the trash icon to permanently remove an event and all its photos</li>
                    </ul>
                    
                    <div class="alert alert-info mt-4">
                        <i class="bi bi-info-circle me-2"></i>
                        The event thumbnail should be a representative image for the event. You can add all the event photos after creating the event.
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
            
            // Thumbnail preview
            document.getElementById('event_thumbnail').addEventListener('change', function(e) {
                const file = e.target.files[0];
                const preview = document.getElementById('thumbnailPreview');
                
                if (file && file.type.match('image.*')) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    
                    reader.readAsDataURL(file);
                } else {
                    preview.style.display = 'none';
                }
            });
            
            // Set today's date as default for event date
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('event_date').value = today;
        });
    </script>
</body>
</html>