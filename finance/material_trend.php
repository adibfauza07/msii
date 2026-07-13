<?php
// 1. Mencegah error timeout dan memori habis
set_time_limit(0);
ini_set('memory_limit', '512M');

// 2. Load konfigurasi database
require_once "../config/database_aging.php";

// 3. Pastikan ARITHABORT ON (Penting untuk Indexed View)
// Karena $conn adalah resource, gunakan sqlsrv_query
if (isset($conn)) {
    sqlsrv_query($conn, "SET ARITHABORT ON");
    sqlsrv_query($conn, "SET ANSI_WARNINGS ON");
}

// 4. Ambil tahun dari request
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$chart_labels      = array("Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des");
$chart_data_rates  = array();
$table_rows        = array();

// 5. Loop 12 bulan
for ($m = 1; $m <= 12; $m++) {
    $from_date = sprintf("%04d-%02d-01", $selected_year, $m);
    $to_date   = date('Y-m-t', strtotime($from_date));
    
    // Parameter untuk query (sesuai urutan ?)
    $params = array($from_date, $m, $selected_year, $to_date, $from_date, $to_date, $m, $selected_year, $from_date);

    $sql = "
    SELECT 
        SUM((ISNULL(G_AWAL.STOK_AWAL, 0) + ISNULL(G_IN.TOTAL_QTY_IN, 0) - ISNULL(G_AKHIR.STOK_AKHIR, 0)) * (CASE WHEN ISNULL(G_IN.PO_CUR, 'IDR') = 'USD' THEN ISNULL(G_IN.AVG_PRICE, 0) * ISNULL(CR.CURR_CRATE, 1) ELSE ISNULL(G_IN.AVG_PRICE, 0) END)
        ) AS MONTH_KONSUMSI_IDR,
        SUM(ISNULL(DATA_SALES.AMOUNT_SALES_IDR, 0)) AS MONTH_SALES_IDR
    FROM dbo.ITEMS AS I
    INNER JOIN dbo.ITTY ON dbo.ITTY.ITTY_CODE = I.ITTY_CODE
    LEFT JOIN (
        SELECT T.ITEM_ID, SUM(T.TAG_QTY) AS STOK_AWAL
        FROM dbo.TAGS AS T INNER JOIN dbo.SOP AS S ON T.SOP_ID = S.SOP_ID 
        INNER JOIN dbo.ITEMS AS I_S1 ON T.ITEM_ID = I_S1.ITEM_ID
        WHERE (S.SOP_SDATE = ?) AND (I_S1.ITEM_CODE LIKE '%-0') GROUP BY T.ITEM_ID
    ) AS G_AWAL ON I.ITEM_ID = G_AWAL.ITEM_ID
    LEFT JOIN (
        SELECT MAT_ID, TOTAL_QTY_IN, 
               CASE WHEN TOTAL_QTY_IN = 0 THEN 0 ELSE TOTAL_VAL_IN / TOTAL_QTY_IN END AS AVG_PRICE,
               'IDR' AS PO_CUR
        FROM dbo.V_MATERIAL_FINANCIAL_SUMMARY WITH (NOEXPAND)
        WHERE TRAN_MM = ? AND TRAN_YY = ?
    ) AS G_IN ON I.ITEM_ID = G_IN.MAT_ID
    LEFT JOIN (
        SELECT T.ITEM_ID, SUM(T.TAG_QTY) AS STOK_AKHIR
        FROM dbo.TAGS AS T INNER JOIN dbo.SOP AS S ON T.SOP_ID = S.SOP_ID 
        INNER JOIN dbo.ITEMS AS I_S3 ON T.ITEM_ID = I_S3.ITEM_ID
        WHERE (S.SOP_SDATE = DATEADD(dd, DATEDIFF(dd, 0, ?), 0)) AND (I_S3.ITEM_CODE LIKE '%-0')
        GROUP BY T.ITEM_ID
    ) AS G_AKHIR ON I.ITEM_ID = G_AKHIR.ITEM_ID
    LEFT JOIN (
        SELECT B.ITEM_ID AS MAT_ID, 
               SUM(CASE DIPA_PAR.CURR WHEN 'USD' THEN (DIPA_PAR.QTY * DIPA_PAR.PART_PRICE) * ISNULL(CR_S.CURR_CRATE, 1) ELSE (DIPA_PAR.QTY * DIPA_PAR.PART_PRICE) END) AS AMOUNT_SALES_IDR
        FROM dbo.DI 
        INNER JOIN dbo.DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID 
        INNER JOIN dbo.PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID
        INNER JOIN dbo.BOM_DEFAULT AS B ON PRICE.PART_ID = B.PART_ID
        INNER JOIN dbo.ITEMS AS I_S4 ON B.ITEM_ID = I_S4.ITEM_ID
        LEFT JOIN dbo.CURR_RAT AS CR_S ON CR_S.CURR_CODE = 'USD' AND CR_S.CURR_MM = MONTH(DI.DI_DATE) AND CR_S.CURR_YY = YEAR(DI.DI_DATE)
        WHERE (DI.DI_DATE BETWEEN ? AND DATEADD(DD, -1, ?)) AND (I_S4.ITEM_CODE LIKE '%-0')
        GROUP BY B.ITEM_ID
    ) AS DATA_SALES ON I.ITEM_ID = DATA_SALES.MAT_ID
    LEFT JOIN dbo.CURR_RAT AS CR ON CR.CURR_CODE = 'USD' AND CR.CURR_MM = ? AND CR.CURR_YY = ?
    OUTER APPLY (
        SELECT TOP 1 RD_B.POD_PRICE AS LAST_PRICE, P_B.PO_CUR
        FROM dbo.RECEIVE AS R_B
        INNER JOIN dbo.RECEIVE_DETAIL AS RD_B ON R_B.RCV_ID = RD_B.RCV_ID
        INNER JOIN dbo.PO AS P_B ON RD_B.PO_ID = P_B.PO_ID
        WHERE RD_B.ITEM_ID = I.ITEM_ID AND R_B.RCV_DATE >= DATEADD(year, -1, ?)
        ORDER BY R_B.RCV_DATE DESC
    ) AS PRICE_BACKUP
    WHERE (I.ITEM_CODE LIKE '%-0') AND (dbo.ITTY.ITTY_CODE IN ('02', '03', '05'));
    ";

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) { die(print_r(sqlsrv_errors(), true)); }
    
    $row  = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    $konsumsi_val = isset($row['MONTH_KONSUMSI_IDR']) ? (float)$row['MONTH_KONSUMSI_IDR'] : 0.0;
    $sales_val    = isset($row['MONTH_SALES_IDR']) ? (float)$row['MONTH_SALES_IDR'] : 0.0;
    $rate_pct     = ($sales_val > 0) ? ($konsumsi_val / $sales_val) * 100.0 : 0.0;
    
    $chart_data_rates[] = number_format($rate_pct, 4, '.', '');
    $table_rows[] = array("bulan" => $chart_labels[$m - 1], "konsumsi" => $konsumsi_val, "sales" => $sales_val, "rate" => $rate_pct);
}
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>Dashboard Tren Finansial</title><script src="https://cdn.jsdelivr.net/npm/chart.js"></script></head>
<body>
    <div style="width:80%; margin:auto;">
        <h3>Dashboard Tren Finansial Material (-0)</h3>
        <canvas id="trendChart"></canvas>
        <table border="1">
            <thead><tr><th>Bulan</th><th>Konsumsi (IDR)</th><th>Sales (IDR)</th><th>Rate (%)</th></tr></thead>
            <tbody>
                <?php foreach($table_rows as $r): ?>
                <tr><td><?= $r['bulan'] ?></td><td><?= number_format($r['konsumsi']) ?></td><td><?= number_format($r['sales']) ?></td><td><?= number_format($r['rate'],4) ?> %</td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [{ label: 'Rate %', data: <?php echo json_encode($chart_data_rates); ?>, borderColor: '#28a745', fill: true }] }
        });
    </script>
</body>
</html>