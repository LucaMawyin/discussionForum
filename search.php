<?php
/**
 * Search Page
 * 
 * Displays search results
 */
$page_title = 'Search Results';
$extra_styles = ['assets/css/community.css', 'assets/css/search.css'];

require_once 'includes/utils.php';
require_once 'includes/config/database.php';
require_once 'includes/classes/Post.php';
require_once 'includes/classes/User.php';

// Include header and sidebar
include 'includes/partials/header.php';
include 'includes/partials/sidebar.php';

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];
$total_results = 0;

// Only search if query is provided and has at least 2 characters
if (!empty($query) && strlen($query) >= 2) {
    $db = new Database();
    $conn = $db->getConnection();
    $post_obj = new Post($conn);
    
    // Get search results
    $results = $post_obj->search_posts($query);
    $total_results = count($results);
}
?>

<main class="home-content">
    <div class="content-header">
        <h2>Search Results<?php if (!empty($query)): ?> for "<?php echo htmlspecialchars($query); ?>"<?php endif; ?></h2>
        <div class="search-stats">
            <?php if (!empty($query)): ?>
                <p>Found <?php echo $total_results; ?> <?php echo $total_results == 1 ? 'result' : 'results'; ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($query)): ?>
        <div class="search-instructions">
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>Search for posts</h3>
                <p>Enter keywords to search for posts across all courses</p>
            </div>
        </div>
    <?php elseif ($total_results == 0): ?>
        <div class="empty-state">
            <i class="fas fa-search"></i>
            <h3>No results found</h3>
            <p>Try using different keywords or check your spelling</p>
            <div class="search-tips">
                <h4>Search Tips:</h4>
                <ul>
                    <li>Use specific keywords related to your topic</li>
                    <li>Try searching for a single keyword instead of a phrase</li>
                    <li>Make sure words are spelled correctly</li>
                    <li>Try searching by username, course code, or title</li>
                </ul>
            </div>
        </div>
    <?php else: ?>
        <div class="posts-container">
            <?php foreach ($results as $post): ?>
                <a href="post.php?id=<?php echo $post['post_id']; ?>" class="post-link">
                    <div class="post-tile <?php echo isset($post['is_pinned']) && $post['is_pinned'] ? 'pinned' : ''; ?>">
                        <h3>
                            <?php echo highlight_search_terms($post['title'], $query); ?>
                            <?php if (isset($post['is_pinned']) && $post['is_pinned']): ?>
                                <span class="pinned-badge">Pinned</span>
                            <?php endif; ?>
                        </h3>
                        <div class="post-meta">
                            <div class="post-author">
                                <div class="user-icon">
                                    <?php echo get_user_icon_svg(24, '#6c757d'); ?>
                                </div>
                                <span>
                                    <?php echo htmlspecialchars($post['username']); ?> •
                                    <?php echo get_relative_time($post['created_at']); ?>
                                </span>
                            </div>
                            <div class="post-stats">
                                <span><i class="fas fa-eye"></i> <?php echo $post['view_count']; ?></span>
                                <span><i class="fas fa-comment"></i> <?php echo $post['comment_count']; ?></span>
                            </div>
                        </div>
                        <div class="post-content">
                            <?php 
                            // Get content preview with highlighted search terms
                            $content = strip_tags(html_entity_decode($post['content']));
                            
                            // Try to find a snippet containing the search term
                            $pos = stripos($content, $query);
                            if ($pos !== false) {
                                // Show text around the search term
                                $start = max(0, $pos - 60);
                                $length = 200;
                                if ($start > 0) {
                                    $excerpt = '...' . substr($content, $start, $length) . '...';
                                } else {
                                    $excerpt = substr($content, 0, $length) . '...';
                                }
                            } else {
                                // Just show beginning of content
                                $excerpt = substr($content, 0, 200) . (strlen($content) > 200 ? '...' : '');
                            }
                            
                            echo highlight_search_terms($excerpt, $query);
                            ?>
                        </div>
                        <div class="post-tags">
                            <i class="fas fa-book"></i>
                            <span class="post-tag">
                                <?php echo htmlspecialchars($post['course_code']); ?>
                            </span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include 'includes/partials/footer.php'; ?>