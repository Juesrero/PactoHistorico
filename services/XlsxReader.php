<?php
declare(strict_types=1);

class XlsxReader
{
    /**
     * @return list<array{row_number:int, cells:array<int, string>}>
     */
    public function readRows(string $xlsxPath): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('El servidor no tiene habilitada la extension ZipArchive para leer archivos Excel.');
        }

        $zip = new ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            throw new RuntimeException('No fue posible abrir el archivo Excel.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetPath = $this->resolveFirstSheetPath($zip);
            $sheetXml = $zip->getFromName($sheetPath);

            if ($sheetXml === false) {
                throw new RuntimeException('No se encontro la hoja principal del archivo Excel.');
            }

            return $this->parseSheetRows($sheetXml, $sharedStrings);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $document = $this->loadXml($xml, 'No se pudieron leer los textos compartidos del Excel.');
        $mainNs = $this->mainNamespace($document);

        $siNodes = $this->xpathNodes($document, $mainNs, '//m:si', '//si');
        $result = [];

        foreach ($siNodes as $si) {
            $textNodes = $this->xpathNodes($si, $mainNs, './/m:t', './/t');
            $text = '';

            foreach ($textNodes as $node) {
                $text .= (string) $node;
            }

            $result[] = $text;
        }

        return $result;
    }

    private function resolveFirstSheetPath(ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = $this->loadXml($workbookXml, 'No se pudo leer la estructura del libro Excel.');
        $workbookNs = $workbook->getNamespaces(true);
        $mainNs = $workbookNs[''] ?? null;
        $relationshipNs = $workbookNs['r'] ?? null;

        if ($mainNs === null || $relationshipNs === null) {
            return 'xl/worksheets/sheet1.xml';
        }

        $sheets = $workbook->children($mainNs)->sheets;
        if (!isset($sheets->sheet[0])) {
            return 'xl/worksheets/sheet1.xml';
        }

        $sheetAttrs = $sheets->sheet[0]->attributes($relationshipNs);
        $relationshipId = (string) ($sheetAttrs['id'] ?? '');
        if ($relationshipId === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        $rels = $this->loadXml($relsXml, 'No se pudieron leer las relaciones internas del archivo Excel.');
        $relsNs = $rels->getNamespaces(true);
        $relsMainNs = $relsNs[''] ?? null;
        $relationships = $relsMainNs !== null ? $rels->children($relsMainNs)->Relationship : $rels->Relationship;

        foreach ($relationships as $relationship) {
            if ((string) $relationship['Id'] !== $relationshipId) {
                continue;
            }

            $target = str_replace('\\', '/', (string) $relationship['Target']);
            if ($target === '') {
                break;
            }

            if (substr($target, 0, 1) === '/') {
                return ltrim($target, '/');
            }

            return 'xl/' . ltrim($target, '/');
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param list<string> $sharedStrings
     * @return list<array{row_number:int, cells:array<int, string>}>
     */
    private function parseSheetRows(string $sheetXml, array $sharedStrings): array
    {
        $worksheet = $this->loadXml($sheetXml, 'No se pudo leer la hoja del Excel.');
        $mainNs = $this->mainNamespace($worksheet);

        $rowNodes = $this->xpathNodes($worksheet, $mainNs, '//m:sheetData/m:row', '//sheetData/row');
        $rows = [];

        foreach ($rowNodes as $row) {
            $rowAttrs = $row->attributes();
            $rowNumber = (int) ($rowAttrs['r'] ?? 0);
            if ($rowNumber <= 0) {
                $rowNumber = count($rows) + 1;
            }

            $cells = [];
            $cellNodes = $this->xpathNodes($row, $mainNs, './m:c', './c');

            foreach ($cellNodes as $cell) {
                $cellAttrs = $cell->attributes();
                $reference = (string) ($cellAttrs['r'] ?? '');

                $columnIndex = $this->columnIndexFromReference($reference);
                if ($columnIndex < 0) {
                    $columnIndex = count($cells);
                }

                $cells[$columnIndex] = $this->cellValue($cell, $mainNs, $sharedStrings);
            }

            if ($cells === []) {
                continue;
            }

            ksort($cells);
            $rows[] = [
                'row_number' => $rowNumber,
                'cells' => $cells,
            ];
        }

        return $rows;
    }

    /**
     * @param list<string> $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, ?string $mainNs, array $sharedStrings): string
    {
        $attrs = $cell->attributes();
        $type = (string) ($attrs['t'] ?? $cell['t'] ?? '');

        if ($type === 's') {
            $rawValue = $this->firstNodeText($cell, $mainNs, './m:v', './v');
            $index = (int) $rawValue;
            return $sharedStrings[$index] ?? '';
        }

        if ($type === 'inlineStr') {
            $textNodes = $this->xpathNodes($cell, $mainNs, './m:is//m:t', './is//t');
            $text = '';
            foreach ($textNodes as $node) {
                $text .= (string) $node;
            }

            return trim($text);
        }

        if ($type === 'b') {
            $rawValue = $this->firstNodeText($cell, $mainNs, './m:v', './v');
            return (string) ((int) $rawValue);
        }

        $rawValue = $this->firstNodeText($cell, $mainNs, './m:v', './v');


        return trim($rawValue);
    }

    private function columnIndexFromReference(string $reference): int
    {
        if ($reference === '') {
            return -1;
        }

        if (!preg_match('/^[A-Z]+/i', $reference, $matches)) {
            return -1;
        }

        $letters = strtoupper($matches[0]);
        $value = 0;

        for ($i = 0, $length = strlen($letters); $i < $length; $i++) {
            $value = ($value * 26) + (ord($letters[$i]) - 64);
        }

        return $value - 1;
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private function xpathNodes(SimpleXMLElement $context, ?string $mainNs, string $queryWithNamespace, string $queryWithoutNamespace): array
    {
        if ($mainNs !== null && $mainNs !== '') {
            $context->registerXPathNamespace('m', $mainNs);
            $nodes = $context->xpath($queryWithNamespace);
            if (is_array($nodes)) {
                return $nodes;
            }
        }

        $nodes = $context->xpath($queryWithoutNamespace);
        return is_array($nodes) ? $nodes : [];
    }

    private function firstNodeText(SimpleXMLElement $context, ?string $mainNs, string $queryWithNamespace, string $queryWithoutNamespace): string
    {
        $nodes = $this->xpathNodes($context, $mainNs, $queryWithNamespace, $queryWithoutNamespace);
        if ($nodes === []) {
            return '';
        }

        return (string) $nodes[0];
    }

    private function mainNamespace(SimpleXMLElement $xml): ?string
    {
        $namespaces = $xml->getNamespaces(true);
        return $namespaces[''] ?? null;
    }

    private function loadXml(string $xml, string $errorMessage): SimpleXMLElement
    {
        $document = @simplexml_load_string($xml);
        if (!$document instanceof SimpleXMLElement) {
            throw new RuntimeException($errorMessage);
        }

        return $document;
    }
}

