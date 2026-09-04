<?php
// Panggil file config yang sudah Anda buat
require_once __DIR__ . "/../config/database_ppic.php";

// Pastikan koneksi berhasil
if ($conn === false) {
    die("Koneksi database gagal atau sesi telah berakhir. Silakan login kembali.");
}

// Cek apakah tombol export ke excel diklik
$is_export = isset($_POST['export']) && $_POST['export'] === 'excel';

// Inisialisasi variabel (Default awal bulan s/d akhir bulan)
$from_date = isset($_POST['from_date']) ? $_POST['from_date'] : date('Y-m-01');
$to_date   = isset($_POST['to_date']) ? $_POST['to_date'] : date('Y-m-t');
$cust_code = isset($_POST['cust_code']) ? $_POST['cust_code'] : '';
$is_dead_stock = isset($_POST['is_dead_stock']) ? $_POST['is_dead_stock'] : '';

$groupedResults = array();
$message   = "";

// =========================================================
// 1. PROSES AMBIL & FILTER DATA
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $cust_code_param = empty($cust_code) ? '%' : $cust_code;
    $fDate = $from_date . ' 00:00:00';
    $tDate = $to_date . ' 23:59:59';

    // Panggil Stored Procedure utama
    $tsql = "EXEC [msdata].[dbo].[sp_stok_actual] @from_date = ?, @to_date = ?, @cust_code = ?";
    $params = array(
        array($fDate, SQLSRV_PARAM_IN),
        array($tDate, SQLSRV_PARAM_IN),
        array($cust_code_param, SQLSRV_PARAM_IN)
    );

    $stmt = sqlsrv_query($conn, $tsql, $params);

    if ($stmt === false) {
        $message = "Error eksekusi Stored Procedure: " . print_r(sqlsrv_errors(), true);
    } else {
        $rawResults = array();
        do {
            if (sqlsrv_num_fields($stmt) > 0) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $rawResults[] = $row;
                }
            }
        } while (sqlsrv_next_result($stmt));
        sqlsrv_free_stmt($stmt);

        // =========================================================
        // LOGIKA FILTER DEAD STOCK (> 2 TAHUN) DARI 'TO DATE'
        // =========================================================
        if ($is_dead_stock === '1' && !empty($rawResults)) {
            $filteredResults = array();
            
            // Perbaikan: Ambil batas 2 tahun mundur dari tanggal "To Date" yang dipilih UI
            $to_date_timestamp = strtotime($tDate);
            $two_years_ago = strtotime('-2 years', $to_date_timestamp); 

            foreach ($rawResults as $row) {
                $item_code = trim($row['item_code']);
                
                $tags = isset($row['tags']) ? (float)$row['tags'] : 0;
                $pd_actual = isset($row['pd_actual']) ? (float)$row['pd_actual'] : 0;
                $sub_qty = isset($row['sub_qty']) ? (float)$row['sub_qty'] : 0;
                $del_actual = isset($row['del_actual']) ? (float)$row['del_actual'] : 0;
                $ng_rw = isset($row['ng_rw']) ? (float)$row['ng_rw'] : 0;
                
                $stok_actual = ($tags + $pd_actual + $sub_qty) - ($del_actual + $ng_rw);

                // Filter 1: Pastikan barang ini memang masih ada stok fisiknya
                if ($stok_actual > 0) {
                    
                    $tsql_trx = "
                        SELECT MAX(LastDate) as LastTransDate
                        FROM (
                            -- Cek Transaksi Keluar (Delivery)
                            SELECT MAX(d.DELS_DATE) AS LastDate
                            FROM dbo.DELI_SCH d
                            INNER JOIN dbo.PRICE pr ON d.PRICE_ID = pr.PRICE_ID
                            INNER JOIN dbo.ITEMS i ON pr.PART_ID = i.ITEM_ID
                            WHERE i.ITEM_CODE = ? AND ISDATE(d.DELS_DATE) = 1
                        ) as T
                    ";
                    
                    $stmt_trx = sqlsrv_query($conn, $tsql_trx, array($item_code)); 
                    $last_date_str = null;

                    if ($stmt_trx !== false) {
                        $trx_row = sqlsrv_fetch_array($stmt_trx, SQLSRV_FETCH_ASSOC);
                        if ($trx_row && $trx_row['LastTransDate'] !== null) {
                            $last_trans_date = $trx_row['LastTransDate'];
                            $last_date_str = is_object($last_trans_date) ? $last_trans_date->format('Y-m-d') : $last_trans_date;
                        }
                        sqlsrv_free_stmt($stmt_trx);
                    }

                    // Keputusan: Jika tanggal terakhir kosong ATAU kurang dari 2 tahun lalu
                    if ($last_date_str == null || strtotime($last_date_str) < $two_years_ago) {
                        $row['last_trans_date'] = $last_date_str ? date('d-M-Y', strtotime($last_date_str)) : 'Tidak Ada Trx';
                        $filteredResults[] = $row;
                    }
                }
            }
            $rawResults = $filteredResults;
            
            if (empty($rawResults)) {
                $message = "Tidak ditemukan Dead Stock untuk filter yang dipilih.";
            }
        }

        // =========================================================
        // GROUPING & SORTING DATA BERDASARKAN CUSTOMER
        // =========================================================
        if (!empty($rawResults)) {
            foreach ($rawResults as $row) {
                $cCode = $row['CUST_CODE'];
                if (!isset($groupedResults[$cCode])) {
                    $groupedResults[$cCode] = array(
                        'CUST_COMP' => $row['CUST_COMP'],
                        'items' => array()
                    );
                }
                $groupedResults[$cCode]['items'][] = $row;
            }

            foreach ($groupedResults as $cCode => &$custData) {
                usort($custData['items'], function($a, $b) {
                    return strcasecmp(trim($a['item_name']), trim($b['item_name']));
                });
            }
            unset($custData); 

        } else if (empty($message)) {
            $message = "Tidak ada data ditemukan untuk filter tersebut.";
        }
    }
}

// =========================================================
// 2. LOGIKA KHUSUS EXPORT EXCEL (NATIVE HTML-EXCEL)
// =========================================================
if ($is_export && !empty($groupedResults)) {
    $filename = "Analisa_Stok_Aktual_" . date('Ymd_His') . ".xls";
    
    // Cegah file korup akibat whitespace tak sengaja
    if (ob_get_length()) ob_clean();

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Cache-Control: max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <style>
                table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 10pt; }
                th, td { border: 0.5pt solid #000000; padding: 5px; }
                th { background-color: #d9e1f2; font-weight: bold; text-align: center; }
                .num { text-align: right; }
                .hdr-cust { background-color: #f2f2f2; font-weight: bold; font-size: 11pt; text-align: left; }
                .calc-col { background-color: #fffde7; font-weight: bold; }
                .danger { color: #d32f2f; font-weight: bold; }
                .safe { color: #2e7d32; font-weight: bold; }
            </style>
          </head><body>';

    echo '<h2>Laporan Analisa Stok Aktual</h2>';
    echo '<p>Periode: ' . date('d-M-Y', strtotime($from_date)) . ' s/d ' . date('d-M-Y', strtotime($to_date)) . '</p>';
    if ($is_dead_stock === '1') {
        echo '<p style="color: red; font-weight: bold;">Filter Aktif: Menampilkan Hanya Dead Stock (> 2 Tahun)</p>';
    }

    foreach ($groupedResults as $custCode => $custData) {
        $total_cols = ($is_dead_stock === '1') ? 18 : 17;
        echo '<table><thead><tr>';
        echo '<td colspan="' . $total_cols . '" class="hdr-cust">' . htmlspecialchars($custCode) . ' &nbsp;&nbsp;&nbsp; ' . htmlspecialchars($custData['CUST_COMP']) . '</td>';
        echo '</tr><tr>
                <th>No</th><th>Item Code</th><th>Item Name</th><th>Tags</th><th>Prod Sch</th>
                <th>Prod Actual</th><th>Del Sch</th><th>Del Actual</th><th>Delivery Balance</th>
                <th>NG / RW</th><th>Sub Qty</th><th>Forecast</th><th>Stok Plan</th>
                <th>Stok Actual</th><th>Prod Plan next</th>
                <th style="background-color: #ffcccc;">Shortage Date</th>
                <th style="background-color: #e2efda;">Next Prod (-7 Hari)</th>';
        if ($is_dead_stock === '1') {
            echo '<th style="background-color: #ffd966;">Last Trans Date</th>';
        }
        echo '</tr></thead><tbody>';

        $no = 1;
        foreach ($custData['items'] as $row) {
            $tags = isset($row['tags']) ? (float)$row['tags'] : 0;
            $ps = isset($row['ps']) ? (float)$row['ps'] : 0;
            $pd_actual = isset($row['pd_actual']) ? (float)$row['pd_actual'] : 0;
            $del_sch = isset($row['del_sch']) ? (float)$row['del_sch'] : 0;
            $del_actual = isset($row['del_actual']) ? (float)$row['del_actual'] : 0;
            $ng_rw = isset($row['ng_rw']) ? (float)$row['ng_rw'] : 0;
            $sub_qty = isset($row['sub_qty']) ? (float)$row['sub_qty'] : 0;
            $fore_qty = isset($row['fore_qty']) ? (float)$row['fore_qty'] : 0;

            $stok_plan = ($tags + $ps + $sub_qty) - $del_sch - $ng_rw;
            $stok_actual = ($tags + $pd_actual + $sub_qty) - ($del_actual + $ng_rw);
            $del_balance = $del_actual - $del_sch;
            $prod_plan = $stok_plan - $fore_qty;

            // Kalkulasi Shortage Date Khusus Excel
            $shortage_date = "-";
            $next_prod_date = "-";
            $item_code = trim($row['item_code']);
            $days_in_period = (strtotime($to_date) - strtotime($from_date)) / 86400 + 1;

            if ($stok_plan < 0) {
                $shortage_date = date('d-M-Y', strtotime($from_date));
                $next_prod_date = date('d-M-Y', strtotime('-7 days', strtotime($from_date)));
            } else {
                $saldo_harian = $stok_plan; 
                $tsql_harian = "SELECT d.DELS_DATE as tgl, ISNULL(d.DELS_QTY, 0) as del_qty FROM dbo.DELI_SCH d
                                INNER JOIN dbo.PRICE pr ON d.PRICE_ID = pr.PRICE_ID INNER JOIN dbo.ITEMS i ON pr.PART_ID = i.ITEM_ID
                                WHERE i.ITEM_CODE = ? AND ISDATE(d.DELS_DATE) = 1 AND d.DELS_DATE >= ? AND d.DELS_DATE <= ?
                                ORDER BY d.DELS_DATE ASC";
                $stmt_harian = sqlsrv_query($conn, $tsql_harian, array($item_code, $from_date, $to_date));
                if ($stmt_harian !== false) {
                    $found_shortage = false;
                    $has_daily_data = false; 
                    while ($h = sqlsrv_fetch_array($stmt_harian, SQLSRV_FETCH_ASSOC)) {
                        $has_daily_data = true;
                        $saldo_harian -= (float)$h['del_qty'];
                        if ($saldo_harian < 0) {
                            $raw_date = $h['tgl'];
                            $timestamp = ($raw_date instanceof DateTime) ? $raw_date->getTimestamp() : strtotime($raw_date);
                            $shortage_date = date('d-M-Y', $timestamp);
                            $next_prod_date = date('d-M-Y', strtotime('-7 days', $timestamp));
                            $found_shortage = true;
                            break; 
                        }
                    }
                    sqlsrv_free_stmt($stmt_harian);

                    if (!$found_shortage) {
                        if (!$has_daily_data && $del_sch > 0 && $stok_plan < $del_sch) {
                            $avg_daily_del = $del_sch / $days_in_period;
                            if ($avg_daily_del > 0) {
                                $shortage_ts = strtotime($from_date . " +" . (int)ceil($stok_plan / $avg_daily_del) . " days");
                                $shortage_date = date('d-M-Y', $shortage_ts);
                                $next_prod_date = date('d-M-Y', strtotime('-7 days', $shortage_ts));
                            } else {
                                $shortage_date = date('d-M-Y', strtotime($from_date));
                                $next_prod_date = date('d-M-Y', strtotime('-7 days', strtotime($from_date)));
                            }
                        } else {
                            $shortage_date = "Aman";
                            $next_prod_date = "Aman";
                        }
                    }
                } else {
                    $shortage_date = "Error DB";
                }
            }

            echo '<tr>';
            echo '<td align="center">' . $no++ . '</td>';
            // MSO Number format memastikan item code seperti 012613-0 tidak dirubah formatnya oleh Excel
            echo '<td style="mso-number-format:\'@\';">' . htmlspecialchars($item_code) . '</td>';
            echo '<td>' . htmlspecialchars(trim($row['item_name'])) . '</td>';
            echo '<td class="num">' . $tags . '</td>';
            echo '<td class="num">' . $ps . '</td>';
            echo '<td class="num">' . $pd_actual . '</td>';
            echo '<td class="num">' . $del_sch . '</td>';
            echo '<td class="num">' . $del_actual . '</td>';
            echo '<td class="num calc-col">' . $del_balance . '</td>';
            echo '<td class="num">' . $ng_rw . '</td>';
            echo '<td class="num">' . $sub_qty . '</td>';
            echo '<td class="num">' . $fore_qty . '</td>';
            echo '<td class="num calc-col">' . $stok_plan . '</td>';
            echo '<td class="num calc-col">' . $stok_actual . '</td>';
            echo '<td class="num calc-col">' . $prod_plan . '</td>';
            
            $sc_class = ($shortage_date === 'Aman') ? 'safe' : 'danger';
            echo '<td align="center" class="' . $sc_class . '">' . htmlspecialchars($shortage_date) . '</td>';
            echo '<td align="center" class="' . $sc_class . '">' . htmlspecialchars($next_prod_date) . '</td>';
            
            if ($is_dead_stock === '1') {
                echo '<td align="center" class="danger">' . htmlspecialchars($row['last_trans_date']) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table><br><br>';
    }
    echo '</body></html>';
    exit; // Stop proses agar HTML Web UI tidak ikut masuk ke file Excel
}

// Fungsi bantu format angka UI (Web HTML)
function formatNum($val) {
    $num = (float)$val;
    $formatted = number_format($num);
    return ($num < 0) ? '<span style="color: red;">' . $formatted . '</span>' : $formatted;
}
?>

<!-- ========================================================= -->
<!-- 3. LOGIKA TAMPILAN WEB HTML BROWSER -->
<!-- ========================================================= -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisa Stok Aktual</title>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f7f6; font-size: 13px;}
        .container { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .report-header { text-align: center; margin-bottom: 20px; }
        .report-header h2 { margin: 0; text-transform: uppercase; }
        .report-header p { margin: 5px 0; }
        .form-group { margin-bottom: 15px; display: inline-block; margin-right: 15px; vertical-align: top; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="date"], input[type="text"] { padding: 8px; width: 180px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 8px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #0056b3; }
        .btn-excel { background: #28a745; }
        .btn-excel:hover { background: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 5px 8px; }
        th { background-color: #d9e1f2; font-weight: bold; text-align: center; }
        td { text-align: left; }
        td.text-right { text-align: right; }
        tr.group-header td { background-color: #f2f2f2; font-weight: bold; font-size: 14px; border-bottom: 2px solid #000; }
        .message { color: red; font-weight: bold; margin-top: 15px; text-align: center; }
        .ui-autocomplete { z-index: 1000; max-height: 200px; overflow-y: auto; }
        .calc-col { background-color: #fffde7; font-weight: bold; }
        .link-item { color: #0056b3; text-decoration: none; font-weight: bold; }
        .link-item:hover { text-decoration: underline; color: #003366; }
        .link-icon { font-size: 11px; color: #007bff; margin-left: 4px; }
    </style>
</head>
<body>

<div class="container">
    <div class="report-header">
        <h2>Analisa Stock Aktual</h2>
        <p>Periode: <?= date('d-M-Y', strtotime($from_date)) ?> s/d <?= date('d-M-Y', strtotime($to_date)) ?></p>
    </div>

    <form method="POST" action="">
        <div class="form-group">
            <label>From Date</label>
            <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" required>
        </div>
        <div class="form-group">
            <label>To Date</label>
            <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" required>
        </div>
        <div class="form-group">
            <label>Customer Code</label>
            <input type="text" id="cust_code" name="cust_code" value="<?= htmlspecialchars($cust_code) ?>" placeholder="Ketik kode/nama customer">
        </div>
        <div class="form-group" style="margin-top: 25px;">
            <label style="display: inline-flex; align-items: center; cursor: pointer; font-weight: normal; color: #cc0000; font-weight: bold;">
                <input type="checkbox" name="is_dead_stock" value="1" <?= $is_dead_stock === '1' ? 'checked' : '' ?> style="width: 16px; margin-right: 5px; height: auto;">
                Tampilkan Hanya Dead Stock (> 2 Tahun)
            </label>
        </div>
        <div class="form-group">
            <button type="submit" style="margin-top: 22px;">Tampilkan Report</button>
            <button type="submit" name="export" value="excel" class="btn-excel" style="margin-top: 22px;">Export to Excel</button>
        </div>
    </form>
    <br>

    <?php if ($message): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <?php if (!empty($groupedResults)): ?>
        <?php foreach ($groupedResults as $custCode => $custData): ?>
            <table>
                <tr class="group-header">
                    <td colspan="<?= $is_dead_stock === '1' ? '18' : '17' ?>">
                        <?= htmlspecialchars($custCode) ?> &nbsp;&nbsp;&nbsp; <?= htmlspecialchars($custData['CUST_COMP']) ?>
                    </td>
                </tr>
                <thead>
                    <tr>
                        <th style="width: 20px;">No</th>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Tags</th>
                        <th>Prod Sch</th>
                        <th>Prod Actual</th>
                        <th>Del Sch</th>
                        <th>Del Actual</th>
                        <th>Delivery Balance</th>
                        <th>NG / RW</th>
                        <th>Sub Qty</th>
                        <th>Forecast</th>
                        <th>Stok Plan</th>
                        <th>Stok Actual</th>
                        <th>Prod Plan next</th>
                        <th style="background-color: #ffcccc;">Shortage Date</th>
                        <th style="background-color: #e2efda;">Next Prod (-7 Hari)</th>
                        <?php if ($is_dead_stock === '1'): ?>
                            <th style="background-color: #ffd966;">Last Trans Date</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1; 
                    foreach ($custData['items'] as $row): 
                        $tags = isset($row['tags']) ? (float)$row['tags'] : 0;
                        $ps = isset($row['ps']) ? (float)$row['ps'] : 0;
                        $pd_actual = isset($row['pd_actual']) ? (float)$row['pd_actual'] : 0;
                        $del_sch = isset($row['del_sch']) ? (float)$row['del_sch'] : 0;
                        $del_actual = isset($row['del_actual']) ? (float)$row['del_actual'] : 0;
                        $ng_rw = isset($row['ng_rw']) ? (float)$row['ng_rw'] : 0;
                        $sub_qty = isset($row['sub_qty']) ? (float)$row['sub_qty'] : 0;
                        $fore_qty = isset($row['fore_qty']) ? (float)$row['fore_qty'] : 0;

                        $stok_plan = ($tags + $ps + $sub_qty) - $del_sch - $ng_rw;
                        $stok_actual = ($tags + $pd_actual + $sub_qty) - ($del_actual + $ng_rw);
                        $del_balance = $del_actual - $del_sch;
                        $prod_plan = $stok_plan - $fore_qty;

                        // =========================================================
                        // LOGIKA SHORTAGE DATE WEB HTML
                        // =========================================================
                        $shortage_date = "-";
                        $next_prod_date = "-";
                        $item_code = trim($row['item_code']);
                        $param_start = $from_date;
                        $param_end   = $to_date;
                        $days_in_period = (strtotime($to_date) - strtotime($from_date)) / 86400 + 1;

                        if ($stok_plan < 0) {
                            $shortage_date = date('d-M-Y', strtotime($from_date));
                            $next_prod_date = date('d-M-Y', strtotime('-7 days', strtotime($from_date)));
                        } else {
                            $saldo_harian = $stok_plan; 
                            $tsql_harian = "
                                SELECT d.DELS_DATE as tgl, ISNULL(d.DELS_QTY, 0) as del_qty
                                FROM dbo.DELI_SCH d
                                INNER JOIN dbo.PRICE pr ON d.PRICE_ID = pr.PRICE_ID
                                INNER JOIN dbo.ITEMS i ON pr.PART_ID = i.ITEM_ID
                                WHERE i.ITEM_CODE = ? AND ISDATE(d.DELS_DATE) = 1
                                  AND d.DELS_DATE >= ? AND d.DELS_DATE <= ?
                                ORDER BY d.DELS_DATE ASC
                            ";

                            $params_harian = array($item_code, $param_start, $param_end);
                            $stmt_harian = sqlsrv_query($conn, $tsql_harian, $params_harian);

                            if ($stmt_harian !== false) {
                                $found_shortage = false;
                                $has_daily_data = false; 
                                
                                while ($h = sqlsrv_fetch_array($stmt_harian, SQLSRV_FETCH_ASSOC)) {
                                    $has_daily_data = true;
                                    $del_qty = (float)$h['del_qty'];
                                    $saldo_harian = $saldo_harian - $del_qty;
                                    
                                    if ($saldo_harian < 0) {
                                        $raw_date = $h['tgl'];
                                        $timestamp = ($raw_date instanceof DateTime) ? $raw_date->getTimestamp() : strtotime($raw_date);
                                        $shortage_date = date('d-M-Y', $timestamp);
                                        $next_prod_date = date('d-M-Y', strtotime('-7 days', $timestamp));
                                        $found_shortage = true;
                                        break; 
                                    }
                                }
                                sqlsrv_free_stmt($stmt_harian);

                                if (!$found_shortage) {
                                    if (!$has_daily_data && $del_sch > 0 && $stok_plan < $del_sch) {
                                        $avg_daily_del = $del_sch / $days_in_period;
                                        if ($avg_daily_del > 0) {
                                            $days_survive = (int)ceil($stok_plan / $avg_daily_del); 
                                            $shortage_ts = strtotime($from_date . " +{$days_survive} days");
                                            $shortage_date = date('d-M-Y', $shortage_ts);
                                            $next_prod_date = date('d-M-Y', strtotime('-7 days', $shortage_ts));
                                        } else {
                                            $shortage_date = date('d-M-Y', strtotime($from_date));
                                            $next_prod_date = date('d-M-Y', strtotime('-7 days', strtotime($from_date)));
                                        }
                                    } else {
                                        $shortage_date = "Aman";
                                        $next_prod_date = "Aman";
                                    }
                                }
                            } else {
                                $err = sqlsrv_errors();
                                $err_msg = $err ? $err[0]['message'] : "Unknown";
                                $shortage_date = "<span style='color:red;cursor:help;border-bottom:1px dashed red;' title='" . htmlspecialchars($err_msg, ENT_QUOTES) . "'>Error</span>";
                                $next_prod_date = "-"; 
                            }
                        }

                        $del_sch_link = "delivery_schedule.php?item_code=" . urlencode($item_code) . "&from_date=" . urlencode($from_date) . "&to_date=" . urlencode($to_date);
                        $item_code_display = "<a href='" . $del_sch_link . "' target='_blank' class='link-item' title='Lihat Delivery Schedule'>" . htmlspecialchars($item_code) . " <span class='link-icon'>📥</span></a>";
                    ?>
                        <tr>
                            <td style="text-align: center;"><?= $no++ ?></td>
                            <td><?= $item_code_display ?></td>
                            <td><?= htmlspecialchars($row['item_name']) ?></td>
                            <td class="text-right"><?= formatNum($tags) ?></td>
                            <td class="text-right"><?= formatNum($ps) ?></td>
                            <td class="text-right"><?= formatNum($pd_actual) ?></td>
                            <td class="text-right"><?= formatNum($del_sch) ?></td>
                            <td class="text-right"><?= formatNum($del_actual) ?></td>
                            <td class="text-right calc-col"><?= formatNum($del_balance) ?></td>
                            <td class="text-right"><?= formatNum($ng_rw) ?></td>
                            <td class="text-right"><?= formatNum($sub_qty) ?></td>
                            <td class="text-right"><?= formatNum($fore_qty) ?></td>
                            <td class="text-right calc-col"><?= formatNum($stok_plan) ?></td>
                            <td class="text-right calc-col"><?= formatNum($stok_actual) ?></td>
                            <td class="text-right calc-col"><?= formatNum($prod_plan) ?></td>
                            <td style="text-align: center; font-weight: bold; color: <?= ($shortage_date === 'Aman') ? 'green' : 'red' ?>;">
                                <?= $shortage_date ?>
                            </td>
                            <td style="text-align: center; font-weight: bold; color: <?= ($next_prod_date === 'Aman') ? 'green' : 'red' ?>;">
                                <?= $next_prod_date ?>
                            </td>
                            <?php if ($is_dead_stock === '1'): ?>
                                <td style="text-align: center; font-weight: bold; color: #d32f2f;">
                                    <?= htmlspecialchars($row['last_trans_date']) ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <br>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
 $(document).ready(function() {
    $("#cust_code").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "ajax_customer.php",
                dataType: "json",
                data: { term: request.term },
                success: function(data) { response(data); }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            $("#cust_code").val(ui.item.value);
            return false;
        }
    });
});
</script>
</body>
</html>