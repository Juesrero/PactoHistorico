<?php
declare(strict_types=1);

define('APP_NAME', 'Control de Asistencia');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));

$scriptDir = isset($_SERVER['SCRIPT_NAME']) ? (string) dirname($_SERVER['SCRIPT_NAME']) : '';
$scriptDir = str_replace('\\', '/', $scriptDir);
$baseUrl = rtrim($scriptDir, '/');
if ($baseUrl === '') {
    $baseUrl = '/';
}
define('BASE_URL', $baseUrl);

define('LOGO_PATH', '/Logo/pacto.png');

date_default_timezone_set('America/Bogota');
