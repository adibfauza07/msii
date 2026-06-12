<?php
if (session_id() == "") {
    session_start();
}

require_once dirname(__DIR__) . "/config/database_ppic.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function get_value($name, $default) {
    if (isset($_GET[$name])) {
        return trim((string)$_GET[$name]);
    }
    return $default;
}

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

function n0($value) {
    if ($value === null || $value === "") {
        return "-";
    }

    $n = floatval($value);
    if (abs($n) < 0.000001) {
        return "-";
    }

    return number_format($n, 0, ".", ",");
}

function n2($value) {
    if ($value === null || $value === "") {
        return "-";
    }

    $n = floatval($value);
    if (abs($n) < 0.000001) {
        return "-";
    }

    return number_format($n, 2, ".", ",");
}

function n4($value) {
    if ($value === null || $value === "") {
        return "-";
    }

    $n = floatval($value);
    if (abs($n) < 0.000001) {
        return "-";
    }

    return number_format($n, 4, ".", ",");
}

function excel_num($value, $dec) {
    if ($value === null || $value === "") {
        return "";
    }

    $n = floatval($value);
    if (abs($n) < 0.000001) {
        return "";
    }

    if ($dec == 0) {
        return number_format($n, 0, ".", "");
    }

    return number_format($n, $dec, ".", "");
}

function month_name_short($m) {
    $names = array(
        1 => "Jan", 2 => "Feb", 3 => "Mar", 4 => "Apr",
        5 => "May", 6 => "Jun", 7 => "Jul", 8 => "Aug",
        9 => "Sep", 10 => "Oct", 11 => "Nov", 12 => "Dec"
    );

    return isset($names[$m]) ? $names[$m] : "";
}

$yearInput = intval(get_value("year", date("Y")));
if ($yearInput < 2000 || $yearInput > 2100) {
    $yearInput = intval(date("Y"));
}

$custFilter = get_value("cust", "");
$partFilter = get_value("part", "");
$matFilter  = get_value("mat", "");
$export     = strtolower(get_value("export", ""));
$printDate  = date("d-M-Y H:i:s");

/* ======================================================
   AUTO COMPLETE CUSTOMER
====================================================== */
$custList = array();
$sqlCust = "
    SELECT TOP 500
        LTRIM(RTRIM(CUST_CODE)) AS CUST_CODE,
        LTRIM(RTRIM(CUST_COMP)) AS CUST_COMP
    FROM dbo.CUST
    WHERE ISNULL(CUST_CODE, '') <> ''
    ORDER BY CUST_CODE
";
$stmtCust = sqlsrv_query($conn, $sqlCust);
if ($stmtCust !== false) {
    while ($cr = sqlsrv_fetch_array($stmtCust, SQLSRV_FETCH_ASSOC)) {
        $custList[] = array(
            "CUST_CODE" => trim((string)$cr["CUST_CODE"]),
            "CUST_COMP" => trim((string)$cr["CUST_COMP"])
        );
    }
}

/* ======================================================
   LOAD DATA DARI RPT_ACTUAL_MATERIAL_COST
   SP tetap 1 parameter: @YEAR
   Filter customer / part / material dilakukan di PHP.
====================================================== */
$sql = "EXEC dbo.RPT_ACTUAL_MATERIAL_COST ?";
$stmt = sqlsrv_query(
    $conn,
    $sql,
    array($yearInput),
    array("QueryTimeout" => 0)
);

if ($stmt === false) {
    die("<pre>Query RPT_ACTUAL_MATERIAL_COST error:\n" . sql_error_text() . "</pre>");
}

$groups = array();
$totalRows = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $custCode = isset($r["CUST_CODE"]) ? trim((string)$r["CUST_CODE"]) : "";
    $custComp = isset($r["CUST_COMP"]) ? trim((string)$r["CUST_COMP"]) : "";
    $partNum  = isset($r["PART_NUM"]) ? trim((string)$r["PART_NUM"]) : "";
    $partNo   = isset($r["PART_NO"]) ? trim((string)$r["PART_NO"]) : "";
    $partName = isset($r["PART_NAME"]) ? trim((string)$r["PART_NAME"]) : "";
    $matCode  = isset($r["MAT_CODE"]) ? trim((string)$r["MAT_CODE"]) : "";
    $matName  = isset($r["MAT_NAME"]) ? trim((string)$r["MAT_NAME"]) : "";
    $unit     = isset($r["UNIT"]) ? trim((string)$r["UNIT"]) : "";

    if ($custFilter != "") {
        $haystack = $custCode . " " . $custComp;
        if (stripos($haystack, $custFilter) === false) {
            continue;
        }
    }

    if ($partFilter != "") {
        $haystack = $partNum . " " . $partNo . " " . $partName;
        if (stripos($haystack, $partFilter) === false) {
            continue;
        }
    }

    if ($matFilter != "") {
        $haystack = $matCode . " " . $matName;
        if (stripos($haystack, $matFilter) === false) {
            continue;
        }
    }

    $partPrice = isset($r["PART_PRICE"]) ? floatval($r["PART_PRICE"]) : 0;
    $matPrice  = isset($r["MAT_PRICE"]) ? floatval($r["MAT_PRICE"]) : 0;
    $bomQty    = isset($r["BOM_QTY"]) ? floatval($r["BOM_QTY"]) : 0;

    $qty = array();
    $sales = array();
    $matUse = array();
    $matCost = array();

    $totalQty = 0;
    $totalSales = 0;
    $totalMatUse = 0;
    $totalMatCost = 0;

    for ($i = 1; $i <= 12; $i++) {
        $qField = "QTY" . $i;
        $qty[$i] = isset($r[$qField]) ? floatval($r[$qField]) : 0;
        $sales[$i] = $qty[$i] * $partPrice;

        /*
            Sama dengan report sales/material forecast:
            BOM_QTY / weight dalam gram, jadi material use dibagi 1000.
            Contoh: Qty 50 x 15.14 gr / 1000 = 0.76
        */
        $matUse[$i] = ($qty[$i] * $bomQty) / 1000.0;
        $matCost[$i] = $matUse[$i] * $matPrice;

        $totalQty += $qty[$i];
        $totalSales += $sales[$i];
        $totalMatUse += $matUse[$i];
        $totalMatCost += $matCost[$i];
    }

    $groupKey = $custCode . "|" . $custComp;
    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = array(
            "CUST_CODE" => $custCode,
            "CUST_COMP" => $custComp,
            "ROWS" => array(),
            "TOTAL_QTY" => 0,
            "TOTAL_SALES" => 0,
            "TOTAL_MAT_USE" => 0,
            "TOTAL_MAT_COST" => 0,
            "MONTH_QTY" => array(),
            "MONTH_SALES" => array(),
            "MONTH_MAT_USE" => array(),
            "MONTH_MAT_COST" => array()
        );

        for ($i = 1; $i <= 12; $i++) {
            $groups[$groupKey]["MONTH_QTY"][$i] = 0;
            $groups[$groupKey]["MONTH_SALES"][$i] = 0;
            $groups[$groupKey]["MONTH_MAT_USE"][$i] = 0;
            $groups[$groupKey]["MONTH_MAT_COST"][$i] = 0;
        }
    }

    $row = array(
        "CUST_CODE" => $custCode,
        "CUST_COMP" => $custComp,
        "PART_NUM" => $partNum,
        "PART_NO" => $partNo,
        "PART_NAME" => $partName,
        "PART_PRICE" => $partPrice,
        "UNIT" => $unit,
        "MAT_CODE" => $matCode,
        "MAT_NAME" => $matName,
        "BOM_QTY" => $bomQty,
        "MAT_PRICE" => $matPrice,
        "QTY" => $qty,
        "SALES" => $sales,
        "MAT_USE" => $matUse,
        "MAT_COST" => $matCost,
        "TOTAL_QTY" => $totalQty,
        "TOTAL_SALES" => $totalSales,
        "TOTAL_MAT_USE" => $totalMatUse,
        "TOTAL_MAT_COST" => $totalMatCost
    );

    $groups[$groupKey]["ROWS"][] = $row;
    $groups[$groupKey]["TOTAL_QTY"] += $totalQty;
    $groups[$groupKey]["TOTAL_SALES"] += $totalSales;
    $groups[$groupKey]["TOTAL_MAT_USE"] += $totalMatUse;
    $groups[$groupKey]["TOTAL_MAT_COST"] += $totalMatCost;

    for ($i = 1; $i <= 12; $i++) {
        $groups[$groupKey]["MONTH_QTY"][$i] += $qty[$i];
        $groups[$groupKey]["MONTH_SALES"][$i] += $sales[$i];
        $groups[$groupKey]["MONTH_MAT_USE"][$i] += $matUse[$i];
        $groups[$groupKey]["MONTH_MAT_COST"][$i] += $matCost[$i];
    }

    $totalRows++;
}

ksort($groups);

/* ======================================================
   EXPORT EXCEL
====================================================== */
if ($export == "excel") {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $fileName = "ACTUAL_MATERIAL_COST_USD_" . $yearInput . "_" . date("Ymd_His") . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
    header("Cache-Control: max-age=0");
    header("Pragma: public");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; font-family: Calibri, Arial, sans-serif; font-size: 9px; }
        th { background: #d9eaf7; font-weight: bold; text-align: center; border: 1px solid #000000; mso-number-format:"\@"; }
        td { border: 1px solid #000000; padding: 2px; mso-number-format:"\@"; }
        .num { mso-number-format:"0"; text-align: right; }
        .num2 { mso-number-format:"0\.00"; text-align: right; }
        .num4 { mso-number-format:"0\.0000"; text-align: right; }
        .title { font-size: 15px; font-weight: bold; border: none; }
        .info { border: none; }
        .cust { background: #eeeeee; font-weight: bold; }
        .total { background: #f8f8f8; font-weight: bold; }
    </style>
</head>
<body>
<table>
    <tr>
        <td colspan="63" class="title">PT. IMC TEKNO INDONESIA</td>
    </tr>
    <tr>
        <td colspan="63" class="title" style="text-align:center;">ACTUAL SALES AND MATERIAL COST (USD)</td>
    </tr>
    <tr>
        <td colspan="63" class="info">YEAR : <?php echo h($yearInput); ?> | Export Date : <?php echo h($printDate); ?></td>
    </tr>
    <tr><td colspan="63" class="info"></td></tr>

    <tr>
        <th colspan="5">PART</th>
        <th colspan="4">MATERIAL</th>
        <th colspan="4">TOTAL</th>
        <?php for ($i = 1; $i <= 12; $i++) { ?>
            <th colspan="4"><?php echo h(month_name_short($i) . "-" . substr((string)$yearInput, 2, 2)); ?></th>
        <?php } ?>
    </tr>
    <tr>
        <th>Code</th>
        <th>No</th>
        <th>Name</th>
        <th>Price</th>
        <th>Cust</th>
        <th>Code</th>
        <th>Name</th>
        <th>Weight</th>
        <th>Price</th>
        <th>Qty</th>
        <th>Sales USD</th>
        <th>M.Use</th>
        <th>M.Cost USD</th>
        <?php for ($i = 1; $i <= 12; $i++) { ?>
            <th>Qty</th>
            <th>Sales</th>
            <th>M.Use</th>
            <th>M.Cost</th>
        <?php } ?>
    </tr>

    <?php foreach ($groups as $g) { ?>
        <tr class="cust">
            <td colspan="63"><?php echo h($g["CUST_CODE"] . " - " . $g["CUST_COMP"]); ?></td>
        </tr>

        <?php foreach ($g["ROWS"] as $r) { ?>
            <tr>
                <td><?php echo h($r["PART_NUM"]); ?></td>
                <td><?php echo h($r["PART_NO"]); ?></td>
                <td><?php echo h($r["PART_NAME"]); ?></td>
                <td class="num4"><?php echo h(excel_num($r["PART_PRICE"], 4)); ?></td>
                <td><?php echo h($r["CUST_COMP"]); ?></td>
                <td><?php echo h($r["MAT_CODE"]); ?></td>
                <td><?php echo h($r["MAT_NAME"]); ?></td>
                <td class="num4"><?php echo h(excel_num($r["BOM_QTY"], 4)); ?></td>
                <td class="num4"><?php echo h(excel_num($r["MAT_PRICE"], 4)); ?></td>
                <td class="num"><?php echo h(excel_num($r["TOTAL_QTY"], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["TOTAL_SALES"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["TOTAL_MAT_USE"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["TOTAL_MAT_COST"], 2)); ?></td>
                <?php for ($i = 1; $i <= 12; $i++) { ?>
                    <td class="num"><?php echo h(excel_num($r["QTY"][$i], 0)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["SALES"][$i], 2)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["MAT_USE"][$i], 2)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["MAT_COST"][$i], 2)); ?></td>
                <?php } ?>
            </tr>
        <?php } ?>

        <tr class="total">
            <td colspan="9">TOTAL <?php echo h($g["CUST_COMP"]); ?></td>
            <td class="num"><?php echo h(excel_num($g["TOTAL_QTY"], 0)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["TOTAL_SALES"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["TOTAL_MAT_USE"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["TOTAL_MAT_COST"], 2)); ?></td>
            <?php for ($i = 1; $i <= 12; $i++) { ?>
                <td class="num"><?php echo h(excel_num($g["MONTH_QTY"][$i], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($g["MONTH_SALES"][$i], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($g["MONTH_MAT_USE"][$i], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($g["MONTH_MAT_COST"][$i], 2)); ?></td>
            <?php } ?>
        </tr>
    <?php } ?>
</table>
</body>
</html>
    <?php
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Actual Material Cost USD</title>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #9c9c9c;
            color: #000000;
            font-family: "Times New Roman", serif;
            font-size: 8px;
        }

        .filter {
            width: calc(100% - 20px);
            max-width: 1600px;
            margin: 8px auto;
            background: #d4d0c8;
            border: 1px solid #777777;
            padding: 8px;
            box-sizing: border-box;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            white-space: nowrap;
        }

        .filter input {
            height: 24px;
            border: 1px solid #777777;
            padding: 2px 5px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            box-sizing: border-box;
        }

        .btn {
            height: 26px;
            padding: 2px 12px;
            border: 1px solid #777777;
            background: #eeeeee;
            cursor: pointer;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            color: #000000;
            text-decoration: none;
            box-sizing: border-box;
        }

        .toolbar {
            width: calc(100% - 20px);
            max-width: 1600px;
            margin: 0 auto 6px auto;
            text-align: right;
        }

        .page {
            width: calc(100% - 20px);
            max-width: 1600px;
            min-height: 780px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 12px 10px;
            border: 2px solid #000000;
            box-sizing: border-box;
            overflow-x: auto;
        }

        .topline {
            position: relative;
            min-height: 44px;
        }

        .company {
            position: absolute;
            left: 0;
            top: 0;
            font-size: 12px;
        }

        .dept {
            position: absolute;
            left: 0;
            top: 18px;
            font-size: 9px;
        }

        .title {
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            padding-top: 6px;
        }

        .year {
            text-align: center;
            font-size: 9px;
            padding-top: 6px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 6px;
            font-size: 6px;
        }

        table.report th,
        table.report td {
            border: 1px dotted #000000;
            padding: 1px 1px;
            vertical-align: top;
            line-height: 8px;
            overflow: hidden;
        }

        table.report th {
            text-align: center;
            font-weight: normal;
        }

        table.report td.num {
            text-align: right;
            white-space: nowrap;
        }

        .cust-row td {
            font-weight: bold;
            padding-top: 4px;
            background: #ffffff;
        }

        .total-row td {
            font-weight: bold;
            border-top: 1px solid #000000;
        }

        .no-data {
            padding: 60px 0;
            text-align: center;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 14px;
        }

        @page {
            size: A3 landscape;
            margin: 5mm;
        }

        @media print {
            html, body {
                background: #ffffff;
            }
            .filter,
            .toolbar {
                display: none;
            }
            .page {
                width: 100%;
                max-width: none;
                min-height: auto;
                margin: 0;
                border: none;
                padding: 0;
                overflow: visible;
            }
            table.report {
                font-size: 5px;
            }
            table.report th,
            table.report td {
                padding: 1px;
                line-height: 6px;
            }
        }
    </style>
</head>
<body>

<div class="filter">
    <form method="get">
        <b>Actual Material Cost USD</b>
        &nbsp;&nbsp;
        Year:
        <input type="number" name="year" value="<?php echo h($yearInput); ?>" style="width:80px;">
        &nbsp;
        Customer:
        <input type="text" name="cust" value="<?php echo h($custFilter); ?>" list="custList" placeholder="ALL / cust code" style="width:130px;">
        <datalist id="custList">
            <?php foreach ($custList as $c) { ?>
                <option value="<?php echo h($c["CUST_CODE"]); ?>"><?php echo h($c["CUST_COMP"]); ?></option>
            <?php } ?>
        </datalist>
        &nbsp;
        Part:
        <input type="text" name="part" value="<?php echo h($partFilter); ?>" placeholder="Part code / name" style="width:170px;">
        &nbsp;
        Material:
        <input type="text" name="mat" value="<?php echo h($matFilter); ?>" placeholder="Material code / name" style="width:170px;">
        &nbsp;
        <button type="submit" class="btn">FILTER</button>
        <a href="actual_material_cost_usd.php?year=<?php echo h($yearInput); ?>" class="btn">ALL</a>
        <button type="submit" name="export" value="excel" class="btn">EXPORT EXCEL</button>
    </form>
</div>

<div class="toolbar">
    <button type="button" class="btn" onclick="window.print()">PRINT</button>
    <button type="button" class="btn" onclick="window.location.href='dashboard_ppic.php'">CLOSE</button>
</div>

<div class="page">
    <div class="topline">
        <div class="company">PT. IMC TEKNO INDONESIA</div>
        <div class="dept">PPIC</div>
        <div class="title">ACTUAL SALES AND MATERIAL COST (USD)</div>
        <div class="year">YEAR: <?php echo h($yearInput); ?></div>
    </div>

    <?php if ($totalRows == 0) { ?>
        <div class="no-data">Data actual material cost tidak ditemukan.</div>
    <?php } else { ?>
        <table class="report">
            <colgroup>
                <col style="width:4%;">
                <col style="width:5%;">
                <col style="width:11%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:5%;">
                <col style="width:8%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <?php for ($i = 1; $i <= 12; $i++) { ?>
                    <col style="width:2.2%;">
                    <col style="width:2.8%;">
                    <col style="width:2.5%;">
                    <col style="width:2.8%;">
                <?php } ?>
            </colgroup>
            <thead>
                <tr>
                    <th colspan="4">PART</th>
                    <th colspan="5">MATERIAL</th>
                    <?php for ($i = 1; $i <= 12; $i++) { ?>
                        <th colspan="4"><?php echo h(month_name_short($i)); ?></th>
                    <?php } ?>
                </tr>
                <tr>
                    <th>Code</th>
                    <th>No</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Weight</th>
                    <th>Price</th>
                    <th>Unit</th>
                    <?php for ($i = 1; $i <= 12; $i++) { ?>
                        <th>Qty</th>
                        <th>Sales</th>
                        <th>M.Use</th>
                        <th>M.Cost</th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $g) { ?>
                    <tr class="cust-row">
                        <td colspan="57"><?php echo h($g["CUST_CODE"] . " " . $g["CUST_COMP"]); ?></td>
                    </tr>

                    <?php foreach ($g["ROWS"] as $r) { ?>
                        <tr>
                            <td><?php echo h($r["PART_NUM"]); ?></td>
                            <td><?php echo h($r["PART_NO"]); ?></td>
                            <td><?php echo h($r["PART_NAME"]); ?></td>
                            <td class="num"><?php echo h(n4($r["PART_PRICE"])); ?></td>
                            <td><?php echo h($r["MAT_CODE"]); ?></td>
                            <td><?php echo h($r["MAT_NAME"]); ?></td>
                            <td class="num"><?php echo h(n4($r["BOM_QTY"])); ?></td>
                            <td class="num"><?php echo h(n4($r["MAT_PRICE"])); ?></td>
                            <td><?php echo h($r["UNIT"]); ?></td>
                            <?php for ($i = 1; $i <= 12; $i++) { ?>
                                <td class="num"><?php echo h(n0($r["QTY"][$i])); ?></td>
                                <td class="num"><?php echo h(n2($r["SALES"][$i])); ?></td>
                                <td class="num"><?php echo h(n2($r["MAT_USE"][$i])); ?></td>
                                <td class="num"><?php echo h(n2($r["MAT_COST"][$i])); ?></td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</div>

</body>
</html>
