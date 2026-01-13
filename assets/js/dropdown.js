/**
 * Dropdown Menu Functionality
 * 
 * Handles dropdown menu behavior for the navigation
 */
document.addEventListener('DOMContentLoaded', function() {
  // Setup dropdown toggles
  function setupDropdowns() {
    const toggles = document.querySelectorAll('.dropdown-toggle');
    
    toggles.forEach(toggle => {
      toggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const dropdown = this.closest('.dropdown');
        
        // Toggle current dropdown
        dropdown.classList.toggle('show');
        
        // Close other dropdowns
        document.querySelectorAll('.dropdown.show').forEach(d => {
          if (d !== dropdown) d.classList.remove('show');
        });
      });
    });
    
    // Prevent clicks inside dropdown from closing it
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
      menu.addEventListener('click', function(e) {
        e.stopPropagation();
      });
    });
    
    // Close all dropdowns when clicking outside
    document.addEventListener('click', function() {
      document.querySelectorAll('.dropdown.show').forEach(dropdown => {
        dropdown.classList.remove('show');
      });
    });
  }
  
  // Initialize dropdowns
  setupDropdowns();
  
  // Add touch device detection for mobile-specific behavior
  if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
    document.body.classList.add('touch-device');
  }
});