<?php
// ajax_catalog.php - Real-time Brand & Category Management API
require_once __DIR__ . '/config/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() || !has_permission(['admin', 'manager', 'inventory'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized action']);
    exit;
}

$db = getDB();

// Read input (GET, POST or JSON body)
$raw_input = file_get_contents('php://input');
$json_input = !empty($raw_input) ? json_decode($raw_input, true) : null;
$action = $_GET['action'] ?? $_POST['action'] ?? ($json_input['action'] ?? '');

try {
    // 1. Quick Add Brand in Real-Time
    if ($action === 'add_brand') {
        $name = trim($_POST['name'] ?? ($json_input['name'] ?? ''));
        $description = trim($_POST['description'] ?? ($json_input['description'] ?? ''));

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Brand name is required.']);
            exit;
        }

        // Check if brand already exists (case-insensitive)
        $check = $db->prepare("SELECT id, name FROM brands WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $check->execute([$name]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            echo json_encode([
                'success' => true,
                'brand' => $existing,
                'message' => 'Brand already existed, selected automatically.'
            ]);
            exit;
        }

        // Insert new brand
        $stmt = $db->prepare("INSERT INTO brands (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        $brand_id = (int)$db->lastInsertId();

        log_audit('CREATE', 'BRAND', $brand_id, "Quick-added brand: {$name}");

        echo json_encode([
            'success' => true,
            'brand' => [
                'id' => $brand_id,
                'name' => $name,
                'description' => $description
            ],
            'message' => "Brand '{$name}' created successfully!"
        ]);
        exit;
    }

    // 2. Quick Add Category in Real-Time
    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? ($json_input['name'] ?? ''));

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Category name is required.']);
            exit;
        }

        // Check if category already exists (case-insensitive)
        $check = $db->prepare("SELECT id, name, slug FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $check->execute([$name]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            echo json_encode([
                'success' => true,
                'category' => $existing,
                'message' => 'Category already existed, selected automatically.'
            ]);
            exit;
        }

        // Generate clean unique slug
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        if (empty($slug)) {
            $slug = 'cat-' . time();
        }

        // Ensure slug uniqueness
        $slug_check = $db->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
        $slug_check->execute([$slug]);
        if ($slug_check->fetch()) {
            $slug .= '-' . rand(10, 99);
        }

        $stmt = $db->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
        $stmt->execute([$name, $slug]);
        $cat_id = (int)$db->lastInsertId();

        log_audit('CREATE', 'CATEGORY', $cat_id, "Quick-added category: {$name}");

        echo json_encode([
            'success' => true,
            'category' => [
                'id' => $cat_id,
                'name' => $name,
                'slug' => $slug
            ],
            'message' => "Category '{$name}' created successfully!"
        ]);
        exit;
    }

    // Default: unknown action
    echo json_encode(['success' => false, 'message' => 'Invalid catalog action.']);
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
