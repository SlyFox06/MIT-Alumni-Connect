<?php
header('Content-Type: application/json');
require 'db_controller.php';

// Validate event ID
$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
if (!$event_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid event ID']);
    exit;
}

// Get event details
$stmt = $conn->prepare("SELECT * FROM alumni_gallery_events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    echo json_encode(['success' => false, 'error' => 'Event not found']);
    exit;
}

// Get photos for this event
$stmt = $conn->prepare("SELECT * FROM alumni_gallery_photos WHERE event_id = ? ORDER BY uploaded_at DESC");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

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
?>