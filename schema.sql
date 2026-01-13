-- 
-- CodeForum Database Schema
-- 

-- Users table - stores user accounts
CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL,
  role ENUM('student', 'moderator', 'admin') DEFAULT 'student',
  remember_token VARCHAR(64) NULL,
  token_expiry TIMESTAMP NULL
);

-- Courses table - stores available courses
CREATE TABLE courses (
  course_id INT AUTO_INCREMENT PRIMARY KEY,
  course_code VARCHAR(20) NOT NULL UNIQUE,
  course_name VARCHAR(100) NOT NULL,
  description TEXT,
  instructor_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (instructor_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- User course enrollments and roles
CREATE TABLE user_courses (
  user_id INT NOT NULL,
  course_id INT NOT NULL,
  enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  role ENUM('student', 'moderator', 'admin') DEFAULT 'student',
  PRIMARY KEY (user_id, course_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
);

-- Forum posts
CREATE TABLE posts (
  post_id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  course_id INT NOT NULL,
  title VARCHAR(100) NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  is_pinned BOOLEAN DEFAULT FALSE,
  is_closed BOOLEAN DEFAULT FALSE,
  is_deleted BOOLEAN DEFAULT FALSE,
  view_count INT DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
);

-- Comments on posts
CREATE TABLE comments (
  comment_id INT PRIMARY KEY AUTO_INCREMENT,
  post_id INT NOT NULL,
  user_id INT,
  content TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  parent_comment_id INT,
  is_deleted BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  FOREIGN KEY (parent_comment_id) REFERENCES comments(comment_id) ON DELETE SET NULL
);

-- Create an initial users (password: Password123!)
INSERT INTO users (username, email, password, role) VALUES
  ('admin', 'admin@mcmaster.ca', '$2y$10$acGCs9eL42d2npSkIQzfoOOt4x0qdE.bvFdJkDNXWIMT2jx8Sqhnm', 'admin'),
  ('mod', 'mod@mcmaster.ca', '$2y$10$acGCs9eL42d2npSkIQzfoOOt4x0qdE.bvFdJkDNXWIMT2jx8Sqhnm', 'moderator'),
  ('student', 'student@mcmaster.ca', '$2y$10$acGCs9eL42d2npSkIQzfoOOt4x0qdE.bvFdJkDNXWIMT2jx8Sqhnm', 'student');

-- Create sample courses (gpt-generated)
INSERT INTO courses (course_code, course_name, description, instructor_id)
VALUES 
('COMPSCI 1MD3', 'Introduction to Programming', 'Fundamentals of programming concepts, basic data types, control structures and classes. Problem solving, algorithms and software design principles.', 1),
('COMPSCI 1XA3', 'Computer Science Practice', 'Practical experience with implementing basic CS concepts such as data structures, functional programming, shell scripting, and automation.', 1),
('COMPSCI 2DM3', 'Discrete Mathematics for Computer Science', 'Sets, functions, relations, trees, graphs, well-ordering and induction. Application to analysis of algorithms and recurrence relations.', 1),
('COMPSCI 2C03', 'Data Structures and Algorithms', 'Basic data structures: lists, stacks, queues, trees, and graphs. Analysis of algorithms, Big-O notation, and algorithm design techniques.', 1);