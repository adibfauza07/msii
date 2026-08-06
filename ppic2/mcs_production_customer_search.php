<?php
/**
 * MCS Production - List Production Standard + BOM + Harga Material IDR
 * Group by Customer
 * PHP 5.4 + SQL Server 2008
 */

@ini_set('max_execution_time', '180');
@ini_set('memory_limit', '512M');
if (session_id() === '') session_start();

// Hindari browser/proxy menampilkan versi lama setelah file diganti.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// ============================================
// PILIH PLANT
// ============================================
if (isset($_GET['plant'])) {
    $plantGet = strtolower(trim((string)$_GET['plant']));
    if ($plantGet === 'p1' || $plantGet === 'p2') {
        $_SESSION['active_plant'] = $plantGet;
    }
}

// ============================================
// LOAD CONFIG DATABASE
// ============================================
$config1 = __DIR__ . '/config/database_aging.php';
$config2 = __DIR__ . '/../config/database_aging.php';

if (file_exists($config1)) {
    require_once $config1;
} elseif (file_exists($config2)) {
    require_once $config2;
} else {
    die('File config database_aging.php tidak ditemukan.');
}

// ============================================
// HELPER
// ============================================
function h($value) {
    if ($value instanceof DateTime) {
        return htmlspecialchars($value->format('Y-m-d'), ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getv($key, $default) {
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

function run_query($sql, $params) {
    global $conn;

    if (function_exists('q')) {
        return q($sql, $params);
    }

    if (isset($conn) && $conn) {
        return sqlsrv_query($conn, $sql, $params);
    }

    return false;
}

function sql_error_text() {
    $errors = sqlsrv_errors();
    if (!is_array($errors)) return 'SQL error tidak diketahui.';

    $messages = array();
    foreach ($errors as $error) {
        if (isset($error['message'])) $messages[] = $error['message'];
    }
    return implode(' | ', $messages);
}

function fmt_date($value) {
    if ($value instanceof DateTime) return $value->format('Y-m-d');
    if ($value === null || $value === '') return '-';
    return (string)$value;
}

function fmt_num($value, $decimal) {
    if ($value === null || $value === '') return '-';
    return number_format((float)$value, $decimal, ',', '.');
}

/**
 * Format tonase tanpa angka desimal dan tanpa pemisah ribuan.
 * Contoh: 20080.00 menjadi 20080T.
 */
function fmt_tonase($value) {
    if ($value === null || $value === '') return '-';
    return number_format((float)$value, 0, '', '') . 'T';
}

/**
 * Normalisasi input filter tonase agar nilai seperti 20.080T tetap
 * dapat dicocokkan dengan MAG.MAG_STATION = 20080.00.
 */
function normalize_tonase_filter($value) {
    $value = strtoupper(trim((string)$value));
    if ($value === '') return '';
    return preg_replace('/[^0-9\-]/', '', $value);
}

function fmt_idr($value) {
    if ($value === null || $value === '') return 'Rp 0,00';
    return 'Rp ' . number_format((float)$value, 2, ',', '.');
}

function clean_limit($value) {
    $limit = (int)$value;
    if ($limit <= 0) $limit = 1000;
    if ($limit > 5000) $limit = 5000;
    return $limit;
}

function customer_key($row) {
    $code = isset($row['CUST_CODE']) ? trim((string)$row['CUST_CODE']) : '';
    $abbr = isset($row['CUST_ABBR']) ? trim((string)$row['CUST_ABBR']) : '';
    $comp = isset($row['CUST_COMP']) ? trim((string)$row['CUST_COMP']) : '';
    return strtoupper($code . '|' . $abbr . '|' . $comp);
}

function customer_label($row) {
    $code = isset($row['CUST_CODE']) ? trim((string)$row['CUST_CODE']) : '';
    $abbr = isset($row['CUST_ABBR']) ? trim((string)$row['CUST_ABBR']) : '';
    $comp = isset($row['CUST_COMP']) ? trim((string)$row['CUST_COMP']) : '';

    if ($code === '' && $abbr === '' && $comp === '') return 'TIDAK ADA CUSTOMER';

    $left = $code !== '' ? $code : '-';
    if ($abbr !== '') $left .= ' / ' . $abbr;
    return $left . ' - ' . ($comp !== '' ? $comp : '-');
}

// ============================================
// FILTER
// ============================================
$filterCustomerText = getv('customer', '');
$filterCustomerKey  = getv('customer_key', '');
$filterCustomer     = ($filterCustomerKey !== '') ? $filterCustomerKey : $filterCustomerText;
$filterPart     = getv('part', '');
$filterMaterial = getv('material', '');
$filterTonase   = getv('tonase', '');
$filterStatus   = getv('status', 'active');
$limit          = clean_limit(getv('limit', '1000'));
$action         = getv('action', '');

$currentPlant = isset($_SESSION['active_plant']) ? strtolower(trim((string)$_SESSION['active_plant'])) : 'p1';
if ($currentPlant !== 'p1' && $currentPlant !== 'p2') $currentPlant = 'p1';

$plantDisplay = ($currentPlant === 'p1') ? 'Plant 1' : 'Plant 2';
$serverIpDisplay = isset($serverName) ? $serverName : (($currentPlant === 'p1') ? '192.168.0.4' : '192.168.0.9');

// ============================================
// DATA CUSTOMER UNTUK FILTER
// ============================================
function get_customer_options() {
    $sql = "
        SELECT DISTINCT
            ci.CUST_CODE,
            ci.CUST_ABBR,
            ci.CUST_COMP
        FROM ITEM_PROD ip
        INNER JOIN ITEMS part
            ON ip.ITEM_ID = part.ITEM_ID
        LEFT JOIN ITEM_CUSTINFO_VIEW ci
            ON part.ITEM_ID = ci.ITEM_ID
        WHERE part.ITEM_INACTIVE = 0
        ORDER BY ci.CUST_CODE, ci.CUST_COMP
    ";

    $stmt = run_query($sql, array());
    $data = array();
    if (!$stmt) return $data;

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = $row;
    }
    return $data;
}

// ============================================
// DATA MCS PRODUCTION + HARGA MATERIAL IDR
// ============================================
function get_mcs_production($filterCustomer, $filterPart, $filterMaterial, $filterTonase, $filterStatus, $limit) {
    $sql = "
        ;WITH LatestPO AS
        (
            SELECT
                pd.ITEM_ID AS MAT_ID,
                pd.POD_PRICE,
                pd.POD_UNIT,
                po.PO_CUR,
                po.PO_DATE AS PRICE_DATE_RAW,
                po.PO_ID,
                ROW_NUMBER() OVER
                (
                    PARTITION BY pd.ITEM_ID
                    ORDER BY po.PO_DATE DESC, po.PO_ID DESC
                ) AS rn
            FROM PO_DETAIL pd
            INNER JOIN PO po
                ON pd.PO_ID = po.PO_ID
        ),
        PriceData AS
        (
            SELECT
                p.MAT_ID,
                p.POD_PRICE,
                p.POD_UNIT,
                p.PO_CUR,
                p.PRICE_DATE_RAW,
                c.CURR_VRATE,
                CAST
                (
                    CASE
                        WHEN p.POD_PRICE IS NULL THEN NULL
                        WHEN ISNULL(p.PO_CUR, 'IDR') = 'IDR' THEN p.POD_PRICE
                        WHEN c.CURR_VRATE IS NULL THEN NULL
                        ELSE p.POD_PRICE * c.CURR_VRATE
                    END
                    AS DECIMAL(38, 8)
                ) AS PRICE_IDR,
                CASE
                    WHEN p.POD_PRICE IS NULL THEN 'TANPA_HARGA'
                    WHEN ISNULL(p.PO_CUR, 'IDR') <> 'IDR' AND c.CURR_VRATE IS NULL THEN 'TANPA_KURS'
                    ELSE 'OK'
                END AS PRICE_STATUS
            FROM LatestPO p
            LEFT JOIN CURR_RAT c
                ON p.PO_CUR = c.CURR_CODE
               AND p.PRICE_DATE_RAW BETWEEN c.CURR_SDATE AND c.CURR_EDATE
            WHERE p.rn = 1
        )
        SELECT TOP " . (int)$limit . "
            ip.ITEM_CYTM,
            ip.ITEM_CAVT,
            ip.ITEM_WEIGHT,
            ip.ITEM_RWEIGHT,
            ip.ITEM_RCLY,
            ip.ITEM_UNIT AS PROD_UNIT,
            ip.MAG_ID,
            ip.INACTIVE,
            ip.TRIAL_DATE,
            ip.TRIAL_REM,

            bd.ITEM_ID AS MAT_ID,
            CAST(bd.QTY AS DECIMAL(38, 8)) AS BOM_QTY,
            bd.UNIT AS BOM_UNIT,

            mat.ITEM_CODE AS MAT_CODE,
            mat.ITEM_NAME AS MAT_NAME,
            mat.ITTY_CODE AS MAT_ITTY_CODE,

            mag.MAG_STATION AS TONASE,

            bd.PART_ID,
            part.ITEM_ID AS PART_ITEM_ID,
            part.ITEM_CODE AS PART_CODE,
            part.ITEM_NAME AS PART_NAME,

            ci.CUST_CODE,
            ci.CUST_ABBR,
            ci.CUST_COMP,

            pd.POD_PRICE AS HARGA_ASLI,
            pd.POD_UNIT AS HARGA_UNIT,
            ISNULL(pd.PO_CUR, 'IDR') AS MATA_UANG,
            pd.CURR_VRATE AS KURS_VRATE,
            pd.PRICE_IDR AS HARGA_IDR,
            CONVERT(VARCHAR(10), pd.PRICE_DATE_RAW, 23) AS TGL_HARGA,
            ISNULL(pd.PRICE_STATUS, 'TANPA_HARGA') AS STATUS_HARGA,

            CAST
            (
                CASE
                    WHEN pd.PRICE_IDR IS NULL THEN 0
                    WHEN mat.ITTY_CODE = '02'
                        THEN ROUND((bd.QTY * pd.PRICE_IDR) / 1000.0, 2)
                    ELSE ROUND(bd.QTY * pd.PRICE_IDR, 2)
                END
                AS DECIMAL(38, 2)
            ) AS TOTAL_MATERIAL_IDR

        FROM ITEM_PROD ip
        INNER JOIN BOM_DEFAULT bd
            ON ip.ITEM_ID = bd.PART_ID
        INNER JOIN ITEMS mat
            ON bd.ITEM_ID = mat.ITEM_ID
        INNER JOIN MAG mag
            ON ip.MAG_ID = mag.MAG_ID
        INNER JOIN ITEMS part
            ON ip.ITEM_ID = part.ITEM_ID
        LEFT JOIN ITEM_CUSTINFO_VIEW ci
            ON part.ITEM_ID = ci.ITEM_ID
        LEFT JOIN PriceData pd
            ON bd.ITEM_ID = pd.MAT_ID

        WHERE part.ITEM_INACTIVE = 0
          AND mat.ITEM_INACTIVE = 0
    ";

    $params = array();

    if ($filterStatus === 'active') {
        $sql .= " AND ISNULL(ip.INACTIVE, 0) = 0 ";
    } elseif ($filterStatus === 'inactive') {
        $sql .= " AND ISNULL(ip.INACTIVE, 0) <> 0 ";
    }

    if ($filterCustomer !== '') {
        $likeCustomer = '%' . $filterCustomer . '%';
        $sql .= " AND (ci.CUST_CODE LIKE ? OR ci.CUST_ABBR LIKE ? OR ci.CUST_COMP LIKE ?) ";
        $params[] = $likeCustomer;
        $params[] = $likeCustomer;
        $params[] = $likeCustomer;
    }

    if ($filterPart !== '') {
        $like = '%' . $filterPart . '%';
        $sql .= " AND (part.ITEM_CODE LIKE ? OR part.ITEM_NAME LIKE ?) ";
        $params[] = $like;
        $params[] = $like;
    }

    if ($filterMaterial !== '') {
        $like = '%' . $filterMaterial . '%';
        $sql .= " AND (mat.ITEM_CODE LIKE ? OR mat.ITEM_NAME LIKE ?) ";
        $params[] = $like;
        $params[] = $like;
    }

    $tonaseSearch = normalize_tonase_filter($filterTonase);
    if ($tonaseSearch !== '') {
        $like = '%' . $tonaseSearch . '%';
        $sql .= " AND CONVERT(VARCHAR(50), mag.MAG_STATION) LIKE ? ";
        $params[] = $like;
    }

    $sql .= "
        ORDER BY
            CASE WHEN ci.CUST_CODE IS NULL OR LTRIM(RTRIM(ci.CUST_CODE)) = '' THEN 1 ELSE 0 END,
            ci.CUST_CODE,
            ci.CUST_COMP,
            part.ITEM_CODE,
            mat.ITEM_CODE
    ";

    $stmt = run_query($sql, $params);
    $data = array();

    if (!$stmt) return array('data' => $data, 'error' => sql_error_text());

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = $row;
    }

    return array('data' => $data, 'error' => '');
}

$customerOptions = get_customer_options();

// Data untuk autocomplete customer dan label yang tampil setelah filter dipilih.
$customerAutocomplete = array();
$customerInputText = ($filterCustomerText !== '') ? $filterCustomerText : $filterCustomer;
foreach ($customerOptions as $customer) {
    $customerValue = isset($customer['CUST_CODE']) ? trim((string)$customer['CUST_CODE']) : '';
    if ($customerValue === '' && isset($customer['CUST_ABBR'])) $customerValue = trim((string)$customer['CUST_ABBR']);
    if ($customerValue === '' && isset($customer['CUST_COMP'])) $customerValue = trim((string)$customer['CUST_COMP']);
    if ($customerValue === '') continue;

    $label = customer_label($customer);
    $customerAutocomplete[] = array(
        'value' => $customerValue,
        'label' => $label,
        'code'  => isset($customer['CUST_CODE']) ? (string)$customer['CUST_CODE'] : '',
        'abbr'  => isset($customer['CUST_ABBR']) ? (string)$customer['CUST_ABBR'] : '',
        'comp'  => isset($customer['CUST_COMP']) ? (string)$customer['CUST_COMP'] : ''
    );

    if ($filterCustomer !== '' && (
        strcasecmp($filterCustomer, $customerValue) === 0 ||
        (isset($customer['CUST_CODE']) && strcasecmp($filterCustomer, trim((string)$customer['CUST_CODE'])) === 0) ||
        (isset($customer['CUST_ABBR']) && strcasecmp($filterCustomer, trim((string)$customer['CUST_ABBR'])) === 0) ||
        (isset($customer['CUST_COMP']) && strcasecmp($filterCustomer, trim((string)$customer['CUST_COMP'])) === 0)
    )) {
        $customerInputText = $label;
    }
}

$result = get_mcs_production(
    $filterCustomer,
    $filterPart,
    $filterMaterial,
    $filterTonase,
    $filterStatus,
    $limit
);
$dataRows = $result['data'];
$sqlError = $result['error'];

// ============================================
// SUMMARY
// ============================================
$totalHarga = 0;
$totalHargaValid = 0;
$missingHarga = 0;
$missingKurs = 0;
$activeCount = 0;
$inactiveCount = 0;
$customerSet = array();
$partSet = array();
$materialSet = array();

foreach ($dataRows as $row) {
    $nilai = isset($row['TOTAL_MATERIAL_IDR']) ? (float)$row['TOTAL_MATERIAL_IDR'] : 0;
    $totalHarga += $nilai;

    if (isset($row['STATUS_HARGA']) && $row['STATUS_HARGA'] === 'OK') {
        $totalHargaValid += $nilai;
    }
    if (isset($row['STATUS_HARGA']) && $row['STATUS_HARGA'] === 'TANPA_HARGA') $missingHarga++;
    if (isset($row['STATUS_HARGA']) && $row['STATUS_HARGA'] === 'TANPA_KURS') $missingKurs++;

    if (isset($row['INACTIVE']) && (int)$row['INACTIVE'] !== 0) $inactiveCount++;
    else $activeCount++;

    $customerSet[customer_key($row)] = true;
    if (isset($row['PART_ID'])) $partSet[(string)$row['PART_ID']] = true;
    if (isset($row['MAT_ID'])) $materialSet[(string)$row['MAT_ID']] = true;
}

// ============================================
// EXPORT EXCEL
// ============================================
if ($action === 'excel') {
    $filename = 'MCS_Production_' . $currentPlant . '_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'table{border-collapse:collapse;font-family:Arial;font-size:10pt}td,th{border:1px solid #999;padding:4px;mso-number-format:"\\@"}.title{font-size:16pt;font-weight:bold;text-align:center}.customer{background:#dbeafe;color:#1e3a8a;font-weight:bold}.part{background:#e8eaf6;color:#1a237e;font-weight:bold}.head{background:#37474f;color:#fff;font-weight:bold}.subtotal{background:#f3f4f6;font-weight:bold}.money{mso-number-format:"#,##0.00"}</style></head><body>';
    echo '<table>';
    echo '<tr><td colspan="25" class="title">MCS PRODUCTION - ' . h($plantDisplay) . '</td></tr>';
    echo '<tr><td colspan="25">Harga material dikonversi ke IDR berdasarkan PO terakhir dan kurs pada tanggal PO.</td></tr>';
    echo '<tr class="head">';

    $headers = array(
        'No', 'Customer Code', 'Customer Abbr', 'Customer Company', 'Part Code', 'Part Name',
        'Tonase', 'Cycle Time', 'Cavity', 'Part Weight', 'Runner Weight', 'RCLY', 'Prod Unit',
        'Material Code', 'Material Name', 'ITTY', 'BOM Qty', 'BOM Unit', 'Harga Asli', 'Curr',
        'Kurs', 'Harga IDR', 'Total Material IDR', 'Trial Date', 'Trial Remark'
    );
    foreach ($headers as $head) echo '<th>' . h($head) . '</th>';
    echo '</tr>';

    $currentCustomer = null;
    $currentPart = null;
    $no = 1;
    $subtotalPart = 0;

    foreach ($dataRows as $row) {
        $ckey = customer_key($row);
        $pkey = isset($row['PART_ID']) ? (string)$row['PART_ID'] : '';

        if ($ckey !== $currentCustomer) {
            if ($currentPart !== null) {
                echo '<tr class="subtotal"><td colspan="22">Subtotal Part</td><td class="money">' . number_format($subtotalPart, 2, '.', '') . '</td><td colspan="2"></td></tr>';
            }
            $currentCustomer = $ckey;
            $currentPart = null;
            $subtotalPart = 0;
            echo '<tr class="customer"><td colspan="25">CUSTOMER: ' . h(customer_label($row)) . '</td></tr>';
        }

        if ($pkey !== $currentPart) {
            if ($currentPart !== null) {
                echo '<tr class="subtotal"><td colspan="22">Subtotal Part</td><td class="money">' . number_format($subtotalPart, 2, '.', '') . '</td><td colspan="2"></td></tr>';
            }
            $currentPart = $pkey;
            $subtotalPart = 0;
            $no = 1;
            echo '<tr class="part"><td colspan="25">PART: ' . h($row['PART_CODE']) . ' - ' . h($row['PART_NAME']) . '</td></tr>';
        }

        $subtotalPart += (float)$row['TOTAL_MATERIAL_IDR'];

        echo '<tr>';
        $values = array(
            $no++, $row['CUST_CODE'], $row['CUST_ABBR'], $row['CUST_COMP'], $row['PART_CODE'], $row['PART_NAME'],
            fmt_tonase($row['TONASE']), $row['ITEM_CYTM'], $row['ITEM_CAVT'], $row['ITEM_WEIGHT'], $row['ITEM_RWEIGHT'], $row['ITEM_RCLY'], $row['PROD_UNIT'],
            $row['MAT_CODE'], $row['MAT_NAME'], $row['MAT_ITTY_CODE'], $row['BOM_QTY'], $row['BOM_UNIT'], $row['HARGA_ASLI'], $row['MATA_UANG'],
            $row['KURS_VRATE'], $row['HARGA_IDR'], $row['TOTAL_MATERIAL_IDR'], fmt_date($row['TRIAL_DATE']), $row['TRIAL_REM']
        );
        foreach ($values as $index => $value) {
            $class = in_array($index, array(18, 20, 21, 22), true) ? ' class="money"' : '';
            echo '<td' . $class . '>' . h($value) . '</td>';
        }
        echo '</tr>';
    }

    if ($currentPart !== null) {
        echo '<tr class="subtotal"><td colspan="22">Subtotal Part</td><td class="money">' . number_format($subtotalPart, 2, '.', '') . '</td><td colspan="2"></td></tr>';
    }

    echo '<tr class="subtotal"><td colspan="22">GRAND TOTAL IDR</td><td class="money">' . number_format($totalHarga, 2, '.', '') . '</td><td colspan="2"></td></tr>';
    echo '</table></body></html>';
    exit;
}

function build_query_string($overrides) {
    $data = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null) unset($data[$key]);
        else $data[$key] = $value;
    }
    return http_build_query($data);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MCS Production - Harga Material IDR (Customer Search)</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:#eef2f7;color:#1f2937;padding:16px;font-size:12px}
.container{max-width:1900px;margin:0 auto}
.header{background:linear-gradient(135deg,#0f172a,#1e3a8a);color:#fff;padding:18px 22px;border-radius:10px 10px 0 0;display:flex;justify-content:space-between;gap:16px;align-items:center}
.header h1{font-size:22px;margin-bottom:4px}.header p{font-size:12px;opacity:.82}.header-right{display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
.badge-header{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:18px;padding:7px 11px;font-weight:700;white-space:nowrap}
.card{background:#fff;border:1px solid #d7e0ea;box-shadow:0 3px 12px rgba(15,23,42,.06);margin-bottom:12px}.filter-card{border-top:0}.card-title{font-size:13px;font-weight:800;padding:10px 14px;border-bottom:1px solid #e5e7eb;background:#f8fafc}.card-body{padding:13px 14px}
.notice{padding:10px 12px;border-radius:7px;margin:10px 0}.notice.error{background:#fff1f2;color:#991b1b;border:1px solid #fecaca}
.filter-row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}.field{display:flex;flex-direction:column;gap:4px;position:relative}.field label{font-size:10px;font-weight:800;text-transform:uppercase;color:#64748b}.field input,.field select{height:34px;border:1px solid #cbd5e1;border-radius:6px;padding:6px 9px;font-size:12px;background:#fff;min-width:165px}.field input.wide{min-width:240px}.field input.customer-search{min-width:365px;padding-right:30px}.customer-hint{font-size:9px;color:#64748b;margin-top:1px}.field select.server{background:#fff7ed;border-color:#fb923c;color:#9a3412;font-weight:800}
.customer-suggest{display:none;position:absolute;left:0;top:55px;z-index:9999;width:100%;min-width:430px;max-height:300px;overflow-y:auto;background:#fff;border:1px solid #94a3b8;border-radius:7px;box-shadow:0 12px 28px rgba(15,23,42,.22)}.customer-suggest.show{display:block}.customer-option{padding:8px 10px;border-bottom:1px solid #e5e7eb;cursor:pointer;line-height:1.35}.customer-option:last-child{border-bottom:0}.customer-option:hover,.customer-option.active{background:#eff6ff}.customer-option-code{font-weight:900;color:#1e3a8a}.customer-option-company{color:#475569}.customer-empty{padding:10px;color:#64748b}.customer-clear{position:absolute;right:8px;bottom:8px;border:0;background:transparent;color:#64748b;font-size:15px;cursor:pointer;line-height:1}
.btn{height:34px;border:0;border-radius:6px;padding:8px 13px;font-size:11px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap}.btn-primary{background:#1d4ed8;color:#fff}.btn-warning{background:#f59e0b;color:#fff}.btn-success{background:#15803d;color:#fff}.btn-light{background:#e2e8f0;color:#1f2937}
.guide{background:#fffde7;border:1px solid #fde68a;color:#713f12;padding:12px 14px;line-height:1.55}.guide h3{font-size:13px;margin-bottom:6px}.guide-grid{display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:8px}.guide-item{background:rgba(255,255,255,.55);border-radius:6px;padding:8px}.guide-item b{color:#92400e}
.stats{display:grid;grid-template-columns:repeat(6,minmax(140px,1fr));gap:10px;margin:12px 0}.stat{background:#fff;border-radius:8px;padding:12px;border-left:4px solid #1d4ed8;box-shadow:0 2px 8px rgba(15,23,42,.05)}.stat:nth-child(2){border-left-color:#7c3aed}.stat:nth-child(3){border-left-color:#0f766e}.stat:nth-child(4){border-left-color:#15803d}.stat:nth-child(5){border-left-color:#f59e0b}.stat:nth-child(6){border-left-color:#dc2626}.stat-label{font-size:10px;color:#64748b;text-transform:uppercase;font-weight:800}.stat-value{font-size:17px;font-weight:900;margin-top:3px;color:#0f172a}
.toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px}.table-wrap{overflow:auto;max-height:700px;border:1px solid #cbd5e1;border-radius:8px}
table{width:100%;border-collapse:collapse;min-width:2500px}thead th{position:sticky;top:0;z-index:4;background:#374151;color:#fff;padding:8px 7px;font-size:10px;text-transform:uppercase;letter-spacing:.25px;text-align:left;white-space:nowrap;border-right:1px solid rgba(255,255,255,.13)}tbody td{padding:7px 8px;border-right:1px solid #edf2f7;border-bottom:1px solid #e5e7eb;white-space:nowrap;vertical-align:middle}tbody tr.detail:hover td{background:#f8fbff}.text-right{text-align:right}.text-center{text-align:center}.bold{font-weight:800}.money{color:#1e3a8a;font-weight:900}
.customer-row td{background:#dbeafe!important;color:#1e3a8a;font-weight:900;font-size:13px;padding:10px!important;border-top:2px solid #93c5fd}.customer-meta{float:right;font-size:10px;color:#475569}.part-row td{background:#e8eaf6!important;color:#312e81;font-weight:800;padding:8px 10px!important}.subtotal-row td{background:#f3f4f6!important;font-weight:800}.grand-total td{background:#1e3a8a!important;color:#fff;font-weight:900;font-size:13px;padding:11px 8px!important}
.tag{display:inline-block;border-radius:12px;padding:2px 7px;font-size:10px;font-weight:800}.tag-ok{background:#dcfce7;color:#166534}.tag-error{background:#fee2e2;color:#991b1b}.tag-warn{background:#ffedd5;color:#9a3412}.tag-idr{background:#f3e8ff;color:#6b21a8}.tag-usd{background:#e0e7ff;color:#3730a3}.tag-active{background:#dcfce7;color:#166534}.tag-inactive{background:#fee2e2;color:#991b1b}.no-data{text-align:center;padding:38px!important;color:#64748b}
.footer{text-align:center;color:#64748b;font-size:11px;padding:12px}.muted{color:#64748b}.nowrap{white-space:nowrap}
@media(max-width:1000px){body{padding:8px}.header{flex-direction:column;align-items:flex-start}.header-right{justify-content:flex-start}.guide-grid{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,1fr)}.filter-row{flex-direction:column;align-items:stretch}.field input,.field select,.field input.wide{width:100%;min-width:0}.btn{width:100%}}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>🏭 MCS PRODUCTION + HARGA MATERIAL IDR</h1>
            <p>ITEM_PROD + BOM_DEFAULT + ITEMS + MAG + CUSTOMER + harga PO terakhir</p>
        </div>
        <div class="header-right">
            <div class="badge-header">Plant: <?php echo h($plantDisplay); ?></div>
            <div class="badge-header"><?php echo h($serverIpDisplay); ?></div>
            <div class="badge-header">Group by Customer</div>
        </div>
    </div>

    <?php if ($sqlError !== ''): ?>
        <div class="notice error"><b>Query gagal:</b> <?php echo h($sqlError); ?></div>
    <?php endif; ?>

    <div class="card filter-card">
        <div class="card-title">Filter Data Production</div>
        <div class="card-body">
            <form method="get" action="mcs_production.php" class="filter-row">
                <div class="field">
                    <label>Plant</label>
                    <select name="plant" class="server" onchange="this.form.submit()">
                        <option value="p1" <?php echo $currentPlant === 'p1' ? 'selected' : ''; ?>>Plant 1 (192.168.0.4)</option>
                        <option value="p2" <?php echo $currentPlant === 'p2' ? 'selected' : ''; ?>>Plant 2 (192.168.0.9)</option>
                    </select>
                </div>

                <div class="field customer-field">
                    <label>Customer</label>
                    <input
                        class="customer-search"
                        type="search"
                        name="customer"
                        id="customerSearch"
                        value="<?php echo h($customerInputText); ?>"
                        placeholder="Ketik kode / singkatan / nama customer"
                        autocomplete="off"
                        spellcheck="false"
                    >
                    <input type="hidden" name="customer_key" id="customerValue" value="<?php echo h($filterCustomerKey); ?>">
                    <button type="button" class="customer-clear" id="customerClear" title="Hapus filter customer">×</button>
                    <div id="customerSuggest" class="customer-suggest"></div>
                    <div class="customer-hint">Klik lalu ketik untuk mencari customer.</div>
                </div>

                <div class="field">
                    <label>Part Code / Name</label>
                    <input class="wide" type="text" name="part" value="<?php echo h($filterPart); ?>" placeholder="Cari part">
                </div>

                <div class="field">
                    <label>Material Code / Name</label>
                    <input class="wide" type="text" name="material" value="<?php echo h($filterMaterial); ?>" placeholder="Cari material">
                </div>

                <div class="field">
                    <label>Tonase</label>
                    <input type="text" name="tonase" value="<?php echo h($filterTonase); ?>" placeholder="Contoh: 20080T">
                </div>

                <div class="field">
                    <label>Status Production</label>
                    <select name="status">
                        <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>Semua</option>
                    </select>
                </div>

                <div class="field">
                    <label>Limit</label>
                    <select name="limit">
                        <?php foreach (array(300, 500, 1000, 2000, 5000) as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php echo $limit === $opt ? 'selected' : ''; ?>><?php echo $opt; ?> row</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">🔍 Cari</button>
                <a href="mcs_production.php?plant=<?php echo h($currentPlant); ?>" class="btn btn-light">Reset</a>
                <a href="mcs_production.php?<?php echo h(build_query_string(array('action' => 'excel'))); ?>" class="btn btn-success">⬇ Excel</a>
            </form>
        </div>
    </div>

    <div class="guide">
        <h3>📘 Panduan Harga dan Perhitungan</h3>
        <div class="guide-grid">
            <div class="guide-item"><b>Harga dasar:</b> mengambil harga PO terakhir untuk setiap material dari `PO_DETAIL` dan `PO`.</div>
            <div class="guide-item"><b>Konversi IDR:</b> harga non-IDR dikalikan `CURR_RAT.CURR_VRATE` yang berlaku pada tanggal PO.</div>
            <div class="guide-item"><b>Material ITTY 02:</b> Total IDR = BOM QTY × Harga IDR ÷ 1.000 karena harga digunakan sebagai basis kilogram.</div>
            <div class="guide-item"><b>Material lainnya:</b> Total IDR = BOM QTY × Harga IDR. Status merah menunjukkan harga atau kurs belum tersedia.</div>
        </div>
    </div>

    <div class="stats">
        <div class="stat"><div class="stat-label">Customer</div><div class="stat-value"><?php echo count($customerSet); ?></div></div>
        <div class="stat"><div class="stat-label">Part</div><div class="stat-value"><?php echo count($partSet); ?></div></div>
        <div class="stat"><div class="stat-label">Material</div><div class="stat-value"><?php echo count($materialSet); ?></div></div>
        <div class="stat"><div class="stat-label">Grand Total IDR</div><div class="stat-value" style="font-size:14px"><?php echo fmt_idr($totalHarga); ?></div></div>
        <div class="stat"><div class="stat-label">Tanpa Harga</div><div class="stat-value"><?php echo $missingHarga; ?></div></div>
        <div class="stat"><div class="stat-label">Tanpa Kurs</div><div class="stat-value"><?php echo $missingKurs; ?></div></div>
    </div>

    <div class="card">
        <div class="card-title">Daftar MCS Production — <?php echo count($dataRows); ?> detail BOM</div>
        <div class="card-body">
            <div class="toolbar">
                <div class="muted">Urutan: Customer → Part → Material</div>
                <div><b>Total harga valid:</b> <?php echo fmt_idr($totalHargaValid); ?></div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th class="text-center">No</th>
                        <th>Part Code</th>
                        <th>Part Name</th>
                        <th class="text-right">Tonase</th>
                        <th class="text-right">Cycle</th>
                        <th class="text-right">Cavity</th>
                        <th class="text-right">Part Weight</th>
                        <th class="text-right">Runner Weight</th>
                        <th class="text-right">RCLY</th>
                        <th>Prod Unit</th>
                        <th>Material Code</th>
                        <th>Material Name</th>
                        <th class="text-center">ITTY</th>
                        <th class="text-right">BOM Qty</th>
                        <th>BOM Unit</th>
                        <th class="text-right">Harga Asli</th>
                        <th class="text-center">Curr</th>
                        <th class="text-right">Kurs</th>
                        <th class="text-right">Harga IDR</th>
                        <th class="text-right">Total Material IDR</th>
                        <th>Tgl Harga</th>
                        <th>Trial Date</th>
                        <th>Trial Remark</th>
                        <th class="text-center">Prod Status</th>
                        <th class="text-center">Harga Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($dataRows) > 0): ?>
                        <?php
                        $currentCustomer = null;
                        $currentPart = null;
                        $subtotalPart = 0;
                        $subtotalCustomer = 0;
                        $no = 1;
                        ?>

                        <?php foreach ($dataRows as $row): ?>
                            <?php
                            $ckey = customer_key($row);
                            $pkey = isset($row['PART_ID']) ? (string)$row['PART_ID'] : '';
                            ?>

                            <?php if ($ckey !== $currentCustomer): ?>
                                <?php if ($currentPart !== null): ?>
                                    <tr class="subtotal-row">
                                        <td colspan="19" class="text-right">Subtotal Part</td>
                                        <td class="text-right money"><?php echo fmt_idr($subtotalPart); ?></td>
                                        <td colspan="5"></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($currentCustomer !== null): ?>
                                    <tr class="subtotal-row">
                                        <td colspan="19" class="text-right">Subtotal Customer</td>
                                        <td class="text-right money"><?php echo fmt_idr($subtotalCustomer); ?></td>
                                        <td colspan="5"></td>
                                    </tr>
                                <?php endif; ?>

                                <?php
                                $currentCustomer = $ckey;
                                $currentPart = null;
                                $subtotalPart = 0;
                                $subtotalCustomer = 0;
                                ?>
                                <tr class="customer-row">
                                    <td colspan="25">
                                        CUSTOMER: <?php echo h(customer_label($row)); ?>
                                        <span class="customer-meta">MCS Production | <?php echo h($plantDisplay); ?></span>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php if ($pkey !== $currentPart): ?>
                                <?php if ($currentPart !== null): ?>
                                    <tr class="subtotal-row">
                                        <td colspan="19" class="text-right">Subtotal Part</td>
                                        <td class="text-right money"><?php echo fmt_idr($subtotalPart); ?></td>
                                        <td colspan="5"></td>
                                    </tr>
                                <?php endif; ?>

                                <?php
                                $currentPart = $pkey;
                                $subtotalPart = 0;
                                $no = 1;
                                ?>
                                <tr class="part-row">
                                    <td colspan="25">📦 PART: <?php echo h($row['PART_CODE']); ?> - <?php echo h($row['PART_NAME']); ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php
                            $lineTotal = isset($row['TOTAL_MATERIAL_IDR']) ? (float)$row['TOTAL_MATERIAL_IDR'] : 0;
                            $subtotalPart += $lineTotal;
                            $subtotalCustomer += $lineTotal;
                            ?>

                            <tr class="detail">
                                <td class="text-center"><?php echo $no++; ?></td>
                                <td class="bold"><?php echo h($row['PART_CODE']); ?></td>
                                <td><?php echo h($row['PART_NAME']); ?></td>
                                <td class="text-right bold"><?php echo fmt_tonase($row['TONASE']); ?></td>
                                <td class="text-right"><?php echo fmt_num($row['ITEM_CYTM'], 3); ?></td>
                                <td class="text-right"><?php echo fmt_num($row['ITEM_CAVT'], 3); ?></td>
                                <td class="text-right"><?php echo fmt_num($row['ITEM_WEIGHT'], 4); ?></td>
                                <td class="text-right"><?php echo fmt_num($row['ITEM_RWEIGHT'], 4); ?></td>
                                <td class="text-right"><?php echo fmt_num($row['ITEM_RCLY'], 4); ?></td>
                                <td><?php echo h($row['PROD_UNIT']); ?></td>
                                <td class="bold"><?php echo h($row['MAT_CODE']); ?></td>
                                <td><?php echo h($row['MAT_NAME']); ?></td>
                                <td class="text-center"><span class="tag tag-idr"><?php echo h($row['MAT_ITTY_CODE']); ?></span></td>
                                <td class="text-right"><?php echo fmt_num($row['BOM_QTY'], 6); ?></td>
                                <td><?php echo h($row['BOM_UNIT']); ?></td>
                                <td class="text-right"><?php echo fmt_num($row['HARGA_ASLI'], 4); ?></td>
                                <td class="text-center">
                                    <?php if ($row['MATA_UANG'] === 'USD'): ?>
                                        <span class="tag tag-usd">USD</span>
                                    <?php else: ?>
                                        <span class="tag tag-idr"><?php echo h($row['MATA_UANG']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right"><?php echo ($row['MATA_UANG'] === 'IDR') ? '-' : fmt_num($row['KURS_VRATE'], 2); ?></td>
                                <td class="text-right"><?php echo fmt_idr($row['HARGA_IDR']); ?></td>
                                <td class="text-right money"><?php echo fmt_idr($row['TOTAL_MATERIAL_IDR']); ?></td>
                                <td><?php echo h($row['TGL_HARGA'] ? $row['TGL_HARGA'] : '-'); ?></td>
                                <td><?php echo h(fmt_date($row['TRIAL_DATE'])); ?></td>
                                <td><?php echo h($row['TRIAL_REM']); ?></td>
                                <td class="text-center">
                                    <?php if ((int)$row['INACTIVE'] === 0): ?>
                                        <span class="tag tag-active">Aktif</span>
                                    <?php else: ?>
                                        <span class="tag tag-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['STATUS_HARGA'] === 'OK'): ?>
                                        <span class="tag tag-ok">✓ OK</span>
                                    <?php elseif ($row['STATUS_HARGA'] === 'TANPA_KURS'): ?>
                                        <span class="tag tag-warn">⚠ Tanpa Kurs</span>
                                    <?php else: ?>
                                        <span class="tag tag-error">⚠ Tanpa Harga</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if ($currentPart !== null): ?>
                            <tr class="subtotal-row">
                                <td colspan="19" class="text-right">Subtotal Part</td>
                                <td class="text-right money"><?php echo fmt_idr($subtotalPart); ?></td>
                                <td colspan="5"></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($currentCustomer !== null): ?>
                            <tr class="subtotal-row">
                                <td colspan="19" class="text-right">Subtotal Customer</td>
                                <td class="text-right money"><?php echo fmt_idr($subtotalCustomer); ?></td>
                                <td colspan="5"></td>
                            </tr>
                        <?php endif; ?>

                        <tr class="grand-total">
                            <td colspan="19" class="text-right">GRAND TOTAL MATERIAL (IDR)</td>
                            <td class="text-right"><?php echo fmt_idr($totalHarga); ?></td>
                            <td colspan="5"></td>
                        </tr>
                    <?php else: ?>
                        <tr><td colspan="25" class="no-data">📭 Tidak ada data MCS Production sesuai filter.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="footer">
        © 2026 Di-trace System | Database: msData | <?php echo h($plantDisplay); ?> | Harga PO terakhir dikonversi ke IDR menggunakan VRATE.
    </div>
</div>

<script>
(function(){
    var options = <?php echo json_encode($customerAutocomplete); ?> || [];
    var input = document.getElementById('customerSearch');
    var hidden = document.getElementById('customerValue');
    var box = document.getElementById('customerSuggest');
    var clearBtn = document.getElementById('customerClear');
    var activeIndex = -1;
    var visibleOptions = [];

    if (!input || !hidden || !box) return;

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalize(value) {
        return String(value || '').toLowerCase().replace(/^\s+|\s+$/g, '');
    }

    function render(query) {
        var q = normalize(query);
        visibleOptions = [];

        for (var i = 0; i < options.length; i++) {
            var item = options[i];
            var haystack = normalize(
                (item.code || '') + ' ' +
                (item.abbr || '') + ' ' +
                (item.comp || '') + ' ' +
                (item.label || '')
            );
            if (q === '' || haystack.indexOf(q) !== -1) {
                visibleOptions.push(item);
                if (visibleOptions.length >= 50) break;
            }
        }

        activeIndex = -1;
        if (!visibleOptions.length) {
            box.innerHTML = '<div class="customer-empty">Customer tidak ditemukan.</div>';
            box.className = 'customer-suggest show';
            return;
        }

        var html = '';
        for (var j = 0; j < visibleOptions.length; j++) {
            var row = visibleOptions[j];
            var left = row.code || row.value || '-';
            if (row.abbr) left += ' / ' + row.abbr;
            html += '<div class="customer-option" data-index="' + j + '">' +
                '<span class="customer-option-code">' + escapeHtml(left) + '</span>' +
                '<span class="customer-option-company"> - ' + escapeHtml(row.comp || '-') + '</span>' +
                '</div>';
        }
        box.innerHTML = html;
        box.className = 'customer-suggest show';
    }

    function hideBox() {
        box.className = 'customer-suggest';
        activeIndex = -1;
    }

    function choose(index) {
        if (index < 0 || index >= visibleOptions.length) return;
        var item = visibleOptions[index];
        input.value = item.label || item.value || '';
        hidden.value = item.value || item.code || item.abbr || item.comp || '';
        hideBox();
        input.focus();
    }

    function highlight(index) {
        var nodes = box.getElementsByClassName('customer-option');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].className = 'customer-option' + (i === index ? ' active' : '');
        }
        if (nodes[index] && nodes[index].scrollIntoView) {
            nodes[index].scrollIntoView({block:'nearest'});
        }
    }

    input.onfocus = function(){ render(input.value); };
    input.oninput = function(){
        // Apabila user mengetik sendiri, gunakan teks tersebut sebagai pencarian LIKE.
        // Saat user mengetik bebas, kosongkan key pilihan agar server memakai teks input.
        hidden.value = '';
        render(input.value);
    };
    input.onkeydown = function(event){
        event = event || window.event;
        var key = event.keyCode || event.which;
        if (key === 40) {
            if (!visibleOptions.length) render(input.value);
            activeIndex = Math.min(activeIndex + 1, visibleOptions.length - 1);
            highlight(activeIndex);
            if (event.preventDefault) event.preventDefault();
        } else if (key === 38) {
            activeIndex = Math.max(activeIndex - 1, 0);
            highlight(activeIndex);
            if (event.preventDefault) event.preventDefault();
        } else if (key === 13 && activeIndex >= 0) {
            choose(activeIndex);
            if (event.preventDefault) event.preventDefault();
            return false;
        } else if (key === 13) {
            // Enter tanpa memilih suggestion tetap menjalankan pencarian teks bebas.
            hidden.value = '';
            hideBox();
        } else if (key === 27) {
            hideBox();
        }
    };

    box.onclick = function(event){
        event = event || window.event;
        var target = event.target || event.srcElement;
        while (target && target !== box && target.getAttribute('data-index') === null) {
            target = target.parentNode;
        }
        if (target && target !== box) choose(parseInt(target.getAttribute('data-index'), 10));
    };

    clearBtn.onclick = function(){
        input.value = '';
        hidden.value = '';
        hideBox();
        input.focus();
    };

    document.onclick = function(event){
        event = event || window.event;
        var target = event.target || event.srcElement;
        if (target !== input && target !== clearBtn && !box.contains(target)) hideBox();
    };
})();
</script>
</body>
</html>
<?php
if (isset($conn) && $conn) {
    sqlsrv_close($conn);
}
?>
