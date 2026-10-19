<?php
/**
 * Task 5 — course card partial.
 *
 * Shared by the server-rendered catalogue and the AJAX endpoint so both render
 * identical markup. Expects $result and $enrolled to be in scope.
 *
 * @var array $result
 * @var array<int,bool> $enrolled
 */

if (!$result['rows']): ?>
    <div class="card">
        <p class="muted">No courses match those filters. Try a broader search.</p>
    </div>
<?php endif; ?>

<?php foreach ($result['rows'] as $c):
    $id       = (int) $c['id'];
    $isOn     = isset($enrolled[$id]);
    $isFree   = (int) $c['price_cents'] === 0;
?>
    <article class="card course-card">
        <div class="row">
            <span class="badge"><?= e((string) $c['category']) ?></span>
            <span class="badge badge-alt"><?= e(ucfirst((string) $c['level'])) ?></span>
        </div>

        <h2 class="h5"><?= e((string) $c['title']) ?></h2>
        <p class="muted small mono"><?= e((string) $c['code']) ?></p>
        <p class="small clamp"><?= e((string) $c['summary']) ?></p>

        <ul class="course-meta">
            <li><i class="bi bi-list-check" aria-hidden="true"></i> <?= (int) $c['lesson_count'] ?> lessons</li>
            <li><i class="bi bi-clock" aria-hidden="true"></i> <?= (int) round((int) $c['duration_min'] / 60) ?> h</li>
            <li><i class="bi bi-people" aria-hidden="true"></i> <?= (int) $c['enrolled'] ?> enrolled</li>
        </ul>

        <p class="price"><?= e(lms_money((int) $c['price_cents'])) ?></p>

        <div class="row mt-auto">
            <a class="btn btn-sm btn-ghost" href="course.php?id=<?= $id ?>">View course</a>
            <?php if (is_logged_in()): ?>
                <?php if ($isOn): ?>
                    <a class="btn btn-sm" href="my-courses.php">Enrolled</a>
                <?php else: ?>
                    <form method="post" action="enrol.php" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="course_id" value="<?= $id ?>">
                        <button class="btn btn-sm" type="submit">Enrol<?= $isFree ? ' free' : '' ?></button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <a class="btn btn-sm btn-ghost" href="login.php">Sign in to enrol</a>
            <?php endif; ?>
        </div>
    </article>
<?php endforeach; ?>
