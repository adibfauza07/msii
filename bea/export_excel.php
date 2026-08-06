<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/data.php';
require_once __DIR__ . '/inc/repository.php';

$reportId = isset($_GET['report']) ? (int) $_GET['report'] : 1;
if (!isset($reportList[$reportId])) {
    $reportId = 1;
}
$startDate = input_date('start', date('Y-m-01'));
$endDate = input_date('end', date('Y-m-d'));
$docType = isset($_GET['doc_type']) && isset($documentTypes[$_GET['doc_type']]) ? $_GET['doc_type'] : '';
$rows = $recentDocumentsDemo;

if ($dbConnected) {
    $dbRows = inventory_get_report_rows($conn, $reportId, $startDate, $endDate, $docType);
    if ($dbRows) {
        $rows = $dbRows;
    } else {
        $rows = array();
    }
} elseif ($docType !== '') {
    $filtered = array();
    foreach ($rows as $row) {
        if ($row['jenis'] === $docType) {
            $filtered[] = $row;
        }
    }
    $rows = $filtered;
}

$fileName = 'laporan_' . $reportId . '_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";
?>
<table border="1">
    <tr><th colspan="8"><?php echo e($reportList[$reportId]); ?></th></tr>
    <tr><td colspan="8">Periode: <?php echo e($startDate); ?> s/d <?php echo e($endDate); ?><?php echo $docType !== '' ? ' | Dokumen: ' . e($docType) : ''; ?></td></tr>
    <tr>
        <th>No</th><th>No. Dokumen</th><th>Tanggal</th><th>Jenis</th><th>Arah/Mutasi</th><th>Partner/Gudang</th><th>Jumlah</th><th>Status</th>
    </tr>
    <?php $no = 1; foreach ($rows as $row): ?>
    <tr>
        <td><?php echo $no++; ?></td>
        <td><?php echo e($row['no_dokumen']); ?></td>
        <td><?php echo e($row['tanggal']); ?></td>
        <td><?php echo e($row['jenis']); ?></td>
        <td><?php echo e($row['arah']); ?></td>
        <td><?php echo e($row['supplier']); ?></td>
        <td><?php echo (int) $row['jumlah']; ?></td>
        <td><?php echo e($row['status']); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
