<?php
// ============================================
// delivery_balance_amount_export_excel_idr.php
// Export Excel: Delivery Balance Amount (IDR)
// Support pilihan server P1 atau P2
// ============================================
set_time_limit(30);
session_start();

if (!isset($_SESSION['db_user']) || $_SESSION['db_user'] == "") {
    die("Silakan login terlebih dahulu.");
}

 $uid    = $_SESSION['db_user'];
 $pwd    = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";
 $dbName = "msData";

 $servers_config = [
    'p1' => ['ip' => '192.168.0.4', 'label' => 'Plant 1', 'short' => 'P1'],
    'p2' => ['ip' => '192.168.0.9', 'label' => 'Plant 2', 'short' => 'P2'],
];

 $selected_plant = isset($_GET['plant']) ? strtolower(trim($_GET['plant'])) : 'p1';
if (!in_array($selected_plant, ['p1', 'p2'])) {
    $selected_plant = 'p1';
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function safe_trim($value) {
    if ($value === null) return "";
    return trim((string)$value);
}

function get_param($name, $default = "") {
    if (isset($_GET[$name])) return trim($_GET[$name]);
    if (isset($_POST[$name])) return trim($_POST[$name]);
    return $default;
}

function ymd_param($value) {
    $value = trim($value);
    if ($value == "") return "";
    if (preg_match('/^\d{8}$/', $value)) return $value;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return str_replace("-", "", $value);
    $ts = strtotime($value);
    if ($ts === false) return "";
    return date("Ymd", $ts);
}

function date_input_value($value, $default) {
    $value = trim($value);
    if ($value == "") return $default;
    if (preg_match('/^\d{8}$/', $value)) {
        return substr($value, 0, 4) . "-" . substr($value, 4, 2) . "-" . substr($value, 6, 2);
    }
    $ts = strtotime($value);
    if ($ts === false) return $default;
    return date("Y-m-d", $ts);
}

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
}

function fmt_price($value) {
    if ($value === null || $value === "") $value = 0;
    return number_format((float)$value, 5, ".", ",");
}

function idr_factor($currCode, $currRate, $usdRate) {
    $currCode = strtoupper(trim((string)$currCode));
    if ($currCode == "IDR" || $currCode == "RP") return 1;
    $currRate = (float)$currRate;
    $usdRate  = (float)$usdRate;
    if ($currRate == 0) $currRate = 1;
    if ($usdRate == 0) $usdRate = 1;
    if ($currCode == "USD") return $usdRate;
    return $currRate;
}

 $start_input = date_input_value(get_param("START_DATE", ""), "");
 $end_input   = date_input_value(get_param("END_DATE", ""), "");
 $cust_code   = get_param("CUST_CODE", "%");

if ($cust_code === "") $cust_code = "%";

 $start_ymd = ymd_param($start_input);
 $end_ymd   = ymd_param($end_input);

if ($start_ymd === "" || $end_ymd === "") {
    die("Tanggal tidak valid.");
}

 $sql = "
SET NOCOUNT ON;

SELECT
    X.CUST_ID,
    X.CUST_CODE,
    X.CUST_COMP,
    X.PRICE_ID,
    X.PART_NUM,
    X.PART_NO,
    X.PART_NAME,
    X.SSQTY,
    X.SDELQTY,
    X.SPOQTY,
    ISNULL(APV.PRDT_PRICE, 0) AS PRDT_PRICE,
    ISNULL(APV.CURR_CODE, '') AS CURR_CODE,
    ISNULL(RV.CURR_VRATE, 1) AS CURR_VRATE,
    ISNULL((SELECT TOP 1 CURR_CRATE FROM dbo.TODAY_USDRATE_VIEW), 1) AS USDRATE
FROM
(
    SELECT DISTINCT TOP 100 PERCENT
        C.CUST_ID,
        C.CUST_CODE,
        C.CUST_COMP,
        DS.PRICE_ID,
        PV.PART_NUM,
        PV.PART_NO,
        PV.PART_NAME,
        ISNULL(SUM(DS.DELS_QTY), 0) AS SSQTY,
        ISNULL((
            SELECT SUM(DP.DIPA_QTY)
            FROM dbo.DI_PART AS DP
            INNER JOIN dbo.DI AS DIH
                ON DP.DI_ID = DIH.DI_ID
            WHERE
                DP.PRICE_ID = DS.PRICE_ID
                AND DIH.DI_DATE BETWEEN ? AND ?
        ), 0) AS SDELQTY,
        ISNULL((
            SELECT SUM(OP.ORDP_BQTY)
            FROM dbo.ORDR_PAR AS OP
            WHERE
                OP.ORDP_BQTY > 0
                AND OP.ORDP_CLOSE = 0
                AND OP.PRICE_ID = DS.PRICE_ID
        ), 0) AS SPOQTY
    FROM dbo.DELI_SCH AS DS
    INNER JOIN dbo.PART_VIEW AS PV
        ON DS.PRICE_ID = PV.PRICE_ID
    INNER JOIN dbo.CUST AS C
        ON PV.CUST_ID = C.CUST_ID
    WHERE
        DS.DELS_DATE BETWEEN ? AND ?
        AND C.CUST_CODE LIKE ?
    GROUP BY
        C.CUST_ID,
        C.CUST_CODE,
        C.CUST_COMP,
        DS.PRICE_ID,
        PV.PART_NUM,
        PV.PART_NO,
        PV.PART_NAME
) AS X
LEFT JOIN dbo.ACTIVE_PRICE_VIEW AS APV
    ON X.PRICE_ID = APV.PRICE_ID
LEFT JOIN dbo.TODAY_RATE_VIEW AS RV
    ON APV.CURR_CODE = RV.CURR_CODE
ORDER BY
    X.CUST_CODE,
    X.PART_NUM
";

 $params = array(
    $start_ymd,
    $end_ymd,
    $start_ymd,
    $end_ymd,
    $cust_code
);

 $conn_opts = [
    "Database" => $dbName, "Uid" => $uid, "PWD" => $pwd,
    "CharacterSet" => "UTF-8", "LoginTimeout" => 5, "Encrypt" => false,
];

 $servers_to_try   = [$selected_plant];
 $all_raw_rows     = [];
 $connected_servers = [];

foreach ($servers_to_try as $key) {
    $ip    = $servers_config[$key]['ip'];
    $label = $servers_config[$key]['label'];
    $conn  = @sqlsrv_connect($ip, $conn_opts);

    if (!$conn) {
        continue;
    }

    $connected_servers[] = $label;
    $stmt = @sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $all_raw_rows[] = $r;
        }
        sqlsrv_free_stmt($stmt);
    }
    sqlsrv_close($conn);
}

 $aggregated = [];
foreach ($all_raw_rows as $r) {
    $pid = $r['PRICE_ID'];
    if (!isset($aggregated[$pid])) {
        $aggregated[$pid] = [
            'CUST_ID'    => $r['CUST_ID'],
            'CUST_CODE'  => $r['CUST_CODE'],
            'CUST_COMP'  => $r['CUST_COMP'],
            'PRICE_ID'   => $r['PRICE_ID'],
            'PART_NUM'   => $r['PART_NUM'],
            'PART_NO'    => $r['PART_NO'],
            'PART_NAME'  => $r['PART_NAME'],
            'SSQTY'      => (float)$r['SSQTY'],
            'SDELQTY'    => (float)$r['SDELQTY'],
            'SPOQTY'     => (float)$r['SPOQTY'],
            'PRDT_PRICE' => (float)$r['PRDT_PRICE'],
            'CURR_CODE'  => safe_trim($r['CURR_CODE']),
            'CURR_VRATE' => (float)$r['CURR_VRATE'],
            'USDRATE'    => (float)$r['USDRATE'],
        ];
    } else {
        $aggregated[$pid]['SSQTY']   += (float)$r['SSQTY'];
        $aggregated[$pid]['SDELQTY'] += (float)$r['SDELQTY'];
        $aggregated[$pid]['SPOQTY']  += (float)$r['SPOQTY'];
    }
}

 $rows = array();

foreach ($aggregated as $r) {
    $schedule  = $r['SSQTY'];
    $delivered = $r['SDELQTY'];
    $balance   = $delivered - $schedule;

    $price    = $r['PRDT_PRICE'];
    $currCode = $r['CURR_CODE'];
    $factor   = idr_factor($currCode, $r['CURR_VRATE'], $r['USDRATE']);

    $rows[] = array(
        "CUST_CODE"   => safe_trim($r['CUST_CODE']),
        "CUST_COMP"   => safe_trim($r['CUST_COMP']),
        "PART_NUM"    => safe_trim($r['PART_NUM']),
        "PART_NO"     => safe_trim($r['PART_NO']),
        "PART_NAME"   => safe_trim($r['PART_NAME']),
        "PRICE"       => $price,
        "CURR_CODE"   => $currCode,
        "SCH_QTY"     => $schedule,
        "SCH_AMOUNT"  => $schedule * $price * $factor,
        "DEL_QTY"     => $delivered,
        "DEL_AMOUNT"  => $delivered * $price * $factor,
        "BAL_QTY"     => $balance,
        "BAL_AMOUNT"  => $balance * $price * $factor
    );
}

usort($rows, function($a, $b) {
    $cmp = strcmp($a['CUST_CODE'], $b['CUST_CODE']);
    if ($cmp !== 0) return $cmp;
    return strcmp($a['PART_NUM'], $b['PART_NUM']);
});

 $printRows = array();

 $lastCust       = "";
 $subSchAmount   = 0;
 $subDelAmount   = 0;
 $subBalAmount   = 0;
 $grandSchAmount = 0;
 $grandDelAmount = 0;
 $grandBalAmount = 0;

for ($i = 0; $i < count($rows); $i++) {
    $r = $rows[$i];
    $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

    if ($custKey != $lastCust) {
        if ($lastCust != "") {
            $printRows[] = array(
                "ROW_TYPE"   => "CUSTOMER_TOTAL",
                "SCH_AMOUNT" => $subSchAmount,
                "DEL_AMOUNT" => $subDelAmount,
                "BAL_AMOUNT" => $subBalAmount
            );
        }

        $printRows[] = array(
            "ROW_TYPE"  => "CUSTOMER",
            "CUST_CODE" => $r["CUST_CODE"],
            "CUST_COMP" => $r["CUST_COMP"]
        );

        $lastCust     = $custKey;
        $subSchAmount = 0;
        $subDelAmount = 0;
        $subBalAmount = 0;
    }

    $printRows[] = array(
        "ROW_TYPE"   => "DETAIL",
        "PART_NUM"   => $r["PART_NUM"],
        "PART_NO"    => $r["PART_NO"],
        "PART_NAME"  => $r["PART_NAME"],
        "PRICE"      => $r["PRICE"],
        "CURR_CODE"  => $r["CURR_CODE"],
        "SCH_QTY"    => $r["SCH_QTY"],
        "SCH_AMOUNT" => $r["SCH_AMOUNT"],
        "DEL_QTY"    => $r["DEL_QTY"],
        "DEL_AMOUNT" => $r["DEL_AMOUNT"],
        "BAL_QTY"    => $r["BAL_QTY"],
        "BAL_AMOUNT" => $r["BAL_AMOUNT"]
    );

    $subSchAmount   += $r["SCH_AMOUNT"];
    $subDelAmount   += $r["DEL_AMOUNT"];
    $subBalAmount   += $r["BAL_AMOUNT"];
    $grandSchAmount += $r["SCH_AMOUNT"];
    $grandDelAmount += $r["DEL_AMOUNT"];
    $grandBalAmount += $r["BAL_AMOUNT"];
}

if ($lastCust != "") {
    $printRows[] = array(
        "ROW_TYPE"   => "CUSTOMER_TOTAL",
        "SCH_AMOUNT" => $subSchAmount,
        "DEL_AMOUNT" => $subDelAmount,
        "BAL_AMOUNT" => $subBalAmount
    );
}

if (count($rows) > 0) {
    $printRows[] = array(
        "ROW_TYPE"   => "GRAND_TOTAL",
        "SCH_AMOUNT" => $grandSchAmount,
        "DEL_AMOUNT" => $grandDelAmount,
        "BAL_AMOUNT" => $grandBalAmount
    );
}

function xl_qty($val) {
    $n = (float)$val;
    if ($n == 0) return "";
    return number_format($n, 0, ".", ",");
}

function xl_amt($val) {
    $n = (float)$val;
    if ($n == 0) return "";
    return number_format($n, 2, ".", ",");
}

$plant_label = $servers_config[$selected_plant]['label'];

 $filename = "Delivery_Balance_IDR_" . str_replace(" ", "_", $plant_label) . "_" . $start_ymd . "_" . $end_ymd . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
header("Cache-Control: max-age=0");
header("Pragma: public");

?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<!--[if gte mso 9]>
<xml>
<x:ExcelWorkbook>
<x:ExcelWorksheets>
<x:ExcelWorksheet>
<x:Name>Delivery Balance IDR</x:Name>
<x:WorksheetOptions>
<x:DisplayGridlines/>
<x:FitToPage/>
<x:Print>
<x:FitToWidth>1</x:FitToWidth>
<x:FitToHeight>0</x:FitToHeight>
</x:Print>
</x:WorksheetOptions>
</x:ExcelWorksheet>
</x:ExcelWorksheets>
</x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
    td {
        font-family: Calibri, Arial, sans-serif;
        font-size: 11pt;
        vertical-align: middle;
        mso-default-font-family: Calibri;
        mso-default-font-size: 11pt;
    }
    .hdr-bg {
        background-color: #BDD7EE;
        font-weight: bold;
        text-align: center;
        border: 0.5pt solid windowtext;
    }
    .cust-bg {
        background-color: #E2EFDA;
        font-weight: bold;
        border: 0.5pt solid windowtext;
    }
    .sub-bg {
        background-color: #F2F2F2;
        font-weight: bold;
        border: 0.5pt solid windowtext;
    }
    .grand-bg {
        background-color: #FFF2CC;
        font-weight: bold;
        border: 1.5pt solid windowtext;
    }
    .bdr {
        border: 0.5pt solid windowtext;
    }
    .txt {
        mso-number-format:\@;
    }
    .num0 {
        mso-number-format:#,##0;
        text-align: right;
    }
    .num2 {
        mso-number-format:#,##0.00;
        text-align: right;
    }
</style>
</head>
<body>

<table border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">

<colgroup>
    <col style="width:85px;">
    <col style="width:115px;">
    <col style="width:260px;">
    <col style="width:130px;">
    <col style="width:80px;">
    <col style="width:150px;">
    <col style="width:80px;">
    <col style="width:150px;">
    <col style="width:80px;">
    <col style="width:150px;">
</colgroup>

<tr style="height:28px;">
    <td colspan="4" style="font-size:14pt; font-weight:bold;">P.T. IMC TEKNO INDONESIA</td>
    <td colspan="4" style="font-size:18pt; font-weight:bold; text-align:center;">DELIVERY BALANCE AMOUNT (IDR)</td>
    <td colspan="2" style="font-size:10pt; text-align:right; color:#666666;">Page 1 of 1</td>
</tr>

<tr style="height:22px;">
    <td colspan="4" style="font-size:11pt; color:#444444;">PPIC Departement</td>
    <td colspan="4" style="font-size:11pt; text-align:center; color:#444444;">
        Date range: <?php echo h($start_ymd); ?> ~ <?php echo h($end_ymd); ?>
    </td>
    <td colspan="2" style="font-size:10pt;"></td>
</tr>

<tr style="height:20px;">
    <td colspan="8" style="font-size:10pt;"></td>
    <td colspan="2" style="font-size:10pt; text-align:right; color:#666666;">
        Print Date: <?php echo h(fmt_print_datetime()); ?>
    </td>
</tr>

<tr style="height:8px;">
    <td colspan="10"></td>
</tr>

<tr style="height:20px;">
    <td colspan="10" style="font-size:10pt; color:#444444;">
        <b>Server:</b> <?php echo h($plant_label); ?>
        <?php if (count($connected_servers) > 0 && count($connected_servers) < count($servers_to_try)): ?>
        &nbsp;|&nbsp; <span style="color:#CC0000;">* Hanya server yang online ditampilkan</span>
        <?php endif; ?>
    </td>
</tr>

<tr style="height:4px;">
    <td colspan="10"></td>
</tr>

<tr style="height:26px;">
    <td rowspan="2" class="hdr-bg">CODE</td>
    <td rowspan="2" class="hdr-bg">PART NO</td>
    <td rowspan="2" class="hdr-bg">PART NAME</td>
    <td rowspan="2" class="hdr-bg">PRICE</td>
    <td colspan="2" class="hdr-bg">SCHEDULE</td>
    <td colspan="2" class="hdr-bg">DELIVERY</td>
    <td colspan="2" class="hdr-bg">BALANCE</td>
</tr>

<tr style="height:22px;">
    <td class="hdr-bg">Qty</td>
    <td class="hdr-bg">Amount IDR</td>
    <td class="hdr-bg">Qty</td>
    <td class="hdr-bg">Amount IDR</td>
    <td class="hdr-bg">Qty</td>
    <td class="hdr-bg">Amount IDR</td>
</tr>

<?php foreach ($printRows as $r) { ?>

    <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
    <tr style="height:24px;">
        <td colspan="10" class="cust-bg" style="padding-left:6px;">
            <?php echo h($r["CUST_CODE"]); ?> - <?php echo h($r["CUST_COMP"]); ?>
        </td>
    </tr>

    <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
    <tr style="height:20px;">
        <td class="bdr txt"><?php echo h($r["PART_NUM"]); ?></td>
        <td class="bdr txt"><?php echo h($r["PART_NO"]); ?></td>
        <td class="bdr txt"><?php echo h($r["PART_NAME"]); ?></td>
        <td class="bdr txt" style="text-align:right;"><?php echo h(fmt_price($r["PRICE"]) . " " . $r["CURR_CODE"]); ?></td>
        <td class="bdr num0"><?php echo xl_qty($r["SCH_QTY"]); ?></td>
        <td class="bdr num2"><?php echo xl_amt($r["SCH_AMOUNT"]); ?></td>
        <td class="bdr num0"><?php echo xl_qty($r["DEL_QTY"]); ?></td>
        <td class="bdr num2"><?php echo xl_amt($r["DEL_AMOUNT"]); ?></td>
        <td class="bdr num0"><?php echo xl_qty($r["BAL_QTY"]); ?></td>
        <td class="bdr num2"><?php echo xl_amt($r["BAL_AMOUNT"]); ?></td>
    </tr>

    <?php } elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL") { ?>
    <tr style="height:22px;">
        <td colspan="5" class="sub-bg" style="text-align:right; padding-right:6px;">TOTAL IDR</td>
        <td class="sub-bg num2"><?php echo xl_amt($r["SCH_AMOUNT"]); ?></td>
        <td class="sub-bg"></td>
        <td class="sub-bg num2"><?php echo xl_amt($r["DEL_AMOUNT"]); ?></td>
        <td class="sub-bg"></td>
        <td class="sub-bg num2"><?php echo xl_amt($r["BAL_AMOUNT"]); ?></td>
    </tr>

    <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
    <tr style="height:26px;">
        <td colspan="5" class="grand-bg" style="text-align:right; padding-right:6px; font-size:12pt;">GRAND TOTAL IDR</td>
        <td class="grand-bg num2" style="font-size:12pt;"><?php echo xl_amt($r["SCH_AMOUNT"]); ?></td>
        <td class="grand-bg"></td>
        <td class="grand-bg num2" style="font-size:12pt;"><?php echo xl_amt($r["DEL_AMOUNT"]); ?></td>
        <td class="grand-bg"></td>
        <td class="grand-bg num2" style="font-size:12pt;"><?php echo xl_amt($r["BAL_AMOUNT"]); ?></td>
    </tr>

    <?php } ?>
<?php } ?>

</table>

</body>
</html>