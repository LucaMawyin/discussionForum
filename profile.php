<?php
/**
 * User Profile Page
 * 
 * Displays and manages user profile information
 */
$page_title = 'User Profile';
$extra_styles = ['assets/css/profile.css'];
require_once 'includes/utils.php';
require_once 'includes/config/database.php';
require_once 'includes/classes/User.php';
require_once 'includes/classes/Post.php';
require_once 'includes/classes/Comment.php';

// Redirect if not logged in
if (!is_logged_in()) {
  $_SESSION['redirect_after_login'] = 'profile.php';
  redirect("login.php");
  exit();
}

// Include header
include 'includes/partials/header.php';

// Include sidebar
include 'includes/partials/sidebar.php';

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_role = $_SESSION['role'];
$user = new User($conn);
$user_data = $user->get_user_by_id($user_id);

// Get posts by user
$post_obj = new Post($conn);
$user_posts_data = $post_obj->get_posts_by_user($user_id, 1, 5);
$user_posts = $user_posts_data['posts'];

// Get comments by user
$comment_obj = new Comment($conn);
$user_comments = $comment_obj->get_user_comments($user_id, 5);

// Handle profile update
$update_success = false;
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
  // Validate CSRF token
  if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    $update_error = "Invalid form submission. Please try again.";
  } else {
    $new_email = $_POST['email'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Basic validation
    if (empty($new_email)) {
      $update_error = "Email is required";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
      $update_error = "Invalid email format";
    } elseif (!preg_match('/@mcmaster\.ca$/', $new_email)) {
      $update_error = "Please use a valid McMaster email address";
    } elseif (empty($current_password)) {
      $update_error = "Current password is required to make changes";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
      $update_error = "New passwords do not match";
    } elseif (!empty($new_password) && strlen($new_password) < 8) {
      $update_error = "New password must be at least 8 characters";
    } else {
      $result = $user->update_profile($user_id, $new_email, $current_password, $new_password);
      if ($result['success']) {
        $update_success = true;
        $user_data = $user->get_user_by_id($user_id); // Refresh user data
        
        // Update session email if it changed
        if ($_SESSION['email'] !== $new_email) {
          $_SESSION['email'] = $new_email;
        }
      } else {
        $update_error = $result['message'];
      }
    }
  }
}
?>

<main class="home-content">
  <div class="profile-container">
    <div class="profile-header">
      <div class="profile-avatar">
        <?php echo get_user_icon_svg(32, '#6c757d'); ?>
      </div>
      <div class="profile-info">
        <h2><?php echo htmlspecialchars($username); ?></h2>
        <p><i class="fas fa-user-tag"></i> <?php echo ucfirst(htmlspecialchars($user_role)); ?></p>
        <p><i class="fas fa-calendar-alt"></i> Member since: <?php echo date('F j, Y', strtotime($user_data['registration_date'])); ?></p>
      </div>
    </div>
    
    <?php if ($update_success): ?>
    <div class="alert alert-success">
      Profile updated successfully!
    </div>
    <?php endif; ?>
    
    <?php if (!empty($update_error)): ?>
    <div class="alert alert-error">
      <?php echo $update_error; ?>
    </div>
    <?php endif; ?>
    
    <div class="profile-tabs">
      <ul class="nav-tabs">
        <li class="nav-item active" data-tab="activity">Activity</li>
        <li class="nav-item" data-tab="settings">Settings</li>
      </ul>
      
      <div class="tab-content">
        <!-- Activity Tab -->
        <div id="activity" class="tab-pane active">
          <h3>Recent Posts</h3>
          <?php if (count($user_posts) > 0): ?>
            <div class="user-posts">
              <?php foreach ($user_posts as $post): ?>
                <div class="post-item">
                  <h4><a href="post.php?id=<?php echo $post['post_id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a></h4>
                  <div class="post-meta">
                    <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($post['course_code']); ?></span>
                    <span><i class="fas fa-calendar"></i> <?php echo get_relative_time($post['created_at']); ?></span>
                    <span><i class="fas fa-comments"></i> <?php echo $post['comment_count']; ?> comments</span>
                  </div>
                </div>
              <?php endforeach; ?>
              <?php if ($user_posts_data['total_pages'] > 1): ?>
                <a href="user-posts.php?id=<?php echo $user_id; ?>" class="btn btn-outline">View All Posts</a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <p class="no-data">No posts yet.</p>
          <?php endif; ?>
          
          <h3>Recent Comments</h3>
          <?php if (count($user_comments) > 0): ?>
            <div class="user-comments">
              <?php foreach ($user_comments as $comment): ?>
                <div class="comment-item">
                  <p><?php echo substr(htmlspecialchars($comment['content']), 0, 100); ?>
                  <?php if (strlen($comment['content']) > 100): ?>...<?php endif; ?></p>
                  <div class="comment-meta">
                    <span>On: <a href="post.php?id=<?php echo $comment['post_id']; ?>"><?php echo htmlspecialchars($comment['post_title']); ?></a></span>
                    <span><i class="fas fa-calendar"></i> <?php echo get_relative_time($comment['created_at']); ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
              <a href="user-comments.php?id=<?php echo $user_id; ?>" class="btn btn-outline">View All Comments</a>
            </div>
          <?php else: ?>
            <p class="no-data">No comments yet.</p>
          <?php endif; ?>
        </div>
        
        <!-- Settings Tab -->
        <div id="settings" class="tab-pane">
          <h3>Profile Settings</h3>
          <form method="post" class="profile-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
              <label for="username">Username</label>
              <input type="text" id="username" value="<?php echo htmlspecialchars($username); ?>" disabled>
              <small>Username cannot be changed</small>
            </div>
            
            <div class="form-group">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
              <small>Must be a valid McMaster email address</small>
            </div>
            
            <div class="form-group">
              <label for="current_password">Current Password</label>
              <input type="password" id="current_password" name="current_password" required>
              <small>Required to save changes</small>
            </div>
            
            <h4>Change Password</h4>
            <div class="form-group">
              <label for="new_password">New Password</label>
              <input type="password" id="new_password" name="new_password">
              <small>Leave empty to keep current password</small>
            </div>
            
            <div class="form-group">
              <label for="confirm_password">Confirm New Password</label>
              <input type="password" id="confirm_password" name="confirm_password">
            </div>
            
            <div class="form-actions">
              <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const tabItems = document.querySelectorAll('.nav-tabs .nav-item');
  const tabPanes = document.querySelectorAll('.tab-pane');
  
  tabItems.forEach(item => {
    item.addEventListener('click', function() {
      const tabId = this.getAttribute('data-tab');
      
      // Remove active class from all tabs and panes
      tabItems.forEach(tab => tab.classList.remove('active'));
      tabPanes.forEach(pane => pane.classList.remove('active'));
      
      // Add active class to current tab and pane
      this.classList.add('active');
      document.getElementById(tabId).classList.add('active');
    });
  });
});
</script>

<?php
// Include footer
include 'includes/partials/footer.php';
?>