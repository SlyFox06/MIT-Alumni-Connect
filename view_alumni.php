<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment 2 | View atharv</title>

    <link rel="stylesheet" href="css/styles.css">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
       /* Navbar Styles */
       .navbar {
            background: white;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            padding: 0.8rem 1rem;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.2rem;
        }

        .navbar-brand:hover {
            color: #3D1165;
            transition: 0.5s ease;
        }

        /* .navbar-brand span {
            color: var(--secondary);
        } */

        .nav-link {
            font-weight: 500;
            color: var(--dark);
            padding: 0.5rem 1rem;
            margin: 0 0.2rem;
            border-radius: 5px;
            transition: all 0.3s ease-in-out;
        }

        .nav-link:hover {
            color: var(--secondary);
            /* slight color shift */
            background: rgba(67, 97, 238, 0.15);
            transform: translateY(-2px);
        }
        

        /* .nav-link:hover,
        .nav-link.active {
            color: var(--primary);
            background: rgba(67, 97, 238, 0.1);
        } */


        body {
            padding-top: 70px;
        }
                
        .card {
            width: 25rem;
            border: none;
            box-shadow: 0 2px 2px rgba(0,0,0,.08), 0 0 6px rgba(0,0,0,.05);
            transition: 0.2s ease;
        }

        .card:hover{
            background-color: #E4E6E9;
            transition: 0.075s ease;
        }

        .profilePictureThumbnail{
            width: 100px;
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <?php
        include 'db_controller.php';
        $conn->select_db("atharv");
        
        session_start();

        include 'logged_user.php';
    ?>

    <!-- Updated Top nav bar -->
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
                        <a class="nav-link" href="view_alumni.php"><i class="bi bi-people"></i></i> Alumni</a>
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
                <li class="breadcrumb-item breadcrumb-active" aria-current="page">Alumni Friends</li>
            </ol>
        </nav>
    </div>

    <div class="container mb-5">
        <div class="row">
            <h1 class="<?php echo (!isset($_GET['search'])) ? "slide-left" : "" ?>">Alumni Friends</h1>
            
            <!-- Search box -->
            <form action = "<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method = "GET">
                <div class="input-group mb-3 <?php echo (!isset($_GET['search'])) ? "slide-left" : "" ?>">
                    <input type="text" class="form-control py-3 my-3" placeholder="Search" name="search" aria-label="Search" aria-describedby="button-addon2" value="<?php echo (isset($_GET['search'])) ? trim($_GET['search']) : ""; ?>">
                    <button class="btn btn-primary px-4 py-3 my-3" type="submit" id="button-addon2"><i class="bi bi-search"></i></button>
                </div>
            </form>

            <?php
                $loggedAccountEmail = $_SESSION['logged_account']['email'];

                // Perform search on query, otherwise simply fetch all
                $searchQuery = "";
                if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search'])) {
                    $searchQuery = trim(strtolower($_GET['search']));

                    if ($searchQuery != ""){
                        $allUsers = $conn->query("SELECT user_table.*, account_table.type FROM user_table JOIN account_table ON user_table.email = account_table.email WHERE user_table.email != '$loggedAccountEmail' AND account_table.type != 'Admin' AND 
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
                    } else 
                        $allUsers = $conn->query("SELECT user_table.*, account_table.type FROM user_table JOIN account_table ON user_table.email = account_table.email WHERE user_table.email != '$loggedAccountEmail' AND account_table.type != 'Admin'");
                } else
                    $allUsers = $conn->query("SELECT user_table.*, account_table.type FROM user_table JOIN account_table ON user_table.email = account_table.email WHERE user_table.email != '$loggedAccountEmail' AND account_table.type != 'Admin'");

                // Display each retrieved alumnus in its card
                if ($allUsers && $allUsers->num_rows > 0) {
                    while ($user = $allUsers->fetch_assoc()) {
                        $listDetails = "<h5 class='card-title'>".$user['first_name']." ".$user['last_name']."</h5>
                            <p class='card-text'>".$user['hometown']."</p>";

                        // Name and hometown
                        if ($user['gender'] == "Male")
                            $listProfilePicture = "profile_images/default-male-user-profile-icon.jpg";
                        elseif ($user['gender'] == "Female")
                            $listProfilePicture = "profile_images/default-female-user-profile-icon.jpg";
                        
                        // Profile photo
                        if ($user['profile_image'] != null)
                            $listProfilePicture = "profile_images/".$user['profile_image'];

                        // Create the card
                        echo '
                            <div class="col-auto mb-3 slide-left">
                                <form action="'.htmlspecialchars('profile_detail.php').'" method="GET">
                                    <input type="hidden" name="email" value="'.$user['email'].'">
                                    <div class="card">
                                        <div class="row align-items-center ps-2 py-2">
                                            <div class="col-auto">
                                                <div class="image-container-atharv"><img src="'.$listProfilePicture.'" class="img-fluid profilePictureThumbnail" alt="profile_picture"></div>
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
                // If not results found with filter
                } elseif($allUsers && $allUsers->num_rows == 0 && $searchQuery != "") {
                    echo '
                        <div class="text-center slide-left">
                            <div class="row align-items-center ps-2 py-2">
                                <div class="col-12"><h5 class="fw-bold text-secondary">No results for: '.$_GET['search'].'</h5></div>
                            </div>
                        </div>
                    ';
                // If no alumnus to retrieve
                } else {
                    echo '
                        <div class="text-center slide-left">
                            <div class="row align-items-center ps-2 py-2">
                                <div class="col-12"><h5 class="fw-bold text-secondary">No other registered atharv friends</h5></div>
                            </div>
                        </div>
                    ';
                }
            ?>
        </div>
    </div>
    
    <!-- Boostrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</body>
</html>