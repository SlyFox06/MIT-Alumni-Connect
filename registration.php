<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment 2 | Register</title>

    <!-- External CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
</head>
<body>
<?php
session_start();

unset($_SESSION['login_errors']);

// Redirect if already logged in
if (isset($_SESSION['logged_account'])) {
    if ($_SESSION['logged_account']['type'] == 'user') {
        header('Location: main_menu.php');
        exit;
    } elseif ($_SESSION['logged_account']['type'] == 'admin') {
        header('Location: main_menu_admin.php');
        exit;
    }
}

// Get and clear session data
$formData = $_SESSION['form_data'] ?? [];
$errors = $_SESSION['errors'] ?? [];
$verified = $_SESSION['verified'] ?? [];

unset($_SESSION['form_data'], $_SESSION['errors'], $_SESSION['verified']);
?>

<div class="container mt-5 bg-white mainView">
    <div class="row align-items-center">
        <!-- Left image -->
        <div class="col mt-5 ms-5">
            <img class="img-fluid" src="images/signup-image.jpg" alt="Signup image" width="450"/>
        </div>

        <!-- Registration Form -->
        <div class="col my-5 me-5 <?php echo empty($errors) ? 'slide-left' : ''; ?>">
            <h1>Register</h1>
            <form id="registrationForm" class="form-floating needs-validation <?php echo !empty($errors) ? 'animate__animated animate__headShake animate__fast' : ''; ?>" action="process_register.php" method="POST">
                
                <!-- First and Last Name -->
                <div class="row">
                    <div class="col">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control <?php echo isset($errors['firstName']) ? 'is-invalid' : (isset($verified['firstName']) ? 'is-valid' : ''); ?>" id="firstName" name="firstName" placeholder="John" value="<?php echo htmlspecialchars($formData['firstName'] ?? ''); ?>">
                            <label for="firstName">First Name<strong class="text-danger">*</strong></label>
                            <?php echo isset($errors['firstName']) ? '<div class="invalid-feedback">' . $errors['firstName'] . '</div>' : ''; ?>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control <?php echo isset($errors['lastName']) ? 'is-invalid' : (isset($verified['lastName']) ? 'is-valid' : ''); ?>" id="lastName" name="lastName" placeholder="Doe" value="<?php echo htmlspecialchars($formData['lastName'] ?? ''); ?>">
                            <label for="lastName">Last Name<strong class="text-danger">*</strong></label>
                            <?php echo isset($errors['lastName']) ? '<div class="invalid-feedback">' . $errors['lastName'] . '</div>' : ''; ?>
                        </div>
                    </div>
                </div>

                <!-- DOB and Gender -->
                <div class="row">
                    <div class="col">
                        <div class="form-floating mb-3">
                            <input type="date" class="form-control <?php echo isset($errors['dob']) ? 'is-invalid' : (isset($verified['dob']) ? 'is-valid' : ''); ?>" id="dob" name="dob" value="<?php echo htmlspecialchars($formData['dob'] ?? ''); ?>">
                            <label for="dob">Date of Birth<strong class="text-danger">*</strong></label>
                            <?php echo isset($errors['dob']) ? '<div class="invalid-feedback">' . $errors['dob'] . '</div>' : ''; ?>
                        </div>
                    </div>
                    <div class="col">
                        <p class="mb-1">Gender<strong class="text-danger">*</strong></p>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input <?php echo isset($errors['gender']) ? 'is-invalid' : (isset($verified['gender']) ? 'is-valid' : ''); ?>" type="radio" name="gender" id="genderFemale" value="Female" <?php echo (isset($formData['gender']) && $formData['gender'] === 'Female') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="genderFemale">Female</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input <?php echo isset($errors['gender']) ? 'is-invalid' : (isset($verified['gender']) ? 'is-valid' : ''); ?>" type="radio" name="gender" id="genderMale" value="Male" <?php echo (isset($formData['gender']) && $formData['gender'] === 'Male') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="genderMale">Male</label>
                        </div>
                        <?php echo isset($errors['gender']) ? '<div class="invalid-feedback d-block">' . $errors['gender'] . '</div>' : ''; ?>
                    </div>
                </div>

                <!-- Email and Hometown -->
                <div class="row">
                    <div class="col">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : (isset($verified['email']) ? 'is-valid' : ''); ?>" id="email" name="email" placeholder="johndoe@email.com" value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>">
                            <label for="email">Email<strong class="text-danger">*</strong></label>
                            <?php echo isset($errors['email']) ? '<div class="invalid-feedback">' . $errors['email'] . '</div>' : ''; ?>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control <?php echo isset($errors['hometown']) ? 'is-invalid' : (isset($verified['hometown']) ? 'is-valid' : ''); ?>" id="hometown" name="hometown" placeholder="Kuching" value="<?php echo htmlspecialchars($formData['hometown'] ?? ''); ?>">
                            <label for="hometown">Hometown<strong class="text-danger">*</strong></label>
                            <?php echo isset($errors['hometown']) ? '<div class="invalid-feedback">' . $errors['hometown'] . '</div>' : ''; ?>
                        </div>
                    </div>
                </div>

                <!-- Passwords -->
                <div class="row">
                    <div class="col">
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" placeholder="Password">
                            <label for="password">Password<strong class="text-danger">*</strong></label>
                            <?php echo isset($errors['password']) ? '<div class="invalid-feedback">' . $errors['password'] . '</div>' : ''; ?>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control <?php echo isset($errors['confirmPassword']) ? 'is-invalid' : ''; ?>" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password">
                            <label for="confirmPassword">Confirm Password<strong class="text-danger">*</strong></label>
                            <?php echo isset($errors['confirmPassword']) ? '<div class="invalid-feedback">' . $errors['confirmPassword'] . '</div>' : ''; ?>
                        </div>
                    </div>
                </div>

                <!-- Submit and Reset -->
                <div class="row">
                    <div class="d-grid gap-2 col-12 mx-auto">
                        <button type="submit" class="btn btn-primary py-2 fw-medium">Register</button>
                    </div>
                </div>

                <div class="row mt-3 mb-5 justify-content-between align-items-center">
                    <div class="col">
                        <span class="text-secondary fst-italic"><strong class="text-danger">*</strong> Indicates required field</span>
                    </div>
                    <div class="col-auto">
                        <button type="reset" class="btn btn-outline-danger me-2" onclick="resetValidation(event)">Reset Form</button>
                    </div>
                </div>
            </form>

            <hr/>

            <!-- Login redirect -->
            <div class="row mt-5">
                <div class="col-auto pe-0"><span class="fw-medium">Already have an account?</span></div>
                <div class="col ps-2"><a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="login.php">Login</a></div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Reset form validation and values
function resetValidation(event) {
    event.preventDefault();
    document.querySelectorAll(".is-invalid, .is-valid").forEach(el => el.classList.remove("is-invalid", "is-valid"));
    document.querySelectorAll("input").forEach(input => {
        if (!(input.type === "radio" && input.name === "gender")) input.value = "";
    });
    document.querySelectorAll(".invalid-feedback").forEach(msg => msg.innerHTML = "");
}
</script>

</body>
</html>
