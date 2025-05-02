<?php
ob_start();
include 'db_controller.php';
$conn->select_db("atharv");

session_start();

// Create directories if they don't exist
if (!file_exists('uploads/profile_pics')) {
    mkdir('uploads/profile_pics', 0755, true);
}
if (!file_exists('uploads/resumes')) {
    mkdir('uploads/resumes', 0755, true);
}

include 'logged_user.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Detail</title>
    <link rel="stylesheet" href="css/styles.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .card {
            width: 25rem;
            border: none;
            box-shadow: 0 2px 2px rgba(0,0,0,.08), 0 0 6px rgba(0,0,0,.05);
            transition: 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,.1);
            background-color: #E4E6E9;
        }

        .profilePicture {
            width: 250px;
            height: 250px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .profilePicture:hover {
            transform: scale(1.05);
        }

        .profilePictureThumbnail {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .middle-dot {
            display: inline-block;
            width: 4px;
            height: 4px;
            background-color: #6c757d;
            border-radius: 50%;
            vertical-align: middle;
            margin: 0 6px;
        }

        /* Animations */
        .slide-left {
            animation: slideInLeft 0.5s ease-out;
        }

        .fade-in {
            animation: fadeIn 0.8s ease-in;
        }

        @keyframes slideInLeft {
            from {
                transform: translateX(-50px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .btn-primary, .btn-success {
            transition: all 0.3s ease;
            transform: translateY(0);
        }

        .btn-primary:hover, .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .navbar {
            transition: all 0.3s ease;
        }

        .navbar-brand {
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <!-- Top nav bar -->
    <nav class="navbar sticky-top navbar-expand-lg navbar-light bg-light fade-in">
        <div class="container">
            <a class="navbar-brand mx-0 mb-0 h1" href="main_menu.php">MIT Alumni Portal</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse me-5" id="navbarSupportedContent">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item mx-1">
                        <a class="nav-link px-5" href="main_menu.php"><i class="bi bi-house-door"></i></a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link px-5" href="view_atharv.php"><i class="bi bi-people"></i></a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link px-5" href="view_events.php"><i class="bi bi-calendar-event"></i></a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link px-5" href="view_advertisements.php"><i class="bi bi-megaphone"></i></a>
                    </li>
                </ul>
            </div>
            <?php include 'nav_user.php' ?>
        </div>
    </nav>

    <?php include 'get_alumnus_by_email.php'; ?>

    <!-- Breadcrumb -->
    <div class="container my-3 fade-in">
        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a class="breadcrumb-link text-secondary" href="main_menu.php">Home</a></li>
                <?php echo (isset($alumnusToViewEmail) && $alumnusToViewEmail != $_SESSION['logged_account']['email']) ? '<li class="breadcrumb-item"><a class="breadcrumb-link text-secondary" href="view_atharv.php">Alumni Friends</a></li>' : ''; ?>
                <li class="breadcrumb-item active" aria-current="page"><?php echo (isset($alumnusToView)) ? $alumnusToViewName : '?'; ?></li>
            </ol>
        </nav>
    </div>

    <?php if (isset($alumnusToView)): ?>
        <div class="container py-4 px-4 bg-white rounded shadow fade-in">
            <div class="row">
                <!-- Profile image -->
                <div class="col-auto mx-3">
                    <?php
                        $profilePicPath = 'uploads/profile_pics/'.$alumnusToViewProfilePicture;
                        if (!empty($alumnusToViewProfilePicture) && file_exists($profilePicPath)) {
                            echo '<div class="image-container"><img src="'.$profilePicPath.'" class="img-fluid profilePicture ms-1" alt="profile_picture"></div>';
                        } elseif ($alumnusToViewGender == "Male") {
                            echo '<div class="image-container"><img src="uploads/profile_pics/default-male-user-profile-icon.jpg" class="img-fluid profilePicture ms-1" alt="profile_picture"></div>';
                        } else {
                            echo '<div class="image-container"><img src="uploads/profile_pics/default-female-user-profile-icon.jpg" class="img-fluid profilePicture ms-1" alt="profile_picture"></div>';
                        }
                    ?>
                </div>

                <div class="col">
                    <!-- Resume -->
                    <div class="row">
                        <div class="col"><?php echo (isset($alumnusToViewName)) ? '<h2 class="mb-0">'.$alumnusToViewName.'</h2>' : ''; ?></div>
                        <?php if (isset($alumnusToViewResume) && $alumnusToViewResume != ''): ?>
                            <form action="view_resume.php" method="GET" class="col-auto" target="_blank">
                                <input type="hidden" name="resume" value="<?php echo htmlspecialchars($alumnusToViewResume); ?>">
                                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-file-earmark-text me-2"></i>View resume</button>
                            </form>
                        <?php else: ?>
                            <form class="col-auto">
                                <button class="btn btn-primary disabled px-4" aria-disabled="true"><i class="bi bi-file-earmark-text me-2"></i>View resume</button>
                            </form>
                        <?php endif; ?>
                        
                        <form action="contact_alumni.php" method="GET" class="col-auto">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($alumnusToViewEmail); ?>">
                            <button type="submit" class="btn btn-success px-4"><i class="bi bi-chat-left-text me-2"></i>Talk to</button>
                        </form>
                        
                        <?php if ($alumnusToViewEmail == $_SESSION['logged_account']['email']): ?>
                            <div class="col-auto">
                                <a role="button" href="update_profile.php?email=<?= htmlspecialchars($_SESSION['logged_account']['email']) ?>" class="btn btn-secondary px-3">
                                    <i class="bi bi-pencil-fill me-2"></i>Update profile
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Job, Company, Current Location -->
                    <div class="row">
                        <div class="col">
                            <?php
                                echo (isset($alumnusToViewJobPosition)) && $alumnusToViewJobPosition != '' ? '<span class="mb-2">'.$alumnusToViewJobPosition.'</span>' : '';
                                echo (isset($alumnusToViewJobPosition)) && $alumnusToViewJobPosition != '' && isset($alumnusToViewCompany) && $alumnusToViewCompany != '' ? '<span> at </span>' : '';
                                echo (isset($alumnusToViewCompany)) && $alumnusToViewCompany != '' ? '<span>'.$alumnusToViewCompany.'</span>' : '';
                                echo (((isset($alumnusToViewCompany))) && $alumnusToViewCompany != '') || (isset($alumnusToViewJobPosition) && $alumnusToViewJobPosition != '') && isset($alumnusToViewCurrentLocation) && $alumnusToViewCurrentLocation != '' ? '<span class="middle-dot mx-1"></span>' : '';
                                echo (isset($alumnusToViewCurrentLocation)) && $alumnusToViewCurrentLocation != '' ? '<span>'.$alumnusToViewCurrentLocation.'</span>' : '';
                            ?>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="row mb-3">
                        <div class="col">
                            <?php echo (isset($alumnusToViewEmail)) ? '<a class="text-decoration-none" href="mailto:'.$alumnusToViewEmail.'">'.$alumnusToViewEmail.'</a>' : '';?>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Hometown -->
                        <div class="col">
                            <?php echo (isset($alumnusToViewHometown)) ? '<h4 class="mt-3 mb-2 pt-0">Hometown</h4><p class="my-0 ms-3">'.$alumnusToViewHometown.'</p>' : ''; ?>
                        </div>
                        <!-- Degree, Campus, Year Graduated -->
                        <div class="col">
                            <?php
                                echo ((isset($alumnusToViewDegree) && $alumnusToViewDegree != '') || (isset($alumnusToViewDegreeYearGraduated) && $alumnusToViewDegreeYearGraduated != '') || (isset($alumnusToViewCampus) && $alumnusToViewCampus != '')) ? '<h4 class="mt-3 mb-2 pt-0">Education</h4>' : '';
                                echo (isset($alumnusToViewCampus)) ? '<p class="my-0 ms-3">'.$alumnusToViewCampus.'</p>' : '';
                                echo (isset($alumnusToViewDegree)) ? '<p class="my-0 ms-3">'.$alumnusToViewDegree.'</p>' : '';
                                echo (isset($alumnusToViewDegreeYearGraduated) && $alumnusToViewDegreeYearGraduated != '') ? '<p class="my-0 ms-3 text-muted">Graduated in '.$alumnusToViewDegreeYearGraduated.'</p>' : '';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- View alumni friends -->
        <div class="container my-5 fade-in">
            <div class="row">
                <!-- Title -->
                <h3><?php echo ($alumnusToViewEmail == $_SESSION['logged_account']['email']) ? 'Your Alumni Friends' : 'Other Alumni Friends'; ?></h3>

                <!-- Search box -->
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control py-3 my-3" placeholder="Search" name="search" value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($alumnusToViewEmail); ?>">
                        <button class="btn btn-primary px-4 py-3 my-3" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </form>

                <?php
                    $loggedAccountEmail = $_SESSION['logged_account']['email'];

                    // Perform search on query, otherwise simply fetch all
                    $searchQuery = "";
                    if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search'])) {
                        $searchQuery = trim(strtolower($_GET['search']));
    
                        if ($searchQuery != ""){
                            $allUsers = $conn->query("SELECT user_table.*, account_table.type FROM user_table JOIN account_table ON user_table.email = account_table.email WHERE user_table.email != '$loggedAccountEmail' AND user_table.email != '$alumnusToViewEmail' AND account_table.type != 'Admin' AND 
                                (LOWER(user_table.email) LIKE '%$searchQuery%' OR 
                                LOWER(user_table.first_name) LIKE '%$searchQuery%' OR 
                                LOWER(user_table.last_name) LIKE '%$searchQuery%' OR 
                                LOWER(user_table.dob) LIKE '%$searchQuery%' OR 
                                LOWER(user_table.gender) LIKE '%$searchQuery%' OR 
                                LOWER(user_table.contact_number) LIKE '%$searchQuery%' OR 
                                LOWER(user_table.hometown) LIKE '%$searchQuery%' OR 
                                LOWER(user_table.current_location) LIKE '%$searchQuery%' OR 
                                LOWER(user_table.job_position) LIKE '%$searchQuery%' OR 
                                LOWER(user_table.qualification) LIKE '%$searchQuery%' OR 
                                LOWER(user_table.year) LIKE '%$searchQuery%' OR 
                                LOWER(user_table.university) LIKE '%$searchQuery%' OR 
                                LOWER(user_table.company) LIKE '%$searchQuery%' OR 
                                LOWER(user_table.resume) LIKE '%$searchQuery%')
                            ");
                        } else {
                            $allUsers = $conn->query("SELECT user_table.*, account_table.type FROM user_table JOIN account_table ON user_table.email = account_table.email WHERE user_table.email != '$loggedAccountEmail' AND user_table.email != '$alumnusToViewEmail' AND account_table.type != 'Admin'");
                        }
                    } else {
                        $allUsers = $conn->query("SELECT user_table.*, account_table.type FROM user_table JOIN account_table ON user_table.email = account_table.email WHERE user_table.email != '$loggedAccountEmail' AND user_table.email != '$alumnusToViewEmail' AND account_table.type != 'Admin'");
                    }
    
                    // Display each retrieved alumnus in its card
                    if ($allUsers && $allUsers->num_rows > 0) {
                        while ($user = $allUsers->fetch_assoc()) {
                            // Name and hometown
                            $listDetails = "<h5 class='card-title'>".$user['first_name']." ".$user['last_name']."</h5>
                                <p class='card-text'>".$user['hometown']."</p>";
    
                            // Default gender profile photo
                            if ($user['gender'] == "Male") {
                                $listProfilePicture = "uploads/profile_pics/default-male-user-profile-icon.jpg";
                            } elseif ($user['gender'] == "Female") {
                                $listProfilePicture = "uploads/profile_pics/default-female-user-profile-icon.jpg";
                            }
    
                            // Use custom uploaded photo if available
                            if (!empty($user['profile_image']) && file_exists("uploads/profile_pics/".$user['profile_image'])) {
                                $listProfilePicture = "uploads/profile_pics/".$user['profile_image'];
                            }
    
                            // Create the card
                            echo '
                                <div class="col-auto mb-3 slide-left">
                                    <form action="profile_detail.php" method="GET">
                                        <input type="hidden" name="email" value="'.$user['email'].'">
                                        <div class="card">
                                            <div class="row align-items-center ps-2 py-2">
                                                <div class="col-auto">
                                                    <img src="'.$listProfilePicture.'" class="profilePictureThumbnail" alt="profile_picture">
                                                </div>
                                                <div class="col">
                                                    <div class="card-body px-2">
                                                        '.$listDetails.'
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="submit" class="stretched-link btn-hidden"></button>
                                        </div>
                                    </form>
                                </div>
                            ';
                        }
                    } elseif($allUsers && $allUsers->num_rows == 0 && $searchQuery != "") {
                        echo '
                            <div class="text-center slide-left">
                                <div class="row align-items-center ps-2 py-2">
                                    <div class="col-12"><h5 class="fw-bold text-secondary">No results for: '.htmlspecialchars($_GET['search']).'</h5></div>
                                </div>
                            </div>
                        ';
                    } else {
                        echo '
                            <div class="text-center slide-left">
                                <div class="row align-items-center ps-2 py-2">
                                    <div class="col-12"><h5 class="fw-bold text-secondary">No other registered alumni friends</h5></div>
                                </div>
                            </div>
                        ';
                    }
                ?>
            </div>
        </div>
    <?php else: ?>
        <h4 class="container fade-in">User not found.</h4>
    <?php endif; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add animation to cards on hover
        document.querySelectorAll('.card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-5px)';
                card.style.boxShadow = '0 10px 20px rgba(0,0,0,0.1)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = '';
                card.style.boxShadow = '0 2px 2px rgba(0,0,0,.08), 0 0 6px rgba(0,0,0,.05)';
            });
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>