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

/* ======================================================
   DATA EXPORT EXCEL
   Dibuat per halaman agar pilihan export sama dengan PDF/print.
====================================================== */
$excelPages = array();
$excelAllRows = array();

foreach ($pages as $excelPageIndex => $excelPageBlocks) {
    $excelPageNo = $excelPageIndex + 1;
    $excelPageRows = array();

    foreach ($excelPageBlocks as $excelBlock) {
        $excelHead = $excelBlock["HEAD"];
        $excelDetails = $excelBlock["DETAIL"];

        if (count($excelDetails) == 0) {
            $excelRow = array(
                "PAGE" => $excelPageNo,
                "CUSTOMER_CODE" => (string)$excelHead["CUST_CODE"],
                "CUSTOMER_NAME" => (string)$excelHead["CUST_COMP"],
                "PART_CODE" => (string)$excelHead["PART_CODE"],
                "PART_NO" => (string)$excelHead["PART_NO"],
                "PART_NAME" => (string)$excelHead["PART_NAME"],
                "ITEM_CODE" => "",
                "ITEM_NAME" => "",
                "QTY" => null,
                "UNIT" => ""
            );

            $excelPageRows[] = $excelRow;
            $excelAllRows[] = $excelRow;
            continue;
        }

        foreach ($excelDetails as $excelDetail) {
            $excelQty = null;
            if ($excelDetail["QTY"] !== null && $excelDetail["QTY"] !== "") {
                $excelQty = floatval($excelDetail["QTY"]);
            }

            $excelRow = array(
                "PAGE" => $excelPageNo,
                "CUSTOMER_CODE" => (string)$excelHead["CUST_CODE"],
                "CUSTOMER_NAME" => (string)$excelHead["CUST_COMP"],
                "PART_CODE" => (string)$excelHead["PART_CODE"],
                "PART_NO" => (string)$excelHead["PART_NO"],
                "PART_NAME" => (string)$excelHead["PART_NAME"],
                "ITEM_CODE" => (string)$excelDetail["ITEM_CODE"],
                "ITEM_NAME" => (string)$excelDetail["ITEM_NAME"],
                "QTY" => $excelQty,
                "UNIT" => (string)$excelDetail["UNIT"]
            );

            $excelPageRows[] = $excelRow;
            $excelAllRows[] = $excelRow;
        }
    }

    $excelPages[(string)$excelPageNo] = $excelPageRows;
}

$excelPayload = array(
    "all" => $excelAllRows,
    "pages" => $excelPages
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bill of Material</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous"
    >

    <style>
        :root {
            --app-bg: #eef2f7;
            --app-primary: #0d6efd;
            --app-text: #1f2937;
            --app-muted: #64748b;
            --paper-border: #d9e0e8;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            background: var(--app-bg);
            color: var(--app-text);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
        }

        .app-navbar {
            background: linear-gradient(135deg, #0d6efd 0%, #084298 100%);
        }

        .app-container {
            width: min(100% - 24px, 1280px);
            margin: 0 auto;
        }

        .filter-card,
        .toolbar-card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }

        .toolbar-card {
            position: sticky;
            top: 10px;
            z-index: 1020;
        }

        .form-label {
            margin-bottom: 0.35rem;
            color: #475569;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .form-control,
        .form-select {
            min-height: 40px;
            border-color: #d7dee8;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.12);
        }

        .summary-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            min-height: 34px;
            padding: 0.35rem 0.7rem;
            border: 1px solid #dbe4f0;
            border-radius: 999px;
            background: #f8fafc;
            color: #334155;
            font-size: 0.82rem;
            white-space: nowrap;
        }

        .summary-pill strong {
            color: #0f172a;
        }

        .page-hidden {
            display: none !important;
        }

        .report-page {
            width: min(100%, 1180px);
            min-height: 720px;
            margin: 0 auto 24px auto;
            padding: 24px;
            overflow: hidden;
            border: 1px solid var(--paper-border);
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 12px 34px rgba(15, 23, 42, 0.10);
            box-sizing: border-box;
            font-family: "Courier New", Courier, monospace;
            color: #000000;
        }

        .report-header {
            position: relative;
            padding-bottom: 6px;
            margin-bottom: 4px;
            border-bottom: 2px solid #000000;
        }

        .company {
            position: absolute;
            top: 0;
            left: 0;
            font-size: 13px;
        }

        .dept {
            position: absolute;
            top: 18px;
            left: 0;
            font-size: 10px;
        }

        .report-title {
            padding-top: 16px;
            text-align: center;
            font-size: 21px;
            font-weight: 700;
            letter-spacing: 3px;
        }

        .page-info {
            position: absolute;
            top: 0;
            right: 0;
            text-align: right;
            font-size: 10px;
        }

        .print-date {
            position: absolute;
            top: 38px;
            right: 0;
            text-align: right;
            font-size: 10px;
        }

        .spaced-title {
            display: grid;
            grid-template-columns: 48% 52%;
            width: 100%;
            padding: 3px 0;
            margin-top: 8px;
            border-bottom: 1px solid #000000;
            font-size: 11px;
        }

        .spaced-title .part-title {
            text-align: left;
            letter-spacing: 8px;
        }

        .spaced-title .mat-title {
            text-align: center;
            letter-spacing: 8px;
        }

        .report-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table.report {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.report th {
            padding: 4px 5px;
            overflow: hidden;
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            font-size: 10px;
            font-weight: 700;
            line-height: 12px;
            text-align: left;
        }

        table.report td {
            padding: 2px 5px;
            overflow: hidden;
            vertical-align: top;
            font-size: 10px;
            line-height: 13px;
            overflow-wrap: anywhere;
        }

        table.report tbody tr:not(.cust-row):not(.part-row):hover td {
            background: #f8fbff;
        }

        .cust-row td {
            padding-top: 7px !important;
            border-top: 1px solid #000000;
            background: #eaf2ff;
            font-weight: 700;
        }

        .part-row td {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            background: #f1f5f9;
            font-weight: 700;
        }

        .continued-label {
            font-size: 9px;
            font-weight: normal;
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
            height: 20px;
            margin-top: 10px;
            border-top: 1px solid #000000;
        }

        .no-data {
            padding: 70px 0;
            color: var(--app-muted);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 15px;
            text-align: center;
        }

        @media (max-width: 767.98px) {
            .app-container {
                width: min(100% - 16px, 1280px);
            }

            .toolbar-card {
                position: static;
            }

            .report-page {
                padding: 14px;
                border-radius: 8px;
            }

            .company,
            .dept,
            .page-info,
            .print-date {
                position: static;
                text-align: center;
            }

            .report-title {
                padding-top: 8px;
                font-size: 18px;
            }
        }

        @page {
            size: A4 landscape;
            margin: 7mm;
        }

        @media print {
            html,
            body {
                background: #ffffff;
                font-size: 9px;
            }

            .app-controls,
            .print-exclude {
                display: none !important;
            }

            .app-container {
                width: 100%;
                max-width: none;
                margin: 0;
            }

            .report-page {
                width: 100%;
                max-width: none;
                min-height: auto;
                margin: 0;
                padding: 0;
                overflow: visible;
                border: none;
                border-radius: 0;
                box-shadow: none;
                page-break-after: always;
            }

            .report-page:last-child {
                page-break-after: auto;
            }

            .report-table-wrap {
                overflow: visible;
            }

            table.report {
                min-width: 0;
            }

            table.report tbody tr:not(.cust-row):not(.part-row):hover td {
                background: transparent;
            }

            .report-title {
                font-size: 18px;
            }

            table.report th,
            table.report td {
                padding: 1px 2px;
                font-size: 8.5px;
                line-height: 11px;
            }
        }
    </style>
</head>

<body>
<nav class="navbar navbar-dark app-navbar shadow-sm app-controls">
    <div class="app-container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1">
        <div>
            <div class="navbar-brand mb-0 fw-bold">Bill of Material</div>
            <div class="small text-white-50">PPIC Report &amp; Export Center</div>
        </div>
        <span class="badge rounded-pill text-bg-light text-primary px-3 py-2">
            <?php echo h($printDate); ?>
        </span>
    </div>
</nav>

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

<main class="app-container py-3 py-md-4">
    <section class="card filter-card mb-3 app-controls">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h1 class="h5 mb-1 fw-bold">Filter BOM</h1>
                    <p class="mb-0 text-secondary small">Cari berdasarkan customer, part, atau material.</p>
                </div>
                <span class="badge text-bg-primary rounded-pill px-3 py-2">ITEM_CODE ASC</span>
            </div>

            <form method="get" autocomplete="off" class="row g-3 align-items-end">
                <div class="col-12 col-lg-4">
                    <label for="cust" class="form-label">Customer</label>
                    <input
                        id="cust"
                        type="text"
                        name="cust"
                        list="custOptions"
                        class="form-control"
                        value="<?php echo h($cust); ?>"
                        placeholder="Kode / nama customer"
                    >
                </div>

                <div class="col-12 col-lg-4">
                    <label for="part" class="form-label">Part / Material</label>
                    <input
                        id="part"
                        type="text"
                        name="part"
                        list="partOptions"
                        class="form-control"
                        value="<?php echo h($part); ?>"
                        placeholder="Part code / no / name / item"
                    >
                </div>

                <div class="col-6 col-lg-2">
                    <label for="page_size" class="form-label">Baris / Page</label>
                    <select id="page_size" name="page_size" class="form-select">
                        <?php foreach ($allowedPageSizes as $ps) { ?>
                            <option value="<?php echo $ps; ?>" <?php echo ($pageSize == $ps) ? "selected" : ""; ?>>
                                <?php echo $ps; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="col-6 col-lg-2 d-grid">
                    <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <a href="bom.php" class="btn btn-outline-secondary">Reset / Tampilkan Semua</a>
                    <?php if ($cust != "" || $part != "") { ?>
                        <span class="align-self-center small text-secondary">
                            Filter aktif:
                            <?php if ($cust != "") { ?>Customer “<?php echo h($cust); ?>”<?php } ?>
                            <?php if ($cust != "" && $part != "") { ?>, <?php } ?>
                            <?php if ($part != "") { ?>Part/Item “<?php echo h($part); ?>”<?php } ?>
                        </span>
                    <?php } ?>
                </div>
            </form>
        </div>
    </section>

    <section class="card toolbar-card mb-3 app-controls">
        <div class="card-body p-3">
            <div class="d-flex flex-column flex-xl-row align-items-stretch align-items-xl-center justify-content-between gap-3">
                <div class="d-flex flex-wrap gap-2">
                    <span class="summary-pill"><strong><?php echo h($totalPart); ?></strong> Part</span>
                    <span class="summary-pill"><strong><?php echo h($totalPages); ?></strong> Page</span>
                    <span class="summary-pill">Urutan <strong>ITEM_CODE ASC</strong></span>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <label for="pageSelect" class="visually-hidden">Pilih halaman</label>
                    <select id="pageSelect" class="form-select form-select-sm" style="width:auto; min-width:170px;" onchange="showSelectedPage()">
                        <option value="all">Semua Halaman</option>
                        <?php for ($pNo = 1; $pNo <= $totalPages; $pNo++) { ?>
                            <option value="<?php echo $pNo; ?>">Halaman <?php echo $pNo; ?></option>
                        <?php } ?>
                    </select>

                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="showSelectedPage()">Tampilkan</button>
                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="printSelectedPage()">Print</button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="exportSelectedPagePdf()">Export PDF</button>
                    <button type="button" class="btn btn-sm btn-success" onclick="exportSelectedPageExcel()">Export Excel</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="window.location.href='dashboard_ppic.php'">Close</button>
                </div>
            </div>
        </div>
    </section>

    <div id="pagesContainer">
    <?php for ($pageIndex = 0; $pageIndex < count($pages); $pageIndex++) { ?>
        <?php
            $pageNo = $pageIndex + 1;
            $pageBlocks = $pages[$pageIndex];
        ?>
        <section class="report-page bom-page" data-page-no="<?php echo $pageNo; ?>">
            <div class="report-header">
                <div class="company">P.T. IMC TEKNO INDONESIA</div>
                <div class="dept">Commercial Business</div>
                <div class="report-title">BILL OF MATERIAL</div>
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
                <div class="report-table-wrap">
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
                </div>
            <?php } ?>

            <div class="footer-line"></div>
        </section>
    <?php } ?>
    </div>
</main>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"
></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
<script>
(function () {
    "use strict";

    var excelData = <?php echo json_encode($excelPayload); ?>;
    var filterCustomer = <?php echo json_encode($cust); ?>;
    var filterPart = <?php echo json_encode($part); ?>;

    function getPages() {
        return Array.prototype.slice.call(document.querySelectorAll(".bom-page"));
    }

    function getSelectedValue() {
        var sel = document.getElementById("pageSelect");
        return sel ? sel.value : "all";
    }

    function safeFilePart(value) {
        return String(value || "")
            .trim()
            .replace(/[\\/:*?"<>|]+/g, "-")
            .replace(/\s+/g, "_")
            .substring(0, 60);
    }

    function buildFileSuffix(selected) {
        var parts = [];

        if (selected === "all") {
            parts.push("semua_halaman");
        } else {
            parts.push("halaman_" + selected);
        }

        if (filterCustomer) {
            parts.push("cust_" + safeFilePart(filterCustomer));
        }

        if (filterPart) {
            parts.push("part_" + safeFilePart(filterPart));
        }

        return parts.join("_");
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
                window.scrollTo({
                    top: Math.max(0, target.offsetTop - 90),
                    behavior: "smooth"
                });
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
            clone.style.borderRadius = "0";
            clone.style.boxShadow = "none";
            clone.style.boxSizing = "border-box";
            clone.style.pageBreakAfter = (j < selectedPages.length - 1) ? "always" : "auto";
            exportWrap.appendChild(clone);
        }

        document.body.appendChild(exportWrap);

        var fileName = "BOM_" + buildFileSuffix(selected) + ".pdf";
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

    window.exportSelectedPageExcel = function () {
        var selected = getSelectedValue();
        var rows = (selected === "all")
            ? (excelData.all || [])
            : ((excelData.pages && excelData.pages[selected]) || []);

        if (rows.length === 0) {
            alert("Tidak ada data untuk diexport ke Excel.");
            return;
        }

        if (typeof XLSX === "undefined") {
            alert("Library Excel belum berhasil dimuat. Cek koneksi internet/CDN.");
            return;
        }

        var aoa = [
            ["BILL OF MATERIAL"],
            ["Export Date", new Date().toLocaleString("id-ID")],
            ["Filter Customer", filterCustomer || "Semua"],
            ["Filter Part / Item", filterPart || "Semua"],
            [],
            [
                "PAGE",
                "CUSTOMER_CODE",
                "CUSTOMER_NAME",
                "PART_CODE",
                "PART_NO",
                "PART_NAME",
                "ITEM_CODE",
                "ITEM_NAME",
                "QTY",
                "UNIT"
            ]
        ];

        for (var i = 0; i < rows.length; i++) {
            aoa.push([
                rows[i].PAGE,
                rows[i].CUSTOMER_CODE,
                rows[i].CUSTOMER_NAME,
                rows[i].PART_CODE,
                rows[i].PART_NO,
                rows[i].PART_NAME,
                rows[i].ITEM_CODE,
                rows[i].ITEM_NAME,
                rows[i].QTY,
                rows[i].UNIT
            ]);
        }

        var worksheet = XLSX.utils.aoa_to_sheet(aoa);
        worksheet["!cols"] = [
            { wch: 8 },
            { wch: 18 },
            { wch: 30 },
            { wch: 18 },
            { wch: 18 },
            { wch: 36 },
            { wch: 18 },
            { wch: 42 },
            { wch: 14 },
            { wch: 10 }
        ];
        worksheet["!autofilter"] = {
            ref: "A6:J" + (aoa.length)
        };
        worksheet["!merges"] = [XLSX.utils.decode_range("A1:J1")];

        for (var rowNo = 7; rowNo <= aoa.length; rowNo++) {
            var qtyCell = worksheet["I" + rowNo];
            if (qtyCell && qtyCell.t === "n") {
                qtyCell.z = "0.######";
            }
        }

        var workbook = XLSX.utils.book_new();
        var sheetName = (selected === "all") ? "Semua BOM" : "Halaman " + selected;
        XLSX.utils.book_append_sheet(workbook, worksheet, sheetName.substring(0, 31));

        XLSX.writeFile(
            workbook,
            "BOM_" + buildFileSuffix(selected) + ".xlsx",
            { compression: true }
        );
    };

    window.showSelectedPage();
})();
</script>
</body>
</html>