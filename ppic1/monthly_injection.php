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

function rowv($row, $name, $default = "") {
    if (isset($row[$name])) {
        return $row[$name];
    }

    $upper = strtoupper($name);
    if (isset($row[$upper])) {
        return $row[$upper];
    }

    $lower = strtolower($name);
    if (isset($row[$lower])) {
        return $row[$lower];
    }

    return $default;
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

$monthInput = get_value("month", date("Y-m"));
$tonaseFilter = get_value("tonase", "");
$itemFilter = get_value("item", "");
$export = strtolower(get_value("export", ""));

if ($tonaseFilter == "%" || strtoupper($tonaseFilter) == "ALL TONASE") {
    $tonaseFilter = "";
}

if ($itemFilter == "%" || strtoupper($itemFilter) == "ALL ITEM") {
    $itemFilter = "";
}

$fromDate = $monthInput . "-01";
if (strtotime($fromDate) === false) {
    $monthInput = date("Y-m");
    $fromDate = date("Y-m-01");
}

$toDate = date("Y-m-t", strtotime($fromDate));
$monthLong = date("F Y", strtotime($fromDate));
$printDate = date("d-M-Y H:i:s");

/* ======================================================
   AUTO COMPLETE TONASE
====================================================== */
$tonaseList = array();
$sqlTonase = "
    SELECT DISTINCT LTRIM(RTRIM(MAG_STATION)) AS MAG_STATION
    FROM dbo.MAG
    WHERE ISNULL(MAG_STATION, '') <> ''
    ORDER BY LTRIM(RTRIM(MAG_STATION))
";
$stmtTonase = sqlsrv_query($conn, $sqlTonase);
if ($stmtTonase !== false) {
    while ($tr = sqlsrv_fetch_array($stmtTonase, SQLSRV_FETCH_ASSOC)) {
        $v = trim((string)$tr["MAG_STATION"]);
        if ($v != "") {
            $tonaseList[] = $v;
        }
    }
}

/* ======================================================
   LOAD DATA DARI sp_injection_report
   SP baru sudah langsung SELECT, tidak perlu sqlsrv_next_result
====================================================== */
$sql = "EXEC dbo.sp_injection_report ?, ?";
$stmt = sqlsrv_query($conn, $sql, array($fromDate, $toDate));

if ($stmt === false) {
    die("<pre>Query sp_injection_report error:\n" . sql_error_text() . "</pre>");
}

$groups = array();
$totalRows = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tonase = trim((string)rowv($r, "MAG_STATION", ""));
    $itemCode = trim((string)rowv($r, "ITEM_CODE", ""));
    $itemName = trim((string)rowv($r, "ITEM_NAME", ""));

    if ($tonase == "") {
        $tonase = "*NONE*";
    }

    if ($tonaseFilter != "" && stripos($tonase, $tonaseFilter) === false) {
        continue;
    }

    if ($itemFilter != "") {
        $haystack = $itemCode . " " . $itemName;
        if (stripos($haystack, $itemFilter) === false) {
            continue;
        }
    }

    $cust = trim((string)rowv($r, "CUST_ABBR", ""));
    $capd = floatval(rowv($r, "capd", 0));
    $mc = trim((string)rowv($r, "MAC_CODE", ""));

    $planQty = floatval(rowv($r, "ps", 0));
    $planMcd = ($capd > 0) ? ($planQty / $capd) : 0;

    $ok = floatval(rowv($r, "ok", 0));
    $hold = floatval(rowv($r, "hold", 0));
    $ng = floatval(rowv($r, "ng", 0));
    $actualMcd = floatval(rowv($r, "HITUNG_PD_DATE", 0));
    $ngRw = floatval(rowv($r, "ng_rw", 0));
    $purg = floatval(rowv($r, "purg", 0));
    $price = floatval(rowv($r, "ITEM_COST", 0));

    $totalActual = $ok + $hold + $ng;

    $effPct = ($capd > 0 && $actualMcd > 0) ? ($ok / ($capd * $actualMcd) * 100) : 0;
    $ngPct = ($totalActual > 0) ? ($ng / $totalActual) : 0;

    if (!isset($groups[$tonase])) {
        $groups[$tonase] = array(
            "TONASE" => $tonase,
            "ROWS" => array(),
            "SUM_PLAN" => 0,
            "SUM_PLAN_MCD" => 0,
            "SUM_OK" => 0,
            "SUM_HOLD" => 0,
            "SUM_NG" => 0,
            "SUM_ACT_MCD" => 0,
            "SUM_NG_RW" => 0,
            "SUM_PURG" => 0,
            "SUM_PRICE" => 0
        );
    }

    $row = array(
        "ITEM_CODE" => $itemCode,
        "ITEM_NAME" => $itemName,
        "CUST" => $cust,
        "CAPD" => $capd,
        "MC" => $mc,
        "PLAN_QTY" => $planQty,
        "PLAN_MCD" => $planMcd,
        "OK" => $ok,
        "HOLD" => $hold,
        "NG" => $ng,
        "ACTUAL_MCD" => $actualMcd,
        "NG_RW" => $ngRw,
        "EFF_PCT" => $effPct,
        "NG_PCT" => $ngPct,
        "PURGING" => $purg,
        "PRICE" => $price
    );

    $groups[$tonase]["ROWS"][] = $row;
    $groups[$tonase]["SUM_PLAN"] += $planQty;
    $groups[$tonase]["SUM_PLAN_MCD"] += $planMcd;
    $groups[$tonase]["SUM_OK"] += $ok;
    $groups[$tonase]["SUM_HOLD"] += $hold;
    $groups[$tonase]["SUM_NG"] += $ng;
    $groups[$tonase]["SUM_ACT_MCD"] += $actualMcd;
    $groups[$tonase]["SUM_NG_RW"] += $ngRw;
    $groups[$tonase]["SUM_PURG"] += $purg;
    $groups[$tonase]["SUM_PRICE"] += $price;

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

    $fileName = "MONTHLY_PRODUCTION_INJECTION_" . date("Ym", strtotime($fromDate)) . "_" . date("Ymd_His") . ".xls";

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
        table { border-collapse: collapse; font-family: Calibri, Arial, sans-serif; font-size: 11px; }
        th { background: #d9eaf7; font-weight: bold; text-align: center; border: 1px solid #000000; mso-number-format:"\@"; }
        td { border: 1px solid #000000; padding: 3px; mso-number-format:"\@"; }
        .num { mso-number-format:"0"; text-align: right; }
        .num2 { mso-number-format:"0\.00"; text-align: right; }
        .title { font-size: 16px; font-weight: bold; border: none; }
        .info { border: none; }
        .group { background: #eeeeee; font-weight: bold; }
        .total { background: #f8f8f8; font-weight: bold; }
    </style>
</head>
<body>
<table>
    <tr><td colspan="16" class="title">PT.IMC TEKNO INDONESIA</td></tr>
    <tr><td colspan="16" class="title">MONTHLY PRODUCTION INJECTION REPORT</td></tr>
    <tr><td colspan="16" class="info">Month : <?php echo h($monthLong); ?> | Export Date : <?php echo h($printDate); ?></td></tr>
    <tr><td colspan="16" class="info"></td></tr>

    <tr>
        <th rowspan="2">NO</th>
        <th rowspan="2">ITEM</th>
        <th rowspan="2">NAME</th>
        <th rowspan="2">CUST</th>
        <th rowspan="2">CAPD</th>
        <th rowspan="2">MC</th>
        <th colspan="2">PROD PLAN</th>
        <th colspan="6">PROD AKTUAL</th>
        <th rowspan="2">%NG</th>
        <th rowspan="2">PURGING PRICE</th>
    </tr>
    <tr>
        <th>QTY</th>
        <th>MCD</th>
        <th>OK</th>
        <th>HOLD</th>
        <th>NG</th>
        <th>MCD</th>
        <th>NG_RW</th>
        <th>%EFF</th>
    </tr>

    <?php foreach ($groups as $g) { ?>
        <tr class="group">
            <td colspan="16">TONASE : <?php echo h($g["TONASE"]); ?></td>
        </tr>
        <?php $no = 1; ?>
        <?php foreach ($g["ROWS"] as $r) { ?>
            <tr>
                <td class="num"><?php echo h($no); ?></td>
                <td><?php echo h($r["ITEM_CODE"]); ?></td>
                <td><?php echo h($r["ITEM_NAME"]); ?></td>
                <td><?php echo h($r["CUST"]); ?></td>
                <td class="num"><?php echo h(excel_num($r["CAPD"], 0)); ?></td>
                <td><?php echo h($r["MC"]); ?></td>
                <td class="num"><?php echo h(excel_num($r["PLAN_QTY"], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["PLAN_MCD"], 2)); ?></td>
                <td class="num"><?php echo h(excel_num($r["OK"], 0)); ?></td>
                <td class="num"><?php echo h(excel_num($r["HOLD"], 0)); ?></td>
                <td class="num"><?php echo h(excel_num($r["NG"], 0)); ?></td>
                <td class="num"><?php echo h(excel_num($r["ACTUAL_MCD"], 0)); ?></td>
                <td class="num"><?php echo h(excel_num($r["NG_RW"], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["EFF_PCT"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["NG_PCT"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($r["PURGING"], 2)); ?></td>
            </tr>
            <?php $no++; ?>
        <?php } ?>
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
    <title>Monthly Production Injection Report</title>

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
            font-size: 20px;
            line-height: 24px;
        }

        .title {
            font-weight: bold;
            font-size: 19px;
            line-height: 24px;
            letter-spacing: 1px;
        }

        .month {
            font-weight: bold;
            font-size: 19px;
            line-height: 22px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 12px;
            margin-top: 5px;
        }

        table.report th,
        table.report td {
            border: none;
            padding: 2px 3px;
            vertical-align: top;
            line-height: 15px;
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

        .tonase-row td {
            font-weight: bold;
            padding-top: 12px;
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
                font-size: 9px;
            }

            table.report th,
            table.report td {
                padding: 1px 2px;
                line-height: 11px;
            }
        }
    </style>
</head>
<body>

<div class="filter">
    <form method="get">
        <b>Monthly Injection</b>
        &nbsp;&nbsp;
        Month:
        <input type="month" name="month" value="<?php echo h($monthInput); ?>">
        &nbsp;
        Tonase:
        <input type="text" name="tonase" value="<?php echo h($tonaseFilter); ?>" list="tonaseList" placeholder="ALL TONASE" style="width:120px;">
        <datalist id="tonaseList">
            <?php foreach ($tonaseList as $tv) { ?>
                <option value="<?php echo h($tv); ?>">
            <?php } ?>
        </datalist>
        &nbsp;
        Item:
        <input type="text" name="item" value="<?php echo h($itemFilter); ?>" placeholder="ALL ITEM" style="width:180px;">
        &nbsp;
        <button type="submit" class="btn">FILTER</button>
        <a href="monthly_injection.php?month=<?php echo h($monthInput); ?>" class="btn">ALL</a>
        <button type="submit" name="export" value="excel" class="btn">EXPORT EXCEL</button>
    </form>
</div>

<div class="toolbar">
    <button type="button" class="btn" onclick="window.print()">PRINT</button>
    <button type="button" class="btn" onclick="window.location.href='dashboard_ppic.php'">CLOSE</button>
</div>

<div class="page">
    <div class="company">PT.IMC TEKNO INDONESIA</div>
    <div class="title">MONTHLY PRODUCTION INJECTION REPORT</div>
    <div class="month"><?php echo h($monthLong); ?></div>

    <?php if ($totalRows == 0) { ?>
        <div class="no-data">Data injection tidak ditemukan.</div>
    <?php } else { ?>
        <table class="report">
            <colgroup>
                <col style="width:4%;">
                <col style="width:8%;">
                <col style="width:25%;">
                <col style="width:5%;">
                <col style="width:6%;">
                <col style="width:4%;">
                <col style="width:7%;">
                <col style="width:5%;">
                <col style="width:5%;">
                <col style="width:5%;">
                <col style="width:5%;">
                <col style="width:5%;">
                <col style="width:5%;">
                <col style="width:7%;">
                <col style="width:5%;">
                <col style="width:6%;">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2">NO</th>
                    <th rowspan="2">ITEM</th>
                    <th rowspan="2">NAME</th>
                    <th rowspan="2">CUST</th>
                    <th rowspan="2">CAPD</th>
                    <th rowspan="2">MC</th>
                    <th colspan="2">PROD PLAN</th>
                    <th colspan="6">PROD AKTUAL</th>
                    <th rowspan="2">%NG</th>
                    <th rowspan="2">PURGING&nbsp; PRICE</th>
                </tr>
                <tr>
                    <th>QTY</th>
                    <th>MCD</th>
                    <th>OK</th>
                    <th>HOLD</th>
                    <th>NG</th>
                    <th>MCD</th>
                    <th>NG_RW</th>
                    <th>%EFF</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $g) { ?>
                    <tr class="tonase-row">
                        <td colspan="16">TONASE : <?php echo h($g["TONASE"]); ?></td>
                    </tr>

                    <?php $no = 1; ?>
                    <?php foreach ($g["ROWS"] as $r) { ?>
                        <tr>
                            <td class="num"><?php echo h($no); ?></td>
                            <td><?php echo h($r["ITEM_CODE"]); ?></td>
                            <td><?php echo h($r["ITEM_NAME"]); ?></td>
                            <td><?php echo h($r["CUST"]); ?></td>
                            <td class="num"><?php echo h(n0($r["CAPD"])); ?></td>
                            <td><?php echo h($r["MC"]); ?></td>
                            <td class="num"><?php echo h(n0($r["PLAN_QTY"])); ?></td>
                            <td class="num"><?php echo h(n2($r["PLAN_MCD"])); ?></td>
                            <td class="num"><?php echo h(n0($r["OK"])); ?></td>
                            <td class="num"><?php echo h(n0($r["HOLD"])); ?></td>
                            <td class="num"><?php echo h(n0($r["NG"])); ?></td>
                            <td class="num"><?php echo h(n0($r["ACTUAL_MCD"])); ?></td>
                            <td class="num"><?php echo h(n0($r["NG_RW"])); ?></td>
                            <td class="num"><?php echo h(n2($r["EFF_PCT"])); ?></td>
                            <td class="num"><?php echo h(n2($r["NG_PCT"])); ?></td>
                            <td class="num"><?php echo h(n2($r["PURGING"])); ?></td>
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