<?php
declare(strict_types=1);

class PersonaService
{
    private PDO $pdo;

    /** @var array<string, string> */
    private array $sortableColumns = [
        'id' => 'p.id',
        'nombres' => 'p.nombres',
        'apellidos' => 'p.apellidos',
        'numero_documento' => 'p.numero_documento',
        'celular' => 'p.celular',
        'correo' => 'p.correo',
        'es_testigo' => 'p.es_testigo',
        'es_jurado' => 'p.es_jurado',
        'es_militante' => 'p.es_militante',
        'tipo_poblacion' => 'tp.nombre',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function list(string $search = ''): array
    {
        return $this->listPaginated($search, 'id', 'desc', 1000000, 0);
    }

    public function count(string $search = ''): int
    {
        $sql = 'SELECT COUNT(*)
                FROM personas p
                LEFT JOIN tipos_poblacion tp ON tp.id = p.tipo_poblacion_id';

        $params = [];
        if ($search !== '') {
            $sql .= '
                WHERE p.nombres_apellidos LIKE :search_full_name
                   OR p.nombres LIKE :search_name
                   OR p.apellidos LIKE :search_last_name
                   OR p.numero_documento LIKE :search_document
                   OR p.celular LIKE :search_phone
                   OR p.correo LIKE :search_email
                   OR COALESCE(tp.nombre, \'\') LIKE :search_population';

            $like = '%' . $search . '%';
            $params = [
                'search_full_name' => $like,
                'search_name' => $like,
                'search_last_name' => $like,
                'search_document' => $like,
                'search_phone' => $like,
                'search_email' => $like,
                'search_population' => $like,
            ];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function listPaginated(string $search, string $sortBy, string $sortDir, int $limit, int $offset): array
    {
        $orderColumn = $this->sortableColumns[$sortBy] ?? 'p.id';
        $orderDir = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';

        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $sql = 'SELECT p.id,
                       p.nombres_apellidos,
                       p.nombres,
                       p.apellidos,
                       p.numero_documento,
                       p.genero,
                       p.fecha_nacimiento,
                       p.correo,
                       p.celular,
                       p.direccion,
                       p.tipo_poblacion_id,
                       tp.nombre AS tipo_poblacion_nombre,
                       tp.activo AS tipo_poblacion_activo,
                       p.es_testigo,
                       p.es_jurado,
                       p.es_militante,
                       p.created_at
                FROM personas p
                LEFT JOIN tipos_poblacion tp ON tp.id = p.tipo_poblacion_id';

        $params = [];
        if ($search !== '') {
            $sql .= '
                WHERE p.nombres_apellidos LIKE :search_full_name
                   OR p.nombres LIKE :search_name
                   OR p.apellidos LIKE :search_last_name
                   OR p.numero_documento LIKE :search_document
                   OR p.celular LIKE :search_phone
                   OR p.correo LIKE :search_email
                   OR COALESCE(tp.nombre, \'\') LIKE :search_population';

            $like = '%' . $search . '%';
            $params = [
                'search_full_name' => $like,
                'search_name' => $like,
                'search_last_name' => $like,
                'search_document' => $like,
                'search_phone' => $like,
                'search_email' => $like,
                'search_population' => $like,
            ];
        }

        $sql .= "\n                ORDER BY {$orderColumn} {$orderDir}\n                LIMIT :limit OFFSET :offset";

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
        $stmt = $this->pdo->prepare('SELECT p.id,
                                            p.nombres_apellidos,
                                            p.nombres,
                                            p.apellidos,
                                            p.numero_documento,
                                            p.genero,
                                            p.fecha_nacimiento,
                                            p.correo,
                                            p.celular,
                                            p.direccion,
                                            p.tipo_poblacion_id,
                                            tp.nombre AS tipo_poblacion_nombre,
                                            tp.activo AS tipo_poblacion_activo,
                                            p.es_testigo,
                                            p.es_jurado,
                                            p.es_militante
                                     FROM personas p
                                     LEFT JOIN tipos_poblacion tp ON tp.id = p.tipo_poblacion_id
                                     WHERE p.id = :id
                                     LIMIT 1');
        $stmt->execute(['id' => $id]);
        $persona = $stmt->fetch();

        return $persona !== false ? $persona : null;
    }

    public function documentExists(string $documento, ?int $excludeId = null): bool
    {
        if ($excludeId === null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM personas WHERE numero_documento = :numero_documento');
            $stmt->execute(['numero_documento' => $documento]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM personas WHERE numero_documento = :numero_documento AND id <> :id');
            $stmt->execute([
                'numero_documento' => $documento,
                'id' => $excludeId,
            ]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): void
    {
        $sql = 'INSERT INTO personas (
                    nombres_apellidos,
                    nombres,
                    apellidos,
                    numero_documento,
                    genero,
                    fecha_nacimiento,
                    correo,
                    celular,
                    direccion,
                    tipo_poblacion_id,
                    es_testigo,
                    es_jurado,
                    es_militante,
                    created_at,
                    updated_at
                ) VALUES (
                    :nombres_apellidos,
                    :nombres,
                    :apellidos,
                    :numero_documento,
                    :genero,
                    :fecha_nacimiento,
                    :correo,
                    :celular,
                    :direccion,
                    :tipo_poblacion_id,
                    :es_testigo,
                    :es_jurado,
                    :es_militante,
                    NOW(),
                    NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->persistableData($data));
    }

    /**
     * @param list<string> $documentos
     * @return array<string, true>
     */
    public function existingDocuments(array $documentos): array
    {
        $documentos = array_values(array_unique(array_filter($documentos, static fn ($item): bool => $item !== '')));
        if ($documentos === []) {
            return [];
        }

        $existing = [];
        $chunkSize = 500;

        foreach (array_chunk($documentos, $chunkSize) as $chunk) {
            $placeholders = [];
            $params = [];

            foreach ($chunk as $index => $documento) {
                $key = 'doc' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $documento;
            }

            $sql = 'SELECT numero_documento FROM personas WHERE numero_documento IN (' . implode(', ', $placeholders) . ')';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $documento) {
                $existing[(string) $documento] = true;
            }
        }

        return $existing;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function createMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $sql = 'INSERT INTO personas (
                    nombres_apellidos,
                    nombres,
                    apellidos,
                    numero_documento,
                    genero,
                    fecha_nacimiento,
                    correo,
                    celular,
                    direccion,
                    tipo_poblacion_id,
                    es_testigo,
                    es_jurado,
                    es_militante,
                    created_at,
                    updated_at
                ) VALUES (
                    :nombres_apellidos,
                    :nombres,
                    :apellidos,
                    :numero_documento,
                    :genero,
                    :fecha_nacimiento,
                    :correo,
                    :celular,
                    :direccion,
                    :tipo_poblacion_id,
                    :es_testigo,
                    :es_jurado,
                    :es_militante,
                    NOW(),
                    NOW()
                )';
        $stmt = $this->pdo->prepare($sql);

        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $stmt->execute($this->persistableData($row));
            }

            $this->pdo->commit();
            return count($rows);
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function update(int $id, array $data): bool
    {
        $sql = 'UPDATE personas
                SET nombres_apellidos = :nombres_apellidos,
                    nombres = :nombres,
                    apellidos = :apellidos,
                    numero_documento = :numero_documento,
                    genero = :genero,
                    fecha_nacimiento = :fecha_nacimiento,
                    correo = :correo,
                    celular = :celular,
                    direccion = :direccion,
                    tipo_poblacion_id = :tipo_poblacion_id,
                    es_testigo = :es_testigo,
                    es_jurado = :es_jurado,
                    es_militante = :es_militante,
                    updated_at = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $payload = $this->persistableData($data);
        $payload['id'] = $id;
        $stmt->execute($payload);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM personas WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function persistableData(array $data): array
    {
        return [
            'nombres_apellidos' => (string) ($data['nombres_apellidos'] ?? ''),
            'nombres' => $this->nullableString($data['nombres'] ?? null),
            'apellidos' => $this->nullableString($data['apellidos'] ?? null),
            'numero_documento' => (string) ($data['numero_documento'] ?? ''),
            'genero' => $this->nullableString($data['genero'] ?? null),
            'fecha_nacimiento' => $this->nullableString($data['fecha_nacimiento'] ?? null),
            'correo' => $this->nullableString($data['correo'] ?? null),
            'celular' => (string) ($data['celular'] ?? ''),
            'direccion' => $this->nullableString($data['direccion'] ?? null),
            'tipo_poblacion_id' => isset($data['tipo_poblacion_id']) && (int) $data['tipo_poblacion_id'] > 0 ? (int) $data['tipo_poblacion_id'] : null,
            'es_testigo' => !empty($data['es_testigo']) ? 1 : 0,
            'es_jurado' => !empty($data['es_jurado']) ? 1 : 0,
            'es_militante' => !empty($data['es_militante']) ? 1 : 0,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
