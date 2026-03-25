<?php
declare(strict_types=1);

class ExportService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getMeetingWithAttendance(int $reunionId): ?array
    {
        $meetingSql = 'SELECT r.id,
                              r.nombre_reunion,
                              r.objetivo,
                              r.tipo_reunion,
                              r.organizacion,
                              r.lugar_reunion,
                              r.fecha,
                              r.hora
                       FROM reuniones r
                       WHERE r.id = :id
                       LIMIT 1';

        $meetingStmt = $this->pdo->prepare($meetingSql);
        $meetingStmt->execute(['id' => $reunionId]);
        $meeting = $meetingStmt->fetch();

        if ($meeting === false) {
            return null;
        }

        $attendeesSql = 'SELECT COALESCE(NULLIF(TRIM(CONCAT_WS(" ", p.nombres, p.apellidos)), ""), p.nombres_apellidos) AS nombre_persona,
                                p.numero_documento,
                                p.celular,
                                p.es_testigo,
                                a.fecha_registro,
                                a.hora_registro
                         FROM asistencias a
                         INNER JOIN personas p ON p.id = a.persona_id
                         WHERE a.reunion_id = :reunion_id
                         ORDER BY nombre_persona ASC';

        $attendeesStmt = $this->pdo->prepare($attendeesSql);
        $attendeesStmt->execute(['reunion_id' => $reunionId]);
        $attendees = $attendeesStmt->fetchAll();

        $totalAsistentes = count($attendees);
        $totalTestigos = 0;

        foreach ($attendees as $row) {
            if ((int) $row['es_testigo'] === 1) {
                $totalTestigos++;
            }
        }

        return [
            'meeting' => $meeting,
            'attendees' => $attendees,
            'total_asistentes' => $totalAsistentes,
            'total_testigos' => $totalTestigos,
        ];
    }

    public function exportFileBaseName(int $reunionId): string
    {
        return 'asistencia_reunion_' . $reunionId . '_' . date('Y-m-d');
    }

    public function resolveLogoPath(): ?string
    {
        $candidatePaths = [
            BASE_PATH . '/Logo/pacto.png',
            (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') : '') . '/Logo/pacto.png',
        ];

        foreach ($candidatePaths as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function resolveLogoPathForPdf(): ?string
    {
        $candidatePaths = [
            BASE_PATH . '/Logo/pacto_pdf.jpg',
            (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') : '') . '/Logo/pacto_pdf.jpg',
            BASE_PATH . '/Logo/pacto.jpg',
            (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') : '') . '/Logo/pacto.jpg',
            BASE_PATH . '/Logo/pacto.jpeg',
            (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') : '') . '/Logo/pacto.jpeg',
            BASE_PATH . '/Logo/pacto.png',
            (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') : '') . '/Logo/pacto.png',
        ];

        foreach ($candidatePaths as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}