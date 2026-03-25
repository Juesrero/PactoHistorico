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
    'organizacion' => '',
    'lugar_reunion' => '',
    'fecha' => '',
    'hora' => '',
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
                redirect_to('reuniones');
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
?>

<section class="row g-3">
    <div class="col-lg-4">
        <div class="card-clean p-4">
            <h1 class="h5 mb-1"><?= $formMode === 'edit' ? 'Editar reunion' : 'Nueva reunion'; ?></h1>
            <p class="text-muted small mb-3">Administre la informacion principal de cada reunion.</p>

            <?php if (isset($formErrors['general'])): ?>
                <div class="alert alert-danger"><?= e($formErrors['general']); ?></div>
            <?php endif; ?>

            <form method="post" class="needs-validation" novalidate>
                <?= csrf_field(); ?>
                <input type="hidden" name="form_action" value="<?= $formMode === 'edit' ? 'update_reunion' : 'create_reunion'; ?>">
                <?php if ($formMode === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= e((string) $formData['id']); ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="nombre_reunion" class="form-label">Nombre de la reunion</label>
                    <input
                        id="nombre_reunion"
                        name="nombre_reunion"
                        type="text"
                        class="form-control <?= isset($formErrors['nombre_reunion']) ? 'is-invalid' : ''; ?>"
                        maxlength="150"
                        required
                        value="<?= e((string) $formData['nombre_reunion']); ?>"
                    >
                    <div class="invalid-feedback"><?= e($formErrors['nombre_reunion'] ?? 'Campo obligatorio.'); ?></div>
                </div>

                <div class="mb-3">
                    <label for="objetivo" class="form-label">Objetivo</label>
                    <textarea
                        id="objetivo"
                        name="objetivo"
                        class="form-control <?= isset($formErrors['objetivo']) ? 'is-invalid' : ''; ?>"
                        rows="3"
                        required
                    ><?= e((string) $formData['objetivo']); ?></textarea>
                    <div class="invalid-feedback"><?= e($formErrors['objetivo'] ?? 'Campo obligatorio.'); ?></div>
                </div>

                <div class="mb-3">
                    <label for="organizacion" class="form-label">Organizacion</label>
                    <input
                        id="organizacion"
                        name="organizacion"
                        type="text"
                        class="form-control <?= isset($formErrors['organizacion']) ? 'is-invalid' : ''; ?>"
                        maxlength="120"
                        required
                        value="<?= e((string) $formData['organizacion']); ?>"
                    >
                    <div class="invalid-feedback"><?= e($formErrors['organizacion'] ?? 'Campo obligatorio.'); ?></div>
                </div>

                <div class="mb-3">
                    <label for="lugar_reunion" class="form-label">Lugar reunion</label>
                    <input
                        id="lugar_reunion"
                        name="lugar_reunion"
                        type="text"
                        class="form-control <?= isset($formErrors['lugar_reunion']) ? 'is-invalid' : ''; ?>"
                        maxlength="150"
                        required
                        value="<?= e((string) $formData['lugar_reunion']); ?>"
                    >
                    <div class="invalid-feedback"><?= e($formErrors['lugar_reunion'] ?? 'Campo obligatorio.'); ?></div>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input
                            id="fecha"
                            name="fecha"
                            type="date"
                            class="form-control <?= isset($formErrors['fecha']) ? 'is-invalid' : ''; ?>"
                            required
                            value="<?= e((string) $formData['fecha']); ?>"
                        >
                        <div class="invalid-feedback"><?= e($formErrors['fecha'] ?? 'Fecha invalida.'); ?></div>
                    </div>
                    <div class="col-6">
                        <label for="hora" class="form-label">Hora</label>
                        <input
                            id="hora"
                            name="hora"
                            type="time"
                            class="form-control <?= isset($formErrors['hora']) ? 'is-invalid' : ''; ?>"
                            required
                            value="<?= e((string) $formData['hora']); ?>"
                        >
                        <div class="invalid-feedback"><?= e($formErrors['hora'] ?? 'Hora invalida.'); ?></div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-floppy-disk me-1"></i><?= $formMode === 'edit' ? 'Actualizar' : 'Guardar'; ?></button>
                    <?php if ($formMode === 'edit'): ?>
                        <a href="<?= e(page_url('reuniones')); ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i>Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card-clean p-4 mb-3">
            <h2 class="h5 mb-3">Listado de reuniones</h2>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Reunion</th>
                            <th>Organizacion</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Asistentes</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reuniones === []): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No hay reuniones registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reuniones as $reunion): ?>
                                <tr>
                                    <td><?= e((string) $reunion['id']); ?></td>
                                    <td>
                                        <strong><?= e((string) $reunion['nombre_reunion']); ?></strong>
                                        <div class="small text-muted text-truncate-table"><?= e((string) $reunion['lugar_reunion']); ?></div>
                                    </td>
                                    <td><?= e((string) $reunion['organizacion']); ?></td>
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
            <div class="card-clean p-4">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h3 class="h5 mb-1">Detalle de reunion #<?= e((string) $detailData['id']); ?></h3>
                        <p class="mb-0 text-muted">Informacion completa y registro de asistencia.</p>
                    </div>
                    <a href="<?= e(page_url('reuniones')); ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i>Cerrar</a>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6"><strong>Nombre:</strong> <?= e((string) $detailData['nombre_reunion']); ?></div>
                    <div class="col-md-6"><strong>Organizacion:</strong> <?= e((string) $detailData['organizacion']); ?></div>
                    <div class="col-md-6"><strong>Lugar:</strong> <?= e((string) $detailData['lugar_reunion']); ?></div>
                    <div class="col-md-3"><strong>Fecha:</strong> <?= e((string) $detailData['fecha']); ?></div>
                    <div class="col-md-3"><strong>Hora:</strong> <?= e(substr((string) $detailData['hora'], 0, 5)); ?></div>
                    <div class="col-12"><strong>Objetivo:</strong> <?= nl2br(e((string) $detailData['objetivo'])); ?></div>
                </div>

                <?php
                    $excelExportUrl = url('exports/excel/reunion_asistencia_excel.php') . '?reunion_id=' . urlencode((string) $detailData['id']);
                    $pdfExportUrl = url('exports/pdf/reunion_asistencia_pdf.php') . '?reunion_id=' . urlencode((string) $detailData['id']);
                    $detailAnchorUrl = page_url_with_query('reuniones', ['action' => 'detail', 'id' => (int) $detailData['id']]) . '#agregar-asistencia';
                ?>
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge text-bg-primary">Total asistentes: <?= e((string) $detailData['total_asistentes']); ?></span>
                        <span class="badge text-bg-success">Total testigos: <?= e((string) $detailData['total_testigos']); ?></span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= e($excelExportUrl); ?>" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-file-excel me-1"></i>Exportar a Excel</a>
                        <a href="<?= e($pdfExportUrl); ?>" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-file-pdf me-1"></i>Exportar a PDF</a>
                    </div>
                </div>

                <div id="agregar-asistencia" class="border rounded p-3 mb-3 bg-light-subtle">
                    <h4 class="h6 mb-2">Agregar asistencia</h4>

                    <form method="get" action="<?= e(url('index.php')); ?>#agregar-asistencia" class="row g-2 mb-2">
                        <input type="hidden" name="page" value="reuniones">
                        <input type="hidden" name="action" value="detail">
                        <input type="hidden" name="id" value="<?= e((string) $detailData['id']); ?>">
                        <div class="col-md-8">
                            <input type="text" id="person_q" name="person_q" class="form-control" placeholder="Buscar persona por nombre o documento" value="<?= e($personSearch); ?>">
                        </div>
                        <div class="col-md-4 d-grid d-md-flex gap-2">
                            <button type="submit" class="btn btn-outline-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i>Buscar</button>
                            <a href="<?= e($detailAnchorUrl); ?>" class="btn btn-outline-secondary w-100"><i class="fa-solid fa-eraser me-1"></i>Limpiar</a>
                        </div>
                    </form>

                    <form method="post" class="row g-2 needs-validation" novalidate>
                        <?= csrf_field(); ?>
                        <input type="hidden" name="form_action" value="add_asistencia">
                        <input type="hidden" name="reunion_id" value="<?= e((string) $detailData['id']); ?>">
                        <input type="hidden" name="person_q" value="<?= e($personSearch); ?>">

                        <div class="col-md-9">
                            <select name="persona_id" class="form-select" required>
                                <option value="">Seleccione una persona...</option>
                                <?php foreach ($availablePersons as $person): ?>
                                    <option value="<?= e((string) $person['id']); ?>">
                                        <?= e((string) $person['nombres_apellidos']); ?> - <?= e((string) $person['numero_documento']); ?>
                                        <?php if ((int) $person['es_testigo'] === 1): ?>
                                            (Testigo)
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
                        <p class="small text-muted mt-2 mb-0">No hay personas disponibles con ese filtro o todas ya fueron registradas en esta reunion.</p>
                    <?php endif; ?>
                </div>

                <h4 class="h6 mb-2">Asistentes de la reunion</h4>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nombre y apellido</th>
                                <th>Documento</th>
                                <th>Celular</th>
                                <th>Testigo</th>
                                <th>Fecha registro</th>
                                <th>Hora registro</th>
                                <th class="text-end">Accion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($detailAttendees === []): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">Sin asistentes registrados.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($detailAttendees as $attendee): ?>
                                    <tr>
                                        <td><?= e((string) $attendee['nombres_apellidos']); ?></td>
                                        <td><?= e((string) $attendee['numero_documento']); ?></td>
                                        <td><?= e((string) $attendee['celular']); ?></td>
                                        <td>
                                            <span class="badge <?= (int) $attendee['es_testigo'] === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                                <?= (int) $attendee['es_testigo'] === 1 ? 'Si' : 'No'; ?>
                                            </span>
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

