<?php
declare(strict_types=1);

$analisisService = new AnalisisService(db());
$tipoPoblacionService = new TipoPoblacionService(db());

$filters = $analisisService->normalizeFilters($_GET);
$tiposPoblacion = $tipoPoblacionService->listAll();
$genderOptions = $analisisService->listGeneroOptions();

$overview = [
    'summary' => [
        'total_personas' => 0,
        'total_testigos' => 0,
        'total_jurados' => 0,
        'total_con_fecha_nacimiento' => 0,
        'total_sin_fecha_nacimiento' => 0,
    ],
    'gender' => [],
    'population' => [],
    'age_ranges' => [],
];
$loadWarning = '';

try {
    $overview = $analisisService->getOverview($filters);
} catch (Throwable $exception) {
    $loadWarning = 'No fue posible cargar el analisis sociodemografico.';
}

$summary = $overview['summary'];
$genderRows = $overview['gender'];
$populationRows = $overview['population'];
$ageRows = $overview['age_ranges'];
$ageChartRows = array_values(array_filter(
    $ageRows,
    static fn (array $row): bool => (string) ($row['key'] ?? '') !== 'sin_fecha'
));

$jsonEncode = static function (array $value): string {
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    return is_string($json) ? $json : '[]';
};

$totalPersons = max(0, (int) $summary['total_personas']);
$formatPercent = static function (int $value, int $totalPersons): string {
    if ($totalPersons <= 0) {
        return '0.0%';
    }

    return number_format(($value / $totalPersons) * 100, 1) . '%';
};

$genderLabelsJson = $jsonEncode(array_map(static fn (array $row): string => (string) $row['etiqueta'], $genderRows));
$genderValuesJson = $jsonEncode(array_map(static fn (array $row): int => (int) $row['total'], $genderRows));
$populationLabelsJson = $jsonEncode(array_map(static fn (array $row): string => (string) $row['etiqueta'], $populationRows));
$populationValuesJson = $jsonEncode(array_map(static fn (array $row): int => (int) $row['total'], $populationRows));
$ageLabelsJson = $jsonEncode(array_map(static fn (array $row): string => (string) $row['label'], $ageChartRows));
$ageValuesJson = $jsonEncode(array_map(static fn (array $row): int => (int) $row['total'], $ageChartRows));

$filterCount = 0;
foreach ($filters as $key => $value) {
    if (in_array($key, ['es_testigo', 'es_jurado'], true)) {
        if ($value === '0' || $value === '1') {
            $filterCount++;
        }
        continue;
    }

    if ($key === 'tipo_poblacion_id') {
        if ((int) $value > 0) {
            $filterCount++;
        }
        continue;
    }

    if ((string) $value !== '') {
        $filterCount++;
    }
}
?>

<section class="mb-4">
    <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3">
        <div>
            <h1 class="section-title">Analisis sociodemografico</h1>
            <p class="section-subtitle mb-0">Lectura poblacional basada en las personas registradas, con filtros y calculo de edad desde la fecha de nacimiento.</p>
        </div>
        <span class="badge text-bg-light border">Filtros activos: <?= e((string) $filterCount); ?></span>
    </div>
</section>

<?php if ($loadWarning !== ''): ?>
    <div class="alert alert-warning mb-4"><?= e($loadWarning); ?></div>
<?php endif; ?>

<div class="card-clean p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1">Filtros de consulta</h2>
            <p class="small text-muted mb-0">Puede cruzar genero, tipo de poblacion, rol y rango de edad sin guardar la edad en la base de datos.</p>
        </div>
        <a href="<?= e(page_url('analisis')); ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Limpiar filtros</a>
    </div>

    <form method="get" class="row g-3 align-items-end">
        <input type="hidden" name="page" value="analisis">

        <div class="col-md-6 col-xl-3">
            <label for="genero" class="form-label">Genero</label>
            <select id="genero" name="genero" class="form-select">
                <option value="">Todos</option>
                <?php foreach ($genderOptions as $option): ?>
                    <option value="<?= e($option); ?>" <?= $filters['genero'] === $option ? 'selected' : ''; ?>><?= e($option); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6 col-xl-3">
            <label for="tipo_poblacion_id" class="form-label">Tipo de poblacion</label>
            <select id="tipo_poblacion_id" name="tipo_poblacion_id" class="form-select">
                <option value="">Todos</option>
                <?php foreach ($tiposPoblacion as $tipo): ?>
                    <option value="<?= e((string) $tipo['id']); ?>" <?= (int) $filters['tipo_poblacion_id'] === (int) $tipo['id'] ? 'selected' : ''; ?>>
                        <?= e((string) $tipo['nombre']); ?><?= (int) $tipo['activo'] === 0 ? ' (Inactivo)' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6 col-xl-2">
            <label for="es_testigo" class="form-label">Testigo</label>
            <select id="es_testigo" name="es_testigo" class="form-select">
                <option value="">Todos</option>
                <option value="1" <?= $filters['es_testigo'] === '1' ? 'selected' : ''; ?>>Si</option>
                <option value="0" <?= $filters['es_testigo'] === '0' ? 'selected' : ''; ?>>No</option>
            </select>
        </div>

        <div class="col-md-6 col-xl-2">
            <label for="es_jurado" class="form-label">Jurado</label>
            <select id="es_jurado" name="es_jurado" class="form-select">
                <option value="">Todos</option>
                <option value="1" <?= $filters['es_jurado'] === '1' ? 'selected' : ''; ?>>Si</option>
                <option value="0" <?= $filters['es_jurado'] === '0' ? 'selected' : ''; ?>>No</option>
            </select>
        </div>

        <div class="col-md-6 col-xl-2">
            <label for="rango_edad" class="form-label">Rango de edad</label>
            <select id="rango_edad" name="rango_edad" class="form-select">
                <option value="">Todos</option>
                <?php foreach ($analisisService->getAgeRangeOptions() as $option): ?>
                    <option value="<?= e((string) $option['key']); ?>" <?= $filters['rango_edad'] === (string) $option['key'] ? 'selected' : ''; ?>>
                        <?= e((string) $option['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>Aplicar filtros</button>
            <a href="<?= e(page_url('analisis')); ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-eraser me-1"></i>Reiniciar</a>
        </div>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3 col-xxl-2">
        <article class="summary-card p-3 h-100">
            <small class="text-muted">Total personas</small>
            <div class="summary-value mt-2"><?= e((string) $summary['total_personas']); ?></div>
        </article>
    </div>
    <div class="col-6 col-lg-3 col-xxl-2">
        <article class="summary-card p-3 h-100">
            <small class="text-muted">Total testigos</small>
            <div class="summary-value mt-2"><?= e((string) $summary['total_testigos']); ?></div>
        </article>
    </div>
    <div class="col-6 col-lg-3 col-xxl-2">
        <article class="summary-card p-3 h-100">
            <small class="text-muted">Total jurados</small>
            <div class="summary-value mt-2"><?= e((string) $summary['total_jurados']); ?></div>
        </article>
    </div>
    <div class="col-6 col-lg-3 col-xxl-3">
        <article class="summary-card p-3 h-100">
            <small class="text-muted">Con fecha nacimiento</small>
            <div class="summary-value mt-2"><?= e((string) $summary['total_con_fecha_nacimiento']); ?></div>
        </article>
    </div>
    <div class="col-6 col-lg-3 col-xxl-3">
        <article class="summary-card p-3 h-100">
            <small class="text-muted">Sin fecha nacimiento</small>
            <div class="summary-value mt-2"><?= e((string) $summary['total_sin_fecha_nacimiento']); ?></div>
        </article>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card-clean p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 mb-0">Genero</h2>
                <small class="text-muted">Distribucion</small>
            </div>
            <div class="chart-wrap chart-wrap-donut">
                <canvas
                    id="chartAnalisisGenero"
                    class="chart-canvas"
                    data-chart-type="donut"
                    data-empty-text="Sin datos de genero."
                    data-labels='<?= e($genderLabelsJson); ?>'
                    data-values='<?= e($genderValuesJson); ?>'
                ></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-clean p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 mb-0">Tipo de poblacion</h2>
                <small class="text-muted">Totales</small>
            </div>
            <div class="chart-wrap">
                <canvas
                    id="chartAnalisisPoblacion"
                    class="chart-canvas"
                    data-chart-type="bar"
                    data-empty-text="Sin datos de tipo de poblacion."
                    data-labels='<?= e($populationLabelsJson); ?>'
                    data-values='<?= e($populationValuesJson); ?>'
                ></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-clean p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 mb-0">Rangos de edad</h2>
                <small class="text-muted">Desde fecha de nacimiento</small>
            </div>
            <div class="chart-wrap">
                <canvas
                    id="chartAnalisisEdad"
                    class="chart-canvas"
                    data-chart-type="bar"
                    data-empty-text="Sin datos de edad."
                    data-labels='<?= e($ageLabelsJson); ?>'
                    data-values='<?= e($ageValuesJson); ?>'
                ></canvas>
            </div>
        </div>
    </div>
</div>

<?php if ($totalPersons === 0): ?>
    <div class="empty-state-card mb-4">
        <i class="fa-solid fa-chart-column"></i>
        <span>No hay personas que coincidan con los filtros actuales. Ajuste la consulta o registre nuevas personas para ver indicadores.</span>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="card-clean p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Totales por genero</h2>
                <span class="badge text-bg-light border"><?= e((string) count($genderRows)); ?> grupos</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle analysis-table mb-0">
                    <thead>
                        <tr>
                            <th>Genero</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($genderRows === []): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No hay registros para este filtro.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($genderRows as $row): ?>
                                <tr>
                                    <td><?= e((string) $row['etiqueta']); ?></td>
                                    <td class="text-end fw-semibold"><?= e((string) $row['total']); ?></td>
                                    <td class="text-end text-muted"><?= e($formatPercent((int) $row['total'], $totalPersons)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card-clean p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Totales por tipo de poblacion</h2>
                <span class="badge text-bg-light border"><?= e((string) count($populationRows)); ?> grupos</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle analysis-table mb-0">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($populationRows === []): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No hay registros para este filtro.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($populationRows as $row): ?>
                                <tr>
                                    <td><?= e((string) $row['etiqueta']); ?></td>
                                    <td class="text-end fw-semibold"><?= e((string) $row['total']); ?></td>
                                    <td class="text-end text-muted"><?= e($formatPercent((int) $row['total'], $totalPersons)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card-clean p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h5 mb-0">Distribucion por edad</h2>
                    <small class="text-muted">La edad se calcula con la fecha actual del servidor.</small>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle analysis-table mb-0">
                    <thead>
                        <tr>
                            <th>Rango</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($ageRows === []): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No hay registros para este filtro.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ageRows as $row): ?>
                                <tr>
                                    <td><?= e((string) $row['label']); ?></td>
                                    <td class="text-end fw-semibold"><?= e((string) $row['total']); ?></td>
                                    <td class="text-end text-muted"><?= e($formatPercent((int) $row['total'], $totalPersons)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
