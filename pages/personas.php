<?php
declare(strict_types=1);

$personaService = new PersonaService(db());
$tipoPoblacionService = new TipoPoblacionService(db());
$personaImportService = new PersonaImportService($personaService, null, $tipoPoblacionService);

$search = request_string($_GET, 'q', 120);
$action = request_string($_GET, 'action', 20);
$requestedId = request_int($_GET, 'id', 0);
$typeAction = request_string($_GET, 'type_action', 20);
$requestedTypeId = request_int($_GET, 'type_id', 0);

$allowedSorts = ['id', 'nombres', 'apellidos', 'numero_documento', 'celular', 'correo', 'es_testigo', 'es_jurado', 'tipo_poblacion'];
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
    'numero_documento' => '',
    'nombres' => '',
    'apellidos' => '',
    'genero' => '',
    'fecha_nacimiento' => '',
    'correo' => '',
    'celular' => '',
    'direccion' => '',
    'tipo_poblacion_id' => 0,
    'es_testigo' => 0,
    'es_jurado' => 0,
];

$typeFormMode = 'create';
$typeFormErrors = [];
$typeFormData = [
    'id' => 0,
    'nombre' => '',
    'descripcion' => '',
    'activo' => 1,
];

$maxImportErrorsToShow = 12;
$genderOptions = ['', 'Femenino', 'Masculino', 'No binario', 'Otro', 'Prefiero no decir'];

$splitLegacyFullName = static function (string $fullName): array {
    $fullName = trim($fullName);
    if ($fullName === '') {
        return ['', ''];
    }

    $parts = preg_split('/\s+/', $fullName) ?: [];
    if (count($parts) <= 1) {
        return [$fullName, ''];
    }

    $lastName = (string) array_pop($parts);
    $names = trim(implode(' ', $parts));

    return [$names, $lastName];
};

$buildListUrl = static function (string $search, string $sortBy, string $sortDir, int $page): string {
    return page_url_with_query('personas', [
        'q' => $search,
        'sort' => $sortBy,
        'dir' => $sortDir,
        'p' => $page,
    ]);
};

$withTypesAnchor = static function (string $url): string {
    return $url . '#tipos-poblacion';
};

$validateTipoPoblacion = static function (array $input): array {
    $errors = [];
    $nombre = trim((string) ($input['nombre'] ?? ''));
    $descripcion = trim((string) ($input['descripcion'] ?? ''));
    $activo = isset($input['activo']) ? 1 : 0;

    if ($nombre === '') {
        $errors['nombre'] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 120) {
        $errors['nombre'] = 'El nombre debe tener entre 2 y 120 caracteres.';
    }

    if ($descripcion !== '' && mb_strlen($descripcion) > 255) {
        $errors['descripcion'] = 'La descripcion no puede superar 255 caracteres.';
    }

    return [[
        'nombre' => $nombre,
        'descripcion' => $descripcion !== '' ? $descripcion : null,
        'activo' => $activo,
    ], $errors];
};

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('message', 'Token CSRF invalido. Intente nuevamente.', 'danger');
        redirect_to('personas');
    }

    $postAction = request_string($_POST, 'form_action', 40);
    $returnQ = request_string($_POST, 'return_q', 120);
    $returnSort = request_string($_POST, 'return_sort', 30);
    if (!in_array($returnSort, $allowedSorts, true)) {
        $returnSort = $sortBy;
    }

    $returnDir = strtolower(request_string($_POST, 'return_dir', 4));
    if (!in_array($returnDir, ['asc', 'desc'], true)) {
        $returnDir = $sortDir;
    }

    $returnPage = max(1, request_int($_POST, 'return_p', $currentListPage));
    $returnUrl = $buildListUrl($returnQ, $returnSort, $returnDir, $returnPage);

    if ($postAction === 'create_persona') {
        [$clean, $formErrors] = Validator::validatePersona($_POST);

        if (($clean['tipo_poblacion_id'] ?? null) !== null && $tipoPoblacionService->findById((int) $clean['tipo_poblacion_id']) === null) {
            $formErrors['tipo_poblacion_id'] = 'Seleccione un tipo de poblacion valido.';
        }

        if ($personaService->documentExists($clean['numero_documento'])) {
            $formErrors['numero_documento'] = 'La identificacion ya existe.';
        }

        if ($formErrors === []) {
            try {
                $personaService->create($clean);
                flash('message', 'Persona registrada correctamente.', 'success');
                redirect_to_url($returnUrl);
            } catch (Throwable $exception) {
                $formErrors['general'] = 'No fue posible registrar la persona.';
            }
        }

        $formMode = 'create';
        $formData = array_merge($formData, $clean, ['id' => 0]);
    }

    if ($postAction === 'update_persona') {
        $editId = request_int($_POST, 'id', 0);
        [$clean, $formErrors] = Validator::validatePersona($_POST);

        if ($editId <= 0) {
            $formErrors['general'] = 'ID de persona invalido.';
        }

        if (($clean['tipo_poblacion_id'] ?? null) !== null && $tipoPoblacionService->findById((int) $clean['tipo_poblacion_id']) === null) {
            $formErrors['tipo_poblacion_id'] = 'Seleccione un tipo de poblacion valido.';
        }

        if ($personaService->documentExists($clean['numero_documento'], $editId)) {
            $formErrors['numero_documento'] = 'La identificacion ya existe.';
        }

        if ($formErrors === []) {
            try {
                $personaService->update($editId, $clean);
                flash('message', 'Persona actualizada correctamente.', 'success');
                redirect_to_url($returnUrl);
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

        redirect_to_url($returnUrl);
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

    if ($postAction === 'create_tipo_poblacion') {
        [$cleanType, $typeFormErrors] = $validateTipoPoblacion($_POST);

        if ($tipoPoblacionService->existsName($cleanType['nombre'])) {
            $typeFormErrors['nombre'] = 'Ya existe un tipo de poblacion con ese nombre.';
        }

        if ($typeFormErrors === []) {
            try {
                $tipoPoblacionService->create($cleanType);
                flash('message', 'Tipo de poblacion creado correctamente.', 'success');
                redirect_to_url($withTypesAnchor($returnUrl));
            } catch (Throwable $exception) {
                $typeFormErrors['general'] = 'No fue posible crear el tipo de poblacion.';
            }
        }

        $typeFormMode = 'create';
        $typeFormData = array_merge($typeFormData, $cleanType, ['id' => 0]);
    }

    if ($postAction === 'update_tipo_poblacion') {
        $typeId = request_int($_POST, 'type_id', 0);
        [$cleanType, $typeFormErrors] = $validateTipoPoblacion($_POST);

        if ($typeId <= 0) {
            $typeFormErrors['general'] = 'ID de tipo de poblacion invalido.';
        }

        if ($tipoPoblacionService->existsName($cleanType['nombre'], $typeId)) {
            $typeFormErrors['nombre'] = 'Ya existe un tipo de poblacion con ese nombre.';
        }

        if ($typeFormErrors === []) {
            try {
                $tipoPoblacionService->update($typeId, $cleanType);
                flash('message', 'Tipo de poblacion actualizado correctamente.', 'success');
                redirect_to_url($withTypesAnchor($returnUrl));
            } catch (Throwable $exception) {
                $typeFormErrors['general'] = 'No fue posible actualizar el tipo de poblacion.';
            }
        }

        $typeFormMode = 'edit';
        $typeFormData = array_merge($typeFormData, $cleanType, ['id' => $typeId]);
    }

    if ($postAction === 'toggle_tipo_poblacion') {
        $typeId = request_int($_POST, 'type_id', 0);
        $activeTarget = request_int($_POST, 'activo', -1);

        if ($typeId <= 0 || !in_array($activeTarget, [0, 1], true)) {
            flash('message', 'Parametros invalidos para actualizar el tipo de poblacion.', 'danger');
        } else {
            try {
                $updated = $tipoPoblacionService->setActive($typeId, $activeTarget === 1);
                if ($updated) {
                    flash('message', $activeTarget === 1 ? 'Tipo de poblacion activado.' : 'Tipo de poblacion inactivado.', 'success');
                } else {
                    flash('message', 'El tipo de poblacion ya estaba en ese estado o no existe.', 'warning');
                }
            } catch (Throwable $exception) {
                flash('message', 'No fue posible actualizar el estado del tipo de poblacion.', 'danger');
            }
        }

        redirect_to_url($withTypesAnchor($returnUrl));
    }
}

if ($action === 'edit' && $requestedId > 0 && $formMode !== 'edit') {
    $persona = $personaService->findById($requestedId);

    if ($persona === null) {
        flash('message', 'La persona solicitada no existe.', 'warning');
        redirect_to_url($buildListUrl($search, $sortBy, $sortDir, $currentListPage));
    }

    $nombres = trim((string) ($persona['nombres'] ?? ''));
    $apellidos = trim((string) ($persona['apellidos'] ?? ''));
    if ($nombres === '' && $apellidos === '') {
        [$nombres, $apellidos] = $splitLegacyFullName((string) $persona['nombres_apellidos']);
    }

    $formMode = 'edit';
    $formData = [
        'id' => (int) $persona['id'],
        'numero_documento' => (string) $persona['numero_documento'],
        'nombres' => $nombres,
        'apellidos' => $apellidos,
        'genero' => (string) ($persona['genero'] ?? ''),
        'fecha_nacimiento' => (string) ($persona['fecha_nacimiento'] ?? ''),
        'correo' => (string) ($persona['correo'] ?? ''),
        'celular' => (string) ($persona['celular'] ?? ''),
        'direccion' => (string) ($persona['direccion'] ?? ''),
        'tipo_poblacion_id' => (int) ($persona['tipo_poblacion_id'] ?? 0),
        'es_testigo' => (int) $persona['es_testigo'],
        'es_jurado' => (int) ($persona['es_jurado'] ?? 0),
    ];
}

if ($typeAction === 'edit' && $requestedTypeId > 0 && $typeFormMode !== 'edit') {
    $tipoPoblacion = $tipoPoblacionService->findById($requestedTypeId);

    if ($tipoPoblacion === null) {
        flash('message', 'El tipo de poblacion solicitado no existe.', 'warning');
        redirect_to_url($withTypesAnchor($buildListUrl($search, $sortBy, $sortDir, $currentListPage)));
    }

    $typeFormMode = 'edit';
    $typeFormData = [
        'id' => (int) $tipoPoblacion['id'],
        'nombre' => (string) $tipoPoblacion['nombre'],
        'descripcion' => (string) ($tipoPoblacion['descripcion'] ?? ''),
        'activo' => (int) $tipoPoblacion['activo'],
    ];
}

$totalRecords = $personaService->count($search);
$totalPages = max(1, (int) ceil($totalRecords / $perPage));
if ($currentListPage > $totalPages) {
    $currentListPage = $totalPages;
}

$offset = ($currentListPage - 1) * $perPage;
$personas = $personaService->listPaginated($search, $sortBy, $sortDir, $perPage, $offset);
$tiposPoblacion = $tipoPoblacionService->listAll();
$clearUrl = page_url('personas');
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
    <div class="col-xl-4">
        <div class="card-clean p-4 mb-3">
            <h1 class="h5 mb-1"><?= $formMode === 'edit' ? 'Editar persona' : 'Registrar persona'; ?></h1>
            <p class="text-muted small mb-3">Complete la informacion principal sin afectar la compatibilidad del sistema actual.</p>

            <?php if (isset($formErrors['general'])): ?>
                <div class="alert alert-danger"><?= e($formErrors['general']); ?></div>
            <?php endif; ?>

            <form method="post" class="needs-validation" novalidate>
                <?= csrf_field(); ?>
                <input type="hidden" name="form_action" value="<?= $formMode === 'edit' ? 'update_persona' : 'create_persona'; ?>">
                <input type="hidden" name="return_q" value="<?= e($search); ?>">
                <input type="hidden" name="return_sort" value="<?= e($sortBy); ?>">
                <input type="hidden" name="return_dir" value="<?= e($sortDir); ?>">
                <input type="hidden" name="return_p" value="<?= e((string) $currentListPage); ?>">
                <?php if ($formMode === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= e((string) $formData['id']); ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="numero_documento"><i class="fa-solid fa-address-card me-1 text-muted"></i>Identificacion</label>
                        <input
                            type="text"
                            class="form-control <?= isset($formErrors['numero_documento']) ? 'is-invalid' : ''; ?>"
                            id="numero_documento"
                            name="numero_documento"
                            maxlength="20"
                            pattern="[A-Za-z0-9\-]{5,20}"
                            required
                            value="<?= e((string) $formData['numero_documento']); ?>"
                        >
                        <div class="invalid-feedback"><?= e($formErrors['numero_documento'] ?? 'Ingrese una identificacion valida.'); ?></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="genero"><i class="fa-solid fa-venus-mars me-1 text-muted"></i>Genero</label>
                        <select class="form-select <?= isset($formErrors['genero']) ? 'is-invalid' : ''; ?>" id="genero" name="genero">
                            <?php foreach ($genderOptions as $option): ?>
                                <option value="<?= e($option); ?>" <?= (string) $formData['genero'] === $option ? 'selected' : ''; ?>>
                                    <?= e($option !== '' ? $option : 'Seleccione'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?= e($formErrors['genero'] ?? 'Seleccione un genero valido.'); ?></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="nombres"><i class="fa-solid fa-user me-1 text-muted"></i>Nombres</label>
                        <input type="text" class="form-control <?= isset($formErrors['nombres']) ? 'is-invalid' : ''; ?>" id="nombres" name="nombres" minlength="2" maxlength="60" required value="<?= e((string) $formData['nombres']); ?>">
                        <div class="invalid-feedback"><?= e($formErrors['nombres'] ?? 'Ingrese los nombres.'); ?></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="apellidos"><i class="fa-solid fa-user-tag me-1 text-muted"></i>Apellidos</label>
                        <input type="text" class="form-control <?= isset($formErrors['apellidos']) ? 'is-invalid' : ''; ?>" id="apellidos" name="apellidos" minlength="2" maxlength="60" required value="<?= e((string) $formData['apellidos']); ?>">
                        <div class="invalid-feedback"><?= e($formErrors['apellidos'] ?? 'Ingrese los apellidos.'); ?></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="fecha_nacimiento"><i class="fa-solid fa-cake-candles me-1 text-muted"></i>Fecha de nacimiento</label>
                        <input type="date" class="form-control <?= isset($formErrors['fecha_nacimiento']) ? 'is-invalid' : ''; ?>" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= e((string) $formData['fecha_nacimiento']); ?>">
                        <div class="invalid-feedback"><?= e($formErrors['fecha_nacimiento'] ?? 'Ingrese una fecha valida.'); ?></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="correo"><i class="fa-solid fa-envelope me-1 text-muted"></i>Correo</label>
                        <input type="email" class="form-control <?= isset($formErrors['correo']) ? 'is-invalid' : ''; ?>" id="correo" name="correo" maxlength="120" value="<?= e((string) $formData['correo']); ?>">
                        <div class="invalid-feedback"><?= e($formErrors['correo'] ?? 'Ingrese un correo valido.'); ?></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="celular"><i class="fa-solid fa-phone me-1 text-muted"></i>Telefono</label>
                        <input type="text" class="form-control <?= isset($formErrors['celular']) ? 'is-invalid' : ''; ?>" id="celular" name="celular" maxlength="20" pattern="[0-9+\-\s]{7,20}" required value="<?= e((string) $formData['celular']); ?>">
                        <div class="invalid-feedback"><?= e($formErrors['celular'] ?? 'Ingrese un telefono valido.'); ?></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="tipo_poblacion_id"><i class="fa-solid fa-people-group me-1 text-muted"></i>Tipo de poblacion</label>
                        <select class="form-select <?= isset($formErrors['tipo_poblacion_id']) ? 'is-invalid' : ''; ?>" id="tipo_poblacion_id" name="tipo_poblacion_id">
                            <option value="">Seleccione</option>
                            <?php foreach ($tiposPoblacion as $tipo): ?>
                                <?php $isSelected = (int) $formData['tipo_poblacion_id'] === (int) $tipo['id']; $isInactive = (int) $tipo['activo'] !== 1; $isDisabled = $isInactive && !$isSelected; ?>
                                <option value="<?= e((string) $tipo['id']); ?>" <?= $isSelected ? 'selected' : ''; ?> <?= $isDisabled ? 'disabled' : ''; ?>>
                                    <?= e((string) $tipo['nombre']); ?><?= $isInactive ? ' (Inactivo)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?= e($formErrors['tipo_poblacion_id'] ?? 'Seleccione un tipo de poblacion valido.'); ?></div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="direccion"><i class="fa-solid fa-location-dot me-1 text-muted"></i>Direccion de domicilio</label>
                        <textarea class="form-control <?= isset($formErrors['direccion']) ? 'is-invalid' : ''; ?>" id="direccion" name="direccion" rows="2" maxlength="255"><?= e((string) $formData['direccion']); ?></textarea>
                        <div class="invalid-feedback"><?= e($formErrors['direccion'] ?? 'Revise la direccion.'); ?></div>
                    </div>

                    <div class="col-sm-6">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" id="es_testigo" name="es_testigo" value="1" <?= (int) $formData['es_testigo'] === 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="es_testigo"><i class="fa-solid fa-user-check me-1 text-muted"></i>Testigo</label>
                        </div>
                    </div>

                    <div class="col-sm-6">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" id="es_jurado" name="es_jurado" value="1" <?= (int) $formData['es_jurado'] === 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="es_jurado"><i class="fa-solid fa-scale-balanced me-1 text-muted"></i>Jurado</label>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-floppy-disk me-1"></i><?= $formMode === 'edit' ? 'Actualizar' : 'Guardar'; ?></button>
                    <?php if ($formMode === 'edit'): ?>
                        <a href="<?= e($buildListUrl($search, $sortBy, $sortDir, $currentListPage)); ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i>Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card-clean p-4 mb-3">
            <h2 class="h5 mb-1">Importar personas desde Excel</h2>
            <p class="text-muted small mb-3">Compatible con plantillas nuevas y con el formato historico de nombre completo.</p>

            <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                <?= csrf_field(); ?>
                <input type="hidden" name="form_action" value="import_personas_excel">

                <div class="mb-2">
                    <label class="form-label" for="archivo_excel"><i class="fa-solid fa-file-excel me-1 text-success"></i>Archivo Excel</label>
                    <input type="file" class="form-control" id="archivo_excel" name="archivo_excel" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                    <div class="invalid-feedback">Seleccione un archivo Excel .xlsx.</div>
                </div>

                <p class="small text-muted mb-3">
                    Requerido: <code>identificacion</code>/<code>numero_documento</code>, <code>telefono</code>/<code>celular</code> y
                    <code>nombres</code> + <code>apellidos</code> o <code>nombres_apellidos</code>.
                    Opcionales: <code>genero</code>, <code>fecha_nacimiento</code>, <code>correo</code>, <code>direccion</code>, <code>tipo_poblacion</code>, <code>es_testigo</code>, <code>es_jurado</code>.
                </p>

                <button type="submit" class="btn btn-outline-primary w-100"><i class="fa-solid fa-upload me-1"></i>Importar Excel</button>
            </form>

            <?php if ($importReport !== null): ?>
                <?php $hasErrors = $importReport['errors'] !== []; $alertClass = !$hasErrors ? 'success' : ((int) $importReport['inserted'] > 0 ? 'warning' : 'danger'); $extraErrors = max(0, count($importReport['errors']) - $maxImportErrorsToShow); ?>
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
                                <li><?= (int) $error['row'] > 0 ? 'Fila ' . e((string) $error['row']) . ': ' : ''; ?><?= e((string) $error['message']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($extraErrors > 0): ?>
                            <div class="small mt-2">Y <?= e((string) $extraErrors); ?> errores mas.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="tipos-poblacion" class="card-clean p-4">
            <h2 class="h5 mb-1"><?= $typeFormMode === 'edit' ? 'Editar tipo de poblacion' : 'Tipos de poblacion'; ?></h2>
            <p class="text-muted small mb-3">Catalogo configurable para clasificar personas y habilitar analisis posteriores.</p>

            <?php if (isset($typeFormErrors['general'])): ?>
                <div class="alert alert-danger"><?= e($typeFormErrors['general']); ?></div>
            <?php endif; ?>

            <form method="post" class="needs-validation" novalidate>
                <?= csrf_field(); ?>
                <input type="hidden" name="form_action" value="<?= $typeFormMode === 'edit' ? 'update_tipo_poblacion' : 'create_tipo_poblacion'; ?>">
                <input type="hidden" name="return_q" value="<?= e($search); ?>">
                <input type="hidden" name="return_sort" value="<?= e($sortBy); ?>">
                <input type="hidden" name="return_dir" value="<?= e($sortDir); ?>">
                <input type="hidden" name="return_p" value="<?= e((string) $currentListPage); ?>">
                <?php if ($typeFormMode === 'edit'): ?>
                    <input type="hidden" name="type_id" value="<?= e((string) $typeFormData['id']); ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label" for="tipo_nombre"><i class="fa-solid fa-tags me-1 text-muted"></i>Nombre</label>
                    <input type="text" class="form-control <?= isset($typeFormErrors['nombre']) ? 'is-invalid' : ''; ?>" id="tipo_nombre" name="nombre" minlength="2" maxlength="120" required value="<?= e((string) $typeFormData['nombre']); ?>">
                    <div class="invalid-feedback"><?= e($typeFormErrors['nombre'] ?? 'Ingrese un nombre valido.'); ?></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="tipo_descripcion"><i class="fa-solid fa-align-left me-1 text-muted"></i>Descripcion</label>
                    <textarea class="form-control <?= isset($typeFormErrors['descripcion']) ? 'is-invalid' : ''; ?>" id="tipo_descripcion" name="descripcion" rows="2" maxlength="255"><?= e((string) $typeFormData['descripcion']); ?></textarea>
                    <div class="invalid-feedback"><?= e($typeFormErrors['descripcion'] ?? 'Revise la descripcion.'); ?></div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="tipo_activo" name="activo" value="1" <?= (int) $typeFormData['activo'] === 1 ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="tipo_activo">Activo</label>
                </div>

                <div class="d-flex gap-2 mb-3">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="fa-solid fa-floppy-disk me-1"></i><?= $typeFormMode === 'edit' ? 'Actualizar tipo' : 'Crear tipo'; ?></button>
                    <?php if ($typeFormMode === 'edit'): ?>
                        <a href="<?= e($withTypesAnchor($buildListUrl($search, $sortBy, $sortDir, $currentListPage))); ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i>Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>Personas</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($tiposPoblacion === []): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Aun no hay tipos de poblacion registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tiposPoblacion as $tipo): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) $tipo['nombre']); ?></div>
                                        <?php if (!empty($tipo['descripcion'])): ?><div class="small text-muted"><?= e((string) $tipo['descripcion']); ?></div><?php endif; ?>
                                    </td>
                                    <td><span class="badge <?= (int) $tipo['activo'] === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= (int) $tipo['activo'] === 1 ? 'Activo' : 'Inactivo'; ?></span></td>
                                    <td><?= e((string) $tipo['total_personas']); ?></td>
                                    <td class="text-end actions-col">
                                        <a href="<?= e($withTypesAnchor(page_url_with_query('personas', ['type_action' => 'edit', 'type_id' => (int) $tipo['id'], 'q' => $search, 'sort' => $sortBy, 'dir' => $sortDir, 'p' => $currentListPage]))); ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen-to-square me-1"></i>Editar</a>
                                        <form method="post" class="d-inline" data-confirm="<?= (int) $tipo['activo'] === 1 ? 'Confirma inactivar este tipo de poblacion?' : 'Confirma activar este tipo de poblacion?'; ?>">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="form_action" value="toggle_tipo_poblacion">
                                            <input type="hidden" name="type_id" value="<?= e((string) $tipo['id']); ?>">
                                            <input type="hidden" name="activo" value="<?= (int) $tipo['activo'] === 1 ? '0' : '1'; ?>">
                                            <input type="hidden" name="return_q" value="<?= e($search); ?>">
                                            <input type="hidden" name="return_sort" value="<?= e($sortBy); ?>">
                                            <input type="hidden" name="return_dir" value="<?= e($sortDir); ?>">
                                            <input type="hidden" name="return_p" value="<?= e((string) $currentListPage); ?>">
                                            <button type="submit" class="btn btn-sm <?= (int) $tipo['activo'] === 1 ? 'btn-outline-secondary' : 'btn-outline-success'; ?>"><i class="fa-solid <?= (int) $tipo['activo'] === 1 ? 'fa-eye-slash' : 'fa-eye'; ?> me-1"></i><?= (int) $tipo['activo'] === 1 ? 'Inactivar' : 'Activar'; ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card-clean p-4">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Listado de personas</h2>
                    <p class="text-muted small mb-0">Busqueda ampliada por identificacion, nombres, apellidos, telefono, correo y tipo de poblacion.</p>
                </div>
                <form method="get" class="d-flex gap-2 w-100 flex-wrap flex-lg-nowrap">
                    <input type="hidden" name="page" value="personas">
                    <input type="hidden" name="sort" value="<?= e($sortBy); ?>">
                    <input type="hidden" name="dir" value="<?= e($sortDir); ?>">
                    <input type="text" name="q" class="form-control" placeholder="Buscar por identificacion, nombre, correo, telefono o poblacion" value="<?= e($search); ?>">
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
                            <th><div class="sort-header"><span>#</span><span class="sort-controls"><a class="sort-link <?= $sortBy === 'id' && $sortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($sortLink('id', 'asc', $search)); ?>" title="Orden ascendente"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $sortBy === 'id' && $sortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($sortLink('id', 'desc', $search)); ?>" title="Orden descendente"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th><div class="sort-header"><span>Nombres</span><span class="sort-controls"><a class="sort-link <?= $sortBy === 'nombres' && $sortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($sortLink('nombres', 'asc', $search)); ?>" title="Orden ascendente"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $sortBy === 'nombres' && $sortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($sortLink('nombres', 'desc', $search)); ?>" title="Orden descendente"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th><div class="sort-header"><span>Apellidos</span><span class="sort-controls"><a class="sort-link <?= $sortBy === 'apellidos' && $sortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($sortLink('apellidos', 'asc', $search)); ?>" title="Orden ascendente"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $sortBy === 'apellidos' && $sortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($sortLink('apellidos', 'desc', $search)); ?>" title="Orden descendente"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th><div class="sort-header"><span>Identificacion</span><span class="sort-controls"><a class="sort-link <?= $sortBy === 'numero_documento' && $sortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($sortLink('numero_documento', 'asc', $search)); ?>" title="Orden ascendente"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $sortBy === 'numero_documento' && $sortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($sortLink('numero_documento', 'desc', $search)); ?>" title="Orden descendente"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th>Contacto</th>
                            <th><div class="sort-header"><span>Poblacion</span><span class="sort-controls"><a class="sort-link <?= $sortBy === 'tipo_poblacion' && $sortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($sortLink('tipo_poblacion', 'asc', $search)); ?>" title="Orden ascendente"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $sortBy === 'tipo_poblacion' && $sortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($sortLink('tipo_poblacion', 'desc', $search)); ?>" title="Orden descendente"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th>Roles</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>                        <?php if ($personas === []): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No hay personas para mostrar.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($personas as $persona): ?>
                                <?php
                                    $displayNames = trim((string) ($persona['nombres'] ?? ''));
                                    $displayLastNames = trim((string) ($persona['apellidos'] ?? ''));
                                    if ($displayNames === '' && $displayLastNames === '') {
                                        [$displayNames, $displayLastNames] = $splitLegacyFullName((string) $persona['nombres_apellidos']);
                                    }
                                ?>
                                <tr>
                                    <td><?= e((string) $persona['id']); ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= e($displayNames !== '' ? $displayNames : (string) $persona['nombres_apellidos']); ?></div>
                                        <?php if (!empty($persona['genero']) || !empty($persona['fecha_nacimiento'])): ?>
                                            <div class="small text-muted">
                                                <?= e((string) ($persona['genero'] ?? '')); ?>
                                                <?php if (!empty($persona['genero']) && !empty($persona['fecha_nacimiento'])): ?> | <?php endif; ?>
                                                <?= e((string) ($persona['fecha_nacimiento'] ?? '')); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= e($displayLastNames); ?></div>
                                        <?php if ($displayLastNames === '' && !empty($persona['nombres_apellidos'])): ?>
                                            <div class="small text-muted">Dato historico</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e((string) $persona['numero_documento']); ?></td>
                                    <td>
                                        <div><?= e((string) $persona['celular']); ?></div>
                                        <?php if (!empty($persona['correo'])): ?>
                                            <div class="small text-muted text-truncate-table"><?= e((string) $persona['correo']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($persona['tipo_poblacion_nombre'])): ?>
                                            <span class="badge <?= (int) ($persona['tipo_poblacion_activo'] ?? 0) === 1 ? 'text-bg-light border' : 'text-bg-secondary'; ?>">
                                                <?= e((string) $persona['tipo_poblacion_nombre']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge text-bg-light border">Sin asignar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <span class="badge <?= (int) $persona['es_testigo'] === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>">Testigo: <?= (int) $persona['es_testigo'] === 1 ? 'Si' : 'No'; ?></span>
                                            <span class="badge <?= (int) ($persona['es_jurado'] ?? 0) === 1 ? 'text-bg-warning' : 'text-bg-secondary'; ?>">Jurado: <?= (int) ($persona['es_jurado'] ?? 0) === 1 ? 'Si' : 'No'; ?></span>
                                        </div>
                                    </td>
                                    <td class="text-end actions-col">
                                        <a href="<?= e(page_url_with_query('personas', ['action' => 'edit', 'id' => (int) $persona['id'], 'q' => $search, 'sort' => $sortBy, 'dir' => $sortDir, 'p' => $currentListPage])); ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen-to-square me-1"></i>Editar</a>
                                        <form method="post" class="d-inline" data-confirm="Confirma eliminar esta persona? Esta accion no se puede deshacer.">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="form_action" value="delete_persona">
                                            <input type="hidden" name="id" value="<?= e((string) $persona['id']); ?>">
                                            <input type="hidden" name="return_q" value="<?= e($search); ?>">
                                            <input type="hidden" name="return_sort" value="<?= e($sortBy); ?>">
                                            <input type="hidden" name="return_dir" value="<?= e($sortDir); ?>">
                                            <input type="hidden" name="return_p" value="<?= e((string) $currentListPage); ?>">
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
