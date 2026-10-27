<?php
// settings.php - System Configuration & Store Profile
require_once __DIR__ . '/includes/auth_check.php';
require_permission(['admin']);

$db = getDB();
$error = '';
$success = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings_to_update = [
        'shop_name' => trim($_POST['shop_name'] ?? ''),
        'tagline' => trim($_POST['tagline'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'currency' => clean($_POST['currency'] ?? 'PKR'),
        'currency_symbol' => clean($_POST['currency_symbol'] ?? 'Rs.'),
        'tax_rate' => (float)($_POST['tax_rate'] ?? 0),
        'return_policy_days' => (int)($_POST['return_policy_days'] ?? 7),
        'print_type' => clean($_POST['print_type'] ?? 'thermal'),
        'thermal_width' => (int)($_POST['thermal_width'] ?? 80),
        'receipt_footer' => trim($_POST['receipt_footer'] ?? '')
    ];

    foreach ($settings_to_update as $k => $v) {
        update_setting($k, $v);
    }

    log_audit('SETTINGS_UPDATE', 'SETTINGS', 1, "Shop settings updated");
    $success = "Store settings and print templates updated successfully!";
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-sliders text-primary me-2"></i>Settings &amp; System Configuration
            </h2>
            <p class="text-muted small mb-0">Shop profile, receipt return policies, and printing modes</p>
        </div>
        <div class="d-flex gap-2">
            <a href="backup.php" class="btn btn-outline-dark fw-bold">
                <i class="fa-solid fa-database me-1"></i> Database Backup
            </a>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= clean($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success shadow-sm mb-4"><i class="fa-solid fa-circle-check me-2"></i><?= clean($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="settings.php">
        <div class="row g-4">
            <!-- Left Card: Shop Profile -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-4 bg-white h-100">
                    <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                        <i class="fa-solid fa-shop text-primary me-2"></i>Store Identity &amp; Contact
                    </h5>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Shop Name *</label>
                        <input type="text" name="shop_name" class="form-control fw-bold" required value="<?= clean(get_setting('shop_name')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Shop Tagline / Slogan</label>
                        <input type="text" name="tagline" class="form-control" value="<?= clean(get_setting('tagline')) ?>">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Phone / WhatsApp</label>
                            <input type="text" name="phone" class="form-control" value="<?= clean(get_setting('phone')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= clean(get_setting('email')) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Store Physical Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= clean(get_setting('address')) ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Currency Code</label>
                            <input type="text" name="currency" class="form-control" value="<?= clean(get_setting('currency', 'PKR')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Currency Symbol</label>
                            <input type="text" name="currency_symbol" class="form-control" value="<?= clean(get_setting('currency_symbol', 'Rs.')) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Card: POS, Policy & Receipt Settings -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-4 bg-white h-100">
                    <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                        <i class="fa-solid fa-receipt text-primary me-2"></i>Receipt &amp; Return Policy
                    </h5>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Default Print Format</label>
                            <select name="print_type" class="form-select">
                                <option value="thermal" <?= get_setting('print_type') === 'thermal' ? 'selected' : '' ?>>Thermal Receipt</option>
                                <option value="a4" <?= get_setting('print_type') === 'a4' ? 'selected' : '' ?>>Full A4 Invoice</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Thermal Roll Width</label>
                            <select name="thermal_width" class="form-select">
                                <option value="80" <?= get_setting('thermal_width', '80') == '80' ? 'selected' : '' ?>>80mm Roll (72mm Printable)</option>
                                <option value="58" <?= get_setting('thermal_width', '80') == '58' ? 'selected' : '' ?>>58mm Roll (48mm Printable)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Return / Exchange Days</label>
                            <div class="input-group">
                                <input type="number" name="return_policy_days" class="form-control" value="<?= clean(get_setting('return_policy_days', '7')) ?>" min="1" max="60">
                                <span class="input-group-text">Days</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Sales Tax / GST (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="tax_rate" class="form-control" value="<?= clean(get_setting('tax_rate', '0.00')) ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Receipt Footer Note</label>
                        <textarea name="receipt_footer" class="form-control" rows="3"><?= clean(get_setting('receipt_footer')) ?></textarea>
                    </div>

                    <div class="pt-3 border-top mt-auto text-end">
                        <button type="submit" class="btn btn-primary px-5 py-3 fw-bold shadow-sm">
                            <i class="fa-solid fa-check me-2"></i> Save All Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
