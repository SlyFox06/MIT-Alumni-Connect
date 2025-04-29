<?php
session_start();
include 'db_controller.php';

// Get all active gallery events with their photos
$events = [];
$stmt = $conn->prepare("
    SELECT e.id, e.event_name, e.event_date, e.description, 
           p.id as photo_id, p.image_path, p.thumbnail_path, p.upload_date
    FROM alumni_gallery_events e
    LEFT JOIN alumni_gallery_photos p ON e.id = p.event_id
    WHERE e.is_active = 1
    ORDER BY e.event_date DESC, p.upload_date DESC
");

// Check if prepare failed
if ($stmt === false) {
    die("Database error: " . $conn->error);
}

if (!$stmt->execute()) {
    die("Database error: " . $stmt->error);
}

$result = $stmt->get_result();

// Organize data into events with their photos
while ($row = $result->fetch_assoc()) {
    $event_id = $row['id'];
    if (!isset($events[$event_id])) {
        $events[$event_id] = [
            'id' => $row['id'],
            'name' => $row['event_name'],
            'date' => $row['event_date'],
            'description' => $row['description'],
            'photos' => []
        ];
    }
    
    // Only add photo if it exists (LEFT JOIN may return NULL photo fields)
    if ($row['photo_id'] && file_exists($row['image_path'])) {
        $events[$event_id]['photos'][] = [
            'id' => $row['photo_id'],
            'image' => $row['image_path'],
            'thumbnail' => $row['thumbnail_path'],
            'upload_date' => $row['upload_date']
        ];
    }
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photo Gallery | MIT Alumni Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .gallery-container {
            padding: 2rem 0;
        }
        .event-card {
            margin-bottom: 2rem;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .event-card:hover {
            transform: translateY(-5px);
        }
        .event-header {
            background-color: #002c59;
            color: white;
            padding: 1rem;
            border-radius: 0.375rem 0.375rem 0 0 !important;
        }
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }
        .photo-thumbnail {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .photo-thumbnail:hover {
            transform: scale(1.03);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        .modal-image {
            max-height: 70vh;
            width: auto;
            max-width: 100%;
            margin: 0 auto;
            display: block;
        }
        .empty-gallery {
            text-align: center;
            padding: 4rem;
            color: #6c757d;
        }
        .event-date {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .photo-count-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: #dc3545;
        }
        .carousel-control-prev, .carousel-control-next {
            width: 5%;
            background-color: rgba(0,0,0,0.3);
        }
        .carousel-indicators {
            position: relative;
            margin: 0;
            padding: 1rem 0;
            justify-content: center;
        }
        .carousel-indicators [data-bs-target] {
            width: 80px;
            height: 60px;
            margin: 0 5px;
            opacity: 0.7;
            border: none;
            overflow: hidden;
        }
        .carousel-indicators [data-bs-target] img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .carousel-indicators .active {
            opacity: 1;
            border: 2px solid #0d6efd;
        }
    </style>
</head>
<body>
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
                    <div class="col-lg-6">
                        <div class="card event-card">
                            <div class="card-header event-header">
                                <h3 class="h5 mb-0"><?php echo htmlspecialchars($event['name']); ?></h3>
                                <div class="event-date">
                                    <?php echo date('F j, Y', strtotime($event['date'])); ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($event['description'])): ?>
                                    <p class="card-text"><?php echo htmlspecialchars($event['description']); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($event['photos'])): ?>
                                    <h4 class="h6 mb-3 position-relative d-inline-block">
                                        Event Photos
                                        <span class="badge rounded-pill photo-count-badge">
                                            <?php echo count($event['photos']); ?>
                                        </span>
                                    </h4>
                                    <div class="photo-grid">
                                        <?php foreach ($event['photos'] as $index => $photo): ?>
                                            <div class="photo-item" 
                                                 data-bs-toggle="modal" 
                                                 data-bs-target="#photoModal"
                                                 data-event-id="<?php echo $event['id']; ?>"
                                                 data-photo-index="<?php echo $index; ?>">
                                                <img src="<?php echo htmlspecialchars($photo['thumbnail']); ?>" 
                                                     alt="Event photo" 
                                                     class="photo-thumbnail"
                                                     onerror="this.src='assets/default-image.jpg'">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info mb-0">
                                        No photos available for this event yet.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="photoModalLabel">Event Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="photoCarousel" class="carousel slide" data-bs-interval="false">
                        <div class="carousel-inner" id="carousel-inner">
                            <!-- Carousel items will be added dynamically -->
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#photoCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#photoCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                    <div class="carousel-indicators" id="carousel-thumbnails">
                        <!-- Thumbnail indicators will be added dynamically -->
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="photoDetails" class="text-muted small w-100">
                        <!-- Photo details will be added dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Prepare photo data for JavaScript
            const eventsData = <?php echo json_encode(array_values($events)); ?>;
            
            // Handle photo modal display
            const photoModal = document.getElementById('photoModal');
            if (photoModal) {
                photoModal.addEventListener('show.bs.modal', function(event) {
                    const trigger = event.relatedTarget;
                    const eventId = parseInt(trigger.getAttribute('data-event-id'));
                    const photoIndex = parseInt(trigger.getAttribute('data-photo-index'));
                    
                    // Find the event in our data
                    const event = eventsData.find(e => e.id == eventId);
                    if (!event) return;
                    
                    // Update modal title
                    document.getElementById('photoModalLabel').textContent = event.name;
                    
                    // Build carousel items
                    const carouselInner = document.getElementById('carousel-inner');
                    carouselInner.innerHTML = '';
                    
                    // Build thumbnail indicators
                    const carouselThumbnails = document.getElementById('carousel-thumbnails');
                    carouselThumbnails.innerHTML = '';
                    
                    event.photos.forEach((photo, index) => {
                        // Create carousel item
                        const carouselItem = document.createElement('div');
                        carouselItem.className = `carousel-item ${index === photoIndex ? 'active' : ''}`;
                        
                        const img = document.createElement('img');
                        img.src = photo.image;
                        img.className = 'modal-image img-fluid';
                        img.alt = `Photo from ${event.name}`;
                        img.onerror = function() {
                            this.src = 'assets/default-image.jpg';
                        };
                        
                        carouselItem.appendChild(img);
                        carouselInner.appendChild(carouselItem);
                        
                        // Create thumbnail indicator
                        const thumbnailBtn = document.createElement('button');
                        thumbnailBtn.type = 'button';
                        thumbnailBtn.setAttribute('data-bs-target', '#photoCarousel');
                        thumbnailBtn.setAttribute('data-bs-slide-to', index);
                        thumbnailBtn.className = index === photoIndex ? 'active' : '';
                        thumbnailBtn.setAttribute('aria-current', index === photoIndex ? 'true' : 'false');
                        thumbnailBtn.setAttribute('aria-label', `Slide ${index + 1}`);
                        
                        const thumbnailImg = document.createElement('img');
                        thumbnailImg.src = photo.thumbnail;
                        thumbnailImg.alt = `Thumbnail ${index + 1}`;
                        thumbnailImg.onerror = function() {
                            this.src = 'assets/default-thumbnail.jpg';
                        };
                        
                        thumbnailBtn.appendChild(thumbnailImg);
                        carouselThumbnails.appendChild(thumbnailBtn);
                    });
                    
                    // Update photo details
                    updatePhotoDetails(event, photoIndex);
                });
                
                // Initialize carousel when modal is shown
                photoModal.addEventListener('shown.bs.modal', function() {
                    const carousel = new bootstrap.Carousel(document.getElementById('photoCarousel'), {
                        interval: false
                    });
                    
                    // Update details when slide changes
                    document.getElementById('photoCarousel').addEventListener('slid.bs.carousel', function(event) {
                        const activeIndex = event.to;
                        const eventId = parseInt(
                            document.querySelector('[data-bs-target="#photoModal"][data-event-id]').getAttribute('data-event-id')
                        );
                        const event = eventsData.find(e => e.id == eventId);
                        
                        if (event && event.photos[activeIndex]) {
                            updatePhotoDetails(event, activeIndex);
                        }
                    });
                });
                
                // Function to update photo details
                function updatePhotoDetails(event, index) {
                    const uploadDate = new Date(event.photos[index].upload_date);
                    document.getElementById('photoDetails').innerHTML = `
                        Uploaded: ${uploadDate.toLocaleDateString()} 
                        | Photo ${index + 1} of ${event.photos.length}
                    `;
                    
                    // Update active thumbnail
                    const thumbnails = document.querySelectorAll('#carousel-thumbnails button');
                    thumbnails.forEach((thumb, i) => {
                        if (i === index) {
                            thumb.classList.add('active');
                            thumb.setAttribute('aria-current', 'true');
                        } else {
                            thumb.classList.remove('active');
                            thumb.removeAttribute('aria-current');
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>