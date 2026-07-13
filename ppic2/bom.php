<?php
if (session_id() == "") {
    session_start();
}

require_once dirname(__DIR__) . "/config/database_ppic.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function get_value($name, $default) {
    if (isset($_GET[$name])) {
        return trim((string)$_GET[$name]);
    }
    return $default;
}

function fmt_qty($value) {
    if ($value === null || $value === "") {
        return "-";
    }

    $n = floatval($value);

    if ($n == 0) {
        return "-";
    }

    if (floor($n) == $n) {
        return number_format($n, 0, ".", ",");
    }

    return rtrim(rtrim(number_format($n, 6, ".", ","), "0"), ".");
}

$cust = get_value("cust", "");
$part = get_value("part", "");

/*
 * Jumlah baris detail per halaman.
 * Nilai ini dipakai untuk pagination tampilan, print, dan export PDF.
 */
$pageSize = intval(get_value("page_size", "30"));
$allowedPageSizes = array(20, 25, 30, 35, 40, 50);
if (!in_array($pageSize, $allowedPageSizes)) {
    $pageSize = 30;
}

$printDate = date("d-M-Y H:i:s");

/* ======================================================
   AUTOCOMPLETE CUSTOMER
====================================================== */
$custList = array();

$sqlCust = "
    SELECT TOP 300
        ISNULL(CUST_CODE, '') AS CUST_CODE,
        ISNULL(CUST_COMP, '') AS CUST_COMP
    FROM dbo.CUST
    ORDER BY CUST_CODE
";

$stmtCust = sqlsrv_query($conn, $sqlCust);

if ($stmtCust !== false) {
    while ($c = sqlsrv_fetch_array($stmtCust, SQLSRV_FETCH_ASSOC)) {
        $custList[] = array(
            "CUST_CODE" => trim((string)$c["CUST_CODE"]),
            "CUST_COMP" => trim((string)$c["CUST_COMP"])
        );
    }
}

/* ======================================================
   AUTOCOMPLETE PART
====================================================== */
$partList = array();

$sqlPartList = "
    SELECT DISTINCT TOP 500
        ISNULL(PART_CODE, '') AS PART_CODE,
        ISNULL(PART_NO, '') AS PART_NO,
        ISNULL(PART_NAME, '') AS PART_NAME
    FROM dbo.BOM_VIEW
    WHERE
        ISNULL(PART_CODE, '') <> ''
        OR ISNULL(PART_NO, '') <> ''
        OR ISNULL(PART_NAME, '') <> ''
    ORDER BY
        PART_CODE,
        PART_NO,
        PART_NAME
";

$stmtPartList = sqlsrv_query($conn, $sqlPartList);

if ($stmtPartList !== false) {
    while ($p = sqlsrv_fetch_array($stmtPartList, SQLSRV_FETCH_ASSOC)) {
        $partList[] = array(
            "PART_CODE" => trim((string)$p["PART_CODE"]),
            "PART_NO" => trim((string)$p["PART_NO"]),
            "PART_NAME" => trim((string)$p["PART_NAME"])
        );
    }
}

/* ======================================================
   DATA REPORT
====================================================== */
$where = array();
$params = array();

if ($cust != "") {
    $where[] = "(CUST_CODE LIKE ? OR CUST_COMP LIKE ?)";
    $params[] = "%" . $cust . "%";
    $params[] = "%" . $cust . "%";
}

if ($part != "") {
    $where[] = "(PART_CODE LIKE ? OR PART_NO LIKE ? OR PART_NAME LIKE ? OR ITEM_CODE LIKE ? OR ITEM_NAME LIKE ?)";
    $params[] = "%" . $part . "%";
    $params[] = "%" . $part . "%";
    $params[] = "%" . $part . "%";
    $params[] = "%" . $part . "%";
    $params[] = "%" . $part . "%";
}

$sqlWhere = "";

if (count($where) > 0) {
    $sqlWhere = " WHERE " . implode(" AND ", $where);
}

$sql = "
    SELECT
        CUST_ID,
        ISNULL(CUST_CODE, '') AS CUST_CODE,
        ISNULL(CUST_COMP, '') AS CUST_COMP,
        PART_ID,
        ISNULL(PART_CODE, '') AS PART_CODE,
        ISNULL(PART_NO, '') AS PART_NO,
        ISNULL(PART_NAME, '') AS PART_NAME,
        ISNULL(ITEM_CODE, '') AS ITEM_CODE,
        ISNULL(ITEM_NAME, '') AS ITEM_NAME,
        ISNULL(QTY, 0) AS QTY,
        ISNULL(UNIT, '') AS UNIT,
        ITEM_ID,
        ISNULL(SUP_CODE, '') AS SUP_CODE,
        ISNULL(SUP_COMP, '') AS SUP_COMP
    FROM dbo.BOM_VIEW
    " . $sqlWhere . "
    ORDER BY
        CUST_CODE,
        PART_CODE,
        PART_NO,
        ITEM_CODE,
        ITEM_NAME
";

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("<pre>Query BOM_VIEW error:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

/* ======================================================
   GROUP DATA + HAPUS DUPLIKASI DETAIL
====================================================== */
$data = array();
$checkDuplicate = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $key =
        trim((string)$r["CUST_CODE"]) . "|" .
        trim((string)$r["PART_ID"]) . "|" .
        trim((string)$r["PART_CODE"]) . "|" .
        trim((string)$r["PART_NO"]);

    if (!isset($data[$key])) {
        $data[$key] = array(
            "CUST_CODE" => $r["CUST_CODE"],
            "CUST_COMP" => $r["CUST_COMP"],
            "PART_ID" => $r["PART_ID"],
            "PART_CODE" => $r["PART_CODE"],
            "PART_NO" => $r["PART_NO"],
            "PART_NAME" => $r["PART_NAME"],
            "DETAIL" => array()
        );

        $checkDuplicate[$key] = array();
    }

    /*
        Cegah material dobel dalam PART yang sama.
        Kalau ITEM_CODE + ITEM_NAME + QTY + UNIT sama,
        hanya ditampilkan 1 kali.
    */
    $detailKey =
        trim((string)$r["ITEM_CODE"]) . "|" .
        trim((string)$r["ITEM_NAME"]) . "|" .
        trim((string)$r["QTY"]) . "|" .
        trim((string)$r["UNIT"]);

    if (isset($checkDuplicate[$key][$detailKey])) {
        continue;
    }

    $checkDuplicate[$key][$detailKey] = true;

    $data[$key]["DETAIL"][] = array(
        "ITEM_CODE" => $r["ITEM_CODE"],
        "ITEM_NAME" => $r["ITEM_NAME"],
        "QTY" => $r["QTY"],
        "UNIT" => $r["UNIT"],
        "ITEM_ID" => $r["ITEM_ID"],
        "SUP_CODE" => $r["SUP_CODE"],
        "SUP_COMP" => $r["SUP_COMP"]
    );
}

/*
 * SORT DETAIL BERDASARKAN ITEM_CODE ASC.
 * Secondary sort ITEM_NAME agar urutan stabil jika ITEM_CODE sama.
 */
foreach ($data as $groupKey => $groupData) {
    usort($data[$groupKey]["DETAIL"], function ($a, $b) {
        $cmp = strnatcasecmp(trim((string)$a["ITEM_CODE"]), trim((string)$b["ITEM_CODE"]));
        if ($cmp !== 0) {
            return $cmp;
        }
        return strnatcasecmp(trim((string)$a["ITEM_NAME"]), trim((string)$b["ITEM_NAME"]));
    });
}

$totalPart = count($data);

/* ======================================================
   PAGINATION
   - Mempertahankan group Customer + Part
   - Jika detail Part terlalu banyak, lanjut ke halaman berikutnya
   - Header customer/part diulang pada halaman lanjutan
====================================================== */
$pages = array();
$currentPage = array();
$usedRows = 0;
$lastCustOnPage = "";

function push_page(&$pages, &$currentPage, &$usedRows, &$lastCustOnPage) {
    if (count($currentPage) > 0) {
        $pages[] = $currentPage;
    }
    $currentPage = array();
    $usedRows = 0;
    $lastCustOnPage = "";
}

foreach ($data as $key => $h) {
    $details = $h["DETAIL"];
    $detailCount = count($details);
    $offset = 0;
    $isContinuation = false;

    /*
     * Part tanpa detail tetap ditampilkan satu kali.
     */
    if ($detailCount == 0) {
        $needCust = ($lastCustOnPage !== $h["CUST_CODE"]);
        $headerCost = 1 + ($needCust ? 1 : 0);

        if ($usedRows > 0 && ($usedRows + $headerCost) > $pageSize) {
            push_page($pages, $currentPage, $usedRows, $lastCustOnPage);
            $needCust = true;
        }

        $currentPage[] = array(
            "SHOW_CUST" => $needCust,
            "CONTINUED" => false,
            "HEAD" => $h,
            "DETAIL" => array()
        );

        $usedRows += $headerCost;
        $lastCustOnPage = $h["CUST_CODE"];
        continue;
    }

    while ($offset < $detailCount) {
        $needCust = ($lastCustOnPage !== $h["CUST_CODE"]);
        $headerCost = 1 + ($needCust ? 1 : 0);

        /*
         * Tidak cukup ruang untuk header + minimal 1 detail:
         * tutup halaman saat ini dan mulai halaman baru.
         */
        if ($usedRows > 0 && ($usedRows + $headerCost + 1) > $pageSize) {
            push_page($pages, $currentPage, $usedRows, $lastCustOnPage);
            $needCust = true;
            $headerCost = 2;
        }

        $available = $pageSize - $usedRows - $headerCost;
        if ($available < 1) {
            $available = 1;
        }

        $remaining = $detailCount - $offset;
        $take = ($remaining < $available) ? $remaining : $available;
        $slice = array_slice($details, $offset, $take);

        $currentPage[] = array(
            "SHOW_CUST" => $needCust,
            "CONTINUED" => $isContinuation,
            "HEAD" => $h,
            "DETAIL" => $slice
        );

        $usedRows += $headerCost + $take;
        $lastCustOnPage = $h["CUST_CODE"];
        $offset += $take;
        $isContinuation = true;

        /*
         * Masih ada detail Part yang belum masuk:
         * paksa lanjut ke halaman baru.
         */
        if ($offset < $detailCount) {
            push_page($pages, $currentPage, $usedRows, $lastCustOnPage);
        }
    }
}

push_page($pages, $currentPage, $usedRows, $lastCustOnPage);

$totalPages = count($pages);
if ($totalPages == 0) {
    $totalPages = 1;
    $pages = array(array());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bill Of Material</title>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #9c9c9c;
            font-family: "Courier New", Courier, monospace;
            font-size: 11px;
            color: #000000;
        }

        .filter {
            width: calc(100% - 24px);
            max-width: 1060px;
            margin: 8px auto;
            background: #d4d0c8;
            border: 1px solid #777777;
            padding: 8px;
            box-sizing: border-box;
            font-family: Tahoma, Arial, sans-serif;
            white-space: nowrap;
        }

        .filter input,
        .filter select {
            height: 24px;
            border: 1px solid #777777;
            padding: 2px 5px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            box-sizing: border-box;
            background: #ffffff;
        }

        .btn {
            height: 26px;
            padding: 2px 12px;
            border: 1px solid #777777;
            background: #eeeeee;
            cursor: pointer;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            color: #000000;
            text-decoration: none;
            box-sizing: border-box;
        }

        .toolbar {
            width: calc(100% - 24px);
            max-width: 1060px;
            margin: 0 auto 6px auto;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }

        .toolbar select {
            height: 26px;
            border: 1px solid #777777;
            background: #ffffff;
            padding: 2px 6px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }

        .toolbar .status {
            margin-right: auto;
            color: #ffffff;
            background: #555555;
            border: 1px solid #333333;
            padding: 5px 8px;
        }

        .page-hidden {
            display: none !important;
        }

        .continued-label {
            font-weight: normal;
            font-size: 9px;
        }

        .page {
            width: calc(100% - 24px);
            max-width: 1060px;
            min-height: 690px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 16px;
            box-sizing: border-box;
            border: 1px solid #000000;
            overflow: hidden;
        }

        .header {
            position: relative;
            border-bottom: 2px solid #000000;
            padding-bottom: 4px;
            margin-bottom: 3px;
        }

        .company {
            position: absolute;
            left: 0;
            top: 0;
            font-size: 13px;
            font-weight: normal;
        }

        .dept {
            position: absolute;
            left: 0;
            top: 17px;
            font-size: 10px;
        }

        .title {
            text-align: center;
            font-size: 20px;
            font-weight: normal;
            padding-top: 16px;
            letter-spacing: 2px;
        }

        .page-info {
            position: absolute;
            right: 0;
            top: 0;
            text-align: right;
            font-size: 10px;
        }

        .print-date {
            position: absolute;
            right: 0;
            top: 37px;
            text-align: right;
            font-size: 10px;
        }

        .spaced-title {
            display: table;
            width: 100%;
            border-bottom: 1px solid #000000;
            margin-top: 6px;
            padding: 2px 0;
            font-size: 11px;
            font-weight: normal;
        }

        .spaced-title .part-title {
            display: table-cell;
            width: 48%;
            text-align: left;
            letter-spacing: 8px;
        }

        .spaced-title .mat-title {
            display: table-cell;
            width: 52%;
            text-align: center;
            letter-spacing: 8px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.report th {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            padding: 2px 3px;
            text-align: left;
            font-weight: normal;
            font-size: 10px;
            line-height: 12px;
            overflow: hidden;
        }

        table.report td {
            padding: 1px 3px;
            vertical-align: top;
            font-size: 10px;
            line-height: 13px;
            overflow: hidden;
            word-wrap: break-word;
        }

        .cust-row td {
            font-weight: bold;
            padding-top: 5px;
            border-top: 1px solid #000000;
        }

        .part-row td {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            font-weight: bold;
            background: #f4f4f4;
        }

        .qty {
            text-align: right !important;
            white-space: nowrap;
        }

        .unit {
            text-align: left;
            white-space: nowrap;
        }

        .footer-line {
            border-top: 1px solid #000000;
            margin-top: 10px;
            height: 20px;
        }

        .no-data {
            text-align: center;
            padding: 60px 0;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 14px;
        }

        @page {
            size: A4 landscape;
            margin: 7mm;
        }

        @media print {
            html, body {
                background: #ffffff;
                font-size: 9px;
            }

            .toolbar,
            .filter,
            .print-exclude {
                display: none !important;
            }

            .page {
                width: 100%;
                max-width: none;
                min-height: auto;
                margin: 0;
                border: none;
                padding: 0;
                overflow: visible;
                page-break-after: always;
            }

            .page:last-child {
                page-break-after: auto;
            }

            .title {
                font-size: 18px;
            }

            table.report th,
            table.report td {
                font-size: 8.5px;
                line-height: 11px;
                padding: 1px 2px;
            }
        }
    </style>
</head>

<body>

<datalist id="custOptions">
    <?php for ($i = 0; $i < count($custList); $i++) { ?>
        <option value="<?php echo h($custList[$i]["CUST_CODE"]); ?>">
            <?php echo h($custList[$i]["CUST_COMP"]); ?>
        </option>
        <option value="<?php echo h($custList[$i]["CUST_COMP"]); ?>">
            <?php echo h($custList[$i]["CUST_CODE"]); ?>
        </option>
    <?php } ?>
</datalist>

<datalist id="partOptions">
    <?php for ($i = 0; $i < count($partList); $i++) { ?>
        <?php if ($partList[$i]["PART_CODE"] != "") { ?>
            <option value="<?php echo h($partList[$i]["PART_CODE"]); ?>">
                <?php echo h($partList[$i]["PART_NAME"]); ?>
            </option>
        <?php } ?>

        <?php if ($partList[$i]["PART_NO"] != "") { ?>
            <option value="<?php echo h($partList[$i]["PART_NO"]); ?>">
                <?php echo h($partList[$i]["PART_NAME"]); ?>
            </option>
        <?php } ?>

        <?php if ($partList[$i]["PART_NAME"] != "") { ?>
            <option value="<?php echo h($partList[$i]["PART_NAME"]); ?>">
                <?php echo h($partList[$i]["PART_CODE"] . " " . $partList[$i]["PART_NO"]); ?>
            </option>
        <?php } ?>
    <?php } ?>
</datalist>

<div class="filter">
    <form method="get" autocomplete="off">
        <b>Filter BOM</b>
        &nbsp;&nbsp;
        Customer:
        <input
            type="text"
            name="cust"
            list="custOptions"
            value="<?php echo h($cust); ?>"
            placeholder="Kode / nama customer"
            style="width:190px;"
        >

        &nbsp;
        Part:
        <input
            type="text"
            name="part"
            list="partOptions"
            value="<?php echo h($part); ?>"
            placeholder="Part code / no / name"
            style="width:260px;"
        >

        &nbsp;
        Baris/Page:
        <select name="page_size" style="width:72px;">
            <?php foreach ($allowedPageSizes as $ps) { ?>
                <option value="<?php echo $ps; ?>" <?php echo ($pageSize == $ps) ? "selected" : ""; ?>>
                    <?php echo $ps; ?>
                </option>
            <?php } ?>
        </select>

        &nbsp;
        <button type="submit" class="btn">FILTER</button>
        <a href="bom.php" class="btn">ALL</a>
    </form>
</div>

<div class="toolbar">
    <div class="status">
        <?php echo h($totalPart); ?> Part |
        <?php echo h($totalPages); ?> Page |
        Sort ITEM_CODE ASC
    </div>

    <label for="pageSelect"><b>Pilih Halaman:</b></label>
    <select id="pageSelect" onchange="showSelectedPage()">
        <option value="all">Semua Halaman</option>
        <?php for ($pNo = 1; $pNo <= $totalPages; $pNo++) { ?>
            <option value="<?php echo $pNo; ?>">Halaman <?php echo $pNo; ?></option>
        <?php } ?>
    </select>

    <button type="button" class="btn" onclick="showSelectedPage()">TAMPILKAN</button>
    <button type="button" class="btn" onclick="printSelectedPage()">PRINT PILIHAN</button>
    <button type="button" class="btn" onclick="exportSelectedPagePdf()">EXPORT PDF</button>
    <button type="button" class="btn" onclick="window.location.href='dashboard_ppic.php'">CLOSE</button>
</div>

<div id="pagesContainer">
<?php for ($pageIndex = 0; $pageIndex < count($pages); $pageIndex++) { ?>
    <?php
        $pageNo = $pageIndex + 1;
        $pageBlocks = $pages[$pageIndex];
    ?>
    <div class="page bom-page" data-page-no="<?php echo $pageNo; ?>">
        <div class="header">
            <div class="company">P.T. IMC TEKNO INDONESIA</div>
            <div class="dept">Commercial Business</div>
            <div class="title">BILL OF MATERIAL</div>
            <div class="page-info">Page <?php echo $pageNo; ?> of <?php echo $totalPages; ?></div>
            <div class="print-date">Print Date : <?php echo h($printDate); ?></div>

            <div class="spaced-title">
                <div class="part-title">PART</div>
                <div class="mat-title">MATERIAL</div>
            </div>
        </div>

        <?php if ($totalPart == 0) { ?>
            <div class="no-data">Data BOM tidak ditemukan.</div>
        <?php } else { ?>

            <table class="report">
                <colgroup>
                    <col style="width:11%;">
                    <col style="width:29%;">
                    <col style="width:13%;">
                    <col style="width:35%;">
                    <col style="width:7%;">
                    <col style="width:5%;">
                </colgroup>

                <thead>
                    <tr>
                        <th>PART_CODE</th>
                        <th>PART_NAME</th>
                        <th>ITEM_CODE</th>
                        <th>ITEM_NAME</th>
                        <th class="qty">QTY</th>
                        <th>UNIT</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($pageBlocks as $block) { ?>
                        <?php $h = $block["HEAD"]; ?>

                        <?php if ($block["SHOW_CUST"]) { ?>
                            <tr class="cust-row">
                                <td colspan="6">
                                    <?php echo h($h["CUST_CODE"]); ?>
                                    &nbsp;
                                    <?php echo h($h["CUST_COMP"]); ?>
                                </td>
                            </tr>
                        <?php } ?>

                        <tr class="part-row">
                            <td><?php echo h($h["PART_CODE"]); ?></td>
                            <td>
                                <?php echo h($h["PART_NAME"]); ?>
                                <?php if ($block["CONTINUED"]) { ?>
                                    <span class="continued-label">(lanjutan)</span>
                                <?php } ?>
                            </td>
                            <td colspan="4"><?php echo h($h["PART_NO"]); ?></td>
                        </tr>

                        <?php for ($i = 0; $i < count($block["DETAIL"]); $i++) { ?>
                            <?php $d = $block["DETAIL"][$i]; ?>
                            <tr>
                                <td></td>
                                <td></td>
                                <td><?php echo h($d["ITEM_CODE"]); ?></td>
                                <td><?php echo h($d["ITEM_NAME"]); ?></td>
                                <td class="qty"><?php echo h(fmt_qty($d["QTY"])); ?></td>
                                <td class="unit"><?php echo h($d["UNIT"]); ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>

        <?php } ?>

        <div class="footer-line"></div>
    </div>
<?php } ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
(function () {
    "use strict";

    function getPages() {
        return Array.prototype.slice.call(document.querySelectorAll(".bom-page"));
    }

    function getSelectedValue() {
        var sel = document.getElementById("pageSelect");
        return sel ? sel.value : "all";
    }

    window.showSelectedPage = function () {
        var selected = getSelectedValue();
        var pages = getPages();

        for (var i = 0; i < pages.length; i++) {
            var pageNo = pages[i].getAttribute("data-page-no");
            var shouldShow = (selected === "all" || selected === pageNo);
            if (shouldShow) {
                pages[i].classList.remove("page-hidden");
            } else {
                pages[i].classList.add("page-hidden");
            }
        }

        if (selected !== "all") {
            var target = document.querySelector('.bom-page[data-page-no="' + selected + '"]');
            if (target) {
                window.scrollTo(0, Math.max(0, target.offsetTop - 10));
            }
        }
    };

    window.printSelectedPage = function () {
        var selected = getSelectedValue();
        var pages = getPages();

        for (var i = 0; i < pages.length; i++) {
            var pageNo = pages[i].getAttribute("data-page-no");
            if (selected !== "all" && selected !== pageNo) {
                pages[i].classList.add("print-exclude");
            }
        }

        window.print();

        setTimeout(function () {
            for (var j = 0; j < pages.length; j++) {
                pages[j].classList.remove("print-exclude");
            }
        }, 500);
    };

    window.exportSelectedPagePdf = function () {
        var selected = getSelectedValue();
        var pages = getPages();

        if (typeof html2pdf === "undefined") {
            alert("Library PDF belum berhasil dimuat. Cek koneksi internet/CDN.");
            return;
        }

        var exportWrap = document.createElement("div");
        exportWrap.id = "pdfExportWrap";
        exportWrap.style.background = "#ffffff";

        var selectedPages = [];
        for (var i = 0; i < pages.length; i++) {
            var pageNo = pages[i].getAttribute("data-page-no");
            if (selected === "all" || selected === pageNo) {
                selectedPages.push(pages[i]);
            }
        }

        if (selectedPages.length === 0) {
            alert("Halaman tidak ditemukan.");
            return;
        }

        for (var j = 0; j < selectedPages.length; j++) {
            var clone = selectedPages[j].cloneNode(true);
            clone.classList.remove("page-hidden");
            clone.classList.remove("print-exclude");
            clone.style.width = "100%";
            clone.style.maxWidth = "none";
            clone.style.margin = "0 0 5mm 0";
            clone.style.border = "none";
            clone.style.boxSizing = "border-box";
            clone.style.pageBreakAfter = (j < selectedPages.length - 1) ? "always" : "auto";
            exportWrap.appendChild(clone);
        }

        document.body.appendChild(exportWrap);

        var fileName = (selected === "all")
            ? "BOM_semua_halaman.pdf"
            : "BOM_halaman_" + selected + ".pdf";

        var options = {
            margin: [7, 7, 7, 7],
            filename: fileName,
            image: {
                type: "jpeg",
                quality: 0.98
            },
            html2canvas: {
                scale: 2,
                useCORS: true,
                letterRendering: true,
                backgroundColor: "#ffffff"
            },
            jsPDF: {
                unit: "mm",
                format: "a4",
                orientation: "landscape"
            },
            pagebreak: {
                mode: ["css", "legacy"]
            }
        };

        html2pdf()
            .set(options)
            .from(exportWrap)
            .save()
            .then(function () {
                if (exportWrap.parentNode) {
                    exportWrap.parentNode.removeChild(exportWrap);
                }
            })
            .catch(function (err) {
                if (exportWrap.parentNode) {
                    exportWrap.parentNode.removeChild(exportWrap);
                }
                alert("Export PDF gagal: " + err);
            });
    };
})();
</script>

</body>
</html>