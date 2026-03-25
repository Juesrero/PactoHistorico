<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_url_prefix(): string
{
    return BASE_URL === '/' ? '' : BASE_URL;
}

function url(string $path = ''): string
{
    $prefix = base_url_prefix();

    if ($path === '' || $path === '/') {
        return $prefix . '/';
    }

    return $prefix . '/' . ltrim($path, '/');
}

function page_url(string $page): string
{
    return base_url_prefix() . '/index.php?page=' . urlencode($page);
}

function page_url_with_query(string $page, array $query = []): string
{
    $params = ['page' => $page];

    foreach ($query as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $params[$key] = (string) $value;
    }

    return base_url_prefix() . '/index.php?' . http_build_query($params);
}

function redirect_to(string $page): void
{
    header('Location: ' . page_url($page));
    exit;
}

function redirect_to_url(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $key, string $message, string $type = 'success'): void
{
    $_SESSION['flash'][$key] = [
        'message' => $message,
        'type' => $type,
    ];
}

function get_flash(string $key): ?array
{
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $value = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return is_array($value) ? $value : null;
}

function set_old_input(array $data): void
{
    $_SESSION['old'] = $data;
}

function old(string $key, string $default = ''): string
{
    if (!isset($_SESSION['old']) || !is_array($_SESSION['old'])) {
        return $default;
    }

    return isset($_SESSION['old'][$key]) ? (string) $_SESSION['old'][$key] : $default;
}

function clear_old_input(): void
{
    unset($_SESSION['old']);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

function verify_csrf(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }

    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals((string) $_SESSION['csrf_token'], $token);
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function request_string(array $source, string $key, int $maxLength = 255): string
{
    $value = trim((string) ($source[$key] ?? ''));

    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return $value;
}

function request_int(array $source, string $key, int $default = 0): int
{
    if (!isset($source[$key])) {
        return $default;
    }

    return (int) $source[$key];
}
