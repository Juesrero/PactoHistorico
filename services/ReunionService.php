<?php
declare(strict_types=1);

class ReunionService
{
    private PDO $pdo;
    private const LIST_SORT_MAP = [
        'id' => 'r.id',
        'nombre_reunion' => 'r.nombre_reunion',
        'tipo_reunion' => 'r.tipo_reunion',
        'lugar_reunion' => 'r.lugar_reunion',
        'fecha' => 'r.fecha',
        'hora' => 'r.hora',
        'total_asistentes' => 'total_asistentes',
    ];
    private const ATTENDEE_SORT_MAP = [
        'nombre_persona' => 'nombre_persona',
        'numero_documento' => 'p.numero_documento',
        'celular' => 'p.celular',
        'es_testigo' => 'p.es_testigo',
        'es_jurado' => 'p.es_jurado',
        'fecha_registro' => 'a.fecha_registro',
        'hora_registro' => 'a.hora_registro',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function count(string $search = ''): int
    {
        $sql = 'SELECT COUNT(*)
                FROM reuniones r';
        $params = [];

        if ($search !== '') {
            $sql .= ' WHERE r.nombre_reunion LIKE :search
                      OR r.objetivo LIKE :search
                      OR r.tipo_reunion LIKE :search
                      OR r.organizacion LIKE :search
                      OR r.lugar_reunion LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function listPaginated(string $search, string $sortBy, string $sortDir, int $limit, int $offset): array
    {
        $orderColumn = self::LIST_SORT_MAP[$sortBy] ?? self::LIST_SORT_MAP['fecha'];
        $orderDir = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';

        $sql = 'SELECT r.id,
                       r.nombre_reunion,
                       r.objetivo,
                       r.tipo_reunion,
                       r.organizacion,
                       r.lugar_reunion,
                       r.fecha,
                       r.hora,
                       COUNT(a.id) AS total_asistentes
                FROM reuniones r
                LEFT JOIN asistencias a ON a.reunion_id = r.id';
        $params = [];

        if ($search !== '') {
            $sql .= ' WHERE r.nombre_reunion LIKE :search
                      OR r.objetivo LIKE :search
                      OR r.tipo_reunion LIKE :search
                      OR r.organizacion LIKE :search
                      OR r.lugar_reunion LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= ' GROUP BY r.id, r.nombre_reunion, r.objetivo, r.tipo_reunion, r.organizacion, r.lugar_reunion, r.fecha, r.hora
                  ORDER BY ' . $orderColumn . ' ' . $orderDir;

        if ($orderColumn !== 'r.id') {
            $sql .= ', r.id DESC';
        }

        $sql .= ' LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $sql = 'SELECT r.id,
                       r.nombre_reunion,
                       r.objetivo,
                       r.tipo_reunion,
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
                GROUP BY r.id, r.nombre_reunion, r.objetivo, r.tipo_reunion, r.organizacion, r.lugar_reunion, r.fecha, r.hora
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $reunion = $stmt->fetch();

        return $reunion !== false ? $reunion : null;
    }

    public function countAttendeesByMeeting(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM asistencias WHERE reunion_id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    public function attendeesByMeetingPaginated(int $id, string $sortBy, string $sortDir, int $limit, int $offset): array
    {
        $orderColumn = self::ATTENDEE_SORT_MAP[$sortBy] ?? self::ATTENDEE_SORT_MAP['nombre_persona'];
        $orderDir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        $sql = 'SELECT a.id,
                       a.persona_id,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(" ", p.nombres, p.apellidos)), ""), p.nombres_apellidos) AS nombre_persona,
                       p.nombres,
                       p.apellidos,
                       p.numero_documento,
                       p.celular,
                       p.es_testigo,
                       p.es_jurado,
                       a.fecha_registro,
                       a.hora_registro,
                       a.observacion
                FROM asistencias a
                INNER JOIN personas p ON p.id = a.persona_id
                WHERE a.reunion_id = :id
                ORDER BY ' . $orderColumn . ' ' . $orderDir;

        if ($orderColumn !== 'nombre_persona') {
            $sql .= ', nombre_persona ASC';
        }

        $sql .= ' LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function availablePersonsForMeeting(int $reunionId, string $search = ''): array
    {
        $baseSql = 'SELECT p.id,
                           COALESCE(NULLIF(TRIM(CONCAT_WS(" ", p.nombres, p.apellidos)), ""), p.nombres_apellidos) AS nombre_persona,
                           p.nombres,
                           p.apellidos,
                           p.numero_documento,
                           p.celular,
                           p.es_testigo,
                           p.es_jurado
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
                p.numero_documento LIKE :search_document
                OR COALESCE(NULLIF(TRIM(p.nombres), ""), "") LIKE :search_names
                OR COALESCE(NULLIF(TRIM(p.apellidos), ""), "") LIKE :search_last_names
                OR COALESCE(NULLIF(TRIM(CONCAT_WS(" ", p.nombres, p.apellidos)), ""), p.nombres_apellidos) LIKE :search_full_name
                OR p.nombres_apellidos LIKE :search_legacy_name
            )';
            $like = '%' . $search . '%';
            $params['search_document'] = $like;
            $params['search_names'] = $like;
            $params['search_last_names'] = $like;
            $params['search_full_name'] = $like;
            $params['search_legacy_name'] = $like;
        }

        $baseSql .= ' ORDER BY COALESCE(NULLIF(TRIM(p.apellidos), ""), p.nombres_apellidos) ASC,
                               COALESCE(NULLIF(TRIM(p.nombres), ""), p.nombres_apellidos) ASC
                      LIMIT 80';

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
        $sql = 'INSERT INTO reuniones (nombre_reunion, objetivo, tipo_reunion, organizacion, lugar_reunion, fecha, hora, created_at, updated_at)
                VALUES (:nombre_reunion, :objetivo, :tipo_reunion, :organizacion, :lugar_reunion, :fecha, :hora, NOW(), NOW())';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
    }

    public function update(int $id, array $data): bool
    {
        $sql = 'UPDATE reuniones
                SET nombre_reunion = :nombre_reunion,
                    objetivo = :objetivo,
                    tipo_reunion = :tipo_reunion,
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
            'tipo_reunion' => $data['tipo_reunion'],
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
