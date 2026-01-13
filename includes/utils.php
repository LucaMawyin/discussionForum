<?php
/**
 * Utility Functions
 * 
 * Collection of helper functions used throughout the application
 * Optimized for consistency and security
 */

/**
 * Ensures a session is started
 * 
 * @return bool True if session was just started, false if already active
 */
function ensure_session_started() {
  if (session_status() === PHP_SESSION_NONE) {
    // Set secure session settings
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    
    session_start();
    return true;
  }
  return false;
}

/**
 * Checks if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function is_logged_in() {
  ensure_session_started();
  return isset($_SESSION['user_id']);
}

/**
 * Redirects to specified URL with proper headers
 * 
 * @param string $url URL to redirect to
 * @param array $params Optional query parameters to append
 */
function redirect($url, $params = []) {
  if (!empty($params)) {
    $separator = (strpos($url, '?') !== false) ? '&' : '?';
    $url .= $separator . http_build_query($params);
  }
  
  header("Location: $url");
  exit;
}

/**
 * Sanitizes input data to prevent XSS
 * 
 * @param string $data Input data to be sanitized
 * @return string Sanitized data
 */
function clean_input($data) {
  if (is_null($data)) {
    return '';
  }
  
  if (is_array($data)) {
    return array_map('clean_input', $data);
  }
  
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
  return $data;
}

/**
 * Sets a flash message to be displayed on the next page load
 * 
 * @param string $type Message type ('success', 'error', 'info', 'warning')
 * @param string $message Message content
 * @param string $context Optional context identifier (for specific pages)
 */
function set_flash_message($type, $message, $context = 'global') {
  ensure_session_started();
  
  if (!isset($_SESSION['flash_messages'])) {
    $_SESSION['flash_messages'] = [];
  }
  
  $_SESSION['flash_messages'][$context][] = [
    'type' => $type,
    'message' => $message,
    'created' => time()
  ];
}

/**
 * Gets flash messages for display and removes them from session
 * 
 * @param string $context Optional context identifier to get messages for
 * @return array Array of flash messages
 */
function get_flash_messages($context = 'global') {
  ensure_session_started();
  
  if (!isset($_SESSION['flash_messages']) || empty($_SESSION['flash_messages'][$context])) {
    return [];
  }
  
  $messages = $_SESSION['flash_messages'][$context];
  unset($_SESSION['flash_messages'][$context]);
  
  return $messages;
}

/**
 * Checks if flash messages exist for a specific context
 * 
 * @param string $context Context identifier
 * @return bool True if messages exist, false otherwise
 */
function has_flash_messages($context = 'global') {
  ensure_session_started();
  return isset($_SESSION['flash_messages']) && 
         !empty($_SESSION['flash_messages'][$context]);
}

/**
 * Formats date to a human-readable format
 * 
 * @param string $timestamp Timestamp to format
 * @param string $format Optional date format (default: 'F j, Y \a\t g:i a')
 * @return string Formatted date string
 */
function format_date($timestamp, $format = 'F j, Y \a\t g:i a') {
  $date = new DateTime($timestamp);
  return $date->format($format);
}

/**
 * Converts timestamp to relative time (e.g. "2 hours ago")
 * 
 * @param string $timestamp Timestamp to convert
 * @return string Relative time string
 */
function get_relative_time($timestamp) {
  $time_ago = strtotime($timestamp);
  $current_time = time();
  $seconds = $current_time - $time_ago;

  $minute = 60;
  $hour = 60 * $minute;
  $day = 24 * $hour;
  $week = 7 * $day;
  $month = 30 * $day;
  $year = 365 * $day;

  if ($seconds < $minute) {
    return $seconds == 1 ? "1 second ago" : "$seconds seconds ago";
  } else if ($seconds < $hour) {
    $minutes = floor($seconds / $minute);
    return $minutes == 1 ? "1 minute ago" : "$minutes minutes ago";
  } else if ($seconds < $day) {
    $hours = floor($seconds / $hour);
    return $hours == 1 ? "1 hour ago" : "$hours hours ago";
  } else if ($seconds < $week) {
    $days = floor($seconds / $day);
    return $days == 1 ? "1 day ago" : "$days days ago";
  } else if ($seconds < $month) {
    $weeks = floor($seconds / $week);
    return $weeks == 1 ? "1 week ago" : "$weeks weeks ago";
  } else if ($seconds < $year) {
    $months = floor($seconds / $month);
    return $months == 1 ? "1 month ago" : "$months months ago";
  } else {
    $years = floor($seconds / $year);
    return $years == 1 ? "1 year ago" : "$years years ago";
  }
}

/**
 * Converts plain URLs in text to clickable links
 * 
 * @param string $text Text potentially containing URLs
 * @return string Text with URLs converted to HTML links
 */
function linkify($text) {
  return preg_replace(
    '~(?<!href="|">|="|src="|alt=")(https?://[^\s<]+)~i',
    '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
    $text
  );
}

/**
 * Creates a standardized response array
 * 
 * @param bool $success Success status
 * @param string $message Response message
 * @param array $data Optional additional data
 * @return array Response array with success status, message, and optional data
 */
function return_response($success, $message, $data = []) {
  $response = [
    "success" => $success, 
    "message" => $message
  ];
  
  if (!empty($data)) {
    $response["data"] = $data;
  }
  
  return $response;
}

/**
 * Formats title text for display
 * 
 * @param string $content Title text to format
 * @return string Formatted title
 */
function format_title_content($content) {
  $content = htmlspecialchars_decode($content, ENT_QUOTES);
  return $content;
}

/**
 * Formats post content with basic Markdown syntax
 * 
 * @param string $content Post content to format
 * @return string Formatted HTML content
 */
function format_post_content($content) {
  if (empty($content)) return '';
  
  // Handle HTML entities
  $content = htmlspecialchars_decode($content, ENT_QUOTES);
  
  // Process code blocks
  $content = preg_replace_callback('/```(.*?)```/s', function($matches) {
    $code = isset($matches[1]) ? $matches[1] : '';
    return '<pre><code>' . htmlspecialchars($code, ENT_QUOTES) . '</code></pre>';
  }, $content);
  
  // Process inline code
  $content = preg_replace('/`(.*?)`/s', '<code>$1</code>', $content);
  
  // Process headings (must process h6, h5, h4, h3, h2, h1 in that order to avoid conflicts)
  $content = preg_replace('/^[ \t]*#{6}[ \t]+(.+?)[ \t]*(?:#*)$/m', '<h6>$1</h6>', $content);
  $content = preg_replace('/^[ \t]*#{5}[ \t]+(.+?)[ \t]*(?:#*)$/m', '<h5>$1</h5>', $content);
  $content = preg_replace('/^[ \t]*#{4}[ \t]+(.+?)[ \t]*(?:#*)$/m', '<h4>$1</h4>', $content);
  $content = preg_replace('/^[ \t]*#{3}[ \t]+(.+?)[ \t]*(?:#*)$/m', '<h3>$1</h3>', $content);
  $content = preg_replace('/^[ \t]*#{2}[ \t]+(.+?)[ \t]*(?:#*)$/m', '<h2>$2</h2>', $content);
  $content = preg_replace('/^[ \t]*#[ \t]+(.+?)[ \t]*(?:#*)$/m', '<h1>$1</h1>', $content);
  
  // Process horizontal rules
  $content = preg_replace('/^[ \t]*(?:-{3,}|_{3,}|\*{3,})[ \t]*$/m', '<hr>', $content);
  
  // Process bold formatting
  $content = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $content);
  $content = preg_replace('/__(.*?)__/s', '<strong>$1</strong>', $content);
  
  // Process italic formatting
  $content = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $content);
  $content = preg_replace('/_(.*?)_/s', '<em>$1</em>', $content);
  
  // Process links
  $content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/s', '<a href="$2" target="_blank">$1</a>', $content);
  
  // Process unordered lists
  $content = preg_replace_callback('/(?:^[ \t]*[-*+][ \t]+.+?$(?:\r\n|\n|\r)?)+/m', function($matches) {
    $list = preg_replace('/^[ \t]*[-*+][ \t]+(.+?)$/m', '<li>$1</li>', $matches[0]);
    return '<ul>' . $list . '</ul>';
  }, $content);
  
  // Process ordered lists
  $content = preg_replace_callback('/(?:^[ \t]*\d+\.[ \t]+.+?$(?:\r\n|\n|\r)?)+/m', function($matches) {
    $list = preg_replace('/^[ \t]*\d+\.[ \t]+(.+?)$/m', '<li>$1</li>', $matches[0]);
    return '<ol>' . $list . '</ol>';
  }, $content);
  
  // Process blockquotes
  $content = preg_replace_callback('/(?:^[ \t]*>[ \t]?.+?$(?:\r\n|\n|\r)?)+/m', function($matches) {
    $quote = preg_replace('/^[ \t]*>[ \t]?(.+?)$/m', '$1', $matches[0]);
    $quote = '<p>' . str_replace("\n", '</p><p>', $quote) . '</p>';
    return '<blockquote>' . $quote . '</blockquote>';
  }, $content);
  
  // Process paragraphs and line breaks
  $paragraphs = preg_split('/\n{2,}/', $content);
  $content = '';
  
  foreach ($paragraphs as $paragraph) {
    $paragraph = trim($paragraph);
    if (empty($paragraph)) continue;
    
    // Skip wrapping if paragraph already contains block-level HTML
    if (preg_match('/^<(h[1-6]|ul|ol|blockquote|div|pre|table)/i', $paragraph)) {
      $content .= $paragraph . "\n";
    } else {
      // Convert line breaks to <br> within paragraphs
      $paragraph = str_replace("\n", "<br>", $paragraph);
      $content .= "<p>" . $paragraph . "</p>\n";
    }
  }
  
  // If no paragraphs, wrap content
  if (empty($content)) {
    $content = "<p>" . str_replace("\n", "<br>", $content) . "</p>";
  }
  
  // Auto-linkify URLs that aren't already in a link tag
  $content = preg_replace_callback(
    '~(?<!href="|">|="|src="|alt=")(https?://[^\s<]+)~i',
    function($matches) {
      return '<a href="' . $matches[1] . '" target="_blank">' . $matches[1] . '</a>';
    },
    $content
  );
  
  return $content;
}

/**
 * Checks if user has a specific role
 * 
 * @param string $role Role to check
 * @return bool True if user has the role, false otherwise
 */
function has_role($role) {
  ensure_session_started();
  return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Checks if user is an admin
 * 
 * @return bool True if user is an admin, false otherwise
 */
function is_admin() {
  return has_role('admin');
}

/**
 * Checks if user is a moderator or admin
 * 
 * @return bool True if user is a moderator or admin, false otherwise
 */
function is_moderator_or_above() {
  ensure_session_started();
  return isset($_SESSION['role']) && ($_SESSION['role'] === 'moderator' || $_SESSION['role'] === 'admin');
}

/**
 * Generates a CSRF token and stores it in the session
 * 
 * @param string $context Optional context for specific forms
 * @return string CSRF token
 */
function generate_csrf_token($context = 'global') {
  ensure_session_started();
  
  if (!isset($_SESSION['csrf_tokens'])) {
    $_SESSION['csrf_tokens'] = [];
  }
  
  $token = bin2hex(random_bytes(32));
  $_SESSION['csrf_tokens'][$context] = [
    'token' => $token,
    'created' => time()
  ];
  
  return $token;
}

/**
 * Validates a CSRF token against the stored token
 * 
 * @param string $token Token to validate
 * @param string $context Optional context for specific forms
 * @param int $expiry Optional token expiry time in seconds (default: 3600)
 * @return bool True if token is valid, false otherwise
 */
function verify_csrf_token($token, $context = 'global', $expiry = 3600) {
  ensure_session_started();
  
  if (!isset($_SESSION['csrf_tokens'][$context])) {
    return false;
  }
  
  $stored = $_SESSION['csrf_tokens'][$context];
  
  // Check if token is expired
  if (time() - $stored['created'] > $expiry) {
    unset($_SESSION['csrf_tokens'][$context]);
    return false;
  }
  
  return hash_equals($stored['token'], $token);
}

/**
 * Highlights search terms in text
 * 
 * @param string $text Text to search in
 * @param string $search_terms Search terms to highlight
 * @return string Text with highlighted search terms
 */
function highlight_search_terms($text, $search_terms) {
  if (empty($search_terms) || empty($text)) {
    return $text;
  }
  
  // Clean the text for display if it's not already
  if (strpos($text, '<') === false) {
    $text = htmlspecialchars($text, ENT_QUOTES);
  }
  
  // Split search terms by spaces
  $terms = preg_split('/\s+/', $search_terms);
  
  // Filter out short terms and sort by length (longest first)
  $terms = array_filter($terms, function($term) {
    return strlen($term) >= 2;
  });
  
  // If no valid terms remain, return the original text
  if (empty($terms)) {
    return $text;
  }
  
  // Sort terms by length (longest first) to avoid highlighting substrings first
  usort($terms, function($a, $b) {
    return strlen($b) - strlen($a);
  });
  
  // Highlight each term
  foreach ($terms as $term) {
    // Use word boundaries when possible for better matching
    $pattern = '/\b(' . preg_quote($term, '/') . ')\b/i';
    $replacement = '<mark>$1</mark>';
    
    // Try with word boundaries first
    $new_text = preg_replace($pattern, $replacement, $text);
    
    // If no matches with word boundaries, try without them
    if ($new_text === $text) {
      $pattern = '/(' . preg_quote($term, '/') . ')/i';
      $text = preg_replace($pattern, $replacement, $text);
    } else {
      $text = $new_text;
    }
  }
  
  return $text;
}

/**
 * Generates an SVG user icon for placeholders
 * 
 * @param int $size Icon size in pixels
 * @param string $color Icon color in hex
 * @return string SVG markup
 */
function get_user_icon_svg($size = 24, $color = '#6c757d') {
  return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 448 512" fill="' . $color . '">
    <path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H418.3c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304H178.3z"/>
  </svg>';
}

/**
 * Validate required form fields
 * 
 * @param array $required_fields Array of required field names
 * @param array $data Form data to validate
 * @return array Array with validation status and error message
 */
function validate_required_fields($required_fields, $data) {
  $missing = [];
  
  foreach ($required_fields as $field) {
    if (!isset($data[$field]) || trim($data[$field]) === '') {
      $missing[] = $field;
    }
  }
  
  if (!empty($missing)) {
    return [
      'valid' => false,
      'message' => 'Missing required fields: ' . implode(', ', $missing)
    ];
  }
  
  return ['valid' => true];
}

/**
 * Validate email format
 * 
 * @param string $email Email to validate
 * @param bool $require_mcmaster Whether to require McMaster domain
 * @return bool True if valid, false otherwise
 */
function validate_email($email, $require_mcmaster = true) {
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return false;
  }
  
  if ($require_mcmaster) {
    return preg_match('/@mcmaster\.ca$/', $email);
  }
  
  return true;
}

/**
 * Validate password complexity
 * 
 * @param string $password Password to validate
 * @return array Array with validation status and error messages
 */
function validate_password($password) {
  $errors = [];
  
  if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters long';
  }
  
  if (!preg_match('/[A-Z]/', $password)) {
    $errors[] = 'Password must contain at least one uppercase letter';
  }
  
  if (!preg_match('/[a-z]/', $password)) {
    $errors[] = 'Password must contain at least one lowercase letter';
  }
  
  if (!preg_match('/[0-9]/', $password)) {
    $errors[] = 'Password must contain at least one number';
  }
  
  if (!preg_match('/[^A-Za-z0-9]/', $password)) {
    $errors[] = 'Password must contain at least one special character';
  }
  
  return [
    'valid' => empty($errors),
    'errors' => $errors
  ];
}