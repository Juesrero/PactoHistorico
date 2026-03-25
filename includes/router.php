<?php
declare(strict_types=1);

function routes(): array
{
    return [
        'dashboard' => [
            'title' => 'Dashboard',
            'file' => 'pages/dashboard.php',
        ],
        'personas' => [
            'title' => 'Personas',
            'file' => 'pages/personas.php',
        ],
        'reuniones' => [
            'title' => 'Reuniones',
            'file' => 'pages/reuniones.php',
        ],
        'asistencias' => [
            'title' => 'Asistencias',
            'file' => 'pages/asistencias.php',
        ],
        '404' => [
            'title' => 'Pagina no encontrada',
            'file' => 'pages/404.php',
        ],
    ];
}

function resolve_route(string $page): array
{
    $map = routes();

    if (!isset($map[$page])) {
        http_response_code(404);
        $notFound = $map['404'];
        $notFound['key'] = '404';
        return $notFound;
    }

    $route = $map[$page];
    $route['key'] = $page;
    return $route;
}
