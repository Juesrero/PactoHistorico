<?php
declare(strict_types=1);

class PersonaImportService
{
    private const MAX_FILE_SIZE = 5242880; // 5MB
    private const MAX_ROWS = 5000;
    private const ALLOWED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/x-zip-compressed',
        'application/octet-stream',
    ];

    private PersonaService $personaService;
    private XlsxReader $xlsxReader;
    private ?TipoPoblacionService $tipoPoblacionService;

    public function __construct(PersonaService $personaService, ?XlsxReader $xlsxReader = null, ?TipoPoblacionService $tipoPoblacionService = null)
    {
        $this->personaService = $personaService;
        $this->xlsxReader = $xlsxReader ?? new XlsxReader();
        $this->tipoPoblacionService = $tipoPoblacionService;
    }

    /**
     * @param array<string, mixed> $uploadedFile
     * @return array{processed:int, inserted:int, skipped:int, errors:list<array{row:int, message:string}>}
     */
    public function importFromUploadedFile(array $uploadedFile): array
    {
        $errorCode = (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($errorCode));
        }

        $tmpPath = (string) ($uploadedFile['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('No se encontro el archivo temporal para importar.');
        }

        $originalName = (string) ($uploadedFile['name'] ?? 'archivo.xlsx');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            throw new RuntimeException('Formato invalido. Solo se permite archivo Excel .xlsx');
        }

        $size = (int) ($uploadedFile['size'] ?? 0);
        if ($size <= 0) {
            throw new RuntimeException('El archivo Excel esta vacio.');
        }

        if ($size > self::MAX_FILE_SIZE) {
            throw new RuntimeException('El archivo supera el maximo permitido de 5 MB.');
        }

        $mime = strtolower((string) (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath));
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new RuntimeException('El archivo cargado no corresponde a un Excel .xlsx valido.');
        }

        $rows = $this->xlsxReader->readRows($tmpPath);
        if ($rows === []) {
            throw new RuntimeException('El archivo Excel no contiene filas para importar.');
        }

        $headerIndex = null;
        $headerMap = [];

        $maxHeaderScan = min(7, count($rows));
        for ($i = 0; $i < $maxHeaderScan; $i++) {
            $candidateMap = $this->resolveHeaderMap($rows[$i]['cells']);
            if ($this->hasRequiredColumns($candidateMap)) {
                $headerIndex = $i;
                $headerMap = $candidateMap;
                break;
            }
        }

        if ($headerIndex === null) {
            $detected = $this->detectedHeadersText($rows[0]['cells']);
            throw new RuntimeException('Faltan columnas obligatorias. Encabezados detectados en la primera fila: ' . $detected . '.');
        }

        $processed = 0;
        $errors = [];
        $seenDocuments = [];
        $candidates = [];

        for ($i = $headerIndex + 1, $totalRows = count($rows); $i < $totalRows; $i++) {
            $row = $rows[$i];

            if ($processed >= self::MAX_ROWS) {
                $errors[] = [
                    'row' => (int) $row['row_number'],
                    'message' => 'Se alcanzo el limite de ' . self::MAX_ROWS . ' filas procesables por importacion.',
                ];
                break;
            }

            $input = $this->rowToInput($row['cells'], $headerMap);
            if ($this->isRowEmpty($input)) {
                continue;
            }

            $processed++;

            [$nombres, $apellidos] = $this->resolveNames($input['nombres'], $input['apellidos'], $input['nombres_apellidos']);

            $esTestigo = $this->parseBooleanFlag($input['es_testigo']);
            if ($esTestigo === null) {
                $errors[] = [
                    'row' => (int) $row['row_number'],
                    'message' => 'Valor de es_testigo invalido. Use 1/0, si/no o verdadero/falso.',
                ];
                continue;
            }

            $esJurado = $this->parseBooleanFlag($input['es_jurado']);
            if ($esJurado === null) {
                $errors[] = [
                    'row' => (int) $row['row_number'],
                    'message' => 'Valor de es_jurado invalido. Use 1/0, si/no o verdadero/falso.',
                ];
                continue;
            }

            $tipoPoblacionId = null;
            if ($input['tipo_poblacion'] !== '') {
                $tipoPoblacion = $this->resolveTipoPoblacion($input['tipo_poblacion']);
                if ($tipoPoblacion === null) {
                    $errors[] = [
                        'row' => (int) $row['row_number'],
                        'message' => 'El tipo de poblacion indicado no existe o esta inactivo.',
                    ];
                    continue;
                }
                $tipoPoblacionId = (int) $tipoPoblacion['id'];
            }

            $validationInput = [
                'numero_documento' => $input['numero_documento'],
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'genero' => $input['genero'],
                'fecha_nacimiento' => $input['fecha_nacimiento'],
                'correo' => $input['correo'],
                'celular' => $input['celular'],
                'direccion' => $input['direccion'],
                'tipo_poblacion_id' => $tipoPoblacionId,
                'es_testigo' => $esTestigo === 1 ? '1' : '0',
                'es_jurado' => $esJurado === 1 ? '1' : '0',
            ];

            [$clean, $validationErrors] = Validator::validatePersona($validationInput);
            if ($validationErrors !== []) {
                $errors[] = [
                    'row' => (int) $row['row_number'],
                    'message' => implode(' ', array_values($validationErrors)),
                ];
                continue;
            }

            $documento = $clean['numero_documento'];
            if (isset($seenDocuments[$documento])) {
                $errors[] = [
                    'row' => (int) $row['row_number'],
                    'message' => 'Identificacion duplicada dentro del archivo (ya aparece en fila ' . $seenDocuments[$documento] . ').',
                ];
                continue;
            }

            $seenDocuments[$documento] = (int) $row['row_number'];
            $candidates[] = [
                'row_number' => (int) $row['row_number'],
                'data' => $clean,
            ];
        }

        $existing = $this->personaService->existingDocuments(array_keys($seenDocuments));
        $toInsert = [];

        foreach ($candidates as $candidate) {
            $documento = $candidate['data']['numero_documento'];
            if (isset($existing[$documento])) {
                $errors[] = [
                    'row' => $candidate['row_number'],
                    'message' => 'La identificacion ya existe en la base de datos.',
                ];
                continue;
            }

            $toInsert[] = $candidate['data'];
        }

        $inserted = $this->personaService->createMany($toInsert);

        return [
            'processed' => $processed,
            'inserted' => $inserted,
            'skipped' => max(0, $processed - $inserted),
            'errors' => $errors,
        ];
    }

    /**
     * @param array<int, string> $headerCells
     * @return array<string, int>
     */
    private function resolveHeaderMap(array $headerCells): array
    {
        $aliases = [
            'nombres_apellidos' => [
                'nombres_apellidos',
                'nombres_apellido',
                'nombre_apellidos',
                'nombres_y_apellidos',
                'nombre_apellido',
                'nombre_completo',
            ],
            'nombres' => ['nombres', 'nombre', 'primer_nombre'],
            'apellidos' => ['apellidos', 'apellido', 'primer_apellido'],
            'numero_documento' => ['numero_documento', 'numero_de_documento', 'documento', 'identificacion', 'cedula', 'dni', 'doc'],
            'celular' => ['celular', 'telefono', 'telefono_celular', 'movil', 'cel'],
            'genero' => ['genero', 'sexo'],
            'fecha_nacimiento' => ['fecha_nacimiento', 'fecha_de_nacimiento', 'nacimiento'],
            'correo' => ['correo', 'email', 'correo_electronico'],
            'direccion' => ['direccion', 'direccion_domicilio', 'domicilio'],
            'tipo_poblacion' => ['tipo_poblacion', 'poblacion', 'grupo_poblacional'],
            'es_testigo' => ['es_testigo', 'testigo', 'testigo_si_no', 'testigo_1_0'],
            'es_jurado' => ['es_jurado', 'jurado', 'jurado_si_no', 'jurado_1_0'],
        ];

        $normalizedHeaders = [];
        foreach ($headerCells as $index => $value) {
            $normalized = $this->normalizeText($value);
            if ($normalized === '') {
                continue;
            }

            $normalizedHeaders[$normalized] = $index;
        }

        $map = [];
        foreach ($aliases as $canonical => $options) {
            foreach ($options as $option) {
                if (!isset($normalizedHeaders[$option])) {
                    continue;
                }

                $map[$canonical] = $normalizedHeaders[$option];
                break;
            }
        }

        return $map;
    }

    /**
     * @param array<string, int> $headerMap
     */
    private function hasRequiredColumns(array $headerMap): bool
    {
        $hasNames = isset($headerMap['nombres_apellidos']) || (isset($headerMap['nombres']) && isset($headerMap['apellidos']));

        return $hasNames
            && isset($headerMap['numero_documento'])
            && isset($headerMap['celular']);
    }

    /**
     * @param array<int, string> $headerCells
     */
    private function detectedHeadersText(array $headerCells): string
    {
        $headers = [];
        foreach ($headerCells as $value) {
            $clean = trim((string) $value);
            if ($clean === '') {
                continue;
            }

            $headers[] = $clean;
        }

        if ($headers === []) {
            return '(sin texto de encabezados)';
        }

        return implode(', ', $headers);
    }

    /**
     * @param array<int, string> $rowCells
     * @param array<string, int> $headerMap
     * @return array<string, string>
     */
    private function rowToInput(array $rowCells, array $headerMap): array
    {
        $pick = static function (array $cells, ?int $index): string {
            if ($index === null || !isset($cells[$index])) {
                return '';
            }

            return trim((string) $cells[$index]);
        };

        return [
            'nombres_apellidos' => $pick($rowCells, $headerMap['nombres_apellidos'] ?? null),
            'nombres' => $pick($rowCells, $headerMap['nombres'] ?? null),
            'apellidos' => $pick($rowCells, $headerMap['apellidos'] ?? null),
            'numero_documento' => $pick($rowCells, $headerMap['numero_documento'] ?? null),
            'celular' => $pick($rowCells, $headerMap['celular'] ?? null),
            'genero' => $pick($rowCells, $headerMap['genero'] ?? null),
            'fecha_nacimiento' => $pick($rowCells, $headerMap['fecha_nacimiento'] ?? null),
            'correo' => $pick($rowCells, $headerMap['correo'] ?? null),
            'direccion' => $pick($rowCells, $headerMap['direccion'] ?? null),
            'tipo_poblacion' => $pick($rowCells, $headerMap['tipo_poblacion'] ?? null),
            'es_testigo' => $pick($rowCells, $headerMap['es_testigo'] ?? null),
            'es_jurado' => $pick($rowCells, $headerMap['es_jurado'] ?? null),
        ];
    }

    /**
     * @param array<string, string> $input
     */
    private function isRowEmpty(array $input): bool
    {
        foreach ($input as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveNames(string $nombres, string $apellidos, string $nombresApellidos): array
    {
        $nombres = trim($nombres);
        $apellidos = trim($apellidos);

        if ($nombres !== '' && $apellidos !== '') {
            return [$nombres, $apellidos];
        }

        $nombresApellidos = trim($nombresApellidos);
        if ($nombresApellidos === '') {
            return [$nombres, $apellidos];
        }

        $parts = preg_split('/\s+/', $nombresApellidos) ?: [];
        if (count($parts) <= 1) {
            return [$nombresApellidos, $apellidos];
        }

        $lastName = (string) array_pop($parts);
        $firstNames = trim(implode(' ', $parts));

        return [$firstNames, $lastName];
    }

    private function parseBooleanFlag(string $value): ?int
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === '') {
            return 0;
        }

        $yes = ['1', 'si', 's', 'true', 'verdadero', 'x'];
        if (in_array($normalized, $yes, true)) {
            return 1;
        }

        $no = ['0', 'no', 'n', 'false', 'falso'];
        if (in_array($normalized, $no, true)) {
            return 0;
        }

        return null;
    }

    private function normalizeText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }

        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
        return trim($value, '_');
    }

    private function resolveTipoPoblacion(string $value): ?array
    {
        if ($this->tipoPoblacionService === null) {
            return null;
        }

        return $this->tipoPoblacionService->findByName(trim($value), true);
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamano permitido en el servidor.',
            UPLOAD_ERR_PARTIAL => 'La carga del archivo fue parcial. Intente nuevamente.',
            UPLOAD_ERR_NO_FILE => 'Debe seleccionar un archivo Excel para importar.',
            UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal para cargas.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo temporal en el servidor.',
            UPLOAD_ERR_EXTENSION => 'Una extension del servidor bloqueo la carga del archivo.',
            default => 'No fue posible cargar el archivo Excel.',
        };
    }
}
