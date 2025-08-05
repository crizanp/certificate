<?php
require_once 'includes/session.php';

// Destroy the session
session_start();
session_unset();
session_destroy();

// Redirect to login page with success message
header('Location: login.php');
exit();
?>