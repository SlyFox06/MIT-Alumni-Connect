<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_account'])) {
    header("Location: login.php");
    exit();
}

include 'db_controller.php';

// Safely get user data with fallbacks
$user = $_SESSION['logged_user'] ?? [];
$first_name = $user['first_name'] ?? 'User';// More generic fallback
$email = $user['email'] ?? '';

// Safely fetch stats for the dashboard
try {
    $atharv_count = $conn->query("SELECT COUNT(*) FROM user_table")->fetch_row()[0];
    $event_count = $conn->query("SELECT COUNT(*) FROM event_table WHERE event_date >= CURDATE()")->fetch_row()[0];

    // Get count of active job advertisements
    $job_count = $conn->query("SELECT COUNT(*) FROM advertisement_table WHERE status = 'Active' AND (date_to_hide IS NULL OR date_to_hide >= CURDATE())")->fetch_row()[0];
    
} catch (mysqli_sql_exception $e) {
    // Handle database errors gracefully
    error_log("Database error: " . $e->getMessage());
    $atharv_count = $event_count = $job_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MIT Alumni Portal | Dashboard</title>

    <!-- CSS -->
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
                overflow-x: hidden;
                padding-top: 50px;
            }

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

        /* Banner Styles */
        .banner {
            position: relative;
            width: 100%;
            height: 80vh;
            overflow: hidden;
        }

        .banner-slide {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
            background-color: var(--primary);
            /* Fallback color */
        }

        .banner-slide.active {
            opacity: 1;
        }

        .slide-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .banner-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: white;
            z-index: 2;
            width: 90%;
            max-width: 1200px;
            padding: 0 20px;
        }

        .banner-content h1 {
            font-size: 4rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.7);
            animation: fadeInDown 1s ease;
        }

        .banner-content p {
            margin: 0 auto 2.5rem;
            font-weight: 300;
            line-height: 1.6;
            font-size: 1.3rem;
            max-width: 700px;
            text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.5);
            animation: fadeIn 1.5s ease;
        }

        /* Button Animation Styles */
        .banner-btn {
            font-size: 1.1rem;
            width: 252px;
            padding: 15px 0;
            text-align: center;
            border-radius: 50px;
            font-weight: 600;
            border: 2px solid white;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
            z-index: 1;
            transform: translateY(0);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            margin: 0 10px;
        }

        .banner-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            background: rgba(247, 247, 247, 0.76);
            background-color: white;
            color: black;
            transition: all 0, 5s ease;
        }

        .banner-btn:active {
            transform: translateY(-2px);
        }



        /* Animations */
        .pulse-animation {
            animation: pulse 2s infinite ease-in-out;
        }

        .float-animation {
            animation: float 3s infinite ease-in-out;
        }

        .glow-animation {
            animation: glow 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: translateY(0) scale(1);
            }

            50% {
                transform: translateY(-5px) scale(1.05);
            }

            100% {
                transform: translateY(0) scale(1);
            }
        }

        @keyframes float {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }

            100% {
                transform: translateY(0px);
            }
        }

        @keyframes glow {
            0% {
                box-shadow: 0 0 5px rgba(255, 255, 255, 0.5);
            }

            50% {
                box-shadow: 0 0 20px rgba(255, 255, 255, 0.8);
            }

            100% {
                box-shadow: 0 0 5px rgba(255, 255, 255, 0.5);
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translate3d(0, -20px, 0);
            }

            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Main Content Styles */
        .main-content-container {
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.05);
            margin-top: -50px;
            position: relative;
            z-index: 2;
            padding: 3rem;
        }

        /* Stats Cards */
        .stat-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
        }

        /* Feature Cards */
        .feature-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            overflow: hidden;
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        /* Gallery Section */
        .gallery-section {
            margin-bottom: 3rem;
        }

        .gallery-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .gallery-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .gallery-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            height: 200px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .gallery-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .gallery-item:hover img {
            transform: scale(1.05);
        }

        /* Alumni Map Section */
        .map-section {
            margin-bottom: 3rem;
        }

        .map-header {
            margin-bottom: 1.5rem;
        }

        .map-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .map-container {
            background-color: #f8f9fa;
            border-radius: 12px;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #dee2e6;
            position: relative;
            overflow: hidden;
        }

        .map-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .map-placeholder {
            text-align: center;
            padding: 2rem;
        }

        .map-icon {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        /* Divider */
        .section-divider {
            border: 0;
            height: 1px;
            background-color: #e9ecef;
            margin: 2.5rem 0;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .main-content-container {
                margin-top: -30px;
                padding: 1.5rem;
            }

            .banner {
                height: 70vh;
            }

            .banner-content h1 {
                font-size: 2.5rem;
            }

            .banner-content p {
                font-size: 1.1rem;
            }

            .banner-btn {
                width: 180px;
                padding: 12px 0;
                font-size: 1rem;
            }

            .map-title {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .banner {
                height: 60vh;
            }

            .banner-content h1 {
                font-size: 2rem;
            }

            .banner-content p {
                font-size: 1rem;
                margin-bottom: 1.5rem;
            }

            .banner-buttons {
                flex-direction: column;
                align-items: center;
            }

            .banner-btn {
                width: 160px;
                padding: 10px 0;
                font-size: 0.9rem;
                margin-bottom: 0.5rem;
            }

            .stat-number {
                font-size: 2rem;
            }



            .map-container {
                height: 300px;
            }
/* Gallery Carousel Styles */
.gallery-carousel-img {
    height: 400px;
    width: 100%;
    object-fit: cover;
    border-radius: 8px;
}

.carousel-inner {
    border-radius: 8px;
    overflow: hidden;
}

.carousel-caption {
    background: rgba(0, 0, 0, 0.6);
    border-radius: 8px;
    padding: 15px;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    width: 80%;
}

.carousel-indicators button {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin: 0 5px;
}

@media (max-width: 768px) {
    .gallery-carousel-img {
        height: 300px;
    }
    
    .carousel-caption {
        width: 90%;
        padding: 10px;
    }
    
    .carousel-caption h5 {
        font-size: 1rem;
    }
    
    .carousel-caption p {
        font-size: 0.8rem;
        margin-bottom: 0;
    }
}
        }
    </style>
</head>

<body>
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
                        <a class="nav-link" href="view_alumni.php"><i class="bi bi-people nav-bi"></i> Alumni</a>
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

    <!-- Banner Section -->
    <div class="banner">
        <!-- Slide 1 - Local Image -->
        <div class="banner-slide active">
            <img src="images\clgmain.jpg" alt="College Main Building" class="slide-image">
        </div>

        <!-- Slide 2 - External Image -->
        <div class="banner-slide">
            <img src="images\clgmain2.jpg"
                alt="Graduation Ceremony"
                class="slide-image"
                loading="lazy">
        </div>

        <!-- Slide 3 - External Image -->
        <div class="banner-slide">
            <img src="images\clgmain3.jpg"
                alt="College Meeting"
                class="slide-image"
                loading="lazy">
        </div>
    </div>
    <div class="banner-content">
        <h1>MIT Alumni PORTAL</h1>
        <p style="color: #f1f1f1; font-weight: 700; font-size: 1.25rem; position: relative; display: inline-block; padding-bottom: 5px;">
            <strong>Connect with fellow graduates, discover career opportunities, and stay engaged with your alma mater.</strong>
            <span style="position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background: #DD7228; transform: scaleX(0); transform-origin: right; transition: transform 0.3s ease;"></span>
        </p>

        <script>
            // Add hover animation
            document.querySelector('p').addEventListener('mouseenter', function() {
                this.querySelector('span').style.transform = 'scaleX(1)';
                this.querySelector('span').style.transformOrigin = 'left';
            });
            document.querySelector('p').addEventListener('mouseleave', function() {
                this.querySelector('span').style.transform = 'scaleX(0)';
                this.querySelector('span').style.transformOrigin = 'right';
            });
        </script>
        <div class="banner-buttons">
            <button class="banner-btn pulse-animation" onclick="location.href='view_events.php'">UPCOMING EVENTS</button>
            <button class="banner-btn float-animation" onclick="location.href='view_advertisements.php'">CAREER OPPORTUNITIES</button>
        </div>
    </div>
    </div>

    <div class="container main-content-container">
        <!-- Welcome Section -->
        <div class="row mb-5" data-animate="fadeIn">
            <div class="col-md-8">
                <h1 class="fw-bold mb-3">Welcome back, <?php echo htmlspecialchars($first_name); ?> !</h1>
                <p class="lead text-muted">Connect with your alumni network and discover new opportunities.</p>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4 g-4">
            <div class="col-md-4" data-animate="fadeIn">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-people-fill me-3" style="font-size: 2rem;"></i>
                        <div>
                            <div class="stat-number"><?php echo number_format($atharv_count); ?></div>
                            <div>Alumni Connected</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-animate="fadeIn" data-delay="100">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-calendar-check-fill me-3" style="font-size: 2rem;"></i>
                        <div>
                            <div class="stat-number"><?php echo $event_count; ?></div>
                            <div>Upcoming Events</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-animate="fadeIn" data-delay="200">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-briefcase-fill me-3" style="font-size: 2rem;"></i>
                        <div>
                            <div class="stat-number"><?php echo $job_count; ?></div>
                            <div>Job Opportunities</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Grid -->
        <div class="row g-4 mb-5">
            <div class="col-md-6 col-lg-4" data-animate="fadeIn">
                <div class="feature-card p-4">
                    <div class="card-icon">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <h3>Profile</h3>
                    <p>Update your professional profile, education history, and work experience.</p>
                    <a href="update_profile.php?email=<?php echo htmlspecialchars($email); ?>" class="btn btn-outline-primary">Manage Profile</a>
                </div>
            </div>

            <div class="col-md-6 col-lg-4" data-animate="fadeIn" data-delay="100">
                <div class="feature-card p-4">
                    <div class="card-icon">
                        <i class="bi bi-calendar2-event"></i>
                    </div>
                    <h3>Events</h3>
                    <p>Browse and RSVP to upcoming atharv events, reunions, and workshops.</p>
                    <a href="view_events.php" class="btn btn-outline-primary">View Events</a>
                </div>
            </div>

            <div class="col-md-6 col-lg-4" data-animate="fadeIn" data-delay="200">
                <div class="feature-card p-4">
                    <div class="card-icon">
                        <i class="bi bi-briefcase"></i>
                    </div>
                    <h3>Opportunities</h3>
                    <p>Find exclusive job listings and career development opportunities.</p>
                    <a href="view_advertisements.php" class="btn btn-outline-primary">Browse Jobs</a>
                </div>
            </div>

            <div class="col-md-6 col-lg-4" data-animate="fadeIn" data-delay="300">
                <div class="feature-card p-4">
                    <div class="card-icon">
                        <i class="bi bi-newspaper"></i>
                    </div>
                    <h3>News</h3>
                    <p>Stay updated with the latest announcements from MIT and alumni.</p>
                    <a href="#" class="btn btn-outline-primary">Read News</a>
                </div>
            </div>

            <div class="col-md-6 col-lg-4" data-animate="fadeIn" data-delay="400">
                <div class="feature-card p-4">
                    <div class="card-icon">
                        <i class="bi bi-map"></i>
                    </div>
                    <h3>Alumni Map
                    </h3>
                    <p>Explore where MIT Alumni are located around the world.</p>
                    <a href="#" class="btn btn-outline-primary">View Map</a>
                </div>
            </div>

            <div class="col-md-6 col-lg-4" data-animate="fadeIn" data-delay="500">
                <div class="feature-card p-4">
                    <div class="card-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <h3>Mentorship</h3>
                    <p>Connect with mentors or become one to help fellow alumni.</p>
                    <a href="#" class="btn btn-outline-primary">Learn More</a>
                </div>
            </div>
        </div>

        <!-- Success Stories -->
        <div class="mb-5" data-animate="fadeIn">
            <h2 class="mb-4 fw-bold"><i class="bi bi-trophy me-2"></i> Alumni Success Stories</h2>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="feature-card p-4 h-100">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                <i class="bi bi-person-fill" style="font-size: 1.5rem; color: var(--primary);"></i>
                            </div>
                            <div class="ms-3">
                                <h5 class="mb-0">Sarah Johnson</h5>
                                <small class="text-muted">Class of 2015 | Tech Entrepreneur</small>
                            </div>
                        </div>
                        <p>"The MIT Alumni network helped me secure funding for my startup and connect with key industry partners."</p>
                        <a href="#" class="btn btn-sm btn-outline-primary">Read Story</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="feature-card p-4 h-100">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                <i class="bi bi-person-fill" style="font-size: 1.5rem; color: var(--primary);"></i>
                            </div>
                            <div class="ms-3">
                                <h5 class="mb-0">Michael Chen</h5>
                                <small class="text-muted">Class of 2008 | Research Scientist</small>
                            </div>
                        </div>
                        <p>"Through the alumni mentorship program, I found guidance that shaped my research career path."</p>
                        <a href="#" class="btn btn-sm btn-outline-primary">Read Story</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gallery Section -->
<div class="gallery-section">
    <div class="gallery-header">
        <h2 class="gallery-title"><i class="bi bi-images me-2"></i> Recent Event Gallery</h2>
        <a href="view_gallery.php" class="btn btn-outline-primary btn-sm">View All</a>
    </div>
    
    <!-- Carousel -->
    <div id="galleryCarousel" class="carousel slide" data-bs-ride="carousel">
        <!-- Indicators -->
        <div class="carousel-indicators">
            <?php
            $gallery_query = $conn->query("SELECT * FROM gallery_table ORDER BY upload_date DESC LIMIT 5");
            $i = 0;
            while($image = $gallery_query->fetch_assoc()):
            ?>
            <button type="button" data-bs-target="#galleryCarousel" data-bs-slide-to="<?= $i ?>" <?= $i == 0 ? 'class="active"' : '' ?> aria-label="Slide <?= $i + 1 ?>"></button>
            <?php 
            $i++;
            endwhile; 
            ?>
        </div>
        
        <div class="carousel-inner">
            <?php
            // Reset pointer
            $gallery_query->data_seek(0);
            $active = true;
            
            while($image = $gallery_query->fetch_assoc()):
            ?>
            <div class="carousel-item <?= $active ? 'active' : '' ?>">
                <div class="d-flex justify-content-center">
                    <img src="uploads/gallery/<?= htmlspecialchars($image['image_path']) ?>" 
                         class="d-block gallery-carousel-img" 
                         alt="<?= htmlspecialchars($image['description']) ?>"
                         style="width: 100%; height: 400px; object-fit: cover;">
                </div>
                <div class="carousel-caption d-none d-md-block">
                    <h5><?= htmlspecialchars($image['title']) ?></h5>
                    <p><?= htmlspecialchars($image['description']) ?></p>
                </div>
            </div>
            <?php 
            $active = false;
            endwhile; 
            ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#galleryCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#galleryCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
</div>

<script>
    // Initialize carousel with 5-second interval
    document.addEventListener('DOMContentLoaded', function() {
        var myCarousel = document.getElementById('galleryCarousel');
        var carousel = new bootstrap.Carousel(myCarousel, {
            interval: 5000, // 5 seconds
            ride: 'carousel'
        });
    });
</script>

        <hr class="section-divider">

        <div class="map-section">
            <div class="map-header">
                <h2 class="map-title"><i class="bi bi-map me-2"></i> Alumni Network Map</h2>
            </div>
            <div class="map-container">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3784.216909222709!2d73.9922803143696!3d18.46398297594758!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3bc2e8d0d9f8e0a5%3A0x9d6c5b8b1a5d5b5e!2sMIT%20ADT%20University%2C%20Railway%20Station%2C%20MIT%20ADT%20Campus%2C%20Rajbaugh%2C%20Solapur%20-%20Pune%20Hwy%2C%20near%20Bharat%20Petrol%20Pump%2C%20Loni%20Kalbhor%2C%20Maharashtra%20412201!5e0!3m2!1sen!2sin!4v1620000000000!5m2!1sen!2sin"
                    width="100%"
                    height="100%"
                    style="border:0;"
                    allowfullscreen=""
                    loading="lazy">
                </iframe>
            </div>
            <div class="text-center mt-3">


                <!-- Back to Top Button -->
                <div class="back-to-top" id="backToTop">
                    <i class="bi bi-arrow-up"></i>
                </div>

                <!-- Bootstrap JS -->
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

                <script>
                    // Banner Slideshow
                    let currentSlide = 0;
                    const slides = document.querySelectorAll('.banner-slide');
                    const totalSlides = slides.length;

                    function showSlide(index) {
                        slides.forEach(slide => slide.classList.remove('active'));
                        slides[index].classList.add('active');
                    }

                    function nextSlide() {
                        currentSlide = (currentSlide + 1) % totalSlides;
                        showSlide(currentSlide);
                    }

                    // Change slide every 5 seconds
                    setInterval(nextSlide, 5000);

                    // Back to Top Button
                    const backToTop = document.getElementById('backToTop');

                    window.addEventListener('scroll', () => {
                        if (window.pageYOffset > 300) {
                            backToTop.classList.add('show');
                        } else {
                            backToTop.classList.remove('show');
                        }
                    });

                    backToTop.addEventListener('click', () => {
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    });

                    // Animation Script
                    document.addEventListener('DOMContentLoaded', function() {
                        const animateElements = document.querySelectorAll('[data-animate]');

                        const observer = new IntersectionObserver((entries) => {
                            entries.forEach(entry => {
                                if (entry.isIntersecting) {
                                    entry.target.classList.add('animated');
                                    observer.unobserve(entry.target);
                                }
                            });
                        }, {
                            threshold: 0.1
                        });

                        animateElements.forEach(el => {
                            const delay = el.getAttribute('data-delay') || 0;
                            el.style.transitionDelay = `${delay}ms`;
                            observer.observe(el);
                        });

                        // Button hover effects
                        const buttons = document.querySelectorAll('.banner-btn');

                        buttons.forEach(button => {
                            // Stop animation on hover
                            button.addEventListener('mouseenter', function() {
                                this.style.animation = 'none';
                                this.style.transform = 'translateY(-5px)';
                            });

                            // Restart animation when not hovering
                            button.addEventListener('mouseleave', function() {
                                if (this.classList.contains('pulse-animation')) {
                                    this.style.animation = 'pulse 2s infinite ease-in-out';
                                } else if (this.classList.contains('float-animation')) {
                                    this.style.animation = 'float 3s infinite ease-in-out';
                                }
                                this.style.transform = 'translateY(0)';
                            });
                        });
                    });
                </script>
</body>

</html>