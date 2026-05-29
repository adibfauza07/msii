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

function month_title($date, $addMonth) {
    return date("M-y", strtotime("+" . intval($addMonth) . " month", strtotime($date)));
}

function itty_name($code) {
    $code = trim((string)$code);

    $names = array(
        "01" => "01 - PART / COMPONENT",
        "02" => "02 - MATERIAL / RESIN",
        "03" => "03 - SUB MATERIAL",
        "04" => "04 - PACKING",
        "05" => "05 - OTHER MATERIAL",
        "06" => "06 - RUNNER / RECYCLE"
    );

    if (isset($names[$code])) {
        return $names[$code];
    }

    if ($code == "") {
        return "UNKNOWN";
    }

    return $code;
}

$monthInput = get_value("month", date("Y-m"));
$ittyFilter = get_value("itty", "");
$matFilter = get_value("mat", "");
$export = strtolower(get_value("export", ""));

$startDate = $monthInput . "-01";
if (strtotime($startDate) === false) {
    $monthInput = date("Y-m");
    $startDate = date("Y-m-01");
}

$printDate = date("d-M-Y H:i:s");
$m1 = month_title($startDate, 0);
$m2 = month_title($startDate, 1);
$m3 = month_title($startDate, 2);
$monthLong = date("F Y", strtotime($startDate));

/* ======================================================
   LOAD DATA DARI SP MRP
====================================================== */
$sql = "EXEC dbo.sp_mrp ?";
$stmt = sqlsrv_query($conn, $sql, array($startDate));

if ($stmt === false) {
    die("<pre>Query sp_mrp error:\n" . sql_error_text() . "</pre>");
}

$groups = array();
$totalRows = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $itty = isset($r["ITTY_CODE"]) ? trim((string)$r["ITTY_CODE"]) : "";
    $matCode = isset($r["MAT_CODE"]) ? trim((string)$r["MAT_CODE"]) : "";
    $matName = isset($r["MAT_NAME"]) ? trim((string)$r["MAT_NAME"]) : "";

    if ($ittyFilter != "" && stripos($itty, $ittyFilter) === false) {
        continue;
    }

    if ($matFilter != "") {
        $haystack = $matCode . " " . $matName;
        if (stripos($haystack, $matFilter) === false) {
            continue;
        }
    }

    $partCode = isset($r["PART_CODE"]) ? trim((string)$r["PART_CODE"]) : "";
    $partName = isset($r["PART_NAME"]) ? trim((string)$r["PART_NAME"]) : "";
    $unit = isset($r["UNIT"]) ? trim((string)$r["UNIT"]) : "";
    $partUnit = isset($r["PART_UNIT"]) ? trim((string)$r["PART_UNIT"]) : "";

    $qty = isset($r["QTY"]) ? floatval($r["QTY"]) : 0;
    $cavity = isset($r["PART_CAVT"]) ? floatval($r["PART_CAVT"]) : 0;
    $partWeight = isset($r["PART_WEIGHT"]) ? floatval($r["PART_WEIGHT"]) : 0;
    $runnerWeight = isset($r["PART_RWEIGHT"]) ? floatval($r["PART_RWEIGHT"]) : 0;
    $totalWeight = isset($r["PART_TWEIGHT"]) ? floatval($r["PART_TWEIGHT"]) : 0;
    $netWeight = isset($r["PART_NWEIGHT"]) ? floatval($r["PART_NWEIGHT"]) : 0;
    $rcly = isset($r["PART_RCLY"]) ? floatval($r["PART_RCLY"]) : 0;

    $pp1 = isset($r["PP1"]) ? floatval($r["PP1"]) : 0;
    $pp2 = isset($r["PP2"]) ? floatval($r["PP2"]) : 0;
    $pp3 = isset($r["PP3"]) ? floatval($r["PP3"]) : 0;

    $use1 = isset($r["MAT_USE1"]) ? floatval($r["MAT_USE1"]) : 0;
    $use2 = isset($r["MAT_USE2"]) ? floatval($r["MAT_USE2"]) : 0;
    $use3 = isset($r["MAT_USE3"]) ? floatval($r["MAT_USE3"]) : 0;

    $bbal = isset($r["BBAL"]) ? floatval($r["BBAL"]) : 0;
    $oe1 = isset($r["OE1"]) ? floatval($r["OE1"]) : 0;
    $oe2 = isset($r["OE2"]) ? floatval($r["OE2"]) : 0;
    $oe3 = isset($r["OE3"]) ? floatval($r["OE3"]) : 0;
    $matSafety = isset($r["MAT_SAFETY"]) ? floatval($r["MAT_SAFETY"]) : 0;
    $matPack = isset($r["MAT_PACK"]) ? floatval($r["MAT_PACK"]) : 0;

    $ittyKey = ($itty == "") ? "ZZ" : $itty;
    $matKey = $matCode . "|" . $matName;

    if (!isset($groups[$ittyKey])) {
        $groups[$ittyKey] = array(
            "ITTY_CODE" => $itty,
            "ITTY_NAME" => itty_name($itty),
            "MATERIALS" => array(),
            "TOTAL_PP1" => 0,
            "TOTAL_PP2" => 0,
            "TOTAL_PP3" => 0,
            "TOTAL_USE1" => 0,
            "TOTAL_USE2" => 0,
            "TOTAL_USE3" => 0
        );
    }

    if (!isset($groups[$ittyKey]["MATERIALS"][$matKey])) {
        $groups[$ittyKey]["MATERIALS"][$matKey] = array(
            "MAT_CODE" => $matCode,
            "MAT_NAME" => $matName,
            "MAT_SAFETY" => $matSafety,
            "MAT_PACK" => $matPack,
            "BBAL" => $bbal,
            "OE1" => $oe1,
            "OE2" => $oe2,
            "OE3" => $oe3,
            "ROWS" => array(),
            "SUM_PP1" => 0,
            "SUM_PP2" => 0,
            "SUM_PP3" => 0,
            "SUM_USE1" => 0,
            "SUM_USE2" => 0,
            "SUM_USE3" => 0
        );
    }

    $row = array(
        "PART_CODE" => $partCode,
        "PART_NAME" => $partName,
        "CAVITY" => $cavity,
        "PART_WEIGHT" => $partWeight,
        "RUNNER_WEIGHT" => $runnerWeight,
        "TOTAL_WEIGHT" => $totalWeight,
        "NET_WEIGHT" => $netWeight,
        "RCLY" => $rcly,
        "QTY" => $qty,
        "UNIT" => $unit,
        "PART_UNIT" => $partUnit,
        "PP1" => $pp1,
        "PP2" => $pp2,
        "PP3" => $pp3,
        "USE1" => $use1,
        "USE2" => $use2,
        "USE3" => $use3
    );

    $groups[$ittyKey]["MATERIALS"][$matKey]["ROWS"][] = $row;

    $groups[$ittyKey]["MATERIALS"][$matKey]["SUM_PP1"] += $pp1;
    $groups[$ittyKey]["MATERIALS"][$matKey]["SUM_PP2"] += $pp2;
    $groups[$ittyKey]["MATERIALS"][$matKey]["SUM_PP3"] += $pp3;
    $groups[$ittyKey]["MATERIALS"][$matKey]["SUM_USE1"] += $use1;
    $groups[$ittyKey]["MATERIALS"][$matKey]["SUM_USE2"] += $use2;
    $groups[$ittyKey]["MATERIALS"][$matKey]["SUM_USE3"] += $use3;

    $groups[$ittyKey]["TOTAL_PP1"] += $pp1;
    $groups[$ittyKey]["TOTAL_PP2"] += $pp2;
    $groups[$ittyKey]["TOTAL_PP3"] += $pp3;
    $groups[$ittyKey]["TOTAL_USE1"] += $use1;
    $groups[$ittyKey]["TOTAL_USE2"] += $use2;
    $groups[$ittyKey]["TOTAL_USE3"] += $use3;

    $totalRows++;
}

ksort($groups);

foreach ($groups as $ittyKey => $g) {
    ksort($groups[$ittyKey]["MATERIALS"]);
}

/* ======================================================
   EXPORT EXCEL
====================================================== */
if ($export == "excel") {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $fileName = "MRP_" . date("Ym", strtotime($startDate)) . "_" . date("Ymd_His") . ".xls";

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
        .group1 { background: #cfe2f3; font-weight: bold; }
        .mat { background: #eeeeee; font-weight: bold; }
        .total { background: #f8f8f8; font-weight: bold; }
    </style>
</head>
<body>
<table>
    <tr><td colspan="20" class="title">P.T. IMC TEKNO INDONESIA</td></tr>
    <tr><td colspan="20" class="title">MATERIAL REQUIREMENT PLANNING</td></tr>
    <tr><td colspan="20" class="info">Month : <?php echo h($monthLong); ?> | Export Date : <?php echo h($printDate); ?></td></tr>
    <tr><td colspan="20" class="info"></td></tr>

    <tr>
        <th rowspan="2">ITTY_CODE</th>
        <th rowspan="2">MAT_CODE</th>
        <th rowspan="2">MAT_NAME</th>
        <th rowspan="2">PART_CODE</th>
        <th rowspan="2">PART_NAME</th>
        <th colspan="1">Cavity</th>
        <th colspan="3">Weight</th>
        <th rowspan="2">Rcly (%)</th>
        <th rowspan="2">Std.Used</th>
        <th rowspan="2">Unit</th>
        <th colspan="2"><?php echo h($m1); ?></th>
        <th colspan="2"><?php echo h($m2); ?></th>
        <th colspan="2"><?php echo h($m3); ?></th>
        <th rowspan="2">Safety</th>
        <th rowspan="2">Pack</th>
    </tr>
    <tr>
        <th>Part</th>
        <th>Part</th>
        <th>Runner</th>
        <th>Total</th>
        <th>P.Plan</th>
        <th>M.Used</th>
        <th>P.Plan</th>
        <th>M.Used</th>
        <th>P.Plan</th>
        <th>M.Used</th>
    </tr>

    <?php foreach ($groups as $g) { ?>
        <tr class="group1">
            <td colspan="20"><?php echo h($g["ITTY_NAME"]); ?></td>
        </tr>

        <?php foreach ($g["MATERIALS"] as $mat) { ?>
            <tr class="mat">
                <td><?php echo h($g["ITTY_CODE"]); ?></td>
                <td><?php echo h($mat["MAT_CODE"]); ?></td>
                <td colspan="17"><?php echo h($mat["MAT_NAME"]); ?></td>
            </tr>

            <?php foreach ($mat["ROWS"] as $r) { ?>
                <tr>
                    <td><?php echo h($g["ITTY_CODE"]); ?></td>
                    <td><?php echo h($mat["MAT_CODE"]); ?></td>
                    <td><?php echo h($mat["MAT_NAME"]); ?></td>
                    <td><?php echo h($r["PART_CODE"]); ?></td>
                    <td><?php echo h($r["PART_NAME"]); ?></td>
                    <td class="num"><?php echo h(excel_num($r["CAVITY"], 0)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["PART_WEIGHT"], 2)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["RUNNER_WEIGHT"], 2)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["TOTAL_WEIGHT"], 2)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["RCLY"], 2)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["QTY"], 2)); ?></td>
                    <td><?php echo h($r["UNIT"]); ?></td>
                    <td class="num"><?php echo h(excel_num($r["PP1"], 0)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["USE1"], 2)); ?></td>
                    <td class="num"><?php echo h(excel_num($r["PP2"], 0)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["USE2"], 2)); ?></td>
                    <td class="num"><?php echo h(excel_num($r["PP3"], 0)); ?></td>
                    <td class="num2"><?php echo h(excel_num($r["USE3"], 2)); ?></td>
                    <td class="num2"><?php echo h(excel_num($mat["MAT_SAFETY"], 2)); ?></td>
                    <td class="num2"><?php echo h(excel_num($mat["MAT_PACK"], 2)); ?></td>
                </tr>
            <?php } ?>

            <tr class="total">
                <td colspan="12">TOTAL MATERIAL <?php echo h($mat["MAT_CODE"]); ?></td>
                <td class="num"><?php echo h(excel_num($mat["SUM_PP1"], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($mat["SUM_USE1"], 2)); ?></td>
                <td class="num"><?php echo h(excel_num($mat["SUM_PP2"], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($mat["SUM_USE2"], 2)); ?></td>
                <td class="num"><?php echo h(excel_num($mat["SUM_PP3"], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($mat["SUM_USE3"], 2)); ?></td>
                <td></td>
                <td></td>
            </tr>
        <?php } ?>

        <tr class="total">
            <td colspan="12">TOTAL ITTY <?php echo h($g["ITTY_CODE"]); ?></td>
            <td class="num"><?php echo h(excel_num($g["TOTAL_PP1"], 0)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["TOTAL_USE1"], 2)); ?></td>
            <td class="num"><?php echo h(excel_num($g["TOTAL_PP2"], 0)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["TOTAL_USE2"], 2)); ?></td>
            <td class="num"><?php echo h(excel_num($g["TOTAL_PP3"], 0)); ?></td>
            <td class="num2"><?php echo h(excel_num($g["TOTAL_USE3"], 2)); ?></td>
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
    <title>Material Requirement Planning</title>

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
            max-width: 1130px;
            margin: 8px auto;
            background: #d4d0c8;
            border: 1px solid #777777;
            padding: 8px;
            box-sizing: border-box;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            white-space: nowrap;
        }

        .filter input,
        .filter select {
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
            max-width: 1130px;
            margin: 0 auto 6px auto;
            text-align: right;
        }

        .page {
            width: calc(100% - 20px);
            max-width: 1130px;
            min-height: 780px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 18px 16px;
            border: 2px solid #000000;
            box-sizing: border-box;
        }

        .header {
            position: relative;
            min-height: 82px;
            border-bottom: 1px solid #000000;
        }

        .company {
            position: absolute;
            left: 0;
            top: 0;
            font-size: 14px;
        }

        .dept {
            position: absolute;
            left: 0;
            top: 18px;
            font-size: 11px;
        }

        .title {
            text-align: center;
            font-size: 24px;
            line-height: 26px;
            padding-top: 14px;
        }

        .subtitle {
            text-align: center;
            font-size: 12px;
        }

        .right-info {
            position: absolute;
            right: 0;
            top: 38px;
            text-align: right;
            font-size: 12px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 10px;
            margin-top: 6px;
        }

        table.report th,
        table.report td {
            border: none;
            padding: 1px 3px;
            vertical-align: top;
            line-height: 12px;
            overflow: hidden;
        }

        table.report th {
            text-align: center;
            font-weight: normal;
            border-bottom: 1px solid #000000;
        }

        table.report td.num {
            text-align: right;
            white-space: nowrap;
        }

        .group1 td {
            font-weight: bold;
            border-top: 2px solid #0000ff;
            border-bottom: 2px solid #0000ff;
            color: #000000;
            padding-top: 3px;
            padding-bottom: 3px;
        }

        .mat-row td {
            font-weight: bold;
            padding-top: 4px;
        }

        .part-row td {
            font-weight: normal;
        }

        .total-row td {
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
        <b>MRP Report</b>
        &nbsp;&nbsp;
        Month:
        <input type="month" name="month" value="<?php echo h($monthInput); ?>">
        &nbsp;
        ITTY:
        <select name="itty">
            <option value="">ALL</option>
            <option value="01" <?php echo ($ittyFilter == "01") ? "selected" : ""; ?>>01</option>
            <option value="02" <?php echo ($ittyFilter == "02") ? "selected" : ""; ?>>02</option>
            <option value="03" <?php echo ($ittyFilter == "03") ? "selected" : ""; ?>>03</option>
            <option value="04" <?php echo ($ittyFilter == "04") ? "selected" : ""; ?>>04</option>
            <option value="05" <?php echo ($ittyFilter == "05") ? "selected" : ""; ?>>05</option>
            <option value="06" <?php echo ($ittyFilter == "06") ? "selected" : ""; ?>>06</option>
        </select>
        &nbsp;
        Material:
        <input type="text" name="mat" value="<?php echo h($matFilter); ?>" placeholder="MAT code / name" style="width:180px;">
        &nbsp;
        <button type="submit" class="btn">FILTER</button>
        <a href="mrp.php" class="btn">CURRENT</a>
        <button type="submit" name="export" value="excel" class="btn">EXPORT EXCEL</button>
    </form>
</div>

<div class="toolbar">
    <button type="button" class="btn" onclick="window.print()">PRINT</button>
    <button type="button" class="btn" onclick="window.location.href='dashboard_ppic.php'">CLOSE</button>
</div>

<div class="page">
    <div class="header">
        <div class="company">PT. IMC TEKNO INDONESIA</div>
        <div class="dept">PPIC Departement</div>
        <div class="title">Material Requirement Planning</div>
        <div class="subtitle">(Planning)</div>
        <div class="right-info">
            Page 1 of 1<br>
            Print Date : <?php echo h($printDate); ?>
        </div>
    </div>

    <?php if ($totalRows == 0) { ?>
        <div class="no-data">Data MRP tidak ditemukan.</div>
    <?php } else { ?>
        <table class="report">
            <colgroup>
                <col style="width:6%;">
                <col style="width:24%;">
                <col style="width:5%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:4%;">
                <col style="width:5%;">
                <col style="width:4%;">
                <col style="width:7%;">
                <col style="width:6%;">
                <col style="width:6%;">
                <col style="width:6%;">
                <col style="width:6%;">
                <col style="width:6%;">
                <col style="width:6%;">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2">CODE</th>
                    <th rowspan="2">PART NAME</th>
                    <th>Cavity</th>
                    <th colspan="3">Weight</th>
                    <th>Rcly</th>
                    <th colspan="2"></th>
                    <th colspan="2"><?php echo h($m1); ?></th>
                    <th colspan="2"><?php echo h($m2); ?></th>
                    <th colspan="2"><?php echo h($m3); ?></th>
                </tr>
                <tr>
                    <th>Part</th>
                    <th>Part</th>
                    <th>Runner</th>
                    <th>Total</th>
                    <th>(%)</th>
                    <th>Std.Used</th>
                    <th></th>
                    <th>P.Plan</th>
                    <th>M.Used</th>
                    <th>P.Plan</th>
                    <th>M.Used</th>
                    <th>P.Plan</th>
                    <th>M.Used</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $g) { ?>
                    <tr class="group1">
                        <td colspan="17"><?php echo h($g["ITTY_CODE"]); ?></td>
                    </tr>

                    <?php foreach ($g["MATERIALS"] as $mat) { ?>
                        <tr class="mat-row">
                            <td><?php echo h($mat["MAT_CODE"]); ?></td>
                            <td><?php echo h($mat["MAT_NAME"]); ?></td>
                            <td colspan="15"></td>
                        </tr>

                        <?php foreach ($mat["ROWS"] as $r) { ?>
                            <tr class="part-row">
                                <td><?php echo h($r["PART_CODE"]); ?></td>
                                <td><?php echo h($r["PART_NAME"]); ?></td>
                                <td class="num"><?php echo h(n0($r["CAVITY"])); ?></td>
                                <td class="num"><?php echo h(n2($r["PART_WEIGHT"])); ?></td>
                                <td class="num"><?php echo h(n2($r["RUNNER_WEIGHT"])); ?></td>
                                <td class="num"><?php echo h(n2($r["TOTAL_WEIGHT"])); ?></td>
                                <td class="num"><?php echo h(n2($r["RCLY"])); ?></td>
                                <td class="num"><?php echo h(n2($r["QTY"])); ?></td>
                                <td><?php echo h($r["UNIT"]); ?></td>
                                <td class="num"><?php echo h(n0($r["PP1"])); ?></td>
                                <td class="num"><?php echo h(n2($r["USE1"])); ?></td>
                                <td class="num"><?php echo h(n0($r["PP2"])); ?></td>
                                <td class="num"><?php echo h(n2($r["USE2"])); ?></td>
                                <td class="num"><?php echo h(n0($r["PP3"])); ?></td>
                                <td class="num"><?php echo h(n2($r["USE3"])); ?></td>
                            </tr>
                        <?php } ?>

                        <tr class="total-row">
                            <td colspan="9"></td>
                            <td class="num"><?php echo h(n0($mat["SUM_PP1"])); ?></td>
                            <td class="num"><?php echo h(n2($mat["SUM_USE1"])); ?></td>
                            <td class="num"><?php echo h(n0($mat["SUM_PP2"])); ?></td>
                            <td class="num"><?php echo h(n2($mat["SUM_USE2"])); ?></td>
                            <td class="num"><?php echo h(n0($mat["SUM_PP3"])); ?></td>
                            <td class="num"><?php echo h(n2($mat["SUM_USE3"])); ?></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</div>

</body>
</html>
