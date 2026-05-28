<?php
require_once __DIR__ . "/../config/db_plant2.php";

if ($conn === false) {
    header("Location: login.php?error=session_expired");
    exit();
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$today = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$dbUser = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manual Delivery Instruction</title>

    <style>
        body {
            margin: 0;
            padding: 0;
            background: #d4d0c8;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            color: #000000;
        }

        .topbar {
            background: #000080;
            color: #ffffff;
            padding: 7px 10px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar a {
            color: #ffffff;
            text-decoration: none;
            margin-left: 12px;
        }

        .main-window {
            width: 1280px;
            max-width: calc(100% - 20px);
            margin: 10px auto;
            border: 2px solid #808080;
            background: #d4d0c8;
            padding: 8px;
            box-sizing: border-box;
        }

        .title {
            font-weight: bold;
            margin-bottom: 8px;
        }

        .readonly-info {
            background: #eeeeee;
            border: 1px solid #808080;
            padding: 5px;
            margin-bottom: 8px;
        }

        .search-area {
            display: grid;
            grid-template-columns: 70px 90px 300px 80px 1fr;
            gap: 4px;
            align-items: center;
            margin-bottom: 8px;
        }

        .top-area {
            display: grid;
            grid-template-columns: 80px 150px 80px 150px 70px 150px 80px 150px;
            gap: 4px;
            align-items: center;
            margin-bottom: 8px;
        }

        label {
            white-space: nowrap;
        }

        input,
        select {
            height: 22px;
            border: 1px solid #808080;
            background: #ffffff;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            padding: 1px 3px;
            box-sizing: border-box;
            width: 100%;
        }

        input[readonly] {
            background: #eeeeee;
        }

        button,
        .btn-link {
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            background: #d4d0c8;
            border: 2px outset #ffffff;
            padding: 4px 12px;
            cursor: pointer;
            color: #000000;
            text-decoration: none;
            display: inline-block;
        }

        button:active,
        .btn-link:active {
            border: 2px inset #ffffff;
        }

        .button-row {
            text-align: right;
            margin: 8px 0;
        }

        .status-text {
            margin: 5px 0;
            color: #000080;
            min-height: 18px;
        }

        .autocomplete-wrap {
            position: relative;
            width: 100%;
        }

        .autocomplete-list {
            display: none;
            position: absolute;
            z-index: 9999;
            top: 22px;
            left: 0;
            right: 0;
            max-height: 140px;
            overflow-y: auto;
            background: #c6d8e8;
            border: 1px solid #808080;
            box-shadow: 2px 2px 3px #808080;
        }

        .autocomplete-item {
            padding: 4px;
            cursor: pointer;
            border-bottom: 1px solid #808080;
            line-height: 16px;
            background: #c6d8e8;
            color: #000000;
        }

        .autocomplete-item:hover {
            background: #316ac5;
            color: #ffffff;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: #c6d8e8;
        }

        .grid th {
            background: #d4d0c8;
            border: 1px solid #808080;
            height: 22px;
            font-weight: normal;
            text-align: left;
            padding-left: 3px;
        }

        .grid td {
            border: 1px solid #808080;
            height: 22px;
            padding: 0;
        }

        .grid input,
        .grid select {
            border: none;
            background: transparent;
            height: 22px;
        }

        .grid tr:nth-child(even) {
            background: #b7cfe2;
        }

        .grid tr.selected {
            background: #316ac5;
            color: #ffffff;
        }

        .grid tr.selected input,
        .grid tr.selected select {
            color: #ffffff;
        }

        .grid .autocomplete-wrap {
            position: relative;
            width: 100%;
            height: 22px;
        }

        .grid .autocomplete-list {
            top: 22px;
            min-width: 180px;
            right: auto;
            z-index: 99999;
            background: #c6d8e8;
        }

        .grid .autocomplete-item {
            background: #c6d8e8;
        }

        .grid .autocomplete-item:hover {
            background: #316ac5;
            color: #ffffff;
        }

        .pack-desc-input {
            width: 100%;
        }

        .middle-title {
            margin-top: 6px;
            margin-bottom: 3px;
            font-weight: bold;
        }

        .bottom-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-top: 10px;
        }

        .panel {
            border: 1px solid #808080;
            padding: 5px;
            background: #d4d0c8;
        }

        .panel-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .small-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: #ffffff;
        }

        .small-grid th {
            background: #d4d0c8;
            border: 1px solid #808080;
            height: 22px;
            font-weight: normal;
            text-align: left;
            padding-left: 3px;
        }

        .small-grid td {
            border: 1px solid #808080;
            height: 22px;
            padding: 2px;
            background: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .small-grid tr.selected td {
            background: #316ac5;
            color: #ffffff;
        }

        .footer-row {
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .progress-box {
            width: 300px;
        }

        .progress {
            width: 100%;
            height: 16px;
        }

        .report-area {
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px solid #808080;
            display: grid;
            grid-template-columns: 80px 120px 80px 120px 80px 160px 1fr;
            gap: 4px;
            align-items: center;
        }


        .report-menu-wrap {
            position: relative;
            display: inline-block;
        }

        .report-menu-popup {
            display: none;
            position: absolute;
            right: 0;
            top: 28px;
            width: 260px;
            background: #d4d0c8;
            border: 2px outset #ffffff;
            z-index: 999999;
            text-align: left;
            padding: 2px;
            box-shadow: 2px 2px 4px #808080;
        }

        .report-menu-item {
            padding: 5px 12px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            color: #000000;
            cursor: pointer;
            background: #d4d0c8;
        }

        .report-menu-item:hover {
            background: #316ac5;
            color: #ffffff;
        }
		.autocomplete-item.active {
    background: #316ac5;
    color: #ffffff;
}

.grid .autocomplete-item.active {
    background: #316ac5;
    color: #ffffff;
}
    </style>
</head>

<body>

<div class="topbar">
    <div>ORDERING SYSTEM - PLANT 2</div>
    <div>
        User: <?php echo h($dbUser); ?>
        <a href="dashboard.php">Dashboard</a>
        <a href="login.php?logout=1">Logout</a>
    </div>
</div>

<div class="main-window">

    <div class="title">MANUAL DELIVERY INSTRUCTION</div>

    <div class="readonly-info">
        Server: <b>192.168.0.9</b> |
        Database: <b>msdata</b> |
        Status: <b>Connected</b>
    </div>

    <div class="search-area">
        <label>CARI DI :</label>

        <select id="SEARCH_TYPE">
            <option value="DI_NO">DI NO</option>
            <option value="DI_INVNO">INV NO</option>
        </select>

        <div class="autocomplete-wrap">
            <input type="text" id="SEARCH_KEYWORD" placeholder="Ketik DI NO / INV NO" autocomplete="off">
            <div id="diSuggest" class="autocomplete-list"></div>
        </div>

        <button type="button" id="btnSearchDI">CARI</button>

        <span id="SearchStatus"></span>
    </div>

    <form method="post" action="#" id="frmManualOrder">

        <div class="top-area">

            <label>DI ID :</label>
            <input type="text" name="DI_ID" id="DI_ID" readonly>

            <label>DI NO :</label>
            <input type="text" name="DI_NO" id="DI_NO" maxlength="7">

            <label>START :</label>
            <input type="date" name="DI_START_DATE" id="DI_START_DATE" value="<?php echo h($firstDayOfMonth); ?>">

            <label>DI DATE :</label>
            <input type="date" name="DI_DATE" id="DI_DATE" value="<?php echo h($today); ?>">

            <label>CUST CODE :</label>
            <div class="autocomplete-wrap">
                <input type="text" id="CUST_SEARCH" placeholder="Ketik kode/nama customer" autocomplete="off">
                <input type="hidden" name="CUST_CODE" id="CUST_CODE">
                <div id="custSuggest" class="autocomplete-list"></div>
            </div>

            <label>CUST NAME :</label>
            <input type="text" name="CUST_COMP" id="CUST_COMP" readonly>

            <label>ABBR :</label>
            <input type="text" name="CUST_ABBR" id="CUST_ABBR" readonly>

            <label>CUST ID :</label>
            <input type="text" name="CUST_ID" id="CUST_ID" readonly>

            <label>DS NO :</label>
            <input type="text" name="DI_DSNO" id="DI_DSNO">

            <label>INV NO :</label>
            <input type="text" name="DI_INVNO" id="DI_INVNO">

            <label>ORDER NO :</label>
            <input type="text" name="DI_ORDERNO" id="DI_ORDERNO">

        </div>

        <div class="button-row">
            <button type="button" id="btnNew">NEW</button>
            <button type="button" id="btnSaveHeader">SAVE HEADER</button>
			<button type="button" id="btnDeleteHeader">DELETE HEADER</button>
            <button type="button" id="btnLoadOther">LOAD DI OTHER</button>
            <button type="button" id="btnLoadStaging">LOAD KOITO / STAGING</button>
            <button type="button" id="btnPostFifo">POST FIFO</button>
            <span class="report-menu-wrap">
                <button type="button" id="btnReport">REPORT MENU</button>
                <div id="reportMenuPopup" class="report-menu-popup">
                    <div class="report-menu-item" data-report="INVOICE_PO">INVOICE PO</div>
                    <div class="report-menu-item" data-report="DELIVERY_SHEET">DELIVERY SHEET</div>
                    <div class="report-menu-item" data-report="DELIVERY_SHEET_PO">DELIVERY SHEET PO</div>
                    <div class="report-menu-item" data-report="DELIVERY_SHEET_CABININDO">DELIVERY SHEET CABININDO</div>
                    <div class="report-menu-item" data-report="DS_TOYODENSO">DS TOYODENSO</div>
                    <div class="report-menu-item" data-report="INVOICE_RATE_HIROSE">INVOICE RATE HIROSE</div>
                    <div class="report-menu-item" data-report="INVOICE_HILEX">INVOICE HILEX</div>
                    <div class="report-menu-item" data-report="PACKING_LIST">PACKING LIST</div>
                </div>
            </span>
        </div>

        <div class="status-text" id="LabelStatus">
            # NO TIDAK BOLEH DI RUBAH
        </div>

        <div class="middle-title">DETAIL DI PART</div>

        <table class="grid" id="tblPart">
            <thead>
                <tr>
                    <th style="width:50px;"># NO</th>
                    <th style="width:130px;">CODE</th>
                    <th style="width:420px;">NAME</th>
                    <th style="width:90px;">QTY</th>
                    <th style="width:90px;">PACK QTY</th>
                    <th style="width:100px;">PACK DESC</th>
                    <th style="width:150px;">LOCATION</th>
                    <th style="width:90px;">PRICE ID</th>
                    <th style="width:80px;">PACK ID</th>
                    <th style="width:50px; display:none;">DEL</th>
                </tr>
            </thead>

            <tbody id="partBody">
                <?php for ($i = 1; $i <= 10; $i++) { ?>
                <tr onclick="selectPartRow(this)">
                    <td><input type="text" name="DIPA_LINO[]" value="<?php echo $i; ?>" readonly></td>
                    <td>
                        <div class="autocomplete-wrap">
                            <input type="text" name="CODE[]" class="part-code-input" autocomplete="off">
                            <div class="partSuggest autocomplete-list"></div>
                        </div>
                    </td>
                    <td><input type="text" name="NAME[]"></td>
                    <td><input type="text" name="DIPA_QTY[]"></td>
                    <td><input type="text" name="DIPA_PQTY[]"></td>
                    <td>
                        <div class="autocomplete-wrap">
                            <input type="text" name="PACK_DESC[]" class="pack-desc-input" autocomplete="off">
                            <div class="packSuggest autocomplete-list"></div>
                        </div>
                    </td>
                    <td><input type="text" name="LOCATION[]"></td>
                    <td><input type="text" name="PRICE_ID[]"></td>
                    <td><input type="text" name="PACK_ID[]"></td>
                    <td style="display:none;"><button type="button" onclick="deleteRow(event, this)">X</button></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>

        <div class="button-row">
            <button type="button" id="btnAddRow">TAMBAH BARIS</button>
            <button type="button" id="btnDeleteLine">HAPUS PER BARIS</button>
            <button type="button" id="btnDeleteAll">BATAL SEMUA DETAIL</button>
            <button type="button" id="btnSaveDetail" style="display:none;">SAVE DETAIL</button>
        </div>

        <div class="bottom-area">

            <div class="panel">
                <div class="panel-title">ORDER / PO AVAILABLE</div>

                <table class="small-grid" id="tblOrder">
                    <thead>
                        <tr>
                            <th style="width:90px;">ORDR_DATE</th>
                            <th style="width:240px;">ORDR_PO</th>
                            <th style="width:80px;">QTY</th>
                            <th style="width:80px;">DQTY</th>
                            <th style="width:80px;">BQTY</th>
                        </tr>
                    </thead>
                    <tbody id="orderBody">
                        <tr>
                            <td colspan="5">Belum ada data.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="button-row">
                    <button type="button" id="btnAllocatePO">ALLOCATE PO</button>
                </div>
            </div>

            <div class="panel">
                <div class="panel-title">PO TERAMBIL / ALLOCATED</div>

                <table class="small-grid" id="tblPO">
                    <thead>
                        <tr>
                            <th style="width:90px;">ORDR_DATE</th>
                            <th style="width:260px;">PO NUMBER</th>
                            <th style="width:80px;">QTY</th>
                        </tr>
                    </thead>
                    <tbody id="poBody">
                        <tr>
                            <td colspan="3">Belum ada data.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="button-row">
                    <button type="button" id="btnRollbackPO">ROLLBACK PO</button>
                </div>
            </div>

        </div>

        <div class="report-area">

            <label>START :</label>
            <input type="date" name="REPORT_START_DATE" id="REPORT_START_DATE" value="<?php echo h($firstDayOfMonth); ?>">

            <label>END :</label>
            <input type="date" name="REPORT_END_DATE" id="REPORT_END_DATE" value="<?php echo h($tomorrow); ?>">

           <label>CUSTOMER :</label>
<div class="autocomplete-wrap">
    <input type="text" name="REPORT_CUST_CODE" id="REPORT_CUST_CODE" value="%" autocomplete="off" placeholder="Ketik kode/nama customer">
    <div id="reportCustSuggest" class="autocomplete-list"></div>
</div>

<button type="button" id="btnReportDI">PRINT DELIVERY INSTRUCTION</button>

        </div>

        <div class="footer-row">

            <div>
                <button type="button" id="btnorder">INPUT ORDER</button>
				<button type="button" id="btnForecast">FORECAST</button>
                <button type="button" id="btnSchedule">SCHEDULE</button>
                <button type="button" id="btnEditOrder">EDIT ORDER</button>
            </div>

            <div class="progress-box">
                <progress class="progress" id="ProgressBar1" value="0" max="100"></progress>
            </div>

        </div>

    </form>

</div>

<script>
var selectedPartRow = null;
var selectedOrderRow = null;
var selectedPORow = null;

var custItems = [];
var diItems = [];
var packItems = [];

var reportCustItems = [];
var reportCustIndex = -1;

function ajaxPost(url, data, callback) {
    var xhr = new XMLHttpRequest();

    xhr.open("POST", url, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4) {
            callback(xhr.status, xhr.responseText);
        }
    };

    xhr.send(data);
}

function enc(value) {
    return encodeURIComponent(value == null ? "" : value);
}

function htmlEncode(value) {
    value = value == null ? "" : String(value);

    return value
        .replace(/&/g, "&amp;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
}

function getRowFromElement(el) {
    var row = el;

    while (row && row.tagName != "TR") {
        row = row.parentNode;
    }

    return row;
}

function getCellFromElement(el) {
    var cell = el;

    while (cell && cell.tagName != "TD") {
        cell = cell.parentNode;
    }

    return cell;
}

function getInputByNameFromRow(row, inputName) {
    var inputs = row.getElementsByTagName("input");

    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].name == inputName) {
            return inputs[i];
        }
    }

    return null;
}

function setCustomer(c) {
    document.getElementById("CUST_CODE").value = c.CUST_CODE;
    document.getElementById("CUST_SEARCH").value = c.CUST_CODE;
    document.getElementById("CUST_ID").value = c.CUST_ID;
    document.getElementById("CUST_COMP").value = c.CUST_COMP;
    document.getElementById("CUST_ABBR").value = c.CUST_ABBR;
    document.getElementById("REPORT_CUST_CODE").value = c.CUST_CODE;

    document.getElementById("custSuggest").style.display = "none";
}

function renderCustomerSuggest(items) {
    var box = document.getElementById("custSuggest");
    box.innerHTML = "";

    custItems = items;

    if (!items || items.length == 0) {
        box.style.display = "none";
        return;
    }

    for (var i = 0; i < items.length; i++) {
        var div = document.createElement("div");
        div.className = "autocomplete-item";
        div.setAttribute("data-index", i);

        div.innerHTML =
            htmlEncode(items[i].CUST_CODE) +
            " - " +
            htmlEncode(items[i].CUST_COMP);

        div.onclick = function () {
            var idx = parseInt(this.getAttribute("data-index"), 10);
            setCustomer(custItems[idx]);
        };

        box.appendChild(div);
    }

    box.style.display = "block";
}

document.getElementById("CUST_SEARCH").onkeyup = function (e) {
    e = e || window.event;

    var key = e.keyCode || e.which;
    var q = this.value;

    if (key == 13 && custItems.length > 0) {
        setCustomer(custItems[0]);
        return;
    }

    document.getElementById("CUST_CODE").value = "";
    document.getElementById("CUST_ID").value = "";
    document.getElementById("CUST_COMP").value = "";
    document.getElementById("CUST_ABBR").value = "";

    if (q.length < 1) {
        document.getElementById("custSuggest").style.display = "none";
        return;
    }

    ajaxPost("ajax_customer_autocomplete.php", "q=" + enc(q), function (status, responseText) {
        if (status != 200) {
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            return;
        }

        renderCustomerSuggest(result);
    });
};

document.getElementById("CUST_SEARCH").onblur = function () {
    setTimeout(function () {
        document.getElementById("custSuggest").style.display = "none";
    }, 250);
};

function hideReportCustSuggest() {
    var box = document.getElementById("reportCustSuggest");

    if (box) {
        box.style.display = "none";
        box.innerHTML = "";
    }

    reportCustItems = [];
    reportCustIndex = -1;
}

function setActiveReportCust(index) {
    var box = document.getElementById("reportCustSuggest");
    var items = box.getElementsByClassName("autocomplete-item");

    if (!items || items.length == 0) {
        reportCustIndex = -1;
        return;
    }

    if (index < 0) {
        index = items.length - 1;
    }

    if (index >= items.length) {
        index = 0;
    }

    for (var i = 0; i < items.length; i++) {
        items[i].style.background = "#c6d8e8";
        items[i].style.color = "#000000";
    }

    items[index].style.background = "#316ac5";
    items[index].style.color = "#ffffff";

    reportCustIndex = index;
}

function setReportCustomer(c) {
    document.getElementById("REPORT_CUST_CODE").value = c.CUST_CODE;
    hideReportCustSuggest();
}

function renderReportCustomerSuggest(items) {
    var box = document.getElementById("reportCustSuggest");
    box.innerHTML = "";

    reportCustItems = items || [];
    reportCustIndex = -1;

    if (!items || items.length == 0) {
        box.style.display = "none";
        return;
    }

    for (var i = 0; i < items.length; i++) {
        var div = document.createElement("div");
        div.className = "autocomplete-item";
        div.setAttribute("data-index", i);

        div.innerHTML =
            htmlEncode(items[i].CUST_CODE) +
            " - " +
            htmlEncode(items[i].CUST_COMP);

        div.onmouseover = function () {
            setActiveReportCust(parseInt(this.getAttribute("data-index"), 10));
        };

        div.onmousedown = function (e) {
            if (!e) {
                e = window.event;
            }

            if (e.preventDefault) {
                e.preventDefault();
            }

            var idx = parseInt(this.getAttribute("data-index"), 10);
            setReportCustomer(reportCustItems[idx]);
        };

        box.appendChild(div);
    }

    box.style.display = "block";
    setActiveReportCust(0);
}

document.getElementById("REPORT_CUST_CODE").onkeyup = function (e) {
    e = e || window.event;

    var key = e.keyCode || e.which;
    var q = this.value;

    if (key == 40) {
        setActiveReportCust(reportCustIndex + 1);
        return;
    }

    if (key == 38) {
        setActiveReportCust(reportCustIndex - 1);
        return;
    }

    if (key == 13) {
        if (reportCustItems.length > 0) {
            if (reportCustIndex < 0) {
                reportCustIndex = 0;
            }

            setReportCustomer(reportCustItems[reportCustIndex]);
        }

        return;
    }

    if (q.length < 1 || q == "%") {
        hideReportCustSuggest();
        return;
    }

    ajaxPost("ajax_customer_autocomplete.php", "q=" + enc(q), function (status, responseText) {
        if (status != 200) {
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            return;
        }

        renderReportCustomerSuggest(result);
    });
};

document.getElementById("REPORT_CUST_CODE").onblur = function () {
    setTimeout(function () {
        hideReportCustSuggest();
    }, 250);
};


function renderDISuggest(items) {
    var box = document.getElementById("diSuggest");
    box.innerHTML = "";

    diItems = items;

    if (!items || items.length == 0) {
        box.style.display = "none";
        return;
    }

    for (var i = 0; i < items.length; i++) {
        var div = document.createElement("div");
        div.className = "autocomplete-item";
        div.setAttribute("data-index", i);

        div.innerHTML =
            htmlEncode(items[i].DI_NO) +
            " - " +
            htmlEncode(items[i].DI_INVNO) +
            "<br>" +
            htmlEncode(items[i].CUST_CODE) +
            " - " +
            htmlEncode(items[i].CUST_COMP);

        div.onclick = function () {
            var idx = parseInt(this.getAttribute("data-index"), 10);
            var item = diItems[idx];

            if (document.getElementById("SEARCH_TYPE").value == "DI_INVNO") {
                document.getElementById("SEARCH_KEYWORD").value = item.DI_INVNO;
            } else {
                document.getElementById("SEARCH_KEYWORD").value = item.DI_NO;
            }

            document.getElementById("diSuggest").style.display = "none";
            document.getElementById("btnSearchDI").click();
        };

        box.appendChild(div);
    }

    box.style.display = "block";
}

document.getElementById("SEARCH_KEYWORD").onkeyup = function (e) {
    e = e || window.event;

    var key = e.keyCode || e.which;
    var q = this.value;
    var searchType = document.getElementById("SEARCH_TYPE").value;

    if (key == 13) {
        document.getElementById("diSuggest").style.display = "none";
        document.getElementById("btnSearchDI").click();
        return;
    }

    if (q.length < 1) {
        document.getElementById("diSuggest").style.display = "none";
        return;
    }

    var data = [];
    data.push("search_type=" + enc(searchType));
    data.push("q=" + enc(q));

    ajaxPost("ajax_di_autocomplete.php", data.join("&"), function (status, responseText) {
        if (status != 200) {
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            return;
        }

        renderDISuggest(result);
    });
};

document.getElementById("SEARCH_KEYWORD").onblur = function () {
    setTimeout(function () {
        document.getElementById("diSuggest").style.display = "none";
    }, 250);
};

document.getElementById("SEARCH_TYPE").onchange = function () {
    document.getElementById("SEARCH_KEYWORD").value = "";
    document.getElementById("diSuggest").style.display = "none";
};

function selectPartRow(row) {
    var rows = document.getElementById("partBody").getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        rows[i].className = "";
    }

    row.className = "selected";
    selectedPartRow = row;

    loadBottomPOGrids(row);
}

function addRow() {
    var tbody = document.getElementById("partBody");
    var no = tbody.rows.length + 1;
    var row = tbody.insertRow(-1);

    row.setAttribute("onclick", "selectPartRow(this)");

    row.innerHTML =
        '<td><input type="text" name="DIPA_LINO[]" value="' + no + '" readonly></td>' +
        '<td>' +
        '<div class="autocomplete-wrap">' +
            '<input type="text" name="CODE[]" class="part-code-input" autocomplete="off">' +
            '<div class="partSuggest autocomplete-list"></div>' +
        '</div>' +
        '</td>' +
        '<td><input type="text" name="NAME[]"></td>' +
        '<td><input type="text" name="DIPA_QTY[]"></td>' +
        '<td><input type="text" name="DIPA_PQTY[]"></td>' +
        '<td>' +
            '<div class="autocomplete-wrap">' +
                '<input type="text" name="PACK_DESC[]" class="pack-desc-input" autocomplete="off">' +
                '<div class="packSuggest autocomplete-list"></div>' +
            '</div>' +
        '</td>' +
        '<td><input type="text" name="LOCATION[]"></td>' +
        '<td><input type="text" name="PRICE_ID[]"></td>' +
        '<td><input type="text" name="PACK_ID[]"></td>' +
        '<td style="display:none;"><button type="button" onclick="deleteRow(event, this)">X</button></td>';
}

function addPartRowFromData(r) {
    var tbody = document.getElementById("partBody");
    var row = tbody.insertRow(-1);

    row.setAttribute("onclick", "selectPartRow(this)");

    row.innerHTML =
        '<td><input type="text" name="DIPA_LINO[]" value="' + htmlEncode(r.DIPA_LINO) + '" readonly></td>' +
        '<td>' +
        '<div class="autocomplete-wrap">' +
            '<input type="text" name="CODE[]" class="part-code-input" autocomplete="off" value="' + htmlEncode(r.CODE) + '">' +
            '<div class="partSuggest autocomplete-list"></div>' +
        '</div>' +
        '</td>' +
        '<td><input type="text" name="NAME[]" value="' + htmlEncode(r.NAME) + '"></td>' +
        '<td><input type="text" name="DIPA_QTY[]" value="' + htmlEncode(r.DIPA_QTY) + '"></td>' +
        '<td><input type="text" name="DIPA_PQTY[]" value="' + htmlEncode(r.DIPA_PQTY) + '"></td>' +
        '<td>' +
            '<div class="autocomplete-wrap">' +
                '<input type="text" name="PACK_DESC[]" class="pack-desc-input" autocomplete="off" value="' + htmlEncode(r.PACK_DESC) + '">' +
                '<div class="packSuggest autocomplete-list"></div>' +
            '</div>' +
        '</td>' +
        '<td><input type="text" name="LOCATION[]" value="' + htmlEncode(r.LOCATION) + '"></td>' +
        '<td><input type="text" name="PRICE_ID[]" value="' + htmlEncode(r.PRICE_ID) + '"></td>' +
        '<td><input type="text" name="PACK_ID[]" value="' + htmlEncode(r.PACK_ID) + '"></td>' +
        '<td style="display:none;"><button type="button" onclick="deleteRow(event, this)">X</button></td>';
}

function deleteRow(e, btn) {
    if (!e) {
        e = window.event;
    }

    if (e.stopPropagation) {
        e.stopPropagation();
    } else {
        e.cancelBubble = true;
    }

    var row = btn.parentNode.parentNode;
    selectPartRow(row);
    deleteSelectedDetailRow(row);
}


function renumberRows() {
    var tbody = document.getElementById("partBody");
    var rows = tbody.getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        var inputNo = rows[i].cells[0].getElementsByTagName("input")[0];
        inputNo.value = i + 1;
    }
}

function clearPartGrid() {
    var tbody = document.getElementById("partBody");

    while (tbody.rows.length > 0) {
        tbody.deleteRow(0);
    }

    selectedPartRow = null;
}

function fillDefaultEmptyRows(totalRows) {
    for (var i = 0; i < totalRows; i++) {
        addRow();
    }
}

function resetSmallGrids() {
    clearPOGrids();
}

function getHeaderPostData() {
    var data = [];

    data.push("DI_ID=" + enc(document.getElementById("DI_ID").value));
    data.push("DI_NO=" + enc(document.getElementById("DI_NO").value));
    data.push("DI_START_DATE=" + enc(document.getElementById("DI_START_DATE").value));
    data.push("DI_DATE=" + enc(document.getElementById("DI_DATE").value));
    data.push("CUST_CODE=" + enc(document.getElementById("CUST_CODE").value));

    /*
        Manual edit header
    */
    data.push("DI_DSNO=" + enc(document.getElementById("DI_DSNO").value));
    data.push("DI_INVNO=" + enc(document.getElementById("DI_INVNO").value));
    data.push("DI_ORDERNO=" + enc(document.getElementById("DI_ORDERNO").value));

    return data.join("&");
}

function getLoadOtherPostData() {
    var data = [];

    data.push("DI_ID=" + enc(document.getElementById("DI_ID").value));
    data.push("CUST_CODE=" + enc(document.getElementById("CUST_CODE").value));
    data.push("START_DATE=" + enc(document.getElementById("DI_START_DATE").value));
    data.push("END_DATE=" + enc(document.getElementById("DI_DATE").value));

    return data.join("&");
}

function fillHeaderFromSearch(h) {
    var custCode = "";

    if (h.CUST_CODE) {
        custCode = h.CUST_CODE;
    }

    document.getElementById("DI_ID").value = h.DI_ID || "";
    document.getElementById("DI_NO").value = h.DI_NO || "";
    document.getElementById("DI_START_DATE").value = h.DI_START_DATE || "";
    document.getElementById("DI_DATE").value = h.DI_DATE || "";
    document.getElementById("DI_DSNO").value = h.DI_DSNO || "";
    document.getElementById("DI_INVNO").value = h.DI_INVNO || "";
    document.getElementById("DI_ORDERNO").value = h.DI_ORDERNO || "";

    document.getElementById("CUST_CODE").value = custCode;
    document.getElementById("CUST_SEARCH").value = custCode;

    document.getElementById("CUST_ID").value = h.CUST_ID || "";
    document.getElementById("CUST_COMP").value = h.CUST_COMP || "";
    document.getElementById("CUST_ABBR").value = h.CUST_ABBR || "";
    document.getElementById("REPORT_CUST_CODE").value = custCode;
}

function fillDetailFromSearch(details) {
    clearPartGrid();

    if (!details || details.length == 0) {
        fillDefaultEmptyRows(10);
        return;
    }

    for (var i = 0; i < details.length; i++) {
        addPartRowFromData(details[i]);
    }

    var rows = document.getElementById("partBody").getElementsByTagName("tr");
    if (rows.length > 0) {
        selectPartRow(rows[0]);
    }
}

document.getElementById("btnNew").onclick = function () {
    document.getElementById("DI_ID").value = "";
    document.getElementById("DI_NO").value = "";
    document.getElementById("DI_DSNO").value = "";
    document.getElementById("DI_INVNO").value = "";
    document.getElementById("DI_ORDERNO").value = "";

    document.getElementById("CUST_CODE").value = "";
    document.getElementById("CUST_SEARCH").value = "";
    document.getElementById("CUST_ID").value = "";
    document.getElementById("CUST_COMP").value = "";
    document.getElementById("CUST_ABBR").value = "";
    document.getElementById("REPORT_CUST_CODE").value = "%";

    document.getElementById("SEARCH_KEYWORD").value = "";
    document.getElementById("SearchStatus").innerHTML = "";
    document.getElementById("diSuggest").style.display = "none";
    document.getElementById("custSuggest").style.display = "none";

    clearPartGrid();
    fillDefaultEmptyRows(10);
    resetSmallGrids();

    document.getElementById("LabelStatus").innerHTML = "Mode input baru.";
    document.getElementById("ProgressBar1").value = 0;
};

document.getElementById("btnSaveHeader").onclick = function () {
    var diNo = document.getElementById("DI_NO").value;
    var custCode = document.getElementById("CUST_CODE").value;

    if (diNo == "") {
        alert("DI NO wajib diisi.");
        document.getElementById("DI_NO").focus();
        return;
    }

    if (diNo.length > 7) {
        alert("DI NO maksimal 7 karakter.");
        document.getElementById("DI_NO").focus();
        return;
    }

    if (custCode == "") {
        alert("Customer wajib dipilih dari autocomplete.");
        document.getElementById("CUST_SEARCH").focus();
        return;
    }

    document.getElementById("LabelStatus").innerHTML = "Menyimpan header...";
    document.getElementById("ProgressBar1").value = 20;

    ajaxPost("ajax_save_header.php", getHeaderPostData(), function (status, responseText) {
        document.getElementById("ProgressBar1").value = 100;

        if (status != 200) {
            alert("HTTP Error: " + status);
            document.getElementById("LabelStatus").innerHTML = "Save header gagal.";
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            alert("Response bukan JSON:\n\n" + responseText);
            document.getElementById("LabelStatus").innerHTML = "Save header gagal.";
            return;
        }

        if (!result.success) {
            alert(result.message);
            document.getElementById("LabelStatus").innerHTML = "Save header gagal.";
            return;
        }

        document.getElementById("DI_ID").value = result.DI_ID;
        document.getElementById("DI_DSNO").value = result.DI_DSNO;
        document.getElementById("DI_INVNO").value = result.DI_INVNO;
        document.getElementById("CUST_ID").value = result.CUST_ID;
        document.getElementById("CUST_COMP").value = result.CUST_COMP;
        document.getElementById("CUST_ABBR").value = result.CUST_ABBR;

        document.getElementById("LabelStatus").innerHTML =
            "Header berhasil disimpan. DI_ID: " + result.DI_ID;

        alert(result.message);
    });
};

document.getElementById("btnLoadOther").onclick = function () {
    var diId = document.getElementById("DI_ID").value;
    var diNo = document.getElementById("DI_NO").value;
    var custCode = document.getElementById("CUST_CODE").value;
    var custName = document.getElementById("CUST_COMP").value;
    var startDate = document.getElementById("DI_START_DATE").value;
    var endDate = document.getElementById("DI_DATE").value;

    if (diId == "") {
        alert("Save Header dulu sebelum LOAD DI OTHER.");
        return;
    }

    if (custCode == "") {
        alert("Customer belum dipilih.");
        document.getElementById("CUST_SEARCH").focus();
        return;
    }

    if (startDate == "") {
        alert("Start Date belum diisi.");
        document.getElementById("DI_START_DATE").focus();
        return;
    }

    if (endDate == "") {
        alert("DI Date belum diisi.");
        document.getElementById("DI_DATE").focus();
        return;
    }

    var confirmText =
        "KONFIRMASI LOAD DI OTHER\n\n" +
        "Apakah Anda yakin ingin mengambil data DI OTHER?\n\n" +
        "DI_ID      : " + diId + "\n" +
        "DI_NO      : " + diNo + "\n" +
        "CUST_CODE  : " + custCode + "\n" +
        "CUST_NAME  : " + custName + "\n" +
        "START      : " + startDate + "\n" +
        "END        : " + endDate + "\n\n" +
        "PERHATIAN:\n" +
        "Detail lama untuk DI ini akan DIHAPUS dan DIISI ULANG.\n\n" +
        "Klik OK untuk lanjut.\n" +
        "Klik Cancel untuk batal.";

    if (!confirm(confirmText)) {
        document.getElementById("LabelStatus").innerHTML = "LOAD DI OTHER dibatalkan.";
        return;
    }

    document.getElementById("LabelStatus").innerHTML = "Loading DI OTHER...";
    document.getElementById("ProgressBar1").value = 10;

    ajaxPost("ajax_load_other.php", getLoadOtherPostData(), function (status, responseText) {
        document.getElementById("ProgressBar1").value = 100;

        if (status != 200) {
            alert("HTTP Error: " + status);
            document.getElementById("LabelStatus").innerHTML = "LOAD DI OTHER gagal.";
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            alert("Response bukan JSON:\n\n" + responseText);
            document.getElementById("LabelStatus").innerHTML = "LOAD DI OTHER gagal.";
            return;
        }

        if (!result.success) {
            alert(result.message);
            document.getElementById("LabelStatus").innerHTML = "LOAD DI OTHER gagal.";
            return;
        }

        clearPartGrid();

        for (var i = 0; i < result.rows.length; i++) {
            addPartRowFromData(result.rows[i]);
        }

        resetSmallGrids();

        var rows = document.getElementById("partBody").getElementsByTagName("tr");

        if (rows.length > 0) {
            selectPartRow(rows[0]);
        }

        document.getElementById("LabelStatus").innerHTML = result.message;

        alert(
            "LOAD DI OTHER selesai.\n\n" +
            "Total data dimuat: " + result.rows.length + " item."
        );
    });
};

document.getElementById("btnSearchDI").onclick = function () {
    var searchType = document.getElementById("SEARCH_TYPE").value;
    var keyword = document.getElementById("SEARCH_KEYWORD").value;

    if (keyword == "") {
        alert("Isi keyword pencarian dulu.");
        document.getElementById("SEARCH_KEYWORD").focus();
        return;
    }

    document.getElementById("SearchStatus").innerHTML = "Searching...";
    document.getElementById("LabelStatus").innerHTML = "Mencari data DI...";

    var data = [];
    data.push("search_type=" + enc(searchType));
    data.push("keyword=" + enc(keyword));

    ajaxPost("ajax_search_di.php", data.join("&"), function (status, responseText) {
        document.getElementById("SearchStatus").innerHTML = "";

        if (status != 200) {
            alert("HTTP Error: " + status);
            document.getElementById("LabelStatus").innerHTML = "Search DI gagal.";
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            alert("Response bukan JSON:\n\n" + responseText);
            document.getElementById("LabelStatus").innerHTML = "Search DI gagal.";
            return;
        }

        if (!result.success) {
            alert(result.message);
            document.getElementById("LabelStatus").innerHTML = "Search DI gagal.";
            return;
        }

        fillHeaderFromSearch(result.header);
        fillDetailFromSearch(result.details);

        document.getElementById("LabelStatus").innerHTML =
            "Data DI ditemukan. DI_ID: " + result.header.DI_ID;
    });
};

function getDeleteLineData(row) {
    var data = [];

    data.push("DI_ID=" + enc(document.getElementById("DI_ID").value));
    data.push("DIPA_LINO=" + enc(getInputByNameFromRow(row, "DIPA_LINO[]").value));
    data.push("PRICE_ID=" + enc(getInputByNameFromRow(row, "PRICE_ID[]").value));

    return data.join("&");
}

function deleteSelectedDetailRow(row) {
    if (!row) {
        alert("Pilih baris detail dulu.");
        return;
    }

    var diId = document.getElementById("DI_ID").value;
    var lineInput = getInputByNameFromRow(row, "DIPA_LINO[]");
    var codeInput = getInputByNameFromRow(row, "CODE[]");
    var priceInput = getInputByNameFromRow(row, "PRICE_ID[]");

    if (!lineInput) {
        alert("Line tidak valid.");
        return;
    }

    if (diId == "" || !priceInput || priceInput.value == "") {
        if (confirm("Baris belum tersimpan. Hapus dari tampilan?")) {
            row.parentNode.removeChild(row);
            selectedPartRow = null;
            clearPOGrids();
        }
        return;
    }

    if (!confirm(
        "Hapus baris ini?\n\n" +
        "DI_ID : " + diId + "\n" +
        "Line  : " + lineInput.value + "\n" +
        "Code  : " + (codeInput ? codeInput.value : "") + "\n\n" +
        "Alokasi PO dan detail line ini akan dibatalkan."
    )) {
        return;
    }

    document.getElementById("LabelStatus").innerHTML =
        "Menghapus line " + lineInput.value + "...";

    ajaxPost("ajax_delete_detail_line.php", getDeleteLineData(row), function (status, responseText) {
        if (status != 200) {
            alert("HTTP Error: " + status);
            document.getElementById("LabelStatus").innerHTML = "Hapus line gagal.";
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            alert("Response bukan JSON:\n\n" + responseText);
            document.getElementById("LabelStatus").innerHTML = "Hapus line gagal.";
            return;
        }

        if (!result.success) {
            alert(result.message);
            document.getElementById("LabelStatus").innerHTML = "Hapus line gagal.";
            return;
        }

        row.parentNode.removeChild(row);
        selectedPartRow = null;
        clearPOGrids();

        document.getElementById("LabelStatus").innerHTML = result.message;
        alert(result.message);
    });
}

document.getElementById("btnDeleteLine").onclick = function () {
    deleteSelectedDetailRow(selectedPartRow);
};

function getCancelAllDetailData() {
    var data = [];

    data.push("DI_ID=" + enc(document.getElementById("DI_ID").value));

    return data.join("&");
}

document.getElementById("btnDeleteAll").onclick = function () {
    var diId = document.getElementById("DI_ID").value;

    if (diId == "") {
        alert("DI_ID kosong. Cari DI atau Save Header dulu.");
        return;
    }

    if (!confirm(
        "Batalkan dan hapus semua item DI_PART untuk DI ini?\n\n" +
        "DI_ID: " + diId + "\n\n" +
        "Proses ini akan rollback stok/FIFO lalu menghapus semua detail."
    )) {
        return;
    }

    document.getElementById("LabelStatus").innerHTML = "Membatalkan semua detail...";
    document.getElementById("ProgressBar1").value = 20;

    ajaxPost("ajax_cancel_all_detail.php", getCancelAllDetailData(), function (status, responseText) {
        document.getElementById("ProgressBar1").value = 100;

        if (status != 200) {
            alert("HTTP Error: " + status);
            document.getElementById("LabelStatus").innerHTML = "BATAL SEMUA DETAIL gagal.";
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            alert("Response bukan JSON:\n\n" + responseText);
            document.getElementById("LabelStatus").innerHTML = "BATAL SEMUA DETAIL gagal.";
            return;
        }

        if (!result.success) {
            alert(result.message);
            document.getElementById("LabelStatus").innerHTML = "BATAL SEMUA DETAIL gagal.";
            return;
        }

        clearPartGrid();
        fillDefaultEmptyRows(10);
        clearPOGrids();

        selectedPartRow = null;

        document.getElementById("LabelStatus").innerHTML =
            "Semua detail berhasil dibatalkan. Deleted: " + result.deleted;

        alert(result.message);
    });
};

function isDetailRowHasData(row) {
    var priceInput = getInputByNameFromRow(row, "PRICE_ID[]");
    var codeInput = getInputByNameFromRow(row, "CODE[]");
    var qtyInput = getInputByNameFromRow(row, "DIPA_QTY[]");
    var nameInput = getInputByNameFromRow(row, "NAME[]");

    if (priceInput && priceInput.value != "" && parseInt(priceInput.value, 10) > 0) {
        return true;
    }

    if (codeInput && codeInput.value != "") {
        return true;
    }

    if (qtyInput && qtyInput.value != "") {
        return true;
    }

    if (nameInput && nameInput.value != "") {
        return true;
    }

    return false;
}

function saveAllDetailRows(rows, index, savedCount) {
    if (index >= rows.length) {
        document.getElementById("ProgressBar1").value = 100;
        document.getElementById("LabelStatus").innerHTML =
            "SAVE DETAIL selesai. Total line tersimpan: " + savedCount;

        alert("SAVE DETAIL selesai.\nTotal line tersimpan: " + savedCount);

        if (selectedPartRow) {
            loadBottomPOGrids(selectedPartRow);
        }

        if (typeof refreshDIQtyValidationStatus == "function") {
            refreshDIQtyValidationStatus();
        }

        return;
    }

    var row = rows[index];

    if (!isDetailRowHasData(row)) {
        saveAllDetailRows(rows, index + 1, savedCount);
        return;
    }

    var lineInput = getInputByNameFromRow(row, "DIPA_LINO[]");

    document.getElementById("LabelStatus").innerHTML =
        "Menyimpan line " + lineInput.value + "...";

    saveDetailRow(row, function () {
        saveAllDetailRows(rows, index + 1, savedCount + 1);
    });
}

document.getElementById("btnSaveDetail").onclick = function () {
    var diId = document.getElementById("DI_ID").value;

    if (diId == "") {
        alert("DI_ID kosong. Cari DI atau Save Header dulu.");
        return;
    }

    if (!confirm("Simpan semua detail DI_PART untuk DI_ID: " + diId + " ?")) {
        return;
    }

    var rows = document.getElementById("partBody").getElementsByTagName("tr");

    document.getElementById("ProgressBar1").value = 20;
    document.getElementById("LabelStatus").innerHTML = "SAVE DETAIL diproses...";

    saveAllDetailRows(rows, 0, 0);
};

document.getElementById("btnLoadStaging").onclick = function () {
    alert("Step berikutnya: LOAD KOITO / STAGING.");
};

function getPostFifoData() {
    return "DI_ID=" + enc(document.getElementById("DI_ID").value);
}

document.getElementById("btnPostFifo").onclick = function () {
    var diId = document.getElementById("DI_ID").value;

    if (diId == "") {
        alert("Cari DI atau Save Header dulu sebelum POST FIFO.");
        return;
    }

    if (!confirm(
        "POST FIFO untuk DI_ID: " + diId + " ?\n\n" +
        "Proses ini akan menjalankan FIFO untuk semua line DI_PART."
    )) {
        return;
    }

    document.getElementById("LabelStatus").innerHTML = "POST FIFO sedang diproses...";
    document.getElementById("ProgressBar1").value = 20;

    ajaxPost("ajax_post_fifo.php", getPostFifoData(), function (status, responseText) {
        document.getElementById("ProgressBar1").value = 100;

        if (status != 200) {
            alert("HTTP Error: " + status);
            document.getElementById("LabelStatus").innerHTML = "POST FIFO gagal.";
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            alert("Response bukan JSON:\n\n" + responseText);
            document.getElementById("LabelStatus").innerHTML = "POST FIFO gagal.";
            return;
        }

        if (!result.success) {
            alert(result.message);
            document.getElementById("LabelStatus").innerHTML = "POST FIFO gagal.";
            return;
        }

        document.getElementById("LabelStatus").innerHTML =
            "POST FIFO selesai. Total line: " + result.processed;

        alert(result.message);
    });
};

function getSelectedPOParamsFromRow(row) {
    if (!row) {
        return null;
    }

    return {
        ORDR_ID: row.getAttribute("data-ordr-id"),
        ORDP_LINO: row.getAttribute("data-ordp-lino"),
        ORDR_PO: row.getAttribute("data-ordr-po")
    };
}

function getSelectedPartForPO() {
    if (!selectedPartRow) {
        return null;
    }

    return getSelectedPartParams(selectedPartRow);
}

document.getElementById("btnAllocatePO").onclick = function () {
    var part = getSelectedPartForPO();
    var po = getSelectedPOParamsFromRow(selectedOrderRow);

    if (!part) {
        alert("Pilih baris DETAIL DI PART dulu.");
        return;
    }

    if (!po || !po.ORDR_ID || !po.ORDP_LINO) {
        alert("Pilih baris ORDER / PO AVAILABLE dulu.");
        return;
    }

    if (!confirm(
        "ALLOCATE PO ini?\n\n" +
        "PO       : " + po.ORDR_PO + "\n" +
        "DI_ID    : " + part.DI_ID + "\n" +
        "LINE     : " + part.DIPA_LINO + "\n" +
        "PRICE_ID : " + part.PRICE_ID
    )) {
        return;
    }

    var data = [];
    data.push("DI_ID=" + enc(part.DI_ID));
    data.push("DIPA_LINO=" + enc(part.DIPA_LINO));
    data.push("PRICE_ID=" + enc(part.PRICE_ID));
    data.push("ORDR_ID=" + enc(po.ORDR_ID));
    data.push("ORDP_LINO=" + enc(po.ORDP_LINO));

    document.getElementById("LabelStatus").innerHTML = "Allocate PO sedang diproses...";

    ajaxPost("ajax_allocate_po.php", data.join("&"), function (status, responseText) {
        if (status != 200) {
            alert("HTTP Error: " + status);
            document.getElementById("LabelStatus").innerHTML = "ALLOCATE PO gagal.";
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            alert("Response bukan JSON:\n\n" + responseText);
            document.getElementById("LabelStatus").innerHTML = "ALLOCATE PO gagal.";
            return;
        }

        var msg = result.message || "ALLOCATE PO selesai, tapi server tidak mengirim pesan.";

        if (!result.success) {
            alert(msg);
            document.getElementById("LabelStatus").innerHTML = "ALLOCATE PO gagal.";
            return;
        }

        document.getElementById("LabelStatus").innerHTML = msg;
        alert(msg);

        loadBottomPOGrids(selectedPartRow);

        if (typeof refreshDIQtyValidationStatus == "function") {
            refreshDIQtyValidationStatus();
        }
    });
};

document.getElementById("btnRollbackPO").onclick = function () {
    var part = getSelectedPartForPO();
    var po = getSelectedPOParamsFromRow(selectedPORow);

    if (!part) {
        alert("Pilih baris DETAIL DI PART dulu.");
        return;
    }

    if (!po || !po.ORDR_ID || !po.ORDP_LINO) {
        alert("Pilih baris PO TERAMBIL / ALLOCATED dulu.");
        return;
    }

    if (!confirm(
        "ROLLBACK PO ini?\n\n" +
        "PO       : " + po.ORDR_PO + "\n" +
        "DI_ID    : " + part.DI_ID + "\n" +
        "LINE     : " + part.DIPA_LINO + "\n" +
        "PRICE_ID : " + part.PRICE_ID
    )) {
        return;
    }

    var data = [];
    data.push("DI_ID=" + enc(part.DI_ID));
    data.push("DIPA_LINO=" + enc(part.DIPA_LINO));
    data.push("PRICE_ID=" + enc(part.PRICE_ID));
    data.push("ORDR_ID=" + enc(po.ORDR_ID));
    data.push("ORDP_LINO=" + enc(po.ORDP_LINO));

    document.getElementById("LabelStatus").innerHTML = "Rollback PO sedang diproses...";

    ajaxPost("ajax_rollback_po.php", data.join("&"), function (status, responseText) {
        if (status != 200) {
            alert("HTTP Error: " + status);
            document.getElementById("LabelStatus").innerHTML = "ROLLBACK PO gagal.";
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            alert("Response bukan JSON:\n\n" + responseText);
            document.getElementById("LabelStatus").innerHTML = "ROLLBACK PO gagal.";
            return;
        }

        var msg = result.message || "ROLLBACK PO selesai, tapi server tidak mengirim pesan.";

        if (!result.success) {
            alert(msg);
            document.getElementById("LabelStatus").innerHTML = "ROLLBACK PO gagal.";
            return;
        }

        document.getElementById("LabelStatus").innerHTML = msg;
        alert(msg);

        loadBottomPOGrids(selectedPartRow);

        if (typeof refreshDIQtyValidationStatus == "function") {
            refreshDIQtyValidationStatus();
        }
    });
};

function hideReportMenu() {
    var menu = document.getElementById("reportMenuPopup");

    if (menu) {
        menu.style.display = "none";
    }
}

function showReportMenu() {
    var menu = document.getElementById("reportMenuPopup");

    if (!menu) {
        return;
    }

    if (menu.style.display == "block") {
        menu.style.display = "none";
    } else {
        menu.style.display = "block";
    }
}

document.getElementById("btnReport").onclick = function (e) {
    e = e || window.event;

    if (e.stopPropagation) {
        e.stopPropagation();
    } else {
        e.cancelBubble = true;
    }

    showReportMenu();
};

function openReportMenuItem(reportType) {
    var diId = document.getElementById("DI_ID").value;

    if (diId == "") {
        alert("Cari DI atau Save Header dulu.");
        return;
    }

    hideReportMenu();

    if (reportType == "INVOICE_PO") {
        window.open("report_invoice_po.php?DI_ID=" + enc(diId), "_blank");
        return;
    }

    if (reportType == "DELIVERY_SHEET") {
       window.open(
        "report_delivery_sheet.php?DI_ID=" + enc(diId),"_blank" );
        return;
    }

    if (reportType == "DELIVERY_SHEET_PO") {
       window.open(
        "report_delivery_sheet_po.php?DI_ID=" + enc(diId),"_blank");
        return;
    }

    if (reportType == "DELIVERY_SHEET_CABININDO") {
        window.open(
        "report_delivery_sheet_cabinindo.php?DI_ID=" + enc(diId),
        "_blank"
    );
        return;
    }

    if (reportType == "DS_TOYODENSO") {
        window.open(
        "report_ds_toyodenso.php?DI_ID=" + enc(diId),
        "_blank"
    );
        return;
    }

    if (reportType == "INVOICE_RATE_HIROSE") {
        window.open(
        "report_invoice_rate.php?DI_ID=" + enc(diId),
        "_blank"
    );
        return;
    }

    if (reportType == "INVOICE_HILEX") {
        window.open(
        "report_surat_jalan.php?DI_ID=" + enc(diId),
        "_blank"
    );
        return;
    }

    if (reportType == "PACKING_LIST") {
         window.open(
        "report_packing_list.php?DI_ID=" + enc(diId),
        "_blank"
    );
        return;
    }

    alert("Report belum dibuat: " + reportType + "\nDI_ID: " + diId);
}

var reportItems = document.getElementsByClassName("report-menu-item");

for (var i = 0; i < reportItems.length; i++) {
    reportItems[i].onclick = function (e) {
        e = e || window.event;

        if (e.stopPropagation) {
            e.stopPropagation();
        } else {
            e.cancelBubble = true;
        }

        openReportMenuItem(this.getAttribute("data-report"));
    };
}

document.addEventListener("click", function () {
    hideReportMenu();
});

document.getElementById("btnReportDI").onclick = function () {
    var custCode  = document.getElementById("REPORT_CUST_CODE").value;
    var startDate = document.getElementById("REPORT_START_DATE").value;
    var endDate   = document.getElementById("REPORT_END_DATE").value;

    custCode = custCode.replace(/^\s+|\s+$/g, "");

    /*
        % = CETAK SEMUA CUSTOMER
    */
    if (custCode == "") {
        alert("Customer report belum diisi. Isi kode customer atau % untuk semua customer.");
        document.getElementById("REPORT_CUST_CODE").focus();
        return;
    }

    if (startDate == "") {
        alert("Start date report belum diisi.");
        document.getElementById("REPORT_START_DATE").focus();
        return;
    }

    if (endDate == "") {
        alert("End date report belum diisi.");
        document.getElementById("REPORT_END_DATE").focus();
        return;
    }

    window.open(
        "delivery_instruction_report.php" +
        "?CUST_CODE=" + enc(custCode) +
        "&START_DATE=" + enc(startDate) +
        "&END_DATE=" + enc(endDate),
        "delivery_instruction",
        "width=1200,height=700,scrollbars=yes,resizable=yes"
    );
};


document.getElementById("btnorder").onclick = function () {
    window.open(
        "input_order.php",
        "_blank",
        "width=1150,height=700,scrollbars=yes"
    );
};

document.getElementById("btnForecast").onclick = function () {
    window.open(
        "forecast.php",
        "forecast",
        "width=1150,height=560,scrollbars=yes,resizable=yes"
    );
};

document.getElementById("btnSchedule").onclick = function () {
    window.open(
        "schedule.php",
        "schedule",
        "width=1150,height=620,scrollbars=yes,resizable=yes"
    );
};

document.getElementById("btnEditOrder").onclick = function () {
    var po = "";

    /*
        Ambil PO dari grid ORDER / PO AVAILABLE.
        Pastikan tbody PO Available punya id="poAvailableBody".
    */
    var poBody = document.getElementById("poAvailableBody");

    if (poBody) {
        var rows = poBody.getElementsByTagName("tr");

        if (rows.length > 0) {
            var cells = rows[0].getElementsByTagName("td");

            /*
                Kolom:
                0 = ORDR_DATE
                1 = ORDR_PO
                2 = QTY
                3 = DQTY
                4 = BQTY
            */
            if (cells.length >= 2) {
                po = cells[1].innerText || cells[1].textContent;
                po = po.replace(/^\s+|\s+$/g, "");
            }
        }
    }

    /*
        Kalau PO kosong / belum ada data, tetap buka halaman Edit Order kosong.
    */
    if (po == "" || po == "Belum ada data.") {
        window.open(
            "order_edit.php",
            "_blank",
            "width=980,height=620,scrollbars=yes"
        );

        return;
    }

    window.open(
        "order_edit.php?po=" + enc(po),
        "_blank",
        "width=980,height=620,scrollbars=yes"
    );
};

function getSelectedPartParams(row) {
    return {
        DI_ID: document.getElementById("DI_ID").value,
        DIPA_LINO: getInputByNameFromRow(row, "DIPA_LINO[]").value,
        PRICE_ID: getInputByNameFromRow(row, "PRICE_ID[]").value
    };
}

function clearPOGrids() {
    document.getElementById("orderBody").innerHTML =
        '<tr><td colspan="5">Belum ada data.</td></tr>';

    document.getElementById("poBody").innerHTML =
        '<tr><td colspan="3">Belum ada data.</td></tr>';

    selectedOrderRow = null;
    selectedPORow = null;
}

function renderPOAvailable(rows) {
    var tbody = document.getElementById("orderBody");
    tbody.innerHTML = "";

    if (!rows || rows.length == 0) {
        tbody.innerHTML = '<tr><td colspan="5">Belum ada data.</td></tr>';
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        var tr = document.createElement("tr");
        tr.setAttribute("onclick", "selectOrderRow(this)");

        tr.setAttribute("data-ordr-id", rows[i].ORDR_ID);
        tr.setAttribute("data-ordp-lino", rows[i].ORDP_LINO);
        tr.setAttribute("data-bqty", rows[i].BQTY);
        tr.setAttribute("data-ordr-po", rows[i].ORDR_PO);

        tr.innerHTML =
            '<td>' + htmlEncode(rows[i].ORDR_DATE) + '</td>' +
            '<td title="' + htmlEncode(rows[i].ORDR_PO) + '">' + htmlEncode(rows[i].ORDR_PO) + '</td>' +
            '<td>' + htmlEncode(rows[i].QTY) + '</td>' +
            '<td>' + htmlEncode(rows[i].DQTY) + '</td>' +
            '<td>' + htmlEncode(rows[i].BQTY) + '</td>';

        tbody.appendChild(tr);
    }
}

function renderPOAllocated(rows) {
    var tbody = document.getElementById("poBody");
    tbody.innerHTML = "";

    if (!rows || rows.length == 0) {
        tbody.innerHTML = '<tr><td colspan="3">Belum ada data.</td></tr>';
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        var tr = document.createElement("tr");
        tr.setAttribute("onclick", "selectPORow(this)");

        tr.setAttribute("data-ordr-id", rows[i].ORDR_ID);
        tr.setAttribute("data-ordp-lino", rows[i].ORDP_LINO);
        tr.setAttribute("data-qty", rows[i].QTY);
        tr.setAttribute("data-ordr-po", rows[i].ORDR_PO);

        tr.innerHTML =
            '<td>' + htmlEncode(rows[i].ORDR_DATE) + '</td>' +
            '<td title="' + htmlEncode(rows[i].ORDR_PO) + '">' + htmlEncode(rows[i].ORDR_PO) + '</td>' +
            '<td>' + htmlEncode(rows[i].QTY) + '</td>';

        tbody.appendChild(tr);
    }
}

function loadPOAvailable(row) {
    var p = getSelectedPartParams(row);

    if (p.PRICE_ID == "" || parseInt(p.PRICE_ID, 10) <= 0) {
        renderPOAvailable([]);
        return;
    }

    ajaxPost(
        "ajax_get_po_available.php",
        "PRICE_ID=" + enc(p.PRICE_ID),
        function (status, responseText) {
            if (status != 200) {
                renderPOAvailable([]);
                return;
            }

            var result;

            try {
                result = JSON.parse(responseText);
            } catch (e) {
                renderPOAvailable([]);
                return;
            }

            if (!result.success) {
                renderPOAvailable([]);
                return;
            }

            renderPOAvailable(result.rows);
        }
    );
}

function loadPOAllocated(row) {
    var p = getSelectedPartParams(row);

    if (
        p.DI_ID == "" ||
        p.PRICE_ID == "" ||
        p.DIPA_LINO == ""
    ) {
        renderPOAllocated([]);
        return;
    }

    var data = [];
    data.push("DI_ID=" + enc(p.DI_ID));
    data.push("PRICE_ID=" + enc(p.PRICE_ID));
    data.push("DIPA_LINO=" + enc(p.DIPA_LINO));

    ajaxPost(
        "ajax_get_po_allocated.php",
        data.join("&"),
        function (status, responseText) {
            if (status != 200) {
                renderPOAllocated([]);
                return;
            }

            var result;

            try {
                result = JSON.parse(responseText);
            } catch (e) {
                renderPOAllocated([]);
                return;
            }

            if (!result.success) {
                renderPOAllocated([]);
                return;
            }

            renderPOAllocated(result.rows);
        }
    );
}

function loadBottomPOGrids(row) {
    if (!row) {
        clearPOGrids();
        return;
    }

    loadPOAvailable(row);
    loadPOAllocated(row);
}

function selectOrderRow(row) {
    var rows = document.getElementById("orderBody").getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        rows[i].className = "";
    }

    row.className = "selected";
    selectedOrderRow = row;
}

function selectPORow(row) {
    var rows = document.getElementById("poBody").getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        rows[i].className = "";
    }

    row.className = "selected";
    selectedPORow = row;
}

function getPackIdInputFromRow(row) {
    return getInputByNameFromRow(row, "PACK_ID[]");
}

function selectPackItem(input, item) {
    input.value = item.PACK_CODE;

    var row = getRowFromElement(input);
    var packIdInput = getPackIdInputFromRow(row);

    if (packIdInput) {
        packIdInput.value = item.PACK_ID;
    }

    var box = input.parentNode.getElementsByClassName("packSuggest")[0];
    if (box) {
        box.style.display = "none";
    }
}

function renderPackSuggest(input, items) {
    var wrap = input.parentNode;
    var box = wrap.getElementsByClassName("packSuggest")[0];

    if (!box) {
        return;
    }

    box.innerHTML = "";

    var filtered = [];

    if (items) {
        for (var i = 0; i < items.length; i++) {
            if (items[i].PACK_CODE && items[i].PACK_CODE != "") {
                filtered.push(items[i]);
            }
        }
    }

    packItems = filtered;

    if (filtered.length == 0) {
        box.style.display = "none";
        return;
    }

    for (var j = 0; j < filtered.length; j++) {
        var div = document.createElement("div");
        div.className = "autocomplete-item";
        div.setAttribute("data-index", j);

        div.innerHTML =
            htmlEncode(filtered[j].PACK_CODE) +
            " - ID: " +
            htmlEncode(filtered[j].PACK_ID);

        div.onmousedown = function (e) {
            if (!e) {
                e = window.event;
            }

            if (e.preventDefault) {
                e.preventDefault();
            }

            var idx = parseInt(this.getAttribute("data-index"), 10);
            selectPackItem(input, packItems[idx]);
        };

        box.appendChild(div);
    }

    box.style.display = "block";
}

document.getElementById("tblPart").addEventListener("keyup", function (e) {
    e = e || window.event;

    var target = e.target || e.srcElement;

    if (!target || target.name != "PACK_DESC[]") {
        return;
    }

    var key = e.keyCode || e.which;

    if (key == 13 || key == 38 || key == 40) {
        return;
    }

    var q = target.value;
    var row = getRowFromElement(target);
    var packIdInput = getPackIdInputFromRow(row);

    if (packIdInput) {
        packIdInput.value = "";
    }

    if (q.length < 1) {
        var boxEmpty = target.parentNode.getElementsByClassName("packSuggest")[0];
        if (boxEmpty) {
            boxEmpty.style.display = "none";
        }
        return;
    }

    ajaxPost("ajax_pack_autocomplete.php", "q=" + enc(q), function (status, responseText) {
        if (status != 200) {
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            return;
        }

        renderPackSuggest(target, result);
    });
});

document.getElementById("tblPart").addEventListener("blur", function (e) {
    e = e || window.event;

    var target = e.target || e.srcElement;

    if (!target || target.name != "PACK_DESC[]") {
        return;
    }

    setTimeout(function () {
        var box = target.parentNode.getElementsByClassName("packSuggest")[0];
        if (box) {
            box.style.display = "none";
        }
    }, 250);

}, true);

function getRowSaveData(row) {
    var data = [];

    data.push("DI_ID=" + enc(document.getElementById("DI_ID").value));
    data.push("DIPA_LINO=" + enc(getInputByNameFromRow(row, "DIPA_LINO[]").value));
    data.push("CODE=" + enc(getInputByNameFromRow(row, "CODE[]").value));
    data.push("DIPA_QTY=" + enc(getInputByNameFromRow(row, "DIPA_QTY[]").value));
    data.push("DIPA_PQTY=" + enc(getInputByNameFromRow(row, "DIPA_PQTY[]").value));
    data.push("PACK_DESC=" + enc(getInputByNameFromRow(row, "PACK_DESC[]").value));
    data.push("LOCATION=" + enc(getInputByNameFromRow(row, "LOCATION[]").value));
    data.push("PRICE_ID=" + enc(getInputByNameFromRow(row, "PRICE_ID[]").value));
    data.push("PACK_ID=" + enc(getInputByNameFromRow(row, "PACK_ID[]").value));

    return data.join("&");
}

function applyCodeFromLookupByPrice(row, done) {
    var codeInput = getInputByNameFromRow(row, "CODE[]");
    var nameInput = getInputByNameFromRow(row, "NAME[]");
    var qtyInput = getInputByNameFromRow(row, "DIPA_QTY[]");
    var packQtyInput = getInputByNameFromRow(row, "DIPA_PQTY[]");
    var packDescInput = getInputByNameFromRow(row, "PACK_DESC[]");
    var priceInput = getInputByNameFromRow(row, "PRICE_ID[]");
    var packIdInput = getInputByNameFromRow(row, "PACK_ID[]");

    if (!codeInput || !priceInput) {
        done();
        return;
    }

    if (codeInput.value != "" || priceInput.value == "" || parseInt(priceInput.value, 10) <= 0) {
        done();
        return;
    }

    ajaxPost("ajax_part_lookup.php", getPartLookupData(""), function (status, responseText) {
        if (status != 200) {
            done();
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            done();
            return;
        }

        var lookupRows = [];

        if (result && result.rows) {
            lookupRows = result.rows;
        } else if (result && result.length) {
            lookupRows = result;
        }

        if (!lookupRows || lookupRows.length == 0) {
            done();
            return;
        }

        var targetPriceId = String(priceInput.value);
        var item = null;

        for (var i = 0; i < lookupRows.length; i++) {
            if (String(lookupRows[i].PRICE_ID) == targetPriceId) {
                item = lookupRows[i];
                break;
            }
        }

        if (item) {
            if (codeInput && item.CODE) {
                codeInput.value = item.CODE;
            }

            if (nameInput && item.NAME && nameInput.value == "") {
                nameInput.value = item.NAME;
            }

            if (qtyInput && item.DAILY_SCH && qtyInput.value == "") {
                qtyInput.value = item.DAILY_SCH;
            }

            if (packQtyInput && item.DIPA_PQTY && packQtyInput.value == "") {
                packQtyInput.value = item.DIPA_PQTY;
            }

            if (packDescInput && item.PACK_DESC && packDescInput.value == "") {
                packDescInput.value = item.PACK_DESC;
            }

            if (packIdInput && item.PACK_ID && packIdInput.value == "") {
                packIdInput.value = item.PACK_ID;
            }
        }

        done();
    });
}

function saveDetailRow(row, callback) {
    if (!row) {
        if (callback) {
            callback(false);
        }
        return;
    }

    var diId = document.getElementById("DI_ID").value;

    if (diId == "") {
        alert("Save Header dulu sebelum simpan detail.");
        if (callback) {
            callback(false);
        }
        return;
    }

    var lineInput = getInputByNameFromRow(row, "DIPA_LINO[]");
    var codeInput = getInputByNameFromRow(row, "CODE[]");
    var priceInput = getInputByNameFromRow(row, "PRICE_ID[]");

    if (!lineInput || lineInput.value == "") {
        alert("Line number kosong.");
        if (callback) {
            callback(false);
        }
        return;
    }

    if (!codeInput) {
        alert("Input CODE tidak ditemukan pada line " + lineInput.value);
        if (callback) {
            callback(false);
        }
        return;
    }

    if (!priceInput || priceInput.value == "" || parseInt(priceInput.value, 10) <= 0) {
        alert("PRICE_ID kosong pada line " + lineInput.value);
        if (callback) {
            callback(false);
        }
        return;
    }

    /*
        Lengkapi CODE / NAME dari lookup PRICE_ID dulu.
        Setelah itu baru cek duplicate dan save.
    */
    applyCodeFromLookupByPrice(row, function () {
        var codeInputAfterLookup = getInputByNameFromRow(row, "CODE[]");
        var priceInputAfterLookup = getInputByNameFromRow(row, "PRICE_ID[]");

        var codeValue = codeInputAfterLookup ? codeInputAfterLookup.value : "";
        var priceValue = priceInputAfterLookup ? priceInputAfterLookup.value : "";

        /*
            Pengaman duplicate item dalam grid.
            Cek berdasarkan PRICE_ID dan CODE.
            Baris dirinya sendiri tidak dihitung duplicate.
        */
        if (typeof isDuplicateItemInGrid == "function") {
            if (isDuplicateItemInGrid(priceValue, codeValue, row)) {
                alert(
                    "Item tidak boleh double dalam 1 DI.\n\n" +
                    "Line     : " + lineInput.value + "\n" +
                    "CODE     : " + codeValue + "\n" +
                    "PRICE ID : " + priceValue
                );

                document.getElementById("LabelStatus").innerHTML =
                    "Save line gagal. Item duplicate.";

                if (codeInputAfterLookup) {
                    codeInputAfterLookup.focus();
                }

                if (callback) {
                    callback(false);
                }

                return;
            }
        }

        document.getElementById("LabelStatus").innerHTML =
            "Menyimpan line " + lineInput.value + "...";

        ajaxPost("ajax_save_detail_line.php", getRowSaveData(row), function (status, responseText) {
            if (status != 200) {
                alert("HTTP Error: " + status);
                document.getElementById("LabelStatus").innerHTML = "Save line gagal.";

                if (callback) {
                    callback(false);
                }

                return;
            }

            var result;

            try {
                result = JSON.parse(responseText);
            } catch (e) {
                alert("Response bukan JSON:\n\n" + responseText);
                document.getElementById("LabelStatus").innerHTML = "Save line gagal.";

                if (callback) {
                    callback(false);
                }

                return;
            }

            if (!result.success) {
                alert(result.message);
                document.getElementById("LabelStatus").innerHTML = "Save line gagal.";

                if (callback) {
                    callback(false);
                }

                return;
            }

            var codeInputAfterSave = getInputByNameFromRow(row, "CODE[]");
            var serverCode = result.PART_CODE || result.CODE || result.ITEM_CODE || "";

            if (codeInputAfterSave && serverCode != "") {
                codeInputAfterSave.value = serverCode;
            }

            var nameInputAfterSave = getInputByNameFromRow(row, "NAME[]");
            var serverName = result.ITEM_NAME || result.NAME || "";

            if (nameInputAfterSave && serverName != "") {
                nameInputAfterSave.value = serverName;
            }

            var packIdInput = getInputByNameFromRow(row, "PACK_ID[]");

            if (packIdInput && result.PACK_ID) {
                packIdInput.value = result.PACK_ID;
            }

            /*
                Support dua kemungkinan nama input:
                PACK_DESC[] atau DIPA_PACK[]
            */
            var packDescInputAfterSave = getInputByNameFromRow(row, "PACK_DESC[]");

            if (!packDescInputAfterSave) {
                packDescInputAfterSave = getInputByNameFromRow(row, "DIPA_PACK[]");
            }

            if (packDescInputAfterSave && result.PACK_DESC) {
                packDescInputAfterSave.value = result.PACK_DESC;
            }

            var packQtyInputAfterSave = getInputByNameFromRow(row, "DIPA_PQTY[]");

            if (packQtyInputAfterSave && result.DIPA_PQTY) {
                packQtyInputAfterSave.value = result.DIPA_PQTY;
            }

            var qtyInputAfterSave = getInputByNameFromRow(row, "DIPA_QTY[]");

            if (qtyInputAfterSave && result.DIPA_QTY) {
                qtyInputAfterSave.value = result.DIPA_QTY;
            }

            document.getElementById("LabelStatus").innerHTML = result.message;

            loadBottomPOGrids(row);

            if (typeof refreshDIQtyValidationStatus == "function") {
                refreshDIQtyValidationStatus();
            }

            if (callback) {
                callback(true);
            }
        });
    });
}

function isDuplicateItemInGrid(priceId, code, currentRow) {
    var rows = document.getElementById("partBody").getElementsByTagName("tr");

    priceId = String(priceId || "").replace(/^\s+|\s+$/g, "");
    code = String(code || "").replace(/^\s+|\s+$/g, "").toUpperCase();

    if (priceId == "" && code == "") {
        return false;
    }

    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];

        if (currentRow && row === currentRow) {
            continue;
        }

        var priceInput = getInputByNameFromRow(row, "PRICE_ID[]");
        var codeInput = getInputByNameFromRow(row, "CODE[]");

        var rowPriceId = priceInput ? String(priceInput.value || "").replace(/^\s+|\s+$/g, "") : "";
        var rowCode = codeInput ? String(codeInput.value || "").replace(/^\s+|\s+$/g, "").toUpperCase() : "";

        if (priceId != "" && rowPriceId != "" && priceId == rowPriceId) {
            return true;
        }

        if (code != "" && rowCode != "" && code == rowCode) {
            return true;
        }
    }

    return false;
}

function moveToNextRowSameColumn(currentInput) {
    var cell = getCellFromElement(currentInput);

    if (!cell) {
        return;
    }

    var row = cell.parentNode;
    var tbody = document.getElementById("partBody");
    var rows = tbody.getElementsByTagName("tr");

    var rowIndex = -1;
    var cellIndex = cell.cellIndex;

    for (var i = 0; i < rows.length; i++) {
        if (rows[i] == row) {
            rowIndex = i;
            break;
        }
    }

    if (rowIndex < 0) {
        return;
    }

    var nextRowIndex = rowIndex + 1;

    if (nextRowIndex >= rows.length) {
        return;
    }

    var nextCell = rows[nextRowIndex].cells[cellIndex];

    if (!nextCell) {
        return;
    }

    var nextInput = nextCell.getElementsByTagName("input")[0];

    if (nextInput) {
        selectPartRow(rows[nextRowIndex]);
        nextInput.focus();

        if (nextInput.select) {
            nextInput.select();
        }
    }
}

var lastDIQtyValid = false;
var partItems = [];
var partActiveInput = null;
var partActiveIndex = -1;

function getPartSuggestBox(input) {
    if (!input || !input.parentNode) {
        return null;
    }

    var boxes = input.parentNode.getElementsByClassName("partSuggest");

    if (!boxes || boxes.length == 0) {
        return null;
    }

    return boxes[0];
}

function isPartSuggestOpen(input) {
    var box = getPartSuggestBox(input);

    if (!box) {
        return false;
    }

    return box.style.display != "none" && partItems && partItems.length > 0;
}

function setActivePartSuggest(input, index) {
    var box = getPartSuggestBox(input);

    if (!box) {
        partActiveIndex = -1;
        return;
    }

    var items = box.getElementsByClassName("autocomplete-item");

    if (!items || items.length == 0) {
        partActiveIndex = -1;
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

    if (items[index].scrollIntoView) {
        items[index].scrollIntoView({ block: "nearest" });
    }

    partActiveInput = input;
    partActiveIndex = index;
}

function hidePartSuggest(input) {
    var box = getPartSuggestBox(input);

    if (box) {
        box.style.display = "none";
    }

    if (partActiveInput === input) {
        partActiveInput = null;
        partActiveIndex = -1;
    }
}

function choosePartSuggest(input, index, autoSave) {
    if (!partItems || partItems.length == 0) {
        return;
    }

    if (index < 0 || index >= partItems.length) {
        index = 0;
    }

    var row = getRowFromElement(input);
    var item = partItems[index];

    setRowPartFromLookup(input, item);
    hidePartSuggest(input);

    /*
        Pilih pakai ENTER = langsung SAVE line.
    */
    if (autoSave && row) {
        saveDetailRow(row, function (ok) {
            if (ok) {
                moveToNextRowSameColumn(input);
            }
        });
    }
}

function focusInputSameColumn(row, cellIndex) {
    if (!row || !row.cells || !row.cells[cellIndex]) {
        return;
    }

    var nextInput = row.cells[cellIndex].getElementsByTagName("input")[0];

    if (nextInput) {
        selectPartRow(row);
        nextInput.focus();

        if (nextInput.select) {
            nextInput.select();
        }
    }
}

function movePartGridSelection(currentInput, direction) {
    var cell = getCellFromElement(currentInput);

    if (!cell) {
        return;
    }

    var row = cell.parentNode;
    var tbody = document.getElementById("partBody");
    var rows = tbody.getElementsByTagName("tr");

    var rowIndex = -1;
    var cellIndex = cell.cellIndex;

    for (var i = 0; i < rows.length; i++) {
        if (rows[i] == row) {
            rowIndex = i;
            break;
        }
    }

    if (rowIndex < 0) {
        return;
    }

    var nextRowIndex = rowIndex + direction;

    if (nextRowIndex < 0) {
        nextRowIndex = 0;
    }

    /*
        Kalau panah bawah di baris terakhir,
        otomatis tambah baris baru.
    */
    if (nextRowIndex >= rows.length) {
        addRow();
        rows = tbody.getElementsByTagName("tr");
        nextRowIndex = rows.length - 1;
    }

    focusInputSameColumn(rows[nextRowIndex], cellIndex);
}

document.getElementById("tblPart").addEventListener("keydown", function (e) {
    e = e || window.event;

    var target = e.target || e.srcElement;

    if (!target || target.tagName != "INPUT") {
        return true;
    }

    var key = e.keyCode || e.which;

    /*
        Kalau sedang di kolom CODE dan dropdown part terbuka:
        - Panah bawah = turun pilihan dropdown
        - Panah atas   = naik pilihan dropdown
        - Enter        = pilih item aktif + SAVE line
    */
    if (target.name == "CODE[]" && isPartSuggestOpen(target)) {
        if (key == 40) {
            if (e.preventDefault) {
                e.preventDefault();
            } else {
                e.returnValue = false;
            }

            setActivePartSuggest(target, partActiveIndex + 1);
            return false;
        }

        if (key == 38) {
            if (e.preventDefault) {
                e.preventDefault();
            } else {
                e.returnValue = false;
            }

            setActivePartSuggest(target, partActiveIndex - 1);
            return false;
        }

        if (key == 13) {
            if (e.preventDefault) {
                e.preventDefault();
            } else {
                e.returnValue = false;
            }

            if (e.stopImmediatePropagation) {
                e.stopImmediatePropagation();
            }

            if (partActiveIndex < 0) {
                partActiveIndex = 0;
            }

            choosePartSuggest(target, partActiveIndex, true);
            return false;
        }
    }

    /*
        Kalau dropdown tidak terbuka:
        panah atas / bawah pindah baris grid.
    */
    if (key == 38) {
        if (e.preventDefault) {
            e.preventDefault();
        } else {
            e.returnValue = false;
        }

        movePartGridSelection(target, -1);
        return false;
    }

    if (key == 40) {
        if (e.preventDefault) {
            e.preventDefault();
        } else {
            e.returnValue = false;
        }

        movePartGridSelection(target, 1);
        return false;
    }

    if (key != 13) {
        return true;
    }

    if (e.preventDefault) {
        e.preventDefault();
    } else {
        e.returnValue = false;
    }

    if (e.stopImmediatePropagation) {
        e.stopImmediatePropagation();
    }

    /*
        ENTER di kolom CODE:
        kalau dropdown belum muncul, load lookup dulu.
        kalau dropdown muncul, pilih item aktif + save.
    */
    if (target.name == "CODE[]") {
        if (isPartSuggestOpen(target)) {
            if (partActiveIndex < 0) {
                partActiveIndex = 0;
            }

            choosePartSuggest(target, partActiveIndex, true);
        } else {
            loadPartLookup(target);
        }

        return false;
    }

    var rowSave = getRowFromElement(target);

    if (!rowSave) {
        return false;
    }

    if (target.name == "PACK_DESC[]") {
        var box = target.parentNode.getElementsByClassName("packSuggest")[0];

        if (box && box.style.display != "none" && packItems.length > 0) {
            selectPackItem(target, packItems[0]);
        }
    }

    saveDetailRow(rowSave, function (ok) {
        if (ok) {
            moveToNextRowSameColumn(target);
        }
    });

    return false;
});

document.getElementById("tblPart").onfocusin = function (e) {
    e = e || window.event;

    var target = e.target || e.srcElement;

    if (!target) {
        return;
    }

    var row = getRowFromElement(target);

    if (row && row.parentNode && row.parentNode.id == "partBody") {
        selectPartRow(row);
    }
};

function buildDIQtyWarning(result) {
    var msg = "";

    if (!result) {
        return "Validasi gagal.";
    }

    msg += result.message || "QTY DI_PART dan PO Allocated belum sama.";

    if (result.mismatch && result.mismatch.length > 0) {
        msg += "\n\nDetail selisih:";

        for (var i = 0; i < result.mismatch.length; i++) {
            msg +=
                "\n\nLine      : " + result.mismatch[i].DIPA_LINO +
                "\nCode      : " + result.mismatch[i].CODE +
                "\nName      : " + result.mismatch[i].NAME +
                "\nDI Qty    : " + result.mismatch[i].SCHEDULE_QTY +
                "\nPO Qty    : " + result.mismatch[i].ALLOCATED_QTY +
                "\nSelisih   : " + result.mismatch[i].SELISIH_QTY;
        }
    }

    return msg;
}

function validateDIQtyAsync(callback) {
    var diId = document.getElementById("DI_ID").value;

    if (diId == "") {
        callback({
            success: true,
            valid: false,
            message: "DI_ID kosong. Cari DI atau Save Header dulu.",
            mismatch: []
        });
        return;
    }

    ajaxPost("ajax_validate_di_qty.php", "DI_ID=" + enc(diId), function (status, responseText) {
        if (status != 200) {
            callback({
                success: false,
                valid: false,
                message: "HTTP Error validasi: " + status,
                mismatch: []
            });
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            callback({
                success: false,
                valid: false,
                message: "Response validasi bukan JSON:\n\n" + responseText,
                mismatch: []
            });
            return;
        }

        callback(result);
    });
}

function validateDIQtySync() {
    var diId = document.getElementById("DI_ID").value;

    if (diId == "") {
        return {
            success: true,
            valid: false,
            message: "DI_ID kosong. Cari DI atau Save Header dulu.",
            mismatch: []
        };
    }

    var xhr = new XMLHttpRequest();

    try {
        xhr.open("POST", "ajax_validate_di_qty.php", false);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.send("DI_ID=" + enc(diId));

        if (xhr.status != 200) {
            return {
                success: false,
                valid: false,
                message: "HTTP Error validasi: " + xhr.status,
                mismatch: []
            };
        }

        return JSON.parse(xhr.responseText);

    } catch (e) {
        return {
            success: false,
            valid: false,
            message: "Validasi gagal: " + e.message,
            mismatch: []
        };
    }
}

function refreshDIQtyValidationStatus() {
    validateDIQtyAsync(function (result) {
        lastDIQtyValid = !!result.valid;

        if (result.valid) {
            document.getElementById("LabelStatus").innerHTML =
                "Validasi OK. QTY DI_PART = PO Allocated.";
        } else {
            document.getElementById("LabelStatus").innerHTML =
                "WARNING: QTY DI_PART belum sama dengan PO Allocated.";
        }
    });
}

function guardBeforePrintOrClose(actionName, callback) {
    validateDIQtyAsync(function (result) {
        lastDIQtyValid = !!result.valid;

        if (!result.valid) {
            alert(
                "Tidak bisa " + actionName + ".\n\n" +
                buildDIQtyWarning(result)
            );

            document.getElementById("LabelStatus").innerHTML =
                "WARNING: QTY DI_PART belum sama dengan PO Allocated.";

            return;
        }

        callback();
    });
}

function protectTopbarLinks() {
    var links = document.querySelectorAll(".topbar a");

    for (var i = 0; i < links.length; i++) {
        links[i].onclick = function (e) {
            if (document.getElementById("DI_ID").value == "") {
                return true;
            }

            var check = validateDIQtySync();

            if (!check.valid) {
                if (e.preventDefault) {
                    e.preventDefault();
                } else {
                    e.returnValue = false;
                }

                alert(
                    "Tidak bisa keluar dari form.\n\n" +
                    buildDIQtyWarning(check)
                );

                return false;
            }

            return true;
        };
    }
}

protectTopbarLinks();

window.onbeforeunload = function (e) {
    if (document.getElementById("DI_ID").value == "") {
        return;
    }

    var check = validateDIQtySync();

    if (!check.valid) {
        var message = "QTY DI_PART belum sama dengan PO Allocated. Tidak boleh keluar sebelum sama.";

        if (e) {
            e.returnValue = message;
        }

        return message;
    }
};

document.getElementById("btnAddRow").onclick = function () {
    addRow();

    var rows = document.getElementById("partBody").getElementsByTagName("tr");

    if (rows.length > 0) {
        var lastRow = rows[rows.length - 1];
        selectPartRow(lastRow);

        var codeInput = getInputByNameFromRow(lastRow, "CODE[]");

        if (codeInput) {
            codeInput.focus();
            codeInput.select();
        }
    }
};

function getPartLookupData(q) {
    var data = [];

    data.push("CUST_CODE=" + enc(document.getElementById("CUST_CODE").value));
    data.push("START_DATE=" + enc(document.getElementById("DI_START_DATE").value));
    data.push("END_DATE=" + enc(document.getElementById("DI_DATE").value));
    data.push("q=" + enc(q));

    return data.join("&");
}

function loadPartLookup(input) {
    var custCode = document.getElementById("CUST_CODE").value;
    var startDate = document.getElementById("DI_START_DATE").value;
    var endDate = document.getElementById("DI_DATE").value;

    if (custCode == "") {
        document.getElementById("LabelStatus").innerHTML = "Pilih customer dulu.";
        return;
    }

    if (startDate == "" || endDate == "") {
        document.getElementById("LabelStatus").innerHTML = "Start Date / DI Date belum diisi.";
        return;
    }

    var q = input.value;

    document.getElementById("LabelStatus").innerHTML = "Loading lookup part...";

    ajaxPost("ajax_part_lookup.php", getPartLookupData(q), function (status, responseText) {
        if (status != 200) {
            document.getElementById("LabelStatus").innerHTML =
                "Lookup part gagal. HTTP Error: " + status;
            renderPartSuggest(input, []);
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            document.getElementById("LabelStatus").innerHTML =
                "Lookup part gagal. Response bukan JSON.";
            renderPartSuggest(input, []);
            return;
        }

        if (result.success === false) {
            document.getElementById("LabelStatus").innerHTML = result.message;
            renderPartSuggest(input, []);
            return;
        }

        if (result.rows && result.rows.length > 0) {
            document.getElementById("LabelStatus").innerHTML =
                "Lookup part ditemukan: " + result.rows.length + " item.";

            renderPartSuggest(input, result.rows);
            return;
        }

        document.getElementById("LabelStatus").innerHTML =
            "Lookup part kosong untuk customer/tanggal ini.";

        renderPartSuggest(input, []);
    });
}

function setRowPartFromLookup(input, item) {
    var row = getRowFromElement(input);

    if (!row) {
        return;
    }

    var codeInput     = getInputByNameFromRow(row, "CODE[]");
    var nameInput     = getInputByNameFromRow(row, "NAME[]");
    var qtyInput      = getInputByNameFromRow(row, "DIPA_QTY[]");
    var packQtyInput  = getInputByNameFromRow(row, "DIPA_PQTY[]");
    var packDescInput = getInputByNameFromRow(row, "PACK_DESC[]");
    var priceInput    = getInputByNameFromRow(row, "PRICE_ID[]");
    var packIdInput   = getInputByNameFromRow(row, "PACK_ID[]");

    if (codeInput) {
        codeInput.value = item.CODE;
    }

    if (nameInput) {
        nameInput.value = item.NAME;
    }

    if (qtyInput) {
        qtyInput.value = item.DAILY_SCH;
    }

    if (packQtyInput) {
        packQtyInput.value = item.DIPA_PQTY;
    }

    if (packDescInput) {
        packDescInput.value = item.PACK_DESC;
    }

    if (priceInput) {
        priceInput.value = item.PRICE_ID;
    }

    if (packIdInput) {
        packIdInput.value = item.PACK_ID;
    }

    var box = input.parentNode.getElementsByClassName("partSuggest")[0];

    if (box) {
        box.style.display = "none";
    }

    selectPartRow(row);

    if (qtyInput) {
        qtyInput.focus();
        qtyInput.select();
    }
}

function renderPartSuggest(input, items) {
    var box = input.parentNode.getElementsByClassName("partSuggest")[0];

    if (!box) {
        return;
    }

    box.innerHTML = "";
    partItems = items || [];
    partActiveInput = input;
    partActiveIndex = -1;

    if (!items || items.length == 0) {
        box.style.display = "none";
        return;
    }

    for (var i = 0; i < items.length; i++) {
        (function (idx) {
            var div = document.createElement("div");
            div.className = "autocomplete-item";
            div.setAttribute("data-index", idx);

            div.innerHTML =
                htmlEncode(items[idx].CODE) +
                " - " +
                htmlEncode(items[idx].NAME) +
                "<br>Sch: " +
                htmlEncode(items[idx].DAILY_SCH) +
                " | Price ID: " +
                htmlEncode(items[idx].PRICE_ID);

            div.onmouseover = function () {
                setActivePartSuggest(input, idx);
            };

            div.onmousedown = function (e) {
                if (!e) {
                    e = window.event;
                }

                if (e.preventDefault) {
                    e.preventDefault();
                }

                /*
                    Klik mouse hanya pilih item, belum auto save.
                    Kalau mau klik juga langsung save, ubah false jadi true.
                */
                choosePartSuggest(input, idx, false);
            };

            box.appendChild(div);
        })(i);
    }

    box.style.display = "block";
    setActivePartSuggest(input, 0);
}


document.getElementById("tblPart").addEventListener("focusin", function (e) {
    e = e || window.event;

    var target = e.target || e.srcElement;

    if (!target || target.name != "CODE[]") {
        return;
    }

    loadPartLookup(target);
});

document.getElementById("tblPart").addEventListener("keyup", function (e) {
    e = e || window.event;

    var target = e.target || e.srcElement;

    if (!target || target.name != "CODE[]") {
        return;
    }

    var key = e.keyCode || e.which;

    if (key == 13 || key == 38 || key == 40) {
        return;
    }

    loadPartLookup(target);
});

document.getElementById("tblPart").addEventListener("blur", function (e) {
    e = e || window.event;

    var target = e.target || e.srcElement;

    if (!target || target.name != "CODE[]") {
        return;
    }

    setTimeout(function () {
        var box = target.parentNode.getElementsByClassName("partSuggest")[0];

        if (box) {
            box.style.display = "none";
        }
    }, 250);

}, true);

function getDeleteHeaderData() {
    var data = [];

    data.push("DI_ID=" + enc(document.getElementById("DI_ID").value));

    return data.join("&");
}

function clearHeaderAfterDelete() {
    document.getElementById("DI_ID").value = "";
    document.getElementById("DI_NO").value = "";
    document.getElementById("DI_START_DATE").value = "";
    document.getElementById("DI_DATE").value = "";

    document.getElementById("CUST_CODE").value = "";
    document.getElementById("CUST_COMP").value = "";
    document.getElementById("CUST_ABBR").value = "";
    document.getElementById("CUST_ID").value = "";

    document.getElementById("DI_DSNO").value = "";
    document.getElementById("DI_INVNO").value = "";
    document.getElementById("DI_ORDERNO").value = "";

    if (typeof clearPartGrid == "function") {
        clearPartGrid();
    }

    if (typeof resetSmallGrids == "function") {
        resetSmallGrids();
    }

    if (typeof clearPOGrids == "function") {
        clearPOGrids();
    }

    selectedPartRow = null;
}

document.getElementById("btnDeleteHeader").onclick = function () {
    var diId = document.getElementById("DI_ID").value;
    var diNo = document.getElementById("DI_NO").value;
    var dsNo = document.getElementById("DI_DSNO").value;
    var invNo = document.getElementById("DI_INVNO").value;

    if (diId == "") {
        alert("DI_ID kosong. Cari data DI dulu sebelum DELETE HEADER.");
        return;
    }

    if (!confirm(
        "DELETE HEADER DI?\n\n" +
        "DI_ID  : " + diId + "\n" +
        "DI_NO  : " + diNo + "\n" +
        "DS NO  : " + dsNo + "\n" +
        "INV NO : " + invNo + "\n\n" +
        "Header hanya bisa dihapus jika belum ada detail.\n\n" +
        "Klik OK untuk lanjut.\n" +
        "Klik Cancel untuk batal."
    )) {
        document.getElementById("LabelStatus").innerHTML = "DELETE HEADER dibatalkan.";
        return;
    }

    document.getElementById("LabelStatus").innerHTML = "Delete header sedang diproses...";
    document.getElementById("ProgressBar1").value = 30;

    ajaxPost("ajax_delete_header.php", getDeleteHeaderData(), function (status, responseText) {
        document.getElementById("ProgressBar1").value = 100;

        if (status != 200) {
            alert("HTTP Error: " + status);
            document.getElementById("LabelStatus").innerHTML = "DELETE HEADER gagal.";
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            alert("Response bukan JSON:\n\n" + responseText);
            document.getElementById("LabelStatus").innerHTML = "DELETE HEADER gagal.";
            return;
        }

        if (!result.success) {
            alert(result.message);
            document.getElementById("LabelStatus").innerHTML = "DELETE HEADER gagal.";
            return;
        }

        alert(result.message);

        clearHeaderAfterDelete();

        document.getElementById("LabelStatus").innerHTML = "Header berhasil dihapus.";
    });
};

</script>

</body>
</html>