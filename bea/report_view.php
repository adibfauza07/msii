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
$isPrint = isset($_GET['print']) && $_GET['print'] === '1';
$pageTitle = $reportList[$reportId];
$currentPage = 'laporan';

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

include __DIR__ . '/inc/header.php';
?>
<div class="page-header-box">
    <h1><?php echo e($reportList[$reportId]); ?></h1>
    <ol class="breadcrumb breadcrumb-clean">
        <li><a href="laporan.php">Daftar Laporan</a></li>
        <li class="active">Pratinjau</li>
    </ol>
</div>
<div class="panel panel-clean">
    <div class="panel-heading">
        Periode <?php echo e($startDate); ?> s/d <?php echo e($endDate); ?>
        <?php if ($docType !== ''): ?> — Dokumen <?php echo e($docType); ?><?php endif; ?>
        <span class="pull-right no-print">
            <button class="btn btn-xs btn-default" onclick="window.print();"><i class="fa fa-print"></i> Cetak / Simpan PDF</button>
            <a class="btn btn-xs btn-success" href="export_excel.php?report=<?php echo (int) $reportId; ?>&amp;start=<?php echo e($startDate); ?>&amp;end=<?php echo e($endDate); ?>&amp;doc_type=<?php echo urlencode($docType); ?>"><i class="fa fa-file-excel-o"></i> Excel</a>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-condensed">
            <thead>
            <tr>
                <th>No</th>
                <th>No. Dokumen</th>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Arah/Mutasi</th>
                <th>Partner/Gudang</th>
                <th class="text-right">Jumlah</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-center text-muted">Tidak ada data untuk filter yang dipilih.</td></tr>
            <?php else: ?>
                <?php $no = 1; foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo e($row['no_dokumen']); ?></td>
                    <td><?php echo e($row['tanggal']); ?></td>
                    <td><?php echo e($row['jenis']); ?></td>
                    <td><?php echo e($row['arah']); ?></td>
                    <td><?php echo e($row['supplier']); ?></td>
                    <td class="text-right"><?php echo format_number_id($row['jumlah']); ?></td>
                    <td><?php echo e($row['status']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="footer-note">PDF dibuat melalui dialog cetak browser: pilih tujuan “Save as PDF”.</p>
<?php if ($isPrint): ?>
<script>window.onload = function () { window.print(); };</script>
<?php endif; ?>
<?php include __DIR__ . '/inc/footer.php'; ?>
