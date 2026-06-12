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

function fmt_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }
    return number_format((float)$value, $decimal, ".", ",");
}

function fmt_zero_dash($value, $decimal = 0) {
    if ($value === null || $value === "") {
        return "-";
    }

    $n = (float)$value;
    if ($n == 0) {
        return "-";
    }

    return number_format($n, $decimal, ".", ",");
}

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

function date_out($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-M-Y");
    }

    if ($value === null || $value === "") {
        return "";
    }

    $ts = strtotime((string)$value);
    if ($ts === false) {
        return safe_trim($value);
    }

    return date("d-M-Y", $ts);
}

function print_datetime() {
    return date("d-M-Y H:i:s");
}

function query_all($conn, $sql, $params) {
    $rows = array();
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        return false;
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }

    return $rows;
}

// ==========================================================
// PARAMETER
// Report ini TIDAK memakai parameter.
// Data langsung dari dbo.view_po_depresiasi.
// ==========================================================
$export = get_param("EXPORT", "") == "1";

// ==========================================================
// LOAD DATA
// ==========================================================
$sql = "
    SET NOCOUNT ON;

    SELECT
        ISNULL(ORDR_PO, '') AS ORDR_PO,
        ORDR_DATE,
        ISNULL(ITEM_CODE, '') AS ITEM_CODE,
        ISNULL(ITEM_NAME, '') AS ITEM_NAME,
        ISNULL(ORDP_QTY, 0) AS ORDP_QTY,
        ISNULL(ORDP_DQTY, 0) AS ORDP_DQTY,
        ISNULL(ORDP_BQTY, 0) AS ORDP_BQTY
    FROM dbo.view_po_depresiasi
    ORDER BY ITEM_CODE, ORDR_DATE, ORDR_PO
";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die("<pre>Query report list depresiasi gagal:\n" . h(sql_error_text()) . "</pre>");
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = array(
        "ORDR_PO" => safe_trim($r["ORDR_PO"]),
        "ORDR_DATE" => $r["ORDR_DATE"],
        "ITEM_CODE" => safe_trim($r["ITEM_CODE"]),
        "ITEM_NAME" => safe_trim($r["ITEM_NAME"]),
        "ORDP_QTY" => (float)$r["ORDP_QTY"],
        "ORDP_DQTY" => (float)$r["ORDP_DQTY"],
        "ORDP_BQTY" => (float)$r["ORDP_BQTY"]
    );
}

// ==========================================================
// GROUPING PER ITEM + TOTAL
// ==========================================================
$printRows = array();

$lastItem = "";
$itemQty = 0;
$itemDQty = 0;
$itemBQty = 0;
$grandQty = 0;
$grandDQty = 0;
$grandBQty = 0;

for ($i = 0; $i < count($rows); $i++) {
    $r = $rows[$i];
    $itemKey = $r["ITEM_CODE"] . "|" . $r["ITEM_NAME"];

    if ($itemKey != $lastItem) {
        if ($lastItem != "") {
            $printRows[] = array(
                "ROW_TYPE" => "ITEM_TOTAL",
                "ORDP_QTY" => $itemQty,
                "ORDP_DQTY" => $itemDQty,
                "ORDP_BQTY" => $itemBQty
            );
        }

        $printRows[] = array(
            "ROW_TYPE" => "ITEM",
            "ITEM_CODE" => $r["ITEM_CODE"],
            "ITEM_NAME" => $r["ITEM_NAME"]
        );

        $lastItem = $itemKey;
        $itemQty = 0;
        $itemDQty = 0;
        $itemBQty = 0;
    }

    $printRows[] = array_merge(array("ROW_TYPE" => "DETAIL"), $r);

    $itemQty += $r["ORDP_QTY"];
    $itemDQty += $r["ORDP_DQTY"];
    $itemBQty += $r["ORDP_BQTY"];

    $grandQty += $r["ORDP_QTY"];
    $grandDQty += $r["ORDP_DQTY"];
    $grandBQty += $r["ORDP_BQTY"];
}

if ($lastItem != "") {
    $printRows[] = array(
        "ROW_TYPE" => "ITEM_TOTAL",
        "ORDP_QTY" => $itemQty,
        "ORDP_DQTY" => $itemDQty,
        "ORDP_BQTY" => $itemBQty
    );
}

if (count($rows) > 0) {
    $printRows[] = array(
        "ROW_TYPE" => "GRAND_TOTAL",
        "ORDP_QTY" => $grandQty,
        "ORDP_DQTY" => $grandDQty,
        "ORDP_BQTY" => $grandBQty
    );
}

if (count($printRows) == 0) {
    $printRows[] = array("ROW_TYPE" => "EMPTY", "MESSAGE" => "Data PO depresiasi tidak ditemukan.");
}

$rowsPerPage = 36;
$pages = array_chunk($printRows, $rowsPerPage);
$totalPages = count($pages);
if ($totalPages <= 0) {
    $totalPages = 1;
}

// ==========================================================
// EXPORT EXCEL TANPA PARAMETER
// ==========================================================
if ($export) {
    $fileName = "depresiasi_list_" . date("Ymd_His") . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 10pt; }
            th { background: #d9eaf7; border: 1px solid #000000; font-weight: bold; }
            td { border: 1px solid #000000; }
            .text { mso-number-format: "\@"; }
            .num { mso-number-format: "#,##0"; text-align: right; }
            .title { font-size: 16pt; font-weight: bold; text-align: center; }
            .item-row { background: #eeeeee; font-weight: bold; }
            .total-row { background: #f3f3f3; font-weight: bold; }
            .grand-row { background: #d9eaf7; font-weight: bold; }
        </style>
    </head>
    <body>
    <table>
        <tr><td colspan="7" class="title">REPORT LIST DEPRESIASI</td></tr>
        <tr><td colspan="7">P.T. IMC TEKNO INDONESIA - PPIC Department</td></tr>
        <tr><td colspan="7">Export Date: <?php echo h(print_datetime()); ?></td></tr>
        <tr><td colspan="7">&nbsp;</td></tr>
        <tr>
            <th>PO #</th>
            <th>PO Date</th>
            <th>Item Code</th>
            <th>Item Name</th>
            <th>PO Qty</th>
            <th>Del Qty</th>
            <th>Bal Qty</th>
        </tr>
        <?php for ($i = 0; $i < count($printRows); $i++) { $r = $printRows[$i]; ?>
            <?php if ($r["ROW_TYPE"] == "ITEM") { ?>
                <tr class="item-row">
                    <td colspan="7" class="text"><?php echo h($r["ITEM_CODE"] . "  " . $r["ITEM_NAME"]); ?></td>
                </tr>
            <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
                <tr>
                    <td class="text"><?php echo h($r["ORDR_PO"]); ?></td>
                    <td class="text"><?php echo h(date_out($r["ORDR_DATE"])); ?></td>
                    <td class="text"><?php echo h($r["ITEM_CODE"]); ?></td>
                    <td class="text"><?php echo h($r["ITEM_NAME"]); ?></td>
                    <td class="num"><?php echo h((int)$r["ORDP_QTY"]); ?></td>
                    <td class="num"><?php echo h((int)$r["ORDP_DQTY"]); ?></td>
                    <td class="num"><?php echo h((int)$r["ORDP_BQTY"]); ?></td>
                </tr>
            <?php } elseif ($r["ROW_TYPE"] == "ITEM_TOTAL") { ?>
                <tr class="total-row">
                    <td colspan="4" style="text-align:right;">TOTAL ITEM</td>
                    <td class="num"><?php echo h((int)$r["ORDP_QTY"]); ?></td>
                    <td class="num"><?php echo h((int)$r["ORDP_DQTY"]); ?></td>
                    <td class="num"><?php echo h((int)$r["ORDP_BQTY"]); ?></td>
                </tr>
            <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
                <tr class="grand-row">
                    <td colspan="4" style="text-align:right;">GRAND TOTAL</td>
                    <td class="num"><?php echo h((int)$r["ORDP_QTY"]); ?></td>
                    <td class="num"><?php echo h((int)$r["ORDP_DQTY"]); ?></td>
                    <td class="num"><?php echo h((int)$r["ORDP_BQTY"]); ?></td>
                </tr>
            <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                <tr><td colspan="7"><?php echo h($r["MESSAGE"]); ?></td></tr>
            <?php } ?>
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
    <title>Report List Depresiasi</title>
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

        .toolbar {
            width: 275mm;
            margin: 8px auto;
            text-align: right;
        }

        .toolbar button,
        .toolbar a {
            display: inline-block;
            padding: 6px 14px;
            font-size: 11px;
            cursor: pointer;
            font-family: Arial, sans-serif;
            background: #eeeeee;
            border: 1px solid #777777;
            color: #000000;
            text-decoration: none;
            margin-left: 4px;
        }

        .page {
            width: 275mm;
            min-height: 190mm;
            margin: 10px auto;
            background: #ffffff;
            border: 2px solid #000000;
            padding: 7mm;
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
            font-family: Arial, sans-serif;
        }

        .header td {
            border: none;
            vertical-align: top;
        }

        .company {
            width: 30%;
            font-size: 11px;
            line-height: 14px;
        }

        .company-title {
            font-size: 14px;
            font-weight: normal;
        }

        .title-area {
            width: 40%;
            text-align: center;
        }

        .report-title {
            font-size: 20px;
            font-weight: normal;
            margin-top: 4px;
        }

        .period-text {
            font-size: 11px;
            margin-top: 4px;
        }

        .right-info {
            width: 30%;
            text-align: right;
            font-size: 11px;
            line-height: 17px;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .report-table th,
        .report-table td {
            padding: 2px 3px;
            height: 16px;
            line-height: 12px;
            box-sizing: border-box;
            white-space: nowrap;
            overflow: hidden;
            font-size: 9px;
        }

        .report-table thead th {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            font-weight: normal;
            text-align: center;
        }

        .report-table tbody td {
            border-bottom: 1px dotted #cccccc;
        }

        .col-po { width: 18%; text-align: left; }
        .col-date { width: 10%; text-align: left; }
        .col-code { width: 11%; text-align: left; }
        .col-name { width: 35%; text-align: left; }
        .col-qty { width: 8.5%; text-align: right; }

        .item-row td {
            background: #bfbfbf;
            font-weight: bold;
            border-bottom: 1px solid #999999;
        }

        .item-total-row td {
            font-weight: bold;
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
        }

        .grand-total-row td {
            font-weight: bold;
            border-top: 2px solid #000000;
            border-bottom: 2px solid #000000;
        }

        .num { text-align: right; }
        .center { text-align: center; }

        @media print {
            html, body {
                width: 297mm;
                min-height: 210mm;
                background: #ffffff;
            }

            .toolbar {
                display: none;
            }

            .page {
                width: 275mm;
                min-height: 190mm;
                margin: 0 auto;
                border: none;
                padding: 5mm;
                overflow: hidden;
            }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="po_depresiasi.php?EXPORT=1">EXPORT EXCEL</a>
    <button type="button" onclick="window.print()">PRINT</button>
    <button type="button" onclick="closeReport()">CLOSE</button>
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
                    <div class="report-title">REPORT LIST DEPRESIASI</div>
                    <div class="period-text">As per: ALL DATA</div>
                </td>
                <td class="right-info">
                    Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?><br>
                    Print Date: <?php echo h(print_datetime()); ?>
                </td>
            </tr>
        </table>

        <table class="report-table">
            <thead>
                <tr>
                    <th class="col-po">PO #</th>
                    <th class="col-date">PO Date</th>
                    <th class="col-code">Item Code</th>
                    <th class="col-name">Item Name</th>
                    <th class="col-qty">PO Qty</th>
                    <th class="col-qty">Del Qty</th>
                    <th class="col-qty">Bal Qty</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < count($pageRows); $i++) { ?>
                    <?php $r = $pageRows[$i]; ?>

                    <?php if ($r["ROW_TYPE"] == "ITEM") { ?>
                        <tr class="item-row">
                            <td colspan="7">
                                <?php echo h($r["ITEM_CODE"]); ?>
                                &nbsp;&nbsp;
                                <?php echo h($r["ITEM_NAME"]); ?>
                            </td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
                        <tr>
                            <td class="col-po"><?php echo h($r["ORDR_PO"]); ?></td>
                            <td class="col-date"><?php echo h(date_out($r["ORDR_DATE"])); ?></td>
                            <td class="col-code"><?php echo h($r["ITEM_CODE"]); ?></td>
                            <td class="col-name"><?php echo h($r["ITEM_NAME"]); ?></td>
                            <td class="col-qty num"><?php echo h(fmt_zero_dash($r["ORDP_QTY"], 0)); ?></td>
                            <td class="col-qty num"><?php echo h(fmt_zero_dash($r["ORDP_DQTY"], 0)); ?></td>
                            <td class="col-qty num"><?php echo h(fmt_zero_dash($r["ORDP_BQTY"], 0)); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "ITEM_TOTAL") { ?>
                        <tr class="item-total-row">
                            <td colspan="4" style="text-align:right;">TOTAL ITEM</td>
                            <td class="num"><?php echo h(fmt_num($r["ORDP_QTY"], 0)); ?></td>
                            <td class="num"><?php echo h(fmt_num($r["ORDP_DQTY"], 0)); ?></td>
                            <td class="num"><?php echo h(fmt_num($r["ORDP_BQTY"], 0)); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
                        <tr class="grand-total-row">
                            <td colspan="4" style="text-align:right;">GRAND TOTAL</td>
                            <td class="num"><?php echo h(fmt_num($r["ORDP_QTY"], 0)); ?></td>
                            <td class="num"><?php echo h(fmt_num($r["ORDP_DQTY"], 0)); ?></td>
                            <td class="num"><?php echo h(fmt_num($r["ORDP_BQTY"], 0)); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr>
                            <td colspan="7" class="center"><?php echo h($r["MESSAGE"]); ?></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<script>
function closeReport() {
    window.open("", "_self");
    window.close();
    setTimeout(function() {
        if (!window.closed) {
            window.location.href = "depresiasi.php";
        }
    }, 200);
}
</script>

</body>
</html>
