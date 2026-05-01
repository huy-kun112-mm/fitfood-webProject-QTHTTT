<?php
/**
 * FitFood — Admin Dashboard
 * Truy cập: http://localhost:8080/admin/
 *
 * Render server-side bằng PDO. Không cần npm/Node — Bootstrap & icons
 * đều load từ CDN, custom CSS nằm ở assets/css/style.css.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/partials/auth_guard.php';

// ---------- Helpers ----------
function vnd($n): string {
    return number_format((float)$n, 0, ',', '.') . '₫';
}

function time_ago_vi(?string $ts): string {
    if (!$ts) return '';
    $diff = time() - strtotime($ts);
    if ($diff < 60)            return 'Vừa xong';
    if ($diff < 3600)          return floor($diff / 60)    . ' phút trước';
    if ($diff < 86400)         return floor($diff / 3600)  . ' giờ trước';
    if ($diff < 86400 * 30)    return floor($diff / 86400) . ' ngày trước';
    if ($diff < 86400 * 365)   return floor($diff / (86400 * 30))  . ' tháng trước';
    return floor($diff / (86400 * 365)) . ' năm trước';
}

function status_badge(string $status): string {
    $map = [
        'completed'  => ['Hoàn tất',   'success'],
        'processing' => ['Đang xử lý', 'primary'],
        'pending'    => ['Chờ duyệt',  'warning'],
        'cancelled'  => ['Đã huỷ',     'danger'],
    ];
    [$label, $color] = $map[$status] ?? [$status, 'secondary'];
    return '<span class="badge bg-' . $color . '-subtle text-' . $color . '">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '</span>';
}

function effective_price(array $p): float {
    return $p['sale_price'] !== null ? (float)$p['sale_price'] : (float)$p['price'];
}

// ---------- Queries ----------
$total_sales   = 0.0;
$total_orders  = 0;
$top_selling   = [];
$low_stock     = [];
$recent_sales  = [];
$query_error   = null;

// Tạm hardcode — khi có form nhập sẽ query từ DB.
// Total Purchase = chi phí nguyên vật liệu cho cửa hàng.
// Total Expenses = chi phí vận hành (điện, nước, lương, mặt bằng, …).
$total_purchase = 8000000;
$total_expenses = 3000000;

if ($pdo === null) {
    $query_error = 'Không thể kết nối database. Kiểm tra docker-compose hoặc config/database.php.';
} else {
    try {
        // 1. Tổng tiền các đơn ĐÃ BÁN (status = completed)
        $total_sales = (float) $pdo->query(
            "SELECT COALESCE(SUM(total_amount), 0)
             FROM orders
             WHERE status = 'completed'"
        )->fetchColumn();

        $total_orders = (int) $pdo->query(
            "SELECT COUNT(*) FROM orders WHERE status = 'completed'"
        )->fetchColumn();

        // 2. Top 5 sản phẩm bán chạy (theo sold_count denormalized)
        $top_selling = $pdo->query(
            "SELECT id, name, image_url, price, sale_price, sold_count, unit
             FROM products
             WHERE is_active = 1 AND sold_count > 0
             ORDER BY sold_count DESC, id ASC
             LIMIT 5"
        )->fetchAll();

        // 3. Top 5 sản phẩm gần hết hàng (stock thấp nhất, vẫn còn active)
        $low_stock = $pdo->query(
            "SELECT id, name, image_url, stock, unit
             FROM products
             WHERE is_active = 1
             ORDER BY stock ASC, id ASC
             LIMIT 5"
        )->fetchAll();

        // 4. 5 đơn gần đây nhất (kèm sản phẩm đầu tiên trong mỗi đơn)
        $recent_sales = $pdo->query(
            "SELECT o.id, o.total_amount, o.status, o.created_at,
                    p.name        AS product_name,
                    p.image_url   AS product_image,
                    c.name        AS category_name
             FROM orders o
             INNER JOIN (
                 SELECT order_id, MIN(id) AS first_id
                 FROM order_items
                 GROUP BY order_id
             ) f               ON f.order_id  = o.id
             INNER JOIN order_items oi ON oi.id = f.first_id
             INNER JOIN products    p  ON p.id  = oi.product_id
             LEFT  JOIN categories  c  ON c.id  = p.category_id
             ORDER BY o.created_at DESC
             LIMIT 5"
        )->fetchAll();
    } catch (PDOException $e) {
        $query_error = 'Lỗi truy vấn DB: ' . $e->getMessage();
        error_log('[admin] ' . $e->getMessage());
    }
}

$total_profit    = $total_sales - $total_purchase - $total_expenses;
$placeholder_img = './assets/images/logo-icon.svg';
$active_page     = 'dashboard';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <title>Dashboard - FitFood Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="apple-touch-icon" sizes="180x180" href="./assets/images/favicon_io/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32"  href="./assets/images/favicon_io/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16"  href="./assets/images/favicon_io/favicon-16x16.png">
  <link rel="manifest" href="./assets/images/favicon_io/site.webmanifest">

  <!-- CDN: Bootstrap 5.3 + Tabler Icons + Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.35.0/dist/tabler-icons.min.css" rel="stylesheet">

  <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body>

<div id="overlay" class="overlay"></div>

<!-- TOPBAR -->
<nav id="topbar" class="navbar bg-white border-bottom fixed-top topbar px-3">
  <button id="toggleBtn" class="d-none d-lg-inline-flex btn btn-light btn-icon btn-sm">
    <i class="ti ti-layout-sidebar-left-expand"></i>
  </button>
  <button id="mobileBtn" class="btn btn-light btn-icon btn-sm d-lg-none me-2">
    <i class="ti ti-layout-sidebar-left-expand"></i>
  </button>

  <div>
    <ul class="list-unstyled d-flex align-items-center mb-0 gap-1">
      <li>
        <a class="position-relative btn-icon btn-sm btn-light btn rounded-circle" href="#" role="button">
          <i class="ti ti-bell"></i>
        </a>
      </li>
      <li class="ms-3 dropdown">
        <a href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="./assets/images/avatar/avatar-1.jpg" alt="" class="avatar avatar-sm rounded-circle">
        </a>
        <div class="dropdown-menu dropdown-menu-end p-0" style="min-width: 200px;">
          <div class="d-flex gap-3 align-items-center border-bottom px-3 py-3">
            <img src="./assets/images/avatar/avatar-1.jpg" alt="" class="avatar avatar-md rounded-circle">
            <div>
              <h4 class="mb-0 small">Admin FitFood</h4>
              <p class="mb-0 small text-muted">@admin</p>
            </div>
          </div>
          <div class="p-3 d-flex flex-column gap-1 small lh-lg">
            <a href="../index.php" class="text-body">Về trang chủ</a>
            <a href="../logout.php" class="text-body">Đăng xuất</a>
          </div>
        </div>
      </li>
    </ul>
  </div>
</nav>

<!-- SIDEBAR (shared partial) -->
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<!-- MAIN CONTENT -->
<main id="content" class="content py-10">
  <div class="container-fluid pt-5">

    <div class="row mt-4">
      <div class="col-12">
        <div class="mb-4">
          <h1 class="fs-3 mb-1">Dashboard</h1>
          <p class="text-muted">Tổng quan doanh thu và tồn kho FitFood.</p>
        </div>
      </div>
    </div>

    <?php if ($query_error): ?>
      <div class="alert alert-danger" role="alert">
        <i class="ti ti-alert-triangle me-2"></i>
        <?= htmlspecialchars($query_error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- KPI CARDS -->
    <div class="row g-3 mb-3">
      <!-- Total Sales (REAL DATA) -->
      <div class="col-lg-3 col-12">
        <div class="card p-4 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-2 h-100">
          <div class="d-flex gap-3">
            <div class="icon-shape icon-md bg-primary text-white rounded-2">
              <i class="ti ti-report-analytics fs-4"></i>
            </div>
            <div>
              <h2 class="mb-3 fs-6">Total Sales</h2>
              <h3 class="fw-bold mb-0"><?= htmlspecialchars(vnd($total_sales)) ?></h3>
              <p class="text-primary mb-0 small">
                Từ <?= (int)$total_orders ?> đơn đã hoàn tất
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Total Purchase (chi phí nguyên vật liệu, tạm hardcode) -->
      <div class="col-lg-3 col-12">
        <div class="card p-4 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-2 h-100">
          <div class="d-flex gap-3">
            <div class="icon-shape icon-md bg-success text-white rounded-2">
              <i class="ti ti-repeat fs-4"></i>
            </div>
            <div>
              <h2 class="mb-3 fs-6">Total Purchase</h2>
              <h3 class="fw-bold mb-0"><?= htmlspecialchars(vnd($total_purchase)) ?></h3>
              <p class="text-success mb-0 small">Chi phí nguyên vật liệu</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Total Expenses (chi phí vận hành, tạm hardcode) -->
      <div class="col-lg-3 col-12">
        <div class="card p-4 bg-info bg-opacity-10 border border-info border-opacity-25 rounded-2 h-100">
          <div class="d-flex gap-3">
            <div class="icon-shape icon-md bg-info text-white rounded-2">
              <i class="ti ti-currency-dollar fs-4"></i>
            </div>
            <div>
              <h2 class="mb-3 fs-6">Total Expenses</h2>
              <h3 class="fw-bold mb-0"><?= htmlspecialchars(vnd($total_expenses)) ?></h3>
              <p class="text-info mb-0 small">Chi phí vận hành cửa hàng</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Total Profit = Sales - Purchase - Expenses -->
      <div class="col-lg-3 col-12">
        <div class="card p-4 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-2 h-100">
          <div class="d-flex gap-3">
            <div class="icon-shape icon-md bg-warning text-white rounded-2">
              <i class="ti ti-coin fs-4"></i>
            </div>
            <div>
              <h2 class="mb-3 fs-6">Total Profit</h2>
              <h3 class="fw-bold mb-0 <?= $total_profit < 0 ? 'text-danger' : '' ?>">
                <?= htmlspecialchars(vnd($total_profit)) ?>
              </h3>
              <p class="text-warning mb-0 small">Sales − Purchase − Expenses</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CHART ROW: Sales Overview (line) + Customers Overview (donut) -->
    <div class="row g-3 mb-3">
      <div class="col-12 col-lg-6">
        <div class="card h-100">
          <div class="card-header d-flex justify-content-between align-items-center bg-transparent px-4 py-3">
            <h3 class="h5 mb-0">Sales Overview</h3>
            <select class="form-select form-select-sm w-auto" disabled>
              <option selected>This Year</option>
            </select>
          </div>
          <div class="card-body p-4">
            <div id="salesChart"></div>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-6">
        <div class="card h-100">
          <div class="card-header d-flex justify-content-between align-items-center bg-transparent px-4 py-3">
            <h3 class="h5 mb-0">Overall Information</h3>
            <select class="form-select form-select-sm w-auto" disabled>
              <option selected>Last 6 Months</option>
            </select>
          </div>
          <div class="card-body p-4">
            <h3 class="h6">Customers Overview</h3>
            <div class="row align-items-center">
              <div class="col-sm-6">
                <div id="customerChart"></div>
              </div>
              <div class="col-sm-6">
                <div class="row">
                  <div class="col-6 border-end">
                    <div class="text-center">
                      <h2 class="mb-1">5.5K</h2>
                      <p class="text-success mb-2">First Time</p>
                      <span class="badge bg-success"><i class="ti ti-arrow-up-left me-1"></i>25%</span>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="text-center">
                      <h2 class="mb-1">3.5K</h2>
                      <p class="text-warning mb-2">Return</p>
                      <span class="badge bg-success"><i class="ti ti-arrow-up-left me-1"></i>21%</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="row text-center border-top mt-4 pt-4">
              <div class="col-4 border-end">
                <h3 class="fw-bold mb-2">6987</h3>
                <small class="text-secondary">Suppliers</small>
              </div>
              <div class="col-4 border-end">
                <h3 class="fw-bold mb-2">4896</h3>
                <small class="text-secondary">Customers</small>
              </div>
              <div class="col-4">
                <h3 class="fw-bold mb-2">487</h3>
                <small class="text-secondary">Orders</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 3 LISTS: Top Selling / Low Stock / Recent Sales -->
    <div class="row g-3">

      <!-- CARD 1 — Top Selling Products -->
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header bg-white d-flex justify-content-between align-items-center px-4 py-3">
            <h4 class="mb-0 h5">Sản phẩm bán chạy</h4>
            <span class="badge bg-light text-secondary">
              <i class="ti ti-trending-up me-1"></i>Top 5
            </span>
          </div>

          <ul class="list-group list-group-flush">
            <?php if (empty($top_selling)): ?>
              <li class="list-group-item text-center text-muted py-4">Chưa có dữ liệu</li>
            <?php else: ?>
              <?php foreach ($top_selling as $p):
                $price = effective_price($p);
                $img = $p['image_url'] ?: $placeholder_img;
              ?>
                <li class="list-group-item d-flex align-items-center gap-3">
                  <img src="<?= htmlspecialchars($img) ?>" class="rounded" width="48" height="48" style="object-fit:cover;" alt="">
                  <div class="flex-grow-1">
                    <p class="mb-1"><?= htmlspecialchars($p['name']) ?></p>
                    <div class="d-flex align-items-center gap-2 text-muted">
                      <small class="fw-semibold"><?= htmlspecialchars(vnd($price)) ?></small>
                      <small>•</small>
                      <small>Đã bán <?= (int)$p['sold_count'] ?></small>
                    </div>
                  </div>
                  <span class="badge bg-primary-subtle text-primary border border-primary">
                    <?= (int)$p['sold_count'] ?>
                  </span>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>
      </div>

      <!-- CARD 2 — Low Stock Products -->
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header bg-white d-flex justify-content-between align-items-center px-4 py-3">
            <h4 class="mb-0 h5">Sản phẩm gần hết hàng</h4>
            <span class="badge bg-light text-secondary">
              <i class="ti ti-alert-triangle me-1"></i>Top 5
            </span>
          </div>

          <ul class="list-group list-group-flush">
            <?php if (empty($low_stock)): ?>
              <li class="list-group-item text-center text-muted py-4">Chưa có dữ liệu</li>
            <?php else: ?>
              <?php foreach ($low_stock as $p):
                $img = $p['image_url'] ?: $placeholder_img;
                $stock = (int)$p['stock'];
                $stock_color = $stock <= 5 ? 'danger' : ($stock <= 15 ? 'warning' : 'primary');
              ?>
                <li class="list-group-item d-flex align-items-center gap-3">
                  <img src="<?= htmlspecialchars($img) ?>" class="rounded" width="48" height="48" style="object-fit:cover;" alt="">
                  <div class="flex-grow-1">
                    <p class="mb-1"><?= htmlspecialchars($p['name']) ?></p>
                    <small class="text-muted">ID: #<?= str_pad((string)$p['id'], 6, '0', STR_PAD_LEFT) ?></small>
                  </div>
                  <div class="d-flex flex-column gap-0 align-items-center">
                    <span class="fw-semibold text-<?= $stock_color ?>"><?= str_pad((string)$stock, 2, '0', STR_PAD_LEFT) ?></span>
                    <small class="text-muted">Còn lại</small>
                  </div>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>
      </div>

      <!-- CARD 3 — Recent Sales -->
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header bg-white d-flex justify-content-between align-items-center px-4 py-3">
            <h4 class="mb-0 h5">Bán gần đây</h4>
            <span class="badge bg-light text-secondary">
              <i class="ti ti-calendar-event me-1"></i>5 đơn mới nhất
            </span>
          </div>

          <ul class="list-group list-group-flush">
            <?php if (empty($recent_sales)): ?>
              <li class="list-group-item text-center text-muted py-4">Chưa có đơn nào</li>
            <?php else: ?>
              <?php foreach ($recent_sales as $r):
                $img = $r['product_image'] ?: $placeholder_img;
              ?>
                <li class="list-group-item d-flex align-items-center gap-3">
                  <img src="<?= htmlspecialchars($img) ?>" class="rounded" width="48" height="48" style="object-fit:cover;" alt="">
                  <div class="flex-grow-1">
                    <p class="mb-1"><?= htmlspecialchars($r['product_name']) ?></p>
                    <div class="d-flex align-items-center gap-2 text-muted">
                      <small class="fw-semibold"><?= htmlspecialchars($r['category_name'] ?? '—') ?></small>
                      <small>•</small>
                      <small><?= htmlspecialchars(vnd($r['total_amount'])) ?></small>
                    </div>
                    <small class="text-muted"><?= htmlspecialchars(time_ago_vi($r['created_at'])) ?></small>
                  </div>
                  <?= status_badge($r['status']) ?>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>
      </div>

    </div>

    <div class="row mt-3">
      <div class="col-12">
        <footer class="text-center py-2 mt-6 text-secondary">
          <p class="mb-0 small">© 2026 FitFood Admin. Template ban đầu bởi
            <a href="https://codescandy.com/" target="_blank" class="text-primary">CodesCandy</a>.
          </p>
        </footer>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@5.3.6/dist/apexcharts.min.js"></script>
<script src="./assets/js/main.js"></script>
<script src="./assets/js/charts.js"></script>
</body>
</html>
