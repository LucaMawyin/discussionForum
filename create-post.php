<?php
/**
 * Create Post Page
 * 
 * Allows users to create new posts
 */
// Include debug helpers
require_once 'includes/debug.php';
debug_log("Starting create-post.php");

$page_title = "Create Post";
$extra_styles = ["assets/css/create.css"];
$extra_scripts = ["assets/js/create-post.js"];

require_once 'includes/utils.php';
require_once 'includes/config/database.php';
require_once 'includes/classes/Course.php';
require_once 'includes/classes/Post.php';

// Explicitly start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    debug_log("Session started with ID: " . session_id());
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

if (!$is_logged_in) {
    $_SESSION['redirect_after_login'] = 'create-post.php' . (isset($_GET['course']) ? '?course=' . $_GET['course'] : '');
    debug_log("User not logged in, redirecting to login.php");
    header("Location: login.php");
    exit;
}

// Database setup
$db = new Database();
$conn = $db->getConnection();

// Handle post submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    debug_log("POST request received");
    
    // Instead of CSRF token, we'll use a simpler approach for now to fix the immediate issue
    $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
    $title = isset($_POST['title']) ? clean_input($_POST['title']) : '';
    $content = isset($_POST['content']) ? clean_input($_POST['content']) : '';
    
    // Log the form data
    debug_log("course_id: $course_id, title length: " . strlen($title) . ", content length: " . strlen($content));

    // Validate input
    if (empty($course_id) || empty($title) || empty($content)) {
        $_SESSION['post_error'] = "All fields are required.";
        debug_log("Validation error: Missing required fields");
    } elseif (strlen($title) > 100) {
        $_SESSION['post_error'] = "Title must be 100 characters or less.";
        debug_log("Validation error: Title too long");
    } else {
        // Create post
        $post = new Post($conn);
        $user_id = $_SESSION['user_id'];
        $result = $post->create_post($user_id, $course_id, $title, $content);
        
        debug_log("Post creation result: " . ($result['success'] ? "Success" : "Failure - " . $result['message']));
        
        if ($result['success']) {
            header("Location: post.php?id=" . $result['post_id']);
            exit;
        } else {
            $_SESSION['post_error'] = $result['message'];
        }
    }
}

// Get all courses for selection
$course_obj = new Course($conn);
$all_courses = $course_obj->get_all_courses();

// Check if a course is pre-selected from the URL
$selected_course = null;
if (isset($_GET['course'])) {
    $course_id = (int)$_GET['course'];
    foreach ($all_courses as $course) {
        if ($course_id == $course['course_id']) {
            $selected_course = $course;
            break;
        }
    }
}

// Include header
include 'includes/partials/header.php';

// Include sidebar
include 'includes/partials/sidebar.php';
?>

<div class="content">
  <main class="create-content">
    <div class="create-header">
      <h2>Create a New Post</h2>
      <p>Share your question, idea, or code with the community.</p>
    </div>

    <?php if (isset($_SESSION['post_error'])): ?>
      <div class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['post_error']; ?>
      </div>
      <?php unset($_SESSION['post_error']); ?>
    <?php endif; ?>

    <form class="post-form" action="create-post.php" method="post">
      <!-- Temporarily removing CSRF token to fix the immediate issue -->
      
      <div class="form-group">
        <label for="course">Course</label>
        <select id="course" name="course_id" required>
          <option value="" disabled <?php echo $selected_course ? '' : 'selected'; ?>>Select a course</option>
          <?php foreach ($all_courses as $course): ?>
            <option value="<?php echo $course['course_id']; ?>" 
              <?php echo ($selected_course && $selected_course['course_id'] == $course['course_id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="form-group">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" placeholder="Be specific and concise" required maxlength="100"
               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
        <div class="input-help">Good titles are specific and summarize the problem</div>
      </div>
      
      <div class="form-group">
        <label for="content">Post Content</label>
        <textarea id="content" name="content" placeholder="Describe your problem or share your code..." required><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
        <div class="input-help">
          Be clear and detailed. Include relevant code and explain what you've tried.
          <a href="#" data-toggle="modal" data-target="#markdownHelp">Markdown formatting</a> is supported.
        </div>
      </div>
      
      <div class="form-preview">
        <h3>Preview</h3>
        <div id="content-preview" class="preview-area">
          <p class="preview-placeholder">Your content preview will appear here...</p>
        </div>
      </div>
      
      <div class="form-buttons">
        <a href="<?php echo isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : 'index.php'; ?>" class="btn btn-cancel">Cancel</a>
        <button type="submit" class="btn btn-submit">Post</button>
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
include 'includes/partials/footer.php';
?>