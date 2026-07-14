<?php
require_once __DIR__ . "/../config/global.php";

/*
    prod_report.php
    Dashboard Produksi Tahunan + Top 5 NG + Detail Item Bermasalah + Actual SMS Virgin/Crusher
    PHP 5.4 + SQL Server 2008 + sqlsrv
*/

if ($conn === false) {
    echo "Koneksi database gagal.";
    exit;
}

function h($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, "UTF-8");
}

function angka($nilai, $decimal = 0) {
    return number_format((float)$nilai, $decimal, ",", ".");
}

function safeText($value) {
    $text = trim((string)$value);

    if ($text === "") {
        return "";
    }

    if (@preg_match('//u', $text)) {
        return $text;
    }

    if (function_exists('iconv')) {
        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $text);
        if ($converted !== false) {
            return $converted;
        }
    }

    if (function_exists('utf8_encode')) {
        return @utf8_encode($text);
    }

    return $text;
}

/*
    FILTER
*/
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

if ($year < 2000 || $year > 2100) {
    $year = intval(date('Y'));
}

$cust_code = isset($_GET['cust_code']) ? trim($_GET['cust_code']) : '';
$cust_text = isset($_GET['cust_text']) ? trim($_GET['cust_text']) : '';
$mc_no     = isset($_GET['mc_no']) ? trim($_GET['mc_no']) : '';
$tonase    = isset($_GET['tonase']) ? trim($_GET['tonase']) : '';
$ngt_code  = isset($_GET['ngt_code']) ? trim($_GET['ngt_code']) : '';
$month     = isset($_GET['month']) ? intval($_GET['month']) : 0;

if ($month < 1 || $month > 12) {
    $month = 0;
}

/*
    PERIODE FILTER
    month = 0 berarti semua bulan dalam tahun yang dipilih.
    month 1-12 berarti data hanya bulan tersebut.
*/
if ($month > 0) {
    $start_date = sprintf("%04d-%02d-01", $year, $month);

    if ($month == 12) {
        $end_date = ($year + 1) . "-01-01";
    } else {
        $end_date = sprintf("%04d-%02d-01", $year, $month + 1);
    }
} else {
    $start_date = $year . "-01-01";
    $end_date   = ($year + 1) . "-01-01";
}

/*
    COMBO MESIN DAN TONASE
*/
$sqlMachineCombo = "
SELECT
    dbo.MAC.MAC_CODE,
    dbo.MAG.MAG_STATION AS TONASE
FROM dbo.MAG
INNER JOIN dbo.MAC ON dbo.MAG.MAG_ID = dbo.MAC.MAG_ID
WHERE
    dbo.MAC.MAC_CODE IS NOT NULL
    AND LTRIM(RTRIM(dbo.MAC.MAC_CODE)) <> ''
ORDER BY
    dbo.MAG.MAG_STATION,
    dbo.MAC.MAC_CODE
";

$stmtCombo = sqlsrv_query($conn, $sqlMachineCombo);

$machineRows = array();
$tonaseMap = array();

if ($stmtCombo !== false) {
    while ($rowCombo = sqlsrv_fetch_array($stmtCombo, SQLSRV_FETCH_ASSOC)) {
        $macCode = safeText(isset($rowCombo["MAC_CODE"]) ? $rowCombo["MAC_CODE"] : "");
        $tonaseVal = safeText(isset($rowCombo["TONASE"]) ? $rowCombo["TONASE"] : "");

        if ($macCode != "") {
            $machineRows[] = array(
                "MAC_CODE" => $macCode,
                "TONASE" => $tonaseVal
            );
        }

        if ($tonaseVal != "") {
            $tonaseMap[$tonaseVal] = true;
        }
    }

    sqlsrv_free_stmt($stmtCombo);
}

$tonaseList = array_keys($tonaseMap);
sort($tonaseList);

/*
    WHERE DASHBOARD
*/
$where = "";
$params = array($start_date, $end_date);

if ($cust_code != "") {
    $where .= " AND ITEM_CUSTINFO_VIEW.CUST_CODE = ? ";
    $params[] = $cust_code;
}

if ($tonase != "") {
    $where .= " AND MAG.MAG_STATION = ? ";
    $params[] = $tonase;
}

if ($mc_no != "") {
    $where .= " AND MAC.MAC_CODE = ? ";
    $params[] = $mc_no;
}

/*
    WHERE GLOBAL MATERIAL ACTUAL SMS
    Sumber qty: SMS_DETAIL.SMSD_QTY
    Dibuat terpisah supaya data SMS tidak dobel karena join ITEM_CUSTINFO_VIEW.
*/
$whereMaterialGlobal = "";
$paramsMaterialGlobal = array($start_date, $end_date);

if ($cust_code != "") {
    $whereMaterialGlobal .= "
        AND EXISTS (
            SELECT 1
            FROM ITEM_CUSTINFO_VIEW ICV
            WHERE ICV.ITEM_ID = WO.ITEM_ID
              AND ICV.CUST_CODE = ?
        )
    ";
    $paramsMaterialGlobal[] = $cust_code;
}

if ($tonase != "") {
    $whereMaterialGlobal .= " AND MAG.MAG_STATION = ? ";
    $paramsMaterialGlobal[] = $tonase;
}

if ($mc_no != "") {
    $whereMaterialGlobal .= " AND MAC.MAC_CODE = ? ";
    $paramsMaterialGlobal[] = $mc_no;
}

/*
    DATA PRODUKSI PER BULAN
*/
$sqlMonthly = "
SELECT
    MONTH(PRODUCTION.PD_DATE) AS BULAN,

    SUM(ISNULL(PRODUCTION.PD_OK, 0) + ISNULL(PRODUCTION.PD_HO, 0)) AS OK_QTY,
    SUM(ISNULL(PRODUCTION.PD_NG, 0)) AS NG_QTY,
    SUM(ISNULL(PRODUCTION.PD_SC, 0)) AS PURGING_QTY,

    SUM(ISNULL(PRODUCTION.PD_WKH, 0)) AS WORK_HOURS,
    SUM(ISNULL(PRODUCTION.PD_LOSTHOUR, 0)) AS LOST_HOURS,

    SUM(ISNULL(PRODUCTION.PD_SHOT, 0)) AS SHOT_QTY,
    AVG(NULLIF(CAST(PRODUCTION.PD_CAV AS FLOAT), 0)) AS AVG_CAVITY,
    AVG(NULLIF(CAST(PRODUCTION.PD_CYTM AS FLOAT), 0)) AS AVG_CYCLETIME

FROM PRODUCTION
INNER JOIN WO ON PRODUCTION.WO_ID = WO.WO_ID
INNER JOIN MAC ON WO.MAC_ID = MAC.MAC_ID
INNER JOIN MAG ON MAC.MAG_ID = MAG.MAG_ID
INNER JOIN ITEM_CUSTINFO_VIEW ON WO.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID

WHERE
    PRODUCTION.PD_DATE >= ?
    AND PRODUCTION.PD_DATE < ?
    $where

GROUP BY
    MONTH(PRODUCTION.PD_DATE)

ORDER BY
    MONTH(PRODUCTION.PD_DATE)
";

$stmtMonthly = sqlsrv_query($conn, $sqlMonthly, $params);

if ($stmtMonthly === false) {
    echo "<pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
    exit;
}

$monthNames = array(
    1 => "Jan",
    2 => "Feb",
    3 => "Mar",
    4 => "Apr",
    5 => "Mei",
    6 => "Jun",
    7 => "Jul",
    8 => "Agu",
    9 => "Sep",
    10 => "Okt",
    11 => "Nov",
    12 => "Des"
);

$periodeLabel = "Tahun " . $year;

if ($month > 0 && isset($monthNames[$month])) {
    $periodeLabel = "Bulan " . $monthNames[$month] . " " . $year;
}

$displayMonthIndexes = array();

if ($month > 0) {
    $displayMonthIndexes[] = $month;
} else {
    for ($i = 1; $i <= 12; $i++) {
        $displayMonthIndexes[] = $i;
    }
}

$data = array();

for ($i = 1; $i <= 12; $i++) {
    $data[$i] = array(
        "bulan" => $monthNames[$i],
        "ok" => 0,
        "ng" => 0,
        "purging" => 0,
        "work_hours" => 0,
        "lost_hours" => 0,
        "shot" => 0,
        "avg_cavity" => 0,
        "avg_cycletime" => 0,
        "ng_percent_ok" => 0,
        "ng_percent_total" => 0,
        "purging_percent_ok" => 0,
        "lost_hour_percent" => 0,
        "availability" => 0,
        "ok_per_hour" => 0
    );
}

$total_ok = 0;
$total_ng = 0;
$total_purging = 0;
$total_work_hours = 0;
$total_lost_hours = 0;
$total_shot = 0;

while ($row = sqlsrv_fetch_array($stmtMonthly, SQLSRV_FETCH_ASSOC)) {
    $b = intval($row["BULAN"]);

    $ok = floatval($row["OK_QTY"]);
    $ng = floatval($row["NG_QTY"]);
    $purging = floatval($row["PURGING_QTY"]);
    $work_hours = floatval($row["WORK_HOURS"]);
    $lost_hours = floatval($row["LOST_HOURS"]);
    $shot = floatval($row["SHOT_QTY"]);
    $avg_cavity = floatval($row["AVG_CAVITY"]);
    $avg_cycletime = floatval($row["AVG_CYCLETIME"]);

    $ng_percent_ok = 0;
    if ($ok > 0) {
        $ng_percent_ok = ($ng / $ok) * 100;
    }

    $ng_percent_total = 0;
    if (($ok + $ng) > 0) {
        $ng_percent_total = ($ng / ($ok + $ng)) * 100;
    }

    $purging_percent_ok = 0;
    if ($ok > 0) {
        $purging_percent_ok = ($purging / $ok) * 100;
    }

    $lost_hour_percent = 0;
    if (($work_hours + $lost_hours) > 0) {
        $lost_hour_percent = ($lost_hours / ($work_hours + $lost_hours)) * 100;
    }

    $availability = 0;
    if (($work_hours + $lost_hours) > 0) {
        $availability = ($work_hours / ($work_hours + $lost_hours)) * 100;
    }

    $ok_per_hour = 0;
    if ($work_hours > 0) {
        $ok_per_hour = $ok / $work_hours;
    }

    $data[$b]["ok"] = $ok;
    $data[$b]["ng"] = $ng;
    $data[$b]["purging"] = $purging;
    $data[$b]["work_hours"] = $work_hours;
    $data[$b]["lost_hours"] = $lost_hours;
    $data[$b]["shot"] = $shot;
    $data[$b]["avg_cavity"] = $avg_cavity;
    $data[$b]["avg_cycletime"] = $avg_cycletime;
    $data[$b]["ng_percent_ok"] = $ng_percent_ok;
    $data[$b]["ng_percent_total"] = $ng_percent_total;
    $data[$b]["purging_percent_ok"] = $purging_percent_ok;
    $data[$b]["lost_hour_percent"] = $lost_hour_percent;
    $data[$b]["availability"] = $availability;
    $data[$b]["ok_per_hour"] = $ok_per_hour;

    $total_ok += $ok;
    $total_ng += $ng;
    $total_purging += $purging;
    $total_work_hours += $work_hours;
    $total_lost_hours += $lost_hours;
    $total_shot += $shot;
}

sqlsrv_free_stmt($stmtMonthly);

/*
    TOTAL KPI
*/
$total_ng_percent_ok = 0;
if ($total_ok > 0) {
    $total_ng_percent_ok = ($total_ng / $total_ok) * 100;
}

$total_ng_percent_total = 0;
if (($total_ok + $total_ng) > 0) {
    $total_ng_percent_total = ($total_ng / ($total_ok + $total_ng)) * 100;
}

$total_purging_percent_ok = 0;
if ($total_ok > 0) {
    $total_purging_percent_ok = ($total_purging / $total_ok) * 100;
}

$total_lost_percent = 0;
if (($total_work_hours + $total_lost_hours) > 0) {
    $total_lost_percent = ($total_lost_hours / ($total_work_hours + $total_lost_hours)) * 100;
}

$total_availability = 0;
if (($total_work_hours + $total_lost_hours) > 0) {
    $total_availability = ($total_work_hours / ($total_work_hours + $total_lost_hours)) * 100;
}

$total_ok_per_hour = 0;
if ($total_work_hours > 0) {
    $total_ok_per_hour = $total_ok / $total_work_hours;
}

/*
    GLOBAL ACTUAL MATERIAL USAGE PER BULAN
    Sumber qty: SMS_DETAIL.SMSD_QTY
    Hanya material MAT.ITTY_CODE = 02

    Rule:
    MAT_CODE belakang -0 = VIRGIN
    Selain -0 = CRUSHER

    Contoh:
    100617-0 = VIRGIN
    100617-1 = CRUSHER
*/
$globalMaterialMonthly = array();

for ($i = 1; $i <= 12; $i++) {
    $globalMaterialMonthly[$i] = array(
        "bulan" => $monthNames[$i],
        "virgin_qty" => 0,
        "crusher_qty" => 0,
        "total_qty" => 0,
        "virgin_pct" => 0,
        "crusher_pct" => 0,
        "material_type" => "-"
    );
}

$globalVirginQty = 0;
$globalCrusherQty = 0;
$globalMaterialTotalQty = 0;
$globalVirginPct = 0;
$globalCrusherPct = 0;
$globalMaterialType = "-";

$sqlGlobalMaterialMonthly = "
SELECT
    MONTH(SMS.SMS_DATE) AS BULAN,

    SUM(
        CASE
            WHEN RIGHT(RTRIM(ISNULL(MAT.ITEM_CODE, '')), 2) = '-0'
            THEN ISNULL(SMS_DETAIL.SMSD_QTY, 0)
            ELSE 0
        END
    ) AS VIRGIN_QTY,

    SUM(
        CASE
            WHEN LTRIM(RTRIM(ISNULL(MAT.ITEM_CODE, ''))) <> ''
             AND RIGHT(RTRIM(ISNULL(MAT.ITEM_CODE, '')), 2) <> '-0'
            THEN ISNULL(SMS_DETAIL.SMSD_QTY, 0)
            ELSE 0
        END
    ) AS CRUSHER_QTY

FROM SMS
INNER JOIN WO ON SMS.WO_ID = WO.WO_ID
INNER JOIN SMS_DETAIL ON SMS.SMS_ID = SMS_DETAIL.SMS_ID
INNER JOIN ITEMS AS MAT ON SMS_DETAIL.ITEM_ID = MAT.ITEM_ID
INNER JOIN MAC ON WO.MAC_ID = MAC.MAC_ID
INNER JOIN MAG ON MAC.MAG_ID = MAG.MAG_ID

WHERE
    SMS.SMS_DATE >= ?
    AND SMS.SMS_DATE < ?
    AND MAT.ITTY_CODE = '02'
    $whereMaterialGlobal

GROUP BY
    MONTH(SMS.SMS_DATE)

ORDER BY
    MONTH(SMS.SMS_DATE)
";

$stmtGlobalMaterialMonthly = sqlsrv_query($conn, $sqlGlobalMaterialMonthly, $paramsMaterialGlobal);

if ($stmtGlobalMaterialMonthly === false) {
    echo "<pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
    exit;
}

while ($rowGm = sqlsrv_fetch_array($stmtGlobalMaterialMonthly, SQLSRV_FETCH_ASSOC)) {
    $bulanGm = intval($rowGm["BULAN"]);

    $virginQtyGm = floatval(isset($rowGm["VIRGIN_QTY"]) ? $rowGm["VIRGIN_QTY"] : 0);
    $crusherQtyGm = floatval(isset($rowGm["CRUSHER_QTY"]) ? $rowGm["CRUSHER_QTY"] : 0);
    $totalQtyGm = $virginQtyGm + $crusherQtyGm;

    $virginPctGm = 0;
    $crusherPctGm = 0;
    $materialTypeGm = "-";

    if ($totalQtyGm > 0) {
        $virginPctGm = ($virginQtyGm / $totalQtyGm) * 100;
        $crusherPctGm = ($crusherQtyGm / $totalQtyGm) * 100;

        if ($virginQtyGm > 0 && $crusherQtyGm > 0) {
            $materialTypeGm = "MIXED";
        } elseif ($virginQtyGm > 0) {
            $materialTypeGm = "VIRGIN";
        } elseif ($crusherQtyGm > 0) {
            $materialTypeGm = "CRUSHER";
        }
    }

    if ($bulanGm >= 1 && $bulanGm <= 12) {
        $globalMaterialMonthly[$bulanGm]["virgin_qty"] = $virginQtyGm;
        $globalMaterialMonthly[$bulanGm]["crusher_qty"] = $crusherQtyGm;
        $globalMaterialMonthly[$bulanGm]["total_qty"] = $totalQtyGm;
        $globalMaterialMonthly[$bulanGm]["virgin_pct"] = $virginPctGm;
        $globalMaterialMonthly[$bulanGm]["crusher_pct"] = $crusherPctGm;
        $globalMaterialMonthly[$bulanGm]["material_type"] = $materialTypeGm;
    }

    $globalVirginQty += $virginQtyGm;
    $globalCrusherQty += $crusherQtyGm;
}

sqlsrv_free_stmt($stmtGlobalMaterialMonthly);

$globalMaterialTotalQty = $globalVirginQty + $globalCrusherQty;

if ($globalMaterialTotalQty > 0) {
    $globalVirginPct = ($globalVirginQty / $globalMaterialTotalQty) * 100;
    $globalCrusherPct = ($globalCrusherQty / $globalMaterialTotalQty) * 100;

    if ($globalVirginQty > 0 && $globalCrusherQty > 0) {
        $globalMaterialType = "MIXED";
    } elseif ($globalVirginQty > 0) {
        $globalMaterialType = "VIRGIN";
    } elseif ($globalCrusherQty > 0) {
        $globalMaterialType = "CRUSHER";
    }
}


/*
    HARGA PART INTERNAL BERDASARKAN BOM + HARGA PO TERAKHIR
    Sumber rumus sama dengan report List Harga Material Konsumsi:
    - ITTY 02: BOM_QTY x Harga_PO_IDR / 1000
    - ITTY 03: BOM_QTY x Harga_PO_IDR
    - Currency non-IDR dikonversi dengan CURR_RAT.CURR_VRATE
*/
$partHargaByCode = array();

$sqlHargaPartInternal = "
SELECT
    b.PART_CODE,

    SUM(
        CASE
            WHEN b.ITTY_CODE = '02' THEN
                ROUND(
                    (
                        ISNULL(b.QTY, 0) *
                        CASE
                            WHEN ISNULL(p.PO_CUR, 'IDR') = 'IDR'
                            THEN ISNULL(p.POD_PRICE, 0)
                            ELSE ISNULL(p.POD_PRICE, 0) * ISNULL(c.CURR_VRATE, 0)
                        END
                    ) / 1000.0,
                    2
                )
            WHEN b.ITTY_CODE = '03' THEN
                ROUND(
                    ISNULL(b.QTY, 0) *
                    CASE
                        WHEN ISNULL(p.PO_CUR, 'IDR') = 'IDR'
                        THEN ISNULL(p.POD_PRICE, 0)
                        ELSE ISNULL(p.POD_PRICE, 0) * ISNULL(c.CURR_VRATE, 0)
                    END,
                    2
                )
            ELSE 0
        END
    ) AS HARGA_PART_IDR

FROM
(
    SELECT
        PART.ITEM_ID AS PART_ID,
        PART.ITEM_CODE AS PART_CODE,
        MAT.ITEM_ID AS MAT_ID,
        MAT.ITEM_CODE AS MAT_CODE,
        BD.QTY,
        MAT.ITTY_CODE
    FROM BOM_DEFAULT BD
    INNER JOIN ITEMS MAT ON BD.ITEM_ID = MAT.ITEM_ID
    INNER JOIN ITEMS PART ON BD.PART_ID = PART.ITEM_ID
    WHERE
        MAT.ITTY_CODE IN ('02', '03')
        AND ISNULL(PART.ITEM_INACTIVE, 0) = 0
        AND ISNULL(MAT.ITEM_INACTIVE, 0) = 0
) b

LEFT JOIN
(
    SELECT
        pd.ITEM_ID AS MAT_ID,
        pd.POD_PRICE,
        po.PO_DATE AS PRICE_DATE_RAW,
        po.PO_CUR,
        ROW_NUMBER() OVER
        (
            PARTITION BY pd.ITEM_ID
            ORDER BY po.PO_DATE DESC, po.PO_ID DESC
        ) AS rn
    FROM PO_DETAIL pd
    INNER JOIN PO po ON pd.PO_ID = po.PO_ID
) p
    ON b.MAT_ID = p.MAT_ID
   AND p.rn = 1

LEFT JOIN CURR_RAT c
    ON p.PO_CUR = c.CURR_CODE
   AND p.PRICE_DATE_RAW BETWEEN c.CURR_SDATE AND c.CURR_EDATE

GROUP BY
    b.PART_CODE
";

$stmtHargaPartInternal = sqlsrv_query($conn, $sqlHargaPartInternal);

if ($stmtHargaPartInternal === false) {
    echo "<pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
    exit;
}

while ($rowHargaPart = sqlsrv_fetch_array($stmtHargaPartInternal, SQLSRV_FETCH_ASSOC)) {
    $partCodeHarga = safeText(isset($rowHargaPart["PART_CODE"]) ? $rowHargaPart["PART_CODE"] : "");

    if ($partCodeHarga != "") {
        $partHargaByCode[$partCodeHarga] = floatval(isset($rowHargaPart["HARGA_PART_IDR"]) ? $rowHargaPart["HARGA_PART_IDR"] : 0);
    }
}

sqlsrv_free_stmt($stmtHargaPartInternal);

/*
    TOP 10 MESIN BERDASARKAN NG %
*/
$sqlMachine = "
SELECT TOP 10
    MAC.MAC_CODE AS MC_NO,
    MAG.MAG_STATION AS TONASE,

    SUM(ISNULL(PRODUCTION.PD_OK, 0) + ISNULL(PRODUCTION.PD_HO, 0)) AS OK_QTY,
    SUM(ISNULL(PRODUCTION.PD_NG, 0)) AS NG_QTY,

    CASE
        WHEN SUM(ISNULL(PRODUCTION.PD_OK, 0) + ISNULL(PRODUCTION.PD_HO, 0)) = 0 THEN 0
        ELSE
            SUM(ISNULL(PRODUCTION.PD_NG, 0)) * 100.0 /
            SUM(ISNULL(PRODUCTION.PD_OK, 0) + ISNULL(PRODUCTION.PD_HO, 0))
    END AS NG_PERCENT_OK

FROM PRODUCTION
INNER JOIN WO ON PRODUCTION.WO_ID = WO.WO_ID
INNER JOIN MAC ON WO.MAC_ID = MAC.MAC_ID
INNER JOIN MAG ON MAC.MAG_ID = MAG.MAG_ID
INNER JOIN ITEM_CUSTINFO_VIEW ON WO.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID

WHERE
    PRODUCTION.PD_DATE >= ?
    AND PRODUCTION.PD_DATE < ?
    $where

GROUP BY
    MAC.MAC_CODE,
    MAG.MAG_STATION

HAVING
    SUM(ISNULL(PRODUCTION.PD_OK, 0) + ISNULL(PRODUCTION.PD_HO, 0)) > 0

ORDER BY
    NG_PERCENT_OK DESC
";

$stmtMachine = sqlsrv_query($conn, $sqlMachine, $params);

if ($stmtMachine === false) {
    echo "<pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
    exit;
}

$machineLabels = array();
$machineNgPercent = array();

while ($rowM = sqlsrv_fetch_array($stmtMachine, SQLSRV_FETCH_ASSOC)) {
    $label = safeText($rowM["MC_NO"]);

    if (isset($rowM["TONASE"]) && trim((string)$rowM["TONASE"]) != "") {
        $label .= " / " . safeText($rowM["TONASE"]);
    }

    $machineLabels[] = $label;
    $machineNgPercent[] = round(floatval($rowM["NG_PERCENT_OK"]), 2);
}

sqlsrv_free_stmt($stmtMachine);

/*
    TOP 5 JENIS NG TERBESAR
*/
$sqlTopNgType = "
SELECT TOP 5
    NG_TYPE.NGT_CODE,
    NG_TYPE.NGT_DESC,
    SUM(ISNULL(NG_PROD.NGP_QTY, 0)) AS NG_QTY
FROM PRODUCTION
INNER JOIN NG_PROD ON PRODUCTION.PD_ID = NG_PROD.PD_ID
INNER JOIN NG_TYPE ON NG_PROD.NGT_ID = NG_TYPE.NGT_ID
INNER JOIN WO ON PRODUCTION.WO_ID = WO.WO_ID
INNER JOIN MAC ON WO.MAC_ID = MAC.MAC_ID
INNER JOIN MAG ON MAC.MAG_ID = MAG.MAG_ID
INNER JOIN ITEM_CUSTINFO_VIEW ON WO.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID
WHERE
    PRODUCTION.PD_DATE >= ?
    AND PRODUCTION.PD_DATE < ?
    $where
GROUP BY
    NG_TYPE.NGT_CODE,
    NG_TYPE.NGT_DESC
HAVING
    SUM(ISNULL(NG_PROD.NGP_QTY, 0)) > 0
ORDER BY
    NG_QTY DESC
";

$stmtTopNgType = sqlsrv_query($conn, $sqlTopNgType, $params);

if ($stmtTopNgType === false) {
    echo "<pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
    exit;
}

$topNgRows = array();
$topNgLabels = array();
$topNgQty = array();
$topNgUrls = array();

$baseUrlParams = array(
    "year" => $year,
    "month" => $month,
    "cust_code" => $cust_code,
    "cust_text" => $cust_text,
    "tonase" => $tonase,
    "mc_no" => $mc_no
);

while ($rowNg = sqlsrv_fetch_array($stmtTopNgType, SQLSRV_FETCH_ASSOC)) {
    $code = safeText(isset($rowNg["NGT_CODE"]) ? $rowNg["NGT_CODE"] : "");
    $desc = safeText(isset($rowNg["NGT_DESC"]) ? $rowNg["NGT_DESC"] : "");
    $qty  = floatval(isset($rowNg["NG_QTY"]) ? $rowNg["NG_QTY"] : 0);

    $label = $code;
    if ($desc != "") {
        $label .= " - " . $desc;
    }

    $urlParams = $baseUrlParams;
    $urlParams["ngt_code"] = $code;

    $url = "prod_report.php?" . http_build_query($urlParams);

    $topNgRows[] = array(
        "NGT_CODE" => $code,
        "NGT_DESC" => $desc,
        "NG_QTY" => $qty,
        "URL" => $url
    );

    $topNgLabels[] = $label;
    $topNgQty[] = round($qty, 2);
    $topNgUrls[] = $url;
}

sqlsrv_free_stmt($stmtTopNgType);

/*
    DETAIL NG YANG DIKLIK
*/
$selectedNgDesc = "";
$selectedNgMonthly = array();
$selectedNgMonthlyChart = array();
$selectedNgMachineLabels = array();
$selectedNgMachineQty = array();
$selectedNgItemRows = array();
$smsMaterialByPart = array();

for ($i = 1; $i <= 12; $i++) {
    $selectedNgMonthly[$i] = 0;
}

if ($ngt_code != "") {
    $paramsSelectedNg = $params;
    $paramsSelectedNg[] = $ngt_code;

    /*
        TREND NG PER BULAN
    */
    $sqlSelectedNgMonthly = "
    SELECT
        MONTH(PRODUCTION.PD_DATE) AS BULAN,
        MIN(NG_TYPE.NGT_DESC) AS NGT_DESC,
        SUM(ISNULL(NG_PROD.NGP_QTY, 0)) AS NG_QTY
    FROM PRODUCTION
    INNER JOIN NG_PROD ON PRODUCTION.PD_ID = NG_PROD.PD_ID
    INNER JOIN NG_TYPE ON NG_PROD.NGT_ID = NG_TYPE.NGT_ID
    INNER JOIN WO ON PRODUCTION.WO_ID = WO.WO_ID
    INNER JOIN MAC ON WO.MAC_ID = MAC.MAC_ID
    INNER JOIN MAG ON MAC.MAG_ID = MAG.MAG_ID
    INNER JOIN ITEM_CUSTINFO_VIEW ON WO.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID
    WHERE
        PRODUCTION.PD_DATE >= ?
        AND PRODUCTION.PD_DATE < ?
        $where
        AND NG_TYPE.NGT_CODE = ?
    GROUP BY
        MONTH(PRODUCTION.PD_DATE)
    ORDER BY
        MONTH(PRODUCTION.PD_DATE)
    ";

    $stmtSelectedNgMonthly = sqlsrv_query($conn, $sqlSelectedNgMonthly, $paramsSelectedNg);

    if ($stmtSelectedNgMonthly === false) {
        echo "<pre>";
        print_r(sqlsrv_errors());
        echo "</pre>";
        exit;
    }

    while ($rowSel = sqlsrv_fetch_array($stmtSelectedNgMonthly, SQLSRV_FETCH_ASSOC)) {
        $bulanIdx = intval($rowSel["BULAN"]);
        $selectedNgMonthly[$bulanIdx] = floatval($rowSel["NG_QTY"]);

        if ($selectedNgDesc == "" && isset($rowSel["NGT_DESC"])) {
            $selectedNgDesc = safeText($rowSel["NGT_DESC"]);
        }
    }

    sqlsrv_free_stmt($stmtSelectedNgMonthly);

    /*
        TOP MESIN PENYUMBANG NG YANG DIPILIH
    */
    $sqlSelectedNgMachine = "
    SELECT TOP 10
        MAC.MAC_CODE,
        MAG.MAG_STATION AS TONASE,
        SUM(ISNULL(NG_PROD.NGP_QTY, 0)) AS NG_QTY
    FROM PRODUCTION
    INNER JOIN NG_PROD ON PRODUCTION.PD_ID = NG_PROD.PD_ID
    INNER JOIN NG_TYPE ON NG_PROD.NGT_ID = NG_TYPE.NGT_ID
    INNER JOIN WO ON PRODUCTION.WO_ID = WO.WO_ID
    INNER JOIN MAC ON WO.MAC_ID = MAC.MAC_ID
    INNER JOIN MAG ON MAC.MAG_ID = MAG.MAG_ID
    INNER JOIN ITEM_CUSTINFO_VIEW ON WO.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID
    WHERE
        PRODUCTION.PD_DATE >= ?
        AND PRODUCTION.PD_DATE < ?
        $where
        AND NG_TYPE.NGT_CODE = ?
    GROUP BY
        MAC.MAC_CODE,
        MAG.MAG_STATION
    HAVING
        SUM(ISNULL(NG_PROD.NGP_QTY, 0)) > 0
    ORDER BY
        NG_QTY DESC
    ";

    $stmtSelectedNgMachine = sqlsrv_query($conn, $sqlSelectedNgMachine, $paramsSelectedNg);

    if ($stmtSelectedNgMachine === false) {
        echo "<pre>";
        print_r(sqlsrv_errors());
        echo "</pre>";
        exit;
    }

    while ($rowMacNg = sqlsrv_fetch_array($stmtSelectedNgMachine, SQLSRV_FETCH_ASSOC)) {
        $macLabel = safeText($rowMacNg["MAC_CODE"]);

        if (isset($rowMacNg["TONASE"]) && trim((string)$rowMacNg["TONASE"]) != "") {
            $macLabel .= " / " . safeText($rowMacNg["TONASE"]);
        }

        $selectedNgMachineLabels[] = $macLabel;
        $selectedNgMachineQty[] = round(floatval($rowMacNg["NG_QTY"]), 2);
    }

    sqlsrv_free_stmt($stmtSelectedNgMachine);

    /*
        DETAIL ITEM BERMASALAH UNTUK NG YANG DIPILIH
        Kolom item:
        ITEM_CUSTINFO_VIEW.part_code
        ITEM_CUSTINFO_VIEW.part_name
    */
    $sqlSelectedNgItem = "
    SELECT TOP 100
        ITEM_CUSTINFO_VIEW.part_code AS ITEM_CODE,
        ITEM_CUSTINFO_VIEW.part_name AS ITEM_NAME,
        ITEM_CUSTINFO_VIEW.CUST_CODE,
        ITEM_CUSTINFO_VIEW.CUST_COMP,

        SUM(ISNULL(NG_PROD.NGP_QTY, 0)) AS NG_QTY,
        SUM(ISNULL(PRODUCTION.PD_OK, 0) + ISNULL(PRODUCTION.PD_HO, 0)) AS OK_QTY,

        COUNT(DISTINCT MAC.MAC_CODE) AS MACHINE_COUNT,

        CASE
            WHEN SUM(ISNULL(PRODUCTION.PD_OK, 0) + ISNULL(PRODUCTION.PD_HO, 0)) = 0 THEN 0
            ELSE
                SUM(ISNULL(NG_PROD.NGP_QTY, 0)) * 100.0 /
                SUM(ISNULL(PRODUCTION.PD_OK, 0) + ISNULL(PRODUCTION.PD_HO, 0))
        END AS NG_PERCENT_OK

    FROM PRODUCTION
    INNER JOIN NG_PROD ON PRODUCTION.PD_ID = NG_PROD.PD_ID
    INNER JOIN NG_TYPE ON NG_PROD.NGT_ID = NG_TYPE.NGT_ID
    INNER JOIN WO ON PRODUCTION.WO_ID = WO.WO_ID
    INNER JOIN MAC ON WO.MAC_ID = MAC.MAC_ID
    INNER JOIN MAG ON MAC.MAG_ID = MAG.MAG_ID
    INNER JOIN ITEM_CUSTINFO_VIEW ON WO.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID

    WHERE
        PRODUCTION.PD_DATE >= ?
        AND PRODUCTION.PD_DATE < ?
        $where
        AND NG_TYPE.NGT_CODE = ?

    GROUP BY
        ITEM_CUSTINFO_VIEW.part_code,
        ITEM_CUSTINFO_VIEW.part_name,
        ITEM_CUSTINFO_VIEW.CUST_CODE,
        ITEM_CUSTINFO_VIEW.CUST_COMP

    HAVING
        SUM(ISNULL(NG_PROD.NGP_QTY, 0)) > 0

    ORDER BY
        NG_QTY DESC
    ";

    $stmtSelectedNgItem = sqlsrv_query($conn, $sqlSelectedNgItem, $paramsSelectedNg);

    if ($stmtSelectedNgItem === false) {
        echo "<pre>";
        print_r(sqlsrv_errors());
        echo "</pre>";
        exit;
    }

    while ($rowItemNg = sqlsrv_fetch_array($stmtSelectedNgItem, SQLSRV_FETCH_ASSOC)) {
        $selectedNgItemRows[] = array(
            "ITEM_CODE" => safeText(isset($rowItemNg["ITEM_CODE"]) ? $rowItemNg["ITEM_CODE"] : ""),
            "ITEM_NAME" => safeText(isset($rowItemNg["ITEM_NAME"]) ? $rowItemNg["ITEM_NAME"] : ""),
            "CUST_CODE" => safeText(isset($rowItemNg["CUST_CODE"]) ? $rowItemNg["CUST_CODE"] : ""),
            "CUST_COMP" => safeText(isset($rowItemNg["CUST_COMP"]) ? $rowItemNg["CUST_COMP"] : ""),
            "NG_QTY" => floatval(isset($rowItemNg["NG_QTY"]) ? $rowItemNg["NG_QTY"] : 0),
            "OK_QTY" => floatval(isset($rowItemNg["OK_QTY"]) ? $rowItemNg["OK_QTY"] : 0),
            "NG_PERCENT_OK" => floatval(isset($rowItemNg["NG_PERCENT_OK"]) ? $rowItemNg["NG_PERCENT_OK"] : 0),
            "HARGA_PART_IDR" => 0,
            "AMOUNT_IDR" => 0,
            "NG_AMOUNT_IDR" => 0,
            "MACHINE_COUNT" => intval(isset($rowItemNg["MACHINE_COUNT"]) ? $rowItemNg["MACHINE_COUNT"] : 0)
        );
    }

    sqlsrv_free_stmt($stmtSelectedNgItem);

    /*
        ACTUAL MATERIAL USAGE DARI SMS_DETAIL
        SMSD_QTY = pemakaian actual material
        Hanya material MAT.ITTY_CODE = 02

        MAT_CODE belakang -0 = VIRGIN
        Selain -0 = CRUSHER

        Contoh:
        100617-0 = VIRGIN
        100617-1 = CRUSHER
    */
    $sqlSmsMaterialByPart = "
    SELECT
        ITEM_CUSTINFO_VIEW.part_code AS PART_CODE,

        SUM(
            CASE
                WHEN RIGHT(RTRIM(ISNULL(MAT.ITEM_CODE, '')), 2) = '-0'
                THEN ISNULL(SMS_DETAIL.SMSD_QTY, 0)
                ELSE 0
            END
        ) AS VIRGIN_QTY,

        SUM(
            CASE
                WHEN RIGHT(RTRIM(ISNULL(MAT.ITEM_CODE, '')), 2) <> '-0'
                THEN ISNULL(SMS_DETAIL.SMSD_QTY, 0)
                ELSE 0
            END
        ) AS CRUSHER_QTY

    FROM SMS
    INNER JOIN WO ON SMS.WO_ID = WO.WO_ID
    INNER JOIN SMS_DETAIL ON SMS.SMS_ID = SMS_DETAIL.SMS_ID
    INNER JOIN ITEMS AS MAT ON SMS_DETAIL.ITEM_ID = MAT.ITEM_ID
    INNER JOIN MAC ON WO.MAC_ID = MAC.MAC_ID
    INNER JOIN MAG ON MAC.MAG_ID = MAG.MAG_ID
    INNER JOIN ITEM_CUSTINFO_VIEW ON WO.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID

    WHERE
        SMS.SMS_DATE >= ?
        AND SMS.SMS_DATE < ?
        AND MAT.ITTY_CODE = '02'
        $where

    GROUP BY
        ITEM_CUSTINFO_VIEW.part_code
    ";

    $stmtSmsMaterialByPart = sqlsrv_query($conn, $sqlSmsMaterialByPart, $params);

    if ($stmtSmsMaterialByPart === false) {
        echo "<pre>";
        print_r(sqlsrv_errors());
        echo "</pre>";
        exit;
    }

    while ($rowSmsMat = sqlsrv_fetch_array($stmtSmsMaterialByPart, SQLSRV_FETCH_ASSOC)) {
        $partCodeSms = safeText(isset($rowSmsMat["PART_CODE"]) ? $rowSmsMat["PART_CODE"] : "");

        if ($partCodeSms != "") {
            $virginQty = floatval(isset($rowSmsMat["VIRGIN_QTY"]) ? $rowSmsMat["VIRGIN_QTY"] : 0);
            $crusherQty = floatval(isset($rowSmsMat["CRUSHER_QTY"]) ? $rowSmsMat["CRUSHER_QTY"] : 0);
            $totalMaterialQty = $virginQty + $crusherQty;

            $virginPct = 0;
            $crusherPct = 0;
            $materialType = "-";

            if ($totalMaterialQty > 0) {
                $virginPct = ($virginQty / $totalMaterialQty) * 100;
                $crusherPct = ($crusherQty / $totalMaterialQty) * 100;

                if ($virginQty > 0 && $crusherQty > 0) {
                    $materialType = "MIXED";
                } elseif ($virginQty > 0) {
                    $materialType = "VIRGIN";
                } elseif ($crusherQty > 0) {
                    $materialType = "CRUSHER";
                }
            }

            $smsMaterialByPart[$partCodeSms] = array(
                "VIRGIN_QTY" => $virginQty,
                "CRUSHER_QTY" => $crusherQty,
                "TOTAL_MATERIAL_QTY" => $totalMaterialQty,
                "VIRGIN_PCT" => $virginPct,
                "CRUSHER_PCT" => $crusherPct,
                "MATERIAL_TYPE" => $materialType
            );
        }
    }

    sqlsrv_free_stmt($stmtSmsMaterialByPart);

    /*
        MERGE ACTUAL SMS MATERIAL KE DETAIL ITEM NG
    */
    for ($i = 0; $i < count($selectedNgItemRows); $i++) {
        $partCodeKey = $selectedNgItemRows[$i]["ITEM_CODE"];

        $selectedNgItemRows[$i]["VIRGIN_QTY"] = 0;
        $selectedNgItemRows[$i]["CRUSHER_QTY"] = 0;
        $selectedNgItemRows[$i]["TOTAL_MATERIAL_QTY"] = 0;
        $selectedNgItemRows[$i]["VIRGIN_PCT"] = 0;
        $selectedNgItemRows[$i]["CRUSHER_PCT"] = 0;
        $selectedNgItemRows[$i]["MATERIAL_TYPE"] = "-";
        $selectedNgItemRows[$i]["HARGA_PART_IDR"] = 0;
        $selectedNgItemRows[$i]["AMOUNT_IDR"] = 0;
        $selectedNgItemRows[$i]["NG_AMOUNT_IDR"] = 0;

        if (isset($partHargaByCode[$partCodeKey])) {
            $selectedNgItemRows[$i]["HARGA_PART_IDR"] = $partHargaByCode[$partCodeKey];
            $selectedNgItemRows[$i]["AMOUNT_IDR"] = $selectedNgItemRows[$i]["OK_QTY"] * $selectedNgItemRows[$i]["HARGA_PART_IDR"];
            $selectedNgItemRows[$i]["NG_AMOUNT_IDR"] = $selectedNgItemRows[$i]["NG_QTY"] * $selectedNgItemRows[$i]["HARGA_PART_IDR"];
        }

        if (isset($smsMaterialByPart[$partCodeKey])) {
            $selectedNgItemRows[$i]["VIRGIN_QTY"] = $smsMaterialByPart[$partCodeKey]["VIRGIN_QTY"];
            $selectedNgItemRows[$i]["CRUSHER_QTY"] = $smsMaterialByPart[$partCodeKey]["CRUSHER_QTY"];
            $selectedNgItemRows[$i]["TOTAL_MATERIAL_QTY"] = $smsMaterialByPart[$partCodeKey]["TOTAL_MATERIAL_QTY"];
            $selectedNgItemRows[$i]["VIRGIN_PCT"] = $smsMaterialByPart[$partCodeKey]["VIRGIN_PCT"];
            $selectedNgItemRows[$i]["CRUSHER_PCT"] = $smsMaterialByPart[$partCodeKey]["CRUSHER_PCT"];
            $selectedNgItemRows[$i]["MATERIAL_TYPE"] = $smsMaterialByPart[$partCodeKey]["MATERIAL_TYPE"];
        }
    }
}

for ($i = 1; $i <= 12; $i++) {
    $selectedNgMonthlyChart[] = round($selectedNgMonthly[$i], 2);
}


/*
    SEMUA ITEM PRODUKSI PADA PERIODE FILTER
    Tampil paling bawah, group by customer, sort by item_code.
*/
$allProductionItemRows = array();
$allProductionMaterialByKey = array();
$allProductionCustomerTotals = array();
$allProductionGrandTotal = array(
    "PRODUCTION_QTY" => 0,
    "NG_QTY" => 0,
    "PURGING_QTY" => 0,
    "VIRGIN_QTY" => 0,
    "CRUSHER_QTY" => 0,
    "TOTAL_MATERIAL_QTY" => 0,
    "AMOUNT_IDR" => 0,
    "NG_AMOUNT_IDR" => 0,
    "MACHINE_COUNT" => 0
);

$allProductionPriceFound = 0;
$allProductionPriceMissing = 0;

$sqlAllProductionItems = "
SELECT
    ITEM_CUSTINFO_VIEW.CUST_CODE,
    ITEM_CUSTINFO_VIEW.CUST_COMP,
    ITEM_CUSTINFO_VIEW.part_code AS ITEM_CODE,
    ITEM_CUSTINFO_VIEW.part_name AS ITEM_NAME,

    SUM(ISNULL(PRODUCTION.PD_OK, 0) + ISNULL(PRODUCTION.PD_HO, 0)) AS PRODUCTION_QTY,
    SUM(ISNULL(PRODUCTION.PD_NG, 0)) AS NG_QTY,
    SUM(ISNULL(PRODUCTION.PD_SC, 0)) AS PURGING_QTY,
    SUM(ISNULL(PRODUCTION.PD_SHOT, 0)) AS SHOT_QTY,
    SUM(ISNULL(PRODUCTION.PD_WKH, 0)) AS WORK_HOURS,
    SUM(ISNULL(PRODUCTION.PD_LOSTHOUR, 0)) AS LOST_HOURS,
    COUNT(DISTINCT MAC.MAC_CODE) AS MACHINE_COUNT,

    CASE
        WHEN SUM(ISNULL(PRODUCTION.PD_OK, 0) + ISNULL(PRODUCTION.PD_HO, 0)) = 0 THEN 0
        ELSE
            SUM(ISNULL(PRODUCTION.PD_NG, 0)) * 100.0 /
            SUM(ISNULL(PRODUCTION.PD_OK, 0) + ISNULL(PRODUCTION.PD_HO, 0))
    END AS NG_PERCENT_OK

FROM PRODUCTION
INNER JOIN WO ON PRODUCTION.WO_ID = WO.WO_ID
INNER JOIN MAC ON WO.MAC_ID = MAC.MAC_ID
INNER JOIN MAG ON MAC.MAG_ID = MAG.MAG_ID
INNER JOIN ITEM_CUSTINFO_VIEW ON WO.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID

WHERE
    PRODUCTION.PD_DATE >= ?
    AND PRODUCTION.PD_DATE < ?
    $where

GROUP BY
    ITEM_CUSTINFO_VIEW.CUST_CODE,
    ITEM_CUSTINFO_VIEW.CUST_COMP,
    ITEM_CUSTINFO_VIEW.part_code,
    ITEM_CUSTINFO_VIEW.part_name

HAVING
    SUM(ISNULL(PRODUCTION.PD_OK, 0) + ISNULL(PRODUCTION.PD_HO, 0)) > 0
    OR SUM(ISNULL(PRODUCTION.PD_NG, 0)) > 0
    OR SUM(ISNULL(PRODUCTION.PD_SC, 0)) > 0

ORDER BY
    ITEM_CUSTINFO_VIEW.CUST_CODE,
    ITEM_CUSTINFO_VIEW.part_code
";

$stmtAllProductionItems = sqlsrv_query($conn, $sqlAllProductionItems, $params);

if ($stmtAllProductionItems === false) {
    echo "<pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
    exit;
}

while ($rowAllItem = sqlsrv_fetch_array($stmtAllProductionItems, SQLSRV_FETCH_ASSOC)) {
    $allProductionItemRows[] = array(
        "CUST_CODE" => safeText(isset($rowAllItem["CUST_CODE"]) ? $rowAllItem["CUST_CODE"] : ""),
        "CUST_COMP" => safeText(isset($rowAllItem["CUST_COMP"]) ? $rowAllItem["CUST_COMP"] : ""),
        "ITEM_CODE" => safeText(isset($rowAllItem["ITEM_CODE"]) ? $rowAllItem["ITEM_CODE"] : ""),
        "ITEM_NAME" => safeText(isset($rowAllItem["ITEM_NAME"]) ? $rowAllItem["ITEM_NAME"] : ""),
        "PRODUCTION_QTY" => floatval(isset($rowAllItem["PRODUCTION_QTY"]) ? $rowAllItem["PRODUCTION_QTY"] : 0),
        "NG_QTY" => floatval(isset($rowAllItem["NG_QTY"]) ? $rowAllItem["NG_QTY"] : 0),
        "PURGING_QTY" => floatval(isset($rowAllItem["PURGING_QTY"]) ? $rowAllItem["PURGING_QTY"] : 0),
        "SHOT_QTY" => floatval(isset($rowAllItem["SHOT_QTY"]) ? $rowAllItem["SHOT_QTY"] : 0),
        "WORK_HOURS" => floatval(isset($rowAllItem["WORK_HOURS"]) ? $rowAllItem["WORK_HOURS"] : 0),
        "LOST_HOURS" => floatval(isset($rowAllItem["LOST_HOURS"]) ? $rowAllItem["LOST_HOURS"] : 0),
        "NG_PERCENT_OK" => floatval(isset($rowAllItem["NG_PERCENT_OK"]) ? $rowAllItem["NG_PERCENT_OK"] : 0),
        "MACHINE_COUNT" => intval(isset($rowAllItem["MACHINE_COUNT"]) ? $rowAllItem["MACHINE_COUNT"] : 0),
        "VIRGIN_QTY" => 0,
        "CRUSHER_QTY" => 0,
        "TOTAL_MATERIAL_QTY" => 0,
        "VIRGIN_PCT" => 0,
        "CRUSHER_PCT" => 0,
        "MATERIAL_TYPE" => "-",
        "HARGA_PART_IDR" => 0,
        "AMOUNT_IDR" => 0,
        "NG_AMOUNT_IDR" => 0
    );
}

sqlsrv_free_stmt($stmtAllProductionItems);

/*
    ACTUAL MATERIAL SMS UNTUK SEMUA ITEM PRODUKSI
    Sumber: SMS_DETAIL.SMSD_QTY, hanya MAT.ITTY_CODE = 02.
*/
$sqlAllProductionSmsMaterial = "
SELECT
    ITEM_CUSTINFO_VIEW.CUST_CODE,
    ITEM_CUSTINFO_VIEW.part_code AS ITEM_CODE,

    SUM(
        CASE
            WHEN RIGHT(RTRIM(ISNULL(MAT.ITEM_CODE, '')), 2) = '-0'
            THEN ISNULL(SMS_DETAIL.SMSD_QTY, 0)
            ELSE 0
        END
    ) AS VIRGIN_QTY,

    SUM(
        CASE
            WHEN LTRIM(RTRIM(ISNULL(MAT.ITEM_CODE, ''))) <> ''
             AND RIGHT(RTRIM(ISNULL(MAT.ITEM_CODE, '')), 2) <> '-0'
            THEN ISNULL(SMS_DETAIL.SMSD_QTY, 0)
            ELSE 0
        END
    ) AS CRUSHER_QTY

FROM SMS
INNER JOIN WO ON SMS.WO_ID = WO.WO_ID
INNER JOIN SMS_DETAIL ON SMS.SMS_ID = SMS_DETAIL.SMS_ID
INNER JOIN ITEMS AS MAT ON SMS_DETAIL.ITEM_ID = MAT.ITEM_ID
INNER JOIN MAC ON WO.MAC_ID = MAC.MAC_ID
INNER JOIN MAG ON MAC.MAG_ID = MAG.MAG_ID
INNER JOIN ITEM_CUSTINFO_VIEW ON WO.ITEM_ID = ITEM_CUSTINFO_VIEW.ITEM_ID

WHERE
    SMS.SMS_DATE >= ?
    AND SMS.SMS_DATE < ?
    AND MAT.ITTY_CODE = '02'
    $where

GROUP BY
    ITEM_CUSTINFO_VIEW.CUST_CODE,
    ITEM_CUSTINFO_VIEW.part_code
";

$stmtAllProductionSmsMaterial = sqlsrv_query($conn, $sqlAllProductionSmsMaterial, $params);

if ($stmtAllProductionSmsMaterial === false) {
    echo "<pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
    exit;
}

while ($rowAllMat = sqlsrv_fetch_array($stmtAllProductionSmsMaterial, SQLSRV_FETCH_ASSOC)) {
    $custKeyMat = safeText(isset($rowAllMat["CUST_CODE"]) ? $rowAllMat["CUST_CODE"] : "");
    $itemKeyMat = safeText(isset($rowAllMat["ITEM_CODE"]) ? $rowAllMat["ITEM_CODE"] : "");
    $mapKeyMat = $custKeyMat . "|" . $itemKeyMat;

    $virginQty = floatval(isset($rowAllMat["VIRGIN_QTY"]) ? $rowAllMat["VIRGIN_QTY"] : 0);
    $crusherQty = floatval(isset($rowAllMat["CRUSHER_QTY"]) ? $rowAllMat["CRUSHER_QTY"] : 0);
    $totalMaterialQty = $virginQty + $crusherQty;

    $virginPct = 0;
    $crusherPct = 0;
    $materialType = "-";

    if ($totalMaterialQty > 0) {
        $virginPct = ($virginQty / $totalMaterialQty) * 100;
        $crusherPct = ($crusherQty / $totalMaterialQty) * 100;

        if ($virginQty > 0 && $crusherQty > 0) {
            $materialType = "MIXED";
        } elseif ($virginQty > 0) {
            $materialType = "VIRGIN";
        } elseif ($crusherQty > 0) {
            $materialType = "CRUSHER";
        }
    }

    $allProductionMaterialByKey[$mapKeyMat] = array(
        "VIRGIN_QTY" => $virginQty,
        "CRUSHER_QTY" => $crusherQty,
        "TOTAL_MATERIAL_QTY" => $totalMaterialQty,
        "VIRGIN_PCT" => $virginPct,
        "CRUSHER_PCT" => $crusherPct,
        "MATERIAL_TYPE" => $materialType
    );
}

sqlsrv_free_stmt($stmtAllProductionSmsMaterial);

for ($i = 0; $i < count($allProductionItemRows); $i++) {
    $custKey = $allProductionItemRows[$i]["CUST_CODE"];
    $itemKey = $allProductionItemRows[$i]["ITEM_CODE"];
    $mapKey = $custKey . "|" . $itemKey;

    if (isset($partHargaByCode[$itemKey])) {
        $allProductionItemRows[$i]["HARGA_PART_IDR"] = $partHargaByCode[$itemKey];
        $allProductionItemRows[$i]["AMOUNT_IDR"] = $allProductionItemRows[$i]["PRODUCTION_QTY"] * $allProductionItemRows[$i]["HARGA_PART_IDR"];
        $allProductionItemRows[$i]["NG_AMOUNT_IDR"] = $allProductionItemRows[$i]["NG_QTY"] * $allProductionItemRows[$i]["HARGA_PART_IDR"];

        if ($allProductionItemRows[$i]["HARGA_PART_IDR"] > 0) {
            $allProductionPriceFound++;
        } else {
            $allProductionPriceMissing++;
        }
    } else {
        $allProductionPriceMissing++;
    }

    if (isset($allProductionMaterialByKey[$mapKey])) {
        $allProductionItemRows[$i]["VIRGIN_QTY"] = $allProductionMaterialByKey[$mapKey]["VIRGIN_QTY"];
        $allProductionItemRows[$i]["CRUSHER_QTY"] = $allProductionMaterialByKey[$mapKey]["CRUSHER_QTY"];
        $allProductionItemRows[$i]["TOTAL_MATERIAL_QTY"] = $allProductionMaterialByKey[$mapKey]["TOTAL_MATERIAL_QTY"];
        $allProductionItemRows[$i]["VIRGIN_PCT"] = $allProductionMaterialByKey[$mapKey]["VIRGIN_PCT"];
        $allProductionItemRows[$i]["CRUSHER_PCT"] = $allProductionMaterialByKey[$mapKey]["CRUSHER_PCT"];
        $allProductionItemRows[$i]["MATERIAL_TYPE"] = $allProductionMaterialByKey[$mapKey]["MATERIAL_TYPE"];
    }

    $customerGroupKey = $allProductionItemRows[$i]["CUST_CODE"] . "|" . $allProductionItemRows[$i]["CUST_COMP"];

    if (!isset($allProductionCustomerTotals[$customerGroupKey])) {
        $allProductionCustomerTotals[$customerGroupKey] = array(
            "CUST_CODE" => $allProductionItemRows[$i]["CUST_CODE"],
            "CUST_COMP" => $allProductionItemRows[$i]["CUST_COMP"],
            "PRODUCTION_QTY" => 0,
            "NG_QTY" => 0,
            "PURGING_QTY" => 0,
            "VIRGIN_QTY" => 0,
            "CRUSHER_QTY" => 0,
            "TOTAL_MATERIAL_QTY" => 0,
            "AMOUNT_IDR" => 0,
            "NG_AMOUNT_IDR" => 0,
            "ITEM_COUNT" => 0
        );
    }

    $allProductionCustomerTotals[$customerGroupKey]["PRODUCTION_QTY"] += $allProductionItemRows[$i]["PRODUCTION_QTY"];
    $allProductionCustomerTotals[$customerGroupKey]["NG_QTY"] += $allProductionItemRows[$i]["NG_QTY"];
    $allProductionCustomerTotals[$customerGroupKey]["PURGING_QTY"] += $allProductionItemRows[$i]["PURGING_QTY"];
    $allProductionCustomerTotals[$customerGroupKey]["VIRGIN_QTY"] += $allProductionItemRows[$i]["VIRGIN_QTY"];
    $allProductionCustomerTotals[$customerGroupKey]["CRUSHER_QTY"] += $allProductionItemRows[$i]["CRUSHER_QTY"];
    $allProductionCustomerTotals[$customerGroupKey]["TOTAL_MATERIAL_QTY"] += $allProductionItemRows[$i]["TOTAL_MATERIAL_QTY"];
    $allProductionCustomerTotals[$customerGroupKey]["AMOUNT_IDR"] += $allProductionItemRows[$i]["AMOUNT_IDR"];
    $allProductionCustomerTotals[$customerGroupKey]["NG_AMOUNT_IDR"] += $allProductionItemRows[$i]["NG_AMOUNT_IDR"];
    $allProductionCustomerTotals[$customerGroupKey]["ITEM_COUNT"]++;

    $allProductionGrandTotal["PRODUCTION_QTY"] += $allProductionItemRows[$i]["PRODUCTION_QTY"];
    $allProductionGrandTotal["NG_QTY"] += $allProductionItemRows[$i]["NG_QTY"];
    $allProductionGrandTotal["PURGING_QTY"] += $allProductionItemRows[$i]["PURGING_QTY"];
    $allProductionGrandTotal["VIRGIN_QTY"] += $allProductionItemRows[$i]["VIRGIN_QTY"];
    $allProductionGrandTotal["CRUSHER_QTY"] += $allProductionItemRows[$i]["CRUSHER_QTY"];
    $allProductionGrandTotal["TOTAL_MATERIAL_QTY"] += $allProductionItemRows[$i]["TOTAL_MATERIAL_QTY"];
    $allProductionGrandTotal["AMOUNT_IDR"] += $allProductionItemRows[$i]["AMOUNT_IDR"];
    $allProductionGrandTotal["NG_AMOUNT_IDR"] += $allProductionItemRows[$i]["NG_AMOUNT_IDR"];
}

/*
    DATA CHART
*/
$labels = array();
$chartOK = array();
$chartNG = array();
$chartPurging = array();
$chartNGPercentOK = array();
$chartNGPercentTotal = array();
$chartPurgingPercentOK = array();
$chartWorkHours = array();
$chartLostHours = array();
$chartLostPercent = array();
$chartAvailability = array();
$chartOKPerHour = array();

foreach ($displayMonthIndexes as $monthIndex) {
    $d = $data[$monthIndex];

    $labels[] = $d["bulan"];
    $chartOK[] = round($d["ok"], 2);
    $chartNG[] = round($d["ng"], 2);
    $chartPurging[] = round($d["purging"], 2);
    $chartNGPercentOK[] = round($d["ng_percent_ok"], 2);
    $chartNGPercentTotal[] = round($d["ng_percent_total"], 2);
    $chartPurgingPercentOK[] = round($d["purging_percent_ok"], 2);
    $chartWorkHours[] = round($d["work_hours"], 2);
    $chartLostHours[] = round($d["lost_hours"], 2);
    $chartLostPercent[] = round($d["lost_hour_percent"], 2);
    $chartAvailability[] = round($d["availability"], 2);
    $chartOKPerHour[] = round($d["ok_per_hour"], 2);
}

$plantLabel = isset($_SESSION["active_plant"]) ? strtoupper($_SESSION["active_plant"]) : "";
$serverLabel = isset($_SESSION["active_server"]) ? $_SESSION["active_server"] : "";

$resetUrl = "?year=" . urlencode($year);
$clearNgParams = $baseUrlParams;
$clearNgUrl = "prod_report.php?" . http_build_query($clearNgParams);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dashboard Laporan Produksi Tahunan / Bulanan</title>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>

    <style>
        body {
            margin: 0;
            padding: 20px 16px;
            font-family: Arial, Helvetica, sans-serif;
            background: #eef1f5;
            color: #333;
        }

        h2 {
            margin: 0 0 5px 0;
            font-size: 24px;
        }

        .subtitle {
            color: #555;
            margin-bottom: 18px;
            font-size: 13px;
        }

        .filter-box {
            background: #fff;
            border: 1px solid #dcdcdc;
            border-radius: 5px;
            padding: 10px 15px;
            margin-bottom: 18px;
        }

        .filter-box label {
            margin-right: 5px;
            font-size: 14px;
        }

        .filter-box input,
        .filter-box select {
            padding: 7px;
            margin-right: 8px;
            border: 1px solid #bbb;
            border-radius: 3px;
            height: 32px;
            box-sizing: border-box;
            font-size: 13px;
            background: #fff;
        }

        .filter-box button {
            padding: 8px 14px;
            background: #2c3e50;
            color: #fff;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            height: 32px;
            font-weight: bold;
        }

        .filter-box a {
            display: inline-block;
            padding: 8px 14px;
            background: #7f8c8d;
            color: #fff;
            border-radius: 3px;
            text-decoration: none;
            margin-left: 4px;
            height: 32px;
            box-sizing: border-box;
            font-weight: bold;
        }

        .select-tonase {
            width: 145px;
        }

        .select-machine {
            width: 185px;
        }

        .cards {
            display: table;
            width: 100%;
            border-spacing: 10px;
            margin-left: -10px;
            margin-bottom: 10px;
        }

        .card {
            display: table-cell;
            background: #fff;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            padding: 14px;
            width: 14%;
            vertical-align: top;
        }

        .card-title {
            font-size: 12px;
            color: #777;
            margin-bottom: 8px;
        }

        .card-value {
            font-size: 22px;
            font-weight: bold;
        }

        .card-small {
            margin-top: 5px;
            font-size: 12px;
            color: #777;
        }

        .green {
            color: #27ae60;
        }

        .red {
            color: #c0392b;
        }

        .orange {
            color: #e67e22;
        }

        .blue {
            color: #2980b9;
        }

        .panel {
            background: #fff;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
        }

        .panel h3 {
            margin-top: 0;
            font-size: 16px;
        }

        .row {
            display: table;
            width: 100%;
            border-spacing: 10px;
            margin-left: -10px;
        }

        .col-6 {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th {
            background: #34495e;
            color: #fff;
            padding: 8px;
            border: 1px solid #ccc;
            white-space: nowrap;
        }

        td {
            padding: 7px;
            border: 1px solid #ddd;
            text-align: right;
            white-space: nowrap;
        }

        td:first-child {
            text-align: left;
            font-weight: bold;
        }

        tfoot th {
            background: #2c3e50;
        }

        .note {
            font-size: 12px;
            color: #777;
            margin-top: 8px;
        }

        .ac-wrap {
            position: relative;
            display: inline-block;
            vertical-align: middle;
        }

        .ac-input {
            width: 245px;
        }

        .ac-list {
            display: none;
            position: absolute;
            top: 34px;
            left: 0;
            width: 440px;
            max-height: 260px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #999;
            border-radius: 3px;
            z-index: 9999;
            box-shadow: 0 3px 8px rgba(0,0,0,0.18);
        }

        .ac-item {
            padding: 8px 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            font-size: 12px;
        }

        .ac-item:hover,
        .ac-item.active {
            background: #2c3e50;
            color: #fff;
        }

        .ac-code {
            font-weight: bold;
            display: inline-block;
            min-width: 85px;
        }

        .ac-name {
            color: #555;
        }

        .ac-item:hover .ac-name,
        .ac-item.active .ac-name {
            color: #fff;
        }

        .ac-abbr {
            color: #888;
            font-size: 11px;
            margin-left: 5px;
        }

        .ac-item:hover .ac-abbr,
        .ac-item.active .ac-abbr {
            color: #eee;
        }

        .ng-link {
            color: #c0392b;
            font-weight: bold;
            text-decoration: none;
        }

        .ng-link:hover {
            text-decoration: underline;
        }

        .click-note {
            font-size: 12px;
            color: #777;
            margin-top: 6px;
        }

        .table-ng td {
            text-align: left;
        }

        .table-ng td.qty {
            text-align: right;
            font-weight: bold;
        }

        .table-item-ng td {
            text-align: left;
        }

        .table-item-ng td.num {
            text-align: right;
            font-weight: bold;
        }

        .table-item-ng .item-code {
            color: #c0392b;
            font-weight: bold;
        }

        .table-item-ng .item-name {
            color: #333;
        }

        .material-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
        }

        .material-virgin {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .material-crusher {
            background: #fff3e0;
            color: #e65100;
        }

        .material-mixed {
            background: #e3f2fd;
            color: #1565c0;
        }

        .material-empty {
            background: #eeeeee;
            color: #777;
        }

        .table-monthly-production th.mat-head {
            background: #263f55;
        }

        .table-monthly-production td.mat-num,
        .table-monthly-production th.mat-num {
            text-align: right;
            font-weight: bold;
        }

        .table-monthly-production td.mat-center,
        .table-monthly-production th.mat-center {
            text-align: center;
        }

        .btn-clear-ng {
            display: inline-block;
            background: #c0392b;
            color: #fff;
            padding: 6px 10px;
            border-radius: 3px;
            text-decoration: none;
            font-size: 12px;
            margin-left: 10px;
        }

        .table-all-production td {
            text-align: left;
        }

        .table-all-production td.num,
        .table-all-production th.num {
            text-align: right;
            font-weight: bold;
        }

        .table-all-production td.center,
        .table-all-production th.center {
            text-align: center;
        }

        .table-all-production .customer-group-row td {
            background: #e8eef5;
            color: #263f55;
            font-weight: bold;
            text-align: left;
            font-size: 13px;
        }

        .table-all-production .customer-subtotal-row td,
        .table-all-production .customer-subtotal-row th {
            background: #f5f7fa;
            font-weight: bold;
        }

        .table-all-production .grand-total-row th {
            background: #2c3e50;
            color: #ffffff;
        }

        .table-all-production .item-code {
            color: #c0392b;
            font-weight: bold;
        }

    </style>
</head>
<body>

<h2>Dashboard Laporan Produksi Tahunan / Bulanan</h2>

<div class="subtitle">
    Plant: <?php echo h($plantLabel); ?> |
    Server: <?php echo h($serverLabel); ?> |
    Periode: <?php echo h($periodeLabel); ?>
</div>

<div class="filter-box">
    <form method="get" id="filterForm">
        <label>Tahun:</label>
        <input type="text" name="year" value="<?php echo h($year); ?>" style="width:86px;">

        <label>Bulan:</label>
        <select name="month" id="month" style="width:120px;">
            <option value="0" <?php echo ($month == 0 ? "selected" : ""); ?>>Semua Bulan</option>
            <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?php echo h($i); ?>" <?php echo ($month == $i ? "selected" : ""); ?>>
                    <?php echo h($monthNames[$i]); ?>
                </option>
            <?php endfor; ?>
        </select>

        <label>Customer:</label>
        <span class="ac-wrap">
            <input
                type="text"
                id="cust_text"
                name="cust_text"
                value="<?php echo h($cust_text != '' ? $cust_text : $cust_code); ?>"
                placeholder="Ketik customer"
                autocomplete="off"
                class="ac-input"
            >

            <input
                type="hidden"
                id="cust_code"
                name="cust_code"
                value="<?php echo h($cust_code); ?>"
            >

            <div id="customer_list" class="ac-list"></div>
        </span>

        <label>Tonase:</label>
        <select name="tonase" id="tonase" class="select-tonase">
            <option value="">Semua Tonase</option>
            <?php foreach ($tonaseList as $t): ?>
                <option value="<?php echo h($t); ?>" <?php echo ($tonase == $t ? "selected" : ""); ?>>
                    <?php echo h($t); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Mesin:</label>
        <select name="mc_no" id="mc_no" class="select-machine">
            <option value="">Semua Mesin</option>
            <?php foreach ($machineRows as $m): ?>
                <?php
                    $optMac = $m["MAC_CODE"];
                    $optTonase = $m["TONASE"];
                    $selectedMachine = ($mc_no == $optMac) ? "selected" : "";
                    $labelMachine = $optMac;

                    if ($optTonase != "") {
                        $labelMachine .= " - " . $optTonase;
                    }
                ?>
                <option
                    value="<?php echo h($optMac); ?>"
                    data-tonase="<?php echo h($optTonase); ?>"
                    <?php echo $selectedMachine; ?>
                >
                    <?php echo h($labelMachine); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ($ngt_code != ""): ?>
            <input type="hidden" name="ngt_code" value="<?php echo h($ngt_code); ?>">
        <?php endif; ?>

        <button type="submit">Tampilkan</button>
        <a href="<?php echo h($resetUrl); ?>">Reset</a>
    </form>
</div>

<div class="cards">
    <div class="card">
        <div class="card-title">Total OK</div>
        <div class="card-value green"><?php echo angka($total_ok); ?></div>
        <div class="card-small">PD_OK + PD_HO</div>
    </div>

    <div class="card">
        <div class="card-title">Total NG</div>
        <div class="card-value red"><?php echo angka($total_ng); ?></div>
        <div class="card-small">Reject / NG</div>
    </div>

    <div class="card">
        <div class="card-title">NG % terhadap OK</div>
        <div class="card-value red"><?php echo angka($total_ng_percent_ok, 2); ?>%</div>
        <div class="card-small">NG / OK x 100</div>
    </div>

    <div class="card">
        <div class="card-title">NG % terhadap Total</div>
        <div class="card-value orange"><?php echo angka($total_ng_percent_total, 2); ?>%</div>
        <div class="card-small">NG / (OK + NG) x 100</div>
    </div>

    <div class="card">
        <div class="card-title">Purging</div>
        <div class="card-value orange"><?php echo angka($total_purging); ?></div>
        <div class="card-small"><?php echo angka($total_purging_percent_ok, 2); ?>% dari OK</div>
    </div>

    <div class="card">
        <div class="card-title">Availability</div>
        <div class="card-value blue"><?php echo angka($total_availability, 2); ?>%</div>
        <div class="card-small">Work / (Work + Lost)</div>
    </div>
</div>

<div class="cards">
    <div class="card">
        <div class="card-title">Work Hours</div>
        <div class="card-value blue"><?php echo angka($total_work_hours, 2); ?></div>
    </div>

    <div class="card">
        <div class="card-title">Lost Hours</div>
        <div class="card-value red"><?php echo angka($total_lost_hours, 2); ?></div>
        <div class="card-small"><?php echo angka($total_lost_percent, 2); ?>% dari total hours</div>
    </div>

    <div class="card">
        <div class="card-title">Total Shot</div>
        <div class="card-value"><?php echo angka($total_shot); ?></div>
    </div>

    <div class="card">
        <div class="card-title">OK per Hour</div>
        <div class="card-value green"><?php echo angka($total_ok_per_hour, 2); ?></div>
    </div>

    <div class="card">
        <div class="card-title">Filter Customer</div>
        <div class="card-value" style="font-size:16px;">
            <?php echo $cust_text != "" ? h($cust_text) : ($cust_code != "" ? h($cust_code) : "-"); ?>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Filter Mesin / Tonase</div>
        <div class="card-value" style="font-size:16px;">
            <?php
                if ($mc_no != "" && $tonase != "") {
                    echo h($mc_no . " / " . $tonase);
                } elseif ($mc_no != "") {
                    echo h($mc_no);
                } elseif ($tonase != "") {
                    echo h($tonase);
                } else {
                    echo "-";
                }
            ?>
        </div>
    </div>
</div>

<div class="cards">
    <div class="card">
        <div class="card-title">Global Virgin Usage</div>
        <div class="card-value green"><?php echo angka($globalVirginQty, 2); ?></div>
        <div class="card-small">Actual SMSD_QTY</div>
    </div>

    <div class="card">
        <div class="card-title">Global Crusher Usage</div>
        <div class="card-value orange"><?php echo angka($globalCrusherQty, 2); ?></div>
        <div class="card-small">Actual SMSD_QTY</div>
    </div>

    <div class="card">
        <div class="card-title">Virgin % Global</div>
        <div class="card-value green"><?php echo angka($globalVirginPct, 2); ?>%</div>
        <div class="card-small">Virgin / Total Material</div>
    </div>

    <div class="card">
        <div class="card-title">Crusher % Global</div>
        <div class="card-value orange"><?php echo angka($globalCrusherPct, 2); ?>%</div>
        <div class="card-small">Crusher / Total Material</div>
    </div>

    <div class="card">
        <div class="card-title">Total Material Actual</div>
        <div class="card-value blue"><?php echo angka($globalMaterialTotalQty, 2); ?></div>
        <div class="card-small">Virgin + Crusher</div>
    </div>

    <div class="card">
        <div class="card-title">Material Status</div>
        <div class="card-value" style="font-size:16px;">
            <?php
                $globalMatClass = "material-empty";

                if ($globalMaterialType == "VIRGIN") {
                    $globalMatClass = "material-virgin";
                } elseif ($globalMaterialType == "CRUSHER") {
                    $globalMatClass = "material-crusher";
                } elseif ($globalMaterialType == "MIXED") {
                    $globalMatClass = "material-mixed";
                }
            ?>
            <span class="material-badge <?php echo h($globalMatClass); ?>">
                <?php echo h($globalMaterialType); ?>
            </span>
        </div>
        <div class="card-small">MAT_CODE -0 / selain -0</div>
    </div>

</div>

<div class="cards">
    <div class="card">
        <div class="card-title">Total Amount IDR</div>
        <div class="card-value blue" style="font-size:18px;">Rp <?php echo angka($allProductionGrandTotal["AMOUNT_IDR"], 2); ?></div>
        <div class="card-small">Production Qty x Harga Part</div>
    </div>

    <div class="card">
        <div class="card-title">Total NG Amount IDR</div>
        <div class="card-value red" style="font-size:18px;">Rp <?php echo angka($allProductionGrandTotal["NG_AMOUNT_IDR"], 2); ?></div>
        <div class="card-small">NG Qty x Harga Part</div>
    </div>

    <div class="card">
        <div class="card-title">Avg Harga / Pcs</div>
        <div class="card-value" style="font-size:18px;">
            Rp
            <?php
                $avgHargaPartDashboard = 0;
                if ($allProductionGrandTotal["PRODUCTION_QTY"] > 0) {
                    $avgHargaPartDashboard = $allProductionGrandTotal["AMOUNT_IDR"] / $allProductionGrandTotal["PRODUCTION_QTY"];
                }
                echo angka($avgHargaPartDashboard, 2);
            ?>
        </div>
        <div class="card-small">Total prod amount / Production Qty</div>
    </div>

    <div class="card">
        <div class="card-title">Item Ada Harga</div>
        <div class="card-value green"><?php echo angka($allProductionPriceFound); ?></div>
        <div class="card-small">BOM + PO terakhir valid</div>
    </div>

    <div class="card">
        <div class="card-title">Item Tanpa Harga</div>
        <div class="card-value red"><?php echo angka($allProductionPriceMissing); ?></div>
        <div class="card-small">Harga part = 0 / tidak ada</div>
    </div>

    <div class="card">
        <div class="card-title">Dasar Harga</div>
        <div class="card-value" style="font-size:15px;">BOM Internal</div>
        <div class="card-small">ITTY 02/03 + PO terakhir</div>
    </div>

    <div class="card">
        <div class="card-title">Currency</div>
        <div class="card-value" style="font-size:15px;">IDR</div>
        <div class="card-small">USD dikonversi VRATE</div>
    </div>
</div>

<div class="panel">
    <h3>Grafik Quantity OK, NG, dan Purging per Bulan</h3>
    <canvas id="chartQty" height="80"></canvas>
</div>

<div class="row">
    <div class="col-6">
        <div class="panel">
            <h3>Grafik Persentase NG terhadap OK</h3>
            <canvas id="chartNgPercent" height="130"></canvas>
            <div class="note">Rumus: NG / OK x 100</div>
        </div>
    </div>

    <div class="col-6">
        <div class="panel">
            <h3>Grafik Persentase NG terhadap Total Produksi</h3>
            <canvas id="chartNgTotalPercent" height="130"></canvas>
            <div class="note">Rumus: NG / (OK + NG) x 100</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-6">
        <div class="panel">
            <h3>Top 5 Penyumbang NG Terbesar</h3>
            <canvas id="chartTopNgType" height="150"></canvas>
            <div class="click-note">Klik bar grafik untuk melihat detail NG per bulan, mesin, dan item bermasalah.</div>
        </div>
    </div>

    <div class="col-6">
        <div class="panel">
            <h3>Daftar Top 5 NG Terbesar</h3>

            <table class="table-ng">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode NG</th>
                        <th>Deskripsi</th>
                        <th>Qty NG</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($topNgRows) > 0): ?>
                        <?php $noNg = 1; ?>
                        <?php foreach ($topNgRows as $ngRow): ?>
                            <tr>
                                <td><?php echo $noNg; ?></td>
                                <td>
                                    <a class="ng-link" href="<?php echo h($ngRow["URL"]); ?>">
                                        <?php echo h($ngRow["NGT_CODE"]); ?>
                                    </a>
                                </td>
                                <td>
                                    <a class="ng-link" href="<?php echo h($ngRow["URL"]); ?>">
                                        <?php echo h($ngRow["NGT_DESC"]); ?>
                                    </a>
                                </td>
                                <td class="qty"><?php echo angka($ngRow["NG_QTY"]); ?></td>
                            </tr>
                            <?php $noNg++; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">Tidak ada data NG.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="click-note">Klik kode atau deskripsi NG untuk drilldown.</div>
        </div>
    </div>
</div>

<?php if ($ngt_code != ""): ?>
<div class="panel">
    <h3>
        Detail NG:
        <?php echo h($ngt_code); ?>
        <?php echo $selectedNgDesc != "" ? " - " . h($selectedNgDesc) : ""; ?>

        <a class="btn-clear-ng" href="<?php echo h($clearNgUrl); ?>">Tutup Detail NG</a>
    </h3>

    <div class="row">
        <div class="col-6">
            <div class="panel">
                <h3>Trend Qty NG per Bulan</h3>
                <canvas id="chartSelectedNgMonthly" height="140"></canvas>
            </div>
        </div>

        <div class="col-6">
            <div class="panel">
                <h3>Top Mesin Penyumbang NG Ini</h3>
                <canvas id="chartSelectedNgMachine" height="140"></canvas>
            </div>
        </div>
    </div>

    <div class="panel">
        <h3>Detail Item Bermasalah + Persentase Actual Material SMS</h3>

        <table class="table-item-ng">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Part Code</th>
                    <th>Part Name</th>
                    <th>Customer</th>
                    <th>Qty NG</th>
                    <th>Production Qty</th>
                    <th>Harga Part (IDR)</th>
                    <th>NG Amount IDR</th>
                    <th>Prod Amount IDR</th>
                    <th>NG % terhadap Prod</th>
                    <th>Virgin Usage</th>
                    <th>Crusher Usage</th>
                    <th>Virgin %</th>
                    <th>Crusher %</th>
                    <th>Material</th>
                    <th>Jumlah Mesin</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($selectedNgItemRows) > 0): ?>
                    <?php $noItemNg = 1; ?>
                    <?php foreach ($selectedNgItemRows as $itemNg): ?>
                        <tr>
                            <td><?php echo $noItemNg; ?></td>

                            <td class="item-code">
                                <?php echo h($itemNg["ITEM_CODE"]); ?>
                            </td>

                            <td class="item-name">
                                <?php echo h($itemNg["ITEM_NAME"]); ?>
                            </td>

                            <td>
                                <?php
                                    $custLabel = $itemNg["CUST_CODE"];

                                    if ($itemNg["CUST_COMP"] != "") {
                                        $custLabel .= " - " . $itemNg["CUST_COMP"];
                                    }

                                    echo h($custLabel);
                                ?>
                            </td>

                            <td class="num">
                                <?php echo angka($itemNg["NG_QTY"]); ?>
                            </td>

                            <td class="num">
                                <?php echo angka($itemNg["OK_QTY"]); ?>
                            </td>

                            <td class="num">
                                Rp <?php echo angka(isset($itemNg["HARGA_PART_IDR"]) ? $itemNg["HARGA_PART_IDR"] : 0, 2); ?>
                            </td>

                            <td class="num">
                                Rp <?php echo angka(isset($itemNg["NG_AMOUNT_IDR"]) ? $itemNg["NG_AMOUNT_IDR"] : 0, 2); ?>
                            </td>

                            <td class="num">
                                Rp <?php echo angka(isset($itemNg["AMOUNT_IDR"]) ? $itemNg["AMOUNT_IDR"] : 0, 2); ?>
                            </td>

                            <td class="num">
                                <?php echo angka($itemNg["NG_PERCENT_OK"], 2); ?>%
                            </td>

                            <td class="num">
                                <?php echo angka(isset($itemNg["VIRGIN_QTY"]) ? $itemNg["VIRGIN_QTY"] : 0, 2); ?>
                            </td>

                            <td class="num">
                                <?php echo angka(isset($itemNg["CRUSHER_QTY"]) ? $itemNg["CRUSHER_QTY"] : 0, 2); ?>
                            </td>

                            <td class="num">
                                <?php echo angka(isset($itemNg["VIRGIN_PCT"]) ? $itemNg["VIRGIN_PCT"] : 0, 2); ?>%
                            </td>

                            <td class="num">
                                <?php echo angka(isset($itemNg["CRUSHER_PCT"]) ? $itemNg["CRUSHER_PCT"] : 0, 2); ?>%
                            </td>

                            <td>
                                <?php
                                    $matType = isset($itemNg["MATERIAL_TYPE"]) ? $itemNg["MATERIAL_TYPE"] : "-";
                                    $matClass = "material-empty";

                                    if ($matType == "VIRGIN") {
                                        $matClass = "material-virgin";
                                    } elseif ($matType == "CRUSHER") {
                                        $matClass = "material-crusher";
                                    } elseif ($matType == "MIXED") {
                                        $matClass = "material-mixed";
                                    }
                                ?>
                                <span class="material-badge <?php echo h($matClass); ?>">
                                    <?php echo h($matType); ?>
                                </span>
                            </td>

                            <td class="num">
                                <?php echo angka($itemNg["MACHINE_COUNT"]); ?>
                            </td>
                        </tr>
                        <?php $noItemNg++; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="16">Tidak ada detail item untuk NG ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="click-note">
            Urutan berdasarkan Qty NG terbesar. Harga Part dihitung dari BOM internal + harga PO terakhir. Prod Amount IDR = Production Qty x Harga Part. NG Amount IDR = NG Qty x Harga Part. Virgin/Crusher dihitung dari actual pemakaian SMS_DETAIL.SMSD_QTY, hanya material ITTY_CODE 02. MAT_CODE akhiran -0 = VIRGIN, selain itu = CRUSHER. Data mengikuti filter tahun, customer, tonase, dan mesin.
        </div>
    </div>

    <div class="click-note">
        Filter aktif:
        <?php echo h($periodeLabel); ?>,
        Customer <?php echo $cust_text != "" ? h($cust_text) : "-"; ?>,
        Tonase <?php echo $tonase != "" ? h($tonase) : "-"; ?>,
        Mesin <?php echo $mc_no != "" ? h($mc_no) : "-"; ?>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-6">
        <div class="panel">
            <h3>Work Hours vs Lost Hours</h3>
            <canvas id="chartHours" height="130"></canvas>
        </div>
    </div>

    <div class="col-6">
        <div class="panel">
            <h3>Availability dan Lost Hour %</h3>
            <canvas id="chartAvailability" height="130"></canvas>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-6">
        <div class="panel">
            <h3>Produktivitas OK per Hour</h3>
            <canvas id="chartProductivity" height="130"></canvas>
        </div>
    </div>

    <div class="col-6">
        <div class="panel">
            <h3>Top 10 Mesin berdasarkan NG % terhadap OK</h3>
            <canvas id="chartMachineNg" height="130"></canvas>
        </div>
    </div>
</div>

<div class="panel">
    <h3>Detail Produksi <?php echo h($periodeLabel); ?></h3>

    <table class="table-monthly-production">
        <thead>
            <tr>
                <th>Bulan</th>
                <th>OK</th>
                <th>NG</th>
                <th>NG % OK</th>
                <th>NG % Total</th>
                <th>Purging</th>
                <th>Purging % OK</th>
                <th>Work Hours</th>
                <th>Lost Hours</th>
                <th>Lost %</th>
                <th>Availability</th>
                <th>Shot</th>
                <th>OK / Hour</th>
                <th>Avg Cavity</th>
                <th>Avg Cycle Time</th>
                <th class="mat-head">Virgin Usage</th>
                <th class="mat-head">Crusher Usage</th>
                <th class="mat-head">Total Material</th>
                <th class="mat-head">Virgin %</th>
                <th class="mat-head">Crusher %</th>
                <th class="mat-head">Material</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($displayMonthIndexes as $monthNo): ?>
            <?php
                $d = $data[$monthNo];
                $gm = $globalMaterialMonthly[$monthNo];
                $gmMatType = isset($gm["material_type"]) ? $gm["material_type"] : "-";
                $gmMatClass = "material-empty";

                if ($gmMatType == "VIRGIN") {
                    $gmMatClass = "material-virgin";
                } elseif ($gmMatType == "CRUSHER") {
                    $gmMatClass = "material-crusher";
                } elseif ($gmMatType == "MIXED") {
                    $gmMatClass = "material-mixed";
                }
            ?>
            <tr>
                <td><?php echo h($d["bulan"]); ?></td>
                <td><?php echo angka($d["ok"]); ?></td>
                <td><?php echo angka($d["ng"]); ?></td>
                <td><?php echo angka($d["ng_percent_ok"], 2); ?>%</td>
                <td><?php echo angka($d["ng_percent_total"], 2); ?>%</td>
                <td><?php echo angka($d["purging"]); ?></td>
                <td><?php echo angka($d["purging_percent_ok"], 2); ?>%</td>
                <td><?php echo angka($d["work_hours"], 2); ?></td>
                <td><?php echo angka($d["lost_hours"], 2); ?></td>
                <td><?php echo angka($d["lost_hour_percent"], 2); ?>%</td>
                <td><?php echo angka($d["availability"], 2); ?>%</td>
                <td><?php echo angka($d["shot"]); ?></td>
                <td><?php echo angka($d["ok_per_hour"], 2); ?></td>
                <td><?php echo angka($d["avg_cavity"], 2); ?></td>
                <td><?php echo angka($d["avg_cycletime"], 2); ?></td>
                <td class="mat-num"><?php echo angka($gm["virgin_qty"], 2); ?></td>
                <td class="mat-num"><?php echo angka($gm["crusher_qty"], 2); ?></td>
                <td class="mat-num"><?php echo angka($gm["total_qty"], 2); ?></td>
                <td class="mat-num"><?php echo angka($gm["virgin_pct"], 2); ?>%</td>
                <td class="mat-num"><?php echo angka($gm["crusher_pct"], 2); ?>%</td>
                <td class="mat-center">
                    <span class="material-badge <?php echo h($gmMatClass); ?>">
                        <?php echo h($gmMatType); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th>Total</th>
                <th><?php echo angka($total_ok); ?></th>
                <th><?php echo angka($total_ng); ?></th>
                <th><?php echo angka($total_ng_percent_ok, 2); ?>%</th>
                <th><?php echo angka($total_ng_percent_total, 2); ?>%</th>
                <th><?php echo angka($total_purging); ?></th>
                <th><?php echo angka($total_purging_percent_ok, 2); ?>%</th>
                <th><?php echo angka($total_work_hours, 2); ?></th>
                <th><?php echo angka($total_lost_hours, 2); ?></th>
                <th><?php echo angka($total_lost_percent, 2); ?>%</th>
                <th><?php echo angka($total_availability, 2); ?>%</th>
                <th><?php echo angka($total_shot); ?></th>
                <th><?php echo angka($total_ok_per_hour, 2); ?></th>
                <th colspan="2"></th>
                <th class="mat-num"><?php echo angka($globalVirginQty, 2); ?></th>
                <th class="mat-num"><?php echo angka($globalCrusherQty, 2); ?></th>
                <th class="mat-num"><?php echo angka($globalMaterialTotalQty, 2); ?></th>
                <th class="mat-num"><?php echo angka($globalVirginPct, 2); ?>%</th>
                <th class="mat-num"><?php echo angka($globalCrusherPct, 2); ?>%</th>
                <th class="mat-center">
                    <?php
                        $globalMatClassFooter = "material-empty";

                        if ($globalMaterialType == "VIRGIN") {
                            $globalMatClassFooter = "material-virgin";
                        } elseif ($globalMaterialType == "CRUSHER") {
                            $globalMatClassFooter = "material-crusher";
                        } elseif ($globalMaterialType == "MIXED") {
                            $globalMatClassFooter = "material-mixed";
                        }
                    ?>
                    <span class="material-badge <?php echo h($globalMatClassFooter); ?>">
                        <?php echo h($globalMaterialType); ?>
                    </span>
                </th>
            </tr>
        </tfoot>
    </table>

    <div class="note">
        Pemakaian material bulanan diambil dari actual <b>SMS_DETAIL.SMSD_QTY</b>, hanya material <b>ITTY_CODE 02</b>.
        Rule: <b>MAT_CODE</b> belakang <b>-0</b> = <b>VIRGIN</b>, selain <b>-0</b> = <b>CRUSHER</b>.
        Persentase dihitung dari Virgin Usage / Total Material dan Crusher Usage / Total Material.
    </div>
</div>

<div class="panel">
    <h3>Semua Item Produksi <?php echo h($periodeLabel); ?> - Group by Customer, Sort by Item Code</h3>

    <table class="table-all-production">
        <thead>
            <tr>
                <th>No</th>
                <th>Part Code</th>
                <th>Part Name</th>
                <th class="num">Production Qty</th>
                <th class="num">Harga Part (IDR)</th>
                <th class="num">Prod Amount IDR</th>
                <th class="num">NG Qty</th>
                <th class="num">NG Amount IDR</th>
                <th class="num">NG % Prod</th>
                <th class="num">Purging</th>
                <th class="num">Virgin Usage</th>
                <th class="num">Crusher Usage</th>
                <th class="num">Virgin %</th>
                <th class="num">Crusher %</th>
                <th class="center">Material</th>
                <th class="num">Mesin</th>
            </tr>
        </thead>

        <tbody>
            <?php if (count($allProductionItemRows) > 0): ?>
                <?php
                    $lastCustomerGroupKey = "";
                    $noAllItem = 1;
                ?>

                <?php for ($i = 0; $i < count($allProductionItemRows); $i++): ?>
                    <?php
                        $prodItem = $allProductionItemRows[$i];
                        $customerGroupKey = $prodItem["CUST_CODE"] . "|" . $prodItem["CUST_COMP"];

                        if ($customerGroupKey != $lastCustomerGroupKey):
                            if ($lastCustomerGroupKey != "" && isset($allProductionCustomerTotals[$lastCustomerGroupKey])):
                                $prevTotal = $allProductionCustomerTotals[$lastCustomerGroupKey];
                                $prevTotalMaterial = $prevTotal["TOTAL_MATERIAL_QTY"];
                                $prevVirginPct = 0;
                                $prevCrusherPct = 0;

                                if ($prevTotalMaterial > 0) {
                                    $prevVirginPct = ($prevTotal["VIRGIN_QTY"] / $prevTotalMaterial) * 100;
                                    $prevCrusherPct = ($prevTotal["CRUSHER_QTY"] / $prevTotalMaterial) * 100;
                                }
                    ?>
                                <tr class="customer-subtotal-row">
                                    <td colspan="3">Subtotal Customer</td>
                                    <td class="num"><?php echo angka($prevTotal["PRODUCTION_QTY"]); ?></td>
                                    <td class="num">-</td>
                                    <td class="num">Rp <?php echo angka($prevTotal["AMOUNT_IDR"], 2); ?></td>
                                    <td class="num"><?php echo angka($prevTotal["NG_QTY"]); ?></td>
                                    <td class="num">Rp <?php echo angka($prevTotal["NG_AMOUNT_IDR"], 2); ?></td>
                                    <td class="num">
                                        <?php
                                            $prevNgPct = 0;
                                            if ($prevTotal["PRODUCTION_QTY"] > 0) {
                                                $prevNgPct = ($prevTotal["NG_QTY"] / $prevTotal["PRODUCTION_QTY"]) * 100;
                                            }
                                            echo angka($prevNgPct, 2);
                                        ?>%
                                    </td>
                                    <td class="num"><?php echo angka($prevTotal["PURGING_QTY"]); ?></td>
                                    <td class="num"><?php echo angka($prevTotal["VIRGIN_QTY"], 2); ?></td>
                                    <td class="num"><?php echo angka($prevTotal["CRUSHER_QTY"], 2); ?></td>
                                    <td class="num"><?php echo angka($prevVirginPct, 2); ?>%</td>
                                    <td class="num"><?php echo angka($prevCrusherPct, 2); ?>%</td>
                                    <td colspan="2"></td>
                                </tr>
                    <?php
                            endif;

                            $customerLabel = $prodItem["CUST_CODE"];

                            if ($prodItem["CUST_COMP"] != "") {
                                $customerLabel .= " - " . $prodItem["CUST_COMP"];
                            }

                            $currentTotal = isset($allProductionCustomerTotals[$customerGroupKey]) ? $allProductionCustomerTotals[$customerGroupKey] : null;
                    ?>
                            <tr class="customer-group-row">
                                <td colspan="16">
                                    Customer: <?php echo h($customerLabel); ?>
                                    <?php if ($currentTotal !== null): ?>
                                        &nbsp; | &nbsp; Item: <?php echo angka($currentTotal["ITEM_COUNT"]); ?>
                                        &nbsp; | &nbsp; Production: <?php echo angka($currentTotal["PRODUCTION_QTY"]); ?>
                                        &nbsp; | &nbsp; NG: <?php echo angka($currentTotal["NG_QTY"]); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                    <?php
                            $lastCustomerGroupKey = $customerGroupKey;
                            $noAllItem = 1;
                        endif;

                        $matTypeAll = isset($prodItem["MATERIAL_TYPE"]) ? $prodItem["MATERIAL_TYPE"] : "-";
                        $matClassAll = "material-empty";

                        if ($matTypeAll == "VIRGIN") {
                            $matClassAll = "material-virgin";
                        } elseif ($matTypeAll == "CRUSHER") {
                            $matClassAll = "material-crusher";
                        } elseif ($matTypeAll == "MIXED") {
                            $matClassAll = "material-mixed";
                        }
                    ?>

                    <tr>
                        <td><?php echo $noAllItem; ?></td>
                        <td class="item-code"><?php echo h($prodItem["ITEM_CODE"]); ?></td>
                        <td><?php echo h($prodItem["ITEM_NAME"]); ?></td>
                        <td class="num"><?php echo angka($prodItem["PRODUCTION_QTY"]); ?></td>
                        <td class="num">Rp <?php echo angka($prodItem["HARGA_PART_IDR"], 2); ?></td>
                        <td class="num">Rp <?php echo angka($prodItem["AMOUNT_IDR"], 2); ?></td>
                        <td class="num"><?php echo angka($prodItem["NG_QTY"]); ?></td>
                        <td class="num">Rp <?php echo angka($prodItem["NG_AMOUNT_IDR"], 2); ?></td>
                        <td class="num"><?php echo angka($prodItem["NG_PERCENT_OK"], 2); ?>%</td>
                        <td class="num"><?php echo angka($prodItem["PURGING_QTY"]); ?></td>
                        <td class="num"><?php echo angka($prodItem["VIRGIN_QTY"], 2); ?></td>
                        <td class="num"><?php echo angka($prodItem["CRUSHER_QTY"], 2); ?></td>
                        <td class="num"><?php echo angka($prodItem["VIRGIN_PCT"], 2); ?>%</td>
                        <td class="num"><?php echo angka($prodItem["CRUSHER_PCT"], 2); ?>%</td>
                        <td class="center">
                            <span class="material-badge <?php echo h($matClassAll); ?>">
                                <?php echo h($matTypeAll); ?>
                            </span>
                        </td>
                        <td class="num"><?php echo angka($prodItem["MACHINE_COUNT"]); ?></td>
                    </tr>

                    <?php $noAllItem++; ?>
                <?php endfor; ?>

                <?php if ($lastCustomerGroupKey != "" && isset($allProductionCustomerTotals[$lastCustomerGroupKey])): ?>
                    <?php
                        $lastTotal = $allProductionCustomerTotals[$lastCustomerGroupKey];
                        $lastTotalMaterial = $lastTotal["TOTAL_MATERIAL_QTY"];
                        $lastVirginPct = 0;
                        $lastCrusherPct = 0;

                        if ($lastTotalMaterial > 0) {
                            $lastVirginPct = ($lastTotal["VIRGIN_QTY"] / $lastTotalMaterial) * 100;
                            $lastCrusherPct = ($lastTotal["CRUSHER_QTY"] / $lastTotalMaterial) * 100;
                        }

                        $lastNgPct = 0;
                        if ($lastTotal["PRODUCTION_QTY"] > 0) {
                            $lastNgPct = ($lastTotal["NG_QTY"] / $lastTotal["PRODUCTION_QTY"]) * 100;
                        }
                    ?>
                    <tr class="customer-subtotal-row">
                        <td colspan="3">Subtotal Customer</td>
                        <td class="num"><?php echo angka($lastTotal["PRODUCTION_QTY"]); ?></td>
                        <td class="num">-</td>
                        <td class="num">Rp <?php echo angka($lastTotal["AMOUNT_IDR"], 2); ?></td>
                        <td class="num"><?php echo angka($lastTotal["NG_QTY"]); ?></td>
                        <td class="num">Rp <?php echo angka($lastTotal["NG_AMOUNT_IDR"], 2); ?></td>
                        <td class="num"><?php echo angka($lastNgPct, 2); ?>%</td>
                        <td class="num"><?php echo angka($lastTotal["PURGING_QTY"]); ?></td>
                        <td class="num"><?php echo angka($lastTotal["VIRGIN_QTY"], 2); ?></td>
                        <td class="num"><?php echo angka($lastTotal["CRUSHER_QTY"], 2); ?></td>
                        <td class="num"><?php echo angka($lastVirginPct, 2); ?>%</td>
                        <td class="num"><?php echo angka($lastCrusherPct, 2); ?>%</td>
                        <td colspan="2"></td>
                    </tr>
                <?php endif; ?>
            <?php else: ?>
                <tr>
                    <td colspan="16">Tidak ada item produksi pada periode filter ini.</td>
                </tr>
            <?php endif; ?>
        </tbody>

        <tfoot>
            <?php
                $grandProdQty = $allProductionGrandTotal["PRODUCTION_QTY"];
                $grandNgQty = $allProductionGrandTotal["NG_QTY"];
                $grandMaterialQty = $allProductionGrandTotal["TOTAL_MATERIAL_QTY"];

                $grandNgPct = 0;
                $grandVirginPct = 0;
                $grandCrusherPct = 0;

                if ($grandProdQty > 0) {
                    $grandNgPct = ($grandNgQty / $grandProdQty) * 100;
                }

                if ($grandMaterialQty > 0) {
                    $grandVirginPct = ($allProductionGrandTotal["VIRGIN_QTY"] / $grandMaterialQty) * 100;
                    $grandCrusherPct = ($allProductionGrandTotal["CRUSHER_QTY"] / $grandMaterialQty) * 100;
                }
            ?>
            <tr class="grand-total-row">
                <th colspan="3">Grand Total Semua Item Produksi</th>
                <th class="num"><?php echo angka($grandProdQty); ?></th>
                <th class="num">-</th>
                <th class="num">Rp <?php echo angka($allProductionGrandTotal["AMOUNT_IDR"], 2); ?></th>
                <th class="num"><?php echo angka($grandNgQty); ?></th>
                <th class="num">Rp <?php echo angka($allProductionGrandTotal["NG_AMOUNT_IDR"], 2); ?></th>
                <th class="num"><?php echo angka($grandNgPct, 2); ?>%</th>
                <th class="num"><?php echo angka($allProductionGrandTotal["PURGING_QTY"]); ?></th>
                <th class="num"><?php echo angka($allProductionGrandTotal["VIRGIN_QTY"], 2); ?></th>
                <th class="num"><?php echo angka($allProductionGrandTotal["CRUSHER_QTY"], 2); ?></th>
                <th class="num"><?php echo angka($grandVirginPct, 2); ?>%</th>
                <th class="num"><?php echo angka($grandCrusherPct, 2); ?>%</th>
                <th colspan="2"></th>
            </tr>
        </tfoot>
    </table>

    <div class="note">
        Tabel ini menampilkan semua item yang ada produksi pada periode filter aktif. Data digroup berdasarkan customer dan diurutkan berdasarkan <b>Part Code / Item Code</b>.
        Kolom Harga Part memakai BOM internal + PO terakhir dalam IDR. Prod Amount IDR = Production Qty x Harga Part. NG Amount IDR = NG Qty x Harga Part. Kolom Virgin/Crusher memakai actual <b>SMS_DETAIL.SMSD_QTY</b>, hanya material <b>ITTY_CODE 02</b>.
    </div>
</div>


<script>
/*
    CUSTOMER AUTOCOMPLETE
*/
(function () {
    var input = document.getElementById("cust_text");
    var hidden = document.getElementById("cust_code");
    var list = document.getElementById("customer_list");
    var form = document.getElementById("filterForm");

    var timer = null;
    var currentIndex = -1;
    var currentData = [];

    if (!input || !hidden || !list) {
        return;
    }

    function clearList() {
        list.innerHTML = "";
        list.style.display = "none";
        currentIndex = -1;
        currentData = [];
    }

    function escapeHtml(text) {
        text = text === null || text === undefined ? "" : String(text);

        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function selectCustomer(item) {
        var code = item.CUST_CODE || "";
        var comp = item.CUST_COMP || "";
        var abbr = item.CUST_ABBR || "";

        hidden.value = code;

        if (comp !== "") {
            input.value = code + " - " + comp;
        } else if (abbr !== "") {
            input.value = code + " - " + abbr;
        } else {
            input.value = code;
        }

        clearList();
    }

    function renderList(data) {
        var html = "";
        var i;

        currentData = data;
        currentIndex = -1;

        if (!data || data.length === 0) {
            clearList();
            return;
        }

        for (i = 0; i < data.length; i++) {
            var code = data[i].CUST_CODE || "";
            var comp = data[i].CUST_COMP || "";
            var abbr = data[i].CUST_ABBR || "";

            html += '<div class="ac-item" data-index="' + i + '">';
            html += '<span class="ac-code">' + escapeHtml(code) + '</span>';
            html += '<span class="ac-name">' + escapeHtml(comp) + '</span>';

            if (abbr !== "") {
                html += '<span class="ac-abbr">(' + escapeHtml(abbr) + ')</span>';
            }

            html += '</div>';
        }

        list.innerHTML = html;
        list.style.display = "block";

        var items = list.getElementsByClassName("ac-item");

        for (i = 0; i < items.length; i++) {
            items[i].onclick = function () {
                var idx = parseInt(this.getAttribute("data-index"), 10);
                if (currentData[idx]) {
                    selectCustomer(currentData[idx]);
                }
            };
        }
    }

    function searchCustomer(keyword) {
        var xhr;

        if (keyword.length < 1) {
            clearList();
            return;
        }

        xhr = new XMLHttpRequest();

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);

                        if (data.error) {
                            clearList();
                            return;
                        }

                        renderList(data);
                    } catch (e) {
                        clearList();
                    }
                } else {
                    clearList();
                }
            }
        };

        xhr.open("GET", "ac_customer.php?q=" + encodeURIComponent(keyword), true);
        xhr.send(null);
    }

    function setActiveItem() {
        var items = list.getElementsByClassName("ac-item");
        var i;

        for (i = 0; i < items.length; i++) {
            items[i].className = "ac-item";
        }

        if (currentIndex >= 0 && items[currentIndex]) {
            items[currentIndex].className = "ac-item active";
        }
    }

    input.onkeyup = function (e) {
        var key = e.keyCode || e.which;

        if (key === 38 || key === 40 || key === 13 || key === 27) {
            return;
        }

        hidden.value = "";
        clearTimeout(timer);

        timer = setTimeout(function () {
            searchCustomer(input.value);
        }, 250);
    };

    input.onkeydown = function (e) {
        var key = e.keyCode || e.which;

        if (list.style.display !== "block") {
            return;
        }

        if (key === 40) {
            currentIndex++;

            if (currentIndex >= currentData.length) {
                currentIndex = 0;
            }

            setActiveItem();

            if (e.preventDefault) {
                e.preventDefault();
            }

            return false;
        }

        if (key === 38) {
            currentIndex--;

            if (currentIndex < 0) {
                currentIndex = currentData.length - 1;
            }

            setActiveItem();

            if (e.preventDefault) {
                e.preventDefault();
            }

            return false;
        }

        if (key === 13) {
            if (currentIndex >= 0 && currentData[currentIndex]) {
                selectCustomer(currentData[currentIndex]);

                if (e.preventDefault) {
                    e.preventDefault();
                }

                return false;
            }
        }

        if (key === 27) {
            clearList();
        }
    };

    input.onblur = function () {
        setTimeout(function () {
            clearList();
        }, 200);
    };

    input.onfocus = function () {
        if (input.value !== "") {
            searchCustomer(input.value);
        }
    };

    if (form) {
        form.onsubmit = function () {
            if (hidden.value === "" && input.value !== "") {
                var val = input.value;
                var pos = val.indexOf(" - ");

                if (pos > -1) {
                    val = val.substring(0, pos);
                }

                hidden.value = val;
            }
        };
    }
})();

/*
    RELASI TONASE DAN MESIN
*/
(function () {
    var tonaseSelect = document.getElementById("tonase");
    var machineSelect = document.getElementById("mc_no");

    if (!tonaseSelect || !machineSelect) {
        return;
    }

    function getOptionTonase(option) {
        return option.getAttribute("data-tonase") || "";
    }

    function filterMachineByTonase() {
        var selectedTonase = tonaseSelect.value;
        var selectedMachineVisible = true;
        var i;

        for (i = 0; i < machineSelect.options.length; i++) {
            var opt = machineSelect.options[i];

            if (i === 0) {
                opt.style.display = "";
                continue;
            }

            var optTonase = getOptionTonase(opt);

            if (selectedTonase === "" || optTonase === selectedTonase) {
                opt.style.display = "";
            } else {
                opt.style.display = "none";

                if (opt.selected) {
                    selectedMachineVisible = false;
                }
            }
        }

        if (!selectedMachineVisible) {
            machineSelect.value = "";
        }
    }

    tonaseSelect.onchange = function () {
        filterMachineByTonase();
    };

    machineSelect.onchange = function () {
        var selectedOption = machineSelect.options[machineSelect.selectedIndex];

        if (!selectedOption || machineSelect.value === "") {
            return;
        }

        var machineTonase = getOptionTonase(selectedOption);

        if (machineTonase !== "") {
            tonaseSelect.value = machineTonase;
            filterMachineByTonase();
        }
    };

    filterMachineByTonase();
})();

/*
    CHART DATA
*/
var labels = <?php echo json_encode($labels); ?>;

var dataOK = <?php echo json_encode($chartOK); ?>;
var dataNG = <?php echo json_encode($chartNG); ?>;
var dataPurging = <?php echo json_encode($chartPurging); ?>;

var dataNGPercentOK = <?php echo json_encode($chartNGPercentOK); ?>;
var dataNGPercentTotal = <?php echo json_encode($chartNGPercentTotal); ?>;
var dataPurgingPercentOK = <?php echo json_encode($chartPurgingPercentOK); ?>;

var dataWorkHours = <?php echo json_encode($chartWorkHours); ?>;
var dataLostHours = <?php echo json_encode($chartLostHours); ?>;
var dataLostPercent = <?php echo json_encode($chartLostPercent); ?>;
var dataAvailability = <?php echo json_encode($chartAvailability); ?>;
var dataOKPerHour = <?php echo json_encode($chartOKPerHour); ?>;

var machineLabels = <?php echo json_encode($machineLabels); ?>;
var machineNgPercent = <?php echo json_encode($machineNgPercent); ?>;

var topNgLabels = <?php echo json_encode($topNgLabels); ?>;
var topNgQty = <?php echo json_encode($topNgQty); ?>;
var topNgUrls = <?php echo json_encode($topNgUrls); ?>;

var selectedNgMonthly = <?php echo json_encode($selectedNgMonthlyChart); ?>;
var selectedNgMachineLabels = <?php echo json_encode($selectedNgMachineLabels); ?>;
var selectedNgMachineQty = <?php echo json_encode($selectedNgMachineQty); ?>;

/*
    CHART PRODUKSI
*/
new Chart(document.getElementById("chartQty"), {
    type: "bar",
    data: {
        labels: labels,
        datasets: [
            {
                label: "OK",
                data: dataOK,
                backgroundColor: "rgba(39, 174, 96, 0.7)"
            },
            {
                label: "NG",
                data: dataNG,
                backgroundColor: "rgba(192, 57, 43, 0.7)"
            },
            {
                label: "Purging",
                data: dataPurging,
                backgroundColor: "rgba(230, 126, 34, 0.7)"
            }
        ]
    },
    options: {
        responsive: true,
        legend: {
            position: "bottom"
        },
        scales: {
            yAxes: [{
                ticks: {
                    beginAtZero: true
                }
            }]
        }
    }
});

new Chart(document.getElementById("chartNgPercent"), {
    type: "line",
    data: {
        labels: labels,
        datasets: [
            {
                label: "NG % terhadap OK",
                data: dataNGPercentOK,
                borderColor: "rgba(192, 57, 43, 1)",
                backgroundColor: "rgba(192, 57, 43, 0.15)",
                fill: true
            },
            {
                label: "Purging % terhadap OK",
                data: dataPurgingPercentOK,
                borderColor: "rgba(230, 126, 34, 1)",
                backgroundColor: "rgba(230, 126, 34, 0.15)",
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        legend: {
            position: "bottom"
        },
        tooltips: {
            callbacks: {
                label: function(tooltipItem, data) {
                    return data.datasets[tooltipItem.datasetIndex].label + ": " + tooltipItem.yLabel + "%";
                }
            }
        },
        scales: {
            yAxes: [{
                ticks: {
                    beginAtZero: true,
                    callback: function(value) {
                        return value + "%";
                    }
                }
            }]
        }
    }
});

new Chart(document.getElementById("chartNgTotalPercent"), {
    type: "bar",
    data: {
        labels: labels,
        datasets: [
            {
                label: "NG % terhadap Total Produksi",
                data: dataNGPercentTotal,
                backgroundColor: "rgba(231, 76, 60, 0.7)"
            }
        ]
    },
    options: {
        responsive: true,
        legend: {
            position: "bottom"
        },
        tooltips: {
            callbacks: {
                label: function(tooltipItem, data) {
                    return data.datasets[tooltipItem.datasetIndex].label + ": " + tooltipItem.yLabel + "%";
                }
            }
        },
        scales: {
            yAxes: [{
                ticks: {
                    beginAtZero: true,
                    callback: function(value) {
                        return value + "%";
                    }
                }
            }]
        }
    }
});

/*
    CHART TOP 5 NG BISA DIKLIK
*/
if (document.getElementById("chartTopNgType")) {
    new Chart(document.getElementById("chartTopNgType"), {
        type: "horizontalBar",
        data: {
            labels: topNgLabels,
            datasets: [
                {
                    label: "Qty NG",
                    data: topNgQty,
                    backgroundColor: "rgba(192, 57, 43, 0.75)"
                }
            ]
        },
        options: {
            responsive: true,
            legend: {
                position: "bottom"
            },
            onClick: function(evt) {
                var activePoints = this.getElementAtEvent(evt);

                if (activePoints.length > 0) {
                    var index = activePoints[0]._index;

                    if (topNgUrls[index]) {
                        window.location.href = topNgUrls[index];
                    }
                }
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        return "Qty NG: " + tooltipItem.xLabel;
                    }
                }
            },
            scales: {
                xAxes: [{
                    ticks: {
                        beginAtZero: true
                    }
                }]
            }
        }
    });
}

/*
    CHART DETAIL NG YANG DIKLIK
*/
if (document.getElementById("chartSelectedNgMonthly")) {
    new Chart(document.getElementById("chartSelectedNgMonthly"), {
        type: "bar",
        data: {
            labels: labels,
            datasets: [
                {
                    label: "Qty NG",
                    data: selectedNgMonthly,
                    backgroundColor: "rgba(231, 76, 60, 0.75)"
                }
            ]
        },
        options: {
            responsive: true,
            legend: {
                position: "bottom"
            },
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true
                    }
                }]
            }
        }
    });
}

if (document.getElementById("chartSelectedNgMachine")) {
    new Chart(document.getElementById("chartSelectedNgMachine"), {
        type: "horizontalBar",
        data: {
            labels: selectedNgMachineLabels,
            datasets: [
                {
                    label: "Qty NG",
                    data: selectedNgMachineQty,
                    backgroundColor: "rgba(230, 126, 34, 0.75)"
                }
            ]
        },
        options: {
            responsive: true,
            legend: {
                position: "bottom"
            },
            scales: {
                xAxes: [{
                    ticks: {
                        beginAtZero: true
                    }
                }]
            }
        }
    });
}

/*
    CHART JAM DAN MESIN
*/
new Chart(document.getElementById("chartHours"), {
    type: "bar",
    data: {
        labels: labels,
        datasets: [
            {
                label: "Work Hours",
                data: dataWorkHours,
                backgroundColor: "rgba(41, 128, 185, 0.7)"
            },
            {
                label: "Lost Hours",
                data: dataLostHours,
                backgroundColor: "rgba(192, 57, 43, 0.7)"
            }
        ]
    },
    options: {
        responsive: true,
        legend: {
            position: "bottom"
        },
        scales: {
            yAxes: [{
                ticks: {
                    beginAtZero: true
                }
            }]
        }
    }
});

new Chart(document.getElementById("chartAvailability"), {
    type: "line",
    data: {
        labels: labels,
        datasets: [
            {
                label: "Availability %",
                data: dataAvailability,
                borderColor: "rgba(39, 174, 96, 1)",
                backgroundColor: "rgba(39, 174, 96, 0.15)",
                fill: true
            },
            {
                label: "Lost Hour %",
                data: dataLostPercent,
                borderColor: "rgba(192, 57, 43, 1)",
                backgroundColor: "rgba(192, 57, 43, 0.15)",
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        legend: {
            position: "bottom"
        },
        tooltips: {
            callbacks: {
                label: function(tooltipItem, data) {
                    return data.datasets[tooltipItem.datasetIndex].label + ": " + tooltipItem.yLabel + "%";
                }
            }
        },
        scales: {
            yAxes: [{
                ticks: {
                    beginAtZero: true,
                    max: 100,
                    callback: function(value) {
                        return value + "%";
                    }
                }
            }]
        }
    }
});

new Chart(document.getElementById("chartProductivity"), {
    type: "line",
    data: {
        labels: labels,
        datasets: [
            {
                label: "OK per Hour",
                data: dataOKPerHour,
                borderColor: "rgba(52, 152, 219, 1)",
                backgroundColor: "rgba(52, 152, 219, 0.15)",
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        legend: {
            position: "bottom"
        },
        scales: {
            yAxes: [{
                ticks: {
                    beginAtZero: true
                }
            }]
        }
    }
});

new Chart(document.getElementById("chartMachineNg"), {
    type: "horizontalBar",
    data: {
        labels: machineLabels,
        datasets: [
            {
                label: "NG % terhadap OK",
                data: machineNgPercent,
                backgroundColor: "rgba(192, 57, 43, 0.7)"
            }
        ]
    },
    options: {
        responsive: true,
        legend: {
            position: "bottom"
        },
        tooltips: {
            callbacks: {
                label: function(tooltipItem, data) {
                    return data.datasets[tooltipItem.datasetIndex].label + ": " + tooltipItem.xLabel + "%";
                }
            }
        },
        scales: {
            xAxes: [{
                ticks: {
                    beginAtZero: true,
                    callback: function(value) {
                        return value + "%";
                    }
                }
            }]
        }
    }
});
</script>

</body>
</html>