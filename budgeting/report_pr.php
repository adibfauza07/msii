<?php
require_once 'config.php';

// Fungsi sanitasi standar PHP 5.4
function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

// Tangkap Parameter Nomor PR
$pr_no = isset($_GET['pr_no']) ? trim($_GET['pr_no']) : '';

if (empty($pr_no)) {
    die("Nomor PR tidak valid atau tidak ditemukan.");
}

// ==========================================================
// 1. AMBIL DATA HEADER
// ==========================================================
$sql_header = "
    SELECT 
        ph.pr_no, 
        ph.pr_date, 
        md.department_name, 
        ph.created_by
    FROM PR_Header ph
    LEFT JOIN Master_Department md ON ph.department_id = md.department_id
    WHERE ph.pr_no = ?
";
$stmt_header = sqlsrv_query($conn, $sql_header, array($pr_no));

if ($stmt_header === false || !sqlsrv_has_rows($stmt_header)) {
    die("Data Purchase Request tidak ditemukan.");
}

$header = sqlsrv_fetch_array($stmt_header, SQLSRV_FETCH_ASSOC);
$pr_date_formatted = $header['pr_date'] instanceof DateTime ? $header['pr_date']->format('d / m / Y') : date('d / m / Y', strtotime($header['pr_date']));

// ==========================================================
// 2. AMBIL DATA DETAIL & AGREGASI REMARK
// ==========================================================
$sql_detail = "
    SELECT 
        pd.item_code, 
        mi.item_name, 
        pd.qty_request, 
        mi.uom, 
        pd.unit_price, 
        pd.remark
    FROM PR_Detail pd
    LEFT JOIN Master_Item mi ON pd.item_code = mi.item_code
    WHERE pd.pr_no = ?
    ORDER BY pd.pr_detail_id ASC
";
$stmt_detail = sqlsrv_query($conn, $sql_detail, array($pr_no));

$details = array();
$aggregated_remarks = array(); 
$item_number = 1;

if ($stmt_detail !== false) {
    while ($row = sqlsrv_fetch_array($stmt_detail, SQLSRV_FETCH_ASSOC)) {
        $details[] = $row;
        
        // Kumpulkan remark jika tidak kosong
        if (!empty(trim($row['remark']))) {
            $aggregated_remarks[] = "<strong>Item " . $item_number . " (" . h($row['item_name']) . "):</strong> " . h($row['remark']);
        }
        $item_number++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak PR - <?php echo h($pr_no); ?></title>
    <style>
        /* Reset & Base Styles */
        body { font-family: "Times New Roman", Times, serif; font-size: 11px; color: #000; background: #e0e0e0; margin: 0; padding: 20px; }
        
        /* Ukuran Kertas A5 Landscape (210mm x 148.5mm) */
        .page { width: 210mm; min-height: 148.5mm; padding: 5mm 10mm; margin: 0 auto; background: #fff; box-shadow: 0 0 5px rgba(0,0,0,0.2); box-sizing: border-box; }

        /* Typography & Layout */
        h2 { text-align: center; font-size: 16px; margin: 0; padding-top: 10px; }
        .company-name { font-size: 13px; font-weight: bold; margin: 0; }
        .company-sub { font-size: 10px; margin: 0 0 5px 0; }

        /* Tables */
        table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        th, td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
        .table-header td { padding: 2px 4px; border: 1px solid #000; }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        /* Layout Alignments */
        .header-section { margin-bottom: 5px; display: flex; justify-content: space-between; align-items: flex-end; }
        .grid-header-left { width: 230px; }
        .grid-header-right { width: 180px; }

        /* Main Item Table */
        .table-items th { font-size: 10px; font-weight: bold; text-align: center; }
        .table-items td { height: 16px; font-size: 10px; }

        /* Footer / Notes / Signatures */
        .note-box { height: 60px; width: 50%; border-right: 1px solid #000; vertical-align: top; font-size: 10px;}
        .note-content { margin-top: 2px; line-height: 1.3; }
        .sign-box { width: 16.66%; text-align: center; vertical-align: bottom; height: 60px; }
        .sign-title { text-align: center; border-bottom: 1px solid #000; padding: 2px; font-size: 10px;}
        .footer-text { font-size: 9px; font-style: italic; margin-top: 3px; line-height: 1.2; }

        /* Pengaturan Khusus Printer (Wajib untuk A5 Landscape) */
        @media print {
            @page { size: A5 landscape; margin: 5mm; }
            body { background: #fff; padding: 0; margin: 0; }
            .page { margin: 0; box-shadow: none; border: none; }
            .btn-print { display: none; }
        }
        
        .btn-print { display: block; width: 210mm; margin: 0 auto 20px auto; padding: 10px; background: #0056b3; color: #fff; text-align: center; cursor: pointer; border: none; font-weight: bold;}
    </style>
</head>
<body>

    <button class="btn-print" onclick="window.print()">Cetak Dokumen PR (A5 Landscape)</button>

    <div class="page">
        <!-- HEADER KOP SURAT -->
        <div class="header-section">
            <div class="grid-header-left">
                <div class="company-name">PT. IMC TEKNO INDONESIA</div>
                <div class="company-sub">Commercial Bussines</div>
                
                <table class="table-header">
                    <tr>
                        <td width="30%">No. PR</td>
                        <td width="70%" class="text-center" style="font-size: 12px; font-weight: bold; letter-spacing: 1px;">
                            <?php echo h($header['pr_no']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Department</td>
                        <td class="text-center"><?php echo h($header['department_name']); ?></td>
                    </tr>
                </table>
            </div>
            
            <div style="flex:1; text-align:center;">
                <h2>PURCHASE REQUISITION</h2>
            </div>

            <div class="grid-header-right">
                <table class="table-header">
                    <tr>
                        <td width="30%">Date</td>
                        <td width="70%" class="text-center"><?php echo h($pr_date_formatted); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- TABEL UTAMA BARANG -->
        <table class="table-items">
            <thead>
                <tr>
                    <th width="4%">No.</th>
                    <th width="32%">Description</th>
                    <th width="15%">Spesification</th>
                    <th width="8%">Quantity</th>
                    <th width="6%">Unit</th>
                    <th width="4%">L</th>
                    <th width="12%">Price</th>
                    <th width="9%">ETA IMC</th>
                    <th width="10%">Remark</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // [PENTING] Karena kertas A5 sangat pendek, batas maksimal baris diturunkan menjadi 7
                $max_rows = 7; 
                $total_items = count($details);
                
                for ($i = 0; $i < $max_rows; $i++) {
                    if ($i < $total_items) {
                        $row = $details[$i];
                        $has_remark = !empty(trim($row['remark'])) ? '*' : '';
                        
                        echo "<tr>";
                        echo "<td class='text-center'>" . ($i + 1) . "</td>";
                        echo "<td>" . h($row['item_name']) . "</td>";
                        echo "<td></td>";
                        echo "<td class='text-center'>" . rtrim(rtrim(number_format($row['qty_request'], 2, ',', '.'), '0'), ',') . "</td>";
                        echo "<td class='text-center'>" . h($row['uom']) . "</td>";
                        echo "<td></td>"; 
                        echo "<td class='text-right'>" . number_format($row['unit_price'], 0, ',', '.') . "</td>";
                        echo "<td></td>"; 
                        echo "<td class='text-center font-weight-bold'>{$has_remark}</td>"; 
                        echo "</tr>";
                    } else {
                        // Baris filler kosong
                        echo "<tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
                    }
                }
                ?>
            </tbody>
        </table>

        <!-- BAGIAN CATATAN (REMARK PANJANG) & TANDA TANGAN -->
        <table style="margin-top: -1px;">
            <tr>
                <td class="note-box" rowspan="2">
                    <strong>Note / Remarks:</strong>
                    <div class="note-content">
                        <?php 
                        if (count($aggregated_remarks) > 0) {
                            echo implode("<br>", $aggregated_remarks);
                        } else {
                            echo "-";
                        }
                        ?>
                    </div>
                </td>
                <td class="sign-title">Approved</td>
                <td class="sign-title">Checked</td>
                <td class="sign-title">Prepared</td>
            </tr>
            <tr>
                <td class="sign-box"></td>
                <td class="sign-box"></td>
                <td class="sign-box">
                    <?php echo h($header['created_by']); ?>
                </td>
            </tr>
        </table>

        <!-- TEKS FOOTER BAWAH -->
        <div class="footer-text">
            1. Purchasing;&nbsp;&nbsp;&nbsp;&nbsp; 2. Finance;&nbsp;&nbsp;&nbsp;&nbsp; 3. Dept. YBS<br>
            FM.CO-01-66 (Revisi 4 Tgl. 01 Juni 2012)
        </div>
    </div>

</body>
</html>

<?php
if (isset($stmt_header) && $stmt_header !== false) sqlsrv_free_stmt($stmt_header);
if (isset($stmt_detail) && $stmt_detail !== false) sqlsrv_free_stmt($stmt_detail);
if ($conn !== false) sqlsrv_close($conn);
?>