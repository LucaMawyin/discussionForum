<?php
/**
 * Post Page
 * 
 * Displays individual post and its comments
 */
$page_title = 'View Post';
$extra_styles = ['assets/css/community.css'];

require_once 'includes/utils.php';
require_once 'includes/config/database.php';
require_once 'includes/classes/Post.php';
require_once 'includes/classes/Comment.php';
require_once 'includes/classes/User.php';

// Handle comment submission directly in post.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['comment_error'] = "Invalid form submission. Please try again.";
    } else {
        $comment_post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        $content = isset($_POST['comment_content']) ? clean_input($_POST['comment_content']) : '';
        
        if (empty($content)) {
            $_SESSION['comment_error'] = "Comment cannot be empty.";
        } else if (!isset($_SESSION['user_id'])) {
            $_SESSION['comment_error'] = "You must be logged in to comment.";
        } else {
            // Create comment
            $db = new Database();
            $conn = $db->getConnection();
            $comment_obj = new Comment($conn);
            $result = $comment_obj->create_comment($_SESSION['user_id'], $comment_post_id, $content);
            
            if ($result['success']) {
                $_SESSION['comment_success'] = "Comment posted successfully.";
                // Refresh the page to show the new comment
                header("Location: post.php?id=" . $comment_post_id . "#comment-" . $result['comment_id']);
                exit();
            } else {
                $_SESSION['comment_error'] = $result['message'];
            }
        }
    }
    
    // Redirect back to ensure form isn't resubmitted on refresh
    if (isset($_POST['post_id'])) {
        header("Location: post.php?id=" . $_POST['post_id'] . "#comments");
        exit();
    }
}

// Ensure session is started
ensure_session_started();

// Database connection
$db = new Database();
$conn = $db->getConnection();

// Include header
include 'includes/partials/header.php';

// Include sidebar
include 'includes/partials/sidebar.php';

// Validate post ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  echo '<div class="content">';
  echo '<main class="home-content">';
  echo '<div class="error-message">';
  echo '<h2>Invalid Request</h2>';
  echo '<p>The requested post could not be found.</p>';
  echo '<a href="index.php" class="btn btn-primary">Return to Home</a>';
  echo '</div>';
  echo '</main>';
  echo '</div>';
  include 'includes/partials/footer.php';
  exit;
}

// Get post data
$post_id = (int)$_GET['id'];
$post_obj = new Post($conn);
$post = $post_obj->get_post_by_id($post_id);

// Check if post exists
if (!$post) {
  echo '<div class="content">';
  echo '<main class="home-content">';
  echo '<div class="error-message">';
  echo '<h2>Post Not Found</h2>';
  echo '<p>The post you are looking for does not exist or has been removed.</p>';
  echo '<a href="index.php" class="btn btn-primary">Return to Home</a>';
  echo '</div>';
  echo '</main>';
  echo '</div>';
  include 'includes/partials/footer.php';
  exit;
}

// Get comments
$comment_obj = new Comment($conn);
$comments = $comment_obj->get_comments_by_post($post_id);

// Update page title with post title
$page_title = htmlspecialchars($post['title']);

// Explicitly check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Check if user is the post author
$is_author = $is_logged_in && $_SESSION['user_id'] == $post['user_id'];

// Check if user is moderator or admin (site-wide or course-specific)
$can_moderate = false;
if ($is_logged_in) {
  // Check site-wide role
  if (is_moderator_or_above()) {
    $can_moderate = true;
  } else {
    // Check course-specific role
    $user_obj = new User($conn, $_SESSION['user_id']);
    $user_courses = $user_obj->get_user_courses($_SESSION['user_id']);
    
    foreach ($user_courses as $course) {
      if ($course['course_id'] == $post['course_id'] && 
          ($course['user_role'] === 'moderator' || $course['user_role'] === 'admin')) {
        $can_moderate = true;
        break;
      }
    }
  }
}
?>

<main class="home-content">
  <!-- Post Content -->
  <div class="post-content">
    <div class="post-header">
      <h1><?php echo htmlspecialchars($post['title']); ?></h1>
      
      <div class="post-meta">
        <div class="post-author">
          <div class="user-icon">
            <?php echo get_user_icon_svg(32, '#6c757d'); ?>
          </div>
          <span>
            <?php echo htmlspecialchars($post['username']); ?> •
            <?php echo format_date($post['created_at']); ?>
            <?php if ($post['updated_at'] != $post['created_at']): ?>
              • Updated: <?php echo format_date($post['updated_at']); ?>
            <?php endif; ?>
          </span>
        </div>
        
        <div class="post-actions">
          <?php if ($is_author): ?>
            <a href="edit-post.php?id=<?php echo $post_id; ?>" class="btn btn-sm btn-primary">
              <i class="fas fa-edit"></i> Edit
            </a>
          <?php endif; ?>
          
          <?php if ($can_moderate): ?>
            <!-- Moderation actions -->
            <div class="dropdown">
              <button class="btn btn-sm btn-primary dropdown-toggle">
                <i class="fas fa-cog"></i> Moderate
              </button>
              <div class="dropdown-menu">
                <form action="includes/controllers/moderator.php?post_id=<?php echo $post_id; ?>" method="post">
                  <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                  <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                  
                  <?php if ($post['is_pinned']): ?>
                    <button type="submit" name="unpin_post" class="dropdown-item">
                      <i class="fas fa-thumbtack fa-rotate-90"></i> Unpin Post
                    </button>
                  <?php else: ?>
                    <button type="submit" name="pin_post" class="dropdown-item">
                      <i class="fas fa-thumbtack"></i> Pin Post
                    </button>
                  <?php endif; ?>
                  
                  <?php if ($post['is_closed']): ?>
                    <button type="submit" name="reopen_post" class="dropdown-item">
                      <i class="fas fa-lock-open"></i> Reopen Post
                    </button>
                  <?php else: ?>
                    <button type="submit" name="close_post" class="dropdown-item">
                      <i class="fas fa-lock"></i> Close Post
                    </button>
                  <?php endif; ?>
                  
                  <div class="dropdown-divider"></div>
                  
                  <button type="submit" name="delete_post" class="dropdown-item text-danger" 
                          onclick="return confirm('Are you sure you want to delete this post? This action cannot be undone.')">
                    <i class="fas fa-trash"></i> Delete Post
                  </button>
                </form>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <div class="post-status">
        <?php if ($post['is_pinned']): ?>
          <span class="badge bg-warning">Pinned</span>
        <?php endif; ?>
        
        <?php if ($post['is_closed']): ?>
          <span class="badge bg-danger">Closed</span>
        <?php endif; ?>
        
        <span class="badge bg-secondary">
          <i class="fas fa-book"></i> <?php echo htmlspecialchars($post['course_code']); ?>
        </span>
      </div>
    </div>
    
    <div class="post-body">
      <?php echo format_post_content($post['content']); ?>
    </div>
    
    <div class="post-stats">
      <span><i class="fas fa-eye"></i> <?php echo $post['view_count']; ?> views</span>
      <span><i class="fas fa-comment"></i> <?php echo count($comments); ?> comments</span>
    </div>
    
    <!-- Comments Section -->
    <div class="comments-section" id="comments">
      <h3>Comments (<?php echo count($comments); ?>)</h3>
      
      <?php if ($is_logged_in && !$post['is_closed']): ?>
        <!-- Comment Form -->
        <form action="post.php" method="post" class="comment-form">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
          
          <textarea name="comment_content" placeholder="Write your comment here..." required></textarea>
          
          <button type="submit" name="submit_comment" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i> Post Comment
          </button>
        </form>
        
        <?php if (isset($_SESSION['comment_error'])): ?>
          <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['comment_error']; ?>
          </div>
          <?php unset($_SESSION['comment_error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['comment_success'])): ?>
          <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['comment_success']; ?>
          </div>
          <?php unset($_SESSION['comment_success']); ?>
        <?php endif; ?>
        
      <?php elseif ($post['is_closed']): ?>
        <div class="closed-message">
          <i class="fas fa-lock"></i>
          <p>This post is closed. New comments are not allowed.</p>
        </div>
      <?php elseif (!$is_logged_in): ?>
        <div class="login-message">
          <i class="fas fa-user"></i>
          <p>Please <a href="login.php?redirect=post.php?id=<?php echo $post_id; ?>">log in</a> to post a comment.</p>
        </div>
      <?php endif; ?>
      
      <!-- Comments List -->
      <div class="comments-list">
        <?php if (count($comments) > 0): ?>
          <?php foreach ($comments as $comment): ?>
            <div class="comment" id="comment-<?php echo $comment['comment_id']; ?>">
              <div class="comment-header">
                <div class="comment-author">
                  <div class="user-icon">
                    <?php echo get_user_icon_svg(28, '#6c757d'); ?>
                  </div>
                  <span>
                    <?php echo htmlspecialchars($comment['username'] ?: 'Anonymous'); ?> •
                    <?php echo get_relative_time($comment['created_at']); ?>
                  </span>
                </div>
                
                <div class="comment-actions">
                  <?php
                  // Comment author can always delete their own comment
                  $can_delete_comment = $is_logged_in && $_SESSION['user_id'] == $comment['user_id'];
                  
                  // Post author can delete any comment on their post
                  $can_delete_comment = $can_delete_comment || ($is_author && $is_logged_in);
                  
                  // Moderators and admins can delete comments based on role hierarchy
                  $can_delete_comment = $can_delete_comment || $can_moderate;
                  
                  if ($can_delete_comment):
                  ?>
                    <form action="includes/controllers/comment.php" method="post">
                      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                      <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                      <input type="hidden" name="comment_id" value="<?php echo $comment['comment_id']; ?>">
                      
                      <button type="submit" name="delete_comment" class="btn btn-sm btn-danger" 
                              onclick="return confirm('Are you sure you want to delete this comment?')">
                        <i class="fas fa-trash"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
              
              <div class="comment-body">
                <?php echo format_post_content($comment['content']); ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="no-comments">
            <p>No comments yet. Be the first to share your thoughts!</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php
// Include footer
include 'includes/partials/footer.php';
?>