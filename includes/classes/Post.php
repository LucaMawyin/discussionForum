<?php
require_once 'includes/utils.php';

/**
 * Post Class
 * 
 * Manages post-related operations including creation, updates,
 * deletion, and retrieving post data
 */
class Post {
  private $conn;
  private $table_name = "posts";

  public $post_id;
  public $user_id;
  public $course_id;
  public $title;
  public $content;
  public $created_at;
  public $updated_at;
  public $is_pinned;
  public $is_closed;
  public $is_deleted;
  public $view_count;

  /**
   * Constructor - initializes database connection
   * 
   * @param PDO $db Database connection
   */
  public function __construct($db) {
    $this->conn = $db;
  }

  /**
   * Creates a new post
   * 
   * @param int $user_id User ID
   * @param int $course_id Course ID
   * @param string $title Post title
   * @param string $content Post content
   * @return array Result array with success status, message, and post ID if successful
   */
  public function create_post($user_id, $course_id, $title, $content) {
    if (empty($user_id) || empty($course_id) || empty($title) || empty($content)) {
      return return_response(false, "All fields are required");
    }

    $query = "INSERT INTO " . $this->table_name . "
              (user_id, course_id, title, content)
              VALUES (:user_id, :course_id, :title, :content)";
    
    $stmt = $this->conn->prepare($query);
    $title = clean_input($title);
    $content = clean_input($content);

    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":course_id", $course_id);
    $stmt->bindParam(":title", $title);
    $stmt->bindParam(":content", $content);

    try {
      if ($stmt->execute()) {
        return array(
          "success" => true,
          "post_id" => $this->conn->lastInsertId(),
          "message" => "Post created successfully"
        );
      }
    } catch (PDOException $e) {
      error_log("Error creating post: " . $e->getMessage());
      return return_response(false, "Unable to create post. Please try again later.");
    }

    return return_response(false, "Unable to create post");
  }

  /**
   * Gets post data by ID
   * 
   * @param int $post_id Post ID
   * @param bool $increment_view Whether to increment view count (default: true)
   * @return array|null Post data array or null if not found
   */
  public function get_post_by_id($post_id, $increment_view = true) {
    $query = "SELECT p.*,
                u.username,
                c.course_code,
                c.course_name,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id AND is_deleted = FALSE) as comment_count
              FROM " . $this->table_name . " p
              JOIN users u ON p.user_id = u.user_id
              JOIN courses c ON p.course_id = c.course_id
              WHERE p.post_id = :post_id AND p.is_deleted = FALSE";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":post_id", $post_id);
    
    try {
      $stmt->execute();

      if ($stmt->rowCount() == 0) return null;

      $post = $stmt->fetch();
      if ($increment_view) $this->increment_view_count($post_id);

      return $post;
    } catch (PDOException $e) {
      error_log("Error fetching post: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Checks if a user can edit/delete a post based on role hierarchy
   * 
   * @param int $editor_id User ID of the editor
   * @param int $author_id User ID of the content author
   * @return bool True if user has permission, false otherwise
   */
  private function check_hierarchical_permission($editor_id, $author_id) {
    // If user is editing their own content, always allow
    if ($editor_id == $author_id) {
      return true;
    }
    
    // Get editor's role
    $query = "SELECT role FROM users WHERE user_id = :user_id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':user_id', $editor_id);
    $stmt->execute();
    $editor_role = $stmt->fetch();
    
    if (!$editor_role) {
      return false;
    }
    
    // Get author's role
    $stmt = $this->conn->prepare($query);
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
   * Updates an existing post
   * 
   * @param int $post_id Post ID
   * @param string $title Post title
   * @param string $content Post content
   * @param int $user_id User ID making the update (for permission check)
   * @return array Result array with success status and message
   */
  public function update_post($post_id, $title, $content, $user_id) {
    $post = $this->get_post_by_id($post_id, false);

    if (!$post) return return_response(false, "Post not found");

    // Check if user is the author (can always edit their own content)
    $is_author = ($post['user_id'] == $user_id);
    
    // If not the author, check for permission based on role hierarchy
    if (!$is_author) {
      $has_hierarchical_permission = $this->check_hierarchical_permission($user_id, $post['user_id']);
      
      // Check if user has course-specific role that allows editing
      $has_course_permission = false;
      $query = "SELECT role FROM user_courses WHERE user_id = :user_id AND course_id = :course_id";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(":user_id", $user_id);
      $stmt->bindParam(":course_id", $post['course_id']);
      $stmt->execute();
      $course_role = $stmt->fetch();
      
      // For course-specific roles, also check hierarchical permission
      if ($course_role && ($course_role['role'] === 'moderator' || $course_role['role'] === 'admin')) {
        // Get course-specific role of the author, if any
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $post['user_id']);
        $stmt->bindParam(":course_id", $post['course_id']);
        $stmt->execute();
        $author_course_role = $stmt->fetch();
        
        $role_hierarchy = [
          'student' => 1,
          'moderator' => 2,
          'admin' => 3
        ];
        
        $editor_level = $role_hierarchy[$course_role['role']];
        // If author has no course role, treat as student
        $author_level = $author_course_role ? $role_hierarchy[$author_course_role['role']] : 1;
        
        $has_course_permission = $editor_level > $author_level;
      }
      
      // Only allow edit if user has site-wide or course-specific permission based on hierarchy
      if (!$has_hierarchical_permission && !$has_course_permission) {
        return return_response(false, "You don't have permission to edit this post based on role hierarchy.");
      }
    }

    $query = "UPDATE " . $this->table_name . "
              SET title = :title,
                  content = :content,
                  updated_at = CURRENT_TIMESTAMP
              WHERE post_id = :post_id";

    $stmt = $this->conn->prepare($query);
    $title = clean_input($title);
    $content = clean_input($content);

    $stmt->bindParam(":post_id", $post_id);
    $stmt->bindParam(":title", $title);
    $stmt->bindParam(":content", $content);

    try {
      if ($stmt->execute()) {
        return return_response(true, "Post updated successfully");
      }
    } catch (PDOException $e) {
      error_log("Error updating post: " . $e->getMessage());
      return return_response(false, "Unable to update post. Please try again later.");
    }

    return return_response(false, "Unable to update post");
  }

  /**
   * Deletes a post (soft delete)
   * 
   * @param int $post_id Post ID
   * @param int $user_id User ID making the deletion (for permission check)
   * @return array Result array with success status and message
   */
  public function delete_post($post_id, $user_id) {
    $post = $this->get_post_by_id($post_id, false);

    if (!$post) return return_response(false, "Post not found");

    // Check if user is the author (can always delete their own content)
    $is_author = ($post['user_id'] == $user_id);
    
    // If not the author, check for permission based on role hierarchy
    if (!$is_author) {
      $has_hierarchical_permission = $this->check_hierarchical_permission($user_id, $post['user_id']);
      
      // Check if user has course-specific role that allows deletion
      $has_course_permission = false;
      $query = "SELECT role FROM user_courses WHERE user_id = :user_id AND course_id = :course_id";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(":user_id", $user_id);
      $stmt->bindParam(":course_id", $post['course_id']);
      $stmt->execute();
      $course_role = $stmt->fetch();
      
      // For course-specific roles, also check hierarchical permission
      if ($course_role && ($course_role['role'] === 'moderator' || $course_role['role'] === 'admin')) {
        // Get course-specific role of the author, if any
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $post['user_id']);
        $stmt->bindParam(":course_id", $post['course_id']);
        $stmt->execute();
        $author_course_role = $stmt->fetch();
        
        $role_hierarchy = [
          'student' => 1,
          'moderator' => 2,
          'admin' => 3
        ];
        
        $editor_level = $role_hierarchy[$course_role['role']];
        // If author has no course role, treat as student
        $author_level = $author_course_role ? $role_hierarchy[$author_course_role['role']] : 1;
        
        $has_course_permission = $editor_level > $author_level;
      }
      
      // Only allow deletion if user has site-wide or course-specific permission based on hierarchy
      if (!$has_hierarchical_permission && !$has_course_permission) {
        return return_response(false, "You don't have permission to delete this post based on role hierarchy.");
      }
    }

    $query = "UPDATE " . $this->table_name . "
              SET is_deleted = TRUE
              WHERE post_id = :post_id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":post_id", $post_id);

    try {
      if ($stmt->execute()) {
        return return_response(true, "Post deleted successfully");
      }
    } catch (PDOException $e) {
      error_log("Error deleting post: " . $e->getMessage());
      return return_response(false, "Unable to delete post. Please try again later.");
    }

    return return_response(false, "Unable to delete post");
  }

  /**
   * Toggles the pinned status of a post
   * 
   * @param int $post_id Post ID
   * @param int $user_id User ID making the change (for permission check)
   * @return array Result array with success status and message
   */
  public function toggle_pin($post_id, $user_id) {
    $post = $this->get_post_by_id($post_id, false);

    if (!$post) return return_response(false, "Post not found");

    // Check if user has permission based on role hierarchy
    $has_hierarchical_permission = $this->check_hierarchical_permission($user_id, $post['user_id']);
    
    // Check if user has course-specific role that allows pinning
    $has_course_permission = false;
    $query = "SELECT role FROM user_courses WHERE user_id = :user_id AND course_id = :course_id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":course_id", $post['course_id']);
    $stmt->execute();
    $course_role = $stmt->fetch();
    
    // For course-specific roles, also check hierarchical permission
    if ($course_role && ($course_role['role'] === 'moderator' || $course_role['role'] === 'admin')) {
      // Get course-specific role of the author, if any
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(":user_id", $post['user_id']);
      $stmt->bindParam(":course_id", $post['course_id']);
      $stmt->execute();
      $author_course_role = $stmt->fetch();
      
      $role_hierarchy = [
        'student' => 1,
        'moderator' => 2,
        'admin' => 3
      ];
      
      $editor_level = $role_hierarchy[$course_role['role']];
      // If author has no course role, treat as student
      $author_level = $author_course_role ? $role_hierarchy[$author_course_role['role']] : 1;
      
      $has_course_permission = $editor_level > $author_level;
    }
    
    // Only allow pinning if user has site-wide or course-specific permission based on hierarchy
    if (!$has_hierarchical_permission && !$has_course_permission) {
      return return_response(false, "You don't have permission to pin this post based on role hierarchy.");
    }

    $new_status = $post['is_pinned'] ? 0 : 1;
    $action = $new_status ? "pinned" : "unpinned";

    $query = "UPDATE " . $this->table_name . "
              SET is_pinned = :new_status
              WHERE post_id = :post_id";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":new_status", $new_status);
    $stmt->bindParam(":post_id", $post_id);

    try {
      if ($stmt->execute()) {
        return return_response(true, "Post $action successfully");
      }
    } catch (PDOException $e) {
      error_log("Error toggling pin status: " . $e->getMessage());
      return return_response(false, "Unable to update post status. Please try again later.");
    }

    return return_response(false, "Unable to update post status");
  }

  /**
   * Toggles the closed status of a post
   * 
   * @param int $post_id Post ID
   * @param int $user_id User ID making the change (for permission check)
   * @return array Result array with success status and message
   */
  public function toggle_close($post_id, $user_id) {
    $post = $this->get_post_by_id($post_id, false);

    if (!$post) return return_response(false, "Post not found");

    // Check if user has permission based on role hierarchy
    $has_hierarchical_permission = $this->check_hierarchical_permission($user_id, $post['user_id']);
    
    // Check if user has course-specific role that allows closing
    $has_course_permission = false;
    $query = "SELECT role FROM user_courses WHERE user_id = :user_id AND course_id = :course_id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":course_id", $post['course_id']);
    $stmt->execute();
    $course_role = $stmt->fetch();
    
    // For course-specific roles, also check hierarchical permission
    if ($course_role && ($course_role['role'] === 'moderator' || $course_role['role'] === 'admin')) {
      // Get course-specific role of the author, if any
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(":user_id", $post['user_id']);
      $stmt->bindParam(":course_id", $post['course_id']);
      $stmt->execute();
      $author_course_role = $stmt->fetch();
      
      $role_hierarchy = [
        'student' => 1,
        'moderator' => 2,
        'admin' => 3
      ];
      
      $editor_level = $role_hierarchy[$course_role['role']];
      // If author has no course role, treat as student
      $author_level = $author_course_role ? $role_hierarchy[$author_course_role['role']] : 1;
      
      $has_course_permission = $editor_level > $author_level;
    }
    
    // Only allow closing if user has site-wide or course-specific permission based on hierarchy
    if (!$has_hierarchical_permission && !$has_course_permission) {
      return return_response(false, "You don't have permission to close this post based on role hierarchy.");
    }

    $new_status = $post['is_closed'] ? 0 : 1;
    $action = $new_status ? "closed" : "reopened";

    $query = "UPDATE " . $this->table_name . "
              SET is_closed = :new_status
              WHERE post_id = :post_id";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":new_status", $new_status);
    $stmt->bindParam(":post_id", $post_id);

    try {
      if ($stmt->execute()) {
        return return_response(true, "Post $action successfully");
      }
    } catch (PDOException $e) {
      error_log("Error toggling close status: " . $e->getMessage());
      return return_response(false, "Unable to update post status. Please try again later.");
    }

    return return_response(false, "Unable to update post status");
  }

  /**
   * Increments the view count for a post
   * 
   * @param int $post_id Post ID
   */
  private function increment_view_count($post_id) {
    $query = "UPDATE " . $this->table_name . "
              SET view_count = view_count + 1
              WHERE post_id = :post_id";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":post_id", $post_id);
    
    try {
      $stmt->execute();
    } catch (PDOException $e) {
      error_log("Error incrementing view count: " . $e->getMessage());
    }
  }

  /**
   * Gets posts for a specific course with pagination and sorting
   * 
   * @param int $course_id Course ID
   * @param string $sort_by Sort method ('recent', 'popular', 'unanswered')
   * @param int $page Page number
   * @param int $limit Items per page
   * @return array Array with posts data and pagination info
   */
  public function get_posts_by_course($course_id, $sort_by = 'recent', $page = 1, $limit = 10) {
    $offset = ($page - 1) * $limit;
    
    // Ensure posts table exists
    try {
      $table_check = $this->conn->query("SHOW TABLES LIKE '{$this->table_name}'");
      
      if ($table_check->rowCount() == 0) {
        return array(
          "posts" => array(),
          "total" => 0,
          "page" => $page,
          "limit" => $limit,
          "total_pages" => 0
        );
      }
      
      // Base query
      $query = "SELECT p.*,
                  u.username,
                  (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id AND is_deleted = FALSE) as comment_count
                FROM " . $this->table_name . " p
                JOIN users u ON p.user_id = u.user_id
                WHERE p.course_id = :course_id AND p.is_deleted = FALSE";

      // Add sorting
      switch ($sort_by) {
        case 'popular':
          $query .= " ORDER BY p.view_count DESC";
          break;
        case 'unanswered':
          $query .= " AND (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id AND is_deleted = FALSE) = 0
                    ORDER BY p.created_at DESC";
          break;
        default:
          $query .= " ORDER BY p.is_pinned DESC, p.created_at DESC";
          break;
      }

      // Add pagination
      $query .= " LIMIT :limit OFFSET :offset";
      $stmt = $this->conn->prepare($query);

      $stmt->bindParam(':course_id', $course_id);
      $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
      $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
      $stmt->execute();
      
      $posts = $stmt->fetchAll();

      // Get total count for pagination
      $count_query = "SELECT COUNT(*) as total FROM " . $this->table_name . "
                    WHERE course_id = :course_id AND is_deleted = FALSE";

      if ($sort_by == 'unanswered') {
        $count_query .= " AND (SELECT COUNT(*) FROM comments WHERE post_id = posts.post_id AND is_deleted = FALSE) = 0";
      }

      $count_stmt = $this->conn->prepare($count_query);
      $count_stmt->bindParam(":course_id", $course_id);
      $count_stmt->execute();
      $total_posts = $count_stmt->fetch()['total'];

      return array(
        "posts" => $posts,
        "total" => $total_posts,
        "page" => $page,
        "limit" => $limit,
        "total_pages" => ceil($total_posts / $limit)
      );
      
    } catch (PDOException $e) {
      error_log("Error fetching posts: " . $e->getMessage());
      return array(
        "posts" => array(),
        "total" => 0,
        "page" => $page,
        "limit" => $limit,
        "total_pages" => 0
      );
    }
  }

  /**
   * Gets recent posts with optional user filtering and sorting
   * 
   * @param int|null $user_id User ID to filter by (optional)
   * @param int $limit Maximum number of posts to return
   * @param string $sort_by Sort method ('recent', 'popular', 'unanswered')
   * @return array Array of posts
   */
  public function get_recent_posts($user_id = null, $limit = 10, $sort_by = 'recent') {
    try {
      // Ensure posts table exists
      $table_check = $this->conn->query("SHOW TABLES LIKE '{$this->table_name}'");
      
      if ($table_check->rowCount() == 0) {
        return array(); // Table doesn't exist, return empty array
      }
      
      // Base query
      $query = "SELECT p.*,
                  u.username,
                  c.course_code,
                  c.course_name,
                  (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id AND is_deleted = FALSE) as comment_count
                FROM " . $this->table_name . " p
                JOIN users u ON p.user_id = u.user_id
                JOIN courses c ON p.course_id = c.course_id
                WHERE p.is_deleted = FALSE";
      
      // Add user filter if provided
      if ($user_id) {
        $query .= " AND p.course_id IN (
                      SELECT course_id FROM user_courses WHERE user_id = :user_id
                    )";
      }

      // Add filter for unanswered posts
      if ($sort_by == 'unanswered') {
        $query .= " AND (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id AND is_deleted = FALSE) = 0";
      }

      // Add sorting
      switch ($sort_by) {
        case 'popular':
          $query .= " ORDER BY p.view_count DESC";
          break;
        case 'unanswered':
          $query .= " ORDER BY p.created_at DESC";
          break;
        default:
          $query .= " ORDER BY p.is_pinned DESC, p.created_at DESC";
          break;
      }
      
      // Add limit
      $query .= " LIMIT :limit";
      
      $stmt = $this->conn->prepare($query);

      if ($user_id) $stmt->bindParam(":user_id", $user_id);
      $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
      $stmt->execute();

      return $stmt->fetchAll();
      
    } catch (PDOException $e) {
      error_log("Error fetching recent posts: " . $e->getMessage());
      return array();
    }
  }

  /**
   * Searches for posts based on search term
   * 
   * @param string $search_term Search term
   * @param int $limit Maximum number of results to return
   * @return array Array of matching posts
   */
  public function search_posts($search_term, $limit = 50) {
      if (empty($search_term)) {
          return [];
      }
      
      try {
          // Create search pattern
          $search_pattern = '%' . $search_term . '%';
          $search_words = explode(" ", $search_term);
          
          // Base query
          $query = "SELECT p.*, 
                      u.username, 
                      c.course_code, 
                      c.course_name,
                      (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id AND is_deleted = FALSE) as comment_count
                  FROM " . $this->table_name . " p
                  JOIN users u ON p.user_id = u.user_id
                  JOIN courses c ON p.course_id = c.course_id
                  WHERE p.is_deleted = FALSE
                  AND (
                      p.title LIKE ? OR 
                      p.content LIKE ? OR 
                      c.course_code LIKE ? OR
                      c.course_name LIKE ? OR
                      u.username LIKE ?
                  )";
          
          // Add ordering by relevance and recency
          $query .= " ORDER BY 
                      CASE 
                          WHEN p.title = ? THEN 1            /* Exact title match */
                          WHEN p.title LIKE ? THEN 2         /* Title starts with term */
                          WHEN p.title LIKE ? THEN 3         /* Title contains term */
                          WHEN p.content LIKE ? THEN 4       /* Content contains term */
                          ELSE 5
                      END,
                      p.is_pinned DESC,
                      p.created_at DESC
                  LIMIT ?";
          
          $stmt = $this->conn->prepare($query);
          
          // Bind main search parameters (WHERE clause)
          $pos = 1;
          $stmt->bindValue($pos++, $search_pattern);  // title LIKE
          $stmt->bindValue($pos++, $search_pattern);  // content LIKE
          $stmt->bindValue($pos++, $search_pattern);  // course_code LIKE
          $stmt->bindValue($pos++, $search_pattern);  // course_name LIKE
          $stmt->bindValue($pos++, $search_pattern);  // username LIKE
          
          // Bind ordering parameters
          $stmt->bindValue($pos++, $search_term);                // Exact title match
          $stmt->bindValue($pos++, $search_term . '%');          // Title starts with
          $stmt->bindValue($pos++, $search_pattern);             // Title contains
          $stmt->bindValue($pos++, $search_pattern);             // Content contains
          
          // Bind limit
          $stmt->bindValue($pos++, $limit, PDO::PARAM_INT);
          
          $stmt->execute();
          return $stmt->fetchAll(PDO::FETCH_ASSOC);
          
      } catch (PDOException $e) {
          error_log("Error searching posts: " . $e->getMessage());
          error_log("SQL state: " . $e->getCode());
          return [];
      }
  }

  /**
   * Gets posts by a specific user with pagination
   * 
   * @param int $user_id User ID
   * @param int $page Page number
   * @param int $limit Items per page
   * @return array Array with posts data and pagination info
   */
  public function get_posts_by_user($user_id, $page = 1, $limit = 10) {
    $offset = ($page - 1) * $limit;
    
    try {
      // Ensure posts table exists
      $table_check = $this->conn->query("SHOW TABLES LIKE '{$this->table_name}'");
      
      if ($table_check->rowCount() == 0) {
        return array(
          'posts' => array(),
          'total' => 0,
          'page' => $page,
          'limit' => $limit,
          'total_pages' => 0
        );
      }
      
      // Get posts
      $query = "SELECT p.*, 
                    c.course_code, 
                    c.course_name,
                    (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id AND is_deleted = FALSE) as comment_count
                FROM " . $this->table_name . " p
                JOIN courses c ON p.course_id = c.course_id
                WHERE p.user_id = :user_id AND p.is_deleted = FALSE
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset";
      
      $stmt = $this->conn->prepare($query);
      
      $stmt->bindParam(':user_id', $user_id);
      $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
      $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
      $stmt->execute();
      
      $posts = $stmt->fetchAll();
      
      // Get total count for pagination
      $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " 
               WHERE user_id = :user_id AND is_deleted = FALSE";
      
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':user_id', $user_id);
      $stmt->execute();
      $total_posts = $stmt->fetch()['total'];
      
      return array(
        'posts' => $posts,
        'total' => $total_posts,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total_posts / $limit)
      );
      
    } catch (PDOException $e) {
      error_log("Error fetching user posts: " . $e->getMessage());
      return array(
        'posts' => array(),
        'total' => 0,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => 0
      );
    }
  }
  
  /**
   * Gets recent posts for admin/moderator dashboard
   * 
   * @param int $limit Maximum number of posts to return
   * @return array Array of posts
   */
  public function get_admin_posts($limit = 10) {
    try {
      // Ensure posts table exists
      $table_check = $this->conn->query("SHOW TABLES LIKE '{$this->table_name}'");
      
      if ($table_check->rowCount() == 0) {
        return array(); // Table doesn't exist, return empty array
      }
      
      $query = "SELECT p.*,
                  u.username as author_name,
                  c.course_name as course_title,
                  c.course_id
                FROM " . $this->table_name . " p
                LEFT JOIN users u ON p.user_id = u.user_id
                LEFT JOIN courses c ON p.course_id = c.course_id
                WHERE p.is_deleted = FALSE
                ORDER BY p.created_at DESC
                LIMIT :limit";
      
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
      $stmt->execute();
      
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
      
    } catch (PDOException $e) {
      error_log("Error fetching admin posts: " . $e->getMessage());
      return array();
    }
  }
}