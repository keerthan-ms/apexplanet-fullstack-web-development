<?php
/**
 * Included (not required) partial for the Task 1 PHP demo header.
 * Demonstrates include(): a missing file would only warn, so it is used for
 * presentational markup that is not essential to page function.
 */

$pageTitle = $pageTitle ?? 'PHP Fundamentals';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · ApexPlanet Task 1</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>

<header class="site-header">
  <nav class="nav wrap" aria-label="Primary">
    <a class="brand" href="../index.html">Apex<span>Planet</span></a>
    <ul class="nav-list">
      <li><a href="../index.html">Home</a></li>
      <li><a href="../fundamentals.html">Fundamentals</a></li>
      <li><a href="index.php" class="active" aria-current="page">PHP demo</a></li>
      <li><a href="../mysql/index.php">MySQL demo</a></li>
    </ul>
  </nav>
</header>

<main id="main" class="wrap">
