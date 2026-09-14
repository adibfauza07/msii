<?php
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

$cust_code = get_param("CUST_CODE", "");
$start_raw = get_param("START_DATE", "");
$end_raw   = get_param("END_DATE", "");

if ($cust_code == "") {
    $cust_code = get_param("CUSTOMER", "");
}

$start_date = to_yyyymmdd($start_raw);
$end_date   = to_yyyymmdd($end_raw);

if ($cust_code == "") {
    die("CUST_CODE belum diisi.");
}

if ($start_date == "" || $end_date == "") {
    die("START_DATE / END_DATE tidak valid.");
}

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.SP_DELIVERY_INSTRUCTION_PO2 ?, ?, ?
";

$stmt = sqlsrv_query($conn, $sql, array(
    $cust_code,
    $start_date,
    $end_date
));

if ($stmt === false) {
    die("<pre>Query SP_DELIVERY_INSTRUCTION_PO2 gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $r;
}

if (count($rows) > 0) {
    $displayStart = isset($rows[0]["START_DATE_DISPLAY"]) ? trim($rows[0]["START_DATE_DISPLAY"]) : fmt_date_id($start_date);
    $displayEnd   = isset($rows[0]["END_DATE_DISPLAY"]) ? trim($rows[0]["END_DATE_DISPLAY"]) : fmt_date_id($end_date);
} else {
    $displayStart = fmt_date_id($start_date);
    $displayEnd   = fmt_date_id($end_date);
}

/*
    1 customer = 1 halaman.
*/
$customerPages = array();

for ($i = 0; $i < count($rows); $i++) {
    $r = $rows[$i];

    $custCode = isset($r["CUST_CODE"]) ? trim((string)$r["CUST_CODE"]) : "";
    $custComp = isset($r["CUST_COMP"]) ? trim((string)$r["CUST_COMP"]) : "";

    $key = $custCode . "|" . $custComp;

    if (!isset($customerPages[$key])) {
        $customerPages[$key] = array(
            "CUST_CODE" => $custCode,
            "CUST_COMP" => $custComp,
            "ROWS"      => array()
        );
    }

    $customerPages[$key]["ROWS"][] = array(
        "ITEM_CODE"    => isset($r["PART_NUM"]) ? trim((string)$r["PART_NUM"]) : "",
        "ITEM_NO"      => isset($r["PART_NO"]) ? trim((string)$r["PART_NO"]) : "",
        "ITEM_NAME"    => isset($r["PART_NAME"]) ? trim((string)$r["PART_NAME"]) : "",
        "PBQTY"        => isset($r["PBQTY"]) ? $r["PBQTY"] : 0,
        "PLAN_QTY"     => isset($r["PLAN_QTY"]) ? $r["PLAN_QTY"] : 0,
        "PO"           => isset($r["PO"]) ? trim((string)$r["PO"]) : "",
        "STD_PACK_BOX" => isset($r["STD_PACK_BOX"]) ? $r["STD_PACK_BOX"] : 0,
        "STD_BOX"      => isset($r["STD_BOX"]) ? $r["STD_BOX"] : 0,
        "PACK_CODE"    => isset($r["PACK_CODE"]) ? trim((string)$r["PACK_CODE"]) : ""
    );
}

// ----------------------------------------------------------------------
// MEMECAH HALAMAN (Mencegah tabel terpotong jika lebih dari 19 baris)
// ----------------------------------------------------------------------
$pages = array();
$maxRowsPerPage = 19; 

foreach ($customerPages as $page) {
    $chunks = array_chunk($page["ROWS"], $maxRowsPerPage);
    foreach ($chunks as $chunk) {
        $pages[] = array(
            "CUST_CODE" => $page["CUST_CODE"],
            "CUST_COMP" => $page["CUST_COMP"],
            "ROWS"      => $chunk
        );
    }
}

if (count($pages) == 0) {
    $pages[] = array(
        "CUST_CODE" => "",
        "CUST_COMP" => "",
        "ROWS"      => array()
    );
}

$totalPages = count($pages);
$rowsPerPage = 20;
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
            font-family: "Courier New", monospace;
            font-size: 10px;
            color: #000000;
        }

        .print-bar {
            width: 285mm;
            margin: 8px auto;
            text-align: right;
        }

        .print-bar button {
            padding: 6px 14px;
            font-size: 11px;
            cursor: pointer;
            font-family: Arial, sans-serif;
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
            width: 38%;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 13px;
        }

        .company-title {
            font-size: 14px;
            font-weight: normal;
        }

        /* TABEL UNTUK NOMOR GENERATE DI BAWAH PPIC */
        .gen-no-table {
            margin-top: 6px;
            font-size: 11px;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            border-collapse: collapse;
        }
        
        .gen-no-table td {
            border: none;
            padding: 1px 2px 1px 0;
        }

        .title-area {
            width: 34%;
            text-align: center;
            font-family: Arial, sans-serif;
        }

        .report-title {
            font-size: 21px;
            font-weight: normal;
            margin-top: 16px;
            margin-bottom: 5px;
        }

        .period {
            font-size: 12px;
        }

        .right-info {
            width: 28%;
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 16px;
        }

        .form-no {
            font-weight: normal;
        }

        .page-no {
            margin-top: 4px;
        }

        .print-date {
            text-align: right;
            font-size: 11px;
            font-family: Arial, sans-serif;
            margin-top: 0;
            margin-bottom: 3px;
        }

        .customer-title {
            width: 100%;
            font-family: "Courier New", monospace;
            font-size: 12px;
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
            padding: 2px 3px;
            height: 20px;
            line-height: 12px;
            box-sizing: border-box;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: clip;
        }

        .di-table th {
            font-weight: normal;
            text-align: center;
        }

        .empty-line td {
            height: 20px;
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
            font-size: 11px;
        }

        .signature td {
            border: none;
        }

        .sig-right {
            text-align: right;
            padding-right: 55px;
            font-style: italic;
        }

        /* Total kolom = 100% */
        .col-item-code { width: 9%; }
        .col-item-no { width: 15%; }
        .col-item-name { width: 20%; }
        .col-lot-no { width: 7%; }
        .col-po-bal { width: 7%; }
        .col-plan { width: 7%; }
        .col-pcs { width: 6%; }
        .col-pack { width: 6%; }
        .col-initial { width: 6%; }
        .col-total { width: 7%; }
        .col-remark { width: 7%; }
        .col-check { width: 3%; }

        @media print {
            html,
            body {
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
                height: 20px;
                line-height: 12px;
                font-size: 10px;
                padding: 2px 3px;
            }

            .report-title { font-size: 21px; }
            .company-title { font-size: 14px; }
        }
    </style>
</head>

<body>

<div class="print-bar">
    <button type="button" onclick="window.print()">PRINT</button>
    <button type="button" onclick="window.close()">CLOSE</button>
</div>

<?php for ($p = 0; $p < count($pages); $p++) { ?>
    <?php
        $pageNo     = $p + 1;
        $pageData   = $pages[$p];
        $custCode   = $pageData["CUST_CODE"];
        $custComp   = $pageData["CUST_COMP"];
        $detailRows = $pageData["ROWS"];

        // -------------------------------------------------------------
        // TARIK NOMOR GENERATE DARI TABEL DI_TEMP
        // -------------------------------------------------------------
        $di_no_val    = "";
        $di_invno_val = "";
        $di_dsno_val  = "";

        $sql_temp = "
            SELECT TOP 1 DI_NO, DI_INVNO, DI_DSNO 
            FROM dbo.DI_TEMP 
            WHERE CUST_CODE = ? AND DI_DATE = ? 
            ORDER BY DI_ID DESC
        ";
        
        $stmt_temp = sqlsrv_query($conn, $sql_temp, array($custCode, $end_date));
        if ($stmt_temp !== false && $row_temp = sqlsrv_fetch_array($stmt_temp, SQLSRV_FETCH_ASSOC)) {
            $di_no_val    = trim((string)$row_temp["DI_NO"]);
            $di_invno_val = trim((string)$row_temp["DI_INVNO"]);
            $di_dsno_val  = trim((string)$row_temp["DI_DSNO"]);
        }
        // -------------------------------------------------------------
    ?>

    <div class="page">

        <table class="header">
            <tr>
                <td class="company">
                    <div class="company-title">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Department
                    
                    <!-- KOTAK MERAH (NOMOR GENERATE) -->
                    <table class="gen-no-table">
                        <tr>
                            <td style="width: 45px;">DI NO</td>
                            <td colspan="2">: <?php echo h($di_no_val); ?></td>
                        </tr>
                        <tr>
                            <td>DS NO</td>
                            <td style="width: 140px;">: <?php echo h($di_dsno_val); ?></td>
                            <td>INV NO : <?php echo h($di_invno_val); ?></td>
                        </tr>
                    </table>
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
                    <div class="page-no">Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?></div>
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
                    <th rowspan="3" class="col-item-code">Item Code</th>
                    <th rowspan="3" class="col-item-no">Item No</th>
                    <th rowspan="3" class="col-item-name">Item Name</th>
                    <th rowspan="3" class="col-lot-no">Lot No</th>
                    <th rowspan="3" class="col-po-bal">PO.Bal</th>
                    <th rowspan="3" class="col-plan">Del.Plan</th>
                    <th colspan="4">Actual Qty</th>
                    <th rowspan="3" class="col-remark">REMARK</th>
                    <th rowspan="3" class="col-check">loading<br>check</th>
                </tr>
                <tr>
                    <th rowspan="2" class="col-pcs">actual</th>
                    <th rowspan="2" class="col-pack">std_box</th>
                    <th class="col-initial">Initial</th>
                    <th rowspan="2" class="col-total">Total</th>
                </tr>
                <tr>
                    <th class="col-initial">packing</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($detailRows) == 0) { ?>
                    <tr>
                        <td colspan="12" class="center">
                            Data tidak ditemukan.
                        </td>
                    </tr>
                <?php } ?>

                <?php 
                    $total_po_bal = 0;
                    $total_plan = 0;
                    $total_box = 0;
                ?>

                <?php for ($i = 0; $i < count($detailRows); $i++) { ?>
                    <?php 
                        $r = $detailRows[$i]; 
                        
                        $jml_box = 0;
                        if (!empty($r["STD_PACK_BOX"]) && $r["STD_PACK_BOX"] > 0) {
                            $jml_box = ceil($r["PLAN_QTY"] / $r["STD_PACK_BOX"]);
                        }

                        $total_po_bal += $r["PBQTY"];
                        $total_plan   += $r["PLAN_QTY"];
                        $total_box    += $jml_box;
                    ?>

                    <tr>
                        <td class="col-item-code">
                            <?php echo h($r["ITEM_CODE"]); ?>
                        </td>

                        <td class="col-item-no">
                            <?php echo h($r["ITEM_NO"]); ?>
                        </td>

                        <td class="col-item-name">
                            <?php echo h($r["ITEM_NAME"]); ?>
                        </td>

                        <td class="col-lot-no">
                            &nbsp;
                        </td>

                        <td class="col-po-bal center">
                            <?php echo h(fmt_num($r["PBQTY"], 0)); ?>
                        </td>

                        <td class="col-plan center">
                            <?php echo h(fmt_num($r["PLAN_QTY"], 0)); ?>
                        </td>

                        <td class="col-pcs"></td>
                        
                        <!-- ISI STD_BOX DIAMBIL DARI DATABASE (STD_PACK_BOX) -->
                        <td class="col-pack center">
                            <?php echo h(fmt_num($r["STD_PACK_BOX"], 0)); ?>
                        </td>
                        
                        <!-- HASIL PERHITUNGAN PINDAH KE KOLOM INITIAL PACKING -->
                        <td class="col-initial center">
                            <?php echo h($r["PACK_CODE"]); ?>
                        </td>
                        
                        <!-- PACK CODE DITAMPILKAN DI KOLOM TOTAL DENGAN RATA KIRI -->
                        <td class="col-total center">
                            <?php echo h(fmt_num($jml_box, 0)); ?>
                        </td>

                        <td class="col-remark">&nbsp;</td>

                        <td class="col-check"></td>
                    </tr>
                <?php } ?>

                <?php
                    $usedRows = count($detailRows);

                    if (count($detailRows) == 0) {
                        $usedRows = 1;
                    }

                    // Kurangi 1 untuk menyediakan ruang bagi baris TOTAL
                    $fillCount = $rowsPerPage - $usedRows - 1;

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
                    </tr>
                <?php } ?>
                
                <!-- BARIS TOTAL -->
                <tr>
                    <td colspan="4" class="center"><strong>TOTAL</strong></td>
                    <td class="center"><strong><?php echo h(fmt_num($total_po_bal, 0)); ?></strong></td>
                    <td class="center"><strong><?php echo h(fmt_num($total_plan, 0)); ?></strong></td>
                    <td></td> <!-- actual kosong -->
                    <td></td> <!-- std_box tidak ditotal -->
                    <td></td> <!-- Initial packing (Pack Code) tidak ditotal -->
                    <td class="center"><strong><?php echo h(fmt_num($total_box, 0)); ?></strong></td> <!-- total perhitungan box -->
                    <td></td> <!-- REMARK -->
                    <td></td> <!-- loading check -->
                </tr>

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