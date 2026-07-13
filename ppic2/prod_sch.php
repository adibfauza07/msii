<?php
// ================================================================
// KONFIGURASI (SESUAI QUERY YANG DIBERIKAN)
// ================================================================
 $CFG = array(
    'wo' => array(
        'table' => 'WO',
        'wo_id' => 'WO_ID', 'wo_no' => 'WO_NO', 'wo_number' => 'WO_NUMBER',
        'wo_ref' => 'WO_REF', 'wo_qty' => 'WO_QTY', 'wo_rem' => 'WO_REM',
        'wo_start' => 'WO_START', 'wo_end' => 'WO_END', 'wo_cap' => 'WO_CAP',
        'wo_mmyy' => 'WO_MMYY', 'wo_closed' => 'WO_CLOSED', 'wo_status' => 'STATUS',
        'sisa' => 'SISA', 'std_qty' => 'STD_QTY', 'mac_id' => 'MAC_ID',
        'item_id' => 'ITEM_ID', 'prcd_code' => 'PRCD_CODE', 'proc_id' => 'PROC_ID',
        'bom_id' => 'BOM_ID', 'mold_id' => 'MOLD_ID', 'material1' => 'MATERIAL1',
        'mac_code' => 'MAC.MAC_CODE', 'mag_station' => 'MAG.MAG_STATION',
        'mag_loc' => 'MAG.MAG_LOC',
        'cust_code' => 'ITEM_CUSTINFO_VIEW.CUST_CODE',
        'cust_comp' => 'ITEM_CUSTINFO_VIEW.CUST_COMP',
        'part_code' => 'ITEM_CUSTINFO_VIEW.PART_CODE',
        'part_no' => 'ITEM_CUSTINFO_VIEW.PART_NO',
        'enabled' => true,
    ),

    // PRODUCTION: PD_QTY = Prod Actual, PD_OK, PD_HO, PD_NG = Quality
    'prod_actual' => array('enabled' => true),
    'quality' => array('enabled' => true),

    // DELIVERY PLAN: dari DELI_SCH + PRICE, SUM per tanggal
    'del_plan' => array('enabled' => true),

    // DELIVERY ACTUAL: dari DI + DIPA_PAR + PRICE, SUM per tanggal
    'del_actual' => array('enabled' => true),

    // TAG/BALANCE: dari TAGS + SOP, SUM per tanggal
    'tag_balance' => array('enabled' => true),

    // ITEM_STD: Cycle Time & Cavity dari ITEM_PROD
    'item_std' => array(
        'table' => 'ITEM_PROD',
        'item_id' => 'ITEM_ID',
        'cycle_time' => 'ITEM_CYTM',
        'cavity' => 'ITEM_CAVT',
        'enabled' => true,
    ),

    'target' => array('header' => 'RPT_PPIC', 'detail' => 'RPT_PPIC_DTL'),
);

// ================================================================
// KONEKSI DATABASE
// ================================================================
require_once __DIR__ . "/../config/database_ppic_test.php";
if ($conn === false) { die("Koneksi database gagal."); }

// ================================================================
// BANTUKAN
// ================================================================
function col_exists($conn, $table, $column) {
    $col = strpos($column, '.') !== false ? explode('.', $column)[1] : $column;
    $tbl = str_replace('dbo.', '', $table);
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = sqlsrv_query($conn, $sql, array($tbl, $col));
    if ($stmt === false) return false;
    return (sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) !== false);
}

function tbl_exists($conn, $table) {
    $tbl = str_replace('dbo.', '', $table);
    $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?";
    $stmt = sqlsrv_query($conn, $sql, array($tbl));
    if ($stmt === false) return false;
    return (sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) !== false);
}

function gv($arr, $key, $def) { return isset($arr[$key]) ? $arr[$key] : $def; }
function gt($arr, $key) { return isset($arr[$key]) ? trim($arr[$key]) : ''; }
function gf($arr, $key) { return isset($arr[$key]) ? (float)$arr[$key] : 0; }
function fdt($dt) {
    if ($dt === null) return '';
    if (is_object($dt)) return $dt->format('Y-m-d');
    return date('Y-m-d', strtotime($dt));
}
function ext_day($dt) {
    if ($dt === null) return 0;
    if (is_object($dt)) return (int)$dt->format('j');
    return (int)date('j', strtotime($dt));
}

// ================================================================
// BUILD WO QUERY (dengan ITEM_PROD untuk Cycle Time & Cavity)
// ================================================================
function buildWOQuery($CFG, $periode, $station, $mc_no, $count_only) {
    if ($count_only) {
        $sql = "SELECT COUNT(*) AS CNT ";
    } else {
        $sql  = "SELECT WO.WO_ID, WO.WO_NO, WO.WO_NUMBER, WO.WO_REF, WO.WO_QTY, ";
        $sql .= "WO.WO_START, WO.WO_END, WO.WO_CAP, WO.WO_MMYY, WO.STD_QTY, WO.SISA, WO.STATUS, ";
        $sql .= "WO.ITEM_ID, WO.MAC_ID, WO.PRCD_CODE, WO.PROC_ID, WO.MOLD_ID, ";
        $sql .= "MAC.MAC_CODE, MAG.MAG_STATION, MAG.MAG_LOC, ";
        $sql .= "ITEM_CUSTINFO_VIEW.CUST_CODE, ITEM_CUSTINFO_VIEW.CUST_COMP, ";
        $sql .= "ITEM_CUSTINFO_VIEW.PART_CODE, ITEM_CUSTINFO_VIEW.PART_NO, ";
        $sql .= "IP.ITEM_CYTM AS CYCLE_TIME, IP.ITEM_CAVT AS CAVITY ";
    }
    $sql .= "FROM WO ";
    $sql .= "INNER JOIN MAC ON WO.MAC_ID = MAC.MAC_ID ";
    $sql .= "INNER JOIN MAG ON MAC.MAG_ID = MAG.MAG_ID ";
    $sql .= "INNER JOIN ITEM_CUSTINFO_VIEW ON WO.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID ";
    $sql .= "CROSS APPLY ( ";
    $sql .= "  SELECT TOP 1 ITEM_CYTM, ITEM_CAVT ";
    $sql .= "  FROM ITEM_PROD ";
    $sql .= "  WHERE ITEM_PROD.ITEM_ID = WO.ITEM_ID ";
    $sql .= "  AND (ITEM_PROD.INACTIVE = 0 OR ITEM_PROD.INACTIVE IS NULL) ";
    $sql .= "  ORDER BY ITEM_PROD.ITEM_DEFAULT_BOM DESC ";
    $sql .= ") IP ";
    $sql .= "WHERE 1=1";

    $params = array();

    if ($periode !== '') {
        $tahun = (int)substr($periode, 0, 4);
        $bulan = (int)substr($periode, 4, 2);
        $periode_dt = $tahun . '-' . sprintf("%02d", $bulan) . '-01 00:00:00';
        $sql .= " AND WO.WO_MMYY = CONVERT(DATETIME, ?, 102)";
        $params[] = $periode_dt;
    }
    if ($station !== '') { $sql .= " AND MAG.MAG_STATION = ?"; $params[] = $station; }
    if ($mc_no !== '') { $sql .= " AND MAC.MAC_CODE = ?"; $params[] = $mc_no; }
    if (!$count_only) { $sql .= " ORDER BY MAC.MAC_CODE ASC, ITEM_CUSTINFO_VIEW.PART_CODE ASC"; }

    return array('sql' => $sql, 'params' => $params);
}

// ================================================================
// AJAX: TEST CONFIG
// ================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && isset($_GET['action']) && $_GET['action'] === 'test_config') {
    header('Content-Type: application/json; charset=utf-8');
    $results = array();

    $main_tables = array(
        'wo' => array('table' => 'WO', 'label' => 'Work Order'),
        'item_view' => array('table' => 'ITEM_CUSTINFO_VIEW', 'label' => 'Item/Customer View'),
        'mac' => array('table' => 'MAC', 'label' => 'Master Mesin'),
        'mag' => array('table' => 'MAG', 'label' => 'Master Station'),
        'production' => array('table' => 'PRODUCTION', 'label' => 'Production'),
        'deli_sch' => array('table' => 'DELI_SCH', 'label' => 'Delivery Schedule'),
        'di' => array('table' => 'DI', 'label' => 'Delivery Invoice'),
        'dipa_par' => array('table' => 'DIPA_PAR', 'label' => 'Delivery Invoice Part'),
        'price' => array('table' => 'PRICE', 'label' => 'Price Master'),
        'tags' => array('table' => 'TAGS', 'label' => 'TAGS'),
        'sop' => array('table' => 'SOP', 'label' => 'SOP'),
        'items' => array('table' => 'ITEMS', 'label' => 'Items'),
        'item_prod' => array('table' => 'ITEM_PROD', 'label' => 'Item Std (Cycle/Cavity)'),
    );
    foreach ($main_tables as $key => $info) {
        $exists = tbl_exists($conn, $info['table']);
        if ($exists) {
            $results[] = array('key' => $key, 'table' => $info['table'], 'status' => 'ok', 'msg' => $info['label'] . ' OK');
        } else {
            $results[] = array('key' => $key, 'table' => $info['table'], 'status' => 'error', 'msg' => $info['label'] . ' TIDAK DITEMUKAN');
        }
    }

    $wo_cols = array('WO_ID','WO_NO','WO_QTY','WO_MMYY','MAC_ID','ITEM_ID','WO_START','WO_END');
    foreach ($wo_cols as $col) {
        $exists = col_exists($conn, 'WO', $col);
        if ($exists) {
            $results[] = array('key' => 'WO.' . $col, 'table' => 'WO', 'status' => 'ok', 'msg' => 'Kolom ' . $col . ' OK');
        } else {
            $results[] = array('key' => 'WO.' . $col, 'table' => 'WO', 'status' => 'error', 'msg' => 'Kolom ' . $col . ' TIDAK DITEMUKAN');
        }
    }

    $join_cols = array(
        'MAC.MAC_CODE' => 'MAC',
        'MAG.MAG_STATION' => 'MAG',
        'ITEM_CUSTINFO_VIEW.PART_CODE' => 'ITEM_CUSTINFO_VIEW',
        'ITEM_CUSTINFO_VIEW.PART_NO' => 'ITEM_CUSTINFO_VIEW',
        'ITEM_CUSTINFO_VIEW.CUST_CODE' => 'ITEM_CUSTINFO_VIEW',
        'ITEM_CUSTINFO_VIEW.CUST_COMP' => 'ITEM_CUSTINFO_VIEW',
        'ITEM_PROD.ITEM_CYTM' => 'ITEM_PROD',
        'ITEM_PROD.ITEM_CAVT' => 'ITEM_PROD',
    );
    foreach ($join_cols as $col => $tbl) {
        $exists = col_exists($conn, $tbl, $col);
        if ($exists) {
            $results[] = array('key' => $col, 'table' => $tbl, 'status' => 'ok', 'msg' => $col . ' OK');
        } else {
            $results[] = array('key' => $col, 'table' => $tbl, 'status' => 'error', 'msg' => $col . ' TIDAK DITEMUKAN');
        }
    }

    $rpt_cols = array('ID_NO','MC_NO','PART_NO','PART_NAME','ITEM_CODE','CUST','FORECAST_N1','CUR_PO_BO','SAFETY_STK','BEG_BALANCE','CYCLE_TIME_STD','CAVITY_STD','CAP_DAY_STD','PROD_PLAN','WORK_DAY','periode','no_urut');
    foreach ($rpt_cols as $col) {
        $exists = col_exists($conn, 'RPT_PPIC', $col);
        if ($exists) {
            $results[] = array('key' => 'RPT_PPIC.' . $col, 'table' => 'RPT_PPIC', 'status' => 'ok', 'msg' => $col . ' OK');
        } else {
            $results[] = array('key' => 'RPT_PPIC.' . $col, 'table' => 'RPT_PPIC', 'status' => 'warning', 'msg' => $col . ' tidak ada');
        }
    }

    $rpt_dtl_cols = array('ID_NO','DESC_PROD','G_TOTAL','ROW_NAME','D1','D2','D3','D31');
    foreach ($rpt_dtl_cols as $col) {
        $exists = col_exists($conn, 'RPT_PPIC_DTL', $col);
        if ($exists) {
            $results[] = array('key' => 'RPT_PPIC_DTL.' . $col, 'table' => 'RPT_PPIC_DTL', 'status' => 'ok', 'msg' => $col . ' OK');
        } else {
            $results[] = array('key' => 'RPT_PPIC_DTL.' . $col, 'table' => 'RPT_PPIC_DTL', 'status' => 'error', 'msg' => $col . ' TIDAK DITEMUKAN');
        }
    }

    echo json_encode(array('status' => 'ok', 'results' => $results));
    exit;
}

// ================================================================
// AJAX: PREVIEW WO
// ================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && isset($_GET['action']) && $_GET['action'] === 'preview_wo') {
    header('Content-Type: application/json; charset=utf-8');
    $periode = isset($_GET['periode']) ? trim($_GET['periode']) : '';
    $station = isset($_GET['station']) ? trim($_GET['station']) : '';
    $mc_no = isset($_GET['mc_no']) ? trim($_GET['mc_no']) : '';
    if ($periode === '') { echo json_encode(array('status'=>'error','message'=>'Periode harus diisi')); exit; }

    $cq = buildWOQuery($CFG, $periode, $station, $mc_no, true);
    $stmt_c = sqlsrv_query($conn, $cq['sql'], $cq['params']);
    $total = 0;
    if ($stmt_c !== false) {
        $row_c = sqlsrv_fetch_array($stmt_c, SQLSRV_FETCH_ASSOC);
        if ($row_c) $total = (int)$row_c['CNT'];
    }
    if ($total === 0) { echo json_encode(array('status'=>'ok','count'=>0,'data'=>array())); exit; }

    $q = buildWOQuery($CFG, $periode, $station, $mc_no, false);
    $stmt = sqlsrv_query($conn, $q['sql'], $q['params']);
    $rows = array();
    if ($stmt === false) {
        $err = sqlsrv_errors(); $err_msg = '';
        if ($err) { foreach ($err as $e) { $err_msg .= $e['message'] . ' '; } }
        echo json_encode(array('status'=>'error','message'=>'Query error: ' . $err_msg)); exit;
    }
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = array(
            'wo_id' => gv($r, 'WO_ID', 0),
            'item_id' => gv($r, 'ITEM_ID', ''),
            'wo_no' => gt($r, 'WO_NO'), 'wo_number' => gt($r, 'WO_NUMBER'), 'wo_ref' => gt($r, 'WO_REF'),
            'wo_qty' => gf($r, 'WO_QTY'), 'wo_start' => fdt(gv($r, 'WO_START', null)),
            'wo_end' => fdt(gv($r, 'WO_END', null)), 'wo_cap' => gv($r, 'WO_CAP', null),
            'std_qty' => gv($r, 'STD_QTY', null), 'sisa' => gv($r, 'SISA', null),
            'status' => gt($r, 'STATUS'), 'wo_closed' => gv($r, 'WO_CLOSED', null),
            'mac_code' => gt($r, 'MAC_CODE'), 'mag_station' => gt($r, 'MAG_STATION'),
            'mag_loc' => gt($r, 'MAG_LOC'), 'cust_code' => gt($r, 'CUST_CODE'),
            'cust_comp' => gt($r, 'CUST_COMP'), 'part_code' => gt($r, 'PART_CODE'),
            'part_no' => gt($r, 'PART_NO'),
            'cycle_time' => gv($r, 'CYCLE_TIME', null), 'cavity' => gv($r, 'CAVITY', null),
        );
    }
    echo json_encode(array('status'=>'ok','count'=>$total,'data'=>$rows));
    exit;
}

// ================================================================
// AJAX: DO GENERATE
// ================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && isset($_GET['action']) && $_GET['action'] === 'do_generate') {
    header('Content-Type: application/json; charset=utf-8');
    $periode = isset($_GET['periode']) ? trim($_GET['periode']) : '';
    $station = isset($_GET['station']) ? trim($_GET['station']) : '';
    $mc_no = isset($_GET['mc_no']) ? trim($_GET['mc_no']) : '';
    $mode = isset($_GET['mode']) ? trim($_GET['mode']) : 'insert';

    if ($periode === '') { echo json_encode(array('status'=>'error','message'=>'Periode harus diisi')); exit; }
    $gen_tahun = (int)substr($periode, 0, 4);
    $gen_bulan = (int)substr($periode, 4, 2);
    if ($gen_tahun < 2020 || $gen_tahun > 2100 || $gen_bulan < 1 || $gen_bulan > 12) {
        echo json_encode(array('status'=>'error','message'=>'Format periode tidak valid')); exit;
    }

    $jml_hari = 0;
    for ($i = 1; $i <= 31; $i++) { if (checkdate($gen_bulan, $i, $gen_tahun)) $jml_hari++; }

    $q = buildWOQuery($CFG, $periode, $station, $mc_no, false);
    $stmt_wo = sqlsrv_query($conn, $q['sql'], $q['params']);
    if ($stmt_wo === false) {
        $err = sqlsrv_errors(); $err_msg = '';
        if ($err) { foreach ($err as $e) { $err_msg .= $e['message'] . ' '; } }
        echo json_encode(array('status'=>'error','message'=>'Query WO error: ' . $err_msg)); exit;
    }
    $wo_list = array();
    while ($r = sqlsrv_fetch_array($stmt_wo, SQLSRV_FETCH_ASSOC)) { $wo_list[] = $r; }
    if (count($wo_list) === 0) { echo json_encode(array('status'=>'error','message'=>'Tidak ada data WO untuk periode ' . $periode)); exit; }

    // MODE REPLACE: hapus data lama
    if ($mode === 'replace') {
        $mc_list = array(); $ic_list = array();
        foreach ($wo_list as $w) {
            $mc = gt($w, 'MAC_CODE'); $ic = gt($w, 'PART_CODE');
            if ($mc !== '' && !in_array($mc, $mc_list)) $mc_list[] = $mc;
            if ($ic !== '' && !in_array($ic, $ic_list)) $ic_list[] = $ic;
        }
        if (count($mc_list) > 0) {
            $ph = implode(',', array_fill(0, count($mc_list), '?'));
            sqlsrv_query($conn, "DELETE FROM RPT_PPIC_DTL WHERE ID_NO IN (SELECT ID_NO FROM RPT_PPIC WHERE periode = ? AND MC_NO IN (" . $ph . "))", array_merge(array($periode), $mc_list));
            sqlsrv_query($conn, "DELETE FROM RPT_PPIC WHERE periode = ? AND MC_NO IN (" . $ph . ")", array_merge(array($periode), $mc_list));
        }
    }

    $log = array(); $success_count = 0; $error_count = 0; $skip_count = 0; $no_urut = 1;

    // Daftar baris data yang akan di-generate
    $data_row_names = array();
    if ($CFG['del_plan']['enabled']) $data_row_names[] = 'Del Plan';
    if ($CFG['del_actual']['enabled']) $data_row_names[] = 'Del Actual';
    if ($CFG['prod_actual']['enabled']) $data_row_names[] = 'Prod Actual';
    if ($CFG['quality']['enabled']) { $data_row_names[] = 'OK'; $data_row_names[] = 'HOLD'; $data_row_names[] = 'NG'; $data_row_names[] = 'NG Rework'; }
    if ($CFG['tag_balance']['enabled']) $data_row_names[] = 'TAG (Balance)';

    // Baris kalkulasi (selalu dibuat kosong)
    $calc_rows = array('Prod Plan R0','Prod Plan R1','Prod Balance','STD MCD','ACT MCD','BAL TIME','RATE MC','TIME AMT','EFF');

    // ============================================================
    // LOOP SETIAP WO
    // ============================================================
    foreach ($wo_list as $w) {
        $wo_id   = gv($w, 'WO_ID', 0);
        $wo_no   = gt($w, 'WO_NO');
        $item_id = gt($w, 'ITEM_ID');
        $item_code = gt($w, 'PART_CODE');
        $part_no = gt($w, 'PART_NO');
        $part_name = '';
        $cust    = gt($w, 'CUST_COMP');
        $mc_no_w = gt($w, 'MAC_CODE');
        $wo_qty  = gf($w, 'WO_QTY');
        $wo_cap  = gv($w, 'WO_CAP', null);
        $std_qty = gv($w, 'STD_QTY', null);
        $sisa    = gv($w, 'SISA', null);
        $cyc_time = gv($w, 'CYCLE_TIME', null);
        $cavity  = gv($w, 'CAVITY', null);

        if ($item_code === '' || $mc_no_w === '') {
            $log[] = array('wo_no'=>$wo_no,'mc'=>$mc_no_w,'item'=>$item_code,'status'=>'skip','msg'=>'Item Code atau MC_NO kosong');
            $skip_count++; $no_urut++; continue;
        }

        $id_no = $periode . '_' . $mc_no_w . '_' . $item_code;

      // 1. CEK EXISTING BERDASARKAN PERIODE, MC_NO, dan ITEM_CODE (Bukan dari teks gabungan)
        $sql_chk = "SELECT ID_NO FROM RPT_PPIC WHERE periode = ? AND MC_NO = ? AND ITEM_CODE = ?";
        $stmt_chk = sqlsrv_query($conn, $sql_chk, array($periode, $mc_no_w, $item_code));
        $exists = false;
        $db_id_no = null; // Penampung ID integer dari database

        if ($stmt_chk !== false && $row_chk = sqlsrv_fetch_array($stmt_chk, SQLSRV_FETCH_ASSOC)) {
            $exists = true;
            $db_id_no = $row_chk['ID_NO'];
        }

        if ($exists && $mode === 'insert') {
            $log[] = array('wo_no'=>$wo_no,'mc'=>$mc_no_w,'item'=>$item_code,'status'=>'skip','msg'=>'Sudah ada (mode=insert)');
            $skip_count++; $no_urut++; continue;
        }

        // ============================================
        // INSERT / UPDATE HEADER
        // ============================================
        // 2. KITA HAPUS 'ID_NO' DARI ARRAY, BIARKAN SQL SERVER YANG BUAT ANGKA AUTO-NYA
        $h_cols = array('MC_NO','PART_NO','PART_NAME','ITEM_CODE','CUST','periode','no_urut');
        $h_vals = array($mc_no_w, $part_no, $part_name, $item_code, $cust, $periode, $no_urut);
        $h_ph = array_fill(0, count($h_cols), '?');

        $optional_cols = array(
            'MC_CAPACITY' => $wo_cap,
            'FORECAST_N1' => $wo_qty,
            'CUR_PO_BO' => $wo_qty,
            'SAFETY_STK' => null,
            'BEG_BALANCE' => null,
            'CYCLE_TIME_STD' => $cyc_time,
            'CAVITY_STD' => $cavity,
            'CAP_DAY_STD' => null,
            'PROD_PLAN' => $wo_qty,
            'WORK_DAY' => $jml_hari,
            'ppa_qty' => null,
        );
        foreach ($optional_cols as $col => $val) {
            if (col_exists($conn, 'RPT_PPIC', $col)) {
                $h_cols[] = $col; $h_vals[] = $val; $h_ph[] = '?';
            }
        }

        $final_id_no = null;

        if ($exists && $mode === 'upsert') {
            $set_parts = array(); $upd_vals = array();
            for ($i = 0; $i < count($h_cols); $i++) {
                $set_parts[] = $h_cols[$i] . " = ?";
                $upd_vals[] = $h_vals[$i];
            }
            $sql_h = "UPDATE RPT_PPIC SET " . implode(", ", $set_parts) . " WHERE ID_NO = ?";
            $upd_vals[] = $db_id_no;
            $stmt_h = sqlsrv_query($conn, $sql_h, $upd_vals);
            $final_id_no = $db_id_no;
        } else {
            // 3. GUNAKAN 'OUTPUT INSERTED.ID_NO' UNTUK MENGAMBIL ANGKA INTEGER YANG BARU DIBUAT
            $sql_h = "INSERT INTO RPT_PPIC (" . implode(",", $h_cols) . ") OUTPUT INSERTED.ID_NO VALUES (" . implode(",", $h_ph) . ")";
            $stmt_h = sqlsrv_query($conn, $sql_h, $h_vals);
            
            if ($stmt_h !== false) {
                $row_insert = sqlsrv_fetch_array($stmt_h, SQLSRV_FETCH_ASSOC);
                if ($row_insert) {
                    $final_id_no = $row_insert['ID_NO'];
                }
            }
        }

        if ($stmt_h === false || !$final_id_no) {
            $err = sqlsrv_errors(); $err_msg = '';
            if ($err) { foreach ($err as $e) { $err_msg .= $e['message'] . ' '; } }
            $log[] = array('wo_no'=>$wo_no,'mc'=>$mc_no_w,'item'=>$item_code,'status'=>'error','msg'=>'Header: ' . $err_msg);
            $error_count++; $no_urut++; continue;
        }

        if ($exists) { sqlsrv_query($conn, "DELETE FROM RPT_PPIC_DTL WHERE ID_NO = ?", array($final_id_no)); }

        // ============================================
        // AMBIL DATA HARIAN DARI 5 SUMBER
        // ============================================
        $daily_data = array();
        $item_id_int = ($item_id !== '') ? (int)$item_id : 0;

        // --- 1. PRODUCTION: Prod Actual + Quality (OK, HOLD, NG) ---
        if ($CFG['prod_actual']['enabled'] || $CFG['quality']['enabled']) {
            $sql_prod  = "SELECT CONVERT(VARCHAR(6), PD_DATE, 112) AS DT, ";
            $sql_prod .= "SUM(PD_QTY) AS PROD_QTY, ";
            $sql_prod .= "SUM(PD_OK) AS OK_QTY, ";
            $sql_prod .= "SUM(PD_HO) AS HOLD_QTY, ";
            $sql_prod .= "SUM(PD_NG) AS NG_QTY ";
            $sql_prod .= "FROM PRODUCTION ";
            $sql_prod .= "WHERE WO_ID = ? ";
            $sql_prod .= "AND CONVERT(VARCHAR(6), PD_DATE, 112) = ? ";
            $sql_prod .= "GROUP BY CONVERT(VARCHAR(6), PD_DATE, 112)";
            $stmt_prod = sqlsrv_query($conn, $sql_prod, array($wo_id, $periode));

            if ($stmt_prod !== false) {
                $daily_data['Prod Actual'] = array_fill(1, 31, null);
                if ($CFG['quality']['enabled']) {
                    $daily_data['OK'] = array_fill(1, 31, null);
                    $daily_data['HOLD'] = array_fill(1, 31, null);
                    $daily_data['NG'] = array_fill(1, 31, null);
                    $daily_data['NG Rework'] = array_fill(1, 31, null);
                }
                while ($rd = sqlsrv_fetch_array($stmt_prod, SQLSRV_FETCH_ASSOC)) {
                    $day = ext_day($rd['DT']);
                    if ($day >= 1 && $day <= 31) {
                        $daily_data['Prod Actual'][$day] = (float)$rd['PROD_QTY'];
                       if ($CFG['quality']['enabled']) {
                            $daily_data['OK'][$day] = (float)$rd['OK_QTY'];
                            $daily_data['HOLD'][$day] = (float)$rd['HOLD_QTY'];
                            $daily_data['NG'][$day] = (float)$rd['NG_QTY'];
                        }
                    }
                }
            }
        }

        // --- 2. DELIVERY PLAN ---
        if ($CFG['del_plan']['enabled'] && $item_id_int > 0) {
            $sql_dp  = "SELECT CONVERT(VARCHAR(6), DELS_DATE, 112) AS DT, SUM(DELS_QTY) AS QTY ";
            $sql_dp .= "FROM DELI_SCH INNER JOIN PRICE ON DELI_SCH.PRICE_ID = PRICE.PRICE_ID ";
            $sql_dp .= "WHERE PRICE.PART_ID = ? ";
            $sql_dp .= "AND CONVERT(VARCHAR(6), DELS_DATE, 112) = ? ";
            $sql_dp .= "GROUP BY CONVERT(VARCHAR(6), DELS_DATE, 112)";
            $stmt_dp = sqlsrv_query($conn, $sql_dp, array($item_id_int, $periode));
            if ($stmt_dp !== false) {
                $daily_data['Del Plan'] = array_fill(1, 31, null);
                while ($rd = sqlsrv_fetch_array($stmt_dp, SQLSRV_FETCH_ASSOC)) {
                    $day = ext_day($rd['DT']);
                    if ($day >= 1 && $day <= 31) { $daily_data['Del Plan'][$day] = (float)$rd['QTY']; }
                }
            }
        }

        // --- 3. DELIVERY ACTUAL ---
        if ($CFG['del_actual']['enabled'] && $item_id_int > 0) {
            $sql_da  = "SELECT CONVERT(VARCHAR(6), DI.DI_DATE, 112) AS DT, SUM(DIPA_PAR.QTY) AS QTY ";
            $sql_da .= "FROM DI ";
            $sql_da .= "INNER JOIN DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID ";
            $sql_da .= "INNER JOIN PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID ";
            $sql_da .= "WHERE PRICE.PART_ID = ? ";
            $sql_da .= "AND CONVERT(VARCHAR(6), DI.DI_DATE, 112) = ? ";
            $sql_da .= "GROUP BY CONVERT(VARCHAR(6), DI.DI_DATE, 112)";
            $stmt_da = sqlsrv_query($conn, $sql_da, array($item_id_int, $periode));
            if ($stmt_da !== false) {
                $daily_data['Del Actual'] = array_fill(1, 31, null);
                while ($rd = sqlsrv_fetch_array($stmt_da, SQLSRV_FETCH_ASSOC)) {
                    $day = ext_day($rd['DT']);
                    if ($day >= 1 && $day <= 31) { $daily_data['Del Actual'][$day] = (float)$rd['QTY']; }
                }
            }
        }

        // --- 4. TAG / BALANCE ---
        if ($CFG['tag_balance']['enabled'] && $item_id_int > 0) {
            $sql_tag = "SELECT CONVERT(VARCHAR(6), SOP.SOP_SDATE, 112) AS DT, SUM(TAGS.TAG_QTY) AS BALANCE ";
            $sql_tag .= "FROM TAGS ";
            $sql_tag .= "INNER JOIN SOP ON TAGS.SOP_ID = SOP.SOP_ID ";
            $sql_tag .= "WHERE TAGS.ITEM_ID = ? ";
            $sql_tag .= "AND CONVERT(VARCHAR(6), SOP.SOP_SDATE, 112) = ? ";
            $sql_tag .= "GROUP BY CONVERT(VARCHAR(6), SOP.SOP_SDATE, 112)";
            $stmt_tag = sqlsrv_query($conn, $sql_tag, array($item_id_int, $periode));
            if ($stmt_tag !== false) {
                $daily_data['TAG (Balance)'] = array_fill(1, 31, null);
                while ($rd = sqlsrv_fetch_array($stmt_tag, SQLSRV_FETCH_ASSOC)) {
                    $day = ext_day($rd['DT']);
                    if ($day >= 1 && $day <= 31) { $daily_data['TAG (Balance)'][$day] = (float)$rd['BALANCE']; }
                }
            }
        }

        // ============================================
        // INSERT DETAIL ROWS
        // ============================================
        $detail_errors = array();
        $all_row_names = array_merge($data_row_names, $calc_rows);

        foreach ($all_row_names as $desc_name) {
            $is_calc = in_array($desc_name, $calc_rows);
            $g_total = 0; $d_params = array();
            for ($d = 1; $d <= 31; $d++) {
                if ($is_calc) {
                    $d_params[] = null;
                } else {
                    $v = isset($daily_data[$desc_name][$d]) ? $daily_data[$desc_name][$d] : null;
                    $d_params[] = ($v !== null) ? (float)$v : null;
                    if ($v !== null) $g_total += (float)$v;
                }
            }
            $d_ph = implode(',', array_fill(0, 31, '?'));
            $sql_dtl = "INSERT INTO RPT_PPIC_DTL (ID_NO, DESC_PROD, G_TOTAL, ";
            $sql_dtl .= "D1,D2,D3,D4,D5,D6,D7,D8,D9,D10,D11,D12,D13,D14,D15,D16,D17,D18,D19,D20,";
            $sql_dtl .= "D21,D22,D23,D24,D25,D26,D27,D28,D29,D30,D31, ROW_NAME) ";
            $sql_dtl .= "VALUES (?,?,?," . $d_ph . ",?)";
           $all_params = array_merge(array($final_id_no, $desc_name, $g_total), $d_params, array($desc_name));
            $stmt_dtl = sqlsrv_query($conn, $sql_dtl, $all_params);
            if ($stmt_dtl === false) {
                $err = sqlsrv_errors(); $err_msg = '';
                if ($err) { foreach ($err as $e) { $err_msg .= $e['message'] . ' '; } }
                $detail_errors[] = $desc_name . ': ' . $err_msg;
            }
        }

        $data_rows = count($data_row_names); $calc_count = count($calc_rows);
        if (count($detail_errors) > 0) {
            $log[] = array('wo_no'=>$wo_no,'mc'=>$mc_no_w,'item'=>$item_code,'status'=>'partial','msg'=>'Detail error: ' . implode('; ', $detail_errors));
            $error_count++;
        } else {
            $log[] = array('wo_no'=>$wo_no,'mc'=>$mc_no_w,'item'=>$item_code,'status'=>'ok','msg'=>'OK: ' . $data_rows . ' data + ' . $calc_count . ' calc = ' . ($data_rows + $calc_count) . ' rows');
            $success_count++;
        }
        $no_urut++;
    }

    echo json_encode(array('status'=>'ok','message'=>'Generate selesai','total_wo'=>count($wo_list),'success'=>$success_count,'error'=>$error_count,'skip'=>$skip_count,'log'=>$log));
    exit;
}

// ================================================================
// DAFTAR STATION
// ================================================================
 $sql_st = "SELECT DISTINCT MAG_STATION FROM MAG ORDER BY MAG_STATION ASC";
 $stmt_st = sqlsrv_query($conn, $sql_st);
 $list_station = array();
if ($stmt_st !== false) {
    while ($r = sqlsrv_fetch_array($stmt_st, SQLSRV_FETCH_ASSOC)) {
        $ms = trim($r['MAG_STATION']);
        if ($ms !== '') $list_station[] = $ms;
    }
}
 $nama_bulan = array(1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Generate Production Schedule</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f5f5f5;padding:20px;color:#333}
        .main-container{max-width:1200px;margin:0 auto}
        .page-title{font-size:24px;font-weight:700;margin-bottom:5px;color:#333}
        .page-desc{font-size:13px;color:#888;margin-bottom:25px}
        .wizard{background:#fff;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.1);overflow:hidden;margin-bottom:20px}
        .wizard-header{background:linear-gradient(135deg,#337ab7,#286090);padding:15px 25px;display:flex;align-items:center;gap:15px}
        .wizard-header h3{color:#fff;margin:0;font-size:16px;font-weight:600}
        .wizard-steps{display:flex;border-bottom:2px solid #e0e0e0;background:#fafafa}
        .wiz-step{flex:1;text-align:center;padding:12px 8px;font-size:12px;font-weight:600;color:#999;position:relative;cursor:default}
        .wiz-step::after{content:'';position:absolute;bottom:-2px;left:0;right:0;height:2px;background:#ccc}
        .wiz-step.active{color:#337ab7;background:#e3f2fd}
        .wiz-step.active::after{background:#337ab7}
        .wiz-step.done{color:#5cb85c}
        .wiz-step.done::after{background:#5cb85c}
        .wiz-step .step-num{display:inline-block;width:24px;height:24px;line-height:24px;border-radius:50%;background:#ddd;color:#fff;font-size:12px;margin-right:6px}
        .wiz-step.active .step-num{background:#337ab7}
        .wiz-step.done .step-num{background:#5cb85c}
        .wizard-body{padding:25px}
        .config-table{width:100%;font-size:12px;margin-bottom:20px}
        .config-table th{background:#f5f5f5;padding:8px 10px;text-align:left;font-weight:600;border-bottom:2px solid #ddd;white-space:nowrap}
        .config-table td{padding:6px 10px;border-bottom:1px solid #eee;vertical-align:top}
        .config-table code{background:#f0f0f0;padding:1px 5px;border-radius:3px;font-size:11px;color:#d9534f}
        .status-ok{color:#5cb85c;font-weight:700}
        .status-error{color:#f44336;font-weight:700}
        .status-warning{color:#f0ad4e;font-weight:700}
        .btn-generate{background:linear-gradient(135deg,#5cb85c,#4cae4c);color:#fff;border:none;font-size:14px;font-weight:700;padding:12px 40px;border-radius:6px;cursor:pointer;transition:all .2s}
        .btn-generate:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(92,184,92,.4)}
        .btn-generate:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none}
        .log-box{max-height:400px;overflow-y:auto;background:#263238;color:#aed581;font-family:'Courier New',monospace;font-size:11px;padding:15px;border-radius:6px;margin-top:15px}
        .log-ok{color:#a5d6a7}
        .log-err{color:#ef9a9a}
        .log-warn{color:#ffe082}
        .log-skip{color:#90a4ae}
        .log-line{margin:3px 0;white-space:pre-wrap;word-wrap:break-word}
        .preview-table{font-size:11px;margin-bottom:15px}
        .preview-table th{background:#337ab7;color:#fff;padding:6px 8px;white-space:nowrap}
        .preview-table td{padding:5px 8px;border-bottom:1px solid #eee}
        .mode-card{border:2px solid #ddd;border-radius:8px;padding:15px;cursor:pointer;transition:all .2s;text-align:center}
        .mode-card:hover{border-color:#337ab7;background:#e3f2fd}
        .mode-card.selected{border-color:#337ab7;background:#e3f2fd;box-shadow:0 0 0 3px rgba(51,122,183,.2)}
        .mode-card .mode-icon{font-size:28px;color:#337ab7;margin-bottom:8px}
        .mode-card .mode-title{font-weight:700;font-size:13px;margin-bottom:4px}
        .mode-card .mode-desc{font-size:11px;color:#888}
        .mode-card.mode-replace.selected{border-color:#d9534f;background:#ffeaea}
        .mode-card.mode-replace .mode-icon{color:#d9534f}
        .data-summary{background:#f0f7ff;border:1px solid #90caf9;border-radius:6px;padding:12px 16px;font-size:12px;margin-bottom:15px}
        .data-summary table{font-size:11px;margin-bottom:0}
        .data-summary td{padding:3px 8px;border:none}
        .data-summary td.val{font-weight:700;color:#1565c0}
    </style>
</head>
<body>
<div class="main-container">
    <div class="page-title"><i class="fa fa-cogs"></i> Generate Production Schedule</div>
    <div class="page-desc">WO → RPT_PPIC | Sumber: WO, PRODUCTION, DELI_SCH, DI, TAGS, ITEM_PROD</div>

    <div class="wizard">
        <div class="wizard-header"><i class="fa fa-magic" style="color:#fff;font-size:20px"></i><h3>Wizard Generate</h3></div>
        <div class="wizard-steps">
            <div class="wiz-step active" id="ws1"><span class="step-num">1</span>Config Check</div>
            <div class="wiz-step" id="ws2"><span class="step-num">2</span>Preview WO</div>
            <div class="wiz-step" id="ws3"><span class="step-num">3</span>Generate</div>
        </div>
        <div class="wizard-body">

            <!-- STEP 1 -->
            <div id="step1">
                <h4 style="margin-top:0"><i class="fa fa-database"></i> Cek Tabel & Kolom</h4>
                <p class="text-muted" style="font-size:12px">Memeriksa 12 tabel yang dibutuhkan oleh generator.</p>
                <button class="btn btn-primary btn-sm" id="btnTestConfig" onclick="testConfig()"><i class="fa fa-stethoscope"></i> Test Konfigurasi</button>
                <div id="configResult" style="margin-top:15px;display:none">
                    <table class="config-table">
                        <thead><tr><th>Objek</th><th>Tabel</th><th>Status</th><th>Keterangan</th></tr></thead>
                        <tbody id="configTbody"></tbody>
                    </table>
                </div>
                <div style="margin-top:20px;text-align:right">
                    <button class="btn btn-primary" id="btnToStep2" disabled onclick="goStep(2)"><i class="fa fa-arrow-right"></i> Lanjut ke Preview <i class="fa fa-arrow-right"></i></button>
                </div>
            </div>

            <!-- STEP 2 -->
            <div id="step2" style="display:none">
                <h4 style="margin-top:0"><i class="fa fa-search"></i> Preview Data Work Order</h4>
                <div class="row" style="margin-bottom:15px">
                    <div class="col-md-3">
                        <label class="control-label" style="font-size:12px;font-weight:600">Periode</label>
                        <div class="input-group">
                            <select class="form-control input-sm" id="selBulanGen">
                                <?php foreach ($nama_bulan as $m => $nm): ?>
                                <option value="<?php echo $m; ?>" <?php if ($m == date('m')) echo 'selected'; ?>><?php echo $nm; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-control input-sm" id="selTahunGen">
                                <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php if ($y == date('Y')) echo 'selected'; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="control-label" style="font-size:12px;font-weight:600">Station</label>
                        <select class="form-control input-sm" id="selStationGen">
                            <option value="">-- Semua --</option>
                            <?php foreach ($list_station as $st): ?>
                            <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="control-label" style="font-size:12px;font-weight:600">Mesin</label>
                        <input type="text" class="form-control input-sm" id="inpMcGen" placeholder="Kosongkan = semua">
                    </div>
                    <div class="col-md-2">
                        <label class="control-label" style="font-size:12px;font-weight:600">&nbsp;</label>
                        <button class="btn btn-primary btn-sm btn-block" onclick="previewWO()"><i class="fa fa-search"></i> Preview</button>
                    </div>
                </div>
                <div id="previewResult" style="display:none">
                    <div class="alert alert-info" style="font-size:12px;padding:8px 12px;margin-bottom:10px">
                        <i class="fa fa-info-circle"></i> Ditemukan <strong id="woCount">0</strong> Work Order
                    </div>
                    <div style="max-height:350px;overflow-y:auto">
                        <table class="table table-bordered table-condensed preview-table">
                            <thead><tr><th>#</th><th>WO No</th><th>MC</th><th>Station</th><th>Part Code</th><th>Part No</th><th>Customer</th><th>WO Qty</th><th>Cyc.Time</th><th>Cavity</th><th>Start</th><th>End</th><th>Sisa</th><th>Status</th></tr></thead>
                            <tbody id="woTbody"></tbody>
                        </table>
                    </div>
                </div>
                <div style="margin-top:20px;display:flex;justify-content:space-between">
                    <button class="btn btn-default" onclick="goStep(1)"><i class="fa fa-arrow-left"></i> Kembali</button>
                    <button class="btn btn-primary" id="btnToStep3" disabled onclick="goStep(3)"><i class="fa fa-arrow-right"></i> Lanjut ke Generate <i class="fa fa-arrow-right"></i></button>
                </div>
            </div>

            <!-- STEP 3 -->
            <div id="step3" style="display:none">
                <h4 style="margin-top:0"><i class="fa fa-bolt"></i> Generate ke RPT_PPIC</h4>

                <!-- SUMBERY DATA YANG AKAN DI-GENERATE -->
                <div class="data-summary" id="dataSummaryBox" style="display:none">
                    <strong><i class="fa fa-database"></i> Summary Data per Baris:</strong>
                    <table class="table table-condensed" style="margin-bottom:0">
                        <tr><td style="color:#666">Prod Actual</td><td class="val">dari PRODUCTION.PD_QTY (SUM per hari)</td></tr>
                        <tr><td style="color:#5cb85c">OK</td><td class="val">dari PRODUCTION.PD_OK (SUM per hari)</td></tr>
                        <tr><td style="color:#f0ad4e">HOLD</td><td class="val">dari PRODUCTION.PD_HO (SUM per hari)</td></tr>
                        <tr><td style="color:#f44336">NG</td><td class="val">dari PRODUCTION.PD_NG (SUM per hari)</td></tr>
                        <tr><td style="color:#9c27b0">Del Plan</td><td class="val">dari DELI_SCH.DELS_QTY (SUM per hari, via PRICE.PART_ID)</td></tr>
                        <tr><td style="color:#2196f3">Del Actual</td><td class="val">dari DI+DIPA_PAR.QTY (SUM per hari, via PRICE.PART_ID)</td></tr>
                        <tr><td style="color:#ff9800">TAG Balance</td><td class="val">dari TAGS.TAG_QTY (SUM per hari, via SOP.SOP_SDATE)</td></tr>
                        <tr><td style="color:#333"><strong>Cycle Time</strong></td><td class="val">dari ITEM_PROD.ITEM_CYTM (CROSS APPLY, ambil default BOM)</td></tr>
                        <tr><td style="color:#333"><strong>Cavity</strong></td><td class="val">dari ITEM_PROD.ITEM_CAVT (CROSS APPLY, ambil default BOM)</td></tr>
                        <tr><td style="color:#999"><strong>Prod Plan R0-EFF</strong></td><td class="val">Dibuat kosong, diisi user di halaman view</td></tr>
                    </table>
                </div>

                <div class="row" style="margin-bottom:20px">
                    <div class="col-md-4">
                        <div class="mode-card selected" data-mode="insert" onclick="selectMode(this)">
                            <div class="mode-icon"><i class="fa fa-plus-circle"></i></div>
                            <div class="mode-title">Insert Only</div>
                            <div class="mode-desc">Skip jika sudah ada</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mode-card" data-mode="upsert" onclick="selectMode(this)">
                            <div class="mode-icon"><i class="fa fa-pencil"></i></div>
                            <div class="mode-title">Upsert</div>
                            <div class="mode-desc">Update + regenerate detail</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mode-card mode-replace" data-mode="replace" onclick="selectMode(this)">
                            <div class="mode-icon"><i class="fa fa-refresh"></i></div>
                            <div class="mode-title">Replace</div>
                            <div class="mode-desc" style="color:#d9534f">Hapus lama, insert ulang</div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-warning" style="font-size:12px;padding:8px 12px">
                    <i class="fa fa-exclamation-triangle"></i> Periode: <strong id="genPeriode"></strong> | Station: <strong id="genStation">Semua</strong> | Mesin: <strong id="genMc">Semua</strong>
                </div>
                <div style="text-align:center;margin:25px 0">
                    <button class="btn-generate" id="btnGenerate" onclick="doGenerate()"><i class="fa fa-bolt"></i> GENERATE SEKARANG</button>
                </div>
                <div id="genResult" style="display:none">
                    <div class="alert" id="genAlert" style="font-size:13px"></div>
                    <div class="log-box" id="genLog"></div>
                </div>
                <div style="margin-top:20px;display:flex;justify-content:space-between">
                    <button class="btn btn-default" onclick="goStep(2)"><i class="fa fa-arrow-left"></i> Kembali</button>
                    <a href="rpt_ppic_view.php" class="btn btn-success" id="btnViewResult" style="display:none"><i class="fa fa-table"></i> Lihat Schedule</a>
                </div>
            </div>

        </div>
    </div>

    <div class="panel panel-default" style="font-size:11px">
        <div class="panel-heading" style="padding:8px 12px;font-size:12px;font-weight:600;cursor:pointer" onclick="$(this).next().slideToggle()">
            <i class="fa fa-link"></i> Mapping WO → RPT_PPIC (klik lihat detail)
        </div>
        <div class="panel-body" style="display:none;padding:10px">
            <table class="table table-condensed" style="font-size:11px;margin-bottom:0">
                <thead><tr><th>RPT_PPIC</th><th>Sumber</th><th>Sumber Query</th><th>Keterangan</th></tr></thead>
                <tbody>
                    <tr><td><code>ID_NO</code></td><td>Generated</td><td>-</td><td>YYYYMM_MC_CODE_PART_CODE</td></tr>
                    <tr><td><code>MC_NO</code></td><td>1 kolom</td><td><code>MAC.MAC_CODE</code></td><td>Kode mesin</td></tr>
                    <tr><td><code>ITEM_CODE</code></td><td>1 kolom</td><td><code>ITEM_CUSTINFO_VIEW.PART_CODE</code></td><td>Kode part (item code)</td></tr>
                    <tr><td><code>PART_NO</code></td><td>1 kolom</td><td><code>ITEM_CUSTINFO_VIEW.PART_NO</code></td><td>Nomor part</td></tr>
                    <td colspan="4" style="font-weight:700;background:#fff3e0"><strong>Header (diisi dari WO)</strong></td></tr>
                    <tr><td><code>FORECAST_N1</code></td><td>-</td><td><code>WO.WO_QTY</code></td><td>Sama dengan WO qty</td></tr>
                    <tr><td><code>CUR_PO_BO</code></td><td>-</td><td><code>WO.WO_QTY</code></td><td>Sama dengan WO qty</td></tr>
                    <td colspan="4" style="font-weight:700;background:#fff3e0"><strong>Header (diisi dari ITEM_PROD)</strong></td></tr>
                    <tr><td><code>CYCLE_TIME_STD</code></td><td>-</td><td><code>ITEM_PROD.ITEM_CYTM</code></td><td>Cycle time dari default BOM</td></tr>
                    <tr><code>CAVITY_STD</code></td><td>-</td><td><code>ITEM_PROD.ITEM_CAVT</code></td>Cavity dari default BOM</td></tr>
                    <td colspan="4" style="font-weight:700;background:#fff3e0"><strong>Detail (diisi dari query harian)</strong></td></tr>
                    <tr><td><code>Prod Actual</code></td><td>1 kolom</td><td><code>PRODUCTION.PD_QTY</code></td>SUM per PD_DATE & WO_ID</td></tr>
                    <tr><td><code>OK</code></td><td>1 kolom</td><td><code>PRODUCTION.PD_OK</code></td>SUM per PD_DATE & WO_ID</td></tr>
                    <tr><td><code>HOLD</code></td><td>1 kolom</td><td><code>PRODUCTION.PD_HO</code></td>SUM per PD_DATE & WO_ID</td></tr>
                    <tr><td><code>NG</code></td><td>1 kolom</td><td><code>PRODUCTION.PD_NG</code></td>SUM per PD_DATE & WO_ID</td></tr>
                    <tr><td><code>NG Rework</code></td><td>-</td><td>-</td><td>Tidak ada sumber, kosong</td></tr>
                    <tr><td><code>Del Plan</code></td><td>1 kolom</td><td><code>DELI_SCH.DELS_QTY</code></td>SUM per DELS_DATE & PRICE.PART_ID (= WO.ITEM_ID)</td></tr>
                    <tr><td><code>Del Actual</code></td><td>1 kolom</td><td><code>DI+DIPA_PAR.QTY</code></td>SUM per DI_DATE & PRICE.PART_ID (= WO.ITEM_ID)</td></tr>
                    <tr><td><code>TAG (Balance)</code></td><td>1 kolom</td><code>TAGS.TAG_QTY</code></td>SUM per SOP_SDATE & TAGS.ITEM_ID (= WO.ITEM_ID)</td></tr>
                    <td colspan="4" style="font-weight:700;background:#fff3e0"><strong>Kosong (diisi user di halaman view)</strong></td></tr>
                    <tr><td><code>Prod Plan R0~EFF</code></td><td>9 baris</td><td>-</td>Dibuat kosong, diisi manual di halaman view</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script>
var selectedMode = 'insert';

function goStep(n) {
    for (var i = 1; i <= 3; i++) {
        $('#step' + i).toggle(i === n);
        $('#ws' + i).removeClass('active done');
        if (i < n) $('#ws' + i).addClass('done');
        if (i === n) $('#ws' + i).addClass('active');
    }
    if (n === 3) {
        var p = $('#selTahunGen').val() + ('0' + $('#selBulanGen').val()).slice(-2);
        $('#genPeriode').text(p);
        $('#genStation').text($('#selStationGen').val() || 'Semua');
        $('#genMc').text($('#inpMcGen').val() || 'Semua');
    }
}

function testConfig() {
    $('#btnTestConfig').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Testing...');
    $.ajax({
        url: window.location.pathname + '?ajax=1&action=test_config', type: 'GET', dataType: 'json',
        success: function (r) {
            $('#btnTestConfig').prop('disabled', false).html('<i class="fa fa-stethoscope"></i> Test Konfigurasi');
            var html = '';
            $.each(r.results, function (i, item) {
                var cls = 'status-disabled';
                if (item.status === 'ok') cls = 'status-ok';
                else if (item.status === 'error') cls = 'status-error';
                else if (item.status === 'warning') cls = 'status-warning';
                html += '<tr><td>' + item.key + '</td><td><code>' + item.table + '</code></td><td class="' + cls + '">' + item.status.toUpperCase() + '</td><td>' + item.msg + '</td></tr>';
            });
            $('#configTbody').html(html);
            $('#configResult').show();
            $('#btnToStep2').prop('disabled', false);
        },
        error: function () {
            $('#btnTestConfig').prop('disabled', false).html('<i class="fa fa-stethoscope"></i> Test Konfigurasi');
            alert('AJAX error');
        }
    });
}

function previewWO() {
    var p = $('#selTahunGen').val() + ('0' + $('#selBulanGen').val()).slice(-2);
    var st = $('#selStationGen').val(), mc = $('#inpMcGen').val();
    $('#woTbody').html('<tr><td colspan="15" style="text-align:center;padding:20px"><i class="fa fa-spinner fa-spin"></i> Memuat...</td></tr>');
    $('#previewResult').show();
    $.ajax({
        url: window.location.pathname + '?ajax=1&action=preview_wo', type: 'GET',
        data: {periode: p, station: st, mc_no: mc}, dataType: 'json',
        success: function (r) {
            if (r.status === 'error') { $('#woTbody').html('<tr><td colspan="15" style="color:red">' + r.message + '</td></tr>'); return; }
            $('#woCount').text(r.count);
            if (r.count === 0) { $('#woTbody').html('<tr><td colspan="15" style="text-align:center;color:#999;padding:20px">Tidak ada data WO</td></tr>'); $('#btnToStep3').prop('disabled', true); return; }
            var html = '';
            $.each(r.data, function (i, row) {
                html += '<tr><td>' + (i + 1) + '</td>';
                html += '<td>' + row.wo_no + '</td>';
                html += '<td>' + row.mac_code + '</td>';
                html += '<td>' + row.mag_station + '</td>';
                html += '<td><strong>' + row.part_code + '</strong></td>';
                html += '<td>' + row.part_no + '</td>';
                html += '<td>' + row.cust_comp + '</td>';
                html += '<td style="text-align:right;font-weight:700">' + row.wo_qty + '</td>';
                html += '<td style="text-align:center">' + row.cycle_time + '</td>';
                html += '<td style="text-align:center">' + row.cavity + '</td>';
                html += '<td>' + row.wo_start + '</td>';
                html += '<td>' + row.wo_end + '</td>';
                html += '<td>' + row.sisa + '</td>';
                html += '<td>' + row.status + '</td>';
                html += '</tr>';
            });
            $('#woTbody').html(html);
            $('#btnToStep3').prop('disabled', false);
        },
        error: function () { $('#woTbody').html('<tr><td colspan="15" style="color:red">AJAX error</td></tr>'); }
    });
}

function selectMode(el) {
    $('.mode-card').removeClass('selected');
    $(el).addClass('selected');
    selectedMode = $(el).data('mode');
}

function doGenerate() {
    if (!confirm('Yakin generate dengan mode ' + selectedMode.toUpperCase() + '?')) return;
    var p = $('#selTahunGen').val() + ('0' + $('#selBulanGen').val()).slice(-2);
    var st = $('#selStationGen').val(), mc = $('#inpMcGen').val();
    $('#btnGenerate').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generating...');
    $('#genResult').show();
    $('#genLog').html('<div class="log-line log-warn">[START] Periode=' + p + ' mode=' + selectedMode + '</div>');
    $('#genAlert').removeClass('alert-success alert-danger alert-warning').addClass('alert-info').html('<i class="fa fa-spinner fa-spin"></i> Memproses...');

    $.ajax({
        url: window.location.pathname + '?ajax=1&action=do_generate', type: 'GET',
        data: {periode: p, station: st, mc_no: mc, mode: selectedMode}, dataType: 'json',
        success: function (r) {
            $('#btnGenerate').prop('disabled', false).html('<i class="fa fa-bolt"></i> GENERATE SEKARANG');
            if (r.status === 'error') {
                $('#genAlert').removeClass('alert-info').addClass('alert-danger').html('<i class="fa fa-times-circle"></i> ' + r.message);
                $('#genLog').append('<div class="log-line log-err">[ERROR] ' + r.message + '</div>');
                return;
            }
            var now = new Date().toLocaleTimeString();
            $('#genLog').append('<div class="log-line log-ok">[' + now + '] Total WO: ' + r.total_wo + ' | Sukses: ' + r.success + ' | Error: ' + r.error + ' | Skip: ' + r.skip + '</div>');
            $('#genLog').append('<div class="log-line">---</div>');
            $.each(r.log, function (i, l) {
                var cls = 'log-ok';
                if (l.status === 'error') cls = 'log-err';
                else if (l.status === 'skip') cls = 'log-skip';
                else if (l.status === 'partial') cls = 'log-warn';
                var pfx = l.status === 'ok' ? '[OK]' : l.status === 'error' ? '[ERR]' : l.status === 'skip' ? '[SKIP]' : '[PARTIAL]';
                $('#genLog').append('<div class="log-line ' + cls + '">' + pfx + ' WO:' + l.wo_no + ' MC:' + l.mc + ' Item:' + l.item + ' - ' + l.msg + '</div>');
            });
            $('#genLog').append('<div class="log-line log-ok">[DONE]</div>');
            $('#genLog').scrollTop($('#genLog')[0].scrollHeight);
            if (r.error === 0) {
                $('#genAlert').removeClass('alert-info').addClass('alert-success').html('<i class="fa fa-check-circle"></i> Berhasil: ' + r.success + '/' + r.total_wo + ' WO');
            } else {
                $('#genAlert').removeClass('alert-info').addClass('alert-warning').html('<i class="fa fa-exclamation-triangle"></i> Selesai dengan ' + r.error + ' error');
            }
            $('#btnViewResult').show();
        },
        error: function (xhr) {
            $('#btnGenerate').prop('disabled', false).html('<i class="fa fa-bolt"></i> GENERATE SEKARANG');
            $('#genAlert').removeClass('alert-info').addClass('alert-danger').html('<i class="fa fa-times-circle"></i> Network error');
            $('#genLog').append('<div class="log-line log-err">[NET ERR] ' + xhr.status + '</div>');
        }
    });
}

 $(document).ready(function () { testConfig(); });
</script>
</body>
</html>