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
    return date("M-y", strtotime("+" . intval($addMonth) . " month", strtotime($date)));
}

$monthInput = get_value("month", date("Y-m"));
$stationFilter = get_value("station", "");
$processFilter = get_value("process", "");
$export = get_value("export", "");

$startDate = $monthInput . "-01";
$ts = strtotime($startDate);

if ($ts === false) {
    $monthInput = date("Y-m");
    $startDate = date("Y-m-01");
}

$m1 = month_title($startDate, 0);
$m2 = month_title($startDate, 1);
$m3 = month_title($startDate, 2);
$printDate = date("d-M-Y H:i:s");

/* ======================================================
   LOAD DATA DARI SP MPS
====================================================== */
$sql = "EXEC dbo.SP_MPS3MONTH_EX ?";
$stmt = sqlsrv_query($conn, $sql, array($startDate));

if ($stmt === false) {
    die("<pre>Query SP_MPS3MONTH_EX error:\n" . sql_error_text() . "</pre>");
}

$groups = array();
$rowsFlat = array();
$totalRow = 0;
$totalCap1 = 0;
$totalCap2 = 0;
$totalCap3 = 0;
$totalPlan1 = 0;
$totalPlan2 = 0;
$totalPlan3 = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $process = isset($r["PROCESS"]) ? trim((string)$r["PROCESS"]) : "";
    $station = isset($r["ST"]) ? trim((string)$r["ST"]) : "";

    if ($process == "") {
        $process = "*NONE*";
    }

    if ($station == "") {
        $station = "*NONE*";
    }

    if ($stationFilter != "" && stripos($station, $stationFilter) === false) {
        continue;
    }

    if ($processFilter != "" && stripos($process, $processFilter) === false) {
        continue;
    }

    $itemCode = isset($r["ITEM_CODE"]) ? trim((string)$r["ITEM_CODE"]) : "";
    $itemName = isset($r["ITEM_NAME"]) ? trim((string)$r["ITEM_NAME"]) : "";
    $custAbbr = isset($r["CUST_ABBR"]) ? trim((string)$r["CUST_ABBR"]) : "";

    $cavt = isset($r["ITEM_CAVT"]) ? floatval($r["ITEM_CAVT"]) : 0;
    $cytm = isset($r["ITEM_CYTM"]) ? floatval($r["ITEM_CYTM"]) : 0;
    $eff = isset($r["PROC_EFFICIENTCY"]) ? floatval($r["PROC_EFFICIENTCY"]) : 0;
    $hours = isset($r["PROC_HOURS"]) ? floatval($r["PROC_HOURS"]) : 0;
    $measure = isset($r["PROC_MEASURE"]) ? floatval($r["PROC_MEASURE"]) : 0;
    $mmdayStd = isset($r["PROC_MMDAY"]) ? floatval($r["PROC_MMDAY"]) : 0;

    $macQty = isset($r["QTY"]) ? floatval($r["QTY"]) : 0;
    $mmday1 = isset($r["MMDAY1"]) ? floatval($r["MMDAY1"]) : 0;
    $mmday2 = isset($r["MMDAY2"]) ? floatval($r["MMDAY2"]) : 0;
    $mmday3 = isset($r["MMDAY3"]) ? floatval($r["MMDAY3"]) : 0;

    $capd = isset($r["CAPD"]) ? floatval($r["CAPD"]) : 0;

    /*
        CAPD dari SP:
        (3600 / ITEM_CYTM * ITEM_CAVT * PROC_HOURS) * (PROC_EFFICIENTCY / 100)

        Capacity month:
        CAPD x MMDAY x Machine QTY
    */
    $cap1 = $capd * $mmday1 * $macQty;
    $cap2 = $capd * $mmday2 * $macQty;
    $cap3 = $capd * $mmday3 * $macQty;

    $plan1 = isset($r["PP1"]) ? floatval($r["PP1"]) : 0;
    $plan2 = isset($r["PP2"]) ? floatval($r["PP2"]) : 0;
    $plan3 = isset($r["PP3"]) ? floatval($r["PP3"]) : 0;

    $bal1 = $cap1 - $plan1;
    $bal2 = $cap2 - $plan2;
    $bal3 = $cap3 - $plan3;

    $load1 = ($cap1 > 0) ? ($plan1 / $cap1 * 100) : 0;
    $load2 = ($cap2 > 0) ? ($plan2 / $cap2 * 100) : 0;
    $load3 = ($cap3 > 0) ? ($plan3 / $cap3 * 100) : 0;

    $groupKey = $process . "|" . $station;

    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = array(
            "PROCESS" => $process,
            "ST" => $station,
            "ROWS" => array(),
            "CAP1" => 0,
            "CAP2" => 0,
            "CAP3" => 0,
            "PLAN1" => 0,
            "PLAN2" => 0,
            "PLAN3" => 0,
            "MAC_QTY" => $macQty,
            "MMDAY1" => $mmday1,
            "MMDAY2" => $mmday2,
            "MMDAY3" => $mmday3
        );
    }

    $row = array(
        "PROCESS" => $process,
        "ST" => $station,
        "ITEM_CODE" => $itemCode,
        "ITEM_NAME" => $itemName,
        "CUST_ABBR" => $custAbbr,
        "CAVT" => $cavt,
        "CYTM" => $cytm,
        "EFF" => $eff,
        "HOURS" => $hours,
        "MEASURE" => $measure,
        "MMDAY_STD" => $mmdayStd,
        "MAC_QTY" => $macQty,
        "MMDAY1" => $mmday1,
        "MMDAY2" => $mmday2,
        "MMDAY3" => $mmday3,
        "CAPD" => $capd,
        "CAP1" => $cap1,
        "CAP2" => $cap2,
        "CAP3" => $cap3,
        "PLAN1" => $plan1,
        "PLAN2" => $plan2,
        "PLAN3" => $plan3,
        "BAL1" => $bal1,
        "BAL2" => $bal2,
        "BAL3" => $bal3,
        "LOAD1" => $load1,
        "LOAD2" => $load2,
        "LOAD3" => $load3
    );

    $groups[$groupKey]["ROWS"][] = $row;
    $groups[$groupKey]["CAP1"] += $cap1;
    $groups[$groupKey]["CAP2"] += $cap2;
    $groups[$groupKey]["CAP3"] += $cap3;
    $groups[$groupKey]["PLAN1"] += $plan1;
    $groups[$groupKey]["PLAN2"] += $plan2;
    $groups[$groupKey]["PLAN3"] += $plan3;

    if ($groups[$groupKey]["MAC_QTY"] <= 0 && $macQty > 0) {
        $groups[$groupKey]["MAC_QTY"] = $macQty;
    }
    if ($groups[$groupKey]["MMDAY1"] <= 0 && $mmday1 > 0) {
        $groups[$groupKey]["MMDAY1"] = $mmday1;
    }
    if ($groups[$groupKey]["MMDAY2"] <= 0 && $mmday2 > 0) {
        $groups[$groupKey]["MMDAY2"] = $mmday2;
    }
    if ($groups[$groupKey]["MMDAY3"] <= 0 && $mmday3 > 0) {
        $groups[$groupKey]["MMDAY3"] = $mmday3;
    }

    $rowsFlat[] = $row;

    $totalCap1 += $cap1;
    $totalCap2 += $cap2;
    $totalCap3 += $cap3;
    $totalPlan1 += $plan1;
    $totalPlan2 += $plan2;
    $totalPlan3 += $plan3;

    $totalRow++;
}

$totalBal1 = $totalCap1 - $totalPlan1;
$totalBal2 = $totalCap2 - $totalPlan2;
$totalBal3 = $totalCap3 - $totalPlan3;
$totalLoad1 = ($totalCap1 > 0) ? ($totalPlan1 / $totalCap1 * 100) : 0;
$totalLoad2 = ($totalCap2 > 0) ? ($totalPlan2 / $totalCap2 * 100) : 0;
$totalLoad3 = ($totalCap3 > 0) ? ($totalPlan3 / $totalCap3 * 100) : 0;

/* ======================================================
   EXPORT EXCEL
====================================================== */
if ($export == "excel") {
    $fileName = "MACHINE_CAPACITY_" . date("Ym", strtotime($startDate)) . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";
    ?>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            table { border-collapse: collapse; font-family: Calibri, Arial, sans-serif; font-size: 11px; }
            th { background: #d9eaf7; font-weight: bold; text-align: center; border: 1px solid #777777; }
            td { border: 1px solid #999999; mso-number-format:"\@"; }
            .num { mso-number-format:"0"; text-align: right; }
            .num2 { mso-number-format:"0\.00"; text-align: right; }
            .title { font-size: 16px; font-weight: bold; text-align: center; }
            .summary { background: #f4f4f4; font-weight: bold; }
        </style>
    </head>
    <body>
        <table>
            <tr>
                <td colspan="27" class="title">MACHINE CAPACITY REPORT</td>
            </tr>
            <tr>
                <td colspan="27">Start Month: <?php echo h(date("F Y", strtotime($startDate))); ?> | Print Date: <?php echo h($printDate); ?></td>
            </tr>
            <tr><td colspan="27"></td></tr>
            <tr>
                <th rowspan="2">PROCESS</th>
                <th rowspan="2">STATION</th>
                <th rowspan="2">PART</th>
                <th rowspan="2">PART NAME</th>
                <th rowspan="2">CUST</th>
                <th rowspan="2">CAVT</th>
                <th rowspan="2">CYTM</th>
                <th rowspan="2">EFF %</th>
                <th rowspan="2">HOURS</th>
                <th rowspan="2">MC QTY</th>
                <th rowspan="2">CAP / DAY</th>
                <th colspan="5"><?php echo h($m1); ?></th>
                <th colspan="5"><?php echo h($m2); ?></th>
                <th colspan="5"><?php echo h($m3); ?></th>
                <th rowspan="2">REMARK</th>
            </tr>
            <tr>
                <th>MMDAY</th><th>CAPACITY</th><th>PLAN</th><th>BALANCE</th><th>LOAD %</th>
                <th>MMDAY</th><th>CAPACITY</th><th>PLAN</th><th>BALANCE</th><th>LOAD %</th>
                <th>MMDAY</th><th>CAPACITY</th><th>PLAN</th><th>BALANCE</th><th>LOAD %</th>
            </tr>

            <?php foreach ($rowsFlat as $r) { ?>
                <tr>
                    <td><?php echo h($r["PROCESS"]); ?></td>
                    <td><?php echo h($r["ST"]); ?></td>
                    <td><?php echo h($r["ITEM_CODE"]); ?></td>
                    <td><?php echo h($r["ITEM_NAME"]); ?></td>
                    <td><?php echo h($r["CUST_ABBR"]); ?></td>
                    <td class="num"><?php echo h(excel_num($r["CAVT"], 0)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["CYTM"], 2)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["EFF"], 2)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["HOURS"], 2)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["MAC_QTY"], 2)); ?></td>
                    <td class="num"><?php echo h(excel_num($r["CAPD"], 0)); ?></td>

                    <td class="num"><?php echo h(excel_num($r["MMDAY1"], 0)); ?></td>
                    <td class="num"><?php echo h(excel_num($r["CAP1"], 0)); ?></td>
                    <td class="num"><?php echo h(excel_num($r["PLAN1"], 0)); ?></td>
                    <td class="num"><?php echo h(excel_num($r["BAL1"], 0)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["LOAD1"], 2)); ?></td>

                    <td class="num"><?php echo h(excel_num($r["MMDAY2"], 0)); ?></td>
                    <td class="num"><?php echo h(excel_num($r["CAP2"], 0)); ?></td>
                    <td class="num"><?php echo h(excel_num($r["PLAN2"], 0)); ?></td>
                    <td class="num"><?php echo h(excel_num($r["BAL2"], 0)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["LOAD2"], 2)); ?></td>

                    <td class="num"><?php echo h(excel_num($r["MMDAY3"], 0)); ?></td>
                    <td class="num"><?php echo h(excel_num($r["CAP3"], 0)); ?></td>
                    <td class="num"><?php echo h(excel_num($r["PLAN3"], 0)); ?></td>
                    <td class="num"><?php echo h(excel_num($r["BAL3"], 0)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["LOAD3"], 2)); ?></td>
                    <td></td>
                </tr>
            <?php } ?>

            <tr class="summary">
                <td colspan="11">GRAND TOTAL</td>
                <td></td>
                <td class="num"><?php echo h(excel_num($totalCap1, 0)); ?></td>
                <td class="num"><?php echo h(excel_num($totalPlan1, 0)); ?></td>
                <td class="num"><?php echo h(excel_num($totalBal1, 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($totalLoad1, 2)); ?></td>
                <td></td>
                <td class="num"><?php echo h(excel_num($totalCap2, 0)); ?></td>
                <td class="num"><?php echo h(excel_num($totalPlan2, 0)); ?></td>
                <td class="num"><?php echo h(excel_num($totalBal2, 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($totalLoad2, 2)); ?></td>
                <td></td>
                <td class="num"><?php echo h(excel_num($totalCap3, 0)); ?></td>
                <td class="num"><?php echo h(excel_num($totalPlan3, 0)); ?></td>
                <td class="num"><?php echo h(excel_num($totalBal3, 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($totalLoad3, 2)); ?></td>
                <td></td>
            </tr>
        </table>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Machine Capacity Report</title>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #9c9c9c;
            color: #000000;
            font-family: "Courier New", Courier, monospace;
            font-size: 8px;
        }

        .filter {
            width: calc(100% - 24px);
            max-width: 1160px;
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
            width: calc(100% - 24px);
            max-width: 1160px;
            margin: 0 auto 6px auto;
            text-align: right;
        }

        .page {
            width: calc(100% - 24px);
            max-width: 1160px;
            min-height: 760px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 14px;
            border: 1px solid #000000;
            box-sizing: border-box;
            overflow: hidden;
        }

        .header {
            position: relative;
            min-height: 66px;
            border-bottom: 1px solid #000000;
        }

        .company {
            position: absolute;
            left: 0;
            top: 0;
            font-size: 10px;
        }

        .dept {
            position: absolute;
            left: 0;
            top: 14px;
            font-size: 8px;
        }

        .title {
            text-align: center;
            font-size: 16px;
            line-height: 18px;
            padding-top: 10px;
        }

        .subtitle {
            text-align: center;
            font-size: 9px;
        }

        .print-date {
            position: absolute;
            right: 0;
            top: 0;
            font-size: 8px;
            text-align: right;
        }

        .info {
            margin: 6px 0;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 11px;
        }

        table.cap {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 4px;
        }

        table.cap th,
        table.cap td {
            border: 1px solid #999999;
            padding: 2px 2px;
            vertical-align: top;
            font-size: 7px;
            line-height: 9px;
            overflow: hidden;
        }

        table.cap th {
            background: #e8e8e8;
            text-align: center;
            font-weight: bold;
        }

        td.num, th.num {
            text-align: right;
            white-space: nowrap;
        }

        td.center, th.center {
            text-align: center;
        }

        .group-row td {
            background: #d9d9d9;
            font-weight: bold;
            font-size: 8px;
        }

        .summary-row td {
            background: #f1f1f1;
            font-weight: bold;
        }

        .grand-total td {
            background: #cfcfcf;
            font-weight: bold;
        }

        .overload {
            background: #ffe0e0;
        }

        .safe {
            background: #e8ffe8;
        }

        .no-data {
            padding: 60px 0;
            text-align: center;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 13px;
        }

        @page {
            size: A4 landscape;
            margin: 6mm;
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
                padding: 0;
                border: none;
                overflow: visible;
            }

            table.cap th,
            table.cap td {
                font-size: 6.5px;
                line-height: 8px;
                padding: 1px;
            }
        }
    </style>
</head>
<body>

<div class="filter">
    <form method="get">
        <b>Machine Capacity Report</b>
        &nbsp;&nbsp;
        Start Month:
        <input type="month" name="month" value="<?php echo h($monthInput); ?>">
        &nbsp;
        Process:
        <input type="text" name="process" value="<?php echo h($processFilter); ?>" placeholder="Optional" style="width:130px;">
        &nbsp;
        Station:
        <input type="text" name="station" value="<?php echo h($stationFilter); ?>" placeholder="Optional" style="width:110px;">
        &nbsp;
        <button type="submit" class="btn">FILTER</button>
        <a href="mps_capacity.php" class="btn">CURRENT</a>
        <a href="mps_capacity.php?month=<?php echo h($monthInput); ?>&process=<?php echo h(urlencode($processFilter)); ?>&station=<?php echo h(urlencode($stationFilter)); ?>&export=excel" class="btn">EXPORT EXCEL</a>
    </form>
</div>

<div class="toolbar">
    <button type="button" class="btn" onclick="window.print()">PRINT</button>
    <button type="button" class="btn" onclick="window.location.href='dashboard_ppic.php'">CLOSE</button>
</div>

<div class="page">
    <div class="header">
        <div class="company">P.T. IMC TEKNO INDONESIA</div>
        <div class="dept">PPIC Department</div>
        <div class="title">Machine Capacity Report</div>
        <div class="subtitle">CAPD x Working Day x Machine Qty</div>
        <div class="print-date">
            Start Month: <?php echo h(date("F Y", strtotime($startDate))); ?><br>
            Print Date : <?php echo h($printDate); ?>
        </div>
    </div>

    <div class="info">
        Rumus: CAP/DAY = (3600 / CYTM x CAVT x HOURS) x EFF% &nbsp; | &nbsp;
        Capacity Month = CAP/DAY x MMDAY x Machine Qty &nbsp; | &nbsp;
        Load % = Production Plan / Capacity Month x 100
    </div>

    <?php if ($totalRow == 0) { ?>
        <div class="no-data">Data capacity tidak ditemukan.</div>
    <?php } else { ?>
        <table class="cap">
            <colgroup>
                <col style="width:4%;">
                <col style="width:5%;">
                <col style="width:7%;">
                <col style="width:15%;">
                <col style="width:3.5%;">
                <col style="width:3.5%;">
                <col style="width:3.8%;">
                <col style="width:3.8%;">
                <col style="width:3.8%;">
                <col style="width:4%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2">ST</th>
                    <th rowspan="2">PART</th>
                    <th rowspan="2">CUST</th>
                    <th rowspan="2">PART NAME</th>
                    <th rowspan="2">CAVT</th>
                    <th rowspan="2">CYTM</th>
                    <th rowspan="2">EFF</th>
                    <th rowspan="2">HOURS</th>
                    <th rowspan="2">MC QTY</th>
                    <th rowspan="2">CAP/DAY</th>
                    <th colspan="5"><?php echo h($m1); ?></th>
                    <th colspan="5"><?php echo h($m2); ?></th>
                    <th colspan="5"><?php echo h($m3); ?></th>
                </tr>
                <tr>
                    <th>DAY</th><th>CAP</th><th>PLAN</th><th>BAL</th><th>LOAD</th>
                    <th>DAY</th><th>CAP</th><th>PLAN</th><th>BAL</th><th>LOAD</th>
                    <th>DAY</th><th>CAP</th><th>PLAN</th><th>BAL</th><th>LOAD</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $groupKey => $g) { ?>
                    <tr class="group-row">
                        <td colspan="25">
                            PROCESS: <?php echo h($g["PROCESS"]); ?> &nbsp;&nbsp;
                            STATION: <?php echo h($g["ST"]); ?>
                        </td>
                    </tr>

                    <?php for ($i = 0; $i < count($g["ROWS"]); $i++) { ?>
                        <?php
                        $r = $g["ROWS"][$i];
                        $loadClass1 = ($r["LOAD1"] > 100) ? "overload" : "safe";
                        $loadClass2 = ($r["LOAD2"] > 100) ? "overload" : "safe";
                        $loadClass3 = ($r["LOAD3"] > 100) ? "overload" : "safe";
                        ?>
                        <tr>
                            <td><?php echo h($r["ST"]); ?></td>
                            <td><?php echo h($r["ITEM_CODE"]); ?></td>
                            <td><?php echo h($r["CUST_ABBR"]); ?></td>
                            <td><?php echo h($r["ITEM_NAME"]); ?></td>
                            <td class="num"><?php echo h(n0($r["CAVT"])); ?></td>
                            <td class="num"><?php echo h(n2($r["CYTM"])); ?></td>
                            <td class="num"><?php echo h(n2($r["EFF"])); ?></td>
                            <td class="num"><?php echo h(n2($r["HOURS"])); ?></td>
                            <td class="num"><?php echo h(n2($r["MAC_QTY"])); ?></td>
                            <td class="num"><?php echo h(n0($r["CAPD"])); ?></td>

                            <td class="num"><?php echo h(n0($r["MMDAY1"])); ?></td>
                            <td class="num"><?php echo h(n0($r["CAP1"])); ?></td>
                            <td class="num"><?php echo h(n0($r["PLAN1"])); ?></td>
                            <td class="num"><?php echo h(n0($r["BAL1"])); ?></td>
                            <td class="num <?php echo h($loadClass1); ?>"><?php echo h(n2($r["LOAD1"])); ?>%</td>

                            <td class="num"><?php echo h(n0($r["MMDAY2"])); ?></td>
                            <td class="num"><?php echo h(n0($r["CAP2"])); ?></td>
                            <td class="num"><?php echo h(n0($r["PLAN2"])); ?></td>
                            <td class="num"><?php echo h(n0($r["BAL2"])); ?></td>
                            <td class="num <?php echo h($loadClass2); ?>"><?php echo h(n2($r["LOAD2"])); ?>%</td>

                            <td class="num"><?php echo h(n0($r["MMDAY3"])); ?></td>
                            <td class="num"><?php echo h(n0($r["CAP3"])); ?></td>
                            <td class="num"><?php echo h(n0($r["PLAN3"])); ?></td>
                            <td class="num"><?php echo h(n0($r["BAL3"])); ?></td>
                            <td class="num <?php echo h($loadClass3); ?>"><?php echo h(n2($r["LOAD3"])); ?>%</td>
                        </tr>
                    <?php } ?>

                    <?php
                    $gBal1 = $g["CAP1"] - $g["PLAN1"];
                    $gBal2 = $g["CAP2"] - $g["PLAN2"];
                    $gBal3 = $g["CAP3"] - $g["PLAN3"];
                    $gLoad1 = ($g["CAP1"] > 0) ? ($g["PLAN1"] / $g["CAP1"] * 100) : 0;
                    $gLoad2 = ($g["CAP2"] > 0) ? ($g["PLAN2"] / $g["CAP2"] * 100) : 0;
                    $gLoad3 = ($g["CAP3"] > 0) ? ($g["PLAN3"] / $g["CAP3"] * 100) : 0;
                    ?>
                    <tr class="summary-row">
                        <td colspan="10">TOTAL <?php echo h($g["ST"]); ?></td>
                        <td></td>
                        <td class="num"><?php echo h(n0($g["CAP1"])); ?></td>
                        <td class="num"><?php echo h(n0($g["PLAN1"])); ?></td>
                        <td class="num"><?php echo h(n0($gBal1)); ?></td>
                        <td class="num"><?php echo h(n2($gLoad1)); ?>%</td>
                        <td></td>
                        <td class="num"><?php echo h(n0($g["CAP2"])); ?></td>
                        <td class="num"><?php echo h(n0($g["PLAN2"])); ?></td>
                        <td class="num"><?php echo h(n0($gBal2)); ?></td>
                        <td class="num"><?php echo h(n2($gLoad2)); ?>%</td>
                        <td></td>
                        <td class="num"><?php echo h(n0($g["CAP3"])); ?></td>
                        <td class="num"><?php echo h(n0($g["PLAN3"])); ?></td>
                        <td class="num"><?php echo h(n0($gBal3)); ?></td>
                        <td class="num"><?php echo h(n2($gLoad3)); ?>%</td>
                    </tr>
                <?php } ?>

                <tr class="grand-total">
                    <td colspan="10">GRAND TOTAL</td>
                    <td></td>
                    <td class="num"><?php echo h(n0($totalCap1)); ?></td>
                    <td class="num"><?php echo h(n0($totalPlan1)); ?></td>
                    <td class="num"><?php echo h(n0($totalBal1)); ?></td>
                    <td class="num"><?php echo h(n2($totalLoad1)); ?>%</td>
                    <td></td>
                    <td class="num"><?php echo h(n0($totalCap2)); ?></td>
                    <td class="num"><?php echo h(n0($totalPlan2)); ?></td>
                    <td class="num"><?php echo h(n0($totalBal2)); ?></td>
                    <td class="num"><?php echo h(n2($totalLoad2)); ?>%</td>
                    <td></td>
                    <td class="num"><?php echo h(n0($totalCap3)); ?></td>
                    <td class="num"><?php echo h(n0($totalPlan3)); ?></td>
                    <td class="num"><?php echo h(n0($totalBal3)); ?></td>
                    <td class="num"><?php echo h(n2($totalLoad3)); ?>%</td>
                </tr>
            </tbody>
        </table>
    <?php } ?>
</div>

</body>
</html>
