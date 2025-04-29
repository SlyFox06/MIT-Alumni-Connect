<?php
session_start();
require_once 'db_controller.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'event' => null,
    'photos' => [],
    'error' => null
];

try {
    // Validate input
    $event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
    
    if ($event_id <= 0) {
        throw new Exception("Invalid event ID provided");
    }

    // Create database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Get event details
    $stmt = $conn->prepare("
        SELECT id, event_name, event_date, description, thumbnail_path 
        FROM alumni_gallery_events 
        WHERE id = ? AND is_active = 1
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Event not found or not active");
    }

    $response['event'] = $result->fetch_assoc();
    $stmt->close();

    // Get all photos for this event
    $stmt = $conn->prepare("
        SELECT id, image_path, upload_date 
        FROM alumni_gallery_photos 
        WHERE event_id = ? 
        ORDER BY upload_date ASC
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Verify the image file exists before including it
        if (file_exists($row['image_path'])) {
            $response['photos'][] = [
                'id' => $row['id'],
                'image_path' => $row['image_path'],
                'upload_date' => $row['upload_date']
            ];
        } else {
            error_log("Missing image file: " . $row['image_path']);
        }
    }
    
    $stmt->close();
    $conn->close();

    $response['success'] = true;

} catch (Exception $e) {
    http_response_code(400);
    $response['error'] = $e->getMessage();
    error_log("get_event_photos.php error: " . $e->getMessage());
}

echo json_encode($response);
?>