<?php
/**
 * LAPORAN MUTASI BARANG SISA / SCRAP
 * PHP 5.4 + SQLSRV + SQL Server 2008 + AdminLTE Bootstrap
 *
 * Lokasi:
 *   /msii/bea/mutasi_scrap.php
 *
 * Stored procedure:
 *   dbo.sp_laporan_mutasi_scrap
 */

/* =========================================================
 * SESSION DAN LOGIN PROTECTION
 * ========================================================= */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$loginUrl = "/msii/bea/login.php";

if (
    !isset($_SESSION['db_user']) ||
    trim($_SESSION['db_user']) == "" ||
    !isset($_SESSION['active_module']) ||
    $_SESSION['active_module'] != "it_inventory"
) {
    header("Location: " . $loginUrl);
    exit();
}

/* =========================================================
 * KONEKSI DATABASE
 * ========================================================= */
require_once __DIR__ . '/config/database.php';

if (!isset($conn) || $conn === false) {
    header("Location: " . $loginUrl . "?error=session_expired");
    exit();
}

/* =========================================================
 * FUNGSI BANTU
 * ========================================================= */
function mscrap_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function mscrap_valid_date($value)
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $parts = explode("-", $value);
    if (count($parts) != 3) {
        return false;
    }
    return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
}

function mscrap_display_date($value)
{
    if ($value instanceof DateTime) {
        return $value->format("d-m-Y");
    }
    if ($value === null || $value === "") {
        return "";
    }
    $time = strtotime((string) $value);
    if ($time === false) {
        return (string) $value;
    }
    return date("d-m-Y", $time);
}

function mscrap_number($value, $decimal)
{
    if ($value === null || $value === "") {
        return number_format(0, $decimal, ".", ",");
    }
    return number_format((float) $value, $decimal, ".", ",");
}

function mscrap_contains($haystack, $needle)
{
    $haystack = (string) $haystack;
    $needle   = (string) $needle;
    if ($needle === "") {
        return true;
    }
    if (function_exists("mb_stripos")) {
        return mb_stripos($haystack, $needle, 0, "UTF-8") !== false;
    }
    return stripos($haystack, $needle) !== false;
}

function mscrap_month_period($dateFrom, $dateTo)
{
    $fromTime = strtotime($dateFrom);
    $toTime   = strtotime($dateTo);
    if ($fromTime === false || $toTime === false) {
        return 1;
    }
    $fromYear  = (int) date("Y", $fromTime);
    $fromMonth = (int) date("n", $fromTime);
    $toYear    = (int) date("Y", $toTime);
    $toMonth   = (int) date("n", $toTime);
    
    $period = (($toYear - $fromYear) * 12) + ($toMonth - $fromMonth) + 1;
    return ($period < 1) ? 1 : $period;
}

function mscrap_status_rank($row)
{
    $status = isset($row["KETERANGAN"]) ? strtoupper(trim($row["KETERANGAN"])) : "";
    return ($status == "TIDAK SESUAI") ? 1 : 0;
}

function mscrap_sort_not_sesuai_last($a, $b)
{
    $rankA = mscrap_status_rank($a);
    $rankB = mscrap_status_rank($b);

    if ($rankA != $rankB) {
        return ($rankA < $rankB) ? -1 : 1;
    }
    $orderA = isset($a["__ORIGINAL_ORDER"]) ? (int) $a["__ORIGINAL_ORDER"] : 0;
    $orderB = isset($b["__ORIGINAL_ORDER"]) ? (int) $b["__ORIGINAL_ORDER"] : 0;
    if ($orderA == $orderB) {
        return 0;
    }
    return ($orderA < $orderB) ? -1 : 1;
}

function mscrap_sqlsrv_error()
{
    $errors = sqlsrv_errors();
    if (!is_array($errors)) {
        return "Kesalahan SQL Server tidak diketahui.";
    }
    $messages = array();
    foreach ($errors as $error) {
        $messages[] = "[" . $error["SQLSTATE"] . "] " . $error["code"] . " - " . $error["message"];
    }
    return implode("\n", $messages);
}

/* =========================================================
 * PARAMETER FILTER
 * ========================================================= */
$today       = date("Y-m-d");
$defaultFrom = date("Y-m-01");

$tglAwal = isset($_GET["tglawal"]) ? trim($_GET["tglawal"]) : $defaultFrom;
$tglAkhir = isset($_GET["tglakhir"]) ? trim($_GET["tglakhir"]) : $today;
$cari = isset($_GET["cari"]) ? trim($_GET["cari"]) : "";

$stockAnalysisUrl = "/msii/bea/print_stock_analysis.php";
$format = isset($_GET["format"]) ? strtolower(trim($_GET["format"])) : "html";
$autoPrint = isset($_GET["print"]) && $_GET["print"] == "1";

$errors = array();

if (!mscrap_valid_date($tglAwal)) {
    $errors[] = "Tanggal awal tidak valid.";
}
if (!mscrap_valid_date($tglAkhir)) {
    $errors[] = "Tanggal akhir tidak valid.";
}
if (mscrap_valid_date($tglAwal) && mscrap_valid_date($tglAkhir) && strtotime($tglAwal) >= strtotime($tglAkhir)) {
    $errors[] = "Tanggal stok akhir harus lebih besar dari tanggal stok awal.";
}
if (strlen($cari) > 150) {
    $errors[] = "Kata pencarian terlalu panjang.";
}

$transactionEndDate = mscrap_valid_date($tglAkhir) ? date("Y-m-d", strtotime($tglAkhir . " -1 day")) : $tglAkhir;
$stockAnalysisPeriod = mscrap_month_period($tglAwal, $tglAkhir);

/* =========================================================
 * PANGGIL STORED PROCEDURE
 * ========================================================= */
$rows = array();
$queryError = "";

if (count($errors) == 0) {
    $sql = "{CALL dbo.sp_laporan_mutasi_scrap(?, ?)}";
    $params = array($tglAwal, $tglAkhir);

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $queryError = mscrap_sqlsrv_error();
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}

/* =========================================================
 * AUTOCOMPLETE, PENCARIAN, DAN PENGURUTAN STATUS
 * ========================================================= */
$allRows = $rows;
$autocompleteNames = array();

foreach ($allRows as $autocompleteRow) {
    $itemName = isset($autocompleteRow["NAMA_BARANG"]) ? trim($autocompleteRow["NAMA_BARANG"]) : "";
    $itemCode = isset($autocompleteRow["KODE_BARANG"]) ? trim($autocompleteRow["KODE_BARANG"]) : "";

    if ($itemName != "") {
        $autocompleteKey = strtolower($itemName);
        if (!isset($autocompleteNames[$autocompleteKey])) {
            $autocompleteNames[$autocompleteKey] = array(
                "name" => $itemName,
                "code" => $itemCode
            );
        }
    }
}

uasort($autocompleteNames, function ($a, $b) {
    return strcasecmp($a["name"], $b["name"]);
});

if ($cari != "") {
    $filteredRows = array();
    foreach ($rows as $searchRow) {
        $itemCode = isset($searchRow["KODE_BARANG"]) ? $searchRow["KODE_BARANG"] : "";
        $itemName = isset($searchRow["NAMA_BARANG"]) ? $searchRow["NAMA_BARANG"] : "";

        if (mscrap_contains($itemCode, $cari) || mscrap_contains($itemName, $cari) || mscrap_contains($itemCode . " - " . $itemName, $cari)) {
            $filteredRows[] = $searchRow;
        }
    }
    $rows = $filteredRows;
}

foreach ($rows as $rowIndex => $sortRow) {
    $rows[$rowIndex]["__ORIGINAL_ORDER"] = $rowIndex;
}
usort($rows, "mscrap_sort_not_sesuai_last");
foreach ($rows as $rowIndex => $sortRow) {
    unset($rows[$rowIndex]["__ORIGINAL_ORDER"]);
}

/* =========================================================
 * EXPORT EXCEL
 * ========================================================= */
if ($format == "excel" && count($errors) == 0 && $queryError == "") {
    $filename = "mutasi_scrap_" . $tglAwal . "_sd_" . $tglAkhir . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 10px; }
        th, td { border: 1px solid #000000; padding: 3px; }
        .title { border: 0; font-size: 12px; font-weight: bold; }
        .center { text-align: center; }
        .number { mso-number-format: "\#\,\#\#0\.00"; text-align: right; }
        .text { mso-number-format: "\@"; }
    </style>
</head>
<body>
<table>
    <tr><td class="title" colspan="12">LAPORAN PERTANGGUNGJAWABAN BARANG SISA / SCRAP</td></tr>
    <tr><td class="title" colspan="12">KAWASAN BERIKAT PT. IMC TEKNO INDONESIA</td></tr>
    <tr>
        <td class="title" colspan="12">
            Periode <?php echo mscrap_h(mscrap_display_date($tglAwal)); ?> s/d <?php echo mscrap_h(mscrap_display_date($tglAkhir)); ?>
        </td>
    </tr>
    <tr><td colspan="12"></td></tr>
    <tr>
        <th>NO</th><th>KODE BARANG</th><th>NAMA BARANG</th><th>SATUAN</th>
        <th>SALDO AWAL<br><?php echo mscrap_h(mscrap_display_date($tglAwal)); ?></th>
        <th>PEMASUKAN</th><th>PENGELUARAN</th><th>PENYESUAIAN</th>
        <th>SALDO AKHIR<br><?php echo mscrap_h(mscrap_display_date($tglAkhir)); ?></th>
        <th>STOK OPNAME</th><th>SELISIH</th><th>KETERANGAN</th>
    </tr>
    <?php
    $no = 1;
    foreach ($rows as $row) {
    ?>
        <tr>
            <td class="center"><?php echo $no++; ?></td>
            <td class="text"><?php echo mscrap_h(isset($row["KODE_BARANG"]) ? $row["KODE_BARANG"] : ""); ?></td>
            <td class="text"><?php echo mscrap_h(isset($row["NAMA_BARANG"]) ? $row["NAMA_BARANG"] : ""); ?></td>
            <td class="center"><?php echo mscrap_h(isset($row["SATUAN"]) ? $row["SATUAN"] : ""); ?></td>
            <td class="number"><?php echo (isset($row["SALDO_AWAL"]) ? (float)$row["SALDO_AWAL"] : 0); ?></td>
            <td class="number"><?php echo (isset($row["MASUK"]) ? (float)$row["MASUK"] : 0); ?></td>
            <td class="number"><?php echo (isset($row["KELUAR"]) ? (float)$row["KELUAR"] : 0); ?></td>
            <td class="number"><?php echo (isset($row["PENYESUAIAN"]) ? (float)$row["PENYESUAIAN"] : 0); ?></td>
            <td class="number"><?php echo (isset($row["SALDO_AKHIR"]) ? (float)$row["SALDO_AKHIR"] : 0); ?></td>
            <td class="number"><?php echo (isset($row["STOK_OPNAME"]) ? (float)$row["STOK_OPNAME"] : 0); ?></td>
            <td class="number"><?php echo (isset($row["SELISIH"]) ? (float)$row["SELISIH"] : 0); ?></td>
            <td class="text"><?php echo mscrap_h(isset($row["KETERANGAN"]) ? $row["KETERANGAN"] : ""); ?></td>
        </tr>
    <?php } ?>
    <?php if (count($rows) == 0) { ?>
        <tr><td colspan="12" class="center">Data tidak ditemukan.</td></tr>
    <?php } ?>
</table>
</body>
</html>
<?php
    exit();
}

/* =========================================================
 * URL BANTU EXCEL
 * ========================================================= */
$queryParams = array(
    "tglawal"  => $tglAwal,
    "tglakhir" => $tglAkhir,
    "cari"     => $cari
);
$excelUrl = "mutasi_scrap.php?" . http_build_query(array_merge($queryParams, array("format" => "excel")));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Mutasi Scrap</title>

    <!-- CSS Bootstrap 3 & FontAwesome -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- CSS AdminLTE 2 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/2.4.18/css/AdminLTE.min.css">

    <style>
        body { background: #ecf0f5; padding: 15px; font-family: 'Source Sans Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        
        .table-mutasi th { background-color: #f4f4f4; text-align: center; vertical-align: middle !important; font-size: 12px; }
        .table-mutasi td { font-size: 12px; vertical-align: middle !important; }
        .report-header { text-align: center; margin-bottom: 20px; }
        .report-header h3, .report-header h4, .report-header h5 { margin: 5px 0; font-weight: bold; }
        
        /* Status Colors */
        .status-sesuai { color: #218838; font-weight: bold; }
        .status-tidak { color: #c82333; font-weight: bold; }
        .status-belum { color: #856404; font-weight: bold; }
        
        /* Links */
        .trace-link, .item-trace-link { font-weight: bold; text-decoration: none; }
        .trace-link { color: inherit; border-bottom: 1px dotted currentColor; }
        .item-trace-link { color: #3c8dbc; }
        .trace-link:hover, .item-trace-link:hover { text-decoration: underline; color: #23527c; }

        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { background: #fff !important; padding: 0 !important; }
            .no-print { display: none !important; }
            .box { border: none !important; box-shadow: none !important; }
            .table-mutasi th, .table-mutasi td { border: 1px solid #000 !important; font-size: 10px !important; padding: 4px !important; }
            .table-mutasi th { background-color: #e0e0e0 !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="row no-print">
    <div class="col-xs-12">
        <!-- FILTER BOX -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-filter"></i> Filter Mutasi Barang Sisa / Scrap</h3>
                <div class="box-tools pull-right">
                    <span class="text-muted" style="margin-right: 15px;">
                        Login: <strong><?php echo mscrap_h($_SESSION["db_user"]); ?></strong>
                    </span>
                </div>
            </div>
            <div class="box-body">
                <form method="get" action="mutasi_scrap.php" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <label for="tglawal" style="margin-right: 5px;">Stok Awal</label>
                        <input type="date" name="tglawal" id="tglawal" class="form-control input-sm" value="<?php echo mscrap_h($tglAwal); ?>" required>
                    </div>
                    
                    <div class="form-group" style="margin-right: 10px;">
                        <label for="tglakhir" style="margin-right: 5px;">Stok Akhir</label>
                        <input type="date" name="tglakhir" id="tglakhir" class="form-control input-sm" value="<?php echo mscrap_h($tglAkhir); ?>" required>
                    </div>

                    <div class="form-group" style="margin-right: 15px;">
                        <label for="cari" style="margin-right: 5px;">Nama Barang</label>
                        <input type="text" name="cari" id="cari" class="form-control input-sm" value="<?php echo mscrap_h($cari); ?>" placeholder="Ketik nama atau kode..." list="nama-barang-list" autocomplete="off" style="width: 250px;">
                        <datalist id="nama-barang-list">
                            <?php foreach ($autocompleteNames as $autocompleteItem) { ?>
                                <option value="<?php echo mscrap_h($autocompleteItem["name"]); ?>" label="<?php echo mscrap_h($autocompleteItem["code"]); ?>"></option>
                            <?php } ?>
                        </datalist>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Tampilkan</button>
                    <a href="mutasi_scrap.php?tglawal=<?php echo urlencode($tglAwal); ?>&amp;tglakhir=<?php echo urlencode($tglAkhir); ?>" class="btn btn-warning btn-sm"><i class="fa fa-refresh"></i> Reset</a>
                    <button type="button" class="btn btn-default btn-sm" onclick="window.print();"><i class="fa fa-print"></i> Cetak PDF</button>
                    <a href="<?php echo mscrap_h($excelUrl); ?>" class="btn btn-success btn-sm"><i class="fa fa-file-excel-o"></i> Excel</a>
                    <a href="index.php" class="btn btn-default btn-sm pull-right"><i class="fa fa-arrow-left"></i> Kembali</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (count($errors) > 0) { ?>
    <div class="alert alert-danger alert-dismissible no-print">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4><i class="icon fa fa-ban"></i> Filter tidak valid!</h4>
        <?php echo nl2br(mscrap_h(implode("\n", $errors))); ?>
    </div>
<?php } ?>

<?php if ($queryError != "") { ?>
    <div class="alert alert-danger alert-dismissible no-print">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4><i class="icon fa fa-warning"></i> Stored procedure gagal dijalankan!</h4>
        <?php echo nl2br(mscrap_h($queryError)); ?>
    </div>
<?php } ?>

<?php if (count($errors) == 0 && $queryError == "") { ?>
<div class="row">
    <div class="col-xs-12">
        <!-- LAPORAN BOX -->
        <div class="box box-success">
            <div class="box-body">
                
                <!-- HEADER PRINT -->
                <div class="report-header">
                    <h3>LAPORAN PERTANGGUNGJAWABAN BARANG SISA / SCRAP</h3>
                    <h4>KAWASAN BERIKAT PT. IMC TEKNO INDONESIA</h4>
                    <h5>
                        Stok Awal: <?php echo mscrap_h(mscrap_display_date($tglAwal)); ?> | 
                        Transaksi: <?php echo mscrap_h(mscrap_display_date($tglAwal)); ?> s/d <?php echo mscrap_h(mscrap_display_date($transactionEndDate)); ?> | 
                        Stok Akhir: <?php echo mscrap_h(mscrap_display_date($tglAkhir)); ?>
                        <?php if ($cari != "") { echo "<br>Pencarian: " . mscrap_h($cari); } ?>
                    </h5>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover table-mutasi">
                        <thead>
                            <tr>
                                <th>NO</th>
                                <th>KODE BARANG</th>
                                <th>NAMA BARANG</th>
                                <th>SATUAN</th>
                                <th>SALDO AWAL<br><small><?php echo mscrap_h(mscrap_display_date($tglAwal)); ?></small></th>
                                <th>PEMASUKAN</th>
                                <th>PENGELUARAN</th>
                                <th>PENYESUAIAN</th>
                                <th>SALDO AKHIR<br><small><?php echo mscrap_h(mscrap_display_date($tglAkhir)); ?></small></th>
                                <th>STOK OPNAME</th>
                                <th>SELISIH</th>
                                <th>KETERANGAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            foreach ($rows as $row) {
                                $status = isset($row["KETERANGAN"]) ? strtoupper(trim($row["KETERANGAN"])) : "";
                                $statusClass = "";

                                if ($status == "SESUAI") {
                                    $statusClass = "status-sesuai";
                                } elseif ($status == "TIDAK SESUAI") {
                                    $statusClass = "status-tidak";
                                } elseif ($status == "BELUM OPNAME") {
                                    $statusClass = "status-belum";
                                }

                                $itemCode = isset($row["KODE_BARANG"]) ? trim($row["KODE_BARANG"]) : "";
                                $traceable = ($itemCode != "");
                                $traceUrl = "";

                                if ($traceable) {
                                    $traceUrl = $stockAnalysisUrl . "?" . http_build_query(array(
                                        "report_type"  => "scrap",
                                        "item_id"      => $itemCode,
                                        "start_date"   => $tglAwal,
                                        "end_date"     => $tglAkhir,
                                        "period"       => $stockAnalysisPeriod,
                                        "saldo_awal"   => isset($row["SALDO_AWAL"]) ? $row["SALDO_AWAL"] : 0,
                                        "masuk"        => isset($row["MASUK"]) ? $row["MASUK"] : 0,
                                        "keluar"       => isset($row["KELUAR"]) ? $row["KELUAR"] : 0,
                                        "saldo_akhir"  => isset($row["SALDO_AKHIR"]) ? $row["SALDO_AKHIR"] : 0,
                                        "stok_opname"  => isset($row["STOK_OPNAME"]) ? $row["STOK_OPNAME"] : 0,
                                        "selisih"      => isset($row["SELISIH"]) ? $row["SELISIH"] : 0
                                    ));
                                }
                            ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td>
                                        <?php if ($traceable) { ?>
                                            <a href="<?php echo mscrap_h($traceUrl); ?>" class="item-trace-link" target="_blank" title="Buka Stock Analysis">
                                                <i class="fa fa-search"></i> <?php echo mscrap_h($itemCode); ?>
                                            </a>
                                        <?php } else { ?>
                                            <?php echo mscrap_h($itemCode); ?>
                                        <?php } ?>
                                    </td>
                                    <td><?php echo mscrap_h(isset($row["NAMA_BARANG"]) ? $row["NAMA_BARANG"] : ""); ?></td>
                                    <td class="text-center"><?php echo mscrap_h(isset($row["SATUAN"]) ? $row["SATUAN"] : ""); ?></td>
                                    <td class="text-right"><?php echo mscrap_h(mscrap_number(isset($row["SALDO_AWAL"]) ? $row["SALDO_AWAL"] : 0, 2)); ?></td>
                                    <td class="text-right"><?php echo mscrap_h(mscrap_number(isset($row["MASUK"]) ? $row["MASUK"] : 0, 2)); ?></td>
                                    <td class="text-right"><?php echo mscrap_h(mscrap_number(isset($row["KELUAR"]) ? $row["KELUAR"] : 0, 2)); ?></td>
                                    <td class="text-right"><?php echo mscrap_h(mscrap_number(isset($row["PENYESUAIAN"]) ? $row["PENYESUAIAN"] : 0, 2)); ?></td>
                                    <td class="text-right"><?php echo mscrap_h(mscrap_number(isset($row["SALDO_AKHIR"]) ? $row["SALDO_AKHIR"] : 0, 2)); ?></td>
                                    <td class="text-right"><?php echo mscrap_h(mscrap_number(isset($row["STOK_OPNAME"]) ? $row["STOK_OPNAME"] : 0, 2)); ?></td>
                                    <td class="text-right"><?php echo mscrap_h(mscrap_number(isset($row["SELISIH"]) ? $row["SELISIH"] : 0, 2)); ?></td>
                                    <td class="<?php echo mscrap_h($statusClass); ?> text-center">
                                        <?php if ($traceable) { ?>
                                            <a href="<?php echo mscrap_h($traceUrl); ?>" class="trace-link" target="_blank" title="Telusuri transaksi WHS">
                                                <?php echo mscrap_h($status); ?> <i class="fa fa-external-link"></i>
                                            </a>
                                        <?php } else { ?>
                                            <?php echo mscrap_h($status); ?>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>

                            <?php if (count($rows) == 0) { ?>
                                <tr>
                                    <td colspan="12" class="text-center text-muted" style="padding: 20px;">
                                        <em>Data mutasi barang sisa / scrap tidak ditemukan untuk periode yang dipilih.</em>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="row no-print" style="margin-top: 10px;">
                    <div class="col-xs-6 text-muted" style="font-size: 11px;">
                        Jumlah baris: <strong><?php echo count($rows); ?></strong>
                        <?php if ($cari != "") { echo "dari <strong>" . count($allRows) . "</strong>"; } ?>
                    </div>
                    <div class="col-xs-6 text-right text-muted" style="font-size: 11px;">
                        Dicetak: <?php echo date("d-m-Y H:i:s"); ?> | Pengguna: <strong><?php echo mscrap_h($_SESSION["db_user"]); ?></strong>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<?php } ?>

<!-- JS Bootstrap & jQuery -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

<?php if ($autoPrint && count($errors) == 0 && $queryError == "") { ?>
<script>
window.onload = function () {
    window.print();
};
</script>
<?php } ?>

</body>
</html>