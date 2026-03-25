<?php
declare(strict_types=1);

class PersonaImportService
{
    private const MAX_FILE_SIZE = 5242880; // 5MB
    private const MAX_ROWS = 5000;

    private PersonaService $personaService;
    private XlsxReader $xlsxReader;

    public function __construct(PersonaService $personaService, ?XlsxReader $xlsxReader = null)
    {
        $this->personaService = $personaService;
        $this->xlsxReader = $xlsxReader ?? new XlsxReader();
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
        if ($tmpPath === '' || !is_file($tmpPath)) {
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

        $rows = $this->xlsxReader->readRows($tmpPath);
        if ($rows === []) {
            throw new RuntimeException('El archivo Excel no contiene filas para importar.');
        }

        $required = ['nombres_apellidos', 'numero_documento', 'celular'];
        $headerIndex = null;
        $headerMap = [];

        $maxHeaderScan = min(7, count($rows));
        for ($i = 0; $i < $maxHeaderScan; $i++) {
            $candidateMap = $this->resolveHeaderMap($rows[$i]['cells']);
            if ($this->hasRequiredColumns($candidateMap, $required)) {
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

            $esTestigoParse = $this->parseEsTestigo($input['es_testigo']);
            if ($esTestigoParse === null) {
                $errors[] = [
                    'row' => (int) $row['row_number'],
                    'message' => 'Valor de es_testigo invalido. Use 1/0, si/no o verdadero/falso.',
                ];
                continue;
            }

            $validationInput = [
                'nombres_apellidos' => $input['nombres_apellidos'],
                'numero_documento' => $input['numero_documento'],
                'celular' => $input['celular'],
            ];
            if ($esTestigoParse === 1) {
                $validationInput['es_testigo'] = '1';
            }

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
                    'message' => 'Documento duplicado dentro del archivo (ya aparece en fila ' . $seenDocuments[$documento] . ').',
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
                    'message' => 'El documento ya existe en la base de datos.',
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
                'nombres_apellidos',
                'nombres_apellido',
                'nombre_apellidos',
                'nombres_y_apellidos',
                'nombre_apellido',
                'nombre_completo',
                'nombre',
            ],
            'numero_documento' => ['numero_documento', 'numero_de_documento', 'documento', 'cedula', 'dni', 'doc'],
            'celular' => ['celular', 'telefono', 'telefono_celular', 'movil', 'cel'],
            'es_testigo' => ['es_testigo', 'testigo', 'testigo_si_no', 'testigo_1_0'],
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
     * @param list<string> $required
     */
    private function hasRequiredColumns(array $headerMap, array $required): bool
    {
        foreach ($required as $requiredColumn) {
            if (!isset($headerMap[$requiredColumn])) {
                return false;
            }
        }

        return true;
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
     * @return array{nombres_apellidos:string, numero_documento:string, celular:string, es_testigo:string}
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
            'numero_documento' => $pick($rowCells, $headerMap['numero_documento'] ?? null),
            'celular' => $pick($rowCells, $headerMap['celular'] ?? null),
            'es_testigo' => $pick($rowCells, $headerMap['es_testigo'] ?? null),
        ];
    }

    /**
     * @param array{nombres_apellidos:string, numero_documento:string, celular:string, es_testigo:string} $input
     */
    private function isRowEmpty(array $input): bool
    {
        return $input['nombres_apellidos'] === ''
            && $input['numero_documento'] === ''
            && $input['celular'] === ''
            && $input['es_testigo'] === '';
    }

    private function parseEsTestigo(string $value): ?int
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
