<?php
/**
 * Admin Controller
 * 
 * Handles administrative operations for users and courses
 */
require_once '../config/database.php';
require_once '../utils.php';
require_once '../classes/User.php';
require_once '../classes/Course.php';

// Start session and check if user is logged in
ensure_session_started();
if (!is_logged_in()) {
    redirect("../../login.php");
    exit();
}

// Redirect if not admin
if (!is_admin()) {
    redirect("../../index.php");
    exit();
}

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['admin_error'] = "Invalid form submission. Please try again.";
        redirect("../../admin.php");
        exit();
    }
    
    // Create course
    if (isset($_POST['create_course'])) {
        $course_code = isset($_POST['course_code']) ? clean_input($_POST['course_code']) : '';
        $course_name = isset($_POST['course_name']) ? clean_input($_POST['course_name']) : '';
        $description = isset($_POST['description']) ? clean_input($_POST['description']) : '';
        
        if (empty($course_code) || empty($course_name)) {
            $_SESSION['admin_error'] = "Course code and name are required.";
            redirect("../../admin.php?tab=courses");
            exit();
        }
        
        $course = new Course($conn);
        $result = $course->create_course($course_code, $course_name, $description, $_SESSION['user_id']);
        
        if ($result['success']) {
            $_SESSION['admin_success'] = "Course created successfully!";
        } else {
            $_SESSION['admin_error'] = $result['message'];
        }
        
        redirect("../../admin.php?tab=courses");
        exit();
    }
    
    // Delete course
    if (isset($_POST['delete_course'])) {
        $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
        
        if ($course_id <= 0) {
            $_SESSION['admin_error'] = "Invalid course ID.";
            redirect("../../admin.php?tab=courses");
            exit();
        }
        
        $course = new Course($conn, $course_id);
        $result = $course->delete_course();
        
        if ($result['success']) {
            $_SESSION['admin_success'] = "Course deleted successfully!";
        } else {
            $_SESSION['admin_error'] = $result['message'];
        }
        
        redirect("../../admin.php?tab=courses");
        exit();
    }
    
    // Update user role
    if (isset($_POST['update_role'])) {
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $role = isset($_POST['role']) ? clean_input($_POST['role']) : '';
        
        if ($user_id <= 0 || !in_array($role, ['student', 'moderator', 'admin'])) {
            $_SESSION['admin_error'] = "Invalid user ID or role.";
            redirect("../../admin.php?tab=users");
            exit();
        }
        
        // Check admin's own role
        $admin_role = $_SESSION['role'];
        
        // Define role hierarchy
        $role_hierarchy = [
            'student' => 1,
            'moderator' => 2,
            'admin' => 3
        ];
        
        $admin_level = $role_hierarchy[$admin_role];
        $target_level = $role_hierarchy[$role];
        
        // Admin cannot promote to a level equal to or higher than their own
        if ($admin_level <= $target_level) {
            $_SESSION['admin_error'] = "You cannot promote a user to your role level or higher.";
            redirect("../../admin.php?tab=users");
            exit();
        }
        
        // Check if the target user's current role
        $target_user = new User($conn, $user_id);
        $target_user_data = $target_user->get_data();
        $current_role_level = $role_hierarchy[$target_user_data['role']];
        
        // Admin cannot modify a user with equal or higher role level
        if ($admin_level <= $current_role_level) {
            $_SESSION['admin_error'] = "You cannot modify a user with equal or higher role level.";
            redirect("../../admin.php?tab=users");
            exit();
        }
        
        $result = $target_user->update_role($role);
        
        if ($result['success']) {
            $_SESSION['admin_success'] = "User role updated successfully!";
        } else {
            $_SESSION['admin_error'] = $result['message'];
        }
        
        redirect("../../admin.php?tab=users");
        exit();
    }
    
    // Delete user
    if (isset($_POST['delete_user'])) {
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        
        if ($user_id <= 0) {
            $_SESSION['admin_error'] = "Invalid user ID.";
            redirect("../../admin.php?tab=users");
            exit();
        }
        
        if ($user_id === $_SESSION['user_id']) {
            $_SESSION['admin_error'] = "You cannot delete your own account.";
            redirect("../../admin.php?tab=users");
            exit();
        }
        
        // Check admin's own role
        $admin_role = $_SESSION['role'];
        
        // Check the target user's role
        $target_user = new User($conn, $user_id);
        $target_user_data = $target_user->get_data();
        
        // Define role hierarchy
        $role_hierarchy = [
            'student' => 1,
            'moderator' => 2,
            'admin' => 3
        ];
        
        $admin_level = $role_hierarchy[$admin_role];
        $target_level = $role_hierarchy[$target_user_data['role']];
        
        // Admin cannot delete a user with equal or higher role level
        if ($admin_level <= $target_level) {
            $_SESSION['admin_error'] = "You cannot delete a user with equal or higher role level.";
            redirect("../../admin.php?tab=users");
            exit();
        }
        
        $result = $target_user->delete_user();
        
        if ($result['success']) {
            $_SESSION['admin_success'] = "User deleted successfully!";
        } else {
            $_SESSION['admin_error'] = $result['message'];
        }
        
        redirect("../../admin.php?tab=users");
        exit();
    }
}

// If we get here, redirect to admin panel
redirect("../../admin.php");