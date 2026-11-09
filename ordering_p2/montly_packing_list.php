<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function safe_trim($value) {
    if ($value === null) return "";
    return trim((string)$value);
}

function get_param($name, $default = "") {
    if (isset($_GET[$name])) return trim($_GET[$name]);
    if (isset($_POST[$name])) return trim($_POST[$name]);
    return $default;
}

function month_to_yyyymmdd($value) {
    $value = trim($value);
    if ($value == "") return "";
    if (preg_match('/^\d{4}-\d{2}$/', $value)) return str_replace("-", "", $value) . "01";
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return str_replace("-", "", $value);
    if (preg_match('/^\d{8}$/', $value)) return $value;
    $ts = strtotime($value);
    if ($ts === false) return "";
    return date("Ymd", $ts);
}

function yyyymmdd_to_month_input($value) {
    $value = trim($value);
    if ($value == "") return date("Y-m");
    if (preg_match('/^\d{8}$/', $value)) return substr($value, 0, 4) . "-" . substr($value, 4, 2);
    $ts = strtotime($value);
    if ($ts === false) return date("Y-m");
    return date("Y-m", $ts);
}

function month_display($yyyymmdd) {
    if ($yyyymmdd == "") return "";
    $ts = strtotime(substr($yyyymmdd, 0, 4) . "-" . substr($yyyymmdd, 4, 2) . "-01");
    if ($ts === false) return "";
    return date("F Y", $ts);
}

function fmt_date($value) {
    if ($value instanceof DateTime) return $value->format("d-M-Y");
    if ($value === null || $value === "") return "";
    $ts = strtotime($value);
    if ($ts === false) return "";
    return date("d-M-Y", $ts);
}

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
}

function fmt_qty($value) {
    if ($value === null || $value === "") $value = 0;
    return number_format((float)$value, 0, ".", ",");
}

function fmt_kg($value) {
    if ($value === null || $value === "") $value = 0;
    return number_format((float)$value, 3, ".", ",");
}

function fmt_gr($value) {
    if ($value === null || $value === "") $value = 0;
    return number_format((float)$value, 2, ".", ",");
}

$is_filter = get_param("RUN", "") == "1";
$asper_month = get_param("ASPER_MONTH", date("Y-m"));
$cust_code   = get_param("CUST_CODE", "");

if ($is_filter && $cust_code == "") {
    $cust_code = "%";
}

$asper_ymd = month_to_yyyymmdd($asper_month);
$monthText = month_display($asper_ymd);

$rows = array();
$printRows = array();
$pages = array();

$totalPages = 0;
$rowsPerPage = 32;

if ($is_filter) {
    if ($asper_ymd == "") die("Bulan tidak valid.");
    if ($cust_code == "") $cust_code = "%";

    $sql = "
        SET NOCOUNT ON;

        DECLARE @ASPER_DT DATETIME;
        SET @ASPER_DT = CONVERT(DATETIME, ?, 112);

        SELECT TOP 100 PERCENT
            C.CUST_CODE,
            C.CUST_COMP,
            DI.DI_INVNO,
            DI.DI_DATE,
            PV.PART_CODE,
            PV.PART_NAME,
            PV.PART_NO,
            PV.PART_NUM,
            DP.DIPA_QTY,
            DP.DIPA_PQTY,
            PK.PACK_DESC, 
            PK.PACK_CODE, 
            PV.PART_WEIGHT,
            PK.PACK_WEIGHT
        FROM dbo.DI_PART AS DP
        INNER JOIN dbo.DI AS DI ON DI.DI_ID = DP.DI_ID
        INNER JOIN dbo.CUST AS C ON DI.CUST_ID = C.CUST_ID
        INNER JOIN dbo.PART_VIEW AS PV ON DP.PRICE_ID = PV.PRICE_ID
        LEFT JOIN dbo.PACK AS PK ON DP.PACK_ID = PK.PACK_ID
        WHERE
            DATEDIFF(MONTH, DI.DI_DATE, @ASPER_DT) = 0
            AND C.CUST_CODE LIKE ?
        ORDER BY
            C.CUST_COMP,
            DI.DI_DATE,
            DI.DI_INVNO,
            PV.PART_NAME
    ";

    $stmt = sqlsrv_query($conn, $sql, array($asper_ymd, $cust_code));
    if ($stmt === false) die("<pre>Query Monthly Packing List gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $partCode = safe_trim($r["PART_CODE"]);
        $partNo   = safe_trim($r["PART_NO"]);
        $partName = safe_trim($r["PART_NAME"]);
        $partNum  = safe_trim($r["PART_NUM"]);

        $qty     = isset($r["DIPA_QTY"]) ? (float)$r["DIPA_QTY"] : 0;
        $packQty = isset($r["DIPA_PQTY"]) ? (float)$r["DIPA_PQTY"] : 0;
        
        $packCode = isset($r["PACK_CODE"]) ? safe_trim($r["PACK_CODE"]) : "";
        $packDesc = isset($r["PACK_DESC"]) ? safe_trim($r["PACK_DESC"]) : "";

        $partWeightGram = isset($r["PART_WEIGHT"]) ? (float)$r["PART_WEIGHT"] : 0;
        $partWeightKg = ($qty * $partWeightGram) / 1000;

        $packWeightGram = isset($r["PACK_WEIGHT"]) ? (float)$r["PACK_WEIGHT"] : 0;
        $packWeightKgTotal = ($packWeightGram * $packQty) / 1000;
        $totalLineWeightKg = $partWeightKg + $packWeightKgTotal;

        $partNoDisplay = $partNo;
        if ($partNoDisplay == "") $partNoDisplay = $partCode;

        $partNameDisplay = $partName;
        if ($partNum != "" && strpos($partNameDisplay, $partNum) === false) {
            $partNameDisplay = trim($partNameDisplay . " " . $partNum);
        }

        $rows[] = array(
            "CUST_CODE"       => safe_trim($r["CUST_CODE"]),
            "CUST_COMP"       => safe_trim($r["CUST_COMP"]),
            "DI_INVNO"        => safe_trim($r["DI_INVNO"]),
            "DI_DATE"         => $r["DI_DATE"],
            "PART_NO"         => $partNoDisplay,
            "PART_NAME"       => $partNameDisplay,
            "DIPA_QTY"        => $qty,
            "PART_WEIGHT_KG"  => $partWeightKg,
            "DIPA_PQTY"       => $packQty,
            "PACK_CODE"       => $packCode,
            "PACK_DESC"       => $packDesc,
            "PACK_WEIGHT_GR"  => $packWeightGram,
            "TOTAL_WEIGHT_KG" => $totalLineWeightKg
        );
    }

    $lastCust = ""; $lastInv = "";
    $custPartQty = 0; $custPartKg = 0; $custPackQty = 0; $custTotalKg = 0;
    $invPartQty = 0;  $invPartKg = 0;  $invPackQty = 0;  $invTotalKg = 0;
    $grandPartQty = 0; $grandPartKg = 0; $grandPackQty = 0; $grandTotalKg = 0;
    $invNo = "";

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];
        $custKey = $r["CUST_COMP"];
        $invKey  = $r["CUST_COMP"] . "|" . $r["DI_INVNO"];

        if ($custKey != $lastCust) {
            if ($lastInv != "") {
                $printRows[] = array(
                    "ROW_TYPE" => "INVOICE_TOTAL", "DI_INVNO" => $invNo,
                    "QTY" => $invPartQty, "PART_KG" => $invPartKg, "PQTY" => $invPackQty, "TOTAL_KG" => $invTotalKg
                );
            }
            if ($lastCust != "") {
                $printRows[] = array(
                    "ROW_TYPE" => "CUSTOMER_TOTAL",
                    "QTY" => $custPartQty, "PART_KG" => $custPartKg, "PQTY" => $custPackQty, "TOTAL_KG" => $custTotalKg
                );
            }
            $printRows[] = array("ROW_TYPE" => "CUSTOMER", "CUST_COMP" => $r["CUST_COMP"]);

            $lastCust = $custKey; $lastInv = "";
            $custPartQty = 0; $custPartKg = 0; $custPackQty = 0; $custTotalKg = 0;
            $invPartQty = 0;  $invPartKg = 0;  $invPackQty = 0;  $invTotalKg = 0;
            $invNo = "";
        }

        $showInv = 0;
        if ($invKey != $lastInv) {
            if ($lastInv != "") {
                $printRows[] = array(
                    "ROW_TYPE" => "INVOICE_TOTAL", "DI_INVNO" => $invNo,
                    "QTY" => $invPartQty, "PART_KG" => $invPartKg, "PQTY" => $invPackQty, "TOTAL_KG" => $invTotalKg
                );
            }
            $showInv = 1; $lastInv = $invKey; $invNo = $r["DI_INVNO"];
            $invPartQty = 0; $invPartKg = 0; $invPackQty = 0; $invTotalKg = 0;
        }

        $printRows[] = array(
            "ROW_TYPE"        => "DETAIL",
            "SHOW_INV"        => $showInv,
            "DI_INVNO"        => $r["DI_INVNO"],
            "DI_DATE"         => $r["DI_DATE"],
            "PART_NO"         => $r["PART_NO"],
            "PART_NAME"       => $r["PART_NAME"],
            "DIPA_QTY"        => $r["DIPA_QTY"],
            "PART_WEIGHT_KG"  => $r["PART_WEIGHT_KG"],
            "DIPA_PQTY"       => $r["DIPA_PQTY"],
            "PACK_CODE"       => $r["PACK_CODE"],
            "PACK_DESC"       => $r["PACK_DESC"],
            "PACK_WEIGHT_GR"  => $r["PACK_WEIGHT_GR"],
            "TOTAL_WEIGHT_KG" => $r["TOTAL_WEIGHT_KG"]
        );

        $invPartQty += $r["DIPA_QTY"]; $invPartKg += $r["PART_WEIGHT_KG"]; $invPackQty +=$r["DIPA_PQTY"]; $invTotalKg +=$r["TOTAL_WEIGHT_KG"];
        $custPartQty +=$r["DIPA_QTY"]; $custPartKg +=$r["PART_WEIGHT_KG"]; $custPackQty +=$r["DIPA_PQTY"]; $custTotalKg +=$r["TOTAL_WEIGHT_KG"];
        $grandPartQty +=$r["DIPA_QTY"]; $grandPartKg +=$r["PART_WEIGHT_KG"]; $grandPackQty +=$r["DIPA_PQTY"]; $grandTotalKg +=$r["TOTAL_WEIGHT_KG"];
    }

    if ($lastInv != "") {
        $printRows[] = array(
            "ROW_TYPE" => "INVOICE_TOTAL", "DI_INVNO" => $invNo,
            "QTY" => $invPartQty, "PART_KG" => $invPartKg, "PQTY" => $invPackQty, "TOTAL_KG" => $invTotalKg
        );
    }
    if ($lastCust != "") {
        $printRows[] = array(
            "ROW_TYPE" => "CUSTOMER_TOTAL",
            "QTY" => $custPartQty, "PART_KG" => $custPartKg, "PQTY" => $custPackQty, "TOTAL_KG" => $custTotalKg
        );
    }
    if (count($rows) > 0) {$printRows[] = array(
            "ROW_TYPE" => "GRAND_TOTAL",
            "QTY" => $grandPartQty, "PART_KG" => $grandPartKg, "PQTY" => $grandPackQty, "TOTAL_KG" => $grandTotalKg
        );
    }
    
    if (count($printRows) == 0) {$printRows[] = array("ROW_TYPE" => "EMPTY", "MESSAGE" => "Data monthly packing list tidak ditemukan.");
    }

    $pages = array_chunk($printRows,$rowsPerPage);
    $totalPages = count($pages);
    if ($totalPages <= 0)$totalPages = 1;
}

$selfFile = basename($_SERVER["PHP_SELF"]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Packing List</title>
    <style>
        @page { size: A4 landscape; margin: 6mm; }
        body { margin: 0; background: #9a9a9a; font-family: "Courier New", monospace; font-size: 10px; color: #000000; }
        
        .filter-bar { width: 285mm; margin: 8px auto; background: #d4d0c8; border: 1px solid #666666; padding: 6px; box-sizing: border-box; font-family: Tahoma, Arial, sans-serif; font-size: 12px; }
        .filter-bar input { height: 24px; border: 1px solid #777777; font-size: 12px; padding: 2px 4px; box-sizing: border-box; }
        .filter-month { width: 130px; } .filter-cust { width: 160px; }
        .filter-bar button { height: 26px; font-size: 12px; cursor: pointer; margin-left: 4px; }
        
        .autocomplete-wrap { position: relative; display: inline-block; }
        .autocomplete-list { position: absolute; top: 24px; left: 0; width: 430px; max-height: 230px; overflow-y: auto; background: #ffffff; border: 1px solid #444444; z-index: 9999; display: none; box-shadow: 2px 2px 5px rgba(0,0,0,0.25); }
        .autocomplete-item { padding: 5px 7px; border-bottom: 1px solid #dddddd; cursor: pointer; line-height: 16px; font-family: Tahoma, Arial, sans-serif; font-size: 12px; }
        .autocomplete-item:hover, .autocomplete-item.active { background: #2f70c9; color: #ffffff; }
        
        .print-bar { width: 285mm; margin: 8px auto; text-align: right; }
        .print-bar button { padding: 6px 14px; font-size: 11px; cursor: pointer; font-family: Arial, sans-serif; }
        
        .page { width: 285mm; min-height: 198mm; margin: 10px auto; background: #ffffff; border: 2px solid #000000; padding: 6mm; box-sizing: border-box; page-break-after: always; overflow: hidden; }
        .page:last-child { page-break-after: auto; }
        
        .header { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .header td { border: none; vertical-align: top; }
        .company { width: 30%; font-family: Arial, sans-serif; font-size: 11px; line-height: 14px; }
        .company-title { font-size: 15px; font-weight: normal; }
        .title-area { width: 40%; text-align: center; font-family: Arial, sans-serif; }
        .report-title { font-size: 20px; font-weight: normal; margin-top: 6px; }
        .as-per { font-size: 11px; font-weight: bold; margin-top: 4px; }
        .right-info { width: 30%; text-align: right; font-family: Arial, sans-serif; font-size: 11px; line-height: 18px; }

        .invoice-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .invoice-table th, .invoice-table td {
            border: none; padding: 2px 3px; height: 17px; line-height: 12px; box-sizing: border-box;
            vertical-align: middle; white-space: nowrap; overflow: hidden; font-size: 10px;
        }
        .invoice-table thead th { border-top: 1px solid #000000; border-bottom: 1px solid #000000; font-weight: normal; text-align: left; font-size: 11px; }
        .invoice-table thead th.num { text-align: right; }
        
        .customer-row td { font-weight: bold; font-size: 12px; padding-top: 8px; }
        .invoice-total-row td { font-weight: bold; font-style: italic; }
        .customer-total-row td { font-weight: bold; }
        .grand-total-row td { font-weight: bold; border-top: 2px solid #000000; border-bottom: 2px solid #000000; }
        
        .inv-bold { font-weight: bold; }
        .num { text-align: right; }
        .center { text-align: center; }

        /* Penyesuaian Kolom */
        .col-inv { width: 10%; }
        .col-date { width: 8%; }
        .col-no { width: 15%; }
        .col-name { width: 20%; }
        .col-pqty { width: 6%; }
        .col-pkg { width: 6%; }
        .col-bxqty { width: 6%; }
        .col-bxcode { width: 6%; }
        .col-bxdesc { width: 10%; }
        .col-bxgr { width: 6%; }
        .col-totkg { width: 7%; }

        .no-data { font-family: Arial, sans-serif; font-size: 14px; text-align: center; margin-top: 70px; line-height: 24px; }
        @media print { html, body { width: 297mm; min-height: 210mm; background: #ffffff; } .filter-bar, .print-bar { display: none; } .page { width: 285mm; min-height: 198mm; margin: 0 auto; border: none; padding: 4mm; overflow: hidden; } }
    </style>
</head>
<body>

<div class="filter-bar">
    <form method="get" action="<?php echo h($selfFile); ?>" autocomplete="off">
        <input type="hidden" name="RUN" value="1">
        Month: <input type="month" id="ASPER_MONTH" name="ASPER_MONTH" class="filter-month" value="<?php echo h(yyyymmdd_to_month_input($asper_ymd)); ?>">
        Customer:
        <div class="autocomplete-wrap">
            <input type="text" id="CUST_CODE" name="CUST_CODE" class="filter-cust" value="<?php echo h($cust_code); ?>" placeholder="Ketik customer / %">
            <div id="custSuggest" class="autocomplete-list"></div>
        </div>
        <button type="submit">FILTER</button>
        <button type="button" onclick="setAllCustomer()">ALL</button>
        <button type="button" onclick="exportExcel()">EXPORT EXCEL</button>
    </form>
</div>

<div class="print-bar">
    <button type="button" onclick="window.print()">PRINT</button>
    <button type="button" onclick="closeReport()">CLOSE</button>
</div>

<?php if (!$is_filter) { ?>
    <div class="page">
        <table class="header">
            <tr>
                <td class="company"><div class="company-title">P.T. IMC TEKNO INDONESIA</div>PPIC Department</td>
                <td class="title-area"><div class="report-title">MONTHLY PACKING LIST</div><div class="as-per">As per: -</div></td>
                <td class="right-info">Page 0 of 0<br>Print date: <?php echo h(fmt_print_datetime()); ?></td>
            </tr>
        </table>
        <div class="no-data">Data belum ditampilkan.<br><br>Pilih Month dan Customer lalu klik <b>FILTER</b>.<br>Klik <b>ALL</b> untuk semua customer.</div>
    </div>
<?php } ?>

<?php for ($p = 0; $p < count($pages);$p++) { ?>
    <?php $pageRows = $pages[$p]; $pageNo =$p + 1; ?>
    <div class="page">
        <table class="header">
            <tr>
                <td class="company"><div class="company-title">P.T. IMC TEKNO INDONESIA</div>PPIC Department</td>
                <td class="title-area"><div class="report-title">MONTHLY PACKING LIST</div><div class="as-per">As per: <?php echo h($monthText); ?></div></td>
                <td class="right-info">Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?><br>Print date: <?php echo h(fmt_print_datetime()); ?></td>
            </tr>
        </table>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th class="col-inv">INV#</th>
                    <th class="col-date">DATE</th>
                    <th class="col-no">PART NUMBER</th>
                    <th class="col-name">PART NAME</th>
                    <th class="col-pqty num">Qty(pcs)</th>
                    <th class="col-pkg num">Wgt(kg)</th>
                    <th class="col-bxqty num">Qty(box)</th>
                    <th class="col-bxcode">Jenis Box</th>
                    <th class="col-bxdesc">Pack Desc</th>
                    <th class="col-bxgr num">Wgt(gr)</th>
                    <th class="col-totkg num">Total Wgt(kg)</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < count($pageRows);$i++) { ?>
                    <?php $r = $pageRows[$i]; ?>
                    <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
                        <tr class="customer-row"><td colspan="11"><?php echo h($r["CUST_COMP"]); ?></td></tr>
                    <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
                        <tr>
                            <td class="col-inv inv-bold"><?php echo $r["SHOW_INV"] == 1 ? h($r["DI_INVNO"]) : ""; ?></td>
                            <td class="col-date"><?php echo h(fmt_date($r["DI_DATE"])); ?></td>
                            <td class="col-no"><?php echo h($r["PART_NO"]); ?></td>
                            <td class="col-name"><?php echo h($r["PART_NAME"]); ?></td>
                            <td class="col-pqty num"><?php echo h(fmt_qty($r["DIPA_QTY"])); ?></td>
                            <td class="col-pkg num"><?php echo h(fmt_kg($r["PART_WEIGHT_KG"])); ?></td>
                            <td class="col-bxqty num"><?php echo h(fmt_qty($r["DIPA_PQTY"])); ?></td>
                            <td class="col-bxcode"><?php echo h($r["PACK_CODE"]); ?></td>
                            <td class="col-bxdesc"><?php echo h($r["PACK_DESC"]); ?></td>
                            <td class="col-bxgr num"><?php echo h(fmt_gr($r["PACK_WEIGHT_GR"])); ?></td>
                            <td class="col-totkg num"><?php echo h(fmt_kg($r["TOTAL_WEIGHT_KG"])); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "INVOICE_TOTAL") { ?>
                        <tr class="invoice-total-row">
                            <td colspan="4" class="num">TOTAL INVOICE <?php echo h($r["DI_INVNO"]); ?></td>
                            <td class="col-pqty num"><?php echo h(fmt_qty($r["QTY"])); ?></td>
                            <td class="col-pkg num"><?php echo h(fmt_kg($r["PART_KG"])); ?></td>
                            <td class="col-bxqty num"><?php echo h(fmt_qty($r["PQTY"])); ?></td>
                            <td colspan="3"></td>
                            <td class="col-totkg num"><?php echo h(fmt_kg($r["TOTAL_KG"])); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL") { ?>
                        <tr class="customer-total-row">
                            <td colspan="4" class="num">TOTAL CUSTOMER</td>
                            <td class="col-pqty num"><?php echo h(fmt_qty($r["QTY"])); ?></td>
                            <td class="col-pkg num"><?php echo h(fmt_kg($r["PART_KG"])); ?></td>
                            <td class="col-bxqty num"><?php echo h(fmt_qty($r["PQTY"])); ?></td>
                            <td colspan="3"></td>
                            <td class="col-totkg num"><?php echo h(fmt_kg($r["TOTAL_KG"])); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
                        <tr class="grand-total-row">
                            <td colspan="4" class="num">GRAND TOTAL</td>
                            <td class="col-pqty num"><?php echo h(fmt_qty($r["QTY"])); ?></td>
                            <td class="col-pkg num"><?php echo h(fmt_kg($r["PART_KG"])); ?></td>
                            <td class="col-bxqty num"><?php echo h(fmt_qty($r["PQTY"])); ?></td>
                            <td colspan="3"></td>
                            <td class="col-totkg num"><?php echo h(fmt_kg($r["TOTAL_KG"])); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr><td colspan="11" class="center"><?php echo h($r["MESSAGE"]); ?></td></tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<script>
var custRows = []; var custIndex = -1; var timer = null;
function enc(v) { return encodeURIComponent(v == null ? "" : v); }
function htmlEncode(value) { return String(value == null ? "" : value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
function closeReport() { try { if (window.parent && window.parent !== window) { window.location.href = "dashboard_home.php"; return; } } catch (e) {} window.open("", "_self"); window.close(); setTimeout(function () { if (!window.closed) window.location.href = "dashboard_home.php"; }, 200); }
function exportExcel() {
    var asperMonth = document.getElementById("ASPER_MONTH").value; var custCode = document.getElementById("CUST_CODE").value;
    if (asperMonth == "") { alert("Month belum dipilih."); document.getElementById("ASPER_MONTH").focus(); return; }
    if (custCode == "") { alert("Customer belum diisi. Isi kode customer atau % untuk semua."); document.getElementById("CUST_CODE").focus(); return; }
    window.location = "monthly_packing_list_export_excel.php?ASPER_MONTH=" + enc(asperMonth) + "&CUST_CODE=" + enc(custCode);
}
function setAllCustomer() { document.getElementById("CUST_CODE").value = "%"; document.forms[0].submit(); }
function hideSuggest() { var box = document.getElementById("custSuggest"); box.style.display = "none"; box.innerHTML = ""; custRows = []; custIndex = -1; }
function setActiveCust(index) { var box = document.getElementById("custSuggest"); var items = box.getElementsByClassName("autocomplete-item"); if (!items || items.length == 0) { custIndex = -1; return; } if (index < 0) index = items.length - 1; if (index >= items.length) index = 0; for (var i = 0; i < items.length; i++) items[i].className = "autocomplete-item"; items[index].className = "autocomplete-item active"; custIndex = index; }
function chooseCust(index) { if (index < 0 || index >= custRows.length) return; document.getElementById("CUST_CODE").value = custRows[index].CUST_CODE; hideSuggest(); }
function renderSuggest(rows) { var box = document.getElementById("custSuggest"); box.innerHTML = ""; custRows = rows || []; custIndex = -1; if (!rows || rows.length == 0) { box.style.display = "none"; return; } for (var i = 0; i < rows.length; i++) { (function (r, idx) { var div = document.createElement("div"); div.className = "autocomplete-item"; div.innerHTML = "<b>" + htmlEncode(r.CUST_CODE) + "</b> - " + htmlEncode(r.CUST_COMP); div.onmouseover = function () { setActiveCust(idx); }; div.onmousedown = function (e) { if (e && e.preventDefault) e.preventDefault(); chooseCust(idx); }; box.appendChild(div); })(rows[i], i); } box.style.display = "block"; setActiveCust(0); }
function searchCustomer(q) { if (q == "" || q == "%") { hideSuggest(); return; } var xhr = new XMLHttpRequest(); xhr.open("POST", "ajax_customer_autocomplete.php", true); xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded"); xhr.onreadystatechange = function () { if (xhr.readyState == 4 && xhr.status == 200) { var result; try { result = JSON.parse(xhr.responseText); } catch (e) { return; } if (result.rows) renderSuggest(result.rows); else renderSuggest(result); } }; xhr.send("q=" + enc(q)); }
document.getElementById("CUST_CODE").onkeyup = function (e) { e = e || window.event; var key = e.keyCode || e.which; if (key == 40) { setActiveCust(custIndex + 1); return; } if (key == 38) { setActiveCust(custIndex - 1); return; } if (key == 13) { if (custRows.length > 0) { if (custIndex < 0) custIndex = 0; chooseCust(custIndex); return false; } return true; } clearTimeout(timer); var q = this.value; timer = setTimeout(function () { searchCustomer(q); }, 250); };
document.getElementById("CUST_CODE").onblur = function () { setTimeout(function () { hideSuggest(); }, 250); };
</script>
</body>
</html>