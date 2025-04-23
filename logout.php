<?php
session_start();
include 'flash_message.php'; // Make sure this is the correct path

// Set the flash message
set_flash_message("You have successfully logged out.", "success");

// Unset session data (but keep flash message until next load)
unset($_SESSION['logged_user']);
unset($_SESSION['logged_account']);
unset($_SESSION['form_data']);
unset($_SESSION['login_errors']);
unset($_SESSION['verified']);


header('Location: login.php');
exit();
?>
