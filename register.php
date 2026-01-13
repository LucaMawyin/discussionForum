<?php
/**
 * Registration Page
 * 
 * Handles new user registration with validation
 */
$page_title = "Register";

require_once 'includes/utils.php';

ensure_session_started();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
  redirect("index.php");
}

// Process registration form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $username = isset($_POST['username']) ? clean_input($_POST['username']) : '';
  $email = isset($_POST['email']) ? clean_input($_POST['email']) : '';
  $password = isset($_POST['password']) ? $_POST['password'] : '';
  $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
  $terms = isset($_POST['terms']) ? true : false;
  $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

  // Validate CSRF token
  if (!verify_csrf_token($csrf_token)) {
    $_SESSION['register_error'] = "Invalid form submission. Please try again.";
  }
  // Validate required fields
  elseif (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
    $_SESSION['register_error'] = "All fields are required.";
  }
  // Validate username format
  elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    $_SESSION['register_error'] = "Username must be 3-20 characters and can only contain letters, numbers, and underscores.";
  }
  // Validate email format
  elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@mcmaster\.ca$/', $email)) {
    $_SESSION['register_error'] = "Please enter a valid McMaster email address.";
  }
  // Validate password complexity
  elseif (strlen($password) < 8) {
    $_SESSION['register_error'] = "Password must be at least 8 characters.";
  }
  elseif (!preg_match('/[A-Z]/', $password)) {
    $_SESSION['register_error'] = "Password must contain at least one uppercase letter.";
  }
  elseif (!preg_match('/[a-z]/', $password)) {
    $_SESSION['register_error'] = "Password must contain at least one lowercase letter.";
  }
  elseif (!preg_match('/[0-9]/', $password)) {
    $_SESSION['register_error'] = "Password must contain at least one number.";
  }
  elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
    $_SESSION['register_error'] = "Password must contain at least one special character.";
  }
  // Validate password confirmation
  elseif ($password !== $confirm_password) {
    $_SESSION['register_error'] = "Passwords do not match.";
  }
  // Validate terms agreement
  elseif (!$terms) {
    $_SESSION['register_error'] = "You must agree to the Terms of Service and Privacy Policy.";
  }
  else {
    // All validation passed, proceed with registration
    require_once 'includes/config/database.php';
    require_once 'includes/classes/User.php';
    $database = new Database();
    $conn = $database->getConnection();

    $user = new User($conn);
    $result = $user->register($username, $email, $password);

    if ($result['success']) {
      $_SESSION['login_success'] = "Registration successful! You can now log in.";
      redirect("login.php");
    } else {
      $_SESSION['register_error'] = $result["message"];
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
    <a href="index.php">
      <h1>CodeForum</h1>
    </a>
    <div id="nav-button-container">
      <a href="register.php"><button class="account">Register</button></a>
      <a href="login.php"><button class="account">Log In</button></a>
    </div>
  </header>
  
  <div class="auth-container">
    <div class="auth-box register-box">
      <div class="auth-header">
        <h2>Create Your Account</h2>
        <p>Join the discussion with fellow CS students</p>
      </div>
      
      <?php if (isset($_SESSION['register_error'])): ?>
        <div class="error-message">
          <i class="fas fa-exclamation-circle"></i>
          <?php echo $_SESSION['register_error']; ?>
        </div>
        <?php unset($_SESSION['register_error']); ?>
      <?php endif; ?>

      <form id="register-form" class="auth-form" action="register.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        
        <div class="form-group">
          <label for="username">Username</label>
          <div class="input-with-icon">
            <i class="fas fa-user"></i>
            <input type="text" id="username" name="username" placeholder="Choose a username" required>
          </div>
          <div class="input-help">Username must be between 3-20 characters</div>
        </div>
        
        <div class="form-group">
          <label for="email">Email</label>
          <div class="input-with-icon">
            <i class="fas fa-envelope"></i>
            <input type="email" id="email" name="email" placeholder="Your McMaster email" required pattern=".*@mcmaster\.ca$">
          </div>
          <div class="input-help">Must be a valid McMaster email address</div>
        </div>
        
        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-with-icon">
            <i class="fas fa-lock"></i>
            <input type="password" id="password" name="password" placeholder="Create a strong password" required minlength="8">
            <button type="button" class="toggle-password" aria-label="Toggle password visibility">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>
        
        <div class="form-group">
          <label for="confirm-password">Confirm Password</label>
          <div class="input-with-icon">
            <i class="fas fa-lock"></i>
            <input type="password" id="confirm-password" name="confirm_password" placeholder="Confirm your password" required>
          </div>
        </div>
        
        <div class="password-requirements">
          <p>Password must include:</p>
          <ul>
            <li id="length-check"><i class="fas fa-times-circle"></i>At least 8 characters</li>
            <li id="uppercase-check"><i class="fas fa-times-circle"></i>One uppercase letter</li>
            <li id="lowercase-check"><i class="fas fa-times-circle"></i>One lowercase letter</li>
            <li id="number-check"><i class="fas fa-times-circle"></i>One number</li>
            <li id="special-check"><i class="fas fa-times-circle"></i>One special character</li>
          </ul>
        </div>
        
        <div class="form-group terms">
          <input type="checkbox" id="terms" name="terms" required>
          <label for="terms">I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a></label>
        </div>
        
        <button type="submit" class="auth-button">Create Account</button>
      </form>
      
      <div class="auth-footer">
        <p>Already have an account? <a href="login.php">Log In</a></p>
      </div>
    </div>
  </div>

  <?php include 'includes/partials/footer.php'; ?>
</body>

</html>