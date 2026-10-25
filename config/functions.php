<?php
// config/functions.php - Global Utility Functions & Helpers

require_once __DIR__ . '/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// System setting getter
function get_setting($key, $default = '') {
    static $settings_cache = null;
    if ($settings_cache === null) {
        $db = getDB();
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
        $settings_cache = [];
        while ($row = $stmt->fetch()) {
            $settings_cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings_cache[$key] ?? $default;
}

// Update setting
function update_setting($key, $value) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

// Format Currency
function format_currency($amount, $show_symbol = true) {
    $symbol = get_setting('currency_symbol', 'Rs.');
    $formatted = number_format((float)$amount, 2);
    return $show_symbol ? $symbol . ' ' . $formatted : $formatted;
}

// User Authentication
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function user_role() {
    return $_SESSION['user']['role'] ?? '';
}

function require_auth() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

function has_permission($allowed_roles) {
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    return in_array(user_role(), $allowed_roles);
}

function require_permission($allowed_roles) {
    require_auth();
    if (!has_permission($allowed_roles)) {
        $_SESSION['flash_error'] = "You do not have permission to access that section.";
        header("Location: dashboard.php");
        exit;
    }
}

// Audit Logger
function log_audit($action, $module, $record_id = null, $details = '') {
    try {
        $db = getDB();
        $user_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, module, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $module, $record_id, $details, $ip]);
    } catch (Exception $e) {
        // silent fail for audit log
    }
}

// Cash Register Helper
function get_active_register($user_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM cash_registers WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// Flash Messages
function set_flash($type, $msg) {
    $_SESSION['flash_' . $type] = $msg;
}

function get_flash($type) {
    if (isset($_SESSION['flash_' . $type])) {
        $msg = $_SESSION['flash_' . $type];
        unset($_SESSION['flash_' . $type]);
        return $msg;
    }
    return null;
}

// Sanitize String
function clean($str) {
    return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
}
