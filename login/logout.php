<?php
require_once '../includes/config.php';

// Destroy all session data
session_unset();
session_destroy();

// Redirect to home page with logout message
redirect(SITE_URL . '/?logged_out=1');
?>