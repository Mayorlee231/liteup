<!-- Keep shared application footers at the bottom on short pages. -->
<style>
    html, body { min-height: 100%; }
    body { min-height: 100vh; display: flex; flex-direction: column; }
    body > main { flex: 1 0 auto; }
    body > footer { width: 100%; flex: 0 0 auto; margin-top: auto !important; }
</style>
<footer class="footer bg-dark text-light text-center py-2 mt-4">
    <div class="container small">
        <p class="mb-0">&copy; <?= date('Y'); ?> LiteUp. All rights reserved.</p>
    </div>
</footer>

<!-- Bootstrap JS is loaded once here so every shared page has working dropdowns. -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script src="../asset/js/security-actions.js" defer></script>
