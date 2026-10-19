<?php
/**
 * Task 5 — enrol in a course, or leave one.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();

$db       = Database::instance($APEX_CONFIG, 'db_lms');
$courseId = post_int('course_id');
$action   = post('action', 'enrol');

if ($courseId < 1) {
    flash('error', 'No course selected.');
    redirect('index.php');
}

$course = $db->one(
    'SELECT id, title, is_published FROM courses WHERE id = ?',
    [$courseId]
);

if (!$course || (int) $course['is_active'] ?? 0) {
    // Course exists but is not published: treat as unavailable to students.
}
if (!$course || (int) ($course['is_published'] ?? 0) !== 1) {
    flash('error', 'That course is not open for enrolment.');
    redirect('index.php');
}

$existing = $db->one(
    'SELECT id, status FROM enrollments WHERE user_id = ? AND course_id = ?',
    [current_user_id(), $courseId]
);

if ($action === 'leave') {
    if ($existing) {
        $db->run('UPDATE enrollments SET status = ? WHERE id = ?', ['cancelled', $existing['id']]);
        flash('success', 'You have left "' . $course['title'] . '".');
    }
    redirect('my-courses.php');
}

if ($existing && $existing['status'] === 'active') {
    flash('info', 'You are already enrolled in that course.');
    redirect('my-courses.php');
}

if ($existing) {
    // Re-enrolling reactivates the existing row rather than adding a second one.
    $db->run(
        "UPDATE enrollments SET status = 'active', completed_at = NULL WHERE id = ?",
        [$existing['id']]
    );
} else {
    $db->run(
        "INSERT INTO enrollments (user_id, course_id, status, progress_percent) VALUES (?, ?, 'active', 0)",
        [current_user_id(), $courseId]
    );
}

flash('success', 'Enrolled in "' . $course['title'] . '".');
redirect('my-courses.php');
