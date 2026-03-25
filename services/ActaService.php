<?php
declare(strict_types=1);

class ActaService
{
    private PDO $pdo;
    private string $storageRoot;
    private ?array $counterSchema = null;
    private ?array $actasSchema = null;
    private ?array $actaAdjuntosSchema = null;

    private const COUNTER_KEY = 'actas';
    private const MAX_FILE_SIZE = 10485760;
    private const ALLOWED_MIME_TYPES = [
        'pdf' => [
            'application/pdf',
        ],
        'doc' => [
            'application/msword',
            'application/doc',
            'application/vnd.ms-word',
            'application/vnd.msword',
            'application/octet-stream',
        ],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
        ],
    ];

    public function __construct(PDO $pdo, ?string $storageRoot = null)
    {
        $this->pdo = $pdo;
        $this->storageRoot = $storageRoot !== null
            ? rtrim($storageRoot, "\\/")
            : BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'actas';
    }

    public function list(string $search = ''): array
    {
        $sql = 'SELECT *
                FROM (' . $this->buildBaseSelectSql() . ') actas_view';

        $params = [];

        if ($search !== '') {
            $sql .= ' WHERE consecutivo LIKE :search
                      OR nombre_o_objetivo LIKE :search
                      OR responsable LIKE :search
                      OR lugar LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY fecha_actualizacion DESC, id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT *
                                     FROM (' . $this->buildBaseSelectSql() . ') actas_view
                                     WHERE id = :id
                                     LIMIT 1');
        $stmt->execute(['id' => $id]);
        $acta = $stmt->fetch();

        return $acta !== false ? $acta : null;
    }

    public function create(array $data, ?array $uploadedFile = null): int
    {
        $this->assertWritableSchema();

        $fileInfo = null;
        if ($this->hasUploadedFile($uploadedFile)) {
            $fileInfo = $this->validateUploadedFile($uploadedFile);
        }

        $storedFile = null;

        $this->pdo->beginTransaction();

        try {
            $consecutivo = $this->generateNextConsecutivo();

            if ($fileInfo !== null && $uploadedFile !== null) {
                $storedFile = $this->storeUploadedFile($uploadedFile, $fileInfo, $consecutivo);
            }

            $stmt = $this->pdo->prepare('INSERT INTO actas (
                                            consecutivo,
                                            nombre_o_objetivo,
                                            responsable,
                                            lugar,
                                            nombre_archivo_original,
                                            ruta_archivo,
                                            tipo_mime,
                                            fecha_creacion,
                                            fecha_actualizacion
                                         ) VALUES (
                                            :consecutivo,
                                            :nombre_o_objetivo,
                                            :responsable,
                                            :lugar,
                                            :nombre_archivo_original,
                                            :ruta_archivo,
                                            :tipo_mime,
                                            NOW(),
                                            NOW()
                                         )');
            $stmt->execute([
                'consecutivo' => $consecutivo,
                'nombre_o_objetivo' => $data['nombre_o_objetivo'],
                'responsable' => $data['responsable'],
                'lugar' => $data['lugar'],
                'nombre_archivo_original' => $storedFile['original_name'] ?? null,
                'ruta_archivo' => $storedFile['relative_path'] ?? null,
                'tipo_mime' => $storedFile['mime'] ?? null,
            ]);

            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();

            return $id;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($storedFile !== null) {
                $this->deleteStoredFile($storedFile['relative_path']);
            }

            throw $exception;
        }
    }

    public function updateBasic(int $id, array $data): bool
    {
        $this->assertWritableSchema();

        $stmt = $this->pdo->prepare('UPDATE actas
                                     SET nombre_o_objetivo = :nombre_o_objetivo,
                                         responsable = :responsable,
                                         lugar = :lugar,
                                         fecha_actualizacion = NOW()
                                     WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'nombre_o_objetivo' => $data['nombre_o_objetivo'],
            'responsable' => $data['responsable'],
            'lugar' => $data['lugar'],
        ]);

        return $stmt->rowCount() > 0;
    }

    public function replaceAttachment(int $id, array $uploadedFile): bool
    {
        $this->assertWritableSchema();

        $acta = $this->findById($id);
        if ($acta === null) {
            throw new RuntimeException('El acta seleccionada no existe.');
        }

        if (!$this->hasUploadedFile($uploadedFile)) {
            throw new InvalidArgumentException('Debe seleccionar un archivo PDF, DOC o DOCX.');
        }

        $fileInfo = $this->validateUploadedFile($uploadedFile);
        $storedFile = $this->storeUploadedFile($uploadedFile, $fileInfo, (string) $acta['consecutivo']);
        $previousPath = (string) ($acta['ruta_archivo'] ?? '');

        try {
            $stmt = $this->pdo->prepare('UPDATE actas
                                         SET nombre_archivo_original = :nombre_archivo_original,
                                             ruta_archivo = :ruta_archivo,
                                             tipo_mime = :tipo_mime,
                                             fecha_actualizacion = NOW()
                                         WHERE id = :id');
            $stmt->execute([
                'id' => $id,
                'nombre_archivo_original' => $storedFile['original_name'],
                'ruta_archivo' => $storedFile['relative_path'],
                'tipo_mime' => $storedFile['mime'],
            ]);

            if ($previousPath !== '' && $previousPath !== $storedFile['relative_path']) {
                $this->deleteStoredFile($previousPath);
            }

            return $stmt->rowCount() > 0;
        } catch (Throwable $exception) {
            $this->deleteStoredFile($storedFile['relative_path']);
            throw $exception;
        }
    }

    public function hasAttachment(array $acta): bool
    {
        return trim((string) ($acta['ruta_archivo'] ?? '')) !== '';
    }

    public function resolveStoredPath(?string $relativePath): ?string
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '') {
            return null;
        }

        $root = $this->storageRootRealPath();
        $candidate = $this->storageRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $realCandidate = realpath($candidate);

        if ($realCandidate === false || !str_starts_with($realCandidate, $root)) {
            return null;
        }

        return $realCandidate;
    }

    public function buildDownloadFilename(array $acta): string
    {
        $original = trim((string) ($acta['nombre_archivo_original'] ?? ''));
        $consecutivo = (string) ($acta['consecutivo'] ?? 'ACTA');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $extension = $extension !== '' ? $extension : 'bin';

        $baseName = $original !== ''
            ? pathinfo($original, PATHINFO_FILENAME)
            : $consecutivo;

        $safeBaseName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $baseName) ?? '';
        $safeBaseName = trim($safeBaseName, '._-');
        if ($safeBaseName === '') {
            $safeBaseName = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $consecutivo) ?? 'acta');
        }

        return $safeBaseName . '.' . $extension;
    }

    public function maxUploadSize(): int
    {
        return self::MAX_FILE_SIZE;
    }

    private function buildBaseSelectSql(): string
    {
        $actasColumns = $this->actasSchema();
        $hasLegacyMeeting = isset($actasColumns['reunion_id']);
        $hasCurrentName = isset($actasColumns['nombre_o_objetivo']);
        $hasCurrentResponsible = isset($actasColumns['responsable']);
        $hasCurrentPlace = isset($actasColumns['lugar']);
        $hasCurrentOriginalFile = isset($actasColumns['nombre_archivo_original']);
        $hasCurrentFilePath = isset($actasColumns['ruta_archivo']);
        $hasCurrentMime = isset($actasColumns['tipo_mime']);
        $hasCurrentCreatedAt = isset($actasColumns['fecha_creacion']);
        $hasCurrentUpdatedAt = isset($actasColumns['fecha_actualizacion']);
        $hasLegacyTitle = isset($actasColumns['titulo']);
        $hasLegacyCreatedAt = isset($actasColumns['created_at']);
        $hasLegacyUpdatedAt = isset($actasColumns['updated_at']);

        $joinReunion = $hasLegacyMeeting && (!$hasCurrentResponsible || !$hasCurrentPlace);
        $joinAdjunto = (!$hasCurrentOriginalFile || !$hasCurrentFilePath || !$hasCurrentMime) && $this->hasActaAdjuntosSupport();

        $nameExpr = $hasCurrentName
            ? 'COALESCE(NULLIF(TRIM(a.nombre_o_objetivo), ""), ' . ($hasLegacyTitle ? 'NULLIF(TRIM(a.titulo), ""), ' : '') . 'CONCAT("Acta ", a.consecutivo))'
            : ($hasLegacyTitle ? 'COALESCE(NULLIF(TRIM(a.titulo), ""), CONCAT("Acta ", a.consecutivo))' : 'CONCAT("Acta ", a.consecutivo)');

        $responsableExpr = $hasCurrentResponsible
            ? 'COALESCE(NULLIF(TRIM(a.responsable), ""), ' . ($joinReunion ? 'NULLIF(TRIM(r.organizacion), ""), ' : '') . '"Pendiente")'
            : ($joinReunion ? 'COALESCE(NULLIF(TRIM(r.organizacion), ""), "Pendiente")' : '"Pendiente"');

        $lugarExpr = $hasCurrentPlace
            ? 'COALESCE(NULLIF(TRIM(a.lugar), ""), ' . ($joinReunion ? 'NULLIF(TRIM(r.lugar_reunion), ""), ' : '') . '"Pendiente")'
            : ($joinReunion ? 'COALESCE(NULLIF(TRIM(r.lugar_reunion), ""), "Pendiente")' : '"Pendiente"');

        $originalFileExpr = $hasCurrentOriginalFile
            ? 'a.nombre_archivo_original'
            : ($joinAdjunto ? 'adj.nombre_original' : 'NULL');

        $filePathExpr = $hasCurrentFilePath
            ? 'a.ruta_archivo'
            : ($joinAdjunto ? 'adj.ruta_archivo' : 'NULL');

        $mimeExpr = $hasCurrentMime
            ? 'a.tipo_mime'
            : ($joinAdjunto ? 'adj.mime_type' : 'NULL');

        $createdExpr = $hasCurrentCreatedAt
            ? 'a.fecha_creacion'
            : ($hasLegacyCreatedAt ? 'a.created_at' : 'NOW()');

        $updatedExpr = $hasCurrentUpdatedAt
            ? 'a.fecha_actualizacion'
            : ($hasLegacyUpdatedAt ? 'a.updated_at' : $createdExpr);

        $sql = 'SELECT a.id,
                       a.consecutivo,
                       ' . $nameExpr . ' AS nombre_o_objetivo,
                       ' . $responsableExpr . ' AS responsable,
                       ' . $lugarExpr . ' AS lugar,
                       ' . $originalFileExpr . ' AS nombre_archivo_original,
                       ' . $filePathExpr . ' AS ruta_archivo,
                       ' . $mimeExpr . ' AS tipo_mime,
                       ' . $createdExpr . ' AS fecha_creacion,
                       ' . $updatedExpr . ' AS fecha_actualizacion
                FROM actas a';

        if ($joinReunion) {
            $sql .= ' LEFT JOIN reuniones r ON r.id = a.reunion_id';
        }

        if ($joinAdjunto) {
            $sql .= ' LEFT JOIN (
                        SELECT aa.acta_id,
                               aa.nombre_original,
                               aa.ruta_archivo,
                               aa.mime_type
                        FROM acta_adjuntos aa
                        INNER JOIN (
                            SELECT acta_id, MAX(id) AS max_id
                            FROM acta_adjuntos
                            GROUP BY acta_id
                        ) latest ON latest.max_id = aa.id
                      ) adj ON adj.acta_id = a.id';
        }

        return $sql;
    }

    private function assertWritableSchema(): void
    {
        $requiredColumns = [
            'nombre_o_objetivo',
            'responsable',
            'lugar',
            'nombre_archivo_original',
            'ruta_archivo',
            'tipo_mime',
            'fecha_creacion',
            'fecha_actualizacion',
        ];

        $actasColumns = $this->actasSchema();
        foreach ($requiredColumns as $column) {
            if (!isset($actasColumns[$column])) {
                throw new RuntimeException('La tabla actas aun usa el esquema anterior. Ejecute nuevamente database/module_actas_create.sql actualizado.');
            }
        }

        $consecutivoType = strtolower((string) ($actasColumns['consecutivo']['data_type'] ?? ''));
        if (!in_array($consecutivoType, ['varchar', 'char'], true)) {
            throw new RuntimeException('La columna actas.consecutivo aun no esta ajustada. Ejecute nuevamente database/module_actas_create.sql actualizado.');
        }
    }

    private function generateNextConsecutivo(): string
    {
        $counterSchema = $this->resolveCounterSchema();
        $keyColumn = $counterSchema['key'];
        $updatedColumn = $counterSchema['updated'];

        $insertColumns = $keyColumn . ', ultimo_numero';
        $insertValues = ':counter_key, 0';
        $duplicateUpdate = 'ultimo_numero = ultimo_numero';

        if ($updatedColumn !== null) {
            $insertColumns .= ', ' . $updatedColumn;
            $insertValues .= ', NOW()';
            $duplicateUpdate = $updatedColumn . ' = ' . $updatedColumn;
        }

        $seedSql = 'INSERT INTO consecutivos (' . $insertColumns . ')
                    VALUES (' . $insertValues . ')
                    ON DUPLICATE KEY UPDATE ' . $duplicateUpdate;
        $seedStmt = $this->pdo->prepare($seedSql);
        $seedStmt->execute(['counter_key' => self::COUNTER_KEY]);

        $stmt = $this->pdo->prepare('SELECT ultimo_numero
                                     FROM consecutivos
                                     WHERE ' . $keyColumn . ' = :counter_key
                                     FOR UPDATE');
        $stmt->execute(['counter_key' => self::COUNTER_KEY]);
        $current = (int) $stmt->fetchColumn();
        $next = $current + 1;

        $updateSql = 'UPDATE consecutivos
                      SET ultimo_numero = :ultimo_numero';
        if ($updatedColumn !== null) {
            $updateSql .= ', ' . $updatedColumn . ' = NOW()';
        }
        $updateSql .= ' WHERE ' . $keyColumn . ' = :counter_key';

        $update = $this->pdo->prepare($updateSql);
        $update->execute([
            'counter_key' => self::COUNTER_KEY,
            'ultimo_numero' => $next,
        ]);

        return sprintf('ACTA-%06d', $next);
    }

    private function resolveCounterSchema(): array
    {
        if ($this->counterSchema !== null) {
            return $this->counterSchema;
        }

        $stmt = $this->pdo->query("SELECT column_name
                                   FROM information_schema.columns
                                   WHERE table_schema = DATABASE()
                                     AND table_name = 'consecutivos'");
        $columns = array_map(
            static fn (mixed $value): string => (string) $value,
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );

        if ($columns === []) {
            throw new RuntimeException('No existe la tabla de consecutivos. Ejecute primero database/module_actas_create.sql.');
        }

        $keyColumn = null;
        if (in_array('modulo', $columns, true)) {
            $keyColumn = 'modulo';
        } elseif (in_array('clave', $columns, true)) {
            $keyColumn = 'clave';
        }

        if ($keyColumn === null) {
            throw new RuntimeException('La tabla consecutivos no tiene una columna compatible para generar el consecutivo de actas.');
        }

        $updatedColumn = null;
        if (in_array('updated_at', $columns, true)) {
            $updatedColumn = 'updated_at';
        } elseif (in_array('fecha_actualizacion', $columns, true)) {
            $updatedColumn = 'fecha_actualizacion';
        }

        $this->counterSchema = [
            'key' => $keyColumn,
            'updated' => $updatedColumn,
        ];

        return $this->counterSchema;
    }

    private function actasSchema(): array
    {
        if ($this->actasSchema !== null) {
            return $this->actasSchema;
        }

        $this->actasSchema = $this->loadTableSchema('actas');
        return $this->actasSchema;
    }

    private function actaAdjuntosSchema(): array
    {
        if ($this->actaAdjuntosSchema !== null) {
            return $this->actaAdjuntosSchema;
        }

        $this->actaAdjuntosSchema = $this->loadTableSchema('acta_adjuntos');
        return $this->actaAdjuntosSchema;
    }

    private function hasActaAdjuntosSupport(): bool
    {
        $schema = $this->actaAdjuntosSchema();

        return isset($schema['acta_id'], $schema['nombre_original'], $schema['ruta_archivo'], $schema['mime_type']);
    }

    private function loadTableSchema(string $table): array
    {
        $stmt = $this->pdo->prepare("SELECT column_name, data_type, is_nullable
                                     FROM information_schema.columns
                                     WHERE table_schema = DATABASE()
                                       AND table_name = :table");
        $stmt->execute(['table' => $table]);
        $rows = $stmt->fetchAll();

        $schema = [];
        foreach ($rows as $row) {
            $schema[(string) $row['column_name']] = [
                'data_type' => (string) $row['data_type'],
                'is_nullable' => (string) $row['is_nullable'],
            ];
        }

        return $schema;
    }

    private function hasUploadedFile(?array $uploadedFile): bool
    {
        return is_array($uploadedFile)
            && isset($uploadedFile['error'])
            && (int) $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE
            && trim((string) ($uploadedFile['name'] ?? '')) !== '';
    }

    private function validateUploadedFile(array $uploadedFile): array
    {
        $error = (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('Debe seleccionar un archivo PDF, DOC o DOCX.');
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('No fue posible cargar el archivo adjunto.');
        }

        $tmpName = (string) ($uploadedFile['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new InvalidArgumentException('El archivo recibido no es valido.');
        }

        $size = (int) ($uploadedFile['size'] ?? 0);
        if ($size <= 0) {
            throw new InvalidArgumentException('El archivo adjunto esta vacio.');
        }

        if ($size > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException('El archivo supera el tamano maximo permitido de 10 MB.');
        }

        $originalName = trim((string) ($uploadedFile['name'] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_MIME_TYPES[$extension])) {
            throw new InvalidArgumentException('Solo se permiten archivos PDF, DOC o DOCX.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string) $finfo->file($tmpName));
        if (!in_array($mime, self::ALLOWED_MIME_TYPES[$extension], true)) {
            throw new InvalidArgumentException('El tipo de archivo no coincide con los formatos permitidos.');
        }

        return [
            'extension' => $extension,
            'mime' => $mime,
            'original_name' => $this->sanitizeOriginalFilename($originalName, $extension),
        ];
    }

    private function sanitizeOriginalFilename(string $originalName, string $extension): string
    {
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $baseName) ?? '';
        $baseName = trim($baseName, '._-');

        if ($baseName === '') {
            $baseName = 'archivo_acta';
        }

        return $baseName . '.' . $extension;
    }

    private function storeUploadedFile(array $uploadedFile, array $fileInfo, string $consecutivo): array
    {
        $this->ensureStorageDirectory();

        $year = date('Y');
        $month = date('m');
        $relativeDirectory = $year . '/' . $month;
        $targetDirectory = $this->storageRoot . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('No fue posible preparar la carpeta para almacenar el archivo.');
        }

        $safeConsecutivo = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $consecutivo) ?? 'acta');
        $targetFilename = $safeConsecutivo . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $fileInfo['extension'];
        $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $targetFilename;

        if (!move_uploaded_file((string) $uploadedFile['tmp_name'], $targetPath)) {
            throw new RuntimeException('No fue posible guardar el archivo adjunto.');
        }

        return [
            'original_name' => $fileInfo['original_name'],
            'relative_path' => $relativeDirectory . '/' . $targetFilename,
            'mime' => $fileInfo['mime'],
        ];
    }

    private function deleteStoredFile(?string $relativePath): void
    {
        $absolutePath = $this->resolveStoredPath($relativePath);
        if ($absolutePath !== null && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storageRoot) && !mkdir($this->storageRoot, 0775, true) && !is_dir($this->storageRoot)) {
            throw new RuntimeException('No fue posible crear la carpeta base de almacenamiento de actas.');
        }
    }

    private function storageRootRealPath(): string
    {
        $this->ensureStorageDirectory();
        $root = realpath($this->storageRoot);

        if ($root === false) {
            throw new RuntimeException('No fue posible resolver la carpeta de almacenamiento de actas.');
        }

        return rtrim($root, "\\/");
    }
}
