<?php
declare(strict_types=1);

class PersonasTemplateService
{
    private ?TipoPoblacionService $tipoPoblacionService;

    public function __construct(?TipoPoblacionService $tipoPoblacionService = null)
    {
        $this->tipoPoblacionService = $tipoPoblacionService;
    }

    public function filename(): string
    {
        return 'plantilla_importacion_personas.xlsx';
    }

    public function mimeType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function build(): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('El servidor no tiene habilitada la extension ZipArchive para generar la plantilla Excel.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'pactoh_personas_tpl_');
        if ($tempFile === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal de la plantilla.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::OVERWRITE) !== true) {
            @unlink($tempFile);
            throw new RuntimeException('No fue posible crear el archivo Excel de plantilla.');
        }

        try {
            $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
            $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
            $zip->addFromString('xl/workbook.xml', $this->workbookXml());
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($this->plantillaRows()));
            $zip->addFromString('xl/worksheets/sheet2.xml', $this->worksheetXml($this->referenciasRows()));
        } finally {
            $zip->close();
        }

        $binary = file_get_contents($tempFile);
        @unlink($tempFile);

        if ($binary === false) {
            throw new RuntimeException('No fue posible leer la plantilla Excel generada.');
        }

        return $binary;
    }

    /**
     * @return list<list<string>>
     */
    private function plantillaRows(): array
    {
        return [
            ['identificacion', 'nombres', 'apellidos', 'genero', 'fecha_nacimiento', 'correo', 'telefono', 'direccion', 'tipo_poblacion', 'es_testigo', 'es_jurado'],
            ['1001234567', 'Camilo', 'Ochoa', 'Masculino', '1990-05-17', 'camilo.ochoa@ejemplo.com', '3001234567', 'Calle 10 #20-30', 'General', 'Si', 'No'],
        ];
    }

    /**
     * @return list<list<string>>
     */
    private function referenciasRows(): array
    {
        $rows = [
            ['Campo', 'Obligatorio', 'Descripcion', 'Ejemplo'],
            ['identificacion', 'Si', 'Documento unico de la persona.', '1001234567'],
            ['nombres', 'Si', 'Nombres de la persona.', 'Camilo'],
            ['apellidos', 'Si', 'Apellidos de la persona.', 'Ochoa'],
            ['genero', 'No', 'Texto libre validado por el sistema.', 'Masculino'],
            ['fecha_nacimiento', 'No', 'Fecha en formato YYYY-MM-DD.', '1990-05-17'],
            ['correo', 'No', 'Correo valido si se informa.', 'persona@ejemplo.com'],
            ['telefono', 'Si', 'Telefono o celular principal.', '3001234567'],
            ['direccion', 'No', 'Direccion de domicilio.', 'Calle 10 #20-30'],
            ['tipo_poblacion', 'No', 'Debe coincidir con un tipo activo del catalogo.', 'General'],
            ['es_testigo', 'No', 'Use Si/No, 1/0 o Verdadero/Falso.', 'Si'],
            ['es_jurado', 'No', 'Use Si/No, 1/0 o Verdadero/Falso.', 'No'],
            ['', '', '', ''],
            ['Valores sugeridos', '', '', ''],
            ['genero', '', 'Ejemplos frecuentes.', 'Femenino / Masculino / No binario / Otro / Prefiero no decir'],
            ['fecha_nacimiento', '', 'Siempre use formato de fecha ISO.', 'YYYY-MM-DD'],
            ['es_testigo / es_jurado', '', 'Valores aceptados.', 'Si / No / 1 / 0 / Verdadero / Falso'],
        ];

        $tipos = $this->tipoPoblacionService?->listActive();
        $activos = [];

        foreach ($tipos ?? [] as $tipo) {
            if ((int) ($tipo['activo'] ?? 0) !== 1) {
                continue;
            }

            $nombre = trim((string) ($tipo['nombre'] ?? ''));
            if ($nombre !== '') {
                $activos[] = $nombre;
            }
        }

        if ($activos !== []) {
            $rows[] = ['', '', '', ''];
            $rows[] = ['Tipos de poblacion activos', '', '', ''];
            foreach ($activos as $tipoNombre) {
                $rows[] = ['tipo_poblacion', '', 'Valor disponible actualmente en el sistema.', $tipoNombre];
            }
        }

        return $rows;
    }

    /**
     * @param list<list<string>> $rows
     */
    private function worksheetXml(array $rows): string
    {
        $xmlRows = [];

        foreach ($rows as $rowIndex => $row) {
            $cells = [];

            foreach ($row as $columnIndex => $value) {
                $reference = $this->columnReference($columnIndex) . ($rowIndex + 1);
                $escapedValue = $this->xmlEscape($value);
                $cells[] = '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">' . $escapedValue . '</t></is></c>';
            }

            $xmlRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode('', $cells) . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<sheetData>' . implode('', $xmlRows) . '</sheetData>'
            . '</worksheet>';
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="Plantilla" sheetId="1" r:id="rId1"/>'
            . '<sheet name="Referencias" sheetId="2" r:id="rId2"/>'
            . '</sheets>'
            . '</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '</Relationships>';
    }

    private function columnReference(int $index): string
    {
        $index += 1;
        $letters = '';

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
