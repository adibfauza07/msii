<?php
/**
 * LAPORAN PENGELUARAN BARANG PER DOKUMEN PABEAN
 * PHP 5.4 + SQLSRV + SQL Server 2008 + AdminLTE Bootstrap
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

// Konfigurasi Database disamakan ke /config/database.php
require_once __DIR__ . '/config/database.php';

if (!isset($conn) || $conn === false) {
    header("Location: " . $loginUrl . "?error=session_expired");
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
 * AJAX: PENCARIAN AUTOCOMPLETE BARANG
 * ========================================================= */
$action = isset($_GET["action"]) ? trim($_GET["action"]) : "";

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
            $res[] = array(
                "ITEM_CODE" => trim($r["ITEM_CODE"]),
                "ITEM_NAME" => trim($r["ITEM_NAME"])
            );
        }
    }
    
    echo json_encode(array("success" => true, "rows" => $res));
    exit;
}

/* =========================================================
 * PARAMETER FILTER
 * ========================================================= */
$today       = date("Y-m-d");
$defaultFrom = date("Y-m-01");

$tglAwal = isset($_GET["tglawal"]) ? trim($_GET["tglawal"]) : $defaultFrom;
$tglAkhir = isset($_GET["tglakhir"]) ? trim($_GET["tglakhir"]) : $today;
$dokumen = isset($_GET["dokumen"]) ? trim($_GET["dokumen"]) : "";
$filterKodeBarang = isset($_GET["kode_barang"]) ? trim($_GET["kode_barang"]) : "";
$filterNamaBarang = isset($_GET["nama_barang"]) ? trim($_GET["nama_barang"]) : "";

$format = isset($_GET["format"]) ? strtolower(trim($_GET["format"])) : "html";
$autoPrint = isset($_GET["print"]) && $_GET["print"] == "1";

$errors = array();

if (!valid_date_ymd($tglAwal)) $errors[] = "Tanggal awal tidak valid.";
if (!valid_date_ymd($tglAkhir)) $errors[] = "Tanggal akhir tidak valid.";
if (valid_date_ymd($tglAwal) && valid_date_ymd($tglAkhir) && strtotime($tglAwal) > strtotime($tglAkhir)) {
    $errors[] = "Tanggal awal tidak boleh lebih besar dari tanggal akhir.";
}
if (strlen($dokumen) > 20) {
    $errors[] = "Jenis dokumen terlalu panjang.";
}

$spDokumen = ($dokumen == "") ? "%" : $dokumen;
$startParam = $tglAwal . " 00:00:00";
$endParam   = $tglAkhir . " 23:59:59";

/* =========================================================
 * DAFTAR JENIS DOKUMEN
 * ========================================================= */
$documentOptions = array(
    ""       => "-- Semua Dokumen --",
    "BC.2.3" => "BC.2.3",
    "BC.4.0" => "BC.4.0",
    "PIB"    => "PIB",
    "PEB"    => "PEB",
    "BC.2.5" => "BC.2.5",
    "BC.2.6.1" => "BC.2.6.1",
    "BC.2.6.2" => "BC.2.6.2",
    "BC.2.7" => "BC.2.7",
    "BC.4.1" => "BC.4.1",
    "BC.3.0" => "BC.3.0",
    "Internal" => "Internal"
);

/* =========================================================
 * PANGGIL STORED PROCEDURE & FILTER BARANG
 * ========================================================= */
$rows = array();
$queryError = "";

if (count($errors) == 0) {
    $sql = "{CALL dbo.sp_pengeluaran_dok(?, ?, ?)}";
    $params = array($startParam, $endParam, $spDokumen);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $queryError = sqlsrv_error_text();
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // FILTER TAMBAHAN: JIKA KODE BARANG DIPILIH
            if ($filterKodeBarang != "") {
                if (trim($row["KODE_BARANG"]) != $filterKodeBarang) {
                    continue; 
                }
            }
            $rows[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}

/* =========================================================
 * EXPORT EXCEL (Dengan Perbaikan Format MSO)
 * ========================================================= */
if ($format == "excel" && count($errors) == 0 && $queryError == "") {
    $filename = "laporan_pengeluaran_" . $tglAwal . "_sd_" . $tglAkhir . ".xls";

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
        /* CSS Khusus Excel untuk memaksa text dan number format */
        .xl-text { mso-number-format: "\@"; } 
        .xl-num { mso-number-format: "\#\,\#\#0\.00"; text-align: right; }
    </style>
</head>
<body>
<table>
    <tr><td class="title" colspan="13">LAPORAN PENGELUARAN BARANG PER DOKUMEN PABEAN</td></tr>
    <tr><td class="title" colspan="13">KAWASAN BERIKAT PT. IMC TEKNO INDONESIA</td></tr>
    <tr>
        <td class="title" colspan="13">
            Periode <?php echo h(display_date($tglAwal)); ?> s/d <?php echo h(display_date($tglAkhir)); ?>
            <?php if ($dokumen != "") { ?> - Dokumen <?php echo h($dokumen); ?><?php } ?>
            <?php if ($filterKodeBarang != "") { ?> - Barang: <?php echo h($filterKodeBarang); ?><?php } ?>
        </td>
    </tr>
    <tr><td colspan="13"></td></tr>
    <tr>
        <th rowspan="2">NO</th>
        <th colspan="3">DOKUMEN</th>
        <th colspan="2">BUKTI PENGELUARAN BARANG</th>
        <th rowspan="2">PENERIMA</th>
        <th rowspan="2">KODE BARANG</th>
        <th rowspan="2">URAIAN BARANG</th>
        <th rowspan="2">SAT</th>
        <th rowspan="2">JML</th>
        <th rowspan="2">VALAS</th>
        <th rowspan="2">NILAI PABEAN</th>
    </tr>
    <tr>
        <th>JENIS</th><th>NOMOR</th><th>TANGGAL</th><th>NOMOR</th><th>TANGGAL</th>
    </tr>
    <?php
    $no = 1;
    foreach ($rows as $row) {
    ?>
        <tr>
            <td class="center"><?php echo $no++; ?></td>
            <td class="xl-text"><?php echo h(isset($row["JENIS_BC"]) ? $row["JENIS_BC"] : ""); ?></td>
            <td class="xl-text"><?php echo h(isset($row["NOMOR_BC"]) ? $row["NOMOR_BC"] : ""); ?></td>
            <td class="xl-text center"><?php echo h(display_date(isset($row["TANGGAL_DOK"]) ? $row["TANGGAL_DOK"] : "")); ?></td>
            <td class="xl-text"><?php echo h(isset($row["BUKTI_PENGELUARAN_BARANG"]) ? $row["BUKTI_PENGELUARAN_BARANG"] : ""); ?></td>
            <td class="xl-text center"><?php echo h(display_date(isset($row["TANGGAL_BUKTI"]) ? $row["TANGGAL_BUKTI"] : "")); ?></td>
            <td class="xl-text"><?php echo h(isset($row["PENERIMA"]) ? $row["PENERIMA"] : ""); ?></td>
            <td class="xl-text"><?php echo h(isset($row["KODE_BARANG"]) ? $row["KODE_BARANG"] : ""); ?></td>
            <td class="xl-text"><?php echo h(isset($row["NAMA_BARANG"]) ? $row["NAMA_BARANG"] : ""); ?></td>
            <td class="center xl-text"><?php echo h(isset($row["SATUAN"]) ? $row["SATUAN"] : ""); ?></td>
            <!-- Output raw float value for Excel to format natively -->
            <td class="xl-num"><?php echo (isset($row["JUMLAH"]) ? (float)$row["JUMLAH"] : 0); ?></td>
            <td class="center xl-text"><?php echo h(isset($row["VALAS"]) ? $row["VALAS"] : ""); ?></td>
            <td class="xl-num"><?php echo (isset($row["NILAI_PABEAN"]) ? (float)$row["NILAI_PABEAN"] : 0); ?></td>
        </tr>
    <?php } ?>
    <?php if (count($rows) == 0) { ?>
        <tr><td colspan="13" class="center">Data tidak ditemukan.</td></tr>
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
    "tglawal"     => $tglAwal,
    "tglakhir"    => $tglAkhir,
    "dokumen"     => $dokumen,
    "kode_barang" => $filterKodeBarang,
    "nama_barang" => $filterNamaBarang
);
$excelUrl = "pengeluaran.php?" . http_build_query(
    array_merge($queryParams, array("format" => "excel"))
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Pengeluaran Barang</title>

    <!-- CSS Bootstrap 3 & FontAwesome -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- CSS AdminLTE 2 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/2.4.18/css/AdminLTE.min.css">

    <style>
        body { background: #ecf0f5; padding: 15px; font-family: 'Source Sans Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        
        /* AutoComplete Styles */
        .autocomplete-wrap { position: relative; }
        .suggest-box {
            position: absolute; top: 100%; left: 0; width: 100%; max-height: 250px;
            overflow-y: auto; background: #ffffff; border: 1px solid #ddd;
            z-index: 99999; display: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .suggest-item { padding: 8px 10px; border-bottom: 1px solid #eee; cursor: pointer; color: #333; line-height: 1.2; }
        .suggest-item:hover, .suggest-item.active { background: #3c8dbc; color: #ffffff; }

        .table-pengeluaran th { background-color: #f4f4f4; text-align: center; vertical-align: middle !important; font-size: 12px; }
        .table-pengeluaran td { font-size: 12px; vertical-align: middle !important; }
        .report-header { text-align: center; margin-bottom: 20px; }
        .report-header h3, .report-header h4, .report-header h5 { margin: 5px 0; font-weight: bold; }
        
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { background: #fff !important; padding: 0 !important; }
            .no-print { display: none !important; }
            .box { border: none !important; box-shadow: none !important; }
            .table-pengeluaran th, .table-pengeluaran td { 
                border: 1px solid #000 !important; font-size: 10px !important; padding: 4px !important;
            }
            .table-pengeluaran th { background-color: #e0e0e0 !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="row no-print">
    <div class="col-xs-12">
        <!-- FILTER BOX -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-filter"></i> Filter Laporan Pengeluaran</h3>
                <div class="box-tools pull-right">
                    <span class="text-muted" style="margin-right: 15px;">
                        Login: <strong><?php echo h($_SESSION["db_user"]); ?></strong>
                    </span>
              
                </div>
            </div>
            <div class="box-body">
                <form method="get" action="pengeluaran.php" class="form-inline">
                    <div class="form-group" style="margin-right: 10px;">
                        <label for="tglawal" style="margin-right: 5px;">Periode</label>
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
                                <option value="<?php echo h($optionValue); ?>" <?php echo ($dokumen == $optionValue) ? 'selected="selected"' : ""; ?>>
                                    <?php echo h($optionLabel); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <!-- AUTOCOMPLETE PENCARIAN BARANG -->
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
        <h4><i class="icon fa fa-warning"></i> Stored procedure gagal dijalankan!</h4>
        <?php echo nl2br(h($queryError)); ?>
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
                    <h3>LAPORAN PENGELUARAN BARANG PER DOKUMEN PABEAN</h3>
                    <h4>KAWASAN BERIKAT PT. IMC TEKNO INDONESIA</h4>
                    <h5>
                        Periode: <?php echo h(display_date($tglAwal)); ?> s/d <?php echo h(display_date($tglAkhir)); ?>
                        <?php if ($dokumen != "") { echo " | Dokumen: " . h($dokumen); } ?>
                        <?php if ($filterKodeBarang != "") { echo " | Barang: " . h($filterKodeBarang); } ?>
                    </h5>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover table-pengeluaran">
                        <thead>
                            <tr>
                                <th rowspan="2">NO</th>
                                <th colspan="3">DOKUMEN</th>
                                <th colspan="2">BUKTI PENGELUARAN BARANG</th>
                                <th rowspan="2">PENERIMA</th>
                                <th rowspan="2">KODE BARANG</th>
                                <th rowspan="2">URAIAN BARANG</th>
                                <th rowspan="2">SAT</th>
                                <th rowspan="2">JML</th>
                                <th rowspan="2">VALAS</th>
                                <th rowspan="2">NILAI PABEAN</th>
                            </tr>
                            <tr>
                                <th>JENIS</th>
                                <th>NOMOR</th>
                                <th>TANGGAL</th>
                                <th>NOMOR</th>
                                <th>TANGGAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            foreach ($rows as $row) {
                            ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td><?php echo h(isset($row["JENIS_BC"]) ? $row["JENIS_BC"] : ""); ?></td>
                                    <td><?php echo h(isset($row["NOMOR_BC"]) ? $row["NOMOR_BC"] : ""); ?></td>
                                    <td class="text-center"><?php echo h(display_date(isset($row["TANGGAL_DOK"]) ? $row["TANGGAL_DOK"] : "")); ?></td>
                                    <td><?php echo h(isset($row["BUKTI_PENGELUARAN_BARANG"]) ? $row["BUKTI_PENGELUARAN_BARANG"] : ""); ?></td>
                                    <td class="text-center"><?php echo h(display_date(isset($row["TANGGAL_BUKTI"]) ? $row["TANGGAL_BUKTI"] : "")); ?></td>
                                    <td><?php echo h(isset($row["PENERIMA"]) ? $row["PENERIMA"] : ""); ?></td>
                                    <td><?php echo h(isset($row["KODE_BARANG"]) ? $row["KODE_BARANG"] : ""); ?></td>
                                    <td><?php echo h(isset($row["NAMA_BARANG"]) ? $row["NAMA_BARANG"] : ""); ?></td>
                                    <td class="text-center"><?php echo h(isset($row["SATUAN"]) ? $row["SATUAN"] : ""); ?></td>
                                    <td class="text-right"><?php echo h(number_id(isset($row["JUMLAH"]) ? $row["JUMLAH"] : 0, 2)); ?></td>
                                    <td class="text-center"><?php echo h(isset($row["VALAS"]) ? $row["VALAS"] : ""); ?></td>
                                    <td class="text-right"><?php echo h(number_id(isset($row["NILAI_PABEAN"]) ? $row["NILAI_PABEAN"] : 0, 2)); ?></td>
                                </tr>
                            <?php } ?>

                            <?php if (count($rows) == 0) { ?>
                                <tr>
                                    <td colspan="13" class="text-center text-muted" style="padding: 20px;">
                                        <em>Data pengeluaran tidak ditemukan untuk periode, jenis dokumen, dan filter barang yang dipilih.</em>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="row no-print" style="margin-top: 10px;">
                    <div class="col-xs-6 text-muted" style="font-size: 11px;">
                        Jumlah baris: <strong><?php echo count($rows); ?></strong>
                    </div>
                    <div class="col-xs-6 text-right text-muted" style="font-size: 11px;">
                        Dicetak: <?php echo date("d-m-Y H:i:s"); ?> | Pengguna: <strong><?php echo h($_SESSION["db_user"]); ?></strong>
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

<!-- Logika JS Autocomplete Barang -->
<script>
    var itemRows = [], itemActiveIndex = -1, timerItem = null;

    function byId(id) { return document.getElementById(id); }
    function enc(v) { return encodeURIComponent(v == null ? "" : v); }
    function html(v) {
        return String(v == null ? "" : v).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\"/g, "&quot;");
    }

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

        // Reset kode tersembunyi jika input dihapus
        if (byId("search_item").value === "") {
            byId("kode_barang").value = "";
        }

        clearTimeout(timerItem);
        timerItem = setTimeout(function () { searchItem(byId("search_item").value); }, 250);
    }

    function itemKeydown(e) {
        e = e || window.event;
        var key = e.keyCode || e.which;
        var box = byId("itemSuggest");
        if (box.style.display == "none") return true;

        if (key == 40) { e.preventDefault(); setActiveItem(itemActiveIndex + 1); return false; } // Down
        if (key == 38) { e.preventDefault(); setActiveItem(itemActiveIndex - 1); return false; } // Up
        if (key == 13) { 
            e.preventDefault(); 
            if (itemActiveIndex >= 0) chooseItem(itemActiveIndex); 
            return false; 
        } // Enter
        if (key == 27) { hideItemSuggest(); return false; } // Escape
        return true;
    }

    function searchItem(q) {
        if (q == "") { hideItemSuggest(); return; }
        ajaxGet("pengeluaran.php?action=search_item&q=" + enc(q), function(res) {
            if (!res.success) return;
            renderItemSuggest(res.rows);
        });
    }

    function renderItemSuggest(rows) {
        itemRows = rows || [];
        itemActiveIndex = -1;
        var box = byId("itemSuggest");
        box.innerHTML = "";
        if (itemRows.length == 0) { box.style.display = "none"; return; }
        
        for (var i = 0; i < itemRows.length; i++) {
            (function(idx) {
                var r = itemRows[idx];
                var div = document.createElement("div");
                div.className = "suggest-item";
                div.innerHTML = "<b>" + html(r.ITEM_CODE) + "</b><br><span style='font-size:10px;'>" + html(r.ITEM_NAME) + "</span>";
                div.onmouseover = function() { setActiveItem(idx); };
                div.onmousedown = function(e) { if (e.preventDefault) e.preventDefault(); chooseItem(idx); };
                box.appendChild(div);
            })(i);
        }
        box.style.display = "block";
        setActiveItem(0);
    }

    function setActiveItem(idx) {
        var box = byId("itemSuggest");
        var items = box.getElementsByClassName("suggest-item");
        if (!items || items.length == 0) return;
        if (idx < 0) idx = items.length - 1;
        if (idx >= items.length) idx = 0;
        for (var i = 0; i < items.length; i++) items[i].className = "suggest-item";
        items[idx].className = "suggest-item active";
        itemActiveIndex = idx;
    }

    function hideItemSuggest() {
        var box = byId("itemSuggest");
        if (box) {
            box.style.display = "none";
            box.innerHTML = "";
        }
        itemRows = []; itemActiveIndex = -1;
    }

    function chooseItem(idx) {
        if (idx < 0 || idx >= itemRows.length) return;
        var r = itemRows[idx];
        hideItemSuggest();
        
        byId("kode_barang").value = r.ITEM_CODE;
        byId("search_item").value = r.ITEM_CODE + " - " + r.ITEM_NAME;
    }

    document.addEventListener("click", function(e) {
        var box = byId("itemSuggest");
        if (box && box.style.display != "none" && e.target.id != "search_item") {
            hideItemSuggest();
        }
    });

    <?php if ($autoPrint && count($errors) == 0 && $queryError == "") { ?>
    window.onload = function () { window.print(); };
    <?php } ?>
</script>

</body>
</html>