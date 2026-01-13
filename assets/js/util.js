/**
 * Utility Functions
 * 
 * Reusable utility functions for client-side operations
 */

/**
 * Sanitize HTML content to prevent XSS
 * 
 * @param {string} input - Raw string that might contain HTML
 * @return {string} Sanitized HTML string
 */
export function sanitize_html(input) {
  let div = document.createElement('div');
  div.textContent = input;
  return div.innerHTML;
}

/**
 * Format post content with Markdown-like syntax
 * 
 * @param {string} content - Raw post content
 * @return {string} Formatted HTML content
 */
export function format_post_content(content) {
  // First sanitize the content
  content = sanitize_html(content);

  // Bold formatting
  content = content.replace(/\*\*(.*?)\*\*/gs, '<strong>$1</strong>');
  content = content.replace(/__(.*?)__/gs, '<strong>$1</strong>');

  // Italic formatting
  content = content.replace(/\*(.*?)\*/gs, '<em>$1</em>');
  content = content.replace(/_(.*?)_/gs, '<em>$1</em>');

  // Link formatting
  content = content.replace(/\[(.*?)\]\((.*?)\)/gs, '<a href="$2">$1</a>');

  // Headings
  content = content.replace(/^(#)(.*?)$/gm, '<h1>$2</h1>');
  content = content.replace(/^(##)(.*?)$/gm, '<h2>$2</h2>');
  content = content.replace(/^(###)(.*?)$/gm, '<h3>$2</h3>');
  content = content.replace(/^(####)(.*?)$/gm, '<h4>$2</h4>');
  content = content.replace(/^(#####)(.*?)$/gm, '<h5>$2</h5>');
  content = content.replace(/^(######)(.*?)$/gm, '<h6>$2</h6>');

  // Lists (unordered)
  content = content.replace(/^\s*[-*]\s+(.*?)$/gm, '<ul><li>$1</li></ul>');

  // Lists (ordered)
  content = content.replace(/^\s*\d+\.\s+(.*?)$/gm, '<ol><li>$1</li></ol>');

  // Code (inline)
  content = content.replace(/\`(.*?)\`/gs, '<code>$1</code>');

  // Paragraphs and line breaks
  content = content.replace(/\n\n/g, '</p><p>');
  content = '<p>' + content + '</p>';
  content = content.replace(/\n/g, '<br>');

  // Auto-linkify URLs
  content = linkify(content);

  return content;
}

/**
 * Convert plain URLs in text to clickable links
 * 
 * @param {string} text - Text potentially containing URLs
 * @return {string} Text with URLs converted to HTML links
 */
export function linkify(text) {
  return text.replace(
    /(https?:\/\/[^\s<]+)/gi,
    '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
  );
}

/**
 * Debounce function to limit execution rate
 * 
 * @param {Function} func - Function to debounce
 * @param {number} wait - Milliseconds to wait
 * @return {Function} Debounced function
 */
export function debounce(func, wait) {
  let timeout;
  return function(...args) {
    const context = this;
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(context, args), wait);
  };
}

/**
 * Format date to relative time (e.g., "2 hours ago")
 * 
 * @param {Date|string} date - Date to format
 * @return {string} Relative time string
 */
export function timeAgo(date) {
  const now = new Date();
  const past = new Date(date);
  const seconds = Math.floor((now - past) / 1000);
  
  const intervals = {
    year: 31536000,
    month: 2592000,
    week: 604800,
    day: 86400,
    hour: 3600,
    minute: 60,
    second: 1
  };
  
  for (const [unit, secondsInUnit] of Object.entries(intervals)) {
    const interval = Math.floor(seconds / secondsInUnit);
    
    if (interval >= 1) {
      return interval === 1 ? `1 ${unit} ago` : `${interval} ${unit}s ago`;
    }
  }
  
  return 'just now';
}