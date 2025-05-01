<?php
session_start();
require_once 'logged_admin.php';
require_once 'db_controller.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Constants
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB per file
define('MAX_TOTAL_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB total
define('MAX_FILES_PER_UPLOAD', 20);
define('UPLOAD_DIR', 'uploads/gallery/');
define('THUMBNAIL_WIDTH', 300);

// Validate event ID
$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
if (!$event_id) {
    $_SESSION['error'] = "Invalid event ID";
    header("Location: manage_gallery.php");
    exit;
}

// Get event details
try {
    $stmt = $conn->prepare("SELECT * FROM alumni_gallery_events WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$event) {
        $_SESSION['error'] = "Event not found";
        header("Location: manage_gallery.php");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: manage_gallery.php");
    exit;
}

// Check GD library availability
$gd_enabled = extension_loaded('gd');

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['gallery_images'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid CSRF token";
        header("Location: add_photos.php?event_id=" . $event_id);
        exit;
    }

    try {
        // Validate uploads
        if (empty($_FILES['gallery_images']['name'][0])) {
            throw new Exception("Please select at least one image to upload");
        }
        
        $file_count = count($_FILES['gallery_images']['name']);
        $total_size = array_sum($_FILES['gallery_images']['size']);
        
        if ($file_count > MAX_FILES_PER_UPLOAD) {
            throw new Exception("You can upload a maximum of " . MAX_FILES_PER_UPLOAD . " files at once");
        }
        
        if ($total_size > MAX_TOTAL_UPLOAD_SIZE) {
            throw new Exception("Total upload size exceeds " . (MAX_TOTAL_UPLOAD_SIZE/1024/1024) . "MB limit");
        }

        // Create upload directory if needed
        if (!file_exists(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
            throw new Exception("Failed to create upload directory");
        }
        
        if (!is_writable(UPLOAD_DIR)) {
            throw new Exception("Upload directory is not writable");
        }
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $uploaded_files = [];
        
        // Process each file
        foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
            $file_error = $_FILES['gallery_images']['error'][$key];
            $file_name = $_FILES['gallery_images']['name'][$key];
            $file_size = $_FILES['gallery_images']['size'][$key];
            
            // Validate file
            if ($file_error !== UPLOAD_ERR_OK) {
                throw new Exception("Error uploading file '$file_name'");
            }
            
            if (!file_exists($tmp_name)) {
                throw new Exception("Temporary file '$file_name' doesn't exist");
            }
            
            $image_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($image_ext, $allowed_exts)) {
                throw new Exception("Invalid file type '$file_name'");
            }
            
            if ($file_size > MAX_FILE_SIZE) {
                throw new Exception("File '$file_name' exceeds size limit");
            }
            
            if (!getimagesize($tmp_name)) {
                throw new Exception("File '$file_name' is not a valid image");
            }
            
            // Generate unique filename and move file
            $new_name = uniqid('img_', true) . '.' . $image_ext;
            $target_file = UPLOAD_DIR . $new_name;
            
            if (!move_uploaded_file($tmp_name, $target_file)) {
                throw new Exception("Failed to save file '$file_name'");
            }
            
            // Create thumbnail if GD is available
            $thumbnail_path = $gd_enabled ? UPLOAD_DIR . 'thumb_' . $new_name : $target_file;
            if ($gd_enabled && !createThumbnail($target_file, $thumbnail_path, THUMBNAIL_WIDTH)) {
                $thumbnail_path = $target_file; // Fallback to original
            }
            
            // Save to database
            $stmt = $conn->prepare("INSERT INTO alumni_gallery_photos (event_id, image_path, thumbnail_path) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $event_id, $target_file, $thumbnail_path);
            
            if (!$stmt->execute()) {
                // Clean up files if DB insert fails
                @unlink($target_file);
                if ($thumbnail_path != $target_file) @unlink($thumbnail_path);
                throw new Exception("Database error: " . $stmt->error);
            }
            
            $stmt->close();
            $uploaded_files[] = $target_file;
        }
        
        $_SESSION['success'] = "Successfully uploaded $file_count photos!";
        header("Location: view_gallery_admin.php?event_id=" . $event_id);
        exit;
        
    } catch (Exception $e) {
        // Clean up any uploaded files on error
        if (!empty($uploaded_files)) {
            foreach ($uploaded_files as $file) {
                @unlink($file);
                $thumb = UPLOAD_DIR . 'thumb_' . basename($file);
                if (file_exists($thumb)) @unlink($thumb);
            }
        }
        $_SESSION['error'] = $e->getMessage();
        header("Location: add_photos.php?event_id=" . $event_id);
        exit;
    }
}

// Thumbnail creation function
function createThumbnail($src, $dest, $targetWidth) {
    if (!function_exists('imagecreatetruecolor')) return false;
    
    $info = getimagesize($src);
    if ($info === false) return false;
    
    list($width, $height, $type) = $info;
    
    switch ($type) {
        case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG: $source = imagecreatefrompng($src); break;
        case IMAGETYPE_GIF: $source = imagecreatefromgif($src); break;
        case IMAGETYPE_WEBP: $source = imagecreatefromwebp($src); break;
        default: return false;
    }
    
    if (!$source) return false;
    
    $targetHeight = (int)($height * ($targetWidth / $width));
    $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);
    
    // Handle transparency
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
    }
    
    imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
    
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

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Photos - <?= htmlspecialchars($event['event_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #002c59;
            --secondary-color: #f8f9fa;
        }
        
        body {
            background-color: var(--secondary-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background-color: var(--primary-color) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background-color: rgba(0, 44, 89, 0.02);
        }
        
        .upload-area:hover {
            border-color: var(--primary-color);
            background-color: rgba(0, 44, 89, 0.05);
        }
        
        .file-preview {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            margin: 5px;
            position: relative;
        }
        
        .remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 24px;
            height: 24px;
            background-color: rgba(255, 0, 0, 0.8);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .file-preview:hover .remove-btn {
            opacity: 1;
        }
        
        .file-size {
            position: absolute;
            bottom: 5px;
            left: 5px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .event-thumbnail {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="manage_gallery.php">Gallery Admin</a>
            <a href="view_gallery_admin.php?event_id=<?= $event_id ?>" class="btn btn-outline-light">
                <i class="bi bi-arrow-left"></i> Back to Event
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger mb-4">
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success mb-4">
                        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Event: <?= htmlspecialchars($event['event_name']) ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <?php
                            // Get a sample thumbnail from the event
                            $sample_photo = null;
                            $stmt = $conn->prepare("SELECT thumbnail_path FROM alumni_gallery_photos WHERE event_id = ? LIMIT 1");
                            $stmt->bind_param("i", $event_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result->num_rows > 0) {
                                $sample_photo = $result->fetch_assoc()['thumbnail_path'];
                            }
                            $stmt->close();
                            ?>
                            
                            <?php if ($sample_photo && file_exists($sample_photo)): ?>
                                <img src="<?= htmlspecialchars($sample_photo) ?>" class="event-thumbnail me-4">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center bg-light rounded me-4" style="width: 200px; height: 200px;">
                                    <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div>
                                <p class="mb-2">
                                    <i class="bi bi-calendar me-2"></i>
                                    <?= date('F j, Y', strtotime($event['event_date'])) ?>
                                </p>
                                <?php if (!empty($event['description'])): ?>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Upload Photos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$gd_enabled): ?>
                            <div class="alert alert-warning mb-4">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                GD library is not enabled - thumbnails will not be generated
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            
                            <div class="mb-4">
                                <div class="upload-area" id="dropArea">
                                    <i class="bi bi-cloud-arrow-up fs-1 text-primary mb-3"></i>
                                    <h5>Drag & drop photos here</h5>
                                    <p class="text-muted mb-0">
                                        or click to browse (max <?= MAX_FILES_PER_UPLOAD ?> files, <?= MAX_FILE_SIZE/1024/1024 ?>MB each)
                                    </p>
                                </div>
                                <input type="file" class="d-none" name="gallery_images[]" id="fileInput" multiple accept="image/*" required>
                                <div class="d-flex flex-wrap mt-3" id="previewContainer"></div>
                                <div class="d-flex justify-content-between mt-2">
                                    <small class="text-muted" id="fileCount">0 files selected</small>
                                    <small class="text-muted" id="totalSize">0MB</small>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-2">
                                <i class="bi bi-upload me-2"></i> Upload Photos
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropArea = document.getElementById('dropArea');
            const fileInput = document.getElementById('fileInput');
            const previewContainer = document.getElementById('previewContainer');
            const fileCount = document.getElementById('fileCount');
            const totalSize = document.getElementById('totalSize');
            const uploadForm = document.getElementById('uploadForm');
            
            // Format file size
            function formatSize(bytes) {
                if (bytes < 1024) return bytes + 'B';
                if (bytes < 1048576) return (bytes/1024).toFixed(1) + 'KB';
                return (bytes/1048576).toFixed(1) + 'MB';
            }
            
            // Update file info display
            function updateFileInfo() {
                const files = fileInput.files;
                let totalBytes = 0;
                Array.from(files).forEach(file => totalBytes += file.size);
                
                fileCount.textContent = `${files.length} file${files.length !== 1 ? 's' : ''} selected`;
                totalSize.textContent = formatSize(totalBytes);
                
                if (totalBytes > <?= MAX_TOTAL_UPLOAD_SIZE ?>) {
                    totalSize.classList.add('text-danger');
                } else {
                    totalSize.classList.remove('text-danger');
                }
            }
            
            // Handle drag and drop
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, () => dropArea.classList.add('border-primary'));
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, () => dropArea.classList.remove('border-primary'));
            });
            
            dropArea.addEventListener('drop', handleDrop, false);
            dropArea.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', handleFiles);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
                handleFiles();
            }
            
            // Handle selected files
            function handleFiles() {
                previewContainer.innerHTML = '';
                const files = fileInput.files;
                
                if (!files || files.length === 0) {
                    updateFileInfo();
                    return;
                }
                
                // Limit number of files
                if (files.length > <?= MAX_FILES_PER_UPLOAD ?>) {
                    alert(`You can upload a maximum of <?= MAX_FILES_PER_UPLOAD ?> files. Only the first <?= MAX_FILES_PER_UPLOAD ?> will be selected.`);
                    const dt = new DataTransfer();
                    for (let i = 0; i < Math.min(files.length, <?= MAX_FILES_PER_UPLOAD ?>); i++) {
                        dt.items.add(files[i]);
                    }
                    fileInput.files = dt.files;
                    return handleFiles(); // Recursively process the limited files
                }
                
                // Process each file
                Array.from(files).forEach(file => {
                    if (!file.type.match('image.*')) return;
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = document.createElement('div');
                        preview.className = 'file-preview';
                        preview.innerHTML = `
                            <img src="${e.target.result}" class="w-100 h-100">
                            <div class="remove-btn">×</div>
                            <div class="file-size">${formatSize(file.size)}</div>
                        `;
                        
                        preview.querySelector('.remove-btn').addEventListener('click', (e) => {
                            e.stopPropagation();
                            const dt = new DataTransfer();
                            Array.from(fileInput.files).forEach(f => {
                                if (f !== file) dt.items.add(f);
                            });
                            fileInput.files = dt.files;
                            preview.remove();
                            updateFileInfo();
                        });
                        
                        previewContainer.appendChild(preview);
                    };
                    reader.readAsDataURL(file);
                });
                
                updateFileInfo();
            }
            
            // Form validation
            uploadForm.addEventListener('submit', function(e) {
                const files = fileInput.files;
                
                if (!files || files.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one image to upload');
                    return;
                }
                
                let totalSize = 0;
                Array.from(files).forEach(file => totalSize += file.size);
                
                if (totalSize > <?= MAX_TOTAL_UPLOAD_SIZE ?>) {
                    e.preventDefault();
                    alert('Total upload size exceeds <?= MAX_TOTAL_UPLOAD_SIZE/1024/1024 ?>MB limit');
                    return;
                }
            });
        });
    </script>
</body>
</html>