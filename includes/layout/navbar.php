<?php
$menu = [
    'dashboard' => 'Dashboard',
    'analisis' => 'Analisis',
];

$gestionMenu = [
    'personas' => 'Personas',
    'reuniones' => 'Reuniones',
    'actas' => 'Actas',
];

$gestionActive = in_array($currentPage, array_keys($gestionMenu), true);
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
                <span class="brand-title">Pacto Historico</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Mostrar menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a
                            class="nav-link dropdown-toggle <?= $gestionActive ? 'active' : ''; ?>"
                            href="#"
                            id="gestionNavbarDropdown"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            Gestion
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="gestionNavbarDropdown">
                            <?php foreach ($gestionMenu as $key => $label): ?>
                                <li>
                                    <a class="dropdown-item <?= $currentPage === $key ? 'active' : ''; ?>" href="<?= e(page_url($key)); ?>">
                                        <?= e($label); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
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
