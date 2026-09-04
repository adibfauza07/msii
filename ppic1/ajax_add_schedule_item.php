<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

$configPath = __DIR__ . "/../config/global.php"; 
if (file_exists($configPath)) { require_once $configPath; }

header('Content-Type: application/json; charset=utf-8');

if (!isset($conn) || $conn === false) {
    echo json_encode(array('status' => 'error', 'message' => 'Koneksi database gagal.'));
    exit;
}

$tahun = isset($_POST['tahun']) ? (int)$_POST['tahun'] : 0;
$bulan = isset($_POST['bulan']) ? (int)$_POST['bulan'] : 0;
$mc_no = isset($_POST['mc_no']) ? trim($_POST['mc_no']) : '';
$item_code = isset($_POST['item_code']) ? trim($_POST['item_code']) : '';

if (empty($tahun) || empty($bulan) || empty($mc_no) || empty($item_code)) {
    echo json_encode(array('status' => 'error', 'message' => 'Data tidak lengkap.'));
    exit;
}

$periode = sprintf('%04d%02d', $tahun, $bulan);

// 1. Parameter Estimasi Order (Bulan Berjalan)
$curMonthStart = sprintf('%04d-%02d-01 00:00:00.000', $tahun, $bulan);

// 2. Kalkulasi Bulan Depan (Bulan N+1) untuk Forecast
$nextMonth = $bulan + 1;
$nextYear  = $tahun;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}
$nextMonthDate = sprintf('%04d-%02d-01 00:00:00.000', $nextYear, $nextMonth);

/* =====================================================================
   CEK DUPLIKASI: PASTIKAN ITEM BELUM ADA DI MESIN & PERIODE INI
===================================================================== */
$sqlCheck = "SELECT COUNT(*) AS is_exist FROM RPT_PPIC WHERE periode = ? AND MC_NO = ? AND ITEM_CODE = ?";
$stmtCheck = sqlsrv_query($conn, $sqlCheck, array($periode, $mc_no, $item_code));

if ($stmtCheck !== false) {
    $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    if ((int)$rowCheck['is_exist'] > 0) {
        // Jika hasil perhitungan lebih dari 0, berarti data sudah ada
        echo json_encode(array(
            'status' => 'error', 
            'message' => 'Item ' . $item_code . ' sudah ada di jadwal mesin ' . $mc_no . ' untuk periode ini.'
        ));
        exit;
    }
    sqlsrv_free_stmt($stmtCheck);
}

// Mulai Transaksi
sqlsrv_begin_transaction($conn);

/* =====================================================================
   STEP 1: CARI NOMOR URUT TERAKHIR DI MESIN & PERIODE TERSEBUT
===================================================================== */
$sqlMax = "SELECT ISNULL(MAX(CAST(no_urut AS INT)), 0) AS max_urut 
           FROM RPT_PPIC 
           WHERE periode = ? AND MC_NO = ? AND ISNUMERIC(no_urut) = 1";
$stmtMax = sqlsrv_query($conn, $sqlMax, array($periode, $mc_no));

$next_urut = 1; // Default jika mesin tersebut masih kosong
if ($stmtMax !== false) {
    $rowMax = sqlsrv_fetch_array($stmtMax, SQLSRV_FETCH_ASSOC);
    $next_urut = (int)$rowMax['max_urut'] + 1;
    sqlsrv_free_stmt($stmtMax);
}

/* =====================================================================
   STEP 2: INSERT KE RPT_PPIC (DENGAN NOMOR URUT BARU)
===================================================================== */
$sqlInsert = "
    INSERT INTO RPT_PPIC (
        periode, MC_NO, ITEM_CODE, PART_NAME, PART_NO, CUST, 
        CUR_PO_BO, PROD_PLAN, CYCLE_TIME_STD, CAVITY_STD, CAP_DAY_STD, no_urut
    )
    OUTPUT inserted.ID_NO
    SELECT 
        ?, ?, V.PART_CODE, V.PART_NAME, V.PART_NO, V.CUST_COMP,
        ISNULL(EO.ESTIMASI_ORDER, 0),
        ISNULL(F.FORE_QTY, 0),
        ISNULL(IP.cycle_time, 0),
        ISNULL(IP.cave_std, 0),
        ISNULL(IP.CAPD, 0),
        ?
    FROM ITEM_CUSTINFO_VIEW V

    -- JOIN ESTIMASI ORDER
    LEFT JOIN (
        SELECT ITEMS.ITEM_CODE, SUM(EST_ORD.OE_QTY) AS ESTIMASI_ORDER
        FROM EST_ORD 
        INNER JOIN ITEMS ON EST_ORD.ITEM_ID = ITEMS.ITEM_ID
        WHERE EST_ORD.OE_MMYY >= ? AND EST_ORD.OE_MMYY < ?
        GROUP BY ITEMS.ITEM_CODE
    ) EO ON V.PART_CODE = EO.ITEM_CODE

    -- JOIN PROD PLAN / FORECAST
    LEFT JOIN (
        SELECT ITEMS.ITEM_CODE, SUM(FORECAST.FORE_QTY) AS FORE_QTY
        FROM FORECAST 
        INNER JOIN PRICE ON FORECAST.PRICE_ID = PRICE.PRICE_ID 
        INNER JOIN ITEMS ON PRICE.PART_ID = ITEMS.ITEM_ID
        WHERE FORECAST.FORE_MONTH = ?
        GROUP BY ITEMS.ITEM_CODE
    ) F ON V.PART_CODE = F.ITEM_CODE

    -- JOIN CYCLE TIME, CAVITY & HITUNG CAP DAYS
    LEFT JOIN (
        SELECT 
            ITEMS.ITEM_CODE, 
            IP.ITEM_CYTM AS cycle_time, 
            IP.ITEM_CAVT AS cave_std,
            ROUND((3600 / NULLIF(IP.ITEM_CYTM, 0) * IP.ITEM_CAVT * 24) * (PR.PROC_EFFICIENTCY / 100), 0) AS CAPD
        FROM ITEM_PROD IP
        INNER JOIN ITEMS ON IP.ITEM_ID = ITEMS.ITEM_ID
        LEFT JOIN MAG M ON IP.MAG_ID = M.MAG_ID
        LEFT JOIN PROCESS PR ON M.PROC_ID = PR.PROC_ID
    ) IP ON V.PART_CODE = IP.ITEM_CODE

    WHERE LTRIM(RTRIM(V.PART_CODE)) = ?
";

// Parameter disesuaikan dengan penambahan $next_urut
$params = array(
    $periode, 
    $mc_no,
    $next_urut, 
    $curMonthStart, 
    $nextMonthDate, 
    $nextMonthDate, 
    $item_code
);

$stmtInsert = sqlsrv_query($conn, $sqlInsert, $params);

if ($stmtInsert === false) {
    sqlsrv_rollback($conn);
    $errors = sqlsrv_errors();
    echo json_encode(array('status' => 'error', 'message' => 'Gagal Insert Master: ' . $errors[0]['message']));
    exit;
}

$rowId = sqlsrv_fetch_array($stmtInsert, SQLSRV_FETCH_ASSOC);
$new_id_no = $rowId['ID_NO'];
sqlsrv_free_stmt($stmtInsert);

/* =====================================================================
   STEP 3: INSERT TEMPLATE BARIS KE RPT_PPIC_DTL
===================================================================== */
$descRows = array(
    'Del Plan', 'Del Actual', 'Del Balance', 
    'Prod Plan R0', 'Prod Plan R1', 'Prod NG', 'Prod OK', 
    'Prod HOLD', 'Prod Balance', 'NG Rework', 
    'Est Stock Plan', 'Est Stock Actual'
);

$sqlDtl = "INSERT INTO RPT_PPIC_DTL (ID_NO, DESC_PROD) VALUES (?, ?)";

foreach ($descRows as $desc) {
    $stmtDtl = sqlsrv_query($conn, $sqlDtl, array($new_id_no, $desc));
    if ($stmtDtl === false) {
        sqlsrv_rollback($conn);
        echo json_encode(array('status' => 'error', 'message' => 'Gagal Insert Detail (Matrix).'));
        exit;
    }
    sqlsrv_free_stmt($stmtDtl);
}

sqlsrv_commit($conn);
echo json_encode(array('status' => 'success', 'message' => 'Item berhasil ditambahkan ke jadwal produksi.'));
exit;
?>