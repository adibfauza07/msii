<?php
//delivery_instruction_oto.php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function get_param($name, $default = "") {
    if (isset($_GET[$name])) {
        return trim($_GET[$name]);
    }
    if (isset($_POST[$name])) {
        return trim($_POST[$name]);
    }
    return $default;
}

function to_yyyymmdd($value) {
    $value = trim($value);
    if ($value == "") {
        return "";
    }
    if (preg_match('/^\d{8}$/', $value)) {
        return $value;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return "";
    }
    return date("Ymd", $ts);
}

function fmt_date_id($yyyymmdd) {
    if ($yyyymmdd == "") {
        return "";
    }
    $ts = strtotime($yyyymmdd);
    if ($ts === false) {
        return $yyyymmdd;
    }
    return strtoupper(date("d M Y", $ts));
}

function fmt_print_date() {
    return date("d-M-Y H:i:s");
}

function fmt_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }
    return number_format((float)$value, $decimal, ".", ",");
}

// Ambil parameter Customer
$cust_code = get_param("CUST_CODE", "");
$start_raw = get_param("START_DATE", "");
$end_raw   = get_param("END_DATE", "");

if ($cust_code == "") {
    $cust_code = get_param("CUSTOMER", "");
}

// Jika CUST_CODE kosong, set ke '%' untuk menampilkan SEMUA
if ($cust_code == "") {
    $cust_code = "%";
}

$start_date = to_yyyymmdd($start_raw);
$end_date   = to_yyyymmdd($end_raw);

// Validasi Tanggal
if ($start_date == "" || $end_date == "") {
    die("START_DATE / END_DATE tidak valid.");
}

// Menambahkan DP.DI_ORDERNO dari DI_PART_TEMP
$sql = "
    SELECT 
        DP.DI_ID, DP.DIPA_LINO, DP.PART_CODE, DP.PART_ID, DP.SPR_CODE, DP.DIPA_QTY, DP.PACK_ID, 
        DP.DIPA_PACK, DP.DIPA_PQTY, DP.DIPA_POSTED, DP.PRICE_ID, DP.DIPA_CLOSE, DP.LOCATION, 
        DP.IS_MANUAL, DP.MANUAL_ORDR_ID, DP.MANUAL_ORDP_LINO, DP.BDQTY, DP.BC_NO, DP.DI_ORDERNO AS DP_ORDERNO,
        DT.DI_NO, DT.DI_START_DATE, DT.DI_DATE, DT.DI_INVNO, DT.DI_DSNO, DT.DI_ORDERNO AS DT_ORDERNO, 
        C.CUST_CODE, C.CUST_COMP,
        P.ITEM_NO AS PART_NO, P.ITEM_NAME AS PART_NAME
    FROM dbo.DI_PART_TEMP DP
    INNER JOIN dbo.DI_TEMP DT ON DP.DI_ID = DT.DI_ID
    INNER JOIN dbo.CUST C ON DT.CUST_ID = C.CUST_ID
    LEFT JOIN dbo.ITEMS P ON DP.PART_ID = P.ITEM_ID
    WHERE C.CUST_CODE LIKE ? 
      AND DT.DI_START_DATE = ? 
      AND DT.DI_DATE = ?
    ORDER BY DP.DI_ID ASC, P.ITEM_NO ASC
";

$stmt = sqlsrv_query($conn, $sql, array(
    $cust_code,
    $start_raw, 
    $end_raw
));

if ($stmt === false) {
    die("<pre>Query Gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $r;
}

$displayStart = fmt_date_id($start_date);
$displayEnd   = fmt_date_id($end_date);

$documentPages = array();

for ($i = 0; $i < count($rows); $i++) {
    $r = $rows[$i];

    $di_id = $r["DI_ID"]; 
    $key = $di_id;

    if (!isset($documentPages[$key])) {
        $documentPages[$key] = array(
            "CUST_CODE" => isset($r["CUST_CODE"]) ? trim((string)$r["CUST_CODE"]) : "",
            "CUST_COMP" => isset($r["CUST_COMP"]) ? trim((string)$r["CUST_COMP"]) : "",
            "DI_NO"     => isset($r["DI_NO"]) ? trim((string)$r["DI_NO"]) : "",
            "DI_DSNO"   => isset($r["DI_DSNO"]) ? trim((string)$r["DI_DSNO"]) : "",
            "DI_INVNO"  => isset($r["DI_INVNO"]) ? trim((string)$r["DI_INVNO"]) : "",
            "LOCATION"  => isset($r["LOCATION"]) ? trim((string)$r["LOCATION"]) : "",
            "ROWS"      => array()
        );
    }

    $planQty = isset($r["DIPA_QTY"]) ? $r["DIPA_QTY"] : 0;
    
    // Set REMARK: Panggil isi dari DP_ORDERNO secara langsung
    $remarkVal = "";
    if (isset($r["DP_ORDERNO"]) && trim((string)$r["DP_ORDERNO"]) !== "") {
        $remarkVal = trim((string)$r["DP_ORDERNO"]);
    } elseif (isset($r["BC_NO"]) && trim((string)$r["BC_NO"]) !== "") {
        $remarkVal = "[" . trim((string)$r["BC_NO"]) . "=" . $planQty . "]";
    }

    $documentPages[$key]["ROWS"][] = array(
        "ITEM_CODE"    => isset($r["PART_CODE"]) ? trim((string)$r["PART_CODE"]) : "",
        "ITEM_NO"      => isset($r["PART_NO"]) ? trim((string)$r["PART_NO"]) : "",
        "ITEM_NAME"    => isset($r["PART_NAME"]) ? trim((string)$r["PART_NAME"]) : "",
        "PBQTY"        => isset($r["BDQTY"]) ? $r["BDQTY"] : 0, 
        "PLAN_QTY"     => $planQty, 
        "PO"           => "", 
        "STD_PACK_BOX" => isset($r["DIPA_PQTY"]) ? $r["DIPA_PQTY"] : 0,
        "PACK_CODE"    => isset($r["DIPA_PACK"]) ? trim((string)$r["DIPA_PACK"]) : "",
        "REMARK"       => $remarkVal
    );
}

$pages = array();
$rowsPerPage = 15; // Jumlah baris per halaman

foreach ($documentPages as $page) {
    // Hitung Grand Total untuk keseluruhan dokumen ini
    $grand_po_bal = 0;
    $grand_plan   = 0;
    $grand_dipa   = 0;
    
    foreach ($page["ROWS"] as $r) {
        $grand_po_bal += (float)$r["PBQTY"];
        $grand_plan   += (float)$r["PLAN_QTY"];
        $grand_dipa   += (float)$r["STD_PACK_BOX"];
    }

    // Pecah array menjadi 15 data per halaman
    $chunkedRows = array_chunk($page["ROWS"], $rowsPerPage);
    if (empty($chunkedRows)) {
        $chunkedRows = array(array());
    }
    
    $docTotalPages = count($chunkedRows);
    $itemNo = 1; // Variabel penomoran di-reset ke 1 setiap dokumen (DI_NO) berbeda
    
    // Memproses per halaman untuk satu dokumen (DI_NO)
    foreach ($chunkedRows as $idx => $chunk) {
        $subPage = $page;
        
        $chunkWithNumbers = array();
        foreach ($chunk as $row) {
            if (!empty($row)) {
                $row['ROW_NO'] = $itemNo++;
            }
            $chunkWithNumbers[] = $row;
        }

        $subPage["ROWS"] = $chunkWithNumbers;
        $subPage["IS_LAST_PAGE"] = ($idx === $docTotalPages - 1);
        $subPage["GRAND_PO_BAL"] = $grand_po_bal;
        $subPage["GRAND_PLAN"]   = $grand_plan;
        $subPage["GRAND_DIPA"]   = $grand_dipa;
        
        // Info Halaman Spesifik untuk Dokumen (DI_NO) ini saja
        $subPage["PAGE_NO"] = $idx + 1;
        $subPage["TOTAL_PAGES"] = $docTotalPages;
        
        $pages[] = $subPage;
    }
}

if (count($pages) == 0) {
    $pages[] = array(
        "CUST_CODE"    => "",
        "CUST_COMP"    => "",
        "DI_NO"        => "",
        "DI_DSNO"      => "",
        "DI_INVNO"     => "",
        "LOCATION"     => "",
        "ROWS"         => array(),
        "IS_LAST_PAGE" => true,
        "GRAND_PO_BAL" => 0,
        "GRAND_PLAN"   => 0,
        "GRAND_DIPA"   => 0,
        "PAGE_NO"      => 1,
        "TOTAL_PAGES"  => 1
    );
}

$totalPages = count($pages);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Instruction</title>

    <style>
        @page {
            size: A4 landscape;
            margin: 6mm;
        }

        body {
            margin: 0;
            background: #9a9a9a;
            font-family: "Calibri", Arial, sans-serif;
            font-size: 14px;
            color: #000000;
        }

        .print-bar {
            width: 285mm;
            margin: 8px auto;
            text-align: right;
        }

        .print-bar button {
            padding: 6px 14px;
            font-size: 12px;
            cursor: pointer;
            font-family: "Calibri", Arial, sans-serif;
        }

        .page {
            width: 285mm;
            min-height: 198mm;
            margin: 10px auto;
            background: #ffffff;
            border: 2px solid #000000;
            padding: 5mm;
            box-sizing: border-box;
            page-break-after: always;
            overflow: hidden;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
        }

        .header td {
            border: none;
            vertical-align: top;
        }

        .company {
            width: 32%;
            font-family: "Calibri", Arial, sans-serif;
            font-size: 14px;
            line-height: 16px;
        }

        .company-title {
            font-size: 16px;
            font-weight: bold;
        }
        
        .header-doc-info {
            margin-top: 12px;
            font-family: "Calibri", Arial, sans-serif;
            font-size: 14px;
            line-height: 17px;
            font-weight: bold;
        }

        .title-area {
            width: 40%;
            text-align: center;
            font-family: "Calibri", Arial, sans-serif;
        }

        .report-title {
            font-size: 24px;
            font-weight: bold;
            margin-top: 16px;
            margin-bottom: 5px;
        }

        .period {
            font-size: 14px;
        }

        .right-info {
            width: 28%;
            text-align: right;
            font-family: "Calibri", Arial, sans-serif;
            font-size: 14px;
            line-height: 18px;
        }

        .form-no {
            font-weight: normal;
        }

        .page-no {
            margin-top: 4px;
        }

        .qr-box {
            margin-top: 4px;
            text-align: right;
        }
        .qr-box img {
            width: 50px;
            height: 50px;
            border: 1px solid #ccc;
            padding: 2px;
        }

        .print-date {
            text-align: right;
            font-size: 12px;
            font-family: "Calibri", Arial, sans-serif;
            margin-top: 0;
            margin-bottom: 3px;
        }

        .customer-title {
            width: 100%;
            font-family: "Calibri", Arial, sans-serif;
            font-size: 15px;
            font-weight: bold;
            margin-top: 2px;
            margin-bottom: 4px;
            padding-left: 2px;
            box-sizing: border-box;
        }

        .di-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .di-table th,
        .di-table td {
            border: 1px solid #000000;
            padding: 3px 3px; 
            height: 28px; 
            line-height: 1.1;
            font-size: 13px; 
            box-sizing: border-box;
            vertical-align: middle;
            white-space: nowrap; 
            overflow: hidden;
            text-overflow: clip;
        }

        .di-table th {
            font-weight: bold;
            text-align: center;
            font-size: 12px;
        }

        .empty-line td {
            height: 28px; 
        }

        .num {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .left {
            text-align: left;
            padding-left: 5px !important;
        }

        .signature {
            width: 100%;
            margin-top: 8px;
            font-size: 14px;
        }

        .signature td {
            border: none;
        }

        .sig-right {
            text-align: right;
            padding-right: 55px;
            font-style: italic;
        }

        /* Penyesuaian Lebar Kolom (Total 13 Kolom) */
        .col-no { width: 3.5%; }
        .col-part-code { width: 9%; } 
        .col-part-name { width: 14.5%; white-space: normal !important; word-wrap: break-word; } 
        .col-lot-no { width: 6.5%; } 
        .col-po-bal { width: 6.5%; }
        .col-plan { width: 6.5%; }
        
        .col-aq-pcs { width: 10%; } 
        .col-aq-pack { width: 3%; } 
        .col-aq-init { width: 3.5%; } 
        .col-aq-tot { width: 6%; }
        
        .col-std-qty { width: 10%; } 
        .col-remark { width: 13%; white-space: normal !important; word-wrap: break-word; }
        .col-check { width: 5%; }

        @media print {
            html, body {
                width: 297mm;
                height: 210mm;
                background: #ffffff;
            }
            .print-bar { display: none; }
            .page {
                width: 285mm;
                min-height: 198mm;
                margin: 0 auto;
                border: none;
                padding: 0;
                overflow: hidden;
            }
            .di-table th,
            .di-table td {
                min-height: 28px; 
                line-height: 1.1;
                font-size: 13px; 
                padding: 3px 3px;
            }
            .report-title { font-size: 24px; }
            .company-title { font-size: 16px; }
            .qr-box img {
                border: none;
            }
        }
    </style>
</head>

<body>

<div class="print-bar">
    <button type="button" onclick="window.print()">PRINT</button>
    <button type="button" onclick="window.close()">CLOSE</button>
</div>

<?php 
for ($p = 0; $p < count($pages); $p++) { 
    $pageData   = $pages[$p];
    $custCode   = $pageData["CUST_CODE"];
    $custComp   = $pageData["CUST_COMP"];
    $diNo       = $pageData["DI_NO"];
    $diDsno     = $pageData["DI_DSNO"];
    $diInvno    = $pageData["DI_INVNO"];
    $location   = $pageData["LOCATION"];
    $detailRows = $pageData["ROWS"];
?>

    <div class="page">

        <table class="header">
            <tr>
                <td class="company">
                    <div class="company-title">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Department
                    
                    <div class="header-doc-info">
                        DI NO &nbsp;: <?php echo h($diNo); ?><br>
                        DS NO &nbsp;: <?php echo h($diDsno); ?><br>
                        INV NO : <?php echo h($diInvno); ?>
                        <?php if ($location != "") { ?>
                        <br>LOC &nbsp;&nbsp;&nbsp;: <?php echo h($location); ?>
                        <?php } ?>
                    </div>
                </td>

                <td class="title-area">
                    <div class="report-title">DELIVERY INSTRUCTION</div>
                    <div class="period">
                        <?php echo h($displayStart); ?>
                        &nbsp;&nbsp;&nbsp;&nbsp; - &nbsp;&nbsp;&nbsp;&nbsp;
                        <?php echo h($displayEnd); ?>
                    </div>
                </td>

                <td class="right-info">
                    <div class="form-no">FM.CO.00-06</div>
                    <div class="page-no">Page <?php echo h($pageData["PAGE_NO"]); ?> of <?php echo h($pageData["TOTAL_PAGES"]); ?></div>
                    
                    <div class="qr-box">
                        <?php if ($diNo != "") { ?>
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($diNo); ?>" alt="QR Code">
                        <?php } ?>
                    </div>
                </td>
            </tr>
        </table>

        <div class="print-date">
            Print Date : &nbsp; <?php echo h(fmt_print_date()); ?>
        </div>

        <div class="customer-title">
            <?php echo h($custCode); ?>
            &nbsp;&nbsp;
            <?php echo h($custComp); ?>
        </div>

        <table class="di-table">
            <thead>
                <tr>
                    <th rowspan="2" class="col-no">No</th>
                    <th rowspan="2" class="col-part-code">Part Code</th>
                    <th rowspan="2" class="col-part-name">Part Name</th>
                    <th rowspan="2" class="col-lot-no">Lot No</th>
                    <th rowspan="2" class="col-po-bal">PO.Bal</th>
                    <th rowspan="2" class="col-plan">Del.Plan</th>
                    <!-- Grup Actual Qty -->
                    <th colspan="4" class="center">Actual Qty</th>
                    <th rowspan="2" class="col-std-qty">Std Qty</th>
                    <th rowspan="2" class="col-remark">REMARK</th>
                    <th rowspan="2" class="col-check">loading<br>check</th>
                </tr>
                <tr>
                    <th class="col-aq-pcs">packing</th>
                    <th class="col-aq-pack">pcs</th>
                    <th class="col-aq-init">Initial<br>packing</th>
                    <th class="col-aq-tot">Total</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($detailRows) == 0) { ?>
                    <tr>
                        <td colspan="13" class="center">
                            Data tidak ditemukan.
                        </td>
                    </tr>
                <?php } ?>

                <?php for ($i = 0; $i < count($detailRows); $i++) { ?>
                    <?php 
                        $r = $detailRows[$i]; 
                        
                        $jml_box = 0;
                        if (!empty($r["STD_PACK_BOX"]) && $r["STD_PACK_BOX"] > 0) {
                            $jml_box = ceil($r["PLAN_QTY"] / $r["STD_PACK_BOX"]);
                        }

                        // Batasi Part Code maksimal 12 karakter
                        $partCode12 = substr(trim((string)$r["ITEM_CODE"]), 0, 12);
                        $partName   = trim((string)$r["ITEM_NAME"]);
                        
                        $stdQtyText = "";
                        if (!empty($r["STD_PACK_BOX"]) && $jml_box > 0) {
                            $packCode = !empty($r["PACK_CODE"]) ? "(" . $r["PACK_CODE"] . ")" : "";
                            $stdQtyText = $r["STD_PACK_BOX"] . " x " . $jml_box . " " . $packCode;
                        }
                    ?>

                    <tr>
                        <td class="col-no center">
                            <?php echo isset($r['ROW_NO']) ? $r['ROW_NO'] : ''; ?>
                        </td>

                        <!-- Kolom Part Code (Max 12 Char) -->
                        <td class="col-part-code left">
                            <?php echo h($partCode12); ?>
                        </td>

                        <!-- Kolom Part Name -->
                        <td class="col-part-name left">
                            <?php echo h($partName); ?>
                        </td>
                        
                        <td class="col-lot-no center"></td>

                        <td class="col-po-bal center">
                            <?php echo h(fmt_num($r["PBQTY"], 0)); ?>
                        </td>

                        <td class="col-plan center">
                            <?php echo h(fmt_num($r["PLAN_QTY"], 0)); ?>
                        </td>

                        <td class="col-aq-pcs center"></td>
                        <td class="col-aq-pack center"></td>
                        <td class="col-aq-init center"></td>
                        <td class="col-aq-tot center"></td>

                        <td class="col-std-qty center">
                            <?php echo h(trim($stdQtyText)); ?>
                        </td>

                        <td class="col-remark left">
                            <?php echo h($r["REMARK"]); ?>
                        </td>

                        <td class="col-check"></td>
                    </tr>
                <?php } ?>

                <?php
                    // Menambahkan baris kosong agar tinggi konsisten
                    $usedRows = count($detailRows);
                    
                    // Hitung jumlah baris kosong yang dibutuhkan
                    $fillCount = $rowsPerPage - $usedRows;
                    
                    // Jika halaman terakhir, kurangi 1 agar muat baris 'TOTAL'
                    if ($pageData["IS_LAST_PAGE"]) {
                        $fillCount -= 1;
                    }
                    
                    if ($fillCount < 0) {
                        $fillCount = 0;
                    }
                ?>

                <?php for ($e = 0; $e < $fillCount; $e++) { ?>
                    <tr class="empty-line">
                        <td>&nbsp;</td>
                        <td></td>
                        <td></td>
                        <td></td> 
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php } ?>
                
                <?php 
                // Cek apakah halaman ini adalah halaman paling akhir dari dokumen yang bersangkutan
                if ($pageData["IS_LAST_PAGE"]) { ?>
                <tr>
                    <td colspan="4" class="center"><strong>TOTAL</strong></td>
                    <td class="center"><strong><?php echo h(fmt_num($pageData["GRAND_PO_BAL"], 0)); ?></strong></td>
                    <td class="center"><strong><?php echo h(fmt_num($pageData["GRAND_PLAN"], 0)); ?></strong></td>
                    <td></td> 
                    <td></td> 
                    <td></td> 
                    <td></td> 
                    <td class="center"><strong><?php echo h(fmt_num($pageData["GRAND_DIPA"], 0)); ?></strong></td> 
                    <td></td> 
                    <td></td> 
                </tr>
                <?php } ?>

            </tbody>
        </table>

        <table class="signature">
            <tr>
                <td></td>
                <td class="sig-right">[Checked by]</td>
                <td class="sig-right">[Prepared by]</td>
            </tr>
        </table>

    </div>
<?php } ?>

</body>
</html>