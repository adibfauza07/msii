<?php
require_once __DIR__ . "/../config/db_plant2.php";

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

function month_to_yyyymmdd($value) {
    $value = trim($value);

    if ($value == "") {
        return "";
    }

    if (preg_match('/^\d{4}-\d{2}$/', $value)) {
        return str_replace("-", "", $value) . "01";
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return str_replace("-", "", $value);
    }

    if (preg_match('/^\d{8}$/', $value)) {
        return $value;
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("Ymd", $ts);
}

function fmt_date($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-M-Y");
    }

    if ($value === null || $value === "") {
        return "";
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("d-M-Y", $ts);
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

$asper_month = get_param("ASPER_MONTH", "");
$cust_code   = get_param("CUST_CODE", "");

if ($asper_month == "") {
    die("Month belum dipilih.");
}

if ($cust_code == "") {
    die("Customer belum diisi.");
}

$asper_ymd = month_to_yyyymmdd($asper_month);

if ($asper_ymd == "") {
    die("Month tidak valid.");
}

$sql = "
    SET NOCOUNT ON;

    DECLARE @ASPER_DT DATETIME;
    SET @ASPER_DT = CONVERT(DATETIME, ?, 112);

    SELECT TOP 100 PERCENT
        C.CUST_CODE,
        C.CUST_COMP,
        PV.PART_NAME,
        PV.PART_NO,
        PV.PART_CODE,
        O.ORDR_PO,
        DI.DI_INVNO,
        DI.DI_DATE,
        DP.PART_PRICE,
        SUM(DP.QTY) AS QTY,
        PV.PART_UNIT,
        PV.CURR_CODE,
        PV.PRICE_CODE,
        OP.ORDP_PRICE
    FROM dbo.DIPA_PAR AS DP
    INNER JOIN dbo.ORDR_PAR AS OP
        ON DP.ORDR_ID = OP.ORDR_ID
       AND DP.ORDP_LINO = OP.ORDP_LINO
    INNER JOIN dbo.DI AS DI
        ON DI.DI_ID = DP.DI_ID
    INNER JOIN dbo.ORDERS AS O
        ON OP.ORDR_ID = O.ORDR_ID
    INNER JOIN dbo.CUST AS C
        ON O.CUST_ID = C.CUST_ID
    INNER JOIN dbo.PART_VIEW AS PV
        ON OP.PRICE_ID = PV.PRICE_ID
    WHERE
        DATEDIFF(MONTH, DI.DI_DATE, @ASPER_DT) = 0
        AND C.CUST_CODE LIKE ?
    GROUP BY
        C.CUST_CODE,
        C.CUST_COMP,
        DI.DI_DATE,
        PV.PART_NUM,
        PV.PART_NAME,
        PV.PART_CODE,
        DP.PART_PRICE,
        PV.PART_UNIT,
        PV.PART_NO,
        DI.DI_INVNO,
        PV.CURR_CODE,
        O.ORDR_PO,
        PV.PRICE_CODE,
        OP.ORDP_PRICE
    ORDER BY
        C.CUST_COMP,
        DI.DI_DATE,
        DI.DI_INVNO,
        PV.PART_NAME
";

$stmt = sqlsrv_query($conn, $sql, array($asper_ymd, $cust_code));

if ($stmt === false) {
    die("<pre>Query Monthly Invoice List gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $qty = isset($r["QTY"]) ? (float)$r["QTY"] : 0;

    if (isset($r["PART_PRICE"]) && $r["PART_PRICE"] !== null) {
        $price = (float)$r["PART_PRICE"];
    } elseif (isset($r["ORDP_PRICE"]) && $r["ORDP_PRICE"] !== null) {
        $price = (float)$r["ORDP_PRICE"];
    } else {
        $price = 0;
    }

    $poPrice = isset($r["ORDP_PRICE"]) ? (float)$r["ORDP_PRICE"] : $price;

    $rows[] = array(
        "CUST_CODE"  => safe_trim($r["CUST_CODE"]),
        "CUST_COMP"  => safe_trim($r["CUST_COMP"]),
        "PART_NAME"  => safe_trim($r["PART_NAME"]),
        "PART_NO"    => safe_trim($r["PART_NO"]),
        "PART_CODE"  => safe_trim($r["PART_CODE"]),
        "ORDR_PO"    => safe_trim($r["ORDR_PO"]),
        "DI_INVNO"   => safe_trim($r["DI_INVNO"]),
        "DI_DATE"    => $r["DI_DATE"],
        "PRICE"      => $price,
        "PO_PRICE"   => $poPrice,
        "QTY"        => $qty,
        "AMOUNT"     => $qty * $price,
        "PART_UNIT"  => safe_trim($r["PART_UNIT"]),
        "CURR_CODE"  => safe_trim($r["CURR_CODE"]),
        "PRICE_CODE" => safe_trim($r["PRICE_CODE"])
    );
}

$fileCust = $cust_code == "%" ? "ALL" : $cust_code;
$fileName = "monthly_invoice_list_" . $fileCust . "_" . $asper_month . "_" . date("Ymd_His") . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Invoice List Export</title>

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
            text-align: left;
        }

        .price {
            mso-number-format: "0.0000";
            text-align: left;
        }

        .money {
            mso-number-format: "#,##0.00";
            text-align: left;
        }

        .customer-row {
            background: #eeeeee;
            font-weight: bold;
        }

        .invoice-total-row {
    background: #ffffff;
    font-weight: bold;
    font-style: italic;
}

        .grand-total-row {
            background: #d9eaf7;
            font-weight: bold;
        }
    </style>
</head>

<body>

<table>
    <tr>
        <td colspan="15" class="title">MONTHLY INVOICE LIST</td>
    </tr>

    <tr>
        <td colspan="15">P.T. IMC TEKNO INDONESIA - PPIC Department</td>
    </tr>

    <tr>
        <td colspan="15">As per: <?php echo h($asper_month); ?></td>
    </tr>

    <tr>
        <td colspan="15">
            Customer:
            <?php echo h($cust_code == "%" ? "ALL CUSTOMER" : $cust_code); ?>
        </td>
    </tr>

    <tr>
        <td colspan="15">Export Date: <?php echo h(fmt_print_datetime()); ?></td>
    </tr>

    <tr>
        <td colspan="15">&nbsp;</td>
    </tr>

    <tr>
        <th>Customer Code</th>
        <th>Customer Name</th>
        <th>INV#</th>
        <th>Date</th>
        <th>Part Code</th>
        <th>Part Name</th>
        <th>Part No</th>
        <th>Price Code</th>
        <th>PO#</th>
        <th>Qty</th>
        <th>Price</th>
        <th>Curr</th>
        <th>Amount</th>
        <th>PO Price</th>
    </tr>

    <?php if (count($rows) == 0) { ?>
        <tr>
            <td colspan="14">Data monthly invoice list tidak ditemukan.</td>
        </tr>
    <?php } ?>

    <?php
    $lastCust = "";
$lastInv = "";

$custQty = 0;
$custAmount = 0;

$invQty = 0;
$invAmount = 0;
$invNo = "";

$grandQty = 0;
$grandAmount = 0;

for ($i = 0; $i < count($rows); $i++) {
    $r = $rows[$i];

    $custKey = $r["CUST_COMP"];
    $invKey  = $r["CUST_COMP"] . "|" . $r["DI_INVNO"];

    if ($custKey != $lastCust) {
        if ($lastInv != "") {
            ?>
            <tr class="invoice-total-row">
                <td colspan="9" style="text-align:right;">
                    TOTAL INVOICE <?php echo h($invNo); ?>
                </td>
                <td class="num"><?php echo h(excel_num($invQty, 0)); ?></td>
                <td></td>
                <td></td>
                <td class="money"><?php echo h(excel_num($invAmount, 2)); ?></td>
                <td></td>
            </tr>
            <?php
        }

        if ($lastCust != "") {
            ?>
            <tr class="customer-total-row">
                <td colspan="9" style="text-align:right;">TOTAL CUSTOMER</td>
                <td class="num"><?php echo h(excel_num($custQty, 0)); ?></td>
                <td></td>
                <td></td>
                <td class="money"><?php echo h(excel_num($custAmount, 2)); ?></td>
                <td></td>
            </tr>
            <?php
        }

        ?>
        <tr class="customer-row">
            <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
            <td colspan="13"><?php echo h($r["CUST_COMP"]); ?></td>
        </tr>
        <?php

        $lastCust = $custKey;
        $lastInv = "";

        $custQty = 0;
        $custAmount = 0;

        $invQty = 0;
        $invAmount = 0;
        $invNo = "";
    }

    if ($invKey != $lastInv) {
        if ($lastInv != "") {
            ?>
            <tr class="invoice-total-row">
                <td colspan="9" style="text-align:right;">
                    TOTAL INVOICE <?php echo h($invNo); ?>
                </td>
                <td class="num"><?php echo h(excel_num($invQty, 0)); ?></td>
                <td></td>
                <td></td>
                <td class="money"><?php echo h(excel_num($invAmount, 2)); ?></td>
                <td></td>
            </tr>
            <?php
        }

        $lastInv = $invKey;
        $invNo = $r["DI_INVNO"];

        $invQty = 0;
        $invAmount = 0;
    }

    $invQty += $r["QTY"];
    $invAmount += $r["AMOUNT"];

    $custQty += $r["QTY"];
    $custAmount += $r["AMOUNT"];

    $grandQty += $r["QTY"];
    $grandAmount += $r["AMOUNT"];
    ?>

    <tr>
        <td class="text"><?php echo h($r["CUST_CODE"]); ?></td>
        <td class="text"><?php echo h($r["CUST_COMP"]); ?></td>
        <td class="text"><?php echo h($r["DI_INVNO"]); ?></td>
        <td class="text"><?php echo h(fmt_date($r["DI_DATE"])); ?></td>
        <td class="text"><?php echo h($r["PART_CODE"]); ?></td>
        <td class="text"><?php echo h($r["PART_NAME"]); ?></td>
        <td class="text"><?php echo h($r["PART_NO"]); ?></td>
        <td class="text"><?php echo h($r["PRICE_CODE"]); ?></td>
        <td class="text"><?php echo h($r["ORDR_PO"]); ?></td>
        <td class="num"><?php echo h(excel_num($r["QTY"], 0)); ?></td>
        <td class="price"><?php echo h(excel_num($r["PRICE"], 4)); ?></td>
        <td class="text"><?php echo h($r["CURR_CODE"]); ?></td>
        <td class="money"><?php echo h(excel_num($r["AMOUNT"], 2)); ?></td>
        <td class="price"><?php echo h(excel_num($r["PO_PRICE"], 4)); ?></td>
    </tr>

<?php } ?>

    <?php if ($lastInv != "") { ?>
    <tr class="invoice-total-row">
        <td colspan="9" style="text-align:right;">
            TOTAL INVOICE <?php echo h($invNo); ?>
        </td>
        <td class="num"><?php echo h(excel_num($invQty, 0)); ?></td>
        <td></td>
        <td></td>
        <td class="money"><?php echo h(excel_num($invAmount, 2)); ?></td>
        <td></td>
    </tr>
<?php } ?>

<?php if ($lastCust != "") { ?>
    <tr class="customer-total-row">
        <td colspan="9" style="text-align:right;">TOTAL CUSTOMER</td>
        <td class="num"><?php echo h(excel_num($custQty, 0)); ?></td>
        <td></td>
        <td></td>
        <td class="money"><?php echo h(excel_num($custAmount, 2)); ?></td>
        <td></td>
    </tr>

    <tr class="grand-total-row">
        <td colspan="9" style="text-align:right;">GRAND TOTAL</td>
        <td class="num"><?php echo h(excel_num($grandQty, 0)); ?></td>
        <td></td>
        <td></td>
        <td class="money"><?php echo h(excel_num($grandAmount, 2)); ?></td>
        <td></td>
    </tr>
<?php } ?>
</table>

</body>
</html>