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
        return "-";
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

$monthInput = get_value("month", date("Y-m"));
$machineFilter = get_value("machine", "");
$stationFilter = get_value("station", "");
$export = strtolower(get_value("export", ""));

/* ======================================================
   LOAD MASTER DATA PROCESS & MAG 
   (Digunakan untuk mengisi Form Input Default UI otomatis)
====================================================== */
$sqlProc = "SELECT PROCESS.PROC_ID, PROCESS.PROC_NAME, PROCESS.PROC_EFFICIENTCY, 
                   PROCESS.PROC_MEASURE, PROCESS.PROC_HOURS, PROCESS.PROC_MMDAY, 
                   PROCESS.PROC_MMDAY2, PROCESS.PROC_MMDAY3, MAG.MAG_LOC, MAG.MAG_STATION
            FROM PROCESS 
            INNER JOIN MAG ON PROCESS.PROC_ID = MAG.PROC_ID";

$stmtProc = sqlsrv_query($conn, $sqlProc);
$processMap = array();

// Nilai default dasar sebelum membaca Database
$db_work_days = 25;
$db_work_hours = 21;
$db_eff = 0.90;

if ($stmtProc !== false) {
    $first = true;
    while ($p = sqlsrv_fetch_array($stmtProc, SQLSRV_FETCH_ASSOC)) {
        $st = trim((string)$p["MAG_STATION"]);
        if ($st !== "") {
            $processMap[$st] = $p;
            
            // Ambil data baris pertama dari DB sebagai default global jika form tidak difilter
            if ($first) {
                if (isset($p["PROC_MMDAY"]) && is_numeric($p["PROC_MMDAY"])) {
                    $db_work_days = floatval($p["PROC_MMDAY"]);
                }
                if (isset($p["PROC_HOURS"]) && is_numeric($p["PROC_HOURS"])) {
                    $db_work_hours = floatval($p["PROC_HOURS"]);
                }
                if (isset($p["PROC_EFFICIENTCY"]) && is_numeric($p["PROC_EFFICIENTCY"])) {
                    $effVal = floatval($p["PROC_EFFICIENTCY"]);
                    $db_eff = ($effVal > 1) ? ($effVal / 100) : $effVal; // Handle format persen
                }
                $first = false;
            }
        }
    }
}

// Jika user melakukan filter Station spesifik, gunakan nilai khusus dari station tersebut ke dalam Form
if ($stationFilter !== "" && isset($processMap[$stationFilter])) {
    $pData = $processMap[$stationFilter];
    if (isset($pData["PROC_MMDAY"]) && is_numeric($pData["PROC_MMDAY"])) {
        $db_work_days = floatval($pData["PROC_MMDAY"]);
    }
    if (isset($pData["PROC_HOURS"]) && is_numeric($pData["PROC_HOURS"])) {
        $db_work_hours = floatval($pData["PROC_HOURS"]);
    }
    if (isset($pData["PROC_EFFICIENTCY"]) && is_numeric($pData["PROC_EFFICIENTCY"])) {
        $effVal = floatval($pData["PROC_EFFICIENTCY"]);
        $db_eff = ($effVal > 1) ? ($effVal / 100) : $effVal;
    }
}

/* 
   Ambil nilai dari GET parameter (jika di-klik tombol FILTER/SUBMIT). 
   Jika form belum disubmit atau nilainya kosong (""), maka otomatis menggunakan data hasil query DB ($db_...).
*/
$workDaysMonth = to_float(get_value("work_days", ""), $db_work_days);
$workHourDays = to_float(get_value("work_hours", ""), $db_work_hours);
$eff = to_float(get_value("eff", ""), $db_eff);

// Proteksi jika nilainya diisi nol atau negatif
if ($workDaysMonth <= 0) $workDaysMonth = $db_work_days;
if ($workHourDays <= 0) $workHourDays = $db_work_hours;
if ($eff <= 0) $eff = $db_eff;


$startDate = $monthInput . "-01";
if (strtotime($startDate) === false) {
    $monthInput = date("Y-m");
    $startDate = date("Y-m-01");
}

$endDate = date("Y-m-t", strtotime($startDate));
$monthTitle = date("F Y", strtotime($startDate));
$printDate = date("d-M-Y H:i:s");

/* ======================================================
   LOAD DATA DARI SP_MC_CAPACITY
====================================================== */
$sql = "EXEC dbo.SP_MC_CAPACITY ?, ?";
$stmt = sqlsrv_query($conn, $sql, array($startDate, $endDate));

if ($stmt === false) {
    die("<pre>Query SP_MC_CAPACITY error:\n" . sql_error_text() . "</pre>");
}

$groups = array();
$totalRows = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $machineNo = isset($r["MAC_CODE"]) ? trim((string)$r["MAC_CODE"]) : "";
    $station = isset($r["MAG_STATION"]) ? trim((string)$r["MAG_STATION"]) : "";

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

    $itemCode = isset($r["ITEM_CODE"]) ? trim((string)$r["ITEM_CODE"]) : "";
    $itemName = isset($r["ITEM_NAME"]) ? trim((string)$r["ITEM_NAME"]) : "";
    $itemNo = isset($r["ITEM_NO"]) ? trim((string)$r["ITEM_NO"]) : "";
    $customer = isset($r["CUST_ALIAS"]) ? trim((string)$r["CUST_ALIAS"]) : "";
    $woQty = isset($r["WO_QTY"]) ? floatval($r["WO_QTY"]) : 0;
    $cytm = isset($r["ITEM_CYTM"]) ? floatval($r["ITEM_CYTM"]) : 0;
    $cav = isset($r["ITEM_CAVT"]) ? floatval($r["ITEM_CAVT"]) : 0;

    $outputHour = 0;
    if ($cytm > 0) {
        $outputHour = (3600.0 / $cytm) * $cav;
    }

    // Assign fallback values based on the input form parameters
    $rowWorkDaysMonth = $workDaysMonth;
    $rowWorkHourDays  = $workHourDays;
    $rowEff           = $eff;
    $workHourMonth    = $workDaysMonth * $workHourDays * $eff; 

    // Override individual table row calculations if MAG_STATION matches an entry in the PROCESS DB
    if (isset($processMap[$station])) {
        $pData = $processMap[$station];

        if (isset($pData["PROC_MMDAY"]) && is_numeric($pData["PROC_MMDAY"])) {
            $workHourMonth = floatval($pData["PROC_MMDAY"]);
            $rowWorkDaysMonth = floatval($pData["PROC_MMDAY"]); // Opsional, sesuaikan apakah WorkDays mengikuti DB per baris
        }

        if (isset($pData["PROC_HOURS"]) && floatval($pData["PROC_HOURS"]) > 0) {
            $rowWorkHourDays = floatval($pData["PROC_HOURS"]);
        }

        if (isset($pData["PROC_EFFICIENTCY"]) && is_numeric($pData["PROC_EFFICIENTCY"])) {
            $effVal = floatval($pData["PROC_EFFICIENTCY"]);
            $rowEff = ($effVal > 1) ? ($effVal / 100) : $effVal;
        }
        
        $workHourMonth = $rowWorkDaysMonth * $rowWorkHourDays * $rowEff;
    }

    $outputDay = $outputHour * $rowWorkHourDays;
    $timeUsageHour = ($outputHour > 0) ? ($woQty / $outputHour) : 0;
    $mcUsageDays = ($rowWorkHourDays > 0) ? ($timeUsageHour / $rowWorkHourDays) : 0;
    $availableTimeHour = $workHourMonth;
    $capacityUsage = ($rowWorkDaysMonth > 0) ? ($mcUsageDays / $rowWorkDaysMonth * 100) : 0;

    $groupKey = $machineNo;

    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = array(
            "MACHINE_NO" => $machineNo,
            "STATION" => $station,
            "ROWS" => array(),
            "TOTAL_MC_DAYS" => 0,
            "TOTAL_USAGE" => 0
        );
    }

    $row = array(
        "ITEM_CODE" => $itemCode,
        "ITEM_NAME" => $itemName,
        "ITEM_NO" => $itemNo,
        "CUSTOMER" => $customer,
        "CAV" => $cav,
        "CYTM" => $cytm,
        "OUTPUT_HOUR" => $outputHour,
        "OUTPUT_DAY" => $outputDay,
        "ORDER" => $woQty,
        "WORK_DAYS_MONTH" => $rowWorkDaysMonth,
        "WORK_HOUR_DAYS" => $rowWorkHourDays,
        "EFF" => $rowEff,
        "WORK_HOUR_MONTH" => $workHourMonth,
        "TIME_USAGE_HOUR" => $timeUsageHour,
        "MC_USAGE_DAYS" => $mcUsageDays,
        "AVAILABLE_TIME_HOUR" => $availableTimeHour,
        "CAPACITY_USAGE" => $capacityUsage,
        "TOTAL_USAGE" => $capacityUsage
    );

    $groups[$groupKey]["ROWS"][] = $row;
    $groups[$groupKey]["TOTAL_MC_DAYS"] += $mcUsageDays;
    $groups[$groupKey]["TOTAL_USAGE"] += $capacityUsage;

    $totalRows++;
}

uksort($groups, "cmp_machine_group");

/* ======================================================
   EXPORT EXCEL
====================================================== */
if ($export == "excel") {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $fileName = "MC_CAPACITY_PLAN_" . date("Ym", strtotime($startDate)) . "_" . date("Ymd_His") . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
    header("Cache-Control: max-age=0");
    header("Pragma: public");
    header("Expires: 0");

    echo "ï»¿";
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
        .group {
            background: #eeeeee;
            font-weight: bold;
        }
        .total {
            background: #f8f8f8;
            font-weight: bold;
        }
    </style>
</head>
<body>
<table>
    <tr>
        <td colspan="20" class="title">P.T. IMC TEKNO INDONESIA</td>
    </tr>
    <tr>
        <td colspan="20" class="title">MC CAPACITY PLAN</td>
    </tr>
    <tr>
        <td colspan="20" class="info">
            Month : <?php echo h($monthTitle); ?> |
            Work Days : <?php echo h(n2z($workDaysMonth)); ?> |
            Work Hour/Days : <?php echo h(n2z($workHourDays)); ?> |
            Eff : <?php echo h(n2z($eff)); ?> |
            Export Date : <?php echo h($printDate); ?>
        </td>
    </tr>
    <tr><td colspan="20" class="info"></td></tr>
    <tr>
        <th>MC</th>
        <th>Station</th>
        <th>ITEM_CODE</th>
        <th>ITEM_NAME</th>
        <th>ITEM_NO</th>
        <th>CUSTOMER</th>
        <th>CAV</th>
        <th>CYTM</th>
        <th>Output Hour</th>
        <th>Output/Day</th>
        <th>ORDER</th>
        <th>Work Days/Month</th>
        <th>Work Hour/Days</th>
        <th>Eff</th>
        <th>Work Hour/Month</th>
        <th>Time Usage/Hour</th>
        <th>Mc Usage/Days</th>
        <th>Available Time/Hour</th>
        <th>Capacity Usage %</th>
        <th>Total Usage %</th>
    </tr>

    <?php foreach ($groups as $g) { ?>
        <tr class="group">
            <td><?php echo h($g["MACHINE_NO"]); ?></td>
            <td><?php echo h($g["STATION"]); ?></td>
            <td colspan="18"></td>
        </tr>

        <?php foreach ($g["ROWS"] as $r) { ?>
            <tr>
                <td><?php echo h($g["MACHINE_NO"]); ?></td>
                <td><?php echo h($g["STATION"]); ?></td>
                <td><?php echo h($r["ITEM_CODE"]); ?></td>
                <td><?php echo h($r["ITEM_NAME"]); ?></td>
                <td><?php echo h($r["ITEM_NO"]); ?></td>
                <td><?php echo h($r["CUSTOMER"]); ?></td>
                <td class="num"><?php echo h(excel_num($r["CAV"], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["CYTM"], 2)); ?></td>
                <td class="num"><?php echo h(excel_num($r["OUTPUT_HOUR"], 0)); ?></td>
                <td class="num"><?php echo h(excel_num($r["OUTPUT_DAY"], 0)); ?></td>
                <td class="num"><?php echo h(excel_num($r["ORDER"], 0)); ?></td>
                <td class="num"><?php echo h(excel_num($r["WORK_DAYS_MONTH"], 0)); ?></td>
                <td class="num"><?php echo h(excel_num($r["WORK_HOUR_DAYS"], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["EFF"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["WORK_HOUR_MONTH"], 2)); ?></td>
                <td class="num"><?php echo h(excel_num($r["TIME_USAGE_HOUR"], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["MC_USAGE_DAYS"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["AVAILABLE_TIME_HOUR"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["CAPACITY_USAGE"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["TOTAL_USAGE"], 2)); ?></td>
            </tr>
        <?php } ?>

        <?php $balanceUsage = 100 - $g["TOTAL_USAGE"]; ?>
        <tr class="total">
            <td colspan="17"></td>
            <td>Total</td>
            <td class="num2"><?php echo h(excel_num($g["TOTAL_MC_DAYS"], 2)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["TOTAL_USAGE"], 2)); ?></td>
        </tr>
        <tr class="total">
            <td colspan="18"></td>
            <td>Balance</td>
            <td class="num2"><?php echo h(excel_num($balanceUsage, 2)); ?></td>
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
    <title>MC Capacity Plan</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #9c9c9c;
            color: #000000;
            font-family: "Times New Roman", serif;
            font-size: 11px;
        }

        .filter {
            width: calc(100% - 20px);
            max-width: 1150px;
            margin: 8px auto;
            background: #d4d0c8;
            border: 1px solid #777777;
            padding: 8px;
            box-sizing: border-box;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
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
            max-width: 1150px;
            margin: 0 auto 6px auto;
            text-align: right;
        }

        .page {
            width: calc(100% - 20px);
            max-width: 1150px;
            min-height: 780px;
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

        .topline {
            margin-bottom: 16px;
        }

        .link-title {
            color: #0000ee;
            text-decoration: underline;
            margin-left: 80px;
            font-size: 16px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 10px;
        }

        table.report th,
        table.report td {
            border: 1px solid #000000;
            padding: 2px 3px;
            vertical-align: top;
            line-height: 12px;
            overflow: hidden;
        }

        table.report th {
            text-align: center;
            font-weight: bold;
        }

        table.report td.num,
        table.report th.num {
            text-align: right;
            white-space: nowrap;
        }

        .group-row td {
            font-size: 12px;
            font-weight: bold;
            border-bottom: none;
            background: #ffffff;
        }

        .data-row td {
            border-top: none;
            border-bottom: none;
        }

        .total-row td {
            font-weight: bold;
            border-top: none;
            border-bottom: none;
        }

        .balance-row td {
            font-weight: bold;
            border-top: none;
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
                font-size: 8px;
            }

            table.report th,
            table.report td {
                padding: 1px 2px;
                line-height: 10px;
            }
        }
    </style>
</head>
<body>

<div class="filter">
    <form method="get">
        <b>MC Capacity Plan</b>
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
        Machine:
        <input type="text" name="machine" value="<?php echo h($machineFilter); ?>" placeholder="201" style="width:90px;">
        &nbsp;
        Station:
        <input type="text" name="station" value="<?php echo h($stationFilter); ?>" placeholder="20080T" style="width:100px;">
        &nbsp;
        <button type="submit" class="btn">FILTER</button>
        <a href="mc_capacity_plan.php" class="btn">CURRENT</a>
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
        <div class="title">MC CAPACITY PLAN :</div>
        <div>
            <b>Month : <?php echo h($monthTitle); ?></b>
            <a class="link-title"
   href="mc_capacity_sum.php?month=<?php echo h($monthInput); ?>&work_days=<?php echo h(n2z($workDaysMonth)); ?>&work_hours=<?php echo h(n2z($workHourDays)); ?>&eff=<?php echo h(n2z($eff)); ?>">
   SUMMARY
</a>
        </div>
    </div>

    <?php if ($totalRows == 0) { ?>
        <div class="no-data">Data capacity tidak ditemukan.</div>
    <?php } else { ?>
        <table class="report">
            <colgroup>
                <col style="width:7%;">
                <col style="width:17%;">
                <col style="width:10%;">
                <col style="width:8%;">
                <col style="width:4%;">
                <col style="width:5%;">
                <col style="width:5%;">
                <col style="width:5%;">
                <col style="width:5%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:3%;">
                <col style="width:5%;">
                <col style="width:4%;">
                <col style="width:5%;">
                <col style="width:5%;">
                <col style="width:4%;">
                <col style="width:4%;">
            </colgroup>
            <thead>
                <tr>
                    <th>ITEM_CODE</th>
                    <th>ITEM_NAME</th>
                    <th>ITEM_NO</th>
                    <th>CUSTOMER</th>
                    <th>CAV</th>
                    <th>CYTM</th>
                    <th>Output<br>Hour<br>(pcs)</th>
                    <th>Output/<br>Day<br>(pcs)</th>
                    <th>ORDER</th>
                    <th>Work<br>Days/<br>Month</th>
                    <th>Work<br>Hour/<br>Days</th>
                    <th>Eff</th>
                    <th>Work<br>Hour/<br>Month</th>
                    <th>Time<br>Usage/<br>Hour</th>
                    <th>Mc<br>Usage/<br>Days</th>
                    <th>Available<br>Time/<br>Hour</th>
                    <th>Capacity<br>Usage/<br>%</th>
                    <th>Total<br>Usage/<br>%</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $g) { ?>
                    <tr class="group-row">
                        <td><?php echo h($g["MACHINE_NO"]); ?></td>
                        <td><?php echo h($g["STATION"]); ?></td>
                        <td colspan="16"></td>
                    </tr>

                    <?php foreach ($g["ROWS"] as $r) { ?>
                        <tr class="data-row">
                            <td><?php echo h($r["ITEM_CODE"]); ?></td>
                            <td><?php echo h($r["ITEM_NAME"]); ?></td>
                            <td><?php echo h($r["ITEM_NO"]); ?></td>
                            <td><?php echo h($r["CUSTOMER"]); ?></td>
                            <td class="num"><?php echo h(n0($r["CAV"])); ?></td>
                            <td class="num"><?php echo h(n2($r["CYTM"])); ?></td>
                            <td class="num"><?php echo h(n0($r["OUTPUT_HOUR"])); ?></td>
                            <td class="num"><?php echo h(n0($r["OUTPUT_DAY"])); ?></td>
                            <td class="num"><?php echo h(n0($r["ORDER"])); ?></td>
                            <td class="num"><?php echo h(n0($r["WORK_DAYS_MONTH"])); ?></td>
                            <td class="num"><?php echo h(n0($r["WORK_HOUR_DAYS"])); ?></td>
                            <td class="num"><?php echo h(n2z($r["EFF"])); ?></td>
                            <td class="num"><?php echo h(n2($r["WORK_HOUR_MONTH"])); ?></td>
                            <td class="num"><?php echo h(n0($r["TIME_USAGE_HOUR"])); ?></td>
                            <td class="num"><?php echo h(n2($r["MC_USAGE_DAYS"])); ?></td>
                            <td class="num"><?php echo h(n2($r["AVAILABLE_TIME_HOUR"])); ?></td>
                            <td class="num"><?php echo h(n2($r["CAPACITY_USAGE"])); ?></td>
                            <td class="num"><?php echo h(n2($r["TOTAL_USAGE"])); ?></td>
                        </tr>
                    <?php } ?>

                    <?php $balanceUsage = 100 - $g["TOTAL_USAGE"]; ?>
                    <tr class="total-row">
                        <td colspan="14"></td>
                        <td>Total</td>
                        <td></td>
                        <td class="num"><?php echo h(n2($g["TOTAL_MC_DAYS"])); ?></td>
                        <td class="num"><?php echo h(n2($g["TOTAL_USAGE"])); ?></td>
                    </tr>
                    <tr class="balance-row">
                        <td colspan="14"></td>
                        <td>Balance</td>
                        <td></td>
                        <td></td>
                        <td class="num"><?php echo h(n2($balanceUsage)); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</div>

</body>
</html>