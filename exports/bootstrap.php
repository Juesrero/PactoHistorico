<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/app.php';
require BASE_PATH . '/config/session.php';
require BASE_PATH . '/config/database.php';
require BASE_PATH . '/includes/helpers.php';
require BASE_PATH . '/services/ExportService.php';

function export_reuniones_url(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $root = dirname(dirname(dirname(str_replace('\\', '/', $script))));

    if ($root === '' || $root === '.' || $root === '\\' || $root === '/') {
        $root = '';
    }

    return rtrim($root, '/') . '/index.php?page=reuniones';
}

function export_reunion_id_from_query(): int
{
    return request_int($_GET, 'reunion_id', 0);
}

function export_invalid_redirect(string $message): void
{
    $_SESSION['flash']['message'] = [
        'message' => $message,
        'type' => 'danger',
    ];

    header('Location: ' . export_reuniones_url());
    exit;
}
