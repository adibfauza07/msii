<?php
// ============================================
// delivery_balance_amount_idr.php
// Report: Delivery Balance Amount (IDR)
// Support pilihan server P1 atau P2
// ============================================
set_time_limit(30);
session_start();

if (!isset($_SESSION['db_user']) || $_SESSION['db_user'] == "") {
    die('<div style="padding:24px;color:#F85149;background:#0D1117;font-family:monospace;">Silakan login terlebih dahulu.</div>');
}

 $uid    = $_SESSION['db_user'];
 $pwd    = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : "";
 $dbName = "msData";

 $servers_config = [
    'p1' => ['ip' => '192.168.0.4', 'label' => 'Plant 1', 'short' => 'P1'],
    'p2' => ['ip' => '192.168.0.9', 'label' => 'Plant 2', 'short' => 'P2'],
];

 $selected_plant = isset($_GET['plant']) ? strtolower(trim($_GET['plant'])) : 'p1';
if (!in_array($selected_plant, ['p1', 'p2'])) {
    $selected_plant = 'p1';
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
    return number_format((float)$value, 5, ".", ",");
}

function fmt_amount($value) {
    if ($value === null || $value === "") {
        $value = 0;
    }
    return number_format((float)$value, 2, ".", ",");
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

function idr_factor($currCode, $currRate, $usdRate) {
    $currCode = strtoupper(trim((string)$currCode));
    if ($currCode == "IDR" || $currCode == "RP") {
        return 1;
    }
    $currRate = (float)$currRate;
    $usdRate  = (float)$usdRate;
    if ($currRate == 0) {
        $currRate = 1;
    }
    if ($usdRate == 0) {
        $usdRate = 1;
    }
    if ($currCode == "USD") {
        return $usdRate;
    }
    return $currRate;
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

 $rows           = array();
 $printRows      = array();
 $pages          = array();
 $totalPages     = 0;
 $rowsPerPage    = 34; // A3 landscape: tinggi halaman lebih pendek
 $connected_servers = array();

if ($is_filter) {
    if ($start_ymd == "" || $end_ymd == "") {
        die("Tanggal tidak valid.");
    }

    if ($cust_code == "") {
        $cust_code = "%";
    }

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
            X.SSQTY,
            X.SDELQTY,
            X.SPOQTY,
            ISNULL(APV.PRDT_PRICE, 0) AS PRDT_PRICE,
            ISNULL(APV.CURR_CODE, '') AS CURR_CODE,
            ISNULL(RV.CURR_VRATE, 1) AS CURR_VRATE,
            ISNULL((SELECT TOP 1 CURR_CRATE FROM dbo.TODAY_USDRATE_VIEW), 1) AS USDRATE
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
                ISNULL(SUM(DS.DELS_QTY), 0) AS SSQTY,
                ISNULL((
                    SELECT SUM(DP.DIPA_QTY)
                    FROM dbo.DI_PART AS DP
                    INNER JOIN dbo.DI AS DIH
                        ON DP.DI_ID = DIH.DI_ID
                    WHERE
                        DP.PRICE_ID = DS.PRICE_ID
                        AND DIH.DI_DATE BETWEEN ? AND ?
                ), 0) AS SDELQTY,
                ISNULL((
                    SELECT SUM(OP.ORDP_BQTY)
                    FROM dbo.ORDR_PAR AS OP
                    WHERE
                        OP.ORDP_BQTY > 0
                        AND OP.ORDP_CLOSE = 0
                        AND OP.PRICE_ID = DS.PRICE_ID
                ), 0) AS SPOQTY
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
        LEFT JOIN dbo.ACTIVE_PRICE_VIEW AS APV
            ON X.PRICE_ID = APV.PRICE_ID
        LEFT JOIN dbo.TODAY_RATE_VIEW AS RV
            ON APV.CURR_CODE = RV.CURR_CODE
        ORDER BY
            X.CUST_CODE,
            X.PART_NUM
    ";

    $params = array(
        $start_ymd,
        $end_ymd,
        $start_ymd,
        $end_ymd,
        $cust_code
    );

    $conn_opts = [
        "Database" => $dbName, "Uid" => $uid, "PWD" => $pwd,
        "CharacterSet" => "UTF-8", "LoginTimeout" => 5, "Encrypt" => false,
    ];

    $servers_to_try = [$selected_plant];
    $all_raw_rows   = [];

    foreach ($servers_to_try as $key) {
        $ip    = $servers_config[$key]['ip'];
        $label = $servers_config[$key]['label'];
        $conn  = @sqlsrv_connect($ip, $conn_opts);

        if (!$conn) {
            continue;
        }

        $connected_servers[] = $label;
        $stmt = @sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $all_raw_rows[] = $r;
            }
            sqlsrv_free_stmt($stmt);
        }
        sqlsrv_close($conn);
    }

    $aggregated = [];
    foreach ($all_raw_rows as $r) {
        $pid = $r['PRICE_ID'];
        if (!isset($aggregated[$pid])) {
            $aggregated[$pid] = [
                'CUST_ID'    => $r['CUST_ID'],
                'CUST_CODE'  => $r['CUST_CODE'],
                'CUST_COMP'  => $r['CUST_COMP'],
                'PRICE_ID'   => $r['PRICE_ID'],
                'PART_NUM'   => $r['PART_NUM'],
                'PART_NO'    => $r['PART_NO'],
                'PART_NAME'  => $r['PART_NAME'],
                'SSQTY'      => (float)$r['SSQTY'],
                'SDELQTY'    => (float)$r['SDELQTY'],
                'SPOQTY'     => (float)$r['SPOQTY'],
                'PRDT_PRICE' => (float)$r['PRDT_PRICE'],
                'CURR_CODE'  => safe_trim($r['CURR_CODE']),
                'CURR_VRATE' => (float)$r['CURR_VRATE'],
                'USDRATE'    => (float)$r['USDRATE'],
            ];
        } else {
            $aggregated[$pid]['SSQTY']   += (float)$r['SSQTY'];
            $aggregated[$pid]['SDELQTY'] += (float)$r['SDELQTY'];
            $aggregated[$pid]['SPOQTY']  += (float)$r['SPOQTY'];
        }
    }

    foreach ($aggregated as $r) {
        $schedule  = $r['SSQTY'];
        $delivered = $r['SDELQTY'];
        $balance   = $delivered - $schedule;

        $price    = $r['PRDT_PRICE'];
        $currCode = $r['CURR_CODE'];
        $factor   = idr_factor($currCode, $r['CURR_VRATE'], $r['USDRATE']);

        $rows[] = array(
            "CUST_CODE"   => safe_trim($r['CUST_CODE']),
            "CUST_COMP"   => safe_trim($r['CUST_COMP']),
            "PART_NUM"    => safe_trim($r['PART_NUM']),
            "PART_NO"     => safe_trim($r['PART_NO']),
            "PART_NAME"   => safe_trim($r['PART_NAME']),
            "PRICE"       => $price,
            "CURR_CODE"   => $currCode,
            "SCH_QTY"     => $schedule,
            "SCH_AMOUNT"  => $schedule * $price * $factor,
            "DEL_QTY"     => $delivered,
            "DEL_AMOUNT"  => $delivered * $price * $factor,
            "BAL_QTY"     => $balance,
            "BAL_AMOUNT"  => $balance * $price * $factor
        );
    }

    usort($rows, function($a, $b) {
        $cmp = strcmp($a['CUST_CODE'], $b['CUST_CODE']);
        if ($cmp !== 0) return $cmp;
        return strcmp($a['PART_NUM'], $b['PART_NUM']);
    });

    $lastCust       = "";
    $subSchAmount   = 0;
    $subDelAmount   = 0;
    $subBalAmount   = 0;
    $grandSchAmount = 0;
    $grandDelAmount = 0;
    $grandBalAmount = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];
        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

        if ($custKey != $lastCust) {
            if ($lastCust != "") {
                $printRows[] = array(
                    "ROW_TYPE"   => "CUSTOMER_TOTAL",
                    "SCH_AMOUNT" => $subSchAmount,
                    "DEL_AMOUNT" => $subDelAmount,
                    "BAL_AMOUNT" => $subBalAmount
                );
            }

            $printRows[] = array(
                "ROW_TYPE"  => "CUSTOMER",
                "CUST_CODE" => $r["CUST_CODE"],
                "CUST_COMP" => $r["CUST_COMP"]
            );

            $lastCust     = $custKey;
            $subSchAmount = 0;
            $subDelAmount = 0;
            $subBalAmount = 0;
        }

        $printRows[] = array(
            "ROW_TYPE"   => "DETAIL",
            "PART_NUM"   => $r["PART_NUM"],
            "PART_NO"    => $r["PART_NO"],
            "PART_NAME"  => $r["PART_NAME"],
            "PRICE"      => $r["PRICE"],
            "CURR_CODE"  => $r["CURR_CODE"],
            "SCH_QTY"    => $r["SCH_QTY"],
            "SCH_AMOUNT" => $r["SCH_AMOUNT"],
            "DEL_QTY"    => $r["DEL_QTY"],
            "DEL_AMOUNT" => $r["DEL_AMOUNT"],
            "BAL_QTY"    => $r["BAL_QTY"],
            "BAL_AMOUNT" => $r["BAL_AMOUNT"]
        );

        $subSchAmount   += $r["SCH_AMOUNT"];
        $subDelAmount   += $r["DEL_AMOUNT"];
        $subBalAmount   += $r["BAL_AMOUNT"];
        $grandSchAmount += $r["SCH_AMOUNT"];
        $grandDelAmount += $r["DEL_AMOUNT"];
        $grandBalAmount += $r["BAL_AMOUNT"];
    }

    if ($lastCust != "") {
        $printRows[] = array(
            "ROW_TYPE"   => "CUSTOMER_TOTAL",
            "SCH_AMOUNT" => $subSchAmount,
            "DEL_AMOUNT" => $subDelAmount,
            "BAL_AMOUNT" => $subBalAmount
        );
    }

    if (count($rows) > 0) {
        $printRows[] = array(
            "ROW_TYPE"   => "GRAND_TOTAL",
            "SCH_AMOUNT" => $grandSchAmount,
            "DEL_AMOUNT" => $grandDelAmount,
            "BAL_AMOUNT" => $grandBalAmount
        );
    }

    if (count($printRows) == 0) {
        $printRows[] = array(
            "ROW_TYPE" => "EMPTY",
            "MESSAGE"  => "Data delivery balance amount tidak ditemukan."
        );
    }

    $pages = array_chunk($printRows, $rowsPerPage);
    $totalPages = count($pages);

    if ($totalPages <= 0) {
        $totalPages = 1;
    }
}

$plant_label = $servers_config[$selected_plant]['label'];

 $selfFile = basename($_SERVER["PHP_SELF"]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delivery Balance Amount (IDR)</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        @page {
            size: A3 landscape;
            margin: 8mm;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background-color: #1a1d23;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        .filter-card {
            max-width: 1200px;
            margin: 10px auto;
        }

        .filter-card .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 2px;
            color: #ced4da;
        }

        .filter-card .form-control,
        .filter-card .form-select {
            font-size: 0.95rem;
            height: 40px;
            background-color: #2b3035;
            border-color: #495057;
            color: #f8f9fa;
        }

        .filter-card .form-control:focus,
        .filter-card .form-select:focus {
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

        .action-bar {
            max-width: 1200px;
            margin: 0 auto 8px auto;
        }

        .action-bar .btn {
            height: 38px;
            font-size: 0.9rem;
            padding: 0 20px;
            font-weight: 600;
        }

        .report-page {
            width: 420mm;
            min-height: 297mm;
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

        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11px;
        }

        .data-table th,
        .data-table td {
            border: none;
            padding: 3px 4px;
            height: 22px;
            line-height: 16px;
            box-sizing: border-box;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Kolom angka tidak dipotong menjadi ... */
        .data-table td.num {
            overflow: visible;
            text-overflow: clip;
            font-size: 10.5px;
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

        .data-table .customer-row td {
            font-weight: 700;
            font-size: 15px;
            padding: 8px 5px;
            color: #0b5ed7;
            background-color: #e7f1ff;
            border-bottom: 2px solid #0b5ed7;
            letter-spacing: 0.2px;
        }

        .data-table .total-row td {
            font-weight: 700;
            font-size: 12px;
            border-top: 1px solid #000;
            background-color: #f1f3f5;
            color: #343a40;
        }

        .data-table .grand-row td {
            font-weight: 800;
            font-size: 14px;
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

        .data-table tbody tr:not(.customer-row):not(.total-row):not(.grand-row):hover {
            background-color: #f8f9fa;
        }

        /* Total = 100%; amount diperlebar agar nilai total tampil penuh */
        .col-code   { width: 7%; }
        .col-no     { width: 12%; }
        .col-name   { width: 20%; }
        .col-price  { width: 9%; }
        .col-qty    { width: 6%; }
        .col-amount { width: 11.3333%; }

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

        .server-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 12px;
            background-color: #e7f1ff;
            color: #0b5ed7;
            margin-top: 6px;
        }

        @media print {
            html, body {
                width: 420mm;
                min-height: 297mm;
                background: #ffffff !important;
            }

            .filter-card,
            .action-bar {
                display: none !important;
            }

            .report-page {
                width: 420mm;
                min-height: 297mm;
                margin: 0;
                padding: 6mm 8mm;
                box-shadow: none;
                border-radius: 0;
                overflow: hidden;
            }
        }
    </style>
</head>

<body>

<div class="filter-card">
    <div class="card border-secondary">
        <div class="card-body py-3">
            <form method="get" action="<?php echo h($selfFile); ?>" autocomplete="off">
                <input type="hidden" name="RUN" value="1">

                <div class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label for="PLANT" class="form-label">Server</label>
                        <select id="PLANT" name="plant" class="form-select" style="width:200px">
                            <option value="p1" <?php echo $selected_plant == 'p1' ? 'selected' : ''; ?>>Plant 1 (192.168.0.4)</option>
                            <option value="p2" <?php echo $selected_plant == 'p2' ? 'selected' : ''; ?>>Plant 2 (192.168.0.9)</option>
                        </select>
                    </div>

                    <div class="col-auto">
                        <label for="START_DATE" class="form-label">Start Date</label>
                        <input type="date"
                               id="START_DATE"
                               name="START_DATE"
                               class="form-control"
                               style="width:175px"
                               value="<?php echo h($start_input); ?>">
                    </div>

                    <div class="col-auto">
                        <label for="END_DATE" class="form-label">End Date</label>
                        <input type="date"
                               id="END_DATE"
                               name="END_DATE"
                               class="form-control"
                               style="width:175px"
                               value="<?php echo h($end_input); ?>">
                    </div>

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

<div class="action-bar d-flex justify-content-end gap-2 px-1">
    <button type="button" class="btn btn-outline-light" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print
    </button>
    <button type="button" class="btn btn-outline-danger" onclick="closeReport()">
        <i class="bi bi-x-lg me-1"></i>Close
    </button>
</div>

<?php if (!$is_filter) { ?>
    <div class="report-page">
        <table class="report-header">
            <tr>
                <td class="company-block">
                    <div class="company-name">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Departement
                </td>
                <td class="title-block">
                    <div class="report-title">DELIVERY BALANCE AMOUNT (IDR)</div>
                    <div class="date-range-text">Date range:<br>~</div>
                </td>
                <td class="page-info-block">Page 0 of 0</td>
            </tr>
        </table>

        <div class="empty-state">
            <div class="icon"><i class="bi bi-inbox"></i></div>
            <h5>Data belum ditampilkan</h5>
            <p>
                Pilih <strong>Server</strong>, isi <strong>Start Date</strong>, <strong>End Date</strong>, dan <strong>Customer</strong> lalu klik <strong>Filter</strong>.<br>
                Klik <strong>All</strong> untuk menampilkan semua customer.
            </p>
        </div>
    </div>
<?php } ?>

<?php for ($p = 0; $p < count($pages); $p++) { ?>
    <?php
        $pageRows = $pages[$p];
        $pageNo = $p + 1;
    ?>
    <div class="report-page">
        <table class="report-header">
            <tr>
                <td class="company-block">
                    <div class="company-name">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Departement
                </td>
                <td class="title-block">
                    <div class="report-title">DELIVERY BALANCE AMOUNT (IDR)</div>
                    <div class="date-range-text">
                        Date range:<br>
                        <?php echo h($start_ymd); ?> &nbsp;~&nbsp; <?php echo h($end_ymd); ?>
                        <br>
                        <span class="server-badge">
                            <?php echo h($plant_label); ?>
                        </span>
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

        <table class="data-table">
            <thead>
                <tr>
                    <th rowspan="2" class="col-code">Code</th>
                    <th rowspan="2" class="col-no">Part No</th>
                    <th rowspan="2" class="col-name">Part Name</th>
                    <th rowspan="2" class="col-price">Price</th>
                    <th colspan="2">Schedule</th>
                    <th colspan="2">Delivery</th>
                    <th colspan="2">Balance</th>
                </tr>
                <tr>
                    <th class="col-qty">Qty</th>
                    <th class="col-amount">Amount IDR</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-amount">Amount IDR</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-amount">Amount IDR</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < count($pageRows); $i++) { ?>
                    <?php $r = $pageRows[$i]; ?>

                    <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
                        <tr class="customer-row">
                            <td colspan="10">
                                <i class="bi bi-building me-2" style="font-size:14px"></i>
                                <?php echo h($r["CUST_CODE"]); ?>
                                &mdash;
                                <?php echo h($r["CUST_COMP"]); ?>
                            </td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
                        <tr>
                            <td class="col-code"><?php echo h($r["PART_NUM"]); ?></td>
                            <td class="col-no"><?php echo h($r["PART_NO"]); ?></td>
                            <td class="col-name" title="<?php echo h($r["PART_NAME"]); ?>"><?php echo h($r["PART_NAME"]); ?></td>
                            <td class="col-price num">
                                <?php echo h(fmt_price($r["PRICE"])); ?>
                                <span style="font-size:10px; color:#6c757d"><?php echo h($r["CURR_CODE"]); ?></span>
                            </td>
                            <td class="col-qty num"><?php echo h(fmt_zero_dash($r["SCH_QTY"], 0)); ?></td>
                            <td class="col-amount num"><?php echo h(fmt_zero_dash($r["SCH_AMOUNT"], 2)); ?></td>
                            <td class="col-qty num"><?php echo h(fmt_zero_dash($r["DEL_QTY"], 0)); ?></td>
                            <td class="col-amount num"><?php echo h(fmt_zero_dash($r["DEL_AMOUNT"], 2)); ?></td>
                            <td class="col-qty num"><?php echo h(fmt_zero_dash($r["BAL_QTY"], 0)); ?></td>
                            <td class="col-amount num"><?php echo h(fmt_zero_dash($r["BAL_AMOUNT"], 2)); ?></td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL") { ?>
                        <tr class="total-row">
                            <td colspan="5" class="num">TOTAL IDR</td>
                            <td class="num"><?php echo h(fmt_amount($r["SCH_AMOUNT"])); ?></td>
                            <td></td>
                            <td class="num"><?php echo h(fmt_amount($r["DEL_AMOUNT"])); ?></td>
                            <td></td>
                            <td class="num"><?php echo h(fmt_amount($r["BAL_AMOUNT"])); ?></td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "GRAND_TOTAL") { ?>
                        <tr class="grand-row">
                            <td colspan="5" class="num">GRAND TOTAL IDR</td>
                            <td class="num"><?php echo h(fmt_amount($r["SCH_AMOUNT"])); ?></td>
                            <td></td>
                            <td class="num"><?php echo h(fmt_amount($r["DEL_AMOUNT"])); ?></td>
                            <td></td>
                            <td class="num"><?php echo h(fmt_amount($r["BAL_AMOUNT"])); ?></td>
                        </tr>

                    <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr>
                            <td colspan="10" class="center" style="padding:40px 0; font-size:14px;">
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
    var endDate   = document.getElementById("END_DATE").value;
    var custCode  = document.getElementById("CUST_CODE").value;
    var plant     = document.getElementById("PLANT").value;

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
        "delivery_balance_amount_export_excel_idr.php" +
        "?START_DATE=" + enc(startDate) +
        "&END_DATE=" + enc(endDate) +
        "&CUST_CODE=" + enc(custCode) +
        "&plant=" + enc(plant);
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