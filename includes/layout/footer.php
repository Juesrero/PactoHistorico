</main>

<footer class="footer mt-auto py-3 border-top bg-white">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-1">
        <small class="text-muted"><i class="fa-regular fa-copyright me-1"></i><?= date('Y'); ?> <?= e(APP_NAME); ?></small>
        <small class="text-muted"><i class="fa-solid fa-code me-1"></i>Version <?= e(APP_VERSION); ?> | PHP + MySQL + Bootstrap 5</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?= e(url('assets/js/app.js')); ?>"></script>
</body>
</html>
