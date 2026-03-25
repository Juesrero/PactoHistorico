<?php
declare(strict_types=1);

class ReunionService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function list(): array
    {
        $sql = 'SELECT r.id,
                       r.nombre_reunion,
                       r.objetivo,
                       r.organizacion,
                       r.lugar_reunion,
                       r.fecha,
                       r.hora,
                       COUNT(a.id) AS total_asistentes
                FROM reuniones r
                LEFT JOIN asistencias a ON a.reunion_id = r.id
                GROUP BY r.id, r.nombre_reunion, r.objetivo, r.organizacion, r.lugar_reunion, r.fecha, r.hora
                ORDER BY r.fecha DESC, r.hora DESC';

        return $this->pdo->query($sql)->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $sql = 'SELECT r.id,
                       r.nombre_reunion,
                       r.objetivo,
                       r.organizacion,
                       r.lugar_reunion,
                       r.fecha,
                       r.hora,
                       COUNT(a.id) AS total_asistentes,
                       COALESCE(SUM(CASE WHEN p.es_testigo = 1 THEN 1 ELSE 0 END), 0) AS total_testigos
                FROM reuniones r
                LEFT JOIN asistencias a ON a.reunion_id = r.id
                LEFT JOIN personas p ON p.id = a.persona_id
                WHERE r.id = :id
                GROUP BY r.id, r.nombre_reunion, r.objetivo, r.organizacion, r.lugar_reunion, r.fecha, r.hora
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $reunion = $stmt->fetch();

        return $reunion !== false ? $reunion : null;
    }

    public function attendeesByMeeting(int $id): array
    {
        $sql = 'SELECT a.id,
                       a.persona_id,
                       p.nombres_apellidos,
                       p.numero_documento,
                       p.celular,
                       p.es_testigo,
                       a.fecha_registro,
                       a.hora_registro,
                       a.observacion
                FROM asistencias a
                INNER JOIN personas p ON p.id = a.persona_id
                WHERE a.reunion_id = :id
                ORDER BY p.nombres_apellidos ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->fetchAll();
    }

    public function availablePersonsForMeeting(int $reunionId, string $search = ''): array
    {
        $baseSql = 'SELECT p.id,
                           p.nombres_apellidos,
                           p.numero_documento,
                           p.celular,
                           p.es_testigo
                    FROM personas p
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM asistencias a
                        WHERE a.reunion_id = :reunion_id
                          AND a.persona_id = p.id
                    )';

        $params = ['reunion_id' => $reunionId];

        if ($search !== '') {
            $baseSql .= ' AND (
                p.nombres_apellidos LIKE :search_name
                OR p.numero_documento LIKE :search_document
            )';
            $like = '%' . $search . '%';
            $params['search_name'] = $like;
            $params['search_document'] = $like;
        }

        $baseSql .= ' ORDER BY p.nombres_apellidos ASC LIMIT 80';

        $stmt = $this->pdo->prepare($baseSql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function personExists(int $personaId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM personas WHERE id = :id');
        $stmt->execute(['id' => $personaId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function addAttendance(int $reunionId, int $personaId): void
    {
        $sql = 'INSERT INTO asistencias (reunion_id, persona_id, fecha_registro, hora_registro, observacion)
                VALUES (:reunion_id, :persona_id, CURDATE(), CURTIME(), NULL)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'reunion_id' => $reunionId,
            'persona_id' => $personaId,
        ]);
    }

    public function removeAttendance(int $reunionId, int $personaId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM asistencias WHERE reunion_id = :reunion_id AND persona_id = :persona_id');
        $stmt->execute([
            'reunion_id' => $reunionId,
            'persona_id' => $personaId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function create(array $data): void
    {
        $sql = 'INSERT INTO reuniones (nombre_reunion, objetivo, organizacion, lugar_reunion, fecha, hora, created_at, updated_at)
                VALUES (:nombre_reunion, :objetivo, :organizacion, :lugar_reunion, :fecha, :hora, NOW(), NOW())';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
    }

    public function update(int $id, array $data): bool
    {
        $sql = 'UPDATE reuniones
                SET nombre_reunion = :nombre_reunion,
                    objetivo = :objetivo,
                    organizacion = :organizacion,
                    lugar_reunion = :lugar_reunion,
                    fecha = :fecha,
                    hora = :hora,
                    updated_at = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'nombre_reunion' => $data['nombre_reunion'],
            'objetivo' => $data['objetivo'],
            'organizacion' => $data['organizacion'],
            'lugar_reunion' => $data['lugar_reunion'],
            'fecha' => $data['fecha'],
            'hora' => $data['hora'],
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM reuniones WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
