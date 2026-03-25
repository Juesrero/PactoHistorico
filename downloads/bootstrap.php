<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/app.php';
require BASE_PATH . '/config/session.php';
require BASE_PATH . '/config/database.php';
require BASE_PATH . '/includes/helpers.php';
require BASE_PATH . '/services/ActaService.php';

function downloads_root_url(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $root = dirname(dirname(dirname($script)));

    if ($root === '' || $root === '.' || $root === '\\' || $root === '/') {
        return '';
    }

    return rtrim($root, '/');
}

function downloads_actas_url(array $query = []): string
{
    $url = downloads_root_url() . '/index.php?page=actas';

    if ($query === []) {
        return $url;
    }

    return $url . '&' . http_build_query($query);
}

function downloads_invalid_redirect(string $message, array $query = []): void
{
    $_SESSION['flash']['message'] = [
        'message' => $message,
        'type' => 'danger',
    ];

    header('Location: ' . downloads_actas_url($query));
    exit;
}
