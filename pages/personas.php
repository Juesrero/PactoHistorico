<?php
declare(strict_types=1);

$personaService = new PersonaService(db());
$personaImportService = new PersonaImportService($personaService);

$search = request_string($_GET, 'q', 120);
$action = request_string($_GET, 'action', 20);
$requestedId = request_int($_GET, 'id', 0);

$allowedSorts = ['id', 'nombres_apellidos', 'numero_documento', 'celular', 'es_testigo'];
$sortBy = request_string($_GET, 'sort', 30);
if (!in_array($sortBy, $allowedSorts, true)) {
    $sortBy = 'id';
}

$sortDir = strtolower(request_string($_GET, 'dir', 4));
if (!in_array($sortDir, ['asc', 'desc'], true)) {
    $sortDir = 'desc';
}

$currentListPage = max(1, request_int($_GET, 'p', 1));
$perPage = 12;

$formMode = 'create';
$formErrors = [];
$importReport = null;
$formData = [
    'id' => 0,
    'nombres_apellidos' => '',
    'numero_documento' => '',
    'celular' => '',
    'es_testigo' => 0,
];

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('message', 'Token CSRF invalido. Intente nuevamente.', 'danger');
        redirect_to('personas');
    }

    $postAction = request_string($_POST, 'form_action', 40);
    $returnQ = request_string($_POST, 'return_q', 120);

    if ($postAction === 'create_persona') {
        [$clean, $formErrors] = Validator::validatePersona($_POST);

        if ($personaService->documentExists($clean['numero_documento'])) {
            $formErrors['numero_documento'] = 'El numero de documento ya existe.';
        }

        if ($formErrors === []) {
            try {
                $personaService->create($clean);
                flash('message', 'Persona registrada correctamente.', 'success');
                redirect_to_url(page_url_with_query('personas', ['q' => $returnQ, 'sort' => $sortBy, 'dir' => $sortDir, 'p' => $currentListPage]));
            } catch (Throwable $exception) {
                $formErrors['general'] = 'No fue posible registrar la persona.';
            }
        }

        $formMode = 'create';
        $formData = array_merge($formData, $clean);
    }

    if ($postAction === 'update_persona') {
        $editId = request_int($_POST, 'id', 0);
        [$clean, $formErrors] = Validator::validatePersona($_POST);

        if ($editId <= 0) {
            $formErrors['general'] = 'ID de persona invalido.';
        }

        if ($personaService->documentExists($clean['numero_documento'], $editId)) {
            $formErrors['numero_documento'] = 'El numero de documento ya existe.';
        }

        if ($formErrors === []) {
            try {
                $personaService->update($editId, $clean);
                flash('message', 'Persona actualizada correctamente.', 'success');
                redirect_to_url(page_url_with_query('personas', ['q' => $returnQ, 'sort' => $sortBy, 'dir' => $sortDir, 'p' => $currentListPage]));
            } catch (Throwable $exception) {
                $formErrors['general'] = 'No fue posible actualizar la persona.';
            }
        }

        $formMode = 'edit';
        $formData = array_merge($formData, $clean, ['id' => $editId]);
    }

    if ($postAction === 'delete_persona') {
        $deleteId = request_int($_POST, 'id', 0);

        if ($deleteId <= 0) {
            flash('message', 'ID de persona invalido.', 'danger');
        } else {
            try {
                $deleted = $personaService->delete($deleteId);
                if ($deleted) {
                    flash('message', 'Persona eliminada correctamente.', 'success');
                } else {
                    flash('message', 'La persona no existe o ya fue eliminada.', 'warning');
                }
            } catch (Throwable $exception) {
                flash('message', 'No fue posible eliminar la persona.', 'danger');
            }
        }

        redirect_to_url(page_url_with_query('personas', ['q' => $returnQ, 'sort' => $sortBy, 'dir' => $sortDir, 'p' => $currentListPage]));
    }

    if ($postAction === 'import_personas_excel') {
        try {
            $importReport = $personaImportService->importFromUploadedFile($_FILES['archivo_excel'] ?? []);
        } catch (Throwable $exception) {
            $importReport = [
                'processed' => 0,
                'inserted' => 0,
                'skipped' => 0,
                'errors' => [[
                    'row' => 0,
                    'message' => $exception->getMessage(),
                ]],
            ];
        }
    }
}

if ($action === 'edit' && $requestedId > 0 && $formMode !== 'edit') {
    $persona = $personaService->findById($requestedId);

    if ($persona === null) {
        flash('message', 'La persona solicitada no existe.', 'warning');
        redirect_to_url(page_url_with_query('personas', ['q' => $search, 'sort' => $sortBy, 'dir' => $sortDir, 'p' => $currentListPage]));
    }

    $formMode = 'edit';
    $formData = [
        'id' => (int) $persona['id'],
        'nombres_apellidos' => (string) $persona['nombres_apellidos'],
        'numero_documento' => (string) $persona['numero_documento'],
        'celular' => (string) $persona['celular'],
        'es_testigo' => (int) $persona['es_testigo'],
    ];
}

$totalRecords = $personaService->count($search);
$totalPages = max(1, (int) ceil($totalRecords / $perPage));
if ($currentListPage > $totalPages) {
    $currentListPage = $totalPages;
}

$offset = ($currentListPage - 1) * $perPage;
$personas = $personaService->listPaginated($search, $sortBy, $sortDir, $perPage, $offset);
$clearUrl = page_url('personas');
$maxImportErrorsToShow = 12;
$firstRecord = $totalRecords > 0 ? ($offset + 1) : 0;
$lastRecord = $totalRecords > 0 ? ($offset + count($personas)) : 0;

$sortLink = static function (string $column, string $direction, string $search): string {
    return page_url_with_query('personas', [
        'q' => $search,
        'sort' => $column,
        'dir' => $direction,
        'p' => 1,
    ]);
};

$paginationLink = static function (int $page, string $search, string $sortBy, string $sortDir): string {
    return page_url_with_query('personas', [
        'q' => $search,
        'sort' => $sortBy,
        'dir' => $sortDir,
        'p' => $page,
    ]);
};
?>

<section class="row g-3">
    <div class="col-lg-4">
        <div class="card-clean p-4 mb-3">
            <h1 class="h5 mb-1"><?= $formMode === 'edit' ? 'Editar persona' : 'Registrar persona'; ?></h1>
            <p class="text-muted small mb-3">Complete los datos y guarde los cambios.</p>

            <?php if (isset($formErrors['general'])): ?>
                <div class="alert alert-danger"><?= e($formErrors['general']); ?></div>
            <?php endif; ?>

            <form method="post" class="needs-validation" novalidate>
                <?= csrf_field(); ?>
                <input type="hidden" name="form_action" value="<?= $formMode === 'edit' ? 'update_persona' : 'create_persona'; ?>">
                <input type="hidden" name="return_q" value="<?= e($search); ?>">
                <?php if ($formMode === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= e((string) $formData['id']); ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label" for="nombres_apellidos"><i class="fa-solid fa-id-card me-1 text-muted"></i>Nombre y apellido</label>
                    <input
                        type="text"
                        class="form-control <?= isset($formErrors['nombres_apellidos']) ? 'is-invalid' : ''; ?>"
                        id="nombres_apellidos"
                        name="nombres_apellidos"
                        minlength="3"
                        maxlength="120"
                        required
                        value="<?= e((string) $formData['nombres_apellidos']); ?>"
                    >
                    <div class="invalid-feedback"><?= e($formErrors['nombres_apellidos'] ?? 'Ingrese un nombre valido.'); ?></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="numero_documento"><i class="fa-solid fa-address-card me-1 text-muted"></i>Numero documento</label>
                    <input
                        type="text"
                        class="form-control <?= isset($formErrors['numero_documento']) ? 'is-invalid' : ''; ?>"
                        id="numero_documento"
                        name="numero_documento"
                        pattern="[0-9]{5,20}"
                        required
                        value="<?= e((string) $formData['numero_documento']); ?>"
                    >
                    <div class="invalid-feedback"><?= e($formErrors['numero_documento'] ?? 'Use solo numeros (5 a 20 digitos).'); ?></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="celular"><i class="fa-solid fa-phone me-1 text-muted"></i>Celular</label>
                    <input
                        type="text"
                        class="form-control <?= isset($formErrors['celular']) ? 'is-invalid' : ''; ?>"
                        id="celular"
                        name="celular"
                        pattern="[0-9]{7,20}"
                        required
                        value="<?= e((string) $formData['celular']); ?>"
                    >
                    <div class="invalid-feedback"><?= e($formErrors['celular'] ?? 'Use solo numeros (7 a 20 digitos).'); ?></div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="es_testigo" name="es_testigo" value="1" <?= (int) $formData['es_testigo'] === 1 ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="es_testigo"><i class="fa-solid fa-user-check me-1 text-muted"></i>Testigo</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-floppy-disk me-1"></i><?= $formMode === 'edit' ? 'Actualizar' : 'Guardar'; ?></button>
                    <?php if ($formMode === 'edit'): ?>
                        <a href="<?= e(page_url_with_query('personas', ['q' => $search, 'sort' => $sortBy, 'dir' => $sortDir, 'p' => $currentListPage])); ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i>Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card-clean p-4">
            <h2 class="h5 mb-1">Importar clientes desde Excel</h2>
            <p class="text-muted small mb-3">Cargue un archivo <strong>.xlsx</strong> para registrar personas en bloque.</p>

            <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                <?= csrf_field(); ?>
                <input type="hidden" name="form_action" value="import_personas_excel">

                <div class="mb-2">
                    <label class="form-label" for="archivo_excel"><i class="fa-solid fa-file-excel me-1 text-success"></i>Archivo Excel</label>
                    <input
                        type="file"
                        class="form-control"
                        id="archivo_excel"
                        name="archivo_excel"
                        accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        required
                    >
                    <div class="invalid-feedback">Seleccione un archivo Excel .xlsx.</div>
                </div>

                <p class="small text-muted mb-3">
                    Columnas requeridas: <code>nombres_apellidos</code>, <code>numero_documento</code>, <code>celular</code>.
                    Columna opcional: <code>es_testigo</code> (1/0, si/no, verdadero/falso).
                </p>

                <button type="submit" class="btn btn-outline-primary w-100"><i class="fa-solid fa-upload me-1"></i>Importar Excel</button>
            </form>

            <?php if ($importReport !== null): ?>
                <?php
                    $hasErrors = $importReport['errors'] !== [];
                    $alertClass = !$hasErrors ? 'success' : ((int) $importReport['inserted'] > 0 ? 'warning' : 'danger');
                    $extraErrors = max(0, count($importReport['errors']) - $maxImportErrorsToShow);
                ?>
                <div class="alert alert-<?= e($alertClass); ?> mt-3 mb-0">
                    <div><strong><i class="fa-solid fa-clipboard-check me-1"></i>Resultado de importacion:</strong></div>
                    <div>Filas procesadas: <?= e((string) $importReport['processed']); ?></div>
                    <div>Registros creados: <?= e((string) $importReport['inserted']); ?></div>
                    <div>Filas omitidas: <?= e((string) $importReport['skipped']); ?></div>

                    <?php if ($hasErrors): ?>
                        <hr>
                        <div class="small fw-semibold mb-1">Errores detectados:</div>
                        <ul class="small mb-0 ps-3">
                            <?php foreach (array_slice($importReport['errors'], 0, $maxImportErrorsToShow) as $error): ?>
                                <li>
                                    <?= (int) $error['row'] > 0 ? 'Fila ' . e((string) $error['row']) . ': ' : ''; ?>
                                    <?= e((string) $error['message']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($extraErrors > 0): ?>
                            <div class="small mt-2">Y <?= e((string) $extraErrors); ?> errores mas.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card-clean p-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                <h2 class="h5 mb-0">Listado de personas</h2>
                <form method="get" class="d-flex gap-2 w-100 flex-wrap flex-md-nowrap">
                    <input type="hidden" name="page" value="personas">
                    <input type="hidden" name="sort" value="<?= e($sortBy); ?>">
                    <input type="hidden" name="dir" value="<?= e($sortDir); ?>">
                    <input type="text" name="q" class="form-control" placeholder="Buscar por nombre, documento o celular" value="<?= e($search); ?>">
                    <button type="submit" class="btn btn-outline-primary"><i class="fa-solid fa-magnifying-glass me-1"></i>Buscar</button>
                    <a href="<?= e($clearUrl); ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-eraser me-1"></i>Limpiar</a>
                </form>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 small text-muted">
                <span><i class="fa-solid fa-filter me-1"></i>Orden actual: <strong><?= e($sortBy); ?></strong> (<?= e(strtoupper($sortDir)); ?>)</span>
                <span><i class="fa-solid fa-list-ol me-1"></i>Mostrando <?= e((string) $firstRecord); ?> - <?= e((string) $lastRecord); ?> de <?= e((string) $totalRecords); ?></span>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>
                                <div class="sort-header">
                                    <span>#</span>
                                    <span class="sort-controls">
                                        <a class="sort-link <?= $sortBy === 'id' && $sortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($sortLink('id', 'asc', $search)); ?>" title="Orden ascendente"><i class="fa-solid fa-sort-up"></i></a>
                                        <a class="sort-link <?= $sortBy === 'id' && $sortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($sortLink('id', 'desc', $search)); ?>" title="Orden descendente"><i class="fa-solid fa-sort-down"></i></a>
                                    </span>
                                </div>
                            </th>
                            <th>
                                <div class="sort-header">
                                    <span>Nombre</span>
                                    <span class="sort-controls">
                                        <a class="sort-link <?= $sortBy === 'nombres_apellidos' && $sortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($sortLink('nombres_apellidos', 'asc', $search)); ?>" title="Orden ascendente"><i class="fa-solid fa-sort-up"></i></a>
                                        <a class="sort-link <?= $sortBy === 'nombres_apellidos' && $sortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($sortLink('nombres_apellidos', 'desc', $search)); ?>" title="Orden descendente"><i class="fa-solid fa-sort-down"></i></a>
                                    </span>
                                </div>
                            </th>
                            <th>
                                <div class="sort-header">
                                    <span>Documento</span>
                                    <span class="sort-controls">
                                        <a class="sort-link <?= $sortBy === 'numero_documento' && $sortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($sortLink('numero_documento', 'asc', $search)); ?>" title="Orden ascendente"><i class="fa-solid fa-sort-up"></i></a>
                                        <a class="sort-link <?= $sortBy === 'numero_documento' && $sortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($sortLink('numero_documento', 'desc', $search)); ?>" title="Orden descendente"><i class="fa-solid fa-sort-down"></i></a>
                                    </span>
                                </div>
                            </th>
                            <th>
                                <div class="sort-header">
                                    <span>Celular</span>
                                    <span class="sort-controls">
                                        <a class="sort-link <?= $sortBy === 'celular' && $sortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($sortLink('celular', 'asc', $search)); ?>" title="Orden ascendente"><i class="fa-solid fa-sort-up"></i></a>
                                        <a class="sort-link <?= $sortBy === 'celular' && $sortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($sortLink('celular', 'desc', $search)); ?>" title="Orden descendente"><i class="fa-solid fa-sort-down"></i></a>
                                    </span>
                                </div>
                            </th>
                            <th>
                                <div class="sort-header">
                                    <span>Testigo</span>
                                    <span class="sort-controls">
                                        <a class="sort-link <?= $sortBy === 'es_testigo' && $sortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($sortLink('es_testigo', 'asc', $search)); ?>" title="Orden ascendente"><i class="fa-solid fa-sort-up"></i></a>
                                        <a class="sort-link <?= $sortBy === 'es_testigo' && $sortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($sortLink('es_testigo', 'desc', $search)); ?>" title="Orden descendente"><i class="fa-solid fa-sort-down"></i></a>
                                    </span>
                                </div>
                            </th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($personas === []): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No hay personas para mostrar.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($personas as $persona): ?>
                                <tr>
                                    <td><?= e((string) $persona['id']); ?></td>
                                    <td><?= e((string) $persona['nombres_apellidos']); ?></td>
                                    <td><?= e((string) $persona['numero_documento']); ?></td>
                                    <td><?= e((string) $persona['celular']); ?></td>
                                    <td>
                                        <span class="badge <?= (int) $persona['es_testigo'] === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                            <?= (int) $persona['es_testigo'] === 1 ? 'Si' : 'No'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end actions-col">
                                        <a href="<?= e(page_url_with_query('personas', ['action' => 'edit', 'id' => (int) $persona['id'], 'q' => $search, 'sort' => $sortBy, 'dir' => $sortDir, 'p' => $currentListPage])); ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen-to-square me-1"></i>Editar</a>

                                        <form method="post" class="d-inline" data-confirm="Confirma eliminar esta persona? Esta accion no se puede deshacer.">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="form_action" value="delete_persona">
                                            <input type="hidden" name="id" value="<?= e((string) $persona['id']); ?>">
                                            <input type="hidden" name="return_q" value="<?= e($search); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash-can me-1"></i>Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                <div class="small text-muted">Pagina <?= e((string) $currentListPage); ?> de <?= e((string) $totalPages); ?></div>
                <div class="d-flex gap-2">
                    <?php if ($currentListPage > 1): ?>
                        <a href="<?= e($paginationLink($currentListPage - 1, $search, $sortBy, $sortDir)); ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Anterior</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled><i class="fa-solid fa-arrow-left me-1"></i>Anterior</button>
                    <?php endif; ?>

                    <?php if ($currentListPage < $totalPages): ?>
                        <a href="<?= e($paginationLink($currentListPage + 1, $search, $sortBy, $sortDir)); ?>" class="btn btn-sm btn-outline-secondary">Siguiente<i class="fa-solid fa-arrow-right ms-1"></i></a>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Siguiente<i class="fa-solid fa-arrow-right ms-1"></i></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>




