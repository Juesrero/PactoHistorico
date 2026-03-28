<?php
declare(strict_types=1);

$reunionService = new ReunionService(db());

$action = request_string($_GET, 'action', 20);
$requestedId = request_int($_GET, 'id', 0);
$meetingSearch = request_string($_GET, 'q', 120);
$personSearch = request_string($_GET, 'person_q', 120);
$listSortBy = request_string($_GET, 'sort', 30);
$listSortDir = strtolower(request_string($_GET, 'dir', 4));
$listPage = max(1, request_int($_GET, 'p', 1));
$attendeeSortBy = request_string($_GET, 'att_sort', 30);
$attendeeSortDir = strtolower(request_string($_GET, 'att_dir', 4));
$attendeePage = max(1, request_int($_GET, 'att_p', 1));

$allowedListSorts = ['id', 'nombre_reunion', 'tipo_reunion', 'lugar_reunion', 'fecha', 'hora', 'total_asistentes'];
if (!in_array($listSortBy, $allowedListSorts, true)) {
    $listSortBy = 'fecha';
}
if (!in_array($listSortDir, ['asc', 'desc'], true)) {
    $listSortDir = 'desc';
}

$allowedAttendeeSorts = ['nombre_persona', 'numero_documento', 'celular', 'es_testigo', 'es_jurado', 'fecha_registro', 'hora_registro'];
if (!in_array($attendeeSortBy, $allowedAttendeeSorts, true)) {
    $attendeeSortBy = 'nombre_persona';
}
if (!in_array($attendeeSortDir, ['asc', 'desc'], true)) {
    $attendeeSortDir = 'asc';
}

$listPerPage = 10;
$attendeesPerPage = 10;

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

$buildMeetingListUrl = static function (string $search, string $sortBy, string $sortDir, int $page): string {
    return page_url_with_query('reuniones', [
        'q' => $search,
        'sort' => $sortBy,
        'dir' => $sortDir,
        'p' => $page,
    ]);
};

$buildDetailUrl = static function (
    int $id,
    string $personSearch,
    string $listSearch,
    string $listSortBy,
    string $listSortDir,
    int $listPage,
    string $attendeeSortBy,
    string $attendeeSortDir,
    int $attendeePage
): string {
    return page_url_with_query('reuniones', [
        'action' => 'detail',
        'id' => $id,
        'person_q' => $personSearch,
        'q' => $listSearch,
        'sort' => $listSortBy,
        'dir' => $listSortDir,
        'p' => $listPage,
        'att_sort' => $attendeeSortBy,
        'att_dir' => $attendeeSortDir,
        'att_p' => $attendeePage,
    ]);
};

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

    $totalAttendees = $reunionService->countAttendeesByMeeting($requestedId);
    $attendeeTotalPages = max(1, (int) ceil($totalAttendees / $attendeesPerPage));
    if ($attendeePage > $attendeeTotalPages) {
        $attendeePage = $attendeeTotalPages;
    }

    $detailAttendees = $reunionService->attendeesByMeetingPaginated(
        $requestedId,
        $attendeeSortBy,
        $attendeeSortDir,
        $attendeesPerPage,
        ($attendeePage - 1) * $attendeesPerPage
    );
    $availablePersons = $reunionService->availablePersonsForMeeting($requestedId, $personSearch);
}

$totalReunionesRecords = $reunionService->count($meetingSearch);
$listTotalPages = max(1, (int) ceil($totalReunionesRecords / $listPerPage));
if ($listPage > $listTotalPages) {
    $listPage = $listTotalPages;
}
$reuniones = $reunionService->listPaginated($meetingSearch, $listSortBy, $listSortDir, $listPerPage, ($listPage - 1) * $listPerPage);
$availableCount = count($availablePersons);
$totalReuniones = count($reuniones);
$totalAsistenciasRegistradas = array_sum(array_map(static fn (array $reunion): int => (int) ($reunion['total_asistentes'] ?? 0), $reuniones));
$reunionesProgramadas = count(array_filter($reuniones, static function (array $reunion): bool {
    $date = trim((string) ($reunion['fecha'] ?? ''));
    $time = trim((string) ($reunion['hora'] ?? ''));
    if ($date === '' || $time === '') {
        return false;
    }

    return strtotime($date . ' ' . $time) >= time();
}));
$listFirstRecord = $totalReunionesRecords > 0 ? (($listPage - 1) * $listPerPage) + 1 : 0;
$listLastRecord = $totalReunionesRecords > 0 ? (($listPage - 1) * $listPerPage) + count($reuniones) : 0;
$attendeeFirstRecord = isset($totalAttendees) && $totalAttendees > 0 ? (($attendeePage - 1) * $attendeesPerPage) + 1 : 0;
$attendeeLastRecord = isset($totalAttendees) && $totalAttendees > 0 ? (($attendeePage - 1) * $attendeesPerPage) + count($detailAttendees) : 0;
?>

<section class="module-hero card-clean mb-4">
    <div class="row g-4 align-items-stretch">
        <div class="col-xl-8">
            <div class="module-hero-copy">
                <span class="dashboard-kicker">Coordinacion operativa</span>
                <h1 class="dashboard-title">Reuniones</h1>
                <p class="dashboard-subtitle">Programa jornadas, organiza la informacion basica del encuentro y continua la asistencia desde el mismo detalle sin salir del flujo operativo.</p>

                <div class="module-hero-actions">
                    <a href="<?= e(page_url('reuniones')); ?>" class="btn btn-primary">
                        <i class="fa-solid fa-calendar-plus me-1"></i><?= $formMode === 'edit' ? 'Nueva reunion' : 'Gestionar formulario'; ?>
                    </a>
                    <?php if ($detailData !== null): ?>
                        <a href="<?= e($detailAnchorUrl ?? page_url('reuniones')); ?>" class="btn btn-outline-success">
                            <i class="fa-solid fa-clipboard-check me-1"></i>Seguir asistencia
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="module-hero-stats">
                <article class="module-hero-stat">
                    <small>Reuniones</small>
                    <strong><?= e((string) $totalReunionesRecords); ?></strong>
                    <span>Total de jornadas registradas en el sistema.</span>
                </article>
                <article class="module-hero-stat">
                    <small>Programadas</small>
                    <strong><?= e((string) $reunionesProgramadas); ?></strong>
                    <span>Encuentros futuros segun fecha y hora guardadas.</span>
                </article>
                <article class="module-hero-stat">
                    <small>Asistencias</small>
                    <strong><?= e((string) $totalAsistenciasRegistradas); ?></strong>
                    <span>Acumuladas entre todas las reuniones listadas.</span>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="row g-3">
    <div class="col-xl-4 module-layout-sidebar">
        <div class="card-clean p-4 module-panel-card module-sticky-sidebar">
            <h1 class="module-panel-title mb-1"><?= $formMode === 'edit' ? 'Editar reunion' : 'Nueva reunion'; ?></h1>
            <p class="module-panel-subtitle mb-3">Registre nombre, objetivo, tipo, lugar, fecha y hora sin afectar la toma de asistencia.</p>

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
                        <a href="<?= e($buildDetailUrl((int) $formData['id'], $personSearch, $meetingSearch, $listSortBy, $listSortDir, $listPage, $attendeeSortBy, $attendeeSortDir, $attendeePage)); ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i>Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card-clean p-4 mb-3 module-panel-card">
            <div class="module-panel-head">
                <div>
                    <h2 class="module-panel-title">Listado de reuniones</h2>
                    <p class="module-panel-subtitle">Vista resumida con tipo, agenda y acceso directo al detalle para seguir tomando asistencia.</p>
                </div>
                <span class="module-panel-badge">Total: <?= e((string) $totalReunionesRecords); ?></span>
            </div>
            <form method="get" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="page" value="reuniones">
                <input type="hidden" name="sort" value="<?= e($listSortBy); ?>">
                <input type="hidden" name="dir" value="<?= e($listSortDir); ?>">
                <div class="col-md-8">
                    <label for="q" class="form-label">Buscar reunion</label>
                    <input id="q" name="q" type="text" class="form-control" maxlength="120" value="<?= e($meetingSearch); ?>" placeholder="Busque por nombre, objetivo, tipo, organizacion o lugar">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="fa-solid fa-magnifying-glass me-1"></i>Buscar</button>
                    <a href="<?= e(page_url('reuniones')); ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i>Limpiar</a>
                </div>
            </form>
            <div class="module-toolbar">
                <div class="module-toolbar-meta">
                    <span><i class="fa-solid fa-filter me-1"></i>Orden actual: <strong><?= e($listSortBy); ?></strong> (<?= e(strtoupper($listSortDir)); ?>)</span>
                    <span><i class="fa-solid fa-list-ol me-1"></i>Mostrando <?= e((string) $listFirstRecord); ?> - <?= e((string) $listLastRecord); ?> de <?= e((string) $totalReunionesRecords); ?></span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle module-table">
                    <thead>
                        <tr>
                            <th><div class="sort-header"><span>#</span><span class="sort-controls"><a class="sort-link <?= $listSortBy === 'id' && $listSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($buildMeetingListUrl($meetingSearch, 'id', 'asc', 1)); ?>"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $listSortBy === 'id' && $listSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($buildMeetingListUrl($meetingSearch, 'id', 'desc', 1)); ?>"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th><div class="sort-header"><span>Reunion</span><span class="sort-controls"><a class="sort-link <?= $listSortBy === 'nombre_reunion' && $listSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($buildMeetingListUrl($meetingSearch, 'nombre_reunion', 'asc', 1)); ?>"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $listSortBy === 'nombre_reunion' && $listSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($buildMeetingListUrl($meetingSearch, 'nombre_reunion', 'desc', 1)); ?>"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th><div class="sort-header"><span>Tipo</span><span class="sort-controls"><a class="sort-link <?= $listSortBy === 'tipo_reunion' && $listSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($buildMeetingListUrl($meetingSearch, 'tipo_reunion', 'asc', 1)); ?>"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $listSortBy === 'tipo_reunion' && $listSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($buildMeetingListUrl($meetingSearch, 'tipo_reunion', 'desc', 1)); ?>"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th><div class="sort-header"><span>Lugar</span><span class="sort-controls"><a class="sort-link <?= $listSortBy === 'lugar_reunion' && $listSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($buildMeetingListUrl($meetingSearch, 'lugar_reunion', 'asc', 1)); ?>"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $listSortBy === 'lugar_reunion' && $listSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($buildMeetingListUrl($meetingSearch, 'lugar_reunion', 'desc', 1)); ?>"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th><div class="sort-header"><span>Fecha</span><span class="sort-controls"><a class="sort-link <?= $listSortBy === 'fecha' && $listSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($buildMeetingListUrl($meetingSearch, 'fecha', 'asc', 1)); ?>"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $listSortBy === 'fecha' && $listSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($buildMeetingListUrl($meetingSearch, 'fecha', 'desc', 1)); ?>"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th><div class="sort-header"><span>Hora</span><span class="sort-controls"><a class="sort-link <?= $listSortBy === 'hora' && $listSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($buildMeetingListUrl($meetingSearch, 'hora', 'asc', 1)); ?>"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $listSortBy === 'hora' && $listSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($buildMeetingListUrl($meetingSearch, 'hora', 'desc', 1)); ?>"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                            <th><div class="sort-header"><span>Asistentes</span><span class="sort-controls"><a class="sort-link <?= $listSortBy === 'total_asistentes' && $listSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($buildMeetingListUrl($meetingSearch, 'total_asistentes', 'asc', 1)); ?>"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $listSortBy === 'total_asistentes' && $listSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($buildMeetingListUrl($meetingSearch, 'total_asistentes', 'desc', 1)); ?>"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
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
                                        <a href="<?= e($buildDetailUrl((int) $reunion['id'], '', $meetingSearch, $listSortBy, $listSortDir, $listPage, 'nombre_persona', 'asc', 1)); ?>" class="btn btn-sm btn-outline-dark"><i class="fa-solid fa-eye me-1"></i>Ver</a>
                                        <a href="<?= e(page_url_with_query('reuniones', ['action' => 'edit', 'id' => (int) $reunion['id'], 'q' => $meetingSearch, 'sort' => $listSortBy, 'dir' => $listSortDir, 'p' => $listPage])); ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen-to-square me-1"></i>Editar</a>
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
            <div class="module-pagination">
                <div class="module-pagination-status">Pagina <?= e((string) $listPage); ?> de <?= e((string) $listTotalPages); ?></div>
                <div class="module-pagination-controls">
                    <?php if ($listPage > 1): ?>
                        <a href="<?= e($buildMeetingListUrl($meetingSearch, $listSortBy, $listSortDir, $listPage - 1)); ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Anterior</a>
                    <?php else: ?>
                        <span class="btn btn-sm btn-outline-secondary disabled"><i class="fa-solid fa-arrow-left me-1"></i>Anterior</span>
                    <?php endif; ?>
                    <span class="module-pagination-index"><?= e((string) $listPage); ?>/<?= e((string) $listTotalPages); ?></span>
                    <?php if ($listPage < $listTotalPages): ?>
                        <a href="<?= e($buildMeetingListUrl($meetingSearch, $listSortBy, $listSortDir, $listPage + 1)); ?>" class="btn btn-sm btn-outline-secondary">Siguiente<i class="fa-solid fa-arrow-right ms-1"></i></a>
                    <?php else: ?>
                        <span class="btn btn-sm btn-outline-secondary disabled">Siguiente<i class="fa-solid fa-arrow-right ms-1"></i></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($detailData !== null): ?>
            <?php
                $excelExportUrl = url('exports/excel/reunion_asistencia_excel.php') . '?reunion_id=' . urlencode((string) $detailData['id']);
                $pdfExportUrl = url('exports/pdf/reunion_asistencia_pdf.php') . '?reunion_id=' . urlencode((string) $detailData['id']);
                $detailBaseUrl = $buildDetailUrl((int) $detailData['id'], $personSearch, $meetingSearch, $listSortBy, $listSortDir, $listPage, $attendeeSortBy, $attendeeSortDir, $attendeePage);
                $detailAnchorUrl = $detailBaseUrl . '#agregar-asistencia';
                $showOrganization = !empty($detailData['organizacion']) && (string) $detailData['organizacion'] !== (string) ($detailData['tipo_reunion'] ?? '');
                $attendeeSortUrl = static function (string $column, string $direction) use ($buildDetailUrl, $detailData, $personSearch, $meetingSearch, $listSortBy, $listSortDir, $listPage): string {
                    return $buildDetailUrl((int) $detailData['id'], $personSearch, $meetingSearch, $listSortBy, $listSortDir, $listPage, $column, $direction, 1) . '#asistentes-reunion';
                };
                $attendeePageUrl = static function (int $page) use ($buildDetailUrl, $detailData, $personSearch, $meetingSearch, $listSortBy, $listSortDir, $listPage, $attendeeSortBy, $attendeeSortDir): string {
                    return $buildDetailUrl((int) $detailData['id'], $personSearch, $meetingSearch, $listSortBy, $listSortDir, $listPage, $attendeeSortBy, $attendeeSortDir, $page) . '#asistentes-reunion';
                };
            ?>
            <div class="card-clean p-4 attendance-detail-card module-panel-card">
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
                        <a href="<?= e(page_url_with_query('reuniones', ['action' => 'edit', 'id' => (int) $detailData['id'], 'q' => $meetingSearch, 'sort' => $listSortBy, 'dir' => $listSortDir, 'p' => $listPage, 'att_sort' => $attendeeSortBy, 'att_dir' => $attendeeSortDir, 'att_p' => $attendeePage])); ?>" class="btn btn-outline-primary"><i class="fa-solid fa-pen-to-square me-1"></i>Editar reunion</a>
                        <a href="<?= e($buildMeetingListUrl($meetingSearch, $listSortBy, $listSortDir, $listPage)); ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i>Cerrar</a>
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

                <div id="asistentes-reunion" class="module-toolbar">
                    <div class="module-toolbar-meta">
                        <span><i class="fa-solid fa-filter me-1"></i>Orden actual: <strong><?= e($attendeeSortBy); ?></strong> (<?= e(strtoupper($attendeeSortDir)); ?>)</span>
                        <span><i class="fa-solid fa-list-ol me-1"></i>Mostrando <?= e((string) $attendeeFirstRecord); ?> - <?= e((string) $attendeeLastRecord); ?> de <?= e((string) ($totalAttendees ?? 0)); ?></span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 attendance-table module-table">
                        <thead>
                            <tr>
                                <th><div class="sort-header"><span>Persona</span><span class="sort-controls"><a class="sort-link <?= $attendeeSortBy === 'nombre_persona' && $attendeeSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($attendeeSortUrl('nombre_persona', 'asc')); ?>"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $attendeeSortBy === 'nombre_persona' && $attendeeSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($attendeeSortUrl('nombre_persona', 'desc')); ?>"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                                <th><div class="sort-header"><span>Documento</span><span class="sort-controls"><a class="sort-link <?= $attendeeSortBy === 'numero_documento' && $attendeeSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($attendeeSortUrl('numero_documento', 'asc')); ?>"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $attendeeSortBy === 'numero_documento' && $attendeeSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($attendeeSortUrl('numero_documento', 'desc')); ?>"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                                <th><div class="sort-header"><span>Telefono</span><span class="sort-controls"><a class="sort-link <?= $attendeeSortBy === 'celular' && $attendeeSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($attendeeSortUrl('celular', 'asc')); ?>"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $attendeeSortBy === 'celular' && $attendeeSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($attendeeSortUrl('celular', 'desc')); ?>"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                                <th><div class="sort-header"><span>Roles</span><span class="sort-controls"><a class="sort-link <?= $attendeeSortBy === 'es_testigo' && $attendeeSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($attendeeSortUrl('es_testigo', 'asc')); ?>"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $attendeeSortBy === 'es_testigo' && $attendeeSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($attendeeSortUrl('es_testigo', 'desc')); ?>"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                                <th><div class="sort-header"><span>Fecha registro</span><span class="sort-controls"><a class="sort-link <?= $attendeeSortBy === 'fecha_registro' && $attendeeSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($attendeeSortUrl('fecha_registro', 'asc')); ?>"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $attendeeSortBy === 'fecha_registro' && $attendeeSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($attendeeSortUrl('fecha_registro', 'desc')); ?>"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
                                <th><div class="sort-header"><span>Hora registro</span><span class="sort-controls"><a class="sort-link <?= $attendeeSortBy === 'hora_registro' && $attendeeSortDir === 'asc' ? 'active' : ''; ?>" href="<?= e($attendeeSortUrl('hora_registro', 'asc')); ?>"><i class="fa-solid fa-sort-up"></i></a><a class="sort-link <?= $attendeeSortBy === 'hora_registro' && $attendeeSortDir === 'desc' ? 'active' : ''; ?>" href="<?= e($attendeeSortUrl('hora_registro', 'desc')); ?>"><i class="fa-solid fa-sort-down"></i></a></span></div></th>
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
                <div class="module-pagination">
                    <div class="module-pagination-status">Pagina <?= e((string) $attendeePage); ?> de <?= e((string) ($attendeeTotalPages ?? 1)); ?></div>
                    <div class="module-pagination-controls">
                        <?php if ($attendeePage > 1): ?>
                            <a href="<?= e($attendeePageUrl($attendeePage - 1)); ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Anterior</a>
                        <?php else: ?>
                            <span class="btn btn-sm btn-outline-secondary disabled"><i class="fa-solid fa-arrow-left me-1"></i>Anterior</span>
                        <?php endif; ?>
                        <span class="module-pagination-index"><?= e((string) $attendeePage); ?>/<?= e((string) ($attendeeTotalPages ?? 1)); ?></span>
                        <?php if (($attendeeTotalPages ?? 1) > $attendeePage): ?>
                            <a href="<?= e($attendeePageUrl($attendeePage + 1)); ?>" class="btn btn-sm btn-outline-secondary">Siguiente<i class="fa-solid fa-arrow-right ms-1"></i></a>
                        <?php else: ?>
                            <span class="btn btn-sm btn-outline-secondary disabled">Siguiente<i class="fa-solid fa-arrow-right ms-1"></i></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
