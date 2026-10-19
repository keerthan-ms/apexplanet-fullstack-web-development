<?php
/**
 * Task 2 — Bootstrap 5 component gallery.
 *
 * Collects every Bootstrap component the brief calls for: grid system,
 * navbar, carousel, cards, modals, buttons and responsive utilities.
 */

declare(strict_types=1);

$pageTitle = 'Bootstrap components';
$activeNav = 'components';

require __DIR__ . '/partials/header.php';

$slides = [
    ['bi-laptop',   'Responsive grid',      'Breakpoints at sm, md, lg, xl and xxl keep the layout readable on every screen.'],
    ['bi-phone',    'Mobile first',         'Columns stack by default and only share a row once there is room for them.'],
    ['bi-universal-access', 'Accessible', 'Focus rings, labels and reduced-motion support are enabled throughout.'],
];

$cards = [
    ['bi-box-seam',    'Cards',       'Reusable content containers with the ApexPlanet hover lift.'],
    ['bi-window',      'Modals',      'Dialogs for confirmations and secondary tasks.'],
    ['bi-collection',  'Navbars',     'Collapsing navigation with the responsive toggler.'],
    ['bi-images',      'Carousels',   'Slideshows built on Bootstrap with custom captions.'],
    ['bi-cursor',      'Buttons',     'Primary, ghost and danger variants with motion feedback.'],
    ['bi-display',     'Utilities',   'd-none, d-md-block and friends for visibility control.'],
];
?>

<div class="container py-5">

  <span class="section-label">Reference</span>
  <h1 class="h2 fw-bold mt-2 mb-2">Bootstrap 5 component gallery</h1>
  <p class="text-body-secondary mb-5">
    Every component named in the Task 2 brief, rendered with the custom theme.
  </p>

  <!-- ------------------------------------------------------ Carousel --- -->
  <section class="mb-5">
    <h2 class="h4 fw-bold mb-3"><i class="bi bi-images me-2 text-primary" aria-hidden="true"></i>Carousel</h2>
    <div id="apexCarousel" class="carousel slide apex-carousel rounded-4 overflow-hidden shadow"
         data-bs-ride="carousel" data-bs-interval="5000">
      <div class="carousel-indicators">
        <?php foreach ($slides as $i => $_): ?>
          <button type="button" data-bs-target="#apexCarousel" data-bs-slide-to="<?= $i ?>"
                  class="<?= $i === 0 ? 'active' : '' ?>"
                  <?= $i === 0 ? 'aria-current="true"' : '' ?>
                  aria-label="Slide <?= $i + 1 ?>"></button>
        <?php endforeach; ?>
      </div>

      <div class="carousel-inner">
        <?php foreach ($slides as $i => [$icon, $title, $body]): ?>
          <div class="carousel-item<?= $i === 0 ? ' active' : '' ?>">
            <div class="d-grid" style="background:linear-gradient(135deg,#0d1330,#2c1f66);height:clamp(220px,38vw,380px)">
              <div class="carousel-caption d-flex flex-column justify-content-end align-items-start text-start p-4">
                <i class="bi <?= e($icon) ?> fs-1 mb-2" aria-hidden="true"></i>
                <h3 class="h4 fw-bold"><?= e($title) ?></h3>
                <p class="mb-0 small col-md-8"><?= e($body) ?></p>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <button class="carousel-control-prev" type="button" data-bs-target="#apexCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#apexCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </button>
    </div>
  </section>

  <!-- ------------------------------------------- Grid + responsiveness --- -->
  <section class="mb-5">
    <h2 class="h4 fw-bold mb-3">
      <i class="bi bi-grid-3x3-gap-fill me-2 text-primary" aria-hidden="true"></i>Grid system and responsive utilities
    </h2>
    <p class="text-body-secondary small">
      Each row below stacks on phones, becomes two columns from <code>md</code>, and
      three from <code>lg</code>.
    </p>
    <div class="row g-3">
      <?php for ($i = 1; $i <= 6; $i++): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="apex-card p-3 text-center">
            <span class="badge text-bg-primary mb-2">col-12 col-md-6 col-lg-4</span>
            <p class="mb-0 small text-body-secondary">Block <?= $i ?></p>
          </div>
        </div>
      <?php endfor; ?>
    </div>

    <div class="row g-3 mt-3">
      <div class="col-12">
        <div class="apex-card p-3 text-center border-2">
          <span class="badge text-bg-secondary mb-2">d-block</span>
          <p class="mb-0 small text-body-secondary">Visible at every breakpoint</p>
        </div>
      </div>
      <div class="col-12 d-none d-md-block">
        <div class="apex-card p-3 text-center">
          <span class="badge text-bg-success mb-2">d-none d-md-block</span>
          <p class="mb-0 small text-body-secondary">Hidden on phones, shown from <code>md</code> upward</p>
        </div>
      </div>
      <div class="col-12 d-md-none">
        <div class="apex-card p-3 text-center">
          <span class="badge text-bg-warning mb-2">d-md-none</span>
          <p class="mb-0 small text-body-secondary">Shown on phones only</p>
        </div>
      </div>
    </div>
  </section>

  <!-- --------------------------------------------------------- Cards --- -->
  <section class="mb-5">
    <h2 class="h4 fw-bold mb-3"><i class="bi bi-box-seam me-2 text-primary" aria-hidden="true"></i>Cards</h2>
    <div class="row g-4">
      <?php foreach ($cards as [$icon, $title, $body]): ?>
        <div class="col-sm-6 col-lg-4">
          <div class="apex-card p-4">
            <div class="icon-tile mb-3"><i class="bi <?= e($icon) ?>" aria-hidden="true"></i></div>
            <h3 class="h6 fw-semibold"><?= e($title) ?></h3>
            <p class="small text-body-secondary mb-3"><?= e($body) ?></p>
            <button class="btn btn-ghost btn-sm" data-bs-toggle="modal" data-bs-target="#detailModal"
                    data-modal-title="<?= e($title) ?>">
              Details
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ------------------------------------------------- Buttons/modals --- -->
  <section class="mb-5">
    <h2 class="h4 fw-bold mb-3"><i class="bi bi-cursor me-2 text-primary" aria-hidden="true"></i>Buttons and modals</h2>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <button class="btn btn-apex">Primary action</button>
      <button class="btn btn-ghost">Secondary</button>
      <button class="btn btn-outline-primary">Outline</button>
      <button class="btn btn-link">Link button</button>
      <button class="btn btn-danger">Destructive</button>
      <button class="btn btn-apex" disabled>Disabled</button>
      <button class="btn btn-apex" data-bs-toggle="modal" data-bs-target="#detailModal">Open modal</button>
    </div>

    <div class="btn-toolbar gap-2" role="group" aria-label="Button group example">
      <div class="btn-group" role="group" aria-label="Alignment options">
        <input type="radio" class="btn-check" name="align" id="align1" value="start" checked>
        <label class="btn btn-outline-primary" for="align1">Start</label>
        <input type="radio" class="btn-check" name="align" id="align2" value="center">
        <label class="btn btn-outline-primary" for="align2">Center</label>
        <input type="radio" class="btn-check" name="align" id="align3" value="end">
        <label class="btn btn-outline-primary" for="align3">End</label>
      </div>
      <div class="btn-group" role="group" aria-label="Size options">
        <button class="btn btn-ghost btn-sm">Small</button>
        <button class="btn btn-ghost">Default</button>
        <button class="btn btn-ghost btn-lg">Large</button>
      </div>
    </div>
  </section>

  <!-- ------------------------------------------- Contextual components --- -->
  <section>
    <h2 class="h4 fw-bold mb-3">
      <i class="bi bi-info-circle me-2 text-primary" aria-hidden="true"></i>Alerts, badges and progress
    </h2>
    <div class="d-grid gap-2">
      <div class="alert alert-success" role="status">Your changes have been saved.</div>
      <div class="alert alert-warning" role="alert">Your session expires in five minutes.</div>
      <div class="alert alert-danger" role="alert">We could not save that. Try again.</div>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-4">
      <span class="badge text-bg-primary">Primary</span>
      <span class="badge text-bg-success">Available</span>
      <span class="badge text-bg-warning">Pending</span>
      <span class="badge text-bg-danger">Rejected</span>
      <span class="badge text-bg-dark">Admin</span>
    </div>
    <label class="form-label small fw-semibold" for="demo-progress">Profile completeness</label>
    <div class="progress" role="progressbar" aria-label="Profile completeness"
         aria-valuenow="72" aria-valuemin="0" aria-valuemax="100" style="height:8px">
      <div class="progress-bar" style="width:72%"></div>
    </div>
  </section>

</div>

<!-- Shared modal, title set from the triggering button -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content apex-card">
      <div class="modal-header">
        <h2 class="modal-title h5" id="detailModalLabel">Details</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0 text-body-secondary">
          This dialog is opened with <code>data-bs-toggle="modal"</code>. The title is
          read from <code>data-modal-title</code> on the button that opened it.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-apex" data-bs-dismiss="modal">Got it</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('click', function (event) {
  var trigger = event.target.closest('[data-modal-title]');
  if (!trigger) { return; }
  var modal = document.getElementById('detailModal');
  if (!modal) { return; }
  var label = modal.querySelector('#detailModalLabel');
  if (label) { label.textContent = trigger.getAttribute('data-modal-title'); }
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
