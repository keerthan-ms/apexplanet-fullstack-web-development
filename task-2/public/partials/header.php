<?php
/**
 * Reusable layout component — Task 2.
 *
 * Every page in this task includes this partial, which itself includes the
 * navbar and footer components, so branding and navigation are defined once.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc.php';

/** @var string $pageTitle  */
$pageTitle = $pageTitle ?? 'ApexPlanet';
/** @var string $activeNav  one of: home | login | register | components */
$activeNav = $activeNav ?? '';

$assetRoot = '../../vendor-assets/bootstrap-5.3.3';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · ApexPlanet Task 2</title>
<meta name="description" content="Responsive login and registration interface built with Bootstrap 5, custom theming and AJAX validation.">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= e($assetRoot) ?>/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>

<a class="visually-hidden-focusable position-absolute top-0 start-0 m-2 p-2 bg-white rounded shadow" href="#main">Skip to main content</a>

<?php require __DIR__ . '/navbar.php'; ?>

<main id="main">
