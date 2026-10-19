<?php
/**
 * Task 5 — admin dashboard with Chart.js analytics.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_admin();

$db = Database::instance($APEX_CONFIG, 'db_lms');

$stats = [
    'students'    => (int) $db->value("SELECT COUNT(*) FROM users WHERE role = 'student'"),
    'verified'    => (int) $db->value('SELECT COUNT(*) FROM users WHERE email_verified = 1'),
    'unverified'  => (int) $db->value('SELECT COUNT(*) FROM users WHERE email_verified = 0'),
    'courses'     => (int) $db->value('SELECT COUNT(*) FROM courses WHERE is_published = 1'),
    'drafts'      => (int) $db->value('SELECT COUNT(*) FROM courses WHERE is_published = 0'),
    'enrollments' => (int) $db->value("SELECT COUNT(*) FROM enrollments WHERE status = 'active'"),
];

// Enrolments per day for the last 14 days.
$rows = $db->all(
    "SELECT DATE(enrolled_at) AS day, COUNT(*) AS n
     FROM enrollments
     WHERE enrolled_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(enrolled_at)
     ORDER BY day ASC"
);
$map = [];
foreach ($rows as $r) { $map[(string) $r['day']] = (int) $r['n']; }

$labels = [];
$series = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $labels[] = date('d M', strtotime($day));
    $series[] = $map[$day] ?? 0;
}

// Enrolments by course level.
$byLevel = $db->all(
    "SELECT c.level, COUNT(e.id) AS n
     FROM courses c LEFT JOIN enrollments e ON e.course_id = c.id AND e.status = 'active'
     GROUP BY c.level ORDER BY n DESC"
);

$topCourses = $db->all(
    "SELECT c.title, c.code, COUNT(e.id) AS enrolled
     FROM courses c LEFT JOIN enrollments e ON e.course_id = c.id AND e.status = 'active'
     WHERE c.is_published = 1
     GROUP BY c.id, c.title, c.code
     ORDER BY enrolled DESC, c.title ASC
     LIMIT 6"
);

$otpStats = [
    'issued'  => (int) $db->value('SELECT COUNT(*) FROM otp_codes'),
    'used'    => (int) $db->value('SELECT COUNT(*) FROM otp_codes WHERE consumed_at IS NOT NULL'),
    'pending' => (int) $db->value('SELECT COUNT(*) FROM otp_codes WHERE consumed_at IS NULL AND expires_at > NOW()'),
];

render_header('Admin dashboard', true, lms_nav());
?>
<div class="wrap">
  <?php render_flashes(); ?>
  <h1>Admin dashboard</h1>

  <div class="grid-3">
    <div class="card stat"><b><?= $stats['students'] ?></b><span>Students</span></div>
    <div class="card stat"><b><?= $stats['verified'] ?></b><span>Verified emails</span></div>
    <div class="card stat"><b><?= $stats['unverified'] ?></b><span>Awaiting verification</span></div>
    <div class="card stat"><b><?= $stats['courses'] ?></b><span>Published courses</span></div>
    <div class="card stat"><b><?= $stats['drafts'] ?></b><span>Drafts</span></div>
    <div class="card stat"><b><?= $stats['enrollments'] ?></b><span>Active enrolments</span></div>
  </div>

  <div class="charts">
    <div class="card">
      <h2 class="h5">Enrolments per day (last 14 days)</h2>
      <div class="chart-box"><canvas id="enrolChart" role="img"
        aria-label="Bar chart of enrolments per day over the last 14 days"></canvas></div>
      <table class="visually-hidden">
        <caption>Enrolments per day, same data as the chart above</caption>
        <thead><tr><th scope="col">Day</th><th scope="col">Enrolments</th></tr></thead>
        <tbody>
        <?php foreach ($series as $i => $n): ?>
          <tr><td><?= e($labels[$i]) ?></td><td><?= $n ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2 class="h5">Enrolments by level</h2>
      <div class="chart-box"><canvas id="levelChart" role="img"
        aria-label="Doughnut chart of enrolments by course level"></canvas></div>
      <ul class="tags">
        <?php foreach ($byLevel as $l): ?>
          <li><?= e((string) $l['level']) ?>: <?= (int) $l['n'] ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <div class="section-head"><h2 class="h4">OTP verification activity</h2></div>
  <div class="grid-3">
    <div class="card stat"><b><?= $otpStats['issued'] ?></b><span>Codes issued</span></div>
    <div class="card stat"><b><?= $otpStats['used'] ?></b><span>Codes consumed</span></div>
    <div class="card stat"><b><?= $otpStats['pending'] ?></b><span>Still valid</span></div>
  </div>

  <div class="section-head"><h2 class="h4">Most popular courses</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th scope="col">Code</th><th scope="col">Title</th><th scope="col">Enrolled</th></tr></thead>
      <tbody>
      <?php foreach ($topCourses as $c): ?>
        <tr>
          <th scope="row" class="mono"><?= e((string) $c['code']) ?></th>
          <td><?= e((string) $c['title']) ?></td>
          <td><?= (int) $c['enrolled'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="../../vendor-assets/chartjs-4.4.1/chart.umd.js"></script>
<script>
(function () {
  'use strict';

  var palette = { indigo: '#5b8cff', violet: '#8b5cf6', cyan: '#22d3ee', green: '#34d399', amber: '#fbbf24', rose: '#fb7185' };
  var gridColour = 'rgba(255,255,255,.06)';
  var tickColour = '#a7b0cc';

  var enrolCanvas = document.getElementById('enrolChart');
  if (enrolCanvas && window.Chart) {
    new Chart(enrolCanvas, {
      type: 'bar',
      data: {
        labels: <?= json_safe($labels) ?>,
        datasets: [{
          label: 'Enrolments',
          data: <?= json_safe($series) ?>,
          backgroundColor: 'rgba(91,140,255,.7)',
          borderColor: palette.indigo,
          borderWidth: 1,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { color: tickColour } } },
        scales: {
          x: { ticks: { color: tickColour }, grid: { color: gridColour } },
          y: { beginAtZero: true, ticks: { color: tickColour, precision: 0 }, grid: { color: gridColour } }
        }
      }
    });
  }

  var levelCanvas = document.getElementById('levelChart');
  if (levelCanvas && window.Chart) {
    new Chart(levelCanvas, {
      type: 'doughnut',
      data: {
        labels: <?= json_safe(array_map(static fn ($r) => (string) $r['level'], $byLevel)) ?>,
        datasets: [{
          data: <?= json_safe(array_map(static fn ($r) => (int) $r['n'], $byLevel)) ?>,
          backgroundColor: [palette.green, palette.indigo, palette.rose],
          borderColor: '#1a2340',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { color: tickColour, boxWidth: 14 } } }
      }
    });
  }
})();
</script>
<?php render_footer(); ?>
