-- 
-- CodeForum Database Schema
-- SQLite version of schema.sql
-- 
-- Users table - stores user accounts
CREATE TABLE users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP,
    role TEXT DEFAULT 'student' CHECK(role IN ('student', 'moderator', 'admin')),
    remember_token TEXT,
    token_expiry TIMESTAMP
);
-- Courses table - stores available courses
CREATE TABLE courses (
    course_id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_code TEXT NOT NULL UNIQUE,
    course_name TEXT NOT NULL,
    description TEXT,
    instructor_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES users(user_id) ON DELETE
    SET NULL
);
-- User course enrollments and roles
CREATE TABLE user_courses (
    user_id INTEGER NOT NULL,
    course_id INTEGER NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    role TEXT DEFAULT 'student' CHECK(role IN ('student', 'moderator', 'admin')),
    PRIMARY KEY (user_id, course_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
);
-- Forum posts
CREATE TABLE posts (
    post_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    course_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    is_pinned INTEGER DEFAULT 0,
    is_closed INTEGER DEFAULT 0,
    is_deleted INTEGER DEFAULT 0,
    view_count INTEGER DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE
    SET NULL,
        FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
);
-- Comments on posts
CREATE TABLE comments (
    comment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    user_id INTEGER,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    parent_comment_id INTEGER,
    is_deleted INTEGER DEFAULT 0 CHECK(is_deleted IN (0, 1)),
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE
    SET NULL,
        FOREIGN KEY (parent_comment_id) REFERENCES comments(comment_id) ON DELETE
    SET NULL
);
-- Create an initial users (password: Password123!)
INSERT INTO users (username, email, password, role)
VALUES (
        'admin',
        'admin@mcmaster.ca',
        '$2y$10$acGCs9eL42d2npSkIQzfoOOt4x0qdE.bvFdJkDNXWIMT2jx8Sqhnm',
        'admin'
    ),
    (
        'mod',
        'mod@mcmaster.ca',
        '$2y$10$acGCs9eL42d2npSkIQzfoOOt4x0qdE.bvFdJkDNXWIMT2jx8Sqhnm',
        'moderator'
    ),
    (
        'student',
        'student@mcmaster.ca',
        '$2y$10$acGCs9eL42d2npSkIQzfoOOt4x0qdE.bvFdJkDNXWIMT2jx8Sqhnm',
        'student'
    );
-- Create sample courses (gpt-generated)
INSERT INTO courses (
        course_code,
        course_name,
        description,
        instructor_id
    )
VALUES (
        'COMPSCI 1MD3',
        'Introduction to Programming',
        'Fundamentals of programming concepts, basic data types, control structures and classes. Problem solving, algorithms and software design principles.',
        1
    ),
    (
        'COMPSCI 1XA3',
        'Computer Science Practice',
        'Practical experience with implementing basic CS concepts such as data structures, functional programming, shell scripting, and automation.',
        1
    ),
    (
        'COMPSCI 2DM3',
        'Discrete Mathematics for Computer Science',
        'Sets, functions, relations, trees, graphs, well-ordering and induction. Application to analysis of algorithms and recurrence relations.',
        1
    ),
    (
        'COMPSCI 2C03',
        'Data Structures and Algorithms',
        'Basic data structures: lists, stacks, queues, trees, and graphs. Analysis of algorithms, Big-O notation, and algorithm design techniques.',
        1
    );