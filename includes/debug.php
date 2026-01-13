<?php
// Debug helper file
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Function to log debug information
function debug_log($message) {
    error_log("[DEBUG] " . $message);
}
?>