<?php
/**
 * File: ac_item.php
 * Backend API Autocomplete & Full CRUD Production
 * Kompatibel: PHP 5.4 + SQL Server 2008 + sqlsrv driver
 */
error_reporting(0);
ob_start();

require_once __DIR__ . '/config/database.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function sendJSON($data) {
    echo json_encode($data);
    exit;
}

$db = null;
if (isset($conn) && $conn !== false) {
    $db = $conn;
} elseif (isset($connection) && $connection !== false) {
    $db = $connection;
} elseif (isset($dbconn) && $dbconn !== false) {
    $db = $dbconn;
}

if (!$db || !function_exists('sqlsrv_query')) {
    sendJSON(array('status' => 'error', 'message' => 'Koneksi database SQL Server tidak tersedia.'));
}

function ac_item_utf8($value) {
    $text = trim((string)$value);
    if ($text === '') return '';
    if (@preg_match('//u', $text)) return $text;
    if (function_exists('iconv')) {
        $conv = @iconv('Windows-1252', 'UTF-8//IGNORE', $text);
        if ($conv !== false) return $conv;
    }
    if (function_exists('utf8_encode')) return @utf8_encode($text);
    return $text;
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// -------------------------------------------------------------
// 1. CARI DAFTAR WORK ORDER (SORT BY WO_NUMBER DESC)
// -------------------------------------------------------------
if ($action === 'get_wo_list' || isset($_GET['item_code'])) {
    $query = isset($_GET['item_code']) ? trim($_GET['item_code']) : (isset($_GET['query']) ? trim($_GET['query']) : '');
    if ($query === '') sendJSON(array());

    $like = '%' . $query . '%';

    $sql = "
        SELECT TOP 30
            W.WO_ID,
            ISNULL(W.WO_NUMBER, '') AS WO_NUMBER,
            ISNULL(W.PROC_ID, 0) AS PROC_ID,
            ISNULL(P.PROC_NAME, '') AS PROC_NAME,
            ISNULL(I.ITEM_CODE, '') AS ITEM_CODE,
            ISNULL(I.ITEM_NAME, '') AS ITEM_NAME,
            ISNULL(M.MAC_CODE, '') AS MAC_CODE,
            ISNULL(W.WO_QTY, 0) AS WO_QTY,
            ISNULL(W.WO_CAP, 0) AS WO_CAP,
            CONVERT(varchar(10), W.WO_START, 120) AS WO_START,
            CONVERT(varchar(10), W.WO_END, 120) AS WO_END
        FROM dbo.WO W
        LEFT JOIN dbo.ITEMS I ON W.ITEM_ID = I.ITEM_ID
        LEFT JOIN dbo.PROCESS P ON W.PROC_ID = P.PROC_ID
        LEFT JOIN dbo.MAC M ON W.MAC_ID = M.MAC_ID
        WHERE (I.ITEM_CODE LIKE ? OR I.ITEM_NAME LIKE ? OR W.WO_NUMBER LIKE ?)
        ORDER BY W.WO_NUMBER DESC
    ";

    $stmt = sqlsrv_query($db, $sql, array($like, $like, $like));
    $data = array();
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = array(
                'WO_ID'     => intval($row['WO_ID']),
                'WO_NUMBER' => ac_item_utf8($row['WO_NUMBER']),
                'PROC_ID'   => intval($row['PROC_ID']),
                'PROC_NAME' => ac_item_utf8($row['PROC_NAME']),
                'ITEM_CODE' => ac_item_utf8($row['ITEM_CODE']),
                'ITEM_NAME' => ac_item_utf8($row['ITEM_NAME']),
                'MAC_CODE'  => ac_item_utf8($row['MAC_CODE']),
                'WO_QTY'    => $row['WO_QTY'] ? number_format((float)$row['WO_QTY'], 0, '.', ',') : '-',
                'WO_CAP'    => $row['WO_CAP'] ? number_format((float)$row['WO_CAP'], 0, '.', ',') : '-',
                'WO_START'  => ac_item_utf8($row['WO_START']),
                'WO_END'    => ac_item_utf8($row['WO_END'])
            );
        }
        sqlsrv_free_stmt($stmt);
    }
    sendJSON($data);
}

// -------------------------------------------------------------
// 2. AMBIL HISTORI PRODUKSI (ADAPTIF & MENDUKUNG DATA LAMA BULAN 8)
// -------------------------------------------------------------
if ($action === 'get_prod_history') {
    $wo_id = isset($_GET['wo_id']) ? intval($_GET['wo_id']) : 0;
    $wo_number = isset($_GET['wo_number']) ? trim($_GET['wo_number']) : '';

    // Deteksi kolom yang benar-benar ada di tabel dbo.PRODUCTION
    $pCols = array();
    $qCols = sqlsrv_query($db, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'PRODUCTION'");
    if ($qCols) {
        while ($rc = sqlsrv_fetch_array($qCols, SQLSRV_FETCH_ASSOC)) {
            $pCols[strtoupper(trim($rc['COLUMN_NAME']))] = true;
        }
        sqlsrv_free_stmt($qCols);
    }

    // Susun kolom SELECT secara aman (mencegah error jika salah satu kolom belum ada)
    $fields = array();
    $fields[] = isset($pCols['PD_ID']) ? "P.PD_ID" : "0 AS PD_ID";
    $fields[] = isset($pCols['WO_ID']) ? "P.WO_ID" : "0 AS WO_ID";
    $fields[] = isset($pCols['PD_LOT']) ? "ISNULL(P.PD_LOT, '') AS PD_LOT" : "'' AS PD_LOT";
    $fields[] = isset($pCols['PD_OK']) ? "ISNULL(P.PD_OK, 0) AS PD_OK" : "0 AS PD_OK";
    $fields[] = isset($pCols['PD_HO']) ? "ISNULL(P.PD_HO, 0) AS PD_HO" : "0 AS PD_HO";
    $fields[] = isset($pCols['PD_NG']) ? "ISNULL(P.PD_NG, 0) AS PD_NG" : "0 AS PD_NG";
    $fields[] = isset($pCols['PD_LOSTHOUR']) ? "ISNULL(P.PD_LOSTHOUR, 0) AS PD_LOSTHOUR" : "0 AS PD_LOSTHOUR";
    $fields[] = isset($pCols['PD_SC']) ? "ISNULL(P.PD_SC, 0) AS PD_SC" : "0 AS PD_SC";
    $fields[] = isset($pCols['PD_REM']) ? "ISNULL(P.PD_REM, '') AS PD_REM" : "'' AS PD_REM";
    $fields[] = isset($pCols['PD_OPR']) ? "ISNULL(P.PD_OPR, '') AS PD_OPR" : "'' AS PD_OPR";
    $fields[] = isset($pCols['PD_PIC_LINE']) ? "ISNULL(P.PD_PIC_LINE, '') AS PD_PIC_LINE" : "'' AS PD_PIC_LINE";
    $fields[] = isset($pCols['PD_INPUT']) ? "CONVERT(varchar(10), P.PD_INPUT, 120) AS PD_INPUT" : "'' AS PD_INPUT";
    $fields[] = isset($pCols['PD_WKH']) ? "ISNULL(P.PD_WKH, 0) AS PD_WKH" : "0 AS PD_WKH";
    $fields[] = isset($pCols['PD_SETUPHOUR']) ? "ISNULL(P.PD_SETUPHOUR, 0) AS PD_SETUPHOUR" : "0 AS PD_SETUPHOUR";
    $fields[] = isset($pCols['PD_CAV']) ? "ISNULL(P.PD_CAV, 0) AS PD_CAV" : "0 AS PD_CAV";
    $fields[] = isset($pCols['PD_WEIGHT_S']) ? "ISNULL(P.PD_WEIGHT_S, 0) AS PD_WEIGHT_S" : "0 AS PD_WEIGHT_S";
    $fields[] = isset($pCols['PD_RUN_S']) ? "ISNULL(P.PD_RUN_S, 0) AS PD_RUN_S" : "0 AS PD_RUN_S";
    $fields[] = isset($pCols['PD_CYTM']) ? "ISNULL(P.PD_CYTM, 0) AS PD_CYTM" : "0 AS PD_CYTM";
    $fields[] = isset($pCols['PD_LOST_REASON']) ? "ISNULL(P.PD_LOST_REASON, '') AS PD_LOST_REASON" : "'' AS PD_LOST_REASON";

    // Susun filter pencarian (mencocokkan WO_ID angka maupun Nomor WO string)
    $whereConditions = array();
    $params = array();

    if ($wo_id > 0) {
        $whereConditions[] = "P.WO_ID = ?";
        $params[] = $wo_id;
    }

    if ($wo_number !== '') {
        $whereConditions[] = "P.WO_ID IN (SELECT WO_ID FROM dbo.WO WHERE LTRIM(RTRIM(WO_NUMBER)) = ?)";
        $params[] = $wo_number;

        if (isset($pCols['WO_NUMBER'])) {
            $whereConditions[] = "LTRIM(RTRIM(P.WO_NUMBER)) = ?";
            $params[] = $wo_number;
        }
        if (isset($pCols['WO_NO'])) {
            $whereConditions[] = "LTRIM(RTRIM(P.WO_NO)) = ?";
            $params[] = $wo_number;
        }
    }

    if (empty($whereConditions)) {
        sendJSON(array());
    }

    $sql = "
        SELECT " . implode(", ", $fields) . "
        FROM dbo.PRODUCTION P
        WHERE (" . implode(" OR ", $whereConditions) . ")
        ORDER BY P.PD_ID ASC
    ";

    $stmt = sqlsrv_query($db, $sql, $params);
    if ($stmt === false) {
        sendJSON(array('status' => 'error', 'message' => 'Gagal membaca data produksi.', 'errors' => sqlsrv_errors()));
    }

    $res = array();
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $r['PD_LOT'] = ac_item_utf8($r['PD_LOT']);
        $r['PD_REM'] = ac_item_utf8($r['PD_REM']);
        $r['PD_OPR'] = ac_item_utf8($r['PD_OPR']);
        $r['PD_PIC_LINE'] = ac_item_utf8($r['PD_PIC_LINE']);
        $r['PD_LOST_REASON'] = ac_item_utf8($r['PD_LOST_REASON']);
        $res[] = $r;
    }
    sqlsrv_free_stmt($stmt);
    sendJSON($res);
}

// -------------------------------------------------------------
// 3. TAMBAH BARIS LOT BARU (CREATE)
// -------------------------------------------------------------
if ($action === 'insert_new') {
    $wo_id = intval($_POST['wo_id']);
    $pd_lot = trim($_POST['pd_lot']);
    $input_date = $_POST['input_date'];

    $sql = "INSERT INTO dbo.PRODUCTION (WO_ID, PD_LOT, PD_OK, PD_HO, PD_NG, PD_REM, PD_INPUT) VALUES (?, ?, 0, 0, 0, '', ?)";
    $stmt = sqlsrv_query($db, $sql, array($wo_id, $pd_lot, $input_date));
    sendJSON(array('status' => $stmt ? 'success' : 'error', 'errors' => sqlsrv_errors()));
}

// -------------------------------------------------------------
// 4. UPDATE DETAIL GRID UTAMA (UPDATE)
// -------------------------------------------------------------
if ($action === 'update_detail') {
    $pd_id = intval($_POST['pd_id']);
    $pd_ok = intval($_POST['pd_ok']);
    $pd_ho = intval($_POST['pd_ho']);
    $pd_ng = intval($_POST['pd_ng']);
    $pd_dt = floatval($_POST['pd_dt']);
    $pd_purging = floatval($_POST['pd_purging']);
    $pd_rem = trim($_POST['pd_rem']);
    $pd_opr = trim($_POST['pd_opr']);
    $pd_pic = trim($_POST['pd_pic']);

    $sql = "UPDATE dbo.PRODUCTION SET PD_OK=?, PD_HO=?, PD_NG=?, PD_LOSTHOUR=?, PD_SC=?, PD_REM=?, PD_OPR=?, PD_PIC_LINE=? WHERE PD_ID=?";
    $stmt = sqlsrv_query($db, $sql, array($pd_ok, $pd_ho, $pd_ng, $pd_dt, $pd_purging, $pd_rem, $pd_opr, $pd_pic, $pd_id));
    sendJSON(array('status' => $stmt ? 'success' : 'error', 'errors' => sqlsrv_errors()));
}

// -------------------------------------------------------------
// 5. UPDATE ACTUAL DATA (UPDATE ACTUAL)
// -------------------------------------------------------------
if ($action === 'update_actual') {
    $pd_id = intval($_POST['pd_id']);
    $pd_wkh = floatval($_POST['pd_wkh']);
    $pd_losthr = floatval($_POST['pd_losthr']);
    $pd_sethr = floatval($_POST['pd_sethr']);
    $pd_cav = intval($_POST['pd_cav']);
    $pd_weights = floatval($_POST['pd_weights']);
    $pd_runs = floatval($_POST['pd_runs']);
    $pd_cytm = floatval($_POST['pd_cytm']);
    $pd_reason = trim($_POST['pd_reason']);

    $sql = "UPDATE dbo.PRODUCTION SET PD_WKH=?, PD_LOSTHOUR=?, PD_SETUPHOUR=?, PD_CAV=?, PD_WEIGHT_S=?, PD_RUN_S=?, PD_CYTM=?, PD_LOST_REASON=? WHERE PD_ID=?";
    $stmt = sqlsrv_query($db, $sql, array($pd_wkh, $pd_losthr, $pd_sethr, $pd_cav, $pd_weights, $pd_runs, $pd_cytm, $pd_reason, $pd_id));
    sendJSON(array('status' => $stmt ? 'success' : 'error', 'errors' => sqlsrv_errors()));
}

// -------------------------------------------------------------
// 6. HAPUS LOT BESERTA DETAIL DEFECT-NYA (DELETE)
// -------------------------------------------------------------
if ($action === 'delete_detail') {
    $pd_id = intval($_POST['pd_id']);
    sqlsrv_query($db, "DELETE FROM dbo.NG_PROD WHERE PD_ID = ?", array($pd_id));
    $stmt = sqlsrv_query($db, "DELETE FROM dbo.PRODUCTION WHERE PD_ID = ?", array($pd_id));
    sendJSON(array('status' => $stmt ? 'success' : 'error', 'errors' => sqlsrv_errors()));
}

// -------------------------------------------------------------
// 7. LOAD DEFECT NG (READ NG)
// -------------------------------------------------------------
if ($action === 'load_ng') {
    $pd_id = intval($_POST['pd_id']);
    $sql = "SELECT NP.NGT_ID, NP.NGP_QTY, NT.NGT_DESC 
            FROM dbo.NG_PROD NP 
            INNER JOIN dbo.NG_TYPE NT ON NP.NGT_ID = NT.NGT_ID 
            WHERE NP.PD_ID = ?";
    $stmt = sqlsrv_query($db, $sql, array($pd_id));
    $data = array();
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $row['NGT_DESC'] = ac_item_utf8($row['NGT_DESC']);
            $data[] = $row;
        }
        sqlsrv_free_stmt($stmt);
        sendJSON(array('status' => 'success', 'data' => $data));
    } else {
        sendJSON(array('status' => 'error', 'errors' => sqlsrv_errors()));
    }
}

// -------------------------------------------------------------
// 8. SIMPAN DEFECT NG (CREATE / UPDATE NG)
// -------------------------------------------------------------
if ($action === 'save_ng') {
    $pd_id = intval($_POST['pd_id']);
    $ngt_id = intval($_POST['ngt_id']);
    $old_ngt_id = isset($_POST['old_ngt_id']) ? intval($_POST['old_ngt_id']) : 0;
    $qty = floatval($_POST['qty']);

    $check_id = ($old_ngt_id > 0) ? $old_ngt_id : $ngt_id;
    $sql_check = "SELECT COUNT(*) AS cnt FROM dbo.NG_PROD WHERE PD_ID = ? AND NGT_ID = ?";
    $stmt_check = sqlsrv_query($db, $sql_check, array($pd_id, $check_id));
    $row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

    if ($row_check['cnt'] > 0) {
        $sql = "UPDATE dbo.NG_PROD SET NGT_ID = ?, NGP_QTY = ? WHERE PD_ID = ? AND NGT_ID = ?";
        $stmt = sqlsrv_query($db, $sql, array($ngt_id, $qty, $pd_id, $check_id));
    } else {
        $sql = "INSERT INTO dbo.NG_PROD (PD_ID, NGT_ID, NGP_QTY) VALUES (?, ?, ?)";
        $stmt = sqlsrv_query($db, $sql, array($pd_id, $ngt_id, $qty));
    }
    sendJSON(array('status' => $stmt ? 'success' : 'error', 'errors' => sqlsrv_errors()));
}

// -------------------------------------------------------------
// 9. HAPUS DEFECT NG (DELETE NG)
// -------------------------------------------------------------
if ($action === 'delete_ng') {
    $pd_id = intval($_POST['pd_id']);
    $ngt_id = intval($_POST['ngt_id']);
    $sql = "DELETE FROM dbo.NG_PROD WHERE PD_ID = ? AND NGT_ID = ?";
    $stmt = sqlsrv_query($db, $sql, array($pd_id, $ngt_id));
    sendJSON(array('status' => $stmt ? 'success' : 'error', 'errors' => sqlsrv_errors()));
}

sendJSON(array());