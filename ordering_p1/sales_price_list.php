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

$is_filter = isset($_GET["CUST_CODE"]) || isset($_POST["CUST_CODE"]);

$cust_code = get_param("CUST_CODE", "");

if ($is_filter && $cust_code == "") {
    $cust_code = "%";
}

$dataRows = array();
$printRows = array();
$pages = array();
$totalPages = 0;
$rowsPerPage = 38;

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
    <title>Sales Price History</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 6mm;
        }

        body {
            margin: 0;
            background: #9a9a9a;
            font-family: "Courier New", monospace;
            font-size: 10px;
            color: #000000;
        }

        .filter-bar {
            width: 200mm;
            margin: 8px auto;
            background: #d4d0c8;
            border: 1px solid #666666;
            padding: 6px;
            box-sizing: border-box;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }

        .filter-bar input {
            height: 22px;
            border: 1px solid #777777;
            font-size: 12px;
            padding: 2px 4px;
            width: 130px;
            box-sizing: border-box;
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
            width: 420px;
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
            width: 200mm;
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
            width: 200mm;
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
            margin-bottom: 20px;
        }

        .header td {
            border: none;
            vertical-align: top;
        }

        .company {
            width: 32%;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 13px;
        }

        .company-title {
            font-size: 15px;
            font-weight: normal;
        }

        .title-area {
            width: 38%;
            text-align: center;
            font-family: Arial, sans-serif;
        }

        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 4px;
        }

        .filter-title {
            font-size: 11px;
            margin-top: 8px;
            font-weight: normal;
        }

        .right-info {
            width: 30%;
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 17px;
        }

        .print-date {
            margin-top: 18px;
        }

        .price-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .price-table th {
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
            border-left: none;
            border-right: none;
            padding: 4px 2px;
            height: 22px;
            font-weight: normal;
            text-align: left;
            box-sizing: border-box;
            font-size: 10px;
            overflow: hidden;
            white-space: nowrap;
        }

        .price-table td {
            padding: 2px 2px;
            height: 17px;
            line-height: 13px;
            box-sizing: border-box;
            vertical-align: top;
            white-space: nowrap;
            overflow: hidden;
            font-size: 10px;
        }

        /*
            PERBAIKAN KEPOTONG:
            Sebelumnya PART 62% dan Quot 7%.
            Sekarang kolom kanan digeser ke kiri dan Quot dibuat lebih lebar.
            Total = 100%.
        */
        .col-part {
            width: 48%;
        }

        .col-price {
            width: 11%;
            text-align: right;
        }

        .col-start {
            width: 13%;
        }

        .col-end {
            width: 13%;
        }

        .col-quot {
            width: 15%;
        }

        .customer-row td {
            height: 22px;
            font-weight: bold;
            font-size: 11px;
            padding-top: 6px;
            letter-spacing: 1px;
        }

        .part-label {
            letter-spacing: 8px;
            font-weight: normal;
        }

        .item-row td {
            padding-top: 4px;
            height: 19px;
        }

        .price-row td {
            height: 17px;
        }

        .price-row .col-price {
            text-align: right;
        }

        .price-row .col-start,
        .price-row .col-end,
        .price-row .col-quot {
            text-align: left;
        }

        .dash-row td {
            border-bottom: 1px dashed #000000;
            height: 6px;
            padding: 0;
        }

        .empty-row td {
            height: 18px;
        }

        .num {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        @media print {
            html,
            body {
                width: 210mm;
                height: 297mm;
                background: #ffffff;
            }

            .filter-bar,
            .print-bar {
                display: none;
            }

            .page {
                width: 200mm;
                min-height: 285mm;
                margin: 0 auto;
                border: none;
                padding: 5mm;
                overflow: hidden;
            }

            .price-table th,
            .price-table td {
                font-size: 10px;
                padding-left: 2px;
                padding-right: 2px;
            }
        }
    </style>
</head>

<body>

<div class="filter-bar">
    <form method="get" action="<?php echo h($selfFile); ?>" autocomplete="off">
        Customer:
        <div class="autocomplete-wrap">
            <input type="text" id="CUST_CODE" name="CUST_CODE" value="<?php echo h($cust_code); ?>" placeholder="Ketik customer / % untuk semua">
            <div id="custSuggest" class="autocomplete-list"></div>
        </div>

        <button type="submit">FILTER</button>
        <button type="button" onclick="setAllCustomer()">ALL</button>
    </form>
</div>

<div class="print-bar">
    <button type="button" onclick="window.print()">PRINT</button>
    <button type="button" onclick="window.close()">CLOSE</button>
</div>

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
                            </td>
                        </tr>
                    <?php } elseif ($r["ROW_TYPE"] == "ITEM") { ?>
                        <tr class="item-row">
                            <td class="col-part">
                                [<?php echo h($r["ITEM_CODE"]); ?>]
                                [<?php echo h($r["ITEM_NO"]); ?>]
                                [<?php echo h($r["ITEM_NAME"]); ?>]
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

                <?php
                    $fillCount = $rowsPerPage - count($pageRows);

                    if ($fillCount < 0) {
                        $fillCount = 0;
                    }
                ?>

                <?php for ($e = 0; $e < $fillCount; $e++) { ?>
                    <tr class="empty-row">
                        <td>&nbsp;</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
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
    document.forms[0].submit();
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

        document.forms[0].submit();
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

</body>
</html>