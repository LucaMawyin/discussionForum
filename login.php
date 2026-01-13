<?php
/**
 * Login Page
 * 
 * Handles user authentication and login
 */
$page_title = "Login";

require_once 'includes/utils.php';
require_once 'includes/config/database.php';
require_once 'includes/classes/User.php';

ensure_session_started();

// Check for remember-me cookie
if (!isset($_SESSION["user_id"]) && isset($_COOKIE['remember_token']) && isset($_COOKIE['user_id'])) {
  $database = new Database();
  $conn = $database->getConnection();
  $user = new User($conn);
  
  $token = $_COOKIE['remember_token'];
  $user_id = $_COOKIE['user_id'];
  
  if ($user->verify_remember_token($user_id, $token)) {
    // Extend cookie lifetime
    setcookie('remember_token', $token, time() + (86400 * 30), "/");
    setcookie('user_id', $user_id, time() + (86400 * 30), "/");
    
    // Redirect to intended page or index
    $redirect_to = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'index.php';
    unset($_SESSION['redirect_after_login']);
    redirect($redirect_to);
  }
}

// Redirect if already logged in
if (isset($_SESSION["user_id"])) {
  redirect("index.php");
}

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Validate CSRF token
  if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    $_SESSION['login_error'] = "Invalid form submission. Please try again.";
  } else {
    $email = isset($_POST['email']) ? clean_input($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember = isset($_POST['remember']) ? true : false;

    // Validate inputs
    if (empty($email) || empty($password)) {
      $_SESSION['login_error'] = "Email and password are required";
    } else {
      $database = new Database();
      $conn = $database->getConnection();
      $user = new User($conn);

      $result = $user->login($email, $password);
      if ($result['success']) {
        // Set remember-me cookie if requested
        if ($remember) {
          $token = bin2hex(random_bytes(32));
          $user_id = $result['user_id'];

          // Store token in database
          $user->set_remember_token($user_id, $token);
          
          // Set cookies
          setcookie('remember_token', $token, time() + (86400 * 30), "/");
          setcookie('user_id', $user_id, time() + (86400 * 30), "/");
        }

        // Redirect to intended page or index
        $redirect_to = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'index.php';
        unset($_SESSION['redirect_after_login']);

        redirect($redirect_to);
      } else {
        $_SESSION['login_error'] = $result['message'];
      }
    }
  }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - CodeForum</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="assets/js/auth.js" defer></script>
  </head>
  <body>
    <header>
      <a href="index.php"><h1>CodeForum</h1></a>
      <div id="nav-button-container">
        <a href="register.php"><button class="account">Register</button></a>
        <a href="login.php"><button class="account">Log In</button></a>
      </div>
    </header>

    <div class="auth-container">
      <div class="auth-box">
        <div class="auth-header">
          <h2>Log In</h2>
          <p>Welcome back to CodeForum</p>
        </div>

        <?php if(isset($_SESSION['login_error'])): ?>
          <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['login_error']; ?>
          </div>
          <?php unset($_SESSION['login_error']); ?>
        <?php endif; ?>

        <?php if(isset($_SESSION['login_success'])): ?>
          <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['login_success']; ?>
          </div>
          <?php unset($_SESSION['login_success']); ?>
        <?php endif; ?>

        <form id="login-form" class="auth-form" action="login.php" method="post">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          
          <div class="form-group">
            <label for="email">Email</label>
            <div class="input-with-icon">
              <i class="fas fa-envelope"></i>
              <input type="email" id="email" name="email" placeholder="Your McMaster email" required>
            </div>
          </div>
          
          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-with-icon">
              <i class="fas fa-lock"></i>
              <input type="password" id="password" name="password" placeholder="Your password" required>
              <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          
          <div class="form-options">
            <div class="remember-me">
              <input type="checkbox" id="remember" name="remember">
              <label for="remember">Remember me</label>
            </div>
            <a href="forgot-password.php" class="forgot-password">Forgot password?</a>
          </div>
          
          <button type="submit" class="auth-button">Log In</button>
        </form>

        <div class="auth-footer">
          <p>Don't have an account? <a href="register.php">Sign Up</a></p>
        </div>
      </div>
    </div>

    <?php include 'includes/partials/footer.php'; ?>
  </body>
</html>