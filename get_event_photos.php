<?php
require 'db_controller.php';
header('Content-Type: application/json');

$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

if (!$event_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid event ID']);
    exit;
}

try {
    // Get event info
    $stmt = $conn->prepare("SELECT * FROM alumni_gallery_events WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$event) {
        echo json_encode(['success' => false, 'error' => 'Event not found']);
        exit;
    }

    // Get all photos for this event
    $stmt = $conn->prepare("SELECT id, image_path, thumbnail_path FROM alumni_gallery_photos WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $photos = [];
    
    while ($photo = $result->fetch_assoc()) {
        // Verify both original and thumbnail exist
        if (file_exists($photo['image_path'])) {
            $photo['thumbnail_exists'] = !empty($photo['thumbnail_path']) && file_exists($photo['thumbnail_path']);
            $photos[] = $photo;
        }
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'event' => $event,
        'photos' => $photos
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>