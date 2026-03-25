<?php
declare(strict_types=1);

$actaService = new ActaService(db());

$search = request_string($_GET, 'q', 120);
$action = request_string($_GET, 'action', 20);
$requestedId = request_int($_GET, 'id', 0);

$formMode = 'create';
$formErrors = [];
$formData = [
    'id' => 0,
    'nombre_o_objetivo' => '',
    'responsable' => '',
    'lugar' => '',
];

$detailActa = null;
$actas = [];
$downloadBaseUrl = url('downloads/actas/descargar.php');
$maxUploadSizeMb = (string) ($actaService->maxUploadSize() / 1024 / 1024);

$buildListUrl = static function (string $search): string {
    return page_url_with_query('actas', ['q' => $search]);
};

$formatDateTime = static function (?string $dateTime): string {
    $dateTime = trim((string) $dateTime);
    if ($dateTime === '') {
        return '-';
    }

    $timestamp = strtotime($dateTime);
    if ($timestamp === false) {
        return $dateTime;
    }

    return date('Y-m-d H:i', $timestamp);
};

$buildDownloadUrl = static function (array $acta) use ($downloadBaseUrl): string {
    return $downloadBaseUrl . '?id=' . urlencode((string) $acta['id']);
};

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('message', 'Token CSRF invalido. Intente nuevamente.', 'danger');
        redirect_to('actas');
    }

    $postAction = request_string($_POST, 'form_action', 40);
    $returnQ = request_string($_POST, 'return_q', 120);
    $returnUrl = $buildListUrl($returnQ);

    if ($postAction === 'create_acta') {
        [$clean, $formErrors] = Validator::validateActa($_POST);

        if ($formErrors === []) {
            try {
                $newId = $actaService->create($clean, $_FILES['archivo_adjunto'] ?? null);
                flash('message', 'Acta creada correctamente.', 'success');
                redirect_to_url(page_url_with_query('actas', ['action' => 'detail', 'id' => $newId]));
            } catch (InvalidArgumentException $exception) {
                $formErrors['archivo_adjunto'] = $exception->getMessage();
            } catch (RuntimeException $exception) {
                $formErrors['general'] = $exception->getMessage();
            } catch (Throwable $exception) {
                $formErrors['general'] = 'No fue posible crear el acta.';
            }
        }

        $formMode = 'create';
        $formData = array_merge($formData, $clean);
    }

    if ($postAction === 'update_acta') {
        $editId = request_int($_POST, 'id', 0);
        [$clean, $formErrors] = Validator::validateActa($_POST);

        if ($editId <= 0) {
            $formErrors['general'] = 'ID de acta invalido.';
        }

        if ($formErrors === []) {
            try {
                $actaService->updateBasic($editId, $clean);
                flash('message', 'Acta actualizada correctamente.', 'success');
                redirect_to_url(page_url_with_query('actas', ['action' => 'detail', 'id' => $editId]));
            } catch (RuntimeException $exception) {
                $formErrors['general'] = $exception->getMessage();
            } catch (Throwable $exception) {
                $formErrors['general'] = 'No fue posible actualizar el acta.';
            }
        }

        $formMode = 'edit';
        $formData = array_merge($formData, $clean, ['id' => $editId]);
    }

    if ($postAction === 'attach_archivo_acta') {
        $actaId = request_int($_POST, 'id', 0);

        if ($actaId <= 0) {
            flash('message', 'ID de acta invalido.', 'danger');
            redirect_to_url($returnUrl);
        }

        try {
            $actaService->replaceAttachment($actaId, $_FILES['archivo_adjunto'] ?? []);
            flash('message', 'Archivo adjunto actualizado correctamente.', 'success');
        } catch (InvalidArgumentException $exception) {
            flash('message', $exception->getMessage(), 'warning');
        } catch (RuntimeException $exception) {
            flash('message', $exception->getMessage(), 'danger');
        } catch (Throwable $exception) {
            flash('message', 'No fue posible adjuntar el archivo del acta.', 'danger');
        }

        redirect_to_url(page_url_with_query('actas', ['action' => 'detail', 'id' => $actaId]) . '#archivo-acta');
    }
}

if ($action === 'edit' && $requestedId > 0 && $formMode !== 'edit') {
    $acta = $actaService->findById($requestedId);

    if ($acta === null) {
        flash('message', 'El acta solicitada no existe.', 'warning');
        redirect_to_url($buildListUrl($search));
    }

    $formMode = 'edit';
    $formData = [
        'id' => (int) $acta['id'],
        'nombre_o_objetivo' => (string) $acta['nombre_o_objetivo'],
        'responsable' => (string) $acta['responsable'],
        'lugar' => (string) $acta['lugar'],
    ];
}

if ($action === 'detail' && $requestedId > 0) {
    $detailActa = $actaService->findById($requestedId);

    if ($detailActa === null) {
        flash('message', 'El acta solicitada no existe.', 'warning');
        redirect_to_url($buildListUrl($search));
    }
}

$actas = $actaService->list($search);
$totalActas = count($actas);
?>

<section class="row g-3">
    <div class="col-xl-4">
        <div class="card-clean p-4">
            <h1 class="h5 mb-1"><?= $formMode === 'edit' ? 'Editar acta' : 'Nueva acta'; ?></h1>
            <p class="text-muted small mb-3">Registre el acta y, si ya lo tiene, adjunte el soporte en PDF, DOC o DOCX.</p>

            <?php if (isset($formErrors['general'])): ?>
                <div class="alert alert-danger"><?= e($formErrors['general']); ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                <?= csrf_field(); ?>
                <input type="hidden" name="form_action" value="<?= $formMode === 'edit' ? 'update_acta' : 'create_acta'; ?>">
                <input type="hidden" name="return_q" value="<?= e($search); ?>">
                <?php if ($formMode === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= e((string) $formData['id']); ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="nombre_o_objetivo" class="form-label">Nombre u objetivo</label>
                    <textarea id="nombre_o_objetivo" name="nombre_o_objetivo" class="form-control <?= isset($formErrors['nombre_o_objetivo']) ? 'is-invalid' : ''; ?>" rows="3" maxlength="200" required><?= e((string) $formData['nombre_o_objetivo']); ?></textarea>
                    <div class="invalid-feedback"><?= e($formErrors['nombre_o_objetivo'] ?? 'Este campo es obligatorio.'); ?></div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="responsable" class="form-label">Responsable</label>
                        <input id="responsable" name="responsable" type="text" class="form-control <?= isset($formErrors['responsable']) ? 'is-invalid' : ''; ?>" maxlength="150" required value="<?= e((string) $formData['responsable']); ?>">
                        <div class="invalid-feedback"><?= e($formErrors['responsable'] ?? 'Este campo es obligatorio.'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="lugar" class="form-label">Lugar</label>
                        <input id="lugar" name="lugar" type="text" class="form-control <?= isset($formErrors['lugar']) ? 'is-invalid' : ''; ?>" maxlength="150" required value="<?= e((string) $formData['lugar']); ?>">
                        <div class="invalid-feedback"><?= e($formErrors['lugar'] ?? 'Este campo es obligatorio.'); ?></div>
                    </div>
                </div>

                <?php if ($formMode === 'create'): ?>
                    <div class="mt-3">
                        <label for="archivo_adjunto" class="form-label">Adjunto inicial</label>
                        <input id="archivo_adjunto" name="archivo_adjunto" type="file" class="form-control <?= isset($formErrors['archivo_adjunto']) ? 'is-invalid' : ''; ?>" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                        <div class="form-text">Opcional. Formatos permitidos: PDF, DOC y DOCX. Tamano maximo: <?= e($maxUploadSizeMb); ?> MB.</div>
                        <div class="invalid-feedback"><?= e($formErrors['archivo_adjunto'] ?? 'Revise el archivo seleccionado.'); ?></div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light border mt-3 mb-0">
                        El archivo adjunto se administra desde el detalle del acta para evitar reemplazos accidentales.
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-floppy-disk me-1"></i><?= $formMode === 'edit' ? 'Actualizar' : 'Guardar'; ?></button>
                    <?php if ($formMode === 'edit'): ?>
                        <a href="<?= e(page_url_with_query('actas', ['action' => 'detail', 'id' => (int) $formData['id']])); ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i>Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card-clean p-4 mb-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">Listado de actas</h2>
                    <p class="small text-muted mb-0">Consulte consecutivo, responsable, lugar y estado del archivo adjunto.</p>
                </div>
                <span class="badge text-bg-light border">Total: <?= e((string) $totalActas); ?></span>
            </div>

            <form method="get" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="page" value="actas">
                <div class="col-md-8">
                    <label for="q" class="form-label">Buscar acta</label>
                    <input id="q" name="q" type="text" class="form-control" maxlength="120" value="<?= e($search); ?>" placeholder="Busque por consecutivo, objetivo, responsable o lugar">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="fa-solid fa-magnifying-glass me-1"></i>Buscar</button>
                    <a href="<?= e(page_url('actas')); ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i>Limpiar</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Consecutivo</th>
                            <th>Nombre u objetivo</th>
                            <th>Responsable</th>
                            <th>Lugar</th>
                            <th>Adjunto</th>
                            <th>Actualizado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($actas === []): ?>
                            <tr>
                                <td colspan="7" class="py-4">
                                    <div class="empty-state-card justify-content-center">
                                        <i class="fa-regular fa-folder-open"></i>
                                        <span>No hay actas registradas con ese criterio.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($actas as $acta): ?>
                                <?php $hasAttachment = $actaService->hasAttachment($acta); ?>
                                <tr>
                                    <td><span class="badge text-bg-dark"><?= e((string) $acta['consecutivo']); ?></span></td>
                                    <td>
                                        <strong><?= e((string) $acta['nombre_o_objetivo']); ?></strong>
                                        <?php if ($hasAttachment): ?>
                                            <div class="small text-muted mt-1"><?= e((string) $acta['nombre_archivo_original']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e((string) $acta['responsable']); ?></td>
                                    <td><?= e((string) $acta['lugar']); ?></td>
                                    <td>
                                        <span class="badge <?= $hasAttachment ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                            <?= $hasAttachment ? 'Con archivo' : 'Sin archivo'; ?>
                                        </span>
                                    </td>
                                    <td><?= e($formatDateTime((string) $acta['fecha_actualizacion'])); ?></td>
                                    <td class="text-end actions-col">
                                        <a href="<?= e(page_url_with_query('actas', ['action' => 'detail', 'id' => (int) $acta['id'], 'q' => $search])); ?>" class="btn btn-sm btn-outline-dark"><i class="fa-solid fa-eye me-1"></i>Ver</a>
                                        <a href="<?= e(page_url_with_query('actas', ['action' => 'edit', 'id' => (int) $acta['id'], 'q' => $search])); ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen-to-square me-1"></i>Editar</a>
                                        <?php if ($hasAttachment): ?>
                                            <a href="<?= e($buildDownloadUrl($acta)); ?>" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-download me-1"></i>Descargar</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($detailActa !== null): ?>
            <?php $detailHasAttachment = $actaService->hasAttachment($detailActa); ?>
            <div class="card-clean p-4 acta-detail-card">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
                    <div>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="badge text-bg-dark"><?= e((string) $detailActa['consecutivo']); ?></span>
                            <span class="badge <?= $detailHasAttachment ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                <?= $detailHasAttachment ? 'Archivo adjunto disponible' : 'Sin archivo adjunto'; ?>
                            </span>
                        </div>
                        <h3 class="h4 mb-1"><?= e((string) $detailActa['nombre_o_objetivo']); ?></h3>
                        <p class="text-muted mb-0">Detalle del acta con edicion basica, control de adjuntos y descarga segura.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= e(page_url_with_query('actas', ['action' => 'edit', 'id' => (int) $detailActa['id'], 'q' => $search])); ?>" class="btn btn-outline-primary"><i class="fa-solid fa-pen-to-square me-1"></i>Editar datos</a>
                        <?php if ($detailHasAttachment): ?>
                            <a href="<?= e($buildDownloadUrl($detailActa)); ?>" class="btn btn-outline-success"><i class="fa-solid fa-download me-1"></i>Descargar archivo</a>
                        <?php endif; ?>
                        <a href="<?= e($buildListUrl($search)); ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i>Cerrar</a>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="summary-card p-3 h-100">
                            <small class="text-muted d-block mb-1">Responsable</small>
                            <div class="fw-semibold"><?= e((string) $detailActa['responsable']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card p-3 h-100">
                            <small class="text-muted d-block mb-1">Lugar</small>
                            <div class="fw-semibold"><?= e((string) $detailActa['lugar']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card p-3 h-100">
                            <small class="text-muted d-block mb-1">Creada</small>
                            <div class="fw-semibold"><?= e($formatDateTime((string) $detailActa['fecha_creacion'])); ?></div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="border rounded p-3 h-100 bg-light-subtle">
                            <h4 class="h6 mb-2">Nombre u objetivo registrado</h4>
                            <p class="mb-0"><?= nl2br(e((string) $detailActa['nombre_o_objetivo'])); ?></p>
                        </div>
                    </div>
                    <div id="archivo-acta" class="col-lg-5">
                        <div class="acta-attachment-panel h-100">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                <div>
                                    <h4 class="h6 mb-1">Archivo adjunto</h4>
                                    <p class="small text-muted mb-0">Suba o reemplace el archivo del acta con validacion en servidor.</p>
                                </div>
                                <span class="badge <?= $detailHasAttachment ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                    <?= $detailHasAttachment ? 'Activo' : 'Pendiente'; ?>
                                </span>
                            </div>

                            <?php if ($detailHasAttachment): ?>
                                <div class="alert alert-light border small">
                                    <div><strong>Archivo:</strong> <?= e((string) $detailActa['nombre_archivo_original']); ?></div>
                                    <div class="mt-1"><strong>Tipo MIME:</strong> <?= e((string) $detailActa['tipo_mime']); ?></div>
                                    <div class="mt-1"><strong>Ultima actualizacion:</strong> <?= e($formatDateTime((string) $detailActa['fecha_actualizacion'])); ?></div>
                                </div>
                            <?php else: ?>
                                <div class="attendance-empty-state mb-3">
                                    <i class="fa-regular fa-file-lines"></i>
                                    <span>Esta acta aun no tiene archivo adjunto.</span>
                                </div>
                            <?php endif; ?>

                            <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <?= csrf_field(); ?>
                                <input type="hidden" name="form_action" value="attach_archivo_acta">
                                <input type="hidden" name="id" value="<?= e((string) $detailActa['id']); ?>">
                                <input type="hidden" name="return_q" value="<?= e($search); ?>">

                                <div class="mb-3">
                                    <label for="archivo_adjunto_detalle" class="form-label"><?= $detailHasAttachment ? 'Reemplazar archivo' : 'Adjuntar archivo'; ?></label>
                                    <input id="archivo_adjunto_detalle" name="archivo_adjunto" type="file" class="form-control" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
                                    <div class="form-text">Formatos permitidos: PDF, DOC y DOCX. Tamano maximo: <?= e($maxUploadSizeMb); ?> MB.</div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-paperclip me-1"></i><?= $detailHasAttachment ? 'Reemplazar adjunto' : 'Adjuntar archivo'; ?></button>
                                    <?php if ($detailHasAttachment): ?>
                                        <a href="<?= e($buildDownloadUrl($detailActa)); ?>" class="btn btn-outline-success"><i class="fa-solid fa-download me-1"></i>Descargar</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
