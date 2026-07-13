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

/*
 * Pagination berbasis tinggi relatif row, bukan sekadar jumlah row.
 * Ini lebih stabil setelah font diperbesar ke Segoe UI dan mencegah
 * satu blok report meluber ke halaman print berikutnya.
 */
function print_row_units($row, $nextRow = null) {
    $type = isset($row["ROW_TYPE"]) ? $row["ROW_TYPE"] : "";

    if ($type === "CUSTOMER") return 1.35;
    if ($type === "ITEM") return 1.05;
    if ($type === "PRICE") {
        $units = 1.00;
        if ($nextRow === null || in_array($nextRow["ROW_TYPE"], array("ITEM", "CUSTOMER"), true)) {
            $units += 0.20; // ruang separator antar item
        }
        return $units;
    }

    return 1.00;
}

function paginate_print_rows($rows, $maxUnits = 30.5) {
    $pages = array();
    $page = array();
    $usedUnits = 0.0;

    $currentCustomer = null;
    $currentItem = null;
    $count = count($rows);

    for ($i = 0; $i < $count; $i++) {
        $row = $rows[$i];
        $nextRow = ($i + 1 < $count) ? $rows[$i + 1] : null;
        $units = print_row_units($row, $nextRow);

        if (count($page) > 0 && ($usedUnits + $units) > $maxUnits) {
            $pages[] = $page;
            $page = array();
            $usedUnits = 0.0;

            // Ulangi context customer/item pada halaman lanjutan supaya mudah dibaca.
            if ($row["ROW_TYPE"] !== "CUSTOMER" && $currentCustomer !== null) {
                $repeatCustomer = $currentCustomer;
                $repeatCustomer["CONTINUED"] = 1;
                $page[] = $repeatCustomer;
                $usedUnits += 1.35;
            }

            if ($row["ROW_TYPE"] === "PRICE" && $currentItem !== null) {
                $repeatItem = $currentItem;
                $repeatItem["CONTINUED"] = 1;
                $page[] = $repeatItem;
                $usedUnits += 1.05;
            }
        }

        $page[] = $row;
        $usedUnits += $units;

        if ($row["ROW_TYPE"] === "CUSTOMER") {
            $currentCustomer = $row;
            $currentItem = null;
        } elseif ($row["ROW_TYPE"] === "ITEM") {
            $currentItem = $row;
        }
    }

    if (count($page) > 0) {
        $pages[] = $page;
    }

    return $pages;
}


$is_filter = isset($_GET["CUST_CODE"]) || isset($_POST["CUST_CODE"]);

$cust_code = get_param("CUST_CODE", "");

if ($is_filter && $cust_code == "") {
    $cust_code = "%";
}

$dataRows = array();
$printRows = array();
$pages = array();
$totalPages = 0;
$pageCapacity = 30.5;

if ($is_filter) {

    /*
        % = semua customer
        selain % = filter exact by CUST_CODE
    */
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
        WHERE
            ? = '%'
            OR CUST_CODE = ?
        ORDER BY
            CUST_CODE,
            ITEM_CODE,
            ITEM_NO,
            ITEM_NAME,
            PRDT_START,
            PRDT_END
    ";

    $stmt = sqlsrv_query($conn, $sql, array($cust_code, $cust_code));

    if ($stmt === false) {
        die("<pre>Query Sales Price History gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

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

    $pages = paginate_print_rows($printRows, $pageCapacity);
    $totalPages = count($pages);

    if ($totalPages <= 0) {
        $totalPages = 1;
    }
}


/* ============================================================
   Bersihkan resource database
   Export Excel dipisahkan ke: sales_price_history_export_excel.php
   ============================================================ */
if ($is_filter && isset($stmt) && $stmt !== false) {
    sqlsrv_free_stmt($stmt);
}

if (isset($conn) && $conn !== false) {
    sqlsrv_close($conn);
}

$selfFile = basename($_SERVER["PHP_SELF"]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Price History</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        :root {
            --grid: #d9dee5;
            --head: #f8f9fa;
            --customer: #e9ecef;
            --text: #212529;
            --muted: #6c757d;
        }

        * { box-sizing: border-box; }

        html, body { margin: 0; padding: 0; }

        body {
            background: #9a9a9a;
            font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 12px;
            color: var(--text);
        }

        .autocomplete-wrap {
            position: relative;
            display: inline-block;
        }

        .autocomplete-list {
            position: absolute;
            top: 36px;
            left: 0;
            width: 100%;
            min-width: 340px;
            max-height: 230px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid #ced4da;
            z-index: 9999;
            display: none;
            border-radius: 0 0 6px 6px;
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        }

        .autocomplete-item {
            padding: 7px 9px;
            border-bottom: 1px solid #eef1f4;
            cursor: pointer;
            line-height: 17px;
            font-family: "Segoe UI", Arial, sans-serif;
            font-size: 12px;
        }

        .autocomplete-item:hover,
        .autocomplete-item.active {
            background: #0d6efd;
            color: #ffffff;
        }

        /* Toolbar Bootstrap */
        .report-toolbar {
            width: min(1100px, calc(100% - 24px));
            margin: 8px auto;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .report-toolbar .card {
            border: 0;
            border-radius: 10px;
        }

        .report-toolbar .form-control {
            min-width: 210px;
            height: 34px;
            font-size: 12px;
        }

        .report-toolbar .btn {
            height: 34px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .report-toolbar .autocomplete-wrap {
            width: min(360px, 100%);
        }

        .report-pages {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        /* Satu .page = tepat satu lembar A4 */
        .page {
            width: 210mm;
            height: 297mm;
            min-height: 297mm;
            margin: 10px auto;
            background: #ffffff;
            padding: 9mm 10mm;
            box-sizing: border-box;
            overflow: hidden;
            break-after: page;
            page-break-after: always;
            box-shadow: 0 2px 10px rgba(0,0,0,.22);
        }

        .page:last-child {
            break-after: auto;
            page-break-after: auto;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-family: "Segoe UI", Roboto, Arial, sans-serif;
        }

        .header td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        .company {
            width: 32%;
            font-size: 11px;
            line-height: 1.25;
        }

        .company-title {
            font-size: 15px;
            font-weight: 500;
        }

        .title-area {
            width: 38%;
            text-align: center;
        }

        .report-title {
            font-size: 19px;
            font-weight: 700;
            margin-top: 2px;
            letter-spacing: .1px;
        }

        .filter-title {
            font-size: 11px;
            margin-top: 7px;
            font-weight: 400;
        }

        .right-info {
            width: 30%;
            text-align: right;
            font-size: 10.5px;
            line-height: 1.45;
        }

        .print-date { margin-top: 14px; }

        /* Tampilan tabel disamakan dengan report referensi: Segoe UI + grid tipis */
        .price-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 11px;
            color: #212529;
            border: 1px solid var(--grid);
        }

        .price-table th {
            background: var(--head);
            border: 1px solid var(--grid);
            padding: 6px 7px;
            height: 29px;
            font-weight: 600;
            text-align: left;
            font-size: 10.5px;
            line-height: 1.2;
            overflow: hidden;
            white-space: nowrap;
        }

        .price-table td {
            border: 1px solid var(--grid);
            padding: 4px 7px;
            height: 24px;
            line-height: 15px;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 11px;
        }

        .col-part { width: 48%; }
        .col-price { width: 11%; text-align: right; }
        .col-start { width: 13%; }
        .col-end { width: 13%; }
        .col-quot { width: 15%; }

        .customer-row td {
            height: 27px;
            background: var(--customer);
            font-weight: 700;
            font-size: 11.5px;
            padding-top: 5px;
            padding-bottom: 5px;
            letter-spacing: 0;
        }

        .part-label {
            letter-spacing: 0;
            font-weight: 600;
        }

        .item-row td {
            height: 24px;
            padding-top: 4px;
            padding-bottom: 4px;
            font-weight: 500;
        }

        .price-row td { height: 23px; }
        .price-row .col-price { text-align: right; font-variant-numeric: tabular-nums; }
        .price-row .col-start,
        .price-row .col-end,
        .price-row .col-quot { text-align: left; }

        .dash-row td {
            border-left: 1px solid var(--grid);
            border-right: 1px solid var(--grid);
            border-top: none;
            border-bottom: 1px dashed #adb5bd;
            height: 3px;
            min-height: 3px;
            padding: 0;
            line-height: 0;
        }

        .num { text-align: right; }
        .center { text-align: center; }

        .continued-note {
            margin-left: 6px;
            color: var(--muted);
            font-size: 9px;
            font-weight: 500;
            font-style: italic;
        }


        @media screen and (max-width: 900px) {
            .page {
                transform-origin: top center;
            }
        }

        @media print {
            html, body {
                width: 210mm;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
            }

            .report-toolbar {
                display: none !important;
            }

            .report-pages {
                width: 210mm;
                margin: 0 !important;
                padding: 0 !important;
            }

            .page {
                width: 210mm;
                height: 297mm;
                min-height: 297mm;
                margin: 0 !important;
                padding: 9mm 10mm;
                border: none !important;
                box-shadow: none !important;
                overflow: hidden;
                break-inside: avoid;
                page-break-inside: avoid;
                break-after: page;
                page-break-after: always;
            }

            .page:last-child {
                break-after: auto;
                page-break-after: auto;
            }

            .price-table {
                font-size: 8.25pt;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .price-table th {
                font-size: 8pt;
                padding: 4px 6px;
                height: 7mm;
            }

            .price-table td {
                font-size: 8.25pt;
                padding: 3px 6px;
                height: 5.8mm;
                line-height: 1.15;
            }

            .customer-row td {
                font-size: 8.5pt;
                height: 6.5mm;
            }

            .item-row td { height: 5.8mm; }
            .price-row td { height: 5.6mm; }
            .dash-row td { height: 1mm; }
        }
    </style>
</head>

<body>

<div class="report-toolbar d-print-none">
    <div class="card shadow-sm">
        <div class="card-body py-2 px-3">
            <form id="filterForm" method="get" action="<?php echo h($selfFile); ?>" autocomplete="off" class="row g-2 align-items-center">
                <div class="col-12 col-lg-auto">
                    <label for="CUST_CODE" class="form-label mb-0 small fw-semibold text-secondary">Customer</label>
                </div>

                <div class="col-12 col-md-auto flex-grow-1">
                    <div class="autocomplete-wrap">
                        <input
                            type="text"
                            id="CUST_CODE"
                            name="CUST_CODE"
                            class="form-control form-control-sm"
                            value="<?php echo h($cust_code); ?>"
                            placeholder="Ketik customer / % untuk semua"
                        >
                        <div id="custSuggest" class="autocomplete-list"></div>
                    </div>
                </div>

                <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-filter"></i> FILTER
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAllCustomer()">
                        <i class="fas fa-users"></i> ALL
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm" onclick="printReport()">
                        <i class="fas fa-print"></i> PRINT
                    </button>
                    <button type="button" class="btn btn-success btn-sm" onclick="exportExcel()">
                        <i class="fas fa-file-excel"></i> EXPORT EXCEL
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="closeReport()">
                        <i class="fas fa-times"></i> CLOSE
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="reportPages" class="report-pages">

<?php if (!$is_filter) { ?>
    <div class="page">
        <table class="header">
            <tr>
                <td class="company">
                    <div class="company-title">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Department
                </td>

                <td class="title-area">
                    <div class="report-title">SALES PRICE HISTORY</div>
                    <div class="filter-title">Silahkan pilih customer lalu klik FILTER.</div>
                </td>

                <td class="right-info">
                    Page 0 of 0

                    <div class="print-date">
                        Print Date : <?php echo h(fmt_print_date()); ?>
                    </div>
                </td>
            </tr>
        </table>

        <div style="font-family:Arial,sans-serif;font-size:14px;text-align:center;margin-top:80px;">
            Data belum ditampilkan.<br><br>
            Isi Customer Code lalu klik <b>FILTER</b>.<br>
            Klik <b>ALL</b> jika ingin tampil semua customer.
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
                    <div class="report-title">SALES PRICE HISTORY</div>
                    <div class="filter-title">
                        Customer:
                        <?php echo h($cust_code == "%" ? "ALL" : $cust_code); ?>
                    </div>
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
                                <?php if (!empty($r["CONTINUED"])) { ?><span class="continued-note">(lanjutan)</span><?php } ?>
                            </td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "ITEM") { ?>
                        <tr class="item-row">
                            <td class="col-part">
                                [<?php echo h($r["ITEM_CODE"]); ?>]
                                [<?php echo h($r["ITEM_NO"]); ?>]
                                [<?php echo h($r["ITEM_NAME"]); ?>]
                                <?php if (!empty($r["CONTINUED"])) { ?><span class="continued-note">(lanjutan)</span><?php } ?>
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

            </tbody>
        </table>

    </div>
<?php } ?>

</div><!-- /#reportPages -->

<script>
var custRows = [];
var custIndex = -1;
var timer = null;

function enc(v) {
    return encodeURIComponent(v == null ? "" : v);
}

function htmlEncode(value) {
    return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

function printReport() {
    window.print();
}

function closeReport() {
    try {
        window.close();
    } catch (e) {}

    setTimeout(function () {
        if (!window.closed) {
            history.back();
        }
    }, 150);
}

function exportExcel() {
    var input = document.getElementById("CUST_CODE");
    var customer = input ? String(input.value || "").trim() : "";

    if (customer === "") {
        customer = "%";
    }

    var exportUrl = "sales_price_history_export_excel.php?CUST_CODE=" +
        encodeURIComponent(customer);

    // Export dilakukan oleh file PHP terpisah; halaman report tidak berpindah.
    window.location.href = exportUrl;
}

function setAllCustomer() {
    document.getElementById("CUST_CODE").value = "%";
    document.getElementById("filterForm").submit();
}

function hideSuggest() {
    var box = document.getElementById("custSuggest");
    box.style.display = "none";
    box.innerHTML = "";
    custRows = [];
    custIndex = -1;
}

function setActiveCust(index) {
    var box = document.getElementById("custSuggest");
    var items = box.getElementsByClassName("autocomplete-item");

    if (!items || items.length == 0) {
        custIndex = -1;
        return;
    }

    if (index < 0) {
        index = items.length - 1;
    }

    if (index >= items.length) {
        index = 0;
    }

    for (var i = 0; i < items.length; i++) {
        items[i].className = "autocomplete-item";
    }

    items[index].className = "autocomplete-item active";
    custIndex = index;
}

function chooseCust(index) {
    if (index < 0 || index >= custRows.length) {
        return;
    }

    var r = custRows[index];

    document.getElementById("CUST_CODE").value = r.CUST_CODE;
    hideSuggest();
    document.getElementById("filterForm").submit();
}

function renderSuggest(rows) {
    var box = document.getElementById("custSuggest");
    box.innerHTML = "";

    custRows = rows || [];
    custIndex = -1;

    if (!rows || rows.length == 0) {
        box.style.display = "none";
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        (function (r, idx) {
            var div = document.createElement("div");
            div.className = "autocomplete-item";
            div.innerHTML =
                "<b>" + htmlEncode(r.CUST_CODE) + "</b> - " +
                htmlEncode(r.CUST_COMP);

            div.onmouseover = function () {
                setActiveCust(idx);
            };

            div.onmousedown = function (e) {
                if (e && e.preventDefault) {
                    e.preventDefault();
                }

                chooseCust(idx);
            };

            box.appendChild(div);
        })(rows[i], i);
    }

    box.style.display = "block";
    setActiveCust(0);
}

function searchCustomer(q) {
    if (q == "" || q == "%") {
        hideSuggest();
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "ajax_customer_autocomplete.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var result;

            try {
                result = JSON.parse(xhr.responseText);
            } catch (e) {
                return;
            }

            if (result.rows) {
                renderSuggest(result.rows);
            } else {
                renderSuggest(result);
            }
        }
    };

    xhr.send("q=" + enc(q));
}

document.getElementById("CUST_CODE").onkeyup = function (e) {
    e = e || window.event;

    var key = e.keyCode || e.which;

    if (key == 40) {
        setActiveCust(custIndex + 1);
        return;
    }

    if (key == 38) {
        setActiveCust(custIndex - 1);
        return;
    }

    if (key == 13) {
        if (custRows.length > 0) {
            if (custIndex < 0) {
                custIndex = 0;
            }

            chooseCust(custIndex);
            return;
        }

        document.getElementById("filterForm").submit();
        return;
    }

    clearTimeout(timer);

    var q = this.value;

    timer = setTimeout(function () {
        searchCustomer(q);
    }, 250);
};

document.getElementById("CUST_CODE").onblur = function () {
    setTimeout(function () {
        hideSuggest();
    }, 250);
};
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>