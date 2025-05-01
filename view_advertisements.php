<?php
// Start session at the very beginning
session_start();

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_controller.php';
$conn->select_db("atharv");

// Get user profile picture
$profilePic = "default_profile.jpg"; // default image
if (isset($_SESSION['logged_account']['photo']) && !empty($_SESSION['logged_account']['photo'])) {
    $profilePic = $_SESSION['logged_account']['photo'];
}

// Check if user is logged in
if (!isset($_SESSION['logged_account'])) {
    header("Location: login.php");
    exit();
}

// Handle flash messages
$tempFlash = null;
if (isset($_SESSION['flash_mode'])) {
    $tempFlash = $_SESSION['flash_mode'];
    unset($_SESSION['flash_mode']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment 2 | Advertisements</title>



    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <!-- AOS (Animate On Scroll) -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
         :root {
            --primary: #4361ee;
            --primary-light: #4f6cff;
            --secondary: #3f37c9;
            --accent: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
            --space-unit: 1.5rem;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #F0F2F5;
            color: var(--dark);
            line-height: 1.6;
            overflow-x: hidden;
            padding-top: 80px;
        }

        /* Navbar Styles */
        .navbar {
        background: white;
        box-shadow:0 0 5px rgba(0,0,0,.3);
        padding: 0.8rem 1rem !important;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1030;
        min-height: 60px;
        display: flex;
        align-items: center;
    }

    .navbar-nav {
    margin-left: auto; /* Pushes items to the right */
    display: flex;
    align-items: center; /* Vertical centering for nav items */
}
        
.navbar-brand {
        font-weight: 600;
        color: #4361ee;
        font-size: 1.3rem;
        letter-spacing: 0.5px;
        margin-right: 3rem; /* Add more space after the brand */
    }

    .navbar-brand:hover {
            color: #3D1165;
            transition: 0.5s ease;
        }

        /* .navbar-brand span {
            color: var(--secondary);
        } */

        .nav-item {
    margin-left: -3px;
    margin-right: -3px;
}

.navbar .container {
    display: flex;
    align-items: center;
}

        .nav-link {
            font-weight: 500;
            color: var(--dark);
            padding: 0.5rem 1rem;
            margin: 0px 10px;
            border-radius: 1px;
            transition: all 0.3s ease-in-out;
            border-radius: 5px 5px 5px 5px !important;
        }

        .nav-link:hover {
                color: var(--secondary);
                /* slight color shift */
                background: rgba(67, 97, 238, 0.15);
                transform: translateY(-2px);
            }
        

        .nav-button:hover {
            transform: translateY(-2px);
            background-color:rgba(67, 97, 238, 0.15);
            color:rgba(67, 97, 238, 0.15);
        }

        .nav-icon {
            font-size: 1.2rem;
        }
        
        
        /* Dropdown menu */
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform-origin: top right;
            animation: dropdownFadeIn 0.3s ease forwards;
        }
        
        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-10px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .dropdown-item {
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            transform: translateX(5px);
        }
        
        /* Card styles */
        .card {
            width: 100%;
            border: none;
            box-shadow: 0 2px 2px rgba(0,0,0,.08), 0 0 6px rgba(0,0,0,.05);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform: perspective(1000px) rotateX(0deg) rotateY(0deg);
            position: relative;
            overflow: hidden;
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.1) 0%, rgba(76, 201, 240, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }
        
        .card:hover {
            transform: translateY(-8px) perspective(1000px) rotateX(2deg) rotateY(2deg);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .card:hover::before {
            opacity: 1;
        }
        
        .card:hover .card-title {
            color: var(--primary);
        }
        
        /* Image hover effect */
        .profilePictureThumbnail {
            transition: all 0.5s ease;
        }
        
        .card:hover .profilePictureThumbnail {
            transform: scale(1.05);
        }
        
        /* Button styles */
        .btn-primary {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.4);
        }
        
        .btn-primary::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%, -50%);
            transform-origin: 50% 50%;
        }
        
        .btn-primary:focus:not(:active)::after {
            animation: ripple 0.6s ease-out;
        }
        
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(20, 20);
                opacity: 0;
            }
        }
        
        .btn-outline-primary {
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.2);
        }
        
        /* Badge animation */
        .badge {
            transition: all 0.3s ease;
        }
        
        .badge:hover {
            transform: scale(1.05);
        }
        
        /* Breadcrumb animation */
        .breadcrumb-item {
            transition: all 0.3s ease;
        }
        
        .breadcrumb-item:hover {
            transform: translateX(3px);
        }
        
        /* Other existing styles */
        .image-container-events {
            width: 250px;
            height: 200px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px 0 0 8px;
        }
        
        .profilePictureThumbnail {
            height: 100%;
            width: auto;
            object-fit: cover;
        }
        
    
        
        /* Floating animation */
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
            100% { transform: translateY(0px); }
        }
        
        .floating-animation {
            animation: floating 3s ease-in-out infinite;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .image-container-events {
                width: 100%;
                height: 200px;
                border-radius: 8px 8px 0 0;
            }
            
            .profilePictureThumbnail {
                width: 100%;
                height: auto;
            }
            
            .nav-link::after {
                display: none;
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary);
        }
    </style>
</head>
<body>
    <!-- Updated Top nav bar with profile dropdown -->
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="#">
                <span>MIT</span> ALUMNI PORTAL
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="main_menu.php"><i class="bi bi-people"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_events.php"><i class="bi bi-calendar-event me-1"></i> Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_gallery.php"><i class="bi bi-images me-1"></i> Gallery</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_profile.php?email=<?php echo htmlspecialchars($email); ?>"><i class="bi bi-person me-1"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Alumni-Portal-main/logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- Breadcrumb -->
    <div class="container my-3">
        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a class="breadcrumb-link text-secondary link-underline link-underline-opacity-0" href="main_menu.php">Home</a></li>
                <li class="breadcrumb-item breadcrumb-active" aria-current="page">Opportunities</li>
            </ol>
        </nav>
    </div>

    <?php
        // GET the POST (which was retrieved from GET previously)
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_POST['filterStatus'])) {
                $_GET['filterStatus'] = $_POST['filterStatus'];
            }
            if (isset($_POST['filterCategory'])) {
                $_GET['filterCategory'] = $_POST['filterCategory'];
            }
            if (isset($_POST['search'])) {
                $_GET['search'] = $_POST['search'];
            }
        }

        if (isset($_SESSION['ad_apply_error']) && $_SESSION['ad_apply_error'] == true) {
            echo "<script>
                window.onload = function() {
                    var errorModalCenter = new bootstrap.Modal(document.getElementById('errorModalCenter'));
                    errorModalCenter.show();
                }
            </script>";
            unset($_SESSION['ad_apply_error']);
        }

        if (isset($_SESSION['ad_apply_success']) && $_SESSION['ad_apply_success'] == true) {
            echo "<script>
                window.onload = function() {
                    var successModalCenter = new bootstrap.Modal(document.getElementById('successModalCenter'));
                    successModalCenter.show();
                }
            </script>";
            unset($_SESSION['ad_apply_success']);
        }
    ?>

    <div class="container-fluid">
        <!-- Flash message -->
        <?php if (isset($tempFlash)){ ?>
            <div class="row justify-content-center position-absolute top-1 start-50 translate-middle">
                <div class="col-auto">
                    <div class="alert <?php echo $tempFlash; ?> mt-4 py-2 fade-in fade-out-alert row align-items-center" role="alert">
                        <i class="bi <?php echo ($tempFlash == "alert-success" ? "bi-check-circle" : ($tempFlash == "alert-warning" ? "bi-exclamation-triangle" : "bi-info-circle")); ?> login-bi col-auto px-0"></i>
                        <div class="col ms-1"><?php echo $_SESSION['flash'] ?? ''; ?></div>
                    </div>
                </div>
            </div>
        <?php } ?>

        <div class="container mb-5">
            <h1 class="<?php echo (isset($_POST['eventID']) || isset($_GET['filterStatus']) || isset($_GET['filterCategory']) || isset($_GET['search'])) ? NULL : 'slide-left' ?>">Opportunities</h1>
            
            <!-- Filter -->
            <div class="container mt-3 py-3 px-4 card bg-white fw-medium <?php echo (isset($_POST['eventID']) || isset($_GET['filterStatus']) || isset($_GET['filterCategory']) || isset($_GET['search'])) ? NULL : 'slide-left' ?>" data-aos="fade-up" data-aos-duration="500">
                <form id="eventsFilterForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="GET">
                    <!-- Status (All, Active, Inactive) -->
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="filterStatus" id="filterStatus1" value="All" <?php echo (isset($_GET['filterStatus']) && $_GET['filterStatus'] == 'All') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="filterStatus1">All</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="filterStatus" id="filterStatus2" value="Active" <?php echo (isset($_GET['filterStatus']) && $_GET['filterStatus'] == 'Active') ? 'checked' : ((!isset($_GET['filterCategory'])) ? 'checked' : '' ) ?>>
                        <label class="form-check-label badge text-bg-success" for="filterStatus2">Active</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="filterStatus" id="filterStatus3" value="Inactive" <?php echo (isset($_GET['filterStatus']) && $_GET['filterStatus'] == 'Inactive') ? 'checked' : '' ?>>
                        <label class="form-check-label badge text-bg-secondary" for="filterStatus3">Inactive</label>
                    </div>

                    <!-- Departments (All, Engineering, IT, Business, Design) -->
                    <div class="form-check-inline ms-4">
                        <div class="input-group">
                            <label class="input-group-text" for="filterCategory"><i class="bi bi-buildings"></i></label>
                            <select class="form-select fw-medium" id="filterCategory" name="filterCategory" aria-label="Time filter">
                                <option value="All" class="fw-medium" <?php echo (isset($_GET['filterCategory']) && $_GET['filterCategory'] == 'All') ? 'selected' : '' ?>>All</option>
                                <option value="Engineering" class="fw-medium" <?php echo (isset($_GET['filterCategory']) && $_GET['filterCategory'] == 'Engineering') ? 'selected' : '' ?>>Engineering</option>
                                <option value="IT" class="fw-medium" <?php echo (isset($_GET['filterCategory']) && $_GET['filterCategory'] == 'IT') ? 'selected' : '' ?>>IT</option>
                                <option value="Business" class="fw-medium" <?php echo (isset($_GET['filterCategory']) && $_GET['filterCategory'] == 'Business') ? 'selected' : '' ?>>Business</option>
                                <option value="Design" class="fw-medium" <?php echo (isset($_GET['filterCategory']) && $_GET['filterCategory'] == 'Design') ? 'selected' : '' ?>>Design</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary fw-medium mb-1 me-2 hover-effect">Display List</button>

                    <a href="add_advertisement_user.php" class="btn btn-success fw-medium mb-1 hover-effect"><i class="bi bi-plus-circle me-1"></i>Add Opportunity</a>

                    <!-- Search box -->
                    <div class="form-check-inline me-0 float-end">
                        <div class="input-group">
                            <input type="text" class="form-control py-2" placeholder="Search opportunities" name="search" aria-label="Search" aria-describedby="button-addon2" value="<?php echo isset($_GET['search']) ? htmlspecialchars(trim($_GET['search'])) : ''; ?>">
                            <button class="btn btn-primary px-3 py-2" type="submit" id="button-addon2"><i class="bi bi-search"></i></button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Advertisements -->
            <div class="row row-cols-1 mt-4 px-0 mx-0 <?php echo ($_SERVER["REQUEST_METHOD"] == "POST") ? NULL : 'slide-left' ?>">
                <?php 
                    $filterStatus = "";
                    $filterCategory = "";
                    $filterSearch = "";

                    // Type filter for Advertisements
                    if (isset($_GET['filterStatus']) && $_GET['filterStatus'] != 'All') {
                        $filterStatus = "status = '" . $conn->real_escape_string($_GET['filterStatus']) . "'";
                    } elseif (!isset($_GET['filterStatus'])) {
                        $filterStatus = "status = 'Active'";
                    }

                    // Category filter for Advertisements
                    if (isset($_GET['filterCategory']) && $_GET['filterCategory'] != 'All') {
                        $filterCategory = "category = '" . $conn->real_escape_string($_GET['filterCategory']) . "'";
                    }

                    // Search filter for Advertisements
                    if (isset($_GET['search']) && $_GET['search'] != "") {
                        $trimSearch = strtolower(trim($conn->real_escape_string($_GET['search'])));
                        $filterSearch = "(
                            LOWER(title) LIKE '%$trimSearch%' OR 
                            LOWER(description) LIKE '%$trimSearch%' OR 
                            LOWER(date_added) LIKE '%$trimSearch%' OR 
                            DATE_FORMAT(date_added, '%a, %e %b %Y') LIKE '%$trimSearch%' OR 
                            LOWER(button_message) LIKE '%$trimSearch%' OR 
                            LOWER(button_link) LIKE '%$trimSearch%' OR 
                            LOWER(category) LIKE '%$trimSearch%' OR 
                            LOWER(status) LIKE '%$trimSearch%'
                        )";
                    }

                    // Puts WHERE and AND to the query appropriately
                    $conditions = array_filter([$filterStatus, $filterCategory, $filterSearch]);
                    if (!empty($conditions)) {
                        $whereClause = "WHERE " . implode(" AND ", $conditions);
                    } else {
                        $whereClause = "";
                    }

                    date_default_timezone_set('Asia/Kuching');
                    $today = date('Y-m-d H:i:s');
                    if (!empty($whereClause)) {
                        $nonHiddenAds = "AND (date_to_hide IS NULL OR date_to_hide >= '".$today."')";
                    } else {
                        $nonHiddenAds = "WHERE (date_to_hide IS NULL OR date_to_hide >= '".$today."')";
                    }

                    // Retrieve from db
                    $query = "SELECT * FROM advertisement_table $whereClause $nonHiddenAds ORDER BY id DESC";
                    $allAdvertisements = $conn->query($query);

                    // Load the advertisements if there's at least 1
                    if ($allAdvertisements && $allAdvertisements->num_rows > 0) {
                        $count = 0;
                        while ($advertisement = $allAdvertisements->fetch_assoc()) {
                            $count++;
                            // Format date
                            $date = new DateTime($advertisement['date_added']);
                            $formattedDate = strtoupper($date->format('D, j M Y'));
                            
                            // Determine animation delay based on position
                            $animationDelay = $count * 100;
                ?>
                    <!-- Each card made for each advertisement -->
                    <div class="col mb-4 px-0 mx-0" data-aos="fade-up" data-aos-delay="<?php echo $animationDelay; ?>">
                        <div class="card h-100 <?php echo ($count <= 3) ? 'pulse-animation' : ''; ?>">
                            <div class="row g-0 h-100">
                                <!-- Image -->
                                <div class="col-md-4">
                                    <div class="image-container-events h-100">
                                        <img src="images/<?php echo htmlspecialchars($advertisement['photo']); ?>" class="img-fluid profilePictureThumbnail" alt="profile_picture">
                                    </div>
                                </div>

                                <div class="col-md-8 d-flex flex-column">
                                    <!-- Body info -->
                                    <div class="card-body px-4 flex-grow-1">
                                        <?php 
                                            echo "<span class='fw-medium fs-6'>".htmlspecialchars($formattedDate)."</span>"
                                            . (($advertisement['status'] == 'Active') ? "<span class='badge text-bg-success mt-1 float-end'>Active</span>" : "<span class='badge text-bg-secondary mt-1 float-end'>Inactive</span>")."
                                            <span class='float-end'>&nbsp;</span>
                                            <span class='badge text-bg-info mt-1 float-end'>".htmlspecialchars($advertisement['category'])."</span>
                                            <br/>
                                            <span class='card-title h3'>".htmlspecialchars($advertisement['title'])."</span>
                                            <br/>
                                            <span class='card-text'>".htmlspecialchars($advertisement['description'])."</span>";
                                        ?>
                                    </div>

                                    <!-- Custom Button -->
                                    <?php if (!empty($advertisement['button_message']) && !empty($advertisement['button_link'])) { ?>
                                        <div class="<?php echo ($advertisement['appliable'] == 1) ? 'mb-2 ms-4' : 'mb-4 ms-4' ?>">
                                            <a type='button' class='btn btn-outline-primary fw-medium px-4 hover-effect' href='<?php echo htmlspecialchars($advertisement['button_link']); ?>' target='_blank'><?php echo htmlspecialchars($advertisement['button_message']); ?><i class="bi bi-box-arrow-up-right ms-2" style="-webkit-text-stroke: 0.25px;"></i></a>
                                        </div>
                                    <?php } ?>

                                    <!-- Apply Button -->
                                    <?php if ($advertisement['appliable'] == 1) { ?>
                                        <div class="mb-4 ms-4">
                                            <form action="<?php echo htmlspecialchars('advertisement_apply.php');?>" method="GET">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($advertisement['id']); ?>">
                                                <button type='submit' class='btn btn-primary fw-medium py-2 px-5 hover-effect'>Apply Now</button>
                                            </form>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php 
                        }
                    // No result from filter
                    } elseif (isset($_GET['filterStatus']) || isset($_GET['filterCategory']) || (isset($_GET['search']) && $_GET['search'] != "")) { ?>
                        <div class="text-center slide-left" data-aos="fade-up">
                            <div class="row align-items-center ps-2 py-2">
                                <div class="col-12"><h5 class="fw-bold text-secondary">No opportunities available from your filter</h5></div>
                            </div>
                        </div>
                    <!-- Simply no result -->
                    <?php } else { ?>
                        <div class="text-center slide-left" data-aos="fade-up">
                            <div class="row align-items-center ps-2 py-2">
                                <div class="col-12"><h5 class="fw-bold text-secondary">No opportunities available</h5></div>
                            </div>
                        </div>
                <?php } 
                    $conn->close();
                ?>
            </div>
        </div>
    </div>
    
    <!-- Back to Top Button -->
    <button id="backToTop" class="btn btn-primary rounded-circle position-fixed bottom-0 end-0 m-4 p-3 shadow-lg" style="display: none;">
        <i class="bi bi-arrow-up"></i>
    </button>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <!-- AOS (Animate On Scroll) -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            once: true, // whether animation should happen only once - while scrolling down
            duration: 600, // values from 0 to 3000, with step 50ms
            easing: 'ease-out-quad', // default easing for AOS animations
        });
        
        // Ignore confirm form resubmission after POST request to register event
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        // Back to top button
        const backToTopButton = document.getElementById('backToTop');
        
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopButton.style.display = 'block';
                backToTopButton.style.animation = 'fadeIn 0.3s';
            } else {
                backToTopButton.style.display = 'none';
            }
        });
        
        backToTopButton.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Hover effect for buttons
        const hoverEffectButtons = document.querySelectorAll('.hover-effect');
        hoverEffectButtons.forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px)';
                this.style.boxShadow = '0 7px 20px rgba(67, 97, 238, 0.3)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });
        
 
       
    </script>
</body>
</html>