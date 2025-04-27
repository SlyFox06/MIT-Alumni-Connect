<?php
// Start output buffering at the very beginning
ob_start();

// Check if email parameter exists
if (!isset($_GET["email"])) {
    // Clear any existing output
    ob_end_clean();
    header('Location: view_alumni.php');
    exit();
}

// Database connection and query
include 'db_controller.php';
$conn->select_db("atharv");

$SQLGetAlumnusInfo = $conn->prepare("SELECT * FROM user_table WHERE email = ?");
$SQLGetAlumnusInfo->bind_param("s", $_GET['email']);
$SQLGetAlumnusInfo->execute();
$result = $SQLGetAlumnusInfo->get_result();

if ($result->num_rows > 0) {
    $accountInfo = $result->fetch_assoc();

    // Set all the variables
    $alumnusToView = $accountInfo;
    $alumnusToViewFirstName = $accountInfo['first_name'];
    $alumnusToViewLastName = $accountInfo['last_name'];
    $alumnusToViewName = $accountInfo['first_name']." ".$accountInfo['last_name'];
    $alumnusToViewDOB = $accountInfo['dob'];
    $alumnusToViewEmail = $accountInfo['email'];
    $alumnusToViewGender = $accountInfo['gender'];
    $alumnusToViewContactNo = $accountInfo['contact_number'];
    $alumnusToViewHometown = $accountInfo['hometown'];
    $alumnusToViewJobPosition = $accountInfo['job_position'];
    $alumnusToViewCompany = $accountInfo['company'];
    $alumnusToViewCurrentLocation = $accountInfo['current_location'];
    $alumnusToViewDegree = $accountInfo['qualification'];
    $alumnusToViewDegreeYearGraduated = $accountInfo['year'];
    $alumnusToViewCampus = $accountInfo['university'];
    $alumnusToViewProfilePicture = $accountInfo['profile_image'];
    $alumnusToViewResume = $accountInfo['resume'];
}

// Clean the output buffer if we're not redirecting
ob_end_clean();
?>