<?php
// Cek apakah tombol export ke excel diklik
 $is_export = isset($_POST['export']) && $_POST['export'] === 'excel';

if ($is_export) {
    $filename = "Analisa_Stok_Aktual_" . date('Ymd') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// Panggil file config yang sudah Anda buat
require_once __DIR__ . "/../config/database_ppic.php";

// Pastikan koneksi berhasil
if ($conn === false) {
    die("Koneksi database gagal atau sesi telah berakhir. Silakan login kembali.");
}

// Inisialisasi variabel (Default awal bulan s/d akhir bulan)
 $from_date = isset($_POST['from_date']) ? $_POST['from_date'] : date('Y-m-01');
 $to_date   = isset($_POST['to_date']) ? $_POST['to_date'] : date('Y-m-t');
 $cust_code = isset($_POST['cust_code']) ? $_POST['cust_code'] : '';
 $groupedResults = array();
 $message   = "";

// Proses jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $cust_code_param = empty($cust_code) ? '%' : $cust_code;
    $fDate = $from_date . ' 00:00:00';
    $tDate = $to_date . ' 23:59:59';

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

        // GROUPING DATA BERDASARKAN CUSTOMER
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

            // SORTING ITEM NAME A-Z
            foreach ($groupedResults as $cCode => &$custData) {
                usort($custData['items'], function($a, $b) {
                    return strcasecmp(trim($a['item_name']), trim($b['item_name']));
                });
            }
            unset($custData); 

        } else {
            $message = "Tidak ada data ditemukan untuk filter tersebut.";
        }
    }
}

// Fungsi bantu untuk format angka
function formatNum($val, $is_export = false) {
    $num = (float)$val;
    if ($is_export) {
        return $num;
    }
    $formatted = number_format($num);
    if ($num < 0) {
        return '<span style="color: red;">' . $formatted . '</span>';
    }
    return $formatted;
}
?>

<?php if (!$is_export): ?>
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
        
        /* STYLE UNTUK HYPERLINK ITEM CODE */
        .link-item {
            color: #0056b3;
            text-decoration: none;
            font-weight: bold;
        }
        .link-item:hover {
            text-decoration: underline;
            color: #003366;
        }
        .link-icon {
            font-size: 11px;
            color: #007bff;
            margin-left: 4px;
        }
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
        <div class="form-group">
            <button type="submit" style="margin-top: 22px;">Tampilkan Report</button>
            <button type="submit" name="export" value="excel" class="btn-excel" style="margin-top: 22px;">Export to Excel</button>
        </div>
    </form>

    <br>

    <?php if ($message): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>
<?php endif; ?> 

<?php if (!empty($groupedResults)): ?>
        <?php foreach ($groupedResults as $custCode => $custData): ?>
            <table <?= $is_export ? 'border="1"' : '' ?>>
                <tr class="group-header">
                    <td colspan="17" <?= $is_export ? 'style="background-color: #f2f2f2; font-weight: bold;"' : '' ?>>
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
                        // LOGIKA SHORTAGE DATE (RUNNING + ESTIMASI)
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
                                WHERE i.ITEM_CODE = ?
                                  AND ISDATE(d.DELS_DATE) = 1
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
                                        if ($raw_date instanceof DateTime) {
                                            $shortage_date = $raw_date->format('d-M-Y');
                                            $np_datetime = clone $raw_date;
                                            $np_datetime->modify('-7 days');
                                            $next_prod_date = $np_datetime->format('d-M-Y');
                                        } else {
                                            $timestamp = strtotime($raw_date);
                                            if ($timestamp) {
                                                $shortage_date = date('d-M-Y', $timestamp);
                                                $next_prod_date = date('d-M-Y', strtotime('-7 days', $timestamp));
                                            }
                                        }
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
                                $shortage_date = $is_export ? "Error DB" : "<span style='color:red;cursor:help;border-bottom:1px dashed red;' title='" . htmlspecialchars($err_msg, ENT_QUOTES) . "'>Error</span>";
                                $next_prod_date = "-"; 
                            }
                        }

                        // =========================================================
                        // BUAT LINK DELIVERY SCHEDULE
                        // =========================================================
                        $del_sch_link = "delivery_schedule.php?item_code=" . urlencode($item_code) . "&from_date=" . urlencode($from_date) . "&to_date=" . urlencode($to_date);
                        
                        if ($is_export) {
                            $item_code_display = $item_code;
                        } else {
                            $item_code_display = "<a href='" . $del_sch_link . "' target='_blank' class='link-item' title='Lihat Delivery Schedule'>" . htmlspecialchars($item_code) . " <span class='link-icon'>📥</span></a>";
                        }
                    ?>
                        <tr>
                            <td style="text-align: center;"><?= $no++ ?></td>
                            <td><?= $item_code_display ?></td>
                            <td><?= htmlspecialchars($row['item_name']) ?></td>
                            <td class="text-right"><?= formatNum($tags, $is_export) ?></td>
                            <td class="text-right"><?= formatNum($ps, $is_export) ?></td>
                            <td class="text-right"><?= formatNum($pd_actual, $is_export) ?></td>
                            <td class="text-right"><?= formatNum($del_sch, $is_export) ?></td>
                            <td class="text-right"><?= formatNum($del_actual, $is_export) ?></td>
                            
                            <td class="text-right calc-col"><?= formatNum($del_balance, $is_export) ?></td>

                            <td class="text-right"><?= formatNum($ng_rw, $is_export) ?></td>
                            <td class="text-right"><?= formatNum($sub_qty, $is_export) ?></td>
                            <td class="text-right"><?= formatNum($fore_qty, $is_export) ?></td>
                            
                            <td class="text-right calc-col"><?= formatNum($stok_plan, $is_export) ?></td>
                            <td class="text-right calc-col"><?= formatNum($stok_actual, $is_export) ?></td>
                            <td class="text-right calc-col"><?= formatNum($prod_plan, $is_export) ?></td>

                            <td style="text-align: center; font-weight: bold; color: <?= ($shortage_date === 'Aman') ? 'green' : 'red' ?>;">
                                <?= $shortage_date ?>
                            </td>
                            <td style="text-align: center; font-weight: bold; color: <?= ($next_prod_date === 'Aman') ? 'green' : 'red' ?>;">
                                <?= $next_prod_date ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <br>
        <?php endforeach; ?>
    <?php endif; ?>

<?php if (!$is_export): ?>
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
<?php endif; ?>