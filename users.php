<?php
// users.php - Users, Roles & Permissions Management
require_once __DIR__ . '/includes/auth_check.php';
require_permission(['admin']);

$db = getDB();
$error = '';
$success = '';

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = clean($_POST['role'] ?? 'cashier');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($username) || empty($password) || empty($full_name)) {
        $error = "Username, password and full name are required.";
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username '{$username}' already taken. Please choose another.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, full_name, role, phone, is_active) 
                                  VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$username, $hash, $full_name, $role, $phone]);
            $new_user_id = $db->lastInsertId();
            log_audit('USER_CREATE', 'USERS', $new_user_id, "Created user {$username} with role {$role}");
            $success = "User {$full_name} ({$username}) created successfully!";
        }
    }
}

// Handle Toggle Active/Inactive
if (isset($_GET['toggle_id'])) {
    $t_id = (int)$_GET['toggle_id'];
    if ($t_id === (int)$_SESSION['user_id']) {
        $error = "You cannot deactivate your own current login account!";
    } else {
        $db->prepare("UPDATE users SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?")->execute([$t_id]);
        log_audit('USER_TOGGLE', 'USERS', $t_id, "Toggled user active status for user #{$t_id}");
        set_flash('success', "User status updated.");
        header("Location: users.php");
        exit;
    }
}

// Fetch all users
$users = $db->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-users-gear text-primary me-2"></i>User Accounts
            </h2>
            <p class="text-muted small mb-0">Role-based access control for Shop Owner, Manager, Cashier, Inventory Staff, and Accountant</p>
        </div>
        <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fa-solid fa-user-plus me-1"></i> Add New User
        </button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= clean($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success shadow-sm mb-4"><i class="fa-solid fa-circle-check me-2"></i><?= clean($success) ?></div>
    <?php endif; ?>

    <!-- Role Reference Card -->
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="row g-2 text-muted small">
                    <div class="col-md"><strong class="text-primary">Admin/Owner:</strong> Full access to system, settings, financial reports, users.</div>
                    <div class="col-md"><strong class="text-success">Store Manager:</strong> POS, products, stock adjustments, purchases, customers.</div>
                    <div class="col-md"><strong class="text-info">Cashier:</strong> Fast POS billing, held bills, receipts, sales returns.</div>
                    <div class="col-md"><strong class="text-warning">Inventory Staff:</strong> Products catalog, stock receiving, size matrix.</div>
                    <div class="col-md"><strong class="text-secondary">Accountant:</strong> Khata ledgers, expenses, payments, profit reports.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Staff Full Name</th>
                        <th>Username</th>
                        <th>Assigned Role</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Registered Date</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= clean($u['full_name']) ?></div>
                            </td>
                            <td><code><?= clean($u['username']) ?></code></td>
                            <td>
                                <span class="badge <?= match($u['role']) { 'admin' => 'bg-primary', 'manager' => 'bg-success', 'cashier' => 'bg-info text-dark', 'inventory' => 'bg-warning text-dark', 'accountant' => 'bg-secondary', default => 'bg-light text-dark' } ?> text-uppercase"><?= clean($u['role']) ?></span>
                            </td>
                            <td><?= clean($u['phone'] ?? '-') ?></td>
                            <td>
                                <?php if ($u['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Deactivated</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                            <td class="text-center">
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <a href="users.php?toggle_id=<?= $u['id'] ?>" class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>" 
                                       onclick="return confirm('Are you sure you want to change this user status?')" title="Toggle Status">
                                        <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add User -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">Create Staff User Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="users.php">
                <input type="hidden" name="add_user" value="1">
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Staff Full Name *</label>
                        <input type="text" name="full_name" class="form-control" required placeholder="e.g. Asad Mehmood">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Login Username *</label>
                            <input type="text" name="username" class="form-control" required placeholder="asad123">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Password *</label>
                            <input type="password" name="password" class="form-control" required placeholder="••••••••">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Role / Permission *</label>
                            <select name="role" class="form-select" required>
                                <option value="cashier">Cashier</option>
                                <option value="manager">Store Manager</option>
                                <option value="inventory">Inventory Staff</option>
                                <option value="accountant">Accountant</option>
                                <option value="admin">Administrator (Full Access)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="0300 1234567">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
