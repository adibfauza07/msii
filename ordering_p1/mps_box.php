<?php
// =========================================================================
// FITUR STREAMING: Matikan buffering agar progress bar berjalan real-time
// =========================================================================
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
@ini_set('implicit_flush', true);
ob_implicit_flush(true);
while (ob_get_level() > 0) { @ob_end_clean(); }
set_time_limit(0); 

require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

// =========================================================================
// 1. BLOK PROSES AJAX UPDATE (AUTO-SAVE)
// =========================================================================
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action    = $_POST['ajax_action'];
    $item_code = trim($_POST['item_code']);
    $value     = trim($_POST['value']);

    if ($item_code === '') { echo json_encode(['status' => 'error', 'msg' => 'Item Code kosong']); exit; }

    $item_code_master = substr($item_code, 0, 8);

    // [BARU] Handler untuk update STD_PACK
    if ($action === 'update_std_pack') {
        $pack_val = floatval($value);
        $sql = "UPDATE dbo.STD_PACK SET STD_PACK = ? WHERE ITEM_CODE = ?";
        $stmt = sqlsrv_query($conn, $sql, array($pack_val, $item_code_master));
        if ($stmt) {
            if (sqlsrv_rows_affected($stmt) === 0) {
                $sql_ins = "INSERT INTO dbo.STD_PACK (ITEM_CODE, STD_PACK) VALUES (?, ?)";
                $stmt_ins = sqlsrv_query($conn, $sql_ins, array($item_code_master, $pack_val));
                echo $stmt_ins ? json_encode(['status' => 'success', 'msg' => 'Inserted']) : json_encode(['status' => 'error', 'msg' => 'Gagal Insert']);
            } else { echo json_encode(['status' => 'success', 'msg' => 'Updated']); }
        } else {
             echo json_encode(['status' => 'error', 'msg' => 'Query Failed']);
        }
        exit;
    }

    if ($action === 'update_std_box') {
        $box_val = floatval($value);
        $sql = "UPDATE dbo.STD_PACK SET STD_PACK_BOX = ? WHERE ITEM_CODE = ?";
        $stmt = sqlsrv_query($conn, $sql, array($box_val, $item_code_master));
        if ($stmt) {
            if (sqlsrv_rows_affected($stmt) === 0) {
                $sql_ins = "INSERT INTO dbo.STD_PACK (ITEM_CODE, STD_PACK_BOX) VALUES (?, ?)";
                $stmt_ins = sqlsrv_query($conn, $sql_ins, array($item_code_master, $box_val));
                echo $stmt_ins ? json_encode(['status' => 'success', 'msg' => 'Inserted']) : json_encode(['status' => 'error', 'msg' => 'Gagal Insert']);
            } else { echo json_encode(['status' => 'success', 'msg' => 'Updated']); }
        }
        exit;
    }

    if ($action === 'update_pack_code') {
        // Ambil PACK_ID dan PACK_DESC sekaligus
        $sql_find = "SELECT PACK_ID, PACK_DESC FROM dbo.PACK WHERE PACK_CODE = ?";
        $stmt_find = sqlsrv_query($conn, $sql_find, array($value));
        
        $pack_id = null;
        $pack_desc_ajax = "";
        
        if ($stmt_find && $row = sqlsrv_fetch_array($stmt_find, SQLSRV_FETCH_ASSOC)) { 
            $pack_id = $row['PACK_ID']; 
            $pack_desc_ajax = $row['PACK_DESC'];
        }

        if ($pack_id !== null) {
            $sql_upd = "UPDATE dbo.STD_PACK SET PACK_ID = ? WHERE ITEM_CODE = ?";
            $stmt_upd = sqlsrv_query($conn, $sql_upd, array($pack_id, $item_code_master));
            if ($stmt_upd) {
                if (sqlsrv_rows_affected($stmt_upd) === 0) {
                    $sql_ins = "INSERT INTO dbo.STD_PACK (ITEM_CODE, PACK_ID) VALUES (?, ?)";
                    $stmt_ins = sqlsrv_query($conn, $sql_ins, array($item_code_master, $pack_id));
                    echo $stmt_ins ? json_encode(['status' => 'success', 'msg' => 'Inserted', 'pack_desc' => $pack_desc_ajax]) : json_encode(['status' => 'error', 'msg' => 'Gagal Insert']);
                } else { 
                    echo json_encode(['status' => 'success', 'msg' => 'Updated', 'pack_desc' => $pack_desc_ajax]); 
                }
            }
        } else { echo json_encode(['status' => 'error', 'msg' => "Kode Packing tidak ditemukan!"]); }
        exit;
    }
    exit;
}

// =========================================================================
// 2. CEK EXPORT EXCEL
// =========================================================================
$is_export = (isset($_GET['export']) && $_GET['export'] === 'excel');

if ($is_export) {
    $filename = "Compare_MRP_vs_Delivery_" . date('Ymd_His') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// =========================================================================
// 3. FUNGSI BANTUAN & PARAMETER
// =========================================================================
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8"); }
function fmt_num($value) { return number_format((float)($value ?: 0), 0, ".", ","); }
// Untuk export excel, angkanya sebaiknya plain agar bisa dijumlah di excel
function fmt_excel($value) { return (float)($value ?: 0); } 

$cust_code       = isset($_GET['CUST_CODE']) ? trim($_GET['CUST_CODE']) : "";
$start_raw       = isset($_GET['START_DATE']) ? trim($_GET['START_DATE']) : date('Y-m-01');
$empty_pack_only = isset($_GET['EMPTY_PACK_ONLY']) ? trim($_GET['EMPTY_PACK_ONLY']) : ""; 

$start_date = ($start_raw !== "") ? date("Ymd", strtotime($start_raw)) : "";

$m1 = date("M Y", strtotime($start_raw));
$m2 = date("M Y", strtotime("+1 month", strtotime($start_raw)));
$m3 = date("M Y", strtotime("+2 months", strtotime($start_raw)));

$master_customers = [];
$stmt_cust = sqlsrv_query($conn, "SELECT CUST_CODE, CUST_COMP FROM dbo.CUST ORDER BY CUST_COMP ASC");
while ($stmt_cust && $row = sqlsrv_fetch_array($stmt_cust, SQLSRV_FETCH_ASSOC)) { $master_customers[] = $row; }

$master_packs = [];
$pack_map = [];
$stmt_pack = sqlsrv_query($conn, "SELECT PACK_ID, PACK_CODE, PACK_DESC FROM dbo.PACK ORDER BY PACK_CODE ASC");
while ($stmt_pack && $row = sqlsrv_fetch_array($stmt_pack, SQLSRV_FETCH_ASSOC)) { 
    $master_packs[] = $row; 
    $pack_map[trim($row['PACK_CODE'])] = trim($row['PACK_DESC']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MRP vs Delivery Actual (3 Months) | OEIC</title>
    <?php if (!$is_export): ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <?php endif; ?>
    <style>
        @page { size: A4 landscape; margin: 6mm; }
        .report-container { background: #9a9a9a; padding: 20px; font-family: "Courier New", monospace; font-size: 10px; color: #000; overflow-x: auto; }
        .page { width: 100%; min-width: 1000px; margin: 10px auto; background: #fff; border: 2px solid #000; padding: 5mm; box-sizing: border-box; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        .report-title { font-size: 18px; text-align: center; font-weight: bold; }
        
        .di-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 10px; }
        .di-table th, .di-table td { border: 1px solid #000; padding: 3px; vertical-align: middle; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .di-table th { background: #f4f4f4; text-align: center; }
        
        .rekap-box-table { width: 50%; border-collapse: collapse; font-size: 10px; margin-top: 5px; }
        .rekap-box-table th, .rekap-box-table td { border: 1px solid #000; padding: 3px 5px; }
        .rekap-box-table th { background: #e9ecef; text-align: center; }

        .num { text-align: right; } .center { text-align: center; }
        .bg-balance { background-color: #e3f2fd; }
        .text-red { color: red; font-weight: bold; }
        
        .input-cell { padding: 0 !important; }
        .input-edit { width: 100%; height: 100%; border: none; background: #fff9c4; text-align: center; outline: none; }
        .input-edit:focus { background: #fff; border: 1px dashed #333; }
        
        @media print {
            body, html { background: #fff; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .content-wrapper, .report-container { background: transparent !important; padding: 0 !important; }
            .page { border: none; padding: 0; margin: 0 auto; page-break-after: always; width: 100%; min-width: auto; }
            .input-edit { background: transparent !important; border: none !important; }
        }
        
        <?php if ($is_export): ?>
        /* Timpa style khusus excel agar border muncul semua */
        table { border-collapse: collapse; }
        th, td { border: 1px solid #000000 !important; }
        .page { border: none; margin: 0; padding: 0; }
        .report-container { background: #ffffff; padding: 0; }
        <?php endif; ?>
    </style>
</head>
<body class="<?php echo !$is_export ? 'hold-transition layout-top-nav' : ''; ?>">

<?php if (!$is_export): ?>
<datalist id="pack-list">
    <?php foreach($master_packs as $p): ?>
        <option value="<?php echo h($p['PACK_CODE']); ?>"><?php echo h($p['PACK_CODE'] . " - " . $p['PACK_DESC']); ?></option>
    <?php endforeach; ?>
</datalist>
<datalist id="cust-list">
    <?php foreach($master_customers as $c): ?><option value="<?php echo h($c['CUST_CODE']); ?>"><?php echo h($c['CUST_COMP']); ?></option><?php endforeach; ?>
</datalist>

<div class="wrapper">
    <nav class="main-header navbar navbar-expand-md navbar-dark bg-primary no-print">
        <div class="container-fluid"><a href="#" class="navbar-brand"><span class="brand-text">P.T. IMC TEKNO INDONESIA</span></a></div>
    </nav>

    <div class="content-wrapper">
        <div class="content-header no-print">
            <div class="container-fluid"><h1 class="m-0">Compare MRP vs Delivery Actual (3 Months)</h1></div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="card card-default no-print">
                    <div class="card-body">
                        <form method="GET" onsubmit="$('#progress-container').show();">
                            <div class="row">
                                <div class="col-md-3">
                                    <label>Customer (Kosongkan = Semua)</label>
                                    <input type="text" name="CUST_CODE" list="cust-list" class="form-control" value="<?php echo h($cust_code); ?>" placeholder="Pilih Customer...">
                                </div>
                                <div class="col-md-2">
                                    <label>Start Date (Bulan MRP)</label>
                                    <input type="date" name="START_DATE" class="form-control" value="<?php echo h($start_raw); ?>" required>
                                </div>
                                <div class="col-md-3 mt-4 pt-2">
                                    <input type="checkbox" id="emptyPackOnly" name="EMPTY_PACK_ONLY" value="1" <?php if($empty_pack_only == "1") echo "checked"; ?>>
                                    <label for="emptyPackOnly">Filter Box Kosong Saja</label>
                                </div>
                                <div class="col-md-2 mt-4">
                                    <button type="button" class="btn btn-info w-100" onclick="window.open('mrp_rekap.php?CUST_CODE=' + $('input[name=CUST_CODE]').val() + '&START_DATE=' + $('input[name=START_DATE]').val(), '_blank')">
                                        <i class="fas fa-list"></i> SUMMARY
                                    </button>
                                </div>
                                <div class="col-md-2 mt-4">
                                    <button type="submit" class="btn btn-primary w-100">LOAD DATA</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
<?php endif; // End of if(!$is_export) ?>

                <?php if ($start_date !== ""): ?>
                <div class="card card-default" <?php if($is_export) echo 'style="border:none;"'; ?>>
                    <?php if (!$is_export): ?>
                    <div class="card-header no-print">
                        <h3 class="card-title">Laporan Data & Rekapitulasi Actual</h3>
                        <div class="card-tools">
                            <!-- TOMBOL EXPORT EXCEL DITAMBAHKAN DI SINI -->
                            <a href="?CUST_CODE=<?php echo urlencode($cust_code); ?>&START_DATE=<?php echo urlencode($start_raw); ?>&EMPTY_PACK_ONLY=<?php echo urlencode($empty_pack_only); ?>&export=excel" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> EXCEL</a>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> PRINT</button>
                        </div>
                    </div>
                    
                    <div class="card-body p-0">
                        <div id="progress-container" class="p-3 no-print" style="background: #f8f9fa; border-bottom: 1px solid #ddd;">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="font-weight-bold text-primary"><i class="fas fa-spinner fa-spin"></i> Memproses Data Delivery Actual...</span>
                                <span id="progress-text" class="font-weight-bold text-primary">0%</span>
                            </div>
                            <div class="progress" style="height: 22px; border-radius: 4px; overflow: hidden; background-color: #e9ecef;">
                                <div id="streaming-progress" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%; transition: width 0.2s ease;">0%</div>
                            </div>
                        </div>
                    <?php endif; ?>

                        <div class="report-container">
                            <?php
                            $sp_cust_code = ($cust_code === "") ? "%" : $cust_code;
                            $sql_sp = "SET NOCOUNT ON; EXEC dbo.SP_MPS3MONTH_BOX ?, ?";
                            $stmt = sqlsrv_query($conn, $sql_sp, array($start_date, $sp_cust_code), array("Scrollable" => SQLSRV_CURSOR_STATIC));

                            if ($stmt === false) {
                                echo "<div class='alert alert-danger'>Gagal memuat SP: " . print_r(sqlsrv_errors(), true) . "</div>";
                            } else {
                                $total_rows = sqlsrv_num_rows($stmt);
                                if ($total_rows === false || $total_rows <= 0) $total_rows = 1;

                                $current_cust = null;
                                $page_no = 0;
                                $row_count = 0;
                                $has_data = false;
                                
                                $rekap_box = [];
                                $tot_m1_OE = 0; $tot_m1_act = 0;
                                $tot_m2_OE = 0; $tot_m2_act = 0;
                                $tot_m3_OE = 0; $tot_m3_act = 0;
                                $tot_bal1 = 0; $tot_bal2 = 0; $tot_bal3 = 0;

                                function render_page_header($r, $page_no, $m1, $m2, $m3) {
                                    global $is_export;
                                    ?>
                                    <div class="page">
                                        <table class="header">
                                            <tr>
                                                <td style="width:30%">P.T. IMC TEKNO INDONESIA</td>
                                                <td class="report-title">MPS PLAN VS DELIVERY ACTUAL & REKAP BOX</td>
                                                <td style="width:30%; text-align:right;">Print: <?php echo date('d-m-Y H:i'); ?><br>Page: <?php echo $page_no; ?></td>
                                            </tr>
                                        </table>
                                        <div style="font-weight:bold; margin-bottom:5px; font-size:12px;">
                                            <?php echo h($r['CUST_CODE'] . " - " . $r['CUST_COMP']); ?>
                                        </div>
                                        <table class="di-table">
                                            <thead>
                                                <tr>
                                                    <th rowspan="2" style="width:8%">Item Code</th>
                                                    <th rowspan="2" style="width:8%">Item No</th>
                                                    <th rowspan="2" style="width:10%">Item Name</th>
                                                    <th rowspan="2" style="width:4%">Std Pack</th> <!-- KOLOM BARU -->
                                                    <th rowspan="2" style="width:4%">Std Box</th>
                                                    <th rowspan="2" style="width:5%">Pack ID</th>
                                                    <th rowspan="2" style="width:10%">Pack Desc</th>
                                                    <th colspan="3">M1: <?php echo $m1; ?></th>
                                                    <th colspan="3">M2: <?php echo $m2; ?></th>
                                                    <th colspan="3">M3: <?php echo $m3; ?></th>
                                                </tr>
                                                <tr>
                                                    <th style="width:5%">OE Box</th><th style="width:5%">Act Qty</th><th style="width:5%" class="bg-balance">Bal M1</th>
                                                    <th style="width:5%">OE Box</th><th style="width:5%">Act Qty</th><th style="width:5%" class="bg-balance">Bal M2</th>
                                                    <th style="width:5%">OE Box</th><th style="width:5%">Act Qty</th><th style="width:5%" class="bg-balance">Bal M3</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                    <?php
                                }

                                function render_rekap_and_close($rekap_box, $tot_m1_OE, $tot_m1_act, $tot_m2_OE, $tot_m2_act, $tot_m3_OE, $tot_m3_act, $tot_bal1, $tot_bal2, $tot_bal3) {
                                    global $is_export;
                                    echo "</tbody>";
                                    echo "<tfoot>";
                                    echo "<tr style='background:#f1f3f5; font-weight:bold;'>";
                                    // Berubah dari 6 ke 7 kolom colspan karena ketambahan Std Pack
                                    echo "<td colspan='7' class='center'>TOTAL KESELURUHAN</td>";
                                    
                                    // Gunakan format angka yang sesuai agar terbaca value di Excel jika $is_export true
                                    $f = $is_export ? 'fmt_excel' : 'fmt_num';
                                    $lbl = $is_export ? '' : ' Box';
                                    $lbl_qty = $is_export ? '' : ' Qty';

                                    echo "<td class='center'>".$f($tot_m1_OE).$lbl."</td><td class='center'>".$f($tot_m1_act).$lbl_qty."</td><td class='center bg-balance " . ($tot_bal1 < 0 ? 'text-red' : '') . "'>".$f($tot_bal1)."</td>";
                                    echo "<td class='center'>".$f($tot_m2_OE).$lbl."</td><td class='center'>".$f($tot_m2_act).$lbl_qty."</td><td class='center bg-balance " . ($tot_bal2 < 0 ? 'text-red' : '') . "'>".$f($tot_bal2)."</td>";
                                    echo "<td class='center'>".$f($tot_m3_OE).$lbl."</td><td class='center'>".$f($tot_m3_act).$lbl_qty."</td><td class='center bg-balance " . ($tot_bal3 < 0 ? 'text-red' : '') . "'>".$f($tot_bal3)."</td>";
                                    
                                    echo "</tr>";
                                    echo "</tfoot>";
                                    echo "</table>";

                                    echo "<h6 style='font-weight:bold; margin-top:10px;'>REKAPITULASI KEBUTUHAN PER JENIS BOX / PACKING:</h6>";
                                    echo "<table class='rekap-box-table'>";
                                    echo "<thead><tr><th>Jenis Pack / Box ID</th><th>Total Box M1 (OE)</th><th>Total Box M2 (OE)</th><th>Total Box M3 (OE)</th></tr></thead><tbody>";
                                    if(empty($rekap_box)) {
                                        echo "<tr><td colspan='4' class='center'>Tidak ada data box</td></tr>";
                                    } else {
                                        foreach($rekap_box as $pack => $val) {
                                            $p_name = ($pack == "" || $pack == null) ? "*BELUM DISET*" : $pack;
                                            echo "<tr>";
                                            echo "<td><b>".h($p_name)."</b></td>";
                                            echo "<td class='center'>".$f($val['m1'])."</td>";
                                            echo "<td class='center'>".$f($val['m2'])."</td>";
                                            echo "<td class='center'>".$f($val['m3'])."</td>";
                                            echo "</tr>";
                                        }
                                    }
                                    echo "</tbody></table>";
                                    echo "</div>";
                                }

                                while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                    $row_count++;
                                    
                                    // Matikan javascript streaming jika sedang export ke excel agar file tidak korup
                                    if (!$is_export) {
                                        $percent = min(100, round(($row_count / $total_rows) * 100));
                                        echo "<script>
                                            var pb = document.getElementById('streaming-progress');
                                            var pt = document.getElementById('progress-text');
                                            if(pb) { pb.style.width = '{$percent}%'; pb.innerHTML = '{$percent}%'; }
                                            if(pt) pt.innerHTML = '{$percent}%';
                                        </script>";
                                        flush();
                                    }

                                    $has_data = true;
                                    $std_box = (float)$r['STD_PACK_BOX'];
                                    $pack_code = trim($r['PACK_CODE']);
                                    
                                    if ($empty_pack_only === "1" && $std_box > 0 && $pack_code !== "") { continue; }

                                    $cust_key = trim($r['CUST_CODE']);
                                    if ($current_cust !== $cust_key) {
                                        if ($current_cust !== null) {
                                            render_rekap_and_close($rekap_box, $tot_m1_OE, $tot_m1_act, $tot_m2_OE, $tot_m2_act, $tot_m3_OE, $tot_m3_act, $tot_bal1, $tot_bal2, $tot_bal3);
                                            if (!$is_export) flush();
                                        }

                                        $current_cust = $cust_key;
                                        $page_no++;
                                        
                                        $rekap_box = [];
                                        $tot_m1_OE = 0; $tot_m1_act = 0;
                                        $tot_m2_OE = 0; $tot_m2_act = 0;
                                        $tot_m3_OE = 0; $tot_m3_act = 0;
                                        $tot_bal1 = 0; $tot_bal2 = 0; $tot_bal3 = 0;

                                        render_page_header($r, $page_no, $m1, $m2, $m3);
                                    }

                                    $p_key = ($pack_code == "") ? "UNSPECIFIED" : $pack_code;
                                    $b_m1_OE = (float)$r['BOX_OE1'];
                                    $b_m2_OE = (float)$r['BOX_OE2'];
                                    $b_m3_OE = (float)$r['BOX_OE3'];
                                    
                                    $q_m1_act = (float)$r['SCHED1_QTY'];
                                    $q_m2_act = (float)$r['SCHED2_QTY'];
                                    $q_m3_act = (float)$r['SCHED3_QTY'];

                                    if (!isset($rekap_box[$p_key])) {
                                        $rekap_box[$p_key] = ['m1' => 0, 'm2' => 0, 'm3' => 0];
                                    }
                                    $rekap_box[$p_key]['m1'] += $b_m1_OE;
                                    $rekap_box[$p_key]['m2'] += $b_m2_OE;
                                    $rekap_box[$p_key]['m3'] += $b_m3_OE;

                                    $tot_m1_OE += $b_m1_OE; $tot_m1_act += $q_m1_act;
                                    $tot_m2_OE += $b_m2_OE; $tot_m2_act += $q_m2_act;
                                    $tot_m3_OE += $b_m3_OE; $tot_m3_act += $q_m3_act;

                                    $diff_b1 = (float)$r['BAL_BOX1'];
                                    $diff_b2 = (float)$r['BAL_BOX2'];
                                    $diff_b3 = (float)$r['BAL_BOX3'];

                                    $tot_bal1 += $diff_b1;
                                    $tot_bal2 += $diff_b2;
                                    $tot_bal3 += $diff_b3;
                                    
                                    $pack_desc = isset($pack_map[$pack_code]) ? $pack_map[$pack_code] : "";
                                    
                                    // Set format value untuk HTML (dengan koma ribuan) atau EXCEL (tanpa format agar bisa dijumlahkan)
                                    $f = $is_export ? 'fmt_excel' : 'fmt_num';
                                    ?>
                                    <tr>
                                        <td><?php echo h($r['ITEM_CODE']); ?></td>
                                        <td><?php echo h($r['ITEM_NO']); ?></td>
                                        <td><?php echo h($r['ITEM_NAME']); ?></td>
                                        
                                        <?php if ($is_export): ?>
                                            <!-- Render plain text saat export agar terbaca normal di Excel -->
                                            <td class="center"><?php echo isset($r['STD_PACK']) ? h($r['STD_PACK']) : ''; ?></td>
                                            <td class="center"><?php echo h($r['STD_PACK_BOX']); ?></td>
                                            <td class="center"><?php echo h($r['PACK_CODE']); ?></td>
                                        <?php else: ?>
                                            <!-- Render input textbox seperti biasa saat dibuka di Web -->
                                            
                                            <!-- KOLOM BARU STD PACK -->
                                            <td class="center input-cell">
                                                <input type="number" step="any" class="input-edit std-pack-trigger" 
                                                       data-item="<?php echo h($r["ITEM_CODE"]); ?>" 
                                                       value="<?php echo isset($r['STD_PACK']) ? h($r['STD_PACK']) : ''; ?>">
                                            </td>

                                            <td class="center input-cell">
                                                <input type="number" step="any" class="input-edit std-box-trigger" 
                                                       data-item="<?php echo h($r["ITEM_CODE"]); ?>" 
                                                       data-m1="<?php echo h($r['MPS_OE1']); ?>" data-s1="<?php echo h($q_m1_act); ?>" 
                                                       data-m2="<?php echo h($r['MPS_OE2']); ?>" data-s2="<?php echo h($q_m2_act); ?>" 
                                                       data-m3="<?php echo h($r['MPS_OE3']); ?>" data-s3="<?php echo h($q_m3_act); ?>" 
                                                       value="<?php echo h($r['STD_PACK_BOX']); ?>">
                                            </td>
                                            <td class="center input-cell">
                                                <input type="text" list="pack-list" class="input-edit pack-code-trigger" 
                                                       data-item="<?php echo h($r["ITEM_CODE"]); ?>" 
                                                       value="<?php echo h($r["PACK_CODE"]); ?>">
                                            </td>
                                        <?php endif; ?>
                                        
                                        <!-- Ditambahkan class pack-desc-cell -->
                                        <td class="pack-desc-cell"><?php echo h($pack_desc); ?></td>

                                        <td class="center calc-m1-box"><?php echo $f($b_m1_OE); ?></td>
                                        <td class="center calc-s1-box"><?php echo $f($q_m1_act); ?></td>
                                        <td class="center bg-balance <?php echo $diff_b1 < 0 ? 'text-red' : ''; ?> calc-diff-b1"><?php echo $f($diff_b1); ?></td>
                                        
                                        <td class="center calc-m2-box"><?php echo $f($b_m2_OE); ?></td>
                                        <td class="center calc-s2-box"><?php echo $f($q_m2_act); ?></td>
                                        <td class="center bg-balance <?php echo $diff_b2 < 0 ? 'text-red' : ''; ?> calc-diff-b2"><?php echo $f($diff_b2); ?></td>
                                        
                                        <td class="center calc-m3-box"><?php echo $f($b_m3_OE); ?></td>
                                        <td class="center calc-s3-box"><?php echo $f($q_m3_act); ?></td>
                                        <td class="center bg-balance <?php echo $diff_b3 < 0 ? 'text-red' : ''; ?> calc-diff-b3"><?php echo $f($diff_b3); ?></td>
                                    </tr>
                                    <?php
                                }

                                if ($current_cust !== null) {
                                    render_rekap_and_close($rekap_box, $tot_m1_OE, $tot_m1_act, $tot_m2_OE, $tot_m2_act, $tot_m3_OE, $tot_m3_act, $tot_bal1, $tot_bal2, $tot_bal3);
                                } else if (!$has_data) {
                                    echo "<div class='alert alert-warning m-3'>Tidak ada data ditemukan sesuai filter.</div>";
                                }
                                
                                if (!$is_export) {
                                    echo "<script>
                                        setTimeout(function(){ 
                                            var pc = document.getElementById('progress-container');
                                            if(pc) pc.style.display = 'none'; 
                                        }, 500);
                                    </script>";
                                    flush();
                                }
                            }
                            ?>
                        </div>
<?php if (!$is_export): ?>                        
                    </div>
                </div>
<?php endif; ?>                
                <?php endif; ?>
<?php if (!$is_export): ?>                
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
$(document).ready(function() {
    // [BARU] Tambahkan class std-pack-trigger pada deteksi event
    $(document).on('change', '.std-pack-trigger, .std-box-trigger, .pack-code-trigger', function() {
        var input = $(this);
        var itemCode = input.data('item');
        var newValue = input.val();
        
        var isPack = input.hasClass('std-pack-trigger');
        var isBox = input.hasClass('std-box-trigger');
        
        // Pilih Action Name secara dinamis
        var actionName = '';
        if (isPack) actionName = 'update_std_pack';
        else if (isBox) actionName = 'update_std_box';
        else actionName = 'update_pack_code';
        
        $.post(window.location.href, { ajax_action: actionName, item_code: itemCode, value: newValue }, function(res) {
            if(res.status === 'success') {
                input.css('background-color', '#c8e6c9'); 
                setTimeout(function(){ input.css('background-color', '#fff9c4'); }, 1000);
                
                var tr = input.closest('tr');
                
                // Kalkulasi BOX hanya dijalankan jika user mengupdate kolom Std Box
                if(isBox) {
                    var stdBox = parseFloat(newValue) || 0;
                    var m1 = parseFloat(input.data('m1')) || 0;
                    var m2 = parseFloat(input.data('m2')) || 0;
                    var m3 = parseFloat(input.data('m3')) || 0;
                    
                    var s1 = parseFloat(input.data('s1')) || 0;
                    var s2 = parseFloat(input.data('s2')) || 0;
                    var s3 = parseFloat(input.data('s3')) || 0;
                    
                    var bm1 = stdBox > 0 ? Math.ceil(m1/stdBox) : 0;
                    var bm2 = stdBox > 0 ? Math.ceil(m2/stdBox) : 0;
                    var bm3 = stdBox > 0 ? Math.ceil(m3/stdBox) : 0;
                    
                    var bal1 = bm1 - s1;
                    var bal2 = bm2 - s2;
                    var bal3 = bm3 - s3;
                    
                    tr.find('.calc-m1-box').text(bm1.toLocaleString('en-US'));
                    tr.find('.calc-m2-box').text(bm2.toLocaleString('en-US'));
                    tr.find('.calc-m3-box').text(bm3.toLocaleString('en-US'));
                    
                    tr.find('.calc-diff-b1').text(bal1.toLocaleString('en-US')).toggleClass('text-red', bal1 < 0);
                    tr.find('.calc-diff-b2').text(bal2.toLocaleString('en-US')).toggleClass('text-red', bal2 < 0);
                    tr.find('.calc-diff-b3').text(bal3.toLocaleString('en-US')).toggleClass('text-red', bal3 < 0);
                } else if (actionName === 'update_pack_code') {
                    // Update Text di kolom "Pack Desc"
                    if (res.pack_desc !== undefined) {
                        tr.find('.pack-desc-cell').text(res.pack_desc);
                    }
                }
            } else { 
                input.css('background-color', '#ffcdd2');
                alert("Error: " + res.msg); 
            }
        }, 'json').fail(function() {
            input.css('background-color', '#ffcdd2'); alert('Gagal tersambung ke server.');
        });
    });

    $(document).on('keydown', '.std-pack-trigger, .std-box-trigger, .pack-code-trigger', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) { e.preventDefault(); $(this).blur(); }
    });
});
</script>
<?php endif; ?>
</body>
</html>