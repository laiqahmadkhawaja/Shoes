<?php
// invoice_print.php - Thermal Receipt (80mm) and A4 Invoice Print View
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();

$sale_id = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? get_setting('print_type', 'thermal'); // 'thermal' or 'a4'
$auto_print = isset($_GET['auto_print']) && $_GET['auto_print'] == '1';

// Fetch sale details
$stmt = $db->prepare("SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address, 
                             u.full_name as cashier_name 
                      FROM sales s 
                      LEFT JOIN customers c ON s.customer_id = c.id 
                      LEFT JOIN users u ON s.user_id = u.id 
                      WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    die("Sale invoice not found.");
}

// Fetch items
$stmt = $db->prepare("SELECT si.*, pv.barcode FROM sale_items si 
                      LEFT JOIN product_variants pv ON si.variant_id = pv.id 
                      WHERE si.sale_id = ?");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll();

// Settings
$shop_name = get_setting('shop_name', 'StepStyle Footwear');
$tagline = get_setting('tagline', 'Quality Footwear Store');
$address = get_setting('address', 'Lahore, Pakistan');
$phone = get_setting('phone', '+92 300 1234567');
$footer_en = get_setting('receipt_footer', 'Exchange within 7 days with bill and original shoe box.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= clean($sale['invoice_no']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- JsBarcode CDN -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            color: #000;
        }

        /* 80mm Thermal Receipt Layout */
        .thermal-receipt {
            width: 80mm;
            margin: 20px auto;
            background: #fff;
            padding: 12px 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            font-size: 13px;
            line-height: 1.35;
        }

        /* A4 Layout */
        .a4-invoice {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #fff;
            padding: 25mm 20mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            font-size: 14px;
        }

        .border-dashed {
            border-top: 1px dashed #475569;
            margin: 8px 0;
        }

        .border-double {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 4px 0;
            margin: 6px 0;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
            .thermal-receipt {
                width: 78mm;
                margin: 0;
                padding: 4px;
                box-shadow: none;
            }
            .a4-invoice {
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

<!-- Action Bar (Hidden on print) -->
<div class="no-print bg-dark text-white py-2 px-3 sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <strong>Invoice: <?= clean($sale['invoice_no']) ?></strong>
            <span class="badge bg-secondary ms-2 text-uppercase"><?= $type ?> Mode</span>
        </div>
        <div class="d-flex gap-2">
            <a href="?id=<?= $sale['id'] ?>&type=thermal" class="btn btn-sm <?= $type == 'thermal' ? 'btn-primary' : 'btn-outline-light' ?>">
                <i class="fa-solid fa-receipt me-1"></i> Thermal 80mm
            </a>
            <a href="?id=<?= $sale['id'] ?>&type=a4" class="btn btn-sm <?= $type == 'a4' ? 'btn-primary' : 'btn-outline-light' ?>">
                <i class="fa-solid fa-file-lines me-1"></i> A4 Format
            </a>
            <button onclick="window.print()" class="btn btn-sm btn-success fw-bold">
                <i class="fa-solid fa-print me-1"></i> Print Now
            </button>
            <button onclick="window.close()" class="btn btn-sm btn-outline-danger">
                Close
            </button>
        </div>
    </div>
</div>

<?php if ($type === 'thermal'): ?>
<!-- ======================= 80mm THERMAL RECEIPT ======================= -->
<div class="thermal-receipt">
    <!-- Header -->
    <div class="text-center mb-2">
        <h5 class="fw-bold mb-0 text-uppercase"><?= clean($shop_name) ?></h5>
        <div style="font-size: 11px;" class="text-muted"><?= clean($tagline) ?></div>
        <div style="font-size: 11px;"><?= clean($address) ?></div>
        <div style="font-size: 11px;">Ph: <strong><?= clean($phone) ?></strong></div>
    </div>

    <div class="border-dashed"></div>

    <!-- Bill Meta Info -->
    <div class="d-flex justify-content-between" style="font-size: 11px;">
        <span>Inv #: <strong><?= clean($sale['invoice_no']) ?></strong></span>
        <span>Date: <?= date('d/m/Y h:i A', strtotime($sale['sale_date'])) ?></span>
    </div>
    <div class="d-flex justify-content-between" style="font-size: 11px;">
        <span>Cashier: <?= clean($sale['cashier_name'] ?? 'Counter') ?></span>
        <span>Cust: <?= clean($sale['customer_name'] ?? 'Walk-in') ?></span>
    </div>

    <div class="border-dashed"></div>

    <!-- Items Table -->
    <table class="w-100" style="font-size: 12px;">
        <thead>
            <tr class="fw-bold border-bottom">
                <th style="width: 50%;">Item / Size / Color</th>
                <th class="text-center" style="width: 15%;">Qty</th>
                <th class="text-end" style="width: 17%;">Rate</th>
                <th class="text-end" style="width: 18%;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td class="pt-1">
                        <div class="fw-bold"><?= clean($item['product_name']) ?></div>
                        <div style="font-size: 10px; color: #475569;">
                            Art: <?= clean($item['article_code']) ?> | Sz: <strong><?= clean($item['size_number']) ?></strong> | <?= clean($item['color_name']) ?>
                        </div>
                    </td>
                    <td class="text-center align-top pt-1"><?= $item['quantity'] ?></td>
                    <td class="text-end align-top pt-1"><?= number_format($item['unit_price'], 0) ?></td>
                    <td class="text-end align-top pt-1 fw-bold"><?= number_format($item['total'], 0) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="border-dashed"></div>

    <!-- Totals Summary -->
    <table class="w-100" style="font-size: 12px;">
        <tr>
            <td>Subtotal:</td>
            <td class="text-end fw-semibold">Rs. <?= number_format($sale['subtotal'], 2) ?></td>
        </tr>
        <?php if ($sale['discount_amount'] > 0): ?>
            <tr>
                <td>Discount:</td>
                <td class="text-end text-danger">-Rs. <?= number_format($sale['discount_amount'], 2) ?></td>
            </tr>
        <?php endif; ?>
        <?php if ($sale['tax_amount'] > 0): ?>
            <tr>
                <td>Tax:</td>
                <td class="text-end">Rs. <?= number_format($sale['tax_amount'], 2) ?></td>
            </tr>
        <?php endif; ?>
        <tr class="border-double fw-bold" style="font-size: 14px;">
            <td>NET TOTAL:</td>
            <td class="text-end">Rs. <?= number_format($sale['grand_total'], 2) ?></td>
        </tr>
        <tr>
            <td>Payment Method:</td>
            <td class="text-end text-uppercase fw-semibold"><?= clean($sale['payment_method']) ?></td>
        </tr>
        <tr>
            <td>Amount Received:</td>
            <td class="text-end">Rs. <?= number_format($sale['paid_amount'], 2) ?></td>
        </tr>
        <?php if ($sale['change_amount'] > 0): ?>
            <tr class="fw-bold">
                <td>Change Returned:</td>
                <td class="text-end">Rs. <?= number_format($sale['change_amount'], 2) ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <div class="border-dashed"></div>

    <!-- Barcode Image -->
    <div class="text-center my-2">
        <svg id="receipt_barcode"></svg>
    </div>

    <!-- Exchange Policy Notes -->
    <div class="text-center" style="font-size: 10px; line-height: 1.3;">
        <div><?= clean($footer_en) ?></div>
        <div class="mt-2 text-muted">Powered by StepStyle Shoes POS</div>
    </div>
</div>

<?php else: ?>
<!-- ======================= A4 TAX INVOICE ======================= -->
<div class="a4-invoice">
    <!-- A4 Header -->
    <div class="row align-items-center pb-3 border-bottom mb-4">
        <div class="col-7">
            <h3 class="fw-bold text-primary mb-1"><?= clean($shop_name) ?></h3>
            <p class="text-muted small mb-0"><?= clean($tagline) ?></p>
            <div class="small mt-1">
                <div><i class="fa-solid fa-location-dot me-1 text-primary"></i> <?= clean($address) ?></div>
                <div><i class="fa-solid fa-phone me-1 text-primary"></i> <?= clean($phone) ?></div>
            </div>
        </div>
        <div class="col-5 text-end">
            <h2 class="fw-bold text-dark text-uppercase mb-1">INVOICE</h2>
            <div class="fw-bold text-primary fs-5"><?= clean($sale['invoice_no']) ?></div>
            <div class="text-muted small">Date: <?= date('d M Y, h:i A', strtotime($sale['sale_date'])) ?></div>
            <div class="badge bg-success text-uppercase mt-2 px-3 py-1"><?= clean($sale['payment_status']) ?></div>
        </div>
    </div>

    <!-- Customer & Bill Info -->
    <div class="row mb-4 bg-light p-3 rounded">
        <div class="col-6">
            <h6 class="text-muted small fw-bold text-uppercase mb-2">Billed To:</h6>
            <div class="fw-bold text-dark fs-6"><?= clean($sale['customer_name'] ?? 'Walk-in Customer') ?></div>
            <?php if (!empty($sale['customer_phone'])): ?>
                <div class="small text-muted"><i class="fa-solid fa-phone me-1"></i> <?= clean($sale['customer_phone']) ?></div>
            <?php endif; ?>
            <?php if (!empty($sale['customer_address'])): ?>
                <div class="small text-muted"><?= clean($sale['customer_address']) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-6 text-end">
            <h6 class="text-muted small fw-bold text-uppercase mb-2">Cashier / Terminal:</h6>
            <div class="fw-semibold"><?= clean($sale['cashier_name'] ?? 'Main Counter') ?></div>
            <div class="small text-muted">Payment: <strong class="text-uppercase text-dark"><?= clean($sale['payment_method']) ?></strong></div>
        </div>
    </div>

    <!-- Items Table -->
    <table class="table table-bordered align-middle mb-4">
        <thead class="table-light">
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Article &amp; Shoe Description</th>
                <th class="text-center" style="width: 12%;">Size</th>
                <th class="text-center" style="width: 12%;">Color</th>
                <th class="text-center" style="width: 8%;">Qty</th>
                <th class="text-end" style="width: 18%;">Line Total</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($items as $item): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td>
                        <strong class="text-dark"><?= clean($item['product_name']) ?></strong>
                        <div class="small text-muted">Article Code: <span class="badge bg-dark"><?= clean($item['article_code']) ?></span></div>
                    </td>
                    <td class="text-center fw-bold fs-6"><?= clean($item['size_number']) ?></td>
                    <td class="text-center"><?= clean($item['color_name']) ?></td>
                    <td class="text-center fw-bold"><?= $item['quantity'] ?></td>
                    <td class="text-end fw-bold">Rs. <?= number_format($item['total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals & Barcode -->
    <div class="row align-items-center mb-4">
        <div class="col-6 text-center">
            <svg id="receipt_barcode"></svg>
            <div class="small text-muted mt-1"><?= clean($sale['invoice_no']) ?></div>
        </div>
        <div class="col-6">
            <div class="card p-3 bg-light border-0">
                <div class="d-flex justify-content-between mb-1">
                    <span>Subtotal:</span>
                    <strong>Rs. <?= number_format($sale['subtotal'], 2) ?></strong>
                </div>
                <?php if ($sale['discount_amount'] > 0): ?>
                    <div class="d-flex justify-content-between mb-1 text-danger">
                        <span>Discount:</span>
                        <strong>-Rs. <?= number_format($sale['discount_amount'], 2) ?></strong>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between pt-2 border-top fs-5 fw-bold text-primary">
                    <span>Grand Total:</span>
                    <span>Rs. <?= number_format($sale['grand_total'], 2) ?></span>
                </div>
                <div class="d-flex justify-content-between small text-muted mt-1">
                    <span>Amount Paid:</span>
                    <span>Rs. <?= number_format($sale['paid_amount'], 2) ?></span>
                </div>
                <?php if ($sale['change_amount'] > 0): ?>
                    <div class="d-flex justify-content-between small text-success fw-bold">
                        <span>Change Returned:</span>
                        <span>Rs. <?= number_format($sale['change_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Terms -->
    <div class="pt-4 border-top text-muted small">
        <div class="fw-bold text-dark mb-1">Exchange Policy &amp; Terms:</div>
        <div><?= clean($footer_en) ?></div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    JsBarcode("#receipt_barcode", "<?= clean($sale['invoice_no']) ?>", {
        format: "CODE128",
        width: 1.4,
        height: 36,
        displayValue: false
    });

    <?php if ($auto_print): ?>
        window.print();
    <?php endif; ?>
});
</script>
</body>
</html>
