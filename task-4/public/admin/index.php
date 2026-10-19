<?php
/**
 * Task 4 — admin dashboard.
 *
 * Statistics plus two charts: orders per day and the most active customers.
 * The data is prepared server-side and handed to Chart.js as JSON, so the
 * browser is never asked to build SQL or re-aggregate anything.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_admin();

$db = Database::instance($APEX_CONFIG, 'db_shop');

$stats = [
    'products'    => (int) $db->value('SELECT COUNT(*) FROM products WHERE is_active = 1'),
    'out_of_stock'=> (int) $db->value('SELECT COUNT(*) FROM products WHERE stock = 0'),
    'orders'      => (int) $db->value('SELECT COUNT(*) FROM orders'),
    'pending'     => (int) $db->value("SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
    'customers'   => (int) $db->value("SELECT COUNT(*) FROM users WHERE role = 'user'"),
    'revenue'     => (int) $db->value("SELECT COALESCE(SUM(total_cents),0) FROM orders WHERE status <> 'cancelled'"),
];

// Orders per day for the last 14 days. A generated date series is joined on so
// days with no orders show as zero instead of collapsing the axis.
$since = date('Y-m-d', strtotime('-13 days'));
$perDay = $db->all(
    "SELECT DATE(placed_at) AS day, COUNT(*) AS orders, COALESCE(SUM(total_cents),0) AS revenue
     FROM orders
     WHERE placed_at >= ? AND status <> 'cancelled'
     GROUP BY DATE(placed_at)
     ORDER BY day ASC",
    [$since]
);
$perDayMap = [];
foreach ($perDay as $r) {
    $perDayMap[(string) $r['day']] = ['orders' => (int) $r['orders'], 'revenue' => (int) $r['revenue']];
}

$labels = [];
$orderSeries = [];
$revenueSeries = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $labels[]         = date('d M', strtotime($day));
    $orderSeries[]    = $perDayMap[$day]['orders'] ?? 0;
    $revenueSeries[]  = ($perDayMap[$day]['revenue'] ?? 0) / 100;
}

$topCustomers = $db->all(
    "SELECT u.username, COUNT(o.id) AS orders, COALESCE(SUM(o.total_cents),0) AS spend
     FROM users u JOIN orders o ON o.user_id = u.id
     WHERE o.status <> 'cancelled'
     GROUP BY u.id, u.username
     ORDER BY spend DESC
     LIMIT 6"
);

$byStatus = $db->all('SELECT status, COUNT(*) AS n FROM orders GROUP BY status ORDER BY n DESC');
$statusLabels = array_map(static fn ($r) => (string) $r['status'], $byStatus);
$statusCounts = array_map(static fn ($r) => (int) $r['n'], $byStatus);

$recentOrders = $db->all(
    'SELECT o.id, o.total_cents, o.status, o.placed_at, u.username
     FROM orders o JOIN users u ON u.id = o.user_id
     ORDER BY o.placed_at DESC, o.id DESC LIMIT 8'
);

$lowStock = $db->all(
    'SELECT title, stock FROM products WHERE is_active = 1 AND stock <= 10 ORDER BY stock ASC, title ASC LIMIT 6'
);

render_header('Admin dashboard', true, shop_nav());
?>
<div class="wrap">
  <?php render_flashes(); ?>
  <h1>Admin dashboard</h1>

  <div class="grid-3">
    <div class="card stat"><b><?= $stats['products'] ?></b><span>Active products</span></div>
    <div class="card stat"><b><?= $stats['out_of_stock'] ?></b><span>Out of stock</span></div>
    <div class="card stat"><b><?= $stats['orders'] ?></b><span>Orders</span></div>
    <div class="card stat"><b><?= $stats['pending'] ?></b><span>Awaiting action</span></div>
    <div class="card stat"><b><?= $stats['customers'] ?></b><span>Customers</span></div>
    <div class="card stat"><b>&#8377;<?= number_format($stats['revenue'] / 100) ?></b><span>Revenue</span></div>
  </div>

  <div class="charts">
    <div class="card">
      <h2 class="h5">Orders per day (last 14 days)</h2>
      <p class="muted small">Cancellations are excluded.</p>
      <div class="chart-box"><canvas id="ordersChart" role="img"
        aria-label="Line chart of orders per day over the last 14 days"></canvas></div>
      <table class="visually-hidden">
        <caption>Orders per day, same data as the chart above</caption>
        <thead><tr><th scope="col">Day</th><th scope="col">Orders</th><th scope="col">Revenue</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse(array_keys($orderSeries)) as $i): ?>
          <tr><td><?= e($labels[$i]) ?></td><td><?= $orderSeries[$i] ?></td><td>&#8377;<?= number_format($revenueSeries[$i], 2) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2 class="h5">Orders by status</h2>
      <div class="chart-box"><canvas id="statusChart" role="img"
        aria-label="Doughnut chart of orders by status"></canvas></div>
      <ul class="tags">
        <?php foreach ($byStatus as $s): ?>
          <li><?= e((string) $s['status']) ?>: <?= (int) $s['n'] ?></li>
        <?php endforeach; ?>
        <?php if (!$byStatus): ?><li>No orders yet</li><?php endif; ?>
      </ul>
    </div>
  </div>

  <div class="section-head"><h2 class="h4">Most active customers</h2></div>
  <?php if ($topCustomers): ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th scope="col">Customer</th><th scope="col">Orders</th><th scope="col">Spend</th></tr></thead>
        <tbody>
        <?php foreach ($topCustomers as $c): ?>
          <tr>
            <th scope="row"><?= e((string) $c['username']) ?></th>
            <td><?= (int) $c['orders'] ?></td>
            <td>&#8377;<?= money((int) $c['spend']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="card"><p class="muted">No orders have been placed yet.</p></div>
  <?php endif; ?>

  <div class="section-head"><h2 class="h4">Recent orders</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Total</th><th scope="col">Status</th><th scope="col">Placed</th><th scope="col"></th></tr></thead>
      <tbody>
      <?php if (!$recentOrders): ?><tr><td colspan="6">No orders yet.</td></tr><?php endif; ?>
      <?php foreach ($recentOrders as $o): ?>
        <tr>
          <th scope="row">#<?= (int) $o['id'] ?></th>
          <td><?= e((string) $o['username']) ?></td>
          <td>&#8377;<?= money((int) $o['total_cents']) ?></td>
          <td><span class="badge"><?= e((string) $o['status']) ?></span></td>
          <td class="small"><?= e((string) $o['placed_at']) ?></td>
          <td><a class="btn btn-sm btn-ghost" href="<?= e(task_url('admin/orders.php')) ?>?id=<?= (int) $o['id'] ?>">Manage</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="section-head"><h2 class="h4">Low stock</h2></div>
  <?php if ($lowStock): ?>
    <ul class="tags">
      <?php foreach ($lowStock as $p): ?>
        <li><?= e((string) $p['title']) ?> — <?= (int) $p['stock'] ?> left</li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <div class="card"><p class="muted">Every active product has more than 10 in stock.</p></div>
  <?php endif; ?>
</div>

<script src="../../vendor-assets/chartjs-4.4.1/chart.umd.js"></script>
<script>
(function () {
  'use strict';

  var palette = {
    indigo: '#5b8cff',
    violet: '#8b5cf6',
    cyan:   '#22d3ee',
    green:  '#34d399',
    amber:  '#fbbf24',
    rose:   '#fb7185',
  };

  var statusColours = [palette.amber, palette.indigo, palette.green, palette.rose];

  var common = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { labels: { color: '#a7b0cc', boxWidth: 14 } }
    },
    scales: {
      x: { ticks: { color: '#a7b0cc' }, grid: { color: 'rgba(255,255,255,.06)' } },
      y: { beginAtZero: true, ticks: { color: '#a7b0cc', precision: 0 }, grid: { color: 'rgba(255,255,255,.06)' } }
    }
  };

  var ordersCanvas = document.getElementById('ordersChart');
  if (ordersCanvas && window.Chart) {
    new Chart(ordersCanvas, {
      type: 'line',
      data: {
        labels: <?= json_out($labels) ?>,
        datasets: [
          {
            label: 'Orders',
            data: <?= json_out($orderSeries) ?>,
            borderColor: palette.indigo,
            backgroundColor: 'rgba(91,140,255,.18)',
            fill: true,
            tension: .3,
            pointRadius: 3
          },
          {
            label: 'Revenue (INR)',
            data: <?= json_out(array_map(static fn ($v) => round((float) $v, 2), $revenueSeries)) ?>,
            borderColor: palette.cyan,
            backgroundColor: 'transparent',
            borderDash: [6, 4],
            tension: .3,
            pointRadius: 2,
            yAxisID: 'y1'
          }
        ]
      },
      options: Object.assign({}, common, {
        scales: {
          x: common.scales.x,
          y: common.scales.y,
          y1: {
            beginAtZero: true,
            position: 'right',
            grid: { display: false },
            ticks: { color: palette.cyan, precision: 0 }
          }
        }
      })
    });
  }

  var statusCanvas = document.getElementById('statusChart');
  if (statusCanvas && window.Chart) {
    new Chart(statusCanvas, {
      type: 'doughnut',
      data: {
        labels: <?= json_out($statusLabels) ?>,
        datasets: [{
          data: <?= json_out($statusCounts) ?>,
          backgroundColor: statusColours,
          borderColor: '#1a2340',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { color: '#a7b0cc', boxWidth: 14 } } }
      }
    });
  }
})();
</script>
<?php render_footer(); ?>
