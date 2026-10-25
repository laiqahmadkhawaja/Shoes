<?php
// product_edit.php - Edit Shoe Model and Manage its Size-Color Variants
require_once __DIR__ . '/includes/auth_check.php';
require_permission(['admin', 'manager', 'inventory']);

$db = getDB();
$product_id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    die("Product not found.");
}

$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$brands = $db->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();
$sizes = $db->query("SELECT * FROM sizes ORDER BY sort_order ASC, size_number ASC")->fetchAll();
$colors = $db->query("SELECT * FROM colors ORDER BY name ASC")->fetchAll();

$error = '';
$success = '';

// Handle Product Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $name = trim($_POST['name'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $gender = clean($_POST['gender'] ?? 'men');
    $base_purchase_price = (float)($_POST['base_purchase_price'] ?? 0);
    $base_selling_price = (float)($_POST['base_selling_price'] ?? 0);
    $wholesale_price = (float)($_POST['wholesale_price'] ?? 0);
    $min_selling_price = (float)($_POST['min_selling_price'] ?? 0);
    $rack_location = trim($_POST['rack_location'] ?? '');
    $shelf_location = trim($_POST['shelf_location'] ?? '');
    $box_number = trim($_POST['box_number'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $error = "Shoe name is required.";
    } else {
        $stmt = $db->prepare("UPDATE products SET 
            name = ?, category_id = ?, brand_id = ?, gender = ?, 
            base_purchase_price = ?, base_selling_price = ?, wholesale_price = ?, min_selling_price = ?, 
            rack_location = ?, shelf_location = ?, box_number = ?, description = ? 
            WHERE id = ?");
        $stmt->execute([
            $name, $category_id, $brand_id, $gender,
            $base_purchase_price, $base_selling_price, $wholesale_price, $min_selling_price,
            $rack_location, $shelf_location, $box_number, $description,
            $product_id
        ]);
        log_audit('UPDATE', 'PRODUCT', $product_id, "Updated details for {$product['article_code']}");
        $success = "Shoe details updated successfully!";

        // Refresh product
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
    }
}

// Handle Update Individual Variant Row
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_variant_row'])) {
    $variant_id = (int)$_POST['variant_id'];
    $variant_cost = (float)$_POST['variant_cost'];
    $variant_price = (float)$_POST['variant_price'];
    $variant_stock = (int)$_POST['variant_stock'];

    $stmt = $db->prepare("UPDATE product_variants SET purchase_price = ?, selling_price = ?, stock_qty = ? WHERE id = ? AND product_id = ?");
    $stmt->execute([$variant_cost, $variant_price, $variant_stock, $variant_id, $product_id]);
    $success = "Variant stock & pricing updated!";
}

// Handle Add Single Variant to this Shoe Model
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_single_variant'])) {
    $size_id = (int)$_POST['new_size_id'];
    $color_id = (int)$_POST['new_color_id'];
    $purchase_price = (float)$_POST['new_purchase_price'];
    $selling_price = (float)$_POST['new_selling_price'];
    $stock_qty = (int)$_POST['new_stock_qty'];

    // Check duplicate
    $chk = $db->prepare("SELECT id FROM product_variants WHERE product_id = ? AND size_id = ? AND color_id = ?");
    $chk->execute([$product_id, $size_id, $color_id]);
    if ($chk->fetch()) {
        $error = "This size and color combination already exists for this shoe model.";
    } else {
        // Generate SKU & Barcode
        $s_row = $db->query("SELECT size_number FROM sizes WHERE id = $size_id")->fetch();
        $c_row = $db->query("SELECT name FROM colors WHERE id = $color_id")->fetch();
        $color_prefix = strtoupper(substr($c_row['name'], 0, 3));
        $sku = clean($product['article_code']) . "-{$color_prefix}-{$s_row['size_number']}";
        $barcode = '89' . str_pad($product_id, 4, '0', STR_PAD_LEFT) . str_pad($size_id, 2, '0', STR_PAD_LEFT) . str_pad($color_id, 2, '0', STR_PAD_LEFT);

        $ins = $db->prepare("INSERT INTO product_variants (product_id, size_id, color_id, sku, barcode, purchase_price, selling_price, stock_qty) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$product_id, $size_id, $color_id, $sku, $barcode, $purchase_price, $selling_price, $stock_qty]);
        $success = "New variant added successfully!";
    }
}

// Fetch existing variants
$stmt = $db->prepare("SELECT pv.*, sz.size_number, cl.name as color_name, cl.hex_code 
                      FROM product_variants pv 
                      JOIN sizes sz ON pv.size_id = sz.id 
                      JOIN colors cl ON pv.color_id = cl.id 
                      WHERE pv.product_id = ? 
                      ORDER BY cl.name ASC, sz.sort_order ASC, sz.size_number ASC");
$stmt->execute([$product_id]);
$variants = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-dark fs-6"><?= clean($product['article_code']) ?></span>
                <h2 class="h3 fw-bold text-dark mb-0"><?= clean($product['name']) ?></h2>
            </div>
            <p class="text-muted small mb-0 mt-1">Manage shoe model details, rack locations, and size-color variant inventories</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#addVariantModal">
                <i class="fa-solid fa-plus-circle me-1"></i> Add Single Variant
            </button>
            <a href="products.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Catalog
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm mb-4">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= clean($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success shadow-sm mb-4">
            <i class="fa-solid fa-circle-check me-2"></i> <?= clean($success) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- Left: Model Master Info -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3 p-4 bg-white">
                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                    <i class="fa-solid fa-pen-to-square text-primary me-2"></i> Edit Master Details
                </h5>
                <form method="POST" action="product_edit.php?id=<?= $product_id ?>">
                    <input type="hidden" name="update_product" value="1">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Shoe Name *</label>
                        <input type="text" name="name" class="form-control" required value="<?= clean($product['name']) ?>">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold text-secondary mb-0">Brand</label>
                                <button type="button" class="btn btn-link p-0 text-primary small text-decoration-none fw-semibold" data-bs-toggle="modal" data-bs-target="#quickBrandModal">
                                    <i class="fa-solid fa-plus-circle me-1"></i>New
                                </button>
                            </div>
                            <select name="brand_id" id="brand_select" class="form-select">
                                <option value="">None</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= $product['brand_id'] == $b['id'] ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold text-secondary mb-0">Category</label>
                                <button type="button" class="btn btn-link p-0 text-primary small text-decoration-none fw-semibold" data-bs-toggle="modal" data-bs-target="#quickCategoryModal">
                                    <i class="fa-solid fa-plus-circle me-1"></i>New
                                </button>
                            </div>
                            <select name="category_id" id="category_select" class="form-select">
                                <option value="">None</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= clean($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Cost Price (Rs.)</label>
                            <input type="number" step="0.01" name="base_purchase_price" class="form-control" value="<?= clean($product['base_purchase_price']) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Selling Price (Rs.) *</label>
                            <input type="number" step="0.01" name="base_selling_price" class="form-control" required value="<?= clean($product['base_selling_price']) ?>">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Wholesale Price (Rs.)</label>
                            <input type="number" step="0.01" name="wholesale_price" class="form-control" value="<?= clean($product['wholesale_price']) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Min Selling Price (Rs.)</label>
                            <input type="number" step="0.01" name="min_selling_price" class="form-control" value="<?= clean($product['min_selling_price']) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Target Gender</label>
                        <select name="gender" class="form-select">
                            <option value="men" <?= $product['gender'] === 'men' ? 'selected' : '' ?>>Men's</option>
                            <option value="women" <?= $product['gender'] === 'women' ? 'selected' : '' ?>>Women's</option>
                            <option value="kids" <?= $product['gender'] === 'kids' ? 'selected' : '' ?>>Kids</option>
                            <option value="unisex" <?= $product['gender'] === 'unisex' ? 'selected' : '' ?>>Unisex</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label small fw-bold text-secondary">Rack</label>
                            <input type="text" name="rack_location" class="form-control" value="<?= clean($product['rack_location'] ?? '') ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-bold text-secondary">Shelf</label>
                            <input type="text" name="shelf_location" class="form-control" value="<?= clean($product['shelf_location'] ?? '') ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-bold text-secondary">Box #</label>
                            <input type="text" name="box_number" class="form-control" value="<?= clean($product['box_number'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= clean($product['description'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="fa-solid fa-save me-1"></i> Update Shoe Details
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Size & Color Variants Table -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-3 bg-white p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-boxes-stacked text-primary me-2"></i> Variants Stock &amp; Pricing
                        </h5>
                        <span class="small text-muted"><?= count($variants) ?> total size-color variants configured</span>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addVariantModal">
                        <i class="fa-solid fa-plus me-1"></i> Add Variant
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Color</th>
                                <th>Size</th>
                                <th>Barcode</th>
                                <th style="width: 100px;">Cost (Rs.)</th>
                                <th style="width: 100px;">Price (Rs.)</th>
                                <th style="width: 90px;" class="text-center">Stock</th>
                                <th class="text-center" style="width: 60px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($variants as $v): ?>
                                <tr>
                                    <form method="POST" action="product_edit.php?id=<?= $product_id ?>">
                                        <input type="hidden" name="update_variant_row" value="1">
                                        <input type="hidden" name="variant_id" value="<?= $v['id'] ?>">
                                        <td>
                                            <span class="color-swatch-sm" style="background-color: <?= clean($v['hex_code']) ?>;"></span>
                                            <?= clean($v['color_name']) ?>
                                        </td>
                                        <td class="fw-bold fs-6"><?= clean($v['size_number']) ?></td>
                                        <td><code><?= clean($v['barcode']) ?></code></td>
                                        <td>
                                            <input type="number" step="0.01" name="variant_cost" class="form-control form-control-sm p-1" value="<?= $v['purchase_price'] ?>">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="variant_price" class="form-control form-control-sm p-1 fw-bold text-primary" value="<?= $v['selling_price'] ?>">
                                        </td>
                                        <td>
                                            <input type="number" name="variant_stock" class="form-control form-control-sm p-1 text-center fw-bold" value="<?= $v['stock_qty'] ?>">
                                        </td>
                                        <td class="text-center">
                                            <button type="submit" class="btn btn-sm btn-outline-primary p-1" title="Save Row">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Single Variant -->
<div class="modal fade" id="addVariantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">Add Single Variant to <?= clean($product['article_code']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="product_edit.php?id=<?= $product_id ?>">
                <input type="hidden" name="add_single_variant" value="1">
                <div class="modal-body p-3">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Select Color *</label>
                            <select name="new_color_id" class="form-select" required>
                                <?php foreach ($colors as $cl): ?>
                                    <option value="<?= $cl['id'] ?>"><?= clean($cl['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Select Size *</label>
                            <select name="new_size_id" class="form-select" required>
                                <?php foreach ($sizes as $sz): ?>
                                    <option value="<?= $sz['id'] ?>"><?= clean($sz['size_number']) ?> (<?= clean($sz['size_system']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Cost Price (Rs.)</label>
                            <input type="number" step="0.01" name="new_purchase_price" class="form-control" value="<?= $product['base_purchase_price'] ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Selling Price (Rs.) *</label>
                            <input type="number" step="0.01" name="new_selling_price" class="form-control" required value="<?= $product['base_selling_price'] ?>">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Initial Stock (Pairs)</label>
                        <input type="number" name="new_stock_qty" class="form-control" value="0">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold">Add Variant</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Quick Add Brand -->
<div class="modal fade" id="quickBrandModal" tabindex="-1" aria-labelledby="quickBrandModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold" id="quickBrandModalLabel">
                    <i class="fa-solid fa-tags me-2"></i> Add New Shoe Brand
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div id="brand_alert" class="alert alert-danger py-1 px-2 small d-none mb-2"></div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Brand Name *</label>
                    <input type="text" id="quick_brand_name" class="form-control" placeholder="e.g. Woodland, Puma, Clarks" required autocomplete="off">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Brand Description / Origin (Optional)</label>
                    <input type="text" id="quick_brand_desc" class="form-control" placeholder="e.g. Premium leather or sports shoes">
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btn_save_brand" class="btn btn-sm btn-primary fw-semibold px-3">
                    <i class="fa-solid fa-check me-1"></i> Save Brand
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Quick Add Category -->
<div class="modal fade" id="quickCategoryModal" tabindex="-1" aria-labelledby="quickCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold" id="quickCategoryModalLabel">
                    <i class="fa-solid fa-folder-plus me-2"></i> Add New Shoe Category
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div id="category_alert" class="alert alert-danger py-1 px-2 small d-none mb-2"></div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Category Name *</label>
                    <input type="text" id="quick_category_name" class="form-control" placeholder="e.g. Loafers, Moccasins, Safety Boots" required autocomplete="off">
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btn_save_category" class="btn btn-sm btn-primary fw-semibold px-3">
                    <i class="fa-solid fa-check me-1"></i> Save Category
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Quick Add Brand AJAX
document.getElementById('btn_save_brand').addEventListener('click', function() {
    const nameInput = document.getElementById('quick_brand_name');
    const descInput = document.getElementById('quick_brand_desc');
    const alertBox = document.getElementById('brand_alert');
    const name = nameInput.value.trim();
    const desc = descInput.value.trim();

    if (!name) {
        alertBox.innerText = 'Please enter a brand name.';
        alertBox.classList.remove('d-none');
        return;
    }
    alertBox.classList.add('d-none');
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

    fetch('ajax_catalog.php?action=add_brand', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `name=${encodeURIComponent(name)}&description=${encodeURIComponent(desc)}`
    })
    .then(res => res.json())
    .then(data => {
        this.disabled = false;
        this.innerHTML = '<i class="fa-solid fa-check me-1"></i> Save Brand';
        if (data.success && data.brand) {
            const brandSelect = document.getElementById('brand_select');
            let opt = document.createElement('option');
            opt.value = data.brand.id;
            opt.text = data.brand.name;
            opt.selected = true;
            brandSelect.appendChild(opt);

            nameInput.value = '';
            descInput.value = '';
            const modalEl = document.getElementById('quickBrandModal');
            bootstrap.Modal.getInstance(modalEl).hide();
        } else {
            alertBox.innerText = data.message || 'Error saving brand';
            alertBox.classList.remove('d-none');
        }
    })
    .catch(err => {
        this.disabled = false;
        this.innerHTML = '<i class="fa-solid fa-check me-1"></i> Save Brand';
        alertBox.innerText = 'Network connection error';
        alertBox.classList.remove('d-none');
    });
});

// Quick Add Category AJAX
document.getElementById('btn_save_category').addEventListener('click', function() {
    const nameInput = document.getElementById('quick_category_name');
    const alertBox = document.getElementById('category_alert');
    const name = nameInput.value.trim();

    if (!name) {
        alertBox.innerText = 'Please enter a category name.';
        alertBox.classList.remove('d-none');
        return;
    }
    alertBox.classList.add('d-none');
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

    fetch('ajax_catalog.php?action=add_category', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `name=${encodeURIComponent(name)}`
    })
    .then(res => res.json())
    .then(data => {
        this.disabled = false;
        this.innerHTML = '<i class="fa-solid fa-check me-1"></i> Save Category';
        if (data.success && data.category) {
            const catSelect = document.getElementById('category_select');
            let opt = document.createElement('option');
            opt.value = data.category.id;
            opt.text = data.category.name;
            opt.selected = true;
            catSelect.appendChild(opt);

            nameInput.value = '';
            const modalEl = document.getElementById('quickCategoryModal');
            bootstrap.Modal.getInstance(modalEl).hide();
        } else {
            alertBox.innerText = data.message || 'Error saving category';
            alertBox.classList.remove('d-none');
        }
    })
    .catch(err => {
        this.disabled = false;
        this.innerHTML = '<i class="fa-solid fa-check me-1"></i> Save Category';
        alertBox.innerText = 'Network connection error';
        alertBox.classList.remove('d-none');
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
