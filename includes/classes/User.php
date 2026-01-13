<?php
require_once 'includes/utils.php';

/**
 * User Class
 * 
 * Manages user-related operations including authentication,
 * registration, profile management, and role-based permissions
 */
class User {
  private $conn;
  private $table_name = "users";

  public $user_id;
  public $username;
  public $email;
  public $password;
  public $registration_date;
  public $last_login;
  public $role;

  /**
   * Constructor - initializes database connection and loads user data if ID provided
   * 
   * @param PDO $db Database connection
   * @param int|null $user_id User ID (optional)
   */
  public function __construct($db, $user_id = null) {
    $this->conn = $db;
    if ($user_id) {
      $this->user_id = $user_id;
      $this->load_user_data();
    }
  }
  
  /**
   * Loads user data from database
   */
  private function load_user_data() {
    $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":user_id", $this->user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $this->username = $row['username'];
      $this->email = $row['email'];
      $this->registration_date = $row['registration_date'];
      $this->last_login = $row['last_login'];
      $this->role = $row['role'];
    }
  }

  /**
   * Registers a new user
   * 
   * @param string $username Username
   * @param string $email Email address
   * @param string $password Password
   * @return array Result array with success status, message, and user ID if successful
   */
  public function register($username, $email, $password) {
    if (empty($username) || empty($email) || empty($password)) {
      return return_response(false, "All fields are required");
    }

    if ($this->username_exists($username)) {
      return return_response(false, "Username already exists");
    }

    if ($this->email_exists($email)) {
      return return_response(false, "Email already exists");
    }

    // Validate email domain for McMaster
    if (!preg_match('/@mcmaster\.ca$/', $email)) {
      return return_response(false, "Please use a valid McMaster email address");
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $query = "INSERT INTO " . $this->table_name . "
              (username, email, password, role)
              VALUES (:username, :email, :password, 'student')";
    
    $stmt = $this->conn->prepare($query);
    
    $username = clean_input($username);
    $email = clean_input($email);

    $stmt->bindParam(":username", $username);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":password", $hashed_password);

    try {
      if ($stmt->execute()) {
        return array(
          "success" => true,
          "user_id" => $this->conn->lastInsertId(),
          "message" => "Registration successful"
        );
      }
    } catch (PDOException $e) {
      error_log("Registration error: " . $e->getMessage());
      return return_response(false, "Registration failed. Please try again later.");
    }

    return return_response(false, "Registration failed");
  }

  /**
   * Authenticates a user
   * 
   * @param string $email Email address
   * @param string $password Password
   * @return array Result array with success status, message, and user data if successful
   */
  public function login($email, $password) {
    if (empty($email) || empty($password)) {
      return return_response(false, "All fields are required");
    }

    $query = "SELECT user_id, username, email, password, role
              FROM " . $this->table_name . "
              WHERE email = :email";
    
    $stmt = $this->conn->prepare($query);
    
    $email = clean_input($email);
    $stmt->bindParam(":email", $email);

    $stmt->execute();

    if ($stmt->rowCount() == 1) {
      $row = $stmt->fetch();

      if (password_verify($password, $row['password'])) {
        $this->update_last_login($row['user_id']);

        ensure_session_started();
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['email'] = $row['email'];
        $_SESSION['role'] = $row['role'];

        return array(
          "success" => true,
          "user_id" => $row['user_id'],
          "username" => $row['username'],
          "role" => $row['role'],
          "message" => "Login successful"
        );
      } else {
        return return_response(false, "Invalid password");
      }
    }

    return return_response(false, "User not found");
  }

  /**
   * Checks if a user can update another user's role based on role hierarchy
   * 
   * @param int $editor_id User ID of the editor
   * @param int $target_id User ID of the target
   * @param string $new_role The new role to assign
   * @return bool True if user has permission, false otherwise
   */
  private function check_role_update_permission($editor_id, $target_id, $new_role) {
    // If user is updating themselves, deny the operation
    if ($editor_id == $target_id) {
      return false;
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
    
    // Get target's role
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':user_id', $target_id);
    $stmt->execute();
    $target_role = $stmt->fetch();
    
    if (!$target_role) {
      return false;
    }
    
    // Define role hierarchy (higher index = higher permission)
    $role_hierarchy = [
      'student' => 1,
      'moderator' => 2,
      'admin' => 3
    ];
    
    $editor_level = $role_hierarchy[$editor_role['role']];
    $target_level = $role_hierarchy[$target_role['role']];
    $new_role_level = $role_hierarchy[$new_role];
    
    // Rules:
    // 1. Editor must be of higher level than target
    // 2. Editor cannot promote to a level higher or equal to their own
    return ($editor_level > $target_level && $editor_level > $new_role_level);
  }

  /**
   * Logs out a user and clears session/cookies
   * 
   * @return array Result array with success status and message
   */
  public function logout() {
    ensure_session_started();
    $_SESSION = array();
    session_destroy();
    
    if (isset($_COOKIE['remember_token']) && isset($_COOKIE['user_id'])) {
      $this->clear_remember_token($_COOKIE['user_id']);
      
      setcookie('remember_token', '', time() - 3600, '/');
      setcookie('user_id', '', time() - 3600, '/');
    }
    
    return return_response(true, "Logout successful");
  }

  /**
   * Retrieves user data by ID
   * 
   * @param int $user_id User ID
   * @return array|false User data array or false if not found
   */
  public function get_user_by_id($user_id) {
    $query = "SELECT user_id, username, email, registration_date, last_login, role
              FROM " . $this->table_name . "
              WHERE user_id = :user_id";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Gets courses the user is enrolled in
   * 
   * @param int $user_id User ID
   * @return array Array of course data with user role
   */
  public function get_user_courses($user_id) {
    $query = "SELECT c.course_id, c.course_code, c.course_name, c.description,
                     uc.role as user_role, uc.enrollment_date
              FROM courses c
              JOIN user_courses uc ON c.course_id = uc.course_id
              WHERE uc.user_id = :user_id
              ORDER BY c.course_code";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Enrolls a user in a course
   * 
   * @param int $user_id User ID
   * @param int $course_id Course ID
   * @param string $role Role in the course (default: 'student')
   * @return array Result array with success status and message
   */
  public function enroll_in_course($user_id, $course_id, $role = 'student') {
    $query = "SELECT * FROM user_courses
              WHERE user_id = :user_id AND course_id = :course_id";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":course_id", $course_id);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
      return return_response(false, "Already enrolled in this course");
    }

    $query = "INSERT INTO user_courses (user_id, course_id, role)
              VALUES (:user_id, :course_id, :role)";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":course_id", $course_id);
    $stmt->bindParam(":role", $role);

    try {
      if ($stmt->execute()) {
        return return_response(true, "Successfully enrolled");
      }
    } catch (PDOException $e) {
      error_log("Enrollment error: " . $e->getMessage());
      return return_response(false, "Enrollment failed. Please try again later.");
    }

    return return_response(false, "Enrollment failed");
  }

  /**
   * Checks if a user is enrolled in a specific course
   * 
   * @param int $user_id User ID
   * @param int $course_id Course ID
   * @return bool True if enrolled, false otherwise
   */
  public function is_enrolled($user_id, $course_id) {
    $query = "SELECT * FROM user_courses
              WHERE user_id = :user_id AND course_id = :course_id";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":course_id", $course_id);
    $stmt->execute();

    return $stmt->rowCount() > 0;
  }

  /**
   * Gets user activity statistics
   * 
   * @param int $user_id User ID
   * @return array Array with post count, comment count, and course count
   */
  public function get_user_stats($user_id) {
    $query = "SELECT COUNT(*) as post_count FROM posts WHERE user_id = :user_id AND is_deleted = FALSE";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    $post_count = $stmt->fetch()['post_count'];

    $query = "SELECT COUNT(*) as comment_count FROM comments WHERE user_id = :user_id AND is_deleted = FALSE";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    $comment_count = $stmt->fetch()['comment_count'];

    $query = "SELECT COUNT(*) as course_count FROM user_courses WHERE user_id = :user_id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    $course_count = $stmt->fetch()['course_count'];

    return array(
      "post_count" => $post_count,
      "comment_count" => $comment_count,
      "course_count" => $course_count
    );
  }

  /**
   * Updates a user's profile
   * 
   * @param int $user_id User ID
   * @param string $email New email (optional)
   * @param string $current_password Current password for verification
   * @param string $new_password New password (optional)
   * @return array Result array with success status and message
   */
  public function update_profile($user_id, $email, $current_password, $new_password = '') {
    // Get current user data
    $query = "SELECT password FROM " . $this->table_name . " WHERE user_id = :user_id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
      return return_response(false, "User not found");
    }
    
    $user_data = $stmt->fetch();
    
    // Verify current password
    if (!password_verify($current_password, $user_data['password'])) {
      return return_response(false, "Current password is incorrect");
    }
    
    // Start building the update query
    $query = "UPDATE " . $this->table_name . " SET ";
    $params = [];
    
    // Add email update if provided
    if (!empty($email)) {
      // Validate email format
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return return_response(false, "Invalid email format");
      }
      
      // Make sure it's a McMaster email
      if (!preg_match('/@mcmaster\.ca$/', $email)) {
        return return_response(false, "Please use a valid McMaster email address");
      }
      
      // Check if email is already in use by another user
      $check_query = "SELECT user_id FROM " . $this->table_name . " WHERE email = :email AND user_id != :user_id";
      $check_stmt = $this->conn->prepare($check_query);
      $check_stmt->bindParam(':email', $email);
      $check_stmt->bindParam(':user_id', $user_id);
      $check_stmt->execute();
      
      if ($check_stmt->rowCount() > 0) {
        return return_response(false, "Email is already in use by another account");
      }
      
      $query .= "email = :email, ";
      $params[':email'] = $email;
    }
    
    // Add password update if provided
    if (!empty($new_password)) {
      // Validate password complexity
      if (strlen($new_password) < 8) {
        return return_response(false, "Password must be at least 8 characters long");
      }
      
      $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
      $query .= "password = :password, ";
      $params[':password'] = $hashed_password;
    }
    
    // Finalize query - remove trailing comma and space
    $query = rtrim($query, ", ");
    
    // Add WHERE clause
    $query .= " WHERE user_id = :user_id";
    $params[':user_id'] = $user_id;
    
    // If nothing to update, return success
    if (count($params) === 1) {
      return return_response(true, "No changes made");
    }
    
    // Execute update
    $stmt = $this->conn->prepare($query);
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    
    try {
      if ($stmt->execute()) {
        return return_response(true, "Profile updated successfully");
      }
    } catch (PDOException $e) {
      error_log("Profile update error: " . $e->getMessage());
      return return_response(false, "Profile update failed. Please try again later.");
    }
    
    return return_response(false, "Profile update failed");
  }

  /**
   * Updates the last login timestamp for a user
   * 
   * @param int $user_id User ID
   */
  private function update_last_login($user_id) {
    $query = "UPDATE " . $this->table_name . "
              SET last_login = CURRENT_TIMESTAMP
              WHERE user_id = :user_id";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
  }

  /**
   * Checks if a username already exists
   * 
   * @param string $username Username to check
   * @return bool True if username exists, false otherwise
   */
  private function username_exists($username) {
    $query = "SELECT user_id FROM " . $this->table_name . " WHERE username = :username";
    
    $stmt = $this->conn->prepare($query);
    $username = clean_input($username);
    $stmt->bindParam(":username", $username);
    $stmt->execute();

    return $stmt->rowCount() > 0;
  }

  /**
   * Checks if an email already exists
   * 
   * @param string $email Email to check
   * @return bool True if email exists, false otherwise
   */
  private function email_exists($email) {
    $query = "SELECT user_id FROM " . $this->table_name . " WHERE email = :email";

    $stmt = $this->conn->prepare($query);
    $email = clean_input($email);
    $stmt->bindParam(":email", $email);
    $stmt->execute();

    return $stmt->rowCount() > 0;
  }
  
  /**
   * Sets a remember-me token for a user
   * 
   * @param int $user_id User ID
   * @param string $token Token string
   * @return bool True if successful, false otherwise
   */
  public function set_remember_token($user_id, $token) {
    $expiry = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days
    
    $query = "UPDATE " . $this->table_name . "
              SET remember_token = :token, token_expiry = :expiry
              WHERE user_id = :user_id";
              
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":token", $token);
    $stmt->bindParam(":expiry", $expiry);
    $stmt->bindParam(":user_id", $user_id);
    
    return $stmt->execute();
  }
  
  /**
   * Verifies a remember-me token
   * 
   * @param int $user_id User ID
   * @param string $token Token to verify
   * @return bool True if token is valid, false otherwise
   */
  public function verify_remember_token($user_id, $token) {
    $query = "SELECT user_id, username, email, role, token_expiry
              FROM " . $this->table_name . "
              WHERE user_id = :user_id AND remember_token = :token
              AND token_expiry > CURRENT_TIMESTAMP";
              
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":token", $token);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
      $row = $stmt->fetch();
      $this->update_last_login($user_id);
      
      ensure_session_started();
      $_SESSION['user_id'] = $user_id;
      $_SESSION['username'] = $row['username'];
      $_SESSION['email'] = $row['email'];
      $_SESSION['role'] = $row['role'];
      
      return true;
    }
    
    return false;
  }
  
  /**
   * Clears a remember-me token
   * 
   * @param int $user_id User ID
   * @return bool True if successful, false otherwise
   */
  public function clear_remember_token($user_id) {
    $query = "UPDATE " . $this->table_name . "
              SET remember_token = NULL, token_expiry = NULL
              WHERE user_id = :user_id";
              
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    
    return $stmt->execute();
  }
  
  /**
   * Gets all users (admin function)
   * 
   * @return array Array of all users
   */
  public function get_all_users() {
    $query = "SELECT * FROM " . $this->table_name . " ORDER BY username";
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  
  /**
   * Updates a user's role (admin function)
   * 
   * @param string $role New role
   * @return array Result array with success status and message
   */
  public function update_role($role) {
    if (!in_array($role, ['student', 'moderator', 'admin'])) {
      return return_response(false, "Invalid role");
    }
    
    // Get the ID of the admin making the change (from session)
    ensure_session_started();
    $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    // Check if the admin has permission to update this user's role
    if (!$this->check_role_update_permission($admin_id, $this->user_id, $role)) {
      return return_response(false, "You cannot change the role of a user with a role equal to or higher than yours, or promote a user to your level.");
    }
    
    $query = "UPDATE " . $this->table_name . "
              SET role = :role
              WHERE user_id = :user_id";
              
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":role", $role);
    $stmt->bindParam(":user_id", $this->user_id);
    
    try {
      if ($stmt->execute()) {
        $this->role = $role;
        return return_response(true, "Role updated successfully");
      }
    } catch (PDOException $e) {
      error_log("Role update error: " . $e->getMessage());
      return return_response(false, "Role update failed. Please try again later.");
    }
    
    return return_response(false, "Failed to update role");
  }
  
  /**
   * Deletes a user (admin function)
   * 
   * @return array Result array with success status and message
   */
  public function delete_user() {
    // Get the ID of the admin making the change (from session)
    ensure_session_started();
    $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    // Don't allow users to delete themselves
    if ($admin_id == $this->user_id) {
      return return_response(false, "You cannot delete your own account.");
    }
    
    // Check if admin has permission to delete this user
    // Get admin's role
    $query = "SELECT role FROM users WHERE user_id = :user_id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':user_id', $admin_id);
    $stmt->execute();
    $admin_role = $stmt->fetch();
    
    // Get target user's role
    $target_role = $this->role;
    
    if (!$admin_role || !$target_role) {
      return return_response(false, "Invalid user information.");
    }
    
    // Define role hierarchy
    $role_hierarchy = [
      'student' => 1,
      'moderator' => 2,
      'admin' => 3
    ];
    
    $admin_level = $role_hierarchy[$admin_role['role']];
    $target_level = $role_hierarchy[$target_role];
    
    // Admin can only delete users of lower role level
    if ($admin_level <= $target_level) {
      return return_response(false, "You cannot delete a user with a role equal to or higher than yours.");
    }
    
    try {
      $this->conn->beginTransaction();
      
      // First anonymize all posts and comments
      $query = "UPDATE posts SET user_id = NULL WHERE user_id = :user_id";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(":user_id", $this->user_id);
      $stmt->execute();
      
      $query = "UPDATE comments SET user_id = NULL WHERE user_id = :user_id";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(":user_id", $this->user_id);
      $stmt->execute();
      
      // Remove from course enrollments
      $query = "DELETE FROM user_courses WHERE user_id = :user_id";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(":user_id", $this->user_id);
      $stmt->execute();
      
      // Delete the user
      $query = "DELETE FROM " . $this->table_name . " WHERE user_id = :user_id";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(":user_id", $this->user_id);
      $stmt->execute();
      
      $this->conn->commit();
      return return_response(true, "User deleted successfully");
      
    } catch (PDOException $e) {
      $this->conn->rollBack();
      error_log("User deletion error: " . $e->getMessage());
      return return_response(false, "Failed to delete user. Please try again later.");
    }
  }
  
  /**
   * Gets user data for the current user instance
   * 
   * @return array User data array
   */
  public function get_data() {
    return [
      'id' => $this->user_id,
      'username' => $this->username,
      'email' => $this->email,
      'role' => $this->role,
      'created_at' => $this->registration_date,
      'last_login' => $this->last_login
    ];
  }
}