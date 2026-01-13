<?php
require_once 'includes/utils.php';

/**
 * Comment Class
 * 
 * Manages comment-related operations including creation,
 * deletion, and retrieving comment data
 */
class Comment {
  private $conn;
  private $table_name = "comments";

  /**
   * Constructor - initializes database connection
   * 
   * @param PDO $db Database connection
   */
  public function __construct($db) {
    $this->conn = $db;
  }

  /**
   * Creates a new comment
   * 
   * @param int $user_id User ID
   * @param int $post_id Post ID
   * @param string $content Comment content
   * @param int|null $parent_comment_id Parent comment ID for replies (optional)
   * @return array Result array with success status, message, and comment ID if successful
   */
  public function create_comment($user_id, $post_id, $content, $parent_comment_id = null) {
    if (empty($user_id) || empty($post_id) || empty($content)) {
      return return_response(false, "All fields are required");
    }

    // Check if post exists and is not closed
    $query = "SELECT is_closed FROM posts WHERE post_id = :post_id AND is_deleted = FALSE";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":post_id", $post_id);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
      return return_response(false, "Post not found");
    }

    $post_data = $stmt->fetch();
    if ($post_data['is_closed']) {
      return return_response(false, "This post is closed. New comments are not allowed.");
    }

    // If parent comment ID provided, check if it exists
    if ($parent_comment_id) {
      $query = "SELECT comment_id FROM " . $this->table_name . " 
               WHERE comment_id = :comment_id AND post_id = :post_id AND is_deleted = FALSE";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(":comment_id", $parent_comment_id);
      $stmt->bindParam(":post_id", $post_id);
      $stmt->execute();

      if ($stmt->rowCount() == 0) {
        return return_response(false, "Parent comment not found");
      }
    }

    // Create the comment
    $query = "INSERT INTO " . $this->table_name . "
              (post_id, user_id, content, parent_comment_id)
              VALUES (:post_id, :user_id, :content, :parent_comment_id)";

    $stmt = $this->conn->prepare($query);
    $content = clean_input($content);

    $stmt->bindParam(":post_id", $post_id);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":content", $content);
    $stmt->bindParam(":parent_comment_id", $parent_comment_id);

    try {
      if ($stmt->execute()) {
        return array(
          "success" => true,
          "comment_id" => $this->conn->lastInsertId(),
          "message" => "Comment posted successfully"
        );
      }
    } catch (PDOException $e) {
      error_log("Error creating comment: " . $e->getMessage());
      return return_response(false, "Unable to post comment. Please try again later.");
    }

    return return_response(false, "Unable to post comment");
  }

  /**
   * Gets comments for a specific post
   * 
   * @param int $post_id Post ID
   * @param bool $include_replies Whether to include replies (default: true)
   * @return array Array of comments
   */
  public function get_comments_by_post($post_id, $include_replies = true) {
    $query = "SELECT c.*, u.username
              FROM " . $this->table_name . " c
              LEFT JOIN users u ON c.user_id = u.user_id
              WHERE c.post_id = :post_id AND c.is_deleted = FALSE";
              
    if (!$include_replies) {
      $query .= " AND c.parent_comment_id IS NULL";
    }
    
    $query .= " ORDER BY c.created_at ASC";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":post_id", $post_id);
    
    try {
      $stmt->execute();
      return $stmt->fetchAll();
    } catch (PDOException $e) {
      error_log("Error fetching comments: " . $e->getMessage());
      return array();
    }
  }

  /**
   * Gets a specific comment by ID
   * 
   * @param int $comment_id Comment ID
   * @return array|null Comment data or null if not found
   */
  public function get_comment_by_id($comment_id) {
    $query = "SELECT c.*, u.username, p.title as post_title, p.post_id, p.user_id as post_author_id
              FROM " . $this->table_name . " c
              LEFT JOIN users u ON c.user_id = u.user_id
              LEFT JOIN posts p ON c.post_id = p.post_id
              WHERE c.comment_id = :comment_id AND c.is_deleted = FALSE";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":comment_id", $comment_id);
    
    try {
      $stmt->execute();
      if ($stmt->rowCount() == 0) return null;
      return $stmt->fetch();
    } catch (PDOException $e) {
      error_log("Error fetching comment: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Deletes a comment (soft delete)
   * 
   * @param int $comment_id Comment ID
   * @param int $user_id User ID making the deletion (for permission check)
   * @return array Result array with success status and message
   */
  public function delete_comment($comment_id, $user_id) {
    $comment = $this->get_comment_by_id($comment_id);

    if (!$comment) {
      return return_response(false, "Comment not found");
    }

    // Users can always delete their own comments
    $is_comment_author = ($comment['user_id'] == $user_id);
    
    // Post authors can delete any comments on their posts
    $is_post_author = ($comment['post_author_id'] == $user_id);
    
    // Check for moderator/admin permissions
    $has_permission = $is_comment_author || $is_post_author;
    
    if (!$has_permission) {
      // Get user role
      $query = "SELECT role FROM users WHERE user_id = :user_id";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':user_id', $user_id);
      $stmt->execute();
      $user_role = $stmt->fetch();
      
      // Get comment author role
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':user_id', $comment['user_id']);
      $stmt->execute();
      $author_role = $stmt->fetch();
      
      // Define role hierarchy
      $role_hierarchy = [
        'student' => 1,
        'moderator' => 2,
        'admin' => 3
      ];
      
      // If author doesn't exist (anonymous comment), default to student level
      $author_level = isset($author_role['role']) ? $role_hierarchy[$author_role['role']] : 1;
      $user_level = isset($user_role['role']) ? $role_hierarchy[$user_role['role']] : 0;
      
      // Users can delete comments from users with lower role level
      $has_permission = $user_level > $author_level;
      
      // Additionally check for course-specific roles
      if (!$has_permission) {
        // Get post course ID
        $query = "SELECT course_id FROM posts WHERE post_id = :post_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':post_id', $comment['post_id']);
        $stmt->execute();
        $post_data = $stmt->fetch();
        
        if ($post_data) {
          // Check user's role in this course
          $query = "SELECT role FROM user_courses WHERE user_id = :user_id AND course_id = :course_id";
          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(':user_id', $user_id);
          $stmt->bindParam(':course_id', $post_data['course_id']);
          $stmt->execute();
          $course_role = $stmt->fetch();
          
          // Check comment author's role in this course
          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(':user_id', $comment['user_id']);
          $stmt->bindParam(':course_id', $post_data['course_id']);
          $stmt->execute();
          $author_course_role = $stmt->fetch();
          
          if ($course_role && ($course_role['role'] === 'moderator' || $course_role['role'] === 'admin')) {
            $user_course_level = $role_hierarchy[$course_role['role']];
            $author_course_level = $author_course_role ? $role_hierarchy[$author_course_role['role']] : 1;
            
            $has_permission = $user_course_level > $author_course_level;
          }
        }
      }
    }
    
    if (!$has_permission) {
      return return_response(false, "You don't have permission to delete this comment");
    }

    $query = "UPDATE " . $this->table_name . "
              SET is_deleted = TRUE
              WHERE comment_id = :comment_id";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":comment_id", $comment_id);

    try {
      if ($stmt->execute()) {
        return return_response(true, "Comment deleted successfully");
      }
    } catch (PDOException $e) {
      error_log("Error deleting comment: " . $e->getMessage());
      return return_response(false, "Unable to delete comment. Please try again later.");
    }

    return return_response(false, "Unable to delete comment");
  }

  /**
   * Gets comments by a specific user
   * 
   * @param int $user_id User ID
   * @param int $limit Maximum number of comments to return (optional)
   * @return array Array of comments
   */
  public function get_user_comments($user_id, $limit = null) {
    $query = "SELECT c.*, p.title as post_title, p.post_id
              FROM " . $this->table_name . " c
              JOIN posts p ON c.post_id = p.post_id
              WHERE c.user_id = :user_id AND c.is_deleted = FALSE AND p.is_deleted = FALSE
              ORDER BY c.created_at DESC";
    
    if ($limit !== null) {
      $query .= " LIMIT :limit";
    }
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    
    if ($limit !== null) {
      $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
    }
    
    try {
      $stmt->execute();
      return $stmt->fetchAll();
    } catch (PDOException $e) {
      error_log("Error fetching user comments: " . $e->getMessage());
      return array();
    }
  }
}