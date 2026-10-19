<?php
/**
 * Task 1 — PHP fundamentals.
 *
 * Demonstrates, in one page:
 *   - syntax, variables, and the basic types
 *   - indexed, associative and multidimensional arrays
 *   - functions and control structures (if / else / switch / loops)
 *   - form handling with $_GET and $_POST
 *   - include() and require()
 *
 * Run:  php -S 127.0.0.1:8081 -t task-1/public
 *  then open http://127.0.0.1:8081/php/index.php
 */

declare(strict_types=1);

// include() returns false and warns if the file is missing; require() halts.
// These are the correct choices for files the page cannot work without.
require __DIR__ . '/inc/functions.php';
include __DIR__ . '/inc/header.php';

/* ------------------------------------------------------- 1. Variables ---- */

$name    = 'Keerthan';
$age     = 20;
$isStudent = true;
$balance = 1499.50;          // float
$tags    = null;             // null

echo '<section class="section"><div class="section-head"><h2>1. Variables and types</h2></div><div class="card">';
echo '<pre>';
echo 'string : ' . var_export($name, true) . "\n";
echo 'int    : ' . var_export($age, true) . "\n";
echo 'float  : ' . var_export($balance, true) . "\n";
echo 'bool   : ' . var_export($isStudent, true) . "\n";
echo 'null   : ' . var_export($tags, true) . "\n";
echo "concat  : {$name} is {$age} years old\n";
echo '</pre>';
echo '<p class="hint">Variables are prefixed <code>$</code>. Strings interpolate with <code>{$var}</code> '
   . 'inside double quotes. <code>declare(strict_types=1)</code> at the top makes PHP reject '
   . 'wrong argument types instead of silently coercing them.</p>';
echo '</div></section>';

/* --------------------------------------------------------- 2. Arrays ---- */

// Indexed
$fruits = ['apple', 'banana', 'cherry', 'date'];

// Associative
$profile = [
    'name'    => 'Keerthan M S',
    'role'    => 'Full Stack Developer',
    'city'    => 'Bengaluru',
    'skills'  => ['PHP', 'MySQL', 'JavaScript'],
];

// Multidimensional — a list of associative arrays
$projects = [
    ['title' => 'User Management System', 'stack' => ['PHP', 'MySQL'], 'year' => 2026],
    ['title' => 'Storefront',            'stack' => ['PHP', 'Bootstrap'], 'year' => 2026],
    ['title' => 'Capstone Portal',       'stack' => ['PHP', 'Chart.js'], 'year' => 2026],
];

echo '<section class="section"><div class="section-head"><h2>2. Arrays</h2></div><div class="card">';

echo '<h3>Indexed array</h3><pre>';
foreach ($fruits as $index => $fruit) {
    echo "  [{$index}] => {$fruit}\n";
}
echo '</pre>';

echo '<h3>Associative array</h3><pre>';
foreach ($profile as $key => $value) {
    if (is_array($value)) {
        $value = implode(', ', $value);
    }
    echo "  {$key} => {$value}\n";
}
echo '</pre>';

echo '<h3>Multidimensional array</h3><pre>';
foreach ($projects as $p) {
    echo "  - {$p['title']} ({$p['year']}): " . implode(' + ', $p['stack']) . "\n";
}
echo '</pre>';
echo '<p class="hint">Access a nested value with <code>$projects[0][\'title\']</code> → <code>'
   . e((string) $projects[0]['title']) . '</code></p>';
echo '</div></section>';

/* ------------------------------------- 3. Functions and control flow ---- */

echo '<section class="section"><div class="section-head"><h2>3. Functions and control structures</h2></div><div class="card">';

// Function declaration
function grade(int $score): string
{
    return match (true) {
        $score >= 90 => 'A',
        $score >= 75 => 'B',
        $score >= 60 => 'C',
        default      => 'F',
    };
}

/** foreach with an index and a running total. */
function total(array $rows, string $key): float
{
    $sum = 0.0;
    foreach ($rows as $row) {
        $sum += (float) ($row[$key] ?? 0);
    }
    return $sum;
}

$scores = [96, 78, 64, 41];

echo '<pre>';
// for loop
for ($i = 0; $i < count($scores); $i++) {
    echo '  for      index ' . $i . ' → score ' . $scores[$i] . "\n";
}

// while loop
$n = 3;
while ($n > 0) {
    echo "  while    count down {$n}\n";
    $n--;
}

// foreach + break
foreach ($scores as $s) {
    if ($s < 50) {
        echo "  foreach  stopping at first failing score: {$s}\n";
        break;
    }
}

// if / elseif / else
$scoreRows = array_map(static fn (int $s): array => ['v' => $s], $scores);
$avg        = total($scoreRows, 'v') / count($scores);
if ($avg >= 75) {
    $verdict = 'Distinction';
} elseif ($avg >= 50) {
    $verdict = 'Pass';
} else {
    $verdict = 'Fail';
}
echo "  if/else  average " . round($avg, 2) . " → {$verdict}\n";

// switch
foreach ($scores as $s) {
    $band = grade($s);
    switch (true) {
        case $band === 'A': echo "  switch   {$s} → grade A (excellent)\n"; break;
        case $band === 'B': echo "  switch   {$s} → grade B (good)\n";      break;
        case $band === 'C': echo "  switch   {$s} → grade C (pass)\n";      break;
        default:           echo "  switch   {$s} → grade F (fail)\n";       break;
    }
}
echo '</pre>';
echo '</div></section>';

/* --------------------------------------------------- 4. $_GET and $_POST - */

$message = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Never trust input. Trim it, then apply a sensible minimum length and
    // character filter before it is used for matching.
    $raw    = trim((string) ($_POST['fruit'] ?? ''));
    $search = strtolower($raw);

    if ($raw === '') {
        $message = 'Please enter a fruit name.';
    } elseif (mb_strlen($search) < 2) {
        $message = 'Please enter at least 2 characters to search.';
    } elseif (mb_strlen($search) > 40) {
        $message = 'Search text is too long (maximum 40 characters).';
    } else {
        foreach ($fruits as $fruit) {
            if (str_contains(mb_strtolower($fruit), $search)) {
                $results[] = $fruit;
            }
        }
        $message = $results
            ? 'Found ' . count($results) . ' match' . (count($results) === 1 ? '' : 'es')
                . ' for "' . e($raw) . '".'
            : 'No fruit matches "' . e($raw) . '".';
    }
}

$sortParam = isset($_GET['sort']) && is_string($_GET['sort']) ? trim($_GET['sort']) : '';
$sortLabel  = in_array($sortParam, ['asc', 'desc'], true) ? $sortParam : '';

echo '<section class="section"><div class="section-head"><h2>4. Form handling</h2></div><div class="card">';

echo '<h3>$_POST — search fruits</h3>';
echo '<form method="post" action="index.php" class="form-grid" style="margin-bottom:1rem">';
echo '<div class="field"><label for="fruit">Fruit name</label>';
echo '<input type="text" id="fruit" name="fruit" value="' . e((string) ($_POST['fruit'] ?? '')) . '" placeholder="e.g. ch"></div>';
echo '<div class="field"><label>&nbsp;</label><button class="btn" type="submit">Search</button></div>';
echo '</form>';

if ($message !== '') {
    echo '<div class="alert alert-info" role="status">' . $message . '</div>';
}
if ($results) {
    echo '<ul class="tags">';
    foreach ($results as $r) {
        echo '<li>' . e($r) . '</li>';
    }
    echo '</ul>';
}

echo '<h3>$_GET — change sort order</h3>';
echo '<p class="hint">Sort is read from the query string with <code>$_GET[\'sort\']</code> '
   . 'and validated against a whitelist before use.</p>';
$copy = $fruits;
if ($sortLabel === 'desc') {
    rsort($copy);
} elseif ($sortLabel === 'asc') {
    sort($copy);
}
echo '<p><a class="btn btn-sm btn-ghost" href="index.php?sort=asc">Ascending</a> ';
echo '<a class="btn btn-sm btn-ghost" href="index.php?sort=desc">Descending</a> ';
echo '<a class="btn btn-sm btn-ghost" href="index.php">Unsorted</a></p>';
echo '<p>Order (' . ($sortLabel !== '' ? e($sortLabel) : 'unsorted') . '): ' . e(implode(', ', $copy)) . '</p>';
echo '</div></section>';

include __DIR__ . '/inc/footer.php';
