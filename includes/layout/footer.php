</main>

<footer class="footer mt-auto py-3 border-top bg-white">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-1">
        <div class="d-flex align-items-center gap-3">
            <img
                src="<?= e(LOGO_PATH); ?>"
                alt="Logo Pacto Historico"
                class="footer-logo"
                width="100"
                height="100"
                style="width:100px;height:100px;"
                onerror="this.onerror=null;this.src='<?= e(url('Logo/pacto.png')); ?>';"
            >
            <small class="text-muted"><i class="fa-regular fa-copyright me-1"></i><?= date('Y'); ?> Pacto Historico</small>
        </div>
        <small class="text-muted"><i class="fa-solid fa-code me-1"></i>Version <?= e(APP_VERSION); ?> | PHP + MySQL + Bootstrap 5</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?= e(url('assets/js/app.js')) . '?v=' . urlencode((string) @filemtime(BASE_PATH . '/assets/js/app.js')); ?>"></script>
</body>
</html>
