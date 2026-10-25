<?php
// pos_actions.php - AJAX API handler for POS operations
require_once __DIR__ . '/config/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// Read input whether from query, form-post, or JSON payload
$raw_input = file_get_contents('php://input');
$json_input = !empty($raw_input) ? json_decode($raw_input, true) : null;
$action = $_GET['action'] ?? $_POST['action'] ?? ($json_input['action'] ?? '');

try {
    // 1. Scan Barcode (Instant Exact Match)
    if ($action === 'get_by_barcode') {
        $barcode = trim($_GET['barcode'] ?? $_POST['barcode'] ?? '');
        if (empty($barcode)) {
            echo json_encode(['success' => false, 'message' => 'Barcode cannot be empty']);
            exit;
        }

        $stmt = $db->prepare("SELECT pv.*, p.name as product_name, p.article_code, p.gender, 
                              b.name as brand_name, c.name as category_name, 
                              sz.size_number, cl.name as color_name, cl.hex_code 
                              FROM product_variants pv 
                              JOIN products p ON pv.product_id = p.id 
                              LEFT JOIN brands b ON p.brand_id = b.id 
                              LEFT JOIN categories c ON p.category_id = c.id 
                              JOIN sizes sz ON pv.size_id = sz.id 
                              JOIN colors cl ON pv.color_id = cl.id 
                              WHERE pv.barcode = ? OR pv.sku = ? LIMIT 1");
        $stmt->execute([$barcode, $barcode]);
        $variant = $stmt->fetch();

        if ($variant) {
            echo json_encode(['success' => true, 'variant' => $variant]);
        } else {
            echo json_encode(['success' => false, 'message' => "No product found with barcode: {$barcode}"]);
        }
        exit;
    }

    // 2. Search Products & Categories
    if ($action === 'search_products') {
        $q = trim($_GET['q'] ?? '');
        $cat_id = (int)($_GET['cat_id'] ?? 0);

        $sql = "SELECT p.*, b.name as brand_name, c.name as category_name,
                (SELECT SUM(stock_qty) FROM product_variants WHERE product_id = p.id) as total_stock 
                FROM products p 
                LEFT JOIN brands b ON p.brand_id = b.id 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.is_active = 1 ";
        $params = [];

        if (!empty($q)) {
            $sql .= " AND (p.name LIKE ? OR p.article_code LIKE ? OR b.name LIKE ? OR c.name LIKE ? 
                      OR EXISTS (SELECT 1 FROM product_variants pv JOIN sizes s ON pv.size_id = s.id JOIN colors cl ON pv.color_id = cl.id WHERE pv.product_id = p.id AND (s.size_number LIKE ? OR cl.name LIKE ? OR pv.barcode LIKE ?)))";
            $term = "%{$q}%";
            $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term]);
        }

        if ($cat_id > 0) {
            $sql .= " AND p.category_id = ?";
            $params[] = $cat_id;
        }

        $sql .= " ORDER BY p.id DESC LIMIT 24";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        echo json_encode(['success' => true, 'products' => $products]);
        exit;
    }

    // 3. Get Variants for Selected Shoe Model (Size & Color Matrix)
    if ($action === 'get_product_variants') {
        $product_id = (int)($_GET['product_id'] ?? 0);
        $stmt = $db->prepare("SELECT pv.*, sz.size_number, sz.size_system, cl.name as color_name, cl.hex_code,
                              p.name as product_name, p.article_code 
                              FROM product_variants pv 
                              JOIN products p ON pv.product_id = p.id 
                              JOIN sizes sz ON pv.size_id = sz.id 
                              JOIN colors cl ON pv.color_id = cl.id 
                              WHERE pv.product_id = ? 
                              ORDER BY cl.name ASC, sz.sort_order ASC, sz.size_number ASC");
        $stmt->execute([$product_id]);
        $variants = $stmt->fetchAll();

        echo json_encode(['success' => true, 'variants' => $variants]);
        exit;
    }

    // 4. Search Customers (Khata accounts)
    if ($action === 'search_customers') {
        $q = trim($_GET['q'] ?? '');
        $stmt = $db->prepare("SELECT id, name, phone, current_balance, credit_limit, preferred_size 
                              FROM customers 
                              WHERE name LIKE ? OR phone LIKE ? 
                              ORDER BY id ASC LIMIT 10");
        $stmt->execute(["%{$q}%", "%{$q}%"]);
        $customers = $stmt->fetchAll();
        echo json_encode(['success' => true, 'customers' => $customers]);
        exit;
    }

    // 5. Quick Add Customer from POS
    if ($action === 'quick_add_customer') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $preferred_size = trim($_POST['preferred_size'] ?? '');
        $credit_limit = (float)($_POST['credit_limit'] ?? 50000.00);

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Customer name is required']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO customers (name, phone, preferred_size, credit_limit, current_balance) VALUES (?, ?, ?, ?, 0.00)");
        $stmt->execute([$name, $phone, $preferred_size, $credit_limit]);
        $new_id = $db->lastInsertId();

        echo json_encode([
            'success' => true, 
            'customer' => [
                'id' => $new_id, 
                'name' => $name, 
                'phone' => $phone, 
                'preferred_size' => $preferred_size,
                'current_balance' => 0.00
            ]
        ]);
        exit;
    }

    // 6. Complete Sale / Checkout (Transactional, Atomicity & Concurrency Safe)
    if ($action === 'save_sale') {
        $input = $json_input ?: json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $items = $input['items'] ?? [];
        if (empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Cannot checkout an empty bill. Add at least one shoe pair.']);
            exit;
        }

        $customer_id = !empty($input['customer_id']) ? (int)$input['customer_id'] : 1; // 1 is Walk-in Customer
        $subtotal = (float)($input['subtotal'] ?? 0);
        $discount_amount = (float)($input['discount_amount'] ?? 0);
        $tax_amount = (float)($input['tax_amount'] ?? 0);
        $grand_total = (float)($input['grand_total'] ?? 0);
        $paid_amount = (float)($input['paid_amount'] ?? 0);
        $payment_method = $input['payment_method'] ?? 'cash';
        $split_payments = $input['split_payments'] ?? [];
        $notes = trim($input['notes'] ?? '');

        // Determine payment status & change with strict validation
        $change_amount = 0.00;
        if ($payment_method === 'credit') {
            if ($customer_id == 1) {
                echo json_encode(['success' => false, 'message' => 'Credit sale is not allowed for Walk-in Customer!']);
                exit;
            }
            $payment_status = 'due';
            $paid_amount = 0.00;
        } else {
            if ($paid_amount < $grand_total) {
                if ($payment_method === 'cash') {
                    $short = $grand_total - $paid_amount;
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Cash received (Rs. ' . number_format($paid_amount, 2) . ') is less than bill amount (Rs. ' . number_format($grand_total, 2) . '). Short by Rs. ' . number_format($short, 2) . '. Sale cannot be completed with insufficient cash.'
                    ]);
                    exit;
                } elseif ($payment_method === 'split') {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Split payments total (Rs. ' . number_format($paid_amount, 2) . ') is less than bill total (Rs. ' . number_format($grand_total, 2) . ').'
                    ]);
                    exit;
                } elseif (in_array($payment_method, ['card', 'jazzcash', 'easypaisa'])) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Paid amount (Rs. ' . number_format($paid_amount, 2) . ') is less than bill total (Rs. ' . number_format($grand_total, 2) . ').'
                    ]);
                    exit;
                }
                $payment_status = 'partial';
            } else {
                $payment_status = 'paid';
                $change_amount = $paid_amount - $grand_total;
            }
        }

        // Active register
        $active_reg = get_active_register($user_id);
        $register_id = $active_reg ? $active_reg['id'] : null;

        // Generate Invoice Number
        $invoice_no = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        $db->beginTransaction();

        // 1. Insert Sales Record
        $stmt = $db->prepare("INSERT INTO sales (invoice_no, user_id, customer_id, register_id, sale_date, subtotal, discount_amount, tax_amount, grand_total, paid_amount, change_amount, payment_status, payment_method, notes, status) 
                              VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
        $stmt->execute([
            $invoice_no, $user_id, $customer_id, $register_id,
            $subtotal, $discount_amount, $tax_amount, $grand_total,
            $paid_amount, $change_amount, $payment_status, $payment_method, $notes
        ]);
        $sale_id = $db->lastInsertId();

        // 2. Insert Items & Deduct Stock Atomically
        $item_stmt = $db->prepare("INSERT INTO sale_items (sale_id, product_id, variant_id, article_code, product_name, size_number, color_name, unit_cost, unit_price, quantity, discount, total) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stock_stmt = $db->prepare("UPDATE product_variants SET stock_qty = stock_qty - ? WHERE id = ?");

        foreach ($items as $item) {
            $variant_id = (int)$item['variant_id'];
            $qty = (int)$item['quantity'];
            $unit_price = (float)$item['unit_price'];
            $item_discount = (float)($item['discount'] ?? 0);
            $item_total = (float)$item['total'];

            // Fetch current purchase cost of this variant
            $cost_stmt = $db->prepare("SELECT purchase_price FROM product_variants WHERE id = ?");
            $cost_stmt->execute([$variant_id]);
            $unit_cost = (float)$cost_stmt->fetchColumn();

            $item_stmt->execute([
                $sale_id,
                (int)$item['product_id'],
                $variant_id,
                $item['article_code'],
                $item['product_name'],
                $item['size_number'],
                $item['color_name'],
                $unit_cost,
                $unit_price,
                $qty,
                $item_discount,
                $item_total
            ]);

            // Deduct stock
            $stock_stmt->execute([$qty, $variant_id]);
        }

        // 3. Record Payment Breakdown
        if ($payment_method === 'split' && !empty($split_payments)) {
            $pay_stmt = $db->prepare("INSERT INTO sale_payments (sale_id, customer_id, payment_method, amount, reference_no, payment_date) VALUES (?, ?, ?, ?, ?, NOW())");
            foreach ($split_payments as $sp) {
                $amt = (float)$sp['amount'];
                if ($amt > 0) {
                    $pay_stmt->execute([$sale_id, $customer_id, $sp['method'], $amt, $sp['ref'] ?? '']);
                }
            }
        } else {
            $recorded_pay = min($paid_amount, $grand_total);
            if ($recorded_pay > 0 && $payment_method !== 'credit') {
                $pay_stmt = $db->prepare("INSERT INTO sale_payments (sale_id, customer_id, payment_method, amount, reference_no, payment_date) VALUES (?, ?, ?, ?, ?, NOW())");
                $pay_stmt->execute([$sale_id, $customer_id, $payment_method, $recorded_pay, '']);
            }
        }

        // 4. Update Customer Khata Balance if Credit or Partial
        $due_amount = $grand_total - min($paid_amount, $grand_total);
        if ($due_amount > 0 && $customer_id > 1) {
            $cust_stmt = $db->prepare("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?");
            $cust_stmt->execute([$due_amount, $customer_id]);
        }

        // 5. Update Cash Register if cash received
        if ($register_id && ($payment_method === 'cash' || $payment_method === 'split')) {
            $cash_portion = 0.00;
            if ($payment_method === 'cash') {
                $cash_portion = min($paid_amount, $grand_total);
            } elseif ($payment_method === 'split' && !empty($split_payments)) {
                foreach ($split_payments as $sp) {
                    if ($sp['method'] === 'cash') $cash_portion += (float)$sp['amount'];
                }
            }
            if ($cash_portion > 0) {
                $reg_stmt = $db->prepare("UPDATE cash_registers SET cash_sales = cash_sales + ? WHERE id = ?");
                $reg_stmt->execute([$cash_portion, $register_id]);
            }
        }

        // Audit Log
        log_audit('SALE_CREATE', 'POS', $sale_id, "Invoice {$invoice_no} created. Total: {$grand_total}, Method: {$payment_method}");

        $db->commit();

        echo json_encode([
            'success' => true,
            'sale_id' => $sale_id,
            'invoice_no' => $invoice_no,
            'grand_total' => $grand_total,
            'paid_amount' => $paid_amount,
            'change_amount' => $change_amount
        ]);
        exit;
    }

    // 7. Hold Bill
    if ($action === 'hold_sale') {
        $input = $json_input ?: json_decode(file_get_contents('php://input'), true);
        $items = $input['items'] ?? [];
        if (empty($items)) {
            echo json_encode(['success' => false, 'message' => 'No items to hold']);
            exit;
        }

        $hold_no = 'HOLD-' . strtoupper(substr(uniqid(), -5));
        $customer_id = !empty($input['customer_id']) ? (int)$input['customer_id'] : 1;
        $notes = trim($input['notes'] ?? 'Held by Cashier');

        $db->beginTransaction();
        $stmt = $db->prepare("INSERT INTO sales (invoice_no, user_id, customer_id, sale_date, subtotal, grand_total, status, notes) 
                              VALUES (?, ?, ?, NOW(), ?, ?, 'hold', ?)");
        $stmt->execute([$hold_no, $user_id, $customer_id, (float)$input['subtotal'], (float)$input['grand_total'], $notes]);
        $sale_id = $db->lastInsertId();

        $item_stmt = $db->prepare("INSERT INTO sale_items (sale_id, product_id, variant_id, article_code, product_name, size_number, color_name, unit_cost, unit_price, quantity, discount, total) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, 0.00, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $item_stmt->execute([
                $sale_id,
                (int)$item['product_id'],
                (int)$item['variant_id'],
                $item['article_code'],
                $item['product_name'],
                $item['size_number'],
                $item['color_name'],
                (float)$item['unit_price'],
                (int)$item['quantity'],
                (float)($item['discount'] ?? 0),
                (float)$item['total']
            ]);
        }
        $db->commit();

        echo json_encode(['success' => true, 'hold_no' => $hold_no, 'sale_id' => $sale_id]);
        exit;
    }

    // 8. Get Held Bills
    if ($action === 'get_held_sales') {
        $stmt = $db->prepare("SELECT s.*, c.name as customer_name, COUNT(si.id) as item_count 
                              FROM sales s 
                              LEFT JOIN customers c ON s.customer_id = c.id 
                              LEFT JOIN sale_items si ON s.id = si.sale_id 
                              WHERE s.status = 'hold' 
                              GROUP BY s.id ORDER BY s.id DESC");
        $stmt->execute();
        $held = $stmt->fetchAll();
        echo json_encode(['success' => true, 'held_sales' => $held]);
        exit;
    }

    // 9. Resume Held Bill
    if ($action === 'resume_sale') {
        $sale_id = (int)($_GET['sale_id'] ?? $_POST['sale_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM sales WHERE id = ? AND status = 'hold'");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch();

        if (!$sale) {
            echo json_encode(['success' => false, 'message' => 'Held bill not found']);
            exit;
        }

        $stmt = $db->prepare("SELECT si.*, pv.barcode, pv.stock_qty 
                              FROM sale_items si 
                              JOIN product_variants pv ON si.variant_id = pv.id 
                              WHERE si.sale_id = ?");
        $stmt->execute([$sale_id]);
        $items = $stmt->fetchAll();

        // Delete hold sale after fetching so it can be resumed
        $db->prepare("DELETE FROM sales WHERE id = ?")->execute([$sale_id]);

        echo json_encode(['success' => true, 'sale' => $sale, 'items' => $items]);
        exit;
    }

    // Default Unknown Action
    echo json_encode(['success' => false, 'message' => 'Invalid action: ' . htmlspecialchars($action)]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
