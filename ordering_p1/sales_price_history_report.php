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

function fmt_print_date() {
    return date("d-M-Y H:i:s");
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

function fmt_price($value) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, 4, ".", "");
}

$sql = "
    SET NOCOUNT ON;

    SELECT
        ISNULL(PARTNUM, '') AS PARTNUM,
        ISNULL(ITEM_CODE, '') AS ITEM_CODE,
        ISNULL(PRICE_CODE, '') AS PRICE_CODE,
        ISNULL(ITEM_NO, '') AS ITEM_NO,
        ISNULL(ITEM_NAME, '') AS ITEM_NAME,
        ISNULL(CUST_CODE, '') AS CUST_CODE,
        ISNULL(CUST_COMP, '') AS CUST_COMP,
        ISNULL(PRDT_PRICE, 0) AS PRDT_PRICE,
        PRDT_START,
        PRDT_END,
        ISNULL(PRDT_QNO, '') AS PRDT_QNO
    FROM dbo.RPT_SALES_PRICE_HISTORY_VIEW
    ORDER BY
        CUST_CODE,
        ITEM_CODE,
        ITEM_NO,
        ITEM_NAME,
        PRDT_START,
        PRDT_END
";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die("<pre>Query Sales Price History gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$dataRows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dataRows[] = array(
        "PARTNUM"    => safe_trim($r["PARTNUM"]),
        "ITEM_CODE"  => safe_trim($r["ITEM_CODE"]),
        "PRICE_CODE" => safe_trim($r["PRICE_CODE"]),
        "ITEM_NO"    => safe_trim($r["ITEM_NO"]),
        "ITEM_NAME"  => safe_trim($r["ITEM_NAME"]),
        "CUST_CODE"  => safe_trim($r["CUST_CODE"]),
        "CUST_COMP"  => safe_trim($r["CUST_COMP"]),
        "PRDT_PRICE" => $r["PRDT_PRICE"],
        "PRDT_START" => $r["PRDT_START"],
        "PRDT_END"   => $r["PRDT_END"],
        "PRDT_QNO"   => safe_trim($r["PRDT_QNO"])
    );
}

/*
    Susun baris report:
    CUSTOMER
    ITEM
    PRICE DETAIL
*/
$printRows = array();

$lastCust = "";
$lastItem = "";

for ($i = 0; $i < count($dataRows); $i++) {
    $r = $dataRows[$i];

    $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

    if ($custKey != $lastCust) {
        $printRows[] = array(
            "ROW_TYPE"  => "CUSTOMER",
            "CUST_CODE" => $r["CUST_CODE"],
            "CUST_COMP" => $r["CUST_COMP"]
        );

        $lastCust = $custKey;
        $lastItem = "";
    }

    $itemKey = $r["ITEM_CODE"] . "|" . $r["ITEM_NO"] . "|" . $r["ITEM_NAME"];

    if ($itemKey != $lastItem) {
        $printRows[] = array(
            "ROW_TYPE"   => "ITEM",
            "ITEM_CODE"  => $r["ITEM_CODE"],
            "PRICE_CODE" => $r["PRICE_CODE"],
            "ITEM_NO"    => $r["ITEM_NO"],
            "ITEM_NAME"  => $r["ITEM_NAME"]
        );

        $lastItem = $itemKey;
    }

    $printRows[] = array(
        "ROW_TYPE"   => "PRICE",
        "PRDT_PRICE" => $r["PRDT_PRICE"],
        "PRDT_START" => $r["PRDT_START"],
        "PRDT_END"   => $r["PRDT_END"],
        "PRDT_QNO"   => $r["PRDT_QNO"]
    );
}

if (count($printRows) == 0) {
    $printRows[] = array(
        "ROW_TYPE" => "EMPTY"
    );
}

$rowsPerPage = 38;
$pages = array_chunk($printRows, $rowsPerPage);
$totalPages = count($pages);

if ($totalPages <= 0) {
    $totalPages = 1;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Price History</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 7mm;
        }

        body {
            margin: 0;
            background: #9a9a9a;
            font-family: "Courier New", monospace;
            font-size: 11px;
            color: #000000;
        }

        .print-bar {
            width: 198mm;
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
            width: 198mm;
            min-height: 285mm;
            margin: 10px auto;
            background: #ffffff;
            border: 2px solid #000000;
            padding: 9mm;
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
            margin-bottom: 22px;
        }

        .header td {
            border: none;
            vertical-align: top;
        }

        .company {
            width: 32%;
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 14px;
        }

        .company-title {
            font-size: 16px;
            font-weight: normal;
        }

        .title-area {
            width: 38%;
            text-align: center;
            font-family: Arial, sans-serif;
        }

        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 4px;
        }

        .right-info {
            width: 30%;
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 18px;
        }

        .print-date {
            margin-top: 18px;
        }

        .price-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .price-table th {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            border-left: none;
            border-right: none;
            padding: 4px 3px;
            height: 22px;
            font-weight: normal;
            text-align: left;
            box-sizing: border-box;
        }

        .price-table td {
            padding: 2px 3px;
            height: 17px;
            line-height: 13px;
            box-sizing: border-box;
            vertical-align: top;
            white-space: nowrap;
            overflow: hidden;
        }

        .col-part {
            width: 62%;
        }

        .col-price {
            width: 9%;
            text-align: right;
        }

        .col-start {
            width: 11%;
        }

        .col-end {
            width: 11%;
        }

        .col-quot {
            width: 7%;
        }

        .customer-row td {
            height: 22px;
            font-weight: bold;
            font-size: 12px;
            padding-top: 6px;
            letter-spacing: 1px;
        }

        .part-label {
            letter-spacing: 8px;
            font-weight: normal;
        }

        .item-row td {
            padding-top: 4px;
            height: 19px;
        }

        .price-row td {
            height: 17px;
        }

        .dash-row td {
            border-bottom: 1px dashed #000000;
            height: 6px;
            padding: 0;
        }

        .empty-row td {
            height: 18px;
        }

        .num {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        @media print {
            html,
            body {
                width: 210mm;
                height: 297mm;
                background: #ffffff;
            }

            .print-bar {
                display: none;
            }

            .page {
                width: 198mm;
                min-height: 285mm;
                margin: 0 auto;
                border: none;
                padding: 7mm;
                overflow: hidden;
            }
        }
    </style>
</head>

<body>

<div class="print-bar">
    <button type="button" onclick="window.print()">PRINT</button>
    <button type="button" onclick="window.close()">CLOSE</button>
</div>

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
                    <div class="report-title">SALES PRICE HISTORY</div>
                </td>

                <td class="right-info">
                    Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?>

                    <div class="print-date">
                        Print Date : <?php echo h(fmt_print_date()); ?>
                    </div>
                </td>
            </tr>
        </table>

        <table class="price-table">
            <thead>
                <tr>
                    <th class="col-part">
                        <span class="part-label">P&nbsp; A&nbsp; R&nbsp; T</span>
                    </th>
                    <th class="col-price">Price</th>
                    <th class="col-start">Start Date</th>
                    <th class="col-end">End Date</th>
                    <th class="col-quot">Quot.</th>
                </tr>
            </thead>

            <tbody>
                <?php for ($i = 0; $i < count($pageRows); $i++) { ?>
                    <?php $r = $pageRows[$i]; ?>

                    <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
                        <tr class="customer-row">
                            <td colspan="5">
                                [<?php echo h($r["CUST_CODE"]); ?>]
                                [<?php echo h($r["CUST_COMP"]); ?>]
                            </td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "ITEM") { ?>
                        <tr class="item-row">
                            <td class="col-part">
                                [<?php echo h($r["ITEM_CODE"]); ?>]
                                [<?php echo h($r["ITEM_NO"]); ?>]
                                [<?php echo h($r["ITEM_NAME"]); ?>]
                            </td>
                            <td class="col-price"></td>
                            <td class="col-start"></td>
                            <td class="col-end"></td>
                            <td class="col-quot"></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "PRICE") { ?>
                        <tr class="price-row">
                            <td class="col-part"></td>
                            <td class="col-price num">
                                <?php echo h(fmt_price($r["PRDT_PRICE"])); ?>
                            </td>
                            <td class="col-start">
                                <?php echo h(fmt_date($r["PRDT_START"])); ?>
                            </td>
                            <td class="col-end">
                                <?php echo h(fmt_date($r["PRDT_END"])); ?>
                            </td>
                            <td class="col-quot">
                                <?php echo h($r["PRDT_QNO"]); ?>
                            </td>
                        </tr>

                        <?php
                            $next = isset($pageRows[$i + 1]) ? $pageRows[$i + 1] : null;

                            if ($next === null || $next["ROW_TYPE"] == "ITEM" || $next["ROW_TYPE"] == "CUSTOMER") {
                        ?>
                            <tr class="dash-row">
                                <td colspan="5"></td>
                            </tr>
                        <?php } ?>

                    <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr>
                            <td colspan="5" class="center">Data tidak ditemukan.</td>
                        </tr>
                    <?php } ?>
                <?php } ?>

                <?php
                    $fillCount = $rowsPerPage - count($pageRows);

                    if ($fillCount < 0) {
                        $fillCount = 0;
                    }
                ?>

                <?php for ($e = 0; $e < $fillCount; $e++) { ?>
                    <tr class="empty-row">
                        <td>&nbsp;</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

    </div>
<?php } ?>

</body>
</html>