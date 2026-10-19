<?php
/**
 * Task 5 — AJAX course search endpoint.
 *
 * GET ?q=&category=&level=&max_price=&page=
 *   -> 200 {"total":N,"pages":P,"page":n,"html":"..."}
 *
 * The HTML fragment is rendered by the same partial the server-side catalogue
 * uses, so there is one source of truth for the markup. All output is escaped
 * before it reaches JSON, and json_encode runs with the HEX_TAG/AMP flags on.
 */

declare(strict_types=1);

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../lib/CourseQuery.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_out(['error' => 'Use GET for this endpoint.'], 405);
}

$db       = Database::instance($APEX_CONFIG, 'db_lms');
$criteria = CourseQuery::fromRequest();
$result   = (new CourseQuery($db))->find($criteria);

$enrolled = [];
if (is_logged_in()) {
    foreach ($db->all('SELECT course_id FROM enrollments WHERE user_id = ?', [current_user_id()]) as $r) {
        $enrolled[(int) $r['course_id']] = true;
    }
}

ob_start();
require __DIR__ . '/../partials/course-cards.php';
$html = (string) ob_get_clean();

json_out([
    'total'  => $result['total'],
    'pages'  => $result['pages'],
    'page'   => $result['page'],
    'html'   => $html,
    'url'    => CourseQuery::url($criteria),
], 200);
