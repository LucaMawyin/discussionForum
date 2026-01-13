<?php
/**
 * Comment Controller
 * 
 * Handles comment-related operations from form submissions
 */
require_once '../config/database.php';
require_once '../utils.php';
require_once '../classes/Comment.php';
require_once '../classes/User.php';
require_once '../classes/Post.php';

// Start session and check if user is logged in
ensure_session_started();
if (!is_logged_in()) {
    redirect("../../login.php");
    exit();
}

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

/**
 * Checks if a user can edit/delete content based on role hierarchy
 * 
 * @param int $editor_id User ID of the editor
 * @param int $author_id User ID of the content author
 * @param PDO $conn Database connection
 * @return bool True if user has permission, false otherwise
 */
function check_hierarchical_permission($editor_id, $author_id, $conn) {
    // If user is editing their own content, always allow
    if ($editor_id == $author_id) {
        return true;
    }
    
    // Get editor's role
    $query = "SELECT role FROM users WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $editor_id);
    $stmt->execute();
    $editor_role = $stmt->fetch();
    
    if (!$editor_role) {
        return false;
    }
    
    // Get author's role
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $author_id);
    $stmt->execute();
    $author_role = $stmt->fetch();
    
    if (!$author_role) {
        // If author doesn't exist, we'll allow admins to edit
        return $editor_role['role'] === 'admin';
    }
    
    // Define role hierarchy (higher index = higher permission)
    $role_hierarchy = [
        'student' => 1,
        'moderator' => 2,
        'admin' => 3
    ];
    
    $editor_level = $role_hierarchy[$editor_role['role']];
    $author_level = $role_hierarchy[$author_role['role']];
    
    // Editor can only edit if their role level is higher than the author's
    return $editor_level > $author_level;
}

/**
 * Checks if a user has course-specific permission to edit content
 * 
 * @param int $editor_id User ID of the editor
 * @param int $author_id User ID of the content author
 * @param int $course_id Course ID
 * @param PDO $conn Database connection
 * @return bool True if user has permission, false otherwise
 */
function check_course_permission($editor_id, $author_id, $course_id, $conn) {
    // Get editor's course role
    $query = "SELECT role FROM user_courses WHERE user_id = :user_id AND course_id = :course_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $editor_id);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();
    $editor_course_role = $stmt->fetch();
    
    // If editor has no specific role in this course, they have no special permission
    if (!$editor_course_role || !in_array($editor_course_role['role'], ['moderator', 'admin'])) {
        return false;
    }
    
    // Get author's course role
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $author_id);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();
    $author_course_role = $stmt->fetch();
    
    // Define role hierarchy
    $role_hierarchy = [
        'student' => 1,
        'moderator' => 2,
        'admin' => 3
    ];
    
    $editor_level = $role_hierarchy[$editor_course_role['role']];
    // If author has no course role, treat as student
    $author_level = $author_course_role ? $role_hierarchy[$author_course_role['role']] : 1;
    
    // Editor can edit if their course role level is higher than the author's
    return $editor_level > $author_level;
}

// Create a new comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['comment_error'] = "Invalid form submission. Please try again.";
        redirect("../../post.php?id=" . $_POST['post_id'] . "#comments");
        exit();
    }
    
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    $content = isset($_POST['comment_content']) ? clean_input($_POST['comment_content']) : '';
    
    if ($post_id <= 0 || empty($content)) {
        $_SESSION['comment_error'] = "Invalid comment data.";
        redirect("../../post.php?id=$post_id#comments");
        exit();
    }
    
    // Check if post is closed
    $post_obj = new Post($conn);
    $post_data = $post_obj->get_post_by_id($post_id, false);
    
    if (!$post_data) {
        $_SESSION['comment_error'] = "Post not found.";
        redirect("../../index.php");
        exit();
    }
    
    if ($post_data['is_closed']) {
        $_SESSION['comment_error'] = "This post is closed. New comments are not allowed.";
        redirect("../../post.php?id=$post_id#comments");
        exit();
    }
    
    // Create comment
    $comment_obj = new Comment($conn);
    $result = $comment_obj->create_comment($_SESSION['user_id'], $post_id, $content);
    
    if ($result['success']) {
        redirect("../../post.php?id=$post_id#comment-" . $result['comment_id']);
    } else {
        $_SESSION['comment_error'] = $result['message'];
        redirect("../../post.php?id=$post_id#comments");
    }
    exit();
}

// Handle comment deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['comment_error'] = "Invalid form submission. Please try again.";
        redirect("../../post.php?id=" . $_POST['post_id'] . "#comments");
        exit();
    }
    
    // Get comment and post IDs
    $comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    
    if ($comment_id <= 0 || $post_id <= 0) {
        $_SESSION['comment_error'] = "Invalid comment or post ID.";
        redirect("../../post.php?id=$post_id#comments");
        exit();
    }
    
    // Get comment details
    $comment_obj = new Comment($conn);
    $comment_data = $comment_obj->get_comment_by_id($comment_id);
    
    if (!$comment_data) {
        $_SESSION['comment_error'] = "Comment not found.";
        redirect("../../post.php?id=$post_id#comments");
        exit();
    }
    
    // Get post details to check if current user is post author
    $post_obj = new Post($conn);
    $post_data = $post_obj->get_post_by_id($post_id, false);
    $is_post_author = $post_data && $_SESSION['user_id'] == $post_data['user_id'];
    
    // Check permissions
    $can_delete = false;
    
    // Users can always delete their own comments
    if ($_SESSION['user_id'] == $comment_data['user_id']) {
        $can_delete = true;
    }
    // Post authors can delete any comments on their posts
    else if ($is_post_author) {
        $can_delete = true;
    }
    // Check hierarchical permissions for site-wide roles
    else {
        $has_hierarchical_permission = check_hierarchical_permission($_SESSION['user_id'], $comment_data['user_id'], $conn);
        
        if ($has_hierarchical_permission) {
            $can_delete = true;
        } else if ($post_data && isset($post_data['course_id'])) {
            // Check course-specific permissions
            $course_id = $post_data['course_id'];
            $has_course_permission = check_course_permission($_SESSION['user_id'], $comment_data['user_id'], $course_id, $conn);
            
            if ($has_course_permission) {
                $can_delete = true;
            }
        }
    }
    
    if (!$can_delete) {
        $_SESSION['comment_error'] = "You don't have permission to delete this comment based on role hierarchy.";
        redirect("../../post.php?id=$post_id#comments");
        exit();
    }
    
    // Delete comment
    $result = $comment_obj->delete_comment($comment_id, $_SESSION['user_id']);
    
    if ($result['success']) {
        $_SESSION['comment_success'] = "Comment deleted successfully.";
    } else {
        $_SESSION['comment_error'] = $result['message'];
    }
    
    // Redirect back to post
    redirect("../../post.php?id=$post_id#comments");
    exit();
}

// If we get here, the request was invalid
$_SESSION['error'] = "Invalid request.";
redirect("../../index.php");
exit();
?>