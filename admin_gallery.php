<?php
session_start();
require_once 'logged_admin.php';
require_once 'db_controller.php';
// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/gallery/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filename = uniqid() . '_' . basename($_FILES['image']['name']);
        $targetPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $stmt = $conn->prepare("INSERT INTO gallery_table (title, description, image_path, uploaded_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $title, $description, $filename, $_SESSION['logged_account']['id']);
            $stmt->execute();
            
            $_SESSION['message'] = "Image uploaded successfully!";
            header("Location: admin_gallery.php");
            exit();
        } else {
            $error = "Error uploading file.";
        }
    } else {
        $error = "Please select an image to upload.";
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Get image path first
    $result = $conn->query("SELECT image_path FROM gallery_table WHERE id = $id");
    if ($result->num_rows > 0) {
        $image = $result->fetch_assoc();
        $filepath = 'uploads/gallery/' . $image['image_path'];
        
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        $conn->query("DELETE FROM gallery_table WHERE id = $id");
        $_SESSION['message'] = "Image deleted successfully!";
        header("Location: admin_gallery.php");
        exit();
    }
}

// Fetch all gallery images
$gallery = $conn->query("SELECT * FROM gallery_table ORDER BY upload_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Gallery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
    <div class="container py-5">
        <h1 class="mb-4">Gallery Management</h1>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['message'] ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Upload New Image</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="image" class="form-label">Image</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                    </div>
                    <button type="submit" name="upload" class="btn btn-primary">Upload</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Gallery Images</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $gallery->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <img src="uploads/gallery/<?= htmlspecialchars($item['image_path']) ?>" 
                                         alt="<?= htmlspecialchars($item['title']) ?>" 
                                         style="width: 100px; height: auto;">
                                </td>
                                <td><?= htmlspecialchars($item['title']) ?></td>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td><?= date('M d, Y', strtotime($item['upload_date'])) ?></td>
                                <td>
                                    <a href="admin_gallery.php?delete=<?= $item['id'] ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this image?')">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>