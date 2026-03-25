<?php
declare(strict_types=1);

class PersonaService
{
    private PDO $pdo;

    /** @var array<string, string> */
    private array $sortableColumns = [
        'id' => 'id',
        'nombres_apellidos' => 'nombres_apellidos',
        'numero_documento' => 'numero_documento',
        'celular' => 'celular',
        'es_testigo' => 'es_testigo',
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
        if ($search === '') {
            return (int) $this->pdo->query('SELECT COUNT(*) FROM personas')->fetchColumn();
        }

        $sql = 'SELECT COUNT(*)
                FROM personas
                WHERE nombres_apellidos LIKE :search_name
                   OR numero_documento LIKE :search_document
                   OR celular LIKE :search_phone';

        $stmt = $this->pdo->prepare($sql);
        $like = '%' . $search . '%';
        $stmt->execute([
            'search_name' => $like,
            'search_document' => $like,
            'search_phone' => $like,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function listPaginated(string $search, string $sortBy, string $sortDir, int $limit, int $offset): array
    {
        $orderColumn = $this->sortableColumns[$sortBy] ?? 'id';
        $orderDir = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';

        $limit = max(1, $limit);
        $offset = max(0, $offset);

        if ($search === '') {
            $sql = 'SELECT id, nombres_apellidos, numero_documento, celular, es_testigo, created_at
                    FROM personas
                    ORDER BY ' . $orderColumn . ' ' . $orderDir . '
                    LIMIT :limit OFFSET :offset';

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        }

        $sql = 'SELECT id, nombres_apellidos, numero_documento, celular, es_testigo, created_at
                FROM personas
                WHERE nombres_apellidos LIKE :search_name
                   OR numero_documento LIKE :search_document
                   OR celular LIKE :search_phone
                ORDER BY ' . $orderColumn . ' ' . $orderDir . '
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $like = '%' . $search . '%';
        $stmt->bindValue(':search_name', $like, PDO::PARAM_STR);
        $stmt->bindValue(':search_document', $like, PDO::PARAM_STR);
        $stmt->bindValue(':search_phone', $like, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, nombres_apellidos, numero_documento, celular, es_testigo FROM personas WHERE id = :id LIMIT 1');
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
        $sql = 'INSERT INTO personas (nombres_apellidos, numero_documento, celular, es_testigo, created_at, updated_at)
                VALUES (:nombres_apellidos, :numero_documento, :celular, :es_testigo, NOW(), NOW())';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
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
     * @param list<array{nombres_apellidos:string, numero_documento:string, celular:string, es_testigo:int}> $rows
     */
    public function createMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $sql = 'INSERT INTO personas (nombres_apellidos, numero_documento, celular, es_testigo, created_at, updated_at)
                VALUES (:nombres_apellidos, :numero_documento, :celular, :es_testigo, NOW(), NOW())';
        $stmt = $this->pdo->prepare($sql);

        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $stmt->execute([
                    'nombres_apellidos' => $row['nombres_apellidos'],
                    'numero_documento' => $row['numero_documento'],
                    'celular' => $row['celular'],
                    'es_testigo' => $row['es_testigo'],
                ]);
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
                    numero_documento = :numero_documento,
                    celular = :celular,
                    es_testigo = :es_testigo,
                    updated_at = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'nombres_apellidos' => $data['nombres_apellidos'],
            'numero_documento' => $data['numero_documento'],
            'celular' => $data['celular'],
            'es_testigo' => $data['es_testigo'],
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM personas WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
