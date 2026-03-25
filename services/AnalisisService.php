<?php
declare(strict_types=1);

class AnalisisService
{
    private PDO $pdo;

    private const AGE_RANGES = [
        '0_17' => ['label' => '0 a 17', 'min' => 0, 'max' => 17],
        '18_28' => ['label' => '18 a 28', 'min' => 18, 'max' => 28],
        '29_40' => ['label' => '29 a 40', 'min' => 29, 'max' => 40],
        '41_59' => ['label' => '41 a 59', 'min' => 41, 'max' => 59],
        '60_plus' => ['label' => '60 o mas', 'min' => 60, 'max' => null],
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function normalizeFilters(array $input): array
    {
        $genero = trim((string) ($input['genero'] ?? ''));
        $tipoPoblacionId = request_int($input, 'tipo_poblacion_id', 0);
        $esTestigo = $this->normalizeBooleanFilter($input['es_testigo'] ?? '');
        $esJurado = $this->normalizeBooleanFilter($input['es_jurado'] ?? '');
        $rangoEdad = trim((string) ($input['rango_edad'] ?? ''));

        if (!isset(self::AGE_RANGES[$rangoEdad])) {
            $rangoEdad = '';
        }

        return [
            'genero' => $genero,
            'tipo_poblacion_id' => $tipoPoblacionId > 0 ? $tipoPoblacionId : 0,
            'es_testigo' => $esTestigo,
            'es_jurado' => $esJurado,
            'rango_edad' => $rangoEdad,
        ];
    }

    public function getAgeRangeOptions(): array
    {
        $options = [];

        foreach (self::AGE_RANGES as $key => $range) {
            $options[] = [
                'key' => $key,
                'label' => $range['label'],
            ];
        }

        return $options;
    }

    public function listGeneroOptions(): array
    {
        $stmt = $this->pdo->query('SELECT DISTINCT genero
                                   FROM personas
                                   WHERE genero IS NOT NULL
                                     AND TRIM(genero) <> \'\'
                                   ORDER BY genero ASC');

        return array_map(
            static fn (mixed $value): string => (string) $value,
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    public function getOverview(array $filters): array
    {
        $summary = $this->fetchSummary($filters);
        $genderRows = $this->fetchGenderDistribution($filters);
        $populationRows = $this->fetchPopulationDistribution($filters);
        $ageRows = $this->fetchAgeDistribution($filters);

        return [
            'summary' => $summary,
            'gender' => $genderRows,
            'population' => $populationRows,
            'age_ranges' => $ageRows,
        ];
    }

    private function fetchSummary(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhereClause($filters);

        $sql = 'SELECT COUNT(*) AS total_personas,
                       COALESCE(SUM(CASE WHEN p.es_testigo = 1 THEN 1 ELSE 0 END), 0) AS total_testigos,
                       COALESCE(SUM(CASE WHEN p.es_jurado = 1 THEN 1 ELSE 0 END), 0) AS total_jurados,
                       COALESCE(SUM(CASE WHEN p.fecha_nacimiento IS NOT NULL THEN 1 ELSE 0 END), 0) AS total_con_fecha_nacimiento,
                       COALESCE(SUM(CASE WHEN p.fecha_nacimiento IS NULL THEN 1 ELSE 0 END), 0) AS total_sin_fecha_nacimiento
                FROM personas p
                LEFT JOIN tipos_poblacion tp ON tp.id = p.tipo_poblacion_id' . $whereSql;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'total_personas' => (int) ($row['total_personas'] ?? 0),
            'total_testigos' => (int) ($row['total_testigos'] ?? 0),
            'total_jurados' => (int) ($row['total_jurados'] ?? 0),
            'total_con_fecha_nacimiento' => (int) ($row['total_con_fecha_nacimiento'] ?? 0),
            'total_sin_fecha_nacimiento' => (int) ($row['total_sin_fecha_nacimiento'] ?? 0),
        ];
    }

    private function fetchGenderDistribution(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhereClause($filters);

        $sql = 'SELECT COALESCE(NULLIF(TRIM(p.genero), \'\'), \'Sin definir\') AS etiqueta,
                       COUNT(*) AS total
                FROM personas p
                LEFT JOIN tipos_poblacion tp ON tp.id = p.tipo_poblacion_id' . $whereSql . '
                GROUP BY COALESCE(NULLIF(TRIM(p.genero), \'\'), \'Sin definir\')
                ORDER BY total DESC, etiqueta ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function fetchPopulationDistribution(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhereClause($filters);

        $sql = 'SELECT COALESCE(NULLIF(TRIM(tp.nombre), \'\'), \'Sin tipo\') AS etiqueta,
                       COUNT(*) AS total
                FROM personas p
                LEFT JOIN tipos_poblacion tp ON tp.id = p.tipo_poblacion_id' . $whereSql . '
                GROUP BY COALESCE(NULLIF(TRIM(tp.nombre), \'\'), \'Sin tipo\')
                ORDER BY total DESC, etiqueta ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function fetchAgeDistribution(array $filters): array
    {
        [$whereSql, $params] = $this->buildWhereClause($filters);
        $ageExpr = $this->ageExpression();

        $sql = 'SELECT
                    COALESCE(SUM(CASE WHEN edad BETWEEN 0 AND 17 THEN 1 ELSE 0 END), 0) AS rango_0_17,
                    COALESCE(SUM(CASE WHEN edad BETWEEN 18 AND 28 THEN 1 ELSE 0 END), 0) AS rango_18_28,
                    COALESCE(SUM(CASE WHEN edad BETWEEN 29 AND 40 THEN 1 ELSE 0 END), 0) AS rango_29_40,
                    COALESCE(SUM(CASE WHEN edad BETWEEN 41 AND 59 THEN 1 ELSE 0 END), 0) AS rango_41_59,
                    COALESCE(SUM(CASE WHEN edad >= 60 THEN 1 ELSE 0 END), 0) AS rango_60_plus,
                    COALESCE(SUM(CASE WHEN edad IS NULL THEN 1 ELSE 0 END), 0) AS sin_fecha
                FROM (
                    SELECT p.id,
                           CASE
                               WHEN p.fecha_nacimiento IS NULL THEN NULL
                               ELSE ' . $ageExpr . '
                           END AS edad
                    FROM personas p
                    LEFT JOIN tipos_poblacion tp ON tp.id = p.tipo_poblacion_id' . $whereSql . '
                ) base';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            ['key' => '0_17', 'label' => self::AGE_RANGES['0_17']['label'], 'total' => (int) ($row['rango_0_17'] ?? 0)],
            ['key' => '18_28', 'label' => self::AGE_RANGES['18_28']['label'], 'total' => (int) ($row['rango_18_28'] ?? 0)],
            ['key' => '29_40', 'label' => self::AGE_RANGES['29_40']['label'], 'total' => (int) ($row['rango_29_40'] ?? 0)],
            ['key' => '41_59', 'label' => self::AGE_RANGES['41_59']['label'], 'total' => (int) ($row['rango_41_59'] ?? 0)],
            ['key' => '60_plus', 'label' => self::AGE_RANGES['60_plus']['label'], 'total' => (int) ($row['rango_60_plus'] ?? 0)],
            ['key' => 'sin_fecha', 'label' => 'Sin fecha de nacimiento', 'total' => (int) ($row['sin_fecha'] ?? 0)],
        ];
    }

    private function buildWhereClause(array $filters): array
    {
        $conditions = [];
        $params = [];
        $ageExpr = $this->ageExpression();

        if ((string) ($filters['genero'] ?? '') !== '') {
            $conditions[] = 'p.genero = :genero';
            $params['genero'] = (string) $filters['genero'];
        }

        if ((int) ($filters['tipo_poblacion_id'] ?? 0) > 0) {
            $conditions[] = 'p.tipo_poblacion_id = :tipo_poblacion_id';
            $params['tipo_poblacion_id'] = (int) $filters['tipo_poblacion_id'];
        }

        if ($filters['es_testigo'] !== '') {
            $conditions[] = 'p.es_testigo = :es_testigo';
            $params['es_testigo'] = (int) $filters['es_testigo'];
        }

        if ($filters['es_jurado'] !== '') {
            $conditions[] = 'p.es_jurado = :es_jurado';
            $params['es_jurado'] = (int) $filters['es_jurado'];
        }

        $rangoEdad = (string) ($filters['rango_edad'] ?? '');
        if ($rangoEdad !== '' && isset(self::AGE_RANGES[$rangoEdad])) {
            $range = self::AGE_RANGES[$rangoEdad];
            $conditions[] = 'p.fecha_nacimiento IS NOT NULL';

            if ($range['max'] === null) {
                $conditions[] = $ageExpr . ' >= :edad_min';
                $params['edad_min'] = (int) $range['min'];
            } else {
                $conditions[] = $ageExpr . ' BETWEEN :edad_min AND :edad_max';
                $params['edad_min'] = (int) $range['min'];
                $params['edad_max'] = (int) $range['max'];
            }
        }

        if ($conditions === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function ageExpression(): string
    {
        return 'TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE())';
    }

    private function normalizeBooleanFilter(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '1' || $value === '0') {
            return $value;
        }

        return '';
    }
}
