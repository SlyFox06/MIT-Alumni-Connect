<?php
session_start();

if (!isset($_SESSION['message_sent'])) {
    header("Location: view_atharv.php");
    exit();
}

unset($_SESSION['message_sent']);
$alumnusEmail = $_GET['email'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Sent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'nav_user.php' ?>
    
    <div class="container my-5">
        <div class="text-center">
            <div class="mb-4">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
            </div>
            <h2>Message Sent Successfully!</h2>
            <p class="lead">Your message has been sent to the alumni.</p>
            <div class="mt-4">
                <a href="profile_detail.php?email=<?php echo urlencode($alumnusEmail); ?>" class="btn btn-primary me-2">
                    <i class="bi bi-person me-1"></i> Back to Profile
                </a>
                <a href="view_atharv.php" class="btn btn-outline-secondary">
                    <i class="bi bi-people me-1"></i> View All Alumni
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>