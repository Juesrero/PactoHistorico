<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbHost = '127.0.0.1';
    $dbPort = '3306';
    $dbName = 'pacto_asistencia';
    $dbUser = 'root';
    $dbPass = '';

    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    } catch (PDOException $exception) {
        throw new RuntimeException('No se pudo conectar a la base de datos: ' . $exception->getMessage());
    }

    return $pdo;
}
