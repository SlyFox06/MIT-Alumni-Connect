<!-- Ensures account type 'user' is logged in to access any page that includes this -->

<?php
    $loggedUser = isset($_SESSION['logged_user']) ? $_SESSION['logged_user'] : '';

    // Re-fetch data to get most updated data and not just rely on the session
    include 'db_controller.php';
    $conn->select_db("atharv");
    
    // Get and save account info from account_table into session
    $SQLGetAccountInfo = $conn->prepare("SELECT email, type, status FROM account_table WHERE email = ?");
    $SQLGetAccountInfo->bind_param("s", $_SESSION['logged_account']['email']);
    $SQLGetAccountInfo->execute();
    $accountInfo = $SQLGetAccountInfo->get_result()->fetch_assoc();
    $_SESSION['logged_account'] = $accountInfo;

    // Get and save user info from user_table into session
    $SQLGetUserInfo = $conn->prepare("SELECT * FROM user_table WHERE email = ?");
    $SQLGetUserInfo->bind_param("s", $_SESSION['logged_account']['email']);
    $SQLGetUserInfo->execute();
    $userInfo = $SQLGetUserInfo->get_result()->fetch_assoc();
    $_SESSION['logged_user'] = $userInfo;

    // If the logged account is NOT user. Or not logged in at all
    if(!isset($_SESSION['logged_account']) || (isset($_SESSION['logged_account']) && $_SESSION['logged_account']['type'] != 'user')) {
        // Logs the user out to prevent infinite redirect from login page
        unset($_SESSION['logged_user']);
        unset($_SESSION['logged_account']);
        unset($_SESSION['form_data']);
        unset($_SESSION['login_errors']);
        unset($_SESSION['verified']);
        
        $_SESSION['flash_mode'] = "alert-primary";
        $_SESSION['flash'] = "You must log in as user to continue.";
        // $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
    
    // If the logged user is NOT Approved
    } 
?>