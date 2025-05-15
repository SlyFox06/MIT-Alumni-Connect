<?php
// This must be the VERY FIRST LINE in the file
session_start();

// Include admin check file
require 'logged_admin.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | MIT Alumni Portal</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: #002c59;
            --secondary-color: #f8f9fa;
            --accent-color: #0d6efd;
            --card-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            --card-hover-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            --transition-speed: 0.3s;
        }
        
        body.admin-bg {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .nav-admin-link {
            color: rgba(255, 255, 255, 0.85);
            border-radius: 4px;
            transition: all var(--transition-speed) ease;
        }
        
        .nav-admin-link:hover, .nav-main-admin-active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .nav-main-admin-active {
            font-weight: 500;
        }
        
        .card {
            width: 100%;
            max-width: 23rem;
            border: none;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            transition: all var(--transition-speed) ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .card-img-top {
            height: 180px;
            object-fit: cover;
        }
        
        .card-btn {
            border-top-left-radius: 0;
            border-top-right-radius: 0;
            background-color: var(--primary-color);
            border: none;
        }
        
        .row-custom {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 25px;
        }
        
        .recent-photo-card {
            max-width: 100%;
            width: 100%;
        }
        
        .recent-photo {
            max-height: 250px;
            object-fit: contain;
        }
        
        .logout-btn {
            background-color: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        @media (max-width: 768px) {
            .nav-admin-link {
                padding: 0.5rem 1rem !important;
            }
            
            .navbar-nav {
                margin-bottom: 1rem;
            }
            
            .logout-container {
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
            }
        }
    </style>
</head>
<body class="admin-bg">
    <!-- Top nav bar -->
    <nav class="navbar sticky-top navbar-expand-lg mb-4" style="background-color: var(--primary-color);">
        <div class="container">
            <a class="navbar-brand mx-0 mb-0 h1 text-light fw-bold" href="main_menu_admin.php">
                <i class="bi bi-building-gear me-2"></i>MIT Alumni Portal
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link nav-main-admin-active px-3 px-lg-4" href="main_menu_admin.php">
                            <i class="bi bi-house-door-fill me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-4" href="manage_accounts.php">
                            <i class="bi bi-people me-1 position-relative"></i>
                            Accounts
                            <?php if (isset($pendingCount) && $pendingCount > 0) { ?> 
                            <span class="badge bg-danger small-badge"><?php echo $pendingCount; ?></span>
                            <?php } ?>
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-4" href="manage_events.php">
                            <i class="bi bi-calendar-event me-1"></i> Events
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-4" href="manage_advertisements.php">
                            <i class="bi bi-megaphone me-1"></i> Ads
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-4" href="manage_gallery.php">
                            <i class="bi bi-images me-1"></i> Gallery
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-4" href="manage_success_stories.php">
                            <i class="bi bi-trophy me-1"></i> Stories
                        </a>
                    </li>
                </ul>
                
                <div class="logout-container d-flex">
                    <form action="logout.php" method="post">
                        <button type="submit" class="btn logout-btn rounded-pill px-3">
                            <i class="bi bi-box-arrow-right me-1"></i> Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        <h2 class="mb-4 text-center" style="color: var(--primary-color);">Admin Dashboard</h2>
        <p class="text-center text-muted mb-4">Manage all aspects of the alumni portal</p>
        
        
        <div class="row row-custom">
            
            <!-- Manage Events/News -->
            <div class="col-auto">
                <div class="card text-center">
                    <img src="images/manage_event.PNG" class="card-img-top" alt="Events">
                    <div class="card-body">
                        <h5 class="card-title">Events/News</h5>
                        <p class="card-text">Post and manage events/news to publish updates</p>
                    </div>
                    <div class="d-grid gap-2"> 
                        <a class="btn card-btn btn-primary py-2" href="manage_events.php">
                            <i class="bi bi-calendar2-plus me-2"></i>Manage Events
                        </a> 
                    </div>
                </div>
            </div>

             <!-- Manage Recent  Events/News -->
             <div class="col-auto">
                <div class="card text-center">
                    <img src="images/day_of_service.PNG" class="card-img-top" alt="Events">
                    <div class="card-body">
                        <h5 class="card-title">Recent Image</h5>
                        <p class="card-text">Post and manage recent events image</p>
                    </div>
                    <div class="d-grid gap-2"> 
                        <a class="btn card-btn btn-primary py-2" href="admin_gallery.php">
                            <i class="bi bi-calendar2-plus me-2"></i>ADD Photos
                        </a> 
                    </div>
                </div>
            </div>

            <!-- Manage User Accounts -->
            <div class="col-auto">
                <div class="card text-center">
                    <img src="
                    
                    
                    
                    
                    
                    
                    
                    
                    images\photo-gallery-icon-16.jpg" class="card-img-top" alt="Accounts">
                    <div class="card-body">
                        <h5 class="card-title">User Accounts</h5>
                        <p class="card-text">Manage user accounts and security</p>
                    </div>
                    <div class="d-grid gap-2"> 
                        <a class="btn card-btn btn-primary py-2" href="manage_accounts.php">
                            <i class="bi bi-person-gear me-2"></i>Manage Accounts
                        </a> 
                    </div>
                </div>
            </div>

            <!-- Manage Advertisements -->
            <div class="col-auto">
                <div class="card text-center">
                    <img src="images/day_of_service.PNG" class="card-img-top" alt="Advertisements">
                    <div class="card-body">
                        <h5 class="card-title">Advertisements</h5>
                        <p class="card-text">Manage job listings and professional events</p>
                    </div>
                    <div class="d-grid gap-2"> 
                        <a class="btn card-btn btn-primary py-2" href="manage_advertisements.php">
                            <i class="bi bi-badge-ad me-2"></i>Manage Ads
                        </a> 
                    </div>
                </div>
            </div>
            
            <!-- Manage Photo Gallery -->
            <div class="col-auto">
                <div class="card text-center">
                    <img src="images\photo-gallery-icon-16.jpg" class="card-img-top" alt="Gallery">
                    <div class="card-body">
                        <h5 class="card-title">Photo Gallery</h5>
                        <p class="card-text">Manage photos showcasing events</p>
                    </div>
                    <div class="d-grid gap-2"> 
                        <a class="btn card-btn btn-primary py-2" href="manage_gallery.php">
                            <i class="bi bi-camera me-2"></i>Manage Gallery
                        </a> 
                    </div>
                </div>
            </div>
            
            <!-- Manage Success Stories -->
            <div class="col-auto">
                <div class="card text-center">
                    <img src="images/success-stories-1.png" class="card-img-top" alt="Success Stories">
                    <div class="card-body">
                        <h5 class="card-title">Success Stories</h5>
                        <p class="card-text">Manage alumni success stories</p>
                    </div>
                    <div class="d-grid gap-2"> 
                        <a class="btn card-btn btn-primary py-2" href="manage_success_stories.php">
                            <i class="bi bi-award me-2"></i>Manage Stories
                        </a> 
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>