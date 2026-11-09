<?php
/**
 * LAPORAN VARIANCE BAHAN BAKU & PENOLONG
 * PHP 5.4 + SQLSRV + SQL Server 2008 + AdminLTE Bootstrap
 *
 * Stored procedure:
 *  dbo.sp_laporan_mutasi_bahanbaku1
 */

/* =========================================================
 * KONEKSI DATABASE
 * ========================================================= */
require_once __DIR__ . '/../config/database_p1.php';

if (!isset($conn) || $conn === false) {
    die("Koneksi database gagal. Silakan cek konfigurasi database_p1.php Anda.");
}

/* =========================================================
 * FUNGSI BANTU
 * ========================================================= */
function mbj_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function mbj_valid_date($value)
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

function mbj_display_date($value)
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

function mbj_number($value, $decimal = 2)
{
    if ($value === null || $value === "") {
        return number_format(0, $decimal, ".", ",");
    }
    return number_format((float) $value, $decimal, ".", ",");
}

function mbj_contains($haystack, $needle)
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

function mbj_month_period($dateFrom, $dateTo)
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

function mbj_status_rank($row)
{
    $status = isset($row["KETERANGAN"]) ? strtoupper(trim($row["KETERANGAN"])) : "";
    return ($status == "TIDAK SESUAI" || $status == "MINUS (KURANG)" || $status == "PLUS (LEBIH)") ? 1 : 0;
}

function mbj_sort_not_sesuai_last($a, $b)
{
    $rankA = mbj_status_rank($a);
    $rankB = mbj_status_rank($b);

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

function mbj_sqlsrv_error()
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
 * IDENTIFIKASI FILE SAAT INI (AGAR LINK DINAMIS)
 * ========================================================= */
$currentFile = basename($_SERVER['PHP_SELF']); 
$idParam     = isset($_GET['id']) ? trim($_GET['id']) : ""; 

/* =========================================================
 * PARAMETER FILTER (Default: Tgl 1 Bulan Lalu s/d Tgl 1 Bulan Berjalan)
 * ========================================================= */
$defaultFrom = date("Y-m-01", strtotime("first day of previous month"));
$defaultTo   = date("Y-m-01", strtotime("first day of this month"));

$tglAwal  = isset($_GET["tglawal"]) ? trim($_GET["tglawal"]) : $defaultFrom;
$tglAkhir = isset($_GET["tglakhir"]) ? trim($_GET["tglakhir"]) : $defaultTo;
$cari     = isset($_GET["cari"]) ? trim($_GET["cari"]) : "";
$locFilter = isset($_GET["loc"]) ? trim($_GET["loc"]) : ""; 

$stockAnalysisUrl = "stok.php"; 

$format = isset($_GET["format"]) ? strtolower(trim($_GET["format"])) : "html";
$autoPrint = isset($_GET["print"]) && $_GET["print"] == "1";

$errors = array();

if (!mbj_valid_date($tglAwal)) {
    $errors[] = "Tanggal awal tidak valid.";
}
if (!mbj_valid_date($tglAkhir)) {
    $errors[] = "Tanggal akhir tidak valid.";
}
if (mbj_valid_date($tglAwal) && mbj_valid_date($tglAkhir) && strtotime($tglAwal) >= strtotime($tglAkhir)) {
    $errors[] = "Tanggal stok akhir harus lebih besar dari tanggal stok awal.";
}
if (strlen($cari) > 150) {
    $errors[] = "Kata pencarian terlalu panjang.";
}

$transactionEndDate = mbj_valid_date($tglAkhir) ? date("Y-m-d", strtotime($tglAkhir . " -1 day")) : $tglAkhir;
$stockAnalysisPeriod = mbj_month_period($tglAwal, $tglAkhir);

/* =========================================================
 * PANGGIL STORED PROCEDURE & ATURAN OVERRIDE
 * ========================================================= */
$rows = array();
$queryError = "";

if (count($errors) == 0) {
    $sql = "{CALL dbo.sp_laporan_mutasi_bahanbaku1(?, ?)}";
    $params = array($tglAwal, $tglAkhir);

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $queryError = mbj_sqlsrv_error();
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // OVERRIDE: Jika Saldo Akhir 0 dan Stok Opname 0, set jadi SESUAI
            $saldoAkhirVal = isset($row["SALDO_AKHIR"]) ? (float)$row["SALDO_AKHIR"] : 0;
            $stokOpnameVal = isset($row["STOK_OPNAME"]) ? (float)$row["STOK_OPNAME"] : 0;
            
            if (abs($saldoAkhirVal) < 0.000001 && abs($stokOpnameVal) < 0.000001) {
                $row["KETERANGAN"] = "SESUAI";
            }
            
            $rows[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}

/* =========================================================
 * AUTOCOMPLETE, EXTRACT LOKASI, PENCARIAN & FILTER
 * ========================================================= */
$allRows = $rows;
$autocompleteNames = array();
$uniqueLocs = array(); 

foreach ($allRows as $r) {
    $itemName = isset($r["NAMA_BARANG"]) ? trim($r["NAMA_BARANG"]) : "";
    $itemCode = isset($r["KODE_BARANG"]) ? trim($r["KODE_BARANG"]) : "";

    if ($itemName != "") {
        $autocompleteKey = strtolower($itemName);
        if (!isset($autocompleteNames[$autocompleteKey])) {
            $autocompleteNames[$autocompleteKey] = array(
                "name" => $itemName,
                "code" => $itemCode
            );
        }
    }

    $locName = isset($r["KODE_LOKASI"]) ? trim($r["KODE_LOKASI"]) : "";
    if ($locName != "" && !in_array($locName, $uniqueLocs)) {
        $uniqueLocs[] = $locName;
    }
}

uasort($autocompleteNames, function ($a, $b) {
    return strcasecmp($a["name"], $b["name"]);
});
sort($uniqueLocs); 

if ($cari != "") {
    $filteredRows = array();
    foreach ($rows as $searchRow) {
        $itemCode = isset($searchRow["KODE_BARANG"]) ? $searchRow["KODE_BARANG"] : "";
        $itemName = isset($searchRow["NAMA_BARANG"]) ? $searchRow["NAMA_BARANG"] : "";

        if (mbj_contains($itemCode, $cari) || mbj_contains($itemName, $cari) || mbj_contains($itemCode . " - " . $itemName, $cari)) {
            $filteredRows[] = $searchRow;
        }
    }
    $rows = $filteredRows;
}

if ($locFilter != "") {
    $filteredRowsLoc = array();
    foreach ($rows as $searchRow) {
        $rowLoc = isset($searchRow["KODE_LOKASI"]) ? trim($searchRow["KODE_LOKASI"]) : "";
        if (strcasecmp($rowLoc, $locFilter) == 0) {
            $filteredRowsLoc[] = $searchRow;
        }
    }
    $rows = $filteredRowsLoc;
}

foreach ($rows as $rowIndex => $sortRow) {
    $rows[$rowIndex]["__ORIGINAL_ORDER"] = $rowIndex;
}
usort($rows, "mbj_sort_not_sesuai_last");
foreach ($rows as $rowIndex => $sortRow) {
    unset($rows[$rowIndex]["__ORIGINAL_ORDER"]);
}

$groupedData = array();
foreach ($rows as $row) {
    $loc = isset($row['KODE_LOKASI']) ? trim($row['KODE_LOKASI']) : 'TANPA LOKASI';
    $itty = isset($row['DESKRIPSI_JENIS']) ? trim($row['DESKRIPSI_JENIS']) : 'TANPA JENIS';
    
    if (!isset($groupedData[$loc])) {
        $groupedData[$loc] = array();
    }
    if (!isset($groupedData[$loc][$itty])) {
        $groupedData[$loc][$itty] = array();
    }
    $groupedData[$loc][$itty][] = $row;
}

/* =========================================================
 * EXPORT EXCEL
 * ========================================================= */
if ($format == "excel" && count($errors) == 0 && $queryError == "") {
    $filename = "mutasi_bahanbaku_" . $tglAwal . "_sd_" . $tglAkhir . ".xls";

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
        .group-header { background-color: #d9edf7; font-weight: bold; text-align: left; }
        .subgroup-header { background-color: #fcf8e3; font-weight: bold; text-align: left; padding-left: 20px !important; }
        .subtotal-row { background-color: #eeeeee; font-weight: bold; }
    </style>
</head>
<body>
<table>
    <tr><td class="title" colspan="14">LAPORAN VARIANCE BAHAN BAKU & PENOLONG</td></tr>
    <tr><td class="title" colspan="14">PT. IMC TEKNO INDONESIA</td></tr>
    <tr>
        <td class="title" colspan="14">
            Periode <?php echo mbj_h(mbj_display_date($tglAwal)); ?> s/d <?php echo mbj_h(mbj_display_date($tglAkhir)); ?>
            <?php if ($locFilter != "") { echo " | Lokasi: " . mbj_h($locFilter); } ?>
        </td>
    </tr>
    <tr><td colspan="14"></td></tr>
    <tr>
        <th>NO</th><th>KODE BARANG</th><th>NAMA BARANG</th><th>SATUAN</th>
        <th>SALDO AWAL<br><?php echo mbj_h(mbj_display_date($tglAwal)); ?></th>
        <th>PEMASUKAN</th><th>PENGELUARAN</th>
        <th>SALDO AKHIR<br><?php echo mbj_h(mbj_display_date($tglAkhir)); ?></th>
        <th>STOK OPNAME</th><th>SELISIH</th>
        <th>HARGA PO</th><th>CURR</th><th>KURS IDR</th><th>AMOUNT SELISIH</th>
        <th>KETERANGAN</th>
    </tr>
    <?php
    $no = 1;
    foreach ($groupedData as $locName => $ittyGroups) {
        echo '<tr><td colspan="14" class="group-header">LOKASI: ' . mbj_h($locName) . '</td></tr>';
        
        foreach ($ittyGroups as $ittyName => $items) {
            echo '<tr><td colspan="14" class="subgroup-header">JENIS BARANG: ' . mbj_h($ittyName) . '</td></tr>';
            
            $subtotalAmount = 0;
            
            foreach ($items as $row) {
                $subtotalAmount += (float)(isset($row["AMOUNT_SELISIH"]) ? $row["AMOUNT_SELISIH"] : 0);
                
                $valSelisihExc = isset($row["SELISIH"]) ? (float)$row["SELISIH"] : 0;
                $styleSelisihExc = ($valSelisihExc < 0) ? 'color: red;' : ''; 
                
                $valAmountSelisihExc = isset($row["AMOUNT_SELISIH"]) ? (float)$row["AMOUNT_SELISIH"] : 0;
                $styleAmountSelisihExc = ($valAmountSelisihExc < 0) ? 'color: red;' : '';
    ?>
        <tr>
            <td class="center"><?php echo $no++; ?></td>
            <td class="text"><?php echo mbj_h(isset($row["KODE_BARANG"]) ? $row["KODE_BARANG"] : ""); ?></td>
            <td class="text"><?php echo mbj_h(isset($row["NAMA_BARANG"]) ? $row["NAMA_BARANG"] : ""); ?></td>
            <td class="center"><?php echo mbj_h(isset($row["SATUAN"]) ? $row["SATUAN"] : ""); ?></td>
            <td class="number"><?php echo (isset($row["SALDO_AWAL"]) ? (float)$row["SALDO_AWAL"] : 0); ?></td>
            <td class="number"><?php echo (isset($row["MASUK"]) ? (float)$row["MASUK"] : 0); ?></td>
            <td class="number"><?php echo (isset($row["KELUAR"]) ? (float)$row["KELUAR"] : 0); ?></td>
            <td class="number"><?php echo (isset($row["SALDO_AKHIR"]) ? (float)$row["SALDO_AKHIR"] : 0); ?></td>
            <td class="number"><?php echo (isset($row["STOK_OPNAME"]) ? (float)$row["STOK_OPNAME"] : 0); ?></td>
            
            <td class="number" style="<?php echo $styleSelisihExc; ?>"><?php echo $valSelisihExc; ?></td>
            <td class="number"><?php echo (isset($row["HARGA_PO_TERBARU"]) ? (float)$row["HARGA_PO_TERBARU"] : 0); ?></td>
            <td class="center"><?php echo mbj_h(isset($row["MATA_UANG"]) ? $row["MATA_UANG"] : ""); ?></td>
            <td class="number"><?php echo (isset($row["KURS_IDR"]) ? (float)$row["KURS_IDR"] : 1); ?></td>
            <td class="number" style="<?php echo $styleAmountSelisihExc; ?>"><?php echo $valAmountSelisihExc; ?></td>
            
            <td class="text"><?php echo mbj_h(isset($row["KETERANGAN"]) ? $row["KETERANGAN"] : ""); ?></td>
        </tr>
    <?php 
            }
            $styleSubtotalExc = ($subtotalAmount < 0) ? 'color: red;' : '';
            echo '<tr class="subtotal-row">';
            echo '<td colspan="13" style="text-align: right;">SUBTOTAL ' . mbj_h($ittyName) . ':</td>';
            echo '<td class="number" style="' . $styleSubtotalExc . '">' . mbj_number($subtotalAmount, 2) . '</td>';
            echo '<td></td>';
            echo '</tr>';
        }
    } 
    ?>
    <?php if (count($rows) == 0) { ?>
        <tr><td colspan="14" class="center">Data tidak ditemukan.</td></tr>
    <?php } ?>
</table>
</body>
</html>
<?php
    exit();
}

/* =========================================================
 * URL BANTU UNTUK LINK (DINAMIS)
 * ========================================================= */
$queryParams = array(
    "tglawal"  => $tglAwal,
    "tglakhir" => $tglAkhir,
    "cari"     => $cari,
    "loc"      => $locFilter
);
if ($idParam != "") {
    $queryParams["id"] = $idParam;
}
$excelUrl = $currentFile . "?" . http_build_query(array_merge($queryParams, array("format" => "excel")));
$resetUrl = $currentFile . "?tglawal=" . urlencode($defaultFrom) . "&tglakhir=" . urlencode($defaultTo) . ($idParam != "" ? "&id=" . urlencode($idParam) : "");
$homeUrl  = $currentFile . ($idParam != "" ? "?id=" . urlencode($idParam) : "");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Mutasi Bahan Baku</title>

    <!-- CSS Bootstrap 3 & FontAwesome -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- CSS AdminLTE 2 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/2.4.18/css/AdminLTE.min.css">

    <style>
        body { background: #ecf0f5; padding: 15px; font-family: 'Source Sans Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        
        .table-mutasi th { background-color: #f4f4f4; text-align: center; vertical-align: middle !important; font-size: 12px; }
        .table-mutasi td { font-size: 12px; vertical-align: middle !important; padding: 4px 8px !important; }
        
        .report-header { text-align: center; margin-bottom: 20px; }
        .report-header h3, .report-header h4, .report-header h5 { margin: 5px 0; font-weight: bold; }
        
        /* Status Colors */
        .status-sesuai { color: #218838; font-weight: bold; }
        .status-tidak { color: #c82333; font-weight: bold; }
        .status-belum { color: #856404; font-weight: bold; }
        
        /* Grouping & Subtotal Colors */
        .row-loc td { background-color: #d9edf7 !important; color: #31708f; font-size: 13px; border-bottom: 2px solid #bce8f1; }
        .row-itty td { background-color: #fcf8e3 !important; color: #8a6d3b; font-size: 12px; padding-left: 25px !important; border-bottom: 1px solid #faebcc; }
        .row-subtotal td { background-color: #f4f4f4 !important; font-weight: bold; border-top: 2px solid #ddd; }

        /* Links */
        .trace-link, .item-trace-link { font-weight: bold; text-decoration: none; display: inline-block; line-height: 1; }
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
            .row-loc td { background-color: #d9edf7 !important; -webkit-print-color-adjust: exact; }
            .row-itty td { background-color: #fcf8e3 !important; -webkit-print-color-adjust: exact; }
            .row-subtotal td { background-color: #e0e0e0 !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="row no-print">
    <div class="col-xs-12">
        <!-- FILTER BOX -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-filter"></i> Filter Mutasi Bahan Baku</h3>
                <div class="box-tools pull-right">
                    <span class="text-muted" style="margin-right: 15px;">
                        Pengguna: <strong><?php echo mbj_h(isset($_SESSION["db_user"]) ? $_SESSION["db_user"] : "Guest"); ?></strong>
                    </span>
                </div>
            </div>
            <div class="box-body">
                <form method="get" action="<?php echo mbj_h($currentFile); ?>" class="form-inline">
                    
                    <?php if ($idParam != "") { ?>
                        <input type="hidden" name="id" value="<?php echo mbj_h($idParam); ?>">
                    <?php } ?>

                    <div class="form-group" style="margin-right: 10px;">
                        <label for="tglawal" style="margin-right: 5px;">Stok Awal</label>
                        <input type="date" name="tglawal" id="tglawal" class="form-control input-sm" value="<?php echo mbj_h($tglAwal); ?>" required>
                    </div>
                    
                    <div class="form-group" style="margin-right: 10px;">
                        <label for="tglakhir" style="margin-right: 5px;">Stok Akhir</label>
                        <input type="date" name="tglakhir" id="tglakhir" class="form-control input-sm" value="<?php echo mbj_h($tglAkhir); ?>" required>
                    </div>
                    
                    <!-- FILTER LOKASI -->
                    <div class="form-group" style="margin-right: 10px;">
                        <label for="loc" style="margin-right: 5px;">Lokasi</label>
                        <select name="loc" id="loc" class="form-control input-sm">
                            <option value="">-- Semua Lokasi --</option>
                            <?php foreach ($uniqueLocs as $l) { ?>
                                <option value="<?php echo mbj_h($l); ?>" <?php if ($locFilter == $l) echo 'selected'; ?>><?php echo mbj_h($l); ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-right: 15px;">
                        <label for="cari" style="margin-right: 5px;">Nama Barang</label>
                        <input type="text" name="cari" id="cari" class="form-control input-sm" value="<?php echo mbj_h($cari); ?>" placeholder="Ketik nama atau kode..." list="nama-barang-list" autocomplete="off" style="width: 200px;">
                        <datalist id="nama-barang-list">
                            <?php foreach ($autocompleteNames as $autocompleteItem) { ?>
                                <option value="<?php echo mbj_h($autocompleteItem["name"]); ?>" label="<?php echo mbj_h($autocompleteItem["code"]); ?>"></option>
                            <?php } ?>
                        </datalist>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Tampilkan</button>
                    <a href="<?php echo mbj_h($resetUrl); ?>" class="btn btn-warning btn-sm"><i class="fa fa-refresh"></i> Reset</a>
                    <button type="button" class="btn btn-default btn-sm" onclick="window.print();"><i class="fa fa-print"></i> Cetak PDF</button>
                    <a href="<?php echo mbj_h($excelUrl); ?>" class="btn btn-success btn-sm"><i class="fa fa-file-excel-o"></i> Excel</a>
                    <a href="<?php echo mbj_h($homeUrl); ?>" class="btn btn-default btn-sm pull-right"><i class="fa fa-arrow-left"></i> Kembali</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (count($errors) > 0) { ?>
    <div class="alert alert-danger alert-dismissible no-print">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4><i class="icon fa fa-ban"></i> Filter tidak valid!</h4>
        <?php echo nl2br(mbj_h(implode("\n", $errors))); ?>
    </div>
<?php } ?>

<?php if ($queryError != "") { ?>
    <div class="alert alert-danger alert-dismissible no-print">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4><i class="icon fa fa-warning"></i> Stored procedure gagal dijalankan!</h4>
        <?php echo nl2br(mbj_h($queryError)); ?>
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
                    <h3>LAPORAN VARIANCE BAHAN BAKU & PENOLONG</h3>
                    <h4>PT. IMC TEKNO INDONESIA</h4>
                    <h5>
                        Stok Awal: <?php echo mbj_h(mbj_display_date($tglAwal)); ?> | 
                        Transaksi: <?php echo mbj_h(mbj_display_date($tglAwal)); ?> s/d <?php echo mbj_h(mbj_display_date($transactionEndDate)); ?> | 
                        Stok Akhir: <?php echo mbj_h(mbj_display_date($tglAkhir)); ?>
                        <?php if ($locFilter != "") { echo "<br>Filter Lokasi: " . mbj_h($locFilter); } ?>
                        <?php if ($cari != "") { echo "<br>Pencarian: " . mbj_h($cari); } ?>
                    </h5>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-mutasi">
                        <thead>
                            <tr>
                                <th>NO</th>
                                <th>KODE BARANG</th>
                                <th>NAMA BARANG</th>
                                <th>SATUAN</th>
                                <th>SALDO AWAL<br><small><?php echo mbj_h(mbj_display_date($tglAwal)); ?></small></th>
                                <th>PEMASUKAN</th>
                                <th>PENGELUARAN</th>
                                <th>SALDO AKHIR<br><small><?php echo mbj_h(mbj_display_date($tglAkhir)); ?></small></th>
                                <th>STOK OPNAME</th>
                                <th>SELISIH</th>
                                <th>HARGA PO</th>
                                <th>CURR</th>
                                <th>KURS</th>
                                <th>AMT SELISIH</th>
                                <th>KETERANGAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            
                            foreach ($groupedData as $locName => $ittyGroups) {
                                ?>
                                <tr class="row-loc">
                                    <td colspan="15"><strong><i class="fa fa-map-marker"></i> LOKASI: <?php echo mbj_h($locName); ?></strong></td>
                                </tr>
                                <?php
                                foreach ($ittyGroups as $ittyName => $items) {
                                    ?>
                                    <tr class="row-itty">
                                        <td colspan="15"><strong><i class="fa fa-tags"></i> JENIS BARANG: <?php echo mbj_h($ittyName); ?></strong></td>
                                    </tr>
                                    <?php
                                    $subtotalAmount = 0;
                                    
                                    foreach ($items as $row) {
                                        $subtotalAmount += (float)(isset($row["AMOUNT_SELISIH"]) ? $row["AMOUNT_SELISIH"] : 0);

                                        $status = isset($row["KETERANGAN"]) ? strtoupper(trim($row["KETERANGAN"])) : "";
                                        $statusClass = "";

                                        if ($status == "SESUAI") {
                                            $statusClass = "status-sesuai";
                                        } elseif ($status == "TIDAK SESUAI" || $status == "MINUS (KURANG)" || $status == "PLUS (LEBIH)") {
                                            $statusClass = "status-tidak";
                                        } elseif ($status == "BELUM OPNAME") {
                                            $statusClass = "status-belum";
                                        }

                                        $itemCode = isset($row["KODE_BARANG"]) ? trim($row["KODE_BARANG"]) : "";
                                        $traceable = ($itemCode != "");
                                        $traceUrl = "";

                                        if ($traceable) {
                                            $traceUrl = $stockAnalysisUrl . "?" . http_build_query(array(
                                                "report_type"  => "bahanbaku",
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
                                        
                                        $valSelisih = isset($row["SELISIH"]) ? (float)$row["SELISIH"] : 0;
                                        $styleSelisih = ($valSelisih < 0) ? 'color: red;' : ''; 
                                        
                                        $valAmountSelisih = isset($row["AMOUNT_SELISIH"]) ? (float)$row["AMOUNT_SELISIH"] : 0;
                                        $styleAmountSelisih = ($valAmountSelisih < 0) ? 'color: red;' : '';
                                    ?>
                                        <tr>
                                            <td class="text-center"><?php echo $no++; ?></td>
                                            <td>
                                                <?php if ($traceable) { ?>
                                                    <a href="<?php echo mbj_h($traceUrl); ?>" class="item-trace-link" target="_blank" title="Buka Variance Laporan">
                                                        <i class="fa fa-search"></i> <?php echo mbj_h($itemCode); ?>
                                                    </a>
                                                <?php } else { ?>
                                                    <?php echo mbj_h($itemCode); ?>
                                                <?php } ?>
                                            </td>
                                            <td><?php echo mbj_h(isset($row["NAMA_BARANG"]) ? $row["NAMA_BARANG"] : ""); ?></td>
                                            <td class="text-center"><?php echo mbj_h(isset($row["SATUAN"]) ? $row["SATUAN"] : ""); ?></td>
                                            <td class="text-right"><?php echo mbj_h(mbj_number(isset($row["SALDO_AWAL"]) ? $row["SALDO_AWAL"] : 0, 2)); ?></td>
                                            <td class="text-right"><?php echo mbj_h(mbj_number(isset($row["MASUK"]) ? $row["MASUK"] : 0, 2)); ?></td>
                                            <td class="text-right"><?php echo mbj_h(mbj_number(isset($row["KELUAR"]) ? $row["KELUAR"] : 0, 2)); ?></td>
                                            <td class="text-right"><?php echo mbj_h(mbj_number(isset($row["SALDO_AKHIR"]) ? $row["SALDO_AKHIR"] : 0, 2)); ?></td>
                                            <td class="text-right"><?php echo mbj_h(mbj_number(isset($row["STOK_OPNAME"]) ? $row["STOK_OPNAME"] : 0, 2)); ?></td>
                                            
                                            <td class="text-right" style="<?php echo $styleSelisih; ?>">
                                                <?php echo mbj_h(mbj_number($valSelisih, 2)); ?>
                                            </td>
                                            
                                            <td class="text-right"><?php echo mbj_h(mbj_number(isset($row["HARGA_PO_TERBARU"]) ? $row["HARGA_PO_TERBARU"] : 0, 2)); ?></td>
                                            <td class="text-center"><?php echo mbj_h(isset($row["MATA_UANG"]) ? $row["MATA_UANG"] : "-"); ?></td>
                                            <td class="text-right"><?php echo mbj_h(mbj_number(isset($row["KURS_IDR"]) ? $row["KURS_IDR"] : 1, 2)); ?></td>
                                            
                                            <td class="text-right" style="font-weight: bold; <?php echo $styleAmountSelisih; ?>">
                                                <?php echo mbj_h(mbj_number($valAmountSelisih, 2)); ?>
                                            </td>
                                            
                                            <td class="<?php echo mbj_h($statusClass); ?> text-center">
                                                <?php echo mbj_h($status); ?>
                                            </td>
                                        </tr>
                                    <?php 
                                    } 
                                    
                                    $styleSubtotal = ($subtotalAmount < 0) ? 'color: red;' : '';
                                    ?>
                                    
                                    <tr class="row-subtotal">
                                        <td colspan="13" class="text-right">SUBTOTAL <?php echo mbj_h($ittyName); ?>:</td>
                                        <td class="text-right" style="font-weight: bold; <?php echo $styleSubtotal; ?>"><?php echo mbj_h(mbj_number($subtotalAmount, 2)); ?></td>
                                        <td></td>
                                    </tr>

                            <?php 
                                } 
                            } 
                            ?>

                            <?php if (count($rows) == 0) { ?>
                                <tr>
                                    <td colspan="15" class="text-center text-muted" style="padding: 20px;">
                                        <em>Data mutasi tidak ditemukan untuk periode/filter yang dipilih.</em>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="row no-print" style="margin-top: 10px;">
                    <div class="col-xs-6 text-muted" style="font-size: 11px;">
                        Jumlah baris: <strong><?php echo count($rows); ?></strong>
                        <?php if ($cari != "" || $locFilter != "") { echo "dari <strong>" . count($allRows) . "</strong>"; } ?>
                    </div>
                    <div class="col-xs-6 text-right text-muted" style="font-size: 11px;">
                        Dicetak: <?php echo date("d-m-Y H:i:s"); ?> | Pengguna: <strong><?php echo mbj_h(isset($_SESSION["db_user"]) ? $_SESSION["db_user"] : "Guest"); ?></strong>
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