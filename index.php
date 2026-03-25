<?php
declare(strict_types=1);

require __DIR__ . '/config/app.php';
require __DIR__ . '/config/session.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/validator.php';
require __DIR__ . '/includes/router.php';
require __DIR__ . '/services/ExportService.php';
require __DIR__ . '/services/PersonaService.php';
require __DIR__ . '/services/ReunionService.php';
require __DIR__ . '/services/XlsxReader.php';
require __DIR__ . '/services/PersonaImportService.php';

$requestedPage = isset($_GET['page']) ? (string) $_GET['page'] : 'dashboard';
$route = resolve_route($requestedPage);

$currentPage = $route['key'];
$pageTitle = $route['title'] . ' | ' . APP_NAME;

require BASE_PATH . '/includes/layout/header.php';
require BASE_PATH . '/' . $route['file'];
require BASE_PATH . '/includes/layout/footer.php';
