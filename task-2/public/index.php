<?php
/**
 * Task 2 — overview page.
 */

declare(strict_types=1);

$pageTitle = 'Overview';
$activeNav = 'home';

require __DIR__ . '/partials/header.php';

$features = [
    ['bi-grid-3x3-gap-fill', 'Responsive grid',        'Rows, columns and breakpoints that reflow from 320px upward.'],
    ['bi-collection',       'Reusable components',    'One navbar and footer partial drive every page in the task.'],
    ['bi-palette',          'Custom palette',         'ApexPlanet indigo, violet and cyan applied through Bootstrap variables.'],
    ['bi-lightning-charge', 'AJAX feedback',          'Live availability checks with no page reload.'],
    ['bi-shield-lock',      'Server-side checks',     'Client validation is convenience only; the server validates again.'],
    ['bi-universal-access', 'Accessible by default',  'Real labels, focus rings, ARIA and reduced-motion support.'],
];
?>

<div class="container py-5">

  <div class="row g-4 align-items-center mb-5">
    <div class="col-lg-7">
      <span class="section-label">Task 2 · Interactive UI</span>
      <h1 class="display-5 fw-bold mt-2 mb-3">
        A responsive <span class="text-gradient">login and registration</span> experience
      </h1>
      <p class="lead text-body-secondary mb-4">
        Built with Bootstrap 5, a custom brand theme and vanilla JavaScript. Every
        form validates in the browser and again on the server, and the availability
        check talks to PHP over AJAX without reloading the page.
      </p>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-apex px-4 py-2" href="register.php">
          <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Create an account
        </a>
        <a class="btn btn-ghost px-4 py-2" href="login.php">
          <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Sign in
        </a>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="apex-hero p-4">
        <p class="section-label text-white-50 mb-2">Try it</p>
        <h2 class="h4 mb-3">Register and watch the AJAX check work</h2>
        <ul class="list-unstyled small mb-0">
          <?php foreach ([
              'Type "admin" to see a taken username reported instantly',
              'Type "keerthan.m" to see the format rule accept it',
              'Enter a 6-character password to see the length rule fire',
              'Make the confirmation differ to see the match check fail',
            ] as $tip): ?>
            <li class="d-flex gap-2 mb-2">
              <i class="bi bi-check2-circle text-white-50" aria-hidden="true"></i><?= e($tip) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <section class="mb-5">
    <div class="section-label mb-2">What this task demonstrates</div>
    <h2 class="h3 fw-bold mb-4">Features</h2>
    <div class="row g-4">
      <?php foreach ($features as [$icon, $title, $body]): ?>
        <div class="col-md-6 col-lg-4">
          <div class="apex-card p-4">
            <div class="icon-tile mb-3"><i class="bi <?= e($icon) ?>" aria-hidden="true"></i></div>
            <h3 class="h5 fw-semibold"><?= e($title) ?></h3>
            <p class="text-body-secondary mb-0 small"><?= e($body) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section>
    <div class="section-label mb-2">Bootstrap 5</div>
    <h2 class="h3 fw-bold mb-4">Component showcase</h2>
    <p class="text-body-secondary">
      Carousels, cards, modals, buttons and the responsive utility classes are
      collected on their own page.
    </p>
    <a class="btn btn-apex" href="components.php">
      <i class="bi bi-collection me-1" aria-hidden="true"></i>Open the component gallery
    </a>
  </section>

</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
