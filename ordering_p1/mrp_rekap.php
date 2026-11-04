<?php
// =========================================================================
// mrp_rekap.php - Rekapitulasi Kebutuhan Box (OE vs Actual)
// =========================================================================
set_time_limit(0); 

require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

// =========================================================================
// FUNGSI BANTUAN & PARAMETER
// =========================================================================
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8"); }
function fmt_num($value) { return number_format((float)($value ?: 0), 0, ".", ","); }

$cust_code       = isset($_GET['CUST_CODE']) ? trim($_GET['CUST_CODE']) : "";
$start_raw       = isset($_GET['START_DATE']) ? trim($_GET['START_DATE']) : date('Y-m-01');
$is_export       = isset($_GET['export_excel']) && $_GET['export_excel'] == '1';

$start_date = ($start_raw !== "") ? date("Ymd", strtotime($start_raw)) : "";

$m1 = date("M Y", strtotime($start_raw));
$m2 = date("M Y", strtotime("+1 month", strtotime($start_raw)));
$m3 = date("M Y", strtotime("+2 months", strtotime($start_raw)));

// Jika mode export, set header untuk Excel
if ($is_export) {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Summary_MRP_vs_Actual_".date('Ymd_His').".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// 1. Load Master Customer
$master_customers = [];
$stmt_cust = sqlsrv_query($conn, "SELECT CUST_CODE, CUST_COMP FROM dbo.CUST ORDER BY CUST_COMP ASC");
while ($stmt_cust && $row = sqlsrv_fetch_array($stmt_cust, SQLSRV_FETCH_ASSOC)) { $master_customers[] = $row; }

// 2. Load Master Pack Description
$pack_map = [];
$stmt_pack = sqlsrv_query($conn, "SELECT PACK_CODE, PACK_DESC FROM dbo.PACK");
if ($stmt_pack !== false) {
    while ($p = sqlsrv_fetch_array($stmt_pack, SQLSRV_FETCH_ASSOC)) {
        $pack_map[trim($p['PACK_CODE'])] = trim($p['PACK_DESC']);
    }
}
?>

<?php if (!$is_export): ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Summary MRP vs Delivery Actual | OEIC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        @page { size: A4 landscape; margin: 6mm; }
        .page { background: #fff; margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; box-shadow: 0 0 5px rgba(0,0,0,0.1); page-break-inside: avoid; }
        .rekap-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px; }
        .rekap-table th, .rekap-table td { border: 1px solid #000; padding: 6px; vertical-align: middle; }
        .rekap-table th { background: #e9ecef; text-align: center; }
        .num { text-align: right; } 
        .center { text-align: center; }
        .bg-balance { background-color: #e3f2fd; }
        .text-red { color: red; font-weight: bold; }
        .cust-title { font-size: 16px; font-weight: bold; margin-bottom: 5px; border-bottom: 2px solid #333; padding-bottom: 5px; }
        .global-title { font-size: 16px; font-weight: bold; background-color: #ffe8a1; padding: 8px; border: 1px solid #000; text-align: center; }

        @media print {
            body, html { background: #fff; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .content-wrapper { background: transparent !important; padding: 0 !important; }
            .page { border: none; box-shadow: none; margin-bottom: 10mm; page-break-inside: avoid; }
        }
    </style>
</head>
<body class="hold-transition layout-top-nav">

<datalist id="cust-list">
    <?php foreach($master_customers as $c): ?><option value="<?php echo h($c['CUST_CODE']); ?>"><?php echo h($c['CUST_COMP']); ?></option><?php endforeach; ?>
</datalist>

<div class="wrapper">
    <nav class="main-header navbar navbar-expand-md navbar-dark bg-primary no-print">
        <div class="container-fluid"><a href="#" class="navbar-brand"><span class="brand-text">P.T. IMC TEKNO INDONESIA</span></a></div>
    </nav>

    <div class="content-wrapper">
        <div class="content-header no-print">
            <div class="container-fluid"><h1 class="m-0">Summary Rekap MRP vs Delivery Actual</h1></div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <!-- FORM FILTER -->
                <div class="card card-default no-print">
                    <div class="card-body">
                        <form id="filterForm" method="GET">
                            <div class="row">
                                <div class="col-md-3">
                                    <label>Customer (Kosongkan = Semua)</label>
                                    <input type="text" name="CUST_CODE" list="cust-list" class="form-control" value="<?php echo h($cust_code); ?>" placeholder="Pilih Customer...">
                                </div>
                                <div class="col-md-3">
                                    <label>Start Date (Bulan MRP)</label>
                                    <input type="date" name="START_DATE" class="form-control" value="<?php echo h($start_raw); ?>" required>
                                </div>
                                <div class="col-md-2 mt-4 pt-1">
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> LOAD DATA</button>
                                </div>
                                <div class="col-md-2 mt-4 pt-1">
                                    <button type="button" class="btn btn-default w-100" onclick="window.print()"><i class="fas fa-print"></i> PRINT</button>
                                </div>
                                <div class="col-md-2 mt-4 pt-1">
                                    <button type="button" class="btn btn-success w-100" onclick="exportExcel()"><i class="fas fa-file-excel"></i> EXCEL</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
<?php else: ?>
    <!-- Basic CSS untuk Export Excel agar rapi -->
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 12px; }
        th, td { border: 1px solid #000; padding: 5px; }
        th { background-color: #D3D3D3; text-align: center; font-weight: bold; }
        .center { text-align: center; }
        .text-red { color: red; font-weight: bold; }
        .cust-title { font-size: 16px; font-weight: bold; margin-top: 20px; margin-bottom: 10px; }
        .global-title { font-size: 16px; font-weight: bold; background-color: #ffe8a1; padding: 8px; text-align: center; border: 1px solid #000; margin-bottom:10px; }
    </style>
<?php endif; ?>

                <?php if ($start_date !== ""): ?>
                    <?php
                    $sp_cust_code = ($cust_code === "") ? "%" : $cust_code;
                    $sql_sp = "SET NOCOUNT ON; EXEC dbo.SP_MPS3MONTH_BOX ?, ?";
                    $stmt = sqlsrv_query($conn, $sql_sp, array($start_date, $sp_cust_code));

                    if ($stmt === false) {
                        echo "<div class='alert alert-danger'>Gagal memuat SP: " . print_r(sqlsrv_errors(), true) . "</div>";
                    } else {
                        // Proses Data di Memori
                        $summary_data = [];
                        $global_summary = []; // Menampung summary semua customer

                        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            $cust_name = $r['CUST_CODE'] . " - " . $r['CUST_COMP'];
                            
                            $pack_code = trim($r['PACK_CODE']);
                            $p_key = ($pack_code === "") ? "*BELUM DISET*" : $pack_code;
                            $p_desc = isset($pack_map[$pack_code]) ? $pack_map[$pack_code] : "";
                            
                            // Gabungkan Pack Code dengan Description
                            $display_pack = ($p_key === "*BELUM DISET*") ? $p_key : $p_key . ($p_desc !== "" ? " - " . $p_desc : "");

                            // --- 1. Kumpulkan Data Per Customer ---
                            if (!isset($summary_data[$cust_name])) {
                                $summary_data[$cust_name] = [];
                            }
                            if (!isset($summary_data[$cust_name][$display_pack])) {
                                $summary_data[$cust_name][$display_pack] = [
                                    'oe1' => 0, 'act1' => 0, 'oe2' => 0, 'act2' => 0, 'oe3' => 0, 'act3' => 0
                                ];
                            }
                            $summary_data[$cust_name][$display_pack]['oe1'] += (float)$r['BOX_OE1'];
                            $summary_data[$cust_name][$display_pack]['oe2'] += (float)$r['BOX_OE2'];
                            $summary_data[$cust_name][$display_pack]['oe3'] += (float)$r['BOX_OE3'];
                            $summary_data[$cust_name][$display_pack]['act1'] += (float)$r['SCHED1_QTY'];
                            $summary_data[$cust_name][$display_pack]['act2'] += (float)$r['SCHED2_QTY'];
                            $summary_data[$cust_name][$display_pack]['act3'] += (float)$r['SCHED3_QTY'];

                            // --- 2. Kumpulkan Data Global Summary (Semua Customer) ---
                            if (!isset($global_summary[$display_pack])) {
                                $global_summary[$display_pack] = [
                                    'oe1' => 0, 'act1' => 0, 'oe2' => 0, 'act2' => 0, 'oe3' => 0, 'act3' => 0
                                ];
                            }
                            $global_summary[$display_pack]['oe1'] += (float)$r['BOX_OE1'];
                            $global_summary[$display_pack]['oe2'] += (float)$r['BOX_OE2'];
                            $global_summary[$display_pack]['oe3'] += (float)$r['BOX_OE3'];
                            $global_summary[$display_pack]['act1'] += (float)$r['SCHED1_QTY'];
                            $global_summary[$display_pack]['act2'] += (float)$r['SCHED2_QTY'];
                            $global_summary[$display_pack]['act3'] += (float)$r['SCHED3_QTY'];
                        }

                        if (empty($summary_data) && !$is_export) {
                            echo "<div class='alert alert-warning'>Tidak ada data ditemukan.</div>";
                        } else {
                            
                            // =========================================================================
                            // RENDER GLOBAL SUMMARY (ALL CUSTOMERS)
                            // =========================================================================
                            if (!empty($global_summary)) {
                                ksort($global_summary); // Urutkan nama box sesuai abjad
                                ?>
                                <div class="page" style="background-color: #fcfcfc;">
                                    <div class="global-title">SUMMARY KEBUTUHAN KESELURUHAN BOX (ALL CUSTOMERS)</div>
                                    <table class="rekap-table">
                                        <thead>
                                            <tr>
                                                <th rowspan="2" style="width: 25%;">Jenis Pack / Box ID</th>
                                                <th colspan="3">Bulan 1 (<?php echo $m1; ?>)</th>
                                                <th colspan="3">Bulan 2 (<?php echo $m2; ?>)</th>
                                                <th colspan="3">Bulan 3 (<?php echo $m3; ?>)</th>
                                            </tr>
                                            <tr>
                                                <th style="width: 8.3%;">OE Box</th>
                                                <th style="width: 8.3%;">Act Qty</th>
                                                <th style="width: 8.3%;" class="bg-balance">Balance</th>
                                                <th style="width: 8.3%;">OE Box</th>
                                                <th style="width: 8.3%;">Act Qty</th>
                                                <th style="width: 8.3%;" class="bg-balance">Balance</th>
                                                <th style="width: 8.3%;">OE Box</th>
                                                <th style="width: 8.3%;">Act Qty</th>
                                                <th style="width: 8.3%;" class="bg-balance">Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $g_tot_oe1 = 0; $g_tot_act1 = 0;
                                            $g_tot_oe2 = 0; $g_tot_act2 = 0;
                                            $g_tot_oe3 = 0; $g_tot_act3 = 0;

                                            foreach ($global_summary as $pack_name => $val) { 
                                                $bal1 = $val['oe1'] - $val['act1'];
                                                $bal2 = $val['oe2'] - $val['act2'];
                                                $bal3 = $val['oe3'] - $val['act3'];

                                                $g_tot_oe1 += $val['oe1']; $g_tot_act1 += $val['act1'];
                                                $g_tot_oe2 += $val['oe2']; $g_tot_act2 += $val['act2'];
                                                $g_tot_oe3 += $val['oe3']; $g_tot_act3 += $val['act3'];
                                            ?>
                                            <tr>
                                                <td><b><?php echo h($pack_name); ?></b></td>
                                                <td class="center"><?php echo fmt_num($val['oe1']); ?></td>
                                                <td class="center"><?php echo fmt_num($val['act1']); ?></td>
                                                <td class="center bg-balance <?php echo $bal1 < 0 ? 'text-red' : ''; ?>"><?php echo fmt_num($bal1); ?></td>
                                                
                                                <td class="center"><?php echo fmt_num($val['oe2']); ?></td>
                                                <td class="center"><?php echo fmt_num($val['act2']); ?></td>
                                                <td class="center bg-balance <?php echo $bal2 < 0 ? 'text-red' : ''; ?>"><?php echo fmt_num($bal2); ?></td>

                                                <td class="center"><?php echo fmt_num($val['oe3']); ?></td>
                                                <td class="center"><?php echo fmt_num($val['act3']); ?></td>
                                                <td class="center bg-balance <?php echo $bal3 < 0 ? 'text-red' : ''; ?>"><?php echo fmt_num($bal3); ?></td>
                                            </tr>
                                            <?php } ?>
                                        </tbody>
                                        <tfoot>
                                            <?php
                                                $g_t_bal1 = $g_tot_oe1 - $g_tot_act1;
                                                $g_t_bal2 = $g_tot_oe2 - $g_tot_act2;
                                                $g_t_bal3 = $g_tot_oe3 - $g_tot_act3;
                                            ?>
                                            <tr style="background:#f8f9fa; font-weight:bold;">
                                                <td class="center">GRAND TOTAL</td>
                                                <td class="center"><?php echo fmt_num($g_tot_oe1); ?></td>
                                                <td class="center"><?php echo fmt_num($g_tot_act1); ?></td>
                                                <td class="center bg-balance <?php echo $g_t_bal1 < 0 ? 'text-red' : ''; ?>"><?php echo fmt_num($g_t_bal1); ?></td>
                                                
                                                <td class="center"><?php echo fmt_num($g_tot_oe2); ?></td>
                                                <td class="center"><?php echo fmt_num($g_tot_act2); ?></td>
                                                <td class="center bg-balance <?php echo $g_t_bal2 < 0 ? 'text-red' : ''; ?>"><?php echo fmt_num($g_t_bal2); ?></td>
                                                
                                                <td class="center"><?php echo fmt_num($g_tot_oe3); ?></td>
                                                <td class="center"><?php echo fmt_num($g_tot_act3); ?></td>
                                                <td class="center bg-balance <?php echo $g_t_bal3 < 0 ? 'text-red' : ''; ?>"><?php echo fmt_num($g_t_bal3); ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <?php
                            }

                            // =========================================================================
                            // RENDER DATA PER CUSTOMER
                            // =========================================================================
                            foreach ($summary_data as $cust => $packs) {
                                ?>
                                <div class="page">
                                    <div class="cust-title"><?php echo h($cust); ?></div>
                                    <table class="rekap-table">
                                        <thead>
                                            <tr>
                                                <th rowspan="2" style="width: 25%;">Jenis Pack / Box ID</th>
                                                <th colspan="3">Bulan 1 (<?php echo $m1; ?>)</th>
                                                <th colspan="3">Bulan 2 (<?php echo $m2; ?>)</th>
                                                <th colspan="3">Bulan 3 (<?php echo $m3; ?>)</th>
                                            </tr>
                                            <tr>
                                                <th style="width: 8.3%;">OE Box</th>
                                                <th style="width: 8.3%;">Act Qty</th>
                                                <th style="width: 8.3%;" class="bg-balance">Balance</th>
                                                <th style="width: 8.3%;">OE Box</th>
                                                <th style="width: 8.3%;">Act Qty</th>
                                                <th style="width: 8.3%;" class="bg-balance">Balance</th>
                                                <th style="width: 8.3%;">OE Box</th>
                                                <th style="width: 8.3%;">Act Qty</th>
                                                <th style="width: 8.3%;" class="bg-balance">Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $tot_oe1 = 0; $tot_act1 = 0;
                                            $tot_oe2 = 0; $tot_act2 = 0;
                                            $tot_oe3 = 0; $tot_act3 = 0;

                                            ksort($packs); // Urutkan nama box sesuai abjad
                                            
                                            foreach ($packs as $pack_name => $val) { 
                                                $bal1 = $val['oe1'] - $val['act1'];
                                                $bal2 = $val['oe2'] - $val['act2'];
                                                $bal3 = $val['oe3'] - $val['act3'];

                                                $tot_oe1 += $val['oe1']; $tot_act1 += $val['act1'];
                                                $tot_oe2 += $val['oe2']; $tot_act2 += $val['act2'];
                                                $tot_oe3 += $val['oe3']; $tot_act3 += $val['act3'];
                                            ?>
                                            <tr>
                                                <td><b><?php echo h($pack_name); ?></b></td>
                                                <td class="center"><?php echo fmt_num($val['oe1']); ?></td>
                                                <td class="center"><?php echo fmt_num($val['act1']); ?></td>
                                                <td class="center bg-balance <?php echo $bal1 < 0 ? 'text-red' : ''; ?>"><?php echo fmt_num($bal1); ?></td>
                                                
                                                <td class="center"><?php echo fmt_num($val['oe2']); ?></td>
                                                <td class="center"><?php echo fmt_num($val['act2']); ?></td>
                                                <td class="center bg-balance <?php echo $bal2 < 0 ? 'text-red' : ''; ?>"><?php echo fmt_num($bal2); ?></td>

                                                <td class="center"><?php echo fmt_num($val['oe3']); ?></td>
                                                <td class="center"><?php echo fmt_num($val['act3']); ?></td>
                                                <td class="center bg-balance <?php echo $bal3 < 0 ? 'text-red' : ''; ?>"><?php echo fmt_num($bal3); ?></td>
                                            </tr>
                                            <?php } ?>
                                        </tbody>
                                        <tfoot>
                                            <?php
                                                $t_bal1 = $tot_oe1 - $tot_act1;
                                                $t_bal2 = $tot_oe2 - $tot_act2;
                                                $t_bal3 = $tot_oe3 - $tot_act3;
                                            ?>
                                            <tr style="background:#f8f9fa; font-weight:bold;">
                                                <td class="center">TOTAL</td>
                                                <td class="center"><?php echo fmt_num($tot_oe1); ?></td>
                                                <td class="center"><?php echo fmt_num($tot_act1); ?></td>
                                                <td class="center bg-balance <?php echo $t_bal1 < 0 ? 'text-red' : ''; ?>"><?php echo fmt_num($t_bal1); ?></td>
                                                
                                                <td class="center"><?php echo fmt_num($tot_oe2); ?></td>
                                                <td class="center"><?php echo fmt_num($tot_act2); ?></td>
                                                <td class="center bg-balance <?php echo $t_bal2 < 0 ? 'text-red' : ''; ?>"><?php echo fmt_num($t_bal2); ?></td>
                                                
                                                <td class="center"><?php echo fmt_num($tot_oe3); ?></td>
                                                <td class="center"><?php echo fmt_num($tot_act3); ?></td>
                                                <td class="center bg-balance <?php echo $t_bal3 < 0 ? 'text-red' : ''; ?>"><?php echo fmt_num($t_bal3); ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <?php
                            }
                        }
                    }
                    ?>
                <?php endif; ?>

<?php if (!$is_export): ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
    function exportExcel() {
        var custCode = $('input[name="CUST_CODE"]').val();
        var startDate = $('input[name="START_DATE"]').val();
        if(startDate === "") {
            alert("Pilih Start Date terlebih dahulu!");
            return;
        }
        // Redirect ke halaman yang sama namun dengan parameter &export_excel=1
        var url = window.location.pathname + "?CUST_CODE=" + encodeURIComponent(custCode) + "&START_DATE=" + encodeURIComponent(startDate) + "&export_excel=1";
        window.location.href = url;
    }
</script>
</body>
</html>
<?php endif; ?>