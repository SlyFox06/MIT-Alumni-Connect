<?php
session_start();
require_once 'logged_admin.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'atharv');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB per file
define('MAX_TOTAL_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB total
define('MAX_FILES_PER_UPLOAD', 20);
define('UPLOAD_DIR', 'uploads/gallery/');
define('THUMBNAIL_WIDTH', 300);

// Initialize variables
$success = $error = null;
$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
$event = null;
$gd_enabled = extension_loaded('gd');

// Function to establish database connection
function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Function to create thumbnail
function createThumbnail($src, $dest, $targetWidth) {
    if (!function_exists('imagecreatetruecolor')) return false;
    
    $info = getimagesize($src);
    if ($info === false) return false;
    
    list($width, $height, $type) = $info;
    
    switch ($type) {
        case IMAGETYPE_JPEG: $source_image = imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG: $source_image = imagecreatefrompng($src); break;
        case IMAGETYPE_GIF: $source_image = imagecreatefromgif($src); break;
        case IMAGETYPE_WEBP: $source_image = imagecreatefromwebp($src); break;
        default: return false;
    }
    
    if (!$source_image) return false;
    
    $targetHeight = (int)($height * ($targetWidth / $width));
    $virtual_image = imagecreatetruecolor($targetWidth, $targetHeight);
    
    // Handle transparency for PNG and GIF
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

// Validate event ID
if (!$event_id) {
    $error = "Invalid event ID";
} else {
    try {
        $conn = connectDB();
        $stmt = $conn->prepare("SELECT * FROM alumni_gallery_events WHERE id = ?");
        if (!$stmt) throw new Exception("Database error: " . $conn->error);
        
        $stmt->bind_param("i", $event_id);
        if (!$stmt->execute()) throw new Exception("Database error: " . $stmt->error);
        
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();
        $stmt->close();
        
        if (!$event) $error = "Event not found";
        $conn->close();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_photos']) && $event_id && !$error) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Invalid CSRF token");
        }

        // Validate file uploads
        if (!isset($_FILES["gallery_images"]) || empty($_FILES["gallery_images"]["name"][0])) {
            throw new Exception("Please select at least one image to upload");
        }
        
        $file_count = count($_FILES["gallery_images"]["name"]);
        $total_size = array_sum($_FILES["gallery_images"]["size"]);
        
        // Validate file count
        if ($file_count > MAX_FILES_PER_UPLOAD) {
            throw new Exception("You can upload a maximum of " . MAX_FILES_PER_UPLOAD . " files at once");
        }
        
        // Validate total size
        if ($total_size > MAX_TOTAL_UPLOAD_SIZE) {
            throw new Exception("Total upload size exceeds " . (MAX_TOTAL_UPLOAD_SIZE/1024/1024) . "MB limit");
        }

        // Create upload directory if it doesn't exist
        if (!file_exists(UPLOAD_DIR)) {
            if (!mkdir(UPLOAD_DIR, 0755, true)) {
                throw new Exception("Failed to create upload directory. Please check permissions.");
            }
        }
        
        // Verify upload directory is writable
        if (!is_writable(UPLOAD_DIR)) {
            throw new Exception("Upload directory is not writable. Please check permissions.");
        }
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $uploaded_files = [];
        $conn = connectDB();
        
        // Prepare statement for inserting photos
        $stmt = $conn->prepare("INSERT INTO alumni_gallery_photos (event_id, image_path, thumbnail_path) VALUES (?, ?, ?)");
        if (!$stmt) throw new Exception("Database error: " . $conn->error);

        // Process each uploaded file
        foreach ($_FILES["gallery_images"]["tmp_name"] as $key => $tmp_name) {
            $file_error = $_FILES["gallery_images"]["error"][$key];
            $file_name = $_FILES["gallery_images"]["name"][$key];
            $file_size = $_FILES["gallery_images"]["size"][$key];
            
            // Validate upload error
            if ($file_error != UPLOAD_ERR_OK) {
                throw new Exception("Error uploading file '$file_name': " . $this->getUploadError($file_error));
            }
            
            // Validate temporary file exists
            if (!file_exists($tmp_name)) {
                throw new Exception("Temporary file '$file_name' doesn't exist");
            }
            
            // Validate file extension
            $image_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($image_ext, $allowed_exts)) {
                throw new Exception("Invalid file type '$file_name'. Only JPG, JPEG, PNG, GIF, and WebP files are allowed.");
            }
            
            // Validate file size
            if ($file_size > MAX_FILE_SIZE) {
                throw new Exception("File '$file_name' exceeds " . (MAX_FILE_SIZE/1024/1024) . "MB size limit");
            }
            
            // Validate image content
            if (!getimagesize($tmp_name)) {
                throw new Exception("File '$file_name' is not a valid image");
            }
            
            // Generate unique filename
            $new_name = uniqid('img_', true) . '.' . $image_ext;
            $target_file = UPLOAD_DIR . $new_name;
            
            // Move uploaded file
            if (!move_uploaded_file($tmp_name, $target_file)) {
                throw new Exception("Failed to save file '$file_name'");
            }
            
            // Verify file was moved successfully
            if (!file_exists($target_file)) {
                throw new Exception("File '$file_name' was not saved correctly");
            }
            
            // Create thumbnail if GD is enabled
            $thumbnail_path = $gd_enabled ? UPLOAD_DIR . 'thumb_' . $new_name : $target_file;
            if ($gd_enabled && !createThumbnail($target_file, $thumbnail_path, THUMBNAIL_WIDTH)) {
                $thumbnail_path = $target_file; // Fallback to original if thumbnail creation fails
            }
            
            // Add to database
            $stmt->bind_param("iss", $event_id, $target_file, $thumbnail_path);
            if (!$stmt->execute()) {
                // Clean up files if DB insert fails
                if (file_exists($target_file)) unlink($target_file);
                if ($gd_enabled && file_exists($thumbnail_path) && $thumbnail_path != $target_file) {
                    unlink($thumbnail_path);
                }
                throw new Exception("Database error: " . $stmt->error);
            }
            
            $uploaded_files[] = $target_file;
            if ($gd_enabled && $thumbnail_path != $target_file) {
                $uploaded_files[] = $thumbnail_path;
            }
        }
        
        $success = "Successfully uploaded " . count($_FILES["gallery_images"]["name"]) . " photos!";
        
    } catch (Exception $e) {
        // Clean up any uploaded files if error occurred
        if (!empty($uploaded_files)) {
            foreach ($uploaded_files as $file) {
                if (file_exists($file)) @unlink($file);
            }
        }
        $error = $e->getMessage();
    } finally {
        if (isset($stmt)) $stmt->close();
        if (isset($conn)) $conn->close();
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to get upload error message
function getUploadError($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:   return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:  return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
        case UPLOAD_ERR_PARTIAL:    return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:    return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR: return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE: return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:  return 'File upload stopped by extension';
        default:                    return 'Unknown upload error';
    }
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
        :root {
            --primary-color: #002c59;
            --secondary-color: #f8f9fa;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
        }
        
        body.admin-bg { 
            background-color: var(--secondary-color); 
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background-color: var(--primary-color) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
        }
        
        .file-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .file-preview {
            position: relative;
            width: 120px;
            height: 120px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        
        .file-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .file-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .file-preview .remove-btn {
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
            font-size: 14px;
            opacity: 0;
            transition: opacity 0.2s ease;
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
        
        .invalid-file {
            border: 2px solid var(--danger-color);
        }
        
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 1rem;
            background-color: rgba(0, 44, 89, 0.02);
        }
        
        .upload-area:hover {
            border-color: var(--primary-color);
            background-color: rgba(0, 44, 89, 0.05);
        }
        
        .upload-area.active {
            border-color: var(--primary-color);
            background-color: rgba(0, 44, 89, 0.1);
        }
        
        .event-thumbnail {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        
        .progress-container {
            background: rgba(0, 0, 0, 0.05);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .progress {
            height: 10px;
            border-radius: 5px;
        }
        
        .progress-bar {
            background-color: var(--primary-color);
            transition: width 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #001f3d;
            border-color: #001f3d;
        }
        
        .alert {
            border-radius: 8px;
        }
        
        .status-text {
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .file-preview {
                width: 100px;
                height: 100px;
            }
            
            .event-thumbnail {
                max-width: 150px;
                max-height: 150px;
            }
        }
    </style>
</head>
<body class="admin-bg">
    <nav class="navbar sticky-top navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="main_menu_admin.php">
                <i class="bi bi-building me-2"></i>
                <span>MIT Alumni Portal</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="manage_gallery.php">
                            <i class="bi bi-images me-1"></i> Gallery
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="logout.php" class="btn btn-outline-light">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Add Photos to Gallery Event</h2>
            <a href="manage_gallery.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Gallery
            </a>
        </div>
        
        <?php if (!$gd_enabled): ?>
            <div class="alert alert-warning d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
                <div>
                    <h5 class="alert-heading mb-1">GD Library Not Enabled</h5>
                    <p class="mb-0">Thumbnails will not be generated for uploaded images.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2 fs-4"></i>
                <div><?php echo htmlspecialchars($success); ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($event): ?>
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header d-flex align-items-center">
                            <i class="bi bi-info-circle me-2"></i>
                            <h5 class="mb-0">Event Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column flex-md-row align-items-start">
                                <?php
                                $thumbnail_path = null;
                                try {
                                    $conn = connectDB();
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
                                } catch (Exception $e) {
                                    // Silently fail - we'll just show the placeholder
                                }
                                ?>
                                
                                <?php if ($thumbnail_path && file_exists($thumbnail_path)): ?>
                                    <img src="<?php echo htmlspecialchars($thumbnail_path); ?>" class="event-thumbnail me-md-3 mb-3 mb-md-0">
                                <?php else: ?>
                                    <div class="d-flex flex-column align-items-center justify-content-center bg-light p-4 rounded me-md-3 mb-3 mb-md-0" style="width: 200px; height: 200px;">
                                        <i class="bi bi-image text-muted mb-2" style="font-size: 3rem;"></i>
                                        <small class="text-muted text-center">No photos yet</small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex-grow-1">
                                    <h4 class="mb-2"><?php echo htmlspecialchars($event['event_name']); ?></h4>
                                    <p class="text-muted mb-3">
                                        <i class="bi bi-calendar me-1"></i>
                                        <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                                    </p>
                                    <?php if (!empty($event['description'])): ?>
                                        <div class="mb-2">
                                            <h6 class="mb-1">Description:</h6>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header d-flex align-items-center">
                            <i class="bi bi-plus-circle me-2"></i>
                            <h5 class="mb-0">Add New Photos</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event_id); ?>">
                                
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Select Images <span class="text-danger">*</span></label>
                                    <div class="upload-area" id="dropArea">
                                        <i class="bi bi-cloud-arrow-up text-primary" style="font-size: 2.5rem;"></i>
                                        <p class="mt-2 mb-1 fw-medium">Drag & drop images here</p>
                                        <p class="text-muted mb-2">or click to browse files</p>
                                        <small class="text-muted">
                                            Max <?= (MAX_FILE_SIZE/1024/1024) ?>MB per file, 
                                            <?= (MAX_TOTAL_UPLOAD_SIZE/1024/1024) ?>MB total, 
                                            up to <?= MAX_FILES_PER_UPLOAD ?> files
                                        </small>
                                    </div>
                                    <input type="file" class="form-control d-none" name="gallery_images[]" id="fileInput" multiple accept="image/*" required>
                                    <div class="file-preview-container mt-3" id="imagePreviews"></div>
                                    <div class="d-flex justify-content-between mt-2">
                                        <small class="text-muted" id="fileCount">0 files selected</small>
                                        <small class="text-muted" id="totalSize">0MB</small>
                                    </div>
                                </div>
                                
                                <div id="progressContainer" class="d-none">
                                    <div class="progress mb-2">
                                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <div class="text-center">
                                        <small class="status-text fw-medium">Preparing upload...</small>
                                    </div>
                                </div>
                                
                                <button type="submit" name="add_photos" class="btn btn-primary w-100 py-2" id="uploadBtn">
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
            const dropArea = document.getElementById('dropArea');
            const fileInput = document.getElementById('fileInput');
            const imagePreviews = document.getElementById('imagePreviews');
            const fileCount = document.getElementById('fileCount');
            const totalSize = document.getElementById('totalSize');
            const uploadForm = document.getElementById('uploadForm');
            const progressContainer = document.getElementById('progressContainer');
            const progressBar = progressContainer.querySelector('.progress-bar');
            const statusText = progressContainer.querySelector('.status-text');
            const uploadBtn = document.getElementById('uploadBtn');
            
            // Format file size
            const formatFileSize = (bytes) => {
                if (bytes < 1024) return bytes + 'B';
                else if (bytes < 1048576) return (bytes/1024).toFixed(1) + 'KB';
                else return (bytes/1048576).toFixed(1) + 'MB';
            };
            
            // Update file info display
            const updateFileInfo = () => {
                const files = fileInput.files;
                let totalBytes = 0;
                Array.from(files).forEach(file => totalBytes += file.size);
                
                fileCount.textContent = `${files.length} file${files.length !== 1 ? 's' : ''} selected`;
                totalSize.textContent = formatFileSize(totalBytes);
                
                if (totalBytes > <?= MAX_TOTAL_UPLOAD_SIZE ?>) {
                    totalSize.classList.add('text-danger');
                    uploadBtn.disabled = true;
                } else {
                    totalSize.classList.remove('text-danger');
                    uploadBtn.disabled = false;
                }
            };
            
            // Drag and drop functionality
            const preventDefaults = (e) => {
                e.preventDefault();
                e.stopPropagation();
            };
            
            const highlight = () => dropArea.classList.add('active');
            const unhighlight = () => dropArea.classList.remove('active');
            
            const handleDrop = (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
                handleFiles(files);
            };
            
            dropArea.addEventListener('dragenter', highlight, false);
            dropArea.addEventListener('dragover', highlight, false);
            dropArea.addEventListener('dragleave', unhighlight, false);
            dropArea.addEventListener('drop', handleDrop, false);
            dropArea.addEventListener('click', () => fileInput.click());
            
            fileInput.addEventListener('change', function() {
                handleFiles(this.files);
            });
            
            // Handle selected files
            const handleFiles = (files) => {
                imagePreviews.innerHTML = '';
                
                if (!files || files.length === 0) {
                    updateFileInfo();
                    return;
                }
                
                // Limit number of files
                if (files.length > <?= MAX_FILES_PER_UPLOAD ?>) {
                    alert(`You can upload a maximum of <?= MAX_FILES_PER_UPLOAD ?> files at once. Only the first <?= MAX_FILES_PER_UPLOAD ?> will be selected.`);
                    const dataTransfer = new DataTransfer();
                    for (let i = 0; i < Math.min(files.length, <?= MAX_FILES_PER_UPLOAD ?>); i++) {
                        dataTransfer.items.add(files[i]);
                    }
                    fileInput.files = dataTransfer.files;
                    files = dataTransfer.files;
                }
                
                // Process each file
                Array.from(files).forEach((file, index) => {
                    const preview = document.createElement('div');
                    preview.className = 'file-preview';
                    preview.title = file.name;
                    
                    const sizeInfo = document.createElement('div');
                    sizeInfo.className = 'file-size';
                    sizeInfo.textContent = formatFileSize(file.size);
                    
                    const removeBtn = document.createElement('div');
                    removeBtn.className = 'remove-btn';
                    removeBtn.innerHTML = '×';
                    removeBtn.onclick = (e) => {
                        e.stopPropagation();
                        const dataTransfer = new DataTransfer();
                        Array.from(fileInput.files).forEach(f => {
                            if (f !== file) dataTransfer.items.add(f);
                        });
                        fileInput.files = dataTransfer.files;
                        preview.remove();
                        updateFileInfo();
                    };
                    
                    // Check if file is an image
                    if (!file.type.match('image.*')) {
                        preview.classList.add('invalid-file');
                        preview.innerHTML = `
                            <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center p-2 bg-danger text-white">
                                <i class="bi bi-exclamation-triangle-fill mb-1"></i>
                                <small class="text-center">Invalid image</small>
                            </div>
                        `;
                        preview.appendChild(sizeInfo);
                        imagePreviews.appendChild(preview);
                        return;
                    }
                    
                    // Create preview for valid images
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.innerHTML = `<img src="${e.target.result}" alt="${file.name}">`;
                        preview.appendChild(removeBtn);
                        preview.appendChild(sizeInfo);
                        imagePreviews.appendChild(preview);
                    };
                    reader.readAsDataURL(file);
                });
                
                updateFileInfo();
            };
            
            // Form submission with validation
            uploadForm.addEventListener('submit', function(e) {
                const files = fileInput.files;
                
                // Basic validation
                if (!files || files.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one image to upload');
                    return false;
                }
                
                // Check total size
                let totalSize = 0;
                Array.from(files).forEach(file => {
                    totalSize += file.size;
                });
                
                if (totalSize > <?= MAX_TOTAL_UPLOAD_SIZE ?>) {
                    e.preventDefault();
                    alert(`Total upload size exceeds <?= (MAX_TOTAL_UPLOAD_SIZE/1024/1024) ?>MB limit`);
                    return false;
                }
                
                // Check for invalid files
                let hasInvalidFiles = false;
                Array.from(files).forEach(file => {
                    if (!file.type.match('image.*')) hasInvalidFiles = true;
                });
                
                if (hasInvalidFiles) {
                    e.preventDefault();
                    alert('One or more selected files are not valid images');
                    return false;
                }
                
                // Show progress bar
                progressContainer.classList.remove('d-none');
                uploadBtn.disabled = true;
            });
        });
    </script>
</body>
</html>