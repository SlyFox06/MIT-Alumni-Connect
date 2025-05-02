<?php
include 'db_controller.php';
$conn->select_db("atharv");

session_start();

// Check if admin is logged in
if (!isset($_SESSION['logged_account']) || $_SESSION['logged_account']['role'] != 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$action = $data['action'] ?? null;

if ($id && $action) {
    try {
        $status = ($action == 'approve') ? 'Approved' : 'Rejected';
        $stmt = $conn->prepare("UPDATE event_table SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}