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
        return "0.00";
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

function month_label($startDate, $idx) {
    return date("j-M-y", strtotime("+" . intval($idx) . " month", strtotime($startDate)));
}

$startInput = get_value("start_month", date("Y") . "-01");
$custFilter = get_value("cust", "%");
$itemFilter = get_value("item", "");
$export = strtolower(get_value("export", ""));

$workHours = to_float(get_value("work_hours", "22"), 22);
$workDays  = to_float(get_value("work_days", "25"), 25);
$eff       = to_float(get_value("eff", "0.90"), 0.90);

if ($workHours <= 0) {
    $workHours = 22;
}

if ($workDays <= 0) {
    $workDays = 25;
}

if ($eff <= 0) {
    $eff = 0.90;
}

if ($custFilter == "" || strtoupper($custFilter) == "ALL") {
    $custFilter = "%";
}

$startDate = $startInput . "-01";
if (strtotime($startDate) === false) {
    $startInput = date("Y") . "-01";
    $startDate = $startInput . "-01";
}

$endDate = date("Y-m-t", strtotime("+11 month", strtotime($startDate)));
$monthTitle = date("F Y", strtotime($startDate));
$printDate = date("d-M-Y H:i:s");

$monthNames = array();
for ($i = 0; $i < 12; $i++) {
    $monthNames[$i + 1] = month_label($startDate, $i);
}

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
   LOAD DATA DARI SP_LOADING_CAP
====================================================== */
$sql = "EXEC dbo.SP_LOADING_CAP ?, ?, ?";
$stmt = sqlsrv_query($conn, $sql, array($startDate, $endDate, $custFilter));

if ($stmt === false) {
    die("<pre>Query SP_LOADING_CAP error:\n" . sql_error_text() . "</pre>");
}

$groups = array();
$totalRows = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $custCode = isset($r["CUST_CODE"]) ? trim((string)$r["CUST_CODE"]) : "";
    $custComp = isset($r["CUST_COMP"]) ? trim((string)$r["CUST_COMP"]) : "";
    $partNum = isset($r["PART_NUM"]) ? trim((string)$r["PART_NUM"]) : "";
    $partNo = isset($r["PART_NO"]) ? trim((string)$r["PART_NO"]) : "";
    $partName = isset($r["PART_NAME"]) ? trim((string)$r["PART_NAME"]) : "";

    if ($itemFilter != "") {
        $haystack = $partNum . " " . $partNo . " " . $partName;
        if (stripos($haystack, $itemFilter) === false) {
            continue;
        }
    }

    $ct = isset($r["ITEM_CYTM"]) ? floatval($r["ITEM_CYTM"]) : 0;
    $cav = isset($r["ITEM_CAVT"]) ? floatval($r["ITEM_CAVT"]) : 0;

    /*
        Capacity sesuai Crystal Report lama:
        Cap/Day   = 3600 / CT x CAV x Work Hours / Eff
        Cap/Month = Cap/Day x Work Days
        Cap/Year  = Cap/Month x 12
        MCD       = Qty / Cap/Day
    */
    $capDay = 0;
    if ($ct > 0) {
        $capDay = (3600.0 / $ct) * $cav * $workHours / $eff;
    }
    $capMonth = $capDay * $workDays;
    $capYear = $capMonth * 12;

    $qty = array();
    $mcd = array();
    $totalQty = 0;
    $totalMcd = 0;

    for ($i = 1; $i <= 12; $i++) {
        $field = "QTY" . $i;
        $qty[$i] = isset($r[$field]) ? floatval($r[$field]) : 0;
        $mcd[$i] = ($capDay > 0) ? ($qty[$i] / $capDay) : 0;
        $totalQty += $qty[$i];
        $totalMcd += $mcd[$i];
    }

    $groupKey = $custCode . "|" . $custComp;
    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = array(
            "CUST_CODE" => $custCode,
            "CUST_COMP" => $custComp,
            "ROWS" => array(),
            "TOTAL_QTY" => 0,
            "TOTAL_MCD" => 0,
            "MONTH_QTY" => array(),
            "MONTH_MCD" => array()
        );

        for ($i = 1; $i <= 12; $i++) {
            $groups[$groupKey]["MONTH_QTY"][$i] = 0;
            $groups[$groupKey]["MONTH_MCD"][$i] = 0;
        }
    }

    $row = array(
        "CUST_CODE" => $custCode,
        "CUST_COMP" => $custComp,
        "PART_NUM" => $partNum,
        "PART_NO" => $partNo,
        "PART_NAME" => $partName,
        "CT" => $ct,
        "CAV" => $cav,
        "CAP_DAY" => $capDay,
        "CAP_MONTH" => $capMonth,
        "CAP_YEAR" => $capYear,
        "TOTAL_QTY" => $totalQty,
        "TOTAL_MCD" => $totalMcd,
        "QTY" => $qty,
        "MCD" => $mcd
    );

    $groups[$groupKey]["ROWS"][] = $row;
    $groups[$groupKey]["TOTAL_QTY"] += $totalQty;
    $groups[$groupKey]["TOTAL_MCD"] += $totalMcd;

    for ($i = 1; $i <= 12; $i++) {
        $groups[$groupKey]["MONTH_QTY"][$i] += $qty[$i];
        $groups[$groupKey]["MONTH_MCD"][$i] += $mcd[$i];
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

    $fileName = "LOADING_CAPACITY_YEARLY_" . date("Ym", strtotime($startDate)) . "_" . date("Ymd_His") . ".xls";

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
        .cust { background: #eeeeee; font-weight: bold; }
        .total { background: #f8f8f8; font-weight: bold; }
    </style>
</head>
<body>
<table>
    <tr><td colspan="35" class="title">PT.IMC TEKNO INDONESIA</td></tr>
    <tr><td colspan="35" class="title">LOADING CAPACITY</td></tr>
    <tr><td colspan="35" class="info">Start Month : <?php echo h($monthTitle); ?> | Work Hours : <?php echo h(n2z($workHours)); ?> | Work Days : <?php echo h(n2z($workDays)); ?> | Eff : <?php echo h(n2z($eff)); ?> | Export Date : <?php echo h($printDate); ?></td></tr>
    <tr><td colspan="35" class="info"></td></tr>

    <tr>
        <th rowspan="2">CUSTOMER</th>
        <th rowspan="2">ITEM CODE</th>
        <th rowspan="2">PART NO</th>
        <th rowspan="2">PART NAME</th>
        <th rowspan="2">CT</th>
        <th rowspan="2">CAV</th>
        <th rowspan="2">Cap/Day</th>
        <th rowspan="2">Cap/Month</th>
        <th rowspan="2">Cap/Year</th>
        <th rowspan="2">Total Qty</th>
        <th rowspan="2">Total MCD</th>
        <?php for ($i = 1; $i <= 12; $i++) { ?>
            <th colspan="2"><?php echo h($monthNames[$i]); ?></th>
        <?php } ?>
    </tr>
    <tr>
        <?php for ($i = 1; $i <= 12; $i++) { ?>
            <th>Qty</th>
            <th>MCD</th>
        <?php } ?>
    </tr>

    <?php foreach ($groups as $g) { ?>
        <tr class="cust">
            <td colspan="35"><?php echo h($g["CUST_CODE"] . " - " . $g["CUST_COMP"]); ?></td>
        </tr>

        <?php foreach ($g["ROWS"] as $r) { ?>
            <tr>
                <td><?php echo h($r["CUST_COMP"]); ?></td>
                <td><?php echo h($r["PART_NUM"]); ?></td>
                <td><?php echo h($r["PART_NO"]); ?></td>
                <td><?php echo h($r["PART_NAME"]); ?></td>
                <td class="num2"><?php echo h(excel_num($r["CT"], 2)); ?></td>
                <td class="num"><?php echo h(excel_num($r["CAV"], 0)); ?></td>
                <td class="num"><?php echo h(excel_num($r["CAP_DAY"], 0)); ?></td>
                <td class="num"><?php echo h(excel_num($r["CAP_MONTH"], 0)); ?></td>
                <td class="num"><?php echo h(excel_num($r["CAP_YEAR"], 0)); ?></td>
                <td class="num"><?php echo h(excel_num($r["TOTAL_QTY"], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["TOTAL_MCD"], 2)); ?></td>
                <?php for ($i = 1; $i <= 12; $i++) { ?>
                    <td class="num"><?php echo h(excel_num($r["QTY"][$i], 0)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["MCD"][$i], 2)); ?></td>
                <?php } ?>
            </tr>
        <?php } ?>

        <tr class="total">
            <td colspan="9">TOTAL <?php echo h($g["CUST_COMP"]); ?></td>
            <td class="num"><?php echo h(excel_num($g["TOTAL_QTY"], 0)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["TOTAL_MCD"], 2)); ?></td>
            <?php for ($i = 1; $i <= 12; $i++) { ?>
                <td class="num"><?php echo h(excel_num($g["MONTH_QTY"][$i], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($g["MONTH_MCD"][$i], 2)); ?></td>
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
    <title>Loading Capacity Yearly</title>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #9c9c9c;
            color: #000000;
            font-family: "Times New Roman", serif;
            font-size: 9px;
        }

        .filter {
            width: calc(100% - 20px);
            max-width: 1280px;
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
            max-width: 1280px;
            margin: 0 auto 6px auto;
            text-align: right;
        }

        .page {
            width: calc(100% - 20px);
            max-width: 1280px;
            min-height: 780px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 36px 28px;
            border: 2px solid #000000;
            box-sizing: border-box;
            overflow-x: auto;
        }

        .company {
            font-weight: bold;
            font-size: 17px;
            line-height: 20px;
        }

        .title {
            font-weight: bold;
            font-size: 16px;
            line-height: 18px;
        }

        .subhead {
            font-weight: bold;
            font-size: 11px;
            line-height: 15px;
            margin-top: 8px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 8px;
            font-size: 7px;
        }

        table.report th,
        table.report td {
            border: none;
            padding: 1px 2px;
            vertical-align: top;
            line-height: 9px;
            overflow: hidden;
        }

        table.report th {
            text-align: center;
            font-weight: bold;
            border-bottom: 1px solid #000000;
        }

        table.report td.num {
            text-align: right;
            white-space: nowrap;
        }

        .cust-row td {
            font-weight: bold;
            padding-top: 10px;
            padding-bottom: 3px;
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
                font-size: 5.5px;
            }

            table.report th,
            table.report td {
                padding: 1px;
                line-height: 7px;
            }
        }
    </style>
</head>
<body>

<div class="filter">
    <form method="get">
        <b>Loading Capacity Yearly</b>
        &nbsp;&nbsp;
        Start Month:
        <input type="month" name="start_month" value="<?php echo h($startInput); ?>">
        &nbsp;
        Customer:
        <input type="text" name="cust" value="<?php echo h($custFilter); ?>" list="custList" placeholder="% / cust code" style="width:130px;">
        <datalist id="custList">
            <?php foreach ($custList as $c) { ?>
                <option value="<?php echo h($c["CUST_CODE"]); ?>"><?php echo h($c["CUST_COMP"]); ?></option>
            <?php } ?>
        </datalist>
        &nbsp;
        Item:
        <input type="text" name="item" value="<?php echo h($itemFilter); ?>" placeholder="Part code / name" style="width:160px;">
        &nbsp;
        Work Hours:
        <input type="text" name="work_hours" value="<?php echo h(n2z($workHours)); ?>" style="width:55px;">
        &nbsp;
        Work Days:
        <input type="text" name="work_days" value="<?php echo h(n2z($workDays)); ?>" style="width:55px;">
        &nbsp;
        Eff:
        <input type="text" name="eff" value="<?php echo h(n2z($eff)); ?>" style="width:45px;">
        &nbsp;
        <button type="submit" class="btn">FILTER</button>
        <a href="loading_capacity_yearly.php?start_month=<?php echo h($startInput); ?>&cust=%25&work_hours=<?php echo h(n2z($workHours)); ?>&work_days=<?php echo h(n2z($workDays)); ?>&eff=<?php echo h(n2z($eff)); ?>" class="btn">ALL</a>
        <button type="submit" name="export" value="excel" class="btn">EXPORT EXCEL</button>
    </form>
</div>

<div class="toolbar">
    <button type="button" class="btn" onclick="window.print()">PRINT</button>
    <button type="button" class="btn" onclick="window.location.href='dashboard_ppic.php'">CLOSE</button>
</div>

<div class="page">
    <div class="company">PT.IMC TEKNO INDONESIA</div>
    <div class="title">LOADING CAPACITY</div>
    <div class="subhead">
        CUSTOMER : <?php echo ($custFilter == "%") ? "ALL" : h($custFilter); ?><br>
        START MONTH : <?php echo h($monthTitle); ?> &nbsp;&nbsp;
        WORK HOURS : <?php echo h(n2z($workHours)); ?> &nbsp;&nbsp;
        WORK DAYS : <?php echo h(n2z($workDays)); ?> &nbsp;&nbsp;
        EFF : <?php echo h(n2z($eff)); ?>
    </div>

    <?php if ($totalRows == 0) { ?>
        <div class="no-data">Data loading capacity tidak ditemukan.</div>
    <?php } else { ?>
        <table class="report">
            <colgroup>
                <col style="width:5%;">
                <col style="width:8%;">
                <col style="width:14%;">
                <col style="width:3%;">
                <col style="width:3%;">
                <col style="width:4%;">
                <col style="width:5%;">
                <col style="width:5%;">
                <col style="width:4%;">
                <?php for ($i = 1; $i <= 12; $i++) { ?>
                    <col style="width:3.3%;">
                    <col style="width:3.3%;">
                <?php } ?>
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2">ITEM CODE</th>
                    <th rowspan="2">PART NO</th>
                    <th rowspan="2">PART NAME</th>
                    <th rowspan="2">CT</th>
                    <th rowspan="2">CAV</th>
                    <th rowspan="2">Cap/Day</th>
                    <th rowspan="2">Cap/Month</th>
                    <th rowspan="2">Cap/Year</th>
                    <th rowspan="2">Total</th>
                    <?php for ($i = 1; $i <= 12; $i++) { ?>
                        <th colspan="2"><?php echo h($monthNames[$i]); ?></th>
                    <?php } ?>
                </tr>
                <tr>
                    <?php for ($i = 1; $i <= 12; $i++) { ?>
                        <th>Qty</th>
                        <th>MCD</th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $g) { ?>
                    <tr class="cust-row">
                        <td colspan="33"><?php echo h($g["CUST_COMP"]); ?></td>
                    </tr>

                    <?php foreach ($g["ROWS"] as $r) { ?>
                        <tr>
                            <td><?php echo h($r["PART_NUM"]); ?></td>
                            <td><?php echo h($r["PART_NO"]); ?></td>
                            <td><?php echo h($r["PART_NAME"]); ?></td>
                            <td class="num"><?php echo h(n2($r["CT"])); ?></td>
                            <td class="num"><?php echo h(n0($r["CAV"])); ?></td>
                            <td class="num"><?php echo h(n0($r["CAP_DAY"])); ?></td>
                            <td class="num"><?php echo h(n0($r["CAP_MONTH"])); ?></td>
                            <td class="num"><?php echo h(n0($r["CAP_YEAR"])); ?></td>
                            <td class="num"><?php echo h(n0($r["TOTAL_QTY"])); ?></td>
                            <?php for ($i = 1; $i <= 12; $i++) { ?>
                                <td class="num"><?php echo h(n0($r["QTY"][$i])); ?></td>
                                <td class="num"><?php echo h(n2($r["MCD"][$i])); ?></td>
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
