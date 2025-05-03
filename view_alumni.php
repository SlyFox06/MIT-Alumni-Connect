<?php
// Absolute first thing in the file - no whitespace before this!
session_start();

include 'db_controller.php';
$conn->select_db("atharv");

include 'logged_user.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Portal | View Alumni</title>

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
            background-color: #f5f7ff;
            color: var(--dark);
            line-height: 1.6;
            padding-top: 70px;
        }

        /* Navbar Styles */
        .navbar {
            background: white;
            padding: 0.8rem 1rem !important;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            min-height: 60px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 600;
            color: #4361ee;
            font-size: 1.3rem;
        }

        .nav-link {
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: var(--secondary);
            background: rgba(67, 97, 238, 0.1);
        }

        /* Alumni Grid Layout */
        .alumni-grid {
            display: flex;
            flex-wrap: wrap;
            margin: -0.5rem;
        }

        .alumni-card-container {
            padding: 0.5rem;
            flex: 0 0 33.333%;
            max-width: 33.333%;
        }

        @media (max-width: 992px) {
            .alumni-card-container {
                flex: 0 0 50%;
                max-width: 50%;
            }
        }

        @media (max-width: 768px) {
            .alumni-card-container {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        /* Card Styles - Removed border */
        .card {
            width: 100%;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-radius: 10px;
            height: 100%;
            
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* Profile Picture Styles */
        .profile-picture-container {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f2f5;
            overflow: hidden;
            border: 3px solid white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .profile-picture {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Animation Styles */
        .slide-left {
            animation: slideInLeft 0.5s ease-out;
        }

        @keyframes slideInLeft {
            from {
                transform: translateX(-20px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Search Box Styles */
        .search-box {
            max-width: 600px;
            margin: 2rem auto;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="main_menu.php">
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
                        <a class="nav-link" href="view_advertisements.php"><i class="bi bi-briefcase me-1"></i> Opportunities</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_gallery.php"><i class="bi bi-images me-1"></i> Gallery</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Alumni-Portal-main/view_profile.php?email=<?php echo htmlspecialchars($_SESSION['logged_account']['email']); ?>"><i class="bi bi-person me-1"></i> Profile</a>
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
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="main_menu.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Alumni Friends</li>
            </ol>
        </nav>
    </div>

    <div class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-12">
                <h1 class="mb-4 <?php echo (!isset($_GET['search'])) ? "slide-left" : "" ?>">Alumni Friends</h1>
                
                <!-- Search box -->
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET">
                    <div class="search-box input-group mb-4 <?php echo (!isset($_GET['search'])) ? "slide-left" : "" ?>">
                        <input type="text" class="form-control py-3" placeholder="Search by name, location, company, etc." name="search" value="<?php echo (isset($_GET['search'])) ? trim($_GET['search']) : ""; ?>">
                        <button class="btn btn-primary px-4" type="submit"><i class="bi bi-search"></i> Search</button>
                    </div>
                </form>

                <div class="alumni-grid">
                    <?php
                        $loggedAccountEmail = $_SESSION['logged_account']['email'];
                        $searchQuery = "";

                        if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search'])) {
                            $searchQuery = trim(strtolower($_GET['search']));

                            if ($searchQuery != ""){
                                $allUsers = $conn->query("SELECT user_table.*, account_table.type FROM user_table JOIN account_table ON user_table.email = account_table.email WHERE user_table.email != '$loggedAccountEmail' AND account_table.type != 'Admin' AND 
                                    (LOWER(user_table.email) LIKE '%$searchQuery%' OR 
                                    LOWER(user_table.first_name) LIKE '%$searchQuery%' OR 
                                    LOWER(user_table.last_name) LIKE '%$searchQuery%' OR 
                                    LOWER(user_table.hometown) LIKE '%$searchQuery%' OR 
                                    LOWER(user_table.current_location) LIKE '%$searchQuery%' OR 
                                    LOWER(user_table.job_position) LIKE '%$searchQuery%' OR 
                                    LOWER(user_table.company) LIKE '%$searchQuery%')
                                ");
                            } else {
                                $allUsers = $conn->query("SELECT user_table.*, account_table.type FROM user_table JOIN account_table ON user_table.email = account_table.email WHERE user_table.email != '$loggedAccountEmail' AND account_table.type != 'Admin'");
                            }
                        } else {
                            $allUsers = $conn->query("SELECT user_table.*, account_table.type FROM user_table JOIN account_table ON user_table.email = account_table.email WHERE user_table.email != '$loggedAccountEmail' AND account_table.type != 'Admin'");
                        }

                        if ($allUsers && $allUsers->num_rows > 0) {
                            while ($user = $allUsers->fetch_assoc()) {
                                $listDetails = "<h5 class='card-title mb-1'>{$user['first_name']} {$user['last_name']}</h5>
                                    <p class='card-text text-muted mb-1'><i class='bi bi-briefcase me-2'></i>{$user['job_position']}</p>
                                    <p class='card-text text-muted'><i class='bi bi-geo-alt me-2'></i>{$user['current_location']}</p>";

                                $listProfilePicture = ($user['gender'] == "Male") ? 
                                    "profile_images/default-male-user-profile-icon.jpg" : 
                                    "profile_images/default-female-user-profile-icon.jpg";
                                
                                if ($user['profile_image'] != null) {
                                    $listProfilePicture = "uploads/profile_pics/".$user['profile_image'];
                                }

                                echo '
                                <div class="alumni-card-container slide-left">
                                    <form action="profile_detail.php" method="GET">
                                        <input type="hidden" name="email" value="'.$user['email'].'">
                                        <div class="card">
                                            <div class="card-body d-flex align-items-center p-4">
                                                <div class="profile-picture-container me-4">
                                                    <img src="'.$listProfilePicture.'" class="profile-picture" alt="profile picture">
                                                </div>
                                                <div class="flex-grow-1">
                                                    '.$listDetails.'
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
                            <div class="col-12 text-center slide-left py-5">
                                <div class="alert alert-info">
                                    <h5 class="fw-bold">No results found for: "'.htmlspecialchars($_GET['search']).'"</h5>
                                    <p class="mb-0">Try different search terms</p>
                                </div>
                            </div>
                            ';
                        } else {
                            echo '
                            <div class="col-12 text-center slide-left py-5">
                                <div class="alert alert-info">
                                    <h5 class="fw-bold">No other registered alumni friends</h5>
                                    <p class="mb-0">Check back later or invite your friends to join</p>
                                </div>
                            </div>
                            ';
                        }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>