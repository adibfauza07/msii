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

function print_datetime() {
    return date("d-M-Y H:i:s");
}

function query_all($conn, $sql, $params = array()) {
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

$export = get_param("EXPORT", "") == "1";

// ==========================================================
// LOAD DATA TANPA PARAMETER
// ==========================================================
$sql = "
    SET NOCOUNT ON;

    SELECT
        ITEM_ID,
        ISNULL(ITEM_CODE, '') AS ITEM_CODE,
        ISNULL(ITEM_NO, '') AS ITEM_NO,
        ISNULL(ITEM_NAME, '') AS ITEM_NAME,
        ISNULL(DEPRESIASI, 0) AS DEPRESIASI,
        ISNULL(DIFF, 0) AS DIFF,
        ISNULL(DELIVERY, 0) AS DELIVERY
    FROM dbo.VIEW_DEPRESIASI
    ORDER BY ITEM_CODE
";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die("<pre>Query report depresiasi gagal:\n" . h(sql_error_text()) . "</pre>");
}

$rows = array();
$totalDepresiasi = 0;
$totalDelivery = 0;
$totalDiff = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dep = (float)$r["DEPRESIASI"];
    $del = (float)$r["DELIVERY"];
    $dif = (float)$r["DIFF"];

    $rows[] = array(
        "ITEM_ID" => intval($r["ITEM_ID"]),
        "ITEM_CODE" => safe_trim($r["ITEM_CODE"]),
        "ITEM_NO" => safe_trim($r["ITEM_NO"]),
        "ITEM_NAME" => safe_trim($r["ITEM_NAME"]),
        "DEPRESIASI" => $dep,
        "DELIVERY" => $del,
        "DIFF" => $dif
    );

    $totalDepresiasi += $dep;
    $totalDelivery += $del;
    $totalDiff += $dif;
}

$printRows = array();

for ($i = 0; $i < count($rows); $i++) {
    $printRows[] = array_merge(array("ROW_TYPE" => "DETAIL", "NO" => $i + 1), $rows[$i]);
}

if (count($rows) > 0) {
    $printRows[] = array(
        "ROW_TYPE" => "GRAND_TOTAL",
        "DEPRESIASI" => $totalDepresiasi,
        "DELIVERY" => $totalDelivery,
        "DIFF" => $totalDiff
    );
} else {
    $printRows[] = array("ROW_TYPE" => "EMPTY", "MESSAGE" => "Data depresiasi tidak ditemukan.");
}

$rowsPerPage = 36;
$pages = array_chunk($printRows, $rowsPerPage);
$totalPages = count($pages);
if ($totalPages <= 0) {
    $totalPages = 1;
}

// ==========================================================
// EXPORT EXCEL
// ==========================================================
if ($export) {
    $fileName = "view_depresiasi_" . date("Ymd_His") . ".xls";

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
            .grand-row { background: #d9eaf7; font-weight: bold; }
        </style>
    </head>
    <body>
    <table>
        <tr><td colspan="8" class="title">REPORT VIEW DEPRESIASI</td></tr>
        <tr><td colspan="8">P.T. IMC TEKNO INDONESIA - PPIC Department</td></tr>
        <tr><td colspan="8">Export Date: <?php echo h(print_datetime()); ?></td></tr>
        <tr><td colspan="8">&nbsp;</td></tr>
        <tr>
            <th>No</th>
            <th>Item ID</th>
            <th>Item Code</th>
            <th>Item No</th>
            <th>Item Name</th>
            <th>Depresiasi</th>
            <th>Delivery</th>
            <th>Diff</th>
        </tr>
        <?php for ($i = 0; $i < count($printRows); $i++) { $r = $printRows[$i]; ?>
            <?php if ($r["ROW_TYPE"] == "DETAIL") { ?>
                <tr>
                    <td class="num"><?php echo h($r["NO"]); ?></td>
                    <td class="num"><?php echo h($r["ITEM_ID"]); ?></td>
                    <td class="text"><?php echo h($r["ITEM_CODE"]); ?></td>
                    <td class="text"><?php echo h($r["ITEM_NO"]); ?></td>
                    <td class="text"><?php echo h($r["ITEM_NAME"]); ?></td>
                    <td class="num"><?php echo h((int)$r["DEPRESIASI"]); ?></td>
                    <td class="num"><?php echo h((int)$r["DELIVERY"]); ?></td>
                    <td class="num"><?php echo h((int)$r["DIFF"]); ?></td>
                </tr>
            <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
                <tr class="grand-row">
                    <td colspan="5" style="text-align:right;">GRAND TOTAL</td>
                    <td class="num"><?php echo h((int)$r["DEPRESIASI"]); ?></td>
                    <td class="num"><?php echo h((int)$r["DELIVERY"]); ?></td>
                    <td class="num"><?php echo h((int)$r["DIFF"]); ?></td>
                </tr>
            <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                <tr><td colspan="8"><?php echo h($r["MESSAGE"]); ?></td></tr>
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
    <title>Report View Depresiasi</title>
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

        .col-no { width: 4%; text-align: right; }
        .col-id { width: 6%; text-align: right; }
        .col-code { width: 10%; text-align: left; }
        .col-itemno { width: 18%; text-align: left; }
        .col-name { width: 38%; text-align: left; }
        .col-qty { width: 8%; text-align: right; }

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
    <a href="depresiasi_view_report.php?EXPORT=1">EXPORT EXCEL</a>
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
                    <div class="report-title">REPORT VIEW DEPRESIASI</div>
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
                    <th class="col-no">No</th>
                    <th class="col-id">Item ID</th>
                    <th class="col-code">Item Code</th>
                    <th class="col-itemno">Item No</th>
                    <th class="col-name">Item Name</th>
                    <th class="col-qty">Depresiasi</th>
                    <th class="col-qty">Delivery</th>
                    <th class="col-qty">Diff</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < count($pageRows); $i++) { ?>
                    <?php $r = $pageRows[$i]; ?>

                    <?php if ($r["ROW_TYPE"] == "DETAIL") { ?>
                        <tr>
                            <td class="col-no num"><?php echo h($r["NO"]); ?></td>
                            <td class="col-id num"><?php echo h($r["ITEM_ID"]); ?></td>
                            <td class="col-code"><?php echo h($r["ITEM_CODE"]); ?></td>
                            <td class="col-itemno"><?php echo h($r["ITEM_NO"]); ?></td>
                            <td class="col-name"><?php echo h($r["ITEM_NAME"]); ?></td>
                            <td class="col-qty num"><?php echo h(fmt_zero_dash($r["DEPRESIASI"], 0)); ?></td>
                            <td class="col-qty num"><?php echo h(fmt_zero_dash($r["DELIVERY"], 0)); ?></td>
                            <td class="col-qty num"><?php echo h(fmt_zero_dash($r["DIFF"], 0)); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
                        <tr class="grand-total-row">
                            <td colspan="5" style="text-align:right;">GRAND TOTAL</td>
                            <td class="num"><?php echo h(fmt_num($r["DEPRESIASI"], 0)); ?></td>
                            <td class="num"><?php echo h(fmt_num($r["DELIVERY"], 0)); ?></td>
                            <td class="num"><?php echo h(fmt_num($r["DIFF"], 0)); ?></td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr>
                            <td colspan="8" class="center"><?php echo h($r["MESSAGE"]); ?></td>
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
