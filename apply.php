<?php
require 'db_connect.php';
require 'flash_message.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad_id = $_POST['advertisement_id'] ?? null;
    $user_id = $_SESSION['user_id'] ?? 1; // Use actual user session in real app

    if (!$ad_id) {
        set_flash_message("Invalid Advertisement ID!", "error");
        header("Location: view_advertisements.php");
        exit;
    }

    // Check if already applied
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE user_id = ? AND advertisement_id = ?");
    $stmt->execute([$user_id, $ad_id]);

    if ($stmt->rowCount() > 0) {
        set_flash_message("You have already applied for this advertisement.", "error");
    } else {
        $stmt = $pdo->prepare("INSERT INTO applications (user_id, advertisement_id, applied_on) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $ad_id]);
        set_flash_message("Application submitted successfully!");
    }

    header("Location: view_advertisements.php");
    exit;
}
?>

