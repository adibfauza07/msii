<?php
require_once __DIR__ . '/../config/database_p1.php';

$sopID = isset($_GET['sop']) ? $_GET['sop'] : '';

if (empty($sopID)) {
    die("Error: Parameter SOP ID tidak ditemukan.");
}


$sopRef = "";
$sopDate = "";
$qHead = sqlsrv_query($conn, "SELECT SOP_REF, SOP_SDATE FROM SOP WHERE SOP_ID = ?", array($sopID));
if ($qHead && $rHead = sqlsrv_fetch_array($qHead, SQLSRV_FETCH_ASSOC)) {
    $sopRef = $rHead['SOP_REF'];
    $sopDate = ($rHead['SOP_SDATE'] instanceof DateTime) ? $rHead['SOP_SDATE']->format('d-M-Y') : $rHead['SOP_SDATE'];
}


$sqlSP = "EXEC TAGLIST @SOP_ID = ?";
$stmt = sqlsrv_query($conn, $sqlSP, array($sopID));

$dataReport = [];
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $dataReport[] = $row;
    }
} else {
    // JIKA SP GAGAL ATAU TIDAK ADA, GUNAKAN MANUAL QUERY (FALLBACK AMAN)
    $sqlManual = "SELECT 
                    ISNULL(L.LOC_CODE, T.LOC_CODE) AS LOC_CODE, 
                    T.TAG_NO, 
                    T.ITEM_CODE, 
                    I.ITEM_NAME, 
                    T.TAG_QTY, 
                    T.TAG_REM 
                  FROM TAGS T 
                  LEFT JOIN ITEMS I ON (T.ITEM_CODE = I.ITEM_CODE OR LTRIM(RTRIM(T.ITEM_CODE)) = LTRIM(RTRIM(I.ITEM_CODE)))
                  LEFT JOIN LOC L ON T.LOC_ID = L.LOC_ID
                  WHERE T.SOP_ID = ?
                  ORDER BY ISNULL(L.LOC_CODE, T.LOC_CODE) ASC, T.TAG_NO ASC";
                  
    $stmtMan = sqlsrv_query($conn, $sqlManual, array($sopID));
    if ($stmtMan) {
        while ($row = sqlsrv_fetch_array($stmtMan, SQLSRV_FETCH_ASSOC)) {
            $dataReport[] = $row;
        }
    } else {
        die("<div style='color:red; font-family:sans-serif;'><b>Error Query Database:</b><br>" . print_r(sqlsrv_errors(), true) . "</div>");
    }
}

// Dinamis Nama Perusahaan (Plant 1 / Plant 2)
$companyName = "PT. IMC TEKNO INDONESIA";
if (isset($_SESSION['active_plant']) && $_SESSION['active_plant'] == 'p2') {
    $companyName = "PT. IMC TEKNO INDONESIA PLANT 1";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tag List - <?php echo $sopID; ?></title>
    <style>
        /* RESET & DASAR */
        body { 
            font-family: Arial, Helvetica, sans-serif; 
            font-size: 11px; 
            color: #000; 
            background: #fff; 
            margin: 0;
            padding: 20px;
        }
        
        .page-container {
            width: 100%; 
            max-width: 210mm; 
            margin: 0 auto;
        }

        /* HEADER DOKUMEN (Bisa di-repeat di setiap halaman saat print) */
        thead.report-header {
            display: table-header-group;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 5px;
        }
        
        .kop-kiri { line-height: 1.3; }
        .kop-kiri .company { font-size: 14px; font-weight: normal; text-transform: uppercase;}
        .kop-kiri .dept { font-size: 11px; }
        .kop-kiri .print-date { font-size: 11px; margin-top: 5px;}
        
        .kop-tengah { text-align: center; flex-grow: 1; margin-top: -10px;}
        .kop-tengah .title { font-size: 16px; font-weight: normal; letter-spacing: 0.5px; }
        
        .kop-kanan { text-align: right; font-size: 11px; line-height: 1.4; }

        /* GARIS BAWAH HEADER (Garis Ganda) */
        .double-line {
            border-top: 1.5px solid #000;
            border-bottom: 1px solid #000;
            height: 2px;
            margin-bottom: 5px;
        }

        /* TABEL REPORT */
        .report-table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 11px; 
        }
        .report-table th { 
            border-bottom: 1.5px solid #000; 
            font-weight: normal; 
            padding: 5px 2px; 
            text-align: left;
        }
        .report-table td { 
            padding: 3px 2px; 
            vertical-align: top; 
        }
        
        /* Lebar Kolom Meniru Gambar */
        .c-tag  { width: 12%; }
        .c-loc  { width: 8%; }
        .c-code { width: 15%; }
        .c-name { width: 35%; }
        .c-qty  { width: 15%; text-align: right; }
        .c-rem  { width: 15%; text-align: center; }

        .group-header { 
            font-weight: bold; 
            font-size: 12px; 
            padding-top: 10px !important; 
            padding-bottom: 5px !important;
            letter-spacing: 5px; /* Spasi seperti M O L */
        }

        /* TANDA TANGAN DI AKHIR */
        .sign-box-container {
            width: 100%;
            display: flex;
            justify-content: flex-end;
            margin-top: 40px;
        }
        .sign-table {
            border-collapse: collapse;
            text-align: center;
            font-size: 11px;
            width: 350px;
        }
        .sign-table th, .sign-table td {
            border: 1px solid #000;
            padding: 5px;
            width: 33.33%;
        }
        .sign-table th { font-weight: normal; text-decoration: underline; }
        .sign-table td { height: 60px; } /* Ruang ttd */

        /* PRINT SETTINGS */
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; font-weight: bold; margin: 0 5px; }
        .btn:hover { background: #e0e0e0; }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            .page-container { max-width: 100%; }
            @page { size: portrait; margin: 10mm; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print">
        <button class="btn" onclick="window.close()">&laquo; Tutup</button>
        <button class="btn" onclick="window.print()">Print Report</button>
    </div>

    <div class="page-container">
        
        <table style="width: 100%; border-collapse: collapse;">
            <thead class="report-header">
                <tr>
                    <td colspan="6">
                        <div class="header-top">
                            <div class="kop-kiri">
                                <div class="company"><?php echo $companyName; ?></div>
                                <div class="dept">Commercial Business</div>
                                <div class="print-date">Print Date: <?php echo date('d-M-Y H:i:s'); ?></div>
                            </div>
                            
                            <div class="kop-tengah">
                                <div class="title">TAG LIST</div>
                            </div>
                            
                            <div class="kop-kanan">
                                SOP Date: <?php echo $sopDate; ?><br>
                                Ref#: <?php echo $sopRef; ?>
                            </div>
                        </div>
                        <div class="double-line"></div>
                    </td>
                </tr>
                <tr>
                    <th class="c-tag">Tag#</th>
                    <th class="c-loc">Loc</th>
                    <th colspan="2">I&nbsp;&nbsp;&nbsp;T&nbsp;&nbsp;&nbsp;E&nbsp;&nbsp;&nbsp;M&nbsp;&nbsp;&nbsp;S</th>
                    <th class="c-qty">TAG Qty</th>
                    <th class="c-rem">REMARKS</th>
                </tr>
            </thead>
            
            <tbody class="report-table">
                <?php 
                if (empty($dataReport)) {
                    echo "<tr><td colspan='6' style='text-align:center; padding: 30px;'>Tidak ada data.</td></tr>";
                } else {
                    $currentLoc = null;

                    foreach ($dataReport as $row) {
                        
                        $loc = isset($row['LOC_CODE']) ? trim($row['LOC_CODE']) : '';
                        
                        // Cetak Header Group (Contoh: M O L)
                        if ($loc !== $currentLoc && $loc !== '') {
                            // Mencetak lokasi dengan huruf kapital dan jarak
                            echo "<tr><td colspan='6' class='group-header'>{$loc}</td></tr>";
                            $currentLoc = $loc;
                        }

                        // Siapkan Data Baris
                        $tagNo = isset($row['TAG_NO']) ? $row['TAG_NO'] : '';
                        $code  = isset($row['ITEM_CODE']) ? $row['ITEM_CODE'] : '';
                        $name  = isset($row['ITEM_NAME']) ? $row['ITEM_NAME'] : '';
                        $rem   = isset($row['TAG_REM']) ? $row['TAG_REM'] : '';
                        
                        // Qty (2 angka di belakang koma)
                        $qtyRaw = isset($row['TAG_QTY']) ? (float)$row['TAG_QTY'] : 0;
                        $qty = number_format($qtyRaw, 2);

                        // Cetak Baris
                        echo "<tr>
                                <td class='c-tag'>{$tagNo}</td>
                                <td class='c-loc'>{$loc}</td>
                                <td class='c-code'>{$code}</td>
                                <td class='c-name'>{$name}</td>
                                <td class='c-qty'>{$qty}</td>
                                <td class='c-rem'>{$rem}</td>
                              </tr>";
                    }
                }
                ?>
                <tr><td colspan="6" style="border-top: 1.5px solid #000; padding:0;"></td></tr>
            </tbody>
        </table>

        <?php if (!empty($dataReport)): ?>
        <div class="sign-box-container">
            <table class="sign-table">
                <tr>
                    <th>Approved by</th>
                    <th>Checked by</th>
                    <th>Prepared by</th>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

    </div>

</body>
</html>