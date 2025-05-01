<?php
require_once 'logged_admin.php';
require_once 'db_controller.php';

$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

if (!$event_id) {
    header("Location: manage_gallery.php");
    exit;
}

// Get event details
$stmt = $conn->prepare("SELECT * FROM alumni_gallery_events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    header("Location: manage_gallery.php");
    exit;
}

// Get photos for this event
$stmt = $conn->prepare("SELECT * FROM alumni_gallery_photos WHERE event_id = ? ORDER BY uploaded_at DESC");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gallery Admin - <?= htmlspecialchars($event['event_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .gallery-container {
            column-count: 3;
            column-gap: 1rem;
        }
        .gallery-item {
            break-inside: avoid;
            margin-bottom: 1rem;
            position: relative;
        }
        .gallery-item img {
            width: 100%;
            border-radius: 8px;
            transition: transform 0.3s;
        }
        .gallery-item img:hover {
            transform: scale(1.02);
        }
        .gallery-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .gallery-item:hover .gallery-actions {
            opacity: 1;
        }
        @media (max-width: 768px) {
            .gallery-container {
                column-count: 2;
            }
        }
        @media (max-width: 576px) {
            .gallery-container {
                column-count: 1;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="manage_gallery.php">Gallery Admin</a>
            <a href="manage_gallery.php" class="btn btn-outline-light ms-auto">
                <i class="bi bi-arrow-left"></i> Back to Gallery
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><?= htmlspecialchars($event['event_name']) ?></h2>
            <a href="add_photos.php?event_id=<?= $event_id ?>" class="btn btn-primary">
                <i class="bi bi-plus"></i> Add Photos
            </a>
        </div>

        <?php if (!empty($event['description'])): ?>
            <div class="mb-4">
                <p><?= nl2br(htmlspecialchars($event['description'])) ?></p>
            </div>
        <?php endif; ?>

        <?php if (empty($photos)): ?>
            <div class="alert alert-info">
                No photos found for this event. <a href="add_photos.php?event_id=<?= $event_id ?>">Add some photos</a>.
            </div>
        <?php else: ?>
            <div class="gallery-container">
                <?php foreach ($photos as $photo): ?>
                    <div class="gallery-item">
                        <img src="<?= $photo['thumbnail_path'] ?? $photo['image_path'] ?>" 
                             alt="Gallery photo" 
                             onerror="this.src='<?= $photo['image_path'] ?>'">
                        <div class="gallery-actions">
                            <a href="delete_photo.php?id=<?= $photo['id'] ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this photo?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>