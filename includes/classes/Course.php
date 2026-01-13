<?php
require_once 'includes/utils.php';

/**
 * Course Class
 * 
 * Manages course-related operations including creation, updates,
 * deletion, and retrieving course data
 */
class Course {
  private $conn;
  private $table_name = "courses";

  public $course_id;
  public $course_code;
  public $course_name;
  public $description;
  public $instructor_id;
  public $created_at;
  public $updated_at;

  /**
   * Constructor - initializes database connection and loads course data if ID provided
   * 
   * @param PDO $db Database connection
   * @param int|null $course_id Course ID (optional)
   */
  public function __construct($db, $course_id = null) {
    $this->conn = $db;
    
    if ($course_id) {
      $this->course_id = $course_id;
      $this->load_course_data();
    }
  }
  
  /**
   * Loads course data from database
   */
  private function load_course_data() {
    $query = "SELECT * FROM " . $this->table_name . " WHERE course_id = :course_id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":course_id", $this->course_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $this->course_code = $row['course_code'];
      $this->course_name = $row['course_name'];
      $this->description = $row['description'];
      $this->instructor_id = $row['instructor_id'];
      $this->created_at = $row['created_at'];
      $this->updated_at = $row['updated_at'];
    }
  }

  /**
   * Gets all courses
   * 
   * @return array Array of course data
   */
  public function get_all_courses() {
    $query = "SELECT c.*, u.username as instructor_name,
              (SELECT COUNT(*) FROM user_courses uc WHERE uc.course_id = c.course_id) as student_count
              FROM " . $this->table_name . " c
              LEFT JOIN users u ON c.instructor_id = u.user_id
              ORDER BY c.course_code";
    
    $stmt = $this->conn->prepare($query);
    
    try {
      $stmt->execute();
      return $stmt->fetchAll();
    } catch (PDOException $e) {
      error_log("Error fetching courses: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Gets course data by ID
   * 
   * @param int $course_id Course ID
   * @return array|false Course data array or false if not found
   */
  public function get_course_by_id($course_id) {
    $query = "SELECT c.*, u.username as instructor_name,
              (SELECT COUNT(*) FROM user_courses uc WHERE uc.course_id = c.course_id) as student_count
              FROM " . $this->table_name . " c
              LEFT JOIN users u ON c.instructor_id = u.user_id
              WHERE c.course_id = :course_id";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":course_id", $course_id);
    
    try {
      $stmt->execute();
      return $stmt->fetch();
    } catch (PDOException $e) {
      error_log("Error fetching course: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Gets course data for the current course instance
   * 
   * @return array|false Course data array or false if not found
   */
  public function get_course_data() {
    if (!$this->course_id) {
      return false;
    }
    
    return $this->get_course_by_id($this->course_id);
  }

  /**
   * Gets courses by course code
   * 
   * @param string $course_code Course code
   * @return array Array of course data
   */
  public function get_course_by_code($course_code) {
    $query = "SELECT c.*, u.username as instructor_name,
              (SELECT COUNT(*) FROM user_courses uc WHERE uc.course_id = c.course_id) as student_count
              FROM " . $this->table_name . " c
              LEFT JOIN users u ON c.instructor_id = u.user_id
              WHERE c.course_code = :course_code";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":course_code", $course_code);
    
    try {
      $stmt->execute();
      return $stmt->fetchAll();
    } catch (PDOException $e) {
      error_log("Error fetching course by code: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Creates a new course
   * 
   * @param string $course_code Course code
   * @param string $course_name Course name
   * @param string|null $description Course description (optional)
   * @param int|null $instructor_id Instructor user ID (optional)
   * @return array Result array with success status, message, and course ID if successful
   */
  public function create_course($course_code, $course_name, $description = null, $instructor_id = null) {
    if (empty($course_code) || empty($course_name)) {
      return return_response(false, "Course code and name are required");
    }

    if ($this->course_code_exists($course_code)) {
      return return_response(false, "Course code already exists");
    }

    $query = "INSERT INTO " . $this->table_name . "
              (course_code, course_name, description, instructor_id)
              VALUES (:course_code, :course_name, :description, :instructor_id)";

    $stmt = $this->conn->prepare($query);
    
    $course_code = clean_input($course_code);
    $course_name = clean_input($course_name);
    $description = clean_input($description);
    
    $stmt->bindParam(":course_code", $course_code);
    $stmt->bindParam(":course_name", $course_name);
    $stmt->bindParam(":description", $description);
    $stmt->bindParam(":instructor_id", $instructor_id);

    try {
      if ($stmt->execute()) {
        $course_id = $this->conn->lastInsertId();
        
        // Automatically enroll the creator as admin if instructor_id is provided
        if ($instructor_id) {
          $query = "INSERT INTO user_courses (user_id, course_id, role)
                    VALUES (:user_id, :course_id, 'admin')";
          $stmt = $this->conn->prepare($query);
          $stmt->bindParam(":user_id", $instructor_id);
          $stmt->bindParam(":course_id", $course_id);
          $stmt->execute();
        }
        
        return array(
          "success" => true,
          "course_id" => $course_id,
          "message" => "Course created successfully",
        );
      }
    } catch (PDOException $e) {
      error_log("Error creating course: " . $e->getMessage());
      return return_response(false, "Unable to create course. Please try again later.");
    }

    return return_response(false, "Unable to create course");
  }

  /**
   * Updates an existing course
   * 
   * @param int $course_id Course ID
   * @param string $course_code Course code
   * @param string $course_name Course name
   * @param string $description Course description
   * @param int|null $instructor_id Instructor user ID (optional)
   * @return array Result array with success status and message
   */
  public function update_course($course_id, $course_code, $course_name, $description, $instructor_id = null) {
    if (empty($course_id) || empty($course_code) || empty($course_name)) {
      return return_response(false, "Course ID, code, and name are required");
    }

    $existing_course = $this->get_course_by_id($course_id);
    if (!$existing_course) {
      return return_response(false, "Course not found");
    }

    if ($course_code !== $existing_course['course_code'] && $this->course_code_exists($course_code)) {
      return return_response(false, "Course code already exists");
    }

    $query = "UPDATE " . $this->table_name . "
              SET course_code = :course_code,
                  course_name = :course_name,
                  description = :description,
                  instructor_id = :instructor_id,
                  updated_at = CURRENT_TIMESTAMP
              WHERE course_id = :course_id";
    
    $stmt = $this->conn->prepare($query);
    
    $course_code = clean_input($course_code);
    $course_name = clean_input($course_name);
    $description = clean_input($description);

    $stmt->bindParam(":course_id", $course_id);
    $stmt->bindParam(":course_code", $course_code);
    $stmt->bindParam(":course_name", $course_name);
    $stmt->bindParam(":description", $description);
    $stmt->bindParam(":instructor_id", $instructor_id);

    try {
      if ($stmt->execute()) {
        return return_response(true, "Course updated successfully");
      }
    } catch (PDOException $e) {
      error_log("Error updating course: " . $e->getMessage());
      return return_response(false, "Unable to update course. Please try again later.");
    }

    return return_response(false, "Unable to update course");
  }

  /**
   * Deletes a course and all related data
   * 
   * @param int $course_id Course ID
   * @return array Result array with success status and message
   */
  public function delete_course() {
    if (!$this->course_id) {
      return return_response(false, "Course ID is required");
    }
    
    $existing_course = $this->get_course_by_id($this->course_id);
    if (!$existing_course) {
      return return_response(false, "Course not found");
    }

    try {
      $this->conn->beginTransaction();

      // Delete all comments on posts in this course
      $query = "DELETE c FROM comments c
                JOIN posts p ON c.post_id = p.post_id
                WHERE p.course_id = :course_id";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':course_id', $this->course_id);
      $stmt->execute();

      // Delete all posts in this course
      $query = "DELETE FROM posts WHERE course_id = :course_id";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':course_id', $this->course_id);
      $stmt->execute();
      
      // Delete all user enrollments for this course
      $query = "DELETE FROM user_courses WHERE course_id = :course_id";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':course_id', $this->course_id);
      $stmt->execute();
      
      // Delete the course itself
      $query = "DELETE FROM " . $this->table_name . " WHERE course_id = :course_id";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':course_id', $this->course_id);
      $stmt->execute();

      $this->conn->commit();
      return return_response(true, "Course deleted successfully");
      
    } catch (PDOException $e) {
      $this->conn->rollBack();
      error_log("Error deleting course: " . $e->getMessage());
      return return_response(false, "Error deleting course: " . $e->getMessage());
    }
  }

  /**
   * Gets users enrolled in a course
   * 
   * @param int $course_id Course ID
   * @return array Array of enrolled user data
   */
  public function get_enrolled_users($course_id) {
    $query = "SELECT u.user_id, u.username, u.email, uc.role as course_role, uc.enrollment_date
              FROM users u
              JOIN user_courses uc ON u.user_id = uc.user_id
              WHERE uc.course_id = :course_id
              ORDER BY uc.role, u.username";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":course_id", $course_id);
    
    try {
      $stmt->execute();
      return $stmt->fetchAll();
    } catch (PDOException $e) {
      error_log("Error fetching enrolled users: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Gets post statistics for a course
   * 
   * @param int $course_id Course ID
   * @return array Array with total posts, recent posts, and unanswered posts counts
   */
  public function get_post_stats($course_id) {
    try {
      // Total posts
      $query = "SELECT COUNT(*) as total_posts FROM posts 
               WHERE course_id = :course_id AND is_deleted = FALSE";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':course_id', $course_id);
      $stmt->execute();
      $total_posts = $stmt->fetch()['total_posts'];
      
      // Recent posts (last 30 days)
      $query = "SELECT COUNT(*) as recent_posts FROM posts 
               WHERE course_id = :course_id AND is_deleted = FALSE 
               AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':course_id', $course_id);
      $stmt->execute();
      $recent_posts = $stmt->fetch()['recent_posts'];
      
      // Unanswered posts
      $query = "SELECT COUNT(*) as unanswered_posts FROM posts p
               WHERE p.course_id = :course_id AND p.is_deleted = FALSE
               AND NOT EXISTS (
                   SELECT 1 FROM comments c 
                   WHERE c.post_id = p.post_id AND c.is_deleted = FALSE
               )";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':course_id', $course_id);
      $stmt->execute();
      $unanswered_posts = $stmt->fetch()['unanswered_posts'];
      
      return array(
          'total_posts' => $total_posts,
          'recent_posts' => $recent_posts,
          'unanswered_posts' => $unanswered_posts
      );
    } catch (PDOException $e) {
      error_log("Error fetching post stats: " . $e->getMessage());
      return array(
          'total_posts' => 0,
          'recent_posts' => 0,
          'unanswered_posts' => 0
      );
    }
  }

  /**
   * Checks if a course code already exists
   * 
   * @param string $course_code Course code to check
   * @return bool True if course code exists, false otherwise
   */
  private function course_code_exists($course_code) {
    $query = "SELECT course_id FROM " . $this->table_name . " WHERE course_code = :course_code";
    
    $stmt = $this->conn->prepare($query);
    $course_code = clean_input($course_code);
    $stmt->bindParam(":course_code", $course_code);
    
    try {
      $stmt->execute();
      return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Error checking course code: " . $e->getMessage());
      return false;
    }
  }
}