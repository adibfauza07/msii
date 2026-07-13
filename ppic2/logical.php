<?php
// Cek apakah tombol export ke excel diklik
 $is_export = isset($_POST['export']) && $_POST['export'] === 'excel';

if ($is_export) {
    $filename = "Jadwal_Mulai_Produksi_Plan_" . date('Ymd') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}

require_once __DIR__ . "/../config/database_ppic.php";

if ($conn === false) {
    die("Koneksi database gagal atau sesi telah berakhir. Silakan login kembali.");
}

// ============================================================
// FUNGSI PENDUKUNG
// ============================================================

function get_prev_month_dates($from_date_input) {
    if (strlen($from_date_input) >= 7) {
        $y = substr($from_date_input, 0, 4);
        $m = (int)substr($from_date_input, 5, 2);
        
        if ($m == 1) {
            $prev_y = $y - 1;
            $prev_m = 12;
        } else {
            $prev_y = $y;
            $prev_m = $m - 1;
        }
        
        $prev_from = "$prev_y-" . str_pad($prev_m, 2, "0", STR_PAD_LEFT) . "-01";
        $prev_to   = "$prev_y-" . str_pad($prev_m, 2, "0", STR_PAD_LEFT) . "-" . cal_days_in_month(CAL_GREGORIAN, $prev_m, $prev_y);
        $label     = date('M-y', strtotime($prev_from)); 
        
        return array('from' => $prev_from, 'to' => $prev_to, 'label'=> $label);
    }
    return false;
}

function clean_item_code($val) {
    $val = preg_replace('/\s+/', '', $val);
    return strtoupper($val);
}

function formatNum($val, $is_export = false) {
    $num = (float)$val;
    if ($is_export) return $num;
    if ($num == 0) return "-";
    $formatted = number_format($num);
    if ($num < 0) return '<span style="color: red;">' . $formatted . '</span>';
    return $formatted;
}

// ============================================================
// INISIALISASI VARIABEL
// ============================================================
 $from_date = isset($_POST['from_date']) ? $_POST['from_date'] : date('Y-m-01');
 $to_date   = isset($_POST['to_date']) ? $_POST['to_date'] : date('Y-m-t');
 $cust_code = isset($_POST['cust_code']) ? $_POST['cust_code'] : '';
 $groupedResults = array();
 $message   = "";
 $prevProdPlanMap = array();
 $prevMonthLabel = "Unknown";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $cust_code_param = empty($cust_code) ? '%' : $cust_code;
    $start_ymd = date('Ymd', strtotime($from_date));
    $end_ymd   = date('Ymd', strtotime($to_date));

    $prevDates = get_prev_month_dates($from_date);
    
    if ($prevDates) {
        $prevMonthLabel = $prevDates['label'];
        $prev_fDate = $prevDates['from'] . ' 00:00:00';
        $prev_tDate = $prevDates['to'] . ' 23:59:59';
    } else {
        $prev_fDate = '2000-01-01 00:00:00';
        $prev_tDate = '2000-01-31 23:59:59';
    }

    $tsqlPrev = "EXEC sp_stok_actual @from_date = ?, @to_date = ?, @cust_code = ?";
    $stmtPrev = sqlsrv_query($conn, $tsqlPrev, array($prev_fDate, $prev_tDate, $cust_code_param));

    if ($stmtPrev !== false) {
        while ($rowPrev = sqlsrv_fetch_array($stmtPrev, SQLSRV_FETCH_ASSOC)) {
            $tags      = isset($rowPrev['tags']) ? (float)$rowPrev['tags'] : 0;
            $pd_actual = isset($rowPrev['pd_actual']) ? (float)$rowPrev['pd_actual'] : 0;
            $del_sch   = isset($rowPrev['del_sch']) ? (float)$rowPrev['del_sch'] : 0;
            $del_actual= isset($rowPrev['del_actual']) ? (float)$rowPrev['del_actual'] : 0;
            $ng_rw     = isset($rowPrev['ng_rw']) ? (float)$rowPrev['ng_rw'] : 0;
            $sub_qty   = isset($rowPrev['sub_qty']) ? (float)$rowPrev['sub_qty'] : 0;
            $fore_qty  = isset($rowPrev['fore_qty']) ? (float)$rowPrev['fore_qty'] : 0;

            $stok_actual = ($tags + $pd_actual + $sub_qty) - ($del_actual + $ng_rw);
            $del_balance = $del_actual - $del_sch;
            $prod_plan   = $stok_actual + $del_balance + $fore_qty;

            $key = clean_item_code($rowPrev['item_code']);
            $prevProdPlanMap[$key] = $prod_plan;
        }
        sqlsrv_free_stmt($stmtPrev);
    }

    $sqlDelPlan = "SET NOCOUNT ON; EXEC dbo.sp_PivotDeliverySchedule_ByCustomer ?, ?, ?";
    $stmtDel = sqlsrv_query($conn, $sqlDelPlan, array($start_ymd, $end_ymd, $cust_code_param));
    
    $delPlanDailyMap = array();
    if ($stmtDel !== false) {
        while ($dRow = sqlsrv_fetch_array($stmtDel, SQLSRV_FETCH_ASSOC)) {
            if (isset($dRow['ROW_EMPTY'])) continue;
            
            $key = clean_item_code($dRow['ITEM_CODE']);
            $delPlanDailyMap[$key] = $dRow; 
        }
        sqlsrv_free_stmt($stmtDel);
    }

    if (!empty($delPlanDailyMap)) {
        foreach ($delPlanDailyMap as $item_code => $delRow) {
            $cCode = isset($delRow['CUST_CODE']) ? $delRow['CUST_CODE'] : "-";
            $iCode = isset($delRow['ITEM_CODE']) ? $delRow['ITEM_CODE'] : $item_code;
            $iName = isset($delRow['ITEM_NAME']) ? $delRow['ITEM_NAME'] : $iCode;
            $cComp = isset($delRow['CUST_COMP']) ? $delRow['CUST_COMP'] : "";

            $stok_awal = isset($prevProdPlanMap[$item_code]) ? $prevProdPlanMap[$item_code] : 0;
            
            $total_del_plan = 0;
            for ($i = 1; $i <= 31; $i++) {
                $col = $i . "_SCH";
                $total_del_plan += isset($delRow[$col]) ? (float)$delRow[$col] : 0;
            }

            $running_stock = $stok_awal;
            $shortage_day = "-"; 
            $prod_plan_day = "-";

            for ($i = 1; $i <= 31; $i++) {
                $col = $i . "_SCH";
                $del_harian = isset($delRow[$col]) ? (float)$delRow[$col] : 0;
                
                $running_stock -= $del_harian;
                
                if ($running_stock < 0) {
                    $shortage_day = str_pad($i, 2, "0", STR_PAD_LEFT);
                    
                    $calc_day = $i - 7;
                    if ($calc_day >= 1) {
                        $prod_plan_day = str_pad($calc_day, 2, "0", STR_PAD_LEFT);
                    } else {
                        $prod_plan_day = "PREV"; 
                    }
                    break; 
                }
            }

            if (!isset($groupedResults[$cCode])) {
                $groupedResults[$cCode] = array('CUST_COMP' => $cComp, 'items' => array());
            }

            $groupedResults[$cCode]['items'][] = array(
                'item_code' => $iCode,
                'item_name' => $iName,
                'stok_awal' => $stok_awal,
                'total_del_plan' => $total_del_plan,
                'shortage_day' => $shortage_day,
                'prod_plan_day' => $prod_plan_day
            );
        }

        foreach ($groupedResults as $cCode => &$custData) {
            usort($custData['items'], function($a, $b) {
                return strcasecmp(trim($a['item_name']), trim($b['item_name']));
            });
        }
        unset($custData);

        if (empty($groupedResults)) {
            $message = "Tidak ada data ditemukan untuk periode tersebut.";
        }
    } else {
        $message = "Tidak ada data Delivery Schedule ditemukan untuk periode tersebut.";
    }
}
?>
<?php if (!$is_export): ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Mulai Produksi Plan</title>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f7f6; font-size: 13px;}
        .container { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .report-header { text-align: center; margin-bottom: 20px; }
        .report-header h2 { margin: 0; text-transform: uppercase; color: #333; }
        .report-header p { margin: 5px 0; color: #555; }
        .info-box { background: #e3f2fd; border-left: 5px solid #2196f3; padding: 10px 15px; margin-bottom: 20px; font-size: 12px; color: #0d47a1; border-radius: 4px; }
        .form-group { margin-bottom: 15px; display: inline-block; margin-right: 15px; vertical-align: top; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="date"], input[type="text"] { padding: 8px; width: 180px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 8px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #0056b3; }
        .btn-excel { background: #28a745; }
        .btn-excel:hover { background: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 8px 10px; }
        th { background-color: #d9e1f2; font-weight: bold; text-align: center; }
        td { text-align: left; }
        td.text-right { text-align: right; }
        td.text-center { text-align: center; }
        tr.group-header td { background-color: #37474f; color: #fff; font-weight: bold; font-size: 14px; border-bottom: 2px solid #000; }
        .message { color: red; font-weight: bold; margin-top: 15px; text-align: center; }
        .ui-autocomplete { z-index: 1000; max-height: 200px; overflow-y: auto; }
        .col-stok { background-color: #e8f5e9; font-weight: bold; }
        .col-minus { background-color: #ffcdd2; color: #b71c1c; font-weight: bold; font-size: 14px; }
        .col-produksi { background-color: #fff9c4; color: #f57f17; font-weight: bold; font-size: 14px; }
        .col-aman { background-color: #c8e6c9; color: #1b5e20; font-weight: bold; }
        .text-prev { color: #d50000; font-weight: bold; font-size: 12px; }
    </style>
</head>
<body>

<div class="container">
    <div class="report-header">
        <h2>Jadwal Mulai Produksi Plan</h2>
        <p>Periode: <?= date('d-M-Y', strtotime($from_date)) ?> s/d <?= date('d-M-Y', strtotime($to_date)) ?></p>
    </div>

    <div class="info-box">
        <b>Logika:</b> Stok Awal diambil dari kolom <b>Prod Plan Analisa Stok (<?= htmlspecialchars($prevMonthLabel) ?>)</b> dicocokkan via <b>Item Code</b>. 
        Dikurangi <b>Delivery Plan</b> harian. Jika minus, <b>Tgl Mulai Produksi = Tgl Minus - 7 Hari</b>.
    </div>

    <form method="POST" action="" id="filterForm">
        <div class="form-group">
            <label>From Date</label>
            <input type="date" id="from_date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" required>
        </div>
        <div class="form-group">
            <label>To Date</label>
            <input type="date" id="to_date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" readonly>
        </div>
        <div class="form-group">
            <label>Customer Code</label>
            <input type="text" id="cust_code" name="cust_code" value="<?= htmlspecialchars($cust_code) ?>" placeholder="Ketik kode/nama customer">
        </div>
        <div class="form-group">
            <button type="submit" style="margin-top: 22px;">Cek Jadwal</button>
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
                <td colspan="6">
                    <?= htmlspecialchars($custCode) ?> &nbsp;&nbsp;&nbsp; <?= htmlspecialchars($custData['CUST_COMP']) ?>
                </td>
            </tr>
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th>Item Code</th>
                    <th>Item Name</th>
                    <th style="background-color: #c8e6c9;">Stok Plan Awal<br>(Prod Plan <?= htmlspecialchars($prevMonthLabel) ?>)</th>
                    <th>Total Del Plan<br>(Bulan Ini)</th>
                    <th style="background-color: #ef9a9a; color: #b71c1c;">Tgl Stok Plan<br>MINUS</th>
                    <th style="background-color: #fff176; color: #f57f17;">Tgl Mulai<br>PRODUKSI PLAN (-7)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1; 
                foreach ($custData['items'] as $row): 
                    $shortage_day = $row['shortage_day'];
                    $prod_plan_day = $row['prod_plan_day'];
                    
                    $class_minus = ($shortage_day != "-") ? "col-minus" : "col-aman";
                    $class_prod = ($prod_plan_day != "-") ? "col-produksi" : "col-aman";
                    
                    $display_prod = $prod_plan_day;
                    if ($prod_plan_day == "PREV") {
                        $display_prod = '<span class="text-prev">PREV MONTH</span>';
                    }
                ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['item_code']) ?></td>
                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                        
                        <td class="text-right col-stok"><?= formatNum($row['stok_awal'], $is_export) ?></td>
                        <td class="text-right"><?= formatNum($row['total_del_plan'], $is_export) ?></td>
                        
                        <td class="text-center <?= $class_minus ?>" <?= $is_export && $shortage_day != "-" ? 'style="background-color: #ffcdd2;"' : '' ?>>
                            <?= $shortage_day ?>
                        </td>
                        <td class="text-center <?= $class_prod ?>" <?= $is_export && $prod_plan_day != "-" ? 'style="background-color: #fff9c4;"' : '' ?>>
                            <?= $is_export ? strip_tags($display_prod) : $display_prod ?>
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
    $("#from_date").on("change", function() {
        var val = $(this).val();
        if (val !== "") {
            var parts = val.split("-");
            var y = parseInt(parts[0], 10);
            var m = parseInt(parts[1], 10);
            var lastDay = new Date(y, m, 0).getDate();
            var mm = String(m).padStart(2, '0');
            var dd = String(lastDay).padStart(2, '0');
            $("#to_date").val(y + "-" + mm + "-" + dd);
        }
    });

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