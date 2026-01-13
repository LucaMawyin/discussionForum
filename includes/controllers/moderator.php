<?php
/**
 * Moderator Controller
 * 
 * Handles moderator-related operations for posts and comments
 */
require_once '../config/database.php';
require_once '../utils.php';
require_once '../classes/Post.php';
require_once '../classes/User.php';

// Start session and check if user is logged in
ensure_session_started();
if (!is_logged_in()) {
    redirect("../../login.php");
    exit();
}

// Redirect if not moderator or admin
if (!is_moderator_or_above()) {
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
        $_SESSION['moderator_error'] = "Invalid form submission. Please try again.";
        
        // Determine where to redirect based on parameters
        if (isset($_GET['post_id'])) {
            redirect("../../post.php?id=" . $_GET['post_id']);
        } else {
            redirect("../../moderator.php");
        }
        exit();
    }
    
    // Pin/unpin post
    if (isset($_POST['pin_post']) || isset($_POST['unpin_post'])) {
        $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        
        if ($post_id <= 0) {
            $_SESSION['moderator_error'] = "Invalid post ID.";
            redirect("../../moderator.php");
            exit();
        }
        
        $post = new Post($conn);
        $result = $post->toggle_pin($post_id, $_SESSION['user_id']);
        
        if ($result['success']) {
            $_SESSION['moderator_success'] = $result['message'];
        } else {
            $_SESSION['moderator_error'] = $result['message'];
        }
        
        // Redirect based on context
        if (isset($_GET['post_id'])) {
            redirect("../../post.php?id=$post_id&action=pin");
        } else {
            redirect("../../moderator.php?tab=posts&success=post_updated");
        }
        exit();
    }
    
    // Close/reopen post
    if (isset($_POST['close_post']) || isset($_POST['reopen_post'])) {
        $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        
        if ($post_id <= 0) {
            $_SESSION['moderator_error'] = "Invalid post ID.";
            redirect("../../moderator.php");
            exit();
        }
        
        $post = new Post($conn);
        $result = $post->toggle_close($post_id, $_SESSION['user_id']);
        
        if ($result['success']) {
            $_SESSION['moderator_success'] = $result['message'];
        } else {
            $_SESSION['moderator_error'] = $result['message'];
        }
        
        // Redirect based on context
        if (isset($_GET['post_id'])) {
            redirect("../../post.php?id=$post_id&action=close");
        } else {
            redirect("../../moderator.php?tab=posts&success=post_updated");
        }
        exit();
    }
    
    // Delete post
    if (isset($_POST['delete_post'])) {
        $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        
        if ($post_id <= 0) {
            $_SESSION['moderator_error'] = "Invalid post ID.";
            redirect("../../moderator.php");
            exit();
        }
        
        // Get course ID before deleting post
        $post_obj = new Post($conn);
        $post_data = $post_obj->get_post_by_id($post_id, false);
        $course_id = $post_data ? $post_data['course_id'] : 0;
        
        // Get the post author's role
        if ($post_data && isset($post_data['user_id'])) {
            $author_id = $post_data['user_id'];
            $user_obj = new User($conn, $author_id);
            $author_data = $user_obj->get_data();
            
            // Get moderator's role
            $mod_role = $_SESSION['role'];
            
            // Define role hierarchy
            $role_hierarchy = [
                'student' => 1,
                'moderator' => 2,
                'admin' => 3
            ];
            
            $mod_level = $role_hierarchy[$mod_role];
            $author_level = $role_hierarchy[$author_data['role']];
            
            // Moderator cannot delete posts from users with equal or higher role level
            if ($mod_level <= $author_level && $_SESSION['user_id'] != $author_id) {
                $_SESSION['moderator_error'] = "You cannot delete posts from users with equal or higher role level.";
                
                if (isset($_GET['course_id'])) {
                    redirect("../../community.php?id=$course_id");
                } else {
                    redirect("../../moderator.php?tab=posts");
                }
                exit();
            }
        }
        
        // Delete post
        $result = $post_obj->delete_post($post_id, $_SESSION['user_id']);
        
        if ($result['success']) {
            $_SESSION['moderator_success'] = "Post deleted successfully.";
        } else {
            $_SESSION['moderator_error'] = $result['message'];
        }
        
        // Redirect based on context
        if (isset($_GET['course_id'])) {
            redirect("../../community.php?id=$course_id&action=delete");
        } else {
            redirect("../../moderator.php?tab=posts&success=post_deleted");
        }
        exit();
    }
}

// If we get here, redirect to moderator panel
redirect("../../moderator.php");