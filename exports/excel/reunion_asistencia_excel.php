<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$reunionId = export_reunion_id_from_query();
if ($reunionId <= 0) {
    export_invalid_redirect('Parametro de reunion invalido para exportar a Excel.');
}

$exportService = new ExportService(db());
$payload = $exportService->getMeetingWithAttendance($reunionId);
if ($payload === null) {
    export_invalid_redirect('La reunion solicitada no existe para exportar a Excel.');
}

$meeting = $payload['meeting'];
$attendees = $payload['attendees'];
$totalAsistentes = (int) $payload['total_asistentes'];
$totalTestigos = (int) $payload['total_testigos'];

$filename = $exportService->exportFileBaseName($reunionId) . '.xls';
$logoPath = $exportService->resolveLogoPath();
$logoHtml = '';

if ($logoPath !== null) {
    $logoData = @file_get_contents($logoPath);
    if ($logoData !== false) {
        $mime = 'image/png';
        $logoBase64 = base64_encode($logoData);
        $logoHtml = '<img src="data:' . $mime . ';base64,' . $logoBase64 . '" alt="Logo" style="height:58px;">';
    }
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo "\xEF\xBB\xBF";
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= e($filename); ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; }
        .head-wrap { margin-bottom: 14px; }
        .title { font-size: 18px; font-weight: bold; margin: 0 0 8px; }
        .meta { border-collapse: collapse; width: 100%; margin-bottom: 14px; }
        .meta td { border: 1px solid #d1d5db; padding: 6px 8px; font-size: 12px; }
        .meta td.label { background: #f3f4f6; width: 180px; font-weight: bold; }
        table.list { border-collapse: collapse; width: 100%; }
        table.list th, table.list td { border: 1px solid #d1d5db; padding: 7px 8px; font-size: 12px; }
        table.list th { background: #0f4c81; color: #ffffff; font-weight: bold; }
        .totals { margin-top: 12px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="head-wrap">
        <?= $logoHtml; ?>
        <p class="title">Listado de asistencia por reunion</p>
    </div>

    <table class="meta">
        <tr><td class="label">Nombre de la reunion</td><td><?= e((string) $meeting['nombre_reunion']); ?></td></tr>
        <tr><td class="label">Objetivo</td><td><?= e((string) $meeting['objetivo']); ?></td></tr>
        <tr><td class="label">Tipo de reunion</td><td><?= e((string) ($meeting['tipo_reunion'] ?? '')); ?></td></tr>
        <tr><td class="label">Organizacion</td><td><?= e((string) $meeting['organizacion']); ?></td></tr>
        <tr><td class="label">Lugar reunion</td><td><?= e((string) $meeting['lugar_reunion']); ?></td></tr>
        <tr><td class="label">Fecha</td><td><?= e((string) $meeting['fecha']); ?></td></tr>
        <tr><td class="label">Hora</td><td><?= e(substr((string) $meeting['hora'], 0, 5)); ?></td></tr>
    </table>

    <table class="list">
        <thead>
            <tr>
                <th>Nombre y apellido</th>
                <th>Numero documento</th>
                <th>Celular</th>
                <th>Testigo</th>
                <th>Fecha de registro</th>
                <th>Hora de registro</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($attendees === []): ?>
                <tr>
                    <td colspan="6">Sin asistentes registrados.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($attendees as $attendee): ?>
                    <tr>
                        <td><?= e((string) $attendee['nombre_persona']); ?></td>
                        <td><?= e((string) $attendee['numero_documento']); ?></td>
                        <td><?= e((string) $attendee['celular']); ?></td>
                        <td><?= (int) $attendee['es_testigo'] === 1 ? 'Si' : 'No'; ?></td>
                        <td><?= e((string) $attendee['fecha_registro']); ?></td>
                        <td><?= e(substr((string) $attendee['hora_registro'], 0, 5)); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="totals">
        <p><strong>Total asistentes:</strong> <?= e((string) $totalAsistentes); ?></p>
        <p><strong>Total testigos:</strong> <?= e((string) $totalTestigos); ?></p>
    </div>
</body>
</html>