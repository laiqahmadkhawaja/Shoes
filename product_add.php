<?php
// product_add.php - Add New Shoe Model with Size-Color Matrix Generator
require_once __DIR__ . '/includes/auth_check.php';
require_permission(['admin', 'manager', 'inventory']);

$db = getDB();
$error = '';
$success = '';

// Load categories, brands, sizes, colors
$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$brands = $db->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();
$sizes = $db->query("SELECT * FROM sizes ORDER BY sort_order ASC, size_number ASC")->fetchAll();
$colors = $db->query("SELECT * FROM colors ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $article_code = strtoupper(trim($_POST['article_code'] ?? ''));
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

    // Selected colors and sizes
    $selected_colors = $_POST['colors'] ?? []; // array of color_ids
    $selected_sizes = $_POST['sizes'] ?? [];   // array of size_ids
    $initial_stock_default = (int)($_POST['initial_stock_default'] ?? 0);

    if (empty($article_code) || empty($name)) {
        $error = "Article Code and Shoe Name are required.";
    } elseif ($base_selling_price <= 0) {
        $error = "Selling price must be greater than zero.";
    } elseif (empty($selected_colors) || empty($selected_sizes)) {
        $error = "Please select at least one Color and one Size for the shoe model.";
    } else {
        // Check duplicate article code
        $stmt = $db->prepare("SELECT id FROM products WHERE article_code = ?");
        $stmt->execute([$article_code]);
        if ($stmt->fetch()) {
            $error = "A shoe with Article Code '{$article_code}' already exists. Please use a unique article code.";
        } else {
            try {
                $db->beginTransaction();

                // 1. Insert Product
                $stmt = $db->prepare("INSERT INTO products (article_code, name, category_id, brand_id, gender, description, base_purchase_price, base_selling_price, wholesale_price, min_selling_price, rack_location, shelf_location, box_number) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $article_code, $name, $category_id, $brand_id, $gender, $description,
                    $base_purchase_price, $base_selling_price, $wholesale_price, $min_selling_price,
                    $rack_location, $shelf_location, $box_number
                ]);
                $product_id = $db->lastInsertId();

                // 2. Generate Variants for each Color x Size combination
                $var_stmt = $db->prepare("INSERT INTO product_variants (product_id, size_id, color_id, sku, barcode, purchase_price, selling_price, stock_qty, alert_qty) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 2)");

                $created_count = 0;
                foreach ($selected_colors as $color_id) {
                    // Fetch color name for SKU
                    $c_stmt = $db->prepare("SELECT name FROM colors WHERE id = ?");
                    $c_stmt->execute([(int)$color_id]);
                    $c_name = strtoupper(substr($c_stmt->fetchColumn() ?: 'COL', 0, 3));

                    foreach ($selected_sizes as $size_id) {
                        $s_stmt = $db->prepare("SELECT size_number FROM sizes WHERE id = ?");
                        $s_stmt->execute([(int)$size_id]);
                        $s_num = $s_stmt->fetchColumn() ?: '00';

                        // Specific stock for this variant if provided, else default
                        $var_stock = isset($_POST['variant_stock'][$color_id][$size_id]) ? (int)$_POST['variant_stock'][$color_id][$size_id] : $initial_stock_default;
                        if ($var_stock < 0) $var_stock = 0;

                        // Generate SKU & Unique Barcode
                        $sku = "{$article_code}-{$c_name}-{$s_num}";
                        // Barcode: 890 + numeric product_id (padded 4) + color_id (2) + size_id (2)
                        $barcode = sprintf("890%04d%02d%02d", $product_id, (int)$color_id, (int)$size_id);

                        $var_stmt->execute([
                            $product_id,
                            (int)$size_id,
                            (int)$color_id,
                            $sku,
                            $barcode,
                            $base_purchase_price,
                            $base_selling_price,
                            $var_stock
                        ]);
                        $created_count++;
                    }
                }

                log_audit('PRODUCT_CREATE', 'PRODUCTS', $product_id, "Created shoe {$article_code} ({$name}) with {$created_count} size/color variants.");

                $db->commit();
                set_flash('success', "Shoe model '{$article_code} - {$name}' created successfully with {$created_count} variants!");
                header("Location: products.php");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-plus-circle text-primary me-2"></i>Add Shoe Article
            </h2>
            <p class="text-muted small mb-0">Define shoe model specifications and auto-generate size-color inventory matrix</p>
        </div>
        <a href="products.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Products
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm mb-4">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= clean($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="product_add.php" id="product_form">
        <div class="row g-4">
            <!-- Left Card: Basic Information & Pricing -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-4 bg-white h-100">
                    <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                        <i class="fa-solid fa-tag text-primary me-2"></i> 1. Model Details &amp; Pricing
                    </h5>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Article Code *</label>
                            <input type="text" name="article_code" class="form-control text-uppercase fw-bold" placeholder="e.g. ART-101" required value="<?= clean($_POST['article_code'] ?? '') ?>">
                            <div class="form-text">Unique model code printed on shoe box</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Shoe / Model Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Classic Derby Formal" required value="<?= clean($_POST['name'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold text-secondary mb-0">Brand</label>
                                <button type="button" class="btn btn-link p-0 text-primary small text-decoration-none fw-semibold" data-bs-toggle="modal" data-bs-target="#quickBrandModal">
                                    <i class="fa-solid fa-plus-circle me-1"></i>New
                                </button>
                            </div>
                            <select name="brand_id" id="brand_select" class="form-select">
                                <option value="">Select Brand</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= clean($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold text-secondary mb-0">Category</label>
                                <button type="button" class="btn btn-link p-0 text-primary small text-decoration-none fw-semibold" data-bs-toggle="modal" data-bs-target="#quickCategoryModal">
                                    <i class="fa-solid fa-plus-circle me-1"></i>New
                                </button>
                            </div>
                            <select name="category_id" id="category_select" class="form-select">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= clean($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary mb-1">Target Gender</label>
                            <select name="gender" class="form-select">
                                <option value="men">Men's Footwear</option>
                                <option value="women">Women's Footwear</option>
                                <option value="kids">Kids</option>
                                <option value="unisex">Unisex</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Base Purchase Cost (Rs.)</label>
                            <input type="number" step="0.01" name="base_purchase_price" class="form-control" placeholder="0.00" value="<?= clean($_POST['base_purchase_price'] ?? '2000.00') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Retail Selling Price (Rs.) *</label>
                            <input type="number" step="0.01" name="base_selling_price" class="form-control fw-bold text-primary" placeholder="0.00" required value="<?= clean($_POST['base_selling_price'] ?? '3200.00') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Wholesale Price (Rs.)</label>
                            <input type="number" step="0.01" name="wholesale_price" class="form-control" placeholder="0.00" value="<?= clean($_POST['wholesale_price'] ?? '2800.00') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Min Selling Price (Rs.)</label>
                            <input type="number" step="0.01" name="min_selling_price" class="form-control" placeholder="0.00" value="<?= clean($_POST['min_selling_price'] ?? '2900.00') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Rack</label>
                            <input type="text" name="rack_location" class="form-control" placeholder="Rack A">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Shelf</label>
                            <input type="text" name="shelf_location" class="form-control" placeholder="Shelf 1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Box Number</label>
                            <input type="text" name="box_number" class="form-control" placeholder="BOX-01">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Shoe Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Leather type, sole material, features..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Right Card: Size & Color Matrix Generator -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-4 bg-white h-100">
                    <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                        <i class="fa-solid fa-table-cells text-primary me-2"></i> 2. Size &amp; Color Matrix Generator
                    </h5>

                    <!-- Step A: Choose Colors -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary d-flex justify-content-between align-items-center">
                            <span>Select Color Variations:</span>
                            <span class="text-muted small">Select one or more</span>
                        </label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($colors as $c): ?>
                                <label class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2">
                                    <input type="checkbox" name="colors[]" value="<?= $c['id'] ?>" class="form-check-input mt-0 color-checkbox" <?= in_array($c['name'], ['Black', 'Brown']) ? 'checked' : '' ?>>
                                    <span class="color-swatch-sm" style="background-color: <?= clean($c['hex_code']) ?>;"></span>
                                    <span><?= clean($c['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Step B: Choose Sizes -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-bold text-secondary mb-0">Select Shoe Sizes:</label>
                            <div>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none me-2" onclick="toggleSizes('EU')">EU 39-45</button>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none me-2" onclick="toggleSizes('ALL')">All</button>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-danger" onclick="toggleSizes('NONE')">Clear</button>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($sizes as $s): ?>
                                <label class="btn btn-outline-primary btn-sm px-3 py-2 text-center" style="min-width: 65px;">
                                    <input type="checkbox" name="sizes[]" value="<?= $s['id'] ?>" data-size="<?= clean($s['size_number']) ?>" 
                                           class="form-check-input mt-0 me-1 size-checkbox" <?= in_array($s['size_number'], ['39', '40', '41', '42', '43', '44']) ? 'checked' : '' ?>>
                                    <span class="fw-bold"><?= clean($s['size_number']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Default Initial Stock per Size -->
                    <div class="mb-4 p-3 bg-light rounded-3 border">
                        <label class="form-label small fw-bold text-secondary">Default Initial Stock (Pairs per Variant):</label>
                        <div class="input-group">
                            <input type="number" name="initial_stock_default" class="form-control" value="4" min="0">
                            <span class="input-group-text">Pairs</span>
                        </div>
                        <div class="form-text">e.g. Setting '4' will automatically initialize 4 pairs for each selected size-color variant.</div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-3 border-top mt-auto">
                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow-sm">
                            <i class="fa-solid fa-check me-2"></i> Save Shoe &amp; Generate Variants Matrix
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
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
function toggleSizes(mode) {
    const checkboxes = document.querySelectorAll('.size-checkbox');
    checkboxes.forEach(cb => {
        let size = parseInt(cb.getAttribute('data-size'));
        if (mode === 'ALL') {
            cb.checked = true;
        } else if (mode === 'NONE') {
            cb.checked = false;
        } else if (mode === 'EU') {
            cb.checked = (size >= 39 && size <= 45);
        }
    });
}

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
