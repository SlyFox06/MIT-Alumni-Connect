<?php
// This must be the VERY FIRST LINE in the file
session_start();

// Include admin check file
require 'logged_admin.php';

include 'db_controller.php';
$conn->select_db("atharv");

// Get current date for comparison
date_default_timezone_set('Asia/Kuching');
$todayDate = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events/News | MIT Alumni Portal</title>

    <link rel="stylesheet" href="css/styles.css">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <!-- DataTables Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" />
    <!-- DataTables Bootstrap 5 fixedHeader -->
    <link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.4.0/css/fixedHeader.bootstrap5.min.css" />
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
            border: none;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            transition: all var(--transition-speed) ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .image-table-container {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
        }
        
        .image-table-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
        
        .breadcrumb-link {
            color: #6c757d;
            text-decoration: none;
        }
        
        .breadcrumb-link:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }
        
        .breadcrumb-active {
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .slider {
            background-color: #f8f9fa;
            border-radius: 0 0 8px 8px;
            box-shadow: inset 0 3px 5px rgba(0,0,0,0.05);
        }
        
        .dropdown-menu-normal {
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: none;
        }
        
        /* Custom form fields styling */
        .form-field-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .form-field-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        /* Status badges */
        .badge-upcoming {
            background-color: #198754;
            color: white;
        }
        
        .badge-past {
            background-color: #6c757d;
            color: white;
        }
        
        /* Registration form preview */
        .registration-form-preview {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .form-preview-item {
            margin-bottom: 10px;
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
    <!-- Enhanced Navbar -->
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
                        <a class="nav-link nav-admin-link px-3 px-lg-4" href="main_menu_admin.php">
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
                        <a class="nav-link nav-admin-link nav-main-admin-active px-3 px-lg-4" href="manage_events.php">
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

    <!-- Breadcrumb -->
    <div class="container my-3">
        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a class="breadcrumb-link text-secondary link-underline link-underline-opacity-0" href="main_menu_admin.php">Home</a></li>
                <li class="breadcrumb-item breadcrumb-active" aria-current="page">Manage Events/News</li>
            </ol>
        </nav>
    </div>

    <?php
        // DELETE request
        if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
            // Extract data from request
            $rawData = file_get_contents('php://input');
            $data = json_decode($rawData, true);

            $id = $data['id'];
            if ($id != null) {
                // Remove image from storage
                $uploadDir = "images/";
                $result = $conn->query("SELECT * FROM event_table WHERE id = $id");
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if ($row['photo'] != "default_events.jpg" && $row['photo'] != "default_news.png" && file_exists($uploadDir . $row['photo']))
                        unlink($uploadDir.$row['photo']);

                    // Delete row from table
                    if ($conn->query("DELETE FROM event_table WHERE id = $id")) {
                        $_SESSION['flash_mode'] = "alert-success";
                        $_SESSION['flash'] = "<span class='fw-medium'>".$row['type']." ".$row['id']."</span> deleted successfully.";
                    } else {
                        $_SESSION['flash_mode'] = "alert-warning";
                        $_SESSION['flash'] = "An error has occured deleting <span class='fw-medium'>".$row['type']." ".$row['id']."</span>";
                    }
                }
            }
        }

        // Prepare flash message
        $tempFlash;
        if (isset($_SESSION['flash_mode'])){
            $tempFlash = $_SESSION['flash_mode'];
            unset($_SESSION['flash_mode']);
        }
    ?>

    <!-- Flash message -->
    <div class="container-fluid">
        <?php if (isset($_SESSION['flash_mode']) || isset($tempFlash)){ ?>
            <div class="row justify-content-center position-absolute top-1 start-50 translate-middle">
                <div class="col-auto">
                    <div class="alert <?php echo (isset($_SESSION['flash_mode'])) ? $_SESSION['flash_mode'] : '' . (isset($tempFlash) ? $tempFlash : ''); ?> mt-4 py-2 fade-out-alert row align-items-center" role="alert">
                        <i class="bi <?php echo (isset($tempFlash) && $tempFlash == "alert-success" ? "bi-check-circle" : ((isset($tempFlash) && ($tempFlash == "alert-primary" || $tempFlash == "alert-secondary") ? "bi-info-circle" : ((isset($tempFlash) && $tempFlash == "alert-warning" ? "bi-exclamation-triangle" : ""))))) ?> login-bi col-auto px-0"></i><div class="col ms-1"><?php echo isset($_SESSION['flash']) ? $_SESSION['flash'] : '' ?></div>
                    </div>
                    <div id="flash-message-container"></div> <!-- Special JS alert for delete -->
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="container mb-5">
        <!-- Page title -->
        <div class="row <?php echo (isset($_POST['eventID']) || isset($_GET['filterType']) || isset($_GET['filterTime']) || isset($_GET['search'])) ? NULL : 'slide-left' ?>">
            <div class="col"><h1>Manage Events/News</h1></div>
            <div class="col-auto align-self-center">
                <a role="button" href="add_event.php" class="btn btn-primary fw-medium px-4 py-2">
                    <i class="bi bi-plus-lg me-2" style="-webkit-text-stroke: 0.25px;"></i>Add Events/News
                </a>
                <button type="button" class="btn btn-success fw-medium px-4 py-2 ms-2" data-bs-toggle="modal" data-bs-target="#addFormModal">
                    <i class="bi bi-file-earmark-plus me-2" style="-webkit-text-stroke: 0.25px;"></i>Add Form Field
                </button>
            </div>
        </div>

        <!-- Filter -->
        <div class="container card mt-3 py-3 px-4 bg-white fw-medium <?php echo (isset($_POST['eventID']) || isset($_GET['filterType']) || isset($_GET['filterTime']) || isset($_GET['search'])) ? NULL : 'slide-left' ?>">
            <form id="eventsFilterForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="GET">
                <!-- Type (All, Events, News) -->
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="filterType" id="filterType1" value="All" <?php echo (isset($_GET['filterType']) && $_GET['filterType'] == 'All') ? 'checked' : ((!isset($_GET['filterTime'])) ? 'checked' : NULL ) ?>>
                    <label class="form-check-label" for="filterType1">All</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="filterType" id="filterType2" value="Event" <?php echo (isset($_GET['filterType']) && $_GET['filterType'] == 'Event') ? 'checked' : NULL ?>>
                    <label class="form-check-label badge text-bg-success" for="filterType2">Events</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="filterType" id="filterType3" value="News" <?php echo (isset($_GET['filterType']) && $_GET['filterType'] == 'News') ? 'checked' : NULL ?>>
                    <label class="form-check-label badge text-bg-warning" for="filterType3">News</label>
                </div>

                <!-- Time (All, Past, Upcoming) -->
                <div class="form-check-inline ms-4">
                    <div class="input-group">
                        <label class="input-group-text" for="filterTime"><i class="bi bi-clock-history" style="-webkit-text-stroke: 0.25px;"></i></label>
                        <select class="form-select fw-medium" id="filterTime" name="filterTime" aria-label="Time filter">
                            <option value="All" class="fw-medium" <?php echo (isset($_GET['filterTime']) && $_GET['filterTime'] == 'All') ? 'selected' : NULL ?>>All</option>
                            <option value="Past" class="fw-medium" <?php echo (isset($_GET['filterTime']) && $_GET['filterTime'] == 'Past') ? 'selected' : NULL ?>>Past</option>
                            <option value="Upcoming" class="fw-medium" <?php echo (isset($_GET['filterTime']) && $_GET['filterTime'] == 'Upcoming') ? 'selected' : NULL ?>>Upcoming</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-outline-primary fw-medium mb-1">Display List</button>

                <!-- Search Box -->
                <div class="form-check-inline me-0 float-end">
                    <div class="input-group">
                        <input type="text" class="form-control py-2" placeholder="Search events" name="search" aria-label="Search" aria-describedby="button-addon2" value="<?php echo (isset($_GET['search'])) ? trim($_GET['search']) : NULL; ?>">
                        <button class="btn btn-outline-primary px-3 py-2" type="submit" id="button-addon2"><i class="bi bi-search"></i></button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table (DataTables) -->
        <div class="table-container table-responsive px-5 pt-4 pb-5 mt-3 <?php echo (isset($_POST['eventID']) || isset($_GET['filterType']) || isset($_GET['filterTime']) || isset($_GET['search'])) ? NULL : '' ?>">
            <?php 
                $filterType = "";
                $filterTime = "";
                $filterSearch = "";

                // Type filter for Events/News
                if (isset($_GET['filterType']) && $_GET['filterType'] != 'All')
                    $filterType = "type = '" . $_GET['filterType'] . "'";

                // Time filter for Events/News
                if (isset($_GET['filterTime']) && $_GET['filterTime'] != 'All') {
                    if ($_GET['filterTime'] == 'Upcoming')
                        $filterTime = "event_date >= '" . $todayDate . "'";
                    elseif ($_GET['filterTime'] == 'Past')
                        $filterTime = "event_date < '" . $todayDate . "'";
                }

                // Search filter for Events/News
                if (isset($_GET['search']) && $_GET['search'] != "") {
                    $trimSearch = strtolower(trim($_GET['search']));
                    $filterSearch = "(
                        LOWER(id) LIKE '%$trimSearch%' OR 
                        LOWER(title) LIKE '%$trimSearch%' OR 
                        LOWER(location) LIKE '%$trimSearch%' OR 
                        LOWER(description) LIKE '%$trimSearch%' OR 
                        LOWER(event_date) LIKE '%$trimSearch%' OR 
                        DATE_FORMAT(event_date, '%d/%m/%Y') LIKE '%$trimSearch%' OR 
                        LOWER(type) LIKE '%$trimSearch%'
                    )";
                }

                // Puts WHERE and AND to the query appropriately
                $conditions = array_filter([$filterType, $filterTime, $filterSearch]);
                if (!empty($conditions))
                    $whereClause = "WHERE " . implode(" AND ", $conditions);
                else
                    $whereClause = "";

                // Query the database with the WHERE from previous
                $allEventsNews = $conn->query("SELECT * FROM event_table $whereClause");
            ?>

            <!-- Table -->
            <table id="eventTable" class="table table-hover">
                <thead>
                    <tr class="table-primary fs-5">
                        <th class="pe-4"></th>
                        <th>#</th>
                        <th>Type</th>
                        <th class="pe-5">Title</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
    
    <!-- Add Form Field Modal -->
    <div class="modal fade" id="addFormModal" tabindex="-1" aria-labelledby="addFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addFormModalLabel">Add Registration Form Field</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formFieldForm">
                        <div class="mb-3">
                            <label for="formEventSelect" class="form-label">Select Event</label>
                            <select class="form-select" id="formEventSelect" required>
                                <option value="" selected disabled>Select an event</option>
                                <?php
                                $upcomingEvents = $conn->query("SELECT * FROM event_table WHERE type = 'Event' AND event_date >= '$todayDate'");
                                while($event = $upcomingEvents->fetch_assoc()) {
                                    echo '<option value="'.$event['id'].'">'.$event['title'].' ('.date('d/m/Y', strtotime($event['event_date'])).')</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="fieldLabel" class="form-label">Field Label</label>
                            <input type="text" class="form-control" id="fieldLabel" placeholder="Enter field label" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="fieldType" class="form-label">Field Type</label>
                            <select class="form-select" id="fieldType" required>
                                <option value="" selected disabled>Select field type</option>
                                <option value="text">Text Input</option>
                                <option value="textarea">Text Area</option>
                                <option value="checkbox">Checkbox</option>
                                <option value="radio">Radio Buttons</option>
                                <option value="select">Dropdown Select</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="optionsContainer" style="display: none;">
                            <label for="fieldOptions" class="form-label">Options (one per line)</label>
                            <textarea class="form-control" id="fieldOptions" rows="3"></textarea>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="fieldRequired">
                            <label class="form-check-label" for="fieldRequired">Required Field</label>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Field</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Boostrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.js"></script>
    <!-- DataTables Bootstrap 5 -->
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <!-- Moment.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.2/moment.min.js"></script>
    <!-- DataTables Bootstrap 5 fixedHeader -->
    <script src="https://cdn.datatables.net/fixedheader/3.4.0/js/dataTables.fixedHeader.min.js"></script>
    <!-- Axios -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        // Display description when opening rows' slider
        function format(row) {
            // Parse custom fields if they exist
            let customFieldsHtml = '';
            if (row[9] && row[9] !== 'null') {
                try {
                    const customFields = JSON.parse(row[9]);
                    if (customFields && customFields.length > 0) {
                        customFieldsHtml = '<div class="registration-form-preview mt-3">';
                        customFieldsHtml += '<h6 class="fw-bold mb-3">Registration Form Fields:</h6>';
                        
                        customFields.forEach(field => {
                            customFieldsHtml += '<div class="form-preview-item">';
                            customFieldsHtml += `<strong>${field.label}</strong> `;
                            customFieldsHtml += `<span class="badge bg-light text-dark">${field.type}</span>`;
                            if (field.required) {
                                customFieldsHtml += ' <span class="badge bg-danger">Required</span>';
                            }
                            
                            if (field.options) {
                                customFieldsHtml += '<div class="mt-1 text-muted small">';
                                customFieldsHtml += field.options.join(', ');
                                customFieldsHtml += '</div>';
                            }
                            
                            customFieldsHtml += '</div>';
                        });
                        
                        customFieldsHtml += '</div>';
                    }
                } catch (e) {
                    console.error('Error parsing custom fields:', e);
                }
            }
            
            return (`
                <div class="slider">
                    <div class="row">
                        <div class="col-auto ms-3 my-3 me-0 pe-0">
                            <div class="image-table-container">
                                <img src="images/${row[8]}" class="img-fluid" alt="event_news_photo">
                            </div>
                        </div>
                        <div class="col mx-0 px-0">
                            <p class="fw-light ps-4 pe-5 pt-2 pb-3">${row[7]}</p>
                            ${customFieldsHtml}
                        </div>
                    </div>
                </div>
            `);
        }

        // DataTables init
        $.fn.dataTableExt.oStdClasses.sWrapper = "dataTables_wrapper dt-bootstrap5 no-footer <?php echo (isset($_POST['eventID']) || isset($_GET['filterType']) || isset($_GET['filterTime']) || isset($_GET['search'])) ? NULL : '' ?>";
        DataTable.datetime('D/MM/Y');
        $('#eventTable').DataTable({
            paging: true,
            lengthMenu: [ [10, 25, 50, 100, -1], [10, 25, 50, 100, "All"] ],
            info: true,
            searching: false,
            responsive: true,
            fixedHeader: {
                header: true,
                headerOffset: $('.navbar').height()
            },
            pagingType: 'full_numbers',
            order: [[1, "desc"]],
            columnDefs: [
                {
                    "targets": [0, 7],
                    "orderable": false,
                },
                {
                    "targets": '_all',
                    "createdCell": function (td, cellData, rowData, row, col) {
                        $(td).css('padding', '15px');
                    }
                },
            ],
        });

        // Onclick listener for the description displayer
        $('#eventTable tbody').on('click', 'a.dt-control', function () {
            var tr = $(this).closest('tr');
            var row = table.row(tr);
        
            if (row.child.isShown()) {
                // This row is already open - close it
                $('div.slider', row.child()).slideUp( function () {
                    row.child.hide();
                    tr.removeClass('shown');
                });
                $(this).html('<i class="bi bi-chevron-down"></i>');
            }
            else {
                // Open this row
                row.child( format(row.data()), 'no-padding' ).show();
                tr.addClass('shown');
                $('div.slider', row.child()).slideDown();
                $(this).html('<i class="bi bi-chevron-up"></i>');
            }
        });

        var table = $('#eventTable').DataTable();
        <?php
            // Populate the DataTables with data from DB
            foreach ($allEventsNews as $row) {
                // Format date
                $eventDate = date('m/d/Y', strtotime($row['event_date']));
                echo "var eventDate = new Date('{$eventDate}');\n";

                // Determine status
                $statusBadge = '';
                if ($row['event_date'] >= $todayDate) {
                    $statusBadge = '<span class="badge badge-upcoming">Upcoming</span>';
                } else {
                    $statusBadge = '<span class="badge badge-past">Past</span>';
                }

                // Dropdown actions to edit and delete
                $actionDropdown = '
                    <div class="dropstart me-4">
                        <div class="float-end" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-three-dots text-secondary"></i>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-normal mt-1 px-2">
                            <li><form action="edit_event.php" method="GET">
                                <input type="hidden" name="id" value="'.$row['id'].'">
                                <button type="submit" class="dropdown-item py-2 pe-5"><i class="bi bi-pencil me-3" style="font-size: 1.25rem; -webkit-text-stroke: 0.25px;"></i><div class="fw-medium">Edit</div></button>
                            </a></form></li>';
                
                // Only show form field option for upcoming events
                if ($row['event_date'] >= $todayDate && $row['type'] == 'Event') {
                    $actionDropdown .= '
                            <li><button type="button" class="dropdown-item py-2 pe-5" onclick="showAddFormFieldModal('.$row['id'].')">
                                <i class="bi bi-file-earmark-plus me-3" style="font-size: 1.25rem; -webkit-text-stroke: 0.25px;"></i><div class="fw-medium">Add Form Field</div>
                            </button></li>';
                }
                
                $actionDropdown .= '
                            <li><button type="button" class="dropdown-item py-2 pe-5" onclick="deleteEventNews('.$row['id'].', \''.$row['type'].'\', this)">
                                <i class="bi bi-trash me-3 text-danger" style="font-size: 1.25rem; -webkit-text-stroke: 0.25px;"></i><div class="fw-medium text-danger">Delete</div>
                            </button></li>
                        </ul>
                    </div>
                ';

                // Set badges for Event and News
                if ($row['type'] == "Event")
                    $type = '<span class="form-check-label badge text-bg-success">Events</span>';
                elseif ($row['type'] == "News")
                    $type = '<span class="form-check-label badge text-bg-warning">News</span>';

                // Expandable rows to display more information
                $expandCollapse = '<a class="dt-control text-secondary" style="cursor: pointer;"><i class="bi bi-chevron-down"></i></a>';

                // Add the row
                echo "table.row.add([`$expandCollapse`, {$row['id']}, `$type`, '{$row['title']}', '".date('d/m/Y', strtotime($row['event_date']))."', '{$row['location']}', `$statusBadge`, `$actionDropdown`, '{$row['description']}', '{$row['photo']}', '".addslashes($row['custom_fields'])."']).draw().nodes().to$().hide().fadeIn();\n";
            }
        ?>

        // JS alert function
        function displayFlashMessage(mode, message) {
            const flashMessageContainer = document.getElementById("flash-message-container");
            flashMessageContainer.innerHTML = `
                <div class="alert ${mode} mt-4 py-2 fade-out-alert row align-items-center" role="alert">
                    <i class="bi ${mode === 'alert-success' ? "bi-check-circle" : (mode === 'alert-primary' || mode === 'alert-secondary' ? "bi-info-circle" : (mode === 'alert-warning' ? "bi-exclamation-triangle" : ""))} login-bi col-auto px-0"></i>
                    <div class="col ms-1">${message}</div>
                </div>
            `;

            setTimeout(() => {
                flashMessageContainer.innerHTML = '';
            }, 5000);
        }
        
        // Function to delete the selected row from the table in DB and DataTables, deletes photo from storage too
        function deleteEventNews(id, type, row) {
            if (confirm(`Are you sure you want to delete this ${type.toLowerCase()}?`)) {
                axios.delete(`manage_events.php`, {
                    data: {
                        id: id,
                    }
                })
                .then(response => {
                    // Gracefully remove the row from the DataTables
                    var rowToRemove = $(row).parents('tr');
                    rowToRemove.fadeOut(250, () => {
                        table.row(rowToRemove).remove().draw();
                    })
                    displayFlashMessage("alert-success", type+" "+id+" deleted successfully.");
                })
                .catch(error => {
                    displayFlashMessage("alert-danger", "Error deleting "+type.toLowerCase()+" "+id);
                });
            }
        }
        
        // Show/hide options field based on field type selection
        $('#fieldType').change(function() {
            const showOptions = ['radio', 'select'].includes($(this).val());
            $('#optionsContainer').toggle(showOptions);
        });
        
        // Show add form field modal with event pre-selected
        function showAddFormFieldModal(eventId) {
            $('#formEventSelect').val(eventId);
            $('#addFormModal').modal('show');
        }
        
        // Handle form field submission
        $('#formFieldForm').submit(function(e) {
            e.preventDefault();
            
            const eventId = $('#formEventSelect').val();
            const fieldLabel = $('#fieldLabel').val();
            const fieldType = $('#fieldType').val();
            const fieldRequired = $('#fieldRequired').is(':checked');
            
            let fieldOptions = [];
            if (['radio', 'select'].includes(fieldType)) {
                fieldOptions = $('#fieldOptions').val().split('\n').filter(option => option.trim() !== '');
            }
            
            // Create field object
            const newField = {
                label: fieldLabel,
                type: fieldType,
                required: fieldRequired
            };
            
            if (fieldOptions.length > 0) {
                newField.options = fieldOptions;
            }
            
            // Send AJAX request to update the event with new field
            axios.post('update_event_fields.php', {
                eventId: eventId,
                newField: newField
            })
            .then(response => {
                if (response.data.success) {
                    // Refresh the page to show updated fields
                    location.reload();
                } else {
                    alert('Error adding field: ' + response.data.message);
                }
            })
            .catch(error => {
                alert('Error adding field: ' + error.response.data.message);
            });
        });
    </script>
</body>
</html>