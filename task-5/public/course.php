<?php
/**
 * Task 5 — course detail with its lessons, plus enrolment management.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

$db       = Database::instance($APEX_CONFIG, 'db_lms');
$courseId = (int) get('id');

$course = $courseId > 0 ? $db->one(
    'SELECT co.id, co.code, co.title, co.summary, co.description, co.level,
            co.price_cents, co.duration_min, co.is_published, cat.name AS category
     FROM courses co JOIN categories cat ON cat.id = co.category_id
     WHERE co.id = ?',
    [$courseId]
) : null;

if ($course && (int) $course['is_published'] !== 1 && current_user_role() !== 'admin') {
    $course = null;
}

if (!$course) {
    http_response_code(404);
    render_header('Not found', true, lms_nav());
    echo '<div class="wrap"><div class="card"><h1 class="h4">That course is not available</h1>'
       . '<p class="muted">It may have been unpublished.</p>'
       . '<a class="btn" href="index.php">Back to courses</a></div></div>';
    render_footer();
    exit;
}

$lessons = $db->all(
    'SELECT id, position, title, duration_min FROM lessons WHERE course_id = ? ORDER BY position',
    [$courseId]
);

$enrollment = is_logged_in() ? $db->one(
    "SELECT id, status, progress_percent FROM enrollments WHERE user_id = ? AND course_id = ?",
    [current_user_id(), $courseId]
) : null;

render_header((string) $course['title'], true, lms_nav());
?>
<div class="wrap">
  <?php render_flashes(); ?>

  <nav aria-label="Breadcrumb" class="muted small mb">
    <a href="index.php">Courses</a> / <span><?= e((string) $course['category']) ?></span>
  </nav>

  <div class="course-detail">
    <div class="card">
      <div class="row">
        <span class="badge"><?= e((string) $course['category']) ?></span>
        <span class="badge badge-alt"><?= e(ucfirst((string) $course['level'])) ?></span>
      </div>
      <h1 class="h3 mt-sm"><?= e((string) $course['title']) ?></h1>
      <p class="muted small mono"><?= e((string) $course['code']) ?></p>
      <p><?= e((string) $course['description']) ?></p>
      <p class="price"><?= e(lms_money((int) $course['price_cents'])) ?></p>

      <?php if ($enrollment && $enrollment['status'] === 'active'): ?>
        <p class="muted small">You are enrolled · <?= (int) $enrollment['progress_percent'] ?>% complete</p>
      <?php elseif (is_logged_in()): ?>
        <form method="post" action="enrol.php">
          <?= csrf_field() ?>
          <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
          <button class="btn" type="submit">Enrol in this course</button>
        </form>
      <?php else: ?>
        <a class="btn" href="login.php">Sign in to enrol</a>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 class="h5">Syllabus</h2>
      <p class="muted small">
        <?= count($lessons) ?> lesson<?= count($lessons) === 1 ? '' : 's' ?> ·
        <?= (int) round((int) $course['duration_min'] / 60) ?> hours total
      </p>
      <?php if (!$lessons): ?>
        <p class="muted">No lessons have been published yet.</p>
      <?php endif; ?>
      <ol class="lesson-list">
        <?php foreach ($lessons as $l): ?>
          <li>
            <span><?= (int) $l['position'] ?>.</span>
            <span><?= e((string) $l['title']) ?></span>
            <span class="muted small"><?= (int) $l['duration_min'] ?> min</span>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </div>
</div>
<?php render_footer(); ?>
