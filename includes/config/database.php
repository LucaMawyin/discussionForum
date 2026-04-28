<?php

/**
 * Database Connection Class
 * 
 * Handles database connection and provides a singleton instance
 * for database operations throughout the application
 */
class Database
{
  // Database credentials - update these for your environment
  private $host = "localhost";
  private $db_name = "mawyinl_db";
  private $username = "root";
  private $password = "";
  private $conn;

  /**
   * Get a PDO database connection
   * 
   * @return PDO Database connection with consistent settings
   */
  public function getConnection()
  {
    $this->conn = null;

    try {
      // Set timezone to ensure consistent timestamp operations
      date_default_timezone_set("America/Toronto");

      // Create connection with error mode set to exceptions
      $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);

      // Set PDO attributes for consistent behavior
      $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
      $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    } catch (PDOException $e) {
      // Log the error and show a generic message
      error_log("Database Connection Error: " . $e->getMessage());
      die("Database connection error. Please try again later or contact the administrator.");
    }

    return $this->conn;
  }
}
