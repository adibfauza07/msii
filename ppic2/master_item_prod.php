<?php
if (session_id() == "") {
    session_start();
}

require_once dirname(__DIR__) . "/config/database_ppic.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function get_value($name, $default) {
    if (isset($_GET[$name])) {
        return trim((string)$_GET[$name]);
    }
    if (isset($_POST[$name])) {
        return trim((string)$_POST[$name]);
    }
    return $default;
}

function post_value($name, $default) {
    if (isset($_POST[$name])) {
        return trim((string)$_POST[$name]);
    }
    return $default;
}

function to_int($value) {
    if ($value === null || $value === "") {
        return 0;
    }
    return intval($value);
}

function to_float($value) {
    if ($value === null || $value === "") {
        return 0;
    }
    return floatval(str_replace(",", "", (string)$value));
}

function json_out($arr) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($arr);
    exit;
}

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

function date_for_sql($value) {
    $value = trim((string)$value);
    if ($value == "") {
        return null;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }
    return date("Y-m-d", $ts);
}

function date_for_input($value) {
    if ($value instanceof DateTime) {
        return $value->format("Y-m-d");
    }
    if ($value == null || $value == "") {
        return "";
    }
    $ts = strtotime((string)$value);
    if ($ts === false) {
        return "";
    }
    return date("Y-m-d", $ts);
}

 $action = get_value("action", "");

/* ==========================================================
   AJAX: SEARCH ITEM PRODUK
========================================================== */
if ($action == "search_item") {
    $q = get_value("q", "");
    $like = "%" . $q . "%";

    $sql = "
        SELECT TOP 80
            ITEM_ID,
            ISNULL(ITEM_CODE, '') AS ITEM_CODE,
            ISNULL(ITEM_NO, '') AS ITEM_NO,
            ISNULL(ITEM_NAME, '') AS ITEM_NAME,
            ISNULL(ITEM_UNIT, '') AS ITEM_UNIT
        FROM dbo.ITEMS
        WHERE
            ISNULL(ITEM_CODE, '') LIKE ?
            OR ISNULL(ITEM_NO, '') LIKE ?
            OR ISNULL(ITEM_NAME, '') LIKE ?
        ORDER BY ITEM_CODE
    ";

    $stmt = sqlsrv_query($conn, $sql, array($like, $like, $like));
    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text(), "rows" => array()));
    }

    $rows = array();
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = array(
            "ITEM_ID" => to_int($r["ITEM_ID"]),
            "ITEM_CODE" => trim((string)$r["ITEM_CODE"]),
            "ITEM_NO" => trim((string)$r["ITEM_NO"]),
            "ITEM_NAME" => trim((string)$r["ITEM_NAME"]),
            "ITEM_UNIT" => trim((string)$r["ITEM_UNIT"])
        );
    }

    json_out(array("success" => true, "rows" => $rows));
}

/* ==========================================================
   AJAX: SEARCH MATERIAL BOM
========================================================== */
if ($action == "search_material") {
    $q = get_value("q", "");
    $like = "%" . $q . "%";

    $sql = "
        SELECT TOP 80
            ITEM_ID,
            ISNULL(ITEM_CODE, '') AS ITEM_CODE,
            ISNULL(ITEM_NO, '') AS ITEM_NO,
            ISNULL(ITEM_NAME, '') AS ITEM_NAME,
            ISNULL(ITEM_UNIT, '') AS ITEM_UNIT
        FROM dbo.ITEMS
        WHERE
            ISNULL(ITEM_CODE, '') LIKE ?
            OR ISNULL(ITEM_NAME, '') LIKE ?
            OR ISNULL(ITEM_NO, '') LIKE ?
        ORDER BY ITEM_CODE
    ";

    $stmt = sqlsrv_query($conn, $sql, array($like, $like, $like));
    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text(), "rows" => array()));
    }

    $rows = array();
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = array(
            "ITEM_ID" => to_int($r["ITEM_ID"]),
            "ITEM_CODE" => trim((string)$r["ITEM_CODE"]),
            "ITEM_NO" => trim((string)$r["ITEM_NO"]),
            "ITEM_NAME" => trim((string)$r["ITEM_NAME"]),
            "ITEM_UNIT" => trim((string)$r["ITEM_UNIT"])
        );
    }

    json_out(array("success" => true, "rows" => $rows));
}

/* ==========================================================
   AJAX: GET ITEM_PROD DETAIL
========================================================== */
if ($action == "get_item_prod") {
    $item_id = to_int(get_value("item_id", "0"));

    if ($item_id <= 0) {
        json_out(array("success" => false, "message" => "ITEM_ID kosong."));
    }

    $sql = "
        SELECT TOP 1
            IP.ITEM_CYTM,
            IP.ITEM_CAVT,
            IP.ITEM_WEIGHT,
            IP.ITEM_RWEIGHT,
            IP.ITEM_RCLY,
            ISNULL(IP.ITEM_UNIT, '') AS ITEM_UNIT,
            IP.MAG_ID,
            ISNULL(IP.INACTIVE, 0) AS INACTIVE,
            IP.ITEM_WO,
            IP.ITEM_ID,
            IP.ITEM_ACTIVE_BOM,
            IP.ITEM_DEFAULT_BOM,
            IP.TRIAL_DATE,
            ISNULL(IP.TRIAL_REM, '') AS TRIAL_REM,
            ISNULL(I.ITEM_CODE, '') AS ITEM_CODE,
            ISNULL(I.ITEM_NAME, '') AS ITEM_NAME,
            ISNULL(I.ITEM_NO, '') AS ITEM_NO,
            ISNULL(M.MAG_STATION, '') AS MAG_STATION,
            ISNULL(M.MAG_LOC, '') AS MAG_LOC
        FROM dbo.ITEM_PROD IP
        INNER JOIN dbo.ITEMS I ON IP.ITEM_ID = I.ITEM_ID
        INNER JOIN dbo.MAG M ON IP.MAG_ID = M.MAG_ID
        WHERE IP.ITEM_ID = ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($item_id));
    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text()));
    }

    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$r) {
        json_out(array("success" => false, "message" => "Data ITEM_PROD tidak ditemukan."));
    }

    json_out(array(
        "success" => true,
        "row" => array(
            "ITEM_ID" => to_int($r["ITEM_ID"]),
            "ITEM_CODE" => trim((string)$r["ITEM_CODE"]),
            "ITEM_NO" => trim((string)$r["ITEM_NO"]),
            "ITEM_NAME" => trim((string)$r["ITEM_NAME"]),
            "ITEM_CYTM" => (float)$r["ITEM_CYTM"],
            "ITEM_CAVT" => (float)$r["ITEM_CAVT"],
            "ITEM_WEIGHT" => (float)$r["ITEM_WEIGHT"],
            "ITEM_RWEIGHT" => (float)$r["ITEM_RWEIGHT"],
            "ITEM_RCLY" => (float)$r["ITEM_RCLY"],
            "ITEM_UNIT" => trim((string)$r["ITEM_UNIT"]),
            "MAG_ID" => to_int($r["MAG_ID"]),
            "TONASE" => trim((string)$r["MAG_STATION"]),
            "MAG_LOC" => trim((string)$r["MAG_LOC"]),
            "ITEM_WO" => to_int($r["ITEM_WO"]),
            "ITEM_ACTIVE_BOM" => to_int($r["ITEM_ACTIVE_BOM"]),
            "ITEM_DEFAULT_BOM" => to_int($r["ITEM_DEFAULT_BOM"]),
            "TRIAL_DATE" => date_for_input($r["TRIAL_DATE"]),
            "TRIAL_REM" => trim((string)$r["TRIAL_REM"]),
            "INACTIVE" => to_int($r["INACTIVE"])
        )
    ));
}

/* ==========================================================
   AJAX: LOAD BOM MASTER + BOM DETAIL
========================================================== */
if ($action == "load_bom") {
    $item_id = to_int(get_value("item_id", "0"));

    if ($item_id <= 0) {
        json_out(array("success" => false, "message" => "ITEM_ID kosong."));
    }

    $sqlMaster = "
        SELECT
            BOM_ID,
            PART_ID,
            ISNULL(BOM_NO, 0) AS BOM_NO,
            ISNULL(BOM_REM, '') AS BOM_REM
        FROM dbo.BOM_MASTER
        WHERE PART_ID = ?
        ORDER BY BOM_NO, BOM_ID
    ";
    $stmtMaster = sqlsrv_query($conn, $sqlMaster, array($item_id));
    if ($stmtMaster === false) {
        json_out(array("success" => false, "message" => sql_error_text()));
    }

    $masters = array();
    while ($m = sqlsrv_fetch_array($stmtMaster, SQLSRV_FETCH_ASSOC)) {
        $masters[] = array(
            "BOM_ID" => to_int($m["BOM_ID"]),
            "PART_ID" => to_int($m["PART_ID"]),
            "BOM_NO" => to_int($m["BOM_NO"]),
            "BOM_REM" => trim((string)$m["BOM_REM"])
        );
    }

    $bom_id = to_int(get_value("bom_id", "0"));
    if ($bom_id <= 0 && count($masters) > 0) {
        $bom_id = to_int($masters[0]["BOM_ID"]);
    }

    $details = array();
    if ($bom_id > 0) {
        $sqlDetail = "
            SELECT
                B.BOM_ID,
                B.ITEM_ID,
                ISNULL(B.QTY, 0) AS QTY,
                ISNULL(B.UNIT, '') AS UNIT,
                ISNULL(B.PERSEN, 0) AS PERSEN,
                ISNULL(B.HASIL, 0) AS HASIL,
                ISNULL(I.ITEM_CODE, '') AS MAT_CODE,
                ISNULL(I.ITEM_NAME, '') AS MAT_NAME,
                ISNULL(I.ITEM_NO, '') AS MAT_NO
            FROM dbo.BOM B
            LEFT JOIN dbo.ITEMS I ON B.ITEM_ID = I.ITEM_ID
            WHERE B.BOM_ID = ?
            ORDER BY I.ITEM_CODE, B.ITEM_ID
        ";
        $stmtDetail = sqlsrv_query($conn, $sqlDetail, array($bom_id));
        if ($stmtDetail === false) {
            json_out(array("success" => false, "message" => sql_error_text()));
        }

        while ($d = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC)) {
            $details[] = array(
                "BOM_ID" => to_int($d["BOM_ID"]),
                "ITEM_ID" => to_int($d["ITEM_ID"]),
                "MAT_CODE" => trim((string)$d["MAT_CODE"]),
                "MAT_NO" => trim((string)$d["MAT_NO"]),
                "MAT_NAME" => trim((string)$d["MAT_NAME"]),
                "PERSEN" => (float)$d["PERSEN"],
                "QTY" => (float)$d["QTY"],
                "UNIT" => trim((string)$d["UNIT"]),
                "HASIL" => (float)$d["HASIL"]
            );
        }
    }

    json_out(array("success" => true, "masters" => $masters, "details" => $details, "active_bom_id" => $bom_id));
}

/* ==========================================================
   POST: SAVE ITEM_PROD
========================================================== */
if ($action == "save_item_prod") {
    $item_id = to_int(post_value("ITEM_ID", "0"));
    $mag_id = to_int(post_value("MAG_ID", "0"));

    if ($item_id <= 0) {
        json_out(array("success" => false, "message" => "Item belum dipilih."));
    }

    if ($mag_id <= 0) {
        json_out(array("success" => false, "message" => "Tonase / MAG belum dipilih."));
    }

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

    if ($item_active_bom <= 0) {
        $item_active_bom = 1;
    }
    if ($item_default_bom <= 0) {
        $item_default_bom = 1;
    }

    $checkSql = "SELECT COUNT(1) AS CNT FROM dbo.ITEM_PROD WHERE ITEM_ID = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, array($item_id));
    if ($checkStmt === false) {
        json_out(array("success" => false, "message" => sql_error_text()));
    }
    $check = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    $exists = to_int($check["CNT"]);

    if ($exists > 0) {
        $sql = "
            UPDATE dbo.ITEM_PROD
            SET
                ITEM_CYTM = ?,
                ITEM_CAVT = ?,
                ITEM_WEIGHT = ?,
                ITEM_RWEIGHT = ?,
                ITEM_RCLY = ?,
                ITEM_UNIT = ?,
                MAG_ID = ?,
                INACTIVE = ?,
                ITEM_ACTIVE_BOM = ?,
                ITEM_DEFAULT_BOM = ?,
                TRIAL_DATE = ?,
                TRIAL_REM = ?
            WHERE ITEM_ID = ?
        ";
        $params = array(
            $item_cytm,
            $item_cavt,
            $item_weight,
            $item_rweight,
            $item_rcly,
            $item_unit,
            $mag_id,
            $inactive,
            $item_active_bom,
            $item_default_bom,
            $trial_date,
            $trial_rem,
            $item_id
        );
    } else {
        $qwo = sqlsrv_query($conn, "SELECT ISNULL(MAX(ITEM_WO), 0) + 1 AS NEXT_WO FROM dbo.ITEM_PROD WITH (UPDLOCK, HOLDLOCK)");
        if ($qwo === false) {
            json_out(array("success" => false, "message" => sql_error_text()));
        }
        $w = sqlsrv_fetch_array($qwo, SQLSRV_FETCH_ASSOC);
        $next_wo = to_int($w["NEXT_WO"]);

        if ($next_wo < 1) {
            $next_wo = 1;
        }
        if ($next_wo > 32767) {
            json_out(array("success" => false, "message" => "ITEM_WO overflow."));
        }

        $sql = "
            INSERT INTO dbo.ITEM_PROD
                (ITEM_ID, ITEM_CYTM, ITEM_CAVT, ITEM_WEIGHT, ITEM_RWEIGHT, ITEM_RCLY, ITEM_UNIT, MAG_ID, INACTIVE, ITEM_WO, ITEM_ACTIVE_BOM, ITEM_DEFAULT_BOM, TRIAL_DATE, TRIAL_REM)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $params = array(
            $item_id,
            $item_cytm,
            $item_cavt,
            $item_weight,
            $item_rweight,
            $item_rcly,
            $item_unit,
            $mag_id,
            $inactive,
            $next_wo,
            $item_active_bom,
            $item_default_bom,
            $trial_date,
            $trial_rem
        );
    }

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text()));
    }

    json_out(array("success" => true, "message" => "ITEM_PROD berhasil disimpan."));
}

/* ==========================================================
   POST: SAVE BOM MASTER
========================================================== */
if ($action == "save_bom_master") {
    $bom_id = to_int(post_value("BOM_ID", "0"));
    $part_id = to_int(post_value("PART_ID", "0"));
    $bom_no = to_int(post_value("BOM_NO", "1"));
    $bom_rem = post_value("BOM_REM", "");

    if ($part_id <= 0) {
        json_out(array("success" => false, "message" => "PART_ID kosong."));
    }
    if ($bom_no <= 0) {
        $bom_no = 1;
    }

    if ($bom_id > 0) {
        $sql = "UPDATE dbo.BOM_MASTER SET BOM_NO = ?, BOM_REM = ? WHERE BOM_ID = ?";
        $params = array($bom_no, $bom_rem, $bom_id);
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            json_out(array("success" => false, "message" => sql_error_text()));
        }
        json_out(array("success" => true, "message" => "BOM MASTER berhasil diupdate.", "BOM_ID" => $bom_id));
    }

    $sql = "
        INSERT INTO dbo.BOM_MASTER (PART_ID, BOM_NO, BOM_REM)
        OUTPUT INSERTED.BOM_ID
        VALUES (?, ?, ?)
    ";
    $stmt = sqlsrv_query($conn, $sql, array($part_id, $bom_no, $bom_rem));
    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text()));
    }
    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    json_out(array("success" => true, "message" => "BOM MASTER berhasil ditambah.", "BOM_ID" => to_int($r["BOM_ID"])));
}

/* ==========================================================
   POST: DELETE BOM MASTER
========================================================== */
if ($action == "delete_bom_master") {
    $bom_id = to_int(post_value("BOM_ID", "0"));
    if ($bom_id <= 0) {
        json_out(array("success" => false, "message" => "BOM_ID kosong."));
    }

    sqlsrv_begin_transaction($conn);

    $stmt1 = sqlsrv_query($conn, "DELETE FROM dbo.BOM WHERE BOM_ID = ?", array($bom_id));
    if ($stmt1 === false) {
        sqlsrv_rollback($conn);
        json_out(array("success" => false, "message" => sql_error_text()));
    }
    $stmt2 = sqlsrv_query($conn, "DELETE FROM dbo.BOM_MASTER WHERE BOM_ID = ?", array($bom_id));
    if ($stmt2 === false) {
        sqlsrv_rollback($conn);
        json_out(array("success" => false, "message" => sql_error_text()));
    }

    sqlsrv_commit($conn);
    json_out(array("success" => true, "message" => "BOM MASTER dan detail berhasil dihapus."));
}

/* ==========================================================
   POST: SAVE BOM DETAIL
========================================================== */
if ($action == "save_bom_detail") {
    $old_bom_id = to_int(post_value("OLD_BOM_ID", "0"));
    $old_item_id = to_int(post_value("OLD_ITEM_ID", "0"));

    $bom_id = to_int(post_value("BOM_ID", "0"));
    $item_id = to_int(post_value("ITEM_ID", "0"));
    $qty = to_float(post_value("QTY", "0"));
    $unit = post_value("UNIT", "");
    $persen = to_float(post_value("PERSEN", "0"));
    $hasil = to_float(post_value("HASIL", "0"));

    if ($bom_id <= 0) {
        json_out(array("success" => false, "message" => "BOM_ID belum dipilih."));
    }
    if ($item_id <= 0) {
        json_out(array("success" => false, "message" => "Material belum dipilih."));
    }

    if ($old_bom_id > 0 && $old_item_id > 0) {
        $sql = "
            UPDATE dbo.BOM
            SET ITEM_ID = ?, QTY = ?, UNIT = ?, PERSEN = ?, HASIL = ?
            WHERE BOM_ID = ? AND ITEM_ID = ?
        ";
        $params = array($item_id, $qty, $unit, $persen, $hasil, $old_bom_id, $old_item_id);
    } else {
        $check = sqlsrv_query($conn, "SELECT COUNT(1) AS CNT FROM dbo.BOM WHERE BOM_ID = ? AND ITEM_ID = ?", array($bom_id, $item_id));
        if ($check === false) {
            json_out(array("success" => false, "message" => sql_error_text()));
        }
        $cr = sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC);
        if (to_int($cr["CNT"]) > 0) {
            json_out(array("success" => false, "message" => "Material ini sudah ada di BOM."));
        }

        $sql = "INSERT INTO dbo.BOM (BOM_ID, ITEM_ID, QTY, UNIT, PERSEN, HASIL) VALUES (?, ?, ?, ?, ?, ?)";
        $params = array($bom_id, $item_id, $qty, $unit, $persen, $hasil);
    }

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text()));
    }

    json_out(array("success" => true, "message" => "BOM detail berhasil disimpan."));
}

/* ==========================================================
   POST: DELETE BOM DETAIL
========================================================== */
if ($action == "delete_bom_detail") {
    $bom_id = to_int(post_value("BOM_ID", "0"));
    $item_id = to_int(post_value("ITEM_ID", "0"));

    if ($bom_id <= 0 || $item_id <= 0) {
        json_out(array("success" => false, "message" => "BOM_ID / ITEM_ID kosong."));
    }

    $stmt = sqlsrv_query($conn, "DELETE FROM dbo.BOM WHERE BOM_ID = ? AND ITEM_ID = ?", array($bom_id, $item_id));
    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text()));
    }

    json_out(array("success" => true, "message" => "BOM detail berhasil dihapus."));
}

/* ==========================================================
   POST: SAVE BOM DETAIL BATCH sebelum HITUNG
========================================================== */
if ($action == "save_bom_detail_batch") {
    $rowsJson = post_value("rows", "");
    $rows = json_decode($rowsJson, true);

    if (!is_array($rows)) {
        json_out(array("success" => false, "message" => "Data BOM detail tidak valid."));
    }

    sqlsrv_begin_transaction($conn);

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];

        $old_bom_id = isset($r["OLD_BOM_ID"]) ? to_int($r["OLD_BOM_ID"]) : 0;
        $old_item_id = isset($r["OLD_ITEM_ID"]) ? to_int($r["OLD_ITEM_ID"]) : 0;
        $bom_id = isset($r["BOM_ID"]) ? to_int($r["BOM_ID"]) : 0;
        $item_id = isset($r["ITEM_ID"]) ? to_int($r["ITEM_ID"]) : 0;
        $qty = isset($r["QTY"]) ? to_float($r["QTY"]) : 0;
        $unit = isset($r["UNIT"]) ? trim((string)$r["UNIT"]) : "";
        $persen = isset($r["PERSEN"]) ? to_float($r["PERSEN"]) : 0;
        $hasil = isset($r["HASIL"]) ? to_float($r["HASIL"]) : 0;

        if ($bom_id <= 0 || $item_id <= 0) {
            continue;
        }

        if ($old_bom_id > 0 && $old_item_id > 0) {
            $sql = "
                UPDATE dbo.BOM
                SET ITEM_ID = ?, QTY = ?, UNIT = ?, PERSEN = ?, HASIL = ?
                WHERE BOM_ID = ? AND ITEM_ID = ?
            ";
            $params = array($item_id, $qty, $unit, $persen, $hasil, $old_bom_id, $old_item_id);
        } else {
            $check = sqlsrv_query($conn, "SELECT COUNT(1) AS CNT FROM dbo.BOM WHERE BOM_ID = ? AND ITEM_ID = ?", array($bom_id, $item_id));
            if ($check === false) {
                sqlsrv_rollback($conn);
                json_out(array("success" => false, "message" => sql_error_text()));
            }
            $cr = sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC);
            if (to_int($cr["CNT"]) > 0) {
                $sql = "UPDATE dbo.BOM SET QTY = ?, UNIT = ?, PERSEN = ?, HASIL = ? WHERE BOM_ID = ? AND ITEM_ID = ?";
                $params = array($qty, $unit, $persen, $hasil, $bom_id, $item_id);
            } else {
                $sql = "INSERT INTO dbo.BOM (BOM_ID, ITEM_ID, QTY, UNIT, PERSEN, HASIL) VALUES (?, ?, ?, ?, ?, ?)";
                $params = array($bom_id, $item_id, $qty, $unit, $persen, $hasil);
            }
        }

        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            sqlsrv_rollback($conn);
            json_out(array("success" => false, "message" => sql_error_text()));
        }
    }

    sqlsrv_commit($conn);
    json_out(array("success" => true, "message" => "BOM detail berhasil disimpan sebelum hitung."));
}

/* ==========================================================
   POST: HITUNG BOM % MB
========================================================== */
if ($action == "hitung_bom") {
    $bom_id = to_int(post_value("BOM_ID", "0"));

    if ($bom_id <= 0) {
        json_out(array("success" => false, "message" => "BOM_ID kosong."));
    }

    $stmt = sqlsrv_query($conn, "EXEC dbo.SP_GET_BOM_DETAIL ?", array($bom_id));
    if ($stmt === false) {
        json_out(array("success" => false, "message" => sql_error_text()));
    }

    do {
        while (sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        }
    } while (sqlsrv_next_result($stmt));

    $sqlDetail = "
        SELECT
            B.BOM_ID,
            B.ITEM_ID,
            ISNULL(B.QTY, 0) AS QTY,
            ISNULL(B.UNIT, '') AS UNIT,
            ISNULL(B.PERSEN, 0) AS PERSEN,
            ISNULL(B.HASIL, 0) AS HASIL,
            ISNULL(I.ITEM_CODE, '') AS MAT_CODE,
            ISNULL(I.ITEM_NAME, '') AS MAT_NAME,
            ISNULL(I.ITEM_NO, '') AS MAT_NO
        FROM dbo.BOM B
        LEFT JOIN dbo.ITEMS I ON B.ITEM_ID = I.ITEM_ID
        WHERE B.BOM_ID = ?
        ORDER BY I.ITEM_CODE, B.ITEM_ID
    ";

    $stmtDetail = sqlsrv_query($conn, $sqlDetail, array($bom_id));
    if ($stmtDetail === false) {
        json_out(array("success" => false, "message" => sql_error_text()));
    }

    $details = array();
    while ($d = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC)) {
        $details[] = array(
            "BOM_ID" => to_int($d["BOM_ID"]),
            "ITEM_ID" => to_int($d["ITEM_ID"]),
            "MAT_CODE" => trim((string)$d["MAT_CODE"]),
            "MAT_NO" => trim((string)$d["MAT_NO"]),
            "MAT_NAME" => trim((string)$d["MAT_NAME"]),
            "PERSEN" => (float)$d["PERSEN"],
            "QTY" => (float)$d["QTY"],
            "UNIT" => trim((string)$d["UNIT"]),
            "HASIL" => (float)$d["HASIL"]
        );
    }

    json_out(array("success" => true, "message" => "Hitung BOM berhasil.", "details" => $details));
}

/* ==========================================================
   LOAD LIST MAG UNTUK DROPDOWN
========================================================== */
 $magList = array();
 $magSql = "
    SELECT MAG_ID, ISNULL(MAG_STATION, '') AS MAG_STATION, ISNULL(MAG_LOC, '') AS MAG_LOC
    FROM dbo.MAG
    ORDER BY MAG_STATION, MAG_LOC
";
 $magStmt = sqlsrv_query($conn, $magSql);
if ($magStmt !== false) {
    while ($m = sqlsrv_fetch_array($magStmt, SQLSRV_FETCH_ASSOC)) {
        $magList[] = array(
            "MAG_ID" => to_int($m["MAG_ID"]),
            "MAG_STATION" => trim((string)$m["MAG_STATION"]),
            "MAG_LOC" => trim((string)$m["MAG_LOC"])
        );
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Master Item Prod</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #d4d0c8;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            color: #000000;
        }

        .page { padding: 10px; }
        .panel {
            background: #a8c8e8;
            border: 1px solid #555555;
            padding: 12px;
            max-width: 1180px;
            min-height: 720px;
            box-sizing: border-box;
        }
        .toolbar { margin-bottom: 10px; }
        button { font-family: Tahoma, Arial, sans-serif; font-size: 12px; cursor: pointer; padding: 4px 10px; }
        .btn-small { padding: 2px 8px; min-width: 28px; }
        .row { display: flex; align-items: center; margin-bottom: 8px; gap: 8px; }
        .label { font-size: 12px; font-weight: normal; color: #000000; }
        .field-block { display: flex; flex-direction: column; }
        input, select, textarea {
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            height: 24px;
            border: 1px solid #777777;
            box-sizing: border-box;
            padding: 2px 4px;
            background: #ffffff;
        }
        textarea { height: 28px; resize: none; }
        input[readonly] { background: #eeeeee; }
        .w-code { width: 140px; }
        .w-name { width: 430px; }
        .w-mag { width: 170px; }
        .w-small { width: 110px; }
        .w-mid { width: 170px; }
        .w-long { width: 900px; }
        .grid-wrap { background: #c7ddeb; border: 1px solid #666666; overflow: auto; }
        .grid-master { width: 410px; height: 92px; }
        .grid-detail { width: 820px; height: 165px; overflow: auto; }
        table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 12px; }
        table.grid th { background: #e0e0e0; border: 1px solid #777777; height: 22px; text-align: left; padding: 2px 4px; white-space: nowrap; }
        table.grid td { background: #ffffff; border: 1px solid #999999; height: 22px; padding: 2px 4px; white-space: nowrap; overflow: hidden; }
        table.grid tr.selected td { background: #316ac5; color: #ffffff; }
        .grid input { width: 100%; height: 20px; border: none; padding: 0 2px; background: transparent; color: inherit; }
        .grid tr.selected input { color: #ffffff; }
        .section-title { margin-top: 12px; margin-bottom: 4px; font-weight: bold; }
        .autocomplete-wrap { position: relative; }
        .status { margin-top: 8px; color: #000080; font-weight: bold; }

        .suggest-box {
            position: absolute;
            top: 24px;
            left: 0;
            width: 520px;
            max-height: 230px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid #333333;
            z-index: 99999;
            display: none;
            color: #000000;
        }
        .suggest-item {
            padding: 5px 6px;
            border-bottom: 1px solid #dddddd;
            cursor: pointer;
            line-height: 16px;
            color: #000000;
            background: #ffffff;
        }
        .suggest-item:hover, .suggest-item.active { background: #316ac5; color: #ffffff; }

        #materialSuggestFloat {
            position: absolute;
            width: 560px;
            max-height: 260px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid #333333;
            z-index: 999999;
            display: none;
            color: #000000;
            box-shadow: 2px 2px 8px rgba(0,0,0,0.25);
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div id="materialSuggestFloat"></div>

<div class="page">
    <div class="panel">
        <div class="toolbar">
            <button type="button" onclick="newItemProd()">NEW</button>
            <button type="button" onclick="saveItemProd()">SAVE ITEM PROD</button>
            <button type="button" onclick="loadCurrentBOM()">REFRESH BOM</button>
            <button type="button" onclick="window.location.href='dashboard_home.php'">CLOSE</button>
        </div>

        <form id="formItemProd" onsubmit="return false;">
            <input type="hidden" id="ITEM_ID" name="ITEM_ID">

            <div class="row">
                <div class="field-block autocomplete-wrap">
                    <span class="label">ITEM_CODE(CARI) :</span>
                    <input type="text" id="ITEM_CODE" class="w-code" autocomplete="off" onkeyup="itemCodeKeyup(event)" onkeydown="itemCodeKeydown(event)">
                    <div id="itemSuggest" class="suggest-box"></div>
                </div>

                <div class="field-block">
                    <span class="label">ITEM_NAME :</span>
                    <input type="text" id="ITEM_NAME" class="w-name" readonly>
                </div>

                <div class="field-block">
                    <span class="label">TONASE</span>
                    <select id="MAG_ID" name="MAG_ID" class="w-mag">
                        <option value="">-- PILIH --</option>
                        <?php for ($i = 0; $i < count($magList); $i++) { ?>
                            <option value="<?php echo h($magList[$i]["MAG_ID"]); ?>">
                                <?php echo h($magList[$i]["MAG_STATION"] . " " . $magList[$i]["MAG_LOC"]); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="field-block">
                    <span class="label">WO_REFF :</span>
                    <input type="text" id="ITEM_WO" class="w-small" readonly>
                </div>
            </div>

            <div class="row">
                <div class="field-block">
                    <span class="label">ITEM_CYTM :</span>
                    <input type="text" id="ITEM_CYTM" name="ITEM_CYTM" class="w-small" value="0">
                </div>
                <div class="field-block">
                    <span class="label">ITEM_ACTIVE_BOM :</span>
                    <input type="text" id="ITEM_ACTIVE_BOM" name="ITEM_ACTIVE_BOM" class="w-small" value="1">
                </div>
            </div>

            <div class="row">
                <div class="field-block">
                    <span class="label">ITEM_CAVT :</span>
                    <input type="text" id="ITEM_CAVT" name="ITEM_CAVT" class="w-small" value="0">
                </div>
                <div class="field-block">
                    <span class="label">ITEM_DEFAULT_BOM :</span>
                    <input type="text" id="ITEM_DEFAULT_BOM" name="ITEM_DEFAULT_BOM" class="w-small" value="1">
                </div>
            </div>

            <div class="row">
                <div class="field-block">
                    <span class="label">ITEM_WEIGHT :</span>
                    <input type="text" id="ITEM_WEIGHT" name="ITEM_WEIGHT" class="w-small" value="0">
                </div>
                <div class="field-block">
                    <span class="label">TRIAL_DATE :</span>
                    <input type="date" id="TRIAL_DATE" name="TRIAL_DATE" class="w-mid">
                </div>
            </div>

            <div class="row">
                <div class="field-block">
                    <span class="label">ITEM_RWEIGHT :</span>
                    <input type="text" id="ITEM_RWEIGHT" name="ITEM_RWEIGHT" class="w-small" value="0">
                </div>
                <div class="field-block">
                    <span class="label">TRIAL_REM :</span>
                    <input type="text" id="TRIAL_REM" name="TRIAL_REM" class="w-long">
                </div>
            </div>

            <div class="row">
                <div class="field-block">
                    <span class="label">ITEM_RCLY :</span>
                    <input type="text" id="ITEM_RCLY" name="ITEM_RCLY" class="w-small" value="0">
                </div>
                <div class="field-block">
                    <span class="label">ITEM_UNIT :</span>
                    <input type="text" id="ITEM_UNIT" name="ITEM_UNIT" class="w-small">
                </div>
                <label><input type="checkbox" id="INACTIVE" name="INACTIVE" value="1"> INACTIVE</label>
            </div>
        </form>

        <div class="section-title">BOM MASTER</div>
        <div class="toolbar">
            <button type="button" class="btn-small" onclick="newBOMMaster()">+</button>
            <button type="button" class="btn-small" onclick="saveBOMMaster()">✓</button>
            <button type="button" class="btn-small" onclick="deleteBOMMaster()">-</button>
        </div>
        <div class="grid-wrap grid-master">
            <table class="grid" id="tblBomMaster">
                <thead>
                    <tr>
                        <th style="width:80px;">BOM_NO</th>
                        <th>BOM_REM</th>
                    </tr>
                </thead>
                <tbody id="bomMasterBody"></tbody>
            </table>
        </div>

        <div class="section-title">BOM DETAIL</div>
        <div class="toolbar">
            <button type="button" class="btn-small" onclick="newBOMDetail()">+</button>
            <button type="button" class="btn-small" onclick="saveBOMDetail()">✓</button>
            <button type="button" class="btn-small" onclick="deleteBOMDetail()">-</button>
            <button type="button" onclick="hitungBOM()" style="margin-left:20px; width:260px;">HITUNG % MB</button>
            <input type="text" id="ACTIVE_BOM_ID" style="width:120px; margin-left:10px;" readonly>
        </div>
        <div class="grid-wrap grid-detail">
            <table class="grid" id="tblBomDetail">
                <thead>
                    <tr>
                        <th style="width:120px;">MAT_CODE</th>
                        <th style="width:240px;">MAT NAME</th>
                        <th style="width:90px;">PERSEN</th>
                        <th style="width:90px;">QTY</th>
                        <th style="width:70px;">UNIT</th>
                    </tr>
                </thead>
                <tbody id="bomDetailBody"></tbody>
            </table>
        </div>

        <div class="status" id="statusText">Ready.</div>
    </div>
</div>

<script>
var itemRows = [];
var itemActiveIndex = -1;
var timerItem = null;
var selectedBOMMasterRow = null;
var selectedBOMDetailRow = null;

var matRows = [];
var matActiveIndex = -1;
var timerMat = null;
var currentMatInput = null;

function enc(v) { return encodeURIComponent(v == null ? "" : v); }
function byId(id) { return document.getElementById(id); }
function setStatus(msg) { byId("statusText").innerHTML = msg; }
function num(v) { var n = parseFloat(String(v).replace(/,/g, "")); return isNaN(n) ? 0 : n; }
function intval(v) { var n = parseInt(v, 10); return isNaN(n) ? 0 : n; }
function html(v) {
    return String(v == null ? "" : v)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function ajaxGet(url, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open("GET", url, true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4) {
            if (xhr.status != 200) { alert("HTTP Error " + xhr.status + "\n" + xhr.responseText); return; }
            var res;
            try { res = JSON.parse(xhr.responseText); }
            catch(e) { alert("Response bukan JSON:\n" + xhr.responseText); return; }
            callback(res);
        }
    };
    xhr.send(null);
}

function ajaxPost(url, data, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", url, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4) {
            if (xhr.status != 200) { alert("HTTP Error " + xhr.status + "\n" + xhr.responseText); return; }
            var res;
            try { res = JSON.parse(xhr.responseText); }
            catch(e) { alert("Response bukan JSON:\n" + xhr.responseText); return; }
            callback(res);
        }
    };
    xhr.send(data);
}

/* ==========================================================
   ITEM PROD FUNCTIONS
========================================================== */
function newItemProd() {
    byId("ITEM_ID").value = "";
    byId("ITEM_CODE").value = "";
    byId("ITEM_NAME").value = "";
    byId("MAG_ID").value = "";
    byId("ITEM_WO").value = "";
    byId("ITEM_CYTM").value = "0";
    byId("ITEM_CAVT").value = "0";
    byId("ITEM_WEIGHT").value = "0";
    byId("ITEM_RWEIGHT").value = "0";
    byId("ITEM_RCLY").value = "0";
    byId("ITEM_UNIT").value = "";
    byId("ITEM_ACTIVE_BOM").value = "1";
    byId("ITEM_DEFAULT_BOM").value = "1";
    byId("TRIAL_DATE").value = "";
    byId("TRIAL_REM").value = "";
    byId("INACTIVE").checked = false;
    byId("bomMasterBody").innerHTML = "";
    byId("bomDetailBody").innerHTML = "";
    byId("ACTIVE_BOM_ID").value = "";
    setStatus("Input item baru. Pilih ITEM_CODE.");
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
        if (!res.success) { alert(res.message); return; }
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
    itemRows = [];
    itemActiveIndex = -1;
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
            setStatus("Item belum ada di ITEM_PROD. Isi detail lalu SAVE.");
            byId("bomMasterBody").innerHTML = "";
            byId("bomDetailBody").innerHTML = "";
            byId("ACTIVE_BOM_ID").value = "";
        }
    });
}

function fillItemProd(r) {
    byId("ITEM_ID").value = r.ITEM_ID;
    byId("ITEM_CODE").value = r.ITEM_CODE;
    byId("ITEM_NAME").value = r.ITEM_NAME;
    byId("MAG_ID").value = r.MAG_ID;
    byId("ITEM_WO").value = r.ITEM_WO;
    byId("ITEM_CYTM").value = r.ITEM_CYTM;
    byId("ITEM_CAVT").value = r.ITEM_CAVT;
    byId("ITEM_WEIGHT").value = r.ITEM_WEIGHT;
    byId("ITEM_RWEIGHT").value = r.ITEM_RWEIGHT;
    byId("ITEM_RCLY").value = r.ITEM_RCLY;
    byId("ITEM_UNIT").value = r.ITEM_UNIT;
    byId("ITEM_ACTIVE_BOM").value = r.ITEM_ACTIVE_BOM;
    byId("ITEM_DEFAULT_BOM").value = r.ITEM_DEFAULT_BOM;
    byId("TRIAL_DATE").value = r.TRIAL_DATE;
    byId("TRIAL_REM").value = r.TRIAL_REM;
    byId("INACTIVE").checked = r.INACTIVE == 1;
    setStatus("ITEM_PROD loaded.");
}

function saveItemProd() {
    var item_id = byId("ITEM_ID").value;
    if (item_id == "") { alert("Pilih ITEM_CODE dulu."); return; }
    var data =
        "ITEM_ID=" + enc(item_id) +
        "&MAG_ID=" + enc(byId("MAG_ID").value) +
        "&ITEM_CYTM=" + enc(byId("ITEM_CYTM").value) +
        "&ITEM_CAVT=" + enc(byId("ITEM_CAVT").value) +
        "&ITEM_WEIGHT=" + enc(byId("ITEM_WEIGHT").value) +
        "&ITEM_RWEIGHT=" + enc(byId("ITEM_RWEIGHT").value) +
        "&ITEM_RCLY=" + enc(byId("ITEM_RCLY").value) +
        "&ITEM_UNIT=" + enc(byId("ITEM_UNIT").value) +
        "&ITEM_ACTIVE_BOM=" + enc(byId("ITEM_ACTIVE_BOM").value) +
        "&ITEM_DEFAULT_BOM=" + enc(byId("ITEM_DEFAULT_BOM").value) +
        "&TRIAL_DATE=" + enc(byId("TRIAL_DATE").value) +
        "&TRIAL_REM=" + enc(byId("TRIAL_REM").value) +
        "&INACTIVE=" + (byId("INACTIVE").checked ? "1" : "0");

    ajaxPost("?action=save_item_prod", data, function(res) {
        if (!res.success) { alert(res.message); return; }
        setStatus(res.message);
        alert(res.message);
    });
}

/* ==========================================================
   BOM MASTER FUNCTIONS
========================================================== */
function loadCurrentBOM() {
    var item_id = byId("ITEM_ID").value;
    if (item_id == "") return;
    ajaxGet("?action=load_bom&item_id=" + enc(item_id), function(res) {
        if (!res.success) { alert(res.message); return; }
        renderBOMMaster(res.masters);
        if (res.active_bom_id && res.active_bom_id != "") {
            byId("ACTIVE_BOM_ID").value = res.active_bom_id;
            renderBOMDetail(res.details);
        } else {
            byId("ACTIVE_BOM_ID").value = "";
            renderBOMDetail([]);
        }
        setStatus("BOM loaded.");
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
        tr.innerHTML =
            "<td><input name='BOM_NO' value='" + html(rows[i].BOM_NO) + "'></td>" +
            "<td><input name='BOM_REM' value='" + html(rows[i].BOM_REM) + "'></td>";
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
    byId("ACTIVE_BOM_ID").value = tr.getAttribute("data-bom-id") || "";
    var bomId = tr.getAttribute("data-bom-id") || "";
    if (bomId != "") loadBOMDetailByBOMID(bomId);
}

function loadBOMDetailByBOMID(bomId) {
    if (!bomId) return;
    var itemId = byId("ITEM_ID").value;
    ajaxGet("?action=load_bom&item_id=" + enc(itemId) + "&bom_id=" + enc(bomId), function(res) {
        if (!res.success) { alert(res.message); return; }
        byId("ACTIVE_BOM_ID").value = bomId;
        renderBOMDetail(res.details);
    });
}

function newBOMMaster() {
    var item_id = byId("ITEM_ID").value;
    if (item_id == "") { alert("Pilih ITEM_CODE dulu."); return; }
    var body = byId("bomMasterBody");
    var tr = document.createElement("tr");
    tr.setAttribute("data-bom-id", "");
    tr.setAttribute("data-part-id", item_id);
    tr.innerHTML = "<td><input name='BOM_NO' value='1'></td><td><input name='BOM_REM' value='Default'></td>";
    tr.onclick = function() { selectBOMMaster(this); };
    body.appendChild(tr);
    selectBOMMaster(tr);
}

function saveBOMMaster(callback, silent) {
    if (!selectedBOMMasterRow) { alert("Pilih BOM MASTER dulu."); return; }
    var bomId = selectedBOMMasterRow.getAttribute("data-bom-id") || "";
    var partId = byId("ITEM_ID").value;
    var no = selectedBOMMasterRow.querySelector("input[name='BOM_NO']").value;
    var rem = selectedBOMMasterRow.querySelector("input[name='BOM_REM']").value;
    if (partId == "") { alert("Pilih ITEM_CODE dulu."); return; }
    if (no == "" || parseInt(no, 10) <= 0) no = "1";

    var data = "BOM_ID=" + enc(bomId) + "&PART_ID=" + enc(partId) + "&BOM_NO=" + enc(no) + "&BOM_REM=" + enc(rem);
    ajaxPost("?action=save_bom_master", data, function(res) {
        if (!res.success) { alert(res.message); return; }
        if (res.BOM_ID) {
            selectedBOMMasterRow.setAttribute("data-bom-id", res.BOM_ID);
            selectedBOMMasterRow.setAttribute("data-part-id", partId);
            byId("ACTIVE_BOM_ID").value = res.BOM_ID;
        }
        if (!silent) alert(res.message);
        if (typeof callback == "function") {
            callback(res.BOM_ID || byId("ACTIVE_BOM_ID").value);
        } else {
            loadCurrentBOM();
        }
    });
}

function deleteBOMMaster() {
    if (!selectedBOMMasterRow) { alert("Pilih BOM MASTER dulu."); return; }
    var bomId = selectedBOMMasterRow.getAttribute("data-bom-id") || "";
    if (bomId == "") { selectedBOMMasterRow.parentNode.removeChild(selectedBOMMasterRow); selectedBOMMasterRow = null; return; }
    if (!confirm("Hapus BOM MASTER dan detailnya?")) return;
    ajaxPost("?action=delete_bom_master", "BOM_ID=" + enc(bomId), function(res) {
        if (!res.success) { alert(res.message); return; }
        alert(res.message);
        loadCurrentBOM();
    });
}

/* ==========================================================
   BOM DETAIL FUNCTIONS (FIXED WITH ENTER TO SAVE)
========================================================== */
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
        var inputName = inputs[i].getAttribute("name");
        if (inputName != "MAT_CODE") {
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
    if (!bomId || bomId == "") {
        alert("Simpan BOM MASTER dulu atau pilih BOM MASTER.");
        return;
    }
    addBOMDetailRow({ BOM_ID: bomId, ITEM_ID: "", MAT_CODE: "", MAT_NAME: "", PERSEN: 0, QTY: 0, UNIT: "" }, true);
    setStatus("Baris baru ditambah. Ketik kode material lalu tekan ENTER.");
}

function saveBOMDetail(callback, silent) {
    if (!selectedBOMDetailRow) {
        if (!silent) alert("Pilih baris BOM DETAIL dulu.");
        return;
    }
    var tr = selectedBOMDetailRow;
    var oldBomId = tr.getAttribute("data-old-bom-id") || "";
    var oldItemId = tr.getAttribute("data-old-item-id") || "";
    var bomId = byId("ACTIVE_BOM_ID").value || "";
    var itemId = tr.getAttribute("data-item-id") || "";

    var persen = tr.querySelector("input[name='PERSEN']").value;
    var qty = tr.querySelector("input[name='QTY']").value;
    var unit = tr.querySelector("input[name='UNIT']").value;

    if (!bomId || bomId == "") { if (!silent) alert("BOM_ID kosong. Simpan BOM MASTER dulu."); return; }
    if (!itemId || itemId == "") { if (!silent) alert("Material belum dipilih."); return; }

    var data = "OLD_BOM_ID=" + enc(oldBomId) + "&OLD_ITEM_ID=" + enc(oldItemId) + "&BOM_ID=" + enc(bomId) + "&ITEM_ID=" + enc(itemId) + "&QTY=" + enc(qty) + "&UNIT=" + enc(unit) + "&PERSEN=" + enc(persen) + "&HASIL=0";

    ajaxPost("?action=save_bom_detail", data, function(res) {
        if (!res.success) { if (!silent) alert(res.message); return; }
        tr.setAttribute("data-old-bom-id", bomId);
        tr.setAttribute("data-old-item-id", itemId);
        if (!silent) setStatus(res.message);
        if (typeof callback == "function") { callback(); } else if (!silent) { loadBOMDetailByBOMID(bomId); }
    });
}

function deleteBOMDetail() {
    if (!selectedBOMDetailRow) { alert("Pilih baris BOM DETAIL dulu."); return; }
    var tr = selectedBOMDetailRow;
    var bomId = byId("ACTIVE_BOM_ID").value || "";
    var oldBomId = tr.getAttribute("data-old-bom-id") || "";
    var oldItemId = tr.getAttribute("data-old-item-id") || "";

    if (!oldBomId || !oldItemId) {
        tr.parentNode.removeChild(tr);
        selectedBOMDetailRow = null;
        setStatus("Baris baru dihapus.");
        return;
    }
    if (!confirm("Hapus material ini dari BOM?")) return;
    ajaxPost("?action=delete_bom_detail", "BOM_ID=" + enc(oldBomId) + "&ITEM_ID=" + enc(oldItemId), function(res) {
        if (!res.success) { alert(res.message); return; }
        alert(res.message);
        loadBOMDetailByBOMID(bomId);
    });
}

function hitungBOM() {
    var bomId = byId("ACTIVE_BOM_ID").value;
    if (!bomId || bomId == "") { alert("Pilih BOM MASTER dulu."); return; }
    if (!confirm("Jalankan HITUNG % MB?")) return;
    saveBOMDetailBatch(function() {
        ajaxPost("?action=hitung_bom", "BOM_ID=" + enc(bomId), function(res) {
            if (!res.success) { alert(res.message); return; }
            renderBOMDetail(res.details);
            setStatus(res.message);
            alert(res.message);
        });
    });
}

function saveBOMDetailBatch(callback) {
    var bomId = byId("ACTIVE_BOM_ID").value;
    var rows = byId("bomDetailBody").getElementsByTagName("tr");
    if (rows.length == 0) { if (typeof callback == "function") callback(); return; }
    var batchData = [];
    for (var i = 0; i < rows.length; i++) {
        var tr = rows[i];
        batchData.push({
            OLD_BOM_ID: tr.getAttribute("data-old-bom-id") || "",
            OLD_ITEM_ID: tr.getAttribute("data-old-item-id") || "",
            BOM_ID: bomId,
            ITEM_ID: tr.getAttribute("data-item-id") || "",
            MAT_CODE: tr.querySelector("input[name='MAT_CODE']").value,
            PERSEN: tr.querySelector("input[name='PERSEN']").value,
            QTY: tr.querySelector("input[name='QTY']").value,
            UNIT: tr.querySelector("input[name='UNIT']").value,
            HASIL: 0
        });
    }
    ajaxPost("?action=save_bom_detail_batch", "rows=" + enc(JSON.stringify(batchData)), function(res) {
        if (!res.success) { alert(res.message); return; }
        setStatus(res.message);
        if (typeof callback == "function") callback();
    });
}

/* ==========================================================
   MATERIAL AUTOCOMPLETE FUNCTIONS
========================================================== */
function materialKeyup(e, input) {
    e = e || window.event;
    var key = e.keyCode || e.which;
    if (key == 13 || key == 38 || key == 40) return;
    if (key == 27) { hideMaterialSuggest(); return; }
    currentMatInput = input;
    clearTimeout(timerMat);
    var q = input.value;
    if (q == "") { hideMaterialSuggest(); return; }
    timerMat = setTimeout(function() { searchMaterial(q, input); }, 250);
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
        if (matActiveIndex >= 0 && matActiveIndex < matRows.length) { chooseMaterial(matActiveIndex); }
        else { hideMaterialSuggest(); saveCurrentRow(input); }
        return false;
    }
    if (key == 27) { e.preventDefault(); hideMaterialSuggest(); return false; }
    if (key == 9) { hideMaterialSuggest(); return true; }
    return true;
}

function materialInputFocus(input) {
    currentMatInput = input;
    var tr = input.closest("tr");
    if (tr) selectBOMDetail(tr);
}

function searchMaterial(q, input) {
    ajaxGet("?action=search_material&q=" + enc(q), function(res) {
        if (!res.success) { alert(res.message); return; }
        renderMaterialSuggest(res.rows, input);
    });
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
            div.innerHTML = "<b>" + html(r.ITEM_CODE) + "</b> | " + html(r.ITEM_NO) + " | " + html(r.ITEM_UNIT) + "<br><span style='color:#666;'>" + html(r.ITEM_NAME) + "</span>";
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
    items[idx].scrollIntoView({ block: "nearest" });
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
    setStatus("Material dipilih: " + r.ITEM_CODE + " - " + r.ITEM_NAME);
}

function hideMaterialSuggest() {
    byId("materialSuggestFloat").style.display = "none";
    byId("materialSuggestFloat").innerHTML = "";
    matRows = [];
    matActiveIndex = -1;
}

/* ==========================================================
   KEYDOWN HANDLER UNTUK KOLOM SELAIN MAT_CODE
========================================================== */
function bomDetailInputKeydown(e) {
    e = e || window.event;
    var key = e.keyCode || e.which;
    var input = e.target;
    var tr = input.closest("tr");
    if (!tr) return true;
    selectBOMDetail(tr);

    if (key == 13) {
        e.preventDefault();
        var inputName = input.getAttribute("name");
        if (inputName == "PERSEN") {
            var qtyInput = tr.querySelector("input[name='QTY']");
            if (qtyInput) { qtyInput.focus(); qtyInput.select(); }
        } else if (inputName == "QTY") {
            var unitInput = tr.querySelector("input[name='UNIT']");
            if (unitInput) { unitInput.focus(); unitInput.select(); }
        } else if (inputName == "UNIT") {
            saveCurrentRow(input);
        }
        return false;
    }

    if (key == 38 && input.getAttribute("name") == "MAT_CODE") {
        var prevTr = tr.previousElementSibling;
        if (prevTr) { selectBOMDetail(prevTr); var prevInput = prevTr.querySelector("input[name='MAT_CODE']"); if (prevInput) { prevInput.focus(); prevInput.select(); } }
        return false;
    }

    if (key == 40 && input.getAttribute("name") == "MAT_CODE") {
        var nextTr = tr.nextElementSibling;
        if (nextTr) { selectBOMDetail(nextTr); var nextInput = nextTr.querySelector("input[name='MAT_CODE']"); if (nextInput) { nextInput.focus(); nextInput.select(); } }
        return false;
    }

    if (key == 27) { hideMaterialSuggest(); input.blur(); return false; }
    return true;
}

/* ==========================================================
   SAVE CURRENT ROW & AUTO NEW ROW
========================================================== */
function saveCurrentRow(input) {
    var tr = input.closest("tr");
    if (!tr) return;
    selectBOMDetail(tr);
    var itemId = tr.getAttribute("data-item-id") || "";
    var bomId = byId("ACTIVE_BOM_ID").value || "";
    if (!bomId) { setStatus("BOM_ID kosong. Simpan BOM MASTER dulu."); return; }
    if (!itemId) { setStatus("Material belum dipilih."); return; }

    saveBOMDetail(function() {
        var nextTr = tr.nextElementSibling;
        if (nextTr) {
            selectBOMDetail(nextTr);
            var nextInput = nextTr.querySelector("input[name='MAT_CODE']");
            if (nextInput) { nextInput.focus(); nextInput.select(); }
        } else {
            newBOMDetail();
        }
    }, true);
}

/* ==========================================================
   GLOBAL CLICK & SCROLL LISTENERS
========================================================== */
document.addEventListener("click", function(e) {
    var box = byId("materialSuggestFloat");
    var itemBox = byId("itemSuggest");
    if (box && box.style.display != "none") {
        if (!box.contains(e.target) && e.target.getAttribute("name") != "MAT_CODE") hideMaterialSuggest();
    }
    if (itemBox && itemBox.style.display != "none") {
        if (!itemBox.contains(e.target) && e.target.id != "ITEM_CODE") hideItemSuggest();
    }
});

window.addEventListener("scroll", function() {
    hideMaterialSuggest();
    hideItemSuggest();
});
</script>
</body>
</html>