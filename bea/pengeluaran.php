<?php
/**
 * LAPORAN PENGELUARAN BARANG PER DOKUMEN PABEAN
 * PHP 5.4 + SQLSRV + SQL Server 2008
 *
 * Lokasi:
 *   /msii/bea/pengeluaran.php
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
$moduleDatabase = __DIR__ . "/config/database.php";
$sharedPlant1   = __DIR__ . "/../config/db_plant1.php";
$sharedPlan1    = __DIR__ . "/../config/db_plan1.php";

if (file_exists($moduleDatabase)) {
    require_once $moduleDatabase;
} elseif (file_exists($sharedPlant1)) {
    require_once $sharedPlant1;
} elseif (file_exists($sharedPlan1)) {
    require_once $sharedPlan1;
} else {
    die("File koneksi database Plant 1 tidak ditemukan.");
}

if (!isset($conn) || $conn === false) {
    header("Location: " . $loginUrl . "?error=session_expired");
    exit();
}

/* =========================================================
 * FUNGSI BANTU
 * ========================================================= */
function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function valid_date_ymd($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $parts = explode("-", $value);
    if (count($parts) != 3) {
        return false;
    }
    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

function display_date($value) {
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

function number_id($value, $decimal) {
    if ($value === null || $value === "") {
        return "";
    }
    return number_format((float) $value, $decimal, ".", ",");
}

function sqlsrv_error_text() {
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
$dokumen = isset($_GET["dokumen"]) ? trim($_GET["dokumen"]) : "";
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

$documentOptions = array(
    ""         => "-- Semua Dokumen --",
    "BC.2.3"   => "BC.2.3",
    "BC.4.0"   => "BC.4.0",
    "PIB"      => "PIB",
    "PEB"      => "PEB",
    "BC.2.5"   => "BC.2.5",
    "BC.2.6.1" => "BC.2.6.1",
    "BC.2.6.2" => "BC.2.6.2",
    "BC.2.7"   => "BC.2.7",
    "BC.4.1"   => "BC.4.1",
    "BC.3.0"   => "BC.3.0",
    "Internal" => "Internal"
);

/* =========================================================
 * [OPTIMASI]: PANGGIL STORED PROCEDURE TANPA BUFFER ARRAY
 * ========================================================= */
$queryError = "";
$stmt = null;

if (count($errors) == 0) {
    $sql = "{CALL dbo.sp_pengeluaran_dok(?, ?, ?)}";
    $params = array($startParam, $endParam, $spDokumen);

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $queryError = sqlsrv_error_text();
    }
    // KITA TIDAK LAGI MENGGUNAKAN LOOP fetch_array DISINI. 
    // Data akan langsung di-looping di bagian Render/View.
}

/* =========================================================
 * EXPORT EXCEL
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
        .title { border: 0; font-size: 12px; font-weight: bold; }
        .center { text-align: center; }
        .number { text-align: right; }
    </style>
</head>
<body>
<table>
    <tr><td class="title" colspan="13">LAPORAN PENGELUARAN BARANG PER DOKUMEN PABEAN</td></tr>
    <tr><td class="title" colspan="13">KAWASAN BERIKAT PT. IMC TEKNO INDONESIA</td></tr>
    <tr>
        <td class="title" colspan="13">
            Periode <?php echo h(display_date($tglAwal)); ?> s/d <?php echo h(display_date($tglAkhir)); ?>
            <?php if ($dokumen != "") echo "- Dokumen " . h($dokumen); ?>
        </td>
    </tr>
    <tr><td colspan="13"></td></tr>
    <tr>
        <th rowspan="2">NO</th>
        <th colspan="3">DOKUMEN</th>
        <th colspan="2">BUKTI PENGELUARAN BARANG</th>
        <th rowspan="2">PENERIMA</th><th rowspan="2">KODE BARANG</th>
        <th rowspan="2">URAIAN BARANG</th><th rowspan="2">SAT</th>
        <th rowspan="2">JML</th><th rowspan="2">VALAS</th><th rowspan="2">NILAI PABEAN</th>
    </tr>
    <tr>
        <th>JENIS</th><th>NOMOR</th><th>TANGGAL</th>
        <th>NOMOR</th><th>TANGGAL</th>
    </tr>
    <?php
    // [OPTIMASI]: LOOPING DATA EXCEL SECARA STREAMING (Unbuffered)
    $no = 1;
    $hasData = false;
    
    if ($stmt !== null) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $hasData = true;
    ?>
        <tr>
            <td class="center"><?php echo $no; ?></td>
            <td><?php echo h(isset($row["JENIS_BC"]) ? $row["JENIS_BC"] : ""); ?></td>
            <td><?php echo h(isset($row["NOMOR_BC"]) ? $row["NOMOR_BC"] : ""); ?></td>
            <td><?php echo h(display_date(isset($row["TANGGAL_DOK"]) ? $row["TANGGAL_DOK"] : "")); ?></td>
            <td><?php echo h(isset($row["BUKTI_PENGELUARAN_BARANG"]) ? $row["BUKTI_PENGELUARAN_BARANG"] : ""); ?></td>
            <td><?php echo h(display_date(isset($row["TANGGAL_BUKTI"]) ? $row["TANGGAL_BUKTI"] : "")); ?></td>
            <td><?php echo h(isset($row["PENERIMA"]) ? $row["PENERIMA"] : ""); ?></td>
            <td><?php echo h(isset($row["KODE_BARANG"]) ? $row["KODE_BARANG"] : ""); ?></td>
            <td><?php echo h(isset($row["NAMA_BARANG"]) ? $row["NAMA_BARANG"] : ""); ?></td>
            <td class="center"><?php echo h(isset($row["SATUAN"]) ? $row["SATUAN"] : ""); ?></td>
            <td class="number"><?php echo h(number_id(isset($row["JUMLAH"]) ? $row["JUMLAH"] : 0, 2)); ?></td>
            <td class="center"><?php echo h(isset($row["VALAS"]) ? $row["VALAS"] : ""); ?></td>
            <td class="number"><?php echo h(number_id(isset($row["NILAI_PABEAN"]) ? $row["NILAI_PABEAN"] : 0, 2)); ?></td>
        </tr>
    <?php
            $no++;
        }
        sqlsrv_free_stmt($stmt); // Bebaskan memori setelah export selesai
    }
    
    if (!$hasData) { 
    ?>
        <tr><td colspan="13" class="center">Data tidak ditemukan.</td></tr>
    <?php } ?>
</table>
</body>
</html>
<?php
    exit();
}

/* =========================================================
 * URL BANTU & HTML VIEW
 * ========================================================= */
$queryParams = array("tglawal" => $tglAwal, "tglakhir" => $tglAkhir, "dokumen" => $dokumen);
$excelUrl = "pengeluaran.php?" . http_build_query(array_merge($queryParams, array("format" => "excel")));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <!-- [Tag Head dan Styles (Tidak Saya Ubah, Tetap Mempertahankan Desain Anda)] -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Pengeluaran Barang</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        /* (Style bawaan Anda tetap dipertahankan) */
        html, body { background: #ffffff; color: #111111; font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; }
        .screen-toolbar { background: #eef3f7; border-bottom: 1px solid #bac7d1; padding: 10px 12px; }
        .screen-toolbar .form-inline { margin-bottom: 8px; }
        .screen-toolbar label { margin-right: 4px; }
        .screen-toolbar .form-control { height: 30px; padding: 4px 7px; font-size: 12px; }
        .screen-toolbar .btn { height: 30px; padding: 5px 10px; font-size: 12px; }
        .report-wrapper { min-width: 1280px; padding: 8px 10px 25px; }
        .report-title { margin-bottom: 12px; line-height: 1.35; }
        .report-title h1 { margin: 0; font-size: 12px; font-weight: bold; }
        .report-title h2 { margin: 0; font-size: 11px; font-weight: bold; }
        .report-title p { margin: 2px 0 0; font-size: 10px; }
        .report-info { float: right; margin-top: -35px; font-size: 10px; text-align: right; }
        .report-table { width: 100%; margin: 0; border-collapse: collapse; table-layout: fixed; }
        .report-table th, .report-table td { border: 1px solid #222222 !important; padding: 2px 3px !important; font-size: 9px; line-height: 1.15; vertical-align: top !important; word-wrap: break-word; }
        .report-table thead th { background: #f4f4f4 !important; text-align: center; vertical-align: middle !important; font-weight: bold; }
        .report-table .number { text-align: right; white-space: nowrap; }
        .report-table .center { text-align: center; }
        .col-no { width: 30px; } .col-jenis { width: 55px; } .col-nomor-bc { width: 80px; } .col-date { width: 70px; }
        .col-bukti { width: 175px; } .col-penerima { width: 220px; } .col-kode { width: 95px; } .col-uraian { width: 285px; }
        .col-sat { width: 42px; } .col-jml { width: 70px; } .col-valas { width: 70px; } .col-total { width: 95px; }
        .alert-report { margin: 10px; white-space: pre-wrap; }
        .report-footer { margin-top: 8px; font-size: 9px; }
        .no-data { padding: 25px !important; text-align: center; font-size: 11px !important; }
        @media print {
            @page { size: A3 landscape; margin: 7mm; }
            html, body { width: auto; height: auto; font-size: 9px; }
            .no-print { display: none !important; }
            .report-wrapper { min-width: 0; padding: 0; }
            .report-table th, .report-table td { padding: 1px 2px !important; font-size: 7px; }
            .report-info { display: none; }
        }
    </style>
</head>
<body>

<div class="screen-toolbar no-print">
    <form method="get" action="pengeluaran.php" class="form-inline">
        <div class="form-group">
            <label for="tglawal">Periode</label>
            <input type="date" name="tglawal" id="tglawal" class="form-control" value="<?php echo h($tglAwal); ?>" required>
        </div>
        <div class="form-group">
            <label for="tglakhir">s/d</label>
            <input type="date" name="tglakhir" id="tglakhir" class="form-control" value="<?php echo h($tglAkhir); ?>" required>
        </div>
        <div class="form-group">
            <label for="dokumen">Dokumen</label>
            <select name="dokumen" id="dokumen" class="form-control">
                <?php foreach ($documentOptions as $optionValue => $optionLabel) { ?>
                    <option value="<?php echo h($optionValue); ?>" <?php echo ($dokumen == $optionValue) ? 'selected="selected"' : ""; ?>>
                        <?php echo h($optionLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Tampilkan</button>
        <button type="button" class="btn btn-default" onclick="window.print();"><i class="fa fa-print"></i> Cetak / PDF</button>
        <a href="<?php echo h($excelUrl); ?>" class="btn btn-success"><i class="fa fa-file-excel-o"></i> Excel</a>
        <a href="laporan.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> Kembali</a>
    </form>
    <div>
        Login: <strong><?php echo h($_SESSION["db_user"]); ?></strong> &nbsp;|&nbsp; <a href="logout.php">Logout</a>
    </div>
</div>

<?php if (count($errors) > 0) { ?>
    <div class="alert alert-danger alert-report">
        <strong>Filter tidak valid:</strong> <?php echo h(implode("\n", $errors)); ?>
    </div>
<?php } ?>

<?php if ($queryError != "") { ?>
    <div class="alert alert-danger alert-report">
        <strong>Stored procedure gagal dijalankan:</strong> <?php echo h($queryError); ?>
    </div>
<?php } ?>

<?php if (count($errors) == 0 && $queryError == "") { ?>
<div class="report-wrapper">
    <div class="report-title">
        <h1>LAPORAN PENGELUARAN BARANG PER DOKUMEN PABEAN</h1>
        <h2>KAWASAN BERIKAT PT. IMC TEKNO INDONESIA</h2>
        <p>
            Periode <?php echo h(display_date($tglAwal)); ?> s/d <?php echo h(display_date($tglAkhir)); ?>
            <?php if ($dokumen != "") { ?> &nbsp;|&nbsp; Dokumen: <strong><?php echo h($dokumen); ?></strong><?php } ?>
        </p>
    </div>

    <!-- [OPTIMASI]: Menggunakan span tag dengan ID agar dapat diupdate JS setelah dilooping -->
    <div class="report-info no-print">
        Jumlah baris: <strong id="lbl-row-count">Menghitung...</strong>
    </div>

    <table class="table report-table">
        <colgroup>
            <col class="col-no"><col class="col-jenis"><col class="col-nomor-bc">
            <col class="col-date"><col class="col-bukti"><col class="col-date">
            <col class="col-penerima"><col class="col-kode"><col class="col-uraian">
            <col class="col-sat"><col class="col-jml"><col class="col-valas"><col class="col-total">
        </colgroup>
        <thead>
            <tr>
                <th rowspan="2">NO</th><th colspan="3">DOKUMEN</th>
                <th colspan="2">BUKTI PENGELUARAN BARANG</th>
                <th rowspan="2">PENERIMA</th><th rowspan="2">KODE BARANG</th>
                <th rowspan="2">URAIAN BARANG</th><th rowspan="2">SAT</th>
                <th rowspan="2">JML</th><th rowspan="2">VALAS</th><th rowspan="2">NILAI PABEAN</th>
            </tr>
            <tr>
                <th>JENIS</th><th>NOMOR</th><th>TANGGAL</th><th>NOMOR</th><th>TANGGAL</th>
            </tr>
        </thead>
        <tbody>
        <?php
        // [OPTIMASI]: LOOPING DATA HTML SECARA STREAMING (Unbuffered)
        $no = 1;
        $hasData = false;

        if ($stmt !== null) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $hasData = true;
        ?>
            <tr>
                <td class="center"><?php echo $no; ?></td>
                <td><?php echo h(isset($row["JENIS_BC"]) ? $row["JENIS_BC"] : ""); ?></td>
                <td><?php echo h(isset($row["NOMOR_BC"]) ? $row["NOMOR_BC"] : ""); ?></td>
                <td class="center"><?php echo h(display_date(isset($row["TANGGAL_DOK"]) ? $row["TANGGAL_DOK"] : "")); ?></td>
                <td><?php echo h(isset($row["BUKTI_PENGELUARAN_BARANG"]) ? $row["BUKTI_PENGELUARAN_BARANG"] : ""); ?></td>
                <td class="center"><?php echo h(display_date(isset($row["TANGGAL_BUKTI"]) ? $row["TANGGAL_BUKTI"] : "")); ?></td>
                <td><?php echo h(isset($row["PENERIMA"]) ? $row["PENERIMA"] : ""); ?></td>
                <td><?php echo h(isset($row["KODE_BARANG"]) ? $row["KODE_BARANG"] : ""); ?></td>
                <td><?php echo h(isset($row["NAMA_BARANG"]) ? $row["NAMA_BARANG"] : ""); ?></td>
                <td class="center"><?php echo h(isset($row["SATUAN"]) ? $row["SATUAN"] : ""); ?></td>
                <td class="number"><?php echo h(number_id(isset($row["JUMLAH"]) ? $row["JUMLAH"] : 0, 2)); ?></td>
                <td class="center"><?php echo h(isset($row["VALAS"]) ? $row["VALAS"] : ""); ?></td>
                <td class="number"><?php echo h(number_id(isset($row["NILAI_PABEAN"]) ? $row["NILAI_PABEAN"] : 0, 2)); ?></td>
            </tr>
        <?php
                $no++;
            }
            sqlsrv_free_stmt($stmt); // Bebaskan memori
        }
        
        if (!$hasData) { 
        ?>
            <tr>
                <td colspan="13" class="no-data">
                    Data pengeluaran tidak ditemukan untuk periode dan jenis dokumen yang dipilih.
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>

    <div class="report-footer">
        Dicetak: <?php echo date("d-m-Y H:i:s"); ?> &nbsp;|&nbsp; Pengguna: <?php echo h($_SESSION["db_user"]); ?>
    </div>
</div>

<!-- [OPTIMASI]: Update Jumlah Baris setelah proses render menggunakan JS -->
<script>
    var elRowCount = document.getElementById('lbl-row-count');
    if(elRowCount !== null) {
        elRowCount.innerText = '<?php echo ($no - 1); ?>';
    }
</script>
<?php } ?>

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