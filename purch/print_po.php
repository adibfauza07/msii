<?php
if (session_id() == "") {
    session_start();
}

require_once dirname(__DIR__) . "/config/db_plant2.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
}

function getv($name, $default = "") {
    return isset($_GET[$name]) ? trim((string)$_GET[$name]) : $default;
}

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

function fmt_date($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-M-Y");
    }

    if ($value == "" || $value === null) {
        return "";
    }

    $ts = strtotime((string)$value);
    if ($ts === false) return "";

    return date("d-M-Y", $ts);
}

function num($v, $dec = 2) {
    return number_format(floatval($v), $dec, ".", ",");
}

function money($v, $dec = 2) {
    return number_format(floatval($v), $dec, ".", ",");
}

function terbilang_en_simple($number) {
    $number = round(floatval($number), 2);
    $whole = floor($number);
    $cents = round(($number - $whole) * 100);

    $ones = array(
        0 => "zero", 1 => "one", 2 => "two", 3 => "three", 4 => "four",
        5 => "five", 6 => "six", 7 => "seven", 8 => "eight", 9 => "nine",
        10 => "ten", 11 => "eleven", 12 => "twelve", 13 => "thirteen",
        14 => "fourteen", 15 => "fifteen", 16 => "sixteen",
        17 => "seventeen", 18 => "eighteen", 19 => "nineteen"
    );

    $tens = array(
        2 => "twenty", 3 => "thirty", 4 => "forty", 5 => "fifty",
        6 => "sixty", 7 => "seventy", 8 => "eighty", 9 => "ninety"
    );

    $convert = function($n) use (&$convert, $ones, $tens) {
        $n = intval($n);

        if ($n < 20) {
            return $ones[$n];
        }

        if ($n < 100) {
            $t = floor($n / 10);
            $r = $n % 10;
            return $tens[$t] . ($r > 0 ? "-" . $ones[$r] : "");
        }

        if ($n < 1000) {
            $h = floor($n / 100);
            $r = $n % 100;
            return $ones[$h] . " hundred" . ($r > 0 ? " " . $convert($r) : "");
        }

        if ($n < 1000000) {
            $th = floor($n / 1000);
            $r = $n % 1000;
            return $convert($th) . " thousand" . ($r > 0 ? " " . $convert($r) : "");
        }

        if ($n < 1000000000) {
            $m = floor($n / 1000000);
            $r = $n % 1000000;
            return $convert($m) . " million" . ($r > 0 ? " " . $convert($r) : "");
        }

        return (string)$n;
    };

    return $convert($whole) . " and " . str_pad($cents, 2, "0", STR_PAD_LEFT) . " /100 only";
}

$poId = intval(getv("po_id", "0"));
$poNum = getv("po_num", "");

if ($poId <= 0 && $poNum == "") {
    die("PO belum dipilih.");
}

$where = "";
$params = array();

if ($poId > 0) {
    $where = "WHERE dbo.PO.PO_ID = ?";
    $params[] = $poId;
} else {
    $where = "WHERE dbo.PO.PO_NUM = ?";
    $params[] = $poNum;
}

$sql = "
    SELECT
        dbo.PO.PO_ID,
        dbo.PO.PO_DATE,
        dbo.PO.PO_NUM,
        dbo.PO.PO_TERM,
        dbo.PO.PO_TERMDEL,
        dbo.PO.PO_REM,
        dbo.PO.PO_DATEDO,
        dbo.PO.PO_CUR AS CURR_CODE,
        dbo.PO.PO_TODEF,

        dbo.SUPPLIER.SUP_CODE,
        dbo.SUPPLIER.SUP_COMP,
        dbo.SUPPLIER.SUP_ADDR1,
        dbo.SUPPLIER.SUP_ADDR2,
        dbo.SUPPLIER.SUP_CITY,
        dbo.SUPPLIER.SUP_PHONE,
        dbo.SUPPLIER.SUP_FAX,
        dbo.SUPPLIER.SUP_CONTA,

        CASE dbo.PO.PO_TODEF
            WHEN 0 THEN SUPPLIER_1.SUP_COMP
            ELSE (SELECT TOP 1 CO_COMPANY FROM dbo.COMPANY)
        END AS INV_COMP,

        CASE dbo.PO.PO_TODEF
            WHEN 0 THEN SUPPLIER_1.SUP_ADDR1
            ELSE (SELECT TOP 1 CO_ADDR1 FROM dbo.COMPANY)
        END AS INV_ADDR1,

        CASE dbo.PO.PO_TODEF
            WHEN 0 THEN SUPPLIER_1.SUP_ADDR2
            ELSE (SELECT TOP 1 CO_ADDR2 FROM dbo.COMPANY)
        END AS INV_ADDR2,

        CASE dbo.PO.PO_TODEF
            WHEN 0 THEN SUPPLIER_1.SUP_CITY
            ELSE (SELECT TOP 1 CO_CITY FROM dbo.COMPANY)
        END AS INV_CITY,

        CASE dbo.PO.PO_TODEF
            WHEN 0 THEN SUPPLIER_1.SUP_PHONE
            ELSE (SELECT TOP 1 CO_PHONE FROM dbo.COMPANY)
        END AS INV_PHONE,

        CASE dbo.PO.PO_TODEF
            WHEN 0 THEN SUPPLIER_1.SUP_FAX
            ELSE (SELECT TOP 1 CO_FAX FROM dbo.COMPANY)
        END AS INV_FAX,

        CASE dbo.PO.PO_TODEF
            WHEN 0 THEN SUPPLIER_1.SUP_CONTA
            ELSE ''
        END AS INV_CONTA,

        dbo.ITEMS.ITEM_CODE,
        dbo.ITEMS.ITEM_NAME,
        dbo.ITEMS.ITEM_NO,
        PODV.QTY,
        PODV.POD_UNIT,
        PODV.POD_PRICE,
        PODV.POD_DUE

    FROM dbo.PO
    INNER JOIN dbo.PO_DETAIL_DUE_VIEW AS PODV
        ON dbo.PO.PO_ID = PODV.PO_ID
    INNER JOIN dbo.ITEMS
        ON PODV.ITEM_ID = dbo.ITEMS.ITEM_ID
    INNER JOIN dbo.SUPPLIER
        ON dbo.PO.SUP_ID = dbo.SUPPLIER.SUP_ID
    LEFT OUTER JOIN dbo.SUPPLIER AS SUPPLIER_1
        ON dbo.PO.PO_TO = SUPPLIER_1.SUP_ID
    $where
    ORDER BY
        dbo.ITEMS.ITEM_CODE,
        PODV.POD_DUE
";

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("<pre>Query print PO error:\n" . sql_error_text() . "</pre>");
}

$rows = array();
$head = null;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($head === null) {
        $head = $r;
    }
    $rows[] = $r;
}

if ($head === null) {
    die("Data PO tidak ditemukan.");
}

$currency = trim((string)$head["CURR_CODE"]);
$total = 0;

foreach ($rows as $r) {
    $total += floatval($r["QTY"]) * floatval($r["POD_PRICE"]);
}

/*
    Jika tax tidak dipakai, ubah jadi 0.
    Dari contoh lama: 747.60 x 11% = 82.24
*/
$taxRate = 0.11;
$tax = $total * $taxRate;
$grandTotal = $total + $tax;

$linesPerPage = 13;
$pages = array_chunk($rows, $linesPerPage);
$totalPages = count($pages);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print PO - <?php echo h($head["PO_NUM"]); ?></title>

    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #999999;
            font-family: "Times New Roman", serif;
            font-size: 11px;
            color: #000000;
        }

        .toolbar {
            position: fixed;
            top: 8px;
            left: 8px;
            z-index: 999;
        }

        .btn {
            font-family: Arial, sans-serif;
            font-size: 12px;
            border: 1px solid #777777;
            background: #eeeeee;
            padding: 4px 12px;
            cursor: pointer;
        }

        .page {
            width: 194mm;
            min-height: 277mm;
            margin: 12px auto;
            background: #ffffff;
            padding: 8mm;
            box-sizing: border-box;
            border: 1px solid #000000;
            position: relative;
            page-break-after: always;
        }

        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-top: -2mm;
            margin-bottom: 2mm;
            letter-spacing: 1px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .main-table td,
        .main-table th {
            border: 1px solid #000000;
            vertical-align: top;
            padding: 2px 4px;
        }

        .no-border td {
            border: none;
        }

        .small {
            font-size: 10px;
        }

        .bold {
            font-weight: bold;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .top-info td {
            height: 38px;
        }

        .address-box {
            height: 86px;
        }

        .supplier-box {
            height: 94px;
        }

        .label {
            font-size: 9px;
            display: block;
        }

        .detail th {
            border: 1px solid #000000;
            font-size: 10px;
            text-align: center;
            padding: 2px;
        }

        .detail td {
            border-left: 1px solid #000000;
            border-right: 1px solid #000000;
            padding: 2px 3px;
            height: 17px;
        }

        .detail .last-line td {
            border-bottom: 1px solid #000000;
        }

        .detail-head {
            background: #ffffff;
        }

        .rohs {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
            height: 24px;
            line-height: 24px;
        }

        .footer-table td {
            border: 1px solid #000000;
            padding: 2px 4px;
        }

        .sign-box {
            height: 72px;
            vertical-align: top;
        }

        .sign-name {
            height: 22px;
            vertical-align: bottom;
            text-align: center;
        }

        .page-no {
            position: absolute;
            right: 8mm;
            top: 8mm;
            font-size: 10px;
        }

        .print-code {
            position: absolute;
            left: 8mm;
            bottom: 6mm;
            font-size: 9px;
        }

        .amount-row td {
            height: 16px;
        }

        @media print {
            html, body {
                background: #ffffff;
            }

            .toolbar {
                display: none;
            }

            .page {
                margin: 0;
                border: none;
                page-break-after: always;
                width: auto;
                min-height: auto;
            }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button class="btn" onclick="window.print()">PRINT</button>
    <button class="btn" onclick="window.close()">CLOSE</button>
</div>

<?php for ($p = 0; $p < $totalPages; $p++) { ?>
    <?php
        $pageRows = $pages[$p];
        $isLastPage = ($p == $totalPages - 1);
    ?>

    <div class="page">
        <div class="page-no">Page <?php echo ($p + 1); ?> of <?php echo $totalPages; ?></div>

        <div class="title">PURCHASE ORDER</div>

        <table class="main-table top-info">
            <tr>
                <td style="width:50%;" rowspan="3">
                    <span class="label">Invoice To</span>
                    <b><?php echo h($head["INV_COMP"]); ?></b><br>
                    <?php echo h($head["INV_ADDR1"]); ?><br>
                    <?php echo h($head["INV_ADDR2"]); ?><br>
                    <?php echo h($head["INV_CITY"]); ?><br>
                    Phone: <?php echo h($head["INV_PHONE"]); ?><br>
                    Fax: <?php echo h($head["INV_FAX"]); ?>
                </td>

                <td style="width:25%;">
                    <span class="label">Order No.</span>
                    <b><?php echo h($head["PO_NUM"]); ?></b>
                </td>

                <td style="width:25%;">
                    <span class="label">Dated</span>
                    <?php echo h(fmt_date($head["PO_DATE"])); ?>
                </td>
            </tr>

            <tr>
                <td>
                    <span class="label">Currency</span>
                    <?php echo h($currency); ?>
                </td>

                <td>
                    <span class="label">Terms of Payment</span>
                    <?php echo h($head["PO_TERM"]); ?>
                </td>
            </tr>

            <tr>
                <td>
                    <span class="label">Supplier's Ref.</span>
                    <?php echo h($head["PO_NUM"]); ?>
                </td>

                <td>
                    <span class="label">Other References</span>
                    &nbsp;
                </td>
            </tr>

            <tr>
                <td class="supplier-box" rowspan="4">
                    <span class="label">Supplier</span>
                    <b><?php echo h($head["SUP_COMP"]); ?></b><br>
                    <?php echo h($head["SUP_ADDR1"]); ?><br>
                    <?php echo h($head["SUP_ADDR2"]); ?><br>
                    <?php echo h($head["SUP_CITY"]); ?><br>
                    Phone: <?php echo h($head["SUP_PHONE"]); ?><br>
                    Fax: <?php echo h($head["SUP_FAX"]); ?><br>
                    Attn: <?php echo h($head["SUP_CONTA"]); ?>
                </td>

                <td>
                    <span class="label">Dispatch through</span>
                    &nbsp;
                </td>

                <td>
                    <span class="label">Destination</span>
                    &nbsp;
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <span class="label">Terms of Delivery</span>
                    <?php echo h($head["PO_TERMDEL"]); ?>
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <span class="label">Remark</span>
                    <?php echo nl2br(h($head["PO_REM"])); ?>
                </td>
            </tr>

            <tr>
                <td colspan="2" class="rohs">ROHS FREE</td>
            </tr>
        </table>

        <table class="detail">
            <thead>
                <tr class="detail-head">
                    <th style="width:44%;">Description of Goods</th>
                    <th style="width:14%;">Due On</th>
                    <th style="width:12%;">Quantity</th>
                    <th style="width:10%;">Rate</th>
                    <th style="width:8%;">Unit</th>
                    <th style="width:12%;">Amount</th>
                </tr>
            </thead>

            <tbody>
                <?php
                    $printed = 0;
                    foreach ($pageRows as $r) {
                        $qty = floatval($r["QTY"]);
                        $price = floatval($r["POD_PRICE"]);
                        $amount = $qty * $price;
                        $printed++;
                ?>
                    <tr>
                        <td>
                            <?php echo h(trim($r["ITEM_CODE"]) . " - " . trim($r["ITEM_NAME"])); ?>
                            <?php if (trim((string)$r["ITEM_NO"]) != "") { ?>
                                <br><span class="small"><?php echo h($r["ITEM_NO"]); ?></span>
                            <?php } ?>
                        </td>
                        <td class="center"><?php echo h(fmt_date($r["POD_DUE"])); ?></td>
                        <td class="right"><?php echo h(num($qty, 2)); ?></td>
                        <td class="right"><?php echo h(money($price, 4)); ?></td>
                        <td class="center"><?php echo h($r["POD_UNIT"]); ?></td>
                        <td class="right"><?php echo h($currency . " " . money($amount, 2)); ?></td>
                    </tr>
                <?php } ?>

                <?php
                    for ($i = $printed; $i < $linesPerPage; $i++) {
                        $cls = ($i == $linesPerPage - 1) ? "last-line" : "";
                ?>
                    <tr class="<?php echo $cls; ?>">
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <?php if ($isLastPage) { ?>
            <table class="footer-table">
                <tr class="amount-row">
                    <td style="width:74%;" class="right bold">Total</td>
                    <td style="width:14%;" class="right bold"><?php echo h(num($total, 2)); ?></td>
                    <td style="width:12%;" class="right bold"><?php echo h($currency . " " . money($total, 2)); ?></td>
                </tr>

                <tr class="amount-row">
                    <td class="right bold">Tax</td>
                    <td></td>
                    <td class="right bold"><?php echo h(money($tax, 2)); ?></td>
                </tr>

                <tr class="amount-row">
                    <td class="right bold">Amount</td>
                    <td></td>
                    <td class="right bold"><?php echo h($currency . " " . money($grandTotal, 2)); ?></td>
                </tr>

                <tr>
                    <td colspan="3">
                        <b>Chargeable (in words)</b><br>
                        <?php echo h(terbilang_en_simple($grandTotal)); ?>
                    </td>
                </tr>
            </table>

            <table class="main-table" style="margin-top:6px;">
                <tr>
                    <td style="width:28%;" class="sign-box">
                        <b>Supplier Confirmed</b>
                    </td>

                    <td style="width:42%; border:none;">
                        &nbsp;
                    </td>

                    <td style="width:30%;" class="sign-box center">
                        <b>for PT. IMCTEKNO INDONESIA</b>
                    </td>
                </tr>

                <tr>
                    <td class="sign-name small">
                        (Chop &amp; Sign)
                    </td>

                    <td style="border:none;" class="small">
                        Please sign and send back by Fax :)
                    </td>

                    <td class="sign-name small">
                        ( Koir H Hiaoka )<br>
                        President Director
                    </td>
                </tr>
            </table>
        <?php } ?>

        <div class="print-code">FM.CO-00-04</div>
    </div>
<?php } ?>

</body>
</html>