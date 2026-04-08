<?php
session_start();

// Evitar timeout de PHP al generar el Excel
set_time_limit(0);
ini_set('memory_limit', '1024M');

require '../KronoLog_connection.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

/* =====================================================
   VALIDACIÓN DE FECHA
===================================================== */
if (!isset($_POST['year'], $_POST['month'])) {
    die('Parámetros inválidos');
}

$year  = (int) $_POST['year'];
$month = (int) $_POST['month'];

$monthsNames = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate   = date('Y-m-t', strtotime($startDate));

$today      = new DateTime();
$firstDayOfMonth = new DateTime($startDate);
$daysInMonth = (int) $firstDayOfMonth->format('t');

if ($firstDayOfMonth->format('Y-m') < $today->format('Y-m')) {
    $daysElapsed = $daysInMonth;
} elseif ($firstDayOfMonth->format('Y-m') == $today->format('Y-m')) {
    $daysElapsed = (int) $today->format('j');
} else {
    $daysElapsed = 0;
}

/* =====================================================
   QUERY SQL
===================================================== */
$sql = "
SELECT
    c.name            AS client_name,
    s.name            AS service_name,
    cs.cost_per_month AS budget_monthly,
    cs.cost_per_hour  AS cost_per_hour,
    SUM(tw.minutes)   AS total_minutes
FROM client c
JOIN client_service cs ON cs.client_id = c.id
JOIN service s        ON s.id = cs.service_id
LEFT JOIN time_worked tw 
       ON tw.client_service_id = cs.id
      AND tw.date BETWEEN :start AND :end
GROUP BY c.id, cs.id
ORDER BY c.name, s.name
";

$stmt = $PDOconnection->prepare($sql);
$stmt->execute([
    'start' => $startDate,
    'end'   => $endDate
]);

/* =====================================================
   PROCESAR DATOS
===================================================== */
$data = [];
$clients = [];
$services = [];

foreach ($stmt as $row) {
    $client  = $row['client_name'];
    $service = $row['service_name'];

    $clients[$client]   = true;
    $services[$service] = true;

    $minutes = (int) $row['total_minutes'];
    $hours   = $minutes / 60;

    $costPerHour = (float)$row['cost_per_hour'];
    $realCost    = $hours * $costPerHour;

    if (!isset($data[$client][$service])) {
        $data[$client][$service] = [
            'minutes'        => 0,
            'cost_real'      => 0,
            'budget_monthly' => 0,
        ];
    }

    if (!isset($data[$client]['total_hours'])) $data[$client]['total_hours'] = 0;
    if (!isset($data[$client]['total_cost']))  $data[$client]['total_cost']  = 0;
    if (!isset($data[$client]['total_budget'])) $data[$client]['total_budget'] = 0;

    $data[$client][$service]['minutes']        += $minutes;
    $data[$client][$service]['cost_real']      += $realCost;
    $data[$client][$service]['budget_monthly'] += (float) $row['budget_monthly'];

    $data[$client]['total_hours'] += $hours;
    $data[$client]['total_cost']  += $realCost;
    $data[$client]['total_budget'] += (float) $row['budget_monthly'];
}

$clients  = array_keys($clients);
$services = array_keys($services);

/* =====================================================
   CREAR EXCEL
===================================================== */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Resumen mensual');

/* =====================================================
   TABLA IZQUIERDA: MATRIZ TRABAJADORES + TOTALES
===================================================== */
// Query para desglose por trabajador
$sqlWorker = "
    SELECT 
        c.name as client_name,
        u.name as worker_name,
        SUM(tw.minutes) as worker_minutes
    FROM time_worked tw
    JOIN user u ON tw.user_id = u.id
    JOIN client_service cs ON tw.client_service_id = cs.id
    JOIN client c ON cs.client_id = c.id
    WHERE tw.date BETWEEN :start AND :end
    GROUP BY c.name, u.name
    ORDER BY c.name, u.name
";
$stmtW = $PDOconnection->prepare($sqlWorker);
$stmtW->execute(['start' => $startDate, 'end' => $endDate]);

$matrix = [];
$allWorkers = [];

foreach ($stmtW as $r) {
    $c = $r['client_name'];
    $w = $r['worker_name'];
    $m = (int)$r['worker_minutes'];
    
    $matrix[$c][$w] = $m;
    $allWorkers[$w] = $w;
}

sort($allWorkers);

// Headers
$sheet->setCellValue('A1', 'Cliente');
$colIndex = 2; // B
foreach ($allWorkers as $wName) {
    $colString = Coordinate::stringFromColumnIndex($colIndex);
    $sheet->setCellValue("{$colString}1", $wName);
    $sheet->getColumnDimension($colString)->setWidth(10);
    $colIndex++;
}
$lastWorkerColLetter = Coordinate::stringFromColumnIndex($colIndex - 1);

// Headers Extra solicitados
$extraHeaders = [
    'Minutos Totales', 
    'Horas Totales', 
    'Precio/Hora', 
    'Bill', 
    'Progreso %'
];

foreach ($extraHeaders as $header) {
    $colString = Coordinate::stringFromColumnIndex($colIndex);
    $sheet->setCellValue("{$colString}1", $header);
    $sheet->getColumnDimension($colString)->setWidth(10);
    $colIndex++;
}

// Estilos Headers
$lastWorkerCol = Coordinate::stringFromColumnIndex($colIndex - 1);
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        'wrapText'   => true,
    ],
];
$sheet->getStyle("A1:{$lastWorkerCol}1")->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(40);

$rowL = 2;
foreach ($clients as $clientName) {
    $sheet->setCellValue("A{$rowL}", $clientName);
    
    $workerSum = 0;
    $colIdx = 2;
    
    // Trabajadores
    foreach ($allWorkers as $wName) {
        $mins = $matrix[$clientName][$wName] ?? 0;
        $workerSum += $mins;
        $colStr = Coordinate::stringFromColumnIndex($colIdx);
        
        if ($mins > 0) {
            $sheet->setCellValue("{$colStr}{$rowL}", $mins);
        } else {
            $sheet->setCellValue("{$colStr}{$rowL}", '');
        }
        $colIdx++;
    }
    
    // Datos Extra
    $clientTotalMins = isset($data[$clientName]['total_hours']) ? $data[$clientName]['total_hours'] * 60 : 0;
    $clientTotalHours = $clientTotalMins / 60;
    $clientTotalCost = $data[$clientName]['total_cost'] ?? 0;
    $clientBudget = $data[$clientName]['total_budget'] ?? 0;
    $pricePerHour = $clientTotalHours > 0 ? $clientTotalCost / $clientTotalHours : 0;

    // 1. Minutos Totales (Dinámico)
    $colMins = Coordinate::stringFromColumnIndex($colIdx++);
    $sheet->setCellValue("{$colMins}{$rowL}", "=SUM(B{$rowL}:{$lastWorkerColLetter}{$rowL})");
    
    // 2. Horas Totales (Dinámico)
    $colHours = Coordinate::stringFromColumnIndex($colIdx++);
    $sheet->setCellValue("{$colHours}{$rowL}", "={$colMins}{$rowL}/60");
    
    $colPrice = Coordinate::stringFromColumnIndex($colIdx++);
    $sheet->setCellValue("{$colPrice}{$rowL}", $pricePerHour); // Precio estático (base del cálculo)
    
    $colCost = Coordinate::stringFromColumnIndex($colIdx++);
    $sheet->setCellValue("{$colCost}{$rowL}", $clientBudget);
    
    $colProg = Coordinate::stringFromColumnIndex($colIdx++);
    $sheet->setCellValue("{$colProg}{$rowL}", "=IF({$colCost}{$rowL}<>0, ({$colHours}{$rowL}*{$colPrice}{$rowL})/{$colCost}{$rowL}, 0)");

    $rowL++;
}

$lastClientRowL = $rowL - 1;

if ($lastClientRowL >= 2) {
    // Aplicar color verde si tiene horas mediando condicional para mejorar rendimiento
    $condVerdeHoras = new Conditional();
    $condVerdeHoras->setConditionType(Conditional::CONDITION_CELLIS)
        ->setOperatorType(Conditional::OPERATOR_GREATERTHAN)
        ->addCondition('0');
    $condVerdeHoras->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
    $condVerdeHoras->getStyle()->getFill()->getStartColor()->setARGB('FFC6EFCE');
    $condVerdeHoras->getStyle()->getFill()->getEndColor()->setARGB('FFC6EFCE');

    // IMPORTANTE: usar $lastWorkerColLetter (última col. de trabajadores) y NO $lastWorkerCol
    // $lastWorkerCol apunta a la última columna extra (Progreso %) y sobreescribiría ese color
    $sheet->getStyle("B2:{$lastWorkerColLetter}{$lastClientRowL}")->setConditionalStyles([$condVerdeHoras]);
}

/* =====================================================
   FILAS DE TOTALES (TABLA IZQUIERDA)
===================================================== */
$rowMins = $rowL;
$rowHours = $rowL + 1;

$sheet->setCellValue("A{$rowMins}", 'TOTAL MINUTOS');
$sheet->getStyle("A{$rowMins}")->getFont()->setBold(true);

$sheet->setCellValue("A{$rowHours}", 'TOTAL HORAS');
$sheet->getStyle("A{$rowHours}")->getFont()->setBold(true);

$cIdx = 2;
// Suma por Trabajador
foreach ($allWorkers as $w) {
    $col = Coordinate::stringFromColumnIndex($cIdx);
    $sheet->setCellValue("{$col}{$rowMins}", "=SUM({$col}2:{$col}" . ($rowMins - 1) . ")");
    $sheet->setCellValue("{$col}{$rowHours}", "={$col}{$rowMins}/60");
    $cIdx++;
}

// Suma Minutos Totales
$col = Coordinate::stringFromColumnIndex($cIdx++);
$sheet->setCellValue("{$col}{$rowMins}", "=SUM({$col}2:{$col}" . ($rowMins - 1) . ")");
$sheet->setCellValue("{$col}{$rowHours}", "={$col}{$rowMins}/60");

// Suma Horas Totales
$colH_T = Coordinate::stringFromColumnIndex($cIdx++);
$sheet->setCellValue("{$colH_T}{$rowMins}", ""); // Not applicable for minutes
$sheet->setCellValue("{$colH_T}{$rowHours}", "=SUM({$colH_T}2:{$colH_T}" . ($rowMins - 1) . ")");

// Precio/Hora - sin valor en fila total
$colP_T = Coordinate::stringFromColumnIndex($cIdx++);
$sheet->setCellValue("{$colP_T}{$rowL}", "");

// Bill - sin valor en fila total
$colB_T = Coordinate::stringFromColumnIndex($cIdx++);
$sheet->setCellValue("{$colB_T}{$rowL}", "");

// Estilo Fila Total
$sheet->getStyle("A{$rowMins}:{$colB_T}{$rowHours}")->getFont()->setBold(true);
$sheet->getStyle("A{$rowMins}:{$colB_T}{$rowHours}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E1F2');

// Formatos de columnas extra
$startExtra = count($allWorkers) + 2; // A + workers + 1
$colH = Coordinate::stringFromColumnIndex($startExtra + 1); // Horas
$colP = Coordinate::stringFromColumnIndex($startExtra + 2); // Precio
$colT = Coordinate::stringFromColumnIndex($startExtra + 3); // Total
$colPr = Coordinate::stringFromColumnIndex($startExtra + 4); // Progreso

$sheet->getStyle("{$colH}2:{$colH}{$rowHours}")->getNumberFormat()->setFormatCode('#,##0.00');
$lastDataRowL = $rowMins - 1;
$sheet->getStyle("{$colP}2:{$colT}{$lastDataRowL}")->getNumberFormat()->setFormatCode('€ #,##0.00');
$sheet->getStyle("{$colPr}2:{$colPr}{$rowHours}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

$sheet->getColumnDimension('A')->setWidth(30);

/* =====================================================
   SELECTOR DE UNIDAD
===================================================== */
// Calculamos offset dinámico para que la tabla derecha no se superponga
// Dejamos 1 columna de espacio después de la última columna de datos
$colOffset = $colIndex; 

$startColChar = Coordinate::stringFromColumnIndex(1 + $colOffset); // J
$nextColChar = Coordinate::stringFromColumnIndex(2 + $colOffset); // K

$sheet->setCellValue("{$startColChar}2", 'Mostrar tiempo como');
$sheet->setCellValue("{$nextColChar}2", 'Minutos');
$sheet->getStyle("{$startColChar}2")->getFont()->setBold(true);

$sheet->getCell("{$nextColChar}2")->getDataValidation()
    ->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
    ->setAllowBlank(false)
    ->setShowDropDown(true)
    ->setFormula1('"Minutos,Horas"');

/* =====================================================
   BLOQUE MINUTOS / HORAS
===================================================== */
$sheet->setCellValue("{$startColChar}4", 'RESUMEN DE TIEMPO');
$sheet->getStyle("{$startColChar}4")->getFont()->setBold(true);

$sheet->setCellValue("{$startColChar}5", 'Cliente');
$sheet->getStyle("{$startColChar}5")->getFont()->setBold(true);

foreach ($services as $i => $service) {
    $col = Coordinate::stringFromColumnIndex($i + 2 + $colOffset);
    $sheet->setCellValue($col . '5', $service);
    $sheet->getStyle($col . '5')->getFont()->setBold(true);
}

// Columna Total de tiempo
$totalCol = Coordinate::stringFromColumnIndex(count($services) + 2 + $colOffset);
$sheet->setCellValue($totalCol . '5', 'Total');
$sheet->getStyle($totalCol . '5')->getFont()->setBold(true);

$row = 6;

foreach ($clients as $client) {
    $sheet->setCellValue("{$startColChar}{$row}", $client);

    foreach ($services as $i => $service) {
        $col = Coordinate::stringFromColumnIndex($i + 2 + $colOffset);
        $minutes = $data[$client][$service]['minutes'] ?? 0;

        $sheet->setCellValue(
            "{$col}{$row}",
            "=IF(\${$nextColChar}\$2=\"Minutos\", {$minutes}, ROUND({$minutes}/60,2))"
        );
    }

    $sheet->setCellValue(
        "{$totalCol}{$row}",
        "=SUM({$nextColChar}{$row}:" . Coordinate::stringFromColumnIndex(count($services)+1+$colOffset) . "{$row})"
    );

    $row++;
}

// Formato numérico para horas decimales
$sheet->getStyle("{$nextColChar}6:{$totalCol}{$row}")
      ->getNumberFormat()
      ->setFormatCode('#,##0.00');

// ===========================
// GRAFICO DE TIEMPO TRABAJADO POR SERVICIO
// ===========================
$dataSeriesLabels = [];
$xAxisTickValues  = [];
$dataSeriesValues = [];

// Series = servicios
foreach ($services as $i => $service) {
    $col = Coordinate::stringFromColumnIndex($i + 2 + $colOffset);
    $dataSeriesLabels[] = new DataSeriesValues('String', "'Resumen mensual'!{$col}5", null, 1);

    $valuesRange = "'Resumen mensual'!{$col}6:{$col}" . ($row - 1);
    $dataSeriesValues[] = new DataSeriesValues('Number', $valuesRange, null, ($row - 6));
}

// Eje X = Clientes
$clientRange = "'Resumen mensual'!{$startColChar}6:{$startColChar}" . ($row - 1);
$xAxisTickValues[] = new DataSeriesValues('String', $clientRange, null, count($clients));

// Construir DataSeries
$series = new DataSeries(
    DataSeries::TYPE_BARCHART,       // Gráfico de barras
    DataSeries::GROUPING_PERCENT_STACKED,    // Apilado
    range(0, count($dataSeriesValues)-1), // Series indexes
    $dataSeriesLabels,               // Labels de cada serie
    $xAxisTickValues,                // Etiquetas X
    $dataSeriesValues                // Valores de la serie
);
$series->setPlotDirection(DataSeries::DIRECTION_COL);

// PlotArea
$plotArea = new PlotArea(null, [$series]);

// Leyenda
$legend = new Legend(Legend::POSITION_RIGHT, null, false);

// Título
$title = new Title('Escala de servicios por cliente');

// Crear el gráfico
$chart = new Chart(
    'chart1',
    $title,
    $legend,
    $plotArea,
    true,
    0,
    null,
    null
);

// Ubicación del gráfico en la hoja (a la derecha del bloque de tiempo)
// Shift chart position as well. Original J4->R20. Shift +9 columns.
// J(10)+9 = S(19). R(18)+9 = AA(27).
$chart->setTopLeftPosition(Coordinate::stringFromColumnIndex(10 + $colOffset) . '4');
$chart->setBottomRightPosition(Coordinate::stringFromColumnIndex(18 + $colOffset) . '20');

$sheet->addChart($chart);


/* =====================================================
   BLOQUE FINANCIERO
===================================================== */
$start = $row + 2;
$sheet->setCellValue("{$startColChar}{$start}", 'RESUMEN ECONÓMICO');
$sheet->getStyle("{$startColChar}{$start}")->getFont()->setBold(true);

$start++;
$headings = [
    'Cliente',
    'Total minutos',
    'Total horas',
    'Coste real €',
    'Presupuesto €',
    'Margen €',
    'Margen %',
    'Progreso actual %',
    'Progreso mes %',
    'Coste/hora €'
];

$sheet->fromArray($headings, null, "{$startColChar}{$start}");
$endHeadCol = Coordinate::stringFromColumnIndex(1 + 9 + $colOffset); // A(1) + 9 -> J + 9 -> S
$sheet->getStyle("{$startColChar}{$start}:{$endHeadCol}{$start}")->getFont()->setBold(true);

$r = $start + 1;

foreach ($clients as $client) {

    $cost = $data[$client]['total_cost'] ?? 0;
    $budget = 0;
    foreach ($services as $service) {
        if (isset($data[$client][$service])) {
            $budget += $data[$client][$service]['budget_monthly'];
        }
    }

    $colA = Coordinate::stringFromColumnIndex(1 + $colOffset);
    $colMins = Coordinate::stringFromColumnIndex(2 + $colOffset);
    $colHours = Coordinate::stringFromColumnIndex(3 + $colOffset);
    $colB = Coordinate::stringFromColumnIndex(4 + $colOffset);
    $colC = Coordinate::stringFromColumnIndex(5 + $colOffset);
    $colD = Coordinate::stringFromColumnIndex(6 + $colOffset);
    $colE = Coordinate::stringFromColumnIndex(7 + $colOffset);
    $colF = Coordinate::stringFromColumnIndex(8 + $colOffset);
    $colG = Coordinate::stringFromColumnIndex(9 + $colOffset);
    $colH = Coordinate::stringFromColumnIndex(10 + $colOffset);

    // Get total minutes for the client
    $totalHours = $data[$client]['total_hours'] ?? 0;
    $totalMins = $totalHours * 60;

    $sheet->setCellValue("{$colA}{$r}", $client);
    $sheet->setCellValue("{$colMins}{$r}", $totalMins);
    $sheet->setCellValue("{$colHours}{$r}", $totalHours);
    $sheet->setCellValue("{$colB}{$r}", $cost);
    $sheet->setCellValue("{$colC}{$r}", $budget);
    $sheet->setCellValue("{$colD}{$r}", "={$colC}{$r}-{$colB}{$r}");
    $sheet->setCellValue("{$colE}{$r}", "=IF({$colC}{$r}=0,0,{$colD}{$r}/{$colC}{$r})");

    // Progreso actual considerando días transcurridos
    $sheet->setCellValue(
        "{$colF}{$r}",
        "=IF({$colC}{$r}=0,0,IF({$daysElapsed}=0,0,({$colB}{$r}/({$colC}{$r}*{$daysElapsed}/{$daysInMonth}))))"
    );

    // Progreso mes %
    $sheet->setCellValue("{$colG}{$r}", "=IF({$colC}{$r}=0,0,{$colB}{$r}/{$colC}{$r})");

    // Coste/hora final usando total_hours del cliente
    $sheet->setCellValue("{$colH}{$r}", $totalHours > 0 ? round($cost / $totalHours, 2) : 0);

    $r++;
}

/* FORMATOS */
$sheet->getStyle("{$colHours}{$start}:{$colHours}{$r}")
      ->getNumberFormat()
      ->setFormatCode('#,##0.00');

$sheet->getStyle("{$colB}{$start}:{$colD}{$r}")
      ->getNumberFormat()
      ->setFormatCode('€ #,##0.00');

$sheet->getStyle("{$colE}{$start}:{$colG}{$r}")
      ->getNumberFormat()
      ->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

$sheet->getStyle("{$colH}{$start}:{$colH}{$r}")
      ->getNumberFormat()
      ->setFormatCode('€ #,##0.00');

/* CONDICIONALES MARGEN */
foreach ([$colD, $colE] as $col) {
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

    $sheet->getStyle("{$col}" . ($start + 1) . ":{$col}" . ($r - 1))
          ->setConditionalStyles([$green, $red]);
}

/* CONDICIONALES PROGRESO (tabla derecha - columnas ocultas) */
$range = "{$colF}" . ($start + 1) . ":{$colG}" . ($r - 1);
$firstCellRight = "{$colF}" . ($start + 1);

$redRight = new Conditional();
$redRight->setConditionType(Conditional::CONDITION_EXPRESSION)
    ->addCondition("{$firstCellRight}<0.7");
$redRight->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
$redRight->getStyle()->getFill()->getStartColor()->setARGB('FFFF5050');

$yellowRight = new Conditional();
$yellowRight->setConditionType(Conditional::CONDITION_EXPRESSION)
    ->addCondition("{$firstCellRight}>1"); // Amarillo solo si SUPERA el 100%
$yellowRight->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
$yellowRight->getStyle()->getFill()->getStartColor()->setARGB('FFFFCC00');

$greenRight = new Conditional();
$greenRight->setConditionType(Conditional::CONDITION_EXPRESSION)
    ->addCondition("{$firstCellRight}>=0.7");
$greenRight->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
$greenRight->getStyle()->getFill()->getStartColor()->setARGB('FF50C050');

$sheet->getStyle($range)->setConditionalStyles([$redRight, $yellowRight, $greenRight]);

/* APLICAR TAMBIÉN A LA TABLA IZQUIERDA (Progreso % - columna visible) */
// Misma lógica que progressConditionals() del reporte anual (CONDITION_CELLIS)
if ($rowL > 2) {
    $rangeLeft = "{$colPr}2:{$colPr}" . ($rowL - 1);

    // AMARILLO: > 100% (se evalúa primero para tener prioridad sobre verde)
    $yellowLeft = new Conditional();
    $yellowLeft->setConditionType(Conditional::CONDITION_CELLIS)
        ->setOperatorType(Conditional::OPERATOR_GREATERTHAN)
        ->addCondition('1');
    $yellowLeft->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
    $yellowLeft->getStyle()->getFill()->getStartColor()->setARGB('FFFFCC00'); // Amarillo

    // VERDE: >= 70% (cubre 70%-100% al evaluarse después del amarillo)
    $greenLeft = new Conditional();
    $greenLeft->setConditionType(Conditional::CONDITION_CELLIS)
        ->setOperatorType(Conditional::OPERATOR_GREATERTHANOREQUAL)
        ->addCondition('0.7');
    $greenLeft->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
    $greenLeft->getStyle()->getFill()->getStartColor()->setARGB('FF92D050'); // Verde

    // ROJO: < 70%
    $redLeft = new Conditional();
    $redLeft->setConditionType(Conditional::CONDITION_CELLIS)
        ->setOperatorType(Conditional::OPERATOR_LESSTHAN)
        ->addCondition('0.7');
    $redLeft->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
    $redLeft->getStyle()->getFill()->getStartColor()->setARGB('FFFF5050'); // Rojo

    // Orden: AMARILLO → VERDE → ROJO (igual que progressConditionals() del anual)
    $sheet->getStyle($rangeLeft)->setConditionalStyles([$yellowLeft, $greenLeft, $redLeft]);
}

/* AUTO SIZE */
foreach (range('A', $colPr) as $col) {
    if ($col !== 'A') {
        $sheet->getColumnDimension($col)->setWidth(10);
    }
}

/* OCULTAR SECCIÓN DERECHA (SOLICITUD JEFES) */
$hideStart = $colOffset; // Columna de separación
$hideEnd = $colOffset + 30; // Margen suficiente para cubrir gráficos y tablas
for ($i = $hideStart; $i <= $hideEnd; $i++) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setVisible(false);
}

/* DESCARGA */
$monthName = $monthsNames[$month] ?? $month;
$filename = "REPORTE_MENSUAL_{$monthName}-{$year}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);  // Muy importante
$writer->setPreCalculateFormulas(false); // Evitar que PHP calcule las fórmulas y se cuelgue
$writer->save('php://output');
exit;
?>

