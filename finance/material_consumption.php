<?php
// 1. Matikan limit waktu 30 detik default XAMPP agar PHP sabar menunggu database raksasa Anda
set_time_limit(0);

// 2. Load config database dinamis multi-plant Anda (Jalur Mundur 1 Folder)
require_once "../config/database_aging.php";

// 3. Ambil parameter filter tanggal dari request (default: bulan berjalan jika kosong)
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-t');

// Ambil angka bulan dan tahun berjalan untuk mengambil kurs tengah dari CURR_RAT
$curr_mm = (int)date('m', strtotime($from_date));
$curr_yy = (int)date('Y', strtotime($from_date));

// 4. Susun array parameter filter (Total ada 7 parameter tanda tanya)
$params = [
    $from_date,              // Subquery 1 (Stok Awal)
    $from_date, $to_date,    // Subquery 2 (In range)
    $to_date,                // Subquery 3 (Stok Akhir)
    $from_date, $to_date,    // Subquery 4 (Sales murni DIPA_PAR range)
    $curr_mm, $curr_yy,      // Main query join ke CURR_RAT (USD rate)
    $from_date               // Pembatas efisiensi OUTER APPLY (Mencegah Full Scan sejarah)
];

// 5. Query SQL Server Finansial AMOUNT Berbasis Murni Mata Uang IDR (Rupiah) Hasil Optimasi Tinggi
$sql = "
SELECT TOP (100) PERCENT 
    I.ITEM_CODE, 
    I.ITEM_NAME, 
    dbo.ITTY.ITTY_DESC, 
    
    -- Indikator Mata Uang Asal Asli Gudang dan Sales
    ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.PO_CUR ELSE PRICE_BACKUP.PO_CUR END, '-') AS MAT_ORIGINAL_CUR,
    ISNULL(DATA_SALES.SALES_CURR, '-') AS SALES_ORIGINAL_CUR,
    
    -- Nilai Kurs USD Bulan Berjalan
    ISNULL(CR.CURR_CRATE, 1) AS USD_EXCHANGE_RATE,

    -- Harga Satuan Komponen Gudang Dikonversi ke IDR (Jika USD, kalikan Kurs)
    CASE 
        WHEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.PO_CUR ELSE PRICE_BACKUP.PO_CUR END, 'IDR') = 'USD'
        THEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0) * ISNULL(CR.CURR_CRATE, 1)
        ELSE ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0)
    END AS PRICE_IN_IDR,

    -- CALCULATE AMOUNT FISIK GUDANG (Dalam Basis IDR)
    (ISNULL(DATA_TAGS.STOK_AWAL, 0) * CASE WHEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.PO_CUR ELSE PRICE_BACKUP.PO_CUR END, 'IDR') = 'USD' 
        THEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0) * ISNULL(CR.CURR_CRATE, 1)
        ELSE ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0) END) AS AMOUNT_STOK_AWAL_IDR,

    (ISNULL(DATA_RECEIVE.TOTAL_IN, 0) * CASE WHEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.PO_CUR ELSE PRICE_BACKUP.PO_CUR END, 'IDR') = 'USD' 
        THEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0) * ISNULL(CR.CURR_CRATE, 1)
        ELSE ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0) END) AS AMOUNT_IN_IDR,

    (ISNULL(DATA_TAGS1.STOK_AKHIR, 0) * CASE WHEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.PO_CUR ELSE PRICE_BACKUP.PO_CUR END, 'IDR') = 'USD' 
        THEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0) * ISNULL(CR.CURR_CRATE, 1)
        ELSE ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0) END) AS AMOUNT_STOK_AKHIR_IDR,

    -- Pembilang: Amount Aktual Konsumsi Berbasis Nilai Rupiah IDR
    ((ISNULL(DATA_TAGS.STOK_AWAL, 0) + ISNULL(DATA_RECEIVE.TOTAL_IN, 0) - ISNULL(DATA_TAGS1.STOK_AKHIR, 0)) * CASE WHEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.PO_CUR ELSE PRICE_BACKUP.PO_CUR END, 'IDR') = 'USD' 
        THEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0) * ISNULL(CR.CURR_CRATE, 1)
        ELSE ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0) END) AS AMOUNT_AKTUAL_KONSUMSI_IDR,

    -- Pembagi: Amount Sales Hasil Konversi Jual ke Rupiah dari DIPA_PAR
    ISNULL(DATA_SALES.AMOUNT_SALES_IDR, 0) AS AMOUNT_SALES_DELIVERY_IDR,

    -- RUMUS UTAMA FINANSIAL AMOUNT RATE (%)
    CASE 
        WHEN ISNULL(DATA_SALES.AMOUNT_SALES_IDR, 0) = 0 THEN 0
        ELSE (((ISNULL(DATA_TAGS.STOK_AWAL, 0) + ISNULL(DATA_RECEIVE.TOTAL_IN, 0) - ISNULL(DATA_TAGS1.STOK_AKHIR, 0)) * CASE WHEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.PO_CUR ELSE PRICE_BACKUP.PO_CUR END, 'IDR') = 'USD' 
            THEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0) * ISNULL(CR.CURR_CRATE, 1)
            ELSE ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0) END) / ISNULL(DATA_SALES.AMOUNT_SALES_IDR, 0)) * 100.0
    END AS CONSUMPTION_AMOUNT_PCT

FROM (
    -- SUBQUERY 1: STOK AWAL MATERIAL MENTAH (Disaring ketat -0 agar loading instan)
    SELECT T.ITEM_ID, SUM(T.TAG_QTY) AS STOK_AWAL
    FROM dbo.TAGS AS T 
    INNER JOIN dbo.SOP AS S ON T.SOP_ID = S.SOP_ID 
    INNER JOIN dbo.ITEMS AS I_SUB1 ON T.ITEM_ID = I_SUB1.ITEM_ID
    WHERE (S.SOP_SDATE = ?) AND (I_SUB1.ITEM_CODE LIKE '%-0')
    GROUP BY T.ITEM_ID
) AS DATA_TAGS 

FULL OUTER JOIN (
    -- SUBQUERY 2: DATA IN PEMBELIAN MASUK MURNI DARI SKEMA RECEIVE PO (Disaring ketat -0)
    SELECT 
        RD.ITEM_ID,
        SUM(RD.RCVD_QTY) AS TOTAL_IN,
        MAX(P.PO_CUR) AS PO_CUR,
        CASE WHEN SUM(RD.RCVD_QTY) = 0 THEN 0 ELSE SUM(RD.RCVD_QTY * RD.POD_PRICE) / SUM(RD.RCVD_QTY) END AS AVG_PRICE
    FROM dbo.RECEIVE AS R
    INNER JOIN dbo.RECEIVE_DETAIL AS RD ON R.RCV_ID = RD.RCV_ID
    INNER JOIN dbo.PO AS P ON RD.PO_ID = P.PO_ID
    INNER JOIN dbo.ITEMS AS I_SUB2 ON RD.ITEM_ID = I_SUB2.ITEM_ID
    WHERE (R.RCV_DATE BETWEEN ? AND DATEADD(DD, -1, ?)) AND (I_SUB2.ITEM_CODE LIKE '%-0')
    GROUP BY RD.ITEM_ID
) AS DATA_RECEIVE ON DATA_TAGS.ITEM_ID = DATA_RECEIVE.ITEM_ID 

FULL OUTER JOIN (
    -- SUBQUERY 3: STOK AKHIR MATERIAL MENTAH (Disaring ketat -0)
    SELECT T.ITEM_ID, SUM(T.TAG_QTY) AS STOK_AKHIR
    FROM dbo.TAGS AS T 
    INNER JOIN dbo.SOP AS S ON T.SOP_ID = S.SOP_ID 
    INNER JOIN dbo.ITEMS AS I_SUB3 ON T.ITEM_ID = I_SUB3.ITEM_ID
    WHERE (S.SOP_SDATE = DATEADD(dd, DATEDIFF(dd, 0, ?), 0)) AND (I_SUB3.ITEM_CODE LIKE '%-0')
    GROUP BY T.ITEM_ID
-- PERBAIKAN: multi-part identifier bound disamakan penuh ke DATA_RECEIVE
) AS DATA_TAGS1 ON DATA_TAGS1.ITEM_ID = DATA_TAGS.ITEM_ID OR DATA_TAGS1.ITEM_ID = DATA_RECEIVE.ITEM_ID 

-- SUBQUERY 4: DATA NILAI AMOUNT SALES PENJUALAN MURNI VIA RESEP BOM JALUR UTAMA (Disaring ketat -0)
LEFT JOIN (
    SELECT 
        B.ITEM_ID AS MAT_ID,
        MAX(DIPA_PAR.CURR) AS SALES_CURR,
        SUM(
            CASE DIPA_PAR.CURR 
                WHEN 'USD' THEN (DIPA_PAR.QTY * DIPA_PAR.PART_PRICE) * ISNULL(CR_S.CURR_CRATE, 1)
                ELSE (DIPA_PAR.QTY * DIPA_PAR.PART_PRICE)
            END
        ) AS AMOUNT_SALES_IDR
    FROM dbo.DI 
    INNER JOIN dbo.DIPA_PAR ON DI.DI_ID = DIPA_PAR.DI_ID 
    INNER JOIN dbo.PRICE ON DIPA_PAR.PRICE_ID = PRICE.PRICE_ID
    INNER JOIN dbo.BOM_DEFAULT AS B ON PRICE.PART_ID = B.PART_ID
    INNER JOIN dbo.ITEMS AS I_SUB4 ON B.ITEM_ID = I_SUB4.ITEM_ID
    LEFT JOIN dbo.CURR_RAT AS CR_S ON CR_S.CURR_CODE = 'USD' 
        AND CR_S.CURR_MM = MONTH(DI.DI_DATE) 
        AND CR_S.CURR_YY = YEAR(DI.DI_DATE)
    WHERE (DI.DI_DATE BETWEEN ? AND DATEADD(DD, -1, ?)) AND (I_SUB4.ITEM_CODE LIKE '%-0')
    GROUP BY B.ITEM_ID
) AS DATA_SALES ON DATA_SALES.MAT_ID = COALESCE(DATA_TAGS.ITEM_ID, DATA_RECEIVE.ITEM_ID, DATA_TAGS1.ITEM_ID)

-- Join Utama ke Master Item
INNER JOIN dbo.ITEMS AS I ON I.ITEM_ID = COALESCE(DATA_TAGS.ITEM_ID, DATA_RECEIVE.ITEM_ID, DATA_TAGS1.ITEM_ID) 
INNER JOIN dbo.ITTY ON dbo.ITTY.ITTY_CODE = I.ITTY_CODE

-- JURUS KURS: Mengambil master kurs USD bulan aktif laporan
LEFT JOIN dbo.CURR_RAT AS CR ON CR.CURR_CODE = 'USD' AND CR.CURR_MM = ? AND CR.CURR_YY = ?

-- Pengaman Harga Cadangan Sejarah Maksimal 1 Tahun ke belakang (Dioptimasi range agar bebas scan lemot)
OUTER APPLY (
    SELECT TOP 1 RD_B.POD_PRICE AS LAST_PRICE, P_B.PO_CUR
    FROM dbo.RECEIVE AS R_B
    INNER JOIN dbo.RECEIVE_DETAIL AS RD_B ON R_B.RCV_ID = RD_B.RCV_ID
    INNER JOIN dbo.PO AS P_B ON RD_B.PO_ID = P_B.PO_ID
    WHERE RD_B.ITEM_ID = COALESCE(DATA_TAGS.ITEM_ID, DATA_RECEIVE.ITEM_ID, DATA_TAGS1.ITEM_ID)
      AND R_B.RCV_DATE >= DATEADD(year, -1, ?)
) AS PRICE_BACKUP

WHERE (dbo.ITTY.ITTY_CODE IN ('02', '03', '05'))
  AND (I.ITEM_CODE LIKE '%-0') 

ORDER BY I.ITEM_CODE;
";

// 6. Jalankan query memakai fungsi q() bawaan dari config database Anda
$stmt = q($sql, $params);

// Akumulator Grand Total Finansial Amount (IDR)
$gtotal_awal_idr   = 0;
$gtotal_in_idr     = 0;
$gtotal_akhir_idr  = 0;
$gtotal_cons_idr   = 0;
$gtotal_sales_idr  = 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Finansial Material IDR - Plant <?php echo strtoupper($active_plant); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f9f9f9; }
        .filter-box { background: #fff; padding: 15px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .formula-box { background-color: #eef2f7; border-left: 5px solid #28a745; padding: 12px 20px; margin-bottom: 20px; border-radius: 4px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #ddd; padding: 8px; font-size: 12px; }
        th { background-color: #2c3e50; color: white; white-space: nowrap; text-align: center; vertical-align: middle; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .tfoot-total { background-color: #e9ecef !important; font-weight: bold; }
    </style>
</head>
<body>

    <h2>Laporan Analisis Finansial Material Amount Berbasis IDR (Rupiah)</h2>
    
    <div class="formula-box">
        <strong><i class="bi bi-currency-exchange"></i> Sistem Perhitungan Nilai Uang (Amount IDR):</strong><br>
        <code>Rasio Pemakaian (%) = ((Amount Awal IDR + Amount In IDR - Amount Akhir IDR) / Amount Sales IDR Bawaan DIPA_PAR) &times; 100%</code>
    </div>

    <div class="filter-box">
        <form method="GET" action="">
            <label>Periode Laporan: </label>
            <input type="date" name="from_date" value="<?php echo $from_date; ?>"> s/d
            <input type="date" name="to_date" value="<?php echo $to_date; ?>">
            <button type="submit" style="padding: 5px 15px; background: #28a745; color: #fff; border: none; border-radius: 3px; cursor: pointer;">Hitung &amp; Konversi IDR</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Kode Barang</th>
                <th>Nama Komponen</th>
                <th>Ori Mat</th>
                <th>Ori Sls</th>
                <th>Harga Satuan (IDR)</th>
                <th>Amount Awal (IDR)</th>
                <th>Amount In (IDR)</th>
                <th>Amount Akhir (IDR)</th>
                <th>Amount Konsumsi (IDR)</th>
                <th>Amount Sales (IDR)</th>
                <th>Amount Rate (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $has_data = false;
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): 
                $has_data = true;

                $gtotal_awal_idr  += $row['AMOUNT_STOK_AWAL_IDR'];
                $gtotal_in_idr    += $row['AMOUNT_IN_IDR'];
                $gtotal_akhir_idr += $row['AMOUNT_STOK_AKHIR_IDR'];
                $gtotal_cons_idr  += $row['AMOUNT_AKTUAL_KONSUMSI_IDR'];
                $gtotal_sales_idr += $row['AMOUNT_SALES_DELIVERY_IDR'];
            ?>
                <tr>
                    <td style="font-weight: bold;"><?php echo $row['ITEM_CODE']; ?></td>
                    <td><?php echo $row['ITEM_NAME']; ?></td>
                    <td class="text-center" style="font-weight: bold; color: #666;"><?php echo $row['MAT_ORIGINAL_CUR']; ?></td>
                    <td class="text-center" style="font-weight: bold; color: #666;"><?php echo $row['SALES_ORIGINAL_CUR']; ?></td>
                    <td class="text-right" style="color:#856404; font-weight: bold;">Rp <?php echo number_format($row['PRICE_IN_IDR'], 2, ',', '.'); ?></td>
                    <td class="text-right">Rp <?php echo number_format($row['AMOUNT_STOK_AWAL_IDR'], 2, ',', '.'); ?></td>
                    <td class="text-right" style="color: #28a745;">Rp <?php echo number_format($row['AMOUNT_IN_IDR'], 2, ',', '.'); ?></td>
                    <td class="text-right">Rp <?php echo number_format($row['AMOUNT_STOK_AKHIR_IDR'], 2, ',', '.'); ?></td>
                    <td class="text-right" style="font-weight: bold;">Rp <?php echo number_format($row['AMOUNT_AKTUAL_KONSUMSI_IDR'], 2, ',', '.'); ?></td>
                    <td class="text-right" style="color: #0056b3;">Rp <?php echo number_format($row['AMOUNT_SALES_DELIVERY_IDR'], 2, ',', '.'); ?></td>
                    <td class="text-right" style="font-weight: bold; color: #d9534f;"><?php echo number_format($row['CONSUMPTION_AMOUNT_PCT'], 4, ',', '.') . ' %'; ?></td>
                </tr>
            <?php 
            endwhile; 
            
            if ($has_data): 
                $gtotal_pct = ($gtotal_sales_idr > 0) ? ($gtotal_cons_idr / $gtotal_sales_idr) * 100.0 : 0;
            ?>
                <tr class="tfoot-total">
                    <td colspan="4" style="text-align: center;">GRAND TOTAL GABUNGAN AMOUNT (IDR)</td>
                    <td class="text-right">Kurs: Rp <?php echo number_format(isset($row['USD_EXCHANGE_RATE']) ? $row['USD_EXCHANGE_RATE'] : 0, 2, ',', '.'); ?></td>
                    <td class="text-right">Rp <?php echo number_format($gtotal_awal_idr, 2, ',', '.'); ?></td>
                    <td class="text-right" style="color: #28a745;">Rp <?php echo number_format($gtotal_in_idr, 2, ',', '.'); ?></td>
                    <td class="text-right">Rp <?php echo number_format($gtotal_akhir_idr, 2, ',', '.'); ?></td>
                    <td class="text-right">Rp <?php echo number_format($gtotal_cons_idr, 2, ',', '.'); ?></td>
                    <td class="text-right" style="color: #0056b3;">Rp <?php echo number_format($gtotal_sales_idr, 2, ',', '.'); ?></td>
                    <td class="text-right" style="color: #d9534f; font-size: 13px;"><?php echo number_format($gtotal_pct, 4, ',', '.') . ' %'; ?></td>
                </tr>
            <?php else: ?>
                <tr><td colspan="11" style="text-align: center; color: #999; padding: 20px;">Tidak ditemukan data mutasi amount pada periode filter ini.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>