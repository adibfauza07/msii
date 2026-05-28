<?php
if (session_id() == "") {
    session_start();
}

/*
    SAVE BARCODE SHOWA
    Generate label tetap ke print_label.php
*/

require_once dirname(__DIR__) . "/config/db_plant2.php";

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

if (!isset($_POST["part_id"]) || trim($_POST["part_id"]) == "") {
    stop_msg("Invalid Request. Part ID kosong.");
}

$part_id = clean_int($_POST["part_id"]);
$qty     = clean_int(post_value("qty", "0"));
$lot     = post_value("lot", "");
$tanggal = post_value("tanggal", "");

/*
    Part name tetap ambil dari input form,
    supaya user bisa custom description sebelum generate.
*/
$part_name = post_value("part_name", "");

if ($part_id <= 0) {
    stop_msg("Part ID tidak valid.");
}

if ($qty <= 0) {
    stop_msg("Qty masih kosong / 0.");
}

if ($tanggal == "") {
    $tanggal = date("Y-m-d");
}

/*
    Validasi tanggal.
*/
$tsTanggal = strtotime($tanggal);

if ($tsTanggal === false) {
    stop_msg("Tanggal tidak valid.");
}

$tanggal = date("Y-m-d", $tsTanggal);

/*
    Ambil data master berdasarkan part_id.
*/
$sql = "
    SELECT TOP 1
        id,
        ISNULL(part_code, '') AS part_code,
        ISNULL(part_no, '') AS part_no,
        ISNULL(part_name, '') AS part_name
    FROM dbo.data_barcode_showa
    WHERE id = ?
";

$res = sqlsrv_query($conn, $sql, array($part_id));

if ($res === false) {
    stop_msg("Query part gagal: " . print_r(sqlsrv_errors(), true));
}

$p = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

if (!$p) {
    stop_msg("Data part tidak ditemukan.");
}

$part_code = trim((string)$p["part_code"]);
$part_no   = trim((string)$p["part_no"]);

/*
    Kalau part_name dari form kosong, fallback ke database.
*/
if ($part_name == "") {
    $part_name = trim((string)$p["part_name"]);
}

$qr_path = "";

/*
    Insert dan langsung ambil ID dengan OUTPUT INSERTED.id.
    Ini lebih aman daripada SELECT TOP 1 ORDER BY id DESC.
*/
$sql2 = "
    INSERT INTO dbo.barcode_showa
        (part_code, part_no, part_name, qty_polibag, qty_box, qr_path, created_at)
    OUTPUT INSERTED.id
    VALUES
        (?, ?, ?, ?, ?, ?, GETDATE())
";

$params2 = array(
    $part_code,
    $part_no,
    $part_name,
    $qty,
    $qty,
    $qr_path
);

$stmt = sqlsrv_query($conn, $sql2, $params2);

if ($stmt === false) {
    stop_msg("Insert Error: " . print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$row || !isset($row["id"])) {
    stop_msg("Data tersimpan tapi ID label tidak terbaca.");
}

$id = intval($row["id"]);

/*
    Redirect ke print label lama.
*/
header("Location: print_label.php?id=" . $id . "&lot=" . urlencode($lot) . "&tgl=" . urlencode($tanggal));
exit;
?>