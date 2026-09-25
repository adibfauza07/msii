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

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
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

$is_filter = get_param("RUN", "") == "1";

$defaultStart = date("Y-m-01");
$defaultEnd   = date("Y-m-d");

$start_input = date_input_value(get_param("START_DATE", ""), $defaultStart);
$end_input   = date_input_value(get_param("END_DATE", ""), $defaultEnd);
$cust_code   = get_param("CUST_CODE", "");

if ($is_filter && $cust_code == "") {
    $cust_code = "%";
}

$start_ymd = ymd_param($start_input);
$end_ymd   = ymd_param($end_input);

$rows = array();
$printRows = array();
$pages = array();

$totalPages = 0;
$rowsPerPage = 52;

if ($is_filter) {
    if ($start_ymd == "" || $end_ymd == "") {
        die("Tanggal tidak valid.");
    }

    if ($cust_code == "") {
        $cust_code = "%";
    }

    $end_dt = strtotime($end_input);
    $target_yy = (int)date("Y", $end_dt);
    $target_mm = (int)date("m", $end_dt);

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
            X.SPOQTY,
            X.SSQTY,
            X.SDELQTY
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
                ISNULL((
                    SELECT SUM(OP.ORDP_BQTY)
                    FROM dbo.ORDR_PAR AS OP
                    INNER JOIN dbo.ORDERS AS ORD
                        ON OP.ORDR_ID = ORD.ORDR_ID
                    WHERE
                        OP.ORDP_BQTY > 0
                        AND OP.ORDP_CLOSE = 0
                        AND OP.PRICE_ID = DS.PRICE_ID
                        AND (
                            YEAR(ORD.ORDR_DATE) < ?
                            OR (YEAR(ORD.ORDR_DATE) = ? AND MONTH(ORD.ORDR_DATE) <= ?)
                        )
                ), 0) AS SPOQTY,
                ISNULL(SUM(DS.DELS_QTY), 0) AS SSQTY,
                ISNULL((
                    SELECT SUM(DP.DIPA_QTY)
                    FROM dbo.DI_PART AS DP
                    INNER JOIN dbo.DI AS DIH
                        ON DP.DI_ID = DIH.DI_ID
                    WHERE
                        DP.PRICE_ID = DS.PRICE_ID
                        AND DIH.DI_DATE BETWEEN ? AND ?
                ), 0) AS SDELQTY
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
        ORDER BY
            X.CUST_CODE,
            X.PART_NUM
    ";

    $params = array(
        $target_yy,
        $target_yy,
        $target_mm,
        $start_ymd,
        $end_ymd,
        $start_ymd,
        $end_ymd,
        $cust_code
    );

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die("<pre>Query Delivery Balance gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $poQty    = isset($r["SPOQTY"]) ? (float)$r["SPOQTY"] : 0;
        $schedule = isset($r["SSQTY"]) ? (float)$r["SSQTY"] : 0;
        $delivery = isset($r["SDELQTY"]) ? (float)$r["SDELQTY"] : 0;
        $balance  = $delivery - $schedule;

        $rows[] = array(
            "CUST_CODE" => safe_trim($r["CUST_CODE"]),
            "CUST_COMP" => safe_trim($r["CUST_COMP"]),
            "PART_NUM"  => safe_trim($r["PART_NUM"]),
            "PART_NO"   => safe_trim($r["PART_NO"]),
            "PART_NAME" => safe_trim($r["PART_NAME"]),
            "PO_QTY"    => $poQty,
            "SCH_QTY"   => $schedule,
            "DEL_QTY"   => $delivery,
            "BAL_QTY"   => $balance
        );
    }

    $lastCust = "";
    $subPoQty = 0;
    $subSchQty = 0;
    $subDelQty = 0;
    $subBalQty = 0;

    $grandPoQty = 0;
    $grandSchQty = 0;
    $grandDelQty = 0;
    $grandBalQty = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];
        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

        if ($custKey != $lastCust) {
            if ($lastCust != "") {
                $printRows[] = array(
                    "ROW_TYPE" => "CUSTOMER_TOTAL",
                    "PO_QTY"   => $subPoQty,
                    "SCH_QTY"  => $subSchQty,
                    "DEL_QTY"  => $subDelQty,
                    "BAL_QTY"  => $subBalQty
                );
            }

            $printRows[] = array(
                "ROW_TYPE"  => "CUSTOMER",
                "CUST_CODE" => $r["CUST_CODE"],
                "CUST_COMP" => $r["CUST_COMP"]
            );

            $lastCust = $custKey;
            $subPoQty = 0;
            $subSchQty = 0;
            $subDelQty = 0;
            $subBalQty = 0;
        }

        $printRows[] = array(
            "ROW_TYPE"  => "DETAIL",
            "PART_NUM"  => $r["PART_NUM"],
            "PART_NO"   => $r["PART_NO"],
            "PART_NAME" => $r["PART_NAME"],
            "PO_QTY"    => $r["PO_QTY"],
            "SCH_QTY"   => $r["SCH_QTY"],
            "DEL_QTY"   => $r["DEL_QTY"],
            "BAL_QTY"   => $r["BAL_QTY"]
        );

        $subPoQty  += $r["PO_QTY"];
        $subSchQty += $r["SCH_QTY"];
        $subDelQty += $r["DEL_QTY"];
        $subBalQty += $r["BAL_QTY"];

        $grandPoQty  += $r["PO_QTY"];
        $grandSchQty += $r["SCH_QTY"];
        $grandDelQty += $r["DEL_QTY"];
        $grandBalQty += $r["BAL_QTY"];
    }

    if ($lastCust != "") {
        $printRows[] = array(
            "ROW_TYPE" => "CUSTOMER_TOTAL",
            "PO_QTY"   => $subPoQty,
            "SCH_QTY"  => $subSchQty,
            "DEL_QTY"  => $subDelQty,
            "BAL_QTY"  => $subBalQty
        );
    }

    if (count($rows) > 0) {
        $printRows[] = array(
            "ROW_TYPE" => "GRAND_TOTAL",
            "PO_QTY"   => $grandPoQty,
            "SCH_QTY"  => $grandSchQty,
            "DEL_QTY"  => $grandDelQty,
            "BAL_QTY"  => $grandBalQty
        );
    }

    if (count($printRows) == 0) {
        $printRows[] = array(
            "ROW_TYPE" => "EMPTY",
            "MESSAGE"  => "Data delivery balance tidak ditemukan."
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
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delivery Balance Report</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        /* ══════════ PRINT: A3 Portrait ══════════ */
        @page {
            size: A3 portrait;
            margin: 8mm;
        }

        /* ══════════ BODY ══════════ */
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background-color: #1a1d23;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        /* ══════════ FILTER BAR ══════════ */
        .filter-card {
            max-width: 1100px;
            margin: 10px auto;
        }

        .filter-card .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 2px;
            color: #ced4da;
        }

        .filter-card .form-control {
            font-size: 0.95rem;
            height: 40px;
            background-color: #2b3035;
            border-color: #495057;
            color: #f8f9fa;
        }

        .filter-card .form-control:focus {
            background-color: #343a40;
            border-color: #0d6efd;
            color: #ffffff;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .filter-card .form-control::placeholder {
            color: #6c757d;
        }

        .filter-card input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }

        .filter-card .btn {
            height: 40px;
            font-size: 0.9rem;
            padding: 0 18px;
            font-weight: 600;
        }

        /* ══════════ AUTOCOMPLETE ══════════ */
        .autocomplete-wrap {
            position: relative;
        }

        .autocomplete-list {
            position: absolute;
            top: 100%;
            left: 0;
            width: 500px;
            max-height: 300px;
            overflow-y: auto;
            background: #2b3035;
            border: 1px solid #495057;
            border-radius: 0.5rem;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.5);
            z-index: 9999;
            display: none;
        }

        .autocomplete-item {
            padding: 10px 14px;
            border-bottom: 1px solid #3a3f44;
            cursor: pointer;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            font-size: 0.95rem;
            color: #dee2e6;
            transition: background 0.1s;
        }

        .autocomplete-item:last-child {
            border-bottom: none;
        }

        .autocomplete-item:hover,
        .autocomplete-item.active {
            background-color: #0d6efd;
            color: #ffffff;
        }

        .autocomplete-item.active b {
            color: #ffffff;
        }

        .autocomplete-item b {
            color: #74c0fc;
        }

        /* ══════════ ACTION BAR ══════════ */
        .action-bar {
            max-width: 1100px;
            margin: 0 auto 8px auto;
        }

        .action-bar .btn {
            height: 38px;
            font-size: 0.9rem;
            padding: 0 20px;
            font-weight: 600;
        }

        /* ══════════ REPORT PAGE — A3 Portrait ══════════ */
        .report-page {
            width: 297mm;
            min-height: 420mm;
            margin: 8px auto;
            background: #ffffff;
            padding: 8mm 10mm;
            box-sizing: border-box;
            page-break-after: always;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.4);
            border-radius: 2px;
        }

        .report-page:last-child {
            page-break-after: auto;
        }

        /* ══════════ HEADER ══════════ */
        .report-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .report-header td {
            border: none;
            vertical-align: top;
            padding: 0;
        }

        .company-block {
            width: 30%;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            line-height: 20px;
            color: #212529;
        }

        .company-name {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .title-block {
            width: 40%;
            text-align: center;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .report-title {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 1px;
            margin-top: 4px;
            color: #212529;
        }

        .date-range-text {
            font-size: 14px;
            margin-top: 8px;
            color: #495057;
            font-weight: 500;
        }

        .page-info-block {
            width: 30%;
            text-align: right;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            line-height: 22px;
            color: #495057;
            font-weight: 500;
        }

        .print-datetime {
            text-align: right;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 10px;
            font-weight: 500;
        }

        /* ══════════ DATA TABLE ══════════ */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 12px;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #d0d5dd;
            padding: 4px 6px;
            height: 22px;
            line-height: 16px;
            box-sizing: border-box;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .data-table thead th {
            border: 2px solid #000000;
            font-weight: 700;
            text-align: center;
            font-size: 12px;
            background-color: #e9ecef;
            color: #212529;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        /* Customer grouping row */
        .data-table .customer-row td {
            font-weight: 700;
            font-size: 15px;
            padding: 8px 8px;
            color: #0b5ed7;
            background-color: #e7f1ff;
            border-bottom: 2px solid #0b5ed7;
            letter-spacing: 0.2px;
        }

        /* Subtotal row */
        .data-table .total-row td {
            font-weight: 700;
            font-size: 12px;
            border-top: 2px solid #000;
            background-color: #f1f3f5;
            color: #343a40;
        }

        /* Grand total row */
        .data-table .grand-row td {
            font-weight: 800;
            font-size: 13px;
            border-top: 3px solid #000;
            border-bottom: 3px solid #000;
            background-color: #dee2e6;
            color: #000;
            letter-spacing: 0.3px;
        }

        .data-table .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .data-table .center {
            text-align: center;
        }

        /* Highlight minus red */
        .minus-red {
            color: #dc3545 !important;
            font-weight: 700 !important;
        }

        .data-table tbody tr:not(.customer-row):not(.total-row):not(.grand-row):hover {
            background-color: #f8f9fa;
        }

        /* Column widths */
        .col-code      { width: 10%; }
        .col-no        { width: 16%; }
        .col-name      { width: 34%; }
        .col-po        { width: 10%; }
        .col-sch       { width: 10%; }
        .col-del       { width: 10%; }
        .col-bal       { width: 10%; }

        /* ══════════ EMPTY STATE ══════════ */
        .empty-state {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            text-align: center;
            padding: 120px 40px;
        }

        .empty-state .icon {
            font-size: 5rem;
            color: #adb5bd;
            margin-bottom: 24px;
        }

        .empty-state h5 {
            color: #6c757d;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .empty-state p {
            color: #868e96;
            font-size: 1.1rem;
            line-height: 1.8;
        }

        /* ══════════ PRINT STYLES ══════════ */
        @media print {
            html, body {
                width: 297mm;
                min-height: 420mm;
                background: #ffffff !important;
            }

            .filter-card,
            .action-bar {
                display: none !important;
            }

            .report-page {
                width: 297mm;
                min-height: 420mm;
                margin: 0;
                padding: 6mm 8mm;
                box-shadow: none;
                border-radius: 0;
                overflow: hidden;
            }

            .minus-red {
                color: #dc3545 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

<!-- ═══════════════ FILTER BAR ═══════════════ -->
<div class="filter-card">
    <div class="card border-secondary">
        <div class="card-body py-3">
            <form method="get" action="<?php echo h($selfFile); ?>" autocomplete="off">
                <input type="hidden" name="RUN" value="1">

                <div class="row g-2 align-items-end">
                    <!-- Start Date -->
                    <div class="col-auto">
                        <label for="START_DATE" class="form-label">Start Date</label>
                        <input type="date"
                               id="START_DATE"
                               name="START_DATE"
                               class="form-control"
                               style="width:175px"
                               value="<?php echo h($start_input); ?>">
                    </div>

                    <!-- End Date -->
                    <div class="col-auto">
                        <label for="END_DATE" class="form-label">End Date</label>
                        <input type="date"
                               id="END_DATE"
                               name="END_DATE"
                               class="form-control"
                               style="width:175px"
                               value="<?php echo h($end_input); ?>">
                    </div>

                    <!-- Customer -->
                    <div class="col-auto">
                        <label for="CUST_CODE" class="form-label">Customer</label>
                        <div class="autocomplete-wrap">
                            <input type="text"
                                   id="CUST_CODE"
                                   name="CUST_CODE"
                                   class="form-control"
                                   style="width:240px"
                                   value="<?php echo h($cust_code); ?>"
                                   placeholder="Ketik customer / %">
                            <div id="custSuggest" class="autocomplete-list"></div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="col-auto">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel-fill me-1"></i>Filter
                            </button>
                            <button type="button" class="btn btn-outline-light" onclick="setAllCustomer()">
                                <i class="bi bi-arrow-up-circle me-1"></i>All
                            </button>
                            <button type="button" class="btn btn-success" onclick="exportExcel()">
                                <i class="bi bi-file-earmark-excel me-1"></i>Export
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════ ACTION BAR ═══════════════ -->
<div class="action-bar d-flex justify-content-end gap-2 px-1">
    <button type="button" class="btn btn-outline-light" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print
    </button>
    <button type="button" class="btn btn-outline-danger" onclick="closeReport()">
        <i class="bi bi-x-lg me-1"></i>Close
    </button>
</div>

<!-- ═══════════════ EMPTY STATE ═══════════════ -->
<?php if (!$is_filter) { ?>
    <div class="report-page">
        <table class="report-header">
            <tr>
                <td class="company-block">
                    <div class="company-name">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Departement
                </td>
                <td class="title-block">
                    <div class="report-title">DELIVERY BALANCE</div>
                    <div class="date-range-text">Date range:<br>~</div>
                </td>
                <td class="page-info-block">Page 0 of 0</td>
            </tr>
        </table>

        <div class="empty-state">
            <div class="icon"><i class="bi bi-inbox"></i></div>
            <h5>Data belum ditampilkan</h5>
            <p>
                Isi <strong>Start Date</strong>, <strong>End Date</strong>, dan <strong>Customer</strong> lalu klik <strong>Filter</strong>.<br>
                Klik <strong>All</strong> untuk menampilkan semua customer.
            </p>
        </div>
    </div>
<?php } ?>

<!-- ═══════════════ REPORT PAGES ═══════════════ -->
<?php for ($p = 0; $p < count($pages); $p++) { ?>
    <?php
        $pageRows = $pages[$p];
        $pageNo = $p + 1;
    ?>
    <div class="report-page">
        <!-- Header -->
        <table class="report-header">
            <tr>
                <td class="company-block">
                    <div class="company-name">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Departement
                </td>
                <td class="title-block">
                    <div class="report-title">DELIVERY BALANCE</div>
                    <div class="date-range-text">
                        Date range:<br>
                        <?php echo h($start_ymd); ?> &nbsp;~&nbsp; <?php echo h($end_ymd); ?>
                    </div>
                </td>
                <td class="page-info-block">
                    Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?>
                </td>
            </tr>
        </table>

        <div class="print-datetime">
            Print Date: <?php echo h(fmt_print_datetime()); ?>
        </div>

        <!-- Table -->
        <table class="data-table">
            <thead>
                <tr>
                    <th rowspan="2" class="col-code">Code</th>
                    <th rowspan="2" class="col-no">Part No</th>
                    <th rowspan="2" class="col-name">Part Name</th>
                    <th class="col-po">PO BAL</th>
                    <th class="col-sch">Schedule</th>
                    <th class="col-del">Delivery</th>
                    <th class="col-bal">Balance</th>
                </tr>
                <tr>
                    <th class="col-po">Qty</th>
                    <th class="col-sch">Qty</th>
                    <th class="col-del">Qty</th>
                    <th class="col-bal">Qty</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < count($pageRows); $i++) { ?>
                    <?php $r = $pageRows[$i]; ?>

                    <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
                        <tr class="customer-row">
                            <td colspan="7">
                                <i class="bi bi-building me-2" style="font-size:14px"></i>
                                <?php echo h($r["CUST_CODE"]); ?>
                                &mdash;
                                <?php echo h($r["CUST_COMP"]); ?>
                            </td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
                        <tr>
                            <td class="col-code center"><?php echo h($r["PART_NUM"]); ?></td>
                            <td class="col-no"><?php echo h($r["PART_NO"]); ?></td>
                            <td class="col-name" title="<?php echo h($r["PART_NAME"]); ?>"><?php echo h($r["PART_NAME"]); ?></td>
                            <td class="col-po num"><?php echo h(fmt_zero_dash($r["PO_QTY"], 0)); ?></td>
                            <td class="col-sch num"><?php echo h(fmt_zero_dash($r["SCH_QTY"], 0)); ?></td>
                            <td class="col-del num"><?php echo h(fmt_zero_dash($r["DEL_QTY"], 0)); ?></td>
                            <td class="col-bal num <?php echo ($r["BAL_QTY"] < 0) ? 'minus-red' : ''; ?>">
                                <?php echo h(fmt_zero_dash($r["BAL_QTY"], 0)); ?>
                            </td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL") { ?>
                        <tr class="total-row">
                            <td colspan="3" class="num">SUBTOTAL:</td>
                            <td class="num"><?php echo h(fmt_zero_dash($r["PO_QTY"], 0)); ?></td>
                            <td class="num"><?php echo h(fmt_zero_dash($r["SCH_QTY"], 0)); ?></td>
                            <td class="num"><?php echo h(fmt_zero_dash($r["DEL_QTY"], 0)); ?></td>
                            <td class="num <?php echo ($r["BAL_QTY"] < 0) ? 'minus-red' : ''; ?>">
                                <?php echo h(fmt_zero_dash($r["BAL_QTY"], 0)); ?>
                            </td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
                        <tr class="grand-row">
                            <td colspan="3" class="num">GRAND TOTAL:</td>
                            <td class="num"><?php echo h(fmt_zero_dash($r["PO_QTY"], 0)); ?></td>
                            <td class="num"><?php echo h(fmt_zero_dash($r["SCH_QTY"], 0)); ?></td>
                            <td class="num"><?php echo h(fmt_zero_dash($r["DEL_QTY"], 0)); ?></td>
                            <td class="num <?php echo ($r["BAL_QTY"] < 0) ? 'minus-red' : ''; ?>">
                                <?php echo h(fmt_zero_dash($r["BAL_QTY"], 0)); ?>
                            </td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr>
                            <td colspan="7" class="center" style="padding:40px 0; font-size:14px;">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?php echo h($r["MESSAGE"]); ?>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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

function closeReport() {
    try {
        if (window.parent && window.parent !== window) {
            window.location.href = "dashboard_home.php";
            return;
        }
    } catch (e) {}

    window.open("", "_self");
    window.close();

    setTimeout(function () {
        if (!window.closed) {
            window.location.href = "dashboard_home.php";
        }
    }, 200);
}

function exportExcel() {
    var startDate = document.getElementById("START_DATE").value;
    var endDate = document.getElementById("END_DATE").value;
    var custCode = document.getElementById("CUST_CODE").value;

    if (startDate === "") {
        alert("Start Date belum diisi.");
        document.getElementById("START_DATE").focus();
        return;
    }
    if (endDate === "") {
        alert("End Date belum diisi.");
        document.getElementById("END_DATE").focus();
        return;
    }
    if (custCode === "") {
        alert("Customer belum diisi. Isi kode customer atau % untuk semua.");
        document.getElementById("CUST_CODE").focus();
        return;
    }

    window.location =
        "delivery_balance_export_excel.php" +
        "?START_DATE=" + enc(startDate) +
        "&END_DATE=" + enc(endDate) +
        "&CUST_CODE=" + enc(custCode);
}

function setAllCustomer() {
    document.getElementById("CUST_CODE").value = "%";
    document.forms[0].submit();
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

    if (!items || items.length === 0) {
        custIndex = -1;
        return;
    }
    if (index < 0) index = items.length - 1;
    if (index >= items.length) index = 0;

    for (var i = 0; i < items.length; i++) {
        items[i].className = "autocomplete-item";
    }
    items[index].className = "autocomplete-item active";
    custIndex = index;
}

function chooseCust(index) {
    if (index < 0 || index >= custRows.length) return;
    document.getElementById("CUST_CODE").value = custRows[index].CUST_CODE;
    hideSuggest();
}

function renderSuggest(rows) {
    var box = document.getElementById("custSuggest");
    box.innerHTML = "";
    custRows = rows || [];
    custIndex = -1;

    if (!rows || rows.length === 0) {
        box.style.display = "none";
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        (function (r, idx) {
            var div = document.createElement("div");
            div.className = "autocomplete-item";
            div.innerHTML =
                "<b>" + htmlEncode(r.CUST_CODE) + "</b> &mdash; " +
                htmlEncode(r.CUST_COMP);

            div.onmouseover = function () { setActiveCust(idx); };
            div.onmousedown = function (e) {
                if (e && e.preventDefault) e.preventDefault();
                chooseCust(idx);
            };
            box.appendChild(div);
        })(rows[i], i);
    }

    box.style.display = "block";
    setActiveCust(0);
}

function searchCustomer(q) {
    if (q === "" || q === "%") {
        hideSuggest();
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "ajax_customer_autocomplete.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var result;
            try {
                result = JSON.parse(xhr.responseText);
            } catch (e) {
                return;
            }
            renderSuggest(result.rows || result);
        }
    };

    xhr.send("q=" + enc(q));
}

document.getElementById("CUST_CODE").onkeyup = function (e) {
    e = e || window.event;
    var key = e.keyCode || e.which;

    if (key === 40) { setActiveCust(custIndex + 1); return; }
    if (key === 38) { setActiveCust(custIndex - 1); return; }
    if (key === 13) {
        if (custRows.length > 0) {
            if (custIndex < 0) custIndex = 0;
            chooseCust(custIndex);
            return false;
        }
        return true;
    }

    clearTimeout(timer);
    var q = this.value;
    timer = setTimeout(function () { searchCustomer(q); }, 250);
};

document.getElementById("CUST_CODE").onblur = function () {
    setTimeout(function () { hideSuggest(); }, 250);
};
</script>

</body>
</html>