<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment 2 | Login</title>

    <link rel="stylesheet" href="css/styles.css">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <!-- Hover.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/hover.css/2.3.1/css/hover-min.css" />
    <style>
        /* Custom animations */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        
        .float-animation {
            animation: float 4s ease-in-out infinite;
        }
        
        .fade-in {
            animation: fadeIn 1s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .mainView {
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .mainView:hover {
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            transform: translateY(-5px);
        }
        
        .btn-login {
            transition: all 0.3s;
            background: linear-gradient(to right, #4facfe 0%, #00f2fe 100%);
            border: none;
            letter-spacing: 1px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .form-control:focus {
            border-color: #4facfe;
            box-shadow: 0 0 0 0.25rem rgba(79, 172, 254, 0.25);
        }
        
        .input-highlight {
            transition: all 0.3s;
        }
        
        .input-highlight:focus-within {
            transform: scale(1.02);
        }
        
        .link-hover-effect {
            transition: all 0.3s;
            position: relative;
        }
        
        .link-hover-effect:after {
            content: '';
            position: absolute;
            width: 100%;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: #4facfe;
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .link-hover-effect:hover:after {
            transform: scaleX(1);
        }
        
        .pulse-once {
            animation: pulse 1s;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body class="gradient-bg">
    <?php
        include 'db_controller.php';
        $conn->select_db("atharv");

        session_start();
    ?>

    <div class="container-fluid">
        <!-- Flash message -->
        <?php if (isset($tempFlash)){ ?>
            <div class="row justify-content-center position-absolute top-0 start-50 translate-middle mt-5">
                <div class="col-auto">
                    <div class="alert <?php echo (isset($_SESSION['flash_mode'])) ? $_SESSION['flash_mode'] : '' . (isset($tempFlash) ? $tempFlash : ''); ?> mt-4 py-2 fade-out-alert row align-items-center animate__animated animate__fadeInDown" role="alert">
                        <i class="bi <?php echo (isset($tempFlash) && $tempFlash == "alert-success" ? "bi-check-circle" : ((isset($tempFlash) && $tempFlash == "alert-primary" ? "bi-info-circle" : ((isset($tempFlash) && ($tempFlash == "alert-danger" || $tempFlash == "alert-warning") ? "bi-exclamation-triangle" : ""))))) ?> login-bi col-auto px-0"></i><div class="col ms-1"><?php echo isset($_SESSION['flash']) ? $_SESSION['flash'] : '' ?></div>
                    </div>
                </div>
            </div>
        <?php } ?>

        <div class="container mt-5 bg-white mainView animate__animated animate__fadeIn">
            <?php
                if ($_SERVER["REQUEST_METHOD"] == "POST") {
                    unset($loginErrors);
                    foreach ($_POST as $key => $value) {
                        //trim every key except password
                        if ($key != "password") {
                            $_POST[$key] = trim($_POST[$key]);
                            $value = trim($value);
                        }

                        // check for empty email field
                        if ($key == 'email' && $value == '')
                            $loginErrors[$key] = '*Email is required.';

                        // check for empty password field
                        if ($key == 'password' && $value == '')
                            $loginErrors[$key] = '*Password is required.';
                    }

                    if (!isset($loginErrors['email'])) {
                        // Find password from entered email
                        $SQLLoginAccount = $conn->prepare("SELECT password FROM account_table WHERE email = ?");
                        $SQLLoginAccount->bind_param("s",$_POST['email']);
                        $SQLLoginAccount->execute();
                        $result = $SQLLoginAccount->get_result();
                        if ($result->num_rows > 0) {
                            $hashedPassword = $result->fetch_assoc()['password'];

                            // Verify password against hashed password saved in DB
                            if (password_verify($_POST['password'], $hashedPassword)) {
                                unset($_SESSION['flash_mode']);
                                unset($_SESSION['flash']);
                                unset($loginErrors);

                                // Get and save account info from account_table into session
                                $SQLGetAccountInfo = $conn->prepare("SELECT email, type, status FROM account_table WHERE email = ?");
                                $SQLGetAccountInfo->bind_param("s", $_POST['email']);
                                $SQLGetAccountInfo->execute();
                                $accountInfo = $SQLGetAccountInfo->get_result()->fetch_assoc();
                                $_SESSION['logged_account'] = $accountInfo;

                                // Get and save user info from user_table into session
                                $SQLGetUserInfo = $conn->prepare("SELECT * FROM user_table WHERE email = ?");
                                $SQLGetUserInfo->bind_param("s", $_POST['email']);
                                $SQLGetUserInfo->execute();
                                $userInfo = $SQLGetUserInfo->get_result()->fetch_assoc();
                                $_SESSION['logged_user'] = $userInfo;

                                // Redirects user to the page they tried to visit, if none, main_menu.php
                                header('Location: '.(isset($_SESSION['redirect_url']) ? basename($_SESSION['redirect_url']) : ($_SESSION['logged_account']['type'] == 'user' ? 'main_menu.php' : 'main_menu_admin.php') ));
                                unset($_SESSION['redirect_url']);
                            } elseif (!isset($loginErrors['password'])) {
                                $loginErrors['email'] = '';
                                $loginErrors['password'] = 'Invalid email or password';
                            }
                        // No result means email is not registered
                        } else {
                            $loginErrors['email'] = '*Email is not registered. <strong><a class="link-underline link-underline-opacity-0 link-hover-effect" href="registration.php">Register here</a></strong>.';
                            unset($loginErrors['password']);
                            $verified['email'] = true;
                            $_SESSION['form_data'] = $_POST;
                        }

                        // If there are invalid data, retrieve form data, login_errors, verified fields
                        if (!empty($loginErrors)) {
                            $_SESSION['form_data'] = $_POST;
                            $_SESSION['login_errors'] = $loginErrors;
                        }
                    }
                }
            ?>
            
            <div class="row align-items-center my-5 mx-5">
                <div class="col ms-3 mt-5 mb-5">
                    <img class="img-fluid hvr-bob" src="images/signin-image.jpg" alt="Signin image" width="450"/>
                </div>
                <div class="col <?php echo (isset($loginErrors) && empty($loginErrors)) ? 'slide-left animate__animated animate__fadeInRight' : 'animate__animated animate__fadeIn' ?>">
                    <h1 class="display-4 mb-4 animate__animated animate__fadeInDown">Welcome Back</h1>
                    <p class="text-muted mb-4 animate__animated animate__fadeIn animate__delay-1s">Please enter your credentials to login.</p>
                    
                    <form id="registrationForm" class="form-floating needs-validation <?php echo (isset($loginErrors) && !empty($loginErrors)) ? 'animate__animated animate__headShake animate__fast' : NULL ?>" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                        <!-- Email -->
                        <div class="row mb-4 input-highlight">
                            <div class="col">
                                <div class="form-floating">
                                    <input type="email" class="form-control <?php echo (isset($loginErrors['email'])) ? 'is-invalid animate__animated animate__shakeX' : ''; ?>" id="email" name="email" placeholder="johndoe@email.com" maxlength="50" value="<?php echo isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>">
                                    <label for="email"><i class="bi bi-envelope me-2"></i>Email</label>
                                    <?php echo (isset($loginErrors['email'])) ? '<div class="invalid-feedback animate__animated animate__fadeIn">' . $loginErrors['email'] . '</div>' : ''; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="row mb-4 input-highlight">
                            <div class="col">
                                <div class="form-floating">
                                    <input type="password" class="form-control <?php echo (isset($loginErrors['password'])) ? 'is-invalid animate__animated animate__shakeX' : ''; ?>" id="password" name="password" placeholder="123456">
                                    <label for="password"><i class="bi bi-lock me-2"></i>Password</label>
                                    <?php echo (isset($loginErrors['password'])) ? '<div class="invalid-feedback animate__animated animate__fadeIn">' . $loginErrors['password'] . '</div>' : ''; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Remember me & Forgot password -->
                        <div class="row mb-4 justify-content-between">
                            <div class="col-auto">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="rememberMe">
                                    <label class="form-check-label" for="rememberMe">
                                        Remember me
                                    </label>
                                </div>
                            </div>
                            <div class="col-auto">
                                <a href="#" class="text-decoration-none link-hover-effect">Forgot password?</a>
                            </div>
                        </div>
                        
                        <!-- Login button -->
                        <div class="row mb-5">
                            <div class="d-grid gap-2 col-12 mx-auto">
                                <button type="submit" class="btn btn-login py-3 fw-bold hvr-float">LOG IN</button>
                            </div>
                        </div>
                    </form>

                    <hr class="my-4"/>

                    <div class="row mt-4 justify-content-start align-items-center animate__animated animate__fadeIn animate__delay-1s">
                        <div class="col-auto pe-0"><span class="fw-medium">Don't have an account?</span></div>
                        <div class="col ps-2"><a class="text-decoration-none link-hover-effect fw-bold" href="registration.php">Register now</a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Boostrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script>
        // Reset all validations (mostly on Bootstrap)
        function resetValidation(){
            // Remove error classes and messages
            while (document.getElementsByClassName("is-invalid").length !== 0)
                document.getElementsByClassName("is-invalid")[0].classList.remove("is-invalid");

            // Remove valid classes and messages
            while (document.getElementsByClassName("is-valid").length !== 0)
                document.getElementsByClassName("is-valid")[0].classList.remove("is-valid");

            // Clear error messages
            document.querySelectorAll('.invalid-feedback').forEach(errorMessage => { errorMessage.innerHTML = ''; })
        }

        // Ignore confirm form resubmission after POST request to login
        if (window.history.replaceState)
            window.history.replaceState(null, null, window.location.href);
            
        // Add pulse animation to the login button on page load
        document.addEventListener('DOMContentLoaded', function() {
            const loginBtn = document.querySelector('.btn-login');
            setTimeout(() => {
                loginBtn.classList.add('pulse-once');
            }, 1000);
            
            // Add hover effect to form inputs
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.parentElement.classList.add('input-highlight');
                });
                input.addEventListener('blur', function() {
                    this.parentElement.parentElement.classList.remove('input-highlight');
                });
            });
        });
    </script>
</body>
</html>