<?php
// pos.php - Point of Sale (POS) Interface
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();

// Categories for category filter buttons
$categories = $db->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
// Brands
$brands = $db->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();
// Walk-in customer details
$stmt = $db->query("SELECT * FROM customers WHERE id = 1 LIMIT 1");
$walk_in_customer = $stmt->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-2 pos-wrapper">
    <div class="row g-3">
        <!-- Left Column: Catalog & Barcode Scanner -->
        <div class="col-lg-7 col-xl-8">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white mb-3">
                <!-- Barcode & Search Controls -->
                <div class="row g-2 align-items-center">
                    <div class="col-md-8">
                        <div class="input-group input-group-lg shadow-sm">
                            <span class="input-group-text bg-primary text-white border-primary px-2 px-sm-3">
                                <i class="fa-solid fa-barcode fs-5"></i>
                            </span>
                            <input type="text" id="pos_search_input" class="form-control border-primary" 
                                   placeholder="Scan Barcode or Search Shoes... [F1]" 
                                   autocomplete="off" autofocus>
                            <button class="btn btn-outline-secondary px-2 px-sm-3" type="button" id="btn_clear_search" title="Clear Search">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                            <button class="btn btn-primary d-flex align-items-center gap-1 px-2 px-sm-3" type="button" id="btn_open_camera_scanner" title="Scan Barcode via Camera (F3)">
                                <i class="fa-solid fa-camera"></i>
                                <span class="d-none d-sm-inline fw-semibold">Camera</span>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <select id="pos_brand_filter" class="form-select form-select-lg">
                            <option value="">All Brands</option>
                            <?php foreach ($brands as $b): ?>
                                <option value="<?= clean($b['name']) ?>"><?= clean($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Category Pills Filter -->
                <div class="d-flex gap-2 overflow-x-auto py-2 mt-2" style="white-space: nowrap;">
                    <button class="btn btn-sm btn-dark category-filter-btn active" data-cat="0">All Shoes</button>
                    <?php foreach ($categories as $cat): ?>
                        <button class="btn btn-sm btn-outline-secondary category-filter-btn" data-cat="<?= $cat['id'] ?>">
                            <?= clean($cat['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Shoe Products Grid -->
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white" style="min-height: 520px;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold">Click any shoe model to choose Size &amp; Color:</span>
                    <span id="product_count_badge" class="badge bg-light text-dark border">Loading...</span>
                </div>

                <div id="pos_products_grid" class="row g-2 overflow-y-auto" style="max-height: 580px;">
                    <!-- Populated via AJAX -->
                </div>
            </div>
        </div>

        <!-- Right Column: Current Sale / Cart Panel -->
        <div class="col-lg-5 col-xl-4" id="pos_cart_container">
            <div class="pos-cart-panel p-3">
                <!-- Customer Selection Bar -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold text-secondary mb-0">
                            <i class="fa-solid fa-user-tag me-1"></i> Customer &amp; Khata:
                        </label>
                        <button class="btn btn-link btn-sm p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#quickCustomerModal">
                            <i class="fa-solid fa-user-plus me-1"></i> New Customer
                        </button>
                    </div>
                    <div class="input-group">
                        <select id="pos_customer_select" class="form-select">
                            <option value="1" data-balance="0.00" data-limit="0.00">Walk-in Customer (Counter Sale)</option>
                            <!-- Additional customers loaded dynamically -->
                        </select>
                        <span id="cust_balance_badge" class="input-group-text bg-light text-muted small">Bal: Rs. 0.00</span>
                    </div>
                </div>

                <!-- Cart Items Table Header -->
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom fw-bold small text-secondary">
                    <span>Shoe &amp; Variant</span>
                    <span class="text-center" style="width: 100px;">Qty</span>
                    <span class="text-end" style="width: 80px;">Total</span>
                    <span style="width: 25px;"></span>
                </div>

                <!-- Cart Items List Container -->
                <div id="pos_cart_items" class="pos-cart-items py-2">
                    <div id="empty_cart_msg" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-bag-shopping fs-1 text-black-50 mb-2"></i>
                        <p class="mb-0">Cart is empty.<br>Scan barcode or click a shoe to add sizes.</p>
                    </div>
                </div>

                <!-- Cart Summary & Calculations -->
                <div class="pos-cart-total p-3">
                    <div class="d-flex justify-content-between mb-1 small text-muted">
                        <span>Subtotal:</span>
                        <span id="pos_subtotal" class="fw-semibold text-dark">Rs. 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1 small text-muted">
                        <div class="d-flex align-items-center gap-1">
                            <span>Discount:</span>
                            <input type="number" id="pos_discount_input" class="form-control form-control-sm text-end" style="width: 80px;" value="0" min="0">
                        </div>
                        <span id="pos_discount_display" class="text-danger">-Rs. 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small text-muted">
                        <span>Tax (0%):</span>
                        <span id="pos_tax" class="fw-semibold text-dark">Rs. 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="h5 fw-bold mb-0">Total Due:</span>
                        <span id="pos_grand_total" class="h3 fw-bold text-primary mb-0">Rs. 0.00</span>
                    </div>

                    <!-- POS Action Buttons -->
                    <div class="row g-2 mt-3">
                        <div class="col-8">
                            <button id="btn_checkout" class="btn btn-primary w-100 py-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2">
                                <i class="fa-solid fa-money-bill-1-wave fs-5"></i>
                                <span>PAY NOW [F2]</span>
                            </button>
                        </div>
                        <div class="col-4">
                            <button id="btn_hold_bill" class="btn btn-outline-warning w-100 py-3 fw-bold" title="Hold Current Bill [F4]">
                                <i class="fa-solid fa-pause"></i> Hold [F4]
                            </button>
                        </div>
                        <div class="col-6">
                            <button id="btn_resume_bills" class="btn btn-sm btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#heldBillsModal">
                                <i class="fa-solid fa-clock-rotate-left me-1"></i> Held Bills (<span id="held_count">0</span>)
                            </button>
                        </div>
                        <div class="col-6">
                            <button id="btn_clear_cart" class="btn btn-sm btn-outline-danger w-100">
                                <i class="fa-solid fa-trash me-1"></i> Clear Bill
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Floating Bottom Checkout Bar (sticky on mobile when cart has items) -->
<div id="mobile_cart_bar" class="d-lg-none position-fixed bottom-0 start-0 end-0 bg-dark text-white p-2 px-3 shadow-lg d-none align-items-center justify-content-between z-3" style="border-top: 2px solid #2563eb;">
    <div class="d-flex align-items-center gap-2">
        <span class="p-1 bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:28px; height:28px;">
            <i class="fa-solid fa-cart-shopping small"></i>
        </span>
        <div>
            <span id="mobile_cart_count" class="badge bg-primary">0 items</span>
            <span id="mobile_cart_total" class="fw-bold ms-1 text-white fs-6">Rs. 0.00</span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-light" onclick="document.getElementById('pos_cart_container').scrollIntoView({behavior: 'smooth'})">
            <i class="fa-solid fa-list me-1"></i> Cart
        </button>
        <button type="button" class="btn btn-sm btn-primary fw-bold px-3" onclick="openPaymentModal()">
            <i class="fa-solid fa-credit-card me-1"></i> Pay
        </button>
    </div>
</div>

<!-- Modal 1: Size & Color Matrix Selection -->
<div class="modal fade" id="variantPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title fw-bold text-dark" id="modal_product_title">Shoe Title</h5>
                    <div class="small text-muted" id="modal_product_subtitle">Article Code: ART-101</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">
                    <i class="fa-solid fa-circle-info text-primary me-1"></i> Select size and color variant to add to bill. Green badges indicate in-stock pairs.
                </p>
                <div id="modal_variants_container">
                    <!-- Populated dynamically with color & size grid -->
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Payment & Checkout -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-credit-card me-2"></i> Bill Checkout
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Amount Due Banner -->
                <div class="bg-light p-3 rounded-3 text-center mb-3 border">
                    <span class="text-muted small fw-semibold text-uppercase">Net Payable Amount</span>
                    <h2 class="fw-bold text-primary mb-0 mt-1" id="modal_pay_due">Rs. 0.00</h2>
                </div>

                <!-- Lump-Sum Bill Discount Field -->
                <div class="card border border-warning-subtle bg-light p-2 mb-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="small fw-bold text-dark d-block">
                                <i class="fa-solid fa-tags text-warning me-1"></i> Bill Discount (Lump-Sum):
                            </span>
                            <span class="text-muted" style="font-size: 0.75rem;">Apply overall bill discount right here</span>
                        </div>
                        <div class="input-group input-group-sm" style="width: 160px;">
                            <span class="input-group-text bg-white fw-bold">Rs.</span>
                            <input type="number" id="modal_discount_input" class="form-control text-end fw-bold" placeholder="0" min="0" step="any">
                        </div>
                    </div>
                </div>

                <!-- Payment Method Selection -->
                <label class="form-label small fw-bold text-secondary mb-2">Select Payment Method:</label>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="payment_method_radio" id="pm_cash" value="cash" checked>
                        <label class="btn btn-outline-primary w-100 py-2 d-flex flex-column align-items-center" for="pm_cash">
                            <i class="fa-solid fa-money-bill-wave fs-5 mb-1"></i> Cash
                        </label>
                    </div>
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="payment_method_radio" id="pm_jazzcash" value="jazzcash">
                        <label class="btn btn-outline-primary w-100 py-2 d-flex flex-column align-items-center" for="pm_jazzcash">
                            <i class="fa-solid fa-mobile-screen fs-5 mb-1 text-danger"></i> JazzCash
                        </label>
                    </div>
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="payment_method_radio" id="pm_easypaisa" value="easypaisa">
                        <label class="btn btn-outline-primary w-100 py-2 d-flex flex-column align-items-center" for="pm_easypaisa">
                            <i class="fa-solid fa-wallet fs-5 mb-1 text-success"></i> Easypaisa
                        </label>
                    </div>
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="payment_method_radio" id="pm_card" value="card">
                        <label class="btn btn-outline-primary w-100 py-2 d-flex flex-column align-items-center" for="pm_card">
                            <i class="fa-solid fa-credit-card fs-5 mb-1"></i> Bank Card
                        </label>
                    </div>
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="payment_method_radio" id="pm_credit" value="credit">
                        <label class="btn btn-outline-danger w-100 py-2 d-flex flex-column align-items-center" for="pm_credit">
                            <i class="fa-solid fa-book-bookmark fs-5 mb-1"></i> Khata (Credit)
                        </label>
                    </div>
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="payment_method_radio" id="pm_split" value="split">
                        <label class="btn btn-outline-secondary w-100 py-2 d-flex flex-column align-items-center" for="pm_split">
                            <i class="fa-solid fa-arrows-split-up-and-left fs-5 mb-1"></i> Split Pay
                        </label>
                    </div>
                </div>

                <!-- Cash Tendered & Quick Cash Buttons -->
                <div id="cash_details_block">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Cash Tendered (Received):</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light">Rs.</span>
                            <input type="number" id="cash_paid_input" class="form-control fw-bold fs-4" placeholder="0">
                        </div>
                    </div>

                    <!-- Dynamic Quick Cash Buttons -->
                    <div class="mb-3">
                        <div class="small fw-semibold text-secondary mb-1">
                            <i class="fa-solid fa-bolt text-warning me-1"></i> Quick Cash Suggestions:
                        </div>
                        <div id="quick_cash_buttons_container" class="d-flex flex-wrap gap-2"></div>
                    </div>

                    <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center border" id="cash_change_box">
                        <span class="fw-semibold text-secondary" id="cash_change_label">Change Return to Customer:</span>
                        <h4 class="fw-bold text-success mb-0" id="cash_change_display">Rs. 0.00</h4>
                    </div>
                </div>

                <!-- Split Payment Form (Hidden by default) -->
                <div id="split_details_block" style="display: none;" class="border rounded-3 p-3 bg-light mb-3">
                    <span class="small fw-bold text-secondary d-block mb-2">Split Breakdown:</span>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="small text-muted">Cash Amount:</label>
                            <input type="number" id="split_cash" class="form-control form-control-sm split-input" placeholder="0">
                        </div>
                        <div class="col-6">
                            <label class="small text-muted">JazzCash Amount:</label>
                            <input type="number" id="split_jazzcash" class="form-control form-control-sm split-input" placeholder="0">
                        </div>
                        <div class="col-6">
                            <label class="small text-muted">Easypaisa Amount:</label>
                            <input type="number" id="split_easypaisa" class="form-control form-control-sm split-input" placeholder="0">
                        </div>
                        <div class="col-6">
                            <label class="small text-muted">Card Amount:</label>
                            <input type="number" id="split_card" class="form-control form-control-sm split-input" placeholder="0">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Total Split Entered:</span>
                        <span id="split_sum_display" class="fw-bold">Rs. 0.00</span>
                    </div>
                </div>

                <!-- Order Notes -->
                <div class="mt-3">
                    <label class="form-label small text-muted">Bill / Sale Notes (Optional):</label>
                    <input type="text" id="sale_notes" class="form-control form-control-sm" placeholder="e.g. Gift pack, regular client discount">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btn_confirm_sale" class="btn btn-primary px-4 fw-bold">
                    <i class="fa-solid fa-check me-1"></i> Complete &amp; Print Bill
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 3: Sale Success & Invoice Print Dialog -->
<div class="modal fade" id="saleSuccessModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-body p-4 text-center">
                <div class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-circle mb-3" style="width: 72px; height: 72px; font-size: 32px;">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1">Sale Completed Successfully!</h4>
                <p class="text-muted small mb-3">Invoice Number: <strong id="success_invoice_no" class="text-primary fs-6">INV-XXXX</strong></p>

                <div class="bg-light p-3 rounded-3 mb-4 text-start">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Total Bill:</span>
                        <strong id="success_grand_total">Rs. 0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Amount Paid:</span>
                        <strong id="success_paid_amount">Rs. 0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Change Returned:</span>
                        <strong class="text-success" id="success_change_amount">Rs. 0.00</strong>
                    </div>
                </div>

                <div class="d-flex flex-column gap-2">
                    <div class="btn-group w-100" role="group">
                        <a id="btn_print_thermal" href="#" target="_blank" class="btn btn-dark py-2 fw-bold">
                            <i class="fa-solid fa-receipt me-1"></i> Thermal (80mm)
                        </a>
                        <a id="btn_print_thermal_58" href="#" target="_blank" class="btn btn-outline-dark py-2 fw-bold">
                            <i class="fa-solid fa-receipt me-1"></i> (58mm)
                        </a>
                    </div>
                    <a id="btn_print_a4" href="#" target="_blank" class="btn btn-outline-secondary py-2">
                        <i class="fa-solid fa-file-invoice me-2"></i> Print A4 Invoice
                    </a>
                    <button type="button" class="btn btn-primary mt-2 py-2" data-bs-dismiss="modal" id="btn_new_sale">
                        <i class="fa-solid fa-plus me-1"></i> Start New Sale
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal 4: Held Bills Modal -->
<div class="modal fade" id="heldBillsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-pause text-warning me-2"></i> Held Bills Queue
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Hold ID</th>
                                <th>Time</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="held_bills_tbody">
                            <tr><td colspan="6" class="text-center py-4 text-muted">No held bills currently.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal 5: Quick Add Customer -->
<div class="modal fade" id="quickCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">Quick Add Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Customer Full Name *</label>
                    <input type="text" id="quick_cust_name" class="form-control" required placeholder="e.g. M. Tariq">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Phone / Mobile *</label>
                    <input type="text" id="quick_cust_phone" class="form-control" required placeholder="03001234567">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold">Preferred Shoe Size</label>
                        <input type="text" id="quick_cust_size" class="form-control" placeholder="e.g. 42">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">Credit Limit (Rs.)</label>
                        <input type="number" id="quick_cust_limit" class="form-control" value="50000">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btn_save_quick_cust" class="btn btn-primary fw-semibold">Save Customer</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Camera Scanner HUD & Alignment Reticle */
#camera_reader_container {
    background: #000;
    min-height: 280px;
    max-height: 380px;
    position: relative;
    overflow: hidden;
}

#camera_reader video {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    border-radius: 8px;
    transform: translateZ(0);
    will-change: transform;
}

#camera_reader_container .reticle-corner {
    position: absolute;
    width: 20px;
    height: 20px;
    border-color: #0dcaf0;
    border-style: solid;
    pointer-events: none;
}
.corner-tl { top: -2px; left: -2px; border-width: 3px 0 0 3px; border-top-left-radius: 6px; }
.corner-tr { top: -2px; right: -2px; border-width: 3px 3px 0 0; border-top-right-radius: 6px; }
.corner-bl { bottom: -2px; left: -2px; border-width: 0 0 3px 3px; border-bottom-left-radius: 6px; }
.corner-br { bottom: -2px; right: -2px; border-width: 0 3px 3px 0; border-bottom-right-radius: 6px; }

.scanner-laser-line {
    position: absolute;
    left: 3%;
    width: 94%;
    height: 2px;
    background: linear-gradient(90deg, rgba(255,0,0,0) 0%, rgba(255,40,40,0.95) 50%, rgba(255,0,0,0) 100%);
    box-shadow: 0 0 10px 2px rgba(255, 40, 40, 0.8);
    animation: laserScanAnim 2s cubic-bezier(0.4, 0, 0.2, 1) infinite alternate;
    pointer-events: none;
}

@keyframes laserScanAnim {
    0% { top: 6%; }
    100% { top: 90%; }
}

.pulse-reticle {
    box-shadow: 0 0 16px 3px rgba(13, 202, 240, 0.8) !important;
    transition: box-shadow 0.3s ease;
}
</style>

<!-- Modal 6: Camera Barcode Scanner Modal -->
<div class="modal fade" id="cameraScannerModal" tabindex="-1" aria-labelledby="cameraScannerModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold fs-6" id="cameraScannerModalLabel">
                    <i class="fa-solid fa-camera text-info me-2"></i> Camera Barcode Scanner
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="btn_close_camera_scanner"></button>
            </div>
            <div class="modal-body p-3 text-center">
                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                    <label class="small fw-bold text-muted mb-0">
                        <i class="fa-solid fa-video me-1"></i> Camera Device:
                    </label>
                    <select id="camera_select" class="form-select form-select-sm w-auto">
                        <option value="">Detecting cameras...</option>
                    </select>
                </div>

                <!-- Scanner Viewport with Alignment Reticle & Laser -->
                <div id="camera_reader_container" class="position-relative bg-black rounded-3 overflow-hidden shadow-sm" style="min-height: 280px; max-height: 380px;">
                    <div id="camera_reader" style="width: 100%;"></div>

                    <!-- Alignment HUD -->
                    <div id="camera_scanner_hud" class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center" style="pointer-events: none; z-index: 10;">
                        <!-- Reticle Target Box -->
                        <div id="scanner_reticle" class="position-relative" style="width: 78%; height: 55%; border: 2px dashed rgba(255, 255, 255, 0.5); border-radius: 8px;">
                            <span class="reticle-corner corner-tl"></span>
                            <span class="reticle-corner corner-tr"></span>
                            <span class="reticle-corner corner-bl"></span>
                            <span class="reticle-corner corner-br"></span>
                            <!-- Animated Laser Scan Line -->
                            <div class="scanner-laser-line"></div>
                        </div>
                        <div class="mt-2 text-white small px-2 py-1 rounded bg-dark bg-opacity-75 shadow-sm" style="font-size: 0.72rem;">
                            <i class="fa-solid fa-crosshairs text-info me-1"></i> Align barcode inside target window
                        </div>
                    </div>
                </div>

                <!-- Zoom and Auto-Focus Controls Bar -->
                <div id="camera_controls_bar" class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-2 px-2 py-1 bg-light rounded border">
                    <div class="d-flex align-items-center gap-1">
                        <span class="small fw-bold text-secondary me-1">
                            <i class="fa-solid fa-magnifying-glass-plus text-primary"></i> Zoom:
                        </span>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary py-0 px-2 zoom-btn" data-zoom="1">1x</button>
                            <button type="button" class="btn btn-primary py-0 px-2 zoom-btn" data-zoom="1.5">1.5x</button>
                            <button type="button" class="btn btn-outline-secondary py-0 px-2 zoom-btn" data-zoom="2">2x</button>
                            <button type="button" class="btn btn-outline-secondary py-0 px-2 zoom-btn" data-zoom="3">3x</button>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-1">
                        <button type="button" id="btn_toggle_torch" class="btn btn-sm btn-outline-warning py-0 px-2" style="display: none;">
                            <i class="fa-solid fa-bolt"></i> <span id="torch_label">Torch</span>
                        </button>
                        <button type="button" id="btn_auto_align" class="btn btn-sm btn-outline-info py-0 px-2">
                            <i class="fa-solid fa-arrows-to-dot"></i> Auto-Focus
                        </button>
                    </div>
                </div>

                <div class="mt-2">
                    <span id="camera_scan_status" class="badge bg-secondary-subtle text-secondary px-3 py-2 fs-6">
                        <i class="fa-solid fa-crosshairs me-1"></i> Ready to scan shoe barcode
                    </span>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary ms-auto px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/html5-qrcode.min.js"></script>
<script>
// POS State Management
let cart = [];
let currentCategoryId = 0;
let currentSearchTerm = '';
let currentBrandFilter = '';
let activeProductVariants = [];

// Initialize POS on page load
document.addEventListener('DOMContentLoaded', function() {
    loadProducts();
    loadCustomers();
    loadHeldBills();

    // Search input typing debounce
    let searchTimeout = null;
    const searchInput = document.getElementById('pos_search_input');
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentSearchTerm = searchInput.value.trim();
            loadProducts();
        }, 250);
    });

    // Barcode Scanner listener (Enter key triggers immediate barcode lookup)
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            let code = searchInput.value.trim();
            if (code.length > 0) {
                tryBarcodeLookup(code);
            }
        }
    });

    // Clear search
    document.getElementById('btn_clear_search').addEventListener('click', function() {
        searchInput.value = '';
        currentSearchTerm = '';
        loadProducts();
        searchInput.focus();
    });

    // Category button click
    document.querySelectorAll('.category-filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.category-filter-btn').forEach(b => b.classList.remove('active', 'btn-dark'));
            document.querySelectorAll('.category-filter-btn').forEach(b => b.classList.add('btn-outline-secondary'));
            this.classList.remove('btn-outline-secondary');
            this.classList.add('active', 'btn-dark');
            currentCategoryId = parseInt(this.getAttribute('data-cat'));
            loadProducts();
        });
    });

    // Brand filter
    document.getElementById('pos_brand_filter').addEventListener('change', function() {
        currentBrandFilter = this.value;
        loadProducts();
    });

    // Customer change
    document.getElementById('pos_customer_select').addEventListener('change', function() {
        let opt = this.options[this.selectedIndex];
        let bal = parseFloat(opt.getAttribute('data-balance') || 0);
        let badge = document.getElementById('cust_balance_badge');
        if (bal > 0) {
            badge.className = 'input-group-text bg-danger-subtle text-danger small fw-bold';
            badge.innerText = 'Due: Rs. ' + bal.toFixed(2);
        } else {
            badge.className = 'input-group-text bg-light text-muted small';
            badge.innerText = 'Bal: Rs. ' + bal.toFixed(2);
        }
        syncCustomerCreditState();
    });

    // Guard Khata (Credit) button if Walk-in Customer is selected
    const creditLabel = document.querySelector('label[for="pm_credit"]');
    if (creditLabel) {
        creditLabel.addEventListener('click', function(e) {
            if (isWalkInCustomer()) {
                e.preventDefault();
                alert('Cannot sell on credit (Khata) to Walk-in Customer!\n\nPlease select an existing customer or click "+ New Customer" to register them.');
            }
        });
    }

    // Discount change on main screen
    document.getElementById('pos_discount_input').addEventListener('input', function() {
        updateCartTotals();
        const modalDisc = document.getElementById('modal_discount_input');
        if (modalDisc && document.activeElement !== modalDisc) {
            modalDisc.value = this.value > 0 ? this.value : '';
        }
    });

    // Modal Lump-Sum Discount listener
    const modalDiscInput = document.getElementById('modal_discount_input');
    if (modalDiscInput) {
        modalDiscInput.addEventListener('input', function() {
            let disc = parseFloat(this.value) || 0;
            if (disc < 0) disc = 0;
            document.getElementById('pos_discount_input').value = disc;
            updateCartTotals();

            let newTotal = getCartGrandTotal();
            document.getElementById('modal_pay_due').innerText = 'Rs. ' + newTotal.toFixed(2);
            renderSmartQuickCash(newTotal);
            calculateChange();
        });
    }

    // Cash Paid change
    document.getElementById('cash_paid_input').addEventListener('input', calculateChange);

    // Payment method radio change
    document.querySelectorAll('input[name="payment_method_radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const method = this.value;
            const cashBlock = document.getElementById('cash_details_block');
            const splitBlock = document.getElementById('split_details_block');

            if (method === 'split') {
                cashBlock.style.display = 'none';
                splitBlock.style.display = 'block';
            } else if (method === 'credit') {
                cashBlock.style.display = 'none';
                splitBlock.style.display = 'none';
            } else {
                cashBlock.style.display = 'block';
                splitBlock.style.display = 'none';
                if (method !== 'cash') {
                    // For card, jazzcash, easypaisa, default cash_paid to exact
                    document.getElementById('cash_paid_input').value = Math.ceil(getCartGrandTotal());
                    calculateChange();
                }
            }
        });
    });

    // Split input listener
    document.querySelectorAll('.split-input').forEach(input => {
        input.addEventListener('input', calculateSplitSum);
    });

    // Checkout button click (F2)
    document.getElementById('btn_checkout').addEventListener('click', openPaymentModal);

    // Hold button click (F4)
    document.getElementById('btn_hold_bill').addEventListener('click', holdCurrentBill);

    // Clear cart
    document.getElementById('btn_clear_cart').addEventListener('click', function() {
        if (cart.length > 0 && confirm('Are you sure you want to clear this bill?')) {
            cart = [];
            renderCart();
        }
    });

    // Confirm checkout button
    document.getElementById('btn_confirm_sale').addEventListener('click', submitSale);

    // Save Quick Customer
    document.getElementById('btn_save_quick_cust').addEventListener('click', saveQuickCustomer);

    // Camera Scan Button Click
    document.getElementById('btn_open_camera_scanner').addEventListener('click', openCameraScanner);

    // Camera modal setup
    const cameraModalEl = document.getElementById('cameraScannerModal');
    const cameraSelect = document.getElementById('camera_select');

    cameraModalEl.addEventListener('shown.bs.modal', function() {
        if (window.Html5Qrcode) {
            Html5Qrcode.getCameras().then(devices => {
                cameraSelect.innerHTML = '';
                if (devices && devices.length) {
                    availableCameras = devices;
                    let selectedCamId = devices[0].id;
                    for (let d of devices) {
                        let opt = document.createElement('option');
                        opt.value = d.id;
                        opt.text = d.label || `Camera ${cameraSelect.options.length + 1}`;
                        if (d.label && (d.label.toLowerCase().includes('back') || d.label.toLowerCase().includes('rear') || d.label.toLowerCase().includes('environment'))) {
                            opt.selected = true;
                            selectedCamId = d.id;
                        }
                        cameraSelect.appendChild(opt);
                    }
                    initCameraScanner(selectedCamId);
                } else {
                    cameraSelect.innerHTML = '<option value="">Default Camera</option>';
                    initCameraScanner(null);
                }
            }).catch(err => {
                cameraSelect.innerHTML = '<option value="">Default Camera</option>';
                initCameraScanner(null);
            });
        }
    });

    cameraSelect.addEventListener('change', function() {
        initCameraScanner(this.value);
    });

    cameraModalEl.addEventListener('hidden.bs.modal', function() {
        stopCameraScanner();
    });

    // Zoom Buttons Click Listeners
    document.querySelectorAll('.zoom-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            let z = parseFloat(this.getAttribute('data-zoom')) || 1.0;
            applyCameraZoom(z);
        });
    });

    // Torch Toggle
    const torchBtn = document.getElementById('btn_toggle_torch');
    if (torchBtn) {
        torchBtn.addEventListener('click', toggleTorch);
    }

    // Auto-Focus & Align
    const autoAlignBtn = document.getElementById('btn_auto_align');
    if (autoAlignBtn) {
        autoAlignBtn.addEventListener('click', triggerAutoAlign);
    }

    // Global POS keyboard shortcuts
    window.addEventListener('keydown', function(e) {
        if (e.key === 'F2') {
            e.preventDefault();
            openPaymentModal();
        } else if (e.key === 'F3') {
            e.preventDefault();
            openCameraScanner();
        } else if (e.key === 'F4') {
            e.preventDefault();
            holdCurrentBill();
        }
    });
});

// Camera Barcode Scanner Functions & Optimization
let html5QrCodeScanner = null;
let isCameraScanning = false;
let availableCameras = [];
let currentVideoTrack = null;
let currentZoom = 1.5;
let isTorchOn = false;

function openCameraScanner() {
    const modalEl = document.getElementById('cameraScannerModal');
    const bsModal = new bootstrap.Modal(modalEl);
    bsModal.show();
}

function playScanBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(1400, audioCtx.currentTime);
        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.15);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.15);
    } catch (e) {}
}

function initCameraScanner(cameraId) {
    const statusBadge = document.getElementById('camera_scan_status');

    if (!window.Html5Qrcode) {
        statusBadge.className = 'badge bg-danger-subtle text-danger px-3 py-2 fs-6';
        statusBadge.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Scanner library not loaded';
        return;
    }

    if (html5QrCodeScanner && isCameraScanning) {
        stopCameraScanner().then(() => {
            startNewScanner(cameraId);
        }).catch(() => {
            startNewScanner(cameraId);
        });
    } else {
        startNewScanner(cameraId);
    }

    function startNewScanner(camId) {
        // Instantiate with native BarcodeDetector hardware acceleration enabled
        if (!html5QrCodeScanner) {
            html5QrCodeScanner = new Html5Qrcode("camera_reader", {
                experimentalFeatures: {
                    useBarCodeDetectorIfSupported: true
                },
                verbose: false
            });
        }

        // Lightweight, performance-tuned scanner config (10 FPS prevents 100% CPU lockup)
        const config = {
            fps: 10,
            qrbox: function(viewfinderWidth, viewfinderHeight) {
                let minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                return {
                    width: Math.min(360, Math.floor(viewfinderWidth * 0.8)),
                    height: Math.min(220, Math.floor(minEdge * 0.65))
                };
            },
            aspectRatio: 1.333333,
            disableFlip: true,
            formatsToSupport: [
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.QR_CODE
            ]
        };

        // Html5Qrcode expects cameraConfig as a direct deviceId string or an object with exactly 1 key (facingMode)
        const cameraConfig = camId ? camId : { facingMode: "environment" };

        statusBadge.className = 'badge bg-primary-subtle text-primary px-3 py-2 fs-6';
        statusBadge.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Activating camera...';

        function handleScanSuccess(decodedText, decodedResult) {
            playScanBeep();
            
            // Visual feedback on reticle HUD
            const reticle = document.getElementById('scanner_reticle');
            if (reticle) {
                reticle.style.borderColor = '#198754';
                reticle.style.boxShadow = '0 0 20px rgba(25, 135, 84, 0.9)';
            }

            statusBadge.className = 'badge bg-success-subtle text-success px-3 py-2 fs-6';
            statusBadge.innerHTML = `<i class="fa-solid fa-check me-1"></i> Scanned: <strong>${decodedText}</strong>`;

            stopCameraScanner().then(() => {
                const modalEl = document.getElementById('cameraScannerModal');
                const bsModal = bootstrap.Modal.getInstance(modalEl);
                if (bsModal) bsModal.hide();

                const searchInput = document.getElementById('pos_search_input');
                searchInput.value = decodedText;
                tryBarcodeLookup(decodedText);
            });
        }

        html5QrCodeScanner.start(
            cameraConfig,
            config,
            handleScanSuccess,
            (errorMessage) => {
                // Seek frame
            }
        ).then(() => {
            isCameraScanning = true;
            statusBadge.className = 'badge bg-secondary-subtle text-secondary px-3 py-2 fs-6';
            statusBadge.innerHTML = '<i class="fa-solid fa-crosshairs me-1"></i> Align barcode within camera target';
            
            // Set up Auto-Zoom and Continuous Autofocus
            setTimeout(setupCameraCapabilities, 350);
        }).catch(err => {
            console.warn("Primary camera start failed, retrying default facingMode:", err);
            // Fallback retry
            html5QrCodeScanner.start(
                { facingMode: "environment" },
                config,
                handleScanSuccess,
                () => {}
            ).then(() => {
                isCameraScanning = true;
                statusBadge.className = 'badge bg-secondary-subtle text-secondary px-3 py-2 fs-6';
                statusBadge.innerHTML = '<i class="fa-solid fa-crosshairs me-1"></i> Align barcode within camera target';
                setTimeout(setupCameraCapabilities, 350);
            }).catch(fallbackErr => {
                let msg = (typeof fallbackErr === 'string') ? fallbackErr : ((typeof err === 'string') ? err : (err?.message || fallbackErr?.message || 'Camera access denied or unavailable'));
                statusBadge.className = 'badge bg-danger-subtle text-danger px-3 py-2 fs-6';
                statusBadge.innerHTML = `<i class="fa-solid fa-triangle-exclamation me-1"></i> ${msg}`;
            });
        });
    }
}

function setupCameraCapabilities() {
    const videoEl = document.querySelector('#camera_reader video');
    if (videoEl && videoEl.srcObject) {
        currentVideoTrack = videoEl.srcObject.getVideoTracks()[0] || null;
    }
    
    if (!currentVideoTrack) return;

    const caps = typeof currentVideoTrack.getCapabilities === 'function' ? currentVideoTrack.getCapabilities() : {};

    // 1. Continuous Auto-focus
    try {
        currentVideoTrack.applyConstraints({
            advanced: [{ focusMode: "continuous" }]
        }).catch(() => {});
    } catch(e) {}

    // 2. Torch capability
    const torchBtn = document.getElementById('btn_toggle_torch');
    if (caps.torch && torchBtn) {
        torchBtn.style.display = 'inline-block';
    } else if (torchBtn) {
        torchBtn.style.display = 'none';
    }

    // 3. Auto-Zoom Feature: default to 1.5x so barcodes are captured without holding too close
    if (caps.zoom) {
        let autoZoom = Math.min(caps.zoom.max || 3.0, Math.max(caps.zoom.min || 1.0, 1.5));
        applyCameraZoom(autoZoom);
    } else {
        applyCameraZoom(1.5);
    }
}

function applyCameraZoom(level) {
    currentZoom = level;

    // Update zoom UI pills
    document.querySelectorAll('.zoom-btn').forEach(btn => {
        let z = parseFloat(btn.getAttribute('data-zoom'));
        if (Math.abs(z - level) < 0.25) {
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-primary');
        } else {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline-secondary');
        }
    });

    // Hardware optical / digital zoom
    if (currentVideoTrack && typeof currentVideoTrack.getCapabilities === 'function') {
        const caps = currentVideoTrack.getCapabilities();
        if (caps.zoom) {
            let clamped = Math.min(caps.zoom.max, Math.max(caps.zoom.min, level));
            currentVideoTrack.applyConstraints({
                advanced: [{ zoom: clamped }]
            }).catch(() => {
                applyCssDigitalZoom(level);
            });
            return;
        }
    }

    // CSS Hardware-accelerated zoom fallback
    applyCssDigitalZoom(level);
}

function applyCssDigitalZoom(level) {
    const videoEl = document.querySelector('#camera_reader video');
    if (videoEl) {
        videoEl.style.transform = `scale(${level}) translateZ(0)`;
        videoEl.style.transformOrigin = 'center center';
        videoEl.style.transition = 'transform 0.2s ease-out';
    }
}

function triggerAutoAlign() {
    const reticle = document.getElementById('scanner_reticle');
    if (reticle) {
        reticle.classList.add('pulse-reticle');
        setTimeout(() => reticle.classList.remove('pulse-reticle'), 600);
    }

    if (currentVideoTrack) {
        try {
            currentVideoTrack.applyConstraints({
                advanced: [{ focusMode: "continuous" }]
            }).catch(() => {});
        } catch(e) {}
    }

    const statusBadge = document.getElementById('camera_scan_status');
    if (statusBadge) {
        statusBadge.className = 'badge bg-info-subtle text-info px-3 py-2 fs-6';
        statusBadge.innerHTML = '<i class="fa-solid fa-arrows-to-dot fa-spin me-1"></i> Auto-focusing & aligning...';
        setTimeout(() => {
            statusBadge.className = 'badge bg-secondary-subtle text-secondary px-3 py-2 fs-6';
            statusBadge.innerHTML = '<i class="fa-solid fa-crosshairs me-1"></i> Align barcode within camera target';
        }, 1000);
    }
}

function toggleTorch() {
    if (!currentVideoTrack) return;
    isTorchOn = !isTorchOn;
    try {
        currentVideoTrack.applyConstraints({
            advanced: [{ torch: isTorchOn }]
        }).then(() => {
            const torchBtn = document.getElementById('btn_toggle_torch');
            const torchLabel = document.getElementById('torch_label');
            if (isTorchOn) {
                torchBtn.className = 'btn btn-sm btn-warning py-0 px-2';
                if (torchLabel) torchLabel.innerText = 'Torch ON';
            } else {
                torchBtn.className = 'btn btn-sm btn-outline-warning py-0 px-2';
                if (torchLabel) torchLabel.innerText = 'Torch';
            }
        }).catch(err => {
            console.warn('Torch toggle not supported:', err);
        });
    } catch(e) {}
}

function stopCameraScanner() {
    if (currentVideoTrack) {
        try {
            currentVideoTrack.stop();
        } catch(e) {}
        currentVideoTrack = null;
    }
    isTorchOn = false;

    // Reset reticle style
    const reticle = document.getElementById('scanner_reticle');
    if (reticle) {
        reticle.style.borderColor = 'rgba(255, 255, 255, 0.5)';
        reticle.style.boxShadow = 'none';
    }

    if (html5QrCodeScanner && isCameraScanning) {
        return html5QrCodeScanner.stop().then(() => {
            isCameraScanning = false;
        }).catch(err => {
            console.warn("Stop error:", err);
            isCameraScanning = false;
        });
    }
    return Promise.resolve();
}

// Load products
function loadProducts() {
    let url = `pos_actions.php?action=search_products&q=${encodeURIComponent(currentSearchTerm)}&cat_id=${currentCategoryId}`;
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderProductGrid(data.products);
            }
        })
        .catch(err => console.error(err));
}

// Render Products Grid
function renderProductGrid(products) {
    const grid = document.getElementById('pos_products_grid');
    const badge = document.getElementById('product_count_badge');
    badge.innerText = `${products.length} models found`;

    if (products.length === 0) {
        grid.innerHTML = `<div class="col-12 text-center py-5 text-muted">
            <i class="fa-solid fa-shoe-prints fs-1 text-black-50 mb-2"></i>
            <p>No shoe models found matching your search.</p>
        </div>`;
        return;
    }

    let html = '';
    products.forEach(p => {
        let stock = parseInt(p.total_stock) || 0;
        let stockBadge = stock > 0 
            ? `<span class="badge bg-success bg-opacity-75">${stock} Pairs Available</span>` 
            : `<span class="badge bg-danger">Out of Stock</span>`;

        html += `
        <div class="col-6 col-md-4 col-xl-3">
            <div class="pos-catalog-card p-2 p-sm-3 h-100 d-flex flex-column justify-content-between" onclick="openVariantPicker(${p.id}, '${escapeHtml(p.name)}', '${escapeHtml(p.article_code)}')">
                <div>
                    <div class="d-flex justify-content-between align-items-start mb-1 mb-sm-2 gap-1">
                        <span class="badge bg-dark small" style="font-size:0.7rem;">${escapeHtml(p.article_code)}</span>
                        <span class="badge bg-light text-secondary border small text-truncate" style="font-size:0.7rem; max-width:80px;">${escapeHtml(p.brand_name || 'Standard')}</span>
                    </div>
                    <div class="text-center my-1 my-sm-2 text-primary" style="font-size: 1.8rem;">
                        <i class="fa-solid fa-shoe-prints"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-0 text-truncate" title="${escapeHtml(p.name)}" style="font-size: 0.9rem;">${escapeHtml(p.name)}</h6>
                    <div class="small text-muted mb-1 text-truncate" style="font-size: 0.78rem;">${escapeHtml(p.category_name || '')}</div>
                </div>
                <div>
                    <div class="d-flex justify-content-between align-items-center mt-1 pt-1 border-top flex-wrap gap-1">
                        <span class="fw-bold text-primary small">Rs. ${parseFloat(p.base_selling_price).toFixed(0)}</span>
                        ${stockBadge}
                    </div>
                </div>
            </div>
        </div>`;
    });
    grid.innerHTML = html;
}

// Open Variant Picker Modal (Size & Color Matrix)
function openVariantPicker(productId, productName, articleCode) {
    document.getElementById('modal_product_title').innerText = productName;
    document.getElementById('modal_product_subtitle').innerText = `Article: ${articleCode}`;

    const container = document.getElementById('modal_variants_container');
    container.innerHTML = '<div class="text-center py-4 text-muted"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading sizes and colors...</div></div>';

    const modal = new bootstrap.Modal(document.getElementById('variantPickerModal'));
    modal.show();

    fetch(`pos_actions.php?action=get_product_variants&product_id=${productId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.variants.length > 0) {
                activeProductVariants = data.variants;
                renderVariantPickerHtml(data.variants);
            } else {
                container.innerHTML = '<div class="alert alert-warning">No size/color variants found for this shoe. Please add variants in Products section.</div>';
            }
        });
}

// Render Size & Color Matrix inside Modal
function renderVariantPickerHtml(variants) {
    const container = document.getElementById('modal_variants_container');

    // Group variants by Color
    let colorsMap = {};
    variants.forEach(v => {
        let col = v.color_name || 'Standard';
        if (!colorsMap[col]) {
            colorsMap[col] = {
                color_name: col,
                hex_code: v.hex_code || '#000000',
                variants: []
            };
        }
        colorsMap[col].variants.push(v);
    });

    let html = '';
    for (let col in colorsMap) {
        let cData = colorsMap[col];
        html += `
        <div class="card mb-3 border">
            <div class="card-header bg-light d-flex align-items-center gap-2 py-2">
                <span class="color-swatch-sm" style="background-color: ${cData.hex_code};"></span>
                <strong class="text-dark">${escapeHtml(cData.color_name)} Color</strong>
            </div>
            <div class="card-body p-3">
                <div class="d-flex flex-wrap gap-2">`;

        cData.variants.forEach(v => {
            let stock = parseInt(v.stock_qty);
            let isOut = stock <= 0;
            let badgeClass = isOut ? 'btn-outline-danger disabled' : (stock <= 2 ? 'btn-outline-warning' : 'btn-outline-success');

            html += `
            <button type="button" class="btn ${badgeClass} text-center p-2" style="min-width: 82px;" 
                    onclick="addVariantToCart(${v.id})" ${isOut ? 'disabled' : ''}>
                <div class="fw-bold fs-6">Size ${v.size_number}</div>
                <div class="small fw-semibold mt-1">${stock > 0 ? stock + ' pairs' : 'Out of Stock'}</div>
                <div class="text-muted" style="font-size: 0.72rem;">Rs. ${parseFloat(v.selling_price).toFixed(0)}</div>
            </button>`;
        });

        html += `</div></div></div>`;
    }

    container.innerHTML = html;
}

// Add Variant to Cart
function addVariantToCart(variantId) {
    // Find variant in activeProductVariants
    let v = activeProductVariants.find(item => item.id == variantId);
    if (!v) return;

    // Check if variant already exists in cart
    let existing = cart.find(item => item.variant_id == v.id);
    if (existing) {
        if (existing.quantity + 1 > v.stock_qty) {
            alert(`Only ${v.stock_qty} pairs available in stock for Size ${v.size_number} (${v.color_name})!`);
            return;
        }
        existing.quantity += 1;
        existing.total = (existing.quantity * existing.unit_price) - existing.discount;
    } else {
        if (v.stock_qty <= 0) {
            alert('This size is out of stock!');
            return;
        }
        cart.push({
            variant_id: v.id,
            product_id: v.product_id,
            article_code: v.article_code,
            product_name: v.product_name,
            size_number: v.size_number,
            color_name: v.color_name,
            unit_price: parseFloat(v.selling_price),
            quantity: 1,
            discount: 0,
            stock_qty: v.stock_qty,
            total: parseFloat(v.selling_price)
        });
    }

    // Close Modal
    const modalEl = document.getElementById('variantPickerModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    renderCart();
    document.getElementById('pos_search_input').focus();
}

// Barcode Lookup Trigger
function tryBarcodeLookup(code) {
    fetch(`pos_actions.php?action=get_by_barcode&barcode=${encodeURIComponent(code)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.variant) {
                let v = data.variant;
                // Add directly to cart
                activeProductVariants = [v];
                addVariantToCart(v.id);
                document.getElementById('pos_search_input').value = '';
            } else {
                alert(`Barcode not found: ${code}`);
            }
        });
}

// Render Cart
function renderCart() {
    const container = document.getElementById('pos_cart_items');
    if (cart.length === 0) {
        container.innerHTML = `
        <div id="empty_cart_msg" class="text-center py-5 text-muted">
            <i class="fa-solid fa-bag-shopping fs-1 text-black-50 mb-2"></i>
            <p class="mb-0">Cart is empty.<br>Scan barcode or click a shoe to add sizes.</p>
        </div>`;
        updateCartTotals();
        return;
    }

    let html = '';
    cart.forEach((item, index) => {
        html += `
        <div class="p-2 border-bottom d-flex justify-content-between align-items-center">
            <div style="flex: 1; min-width: 0;">
                <div class="fw-bold text-dark text-truncate small">${escapeHtml(item.product_name)}</div>
                <div class="d-flex align-items-center gap-1 small text-muted">
                    <span class="badge bg-dark">${escapeHtml(item.article_code)}</span>
                    <span class="badge bg-primary">Size: ${item.size_number}</span>
                    <span class="badge bg-secondary">${escapeHtml(item.color_name)}</span>
                </div>
                <div class="small text-muted mt-1">@ Rs. ${item.unit_price.toFixed(2)}</div>
            </div>

            <!-- Qty Stepper -->
            <div class="d-flex align-items-center gap-1 mx-2" style="width: 105px;">
                <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-0" onclick="updateQty(${index}, -1)">-</button>
                <input type="number" class="form-control form-control-sm text-center p-1" style="width: 45px;" 
                       value="${item.quantity}" min="1" max="${item.stock_qty}" onchange="setQtyDirect(${index}, this.value)">
                <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-0" onclick="updateQty(${index}, 1)">+</button>
            </div>

            <div class="text-end fw-bold text-dark" style="width: 85px;">
                Rs. ${item.total.toFixed(2)}
            </div>

            <button type="button" class="btn btn-link text-danger p-1 ms-1" onclick="removeItem(${index})" title="Remove item">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </div>`;
    });

    container.innerHTML = html;
    updateCartTotals();
}

function updateQty(index, delta) {
    let item = cart[index];
    let newQty = item.quantity + delta;
    if (newQty <= 0) {
        removeItem(index);
        return;
    }
    if (newQty > item.stock_qty) {
        alert(`Maximum available stock is ${item.stock_qty} pairs!`);
        return;
    }
    item.quantity = newQty;
    item.total = (item.quantity * item.unit_price) - item.discount;
    renderCart();
}

function setQtyDirect(index, val) {
    let item = cart[index];
    let qty = parseInt(val) || 1;
    if (qty <= 0) qty = 1;
    if (qty > item.stock_qty) {
        alert(`Maximum available stock is ${item.stock_qty} pairs!`);
        qty = item.stock_qty;
    }
    item.quantity = qty;
    item.total = (item.quantity * item.unit_price) - item.discount;
    renderCart();
}

function removeItem(index) {
    cart.splice(index, 1);
    renderCart();
}

// Calculate Cart Totals
function updateCartTotals() {
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.total;
    });

    let discount = parseFloat(document.getElementById('pos_discount_input').value) || 0;
    if (discount > subtotal) discount = subtotal;

    let tax = 0.00;
    let grandTotal = Math.max(0, subtotal - discount + tax);

    document.getElementById('pos_subtotal').innerText = 'Rs. ' + subtotal.toFixed(2);
    document.getElementById('pos_discount_display').innerText = '-Rs. ' + discount.toFixed(2);
    document.getElementById('pos_tax').innerText = 'Rs. ' + tax.toFixed(2);
    document.getElementById('pos_grand_total').innerText = 'Rs. ' + grandTotal.toFixed(2);

    // Update Mobile Floating Cart Bar
    const mobileBar = document.getElementById('mobile_cart_bar');
    if (mobileBar) {
        if (cart.length > 0) {
            mobileBar.classList.remove('d-none');
            mobileBar.classList.add('d-flex');
            let totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('mobile_cart_count').innerText = `${totalItems} ${totalItems === 1 ? 'pair' : 'pairs'}`;
            document.getElementById('mobile_cart_total').innerText = 'Rs. ' + grandTotal.toFixed(2);
        } else {
            mobileBar.classList.add('d-none');
            mobileBar.classList.remove('d-flex');
        }
    }
}

function getCartGrandTotal() {
    let subtotal = 0;
    cart.forEach(item => subtotal += item.total);
    let discount = parseFloat(document.getElementById('pos_discount_input').value) || 0;
    return Math.max(0, subtotal - discount);
}

// Check if currently selected customer is Walk-in Customer
function isWalkInCustomer() {
    let select = document.getElementById('pos_customer_select');
    if (!select) return true;
    let val = select.value;
    let text = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : '';
    return (val === '1' || val === '' || val === '0' || text.toLowerCase().includes('walk-in') || text.toLowerCase().includes('counter'));
}

// Enable or disable Khata (Credit) based on customer selection
function syncCustomerCreditState() {
    let isWalkIn = isWalkInCustomer();
    let creditRadio = document.getElementById('pm_credit');
    let creditLabel = document.querySelector('label[for="pm_credit"]');
    
    if (creditRadio && creditLabel) {
        if (isWalkIn) {
            creditRadio.disabled = true;
            creditLabel.classList.add('opacity-50');
            creditLabel.style.cursor = 'not-allowed';
            creditLabel.setAttribute('title', 'Khata (Credit) is not allowed for Walk-in Customer.');
            // If credit was selected, switch to cash
            if (creditRadio.checked) {
                let cashRadio = document.getElementById('pm_cash');
                if (cashRadio) {
                    cashRadio.checked = true;
                    cashRadio.dispatchEvent(new Event('change'));
                }
            }
        } else {
            creditRadio.disabled = false;
            creditLabel.classList.remove('opacity-50');
            creditLabel.style.cursor = 'pointer';
            creditLabel.removeAttribute('title');
        }
    }
}

// Open Payment Modal
function openPaymentModal() {
    if (cart.length === 0) {
        alert('Cart is empty. Please add items to checkout.');
        return;
    }

    syncCustomerCreditState();

    let currentDiscount = parseFloat(document.getElementById('pos_discount_input').value) || 0;
    const modalDiscInput = document.getElementById('modal_discount_input');
    if (modalDiscInput) {
        modalDiscInput.value = currentDiscount > 0 ? currentDiscount : '';
    }

    let total = getCartGrandTotal();
    document.getElementById('modal_pay_due').innerText = 'Rs. ' + total.toFixed(2);
    document.getElementById('cash_paid_input').value = Math.ceil(total);

    renderSmartQuickCash(total);
    calculateChange();

    const payModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    payModal.show();
}

// Generate Smart Quick Cash Suggestions based on actual bill
function renderSmartQuickCash(total) {
    const container = document.getElementById('quick_cash_buttons_container');
    if (!container) return;

    let roundedTotal = Math.ceil(total);
    let html = '';

    // 1. Exact amount button
    html += `<button type="button" class="btn btn-sm btn-outline-primary fw-bold quick-cash-btn" data-amt="${roundedTotal}">Exact (Rs. ${roundedTotal.toLocaleString()})</button>`;

    if (roundedTotal > 0) {
        let suggestions = new Set();

        // Step up rules for Pakistani currency notes
        // 1. Next 100
        let next100 = Math.ceil(roundedTotal / 100) * 100;
        if (next100 > roundedTotal) suggestions.add(next100);

        // 2. Next 500 (e.g. 9,800 -> 10,000)
        let next500 = Math.ceil(roundedTotal / 500) * 500;
        if (next500 === roundedTotal) next500 += 500;
        suggestions.add(next500);

        // 3. Next 1,000 (e.g. 9,800 -> 10,000, 11,000)
        let next1000 = Math.ceil(roundedTotal / 1000) * 1000;
        if (next1000 === roundedTotal) next1000 += 1000;
        suggestions.add(next1000);
        suggestions.add(next1000 + 1000);

        // 4. Next 5,000 (e.g. 9,800 -> 10,000, 15,000)
        let next5000 = Math.ceil(roundedTotal / 5000) * 5000;
        if (next5000 === roundedTotal) next5000 += 5000;
        suggestions.add(next5000);
        if (next5000 + 5000 <= roundedTotal + 10000) {
            suggestions.add(next5000 + 5000);
        }

        let sorted = Array.from(suggestions)
            .filter(v => v > roundedTotal)
            .sort((a, b) => a - b)
            .slice(0, 4);

        sorted.forEach(amt => {
            html += `<button type="button" class="btn btn-sm btn-outline-secondary quick-cash-btn" data-amt="${amt}">Rs. ${amt.toLocaleString()}</button>`;
        });
    } else {
        [500, 1000, 2000, 5000].forEach(amt => {
            html += `<button type="button" class="btn btn-sm btn-outline-secondary quick-cash-btn" data-amt="${amt}">Rs. ${amt.toLocaleString()}</button>`;
        });
    }

    container.innerHTML = html;

    container.querySelectorAll('.quick-cash-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            let amt = parseFloat(this.getAttribute('data-amt')) || 0;
            document.getElementById('cash_paid_input').value = amt;
            calculateChange();
        });
    });
}

// Calculate Change (Supports Negative Shortfall in Bold Red)
function calculateChange() {
    let total = getCartGrandTotal();
    let paid = parseFloat(document.getElementById('cash_paid_input').value) || 0;
    let diff = paid - total;

    const changeBox = document.getElementById('cash_change_box');
    const changeLabel = document.getElementById('cash_change_label');
    const changeDisplay = document.getElementById('cash_change_display');

    if (diff < 0) {
        let shortAmount = Math.abs(diff);
        if (changeLabel) changeLabel.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-danger me-1"></i> Remaining Due (Short):';
        if (changeDisplay) {
            changeDisplay.innerText = '-Rs. ' + shortAmount.toFixed(2);
            changeDisplay.className = 'fw-bold text-danger mb-0 fs-4';
        }
        if (changeBox) {
            changeBox.className = 'p-3 rounded-3 d-flex justify-content-between align-items-center border border-danger bg-danger-subtle';
        }
    } else {
        if (changeLabel) changeLabel.innerHTML = 'Change Return to Customer:';
        if (changeDisplay) {
            changeDisplay.innerText = 'Rs. ' + diff.toFixed(2);
            changeDisplay.className = 'fw-bold text-success mb-0 fs-4';
        }
        if (changeBox) {
            changeBox.className = 'p-3 bg-light rounded-3 d-flex justify-content-between align-items-center border border-success-subtle';
        }
    }
}

// Calculate Split Sum
function calculateSplitSum() {
    let sCash = parseFloat(document.getElementById('split_cash').value) || 0;
    let sJc = parseFloat(document.getElementById('split_jazzcash').value) || 0;
    let sEp = parseFloat(document.getElementById('split_easypaisa').value) || 0;
    let sCard = parseFloat(document.getElementById('split_card').value) || 0;
    let sum = sCash + sJc + sEp + sCard;
    document.getElementById('split_sum_display').innerText = 'Rs. ' + sum.toFixed(2);
}

// Submit Sale to Backend
function submitSale() {
    let total = getCartGrandTotal();
    let method = document.querySelector('input[name="payment_method_radio"]:checked').value;
    let paid = parseFloat(document.getElementById('cash_paid_input').value) || 0;
    let customerId = document.getElementById('pos_customer_select').value;
    let notes = document.getElementById('sale_notes').value;
    let discount = parseFloat(document.getElementById('pos_discount_input').value) || 0;

    let subtotal = 0;
    cart.forEach(item => subtotal += (item.quantity * item.unit_price));

    let splitPayments = [];
    if (method === 'cash') {
        if (paid < total) {
            let short = (total - paid).toFixed(2);
            alert(`Cannot complete sale!\n\nCash tendered (Rs. ${paid.toFixed(2)}) is less than the bill amount (Rs. ${total.toFixed(2)}).\n\nCustomer is short by Rs. ${short}.\nPlease collect the remaining cash, give a bill discount, or switch to Khata (Credit).`);
            document.getElementById('cash_paid_input').focus();
            return;
        }
    } else if (method === 'split') {
        let sCash = parseFloat(document.getElementById('split_cash').value) || 0;
        let sJc = parseFloat(document.getElementById('split_jazzcash').value) || 0;
        let sEp = parseFloat(document.getElementById('split_easypaisa').value) || 0;
        let sCard = parseFloat(document.getElementById('split_card').value) || 0;
        let sum = sCash + sJc + sEp + sCard;
        if (sum < total) {
            alert(`Split payments total (Rs. ${sum.toFixed(2)}) is less than bill total (Rs. ${total.toFixed(2)})!`);
            return;
        }
        splitPayments.push({ method: 'cash', amount: sCash });
        splitPayments.push({ method: 'jazzcash', amount: sJc });
        splitPayments.push({ method: 'easypaisa', amount: sEp });
        splitPayments.push({ method: 'card', amount: sCard });
        paid = sum;
    } else if (method === 'credit') {
        if (isWalkInCustomer() || customerId == '1') {
            alert('Cannot save sale as credit for Walk-in Customer!\n\nPlease select an existing customer or click "+ New Customer" to register them.');
            return;
        }
        paid = 0.00;
    } else if (['jazzcash', 'easypaisa', 'card'].includes(method)) {
        if (paid < total) {
            alert(`Cannot complete sale: Received amount (Rs. ${paid.toFixed(2)}) is less than bill amount (Rs. ${total.toFixed(2)})!`);
            return;
        }
    }

    // Additional safeguard: Walk-in customer can never have unpaid remainder
    if ((isWalkInCustomer() || customerId == '1') && paid < total) {
        let short = (total - paid).toFixed(2);
        alert(`Cannot complete sale!\n\nWalk-in Customer cannot have an unpaid balance (Short by Rs. ${short}).\nFull bill amount must be paid.`);
        return;
    }

    const payload = {
        action: 'save_sale',
        customer_id: customerId,
        subtotal: subtotal,
        discount_amount: discount,
        tax_amount: 0.00,
        grand_total: total,
        paid_amount: paid,
        payment_method: method,
        split_payments: splitPayments,
        notes: notes,
        items: cart
    };

    fetch('pos_actions.php?action=save_sale', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Close Payment Modal
            bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();

            // Populate Success Modal
            document.getElementById('success_invoice_no').innerText = data.invoice_no;
            document.getElementById('success_grand_total').innerText = 'Rs. ' + parseFloat(data.grand_total).toFixed(2);
            document.getElementById('success_paid_amount').innerText = 'Rs. ' + parseFloat(data.paid_amount).toFixed(2);
            document.getElementById('success_change_amount').innerText = 'Rs. ' + parseFloat(data.change_amount).toFixed(2);

            document.getElementById('btn_print_thermal').href = `invoice_print.php?id=${data.sale_id}&type=thermal&width=80&auto_print=1`;
            const btn58 = document.getElementById('btn_print_thermal_58');
            if (btn58) btn58.href = `invoice_print.php?id=${data.sale_id}&type=thermal&width=58&auto_print=1`;
            document.getElementById('btn_print_a4').href = `invoice_print.php?id=${data.sale_id}&type=a4&auto_print=1`;

            const successModal = new bootstrap.Modal(document.getElementById('saleSuccessModal'));
            successModal.show();

            // Reset cart
            cart = [];
            document.getElementById('pos_discount_input').value = 0;
            const modalDisc = document.getElementById('modal_discount_input');
            if (modalDisc) modalDisc.value = '';
            renderCart();
            loadProducts(); // refresh stock numbers
        } else {
            alert('Error completing sale: ' + data.message);
        }
    })
    .catch(err => {
        alert('Server communication error');
        console.error(err);
    });
}

// Hold Current Bill
function holdCurrentBill() {
    if (cart.length === 0) {
        alert('Cannot hold an empty cart!');
        return;
    }

    let customerId = document.getElementById('pos_customer_select').value;
    let subtotal = 0;
    cart.forEach(item => subtotal += item.total);

    const payload = {
        action: 'hold_sale',
        customer_id: customerId,
        subtotal: subtotal,
        grand_total: getCartGrandTotal(),
        items: cart
    };

    fetch('pos_actions.php?action=hold_sale', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(`Bill held successfully as #${data.hold_no}`);
            cart = [];
            renderCart();
            loadHeldBills();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// Load Held Bills Queue
function loadHeldBills() {
    fetch('pos_actions.php?action=get_held_sales')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('held_count').innerText = data.held_sales.length;
                let tbody = document.getElementById('held_bills_tbody');
                if (data.held_sales.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No held bills currently.</td></tr>';
                    return;
                }
                let html = '';
                data.held_sales.forEach(h => {
                    html += `
                    <tr>
                        <td class="fw-bold">${escapeHtml(h.invoice_no)}</td>
                        <td class="small text-muted">${h.sale_date}</td>
                        <td>${escapeHtml(h.customer_name || 'Walk-in')}</td>
                        <td>${h.item_count} items</td>
                        <td class="fw-bold">Rs. ${parseFloat(h.grand_total).toFixed(2)}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-primary" onclick="resumeHeldBill(${h.id})">
                                Resume
                            </button>
                        </td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            }
        });
}

// Resume Held Bill
function resumeHeldBill(saleId) {
    fetch(`pos_actions.php?action=resume_sale&sale_id=${saleId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Restore items to cart
                cart = [];
                data.items.forEach(it => {
                    cart.push({
                        variant_id: it.variant_id,
                        product_id: it.product_id,
                        article_code: it.article_code,
                        product_name: it.product_name,
                        size_number: it.size_number,
                        color_name: it.color_name,
                        unit_price: parseFloat(it.unit_price),
                        quantity: parseInt(it.quantity),
                        discount: parseFloat(it.discount),
                        stock_qty: parseInt(it.stock_qty),
                        total: parseFloat(it.total)
                    });
                });
                renderCart();
                bootstrap.Modal.getInstance(document.getElementById('heldBillsModal')).hide();
                loadHeldBills();
            } else {
                alert('Error: ' + data.message);
            }
        });
}

// Load Customers for Dropdown
function loadCustomers() {
    fetch('pos_actions.php?action=search_customers&q=')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                let select = document.getElementById('pos_customer_select');
                select.innerHTML = '<option value="1" data-balance="0.00" data-limit="0.00">Walk-in Customer (Counter Sale)</option>';
                data.customers.forEach(c => {
                    if (c.id != 1) {
                        select.innerHTML += `<option value="${c.id}" data-balance="${c.current_balance}" data-limit="${c.credit_limit}">${escapeHtml(c.name)} (${escapeHtml(c.phone || '')})</option>`;
                    }
                });
            }
        });
}

// Save Quick Customer
function saveQuickCustomer() {
    let name = document.getElementById('quick_cust_name').value.trim();
    let phone = document.getElementById('quick_cust_phone').value.trim();
    let prefSize = document.getElementById('quick_cust_size').value.trim();
    let limit = document.getElementById('quick_cust_limit').value;

    if (!name || !phone) {
        alert('Please fill customer name and phone');
        return;
    }

    let formData = new FormData();
    formData.append('action', 'quick_add_customer');
    formData.append('name', name);
    formData.append('phone', phone);
    formData.append('preferred_size', prefSize);
    formData.append('credit_limit', limit);

    fetch('pos_actions.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                let c = data.customer;
                let select = document.getElementById('pos_customer_select');
                let opt = document.createElement('option');
                opt.value = c.id;
                opt.setAttribute('data-balance', '0.00');
                opt.setAttribute('data-limit', limit);
                opt.innerText = `${c.name} (${c.phone})`;
                opt.selected = true;
                select.appendChild(opt);

                document.getElementById('cust_balance_badge').innerText = 'Bal: Rs. 0.00';
                bootstrap.Modal.getInstance(document.getElementById('quickCustomerModal')).hide();
                alert(`Customer ${c.name} added!`);
            } else {
                alert('Error: ' + data.message);
            }
        });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
