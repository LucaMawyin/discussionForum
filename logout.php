<?php
/**
 * Logout Page
 * 
 * Handles user logout process
 */
require_once 'includes/utils.php';
require_once 'includes/config/database.php';
require_once 'includes/classes/User.php';

ensure_session_started();

// Only process logout if user is logged in
if (is_logged_in()) {
  $database = new Database();
  $conn = $database->getConnection();
  $user = new User($conn);
  
  // Call logout method to clear session and cookies
  $user->logout();
}

// Always redirect to login page
redirect("login.php");