<?php
// invoice_print.php - Thermal Receipt (58mm/80mm) and A4 Invoice Print View
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();

$sale_id = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? get_setting('print_type', 'thermal'); // 'thermal' or 'a4'
$auto_print = isset($_GET['auto_print']) && $_GET['auto_print'] == '1';

// Thermal Roll Width: 58mm (48mm printable) or 80mm (72mm printable)
$default_width = (int)get_setting('thermal_width', 80);
$roll_width = isset($_GET['width']) ? (int)$_GET['width'] : $default_width;
if (!in_array($roll_width, [58, 80])) {
    $roll_width = 80;
}

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
            color: #000;
        }

        /* ========================================================
           THERMAL OPTIMIZATION ENGINE
           1. Monoline Sans-Serif font (Arial / Helvetica / Segoe UI)
           2. Uniform bold weight (700) - avoids faint 1-pixel thermal burns
           3. Hierarchy via font size only
           4. Pure #000 black - avoids dithering/gray dots
           5. Disabled gray fringe anti-aliasing (geometricPrecision)
           6. Both 58mm (48mm printable) and 80mm (72mm printable)
           ======================================================== */
        .thermal-receipt,
        .thermal-receipt *:not(#receipt_barcode) {
            font-family: Arial, "Helvetica Neue", Helvetica, "Segoe UI", Tahoma, sans-serif !important;
            font-weight: 700 !important;
            color: #000000 !important;
            border-color: #000000 !important;
            background-color: transparent !important;
            -webkit-font-smoothing: antialiased !important;
            -moz-osx-font-smoothing: grayscale !important;
            text-rendering: geometricPrecision !important;
            box-sizing: border-box;
        }

        #receipt_barcode {
            background-color: #ffffff !important;
            image-rendering: pixelated !important;
            image-rendering: crisp-edges !important;
            display: block !important;
            margin: 0 auto !important;
        }

        .thermal-receipt {
            margin: 20px auto;
            background: #ffffff !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            line-height: 1.3;
        }

        /* 80mm (3-inch) Roll: Printable width 72mm */
        .thermal-receipt.thermal-80 {
            width: 72mm;
            max-width: 72mm;
            padding: 8px 0;
            font-size: 11.5px;
        }

        /* 58mm (2-inch) Roll: Printable width 48mm */
        .thermal-receipt.thermal-58 {
            width: 48mm;
            max-width: 48mm;
            padding: 6px 0;
            font-size: 10px;
            line-height: 1.25;
        }

        .thermal-receipt .receipt-divider {
            text-align: center;
            overflow: hidden;
            white-space: nowrap;
            letter-spacing: 1px;
            font-size: 10px;
            line-height: 1;
            margin: 4px 0;
            user-select: none;
            color: #000000 !important;
        }

        .thermal-receipt .border-double {
            border-top: 2px solid #000000 !important;
            border-bottom: 2px solid #000000 !important;
            padding: 3px 0;
            margin: 5px 0;
        }

        .thermal-receipt .border-bottom-solid {
            border-bottom: 1.5px solid #000000 !important;
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
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
        }

        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            @page {
                margin: 0;
                size: auto;
            }
            .thermal-receipt {
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }
            .thermal-receipt.thermal-80 {
                width: 72mm !important;
                max-width: 72mm !important;
                padding: 2mm 0 !important;
            }
            .thermal-receipt.thermal-58 {
                width: 48mm !important;
                max-width: 48mm !important;
                padding: 1mm 0 !important;
            }
            .a4-invoice {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Action Bar (Hidden on print) -->
<div class="no-print bg-dark text-white py-2 px-3 sticky-top">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong>Invoice: <?= clean($sale['invoice_no']) ?></strong>
            <span class="badge bg-secondary ms-2 text-uppercase">
                <?= $type === 'thermal' ? "Thermal {$roll_width}mm" : "A4 Format" ?>
            </span>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <div class="btn-group btn-group-sm" role="group">
                <a href="?id=<?= $sale['id'] ?>&type=thermal&width=80" class="btn <?= ($type === 'thermal' && $roll_width === 80) ? 'btn-primary' : 'btn-outline-light' ?>">
                    <i class="fa-solid fa-receipt me-1"></i> Thermal 80mm (72mm)
                </a>
                <a href="?id=<?= $sale['id'] ?>&type=thermal&width=58" class="btn <?= ($type === 'thermal' && $roll_width === 58) ? 'btn-primary' : 'btn-outline-light' ?>">
                    <i class="fa-solid fa-receipt me-1"></i> Thermal 58mm (48mm)
                </a>
                <a href="?id=<?= $sale['id'] ?>&type=a4" class="btn <?= $type === 'a4' ? 'btn-primary' : 'btn-outline-light' ?>">
                    <i class="fa-solid fa-file-lines me-1"></i> A4 Format
                </a>
            </div>
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
<!-- ======================= THERMAL RECEIPT (58mm / 80mm) ======================= -->
<div class="thermal-receipt <?= $roll_width === 58 ? 'thermal-58' : 'thermal-80' ?>">
    <!-- Header -->
    <div class="text-center mb-1">
        <div style="font-size: <?= $roll_width === 58 ? '14px' : '17px' ?>;" class="text-uppercase mb-1">
            <?= clean($shop_name) ?>
        </div>
        <div style="font-size: <?= $roll_width === 58 ? '9px' : '10.5px' ?>;"><?= clean($tagline) ?></div>
        <div style="font-size: <?= $roll_width === 58 ? '9px' : '10.5px' ?>;"><?= clean($address) ?></div>
        <div style="font-size: <?= $roll_width === 58 ? '9px' : '10.5px' ?>;">Ph: <?= clean($phone) ?></div>
    </div>

    <div class="receipt-divider">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

    <!-- Bill Meta Info (Tabular to avoid awkward wrapping) -->
    <table class="w-100" style="font-size: <?= $roll_width === 58 ? '9px' : '10.5px' ?>; line-height: 1.25;">
        <tr>
            <td style="width: 50%;">Inv: <?= clean($sale['invoice_no']) ?></td>
            <td class="text-end" style="width: 50%;"><?= date('d/m/Y h:i A', strtotime($sale['sale_date'])) ?></td>
        </tr>
        <tr>
            <td colspan="2">Cashier: <?= clean($sale['cashier_name'] ?? 'Counter') ?></td>
        </tr>
        <tr>
            <td colspan="2">Cust: <?= clean($sale['customer_name'] ?? 'Walk-in Customer') ?></td>
        </tr>
    </table>

    <div class="receipt-divider">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

    <!-- Items Table -->
    <table class="w-100" style="font-size: <?= $roll_width === 58 ? '9.5px' : '11px' ?>; border-collapse: collapse;">
        <thead>
            <tr class="border-bottom-solid">
                <th style="width: 48%; text-align: left; padding: 2px 0;">Item/Size/Color</th>
                <th class="text-center" style="width: 14%; padding: 2px 0;">Qty</th>
                <th class="text-end" style="width: 18%; padding: 2px 0;">Rate</th>
                <th class="text-end" style="width: 20%; padding: 2px 0;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td class="pt-1" style="word-break: break-word; line-height: 1.2;">
                        <div><?= clean($item['product_name']) ?></div>
                        <div style="font-size: <?= $roll_width === 58 ? '8px' : '9.5px' ?>;">
                            [<?= clean($item['article_code']) ?>] Sz: <?= clean($item['size_number']) ?>, <?= clean($item['color_name']) ?>
                        </div>
                    </td>
                    <td class="text-center align-top pt-1"><?= $item['quantity'] ?></td>
                    <td class="text-end align-top pt-1"><?= number_format($item['unit_price'], 0) ?></td>
                    <td class="text-end align-top pt-1"><?= number_format($item['total'], 0) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="receipt-divider">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

    <!-- Totals Summary -->
    <table class="w-100" style="font-size: <?= $roll_width === 58 ? '10px' : '11.5px' ?>;">
        <tr>
            <td>Subtotal:</td>
            <td class="text-end">Rs. <?= number_format($sale['subtotal'], 2) ?></td>
        </tr>
        <?php if ($sale['discount_amount'] > 0): ?>
            <tr>
                <td>Discount:</td>
                <td class="text-end">-Rs. <?= number_format($sale['discount_amount'], 2) ?></td>
            </tr>
        <?php endif; ?>
        <?php if ($sale['tax_amount'] > 0): ?>
            <tr>
                <td>Tax:</td>
                <td class="text-end">Rs. <?= number_format($sale['tax_amount'], 2) ?></td>
            </tr>
        <?php endif; ?>
        <tr class="border-double" style="font-size: <?= $roll_width === 58 ? '12px' : '14px' ?>;">
            <td>NET TOTAL:</td>
            <td class="text-end">Rs. <?= number_format($sale['grand_total'], 2) ?></td>
        </tr>
        <tr>
            <td>Payment (<?= strtoupper(clean($sale['payment_method'])) ?>):</td>
            <td class="text-end">Rs. <?= number_format($sale['paid_amount'], 2) ?></td>
        </tr>
        <?php if ($sale['change_amount'] > 0): ?>
            <tr>
                <td>Change Returned:</td>
                <td class="text-end">Rs. <?= number_format($sale['change_amount'], 2) ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <div class="receipt-divider">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

    <!-- Barcode Image (Crisp pixel-rendered canvas) -->
    <div class="text-center my-2">
        <img id="receipt_barcode" alt="Barcode" style="max-width: 95%;">
        <div style="font-size: <?= $roll_width === 58 ? '9px' : '10.5px' ?>; letter-spacing: 1px; margin-top: 2px;">
            <?= clean($sale['invoice_no']) ?>
        </div>
    </div>

    <!-- Exchange Policy Notes -->
    <div class="text-center" style="font-size: <?= $roll_width === 58 ? '8.5px' : '9.5px' ?>; line-height: 1.25;">
        <div><?= clean($footer_en) ?></div>
        <div class="mt-1">*** Thank You for Shopping ***</div>
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
            <img id="receipt_barcode" alt="Barcode" class="mx-auto" style="max-width: 280px; image-rendering: pixelated; image-rendering: crisp-edges;">
            <div class="small fw-bold mt-1" style="letter-spacing: 1px;"><?= clean($sale['invoice_no']) ?></div>
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
    var isThermal = <?= $type === 'thermal' ? 'true' : 'false' ?>;
    var is58 = <?= ($type === 'thermal' && $roll_width === 58) ? 'true' : 'false' ?>;
    
    // Create raster canvas to generate clean 1-bit crisp monochrome barcode
    // Module width 1px avoids subpixel anti-aliasing bleeds on 203 DPI thermal printers
    var canvas = document.createElement("canvas");
    var modWidth = 1;
    var bcHeight = is58 ? 28 : (isThermal ? 34 : 40);

    try {
        JsBarcode(canvas, "<?= clean($sale['invoice_no']) ?>", {
            format: "CODE128",
            width: modWidth,
            height: bcHeight,
            displayValue: false,
            margin: 6,
            background: "#ffffff",
            lineColor: "#000000"
        });

        var bcImg = document.getElementById("receipt_barcode");
        if (bcImg) {
            bcImg.src = canvas.toDataURL("image/png");
        }
    } catch (e) {
        console.error("Barcode generation error:", e);
    }

    <?php if ($auto_print): ?>
        setTimeout(function() {
            window.print();
        }, 250);
    <?php endif; ?>
});
</script>
</body>
</html>
