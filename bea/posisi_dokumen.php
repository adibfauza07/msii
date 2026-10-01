<?php
/**
 * LAPORAN POSISI BARANG PER DOKUMEN PABEAN (DENGAN TRACING)
 * PHP 5.4 + SQLSRV + SQL Server 2008 + AdminLTE Bootstrap
 */

/* =========================================================
 * SESSION DAN LOGIN PROTECTION
 * ========================================================= */
if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

set_time_limit(120);

$loginUrl = "/msii/bea/login.php";

if (
    !isset($_SESSION['db_user']) ||
    trim($_SESSION['db_user']) == "" ||
    !isset($_SESSION['active_module']) ||
    $_SESSION['active_module'] != "it_inventory"
) {
    if (!headers_sent()) {
        header("Location: " . $loginUrl);
    } else {
        echo "<script>window.location.href='" . $loginUrl . "';</script>";
    }
    exit();
}

require_once __DIR__ . '/config/database.php';

if (!isset($conn) || $conn === false) {
    if (!headers_sent()) {
        header("Location: " . $loginUrl . "?error=session_expired");
    } else {
        echo "<script>window.location.href='" . $loginUrl . "?error=session_expired';</script>";
    }
    exit();
}

/* =========================================================
 * FUNGSI BANTU PHP 5.4
 * ========================================================= */
function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function valid_date_ymd($value)
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

function display_date($value)
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

function number_id($value, $decimal)
{
    if ($value === null || $value === "") {
        return "";
    }
    return number_format((float) $value, $decimal, ".", ",");
}

function sqlsrv_error_text()
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
 * AJAX HANDLER (AUTOCOMPLETE, FIFO, & TRACING)
 * ========================================================= */
$action = isset($_GET["action"]) ? trim($_GET["action"]) : (isset($_POST["action"]) ? trim($_POST["action"]) : "");

// 1. AJAX: Autocomplete Barang
if ($action == "search_item") {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header("Content-Type: application/json; charset=utf-8");
    
    $q = isset($_GET["q"]) ? trim($_GET["q"]) : "";
    $like = "%" . $q . "%";
    
    $sql = "SELECT TOP 50 ISNULL(ITEM_CODE, '') AS ITEM_CODE, ISNULL(ITEM_NAME, '') AS ITEM_NAME 
            FROM dbo.ITEMS 
            WHERE (ISNULL(ITEM_CODE, '') LIKE ? OR ISNULL(ITEM_NAME, '') LIKE ?)
              AND (ITEM_INACTIVE IS NULL OR ITEM_INACTIVE = 0)
            ORDER BY ITEM_CODE";
            
    $stmt = sqlsrv_query($conn, $sql, array($like, $like));
    $res = array();
    if ($stmt) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $res[] = array("ITEM_CODE" => trim($r["ITEM_CODE"]), "ITEM_NAME" => trim($r["ITEM_NAME"]));
        }
    }
    echo json_encode(array("success" => true, "rows" => $res));
    exit;
}

// 2. AJAX: Eksekusi Mesin Hitung FIFO
if ($action == "run_fifo") {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header("Content-Type: application/json; charset=utf-8");
    
    $bulan = isset($_POST['bulan']) ? (int)$_POST['bulan'] : (int)date('m');
    $tahun = isset($_POST['tahun']) ? (int)$_POST['tahun'] : (int)date('Y');
    
    try {
        $sqlSP = "EXEC dbo.sp_potong_fifo_produksi_bulanan @Bulan = ?, @Tahun = ?";
        $stmtSP = sqlsrv_query($conn, $sqlSP, array($bulan, $tahun));
        if ($stmtSP === false) { throw new Exception(sqlsrv_error_text()); }
        sqlsrv_free_stmt($stmtSP);
        
        echo json_encode(array("success" => true, "message" => "Mesin Hitung FIFO berhasil dijalankan!"));
    } catch (Exception $e) {
        echo json_encode(array("success" => false, "message" => $e->getMessage()));
    }
    exit;
}

// 3. AJAX: Forward Tracing (Menampilkan History Pemotongan)
if ($action == "get_tracing") {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header("Content-Type: application/json; charset=utf-8");
    
    $nomor_bc = isset($_GET['nomor_bc']) ? trim($_GET['nomor_bc']) : '';
    $kode_barang = isset($_GET['kode_barang']) ? trim($_GET['kode_barang']) : '';
    
    // Ambil data dari tabel TBL_HISTORY_POTONG_BC yang baru diperbaiki
    $sqlTrace = "SELECT QTY_DIPOTONG, TGL_PRODUKSI, WO_NUMBER, KODE_BARANG_FG, JENIS_BC_OUT, NOMOR_BC_OUT 
                 FROM dbo.TBL_HISTORY_POTONG_BC 
                 WHERE NOMOR_BC_IN = ? AND KODE_BARANG_MAT = ?
                 ORDER BY TGL_PRODUKSI ASC";
                 
    $stmtTrace = sqlsrv_query($conn, $sqlTrace, array($nomor_bc, $kode_barang));
    $resTrace = array();
    
    if ($stmtTrace) {
        while ($r = sqlsrv_fetch_array($stmtTrace, SQLSRV_FETCH_ASSOC)) {
            $r['TGL_PRODUKSI'] = $r['TGL_PRODUKSI'] ? $r['TGL_PRODUKSI']->format('d-m-Y') : '-';
            $resTrace[] = $r;
        }
        sqlsrv_free_stmt($stmtTrace);
    }
    
    echo json_encode(array("success" => true, "data" => $resTrace));
    exit;
}

/* =========================================================
 * PARAMETER FILTER LAPORAN
 * ========================================================= */
$today       = date("Y-m-d");
$defaultFrom = date("Y-m-01");

$tglAwal = isset($_GET["tglawal"]) ? trim($_GET["tglawal"]) : $defaultFrom;
$tglAkhir = isset($_GET["tglakhir"]) ? trim($_GET["tglakhir"]) : $today;
$dokumen = isset($_GET["dokumen"]) ? trim($_GET["dokumen"]) : "";
$filterKodeBarang = isset($_GET["kode_barang"]) ? trim($_GET["kode_barang"]) : "";
$filterNamaBarang = isset($_GET["nama_barang"]) ? trim($_GET["nama_barang"]) : "";

$format = isset($_GET["format"]) ? strtolower(trim($_GET["format"])) : "html";

$errors = array();

if (!valid_date_ymd($tglAwal)) { $errors[] = "Tanggal awal tidak valid."; }
if (!valid_date_ymd($tglAkhir)) { $errors[] = "Tanggal akhir tidak valid."; }
if (valid_date_ymd($tglAwal) && valid_date_ymd($tglAkhir) && strtotime($tglAwal) > strtotime($tglAkhir)) {
    $errors[] = "Tanggal awal tidak boleh lebih besar dari tanggal akhir.";
}

/* =========================================================
 * DAFTAR JENIS DOKUMEN (Ditarik Dinamis)
 * ========================================================= */
$documentOptions = array("" => "-- Semua Dokumen --");
$sqlDoc = "SELECT DISTINCT JENIS_BC FROM dbo.JENIS_BC WHERE JENIS_BC IS NULL OR JENIS_BC <> '' ORDER BY JENIS_BC ASC";
$stmtDoc = sqlsrv_query($conn, $sqlDoc);
if ($stmtDoc !== false) {
    while ($rDoc = sqlsrv_fetch_array($stmtDoc, SQLSRV_FETCH_ASSOC)) {
        $jb = trim($rDoc['JENIS_BC']);
        if ($jb != "") { $documentOptions[$jb] = $jb; }
    }
    sqlsrv_free_stmt($stmtDoc);
}

/* =========================================================
 * PANGGIL QUERY LAPORAN POSISI
 * ========================================================= */
$rows = array();
$queryError = "";

if (count($errors) == 0) {
    $sql = "SELECT S.JENIS_BC, S.NOMOR_BC, S.TANGGAL_DOK, S.KODE_BARANG, I.ITEM_NAME AS NAMA_BARANG, 
                   I.ITEM_UNIT AS SATUAN, S.QTY_MASUK, S.QTY_KELUAR, S.SALDO_AKHIR
            FROM dbo.TBL_SALDO_DOKUMEN_BC S
            LEFT JOIN dbo.ITEMS I ON S.KODE_BARANG = I.ITEM_CODE
            WHERE S.SALDO_AKHIR >= 0 AND S.TANGGAL_DOK >= ? AND S.TANGGAL_DOK <= ? ";
              
    $params = array($tglAwal, $tglAkhir . ' 23:59:59');

    if ($dokumen != "") {
        $sql .= " AND S.JENIS_BC = ? ";
        $params[] = $dokumen;
    }
    if ($filterKodeBarang != "") {
        $sql .= " AND S.KODE_BARANG = ? ";
        $params[] = $filterKodeBarang;
    }
    $sql .= " ORDER BY S.KODE_BARANG ASC, S.TANGGAL_DOK ASC ";

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $queryError = sqlsrv_error_text();
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $rows[] = $row; }
        sqlsrv_free_stmt($stmt);
    }
}

/* =========================================================
 * EXPORT EXCEL
 * ========================================================= */
if ($format == "excel" && count($errors) == 0 && $queryError == "") {
    $filename = "laporan_posisi_dokumen_" . $tglAwal . "_sd_" . $tglAkhir . ".xls";
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
        .title { border: 0; font-weight: bold; font-size: 12px; }
        .center { text-align: center; }
        .xl-text { mso-number-format: "\@"; } 
        .xl-num { mso-number-format: "\#\,\#\#0\.0000"; text-align: right; }
    </style>
</head>
<body>
<table>
    <tr><td class="title" colspan="10">LAPORAN POSISI BARANG PER DOKUMEN PABEAN (IT INVENTORY)</td></tr>
    <tr><td class="title" colspan="10">KAWASAN BERIKAT PT. IMC TEKNO INDONESIA</td></tr>
    <tr>
        <td class="title" colspan="10">
            Periode Pemasukan: <?php echo h(display_date($tglAwal)); ?> s/d <?php echo h(display_date($tglAkhir)); ?>
            <?php if ($dokumen != "") { ?> - Dokumen: <?php echo h($dokumen); ?><?php } ?>
        </td>
    </tr>
    <tr><td colspan="10"></td></tr>
    <tr>
        <th>NO</th><th>JENIS BC</th><th>NOMOR BC</th><th>TGL DOKUMEN</th>
        <th>KODE BARANG</th><th>URAIAN BARANG</th><th>SATUAN</th>
        <th>QTY MASUK</th><th>QTY KELUAR</th><th>SISA (SALDO AKHIR)</th>
    </tr>
    <?php
    $no = 1;
    foreach ($rows as $row) {
    ?>
        <tr>
            <td class="center"><?php echo $no++; ?></td>
            <td class="xl-text center"><?php echo h(isset($row["JENIS_BC"]) ? $row["JENIS_BC"] : ""); ?></td>
            <td class="xl-text"><?php echo h(isset($row["NOMOR_BC"]) ? $row["NOMOR_BC"] : ""); ?></td>
            <td class="xl-text center"><?php echo h(display_date(isset($row["TANGGAL_DOK"]) ? $row["TANGGAL_DOK"] : "")); ?></td>
            <td class="xl-text"><?php echo h(isset($row["KODE_BARANG"]) ? $row["KODE_BARANG"] : ""); ?></td>
            <td class="xl-text"><?php echo h(isset($row["NAMA_BARANG"]) ? $row["NAMA_BARANG"] : ""); ?></td>
            <td class="center xl-text"><?php echo h(isset($row["SATUAN"]) ? $row["SATUAN"] : ""); ?></td>
            <td class="xl-num"><?php echo (isset($row["QTY_MASUK"]) ? (float)$row["QTY_MASUK"] : 0); ?></td>
            <td class="xl-num"><?php echo (isset($row["QTY_KELUAR"]) ? (float)$row["QTY_KELUAR"] : 0); ?></td>
            <td class="xl-num"><?php echo (isset($row["SALDO_AKHIR"]) ? (float)$row["SALDO_AKHIR"] : 0); ?></td>
        </tr>
    <?php } ?>
    <?php if (count($rows) == 0) { ?>
        <tr><td colspan="10" class="center">Data tidak ditemukan.</td></tr>
    <?php } ?>
</table>
</body>
</html>
<?php
    exit();
}

$queryParams = array("tglawal" => $tglAwal, "tglakhir" => $tglAkhir, "dokumen" => $dokumen, "kode_barang" => $filterKodeBarang, "nama_barang" => $filterNamaBarang);
$excelUrl = "posisi_dokumen.php?" . http_build_query(array_merge($queryParams, array("format" => "excel")));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Posisi Dokumen & Tracing</title>
    <!-- CSS Bootstrap 3 & AdminLTE 2 -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/2.4.18/css/AdminLTE.min.css">

    <style>
        body { background: #ecf0f5; padding: 15px; font-family: 'Source Sans Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .autocomplete-wrap { position: relative; }
        .suggest-box { position: absolute; top: 100%; left: 0; width: 100%; max-height: 250px; overflow-y: auto; background: #ffffff; border: 1px solid #ddd; z-index: 99999; display: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .suggest-item { padding: 8px 10px; border-bottom: 1px solid #eee; cursor: pointer; color: #333; line-height: 1.2; }
        .suggest-item:hover, .suggest-item.active { background: #3c8dbc; color: #ffffff; }
        .table-laporan th { background-color: #f4f4f4; text-align: center; vertical-align: middle !important; font-size: 12px; }
        .table-laporan td { font-size: 12px; vertical-align: middle !important; }
        .report-header { text-align: center; margin-bottom: 20px; }
        .report-header h3, .report-header h4, .report-header h5 { margin: 5px 0; font-weight: bold; }
        .btn-trace { margin-left: 5px; padding: 2px 5px; font-size: 10px; }
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { background: #fff !important; padding: 0 !important; }
            .no-print { display: none !important; }
            .box { border: none !important; box-shadow: none !important; }
            .table-laporan th, .table-laporan td { border: 1px solid #000 !important; font-size: 10px !important; padding: 4px !important; }
            .table-laporan th { background-color: #e0e0e0 !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="row no-print">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-filter"></i> Filter Posisi Barang & Forward Tracing</h3>
                <div class="box-tools pull-right">
                    <span class="text-muted" style="margin-right: 15px;">Login: <strong><?php echo h($_SESSION["db_user"]); ?></strong></span>
                </div>
            </div>
            <div class="box-body">
                
                <!-- TOMBOL MESIN HITUNG FIFO -->
                <div class="well well-sm" style="background-color: #fcf8e3; border-color: #faebcc;">
                    <strong><i class="fa fa-info-circle"></i> Sinkronisasi Data (FIFO)</strong><br>
                    <span style="font-size: 12px;">Pilih periode produksi untuk menjalankan mesin potong bahan baku secara FIFO ke dokumen pabean asal.</span>
                    <div class="form-inline" style="margin-top: 10px;">
                        <select id="fifo_bulan" class="form-control input-sm">
                            <?php 
                            for($m=1; $m<=12; $m++){
                                $sel = ($m == 9) ? 'selected' : ''; 
                                echo "<option value='$m' $sel>".date('F', mktime(0,0,0,$m,1))."</option>";
                            }
                            ?>
                        </select>
                        <select id="fifo_tahun" class="form-control input-sm">
                            <?php 
                            $currYear = 2026; 
                            for($y=$currYear-1; $y<=$currYear+1; $y++){
                                $sel = ($y == $currYear) ? 'selected' : '';
                                echo "<option value='$y' $sel>$y</option>";
                            }
                            ?>
                        </select>
                        <button type="button" class="btn btn-warning btn-sm" id="btnJalankanFIFO">
                            <i class="fa fa-cogs"></i> Jalankan Mesin Hitung FIFO
                        </button>
                        <span id="fifo_status" style="margin-left: 10px; font-weight: bold; color: #d58512; display: none;">
                            <i class="fa fa-spinner fa-spin"></i> Memproses kalkulasi...
                        </span>
                    </div>
                </div>

                <hr>

                <!-- FORM FILTER DATA -->
                <form method="get" action="posisi_dokumen.php" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <label for="tglawal" style="margin-right: 5px;">Tgl Masuk</label>
                        <input type="date" name="tglawal" id="tglawal" class="form-control input-sm" value="<?php echo h($tglAwal); ?>" required>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <label for="tglakhir" style="margin-right: 5px;">s/d</label>
                        <input type="date" name="tglakhir" id="tglakhir" class="form-control input-sm" value="<?php echo h($tglAkhir); ?>" required>
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <label for="dokumen" style="margin-right: 5px;">Dokumen</label>
                        <select name="dokumen" id="dokumen" class="form-control input-sm">
                            <?php foreach ($documentOptions as $optionValue => $optionLabel) { ?>
                                <option value="<?php echo h($optionValue); ?>" <?php echo ($dokumen == $optionValue) ? 'selected="selected"' : ""; ?>><?php echo h($optionLabel); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group autocomplete-wrap" style="margin-right: 15px;">
                        <label for="search_item" style="margin-right: 5px;">Barang</label>
                        <input type="text" id="search_item" name="nama_barang" class="form-control input-sm" value="<?php echo h($filterNamaBarang); ?>" placeholder="Ketik Kode/Nama..." autocomplete="off" onkeyup="itemKeyup(event)" onkeydown="itemKeydown(event)" style="width: 200px;">
                        <input type="hidden" id="kode_barang" name="kode_barang" value="<?php echo h($filterKodeBarang); ?>">
                        <div id="itemSuggest" class="suggest-box"></div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Tampilkan</button>
                    <button type="button" class="btn btn-default btn-sm" onclick="window.print();"><i class="fa fa-print"></i> Cetak PDF</button>
                    <a href="<?php echo h($excelUrl); ?>" class="btn btn-success btn-sm"><i class="fa fa-file-excel-o"></i> Excel</a>
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
        <?php echo nl2br(h(implode("\n", $errors))); ?>
    </div>
<?php } ?>
<?php if ($queryError != "") { ?>
    <div class="alert alert-danger alert-dismissible no-print">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4><i class="icon fa fa-warning"></i> Gagal Mengambil Data!</h4>
        <?php echo nl2br(h($queryError)); ?>
    </div>
<?php } ?>

<?php if (count($errors) == 0 && $queryError == "") { ?>
<div class="row">
    <div class="col-xs-12">
        <div class="box box-success">
            <div class="box-body">
                <div class="report-header">
                    <h3>LAPORAN POSISI BARANG PER DOKUMEN PABEAN</h3>
                    <h4>KAWASAN BERIKAT PT. IMC TEKNO INDONESIA</h4>
                    <h5>
                        Periode Pemasukan: <?php echo h(display_date($tglAwal)); ?> s/d <?php echo h(display_date($tglAkhir)); ?>
                        <?php if ($dokumen != "") { echo " | Dokumen: " . h($dokumen); } ?>
                        <?php if ($filterKodeBarang != "") { echo " | Barang: " . h($filterKodeBarang); } ?>
                    </h5>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover table-laporan">
                        <thead>
                            <tr>
                                <th>NO</th>
                                <th>JENIS BC</th>
                                <th>NOMOR BC</th>
                                <th>TGL DOKUMEN</th>
                                <th>KODE BARANG</th>
                                <th>URAIAN BARANG</th>
                                <th>SATUAN</th>
                                <th>QTY MASUK</th>
                                <th>QTY KELUAR</th>
                                <th style="background-color: #dff0d8;">SISA (SALDO)</th>
                                <th class="no-print" style="width: 70px;">TRACING</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($rows as $row) { ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td class="text-center"><?php echo h(isset($row["JENIS_BC"]) ? $row["JENIS_BC"] : ""); ?></td>
                                    <td><?php echo h(isset($row["NOMOR_BC"]) ? $row["NOMOR_BC"] : ""); ?></td>
                                    <td class="text-center"><?php echo h(display_date(isset($row["TANGGAL_DOK"]) ? $row["TANGGAL_DOK"] : "")); ?></td>
                                    <td><?php echo h(isset($row["KODE_BARANG"]) ? $row["KODE_BARANG"] : ""); ?></td>
                                    <td><?php echo h(isset($row["NAMA_BARANG"]) ? $row["NAMA_BARANG"] : ""); ?></td>
                                    <td class="text-center"><?php echo h(isset($row["SATUAN"]) ? $row["SATUAN"] : ""); ?></td>
                                    <td class="text-right"><?php echo h(number_id(isset($row["QTY_MASUK"]) ? $row["QTY_MASUK"] : 0, 4)); ?></td>
                                    <td class="text-right"><?php echo h(number_id(isset($row["QTY_KELUAR"]) ? $row["QTY_KELUAR"] : 0, 4)); ?></td>
                                    <td class="text-right" style="font-weight: bold; color: #3c763d; background-color: #f9fdf9;">
                                        <?php echo h(number_id(isset($row["SALDO_AKHIR"]) ? $row["SALDO_AKHIR"] : 0, 4)); ?>
                                    </td>
                                    <td class="text-center no-print">
                                        <button type="button" class="btn btn-info btn-trace" onclick="showTracing('<?php echo h(isset($row["NOMOR_BC"]) ? $row["NOMOR_BC"] : ""); ?>', '<?php echo h(isset($row["KODE_BARANG"]) ? $row["KODE_BARANG"] : ""); ?>')" title="Lacak riwayat pengeluaran">
                                            <i class="fa fa-search-plus"></i> Trace
                                        </button>
                                    </td>
                                </tr>
                            <?php } ?>
                            <?php if (count($rows) == 0) { ?>
                                <tr><td colspan="11" class="text-center text-muted" style="padding: 20px;"><em>Data posisi barang tidak ditemukan.</em></td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="row no-print" style="margin-top: 10px;">
                    <div class="col-xs-6 text-muted" style="font-size: 11px;">Jumlah baris: <strong><?php echo count($rows); ?></strong></div>
                    <div class="col-xs-6 text-right text-muted" style="font-size: 11px;">Dicetak: <?php echo date("d-m-Y H:i:s"); ?> | Pengguna: <strong><?php echo h($_SESSION["db_user"]); ?></strong></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<!-- MODAL TRACING EX BC -->
<div class="modal fade no-print" id="modalTracing" tabindex="-1" role="dialog" aria-labelledby="modalTracingLabel">
  <div class="modal-dialog modal-lg" role="document" style="width: 80%;">
    <div class="modal-content">
      <div class="modal-header" style="background-color: #3c8dbc; color: white;">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white;"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="modalTracingLabel"><i class="fa fa-truck"></i> Forward Tracing - Riwayat Penggunaan Bahan Baku</h4>
      </div>
      <div class="modal-body">
        <div class="well well-sm" style="font-size: 12px; margin-bottom: 10px;">
            <strong>Dokumen Masuk (Impor/Lokal):</strong> <span id="lbl_nomor_bc" class="text-primary"></span> &nbsp; | &nbsp;
            <strong>Bahan Baku:</strong> <span id="lbl_kode_barang" class="text-primary"></span>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="tableTracing" style="font-size: 11px;">
                <thead style="background-color: #f4f4f4;">
                    <tr>
                        <th class="text-center">Tgl Produksi</th>
                        <th>No. Work Order</th>
                        <th>Barang Jadi (FG)</th>
                        <th class="text-right">Qty Dipotong (Dipakai)</th>
                        <th class="text-center">Jenis BC Keluar</th>
                        <th>No. Dok Ekspor/Lokal</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data diload via AJAX -->
                </tbody>
            </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- JS Bootstrap & jQuery -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

<script>
    // --- LOGIKA MESIN HITUNG FIFO ---
    $(document).ready(function() {
        $('#btnJalankanFIFO').click(function() {
            var b = $('#fifo_bulan').val();
            var t = $('#fifo_tahun').val();

            if (confirm("Jalankan Mesin Pemotong FIFO stok material untuk Bulan " + b + " Tahun " + t + "?\n\nProses ini akan memotong stok material dari dokumen pabean berdasarkan data produksi aktual.")) {
                
                $('#btnJalankanFIFO').prop('disabled', true);
                $('#fifo_status').show();

                $.ajax({
                    url: 'posisi_dokumen.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { action: 'run_fifo', bulan: b, tahun: t },
                    success: function(res) {
                        $('#btnJalankanFIFO').prop('disabled', false);
                        $('#fifo_status').hide();
                        
                        if(res.success) {
                            alert(res.message + "\n\nSilakan klik tombol 'Tampilkan' di bawah untuk melihat pembaruan data.");
                        } else {
                            alert("ERROR: " + res.message);
                        }
                    },
                    error: function() {
                        $('#btnJalankanFIFO').prop('disabled', false);
                        $('#fifo_status').hide();
                        alert("Gagal menghubungi server untuk proses FIFO.");
                    }
                });
            }
        });
    });

    // --- LOGIKA FORWARD TRACING ---
    function showTracing(nomor_bc, kode_barang) {
        if(nomor_bc === '') {
            alert('Nomor BC tidak tersedia untuk di-trace.');
            return;
        }

        $('#lbl_nomor_bc').text(nomor_bc);
        $('#lbl_kode_barang').text(kode_barang);
        $('#tableTracing tbody').html('<tr><td colspan="6" class="text-center" style="padding: 20px;"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i><br>Memuat data riwayat pemakaian...</td></tr>');
        $('#modalTracing').modal('show');
        
        $.ajax({
            url: 'posisi_dokumen.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_tracing', nomor_bc: nomor_bc, kode_barang: kode_barang },
            success: function(res) {
                var tbody = '';
                if (res.success && res.data.length > 0) {
                    var totalQty = 0;
                    $.each(res.data, function(i, val) {
                        var qty = parseFloat(val.QTY_DIPOTONG);
                        totalQty += qty;
                        tbody += '<tr>' +
                            '<td class="text-center">' + val.TGL_PRODUKSI + '</td>' +
                            '<td>' + (val.WO_NUMBER || '-') + '</td>' +
                            '<td>' + (val.KODE_BARANG_FG || '-') + '</td>' +
                            '<td class="text-right" style="color: #d9534f; font-weight:bold;">' + qty.toFixed(4) + '</td>' +
                            '<td class="text-center">' + (val.JENIS_BC_OUT || '-') + '</td>' +
                            '<td>' + (val.NOMOR_BC_OUT || '-') + '</td>' +
                            '</tr>';
                    });
                    // Baris Total
                    tbody += '<tr style="background-color: #fcf8e3; font-weight: bold;">' +
                             '<td colspan="3" class="text-right">TOTAL MATERIAL DIPAKAI:</td>' +
                             '<td class="text-right text-danger">' + totalQty.toFixed(4) + '</td>' +
                             '<td colspan="2"></td></tr>';
                } else {
                    tbody = '<tr><td colspan="6" class="text-center text-muted" style="padding:15px;">Belum ada riwayat pemotongan (produksi/ekspor) untuk dokumen masuk ini.</td></tr>';
                }
                $('#tableTracing tbody').html(tbody);
            },
            error: function() {
                $('#tableTracing tbody').html('<tr><td colspan="6" class="text-center text-danger">Gagal mengambil data tracing dari server.</td></tr>');
            }
        });
    }

    // --- LOGIKA AUTOCOMPLETE BARANG ---
    function byId(id) { return document.getElementById(id); }
    function enc(v) { return encodeURIComponent(v == null ? "" : v); }
    function html(v) { return String(v == null ? "" : v).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\"/g, "&quot;"); }
    var itemRows = [], itemActiveIndex = -1, timerItem = null;

    function ajaxGet(url, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open("GET", url, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4 && xhr.status == 200) {
                var res; try { res = JSON.parse(xhr.responseText); } catch(e) { return; }
                callback(res);
            }
        };
        xhr.send(null);
    }

    function itemKeyup(e) {
        e = e || window.event;
        var key = e.keyCode || e.which;
        if (key == 38 || key == 40 || key == 13 || key == 27) return;

        if (byId("search_item").value === "") { byId("kode_barang").value = ""; }
        clearTimeout(timerItem);
        timerItem = setTimeout(function () { searchItem(byId("search_item").value); }, 250);
    }

    function itemKeydown(e) {
        e = e || window.event;
        var key = e.keyCode || e.which;
        var box = byId("itemSuggest");
        if (box.style.display == "none") return true;

        if (key == 40) { e.preventDefault(); setActiveItem(itemActiveIndex + 1); return false; } 
        if (key == 38) { e.preventDefault(); setActiveItem(itemActiveIndex - 1); return false; } 
        if (key == 13) { e.preventDefault(); if (itemActiveIndex >= 0) chooseItem(itemActiveIndex); return false; } 
        if (key == 27) { hideItemSuggest(); return false; } 
        return true;
    }

    function searchItem(q) {
        if (q == "") { hideItemSuggest(); return; }
        ajaxGet("posisi_dokumen.php?action=search_item&q=" + enc(q), function(res) {
            if (!res.success) return; renderItemSuggest(res.rows);
        });
    }

    function renderItemSuggest(rows) {
        itemRows = rows || []; itemActiveIndex = -1;
        var box = byId("itemSuggest"); box.innerHTML = "";
        if (itemRows.length == 0) { box.style.display = "none"; return; }
        for (var i = 0; i < itemRows.length; i++) {
            (function(idx) {
                var r = itemRows[idx];
                var div = document.createElement("div"); div.className = "suggest-item";
                div.innerHTML = "<b>" + html(r.ITEM_CODE) + "</b><br><span style='font-size:10px;'>" + html(r.ITEM_NAME) + "</span>";
                div.onmouseover = function() { setActiveItem(idx); };
                div.onmousedown = function(e) { if (e.preventDefault) e.preventDefault(); chooseItem(idx); };
                box.appendChild(div);
            })(i);
        }
        box.style.display = "block"; setActiveItem(0);
    }

    function setActiveItem(idx) {
        var box = byId("itemSuggest"); var items = box.getElementsByClassName("suggest-item");
        if (!items || items.length == 0) return;
        if (idx < 0) idx = items.length - 1; if (idx >= items.length) idx = 0;
        for (var i = 0; i < items.length; i++) items[i].className = "suggest-item";
        items[idx].className = "suggest-item active"; itemActiveIndex = idx;
    }

    function hideItemSuggest() {
        var box = byId("itemSuggest");
        if (box) { box.style.display = "none"; box.innerHTML = ""; }
        itemRows = []; itemActiveIndex = -1;
    }

    function chooseItem(idx) {
        if (idx < 0 || idx >= itemRows.length) return;
        var r = itemRows[idx]; hideItemSuggest();
        byId("kode_barang").value = r.ITEM_CODE;
        byId("search_item").value = r.ITEM_CODE + " - " + r.ITEM_NAME;
    }

    document.addEventListener("click", function(e) {
        var box = byId("itemSuggest");
        if (box && box.style.display != "none" && e.target.id != "search_item") { hideItemSuggest(); }
    });
</script>

</body>
</html>