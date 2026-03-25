<?php
$menu = [
    'dashboard' => 'Dashboard',
    'personas' => 'Personas',
    'reuniones' => 'Reuniones',
    'asistencias' => 'Asistencias',
];
?>
<header class="top-header shadow-sm sticky-top bg-white">
    <nav class="navbar navbar-expand-lg bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(page_url('dashboard')); ?>">
                <img
                    src="<?= e(LOGO_PATH); ?>"
                    alt="Logo Pacto"
                    class="brand-logo"
                    onerror="this.onerror=null;this.src='<?= e(url('Logo/pacto.png')); ?>';"
                >
                <span class="brand-title"><?= e(APP_NAME); ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Mostrar menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php foreach ($menu as $key => $label): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === $key ? 'active' : ''; ?>" href="<?= e(page_url($key)); ?>">
                                <?= e($label); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>
