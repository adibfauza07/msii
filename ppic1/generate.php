<?php
if (session_id() == "") {
    session_start();
}

/*
    FILE GENERATE BARCODE SHOWA
    Sesuai form Input Barcode Showa:
    - part_id
    - jenis
    - part_code
    - part_no
    - part_name
    - qty
    - lot
    - tanggal
*/

require_once dirname(__DIR__) . "/config/database_ppic.php";

/*
    Jika pakai Endroid QR Code, autoload biasanya ada di vendor.
    Kalau tidak ada, proses tetap lanjut tanpa QR image.
*/
$autoload1 = dirname(__DIR__) . "/vendor/autoload.php";
$autoload2 = __DIR__ . "/../vendor/autoload.php";

if (file_exists($autoload1)) {
    require_once $autoload1;
} elseif (file_exists($autoload2)) {
    require_once $autoload2;
}

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if ($conn === false) {
    echo "<script>
        alert('Koneksi database gagal. Silakan login ulang.');
        window.top.location.href = 'login.php';
    </script>";
    exit;
}

function post_value($name, $default) {
    if (isset($_POST[$name])) {
        return trim((string)$_POST[$name]);
    }
    return $default;
}

function clean_int($value) {
    if ($value === null || $value === "") {
        return 0;
    }
    return intval($value);
}

function safe_filename($value) {
    $value = preg_replace('/[^A-Za-z0-9_\\-]/', '_', (string)$value);
    if ($value == "") {
        $value = "QR";
    }
    return $value;
}

function stop_msg($msg) {
    echo "<!DOCTYPE html>
    <html>
    <head><meta charset='utf-8'></head>
    <body>
    <script>
        alert(" . json_encode($msg) . ");
        history.back();
    </script>
    </body>
    </html>";
    exit;
}

/* ==============================
   AMBIL POST DARI FORM
============================== */

$part_id   = clean_int(post_value("part_id", "0"));
$jenis     = post_value("jenis", "");
$qty       = clean_int(post_value("qty", "0"));
$lot       = post_value("lot", "");
$tanggal   = post_value("tanggal", "");
$part_name = post_value("part_name", "");

if ($part_id <= 0) {
    stop_msg("Part No belum dipilih.");
}

if ($jenis == "") {
    $jenis = "polibag";
}

if ($jenis != "polibag" && $jenis != "box") {
    stop_msg("Jenis label tidak valid.");
}

if ($tanggal == "") {
    $tanggal = date("Y-m-d");
}

$tsTanggal = strtotime($tanggal);
if ($tsTanggal === false) {
    stop_msg("Tanggal tidak valid.");
}
$tanggal = date("Y-m-d", $tsTanggal);

/* ==============================
   AMBIL MASTER PART
============================== */

$sqlPart = "
    SELECT TOP 1
        id,
        ISNULL(part_code, '') AS part_code,
        ISNULL(part_no, '') AS part_no,
        ISNULL(part_name, '') AS part_name,
        ISNULL(qty_polibag, 0) AS qty_polibag,
        ISNULL(qty_box, 0) AS qty_box
    FROM dbo.data_barcode_showa
    WHERE id = ?
";

$stmtPart = sqlsrv_query($conn, $sqlPart, array($part_id));

if ($stmtPart === false) {
    stop_msg("Query master part gagal: " . print_r(sqlsrv_errors(), true));
}

$p = sqlsrv_fetch_array($stmtPart, SQLSRV_FETCH_ASSOC);

if (!$p) {
    stop_msg("Data part tidak ditemukan di master Showa.");
}

$part_code = trim((string)$p["part_code"]);
$part_no   = trim((string)$p["part_no"]);

if ($part_name == "") {
    $part_name = trim((string)$p["part_name"]);
}

/*
    Jika qty dari form kosong, ambil dari master sesuai jenis label.
*/
if ($qty <= 0) {
    if ($jenis == "polibag") {
        $qty = clean_int($p["qty_polibag"]);
    } else {
        $qty = clean_int($p["qty_box"]);
    }
}

if ($qty <= 0) {
    stop_msg("Qty masih kosong / 0. Cek Qty Polibag atau Qty Box di master.");
}

/* ==============================
   GENERATE QR
============================== */

$qr_relative = "";

$dataQR =
    "CODE: " . $part_code . "\n" .
    "NO: " . $part_no . "\n" .
    "NAME: " . $part_name . "\n" .
    "JENIS: " . strtoupper($jenis) . "\n" .
    "QTY: " . $qty . "\n" .
    "LOT: " . $lot . "\n" .
    "TGL: " . $tanggal;

$qrDirFs = dirname(__DIR__) . "/assets/qr_showa";
$qrDirWeb = "../assets/qr_showa";

if (!is_dir($qrDirFs)) {
    @mkdir($qrDirFs, 0777, true);
}

if (is_dir($qrDirFs) && is_writable($qrDirFs) && class_exists("Endroid\\QrCode\\QrCode")) {
    $qrName = safe_filename($part_code) . "_" . date("Ymd_His") . ".png";
    $qrFileFs = $qrDirFs . "/" . $qrName;
    $qr_relative = $qrDirWeb . "/" . $qrName;

    try {
        $qr = QrCode::create($dataQR)->setSize(250);
        $writer = new PngWriter();
        $writer->write($qr)->saveToFile($qrFileFs);
    } catch (Exception $e) {
        /*
            Kalau QR gagal, tetap simpan label tanpa qr_path.
        */
        $qr_relative = "";
    }
}

/* ==============================
   INSERT BARCODE
============================== */

/*
    Supaya kompatibel dengan print_label.php lama,
    qty dimasukkan ke qty_polibag dan qty_box seperti save.php lama Bapak.
*/
$qty_polibag = $qty;
$qty_box     = $qty;

$sqlInsert = "
    INSERT INTO dbo.barcode_showa
        (part_code, part_no, part_name, qty_polibag, qty_box, qr_path, created_at)
    OUTPUT INSERTED.id
    VALUES
        (?, ?, ?, ?, ?, ?, GETDATE())
";

$paramsInsert = array(
    $part_code,
    $part_no,
    $part_name,
    $qty_polibag,
    $qty_box,
    $qr_relative
);

$stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

if ($stmtInsert === false) {
    stop_msg("Insert barcode_showa gagal: " . print_r(sqlsrv_errors(), true));
}

$rowId = sqlsrv_fetch_array($stmtInsert, SQLSRV_FETCH_ASSOC);

if (!$rowId || !isset($rowId["id"])) {
    stop_msg("Data tersimpan tapi ID label tidak terbaca.");
}

$id = intval($rowId["id"]);

/* ==============================
   REDIRECT KE PRINT LABEL LAMA
============================== */

header(
    "Location: print_label.php?id=" . $id .
    "&lot=" . urlencode($lot) .
    "&tgl=" . urlencode($tanggal) .
    "&jenis=" . urlencode($jenis)
);
exit;
?>