<?php
/**
 * Format Markdown Service
 * 
 * AJAX endpoint to format markdown content for live preview
 */
require_once 'includes/utils.php';

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the content from the POST data
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    
    // Format the content using the enhanced markdown formatter
    echo format_post_content($content);
    exit;
}

// If not a POST request, redirect to home
header("Location: index.php");
exit;
?>