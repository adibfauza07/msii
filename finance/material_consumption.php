<?php
// 1. Load config database dinamis multi-plant Anda (Jalur Mundur 1 Folder)
require_once "../config/database_aging.php";

// 2. Ambil parameter filter tanggal dari request (default: bulan berjalan jika kosong)
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-t');

// 3. Susun array parameter (Ada total 11 tanda tanya (?) karena penambahan parameter tanggal di UNION ALL Vendor)
$params = [
    $from_date,              // Subquery 1 (Stok Awal)
    $from_date, $to_date,    // Subquery 2 (Mutasi Gudang Material)
    $to_date,                // Subquery 3 (Stok Akhir)
    $from_date, $to_date,    // Subquery 4 (Konversi Delivery FG)
    $from_date, $to_date,    // Subquery 5 - Bagian internal (PRODUCTION)
    $from_date, $to_date     // Subquery 5 - Bagian vendor (INV_TRAN TRTY 12)
];

// 4. Query SQL Server Terintegrasi Penuh (Internal + Vendor Production)
$sql = "
SELECT TOP (100) PERCENT 
    CASE 
        WHEN CHARINDEX('-', I.ITEM_CODE) > 0 THEN LEFT(I.ITEM_CODE, CHARINDEX('-', I.ITEM_CODE) - 1)
        ELSE I.ITEM_CODE 
    END AS ITEM_CODE_CLEAN, 
    
    MIN(I.ITEM_NAME) AS ITEM_NAME, 
    MIN(I.ITEM_SAFETY) AS ITEM_SAFETY, 
    dbo.ITTY.ITTY_CODE, 
    dbo.ITTY.ITTY_DESC, 
    
    -- Akumulasi Data Aktual Gudang Material
    SUM(ISNULL(DATA_TAGS.STOK_AWAL, 0)) AS STOK_AWAL,  
    SUM(ISNULL(DATA_TRANS.TIN1, 0)) AS TIN1,          
    SUM(ISNULL(DATA_TRANS.TOUT1, 0)) AS TOUT1,        
    SUM(ISNULL(DATA_TAGS1.STOK_AKHIR, 0)) AS STOK_AKHIR, 
    SUM(ISNULL(DATA_TAGS.STOK_AWAL, 0) + ISNULL(DATA_TRANS.TIN1, 0) - ISNULL(DATA_TAGS1.STOK_AKHIR, 0)) AS AKTUAL_KONSUMSI,

    -- Standar 1: Berdasarkan Kebutuhan Delivery (Penjualan / DI)
    SUM(ISNULL(DATA_CONVERT_DI.STANDAR_KONSUMSI_BOM, 0)) AS STANDAR_DELIVERY,

    -- Standar 2: Berdasarkan Hasil Kebutuhan Produksi Gabungan (Internal + Vendor)
    SUM(ISNULL(DATA_CONVERT_PROD.STANDAR_PRODUKSI_BOM, 0)) AS STANDAR_PRODUKSI,

    -- Rasio 1: Aktual vs Kebutuhan Delivery (%)
    CASE 
        WHEN SUM(ISNULL(DATA_CONVERT_DI.STANDAR_KONSUMSI_BOM, 0)) = 0 THEN 0
        ELSE (SUM(ISNULL(DATA_TAGS.STOK_AWAL, 0) + ISNULL(DATA_TRANS.TIN1, 0) - ISNULL(DATA_TAGS1.STOK_AKHIR, 0)) / SUM(ISNULL(DATA_CONVERT_DI.STANDAR_KONSUMSI_BOM, 0))) * 100.0
    END AS CONSUMPTION_DEL_PCT,

    -- Rasio 2: Aktual vs Total Produksi Gabungan (%)
    CASE 
        WHEN SUM(ISNULL(DATA_CONVERT_PROD.STANDAR_PRODUKSI_BOM, 0)) = 0 THEN 0
        ELSE (SUM(ISNULL(DATA_TAGS.STOK_AWAL, 0) + ISNULL(DATA_TRANS.TIN1, 0) - ISNULL(DATA_TAGS1.STOK_AKHIR, 0)) / SUM(ISNULL(DATA_CONVERT_PROD.STANDAR_PRODUKSI_BOM, 0))) * 100.0
    END AS CONSUMPTION_PROD_PCT

FROM (
    -- SUBQUERY 1: STOK AWAL MATERIAL MENTAH
    SELECT T.ITEM_ID, SUM(T.TAG_QTY) AS STOK_AWAL
    FROM dbo.TAGS AS T 
    INNER JOIN dbo.SOP AS S ON T.SOP_ID = S.SOP_ID 
    WHERE (S.SOP_SDATE = ?)
    GROUP BY T.ITEM_ID
) AS DATA_TAGS 

FULL OUTER JOIN (
    -- SUBQUERY 2: MUTASI TIN & TOUT MATERIAL MENTAH
    SELECT 
        IT.ITEM_ID, 
        SUM(CASE TL.TRTY_INOUT WHEN 1 THEN IT.IT_QTY * TL.TRTY_SIGN ELSE 0 END) AS TIN1, 
        SUM(CASE TL.TRTY_INOUT WHEN 2 THEN IT.IT_QTY * TL.TRTY_SIGN ELSE 0 END) AS TOUT1
    FROM dbo.INV_TRAN AS IT 
    INNER JOIN dbo.TRANS AS T ON IT.TRAN_ID = T.TRAN_ID 
    INNER JOIN dbo.TRTY_LOC AS TL ON T.TRTY_CODE = TL.TRTY_CODE 
    WHERE (T.TRAN_DATE BETWEEN ? AND DATEADD(DD, -1, ?))
    GROUP BY IT.ITEM_ID
) AS DATA_TRANS ON DATA_TAGS.ITEM_ID = DATA_TRANS.ITEM_ID 

FULL OUTER JOIN (
    -- SUBQUERY 3: STOK AKHIR MATERIAL MENTAH
    SELECT T.ITEM_ID, SUM(T.TAG_QTY) AS STOK_AKHIR
    FROM dbo.TAGS AS T 
    INNER JOIN dbo.SOP AS S ON T.SOP_ID = S.SOP_ID 
    WHERE (S.SOP_SDATE = DATEADD(dd, DATEDIFF(dd, 0, ?), 0))
    GROUP BY T.ITEM_ID
) AS DATA_TAGS1 ON DATA_TAGS1.ITEM_ID = DATA_TAGS.ITEM_ID OR DATA_TAGS1.ITEM_ID = DATA_TRANS.ITEM_ID 

-- SUBQUERY 4: PROSES KONVERSI DATA DELIVERY SURAT JALAN (DI)
LEFT JOIN (
    SELECT 
        B.ITEM_ID AS MAT_ID,
        SUM(CASE ITEMS_1.ITTY_CODE WHEN '02' THEN (DELIVERY_FG.DEL_QTY * B.QTY) / 1000.0 ELSE (DELIVERY_FG.DEL_QTY * B.QTY) END) AS STANDAR_KONSUMSI_BOM
    FROM (
        SELECT SUM(dbo.DI_PART.DIPA_QTY) AS DEL_QTY, dbo.PRICE.PART_ID
        FROM dbo.DI 
        INNER JOIN dbo.DI_PART ON dbo.DI.DI_ID = dbo.DI_PART.DI_ID 
        INNER JOIN dbo.PRICE ON dbo.DI_PART.PRICE_ID = dbo.PRICE.PRICE_ID
        WHERE (dbo.DI.DI_DATE BETWEEN ? AND DATEADD(DD, -1, ?))
        GROUP BY dbo.PRICE.PART_ID
    ) AS DELIVERY_FG
    INNER JOIN dbo.BOM_DEFAULT AS B ON DELIVERY_FG.PART_ID = B.PART_ID
    INNER JOIN dbo.ITEMS AS ITEMS_1 ON B.ITEM_ID = ITEMS_1.ITEM_ID
    WHERE (ITEMS_1.ITTY_CODE IN ('02', '03', '05'))
    GROUP BY B.ITEM_ID
) AS DATA_CONVERT_DI ON DATA_CONVERT_DI.MAT_ID = COALESCE(DATA_TAGS.ITEM_ID, DATA_TRANS.ITEM_ID, DATA_TAGS1.ITEM_ID)

-- SUBQUERY 5: GABUNGAN PRODUKSI AKTUAL INTERNAL + PRODUKSI VENDOR (SUBKON)
LEFT JOIN (
    SELECT 
        B.ITEM_ID AS MAT_ID,
        SUM(CASE ITEMS_2.ITTY_CODE WHEN '02' THEN (TOTAL_PROD_FG.TOTAL_QTY * B.QTY) / 1000.0 ELSE (TOTAL_PROD_FG.TOTAL_QTY * B.QTY) END) AS STANDAR_PRODUKSI_BOM
    FROM (
        -- Jalur A: Produksi Internal Perusahaan
        SELECT SUM(dbo.PRODUCTION.PD_QTY) AS TOTAL_QTY, dbo.WO.ITEM_ID AS FG_ITEM_ID
        FROM dbo.PRODUCTION 
        INNER JOIN dbo.WO ON dbo.PRODUCTION.WO_ID = dbo.WO.WO_ID
        WHERE (dbo.PRODUCTION.PD_DATE BETWEEN ? AND DATEADD(DD, -1, ?))
        GROUP BY dbo.WO.ITEM_ID

        UNION ALL

        -- Jalur B: Produksi di Luar Perusahaan / Vendor (TRTY_CODE = 12)
        SELECT SUM(dbo.INV_TRAN.IT_QTY) AS TOTAL_QTY, dbo.INV_TRAN.ITEM_ID AS FG_ITEM_ID
        FROM dbo.INV_TRAN 
        INNER JOIN dbo.TRANS ON dbo.INV_TRAN.TRAN_ID = dbo.TRANS.TRAN_ID
        WHERE (dbo.TRANS.TRAN_DATE BETWEEN ? AND DATEADD(DD, -1, ?))
          AND (dbo.TRANS.TRTY_CODE = '12')
        GROUP BY dbo.INV_TRAN.ITEM_ID
    ) AS TOTAL_PROD_FG
    INNER JOIN dbo.BOM_DEFAULT AS B ON TOTAL_PROD_FG.FG_ITEM_ID = B.PART_ID
    INNER JOIN dbo.ITEMS AS ITEMS_2 ON B.ITEM_ID = ITEMS_2.ITEM_ID
    WHERE (ITEMS_2.ITTY_CODE IN ('02', '03', '05'))
    GROUP BY B.ITEM_ID
) AS DATA_CONVERT_PROD ON DATA_CONVERT_PROD.MAT_ID = COALESCE(DATA_TAGS.ITEM_ID, DATA_TRANS.ITEM_ID, DATA_TAGS1.ITEM_ID)

-- Join Akhir ke Master Item Komponen Material Mentah
INNER JOIN dbo.ITEMS AS I ON I.ITEM_ID = COALESCE(DATA_TAGS.ITEM_ID, DATA_TRANS.ITEM_ID, DATA_TAGS1.ITEM_ID) 
INNER JOIN dbo.ITTY ON dbo.ITTY.ITTY_CODE = I.ITTY_CODE

WHERE (dbo.ITTY.ITTY_CODE IN ('02', '03', '05'))

GROUP BY 
    CASE 
        WHEN CHARINDEX('-', I.ITEM_CODE) > 0 THEN LEFT(I.ITEM_CODE, CHARINDEX('-', I.ITEM_CODE) - 1)
        ELSE I.ITEM_CODE 
    END,
    dbo.ITTY.ITTY_CODE, 
    dbo.ITTY.ITTY_DESC
    
ORDER BY ITEM_CODE_CLEAN;
";

$stmt = q($sql, $params);

// Akumulator Grand Total
$total_stok_awal   = 0;
$total_tin         = 0;
$total_tout        = 0;
$total_stok_akhir  = 0;
$total_aktual_cons = 0;
$total_standar_del = 0;
$total_standar_prd = 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Material Consumption Rate - Plant <?php echo strtoupper($active_plant); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f9f9f9; }
        .filter-box { background: #fff; padding: 15px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .formula-box { background-color: #eef2f7; border-left: 5px solid #0056b3; padding: 15px 20px; margin-bottom: 20px; border-radius: 4px; }
        .formula-line { margin-bottom: 6px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #ddd; padding: 8px; font-size: 12px; }
        th { background-color: #2c3e50; color: white; white-space: nowrap; text-align: center; vertical-align: middle; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .text-right { text-align: right; }
        .badge { padding: 4px 8px; border-radius: 3px; font-weight: bold; font-size: 11px; }
        .tfoot-total { background-color: #e9ecef !important; font-weight: bold; }
    </style>
</head>
<body>

    <h2>Laporan Analisis Material Consumption Rate</h2>
    
    <div style="margin-bottom: 15px;">
        Plant Terpilih: <span class="badge" style="background-color: #0056b3; color:white;">PLANT <?php echo strtoupper($active_plant); ?></span>
    </div>

    <div class="formula-box">
        <div class="formula-line">
            <strong><i class="bi bi-calculator-fill"></i> Aktual Konsumsi Gudang</strong> = <code>Stok Awal + Tin - Stok Akhir</code>
        </div>
        <div class="formula-line">
            <strong><i class="bi bi-truck"></i> 1. Standar Delivery (Sales)</strong> = <code>Qty Kirim FG (DI) &times; Kuantitas Resep BOM (Tipe 02 / 1000)</code>
        </div>
        <div class="formula-line" style="color: #28a745;">
            <strong><i class="bi bi-cpu-fill"></i> 2. Standar Produksi Gabungan</strong> = <code>(Qty Internal + Qty Vendor TRTY 12) &times; Kuantitas Resep BOM</code>
        </div>
        <hr style="margin: 8px 0; border-color: rgba(0,0,0,0.1);">
        <div class="formula-line">
            <strong><i class="bi bi-info-circle-fill"></i> Catatan Audit:</strong> Data produksi kini telah menggabungkan hasil manufaktur internal pabrik dan subkon dari pihak luar perusahaan (Vendor) untuk keakuratan total konsumsi material baku.
        </div>
    </div>

    <div class="filter-box">
        <form method="GET" action="">
            <label>Dari: </label> <input type="date" name="from_date" value="<?php echo $from_date; ?>">
            <label>Sampai: </label> <input type="date" name="to_date" value="<?php echo $to_date; ?>">
            <button type="submit" style="padding: 5px 15px; background: #0056b3; color: #fff; border: none; border-radius: 3px; cursor: pointer;">Filter Data</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Kode Material</th>
                <th>Nama Material</th>
                <th>Tipe</th>
                <th>Stok Awal</th>
                <th>Masuk (Tin)</th>
                <th>Keluar (Tout)</th>
                <th>Stok Akhir</th>
                <th>Aktual Konsumsi</th>
                <th style="background-color: #34495e;">Std Kebutuhan (Delivery)</th>
                <th style="background-color: #1e7e34;">Std Kebutuhan (Produksi)</th>
                <th>Cons vs Del %</th>
                <th style="color: #1e7e34;">Cons vs Prod %</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $has_data = false;
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): 
                $has_data = true;

                $total_stok_awal   += $row['STOK_AWAL'];
                $total_tin         += $row['TIN1'];
                $total_tout        += $row['TOUT1'];
                $total_stok_akhir  += $row['STOK_AKHIR'];
                $total_aktual_cons += $row['AKTUAL_KONSUMSI'];
                $total_standar_del += $row['STANDAR_DELIVERY'];
                $total_standar_prd += $row['STANDAR_PRODUKSI'];
            ?>
                <tr>
                    <td><?php echo $row['ITEM_CODE_CLEAN']; ?></td>
                    <td><?php echo $row['ITEM_NAME']; ?></td>
                    <td><small><?php echo $row['ITTY_DESC']; ?></small></td>
                    <td class="text-right"><?php echo number_format($row['STOK_AWAL'], 2, ',', '.'); ?></td>
                    <td class="text-right"><?php echo number_format($row['TIN1'], 2, ',', '.'); ?></td>
                    <td class="text-right"><?php echo number_format($row['TOUT1'], 2, ',', '.'); ?></td>
                    <td class="text-right"><?php echo number_format($row['STOK_AKHIR'], 2, ',', '.'); ?></td>
                    <td class="text-right" style="font-weight: bold;"><?php echo number_format($row['AKTUAL_KONSUMSI'], 2, ',', '.'); ?></td>
                    <td class="text-right" style="color: #444;"><?php echo number_format($row['STANDAR_DELIVERY'], 2, ',', '.'); ?></td>
                    <td class="text-right" style="color: #1e7e34; font-weight: bold;"><?php echo number_format($row['STANDAR_PRODUKSI'], 2, ',', '.'); ?></td>
                    <td class="text-right"><?php echo number_format($row['CONSUMPTION_DEL_PCT'], 4, ',', '.') . ' %'; ?></td>
                    <td class="text-right" style="font-weight: bold; color: #1e7e34;"><?php echo number_format($row['CONSUMPTION_PROD_PCT'], 4, ',', '.') . ' %'; ?></td>
                </tr>
            <?php 
            endwhile; 
            
            if ($has_data): 
                $grand_del_pct = ($total_standar_del > 0) ? ($total_aktual_cons / $total_standar_del) * 100.0 : 0;
                $grand_prd_pct = ($total_standar_prd > 0) ? ($total_aktual_cons / $total_standar_prd) * 100.0 : 0;
            ?>
                <tr class="tfoot-total">
                    <td colspan="3" style="text-align: center;">GRAND TOTAL GABUNGAN</td>
                    <td class="text-right"><?php echo number_format($total_stok_awal, 2, ',', '.'); ?></td>
                    <td class="text-right"><?php echo number_format($total_tin, 2, ',', '.'); ?></td>
                    <td class="text-right"><?php echo number_format($total_tout, 2, ',', '.'); ?></td>
                    <td class="text-right"><?php echo number_format($total_stok_akhir, 2, ',', '.'); ?></td>
                    <td class="text-right"><?php echo number_format($total_aktual_cons, 2, ',', '.'); ?></td>
                    <td class="text-right"><?php echo number_format($total_standar_del, 2, ',', '.'); ?></td>
                    <td class="text-right" style="color: #1e7e34;"><?php echo number_format($total_standar_prd, 2, ',', '.'); ?></td>
                    <td class="text-right" style="color: #0056b3;"><?php echo number_format($grand_del_pct, 4, ',', '.') . ' %'; ?></td>
                    <td class="text-right" style="color: #d9534f; font-size: 13px;"><?php echo number_format($grand_prd_pct, 4, ',', '.') . ' %'; ?></td>
                </tr>
            <?php else: ?>
                <tr><td colspan="12" style="text-align: center; color: #999; padding: 20px;">Tidak ada data pada periode ini.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>