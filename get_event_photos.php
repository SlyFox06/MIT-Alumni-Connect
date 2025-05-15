<?php
// Turn off error reporting in production
error_reporting(0);
ini_set('display_errors', 0);

// Set proper headers first
header('Content-Type: application/json');

require 'db_controller.php';

// Validate event ID
$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
if (!$event_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid event ID']);
    exit;
}

try {
    // Get event details
    $stmt = $conn->prepare("SELECT * FROM alumni_gallery_events WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $event_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$event) {
        throw new Exception("Event not found");
    }

    // Get photos for this event
    $stmt = $conn->prepare("SELECT * FROM alumni_gallery_photos WHERE event_id = ? ORDER BY uploaded_at DESC");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $event_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Verify photo files exist
    $valid_photos = [];
    foreach ($photos as $photo) {
        if (file_exists($photo['image_path'])) {
            $valid_photos[] = $photo;
        }
    }

    echo json_encode([
        'success' => true,
        'event' => $event,
        'photos' => $valid_photos
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>