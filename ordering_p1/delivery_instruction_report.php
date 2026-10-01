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
    if ($value == "") return "";
    if (preg_match('/^\d{8}$/', $value)) return $value;
    $ts = strtotime($value);
    if ($ts === false) return "";
    return date("Ymd", $ts);
}

function fmt_date_id($yyyymmdd) {
    if ($yyyymmdd == "") return "";
    $ts = strtotime($yyyymmdd);
    if ($ts === false) return $yyyymmdd;
    return strtoupper(date("d M Y", $ts));
}

function fmt_print_date() {
    return date("d-M-Y  H:i.s");
}

function fmt_num($value, $decimal = 0) {
    if ($value === null || $value === "") $value = 0;
    return number_format((float)$value, $decimal, ".", ",");
}

// Fungsi pembaca kolom yang 100% Case-Insensitive
function get_val($row, $keys, $default = "") {
    $row_ci = array_change_key_case($row, CASE_UPPER);
    foreach ($keys as $k) {
        $k_up = strtoupper($k);
        if (isset($row_ci[$k_up])) {
            return trim((string)$row_ci[$k_up]);
        }
    }
    return $default;
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

// MENGGUNAKAN PO1 SESUAI DENGAN CRYSTAL REPORT ASLI
$sql = "
    SET NOCOUNT ON;
    EXEC dbo.SP_DELIVERY_INSTRUCTION_PO1 ?, ?, ?
";

$stmt = sqlsrv_query($conn, $sql, array(
    $cust_code,
    $start_date,
    $end_date
));

if ($stmt === false) {
    die("<pre>Query SP_DELIVERY_INSTRUCTION_PO1 gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $r;
}

if (count($rows) > 0) {
    $displayStart = get_val($rows[0], ["START_DATE_DISPLAY"]) !== "" ? get_val($rows[0], ["START_DATE_DISPLAY"]) : fmt_date_id($start_date);
    $displayEnd   = get_val($rows[0], ["END_DATE_DISPLAY"]) !== "" ? get_val($rows[0], ["END_DATE_DISPLAY"]) : fmt_date_id($end_date);
} else {
    $displayStart = fmt_date_id($start_date);
    $displayEnd   = fmt_date_id($end_date);
}

// Group data by customer and apply pagination (20 rows per page max)
$tempGroup = array();
for ($i = 0; $i < count($rows); $i++) {
    $r = $rows[$i];

    $custCode = get_val($r, ["CUST_CODE"]);
    $custComp = get_val($r, ["CUST_COMP"]);

    $key = $custCode . "|" . $custComp;

    if (!isset($tempGroup[$key])) {
        $tempGroup[$key] = array(
            "CUST_CODE" => $custCode,
            "CUST_COMP" => $custComp,
            "ROWS"      => array()
        );
    }

    $stdPack = get_val($r, ["STD_PACK_BOX"]) !== "" ? get_val($r, ["STD_PACK_BOX"]) : (get_val($r, ["STD_BOX"]) !== "" ? get_val($r, ["STD_BOX"]) : 0);

    $tempGroup[$key]["ROWS"][] = array(
        "ITEM_CODE"    => get_val($r, ["PART_NUM"]),
        "ITEM_NO"      => get_val($r, ["PART_NO"]),
        "ITEM_NAME"    => get_val($r, ["PART_NAME"]),
        "PBQTY"        => get_val($r, ["PBQTY"]) !== "" ? get_val($r, ["PBQTY"]) : 0,
        "PLAN_QTY"     => get_val($r, ["PLAN_QTY"]) !== "" ? get_val($r, ["PLAN_QTY"]) : 0,
        "STD_PACK_BOX" => $stdPack,
        
        // Membaca kolom LOCATION dengan prioritas penuh
        "LOCATION"     => get_val($r, ["LOCATION", "LOC"]),
        
        "REMARK"       => get_val($r, ["REMARK", "POLYBAG", "PACK_CODE"])
    );
}

$rowsPerPage = 20;
$pages = array();

foreach ($tempGroup as $group) {
    $chunks = array_chunk($group["ROWS"], $rowsPerPage);
    $rowIndex = 1;
    foreach ($chunks as $chunk) {
        $pages[] = array(
            "CUST_CODE"  => $group["CUST_CODE"],
            "CUST_COMP"  => $group["CUST_COMP"],
            "START_ROW"  => $rowIndex,
            "ROWS"       => $chunk
        );
        $rowIndex += count($chunk); 
    }
}

if (count($pages) == 0) {
    $pages[] = array("CUST_CODE" => "", "CUST_COMP" => "", "START_ROW" => 1, "ROWS" => array());
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
            font-family: Arial, sans-serif;
            font-size: 11px;
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
            padding: 5mm;
            box-sizing: border-box;
            page-break-after: always;
            overflow: hidden;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .header-layout {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }
        .header-layout td { vertical-align: top; }
        
        .title-left { width: 33%; }
        .title-center { width: 34%; text-align: center; }
        .title-right { width: 33%; text-align: right; }

        .company-name { font-size: 14px; font-weight: bold; margin-bottom: 2px; }
        .dept-name { font-size: 11px; }
        
        .report-title { font-size: 20px; letter-spacing: 1px; margin-bottom: 4px; }
        .report-period { font-size: 12px; }

        .form-no { font-size: 11px; }
        .page-no { font-size: 11px; margin-top: 4px; }
        .print-date { font-size: 11px; margin-top: 15px; margin-bottom: 2px;}

        .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border-bottom: 1px solid #000;
        }

        .report-table th, .report-table td {
            font-size: 10px;
            padding: 5px 2px; 
            vertical-align: middle;
            word-wrap: break-word;
            overflow: hidden;
        }

        .solid-top th { border-top: 1px solid #000; }
        .dash-bottom th, .dash-bottom td { border-bottom: 1px dashed #000; }
        .dash-left { border-left: 1px dashed #000; }
        .dash-right { border-right: 1px dashed #000; }

        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; padding-right: 5px !important; }
        .font-bold { font-weight: bold; }

        .w1 { width: 9%; }   
        .w2 { width: 17%; }  
        .w3 { width: 10%; }  
        .w4 { width: 7%; }   
        .w5 { width: 6%; }   
        .w6 { width: 5%; }   
        .w7 { width: 4%; }   
        .w8 { width: 5%; }   
        .w9 { width: 5%; }   
        .w10 { width: 4%; }  
        .w11 { width: 5%; }  
        .w12 { width: 13%; } 
        .w13 { width: 6%; }  
        .w14 { width: 4%; }  
        
        .data-row { height: 28px; }

        @media print {
            body { background: #ffffff; }
            .print-bar { display: none; }
            .page { border: none; margin: 0; padding: 0; }
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
        $startRow   = $pageData["START_ROW"];
    ?>

    <div class="page">
        <table class="header-layout">
            <tr>
                <td class="title-left">
                    <div class="company-name">P.T. IMC TEKNO INDONESIA</div>
                    <div class="dept-name">PPIC Departement</div>
                </td>
                <td class="title-center">
                    <div class="report-title">DELIVERY INSTRUCTION</div>
                    <div class="report-period">
                        <?php echo h($displayStart); ?> &nbsp;&nbsp;-&nbsp;&nbsp; <?php echo h($displayEnd); ?>
                    </div>
                </td>
                <td class="title-right">
                    <div class="form-no">FM.CO.00-06</div>
                    <div class="page-no">Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?></div>
                    <div class="print-date">
                        Print Date : &nbsp; <?php echo h(fmt_print_date()); ?>
                    </div>
                </td>
            </tr>
        </table>

        <table class="report-table">
            <colgroup>
                <col class="w1">
                <col class="w2">
                <col class="w3">
                <col class="w4">
                <col class="w5">
                <col class="w6">
                <col class="w7">
                <col class="w8">
                <col class="w9">
                <col class="w10">
                <col class="w11">
                <col class="w12">
                <col class="w13">
                <col class="w14">
            </colgroup>
            <thead>
                <tr class="solid-top">
                    <th colspan="6"></th>
                    <th colspan="3" class="dash-left text-center" style="font-weight: normal; padding-bottom: 2px;">Actual Qty</th>
                    <th class="dash-left"></th>
                    <th class="dash-left text-center" style="font-weight: normal; padding-bottom: 2px;">loading</th>
                    <th class="dash-left"></th>
                    <th class="dash-left text-center" style="font-weight: normal; padding-bottom: 2px;">Initial</th>
                    <th class="dash-left dash-right text-center" style="font-weight: normal; padding-bottom: 2px;">sample</th>
                </tr>
                <tr class="dash-bottom">
                    <th colspan="3" class="text-left" style="font-weight: normal; letter-spacing: 15px; padding-left: 5px;">P A R T</th>
                    
                    <th style="font-weight: normal; text-align: right; padding-right: 5px;">
                        <span style="float: left; padding-left: 5px;">Lot.No</span> STD
                    </th>
                    <th class="text-right" style="font-weight: normal;">PO.Bal</th>
                    <th class="text-right" style="font-weight: normal;">Del.Plan</th>
                    
                    <th class="dash-left text-center" style="font-weight: normal;">pcs</th>
                    <th class="dash-left text-center" style="font-weight: normal;">packing</th>
                    <th class="dash-left text-center" style="font-weight: normal;">Total</th>
                    <th class="dash-left text-center" style="font-weight: normal;">LOC</th>
                    <th class="dash-left text-center" style="font-weight: normal; padding-top: 2px;">check</th>
                    <th class="dash-left text-left" style="font-weight: normal; letter-spacing: 3px; padding-left: 8px;">R E M A R K</th>
                    <th class="dash-left text-center" style="font-weight: normal; padding-top: 2px;">packing</th>
                    <th class="dash-left dash-right text-center" style="font-weight: normal; padding-top: 2px;">QC</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="14" class="text-left font-bold" style="padding-top: 8px; padding-bottom: 8px; font-size: 11px;">
                        <?php echo h($custCode); ?> &nbsp; <?php echo h($custComp); ?>
                    </td>
                </tr>

                <?php for ($i = 0; $i < count($detailRows); $i++) {
                    $r = $detailRows[$i]; 
                    $rowNum = $startRow + $i;
                ?>
                    <tr class="dash-bottom data-row">
                        <td class="text-left" style="padding-left: 2px;"><?php echo h($r["ITEM_NO"]); ?></td>
                        <td class="text-left"><?php echo h($r["ITEM_NAME"]); ?></td>
                        <td class="text-left"><?php echo h($r["ITEM_CODE"]); ?></td>
                        <td class="text-right"><?php echo h(fmt_num($r["STD_PACK_BOX"], 0)); ?></td>
                        <td class="text-right"><?php echo h(fmt_num($r["PBQTY"], 0)); ?></td>
                        <td class="text-right"><?php echo h(fmt_num($r["PLAN_QTY"], 0)); ?></td>
                        <td class="dash-left"></td>
                        <td class="dash-left"></td>
                        <td class="dash-left"></td>
                        <td class="dash-left text-center"><?php echo h($r["LOCATION"]); ?></td>
                        <td class="dash-left"></td>
                        <td class="dash-left text-left"><?php echo h($r["REMARK"]); ?></td>
                        <td class="dash-left text-center font-bold" style="font-size: 12px;"><?php echo h($rowNum); ?></td>
                        <td class="dash-left dash-right"></td>
                    </tr>
                <?php } ?>

                <?php
                    $usedRows = count($detailRows);
                    $fillCount = $rowsPerPage - $usedRows;
                    if ($fillCount < 0) {
                        $fillCount = 0;
                    }
                ?>
                <?php for ($e = 0; $e < $fillCount; $e++) { ?>
                    <tr class="dash-bottom data-row">
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="dash-left"></td>
                        <td class="dash-left"></td>
                        <td class="dash-left"></td>
                        <td class="dash-left"></td>
                        <td class="dash-left"></td>
                        <td class="dash-left"></td>
                        <td class="dash-left"></td>
                        <td class="dash-left dash-right"></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

    </div>
<?php } ?>

</body>
</html>