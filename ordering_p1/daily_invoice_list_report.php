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

function date_input_value($value, $default) {
    $value = trim($value);

    if ($value == "") {
        return $default;
    }

    if (preg_match('/^\d{8}$/', $value)) {
        return substr($value, 0, 4) . "-" . substr($value, 4, 2) . "-" . substr($value, 6, 2);
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return $default;
    }

    return date("Y-m-d", $ts);
}

function fmt_date($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-M-y");
    }

    if ($value === null || $value === "") {
        return "";
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return "";
    }

    return date("d-M-y", $ts);
}

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
}

function fmt_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, $decimal, ".", ",");
}

function fmt_price($value) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, 4, ".", ",");
}

function fmt_amount($value) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, 2, ".", ",");
}

$is_filter = get_param("RUN", "") == "1";

$defaultDate = date("Y-m-d");
$asper_input = date_input_value(get_param("ASPER", ""), $defaultDate);
$asper_ymd   = ymd_param($asper_input);

$rows = array();
$printRows = array();
$pages = array();

$totalPages = 0;
$rowsPerPage = 34;

if ($is_filter) {
    if ($asper_ymd == "") {
        die("Tanggal tidak valid.");
    }

    $sql = "
        SET NOCOUNT ON;
        EXEC dbo.SP_INVOICE_DAYLIST1 ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array($asper_ymd));

    if ($stmt === false) {
        die("<pre>Query Daily Invoice List gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $qty = isset($r["QTY"]) ? (float)$r["QTY"] : 0;

        if (isset($r["PART_PRICE"]) && $r["PART_PRICE"] !== null) {
            $price = (float)$r["PART_PRICE"];
        } elseif (isset($r["ORDP_PRICE"]) && $r["ORDP_PRICE"] !== null) {
            $price = (float)$r["ORDP_PRICE"];
        } else {
            $price = 0;
        }

        $amount = $qty * $price;

        $rows[] = array(
            "CUST_COMP"  => safe_trim($r["CUST_COMP"]),
            "PART_NAME"  => safe_trim($r["PART_NAME"]),
            "PART_NO"    => safe_trim($r["PART_NO"]),
            "PART_CODE"  => safe_trim($r["PART_CODE"]),
            "ORDR_PO"    => safe_trim($r["ORDR_PO"]),
            "DI_INVNO"   => safe_trim($r["DI_INVNO"]),
            "DI_DATE"    => $r["DI_DATE"],
            "PRICE"      => $price,
            "QTY"        => $qty,
            "AMOUNT"     => $amount,
            "PART_UNIT"  => safe_trim($r["PART_UNIT"]),
            "CURR_CODE"  => safe_trim($r["CURR_CODE"]),
            "PRICE_CODE" => safe_trim($r["PRICE_CODE"])
        );
    }

    $lastCust = "";
    $lastInv = "";

    $custQty = 0;
    $custAmount = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];

        $custKey = $r["CUST_COMP"];
        $invKey  = $r["CUST_COMP"] . "|" . $r["DI_INVNO"];

        if ($custKey != $lastCust) {
            if ($lastCust != "") {
                $printRows[] = array(
                    "ROW_TYPE" => "CUSTOMER_TOTAL",
                    "QTY"      => $custQty,
                    "AMOUNT"   => $custAmount
                );
            }

            $printRows[] = array(
                "ROW_TYPE"  => "CUSTOMER",
                "CUST_COMP" => $r["CUST_COMP"]
            );

            $lastCust = $custKey;
            $lastInv = "";

            $custQty = 0;
            $custAmount = 0;
        }

        $showInv = 0;

        if ($invKey != $lastInv) {
            $showInv = 1;
            $lastInv = $invKey;
        }

        $printRows[] = array(
            "ROW_TYPE"   => "DETAIL",
            "SHOW_INV"   => $showInv,
            "DI_INVNO"   => $r["DI_INVNO"],
            "DI_DATE"    => $r["DI_DATE"],
            "PART_CODE"  => $r["PART_CODE"],
            "PART_NAME"  => $r["PART_NAME"],
            "PART_NO"    => $r["PART_NO"],
            "PRICE_CODE" => $r["PRICE_CODE"],
            "QTY"        => $r["QTY"],
            "PRICE"      => $r["PRICE"],
            "AMOUNT"     => $r["AMOUNT"],
            "ORDR_PO"    => $r["ORDR_PO"]
        );

        $custQty += $r["QTY"];
        $custAmount += $r["AMOUNT"];
    }

    if ($lastCust != "") {
        $printRows[] = array(
            "ROW_TYPE" => "CUSTOMER_TOTAL",
            "QTY"      => $custQty,
            "AMOUNT"   => $custAmount
        );
    }

    if (count($printRows) == 0) {
        $printRows[] = array(
            "ROW_TYPE" => "EMPTY",
            "MESSAGE"  => "Data daily invoice list tidak ditemukan."
        );
    }

    $pages = array_chunk($printRows, $rowsPerPage);
    $totalPages = count($pages);

    if ($totalPages <= 0) {
        $totalPages = 1;
    }
}

$selfFile = basename($_SERVER["PHP_SELF"]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Invoice List</title>

    <style>
        @page {
            size: A4 landscape;
            margin: 6mm;
        }

        body {
            margin: 0;
            background: #9a9a9a;
            font-family: "Courier New", monospace;
            font-size: 10px;
            color: #000000;
        }

        .filter-bar {
            width: 285mm;
            margin: 8px auto;
            background: #d4d0c8;
            border: 1px solid #666666;
            padding: 6px;
            box-sizing: border-box;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }

        .filter-bar input {
            height: 24px;
            border: 1px solid #777777;
            font-size: 12px;
            padding: 2px 4px;
            box-sizing: border-box;
        }

        .filter-date {
            width: 140px;
        }

        .filter-bar button {
            height: 26px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 4px;
        }

        .print-bar {
            width: 285mm;
            margin: 8px auto;
            text-align: right;
        }

        .print-bar button {
            padding: 6px 14px;
            font-size: 11px;
            cursor: pointer;
            font-family: Arial, sans-serif;
        }

        .page {
            width: 285mm;
            min-height: 198mm;
            margin: 10px auto;
            background: #ffffff;
            border: 2px solid #000000;
            padding: 6mm;
            box-sizing: border-box;
            page-break-after: always;
            overflow: hidden;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .header td {
            border: none;
            vertical-align: top;
        }

        .company {
            width: 30%;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 14px;
        }

        .company-title {
            font-size: 15px;
            font-weight: normal;
        }

        .title-area {
            width: 40%;
            text-align: center;
            font-family: Arial, sans-serif;
        }

        .report-title {
            font-size: 20px;
            font-weight: normal;
            margin-top: 6px;
        }

        .as-per {
            font-size: 11px;
            font-weight: bold;
            margin-top: 4px;
        }

        .right-info {
            width: 30%;
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 18px;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .invoice-table th,
        .invoice-table td {
            border: none;
            padding: 2px 3px;
            height: 17px;
            line-height: 12px;
            box-sizing: border-box;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            font-size: 10px;
        }

        .invoice-table thead th {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            font-weight: normal;
            text-align: left;
            font-size: 11px;
        }

        .customer-row td {
            font-weight: bold;
            font-size: 12px;
            padding-top: 7px;
        }

        .customer-total-row td {
            font-weight: bold;
            border-top: 1px solid #000000;
            padding-top: 4px;
        }

        .inv-bold {
            font-weight: bold;
        }

        .num {
            text-align: right;
        }

        .left {
            text-align: left !important;
        }

        .center {
            text-align: center;
        }

        .col-inv { width: 13%; }
        .col-date { width: 8%; }
        .col-code { width: 8%; }
        .col-name { width: 29%; }
        .col-no { width: 13%; }
        .col-qty { width: 7%; }
        .col-price { width: 9%; }
        .col-amount { width: 11%; }
        .col-po { width: 12%; }

        .invoice-table .col-qty,
        .invoice-table .col-price,
        .invoice-table .col-amount,
        .invoice-table .col-po {
            text-align: left !important;
        }

        .no-data {
            font-family: Arial, sans-serif;
            font-size: 14px;
            text-align: center;
            margin-top: 70px;
            line-height: 24px;
        }

        @media print {
            html,
            body {
                width: 297mm;
                min-height: 210mm;
                background: #ffffff;
            }

            .filter-bar,
            .print-bar {
                display: none;
            }

            .page {
                width: 285mm;
                min-height: 198mm;
                margin: 0 auto;
                border: none;
                padding: 4mm;
                overflow: hidden;
            }
        }
    </style>
</head>

<body>

<div class="filter-bar">
    <form method="get" action="<?php echo h($selfFile); ?>" autocomplete="off">
        <input type="hidden" name="RUN" value="1">

        As Per:
        <input type="date"
               id="ASPER"
               name="ASPER"
               class="filter-date"
               value="<?php echo h($asper_input); ?>">

        <button type="submit">FILTER</button>
        <button type="button" onclick="exportExcel()">EXPORT EXCEL</button>
    </form>
</div>

<div class="print-bar">
    <button type="button" onclick="window.print()">PRINT</button>
    <button type="button" onclick="closeReport()">CLOSE</button>
</div>

<?php if (!$is_filter) { ?>
    <div class="page">
        <table class="header">
            <tr>
                <td class="company">
                    <div class="company-title">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Department
                </td>

                <td class="title-area">
                    <div class="report-title">DAILY INVOICE LIST</div>
                    <div class="as-per">As per: -</div>
                </td>

                <td class="right-info">
                    Page 0 of 0<br>
                    Print date: <?php echo h(fmt_print_datetime()); ?>
                </td>
            </tr>
        </table>

        <div class="no-data">
            Data belum ditampilkan.<br><br>
            Pilih tanggal lalu klik <b>FILTER</b>.
        </div>
    </div>
<?php } ?>

<?php for ($p = 0; $p < count($pages); $p++) { ?>
    <?php
        $pageRows = $pages[$p];
        $pageNo = $p + 1;
    ?>

    <div class="page">
        <table class="header">
            <tr>
                <td class="company">
                    <div class="company-title">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Department
                </td>

                <td class="title-area">
                    <div class="report-title">DAILY INVOICE LIST</div>
                    <div class="as-per">As per: <?php echo h(fmt_date($asper_input)); ?></div>
                </td>

                <td class="right-info">
                    Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?><br>
                    Print date: <?php echo h(fmt_print_datetime()); ?>
                </td>
            </tr>
        </table>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th class="col-inv">INV#</th>
                    <th class="col-date">DATE</th>
                    <th colspan="3" class="center">P&nbsp;&nbsp;&nbsp;A&nbsp;&nbsp;&nbsp;R&nbsp;&nbsp;&nbsp;T</th>
                    <th class="col-qty">Qty.</th>
                    <th class="col-price">Price</th>
                    <th class="col-amount">Amount</th>
                    <th class="col-po">PO#</th>
                </tr>
            </thead>

            <tbody>
                <?php for ($i = 0; $i < count($pageRows); $i++) { ?>
                    <?php $r = $pageRows[$i]; ?>

                    <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
                        <tr class="customer-row">
                            <td colspan="9"><?php echo h($r["CUST_COMP"]); ?></td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
                        <tr>
                            <td class="col-inv inv-bold">
                                <?php echo $r["SHOW_INV"] == 1 ? h($r["DI_INVNO"]) : ""; ?>
                            </td>

                            <td class="col-date">
                                <?php echo h(fmt_date($r["DI_DATE"])); ?>
                            </td>

                            <td class="col-code"><?php echo h($r["PART_CODE"]); ?></td>
                            <td class="col-name"><?php echo h($r["PART_NAME"]); ?></td>
                            <td class="col-no"><?php echo h($r["PART_NO"]); ?></td>

                            <td class="col-qty"><?php echo h(fmt_num($r["QTY"], 0)); ?></td>
                            <td class="col-price"><?php echo h(fmt_price($r["PRICE"])); ?></td>
                            <td class="col-amount"><?php echo h(fmt_amount($r["AMOUNT"])); ?></td>
                            <td class="col-po"><?php echo h($r["ORDR_PO"]); ?></td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL") { ?>
                        <tr class="customer-total-row">
                            <td colspan="5" class="num">TOTAL CUSTOMER</td>
                            <td class="col-qty"><?php echo h(fmt_num($r["QTY"], 0)); ?></td>
                            <td class="col-price"></td>
                            <td class="col-amount"><?php echo h(fmt_amount($r["AMOUNT"])); ?></td>
                            <td class="col-po"></td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr>
                            <td colspan="9" class="center">
                                <?php echo h($r["MESSAGE"]); ?>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<script>
function enc(v) {
    return encodeURIComponent(v == null ? "" : v);
}

function closeReport() {
    try {
        if (window.parent && window.parent !== window) {
            window.location.href = "dashboard_home.php";
            return;
        }
    } catch (e) {
    }

    window.open("", "_self");
    window.close();

    setTimeout(function () {
        if (!window.closed) {
            window.location.href = "dashboard_home.php";
        }
    }, 200);
}

function exportExcel() {
    var asper = document.getElementById("ASPER").value;

    if (asper == "") {
        alert("Tanggal belum diisi.");
        document.getElementById("ASPER").focus();
        return;
    }

    window.location =
        "daily_invoice_list_export_excel.php" +
        "?ASPER=" + enc(asper);
}
</script>

</body>
</html>