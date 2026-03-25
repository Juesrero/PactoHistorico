<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$reunionId = export_reunion_id_from_query();
if ($reunionId <= 0) {
    export_invalid_redirect('Parametro de reunion invalido para exportar a PDF.');
}

$exportService = new ExportService(db());
$payload = $exportService->getMeetingWithAttendance($reunionId);
if ($payload === null) {
    export_invalid_redirect('La reunion solicitada no existe para exportar a PDF.');
}

$tcpdfPath = BASE_PATH . '/libs/tcpdf/tcpdf.php';
if (!is_file($tcpdfPath)) {
    export_invalid_redirect('No se encontro la libreria TCPDF en /libs/tcpdf.');
}

require_once $tcpdfPath;

$meeting = $payload['meeting'];
$attendees = $payload['attendees'];
$totalAsistentes = (int) $payload['total_asistentes'];
$totalTestigos = (int) $payload['total_testigos'];
$logoPath = $exportService->resolveLogoPathForPdf();

$filename = $exportService->exportFileBaseName($reunionId) . '.pdf';

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('PactoH');
$pdf->SetAuthor('Sistema de Asistencia');
$pdf->SetTitle('Asistencia reunion ' . (string) $reunionId);
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 14);
$pdf->AddPage();

if ($logoPath !== null) {
    $extension = strtolower((string) pathinfo($logoPath, PATHINFO_EXTENSION));
    $imgType = 'PNG';

    if ($extension === 'jpg' || $extension === 'jpeg') {
        $imgType = 'JPG';
    }

    $pdf->Image($logoPath, 12, 10, 24, 0, $imgType, '', '', false, 300, '', false, false, 0, false, false, false);
}

$pdf->SetXY(40, 12);
$pdf->SetFont('dejavusans', 'B', 14);
$pdf->Cell(0, 7, 'Listado de asistencia por reunion', 0, 1, 'L', false, '', 0, false, 'T', 'M');
$pdf->Ln(2);

$pdf->SetFont('dejavusans', '', 10);
$metaHtml = '<table border="1" cellpadding="5" cellspacing="0">
<tr><td width="180"><b>Nombre de la reunion</b></td><td width="340">' . htmlspecialchars((string) $meeting['nombre_reunion'], ENT_QUOTES, 'UTF-8') . '</td></tr>
<tr><td width="180"><b>Objetivo</b></td><td width="340">' . htmlspecialchars((string) $meeting['objetivo'], ENT_QUOTES, 'UTF-8') . '</td></tr>
<tr><td width="180"><b>Organizacion</b></td><td width="340">' . htmlspecialchars((string) $meeting['organizacion'], ENT_QUOTES, 'UTF-8') . '</td></tr>
<tr><td width="180"><b>Lugar reunion</b></td><td width="340">' . htmlspecialchars((string) $meeting['lugar_reunion'], ENT_QUOTES, 'UTF-8') . '</td></tr>
<tr><td width="180"><b>Fecha</b></td><td width="340">' . htmlspecialchars((string) $meeting['fecha'], ENT_QUOTES, 'UTF-8') . '</td></tr>
<tr><td width="180"><b>Hora</b></td><td width="340">' . htmlspecialchars(substr((string) $meeting['hora'], 0, 5), ENT_QUOTES, 'UTF-8') . '</td></tr>
</table>';
$pdf->writeHTML($metaHtml, true, false, true, false, '');
$pdf->Ln(2);

$tableHeader = '<table border="1" cellpadding="4" cellspacing="0">
<thead>
<tr style="background-color:#e5e7eb;">
<th width="155"><b>Nombre y apellido</b></th>
<th width="92"><b>Documento</b></th>
<th width="85"><b>Celular</b></th>
<th width="58"><b>Testigo</b></th>
<th width="85"><b>Fecha registro</b></th>
<th width="65"><b>Hora registro</b></th>
</tr>
</thead>
<tbody>';

$rowsHtml = '';
if ($attendees === []) {
    $rowsHtml .= '<tr><td colspan="6" width="540">Sin asistentes registrados.</td></tr>';
} else {
    foreach ($attendees as $attendee) {
        $rowsHtml .= '<tr>'
            . '<td width="155">' . htmlspecialchars((string) $attendee['nombres_apellidos'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td width="92">' . htmlspecialchars((string) $attendee['numero_documento'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td width="85">' . htmlspecialchars((string) $attendee['celular'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td width="58">' . ((int) $attendee['es_testigo'] === 1 ? 'Si' : 'No') . '</td>'
            . '<td width="85">' . htmlspecialchars((string) $attendee['fecha_registro'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td width="65">' . htmlspecialchars(substr((string) $attendee['hora_registro'], 0, 5), ENT_QUOTES, 'UTF-8') . '</td>'
            . '</tr>';
    }
}

$tableFooter = '</tbody></table>';
$pdf->writeHTML($tableHeader . $rowsHtml . $tableFooter, true, false, true, false, '');
$pdf->Ln(2);

$pdf->SetFont('dejavusans', 'B', 10);
$pdf->Cell(0, 6, 'Total asistentes: ' . $totalAsistentes, 0, 1, 'L');
$pdf->Cell(0, 6, 'Total testigos: ' . $totalTestigos, 0, 1, 'L');

$pdf->Output($filename, 'D');
exit;
