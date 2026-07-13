<?php
require_once __DIR__ . "/../config/database_ordering.php";

/*
    delivery_shortage_report.php
    Dashboard Shortage Delivery by Customer + Detail Item Shortage
    PHP 5.4 + SQL Server 2008 + sqlsrv

    Sumber data mengikuti delivery_balance_report.php:
    EXEC dbo.SP_DELIVERY_INSTRUCTION1 START_DATE, END_DATE, CUST_CODE

    Shortage dihitung dari:
    SHORTAGE_QTY = SCHEDULE - DELIVERED
    dengan kondisi hanya jika DELIVERED < SCHEDULE.
*/

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

function fmt_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, $decimal, ".", ",");
}

function sort_desc_shortage($a, $b) {
    if ($a["SHORTAGE"] == $b["SHORTAGE"]) {
        return 0;
    }

    return ($a["SHORTAGE"] < $b["SHORTAGE"]) ? 1 : -1;
}

function sort_customer_summary($a, $b) {
    if ($a["SHORTAGE"] == $b["SHORTAGE"]) {
        return 0;
    }

    return ($a["SHORTAGE"] < $b["SHORTAGE"]) ? 1 : -1;
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
$shortageRows = array();
$customerSummaryMap = array();
$customerSummaryRows = array();
$itemSummaryMap = array();
$itemSummaryRows = array();

$totalPoBal = 0;
$totalSchedule = 0;
$totalDelivered = 0;
$totalShortage = 0;
$totalBalance = 0;
$totalShortagePct = 0;
$totalCustomerShortage = 0;
$totalItemShortage = 0;

$chartCustomerLabels = array();
$chartCustomerShortage = array();
$chartCustomerKeys = array();
$chartItemLabels = array();
$chartItemShortage = array();

if ($is_filter) {
    if ($start_ymd == "" || $end_ymd == "") {
        die("Tanggal tidak valid.");
    }

    if ($cust_code == "") {
        $cust_code = "%";
    }

    $sql = "
        SET NOCOUNT ON;
        EXEC dbo.SP_DELIVERY_INSTRUCTION1 ?, ?, ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array(
        $start_ymd,
        $end_ymd,
        $cust_code
    ));

    if ($stmt === false) {
        die("<pre>Query Delivery Shortage gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $poBal     = isset($r["SPOQTY"]) ? (float)$r["SPOQTY"] : 0;
        $schedule  = isset($r["SSQTY"]) ? (float)$r["SSQTY"] : 0;
        $delivered = isset($r["SDELQTY"]) ? (float)$r["SDELQTY"] : 0;
        $balance   = $delivered - $schedule;
        $shortage  = 0;

        if ($schedule > $delivered) {
            $shortage = $schedule - $delivered;
        }

        $shortagePct = 0;
        if ($schedule > 0 && $shortage > 0) {
            $shortagePct = ($shortage / $schedule) * 100;
        }

        $row = array(
            "CUST_CODE" => safe_trim($r["CUST_CODE"]),
            "CUST_COMP" => safe_trim($r["CUST_COMP"]),
            "PART_NUM"  => safe_trim($r["PART_NUM"]),
            "PART_NO"   => safe_trim($r["PART_NO"]),
            "PART_NAME" => safe_trim($r["PART_NAME"]),
            "PO_BAL"    => $poBal,
            "SCHEDULE"  => $schedule,
            "DELIVERED" => $delivered,
            "BALANCE"   => $balance,
            "SHORTAGE"  => $shortage,
            "SHORTAGE_PCT" => $shortagePct
        );

        $rows[] = $row;

        if ($shortage > 0) {
            $shortageRows[] = $row;

            $totalPoBal += $poBal;
            $totalSchedule += $schedule;
            $totalDelivered += $delivered;
            $totalShortage += $shortage;
            $totalBalance += $balance;

            $custKey = $row["CUST_CODE"] . "|" . $row["CUST_COMP"];

            if (!isset($customerSummaryMap[$custKey])) {
                $customerSummaryMap[$custKey] = array(
                    "CUSTOMER_KEY" => $custKey,
                    "CUST_CODE" => $row["CUST_CODE"],
                    "CUST_COMP" => $row["CUST_COMP"],
                    "PO_BAL" => 0,
                    "SCHEDULE" => 0,
                    "DELIVERED" => 0,
                    "SHORTAGE" => 0,
                    "BALANCE" => 0,
                    "ITEM_COUNT" => 0,
                    "SHORTAGE_PCT" => 0
                );
            }

            $customerSummaryMap[$custKey]["PO_BAL"] += $poBal;
            $customerSummaryMap[$custKey]["SCHEDULE"] += $schedule;
            $customerSummaryMap[$custKey]["DELIVERED"] += $delivered;
            $customerSummaryMap[$custKey]["SHORTAGE"] += $shortage;
            $customerSummaryMap[$custKey]["BALANCE"] += $balance;
            $customerSummaryMap[$custKey]["ITEM_COUNT"] += 1;

            $itemKey = $row["PART_NUM"] . "|" . $row["PART_NO"] . "|" . $row["PART_NAME"];

            if (!isset($itemSummaryMap[$itemKey])) {
                $itemSummaryMap[$itemKey] = array(
                    "PART_NUM" => $row["PART_NUM"],
                    "PART_NO" => $row["PART_NO"],
                    "PART_NAME" => $row["PART_NAME"],
                    "SCHEDULE" => 0,
                    "DELIVERED" => 0,
                    "SHORTAGE" => 0,
                    "SHORTAGE_PCT" => 0
                );
            }

            $itemSummaryMap[$itemKey]["SCHEDULE"] += $schedule;
            $itemSummaryMap[$itemKey]["DELIVERED"] += $delivered;
            $itemSummaryMap[$itemKey]["SHORTAGE"] += $shortage;
        }
    }

    sqlsrv_free_stmt($stmt);

    if ($totalSchedule > 0) {
        $totalShortagePct = ($totalShortage / $totalSchedule) * 100;
    }

    foreach ($customerSummaryMap as $key => $cr) {
        if ($cr["SCHEDULE"] > 0) {
            $cr["SHORTAGE_PCT"] = ($cr["SHORTAGE"] / $cr["SCHEDULE"]) * 100;
        }
        $customerSummaryRows[] = $cr;
    }

    usort($customerSummaryRows, "sort_customer_summary");

    foreach ($itemSummaryMap as $key => $ir) {
        if ($ir["SCHEDULE"] > 0) {
            $ir["SHORTAGE_PCT"] = ($ir["SHORTAGE"] / $ir["SCHEDULE"]) * 100;
        }
        $itemSummaryRows[] = $ir;
    }

    usort($itemSummaryRows, "sort_desc_shortage");
    usort($shortageRows, "sort_desc_shortage");

    $totalCustomerShortage = count($customerSummaryRows);
    $totalItemShortage = count($shortageRows);

    $topCustomerCount = count($customerSummaryRows);
    if ($topCustomerCount > 10) {
        $topCustomerCount = 10;
    }

    for ($i = 0; $i < $topCustomerCount; $i++) {
        $cr = $customerSummaryRows[$i];
        $label = $cr["CUST_CODE"];
        if ($cr["CUST_COMP"] != "") {
            $label .= " - " . $cr["CUST_COMP"];
        }
        $chartCustomerLabels[] = $label;
        $chartCustomerShortage[] = round($cr["SHORTAGE"], 2);
        $chartCustomerKeys[] = $cr["CUSTOMER_KEY"];
    }

    $topItemCount = count($itemSummaryRows);
    if ($topItemCount > 10) {
        $topItemCount = 10;
    }

    for ($i = 0; $i < $topItemCount; $i++) {
        $ir = $itemSummaryRows[$i];
        $label = $ir["PART_NUM"];
        if ($ir["PART_NAME"] != "") {
            $label .= " - " . $ir["PART_NAME"];
        }
        $chartItemLabels[] = $label;
        $chartItemShortage[] = round($ir["SHORTAGE"], 2);
    }
}

$selfFile = basename($_SERVER["PHP_SELF"]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dashboard Shortage Delivery</title>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>

    <style>
        body {
            margin: 0;
            padding: 18px;
            background: #eef1f5;
            font-family: Arial, Helvetica, sans-serif;
            color: #333333;
            font-size: 13px;
        }

        h2 {
            margin: 0 0 5px 0;
            font-size: 24px;
        }

        .subtitle {
            color: #666666;
            margin-bottom: 15px;
            font-size: 12px;
        }

        .filter-box {
            background: #ffffff;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 15px;
        }

        .filter-box label {
            font-weight: bold;
            margin-right: 5px;
        }

        .filter-box input {
            padding: 7px;
            border: 1px solid #bbbbbb;
            border-radius: 3px;
            height: 32px;
            box-sizing: border-box;
            margin-right: 8px;
        }

        .filter-date {
            width: 145px;
        }

        .filter-cust {
            width: 170px;
        }

        .btn {
            display: inline-block;
            height: 32px;
            line-height: 32px;
            padding: 0 13px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            color: #ffffff;
            font-size: 12px;
            font-weight: bold;
            vertical-align: middle;
        }

        .btn-primary { background: #2c3e50; }
        .btn-secondary { background: #7f8c8d; }
        .btn-red { background: #c0392b; }
        .btn-orange { background: #e67e22; }

        .autocomplete-wrap {
            position: relative;
            display: inline-block;
        }

        .autocomplete-list {
            position: absolute;
            top: 32px;
            left: 0;
            width: 430px;
            max-height: 230px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid #444444;
            z-index: 9999;
            display: none;
            box-shadow: 2px 2px 6px rgba(0,0,0,0.25);
        }

        .autocomplete-item {
            padding: 7px 9px;
            border-bottom: 1px solid #dddddd;
            cursor: pointer;
            line-height: 16px;
            font-size: 12px;
        }

        .autocomplete-item:hover,
        .autocomplete-item.active {
            background: #2f70c9;
            color: #ffffff;
        }

        .cards {
            display: table;
            width: 100%;
            border-spacing: 10px;
            margin-left: -10px;
        }

        .card {
            display: table-cell;
            background: #ffffff;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            padding: 14px;
            width: 16%;
            vertical-align: top;
        }

        .card-title {
            font-size: 12px;
            color: #777777;
            margin-bottom: 8px;
        }

        .card-value {
            font-size: 22px;
            font-weight: bold;
        }

        .card-small {
            margin-top: 5px;
            font-size: 11px;
            color: #777777;
        }

        .red { color: #c0392b; }
        .green { color: #27ae60; }
        .blue { color: #2980b9; }
        .orange { color: #e67e22; }
        .purple { color: #8e44ad; }

        .row {
            display: table;
            width: 100%;
            border-spacing: 10px;
            margin-left: -10px;
        }

        .col-6 {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .panel {
            background: #ffffff;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            padding: 14px;
            margin-top: 15px;
        }

        .panel h3 {
            margin: 0 0 12px 0;
            font-size: 16px;
        }

        .note {
            font-size: 12px;
            color: #777777;
            margin-top: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th {
            background: #34495e;
            color: #ffffff;
            padding: 8px;
            border: 1px solid #cccccc;
            white-space: nowrap;
            text-align: left;
        }

        td {
            padding: 7px;
            border: 1px solid #dddddd;
            white-space: nowrap;
        }

        td.num,
        th.num {
            text-align: right;
        }

        .customer-row td {
            background: #ecf0f1;
            font-weight: bold;
            color: #2c3e50;
            font-size: 13px;
        }

        .shortage-row:hover {
            background: #fff5f5;
        }

        .shortage-value {
            color: #c0392b;
            font-weight: bold;
        }

        .selected-filter {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 9px 12px;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }

        .clickable {
            cursor: pointer;
        }

        .clickable:hover {
            background: #e8f4fd;
        }

        .empty-box {
            background: #ffffff;
            border: 1px dashed #cccccc;
            border-radius: 6px;
            padding: 45px;
            text-align: center;
            color: #777777;
            font-size: 16px;
        }

        @media print {
            .filter-box,
            .btn,
            .selected-filter,
            .note {
                display: none !important;
            }

            body {
                background: #ffffff;
                padding: 0;
            }

            .panel,
            .card {
                border: 1px solid #000000;
            }
        }
    </style>
</head>
<body>

<h2>Dashboard Shortage Delivery</h2>
<div class="subtitle">
    Source: SP_DELIVERY_INSTRUCTION1 | Shortage = Schedule - Delivered jika Delivered &lt; Schedule
</div>

<div class="filter-box">
    <form method="get" action="<?php echo h($selfFile); ?>" autocomplete="off">
        <input type="hidden" name="RUN" value="1">

        <label>Start:</label>
        <input type="date"
               id="START_DATE"
               name="START_DATE"
               class="filter-date"
               value="<?php echo h($start_input); ?>">

        <label>End:</label>
        <input type="date"
               id="END_DATE"
               name="END_DATE"
               class="filter-date"
               value="<?php echo h($end_input); ?>">

        <label>Customer:</label>
        <div class="autocomplete-wrap">
            <input type="text"
                   id="CUST_CODE"
                   name="CUST_CODE"
                   class="filter-cust"
                   value="<?php echo h($cust_code); ?>"
                   placeholder="Ketik customer / %">
            <div id="custSuggest" class="autocomplete-list"></div>
        </div>

        <button type="submit" class="btn btn-primary">Tampilkan</button>
        <button type="button" class="btn btn-secondary" onclick="setAllCustomer()">All Customer</button>
        <button type="button" class="btn btn-orange" onclick="window.print()">Print</button>
        <a class="btn btn-red" href="<?php echo h($selfFile); ?>">Reset</a>
    </form>
</div>

<?php if (!$is_filter) { ?>
    <div class="empty-box">
        Isi tanggal dan customer, lalu klik <b>Tampilkan</b>.<br>
        Gunakan <b>%</b> atau tombol <b>All Customer</b> untuk semua customer.
    </div>
<?php } else { ?>

<div class="cards">
    <div class="card">
        <div class="card-title">Total Schedule</div>
        <div class="card-value blue"><?php echo h(fmt_num($totalSchedule, 0)); ?></div>
        <div class="card-small">Qty rencana kirim</div>
    </div>

    <div class="card">
        <div class="card-title">Total Delivered</div>
        <div class="card-value green"><?php echo h(fmt_num($totalDelivered, 0)); ?></div>
        <div class="card-small">Qty sudah dikirim</div>
    </div>

    <div class="card">
        <div class="card-title">Total Shortage</div>
        <div class="card-value red"><?php echo h(fmt_num($totalShortage, 0)); ?></div>
        <div class="card-small">Schedule - Delivered</div>
    </div>

    <div class="card">
        <div class="card-title">Shortage %</div>
        <div class="card-value red"><?php echo h(fmt_num($totalShortagePct, 2)); ?>%</div>
        <div class="card-small">Shortage / Schedule</div>
    </div>

    <div class="card">
        <div class="card-title">Customer Shortage</div>
        <div class="card-value purple"><?php echo h(fmt_num($totalCustomerShortage, 0)); ?></div>
        <div class="card-small">Customer yang kurang kirim</div>
    </div>

    <div class="card">
        <div class="card-title">Item Shortage</div>
        <div class="card-value orange"><?php echo h(fmt_num($totalItemShortage, 0)); ?></div>
        <div class="card-small">Item yang shortage</div>
    </div>
</div>

<div class="row">
    <div class="col-6">
        <div class="panel">
            <h3>Top 10 Shortage by Customer</h3>
            <canvas id="chartCustomerShortage" height="170"></canvas>
            <div class="note">Klik bar customer untuk filter tabel item shortage.</div>
        </div>
    </div>

    <div class="col-6">
        <div class="panel">
            <h3>Top 10 Shortage by Item</h3>
            <canvas id="chartItemShortage" height="170"></canvas>
        </div>
    </div>
</div>

<div id="selectedFilter" class="selected-filter">
    Filter customer aktif: <b id="selectedCustomerText"></b>
    <button type="button" class="btn btn-secondary" onclick="clearCustomerFilter()" style="margin-left:10px;">Tampilkan Semua</button>
</div>

<div class="panel">
    <h3>Summary Shortage per Customer</h3>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Customer</th>
                <th class="num">Schedule</th>
                <th class="num">Delivered</th>
                <th class="num">Shortage</th>
                <th class="num">Shortage %</th>
                <th class="num">Jumlah Item</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($customerSummaryRows) > 0) { ?>
                <?php for ($i = 0; $i < count($customerSummaryRows); $i++) { ?>
                    <?php
                        $cr = $customerSummaryRows[$i];
                        $custLabel = $cr["CUST_CODE"];
                        if ($cr["CUST_COMP"] != "") {
                            $custLabel .= " - " . $cr["CUST_COMP"];
                        }
                    ?>
                    <tr class="clickable" onclick="filterCustomer('<?php echo h($cr["CUSTOMER_KEY"]); ?>', '<?php echo h($custLabel); ?>')">
                        <td><?php echo h($i + 1); ?></td>
                        <td><?php echo h($custLabel); ?></td>
                        <td class="num"><?php echo h(fmt_num($cr["SCHEDULE"], 0)); ?></td>
                        <td class="num"><?php echo h(fmt_num($cr["DELIVERED"], 0)); ?></td>
                        <td class="num shortage-value"><?php echo h(fmt_num($cr["SHORTAGE"], 0)); ?></td>
                        <td class="num shortage-value"><?php echo h(fmt_num($cr["SHORTAGE_PCT"], 2)); ?>%</td>
                        <td class="num"><?php echo h(fmt_num($cr["ITEM_COUNT"], 0)); ?></td>
                    </tr>
                <?php } ?>
            <?php } else { ?>
                <tr>
                    <td colspan="7" style="text-align:center;">Tidak ada shortage delivery pada periode ini.</td>
                </tr>
            <?php } ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2">Total</th>
                <th class="num"><?php echo h(fmt_num($totalSchedule, 0)); ?></th>
                <th class="num"><?php echo h(fmt_num($totalDelivered, 0)); ?></th>
                <th class="num"><?php echo h(fmt_num($totalShortage, 0)); ?></th>
                <th class="num"><?php echo h(fmt_num($totalShortagePct, 2)); ?>%</th>
                <th class="num"><?php echo h(fmt_num($totalItemShortage, 0)); ?></th>
            </tr>
        </tfoot>
    </table>
</div>

<div class="panel" id="detailPanel">
    <h3>Detail Item yang Shortage</h3>

    <table id="shortageDetailTable">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Part Code</th>
                <th>Part No</th>
                <th>Part Name</th>
                <th class="num">PO Bal.</th>
                <th class="num">Schedule</th>
                <th class="num">Delivered</th>
                <th class="num">Shortage</th>
                <th class="num">Shortage %</th>
                <th class="num">Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($shortageRows) > 0) { ?>
                <?php for ($i = 0; $i < count($shortageRows); $i++) { ?>
                    <?php
                        $r = $shortageRows[$i];
                        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];
                        $custLabel = $r["CUST_CODE"];
                        if ($r["CUST_COMP"] != "") {
                            $custLabel .= " - " . $r["CUST_COMP"];
                        }
                    ?>
                    <tr class="shortage-row" data-cust="<?php echo h($custKey); ?>">
                        <td><?php echo h($custLabel); ?></td>
                        <td><?php echo h($r["PART_NUM"]); ?></td>
                        <td><?php echo h($r["PART_NO"]); ?></td>
                        <td><?php echo h($r["PART_NAME"]); ?></td>
                        <td class="num"><?php echo h(fmt_num($r["PO_BAL"], 0)); ?></td>
                        <td class="num"><?php echo h(fmt_num($r["SCHEDULE"], 0)); ?></td>
                        <td class="num"><?php echo h(fmt_num($r["DELIVERED"], 0)); ?></td>
                        <td class="num shortage-value"><?php echo h(fmt_num($r["SHORTAGE"], 0)); ?></td>
                        <td class="num shortage-value"><?php echo h(fmt_num($r["SHORTAGE_PCT"], 2)); ?>%</td>
                        <td class="num"><?php echo h(fmt_num($r["BALANCE"], 0)); ?></td>
                    </tr>
                <?php } ?>
            <?php } else { ?>
                <tr>
                    <td colspan="10" style="text-align:center;">Tidak ada item yang shortage.</td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <div class="note">
        Hanya menampilkan item dengan Delivered &lt; Schedule. Balance negatif berarti kurang kirim.
    </div>
</div>

<?php } ?>

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
        .replace(/\"/g, "&quot;");
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

var custInput = document.getElementById("CUST_CODE");

if (custInput) {
    custInput.onkeyup = function (e) {
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
                return false;
            }

            return true;
        }

        clearTimeout(timer);

        var q = this.value;

        timer = setTimeout(function () {
            searchCustomer(q);
        }, 250);
    };

    custInput.onblur = function () {
        setTimeout(function () {
            hideSuggest();
        }, 250);
    };
}

function filterCustomer(custKey, custLabel) {
    var table = document.getElementById("shortageDetailTable");

    if (!table) {
        return;
    }

    var rows = table.getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        var rowCust = row.getAttribute("data-cust");

        if (!rowCust) {
            continue;
        }

        if (rowCust == custKey) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    }

    var box = document.getElementById("selectedFilter");
    var text = document.getElementById("selectedCustomerText");

    if (box && text) {
        text.innerHTML = htmlEncode(custLabel);
        box.style.display = "block";
    }

    var panel = document.getElementById("detailPanel");
    if (panel && panel.scrollIntoView) {
        panel.scrollIntoView(true);
    }
}

function clearCustomerFilter() {
    var table = document.getElementById("shortageDetailTable");

    if (!table) {
        return;
    }

    var rows = table.getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        if (rows[i].getAttribute("data-cust")) {
            rows[i].style.display = "";
        }
    }

    var box = document.getElementById("selectedFilter");
    if (box) {
        box.style.display = "none";
    }
}

<?php if ($is_filter) { ?>
var customerLabels = <?php echo json_encode($chartCustomerLabels); ?>;
var customerShortage = <?php echo json_encode($chartCustomerShortage); ?>;
var customerKeys = <?php echo json_encode($chartCustomerKeys); ?>;

var itemLabels = <?php echo json_encode($chartItemLabels); ?>;
var itemShortage = <?php echo json_encode($chartItemShortage); ?>;

if (document.getElementById("chartCustomerShortage")) {
    new Chart(document.getElementById("chartCustomerShortage"), {
        type: "horizontalBar",
        data: {
            labels: customerLabels,
            datasets: [
                {
                    label: "Shortage Qty",
                    data: customerShortage,
                    backgroundColor: "rgba(192, 57, 43, 0.75)"
                }
            ]
        },
        options: {
            responsive: true,
            legend: {
                position: "bottom"
            },
            onClick: function(evt) {
                var activePoints = this.getElementAtEvent(evt);

                if (activePoints.length > 0) {
                    var index = activePoints[0]._index;

                    if (customerKeys[index]) {
                        filterCustomer(customerKeys[index], customerLabels[index]);
                    }
                }
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        return "Shortage: " + tooltipItem.xLabel;
                    }
                }
            },
            scales: {
                xAxes: [{
                    ticks: {
                        beginAtZero: true
                    }
                }]
            }
        }
    });
}

if (document.getElementById("chartItemShortage")) {
    new Chart(document.getElementById("chartItemShortage"), {
        type: "horizontalBar",
        data: {
            labels: itemLabels,
            datasets: [
                {
                    label: "Shortage Qty",
                    data: itemShortage,
                    backgroundColor: "rgba(230, 126, 34, 0.75)"
                }
            ]
        },
        options: {
            responsive: true,
            legend: {
                position: "bottom"
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        return "Shortage: " + tooltipItem.xLabel;
                    }
                }
            },
            scales: {
                xAxes: [{
                    ticks: {
                        beginAtZero: true
                    }
                }]
            }
        }
    });
}
<?php } ?>
</script>

</body>
</html>
