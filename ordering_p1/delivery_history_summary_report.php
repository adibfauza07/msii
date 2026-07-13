<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8"); }
function safe_trim($value) { return ($value === null) ? "" : trim((string)$value); }
function get_param($name, $default = "") {
    if (isset($_GET[$name])) return trim($_GET[$name]);
    if (isset($_POST[$name])) return trim($_POST[$name]);
    return $default;
}
function month_to_yyyymmdd($value) {
    $value = trim($value);
    if ($value == "") return "";
    if (preg_match('/^\d{4}-\d{2}$/', $value)) return str_replace("-", "", $value) . "01";
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return str_replace("-", "", $value);
    if (preg_match('/^\d{8}$/', $value)) return $value;
    $ts = strtotime($value);
    return ($ts === false) ? "" : date("Ymd", $ts);
}
function yyyymmdd_to_month_input($value) {
    $value = trim($value);
    if ($value == "") return date("Y-m");
    if (preg_match('/^\d{8}$/', $value)) return substr($value, 0, 4) . "-" . substr($value, 4, 2);
    $ts = strtotime($value);
    return ($ts === false) ? date("Y-m") : date("Y-m", $ts);
}
function fmt_print_datetime() { return date("d-M-Y h:i:sA"); }
function fmt_num($value, $decimal = 0) { return number_format((float)($value ?: 0), $decimal, ".", ","); }
function fmt_price($value) { return number_format((float)($value ?: 0), 4, ".", ","); }
function fmt_amount($value) { return number_format((float)($value ?: 0), 2, ".", ","); }

function get_usd_factor($row) {
    $crate = (isset($row["CRATE"]) && $row["CRATE"] != 0) ? (float)$row["CRATE"] : 1;
    $basecrate = (isset($row["BASECRATE"]) && $row["BASECRATE"] != 0) ? (float)$row["BASECRATE"] : 1;
    return $crate / $basecrate;
}

$is_filter = get_param("RUN", "") == "1";
$asper_month = get_param("ASPER_MONTH", date("Y-m"));
$cust_code   = get_param("CUST_CODE", "");
if ($is_filter && $cust_code == "") $cust_code = "%";

$asper_ymd = month_to_yyyymmdd($asper_month);
$rows = array();
$printRows = array();
$monthYear = "";

if ($is_filter) {
    if ($asper_ymd == "") die("Month tidak valid.");
    
    $sql = "SET NOCOUNT ON; EXEC dbo.SP_DELIVERY_HISTORY_char_sum ?, ?";
    $stmt = sqlsrv_query($conn, $sql, array($asper_ymd, $cust_code));
    if ($stmt === false) die("<pre>Query Delivery History gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($monthYear == "" && isset($r["MonthYear"])) $monthYear = safe_trim($r["MonthYear"]);

        $qty = isset($r["QTY"]) ? (float)$r["QTY"] : 0;
        $price = isset($r["PART_PRICE"]) ? (float)$r["PART_PRICE"] : 0;
        $amount = isset($r["AMOUNT"]) ? (float)$r["AMOUNT"] : ($qty * $price);
        $usdFactor = get_usd_factor($r);

        $rows[] = array(
            "CUST_CODE"  => safe_trim($r["CUST_CODE"]),
            "CUST_COMP"  => safe_trim($r["CUST_COMP"]),
            "PART_NUM"   => safe_trim($r["PART_NUM"]),
            "PART_NAME"  => safe_trim($r["PART_NAME"]),
            "CURR_CODE"       => safe_trim($r["CURR_CODE"]),
            "PART_PRICE" => $price,
            "QTY"        => $qty,
            "AMOUNT"     => $amount,
            "USD_AMT"    => $amount * $usdFactor
        );
    }


    /* Sort by customer, then item code (PART_NUM) within each customer. */
    usort($rows, function ($a, $b) {
        $cmp = strnatcasecmp($a["CUST_CODE"], $b["CUST_CODE"]);
        if ($cmp !== 0) return $cmp;

        $cmp = strnatcasecmp($a["CUST_COMP"], $b["CUST_COMP"]);
        if ($cmp !== 0) return $cmp;

        return strnatcasecmp($a["PART_NUM"], $b["PART_NUM"]);
    });

    if ($monthYear == "") {
        $ts = strtotime(substr($asper_ymd, 0, 4) . "-" . substr($asper_ymd, 4, 2) . "-01");
        $monthYear = date("F Y", $ts);
    }

    $lastCust = ""; $lastCustCode = ""; $lastCustComp = "";
    
    $custQty = 0; $custAmount = 0; $custUsdAmount = 0;
    $grandQty = 0; $grandAmount = 0; $grandUsdAmount = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];
        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];

        if ($custKey != $lastCust) {
            if ($lastCust != "") {
                $printRows[] = array("ROW_TYPE" => "CUSTOMER_TOTAL", "CUST_CODE" => $lastCustCode, "CUST_COMP" => $lastCustComp, "QTY" => $custQty, "AMOUNT" => $custAmount, "USD_AMT" => $custUsdAmount);
            }
            $printRows[] = array("ROW_TYPE" => "CUSTOMER", "CUST_CODE" => $r["CUST_CODE"], "CUST_COMP" => $r["CUST_COMP"]);

            $lastCust = $custKey; $lastCustCode = $r["CUST_CODE"]; $lastCustComp = $r["CUST_COMP"];
            $custQty = 0; $custAmount = 0; $custUsdAmount = 0;
        }

        $printRows[] = array(
            "ROW_TYPE"   => "DETAIL",
            "PART_NUM"   => $r["PART_NUM"],
            "PART_NAME"  => $r["PART_NAME"],
            "PART_PRICE" => $r["PART_PRICE"],
            "CURR_CODE"       => $r["CURR_CODE"],
            "QTY"        => $r["QTY"],
            "AMOUNT"     => $r["AMOUNT"],
            "USD_AMT"    => $r["USD_AMT"]
        );

        $custQty += $r["QTY"]; $custAmount += $r["AMOUNT"]; $custUsdAmount += $r["USD_AMT"];
        $grandQty += $r["QTY"]; $grandAmount += $r["AMOUNT"]; $grandUsdAmount += $r["USD_AMT"];
    }

    if ($lastCust != "") $printRows[] = array("ROW_TYPE" => "CUSTOMER_TOTAL", "CUST_CODE" => $lastCustCode, "CUST_COMP" => $lastCustComp, "QTY" => $custQty, "AMOUNT" => $custAmount, "USD_AMT" => $custUsdAmount);
    if (count($rows) > 0) $printRows[] = array("ROW_TYPE" => "GRAND_TOTAL", "QTY" => $grandQty, "AMOUNT" => $grandAmount, "USD_AMT" => $grandUsdAmount);
    if (count($printRows) == 0) $printRows[] = array("ROW_TYPE" => "EMPTY", "MESSAGE" => "Data delivery history tidak ditemukan.");
}

$selfFile = basename($_SERVER["PHP_SELF"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery History Sum</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; background-color: #f4f6f9; font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 13px; color: #333; }
        .content-header { padding: 15px 20px; background-color: #fff; border-bottom: 1px solid #dee2e6; margin-bottom: 20px; }
        .content-header h1 { margin: 0; font-size: 20px; font-weight: 500; }
        .breadcrumb { font-size: 12px; color: #6c757d; margin-top: 5px; }
        .container { padding: 0 20px 20px 20px; }
        .card { background: #ffffff; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,.12), 0 1px 2px rgba(0,0,0,.24); margin-bottom: 20px; }
        .card-header { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .filter-group { display: flex; align-items: center; gap: 15px; }
        .filter-item { display: flex; align-items: center; gap: 8px; }
        .filter-item label { font-weight: 600; margin: 0; }
        .form-control { height: 30px; padding: 4px 8px; border: 1px solid #ccc; border-radius: 3px; font-size: 13px; }
        .input-group { display: flex; }
        .input-group .form-control { border-top-right-radius: 0; border-bottom-right-radius: 0; }
        .input-group .btn-search { border: 1px solid #ccc; background: #f8f9fa; border-left: none; padding: 0 10px; cursor: pointer; border-top-right-radius: 3px; border-bottom-right-radius: 3px; }
        .btn { height: 30px; padding: 0 12px; font-size: 12px; font-weight: 600; border: none; border-radius: 3px; cursor: pointer; display: flex; align-items: center; gap: 6px; color: #fff; }
        .btn-primary { background-color: #007bff; } .btn-primary:hover { background-color: #0069d9; }
        .btn-success { background-color: #28a745; } .btn-success:hover { background-color: #218838; }
        .btn-danger { background-color: #dc3545; } .btn-danger:hover { background-color: #c82333; }
        .btn-secondary { background-color: #6c757d; } .btn-secondary:hover { background-color: #5a6268; }
        .action-group { display: flex; gap: 8px; }
        .card-body { padding: 15px; overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .table th, .table td { border: 1px solid #dee2e6; padding: 6px 8px; white-space: nowrap; }
        .table thead th { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; text-align: left; font-weight: 600; }
        .customer-row td { background-color: #e9ecef; font-weight: bold; font-size: 13px; }
        .table tbody tr:hover { background-color: #f1f1f1; }
        .customer-total-row td { font-weight: bold; background-color: #f8f9fa; }
        .grand-row td { font-weight: bold; background-color: #d1ecf1; font-size: 13px; }
        .num { text-align: right; }
        .center { text-align: center; }
        .autocomplete-wrap { position: relative; }
        .autocomplete-list { position: absolute; top: 32px; left: 0; width: 100%; min-width: 300px; max-height: 230px; overflow-y: auto; background: #fff; border: 1px solid #ccc; z-index: 9999; display: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .autocomplete-item { padding: 8px 10px; border-bottom: 1px solid #f1f1f1; cursor: pointer; }
        .autocomplete-item:hover, .autocomplete-item.active { background: #007bff; color: #fff; }
        @media print {
            .content-header, .card-header { display: none !important; }
            .card { box-shadow: none; border: none; }
            body { background: #fff; }
            .table { font-size: 10px; }
            .table th, .table td { border: 1px solid #000 !important; padding: 4px; }
            @page { size: A4 portrait; margin: 10mm; }
        }
    </style>
</head>
<body>

<div class="content-header">
    <h1>Delivery History (Sum)</h1>
    <div class="breadcrumb">Report &gt; Delivery History</div>
</div>

<div class="container">
    <div class="card">
        <div class="card-header">
            <form method="get" action="<?= h($selfFile) ?>" autocomplete="off" style="display: flex; flex-wrap: wrap; width: 100%; justify-content: space-between; align-items: center;">
                <input type="hidden" name="RUN" value="1">
                <div class="filter-group">
                    <div class="filter-item">
                        <label>As per Month</label>
                        <input type="month" id="ASPER_MONTH" name="ASPER_MONTH" class="form-control" style="width: 140px;" value="<?= h(yyyymmdd_to_month_input($asper_ymd)) ?>">
                    </div>
                    <div class="filter-item">
                        <label>Customer</label>
                        <div class="autocomplete-wrap input-group">
                            <input type="text" id="CUST_CODE" name="CUST_CODE" class="form-control" style="width: 200px;" value="<?= h($cust_code) ?>" placeholder="Kode customer / %">
                            <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
                            <div id="custSuggest" class="autocomplete-list"></div>
                        </div>
                    </div>
                </div>
                <div class="action-group">
                    <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> PRINT</button>
                    <button type="button" class="btn btn-success" onclick="exportExcel()"><i class="fas fa-file-excel"></i> EXCEL</button>
                    <button type="button" class="btn btn-danger" onclick="closeReport()"><i class="fas fa-times"></i> CLOSE</button>
                </div>
            </form>
        </div>

        <div class="card-body">
            <?php if (!$is_filter): ?>
                <div style="text-align: center; padding: 50px 0; color: #6c757d;">
                    <i class="fas fa-info-circle" style="font-size: 30px; margin-bottom: 15px;"></i>
                    <p style="font-size: 15px; margin: 0;">Silakan pilih <b>Month</b> dan isi <b>Customer</b> lalu tekan tombol pencarian.</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">CODE</th>
                            <th style="width: 35%;">NAME</th>
                            <th class="num" style="width: 10%;">PRICE</th>
                            <th class="center" style="width: 10%;">CURR</th>
                            <th class="num" style="width: 10%;">QTY</th>
                            <th class="num" style="width: 10%;">AMOUNT</th>
                            <th class="num" style="width: 10%;">USD.AMT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($printRows as $r): ?>
                            <?php if ($r["ROW_TYPE"] == "CUSTOMER"): ?>
                                <tr class="customer-row">
                                    <td colspan="7">
                                        <i class="fas fa-building" style="margin-right: 5px; color: #6c757d;"></i>
                                        <?= h($r["CUST_CODE"]) ?> &nbsp;&nbsp;&nbsp; <?= h($r["CUST_COMP"]) ?>
                                    </td>
                                </tr>
                            <?php elseif ($r["ROW_TYPE"] == "DETAIL"): ?>
                                <tr>
                                    <td><?= h($r["PART_NUM"]) ?></td>
                                    <td><?= h($r["PART_NAME"]) ?></td>
                                    <td class="num"><?= h(fmt_price($r["PART_PRICE"])) ?></td>
                                    <td class="center"><?= h($r["CURR_CODE"]) ?></td>
                                    <td class="num"><?= h(fmt_num($r["QTY"], 0)) ?></td>
                                    <td class="num"><?= h(fmt_amount($r["AMOUNT"])) ?></td>
                                    <td class="num"><?= h(fmt_amount($r["USD_AMT"])) ?></td>
                                </tr>
                            <?php elseif ($r["ROW_TYPE"] == "CUSTOMER_TOTAL"): ?>
                                <tr class="customer-total-row">
                                    <td colspan="4" class="num">Subtotal Customer <?= h($r["CUST_CODE"]) ?> :</td>
                                    <td class="num"><?= h(fmt_num($r["QTY"], 0)) ?></td>
                                    <td class="num"><?= h(fmt_amount($r["AMOUNT"])) ?></td>
                                    <td class="num"><?= h(fmt_amount($r["USD_AMT"])) ?></td>
                                </tr>
                            <?php elseif ($r["ROW_TYPE"] == "GRAND_TOTAL"): ?>
                                <tr class="grand-row">
                                    <td colspan="4" class="num">GRAND TOTAL :</td>
                                    <td class="num"><?= h(fmt_num($r["QTY"], 0)) ?></td>
                                    <td class="num"><?= h(fmt_amount($r["AMOUNT"])) ?></td>
                                    <td class="num"><?= h(fmt_amount($r["USD_AMT"])) ?></td>
                                </tr>
                            <?php elseif ($r["ROW_TYPE"] == "EMPTY"): ?>
                                <tr>
                                    <td colspan="7" class="center" style="padding: 30px;"><?= h($r["MESSAGE"]) ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
var custRows = []; var custIndex = -1; var timer = null;
function enc(v) { return encodeURIComponent(v == null ? "" : v); }
function htmlEncode(value) { return String(value == null ? "" : value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }

function closeReport() {
    try { if (window.parent && window.parent !== window) { window.location.href = "dashboard_home.php"; return; } } catch (e) {}
    window.open("", "_self"); window.close();
    setTimeout(function () { if (!window.closed) { window.location.href = "dashboard_home.php"; } }, 200);
}

function exportExcel() {
    var asperMonth = document.getElementById("ASPER_MONTH").value;
    var custCode = document.getElementById("CUST_CODE").value;
    if (asperMonth == "") { alert("Month belum dipilih."); return; }
    if (custCode == "") { alert("Customer belum diisi."); return; }
    window.location = "delivery_history_summary_export_excel.php?ASPER_MONTH=" + enc(asperMonth) + "&CUST_CODE=" + enc(custCode);
}

function hideSuggest() { document.getElementById("custSuggest").style.display = "none"; custRows = []; custIndex = -1; }
function setActiveCust(index) {
    var items = document.getElementsByClassName("autocomplete-item");
    if (!items.length) { custIndex = -1; return; }
    if (index < 0) index = items.length - 1;
    if (index >= items.length) index = 0;
    for (var i = 0; i < items.length; i++) items[i].className = "autocomplete-item";
    items[index].className = "autocomplete-item active"; custIndex = index;
}
function chooseCust(index) {
    if (index < 0 || index >= custRows.length) return;
    document.getElementById("CUST_CODE").value = custRows[index].CUST_CODE; hideSuggest();
}
function renderSuggest(rows) {
    var box = document.getElementById("custSuggest"); box.innerHTML = ""; custRows = rows || []; custIndex = -1;
    if (!rows || rows.length == 0) { box.style.display = "none"; return; }
    for (var i = 0; i < rows.length; i++) {
        (function (r, idx) {
            var div = document.createElement("div"); div.className = "autocomplete-item";
            div.innerHTML = "<b>" + htmlEncode(r.CUST_CODE) + "</b> - " + htmlEncode(r.CUST_COMP);
            div.onmouseover = function () { setActiveCust(idx); }; div.onmousedown = function (e) { if (e.preventDefault) e.preventDefault(); chooseCust(idx); };
            box.appendChild(div);
        })(rows[i], i);
    }
    box.style.display = "block"; setActiveCust(0);
}
function searchCustomer(q) {
    if (q == "" || q == "%") { hideSuggest(); return; }
    var xhr = new XMLHttpRequest(); xhr.open("POST", "ajax_customer_autocomplete.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
            try { var res = JSON.parse(xhr.responseText); renderSuggest(res.rows ? res.rows : res); } catch (e) { return; }
        }
    };
    xhr.send("q=" + enc(q));
}
document.getElementById("CUST_CODE").onkeyup = function (e) {
    var key = e.keyCode || e.which;
    if (key == 40) { setActiveCust(custIndex + 1); return; }
    if (key == 38) { setActiveCust(custIndex - 1); return; }
    if (key == 13) { if (custRows.length > 0) { chooseCust(custIndex < 0 ? 0 : custIndex); return false; } return true; }
    clearTimeout(timer); var q = this.value; timer = setTimeout(function () { searchCustomer(q); }, 250);
};
document.getElementById("CUST_CODE").onblur = function () { setTimeout(hideSuggest, 250); };
</script>
</body>
</html>