<?php
// barcode_labels.php - Printable Shoe Box Barcode Label Generator
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();

$product_id = (int)($_GET['product_id'] ?? 0);
$variant_id = (int)($_GET['variant_id'] ?? 0);
$label_qty = (int)($_GET['qty'] ?? 6);

// Fetch products for selector
$products = $db->query("SELECT p.id, p.article_code, p.name FROM products p ORDER BY p.article_code ASC")->fetchAll();

// Fetch variants for the selected product
$variants = [];
if ($product_id > 0) {
    $stmt = $db->prepare("SELECT pv.*, sz.size_number, cl.name as color_name, p.article_code, p.name as product_name 
                          FROM product_variants pv 
                          JOIN products p ON pv.product_id = p.id 
                          JOIN sizes sz ON pv.size_id = sz.id 
                          JOIN colors cl ON pv.color_id = cl.id 
                          WHERE pv.product_id = ?");
    $stmt->execute([$product_id]);
    $variants = $stmt->fetchAll();
}

// If specific variant chosen
$selected_variant = null;
if ($variant_id > 0) {
    $stmt = $db->prepare("SELECT pv.*, sz.size_number, cl.name as color_name, p.article_code, p.name as product_name 
                          FROM product_variants pv 
                          JOIN products p ON pv.product_id = p.id 
                          JOIN sizes sz ON pv.size_id = sz.id 
                          JOIN colors cl ON pv.color_id = cl.id 
                          WHERE pv.id = ?");
    $stmt->execute([$variant_id]);
    $selected_variant = $stmt->fetch();
}

$shop_name = get_setting('shop_name', 'StepStyle Footwear');

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <!-- Controls (Hidden on print) -->
    <div class="no-print">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h3 fw-bold text-dark mb-1">
                    <i class="fa-solid fa-barcode text-primary me-2"></i>Shoe Box Barcode Labels Generator
                </h2>
                <p class="text-muted small mb-0">Print scannable Code-128 barcode stickers for shoe boxes and display soles</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-success fw-bold shadow-sm">
                    <i class="fa-solid fa-print me-1"></i> Print Barcode Labels
                </button>
                <a href="products.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Products
                </a>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card border-0 shadow-sm rounded-3 p-4 bg-white mb-4">
            <form method="GET" action="barcode_labels.php" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-secondary">Select Shoe Model:</label>
                    <select name="product_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Choose Shoe Model --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $product_id == $p['id'] ? 'selected' : '' ?>>
                                <?= clean($p['article_code']) ?> &mdash; <?= clean($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary">Specific Size/Color (Optional):</label>
                    <select name="variant_id" class="form-select">
                        <option value="">All Sizes &amp; Colors for Model</option>
                        <?php foreach ($variants as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= $variant_id == $v['id'] ? 'selected' : '' ?>>
                                Size <?= clean($v['size_number']) ?> &bull; <?= clean($v['color_name']) ?> (<?= clean($v['barcode']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary">Copies per item:</label>
                    <input type="number" name="qty" class="form-control" value="<?= $label_qty ?>" min="1" max="50">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Generate</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Printable Barcode Sheet Container -->
    <div class="card border-0 shadow-sm rounded-3 p-4 bg-white">
        <div class="no-print d-flex justify-content-between align-items-center pb-2 border-bottom mb-3">
            <span class="small text-muted fw-bold">Preview Label Sheet (50mm x 35mm Standard Shoe Label Format):</span>
            <span class="badge bg-light text-dark border">Print Ready</span>
        </div>

        <div class="d-flex flex-wrap gap-2 justify-content-start" id="barcode_print_area">
            <?php 
            $labels_to_render = [];
            if ($selected_variant) {
                for ($i = 0; $i < $label_qty; $i++) {
                    $labels_to_render[] = $selected_variant;
                }
            } elseif (!empty($variants)) {
                foreach ($variants as $v) {
                    for ($i = 0; $i < $label_qty; $i++) {
                        $labels_to_render[] = $v;
                    }
                }
            }
            ?>

            <?php if (empty($labels_to_render)): ?>
                <div class="text-center py-5 text-muted w-100">
                    <i class="fa-solid fa-barcode fs-1 text-black-50 mb-2"></i>
                    <p>Please select a shoe model above to generate printable barcode labels.</p>
                </div>
            <?php else: ?>
                <?php foreach ($labels_to_render as $idx => $lbl): ?>
                    <div class="barcode-label-box text-center shadow-sm">
                        <div class="fw-bold text-uppercase" style="font-size: 10px; line-height: 1.1;"><?= clean($shop_name) ?></div>
                        <div class="fw-bold text-dark text-truncate" style="font-size: 11px; margin-top: 1px;"><?= clean($lbl['product_name']) ?></div>
                        <div class="d-flex justify-content-between px-1" style="font-size: 10px; font-weight: 600;">
                            <span>Art: <strong><?= clean($lbl['article_code']) ?></strong></span>
                            <span>Sz: <strong class="fs-6"><?= clean($lbl['size_number']) ?></strong></span>
                        </div>
                        <div style="font-size: 9px; color: #475569;">Color: <?= clean($lbl['color_name']) ?></div>
                        
                        <div style="margin: 2px 0;">
                            <svg class="barcode-svg" data-code="<?= clean($lbl['barcode']) ?>"></svg>
                        </div>

                        <div class="fw-bold text-primary" style="font-size: 12px; line-height: 1;">
                            Rs. <?= number_format($lbl['selling_price'], 0) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.barcode-svg').forEach(el => {
        let code = el.getAttribute('data-code');
        JsBarcode(el, code, {
            format: "CODE128",
            width: 1.2,
            height: 28,
            displayValue: true,
            fontSize: 10,
            margin: 0
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
