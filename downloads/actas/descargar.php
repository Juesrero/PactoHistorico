<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$actaService = new ActaService(db());
$actaId = request_int($_GET, 'id', 0);

if ($actaId <= 0) {
    downloads_invalid_redirect('El acta solicitada es invalida.');
}

$acta = $actaService->findById($actaId);
if ($acta === null) {
    downloads_invalid_redirect('El acta solicitada no existe.');
}

if (!$actaService->hasAttachment($acta)) {
    downloads_invalid_redirect('El acta seleccionada no tiene archivo adjunto.', [
        'action' => 'detail',
        'id' => $actaId,
    ]);
}

$absolutePath = $actaService->resolveStoredPath((string) $acta['ruta_archivo']);
if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
    downloads_invalid_redirect('No fue posible localizar el archivo adjunto del acta.', [
        'action' => 'detail',
        'id' => $actaId,
    ]);
}

$downloadName = $actaService->buildDownloadFilename($acta);
$mime = trim((string) ($acta['tipo_mime'] ?? ''));
if ($mime === '') {
    $mime = 'application/octet-stream';
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . addcslashes($downloadName, "\"\\") . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('Content-Length: ' . (string) filesize($absolutePath));
header('Cache-Control: private, no-transform, no-store, must-revalidate');
header('Pragma: public');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

readfile($absolutePath);
exit;
