<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function safe_trim($value) {
    if ($value === null) {
        return "";
    }

    return trim((string)$value);
}

function get_param($name, $default = "") {
    if (isset($_GET[$name])) {
        return trim($_GET[$name]);
    }

    if (isset($_POST[$name])) {
        return trim($_POST[$name]);
    }

    return $default;
}

function ymd_param($value) {
    $value = trim($value);

    if ($value == "") {
        return "";
    }

    if (preg_match('/^\d{8}$/', $value)) {
        return $value;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return str_replace("-", "", $value);
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("Ymd", $ts);
}

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
}

function excel_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, $decimal, ".", "");
}

function usd_factor($currCode, $currRate, $usdRate) {
    $currCode = strtoupper(trim((string)$currCode));

    if ($currCode == "USD") {
        return 1;
    }

    $currRate = (float)$currRate;
    $usdRate  = (float)$usdRate;

    if ($currRate == 0) {
        $currRate = 1;
    }

    if ($usdRate == 0) {
        $usdRate = 1;
    }

    return $currRate / $usdRate;
}

$start_date = get_param("START_DATE", "");
$end_date   = get_param("END_DATE", "");
$cust_code  = get_param("CUST_CODE", "");

if ($start_date == "" || $end_date == "") {
    die("Tanggal belum diisi.");
}

if ($cust_code == "") {
    die("Customer belum diisi.");
}

$start_ymd = ymd_param($start_date);
$end_ymd   = ymd_param($end_date);

if ($start_ymd == "" || $end_ymd == "") {
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

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("<pre>Query Delivery Balance Amount gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $schedule  = isset($r["SSQTY"]) ? (float)$r["SSQTY"] : 0;
    $delivered = isset($r["SDELQTY"]) ? (float)$r["SDELQTY"] : 0;
    $balance   = $delivered - $schedule;

    $price = isset($r["PRDT_PRICE"]) ? (float)$r["PRDT_PRICE"] : 0;
    $currCode = safe_trim($r["CURR_CODE"]);
    $factor = usd_factor($currCode, $r["CURR_VRATE"], $r["USDRATE"]);

    $rows[] = array(
        "CUST_CODE"   => safe_trim($r["CUST_CODE"]),
        "CUST_COMP"   => safe_trim($r["CUST_COMP"]),
        "PART_NUM"    => safe_trim($r["PART_NUM"]),
        "PART_NO"     => safe_trim($r["PART_NO"]),
        "PART_NAME"   => safe_trim($r["PART_NAME"]),
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

$fileCust = $cust_code == "%" ? "ALL" : $cust_code;
$fileName = "delivery_balance_amount_usd_" . $fileCust . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Balance Amount USD Export</title>

    <style>
        table {
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 10pt;
        }

        th {
            background: #d9eaf7;
            font-weight: bold;
            border: 1px solid #000000;
            text-align: center;
        }

        td {
            border: 1px solid #000000;
            vertical-align: top;
        }

        .title {
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
        }

        .text {
            mso-number-format: "\@";
        }

        .num {
            mso-number-format: "#,##0";
            text-align: right;
        }

        .price {
            mso-number-format: "0.00000";
            text-align: right;
        }

        .money {
            mso-number-format: "#,##0.00";
            text-align: right;
        }
.grand-row {
    background: #d9eaf7;
    font-weight: bold;
}
    </style>
</head>

<body>

<table>
    <tr>
        <td colspan="13" class="title">DELIVERY BALANCE AMOUNT USD</td>
    </tr>

    <tr>
        <td colspan="13">P.T. IMC TEKNO INDONESIA - PPIC Departement</td>
    </tr>

    <tr>
        <td colspan="13">
            Period:
            <?php echo h($start_date); ?>
            ~
            <?php echo h($end_date); ?>
        </td>
    </tr>

    <tr>
        <td colspan="13">
            Customer:
            <?php echo h($cust_code == "%" ? "ALL CUSTOMER" : $cust_code); ?>
        </td>
    </tr>

    <tr>
        <td colspan="13">
            Export Date:
            <?php echo h(fmt_print_datetime()); ?>
        </td>
    </tr>

    <tr>
        <td colspan="13">&nbsp;</td>
    </tr>

    <tr>
        <th>Customer Code</th>
        <th>Customer Name</th>
        <th>Code</th>
        <th>Part No</th>
        <th>Part Name</th>
        <th>Price Original</th>
        <th>Curr</th>
        <th>Schedule Qty</th>
        <th>Schedule Amount USD</th>
        <th>Delivery Qty</th>
        <th>Delivery Amount USD</th>
        <th>Balance Qty</th>
        <th>Balance Amount USD</th>
    </tr>

    <?php if (count($rows) == 0) { ?>
        <tr>
            <td colspan="13">Data delivery balance amount tidak ditemukan.</td>
        </tr>
    <?php } ?>

    <?php
    $lastCust = "";

$subSchAmount = 0;
$subDelAmount = 0;
$subBalAmount = 0;

$grandSchAmount = 0;
$grandDelAmount = 0;
$grandBalAmount = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];

        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

        if ($custKey != $lastCust) {
            if ($lastCust != "") {
                ?>
                <tr class="total-row">
                    <td colspan="8" style="text-align:right;">TOTAL USD</td>
                    <td class="money"><?php echo h(excel_num($subSchAmount, 2)); ?></td>
                    <td></td>
                    <td class="money"><?php echo h(excel_num($subDelAmount, 2)); ?></td>
                    <td></td>
                    <td class="money"><?php echo h(excel_num($subBalAmount, 2)); ?></td>
                </tr>
                <?php
            }

            ?>
            <tr class="customer-row">
                <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
                <td colspan="12"><?php echo h($r["CUST_COMP"]); ?></td>
            </tr>
            <?php

            $lastCust = $custKey;

            $subSchAmount = 0;
            $subDelAmount = 0;
            $subBalAmount = 0;
        }

       $subSchAmount += $r["SCH_AMOUNT"];
$subDelAmount += $r["DEL_AMOUNT"];
$subBalAmount += $r["BAL_AMOUNT"];

$grandSchAmount += $r["SCH_AMOUNT"];
$grandDelAmount += $r["DEL_AMOUNT"];
$grandBalAmount += $r["BAL_AMOUNT"];
        ?>

        <tr>
            <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
            <td class="text"><?php echo h($r["CUST_COMP"]); ?></td>
            <td class="text"><?php echo h($r["PART_NUM"]); ?></td>
            <td class="text"><?php echo h($r["PART_NO"]); ?></td>
            <td class="text"><?php echo h($r["PART_NAME"]); ?></td>
            <td class="price"><?php echo h(excel_num($r["PRICE"], 5)); ?></td>
            <td class="text"><?php echo h($r["CURR_CODE"]); ?></td>
            <td class="num"><?php echo h(excel_num($r["SCH_QTY"], 0)); ?></td>
            <td class="money"><?php echo h(excel_num($r["SCH_AMOUNT"], 2)); ?></td>
            <td class="num"><?php echo h(excel_num($r["DEL_QTY"], 0)); ?></td>
            <td class="money"><?php echo h(excel_num($r["DEL_AMOUNT"], 2)); ?></td>
            <td class="num"><?php echo h(excel_num($r["BAL_QTY"], 0)); ?></td>
            <td class="money"><?php echo h(excel_num($r["BAL_AMOUNT"], 2)); ?></td>
        </tr>

    <?php } ?>

   <?php if ($lastCust != "") { ?>
    <tr class="total-row">
        <td colspan="8" style="text-align:right;">TOTAL USD</td>
        <td class="money"><?php echo h(excel_num($subSchAmount, 2)); ?></td>
        <td></td>
        <td class="money"><?php echo h(excel_num($subDelAmount, 2)); ?></td>
        <td></td>
        <td class="money"><?php echo h(excel_num($subBalAmount, 2)); ?></td>
    </tr>

    <tr class="grand-row">
        <td colspan="8" style="text-align:right;">GRAND TOTAL USD</td>
        <td class="money"><?php echo h(excel_num($grandSchAmount, 2)); ?></td>
        <td></td>
        <td class="money"><?php echo h(excel_num($grandDelAmount, 2)); ?></td>
        <td></td>
        <td class="money"><?php echo h(excel_num($grandBalAmount, 2)); ?></td>
    </tr>
<?php } ?>

</table>

</body>
</html>