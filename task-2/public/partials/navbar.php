<?php
/**
 * Reusable Navbar component — Task 2.
 *
 * Demonstrates the Bootstrap 5 navbar, the responsive collapse behaviour and
 * the d-none / d-md-block utility classes that switch the inline links for a
 * collapsed menu on small screens.
 */

declare(strict_types=1);

/** @var string $activeNav */
$links = [
    'home'       => ['index.php',     'Overview'],
    'components' => ['components.php', 'Bootstrap components'],
    'login'      => ['login.php',      'Sign in'],
    'register'   => ['register.php',   'Create account'],
];
?>
<nav class="navbar navbar-expand-md apex-navbar sticky-top" aria-label="Primary">
  <div class="container">

    <a class="navbar-brand" href="index.php">
      <span class="brand-mark" aria-hidden="true">A</span>
      <span>Apex<span class="text-gradient">Planet</span></span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#apexNav" aria-controls="apexNav"
            aria-expanded="false" aria-label="Toggle navigation">
      <i class="bi bi-list fs-4" aria-hidden="true"></i>
    </button>

    <div class="collapse navbar-collapse" id="apexNav">
      <ul class="navbar-nav ms-auto mb-2 mb-md-0 align-items-md-center gap-md-1">
        <?php foreach ($links as $key => [$href, $label]): ?>
          <li class="nav-item">
            <a class="nav-link<?= $activeNav === $key ? ' active' : '' ?>"
               href="<?= e($href) ?>"
               <?= $activeNav === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
          </li>
        <?php endforeach; ?>

        <!-- Utility classes: hidden on small screens, shown from md upward. -->
        <li class="nav-item d-none d-md-block ms-md-2">
          <a class="btn btn-apex btn-sm px-3" href="register.php">
            <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Get started
          </a>
        </li>
        <li class="nav-item d-md-none">
          <a class="btn btn-apex w-100 mt-2" href="register.php">Get started</a>
        </li>
      </ul>
    </div>

  </div>
</nav>
