<?php
$stats = [
    'reuniones' => 0,
    'personas' => 0,
    'asistencias' => 0,
    'testigos' => 0,
];
$proximaReunion = null;
$promedioAsistencia = 0.0;
$porcentajeTestigos = 0;
$fechaProximaReunion = null;
$horaProximaReunion = null;

try {
    $pdo = db();
    $stats['reuniones'] = (int) $pdo->query('SELECT COUNT(*) FROM reuniones')->fetchColumn();
    $stats['personas'] = (int) $pdo->query('SELECT COUNT(*) FROM personas')->fetchColumn();
    $stats['asistencias'] = (int) $pdo->query('SELECT COUNT(*) FROM asistencias')->fetchColumn();
    $stats['testigos'] = (int) $pdo->query('SELECT COUNT(*) FROM personas WHERE es_testigo = 1')->fetchColumn();

    $stmtProxima = $pdo->query('SELECT nombre_reunion, lugar_reunion, fecha, hora FROM reuniones WHERE CONCAT(fecha, " ", hora) >= NOW() ORDER BY fecha ASC, hora ASC LIMIT 1');
    $proximaReunion = $stmtProxima->fetch();
} catch (Throwable $exception) {
    flash('message', 'No se pudo cargar la informacion del dashboard. Verifica la base de datos.', 'warning');
}

$promedioAsistencia = $stats['reuniones'] > 0
    ? round($stats['asistencias'] / $stats['reuniones'], 1)
    : 0.0;

$porcentajeTestigos = $stats['personas'] > 0
    ? (int) round(($stats['testigos'] / $stats['personas']) * 100)
    : 0;

if (is_array($proximaReunion)) {
    $fecha = DateTime::createFromFormat('Y-m-d', (string) ($proximaReunion['fecha'] ?? ''));
    $hora = DateTime::createFromFormat('H:i:s', (string) ($proximaReunion['hora'] ?? ''));
    $fechaProximaReunion = $fecha ? $fecha->format('d/m/Y') : null;
    $horaProximaReunion = $hora ? $hora->format('H:i') : null;
}
?>

<section class="dashboard-hero card-clean mb-4">
    <div class="row g-4 align-items-stretch">
        <div class="col-xl-7">
            <div class="dashboard-hero-copy h-100">
                <span class="dashboard-kicker">Vista general</span>
                <h1 class="dashboard-title">Panel principal</h1>
                <p class="dashboard-subtitle">Consulta el estado del proceso, entra rapido a los modulos clave y prioriza la siguiente reunion desde una sola pantalla.</p>

                <div class="dashboard-hero-meta">
                    <span class="dashboard-meta-pill">
                        <i class="fa-solid fa-users"></i>
                        <?= e((string) $stats['personas']); ?> personas registradas
                    </span>
                    <span class="dashboard-meta-pill">
                        <i class="fa-solid fa-calendar-check"></i>
                        <?= e((string) $stats['reuniones']); ?> reuniones activas
                    </span>
                    <span class="dashboard-meta-pill">
                        <i class="fa-solid fa-clipboard-user"></i>
                        <?= e((string) $stats['asistencias']); ?> asistencias acumuladas
                    </span>
                </div>

                <div class="dashboard-action-grid">
                    <a class="dashboard-action-card" href="<?= e(page_url('personas')); ?>">
                        <span class="dashboard-action-icon"><i class="fa-solid fa-user-plus"></i></span>
                        <span>
                            <strong>Registrar persona</strong>
                            <small>Agrega nuevos participantes al directorio.</small>
                        </span>
                    </a>
                    <a class="dashboard-action-card" href="<?= e(page_url('reuniones')); ?>">
                        <span class="dashboard-action-icon"><i class="fa-solid fa-calendar-plus"></i></span>
                        <span>
                            <strong>Gestionar reuniones</strong>
                            <small>Crea reuniones y continua la toma de asistencia.</small>
                        </span>
                    </a>
                    <a class="dashboard-action-card" href="<?= e(page_url('actas')); ?>">
                        <span class="dashboard-action-icon"><i class="fa-solid fa-file-lines"></i></span>
                        <span>
                            <strong>Administrar actas</strong>
                            <small>Consulta consecutivos, soportes y descargas.</small>
                        </span>
                    </a>
                    <a class="dashboard-action-card" href="<?= e(page_url('analisis')); ?>">
                        <span class="dashboard-action-icon"><i class="fa-solid fa-chart-column"></i></span>
                        <span>
                            <strong>Ver analisis</strong>
                            <small>Explora indicadores sociodemograficos del registro.</small>
                        </span>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <aside class="dashboard-spotlight h-100">
                <div class="dashboard-spotlight-head">
                    <div>
                        <span class="dashboard-kicker">Siguiente hito</span>
                        <h2 class="dashboard-spotlight-title">Proxima reunion</h2>
                    </div>
                    <span class="dashboard-status-badge <?= $proximaReunion ? 'is-ready' : 'is-muted'; ?>">
                        <?= $proximaReunion ? 'Programada' : 'Sin agenda'; ?>
                    </span>
                </div>

                <?php if ($proximaReunion): ?>
                    <div class="dashboard-spotlight-body">
                        <p class="dashboard-spotlight-name"><?= e((string) $proximaReunion['nombre_reunion']); ?></p>
                        <ul class="dashboard-detail-list">
                            <li>
                                <i class="fa-solid fa-location-dot"></i>
                                <span><?= e((string) $proximaReunion['lugar_reunion']); ?></span>
                            </li>
                            <li>
                                <i class="fa-regular fa-calendar"></i>
                                <span><?= e((string) ($fechaProximaReunion ?? (string) $proximaReunion['fecha'])); ?></span>
                            </li>
                            <li>
                                <i class="fa-regular fa-clock"></i>
                                <span><?= e((string) ($horaProximaReunion ?? substr((string) $proximaReunion['hora'], 0, 5))); ?> horas</span>
                            </li>
                        </ul>
                    </div>
                    <a class="btn btn-primary dashboard-primary-cta" href="<?= e(page_url('reuniones')); ?>">
                        <i class="fa-solid fa-arrow-right me-2"></i>Ir a reuniones
                    </a>
                <?php else: ?>
                    <div class="dashboard-empty-panel">
                        <i class="fa-regular fa-calendar-xmark"></i>
                        <div>
                            <strong>No hay reuniones programadas.</strong>
                            <p class="mb-0">Crea una nueva reunion para empezar a registrar asistencia desde ese detalle.</p>
                        </div>
                    </div>
                    <a class="btn btn-outline-primary dashboard-primary-cta" href="<?= e(page_url('reuniones')); ?>">
                        <i class="fa-solid fa-calendar-plus me-2"></i>Crear reunion
                    </a>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-icon is-blue"><i class="fa-solid fa-calendar-days"></i></div>
            <div>
                <small class="dashboard-stat-label">Reuniones</small>
                <div class="dashboard-stat-value"><?= e((string) $stats['reuniones']); ?></div>
                <p class="dashboard-stat-note">Jornadas y comites registrados en el sistema.</p>
            </div>
        </article>
    </div>
    <div class="col-6 col-xl-3">
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-icon is-indigo"><i class="fa-solid fa-id-card"></i></div>
            <div>
                <small class="dashboard-stat-label">Personas</small>
                <div class="dashboard-stat-value"><?= e((string) $stats['personas']); ?></div>
                <p class="dashboard-stat-note">Base disponible para convocatorias y asistencia.</p>
            </div>
        </article>
    </div>
    <div class="col-6 col-xl-3">
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-icon is-amber"><i class="fa-solid fa-clipboard-check"></i></div>
            <div>
                <small class="dashboard-stat-label">Promedio de asistencia</small>
                <div class="dashboard-stat-value"><?= e(number_format($promedioAsistencia, 1)); ?></div>
                <p class="dashboard-stat-note">Personas registradas por reunion en promedio.</p>
            </div>
        </article>
    </div>
    <div class="col-6 col-xl-3">
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-icon is-green"><i class="fa-solid fa-user-shield"></i></div>
            <div>
                <small class="dashboard-stat-label">Participacion de testigos</small>
                <div class="dashboard-stat-value"><?= e((string) $porcentajeTestigos); ?>%</div>
                <p class="dashboard-stat-note"><?= e((string) $stats['testigos']); ?> personas marcadas como testigo.</p>
            </div>
        </article>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <section class="card-clean dashboard-panel h-100">
            <div class="dashboard-panel-head">
                <div>
                    <h2 class="dashboard-panel-title">Ruta de trabajo</h2>
                    <p class="dashboard-panel-subtitle">Secuencia recomendada para operar el sistema sin perder continuidad.</p>
                </div>
            </div>

            <div class="dashboard-flow-grid">
                <article class="dashboard-flow-step">
                    <span class="dashboard-step-index">1</span>
                    <div>
                        <h3>Actualiza personas</h3>
                        <p>Valida identificacion, roles y tipo de poblacion antes de convocar.</p>
                    </div>
                </article>
                <article class="dashboard-flow-step">
                    <span class="dashboard-step-index">2</span>
                    <div>
                        <h3>Programa reuniones</h3>
                        <p>Define nombre, objetivo, tipo, lugar, fecha y hora en un solo flujo.</p>
                    </div>
                </article>
                <article class="dashboard-flow-step">
                    <span class="dashboard-step-index">3</span>
                    <div>
                        <h3>Registra asistencia</h3>
                        <p>Continua desde el detalle de la reunion y evita duplicados por jornada.</p>
                    </div>
                </article>
                <article class="dashboard-flow-step">
                    <span class="dashboard-step-index">4</span>
                    <div>
                        <h3>Consolida actas y analisis</h3>
                        <p>Adjunta soportes y revisa la composicion poblacional del proceso.</p>
                    </div>
                </article>
            </div>
        </section>
    </div>

    <div class="col-xl-4">
        <section class="card-clean dashboard-panel h-100">
            <div class="dashboard-panel-head">
                <div>
                    <h2 class="dashboard-panel-title">Lectura rapida</h2>
                    <p class="dashboard-panel-subtitle">Indicadores utiles para interpretar el momento actual.</p>
                </div>
            </div>

            <div class="dashboard-insight-list">
                <article class="dashboard-insight-item">
                    <small>Cobertura del registro</small>
                    <strong><?= e((string) $stats['personas']); ?> personas disponibles para convocar</strong>
                </article>
                <article class="dashboard-insight-item">
                    <small>Seguimiento operativo</small>
                    <strong><?= e((string) $stats['asistencias']); ?> asistencias acumuladas en el historico</strong>
                </article>
                <article class="dashboard-insight-item">
                    <small>Rol clave</small>
                    <strong><?= e((string) $stats['testigos']); ?> testigos identificados en la base</strong>
                </article>
                <article class="dashboard-insight-item">
                    <small>Siguiente paso</small>
                    <strong><?= e($proximaReunion ? 'Continuar desde Reuniones para gestionar asistencia.' : 'Programa una reunion para activar el flujo.'); ?></strong>
                </article>
            </div>
        </section>
    </div>
</div>


