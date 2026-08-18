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
    if ($value == "") return $default;
    return floatval(str_replace(",", "", $value));
}

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

function n0($value) {
    if ($value === null || $value === "") return "-";
    $n = floatval($value);
    if (abs($n) < 0.000001) return "-";
    return number_format($n, 0, ".", ",");
}

function n2($value) {
    if ($value === null || $value === "") return "-";
    $n = floatval($value);
    if (abs($n) < 0.000001) return "0.00";
    return number_format($n, 2, ".", ",");
}

function n2z($value) {
    $n = floatval($value);
    return rtrim(rtrim(number_format($n, 2, ".", ","), "0"), ".");
}

function excel_num($value, $dec) {
    if ($value === null || $value === "") return "";
    $n = floatval($value);
    if (abs($n) < 0.000001) return "";
    if ($dec == 0) return number_format($n, 0, ".", "");
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
    if ($na == $nb) return strcmp((string)$a, (string)$b);
    return ($na < $nb) ? -1 : 1;
}

$monthInput    = get_value("month", date("Y-m"));
$machineFilter = get_value("machine", "INJECTION"); 
$stationFilter = get_value("station", "");
$export        = strtolower(get_value("export", ""));
$magLoc        = get_value("mag_loc", "IMC"); 

$workDaysFallback = to_float(get_value("work_days", "25"), 25); 
$workHours        = to_float(get_value("work_hours", "21"), 21);
$eff              = to_float(get_value("eff", "0.90"), 0.90);

if ($workDaysFallback <= 0) $workDaysFallback = 25;
if ($workHours <= 0) $workHours = 21;
if ($eff <= 0) $eff = 0.90;

$startDate = $monthInput . "-01";
if (strtotime($startDate) === false) {
    $monthInput = date("Y-m");
    $startDate  = date("Y-m-01");
}

$m1Title = date("F Y", strtotime($startDate));
$m2Title = date("F Y", strtotime("+1 month", strtotime($startDate)));
$m3Title = date("F Y", strtotime("+2 months", strtotime($startDate)));

$printDate  = date("d-M-Y H:i:s");


/* ======================================================
   AMBIL DATA LOKASI UNTUK COMBO BOX
====================================================== */
$sqlLoc = "
    SELECT
        MAG_ID,
        ISNULL(MAG_STATION, '') AS MAG_STATION,
        ISNULL(MAG_LOC, '') AS MAG_LOC,
        ISNULL(PROC_ID, 0) AS PROC_ID
    FROM dbo.MAG
    ORDER BY MAG_STATION, MAG_LOC
";
$stmtLoc = sqlsrv_query($conn, $sqlLoc);
$locationOptions = array();

if ($stmtLoc !== false) {
    while ($rl = sqlsrv_fetch_array($stmtLoc, SQLSRV_FETCH_ASSOC)) {
        $loc = trim($rl["MAG_LOC"]);
        // Cek agar nilai tidak kosong dan tidak duplikat
        if ($loc !== "" && !in_array($loc, $locationOptions)) {
            $locationOptions[] = $loc;
        }
    }
}
// Fallback jika kosong
if (empty($locationOptions)) {
    $locationOptions = array("IMC", "TII");
}


/* ======================================================
   LOAD MASTER PROCESS DAYS DARI dbo.PROCESS 
====================================================== */
$sqlProc = "
    SELECT 
        PROC_NAME, 
        PROC_MMDAY, 
        PROC_MMDAY2, 
        PROC_MMDAY3
    FROM dbo.PROCESS
";
$stmtProc = sqlsrv_query($conn, $sqlProc);
$procDays = array();

if ($stmtProc !== false) {
    while ($rp = sqlsrv_fetch_array($stmtProc, SQLSRV_FETCH_ASSOC)) {
        $pName = trim((string)$rp["PROC_NAME"]);
        $procDays[$pName] = array(
            "M1"  => floatval($rp["PROC_MMDAY"]),
            "M2"  => floatval($rp["PROC_MMDAY2"]),
            "M3"  => floatval($rp["PROC_MMDAY3"])
        );
    }
}


/* ======================================================
   LOAD LOKASI DARI dbo.MAG (MAPPING STATION -> LOCATION)
====================================================== */
$sqlMag = "
    SELECT
        MAG_ID,
        ISNULL(MAG_STATION, '') AS MAG_STATION,
        ISNULL(MAG_LOC, '') AS MAG_LOC,
        ISNULL(PROC_ID, 0) AS PROC_ID
    FROM dbo.MAG
    ORDER BY MAG_STATION, MAG_LOC
";
$stmtMag = sqlsrv_query($conn, $sqlMag);
$stationLocMap = array();

if ($stmtMag !== false) {
    while ($rm = sqlsrv_fetch_array($stmtMag, SQLSRV_FETCH_ASSOC)) {
        $st = trim((string)$rm["MAG_STATION"]);
        $lc = trim((string)$rm["MAG_LOC"]);
        if ($st !== "") {
            $stationLocMap[$st] = $lc;
        }
    }
}


/* ======================================================
   LOAD DATA DARI SP_MPS3MONTH_EX (3 BULAN SEPARATED)
====================================================== */
$sql = "EXEC dbo.SP_MPS3MONTH_EX ?";
$stmt = sqlsrv_query($conn, $sql, array($startDate), array("QueryTimeout" => 0));

if ($stmt === false) {
    die("<pre>Query SP_MPS3MONTH_EX error:\n" . sql_error_text() . "</pre>");
}

$groups = array();
$totalRows = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $machineNo = isset($r["PROCESS"]) ? trim((string)$r["PROCESS"]) : "";
    $tonage    = isset($r["ST"]) ? trim((string)$r["ST"]) : "";

    if ($machineNo == "") $machineNo = "*NONE*";
    if ($tonage == "") $tonage = "*NONE*";

    // --- HARD FILTER: HANYA TAMPILKAN INJECTION ---
    if (stripos($machineNo, 'INJECTION') === false) {
        continue; 
    }

    // Ambil data lokasi (MAG_LOC) berdasarkan nama STATION (Tonage)
    $machineLoc = isset($stationLocMap[$tonage]) ? $stationLocMap[$tonage] : "";
    
    // Filter berdasarkan lokasi yang dipilih pada combo box
    if ($magLoc != "" && $machineLoc != $magLoc) {
        continue;
    }

    if ($stationFilter != "" && stripos($tonage, $stationFilter) === false) continue;

    // Ambil jumlah ketersediaan mesin
    $qty = isset($r["QTY"]) ? floatval($r["QTY"]) : 1;
    if ($qty <= 0) $qty = 1;

    // Data hari pemakaian kotor (MCD)
    $mcd1 = isset($r["MCD1"]) ? floatval($r["MCD1"]) : 0;
    $mcd2 = isset($r["MCD2"]) ? floatval($r["MCD2"]) : 0;
    $mcd3 = isset($r["MCD3"]) ? floatval($r["MCD3"]) : 0;

    // Data hari ketersediaan ditarik langsung dari msdata.dbo.PROCESS
    $mmday1 = isset($procDays[$machineNo]["M1"]) ? $procDays[$machineNo]["M1"] : 0;
    $mmday2 = isset($procDays[$machineNo]["M2"]) ? $procDays[$machineNo]["M2"] : 0;
    $mmday3 = isset($procDays[$machineNo]["M3"]) ? $procDays[$machineNo]["M3"] : 0;
    
    $groupKey = $machineNo . "_" . $tonage; 

    if (!isset($groups[$groupKey])) {
        // PERBAIKAN: NUM_DAY menggunakan hari murni (22, 26, 27 dst)
        $nDay1 = ($mmday1 > 0 ? $mmday1 : $workDaysFallback);
        $nDay2 = ($mmday2 > 0 ? $mmday2 : $workDaysFallback);
        $nDay3 = ($mmday3 > 0 ? $mmday3 : $workDaysFallback);

        $groups[$groupKey] = array(
            "MACHINE_NO" => $machineNo,
            "TONAGE"     => $tonage,
            "LOCATION"   => $machineLoc,
            "QTY_MESIN"  => $qty,
            "M1" => array("NUM_DAY" => $nDay1, "TOTAL_MCD" => 0, "PLAN_DAY" => 0, "PLAN_PCT" => 0, "BAL_DAY" => 0, "BAL_PCT" => 0, "REMARK" => ""),
            "M2" => array("NUM_DAY" => $nDay2, "TOTAL_MCD" => 0, "PLAN_DAY" => 0, "PLAN_PCT" => 0, "BAL_DAY" => 0, "BAL_PCT" => 0, "REMARK" => ""),
            "M3" => array("NUM_DAY" => $nDay3, "TOTAL_MCD" => 0, "PLAN_DAY" => 0, "PLAN_PCT" => 0, "BAL_DAY" => 0, "BAL_PCT" => 0, "REMARK" => "")
        );
    }

    if ($groups[$groupKey]["TONAGE"] == "*NONE*" && $tonage != "*NONE*") {
        $groups[$groupKey]["TONAGE"] = $tonage;
    }

    // Akumulasi total kebutuhan hari untuk semua mesin
    $groups[$groupKey]["M1"]["TOTAL_MCD"] += $mcd1;
    $groups[$groupKey]["M2"]["TOTAL_MCD"] += $mcd2;
    $groups[$groupKey]["M3"]["TOTAL_MCD"] += $mcd3;

    $totalRows++;
}

uksort($groups, "cmp_machine_group");

// Kalkulasi Final per Bulan (Rata-rata Per Mesin)
foreach ($groups as $key => $g) {
    $qty = $g["QTY_MESIN"];
    
    foreach (["M1", "M2", "M3"] as $m) {
        $numDay = $g[$m]["NUM_DAY"];
        $totalMcd = $g[$m]["TOTAL_MCD"];
        
        // PERBAIKAN: Beban kerja dirata-rata ke per 1 mesin
        $planDay = ($qty > 0) ? ($totalMcd / $qty) : $totalMcd;
        
        $planPct = ($numDay > 0) ? ($planDay / $numDay * 100) : 0;
        $balDay  = $numDay - $planDay;
        $balPct  = 100 - $planPct;
        
        $groups[$key][$m]["PLAN_DAY"] = $planDay;
        $groups[$key][$m]["PLAN_PCT"] = $planPct;
        $groups[$key][$m]["BAL_DAY"]  = $balDay;
        $groups[$key][$m]["BAL_PCT"]  = $balPct;

        if ($balDay < 0) {
            $groups[$key][$m]["REMARK"] = "OVER CAP";
        }
    }
}

/* ======================================================
   EXPORT EXCEL
====================================================== */
if ($export == "excel") {
    while (ob_get_level() > 0) ob_end_clean();
    if (headers_sent($file, $line)) die("Header sudah terkirim di file: " . $file . " line: " . $line);

    $fileName = "MC_CAPACITY_INJECTION_" . date("Ym", strtotime($startDate)) . "_" . date("Ymd_His") . ".xls";

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
        table { border-collapse: collapse; font-family: Calibri, Arial, sans-serif; font-size: 11px; }
        th { background: #d9eaf7; font-weight: bold; text-align: center; border: 1px solid #000000; mso-number-format:"\@"; }
        td { border: 1px solid #000000; padding: 3px; mso-number-format:"\@"; }
        .num { mso-number-format:"0"; text-align: right; }
        .num2 { mso-number-format:"0\.00"; text-align: right; }
        .title { font-size: 16px; font-weight: bold; border: none; }
        .info { border: none; }
    </style>
</head>
<body>
<table>
    <tr><td colspan="23" class="title">PT.IMC TEKNO INDONESIA</td></tr>
    <tr><td colspan="23" class="title">MC CAPACITY INJECTION (3 MONTHS)</td></tr>
    <tr>
        <td colspan="23" class="info">
            Start Month : <?php echo h(date("F Y", strtotime($startDate))); ?> |
            Location : <?php echo h($magLoc == "" ? "ALL" : $magLoc); ?> |
            Work Days/Mo : <?php echo h(n2z($workDaysFallback)); ?> |
            Work Hours : <?php echo h(n2z($workHours)); ?> |
            Eff : <?php echo h(n2z($eff)); ?> |
            Export Date : <?php echo h($printDate); ?>
        </td>
    </tr>
    <tr><td colspan="23" class="info"></td></tr>
    
    <tr>
        <th rowspan="2">NO</th>
        <th rowspan="2">MACHINE</th>
        <th rowspan="2">TONAGE</th>
        <th rowspan="2">LOCATION</th>
        <th rowspan="2">QTY MAC</th>
        <th colspan="6"><?php echo h($m1Title); ?></th>
        <th colspan="6"><?php echo h($m2Title); ?></th>
        <th colspan="6"><?php echo h($m3Title); ?></th>
    </tr>
    <tr>
        <!-- M1 -->
        <th>NUM OF DAY</th>
        <th colspan="2">PLAN USE DAY / %</th>
        <th colspan="2">BALANCE DAY / %</th>
        <th>REMARK</th>
        <!-- M2 -->
        <th>NUM OF DAY</th>
        <th colspan="2">PLAN USE DAY / %</th>
        <th colspan="2">BALANCE DAY / %</th>
        <th>REMARK</th>
        <!-- M3 -->
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
            <td class="center"><?php echo h($g["LOCATION"]); ?></td>
            <td class="num"><?php echo h(excel_num($g["QTY_MESIN"], 0)); ?></td>
            
            <?php foreach (["M1", "M2", "M3"] as $m) { ?>
                <td class="num"><?php echo h(excel_num($g[$m]["NUM_DAY"], 0)); ?></td>
                <td class="num2"><?php echo h(excel_num($g[$m]["PLAN_DAY"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($g[$m]["PLAN_PCT"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($g[$m]["BAL_DAY"], 2)); ?></td>
                <td class="num2"><?php echo h(excel_num($g[$m]["BAL_PCT"], 2)); ?></td>
                <td><?php echo h($g[$m]["REMARK"]); ?></td>
            <?php } ?>
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
    <title>MC Capacity Injection (3 Months)</title>

    <style>
        html, body { margin: 0; padding: 0; background: #9c9c9c; color: #000000; font-family: "Times New Roman", serif; font-size: 11px; }
        .filter { width: calc(100% - 20px); max-width: 1400px; margin: 8px auto; background: #d4d0c8; border: 1px solid #777777; padding: 8px; box-sizing: border-box; font-family: Tahoma, Arial, sans-serif; font-size: 12px; white-space: nowrap; }
        .filter input, .filter select { height: 24px; border: 1px solid #777777; padding: 2px 5px; font-family: Tahoma, Arial, sans-serif; font-size: 12px; box-sizing: border-box; }
        .btn { height: 26px; padding: 2px 12px; border: 1px solid #777777; background: #eeeeee; cursor: pointer; font-family: Tahoma, Arial, sans-serif; font-size: 12px; color: #000000; text-decoration: none; box-sizing: border-box; display: inline-block; line-height: 20px; }
        .toolbar { width: calc(100% - 20px); max-width: 1400px; margin: 0 auto 6px auto; text-align: right; }
        
        .page { width: calc(100% - 20px); max-width: 1400px; min-height: 780px; margin: 0 auto 20px auto; background: #ffffff; padding: 20px 25px; border: 2px solid #000000; box-sizing: border-box; }
        .company { font-weight: bold; font-size: 16px; line-height: 20px; }
        .title { font-weight: bold; font-size: 15px; line-height: 18px; margin-bottom: 5px;}
        .month { font-weight: bold; font-size: 12px; line-height: 16px; margin-bottom: 12px;}
        
        table.report { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10px; }
        table.report th, table.report td { border: 1px solid #000000; padding: 3px 2px; vertical-align: middle; overflow: hidden; word-wrap: break-word;}
        table.report th { text-align: center; font-weight: normal; background-color: #f7f7f7;}
        table.report td.num { text-align: right; white-space: nowrap; }
        table.report td.center { text-align: center; }
        .bold { font-weight: bold; }
        
        /* Layout Grafik di-split 3 agar sejajar */
        .charts-container { display: flex; gap: 15px; margin-top: 24px; width: 100%; }
        .chart-box { flex: 1; border: 1px solid #000000; padding: 8px; box-sizing: border-box; page-break-inside: avoid; }
        .chart-title { font-family: Tahoma, Arial, sans-serif; font-weight: bold; font-size: 11px; margin-bottom: 8px; text-align: center; }
        .chart-row { display: flex; align-items: center; margin-bottom: 5px; font-family: Tahoma, Arial, sans-serif; font-size: 9px; }
        .chart-tonage { width: 45px; font-weight: bold; text-align: center; }
        .chart-track { flex: 1; height: 12px; border: 1px solid #000000; display: flex; background: #ffffff; }
        .chart-plan { height: 12px; background: #2f65d9; }
        .chart-balance { height: 12px; background: #d9d9d9; }
        .chart-over { height: 12px; background: #cc0000; }
        .chart-value { width: 65px; text-align: right; padding-left: 4px; box-sizing: border-box; }
        .chart-legend { margin-top: 8px; font-family: Tahoma, Arial, sans-serif; font-size: 9px; text-align: center;}
        .legend-plan { display: inline-block; width: 10px; height: 8px; background: #2f65d9; border: 1px solid #000000; }
        .legend-balance { display: inline-block; width: 10px; height: 8px; background: #d9d9d9; border: 1px solid #000000; margin-left: 8px; }
        .legend-over { display: inline-block; width: 10px; height: 8px; background: #cc0000; border: 1px solid #000000; margin-left: 8px; }
        .no-data { padding: 60px 0; text-align: center; font-family: Tahoma, Arial, sans-serif; font-size: 14px; }
        
        @page { size: A4 landscape; margin: 5mm; }
        @media print {
            html, body { background: #ffffff; }
            .filter, .toolbar { display: none; }
            .page { width: 100%; max-width: none; min-height: auto; margin: 0; border: none; padding: 0; }
        }
    </style>
</head>
<body>

<div class="filter">
    <form method="get">
        <b>MC Capacity Injection</b>
        &nbsp;&nbsp;
        Start Month:
        <input type="month" name="month" value="<?php echo h($monthInput); ?>">
        &nbsp;
        Location:
        <select name="mag_loc" style="width:80px;">
            <option value="">- ALL -</option>
            <?php foreach ($locationOptions as $loc) { ?>
                <option value="<?php echo h($loc); ?>" <?php if ($magLoc == $loc) echo 'selected'; ?>><?php echo h($loc); ?></option>
            <?php } ?>
        </select>
        &nbsp;
        
        &nbsp;
        Tonage:
        <input type="text" name="station" value="<?php echo h($stationFilter); ?>" placeholder="20080T" style="width:80px;">
        &nbsp;
        <button type="submit" class="btn">FILTER</button>
    </form>
</div>

<div class="toolbar">
    <a class="btn" href="<?php echo h($selfFile); ?>?month=<?php echo h($monthInput); ?>&mag_loc=<?php echo h(urlencode($magLoc)); ?>&work_days=<?php echo h($workDaysFallback); ?>&station=<?php echo h(urlencode($stationFilter)); ?>&export=excel">
        EXPORT EXCEL
    </a>
    <a class="btn" href="<?php echo h($selfFile); ?>?month=<?php echo h($monthInput); ?>&work_days=<?php echo h($workDaysFallback); ?>">
        ALL
    </a>
    <button type="button" class="btn" onclick="window.print()">PRINT</button>
    <button type="button" class="btn" onclick="window.location.href='mc_capacity.php'">CLOSE</button>
</div>

<div class="page">
    <div class="company">PT.IMC TEKNO INDONESIA</div>
    <div class="title">MC CAPACITY INJECTION</div>
    <div class="month">Start Month : <?php echo h(date("F Y", strtotime($startDate))); ?> | Location : <?php echo h($magLoc == "" ? "ALL" : $magLoc); ?></div>

    <?php if ($totalRows == 0) { ?>
        <div class="no-data">Data MC capacity tidak ditemukan.</div>
    <?php } else { ?>

        <table class="report">
            <colgroup>
                <!-- Kiri: Fixed Cols (5) -->
                <col style="width:2.5%;">
                <col style="width:5.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;"> <!-- LOCATION -->
                <col style="width:3%;">
                
                <!-- M1 (6 cols) -->
                <col style="width:3.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:5%;">
                
                <!-- M2 (6 cols) -->
                <col style="width:3.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:5%;">
                
                <!-- M3 (6 cols) -->
                <col style="width:3.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:4.5%;">
                <col style="width:5%;">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2" class="bold">NO</th>
                    <th rowspan="2" class="bold">MACHINE</th>
                    <th rowspan="2" class="bold">TONAGE</th>
                    <th rowspan="2" class="bold">LOCATION</th>
                    <th rowspan="2" class="bold" title="Jumlah Mesin">QTY<br>MAC</th>
                    <th colspan="6" class="bold"><?php echo h($m1Title); ?></th>
                    <th colspan="6" class="bold"><?php echo h($m2Title); ?></th>
                    <th colspan="6" class="bold"><?php echo h($m3Title); ?></th>
                </tr>
                <tr>
                    <!-- M1 -->
                    <th>NUM OF<br>DAY</th>
                    <th colspan="2">PLAN USE<br>DAY / %</th>
                    <th colspan="2">BALANCE<br>DAY / %</th>
                    <th>REMARK</th>
                    <!-- M2 -->
                    <th>NUM OF<br>DAY</th>
                    <th colspan="2">PLAN USE<br>DAY / %</th>
                    <th colspan="2">BALANCE<br>DAY / %</th>
                    <th>REMARK</th>
                    <!-- M3 -->
                    <th>NUM OF<br>DAY</th>
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
                        <td class="center bold"><?php echo h($g["LOCATION"]); ?></td>
                        <td class="center bold"><?php echo h(n0($g["QTY_MESIN"])); ?></td>
                        
                        <?php foreach (["M1", "M2", "M3"] as $m) { ?>
                            <td class="center bold"><?php echo h(n0($g[$m]["NUM_DAY"])); ?></td>
                            <td class="num bold"><?php echo h(n2($g[$m]["PLAN_DAY"])); ?></td>
                            <td class="num bold"><?php echo h(n2($g[$m]["PLAN_PCT"])); ?></td>
                            <td class="num bold"><?php echo h(n2($g[$m]["BAL_DAY"])); ?></td>
                            <td class="num bold"><?php echo h(n2($g[$m]["BAL_PCT"])); ?></td>
                            <td class="center"><?php echo h($g[$m]["REMARK"]); ?></td>
                        <?php } ?>
                    </tr>
                    <?php $no++; ?>
                <?php } ?>
            </tbody>
        </table>

        <!-- Grafik dibagi menjadi 3 blok berdampingan -->
        <div class="charts-container">
            <?php 
            $monthsMap = ["M1" => $m1Title, "M2" => $m2Title, "M3" => $m3Title];
            foreach ($monthsMap as $mKey => $mName) { 
            ?>
            <div class="chart-box">
                <div class="chart-title"><?php echo h(strtoupper($mName)); ?></div>

                <?php foreach ($groups as $g) { 
                    $planPct = floatval($g[$mKey]["PLAN_PCT"]);
                    $balPct  = floatval($g[$mKey]["BAL_PCT"]);

                    if ($planPct < 0) $planPct = 0;
                    if ($balPct < 0) $balPct = 0;

                    $planWidth = ($planPct > 100) ? 100 : $planPct;
                    $balWidth  = ($balPct > 100) ? 100 : $balPct;
                    $planClass = ($g[$mKey]["PLAN_PCT"] > 100) ? "chart-over" : "chart-plan";
                ?>
                    <div class="chart-row">
                        <div class="chart-tonage"><?php echo h($g["TONAGE"]); ?></div>
                        <div class="chart-track">
                            <div class="<?php echo h($planClass); ?>" style="width: <?php echo h($planWidth); ?>%;"></div>
                            <div class="chart-balance" style="width: <?php echo h($balWidth); ?>%;"></div>
                        </div>
                        <div class="chart-value">
                            <?php echo h(n0($g[$mKey]["PLAN_PCT"])); ?>%
                        </div>
                    </div>
                <?php } ?>

                <div class="chart-legend">
                    <span class="legend-plan"></span> Plan
                    <span class="legend-balance"></span> Bal
                    <span class="legend-over"></span> Over
                </div>
            </div>
            <?php } ?>
        </div>

    <?php } ?>
</div>

</body>
</html>