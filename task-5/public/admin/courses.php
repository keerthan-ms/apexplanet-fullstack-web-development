<?php
/**
 * Task 5 — admin course management (Create, Read, Update, Delete).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_admin();

$db = Database::instance($APEX_CONFIG, 'db_lms');
$errors = [];

$categories = $db->all('SELECT id, name FROM categories ORDER BY name');
$levels = ['beginner', 'intermediate', 'advanced'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = post('action');
    $id     = post_int('id');

    if ($action === 'delete') {
        $enrolled = (int) $db->value('SELECT COUNT(*) FROM enrollments WHERE course_id = ?', [$id]);
        if ($enrolled > 0) {
            // enrollments.course_id is ON DELETE CASCADE, so a real delete would
            // silently erase every student's enrolment. Unpublish instead.
            $db->run('UPDATE courses SET is_published = 0 WHERE id = ?', [$id]);
            flash('success', 'That course has students enrolled in it, so it was unpublished instead of deleted.');
        } else {
            $db->run('DELETE FROM courses WHERE id = ?', [$id]);
            flash('success', 'Course deleted.');
        }
        redirect('courses.php');
    }

    if ($action === 'toggle') {
        $db->run('UPDATE courses SET is_published = 1 - is_published WHERE id = ?', [$id]);
        $now = (int) $db->value('SELECT is_published FROM courses WHERE id = ?', [$id]);
        flash('success', $now === 1 ? 'Course published.' : 'Course unpublished.');
        redirect('courses.php');
    }

    $code        = strtoupper(trim(post('code')));
    $title       = trim(post('title'));
    $summary     = trim(post('summary'));
    $description = trim(post('description'));
    $categoryId  = post_int('category_id');
    $level       = post('level');
    $priceCents  = post_int('price_cents');
    $durationMin = post_int('duration_min');
    $isPublished = post('is_published') === '1' ? 1 : 0;

    if ($code === '' || strlen($code) > 20 || !preg_match('/^[A-Z0-9-]+$/', $code)) {
        $errors['code'] = 'Code is required, up to 20 characters, letters, digits and hyphens only.';
    }
    if ($title === '' || mb_strlen($title) > 140) {
        $errors['title'] = 'Title is required and must be 140 characters or fewer.';
    }
    if ($summary === '' || mb_strlen($summary) > 300) {
        $errors['summary'] = 'Summary is required and must be 300 characters or fewer.';
    }
    if ($description === '') {
        $errors['description'] = 'A description is required.';
    }
    if (!in_array($level, $levels, true)) {
        $errors['level'] = 'Choose beginner, intermediate or advanced.';
    }
    if ($priceCents < 0 || $priceCents > 100000000) {
        $errors['price_cents'] = 'Enter a price in whole paise between 0 and 100000000.';
    }
    if ($durationMin < 0 || $durationMin > 10000) {
        $errors['duration_min'] = 'Duration must be between 0 and 10000 minutes.';
    }
    if (!$db->value('SELECT id FROM categories WHERE id = ?', [$categoryId])) {
        $errors['category_id'] = 'Choose a valid category.';
    }

    // The code is a unique key, so check it explicitly to report it as a field
    // error rather than letting the database throw a duplicate-key exception.
    $dup = $db->value('SELECT id FROM courses WHERE code = ?' . ($action === 'update' ? ' AND id <> ?' : ''),
                      $action === 'update' ? [$code, $id] : [$code]);
    if ($dup) {
        $errors['code'] = 'That course code is already in use.';
    }

    if (!$errors) {
        if ($action === 'update') {
            $db->run(
                'UPDATE courses SET code = ?, title = ?, summary = ?, description = ?,
                        category_id = ?, level = ?, price_cents = ?, duration_min = ?, is_published = ?
                 WHERE id = ?',
                [$code, $title, $summary, $description, $categoryId, $level, $priceCents, $durationMin, $isPublished, $id]
            );
            flash('success', 'Course updated.');
        } else {
            $db->run(
                'INSERT INTO courses (code, title, summary, description, category_id, level, price_cents, duration_min, is_published)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$code, $title, $summary, $description, $categoryId, $level, $priceCents, $durationMin, $isPublished]
            );
            flash('success', 'Course created.');
        }
        redirect('courses.php');
    }

    $editId = $id;
}

$editing = null;
if (isset($_GET['id']) && ctype_digit((string) $_GET['id'])) {
    $editing = $db->one(
        'SELECT id, code, title, summary, description, category_id, level, price_cents, duration_min, is_published
         FROM courses WHERE id = ?',
        [(int) $_GET['id']]
    );
}

$q     = mb_substr(get('q'), 0, 80);
$level = get('level');
$level = in_array($level, $levels, true) ? $level : '';

$pattern = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
$courses = $db->all(
    "SELECT c.id, c.code, c.title, c.price_cents, c.is_published, c.level,
            cat.name AS category, COALESCE(e.n, 0) AS enrolled
     FROM courses c
     JOIN categories cat ON cat.id = c.category_id
     LEFT JOIN (SELECT course_id, COUNT(*) AS n FROM enrollments GROUP BY course_id) e
            ON e.course_id = c.id
     WHERE (? = '%%' OR c.title LIKE ? OR c.code LIKE ?)
       AND (? = '' OR c.level = ?)
     ORDER BY c.code ASC",
    [$q, $pattern, $pattern, $level, $level]
);

render_header('Manage courses', true, lms_nav());
?>
<div class="wrap">
  <?php render_flashes(); ?>
  <div class="section-head">
    <h1>Manage courses</h1>
    <form method="get" action="courses.php" role="search" class="shop-search">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search title or code" aria-label="Search courses">
      <select name="level" aria-label="Filter by level">
        <option value="">All levels</option>
        <?php foreach ($levels as $l): ?>
          <option value="<?= $l ?>" <?= $level === $l ? 'selected' : '' ?>><?= ucfirst($l) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-sm" type="submit">Search</button>
    </form>
  </div>

  <form method="post" action="courses.php" class="card form-grid" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>

    <h2 class="h5 field full"><?= $editing ? 'Edit course #' . (int) $editing['id'] : 'Add a course' ?></h2>

    <div class="field">
      <label for="code">Course code</label>
      <input type="text" id="code" name="code" maxlength="20" required
             value="<?= e($editing['code'] ?? post('code')) ?>" placeholder="WD101">
      <?php if (!empty($errors['code'])): ?><p class="error-text"><?= e($errors['code']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="category_id">Category</label>
      <select id="category_id" name="category_id" required>
        <?php
        $sel = (int) ($editing['category_id'] ?? post('category_id'));
        foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $sel === (int) $c['id'] ? 'selected' : '' ?>>
            <?= e((string) $c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (!empty($errors['category_id'])): ?><p class="error-text"><?= e($errors['category_id']) ?></p><?php endif; ?>
    </div>

    <div class="field full">
      <label for="title">Title</label>
      <input type="text" id="title" name="title" maxlength="140" required
             value="<?= e($editing['title'] ?? post('title')) ?>">
      <?php if (!empty($errors['title'])): ?><p class="error-text"><?= e($errors['title']) ?></p><?php endif; ?>
    </div>

    <div class="field full">
      <label for="summary">Summary</label>
      <input type="text" id="summary" name="summary" maxlength="300" required
             value="<?= e($editing['summary'] ?? post('summary')) ?>">
      <p class="hint">Shown on the catalogue card.</p>
      <?php if (!empty($errors['summary'])): ?><p class="error-text"><?= e($errors['summary']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="level">Level</label>
      <select id="level" name="level" required>
        <?php $selLevel = (string) ($editing['level'] ?? post('level', 'beginner')); ?>
        <?php foreach ($levels as $l): ?>
          <option value="<?= $l ?>" <?= $selLevel === $l ? 'selected' : '' ?>><?= ucfirst($l) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (!empty($errors['level'])): ?><p class="error-text"><?= e($errors['level']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="price_cents">Price (whole paise)</label>
      <input type="number" id="price_cents" name="price_cents" min="0" step="1" required
             value="<?= e((string) ($editing['price_cents'] ?? post('price_cents', '0'))) ?>">
      <p class="hint">0 makes the course free.</p>
      <?php if (!empty($errors['price_cents'])): ?><p class="error-text"><?= e($errors['price_cents']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="duration_min">Duration (minutes)</label>
      <input type="number" id="duration_min" name="duration_min" min="0" step="1" required
             value="<?= e((string) ($editing['duration_min'] ?? post('duration_min', '0'))) ?>">
      <?php if (!empty($errors['duration_min'])): ?><p class="error-text"><?= e($errors['duration_min']) ?></p><?php endif; ?>
    </div>

    <div class="field full">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="4" required><?= e($editing['description'] ?? post('description')) ?></textarea>
      <?php if (!empty($errors['description'])): ?><p class="error-text"><?= e($errors['description']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label class="check"><input type="checkbox" name="is_published" value="1"
        <?= (int) ($editing['is_published'] ?? 1) === 1 ? 'checked' : '' ?>> Published (visible to students)</label>
    </div>

    <div class="field full row">
      <button class="btn" type="submit"><?= $editing ? 'Save changes' : 'Create course' ?></button>
      <?php if ($editing): ?>
        <a class="btn btn-ghost" href="courses.php">Cancel</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="table-wrap">
    <table>
      <caption><?= count($courses) ?> course(s)</caption>
      <thead><tr><th scope="col">Code</th><th scope="col">Title</th><th scope="col">Category</th>
        <th scope="col">Level</th><th scope="col">Price</th><th scope="col">Enrolled</th>
        <th scope="col">Published</th><th scope="col">Actions</th></tr></thead>
      <tbody>
      <?php if (!$courses): ?><tr><td colspan="8">No courses match.</td></tr><?php endif; ?>
      <?php foreach ($courses as $c): ?>
        <tr>
          <th scope="row" class="mono"><?= e((string) $c['code']) ?></th>
          <td><?= e((string) $c['title']) ?></td>
          <td><?= e((string) $c['category']) ?></td>
          <td><?= ucfirst((string) $c['level']) ?></td>
          <td><?= e(lms_money((int) $c['price_cents'])) ?></td>
          <td><?= (int) $c['enrolled'] ?></td>
          <td>
            <span class="badge <?= (int) $c['is_published'] === 1 ? 'badge-green' : 'badge-amber' ?>">
              <?= (int) $c['is_published'] === 1 ? 'Yes' : 'No' ?>
            </span>
          </td>
          <td class="nowrap">
            <a class="btn btn-sm" href="courses.php?id=<?= (int) $c['id'] ?>">Edit</a>
            <form method="post" action="courses.php" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button class="btn btn-sm" type="submit">
                <?= (int) $c['is_published'] === 1 ? 'Unpublish' : 'Publish' ?>
              </button>
            </form>
            <form method="post" action="courses.php" class="inline-form"
                  data-confirm="Delete &quot;<?= e((string) $c['title']) ?>&quot;?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php render_footer(); ?>
