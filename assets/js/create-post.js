/**
 * Create Post Functionality
 * 
 * Handles post creation form interactions and live markdown preview
 */

document.addEventListener("DOMContentLoaded", () => {
  // Get form elements
  const contentTextArea = document.getElementById('content');
  const previewArea = document.getElementById('content-preview');
  const titleInput = document.getElementById('title');
  const courseSelect = document.getElementById('course');
  const submitButton = document.querySelector('button[type="submit"]');

  /**
   * Updates the preview area with formatted content using AJAX
   */
  function updatePreview() {
    if (!contentTextArea || !previewArea) return;

    const content = contentTextArea.value;
    if (content.trim() === '') {
      previewArea.innerHTML = '<p class="preview-placeholder">Your content preview will appear here...</p>';
      return;
    }

    // Make an AJAX call to the server to format the content
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'format-markdown.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onload = function() {
      if (this.status === 200) {
        previewArea.innerHTML = this.responseText;
        
        // Apply syntax highlighting to code blocks
        if (window.Prism) {
          previewArea.querySelectorAll('pre code').forEach(block => {
            window.Prism.highlightElement(block);
          });
        }
      } else {
        previewArea.innerHTML = '<p class="error">Error generating preview.</p>';
      }
    };
    
    xhr.onerror = function() {
      previewArea.innerHTML = '<p class="error">Network error occurred.</p>';
    };
    
    xhr.send('content=' + encodeURIComponent(content));
  }

  /**
   * Debounce function to limit execution rate
   * 
   * @param {Function} func - Function to debounce
   * @param {number} wait - Milliseconds to wait
   * @return {Function} Debounced function
   */
  function debounce(func, wait) {
    let timeout;
    return function(...args) {
      const context = this;
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(context, args), wait);
    };
  }

  /**
   * Validates the form before submission
   */
  function validateForm() {
    let isValid = true;
    let errorMessage = '';
    
    // Validate course selection
    if (!courseSelect.value) {
      courseSelect.classList.add('is-invalid');
      errorMessage = 'Please select a course';
      isValid = false;
    } else {
      courseSelect.classList.remove('is-invalid');
    }
    
    // Validate title
    if (!titleInput.value.trim()) {
      titleInput.classList.add('is-invalid');
      errorMessage = errorMessage || 'Title is required';
      isValid = false;
    } else if (titleInput.value.length > 100) {
      titleInput.classList.add('is-invalid');
      errorMessage = 'Title must be 100 characters or less';
      isValid = false;
    } else {
      titleInput.classList.remove('is-invalid');
    }
    
    // Validate content
    if (!contentTextArea.value.trim()) {
      contentTextArea.classList.add('is-invalid');
      errorMessage = errorMessage || 'Content is required';
      isValid = false;
    } else {
      contentTextArea.classList.remove('is-invalid');
    }
    
    // Show error message if needed
    const existingError = document.querySelector('.error-message');
    if (existingError) {
      existingError.remove();
    }
    
    if (!isValid) {
      const errorDiv = document.createElement('div');
      errorDiv.className = 'error-message';
      errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${errorMessage}`;
      document.querySelector('.create-header').insertAdjacentElement('afterend', errorDiv);
    }
    
    return isValid;
  }

  // Add tab key support to textarea (insert tab instead of changing focus)
  if (contentTextArea) {
    contentTextArea.addEventListener('keydown', function(e) {
      if (e.key === 'Tab') {
        e.preventDefault();
        
        // Get cursor position
        const start = this.selectionStart;
        const end = this.selectionEnd;
        
        // Insert tab at cursor position
        this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
        
        // Move cursor after the inserted tab
        this.selectionStart = this.selectionEnd = start + 4;
      }
    });
  }

  // Set up event listeners
  if (contentTextArea && previewArea) {
    // Create debounced update function to avoid excessive AJAX calls
    const debouncedUpdate = debounce(updatePreview, 300);
    
    // Add event listener for content changes
    contentTextArea.addEventListener('input', debouncedUpdate);
    
    // Initial preview update
    updatePreview();
  }
  
  // Form validation on submit
  const postForm = document.querySelector('.post-form');
  if (postForm) {
    postForm.addEventListener('submit', function(e) {
      if (!validateForm()) {
        e.preventDefault();
      }
    });
  }
  
  // Character counter for title
  if (titleInput) {
    titleInput.addEventListener('input', function() {
      const maxLength = 100;
      const remaining = maxLength - this.value.length;
      
      let counter = document.querySelector('.title-counter');
      if (!counter) {
        counter = document.createElement('div');
        counter.className = 'title-counter input-help';
        this.parentElement.appendChild(counter);
      }
      
      counter.textContent = `${remaining} characters remaining`;
      
      if (remaining < 0) {
        counter.style.color = 'var(--color-error)';
      } else if (remaining < 20) {
        counter.style.color = 'var(--color-warning)';
      } else {
        counter.style.color = 'var(--color-text-muted)';
      }
    });
    
    // Trigger immediately to show initial count
    titleInput.dispatchEvent(new Event('input'));
  }
  
  // Add event listener for markdown help modal
  const markdownLink = document.querySelector('[data-target="#markdownHelp"]');
  if (markdownLink) {
    markdownLink.addEventListener('click', function(e) {
      e.preventDefault();
      
      const modal = document.getElementById('markdownHelp');
      if (modal) {
        // Bootstrap 5 way
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
          const bsModal = new bootstrap.Modal(modal);
          bsModal.show();
        } 
        // Simple show/hide fallback
        else {
          document.body.style.overflow = 'hidden';
          modal.style.display = 'block';
          modal.classList.add('show');
          modal.style.overflowY = 'auto';
          
          // Add close button handler
          const closeButtons = modal.querySelectorAll('[data-dismiss="modal"]');
          closeButtons.forEach(button => {
            button.addEventListener('click', function() {
              modal.style.display = 'none';
              modal.classList.remove('show');
              document.body.style.overflow = 'auto';
            });
          });
        }
      }
    });
  }
});