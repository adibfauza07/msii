<?php
/**
 * MCS Excel CRUD - Gabungan Plant 1 & Plant 2
 * PHP 5.4 + SQL Server 2008
 */

@ini_set('max_execution_time', '180');
@ini_set('memory_limit', '512M');
if (session_id() === '') session_start();

// 1. Password
define('MCS_DELETE_PASSWORD', 'q9tj9');

// Helper Periode Bulan Indonesia
$bulanIndo = array('January'=>'Januari', 'February'=>'Februari', 'March'=>'Maret', 'April'=>'April', 'May'=>'Mei', 'June'=>'Juni', 'July'=>'Juli', 'August'=>'Agustus', 'September'=>'September', 'October'=>'Oktober', 'November'=>'November', 'December'=>'Desember');
$periodeSekarang = strtoupper($bulanIndo[date('F')] . ' ' . date('Y'));

// Helper Pencatatan Log Edit
function write_log($action, $id, $item, $plant) {
    $date = date('Y-m-d H:i:s');
    $user = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : 'unknown';
    $msg = "[$date] ACTION: $action | USER: $user | PLANT: $plant | ID: $id | ITEM_CODE: $item" . PHP_EOL;
    @file_put_contents('mcs_edit_log.txt', $msg, FILE_APPEND);
}

// ============================================
// LOAD CONFIG PPIC UNTUK SESSION / FALLBACK
// ============================================
$config1 = __DIR__ . '/config/database_ppic.php';
$config2 = __DIR__ . '/../config/database_ppic.php';
if (file_exists($config1)) {
    require_once $config1;
} elseif (file_exists($config2)) {
    require_once $config2;
}

$uid = isset($_SESSION['db_user']) ? trim((string)$_SESSION['db_user']) : '';
$pwd = isset($_SESSION['db_pass']) ? (string)$_SESSION['db_pass'] : '';
$dbName = 'msData';

$servers_config = array(
    'p1' => array('ip' => '192.168.0.4', 'label' => 'Plant 1', 'short' => 'P1'),
    'p2' => array('ip' => '192.168.0.9', 'label' => 'Plant 2', 'short' => 'P2')
);

// ============================================
// HELPER UMUM
// ============================================
function h($value) {
    if ($value instanceof DateTime) return htmlspecialchars($value->format('Y-m-d'), ENT_QUOTES, 'UTF-8');
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function postv($key, $default = '') {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

function getv($key, $default = '') {
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

function null_if_empty($value) {
    $value = trim((string)$value);
    return ($value === '') ? null : $value;
}

function number_or_null($value) {
    $value = trim((string)$value);
    if ($value === '') return null;
    $value = str_replace(',', '.', $value);
    return is_numeric($value) ? (float)$value : null;
}

function safe_sql_error() {
    $errors = sqlsrv_errors();
    if (!is_array($errors)) return 'SQL error tidak diketahui.';
    $out = array();
    foreach ($errors as $e) {
        $out[] = isset($e['message']) ? $e['message'] : '';
    }
    return implode(' | ', $out);
}

function fetch_all_assoc($stmt) {
    $rows = array();
    if (!$stmt) return $rows;
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }
    return $rows;
}

function json_response($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function clean_plant($plant, $allowAll) {
    $plant = strtolower(trim((string)$plant));
    if ($allowAll && $plant === 'all') return 'all';
    if ($plant !== 'p1' && $plant !== 'p2') return $allowAll ? 'all' : 'p1';
    return $plant;
}

function default_new_plant($selectedPlant) {
    $selectedPlant = clean_plant($selectedPlant, true);
    if ($selectedPlant === 'p1' || $selectedPlant === 'p2') return $selectedPlant;
    if (isset($_SESSION['active_plant'])) {
        $ap = strtolower(trim((string)$_SESSION['active_plant']));
        if ($ap === 'p1' || $ap === 'p2') return $ap;
    }
    return 'p1';
}

function connect_plant($plant) {
    global $servers_config, $uid, $pwd, $dbName, $conn, $conn_ppic, $connection, $dbconn, $link;

    $plant = clean_plant($plant, false);

    if ($uid !== '' && isset($servers_config[$plant])) {
        $opts = array(
            'Database' => $dbName,
            'Uid' => $uid,
            'PWD' => $pwd,
            'CharacterSet' => 'UTF-8',
            'LoginTimeout' => 5
        );
        $c = @sqlsrv_connect($servers_config[$plant]['ip'], $opts);
        if ($c) return $c;
    }

    if (isset($conn) && $conn) return $conn;
    if (isset($conn_ppic) && $conn_ppic) return $conn_ppic;
    if (isset($connection) && $connection) return $connection;
    if (isset($dbconn) && $dbconn) return $dbconn;
    if (isset($link) && $link) return $link;

    return null;
}

function q_conn($c, $sql, $params = array()) {
    if (!$c) return false;
    return sqlsrv_query($c, $sql, $params);
}

function fmt_cell($value, $field, $numberFields) {
    if ($value === null || $value === '') return '';
    if ($value instanceof DateTime) return $value->format('Y-m-d');
    if (in_array($field, $numberFields, true)) {
        return rtrim(rtrim(number_format((float)$value, 6, '.', ''), '0'), '.');
    }
    return (string)$value;
}

// ============================================
// KOLOM TABLE
// ============================================
$mcsFields = array(
    'ITEM_CODE', 'MAC', 'TONAGE', 'MAT_CODE', 'MAT_NAME', 'MAT_BRAND', 'MAT_GRADE',
    'MAT_COLOUR_NO', 'MAT_COLOUR', 'MAT_MAKER', 'CYTM', 'CAV', 'PART_WEIGHT',
    'R_PCS', 'R_SHOT', 'SHOT', 'PCS', 'RUNNER_PERSEN', 'RUNNER_MIN', 'RUNNER_MAX',
    'NET_WEIGHT', 'TARGET_DAY', 'TRIAL_DATE', 'MP', 'REMARKS'
);

$displayFields = array(
    'PLANT', 'ITEM_CODE', 'ITEM_NO', 'ITEM_NAME', 'MAC', 'TONAGE', 'MAT_CODE', 'MAT_NAME',
    'MAT_BRAND', 'MAT_GRADE', 'MAT_COLOUR_NO', 'MAT_COLOUR', 'MAT_MAKER', 'CYTM', 'CAV',
    'PART_WEIGHT', 'R_PCS', 'R_SHOT', 'SHOT', 'PCS', 'RUNNER_PERSEN', 'RUNNER_MIN',
    'RUNNER_MAX', 'NET_WEIGHT', 'TARGET_DAY', 'TRIAL_DATE', 'MP', 'REMARKS'
);

$viewOnlyFields = array('ITEM_NO', 'ITEM_NAME');

$numberFields = array(
    'TONAGE', 'CAV', 'PART_WEIGHT', 'R_PCS', 'R_SHOT', 'SHOT', 'PCS',
    'RUNNER_PERSEN', 'RUNNER_MIN', 'RUNNER_MAX', 'NET_WEIGHT', 'TARGET_DAY'
);

$formulaFields = array('R_PCS', 'SHOT', 'PCS', 'RUNNER_PERSEN', 'NET_WEIGHT', 'TARGET_DAY');

$editableFields = array(
    'ITEM_CODE', 'MAC', 'TONAGE', 'MAT_CODE', 'MAT_NAME', 'MAT_BRAND', 'MAT_GRADE',
    'MAT_COLOUR_NO', 'MAT_COLOUR', 'MAT_MAKER', 'CYTM', 'CAV', 'PART_WEIGHT',
    'R_SHOT', 'RUNNER_MIN', 'RUNNER_MAX', 'TRIAL_DATE', 'MP', 'REMARKS'
);

$fieldLabels = array(
    'PLANT'          => 'Plant',
    'ITEM_CODE'      => 'Item Code',
    'ITEM_NO'        => 'Item No',
    'ITEM_NAME'      => 'Item Name',
    'MAC'            => 'MAC',
    'TONAGE'         => 'Tonage',
    'MAT_CODE'       => 'Mat Code',
    'MAT_NAME'       => 'Mat Name',
    'MAT_BRAND'      => 'Brand',
    'MAT_GRADE'      => 'Grade',
    'MAT_COLOUR_NO'  => 'Colour No',
    'MAT_COLOUR'     => 'Colour',
    'MAT_MAKER'      => 'Maker',
    'CYTM'           => 'CYTM',
    'CAV'            => 'CAV',
    'PART_WEIGHT'    => 'Part Weight',
    'R_PCS'          => 'R Pcs',
    'R_SHOT'         => 'R Shot',
    'SHOT'           => 'Shot',
    'PCS'            => 'Pcs',
    'RUNNER_PERSEN'  => 'Runner %',
    'RUNNER_MIN'     => 'Runner Min',
    'RUNNER_MAX'     => 'Runner Max',
    'NET_WEIGHT'     => 'Net Weight',
    'TARGET_DAY'     => 'Target Day',
    'TRIAL_DATE'     => 'Trial Date',
    'MP'             => 'MP',
    'REMARKS'        => 'Remarks'
);

function empty_row($mcsFields, $defaultPlant) {
    $row = array('PLANT' => $defaultPlant, 'ID' => '', 'CUST_CODE' => '', 'CUST_COMP' => '', 'ITEM_NO' => '', 'ITEM_NAME' => '');
    foreach ($mcsFields as $f) $row[$f] = '';
    return $row;
}

function row_has_any_value($data, $editableFields) {
    foreach ($editableFields as $f) {
        if (isset($data[$f]) && trim((string)$data[$f]) !== '') return true;
    }
    return false;
}

function calc_mcs_formulas(&$data) {
    $cav = isset($data['CAV']) ? number_or_null($data['CAV']) : null;
    $r_shot = isset($data['R_SHOT']) ? number_or_null($data['R_SHOT']) : null;
    $part_weight = isset($data['PART_WEIGHT']) ? number_or_null($data['PART_WEIGHT']) : null;
    $cytm = isset($data['CYTM']) ? number_or_null($data['CYTM']) : null;
    $runner_min = isset($data['RUNNER_MIN']) ? number_or_null($data['RUNNER_MIN']) : null;

    $data['R_PCS'] = ($cav !== null && $r_shot !== null && $r_shot != 0) ? ($cav / $r_shot) : null;
    if ($cav !== null && $part_weight !== null) {
        $data['SHOT'] = ($cav * $part_weight) + (($r_shot !== null) ? $r_shot : 0);
    } else {
        $data['SHOT'] = null;
    }
    $data['PCS'] = ($part_weight !== null) ? ($part_weight * 1.02) : null;
    $shot = isset($data['SHOT']) ? number_or_null($data['SHOT']) : null;
    $data['RUNNER_PERSEN'] = ($r_shot !== null && $shot !== null && $shot != 0) ? (($r_shot / $shot) * 100) : null;
    
    // Perbaikan NET_WEIGHT: Jika RUNNER_MIN kosong/null, anggap sebagai 0 agar tetap terhitung.
    $runner_min_val = ($runner_min !== null) ? $runner_min : 0;
    $data['NET_WEIGHT'] = ($shot !== null && $cav !== null && $cav != 0)
        ? ((($shot / $cav / 100) * (100 - $runner_min_val)) * 1.02)
        : null;
        
    $data['TARGET_DAY'] = ($cytm !== null && $cytm != 0 && $cav !== null)
        ? ((3600 / $cytm) * $cav * 24 * 0.9)
        : null;
}

function collect_row_data($mcsFields, $numberFields, $editableFields) {
    $data = array();
    foreach ($mcsFields as $f) $data[$f] = null;
    foreach ($editableFields as $f) {
        if (in_array($f, $numberFields, true)) {
            $data[$f] = number_or_null(postv($f));
        } else {
            $data[$f] = null_if_empty(postv($f));
        }
    }
    calc_mcs_formulas($data);
    return $data;
}

function normalize_row_for_json($row, $displayFields, $numberFields) {
    $out = array();
    $out['PLANT'] = isset($row['PLANT']) ? strtolower((string)$row['PLANT']) : '';
    $out['ID'] = isset($row['ID']) ? (string)$row['ID'] : '';
    foreach ($displayFields as $f) {
        if ($f === 'PLANT') {
            $out[$f] = isset($row[$f]) ? strtolower((string)$row[$f]) : '';
        } else {
            $out[$f] = isset($row[$f]) ? fmt_cell($row[$f], $f, $numberFields) : '';
        }
    }
    return $out;
}

function lookup_item_info($conn, $itemCode) {
    $info = array('ITEM_NO' => '', 'ITEM_NAME' => '');
    $itemCode = trim((string)$itemCode);
    if ($itemCode === '') return $info;
    $sql = "SELECT TOP 1 ITEM_NO, ITEM_NAME FROM ITEMS WHERE ITEM_CODE = ?";
    $stmt = q_conn($conn, $sql, array($itemCode));
    if ($stmt) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r) {
            $info['ITEM_NO'] = isset($r['ITEM_NO']) ? $r['ITEM_NO'] : '';
            $info['ITEM_NAME'] = isset($r['ITEM_NAME']) ? $r['ITEM_NAME'] : '';
        }
    }
    return $info;
}

function lookup_mat_info($conn, $matCode) {
    $info = array('MAT_CODE' => '', 'MAT_NAME' => '');
    $matCode = trim((string)$matCode);
    if ($matCode === '') return $info;
    $sql = "SELECT TOP 1 ITEM_CODE AS MAT_CODE, ITEM_NAME AS MAT_NAME FROM ITEMS WHERE ITTY_CODE = '02' AND ITEM_CODE = ?";
    $stmt = q_conn($conn, $sql, array($matCode));
    if ($stmt) {
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($r) {
            $info['MAT_CODE'] = isset($r['MAT_CODE']) ? $r['MAT_CODE'] : '';
            $info['MAT_NAME'] = isset($r['MAT_NAME']) ? $r['MAT_NAME'] : '';
        }
    }
    return $info;
}

function item_exists_in_items($conn, $itemCode) {
    $itemCode = trim((string)$itemCode);
    if ($itemCode === '') return false;
    $stmt = q_conn($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE = ?", array($itemCode));
    if (!$stmt) return false;
    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $r ? true : false;
}

function mcs_duplicate_exists($conn, $row) {
    $itemCode = isset($row['ITEM_CODE']) ? (string)$row['ITEM_CODE'] : '';
    $mac = isset($row['MAC']) ? (string)$row['MAC'] : '';
    $matCode = isset($row['MAT_CODE']) ? (string)$row['MAT_CODE'] : '';
    $tonage = isset($row['TONAGE']) ? (string)$row['TONAGE'] : '';

    $sql = "
        SELECT TOP 1 ID FROM MCS
        WHERE ISNULL(ITEM_CODE, '') = ? AND ISNULL(MAC, '') = ? AND ISNULL(MAT_CODE, '') = ? AND ISNULL(CONVERT(VARCHAR(50), TONAGE), '') = ?
    ";
    $stmt = q_conn($conn, $sql, array($itemCode, $mac, $matCode, $tonage));
    if (!$stmt) return false;
    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $r ? true : false;
}

function insert_mcs_row($conn, $row, $mcsFields) {
    $placeholders = array();
    $params = array();
    foreach ($mcsFields as $f) {
        $placeholders[] = '?';
        $params[] = isset($row[$f]) ? $row[$f] : null;
    }
    $sql = 'INSERT INTO MCS (' . implode(', ', $mcsFields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    return q_conn($conn, $sql, $params);
}

function fetch_one_mcs_row($conn, $plant, $id, $mcsFields) {
    $sql = "
        SELECT
            ? AS PLANT, m.ID, i.ITEM_NO, i.ITEM_NAME, m.ITEM_CODE, m.MAC, m.TONAGE, m.MAT_CODE, m.MAT_NAME, m.MAT_BRAND, m.MAT_GRADE,
            m.MAT_COLOUR_NO, m.MAT_COLOUR, m.MAT_MAKER, m.CYTM, m.CAV, m.PART_WEIGHT,
            m.R_PCS, m.R_SHOT, m.SHOT, m.PCS, m.RUNNER_PERSEN, m.RUNNER_MIN, m.RUNNER_MAX,
            m.NET_WEIGHT, m.TARGET_DAY, m.TRIAL_DATE, m.MP, m.REMARKS
        FROM MCS m
        LEFT JOIN ITEMS i ON i.ITEM_CODE = m.ITEM_CODE
        WHERE m.ID = ?
    ";
    $stmt = q_conn($conn, $sql, array($plant, $id));
    if (!$stmt) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$row) return null;
    calc_mcs_formulas($row);
    return $row;
}

// ============================================
// AJAX: CUSTOMER AUTOCOMPLETE UNTUK FILTER
// ============================================
if (getv('action') === 'customer_suggest') {
    $q = getv('q', '');
    $plantFilter = clean_plant(getv('plant', 'all'), true);
    $serversToTry = ($plantFilter === 'all') ? array('p1', 'p2') : array($plantFilter);
    $result = array();
    $seen = array();

    if (trim($q) !== '') {
        foreach ($serversToTry as $plantKey) {
            $c = connect_plant($plantKey);
            if (!$c) continue;

            $like = '%' . $q . '%';
            $sql = "
                SELECT DISTINCT TOP 50 CUST_CODE, CUST_COMP
                FROM ITEM_CUSTINFO_VIEW
                WHERE (CUST_CODE LIKE ? OR CUST_COMP LIKE ?)
                  AND (ISNULL(LTRIM(RTRIM(CUST_CODE)), '') <> '' OR ISNULL(LTRIM(RTRIM(CUST_COMP)), '') <> '')
                ORDER BY CUST_CODE, CUST_COMP
            ";
            $stmt = q_conn($c, $sql, array($like, $like));
            if ($stmt) {
                while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $code = isset($r['CUST_CODE']) ? trim((string)$r['CUST_CODE']) : '';
                    $name = isset($r['CUST_COMP']) ? trim((string)$r['CUST_COMP']) : '';
                    if ($code === '' && $name === '') continue;

                    $key = $plantKey . '|' . strtoupper($code) . '|' . strtoupper($name);
                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;

                    $result[] = array(
                        'plant' => $plantKey,
                        'plant_label' => strtoupper($plantKey),
                        'customer_code' => $code,
                        'customer_name' => $name
                    );
                }
            }
        }
    }

    json_response(array('status' => 'success', 'data' => $result));
}

// ============================================
// AJAX: ITEM AUTOCOMPLETE
// ============================================
if (getv('action') === 'item_suggest') {
    $q = getv('q', '');
    $plantFilter = clean_plant(getv('plant', 'all'), true);
    $serversToTry = ($plantFilter === 'all') ? array('p1', 'p2') : array($plantFilter);
    $result = array();
    $seen = array();

    if (trim($q) !== '') {
        foreach ($serversToTry as $plantKey) {
            $c = connect_plant($plantKey);
            if (!$c) continue;
            $like = '%' . $q . '%';
            $sql = "
                SELECT TOP 30 ITEM_CODE, ITEM_NO, ITEM_NAME FROM ITEMS
                WHERE ITEM_INACTIVE = 0 AND (ITEM_CODE LIKE ? OR ITEM_NO LIKE ? OR ITEM_NAME LIKE ?)
                ORDER BY ITEM_CODE
            ";
            $stmt = q_conn($c, $sql, array($like, $like, $like));
            if ($stmt) {
                while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $code = isset($r['ITEM_CODE']) ? trim((string)$r['ITEM_CODE']) : '';
                    if ($code === '') continue;
                    $key = $plantKey . '|' . strtoupper($code);
                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;
                    $result[] = array(
                        'plant' => $plantKey, 'plant_label' => strtoupper($plantKey), 'item_code' => $code,
                        'item_no' => isset($r['ITEM_NO']) ? (string)$r['ITEM_NO'] : '', 'item_name' => isset($r['ITEM_NAME']) ? (string)$r['ITEM_NAME'] : ''
                    );
                }
            }
        }
    }
    json_response(array('status' => 'success', 'data' => $result));
}

if (getv('action') === 'item_lookup') {
    $plant = clean_plant(getv('plant', 'p1'), false);
    $itemCode = getv('item_code', '');
    $c = connect_plant($plant);
    if (!$c) json_response(array('status' => 'error', 'message' => 'Koneksi plant tidak tersedia.'));
    $info = lookup_item_info($c, $itemCode);
    json_response(array('status' => 'success', 'plant' => $plant, 'item_code' => $itemCode, 'item_no' => $info['ITEM_NO'], 'item_name' => $info['ITEM_NAME']));
}

// ============================================
// AJAX: AUTOCOMPLETE MAC / TONAGE / MATERIAL
// ============================================
function autocomplete_servers($plantFilter) {
    $plantFilter = clean_plant($plantFilter, true);
    return ($plantFilter === 'all') ? array('p1', 'p2') : array($plantFilter);
}

if (getv('action') === 'mac_suggest') {
    $q = getv('q', '');
    $serversToTry = autocomplete_servers(getv('plant', 'all'));
    $result = array();
    $seen = array();
    if (trim($q) !== '') {
        foreach ($serversToTry as $plantKey) {
            $c = connect_plant($plantKey);
            if (!$c) continue;
            $like = '%' . $q . '%';
            $stmt = q_conn($c, "SELECT TOP 50 MAC_CODE AS MC_NO FROM MAC WHERE MAC_CODE LIKE ? ORDER BY MAC_CODE", array($like));
            if ($stmt) {
                while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $v = isset($r['MC_NO']) ? trim((string)$r['MC_NO']) : '';
                    if ($v === '') continue;
                    $key = strtoupper($v);
                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;
                    $result[] = array('plant' => $plantKey, 'plant_label' => strtoupper($plantKey), 'value' => $v);
                }
            }
        }
    }
    json_response(array('status' => 'success', 'data' => $result));
}

if (getv('action') === 'mag_suggest') {
    $q = getv('q', '');
    $serversToTry = autocomplete_servers(getv('plant', 'all'));
    $result = array();
    $seen = array();
    if (trim($q) !== '') {
        foreach ($serversToTry as $plantKey) {
            $c = connect_plant($plantKey);
            if (!$c) continue;
            $like = '%' . $q . '%';
            $stmt = q_conn($c, "SELECT TOP 50 MAG_STATION AS TONASE FROM MAG WHERE CONVERT(VARCHAR(50), MAG_STATION) LIKE ? ORDER BY MAG_STATION", array($like));
            if ($stmt) {
                while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $v = isset($r['TONASE']) ? trim((string)$r['TONASE']) : '';
                    if ($v === '') continue;
                    $key = strtoupper($v);
                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;
                    $result[] = array('plant' => $plantKey, 'plant_label' => strtoupper($plantKey), 'value' => $v);
                }
            }
        }
    }
    json_response(array('status' => 'success', 'data' => $result));
}

if (getv('action') === 'mat_suggest') {
    $q = getv('q', '');
    $serversToTry = autocomplete_servers(getv('plant', 'all'));
    $result = array();
    $seen = array();
    if (trim($q) !== '') {
        foreach ($serversToTry as $plantKey) {
            $c = connect_plant($plantKey);
            if (!$c) continue;
            $like = '%' . $q . '%';
            $sql = "
                SELECT TOP 50 ITEM_CODE AS MAT_CODE, ITEM_NAME AS MAT_NAME FROM ITEMS
                WHERE ITTY_CODE = '02' AND ITEM_INACTIVE = 0 AND (ITEM_CODE LIKE ? OR ITEM_NAME LIKE ?)
                ORDER BY ITEM_CODE
            ";
            $stmt = q_conn($c, $sql, array($like, $like));
            if ($stmt) {
                while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $code = isset($r['MAT_CODE']) ? trim((string)$r['MAT_CODE']) : '';
                    if ($code === '') continue;
                    $key = $plantKey . '|' . strtoupper($code);
                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;
                    $result[] = array(
                        'plant' => $plantKey, 'plant_label' => strtoupper($plantKey), 'mat_code' => $code, 'mat_name' => isset($r['MAT_NAME']) ? (string)$r['MAT_NAME'] : ''
                    );
                }
            }
        }
    }
    json_response(array('status' => 'success', 'data' => $result));
}

if (getv('action') === 'mat_lookup') {
    $plant = clean_plant(getv('plant', 'p1'), false);
    $matCode = getv('mat_code', '');
    $c = connect_plant($plant);
    if (!$c) json_response(array('status' => 'error', 'message' => 'Koneksi plant tidak tersedia.'));
    $info = lookup_mat_info($c, $matCode);
    json_response(array('status' => 'success', 'plant' => $plant, 'mat_code' => $info['MAT_CODE'], 'mat_name' => $info['MAT_NAME']));
}

// ============================================
// AJAX: SAVE ROW
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && postv('action') === 'save_row') {
    $id = (int)postv('ID', '0');
    $plant = clean_plant(postv('PLANT', 'p1'), false);
    
    if ($id > 0) {
        $savePwd = postv('save_password', '');
        if ($savePwd !== MCS_DELETE_PASSWORD) {
            json_response(array('status' => 'error', 'message' => 'Password salah. Data gagal diupdate.'));
        }
    }

    $data = collect_row_data($mcsFields, $numberFields, $editableFields);

    if (!row_has_any_value($data, $editableFields)) {
        json_response(array('status' => 'empty', 'message' => 'Row kosong, tidak disimpan.'));
    }

    if ($data['ITEM_CODE'] === null || trim((string)$data['ITEM_CODE']) === '') {
        json_response(array('status' => 'error', 'message' => 'ITEM_CODE wajib diisi.'));
    }

    $c = connect_plant($plant);
    if (!$c) json_response(array('status' => 'error', 'message' => 'Koneksi ' . strtoupper($plant) . ' tidak tersedia.'));

    if ($id > 0) {
        $sets = array();
        $params = array();
        foreach ($mcsFields as $f) {
            $sets[] = $f . ' = ?';
            $params[] = $data[$f];
        }
        $params[] = $id;
        $sql = 'UPDATE MCS SET ' . implode(', ', $sets) . ' WHERE ID = ?';
        $stmt = q_conn($c, $sql, $params);
        if (!$stmt) json_response(array('status' => 'error', 'message' => safe_sql_error()));
        
        write_log('UPDATE', $id, $data['ITEM_CODE'], strtoupper($plant));
        
        $row = fetch_one_mcs_row($c, $plant, $id, $mcsFields);
        if (!$row) $row = array_merge(array('PLANT' => $plant, 'ID' => $id, 'ITEM_NO' => '', 'ITEM_NAME' => ''), $data);
        
        json_response(array('status' => 'success', 'mode' => 'update', 'message' => 'Row berhasil diupdate.', 'row' => normalize_row_for_json($row, $displayFields, $numberFields)));
    } else {
        $placeholders = array();
        $params = array();
        foreach ($mcsFields as $f) {
            $placeholders[] = '?';
            $params[] = $data[$f];
        }
        
        $sql = 'INSERT INTO MCS (' . implode(', ', $mcsFields) . ') VALUES (' . implode(', ', $placeholders) . '); SELECT SCOPE_IDENTITY() AS NEW_ID;';
        $stmt = q_conn($c, $sql, $params);
        if (!$stmt) json_response(array('status' => 'error', 'message' => safe_sql_error()));

        $newId = 0;
        sqlsrv_next_result($stmt);
        $idRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($idRow && isset($idRow['NEW_ID'])) $newId = (int)$idRow['NEW_ID'];
        
        write_log('INSERT', $newId, $data['ITEM_CODE'], strtoupper($plant));
        
        $row = fetch_one_mcs_row($c, $plant, $newId, $mcsFields);
        if (!$row) $row = array_merge(array('PLANT' => $plant, 'ID' => $newId, 'ITEM_NO' => '', 'ITEM_NAME' => ''), $data);
        
        json_response(array('status' => 'success', 'mode' => 'insert', 'message' => 'Row baru berhasil ditambahkan.', 'row' => normalize_row_for_json($row, $displayFields, $numberFields)));
    }
}

// ============================================
// AJAX: DELETE ROW
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && postv('action') === 'delete_row') {
    $id = (int)postv('ID', '0');
    $plant = clean_plant(postv('PLANT', 'p1'), false);
    $deletePwd = postv('delete_password', '');

    if ($id <= 0) json_response(array('status' => 'error', 'message' => 'ID tidak valid.'));
    if ($deletePwd !== MCS_DELETE_PASSWORD) json_response(array('status' => 'error', 'message' => 'Password hapus salah. Data tidak dihapus.'));

    $c = connect_plant($plant);
    if (!$c) json_response(array('status' => 'error', 'message' => 'Koneksi ' . strtoupper($plant) . ' tidak tersedia.'));

    $stmt = q_conn($c, 'DELETE FROM MCS WHERE ID = ?', array($id));
    if (!$stmt) json_response(array('status' => 'error', 'message' => safe_sql_error()));
    
    write_log('DELETE', $id, 'N/A', strtoupper($plant));
    
    json_response(array('status' => 'success', 'message' => 'Row berhasil dihapus.'));
}

// ============================================
// AJAX: PINDAHKAN 1 ROW P2 KE MCS P1
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && postv('action') === 'move_one_to_p1') {
    $id = (int)postv('ID', '0');
    $plant = clean_plant(postv('PLANT', 'p2'), false);
    $pwdMove = postv('move_password', '');

    if ($id <= 0) json_response(array('status' => 'error', 'message' => 'ID tidak valid.'));
    if ($plant !== 'p2') json_response(array('status' => 'error', 'message' => 'Hanya data dari P2 yang bisa dipindahkan ke P1.'));
    if ($pwdMove !== MCS_DELETE_PASSWORD) json_response(array('status' => 'error', 'message' => 'Password salah. Data tidak dipindahkan.'));

    $cP1 = connect_plant('p1');
    $cP2 = connect_plant('p2');
    if (!$cP1 || !$cP2) json_response(array('status' => 'error', 'message' => 'Koneksi P1/P2 tidak lengkap.'));

    $sql = "
        SELECT ID, ITEM_CODE, MAC, TONAGE, MAT_CODE, MAT_NAME, MAT_BRAND, MAT_GRADE,
            MAT_COLOUR_NO, MAT_COLOUR, MAT_MAKER, CYTM, CAV, PART_WEIGHT,
            R_PCS, R_SHOT, SHOT, PCS, RUNNER_PERSEN, RUNNER_MIN, RUNNER_MAX,
            NET_WEIGHT, TARGET_DAY, TRIAL_DATE, MP, REMARKS
        FROM MCS WHERE ID = ?
    ";
    $stmt = q_conn($cP2, $sql, array($id));
    if (!$stmt) json_response(array('status' => 'error', 'message' => 'Gagal baca MCS P2: ' . safe_sql_error()));
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$row) json_response(array('status' => 'error', 'message' => 'Data P2 tidak ditemukan.'));

    $itemCode = isset($row['ITEM_CODE']) ? trim((string)$row['ITEM_CODE']) : '';
    if ($itemCode === '') json_response(array('status' => 'error', 'message' => 'ITEM_CODE kosong, tidak bisa dipindahkan.'));
    if (!item_exists_in_items($cP1, $itemCode)) json_response(array('status' => 'error', 'message' => 'ITEM_CODE tidak ditemukan di ITEMS P1.'));

    calc_mcs_formulas($row);

    if (mcs_duplicate_exists($cP1, $row)) {
        $del = q_conn($cP2, 'DELETE FROM MCS WHERE ID = ?', array($id));
        if (!$del) json_response(array('status' => 'error', 'message' => 'Duplicate sudah ada di P1, tetapi gagal hapus dari P2: ' . safe_sql_error()));
        json_response(array('status' => 'success', 'message' => 'Data duplicate sudah ada di P1. Row P2 dihapus.'));
    }

    $ins = insert_mcs_row($cP1, $row, $mcsFields);
    if (!$ins) json_response(array('status' => 'error', 'message' => 'Gagal insert ke P1: ' . safe_sql_error()));

    $del = q_conn($cP2, 'DELETE FROM MCS WHERE ID = ?', array($id));
    if (!$del) json_response(array('status' => 'warning', 'message' => 'Data sudah masuk P1, tetapi gagal hapus dari P2: ' . safe_sql_error()));

    json_response(array('status' => 'success', 'message' => 'Row berhasil dipindahkan dari P2 ke P1.'));
}

// ============================================
// AJAX: PINDAHKAN DATA P2 YANG ITEM-NYA KOSONG KE MCS P1
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && postv('action') === 'move_blank_to_p1') {
    $pwdMove = postv('move_password', '');
    if ($pwdMove !== MCS_DELETE_PASSWORD) {
        json_response(array('status' => 'error', 'message' => 'Password salah. Data tidak dipindahkan.'));
    }

    $cP1 = connect_plant('p1');
    $cP2 = connect_plant('p2');
    if (!$cP1 || !$cP2) {
        json_response(array('status' => 'error', 'message' => 'Koneksi P1/P2 tidak lengkap.'));
    }

    $sql = "
        SELECT m.ID, m.ITEM_CODE, m.MAC, m.TONAGE, m.MAT_CODE, m.MAT_NAME, m.MAT_BRAND, m.MAT_GRADE,
            m.MAT_COLOUR_NO, m.MAT_COLOUR, m.MAT_MAKER, m.CYTM, m.CAV, m.PART_WEIGHT,
            m.R_PCS, m.R_SHOT, m.SHOT, m.PCS, m.RUNNER_PERSEN, m.RUNNER_MIN, m.RUNNER_MAX,
            m.NET_WEIGHT, m.TARGET_DAY, m.TRIAL_DATE, m.MP, m.REMARKS
        FROM MCS m
        LEFT JOIN ITEMS i ON i.ITEM_CODE = m.ITEM_CODE
        WHERE m.ITEM_CODE IS NOT NULL AND LTRIM(RTRIM(m.ITEM_CODE)) <> '' AND i.ITEM_CODE IS NULL
        ORDER BY m.ITEM_CODE, m.ID
    ";
    $stmt = q_conn($cP2, $sql);
    if (!$stmt) json_response(array('status' => 'error', 'message' => 'Gagal baca MCS P2: ' . safe_sql_error()));

    $checked = 0; $moved = 0; $duplicate = 0; $notFoundP1 = 0; $failed = array();

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $checked++;
        $itemCode = isset($row['ITEM_CODE']) ? trim((string)$row['ITEM_CODE']) : '';
        if ($itemCode === '' || !item_exists_in_items($cP1, $itemCode)) {
            $notFoundP1++;
            continue;
        }

        $oldId = isset($row['ID']) ? (int)$row['ID'] : 0;
        calc_mcs_formulas($row);

        if (mcs_duplicate_exists($cP1, $row)) {
            $del = q_conn($cP2, 'DELETE FROM MCS WHERE ID = ?', array($oldId));
            if ($del) $duplicate++;
            else $failed[] = $itemCode . ' gagal hapus duplicate dari P2';
            continue;
        }

        $ins = insert_mcs_row($cP1, $row, $mcsFields);
        if (!$ins) {
            $failed[] = $itemCode . ' gagal insert ke P1: ' . safe_sql_error();
            continue;
        }
        $del = q_conn($cP2, 'DELETE FROM MCS WHERE ID = ?', array($oldId));
        if (!$del) {
            $failed[] = $itemCode . ' sudah insert P1, tetapi gagal hapus dari P2: ' . safe_sql_error();
            continue;
        }
        $moved++;
    }

    $msg = 'Cek ' . $checked . ' row kosong P2. Pindah ke P1: ' . $moved . '. Duplicate di P1/dihapus dari P2: ' . $duplicate . '. Tidak ditemukan di ITEMS P1: ' . $notFoundP1 . '.';
    if (count($failed) > 0) $msg .= ' Error: ' . implode(' | ', array_slice($failed, 0, 5));
    json_response(array('status' => (count($failed) > 0 ? 'warning' : 'success'), 'message' => $msg));
}

// ============================================
// AJAX: HAPUS DATA KOSONG DI MCS P2
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && postv('action') === 'delete_blank_p2') {
    $pwdDelete = postv('delete_password', '');
    if ($pwdDelete !== MCS_DELETE_PASSWORD) {
        json_response(array('status' => 'error', 'message' => 'Password salah. Data kosong P2 tidak dihapus.'));
    }

    $cP2 = connect_plant('p2');
    if (!$cP2) {
        json_response(array('status' => 'error', 'message' => 'Koneksi P2 tidak tersedia.'));
    }

    $countSql = "
        SELECT COUNT(*) AS JML FROM MCS m
        LEFT JOIN ITEMS i ON i.ITEM_CODE = m.ITEM_CODE
        WHERE m.ITEM_CODE IS NULL OR LTRIM(RTRIM(m.ITEM_CODE)) = '' OR i.ITEM_CODE IS NULL
    ";
    $stmtCount = q_conn($cP2, $countSql);
    if (!$stmtCount) json_response(array('status' => 'error', 'message' => 'Gagal hitung data kosong P2: ' . safe_sql_error()));
    
    $countRow = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
    $jml = ($countRow && isset($countRow['JML'])) ? (int)$countRow['JML'] : 0;

    if ($jml <= 0) json_response(array('status' => 'success', 'message' => 'Tidak ada data kosong di MCS P2.'));

    $deleteSql = "
        DELETE FROM MCS WHERE ID IN (
            SELECT x.ID FROM (
                SELECT m.ID FROM MCS m
                LEFT JOIN ITEMS i ON i.ITEM_CODE = m.ITEM_CODE
                WHERE m.ITEM_CODE IS NULL OR LTRIM(RTRIM(m.ITEM_CODE)) = '' OR i.ITEM_CODE IS NULL
            ) x
        )
    ";
    $stmtDelete = q_conn($cP2, $deleteSql);
    if (!$stmtDelete) json_response(array('status' => 'error', 'message' => 'Gagal hapus data kosong P2: ' . safe_sql_error()));

    json_response(array('status' => 'success', 'message' => 'Berhasil hapus ' . $jml . ' data kosong dari MCS Plant 2.'));
}

// ============================================
// LIST DATA GABUNGAN P1/P2
// ============================================
$qSearch = getv('q', '');
$customerSearch = getv('customer', '');
$selectedPlant = clean_plant(getv('plant', 'all'), true);
$limit = (int)getv('limit', '300');
if ($limit <= 0) $limit = 300;
if ($limit > 5000) $limit = 5000;

$serversToList = ($selectedPlant === 'all') ? array('p1', 'p2') : array($selectedPlant);
$rows = array();
$connErrors = array();
$serverAvailable = array('p1' => false, 'p2' => false);

foreach ($serversToList as $plantKey) {
    $c = connect_plant($plantKey);
    if (!$c) {
        $connErrors[] = strtoupper($plantKey) . ' offline / koneksi gagal.';
        continue;
    }
    $serverAvailable[$plantKey] = true;

    $paramsList = array($plantKey);
    $whereList = ' WHERE 1 = 1 ';
    if ($qSearch !== '') {
        $whereList .= " AND (m.ITEM_CODE LIKE ? OR m.MAT_CODE LIKE ? OR m.MAT_NAME LIKE ? OR m.MAC LIKE ? OR i.ITEM_NO LIKE ? OR i.ITEM_NAME LIKE ? OR ci.CUST_CODE LIKE ? OR ci.CUST_COMP LIKE ?) ";
        $like = '%' . $qSearch . '%';
        $paramsList[] = $like; $paramsList[] = $like; $paramsList[] = $like;
        $paramsList[] = $like; $paramsList[] = $like; $paramsList[] = $like;
        $paramsList[] = $like; $paramsList[] = $like;
    }
    if ($customerSearch !== '') {
        $whereList .= " AND (ci.CUST_CODE LIKE ? OR ci.CUST_COMP LIKE ?) ";
        $customerLike = '%' . $customerSearch . '%';
        $paramsList[] = $customerLike;
        $paramsList[] = $customerLike;
    }

    $sqlList = '
        SELECT TOP ' . $limit . '
            ? AS PLANT, m.ID, ci.CUST_CODE, ci.CUST_COMP, i.ITEM_NO, i.ITEM_NAME,
            m.ITEM_CODE, m.MAC, m.TONAGE, m.MAT_CODE, m.MAT_NAME, m.MAT_BRAND, m.MAT_GRADE,
            m.MAT_COLOUR_NO, m.MAT_COLOUR, m.MAT_MAKER, m.CYTM, m.CAV, m.PART_WEIGHT,
            m.R_PCS, m.R_SHOT, m.SHOT, m.PCS, m.RUNNER_PERSEN, m.RUNNER_MIN, m.RUNNER_MAX,
            m.NET_WEIGHT, m.TARGET_DAY, m.TRIAL_DATE, m.MP, m.REMARKS
        FROM MCS m
        LEFT JOIN ITEM_CUSTINFO_VIEW ci ON m.ITEM_CODE = ci.PART_CODE
        LEFT JOIN ITEMS i ON i.ITEM_CODE = m.ITEM_CODE
        ' . $whereList . '
        ORDER BY ci.CUST_CODE ASC, ci.CUST_COMP ASC, m.ITEM_CODE ASC, m.ID DESC
    ';

    $stmtList = q_conn($c, $sqlList, $paramsList);
    if (!$stmtList) {
        $connErrors[] = strtoupper($plantKey) . ': ' . safe_sql_error();
        continue;
    }
    $plantRows = fetch_all_assoc($stmtList);
    foreach ($plantRows as $r) {
        $r['PLANT'] = $plantKey;
        calc_mcs_formulas($r);
        $rows[] = $r;
    }
}

usort($rows, function($a, $b) {
    $ca = isset($a['CUST_CODE']) ? trim((string)$a['CUST_CODE']) : '';
    $cb = isset($b['CUST_CODE']) ? trim((string)$b['CUST_CODE']) : '';
    
    if ($ca === '' && $cb !== '') return -1;
    if ($ca !== '' && $cb === '') return 1;

    $cmp = strcmp($ca, $cb);
    if ($cmp !== 0) return $cmp;
    $na = isset($a['CUST_COMP']) ? trim((string)$a['CUST_COMP']) : '';
    $nb = isset($b['CUST_COMP']) ? trim((string)$b['CUST_COMP']) : '';
    $cmp = strcmp($na, $nb);
    if ($cmp !== 0) return $cmp;
    $pa = isset($a['PLANT']) ? (string)$a['PLANT'] : '';
    $pb = isset($b['PLANT']) ? (string)$b['PLANT'] : '';
    $cmp = strcmp($pa, $pb);
    if ($cmp !== 0) return $cmp;
    $iaa = isset($a['ITEM_CODE']) ? (string)$a['ITEM_CODE'] : '';
    $ibb = isset($b['ITEM_CODE']) ? (string)$b['ITEM_CODE'] : '';
    $cmp = strcmp($iaa, $ibb);
    if ($cmp !== 0) return $cmp;
    $ia = isset($a['ID']) ? (int)$a['ID'] : 0;
    $ib = isset($b['ID']) ? (int)$b['ID'] : 0;
    if ($ia == $ib) return 0;
    return ($ia > $ib) ? -1 : 1;
});

if (count($rows) > $limit) $rows = array_slice($rows, 0, $limit);

if (getv('action') === 'json') {
    $jsonRows = array();
    foreach ($rows as $r) $jsonRows[] = normalize_row_for_json($r, $displayFields, $numberFields);
    json_response(array('status' => 'success', 'data' => $jsonRows, 'errors' => $connErrors));
}

if (getv('action') === 'excel') {
    $filename = 'MCS_Material_Composition_Standard_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $totalCols = count($displayFields);
    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="UTF-8">';
    echo '<style>td,th{border:1px solid #999;padding:4px;font-family:Arial;font-size:10pt;mso-number-format:"\\@";} .title{font-size:18pt;font-weight:bold;text-align:center;border:0;} .period{font-size:14pt;font-weight:bold;text-align:center;border:0;} .group{background:#dbeafe;font-weight:bold;color:#1e3a8a;} .head{background:#334155;color:#ffffff;font-weight:bold;text-align:center;}</style></head><body>';
    echo '<table border="1">';
    echo '<tr><td class="title" colspan="' . $totalCols . '">MATERIAL COMPOSITION STANDARD</td></tr>';
    echo '<tr><td class="period" colspan="' . $totalCols . '">Periode : ' . $periodeSekarang . '</td></tr>';
    echo '<tr><td colspan="' . $totalCols . '" style="border:0">&nbsp;</td></tr>';

    echo '<tr>';
    foreach ($displayFields as $f) {
        echo '<th class="head">' . h(isset($fieldLabels[$f]) ? $fieldLabels[$f] : $f) . '</th>';
    }
    echo '</tr>';

    $currentCustomer = null;
    foreach ($rows as $r) {
        $custCode = isset($r['CUST_CODE']) ? trim((string)$r['CUST_CODE']) : '';
        $custComp = isset($r['CUST_COMP']) ? trim((string)$r['CUST_COMP']) : '';
        $customerKey = strtoupper($custCode) . '|' . strtoupper($custComp);
        if ($customerKey !== $currentCustomer) {
            $currentCustomer = $customerKey;
            
            if ($custCode === '' && $custComp === '') {
                $customerText = 'TIDAK ADA CUSTOMER';
            } else {
                $customerText = 'CUSTOMER: ' . ($custCode !== '' ? $custCode : '-') . ' - ' . ($custComp !== '' ? $custComp : '-');
            }
            
            echo '<tr><td class="group" colspan="' . $totalCols . '">' . h($customerText) . '</td></tr>';
        }

        echo '<tr>';
        foreach ($displayFields as $f) {
            $value = '';
            if ($f === 'PLANT') {
                $value = isset($r['PLANT']) ? strtoupper((string)$r['PLANT']) : '';
            } else {
                $value = isset($r[$f]) ? fmt_cell($r[$f], $f, $numberFields) : '';
            }
            echo '<td>' . h($value) . '</td>';
        }
        echo '</tr>';
    }

    echo '</table></body></html>';
    exit;
}

$defaultNewPlant = default_new_plant($selectedPlant);
$blankRow = empty_row($mcsFields, $defaultNewPlant);
calc_mcs_formulas($blankRow);

function width_class($field) {
    if ($field === 'PLANT') return 'w-plant';
    if ($field === 'ITEM_NAME' || $field === 'MAT_NAME' || $field === 'REMARKS' || $field === 'CUST_COMP') return 'w-xl';
    if (in_array($field, array('ITEM_CODE','ITEM_NO','MAT_CODE','MAT_BRAND','MAT_GRADE','MAT_COLOUR_NO','MAT_COLOUR','MAT_MAKER'), true)) return 'w-md';
    return 'w-sm';
}

function render_mcs_row($r, $isNew, $displayFields, $numberFields, $formulaFields, $viewOnlyFields, $fieldLabels) {
    $id = isset($r['ID']) ? $r['ID'] : '';
    $plant = isset($r['PLANT']) ? strtolower((string)$r['PLANT']) : 'p1';
    if ($plant !== 'p1' && $plant !== 'p2') $plant = 'p1';

    $html = '<tr class="' . ($isNew ? 'new-row' : '') . '" data-id="' . h($id) . '" data-plant="' . h($plant) . '">';
    $html .= '<td class="action-cell"><div class="action-inner">';
    $html .= '<button type="button" class="btn btn-success small-btn btn-save">' . ($isNew ? 'Save' : 'Update') . '</button>';
    if ($isNew) {
        $html .= '<button type="button" class="btn btn-light small-btn btn-clear">Clear</button>';
        $html .= '<span class="row-status">New</span>';
    } else {
        $html .= '<button type="button" class="btn btn-danger small-btn btn-delete">Hapus</button>';
        $html .= '<span class="row-status"></span>';
    }
    $html .= '</div></td>';

    foreach ($displayFields as $f) {
        $value = isset($r[$f]) ? fmt_cell($r[$f], $f, $numberFields) : '';
        if ($f === 'PLANT') $value = $plant;
        $isFormula = in_array($f, $formulaFields, true);
        $isNumber = in_array($f, $numberFields, true);
        $isView = in_array($f, $viewOnlyFields, true);
        $tdClass = ($isFormula || $isView) ? 'formula-cell ' : '';
        $tdClass .= width_class($f);
        $html .= '<td class="' . h($tdClass) . '">';
        if ($f === 'PLANT') {
            if ($isNew) {
                $html .= '<select class="cell-input plant-select" data-field="PLANT"><option value="p1"' . ($plant === 'p1' ? ' selected' : '') . '>P1</option><option value="p2"' . ($plant === 'p2' ? ' selected' : '') . '>P2</option></select>';
            } else {
                $html .= '<input class="cell-input readonly center" type="text" data-field="PLANT" value="' . h($plant) . '" readonly="readonly" autocomplete="off">';
            }
        } elseif ($isView) {
            $html .= '<input class="cell-input readonly" type="text" data-view-field="' . h($f) . '" value="' . h($value) . '" readonly="readonly" autocomplete="off">';
        } else {
            $inputClass = 'cell-input ' . ($isNumber ? 'num ' : '') . ($isFormula ? 'readonly ' : '') . ($f === 'ITEM_CODE' ? 'item-code-input ' : '') . ($f === 'MAC' ? 'mac-input ' : '') . ($f === 'TONAGE' ? 'tonage-input ' : '') . ($f === 'MAT_CODE' ? 'mat-code-input ' : '');
            $readonly = $isFormula ? ' readonly="readonly" tabindex="0"' : '';
            $html .= '<input class="' . h($inputClass) . '" type="text" data-field="' . h($f) . '" value="' . h($value) . '"' . $readonly . ' autocomplete="off">';
        }
        $html .= '</td>';
    }

    $html .= '</tr>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Material Composition Standard</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Segoe UI,Tahoma,Arial,sans-serif;background:#eef2f7;color:#172033;padding:14px;font-size:12px}
.container{max-width:1900px;margin:0 auto}.header{background:linear-gradient(135deg,#0f172a,#1e3a8a);color:#fff;padding:14px 18px;border-radius:10px 10px 0 0;display:flex;justify-content:space-between;gap:12px;align-items:center}.header h1{font-size:20px;margin-bottom:3px}.header p{opacity:.82;font-size:11px}.badge{display:inline-block;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.25);padding:6px 10px;border-radius:20px;font-weight:700;font-size:11px}.card{background:#fff;border:1px solid #d5dfeb;box-shadow:0 4px 14px rgba(15,23,42,.06);border-radius:10px;margin-bottom:12px}.card-title{padding:10px 14px;border-bottom:1px solid #e5edf6;font-weight:800;color:#0f172a;background:#f8fafc;border-radius:10px 10px 0 0}.card-body{padding:12px 14px}.notice{display:none;padding:9px 12px;border-radius:8px;margin:10px 0;font-weight:700}.notice.show{display:block}.notice.success{background:#ecfdf3;color:#166534;border:1px solid #bbf7d0}.notice.error{background:#fff1f2;color:#991b1b;border:1px solid #fecaca}.notice.info{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}.notice.warning{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa}.filter-row{display:flex;gap:8px;align-items:end;flex-wrap:wrap}.field{display:flex;flex-direction:column;gap:4px;position:relative}.field label{font-size:10px;text-transform:uppercase;color:#64748b;font-weight:800;letter-spacing:.3px}.field input,.field select{height:32px;border:1px solid #cbd5e1;border-radius:6px;padding:6px 8px;background:#fff;font-size:12px}.field input{min-width:270px}.btn{border:0;border-radius:6px;padding:8px 11px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-block;font-size:11px;white-space:nowrap}.btn-primary{background:#1d4ed8;color:#fff}.btn-success{background:#15803d;color:#fff}.btn-danger{background:#dc2626;color:#fff}.btn-light{background:#e2e8f0;color:#1f2937}.btn-warning{background:#f59e0b;color:#fff}.help{font-size:11px;color:#64748b;margin-top:8px;line-height:1.5}.help b{color:#0f172a}.formula-note{background:#fffde7;border:1px solid #fde68a;color:#854d0e;border-radius:8px;padding:8px 10px;margin-top:9px;font-size:11px;line-height:1.45}.server-note{margin-top:8px;font-size:11px;color:#334155}.server-ok{color:#15803d;font-weight:800}.server-bad{color:#dc2626;font-weight:800}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px}.table-wrap{overflow:auto;max-height:680px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;position:relative}table{width:100%;border-collapse:collapse;min-width:2800px}th{position:sticky;top:0;background:#334155;color:#fff;font-size:10px;text-transform:uppercase;letter-spacing:.25px;padding:8px 6px;text-align:left;z-index:3;white-space:nowrap;border-right:1px solid rgba(255,255,255,.14)}td{padding:0;border-bottom:1px solid #e5edf6;border-right:1px solid #eef2f7;white-space:nowrap;vertical-align:middle;background:#fff}tbody tr:nth-child(even) td{background:#fbfdff}tbody tr:hover td{background:#f8fbff}.new-row td{background:#f0fdf4!important}.new-row .row-status{color:#15803d;font-weight:800}.formula-cell{background:#f8fafc!important}.formula-cell input{background:#f1f5f9;color:#475569;font-weight:700}.action-cell{position:sticky;left:0;z-index:2;background:#fff!important;padding:4px 5px;min-width:250px}.new-row .action-cell{background:#f0fdf4!important}.action-inner{display:flex;gap:4px;align-items:center;flex-wrap:nowrap}.cell-input{width:100%;height:31px;border:0;padding:6px 7px;font-size:12px;background:transparent;outline:none}.cell-input:focus{box-shadow:inset 0 0 0 2px #2563eb;background:#eff6ff}.cell-input.readonly:focus{box-shadow:inset 0 0 0 2px #64748b;background:#e2e8f0}.plant-select{font-weight:800;color:#1d4ed8}.num{text-align:right;font-variant-numeric:tabular-nums}.center{text-align:center;text-transform:uppercase}.w-plant{min-width:70px}.w-sm{min-width:82px}.w-md{min-width:120px}.w-lg{min-width:180px}.w-xl{min-width:260px}.row-status{font-size:10px;color:#64748b;margin-left:4px}.sticky-head-action{left:0;z-index:4}.small-btn{padding:6px 8px;font-size:10px}.calc-badge{display:inline-block;background:#e2e8f0;color:#334155;border-radius:10px;padding:2px 6px;font-size:9px;margin-left:4px}.suggest-box{position:absolute;z-index:9999;background:#fff;border:1px solid #94a3b8;border-radius:8px;box-shadow:0 10px 25px rgba(15,23,42,.18);max-height:260px;overflow:auto;display:none;min-width:360px}.suggest-item{padding:8px 10px;border-bottom:1px solid #e2e8f0;cursor:pointer;line-height:1.35}.suggest-item:hover,.suggest-item.active{background:#eff6ff}.sg-code{font-weight:900;color:#0f172a}.sg-plant{display:inline-block;background:#dbeafe;color:#1d4ed8;border-radius:10px;padding:1px 6px;font-size:10px;margin-right:5px;font-weight:900}.sg-name{color:#475569}.sg-empty{padding:9px;color:#64748b}.customer-group-row td{background:#dbeafe!important;color:#1e3a8a;font-weight:900;border-top:2px solid #93c5fd;border-bottom:1px solid #93c5fd;padding:8px 10px!important}.customer-group-meta{float:right;color:#475569;font-weight:700}
.readonly{cursor:not-allowed}@media(max-width:900px){body{padding:8px}.header{flex-direction:column;align-items:flex-start}.field input{min-width:100%}.filter-row{flex-direction:column;align-items:stretch}.toolbar{align-items:stretch}.toolbar .btn{width:100%}}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>📋 MATERIAL COMPOSITION STANDARD</h1>
            <p><b>Periode : <?php echo $periodeSekarang; ?></b> | Group by Customer | Gabungan Plant 1 & Plant 2.</p>
        </div>
        <div class="badge">Table: MCS + ITEMS + CUSTOMER</div>
    </div>

    <div id="notice" class="notice"></div>
    <?php if (count($connErrors) > 0): ?>
        <div class="notice show warning"><?php echo h(implode(' | ', $connErrors)); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">Filter Data</div>
        <div class="card-body">
            <form method="get" action="mcs.php" class="filter-row" id="filterForm">
                <div class="field">
                    <label>Plant</label>
                    <select name="plant" id="plantFilter">
                        <option value="all" <?php echo ($selectedPlant === 'all') ? 'selected' : ''; ?>>Gabungan P1 & P2</option>
                        <option value="p1" <?php echo ($selectedPlant === 'p1') ? 'selected' : ''; ?>>Plant 1</option>
                        <option value="p2" <?php echo ($selectedPlant === 'p2') ? 'selected' : ''; ?>>Plant 2</option>
                    </select>
                </div>
                <div class="field">
                    <label>Customer</label>
                    <input type="text" name="customer" id="customerFilter" value="<?php echo h($customerSearch); ?>" placeholder="Ketik kode / nama customer" autocomplete="off">
                </div>
                <div class="field">
                    <label>Item Code / Item No / Item Name / Material / Machine</label>
                    <input type="text" name="q" id="qSearch" value="<?php echo h($qSearch); ?>" placeholder="Ketik ITEM_CODE lalu pilih autocomplete" autocomplete="off">
                </div>
                <div class="field">
                    <label>Limit</label>
                    <select name="limit">
                        <?php foreach (array(100, 300, 500, 1000, 2000, 5000) as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php echo ($limit == $opt) ? 'selected' : ''; ?>><?php echo $opt; ?> baris</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">🔍 Cari</button>
                <a class="btn btn-light" href="mcs.php">Tampilkan Semua</a>
                <a class="btn btn-warning" href="mcs.php?action=json&plant=<?php echo urlencode($selectedPlant); ?><?php echo ($customerSearch !== '') ? '&customer=' . urlencode($customerSearch) : ''; ?><?php echo ($qSearch !== '') ? '&q=' . urlencode($qSearch) : ''; ?>" target="_blank">JSON</a>
                <a class="btn btn-success" href="mcs.php?action=excel&plant=<?php echo urlencode($selectedPlant); ?><?php echo ($customerSearch !== '') ? '&customer=' . urlencode($customerSearch) : ''; ?><?php echo ($qSearch !== '') ? '&q=' . urlencode($qSearch) : ''; ?>&limit=<?php echo urlencode($limit); ?>">⬇ Export Excel</a>
            </form>
            <div class="server-note">
                Status server:
                <span class="<?php echo $serverAvailable['p1'] ? 'server-ok' : 'server-bad'; ?>">P1 <?php echo $serverAvailable['p1'] ? 'OK' : 'OFFLINE'; ?></span> |
                <span class="<?php echo $serverAvailable['p2'] ? 'server-ok' : 'server-bad'; ?>">P2 <?php echo $serverAvailable['p2'] ? 'OK' : 'OFFLINE'; ?></span>
            </div>
            <div class="help">
                <b>Shortcut:</b> Enter = save row, panah kiri/kanan/atas/bawah = pindah cell, Ctrl+S = save row aktif. Tombol Tambah Data Baru membuat row kosong di posisi paling atas. Tombol row: Update dan Hapus. ID hidden. TRIAL_DATE text/VARCHAR. Hapus dan Update wajib pakai password <b>q9tj9</b>. Autocomplete: CUSTOMER, ITEM_CODE, MAC, TONAGE/MAG, MAT_CODE.
            </div>
            <div class="formula-note">
                <b>Kolom rumus readonly:</b>
                R_PCS = CAV / R_SHOT;
                SHOT = (CAV × PART_WEIGHT) + R_SHOT;
                PCS = PART_WEIGHT × 1.02;
                RUNNER_% = R_SHOT / SHOT × 100;
                NET_WEIGHT = ((SHOT / CAV / 100) × (100 - RUNNER_MIN)) × 1.02;
                TARGET_DAY = 3600 / CYTM × CAV × 24 × 0.9.
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Daftar Data MCS — <?php echo count($rows); ?> data</div>
        <div class="card-body">
            <div class="toolbar">
                <button type="button" class="btn btn-success" onclick="addNewTopRow(true)">➕ Tambah Data Baru</button>
                <a class="btn btn-success" href="mcs.php?action=excel&plant=<?php echo urlencode($selectedPlant); ?><?php echo ($customerSearch !== '') ? '&customer=' . urlencode($customerSearch) : ''; ?><?php echo ($qSearch !== '') ? '&q=' . urlencode($qSearch) : ''; ?>&limit=<?php echo urlencode($limit); ?>">⬇ Export Excel</a>
                <button type="button" class="btn btn-danger" onclick="deleteBlankP2()">🗑 Hapus Kosong P2</button>
                <a href="mcs_edit_log.txt" target="_blank" class="btn btn-primary" style="margin-left: auto;">📄 Lihat Log Edit</a>
            </div>
            <div class="table-wrap" id="tableWrap">
                <table id="mcsTable">
                    <thead>
                    <tr>
                        <th class="sticky-head-action">Action</th>
                        <?php foreach ($displayFields as $f): ?>
                            <th class="<?php echo h(width_class($f)); ?>">
                                <?php echo h($fieldLabels[$f]); ?><?php echo in_array($f, $formulaFields, true) ? '<span class="calc-badge">AUTO</span>' : ''; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody id="mcsBody">
                    <?php echo render_mcs_row($blankRow, true, $displayFields, $numberFields, $formulaFields, $viewOnlyFields, $fieldLabels); ?>
                    <?php
                    $currentCustomerKey = null;
                    $groupColspan = count($displayFields) + 1; // Otomatis menyesuaikan jumlah kolom yang baru
                    foreach ($rows as $r):
                        $custCode = isset($r['CUST_CODE']) ? trim((string)$r['CUST_CODE']) : '';
                        $custComp = isset($r['CUST_COMP']) ? trim((string)$r['CUST_COMP']) : '';
                        $customerKey = strtoupper($custCode) . '|' . strtoupper($custComp);
                        if ($customerKey !== $currentCustomerKey):
                            $currentCustomerKey = $customerKey;
                    ?>
                        <tr class="customer-group-row">
                            <td colspan="<?php echo $groupColspan; ?>">
                                <?php if ($custCode === '' && $custComp === ''): ?>
                                    TIDAK ADA CUSTOMER
                                <?php else: ?>
                                    CUSTOMER: <?php echo h(($custCode !== '' ? $custCode : '-') . ' - ' . ($custComp !== '' ? $custComp : '-')); ?>
                                <?php endif; ?>
                                <span class="customer-group-meta">MATERIAL COMPOSITION STANDARD | Periode : <?php echo $periodeSekarang; ?></span>
                            </td>
                        </tr>
                    <?php endif; ?>
                        <?php echo render_mcs_row($r, false, $displayFields, $numberFields, $formulaFields, $viewOnlyFields, $fieldLabels); ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="suggestBox" class="suggest-box"></div>

<script>
var DISPLAY_FIELDS = <?php echo json_encode($displayFields); ?>;
var MCS_FIELDS = <?php echo json_encode($mcsFields); ?>;
var NUMBER_FIELDS = <?php echo json_encode($numberFields); ?>;
var FORMULA_FIELDS = <?php echo json_encode($formulaFields); ?>;
var VIEW_ONLY_FIELDS = <?php echo json_encode($viewOnlyFields); ?>;
var DEFAULT_NEW_PLANT = <?php echo json_encode($defaultNewPlant); ?>;

function byId(id){return document.getElementById(id);}
function hasClass(el, cls){return (' '+el.className+' ').indexOf(' '+cls+' ')>-1;}
function addClass(el, cls){if(!hasClass(el, cls))el.className=(el.className+' '+cls).replace(/^\s+|\s+$/g,'');}
function removeClass(el, cls){el.className=(' '+el.className+' ').replace(' '+cls+' ',' ').replace(/^\s+|\s+$/g,'');}
function isFormulaField(field){for(var i=0;i<FORMULA_FIELDS.length;i++){if(FORMULA_FIELDS[i]===field)return true;}return false;}
function isNumberField(field){for(var i=0;i<NUMBER_FIELDS.length;i++){if(NUMBER_FIELDS[i]===field)return true;}return false;}
function isViewOnlyField(field){for(var i=0;i<VIEW_ONLY_FIELDS.length;i++){if(VIEW_ONLY_FIELDS[i]===field)return true;}return false;}
function esc(s){return String(s===null||s===undefined?'':s).replace(/[&<>'"]/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch];});}
function num(value){var s=String(value===null||value===undefined?'':value).replace(',','.').replace(/^\s+|\s+$/g,'');if(s==='')return null;var n=parseFloat(s);return isNaN(n)?null:n;}
function outnum(value, dec){if(value===null||value===undefined||isNaN(value))return '';var n=Number(value);return n.toFixed(dec||6).replace(/\.0+$/,'').replace(/(\.\d*?)0+$/,'$1');}

function showNotice(type, msg){
    var n=byId('notice');
    n.className='notice show '+type;
    n.innerHTML=esc(msg);
    clearTimeout(window._noticeTimer);
    window._noticeTimer=setTimeout(function(){n.className='notice';},3500);
}

function rowControls(tr){return tr.querySelectorAll ? tr.querySelectorAll('.cell-input') : tr.getElementsByTagName('input');}
function dataControls(tr){return tr.querySelectorAll ? tr.querySelectorAll('[data-field]') : tr.getElementsByTagName('input');}
function getInput(tr, field){
    var els=tr.querySelectorAll ? tr.querySelectorAll('[data-field="'+field+'"], [data-view-field="'+field+'"]') : [];
    return els.length?els[0]:null;
}
function setVal(tr, field, value){var inp=getInput(tr,field);if(inp)inp.value=value;}
function getVal(tr, field){var inp=getInput(tr,field);return inp?inp.value:'';}
function getPlant(tr){var p=getInput(tr,'PLANT');return p?p.value:(tr.getAttribute('data-plant')||DEFAULT_NEW_PLANT);}

function calculateRow(tr){
    var cav=num(getVal(tr,'CAV'));
    var rshot=num(getVal(tr,'R_SHOT'));
    var partWeight=num(getVal(tr,'PART_WEIGHT'));
    var cytm=num(getVal(tr,'CYTM'));
    var runnerMin=num(getVal(tr,'RUNNER_MIN'));
    var rpcs=null, shot=null, pcs=null, runnerPersen=null, netWeight=null, targetDay=null;
    if(cav!==null && rshot!==null && rshot!==0) rpcs=cav/rshot;
    if(cav!==null && partWeight!==null) shot=(cav*partWeight)+(rshot!==null?rshot:0);
    if(partWeight!==null) pcs=partWeight*1.02;
    if(rshot!==null && shot!==null && shot!==0) runnerPersen=(rshot/shot)*100;
    
    // Perbaikan NET_WEIGHT JavaScript: Jika RUNNER_MIN kosong, paksa nilainya menjadi 0 dalam rumus.
    var runnerMinVal = runnerMin !== null ? runnerMin : 0;
    if(shot!==null && cav!==null && cav!==0) netWeight=((shot/cav/100)*(100-runnerMinVal))*1.02;
    
    if(cytm!==null && cytm!==0 && cav!==null) targetDay=(3600/cytm)*cav*24*0.9;
    
    setVal(tr,'R_PCS',outnum(rpcs,6));
    setVal(tr,'SHOT',outnum(shot,6));
    setVal(tr,'PCS',outnum(pcs,6));
    setVal(tr,'RUNNER_PERSEN',outnum(runnerPersen,6));
    setVal(tr,'NET_WEIGHT',outnum(netWeight,6));
    setVal(tr,'TARGET_DAY',outnum(targetDay,6));
}

function rowHasAnyValue(tr){
    var els=dataControls(tr);
    for(var i=0;i<els.length;i++){
        var f=els[i].getAttribute('data-field');
        if(f && f!=='PLANT' && !els[i].readOnly && String(els[i].value).replace(/^\s+|\s+$/g,'')!=='') return true;
    }
    return false;
}

function serializeRow(tr){
    calculateRow(tr);
    var data='action=save_row&ID='+encodeURIComponent(tr.getAttribute('data-id')||'');
    var els=dataControls(tr);
    for(var i=0;i<els.length;i++){
        var f=els[i].getAttribute('data-field');
        if(f){data+='&'+encodeURIComponent(f)+'='+encodeURIComponent(els[i].value);}
    }
    return data;
}

function ajaxPost(data, callback){
    var xhr=new XMLHttpRequest();
    xhr.open('POST','mcs.php',true);
    xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded; charset=UTF-8');
    xhr.onreadystatechange=function(){
        if(xhr.readyState===4){
            var json=null;
            try{json=JSON.parse(xhr.responseText);}catch(e){json={status:'error',message:'Response bukan JSON: '+xhr.responseText};}
            callback(json);
        }
    };
    xhr.send(data);
}

function saveRow(tr, moveAfterSave){
    if(!tr)return;
    calculateRow(tr);
    if(hasClass(tr,'new-row') && !rowHasAnyValue(tr)){
        showNotice('info','Row baru masih kosong.');
        return;
    }
    
    var isUpdate = tr.getAttribute('data-id') && tr.getAttribute('data-id') !== '';
    var inputPwd = '';
    
    if (isUpdate) {
        inputPwd = prompt('Masukkan password untuk update data MCS:');
        if (inputPwd === null) return;
        if (inputPwd === '') {
            showNotice('error', 'Password update wajib diisi.');
            return;
        }
    }

    var status=tr.querySelector ? tr.querySelector('.row-status') : null;
    if(status)status.innerHTML='Saving...';
    
    var reqData = serializeRow(tr);
    if (isUpdate) reqData += '&save_password=' + encodeURIComponent(inputPwd);

    ajaxPost(reqData,function(res){
        if(res.status==='success'){
            updateRowFromResponse(tr,res.row);
            if(res.mode==='insert'){
                removeClass(tr,'new-row');
                setExistingActions(tr);
                addNewTopRow(false);
            }
            if(status)status.innerHTML='Saved';
            showNotice('success',res.message || 'Saved.');
            if(moveAfterSave) moveVerticalFromActive(1);
        }else if(res.status==='empty'){
            if(status)status.innerHTML='New';
            showNotice('info',res.message || 'Row kosong.');
        }else{
            if(status)status.innerHTML='Error';
            showNotice('error',res.message || 'Gagal save.');
        }
    });
}

function updateRowFromResponse(tr,row){
    if(!row)return;
    tr.setAttribute('data-id',row.ID||'');
    tr.setAttribute('data-plant',row.PLANT||getPlant(tr));
    for(var i=0;i<DISPLAY_FIELDS.length;i++){
        var f=DISPLAY_FIELDS[i];
        var inp=getInput(tr,f);
        if(inp && row[f]!==undefined) inp.value=row[f];
    }
}

function deleteRow(tr){
    var id=tr.getAttribute('data-id')||'';
    if(id===''){
        clearRow(tr);
        return;
    }
    var plant=getPlant(tr);
    var pwd=prompt('Masukkan password untuk hapus data MCS:');
    if(pwd===null)return;
    if(pwd===''){
        showNotice('error','Password hapus wajib diisi.');
        return;
    }
    if(!confirm('Hapus data MCS ini dari '+plant.toUpperCase()+'?'))return;
    ajaxPost('action=delete_row&ID='+encodeURIComponent(id)+'&PLANT='+encodeURIComponent(plant)+'&delete_password='+encodeURIComponent(pwd),function(res){
        if(res.status==='success'){
            tr.parentNode.removeChild(tr);
            showNotice('success',res.message || 'Deleted.');
        }else{
            showNotice('error',res.message || 'Gagal hapus.');
        }
    });
}

function clearRow(tr){
    var els=rowControls(tr);
    for(var i=0;i<els.length;i++){
        if(els[i].getAttribute('data-field')==='PLANT') continue;
        els[i].value='';
    }
    calculateRow(tr);
}

function inputHtml(field,value,isNew){
    if(field==='PLANT'){
        var plant=value||DEFAULT_NEW_PLANT;
        if(isNew){return '<select class="cell-input plant-select" data-field="PLANT"><option value="p1"'+(plant==='p1'?' selected':'')+'>P1</option><option value="p2"'+(plant==='p2'?' selected':'')+'>P2</option></select>';}
        return '<input class="cell-input readonly center" type="text" data-field="PLANT" value="'+esc(plant)+'" readonly="readonly" autocomplete="off">';
    }
    if(isViewOnlyField(field)){
        return '<input class="cell-input readonly" type="text" data-view-field="'+esc(field)+'" value="'+esc(value||'')+'" readonly="readonly" autocomplete="off">';
    }
    var formula=isFormulaField(field);
    var cls='cell-input '+(isNumberField(field)?'num ':'')+(formula?'readonly ':'')+(field==='ITEM_CODE'?'item-code-input ':'')+(field==='MAC'?'mac-input ':'')+(field==='TONAGE'?'tonage-input ':'')+(field==='MAT_CODE'?'mat-code-input ':'');
    var ro=formula?' readonly="readonly" tabindex="0"':'';
    return '<input class="'+cls+'" type="text" data-field="'+esc(field)+'" value="'+esc(value||'')+'"'+ro+' autocomplete="off">';
}

function widthClass(field){
    if(field==='PLANT')return 'w-plant';
    if(field==='ITEM_NAME'||field==='MAT_NAME'||field==='REMARKS')return 'w-xl';
    if(field==='ITEM_CODE'||field==='ITEM_NO'||field==='MAT_CODE'||field==='MAT_BRAND'||field==='MAT_GRADE'||field==='MAT_COLOUR_NO'||field==='MAT_COLOUR'||field==='MAT_MAKER')return 'w-md';
    return 'w-sm';
}

function rowHtml(row,isNew){
    var plant=row.PLANT||DEFAULT_NEW_PLANT;
    var html='<tr class="'+(isNew?'new-row':'')+'" data-id="'+esc(row.ID||'')+'" data-plant="'+esc(plant)+'">';
    html+='<td class="action-cell"><div class="action-inner"><button type="button" class="btn btn-success small-btn btn-save">'+(isNew?'Save':'Update')+'</button>';
    if(isNew) html+='<button type="button" class="btn btn-light small-btn btn-clear">Clear</button>';
    else {
        html+='<button type="button" class="btn btn-danger small-btn btn-delete">Hapus</button>';
    }
    html+='<span class="row-status">'+(isNew?'New':'')+'</span></div></td>';
    for(var i=0;i<DISPLAY_FIELDS.length;i++){
        var f=DISPLAY_FIELDS[i];
        html+='<td class="'+(isFormulaField(f)||isViewOnlyField(f)?'formula-cell ':'')+widthClass(f)+'">'+inputHtml(f,row[f]||'',isNew)+'</td>';
    }
    html+='</tr>';
    return html;
}

function addNewTopRow(focusFirst){
    var tbody=byId('mcsBody');
    var first=tbody.getElementsByTagName('tr')[0];
    if(first && hasClass(first,'new-row') && !rowHasAnyValue(first)){
        if(focusFirst){var item=getInput(first,'ITEM_CODE'); if(item)item.focus();}
        return;
    }
    var row={ID:'',PLANT:DEFAULT_NEW_PLANT,ITEM_NO:'',ITEM_NAME:''};
    for(var i=0;i<MCS_FIELDS.length;i++)row[MCS_FIELDS[i]]='';
    
    var wrap = document.createElement('table'); 
    wrap.innerHTML = '<tbody>' + rowHtml(row,true) + '</tbody>';
    var tr = wrap.querySelector('tr');
    
    if(first) tbody.insertBefore(tr, first); else tbody.appendChild(tr);
    bindRow(tr);
    if(focusFirst){var inp=getInput(tr,'ITEM_CODE'); if(inp)inp.focus();}
}

function setExistingActions(tr){
    var action=tr.getElementsByClassName('action-cell')[0];
    var plant=getPlant(tr);
    action.innerHTML='<div class="action-inner"><button type="button" class="btn btn-success small-btn btn-save">Update</button><button type="button" class="btn btn-danger small-btn btn-delete">Hapus</button><span class="row-status"></span></div>';
    bindRowButtons(tr);
    var plant=getInput(tr,'PLANT');
    if(plant && plant.tagName && plant.tagName.toLowerCase()==='select'){
        var val=plant.value;
        var td=plant.parentNode;
        td.innerHTML='<input class="cell-input readonly center" type="text" data-field="PLANT" value="'+esc(val)+'" readonly="readonly" autocomplete="off">';
    }
    bindRow(tr);
}

function bindRowButtons(tr){
    var save=tr.getElementsByClassName('btn-save')[0];
    var del=tr.getElementsByClassName('btn-delete')[0];
    var clr=tr.getElementsByClassName('btn-clear')[0];
    if(save)save.onclick=function(){saveRow(tr,false);};
    if(del)del.onclick=function(){deleteRow(tr);};
    if(clr)clr.onclick=function(){clearRow(tr);};
}

function bindRow(tr){
    bindRowButtons(tr);
    var inputs=rowControls(tr);
    for(var i=0;i<inputs.length;i++){
        inputs[i].setAttribute('data-col-index',i);
        inputs[i].oninput=function(){
            var row=this.parentNode.parentNode;
            calculateRow(row);
            var status=row.getElementsByClassName('row-status')[0];
            if(status)status.innerHTML='Edited';
            var f=this.getAttribute('data-field');
            if(f==='ITEM_CODE') handleAutoInput(this,'item');
            else if(f==='MAC') handleAutoInput(this,'mac');
            else if(f==='TONAGE') handleAutoInput(this,'mag');
            else if(f==='MAT_CODE') handleAutoInput(this,'mat');
        };
        inputs[i].onkeydown=handleKey;
        inputs[i].onfocus=function(){if(this.select && !this.readOnly)this.select();};
        if(inputs[i].getAttribute('data-field')==='ITEM_CODE'){
            inputs[i].onblur=function(){lookupItemForRow(this); setTimeout(hideSuggest,180);};
        }else if(inputs[i].getAttribute('data-field')==='MAT_CODE'){
            inputs[i].onblur=function(){lookupMatForRow(this); setTimeout(hideSuggest,180);};
        }else if(inputs[i].getAttribute('data-field')==='MAC' || inputs[i].getAttribute('data-field')==='TONAGE'){
            inputs[i].onblur=function(){setTimeout(hideSuggest,180);};
        }
    }
    calculateRow(tr);
}

function bindAllRows(){
    var rows=byId('mcsBody').getElementsByTagName('tr');
    for(var i=0;i<rows.length;i++)bindRow(rows[i]);
}

function cellInputs(){return document.querySelectorAll ? document.querySelectorAll('.cell-input') : document.getElementsByClassName('cell-input');}
function focusInput(inp){if(inp){inp.focus(); if(inp.select && !inp.readOnly)inp.select();}}
function getRowIndex(tr){var rows=byId('mcsBody').getElementsByTagName('tr');for(var i=0;i<rows.length;i++)if(rows[i]===tr)return i;return -1;}
function moveHorizontal(input,dir){var inputs=cellInputs();for(var i=0;i<inputs.length;i++){if(inputs[i]===input){var target=inputs[i+dir];if(target)focusInput(target);return;}}}
function moveVertical(input,dir){var tr=input.parentNode.parentNode;var rowIdx=getRowIndex(tr);var colIdx=parseInt(input.getAttribute('data-col-index'),10);var rows=byId('mcsBody').getElementsByTagName('tr');var targetRow=rows[rowIdx+dir];if(targetRow){var inputs=rowControls(targetRow);if(inputs[colIdx])focusInput(inputs[colIdx]);}}
function moveVerticalFromActive(dir){var active=document.activeElement;if(active && hasClass(active,'cell-input')) moveVertical(active,dir);}
function handleKey(e){
    e=e||window.event;
    var code=e.keyCode||e.which;
    var tr=this.parentNode.parentNode;
    if(code===13){if(e.preventDefault)e.preventDefault(); else e.returnValue=false; saveRow(tr,true); return false;}
    if(e.ctrlKey && code===83){if(e.preventDefault)e.preventDefault(); else e.returnValue=false; saveRow(tr,false); return false;}
    if(code===37){if(e.preventDefault)e.preventDefault();moveHorizontal(this,-1);return false;}
    if(code===39){if(e.preventDefault)e.preventDefault();moveHorizontal(this,1);return false;}
    if(code===38){if(e.preventDefault)e.preventDefault();moveVertical(this,-1);return false;}
    if(code===40){if(e.preventDefault)e.preventDefault();moveVertical(this,1);return false;}
    return true;
}

// =============================
// AUTOCOMPLETE ITEM_CODE / MAC / TONAGE(MAG) / MAT_CODE
// =============================
var suggestTimer=null;
var activeSuggestInput=null;
var activeSuggestType='item';

function autoType(input){
    if(!input)return '';
    var f=input.getAttribute('data-field')||'';
    if(f==='ITEM_CODE')return 'item';
    if(f==='MAC')return 'mac';
    if(f==='TONAGE')return 'mag';
    if(f==='MAT_CODE')return 'mat';
    if(input.id==='customerFilter')return 'customer';
    if(input.id==='qSearch')return 'search_item';
    return '';
}
function positionSuggest(input){
    var box=byId('suggestBox');
    var r=input.getBoundingClientRect();
    box.style.left=(r.left+window.pageXOffset)+'px';
    box.style.top=(r.bottom+window.pageYOffset+2)+'px';
    box.style.minWidth=Math.max(360,r.width)+'px';
}
function hideSuggest(){var box=byId('suggestBox');box.style.display='none';box.innerHTML='';activeSuggestInput=null;activeSuggestType='item';}
function endpointForType(type){
    if(type==='customer')return 'customer_suggest';
    if(type==='mac')return 'mac_suggest';
    if(type==='mag')return 'mag_suggest';
    if(type==='mat')return 'mat_suggest';
    return 'item_suggest';
}
function fetchAutoSuggest(type,q,plant,cb){
    var xhr=new XMLHttpRequest();
    xhr.open('GET','mcs.php?action='+encodeURIComponent(endpointForType(type))+'&q='+encodeURIComponent(q)+'&plant='+encodeURIComponent(plant),true);
    xhr.onreadystatechange=function(){if(xhr.readyState===4){var res={data:[]};try{res=JSON.parse(xhr.responseText);}catch(e){}cb(res.data||[]);}};
    xhr.send();
}
function showSuggest(input, items, type){
    activeSuggestInput=input;
    activeSuggestType=type||autoType(input)||'item';
    var box=byId('suggestBox');
    positionSuggest(input);
    if(!items || !items.length){box.innerHTML='<div class="sg-empty">Data tidak ditemukan</div>';box.style.display='block';return;}
    var html='';
    for(var i=0;i<items.length;i++){
        var it=items[i];
        if(activeSuggestType==='mac' || activeSuggestType==='mag'){
            html+='<div class="suggest-item" data-type="'+esc(activeSuggestType)+'" data-value="'+esc(it.value)+'">'+
                '<span class="sg-plant">'+esc(it.plant_label||'')+'</span><span class="sg-code">'+esc(it.value)+'</span></div>';
        }else if(activeSuggestType==='customer'){
            html+='<div class="suggest-item" data-type="customer" data-plant="'+esc(it.plant)+'" data-code="'+esc(it.customer_code)+'" data-name="'+esc(it.customer_name)+'">'+
                '<span class="sg-plant">'+esc(it.plant_label||'')+'</span><span class="sg-code">'+esc(it.customer_code||'-')+'</span><br><span class="sg-name">'+esc(it.customer_name||'-')+'</span></div>';
        }else if(activeSuggestType==='mat'){
            html+='<div class="suggest-item" data-type="mat" data-plant="'+esc(it.plant)+'" data-code="'+esc(it.mat_code)+'" data-name="'+esc(it.mat_name)+'">'+
                '<span class="sg-plant">'+esc(it.plant_label)+'</span><span class="sg-code">'+esc(it.mat_code)+'</span><br><span class="sg-name">'+esc(it.mat_name)+'</span></div>';
        }else{
            html+='<div class="suggest-item" data-type="item" data-plant="'+esc(it.plant)+'" data-code="'+esc(it.item_code)+'" data-no="'+esc(it.item_no)+'" data-name="'+esc(it.item_name)+'">'+
                '<span class="sg-plant">'+esc(it.plant_label)+'</span><span class="sg-code">'+esc(it.item_code)+'</span> - <span>'+esc(it.item_no)+'</span><br><span class="sg-name">'+esc(it.item_name)+'</span></div>';
        }
    }
    box.innerHTML=html;
    box.style.display='block';
    var nodes=box.getElementsByClassName('suggest-item');
    for(var n=0;n<nodes.length;n++){
        nodes[n].onmousedown=function(e){if(e.preventDefault)e.preventDefault();selectSuggestion(this);return false;};
    }
}
function handleAutoInput(input, forcedType){
    var type=forcedType||autoType(input);
    var q=String(input.value||'').replace(/^\s+|\s+$/g,'');
    if(q.length<1){hideSuggest();return;}
    clearTimeout(suggestTimer);
    suggestTimer=setTimeout(function(){
        var tr=input.parentNode.parentNode;
        var plant=(input.id==='qSearch' || input.id==='customerFilter')?byId('plantFilter').value:getPlant(tr);
        fetchAutoSuggest(type,q,plant,function(items){showSuggest(input,items,type);});
    },160);
}
function selectSuggestion(node){
    var input=activeSuggestInput;
    if(!input)return;

    if(input.id==='customerFilter'){
        var customerCode=node.getAttribute('data-code')||'';
        var customerName=node.getAttribute('data-name')||'';
        byId('customerFilter').value=customerCode||customerName;
        hideSuggest();
        byId('filterForm').submit();
        return;
    }

    if(input.id==='qSearch'){
        byId('qSearch').value=node.getAttribute('data-code')||'';
        hideSuggest();
        byId('filterForm').submit();
        return;
    }

    var tr=input.parentNode.parentNode;
    var type=node.getAttribute('data-type')||activeSuggestType;

    if(type==='mac'){
        setVal(tr,'MAC',node.getAttribute('data-value')||'');
    }else if(type==='mag'){
        setVal(tr,'TONAGE',node.getAttribute('data-value')||'');
    }else if(type==='mat'){
        var matPlant=node.getAttribute('data-plant')||getPlant(tr);
        var code=node.getAttribute('data-code')||'';
        var name=node.getAttribute('data-name')||'';
        setVal(tr,'MAT_CODE',code);
        setVal(tr,'MAT_NAME',name);
        tr.setAttribute('data-last-mat-plant',matPlant);
    }else{
        var plant=node.getAttribute('data-plant')||getPlant(tr);
        var itemCode=node.getAttribute('data-code')||'';
        var no=node.getAttribute('data-no')||'';
        var name=node.getAttribute('data-name')||'';
        if(getInput(tr,'PLANT') && hasClass(tr,'new-row')) getInput(tr,'PLANT').value=plant;
        tr.setAttribute('data-plant',plant);
        setVal(tr,'ITEM_CODE',itemCode);
        setVal(tr,'ITEM_NO',no);
        setVal(tr,'ITEM_NAME',name);
    }

    calculateRow(tr);
    var status=tr.getElementsByClassName('row-status')[0];
    if(status)status.innerHTML='Edited';
    hideSuggest();
    input.focus();
}
function lookupItemForRow(input){
    var val=String(input.value||'').replace(/^\s+|\s+$/g,'');
    if(val==='')return;
    var tr=input.parentNode.parentNode;
    var plant=getPlant(tr);
    var xhr=new XMLHttpRequest();
    xhr.open('GET','mcs.php?action=item_lookup&item_code='+encodeURIComponent(val)+'&plant='+encodeURIComponent(plant),true);
    xhr.onreadystatechange=function(){
        if(xhr.readyState===4){
            var res=null;try{res=JSON.parse(xhr.responseText);}catch(e){}
            if(res && res.status==='success'){
                setVal(tr,'ITEM_NO',res.item_no||'');
                setVal(tr,'ITEM_NAME',res.item_name||'');
            }
        }
    };
    xhr.send();
}
function lookupMatForRow(input){
    var val=String(input.value||'').replace(/^\s+|\s+$/g,'');
    if(val==='')return;
    var tr=input.parentNode.parentNode;
    var plant=getPlant(tr);
    var xhr=new XMLHttpRequest();
    xhr.open('GET','mcs.php?action=mat_lookup&mat_code='+encodeURIComponent(val)+'&plant='+encodeURIComponent(plant),true);
    xhr.onreadystatechange=function(){
        if(xhr.readyState===4){
            var res=null;try{res=JSON.parse(xhr.responseText);}catch(e){}
            if(res && res.status==='success' && res.mat_code){
                setVal(tr,'MAT_CODE',res.mat_code||val);
                setVal(tr,'MAT_NAME',res.mat_name||'');
            }
        }
    };
    xhr.send();
}

var customerInput=byId('customerFilter');
if(customerInput){
    customerInput.oninput=function(){handleAutoInput(this,'customer');};
    customerInput.onblur=function(){setTimeout(hideSuggest,180);};
}

var qInput=byId('qSearch');
if(qInput){
    qInput.oninput=function(){handleAutoInput(this,'item');};
    qInput.onblur=function(){setTimeout(hideSuggest,180);};
}

function moveOneToP1(tr){
    var id=tr.getAttribute('data-id')||'';
    var plant=getPlant(tr);
    if(id===''){
        showNotice('info','Row baru belum punya ID. Save dulu sebelum pindahkan.');
        return;
    }
    if(plant!=='p2'){
        showNotice('info','Hanya data P2 yang bisa dipindahkan ke P1.');
        return;
    }
    var pwd=prompt('Password untuk pindahkan row ini ke MCS P1:');
    if(pwd===null)return;
    if(!confirm('Pindahkan row ini dari P2 ke MCS P1?'))return;
    ajaxPost('action=move_one_to_p1&ID='+encodeURIComponent(id)+'&PLANT='+encodeURIComponent(plant)+'&move_password='+encodeURIComponent(pwd),function(res){
        if(res.status==='success'){
            showNotice('success',res.message||'Row berhasil dipindahkan.');
            setTimeout(function(){window.location.reload();},800);
        }else if(res.status==='warning'){
            showNotice('warning',res.message||'Selesai dengan warning.');
            setTimeout(function(){window.location.reload();},1200);
        }else{
            showNotice('error',res.message||'Gagal pindahkan row.');
        }
    });
}

function moveBlankToP1(){
    var pwd=prompt('Password untuk pindahkan data kosong P2 ke MCS P1:');
    if(pwd===null)return;
    if(!confirm('Pindahkan data P2 yang ITEM_NO/ITEM_NAME kosong tetapi ITEM_CODE ada di ITEMS P1 ke tabel MCS P1?'))return;
    ajaxPost('action=move_blank_to_p1&move_password='+encodeURIComponent(pwd),function(res){
        if(res.status==='success'){
            showNotice('success',res.message||'Selesai pindah.');
            setTimeout(function(){window.location.reload();},900);
        }else if(res.status==='warning'){
            showNotice('warning',res.message||'Selesai dengan warning.');
            setTimeout(function(){window.location.reload();},1200);
        }else{
            showNotice('error',res.message||'Gagal pindah.');
        }
    });
}

function deleteBlankP2(){
    var pwd=prompt('Password untuk hapus data kosong MCS Plant 2:');
    if(pwd===null)return;
    if(!confirm('Hapus SEMUA data kosong di MCS Plant 2? Kriteria: ITEM_CODE kosong atau ITEM_CODE tidak ketemu di ITEMS P2.'))return;
    ajaxPost('action=delete_blank_p2&delete_password='+encodeURIComponent(pwd),function(res){
        if(res.status==='success'){
            showNotice('success',res.message||'Data kosong P2 berhasil dihapus.');
            setTimeout(function(){window.location.reload();},900);
        }else if(res.status==='warning'){
            showNotice('warning',res.message||'Selesai dengan warning.');
            setTimeout(function(){window.location.reload();},1200);
        }else{
            showNotice('error',res.message||'Gagal hapus data kosong P2.');
        }
    });
}

document.addEventListener('click',function(e){
    var t=e.target||e.srcElement;
    if(t && (hasClass(t,'item-code-input') || hasClass(t,'mac-input') || hasClass(t,'tonage-input') || hasClass(t,'mat-code-input') || t.id==='qSearch' || t.id==='customerFilter')) return;
    var p=t;
    while(p){if(p.id==='suggestBox')return;p=p.parentNode;}
    hideSuggest();
});

document.addEventListener('DOMContentLoaded',function(){bindAllRows();});
</script>
</body>
</html>