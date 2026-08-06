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
function pct2($value) {
    if ($value === null || $value === "") {
        return "";
    }
    $n = floatval($value);
    if (abs($n) < 0.000001) {
        return "-";
    }
    return number_format($n, 2, ".", ",") . "%";
}

/*
    BOM rule mengikuti panduan harga material:
    - Material satuan PCS/PC/PCE/EA/SET tidak dibagi 1000.
    - Material selain itu diasumsikan weight/gram dan dikonversi ke KG dengan /1000.
*/
function bom_divisor($unit) {
    $u = strtoupper(trim((string)$unit));
    $pieceUnits = array("PCS", "PC", "PCE", "EA", "SET", "UNIT");
    if (in_array($u, $pieceUnits, true)) {
        return 1.0;
    }
    return 1000.0;
}

function key_num($value) {
    return number_format(floatval($value), 8, ".", "");
}


function empty_month_array($value) {
    $arr = array();
    for ($i = 1; $i <= 12; $i++) {
        $arr[$i] = $value;
    }
    return $arr;
}

function part_text($row, $field) {
    if (!isset($row["IS_FIRST_PART_ROW"]) || intval($row["IS_FIRST_PART_ROW"]) != 1) {
        return "";
    }
    return isset($row[$field]) ? $row[$field] : "";
}

function part_value($row, $field) {
    if (!isset($row["IS_FIRST_PART_ROW"]) || intval($row["IS_FIRST_PART_ROW"]) != 1) {
        return null;
    }
    return isset($row[$field]) ? $row[$field] : null;
}

function part_sales_key_from_row($row) {
    $currCode = isset($row["CURR_CODE"]) ? $row["CURR_CODE"] : "";
    $rate = isset($row["RATE"]) ? $row["RATE"] : 0;
    return (isset($row["CUST_CODE"]) ? $row["CUST_CODE"] : "") . "|" .
        (isset($row["PART_NUM"]) ? $row["PART_NUM"] : "") . "|" .
        (isset($row["PART_NO"]) ? $row["PART_NO"] : "") . "|" .
        (isset($row["PART_NAME"]) ? $row["PART_NAME"] : "") . "|" .
        $currCode . "|" .
        key_num(isset($row["PART_PRICE"]) ? $row["PART_PRICE"] : 0) . "|" .
        key_num($rate);
}

/*
    Finalisasi perhitungan mengikuti contoh Excel/Crystal:
    - Satu part bisa punya beberapa material/BOM.
    - Qty dan Sales Part hanya dihitung dan ditampilkan 1 kali pada baris material pertama.
    - M.Use dan M.Cost tetap dihitung untuk setiap material.
    - Total M.Cost/Sales % dihitung per part dari TOTAL semua material part / TOTAL sales part.
    Dengan ini subtotal tidak double count ketika part mempunyai lebih dari satu material.
*/
function finalize_excel_style_groups(&$groups) {
    foreach ($groups as $gKey => &$g) {
        $g["TOTAL_QTY"] = 0;
        $g["TOTAL_SALES"] = 0;
        $g["TOTAL_MAT_USE"] = 0;
        $g["TOTAL_MAT_COST"] = 0;
        for ($i = 1; $i <= 12; $i++) {
            $g["MONTH_QTY"][$i] = 0;
            $g["MONTH_SALES"][$i] = 0;
            $g["MONTH_MAT_USE"][$i] = 0;
            $g["MONTH_MAT_COST"][$i] = 0;
        }

        $partSummary = array();
        $partOrder = array();
        $rowCount = count($g["ROWS"]);

        for ($idx = 0; $idx < $rowCount; $idx++) {
            $row = $g["ROWS"][$idx];
            $pKey = part_sales_key_from_row($row);

            if (!isset($partSummary[$pKey])) {
                $partSummary[$pKey] = array(
                    "FIRST_INDEX" => $idx,
                    "TOTAL_QTY" => isset($row["TOTAL_QTY"]) ? $row["TOTAL_QTY"] : 0,
                    "TOTAL_SALES" => isset($row["TOTAL_SALES"]) ? $row["TOTAL_SALES"] : 0,
                    "TOTAL_MAT_USE" => 0,
                    "TOTAL_MAT_COST" => 0,
                    "MONTH_QTY" => isset($row["QTY"]) ? $row["QTY"] : empty_month_array(0),
                    "MONTH_SALES" => isset($row["SALES"]) ? $row["SALES"] : empty_month_array(0),
                    "MONTH_MAT_USE" => empty_month_array(0),
                    "MONTH_MAT_COST" => empty_month_array(0)
                );
                $partOrder[] = $pKey;
            }

            $partSummary[$pKey]["TOTAL_MAT_USE"] += isset($row["TOTAL_MAT_USE"]) ? $row["TOTAL_MAT_USE"] : 0;
            $partSummary[$pKey]["TOTAL_MAT_COST"] += isset($row["TOTAL_MAT_COST"]) ? $row["TOTAL_MAT_COST"] : 0;
            $g["TOTAL_MAT_USE"] += isset($row["TOTAL_MAT_USE"]) ? $row["TOTAL_MAT_USE"] : 0;
            $g["TOTAL_MAT_COST"] += isset($row["TOTAL_MAT_COST"]) ? $row["TOTAL_MAT_COST"] : 0;

            for ($i = 1; $i <= 12; $i++) {
                $mu = (isset($row["MAT_USE"]) && isset($row["MAT_USE"][$i])) ? $row["MAT_USE"][$i] : 0;
                $mc = (isset($row["MAT_COST"]) && isset($row["MAT_COST"][$i])) ? $row["MAT_COST"][$i] : 0;
                $partSummary[$pKey]["MONTH_MAT_USE"][$i] += $mu;
                $partSummary[$pKey]["MONTH_MAT_COST"][$i] += $mc;
                $g["MONTH_MAT_USE"][$i] += $mu;
                $g["MONTH_MAT_COST"][$i] += $mc;
            }
        }

        foreach ($partOrder as $pKey) {
            $s = $partSummary[$pKey];
            $g["TOTAL_QTY"] += $s["TOTAL_QTY"];
            $g["TOTAL_SALES"] += $s["TOTAL_SALES"];
            for ($i = 1; $i <= 12; $i++) {
                $g["MONTH_QTY"][$i] += isset($s["MONTH_QTY"][$i]) ? $s["MONTH_QTY"][$i] : 0;
                $g["MONTH_SALES"][$i] += isset($s["MONTH_SALES"][$i]) ? $s["MONTH_SALES"][$i] : 0;
            }
        }

        for ($idx = 0; $idx < $rowCount; $idx++) {
            $pKey = part_sales_key_from_row($g["ROWS"][$idx]);
            $s = $partSummary[$pKey];
            $isFirst = ($idx == $s["FIRST_INDEX"]);

            $g["ROWS"][$idx]["IS_FIRST_PART_ROW"] = $isFirst ? 1 : 0;
            $g["ROWS"][$idx]["MCOST_SALES_PCT"] = ($isFirst && $s["TOTAL_SALES"] != 0)
                ? (($s["TOTAL_MAT_COST"] / $s["TOTAL_SALES"]) * 100.0)
                : null;
            $g["ROWS"][$idx]["DISPLAY_TOTAL_QTY"] = $isFirst ? $s["TOTAL_QTY"] : null;
            $g["ROWS"][$idx]["DISPLAY_TOTAL_SALES"] = $isFirst ? $s["TOTAL_SALES"] : null;
            $g["ROWS"][$idx]["DISPLAY_QTY"] = $isFirst ? $s["MONTH_QTY"] : empty_month_array(null);
            $g["ROWS"][$idx]["DISPLAY_SALES"] = $isFirst ? $s["MONTH_SALES"] : empty_month_array(null);
        }
    }
    unset($g);
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
$matFilter = get_value("mat", "");
$export = strtolower(get_value("export", ""));
$printDate = date("d-M-Y H:i:s");

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
   LOAD DATA DARI RPT_FORECAST_MATERIAL_COST_ESTIMATION_IDR
====================================================== */
$sql = "EXEC dbo.RPT_FORECAST_MATERIAL_COST_ESTIMATION_IDR ?";
$stmt = sqlsrv_query(
    $conn,
    $sql,
    array($yearInput),
    array("QueryTimeout" => 0)
);

if ($stmt === false) {
    die("<pre>Query RPT_FORECAST_MATERIAL_COST_ESTIMATION_IDR error:\n" . sql_error_text() . "</pre>");
}

$groups = array();
$totalRows = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $custCode = isset($r["CUST_CODE"]) ? trim((string)$r["CUST_CODE"]) : "";
    $custComp = isset($r["CUST_COMP"]) ? trim((string)$r["CUST_COMP"]) : "";
    $partNum = isset($r["PART_NUM"]) ? trim((string)$r["PART_NUM"]) : "";
    $partNo = isset($r["PART_NO"]) ? trim((string)$r["PART_NO"]) : "";
    $partName = isset($r["PART_NAME"]) ? trim((string)$r["PART_NAME"]) : "";
    $matCode = isset($r["MAT_CODE"]) ? trim((string)$r["MAT_CODE"]) : "";
    $matName = isset($r["MAT_NAME"]) ? trim((string)$r["MAT_NAME"]) : "";
    $unit = isset($r["UNIT"]) ? trim((string)$r["UNIT"]) : "";
    $currCode = isset($r["CURR_CODE"]) ? trim((string)$r["CURR_CODE"]) : "";
    $matCur = isset($r["MAT_CUR"]) ? trim((string)$r["MAT_CUR"]) : "";

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
    $matPrice = isset($r["MAT_PRICE"]) ? floatval($r["MAT_PRICE"]) : 0;
    $bomQty = isset($r["BOM_QTY"]) ? floatval($r["BOM_QTY"]) : 0;
    $rate = isset($r["CURR_VRATE"]) ? floatval($r["CURR_VRATE"]) : 0;

    $bomDivisor = bom_divisor($unit);

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

        $matUse[$i] = ($qty[$i] * $bomQty) / $bomDivisor;
        $matCost[$i] = $matUse[$i] * $matPrice;

        $totalQty += $qty[$i];
        $totalSales += $sales[$i];
        $totalMatUse += $matUse[$i];
        $totalMatCost += $matCost[$i];
    }

    $mcostSalesPct = ($totalSales != 0) ? (($totalMatCost / $totalSales) * 100.0) : 0;

    $groupKey = $custCode . "|" . $custComp;
    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = array(
            "CUST_CODE" => $custCode,
            "CUST_COMP" => $custComp,
            "ROWS" => array(),
            "SALES_KEYS" => array(),
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
        "MCOST_SALES_PCT" => $mcostSalesPct,
        "CURR_CODE" => $currCode,
        "PART_PRICE" => $partPrice,
        "RATE" => $rate,
        "UNIT" => $unit,
        "MAT_CODE" => $matCode,
        "MAT_NAME" => $matName,
        "BOM_QTY" => $bomQty,
        "MAT_CUR" => $matCur,
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

    /*
        Qty dan Sales berasal dari part, sedangkan baris laporan sudah pecah per material/BOM.
        Supaya subtotal/grand total tidak double count, Qty/Sales hanya ditambahkan
        sekali untuk kombinasi customer + part + harga/rate. Material use/cost tetap
        dijumlahkan per baris material.
    */
    $salesKey = $custCode . "|" . $partNum . "|" . $partNo . "|" . $partName . "|" .
        (isset($currCode) ? $currCode : "") . "|" . key_num($partPrice) . "|" . key_num(isset($rate) ? $rate : 0);

    $isNewSalesKey = !isset($groups[$groupKey]["SALES_KEYS"][$salesKey]);
    if ($isNewSalesKey) {
        $groups[$groupKey]["SALES_KEYS"][$salesKey] = true;
        $groups[$groupKey]["TOTAL_QTY"] += $totalQty;
        $groups[$groupKey]["TOTAL_SALES"] += $totalSales;
    }

    $groups[$groupKey]["TOTAL_MAT_USE"] += $totalMatUse;
    $groups[$groupKey]["TOTAL_MAT_COST"] += $totalMatCost;

    for ($i = 1; $i <= 12; $i++) {
        if ($isNewSalesKey) {
            $groups[$groupKey]["MONTH_QTY"][$i] += $qty[$i];
            $groups[$groupKey]["MONTH_SALES"][$i] += $sales[$i];
        }
        $groups[$groupKey]["MONTH_MAT_USE"][$i] += $matUse[$i];
        $groups[$groupKey]["MONTH_MAT_COST"][$i] += $matCost[$i];
    }

    $totalRows++;
}

ksort($groups);
finalize_excel_style_groups($groups);

/* ======================================================
   EXPORT EXCEL
====================================================== */
if ($export == "excel") {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $fileName = "SALES_FORECAST_MATERIAL_COST_IDR_" . $yearInput . "_" . date("Ymd_His") . ".xls";

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
    <tr><td colspan="66" class="title">PT. IMC TEKNO INDONESIA</td></tr>
    <tr><td colspan="66" class="title" style="text-align:center;">SALES FORECAST AND MATERIAL COST ESTIMATION (IDR)</td></tr>
    <tr><td colspan="66" class="info">YEAR : <?php echo h($yearInput); ?> | Export Date : <?php echo h($printDate); ?></td></tr>
    <tr><td colspan="66" class="info"></td></tr>

    <tr>
        <th colspan="7">PART</th>
        <th colspan="5">MATERIAL</th>
        <th colspan="4">TOTAL</th>
        <?php for ($i = 1; $i <= 12; $i++) { ?>
            <th colspan="4"><?php echo h(month_name_short($i) . "-" . substr((string)$yearInput, 2, 2)); ?></th>
        <?php } ?>
    </tr>
    <tr>
        <th>Code</th>
        <th>No</th>
        <th>Name</th>
        <th>M.Cost/Sales %</th>
        <th>Curr</th>
        <th>Price</th>
        <th>Cust</th>
        <th>Code</th>
        <th>Name</th>
        <th>Weight</th>
        <th>Curr</th>
        <th>Price</th>
        <th>Qty</th>
        <th>Sales IDR</th>
        <th>M.Use</th>
        <th>Mat Cost IDR</th>
        <?php for ($i = 1; $i <= 12; $i++) { ?>
            <th>Qty</th>
            <th>Sales</th>
            <th>M.Use</th>
            <th>M.Cost</th>
        <?php } ?>
    </tr>

    <?php foreach ($groups as $g) { ?>
        <tr class="cust">
            <td colspan="66"><?php echo h($g["CUST_CODE"] . " - " . $g["CUST_COMP"]); ?></td>
        </tr>

        <?php foreach ($g["ROWS"] as $r) { ?>
            <tr>
                <td><?php echo h(part_text($r, "PART_NUM")); ?></td>
                <td><?php echo h(part_text($r, "PART_NO")); ?></td>
                <td><?php echo h(part_text($r, "PART_NAME")); ?></td>
                <td class="num2"><?php echo h(excel_num($r["MCOST_SALES_PCT"], 2)); ?></td>
                <td><?php echo h(part_text($r, "CURR_CODE")); ?></td>
                <td class="num2"><?php echo h(excel_num(part_value($r, "PART_PRICE"), 2)); ?></td>
                <td><?php echo h(part_text($r, "CUST_COMP")); ?></td>
                <td><?php echo h($r["MAT_CODE"]); ?></td>
                <td><?php echo h($r["MAT_NAME"]); ?></td>
                <td class="num4"><?php echo h(excel_num($r["BOM_QTY"], 4)); ?></td>
                <td><?php echo h($r["MAT_CUR"]); ?></td>
                <td class="num2"><?php echo h(excel_num($r["MAT_PRICE"], 2)); ?></td>
                <td class="num"><?php echo h(excel_num($r["DISPLAY_TOTAL_QTY"], 0)); ?></td>
                <td class="num"><?php echo h(excel_num($r["DISPLAY_TOTAL_SALES"], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["TOTAL_MAT_USE"], 2)); ?></td>
                <td class="num"><?php echo h(excel_num($r["TOTAL_MAT_COST"], 0)); ?></td>
                <?php for ($i = 1; $i <= 12; $i++) { ?>
                    <td class="num"><?php echo h(excel_num($r["DISPLAY_QTY"][$i], 0)); ?></td>
                    <td class="num"><?php echo h(excel_num($r["DISPLAY_SALES"][$i], 0)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["MAT_USE"][$i], 2)); ?></td>
                    <td class="num"><?php echo h(excel_num($r["MAT_COST"][$i], 0)); ?></td>
                <?php } ?>
            </tr>
        <?php } ?>

        <tr class="total">
            <td colspan="3">TOTAL <?php echo h($g["CUST_COMP"]); ?></td>
            <td class="num2"><?php echo h(excel_num(($g["TOTAL_SALES"] != 0) ? (($g["TOTAL_MAT_COST"] / $g["TOTAL_SALES"]) * 100.0) : 0, 2)); ?></td>
            <td colspan="8"></td>
            <td class="num"><?php echo h(excel_num($g["TOTAL_QTY"], 0)); ?></td>
            <td class="num"><?php echo h(excel_num($g["TOTAL_SALES"], 0)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["TOTAL_MAT_USE"], 2)); ?></td>
            <td class="num"><?php echo h(excel_num($g["TOTAL_MAT_COST"], 0)); ?></td>
            <?php for ($i = 1; $i <= 12; $i++) { ?>
                <td class="num"><?php echo h(excel_num($g["MONTH_QTY"][$i], 0)); ?></td>
                <td class="num"><?php echo h(excel_num($g["MONTH_SALES"][$i], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($g["MONTH_MAT_USE"][$i], 2)); ?></td>
                <td class="num"><?php echo h(excel_num($g["MONTH_MAT_COST"][$i], 0)); ?></td>
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
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales Forecast Material Cost IDR</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
        html,body{max-width:100%;overflow-x:hidden}
        body{background:#eef1f5;font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif;font-size:12px;padding-bottom:30px}
        
        .page-shell{width:100%;padding:10px 15px 0}
        
        .panel-filter .panel-heading{padding:8px 15px;background-color:#2c3e50;border-color:#2c3e50}
        .panel-filter .panel-title{font-size:14px;font-weight:600;color:#fff;margin:0}
        .panel-filter .panel-body{padding:12px 15px;background:#fff}
        .filter-group{margin-right:6px;margin-bottom:5px}
        .filter-group label{display:block;font-size:10px;font-weight:600;color:#555;margin-bottom:2px}
        .filter-group .form-control{height:32px;font-size:11px;padding:4px 8px;border-radius:3px}
        .btn-action{height:32px;font-size:11px;padding:4px 12px;border-radius:3px;margin-right:4px}
        
        .info-bar{background:#fff;border:1px solid #dce1e8;border-radius:4px;padding:10px 15px;margin-bottom:10px;font-size:12px;line-height:1.8}
        .info-bar strong{color:#2c3e50}
        
        /* TABLE CONTAINER & SCROLL */
        .table-container{background:#fff;border:1px solid #c8ced6;border-radius:4px;overflow:hidden;margin-bottom:15px}
        .table-scroll{overflow-x:auto;overflow-y:visible}
        
        /* MODERN REPORT TABLE STYLING */
        .report-table{width:100%;min-width:3600px;border-collapse:separate;border-spacing:0;margin:0;font-size:10px;line-height:1.4}
        .report-table thead th{background:#3a5ba0;color:#fff;border:1px solid #2c4a87;border-top:none;padding:5px 4px;text-align:center;vertical-align:middle;white-space:nowrap;font-weight:600;font-size:9px}
        .report-table thead tr:nth-child(2) th{background:#4a6db5;border-top:1px solid #5a7dc5;font-size:8px}
        .report-table tbody td{border:1px solid #dce1e8;border-top:none;padding:4px 5px;vertical-align:middle;background:#fff}
        .report-table tbody tr.data-row td{border-top:1px solid #e8ecf1}
        .report-table tbody tr.data-row:nth-child(even) td{background:#f6f8fb}
        .report-table tbody tr.data-row:hover td{background:#e3edf7!important}
        
        /* TOTAL ROWS */
        .report-table tbody tr.mc-total td{background:#fff8e1!important;font-weight:700;border-top:2px solid #e6c84b;border-bottom:2px solid #e6c84b;color:#5d4e00}
        .report-table tbody tr.grand-total td{background:#1a3a6b!important;color:#fff!important;font-weight:700;border-top:3px solid #0f2548;border-bottom:3px solid #0f2548}
        
        .t-r{text-align:right;white-space:nowrap}.t-c{text-align:center;white-space:nowrap}.t-l{text-align:left}
        .c-pos{color:#1b8c3e;font-weight:700}.c-neg{color:#c0392b;font-weight:700}.c-blue{color:#2471a3;font-weight:600}
        
        /* COLUMN WIDTHS */
        .col-pcode{width:90px;min-width:90px;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .col-pnum{width:100px;min-width:100px;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .col-pname{width:150px;min-width:150px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .col-pct{width:85px;min-width:85px;max-width:85px}
        .col-curr{width:40px;min-width:40px;max-width:40px}
        .col-price{width:60px;min-width:60px;max-width:60px}
        .col-mcode{width:80px;min-width:80px;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .col-mname{width:120px;min-width:120px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .col-unit{width:45px;min-width:45px;max-width:45px}
        .col-qty{width:50px;min-width:50px;max-width:50px}
        .col-sls{width:70px;min-width:70px;max-width:70px}
        .col-mus{width:55px;min-width:55px;max-width:55px}
        .col-mcs{width:70px;min-width:70px;max-width:70px}

        /* STICKY COLUMNS */
        .sk1{position:sticky;left:0;z-index:5}
        .sk2{position:sticky;left:90px;z-index:5}
        .sk3{position:sticky;left:190px;z-index:5}
        .sk4{position:sticky;left:340px;z-index:5}
        
        .report-table thead th.sk1, .report-table thead th.sk2, .report-table thead th.sk3, .report-table thead th.sk4 {z-index:10}
        .report-table thead th{z-index:3}
        
        .sk1,.sk2,.sk3,.sk4{-webkit-transform:translateZ(0);transform:translateZ(0);will-change:left;-webkit-backface-visibility:hidden;backface-visibility:hidden}
        
        .report-table tbody td.sk1{background:#fff!important}
        .report-table tbody td.sk2{background:#fff!important}
        .report-table tbody td.sk3{background:#fff!important}
        .report-table tbody td.sk4{background:#fff!important}
        
        .report-table tbody tr.data-row:nth-child(even) td.sk1{background:#f6f8fb!important}
        .report-table tbody tr.data-row:nth-child(even) td.sk2{background:#f6f8fb!important}
        .report-table tbody tr.data-row:nth-child(even) td.sk3{background:#f6f8fb!important}
        .report-table tbody tr.data-row:nth-child(even) td.sk4{background:#f6f8fb!important}
        
        .report-table tbody tr.data-row:hover td.sk1{background:#e3edf7!important}
        .report-table tbody tr.data-row:hover td.sk2{background:#e3edf7!important}
        .report-table tbody tr.data-row:hover td.sk3{background:#e3edf7!important}
        .report-table tbody tr.data-row:hover td.sk4{background:#e3edf7!important}
        
        .report-table tbody tr.mc-total td.sk1{background:#fff8e1!important}
        .report-table tbody tr.mc-total td.sk2{background:#fff8e1!important}
        .report-table tbody tr.mc-total td.sk3{background:#fff8e1!important}
        .report-table tbody tr.mc-total td.sk4{background:#fff8e1!important}
        
        .report-table thead th.sk1{background:#3a5ba0!important}
        .report-table thead th.sk2{background:#3a5ba0!important}
        .report-table thead th.sk3{background:#3a5ba0!important}
        .report-table thead th.sk4{background:#3a5ba0!important}
        
        .sk4::after{content:'';position:absolute;top:0;right:-4px;bottom:0;width:4px;background:#3a5ba0;z-index:11;pointer-events:none}
        .report-table thead tr:nth-child(2) th.sk4::after{background:#4a6db5}
        .report-table tbody tr.mc-total td.sk4::after{background:#e6c84b}
        
        .empty-state { text-align:center; padding: 40px; color: #777; font-size: 14px; }

        @media print{
            body{background:#fff!important;padding:0!important;font-size:8px}
            .panel-filter,.btn-action{display:none!important}
            .table-container{border:none!important;border-radius:0!important;margin-bottom:0;}
            .table-scroll{overflow:visible!important}
            .report-table{min-width:0!important;font-size:6.5px}
            .sk1,.sk2,.sk3,.sk4{position:static!important;transform:none!important;will-change:auto!important;backface-visibility:visible!important}
            .sk4::after{display:none!important}
            .info-bar{border:none;padding:3px 0;margin-bottom:5px}
        }
    </style>
</head>
<body>
<div class="container-fluid page-shell">

    <!-- PANEL FILTER -->
    <div class="panel panel-default panel-filter">
        <div class="panel-heading"><h4 class="panel-title"><span class="glyphicon glyphicon-filter"></span> Filter Sales Forecast Material Cost IDR</h4></div>
        <div class="panel-body">
            <form class="form-inline" method="get" action="">
                <div class="form-group filter-group" style="width:80px">
                    <label>Year</label>
                    <input type="number" class="form-control" name="year" value="<?php echo h($yearInput); ?>">
                </div>
                <div class="form-group filter-group" style="width:160px">
                    <label>Customer</label>
                    <input type="text" class="form-control" name="cust" value="<?php echo h($custFilter); ?>" list="custList" placeholder="ALL / cust code">
                    <datalist id="custList">
                        <?php foreach ($custList as $c) { ?>
                            <option value="<?php echo h($c["CUST_CODE"]); ?>"><?php echo h($c["CUST_COMP"]); ?></option>
                        <?php } ?>
                    </datalist>
                </div>
                <div class="form-group filter-group" style="width:160px">
                    <label>Part</label>
                    <input type="text" class="form-control" name="part" value="<?php echo h($partFilter); ?>" placeholder="Part code / name">
                </div>
                <div class="form-group filter-group" style="width:160px">
                    <label>Material</label>
                    <input type="text" class="form-control" name="mat" value="<?php echo h($matFilter); ?>" placeholder="Material code / name">
                </div>
                <div class="form-group filter-group">
                    <label>&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary btn-action"><span class="glyphicon glyphicon-search"></span> Filter</button>
                        <a href="?year=<?php echo h($yearInput); ?>" class="btn btn-default btn-action">All</a>
                        <button type="submit" name="export" value="excel" class="btn btn-success btn-action"><span class="glyphicon glyphicon-download"></span> Export Excel</button>
                        <button type="button" class="btn btn-default btn-action" onclick="window.print()"><span class="glyphicon glyphicon-print"></span> Print</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- INFO BAR -->
    <div class="info-bar">
        <div class="row">
            <div class="col-md-12">
                <strong>SALES FORECAST AND MATERIAL COST ESTIMATION (IDR)</strong>
                <span class="label label-info" style="margin-left:8px">YEAR: <?php echo h($yearInput); ?></span>
                <span class="pull-right">
                    Customer: <strong><?php echo $custFilter != "" ? h($custFilter) : "ALL"; ?></strong> &nbsp;&bull;&nbsp; 
                    Data: <strong><?php echo number_format($totalRows, 0, ',', '.'); ?></strong> baris &nbsp;&bull;&nbsp; 
                    Export Date: <strong><?php echo h($printDate); ?></strong>
                </span>
            </div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-container">
        <div class="table-scroll">
            <?php if ($totalRows == 0) { ?>
                <div class="empty-state"><span class="glyphicon glyphicon-info-sign"></span> Data forecast material cost IDR tidak ditemukan.</div>
            <?php } else { ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th class="sk1 col-pcode" rowspan="2">Part Code</th>
                        <th class="sk2 col-pnum" rowspan="2">Part No</th>
                        <th class="sk3 col-pname" rowspan="2">Part Name</th>
                        <th class="sk4 col-pct" rowspan="2">Total<br>M.Cost/Sales %</th>
                        <th colspan="2">PART</th>
                        <th colspan="6">MATERIAL</th>
                        <?php for ($i = 1; $i <= 12; $i++) { ?>
                            <th colspan="4"><?php echo h(month_name_short($i)); ?></th>
                        <?php } ?>
                    </tr>
                    <tr>
                        <!-- 4 Sticky Columns handled by rowspan -->
                        <th class="col-curr">Curr</th>
                        <th class="col-price">Price</th>
                        <th class="col-mcode">Code</th>
                        <th class="col-mname">Name</th>
                        <th class="col-price">Weight</th>
                        <th class="col-curr">Curr</th>
                        <th class="col-price">Price</th>
                        <th class="col-unit">Unit</th>
                        <?php for ($i = 1; $i <= 12; $i++) { ?>
                            <th class="col-qty">Qty</th>
                            <th class="col-sls">Sales</th>
                            <th class="col-mus">M.Use</th>
                            <th class="col-mcs">M.Cost</th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $g) { ?>
                        <!-- Group Header Customer -->
                        <tr class="grand-total">
                            <td colspan="60" class="t-l" style="position:sticky; left:0; z-index:4; background:#1a3a6b!important; color:#fff!important;">
                                CUSTOMER: <?php echo h($g["CUST_CODE"] . " - " . $g["CUST_COMP"]); ?>
                            </td>
                        </tr>

                        <!-- Item Rows -->
                        <?php foreach ($g["ROWS"] as $r) { ?>
                            <tr class="data-row">
                                <td class="sk1 t-l" title="<?php echo h(part_text($r, "PART_NUM")); ?>"><?php echo h(part_text($r, "PART_NUM")); ?></td>
                                <td class="sk2 t-l" title="<?php echo h(part_text($r, "PART_NO")); ?>"><?php echo h(part_text($r, "PART_NO")); ?></td>
                                <td class="sk3 t-l" title="<?php echo h(part_text($r, "PART_NAME")); ?>"><?php echo h(part_text($r, "PART_NAME")); ?></td>
                                <td class="sk4 t-r c-blue"><?php echo h(pct2($r["MCOST_SALES_PCT"])); ?></td>
                                <td class="t-c"><?php echo h(part_text($r, "CURR_CODE")); ?></td>
                                <td class="t-r c-blue"><?php echo h(n2(part_value($r, "PART_PRICE"))); ?></td>
                                <td class="t-l" title="<?php echo h($r["MAT_CODE"]); ?>"><?php echo h($r["MAT_CODE"]); ?></td>
                                <td class="t-l" title="<?php echo h($r["MAT_NAME"]); ?>"><?php echo h($r["MAT_NAME"]); ?></td>
                                <td class="t-r"><?php echo h(n4($r["BOM_QTY"])); ?></td>
                                <td class="t-c"><?php echo h($r["MAT_CUR"]); ?></td>
                                <td class="t-r c-blue"><?php echo h(n2($r["MAT_PRICE"])); ?></td>
                                <td class="t-c"><?php echo h($r["UNIT"]); ?></td>
                                <?php for ($i = 1; $i <= 12; $i++) { 
                                    $qtyClass = $r["DISPLAY_QTY"][$i] > 0 ? "c-pos" : "";
                                    $costClass = $r["MAT_COST"][$i] > 0 ? "c-pos" : "";
                                ?>
                                    <td class="t-r <?php echo $qtyClass; ?>"><?php echo h(n0($r["DISPLAY_QTY"][$i])); ?></td>
                                    <td class="t-r"><?php echo h(n0($r["DISPLAY_SALES"][$i])); ?></td>
                                    <td class="t-r"><?php echo h(n2($r["MAT_USE"][$i])); ?></td>
                                    <td class="t-r <?php echo $costClass; ?>"><?php echo h(n0($r["MAT_COST"][$i])); ?></td>
                                <?php } ?>
                            </tr>
                        <?php } ?>

                        <!-- Sub Total Per Customer -->
                        <tr class="mc-total">
                            <td class="sk1 t-l">TOTAL</td>
                            <td class="sk2"></td>
                            <td class="sk3 t-l"><?php echo h($g["CUST_COMP"]); ?></td>
                            <td class="sk4 t-r c-blue"><?php echo h(pct2(($g["TOTAL_SALES"] != 0) ? (($g["TOTAL_MAT_COST"] / $g["TOTAL_SALES"]) * 100.0) : 0)); ?></td>
                            <td colspan="8"></td> <!-- Mengisi sisa kolom Part & Material -->
                            <?php for ($i = 1; $i <= 12; $i++) { ?>
                                <td class="t-r"><?php echo h(n0($g["MONTH_QTY"][$i])); ?></td>
                                <td class="t-r"><?php echo h(n0($g["MONTH_SALES"][$i])); ?></td>
                                <td class="t-r"><?php echo h(n2($g["MONTH_MAT_USE"][$i])); ?></td>
                                <td class="t-r"><?php echo h(n0($g["MONTH_MAT_COST"][$i])); ?></td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <?php } ?>
        </div>
    </div>

</div>

<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>