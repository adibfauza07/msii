<?php
/**
 * MCS Production - List Production Standard + BOM + Harga Material IDR
 * Group by Customer
 * PHP 5.4 + SQL Server 2008
 */

@ini_set('max_execution_time', '180');
@ini_set('memory_limit', '512M');
if (session_id() === '') session_start();

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
// HELPER RUN QUERY 
// ============================================
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

// ============================================
// HANDLER UPDATE QTY (AJAX)
// ============================================
if (isset($_POST['action']) && $_POST['action'] === 'update_qty') {
    header('Content-Type: application/json');
    $pass = isset($_POST['password']) ? trim((string)$_POST['password']) : '';
    
    if ($pass !== 'q9tj9') {
        echo json_encode(array('success' => false, 'message' => 'Password salah!'));
        exit;
    }
    
    $partId = isset($_POST['part_id']) ? trim($_POST['part_id']) : '';
    $matId = isset($_POST['mat_id']) ? trim($_POST['mat_id']) : '';
    $qty = isset($_POST['qty']) ? (float)$_POST['qty'] : 0;
    
    if ($partId === '' || $matId === '') {
        echo json_encode(array('success' => false, 'message' => 'Data part / material tidak lengkap!'));
        exit;
    }
    
    // Update ke tabel BOM_DEFAULT
    $updateSql = "UPDATE BOM_DEFAULT SET QTY = ? WHERE PART_ID = ? AND ITEM_ID = ?";
    $updateStmt = run_query($updateSql, array($qty, $partId, $matId));
    
    if ($updateStmt) {
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'message' => sql_error_text()));
    }
    exit;
}

// ============================================
// HANDLER UPDATE PART INFO (Tonase, Cycle, Weight, dll)
// ============================================
if (isset($_POST['action']) && $_POST['action'] === 'update_part_info') {
    header('Content-Type: application/json');
    $pass = isset($_POST['password']) ? trim((string)$_POST['password']) : '';
    
    if ($pass !== 'q9tj9') {
        echo json_encode(array('success' => false, 'message' => 'Password salah!'));
        exit;
    }
    
    $partId = isset($_POST['part_id']) ? trim($_POST['part_id']) : '';
    $field = isset($_POST['field']) ? trim($_POST['field']) : '';
    $value = isset($_POST['value']) ? trim($_POST['value']) : '';
    
    if ($partId === '' || $field === '') {
        echo json_encode(array('success' => false, 'message' => 'Data part tidak lengkap!'));
        exit;
    }
    
    $stmt = false;
    
    // Proses update berdasarkan field yang diubah
    if ($field === 'tonase') {
        // Nilai $value sekarang adalah MAG_ID yang dikirim dari Combo Box
        $sql = "UPDATE ITEM_PROD SET MAG_ID = ? WHERE ITEM_ID = ?";
        $stmt = run_query($sql, array($value, $partId));
    } elseif ($field === 'cycle') {
        $sql = "UPDATE ITEM_PROD SET ITEM_CYTM = ? WHERE ITEM_ID = ?";
        $stmt = run_query($sql, array((float)$value, $partId));
    } elseif ($field === 'cavity') {
        $sql = "UPDATE ITEM_PROD SET ITEM_CAVT = ? WHERE ITEM_ID = ?";
        $stmt = run_query($sql, array((float)$value, $partId));
    } elseif ($field === 'part_weight') {
        $sql = "UPDATE ITEM_PROD SET ITEM_WEIGHT = ? WHERE ITEM_ID = ?";
        $stmt = run_query($sql, array((float)$value, $partId));
    } elseif ($field === 'runner_weight') {
        $sql = "UPDATE ITEM_PROD SET ITEM_RWEIGHT = ? WHERE ITEM_ID = ?";
        $stmt = run_query($sql, array((float)$value, $partId));
    } elseif ($field === 'rcly') {
        $sql = "UPDATE ITEM_PROD SET ITEM_RCLY = ? WHERE ITEM_ID = ?";
        $stmt = run_query($sql, array((float)$value, $partId));
    } elseif ($field === 'prod_unit') {
        $sql = "UPDATE ITEM_PROD SET ITEM_UNIT = ? WHERE ITEM_ID = ?";
        $stmt = run_query($sql, array($value, $partId));
    } else {
        echo json_encode(array('success' => false, 'message' => 'Kolom tidak valid!'));
        exit;
    }
    
    if ($stmt) {
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'message' => sql_error_text()));
    }
    exit;
}

// ============================================
// HELPER FORMATTING
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

function fmt_date($value) {
    if ($value instanceof DateTime) return $value->format('Y-m-d');
    if ($value === null || $value === '') return '-';
    return (string)$value;
}

function fmt_num($value, $decimal) {
    if ($value === null || $value === '') return '-';
    return number_format((float)$value, $decimal, ',', '.');
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
// DATA MAG UNTUK COMBOBOX TONASE
// ============================================
function get_mag_options() {
    $sql = "SELECT MAG_ID, MAG_STATION FROM MAG ORDER BY MAG_STATION";
    $stmt = run_query($sql, array());
    $data = array();
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
    }
    return $data;
}
$magOptions = get_mag_options();

// ============================================
// FILTER
// ============================================
$filterCustomer = getv('customer', '');
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
        $sql .= " AND (ci.CUST_CODE = ? OR ci.CUST_ABBR = ? OR ci.CUST_COMP = ?) ";
        $params[] = $filterCustomer;
        $params[] = $filterCustomer;
        $params[] = $filterCustomer;
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

    if ($filterTonase !== '') {
        $like = '%' . $filterTonase . '%';
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
    // Garis Excel tipis standar (.5pt solid #000000)
    echo 'table{border-collapse:collapse;font-family:Arial;font-size:10pt}td,th{border:.5pt solid #000000;padding:4px;mso-number-format:"\\@"}.title{font-size:16pt;font-weight:bold;text-align:center}.customer{background:#dbeafe;color:#1e3a8a;font-weight:bold}.part{background:#e8eaf6;color:#1a237e;font-weight:bold}.head{background:#37474f;color:#fff;font-weight:bold}.subtotal{background:#f3f4f6;font-weight:bold}.money{mso-number-format:"#,##0.00"}</style></head><body>';
    echo '<table>';
    
    // Total kolom = 22 (karena Customer Code, Abbr, dan Comp sudah dihilangkan)
    echo '<tr><td colspan="22" class="title">MCS PRODUCTION - ' . h($plantDisplay) . '</td></tr>';
    echo '<tr><td colspan="22">Harga material dikonversi ke IDR berdasarkan PO terakhir dan kurs pada tanggal PO.</td></tr>';
    echo '<tr class="head">';

    // Susunan kolom dibuat mendatar seperti tampilan di web
    $headers = array(
        'No', 'Part Code', 'Part Name', 'Tonase', 'Cycle', 'Cavity', 'Part Weight', 'Runner Weight', 'RCLY', 'Prod Unit',
        'Material Code', 'Material Name', 'ITTY', 'BOM Qty', 'BOM Unit', 'Harga Asli', 'Curr',
        'Kurs', 'Harga IDR', 'Total Material IDR', 'Trial Date', 'Trial Remark'
    );
    foreach ($headers as $head) echo '<th>' . h($head) . '</th>';
    echo '</tr>';

    $currentCustomer = null;
    $currentPart = null;
    $no = 1;

    foreach ($dataRows as $row) {
        $ckey = customer_key($row);
        $pkey = isset($row['PART_ID']) ? (string)$row['PART_ID'] : '';

        if ($ckey !== $currentCustomer) {
            $currentCustomer = $ckey;
            $currentPart = null;
            echo '<tr class="customer"><td colspan="22">CUSTOMER: ' . h(customer_label($row)) . '</td></tr>';
        }

        if ($pkey !== $currentPart) {
            $currentPart = $pkey;
            $no = 1;
            // Header PART dibuat rapi, cukup Code & Name seperti di web
            echo '<tr class="part"><td colspan="22">PART: ' . h($row['PART_CODE']) . ' - ' . h($row['PART_NAME']) . '</td></tr>';
        }

        echo '<tr>';
        $values = array(
            $no++, 
            $row['PART_CODE'], 
            $row['PART_NAME'],
            number_format((float)$row['TONASE'], 0, '', ''),
            number_format((float)$row['ITEM_CYTM'], 2, '.', ''),
            number_format((float)$row['ITEM_CAVT'], 2, '.', ''),
            number_format((float)$row['ITEM_WEIGHT'], 2, '.', ''),
            number_format((float)$row['ITEM_RWEIGHT'], 2, '.', ''),
            number_format((float)$row['ITEM_RCLY'], 2, '.', ''),
            $row['PROD_UNIT'],
            $row['MAT_CODE'], 
            $row['MAT_NAME'], 
            $row['MAT_ITTY_CODE'], 
            $row['BOM_QTY'], 
            $row['BOM_UNIT'], 
            $row['HARGA_ASLI'], 
            $row['MATA_UANG'],
            $row['KURS_VRATE'], 
            $row['HARGA_IDR'], 
            $row['TOTAL_MATERIAL_IDR'], 
            fmt_date($row['TRIAL_DATE']), 
            $row['TRIAL_REM']
        );
        
        foreach ($values as $index => $value) {
            // Index format uang disesuaikan: Harga Asli (15), Kurs (17), Harga IDR (18), Total Material (19)
            $class = in_array($index, array(15, 17, 18, 19), true) ? ' class="money"' : '';
            echo '<td' . $class . '>' . h($value) . '</td>';
        }
        echo '</tr>';
    }

    // Colspan 19 akan mendorong sel Grand Total tepat di bawah kolom "Total Material IDR" (Kolom ke-20)
    echo '<tr class="subtotal"><td colspan="19">GRAND TOTAL IDR</td><td class="money">' . number_format($totalHarga, 2, '.', '') . '</td><td colspan="2"></td></tr>';
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
<title>MCS Production - Harga Material IDR</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:#eef2f7;color:#1f2937;padding:16px;font-size:12px}
.container{max-width:1900px;margin:0 auto}
.header{background:linear-gradient(135deg,#0f172a,#1e3a8a);color:#fff;padding:18px 22px;border-radius:10px 10px 0 0;display:flex;justify-content:space-between;gap:16px;align-items:center}
.header h1{font-size:22px;margin-bottom:4px}.header p{font-size:12px;opacity:.82}.header-right{display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
.badge-header{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:18px;padding:7px 11px;font-weight:700;white-space:nowrap}
.card{background:#fff;border:1px solid #d7e0ea;box-shadow:0 3px 12px rgba(15,23,42,.06);margin-bottom:12px}.filter-card{border-top:0}.card-title{font-size:13px;font-weight:800;padding:10px 14px;border-bottom:1px solid #e5e7eb;background:#f8fafc}.card-body{padding:13px 14px}
.notice{padding:10px 12px;border-radius:7px;margin:10px 0}.notice.error{background:#fff1f2;color:#991b1b;border:1px solid #fecaca}
.filter-row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}.field{display:flex;flex-direction:column;gap:4px}.field label{font-size:10px;font-weight:800;text-transform:uppercase;color:#64748b}.field input,.field select{height:34px;border:1px solid #cbd5e1;border-radius:6px;padding:6px 9px;font-size:12px;background:#fff;min-width:165px}.field input.wide{min-width:240px}.field select.server{background:#fff7ed;border-color:#fb923c;color:#9a3412;font-weight:800}
.btn{height:34px;border:0;border-radius:6px;padding:8px 13px;font-size:11px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap}.btn-primary{background:#1d4ed8;color:#fff}.btn-warning{background:#f59e0b;color:#fff}.btn-success{background:#15803d;color:#fff}.btn-light{background:#e2e8f0;color:#1f2937}
.guide{background:#fffde7;border:1px solid #fde68a;color:#713f12;padding:12px 14px;line-height:1.55}.guide h3{font-size:13px;margin-bottom:6px}.guide-grid{display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:8px}.guide-item{background:rgba(255,255,255,.55);border-radius:6px;padding:8px}.guide-item b{color:#92400e}
.stats{display:grid;grid-template-columns:repeat(6,minmax(140px,1fr));gap:10px;margin:12px 0}.stat{background:#fff;border-radius:8px;padding:12px;border-left:4px solid #1d4ed8;box-shadow:0 2px 8px rgba(15,23,42,.05)}.stat:nth-child(2){border-left-color:#7c3aed}.stat:nth-child(3){border-left-color:#0f766e}.stat:nth-child(4){border-left-color:#15803d}.stat:nth-child(5){border-left-color:#f59e0b}.stat:nth-child(6){border-left-color:#dc2626}.stat-label{font-size:10px;color:#64748b;text-transform:uppercase;font-weight:800}.stat-value{font-size:17px;font-weight:900;margin-top:3px;color:#0f172a}
.toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px}.table-wrap{overflow:auto;max-height:700px;border:1px solid #cbd5e1;border-radius:8px}
table{width:100%;border-collapse:collapse;min-width:2500px}thead th{position:sticky;top:0;z-index:4;background:#374151;color:#fff;padding:8px 7px;font-size:10px;text-transform:uppercase;letter-spacing:.25px;text-align:left;white-space:nowrap;border-right:1px solid rgba(255,255,255,.13)}tbody td{padding:7px 8px;border-right:1px solid #edf2f7;border-bottom:1px solid #e5e7eb;white-space:nowrap;vertical-align:middle}
/* CSS Freeze Pane */
tbody tr.detail { background-color: #ffffff; }
tbody tr.detail:hover td { background:#f8fbff; }
.sticky-col { position: sticky !important; background-color: inherit; z-index: 2; }
thead th.sticky-col { background-color: #374151 !important; z-index: 5 !important; }
.split-border { border-right: 3px solid #94a3b8 !important; }
.resizer { width: 8px; height: 100%; position: absolute; right: 0; top: 0; cursor: col-resize; z-index: 10; transition: background 0.2s; }
.resizer:hover, .resizer.resizing { background-color: #3b82f6; opacity: 0.8; }
/* End CSS Freeze Pane */
.text-right{text-align:right}.text-center{text-align:center}.bold{font-weight:800}.money{color:#1e3a8a;font-weight:900}
.customer-row td{background:#dbeafe!important;color:#1e3a8a;font-weight:900;font-size:13px;padding:10px!important;border-top:2px solid #93c5fd}.customer-meta{float:right;font-size:10px;color:#475569}.part-row td{background:#e8eaf6!important;color:#312e81;font-weight:800;padding:8px 10px!important}.subtotal-row td{background:#fdfce8!important;font-weight:800;border-top:2px solid #fef08a;color:#854d0e}.grand-total td{background:#1e3a8a!important;color:#fff;font-weight:900;font-size:13px;padding:11px 8px!important}
.tag{display:inline-block;border-radius:12px;padding:2px 7px;font-size:10px;font-weight:800}.tag-ok{background:#dcfce7;color:#166534}.tag-error{background:#fee2e2;color:#991b1b}.tag-warn{background:#ffedd5;color:#9a3412}.tag-idr{background:#f3e8ff;color:#6b21a8}.tag-usd{background:#e0e7ff;color:#3730a3}.tag-active{background:#dcfce7;color:#166534}.tag-inactive{background:#fee2e2;color:#991b1b}.no-data{text-align:center;padding:38px!important;color:#64748b}
.footer{text-align:center;color:#64748b;font-size:11px;padding:12px}.muted{color:#64748b}.nowrap{white-space:nowrap}
@media(max-width:1000px){body{padding:8px}.header{flex-direction:column;align-items:flex-start}.header-right{justify-content:flex-start}.guide-grid{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,1fr)}.filter-row{flex-direction:column;align-items:stretch}.field input,.field select,.field input.wide{width:100%;min-width:0}.btn{width:100%}}
/* Input Styling */
.input-edit { width: 60px; text-align: right; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px; font-size: 11px; background: #fff; transition: all 0.2s; }
.input-edit:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); }
select.input-edit { width: 85px; text-align: left; }
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

                <div class="field">
                    <label>Customer</label>
                    <input class="wide" type="text" name="customer" list="customer-list" value="<?php echo h($filterCustomer); ?>" placeholder="Ketik atau pilih customer..." autocomplete="off">
                    <datalist id="customer-list">
                        <?php foreach ($customerOptions as $customer): ?>
                            <?php
                            $customerValue = trim((string)$customer['CUST_CODE']);
                            if ($customerValue === '') $customerValue = trim((string)$customer['CUST_ABBR']);
                            if ($customerValue === '') $customerValue = trim((string)$customer['CUST_COMP']);
                            if ($customerValue === '') continue;
                            ?>
                            <option value="<?php echo h($customerValue); ?>"><?php echo h(customer_label($customer)); ?></option>
                        <?php endforeach; ?>
                    </datalist>
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
                    <input type="text" name="tonase" value="<?php echo h($filterTonase); ?>" placeholder="Contoh: 350">
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
        <h3>📘 Panduan Edit Data (ENTER untuk Simpan)</h3>
        <div class="guide-grid">
            <div class="guide-item"><b>BOM QTY:</b> Ubah angka QTY lalu tekan ENTER. Perubahan disimpan ke `BOM_DEFAULT`.</div>
            <div class="guide-item"><b>Info Mesin & Part:</b> Tonase (Pilih Combo), Cycle, Cavity, Runner Weight, dll bisa diedit lalu ENTER.</div>
            <div class="guide-item"><b>Data Part Otomatis:</b> Jika Part diedit di satu baris material, baris material lain akan otomatis sinkron di layar.</div>
            <div class="guide-item"><b>Keamanan:</b> Setiap merubah dan menyimpan (ENTER / Change) diwajibkan memasukkan password `q9tj9`.</div>
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
                        <th>Tonase</th>
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
                                        <td colspan="19" class="text-right">TOTAL HARGA SATUAN PART</td>
                                        <td class="text-right money"><?php echo fmt_idr($subtotalPart); ?></td>
                                        <td colspan="5"></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($currentCustomer !== null): ?>
                                    <tr class="subtotal-row">
                                        <td colspan="19" class="text-right" style="color:#0f766e">TOTAL HARGA CUSTOMER</td>
                                        <td class="text-right money" style="color:#0f766e"><?php echo fmt_idr($subtotalCustomer); ?></td>
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
                                        <td colspan="19" class="text-right">TOTAL HARGA SATUAN PART</td>
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
                                
                                <!-- EDIT TONASE COMBOBOX -->
                                <td>
                                    <select class="input-edit edit-part" data-part="<?php echo h($row['PART_ID']); ?>" data-field="tonase">
                                        <option value="">- Pilih -</option>
                                        <?php foreach ($magOptions as $mag): ?>
                                            <option value="<?php echo h($mag['MAG_ID']); ?>" <?php echo ($row['MAG_ID'] == $mag['MAG_ID']) ? 'selected' : ''; ?>>
                                                <?php echo h($mag['MAG_STATION']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                
                                <!-- EDIT CYCLE, CAVITY, PART WEIGHT, RUNNER WEIGHT, RCLY, PROD UNIT -->
                                <td class="text-right">
                                    <input type="text" class="input-edit edit-part" data-part="<?php echo h($row['PART_ID']); ?>" data-field="cycle" value="<?php echo number_format((float)$row['ITEM_CYTM'], 2, '.', ''); ?>">
                                </td>
                                <td class="text-right">
                                    <input type="text" class="input-edit edit-part" data-part="<?php echo h($row['PART_ID']); ?>" data-field="cavity" value="<?php echo number_format((float)$row['ITEM_CAVT'], 2, '.', ''); ?>">
                                </td>
                                <td class="text-right">
                                    <input type="text" class="input-edit edit-part" data-part="<?php echo h($row['PART_ID']); ?>" data-field="part_weight" value="<?php echo number_format((float)$row['ITEM_WEIGHT'], 2, '.', ''); ?>">
                                </td>
                                <td class="text-right">
                                    <input type="text" class="input-edit edit-part" data-part="<?php echo h($row['PART_ID']); ?>" data-field="runner_weight" value="<?php echo number_format((float)$row['ITEM_RWEIGHT'], 2, '.', ''); ?>">
                                </td>
                                <td class="text-right">
                                    <input type="text" class="input-edit edit-part" data-part="<?php echo h($row['PART_ID']); ?>" data-field="rcly" value="<?php echo number_format((float)$row['ITEM_RCLY'], 2, '.', ''); ?>">
                                </td>
                                <td>
                                    <input type="text" class="input-edit edit-part" data-part="<?php echo h($row['PART_ID']); ?>" data-field="prod_unit" value="<?php echo h($row['PROD_UNIT']); ?>" style="text-align:left; width: 45px;">
                                </td>
                                
                                <td class="bold"><?php echo h($row['MAT_CODE']); ?></td>
                                <td><?php echo h($row['MAT_NAME']); ?></td>
                                <td class="text-center"><span class="tag tag-idr"><?php echo h($row['MAT_ITTY_CODE']); ?></span></td>
                                
                                <!-- EDIT QTY -->
                                <td class="text-right">
                                    <?php $qtyDec = (trim($row['MAT_ITTY_CODE']) === '03') ? 1 : 2; ?>
                                    <input type="text" class="input-edit edit-qty" data-part="<?php echo h($row['PART_ID']); ?>" data-mat="<?php echo h($row['MAT_ID']); ?>" value="<?php echo number_format((float)$row['BOM_QTY'], $qtyDec, '.', ''); ?>">
                                </td>
                                
                                <td><?php echo h($row['BOM_UNIT']); ?></td>
                                <td class="text-right"><?php echo fmt_num($row['HARGA_ASLI'], 2); ?></td>
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
                                <td colspan="19" class="text-right">TOTAL HARGA SATUAN PART</td>
                                <td class="text-right money"><?php echo fmt_idr($subtotalPart); ?></td>
                                <td colspan="5"></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($currentCustomer !== null): ?>
                            <tr class="subtotal-row">
                                <td colspan="19" class="text-right" style="color:#0f766e">TOTAL HARGA CUSTOMER</td>
                                <td class="text-right money" style="color:#0f766e"><?php echo fmt_idr($subtotalCustomer); ?></td>
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
document.addEventListener('DOMContentLoaded', function() {

    // ==========================================
    // 1. FREEZE PANE & DRAG RESIZER 
    // ==========================================
    setTimeout(() => {
        const table = document.querySelector('.table-wrap table');
        if (!table) return;

        const ths = table.querySelectorAll('thead th');
        const trs = table.querySelectorAll('tbody tr.detail');
        if (ths.length < 4) return;

        const thNo = ths[0];
        const thCode = ths[1];
        const thName = ths[2];

        const left0 = 0;
        const left1 = thNo.offsetWidth;
        const left2 = left1 + thCode.offsetWidth;

        thNo.classList.add('sticky-col'); thNo.style.left = left0 + 'px';
        thCode.classList.add('sticky-col'); thCode.style.left = left1 + 'px';
        thName.classList.add('sticky-col', 'split-border'); thName.style.left = left2 + 'px';

        trs.forEach(tr => {
            const tds = tr.children;
            if (tds.length >= 3) {
                tds[0].classList.add('sticky-col'); tds[0].style.left = left0 + 'px';
                tds[1].classList.add('sticky-col'); tds[1].style.left = left1 + 'px';
                tds[2].classList.add('sticky-col', 'split-border'); tds[2].style.left = left2 + 'px';
            }
        });

        const specialRows = table.querySelectorAll('tbody tr.customer-row, tbody tr.part-row, tbody tr.subtotal-row, tbody tr.grand-total');
        specialRows.forEach(tr => {
            const td = tr.firstElementChild;
            if (td && td.hasAttribute('colspan')) {
                td.style.position = 'sticky';
                td.style.left = '0';
                td.style.zIndex = '1';
            }
        });

        const resizer = document.createElement('div');
        resizer.className = 'resizer';
        thName.appendChild(resizer);

        let startX, startWidth;

        const onMouseMove = function(e) {
            const newWidth = startWidth + (e.clientX - startX);
            if (newWidth > 100) { 
                thName.style.minWidth = newWidth + 'px';
                thName.style.width = newWidth + 'px';
            }
        };

        const onMouseUp = function() {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            resizer.classList.remove('resizing');
            document.body.style.cursor = 'default';
        };

        resizer.addEventListener('mousedown', function(e) {
            startX = e.clientX;
            startWidth = thName.offsetWidth;
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
            resizer.classList.add('resizing');
            document.body.style.cursor = 'col-resize';
            e.preventDefault();
        });

    }, 100);

    // ==========================================
    // 2. EDIT BOM QTY (AJAX)
    // ==========================================
    const editQtyInputs = document.querySelectorAll('.edit-qty');
    editQtyInputs.forEach(input => {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const partId = this.getAttribute('data-part');
                const matId = this.getAttribute('data-mat');
                const newQty = this.value.replace(',', '.');

                const pwd = prompt("Update BOM QTY - Masukkan password:");
                if (pwd === 'q9tj9') {
                    const formData = new FormData();
                    formData.append('action', 'update_qty');
                    formData.append('password', pwd);
                    formData.append('part_id', partId);
                    formData.append('mat_id', matId);
                    formData.append('qty', newQty);

                    fetch(window.location.href, { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.style.backgroundColor = '#dcfce7';
                            this.style.borderColor = '#166534';
                            setTimeout(() => { this.style.backgroundColor = ''; this.style.borderColor = ''; }, 1500);
                        } else {
                            alert('Gagal menyimpan: ' + data.message);
                        }
                    })
                    .catch(() => alert('Terjadi kesalahan jaringan/server.'));
                } else if (pwd !== null) {
                    alert("Password salah!");
                }
            }
        });
    });

    // ==========================================
    // 3. EDIT PART INFO (Termasuk Select Tonase)
    // ==========================================
    const editPartInputs = document.querySelectorAll('.edit-part');
    
    const processPartUpdate = function(inputEl) {
        const partId = inputEl.getAttribute('data-part');
        const field = inputEl.getAttribute('data-field');
        
        let newValue = inputEl.value;
        if (inputEl.tagName.toLowerCase() !== 'select') {
            newValue = newValue.replace(',', '.');
        }
        
        let title = field.toUpperCase().replace('_', ' ');

        const pwd = prompt(`Update ${title} - Masukkan password:`);
        if (pwd === 'q9tj9') {
            const formData = new FormData();
            formData.append('action', 'update_part_info');
            formData.append('password', pwd);
            formData.append('part_id', partId);
            formData.append('field', field);
            formData.append('value', newValue);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Sinkronisasi ke semua baris material dengan Part ID yang sama
                    const siblingInputs = document.querySelectorAll(`.edit-part[data-part="${partId}"][data-field="${field}"]`);
                    siblingInputs.forEach(sibling => {
                        sibling.value = newValue;
                        sibling.style.backgroundColor = '#dcfce7';
                        sibling.style.borderColor = '#166534';
                        setTimeout(() => { sibling.style.backgroundColor = ''; sibling.style.borderColor = ''; }, 1500);
                    });
                } else {
                    alert('Gagal menyimpan: ' + data.message);
                }
            })
            .catch(() => alert('Terjadi kesalahan jaringan/server.'));
        } else if (pwd !== null) {
            alert("Password salah!");
            // Kembalikan ke value sebelumnya jika diperlukan (dibatalkan)
        }
    };

    editPartInputs.forEach(input => {
        if (input.tagName.toLowerCase() === 'select') {
            // Dropdown dipicu saat dipilih (change)
            input.addEventListener('change', function() {
                processPartUpdate(this);
            });
        } else {
            // Text dipicu saat ditekan ENTER
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    processPartUpdate(this);
                }
            });
        }
    });

});
</script>
</body>
</html>
<?php
if (isset($conn) && $conn) {
    sqlsrv_close($conn);
}
?>