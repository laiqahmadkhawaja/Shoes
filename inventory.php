<?php
// inventory.php - Shoe Inventory & Size Matrix Management
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();

// Filters
$q = trim($_GET['q'] ?? '');
$category_id = (int)($_GET['category_id'] ?? 0);
$brand_id = (int)($_GET['brand_id'] ?? 0);
$filter_status = clean($_GET['filter'] ?? ''); // 'low', 'out', 'in'

// Overall Stock Stats
$stat = $db->query("SELECT 
    COALESCE(SUM(stock_qty), 0) as total_pairs,
    COALESCE(SUM(damaged_qty), 0) as total_damaged,
    COALESCE(SUM(stock_qty * purchase_price), 0) as total_cost_value,
    COALESCE(SUM(stock_qty * selling_price), 0) as total_retail_value,
    COUNT(CASE WHEN stock_qty <= alert_qty AND stock_qty > 0 THEN 1 END) as low_stock_count,
    COUNT(CASE WHEN stock_qty = 0 THEN 1 END) as out_of_stock_count
FROM product_variants")->fetch();

// Standard Shoe Sizes for Matrix columns
$matrix_sizes = $db->query("SELECT id, size_number FROM sizes WHERE size_system = 'EU' ORDER BY sort_order ASC, size_number ASC")->fetchAll();

// Fetch Products with their Colors for Matrix View
$matrix_sql = "SELECT p.id as product_id, p.article_code, p.name as product_name, p.base_selling_price,
                      b.name as brand_name, c.name as category_name,
                      cl.id as color_id, cl.name as color_name, cl.hex_code
               FROM products p
               JOIN product_variants pv ON p.id = pv.product_id
               JOIN colors cl ON pv.color_id = cl.id
               LEFT JOIN brands b ON p.brand_id = b.id
               LEFT JOIN categories c ON p.category_id = c.id
               WHERE 1=1 ";
$params = [];

if (!empty($q)) {
    $matrix_sql .= " AND (p.name LIKE ? OR p.article_code LIKE ?)";
    $term = "%{$q}%";
    $params = array_merge($params, [$term, $term]);
}
if ($category_id > 0) {
    $matrix_sql .= " AND p.category_id = ?";
    $params[] = $category_id;
}
if ($brand_id > 0) {
    $matrix_sql .= " AND p.brand_id = ?";
    $params[] = $brand_id;
}

$matrix_sql .= " GROUP BY p.id, cl.id ORDER BY p.id DESC, cl.name ASC";
$stmt = $db->prepare($matrix_sql);
$stmt->execute($params);
$matrix_rows = $stmt->fetchAll();

// Cache all variant stocks into lookup array [product_id][color_id][size_id]
$all_variants = $db->query("SELECT product_id, color_id, size_id, stock_qty, damaged_qty, alert_qty, barcode, selling_price, purchase_price FROM product_variants")->fetchAll();
$stock_lookup = [];
foreach ($all_variants as $v) {
    $stock_lookup[$v['product_id']][$v['color_id']][$v['size_id']] = $v;
}

// Categories & Brands for dropdown
$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$brands = $db->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-table-cells text-primary me-2"></i>Shoe Inventory &amp; Size Matrix
            </h2>
            <p class="text-muted small mb-0">Article-wise size distribution, variant stocks, and stock valuation audit</p>
        </div>
        <div class="d-flex gap-2">
            <a href="stock_adjust.php" class="btn btn-primary fw-bold shadow-sm">
                <i class="fa-solid fa-sliders me-1"></i> Stock Adjustment
            </a>
            <a href="purchase_add.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-cart-plus me-1"></i> Receive Stock
            </a>
        </div>
    </div>

    <!-- Stock Valuation KPI Cards -->
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                <span class="text-muted small fw-semibold text-uppercase">Total Pairs</span>
                <h4 class="fw-bold text-dark mt-2 mb-0"><?= number_format($stat['total_pairs']) ?> <small class="fs-6 fw-normal text-muted">Pairs</small></h4>
                <span class="small text-muted mt-1"><?= $stat['total_damaged'] ?> pairs damaged</span>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                <span class="text-muted small fw-semibold text-uppercase">Valuation (Cost)</span>
                <h4 class="fw-bold text-primary mt-2 mb-0"><?= format_currency($stat['total_cost_value']) ?></h4>
                <span class="small text-muted mt-1">Landed investment</span>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                <span class="text-muted small fw-semibold text-uppercase">Valuation (Retail)</span>
                <h4 class="fw-bold text-success mt-2 mb-0"><?= format_currency($stat['total_retail_value']) ?></h4>
                <span class="small text-success mt-1">Margin: <?= format_currency($stat['total_retail_value'] - $stat['total_cost_value']) ?></span>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                <span class="text-muted small fw-semibold text-uppercase">Stock Health</span>
                <h4 class="fw-bold <?= $stat['out_of_stock_count'] > 0 ? 'text-danger' : 'text-warning' ?> mt-2 mb-0">
                    <?= $stat['out_of_stock_count'] ?> <small class="fs-6 fw-normal text-muted">Empty</small> / <?= $stat['low_stock_count'] ?> <small class="fs-6 fw-normal text-muted">Low</small>
                </h4>
                <span class="small text-muted mt-1">Variants needing restock</span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white mb-4">
        <form method="GET" action="inventory.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search by Article Code, Model Name..." value="<?= clean($q) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="category_id" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>><?= clean($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="brand_id" class="form-select">
                    <option value="">All Brands</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $brand_id == $b['id'] ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <a href="inventory.php" class="btn btn-outline-secondary" title="Reset"><i class="fa-solid fa-arrow-rotate-right"></i></a>
            </div>
        </form>
    </div>

    <!-- SIZE MATRIX VIEW TABLE -->
    <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="fa-solid fa-grid-horizontal text-primary me-2"></i>Article-Wise Shoe Size Matrix
                </h5>
                <span class="small text-muted">Every cell shows pairs currently available in shop</span>
            </div>
            <div class="d-flex align-items-center gap-3 small">
                <span><span class="badge bg-success me-1">&nbsp;</span> In Stock (&gt;2)</span>
                <span><span class="badge bg-warning text-dark me-1">&nbsp;</span> Low (1-2)</span>
                <span><span class="badge bg-danger me-1">&nbsp;</span> Out of Stock (0)</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0 table-matrix">
                <thead class="table-dark">
                    <tr>
                        <th style="min-width: 110px;">Article #</th>
                        <th style="min-width: 180px; text-align: left;">Shoe Model</th>
                        <th style="min-width: 120px;">Color</th>
                        <?php foreach ($matrix_sizes as $ms): ?>
                            <th style="min-width: 50px;">Sz <?= clean($ms['size_number']) ?></th>
                        <?php endforeach; ?>
                        <th style="min-width: 80px;" class="bg-primary text-white">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($matrix_rows)): ?>
                        <tr><td colspan="<?= count($matrix_sizes) + 4 ?>" class="text-center py-5 text-muted">No shoes found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($matrix_rows as $row): 
                            $row_total = 0;
                        ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= clean($row['article_code']) ?></td>
                                <td class="text-start">
                                    <div class="fw-bold text-dark"><?= clean($row['product_name']) ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?= clean($row['brand_name'] ?? 'Generic') ?> &bull; Rs. <?= number_format($row['base_selling_price'], 0) ?></div>
                                </td>
                                <td>
                                    <span class="color-swatch-sm" style="background-color: <?= clean($row['hex_code']) ?>;"></span>
                                    <span class="fw-semibold"><?= clean($row['color_name']) ?></span>
                                </td>
                                <?php foreach ($matrix_sizes as $ms): 
                                    $v = $stock_lookup[$row['product_id']][$row['color_id']][$ms['id']] ?? null;
                                    if ($v !== null):
                                        $qty = (int)$v['stock_qty'];
                                        $row_total += $qty;
                                        if ($qty <= 0) {
                                            $cellClass = 'bg-danger bg-opacity-10 text-danger fw-bold';
                                        } elseif ($qty <= (int)$v['alert_qty']) {
                                            $cellClass = 'bg-warning bg-opacity-25 text-dark fw-bold';
                                        } else {
                                            $cellClass = 'bg-success bg-opacity-10 text-success fw-bold';
                                        }
                                ?>
                                    <td class="<?= $cellClass ?>" title="Barcode: <?= clean($v['barcode']) ?>">
                                        <?= $qty ?>
                                    </td>
                                <?php else: ?>
                                    <td class="text-muted bg-light">&mdash;</td>
                                <?php endif; ?>
                                <?php endforeach; ?>
                                <td class="fw-bold fs-6 bg-light text-primary"><?= $row_total ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
