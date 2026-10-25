<?php
// products.php - Shoe Products & Variants Catalog
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();

// Handle filter parameters
$q = trim($_GET['q'] ?? '');
$brand_id = (int)($_GET['brand_id'] ?? 0);
$category_id = (int)($_GET['category_id'] ?? 0);
$gender = clean($_GET['gender'] ?? '');

$sql = "SELECT p.*, b.name as brand_name, c.name as category_name,
               (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) as variant_count,
               (SELECT COALESCE(SUM(stock_qty), 0) FROM product_variants WHERE product_id = p.id) as total_stock,
               (SELECT COALESCE(SUM(damaged_qty), 0) FROM product_variants WHERE product_id = p.id) as total_damaged
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE 1=1 ";
$params = [];

if (!empty($q)) {
    $sql .= " AND (p.name LIKE ? OR p.article_code LIKE ? OR p.rack_location LIKE ?)";
    $term = "%{$q}%";
    $params = array_merge($params, [$term, $term, $term]);
}
if ($brand_id > 0) {
    $sql .= " AND p.brand_id = ?";
    $params[] = $brand_id;
}
if ($category_id > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_id;
}
if (!empty($gender)) {
    $sql .= " AND p.gender = ?";
    $params[] = $gender;
}

$sql .= " ORDER BY p.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Brands & Categories for filters
$brands = $db->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();
$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Products Catalog
            </h2>
            <p class="text-muted small mb-0">Manage shoe models, article codes, size-color variants, and warehouse locations</p>
        </div>
        <div class="d-flex gap-2">
            <a href="product_add.php" class="btn btn-primary fw-bold shadow-sm">
                <i class="fa-solid fa-plus-circle me-1"></i> Add Shoe Article
            </a>
            <a href="barcode_labels.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-barcode me-1"></i> Barcode Labels
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white mb-4">
        <form method="GET" action="products.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search by Article Code, Name, Rack..." value="<?= clean($q) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="category_id" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                            <?= clean($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="brand_id" class="form-select">
                    <option value="">All Brands</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $brand_id == $b['id'] ? 'selected' : '' ?>>
                            <?= clean($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="gender" class="form-select">
                    <option value="">All Genders</option>
                    <option value="men" <?= $gender == 'men' ? 'selected' : '' ?>>Men</option>
                    <option value="women" <?= $gender == 'women' ? 'selected' : '' ?>>Women</option>
                    <option value="kids" <?= $gender == 'kids' ? 'selected' : '' ?>>Kids</option>
                    <option value="unisex" <?= $gender == 'unisex' ? 'selected' : '' ?>>Unisex</option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <a href="products.php" class="btn btn-outline-secondary" title="Reset"><i class="fa-solid fa-arrow-rotate-right"></i></a>
            </div>
        </form>
    </div>

    <!-- Products Table -->
    <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 120px;">Article #</th>
                        <th>Shoe Name &amp; Category</th>
                        <th>Brand &amp; Gender</th>
                        <th>Cost / Retail Price</th>
                        <th class="text-center">Variants</th>
                        <th class="text-center">Total Stock (Pairs)</th>
                        <th>Rack / Box</th>
                        <th class="text-center" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No shoe products found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-dark fs-6 px-2 py-1"><?= clean($p['article_code']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= clean($p['name']) ?></div>
                                    <div class="small text-muted"><?= clean($p['category_name'] ?? 'Uncategorized') ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-secondary"><?= clean($p['brand_name'] ?? 'Generic') ?></div>
                                    <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.7rem;"><?= clean($p['gender']) ?></span>
                                </td>
                                <td>
                                    <div>Cost: <strong class="text-muted"><?= format_currency($p['base_purchase_price']) ?></strong></div>
                                    <div>Sell: <strong class="text-primary"><?= format_currency($p['base_selling_price']) ?></strong></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= $p['variant_count'] ?> sizes/colors</span>
                                </td>
                                <td class="text-center">
                                    <?php if ($p['total_stock'] <= 0): ?>
                                        <span class="badge bg-danger fs-6 px-3">0 Pairs</span>
                                    <?php elseif ($p['total_stock'] <= 5): ?>
                                        <span class="badge bg-warning text-dark fs-6 px-3"><?= $p['total_stock'] ?> Pairs</span>
                                    <?php else: ?>
                                        <span class="badge bg-success fs-6 px-3"><?= $p['total_stock'] ?> Pairs</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= !empty($p['rack_location']) ? clean($p['rack_location']) : '-' ?>
                                    <?= !empty($p['box_number']) ? ' / ' . clean($p['box_number']) : '' ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="product_edit.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Edit Shoe &amp; Variants">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <a href="barcode_labels.php?product_id=<?= $p['id'] ?>" class="btn btn-outline-dark" title="Print Barcodes">
                                            <i class="fa-solid fa-barcode"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
