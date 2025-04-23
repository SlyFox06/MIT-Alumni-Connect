<?php
require 'vendor/autoload.php'; // Load PHPMailer
include 'db_controller.php';
$conn->select_db("atharv");

session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_account'])) {
    header("Location: login.php");
    exit();
}

// Get the alumni to contact
$alumnusToContactEmail = $_GET['email'] ?? '';
$alumnusToContact = null;

if ($alumnusToContactEmail) {
    $stmt = $conn->prepare("SELECT first_name, last_name FROM user_table WHERE email = ?");
    $stmt->bind_param("s", $alumnusToContactEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $alumnusToContact = $result->fetch_assoc();
    $stmt->close();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'] ?? '';
    $message = $_POST['message'];
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($message)) {
        $error = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Create PHPMailer instance
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; // Gmail SMTP server
            $mail->SMTPAuth   = true;
            $mail->Username   = 'your@gmail.com'; // Your Gmail
            $mail->Password   = 'your-app-password'; // App password
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            
            // Recipients
            $mail->setFrom($email, $name);
            $mail->addAddress($alumnusToContactEmail);
            
            // Content
            $mail->isHTML(false);
            $mail->Subject = 'Message from atharv Portal';
            $mail->Body    = "You have received a new message from $name ($email, $phone):\n\n$message";
            
            $mail->send();
            $_SESSION['message_sent'] = true;
            header("Location: message_success.php?email=" . urlencode($alumnusToContactEmail));
            exit();
        } catch (Exception $e) {
            $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }
}

// Get logged in user's info
$loggedUserFirstName = $_SESSION['logged_account']['first_name'] ?? '';
$loggedUserLastName = $_SESSION['logged_account']['last_name'] ?? '';
$loggedUserFullName = trim("$loggedUserFirstName $loggedUserLastName");
$loggedUserEmail = $_SESSION['logged_account']['email'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Alumni</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .contact-form {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    
    
    <div class="container my-5">
        <div class="contact-form">
            <h2 class="text-center mb-4">Contact <?php echo htmlspecialchars($alumnusToContact['first_name'] . ' ' . $alumnusToContact['last_name']); ?></h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="name" class="form-label">Your Name</label>
                    <input type="text" class="form-control" id="name" name="name" required 
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($loggedUserFullName); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Your Email</label>
                    <input type="email" class="form-control" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($loggedUserEmail); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="phone" class="form-label">Your Phone Number (optional)</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>
                
                <div class="mb-3">
                    <label for="message" class="form-label">Message</label>
                    <textarea class="form-control" id="message" name="message" rows="5" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send-fill me-2"></i>Send Message</button>
                    <a href="profile_detail.php?email=<?php echo urlencode($alumnusToContactEmail); ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>