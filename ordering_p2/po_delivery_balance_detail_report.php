<?php
require_once __DIR__ . "/../config/db_plant2.php";

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

function fmt_print_datetime() {
    return date("d-M-Y H:i:s");
}

function fmt_num($value, $decimal = 0) {
    if ($value === null || $value === "") {
        $value = 0;
    }

    return number_format((float)$value, $decimal, ".", ",");
}

function fmt_bal($value) {
    if ($value === null || $value === "") {
        return "-";
    }

    $n = (float)$value;

    if ($n == 0) {
        return "-";
    }

    return number_format($n, 0, ".", ",");
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
$rowsPerPage = 30;

if ($is_filter) {
    if ($start_ymd == "" || $end_ymd == "") {
        die("Tanggal tidak valid.");
    }

    if ($cust_code == "") {
        $cust_code = "%";
    }

    $sql = "
        SET NOCOUNT ON;
        EXEC dbo.CUST_ORD_DEL_NEW ?, ?, ?
    ";

    $stmt = sqlsrv_query($conn, $sql, array(
        $start_ymd,
        $end_ymd,
        $cust_code
    ));

    if ($stmt === false) {
        die("<pre>Query PO Delivery Balance Detail gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
    }

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = array(
            "PART_NUM"  => safe_trim($r["PART_NUM"]),
            "PART_NO"   => safe_trim($r["PART_NO"]),
            "PART_NAME" => safe_trim($r["PART_NAME"]),
            "ORDR_PO"   => safe_trim($r["ORDR_PO"]),
            "ORDP_QTY"  => isset($r["ORDP_QTY"]) ? (float)$r["ORDP_QTY"] : 0,
            "DI_DSNO"   => safe_trim($r["DI_DSNO"]),
            "DI_DATE"   => $r["DI_DATE"],
            "QTY"       => isset($r["QTY"]) ? (float)$r["QTY"] : 0,
            "CUST_CODE" => safe_trim($r["CUST_CODE"]),
            "CUST_COMP" => safe_trim($r["CUST_COMP"]),
            "ORDR_DATE" => $r["ORDR_DATE"]
        );
    }

    $lastCust = "";
    $lastItem = "";
    $lastPoKey = "";
    $runningDelQty = 0;

    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];

        $custKey = $r["CUST_CODE"] . "|" . $r["CUST_COMP"];
        $itemKey = $r["CUST_CODE"] . "|" . $r["PART_NUM"] . "|" . $r["PART_NO"] . "|" . $r["PART_NAME"];

        $poDateKey = "";
        if ($r["ORDR_DATE"] instanceof DateTime) {
            $poDateKey = $r["ORDR_DATE"]->format("Ymd");
        } else {
            $poDateKey = safe_trim($r["ORDR_DATE"]);
        }

        $poKey = $itemKey . "|" . $r["ORDR_PO"] . "|" . $poDateKey . "|" . $r["ORDP_QTY"];

        if ($custKey != $lastCust) {
            $printRows[] = array(
                "ROW_TYPE"  => "CUSTOMER",
                "CUST_CODE" => $r["CUST_CODE"],
                "CUST_COMP" => $r["CUST_COMP"]
            );

            $lastCust = $custKey;
            $lastItem = "";
            $lastPoKey = "";
            $runningDelQty = 0;
        }

        if ($itemKey != $lastItem) {
            $printRows[] = array(
                "ROW_TYPE"  => "ITEM",
                "PART_NUM"  => $r["PART_NUM"],
                "PART_NO"   => $r["PART_NO"],
                "PART_NAME" => $r["PART_NAME"]
            );

            $lastItem = $itemKey;
            $lastPoKey = "";
            $runningDelQty = 0;
        }

        $isFirstPoLine = false;

        if ($poKey != $lastPoKey) {
            $lastPoKey = $poKey;
            $runningDelQty = 0;
            $isFirstPoLine = true;
        }

        $runningDelQty += $r["QTY"];
        $poBal = $r["ORDP_QTY"] - $runningDelQty;

        if ($poBal < 0) {
            $poBal = 0;
        }

        $printRows[] = array(
            "ROW_TYPE"       => "DETAIL",
            "SHOW_PO"        => $isFirstPoLine ? 1 : 0,
            "ORDR_PO"        => $r["ORDR_PO"],
            "ORDR_DATE"      => $r["ORDR_DATE"],
            "DI_DATE"        => $r["DI_DATE"],
            "DI_DSNO"        => $r["DI_DSNO"],
            "ORDP_QTY"       => $r["ORDP_QTY"],
            "DEL_QTY"        => $r["QTY"],
            "PO_BAL"         => $poBal
        );
    }

    if (count($printRows) == 0) {
        $printRows[] = array(
            "ROW_TYPE" => "EMPTY",
            "MESSAGE"  => "Data PO Delivery Balance Detail tidak ditemukan."
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
<html>
<head>
    <meta charset="utf-8">
    <title>PO Delivery Balance Detail</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        body {
            margin: 0;
            background: #9a9a9a;
            font-family: "Courier New", monospace;
            font-size: 10px;
            color: #000000;
        }

        .filter-bar {
            width: 205mm;
            margin: 8px auto;
            background: #d4d0c8;
            border: 1px solid #666666;
            padding: 6px;
            box-sizing: border-box;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }

        .filter-bar input {
            height: 24px;
            border: 1px solid #777777;
            font-size: 12px;
            padding: 2px 4px;
            box-sizing: border-box;
        }

        .filter-date {
            width: 130px;
        }

        .filter-cust {
            width: 150px;
        }

        .filter-bar button {
            height: 26px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 4px;
        }

        .autocomplete-wrap {
            position: relative;
            display: inline-block;
        }

        .autocomplete-list {
            position: absolute;
            top: 24px;
            left: 0;
            width: 430px;
            max-height: 230px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid #444444;
            z-index: 9999;
            display: none;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.25);
        }

        .autocomplete-item {
            padding: 5px 7px;
            border-bottom: 1px solid #dddddd;
            cursor: pointer;
            line-height: 16px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }

        .autocomplete-item:hover,
        .autocomplete-item.active {
            background: #2f70c9;
            color: #ffffff;
        }

        .print-bar {
            width: 205mm;
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
            width: 205mm;
            min-height: 285mm;
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
            margin-bottom: 8px;
        }

        .header td {
            border: none;
            vertical-align: top;
        }

        .company {
            width: 30%;
            font-family: Arial, sans-serif;
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
            font-family: Arial, sans-serif;
        }

        .report-title {
            font-size: 18px;
            font-weight: normal;
            margin-top: 6px;
            line-height: 24px;
        }

        .right-info {
            width: 30%;
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 17px;
        }

        .print-date {
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin-bottom: 8px;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .detail-table th,
        .detail-table td {
            border: none;
            padding: 2px 3px;
            height: 18px;
            line-height: 13px;
            box-sizing: border-box;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            font-size: 10px;
        }

        .detail-table thead th {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            font-weight: normal;
            text-align: left;
        }

        .customer-row td {
            font-weight: bold;
            font-size: 11px;
            padding-top: 8px;
        }

        .item-row td {
            font-weight: bold;
            font-style: italic;
            font-size: 11px;
            padding-top: 5px;
        }

        .num {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .col-po {
            width: 16%;
        }

        .col-podate {
            width: 12%;
        }

        .col-dsdate {
            width: 12%;
        }

        .col-ds {
            width: 22%;
        }

        .col-qty {
    width: 10%;
    text-align: left;
}

        .no-data {
            font-family: Arial, sans-serif;
            font-size: 14px;
            text-align: center;
            margin-top: 70px;
            line-height: 24px;
        }

        @media print {
            html,
            body {
                width: 210mm;
                min-height: 297mm;
                background: #ffffff;
            }

            .filter-bar,
            .print-bar {
                display: none;
            }

            .page {
                width: 205mm;
                min-height: 285mm;
                margin: 0 auto;
                border: none;
                padding: 5mm;
                overflow: hidden;
            }
        }
    </style>
</head>

<body>

<div class="filter-bar">
    <form method="get" action="<?php echo h($selfFile); ?>" autocomplete="off">
        <input type="hidden" name="RUN" value="1">

        Start:
        <input type="date"
               id="START_DATE"
               name="START_DATE"
               class="filter-date"
               value="<?php echo h($start_input); ?>">

        End:
        <input type="date"
               id="END_DATE"
               name="END_DATE"
               class="filter-date"
               value="<?php echo h($end_input); ?>">

        Customer:
        <div class="autocomplete-wrap">
            <input type="text"
                   id="CUST_CODE"
                   name="CUST_CODE"
                   class="filter-cust"
                   value="<?php echo h($cust_code); ?>"
                   placeholder="Ketik customer / %">
            <div id="custSuggest" class="autocomplete-list"></div>
        </div>

        <button type="submit">FILTER</button>
        <button type="button" onclick="setAllCustomer()">ALL</button>
        <button type="button" onclick="exportExcel()">EXPORT EXCEL</button>
    </form>
</div>

<div class="print-bar">
    <button type="button" onclick="window.print()">PRINT</button>
    <button type="button" onclick="closeReport()">CLOSE</button>
</div>

<?php if (!$is_filter) { ?>
    <div class="page">
        <table class="header">
            <tr>
                <td class="company">
                    <div class="company-title">P.T. IMC TEKNO INDONESIA</div>
                    PPIC Departement
                </td>

                <td class="title-area">
                    <div class="report-title">
                        PO DELIVERY BALANCE<br>
                        DETAIL
                    </div>
                </td>

                <td class="right-info">
                    Page 0 of 0
                </td>
            </tr>
        </table>

        <div class="no-data">
            Data belum ditampilkan.<br><br>
            Isi Start, End, Customer lalu klik <b>FILTER</b>.<br>
            Klik <b>ALL</b> untuk semua customer.
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
                    PPIC Departement
                </td>

                <td class="title-area">
                    <div class="report-title">
                        PO DELIVERY BALANCE<br>
                        DETAIL
                    </div>
                </td>

                <td class="right-info">
                    Page <?php echo h($pageNo); ?> of <?php echo h($totalPages); ?>
                </td>
            </tr>
        </table>

        <div class="print-date">
            Print Date:
            &nbsp;
            <?php echo h(fmt_print_datetime()); ?>
        </div>

        <table class="detail-table">
            <thead>
                <tr>
                    <th class="col-po">PO #</th>
                    <th class="col-podate">PO Date</th>
                    <th class="col-dsdate">DS Date</th>
                    <th class="col-ds">DS #</th>
                    <th class="col-qty">PO.Qty</th>
                    <th class="col-qty">Del.Qty</th>
                    <th class="col-qty">PO.Bal</th>
                </tr>
            </thead>

            <tbody>
                <?php for ($i = 0; $i < count($pageRows); $i++) { ?>
                    <?php $r = $pageRows[$i]; ?>

                    <?php if ($r["ROW_TYPE"] == "CUSTOMER") { ?>
                        <tr class="customer-row">
                            <td colspan="7">
                                <?php echo h($r["CUST_CODE"]); ?>
                                &nbsp;
                                <?php echo h($r["CUST_COMP"]); ?>
                            </td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "ITEM") { ?>
                        <tr class="item-row">
                            <td colspan="7">
                                <?php echo h($r["PART_NUM"]); ?>
                                &nbsp;
                                <?php echo h($r["PART_NAME"]); ?>
                                <?php if ($r["PART_NO"] != "") { ?>
                                    &nbsp;
                                    <?php echo h($r["PART_NO"]); ?>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "DETAIL") { ?>
                        <tr>
                            <td class="col-po">
                                <?php echo $r["SHOW_PO"] == 1 ? h($r["ORDR_PO"]) : ""; ?>
                            </td>

                            <td class="col-podate">
                                <?php echo $r["SHOW_PO"] == 1 ? h(fmt_date($r["ORDR_DATE"])) : ""; ?>
                            </td>

                            <td class="col-dsdate">
                                <?php echo h(fmt_date($r["DI_DATE"])); ?>
                            </td>

                            <td class="col-ds">
                                <?php echo h($r["DI_DSNO"]); ?>
                            </td>

                           <td class="col-qty">
    <?php echo $r["SHOW_PO"] == 1 ? h(fmt_num($r["ORDP_QTY"], 0)) : ""; ?>
</td>

<td class="col-qty">
    <?php echo h(fmt_num($r["DEL_QTY"], 0)); ?>
</td>

<td class="col-qty">
    <?php echo h(fmt_bal($r["PO_BAL"])); ?>
</td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "EMPTY") { ?>
                        <tr>
                            <td colspan="7" class="center">
                                <?php echo h($r["MESSAGE"]); ?>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
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
        .replace(/"/g, "&quot;");
}

function closeReport() {
    try {
        if (window.parent && window.parent !== window) {
            window.location.href = "dashboard_home.php";
            return;
        }
    } catch (e) {
    }

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

    if (startDate == "") {
        alert("Start Date belum diisi.");
        document.getElementById("START_DATE").focus();
        return;
    }

    if (endDate == "") {
        alert("End Date belum diisi.");
        document.getElementById("END_DATE").focus();
        return;
    }

    if (custCode == "") {
        alert("Customer belum diisi. Isi kode customer atau % untuk semua.");
        document.getElementById("CUST_CODE").focus();
        return;
    }

    window.location =
        "po_delivery_balance_detail_export_excel.php" +
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

document.getElementById("CUST_CODE").onblur = function () {
    setTimeout(function () {
        hideSuggest();
    }, 250);
};
</script>

</body>
</html>