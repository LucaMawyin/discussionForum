<?php
/**
 * Header template
 * 
 * Common header included across all main pages
 * Note: login.php and register.php have their own headers
 */
require_once 'includes/config/database.php';
require_once 'includes/utils.php';

ensure_session_started();

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Check for remember-me token
if (!isset($_SESSION["user_id"]) && isset($_COOKIE['remember_token']) && isset($_COOKIE['user_id'])) {
  require_once 'includes/classes/User.php';
  $user = new User($conn);

  $token = $_COOKIE['remember_token'];
  $user_id = $_COOKIE['user_id'];

  if ($user->verify_remember_token($user_id, $token)) {
    // Token is valid, session is set up in verify_remember_token method
    // Extend cookie lifetime
    setcookie('remember_token', $token, time() + (86400 * 30), "/");
    setcookie('user_id', $user_id, time() + (86400 * 30), "/");
  }
}

// All Courses Variable for Sidebar
require_once "includes/classes/Course.php";
$course_obj = new Course($conn);
$all_courses = $course_obj->get_all_courses();

// User authentication state
$is_logged_in = is_logged_in();
$is_admin = is_admin();
$is_moderator = is_moderator_or_above();

if ($is_logged_in) {
  $user_id = $_SESSION['user_id'];
  $username = $_SESSION['username'];
  $user_role = $_SESSION['role'];
}

// Generate CSRF token for forms
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $page_title ?? 'CodeForum'; ?></title>
  <link rel="stylesheet" href="assets/css/global.css">
  <?php
  // Include additional stylesheets if specified
  if (isset($extra_styles)):
    if (is_array($extra_styles)):
      foreach ($extra_styles as $style):
        echo '<link rel="stylesheet" href="' . htmlspecialchars($style) . '">';
      endforeach;
    else:
      echo $extra_styles;
    endif;
  endif;
  ?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" defer></script>
</head>

<body>
  <header>
    <a href="index.php">
      <h1>CodeForum</h1>
    </a>
    <div class="search-container">
      <form action="search.php" method="GET">
        <input type="text" name="q" placeholder="Search forums..." 
              value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
        <button type="submit" class="search-btn"><i class="fa fa-search"></i></button>
      </form>
    </div>
    <div id="nav-button-container">
      <?php if ($is_logged_in): ?>
        <a href="create-post.php"><button class="create">Create Post</button></a>
        <div class="dropdown">
          <button class="account dropdown-toggle">
            <?php echo htmlspecialchars($username); ?>
            <i class="fas fa-caret-down"></i>
          </button>
          <div class="dropdown-menu">
            <a href="profile.php" class="dropdown-item"><i class="fas fa-user"></i> Profile</a>
            <?php if ($is_admin): ?>
              <a href="admin.php" class="dropdown-item"><i class="fas fa-cog"></i> Admin Dashboard</a>
            <?php elseif ($is_moderator): ?>
              <a href="moderator.php" class="dropdown-item"><i class="fas fa-shield-alt"></i> Moderator Panel</a>
            <?php endif; ?>
            <a href="logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="register.php"><button class="account">Register</button></a>
        <a href="login.php"><button class="account">Log In</button></a>
      <?php endif; ?>
    </div>
  </header>

  <div class="content">