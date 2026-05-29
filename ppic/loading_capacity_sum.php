<?php
if (session_id() == "") {
    session_start();
}

require_once dirname(__DIR__) . "/config/db_plant2.php";

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

    return rtrim(rtrim(number_format($n, 2, ".", ","), "0"), ".");
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

function tonase_sort_number($value) {
    $value = (string)$value;

    if (preg_match('/[0-9]+/', $value, $m)) {
        return intval($m[0]);
    }

    return 999999;
}

function cmp_tonase_group($a, $b) {
    $na = tonase_sort_number($a);
    $nb = tonase_sort_number($b);

    if ($na == $nb) {
        return strcmp((string)$a, (string)$b);
    }

    return ($na < $nb) ? -1 : 1;
}

$monthInput    = get_value("month", date("Y-m"));
$machineFilter = get_value("machine", "");
$stationFilter = get_value("station", "");
$export        = strtolower(get_value("export", ""));

$workDaysMonth = to_float(get_value("work_days", "25"), 25);
$workHourDays  = to_float(get_value("work_hours", "21"), 21);
$eff           = to_float(get_value("eff", "0.90"), 0.90);

if ($workDaysMonth <= 0) {
    $workDaysMonth = 25;
}
if ($workHourDays <= 0) {
    $workHourDays = 21;
}
if ($eff <= 0) {
    $eff = 0.90;
}

$startDate = $monthInput . "-01";
if (strtotime($startDate) === false) {
    $monthInput = date("Y-m");
    $startDate = date("Y-m-01");
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

$groups = array();
$totalRows = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $machineNo = isset($r["MAC_CODE"]) ? trim((string)$r["MAC_CODE"]) : "";
    $station   = isset($r["MAG_STATION"]) ? trim((string)$r["MAG_STATION"]) : "";

    if ($machineNo == "") {
        $machineNo = "*NONE*";
    }
    if ($station == "") {
        $station = "*NONE*";
    }

    if ($machineFilter != "" && stripos($machineNo, $machineFilter) === false) {
        continue;
    }

    if ($stationFilter != "" && stripos($station, $stationFilter) === false) {
        continue;
    }

    $woQty = isset($r["WO_QTY"]) ? floatval($r["WO_QTY"]) : 0;
    $cytm  = isset($r["ITEM_CYTM"]) ? floatval($r["ITEM_CYTM"]) : 0;
    $cav   = isset($r["ITEM_CAVT"]) ? floatval($r["ITEM_CAVT"]) : 0;

    /*
        Rumus sama dengan MC Capacity Plan:
        Output Hour       = 3600 / CYTM x CAV
        Time Usage / Hour = ORDER / Output Hour
        MC Usage / Days   = Time Usage / Hour / Work Hour / Days
        Capacity Usage %  = MC Usage / Days / Work Days / Month x 100
    */
    $outputHour = 0;
    if ($cytm > 0) {
        $outputHour = (3600.0 / $cytm) * $cav;
    }

    $timeUsageHour = ($outputHour > 0) ? ($woQty / $outputHour) : 0;
    $mcUsageDays   = ($workHourDays > 0) ? ($timeUsageHour / $workHourDays) : 0;

    $groupKey = $station;

    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = array(
            "TONASE" => $station,
            "MACHINES" => array(),
            "TOTAL_MC_DAYS" => 0,
            "PLAN_USE_PCT" => 0,
            "BALANCE_DAY" => 0,
            "BALANCE_PCT" => 0,
            "NUM_OF_DAY" => 0,
            "REMARK" => ""
        );
    }

    $groups[$groupKey]["MACHINES"][$machineNo] = true;
    $groups[$groupKey]["TOTAL_MC_DAYS"] += $mcUsageDays;

    $totalRows++;
}

uksort($groups, "cmp_tonase_group");

/* ======================================================
   HITUNG SUMMARY PER TONASE
====================================================== */
foreach ($groups as $key => $g) {
    $machineCount = count($g["MACHINES"]);

    if ($machineCount <= 0) {
        $machineCount = 1;
    }

    $numOfDay = $machineCount * $workDaysMonth;
    $planUsePct = ($numOfDay > 0) ? ($g["TOTAL_MC_DAYS"] / $numOfDay * 100) : 0;
    $balanceDay = $numOfDay - $g["TOTAL_MC_DAYS"];
    $balancePct = 100 - $planUsePct;

    $groups[$key]["MC_COUNT"] = $machineCount;
    $groups[$key]["NUM_OF_DAY"] = $numOfDay;
    $groups[$key]["PLAN_USE_PCT"] = $planUsePct;
    $groups[$key]["BALANCE_DAY"] = $balanceDay;
    $groups[$key]["BALANCE_PCT"] = $balancePct;
}

/* ======================================================
   EXPORT EXCEL
====================================================== */
if ($export == "excel") {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $fileName = "MC_CAPACITY_SUMMARY_TONASE_" . date("Ym", strtotime($startDate)) . "_" . date("Ymd_His") . ".xls";

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
            mso-number-format:"\@";
            padding: 3px;
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
    </style>
</head>
<body>

<table>
    <tr>
        <td colspan="10" class="title">PT.IMC TEKNO INDONESIA</td>
    </tr>
    <tr>
        <td colspan="10" class="title">MC CAPACITY SUMMARY BY TONASE</td>
    </tr>
    <tr>
        <td colspan="10" class="info">
            Month : <?php echo h($monthTitle); ?> |
            Work Days : <?php echo h(n2z($workDaysMonth)); ?> |
            Work Hour/Days : <?php echo h(n2z($workHourDays)); ?> |
            Eff : <?php echo h(n2z($eff)); ?> |
            Export Date : <?php echo h($printDate); ?>
        </td>
    </tr>
    <tr>
        <td colspan="10" class="info"></td>
    </tr>

    <tr>
        <th>NO</th>
        <th>TONASE</th>
        <th>MC COUNT</th>
        <th>NUM OF DAY</th>
        <th>PLAN USE DAY</th>
        <th>PLAN USE %</th>
        <th>BALANCE DAY</th>
        <th>BALANCE %</th>
        <th>REMARK</th>
        <th>MONTH</th>
    </tr>

    <?php $no = 1; ?>
    <?php foreach ($groups as $g) { ?>
        <tr>
            <td class="num"><?php echo h($no); ?></td>
            <td><?php echo h($g["TONASE"]); ?></td>
            <td class="num"><?php echo h(excel_num($g["MC_COUNT"], 0)); ?></td>
            <td class="num"><?php echo h(excel_num($g["NUM_OF_DAY"], 0)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["TOTAL_MC_DAYS"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["PLAN_USE_PCT"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["BALANCE_DAY"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["BALANCE_PCT"], 2)); ?></td>
            <td><?php echo h($g["REMARK"]); ?></td>
            <td><?php echo h($monthTitle); ?></td>
        </tr>
        <?php $no++; ?>
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
    <title>MC Capacity Summary By Tonase</title>

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
            max-width: 900px;
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
            max-width: 900px;
            margin: 0 auto 6px auto;
            text-align: right;
        }

        .page {
            width: calc(100% - 20px);
            max-width: 900px;
            min-height: 700px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 34px 24px;
            border: 2px solid #000000;
            box-sizing: border-box;
        }

        .company {
            font-weight: bold;
            font-size: 20px;
            line-height: 22px;
        }

        .title {
            font-weight: bold;
            font-size: 20px;
            line-height: 22px;
        }

        .month {
            font-weight: bold;
            font-size: 13px;
            line-height: 18px;
            margin-bottom: 14px;
        }

        table.report {
            width: 82%;
            border-collapse: collapse;
            table-layout: fixed;
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
                width: 82%;
            }
        }
		.chart-box {
    margin-top: 24px;
    width: 82%;
    border: 1px solid #000000;
    padding: 10px;
    box-sizing: border-box;
}

.chart-title {
    font-weight: bold;
    font-size: 15px;
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
    width: 70px;
    font-weight: bold;
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

.chart-value {
    width: 110px;
    text-align: right;
    padding-left: 8px;
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
    </style>
</head>
<body>

<div class="filter">
    <form method="get">
        <b>MC Capacity Summary By Tonase</b>
        &nbsp;&nbsp;
        Month:
        <input type="month" name="month" value="<?php echo h($monthInput); ?>">
        &nbsp;
        Work Days:
        <input type="text" name="work_days" value="<?php echo h(n2z($workDaysMonth)); ?>" style="width:55px;">
        &nbsp;
        Work Hours:
        <input type="text" name="work_hours" value="<?php echo h(n2z($workHourDays)); ?>" style="width:55px;">
        &nbsp;
        Eff:
        <input type="text" name="eff" value="<?php echo h(n2z($eff)); ?>" style="width:55px;">
        &nbsp;
        Tonase:
        <input type="text" name="station" value="<?php echo h($stationFilter); ?>" placeholder="20080T" style="width:100px;">
        &nbsp;
        <button type="submit" class="btn">FILTER</button>
        <a href="mc_capacity_sum.php" class="btn">CURRENT</a>
        <a href="mc_capacity_plan.php?month=<?php echo h($monthInput); ?>&work_days=<?php echo h(n2z($workDaysMonth)); ?>&work_hours=<?php echo h(n2z($workHourDays)); ?>&eff=<?php echo h(n2z($eff)); ?>&station=<?php echo h(urlencode($stationFilter)); ?>" class="btn">DETAIL</a>
        <button type="submit" name="export" value="excel" class="btn">EXPORT EXCEL</button>
    </form>
</div>

<div class="toolbar">
    <button type="button" class="btn" onclick="window.print()">PRINT</button>
    <button type="button" class="btn" onclick="window.location.href='loading_capacity_tonase.php'">CLOSE</button>
</div>

<div class="page">
    <div class="company">PT.IMC TEKNO INDONESIA</div>
    <div class="title">MC CAPACITY SUMMARY</div>
    <div class="month">Month : <?php echo h($monthTitle); ?></div>

    <?php if ($totalRows == 0) { ?>
        <div class="no-data">Data capacity summary tidak ditemukan.</div>
    <?php } else { ?>
        <table class="report">
            <colgroup>
                <col style="width:6%;">
                <col style="width:16%;">
                <col style="width:12%;">
                <col style="width:14%;">
                <col style="width:13%;">
                <col style="width:10%;">
                <col style="width:13%;">
                <col style="width:10%;">
                <col style="width:20%;">
            </colgroup>
            <thead>
                <tr>
                    <th>NO</th>
                    <th>TONASE</th>
                    <th>MC COUNT</th>
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
                        <td class="center bold"><?php echo h($g["TONASE"]); ?></td>
                        <td class="num bold"><?php echo h(n0($g["MC_COUNT"])); ?></td>
                        <td class="num bold"><?php echo h(n0($g["NUM_OF_DAY"])); ?></td>
                        <td class="num bold"><?php echo h(n2($g["TOTAL_MC_DAYS"])); ?></td>
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
    <div class="chart-title">GRAFIK MC CAPACITY SUMMARY BY TONASE</div>

    <?php foreach ($groups as $g) { ?>
        <?php
            $planPct = floatval($g["PLAN_USE_PCT"]);
            $balPct  = floatval($g["BALANCE_PCT"]);

            if ($planPct < 0) $planPct = 0;
            if ($balPct < 0) $balPct = 0;

            if ($planPct > 100) $planWidth = 100;
            else $planWidth = $planPct;

            if ($balPct > 100) $balWidth = 100;
            else $balWidth = $balPct;
        ?>

        <div class="chart-row">
            <div class="chart-label"><?php echo h($g["TONASE"]); ?></div>

            <div class="chart-track">
                <div class="chart-plan" style="width: <?php echo h($planWidth); ?>%;"></div>
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
    </div>
</div>
    <?php } ?>
</div>

</body>
</html>