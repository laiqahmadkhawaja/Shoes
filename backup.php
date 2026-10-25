<?php
// backup.php - Database Backup & Restore Utility
require_once __DIR__ . '/includes/auth_check.php';
require_permission(['admin']);

$db = getDB();
$error = '';
$success = '';

// Handle Backup Download
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $tables = [];
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sqlDump = "-- Shoes Shop Management System SQL Backup\n";
    $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $tbl) {
        $createStmt = $db->query("SHOW CREATE TABLE `{$tbl}`")->fetch(PDO::FETCH_NUM);
        $sqlDump .= "DROP TABLE IF EXISTS `{$tbl}`;\n";
        $sqlDump .= $createStmt[1] . ";\n\n";

        $rows = $db->query("SELECT * FROM `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $cols = array_keys($rows[0]);
            $colNames = implode('`, `', $cols);
            foreach ($rows as $r) {
                $values = array_map(function($val) use ($db) {
                    if ($val === null) return "NULL";
                    return $db->quote($val);
                }, array_values($r));
                $valStr = implode(', ', $values);
                $sqlDump .= "INSERT INTO `{$tbl}` (`{$colNames}`) VALUES ({$valStr});\n";
            }
            $sqlDump .= "\n";
        }
    }
    $sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    log_audit('BACKUP_DOWNLOAD', 'DATABASE', null, 'Downloaded full database SQL backup');

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="shoes_db_backup_' . date('Ymd_His') . '.sql"');
    header('Content-Length: ' . strlen($sqlDump));
    echo $sqlDump;
    exit;
}

// Handle Restore from Uploaded SQL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql_file'])) {
    if ($_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['sql_file']['tmp_name'];
        $sql = file_get_contents($tmp);
        if (!empty($sql)) {
            try {
                $db->exec($sql);
                log_audit('BACKUP_RESTORE', 'DATABASE', null, 'Restored database from uploaded SQL file');
                $success = "Database restored successfully from uploaded backup!";
            } catch (Exception $e) {
                $error = "Restore error: " . $e->getMessage();
            }
        } else {
            $error = "Uploaded file was empty.";
        }
    } else {
        $error = "File upload failed with error code: " . $_FILES['sql_file']['error'];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-database text-primary me-2"></i>Database Backup &amp; Disaster Recovery
            </h2>
            <p class="text-muted small mb-0">Create instant full SQL dumps or restore existing store data</p>
        </div>
        <a href="settings.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Settings
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= clean($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success shadow-sm mb-4"><i class="fa-solid fa-circle-check me-2"></i><?= clean($success) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Download Backup -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 p-4 bg-white h-100 text-center">
                <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mx-auto mb-3" style="width: 72px; height: 72px; font-size: 32px;">
                    <i class="fa-solid fa-cloud-arrow-down"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">Create &amp; Download Backup</h4>
                <p class="text-muted small mb-4">
                    Generates a complete, self-contained MySQL dump containing all products, size matrix variants, barcodes, customer khata, and sales transactions.
                </p>
                <div class="mt-auto">
                    <a href="backup.php?action=download" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm">
                        <i class="fa-solid fa-download me-2"></i> Download Full SQL Backup (.sql)
                    </a>
                </div>
            </div>
        </div>

        <!-- Restore Database -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 p-4 bg-white h-100 text-center">
                <div class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle mx-auto mb-3" style="width: 72px; height: 72px; font-size: 32px;">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">Restore Database from File</h4>
                <p class="text-muted small mb-4">
                    Upload a previously downloaded <code>.sql</code> backup file to restore database tables and records. Caution: Existing data will be overwritten.
                </p>
                <form method="POST" action="backup.php" enctype="multipart/form-data" class="mt-auto text-start">
                    <div class="mb-3">
                        <input type="file" name="sql_file" class="form-control" accept=".sql" required>
                    </div>
                    <button type="submit" class="btn btn-outline-danger btn-lg w-100 fw-bold" onclick="return confirm('WARNING: Restoring will overwrite existing data. Are you sure you wish to proceed?')">
                        <i class="fa-solid fa-upload me-2"></i> Upload &amp; Restore Database
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
