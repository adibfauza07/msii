<?php
/**
 * STOCK ANALYSIS UNIVERSAL - WHS DAN REFERENSI PRODUKSI
 * PHP 5.4 + SQLSRV + SQL Server 2008
 *
 * DEFINISI PERIODE:
 *   start_date = tanggal stok awal
 *   end_date   = tanggal stok akhir/opname
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/global.php';

/* =========================================================
 * FUNGSI
 * ========================================================= */
function sap_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sap_valid_date($value)
{
    if (
        !is_string($value) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
    ) {
        return false;
    }

    $parts = explode('-', $value);

    return count($parts) == 3 &&
        checkdate(
            (int) $parts[1],
            (int) $parts[2],
            (int) $parts[0]
        );
}

function sap_date($value, $format)
{
    if ($value instanceof DateTime) {
        return $value->format($format);
    }

    if ($value === null || $value === '') {
        return '';
    }

    $time = strtotime((string) $value);

    if ($time === false) {
        return (string) $value;
    }

    return date($format, $time);
}

function sap_number($value)
{
    return number_format((float) $value, 2, ',', '.');
}

function sap_float_param($name)
{
    if (!isset($_GET[$name]) || trim($_GET[$name]) === '') {
        return null;
    }

    $value = str_replace(',', '', trim($_GET[$name]));

    return is_numeric($value) ? (float) $value : null;
}

function sap_sql_error()
{
    $errors = sqlsrv_errors();

    if (!is_array($errors)) {
        return 'Kesalahan SQL Server tidak diketahui.';
    }

    $messages = array();

    foreach ($errors as $error) {
        $messages[] =
            '[' . $error['SQLSTATE'] . '] '
            . $error['code']
            . ' - '
            . $error['message'];
    }

    return implode("\n", $messages);
}

/* =========================================================
 * PARAMETER
 * ========================================================= */
$reportType = isset($_GET['report_type'])
    ? strtolower(trim($_GET['report_type']))
    : 'bahanbaku';

if ($reportType != 'barang') {
    $reportType = 'bahanbaku';
}

$reportTitle = ($reportType == 'barang')
    ? 'STOCK ANALYSIS BARANG JADI'
    : 'STOCK ANALYSIS BAHAN BAKU';

$summaryProcedure = ($reportType == 'barang')
    ? 'dbo.sp_laporan_mutasi_barang1'
    : 'dbo.sp_laporan_mutasi_bahanbaku1';

$backPage = ($reportType == 'barang')
    ? 'mutasi_barang.php'
    : 'mutasi_bahanbaku.php';

$passedItem = isset($_GET['item_id'])
    ? trim($_GET['item_id'])
    : '';

$startDate = isset($_GET['start_date'])
    ? trim($_GET['start_date'])
    : date('Y-m-01');

$endDate = isset($_GET['end_date'])
    ? trim($_GET['end_date'])
    : date('Y-m-d');

$saldoAwalParam  = sap_float_param('saldo_awal');
$stokOpnameParam = sap_float_param('stok_opname');

$errors = array();

if ($passedItem === '') {
    $errors[] = 'Kode atau ID barang wajib diisi.';
}

if (!sap_valid_date($startDate)) {
    $errors[] = 'Tanggal awal tidak valid.';
}

if (!sap_valid_date($endDate)) {
    $errors[] = 'Tanggal akhir tidak valid.';
}

if (
    sap_valid_date($startDate) &&
    sap_valid_date($endDate) &&
    strtotime($startDate) >= strtotime($endDate)
) {
    $errors[] = 'Tanggal akhir harus lebih besar dari tanggal awal.';
}

$transactionEndDate = sap_valid_date($endDate)
    ? date('Y-m-d', strtotime($endDate . ' -1 day'))
    : $endDate;

/* =========================================================
 * ITEM
 * ========================================================= */
$itemId = 0;
$itemCode = '';
$itemName = '';
$itemUnit = '';
$queryError = '';

if (count($errors) == 0) {
    if (is_numeric($passedItem)) {
        $itemSql = "
            SELECT TOP 1
                ITEM_ID, ITEM_CODE, ITEM_NAME, ITEM_UNIT
            FROM dbo.ITEMS
            WHERE ITEM_ID = ?
        ";
        $itemParams = array((int) $passedItem);
    } else {
        $itemSql = "
            SELECT TOP 1
                ITEM_ID, ITEM_CODE, ITEM_NAME, ITEM_UNIT
            FROM dbo.ITEMS
            WHERE ITEM_CODE = ?
        ";
        $itemParams = array($passedItem);
    }

    $itemStmt = sqlsrv_query($conn, $itemSql, $itemParams);

    if ($itemStmt === false) {
        $queryError = sap_sql_error();
    } else {
        $itemRow = sqlsrv_fetch_array($itemStmt, SQLSRV_FETCH_ASSOC);

        if ($itemRow) {
            $itemId   = (int) $itemRow['ITEM_ID'];
            $itemCode = $itemRow['ITEM_CODE'];
            $itemName = $itemRow['ITEM_NAME'];
            $itemUnit = $itemRow['ITEM_UNIT'];
        } else {
            $errors[] = 'Item tidak ditemukan.';
        }

        sqlsrv_free_stmt($itemStmt);
    }
}

/* =========================================================
 * AMBIL RINGKASAN SP MUTASI (WHS)
 * ========================================================= */
$summaryRow = null;

if (count($errors) == 0 && $queryError == '') {
    $summarySql = "{CALL " . $summaryProcedure . "(?, ?)}";
    $summaryStmt = sqlsrv_query($conn, $summarySql, array($startDate, $endDate));

    if ($summaryStmt === false) {
        $queryError = sap_sql_error();
    } else {
        while ($row = sqlsrv_fetch_array($summaryStmt, SQLSRV_FETCH_ASSOC)) {
            $rowCode = isset($row['KODE_BARANG']) ? trim($row['KODE_BARANG']) : '';
            if (strcasecmp($rowCode, $itemCode) == 0) {
                $summaryRow = $row;
                break;
            }
        }
        sqlsrv_free_stmt($summaryStmt);
    }
}

$saldoAwal = $saldoAwalParam !== null
    ? $saldoAwalParam
    : ($summaryRow !== null && isset($summaryRow['SALDO_AWAL']) ? (float) $summaryRow['SALDO_AWAL'] : 0);

$stokOpname = $stokOpnameParam !== null
    ? $stokOpnameParam
    : ($summaryRow !== null && isset($summaryRow['STOK_OPNAME']) ? (float) $summaryRow['STOK_OPNAME'] : 0);

/* =========================================================
 * TRANSAKSI WHS
 * ========================================================= */
$whsRows = array();

if (count($errors) == 0 && $queryError == '') {
    $whsSql = "
        SELECT
            TR.TRAN_ID, TR.TRAN_DATE, TR.TRAN_DOC, TR.TRAN_DOC2, TR.TRTY_CODE,
            ISNULL(TY.TRTY_DESC, '') AS TRTY_DESC,
            W.LOC_CODE, W.LOC_NAME, W.LOC_GROUP, W.TRTY_INOUT, W.TRTY_SIGN,
            SUM(ISNULL(IT.IT_QTY, 0)) AS IT_QTY
        FROM dbo.TRANS AS TR
        INNER JOIN dbo.INV_TRAN AS IT ON IT.TRAN_ID = TR.TRAN_ID
        INNER JOIN dbo.ITEMS AS I ON I.ITEM_ID = IT.ITEM_ID
        CROSS APPLY
        (
            SELECT TOP 1 L.LOC_CODE, L.LOC_NAME, L.LOC_GROUP, TL.TRTY_INOUT, TL.TRTY_SIGN
            FROM dbo.TRTY_LOC AS TL
            INNER JOIN dbo.LOC AS L ON L.LOC_ID = TL.LOC_ID
            WHERE TL.TRTY_CODE = TR.TRTY_CODE AND L.LOC_CODE = 'WHS'
            ORDER BY TL.LOC_ID
        ) AS W
        LEFT JOIN dbo.TRTY AS TY ON TY.TRTY_CODE = TR.TRTY_CODE
        WHERE I.ITEM_ID = ? AND TR.TRAN_DATE >= ? AND TR.TRAN_DATE < ?
        GROUP BY
            TR.TRAN_ID, TR.TRAN_DATE, TR.TRAN_DOC, TR.TRAN_DOC2, TR.TRTY_CODE, ISNULL(TY.TRTY_DESC, ''),
            W.LOC_CODE, W.LOC_NAME, W.LOC_GROUP, W.TRTY_INOUT, W.TRTY_SIGN
        ORDER BY TR.TRAN_DATE, TR.TRAN_ID, TR.TRTY_CODE
    ";

    $whsStmt = sqlsrv_query($conn, $whsSql, array($itemId, $startDate, $endDate));

    if ($whsStmt === false) {
        $queryError = sap_sql_error();
    } else {
        while ($row = sqlsrv_fetch_array($whsStmt, SQLSRV_FETCH_ASSOC)) {
            $whsRows[] = $row;
        }
        sqlsrv_free_stmt($whsStmt);
    }
}

/* =========================================================
 * REFERENSI LOKASI PRODUKSI / NON-WHS
 * ========================================================= */
$productionRows = array();

if (count($errors) == 0 && $queryError == '') {
    $productionSql = "
        SELECT
            TR.TRAN_ID, TR.TRAN_DATE, TR.TRAN_DOC, TR.TRAN_DOC2, TR.TRTY_CODE,
            ISNULL(TY.TRTY_DESC, '') AS TRTY_DESC,
            L.LOC_CODE, L.LOC_NAME, L.LOC_GROUP, TL.TRTY_INOUT, TL.TRTY_SIGN,
            SUM(ISNULL(IT.IT_QTY, 0)) AS IT_QTY
        FROM dbo.TRANS AS TR
        INNER JOIN dbo.INV_TRAN AS IT ON IT.TRAN_ID = TR.TRAN_ID
        INNER JOIN dbo.ITEMS AS I ON I.ITEM_ID = IT.ITEM_ID
        INNER JOIN dbo.TRTY_LOC AS TL ON TL.TRTY_CODE = TR.TRTY_CODE
        INNER JOIN dbo.LOC AS L ON L.LOC_ID = TL.LOC_ID
        LEFT JOIN dbo.TRTY AS TY ON TY.TRTY_CODE = TR.TRTY_CODE
        WHERE I.ITEM_ID = ? AND L.LOC_CODE <> 'WHS' AND TR.TRAN_DATE >= ? AND TR.TRAN_DATE < ?
        GROUP BY
            TR.TRAN_ID, TR.TRAN_DATE, TR.TRAN_DOC, TR.TRAN_DOC2, TR.TRTY_CODE, ISNULL(TY.TRTY_DESC, ''),
            L.LOC_CODE, L.LOC_NAME, L.LOC_GROUP, TL.TRTY_INOUT, TL.TRTY_SIGN
        ORDER BY L.LOC_GROUP, L.LOC_CODE, TR.TRAN_DATE, TR.TRAN_ID
    ";

    $productionStmt = sqlsrv_query($conn, $productionSql, array($itemId, $startDate, $endDate));

    if ($productionStmt === false) {
        $queryError = sap_sql_error();
    } else {
        while ($row = sqlsrv_fetch_array($productionStmt, SQLSRV_FETCH_ASSOC)) {
            $productionRows[] = $row;
        }
        sqlsrv_free_stmt($productionStmt);
    }
}

/* =========================================================
 * STOK AWAL LOKASI PRODUKSI / NON-WHS (TAGS)
 * ========================================================= */
$productionBeginningRows = array();

if (count($errors) == 0 && $queryError == '') {
    $productionBeginningSql = "
        SELECT 
            L.LOC_ID, L.LOC_CODE, L.LOC_NAME, L.LOC_GROUP,
            SUM(ISNULL(T.TAG_QTY, 0)) AS STOK_AWAL_PRODUKSI
        FROM dbo.TAGS AS T
        INNER JOIN dbo.SOP AS S ON S.SOP_ID = T.SOP_ID
        INNER JOIN dbo.LOC AS L ON L.LOC_ID = T.LOC_ID
        WHERE T.ITEM_ID = ? AND L.LOC_CODE <> 'WHS' AND S.SOP_SDATE = ?
        GROUP BY L.LOC_ID, L.LOC_CODE, L.LOC_NAME, L.LOC_GROUP
    ";

    $productionBeginningStmt = sqlsrv_query($conn, $productionBeginningSql, array($itemId, $startDate));

    if ($productionBeginningStmt === false) {
        $queryError = sap_sql_error();
    } else {
        while ($row = sqlsrv_fetch_array($productionBeginningStmt, SQLSRV_FETCH_ASSOC)) {
            $productionBeginningRows[] = $row;
        }
        sqlsrv_free_stmt($productionBeginningStmt);
    }
}

/* =========================================================
 * STOK AKHIR LOKASI PRODUKSI / NON-WHS (tags)
 * ========================================================= */
$productionClosingRows = array();

if (count($errors) == 0 && $queryError == '') {
    $productionClosingSql = "
        SELECT
            L.LOC_ID, L.LOC_CODE, L.LOC_NAME, L.LOC_GROUP,
            SUM(ISNULL(T.TAG_QTY, 0)) AS STOK_AKHIR_PRODUKSI
        FROM dbo.tags AS T
        INNER JOIN dbo.SOP AS S ON S.SOP_ID = T.SOP_ID
        INNER JOIN dbo.LOC AS L ON L.LOC_ID = T.LOC_ID
        WHERE T.ITEM_ID = ? AND L.LOC_CODE <> 'WHS' AND S.SOP_SDATE >= ? AND S.SOP_SDATE < DATEADD(day, 1, ?)
        GROUP BY L.LOC_ID, L.LOC_CODE, L.LOC_NAME, L.LOC_GROUP
        ORDER BY L.LOC_GROUP, L.LOC_CODE, L.LOC_NAME
    ";

    $productionClosingStmt = sqlsrv_query($conn, $productionClosingSql, array($itemId, $endDate, $endDate));

    if ($productionClosingStmt === false) {
        $queryError = sap_sql_error();
    } else {
        while ($row = sqlsrv_fetch_array($productionClosingStmt, SQLSRV_FETCH_ASSOC)) {
            $productionClosingRows[] = $row;
        }
        sqlsrv_free_stmt($productionClosingStmt);
    }
}

/* =========================================================
 * HITUNG WHS
 * ========================================================= */
$totalMasuk = 0;
$totalKeluar = 0;
$runningBalance = $saldoAwal;
$firstNegativeIndex = -1;

foreach ($whsRows as $index => $row) {
    $qty = isset($row['IT_QTY']) ? (float) $row['IT_QTY'] : 0;
    $inOut = isset($row['TRTY_INOUT']) ? (int) $row['TRTY_INOUT'] : 0;
    $sign = isset($row['TRTY_SIGN']) ? (float) $row['TRTY_SIGN'] : 0;

    $movement = $qty * $sign;
    $masuk = 0;
    $keluar = 0;

    if ($inOut == 1) {
        $masuk = $movement;
        $totalMasuk += $masuk;
        $runningBalance += $masuk;
    } elseif ($inOut == 2) {
        $keluar = $movement;
        $totalKeluar += $keluar;
        $runningBalance -= $keluar;
    }

    $whsRows[$index]['CALC_MASUK'] = $masuk;
    $whsRows[$index]['CALC_KELUAR'] = $keluar;
    $whsRows[$index]['CALC_EFFECT'] = $masuk - $keluar;
    $whsRows[$index]['RUNNING_BALANCE'] = $runningBalance;

    if ($firstNegativeIndex < 0 && $runningBalance < -0.000001) {
        $firstNegativeIndex = $index;
    }
}

$saldoAkhir = $saldoAwal + $totalMasuk - $totalKeluar;
$selisih = $stokOpname - $saldoAkhir;

/* =========================================================
 * RINGKASAN PRODUKSI PER LOKASI
 * ========================================================= */
$productionSummary = array();

// 1. Masukkan Stok Awal Produksi ke Summary
foreach ($productionBeginningRows as $begRow) {
    $locCode = isset($begRow['LOC_CODE']) ? trim($begRow['LOC_CODE']) : '';
    $locName = isset($begRow['LOC_NAME']) ? trim($begRow['LOC_NAME']) : '';
    $locGroup = isset($begRow['LOC_GROUP']) ? trim($begRow['LOC_GROUP']) : '';
    $locKey = trim($locGroup . ' | ' . $locCode . ' | ' . $locName);

    if (!isset($productionSummary[$locKey])) {
        $productionSummary[$locKey] = array('stok_awal' => 0, 'masuk' => 0, 'keluar' => 0, 'net' => 0, 'stok_akhir' => 0, 'has_opname' => false);
    }
    $productionSummary[$locKey]['stok_awal'] = isset($begRow['STOK_AWAL_PRODUKSI']) ? (float) $begRow['STOK_AWAL_PRODUKSI'] : 0;
}

// 2. Kalkulasi Transaksi Masuk/Keluar Produksi
foreach ($productionRows as $index => $row) {
    $locCode = isset($row['LOC_CODE']) ? trim($row['LOC_CODE']) : '';
    $locName = isset($row['LOC_NAME']) ? trim($row['LOC_NAME']) : '';
    $locGroup = isset($row['LOC_GROUP']) ? trim($row['LOC_GROUP']) : '';
    $locKey = trim($locGroup . ' | ' . $locCode . ' | ' . $locName);

    if (!isset($productionSummary[$locKey])) {
        $productionSummary[$locKey] = array('stok_awal' => 0, 'masuk' => 0, 'keluar' => 0, 'net' => 0, 'stok_akhir' => 0, 'has_opname' => false);
    }

    $qty = isset($row['IT_QTY']) ? (float) $row['IT_QTY'] : 0;
    $sign = isset($row['TRTY_SIGN']) ? (float) $row['TRTY_SIGN'] : 0;
    $inOut = isset($row['TRTY_INOUT']) ? (int) $row['TRTY_INOUT'] : 0;

    $movement = $qty * $sign;
    $masuk = 0;
    $keluar = 0;

    if ($inOut == 1) {
        $masuk = $movement;
        $productionSummary[$locKey]['masuk'] += $masuk;
    } elseif ($inOut == 2) {
        $keluar = $movement;
        $productionSummary[$locKey]['keluar'] += $keluar;
    }

    $productionSummary[$locKey]['net'] = $productionSummary[$locKey]['masuk'] - $productionSummary[$locKey]['keluar'];
    $productionRows[$index]['CALC_MASUK'] = $masuk;
    $productionRows[$index]['CALC_KELUAR'] = $keluar;
    $productionRows[$index]['CALC_EFFECT'] = $masuk - $keluar;
}

// 3. Masukkan Stok Akhir (Opname) Produksi
foreach ($productionClosingRows as $closingRow) {
    $locCode = isset($closingRow['LOC_CODE']) ? trim($closingRow['LOC_CODE']) : '';
    $locName = isset($closingRow['LOC_NAME']) ? trim($closingRow['LOC_NAME']) : '';
    $locGroup = isset($closingRow['LOC_GROUP']) ? trim($closingRow['LOC_GROUP']) : '';
    $locKey = trim($locGroup . ' | ' . $locCode . ' | ' . $locName);

    if (!isset($productionSummary[$locKey])) {
        $productionSummary[$locKey] = array('stok_awal' => 0, 'masuk' => 0, 'keluar' => 0, 'net' => 0, 'stok_akhir' => 0, 'has_opname' => false);
    }

    $productionSummary[$locKey]['stok_akhir'] = isset($closingRow['STOK_AKHIR_PRODUKSI']) ? (float) $closingRow['STOK_AKHIR_PRODUKSI'] : 0;
    $productionSummary[$locKey]['has_opname'] = true;
}

/*
 * Total referensi produksi.
 */
$totalProductionStokAwal = 0;
$totalProductionMasuk = 0;
$totalProductionKeluar = 0;
$totalProductionNet = 0;
$totalProductionClosing = 0;
$totalProductionOpnameLocations = 0;

foreach ($productionSummary as $locSummary) {
    $totalProductionStokAwal += isset($locSummary['stok_awal']) ? (float) $locSummary['stok_awal'] : 0;
    $totalProductionMasuk += isset($locSummary['masuk']) ? (float) $locSummary['masuk'] : 0;
    $totalProductionKeluar += isset($locSummary['keluar']) ? (float) $locSummary['keluar'] : 0;
    $totalProductionNet += isset($locSummary['net']) ? (float) $locSummary['net'] : 0;

    if (!empty($locSummary['has_opname'])) {
        $totalProductionClosing += isset($locSummary['stok_akhir']) ? (float) $locSummary['stok_akhir'] : 0;
        $totalProductionOpnameLocations++;
    }
}

$backUrl = $backPage . "?" . http_build_query(
    array('tglawal' => $startDate, 'tglakhir' => $endDate, 'cari' => $itemCode)
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo sap_h($reportTitle); ?></title>
    <style>
        body { background: #e3e3e3; font-family: Arial, Helvetica, sans-serif; font-size: 12px; margin: 0; }
        .toolbar { background: #ffffff; border-bottom: 1px solid #cccccc; padding: 12px; text-align: center; }
        .btn { background: #f8f8f8; border: 1px solid #aaaaaa; color: #111111; cursor: pointer; display: inline-block; margin: 0 4px; padding: 7px 14px; text-decoration: none; }
        .page { background: #ffffff; box-shadow: 0 0 8px rgba(0,0,0,0.12); box-sizing: border-box; margin: 18px auto; min-height: 210mm; padding: 20px; width: 297mm; }
        .header-table { margin-bottom: 12px; width: 100%; }
        .header-table td { vertical-align: top; }
        .title { text-align: center; }
        .title h1 { font-size: 21px; font-weight: normal; margin: 0 0 4px; }
        .right { text-align: right; }
        .section-title { background: #edf3f7; border: 1px solid #a9b7c2; font-weight: bold; margin-top: 12px; padding: 6px; }
        .summary-table, .detail-table { border-collapse: collapse; width: 100%; }
        .summary-table { margin-bottom: 10px; }
        .summary-table th, .summary-table td, .detail-table th, .detail-table td { border: 1px solid #777777; padding: 4px; }
        .summary-table th, .detail-table th { background: #f2f2f2; font-size: 10px; text-align: center; }
        .summary-table td { font-size: 13px; text-align: right; }
        .detail-table td { font-size: 9px; vertical-align: top; }
        .formula { background: #eef7ff; border-left: 4px solid #337ab7; font-family: Consolas, monospace; line-height: 1.6; margin-bottom: 9px; padding: 8px 10px; }
        .note { background: #fff8e5; border-left: 4px solid #f0ad4e; margin-bottom: 9px; padding: 8px 10px; }
        .danger { background: #fff0f0; border-left: 4px solid #d9534f; margin-bottom: 9px; padding: 8px 10px; }
        .number { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .in { color: #138a36; }
        .out { color: #c82333; }
        .negative { color: #c00000; font-weight: bold; }
        .negative-row { background: #ffe5e5; }
        .first-negative { border: 3px solid #d9534f !important; }
        .begin-row { background: #eef7ff; color: #0056b3; font-style: italic; }
        .production-row { background: #fffdf1; }
        .total-row td { border-top: 2px solid #000000; font-weight: bold; }
        @media print {
            @page { margin: 8mm; size: A3 landscape; }
            body { background: #ffffff; }
            .toolbar { display: none; }
            .page { box-shadow: none; margin: 0; min-height: 0; padding: 0; width: 100%; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="<?php echo sap_h($backUrl); ?>" class="btn">Kembali</a>
    <button class="btn" onclick="window.print();">Print Report</button>
</div>

<?php if (count($errors) > 0) { ?>
    <div class="danger" style="margin:20px;">
        <?php echo sap_h(implode("\n", $errors)); ?>
    </div>
<?php } ?>

<?php if ($queryError != '') { ?>
    <div class="danger" style="margin:20px;">
        <strong>Query gagal:</strong>
        <pre><?php echo sap_h($queryError); ?></pre>
    </div>
<?php } ?>

<?php if (count($errors) == 0 && $queryError == '') { ?>

<div class="page">
    <table class="header-table">
        <tr>
            <td style="width:33%;">P.T. IMC TEKNO INDONESIA</td>
            <td style="width:34%;" class="title">
                <h1><?php echo sap_h($reportTitle); ?></h1>
                <div>
                    <?php echo sap_h($itemCode); ?> - <?php echo sap_h($itemName); ?> (<?php echo sap_h($itemUnit); ?>)
                </div>
                <div>
                    Stok awal: <?php echo sap_h(sap_date($startDate, 'd-M-y')); ?> | Stok akhir: <?php echo sap_h(sap_date($endDate, 'd-M-y')); ?>
                </div>
                <div>
                    Transaksi: <?php echo sap_h(sap_date($startDate, 'd-M-y')); ?> s/d <?php echo sap_h(sap_date($transactionEndDate, 'd-M-y')); ?>
                </div>
            </td>
            <td style="width:33%;" class="right">
                <div>Hitungan utama: WHS</div>
                <div>Referensi: lokasi non-WHS/produksi</div>
                <div><?php echo date('d/m/Y H:i:s'); ?></div>
            </td>
        </tr>
    </table>

    <div class="section-title">REKONSILIASI WHS</div>

    <table class="summary-table">
        <tr>
            <th>BEGINNING BALANCE<br><?php echo sap_h(sap_date($startDate, 'd-M-y')); ?></th>
            <th>PEMASUKAN WHS<br><?php echo sap_h(sap_date($startDate, 'd-M-y')); ?> - <?php echo sap_h(sap_date($transactionEndDate, 'd-M-y')); ?></th>
            <th>PENGELUARAN WHS<br><?php echo sap_h(sap_date($startDate, 'd-M-y')); ?> - <?php echo sap_h(sap_date($transactionEndDate, 'd-M-y')); ?></th>
            <th>SALDO AKHIR SISTEM<br><?php echo sap_h(sap_date($endDate, 'd-M-y')); ?></th>
            <th>STOK OPNAME<br><?php echo sap_h(sap_date($endDate, 'd-M-y')); ?></th>
            <th>SELISIH</th>
        </tr>
        <tr>
            <td><?php echo sap_h(sap_number($saldoAwal)); ?></td>
            <td class="in"><?php echo sap_h(sap_number($totalMasuk)); ?></td>
            <td class="out"><?php echo sap_h(sap_number($totalKeluar)); ?></td>
            <td><?php echo sap_h(sap_number($saldoAkhir)); ?></td>
            <td><?php echo sap_h(sap_number($stokOpname)); ?></td>
            <td class="<?php echo (abs($selisih) > 0.000001) ? 'negative' : ''; ?>">
                <?php echo sap_h(sap_number($selisih)); ?>
            </td>
        </tr>
    </table>

    <div class="formula">
        SALDO AKHIR = <?php echo sap_h(sap_number($saldoAwal)); ?> + <?php echo sap_h(sap_number($totalMasuk)); ?> - <?php echo sap_h(sap_number($totalKeluar)); ?> = <strong><?php echo sap_h(sap_number($saldoAkhir)); ?></strong><br>
        SELISIH = <?php echo sap_h(sap_number($stokOpname)); ?> - <?php echo sap_h(sap_number($saldoAkhir)); ?> = <strong><?php echo sap_h(sap_number($selisih)); ?></strong>
    </div>

    <div class="note">
        Transaksi tanggal <strong><?php echo sap_h(sap_date($endDate, 'd-M-y')); ?></strong> tidak masuk mutasi periode. Tanggal tersebut hanya digunakan sebagai tanggal stok akhir/opname.
    </div>

    <?php if ($selisih < -0.000001) { ?>
        <div class="danger">
            Stok opname lebih kecil daripada saldo sistem sebesar <strong><?php echo sap_h(sap_number(abs($selisih))); ?></strong>.
        </div>
    <?php } elseif ($selisih > 0.000001) { ?>
        <div class="danger">
            Stok opname lebih besar daripada saldo sistem sebesar <strong><?php echo sap_h(sap_number($selisih)); ?></strong>.
        </div>
    <?php } ?>

    <div class="section-title">DETAIL TRANSAKSI WHS</div>
    <div class="note">Baris dengan tanggal, TRAN ID, dokumen, TRTY, lokasi, arah, dan sign yang sama sudah digabung. QTY, MASUK, dan KELUAR ditampilkan sebagai hasil penjumlahan.</div>

    <table class="detail-table">
        <thead>
            <tr>
                <th>NO</th>
                <th>TANGGAL</th>
                <th>TRAN ID</th>
                <th>DOKUMEN</th>
                <th>TRTY</th>
                <th>KETERANGAN</th>
                <th>SIGN</th>
                <th>QTY</th>
                <th>MASUK</th>
                <th>KELUAR</th>
                <th>EFEK</th>
                <th>SALDO BERJALAN</th>
            </tr>
        </thead>
        <tbody>
            <tr class="begin-row">
                <td class="center">0</td>
                <td colspan="10">BEGINNING BALANCE <?php echo sap_h(sap_date($startDate, 'd-M-y')); ?></td>
                <td class="number"><?php echo sap_h(sap_number($saldoAwal)); ?></td>
            </tr>
            <?php foreach ($whsRows as $index => $row) { 
                $balance = isset($row['RUNNING_BALANCE']) ? (float) $row['RUNNING_BALANCE'] : 0;
                $isNegative = $balance < -0.000001;
                $isFirstNegative = $index === $firstNegativeIndex;
                $document = trim((isset($row['TRAN_DOC']) ? $row['TRAN_DOC'] : '') . ' ' . (isset($row['TRAN_DOC2']) ? $row['TRAN_DOC2'] : ''));
            ?>
                <tr class="<?php echo $isNegative ? 'negative-row' : ''; ?>">
                    <td class="center"><?php echo $index + 1; ?></td>
                    <td class="center"><?php echo sap_h(sap_date($row['TRAN_DATE'], 'd-M-y H:i')); ?></td>
                    <td class="center"><?php echo sap_h($row['TRAN_ID']); ?></td>
                    <td><?php echo sap_h($document); ?></td>
                    <td class="center"><?php echo sap_h($row['TRTY_CODE']); ?></td>
                    <td>
                        <?php echo sap_h($row['TRTY_DESC']); ?>
                        <?php if ($isFirstNegative) { ?><strong class="negative">← pertama kali saldo minus</strong><?php } ?>
                    </td>
                    <td class="number"><?php echo sap_h(sap_number($row['TRTY_SIGN'])); ?></td>
                    <td class="number"><?php echo sap_h(sap_number($row['IT_QTY'])); ?></td>
                    <td class="number in"><?php echo sap_h(sap_number($row['CALC_MASUK'])); ?></td>
                    <td class="number out"><?php echo sap_h(sap_number($row['CALC_KELUAR'])); ?></td>
                    <td class="number"><?php echo sap_h(sap_number($row['CALC_EFFECT'])); ?></td>
                    <td class="number <?php echo $isNegative ? 'negative' : ''; ?> <?php echo $isFirstNegative ? 'first-negative' : ''; ?>">
                        <?php echo sap_h(sap_number($balance)); ?>
                    </td>
                </tr>
            <?php } ?>
            <?php if (count($whsRows) == 0) { ?>
                <tr><td colspan="12" class="center">Tidak ada transaksi WHS pada periode ini.</td></tr>
            <?php } ?>
            <tr class="total-row">
                <td colspan="8" class="right">TOTAL WHS</td>
                <td class="number in"><?php echo sap_h(sap_number($totalMasuk)); ?></td>
                <td class="number out"><?php echo sap_h(sap_number($totalKeluar)); ?></td>
                <td class="number"><?php echo sap_h(sap_number($totalMasuk - $totalKeluar)); ?></td>
                <td class="number"><?php echo sap_h(sap_number($saldoAkhir)); ?></td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">REFERENSI LOKASI PRODUKSI / NON-WHS</div>
    <div class="note">
        Bagian ini hanya sebagai acuan perpindahan atau aktivitas lokasi produksi. Angka stok awal, masuk, keluar, dan stok akhir produksi tidak dimasukkan ke saldo WHS. Stok akhir diambil dari tags/SOP tepat pada tanggal <strong><?php echo sap_h(sap_date($endDate, 'd-M-y')); ?></strong>.
    </div>

    <table class="summary-table">
        <thead>
            <tr>
                <th>LOKASI</th>
                <th>STOK AWAL<br><?php echo sap_h(sap_date($startDate, 'd-M-y')); ?></th>
                <th>MASUK</th>
                <th>KELUAR</th>
                <th>NET MUTASI</th>
                <th>STOK AKHIR PRODUKSI<br><?php echo sap_h(sap_date($endDate, 'd-M-y')); ?></th>
                <th>STATUS OPNAME</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productionSummary as $locKey => $locSummary) { ?>
                <tr>
                    <td style="text-align:left;"><?php echo sap_h($locKey); ?></td>
                    <td class="number"><?php echo sap_h(sap_number($locSummary['stok_awal'])); ?></td>
                    <td class="in"><?php echo sap_h(sap_number($locSummary['masuk'])); ?></td>
                    <td class="out"><?php echo sap_h(sap_number($locSummary['keluar'])); ?></td>
                    <td><?php echo sap_h(sap_number($locSummary['net'])); ?></td>
                    <td>
                        <?php if (!empty($locSummary['has_opname'])) { 
                            echo sap_h(sap_number($locSummary['stok_akhir'])); 
                        } else { echo '-'; } ?>
                    </td>
                    <td class="center">
                        <?php if (!empty($locSummary['has_opname'])) { ?>
                            <strong class="in">OPNAME</strong>
                        <?php } else { ?>
                            <strong class="out">BELUM OPNAME</strong>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>

            <?php if (count($productionSummary) == 0) { ?>
                <tr><td colspan="7" class="center">Tidak ada transaksi maupun stok opname lokasi non-WHS.</td></tr>
            <?php } else { ?>
                <tr class="total-row">
                    <td style="text-align:right;">TOTAL REFERENSI PRODUKSI</td>
                    <td class="number"><?php echo sap_h(sap_number($totalProductionStokAwal)); ?></td>
                    <td class="in"><?php echo sap_h(sap_number($totalProductionMasuk)); ?></td>
                    <td class="out"><?php echo sap_h(sap_number($totalProductionKeluar)); ?></td>
                    <td><?php echo sap_h(sap_number($totalProductionNet)); ?></td>
                    <td><?php echo sap_h(sap_number($totalProductionClosing)); ?></td>
                    <td class="center"><?php echo (int) $totalProductionOpnameLocations; ?> lokasi opname</td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <table class="detail-table">
        <thead>
            <tr>
                <th>NO</th>
                <th>LOKASI</th>
                <th>TANGGAL</th>
                <th>TRAN ID</th>
                <th>DOKUMEN</th>
                <th>TRTY</th>
                <th>KETERANGAN</th>
                <th>SIGN</th>
                <th>QTY</th>
                <th>MASUK</th>
                <th>KELUAR</th>
                <th>EFEK</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productionRows as $index => $row) { 
                $locationText = trim((isset($row['LOC_GROUP']) ? $row['LOC_GROUP'] : '') . ' | ' . (isset($row['LOC_CODE']) ? $row['LOC_CODE'] : '') . ' | ' . (isset($row['LOC_NAME']) ? $row['LOC_NAME'] : ''));
                $document = trim((isset($row['TRAN_DOC']) ? $row['TRAN_DOC'] : '') . ' ' . (isset($row['TRAN_DOC2']) ? $row['TRAN_DOC2'] : ''));
            ?>
                <tr class="production-row">
                    <td class="center"><?php echo $index + 1; ?></td>
                    <td><?php echo sap_h($locationText); ?></td>
                    <td class="center"><?php echo sap_h(sap_date($row['TRAN_DATE'], 'd-M-y H:i')); ?></td>
                    <td class="center"><?php echo sap_h($row['TRAN_ID']); ?></td>
                    <td><?php echo sap_h($document); ?></td>
                    <td class="center"><?php echo sap_h($row['TRTY_CODE']); ?></td>
                    <td><?php echo sap_h($row['TRTY_DESC']); ?></td>
                    <td class="number"><?php echo sap_h(sap_number($row['TRTY_SIGN'])); ?></td>
                    <td class="number"><?php echo sap_h(sap_number($row['IT_QTY'])); ?></td>
                    <td class="number in"><?php echo sap_h(sap_number($row['CALC_MASUK'])); ?></td>
                    <td class="number out"><?php echo sap_h(sap_number($row['CALC_KELUAR'])); ?></td>
                    <td class="number"><?php echo sap_h(sap_number($row['CALC_EFFECT'])); ?></td>
                </tr>
            <?php } ?>
            <?php if (count($productionRows) == 0) { ?>
                <tr><td colspan="12" class="center">Tidak ada detail transaksi lokasi produksi/non-WHS.</td></tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php } ?>

</body>
</html>