<?php
// ==========================================================
// api_master_proses.php
// Backend AJAX murni untuk Master Proses & Station
// ==========================================================
if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}
session_write_close(); 
error_reporting(0);
header("Content-Type: application/json; charset=utf-8");

// Konfigurasi database langsung dari folder config lokal
require_once __DIR__ . '/config/database.php';

if (!isset($conn) || $conn === false) {
    echo json_encode(array("success" => false, "message" => "Koneksi database gagal.", "rows" => array()));
    exit;
}

function get_value($name, $default) {
    if (isset($_GET[$name])) return trim((string)$_GET[$name]);
    if (isset($_POST[$name])) return trim((string)$_POST[$name]);
    return $default;
}

function post_value($name, $default) {
    if (isset($_POST[$name])) return trim((string)$_POST[$name]);
    return $default;
}

function to_int($value) {
    return ($value === null || $value === "") ? 0 : intval($value);
}

function to_float($value) {
    return ($value === null || $value === "") ? 0 : floatval(str_replace(",", "", (string)$value));
}

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

$action = get_value("action", "");

/* ==========================================================
   AJAX: LOAD PROCESS
========================================================== */
if ($action == "load_process") {
    $sql = "
        SELECT
            PROC_ID,
            ISNULL(PROC_NAME, '') AS PROC_NAME,
            ISNULL(PROC_EFFICIENTCY, 0) AS PROC_EFFICIENTCY,
            ISNULL(PROC_MEASURE, 0) AS PROC_MEASURE,
            ISNULL(PROC_HOURS, 0) AS PROC_HOURS,
            ISNULL(PROC_MMDAY, 0) AS PROC_MMDAY,
            ISNULL(PROC_MMDAY2, 0) AS PROC_MMDAY2,
            ISNULL(PROC_MMDAY3, 0) AS PROC_MMDAY3
        FROM dbo.PROCESS
        ORDER BY PROC_NAME
    ";

    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        echo json_encode(array("success" => false, "message" => sql_error_text(), "rows" => array()));
        exit;
    }

    $rows = array();
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = array(
            "PROC_ID" => to_int($r["PROC_ID"]),
            "PROC_NAME" => trim((string)$r["PROC_NAME"]),
            "PROC_EFFICIENTCY" => (float)$r["PROC_EFFICIENTCY"],
            "PROC_MEASURE" => (float)$r["PROC_MEASURE"],
            "PROC_HOURS" => (float)$r["PROC_HOURS"],
            "PROC_MMDAY" => (float)$r["PROC_MMDAY"],
            "PROC_MMDAY2" => (float)$r["PROC_MMDAY2"],
            "PROC_MMDAY3" => (float)$r["PROC_MMDAY3"]
        );
    }

    echo json_encode(array("success" => true, "rows" => $rows));
    exit;
}

/* ==========================================================
   AJAX: LOAD STATION BY PROCESS
========================================================== */
if ($action == "load_station") {
    $proc_id = to_int(get_value("PROC_ID", "0"));

    if ($proc_id <= 0) {
        echo json_encode(array("success" => true, "rows" => array()));
        exit;
    }

    $sql = "
        SELECT
            MAG_ID,
            ISNULL(MAG_STATION, '') AS MAG_STATION,
            ISNULL(MAG_LOC, '') AS MAG_LOC,
            ISNULL(PROC_ID, 0) AS PROC_ID
        FROM dbo.MAG
        WHERE PROC_ID = ?
        ORDER BY MAG_STATION, MAG_LOC
    ";

    $stmt = sqlsrv_query($conn, $sql, array($proc_id));
    if ($stmt === false) {
        echo json_encode(array("success" => false, "message" => sql_error_text(), "rows" => array()));
        exit;
    }

    $rows = array();
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = array(
            "MAG_ID" => to_int($r["MAG_ID"]),
            "MAG_STATION" => trim((string)$r["MAG_STATION"]),
            "MAG_LOC" => trim((string)$r["MAG_LOC"]),
            "PROC_ID" => to_int($r["PROC_ID"])
        );
    }

    echo json_encode(array("success" => true, "rows" => $rows));
    exit;
}

/* ==========================================================
   AJAX: SAVE PROCESS
========================================================== */
if ($action == "save_process") {
    $proc_id = to_int(post_value("PROC_ID", "0"));
    $proc_name = post_value("PROC_NAME", "");
    $proc_eff = to_float(post_value("PROC_EFFICIENTCY", "0"));
    $proc_measure = to_float(post_value("PROC_MEASURE", "0"));
    $proc_hours = to_float(post_value("PROC_HOURS", "0"));
    $proc_mmday = to_float(post_value("PROC_MMDAY", "0"));
    $proc_mmday2 = to_float(post_value("PROC_MMDAY2", "0"));
    $proc_mmday3 = to_float(post_value("PROC_MMDAY3", "0"));

    if ($proc_name == "") {
        echo json_encode(array("success" => false, "message" => "PROSES belum diisi."));
        exit;
    }

    if ($proc_id > 0) {
        $sql = "UPDATE dbo.PROCESS SET PROC_NAME = ?, PROC_EFFICIENTCY = ?, PROC_MEASURE = ?, PROC_HOURS = ?, PROC_MMDAY = ?, PROC_MMDAY2 = ?, PROC_MMDAY3 = ? WHERE PROC_ID = ?";
        $params = array($proc_name, $proc_eff, $proc_measure, $proc_hours, $proc_mmday, $proc_mmday2, $proc_mmday3, $proc_id);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            echo json_encode(array("success" => false, "message" => sql_error_text()));
            exit;
        }
        echo json_encode(array("success" => true, "message" => "Process berhasil diupdate.", "PROC_ID" => $proc_id));
        exit;
    }

    $sql = "INSERT INTO dbo.PROCESS (PROC_NAME, PROC_EFFICIENTCY, PROC_MEASURE, PROC_HOURS, PROC_MMDAY, PROC_MMDAY2, PROC_MMDAY3) OUTPUT INSERTED.PROC_ID VALUES (?, ?, ?, ?, ?, ?, ?)";
    $params = array($proc_name, $proc_eff, $proc_measure, $proc_hours, $proc_mmday, $proc_mmday2, $proc_mmday3);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo json_encode(array("success" => false, "message" => sql_error_text()));
        exit;
    }

    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$r || !isset($r["PROC_ID"])) {
        echo json_encode(array("success" => false, "message" => "Insert berhasil tapi PROC_ID tidak terbaca."));
        exit;
    }

    echo json_encode(array("success" => true, "message" => "Process berhasil ditambah.", "PROC_ID" => to_int($r["PROC_ID"])));
    exit;
}

/* ==========================================================
   AJAX: DELETE PROCESS
========================================================== */
if ($action == "delete_process") {
    $proc_id = to_int(post_value("PROC_ID", "0"));
    if ($proc_id <= 0) {
        echo json_encode(array("success" => false, "message" => "PROC_ID kosong."));
        exit;
    }

    $cekMag = sqlsrv_query($conn, "SELECT COUNT(1) AS CNT FROM dbo.MAG WHERE PROC_ID = ?", array($proc_id));
    if ($cekMag !== false) {
        $cm = sqlsrv_fetch_array($cekMag, SQLSRV_FETCH_ASSOC);
        if ($cm && to_int($cm["CNT"]) > 0) {
            echo json_encode(array("success" => false, "message" => "Tidak bisa hapus. Process masih punya station di MAG."));
            exit;
        }
    }

    $stmt = sqlsrv_query($conn, "DELETE FROM dbo.PROCESS WHERE PROC_ID = ?", array($proc_id));
    if ($stmt === false) {
        echo json_encode(array("success" => false, "message" => sql_error_text()));
        exit;
    }

    echo json_encode(array("success" => true, "message" => "Process berhasil dihapus."));
    exit;
}

/* ==========================================================
   AJAX: SAVE STATION / MAG
========================================================== */
if ($action == "save_station") {
    $mag_id = to_int(post_value("MAG_ID", "0"));
    $proc_id = to_int(post_value("PROC_ID", "0"));
    $mag_station = post_value("MAG_STATION", "");
    $mag_loc = post_value("MAG_LOC", "");

    if ($proc_id <= 0) {
        echo json_encode(array("success" => false, "message" => "PROC_ID kosong. Pilih / simpan process dulu."));
        exit;
    }
    if ($mag_station == "") {
        echo json_encode(array("success" => false, "message" => "STATION belum diisi."));
        exit;
    }

    if ($mag_id > 0) {
        $sql = "UPDATE dbo.MAG SET MAG_STATION = ?, MAG_LOC = ?, PROC_ID = ? WHERE MAG_ID = ?";
        $params = array($mag_station, $mag_loc, $proc_id, $mag_id);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            echo json_encode(array("success" => false, "message" => sql_error_text()));
            exit;
        }
        echo json_encode(array("success" => true, "message" => "Station berhasil diupdate.", "MAG_ID" => $mag_id));
        exit;
    }

    $sql = "INSERT INTO dbo.MAG (MAG_STATION, MAG_LOC, PROC_ID) OUTPUT INSERTED.MAG_ID VALUES (?, ?, ?)";
    $params = array($mag_station, $mag_loc, $proc_id);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo json_encode(array("success" => false, "message" => sql_error_text()));
        exit;
    }

    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$r || !isset($r["MAG_ID"])) {
        echo json_encode(array("success" => false, "message" => "Insert station berhasil tapi MAG_ID tidak terbaca."));
        exit;
    }

    echo json_encode(array("success" => true, "message" => "Station berhasil ditambah.", "MAG_ID" => to_int($r["MAG_ID"])));
    exit;
}

/* ==========================================================
   AJAX: DELETE STATION / MAG
========================================================== */
if ($action == "delete_station") {
    $mag_id = to_int(post_value("MAG_ID", "0"));
    if ($mag_id <= 0) {
        echo json_encode(array("success" => false, "message" => "MAG_ID kosong."));
        exit;
    }

    $cekMac = sqlsrv_query($conn, "SELECT COUNT(1) AS CNT FROM dbo.MAC WHERE MAG_ID = ?", array($mag_id));
    if ($cekMac !== false) {
        $cm = sqlsrv_fetch_array($cekMac, SQLSRV_FETCH_ASSOC);
        if ($cm && to_int($cm["CNT"]) > 0) {
            echo json_encode(array("success" => false, "message" => "Tidak bisa hapus. Station sudah dipakai di MAC."));
            exit;
        }
    }

    $cekItemProd = sqlsrv_query($conn, "SELECT COUNT(1) AS CNT FROM dbo.ITEM_PROD WHERE MAG_ID = ?", array($mag_id));
    if ($cekItemProd !== false) {
        $ci = sqlsrv_fetch_array($cekItemProd, SQLSRV_FETCH_ASSOC);
        if ($ci && to_int($ci["CNT"]) > 0) {
            echo json_encode(array("success" => false, "message" => "Tidak bisa hapus. Station sudah dipakai di ITEM_PROD."));
            exit;
        }
    }

    $stmt = sqlsrv_query($conn, "DELETE FROM dbo.MAG WHERE MAG_ID = ?", array($mag_id));
    if ($stmt === false) {
        echo json_encode(array("success" => false, "message" => sql_error_text()));
        exit;
    }

    echo json_encode(array("success" => true, "message" => "Station berhasil dihapus."));
    exit;
}
?>