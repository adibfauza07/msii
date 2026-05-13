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
            width: 1180px;
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
            grid-template-columns: 70px 90px 260px 80px 1fr;
            gap: 4px;
            align-items: center;
            margin-bottom: 8px;
        }

        .top-area {
            display: grid;
            grid-template-columns: 80px 120px 90px 130px 70px 130px 80px 130px;
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
            max-height: 190px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid #808080;
            box-shadow: 2px 2px 3px #808080;
        }

        .autocomplete-item {
            padding: 4px;
            cursor: pointer;
            border-bottom: 1px solid #dddddd;
            line-height: 16px;
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

        .middle-title {
            margin-top: 6px;
            margin-bottom: 3px;
            font-weight: bold;
        }

        .bottom-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
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
        Database: <b>msData</b> |
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
            <input type="text" name="DI_DSNO" id="DI_DSNO" readonly>

            <label>INV NO :</label>
            <input type="text" name="DI_INVNO" id="DI_INVNO" readonly>

            <label>ORDER NO :</label>
            <input type="text" name="DI_ORDERNO" id="DI_ORDERNO">

        </div>

        <div class="button-row">
            <button type="button" id="btnNew">NEW</button>
            <button type="button" id="btnSaveHeader">SAVE HEADER</button>
            <button type="button" id="btnLoadOther">LOAD DI OTHER</button>
            <button type="button" id="btnLoadStaging">LOAD KOITO / STAGING</button>
            <button type="button" id="btnPostFifo">POST FIFO</button>
            <button type="button" id="btnReport">REPORT MENU</button>
        </div>

        <div class="status-text" id="LabelStatus">
            # NO TIDAK BOLEH DI RUBAH
        </div>

        <div class="middle-title">DETAIL DI PART</div>

        <table class="grid" id="tblPart">
            <thead>
                <tr>
                    <th style="width:45px;"># NO</th>
                    <th style="width:120px;">CODE</th>
                    <th>NAME</th>
                    <th style="width:80px;">QTY</th>
                    <th style="width:80px;">PACK QTY</th>
                    <th style="width:100px;">PACK DESC</th>
                    <th style="width:120px;">LOCATION</th>
                    <th style="width:80px;">PRICE ID</th>
                    <th style="width:70px;">PACK ID</th>
                    <th style="width:45px;">DEL</th>
                </tr>
            </thead>

            <tbody id="partBody">
                <?php for ($i = 1; $i <= 10; $i++) { ?>
                <tr onclick="selectPartRow(this)">
                    <td><input type="text" name="DIPA_LINO[]" value="<?php echo $i; ?>" readonly></td>
                    <td><input type="text" name="CODE[]"></td>
                    <td><input type="text" name="NAME[]"></td>
                    <td><input type="text" name="DIPA_QTY[]"></td>
                    <td><input type="text" name="DIPA_PQTY[]"></td>
                    <td><input type="text" name="PACK_DESC[]"></td>
                    <td><input type="text" name="LOCATION[]"></td>
                    <td><input type="text" name="PRICE_ID[]"></td>
                    <td><input type="text" name="PACK_ID[]"></td>
                    <td><button type="button" onclick="deleteRow(event, this)">X</button></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>

        <div class="button-row">
            <button type="button" onclick="addRow()">TAMBAH BARIS</button>
            <button type="button" id="btnDeleteLine">HAPUS PER BARIS</button>
            <button type="button" id="btnDeleteAll">BATAL SEMUA DETAIL</button>
            <button type="button" id="btnSaveDetail">SAVE DETAIL</button>
        </div>

        <div class="bottom-area">

            <div class="panel">
                <div class="panel-title">ORDER / PO AVAILABLE</div>

                <table class="small-grid" id="tblOrder">
                    <thead>
                        <tr>
                            <th>ORDR_DATE</th>
                            <th>ORDR_PO</th>
                            <th>QTY</th>
                            <th>DQTY</th>
                            <th>BQTY</th>
                            <th>ORDR_ID</th>
                            <th>LINO</th>
                        </tr>
                    </thead>
                    <tbody id="orderBody">
                        <tr>
                            <td colspan="7">Belum ada data.</td>
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
                            <th>ORDR_DATE</th>
                            <th>PO NUMBER</th>
                            <th>QTY</th>
                            <th>ORDR_ID</th>
                            <th>LINO</th>
                        </tr>
                    </thead>
                    <tbody id="poBody">
                        <tr>
                            <td colspan="5">Belum ada data.</td>
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
            <input type="text" name="REPORT_CUST_CODE" id="REPORT_CUST_CODE" value="%">

            <button type="button" id="btnReportDI">PRINT DELIVERY INSTRUCTION</button>

        </div>

        <div class="footer-row">

            <div>
                <button type="button" id="btnImportPO">IMPORT PO</button>
                <button type="button" id="btnImportSchedule">IMPORT SCHEDULE</button>
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
            "DI: " + htmlEncode(items[i].DI_NO) +
            " | INV: " + htmlEncode(items[i].DI_INVNO) +
            " | " + htmlEncode(items[i].CUST_CODE) +
            " - " + htmlEncode(items[i].CUST_COMP);

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


var diItems = [];

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
}

function addRow() {
    var tbody = document.getElementById("partBody");
    var no = tbody.rows.length + 1;
    var row = tbody.insertRow(-1);

    row.setAttribute("onclick", "selectPartRow(this)");

    row.innerHTML =
        '<td><input type="text" name="DIPA_LINO[]" value="' + no + '" readonly></td>' +
        '<td><input type="text" name="CODE[]"></td>' +
        '<td><input type="text" name="NAME[]"></td>' +
        '<td><input type="text" name="DIPA_QTY[]"></td>' +
        '<td><input type="text" name="DIPA_PQTY[]"></td>' +
        '<td><input type="text" name="PACK_DESC[]"></td>' +
        '<td><input type="text" name="LOCATION[]"></td>' +
        '<td><input type="text" name="PRICE_ID[]"></td>' +
        '<td><input type="text" name="PACK_ID[]"></td>' +
        '<td><button type="button" onclick="deleteRow(event, this)">X</button></td>';
}

function addPartRowFromData(r) {
    var tbody = document.getElementById("partBody");
    var row = tbody.insertRow(-1);

    row.setAttribute("onclick", "selectPartRow(this)");

    row.innerHTML =
        '<td><input type="text" name="DIPA_LINO[]" value="' + htmlEncode(r.DIPA_LINO) + '" readonly></td>' +
        '<td><input type="text" name="CODE[]" value="' + htmlEncode(r.CODE) + '"></td>' +
        '<td><input type="text" name="NAME[]" value="' + htmlEncode(r.NAME) + '"></td>' +
        '<td><input type="text" name="DIPA_QTY[]" value="' + htmlEncode(r.DIPA_QTY) + '"></td>' +
        '<td><input type="text" name="DIPA_PQTY[]" value="' + htmlEncode(r.DIPA_PQTY) + '"></td>' +
        '<td><input type="text" name="PACK_DESC[]" value="' + htmlEncode(r.PACK_DESC) + '"></td>' +
        '<td><input type="text" name="LOCATION[]" value="' + htmlEncode(r.LOCATION) + '"></td>' +
        '<td><input type="text" name="PRICE_ID[]" value="' + htmlEncode(r.PRICE_ID) + '"></td>' +
        '<td><input type="text" name="PACK_ID[]" value="' + htmlEncode(r.PACK_ID) + '"></td>' +
        '<td><button type="button" onclick="deleteRow(event, this)">X</button></td>';
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
    row.parentNode.removeChild(row);

    selectedPartRow = null;
    renumberRows();
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
    document.getElementById("orderBody").innerHTML =
        '<tr><td colspan="7">Belum ada data.</td></tr>';

    document.getElementById("poBody").innerHTML =
        '<tr><td colspan="5">Belum ada data.</td></tr>';

    selectedOrderRow = null;
    selectedPORow = null;
}

function getHeaderPostData() {
    var data = [];

    data.push("DI_ID=" + enc(document.getElementById("DI_ID").value));
    data.push("DI_NO=" + enc(document.getElementById("DI_NO").value));
    data.push("DI_START_DATE=" + enc(document.getElementById("DI_START_DATE").value));
    data.push("DI_DATE=" + enc(document.getElementById("DI_DATE").value));
    data.push("CUST_CODE=" + enc(document.getElementById("CUST_CODE").value));
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
    document.getElementById("DI_ID").value = h.DI_ID;
    document.getElementById("DI_NO").value = h.DI_NO;
    document.getElementById("DI_START_DATE").value = h.DI_START_DATE;
    document.getElementById("DI_DATE").value = h.DI_DATE;
    document.getElementById("DI_DSNO").value = h.DI_DSNO;
    document.getElementById("DI_INVNO").value = h.DI_INVNO;
    document.getElementById("DI_ORDERNO").value = h.DI_ORDERNO;

    document.getElementById("CUST_CODE").value = h.CUST_CODE;
    document.getElementById("CUST_SEARCH").value = h.CUST_CODE;
    document.getElementById("CUST_ID").value = h.CUST_ID;
    document.getElementById("CUST_COMP").value = h.CUST_COMP;
    document.getElementById("CUST_ABBR").value = h.CUST_ABBR;
    document.getElementById("REPORT_CUST_CODE").value = h.CUST_CODE;
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
    var custCode = document.getElementById("CUST_CODE").value;
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

    if (!confirm(
        "LOAD DI OTHER dengan parameter:\n\n" +
        "DI_ID     : " + diId + "\n" +
        "CUST_CODE : " + custCode + "\n" +
        "START    : " + startDate + "\n" +
        "END      : " + endDate + "\n\n" +
        "Detail lama untuk DI ini akan dihapus dan diisi ulang.\n\n" +
        "Lanjut?"
    )) {
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

        document.getElementById("LabelStatus").innerHTML = result.message;
        alert(result.message);
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
        resetSmallGrids();

        document.getElementById("LabelStatus").innerHTML =
            "Data DI ditemukan. DI_ID: " + result.header.DI_ID;
    });
};

document.getElementById("btnDeleteLine").onclick = function () {
    if (selectedPartRow == null) {
        alert("Pilih baris detail dulu.");
        return;
    }

    if (confirm("Hapus baris ini dari tampilan?")) {
        selectedPartRow.parentNode.removeChild(selectedPartRow);
        selectedPartRow = null;
        renumberRows();
    }
};

document.getElementById("btnDeleteAll").onclick = function () {
    if (!confirm("Hapus semua baris detail di tampilan?")) {
        return;
    }

    clearPartGrid();
    document.getElementById("LabelStatus").innerHTML = "Semua detail di tampilan dikosongkan.";
};

document.getElementById("btnSaveDetail").onclick = function () {
    alert("Step berikutnya: SAVE DETAIL manual ke tabel DI_PART.");
};

document.getElementById("btnLoadStaging").onclick = function () {
    alert("Step berikutnya: LOAD KOITO / STAGING.");
};

document.getElementById("btnPostFifo").onclick = function () {
    alert("Step berikutnya: POST FIFO.");
};

document.getElementById("btnAllocatePO").onclick = function () {
    alert("Step berikutnya: ALLOCATE PO.");
};

document.getElementById("btnRollbackPO").onclick = function () {
    alert("Step berikutnya: ROLLBACK PO.");
};

document.getElementById("btnReport").onclick = function () {
    alert("Step berikutnya: REPORT MENU.");
};

document.getElementById("btnReportDI").onclick = function () {
    alert("Step berikutnya: PRINT DELIVERY INSTRUCTION.");
};

document.getElementById("btnImportPO").onclick = function () {
    alert("Step berikutnya: IMPORT PO.");
};

document.getElementById("btnImportSchedule").onclick = function () {
    alert("Step berikutnya: IMPORT SCHEDULE.");
};

document.getElementById("btnEditOrder").onclick = function () {
    alert("Step berikutnya: EDIT ORDER.");
};

document.getElementById("tblPart").onkeydown = function (e) {
    e = e || window.event;

    var key = e.keyCode || e.which;

    if (key != 38 && key != 40) {
        return;
    }

    var target = e.target || e.srcElement;

    if (!target) {
        return;
    }

    if (
        target.tagName != "INPUT" &&
        target.tagName != "SELECT" &&
        target.tagName != "BUTTON"
    ) {
        return;
    }

    var cell = target.parentNode;
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

    var nextRowIndex = rowIndex;

    if (key == 38) {
        nextRowIndex = rowIndex - 1;
    }

    if (key == 40) {
        nextRowIndex = rowIndex + 1;
    }

    if (nextRowIndex < 0 || nextRowIndex >= rows.length) {
        return;
    }

    var nextCell = rows[nextRowIndex].cells[cellIndex];

    if (!nextCell) {
        return;
    }

    var nextInput = nextCell.getElementsByTagName("input")[0];

    if (!nextInput) {
        nextInput = nextCell.getElementsByTagName("select")[0];
    }

    if (!nextInput) {
        nextInput = nextCell.getElementsByTagName("button")[0];
    }

    if (nextInput) {
        if (e.preventDefault) {
            e.preventDefault();
        } else {
            e.returnValue = false;
        }

        selectPartRow(rows[nextRowIndex]);
        nextInput.focus();

        if (nextInput.select && nextInput.tagName == "INPUT") {
            nextInput.select();
        }
    }
};

document.getElementById("tblPart").onfocusin = function (e) {
    e = e || window.event;

    var target = e.target || e.srcElement;

    if (!target) {
        return;
    }

    var row = target;

    while (row && row.tagName != "TR") {
        row = row.parentNode;
    }

    if (row && row.parentNode && row.parentNode.id == "partBody") {
        selectPartRow(row);
    }
};
</script>

</body>
</html>