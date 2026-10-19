<?php
/**
 * Task 5 — catalogue with AJAX search and filtering.
 *
 * The page renders a server-side list so it works without JavaScript, then
 * api/search-courses.php takes over for instant filtering. Both paths share the
 * same SQL builder in lib/CourseQuery.php, so the two views can never disagree.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/lib/CourseQuery.php';

$db       = Database::instance($APEX_CONFIG, 'db_lms');
$criteria = CourseQuery::fromRequest();

$categories = $db->all('SELECT id, name FROM categories ORDER BY name');
$result     = (new CourseQuery($db))->find($criteria);

$enrolled = [];
if (is_logged_in()) {
    foreach ($db->all('SELECT course_id FROM enrollments WHERE user_id = ?', [current_user_id()]) as $r) {
        $enrolled[(int) $r['course_id']] = true;
    }
}

render_header('Courses', true, lms_nav());
?>
<div class="wrap">
  <?php render_flashes(); ?>

  <div class="section-head">
    <div>
      <h1>Courses</h1>
      <p class="muted" id="result-count"><?= $result['total'] ?> course<?= $result['total'] === 1 ? '' : 's' ?></p>
    </div>

    <form id="course-search" class="shop-search" role="search" data-endpoint="api/search-courses.php">
      <label class="visually-hidden" for="q">Search courses</label>
      <input type="search" id="q" name="q" value="<?= e($criteria['q']) ?>"
             placeholder="Search title or summary" maxlength="80">

      <label class="visually-hidden" for="cat">Category</label>
      <select id="cat" name="category">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $criteria['category'] === (int) $c['id'] ? 'selected' : '' ?>>
            <?= e((string) $c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label class="visually-hidden" for="level">Level</label>
      <select id="level" name="level">
        <option value="">All levels</option>
        <?php foreach (['beginner', 'intermediate', 'advanced'] as $lv): ?>
          <option value="<?= e($lv) ?>" <?= $criteria['level'] === $lv ? 'selected' : '' ?>><?= e(ucfirst($lv)) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="visually-hidden" for="max">Maximum price</label>
      <select id="max" name="max_price">
        <option value="">Any price</option>
        <option value="0" <?= $criteria['max_price'] === 0 && $criteria['max_price'] !== null ? 'selected' : '' ?>>Free only</option>
        <option value="100000" <?= $criteria['max_price'] === 100000 ? 'selected' : '' ?>>Under ₹1,000</option>
        <option value="200000" <?= $criteria['max_price'] === 200000 ? 'selected' : '' ?>>Under ₹2,000</option>
      </select>

      <button class="btn" type="submit">Search</button>
      <a class="btn btn-ghost" href="index.php" id="clear-filters">Clear</a>
    </form>
  </div>

  <div class="course-grid" id="course-grid">
    <?php require __DIR__ . '/partials/course-cards.php'; ?>
  </div>

  <?php if ($result['pages'] > 1): ?>
    <nav class="pagination-nav" aria-label="Pagination">
      <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
        <a class="btn btn-sm <?= $p === $criteria['page'] ? '' : 'btn-ghost' ?>"
           href="<?= e(CourseQuery::url($criteria + ['page' => $p])) ?>"><?= $p ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>

<script src="assets/js/search.js" defer></script>
<?php render_footer(); ?>
