<?php
if (session_id() == "") {
    session_start();
}

ob_start();

$selfFile = basename($_SERVER["PHP_SELF"]);

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

function to_float($value, $default) {
    $value = trim((string)$value);
    if ($value == "") {
        return $default;
    }
    return floatval(str_replace(",", "", $value));
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
        return "0.00";
    }

    return number_format($n, 2, ".", ",");
}

function n2z($value) {
    $n = floatval($value);
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

function machine_no_sort_number($value) {
    $value = (string)$value;

    if (preg_match('/[0-9]+/', $value, $m)) {
        return intval($m[0]);
    }

    return 999999;
}

function cmp_machine_group($a, $b) {
    $na = machine_no_sort_number($a);
    $nb = machine_no_sort_number($b);

    if ($na == $nb) {
        return strcmp((string)$a, (string)$b);
    }

    return ($na < $nb) ? -1 : 1;
}

$monthInput    = get_value("month", date("Y-m"));
$machineFilter = get_value("machine", "");
$stationFilter = get_value("station", "");
$export        = strtolower(get_value("export", ""));

$workDays  = to_float(get_value("work_days", "25"), 25);
$workHours = to_float(get_value("work_hours", "21"), 21);
$eff       = to_float(get_value("eff", "0.90"), 0.90);

if ($workDays <= 0) {
    $workDays = 25;
}

if ($workHours <= 0) {
    $workHours = 21;
}

if ($eff <= 0) {
    $eff = 0.90;
}

$startDate = $monthInput . "-01";

if (strtotime($startDate) === false) {
    $monthInput = date("Y-m");
    $startDate  = date("Y-m-01");
}

$endDate    = date("Y-m-t", strtotime($startDate));
$monthTitle = date("F Y", strtotime($startDate));
$printDate  = date("d-M-Y H:i:s");

/* ======================================================
   LOAD DATA DARI SP_MC_CAPACITY
====================================================== */
$sql = "EXEC dbo.SP_MC_CAPACITY ?, ?";
$stmt = sqlsrv_query(
    $conn,
    $sql,
    array($startDate, $endDate),
    array("QueryTimeout" => 0)
);

if ($stmt === false) {
    die("<pre>Query SP_MC_CAPACITY error:\n" . sql_error_text() . "</pre>");
}

$groups       = array();
$totalRows    = 0;
$grandUseDays = 0;
$grandUsePct  = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $machineNo = isset($r["MAC_CODE"]) ? trim((string)$r["MAC_CODE"]) : "";
    $tonage    = isset($r["MAG_STATION"]) ? trim((string)$r["MAG_STATION"]) : "";

    if ($machineNo == "") {
        $machineNo = "*NONE*";
    }

    if ($tonage == "") {
        $tonage = "*NONE*";
    }

    if ($machineFilter != "" && stripos($machineNo, $machineFilter) === false) {
        continue;
    }

    if ($stationFilter != "" && stripos($tonage, $stationFilter) === false) {
        continue;
    }

    $woQty = isset($r["WO_QTY"]) ? floatval($r["WO_QTY"]) : 0;
    $cytm  = isset($r["ITEM_CYTM"]) ? floatval($r["ITEM_CYTM"]) : 0;
    $cav   = isset($r["ITEM_CAVT"]) ? floatval($r["ITEM_CAVT"]) : 0;

    /*
        Sama dengan MC Capacity Plan detail:
        Output Hour     = 3600 / CYTM x CAV
        Time Usage Hour = WO_QTY / Output Hour
        MC Usage Days   = Time Usage Hour / Work Hours
        Plan Use %      = MC Usage Days / Work Days x 100
    */
    $outputHour = 0;

    if ($cytm > 0) {
        $outputHour = (3600.0 / $cytm) * $cav;
    }

    $timeUsageHour = ($outputHour > 0) ? ($woQty / $outputHour) : 0;
    $mcUsageDays   = ($workHours > 0) ? ($timeUsageHour / $workHours) : 0;
    $capacityUsage = ($workDays > 0) ? ($mcUsageDays / $workDays * 100) : 0;

    $groupKey = $machineNo;

    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = array(
            "MACHINE_NO"   => $machineNo,
            "TONAGE"       => $tonage,
            "NUM_OF_DAY"   => $workDays,
            "PLAN_USE_DAY" => 0,
            "PLAN_USE_PCT" => 0,
            "BALANCE_DAY"  => 0,
            "BALANCE_PCT"  => 0,
            "REMARK"       => ""
        );
    }

    if ($groups[$groupKey]["TONAGE"] == "*NONE*" && $tonage != "*NONE*") {
        $groups[$groupKey]["TONAGE"] = $tonage;
    }

    $groups[$groupKey]["PLAN_USE_DAY"] += $mcUsageDays;
    $groups[$groupKey]["PLAN_USE_PCT"] += $capacityUsage;

    $totalRows++;
}

uksort($groups, "cmp_machine_group");

foreach ($groups as $key => $g) {
    $groups[$key]["BALANCE_DAY"] = $g["NUM_OF_DAY"] - $g["PLAN_USE_DAY"];
    $groups[$key]["BALANCE_PCT"] = 100 - $g["PLAN_USE_PCT"];

    if ($groups[$key]["BALANCE_DAY"] < 0) {
        $groups[$key]["REMARK"] = "OVER CAPACITY";
    }

    $grandUseDays += $groups[$key]["PLAN_USE_DAY"];
    $grandUsePct  += $groups[$key]["PLAN_USE_PCT"];
}

/* ======================================================
   EXPORT EXCEL
====================================================== */
if ($export == "excel") {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (headers_sent($file, $line)) {
        die("Header sudah terkirim di file: " . $file . " line: " . $line);
    }

    $fileName = "MC_CAPACITY_SUMMARY_" . date("Ym", strtotime($startDate)) . "_" . date("Ymd_His") . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
    header("Content-Transfer-Encoding: binary");
    header("Cache-Control: max-age=0");
    header("Cache-Control: must-revalidate");
    header("Pragma: public");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table {
            border-collapse: collapse;
            font-family: Calibri, Arial, sans-serif;
            font-size: 11px;
        }

        th {
            background: #d9eaf7;
            font-weight: bold;
            text-align: center;
            border: 1px solid #000000;
            mso-number-format:"\@";
        }

        td {
            border: 1px solid #000000;
            padding: 3px;
            mso-number-format:"\@";
        }

        .num {
            mso-number-format:"0";
            text-align: right;
        }

        .num2 {
            mso-number-format:"0\.00";
            text-align: right;
        }

        .title {
            font-size: 16px;
            font-weight: bold;
            border: none;
        }

        .info {
            border: none;
        }

        .plan {
            background: #2f65d9;
            color: #ffffff;
            text-align: center;
            font-weight: bold;
        }

        .balance {
            background: #d9d9d9;
            text-align: center;
            font-weight: bold;
        }

        .over {
            background: #cc0000;
            color: #ffffff;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>
<table>
    <tr>
        <td colspan="9" class="title">PT.IMC TEKNO INDONESIA</td>
    </tr>
    <tr>
        <td colspan="9" class="title">MC CAPACITY</td>
    </tr>
    <tr>
        <td colspan="9" class="info">
            Month : <?php echo h($monthTitle); ?> |
            Work Days : <?php echo h(n2z($workDays)); ?> |
            Work Hours : <?php echo h(n2z($workHours)); ?> |
            Eff : <?php echo h(n2z($eff)); ?> |
            Export Date : <?php echo h($printDate); ?>
        </td>
    </tr>
    <tr>
        <td colspan="9" class="info"></td>
    </tr>
    <tr>
        <th>NO</th>
        <th>MACHINE</th>
        <th>TONAGE</th>
        <th>NUM OF DAY</th>
        <th colspan="2">PLAN USE DAY / %</th>
        <th colspan="2">BALANCE DAY / %</th>
        <th>REMARK</th>
    </tr>

    <?php $no = 1; ?>
    <?php foreach ($groups as $g) { ?>
        <tr>
            <td class="num"><?php echo h($no); ?></td>
            <td><?php echo h($g["MACHINE_NO"]); ?></td>
            <td><?php echo h($g["TONAGE"]); ?></td>
            <td class="num"><?php echo h(excel_num($g["NUM_OF_DAY"], 0)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["PLAN_USE_DAY"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["PLAN_USE_PCT"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["BALANCE_DAY"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["BALANCE_PCT"], 2)); ?></td>
            <td><?php echo h($g["REMARK"]); ?></td>
        </tr>
        <?php $no++; ?>
    <?php } ?>

    <tr>
        <td colspan="9" class="info"></td>
    </tr>
    <tr>
        <td colspan="9" class="title">GRAPH MC CAPACITY SUMMARY</td>
    </tr>
    <tr>
        <th>MACHINE</th>
        <th>TONAGE</th>
        <th colspan="3">PLAN USE %</th>
        <th colspan="3">BALANCE %</th>
        <th>REMARK</th>
    </tr>

    <?php foreach ($groups as $g) { ?>
        <?php
            $planClass = ($g["PLAN_USE_PCT"] > 100) ? "over" : "plan";
        ?>
        <tr>
            <td><?php echo h($g["MACHINE_NO"]); ?></td>
            <td><?php echo h($g["TONAGE"]); ?></td>
            <td colspan="3" class="<?php echo h($planClass); ?>">
                <?php echo h(n2($g["PLAN_USE_PCT"])); ?>%
            </td>
            <td colspan="3" class="balance">
                <?php echo h(n2($g["BALANCE_PCT"])); ?>%
            </td>
            <td><?php echo h($g["REMARK"]); ?></td>
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
    <title>MC Capacity Summary</title>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #9c9c9c;
            color: #000000;
            font-family: "Times New Roman", serif;
            font-size: 12px;
        }

        .filter {
            width: calc(100% - 20px);
            max-width: 1120px;
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
            display: inline-block;
            line-height: 20px;
        }

        .toolbar {
            width: calc(100% - 20px);
            max-width: 1120px;
            margin: 0 auto 6px auto;
            text-align: right;
        }

        .page {
            width: calc(100% - 20px);
            max-width: 1120px;
            min-height: 780px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 28px 34px;
            border: 2px solid #000000;
            box-sizing: border-box;
        }

        .company {
            font-weight: bold;
            font-size: 18px;
            line-height: 22px;
        }

        .title {
            font-weight: bold;
            font-size: 18px;
            line-height: 22px;
        }

        .month {
            font-weight: bold;
            font-size: 13px;
            line-height: 18px;
        }

        table.report {
            width: 72%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 14px;
            font-size: 12px;
        }

        table.report th,
        table.report td {
            border: 1px solid #000000;
            padding: 4px 5px;
            vertical-align: middle;
            line-height: 15px;
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

        table.report td.center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .chart-box {
            margin-top: 24px;
            width: 72%;
            border: 1px solid #000000;
            padding: 10px;
            box-sizing: border-box;
            page-break-inside: avoid;
        }

        .chart-title {
            font-family: Tahoma, Arial, sans-serif;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 10px;
            text-align: center;
        }

        .chart-row {
            display: flex;
            align-items: center;
            margin-bottom: 7px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 11px;
        }

        .chart-label {
            width: 55px;
            font-weight: bold;
        }

        .chart-tonage {
            width: 72px;
            font-weight: bold;
            text-align: center;
        }

        .chart-track {
            flex: 1;
            height: 18px;
            border: 1px solid #000000;
            display: flex;
            background: #ffffff;
        }

        .chart-plan {
            height: 18px;
            background: #2f65d9;
        }

        .chart-balance {
            height: 18px;
            background: #d9d9d9;
        }

        .chart-over {
            height: 18px;
            background: #cc0000;
        }

        .chart-value {
            width: 135px;
            text-align: right;
            padding-left: 8px;
            box-sizing: border-box;
        }

        .chart-legend {
            margin-top: 8px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 11px;
        }

        .legend-plan {
            display: inline-block;
            width: 14px;
            height: 10px;
            background: #2f65d9;
            border: 1px solid #000000;
        }

        .legend-balance {
            display: inline-block;
            width: 14px;
            height: 10px;
            background: #d9d9d9;
            border: 1px solid #000000;
            margin-left: 18px;
        }

        .legend-over {
            display: inline-block;
            width: 14px;
            height: 10px;
            background: #cc0000;
            border: 1px solid #000000;
            margin-left: 18px;
        }

        .no-data {
            padding: 60px 0;
            text-align: center;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 14px;
        }

        @page {
            size: A4 landscape;
            margin: 7mm;
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
                width: 72%;
            }

            .chart-box {
                width: 72%;
            }
        }
    </style>
</head>
<body>

<div class="filter">
    <form method="get">
        <b>MC Capacity Summary</b>
        &nbsp;&nbsp;

        Month:
        <input type="month" name="month" value="<?php echo h($monthInput); ?>">

        &nbsp;
        Work Days:
        <input type="text" name="work_days" value="<?php echo h(n2z($workDays)); ?>" style="width:55px;">

        &nbsp;
        Work Hours:
        <input type="text" name="work_hours" value="<?php echo h(n2z($workHours)); ?>" style="width:55px;">

        &nbsp;
        Eff:
        <input type="text" name="eff" value="<?php echo h(n2z($eff)); ?>" style="width:55px;">

        &nbsp;
        Machine:
        <input type="text" name="machine" value="<?php echo h($machineFilter); ?>" placeholder="201" style="width:90px;">

        &nbsp;
        Tonage:
        <input type="text" name="station" value="<?php echo h($stationFilter); ?>" placeholder="20080T" style="width:100px;">

        &nbsp;
        <button type="submit" class="btn">FILTER</button>
    </form>
</div>

<div class="toolbar">
    <a class="btn"
       href="<?php echo h($selfFile); ?>?month=<?php echo h($monthInput); ?>&work_days=<?php echo h(n2z($workDays)); ?>&work_hours=<?php echo h(n2z($workHours)); ?>&eff=<?php echo h(n2z($eff)); ?>&machine=<?php echo h(urlencode($machineFilter)); ?>&station=<?php echo h(urlencode($stationFilter)); ?>&export=excel">
       EXPORT EXCEL
    </a>

    <a class="btn"
       href="<?php echo h($selfFile); ?>?month=<?php echo h($monthInput); ?>&work_days=<?php echo h(n2z($workDays)); ?>&work_hours=<?php echo h(n2z($workHours)); ?>&eff=<?php echo h(n2z($eff)); ?>">
       ALL
    </a>

    <a class="btn"
       href="mc_capacity_plan.php?month=<?php echo h($monthInput); ?>&work_days=<?php echo h(n2z($workDays)); ?>&work_hours=<?php echo h(n2z($workHours)); ?>&eff=<?php echo h(n2z($eff)); ?>&machine=<?php echo h(urlencode($machineFilter)); ?>&station=<?php echo h(urlencode($stationFilter)); ?>">
       DETAIL
    </a>

    <button type="button" class="btn" onclick="window.print()">PRINT</button>
    <button type="button" class="btn" onclick="window.location.href='mc_capacity.php'">CLOSE</button>
</div>

<div class="page">
    <div class="company">PT.IMC TEKNO INDONESIA</div>
    <div class="title">MC CAPACITY</div>
    <div class="month">Month : <?php echo h($monthTitle); ?></div>

    <?php if ($totalRows == 0) { ?>
        <div class="no-data">Data MC capacity tidak ditemukan.</div>
    <?php } else { ?>

        <table class="report">
            <colgroup>
                <col style="width:5%;">
                <col style="width:13%;">
                <col style="width:15%;">
                <col style="width:18%;">
                <col style="width:13%;">
                <col style="width:8%;">
                <col style="width:13%;">
                <col style="width:8%;">
                <col style="width:28%;">
            </colgroup>
            <thead>
                <tr>
                    <th>NO</th>
                    <th>MACHINE</th>
                    <th>TONAGE</th>
                    <th>NUM OF DAY</th>
                    <th colspan="2">PLAN USE<br>DAY / %</th>
                    <th colspan="2">BALANCE<br>DAY / %</th>
                    <th>REMARK</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; ?>
                <?php foreach ($groups as $g) { ?>
                    <tr>
                        <td class="center"><?php echo h($no); ?></td>
                        <td class="center bold"><?php echo h($g["MACHINE_NO"]); ?></td>
                        <td class="center bold"><?php echo h($g["TONAGE"]); ?></td>
                        <td class="center bold"><?php echo h(n0($g["NUM_OF_DAY"])); ?></td>
                        <td class="num bold"><?php echo h(n2($g["PLAN_USE_DAY"])); ?></td>
                        <td class="num bold"><?php echo h(n2($g["PLAN_USE_PCT"])); ?></td>
                        <td class="num bold"><?php echo h(n2($g["BALANCE_DAY"])); ?></td>
                        <td class="num bold"><?php echo h(n2($g["BALANCE_PCT"])); ?></td>
                        <td><?php echo h($g["REMARK"]); ?></td>
                    </tr>
                    <?php $no++; ?>
                <?php } ?>
            </tbody>
        </table>

        <div class="chart-box">
            <div class="chart-title">GRAFIK MC CAPACITY SUMMARY</div>

            <?php foreach ($groups as $g) { ?>
                <?php
                    $planPct = floatval($g["PLAN_USE_PCT"]);
                    $balPct  = floatval($g["BALANCE_PCT"]);

                    if ($planPct < 0) {
                        $planPct = 0;
                    }

                    if ($balPct < 0) {
                        $balPct = 0;
                    }

                    $planWidth = ($planPct > 100) ? 100 : $planPct;
                    $balWidth  = ($balPct > 100) ? 100 : $balPct;

                    $planClass = ($g["PLAN_USE_PCT"] > 100) ? "chart-over" : "chart-plan";
                ?>

                <div class="chart-row">
                    <div class="chart-label"><?php echo h($g["MACHINE_NO"]); ?></div>
                    <div class="chart-tonage"><?php echo h($g["TONAGE"]); ?></div>

                    <div class="chart-track">
                        <div class="<?php echo h($planClass); ?>" style="width: <?php echo h($planWidth); ?>%;"></div>
                        <div class="chart-balance" style="width: <?php echo h($balWidth); ?>%;"></div>
                    </div>

                    <div class="chart-value">
                        <?php echo h(n2($g["PLAN_USE_PCT"])); ?>% /
                        <?php echo h(n2($g["BALANCE_PCT"])); ?>%
                    </div>
                </div>
            <?php } ?>

            <div class="chart-legend">
                <span class="legend-plan"></span> Plan Use %
                <span class="legend-balance"></span> Balance %
                <span class="legend-over"></span> Over Capacity
            </div>
        </div>

    <?php } ?>
</div>

</body>
</html>