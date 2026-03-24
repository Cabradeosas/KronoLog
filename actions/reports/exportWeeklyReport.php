<?php
session_start();
require '../time_hours_connection.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/* =====================================================
   VALIDACIÓN DE SEMANA
===================================================== */
if (!isset($_POST['year'], $_POST['week'])) {
    die('Parámetros inválidos: year y week requeridos');
}

$year = (int) $_POST['year'];
$week = (int) $_POST['week'];

// Fechas de inicio y fin de la semana seleccionada
$dt = new DateTime();
$dt->setISODate($year, $week);
$startDate = $dt->format('Y-m-d');
$endDate = $dt->modify('+6 days')->format('Y-m-d');

$daysInWeek = 5; // consideramos días hábiles lunes-viernes

/* =====================================================
   CÁLCULO DEL FACTOR DE PROGRESO PARA LA SEMANA SELECCIONADA
===================================================== */
$today = new DateTime();
$currentYear = (int) $today->format('o');
$currentWeek = (int) $today->format('W');

if ($year > $currentYear || ($year == $currentYear && $week > $currentWeek)) {
    $weekProgressFactor = 0;
} elseif ($year == $currentYear && $week == $currentWeek) {
    $weekProgressFactor = min((int) $today->format('N'), $daysInWeek) / $daysInWeek;
} else {
    $weekProgressFactor = 1;
}

/* =====================================================
   QUERY SQL
   Solo usamos cost_per_hour del client_service
===================================================== */
$sql = "
SELECT
    c.name AS client_name,
    s.name AS service_name,
    cs.cost_per_month AS budget_monthly,
    cs.cost_per_hour,
    SUM(tw.minutes) AS total_minutes
FROM client c
JOIN client_service cs ON cs.client_id = c.id
JOIN service s ON s.id = cs.service_id
LEFT JOIN time_worked tw
       ON tw.client_service_id = cs.id
      AND tw.date BETWEEN :start AND :end
GROUP BY c.id, cs.id
ORDER BY c.name, s.name
";

$stmt = $PDOconnection->prepare($sql);
$stmt->execute([
    'start' => $startDate,
    'end' => $endDate
]);

/* =====================================================
   PROCESAR DATOS
===================================================== */
$data = [];
$clients = [];
$services = [];

foreach ($stmt as $row) {
    $client = $row['client_name'];
    $service = $row['service_name'];

    $clients[$client] = true;
    $services[$service] = true;

    $minutes = (int) $row['total_minutes'];
    $hours = $minutes / 60;
    $realCost = $hours * $row['cost_per_hour'];

    if (!isset($data[$client][$service])) {
        $data[$client][$service] = [
            'minutes' => 0,
            'cost_real' => 0,
            'budget_monthly' => 0,
            'cost_per_hour' => $row['cost_per_hour']
        ];
    }

    $data[$client][$service]['minutes'] += $minutes;
    $data[$client][$service]['cost_real'] += $realCost;
    $data[$client][$service]['budget_monthly'] += (float) $row['budget_monthly'];

    if (!isset($data[$client]['total_hours']))
        $data[$client]['total_hours'] = 0;
    if (!isset($data[$client]['total_cost']))
        $data[$client]['total_cost'] = 0;

    $data[$client]['total_hours'] += $hours;
    $data[$client]['total_cost'] += $realCost;
}

$clients  = array_keys($clients);
$services = array_keys($services);

/* =====================================================
   CREAR EXCEL
===================================================== */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Semana {$week}");

/* =====================================================
   TITULO
===================================================== */
$sheet->setCellValue('A1', "Resumen Semanal - Semana {$week} ({$startDate} a {$endDate})");
$sheet->getStyle('A1')->getFont()->setBold(true);

/* =====================================================
   BLOQUE MINUTOS/HORAS
===================================================== */
$sheet->setCellValue('A3', 'Mostrar tiempo como');
$sheet->setCellValue('B3', 'Minutos');
$sheet->getStyle('A3')->getFont()->setBold(true);

$sheet->getCell('B3')->getDataValidation()
    ->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
    ->setAllowBlank(false)
    ->setShowDropDown(true)
    ->setFormula1('"Minutos,Horas"');

$sheet->setCellValue('A5', 'RESUMEN DE TIEMPO');
$sheet->getStyle('A5')->getFont()->setBold(true);
$sheet->setCellValue('A6', 'Cliente');
$sheet->getStyle('A6')->getFont()->setBold(true);

/* Encabezados de servicios */
foreach ($services as $i => $service) {
    $col = Coordinate::stringFromColumnIndex($i + 2);
    $sheet->setCellValue($col . '6', $service);
    $sheet->getStyle($col . '6')->getFont()->setBold(true);
}

/* Total y coste/hora al final */
$totalCol = Coordinate::stringFromColumnIndex(count($services) + 2);
$costHourCol = Coordinate::stringFromColumnIndex(count($services) + 3);
$sheet->setCellValue($totalCol . '6', 'Total');
$sheet->getStyle($totalCol . '6')->getFont()->setBold(true);

/* ================= DATOS ================= */
$row = 7;
foreach ($clients as $client) {
    $sheet->setCellValue("A{$row}", $client);

    foreach ($services as $i => $service) {
        $col = Coordinate::stringFromColumnIndex($i + 2);
        $minutes = $data[$client][$service]['minutes'] ?? 0;

        $sheet->setCellValue(
            "{$col}{$row}",
            "=IF(\$B\$3=\"Minutos\", {$minutes}, {$minutes}/60)"
        );
    }

    // Total minutos/horas
    $sheet->setCellValue(
        "{$totalCol}{$row}",
        "=SUM(B{$row}:" . Coordinate::stringFromColumnIndex(count($services)+1) . "{$row})"
    );
    $row++;
}

$lastRow = $row - 1;

/* Formato horas decimales */
$sheet->getStyle("B7:{$totalCol}{$lastRow}")
    ->getNumberFormat()
    ->setFormatCode('#,##0.00');

/* =====================================================
   BLOQUE ECONÓMICO
===================================================== */
$start = $lastRow + 2;
$sheet->setCellValue("A{$start}", 'RESUMEN ECONÓMICO');
$sheet->getStyle("A{$start}")->getFont()->setBold(true);

$start++;
$sheet->fromArray(
    ['Cliente', 'Coste real €', 'Presupuesto €', 'Margen €', 'Margen %', 'Progreso actual %', 'Progreso semana %', 'Coste/hora €'],
    null,
    "A{$start}"
);
$sheet->getStyle("A{$start}:H{$start}")->getFont()->setBold(true);

$r = $start + 1;
foreach ($clients as $client) {
    $cost = 0;
    $budget = 0;

    foreach ($services as $service) {
        if (isset($data[$client][$service])) {
            $cost += $data[$client][$service]['cost_real'];
            $budget += $data[$client][$service]['budget_monthly'];
        }
    }

    $sheet->setCellValue("A{$r}", $client);
    $sheet->setCellValue("B{$r}", $cost);
    $sheet->setCellValue("C{$r}", $budget / 4); // presupuesto semanal
    $sheet->setCellValue("D{$r}", "=C{$r}-B{$r}");
    $sheet->setCellValue("E{$r}", "=IF(C{$r}=0,0,D{$r}/C{$r})");

    $weekFactor = max(0, min(1, $weekProgressFactor));
    $sheet->setCellValue("F{$r}", "=IF(C{$r}=0,0,IF({$weekFactor}=0,0,B{$r}/(C{$r}*{$weekFactor})))");
    $sheet->setCellValue("G{$r}", "=IF(C{$r}=0,0,B{$r}/C{$r})");

    // Coste/hora promedio por cliente
    $totalHours = $data[$client]['total_hours'] ?? 0;
    $sheet->setCellValue(
        "H{$r}",
        $totalHours > 0 ? round($data[$client]['total_cost'] / $totalHours, 2) : 0
    );

    $r++;
}

$lastRowEconomy = $r - 1;

/* =====================================================
   FORMATOS
===================================================== */
$sheet->getStyle("B{$start}:D{$lastRowEconomy}")
    ->getNumberFormat()
    ->setFormatCode('€ #,##0.00');

$sheet->getStyle("E{$start}:G{$lastRowEconomy}")
    ->getNumberFormat()
    ->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

/* =====================================================
   CONDICIONALES
===================================================== */
$green = new Conditional();
$green->setConditionType(Conditional::CONDITION_CELLIS)
    ->setOperatorType(Conditional::OPERATOR_GREATERTHAN)
    ->addCondition('0');
$green->getStyle()->getFont()->getColor()->setARGB('FF008000');

$red = new Conditional();
$red->setConditionType(Conditional::CONDITION_CELLIS)
    ->setOperatorType(Conditional::OPERATOR_LESSTHAN)
    ->addCondition('0');
$red->getStyle()->getFont()->getColor()->setARGB('FFCC0000');

foreach (['D','E'] as $col) {
    $sheet->getStyle("{$col}" . ($start + 1) . ":{$col}{$lastRowEconomy}")
        ->setConditionalStyles([$green, $red]);
}

$red = new Conditional();
$red->setConditionType(Conditional::CONDITION_CELLIS)
    ->setOperatorType(Conditional::OPERATOR_LESSTHAN)
    ->addCondition('0.7');
$red->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FFFF9999');

$green = new Conditional();
$green->setConditionType(Conditional::CONDITION_CELLIS)
    ->setOperatorType(Conditional::OPERATOR_BETWEEN)
    ->addCondition('0.7')
    ->addCondition('1');
$green->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FF99FF99');

$yellow = new Conditional();
$yellow->setConditionType(Conditional::CONDITION_CELLIS)
    ->setOperatorType(Conditional::OPERATOR_GREATERTHAN)
    ->addCondition('1');
$yellow->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FFFFFF99');

$range = "F" . ($start + 1) . ":G{$lastRowEconomy}";
$sheet->getStyle($range)->setConditionalStyles([$red, $green, $yellow]);

/* =====================================================
   AUTO SIZE
===================================================== */
foreach (range('A', $colPr) as $col) {
    if ($col !== 'A') {
        $sheet->getColumnDimension($col)->setWidth(15);
    }
}

/* =====================================================
   DESCARGA
===================================================== */
$filename = "Reporte_Semanal{$week}_W{$year}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;
?>
