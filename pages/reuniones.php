<?php
declare(strict_types=1);

$reunionService = new ReunionService(db());

$action = request_string($_GET, 'action', 20);
$requestedId = request_int($_GET, 'id', 0);
$personSearch = request_string($_GET, 'person_q', 120);

$formMode = 'create';
$formErrors = [];
$formData = [
    'id' => 0,
    'nombre_reunion' => '',
    'objetivo' => '',
    'tipo_reunion' => '',
    'organizacion' => '',
    'lugar_reunion' => '',
    'fecha' => '',
    'hora' => '',
];

$meetingTypeSuggestions = [
    'Comite',
    'Asamblea',
    'Capacitacion',
    'Seguimiento',
    'Planeacion',
    'Socializacion',
];

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('message', 'Token CSRF invalido. Intente nuevamente.', 'danger');
        redirect_to('reuniones');
    }

    $postAction = request_string($_POST, 'form_action', 40);

    if ($postAction === 'create_reunion') {
        [$clean, $formErrors] = Validator::validateReunion($_POST);

        if ($formErrors === []) {
            try {
                $reunionService->create($clean);
                flash('message', 'Reunion creada correctamente.', 'success');
                redirect_to('reuniones');
            } catch (Throwable $exception) {
                $formErrors['general'] = 'No fue posible crear la reunion.';
            }
        }

        $formMode = 'create';
        $formData = array_merge($formData, $clean);
    }

    if ($postAction === 'update_reunion') {
        $editId = request_int($_POST, 'id', 0);
        [$clean, $formErrors] = Validator::validateReunion($_POST);

        if ($editId <= 0) {
            $formErrors['general'] = 'ID de reunion invalido.';
        }

        if ($formErrors === []) {
            try {
                $reunionService->update($editId, $clean);
                flash('message', 'Reunion actualizada correctamente.', 'success');
                redirect_to_url(page_url_with_query('reuniones', ['action' => 'detail', 'id' => $editId]));
            } catch (Throwable $exception) {
                $formErrors['general'] = 'No fue posible actualizar la reunion.';
            }
        }

        $formMode = 'edit';
        $formData = array_merge($formData, $clean, ['id' => $editId]);
    }

    if ($postAction === 'delete_reunion') {
        $deleteId = request_int($_POST, 'id', 0);

        if ($deleteId <= 0) {
            flash('message', 'ID de reunion invalido.', 'danger');
        } else {
            try {
                $deleted = $reunionService->delete($deleteId);
                if ($deleted) {
                    flash('message', 'Reunion eliminada correctamente.', 'success');
                } else {
                    flash('message', 'La reunion no existe o ya fue eliminada.', 'warning');
                }
            } catch (Throwable $exception) {
                flash('message', 'No fue posible eliminar la reunion.', 'danger');
            }
        }

        redirect_to('reuniones');
    }

    if ($postAction === 'add_asistencia') {
        $reunionId = request_int($_POST, 'reunion_id', 0);
        $personaId = request_int($_POST, 'persona_id', 0);
        $returnSearch = request_string($_POST, 'person_q', 120);
        $redirectDetail = page_url_with_query('reuniones', ['action' => 'detail', 'id' => $reunionId, 'person_q' => $returnSearch]) . '#agregar-asistencia';

        if ($reunionId <= 0 || $personaId <= 0) {
            flash('message', 'Debe seleccionar una persona valida para registrar asistencia.', 'warning');
            redirect_to_url($redirectDetail);
        }

        if ($reunionService->findById($reunionId) === null) {
            flash('message', 'La reunion seleccionada no existe.', 'danger');
            redirect_to('reuniones');
        }

        if (!$reunionService->personExists($personaId)) {
            flash('message', 'La persona seleccionada no existe.', 'danger');
            redirect_to_url($redirectDetail);
        }

        try {
            $reunionService->addAttendance($reunionId, $personaId);
            flash('message', 'Asistencia registrada correctamente.', 'success');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                flash('message', 'La persona ya esta registrada en esta reunion.', 'warning');
            } else {
                flash('message', 'No fue posible registrar la asistencia.', 'danger');
            }
        } catch (Throwable $exception) {
            flash('message', 'No fue posible registrar la asistencia.', 'danger');
        }

        redirect_to_url($redirectDetail);
    }

    if ($postAction === 'remove_asistencia') {
        $reunionId = request_int($_POST, 'reunion_id', 0);
        $personaId = request_int($_POST, 'persona_id', 0);
        $returnSearch = request_string($_POST, 'person_q', 120);
        $redirectDetail = page_url_with_query('reuniones', ['action' => 'detail', 'id' => $reunionId, 'person_q' => $returnSearch]) . '#agregar-asistencia';

        if ($reunionId <= 0 || $personaId <= 0) {
            flash('message', 'Parametros invalidos para eliminar asistencia.', 'danger');
            redirect_to('reuniones');
        }

        try {
            $removed = $reunionService->removeAttendance($reunionId, $personaId);
            if ($removed) {
                flash('message', 'Asistencia eliminada correctamente.', 'success');
            } else {
                flash('message', 'La asistencia ya no existe.', 'warning');
            }
        } catch (Throwable $exception) {
            flash('message', 'No fue posible eliminar la asistencia.', 'danger');
        }

        redirect_to_url($redirectDetail);
    }
}

$detailData = null;
$detailAttendees = [];
$availablePersons = [];

if ($action === 'edit' && $requestedId > 0 && $formMode !== 'edit') {
    $reunion = $reunionService->findById($requestedId);

    if ($reunion === null) {
        flash('message', 'La reunion solicitada no existe.', 'warning');
        redirect_to('reuniones');
    }

    $formMode = 'edit';
    $formData = [
        'id' => (int) $reunion['id'],
        'nombre_reunion' => (string) $reunion['nombre_reunion'],
        'objetivo' => (string) $reunion['objetivo'],
        'tipo_reunion' => (string) ($reunion['tipo_reunion'] ?? ''),
        'organizacion' => (string) $reunion['organizacion'],
        'lugar_reunion' => (string) $reunion['lugar_reunion'],
        'fecha' => (string) $reunion['fecha'],
        'hora' => substr((string) $reunion['hora'], 0, 5),
    ];
}

if ($action === 'detail' && $requestedId > 0) {
    $detailData = $reunionService->findById($requestedId);

    if ($detailData === null) {
        flash('message', 'La reunion solicitada no existe.', 'warning');
        redirect_to('reuniones');
    }

    $detailAttendees = $reunionService->attendeesByMeeting($requestedId);
    $availablePersons = $reunionService->availablePersonsForMeeting($requestedId, $personSearch);
}

$reuniones = $reunionService->list();
$availableCount = count($availablePersons);
?>

<section class="row g-3">
    <div class="col-xl-4">
        <div class="card-clean p-4">
            <h1 class="h5 mb-1"><?= $formMode === 'edit' ? 'Editar reunion' : 'Nueva reunion'; ?></h1>
            <p class="text-muted small mb-3">Registre nombre, objetivo, tipo, lugar, fecha y hora sin afectar la toma de asistencia.</p>

            <?php if (isset($formErrors['general'])): ?>
                <div class="alert alert-danger"><?= e($formErrors['general']); ?></div>
            <?php endif; ?>

            <form method="post" class="needs-validation compact-floating-form reunion-form-compact" novalidate>
                <?= csrf_field(); ?>
                <input type="hidden" name="form_action" value="<?= $formMode === 'edit' ? 'update_reunion' : 'create_reunion'; ?>">
                <?php if ($formMode === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= e((string) $formData['id']); ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <div class="form-floating">
                        <input id="nombre_reunion" name="nombre_reunion" type="text" class="form-control <?= isset($formErrors['nombre_reunion']) ? 'is-invalid' : ''; ?>" maxlength="150" placeholder="Nombre de la reunion" required value="<?= e((string) $formData['nombre_reunion']); ?>">
                        <label for="nombre_reunion">Nombre de la reunion</label>
                        <div class="invalid-feedback"><?= e($formErrors['nombre_reunion'] ?? 'Campo obligatorio.'); ?></div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-floating">
                        <textarea id="objetivo" name="objetivo" class="form-control <?= isset($formErrors['objetivo']) ? 'is-invalid' : ''; ?>" placeholder="Objetivo" style="height: 96px;" maxlength="2000" required><?= e((string) $formData['objetivo']); ?></textarea>
                        <label for="objetivo">Objetivo</label>
                        <div class="invalid-feedback"><?= e($formErrors['objetivo'] ?? 'Campo obligatorio.'); ?></div>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input id="tipo_reunion" name="tipo_reunion" type="text" class="form-control <?= isset($formErrors['tipo_reunion']) ? 'is-invalid' : ''; ?>" maxlength="80" minlength="3" list="tiposReunionList" placeholder="Tipo" required value="<?= e((string) $formData['tipo_reunion']); ?>">
                            <label for="tipo_reunion">Tipo</label>
                            <datalist id="tiposReunionList">
                                <?php foreach ($meetingTypeSuggestions as $suggestion): ?>
                                    <option value="<?= e($suggestion); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <div class="invalid-feedback"><?= e($formErrors['tipo_reunion'] ?? 'Ingrese un tipo de reunion.'); ?></div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-floating form-floating-optional">
                            <input id="organizacion" name="organizacion" type="text" class="form-control <?= isset($formErrors['organizacion']) ? 'is-invalid' : ''; ?>" maxlength="120" placeholder="Organizacion" value="<?= e((string) $formData['organizacion']); ?>">
                            <label for="organizacion">Organizacion</label>
                            <span class="floating-field-note">Opcional</span>
                        </div>
                        <div class="invalid-feedback"><?= e($formErrors['organizacion'] ?? 'Revise este campo.'); ?></div>
                    </div>
                </div>

                <div class="mb-3 mt-3">
                    <div class="form-floating">
                        <input id="lugar_reunion" name="lugar_reunion" type="text" class="form-control <?= isset($formErrors['lugar_reunion']) ? 'is-invalid' : ''; ?>" maxlength="150" placeholder="Lugar" required value="<?= e((string) $formData['lugar_reunion']); ?>">
                        <label for="lugar_reunion">Lugar</label>
                        <div class="invalid-feedback"><?= e($formErrors['lugar_reunion'] ?? 'Campo obligatorio.'); ?></div>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <div class="form-floating">
                            <input id="fecha" name="fecha" type="date" class="form-control <?= isset($formErrors['fecha']) ? 'is-invalid' : ''; ?>" placeholder="Fecha" required value="<?= e((string) $formData['fecha']); ?>">
                            <label for="fecha">Fecha</label>
                            <div class="invalid-feedback"><?= e($formErrors['fecha'] ?? 'Fecha invalida.'); ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-floating">
                            <input id="hora" name="hora" type="time" class="form-control <?= isset($formErrors['hora']) ? 'is-invalid' : ''; ?>" placeholder="Hora" required value="<?= e((string) $formData['hora']); ?>">
                            <label for="hora">Hora</label>
                            <div class="invalid-feedback"><?= e($formErrors['hora'] ?? 'Hora invalida.'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-floppy-disk me-1"></i><?= $formMode === 'edit' ? 'Actualizar' : 'Guardar'; ?></button>
                    <?php if ($formMode === 'edit'): ?>
                        <a href="<?= e(page_url_with_query('reuniones', ['action' => 'detail', 'id' => (int) $formData['id']])); ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i>Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card-clean p-4 mb-3">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Listado de reuniones</h2>
                    <p class="small text-muted mb-0">Vista resumida con tipo, agenda y acceso directo al detalle para seguir tomando asistencia.</p>
                </div>
                <span class="badge text-bg-light border">Total: <?= e((string) count($reuniones)); ?></span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Reunion</th>
                            <th>Tipo</th>
                            <th>Lugar</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Asistentes</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reuniones === []): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No hay reuniones registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reuniones as $reunion): ?>
                                <tr>
                                    <td><?= e((string) $reunion['id']); ?></td>
                                    <td>
                                        <strong><?= e((string) $reunion['nombre_reunion']); ?></strong>
                                        <div class="small text-muted text-truncate-table"><?= e((string) $reunion['objetivo']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-primary"><?= e((string) ($reunion['tipo_reunion'] ?? 'General')); ?></span>
                                        <?php if (!empty($reunion['organizacion']) && (string) $reunion['organizacion'] !== (string) ($reunion['tipo_reunion'] ?? '')): ?>
                                            <div class="small text-muted mt-1"><?= e((string) $reunion['organizacion']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e((string) $reunion['lugar_reunion']); ?></td>
                                    <td><?= e((string) $reunion['fecha']); ?></td>
                                    <td><?= e(substr((string) $reunion['hora'], 0, 5)); ?></td>
                                    <td><span class="badge text-bg-info"><?= e((string) $reunion['total_asistentes']); ?></span></td>
                                    <td class="text-end actions-col">
                                        <a href="<?= e(page_url_with_query('reuniones', ['action' => 'detail', 'id' => (int) $reunion['id']])); ?>" class="btn btn-sm btn-outline-dark"><i class="fa-solid fa-eye me-1"></i>Ver</a>
                                        <a href="<?= e(page_url_with_query('reuniones', ['action' => 'edit', 'id' => (int) $reunion['id']])); ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen-to-square me-1"></i>Editar</a>
                                        <form method="post" class="d-inline" data-confirm="Confirma eliminar esta reunion? Se eliminaran tambien sus asistencias.">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="form_action" value="delete_reunion">
                                            <input type="hidden" name="id" value="<?= e((string) $reunion['id']); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash-can me-1"></i>Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($detailData !== null): ?>
            <?php
                $excelExportUrl = url('exports/excel/reunion_asistencia_excel.php') . '?reunion_id=' . urlencode((string) $detailData['id']);
                $pdfExportUrl = url('exports/pdf/reunion_asistencia_pdf.php') . '?reunion_id=' . urlencode((string) $detailData['id']);
                $detailAnchorUrl = page_url_with_query('reuniones', ['action' => 'detail', 'id' => (int) $detailData['id']]) . '#agregar-asistencia';
                $showOrganization = !empty($detailData['organizacion']) && (string) $detailData['organizacion'] !== (string) ($detailData['tipo_reunion'] ?? '');
            ?>
            <div class="card-clean p-4 attendance-detail-card">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
                    <div>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="badge text-bg-primary">Tipo: <?= e((string) ($detailData['tipo_reunion'] ?? 'General')); ?></span>
                            <span class="badge text-bg-light border">Fecha: <?= e((string) $detailData['fecha']); ?></span>
                            <span class="badge text-bg-light border">Hora: <?= e(substr((string) $detailData['hora'], 0, 5)); ?></span>
                        </div>
                        <h3 class="h4 mb-1"><?= e((string) $detailData['nombre_reunion']); ?></h3>
                        <p class="text-muted mb-0">Detalle operativo de la reunion y registro de asistencia en la misma vista.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= e(page_url_with_query('reuniones', ['action' => 'edit', 'id' => (int) $detailData['id']])); ?>" class="btn btn-outline-primary"><i class="fa-solid fa-pen-to-square me-1"></i>Editar reunion</a>
                        <a href="<?= e(page_url('reuniones')); ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i>Cerrar</a>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="summary-card p-3 h-100">
                            <small class="text-muted d-block mb-1">Lugar</small>
                            <div class="fw-semibold"><?= e((string) $detailData['lugar_reunion']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card p-3 h-100">
                            <small class="text-muted d-block mb-1">Total asistentes</small>
                            <div class="summary-value"><?= e((string) $detailData['total_asistentes']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card p-3 h-100">
                            <small class="text-muted d-block mb-1">Total testigos</small>
                            <div class="summary-value"><?= e((string) $detailData['total_testigos']); ?></div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-8">
                        <div class="border rounded p-3 h-100 bg-light-subtle">
                            <h4 class="h6 mb-2">Objetivo</h4>
                            <p class="mb-0"><?= nl2br(e((string) $detailData['objetivo'])); ?></p>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="border rounded p-3 h-100 bg-light-subtle">
                            <h4 class="h6 mb-2">Datos complementarios</h4>
                            <div><strong>Tipo:</strong> <?= e((string) ($detailData['tipo_reunion'] ?? '')); ?></div>
                            <?php if ($showOrganization): ?>
                                <div class="mt-1"><strong>Organizacion:</strong> <?= e((string) $detailData['organizacion']); ?></div>
                            <?php else: ?>
                                <div class="mt-1 text-muted small">Sin organizacion diferenciada.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div class="small text-muted">Puede seguir agregando o quitando asistentes desde este mismo detalle.</div>
                    <div class="d-flex gap-2">
                        <a href="<?= e($excelExportUrl); ?>" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-file-excel me-1"></i>Exportar a Excel</a>
                        <a href="<?= e($pdfExportUrl); ?>" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-file-pdf me-1"></i>Exportar a PDF</a>
                    </div>
                </div>

                <div id="agregar-asistencia" class="attendance-panel mb-3">
                    <div class="attendance-panel-head">
                        <div>
                            <h4 class="h6 mb-1">Tomar asistencia</h4>
                            <p class="attendance-panel-subtitle mb-0">Busque por identificacion, nombres o apellidos y agregue a la reunion sin salir del detalle.</p>
                        </div>
                        <div class="attendance-stats">
                            <span class="attendance-stat-chip">Disponibles: <?= e((string) $availableCount); ?></span>
                            <span class="attendance-stat-chip">Registrados: <?= e((string) $detailData['total_asistentes']); ?></span>
                        </div>
                    </div>

                    <form method="get" action="<?= e(url('index.php')); ?>#agregar-asistencia" class="row g-2 mb-2">
                        <input type="hidden" name="page" value="reuniones">
                        <input type="hidden" name="action" value="detail">
                        <input type="hidden" name="id" value="<?= e((string) $detailData['id']); ?>">
                        <div class="col-md-8">
                            <input type="text" id="person_q" name="person_q" class="form-control" placeholder="Buscar por identificacion, nombres o apellidos" value="<?= e($personSearch); ?>">
                        </div>
                        <div class="col-md-4 d-grid d-md-flex gap-2">
                            <button type="submit" class="btn btn-outline-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i>Buscar</button>
                            <a href="<?= e($detailAnchorUrl); ?>" class="btn btn-outline-secondary w-100"><i class="fa-solid fa-eraser me-1"></i>Limpiar</a>
                        </div>
                    </form>

                    <?php if ($personSearch !== ''): ?>
                        <div class="attendance-search-meta mb-2">Filtro actual: <strong><?= e($personSearch); ?></strong></div>
                    <?php endif; ?>

                    <form method="post" class="row g-2 needs-validation" novalidate>
                        <?= csrf_field(); ?>
                        <input type="hidden" name="form_action" value="add_asistencia">
                        <input type="hidden" name="reunion_id" value="<?= e((string) $detailData['id']); ?>">
                        <input type="hidden" name="person_q" value="<?= e($personSearch); ?>">

                        <div class="col-md-9">
                            <select name="persona_id" class="form-select attendance-select" required>
                                <option value="">Seleccione una persona...</option>
                                <?php foreach ($availablePersons as $person): ?>
                                    <option value="<?= e((string) $person['id']); ?>">
                                        <?= e((string) $person['nombre_persona']); ?> - <?= e((string) $person['numero_documento']); ?>
                                        <?php if ((int) $person['es_testigo'] === 1 || (int) ($person['es_jurado'] ?? 0) === 1): ?>
                                            (
                                            <?php if ((int) $person['es_testigo'] === 1): ?>Testigo<?php endif; ?>
                                            <?php if ((int) $person['es_testigo'] === 1 && (int) ($person['es_jurado'] ?? 0) === 1): ?> / <?php endif; ?>
                                            <?php if ((int) ($person['es_jurado'] ?? 0) === 1): ?>Jurado<?php endif; ?>
                                            )
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Debe seleccionar una persona.</div>
                        </div>
                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Agregar</button>
                        </div>
                    </form>

                    <?php if ($availablePersons === []): ?>
                        <div class="attendance-empty-state mt-3">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>No hay personas disponibles con ese filtro o todas ya fueron registradas en esta reunion.</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
                    <h4 class="h6 mb-0">Asistentes de la reunion</h4>
                    <div class="attendance-stats">
                        <span class="attendance-stat-chip">Total asistentes: <?= e((string) $detailData['total_asistentes']); ?></span>
                        <span class="attendance-stat-chip">Testigos: <?= e((string) $detailData['total_testigos']); ?></span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0 attendance-table">
                        <thead>
                            <tr>
                                <th>Persona</th>
                                <th>Documento</th>
                                <th>Telefono</th>
                                <th>Roles</th>
                                <th>Fecha registro</th>
                                <th>Hora registro</th>
                                <th class="text-end">Accion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($detailAttendees === []): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Sin asistentes registrados en esta reunion.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($detailAttendees as $attendee): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= e((string) $attendee['nombre_persona']); ?></div>
                                            <?php if (!empty($attendee['apellidos']) || !empty($attendee['nombres'])): ?>
                                                <div class="small text-muted">Registro listo para control de asistencia</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e((string) $attendee['numero_documento']); ?></td>
                                        <td><?= e((string) $attendee['celular']); ?></td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <span class="role-pill <?= (int) $attendee['es_testigo'] === 1 ? 'role-pill-testigo' : 'role-pill-neutral'; ?>">Testigo: <?= (int) $attendee['es_testigo'] === 1 ? 'Si' : 'No'; ?></span>
                                                <span class="role-pill <?= (int) ($attendee['es_jurado'] ?? 0) === 1 ? 'role-pill-jurado' : 'role-pill-neutral'; ?>">Jurado: <?= (int) ($attendee['es_jurado'] ?? 0) === 1 ? 'Si' : 'No'; ?></span>
                                            </div>
                                        </td>
                                        <td><?= e((string) $attendee['fecha_registro']); ?></td>
                                        <td><?= e(substr((string) $attendee['hora_registro'], 0, 5)); ?></td>
                                        <td class="text-end">
                                            <form method="post" class="d-inline" data-confirm="Confirma quitar esta asistencia de la reunion?">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="form_action" value="remove_asistencia">
                                                <input type="hidden" name="reunion_id" value="<?= e((string) $detailData['id']); ?>">
                                                <input type="hidden" name="persona_id" value="<?= e((string) $attendee['persona_id']); ?>">
                                                <input type="hidden" name="person_q" value="<?= e($personSearch); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-user-minus me-1"></i>Quitar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
