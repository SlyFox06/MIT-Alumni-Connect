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
        'default' => 'noreply@mit.edu'
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
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --accent-color: #2e59d9;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .contact-form {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2.5rem;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .contact-form:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }
        
        .error-list {
            padding-left: 1.5rem;
            margin-bottom: 0;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s;
            border: 1px solid #ced4da;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
            transform: scale(1.02);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            background: linear-gradient(to right, var(--primary-color) 0%, var(--accent-color) 100%);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.3);
        }
        
        .btn-outline-secondary {
            transition: all 0.3s;
            padding: 12px;
            border-radius: 8px;
        }
        
        .btn-outline-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.1);
        }
        
        textarea {
            resize: none;
            min-height: 150px;
        }
        
        .floating-label {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .floating-label label {
            position: absolute;
            top: 12px;
            left: 15px;
            color: #6c757d;
            transition: all 0.3s;
            pointer-events: none;
            background-color: white;
            padding: 0 5px;
        }
        
        .floating-label input:focus + label,
        .floating-label input:not(:placeholder-shown) + label,
        .floating-label textarea:focus + label,
        .floating-label textarea:not(:placeholder-shown) + label {
            top: -10px;
            left: 10px;
            font-size: 0.8rem;
            color: var(--primary-color);
            background-color: white;
        }
        
        .pulse-once {
            animation: pulse 1s;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.03); }
            100% { transform: scale(1); }
        }
        
        .shake-on-error {
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
        
        .error-message {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .header-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="contact-form animate__animated animate__fadeInUp">
            <div class="text-center">
                <i class="bi bi-envelope-paper header-icon"></i>
                <h2 class="mb-4">Contact <?php echo htmlspecialchars($alumnusToContact['first_name'] . ' ' . $alumnusToContact['last_name']); ?></h2>
                <p class="text-muted mb-4">Send a message to connect with this alumni</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger animate__animated animate__headShake">
                    <strong>Please fix these errors:</strong>
                    <ul class="error-list">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="needs-validation" novalidate>
                <div class="floating-label mb-4">
                    <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid shake-on-error' : ''; ?>" 
                           id="name" name="name" required placeholder=" "
                           value="<?php echo htmlspecialchars($_POST['name'] ?? $loggedUserFullName); ?>">
                    <label for="name">Your Name <span class="text-danger">*</span></label>
                    <?php if (isset($errors['name'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['name']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="floating-label mb-4">
                    <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid shake-on-error' : ''; ?>" 
                           id="email" name="email" required placeholder=" "
                           value="<?php echo htmlspecialchars($_POST['email'] ?? $loggedUserEmail); ?>">
                    <label for="email">Your Email <span class="text-danger">*</span></label>
                    <?php if (isset($errors['email'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['email']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="floating-label mb-4">
                    <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid shake-on-error' : ''; ?>" 
                           id="phone" name="phone" placeholder=" "
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    <label for="phone">Your Phone Number (optional)</label>
                    <small class="text-muted d-block mt-1"></small>
                    <?php if (isset($errors['phone'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['phone']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="floating-label mb-4">
                    <textarea class="form-control <?php echo isset($errors['message']) ? 'is-invalid shake-on-error' : ''; ?>" 
                              id="message" name="message" required placeholder=" "><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    <label for="message">Message <span class="text-danger">*</span></label>
                    <?php if (isset($errors['message'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['message']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="d-grid gap-3">
                    <button type="submit" class="btn btn-primary pulse-once">
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
    <script>
        // Add animation to form elements on load
        document.addEventListener('DOMContentLoaded', function() {
            // Animate form elements sequentially
            const formElements = document.querySelectorAll('.floating-label');
            formElements.forEach((el, index) => {
                setTimeout(() => {
                    el.classList.add('animate__animated', 'animate__fadeInUp');
                    el.style.setProperty('--animate-duration', '0.5s');
                    el.style.setProperty('--animate-delay', `${index * 0.1}s`);
                }, 100);
            });
            
            // Add pulse animation to submit button
            setTimeout(() => {
                document.querySelector('.btn-primary').classList.add('pulse-once');
            }, 1000);
            
            // Form validation
            const form = document.querySelector('.needs-validation');
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    // Add shake animation to invalid fields
                    const invalidFields = form.querySelectorAll(':invalid');
                    invalidFields.forEach(field => {
                        field.classList.add('shake-on-error');
                        setTimeout(() => {
                            field.classList.remove('shake-on-error');
                        }, 500);
                    });
                }
                
                form.classList.add('was-validated');
            }, false);
            
            // Phone number formatting
            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
        });
    </script>
</body>
</html>