<?php
session_start();

function set_flash_message($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function display_flash_message() {
    if (!empty($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] === 'error' ? 'danger' : 'success';
        echo "<div class='alert alert-$type' role='alert'>{$_SESSION['flash_message']}</div>";
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    }
}
?>
