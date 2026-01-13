</div><!-- End of .content -->

  <footer>
    <div class="footer-content">
      <div class="footer-logo">
        <h3>CodeForum</h3>
        <p>A discussion platform for Computer Science students at McMaster</p>
      </div>
      <div class="footer-links">
        <ul>
          <li><a href="#">About</a></li>
          <li><a href="#">Privacy Policy</a></li>
          <li><a href="#">Terms of Use</a></li>
          <li><a href="#">Contact</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; <?php echo date('Y'); ?> Team Leibniz. All rights reserved.</p>
    </div>
  </footer>

  <?php
  // Include additional scripts if specified
  if (isset($extra_scripts)):
    if (is_array($extra_scripts)):
      foreach ($extra_scripts as $script):
        echo '<script type="module" src="' . htmlspecialchars($script) . '" defer></script>';
      endforeach;
    else:
      echo $extra_scripts;
    endif;
  endif;
  ?>
  <script src="assets/js/dropdown.js" defer></script>
</body>

</html>