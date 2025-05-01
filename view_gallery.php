<?php
session_start();
require 'db_controller.php'; // Changed to require for critical files

$email = $_SESSION['logged_account']['email'];

// Error handling for DB connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get all active events with their photo count and thumbnail
$events = [];
$stmt = $conn->prepare("
    SELECT e.*, 
    (SELECT COUNT(*) FROM alumni_gallery_photos WHERE event_id = e.id) as photo_count,
    (SELECT thumbnail_path FROM alumni_gallery_photos WHERE event_id = e.id LIMIT 1) as thumbnail_path
    FROM alumni_gallery_events e
    WHERE e.is_active = 1
    ORDER BY e.event_date DESC
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Verify thumbnail exists
        if (!empty($row['thumbnail_path']) && !file_exists($row['thumbnail_path'])) {
            $row['thumbnail_path'] = null;
        }
        $events[] = $row;
    }
} else {
    die("Execute failed: " . $stmt->error);
}

$stmt->close();
// Don't close connection yet as we might need it for the modal
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photo Gallery | MIT Alumni Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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

        .gallery-container {
            padding: 2rem 0;
        }
        .event-card {
            transition: transform 0.3s ease;
            margin-bottom: 2rem;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            cursor: pointer;
            height: 100%;
        }
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        .event-thumbnail {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 4px 4px 0 0;
        }
        .carousel-item img {
            max-height: 70vh;
            object-fit: contain;
            margin: 0 auto;
        }
        .carousel-control-prev, .carousel-control-next {
            background-color: rgba(0,0,0,0.3);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
        }
        .carousel-caption {
            background-color: rgba(0,0,0,0.5);
            border-radius: 5px;
        }
        .empty-gallery {
            text-align: center;
            padding: 4rem;
            color: #6c757d;
        }
        .view-gallery-btn {
            transition: all 0.3s ease;
        }
        .default-thumbnail {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 200px;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <!-- Navigation would go here -->
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
                        <a class="nav-link" href="main_menu.php"><i class="bi bi-images me-1"></i> Home</a>
                    </li>
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
                        <a class="nav-link" href="view_profile.php?email=<?php echo htmlspecialchars($email); ?>"><i class="bi bi-person me-1"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Alumni-Portal-main/logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container gallery-container">
        <h1 class="mb-4">Photo Gallery</h1>
        
        <?php if (empty($events)): ?>
            <div class="empty-gallery">
                <i class="bi bi-images" style="font-size: 3rem;"></i>
                <h3 class="mt-3">No Events Available</h3>
                <p>Check back later for upcoming gallery events.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($events as $event): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card event-card h-100">
                            <?php if (!empty($event['thumbnail_path'])): ?>
                                <img src="<?php echo htmlspecialchars($event['thumbnail_path']); ?>" 
                                     class="event-thumbnail" 
                                     alt="<?php echo htmlspecialchars($event['event_name']); ?>"
                                     onerror="this.src='assets/default-image.jpg'">
                            <?php else: ?>
                                <div class="default-thumbnail">
                                    <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($event['event_name']); ?></h5>
                                <p class="card-text text-muted">
                                    <small><?php echo date('F j, Y', strtotime($event['event_date'])); ?></small>
                                </p>
                                <p class="card-text flex-grow-1"><?php echo htmlspecialchars($event['description']); ?></p>
                                <button class="btn btn-primary view-gallery-btn mt-auto" 
                                        data-event-id="<?php echo htmlspecialchars($event['id']); ?>">
                                    View Gallery (<?php echo (int)$event['photo_count']; ?> photos)
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Gallery Modal -->
    <div class="modal fade" id="galleryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="galleryModalLabel">Event Gallery</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="eventCarousel" class="carousel slide" data-bs-ride="false">
                        <div class="carousel-inner" id="carousel-inner">
                            <!-- Content loaded dynamically -->
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#eventCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#eventCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gallery button functionality
        const viewButtons = document.querySelectorAll('.view-gallery-btn');
        const galleryModal = new bootstrap.Modal(document.getElementById('galleryModal'));
        
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const eventId = this.getAttribute('data-event-id');
                if (!eventId) {
                    console.error('No event ID found on button');
                    return;
                }
                
                loadEventGallery(eventId);
            });
        });
        
        function loadEventGallery(eventId) {
            const modalTitle = document.getElementById('galleryModalLabel');
            const carouselInner = document.getElementById('carousel-inner');
            
            // Show loading state
            modalTitle.textContent = 'Loading Gallery...';
            carouselInner.innerHTML = `
                <div class="d-flex justify-content-center align-items-center" style="height: 400px;">
                    <div class="spinner-border text-primary"></div>
                </div>
            `;
            
            // Show modal
            galleryModal.show();
            
            // Fetch event photos
            fetch(`get_event_photos.php?event_id=${encodeURIComponent(eventId)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data || !data.success) {
                        throw new Error(data?.error || 'Failed to load gallery');
                    }
                    
                    // Update modal title
                    modalTitle.textContent = data.event?.event_name || 'Event Gallery';
                    
                    // Clear loading state
                    carouselInner.innerHTML = '';
                    
                    // Check if there are photos
                    if (!data.photos || data.photos.length === 0) {
                        carouselInner.innerHTML = `
                            <div class="carousel-item active">
                                <div class="d-flex justify-content-center align-items-center" style="height: 400px;">
                                    <p class="text-muted">No photos available for this event</p>
                                </div>
                            </div>
                        `;
                        return;
                    }
                    
                    // Add photos to carousel
                    data.photos.forEach((photo, index) => {
                        const item = document.createElement('div');
                        item.className = `carousel-item ${index === 0 ? 'active' : ''}`;
                        item.innerHTML = `
                            <img src="${escapeHtml(photo.image_path)}" 
                                 class="d-block w-100" 
                                 style="max-height: 70vh; object-fit: contain;"
                                 onerror="this.onerror=null;this.src='assets/default-image.jpg'"
                                 alt="Event photo ${index + 1}">
                            <div class="carousel-caption d-none d-md-block">
                                <p>${index + 1} of ${data.photos.length}</p>
                            </div>
                        `;
                        carouselInner.appendChild(item);
                    });
                })
                .catch(error => {
                    console.error('Error loading gallery:', error);
                    carouselInner.innerHTML = `
                        <div class="carousel-item active">
                            <div class="d-flex justify-content-center align-items-center" style="height: 400px;">
                                <p class="text-danger">Error: ${escapeHtml(error.message)}</p>
                            </div>
                        </div>
                    `;
                });
        }
        
        // Simple HTML escape function
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    });
    </script>
</body>
</html>
<?php
// Close connection at the very end
$conn->close();
?>