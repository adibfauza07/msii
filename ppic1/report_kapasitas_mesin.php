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
$machinesData = array();

// =====================================================================
// QUERY: AGREGASI KAPASITAS MESIN (PLAN vs ACTUAL)
// =====================================================================
if ($isSubmitted) {
    $sql = "WITH Rekap_Transaksi AS (
                SELECT 
                    P.MC_NO,
                    COUNT(DISTINCT P.ITEM_CODE) AS TotalItems,
                    
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
                GROUP BY P.MC_NO
            )
            SELECT 
                ISNULL(G.MAG_STATION, 'TANPA GROUP') AS MAG_STATION, 
                R.MC_NO AS MAC_CODE,
                R.TotalItems,
                R.p1, R.p2, R.p3, R.p4, R.p5, R.p6, R.p7, R.p8, R.p9, R.p10, 
                R.p11, R.p12, R.p13, R.p14, R.p15, R.p16, R.p17, R.p18, R.p19, R.p20, 
                R.p21, R.p22, R.p23, R.p24, R.p25, R.p26, R.p27, R.p28, R.p29, R.p30, R.p31,
                R.a1, R.a2, R.a3, R.a4, R.a5, R.a6, R.a7, R.a8, R.a9, R.a10, 
                R.a11, R.a12, R.a13, R.a14, R.a15, R.a16, R.a17, R.a18, R.a19, R.a20, 
                R.a21, R.a22, R.a23, R.a24, R.a25, R.a26, R.a27, R.a28, R.a29, R.a30, R.a31
            FROM Rekap_Transaksi R
            LEFT JOIN dbo.MAC M ON LTRIM(RTRIM(R.MC_NO)) = LTRIM(RTRIM(M.MAC_CODE))
            LEFT JOIN dbo.MAG G ON M.MAG_ID = G.MAG_ID
            ORDER BY G.MAG_STATION ASC, R.MC_NO ASC";

    $stmt = sqlsrv_query($conn, $sql, array($periode));
    
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $planEmpty = array();
            $actEmpty = array();
            $planActiveDays = 0;

            for ($i = 1; $i <= $daysInMonth; $i++) {
                // Kalkulasi Plan (Berdasarkan Prod Plan R0)
                if ((float)$row["p$i"] > 0) {
                    $planActiveDays++;
                } else {
                    $planEmpty[] = $i;
                }
                
                // Kalkulasi Actual (Berdasarkan Prod OK + Prod NG + Prod HOLD)
                if ((float)$row["a$i"] <= 0) {
                    $actEmpty[] = $i; // Kosong / tidak running jika bernilai 0
                }
            }

            $capacityPct = ($planActiveDays / $daysInMonth) * 100;

            $machinesData[] = array(
                'group' => trim($row['MAG_STATION']),
                'mac' => trim($row['MAC_CODE']),
                'items_count' => (int)$row['TotalItems'],
                'capacity_pct' => $capacityPct,
                'plan_empty_arr' => $planEmpty,
                'act_empty_arr' => $actEmpty
            );
        }
        sqlsrv_free_stmt($stmt);
    }
    
    // CUSTOM SORT: Mengurutkan by Group terlebih dahulu, lalu Machine Code
    usort($machinesData, function($a, $b) {
        if ($a['group'] != $b['group']) {
            return strcmp($a['group'], $b['group']);
        }
        return strcmp($a['mac'], $b['mac']);
    });
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

        table.report-table { width: 100%; border-collapse: collapse; background-color: #fff; border: 1px solid #bbb; margin-top: 10px; min-width: 1000px; }
        table.report-table th, table.report-table td { padding: 4px 6px; border: 1px solid #ddd; text-align: left; }
        table.report-table th { background-color: #eaeaea; font-weight: bold; text-align: center; position: sticky; top: 0; z-index: 10; }
        table.report-table td.center { text-align: center; }
        
        /* Baris Group Header */
        .group-header td { background-color: #dbeafe !important; color: #004494; font-weight: bold; padding: 8px; font-size: 13px; }
        
        /* Baris Sub & Grand Total */
        .row-subtotal td { background-color: #f1f5f9 !important; font-weight: bold; color: #334155; }
        .row-grandtotal td { background-color: #cbd5e1 !important; font-weight: bold; color: #0f172a; font-size: 13px; border-top: 2px solid #64748b; }
        
        /* Cell Matrix (Tampilan Web) */
        .cell-empty { background-color: #dc3545; color: white; text-align: center; font-size: 10px; }
        .cell-filled { background-color: #28a745; color: white; text-align: center; font-size: 10px; }
        .cell-actual { background-color: #0d6efd; color: white; text-align: center; font-size: 10px; } /* Warna biru untuk aktual */
        
        .legend { font-size: 11px; margin-top: 10px; }
        .legend-box { display: inline-block; width: 12px; height: 12px; margin-right: 5px; vertical-align: middle; }
        .bg-empty { background-color: #dc3545; }
        .bg-filled { background-color: #28a745; }
        .bg-actual { background-color: #0d6efd; }

        /* KHUSUS UNTUK TAMPILAN SAAT PRINT (CTRL+P) */
        @media print {
            body { padding: 0; background-color: #fff; }
            .panel { border: none; box-shadow: none; padding: 0; }
            form, .no-print { display: none; }
            
            .cell-empty { background-color: #dc3545 !important; -webkit-print-color-adjust: exact; }
            .cell-filled { background-color: transparent !important; color: #000 !important; font-size: 13px; font-weight: bold; }
            .cell-actual { background-color: transparent !important; color: #0d6efd !important; font-size: 13px; font-weight: bold; }
            
            .group-header td, .row-subtotal td, .row-grandtotal td { -webkit-print-color-adjust: exact; }
            .group-header td { color: #000 !important; }
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
            <span>Matrix Utilisasi Mesin <?= $isSubmitted ? "- Periode $periode" : "" ?></span>
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
                    <th style="width: 150px; text-align: left; padding-left: 10px;">Machine Code</th>
                    <th style="width: 50px;">Kategori</th>
                    <!-- Render kolom tanggal (1 sampai hari terakhir dalam bulan tersebut) -->
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
                        <td colspan="<?= $cols + 2 ?>" class="center" style="padding: 40px; color: #555; font-size: 14px;">
                            Silakan pilih <b>Tahun</b> dan <b>Bulan</b> pada filter di atas, lalu klik tombol <b>"Tampilkan Matrix"</b>.
                        </td>
                    </tr>
                <?php elseif (empty($machinesData)): ?>
                    <tr>
                        <td colspan="<?= $cols + 2 ?>" class="center" style="padding: 20px;">
                            Data mesin tidak ditemukan pada periode ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                        $currentGroup = ""; 
                        
                        $subTotalPlan = array_fill(1, $daysInMonth, 0);
                        $subTotalAct  = array_fill(1, $daysInMonth, 0);
                        
                        $grandTotalMachinePlan = array_fill(1, $daysInMonth, 0);
                        $grandTotalAssemblingPlan = array_fill(1, $daysInMonth, 0);
                        $isFirstGroup = true;

                        foreach ($machinesData as $md): 
                            
                            $isAssembling = (strpos(strtoupper($md['group']), 'ASS') !== false || strtoupper(substr(trim($md['mac']), 0, 1)) === 'A');

                            // 1. Cek Grup Baru
                            if ($md['group'] !== $currentGroup) {
                                
                                if (!$isFirstGroup) {
                                    // Tampilkan Sub Total Plan
                                    echo "<tr class='row-subtotal'>";
                                    echo "<td colspan='2' style='text-align: right; padding-right: 15px;'>Sub Total Running (Plan):</td>";
                                    for ($i = 1; $i <= $daysInMonth; $i++) {
                                        echo "<td class='center'>" . ($subTotalPlan[$i] > 0 ? $subTotalPlan[$i] : "-") . "</td>";
                                    }
                                    echo "</tr>";
                                    // Tampilkan Sub Total Actual
                                    echo "<tr class='row-subtotal' style='background-color:#e2e8f0 !important;'>";
                                    echo "<td colspan='2' style='text-align: right; padding-right: 15px;'>Sub Total Running (Act):</td>";
                                    for ($i = 1; $i <= $daysInMonth; $i++) {
                                        echo "<td class='center'>" . ($subTotalAct[$i] > 0 ? $subTotalAct[$i] : "-") . "</td>";
                                    }
                                    echo "</tr>";
                                    
                                    // Reset Sub Total
                                    $subTotalPlan = array_fill(1, $daysInMonth, 0);
                                    $subTotalAct = array_fill(1, $daysInMonth, 0);
                                }
                                
                                $currentGroup = $md['group'];
                                $isFirstGroup = false;
                                
                                echo "<tr class='group-header'>";
                                echo "<td colspan='" . ($daysInMonth + 2) . "'><i class='fa fa-cubes'></i> Mach Group : " . htmlspecialchars($currentGroup) . "</td>";
                                echo "</tr>";
                            }
                    ?>
                        <!-- 2A. Baris Plan -->
                        <tr>
                            <td rowspan="2" style="background:#fff; vertical-align: middle;">
                                <b><?= htmlspecialchars($md['mac']) ?></b> 
                                <br>
                                <span style="font-size: 10px; color: #555;"><?= $md['items_count'] ?> items | Plan <?= round($md['capacity_pct']) ?>%</span>
                            </td>
                            <td style="font-size:10px; font-weight:bold; background:#f8f9fa; text-align:center;">Plan</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): ?>
                                <?php if (in_array($i, $md['plan_empty_arr'])): ?>
                                    <td class="cell-empty" title="Tgl <?= $i ?> Plan: Kosong"></td>
                                <?php else: ?>
                                    <?php 
                                        $subTotalPlan[$i]++;
                                        if ($isAssembling) $grandTotalAssemblingPlan[$i]++; else $grandTotalMachinePlan[$i]++;
                                    ?>
                                    <td class="cell-filled" title="Tgl <?= $i ?> Plan: Terisi">&#10003;</td>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </tr>
                        
                        <!-- 2B. Baris Actual -->
                        <tr>
                            <td style="font-size:10px; font-weight:bold; background:#e9ecef; text-align:center;">Act</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): ?>
                                <?php if (in_array($i, $md['act_empty_arr'])): ?>
                                    <td class="cell-empty" title="Tgl <?= $i ?> Act: Kosong"></td>
                                <?php else: ?>
                                    <?php $subTotalAct[$i]++; ?>
                                    <td class="cell-actual" title="Tgl <?= $i ?> Act: Terlaksana">&#10003;</td>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (!$isFirstGroup): ?>
                        <!-- Sub Total Grup Terakhir -->
                        <tr class="row-subtotal">
                            <td colspan="2" style="text-align: right; padding-right: 15px;">Sub Total Running (Plan):</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): ?>
                                <td class="center"><?= ($subTotalPlan[$i] > 0 ? $subTotalPlan[$i] : "-") ?></td>
                            <?php endfor; ?>
                        </tr>
                        <tr class="row-subtotal" style="background-color:#e2e8f0 !important;">
                            <td colspan="2" style="text-align: right; padding-right: 15px;">Sub Total Running (Act):</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): ?>
                                <td class="center"><?= ($subTotalAct[$i] > 0 ? $subTotalAct[$i] : "-") ?></td>
                            <?php endfor; ?>
                        </tr>
                        
                        <!-- Grand Total (Tetap fokus pada Plan sebagai baseline) -->
                        <tr class="row-grandtotal">
                            <td colspan="2" style="text-align: right; padding-right: 15px; text-transform: uppercase;">Grand Total Machine (Plan):</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): ?>
                                <td class="center"><?= ($grandTotalMachinePlan[$i] > 0 ? $grandTotalMachinePlan[$i] : "-") ?></td>
                            <?php endfor; ?>
                        </tr>
                        <tr class="row-grandtotal" style="border-top: none;">
                            <td colspan="2" style="text-align: right; padding-right: 15px; text-transform: uppercase;">Grand Total Assembling (Plan):</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): ?>
                                <td class="center"><?= ($grandTotalAssemblingPlan[$i] > 0 ? $grandTotalAssemblingPlan[$i] : "-") ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endif; ?>

                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>