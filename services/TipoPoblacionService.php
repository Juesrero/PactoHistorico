<?php
declare(strict_types=1);

class TipoPoblacionService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listAll(): array
    {
        $sql = 'SELECT tp.id,
                       tp.nombre,
                       tp.descripcion,
                       tp.activo,
                       tp.created_at,
                       tp.updated_at,
                       COUNT(p.id) AS total_personas
                FROM tipos_poblacion tp
                LEFT JOIN personas p ON p.tipo_poblacion_id = tp.id
                GROUP BY tp.id, tp.nombre, tp.descripcion, tp.activo, tp.created_at, tp.updated_at
                ORDER BY tp.activo DESC, tp.nombre ASC';

        return $this->pdo->query($sql)->fetchAll();
    }

    public function listActive(): array
    {
        $stmt = $this->pdo->query('SELECT id, nombre, descripcion, activo FROM tipos_poblacion WHERE activo = 1 ORDER BY nombre ASC');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, nombre, descripcion, activo FROM tipos_poblacion WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function findByName(string $name, bool $onlyActive = false): ?array
    {
        $sql = 'SELECT id, nombre, descripcion, activo
                FROM tipos_poblacion
                WHERE LOWER(nombre) = LOWER(:nombre)';

        if ($onlyActive) {
            $sql .= ' AND activo = 1';
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['nombre' => trim($name)]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function existsName(string $name, ?int $excludeId = null): bool
    {
        $name = trim($name);

        if ($excludeId === null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tipos_poblacion WHERE LOWER(nombre) = LOWER(:nombre)');
            $stmt->execute(['nombre' => $name]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tipos_poblacion WHERE LOWER(nombre) = LOWER(:nombre) AND id <> :id');
            $stmt->execute([
                'nombre' => $name,
                'id' => $excludeId,
            ]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO tipos_poblacion (nombre, descripcion, activo, created_at, updated_at)
                                     VALUES (:nombre, :descripcion, :activo, NOW(), NOW())');
        $stmt->execute([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'],
            'activo' => $data['activo'],
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare('UPDATE tipos_poblacion
                                     SET nombre = :nombre,
                                         descripcion = :descripcion,
                                         activo = :activo,
                                         updated_at = NOW()
                                     WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'],
            'activo' => $data['activo'],
        ]);

        return $stmt->rowCount() > 0;
    }

    public function setActive(int $id, bool $active): bool
    {
        $stmt = $this->pdo->prepare('UPDATE tipos_poblacion SET activo = :activo, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'activo' => $active ? 1 : 0,
        ]);

        return $stmt->rowCount() > 0;
    }
}