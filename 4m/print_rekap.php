<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';

$is_filtered = isset($_GET['filter']) ? true : false;

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');    
$cust_alias = isset($_GET['cust_alias']) ? trim($_GET['cust_alias']) : '';
$item_code  = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';
$f_status   = isset($_GET['f_status']) ? trim($_GET['f_status']) : '';

// PERBAIKAN: Tangkap kata kunci pencarian DataTables dari kiriman JavaScript URL
$dt_search  = isset($_GET['dt_search']) ? trim($_GET['dt_search']) : '';

$query = false;

if ($is_filtered) {
    $sp_start = date('Ymd', strtotime($start_date));
    $sp_end   = date('Ymd', strtotime($end_date));  
    $sp_item  = ($item_code != '') ? trim($item_code) : '%';
    $sp_cust  = ($cust_alias != '') ? trim($cust_alias) : '%';

    $sql = "{CALL REP_PCIS1(?, ?, ?, ?)}";
    $params = array($sp_start, $sp_end, $sp_item, $sp_cust);
    $query = q($sql, $params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SUMMARY REPORT PCIS</title>
    <style>
        @page { size: A4 landscape; margin: 8mm 10mm 10mm 10mm; }
        body { font-family: "Courier New", Courier, monospace; font-size: 11px; color: #000; margin: 0; padding: 0; }
        .no-print { background: #f1f1f1; padding: 8px; border-bottom: 1px solid #ccc; margin-bottom: 10px; }
        .no-print button { padding: 5px 15px; font-weight: bold; cursor: pointer; }
        .report-header { width: 100%; border-bottom: 2px dashed #000; padding-bottom: 5px; margin-bottom: 10px; }
        .report-title { font-size: 15px; font-weight: bold; text-align: center; letter-spacing: 1px; margin: 0; }
        .report-meta { width: 100%; font-size: 11px; margin-top: 5px; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 5px 2px; font-weight: bold; text-align: left; }
        .report-table td { padding: 6px 2px; vertical-align: top; border-bottom: 1px dashed #ccc; }
        .text-center { text-align: center !important; }
        .fw-bold { font-weight: bold; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body <?php echo $is_filtered ? 'onload="window.print()"' : ''; ?>>

    <div class="no-print">
        <?php if ($is_filtered): ?>
            <button onclick="window.print()">PRINT REPORT SUMMARY</button>
        <?php endif; ?>
        <button onclick="window.close()" style="background-color: #6c757d; color:white; border:none; padding:5px 10px;">TUTUP</button>
    </div>

    <div class="report-header">
        <div class="report-title">PROSES CHANGE INFORMATION SHEET (PCIS) SUMMARY REPORT</div>
        <table class="report-meta">
            <tr>
                <td width="50%"><b>Periode:</b> <?php echo date('d/m/Y', strtotime($start_date)); ?> s/d <?php echo date('d/m/Y', strtotime($end_date)); ?></td>
                <td width="50%" align="right"><b>Plant ID:</b> <?php echo isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p1' ? 'PLANT 1' : 'PLANT 2'; ?></td>
            </tr>
            <tr>
                <td style="font-size: 9px; color: #444;">Run Date: <?php echo date('d-m-Y H:i:s'); ?></td>
                <td align="right" style="font-size: 9px; color: #444;">User: <?php echo isset($_SESSION['erp_user']) ? $_SESSION['erp_user'] : 'System'; ?></td>
                <?php if(!empty($dt_search)): ?>
                    <br><span style="color:blue; font-size:10px;">Filtered by search keyword: "<?php echo htmlspecialchars($dt_search); ?>"</span>
                <?php endif; ?>
            </tr>
        </table>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th width="14%" class="text-center">NO. CONTROL</th>
                <th width="9%" class="text-center">TANGGAL</th>
                <th width="12%">CUSTOMER</th>
                <th width="13%">PART CODE</th>
                <th width="22%">PART NAME / ITEM</th>
                <th width="10%" class="text-center">MODEL</th>
                <th width="14%" class="text-center">4M CATEGORY</th>
                <th width="7%" class="text-center">STATUS</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no_data = true;
            if ($is_filtered && $query):
                while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): 
                    $current_status = isset($row['STATUS']) ? trim(strval($row['STATUS'])) : 'OPEN';
                    if ($f_status != '' && $current_status != $f_status) { continue; }
                    
                    // PERBAIKAN LOGIKA UTAMA: Lakukan pencarian string global (seperti DataTables Search) di level PHP array row
                    if (!empty($dt_search)) {
                        // Gabungkan seluruh teks baris data untuk dicocokkan dengan keyword (case-insensitive)
                        $haystack = (isset($row['CONTROL_NO']) ? $row['CONTROL_NO'] : '') . ' ' .
                                    (isset($row['CUST_ALIAS']) ? $row['CUST_ALIAS'] : '') . ' ' .
                                    (isset($row['PART_CODE']) ? $row['PART_CODE'] : '') . ' ' .
                                    (isset($row['PART_NAME']) ? $row['PART_NAME'] : '') . ' ' .
                                    (isset($row['MODEL']) ? $row['MODEL'] : '');
                        
                        // Jika kata kunci (misal: 'Diamond') tidak ditemukan pada baris data ini, lewati baris ini (skip)
                        if (stripos($haystack, $dt_search) === false) {
                            continue;
                        }
                    }

                    $no_data = false;
                    
                    $m_list = array();
                    if(!empty($row['MAN'])) $m_list[] = "Man";
                    if(!empty($row['MACHINE'])) $m_list[] = "Machine";
                    if(!empty($row['METHOD'])) $m_list[] = "Method";
                    if(!empty($row['MATERIAL'])) $m_list[] = "Material";
                    if(!empty($row['OTHER'])) $m_list[] = "Other";
                    $print_4m = implode(', ', $m_list);
            ?>
            <tr>
                <td class="text-center fw-bold"><?php echo isset($row['CONTROL_NO']) ? rtrim($row['CONTROL_NO']) : '-'; ?></td>
                <td class="text-center">
                    <?php 
                    if (isset($row['CONTROL_DATE1'])) {
                        echo $row['CONTROL_DATE1'] instanceof DateTime ? $row['CONTROL_DATE1']->format('d-m-Y') : date('d-m-Y', strtotime($row['CONTROL_DATE1']));
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
                <td class="fw-bold"><?php echo isset($row['CUST_ALIAS']) ? trim($row['CUST_ALIAS']) : '-'; ?></td>
                <td class="fw-bold"><?php echo isset($row['PART_CODE']) ? trim($row['PART_CODE']) : (isset($row['PART_NO']) ? trim($row['PART_NO']) : '-'); ?></td>
                <td><?php echo isset($row['PART_NAME']) ? trim($row['PART_NAME']) : (isset($row['ITEM_NAME']) ? trim($row['ITEM_NAME']) : '-'); ?></td>
                <td class="text-center fw-bold text-muted"><?php echo isset($row['MODEL']) ? trim($row['MODEL']) : '-'; ?></td>
                <td class="text-center fw-bold"><?php echo !empty($print_4m) ? $print_4m : '-'; ?></td>
                <td class="text-center fw-bold"><?php echo $current_status; ?></td>
            </tr>
            <?php 
                endwhile;
            endif;
            
            if ($no_data): 
            ?>
            <tr>
                <td colspan="8" class="text-center" style="padding: 30px; font-style: italic;">
                    Tidak ada data rekapitulasi ditemukan.
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>