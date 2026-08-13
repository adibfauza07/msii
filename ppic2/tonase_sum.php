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

function xls_num($value, $dec) {
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

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

$monthInput = get_value("month", date("Y-m"));
$customerInput = get_value("customer", "");
$export = get_value("export", "");
$startDate = $monthInput . "-01";

$ts = strtotime($startDate);
if ($ts === false) {
    $startDate = date("Y-m-01");
    $monthInput = date("Y-m");
}

$printDate = date("d-M-Y H:i:s");
$m1 = month_title($startDate, 0);
$m2 = month_title($startDate, 1);
$m3 = month_title($startDate, 2);

/* ======================================================
   LOAD DATA DARI SP_MPS3MONTH_EX
====================================================== */
$sql = "EXEC dbo.SP_MPS3MONTH_EX ?";
$stmt = sqlsrv_query($conn, $sql, array($startDate));

if ($stmt === false) {
    die("<pre>Query SP_MPS3MONTH_EX error:\n" . sql_error_text() . "</pre>");
}

$groups = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $process = trim((string)$r["PROCESS"]);
    $station = trim((string)$r["ST"]); // Tonase
    $cust = isset($r["CUST_ABBR"]) ? trim((string)$r["CUST_ABBR"]) : "";

    if ($process == "") {
        $process = "*NONE*";
    }

    if ($station == "") {
        $station = "*NONE*";
    }

    $groupKey = $station;

    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = array(
            "PROCESS" => $process,
            "ST" => $station,
            "SUM_MCD1" => 0,
            "SUM_MCD2" => 0,
            "SUM_MCD3" => 0,
            "MAC_AVAIL" => 0,
            "MMDAY1" => 0,
            "MMDAY2" => 0,
            "MMDAY3" => 0,
            "HAS_DATA" => false
        );
    }

    /*
       Ambil Data Kapasitas Mesin (MAC_AVAIL & MMDAY) 
       TIDAK dipengaruhi filter customer agar total ketersediaan mesin valid per Tonase.
    */
    if ($groups[$groupKey]["MAC_AVAIL"] <= 0 && isset($r["QTY"]) && floatval($r["QTY"]) > 0) {
        $groups[$groupKey]["MAC_AVAIL"] = floatval($r["QTY"]);
    }
    if ($groups[$groupKey]["MMDAY1"] <= 0 && isset($r["MMDAY1"]) && floatval($r["MMDAY1"]) > 0) {
        $groups[$groupKey]["MMDAY1"] = floatval($r["MMDAY1"]);
    }
    if ($groups[$groupKey]["MMDAY2"] <= 0 && isset($r["MMDAY2"]) && floatval($r["MMDAY2"]) > 0) {
        $groups[$groupKey]["MMDAY2"] = floatval($r["MMDAY2"]);
    }
    if ($groups[$groupKey]["MMDAY3"] <= 0 && isset($r["MMDAY3"]) && floatval($r["MMDAY3"]) > 0) {
        $groups[$groupKey]["MMDAY3"] = floatval($r["MMDAY3"]);
    }

    /*
       Terapkan Filter Customer.
       Jika customer diisi dan tidak cocok, skip perhitungan MCD untuk row ini.
    */
    if ($customerInput !== "" && stripos($cust, $customerInput) === false) {
        continue;
    }

    // Hanya hitung beban MCD milik Customer yang sesuai filter
    $mcd1 = isset($r["MCD1"]) ? floatval($r["MCD1"]) : 0;
    $mcd2 = isset($r["MCD2"]) ? floatval($r["MCD2"]) : 0;
    $mcd3 = isset($r["MCD3"]) ? floatval($r["MCD3"]) : 0;

    $groups[$groupKey]["SUM_MCD1"] += $mcd1;
    $groups[$groupKey]["SUM_MCD2"] += $mcd2;
    $groups[$groupKey]["SUM_MCD3"] += $mcd3;
    $groups[$groupKey]["HAS_DATA"] = true;
}

// Bersihkan Tonase/Mesin yang tidak memiliki data/beban sama sekali untuk customer terkait
if ($customerInput !== "") {
    foreach ($groups as $key => $g) {
        if (!$g["HAS_DATA"]) {
            unset($groups[$key]);
        }
    }
}

$totalGroup = count($groups);

/* ======================================================
   EXPORT EXCEL (HANYA SUMMARY)
====================================================== */
if ($export == "excel") {
    $fileName = "MPS_3MONTH_SUMMARY_" . date("Ym", strtotime($startDate)) . ".xls";

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
            th { background: #d9eaf7; font-weight: bold; text-align: center; border: 1px solid #777777; padding: 4px; }
            td { border: 1px solid #999999; mso-number-format:"\@"; padding: 2px 4px; }
            .num { mso-number-format:"0"; text-align: right; }
            .num2 { mso-number-format:"0\.00"; text-align: right; }
            .group { background: #eeeeee; font-weight: bold; }
            .title { font-size: 16px; font-weight: bold; text-align: center; }
            .subtitle { font-size: 12px; text-align: center; }
        </style>
    </head>
    <body>
        <table>
            <tr>
                <td colspan="23" class="title">MASTER PRODUCTION SCHEDULE (SUMMARY BY TONASE)</td>
            </tr>
            <tr>
                <td colspan="23" class="subtitle">
                    Start Month: <?php echo h(date("F Y", strtotime($startDate))); ?> | 
                    Customer: <?php echo h($customerInput !== "" ? $customerInput : "ALL"); ?> | 
                    Print Date: <?php echo h($printDate); ?>
                </td>
            </tr>
            <tr><td colspan="23"></td></tr>
            <tr>
                <th colspan="10">TONASE / STATION SUMMARY</th>
                <th colspan="4"><?php echo h($m1); ?></th>
                <th colspan="4"><?php echo h($m2); ?></th>
                <th colspan="4"><?php echo h($m3); ?></th>
                <th>REMARKS</th>
            </tr>

            <?php foreach ($groups as $groupKey => $g) { 
                $avail = floatval($g["MAC_AVAIL"]);
                $sumMcd1 = floatval($g["SUM_MCD1"]);
                $sumMcd2 = floatval($g["SUM_MCD2"]);
                $sumMcd3 = floatval($g["SUM_MCD3"]);
                $mmday1 = floatval($g["MMDAY1"]);
                $mmday2 = floatval($g["MMDAY2"]);
                $mmday3 = floatval($g["MMDAY3"]);

                $req1 = ($mmday1 > 0) ? ($sumMcd1 / $mmday1) : 0;
                $req2 = ($mmday2 > 0) ? ($sumMcd2 / $mmday2) : 0;
                $req3 = ($mmday3 > 0) ? ($sumMcd3 / $mmday3) : 0;
                $mav1 = $avail * $mmday1;
                $mav2 = $avail * $mmday2;
                $mav3 = $avail * $mmday3;
                $bal1 = $avail - $req1;
                $bal2 = $avail - $req2;
                $bal3 = $avail - $req3;
                $balm1 = $mav1 - $sumMcd1;
                $balm2 = $mav2 - $sumMcd2;
                $balm3 = $mav3 - $sumMcd3;
                $per1 = ($mav1 > 0) ? ($sumMcd1 / $mav1 * 100) : 0;
                $per2 = ($mav2 > 0) ? ($sumMcd2 / $mav2 * 100) : 0;
                $per3 = ($mav3 > 0) ? ($sumMcd3 / $mav3 * 100) : 0;
            ?>
                <tr class="group">
                    <td colspan="10">TONASE : <?php echo h($g["ST"]); ?> - <?php echo h($g["PROCESS"]); ?></td>
                    <td colspan="13"></td>
                </tr>
                <tr>
                    <td colspan="10">Num. of Machine</td>
                    <td>Unit</td><td></td><td></td><td class="num"><?php echo h(xls_num($mmday1, 0)); ?></td>
                    <td>Unit</td><td></td><td></td><td class="num"><?php echo h(xls_num($mmday2, 0)); ?></td>
                    <td>Unit</td><td></td><td></td><td class="num"><?php echo h(xls_num($mmday3, 0)); ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="10">Machine Requirement</td>
                    <td class="num2"><?php echo h(xls_num($req1, 2)); ?></td><td></td><td></td><td></td>
                    <td class="num2"><?php echo h(xls_num($req2, 2)); ?></td><td></td><td></td><td></td>
                    <td class="num2"><?php echo h(xls_num($req3, 2)); ?></td><td></td><td></td><td></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="10">Machine Available</td>
                    <td class="num2"><?php echo h(xls_num($avail, 2)); ?></td><td></td><td></td><td class="num2"><?php echo h(xls_num($mav1, 2)); ?></td>
                    <td class="num2"><?php echo h(xls_num($avail, 2)); ?></td><td></td><td></td><td class="num2"><?php echo h(xls_num($mav2, 2)); ?></td>
                    <td class="num2"><?php echo h(xls_num($avail, 2)); ?></td><td></td><td></td><td class="num2"><?php echo h(xls_num($mav3, 2)); ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="10">Balance</td>
                    <td class="num2"><?php echo h(xls_num($bal1, 2)); ?></td><td></td><td></td><td class="num2"><?php echo h(xls_num($balm1, 2)); ?></td>
                    <td class="num2"><?php echo h(xls_num($bal2, 2)); ?></td><td></td><td></td><td class="num2"><?php echo h(xls_num($balm2, 2)); ?></td>
                    <td class="num2"><?php echo h(xls_num($bal3, 2)); ?></td><td></td><td></td><td class="num2"><?php echo h(xls_num($balm3, 2)); ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="10">Percentage</td>
                    <td colspan="4" class="num2"><?php echo h(xls_num($per1, 2)); ?>%</td>
                    <td colspan="4" class="num2"><?php echo h(xls_num($per2, 2)); ?>%</td>
                    <td colspan="4" class="num2"><?php echo h(xls_num($per3, 2)); ?>%</td>
                    <td></td>
                </tr>
                <tr><td colspan="23"></td></tr>
            <?php } ?>
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
    <title>MPS Summary by Tonase</title>

    <style>
        html, body {
            margin: 0; padding: 0; background: #9c9c9c;
            color: #000000; font-family: "Courier New", Courier, monospace; font-size: 8px;
        }
        .filter {
            width: calc(100% - 24px); max-width: 1120px; margin: 8px auto;
            background: #d4d0c8; border: 1px solid #777777; padding: 8px;
            box-sizing: border-box; font-family: Tahoma, Arial, sans-serif; font-size: 12px;
        }
        .filter input {
            height: 24px; border: 1px solid #777777; padding: 2px 5px;
            font-family: Tahoma, Arial, sans-serif; font-size: 12px; box-sizing: border-box;
        }
        .btn {
            height: 26px; padding: 2px 12px; border: 1px solid #777777; background: #eeeeee;
            cursor: pointer; font-family: Tahoma, Arial, sans-serif; font-size: 12px;
            color: #000000; text-decoration: none; box-sizing: border-box; display: inline-block;
        }
        .toolbar {
            width: calc(100% - 24px); max-width: 1120px; margin: 0 auto 6px auto; text-align: right;
        }
        .page {
            width: calc(100% - 24px); max-width: 1120px; min-height: 760px; margin: 0 auto 20px auto;
            background: #ffffff; padding: 14px; border: 1px solid #000000; box-sizing: border-box; overflow: hidden;
        }
        .header { position: relative; min-height: 70px; border-bottom: 1px solid #000000; }
        .company { position: absolute; left: 0; top: 0; font-size: 10px; }
        .dept { position: absolute; left: 0; top: 14px; font-size: 8px; }
        .report-no { position: absolute; right: 0; top: 0; text-align: right; font-size: 8px; }
        .print-date { position: absolute; right: 0; top: 36px; text-align: right; font-size: 8px; }
        .title { text-align: center; font-size: 16px; line-height: 18px; padding-top: 10px; }
        .subtitle { text-align: center; font-size: 9px; margin-top: 4px; }
        
        table.mps {
            width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 15px;
        }
        table.mps th, table.mps td {
            border-bottom: 1px dotted #000000; padding: 3px 2px;
            vertical-align: top; font-size: 7px; line-height: 9px; overflow: hidden;
        }
        table.mps th {
            text-align: center; border-top: 1px solid #000000; border-bottom: 1px solid #000000; font-weight: bold;
        }
        table.mps td.num, table.mps th.num { text-align: right; white-space: nowrap; }
        table.mps td.center, table.mps th.center { text-align: center; }
        
        .station-row td {
            font-weight: bold; border-top: 1px solid #000000; border-bottom: 1px solid #000000;
            background: #e6e6e6; font-size: 9px; padding: 5px;
        }
        .summary-row td { border-bottom: none; font-size: 8px; line-height: 11px; padding: 4px 2px; }
        .summary-label { text-align: right; font-weight: bold; padding-right: 10px; }
        .summary-line td { border-top: 1px dashed #000000; }
        .space-row td { height: 18px; border-bottom: none; }
        .no-data { padding: 60px 0; text-align: center; font-family: Tahoma, Arial, sans-serif; font-size: 13px; }

        @page { size: A4 landscape; margin: 6mm; }
        @media print {
            html, body { background: #ffffff; }
            .filter, .toolbar { display: none; }
            .page { width: 100%; max-width: none; min-height: auto; margin: 0; padding: 0; border: none; overflow: visible; }
            table.mps th, table.mps td { font-size: 6.5px; line-height: 8px; padding: 1px; }
        }
    </style>
</head>

<body>

<div class="filter">
    <form method="get">
        <b>MPS Summary by Tonase</b>
        &nbsp;&nbsp;
        Start Month:
        <input type="month" name="month" value="<?php echo h($monthInput); ?>">
        &nbsp;&nbsp;
        Customer:
        <input type="text" name="customer" value="<?php echo h($customerInput); ?>" placeholder="All Customers">
        &nbsp;
        <button type="submit" class="btn">FILTER</button>
        <a href="mps.php" class="btn">CURRENT</a>
        <a href="?month=<?php echo h($monthInput); ?>&customer=<?php echo h($customerInput); ?>&export=excel" class="btn">EXPORT EXCEL</a>
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

        <div class="title">Master Production Schedule (SUMMARY ONLY)</div>
        <div class="subtitle">( Planning ) <?php if($customerInput != "") echo "<br><strong>Filtered by Customer: ".h($customerInput)."</strong>"; ?></div>

        <div class="report-no">
            FM.CO-00-11<br>
            Page 1 of 1
        </div>

        <div class="print-date">
            Print Date : <?php echo h($printDate); ?>
        </div>
    </div>

    <?php if ($totalGroup == 0) { ?>
        <div class="no-data">Data Summary tidak ditemukan.</div>
    <?php } else { ?>

        <table class="mps">
            <colgroup>
                <!-- Menjaga 22 Kolom orisinil agar layout alignment presisi -->
                <col style="width:5.5%;"><col style="width:20.5%;"><col style="width:3.5%;"><col style="width:3%;">
                <col style="width:3%;"><col style="width:4%;"><col style="width:4%;"><col style="width:4%;">
                <col style="width:4%;"><col style="width:4%;"><col style="width:4%;"><col style="width:4%;">
                <col style="width:4%;"><col style="width:4%;"><col style="width:4%;"><col style="width:4%;">
                <col style="width:4%;"><col style="width:4%;"><col style="width:4%;"><col style="width:4%;">
                <col style="width:4%;"><col style="width:5%;">
            </colgroup>
            
            <thead>
                <tr>
                    <th colspan="8" style="text-align: left; padding-left: 10px;">TONASE / STATION SUMMARY</th>
                    <th colspan="4"><?php echo h($m1); ?></th>
                    <th colspan="4"><?php echo h($m2); ?></th>
                    <th colspan="4"><?php echo h($m3); ?></th>
                    <th colspan="2"></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($groups as $groupKey => $g) { 
                    $avail = floatval($g["MAC_AVAIL"]);
                    $sumMcd1 = floatval($g["SUM_MCD1"]);
                    $sumMcd2 = floatval($g["SUM_MCD2"]);
                    $sumMcd3 = floatval($g["SUM_MCD3"]);

                    $mmday1 = floatval($g["MMDAY1"]);
                    $mmday2 = floatval($g["MMDAY2"]);
                    $mmday3 = floatval($g["MMDAY3"]);

                    $req1 = ($mmday1 > 0) ? ($sumMcd1 / $mmday1) : 0;
                    $req2 = ($mmday2 > 0) ? ($sumMcd2 / $mmday2) : 0;
                    $req3 = ($mmday3 > 0) ? ($sumMcd3 / $mmday3) : 0;

                    $mav1 = $avail * $mmday1;
                    $mav2 = $avail * $mmday2;
                    $mav3 = $avail * $mmday3;

                    $bal1 = $avail - $req1;
                    $bal2 = $avail - $req2;
                    $bal3 = $avail - $req3;

                    $balm1 = $mav1 - $sumMcd1;
                    $balm2 = $mav2 - $sumMcd2;
                    $balm3 = $mav3 - $sumMcd3;

                    $per1 = ($mav1 > 0) ? ($sumMcd1 / $mav1 * 100) : 0;
                    $per2 = ($mav2 > 0) ? ($sumMcd2 / $mav2 * 100) : 0;
                    $per3 = ($mav3 > 0) ? ($sumMcd3 / $mav3 * 100) : 0;
                ?>

                    <tr class="station-row">
                        <td colspan="22">TONASE: <?php echo h($g["ST"]); ?> &nbsp;&nbsp;|&nbsp;&nbsp; PROCESS: <?php echo h($g["PROCESS"]); ?></td>
                    </tr>
                    
                    <tr class="summary-row summary-line">
                        <td colspan="8" class="summary-label">Num. of</td>
                        <td class="center">UNIT</td><td></td><td></td><td class="center"><?php echo h(n0($mmday1)); ?></td>
                        <td class="center">UNIT</td><td></td><td></td><td class="center"><?php echo h(n0($mmday2)); ?></td>
                        <td class="center">UNIT</td><td></td><td></td><td class="center"><?php echo h(n0($mmday3)); ?></td>
                        <td colspan="2"></td>
                    </tr>
                    <tr class="summary-row">
                        <td colspan="8" class="summary-label">Machine Requirement</td>
                        <td colspan="4" class="center"><?php echo h(n2($req1)); ?></td>
                        <td colspan="4" class="center"><?php echo h(n2($req2)); ?></td>
                        <td colspan="4" class="center"><?php echo h(n2($req3)); ?></td>
                        <td colspan="2"></td>
                    </tr>
                    <tr class="summary-row">
                        <td colspan="8" class="summary-label">Machine Available</td>
                        <td colspan="4" class="center"><?php echo h(n2($avail)); ?></td>
                        <td colspan="4" class="center"><?php echo h(n2($avail)); ?></td>
                        <td colspan="4" class="center"><?php echo h(n2($avail)); ?></td>
                        <td colspan="2"></td>
                    </tr>
                    <tr class="summary-row">
                        <td colspan="8" class="summary-label">Balance</td>
                        <td colspan="4" class="center"><?php echo h(n2($bal1)); ?></td>
                        <td colspan="4" class="center"><?php echo h(n2($bal2)); ?></td>
                        <td colspan="4" class="center"><?php echo h(n2($bal3)); ?></td>
                        <td colspan="2"></td>
                    </tr>
                    <tr class="summary-row">
                        <td colspan="8" class="summary-label">Machine Available Days</td>
                        <td colspan="4" class="center"><?php echo h(n2($mav1)); ?></td>
                        <td colspan="4" class="center"><?php echo h(n2($mav2)); ?></td>
                        <td colspan="4" class="center"><?php echo h(n2($mav3)); ?></td>
                        <td colspan="2"></td>
                    </tr>
                    <tr class="summary-row">
                        <td colspan="8" class="summary-label">Balance Available Days</td>
                        <td colspan="4" class="center"><?php echo h(n2($balm1)); ?></td>
                        <td colspan="4" class="center"><?php echo h(n2($balm2)); ?></td>
                        <td colspan="4" class="center"><?php echo h(n2($balm3)); ?></td>
                        <td colspan="2"></td>
                    </tr>
                    <tr class="summary-row">
                        <td colspan="8" class="summary-label">Percentage</td>
                        <td colspan="4" class="center"><?php echo h(n2($per1)); ?>%</td>
                        <td colspan="4" class="center"><?php echo h(n2($per2)); ?>%</td>
                        <td colspan="4" class="center"><?php echo h(n2($per3)); ?>%</td>
                        <td colspan="2"></td>
                    </tr>
                    <tr class="space-row"><td colspan="22"></td></tr>

                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</div>

</body>
</html>