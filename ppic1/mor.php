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
    return rtrim(rtrim(number_format($n, 2, ".", ","), "0"), ".");
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

function month_title($date, $addMonth) {
    return date("F Y", strtotime("+" . intval($addMonth) . " month", strtotime($date)));
}

$monthInput = get_value("month", date("Y-m"));
$materialFilter = get_value("material", "");
$supplierFilter = get_value("supplier", "");
$export = strtolower(get_value("export", ""));

$startDate = $monthInput . "-01";
if (strtotime($startDate) === false) {
    $monthInput = date("Y-m");
    $startDate = date("Y-m-01");
}

$endDate = date("Y-m-t", strtotime("+2 month", strtotime($startDate)));
$monthLong = date("F Y", strtotime($startDate));
$m1 = month_title($startDate, 0);
$m2 = month_title($startDate, 1);
$m3 = month_title($startDate, 2);
$printDate = date("d-M-Y H:i:s");

/* ======================================================
   LOAD DATA DARI SP_MOR_ROLLING_SUPPLIER
====================================================== */
$sql = "EXEC dbo.SP_MOR_ROLLING_SUPPLIER ?, ?";
$stmt = sqlsrv_query($conn, $sql, array($startDate, $endDate));

if ($stmt === false) {
    die("<pre>Query SP_MOR_ROLLING_SUPPLIER error:\n" . sql_error_text() . "</pre>");
}

$supplierGroups = array();
$totalRows = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $matCode = isset($r["MAT_CODE"]) ? trim((string)$r["MAT_CODE"]) : "";
    $matName = isset($r["MAT_NAME"]) ? trim((string)$r["MAT_NAME"]) : "";
    $supCode = isset($r["SUP_CODE"]) ? trim((string)$r["SUP_CODE"]) : "";
    $supComp = isset($r["SUP_COMP"]) ? trim((string)$r["SUP_COMP"]) : "";

    if ($materialFilter != "") {
        $haystack = $matCode . " " . $matName;
        if (stripos($haystack, $materialFilter) === false) {
            continue;
        }
    }

    if ($supplierFilter != "") {
        $haystack = $supCode . " " . $supComp;
        if (stripos($haystack, $supplierFilter) === false) {
            continue;
        }
    }

    $row = array(
        "MAT_CODE" => $matCode,
        "MAT_NAME" => $matName,
        "D" => isset($r["D"]) ? trim((string)$r["D"]) : "",
        "SUP_CODE" => $supCode,
        "SUP_COMP" => $supComp,
        "TAG_INTERNAL" => isset($r["TAG_INTERNAL"]) ? floatval($r["TAG_INTERNAL"]) : 0,
        "TAG_EXTERNAL" => isset($r["TAG_EXTERNAL"]) ? floatval($r["TAG_EXTERNAL"]) : 0,
        "TAG_QTY" => isset($r["TAG_QTY"]) ? floatval($r["TAG_QTY"]) : 0,
        "POD_QTY" => isset($r["POD_QTY"]) ? floatval($r["POD_QTY"]) : 0,
        "RCVD_QTY" => isset($r["RCVD_QTY"]) ? floatval($r["RCVD_QTY"]) : 0,
        "OUTSTANDING2" => isset($r["OUTSTANDING2"]) ? floatval($r["OUTSTANDING2"]) : 0,
        "MAT_USE1" => isset($r["MAT_USE1"]) ? floatval($r["MAT_USE1"]) : 0,
        "MAT_USE2" => isset($r["MAT_USE2"]) ? floatval($r["MAT_USE2"]) : 0,
        "MAT_USE3" => isset($r["MAT_USE3"]) ? floatval($r["MAT_USE3"]) : 0,
        "SAFETY_QTY" => isset($r["SAFETY_QTY"]) ? floatval($r["SAFETY_QTY"]) : 0,
        "DEMAND1" => isset($r["DEMAND1"]) ? floatval($r["DEMAND1"]) : 0,
        "PURCHASE1" => isset($r["PURCHASE1"]) ? floatval($r["PURCHASE1"]) : 0,
        "PURCHASE_ADJUST1" => isset($r["PURCHASE_ADJUST1"]) ? floatval($r["PURCHASE_ADJUST1"]) : 0,
        "ENDST1" => isset($r["ENDST1"]) ? floatval($r["ENDST1"]) : 0,
        "DEMAND2" => isset($r["DEMAND2"]) ? floatval($r["DEMAND2"]) : 0,
        "PURCHASE2" => isset($r["PURCHASE2"]) ? floatval($r["PURCHASE2"]) : 0,
        "PURCHASE_ADJUST2" => isset($r["PURCHASE_ADJUST2"]) ? floatval($r["PURCHASE_ADJUST2"]) : 0,
        "ENDST2" => isset($r["ENDST2"]) ? floatval($r["ENDST2"]) : 0,
        "DEMAND3" => isset($r["DEMAND3"]) ? floatval($r["DEMAND3"]) : 0,
        "PURCHASE3" => isset($r["PURCHASE3"]) ? floatval($r["PURCHASE3"]) : 0,
        "PURCHASE_ADJUST3" => isset($r["PURCHASE_ADJUST3"]) ? floatval($r["PURCHASE_ADJUST3"]) : 0,
        "ENDST3" => isset($r["ENDST3"]) ? floatval($r["ENDST3"]) : 0
    );

    $groupKey = $supCode . "|" . $supComp;
    if (!isset($supplierGroups[$groupKey])) {
        $supplierGroups[$groupKey] = array(
            "SUP_CODE" => $supCode,
            "SUP_COMP" => $supComp,
            "ROWS" => array(),
            "SUM_TAG_INTERNAL" => 0,
            "SUM_TAG_EXTERNAL" => 0,
            "SUM_TAG_QTY" => 0,
            "SUM_PO" => 0,
            "SUM_RCVD" => 0,
            "SUM_USE1" => 0,
            "SUM_USE2" => 0,
            "SUM_USE3" => 0,
            "SUM_DEMAND1" => 0,
            "SUM_DEMAND2" => 0,
            "SUM_DEMAND3" => 0,
            "SUM_PURCHASE_ADJUST1" => 0,
            "SUM_PURCHASE_ADJUST2" => 0,
            "SUM_PURCHASE_ADJUST3" => 0
        );
    }

    $supplierGroups[$groupKey]["ROWS"][] = $row;
    $supplierGroups[$groupKey]["SUM_TAG_INTERNAL"] += $row["TAG_INTERNAL"];
    $supplierGroups[$groupKey]["SUM_TAG_EXTERNAL"] += $row["TAG_EXTERNAL"];
    $supplierGroups[$groupKey]["SUM_TAG_QTY"] += $row["TAG_QTY"];
    $supplierGroups[$groupKey]["SUM_PO"] += $row["POD_QTY"];
    $supplierGroups[$groupKey]["SUM_RCVD"] += $row["RCVD_QTY"];
    $supplierGroups[$groupKey]["SUM_USE1"] += $row["MAT_USE1"];
    $supplierGroups[$groupKey]["SUM_USE2"] += $row["MAT_USE2"];
    $supplierGroups[$groupKey]["SUM_USE3"] += $row["MAT_USE3"];
    $supplierGroups[$groupKey]["SUM_DEMAND1"] += $row["DEMAND1"];
    $supplierGroups[$groupKey]["SUM_DEMAND2"] += $row["DEMAND2"];
    $supplierGroups[$groupKey]["SUM_DEMAND3"] += $row["DEMAND3"];
    $supplierGroups[$groupKey]["SUM_PURCHASE_ADJUST1"] += $row["PURCHASE_ADJUST1"];
    $supplierGroups[$groupKey]["SUM_PURCHASE_ADJUST2"] += $row["PURCHASE_ADJUST2"];
    $supplierGroups[$groupKey]["SUM_PURCHASE_ADJUST3"] += $row["PURCHASE_ADJUST3"];

    $totalRows++;
}

ksort($supplierGroups);

/* ======================================================
   EXPORT EXCEL
   Kolom dibuat sama dengan tampilan layar:
   TOTAL hanya 1 kolom, REC = RCVD_QTY
====================================================== */
if ($export == "excel") {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $fileName = "MOR_PLANNING_" . date("Ym", strtotime($startDate)) . "_" . date("Ymd_His") . ".xls";

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
        table { border-collapse: collapse; font-family: Calibri, Arial, sans-serif; font-size: 10px; }
        th { background: #d9eaf7; font-weight: bold; text-align: center; border: 1px solid #000000; mso-number-format:"\@"; }
        td { border: 1px solid #000000; padding: 3px; mso-number-format:"\@"; }
        .num { mso-number-format:"0"; text-align: right; }
        .num2 { mso-number-format:"0\.00"; text-align: right; }
        .title { font-size: 16px; font-weight: bold; border: none; }
        .info { border: none; }
        .supplier { background: #eeeeee; font-weight: bold; }
        .total { background: #f8f8f8; font-weight: bold; }
        .red { color: #ff0000; font-weight: bold; }
        .blue { color: #0000ff; font-weight: bold; }
    </style>
</head>
<body>
<table>
    <tr><td colspan="27" class="title">PT.IMC TEKNO INDONESIA</td></tr>
    <tr><td colspan="27" class="title" style="text-align:center;">MOR PLANING</td></tr>
    <tr><td colspan="27" class="info">Month : <?php echo h($monthLong); ?> | Export Date : <?php echo h($printDate); ?></td></tr>
    <tr><td colspan="27" class="info"></td></tr>

    <tr>
        <th rowspan="3">NO</th>
        <th rowspan="3">CODE</th>
        <th rowspan="3">Material Name</th>
        <th rowspan="3">Safety</th>
        <th colspan="3">TAG</th>
        <th colspan="8"><?php echo h($m1); ?></th>
        <th colspan="6"><?php echo h($m2); ?></th>
        <th colspan="5"><?php echo h($m3); ?></th>
        <th rowspan="3">Supplier</th>
    </tr>
    <tr>
        <th>IN</th>
        <th>EXT</th>
        <th>TOTAL</th>
        <th>PO<br>Supplier</th>
        <th>USE 1</th>
        <th>Demand</th>
        <th>Purchase</th>
        <th>Purchase<br>Adjust</th>
        <th>REC</th>
        <th>ADD</th>
        <th>Endst1</th>
        <th>Out<br>Standing</th>
        <th>USE 2</th>
        <th>Demand</th>
        <th>Purchase2</th>
        <th>Purchase2<br>Adjust</th>
        <th>Endst2</th>
        <th>USE 3</th>
        <th>Demand</th>
        <th>Purchase3</th>
        <th>Purchase3<br>Adjust</th>
        <th>Endst3</th>
    </tr>
    <tr>
        <th></th><th></th><th></th>
        <th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
        <th></th><th></th><th></th><th></th><th></th><th></th>
        <th></th><th></th><th></th><th></th><th></th>
    </tr>

    <?php foreach ($supplierGroups as $g) { ?>
        <tr class="supplier">
            <td colspan="27"><?php echo h($g["SUP_CODE"] . " - " . $g["SUP_COMP"]); ?></td>
        </tr>

        <?php $no = 1; ?>
        <?php foreach ($g["ROWS"] as $r) { ?>
            <tr>
                <td class="num"><?php echo h($no); ?></td>
                <td><?php echo h($r["MAT_CODE"]); ?></td>
                <td><?php echo h($r["MAT_NAME"]); ?></td>
                <td class="num"><?php echo h(excel_num($r["SAFETY_QTY"], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["TAG_INTERNAL"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["TAG_EXTERNAL"], 2)); ?></td>
                <td class="num2 blue"><?php echo h(excel_num($r["TAG_QTY"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["POD_QTY"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["MAT_USE1"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["DEMAND1"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["PURCHASE1"], 2)); ?></td>
                <td class="num2 red"><?php echo h(excel_num($r["PURCHASE_ADJUST1"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["RCVD_QTY"], 2)); ?></td>
                <td class="num2"></td>
                <td class="num2"><?php echo h(excel_num($r["ENDST1"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["OUTSTANDING2"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["MAT_USE2"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["DEMAND2"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["PURCHASE2"], 2)); ?></td>
                <td class="num2 red"><?php echo h(excel_num($r["PURCHASE_ADJUST2"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["ENDST2"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["MAT_USE3"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["DEMAND3"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["PURCHASE3"], 2)); ?></td>
                <td class="num2 red"><?php echo h(excel_num($r["PURCHASE_ADJUST3"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["ENDST3"], 2)); ?></td>
                <td><?php echo h($r["SUP_COMP"]); ?></td>
            </tr>
            <?php $no++; ?>
        <?php } ?>

        <tr class="total">
            <td colspan="4">TOTAL <?php echo h($g["SUP_CODE"]); ?></td>
            <td class="num2"><?php echo h(excel_num($g["SUM_TAG_INTERNAL"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["SUM_TAG_EXTERNAL"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["SUM_TAG_QTY"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["SUM_PO"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["SUM_USE1"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["SUM_DEMAND1"], 2)); ?></td>
            <td></td>
            <td class="num2 red"><?php echo h(excel_num($g["SUM_PURCHASE_ADJUST1"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["SUM_RCVD"], 2)); ?></td>
            <td></td>
            <td></td>
            <td></td>
            <td class="num2"><?php echo h(excel_num($g["SUM_USE2"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["SUM_DEMAND2"], 2)); ?></td>
            <td></td>
            <td class="num2 red"><?php echo h(excel_num($g["SUM_PURCHASE_ADJUST2"], 2)); ?></td>
            <td></td>
            <td class="num2"><?php echo h(excel_num($g["SUM_USE3"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["SUM_DEMAND3"], 2)); ?></td>
            <td></td>
            <td class="num2 red"><?php echo h(excel_num($g["SUM_PURCHASE_ADJUST3"], 2)); ?></td>
            <td></td>
            <td></td>
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
    <title>MOR Planning</title>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #9c9c9c;
            color: #000000;
            font-family: "Times New Roman", serif;
            font-size: 10px;
        }

        .filter {
            width: calc(100% - 20px);
            max-width: 1180px;
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
            max-width: 1180px;
            margin: 0 auto 6px auto;
            text-align: right;
        }

        .page {
            width: calc(100% - 20px);
            max-width: 1180px;
            min-height: 780px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 8px 12px;
            border: 2px solid #000000;
            box-sizing: border-box;
        }

        .topline {
            position: relative;
            min-height: 44px;
            margin-bottom: 4px;
        }

        .company {
            position: absolute;
            left: 0;
            top: 0;
            font-weight: bold;
            font-size: 15px;
        }

        .month {
            position: absolute;
            left: 0;
            top: 22px;
            font-weight: bold;
            font-size: 12px;
        }

        .title {
            text-align: center;
            font-weight: bold;
            font-size: 17px;
            padding-top: 2px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7px;
        }

        table.report th,
        table.report td {
            border: 1px dotted #000000;
            padding: 1px 2px;
            vertical-align: top;
            line-height: 9px;
            overflow: hidden;
        }

        table.report th {
            text-align: center;
            font-weight: bold;
        }

        table.report td.num {
            text-align: right;
            white-space: nowrap;
        }

        .supplier-row td {
            font-weight: bold;
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            background: #ffffff;
        }

        .total-row td {
            font-weight: bold;
            background: #f8f8f8;
        }

        .red {
            color: #ff0000;
            font-weight: bold;
        }

        .blue {
            color: #0000ff;
            font-weight: bold;
        }

        .no-data {
            padding: 60px 0;
            text-align: center;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 14px;
        }

        @page {
            size: A4 landscape;
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
            }

            table.report {
                font-size: 6px;
            }

            table.report th,
            table.report td {
                padding: 1px;
                line-height: 8px;
            }
        }
    </style>
</head>
<body>

<div class="filter">
    <form method="get">
        <b>MOR Planning</b>
        &nbsp;&nbsp;
        Month:
        <input type="month" name="month" value="<?php echo h($monthInput); ?>">
        &nbsp;
        Material:
        <input type="text" name="material" value="<?php echo h($materialFilter); ?>" placeholder="Code / material name" style="width:180px;">
        &nbsp;
        Supplier:
        <input type="text" name="supplier" value="<?php echo h($supplierFilter); ?>" placeholder="Supplier" style="width:150px;">
        &nbsp;
        <button type="submit" class="btn">FILTER</button>
        <a href="mor.php" class="btn">CURRENT</a>
        <button type="submit" name="export" value="excel" class="btn">EXPORT EXCEL</button>
    </form>
</div>

<div class="toolbar">
    <button type="button" class="btn" onclick="window.print()">PRINT</button>
    <button type="button" class="btn" onclick="window.location.href='dashboard_ppic.php'">CLOSE</button>
</div>

<div class="page">
    <div class="topline">
        <div class="company">PT.IMC TEKNO INDONESIA</div>
        <div class="month"><?php echo h($monthLong); ?></div>
        <div class="title">MOR PLANING</div>
    </div>

    <?php if ($totalRows == 0) { ?>
        <div class="no-data">Data MOR tidak ditemukan.</div>
    <?php } else { ?>
        <table class="report">
            <colgroup>
                <col style="width:2%;">
                <col style="width:5%;">
                <col style="width:14%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="3">NO</th>
                    <th rowspan="3">CODE</th>
                    <th rowspan="3">Material Name</th>
                    <th rowspan="3">Safety</th>
                    <th colspan="3">TAG</th>
                    <th colspan="8"><?php echo h($m1); ?></th>
                    <th colspan="6"><?php echo h($m2); ?></th>
                    <th colspan="5"><?php echo h($m3); ?></th>
                </tr>
                <tr>
                    <th>IN</th>
                    <th>EXT</th>
                    <th>TOTAL</th>
                    <th>PO<br>Supplier</th>
                    <th>USE 1</th>
                    <th>Demand</th>
                    <th>Purchase</th>
                    <th>Purchase1<br>Adjust</th>
                    <th>REC</th>
                    <th>ADD</th>
                    <th>Endst1</th>
                    <th>Out<br>Standing</th>
                    <th>USE 2</th>
                    <th>Demand</th>
                    <th>Purchase2</th>
                    <th>Purchase2<br>Adjust</th>
                    <th>Endst2</th>
                    <th>USE 3</th>
                    <th>Demand</th>
                    <th>Purchase3</th>
                    <th>Purchase3<br>Adjust</th>
                    <th>Endst3</th>
                </tr>
                <tr>
                    <th></th><th></th><th></th>
                    <th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>
                    <th></th><th></th><th></th><th></th><th></th><th></th>
                    <th></th><th></th><th></th><th></th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($supplierGroups as $g) { ?>
                    <tr class="supplier-row">
                        <td colspan="26"><?php echo h($g["SUP_CODE"] . " - " . $g["SUP_COMP"]); ?></td>
                    </tr>

                    <?php $no = 1; ?>
                    <?php foreach ($g["ROWS"] as $r) { ?>
                        <tr>
                            <td class="num"><?php echo h($no); ?></td>
                            <td><?php echo h($r["MAT_CODE"]); ?></td>
                            <td><?php echo h($r["MAT_NAME"]); ?></td>
                            <td class="num"><?php echo h(n0($r["SAFETY_QTY"])); ?></td>
                            <td class="num"><?php echo h(n2($r["TAG_INTERNAL"])); ?></td>
                            <td class="num"><?php echo h(n2($r["TAG_EXTERNAL"])); ?></td>
                            <td class="num blue"><?php echo h(n2($r["TAG_QTY"])); ?></td>
                            <td class="num"><?php echo h(n2($r["POD_QTY"])); ?></td>
                            <td class="num"><?php echo h(n2($r["MAT_USE1"])); ?></td>
                            <td class="num"><?php echo h(n2($r["DEMAND1"])); ?></td>
                            <td class="num"><?php echo h(n2($r["PURCHASE1"])); ?></td>
                            <td class="num red"><?php echo h(n2($r["PURCHASE_ADJUST1"])); ?></td>
                            <td class="num"><?php echo h(n2($r["RCVD_QTY"])); ?></td>
                            <td class="num"></td>
                            <td class="num"><?php echo h(n2($r["ENDST1"])); ?></td>
                            <td class="num"><?php echo h(n2($r["OUTSTANDING2"])); ?></td>
                            <td class="num"><?php echo h(n2($r["MAT_USE2"])); ?></td>
                            <td class="num"><?php echo h(n2($r["DEMAND2"])); ?></td>
                            <td class="num"><?php echo h(n2($r["PURCHASE2"])); ?></td>
                            <td class="num red"><?php echo h(n2($r["PURCHASE_ADJUST2"])); ?></td>
                            <td class="num"><?php echo h(n2($r["ENDST2"])); ?></td>
                            <td class="num"><?php echo h(n2($r["MAT_USE3"])); ?></td>
                            <td class="num"><?php echo h(n2($r["DEMAND3"])); ?></td>
                            <td class="num"><?php echo h(n2($r["PURCHASE3"])); ?></td>
                            <td class="num red"><?php echo h(n2($r["PURCHASE_ADJUST3"])); ?></td>
                            <td class="num"><?php echo h(n2($r["ENDST3"])); ?></td>
                        </tr>
                        <?php $no++; ?>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</div>

</body>
</html>
