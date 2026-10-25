<?php
// includes/footer.php
?>
</main>

<?php if (is_logged_in()): ?>
<footer class="bg-white border-top py-3 mt-auto text-muted small text-center">
    <div class="container-fluid px-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <div>
            &copy; <?= date('Y') ?> <strong><?= clean(get_setting('shop_name', 'Shoes POS')) ?></strong> &mdash; Footwear Retail &amp; Size Matrix ERP.
        </div>
        <div class="d-flex align-items-center gap-3">
            <span><kbd>F1</kbd> POS Search</span>
            <span><kbd>F2</kbd> Pay / Checkout</span>
            <span><kbd>F4</kbd> Hold Bill</span>
            <span><i class="fa-solid fa-clock text-primary"></i> <?= date('d M, Y') ?></span>
        </div>
    </div>
</footer>
<?php endif; ?>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if (isset($current_page) && $current_page === 'barcode_labels.php'): ?>
<!-- JsBarcode for barcode rendering -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<?php endif; ?>

<?php if (isset($current_page) && in_array($current_page, ['dashboard.php', 'reports.php'])): ?>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php endif; ?>

<script>
// Safe HTML escaping helper
function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Global Shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'F1') {
        e.preventDefault();
        if (window.location.pathname.indexOf('pos.php') !== -1) {
            let searchEl = document.getElementById('pos_search_input');
            if (searchEl) searchEl.focus();
        } else {
            window.location.href = 'pos.php';
        }
    }
});
</script>
</body>
</html>
