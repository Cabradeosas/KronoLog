<?php
session_start();
require '../time_hours_connection.php';
require '../../vendor/autoload.php';
require_once '../../utility/auth.php';
auth::needs_role(['admin']);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
// Agrega estas clases para los gráficos
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

// Función auxiliar para contar días hábiles (Lunes a Viernes)
function getWorkingDays($startDate, $endDate) {
    $begin = new DateTime($startDate);
    $end   = new DateTime($endDate);
    $days  = 0;
    while ($begin <= $end) {
        if ($begin->format('N') < 6) $days++;
        $begin->modify('+1 day');
    }
    return $days;
}

// ===========================
// VALIDACIÓN DE POST
// ===========================
if (!isset($_POST['year'], $_POST['month'], $_POST['users'])) {
    die('Parámetros inválidos.');
}

$year = (int) $_POST['year'];
$month = (int) $_POST['month'];
$selectedUsers = $_POST['users'];

$startDate = date('Y-m-01', strtotime("$year-$month-01"));
$endDate = date('Y-m-t', strtotime("$year-$month-01"));

// ===========================
// CREAR EXCEL
// ===========================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
// Usar un nombre de hoja sin espacios para evitar problemas
$sheet->setTitle("Reporte");

// ===========================
// ENCABEZADO
// ===========================
$headers = [
    'Nombre Trabajador',
    'Coste Empresa',
    'Clientes',
    'Cuota de Clientes',
    'Horas trabajadas',
    'Días Vacaciones',
    'Días Ausentes',
    'Rentabilidad',
    '% Rentabilidad'
];

$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:I1')->getFont()->setBold(true);

// ===========================
// DATOS POR USUARIO
// ===========================
$row = 2;
$userNames = []; // Para almacenar nombres de usuarios para el gráfico

foreach ($selectedUsers as $userId) {
    // Usuario
    $stmt = $PDOconnection->prepare(
        "SELECT id, name, mensual_cost FROM user WHERE id = ?"
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        continue;
    }

    // ===========================
    // Clientes distintos
    // ===========================
    $stmt = $PDOconnection->prepare("
        SELECT COUNT(DISTINCT c.id)
        FROM time_worked tw
        LEFT JOIN client_service cs ON tw.client_service_id = cs.id
        LEFT JOIN client c ON cs.client_id = c.id
        WHERE tw.user_id = ? AND tw.date BETWEEN ? AND ?
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $num_clients = (int) ($stmt->fetchColumn() ?? 0);

    // ===========================
    // Cuota de clientes (servicios únicos)
    // ===========================
    $stmt = $PDOconnection->prepare("
        SELECT COALESCE(SUM(t.cost_per_month), 0)
        FROM (
            SELECT DISTINCT cs.id, cs.cost_per_month
            FROM time_worked tw
            INNER JOIN client_service cs ON tw.client_service_id = cs.id
            WHERE tw.user_id = ? AND tw.date BETWEEN ? AND ?
        ) AS t
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $clients_cost = (float) ($stmt->fetchColumn() ?? 0);

    // ===========================
    // Horas trabajadas
    // ===========================
    $stmt = $PDOconnection->prepare("
        SELECT COALESCE(SUM(minutes), 0)
        FROM time_worked
        WHERE user_id = ? AND date BETWEEN ? AND ?
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $total_minutes = (float) ($stmt->fetchColumn() ?? 0);
    $total_hours = $total_minutes / 60;

    // ===========================
    // DÍAS VACACIONES
    // ===========================
    $vacation_days = 0;
    $stmt = $PDOconnection->prepare("
        SELECT start_date, COALESCE(end_date, ?) as end_date
        FROM user_status
        WHERE user_id = ? AND motive = 'vacation'
        AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)
    ");
    $stmt->execute([$endDate, $userId, $endDate, $startDate]);
    
    while ($rowStatus = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $s = max(strtotime($rowStatus['start_date']), strtotime($startDate));
        $e = min(strtotime($rowStatus['end_date']), strtotime($endDate));
        if ($s <= $e) $vacation_days += getWorkingDays(date('Y-m-d', $s), date('Y-m-d', $e));
    }

    // ===========================
    // DÍAS AUSENTES
    // ===========================
    $absent_days = 0;
    $stmt = $PDOconnection->prepare("
        SELECT start_date, COALESCE(end_date, ?) as end_date
        FROM user_status
        WHERE user_id = ? AND motive = 'absent'
        AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)
    ");
    $stmt->execute([$endDate, $userId, $endDate, $startDate]);
    
    while ($rowStatus = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $s = max(strtotime($rowStatus['start_date']), strtotime($startDate));
        $e = min(strtotime($rowStatus['end_date']), strtotime($endDate));
        if ($s <= $e) $absent_days += getWorkingDays(date('Y-m-d', $s), date('Y-m-d', $e));
    }

    // ===========================
    // ESCRIBIR FILA
    // ===========================
    $mensual_cost = (float) $user['mensual_cost'];

    $sheet->setCellValue("A{$row}", $user['name']);
    $sheet->setCellValue("B{$row}", $mensual_cost);
    $sheet->setCellValue("C{$row}", $num_clients);
    $sheet->setCellValue("D{$row}", $clients_cost);
    $sheet->setCellValue("E{$row}", $total_hours);
    $sheet->setCellValue("F{$row}", $vacation_days);
    $sheet->setCellValue("G{$row}", $absent_days);

    // Rentabilidad (Excel)
    $sheet->setCellValue("H{$row}", "=D{$row}-B{$row}");
    $sheet->setCellValue("I{$row}", "=IF(B{$row}>0,H{$row}/B{$row},0)");

    // ===========================
    // GUARDAR DATOS PARA EL GRÁFICO
    // ===========================
    $userNames[] = $user['name'];

    // ===========================
    // FORMATOS
    // ===========================
    $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('€ #,##0.00');
    $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('€ #,##0.00');
    $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode('€ #,##0.00');
    $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

    $row++;
}

$highestRow = $sheet->getHighestRow();

// ===========================
// FORMATO CONDICIONAL
// ===========================
$green = new Conditional();
$green->setConditionType(Conditional::CONDITION_CELLIS)
    ->setOperatorType(Conditional::OPERATOR_GREATERTHAN)
    ->addCondition(0)
    ->getStyle()->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('C6EFCE');

$red = new Conditional();
$red->setConditionType(Conditional::CONDITION_CELLIS)
    ->setOperatorType(Conditional::OPERATOR_LESSTHAN)
    ->addCondition(0)
    ->getStyle()->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('FFC7CE');

foreach (['H', 'I'] as $col) {
    $styles = $sheet->getStyle("{$col}2:{$col}{$highestRow}")->getConditionalStyles();
    $styles[] = $green;
    $styles[] = $red;
    $sheet->getStyle("{$col}2:{$col}{$highestRow}")->setConditionalStyles($styles);
}

// Clientes > 0
$clientGreen = new Conditional();
$clientGreen->setConditionType(Conditional::CONDITION_CELLIS)
    ->setOperatorType(Conditional::OPERATOR_GREATERTHAN)
    ->addCondition(0)
    ->getStyle()->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('C6EFCE');

$stylesC = $sheet->getStyle("C2:C{$highestRow}")->getConditionalStyles();
$stylesC[] = $clientGreen;
$sheet->getStyle("C2:C{$highestRow}")->setConditionalStyles($stylesC);

// ===========================
// CREAR GRÁFICOS - FORMA ALTERNATIVA
// ===========================
if ($highestRow > 2 && count($userNames) > 0) {
    // Opción 1: Crear gráfico usando datos calculados directamente
    // Primero, calculamos los valores de rentabilidad manualmente
    
    // Obtener valores calculados de rentabilidad
    $rentabilidadValues = [];
    $porcentajeValues = [];
    
    for ($i = 2; $i <= $highestRow; $i++) {
        // Calcular rentabilidad directamente en PHP para evitar problemas con referencias
        $coste = $sheet->getCell("B{$i}")->getValue();
        $cuota = $sheet->getCell("D{$i}")->getValue();
        $rentabilidad = $cuota - $coste;
        $rentabilidadValues[] = $rentabilidad;
        
        // Calcular porcentaje
        $porcentaje = ($coste > 0) ? ($rentabilidad / $coste) : 0;
        $porcentajeValues[] = $porcentaje;
    }
    
    // Crear una nueva hoja para el gráfico
    $chartSheet = $spreadsheet->createSheet();
    $chartSheet->setTitle("Gráficos");
    
    // Escribir datos para el gráfico en la nueva hoja
    $chartSheet->setCellValue('A1', 'Trabajador');
    $chartSheet->setCellValue('B1', 'Rentabilidad (€)');
    $chartSheet->setCellValue('C1', 'Rentabilidad (%)');
    
    $chartRow = 2;
    foreach ($userNames as $index => $name) {
        $chartSheet->setCellValue("A{$chartRow}", $name);
        $chartSheet->setCellValue("B{$chartRow}", $rentabilidadValues[$index]);
        $chartSheet->setCellValue("C{$chartRow}", $porcentajeValues[$index]);
        $chartRow++;
    }
    
    // Formato para la nueva hoja
    $chartSheet->getStyle('A1:C1')->getFont()->setBold(true);
    $chartSheet->getStyle("B2:B{$chartRow}")->getNumberFormat()->setFormatCode('€ #,##0.00');
    $chartSheet->getStyle("C2:C{$chartRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
    
    // Crear gráfico de rentabilidad en euros
    $dataSeriesLabels1 = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Gráficos!$B$1', null, 1),
    ];
    
    $xAxisTickValues1 = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Gráficos!$A$2:$A$' . ($chartRow-1), null, count($userNames)),
    ];
    
    $dataSeriesValues1 = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Gráficos!$B$2:$B$' . ($chartRow-1), null, count($userNames)),
    ];

    $series1 = new DataSeries(
        DataSeries::TYPE_BARCHART,
        DataSeries::GROUPING_CLUSTERED,
        range(0, count($dataSeriesValues1) - 1),
        $dataSeriesLabels1,
        $xAxisTickValues1,
        $dataSeriesValues1
    );
    $series1->setPlotDirection(DataSeries::DIRECTION_COL);

    $plotArea1 = new PlotArea(null, [$series1]);
    $legend1 = new Legend(Legend::POSITION_RIGHT, null, false);
    $title1 = new Title('Rentabilidad por Trabajador (Euros)');

    $chart1 = new Chart(
        'chart1',
        $title1,
        $legend1,
        $plotArea1,
        true,
        0,
        null,
        null
    );

    $chart1->setTopLeftPosition('E2');
    $chart1->setBottomRightPosition('P20');
    $chartSheet->addChart($chart1);
    
    // Crear gráfico de rentabilidad en porcentaje
    $dataSeriesLabels2 = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Gráficos!$C$1', null, 1),
    ];
    
    $dataSeriesValues2 = [
        new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Gráficos!$C$2:$C$' . ($chartRow-1), null, count($userNames)),
    ];

    $series2 = new DataSeries(
        DataSeries::TYPE_BARCHART,
        DataSeries::GROUPING_CLUSTERED,
        range(0, count($dataSeriesValues2) - 1),
        $dataSeriesLabels2,
        $xAxisTickValues1, // Mismos nombres
        $dataSeriesValues2
    );
    $series2->setPlotDirection(DataSeries::DIRECTION_COL);

    $plotArea2 = new PlotArea(null, [$series2]);
    $legend2 = new Legend(Legend::POSITION_RIGHT, null, false);
    $title2 = new Title('Rentabilidad por Trabajador (%)');

    $chart2 = new Chart(
        'chart2',
        $title2,
        $legend2,
        $plotArea2,
        true,
        0,
        null,
        null
    );

    $chart2->setTopLeftPosition('E22');
    $chart2->setBottomRightPosition('P40');
    $chartSheet->addChart($chart2);
    
    // Auto size para la hoja de gráficos
    foreach (range('A', 'C') as $col) {
        $chartSheet->getColumnDimension($col)->setAutoSize(true);
    }
}

// ===========================
// AUTO SIZE
// ===========================
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ===========================
// DESCARGA
// ===========================
$filename = "Reporte_Trabajadores_{$month}_{$year}.xlsx";

// Necesitamos un writer especial para incluir gráficos
$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;