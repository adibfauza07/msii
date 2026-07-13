<?php
error_reporting(0);
require_once __DIR__ . "/../config/database_ppic_test.php";

if ($conn === false) { die("Koneksi database gagal."); }

// ============================================
// AJAX: Ambil daftar Item Code
// ============================================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && isset($_GET['action']) && $_GET['action'] === 'get_item_codes') {
    header('Content-Type: application/json; charset=utf-8');
    $bulan_ajax   = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
    $tahun_ajax   = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
    $station_ajax = isset($_GET['station']) ? trim($_GET['station']) : '';
    $mc_ajax      = isset($_GET['mc_no']) ? trim($_GET['mc_no']) : '';
    $periode_ajax = $tahun_ajax . sprintf("%02d", $bulan_ajax);

    $sql_item = "SELECT DISTINCT RPT_PPIC.ITEM_CODE, RPT_PPIC.PART_NAME, RPT_PPIC.PART_NO, RPT_PPIC.CUST
                 FROM RPT_PPIC
                 WHERE RPT_PPIC.periode = ? AND RPT_PPIC.ITEM_CODE IS NOT NULL AND RPT_PPIC.ITEM_CODE <> ''";
    $params_item = array($periode_ajax);

    $sql_ma = "SELECT dbo.MAC.MAC_CODE, dbo.MAG.MAG_STATION
               FROM dbo.MAC INNER JOIN dbo.MAG ON CAST(dbo.MAC.MAG_ID AS NVARCHAR(50)) = CAST(dbo.MAG.MAG_ID AS NVARCHAR(50))
               WHERE dbo.MAC.MAC_ACTIVE = 1";
    $stmt_ma = sqlsrv_query($conn, $sql_ma);
    $list_ma = array();
    if ($stmt_ma !== false) { while ($r = sqlsrv_fetch_array($stmt_ma, SQLSRV_FETCH_ASSOC)) { $list_ma[] = $r; } }

    if ($mc_ajax !== '') {
        $sql_item .= " AND RPT_PPIC.MC_NO = ?"; $params_item[] = $mc_ajax;
    } elseif ($station_ajax !== '') {
        $fmc = array();
        foreach ($list_ma as $msn) {
            if ((isset($msn['MAG_STATION']) ? trim($msn['MAG_STATION']) : '') === $station_ajax) {
                $mc = isset($msn['MAC_CODE']) ? trim($msn['MAC_CODE']) : '';
                if ($mc !== '') $fmc[] = $mc;
            }
        }
        if (count($fmc) > 0) {
            $ph = implode(',', array_fill(0, count($fmc), '?'));
            $sql_item .= " AND RPT_PPIC.MC_NO IN (" . $ph . ")";
            foreach ($fmc as $mc) $params_item[] = $mc;
        } else { $sql_item .= " AND 1=0"; }
    }
    $sql_item .= " ORDER BY RPT_PPIC.ITEM_CODE ASC";
    $stmt_it = sqlsrv_query($conn, $sql_item, $params_item);
    $il = array();
    if ($stmt_it !== false) {
        while ($r = sqlsrv_fetch_array($stmt_it, SQLSRV_FETCH_ASSOC)) {
            $ic = trim($r['ITEM_CODE']);
            if ($ic !== '') $il[] = array('item_code'=>$ic,'part_name'=>trim($r['PART_NAME']),'part_no'=>trim($r['PART_NO']),'cust'=>trim($r['CUST']));
        }
    }
    echo json_encode(array('status'=>'ok','data'=>$il));
    exit;
}

// ============================================
// AJAX: SAVE Prod Plan R0 + VERIFIKASI READ-BACK
// ============================================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && isset($_GET['action']) && $_GET['action'] === 'save_plan') {
    header('Content-Type: application/json; charset=utf-8');
    $detail_id = isset($_POST['detail_id']) ? (int)$_POST['detail_id'] : 0;
    $col_name  = isset($_POST['col_name']) ? trim($_POST['col_name']) : '';
    $value     = isset($_POST['value']) ? trim($_POST['value']) : '';

    if ($detail_id <= 0) {
        echo json_encode(array('status'=>'error','message'=>'Detail ID tidak valid: '.$detail_id));
        exit;
    }
    if (!preg_match('/^D(\d{1,2})$/', $col_name, $m)) {
        echo json_encode(array('status'=>'error','message'=>'Nama kolom tidak valid: '.$col_name));
        exit;
    }

    $day_num = (int)$m[1];
    if ($day_num < 1 || $day_num > 31) {
        echo json_encode(array('status'=>'error','message'=>'Nomor hari tidak valid: '.$day_num));
        exit;
    }

    $save_val = ($value === '') ? NULL : (float)$value;

    $sql_u = "UPDATE RPT_PPIC_DTL SET " . $col_name . " = ? WHERE ID = ?";
    $params_u = array($save_val, $detail_id);
    $stmt_u = sqlsrv_query($conn, $sql_u, $params_u);

    if ($stmt_u === false) {
        $errors = sqlsrv_errors();
        $err_msg = 'SQL Error: ';
        if ($errors) {
            foreach ($errors as $error) { $err_msg .= $error['message'] . ' '; }
        } else { $err_msg .= 'Unknown error'; }
        echo json_encode(array('status'=>'error','message'=>$err_msg));
        exit;
    }

    $rows_affected = sqlsrv_rows_affected($stmt_u);

    // VERIFIKASI READ-BACK
    $sql_v = "SELECT " . $col_name . " AS verified_val FROM RPT_PPIC_DTL WHERE ID = ?";
    $stmt_v = sqlsrv_query($conn, $sql_v, array($detail_id));
    $verified_val = null;
    $verify_success = false;
    if ($stmt_v !== false) {
        $row_v = sqlsrv_fetch_array($stmt_v, SQLSRV_FETCH_ASSOC);
        if ($row_v) { $verified_val = $row_v['verified_val']; $verify_success = true; }
    }

    // Ambil ID_NO
    $sql_idno = "SELECT ID_NO FROM RPT_PPIC_DTL WHERE ID = ?";
    $stmt_idno = sqlsrv_query($conn, $sql_idno, array($detail_id));
    $id_no_ret = '';
    if ($stmt_idno !== false) {
        $row_idno = sqlsrv_fetch_array($stmt_idno, SQLSRV_FETCH_ASSOC);
        if ($row_idno) $id_no_ret = trim($row_idno['ID_NO']);
    }

    echo json_encode(array(
        'status'=>'ok','message'=>'Tersimpan','id_no'=>$id_no_ret,
        'rows_affected'=>$rows_affected,
        'requested_value'=>$save_val,'verified_value'=>$verified_val,'verify_success'=>$verify_success
    ));
    exit;
}

// ============================================
// AJAX: REFRESH semua baris terkait
// ============================================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && isset($_GET['action']) && $_GET['action'] === 'refresh_details') {
    header('Content-Type: application/json; charset=utf-8');
    $id_no = isset($_GET['id_no']) ? trim($_GET['id_no']) : '';
    if ($id_no === '') { echo json_encode(array('status'=>'error','message'=>'ID_NO kosong')); exit; }

    $sql_ref = "SELECT RPT_PPIC_DTL.ID, RPT_PPIC_DTL.DESC_PROD, RPT_PPIC_DTL.G_TOTAL,
                RPT_PPIC_DTL.D1,RPT_PPIC_DTL.D2,RPT_PPIC_DTL.D3,RPT_PPIC_DTL.D4,RPT_PPIC_DTL.D5,
                RPT_PPIC_DTL.D6,RPT_PPIC_DTL.D7,RPT_PPIC_DTL.D8,RPT_PPIC_DTL.D9,RPT_PPIC_DTL.D10,
                RPT_PPIC_DTL.D11,RPT_PPIC_DTL.D12,RPT_PPIC_DTL.D13,RPT_PPIC_DTL.D14,RPT_PPIC_DTL.D15,
                RPT_PPIC_DTL.D16,RPT_PPIC_DTL.D17,RPT_PPIC_DTL.D18,RPT_PPIC_DTL.D19,RPT_PPIC_DTL.D20,
                RPT_PPIC_DTL.D21,RPT_PPIC_DTL.D22,RPT_PPIC_DTL.D23,RPT_PPIC_DTL.D24,RPT_PPIC_DTL.D25,
                RPT_PPIC_DTL.D26,RPT_PPIC_DTL.D27,RPT_PPIC_DTL.D28,RPT_PPIC_DTL.D29,RPT_PPIC_DTL.D30,
                RPT_PPIC_DTL.D31
                FROM RPT_PPIC_DTL WHERE RPT_PPIC_DTL.ID_NO = ? ORDER BY RPT_PPIC_DTL.ID ASC";
    $stmt_ref = sqlsrv_query($conn, $sql_ref, array($id_no));
    $rows = array();
    if ($stmt_ref !== false) {
        while ($r = sqlsrv_fetch_array($stmt_ref, SQLSRV_FETCH_ASSOC)) { $rows[] = $r; }
    }
    echo json_encode(array('status'=>'ok','data'=>$rows));
    exit;
}

// ============================================
// Parameter Input
// ============================================
 $bulan_input     = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
 $tahun_input     = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
 $station_input   = isset($_GET['station']) ? trim($_GET['station']) : '';
 $mc_input        = isset($_GET['mc_no']) ? trim($_GET['mc_no']) : '';
 $item_code_input = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';
 $periode_filter  = $tahun_input . sprintf("%02d", $bulan_input);

// Query Master Mesin
 $sql_mesin = "SELECT dbo.MAC.MAC_ID, dbo.MAC.MAC_CODE, dbo.MAC.MAC_SERIAL, dbo.MAC.MAC_TYPE, 
                     dbo.MAC.MAG_ID, dbo.MAC.MAC_ACTIVE, dbo.MAC.MAC_NO, 
                     dbo.MAG.MAG_ID AS Expr1, dbo.MAG.PROC_ID, dbo.MAG.MAG_STATION, 
                     dbo.MAG.MAG_LOC, dbo.MAG.MAG_PWR, dbo.MAG.MAG_OFC, dbo.MAG.MAG_OVC
              FROM dbo.MAC 
              INNER JOIN dbo.MAG ON CAST(dbo.MAC.MAG_ID AS NVARCHAR(50)) = CAST(dbo.MAG.MAG_ID AS NVARCHAR(50))
              ORDER BY dbo.MAG.MAG_STATION ASC, dbo.MAC.MAC_CODE ASC";
 $stmt_mesin = sqlsrv_query($conn, $sql_mesin);
 $list_mesin = array();
 $list_station = array();
if ($stmt_mesin !== false) {
    while ($r = sqlsrv_fetch_array($stmt_mesin, SQLSRV_FETCH_ASSOC)) {
        $ma = isset($r['MAC_ACTIVE']) ? $r['MAC_ACTIVE'] : '';
        if ($ma === '1' || $ma === 1 || $ma === true) { $list_mesin[] = $r; }
        $ms = isset($r['MAG_STATION']) ? trim($r['MAG_STATION']) : '';
        if ($ms !== '' && !in_array($ms, $list_station)) { $list_station[] = $ms; }
    }
}
natsort($list_station);
 $list_station = array_values($list_station);

 $filtered_mc_no = array();
if ($station_input !== '') {
    foreach ($list_mesin as $msn) {
        if ((isset($msn['MAG_STATION']) ? trim($msn['MAG_STATION']) : '') === $station_input) {
            $mc = isset($msn['MAC_CODE']) ? trim($msn['MAC_CODE']) : '';
            if ($mc !== '') $filtered_mc_no[] = $mc;
        }
    }
}

// Query Data
 $data_report = array();
 $has_plan_r0 = false;
if ($item_code_input !== '') {
    $sql_data = "SELECT RPT_PPIC.ID_NO, RPT_PPIC.MC_NO, RPT_PPIC.MC_CAPACITY, RPT_PPIC.PART_NO, 
                   RPT_PPIC.PART_NAME, RPT_PPIC.ITEM_CODE, RPT_PPIC.CUST, RPT_PPIC.FORECAST_N1, 
                   RPT_PPIC.CUR_PO_BO, RPT_PPIC.SAFETY_STK, RPT_PPIC.BEG_BALANCE, RPT_PPIC.CYCLE_TIME_STD, 
                   RPT_PPIC.CAVITY_STD, RPT_PPIC.CAP_DAY_STD, RPT_PPIC.PROD_PLAN, RPT_PPIC.WORK_DAY, 
                   RPT_PPIC.periode, RPT_PPIC.ppa_qty, RPT_PPIC.no_urut, 
                   RPT_PPIC_DTL.ID_NO AS Expr1, RPT_PPIC_DTL.DESC_PROD, RPT_PPIC_DTL.G_TOTAL, 
                   RPT_PPIC_DTL.D1,RPT_PPIC_DTL.D2,RPT_PPIC_DTL.D3,RPT_PPIC_DTL.D4,RPT_PPIC_DTL.D5,
                   RPT_PPIC_DTL.D6,RPT_PPIC_DTL.D7,RPT_PPIC_DTL.D8,RPT_PPIC_DTL.D9,RPT_PPIC_DTL.D10,
                   RPT_PPIC_DTL.D11,RPT_PPIC_DTL.D12,RPT_PPIC_DTL.D13,RPT_PPIC_DTL.D14,RPT_PPIC_DTL.D15,
                   RPT_PPIC_DTL.D16,RPT_PPIC_DTL.D17,RPT_PPIC_DTL.D18,RPT_PPIC_DTL.D19,RPT_PPIC_DTL.D20,
                   RPT_PPIC_DTL.D21,RPT_PPIC_DTL.D22,RPT_PPIC_DTL.D23,RPT_PPIC_DTL.D24,RPT_PPIC_DTL.D25,
                   RPT_PPIC_DTL.D26,RPT_PPIC_DTL.D27,RPT_PPIC_DTL.D28,RPT_PPIC_DTL.D29,RPT_PPIC_DTL.D30,
                   RPT_PPIC_DTL.D31, RPT_PPIC_DTL.TTL_PRC, RPT_PPIC_DTL.STD_MCD, RPT_PPIC_DTL.ACT_MCD, 
                   RPT_PPIC_DTL.BAL_TIME, RPT_PPIC_DTL.RATE_MC, RPT_PPIC_DTL.TIME_AMT, RPT_PPIC_DTL.EFF, 
                   RPT_PPIC_DTL.M1,RPT_PPIC_DTL.M2,RPT_PPIC_DTL.M3,RPT_PPIC_DTL.M4,RPT_PPIC_DTL.M5, 
                   RPT_PPIC_DTL.ROW_NAME, RPT_PPIC_DTL.M0, RPT_PPIC_DTL.ID
            FROM RPT_PPIC INNER JOIN RPT_PPIC_DTL ON RPT_PPIC.ID_NO = RPT_PPIC_DTL.ID_NO
            WHERE RPT_PPIC.periode = ? AND RPT_PPIC.ITEM_CODE = ?";
    $params = array($periode_filter, $item_code_input);
    if ($mc_input !== '') {
        $sql_data .= " AND RPT_PPIC.MC_NO = ?"; $params[] = $mc_input;
    } elseif (count($filtered_mc_no) > 0) {
        $ph = implode(',', array_fill(0, count($filtered_mc_no), '?'));
        $sql_data .= " AND RPT_PPIC.MC_NO IN (" . $ph . ")";
        foreach ($filtered_mc_no as $mc) $params[] = $mc;
    } elseif ($station_input !== '') {
        $sql_data .= " AND 1=0 ";
    }
    $sql_data .= " ORDER BY RPT_PPIC.MC_NO ASC, RPT_PPIC_DTL.ID ASC";
    $stmt_data = sqlsrv_query($conn, $sql_data, $params);
    if ($stmt_data === false) {
        echo "<div style='padding:20px;background:#fee;border:2px solid #f00;margin-bottom:20px;'>";
        echo "<h3 style='color:red;'>Query Error:</h3><pre>" . print_r(sqlsrv_errors(), true) . "</pre></div>";
    } else {
        while ($row = sqlsrv_fetch_array($stmt_data, SQLSRV_FETCH_ASSOC)) {
            $id_no = isset($row['ID_NO']) ? trim($row['ID_NO']) : '';
            if ($id_no !== '' && !isset($data_report[$id_no])) {
                $data_report[$id_no] = array('header' => $row, 'details' => array());
            }
            if ($id_no !== '') {
                $dp = isset($row['DESC_PROD']) ? trim($row['DESC_PROD']) : '';
                $data_report[$id_no]['details'][$dp] = $row;
                if (preg_match('/prod\s*plan\s*r0/i', $dp)) $has_plan_r0 = true;
            }
        }
    }
}

function fmt_num($val) {
    if ($val === null || $val === "" || (float)$val == 0) return "-";
    return number_format((float)$val, 0, ".", ",");
}

 $nama_bulan = array(1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember');
 $hari_list = array();
for ($i = 1; $i <= 31; $i++) { if (checkdate($bulan_input, $i, $tahun_input)) $hari_list[] = $i; }
 $json_mesin = json_encode($list_mesin);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Production Schedule</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        @page{size:A3 landscape;margin:5mm}
        body{font-family:"Courier New",monospace;font-size:10px;color:#000;background:#f5f5f5;padding:10px}
        .panel-filter{box-shadow:0 2px 10px rgba(0,0,0,.15);border:none;margin-bottom:15px}
        .panel-filter .panel-heading{background:linear-gradient(135deg,#337ab7,#286090);color:#fff;font-family:'Segoe UI',Arial,sans-serif;font-size:14px;font-weight:600;padding:10px 15px}
        .filter-form label{font-weight:600;color:#333;font-size:12px;margin-bottom:3px;display:block}
        .filter-form select{font-size:12px;height:34px}
        .header-title{text-align:center;font-family:'Segoe UI',Arial,sans-serif;font-size:22px;font-weight:bold;margin:10px 0 5px;color:#333}
        .header-subtitle{text-align:center;font-family:'Segoe UI',Arial,sans-serif;font-size:12px;color:#666;margin-bottom:15px}
        .schedule-wrapper{display:flex;background:#fff;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.2);border:1px solid #ccc}
        .left-panel{width:240px;flex-shrink:0;background:#f8f9fa;overflow:hidden}
        .right-panel{flex-grow:1;overflow-x:auto;overflow-y:hidden;position:relative}
        .table-left-vb6{margin-bottom:0!important;font-size:9px}
        .table-right-vb6{margin-bottom:0!important;font-size:9px;border:2px solid #d9534f!important}
        .table-left-vb6>thead>tr>th,.table-right-vb6>thead>tr>th{background-color:#337ab7!important;color:#fff!important;font-weight:bold;padding:6px 5px;border:1px solid #286090!important;font-size:9px}
        .table-left-vb6>tbody>tr>td,.table-right-vb6>tbody>tr>td{padding:4px 5px;border:1px solid #ddd!important;vertical-align:top}
        .header-left-vb6{width:240px;text-align:center}
        .col-desc-vb6{width:120px;min-width:120px;text-align:left!important;padding-left:5px!important;background:#eef2f7!important;font-weight:bold;border-right:2px solid #bbb!important}
        .col-gtotal-vb6{width:55px;min-width:55px;text-align:center;background:#d9e2f3!important;font-weight:bold;border-right:2px solid #999!important;color:#000}
        .col-day-vb6{width:45px;min-width:45px;text-align:center}
        .info-panel-vb6{padding:0!important;background:#f8f9fa!important;vertical-align:top!important}
        .mc-title-vb6{background:#d9534f;color:#fff;text-align:center;padding:5px;font-size:10px;font-weight:bold;letter-spacing:1px;margin-bottom:8px}
        .panel-inner-vb6{padding:0 8px 8px}
        .info-label{display:block;font-size:8px;color:#777;font-weight:bold;text-transform:uppercase;margin-bottom:1px;margin-top:5px}
        .info-val{display:block;font-size:10px;font-weight:bold;color:#333;margin-bottom:2px;word-wrap:break-word}
        .item-code-big{color:#d9534f!important;font-size:11px!important;border:1px solid #d9534f;padding:2px 4px;background:#ffeaea;border-radius:3px;display:inline-block!important}
        .info-separator{border:none;border-top:1px dashed #ccc;margin:6px 0}
        .row-master{background-color:#d9e2f3!important;font-weight:bold}
        .row-prod{background-color:#e2efda!important;font-weight:bold}
        .row-plan-r0{background-color:#e2efda!important;font-weight:bold}
        .row-normal{background-color:#fff!important}
        .row-total{background-color:#fff2cc!important;font-weight:bold}
        .cell-highlight{background-color:#e8f4ea!important}
        .badge-filter{display:inline-block;background:#d9534f;color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;margin-left:5px;font-weight:600}
        select:disabled{background-color:#eee!important;cursor:not-allowed}
        .step-num{display:inline-block;width:22px;height:22px;line-height:22px;text-align:center;border-radius:50%;background:#ccc;color:#fff;font-size:12px;font-weight:bold;margin-right:5px;vertical-align:middle}
        .step-num.active{background:#f0ad4e}
        .step-num.done{background:#5cb85c}

        .cell-editable{padding:0!important;background-color:#e2efda!important}
        .plan-input{width:100%;height:22px;border:2px solid transparent;background:transparent;text-align:right;font-family:"Courier New",monospace;font-size:9px;font-weight:bold;color:#1a5e1a;padding:0 3px;margin:0;outline:none;box-sizing:border-box;transition:all .12s ease;display:block}
        .plan-input:hover{background:#d5efc8;border-color:#8cc07a}
        .plan-input:focus{background:#c8e6b0;border-color:#2e7d32;box-shadow:0 0 0 2px rgba(46,125,50,.25);z-index:10;position:relative;color:#000}
        .plan-input.is-saving{border-color:#f0ad4e!important;background:#fff8e1!important}
        .plan-input.is-saved{border-color:#4caf50!important;background:#e8f5e9!important}
        .plan-input.is-error{border-color:#f44336!important;background:#ffebee!important;animation:shake .3s ease}
        .plan-input.is-reverted{border-color:#ff9800!important;background:#fff3e0!important;animation:shake .4s ease}
        @keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-3px)}75%{transform:translateX(3px)}}
        .row-plan-r0 .col-desc-vb6{background:#c8e6b0!important}
        .row-plan-r0 .col-desc-vb6::before{content:'\f040';font-family:'FontAwesome';font-size:8px;color:#2e7d32;margin-right:4px}

        .refresh-overlay{position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,.6);z-index:50;display:none;align-items:center;justify-content:center;flex-direction:column;gap:8px}
        .refresh-overlay.active{display:flex}
        .refresh-overlay i{font-size:24px;color:#337ab7;animation:spin .8s linear infinite}
        .refresh-overlay span{font-family:'Segoe UI',Arial,sans-serif;font-size:12px;color:#555;font-weight:600}
        @keyframes spin{to{transform:rotate(360deg)}}

        .cell-updated{animation:cellFlash .6s ease}
        @keyframes cellFlash{0%{background:#fff9c4}100%{background:transparent}}

        .edit-hint{background:linear-gradient(90deg,#e8f5e9,#f1f8e9);border:1px solid #a5d6a7;border-radius:6px;padding:8px 15px;margin-bottom:10px;font-family:'Segoe UI',Arial,sans-serif;font-size:12px;color:#2e7d32;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
        .edit-hint kbd{display:inline-block;background:#fff;border:1px solid #bbb;border-radius:4px;padding:1px 6px;font-size:11px;font-family:"Courier New",monospace;color:#333;box-shadow:0 1px 2px rgba(0,0,0,.1);margin:0 1px}
        .edit-hint .divider{color:#a5d6a7;margin:0 4px}

        .save-toast{position:fixed;bottom:25px;right:25px;padding:10px 20px;border-radius:8px;font-family:'Segoe UI',Arial,sans-serif;font-size:13px;font-weight:600;z-index:99999;box-shadow:0 4px 15px rgba(0,0,0,.25);display:none;align-items:center;gap:8px;max-width:450px}
        .save-toast.success{background:#4caf50;color:#fff}
        .save-toast.error{background:#f44336;color:#fff}
        .save-toast.info{background:#2196f3;color:#fff}
        .save-toast.warning{background:#ff9800;color:#fff}

        .debug-panel{display:none;position:fixed;bottom:80px;right:25px;background:#263238;color:#aed581;font-family:'Courier New',monospace;font-size:11px;padding:12px 16px;border-radius:8px;z-index:99998;max-width:500px;max-height:250px;overflow-y:auto;box-shadow:0 4px 15px rgba(0,0,0,.4)}
        .debug-panel.show{display:block}
        .debug-panel .dbg-title{color:#80cbc4;font-weight:bold;margin-bottom:6px;font-size:12px}
        .debug-panel .dbg-line{margin:2px 0;word-wrap:break-word}
        .debug-panel .dbg-err{color:#ef9a9a}
        .debug-panel .dbg-ok{color:#a5d6a7}

        .placeholder-box{display:flex;align-items:center;justify-content:center;min-height:300px;background:#fff;border:2px dashed #ccc;border-radius:8px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.08);text-align:center;padding:40px}
        .placeholder-box i{font-size:60px;color:#ccc;margin-bottom:15px;display:block}
        .placeholder-box h3{color:#999;font-family:'Segoe UI',Arial,sans-serif;font-weight:600;margin:0 0 8px}
        .placeholder-box p{color:#bbb;font-family:'Segoe UI',Arial,sans-serif;font-size:13px;margin:0}
        .placeholder-box .step-flow{margin-top:20px;display:inline-flex;align-items:center;gap:5px}
        .placeholder-box .step-flow .sf-item{display:inline-flex;align-items:center;gap:4px;background:#f0f0f0;border:1px solid #ddd;border-radius:15px;padding:4px 12px;font-family:'Segoe UI',Arial,sans-serif;font-size:11px;color:#999}
        .placeholder-box .step-flow .sf-item.sf-active{background:#337ab7;color:#fff;border-color:#286090}
        .placeholder-box .step-flow .sf-arrow{color:#ccc;font-size:14px}

        @media print{
            .no-print{display:none!important}
            .schedule-wrapper{overflow:visible;border:1px solid #000}
            .left-panel,.right-panel{overflow:visible;width:auto}
            .table-right-vb6{border:1px solid #000!important}
            body{margin:0;padding:0;background:#fff}
            .header-title{font-size:18px;margin-top:5px}
            .col-day-vb6{width:30px!important;min-width:30px!important}
            .plan-input{border:none!important;background:transparent!important}
        }
    </style>
</head>
<body>

<!-- PANEL FILTER -->
<div class="panel panel-primary panel-filter no-print">
    <div class="panel-heading"><i class="fa fa-filter"></i> Filter Production Schedule</div>
    <div class="panel-body">
        <form method="GET" action="" class="form-horizontal filter-form" id="filterForm">
            <div class="row">
                <div class="col-md-2 col-sm-3 col-xs-6">
                    <label><i class="fa fa-calendar"></i> Tahun</label>
                    <select name="tahun" id="selTahun" class="form-control input-sm">
                        <?php for($y=date('Y')-2;$y<=date('Y')+2;$y++){echo"<option value='".$y."'".($y==$tahun_input?" selected":"").">".$y."</option>";} ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-3 col-xs-6">
                    <label><i class="fa fa-calendar-o"></i> Bulan</label>
                    <select name="bulan" id="selBulan" class="form-control input-sm">
                        <?php foreach($nama_bulan as $m=>$nm){echo"<option value='".$m."'".($m==$bulan_input?" selected":"").">".$nm."</option>";} ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-4 col-xs-12">
                    <label><span class="step-num active" id="stepStation">1</span><i class="fa fa-cog"></i> Tonase</label>
                    <select name="station" id="selStation" class="form-control input-sm">
                        <option value="">-- Pilih Tonase --</option>
                        <?php foreach($list_station as $stn){echo"<option value='".htmlspecialchars($stn)."'".($stn==$station_input?" selected":"").">".htmlspecialchars($stn)."</option>";} ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-4 col-xs-12">
                    <label><span class="step-num" id="stepMesin">2</span><i class="fa fa-industry"></i> Mesin</label>
                    <select name="mc_no" id="selMesin" class="form-control input-sm" disabled>
                        <option value="">-- Pilih Tonase Dulu --</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-4 col-xs-12">
                    <label><span class="step-num" id="stepItem">3</span><i class="fa fa-barcode"></i> Item Code</label>
                    <select name="item_code" id="selItemCode" class="form-control input-sm" disabled>
                        <option value="">-- Pilih Mesin Dulu --</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 col-xs-12">
                    <label>&nbsp;</label>
                    <div class="btn-group btn-group-justified">
                        <button type="submit" class="btn btn-primary btn-sm" id="btnTampil" disabled><i class="fa fa-search"></i> Tampil</button>
                        <button type="button" class="btn btn-success btn-sm" onclick="window.print()" id="btnPrint" disabled><i class="fa fa-print"></i> Print</button>
                    </div>
                </div>
            </div>
            <div class="row" style="margin-top:8px">
                <div class="col-md-12">
                    <small class="text-muted">
                        <i class="fa fa-info-circle"></i>
                        <?php if(count($data_report)>0):?>Total: <strong><?php echo count($data_report);?></strong> mesin<?php endif;?>
                        <?php if($station_input!==''):?><span class="badge-filter">Station: <?php echo htmlspecialchars($station_input);?></span><?php endif;?>
                        <?php if($mc_input!==''):?><span class="badge-filter">Mesin: <?php echo htmlspecialchars($mc_input);?></span><?php endif;?>
                        <?php if($item_code_input!==''):?><span class="badge-filter">Item: <?php echo htmlspecialchars($item_code_input);?></span><?php endif;?>
                        <a href="?" class="btn btn-xs btn-default" style="margin-left:10px"><i class="fa fa-refresh"></i> Reset</a>
                        <button type="button" class="btn btn-xs btn-default" id="btnDebug" style="margin-left:5px"><i class="fa fa-bug"></i> Debug</button>
                    </small>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="header-title">PRODUCTION SCHEDULE</div>
<div class="header-subtitle">
    Periode: <?php echo strtoupper($nama_bulan[$bulan_input])." ".$tahun_input;?>
    <?php if($station_input!==''):?> | Station: <?php echo htmlspecialchars($station_input);?><?php endif;?>
    <?php if($mc_input!==''):?> | Mesin: <?php echo htmlspecialchars($mc_input);?><?php endif;?>
    <?php if($item_code_input!==''):?> | Item Code: <?php echo htmlspecialchars($item_code_input);?><?php endif;?>
</div>

<?php if(count($data_report)>0):?>
<?php if($has_plan_r0):?>
<div class="edit-hint no-print">
    <i class="fa fa-pencil-square-o" style="font-size:16px"></i>
    <strong>Prod Plan R0 editable</strong>
    <span class="divider">|</span>
    <kbd>&larr;</kbd><kbd>&rarr;</kbd><kbd>&uarr;</kbd><kbd>&darr;</kbd> Navigasi
    <span class="divider">|</span>
    <kbd>Tab</kbd>/<kbd>Enter</kbd> Next
    <span class="divider">|</span>
    <kbd>Esc</kbd> Batal
    <span class="divider">|</span>
    <i class="fa fa-calculator"></i> Auto-calculate + Verify save
</div>
<?php endif;?>

<div class="schedule-wrapper">
    <div class="left-panel">
        <table class="table table-bordered table-condensed table-left-vb6">
            <thead><tr><th class="header-left-vb6">MACHINE INFORMATION</th></tr></thead>
            <tbody>
            <?php
            foreach($data_report as $id_no=>$data){
                $hdr=$data['header']; $details=$data['details'];
                $jml_detail=count($details); if($jml_detail==0)$jml_detail=1;
                $mc_no_hdr=isset($hdr['MC_NO'])?$hdr['MC_NO']:'-';
                $part_name_hdr=isset($hdr['PART_NAME'])?$hdr['PART_NAME']:'-';
                $item_code_hdr=isset($hdr['ITEM_CODE'])?strtoupper(trim($hdr['ITEM_CODE'])):'-';
                $cust_hdr=isset($hdr['CUST'])?$hdr['CUST']:'-';
                $beg_bal_hdr=isset($hdr['BEG_BALANCE'])?$hdr['BEG_BALANCE']:null;
                $safety_hdr=isset($hdr['SAFETY_STK'])?$hdr['SAFETY_STK']:null;
                $forecast_hdr=isset($hdr['FORECAST_N1'])?$hdr['FORECAST_N1']:null;
                $po_hdr=isset($hdr['CUR_PO_BO'])?$hdr['CUR_PO_BO']:null;
                $cap_day_hdr=isset($hdr['CAP_DAY_STD'])?$hdr['CAP_DAY_STD']:null;
                $cycle_hdr=isset($hdr['CYCLE_TIME_STD'])?$hdr['CYCLE_TIME_STD']:null;
                $cavity_hdr=isset($hdr['CAVITY_STD'])?$hdr['CAVITY_STD']:null;
                $work_day_hdr=isset($hdr['WORK_DAY'])?$hdr['WORK_DAY']:null;
                $mac_type='-';
                if(!empty($list_mesin)){foreach($list_mesin as $msn){if((isset($msn['MAC_CODE'])?trim($msn['MAC_CODE']):'')===trim($mc_no_hdr)){$mac_type=isset($msn['MAC_TYPE'])?trim($msn['MAC_TYPE']):'-';break;}}}
                $first_row=true;
                foreach($details as $desc=>$dtl){
                    echo"<tr>";
                    if($first_row){
                        echo"<td class='info-panel-vb6' rowspan='".$jml_detail."'>";
                        echo"<div class='mc-title-vb6'>MACHINE INFORMATION</div><div class='panel-inner-vb6'>";
                        echo"<span class='info-label'>Machine No</span><span class='info-val'>".htmlspecialchars($mc_no_hdr)."</span>";
                        echo"<span class='info-label'>Machine Type</span><span class='info-val'>".htmlspecialchars($mac_type)."</span>";
                        echo"<span class='info-label'>Part Name</span><span class='info-val'>".htmlspecialchars($part_name_hdr)."</span>";
                        echo"<span class='info-label'>Item Code</span><span class='info-val item-code-big'>".htmlspecialchars($item_code_hdr)."</span>";
                        echo"<span class='info-label'>Customer</span><span class='info-val'>".htmlspecialchars($cust_hdr)."</span>";
                        echo"<hr class='info-separator'>";
                        echo"<span class='info-label'>Forecast N+1</span><span class='info-val'>".fmt_num($forecast_hdr)."</span>";
                        echo"<span class='info-label'>Safety Stock</span><span class='info-val'>".fmt_num($safety_hdr)."</span>";
                        echo"<span class='info-label'>Beg Balance</span><span class='info-val'>".fmt_num($beg_bal_hdr)."</span>";
                        echo"<span class='info-label'>Cur PO / BO</span><span class='info-val'>".fmt_num($po_hdr)."</span>";
                        echo"<hr class='info-separator'>";
                        echo"<span class='info-label'>Cap/Day</span><span class='info-val'>".fmt_num($cap_day_hdr)."</span>";
                        echo"<span class='info-label'>Cycle Time</span><span class='info-val'>".fmt_num($cycle_hdr)."</span>";
                        echo"<span class='info-label'>Cavity</span><span class='info-val'>".fmt_num($cavity_hdr)."</span>";
                        echo"<span class='info-label'>Work Day</span><span class='info-val'>".fmt_num($work_day_hdr)."</span>";
                        echo"</div></td>"; $first_row=false;
                    }
                    echo"</tr>";
                }
            }
            ?>
            </tbody>
        </table>
    </div>

    <div class="right-panel" id="rightPanel">
        <div class="refresh-overlay" id="refreshOverlay"><i class="fa fa-spinner"></i><span>Menghitung ulang...</span></div>
        <table class="table table-bordered table-condensed table-right-vb6">
            <thead>
                <tr>
                    <th class="col-desc-vb6">Description</th>
                    <th class="col-gtotal-vb6">Grand Total</th>
                    <?php foreach($hari_list as $h):?><th class="col-day-vb6"><?php echo sprintf("%02d",$h);?></th><?php endforeach;?>
                </tr>
            </thead>
            <tbody id="tableBody">
            <?php
            $plan_r0_row_idx=0;
            foreach($data_report as $id_no=>$data){
                $details=$data['details'];
                foreach($details as $desc=>$dtl){
                    $is_plan_r0=preg_match('/prod\s*plan\s*r0/i',$desc);
                    $dtl_id=isset($dtl['ID'])?(int)$dtl['ID']:0;

                    if($is_plan_r0) $row_class="row-plan-r0";
                    elseif(stripos($desc,'Plan')!==false) $row_class="row-prod";
                    elseif(stripos($desc,'Total')!==false||stripos($desc,'Grand')!==false) $row_class="row-total";
                    else $row_class="row-normal";

                    // Tandai R0 row dengan data-is-r0
                    $r0_attr = $is_plan_r0 ? ' data-is-r0="1" data-r0-row="'.$plan_r0_row_idx.'"' : '';

                    echo"<tr class='".$row_class."' data-dtl-id='".$dtl_id."' data-id-no='".htmlspecialchars($id_no)."'".$r0_attr.">";
                    echo"<td class='col-desc-vb6'>".htmlspecialchars($desc)."</td>";

                    $g_total=isset($dtl['G_TOTAL'])?$dtl['G_TOTAL']:0;
                    // Untuk R0: jangan tampilkan G_TOTAL dari DB (mungkin NULL), biarkan JS hitung
                    if($is_plan_r0){
                        echo"<td class='col-gtotal-vb6 gtotal-cell r0-gtotal' data-r0-row='".$plan_r0_row_idx."'>-</td>";
                    } else {
                        echo"<td class='col-gtotal-vb6 gtotal-cell'>".fmt_num($g_total)."</td>";
                    }

                    $col_idx=0;
                    foreach($hari_list as $h){
                        $nama_kolom="D".$h;
                        $nilai=isset($dtl[$nama_kolom])?$dtl[$nama_kolom]:null;
                        if($is_plan_r0){
                            $iv=($nilai!==null&&$nilai!==''&&(float)$nilai!=0)?(float)$nilai:'';
                            echo"<td class='col-day-vb6 cell-editable' data-day='".$h."'>";
                            echo"<input type='text' class='plan-input' data-detail-id='".$dtl_id."' data-col='".$nama_kolom."' data-row='".$plan_r0_row_idx."' data-col-idx='".$col_idx."' data-id-no='".htmlspecialchars($id_no)."' value='".$iv."'>";
                            echo"</td>";
                        } else {
                            $cc=($nilai&&(float)$nilai>0)?"cell-highlight":"";
                            echo"<td class='col-day-vb6 ".$cc." day-cell' data-day='".$h."'>".fmt_num($nilai)."</td>";
                        }
                        $col_idx++;
                    }
                    echo"</tr>";
                    if($is_plan_r0) $plan_r0_row_idx++;
                }
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<?php else:?>
<div class="placeholder-box no-print">
    <div>
        <i class="fa fa-search"></i>
        <h3>Pilih Item Code untuk Menampilkan Data</h3>
        <p>Data tidak ditampilkan sebelum Item Code dipilih.</p>
        <div class="step-flow">
            <span class="sf-item <?php echo $station_input!==''?'sf-active':'';?>"><i class="fa fa-cog"></i> 1. Tonase</span>
            <span class="sf-arrow"><i class="fa fa-chevron-right"></i></span>
            <span class="sf-item <?php echo $mc_input!==''?'sf-active':'';?>"><i class="fa fa-industry"></i> 2. Mesin</span>
            <span class="sf-arrow"><i class="fa fa-chevron-right"></i></span>
            <span class="sf-item <?php echo $item_code_input!==''?'sf-active':'';?>"><i class="fa fa-barcode"></i> 3. Item Code</span>
            <span class="sf-arrow"><i class="fa fa-chevron-right"></i></span>
            <span class="sf-item"><i class="fa fa-table"></i> 4. Tampil</span>
        </div>
    </div>
</div>
<?php endif;?>

<div id="saveToast" class="save-toast"><span id="toastIcon"></span><span id="saveToastMsg"></span></div>

<!-- DEBUG PANEL -->
<div id="debugPanel" class="debug-panel">
    <div class="dbg-title"><i class="fa fa-bug"></i> DEBUG LOG <button type="button" onclick="$('#debugContent').html('')" style="float:right;background:none;border:none;color:#ef9a9a;cursor:pointer;font-size:14px">&times;</button></div>
    <div id="debugContent"></div>
</div>

<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script>
var listMesin=<?php echo $json_mesin;?>;
var currentStation='<?php echo addslashes($station_input);?>';
var currentMesin='<?php echo addslashes($mc_input);?>';
var currentItemCode='<?php echo addslashes($item_code_input);?>';
var ajaxTimer=null;
var saveTimers={};
var isRefreshing=false;
var debugMode=false;

// ===================== DEBUG =====================
function dbgLog(msg,isError){
    if(!debugMode)return;
    var cls=isError?'dbg-err':'dbg-ok';
    var ts=new Date().toLocaleTimeString();
    $('#debugContent').prepend('<div class="dbg-line '+cls+'">['+ts+'] '+msg+'</div>');
}
 $('#btnDebug').on('click',function(){
    debugMode=!debugMode;
    $('#debugPanel').toggleClass('show',debugMode);
    $(this).toggleClass('btn-danger',debugMode).toggleClass('btn-default',!debugMode);
});

// ===================== FORMAT =====================
function fmtN(v){if(v===0||isNaN(v))return"-";return v.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g,",");}

// ============================================================
// TOTAL R0: SELALU DIHITUNG CLIENT-SIDE, JANGAN PERNAH PAKE SERVER
// ============================================================
function recalcR0Total(rowIdx){
    var sum=0;
    var $inputs=$('.plan-input[data-row="'+rowIdx+'"]');
    $inputs.each(function(){sum+=parseFloat($(this).val())||0;});
    // Update cell G_TOTAL untuk row ini
    var $gt=$('.r0-gtotal[data-r0-row="'+rowIdx+'"]');
    $gt.text(fmtN(sum));
    dbgLog('R0 Total row '+rowIdx+' = '+sum+' ('+$inputs.length+' inputs)',false);
}

// Hitung SEMUA R0 total (dipanggil saat page load & setelah refresh)
function recalcAllR0Totals(){
    var rowIndices=[];
    $('.plan-input').each(function(){
        var r=parseInt($(this).data('row'));
        if(rowIndices.indexOf(r)===-1) rowIndices.push(r);
    });
    dbgLog('initAllR0Totals: '+rowIndices.length+' R0 rows found',false);
    for(var i=0;i<rowIndices.length;i++){
        recalcR0Total(rowIndices[i]);
    }
}

// ===================== STEP 1: Station =====================
 $('#selStation').on('change',function(){
    var station=$(this).val();
    var $sm=$('#selMesin'),$si=$('#selItemCode'),$stm=$('#stepMesin'),$sti=$('#stepItem'),$btn=$('#btnTampil');
    $sm.html('<option value="">-- Pilih Tonase Dulu --</option>').prop('disabled',true);
    $si.html('<option value="">-- Pilih Mesin Dulu --</option>').prop('disabled',true);
    $stm.removeClass('active done');$sti.removeClass('active done');$btn.prop('disabled',true);
    $('#sfStep1').toggleClass('sf-active',station!=='');$('#sfStep2,#sfStep3,#sfStep4').removeClass('sf-active');
    if(station==='')return;
    $sm.prop('disabled',false);$stm.addClass('active');
    $sm.html('<option value="">-- Semua Mesin '+station+' --</option>');
    var c=0;
    $.each(listMesin,function(i,m){
        if((m.MAG_STATION||'').trim()===station){
            var mc=(m.MAC_CODE||'').trim(),ms=(m.MAC_SERIAL||'').trim(),mt=(m.MAC_TYPE||'').trim();
            if(mc!==''){$sm.append('<option value="'+mc+'">'+mc+' - '+ms+' ('+mt+')</option>');c++;}
        }
    });
    if(c>0){$stm.removeClass('active').addClass('done');$('#sfStep2').addClass('sf-active');}
    if(c===0)$sm.html('<option value="">-- Tidak ada mesin --</option>').prop('disabled',true);
});

// ===================== STEP 2: AJAX Item Code =====================
function loadItemCodes(){
    var station=$('#selStation').val(),mc=$('#selMesin').val(),bl=$('#selBulan').val(),th=$('#selTahun').val();
    var $si=$('#selItemCode'),$sti=$('#stepItem'),$btn=$('#btnTampil');
    $si.html('<option value="">-- Memuat... --</option>');$sti.removeClass('active done');$btn.prop('disabled',true);
    $('#sfStep3,#sfStep4').removeClass('sf-active');
    if(station===''){$si.html('<option value="">-- Pilih Tonase Dulu --</option>').prop('disabled',true);return;}
    $si.prop('disabled',false);$sti.addClass('active');
    if(ajaxTimer)clearTimeout(ajaxTimer);
    ajaxTimer=setTimeout(function(){
        $.ajax({url:window.location.pathname,type:'GET',data:{ajax:'1',action:'get_item_codes',tahun:th,bulan:bl,station:station,mc_no:mc},dataType:'json',
            success:function(r){
                $si.html('');
                if(r.status==='ok'&&r.data&&r.data.length>0){
                    $si.append('<option value="">-- Pilih Item Code --</option>');
                    $.each(r.data,function(i,it){var l=it.item_code;if(it.part_name)l+=' - '+it.part_name;if(it.cust)l+=' ['+it.cust+']';$si.append('<option value="'+it.item_code+'">'+l+'</option>');});
                    $sti.removeClass('active').addClass('done');$('#sfStep3').addClass('sf-active');
                }else{$si.html('<option value="">-- Tidak ada Item Code --</option>').prop('disabled',true);$sti.removeClass('active');}
            },
            error:function(){$si.html('<option value="">-- Gagal --</option>').prop('disabled',true);$sti.removeClass('active');}
        });
    },300);
}
 $('#selMesin').on('change',loadItemCodes);
 $('#selBulan,#selTahun').on('change',function(){if($('#selStation').val()!=='')$('#selStation').trigger('change');});

// ===================== STEP 3 =====================
 $('#selItemCode').on('change',function(){var v=$(this).val();$('#btnTampil').prop('disabled',v==='');$('#sfStep4').toggleClass('sf-active',v!=='');});

// ===================== TOAST =====================
var toastTimer=null;
function showToast(msg,type){
    var $t=$('#saveToast');$t.removeClass('success error info warning');
    $t.addClass(type||'success');
    var icons={success:'fa-check-circle',error:'fa-times-circle',info:'fa-refresh',warning:'fa-exclamation-triangle'};
    $('#toastIcon').html('<i class="fa '+(icons[type]||icons.success)+'"></i>');
    $('#saveToastMsg').text(msg);
    $t.stop(true).fadeIn(200);
    if(toastTimer)clearTimeout(toastTimer);
    toastTimer=setTimeout(function(){$t.fadeOut(400);},3500);
}

// ===================== SAVE =====================
function doSave($input){
    var did=$input.data('detail-id'),col=$input.data('col'),val=$input.val(),row=$input.data('row'),idNo=$input.data('id-no');
    if(val===$input.data('original'))return;
    $input.removeClass('is-saved is-error is-reverted').addClass('is-saving');
    dbgLog('SAVE: ID='+did+' '+col+'="'+val+'"',false);

    $.ajax({
        url:window.location.pathname+'?ajax=1&action=save_plan',type:'POST',
        data:{detail_id:did,col_name:col,value:val},dataType:'json',
        success:function(resp){
            $input.removeClass('is-saving');
            if(resp.status==='ok'){
                var requested=(resp.requested_value!==null&&resp.requested_value!=='')?parseFloat(resp.requested_value):0;
                var verified=(resp.verified_value!==null&&resp.verified_value!=='')?parseFloat(resp.verified_value):0;
                var rowsAffected=resp.rows_affected||0;
                dbgLog('SAVE OK: affected='+rowsAffected+' req='+requested+' ver='+verified,false);

                if(resp.verify_success && Math.abs(requested-verified)>0.001){
                    dbgLog('WARN: Server mengubah '+requested+' -> '+verified,true);
                    var dv=verified!==0?verified:'';
                    $input.val(dv);$input.data('original',String(dv));
                    $input.addClass('is-reverted');
                    setTimeout(function(){$input.removeClass('is-reverted');},3000);
                    showToast('PERHATIAN: '+col+' diubah server dari '+requested+' jadi '+verified,'warning');
                } else if(rowsAffected===0){
                    dbgLog('WARN: 0 rows affected!',true);
                    $input.addClass('is-error');
                    setTimeout(function(){$input.removeClass('is-error');},3000);
                    showToast('Gagal: 0 row terpengaruh, ID='+did+' tidak ditemukan','error');
                    return;
                } else {
                    $input.addClass('is-saved');$input.data('original',val);
                    setTimeout(function(){$input.removeClass('is-saved');},1000);
                    showToast(col.replace('D','Day ')+' = '+(val||'0')+' tersimpan','success');
                }

                // Hitung ulang total R0
                recalcR0Total(row);

                // Refresh baris lain
                if(resp.id_no) refreshCalculatedRows(resp.id_no,did,col);
            } else {
                dbgLog('SAVE ERR: '+resp.message,true);
                $input.addClass('is-error');setTimeout(function(){$input.removeClass('is-error');},3000);
                showToast('Gagal: '+(resp.message||'Error'),'error');
            }
        },
        error:function(xhr,status,err){
            $input.removeClass('is-saving').addClass('is-error');
            setTimeout(function(){$input.removeClass('is-error');},3000);
            dbgLog('NET ERR: '+status+' '+err,true);
            showToast('Network error: '+status,'error');
        }
    });
}
function scheduleSave($input){
    var k=$input.data('detail-id')+'_'+$input.data('col');
    if(saveTimers[k])clearTimeout(saveTimers[k]);
    saveTimers[k]=setTimeout(function(){doSave($input);},800);
}

// ===================== REFRESH =====================
function refreshCalculatedRows(idNo,skipDid,skipCol){
    if(isRefreshing)return;
    isRefreshing=true;
    $('#refreshOverlay').addClass('active');
    dbgLog('REFRESH: id_no='+idNo,false);

    $.ajax({
        url:window.location.pathname,type:'GET',
        data:{ajax:'1',action:'refresh_details',id_no:idNo},dataType:'json',
        success:function(resp){
            $('#refreshOverlay').removeClass('active');
            isRefreshing=false;
            if(resp.status!=='ok'){dbgLog('REFRESH ERR',true);return;}

            $.each(resp.data,function(i,row){
                var dtlId=row.ID;
                var desc=(row.DESC_PROD||'').trim();
                var isR0=/prod\s*plan\s*r0/i.test(desc);
                var $tr=$('tr[data-dtl-id="'+dtlId+'"]');
                if($tr.length===0)return;

                if(isR0){
                    // === R0: Update inputs dari server, lalu RECALC TOTAL client-side ===
                    $tr.find('.plan-input').each(function(){
                        var thisId=$(this).data('detail-id');
                        var thisCol=$(this).data('col');
                        if(String(thisId)===String(skipDid)&&thisCol===skipCol)return;
                        var colKey=thisCol;
                        var sv=row[colKey];
                        var sd=(sv!==null&&sv!==''&&parseFloat(sv)!=0)?parseFloat(sv):'';
                        var cv=$(this).val();
                        if(String(sd)!==String(cv)){
                            dbgLog('R0 sync '+thisCol+': "'+cv+'" -> "'+sd+'"',false);
                            $(this).val(sd);$(this).data('original',String(sd));
                            $(this).addClass('cell-updated');
                        }
                    });
                    // !!! PENTING: Total R0 dihitung client-side, BUKAN dari server !!!
                    var r0RowIdx=$tr.data('r0-row');
                    if(r0RowIdx!==undefined) recalcR0Total(r0RowIdx);

                } else {
                    // === NON-R0: Update D cells + G_TOTAL dari server ===
                    $tr.find('.day-cell').each(function(){
                        var dn=parseInt($(this).data('day'));
                        var ck='D'+dn;
                        var nv=row[ck];
                        var nt=fmtN(nv);
                        var ot=$(this).text();
                        $(this).text(nt);
                        if(ot!==nt){
                            $(this).addClass('cell-updated');
                            if(nt!=='-'){$(this).addClass('cell-highlight');}else{$(this).removeClass('cell-highlight');}
                        }
                    });
                    // G_TOTAL dari server untuk non-R0
                    var gt=parseFloat(row.G_TOTAL)||0;
                    var $gtc=$tr.find('.gtotal-cell');
                    var oldGT=$gtc.text();
                    $gtc.text(fmtN(gt));
                    if(oldGT!==$gtc.text()) $gtc.addClass('cell-updated');
                }
            });

            setTimeout(function(){$('.cell-updated').removeClass('cell-updated');},700);
            syncRowHeights();
            dbgLog('REFRESH DONE','ok');
            showToast('Auto-calculate selesai','info');
        },
        error:function(){
            $('#refreshOverlay').removeClass('active');isRefreshing=false;
            dbgLog('REFRESH NET ERR',true);
            showToast('Refresh gagal','error');
        }
    });
}

// ===================== NAVIGASI =====================
function navTo(r,c){
    var $t=$('.plan-input[data-row="'+r+'"][data-col-idx="'+c+'"]');
    if($t.length){$t.focus().select();var ct=$('#rightPanel');ct.scrollLeft(Math.max(0,$t.position().left-ct.width()/2+$t.width()/2));return true;}
    return false;
}
function maxCol(){var m=0;$('.plan-input').first().closest('tr').find('.plan-input').each(function(){var c=parseInt($(this).data('col-idx'));if(c>m)m=c;});return m;}
function maxRow(){var m=0;$('.plan-input').each(function(){var r=parseInt($(this).data('row'));if(r>m)m=r;});return m;}

// ===================== INPUT EVENTS =====================
 $(document).on('focus','.plan-input',function(){
    if(!$(this).data('original-set')){$(this).data('original',$(this).val());$(this).data('original-set',true);}
    $(this).select();
});
 $(document).on('input','.plan-input',function(){
    var v=$(this).val();v=v.replace(/[^0-9.]/g,'');var p=v.split('.');if(p.length>2)v=p[0]+'.'+p.slice(1).join('');
    $(this).val(v);
    scheduleSave($(this));
    // Immediate total feedback
    recalcR0Total(parseInt($(this).data('row')));
});
 $(document).on('blur','.plan-input',function(){
    var k=$(this).data('detail-id')+'_'+$(this).data('col');
    if(saveTimers[k]){clearTimeout(saveTimers[k]);delete saveTimers[k];}
    if($(this).val()!==$(this).data('original'))doSave($(this));
});

// ===================== KEYBOARD NAV =====================
 $(document).on('keydown','.plan-input',function(e){
    var $t=$(this),r=parseInt($t.data('row')),c=parseInt($t.data('col-idx')),mc=maxCol(),mr=maxRow();
    switch(e.key){
        case'ArrowLeft':e.preventDefault();if(c>0)navTo(r,c-1);break;
        case'ArrowRight':e.preventDefault();if(c<mc)navTo(r,c+1);break;
        case'ArrowUp':e.preventDefault();if(r>0)navTo(r-1,c);break;
        case'ArrowDown':e.preventDefault();if(r<mr)navTo(r+1,c);break;
        case'Tab':e.preventDefault();
            if(e.shiftKey){if(c>0)navTo(r,c-1);else if(r>0)navTo(r-1,mc);}
            else{if(c<mc)navTo(r,c+1);else if(r<mr)navTo(r+1,0);}
            break;
        case'Enter':e.preventDefault();if(r<mr)navTo(r+1,c);break;
        case'Escape':e.preventDefault();$t.val($t.data('original')||'');$t.blur();break;
        case'Home':e.preventDefault();navTo(r,0);break;
        case'End':e.preventDefault();navTo(r,mc);break;
    }
});

// ===================== SYNC ROW HEIGHTS =====================
function syncRowHeights(){
    var rr=$('.table-right-vb6 tbody tr'),lr=$('.table-left-vb6 tbody tr');
    if(rr.length===0||lr.length===0)return;
    lr.css('height','auto');rr.css('height','auto');
    rr.each(function(i){if(lr.eq(i).length)lr.eq(i).outerHeight($(this).outerHeight());});
    $('.table-left-vb6 thead tr').outerHeight($('.table-right-vb6 thead tr').outerHeight());
}

// ===================== INIT =====================
 $(document).ready(function(){
    <?php if(count($data_report)>0):?>$('#btnPrint').prop('disabled',false);<?php endif;?>

    // !!! PENTING: Hitung semua R0 total saat page load !!!
    recalcAllR0Totals();

    if(currentStation!==''){
        $('#selStation').trigger('change');
        if(currentMesin!==''){
            setTimeout(function(){
                $('#selMesin').val(currentMesin);loadItemCodes();
                if(currentItemCode!==''){
                    setTimeout(function(){
                        $('#selItemCode').val(currentItemCode);$('#btnTampil').prop('disabled',false);
                        $('#stepItem').removeClass('active').addClass('done');$('#sfStep3,#sfStep4').addClass('sf-active');
                    },500);
                }
            },150);
        }
    }
    setTimeout(syncRowHeights,300);
    $(window).on('resize',syncRowHeights);
});
</script>
</body>
</html>