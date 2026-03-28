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
$ageRangeOptions = $analisisService->getAgeRangeOptions();
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
$genderPalette = ['#1c3587', '#ff1a1f', '#ff9c00'];
$populationPalette = ['#1c3587', '#3557c2', '#ff9c00', '#ffbd59', '#00a93f', '#49c96f', '#a52e94', '#c86fba', '#ff1a1f', '#ff6b70'];
$agePalette = ['#1c3587', '#00a93f', '#ff9c00', '#ff1a1f', '#a52e94'];
$genderPaletteJson = $jsonEncode($genderPalette);
$populationPaletteJson = $jsonEncode($populationPalette);
$agePaletteJson = $jsonEncode($agePalette);
$topPopulation = $populationRows[0] ?? null;
$topAgeRange = $ageChartRows[0] ?? null;
$tablePerPage = 5;

$genderSortBy = request_string($_GET, 'gender_sort', 20);
$genderSortDir = strtolower(request_string($_GET, 'gender_dir', 4));
$populationSortBy = request_string($_GET, 'population_sort', 20);
$populationSortDir = strtolower(request_string($_GET, 'population_dir', 4));
$ageSortBy = request_string($_GET, 'age_sort', 20);
$ageSortDir = strtolower(request_string($_GET, 'age_dir', 4));
$genderPage = max(1, request_int($_GET, 'gender_p', 1));
$populationPage = max(1, request_int($_GET, 'population_p', 1));
$agePage = max(1, request_int($_GET, 'age_p', 1));

$normalizeSort = static function (string $sortBy, string $sortDir): array {
    $allowed = ['label', 'total', 'percent'];
    if (!in_array($sortBy, $allowed, true)) {
        $sortBy = 'total';
    }
    if (!in_array($sortDir, ['asc', 'desc'], true)) {
        $sortDir = 'desc';
    }
    return [$sortBy, $sortDir];
};

[$genderSortBy, $genderSortDir] = $normalizeSort($genderSortBy, $genderSortDir);
[$populationSortBy, $populationSortDir] = $normalizeSort($populationSortBy, $populationSortDir);
[$ageSortBy, $ageSortDir] = $normalizeSort($ageSortBy, $ageSortDir);

$filterCount = 0;
$activeFilterBadges = [];
foreach ($filters as $key => $value) {
    if (in_array($key, ['es_testigo', 'es_jurado'], true)) {
        if ($value === '0' || $value === '1') {
            $filterCount++;
            $activeFilterBadges[] = ($key === 'es_testigo' ? 'Testigo' : 'Jurado') . ': ' . ($value === '1' ? 'Si' : 'No');
        }
        continue;
    }

    if ($key === 'tipo_poblacion_id') {
        if ((int) $value > 0) {
            $filterCount++;
            foreach ($tiposPoblacion as $tipo) {
                if ((int) $tipo['id'] === (int) $value) {
                    $activeFilterBadges[] = 'Tipo: ' . (string) $tipo['nombre'];
                    break;
                }
            }
        }
        continue;
    }

    if ((string) $value !== '') {
        $filterCount++;
        if ($key === 'genero') {
            $activeFilterBadges[] = 'Genero: ' . (string) $value;
        }
        if ($key === 'rango_edad') {
            foreach ($ageRangeOptions as $option) {
                if ((string) $option['key'] === (string) $value) {
                    $activeFilterBadges[] = 'Edad: ' . (string) $option['label'];
                    break;
                }
            }
        }
    }
}

$totalConFechaNacimiento = (int) ($summary['total_con_fecha_nacimiento'] ?? 0);
$coverageNacimiento = $totalPersons > 0
    ? (int) round(($totalConFechaNacimiento / $totalPersons) * 100)
    : 0;
$testigoPercent = $totalPersons > 0
    ? (int) round((((int) $summary['total_testigos']) / $totalPersons) * 100)
    : 0;
$juradoPercent = $totalPersons > 0
    ? (int) round((((int) $summary['total_jurados']) / $totalPersons) * 100)
    : 0;

$sortAnalysisRows = static function (array $rows, string $sortBy, string $sortDir, callable $percentResolver): array {
    usort($rows, static function (array $left, array $right) use ($sortBy, $sortDir, $percentResolver): int {
        $comparison = 0;

        if ($sortBy === 'label') {
            $comparison = strcasecmp((string) ($left['label'] ?? $left['etiqueta'] ?? ''), (string) ($right['label'] ?? $right['etiqueta'] ?? ''));
        } elseif ($sortBy === 'percent') {
            $comparison = $percentResolver($left) <=> $percentResolver($right);
        } else {
            $comparison = ((int) ($left['total'] ?? 0)) <=> ((int) ($right['total'] ?? 0));
        }

        if ($comparison === 0) {
            $comparison = strcasecmp((string) ($left['label'] ?? $left['etiqueta'] ?? ''), (string) ($right['label'] ?? $right['etiqueta'] ?? ''));
        }

        return $sortDir === 'desc' ? -$comparison : $comparison;
    });

    return $rows;
};

$paginateRows = static function (array $rows, int $page, int $perPage): array {
    $total = count($rows);
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    return [
        'rows' => array_slice($rows, $offset, $perPage),
        'page' => $page,
        'total' => $total,
        'total_pages' => $totalPages,
        'first' => $total > 0 ? $offset + 1 : 0,
        'last' => $total > 0 ? $offset + count(array_slice($rows, $offset, $perPage)) : 0,
    ];
};

$genderRowsSorted = $sortAnalysisRows($genderRows, $genderSortBy, $genderSortDir, static fn (array $row): float => $totalPersons > 0 ? ((int) $row['total'] / $totalPersons) : 0.0);
$populationRowsSorted = $sortAnalysisRows($populationRows, $populationSortBy, $populationSortDir, static fn (array $row): float => $totalPersons > 0 ? ((int) $row['total'] / $totalPersons) : 0.0);
$ageRowsSorted = $sortAnalysisRows($ageRows, $ageSortBy, $ageSortDir, static fn (array $row): float => $totalPersons > 0 ? ((int) $row['total'] / $totalPersons) : 0.0);

$genderTable = $paginateRows($genderRowsSorted, $genderPage, $tablePerPage);
$populationTable = $paginateRows($populationRowsSorted, $populationPage, $tablePerPage);
$ageTable = $paginateRows($ageRowsSorted, $agePage, $tablePerPage);

$analysisUrl = static function (array $filters, array $tableState): string {
    return page_url_with_query('analisis', array_merge(['page' => 'analisis'], $filters, $tableState));
};
?>

<section class="analysis-hero card-clean mb-4">
    <div class="row g-4 align-items-stretch">
        <div class="col-xl-8">
            <div class="analysis-hero-copy h-100">
                <span class="dashboard-kicker">Analitica poblacional</span>
                <h1 class="dashboard-title">Analisis sociodemografico</h1>
                <p class="dashboard-subtitle">Interpreta la composicion del registro con una lectura compacta de genero, edad, tipo de poblacion y roles del proceso. La edad se calcula dinamicamente desde la fecha de nacimiento.</p>

                <div class="analysis-active-filters">
                    <span class="analysis-filter-counter">Filtros activos: <?= e((string) $filterCount); ?></span>
                    <?php if ($activeFilterBadges === []): ?>
                        <span class="analysis-filter-pill is-neutral">Sin filtros aplicados</span>
                    <?php else: ?>
                        <?php foreach ($activeFilterBadges as $badge): ?>
                            <span class="analysis-filter-pill"><?= e($badge); ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <aside class="analysis-hero-aside h-100">
                <div class="analysis-aside-block">
                    <small>Dato predominante</small>
                    <strong>
                        <?= $topPopulation ? e((string) $topPopulation['etiqueta']) : 'Sin categoria predominante'; ?>
                    </strong>
                    <span>
                        <?= $topPopulation ? e((string) $topPopulation['total']) . ' personas registradas en este grupo.' : 'Aun no hay datos suficientes para establecer una tendencia.'; ?>
                    </span>
                </div>
                <div class="analysis-aside-block">
                    <small>Base con fecha de nacimiento</small>
                    <strong><?= e((string) $coverageNacimiento); ?>%</strong>
                    <span><?= e((string) $summary['total_con_fecha_nacimiento']); ?> de <?= e((string) $totalPersons); ?> registros permiten calcular edad.</span>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php if ($loadWarning !== ''): ?>
    <div class="alert alert-warning mb-4"><?= e($loadWarning); ?></div>
<?php endif; ?>

<div class="card-clean analysis-filter-panel mb-4">
    <div class="analysis-filter-head">
        <div>
            <h2 class="analysis-panel-title">Filtros de consulta</h2>
            <p class="analysis-panel-subtitle">Cruza variables sin alterar la base y ajusta la lectura del tablero en tiempo real.</p>
        </div>
        <a href="<?= e(page_url('analisis')); ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Limpiar filtros</a>
    </div>

    <form method="get" class="row g-3 align-items-end compact-floating-form">
        <input type="hidden" name="page" value="analisis">

        <div class="col-md-6 col-xl-3">
            <div class="form-floating">
                <select id="genero" name="genero" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($genderOptions as $option): ?>
                        <option value="<?= e($option); ?>" <?= $filters['genero'] === $option ? 'selected' : ''; ?>><?= e($option); ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="genero">Genero</label>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="form-floating">
                <select id="tipo_poblacion_id" name="tipo_poblacion_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($tiposPoblacion as $tipo): ?>
                        <option value="<?= e((string) $tipo['id']); ?>" <?= (int) $filters['tipo_poblacion_id'] === (int) $tipo['id'] ? 'selected' : ''; ?>>
                            <?= e((string) $tipo['nombre']); ?><?= (int) $tipo['activo'] === 0 ? ' (Inactivo)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="tipo_poblacion_id">Tipo de poblacion</label>
            </div>
        </div>

        <div class="col-md-6 col-xl-2">
            <div class="form-floating">
                <select id="es_testigo" name="es_testigo" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" <?= $filters['es_testigo'] === '1' ? 'selected' : ''; ?>>Si</option>
                    <option value="0" <?= $filters['es_testigo'] === '0' ? 'selected' : ''; ?>>No</option>
                </select>
                <label for="es_testigo">Testigo</label>
            </div>
        </div>

        <div class="col-md-6 col-xl-2">
            <div class="form-floating">
                <select id="es_jurado" name="es_jurado" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" <?= $filters['es_jurado'] === '1' ? 'selected' : ''; ?>>Si</option>
                    <option value="0" <?= $filters['es_jurado'] === '0' ? 'selected' : ''; ?>>No</option>
                </select>
                <label for="es_jurado">Jurado</label>
            </div>
        </div>

        <div class="col-md-6 col-xl-2">
            <div class="form-floating">
                <select id="rango_edad" name="rango_edad" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($ageRangeOptions as $option): ?>
                        <option value="<?= e((string) $option['key']); ?>" <?= $filters['rango_edad'] === (string) $option['key'] ? 'selected' : ''; ?>>
                            <?= e((string) $option['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="rango_edad">Rango de edad</label>
            </div>
        </div>

        <div class="col-12 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>Aplicar filtros</button>
            <a href="<?= e(page_url('analisis')); ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-eraser me-1"></i>Reiniciar</a>
        </div>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3 col-xxl-2">
        <article class="analysis-stat-card">
            <small>Total personas</small>
            <div class="analysis-stat-value"><?= e((string) $summary['total_personas']); ?></div>
            <p>Base total sobre la que se calculan los indicadores.</p>
        </article>
    </div>
    <div class="col-6 col-lg-3 col-xxl-2">
        <article class="analysis-stat-card">
            <small>Total testigos</small>
            <div class="analysis-stat-value"><?= e((string) $summary['total_testigos']); ?></div>
            <p><?= e((string) $testigoPercent); ?>% del universo filtrado.</p>
        </article>
    </div>
    <div class="col-6 col-lg-3 col-xxl-2">
        <article class="analysis-stat-card">
            <small>Total jurados</small>
            <div class="analysis-stat-value"><?= e((string) $summary['total_jurados']); ?></div>
            <p><?= e((string) $juradoPercent); ?>% del universo filtrado.</p>
        </article>
    </div>
    <div class="col-6 col-lg-3 col-xxl-3">
        <article class="analysis-stat-card">
            <small>Con fecha de nacimiento</small>
            <div class="analysis-stat-value"><?= e((string) $summary['total_con_fecha_nacimiento']); ?></div>
            <p>Permiten calcular rangos etarios automaticamente.</p>
        </article>
    </div>
    <div class="col-6 col-lg-3 col-xxl-3">
        <article class="analysis-stat-card">
            <small>Sin fecha de nacimiento</small>
            <div class="analysis-stat-value"><?= e((string) $summary['total_sin_fecha_nacimiento']); ?></div>
            <p>Registros pendientes por completar para mejorar el analisis.</p>
        </article>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-5">
        <div class="card-clean analysis-chart-card h-100">
            <div class="analysis-chart-head">
                <div>
                    <h2 class="analysis-panel-title">Genero</h2>
                    <p class="analysis-panel-subtitle">Distribucion general del registro filtrado.</p>
                </div>
                <span class="analysis-panel-badge">Distribucion</span>
            </div>
            <div class="chart-wrap chart-wrap-donut">
                <canvas
                    id="chartAnalisisGenero"
                    class="chart-canvas"
                    data-chart-type="donut"
                    data-empty-text="Sin datos de genero."
                    data-chart-palette='<?= e($genderPaletteJson); ?>'
                    data-labels='<?= e($genderLabelsJson); ?>'
                    data-values='<?= e($genderValuesJson); ?>'
                ></canvas>
            </div>
            <div class="analysis-donut-legend mt-3">
                <?php if ($genderRows === []): ?>
                    <div class="small text-muted">No hay categorias de genero para mostrar con los filtros actuales.</div>
                <?php else: ?>
                    <?php foreach ($genderRows as $index => $row): ?>
                        <div class="analysis-donut-legend-item">
                            <span class="analysis-donut-legend-color" style="background: <?= e($genderPalette[$index % count($genderPalette)]); ?>;"></span>
                            <div class="analysis-donut-legend-copy">
                                <div class="analysis-donut-legend-label"><?= e((string) $row['etiqueta']); ?></div>
                                <div class="analysis-donut-legend-meta">
                                    <?= e((string) $row['total']); ?> personas
                                    <span class="text-muted">|</span>
                                    <?= e($formatPercent((int) $row['total'], $totalPersons)); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card-clean analysis-chart-card h-100">
            <div class="analysis-chart-head">
                <div>
                    <h2 class="analysis-panel-title">Tipo de poblacion</h2>
                    <p class="analysis-panel-subtitle">Comparativo por categoria para identificar concentraciones del registro.</p>
                </div>
                <span class="analysis-panel-badge">Totales</span>
            </div>
            <div class="chart-wrap chart-wrap-tall">
                <canvas
                    id="chartAnalisisPoblacion"
                    class="chart-canvas"
                    data-chart-type="bar-horizontal"
                    data-empty-text="Sin datos de tipo de poblacion."
                    data-chart-palette='<?= e($populationPaletteJson); ?>'
                    data-labels='<?= e($populationLabelsJson); ?>'
                    data-values='<?= e($populationValuesJson); ?>'
                ></canvas>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card-clean analysis-chart-card">
            <div class="analysis-chart-head">
                <div>
                    <h2 class="analysis-panel-title">Rangos de edad</h2>
                    <p class="analysis-panel-subtitle">Segmentacion etaria calculada desde la fecha actual del servidor.</p>
                </div>
                <span class="analysis-panel-badge">
                    <?= $topAgeRange ? e((string) $topAgeRange['label']) . ' lidera' : 'Sin datos'; ?>
                </span>
            </div>
            <div class="chart-wrap">
                <canvas
                    id="chartAnalisisEdad"
                    class="chart-canvas"
                    data-chart-type="bar"
                    data-empty-text="Sin datos de edad."
                    data-chart-palette='<?= e($agePaletteJson); ?>'
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
        <div class="card-clean analysis-table-card h-100">
            <div class="analysis-table-head">
                <h2 class="analysis-panel-title">Totales por genero</h2>
                <span class="analysis-panel-badge"><?= e((string) $genderTable['total']); ?> grupos</span>
            </div>
            <div class="module-toolbar">
                <div class="module-toolbar-meta">
                    <span>Mostrando <?= e((string) $genderTable['first']); ?> - <?= e((string) $genderTable['last']); ?></span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle analysis-table module-table mb-0">
                    <thead>
                        <tr>
                            <th><div class="sort-header"><span>Genero</span><span class="sort-controls"><a class="sort-link <?= $genderSortBy === 'label' && $genderSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => 'label', 'gender_dir' => 'asc', 'gender_p' => 1, 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-genero"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $genderSortBy === 'label' && $genderSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => 'label', 'gender_dir' => 'desc', 'gender_p' => 1, 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-genero"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th class="text-end"><div class="sort-header justify-content-end"><span>Total</span><span class="sort-controls"><a class="sort-link <?= $genderSortBy === 'total' && $genderSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => 'total', 'gender_dir' => 'asc', 'gender_p' => 1, 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-genero"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $genderSortBy === 'total' && $genderSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => 'total', 'gender_dir' => 'desc', 'gender_p' => 1, 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-genero"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th class="text-end"><div class="sort-header justify-content-end"><span>%</span><span class="sort-controls"><a class="sort-link <?= $genderSortBy === 'percent' && $genderSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => 'percent', 'gender_dir' => 'asc', 'gender_p' => 1, 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-genero"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $genderSortBy === 'percent' && $genderSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => 'percent', 'gender_dir' => 'desc', 'gender_p' => 1, 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-genero"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                        </tr>
                    </thead>
                    <tbody id="tabla-genero">
                        <?php if ($genderTable['rows'] === []): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No hay registros para este filtro.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($genderTable['rows'] as $row): ?>
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
            <div class="module-pagination">
                <div class="module-pagination-status">Pagina <?= e((string) $genderTable['page']); ?> de <?= e((string) $genderTable['total_pages']); ?></div>
                <div class="module-pagination-controls">
                    <?php if ($genderTable['page'] > 1): ?>
                        <a href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'] - 1, 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-genero" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Anterior</a>
                    <?php else: ?><span class="btn btn-sm btn-outline-secondary disabled"><i class="fa-solid fa-arrow-left me-1"></i>Anterior</span><?php endif; ?>
                    <span class="module-pagination-index"><?= e((string) $genderTable['page']); ?>/<?= e((string) $genderTable['total_pages']); ?></span>
                    <?php if ($genderTable['page'] < $genderTable['total_pages']): ?>
                        <a href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'] + 1, 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-genero" class="btn btn-sm btn-outline-secondary">Siguiente<i class="fa-solid fa-arrow-right ms-1"></i></a>
                    <?php else: ?><span class="btn btn-sm btn-outline-secondary disabled">Siguiente<i class="fa-solid fa-arrow-right ms-1"></i></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card-clean analysis-table-card h-100">
            <div class="analysis-table-head">
                <h2 class="analysis-panel-title">Totales por tipo de poblacion</h2>
                <span class="analysis-panel-badge"><?= e((string) $populationTable['total']); ?> grupos</span>
            </div>
            <div class="module-toolbar">
                <div class="module-toolbar-meta">
                    <span>Mostrando <?= e((string) $populationTable['first']); ?> - <?= e((string) $populationTable['last']); ?></span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle analysis-table module-table mb-0">
                    <thead>
                        <tr>
                            <th><div class="sort-header"><span>Tipo</span><span class="sort-controls"><a class="sort-link <?= $populationSortBy === 'label' && $populationSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => 'label', 'population_dir' => 'asc', 'population_p' => 1, 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-poblacion"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $populationSortBy === 'label' && $populationSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => 'label', 'population_dir' => 'desc', 'population_p' => 1, 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-poblacion"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th class="text-end"><div class="sort-header justify-content-end"><span>Total</span><span class="sort-controls"><a class="sort-link <?= $populationSortBy === 'total' && $populationSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => 'total', 'population_dir' => 'asc', 'population_p' => 1, 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-poblacion"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $populationSortBy === 'total' && $populationSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => 'total', 'population_dir' => 'desc', 'population_p' => 1, 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-poblacion"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th class="text-end"><div class="sort-header justify-content-end"><span>%</span><span class="sort-controls"><a class="sort-link <?= $populationSortBy === 'percent' && $populationSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => 'percent', 'population_dir' => 'asc', 'population_p' => 1, 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-poblacion"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $populationSortBy === 'percent' && $populationSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => 'percent', 'population_dir' => 'desc', 'population_p' => 1, 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-poblacion"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                        </tr>
                    </thead>
                    <tbody id="tabla-poblacion">
                        <?php if ($populationTable['rows'] === []): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No hay registros para este filtro.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($populationTable['rows'] as $row): ?>
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
            <div class="module-pagination">
                <div class="module-pagination-status">Pagina <?= e((string) $populationTable['page']); ?> de <?= e((string) $populationTable['total_pages']); ?></div>
                <div class="module-pagination-controls">
                    <?php if ($populationTable['page'] > 1): ?>
                        <a href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'] - 1, 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-poblacion" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Anterior</a>
                    <?php else: ?><span class="btn btn-sm btn-outline-secondary disabled"><i class="fa-solid fa-arrow-left me-1"></i>Anterior</span><?php endif; ?>
                    <span class="module-pagination-index"><?= e((string) $populationTable['page']); ?>/<?= e((string) $populationTable['total_pages']); ?></span>
                    <?php if ($populationTable['page'] < $populationTable['total_pages']): ?>
                        <a href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'] + 1, 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page']])); ?>#tabla-poblacion" class="btn btn-sm btn-outline-secondary">Siguiente<i class="fa-solid fa-arrow-right ms-1"></i></a>
                    <?php else: ?><span class="btn btn-sm btn-outline-secondary disabled">Siguiente<i class="fa-solid fa-arrow-right ms-1"></i></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card-clean analysis-table-card h-100">
            <div class="analysis-table-head">
                <div>
                    <h2 class="analysis-panel-title">Distribucion por edad</h2>
                    <p class="analysis-panel-subtitle mb-0">La edad se calcula con la fecha actual del servidor.</p>
                </div>
            </div>
            <div class="module-toolbar">
                <div class="module-toolbar-meta">
                    <span>Mostrando <?= e((string) $ageTable['first']); ?> - <?= e((string) $ageTable['last']); ?></span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle analysis-table module-table mb-0">
                    <thead>
                        <tr>
                            <th><div class="sort-header"><span>Rango</span><span class="sort-controls"><a class="sort-link <?= $ageSortBy === 'label' && $ageSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => 'label', 'age_dir' => 'asc', 'age_p' => 1])); ?>#tabla-edad"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $ageSortBy === 'label' && $ageSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => 'label', 'age_dir' => 'desc', 'age_p' => 1])); ?>#tabla-edad"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th class="text-end"><div class="sort-header justify-content-end"><span>Total</span><span class="sort-controls"><a class="sort-link <?= $ageSortBy === 'total' && $ageSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => 'total', 'age_dir' => 'asc', 'age_p' => 1])); ?>#tabla-edad"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $ageSortBy === 'total' && $ageSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => 'total', 'age_dir' => 'desc', 'age_p' => 1])); ?>#tabla-edad"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th class="text-end"><div class="sort-header justify-content-end"><span>%</span><span class="sort-controls"><a class="sort-link <?= $ageSortBy === 'percent' && $ageSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => 'percent', 'age_dir' => 'asc', 'age_p' => 1])); ?>#tabla-edad"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $ageSortBy === 'percent' && $ageSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => 'percent', 'age_dir' => 'desc', 'age_p' => 1])); ?>#tabla-edad"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                        </tr>
                    </thead>
                    <tbody id="tabla-edad">
                        <?php if ($ageTable['rows'] === []): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No hay registros para este filtro.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ageTable['rows'] as $row): ?>
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
            <div class="module-pagination">
                <div class="module-pagination-status">Pagina <?= e((string) $ageTable['page']); ?> de <?= e((string) $ageTable['total_pages']); ?></div>
                <div class="module-pagination-controls">
                    <?php if ($ageTable['page'] > 1): ?>
                        <a href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page'] - 1])); ?>#tabla-edad" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Anterior</a>
                    <?php else: ?><span class="btn btn-sm btn-outline-secondary disabled"><i class="fa-solid fa-arrow-left me-1"></i>Anterior</span><?php endif; ?>
                    <span class="module-pagination-index"><?= e((string) $ageTable['page']); ?>/<?= e((string) $ageTable['total_pages']); ?></span>
                    <?php if ($ageTable['page'] < $ageTable['total_pages']): ?>
                        <a href="<?= e($analysisUrl($filters, ['gender_sort' => $genderSortBy, 'gender_dir' => $genderSortDir, 'gender_p' => $genderTable['page'], 'population_sort' => $populationSortBy, 'population_dir' => $populationSortDir, 'population_p' => $populationTable['page'], 'age_sort' => $ageSortBy, 'age_dir' => $ageSortDir, 'age_p' => $ageTable['page'] + 1])); ?>#tabla-edad" class="btn btn-sm btn-outline-secondary">Siguiente<i class="fa-solid fa-arrow-right ms-1"></i></a>
                    <?php else: ?><span class="btn btn-sm btn-outline-secondary disabled">Siguiente<i class="fa-solid fa-arrow-right ms-1"></i></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
