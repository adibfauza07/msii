<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/data.php';

$pageTitle   = 'Daftar Laporan - IT Inventory';
$currentPage = 'laporan';

$today    = date('Y-m-d');
$firstDay = date('Y-m-01');

$startDate = input_date('start', $firstDay);
$endDate   = input_date('end', $today);

$docType = isset($_GET['doc_type']) &&
           isset($documentTypes[$_GET['doc_type']])
    ? $_GET['doc_type']
    : 'BC2.3';

function get_bc_document_value($value)
{
    $map = array(
        'BC2.3'     => 'BC.2.3',
        'BC4.0'     => 'BC.4.0',
        'BC2.5'     => 'BC.2.5',
        'BC2.6.1'   => 'BC.2.6.1',
        'BC2.6.2'   => 'BC.2.6.2',
        'BC2.7'     => 'BC.2.7',
        'BC4.1'     => 'BC.4.1',
        'BC3.0'     => 'BC.3.0',
        'PIB'       => 'PIB',
        'PEB'       => 'PEB',
        'Internal'  => 'Internal',
        ''          => ''
    );

    return isset($map[$value]) ? $map[$value] : $value;
}

$bcDocument = get_bc_document_value($docType);

/*
|--------------------------------------------------------------------------
| PARAMETER DASAR
|--------------------------------------------------------------------------
*/
$documentReportParams = array(
    'tglawal'  => $startDate,
    'tglakhir' => $endDate,
    'dokumen'  => $bcDocument
);

$mutationReportParams = array(
    'tglawal'  => $startDate,
    'tglakhir' => $endDate
);

/*
|--------------------------------------------------------------------------
| PARAMETER WIP
|--------------------------------------------------------------------------
| start_date ikut transaksi.
| end_date adalah tanggal batas akhir dan tidak ikut transaksi.
|
| Contoh:
| start = 2026-06-01
| end   = 2026-07-01
| transaksi WIP = 01-Jun-2026 s/d 30-Jun-2026.
*/
$wipReportParams = array(
    'start_date' => $startDate,
    'end_date'   => $endDate
);

/*
|--------------------------------------------------------------------------
| REPORT 1 - PEMASUKAN
|--------------------------------------------------------------------------
*/
$pemasukanUrl = 'pemasukan.php?' . http_build_query(
    $documentReportParams
);

$pemasukanPdfUrl = 'pemasukan.php?' . http_build_query(
    array_merge(
        $documentReportParams,
        array('print' => 1)
    )
);

$pemasukanExcelUrl = 'pemasukan.php?' . http_build_query(
    array_merge(
        $documentReportParams,
        array('format' => 'excel')
    )
);

/*
|--------------------------------------------------------------------------
| REPORT 2 - PENGELUARAN
|--------------------------------------------------------------------------
*/
$pengeluaranUrl = 'pengeluaran.php?' . http_build_query(
    $documentReportParams
);

$pengeluaranPdfUrl = 'pengeluaran.php?' . http_build_query(
    array_merge(
        $documentReportParams,
        array('print' => 1)
    )
);

$pengeluaranExcelUrl = 'pengeluaran.php?' . http_build_query(
    array_merge(
        $documentReportParams,
        array('format' => 'excel')
    )
);

/*
|--------------------------------------------------------------------------
| REPORT 3 - MUTASI BAHAN BAKU DAN PENOLONG
|--------------------------------------------------------------------------
| Report ini tidak menggunakan filter jenis dokumen BC.
*/
$mutasiBahanBakuUrl = 'mutasi_bahanbaku.php?' . http_build_query(
    $mutationReportParams
);

$mutasiBahanBakuPdfUrl = 'mutasi_bahanbaku.php?' . http_build_query(
    array_merge(
        $mutationReportParams,
        array('print' => 1)
    )
);

$mutasiBahanBakuExcelUrl = 'mutasi_bahanbaku.php?' . http_build_query(
    array_merge(
        $mutationReportParams,
        array('format' => 'excel')
    )
);


/*
|--------------------------------------------------------------------------
| REPORT 4 - MUTASI BARANG JADI
|--------------------------------------------------------------------------
| Report ini tidak menggunakan filter jenis dokumen BC.
*/
$mutasiBarangUrl = 'mutasi_barang.php?' . http_build_query(
    $mutationReportParams
);

$mutasiBarangPdfUrl = 'mutasi_barang.php?' . http_build_query(
    array_merge(
        $mutationReportParams,
        array('print' => 1)
    )
);

$mutasiBarangExcelUrl = 'mutasi_barang.php?' . http_build_query(
    array_merge(
        $mutationReportParams,
        array('format' => 'excel')
    )
);

/*
|--------------------------------------------------------------------------
| REPORT 7 - WIP MATERIAL
|--------------------------------------------------------------------------
| Material out WHS ke produksi dikurangi actual produksi
| yang dikonversi melalui BOM.
*/
$wipUrl = 'wip.php?' . http_build_query(
    $wipReportParams
);

$wipPdfUrl = 'wip.php?' . http_build_query(
    array_merge(
        $wipReportParams,
        array('print' => 1)
    )
);

$wipExcelUrl = 'wip.php?' . http_build_query(
    array_merge(
        $wipReportParams,
        array('format' => 'excel')
    )
);

include __DIR__ . '/inc/header.php';
?>

<div class="page-header-box">
    <h1>Daftar Laporan</h1>

    <ol class="breadcrumb breadcrumb-clean">
        <li><i class="fa fa-home"></i></li>
        <li class="active">IT Inventory</li>
    </ol>
</div>

<form
    method="get"
    action="laporan.php"
    class="filter-box form-horizontal"
>
    <div class="row">

        <div class="col-sm-8">
            <div class="form-group form-group-sm">

                <label class="col-sm-2 control-label">
                    Periode
                </label>

                <div class="col-sm-4">
                    <input
                        type="date"
                        name="start"
                        class="form-control input-sm"
                        value="<?php echo e($startDate); ?>"
                    >
                </div>

                <label class="col-sm-1 control-label">
                    S/D
                </label>

                <div class="col-sm-4">
                    <input
                        type="date"
                        name="end"
                        class="form-control input-sm"
                        value="<?php echo e($endDate); ?>"
                    >
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group form-group-sm">

                <label class="col-sm-4 control-label">
                    Dokumen
                </label>

                <div class="col-sm-8">
                    <select
                        name="doc_type"
                        class="form-control input-sm"
                        onchange="this.form.submit();"
                    >
                        <?php foreach ($documentTypes as $value => $label): ?>

                            <option
                                value="<?php echo e($value); ?>"
                                <?php echo selected($value, $docType); ?>
                            >
                                <?php echo e($label); ?>
                            </option>

                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12 text-right">

            <button
                type="submit"
                class="btn btn-primary btn-sm"
            >
                <i class="fa fa-search"></i>
                Tampilkan
            </button>
        </div>
    </div>
</form>

<ul class="nav nav-tabs report-tabs">
    <li class="active">
        <a href="#">
            <i class="fa fa-list"></i>
            List Laporan
        </a>
    </li>
</ul>

<div class="report-panel">
    <ol class="report-list">

        <?php foreach ($reportList as $id => $reportName): ?>

        <li>
            <div class="row">

                <div class="col-sm-8">

                    <?php if ((int) $id === 1): ?>

                        <a href="<?php echo e($pemasukanUrl); ?>">
                            <?php echo e($reportName); ?>
                        </a>

                    <?php elseif ((int) $id === 2): ?>

                        <a href="<?php echo e($pengeluaranUrl); ?>">
                            <?php echo e($reportName); ?>
                        </a>

                    <?php elseif ((int) $id === 3): ?>

                        <a href="<?php echo e($mutasiBahanBakuUrl); ?>">
                            <?php echo e($reportName); ?>
                        </a>

                    <?php elseif ((int) $id === 4): ?>

                        <a href="<?php echo e($mutasiBarangUrl); ?>">
                            <?php echo e($reportName); ?>
                        </a>

                    <?php elseif ((int) $id === 7): ?>

                        <a href="<?php echo e($wipUrl); ?>">
                            <?php echo e($reportName); ?>
                        </a>

                    <?php else: ?>

                        <a
                            href="report_view.php?report=<?php echo (int) $id; ?>&amp;start=<?php echo e($startDate); ?>&amp;end=<?php echo e($endDate); ?>&amp;doc_type=<?php echo urlencode($docType); ?>"
                        >
                            <?php echo e($reportName); ?>
                        </a>

                    <?php endif; ?>
                </div>

                <div class="col-sm-4 report-actions text-right">

                    <?php if ((int) $id === 1): ?>

                        <a
                            href="<?php echo e($pemasukanPdfUrl); ?>"
                            target="_blank"
                        >
                            <i class="fa fa-file-pdf-o"></i>
                            Pdf
                        </a>

                        <span>|</span>

                        <a href="<?php echo e($pemasukanExcelUrl); ?>">
                            <i class="fa fa-file-excel-o"></i>
                            Excel
                        </a>

                    <?php elseif ((int) $id === 2): ?>

                        <a
                            href="<?php echo e($pengeluaranPdfUrl); ?>"
                            target="_blank"
                        >
                            <i class="fa fa-file-pdf-o"></i>
                            Pdf
                        </a>

                        <span>|</span>

                        <a href="<?php echo e($pengeluaranExcelUrl); ?>">
                            <i class="fa fa-file-excel-o"></i>
                            Excel
                        </a>

                    <?php elseif ((int) $id === 3): ?>

                        <a
                            href="<?php echo e($mutasiBahanBakuPdfUrl); ?>"
                            target="_blank"
                        >
                            <i class="fa fa-file-pdf-o"></i>
                            Pdf
                        </a>

                        <span>|</span>

                        <a href="<?php echo e($mutasiBahanBakuExcelUrl); ?>">
                            <i class="fa fa-file-excel-o"></i>
                            Excel
                        </a>

                    <?php elseif ((int) $id === 4): ?>

                        <a
                            href="<?php echo e($mutasiBarangPdfUrl); ?>"
                            target="_blank"
                        >
                            <i class="fa fa-file-pdf-o"></i>
                            Pdf
                        </a>

                        <span>|</span>

                        <a href="<?php echo e($mutasiBarangExcelUrl); ?>">
                            <i class="fa fa-file-excel-o"></i>
                            Excel
                        </a>

                    <?php elseif ((int) $id === 7): ?>

                        <a
                            href="<?php echo e($wipPdfUrl); ?>"
                            target="_blank"
                        >
                            <i class="fa fa-file-pdf-o"></i>
                            Pdf
                        </a>

                        <span>|</span>

                        <a href="<?php echo e($wipExcelUrl); ?>">
                            <i class="fa fa-file-excel-o"></i>
                            Excel
                        </a>

                    <?php else: ?>

                        <a
                            href="report_view.php?report=<?php echo (int) $id; ?>&amp;start=<?php echo e($startDate); ?>&amp;end=<?php echo e($endDate); ?>&amp;doc_type=<?php echo urlencode($docType); ?>&amp;print=1"
                            target="_blank"
                        >
                            <i class="fa fa-file-pdf-o"></i>
                            Pdf
                        </a>

                        <span>|</span>

                        <a
                            href="export_excel.php?report=<?php echo (int) $id; ?>&amp;start=<?php echo e($startDate); ?>&amp;end=<?php echo e($endDate); ?>&amp;doc_type=<?php echo urlencode($docType); ?>"
                        >
                            <i class="fa fa-file-excel-o"></i>
                            Excel
                        </a>

                    <?php endif; ?>
                </div>
            </div>
        </li>

        <?php endforeach; ?>
    </ol>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
