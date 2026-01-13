<?php
/**
 * Moderator Panel
 * 
 * Allows moderators to manage posts and comments
 */
require_once 'includes/config/database.php';
require_once 'includes/utils.php';
require_once 'includes/classes/User.php';
require_once 'includes/classes/Post.php';
require_once 'includes/classes/Course.php';

ensure_session_started();

// Redirect if not logged in
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = 'moderator.php';
    redirect("login.php");
    exit();
}

// Redirect if not moderator or admin
if (!is_moderator_or_above()) {
    redirect("index.php");
    exit();
}

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();
$user = new User($conn, $_SESSION['user_id']);

// Set the active tab
$valid_tabs = ['posts'];
$tab = isset($_GET['tab']) && in_array($_GET['tab'], $valid_tabs) ? $_GET['tab'] : 'posts';

// Get recent posts
$post = new Post($conn);
$recent_posts = $post->get_admin_posts(20);

// Page title
$page_title = 'Moderator Panel';

// Include header
include 'includes/partials/header.php';
?>

<div class="container mt-4">
    <h1>Moderator Panel</h1>
    
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'posts' ? 'active' : ''; ?>" href="moderator.php?tab=posts">Manage Posts</a>
        </li>
        <?php if (is_admin()): ?>
        <li class="nav-item">
            <a class="nav-link" href="admin.php">Admin Dashboard</a>
        </li>
        <?php endif; ?>
    </ul>
    
    <?php if (isset($_SESSION['moderator_success'])): ?>
    <div class="alert alert-success">
        <?php echo $_SESSION['moderator_success']; ?>
    </div>
    <?php unset($_SESSION['moderator_success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['moderator_error'])): ?>
    <div class="alert alert-error">
        <?php echo $_SESSION['moderator_error']; ?>
    </div>
    <?php unset($_SESSION['moderator_error']); ?>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h5>Recent Posts</h5>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_posts as $p): ?>
                    <tr>
                        <td><?php echo $p['post_id']; ?></td>
                        <td><?php echo htmlspecialchars($p['title']); ?></td>
                        <td><?php 
                            $author = new User($conn, $p['user_id']);
                            $author_data = $author->get_data();
                            echo htmlspecialchars($author_data['username'] ?? 'Deleted User'); 
                        ?></td>
                        <td><?php 
                            $post_course = new Course($conn, $p['course_id']);
                            $course_data = $post_course->get_course_data();
                            echo htmlspecialchars($course_data['course_code'] ?? 'Unknown Course'); 
                        ?></td>
                        <td>
                            <?php if ($p['is_pinned']): ?>
                                <span class="badge bg-info">Pinned</span>
                            <?php endif; ?>
                            <?php if ($p['is_closed']): ?>
                                <span class="badge bg-secondary">Closed</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('Y-m-d H:i', strtotime($p['created_at'])); ?></td>
                        <td>
                            <a href="post.php?id=<?php echo $p['post_id']; ?>" class="btn btn-sm btn-info">View</a>
                            <form method="post" action="post.php?id=<?php echo $p['post_id']; ?>" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="post_id" value="<?php echo $p['post_id']; ?>">
                                <button type="submit" name="<?php echo $p['is_pinned'] ? 'unpin_post' : 'pin_post'; ?>" class="btn btn-sm <?php echo $p['is_pinned'] ? 'btn-secondary' : 'btn-success'; ?>">
                                    <?php echo $p['is_pinned'] ? 'Unpin' : 'Pin'; ?>
                                </button>
                                <button type="submit" name="<?php echo $p['is_closed'] ? 'reopen_post' : 'close_post'; ?>" class="btn btn-sm <?php echo $p['is_closed'] ? 'btn-warning' : 'btn-secondary'; ?>">
                                    <?php echo $p['is_closed'] ? 'Reopen' : 'Close'; ?>
                                </button>
                                <button type="submit" name="delete_post" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this post?')">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/partials/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>