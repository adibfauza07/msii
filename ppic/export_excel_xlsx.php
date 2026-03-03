<?php
require_once "../config/database.php";

// autoload PhpSpreadsheet
require_once "../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$cust  = $_GET['cust'] ?? '';
$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->setCellValue('A1', 'PART_NUM');
$sheet->setCellValue('B1', 'PART_NO');
$sheet->setCellValue('C1', 'PART_NAME');
$sheet->setCellValue('D1', 'BAL_QTY');
$sheet->setCellValue('E1', 'DAILY_SCH');
$sheet->setCellValue('F1', 'BALANCE');

$sql = "{CALL SP_DI_PART_new(?, ?, ?)}";
$params = array($cust, $start, $end);
$stmt = sqlsrv_query($conn, $sql, $params);

$rowNum = 2;
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $sheet->setCellValue('A'.$rowNum, $r['PART_NUM']);
    $sheet->setCellValue('B'.$rowNum, $r['PART_NO']);
    $sheet->setCellValue('C'.$rowNum, $r['PART_NAME']);
    $sheet->setCellValue('D'.$rowNum, $r['BAL_QTY']);
    $sheet->setCellValue('E'.$rowNum, $r['DAILY_SCH']);
    $sheet->setCellValue('F'.$rowNum, $r['BALANCE']);
    $rowNum++;
}

$filename = "DI_PART_{$cust}_{$start}_{$end}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
