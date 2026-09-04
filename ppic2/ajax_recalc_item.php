<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
// BUKA KUNCI SESSION SEGERA AGAR AJAX LAIN TIDAK FREEZE/HANG
session_write_close(); 

error_reporting(0);
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

$configPath = __DIR__ . "/../config/global.php"; 
if (file_exists($configPath)) { require_once $configPath; }

if (!isset($conn) || $conn === false) {
    die(json_encode(array('status' => 'error', 'message' => 'Koneksi database gagal.')));
}

// 1. Ambil Parameter dari AJAX
$id_no     = isset($_POST['id_no']) ? trim($_POST['id_no']) : '';
$tahun     = isset($_POST['tahun']) ? trim($_POST['tahun']) : '';
$bulan     = isset($_POST['bulan']) ? trim($_POST['bulan']) : '';
$mc_no     = isset($_POST['mc_no']) ? trim($_POST['mc_no']) : '';
$item_code = isset($_POST['item_code']) ? trim($_POST['item_code']) : '';

if (!$id_no || !$tahun || !$bulan || !$mc_no || !$item_code) {
    die(json_encode(array('status' => 'error', 'message' => 'Parameter Re-Calc tidak lengkap.')));
}

// 2. Kalkulasi SARGable Date Range 
$curYear  = (int)$tahun;
$curMonth = (int)$bulan;

$startDateStr = sprintf('%04d%02d01', $curYear, $curMonth);
$endDateStr   = date("Ymd", strtotime(sprintf('%04d-%02d-01', $curYear, $curMonth) . " +1 month -1 day"));

$nextMonth = $curMonth + 1;
$nextYear  = $curYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}
$startDateProd = sprintf('%04d%02d01', $curYear, $curMonth);
$endDateProd   = sprintf('%04d%02d01', $nextYear, $nextMonth);

// 3. Muat Data Eksisting (Seperti Prod Plan R0, Del Plan, Del Actual) yang sudah tersimpan di DB
$loaded_data = array(
    'Del Plan'     => array_fill(1, 31, 0),
    'Del Actual'   => array_fill(1, 31, 0),
    'Prod Plan R0' => array_fill(1, 31, 0)
);

$sqlLoadDtl = "SELECT DESC_PROD, D1, D2, D3, D4, D5, D6, D7, D8, D9, D10,
                      D11, D12, D13, D14, D15, D16, D17, D18, D19, D20,
                      D21, D22, D23, D24, D25, D26, D27, D28, D29, D30, D31
               FROM dbo.RPT_PPIC_DTL 
               WHERE ID_NO = ?";
$stmtLoadDtl = sqlsrv_query($conn, $sqlLoadDtl, array($id_no));
if ($stmtLoadDtl !== false) {
    while ($rowDtl = sqlsrv_fetch_array($stmtLoadDtl, SQLSRV_FETCH_ASSOC)) {
        $descName = trim($rowDtl['DESC_PROD']);
        if (array_key_exists($descName, $loaded_data)) {
            for ($i = 1; $i <= 31; $i++) {
                $loaded_data[$descName][$i] = (float)$rowDtl['D' . $i];
            }
        }
    }
    sqlsrv_free_stmt($stmtLoadDtl);
}

// 4. Dapatkan ITTY_CODE untuk Proteksi "Part Injection"
$itty_code = '';
$sqlItem = "SELECT TOP 1 LTRIM(RTRIM(ITTY_CODE)) AS ITTY_CODE FROM dbo.ITEMS WHERE ITEM_CODE = ?";
$stmtItem = sqlsrv_query($conn, $sqlItem, array($item_code));
if ($stmtItem !== false && $rowItem = sqlsrv_fetch_array($stmtItem, SQLSRV_FETCH_ASSOC)) {
    $itty_code = $rowItem['ITTY_CODE'];
    sqlsrv_free_stmt($stmtItem);
}

// INISIALISASI ARRAY HARIAN
$daily = array(
    'Prod OK'      => array_fill(1, 31, 0),
    'Prod HOLD'    => array_fill(1, 31, 0),
    'Prod NG'      => array_fill(1, 31, 0),
    'Prod Plan R0' => $loaded_data['Prod Plan R0'],
    'Del Plan'     => $loaded_data['Del Plan'],
    'Del Actual'   => $loaded_data['Del Actual']
);

$isInjection = ($itty_code === '01');

// =================================================================================
// A. AMBIL DATA PRODUKSI AKTUAL
// =================================================================================
$sqlProd = "SELECT DAY(P.PD_DATE) AS D,
                   SUM(P.PD_OK) AS OK_QTY,
                   SUM(P.PD_HO) AS HOLD_QTY,
                   SUM(P.PD_NG) AS NG_QTY
            FROM dbo.PRODUCTION P
            WHERE P.PD_DATE >= CAST(? AS DATE) AND P.PD_DATE < CAST(? AS DATE)
            AND P.WO_ID IN (
                SELECT W.WO_ID
                FROM dbo.WO W
                INNER JOIN dbo.MAC M ON W.MAC_ID = M.MAC_ID
                INNER JOIN dbo.ITEM_CUSTINFO_VIEW ICV ON W.ITEM_ID = ICV.ITEM_ID
                WHERE W.WO_MMYY >= ? AND W.WO_MMYY < ?
                AND M.MAC_CODE = ? AND ICV.PART_CODE = ?
            )
            GROUP BY DAY(P.PD_DATE)";

$stmtProd = sqlsrv_query($conn, $sqlProd, array($startDateProd, $endDateProd, $startDateProd, $endDateProd, $mc_no, $item_code));
if ($stmtProd !== false) {
    while ($r = sqlsrv_fetch_array($stmtProd, SQLSRV_FETCH_ASSOC)) {
        $d = (int)$r['D'];
        if ($d >= 1 && $d <= 31) {
            $daily['Prod OK'][$d]   = (float)$r['OK_QTY'];
            $daily['Prod HOLD'][$d] = (float)$r['HOLD_QTY'];
            $daily['Prod NG'][$d]   = (float)$r['NG_QTY'];
        }
    }
    sqlsrv_free_stmt($stmtProd);
}

// =================================================================================
// B & C. AMBIL DATA DELIVERY (JIKA BUKAN PART INJECTION)
// =================================================================================
if (!$isInjection) {
    // Delivery Plan
    $sqlDp = "SELECT DAY(DS.DELS_DATE) AS D, SUM(DS.DELS_QTY) AS QTY
              FROM dbo.DELI_SCH DS
              INNER JOIN dbo.PRICE P ON DS.PRICE_ID = P.PRICE_ID
              INNER JOIN dbo.ITEMS I ON P.PART_ID = I.ITEM_ID
              WHERE LTRIM(RTRIM(I.ITEM_CODE)) = ? 
              AND CONVERT(CHAR(8), DS.DELS_DATE, 112) BETWEEN ? AND ?
              GROUP BY DAY(DS.DELS_DATE)";
              
    $stmtDp = sqlsrv_query($conn, $sqlDp, array($item_code, $startDateStr, $endDateStr));
    if ($stmtDp !== false) {
        while ($r = sqlsrv_fetch_array($stmtDp, SQLSRV_FETCH_ASSOC)) {
            $d = (int)$r['D'];
            if ($d >= 1 && $d <= 31) $daily['Del Plan'][$d] = (float)$r['QTY'];
        }
        sqlsrv_free_stmt($stmtDp);
    }

    // Delivery Actual
    $sqlDa = "SELECT DAY(DI.DI_DATE) AS D, SUM(DIPA_PAR.QTY) AS QTY
              FROM dbo.DIPA_PAR
              INNER JOIN dbo.DI ON DIPA_PAR.DI_ID = DI.DI_ID
              INNER JOIN dbo.PRICE P ON DIPA_PAR.PRICE_ID = P.PRICE_ID
              INNER JOIN dbo.ITEMS I ON P.PART_ID = I.ITEM_ID
              WHERE LTRIM(RTRIM(I.ITEM_CODE)) = ? 
              AND CONVERT(CHAR(8), DI.DI_DATE, 112) BETWEEN ? AND ?
              GROUP BY DAY(DI.DI_DATE)";
              
    $stmtDa = sqlsrv_query($conn, $sqlDa, array($item_code, $startDateStr, $endDateStr));
    if ($stmtDa !== false) {
        while ($r = sqlsrv_fetch_array($stmtDa, SQLSRV_FETCH_ASSOC)) {
            $d = (int)$r['D'];
            if ($d >= 1 && $d <= 31) $daily['Del Actual'][$d] = (float)$r['QTY'];
        }
        sqlsrv_free_stmt($stmtDa);
    }
}

// =================================================================================
// D. HITUNG / KALKULASI BALANCE HARIAN (1 s/d 31) - RUNNING BALANCE
// =================================================================================

// 1. Inisialisasi Saldo Awal dengan aman (Mencegah Undefined Variable)
$beg_balance = isset($beg_balance) ? (float)$beg_balance : 0;

$running_stock_plan   = $beg_balance;
$running_stock_actual = $beg_balance;
$run_del_bal          = 0; 
$run_prod_bal         = 0; 

// Pastikan array baris kalkulasi tersedia agar tidak terjadi error Undefined Index
$kalkulasi_rows = array('Del Balance', 'Prod Balance', 'Est Stock Plan', 'Est Stock Actual');
foreach ($kalkulasi_rows as $rowName) {
    if (!isset($daily[$rowName])) {
        $daily[$rowName] = array_fill(1, 31, 0);
    }
}

// 2. Gabungkan ke dalam SATU Looping Saja
for ($i = 1; $i <= 31; $i++) {
    
    // Tarik nilai harian dengan aman (Fallback ke 0 jika kosong)
    $dp = isset($daily['Del Plan'][$i]) ? (float)$daily['Del Plan'][$i] : 0;
    $da = isset($daily['Del Actual'][$i]) ? (float)$daily['Del Actual'][$i] : 0;
    $po = isset($daily['Prod OK'][$i]) ? (float)$daily['Prod OK'][$i] : 0;
    $pp = isset($daily['Prod Plan R0'][$i]) ? (float)$daily['Prod Plan R0'][$i] : 0;

    // A. Del Balance = Akumulasi (Saldo Kemarin + (Plan - Actual))
    $run_del_bal += ($dp - $da);
    $daily['Del Balance'][$i] = $run_del_bal;
    
    // B. Prod Balance = Akumulasi (Saldo Kemarin + (OK - Plan R0))
    $run_prod_bal += ($po - $pp);
    $daily['Prod Balance'][$i] = $run_prod_bal;

    // C. Est Stock Plan = Akumulasi (Saldo Stok Kemarin + Prod Plan - Del Plan)
    $running_stock_plan = $running_stock_plan + $pp - $dp;
    $daily['Est Stock Plan'][$i] = $running_stock_plan;

    // D. Est Stock Actual = Akumulasi (Saldo Stok Kemarin + Prod OK - Del Actual)
    $running_stock_actual = $running_stock_actual + $po - $da;
    $daily['Est Stock Actual'][$i] = $running_stock_actual;
}

// =================================================================================
// 4. UPDATE KE TABEL RPT_PPIC_DTL
// =================================================================================
$success = true;
$errMsg = '';

// Daftar baris yang Grand Total-nya adalah Saldo Akhir (Bukan SUM)
$running_balance_rows = array('Del Balance', 'Prod Balance', 'Est Stock Plan', 'Est Stock Actual');

foreach ($daily as $desc => $days) {
    
    // 3. Logika Penentuan Grand Total yang Tepat
    if (in_array($desc, $running_balance_rows)) {
        // Jika baris akumulasi, Grand Total = Saldo di tanggal 31
        $g_total = (float)$days[31]; 
    } else {
        // Jika baris transaksi normal (Plan/Actual), Grand Total = SUM(tgl 1 - 31)
        $g_total = array_sum($days); 
    }
    
    // 4. Parameterized Query Update (Mencegah SQL Injection di SQL Server 2008)
    $sqlUpd = "UPDATE dbo.RPT_PPIC_DTL
               SET G_TOTAL=?, D1=?, D2=?, D3=?, D4=?, D5=?, D6=?, D7=?, D8=?, D9=?, D10=?,
                   D11=?, D12=?, D13=?, D14=?, D15=?, D16=?, D17=?, D18=?, D19=?, D20=?,
                   D21=?, D22=?, D23=?, D24=?, D25=?, D26=?, D27=?, D28=?, D29=?, D30=?, D31=?
               WHERE ID_NO = ? AND DESC_PROD = ?";
               
    // Susun parameter secara berurutan
    $params = array($g_total);
    for ($i = 1; $i <= 31; $i++) { 
        // Pastikan format angka aman untuk masuk ke SQL Server
        $params[] = isset($days[$i]) ? (float)$days[$i] : 0; 
    }
    $params[] = $id_no;
    $params[] = $desc;

    // Eksekusi Statement
    $stmtUpd = sqlsrv_query($conn, $sqlUpd, $params);
    if ($stmtUpd === false) { 
        $err = sqlsrv_errors();
        $errMsg = isset($err[0]['message']) ? $err[0]['message'] : 'Unknown DB Error';
        $success = false; 
        break; // Hentikan loop jika ada yang gagal 
    }
    
    // Bebaskan resource memory secepatnya (Sangat disarankan di eksekusi looping SQLSRV)
    sqlsrv_free_stmt($stmtUpd);
}

// 5. Output Response JSON
if ($success) {
    echo json_encode(array('status' => 'success', 'message' => 'Data Aktual & Balance berhasil dikalkulasi ulang!'));
} else {
    echo json_encode(array('status' => 'error', 'message' => 'Update gagal: ' . $errMsg));
}
exit;
?>