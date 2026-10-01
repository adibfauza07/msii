<?php
// File: bom.php
if (session_id() == "") {
    session_start();
}
error_reporting(0);
ob_start();

require_once __DIR__ . '/config/database.php';

if (!isset($conn) || $conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function get_value($name, $default = "") {
    if (isset($_GET[$name])) {
        return trim((string)$_GET[$name]);
    }
    if (isset($_POST[$name])) {
        return trim((string)$_POST[$name]);
    }
    return $default;
}

function post_value($name, $default = "") {
    if (isset($_POST[$name])) {
        return trim((string)$_POST[$name]);
    }
    return $default;
}

function to_int($value) {
    if ($value === null || $value === "") return 0;
    return intval($value);
}

function to_float($value) {
    if ($value === null || $value === "") return 0;
    return floatval(str_replace(",", "", (string)$value));
}

function json_out($arr) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($arr);
    exit;
}

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

function date_for_sql($value) {
    $value = trim((string)$value);
    if ($value == "") return null;
    $ts = strtotime($value);
    if ($ts === false) return null;
    return date("Y-m-d", $ts);
}

function date_for_input($value) {
    if ($value instanceof DateTime) return $value->format("Y-m-d");
    if ($value == null || $value == "") return "";
    $ts = strtotime((string)$value);
    if ($ts === false) return "";
    return date("Y-m-d", $ts);
}

$action = get_value("action", "");

// =================================================================
// 1. AJAX HANDLER: TAB B.O.M & ITEM PROD
// =================================================================
if ($action == "search_item") {
    $q = get_value("q", "");
    $like = "%" . $q . "%";
    $sql = "SELECT TOP 80 ITEM_ID, ISNULL(ITEM_CODE, '') AS ITEM_CODE, ISNULL(ITEM_NO, '') AS ITEM_NO, 
                   ISNULL(ITEM_NAME, '') AS ITEM_NAME, ISNULL(ITEM_UNIT, '') AS ITEM_UNIT
            FROM dbo.ITEMS
            WHERE (ISNULL(ITEM_CODE, '') LIKE ? OR ISNULL(ITEM_NO, '') LIKE ? OR ISNULL(ITEM_NAME, '') LIKE ?)
              AND (ITEM_INACTIVE IS NULL OR ITEM_INACTIVE = 0)
            ORDER BY ITEM_CODE";
    $stmt = sqlsrv_query($conn, $sql, array($like, $like, $like));
    if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text(), "rows" => array()));
    $rows = array();
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = array(
            "ITEM_ID"   => to_int($r["ITEM_ID"]),
            "ITEM_CODE" => trim((string)$r["ITEM_CODE"]),
            "ITEM_NO"   => trim((string)$r["ITEM_NO"]),
            "ITEM_NAME" => trim((string)$r["ITEM_NAME"]),
            "ITEM_UNIT" => trim((string)$r["ITEM_UNIT"])
        );
    }
    json_out(array("success" => true, "rows" => $rows));
}

if ($action == "search_material") {
    $q = get_value("q", "");
    $like = "%" . $q . "%";
    $sql = "SELECT TOP 80 ITEM_ID, ISNULL(ITEM_CODE, '') AS ITEM_CODE, ISNULL(ITEM_NO, '') AS ITEM_NO, 
                   ISNULL(ITEM_NAME, '') AS ITEM_NAME, ISNULL(ITEM_UNIT, '') AS ITEM_UNIT
            FROM dbo.ITEMS
            WHERE (ISNULL(ITEM_CODE, '') LIKE ? OR ISNULL(ITEM_NAME, '') LIKE ? OR ISNULL(ITEM_NO, '') LIKE ?)
              AND (ITEM_INACTIVE IS NULL OR ITEM_INACTIVE = 0)
            ORDER BY ITEM_CODE";
    $stmt = sqlsrv_query($conn, $sql, array($like, $like, $like));
    if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text(), "rows" => array()));
    $rows = array();
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = array(
            "ITEM_ID"   => to_int($r["ITEM_ID"]),
            "ITEM_CODE" => trim((string)$r["ITEM_CODE"]),
            "ITEM_NO"   => trim((string)$r["ITEM_NO"]),
            "ITEM_NAME" => trim((string)$r["ITEM_NAME"]),
            "ITEM_UNIT" => trim((string)$r["ITEM_UNIT"])
        );
    }
    json_out(array("success" => true, "rows" => $rows));
}

if ($action == "get_item_prod") {
    $item_id = to_int(get_value("item_id", "0"));
    if ($item_id <= 0) json_out(array("success" => false, "message" => "ITEM_ID kosong."));
    $sql = "SELECT TOP 1 IP.ITEM_CYTM, IP.ITEM_CAVT, IP.ITEM_WEIGHT, IP.ITEM_RWEIGHT, IP.ITEM_RCLY,
                   ISNULL(IP.ITEM_UNIT, '') AS ITEM_UNIT, IP.MAG_ID, ISNULL(IP.INACTIVE, 0) AS INACTIVE,
                   IP.ITEM_WO, IP.ITEM_ID, IP.ITEM_ACTIVE_BOM, IP.ITEM_DEFAULT_BOM, IP.TRIAL_DATE,
                   ISNULL(IP.TRIAL_REM, '') AS TRIAL_REM, ISNULL(I.ITEM_CODE, '') AS ITEM_CODE,
                   ISNULL(I.ITEM_NAME, '') AS ITEM_NAME, ISNULL(I.ITEM_NO, '') AS ITEM_NO,
                   ISNULL(M.MAG_STATION, '') AS MAG_STATION, ISNULL(M.MAG_LOC, '') AS MAG_LOC
            FROM dbo.ITEM_PROD IP
            INNER JOIN dbo.ITEMS I ON IP.ITEM_ID = I.ITEM_ID
            LEFT JOIN dbo.MAG M ON IP.MAG_ID = M.MAG_ID
            WHERE IP.ITEM_ID = ?";
    $stmt = sqlsrv_query($conn, $sql, array($item_id));
    if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text()));
    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$r) json_out(array("success" => false, "message" => "Data ITEM_PROD tidak ditemukan."));
    json_out(array(
        "success" => true,
        "row" => array(
            "ITEM_ID"          => to_int($r["ITEM_ID"]),
            "ITEM_CODE"        => trim((string)$r["ITEM_CODE"]),
            "ITEM_NO"          => trim((string)$r["ITEM_NO"]),
            "ITEM_NAME"        => trim((string)$r["ITEM_NAME"]),
            "ITEM_CYTM"        => (float)$r["ITEM_CYTM"],
            "ITEM_CAVT"        => (float)$r["ITEM_CAVT"],
            "ITEM_WEIGHT"      => (float)$r["ITEM_WEIGHT"],
            "ITEM_RWEIGHT"     => (float)$r["ITEM_RWEIGHT"],
            "ITEM_RCLY"        => (float)$r["ITEM_RCLY"],
            "ITEM_UNIT"        => trim((string)$r["ITEM_UNIT"]),
            "MAG_ID"           => to_int($r["MAG_ID"]),
            "TONASE"           => trim((string)$r["MAG_STATION"]),
            "MAG_LOC"          => trim((string)$r["MAG_LOC"]),
            "ITEM_WO"          => to_int($r["ITEM_WO"]),
            "ITEM_ACTIVE_BOM"  => to_int($r["ITEM_ACTIVE_BOM"]),
            "ITEM_DEFAULT_BOM" => to_int($r["ITEM_DEFAULT_BOM"]),
            "TRIAL_DATE"       => date_for_input($r["TRIAL_DATE"]),
            "TRIAL_REM"        => trim((string)$r["TRIAL_REM"]),
            "INACTIVE"         => to_int($r["INACTIVE"])
        )
    ));
}

if ($action == "load_bom") {
    $item_id = to_int(get_value("item_id", "0"));
    if ($item_id <= 0) json_out(array("success" => false, "message" => "ITEM_ID kosong."));
    $sqlMaster = "SELECT BOM_ID, PART_ID, ISNULL(BOM_NO, 0) AS BOM_NO, ISNULL(BOM_REM, '') AS BOM_REM
                  FROM dbo.BOM_MASTER WHERE PART_ID = ? ORDER BY BOM_NO, BOM_ID";
    $stmtMaster = sqlsrv_query($conn, $sqlMaster, array($item_id));
    if ($stmtMaster === false) json_out(array("success" => false, "message" => sql_error_text()));
    $masters = array();
    while ($m = sqlsrv_fetch_array($stmtMaster, SQLSRV_FETCH_ASSOC)) {
        $masters[] = array(
            "BOM_ID"  => to_int($m["BOM_ID"]),
            "PART_ID" => to_int($m["PART_ID"]),
            "BOM_NO"  => to_int($m["BOM_NO"]),
            "BOM_REM" => trim((string)$m["BOM_REM"])
        );
    }
    $bom_id = to_int(get_value("bom_id", "0"));
    if ($bom_id <= 0 && count($masters) > 0) {
        $bom_id = to_int($masters[0]["BOM_ID"]);
    }
    $details = array();
    if ($bom_id > 0) {
        $sqlDetail = "SELECT B.BOM_ID, B.ITEM_ID, ISNULL(B.QTY, 0) AS QTY, ISNULL(B.UNIT, '') AS UNIT,
                             ISNULL(B.PERSEN, 0) AS PERSEN, ISNULL(B.HASIL, 0) AS HASIL,
                             ISNULL(I.ITEM_CODE, '') AS MAT_CODE, ISNULL(I.ITEM_NAME, '') AS MAT_NAME,
                             ISNULL(I.ITEM_NO, '') AS MAT_NO
                      FROM dbo.BOM B
                      LEFT JOIN dbo.ITEMS I ON B.ITEM_ID = I.ITEM_ID
                      WHERE B.BOM_ID = ?
                      ORDER BY I.ITEM_CODE, B.ITEM_ID";
        $stmtDetail = sqlsrv_query($conn, $sqlDetail, array($bom_id));
        if ($stmtDetail) {
            while ($d = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC)) {
                $details[] = array(
                    "BOM_ID"   => to_int($d["BOM_ID"]),
                    "ITEM_ID"  => to_int($d["ITEM_ID"]),
                    "MAT_CODE" => trim((string)$d["MAT_CODE"]),
                    "MAT_NO"   => trim((string)$d["MAT_NO"]),
                    "MAT_NAME" => trim((string)$d["MAT_NAME"]),
                    "PERSEN"   => (float)$d["PERSEN"],
                    "QTY"      => (float)$d["QTY"],
                    "UNIT"     => trim((string)$d["UNIT"]),
                    "HASIL"    => (float)$d["HASIL"]
                );
            }
        }
    }
    json_out(array("success" => true, "masters" => $masters, "details" => $details, "active_bom_id" => $bom_id));
}

if ($action == "save_item_prod") {
    $item_id = to_int(post_value("ITEM_ID", "0"));
    $mag_id = to_int(post_value("MAG_ID", "0"));
    if ($item_id <= 0) json_out(array("success" => false, "message" => "Item belum dipilih."));
    if ($mag_id <= 0) json_out(array("success" => false, "message" => "Tonase / MAG belum dipilih."));

    $item_cytm = to_float(post_value("ITEM_CYTM", "0"));
    $item_cavt = to_float(post_value("ITEM_CAVT", "0"));
    $item_weight = to_float(post_value("ITEM_WEIGHT", "0"));
    $item_rweight = to_float(post_value("ITEM_RWEIGHT", "0"));
    $item_rcly = to_float(post_value("ITEM_RCLY", "0"));
    $item_unit = post_value("ITEM_UNIT", "");
    $item_active_bom = to_int(post_value("ITEM_ACTIVE_BOM", "1"));
    $item_default_bom = to_int(post_value("ITEM_DEFAULT_BOM", "1"));
    $trial_date = date_for_sql(post_value("TRIAL_DATE", ""));
    $trial_rem = post_value("TRIAL_REM", "");
    $inactive = to_int(post_value("INACTIVE", "0"));

    $checkStmt = sqlsrv_query($conn, "SELECT COUNT(1) AS CNT FROM dbo.ITEM_PROD WHERE ITEM_ID = ?", array($item_id));
    $check = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    if (to_int($check["CNT"]) > 0) {
        $sql = "UPDATE dbo.ITEM_PROD SET ITEM_CYTM=?, ITEM_CAVT=?, ITEM_WEIGHT=?, ITEM_RWEIGHT=?, ITEM_RCLY=?,
                       ITEM_UNIT=?, MAG_ID=?, INACTIVE=?, ITEM_ACTIVE_BOM=?, ITEM_DEFAULT_BOM=?, TRIAL_DATE=?, TRIAL_REM=?
                WHERE ITEM_ID=?";
        $params = array($item_cytm, $item_cavt, $item_weight, $item_rweight, $item_rcly, $item_unit, $mag_id, $inactive, $item_active_bom, $item_default_bom, $trial_date, $trial_rem, $item_id);
    } else {
        $qwo = sqlsrv_query($conn, "SELECT ISNULL(MAX(ITEM_WO), 0) + 1 AS NEXT_WO FROM dbo.ITEM_PROD WITH (UPDLOCK, HOLDLOCK)");
        $w = sqlsrv_fetch_array($qwo, SQLSRV_FETCH_ASSOC);
        $next_wo = to_int($w["NEXT_WO"]) > 0 ? to_int($w["NEXT_WO"]) : 1;
        $sql = "INSERT INTO dbo.ITEM_PROD (ITEM_ID, ITEM_CYTM, ITEM_CAVT, ITEM_WEIGHT, ITEM_RWEIGHT, ITEM_RCLY, ITEM_UNIT, MAG_ID, INACTIVE, ITEM_WO, ITEM_ACTIVE_BOM, ITEM_DEFAULT_BOM, TRIAL_DATE, TRIAL_REM)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = array($item_id, $item_cytm, $item_cavt, $item_weight, $item_rweight, $item_rcly, $item_unit, $mag_id, $inactive, $next_wo, $item_active_bom, $item_default_bom, $trial_date, $trial_rem);
    }
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text()));
    json_out(array("success" => true, "message" => "ITEM_PROD berhasil disimpan."));
}

if ($action == "save_bom_master") {
    $bom_id  = to_int(post_value("BOM_ID", "0"));
    $part_id = to_int(post_value("PART_ID", "0"));
    $bom_no  = to_int(post_value("BOM_NO", "1"));
    $bom_rem = post_value("BOM_REM", "");
    if ($part_id <= 0) json_out(array("success" => false, "message" => "PART_ID kosong."));
    if ($bom_no <= 0) $bom_no = 1;

    if ($bom_id > 0) {
        $stmt = sqlsrv_query($conn, "UPDATE dbo.BOM_MASTER SET BOM_NO = ?, BOM_REM = ? WHERE BOM_ID = ?", array($bom_no, $bom_rem, $bom_id));
        if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text()));
        json_out(array("success" => true, "message" => "BOM MASTER berhasil diupdate.", "BOM_ID" => $bom_id));
    } else {
        $stmt = sqlsrv_query($conn, "INSERT INTO dbo.BOM_MASTER (PART_ID, BOM_NO, BOM_REM) VALUES (?, ?, ?); SELECT SCOPE_IDENTITY() AS BOM_ID;", array($part_id, $bom_no, $bom_rem));
        if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text()));
        sqlsrv_next_result($stmt);
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        json_out(array("success" => true, "message" => "BOM MASTER berhasil ditambah.", "BOM_ID" => to_int($r["BOM_ID"])));
    }
}

if ($action == "delete_bom_master") {
    $bom_id = to_int(post_value("BOM_ID", "0"));
    if ($bom_id <= 0) json_out(array("success" => false, "message" => "BOM_ID kosong."));
    sqlsrv_begin_transaction($conn);
    sqlsrv_query($conn, "DELETE FROM dbo.BOM WHERE BOM_ID = ?", array($bom_id));
    $stmt2 = sqlsrv_query($conn, "DELETE FROM dbo.BOM_MASTER WHERE BOM_ID = ?", array($bom_id));
    if ($stmt2 === false) {
        sqlsrv_rollback($conn);
        json_out(array("success" => false, "message" => sql_error_text()));
    }
    sqlsrv_commit($conn);
    json_out(array("success" => true, "message" => "BOM MASTER dan detail berhasil dihapus."));
}

if ($action == "save_bom_detail") {
    $old_bom_id  = to_int(post_value("OLD_BOM_ID", "0"));
    $old_item_id = to_int(post_value("OLD_ITEM_ID", "0"));
    $bom_id  = to_int(post_value("BOM_ID", "0"));
    $item_id = to_int(post_value("ITEM_ID", "0"));
    $qty     = to_float(post_value("QTY", "0"));
    $unit    = post_value("UNIT", "");
    $persen  = to_float(post_value("PERSEN", "0"));
    $hasil   = to_float(post_value("HASIL", "0"));

    if ($bom_id <= 0 || $item_id <= 0) json_out(array("success" => false, "message" => "BOM_ID / Material belum valid."));

    if ($old_bom_id > 0 && $old_item_id > 0) {
        $sql = "UPDATE dbo.BOM SET ITEM_ID = ?, QTY = ?, UNIT = ?, PERSEN = ?, HASIL = ? WHERE BOM_ID = ? AND ITEM_ID = ?";
        $params = array($item_id, $qty, $unit, $persen, $hasil, $old_bom_id, $old_item_id);
    } else {
        $check = sqlsrv_query($conn, "SELECT COUNT(1) AS CNT FROM dbo.BOM WHERE BOM_ID = ? AND ITEM_ID = ?", array($bom_id, $item_id));
        $cr = sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC);
        if (to_int($cr["CNT"]) > 0) json_out(array("success" => false, "message" => "Material ini sudah ada di BOM."));
        $sql = "INSERT INTO dbo.BOM (BOM_ID, ITEM_ID, QTY, UNIT, PERSEN, HASIL) VALUES (?, ?, ?, ?, ?, ?)";
        $params = array($bom_id, $item_id, $qty, $unit, $persen, $hasil);
    }
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text()));
    json_out(array("success" => true, "message" => "BOM detail berhasil disimpan."));
}

if ($action == "delete_bom_detail") {
    $bom_id  = to_int(post_value("BOM_ID", "0"));
    $item_id = to_int(post_value("ITEM_ID", "0"));
    $stmt = sqlsrv_query($conn, "DELETE FROM dbo.BOM WHERE BOM_ID = ? AND ITEM_ID = ?", array($bom_id, $item_id));
    if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text()));
    json_out(array("success" => true, "message" => "BOM detail berhasil dihapus."));
}

if ($action == "hitung_bom") {
    $bom_id = to_int(post_value("BOM_ID", "0"));
    $stmt = sqlsrv_query($conn, "EXEC dbo.SP_GET_BOM_DETAIL ?", array($bom_id));
    if ($stmt) {
        do { while (sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {} } while (sqlsrv_next_result($stmt));
    }
    $sqlDetail = "SELECT B.BOM_ID, B.ITEM_ID, ISNULL(B.QTY, 0) AS QTY, ISNULL(B.UNIT, '') AS UNIT,
                         ISNULL(B.PERSEN, 0) AS PERSEN, ISNULL(B.HASIL, 0) AS HASIL,
                         ISNULL(I.ITEM_CODE, '') AS MAT_CODE, ISNULL(I.ITEM_NAME, '') AS MAT_NAME,
                         ISNULL(I.ITEM_NO, '') AS MAT_NO
                  FROM dbo.BOM B LEFT JOIN dbo.ITEMS I ON B.ITEM_ID = I.ITEM_ID WHERE B.BOM_ID = ? ORDER BY I.ITEM_CODE";
    $stmtDetail = sqlsrv_query($conn, $sqlDetail, array($bom_id));
    $details = array();
    while ($d = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC)) {
        $details[] = array(
            "BOM_ID"   => to_int($d["BOM_ID"]), "ITEM_ID" => to_int($d["ITEM_ID"]),
            "MAT_CODE" => trim((string)$d["MAT_CODE"]), "MAT_NAME" => trim((string)$d["MAT_NAME"]),
            "PERSEN"   => (float)$d["PERSEN"], "QTY" => (float)$d["QTY"], "UNIT" => trim((string)$d["UNIT"])
        );
    }
    json_out(array("success" => true, "message" => "Hitung BOM berhasil.", "details" => $details));
}

// =================================================================
// 2. AJAX HANDLER: TAB MACHINE
// =================================================================
if ($action == "load_machine") {
    $sql = "SELECT M.MAC_ID, ISNULL(M.MAC_CODE, '') AS MAC_CODE, ISNULL(M.MAC_SERIAL, '') AS MAC_SERIAL,
                   ISNULL(M.MAC_TYPE, '') AS MAC_TYPE, ISNULL(M.MAG_ID, 0) AS MAG_ID,
                   ISNULL(M.MAC_ACTIVE, 0) AS MAC_ACTIVE, ISNULL(M.MAC_NO, '') AS MAC_NO, 
                   ISNULL(G.MAG_STATION, '') AS MAG_STATION, ISNULL(G.MAG_LOC, '') AS MAG_LOC
            FROM dbo.MAC M LEFT JOIN dbo.MAG G ON M.MAG_ID = G.MAG_ID
            ORDER BY M.MAC_CODE, M.MAC_SERIAL";
    $stmt = sqlsrv_query($conn, $sql);
    $rows = array();
    if ($stmt) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = array(
                "MAC_ID" => to_int($r["MAC_ID"]), "MAC_CODE" => trim((string)$r["MAC_CODE"]),
                "MAC_SERIAL" => trim((string)$r["MAC_SERIAL"]), "MAC_TYPE" => trim((string)$r["MAC_TYPE"]),
                "MAG_ID" => to_int($r["MAG_ID"]), "MAC_ACTIVE" => to_int($r["MAC_ACTIVE"]),
                "MAC_NO" => trim((string)$r["MAC_NO"]),
                "MAG_STATION" => trim((string)$r["MAG_STATION"]), "MAG_LOC" => trim((string)$r["MAG_LOC"])
            );
        }
    }
    json_out(array("success" => true, "rows" => $rows));
}

if ($action == "save_machine") {
    $mac_id = to_int(post_value("MAC_ID", "0"));
    $mac_code = post_value("MAC_CODE", "");
    $mac_serial = post_value("MAC_SERIAL", "");
    $mac_type = post_value("MAC_TYPE", "");
    $mag_id = to_int(post_value("MAG_ID", "0"));
    $mac_active = to_int(post_value("MAC_ACTIVE", "0"));
    $mac_no = post_value("MAC_NO", "");

    if ($mac_code == "") json_out(array("success" => false, "message" => "MAC_CODE belum diisi."));
    if ($mag_id <= 0) json_out(array("success" => false, "message" => "STATION / MAG belum dipilih."));

    if ($mac_id > 0) {
        $sql = "UPDATE dbo.MAC SET MAC_CODE=?, MAC_SERIAL=?, MAC_TYPE=?, MAG_ID=?, MAC_ACTIVE=?, MAC_NO=? WHERE MAC_ID=?";
        $stmt = sqlsrv_query($conn, $sql, array($mac_code, $mac_serial, $mac_type, $mag_id, $mac_active, $mac_no, $mac_id));
        if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text()));
        json_out(array("success" => true, "message" => "Machine berhasil diupdate.", "MAC_ID" => $mac_id));
    } else {
        $sql = "INSERT INTO dbo.MAC (MAC_CODE, MAC_SERIAL, MAC_TYPE, MAG_ID, MAC_ACTIVE, MAC_NO) OUTPUT INSERTED.MAC_ID VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = sqlsrv_query($conn, $sql, array($mac_code, $mac_serial, $mac_type, $mag_id, $mac_active, $mac_no));
        if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text()));
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        json_out(array("success" => true, "message" => "Machine berhasil ditambah.", "MAC_ID" => to_int($r["MAC_ID"])));
    }
}

if ($action == "delete_machine") {
    $mac_id = to_int(post_value("MAC_ID", "0"));
    $cekWo = sqlsrv_query($conn, "SELECT COUNT(1) AS CNT FROM dbo.WO WHERE MAC_ID = ?", array($mac_id));
    if ($cekWo && ($cw = sqlsrv_fetch_array($cekWo, SQLSRV_FETCH_ASSOC)) && to_int($cw["CNT"]) > 0) {
        json_out(array("success" => false, "message" => "Tidak bisa hapus. Machine sudah dipakai di WO."));
    }
    $stmt = sqlsrv_query($conn, "DELETE FROM dbo.MAC WHERE MAC_ID = ?", array($mac_id));
    if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text()));
    json_out(array("success" => true, "message" => "Machine berhasil dihapus."));
}

// =================================================================
// 3. AJAX HANDLER: TAB PROSES & STATION
// =================================================================
if ($action == "load_process") {
    $sql = "SELECT PROC_ID, ISNULL(PROC_NAME, '') AS PROC_NAME, ISNULL(PROC_EFFICIENTCY, 0) AS PROC_EFFICIENTCY,
                   ISNULL(PROC_MEASURE, 0) AS PROC_MEASURE, ISNULL(PROC_HOURS, 0) AS PROC_HOURS,
                   ISNULL(PROC_MMDAY, 0) AS PROC_MMDAY, ISNULL(PROC_MMDAY2, 0) AS PROC_MMDAY2, ISNULL(PROC_MMDAY3, 0) AS PROC_MMDAY3
            FROM dbo.PROCESS ORDER BY PROC_NAME";
    $stmt = sqlsrv_query($conn, $sql);
    $rows = array();
    if ($stmt) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = array(
                "PROC_ID" => to_int($r["PROC_ID"]), "PROC_NAME" => trim((string)$r["PROC_NAME"]),
                "PROC_EFFICIENTCY" => (float)$r["PROC_EFFICIENTCY"], "PROC_MEASURE" => (float)$r["PROC_MEASURE"],
                "PROC_HOURS" => (float)$r["PROC_HOURS"], "PROC_MMDAY" => (float)$r["PROC_MMDAY"],
                "PROC_MMDAY2" => (float)$r["PROC_MMDAY2"], "PROC_MMDAY3" => (float)$r["PROC_MMDAY3"]
            );
        }
    }
    json_out(array("success" => true, "rows" => $rows));
}

if ($action == "load_station") {
    $proc_id = to_int(get_value("PROC_ID", "0"));
    $sql = "SELECT MAG_ID, ISNULL(MAG_STATION, '') AS MAG_STATION, ISNULL(MAG_LOC, '') AS MAG_LOC, ISNULL(PROC_ID, 0) AS PROC_ID
            FROM dbo.MAG WHERE PROC_ID = ? ORDER BY MAG_STATION, MAG_LOC";
    $stmt = sqlsrv_query($conn, $sql, array($proc_id));
    $rows = array();
    if ($stmt) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = array(
                "MAG_ID" => to_int($r["MAG_ID"]), "MAG_STATION" => trim((string)$r["MAG_STATION"]),
                "MAG_LOC" => trim((string)$r["MAG_LOC"]), "PROC_ID" => to_int($r["PROC_ID"])
            );
        }
    }
    json_out(array("success" => true, "rows" => $rows));
}

if ($action == "save_process") {
    $proc_id = to_int(post_value("PROC_ID", "0"));
    $proc_name = post_value("PROC_NAME", "");
    $proc_eff = to_float(post_value("PROC_EFFICIENTCY", "0"));
    $proc_measure = to_float(post_value("PROC_MEASURE", "0"));
    $proc_hours = to_float(post_value("PROC_HOURS", "0"));
    $proc_mmday = to_float(post_value("PROC_MMDAY", "0"));
    $proc_mmday2 = to_float(post_value("PROC_MMDAY2", "0"));
    $proc_mmday3 = to_float(post_value("PROC_MMDAY3", "0"));

    if ($proc_name == "") json_out(array("success" => false, "message" => "Nama PROSES belum diisi."));

    if ($proc_id > 0) {
        $sql = "UPDATE dbo.PROCESS SET PROC_NAME=?, PROC_EFFICIENTCY=?, PROC_MEASURE=?, PROC_HOURS=?, PROC_MMDAY=?, PROC_MMDAY2=?, PROC_MMDAY3=? WHERE PROC_ID=?";
        $stmt = sqlsrv_query($conn, $sql, array($proc_name, $proc_eff, $proc_measure, $proc_hours, $proc_mmday, $proc_mmday2, $proc_mmday3, $proc_id));
        if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text()));
        json_out(array("success" => true, "message" => "Process berhasil diupdate.", "PROC_ID" => $proc_id));
    } else {
        $sql = "INSERT INTO dbo.PROCESS (PROC_NAME, PROC_EFFICIENTCY, PROC_MEASURE, PROC_HOURS, PROC_MMDAY, PROC_MMDAY2, PROC_MMDAY3) OUTPUT INSERTED.PROC_ID VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = sqlsrv_query($conn, $sql, array($proc_name, $proc_eff, $proc_measure, $proc_hours, $proc_mmday, $proc_mmday2, $proc_mmday3));
        if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text()));
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        json_out(array("success" => true, "message" => "Process berhasil ditambah.", "PROC_ID" => to_int($r["PROC_ID"])));
    }
}

if ($action == "delete_process") {
    $proc_id = to_int(post_value("PROC_ID", "0"));
    $cekMag = sqlsrv_query($conn, "SELECT COUNT(1) AS CNT FROM dbo.MAG WHERE PROC_ID = ?", array($proc_id));
    if ($cekMag && ($cm = sqlsrv_fetch_array($cekMag, SQLSRV_FETCH_ASSOC)) && to_int($cm["CNT"]) > 0) {
        json_out(array("success" => false, "message" => "Tidak bisa hapus. Process masih punya station di MAG."));
    }
    $stmt = sqlsrv_query($conn, "DELETE FROM dbo.PROCESS WHERE PROC_ID = ?", array($proc_id));
    if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text()));
    json_out(array("success" => true, "message" => "Process berhasil dihapus."));
}

if ($action == "save_station") {
    $mag_id = to_int(post_value("MAG_ID", "0"));
    $proc_id = to_int(post_value("PROC_ID", "0"));
    $mag_station = post_value("MAG_STATION", "");
    $mag_loc = post_value("MAG_LOC", "");

    if ($proc_id <= 0) json_out(array("success" => false, "message" => "Pilih / simpan process dulu."));
    if ($mag_station == "") json_out(array("success" => false, "message" => "STATION belum diisi."));

    if ($mag_id > 0) {
        $stmt = sqlsrv_query($conn, "UPDATE dbo.MAG SET MAG_STATION=?, MAG_LOC=?, PROC_ID=? WHERE MAG_ID=?", array($mag_station, $mag_loc, $proc_id, $mag_id));
        if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text()));
        json_out(array("success" => true, "message" => "Station berhasil diupdate.", "MAG_ID" => $mag_id));
    } else {
        $stmt = sqlsrv_query($conn, "INSERT INTO dbo.MAG (MAG_STATION, MAG_LOC, PROC_ID) OUTPUT INSERTED.MAG_ID VALUES (?, ?, ?)", array($mag_station, $mag_loc, $proc_id));
        if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text()));
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        json_out(array("success" => true, "message" => "Station berhasil ditambah.", "MAG_ID" => to_int($r["MAG_ID"])));
    }
}

if ($action == "delete_station") {
    $mag_id = to_int(post_value("MAG_ID", "0"));
    $cekMac = sqlsrv_query($conn, "SELECT COUNT(1) AS CNT FROM dbo.MAC WHERE MAG_ID = ?", array($mag_id));
    if ($cekMac && ($cm = sqlsrv_fetch_array($cekMac, SQLSRV_FETCH_ASSOC)) && to_int($cm["CNT"]) > 0) {
        json_out(array("success" => false, "message" => "Tidak bisa hapus. Station sudah dipakai di MAC."));
    }
    
    $cekItemProd = sqlsrv_query($conn, "SELECT COUNT(1) AS CNT FROM dbo.ITEM_PROD WHERE MAG_ID = ?", array($mag_id));
    if ($cekItemProd && ($ci = sqlsrv_fetch_array($cekItemProd, SQLSRV_FETCH_ASSOC)) && to_int($ci["CNT"]) > 0) {
        json_out(array("success" => false, "message" => "Tidak bisa hapus. Station sudah dipakai di ITEM_PROD."));
    }
    
    $stmt = sqlsrv_query($conn, "DELETE FROM dbo.MAG WHERE MAG_ID = ?", array($mag_id));
    if ($stmt === false) json_out(array("success" => false, "message" => sql_error_text()));
    json_out(array("success" => true, "message" => "Station berhasil dihapus."));
}

// =================================================================
// 4. LOAD GLOBAL MAG LIST
// =================================================================
$magList = array();
$magStmt = sqlsrv_query($conn, "SELECT MAG_ID, ISNULL(MAG_STATION, '') AS MAG_STATION, ISNULL(MAG_LOC, '') AS MAG_LOC FROM dbo.MAG ORDER BY MAG_STATION, MAG_LOC");
if ($magStmt !== false) {
    while ($m = sqlsrv_fetch_array($magStmt, SQLSRV_FETCH_ASSOC)) {
        $magList[] = array(
            "MAG_ID"      => to_int($m["MAG_ID"]),
            "MAG_STATION" => trim((string)$m["MAG_STATION"]),
            "MAG_LOC"     => trim((string)$m["MAG_LOC"])
        );
    }
}
?>

<style>
    .autocomplete-wrap { position: relative; }
    .suggest-box {
        position: absolute; top: 100%; left: 0; width: 100%; max-height: 230px;
        overflow-y: auto; background: #ffffff; border: 1px solid #ddd;
        z-index: 99999; display: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .suggest-item { padding: 8px; border-bottom: 1px solid #eee; cursor: pointer; color: #333; }
    .suggest-item:hover, .suggest-item.active { background: #3c8dbc; color: #ffffff; }

    #materialSuggestFloat {
        position: absolute; width: 400px; max-height: 260px; overflow-y: auto;
        background: #ffffff; border: 1px solid #333333; z-index: 999999;
        display: none; box-shadow: 2px 2px 8px rgba(0,0,0,0.25); font-size: 12px;
    }
    
    table.grid th { text-align: center; background-color: #f4f4f4; padding: 4px !important; }
    table.grid td { padding: 0 !important; vertical-align: middle !important; }
    table.grid tr.selected td { background-color: #3c8dbc; color: #ffffff; }
    table.grid input, table.grid select { 
        width: 100%; height: 28px; border: none; padding: 2px 6px; 
        background: transparent; color: inherit; box-sizing: border-box; font-size: 12px;
    }
    table.grid tr.selected input { color: #ffffff; }
    table.grid select { background: #fff; color: #333; }
    table.grid input:focus, table.grid select:focus { outline: 2px solid #f39c12; background: transparent; color: inherit; }
</style>

<div id="materialSuggestFloat"></div>

<!-- ================================================================= -->
<!-- TAB NAVIGATION (CLIENT-SIDE BOOTSTRAP) -->
<!-- ================================================================= -->
<div class="nav-tabs-custom" style="box-shadow: none; margin-bottom: 15px;">
    <ul class="nav nav-tabs">
        <li class="active">
            <a href="#tab_bom" data-toggle="tab"><i class="fa fa-cubes text-primary"></i> <b>B.O.M (ITEM PROD)</b></a>
        </li>
        <li>
            <a href="#tab_machine" data-toggle="tab"><i class="fa fa-cogs text-primary"></i> <b>MACHINE</b></a>
        </li>
        <li>
            <a href="#tab_process" data-toggle="tab"><i class="fa fa-retweet text-primary"></i> <b>PROSES & STATION</b></a>
        </li>
    </ul>
</div>

<!-- ================================================================= -->
<!-- ISI TAB CONTENT -->
<!-- ================================================================= -->
<div class="tab-content" style="padding: 0; background: transparent;">

    <!-- TAB 1: B.O.M & ITEM PROD -->
    <div class="tab-pane active" id="tab_bom">
        <div class="row">
            <div class="col-md-9">
                <div class="box box-solid bg-gray-light" style="margin-bottom: 12px;">
                    <div class="box-body" style="padding: 8px 10px;">
                        <div class="pull-left">
                            <button type="button" class="btn btn-default disabled" style="font-weight:bold; color:#333;">
                                <i class="fa fa-cube"></i> MASTER ITEM PROD
                            </button>
                            <span id="statusText" style="margin-left:15px; font-weight:bold; color:#0056b3;">Ready.</span>
                        </div>
                        <div class="pull-right">
                            <div class="btn-group">
                                <button type="button" class="btn btn-success font-weight-bold" onclick="newItemProd()"><i class="fa fa-plus"></i> BARU</button>
                                <button type="button" class="btn btn-primary font-weight-bold" onclick="saveItemProd()"><i class="fa fa-save"></i> SIMPAN PROD</button>
                                <button type="button" class="btn btn-info font-weight-bold" onclick="loadCurrentBOM()"><i class="fa fa-refresh"></i> REFRESH BOM</button>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                    </div>
                </div>

                <!-- FORM ITEM PROD -->
                <div class="box box-primary" style="margin-bottom: 12px;">
                    <div class="box-header with-border">
                        <h3 class="box-title" style="font-size:13px; font-weight:bold;"><i class="fa fa-cube"></i> INFORMASI ITEM PROD</h3>
                    </div>
                    <div class="box-body">
                        <form id="formItemProd" onsubmit="return false;">
                            <input type="hidden" id="ITEM_ID" name="ITEM_ID">
                            <div class="row">
                                <div class="col-md-3 form-group autocomplete-wrap">
                                    <label>ITEM_CODE (Cari)</label>
                                    <input type="text" id="ITEM_CODE" class="form-control input-sm text-primary font-weight-bold" autocomplete="off" onkeyup="itemCodeKeyup(event)" onkeydown="itemCodeKeydown(event)">
                                    <div id="itemSuggest" class="suggest-box"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>ITEM_NAME</label>
                                    <input type="text" id="ITEM_NAME" class="form-control input-sm" readonly style="background:#eee;">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>TONASE (MAG)</label>
                                    <select id="MAG_ID" name="MAG_ID" class="form-control input-sm">
                                        <option value="">-- PILIH --</option>
                                        <?php for ($i = 0; $i < count($magList); $i++) { ?>
                                            <option value="<?php echo h($magList[$i]["MAG_ID"]); ?>">
                                                <?php echo h($magList[$i]["MAG_STATION"] . " " . $magList[$i]["MAG_LOC"]); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-2 form-group">
                                    <label>WO_REFF</label>
                                    <input type="text" id="ITEM_WO" class="form-control input-sm text-center font-weight-bold" readonly style="background:#eee;">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-2 form-group"><label>ITEM_CYTM</label><input type="text" id="ITEM_CYTM" class="form-control input-sm text-right" value="0"></div>
                                <div class="col-md-2 form-group"><label>ITEM_CAVT</label><input type="text" id="ITEM_CAVT" class="form-control input-sm text-right" value="0"></div>
                                <div class="col-md-2 form-group"><label>ITEM_WEIGHT</label><input type="text" id="ITEM_WEIGHT" class="form-control input-sm text-right" value="0"></div>
                                <div class="col-md-2 form-group"><label>ITEM_RWEIGHT</label><input type="text" id="ITEM_RWEIGHT" class="form-control input-sm text-right" value="0"></div>
                                <div class="col-md-2 form-group"><label>ITEM_RCLY</label><input type="text" id="ITEM_RCLY" class="form-control input-sm text-right" value="0"></div>
                                <div class="col-md-2 form-group"><label>ITEM_UNIT</label><input type="text" id="ITEM_UNIT" class="form-control input-sm"></div>
                            </div>

                            <div class="row">
                                <div class="col-md-2 form-group"><label>ACTIVE_BOM</label><input type="text" id="ITEM_ACTIVE_BOM" class="form-control input-sm text-center" value="1"></div>
                                <div class="col-md-2 form-group"><label>DEFAULT_BOM</label><input type="text" id="ITEM_DEFAULT_BOM" class="form-control input-sm text-center" value="1"></div>
                                <div class="col-md-2 form-group"><label>TRIAL_DATE</label><input type="date" id="TRIAL_DATE" class="form-control input-sm"></div>
                                <div class="col-md-4 form-group"><label>TRIAL_REM</label><input type="text" id="TRIAL_REM" class="form-control input-sm"></div>
                                <div class="col-md-2 form-group" style="padding-top:20px;">
                                    <label class="checkbox-inline text-danger font-weight-bold"><input type="checkbox" id="INACTIVE" value="1"> INACTIVE</label>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- BOM DETAIL TABLE -->
                <div class="box box-success">
                    <div class="box-header with-border" style="padding: 8px 10px;">
                        <h3 class="box-title" style="font-size:13px; font-weight:bold;"><i class="fa fa-list"></i> BOM DETAIL</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-default btn-xs" onclick="newBOMDetail()" title="Tambah Material"><i class="fa fa-plus"></i></button>
                            <button type="button" class="btn btn-default btn-xs" onclick="saveBOMDetail()" title="Simpan Baris"><i class="fa fa-check text-green"></i></button>
                            <button type="button" class="btn btn-default btn-xs" onclick="deleteBOMDetail()" title="Hapus Baris"><i class="fa fa-trash text-red"></i></button>
                            <button type="button" class="btn btn-warning btn-xs font-weight-bold" onclick="hitungBOM()" style="margin-left: 8px;"><i class="fa fa-calculator"></i> HITUNG % MB</button>
                        </div>
                    </div>
                    <div class="box-body no-padding" style="height: 240px; overflow-y: auto;">
                        <table class="table table-bordered grid" id="tblBomDetail" style="margin-bottom:0;">
                            <thead>
                                <tr style="position:sticky; top:0; z-index:2;">
                                    <th style="width:22%;">MAT_CODE</th>
                                    <th>MAT_NAME</th>
                                    <th style="width:10%;">PERSEN</th>
                                    <th style="width:14%;">QTY</th>
                                    <th style="width:10%;">UNIT</th>
                                </tr>
                            </thead>
                            <tbody id="bomDetailBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- KOLOM KANAN: BOM MASTER -->
            <div class="col-md-3">
                <div class="box box-solid box-warning">
                    <div class="box-header with-border" style="text-align:center;">
                        <h3 class="box-title" style="font-size: 13px; font-weight:bold;">BOM MASTER</h3>
                    </div>
                    <div class="box-body" style="padding: 10px 5px;">
                        <div style="text-align: center; margin-bottom: 8px;">
                            <div class="btn-group">
                                <button type="button" class="btn btn-default btn-xs" onclick="newBOMMaster()"><i class="fa fa-plus"></i> Tambah</button>
                                <button type="button" class="btn btn-default btn-xs" onclick="saveBOMMaster()"><i class="fa fa-save"></i> Simpan</button>
                                <button type="button" class="btn btn-default btn-xs" onclick="deleteBOMMaster()"><i class="fa fa-trash text-red"></i></button>
                            </div>
                        </div>
                        
                        <div class="form-group" style="padding: 0 5px;">
                            <label style="font-size:11px;">Active BOM ID:</label>
                            <input type="text" id="ACTIVE_BOM_ID" class="form-control input-sm text-center font-weight-bold" readonly style="background:#eee;">
                        </div>

                        <div style="height: 380px; overflow-y: auto;">
                            <table class="table table-bordered grid" id="tblBomMaster" style="font-size: 11px;">
                                <thead>
                                    <tr style="position:sticky; top:0; z-index:2;"><th style="width:35%;">NO</th><th>REMARK</th></tr>
                                </thead>
                                <tbody id="bomMasterBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- /tab_bom -->

    <!-- TAB 2: MACHINE -->
    <div class="tab-pane" id="tab_machine">
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title" style="font-size:14px; font-weight:bold;"><i class="fa fa-cogs"></i> MASTER MACHINE</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-success btn-sm font-weight-bold" onclick="newMachineRow()"><i class="fa fa-plus"></i> Baris Baru</button>
                            <button type="button" class="btn btn-primary btn-sm font-weight-bold" onclick="saveSelectedMachine()"><i class="fa fa-save"></i> Simpan</button>
                            <button type="button" class="btn btn-danger btn-sm font-weight-bold" onclick="deleteSelectedMachine()"><i class="fa fa-trash"></i> Hapus</button>
                            <button type="button" class="btn btn-default btn-sm" onclick="loadMachines()"><i class="fa fa-refresh"></i> Refresh</button>
                        </div>
                    </div>
                    <div class="box-body no-padding" style="height: 480px; overflow-y: auto;">
                        <table class="table table-bordered grid" id="tblMachine" style="margin-bottom:0;">
                            <thead>
                                <tr style="position:sticky; top:0; z-index:2;">
                                    <th style="width:130px;">MAC_CODE</th>
                                    <th style="width:100px;">MAC_NO</th>
                                    <th style="width:150px;">MAC_SERIAL</th>
                                    <th>MAC_TYPE</th>
                                    <th style="width:80px;" class="text-center">ACTIVE</th>
                                    <th style="width:180px;">STATION</th>
                                    <th style="width:100px;">LOC</th>
                                </tr>
                            </thead>
                            <tbody id="machineBody"></tbody>
                        </table>
                    </div>
                    <div class="box-footer">
                        <span id="macStatusText" class="text-primary font-weight-bold">Ready.</span>
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- /tab_machine -->

    <!-- TAB 3: PROSES & STATION -->
    <div class="tab-pane" id="tab_process">
        <div class="row">
            <!-- PROSES GRID (KIRI) -->
            <div class="col-md-8">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title" style="font-size:14px; font-weight:bold;"><i class="fa fa-retweet"></i> DAFTAR PROSES</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-success btn-xs font-weight-bold" onclick="newProcessRow()"><i class="fa fa-plus"></i> Tambah</button>
                            <button type="button" class="btn btn-primary btn-xs font-weight-bold" onclick="saveSelectedProcess()"><i class="fa fa-save"></i> Simpan</button>
                            <button type="button" class="btn btn-danger btn-xs font-weight-bold" onclick="deleteSelectedProcess()"><i class="fa fa-trash"></i> Hapus</button>
                            <button type="button" class="btn btn-default btn-xs" onclick="loadProcess()"><i class="fa fa-refresh"></i></button>
                        </div>
                    </div>
                    <div class="box-body no-padding" style="height: 480px; overflow-y: auto;">
                        <table class="table table-bordered grid" id="tblProcess" style="margin-bottom:0;">
                            <thead>
                                <tr style="position:sticky; top:0; z-index:2;">
                                    <th>PROSES</th>
                                    <th style="width:65px;">EFF</th>
                                    <th style="width:80px;">MEASURE</th>
                                    <th style="width:80px;">HOURS</th>
                                    <th style="width:85px;">MMDAY</th>
                                    <th style="width:85px;">MMDAY2</th>
                                    <th style="width:85px;">MMDAY3</th>
                                </tr>
                            </thead>
                            <tbody id="processBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- STATION BY PROSES GRID (KANAN) -->
            <div class="col-md-4">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title" style="font-size:14px; font-weight:bold;"><i class="fa fa-map-marker"></i> STATION / MAG</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-success btn-xs font-weight-bold" onclick="newStationRow()"><i class="fa fa-plus"></i> Tambah</button>
                            <button type="button" class="btn btn-warning btn-xs font-weight-bold" onclick="saveSelectedStation()"><i class="fa fa-save"></i> Simpan</button>
                            <button type="button" class="btn btn-danger btn-xs font-weight-bold" onclick="deleteSelectedStation()"><i class="fa fa-trash"></i> Hapus</button>
                        </div>
                    </div>
                    <div class="box-body no-padding" style="height: 480px; overflow-y: auto;">
                        <table class="table table-bordered grid" id="tblStation" style="margin-bottom:0;">
                            <thead>
                                <tr style="position:sticky; top:0; z-index:2;">
                                    <th>STATION</th>
                                    <th style="width:80px;">LOC</th>
                                </tr>
                            </thead>
                            <tbody id="stationBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- /tab_process -->

</div> <!-- /tab-content -->

<script>
var magList = <?php echo json_encode($magList); ?>;

function byId(id) { return document.getElementById(id); }
function enc(v) { return encodeURIComponent(v == null ? "" : v); }
function setStatus(msg) { if(byId("statusText")) byId("statusText").innerHTML = msg; }
function intval(v) { var n = parseInt(v, 10); return isNaN(n) ? 0 : n; }
function html(v) {
    return String(v == null ? "" : v).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\"/g, "&quot;");
}

function ajaxGet(url, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "bom.php" + url, true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var res; try { res = JSON.parse(xhr.responseText); } catch(e) { return; }
            callback(res);
        }
    };
    xhr.send(null);
}

function ajaxPost(url, data, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "bom.php" + url, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var res; try { res = JSON.parse(xhr.responseText); } catch(e) { return; }
            callback(res);
        }
    };
    xhr.send(data);
}

// =================================================================
// LOGIKA JAVASCRIPT TAB 1: B.O.M & ITEM PROD
// =================================================================
var itemRows = [], itemActiveIndex = -1, timerItem = null;
var matRows = [], matActiveIndex = -1, timerMat = null, currentMatInput = null;
var selectedBOMMasterRow = null, selectedBOMDetailRow = null;

function newItemProd() {
    byId("ITEM_ID").value = ""; byId("ITEM_CODE").value = ""; byId("ITEM_NAME").value = "";
    byId("MAG_ID").value = ""; byId("ITEM_WO").value = ""; byId("ITEM_CYTM").value = "0";
    byId("ITEM_CAVT").value = "0"; byId("ITEM_WEIGHT").value = "0"; byId("ITEM_RWEIGHT").value = "0";
    byId("ITEM_RCLY").value = "0"; byId("ITEM_UNIT").value = ""; byId("ITEM_ACTIVE_BOM").value = "1";
    byId("ITEM_DEFAULT_BOM").value = "1"; byId("TRIAL_DATE").value = ""; byId("TRIAL_REM").value = "";
    byId("INACTIVE").checked = false; byId("bomMasterBody").innerHTML = ""; byId("bomDetailBody").innerHTML = "";
    byId("ACTIVE_BOM_ID").value = ""; setStatus("Input item baru. Pilih ITEM_CODE.");
}

function itemCodeKeyup(e) {
    e = e || window.event;
    var key = e.keyCode || e.which;
    if (key == 38 || key == 40 || key == 13) return;
    clearTimeout(timerItem);
    timerItem = setTimeout(function () { searchItem(byId("ITEM_CODE").value); }, 250);
}

function itemCodeKeydown(e) {
    e = e || window.event;
    var key = e.keyCode || e.which;
    if (byId("itemSuggest").style.display == "none") return true;
    if (key == 40) { e.preventDefault(); setActiveItem(itemActiveIndex + 1); return false; }
    if (key == 38) { e.preventDefault(); setActiveItem(itemActiveIndex - 1); return false; }
    if (key == 13) { e.preventDefault(); chooseItem(itemActiveIndex < 0 ? 0 : itemActiveIndex); return false; }
    return true;
}

function searchItem(q) {
    if (q == "") { hideItemSuggest(); return; }
    ajaxGet("?action=search_item&q=" + enc(q), function(res) {
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
            div.innerHTML = "<b>" + html(r.ITEM_CODE) + "</b> | " + html(r.ITEM_NO) + "<br>" + html(r.ITEM_NAME);
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
    byId("itemSuggest").style.display = "none";
    byId("itemSuggest").innerHTML = "";
    itemRows = []; itemActiveIndex = -1;
}

function chooseItem(idx) {
    if (idx < 0 || idx >= itemRows.length) return;
    var r = itemRows[idx];
    hideItemSuggest();
    byId("ITEM_ID").value = r.ITEM_ID;
    byId("ITEM_CODE").value = r.ITEM_CODE;
    byId("ITEM_NAME").value = r.ITEM_NAME;
    byId("ITEM_UNIT").value = r.ITEM_UNIT;

    ajaxGet("?action=get_item_prod&item_id=" + enc(r.ITEM_ID), function(res) {
        if (res.success) {
            fillItemProd(res.row);
            loadCurrentBOM();
        } else {
            setStatus("Item belum ada di ITEM_PROD. Isi detail lalu simpan.");
            byId("bomMasterBody").innerHTML = ""; byId("bomDetailBody").innerHTML = ""; byId("ACTIVE_BOM_ID").value = "";
        }
    });
}

function fillItemProd(r) {
    byId("ITEM_ID").value = r.ITEM_ID; byId("ITEM_CODE").value = r.ITEM_CODE; byId("ITEM_NAME").value = r.ITEM_NAME;
    byId("MAG_ID").value = r.MAG_ID; byId("ITEM_WO").value = r.ITEM_WO; byId("ITEM_CYTM").value = r.ITEM_CYTM;
    byId("ITEM_CAVT").value = r.ITEM_CAVT; byId("ITEM_WEIGHT").value = r.ITEM_WEIGHT; byId("ITEM_RWEIGHT").value = r.ITEM_RWEIGHT;
    byId("ITEM_RCLY").value = r.ITEM_RCLY; byId("ITEM_UNIT").value = r.ITEM_UNIT; byId("ITEM_ACTIVE_BOM").value = r.ITEM_ACTIVE_BOM;
    byId("ITEM_DEFAULT_BOM").value = r.ITEM_DEFAULT_BOM; byId("TRIAL_DATE").value = r.TRIAL_DATE; byId("TRIAL_REM").value = r.TRIAL_REM;
    byId("INACTIVE").checked = r.INACTIVE == 1; setStatus("ITEM_PROD loaded.");
}

function saveItemProd() {
    var item_id = byId("ITEM_ID").value;
    if (item_id == "") { alert("Pilih ITEM_CODE dulu."); return; }
    var data = "ITEM_ID=" + enc(item_id) + "&MAG_ID=" + enc(byId("MAG_ID").value) +
        "&ITEM_CYTM=" + enc(byId("ITEM_CYTM").value) + "&ITEM_CAVT=" + enc(byId("ITEM_CAVT").value) +
        "&ITEM_WEIGHT=" + enc(byId("ITEM_WEIGHT").value) + "&ITEM_RWEIGHT=" + enc(byId("ITEM_RWEIGHT").value) +
        "&ITEM_RCLY=" + enc(byId("ITEM_RCLY").value) + "&ITEM_UNIT=" + enc(byId("ITEM_UNIT").value) +
        "&ITEM_ACTIVE_BOM=" + enc(byId("ITEM_ACTIVE_BOM").value) + "&ITEM_DEFAULT_BOM=" + enc(byId("ITEM_DEFAULT_BOM").value) +
        "&TRIAL_DATE=" + enc(byId("TRIAL_DATE").value) + "&TRIAL_REM=" + enc(byId("TRIAL_REM").value) +
        "&INACTIVE=" + (byId("INACTIVE").checked ? "1" : "0");
    ajaxPost("?action=save_item_prod", data, function(res) {
        alert(res.message);
        if (res.success) setStatus(res.message);
    });
}

function loadCurrentBOM() {
    var item_id = byId("ITEM_ID").value;
    if (item_id == "") return;
    ajaxGet("?action=load_bom&item_id=" + enc(item_id), function(res) {
        if (!res.success) return;
        renderBOMMaster(res.masters);
        if (res.active_bom_id) {
            byId("ACTIVE_BOM_ID").value = res.active_bom_id;
            renderBOMDetail(res.details);
        } else {
            byId("ACTIVE_BOM_ID").value = "";
            renderBOMDetail([]);
        }
    });
}

function renderBOMMaster(rows) {
    selectedBOMMasterRow = null;
    var body = byId("bomMasterBody");
    body.innerHTML = "";
    for (var i = 0; i < rows.length; i++) {
        var tr = document.createElement("tr");
        tr.setAttribute("data-bom-id", rows[i].BOM_ID);
        tr.setAttribute("data-part-id", rows[i].PART_ID);
        tr.innerHTML = "<td><input name='BOM_NO' value='" + html(rows[i].BOM_NO) + "'></td><td><input name='BOM_REM' value='" + html(rows[i].BOM_REM) + "'></td>";
        tr.onclick = function() { selectBOMMaster(this); };
        body.appendChild(tr);
    }
    if (body.rows.length > 0) selectBOMMaster(body.rows[0]);
}

function selectBOMMaster(tr) {
    var rows = byId("bomMasterBody").getElementsByTagName("tr");
    for (var i = 0; i < rows.length; i++) rows[i].className = "";
    tr.className = "selected";
    selectedBOMMasterRow = tr;
    var bomId = tr.getAttribute("data-bom-id") || "";
    byId("ACTIVE_BOM_ID").value = bomId;
    if (bomId != "") {
        ajaxGet("?action=load_bom&item_id=" + enc(byId("ITEM_ID").value) + "&bom_id=" + enc(bomId), function(res) {
            if (res.success) renderBOMDetail(res.details);
        });
    }
}

function newBOMMaster() {
    var item_id = byId("ITEM_ID").value;
    if (item_id == "") { alert("Pilih ITEM_CODE dulu."); return; }
    var body = byId("bomMasterBody");
    var tr = document.createElement("tr");
    tr.setAttribute("data-bom-id", ""); tr.setAttribute("data-part-id", item_id);
    tr.innerHTML = "<td><input name='BOM_NO' value='1'></td><td><input name='BOM_REM' value='Default'></td>";
    tr.onclick = function() { selectBOMMaster(this); };
    body.appendChild(tr);
    selectBOMMaster(tr);
}

function saveBOMMaster() {
    if (!selectedBOMMasterRow) { alert("Pilih BOM MASTER dulu."); return; }
    var bomId = selectedBOMMasterRow.getAttribute("data-bom-id") || "";
    var partId = byId("ITEM_ID").value;
    var no = selectedBOMMasterRow.querySelector("input[name='BOM_NO']").value;
    var rem = selectedBOMMasterRow.querySelector("input[name='BOM_REM']").value;
    var data = "BOM_ID=" + enc(bomId) + "&PART_ID=" + enc(partId) + "&BOM_NO=" + enc(no) + "&BOM_REM=" + enc(rem);
    ajaxPost("?action=save_bom_master", data, function(res) {
        alert(res.message);
        if (res.success) loadCurrentBOM();
    });
}

function deleteBOMMaster() {
    if (!selectedBOMMasterRow) { alert("Pilih BOM MASTER dulu."); return; }
    var bomId = selectedBOMMasterRow.getAttribute("data-bom-id") || "";
    if (bomId == "") { selectedBOMMasterRow.parentNode.removeChild(selectedBOMMasterRow); return; }
    if (!confirm("Hapus BOM MASTER dan detailnya?")) return;
    ajaxPost("?action=delete_bom_master", "BOM_ID=" + enc(bomId), function(res) {
        alert(res.message);
        if (res.success) loadCurrentBOM();
    });
}

function selectBOMDetail(tr) {
    var rows = byId("bomDetailBody").getElementsByTagName("tr");
    for (var i = 0; i < rows.length; i++) rows[i].className = "";
    tr.className = "selected";
    selectedBOMDetailRow = tr;
}

function renderBOMDetail(rows) {
    selectedBOMDetailRow = null;
    hideMaterialSuggest();
    var body = byId("bomDetailBody");
    body.innerHTML = "";
    for (var i = 0; i < rows.length; i++) addBOMDetailRow(rows[i], false);
}

function addBOMDetailRow(r, selectIt) {
    var body = byId("bomDetailBody");
    var tr = document.createElement("tr");
    tr.setAttribute("data-old-bom-id", r.BOM_ID || "");
    tr.setAttribute("data-old-item-id", r.ITEM_ID || "");
    tr.setAttribute("data-item-id", r.ITEM_ID || "");

    tr.innerHTML =
        "<td><input name='MAT_CODE' value='" + html(r.MAT_CODE || "") + "' autocomplete='off' onkeyup='materialKeyup(event,this)' onkeydown='materialKeydown(event,this)' onfocus='materialInputFocus(this)'></td>" +
        "<td><input name='MAT_NAME' value='" + html(r.MAT_NAME || "") + "' readonly></td>" +
        "<td><input name='PERSEN' value='" + html(r.PERSEN || 0) + "'></td>" +
        "<td><input name='QTY' value='" + html(r.QTY || 0) + "'></td>" +
        "<td><input name='UNIT' value='" + html(r.UNIT || "") + "'></td>";

    var inputs = tr.querySelectorAll("input");
    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].getAttribute("name") != "MAT_CODE") {
            inputs[i].addEventListener("keydown", bomDetailInputKeydown);
        }
    }

    tr.onclick = function() { selectBOMDetail(this); };
    body.appendChild(tr);

    if (selectIt) {
        selectBOMDetail(tr);
        setTimeout(function() {
            var input = tr.querySelector("input[name='MAT_CODE']");
            if (input) { input.focus(); input.select(); }
        }, 50);
    }
}

function newBOMDetail() {
    var bomId = byId("ACTIVE_BOM_ID").value;
    if (!bomId || bomId == "") { alert("Simpan BOM MASTER dulu."); return; }
    addBOMDetailRow({ BOM_ID: bomId, ITEM_ID: "", MAT_CODE: "", MAT_NAME: "", PERSEN: 0, QTY: 0, UNIT: "" }, true);
}

function saveBOMDetail(callback, silent) {
    if (!selectedBOMDetailRow) { if (!silent) alert("Pilih baris BOM DETAIL dulu."); return; }
    var tr = selectedBOMDetailRow;
    var oldBomId = tr.getAttribute("data-old-bom-id") || "";
    var oldItemId = tr.getAttribute("data-old-item-id") || "";
    var bomId = byId("ACTIVE_BOM_ID").value || "";
    var itemId = tr.getAttribute("data-item-id") || "";
    var persen = tr.querySelector("input[name='PERSEN']").value;
    var qty = tr.querySelector("input[name='QTY']").value;
    var unit = tr.querySelector("input[name='UNIT']").value;

    if (!bomId || !itemId) { if (!silent) alert("Data BOM atau Material belum lengkap."); return; }

    var data = "OLD_BOM_ID=" + enc(oldBomId) + "&OLD_ITEM_ID=" + enc(oldItemId) + "&BOM_ID=" + enc(bomId) + 
               "&ITEM_ID=" + enc(itemId) + "&QTY=" + enc(qty) + "&UNIT=" + enc(unit) + "&PERSEN=" + enc(persen) + "&HASIL=0";

    ajaxPost("?action=save_bom_detail", data, function(res) {
        if (!res.success) { if (!silent) alert(res.message); return; }
        tr.setAttribute("data-old-bom-id", bomId);
        tr.setAttribute("data-old-item-id", itemId);
        if (typeof callback == "function") callback();
        else if (!silent) alert(res.message);
    });
}

function deleteBOMDetail() {
    if (!selectedBOMDetailRow) { alert("Pilih baris BOM DETAIL dulu."); return; }
    var tr = selectedBOMDetailRow;
    var oldBomId = tr.getAttribute("data-old-bom-id") || "";
    var oldItemId = tr.getAttribute("data-old-item-id") || "";
    if (!oldBomId || !oldItemId) { tr.parentNode.removeChild(tr); return; }
    if (!confirm("Hapus material ini dari BOM?")) return;
    ajaxPost("?action=delete_bom_detail", "BOM_ID=" + enc(oldBomId) + "&ITEM_ID=" + enc(oldItemId), function(res) {
        alert(res.message);
        if (res.success) loadCurrentBOM();
    });
}

function hitungBOM() {
    var bomId = byId("ACTIVE_BOM_ID").value;
    if (!bomId) { alert("Pilih BOM MASTER dulu."); return; }
    ajaxPost("?action=hitung_bom", "BOM_ID=" + enc(bomId), function(res) {
        alert(res.message);
        if (res.success) renderBOMDetail(res.details);
    });
}

function materialKeyup(e, input) {
    e = e || window.event;
    var key = e.keyCode || e.which;
    if (key == 13 || key == 38 || key == 40 || key == 27) return;
    currentMatInput = input;
    clearTimeout(timerMat);
    var q = input.value;
    if (q == "") { hideMaterialSuggest(); return; }
    timerMat = setTimeout(function() {
        ajaxGet("?action=search_material&q=" + enc(q), function(res) {
            if (res.success) renderMaterialSuggest(res.rows, input);
        });
    }, 250);
}

function materialKeydown(e, input) {
    e = e || window.event;
    var key = e.keyCode || e.which;
    var box = byId("materialSuggestFloat");
    if (box.style.display == "none") {
        if (key == 13) { e.preventDefault(); saveCurrentRow(input); return false; }
        return true;
    }
    if (key == 40) { e.preventDefault(); setActiveMaterial(matActiveIndex + 1); return false; }
    if (key == 38) { e.preventDefault(); setActiveMaterial(matActiveIndex - 1); return false; }
    if (key == 13) {
        e.preventDefault();
        if (matActiveIndex >= 0 && matActiveIndex < matRows.length) chooseMaterial(matActiveIndex);
        else { hideMaterialSuggest(); saveCurrentRow(input); }
        return false;
    }
    if (key == 27) { hideMaterialSuggest(); return false; }
    return true;
}

function materialInputFocus(input) {
    currentMatInput = input;
    var tr = input.closest("tr");
    if (tr) selectBOMDetail(tr);
}

function renderMaterialSuggest(rows, input) {
    matRows = rows || [];
    matActiveIndex = -1;
    var box = byId("materialSuggestFloat");
    box.innerHTML = "";
    if (matRows.length == 0) { box.style.display = "none"; return; }
    var rect = input.getBoundingClientRect();
    box.style.left = rect.left + "px";
    box.style.top = (rect.bottom + window.scrollY) + "px";
    box.style.display = "block";
    for (var i = 0; i < matRows.length; i++) {
        (function(idx) {
            var r = matRows[idx];
            var div = document.createElement("div");
            div.className = "suggest-item";
            div.innerHTML = "<b>" + html(r.ITEM_CODE) + "</b> | " + html(r.ITEM_NO) + "<br>" + html(r.ITEM_NAME);
            div.onmouseover = function() { setActiveMaterial(idx); };
            div.onmousedown = function(e) { if (e.preventDefault) e.preventDefault(); chooseMaterial(idx); };
            box.appendChild(div);
        })(i);
    }
    setActiveMaterial(0);
}

function setActiveMaterial(idx) {
    var box = byId("materialSuggestFloat");
    var items = box.getElementsByClassName("suggest-item");
    if (!items || items.length == 0) return;
    if (idx < 0) idx = items.length - 1;
    if (idx >= items.length) idx = 0;
    for (var i = 0; i < items.length; i++) items[i].className = "suggest-item";
    items[idx].className = "suggest-item active";
    matActiveIndex = idx;
}

function chooseMaterial(idx) {
    if (idx < 0 || idx >= matRows.length) return;
    var r = matRows[idx];
    hideMaterialSuggest();
    if (!currentMatInput) return;
    var tr = currentMatInput.closest("tr");
    if (!tr) return;
    tr.querySelector("input[name='MAT_CODE']").value = r.ITEM_CODE;
    tr.querySelector("input[name='MAT_NAME']").value = r.ITEM_NAME;
    tr.querySelector("input[name='UNIT']").value = r.ITEM_UNIT || "";
    tr.setAttribute("data-item-id", r.ITEM_ID);
    tr.setAttribute("data-old-item-id", "");
    var persenInput = tr.querySelector("input[name='PERSEN']");
    if (persenInput) { persenInput.focus(); persenInput.select(); }
}

function hideMaterialSuggest() {
    byId("materialSuggestFloat").style.display = "none";
    byId("materialSuggestFloat").innerHTML = "";
    matRows = []; matActiveIndex = -1;
}

function bomDetailInputKeydown(e) {
    e = e || window.event;
    var key = e.keyCode || e.which;
    var input = e.target;
    var tr = input.closest("tr");
    if (key == 13) {
        e.preventDefault();
        var name = input.getAttribute("name");
        if (name == "PERSEN") {
            var q = tr.querySelector("input[name='QTY']"); if (q) { q.focus(); q.select(); }
        } else if (name == "QTY") {
            var u = tr.querySelector("input[name='UNIT']"); if (u) { u.focus(); u.select(); }
        } else if (name == "UNIT") {
            saveCurrentRow(input);
        }
    }
}

function saveCurrentRow(input) {
    var tr = input.closest("tr");
    if (!tr) return;
    selectBOMDetail(tr);
    saveBOMDetail(function() {
        var nextTr = tr.nextElementSibling;
        if (nextTr) {
            selectBOMDetail(nextTr);
            var nextInp = nextTr.querySelector("input[name='MAT_CODE']");
            if (nextInp) { nextInp.focus(); nextInp.select(); }
        } else {
            newBOMDetail();
        }
    }, true);
}

// =================================================================
// LOGIKA JAVASCRIPT TAB 2: MACHINE
// =================================================================
var selectedMachineRow = null;

function magOptions(selectedMagId) {
    var s = "<select name='MAG_ID' onchange='syncLoc(this)' class='form-control input-sm'><option value=''>-- PILIH --</option>";
    for (var i = 0; i < magList.length; i++) {
        var m = magList[i];
        var sel = String(m.MAG_ID) == String(selectedMagId) ? " selected" : "";
        s += "<option value='" + html(m.MAG_ID) + "' data-loc='" + html(m.MAG_LOC) + "'" + sel + ">" + html(m.MAG_STATION) + "</option>";
    }
    s += "</select>";
    return s;
}

function syncLoc(sel) {
    var tr = sel.parentNode.parentNode;
    var opt = sel.options[sel.selectedIndex];
    tr.querySelector("input[name='MAG_LOC']").value = opt ? (opt.getAttribute("data-loc") || "") : "";
}

function makeMachineRow(r) {
    var tr = document.createElement("tr");
    tr.setAttribute("data-mac-id", r.MAC_ID || "");
    var activeChecked = intval(r.MAC_ACTIVE) == 1 ? " checked" : "";
    tr.innerHTML =
        "<td><input name='MAC_CODE' value='" + html(r.MAC_CODE || "") + "'></td>" +
        "<td><input name='MAC_NO' value='" + html(r.MAC_NO || "") + "'></td>" +
        "<td><input name='MAC_SERIAL' value='" + html(r.MAC_SERIAL || "") + "'></td>" +
        "<td><input name='MAC_TYPE' value='" + html(r.MAC_TYPE || "") + "'></td>" +
        "<td class='text-center'><input type='checkbox' name='MAC_ACTIVE'" + activeChecked + " style='width:auto;'></td>" +
        "<td>" + magOptions(r.MAG_ID || "") + "</td>" +
        "<td><input name='MAG_LOC' value='" + html(r.MAG_LOC || "") + "' readonly style='background:#eee;'></td>";

    tr.onclick = function () { selectMachineRow(tr); };
    var inputs = tr.getElementsByTagName("input");
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].onfocus = function () { selectMachineRow(tr); };
        inputs[i].onkeydown = function (e) {
            e = e || window.event;
            if (e.keyCode == 13) { e.preventDefault(); saveSelectedMachine(); return false; }
            if (e.keyCode == 40) { e.preventDefault(); moveMachineRow(1, this); return false; }
            if (e.keyCode == 38) { e.preventDefault(); moveMachineRow(-1, this); return false; }
        };
    }
    return tr;
}

function selectMachineRow(tr) {
    var rows = byId("machineBody").getElementsByTagName("tr");
    for (var i = 0; i < rows.length; i++) rows[i].className = "";
    tr.className = "selected";
    selectedMachineRow = tr;
}

function loadMachines() {
    ajaxGet("?action=load_machine", function(res) {
        if (!res.success) return;
        var body = byId("machineBody");
        body.innerHTML = "";
        for (var i = 0; i < res.rows.length; i++) body.appendChild(makeMachineRow(res.rows[i]));
        if (body.rows.length > 0) selectMachineRow(body.rows[0]);
        if (byId("macStatusText")) byId("macStatusText").innerHTML = "Loaded " + res.rows.length + " machines.";
    });
}

function newMachineRow() {
    var body = byId("machineBody");
    var tr = makeMachineRow({ MAC_ID: "", MAC_CODE: "", MAC_NO: "", MAC_SERIAL: "", MAC_TYPE: "", MAC_ACTIVE: 1, MAG_ID: "", MAG_LOC: "" });
    body.insertBefore(tr, body.firstChild);
    selectMachineRow(tr);
    var input = tr.querySelector("input[name='MAC_CODE']");
    if (input) { input.focus(); input.select(); }
}

function saveSelectedMachine() {
    if (!selectedMachineRow) { alert("Pilih baris machine dulu."); return; }
    var tr = selectedMachineRow;
    var macId = tr.getAttribute("data-mac-id") || "";
    var code = tr.querySelector("input[name='MAC_CODE']").value;
    var macNo = tr.querySelector("input[name='MAC_NO']").value;
    var serial = tr.querySelector("input[name='MAC_SERIAL']").value;
    var type = tr.querySelector("input[name='MAC_TYPE']").value;
    var active = tr.querySelector("input[name='MAC_ACTIVE']").checked ? 1 : 0;
    var magId = tr.querySelector("select[name='MAG_ID']").value;
    if (code == "") { alert("MAC_CODE belum diisi."); return; }
    if (magId == "") { alert("STATION belum dipilih."); return; }

    var data = "MAC_ID=" + enc(macId) + "&MAC_CODE=" + enc(code) + "&MAC_NO=" + enc(macNo) + "&MAC_SERIAL=" + enc(serial) + 
               "&MAC_TYPE=" + enc(type) + "&MAG_ID=" + enc(magId) + "&MAC_ACTIVE=" + enc(active);
    ajaxPost("?action=save_machine", data, function(res) {
        alert(res.message);
        if (res.success) loadMachines();
    });
}

function deleteSelectedMachine() {
    if (!selectedMachineRow) { alert("Pilih baris machine dulu."); return; }
    var macId = selectedMachineRow.getAttribute("data-mac-id") || "";
    if (macId == "") { selectedMachineRow.parentNode.removeChild(selectedMachineRow); return; }
    if (!confirm("Hapus machine ini?")) return;
    ajaxPost("?action=delete_machine", "MAC_ID=" + enc(macId), function(res) {
        alert(res.message);
        if (res.success) loadMachines();
    });
}

function moveMachineRow(direction, input) {
    var tr = input.parentNode.parentNode;
    var rows = byId("machineBody").getElementsByTagName("tr");
    var rowIndex = -1;
    for (var i = 0; i < rows.length; i++) { if (rows[i] == tr) { rowIndex = i; break; } }
    if (rowIndex < 0) return;
    var nextIndex = rowIndex + direction;
    if (nextIndex >= 0 && nextIndex < rows.length) {
        selectMachineRow(rows[nextIndex]);
        var nextInput = rows[nextIndex].cells[input.parentNode.cellIndex].getElementsByTagName("input")[0];
        if (nextInput) { nextInput.focus(); nextInput.select(); }
    }
}

// =================================================================
// LOGIKA JAVASCRIPT TAB 3: PROSES & STATION
// =================================================================
var selectedProcessRow = null, selectedStationRow = null;

function makeProcessRow(r) {
    var tr = document.createElement("tr");
    tr.setAttribute("data-proc-id", r.PROC_ID || "");
    tr.innerHTML =
        "<td><input name='PROC_NAME' value='" + html(r.PROC_NAME || "") + "'></td>" +
        "<td><input name='PROC_EFFICIENTCY' value='" + html(r.PROC_EFFICIENTCY || 0) + "' class='text-right'></td>" +
        "<td><input name='PROC_MEASURE' value='" + html(r.PROC_MEASURE || 0) + "' class='text-right'></td>" +
        "<td><input name='PROC_HOURS' value='" + html(r.PROC_HOURS || 0) + "' class='text-right'></td>" +
        "<td><input name='PROC_MMDAY' value='" + html(r.PROC_MMDAY || 0) + "' class='text-right'></td>" +
        "<td><input name='PROC_MMDAY2' value='" + html(r.PROC_MMDAY2 || 0) + "' class='text-right'></td>" +
        "<td><input name='PROC_MMDAY3' value='" + html(r.PROC_MMDAY3 || 0) + "' class='text-right'></td>";

    tr.onclick = function () { selectProcessRow(tr); };
    bindRowInputs(tr, "process");
    return tr;
}

function makeStationRow(r) {
    var tr = document.createElement("tr");
    tr.setAttribute("data-mag-id", r.MAG_ID || "");
    tr.setAttribute("data-proc-id", r.PROC_ID || "");
    tr.innerHTML =
        "<td><input name='MAG_STATION' value='" + html(r.MAG_STATION || "") + "'></td>" +
        "<td><input name='MAG_LOC' value='" + html(r.MAG_LOC || "") + "'></td>";

    tr.onclick = function () { selectStationRow(tr); };
    bindRowInputs(tr, "station");
    return tr;
}

function bindRowInputs(tr, type) {
    var inputs = tr.getElementsByTagName("input");
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].onfocus = function () { if (type == "process") selectProcessRow(tr); else selectStationRow(tr); };
        inputs[i].onkeydown = function (e) {
            e = e || window.event;
            if (e.keyCode == 13) {
                e.preventDefault();
                if (type == "process") saveSelectedProcess(); else saveSelectedStation();
            }
        };
    }
}

function selectProcessRow(tr) {
    var rows = byId("processBody").getElementsByTagName("tr");
    for (var i = 0; i < rows.length; i++) rows[i].className = "";
    tr.className = "selected";
    selectedProcessRow = tr;
    loadStation(tr.getAttribute("data-proc-id") || "");
}

function selectStationRow(tr) {
    var rows = byId("stationBody").getElementsByTagName("tr");
    for (var i = 0; i < rows.length; i++) rows[i].className = "";
    tr.className = "selected";
    selectedStationRow = tr;
}

function loadProcess() {
    ajaxGet("?action=load_process", function(res) {
        if (!res.success) return;
        var body = byId("processBody");
        body.innerHTML = "";
        for (var i = 0; i < res.rows.length; i++) body.appendChild(makeProcessRow(res.rows[i]));
        if (body.rows.length > 0) selectProcessRow(body.rows[0]);
    });
}

function loadStation(procId) {
    selectedStationRow = null;
    var body = byId("stationBody");
    body.innerHTML = "";
    if (!procId) return;
    ajaxGet("?action=load_station&PROC_ID=" + enc(procId), function(res) {
        if (!res.success) return;
        for (var i = 0; i < res.rows.length; i++) body.appendChild(makeStationRow(res.rows[i]));
        if (body.rows.length > 0) selectStationRow(body.rows[0]);
    });
}

function newProcessRow() {
    var body = byId("processBody");
    var tr = makeProcessRow({ PROC_ID: "", PROC_NAME: "", PROC_EFFICIENTCY: 90, PROC_MEASURE: 0, PROC_HOURS: 1, PROC_MMDAY: 25, PROC_MMDAY2: 0, PROC_MMDAY3: 0 });
    body.insertBefore(tr, body.firstChild);
    selectProcessRow(tr);
    tr.querySelector("input[name='PROC_NAME']").focus();
}

function newStationRow() {
    if (!selectedProcessRow) { alert("Pilih process dulu."); return; }
    var procId = selectedProcessRow.getAttribute("data-proc-id") || "";
    if (!procId) { alert("Process baru harus disimpan dulu."); return; }
    var body = byId("stationBody");
    var tr = makeStationRow({ MAG_ID: "", PROC_ID: procId, MAG_STATION: "", MAG_LOC: "IM1" });
    body.insertBefore(tr, body.firstChild);
    selectStationRow(tr);
    tr.querySelector("input[name='MAG_STATION']").focus();
}

function saveSelectedProcess() {
    if (!selectedProcessRow) return;
    var tr = selectedProcessRow;
    var procId = tr.getAttribute("data-proc-id") || "";
    var name = tr.querySelector("input[name='PROC_NAME']").value;
    if (name == "") { alert("PROSES belum diisi."); return; }
    var data = "PROC_ID=" + enc(procId) + "&PROC_NAME=" + enc(name) + 
               "&PROC_EFFICIENTCY=" + enc(tr.querySelector("input[name='PROC_EFFICIENTCY']").value) +
               "&PROC_MEASURE=" + enc(tr.querySelector("input[name='PROC_MEASURE']").value) +
               "&PROC_HOURS=" + enc(tr.querySelector("input[name='PROC_HOURS']").value) +
               "&PROC_MMDAY=" + enc(tr.querySelector("input[name='PROC_MMDAY']").value) +
               "&PROC_MMDAY2=" + enc(tr.querySelector("input[name='PROC_MMDAY2']").value) +
               "&PROC_MMDAY3=" + enc(tr.querySelector("input[name='PROC_MMDAY3']").value);
    ajaxPost("?action=save_process", data, function(res) {
        alert(res.message);
        if (res.success) loadProcess();
    });
}

function saveSelectedStation() {
    if (!selectedProcessRow || !selectedStationRow) return;
    var tr = selectedStationRow;
    var magId = tr.getAttribute("data-mag-id") || "";
    var procId = selectedProcessRow.getAttribute("data-proc-id") || "";
    var station = tr.querySelector("input[name='MAG_STATION']").value;
    var loc = tr.querySelector("input[name='MAG_LOC']").value;
    if (station == "") { alert("STATION belum diisi."); return; }
    var data = "MAG_ID=" + enc(magId) + "&PROC_ID=" + enc(procId) + "&MAG_STATION=" + enc(station) + "&MAG_LOC=" + enc(loc);
    ajaxPost("?action=save_station", data, function(res) {
        alert(res.message);
        if (res.success) loadStation(procId);
    });
}

function deleteSelectedProcess() {
    if (!selectedProcessRow) return;
    var procId = selectedProcessRow.getAttribute("data-proc-id") || "";
    if (!procId) { selectedProcessRow.parentNode.removeChild(selectedProcessRow); return; }
    if (!confirm("Hapus process ini?")) return;
    ajaxPost("?action=delete_process", "PROC_ID=" + enc(procId), function(res) {
        alert(res.message);
        if (res.success) loadProcess();
    });
}

function deleteSelectedStation() {
    if (!selectedStationRow) return;
    var magId = selectedStationRow.getAttribute("data-mag-id") || "";
    var procId = selectedProcessRow ? selectedProcessRow.getAttribute("data-proc-id") : "";
    if (!magId) { selectedStationRow.parentNode.removeChild(selectedStationRow); return; }
    if (!confirm("Hapus station ini?")) return;
    ajaxPost("?action=delete_station", "MAG_ID=" + enc(magId), function(res) {
        alert(res.message);
        if (res.success) loadStation(procId);
    });
}

// Menjalankan AJAX Load Saat Halaman Dibuka Pertama Kali
loadMachines();
loadProcess();

// Global close floating suggest box saat klik di luar
document.addEventListener("click", function(e) {
    var box = byId("materialSuggestFloat");
    var itemBox = byId("itemSuggest");
    if (box && box.style.display != "none" && !box.contains(e.target)) hideMaterialSuggest();
    if (itemBox && itemBox.style.display != "none" && !itemBox.contains(e.target) && e.target.id != "ITEM_CODE") hideItemSuggest();
});
</script>