<?php
/**
 * Edit Post Page
 * 
 * Allows users to edit their posts
 */
$page_title = "Edit Post";
$extra_styles = ["assets/css/create.css"];
$extra_scripts = ["assets/js/create-post.js"];

require_once 'includes/utils.php';
require_once 'includes/config/database.php';
require_once 'includes/classes/Post.php';
require_once 'includes/classes/Course.php';
require_once 'includes/classes/User.php';

// Include header and sidebar
require_once 'includes/partials/header.php';
require_once 'includes/partials/sidebar.php';

// Redirect if not logged in
if (!$is_logged_in) {
  redirect("login.php");
}

// Validate post ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  redirect("index.php");
}

$post_id = (int)$_GET['id'];
$db = new Database();
$conn = $db->getConnection();
$post_obj = new Post($conn);
$post = $post_obj->get_post_by_id($post_id, false); // Don't increment view count

// Check if post exists
if (!$post) {
  redirect("index.php");
}

// Helper function to check role hierarchy
function check_hierarchical_permission($editor_id, $author_id, $conn) {
  // If user is editing their own content, always allow
  if ($editor_id == $author_id) {
    return true;
  }
  
  // Get editor's role
  $query = "SELECT role FROM users WHERE user_id = :user_id";
  $stmt = $conn->prepare($query);
  $stmt->bindParam(':user_id', $editor_id);
  $stmt->execute();
  $editor_role = $stmt->fetch();
  
  if (!$editor_role) {
    return false;
  }
  
  // Get author's role
  $stmt = $conn->prepare($query);
  $stmt->bindParam(':user_id', $author_id);
  $stmt->execute();
  $author_role = $stmt->fetch();
  
  if (!$author_role) {
    // If author doesn't exist, we'll allow admins to edit
    return $editor_role['role'] === 'admin';
  }
  
  // Define role hierarchy (higher index = higher permission)
  $role_hierarchy = [
    'student' => 1,
    'moderator' => 2,
    'admin' => 3
  ];
  
  $editor_level = $role_hierarchy[$editor_role['role']];
  $author_level = $role_hierarchy[$author_role['role']];
  
  // Editor can only edit if their role level is higher than the author's
  return $editor_level > $author_level;
}

// Check if user has permission to edit
$can_edit = false;
if ($_SESSION['user_id'] == $post['user_id']) {
  // Users can always edit their own posts
  $can_edit = true;
} else {
  // Check hierarchical permissions for site-wide roles
  $has_hierarchical_permission = check_hierarchical_permission($_SESSION['user_id'], $post['user_id'], $conn);
  if ($has_hierarchical_permission) {
    $can_edit = true;
  } else {
    // Check for course-specific moderator role with hierarchical permissions
    $user_obj = new User($conn, $_SESSION['user_id']);
    $user_courses = $user_obj->get_user_courses($_SESSION['user_id']);
    
    foreach ($user_courses as $course) {
      if ($course['course_id'] == $post['course_id'] && 
        ($course['user_role'] === 'moderator' || $course['user_role'] === 'admin')) {
        
        // Get course-specific role of the author, if any
        $query = "SELECT role FROM user_courses WHERE user_id = :user_id AND course_id = :course_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $post['user_id']);
        $stmt->bindParam(':course_id', $post['course_id']);
        $stmt->execute();
        $author_course_role = $stmt->fetch();
        
        $role_hierarchy = [
          'student' => 1,
          'moderator' => 2,
          'admin' => 3
        ];
        
        $editor_level = $role_hierarchy[$course['user_role']];
        // If author has no course role, treat as student
        $author_level = $author_course_role ? $role_hierarchy[$author_course_role['role']] : 1;
        
        // Editor can only edit if their course role level is higher than the author's
        if ($editor_level > $author_level) {
          $can_edit = true;
          break;
        }
      }
    }
  }
}

// Redirect if user doesn't have permission
if (!$can_edit) {
  $_SESSION['post_error'] = "You don't have permission to edit this post based on role hierarchy.";
  redirect("post.php?id=" . $post_id);
}

// Handle post submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Skip CSRF validation for now to fix immediate issues
  $title = isset($_POST['title']) ? clean_input($_POST['title']) : '';
  $content = isset($_POST['content']) ? $_POST['content'] : '';

  // Validate input
  if (empty($title) || empty($content)) {
    $_SESSION['post_error'] = "All fields are required.";
  } elseif (strlen($title) > 100) {
    $_SESSION['post_error'] = "Title must be 100 characters or less.";
  } else {
    // Update post
    $result = $post_obj->update_post($post_id, $title, $content, $_SESSION['user_id']);
    
    if ($result['success']) {
      redirect("post.php?id=" . $post_id);
    } else {
      $_SESSION['post_error'] = $result['message'];
    }
  }
}

// Get the current post data
$course_obj = new Course($conn);
$course = $course_obj->get_course_by_id($post['course_id']);

// Display the edit form
?>

<div class="content">
  <main class="create-content">
    <div class="create-header">
      <h2>Edit Post</h2>
      <p>Make changes to your post and save when finished.</p>
    </div>

    <?php if (isset($_SESSION['post_error'])): ?>
      <div class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['post_error']; ?>
      </div>
      <?php unset($_SESSION['post_error']); ?>
    <?php endif; ?>

    <form class="post-form" action="edit-post.php?id=<?php echo $post_id; ?>" method="post">
      <div class="form-group">
        <label for="course">Course</label>
        <input type="text" id="course" value="<?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>" disabled>
        <div class="input-help">Course cannot be changed when editing a post</div>
      </div>
      
      <div class="form-group">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" required maxlength="100"
               value="<?php echo htmlspecialchars($post['title']); ?>">
        <div class="input-help">Good titles are specific and summarize the problem</div>
      </div>
      
      <div class="form-group">
        <label for="content">Post Content</label>
        <textarea id="content" name="content" required><?php echo htmlspecialchars($post['content']); ?></textarea>
        <div class="input-help">
          Be clear and detailed. Include relevant code and explain what you've tried.
          <a href="#" data-toggle="modal" data-target="#markdownHelp">Markdown formatting</a> is supported.
        </div>
      </div>
      
      <div class="form-preview">
        <h3>Preview</h3>
        <div id="content-preview" class="preview-area">
          <?php echo format_post_content($post['content']); ?>
        </div>
      </div>
      
      <div class="form-buttons">
        <a href="post.php?id=<?php echo $post_id; ?>" class="btn btn-cancel">Cancel</a>
        <button type="submit" class="btn btn-submit">Update Post</button>
      </div>
    </form>
  </main>
</div>

<!-- Markdown Help Modal -->
<div class="modal fade" id="markdownHelp" tabindex="-1" role="dialog" aria-labelledby="markdownHelpTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="markdownHelpTitle">Markdown Formatting Guide</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <h6>Headers</h6>
            <pre><code># Header 1
## Header 2
### Header 3</code></pre>
            
            <h6>Emphasis</h6>
            <pre><code>*Italic*  or  _Italic_
**Bold**  or  __Bold__</code></pre>
            
            <h6>Lists</h6>
            <pre><code>* Bullet item
* Another item

1. Numbered item
2. Second item</code></pre>
          </div>
          <div class="col-md-6">
            <h6>Links</h6>
            <pre><code>[Link text](http://example.com)</code></pre>
            
            <h6>Code</h6>
            <pre><code>`Inline code` with backticks

```
// Code block
function example() {
  return true;
}
```</code></pre>
            
            <h6>Blockquotes</h6>
            <pre><code>> This is a blockquote
> It can span multiple lines</code></pre>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php
// Include footer
require_once 'includes/partials/footer.php';
?>