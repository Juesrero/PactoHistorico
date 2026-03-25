<?php
$errors = [];
$pdo = db();

if (is_post() && (($_POST['form'] ?? '') === 'asistencia')) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('message', 'Token CSRF invalido. Intenta nuevamente.', 'danger');
        redirect_to('asistencias');
    }

    [$clean, $errors] = Validator::validateAsistencia($_POST);

    if ($errors === []) {
        $stmtReunion = $pdo->prepare('SELECT COUNT(*) FROM reuniones WHERE id = :id');
        $stmtReunion->execute(['id' => $clean['reunion_id']]);
        if ((int) $stmtReunion->fetchColumn() === 0) {
            $errors['reunion_id'] = 'La reunion seleccionada no existe.';
        }

        $stmtPersona = $pdo->prepare('SELECT COUNT(*) FROM personas WHERE id = :id');
        $stmtPersona->execute(['id' => $clean['persona_id']]);
        if ((int) $stmtPersona->fetchColumn() === 0) {
            $errors['persona_id'] = 'La persona seleccionada no existe.';
        }
    }

    if ($errors !== []) {
        set_old_input($_POST);
    } else {
        try {
            $sql = 'INSERT INTO asistencias (reunion_id, persona_id, fecha_registro, hora_registro, observacion)
                    VALUES (:reunion_id, :persona_id, :fecha_registro, :hora_registro, :observacion)';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($clean);

            clear_old_input();
            flash('message', 'Asistencia registrada correctamente.', 'success');
            redirect_to('asistencias');
        } catch (PDOException $exception) {
            set_old_input($_POST);
            if ($exception->getCode() === '23000') {
                $errors['general'] = 'No se puede duplicar la asistencia de la misma persona en la misma reunion.';
            } else {
                $errors['general'] = 'Ocurrio un error al registrar la asistencia.';
            }
        }
    }
}

$reuniones = $pdo->query('SELECT id, nombre_reunion, fecha, hora FROM reuniones ORDER BY fecha DESC, hora DESC')->fetchAll();
$personas = $pdo->query('SELECT id, nombres_apellidos, numero_documento FROM personas ORDER BY nombres_apellidos ASC')->fetchAll();

$asistencias = $pdo->query('SELECT a.id, r.nombre_reunion, p.nombres_apellidos, p.numero_documento, a.fecha_registro, a.hora_registro, a.observacion
                            FROM asistencias a
                            INNER JOIN reuniones r ON r.id = a.reunion_id
                            INNER JOIN personas p ON p.id = a.persona_id
                            ORDER BY a.id DESC')->fetchAll();
?>

<section class="row g-3">
    <div class="col-lg-4">
        <div class="card-clean p-4">
            <h1 class="h5 mb-3">Registrar asistencia</h1>

            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger"><?= e($errors['general']); ?></div>
            <?php endif; ?>

            <form method="post" class="needs-validation" novalidate>
                <?= csrf_field(); ?>
                <input type="hidden" name="form" value="asistencia">

                <div class="mb-3">
                    <label class="form-label" for="reunion_id"><i class="fa-solid fa-people-group me-1 text-muted"></i>Reunion</label>
                    <select id="reunion_id" name="reunion_id" class="form-select <?= isset($errors['reunion_id']) ? 'is-invalid' : ''; ?>" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($reuniones as $reunion): ?>
                            <option value="<?= e((string) $reunion['id']); ?>" <?= old('reunion_id') === (string) $reunion['id'] ? 'selected' : ''; ?>>
                                <?= e((string) $reunion['nombre_reunion']); ?> (<?= e((string) $reunion['fecha']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">
                        <?= e($errors['reunion_id'] ?? 'Debe seleccionar una reunion.'); ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="persona_id"><i class="fa-solid fa-user me-1 text-muted"></i>Persona</label>
                    <select id="persona_id" name="persona_id" class="form-select <?= isset($errors['persona_id']) ? 'is-invalid' : ''; ?>" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($personas as $persona): ?>
                            <option value="<?= e((string) $persona['id']); ?>" <?= old('persona_id') === (string) $persona['id'] ? 'selected' : ''; ?>>
                                <?= e((string) $persona['nombres_apellidos']); ?> - <?= e((string) $persona['numero_documento']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">
                        <?= e($errors['persona_id'] ?? 'Debe seleccionar una persona.'); ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="observacion"><i class="fa-solid fa-note-sticky me-1 text-muted"></i>Observacion (opcional)</label>
                    <textarea id="observacion" name="observacion" class="form-control <?= isset($errors['observacion']) ? 'is-invalid' : ''; ?>" rows="2" maxlength="255"><?= e(old('observacion')); ?></textarea>
                    <div class="invalid-feedback">
                        <?= e($errors['observacion'] ?? 'Maximo 255 caracteres.'); ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar asistencia</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card-clean p-4">
            <h2 class="h5 mb-3">Historial de asistencias</h2>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Reunion</th>
                            <th>Persona</th>
                            <th>Documento</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Observacion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($asistencias === []): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No hay asistencias registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($asistencias as $asistencia): ?>
                                <tr>
                                    <td><?= e((string) $asistencia['id']); ?></td>
                                    <td><?= e((string) $asistencia['nombre_reunion']); ?></td>
                                    <td><?= e((string) $asistencia['nombres_apellidos']); ?></td>
                                    <td><?= e((string) $asistencia['numero_documento']); ?></td>
                                    <td><?= e((string) $asistencia['fecha_registro']); ?></td>
                                    <td><?= e(substr((string) $asistencia['hora_registro'], 0, 5)); ?></td>
                                    <td><?= e((string) ($asistencia['observacion'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php clear_old_input(); ?>


