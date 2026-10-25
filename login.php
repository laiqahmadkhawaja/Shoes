<?php
// login.php
require_once __DIR__ . '/config/functions.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user;
            log_audit('LOGIN', 'AUTH', $user['id'], 'User logged in successfully');
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password. Please try again.";
        }
    }
}

$shop_name = get_setting('shop_name', 'StepStyle Footwear & Shoes');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login &mdash; <?= clean($shop_name) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 440px;
            width: 100%;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>

<div class="container p-3">
    <div class="card login-card mx-auto bg-white p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle mb-3" style="width: 64px; height: 64px; font-size: 28px;">
                <i class="fa-solid fa-shoe-prints"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1"><?= clean($shop_name) ?></h4>
            <p class="text-muted small">Footwear ERP, Size Matrix &amp; POS Billing</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3">
                <i class="fa-solid fa-circle-exclamation me-1"></i> <?= clean($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-3">
                <label class="form-label text-secondary small fw-semibold">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="admin / cashier / manager" required autofocus value="admin">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label text-secondary small fw-semibold">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required value="admin123">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In to POS
            </button>
        </form>

        <div class="mt-4 pt-3 border-top text-center text-muted small">
            <div class="fw-semibold mb-1">Demo Credentials:</div>
            <span class="badge bg-light text-dark border">Admin: admin / admin123</span>
            <span class="badge bg-light text-dark border">Cashier: cashier / admin123</span>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
