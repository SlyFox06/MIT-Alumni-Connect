<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "root";         // Default for XAMPP
$dbname = "atharv";     // Ensure this DB exists in phpMyAdmin

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
} else {
    // Optional: for testing purposes
    // echo "✅ Connected to database successfully.";
}
?>

<!-- /* pwd is root 
if issue persists keep the password part empty */ -->