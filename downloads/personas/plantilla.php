<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require BASE_PATH . '/services/TipoPoblacionService.php';
require BASE_PATH . '/services/PersonasTemplateService.php';

try {
    $tipoPoblacionService = new TipoPoblacionService(db());
    $templateService = new PersonasTemplateService($tipoPoblacionService);
    $binary = $templateService->build();
    $filename = $templateService->filename();
} catch (Throwable $exception) {
    $_SESSION['flash']['message'] = [
        'message' => 'No fue posible generar la plantilla de personas.',
        'type' => 'danger',
    ];

    header('Location: ' . downloads_root_url() . '/index.php?page=personas');
    exit;
}

header('Content-Type: ' . $templateService->mimeType());
header('Content-Disposition: attachment; filename="' . addcslashes($filename, "\"\\") . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Length: ' . (string) strlen($binary));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $binary;
exit;
