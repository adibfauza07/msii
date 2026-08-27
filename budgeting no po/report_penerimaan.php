<?php
require_once 'config.php';

// Fungsi anti-XSS untuk PHP 5.4
function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

$id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($id)) {
    die("Error: ID Transaksi tidak ditemukan.");
}

// ==========================================================
// 1. AMBIL DATA HEADER TRANSAKSI
// ==========================================================
$sqlHead = "SELECT 
                rh.receive_no, 
                rh.receive_date, 
                rh.plant_id, 
                rh.received_by,
                md.department_name
            FROM Receive_Header rh
            LEFT JOIN Master_Department md ON rh.department_id = md.department_id
            WHERE rh.receive_no = ?";
$qHead = sqlsrv_query($conn, $sqlHead, array($id));

if ($qHead === false || !($rHead = sqlsrv_fetch_array($qHead, SQLSRV_FETCH_ASSOC))) {
    die("Error: Data transaksi penerimaan tidak ditemukan.");
}

$tranDateObj = ($rHead['receive_date'] instanceof DateTime) ? $rHead['receive_date'] : new DateTime($rHead['receive_date']);
$tranDate = $tranDateObj->format('d-F-Y');
$periode_bulan = $tranDateObj->format('F Y');
$plant = $rHead['plant_id'];

// ==========================================================
// 2. AMBIL DATA DETAIL BARANG & HARGA (Gabungan ICL & Budget)
// ==========================================================
$sqlDetail = "SELECT 
                rd.item_code, 
                mi.item_name, 
                mi.uom, 
                rd.qty_in,
                pd.pr_no,
                ISNULL(pd.unit_price, 0) AS unit_price,
                (rd.qty_in * ISNULL(pd.unit_price, 0)) AS subtotal,
                v.vendor_name
              FROM Receive_Det rd 
              INNER JOIN Master_Item mi ON rd.item_code = mi.item_code
              LEFT JOIN PR_Detail pd ON rd.pr_detail_id = pd.pr_detail_id
              LEFT JOIN Quotation q ON pd.quote_id = q.quote_id
              LEFT JOIN Master_Vendor v ON q.vendor_id = v.vendor_id
              WHERE rd.receive_no = ?
              ORDER BY rd.item_code ASC";

$qDet = sqlsrv_query($conn, $sqlDetail, array($id));

if ($qDet === false) {
    die("<div style='background:#ffcccc; padding:20px; border:2px solid red;'><b>Error Detail:</b><br>" . print_r(sqlsrv_errors(), true) . "</div>");
}

$dataDetail = array();
$totalQty = 0; 
$grandTotalAmt = 0;
$listPR = array();
$listSupplier = array();

while ($row = sqlsrv_fetch_array($qDet, SQLSRV_FETCH_ASSOC)) {
    $totalQty += (float)$row['qty_in']; 
    $grandTotalAmt += (float)$row['subtotal'];
    $dataDetail[] = $row;
    
    // Kumpulkan Nomor PR unik
    if (!empty($row['pr_no']) && !in_array($row['pr_no'], $listPR)) {
        $listPR[] = $row['pr_no'];
    }
    // Kumpulkan Nama Supplier unik
    if (!empty($row['vendor_name']) && !in_array($row['vendor_name'], $listSupplier)) {
        $listSupplier[] = $row['vendor_name'];
    }
}

$stringPR = implode(", ", $listPR);
$stringSupplier = implode(", ", $listSupplier);

// ==========================================================
// 3. LOGIKA PEMBAGIAN HALAMAN (Maksimal 6 baris per halaman)
// ==========================================================
$maxRowsPerPage = 6; 
$chunks = array_chunk($dataDetail, $maxRowsPerPage);
if (empty($chunks)) {
    $chunks = array(array()); 
}
$totalPages = count($chunks);

// Info Perusahaan Berdasarkan Plant
$companyName = "PT. IMC TEKNO INDONESIA PLANT 1";
$companyAddress = "Kawasan Berikat, NSS Indonesia<br>Kota Bukit Indah Dangdeur Bungursari<br>Kab. Purwakarta, Jawa Barat 41181<br>Phone : (0264)351440";

if (strtoupper($plant) == 'P2') {
    $companyName = "PT. IMC TEKNO INDONESIA PLANT 2";
    $companyAddress = "Kawasan Industri Kota Bukit Indah<br>Blok A-III No. 15E Dangdeur Bungursari<br>Kab. Purwakarta, Jawa Barat 41181<br>Phone : (0264)351440";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Gabungan ICL & Budgeting - <?php echo h($rHead['receive_no']); ?></title>
    <style>
        /* BASE & RESET */
        body { font-family: Arial, Helvetica, sans-serif; background: #e0e0e0; margin: 0; padding: 20px; color: #000; }
        .page { background: #fff; width: 210mm; height: 297mm; margin: 0 auto 20px auto; box-shadow: 0 0 5px rgba(0,0,0,0.2); box-sizing: border-box; padding: 5mm 10mm; display: flex; flex-direction: column; justify-content: space-between; }
        
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn { padding: 10px 20px; font-size: 14px; background-color: #0056b3; color: white; border: none; cursor: pointer; border-radius: 4px; font-weight: bold; }
        
        /* PEMBAGI HALAMAN (A4 DIBAGI 2) */
        .section-half { height: 48%; position: relative; box-sizing: border-box; }
        .cut-line { border-top: 1px dashed #999; margin: 2mm 0; position: relative; text-align: center; font-size: 9px; color: #666; letter-spacing: 2px;}
        .cut-line span { background: #fff; padding: 0 5px; position: relative; top: -6px; }

        /* ==================== CSS ICL (ATAS) ==================== */
        .icl-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 5px; }
        .icl-company { font-size: 12px; font-weight: bold; text-decoration: underline; margin-bottom: 2px;}
        .icl-title { font-size: 12px; font-weight: bold; margin-bottom: 2px;}
        .icl-addr { font-size: 9px; line-height: 1.1; color: #333;}
        .icl-page-info { font-size: 9px; font-weight: bold; text-align: right; margin-bottom: 2px; }
        .icl-sign { border-collapse: collapse; font-size: 9px; text-align: center; margin-left: auto; } /* PERBAIKAN RATA KANAN */
        .icl-sign th, .icl-sign td { border: 1px solid #000; padding: 2px; width: 65px; }
        .icl-sign td { height: 30px; vertical-align: bottom; padding-bottom: 2px; }
        .icl-meta { width: 100%; font-size: 10px; font-weight: bold; margin-bottom: 5px; }
        .icl-meta td { padding: 1px 0; vertical-align: top;}
        .icl-table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 5px; }
        .icl-table th, .icl-table td { border: 1px solid #000; padding: 2px 4px; }
        .icl-table th { font-weight: bold; text-align: center; vertical-align: middle; font-size: 9px; }
        .icl-legend { width: 100%; font-size: 9px; border-collapse: collapse; line-height: 1.1; margin-top: 5px; }
        .icl-legend td { padding: 1px 0; vertical-align: top; }
        .icl-footer { margin-top: 5px; font-size: 9px; }

        /* ==================== CSS BUDGETING (BAWAH) ==================== */
        .bdg-wrap { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 5px; }
        .bdg-logo { font-style: italic; font-weight: bold; font-size: 14px; letter-spacing: 2px; }
        
        /* PERBAIKAN ALIGNMENT TABEL TANDA TANGAN */
        .bdg-sign-container { width: 100%; display: flex; justify-content: flex-end; }
        .bdg-sign { border-collapse: collapse; text-align: center; font-size: 9px; margin-left: auto; }
        .bdg-sign th, .bdg-sign td { border: 1px solid #000; padding: 2px; width: 65px;}
        .bdg-sign th { font-weight: normal; }
        .bdg-sign td.ttd-box { height: 30px; vertical-align: bottom; }
        
        /* PERBAIKAN JUDUL BUDGETING FORM */
        .bdg-title-area { text-align: center; margin-top: -15px; margin-bottom: 10px; }
        .bdg-title { font-size: 13px; font-weight: bold; }
        .bdg-periode { font-size: 11px; margin-top: 2px;}
        .bdg-periode span { border-bottom: 1px solid #000; display: inline-block; min-width: 150px; }
        
        .bdg-info { border-collapse: collapse; font-size: 10px; width: 100%; }
        .bdg-info td { padding: 1px 5px 1px 0; vertical-align: top; }
        .line-bottom { border-bottom: 1px solid #000; display: inline-block; min-width: 160px; }
        .static-dept { font-size: 8px; line-height: 1.2; color: #555; }
        
        .bdg-table { border-collapse: collapse; width: 100%; font-size: 10px; margin-top: 5px; }
        .bdg-table th, .bdg-table td { border: 1px solid #000; padding: 3px; }
        .bdg-table th { text-align: center; font-weight: normal; }
        .bdg-table td { height: 16px; }
        .bdg-footer { margin-top: 5px; font-size: 9px; display: flex; justify-content: space-between; line-height: 1.3; }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none; }
            .page { width: 100%; height: 297mm; padding: 5mm 10mm; margin: 0; box-shadow: none; border: none; }
            .page-break { page-break-after: always; }
            @page { size: A4 portrait; margin: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn" onclick="window.print()"><i class="fas fa-print"></i> Cetak Gabungan A4 (ICL + Budgeting)</button>
    </div>

    <?php 
    foreach ($chunks as $pageIndex => $chunk): 
        $pageNumber = $pageIndex + 1;
        $isLastPage = ($pageNumber == $totalPages);
    ?>

    <div class="page <?php echo !$isLastPage ? 'page-break' : ''; ?>">
        
        <!-- ========================================================================= -->
        <!-- BAGIAN 1: INCOMING CHECK LIST (ATAS)                                      -->
        <!-- ========================================================================= -->
        <div class="section-half">
            <div class="icl-header">
                <div style="flex: 1;">
                    <div class="icl-company"><?php echo $companyName; ?></div>
                    <div class="icl-title">INCOMING CHECK LIST</div>
                    <div class="icl-addr"><?php echo $companyAddress; ?></div>
                </div>
                <div>
                    <div class="icl-page-info">Page <?php echo $pageNumber; ?> of <?php echo $totalPages; ?></div>
                    <table class="icl-sign">
                        <tr><th>CHECKER</th><th>RECEIVER</th></tr>
                        <tr><td></td><td><?php echo h($rHead['received_by']); ?></td></tr>
                    </table>
                </div>
            </div>

            <table class="icl-meta">
                <tr>
                    <td style="width: 8%;">Date</td>
                    <td style="width: 2%;">:</td>
                    <td style="width: 45%; font-weight: normal;"><?php echo $tranDate; ?></td>
                    <td style="width: 12%;">ICL Number</td>
                    <td style="width: 2%;">:</td>
                    <td style="width: 31%; font-weight: normal;"><?php echo h($rHead['receive_no']); ?></td>
                </tr>
                <tr>
                    <td>Supplier</td>
                    <td>:</td>
                    <td style="font-weight: normal;"><?php echo h($stringSupplier); ?></td>
                    <td>No. PR</td>
                    <td>:</td>
                    <td style="font-weight: normal;"><?php echo h($stringPR); ?></td>
                </tr>
                <tr>
                    <td>Dept</td>
                    <td>:</td>
                    <td style="font-weight: normal;"><?php echo h($rHead['department_name']); ?></td>
                    <td></td><td></td><td></td>
                </tr>
            </table>

            <table class="icl-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 12%;">CODE</th>
                        <th rowspan="2" style="width: 35%;">NAME</th>
                        <th rowspan="2" style="width: 5%;">UNIT</th>
                        <th rowspan="2" style="width: 10%;">Incoming<br>QTY</th>
                        <th colspan="2" style="width: 10%;">JUDGEMENT</th>
                        <th rowspan="2" style="width: 14%;">PROBLEM</th>
                        <th rowspan="2" style="width: 14%;">RECOMENDATION</th>
                    </tr>
                    <tr>
                        <th style="width: 5%;">OK</th>
                        <th style="width: 5%;">HOLD</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($chunk as $row) {
                        $qty = number_format($row['qty_in'], 2, '.', ',');
                        echo "<tr>
                                <td>" . h($row['item_code']) . "</td>
                                <td>" . h($row['item_name']) . "</td>
                                <td style='text-align: center; text-transform: capitalize;'>" . h($row['uom']) . "</td>
                                <td style='text-align: right;'>{$qty}</td>
                                <td></td><td></td><td></td><td></td> 
                              </tr>";
                    }

                    // Filler rows
                    $emptyRows = $maxRowsPerPage - count($chunk);
                    for ($i = 0; $i < $emptyRows; $i++) {
                        echo "<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
                    }
                    ?>
                </tbody>
                <tfoot>
                    <?php if ($isLastPage): ?>
                        <tr>
                            <td colspan="3" style="text-align: right; font-weight: bold; border: 1px solid #000; padding: 2px 4px;">TOTAL :</td>
                            <td style="text-align: right; border: 1px solid #000; padding: 2px 4px;"><?php echo number_format($totalQty, 2, '.', ','); ?></td>
                            <td colspan="4" style="border: 1px solid #000;"></td>
                        </tr>
                    <?php endif; ?>
                </tfoot>
            </table>

            <table class="icl-legend">
                <tr>
                    <td style="width: 6%;">White</td>
                    <td style="width: 16%;">: Acc + Finnace</td>
                    <td style="width: 6%;">Green</td>
                    <td style="width: 16%;">: Checker</td>
                    <td style="width: 16%;">R : Raw Material</td>
                    <td style="width: 16%;">M : Machine</td>
                </tr>
                <tr>
                    <td>Yellow</td>
                    <td>: Purchasing</td>
                    <td>Pink</td>
                    <td>: Receiver</td>
                    <td>V : Vendor</td>
                    <td>A : ATK</td>
                </tr>
                <tr>
                    <td></td><td></td><td></td><td></td>
                    <td>P : Packing</td>
                    <td>O : Other</td>
                </tr>
            </table>
            <div class="icl-footer">FM.CO.01-41 (Revisi 5 : Tgl.1 Mar 26)</div>
        </div>

        <!-- GARIS POTONG TENGAH -->
        <div class="cut-line"><span>✂ CUT HERE ✂</span></div>

        <!-- ========================================================================= -->
        <!-- BAGIAN 2: BUDGETING FORM (BAWAH)                                          -->
        <!-- ========================================================================= -->
        <div class="section-half" style="padding-top: 5mm;">
            
            <div class="bdg-wrap">
                <div style="flex: 1;">
                    <div class="bdg-logo">I . M . C</div>
                </div>
                <div>
                    <!-- Kotak tanda tangan dipastikan menempel ke pojok kanan -->
                    <table class="bdg-sign">
                        <tr>
                            <th colspan="2">Checked By</th>
                            <th colspan="2">Prepared By</th>
                        </tr>
                        <tr>
                            <td width="65">Finn / Acc</td>
                            <td width="65"></td>
                            <td width="65">Purchasing</td>
                            <td width="65">Departement</td>
                        </tr>
                        <tr>
                            <td class="ttd-box"></td>
                            <td class="ttd-box"></td>
                            <td class="ttd-box">Lika</td>
                            <td class="ttd-box"></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Posisi Judul diturunkan dan dirapikan di tengah -->
            <div class="bdg-title-area">
                <div class="bdg-title">BUDGETING FORM</div>
                <div class="bdg-periode">Periode : <span><?php echo $periode_bulan; ?></span></div>
            </div>

            <div class="bdg-wrap" style="margin-bottom: 5px;">
                <div style="width: 50%;">
                    <table class="bdg-info">
                        <tr><td width="60">Date</td><td width="10">:</td><td><span class="line-bottom"><?php echo $tranDate; ?></span></td></tr>
                        <tr><td>No PR / PO</td><td>:</td><td><span class="line-bottom"><?php echo h($stringPR); ?></span></td></tr>
                        <tr><td>Supplier</td><td>:</td><td><span class="line-bottom"><?php echo h($stringSupplier); ?></span></td></tr>
                    </table>
                </div>
                <div style="width: 48%;">
                    <table class="bdg-info">
                        <tr><td width="65">Nomor</td><td width="10">:</td><td><span class="line-bottom"><?php echo h($rHead['receive_no']); ?></span></td></tr>
                        <tr>
                            <td style="padding-top: 5px;">Departement</td>
                            <td style="padding-top: 5px;">:</td>
                            <td style="padding-top: 5px;">
                                <strong><?php echo h($rHead['department_name']); ?></strong><br>
                                <div class="static-dept">
                                    Acc, Ex, PE, Fin, HRD-GA, PPIC, DCC, VC<br>
                                    Purc, Prod, QC, Mrkt, Mold, Maint
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <table class="bdg-table">
                <thead>
                    <tr>
                        <th width="4%">No</th>
                        <th width="36%">Description</th>
                        <th width="15%">Account No</th>
                        <th width="8%">Qty per</th>
                        <th width="7%">Unit</th>
                        <th width="13%">Price</th>
                        <th width="17%">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1 + ($pageIndex * $maxRowsPerPage);
                    foreach ($chunk as $row) {
                        $qty = number_format($row['qty_in'], 2, ',', '.');
                        $price = number_format($row['unit_price'], 0, ',', '.');
                        $amt = number_format($row['subtotal'], 0, ',', '.');
                        
                        echo "<tr>
                                <td style='text-align:center;'>{$no}</td>
                                <td>" . h($row['item_name']) . "</td>
                                <td></td>
                                <td style='text-align:center;'>{$qty}</td>
                                <td style='text-align:center;'>" . h($row['uom']) . "</td>
                                <td style='text-align:right;'>{$price}</td>
                                <td style='text-align:right;'>{$amt}</td>
                              </tr>";
                        $no++;
                    }

                    // Filler rows
                    for ($i = 0; $i < $emptyRows; $i++) {
                        echo "<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
                    }
                    ?>
                </tbody>
                <?php if ($isLastPage): ?>
                <tfoot>
                    <tr>
                        <td colspan="5" style="border: none; border-right: 1px solid #000;"></td>
                        <td style="text-align: center; font-weight: bold;">Total</td>
                        <td style="text-align: right; font-weight: bold; background-color: #f9f9f9;">Rp <span style="float:right;"><?php echo number_format($grandTotalAmt, 0, ',', '.'); ?></span></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>

            <div class="bdg-footer">
                <div>
                    Rate &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <br><br>
                    - Account Number diisi oleh Finance/Accounting<br>
                    - Acc/Fin : White &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Purch : Hijau &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Depart ybs : Kuning
                    <div style="font-style: italic; margin-top: 5px;">FM.AC.038-009-00 (Rev.00 Tgl.01 Oktober 2017)</div>
                </div>
                <div style="padding-top: 30px; margin-right: 150px;">:</div>
            </div>
        </div>

    </div>
    <?php endforeach; ?>

</body>
</html>