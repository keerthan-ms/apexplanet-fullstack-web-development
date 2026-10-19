<?php
/**
 * Task 5 — "My learning": the student's enrolments and progress.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();

$db = Database::instance($APEX_CONFIG, 'db_lms');

$rows = $db->all(
    "SELECT e.id, e.status, e.progress_percent, e.enrolled_at, e.completed_at,
            c.id AS course_id, c.title, c.code, c.level, c.duration_min,
            cat.name AS category,
            (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lesson_count
     FROM enrollments e
     JOIN courses c ON c.id = e.course_id
     JOIN categories cat ON cat.id = c.category_id
     WHERE e.user_id = ? AND e.status <> 'cancelled'
     ORDER BY e.enrolled_at DESC, e.id DESC",
    [current_user_id()]
);

$active    = array_values(array_filter($rows, static fn ($r) => $r['status'] === 'active'));
$completed = array_values(array_filter($rows, static fn ($r) => $r['status'] === 'completed'));

$avgProgress = $active
    ? (int) round(array_sum(array_map(static fn ($r) => (int) $r['progress_percent'], $active)) / count($active))
    : 0;

render_header('My learning', true, lms_nav());
?>
<div class="wrap">
  <?php render_flashes(); ?>
  <h1>My learning</h1>

  <div class="grid-3">
    <div class="card stat"><b><?= count($active) ?></b><span>In progress</span></div>
    <div class="card stat"><b><?= count($completed) ?></b><span>Completed</span></div>
    <div class="card stat"><b><?= $avgProgress ?>%</b><span>Average progress</span></div>
  </div>

  <?php if (!$rows): ?>
    <div class="card">
      <p class="muted">You are not enrolled in anything yet.</p>
      <a class="btn" href="index.php">Browse courses</a>
    </div>
  <?php endif; ?>

  <?php foreach (['active' => 'In progress', 'completed' => 'Completed'] as $status => $heading): ?>
    <?php $list = $status === 'active' ? $active : $completed; ?>
    <?php if (!$list) { continue; } ?>
    <div class="section-head"><h2 class="h4"><?= e($heading) ?></h2></div>
    <div class="grid">
      <?php foreach ($list as $r): ?>
        <div class="card">
          <div class="row">
            <span class="badge"><?= e((string) $r['category']) ?></span>
            <span class="badge badge-alt"><?= e(ucfirst((string) $r['level'])) ?></span>
          </div>
          <h3 class="h5 mt-sm"><?= e((string) $r['title']) ?></h3>
          <p class="muted small mono"><?= e((string) $r['code']) ?> · <?= (int) $r['lesson_count'] ?> lessons</p>

          <div class="progress" role="progressbar" aria-label="Progress for <?= e((string) $r['title']) ?>"
               aria-valuenow="<?= (int) $r['progress_percent'] ?>" aria-valuemin="0" aria-valuemax="100">
            <i style="width: <?= (int) $r['progress_percent'] ?>%"></i>
          </div>
          <p class="small muted">
            <?= (int) $r['progress_percent'] ?>% complete
            <?php if ($r['completed_at']): ?> · finished <?= e((string) $r['completed_at']) ?><?php endif; ?>
          </p>

          <div class="row mt-sm">
            <a class="btn btn-sm" href="course.php?id=<?= (int) $r['course_id'] ?>">Continue</a>
            <form method="post" action="enrol.php" class="inline-form"
                  data-confirm="Leave &quot;<?= e((string) $r['title']) ?>&quot;?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="leave">
              <input type="hidden" name="course_id" value="<?= (int) $r['course_id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">Leave</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php render_footer(); ?>
