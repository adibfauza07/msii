<?php
// report_kapasitas_mesin.php
require_once __DIR__ . "/../config/global.php"; 

if (session_status() == PHP_SESSION_NONE) { session_start(); }
error_reporting(0);
ini_set('display_errors', 0);

// =====================================================================
// KONEKSI DATABASE
// =====================================================================
$databaseName = "msData";
$serverName = isset($_SESSION['active_server']) ? $_SESSION['active_server'] : "192.168.0.9";
$uid = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : "";
$pwd = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";

$connectionOptions = array("Database" => $databaseName, "CharacterSet" => "UTF-8");
if ($uid !== "") { 
    $connectionOptions["Uid"] = $uid; 
    $connectionOptions["PWD"] = $pwd; 
}
$conn = @sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die("<div style='color:red; padding:20px; font-weight:bold;'>Sistem Gagal Terhubung ke Database.</div>");
}

// =====================================================================
// PARAMETER FILTER PERIODE & VALIDASI SUBMIT
// =====================================================================
$currentYear = (int)date('Y');
$currentMonth = (int)date('n');

$isSubmitted = (isset($_GET['tahun']) && isset($_GET['bulan']));

$filterYear = $isSubmitted ? (int)$_GET['tahun'] : $currentYear;
$filterMonth = $isSubmitted ? (int)$_GET['bulan'] : $currentMonth;
$periode = sprintf('%04d%02d', $filterYear, $filterMonth);

$daysInMonth = date('t', mktime(0, 0, 0, $filterMonth, 1, $filterYear));

// Array Utama untuk menyimpan data yang sudah di-group
$groupedData = array();

// =====================================================================
// QUERY: AGREGASI KAPASITAS MESIN (PLAN vs ACTUAL) BERDASARKAN ITEM
// =====================================================================
if ($isSubmitted) {
    // T-SQL Menggunakan Group By MC_NO dan ITEM_CODE
    $sql = "WITH Rekap_Transaksi AS (
                SELECT 
                    P.MC_NO,
                    P.ITEM_CODE,
                    MAX(P.PART_NAME) AS PART_NAME,
                    
                    -- DATA PLAN (Prod Plan R0)
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D1,0) ELSE 0 END) AS p1,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D2,0) ELSE 0 END) AS p2,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D3,0) ELSE 0 END) AS p3,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D4,0) ELSE 0 END) AS p4,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D5,0) ELSE 0 END) AS p5,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D6,0) ELSE 0 END) AS p6,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D7,0) ELSE 0 END) AS p7,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D8,0) ELSE 0 END) AS p8,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D9,0) ELSE 0 END) AS p9,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D10,0) ELSE 0 END) AS p10,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D11,0) ELSE 0 END) AS p11,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D12,0) ELSE 0 END) AS p12,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D13,0) ELSE 0 END) AS p13,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D14,0) ELSE 0 END) AS p14,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D15,0) ELSE 0 END) AS p15,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D16,0) ELSE 0 END) AS p16,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D17,0) ELSE 0 END) AS p17,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D18,0) ELSE 0 END) AS p18,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D19,0) ELSE 0 END) AS p19,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D20,0) ELSE 0 END) AS p20,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D21,0) ELSE 0 END) AS p21,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D22,0) ELSE 0 END) AS p22,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D23,0) ELSE 0 END) AS p23,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D24,0) ELSE 0 END) AS p24,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D25,0) ELSE 0 END) AS p25,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D26,0) ELSE 0 END) AS p26,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D27,0) ELSE 0 END) AS p27,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D28,0) ELSE 0 END) AS p28,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D29,0) ELSE 0 END) AS p29,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D30,0) ELSE 0 END) AS p30,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod Plan R0') THEN ISNULL(D.D31,0) ELSE 0 END) AS p31,

                    -- DATA ACTUAL (Prod OK, Prod NG, Prod HOLD)
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D1,0) ELSE 0 END) AS a1,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D2,0) ELSE 0 END) AS a2,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D3,0) ELSE 0 END) AS a3,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D4,0) ELSE 0 END) AS a4,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D5,0) ELSE 0 END) AS a5,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D6,0) ELSE 0 END) AS a6,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D7,0) ELSE 0 END) AS a7,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D8,0) ELSE 0 END) AS a8,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D9,0) ELSE 0 END) AS a9,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D10,0) ELSE 0 END) AS a10,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D11,0) ELSE 0 END) AS a11,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D12,0) ELSE 0 END) AS a12,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D13,0) ELSE 0 END) AS a13,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D14,0) ELSE 0 END) AS a14,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D15,0) ELSE 0 END) AS a15,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D16,0) ELSE 0 END) AS a16,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D17,0) ELSE 0 END) AS a17,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D18,0) ELSE 0 END) AS a18,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D19,0) ELSE 0 END) AS a19,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D20,0) ELSE 0 END) AS a20,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D21,0) ELSE 0 END) AS a21,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D22,0) ELSE 0 END) AS a22,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D23,0) ELSE 0 END) AS a23,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D24,0) ELSE 0 END) AS a24,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D25,0) ELSE 0 END) AS a25,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D26,0) ELSE 0 END) AS a26,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D27,0) ELSE 0 END) AS a27,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D28,0) ELSE 0 END) AS a28,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D29,0) ELSE 0 END) AS a29,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D30,0) ELSE 0 END) AS a30,
                    SUM(CASE WHEN D.DESC_PROD IN ('Prod OK', 'Prod NG', 'Prod HOLD') THEN ISNULL(D.D31,0) ELSE 0 END) AS a31

                FROM dbo.RPT_PPIC P
                INNER JOIN dbo.RPT_PPIC_DTL D ON P.ID_NO = D.ID_NO 
                WHERE P.periode = ? 
                  AND P.MC_NO IS NOT NULL
                  AND P.MC_NO <> ''
                  AND D.DESC_PROD IN ('Prod Plan R0', 'Prod OK', 'Prod NG', 'Prod HOLD')
                GROUP BY P.MC_NO, P.ITEM_CODE
            )
            SELECT 
                ISNULL(G.MAG_STATION, 'TANPA GROUP') AS MAG_STATION, 
                R.MC_NO AS MAC_CODE,
                R.ITEM_CODE,
                ISNULL(R.PART_NAME, '') AS PART_NAME,
                R.p1, R.p2, R.p3, R.p4, R.p5, R.p6, R.p7, R.p8, R.p9, R.p10, 
                R.p11, R.p12, R.p13, R.p14, R.p15, R.p16, R.p17, R.p18, R.p19, R.p20, 
                R.p21, R.p22, R.p23, R.p24, R.p25, R.p26, R.p27, R.p28, R.p29, R.p30, R.p31,
                R.a1, R.a2, R.a3, R.a4, R.a5, R.a6, R.a7, R.a8, R.a9, R.a10, 
                R.a11, R.a12, R.a13, R.a14, R.a15, R.a16, R.a17, R.a18, R.a19, R.a20, 
                R.a21, R.a22, R.a23, R.a24, R.a25, R.a26, R.a27, R.a28, R.a29, R.a30, R.a31
            FROM Rekap_Transaksi R
            LEFT JOIN dbo.MAC M ON LTRIM(RTRIM(R.MC_NO)) = LTRIM(RTRIM(M.MAC_CODE))
            LEFT JOIN dbo.MAG G ON M.MAG_ID = G.MAG_ID
            ORDER BY G.MAG_STATION ASC, R.MC_NO ASC, R.ITEM_CODE ASC";

    $stmt = sqlsrv_query($conn, $sql, array($periode));
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $grp = trim($row['MAG_STATION']);
            $mac = trim($row['MAC_CODE']);
            $itemCode = trim($row['ITEM_CODE']);
            $itemName = trim($row['PART_NAME']);

            $itemPlanDays = 0;
            $itemActDays = 0;
            $pData = array();
            $aData = array();

            // Hitung terlebih dahulu data per hari untuk 1 item ini
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $p = (float)$row["p$i"];
                $a = (float)$row["a$i"];

                $pData[$i] = $p;
                $aData[$i] = $a;

                // Hitung total days per item
                if ($p > 0) $itemPlanDays++;
                if ($a > 0) $itemActDays++;
            }

            // FILTER: Hanya proses dan tambahkan ke array jika ada PLAN (Schedule)
            if ($itemPlanDays > 0) {
                
                // Baru inisialisasi group dan mesin jika ada isinya
                if (!isset($groupedData[$grp])) {
                    $groupedData[$grp] = array();
                }
                if (!isset($groupedData[$grp][$mac])) {
                    $groupedData[$grp][$mac] = array(
                        'items'      => array(),
                        'daily_plan' => array_fill(1, $daysInMonth, 0),
                        'daily_act'  => array_fill(1, $daysInMonth, 0)
                    );
                }

                // Akumulasi array harian ke level mesin
                for ($i = 1; $i <= $daysInMonth; $i++) {
                    $groupedData[$grp][$mac]['daily_plan'][$i] += $pData[$i];
                    $groupedData[$grp][$mac]['daily_act'][$i] += $aData[$i];
                }

                $groupedData[$grp][$mac]['items'][] = array(
                    'item_code'  => $itemCode,
                    'item_name'  => $itemName,
                    'p'          => $pData,
                    'a'          => $aData,
                    'plan_days'  => $itemPlanDays,
                    'act_days'   => $itemActDays
                );
            }
        }
        sqlsrv_free_stmt($stmt);
        
        // Kalkulasi Total Days per Mesin setelah semua item terkumpul
        foreach ($groupedData as $grp => $macs) {
            foreach ($macs as $mac => $macData) {
                $macPlanDays = 0;
                $macActDays = 0;
                for ($i = 1; $i <= $daysInMonth; $i++) {
                    if ($macData['daily_plan'][$i] > 0) $macPlanDays++;
                    if ($macData['daily_act'][$i] > 0) $macActDays++;
                }
                $groupedData[$grp][$mac]['machine_plan_days'] = $macPlanDays;
                $groupedData[$grp][$mac]['machine_act_days']  = $macActDays;
            }
        }
    }
}

sqlsrv_close($conn);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Matrix Kapasitas Mesin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .panel { background-color: #fff; border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow-x: auto; }
        .panel-title { font-weight: bold; color: #0056b3; margin-bottom: 15px; border-bottom: 2px solid #0056b3; padding-bottom: 5px; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
        
        .form-group { display: inline-block; margin-right: 15px; }
        label { font-weight: bold; color: #444; margin-right: 5px; }
        select { padding: 4px; border: 1px solid #ccc; font-size: 12px; height: 26px; }
        button { background-color: #0056b3; color: #fff; border: none; padding: 5px 15px; height: 26px; font-weight: bold; cursor: pointer; border-radius: 3px; }
        button:hover { background-color: #004494; }

        table.report-table { width: 100%; border-collapse: collapse; background-color: #fff; border: 1px solid #bbb; margin-top: 10px; min-width: 1200px; }
        table.report-table th, table.report-table td { padding: 4px 6px; border: 1px solid #ddd; text-align: left; }
        table.report-table th { background-color: #eaeaea; font-weight: bold; text-align: center; position: sticky; top: 0; z-index: 10; }
        table.report-table td.center { text-align: center; }
        
        /* Baris Group Header */
        .group-header td { background-color: #dbeafe !important; color: #004494; font-weight: bold; padding: 8px; font-size: 13px; }
        
        /* Baris Sub & Grand Total */
        .row-subtotal td { background-color: #f1f5f9 !important; font-weight: bold; color: #334155; }
        .row-grandtotal td { background-color: #cbd5e1 !important; font-weight: bold; color: #0f172a; font-size: 13px; border-top: 2px solid #64748b; }
        
        /* Cell Matrix */
        .cell-empty { background-color: #dc3545; color: white; text-align: center; font-size: 10px; }
        .cell-filled { background-color: #28a745; color: white; text-align: center; font-size: 10px; }
        .cell-actual { background-color: #0d6efd; color: white; text-align: center; font-size: 10px; } 
        
        .legend { font-size: 11px; margin-top: 10px; }
        .legend-box { display: inline-block; width: 12px; height: 12px; margin-right: 5px; vertical-align: middle; }
        .bg-empty { background-color: #dc3545; }
        .bg-filled { background-color: #28a745; }
        .bg-actual { background-color: #0d6efd; }

        @media print {
            /* PENYESUAIAN A4 LANDSCAPE FIT */
            @page { size: A4 landscape; margin: 5mm; }
            body { padding: 0; background-color: #fff; -webkit-print-color-adjust: exact; zoom: 85%; }
            .panel { border: none; box-shadow: none; padding: 0; margin: 0; overflow-x: visible; }
            form, .no-print { display: none; }
            
            table.report-table { 
                min-width: 100% !important; 
                width: 100% !important; 
                table-layout: fixed; 
                font-size: 8px !important; 
                border-collapse: collapse !important;
            }
            
            table.report-table th, table.report-table td { 
                padding: 2px !important; 
                font-size: 8px !important; 
                border: 1px solid #000 !important; /* Memastikan garis border ikut tercetak hitam */
                word-wrap: break-word;
            }
            
            /* Penyesuaian Lebar Kolom saat Print */
            table.report-table th:nth-child(1) { width: 70px !important; }
            table.report-table th:nth-child(2) { width: 130px !important; }
            table.report-table th:nth-child(3) { width: 35px !important; }
            table.report-table th:nth-child(4) { width: 35px !important; }
            table.report-table th:nth-child(n+5) { width: auto !important; } /* Kolom Tgl otomatis menyesuaikan */

            /* Background di-transparent-kan, warna teks diubah hitam agar jelas */
            .cell-empty { background-color: transparent !important; }
            .cell-filled { background-color: transparent !important; color: #000 !important; font-size: 9px !important; font-weight: bold; }
            .cell-actual { background-color: transparent !important; color: #000 !important; font-size: 9px !important; font-weight: bold; }
            
            .group-header td { background-color: #f8f9fa !important; color: #000 !important; font-size: 10px !important; }
            .row-subtotal td, .row-grandtotal td { background-color: #f1f5f9 !important; color: #000 !important; }
        }
    </style>
</head>
<body>

    <div class="panel no-print">
        <div class="panel-title"><i class="fa fa-bar-chart"></i> Filter Kapasitas Mesin (Plan vs Actual)</div>
        <form method="GET" action="">
            <div class="form-group">
                <label>Tahun:</label>
                <select name="tahun">
                    <?php for ($y = $currentYear - 1; $y <= $currentYear + 2; $y++) {
                        $sel = ($y === $filterYear) ? 'selected' : '';
                        echo "<option value=\"$y\" $sel>$y</option>";
                    } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Bulan:</label>
                <select name="bulan">
                    <?php for ($m = 1; $m <= 12; $m++) {
                        $sel = ($m === $filterMonth) ? 'selected' : '';
                        echo "<option value=\"$m\" $sel>" . date("F", mktime(0, 0, 0, $m, 1)) . " ($m)</option>";
                    } ?>
                </select>
            </div>
            <button type="submit"><i class="fa fa-search"></i> Tampilkan Matrix</button>
            <button type="button" onclick="window.print();" style="background-color:#6c757d; margin-left:10px;"><i class="fa fa-print"></i> Print</button>
        </form>
    </div>

    <div class="panel">
        <div class="panel-title">
            <span>Matrix Detail Utilisasi Mesin <?= $isSubmitted ? "- Periode $periode" : "" ?></span>
        </div>
        
        <div class="legend no-print">
            <strong>Keterangan:</strong> 
            <span class="legend-box bg-filled" style="margin-left: 10px;"></span> Plan Terjadwal
            <span class="legend-box bg-actual" style="margin-left: 10px;"></span> Actual Terlaksana
            <span class="legend-box bg-empty" style="margin-left: 10px;"></span> Kosong (Tersedia / Not Running)
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 120px; text-align: left; padding-left: 10px;">Machine Code</th>
                    <th style="width: 180px; text-align: left;">Item Details</th>
                    <th style="width: 50px;">Kategori</th>
                    <th style="width: 60px;">Total Days</th>
                    <?php 
                        $cols = $isSubmitted ? $daysInMonth : date('t');
                        for ($i = 1; $i <= $cols; $i++): 
                    ?>
                        <th style="width: 20px;"><?= $i ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!$isSubmitted): ?>
                    <tr>
                        <td colspan="<?= $cols + 4 ?>" class="center" style="padding: 40px; color: #555; font-size: 14px;">
                            Silakan pilih <b>Tahun</b> dan <b>Bulan</b> pada filter di atas, lalu klik tombol <b>"Tampilkan Matrix"</b>.
                        </td>
                    </tr>
                <?php elseif (empty($groupedData)): ?>
                    <tr>
                        <td colspan="<?= $cols + 4 ?>" class="center" style="padding: 20px;">
                            Data mesin tidak ditemukan pada periode ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                        $grandTotalMachinePlan = array_fill(1, $daysInMonth, 0);
                        $grandTotalAssemblingPlan = array_fill(1, $daysInMonth, 0);

                        foreach ($groupedData as $grp => $macs): 
                            $isAssembling = (strpos(strtoupper($grp), 'ASS') !== false);
                            $subTotalPlan = array_fill(1, $daysInMonth, 0);
                            $subTotalAct  = array_fill(1, $daysInMonth, 0);
                    ?>
                        <!-- Header Group -->
                        <tr class='group-header'>
                            <td colspan='<?= $daysInMonth + 4 ?>'><i class='fa fa-cubes'></i> Mach Group : <?= htmlspecialchars($grp) ?></td>
                        </tr>

                        <?php 
                            foreach ($macs as $mac => $macData): 
                                $itemsCount = count($macData['items']);
                                $rowspanMachine = $itemsCount * 2; // Tiap item butuh 2 baris (Plan & Act)
                                
                                // Kalkulasi subtotal harian per grup
                                $isAssItem = (strpos(strtoupper($grp), 'ASS') !== false || strtoupper(substr(trim($mac), 0, 1)) === 'A');
                                for ($i = 1; $i <= $daysInMonth; $i++) {
                                    if ($macData['daily_plan'][$i] > 0) {
                                        $subTotalPlan[$i]++;
                                        if ($isAssItem) $grandTotalAssemblingPlan[$i]++; else $grandTotalMachinePlan[$i]++;
                                    }
                                    if ($macData['daily_act'][$i] > 0) {
                                        $subTotalAct[$i]++;
                                    }
                                }
                                
                                // Kalkulasi Persentase Level Mesin
                                $macPlanPct = ($daysInMonth > 0) ? round(($macData['machine_plan_days'] / $daysInMonth) * 100, 1) : 0;
                                $macActPct  = ($daysInMonth > 0) ? round(($macData['machine_act_days'] / $daysInMonth) * 100, 1) : 0;

                                foreach ($macData['items'] as $index => $item):
                                
                                // Kalkulasi Persentase Level Item
                                $itemPlanPct = ($daysInMonth > 0) ? round(($item['plan_days'] / $daysInMonth) * 100, 1) : 0;
                                $itemActPct  = ($daysInMonth > 0) ? round(($item['act_days'] / $daysInMonth) * 100, 1) : 0;
                        ?>
                                <!-- Baris Plan (Item) -->
                                <tr>
                                    <?php if ($index === 0): ?>
                                        <td rowspan="<?= $rowspanMachine ?>" style="background:#fff; vertical-align: top; padding-top: 10px;">
                                            <b style="font-size: 13px; color: #0056b3;"><?= htmlspecialchars($mac) ?></b><br>
                                            <span style="font-size: 10px; color: #555;">
                                                Items: <?= $itemsCount ?><br>
                                                Running Plan: <?= $macData['machine_plan_days'] ?> days <strong style="color:#28a745;">(<?= $macPlanPct ?>%)</strong><br>
                                                Running Act: <?= $macData['machine_act_days'] ?> days <strong style="color:#0d6efd;">(<?= $macActPct ?>%)</strong>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                    
                                    <td rowspan="2" style="background:#fff; font-size:10px; vertical-align:middle; border-left: 2px solid #ccc;">
                                        <strong style="color:#d32f2f;"><?= htmlspecialchars($item['item_code']) ?></strong><br>
                                        <span style="color:#666;"><?= htmlspecialchars($item['item_name']) ?></span>
                                    </td>
                                    
                                    <td style="font-size:10px; font-weight:bold; background:#d4edda; color:#155724; text-align:center;">PLAN</td>
                                    <td class="center" style="font-weight:bold; background:#e6f2ff; color:#0056b3;">
                                        <?= $item['plan_days'] ?><br>
                                        <span style="font-size:9px; color:#155724;"><?= $itemPlanPct ?>%</span>
                                    </td>
                                    
                                    <?php for ($i = 1; $i <= $daysInMonth; $i++): ?>
                                        <?php if ($item['p'][$i] > 0): ?>
                                            <td class="cell-filled" title="Tgl <?= $i ?> Plan: Terisi">&#10003;</td>
                                        <?php else: ?>
                                            <td class="cell-empty" title="Tgl <?= $i ?> Plan: Kosong"></td>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </tr>
                                
                                <!-- Baris Act (Item) -->
                                <tr>
                                    <td style="font-size:10px; font-weight:bold; background:#e9ecef; text-align:center;">ACT</td>
                                    <td class="center" style="font-weight:bold; background:#f8f9fa; color:#333;">
                                        <?= $item['act_days'] ?><br>
                                        <span style="font-size:9px; color:#0d6efd;"><?= $itemActPct ?>%</span>
                                    </td>
                                    
                                    <?php for ($i = 1; $i <= $daysInMonth; $i++): ?>
                                        <?php if ($item['a'][$i] > 0): ?>
                                            <td class="cell-actual" title="Tgl <?= $i ?> Act: Terlaksana">&#10003;</td>
                                        <?php else: ?>
                                            <td class="cell-empty" title="Tgl <?= $i ?> Act: Kosong"></td>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </tr>

                                <?php endforeach; // End Loop Item ?>
                            <?php endforeach; // End Loop Mesin ?>

                        <!-- Sub Total Grup Terakhir -->
                        <tr class="row-subtotal">
                            <td colspan="4" style="text-align: right; padding-right: 15px;">Sub Total Running (Plan):</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): ?>
                                <td class="center"><?= ($subTotalPlan[$i] > 0 ? $subTotalPlan[$i] : "-") ?></td>
                            <?php endfor; ?>
                        </tr>
                        <tr class="row-subtotal" style="background-color:#e2e8f0 !important;">
                            <td colspan="4" style="text-align: right; padding-right: 15px;">Sub Total Running (Act):</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): ?>
                                <td class="center"><?= ($subTotalAct[$i] > 0 ? $subTotalAct[$i] : "-") ?></td>
                            <?php endfor; ?>
                        </tr>

                    <?php endforeach; // End Loop Group ?>
                    
                    <!-- Grand Total -->
                    <tr class="row-grandtotal">
                        <td colspan="4" style="text-align: right; padding-right: 15px; text-transform: uppercase;">Grand Total Machine (Plan):</td>
                        <?php for ($i = 1; $i <= $daysInMonth; $i++): ?>
                            <td class="center"><?= ($grandTotalMachinePlan[$i] > 0 ? $grandTotalMachinePlan[$i] : "-") ?></td>
                        <?php endfor; ?>
                    </tr>
                    <tr class="row-grandtotal" style="border-top: none;">
                        <td colspan="4" style="text-align: right; padding-right: 15px; text-transform: uppercase;">Grand Total Assembling (Plan):</td>
                        <?php for ($i = 1; $i <= $daysInMonth; $i++): ?>
                            <td class="center"><?= ($grandTotalAssemblingPlan[$i] > 0 ? $grandTotalAssemblingPlan[$i] : "-") ?></td>
                        <?php endfor; ?>
                    </tr>

                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>