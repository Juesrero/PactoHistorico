<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME); ?></title>
    <meta name="description" content="Sistema web local para control de asistencia de reuniones">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) . '?v=' . urlencode((string) @filemtime(BASE_PATH . '/assets/css/app.css')); ?>">
</head>
<body class="app-body">
<?php require BASE_PATH . '/includes/layout/navbar.php'; ?>

<main class="container py-4">
    <?php if ($flash = get_flash('message')): ?>
        <?php
            $flashType = (string) ($flash['type'] ?? 'info');
            $flashIcon = match ($flashType) {
                'success' => 'fa-circle-check',
                'warning' => 'fa-triangle-exclamation',
                'danger' => 'fa-circle-xmark',
                default => 'fa-circle-info',
            };
        ?>
        <div class="alert alert-<?= e($flashType); ?> alert-dismissible fade show app-alert" role="alert">
            <div class="d-flex align-items-start gap-2">
                <i class="fa-solid <?= e($flashIcon); ?> mt-1"></i>
                <div><?= e((string) $flash['message']); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

