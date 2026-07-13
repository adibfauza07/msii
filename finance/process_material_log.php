<?php
// 1. Pengaturan Dasar
set_time_limit(0); 
require_once "../config/database_aging.php";

$message = "";
$message_type = "";
$import_details = "";

// 2. Jika Tombol Import Ditekan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_import'])) {
    
    $selected_year  = $_POST['year'];
    $months_to_process = [];
    
    if ($_POST['month'] === 'ALL') {
        for ($i = 1; $i <= 12; $i++) {
            $months_to_process[] = $i;
        }
    } else {
        $months_to_process[] = (int)$_POST['month'];
    }

    $import_details = "<div style='max-height: 200px; overflow-y: auto; text-align: left; background: #fff; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;'><ul style='margin-bottom: 0;'>";
    $success_count = 0;

    foreach ($months_to_process as $m) {
        $selected_month = str_pad($m, 2, '0', STR_PAD_LEFT);
        $from_date      = "$selected_year-$selected_month-01";
        $next_month_1st = date('Y-m-01', strtotime('+1 month', strtotime($from_date)));
        
        $curr_mm = (int)$selected_month;
        $curr_yy = (int)$selected_year;

        // ==============================================================================
        // TAHAP 1: AMBIL 1 NILAI KURS TERBARU DI BULAN TERSEBUT DARI PHP
        // ==============================================================================
        $rate_usd = 16200; // Default fallback jika bulan tsb belum ada inputan
        $sql_kurs = "SELECT TOP 1 CURR_RP FROM dbo.CURR_RAT_TALLY 
                     WHERE CURR_CODE = 'USD' AND CURR_MM = ? AND CURR_YY = ? 
                     ORDER BY CURR_SDATE DESC"; // Ambil tanggal update terakhir
        $stmt_kurs = q($sql_kurs, [$curr_mm, $curr_yy]);
        if ($stmt_kurs && $row_kurs = sqlsrv_fetch_array($stmt_kurs, SQLSRV_FETCH_ASSOC)) {
            if (!empty($row_kurs['CURR_RP']) && $row_kurs['CURR_RP'] > 0) {
                $rate_usd = (float)$row_kurs['CURR_RP'];
            }
        }

        // ==============================================================================
        // TAHAP 2: QUERY MATERIAL (SUDAH ANTI-DUPLIKASI / TANPA JOIN TABEL KURS)
        // ==============================================================================
        $sql_material = "
        SELECT 
            SUM(ISNULL(DATA_TAGS.STOK_AWAL, 0) * CASE WHEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.PO_CUR ELSE PRICE_BACKUP.PO_CUR END, 'IDR') = 'USD' 
                     THEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0)
                     ELSE ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0) / ? 
                END) AS GRAND_AWAL,
                
            SUM(ISNULL(DATA_RECEIVE.TOTAL_IN, 0) * CASE WHEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.PO_CUR ELSE PRICE_BACKUP.PO_CUR END, 'IDR') = 'USD' 
                     THEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0)
                     ELSE ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0) / ? 
                END) AS GRAND_MASUK,
                
            SUM(ISNULL(DATA_TAGS1.STOK_AKHIR, 0) * CASE WHEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.PO_CUR ELSE PRICE_BACKUP.PO_CUR END, 'IDR') = 'USD' 
                     THEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0)
                     ELSE ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0) / ? 
                END) AS GRAND_AKHIR,

            SUM((ISNULL(DATA_TAGS.STOK_AWAL, 0) + ISNULL(DATA_RECEIVE.TOTAL_IN, 0) - ISNULL(DATA_TAGS1.STOK_AKHIR, 0)) * CASE WHEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.PO_CUR ELSE PRICE_BACKUP.PO_CUR END, 'IDR') = 'USD' 
                     THEN ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0)
                     ELSE ISNULL(CASE WHEN DATA_RECEIVE.TOTAL_IN > 0 THEN DATA_RECEIVE.AVG_PRICE ELSE PRICE_BACKUP.LAST_PRICE END, 0) / ? 
                END) AS GRAND_KONSUMSI

        FROM (
            SELECT T.ITEM_ID, SUM(T.TAG_QTY) AS STOK_AWAL 
            FROM dbo.TAGS AS T 
            INNER JOIN dbo.SOP AS S ON T.SOP_ID = S.SOP_ID 
            INNER JOIN dbo.ITEMS AS I_SUB1 ON T.ITEM_ID = I_SUB1.ITEM_ID 
            WHERE (CAST(S.SOP_SDATE AS DATE) = ?) AND (I_SUB1.ITEM_CODE LIKE '%-0') 
            GROUP BY T.ITEM_ID
        ) AS DATA_TAGS 
        FULL OUTER JOIN (
            SELECT RD.ITEM_ID, SUM(RD.RCVD_QTY) AS TOTAL_IN, MAX(P.PO_CUR) AS PO_CUR, CASE WHEN SUM(RD.RCVD_QTY) = 0 THEN 0 ELSE SUM(RD.RCVD_QTY * RD.POD_PRICE) / SUM(RD.RCVD_QTY) END AS AVG_PRICE
            FROM dbo.RECEIVE AS R 
            INNER JOIN dbo.RECEIVE_DETAIL AS RD ON R.RCV_ID = RD.RCV_ID 
            INNER JOIN dbo.PO AS P ON RD.PO_ID = P.PO_ID 
            INNER JOIN dbo.ITEMS AS I_SUB2 ON RD.ITEM_ID = I_SUB2.ITEM_ID
            WHERE (R.RCV_DATE BETWEEN ? AND DATEADD(DD, -1, ?)) AND (I_SUB2.ITEM_CODE LIKE '%-0') 
            GROUP BY RD.ITEM_ID
        ) AS DATA_RECEIVE ON DATA_TAGS.ITEM_ID = DATA_RECEIVE.ITEM_ID 
        FULL OUTER JOIN (
            SELECT T.ITEM_ID, SUM(T.TAG_QTY) AS STOK_AKHIR 
            FROM dbo.TAGS AS T 
            INNER JOIN dbo.SOP AS S ON T.SOP_ID = S.SOP_ID 
            INNER JOIN dbo.ITEMS AS I_SUB3 ON T.ITEM_ID = I_SUB3.ITEM_ID 
            WHERE (CAST(S.SOP_SDATE AS DATE) = ?) AND (I_SUB3.ITEM_CODE LIKE '%-0') 
            GROUP BY T.ITEM_ID
        ) AS DATA_TAGS1 ON DATA_TAGS1.ITEM_ID = COALESCE(DATA_TAGS.ITEM_ID, DATA_RECEIVE.ITEM_ID) 
        INNER JOIN dbo.ITEMS AS I ON I.ITEM_ID = COALESCE(DATA_TAGS.ITEM_ID, DATA_RECEIVE.ITEM_ID, DATA_TAGS1.ITEM_ID) 
        INNER JOIN dbo.ITTY ON dbo.ITTY.ITTY_CODE = I.ITTY_CODE
        OUTER APPLY (
            SELECT TOP 1 RD_B.POD_PRICE AS LAST_PRICE, P_B.PO_CUR 
            FROM dbo.RECEIVE AS R_B 
            INNER JOIN dbo.RECEIVE_DETAIL AS RD_B ON R_B.RCV_ID = RD_B.RCV_ID 
            INNER JOIN dbo.PO AS P_B ON RD_B.PO_ID = P_B.PO_ID
            WHERE RD_B.ITEM_ID = COALESCE(DATA_TAGS.ITEM_ID, DATA_RECEIVE.ITEM_ID, DATA_TAGS1.ITEM_ID) AND R_B.RCV_DATE >= DATEADD(year, -1, ?)
        ) AS PRICE_BACKUP
        WHERE (dbo.ITTY.ITTY_CODE IN ('02', '03', '05')) AND (I.ITEM_CODE LIKE '%-0');
        ";

        // Mengirimkan $rate_usd yang diambil di Tahap 1 langsung ke perhitungan Material
        $params_mat = [$rate_usd, $rate_usd, $rate_usd, $rate_usd, $from_date, $from_date, $next_month_1st, $next_month_1st, $from_date];
        $stmt_mat = q($sql_material, $params_mat);

        $g_awal = 0; $g_masuk = 0; $g_akhir = 0; $g_konsumsi = 0;
        if ($stmt_mat && $row_mat = sqlsrv_fetch_array($stmt_mat, SQLSRV_FETCH_ASSOC)) {
            $g_awal     = (float)$row_mat['GRAND_AWAL'];
            $g_masuk    = (float)$row_mat['GRAND_MASUK'];
            $g_akhir    = (float)$row_mat['GRAND_AKHIR'];
            $g_konsumsi = (float)$row_mat['GRAND_KONSUMSI'];
        }

        // ==============================================================================
        // TAHAP 3: QUERY SALES (JUGA MENGGUNAKAN $rate_usd DARI PHP)
        // ==============================================================================
        $sql_sales = "
        SELECT SUM(
            CASE WHEN ISNULL(dbo.DIPA_PAR.CURR, 'IDR') = 'USD' 
                 THEN (dbo.DIPA_PAR.QTY * dbo.DIPA_PAR.PART_PRICE) 
                 ELSE (dbo.DIPA_PAR.QTY * dbo.DIPA_PAR.PART_PRICE) / ? 
            END
        ) AS GRAND_SALES
        FROM dbo.DI 
        INNER JOIN dbo.DIPA_PAR ON dbo.DI.DI_ID = dbo.DIPA_PAR.DI_ID 
        WHERE (dbo.DI.DI_DATE BETWEEN ? AND DATEADD(DD, -1, ?));
        ";

        // Mengirimkan $rate_usd ke perhitungan Sales
        $stmt_sales = q($sql_sales, [$rate_usd, $from_date, $next_month_1st]);
        $g_sales = ($stmt_sales && $row_sales = sqlsrv_fetch_array($stmt_sales, SQLSRV_FETCH_ASSOC)) ? (float)$row_sales['GRAND_SALES'] : 0;

        // ==============================================================================
        // TAHAP 4: INSERT / UPDATE LOG
        // ==============================================================================
        $sql_check = "SELECT COUNT(*) as JML FROM dbo.T_MATERIAL_FINANCIAL_LOG WHERE BULAN = ?";
        $stmt_check = q($sql_check, [$from_date]);
        $row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

        if ($row_check['JML'] > 0) {
            $sql_import = "UPDATE dbo.T_MATERIAL_FINANCIAL_LOG 
                           SET AMOUNT_AWAL = ?, AMOUNT_MASUK = ?, AMOUNT_AKHIR = ?, AMOUNT_KONSUMSI = ?, SALES_AMOUNT_IDR = ?, CREATED_AT = GETDATE() 
                           WHERE BULAN = ?";
            q($sql_import, [$g_awal, $g_masuk, $g_akhir, $g_konsumsi, $g_sales, $from_date]);
        } else {
            $sql_import = "INSERT INTO dbo.T_MATERIAL_FINANCIAL_LOG (BULAN, AMOUNT_AWAL, AMOUNT_MASUK, AMOUNT_AKHIR, AMOUNT_KONSUMSI, SALES_AMOUNT_IDR, CREATED_AT) 
                           VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
            q($sql_import, [$from_date, $g_awal, $g_masuk, $g_akhir, $g_konsumsi, $g_sales]);
        }
        
        $success_count++;
        $nama_bulan = date('M Y', strtotime($from_date));
        $import_details .= "<li><b>$nama_bulan (Kurs: Rp " . number_format($rate_usd, 0, ',', '.') . "):</b> Konsumsi $ " . number_format($g_konsumsi, 2, '.', ',') . " | Sales $ " . number_format($g_sales, 2, '.', ',') . "</li>";
    }
    
    $import_details .= "</ul></div>";
    
    $message_type = "success";
    if (count($months_to_process) > 1) {
        $message = "Sukses! Berhasil memproses data <b>$success_count bulan penuh</b> untuk tahun <b>$selected_year</b>.";
    } else {
        $message = "Sukses! Data bulan <b>" . date('F Y', strtotime("$selected_year-" . str_pad($months_to_process[0], 2, '0', STR_PAD_LEFT) . "-01")) . "</b> berhasil diproses.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Import Data Material Log</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; margin: 40px; }
        .card { background: #fff; max-width: 500px; margin: auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; }
        .form-group { margin-bottom: 20px; text-align: left; }
        label { display: block; font-weight: bold; margin-bottom: 8px; color: #333; }
        select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 16px; }
        button { width: 100%; padding: 12px; background: #007bff; color: #fff; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        button:hover { background: #0056b3; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid transparent; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
    </style>
</head>
<body>

<div class="card">
    <h2 style="margin-top: 0;">Import Data Financial Log</h2>
    <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Tarik data mutasi dan sales (dikonversi ke <b>USD</b>) per bulan ke tabel log.</p>

    <?php if ($message != ""): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <p style="margin-top:0; font-weight:bold;"><?php echo $message; ?></p>
            <?php echo $import_details; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Pilih Bulan</label>
            <select name="month" required>
                <option value="ALL" style="font-weight: bold; color: #007bff;">✅ Tarik Semua Bulan (1 Tahun Penuh)</option>
                <optgroup label="Atau Pilih Per Bulan:">
                    <?php
                    $months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
                    $current_m = date('n');
                    foreach ($months as $index => $nama) {
                        $m_val = $index + 1;
                        $selected = ($m_val == $current_m) ? "selected" : "";
                        echo "<option value='$m_val' $selected>$nama</option>";
                    }
                    ?>
                </optgroup>
            </select>
        </div>

        <div class="form-group">
            <label>Pilih Tahun</label>
            <select name="year" required>
                <?php
                $current_y = date('Y');
                for ($y = $current_y - 5; $y <= $current_y; $y++) {
                    $selected = ($y == $current_y) ? "selected" : "";
                    echo "<option value='$y' $selected>$y</option>";
                }
                ?>
            </select>
        </div>

        <button type="submit" name="btn_import">Proses & Import Data</button>
    </form>
    
    <div style="margin-top: 25px; border-top: 1px solid #eee; padding-top: 15px;">
        <a href="dashboard_grafik_log.php" style="color: #28a745; text-decoration: none; font-weight: bold;">📊 Lihat Laporan Grafik</a>
    </div>
</div>

</body>
</html>