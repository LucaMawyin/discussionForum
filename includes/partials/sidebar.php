<?php
/**
 * Sidebar template
 * 
 * Displays list of courses in a sidebar navigation
 */
$current_course = isset($_GET['id']) ? (int)$_GET['id'] : null;
?>

<nav class="communities">
  <h2>Courses</h2>
  <ul>
    <?php if (empty($all_courses)): ?>
      <li class="loading-placeholder">No courses available</li>
    <?php else: ?>
      <?php foreach ($all_courses as $course): ?>
        <li>
          <a href="community.php?id=<?php echo $course['course_id']; ?>"
             <?php if ($current_course == $course['course_id']) echo "class='active'"; ?>>
            <?php echo htmlspecialchars($course['course_code']); ?>
          </a>
        </li>
      <?php endforeach; ?>
    <?php endif; ?>
  </ul>
  
  <?php if ($is_admin): ?>
  <div class="sidebar-actions">
    <a href="admin.php?tab=courses" class="btn btn-outline btn-sm">
      <i class="fas fa-plus"></i> Manage Courses
    </a>
  </div>
  <?php endif; ?>
</nav>