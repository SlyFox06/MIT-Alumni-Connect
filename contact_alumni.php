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
    if (!filter_var($alumnusToContactEmail, FILTER_VALIDATE_EMAIL)) {
        die("Invalid recipient email format");
    }
    
    $stmt = $conn->prepare("SELECT first_name, last_name FROM user_table WHERE email = ?");
    $stmt->bind_param("s", $alumnusToContactEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $alumnusToContact = $result->fetch_assoc();
    $stmt->close();
    
    if (!$alumnusToContact) {
        die("Recipient not found in database");
    }
}

// Initialize variables
$errors = [];
$success = false;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $message = trim($_POST['message']);
    
    // Validate inputs
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($message)) $errors[] = "Message is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (!empty($phone) && !preg_match('/^[0-9]{10,15}$/', $phone)) $errors[] = "Invalid phone number format";
    
    if (empty($errors)) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings - REPLACE WITH YOUR SMTP CREDENTIALS
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'naikatharva34@gmail.com'; // Your Gmail
            $mail->Password   = 'yfxc xibp eiwf frkh';    // Gmail App Password
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->CharSet    = 'UTF-8';
            
            // For debugging (enable only when needed)
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            
            // Determine sender email
            $senderDomain = strtolower(substr(strrchr($email, "@"), 1));
            $receiverDomain = strtolower(substr(strrchr($alumnusToContactEmail, "@"), 1));
            $senderEmail = determineSenderEmail($senderDomain, $receiverDomain);
            
            // Recipients
            $mail->setFrom($senderEmail, 'Atharv Portal - ' . $name);
            $mail->addReplyTo($email, $name); // Allow direct replies
            $mail->addAddress($alumnusToContactEmail);
            
            // Content
            $mail->isHTML(false);
            $mail->Subject = 'Message from ' . $name . ' via MIT Portal';
            
            $emailBody = "Dear " . $alumnusToContact['first_name'] . ",\n\n";
            $emailBody .= "You have received a message through the MIT Alumni Portal:\n\n";
            $emailBody .= "From: $name ($email)\n";
            if (!empty($phone)) $emailBody .= "Phone: $phone\n";
            $emailBody .= "\nMessage:\n" . wordwrap($message, 70) . "\n\n";
            $emailBody .= "--\nThis message was sent via MIT Alumni Portal\n";
            $emailBody .= "To reply directly, please use the email address above.";
            
            $mail->Body = $emailBody;
            
            if ($mail->send()) {
                $_SESSION['message_sent'] = true;
                header("Location: message_success.php?email=" . urlencode($alumnusToContactEmail));
                exit();
            }
        } catch (Exception $e) {
            $errors[] = "Message could not be sent. Mailer Error: " . $e->getMessage();
            error_log("Mailer Error: " . $e->getMessage());
        }
    }
}

// Function to determine sender email
function determineSenderEmail($senderDomain, $receiverDomain) {
    $rules = [
        'gmail.com-company.com' => 'gmail-relay@atharv.edu',
        'yahoo.com-company.com' => 'yahoo-relay@atharv.edu',
        '*-gmail.com' => 'gmail-contact@mit.edu',
        '*-yahoo.com' => 'yahoo-contact@mit.edu',
        'default' => 'noreply@atharv.edu'
    ];
    
    $key = "$senderDomain-$receiverDomain";
    if (isset($rules[$key])) {
        return $rules[$key];
    }
    
    foreach ($rules as $pattern => $sender) {
        if (strpos($pattern, '*-') === 0 && substr($pattern, 2) === $receiverDomain) {
            return $sender;
        }
    }
    
    return $rules['default'];
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
    <title>Contact Alumni | Atharv Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .contact-form {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
        }
        .error-list {
            padding-left: 1.5rem;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="contact-form">
            <h2 class="text-center mb-4">Contact <?php echo htmlspecialchars($alumnusToContact['first_name'] . ' ' . $alumnusToContact['last_name']); ?></h2>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Please fix these errors:</strong>
                    <ul class="error-list">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="name" class="form-label">Your Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" required 
                           value="<?php echo htmlspecialchars($_POST['name'] ?? $loggedUserFullName); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Your Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? $loggedUserEmail); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="phone" class="form-label">Your Phone Number (optional)</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    <small class="text-muted">Format: 10-15 digits, no spaces or special characters</small>
                </div>
                
                <div class="mb-3">
                    <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="message" name="message" rows="5" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send-fill me-2"></i>Send Message
                    </button>
                    <a href="profile_detail.php?email=<?php echo urlencode($alumnusToContactEmail); ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>