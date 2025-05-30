<?php
// This must be the VERY FIRST LINE in the file
session_start();

// Include admin check file
require 'logged_admin.php';

include 'db_controller.php';
$conn->select_db("atharv");

// Initialize pending count if not set
if (!isset($pendingCount)) {
    $pendingCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Advertisements | MIT Alumni Portal</title>

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
            padding: 0.5rem 1rem;
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
        
        .badge-category {
            background-color: #6f42c1;
        }
        
        .badge-status-active {
            background-color: #198754;
        }
        
        .badge-status-inactive {
            background-color: #6c757d;
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
            
            .filter-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .filter-actions {
                justify-content: space-between;
                width: 100%;
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
                            <?php if ($pendingCount > 0) { ?> 
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger small-badge"><?php echo $pendingCount; ?></span>
                            <?php } ?>
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link px-3 px-lg-4" href="manage_events.php">
                            <i class="bi bi-calendar-event me-1"></i> Events
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link nav-admin-link nav-main-admin-active px-3 px-lg-4" href="manage_advertisements.php">
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

    <!-- Rest of your HTML/PHP code remains the same -->
    <!-- Breadcrumb -->
    <div class="container my-3">
        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a class="breadcrumb-link text-secondary link-underline link-underline-opacity-0" href="main_menu_admin.php">Home</a></li>
                <li class="breadcrumb-item breadcrumb-active" aria-current="page">Manage Advertisements</li>
            </ol>
        </nav>
    </div>

    <?php
        // POST request (also handles the deletes)
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_POST['filterStatus'])) {
                $_GET['filterStatus'] = htmlspecialchars($_POST['filterStatus']);
            }
            if (isset($_POST['search'])) {
                $_GET['search'] = htmlspecialchars($_POST['search']);
            }

            if (isset($_POST['id']) && isset($_POST['action'])) {
                if ($_POST['action'] == 'delete') {
                    $id = (int)$_POST['id'];

                    // Remove from storage
                    $uploadDir = "images/";
                    
                    // Use prepared statement to prevent SQL injection
                    $stmt = $conn->prepare("SELECT * FROM advertisement_table WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        if ($row['photo'] != "default_advertisement.jpg" && file_exists($uploadDir . $row['photo'])) {
                            unlink($uploadDir.$row['photo']);
                        }
    
                        // Delete row from table using prepared statement
                        $stmt = $conn->prepare("DELETE FROM advertisement_table WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        
                        if ($stmt->execute()) {
                            $_SESSION['flash_mode'] = "alert-success";
                            $_SESSION['flash'] = "<span class='fw-medium'>Advertisement ".htmlspecialchars($row['id'])."</span> deleted successfully.";
                        } else {
                            $_SESSION['flash_mode'] = "alert-warning";
                            $_SESSION['flash'] = "An error has occurred deleting <span class='fw-medium'>Advertisement ".htmlspecialchars($row['id'])."</span>";
                        }
                    }
                }
            }
        }

        // Prepare flash message
        $tempFlash = isset($_SESSION['flash_mode']) ? $_SESSION['flash_mode'] : '';
        if (isset($_SESSION['flash_mode'])) {
            unset($_SESSION['flash_mode']);
        }
    ?>

    <!-- Flash message -->
    <div class="container-fluid">
        <?php if (!empty($tempFlash) || isset($_SESSION['flash'])) { ?>
            <div class="row justify-content-center position-absolute top-1 start-50 translate-middle">
                <div class="col-auto">
                    <div class="alert <?php echo htmlspecialchars($tempFlash); ?> mt-4 py-2 fade-out-alert row align-items-center" role="alert">
                        <i class="bi <?php echo ($tempFlash == "alert-success" ? "bi-check-circle" : ($tempFlash == "alert-primary" || $tempFlash == "alert-secondary" ? "bi-info-circle" : ($tempFlash == "alert-warning" ? "bi-exclamation-triangle" : ""))); ?> login-bi col-auto px-0"></i>
                        <div class="col ms-1"><?php echo isset($_SESSION['flash']) ? $_SESSION['flash'] : '' ?></div>
                    </div>
                    <div id="flash-message-container"></div>
                </div>
            </div>
            <?php unset($_SESSION['flash']); ?>
        <?php } ?>
    </div>

    <div class="container mb-5">
        <div class="row <?php echo (isset($_POST['eventID']) || isset($_GET['filterType']) || isset($_GET['filterTime']) || isset($_GET['search'])) ? NULL : 'slide-left' ?>">
            <div class="col"><h1>Manage Advertisements</h1></div>
            <div class="col-auto align-self-center"><a role="button" href="add_advertisement.php" class="btn btn-primary fw-medium px-4 py-2"><i class="bi bi-plus-lg me-2" style="-webkit-text-stroke: 0.25px;"></i>Add Advertisements</a></div>
        </div>

        <!-- Filter -->
        <div class="container mt-3 py-3 px-4 card bg-white fw-medium <?php echo (isset($_POST['eventID']) || isset($_GET['filterStatus']) || isset($_GET['filterCategory']) || isset($_GET['search'])) ? NULL : 'slide-left' ?>">
            <form id="eventsFilterForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="GET" class="filter-container d-flex flex-wrap align-items-center">
                <!-- Status (All, Active, Inactive) -->
                <div class="d-flex flex-wrap align-items-center me-3">
                    <div class="form-check form-check-inline me-2">
                        <input class="form-check-input" type="radio" name="filterStatus" id="filterStatus1" value="All" <?php echo (isset($_GET['filterStatus']) && $_GET['filterStatus'] == 'All') ? 'checked' : ((!isset($_GET['filterCategory'])) ? 'checked' : NULL ) ?>>
                        <label class="form-check-label" for="filterStatus1">All</label>
                    </div>
                    <div class="form-check form-check-inline me-2">
                        <input class="form-check-input" type="radio" name="filterStatus" id="filterStatus2" value="Active" <?php echo (isset($_GET['filterStatus']) && $_GET['filterStatus'] == 'Active') ? 'checked' : NULL ?>>
                        <label class="form-check-label badge text-bg-success" for="filterStatus2">Active</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="filterStatus" id="filterStatus3" value="Inactive" <?php echo (isset($_GET['filterStatus']) && $_GET['filterStatus'] == 'Inactive') ? 'checked' : NULL ?>>
                        <label class="form-check-label badge text-bg-secondary" for="filterStatus3">Inactive</label>
                    </div>
                </div>

                <!-- Departments (All, Engineering, IT, Business, Design) -->
                <div class="form-check-inline me-3">
                    <div class="input-group">
                        <label class="input-group-text" for="filterCategory"><i class="bi bi-buildings"></i></label>
                        <select class="form-select fw-medium" id="filterCategory" name="filterCategory" aria-label="Time filter">
                            <option value="All" class="fw-medium" <?php echo (isset($_GET['filterCategory']) && $_GET['filterCategory'] == 'All') ? 'selected' : NULL ?>>All</option>
                            <option value="Engineering" class="fw-medium" <?php echo (isset($_GET['filterCategory']) && $_GET['filterCategory'] == 'Engineering') ? 'selected' : NULL ?>>Engineering</option>
                            <option value="IT" class="fw-medium" <?php echo (isset($_GET['filterCategory']) && $_GET['filterCategory'] == 'IT') ? 'selected' : NULL ?>>IT</option>
                            <option value="Business" class="fw-medium" <?php echo (isset($_GET['filterCategory']) && $_GET['filterCategory'] == 'Business') ? 'selected' : NULL ?>>Business</option>
                            <option value="Design" class="fw-medium" <?php echo (isset($_GET['filterCategory']) && $_GET['filterCategory'] == 'Design') ? 'selected' : NULL ?>>Design</option>
                        </select>
                    </div>
                </div>

                <div class="filter-actions d-flex align-items-center">
                    <button type="submit" class="btn btn-primary fw-medium mb-1 me-2">Display List</button>

                    <!-- Search box -->
                    <div class="form-check-inline">
                        <div class="input-group">
                            <input type="text" class="form-control py-2" placeholder="Search advertisements" name="search" aria-label="Search" aria-describedby="button-addon2" value="<?php echo isset($_GET['search']) ? htmlspecialchars(trim($_GET['search'])) : ''; ?>">
                            <button class="btn btn-primary px-3 py-2" type="submit" id="button-addon2"><i class="bi bi-search"></i></button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table (DataTables) -->
        <div class="table-container table-responsive px-5 pt-4 pb-5 mt-3 <?php echo (isset($_POST['eventID']) || isset($_GET['filterType']) || isset($_GET['filterTime']) || isset($_GET['search'])) ? NULL : "" ?>">
            <?php 
                // Initialize filter variables
                $filterStatus = "";
                $filterCategory = "";
                $filterSearch = "";

                // Status filter
                if (isset($_GET['filterStatus']) && $_GET['filterStatus'] != 'All') {
                    $filterStatus = "status = '" . $conn->real_escape_string($_GET['filterStatus']) . "'";
                }

                // Category filter
                if (isset($_GET['filterCategory']) && $_GET['filterCategory'] != 'All') {
                    $filterCategory = "category = '" . $conn->real_escape_string($_GET['filterCategory']) . "'";
                }

                // Search filter
                if (isset($_GET['search']) && $_GET['search'] != "") {
                    $trimSearch = strtolower(trim($conn->real_escape_string($_GET['search'])));
                    $filterSearch = "(
                        LOWER(id) LIKE '%$trimSearch%' OR 
                        LOWER(title) LIKE '%$trimSearch%' OR 
                        LOWER(description) LIKE '%$trimSearch%' OR 
                        LOWER(date_added) LIKE '%$trimSearch%' OR 
                        DATE_FORMAT(date_added, '%d/%m/%Y') LIKE '%$trimSearch%' OR 
                        LOWER(button_message) LIKE '%$trimSearch%' OR 
                        LOWER(button_link) LIKE '%$trimSearch%' OR 
                        LOWER(category) LIKE '%$trimSearch%' OR 
                        LOWER(status) LIKE '%$trimSearch%'
                    )";
                }

                // Build WHERE clause
                $conditions = array_filter([$filterStatus, $filterCategory, $filterSearch]);
                $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

                // Retrieve from db
                $allAdvertisements = $conn->query("SELECT * FROM advertisement_table $whereClause ORDER BY id DESC");
                $conn->close();
            ?>

            <table id="eventTable" class="table table-hover">
                <thead>
                    <tr class="table-primary fs-5">
                        <th class="pe-4"></th>
                        <th>#</th>
                        <th class="pe-5">Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Date Added</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($allAdvertisements->num_rows > 0) {
                        while($row = $allAdvertisements->fetch_assoc()) {
                            // Set badges for Active and Inactive
                            $status = $row['status'] == "Active" 
                                ? '<span class="badge badge-status-active">Active</span>'
                                : '<span class="badge badge-status-inactive">Inactive</span>';
                                
                            $category = '<span class="badge badge-category">'.htmlspecialchars($row['category']).'</span>';
                            
                            // Action dropdown
                            $actionDropdown = '
                                <div class="dropstart me-4">
                                    <div class="float-end" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots text-secondary"></i>
                                    </div>
                                    <ul class="dropdown-menu dropdown-menu-normal mt-1 px-2">
                                        <li><form action="edit_advertisement.php" method="GET">
                                            <input type="hidden" name="id" value="'.htmlspecialchars($row['id']).'">
                                            <button type="submit" class="dropdown-item py-2 pe-5"><i class="bi bi-pencil me-3" style="font-size: 1.25rem; -webkit-text-stroke: 0.25px;"></i><div class="fw-medium">Edit</div></button>
                                        </form></li>
                                        <li><form action="'.htmlspecialchars($_SERVER["PHP_SELF"]).'" method="POST">
                                            <input type="hidden" name="id" value="'.htmlspecialchars($row['id']).'">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="dropdown-item py-2 pe-5" onclick="return confirm(\'Are you sure you want to delete this advertisement?\')">
                                            <i class="bi bi-trash me-3 text-danger" style="font-size: 1.25rem; -webkit-text-stroke: 0.25px;"></i><div class="fw-medium text-danger">Delete</div>
                                        </button></form></li>
                                    </ul>
                                </div>
                            ';
                            
                            // Expandable row control
                            $expandCollapse = '<a class="dt-control text-secondary" style="cursor: pointer;"><i class="bi bi-chevron-down"></i></a>';
                            
                            echo '<tr>
                                <td>'.$expandCollapse.'</td>
                                <td>'.htmlspecialchars($row['id']).'</td>
                                <td>'.htmlspecialchars($row['title']).'</td>
                                <td>'.$category.'</td>
                                <td>'.$status.'</td>
                                <td>'.date('d/m/Y', strtotime($row['date_added'])).'</td>
                                <td>'.$actionDropdown.'</td>
                            </tr>';
                        }
                    } else {
                        echo '<tr><td colspan="7" class="text-center">No advertisements found</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
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
            // If custom button is set
            const buttonMsgLink = (row.button_message || row.button_link) 
                ? `<a role="button" class="btn btn-primary ms-4 fw-medium px-4" href="${row.button_link}" target="_blank">${row.button_message}<i class="bi bi-box-arrow-up-right ms-2" style="-webkit-text-stroke: 0.25px;"></i></a>
                    <span class="ms-2 fst-italic fw-light text-secondary">(<a class="link-underline link-underline-opacity-0 link-underline-opacity-75-hover" href="${row.button_link}" target="_blank">${row.button_link}</a>)</span><br/>`
                : "";

            const appliable = row.appliable == 1 
                ? '<span>*User application enabled</span><br/>' 
                : '';

            return `<div class="slider">
                <div class="row">
                    <div class="col-auto ms-3 my-3 me-0 pe-0">
                        <div class="image-table-container">
                            <img src="images/${row.photo}" class="img-fluid" alt="ad_photo">
                        </div>
                    </div>
                    <div class="col mx-0 px-0">
                        <p class="fw-light ps-4 pe-5 pt-2 pb-3">${row.description}</p>
                        ${buttonMsgLink}
                        <p class="fst-italic fw-light mt-3 text-end me-5">
                            ${appliable}
                            Added by: 
                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-75-hover" href="mailto:${row.advertiser}">${row.advertiser}</a>
                        </p>
                    </div>
                </div>
            </div>`;
        }

        // Initialize DataTable after page load
        $(document).ready(function() {
            const table = $('#eventTable').DataTable({
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
                        targets: [0, 6],
                        orderable: false,
                    },
                    {
                        targets: '_all',
                        createdCell: function (td, cellData, rowData, row, col) {
                            $(td).css('padding', '15px');
                        }
                    },
                ],
            });

            // Onclick listener for the description displayer
            $('#eventTable tbody').on('click', 'a.dt-control', function () {
                const tr = $(this).closest('tr');
                const row = table.row(tr);
            
                if (row.child.isShown()) {
                    // This row is already open - close it
                    $('div.slider', row.child()).slideUp(function () {
                        row.child.hide();
                        tr.removeClass('shown');
                    });
                    $(this).html('<i class="bi bi-chevron-down"></i>');
                } else {
                    // Open this row
                    const rowData = row.data();
                    row.child(format({
                        description: rowData[7],
                        photo: rowData[8],
                        button_message: rowData[9],
                        button_link: rowData[10],
                        advertiser: rowData[11],
                        appliable: rowData[12]
                    }), 'no-padding').show();
                    tr.addClass('shown');
                    $('div.slider', row.child()).slideDown();
                    $(this).html('<i class="bi bi-chevron-up"></i>');
                }
            });
        });

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
        
        // Ignore confirm form resubmission after POST request
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>