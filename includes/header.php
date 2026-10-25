<?php
// includes/header.php
require_once __DIR__ . '/../config/functions.php';
$user = current_user();
$active_reg = $user ? get_active_register($user['id']) : null;
$current_page = basename($_SERVER['PHP_SELF']);
$shop_name = get_setting('shop_name', 'StepStyle Footwear & Shoes');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean($shop_name) ?> &mdash; Shoes POS</title>
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if (is_logged_in()): ?>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
    <div class="container-fluid px-2 px-lg-3">
        <a class="navbar-brand d-flex align-items-center gap-2 me-2 me-xl-3" href="dashboard.php">
            <span class="p-1 bg-white bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                <i class="fa-solid fa-shoe-prints text-white fs-6"></i>
            </span>
            <span><?= clean($shop_name) ?></span>
        </a>

        <button class="navbar-toggler border-0 p-1" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-lg-center gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                        <i class="fa-solid fa-chart-pie me-1"></i> Dashboard
                    </a>
                </li>

                <li class="nav-item ms-lg-1 me-lg-1">
                    <a class="nav-link btn-nav-pos d-inline-flex align-items-center <?= $current_page == 'pos.php' ? 'active' : '' ?>" href="pos.php">
                        <i class="fa-solid fa-cash-register me-1"></i> POS Billing
                    </a>
                </li>

                <!-- Products Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['products.php', 'product_add.php', 'product_edit.php', 'inventory.php', 'stock_adjust.php', 'barcode_labels.php']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fa-solid fa-boxes-stacked me-1"></i> Products
                    </a>
                    <ul class="dropdown-menu shadow-sm">
                        <li><a class="dropdown-item" href="products.php"><i class="fa-solid fa-list me-2 text-primary"></i>All Products</a></li>
                        <?php if (has_permission(['admin', 'manager', 'inventory'])): ?>
                            <li><a class="dropdown-item" href="product_add.php"><i class="fa-solid fa-plus-circle me-2 text-success"></i>Add Product</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="inventory.php"><i class="fa-solid fa-table-cells me-2 text-info"></i>Shoe Stock Matrix</a></li>
                        <?php if (has_permission(['admin', 'manager', 'inventory'])): ?>
                            <li><a class="dropdown-item" href="stock_adjust.php"><i class="fa-solid fa-sliders me-2 text-warning"></i>Stock Adjustment</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="barcode_labels.php"><i class="fa-solid fa-barcode me-2 text-dark"></i>Barcode Labels</a></li>
                    </ul>
                </li>

                <!-- Purchases & Suppliers -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['purchases.php', 'purchase_add.php', 'suppliers.php', 'supplier_ledger.php']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fa-solid fa-truck-ramp-box me-1"></i> Purchases
                    </a>
                    <ul class="dropdown-menu shadow-sm">
                        <li><a class="dropdown-item" href="purchases.php"><i class="fa-solid fa-file-invoice me-2 text-primary"></i>Purchase Invoices</a></li>
                        <?php if (has_permission(['admin', 'manager', 'inventory'])): ?>
                            <li><a class="dropdown-item" href="purchase_add.php"><i class="fa-solid fa-cart-plus me-2 text-success"></i>New Purchase (Matrix)</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="suppliers.php"><i class="fa-solid fa-handshake me-2 text-dark"></i>Suppliers &amp; Payables</a></li>
                    </ul>
                </li>

                <!-- Customers & Khata -->
                <li class="nav-item">
                    <a class="nav-link <?= in_array($current_page, ['customers.php', 'customer_ledger.php']) ? 'active' : '' ?>" href="customers.php">
                        <i class="fa-solid fa-users me-1"></i> Customers
                    </a>
                </li>

                <!-- Returns & Exchanges -->
                <li class="nav-item">
                    <a class="nav-link <?= in_array($current_page, ['returns.php', 'return_create.php']) ? 'active' : '' ?>" href="returns.php">
                        <i class="fa-solid fa-arrow-rotate-left me-1"></i> Returns
                    </a>
                </li>

                <!-- Cash & Expenses -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['expenses.php', 'cash_register.php']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fa-solid fa-wallet me-1"></i> Accounts
                    </a>
                    <ul class="dropdown-menu shadow-sm">
                        <li><a class="dropdown-item" href="cash_register.php"><i class="fa-solid fa-vault me-2 text-primary"></i>Cash Register</a></li>
                        <li><a class="dropdown-item" href="expenses.php"><i class="fa-solid fa-receipt me-2 text-danger"></i>Expenses</a></li>
                    </ul>
                </li>

                <?php if (has_permission(['admin', 'manager', 'accountant'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'reports.php' ? 'active' : '' ?>" href="reports.php">
                        <i class="fa-solid fa-chart-line me-1"></i> Reports
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Right Utilities (Desktop) -->
            <div class="d-none d-lg-flex align-items-center gap-2 mt-2 mt-lg-0">
                <!-- Cash Register Status Badge -->
                <?php if ($active_reg): ?>
                    <a href="cash_register.php" class="badge bg-success text-decoration-none py-2 px-2 d-flex align-items-center gap-1 shadow-sm" title="Register Open">
                        <i class="fa-solid fa-lock-open"></i> <span>Drawer Open</span>
                    </a>
                <?php else: ?>
                    <a href="cash_register.php" class="badge bg-warning text-dark text-decoration-none py-2 px-2 d-flex align-items-center gap-1 shadow-sm" title="Open Drawer">
                        <i class="fa-solid fa-lock"></i> <span>Open Drawer</span>
                    </a>
                <?php endif; ?>

                <!-- User Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-light dropdown-toggle d-flex align-items-center gap-1 shadow-sm py-1 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-circle-user text-primary"></i>
                        <span class="fw-semibold" style="font-size:0.85rem;"><?= clean($user['username'] ?? 'User') ?></span>
                        <span class="badge bg-secondary text-uppercase" style="font-size:0.6rem;"><?= clean($user['role'] ?? '') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><h6 class="dropdown-header"><?= clean($user['full_name'] ?? 'Staff') ?></h6></li>
                        <?php if (has_permission(['admin'])): ?>
                            <li><a class="dropdown-item" href="users.php"><i class="fa-solid fa-user-gear me-2"></i>Users</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fa-solid fa-sliders me-2"></i>Settings</a></li>
                            <li><a class="dropdown-item" href="backup.php"><i class="fa-solid fa-database me-2"></i>Backup &amp; Restore</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>

            <!-- Mobile User Profile Block (Full-Width, Never Clipped) -->
            <div class="d-lg-none mt-3 pt-3 border-top border-white border-opacity-25">
                <div class="p-2 rounded-3 bg-white bg-opacity-10 text-white mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-circle-user fs-3 text-info"></i>
                            <div>
                                <div class="fw-bold small"><?= clean($user['full_name'] ?? $user['username'] ?? 'Staff') ?></div>
                                <span class="badge bg-secondary text-uppercase" style="font-size:0.65rem;"><?= clean($user['role'] ?? 'cashier') ?></span>
                            </div>
                        </div>
                        <a href="logout.php" class="btn btn-sm btn-outline-danger text-white border-danger-subtle py-1 px-3 fw-semibold">
                            <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                        </a>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="cash_register.php" class="btn btn-sm <?= $active_reg ? 'btn-success' : 'btn-warning text-dark' ?> flex-fill py-2 fw-semibold">
                        <i class="fa-solid <?= $active_reg ? 'fa-lock-open' : 'fa-lock' ?> me-1"></i> <?= $active_reg ? 'Drawer Open' : 'Open Drawer' ?>
                    </a>
                    <?php if (has_permission(['admin'])): ?>
                        <a href="settings.php" class="btn btn-sm btn-outline-light flex-fill py-2">
                            <i class="fa-solid fa-sliders me-1"></i> Settings
                        </a>
                        <a href="users.php" class="btn btn-sm btn-outline-light flex-fill py-2">
                            <i class="fa-solid fa-user-gear me-1"></i> Users
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="main-content">
    <?php if (is_logged_in()): ?>
    <div class="container-fluid px-2 px-lg-3 pt-2">
        <?php if ($flash_err = get_flash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm py-2 px-3 small" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= clean($flash_err) ?>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($flash_succ = get_flash('success')): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm py-2 px-3 small" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?= clean($flash_succ) ?>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
