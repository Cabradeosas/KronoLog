<?php
session_start();

set_time_limit(0);
ini_set('memory_limit', '1024M');

require '../KronoLog_connection.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/* =====================================================
   VALIDACIÓN
===================================================== */
if (!isset($_POST['year'])) {
    die('Año inválido');
}
$year = (int)$_POST['year'];

/* =====================================================
   MESES
===================================================== */
// Nombres abreviados de los meses (3 letras)
$months = [
    1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR',
    5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO',
    9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC'
];

/* =====================================================
   SQL HISTÓRICO PERFILES (PRESUPUESTOS)

   BUG FIX #1: The original query used YEAR(start_date) and YEAR(end_date)
   which incorrectly excluded history records that span across year boundaries.
   Example: a record starting 2025-11-01 with end_date 2026-02-01 would be
   excluded for year=2026 because YEAR(start_date)=2025, not <=2026.
   
   Fix: Compare full dates against the first and last day of the requested year.
===================================================== */
$sqlHistory = "
SELECT
    c.name AS client,
    cs.id AS service_id,
    csh.cost_per_hour,
    csh.cost_per_month,
    csh.start_date,
    csh.end_date
FROM client_service_history csh
JOIN client_service cs ON cs.id = csh.client_service_id
JOIN client c ON c.id = cs.client_id
WHERE csh.start_date <= :year_end
  AND (csh.end_date IS NULL OR csh.end_date >= :year_start)
ORDER BY c.name, cs.id
";

$stmtHistory = $PDOconnection->prepare($sqlHistory);
$stmtHistory->execute([
    'year_start' => "{$year}-01-01",
    'year_end'   => "{$year}-12-31",
]);
$historyRows = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   SQL HORAS TRABAJADAS
===================================================== */
$sqlTime = "
SELECT
    cs.id AS service_id,
    MONTH(tw.date) AS month,
    SUM(tw.minutes) / 60 AS hours
FROM time_worked tw
JOIN client_service cs ON cs.id = tw.client_service_id
WHERE YEAR(tw.date) = :year
GROUP BY cs.id, MONTH(tw.date)
";

$stmtTime = $PDOconnection->prepare($sqlTime);
$stmtTime->execute(['year' => $year]);
$timeRows = $stmtTime->fetchAll(PDO::FETCH_ASSOC);

$timeData = [];
foreach ($timeRows as $tr) {
    if (!isset($timeData[$tr['service_id']])) {
        $timeData[$tr['service_id']] = [];
    }
    $timeData[$tr['service_id']][$tr['month']] = (float)$tr['hours'];
}

/* =====================================================
   BUILD ROWS

   BUG FIX #2: The original code used strtotime() on start_date/end_date
   strings, which is unreliable — strtotime can return false on certain
   formats, silently producing month=1 and year=1970. Use date_create() or
   explicit string parsing instead (both safe with 'Y-m-d' formatted dates).

   BUG FIX #3: The month range loop did not deduplicate rows when a single
   service had multiple overlapping history entries for the same month (e.g.
   client_service_history rows 16 and 17 both cover service 33 from 2026-02-01).
   This caused duplicate rows for those months, doubling costs/hours.
   Fix: use a keyed array [service_id][month] so only the LAST (most recent)
   history entry wins per service+month — matching the real billing intent.
===================================================== */
// Keyed: $rowMap[service_id][month] = row data — last write wins on overlap
$rowMap = [];

foreach ($historyRows as $hr) {
    // Siempre cubrimos el año completo (1-12).
    // Solo saltamos registros que NO se solapan con el año pedido.

    // — Fecha de inicio —
    [$hrStartYear, $hrStartMonth] = explode('-', substr($hr['start_date'], 0, 7));
    $hrStartYear = (int)$hrStartYear;

    if ($hrStartYear > $year) {
        // El contrato empieza después del año pedido → no aplica
        continue;
    }

    // — Fecha de fin — (NULL = vigente; '0000-00-00' = MySQL zero date, tratar como NULL)
    $endDateRaw = $hr['end_date'];
    $hasEndDate = !empty($endDateRaw) && $endDateRaw !== '0000-00-00';

    if ($hasEndDate) {
        [$hrEndYear] = explode('-', substr($endDateRaw, 0, 7));
        $hrEndYear = (int)$hrEndYear;
        if ($hrEndYear < $year) {
            // El contrato terminó antes del año pedido → no aplica
            continue;
        }
    }

    // Siempre cubrimos los 12 meses: las horas reales del $timeData
    // determinarán qué meses tienen actividad y cuáles no
    $sid = $hr['service_id'];
    if (!isset($rowMap[$sid])) {
        $rowMap[$sid] = [];
    }

    for ($m = 1; $m <= 12; $m++) {
        // Last history entry for this service+month overwrites previous ones
        $rowMap[$sid][$m] = [
            'client'         => $hr['client'],
            'service_id'     => $sid,
            'month'          => $m,
            'hours'          => $timeData[$sid][$m] ?? 0,
            'cost_per_hour'  => $hr['cost_per_hour'],
            'cost_per_month' => $hr['cost_per_month'],
        ];
    }
}

// Flatten map into a simple array sorted by client then month
$rows = [];
foreach ($rowMap as $monthMap) {
    ksort($monthMap);
    foreach ($monthMap as $r) {
        $rows[] = $r;
    }
}
usort($rows, fn($a, $b) => strcmp($a['client'], $b['client']) ?: $a['month'] <=> $b['month']);

/* =====================================================
   PRE-CÁLCULO DE VALORES POR CLIENTE/MES
   Se calculan en PHP para evitar dependencia de SUMIFS cross-sheet,
   que falla en producción por comparaciones de texto sensibles a
   codificación/locale del servidor.
===================================================== */
$progByClientMonth  = []; // $progByClientMonth[$client][$month]  = ratio (0-N)
$hoursByClientMonth = []; // $hoursByClientMonth[$client][$month] = hours

foreach ($rows as $r) {
    $c = $r['client'];
    $m = (int)$r['month'];
    $hours  = (float)$r['hours'];
    $rate   = (float)$r['cost_per_hour'];
    $budget = (float)$r['cost_per_month'];
    $cost   = $hours * $rate;

    // Acumular por cliente+mes (un cliente puede tener varios servicios)
    if (!isset($progByClientMonth[$c][$m])) {
        $progByClientMonth[$c][$m]  = ['cost' => 0, 'budget' => 0];
        $hoursByClientMonth[$c][$m] = 0;
    }
    $progByClientMonth[$c][$m]['cost']   += $cost;
    $progByClientMonth[$c][$m]['budget'] += $budget;
    $hoursByClientMonth[$c][$m]          += $hours;
}

/* =====================================================
   HELPER — 3-state progress conditional styles
   >100% → yellow (over budget), 70–100% → green, <70% → red
   Order matters: PhpSpreadsheet applies first matching rule.
===================================================== */
function progressConditionals(): array {
    // Rule 1: >100% — amarillo (sobre presupuesto)
    $yellow = new Conditional();
    $yellow->setConditionType(Conditional::CONDITION_CELLIS)
           ->setOperatorType(Conditional::OPERATOR_GREATERTHAN)
           ->addCondition('1');
    $yellow->getStyle()->getFill()
           ->setFillType(Fill::FILL_SOLID)
           ->getStartColor()->setARGB('FFFFCC00'); // Amarillo

    // Rule 2: 70%–100% — verde
    $green = new Conditional();
    $green->setConditionType(Conditional::CONDITION_CELLIS)
          ->setOperatorType(Conditional::OPERATOR_GREATERTHANOREQUAL)
          ->addCondition('0.7');
    $green->getStyle()->getFill()
          ->setFillType(Fill::FILL_SOLID)
          ->getStartColor()->setARGB('FF92D050'); // Verde

    // Rule 3: <70% — rojo
    $red = new Conditional();
    $red->setConditionType(Conditional::CONDITION_CELLIS)
        ->setOperatorType(Conditional::OPERATOR_LESSTHAN)
        ->addCondition('0.7');
    $red->getStyle()->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFFF5050'); // Rojo

    return [$yellow, $green, $red];
}

/* =====================================================
   SPREADSHEET
===================================================== */
$spreadsheet = new Spreadsheet();

/* =====================================================
   HOJA DATOS (EDITABLE)
===================================================== */
$datos = $spreadsheet->getActiveSheet();
$datos->setTitle('DATOS');

$datos->setCellValue('A1', "BASE DE DATOS {$year}");
$datos->mergeCells('A1:H1');
$datos->getStyle('A1')->getFont()->setBold(true)->setSize(18)->setColor(new Color('FFFFFFFF'));
$datos->getStyle('A1')->getAlignment()
     ->setHorizontal(Alignment::HORIZONTAL_CENTER)
     ->setVertical(Alignment::VERTICAL_CENTER);
$datos->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2F75B5');
$datos->getRowDimension(1)->setRowHeight(40);

$datos->setCellValue('A2', 'NOTA IMPORTANTE: EDITA LOS VALORES (HORAS, €/HORA, PRESUPUESTO) AQUÍ, LAS OTRAS HOJAS SE ACTUALIZARÁN SOLAS');
$datos->mergeCells('A2:H2');
$datos->getStyle('A2')->getFont()->setBold(true)->setSize(12)->setColor(new Color('FFFF0000'));
$datos->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$datos->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF2CC');
$datos->getRowDimension(2)->setRowHeight(25);

$headers = ['CLIENTE', 'ID SERVICIO', 'MES', 'HORAS', '€/HORA', 'COSTE', 'PRESUPUESTO', 'PROGRESO'];
$datos->fromArray($headers, null, 'A3');

$datos->getStyle('A3:H3')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F75B5']],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
        'wrapText'   => true,
    ],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1F497D']]],
]);
$datos->getRowDimension(3)->setRowHeight(30);

$columnWidths = ['A' => 30, 'B' => 15, 'C' => 12, 'D' => 15, 'E' => 15, 'F' => 18, 'G' => 20, 'H' => 15];
foreach ($columnWidths as $column => $width) {
    $datos->getColumnDimension($column)->setWidth($width);
}

$row = 4;
foreach ($rows as $r) {
    $datos->setCellValue("A{$row}", $r['client']);
    $datos->setCellValue("B{$row}", (int)$r['service_id']);
    $datos->setCellValue("C{$row}", $months[(int)$r['month']]);
    $datos->setCellValue("D{$row}", (float)$r['hours']);
    $datos->setCellValue("E{$row}", (float)$r['cost_per_hour']);
    $datos->setCellValue("G{$row}", (float)$r['cost_per_month']);
    $datos->setCellValue("F{$row}", "=D{$row}*E{$row}");
    $datos->setCellValue("H{$row}", "=IFERROR(F{$row}/G{$row},0)");
    $datos->getRowDimension($row)->setRowHeight(25);

    if ($row % 2 == 0) {
        $datos->getStyle("A{$row}:H{$row}")->getFill()
              ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F2F2');
    }
    $row++;
}
$lastDataRow = $row - 1;

if ($lastDataRow >= 4) {
    $datos->getStyle("A4:H{$lastDataRow}")->applyFromArray([
        'font'      => ['size' => 12],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFA6A6A6']]],
    ]);
    $datos->getStyle("E4:F{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00 €');
    $datos->getStyle("G4:G{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00 €');
    $datos->getStyle("H4:H{$lastDataRow}")->getNumberFormat()->setFormatCode('0.0%');
    $datos->getStyle("H4:H{$lastDataRow}")->getFont()->setBold(true)->setSize(12);

    $datos->getStyle("H4:H{$lastDataRow}")->setConditionalStyles(progressConditionals());
}

/* =====================================================
   HOJA PROGRESO (renamed from CÁLCULOS)
===================================================== */
$calc = $spreadsheet->createSheet();
$calc->setTitle('PROGRESO');

$calc->setCellValue('A1', 'PROGRESO ANUAL POR CLIENTE');
$calc->mergeCells('A1:N1');
$calc->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setColor(new Color('FFFFFFFF'));
$calc->getStyle('A1')->getAlignment()
     ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$calc->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2F75B5');
$calc->getRowDimension(1)->setRowHeight(35);

$calc->setCellValue('A2', 'NOTA: Los datos provienen de DATOS, pero si sobrescribes un % a mano aquí, todos los totales (media anual, media mes) cambiarán en tiempo real.');
$calc->mergeCells('A2:N2');
$calc->getStyle('A2')->getFont()->setBold(true)->setSize(11)->setColor(new Color('FFFF0000'));
$calc->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$calc->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF2CC');
$calc->getRowDimension(2)->setRowHeight(25);

$calc->setCellValue('A4', 'CLIENTE');
$calc->setCellValue('A5', 'MES');

$monthWidths = 10;
$col = 2;
foreach ($months as $m => $name) {
    $L = Coordinate::stringFromColumnIndex($col);
    $calc->setCellValue("{$L}4", $name);
    $calc->setCellValue("{$L}5", $m);
    $calc->getStyle("{$L}4")->getFont()->setBold(true)->setSize(12);
    $calc->getStyle("{$L}4")->getAlignment()
         ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    $calc->getColumnDimension($L)->setWidth($monthWidths);
    $calc->getRowDimension(4)->setRowHeight(30);
    $col++;
}

$calc->setCellValue('N4', 'TOTAL ANUAL');
$calc->getStyle('N4')->getFont()->setBold(true)->setSize(13);
$calc->getStyle('N4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
$calc->getColumnDimension('N')->setWidth(18);
$calc->getRowDimension(5)->setVisible(false);

$calc->getStyle('B4:N4')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1F497D']]],
]);

$clients = array_values(array_unique(array_column($rows, 'client')));
$row = 6;

foreach ($clients as $client) {
    $calc->setCellValue("A{$row}", $client);
    $calc->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
    $calc->getStyle("A{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $calc->getRowDimension($row)->setRowHeight(25);
    $calc->getColumnDimension('A')->setWidth(30);

    for ($monthNum = 1; $monthNum <= 12; $monthNum++) {
        $colIndex = 1 + $monthNum;
        $L        = Coordinate::stringFromColumnIndex($colIndex);

        // Valor pre-calculado en PHP (evita SUMIFS cross-sheet que falla en producción)
        $costM   = $progByClientMonth[$client][$monthNum]['cost']   ?? 0;
        $budgetM = $progByClientMonth[$client][$monthNum]['budget'] ?? 0;
        $pct     = ($budgetM > 0) ? ($costM / $budgetM) : 0;

        $calc->setCellValue("{$L}{$row}", round($pct, 6));
        $calc->getStyle("{$L}{$row}")->setConditionalStyles(progressConditionals());

        if ($row % 2 == 0) {
            $calc->getStyle("{$L}{$row}")->getFill()
                 ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F2F2');
        }
    }

    $calc->setCellValue("N{$row}", "=IFERROR(AVERAGEIF(B{$row}:M{$row},\"<>0\"),0)");
    $calc->getStyle("N{$row}")->getNumberFormat()->setFormatCode('0.0%');
    $calc->getStyle("N{$row}")->getFont()->setBold(true)->setSize(13);
    $calc->getStyle("N{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $calc->getStyle("N{$row}")->setConditionalStyles(progressConditionals());

    $row++;
}
$lastClientRow = $row - 1;

if ($lastClientRow >= 6) {
    $calc->getStyle("B6:M{$lastClientRow}")->applyFromArray([
        'numberFormat' => ['formatCode' => '0.0%'],
        'font'         => ['size' => 12],
        'alignment'    => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $calc->getStyle("A6:N{$lastClientRow}")->getBorders()->getAllBorders()
         ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFA6A6A6');
}

// PROGRESO GLOBAL
$totalRow = $lastClientRow + 2;
$calc->setCellValue("A{$totalRow}", "PROGRESO GLOBAL %");
$calc->getStyle("A{$totalRow}")->getFont()->setBold(true)->setSize(14);
$calc->getStyle("A{$totalRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$calc->getRowDimension($totalRow)->setRowHeight(30);
$calc->getStyle("A{$totalRow}:N{$totalRow}")->getFill()
     ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE6F3FF');

for ($c = 2; $c <= 13; $c++) {
    $L = Coordinate::stringFromColumnIndex($c);
    $calc->setCellValue("{$L}{$totalRow}", "=IFERROR(AVERAGEIF({$L}6:{$L}{$lastClientRow},\"<>0\"),0)");
    $calc->getStyle("{$L}{$totalRow}")->getNumberFormat()->setFormatCode('0.0%');
    $calc->getStyle("{$L}{$totalRow}")->getFont()->setBold(true)->setSize(13);
    $calc->getStyle("{$L}{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $calc->getStyle("{$L}{$totalRow}")->setConditionalStyles(progressConditionals());
}

$calc->setCellValue("N{$totalRow}", "=IFERROR(AVERAGEIF(N6:N{$lastClientRow},\"<>0\"),0)");
$calc->getStyle("N{$totalRow}")->getNumberFormat()->setFormatCode('0.0%');
$calc->getStyle("N{$totalRow}")->getFont()->setBold(true)->setSize(14);
$calc->getStyle("N{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$calc->getStyle("N{$totalRow}")->setConditionalStyles(progressConditionals());
$calc->getStyle("A{$totalRow}:N{$totalRow}")->getBorders()->getAllBorders()
     ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('FF2F75B5');

/* =====================================================
   HOJA BENEFICIOS (renamed from TIEMPO DEDICADO — shows hours per client)
===================================================== */
$tiempo = $spreadsheet->createSheet();
$tiempo->setTitle('HORAS');

$tiempo->setCellValue('A1', 'HORAS DEDICADAS POR CLIENTE');
$tiempo->mergeCells('A1:N1');
$tiempo->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setColor(new Color('FFFFFFFF'));
$tiempo->getStyle('A1')->getAlignment()
       ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$tiempo->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2F75B5');
$tiempo->getRowDimension(1)->setRowHeight(35);

$tiempo->setCellValue('A3', 'CLIENTE');
$tiempo->setCellValue('A4', 'MES');

$col = 2;
foreach ($months as $m => $name) {
    $L = Coordinate::stringFromColumnIndex($col);
    $tiempo->setCellValue("{$L}3", $name);
    $tiempo->setCellValue("{$L}4", $m);
    $tiempo->getStyle("{$L}3")->getFont()->setBold(true)->setSize(12);
    $tiempo->getStyle("{$L}3")->getAlignment()
           ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    $tiempo->getColumnDimension($L)->setWidth($monthWidths);
    $tiempo->getRowDimension(3)->setRowHeight(30);
    $col++;
}

$tiempo->setCellValue('N3', 'TOTAL HORAS');
$tiempo->getStyle('N3')->getFont()->setBold(true)->setSize(13);
$tiempo->getStyle('N3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
$tiempo->getColumnDimension('N')->setWidth(18);
$tiempo->getRowDimension(4)->setVisible(false);

$tiempo->getStyle('B3:N3')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1F497D']]],
]);

$rowT = 5;
foreach ($clients as $client) {
    $tiempo->setCellValue("A{$rowT}", $client);
    $tiempo->getStyle("A{$rowT}")->getFont()->setBold(true)->setSize(12);
    $tiempo->getStyle("A{$rowT}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $tiempo->getRowDimension($rowT)->setRowHeight(25);
    $tiempo->getColumnDimension('A')->setWidth(30);

    for ($monthNum = 1; $monthNum <= 12; $monthNum++) {
        $colIndex = 1 + $monthNum;
        $L        = Coordinate::stringFromColumnIndex($colIndex);

        // Valor pre-calculado en PHP (evita SUMIFS cross-sheet que falla en producción)
        $hrs = $hoursByClientMonth[$client][$monthNum] ?? 0;

        $tiempo->setCellValue("{$L}{$rowT}", round($hrs, 2));
        $tiempo->getStyle("{$L}{$rowT}")->getNumberFormat()->setFormatCode('#,##0.00');
        $tiempo->getStyle("{$L}{$rowT}")->getFont()->setSize(12);

        if ($rowT % 2 == 0) {
            $tiempo->getStyle("{$L}{$rowT}")->getFill()
                   ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F2F2');
        }
    }

    $tiempo->setCellValue("N{$rowT}", "=SUM(B{$rowT}:M{$rowT})");
    $tiempo->getStyle("N{$rowT}")->getNumberFormat()->setFormatCode('#,##0.00');
    $tiempo->getStyle("N{$rowT}")->getFont()->setBold(true)->setSize(13);
    $tiempo->getStyle("N{$rowT}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $tiempo->getStyle("N{$rowT}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE6F3FF');

    $rowT++;
}
$lastClientRowT = $rowT - 1;

if ($lastClientRowT >= 5) {
    $tiempo->getStyle("A5:N{$lastClientRowT}")->getBorders()->getAllBorders()
           ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFA6A6A6');
}

$totalRowT = $lastClientRowT + 2;
$tiempo->setCellValue("A{$totalRowT}", "TOTAL HORAS MES");
$tiempo->getStyle("A{$totalRowT}")->getFont()->setBold(true)->setSize(14);
$tiempo->getStyle("A{$totalRowT}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$tiempo->getRowDimension($totalRowT)->setRowHeight(30);
$tiempo->getStyle("A{$totalRowT}:N{$totalRowT}")->getFill()
       ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE6F3FF');

for ($c = 2; $c <= 13; $c++) {
    $L = Coordinate::stringFromColumnIndex($c);
    $tiempo->setCellValue("{$L}{$totalRowT}", "=SUM({$L}5:{$L}{$lastClientRowT})");
    $tiempo->getStyle("{$L}{$totalRowT}")->getNumberFormat()->setFormatCode('#,##0.00');
    $tiempo->getStyle("{$L}{$totalRowT}")->getFont()->setBold(true)->setSize(13);
    $tiempo->getStyle("{$L}{$totalRowT}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

$tiempo->setCellValue("N{$totalRowT}", "=SUM(N5:N{$lastClientRowT})");
$tiempo->getStyle("N{$totalRowT}")->getNumberFormat()->setFormatCode('#,##0.00');
$tiempo->getStyle("N{$totalRowT}")->getFont()->setBold(true)->setSize(14)->setColor(new Color('FF00B050'));
$tiempo->getStyle("N{$totalRowT}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$tiempo->getStyle("A{$totalRowT}:N{$totalRowT}")->getBorders()->getAllBorders()
       ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('FF2F75B5');

/* =====================================================
   AJUSTES FINALES DE VISIBILIDAD
===================================================== */
foreach ($spreadsheet->getAllSheets() as $sheet) {
    $sheet->getDefaultRowDimension()->setRowHeight(20);
    $highestColumn = $sheet->getHighestColumn();
    $highestRow    = $sheet->getHighestRow();
    if ($highestRow > 0 && $highestColumn !== 'A') {
        $range = 'A1:' . $highestColumn . $highestRow;
        $sheet->getStyle($range)->getAlignment()->setWrapText(true);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }
    for ($col = 1; $col <= 20; $col++) {
        $colLetter    = Coordinate::stringFromColumnIndex($col);
        $currentWidth = $sheet->getColumnDimension($colLetter)->getWidth();
        if ($currentWidth === -1 || $currentWidth < 8) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(false);
            $sheet->getColumnDimension($colLetter)->setWidth(10);
        }
    }
}

/* =====================================================
   EXPORT
===================================================== */
$spreadsheet->setActiveSheetIndexByName('PROGRESO');

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"Reporte_Anual{$year}.xlsx\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(false);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;

