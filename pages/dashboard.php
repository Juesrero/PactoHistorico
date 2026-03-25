<?php
$stats = [
    'reuniones' => 0,
    'personas' => 0,
    'asistencias' => 0,
    'testigos' => 0,
];
$proximaReunion = null;

$attendanceLabels = [];
$attendanceValues = [];
$testigosCount = 0;
$noTestigosCount = 0;

try {
    $pdo = db();
    $stats['reuniones'] = (int) $pdo->query('SELECT COUNT(*) FROM reuniones')->fetchColumn();
    $stats['personas'] = (int) $pdo->query('SELECT COUNT(*) FROM personas')->fetchColumn();
    $stats['asistencias'] = (int) $pdo->query('SELECT COUNT(*) FROM asistencias')->fetchColumn();
    $stats['testigos'] = (int) $pdo->query('SELECT COUNT(*) FROM personas WHERE es_testigo = 1')->fetchColumn();

    $stmtProxima = $pdo->query('SELECT nombre_reunion, lugar_reunion, fecha, hora FROM reuniones WHERE CONCAT(fecha, " ", hora) >= NOW() ORDER BY fecha ASC, hora ASC LIMIT 1');
    $proximaReunion = $stmtProxima->fetch();

    $stmtChart = $pdo->query('SELECT r.nombre_reunion, COUNT(a.id) AS total_asistentes
                              FROM reuniones r
                              LEFT JOIN asistencias a ON a.reunion_id = r.id
                              GROUP BY r.id, r.nombre_reunion
                              ORDER BY r.fecha DESC, r.hora DESC
                              LIMIT 6');
    $rowsChart = $stmtChart->fetchAll();

    if ($rowsChart !== []) {
        $rowsChart = array_reverse($rowsChart);
        foreach ($rowsChart as $row) {
            $attendanceLabels[] = (string) $row['nombre_reunion'];
            $attendanceValues[] = (int) $row['total_asistentes'];
        }
    }

    $testigosCount = (int) $stats['testigos'];
    $noTestigosCount = max(0, (int) $stats['personas'] - $testigosCount);
} catch (Throwable $exception) {
    flash('message', 'No se pudo cargar la informacion del dashboard. Verifica la base de datos.', 'warning');
}

$attendanceLabelsJson = json_encode($attendanceLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$attendanceValuesJson = json_encode($attendanceValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$personasDistLabelsJson = json_encode(['Testigos', 'No testigos'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$personasDistValuesJson = json_encode([$testigosCount, $noTestigosCount], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

if (!is_string($attendanceLabelsJson)) {
    $attendanceLabelsJson = '[]';
}
if (!is_string($attendanceValuesJson)) {
    $attendanceValuesJson = '[]';
}
if (!is_string($personasDistLabelsJson)) {
    $personasDistLabelsJson = '[]';
}
if (!is_string($personasDistValuesJson)) {
    $personasDistValuesJson = '[]';
}
?>

<section class="mb-4">
    <h1 class="section-title">Panel principal</h1>
    <p class="section-subtitle">Resumen general de reuniones y asistencias registradas.</p>
</section>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <article class="summary-card p-3">
            <small class="text-muted">Reuniones</small>
            <div class="summary-value mt-2"><?= e((string) $stats['reuniones']); ?></div>
        </article>
    </div>
    <div class="col-6 col-lg-3">
        <article class="summary-card p-3">
            <small class="text-muted">Personas</small>
            <div class="summary-value mt-2"><?= e((string) $stats['personas']); ?></div>
        </article>
    </div>
    <div class="col-6 col-lg-3">
        <article class="summary-card p-3">
            <small class="text-muted">Asistencias</small>
            <div class="summary-value mt-2"><?= e((string) $stats['asistencias']); ?></div>
        </article>
    </div>
    <div class="col-6 col-lg-3">
        <article class="summary-card p-3">
            <small class="text-muted">Testigos</small>
            <div class="summary-value mt-2"><?= e((string) $stats['testigos']); ?></div>
        </article>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card-clean p-4 h-100">
            <h2 class="h5 mb-3">Acciones rapidas</h2>
            <div class="d-grid gap-2 d-sm-flex">
                <a class="btn btn-primary" href="<?= e(page_url('personas')); ?>"><i class="fa-solid fa-user-plus me-1"></i>Registrar persona</a>
                <a class="btn btn-outline-primary" href="<?= e(page_url('reuniones')); ?>"><i class="fa-solid fa-calendar-plus me-1"></i>Crear reunion</a>
                <a class="btn btn-outline-secondary" href="<?= e(page_url('asistencias')); ?>"><i class="fa-solid fa-square-check me-1"></i>Registrar asistencia</a>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-clean p-4 h-100">
            <h2 class="h5 mb-3">Proxima reunion</h2>
            <?php if ($proximaReunion): ?>
                <p class="mb-1"><strong><?= e((string) $proximaReunion['nombre_reunion']); ?></strong></p>
                <p class="mb-1 text-muted"><i class="fa-solid fa-location-dot me-1"></i><?= e((string) $proximaReunion['lugar_reunion']); ?></p>
                <p class="mb-0 text-muted"><i class="fa-regular fa-clock me-1"></i><?= e((string) $proximaReunion['fecha']); ?> | <?= e(substr((string) $proximaReunion['hora'], 0, 5)); ?></p>
            <?php else: ?>
                <p class="text-muted mb-0">Aun no hay reuniones programadas.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-clean p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 mb-0">Asistencias por reunion</h2>
                <small class="text-muted">Ultimas 6 reuniones</small>
            </div>
            <div class="chart-wrap">
                <canvas
                    id="chartAsistenciasReunion"
                    class="chart-canvas"
                    data-labels='<?= e($attendanceLabelsJson); ?>'
                    data-values='<?= e($attendanceValuesJson); ?>'
                ></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-clean p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 mb-0">Distribucion de personas</h2>
                <small class="text-muted">Testigos vs no testigos</small>
            </div>
            <div class="chart-wrap chart-wrap-donut">
                <canvas
                    id="chartPersonasDistribucion"
                    class="chart-canvas"
                    data-labels='<?= e($personasDistLabelsJson); ?>'
                    data-values='<?= e($personasDistValuesJson); ?>'
                ></canvas>
            </div>
            <div class="chart-legend mt-3">
                <span class="legend-item">
                    <span class="legend-color legend-color-testigo"></span>
                    Testigos: <strong><?= e((string) $testigosCount); ?></strong>
                </span>
                <span class="legend-item">
                    <span class="legend-color legend-color-no-testigo"></span>
                    No testigos: <strong><?= e((string) $noTestigosCount); ?></strong>
                </span>
            </div>
        </div>
    </div>
</div>

