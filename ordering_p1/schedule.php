<?php
require_once __DIR__ . "/../config/database_ordering.php";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Schedule Plant 2</title>

    <style>
        body {
            margin: 0;
            background: #9a9a9a;
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
        }

        .form-box {
            width: 1120px;
            min-height: 520px;
            margin: 15px auto;
            background: #078b83;
            border: 1px solid #333;
            box-sizing: border-box;
            padding: 10px;
        }

        .title {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }

        label {
            font-weight: bold;
            display: block;
            margin-top: 6px;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"] {
            height: 24px;
            border: 1px solid #777;
            padding: 2px 4px;
            box-sizing: border-box;
            font-size: 12px;
        }

        input[readonly] {
            background: #e6e6e6;
        }

        button {
            height: 25px;
            min-width: 80px;
            font-size: 12px;
            cursor: pointer;
            margin-right: 3px;
        }

        .layout {
            display: grid;
            grid-template-columns: 800px 1fr;
            gap: 12px;
        }

        .left-panel {
            min-width: 0;
        }

        .right-panel {
            min-width: 0;
            background: #d3d0c8;
            border: 1px solid #555;
            padding: 5px;
        }

        .cust-code {
            width: 90px;
        }

        .cust-name {
            width: 340px;
        }

        .item-search {
            width: 360px;
        }

        .status {
            color: navy;
            font-weight: bold;
            min-height: 18px;
            margin: 8px 0;
        }

        .toolbar {
            margin: 10px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #888;
            padding: 2px 4px;
            height: 23px;
            font-size: 12px;
            box-sizing: border-box;
            white-space: nowrap;
            overflow: hidden;
        }

        th {
            background: #d9d6ce;
            font-weight: normal;
            text-align: left;
        }

        .item-table .col-code {
            width: 95px;
        }

        .item-table .col-prcd {
            width: 110px;
        }

        .item-table .col-no {
            width: 140px;
        }

        .item-table .col-name {
            width: auto;
        }

        .item-table .col-delivery {
            width: 85px;
            text-align: right;
        }

        .item-table .col-bal {
            width: 75px;
            text-align: right;
        }

        .item-table tbody tr {
            cursor: pointer;
        }

        .item-table tr:focus {
            outline: 2px solid #0033ff;
        }

        .schedule-table .col-date {
            width: 140px;
        }

        .schedule-table .col-qty {
            width: 115px;
            text-align: right;
        }

        .schedule-table input {
            width: 100%;
            height: 21px;
            border: 1px solid #999;
            box-sizing: border-box;
            font-size: 12px;
        }

        .num {
            text-align: right;
        }

        .selected {
            background: red;
            color: #fff;
        }

        .selected input {
            color: #000;
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
            background: #fff;
            border: 1px solid #444;
            z-index: 9999;
            display: none;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.25);
        }

        .autocomplete-item {
            padding: 5px 7px;
            border-bottom: 1px solid #ddd;
            cursor: pointer;
            line-height: 16px;
        }

        .autocomplete-item:hover,
        .autocomplete-item.active {
            background: #2f70c9;
            color: #fff;
        }

        .autocomplete-main {
            font-weight: bold;
        }

        .autocomplete-sub {
            color: #555;
            font-size: 11px;
        }

        .autocomplete-item:hover .autocomplete-sub,
        .autocomplete-item.active .autocomplete-sub {
            color: #fff;
        }

        #itemSearchSuggest {
            width: 620px;
            max-height: 220px;
        }

        .small-info {
            font-size: 11px;
            color: navy;
            font-weight: bold;
            margin-bottom: 5px;
        }
    </style>
</head>

<body>

<div class="form-box">
    <div class="title">DELIVERY SCHEDULE</div>

    <input type="hidden" id="CUST_ID" value="">
    <input type="hidden" id="PRICE_ID" value="">

    <div class="layout">
        <div class="left-panel">
            <label>CUST CODE</label>
            <div class="autocomplete-wrap">
                <input type="text" id="CUST_CODE" class="cust-code" autocomplete="off">
                <div id="custSuggest" class="autocomplete-list"></div>
            </div>

            <label>CUST NAME</label>
            <input type="text" id="CUST_COMP" class="cust-name" readonly>

            <div class="toolbar">
                <button type="button" id="btnRefreshItem">REFRESH ITEM</button>
              
            </div>

            <label>CARI ITEM CODE / NAME</label>
            <div class="autocomplete-wrap">
                <input type="text" id="ITEM_SEARCH" class="item-search" autocomplete="off" placeholder="Ketik part code / part name / part no">
                <div id="itemSearchSuggest" class="autocomplete-list"></div>
            </div>

            <div class="status" id="LabelStatus"></div>

            <table class="item-table">
                <thead>
                    <tr>
                        <th class="col-code">PART_CODE</th>
                        <th class="col-prcd">PRCD</th>
                        <th class="col-no">PART_NO</th>
                        <th class="col-name">PART_NAME</th>
                        <th class="col-delivery">DELIVERY</th>
                        <th class="col-bal">BAL</th>
                    </tr>
                </thead>
                <tbody id="itemBody">
                    <tr>
                        <td colspan="6">Pilih customer dulu.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="right-panel">
            <div class="small-info" id="selectedPartInfo">Belum pilih part.</div>

            <table class="schedule-table">
                <thead>
                    <tr>
                        <th class="col-date">DELS_DATE</th>
                        <th class="col-qty">DELS_QTY</th>
                    </tr>
                </thead>
                <tbody id="scheduleBody">
                    <tr>
                        <td colspan="2">Belum ada data.</td>
                    </tr>
                </tbody>
            </table>

            <div class="toolbar">
                <button type="button" id="btnAddSchedule">+</button>
                <button type="button" id="btnDeleteSchedule">-</button>
                <button type="button" id="btnSaveSchedule">SIMPAN</button>
                <button type="button" id="btnReloadSchedule">REFRESH</button>
            </div>
        </div>
    </div>
</div>

<script>
var custTimer = null;
var custRows = [];
var custIndex = -1;

var selectedItemRow = null;
var selectedScheduleRow = null;
var scheduleLoadSeq = 0;

var itemRowsAll = [];
var itemRowsCache = [];
var itemSuggestRows = [];
var itemSuggestIndex = -1;
var itemSearchTimer = null;

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

function setStatus(text) {
    document.getElementById("LabelStatus").innerHTML = text;
}

function hideCustSuggest() {
    var box = document.getElementById("custSuggest");

    if (box) {
        box.style.display = "none";
        box.innerHTML = "";
    }

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

    if (items[index].offsetTop < box.scrollTop) {
        box.scrollTop = items[index].offsetTop;
    } else if ((items[index].offsetTop + items[index].offsetHeight) > (box.scrollTop + box.clientHeight)) {
        box.scrollTop = items[index].offsetTop + items[index].offsetHeight - box.clientHeight;
    }
}

function chooseCust(index) {
    if (index < 0 || index >= custRows.length) {
        return;
    }

    var r = custRows[index];

    document.getElementById("CUST_ID").value = r.CUST_ID;
    document.getElementById("CUST_CODE").value = r.CUST_CODE;
    document.getElementById("CUST_COMP").value = r.CUST_COMP;

    document.getElementById("PRICE_ID").value = "";
    document.getElementById("ITEM_SEARCH").value = "";
    document.getElementById("selectedPartInfo").innerHTML = "Belum pilih part.";
    document.getElementById("scheduleBody").innerHTML = '<tr><td colspan="2">Belum ada data.</td></tr>';

    hideCustSuggest();
    loadItems();
}

function showCustSuggest(rows) {
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
                '<div class="autocomplete-main">' + htmlEncode(r.CUST_CODE) + '</div>' +
                '<div class="autocomplete-sub">' + htmlEncode(r.CUST_COMP) + '</div>';

            div.onmouseover = function () {
                setActiveCust(idx);
            };

            div.onclick = function () {
                chooseCust(idx);
            };

            box.appendChild(div);
        })(rows[i], i);
    }

    box.style.display = "block";
    setActiveCust(0);
}

function searchCustomer() {
    var q = document.getElementById("CUST_CODE").value;

    if (q.length < 1) {
        hideCustSuggest();
        return;
    }

    ajaxPost("ajax_schedule_customer.php", "q=" + enc(q), function (status, responseText) {
        if (status != 200) {
            hideCustSuggest();
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            hideCustSuggest();
            return;
        }

        if (!result.success) {
            hideCustSuggest();
            return;
        }

        showCustSuggest(result.rows);
    });
}

function custKeyDown(e) {
    e = e || window.event;

    var key = e.keyCode || e.which;
    var box = document.getElementById("custSuggest");

    if (!box || box.style.display != "block") {
        return true;
    }

    if (key == 40) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        setActiveCust(custIndex + 1);
        return false;
    }

    if (key == 38) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        setActiveCust(custIndex - 1);
        return false;
    }

    if (key == 13) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;

        if (custIndex < 0 && custRows.length > 0) {
            custIndex = 0;
        }

        chooseCust(custIndex);
        return false;
    }

    return true;
}

function loadItems() {
    var custId = document.getElementById("CUST_ID").value;

    if (custId == "") {
        alert("Pilih customer dulu.");
        document.getElementById("CUST_CODE").focus();
        return;
    }

    setStatus("Loading item schedule...");

    ajaxPost("ajax_schedule_items.php", "CUST_ID=" + enc(custId), function (status, responseText) {
        if (status != 200) {
            alert("HTTP Error: " + status);
            setStatus("Load item gagal.");
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            alert("Response bukan JSON:\n\n" + responseText);
            setStatus("Load item gagal.");
            return;
        }

        if (!result.success) {
            alert(result.message);
            setStatus("Load item gagal.");
            return;
        }

        renderItems(result.rows);
    });
}

function getItemRows() {
    return document.getElementById("itemBody").getElementsByTagName("tr");
}

function getSelectedItemIndex() {
    var rows = getItemRows();

    for (var i = 0; i < rows.length; i++) {
        if (rows[i] === selectedItemRow) {
            return i;
        }
    }

    return -1;
}

function moveItemSelection(direction) {
    var rows = getItemRows();

    if (!rows || rows.length == 0) {
        return;
    }

    var index = getSelectedItemIndex();

    if (index < 0) {
        index = 0;
    } else {
        index = index + direction;
    }

    if (index < 0) {
        index = 0;
    }

    if (index >= rows.length) {
        index = rows.length - 1;
    }

    selectItemRow(rows[index]);
    loadSchedule();

    if (rows[index].scrollIntoView) {
        rows[index].scrollIntoView({ block: "nearest" });
    }

    rows[index].focus();
}

function focusScheduleGrid() {
    var body = document.getElementById("scheduleBody");
    var rows = body.getElementsByTagName("tr");

    if (!rows || rows.length == 0) {
        addScheduleRow();
        rows = body.getElementsByTagName("tr");
    }

    if (rows.length > 0) {
        var lastRow = rows[rows.length - 1];

        selectScheduleRow(lastRow);

        var qtyInput = getScheduleInput(lastRow, "DELS_QTY");
        var dateInput = getScheduleInput(lastRow, "DELS_DATE");

        if (qtyInput) {
            qtyInput.focus();
            qtyInput.select();
        } else if (dateInput) {
            dateInput.focus();
            dateInput.select();
        }

        if (lastRow.scrollIntoView) {
            lastRow.scrollIntoView({ block: "nearest" });
        }
    }
}

function itemGridKeyDown(e) {
    e = e || window.event;

    var key = e.keyCode || e.which;

    if (key == 40) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        moveItemSelection(1);
        return false;
    }

    if (key == 38) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        moveItemSelection(-1);
        return false;
    }

    if (key == 13) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        loadSchedule();
        return false;
    }

    if (key == 9) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        focusScheduleGrid();
        return false;
    }

    return true;
}

function renderItems(rows) {
    itemRowsAll = rows || [];
    itemRowsCache = itemRowsAll;

    document.getElementById("ITEM_SEARCH").value = "";

    renderItemGrid(itemRowsAll);
    setStatus("Item loaded: " + itemRowsAll.length);
}

function renderItemGrid(rows) {
    var body = document.getElementById("itemBody");

    body.innerHTML = "";
    selectedItemRow = null;

    document.getElementById("PRICE_ID").value = "";
    document.getElementById("selectedPartInfo").innerHTML = "Belum pilih part.";
    document.getElementById("scheduleBody").innerHTML = '<tr><td colspan="2">Belum ada data.</td></tr>';

    if (!rows || rows.length == 0) {
        body.innerHTML = '<tr><td colspan="6">Item tidak ditemukan.</td></tr>';
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        (function (r) {
            var tr = document.createElement("tr");

            tr.setAttribute("tabindex", "0");
            tr.setAttribute("data-price-id", r.PRICE_ID);
            tr.setAttribute("data-part-code", r.ITEM_CODE);
            tr.setAttribute("data-part-name", r.ITEM_NAME);

            tr.innerHTML =
                '<td class="col-code">' + htmlEncode(r.ITEM_CODE) + '</td>' +
                '<td class="col-prcd">' + htmlEncode(r.PRICE_CODE) + '</td>' +
                '<td class="col-no">' + htmlEncode(r.ITEM_NO) + '</td>' +
                '<td class="col-name">' + htmlEncode(r.ITEM_NAME) + '</td>' +
                '<td class="col-delivery num">' + htmlEncode(r.DELIVERY_QTY) + '</td>' +
                '<td class="col-bal num">' + htmlEncode(r.BAL2) + '</td>';

            tr.onclick = function () {
                selectItemRow(this);
                loadSchedule();
                this.focus();
            };

            tr.ondblclick = function () {
                selectItemRow(this);
                loadSchedule();
                focusScheduleGrid();
            };

            tr.onkeydown = function (e) {
                return itemGridKeyDown(e);
            };

            body.appendChild(tr);
        })(rows[i]);
    }

    var firstRow = body.getElementsByTagName("tr")[0];

    if (firstRow) {
        selectItemRow(firstRow);
        loadSchedule();
        firstRow.focus();
    }
}

function selectItemRow(row) {
    var rows = document.getElementById("itemBody").getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        rows[i].className = "";
    }

    row.className = "selected";
    selectedItemRow = row;

    document.getElementById("PRICE_ID").value = row.getAttribute("data-price-id");

    document.getElementById("selectedPartInfo").innerHTML =
        "PRICE_ID: " + htmlEncode(row.getAttribute("data-price-id")) +
        " | " + htmlEncode(row.getAttribute("data-part-code")) +
        " - " + htmlEncode(row.getAttribute("data-part-name"));
}

function hideItemSearchSuggest() {
    var box = document.getElementById("itemSearchSuggest");

    if (box) {
        box.style.display = "none";
        box.innerHTML = "";
    }

    itemSuggestRows = [];
    itemSuggestIndex = -1;
}

function setActiveItemSuggest(index) {
    var box = document.getElementById("itemSearchSuggest");
    var items = box.getElementsByClassName("autocomplete-item");

    if (!items || items.length == 0) {
        itemSuggestIndex = -1;
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
    itemSuggestIndex = index;
}

function findItemRowByPriceId(priceId) {
    var rows = document.getElementById("itemBody").getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        if (rows[i].getAttribute("data-price-id") == priceId) {
            return rows[i];
        }
    }

    return null;
}

function chooseItemSuggest(index) {
    if (index < 0 || index >= itemSuggestRows.length) {
        return;
    }

    var r = itemSuggestRows[index];
    var row = findItemRowByPriceId(r.PRICE_ID);

    if (!row) {
        return;
    }

    document.getElementById("ITEM_SEARCH").value = r.ITEM_CODE + " - " + r.ITEM_NAME;

    hideItemSearchSuggest();

    selectItemRow(row);
    loadSchedule();

    row.focus();

    if (row.scrollIntoView) {
        row.scrollIntoView({ block: "nearest" });
    }
}

function showItemSearchSuggest(rows) {
    var box = document.getElementById("itemSearchSuggest");
    box.innerHTML = "";

    itemSuggestRows = rows || [];
    itemSuggestIndex = -1;

    if (!rows || rows.length == 0) {
        box.style.display = "none";
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        (function (r, idx) {
            var div = document.createElement("div");
            div.className = "autocomplete-item";

            div.innerHTML =
                '<div class="autocomplete-main">' +
                    htmlEncode(r.ITEM_CODE) + " - " + htmlEncode(r.ITEM_NAME) +
                '</div>' +
                '<div class="autocomplete-sub">' +
                    'PRCD: ' + htmlEncode(r.PRICE_CODE) +
                    ' | PART NO: ' + htmlEncode(r.ITEM_NO) +
                    ' | PRICE_ID: ' + htmlEncode(r.PRICE_ID) +
                    ' | BAL: ' + htmlEncode(r.BAL2) +
                '</div>';

            div.onmouseover = function () {
                setActiveItemSuggest(idx);
            };

            div.onclick = function () {
                chooseItemSuggest(idx);
            };

            box.appendChild(div);
        })(rows[i], i);
    }

    box.style.display = "block";
    setActiveItemSuggest(0);
}

function searchItemLocal() {
    var q = document.getElementById("ITEM_SEARCH").value.toLowerCase();
    var result = [];

    if (q.length < 1) {
        hideItemSearchSuggest();

        itemRowsCache = itemRowsAll;
        renderItemGrid(itemRowsAll);

        setStatus("Item loaded: " + itemRowsAll.length);
        return;
    }

    for (var i = 0; i < itemRowsAll.length; i++) {
        var r = itemRowsAll[i];

        var text = (
            String(r.ITEM_CODE || "") + " " +
            String(r.ITEM_NAME || "") + " " +
            String(r.ITEM_NO || "") + " " +
            String(r.PRICE_CODE || "")
        ).toLowerCase();

        if (text.indexOf(q) >= 0) {
            result.push(r);
        }

        if (result.length >= 10) {
            break;
        }
    }

    itemRowsCache = result;

    renderItemGrid(result);
    showItemSearchSuggest(result);

    setStatus("Hasil pencarian item: " + result.length + " dari " + itemRowsAll.length);
}

function itemSearchKeyDown(e) {
    e = e || window.event;

    var key = e.keyCode || e.which;
    var box = document.getElementById("itemSearchSuggest");

    if (!box || box.style.display != "block") {
        if (key == 13) {
            e.preventDefault ? e.preventDefault() : e.returnValue = false;

            searchItemLocal();

            if (itemSuggestRows.length > 0) {
                chooseItemSuggest(0);
            }

            return false;
        }

        return true;
    }

    if (key == 40) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        setActiveItemSuggest(itemSuggestIndex + 1);
        return false;
    }

    if (key == 38) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        setActiveItemSuggest(itemSuggestIndex - 1);
        return false;
    }

    if (key == 13) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;

        if (itemSuggestIndex < 0 && itemSuggestRows.length > 0) {
            itemSuggestIndex = 0;
        }

        chooseItemSuggest(itemSuggestIndex);
        return false;
    }

    return true;
}

function loadSchedule() {
    var priceId = document.getElementById("PRICE_ID").value;

    if (priceId == "") {
        return;
    }

    var currentSeq = ++scheduleLoadSeq;

    ajaxPost("ajax_schedule_load.php", "PRICE_ID=" + enc(priceId), function (status, responseText) {
        if (currentSeq != scheduleLoadSeq) {
            return;
        }

        if (status != 200) {
            alert("HTTP Error: " + status);
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            alert("Response bukan JSON:\n\n" + responseText);
            return;
        }

        if (!result.success) {
            alert(result.message);
            return;
        }

        renderSchedule(result.rows);
    });
}

function renderSchedule(rows) {
    var body = document.getElementById("scheduleBody");

    body.innerHTML = "";
    selectedScheduleRow = null;

    if (!rows || rows.length == 0) {
        addScheduleRow();
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        addScheduleRow(rows[i]);
    }

    var firstRow = body.getElementsByTagName("tr")[0];

    if (firstRow) {
        selectScheduleRow(firstRow);
    }
}

function selectScheduleRow(row) {
    var rows = document.getElementById("scheduleBody").getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        rows[i].className = "";
    }

    row.className = "selected";
    selectedScheduleRow = row;
}

function getScheduleInput(row, name) {
    var inputs = row.getElementsByTagName("input");

    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].getAttribute("data-name") == name) {
            return inputs[i];
        }
    }

    return null;
}

function getNextScheduleDate() {
    var body = document.getElementById("scheduleBody");
    var rows = body.getElementsByTagName("tr");
    var maxValue = "";

    for (var i = 0; i < rows.length; i++) {
        var dateInput = getScheduleInput(rows[i], "DELS_DATE");

        if (dateInput && dateInput.value != "" && dateInput.value > maxValue) {
            maxValue = dateInput.value;
        }
    }

    if (maxValue != "") {
        var d = new Date(maxValue + "T00:00:00");
        d.setDate(d.getDate() + 1);

        var y = d.getFullYear();
        var m = d.getMonth() + 1;
        var day = d.getDate();

        return y + "-" + (m < 10 ? "0" + m : m) + "-" + (day < 10 ? "0" + day : day);
    }

    var now = new Date();
    var yy = now.getFullYear();
    var mm = now.getMonth() + 1;
    var dd = now.getDate();

    return yy + "-" + (mm < 10 ? "0" + mm : mm) + "-" + (dd < 10 ? "0" + dd : dd);
}

function getScheduleRows() {
    return document.getElementById("scheduleBody").getElementsByTagName("tr");
}

function getSelectedScheduleIndex() {
    var rows = getScheduleRows();

    for (var i = 0; i < rows.length; i++) {
        if (rows[i] === selectedScheduleRow) {
            return i;
        }
    }

    return -1;
}

function moveScheduleSelection(direction, fieldName) {
    var rows = getScheduleRows();

    if (!rows || rows.length == 0) {
        addScheduleRow();
        rows = getScheduleRows();
    }

    var index = getSelectedScheduleIndex();

    if (index < 0) {
        index = 0;
    } else {
        index = index + direction;
    }

    /*
        Jika tekan panah bawah saat posisi sudah di baris terakhir,
        otomatis tambah baris baru.
    */
    if (direction > 0 && index >= rows.length) {
        addScheduleRow();

        rows = getScheduleRows();
        index = rows.length - 1;
    }

    if (index < 0) {
        index = 0;
    }

    if (index >= rows.length) {
        index = rows.length - 1;
    }

    selectScheduleRow(rows[index]);

    var input = getScheduleInput(rows[index], fieldName);

    if (input) {
        input.focus();
        input.select();
    }

    if (rows[index].scrollIntoView) {
        rows[index].scrollIntoView({ block: "nearest" });
    }
}

function addScheduleRow(data) {
    data = data || {};

    var body = document.getElementById("scheduleBody");

    if (body.getElementsByTagName("td").length == 1 && body.innerHTML.indexOf("Belum ada") >= 0) {
        body.innerHTML = "";
    }

    var tr = document.createElement("tr");

    tr.onclick = function () {
        selectScheduleRow(this);
    };

    var dateValue = data.DELS_DATE || getNextScheduleDate();

    var qtyValue = "";
    if (data.hasOwnProperty("DELS_QTY")) {
        qtyValue = data.DELS_QTY;
    }

    tr.innerHTML =
        '<td class="col-date">' +
            '<input type="date" data-name="DELS_DATE" value="' + htmlEncode(dateValue) + '">' +
        '</td>' +
        '<td class="col-qty">' +
            '<input type="number" class="num" data-name="DELS_QTY" value="' + htmlEncode(qtyValue) + '">' +
        '</td>';

    body.appendChild(tr);

    var dateInput = getScheduleInput(tr, "DELS_DATE");
    var qtyInput = getScheduleInput(tr, "DELS_QTY");

    dateInput.onfocus = function () {
        selectScheduleRow(tr);
    };

    qtyInput.onfocus = function () {
        selectScheduleRow(tr);
    };

    dateInput.onkeydown = function (e) {
        e = e || window.event;

        var key = e.keyCode || e.which;

        if (key == 13 || key == 9) {
            e.preventDefault ? e.preventDefault() : e.returnValue = false;
            qtyInput.focus();
            qtyInput.select();
            return false;
        }

        if (key == 40) {
            e.preventDefault ? e.preventDefault() : e.returnValue = false;
            moveScheduleSelection(1, "DELS_DATE");
            return false;
        }

        if (key == 38) {
            e.preventDefault ? e.preventDefault() : e.returnValue = false;
            moveScheduleSelection(-1, "DELS_DATE");
            return false;
        }

        return true;
    };

    qtyInput.onkeydown = function (e) {
        e = e || window.event;

        var key = e.keyCode || e.which;

        if (key == 13 || key == 9) {
            e.preventDefault ? e.preventDefault() : e.returnValue = false;

            saveSchedule(function (ok) {
                if (ok) {
                    addScheduleRow();

                    var rows = document.getElementById("scheduleBody").getElementsByTagName("tr");
                    var lastRow = rows[rows.length - 1];

                    selectScheduleRow(lastRow);
                    getScheduleInput(lastRow, "DELS_DATE").focus();
                }
            });

            return false;
        }

        if (key == 40) {
            e.preventDefault ? e.preventDefault() : e.returnValue = false;
            moveScheduleSelection(1, "DELS_QTY");
            return false;
        }

        if (key == 38) {
            e.preventDefault ? e.preventDefault() : e.returnValue = false;
            moveScheduleSelection(-1, "DELS_QTY");
            return false;
        }

        return true;
    };

    selectScheduleRow(tr);
}

function collectScheduleRows() {
    var body = document.getElementById("scheduleBody");
    var trs = body.getElementsByTagName("tr");
    var rows = [];
    var checkDate = {};

    for (var i = 0; i < trs.length; i++) {
        var dateInput = getScheduleInput(trs[i], "DELS_DATE");
        var qtyInput = getScheduleInput(trs[i], "DELS_QTY");

        if (!dateInput || !qtyInput) {
            continue;
        }

        var dateValue = dateInput.value;
        var qtyText = qtyInput.value;

        if (dateValue == "") {
            continue;
        }

        if (qtyText == "") {
            qtyText = "0";
        }

        var qty = parseInt(qtyText, 10);

        if (isNaN(qty) || qty < 0) {
            alert("DELS_QTY tidak valid pada date " + dateValue);
            qtyInput.focus();
            return false;
        }

        if (checkDate[dateValue]) {
            alert("Tanggal duplicate di grid: " + dateValue);
            dateInput.focus();
            return false;
        }

        checkDate[dateValue] = true;

        rows.push({
            DELS_DATE: dateValue,
            DELS_QTY: qty,
            DELS_C1: 0,
            DELS_C2: 0
        });
    }

    return rows;
}

function saveSchedule(callback) {
    var priceId = document.getElementById("PRICE_ID").value;

    if (priceId == "") {
        alert("Pilih item dulu.");

        if (callback) {
            callback(false);
        }

        return;
    }

    var rows = collectScheduleRows();

    if (rows === false) {
        if (callback) {
            callback(false);
        }

        return;
    }

    if (rows.length == 0) {
        alert("Schedule kosong.");

        if (callback) {
            callback(false);
        }

        return;
    }

    ajaxPost(
        "ajax_schedule_save.php",
        "PRICE_ID=" + enc(priceId) + "&ROWS_JSON=" + enc(JSON.stringify(rows)),
        function (status, responseText) {
            if (status != 200) {
                alert("HTTP Error: " + status);

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

                if (callback) {
                    callback(false);
                }

                return;
            }

            if (!result.success) {
                alert(result.message);

                if (callback) {
                    callback(false);
                }

                return;
            }

            setStatus(result.message);

            if (callback) {
                callback(true);
            }
        }
    );
}

function deleteScheduleRow() {
    if (!selectedScheduleRow) {
        alert("Pilih schedule yang mau dihapus.");
        return;
    }

    var priceId = document.getElementById("PRICE_ID").value;
    var dateInput = getScheduleInput(selectedScheduleRow, "DELS_DATE");

    /*
        Kalau baris baru belum ada tanggal, hapus dari layar saja.
        Setelah hapus, kalau kosong otomatis tambah 1 baris kosong lagi.
    */
    if (!dateInput || dateInput.value == "") {
        selectedScheduleRow.parentNode.removeChild(selectedScheduleRow);
        selectedScheduleRow = null;

        if (document.getElementById("scheduleBody").getElementsByTagName("tr").length == 0) {
            addScheduleRow();
        }

        return;
    }

    if (priceId == "") {
        alert("PRICE_ID kosong.");
        return;
    }

    if (!confirm("Hapus schedule date " + dateInput.value + "?")) {
        return;
    }

    ajaxPost(
        "ajax_schedule_delete.php",
        "PRICE_ID=" + enc(priceId) + "&DELS_DATE=" + enc(dateInput.value),
        function (status, responseText) {
            if (status != 200) {
                alert("HTTP Error: " + status);
                return;
            }

            var result;

            try {
                result = JSON.parse(responseText);
            } catch (e) {
                alert("Response bukan JSON:\n\n" + responseText);
                return;
            }

            if (!result.success) {
                alert(result.message);
                return;
            }

            selectedScheduleRow.parentNode.removeChild(selectedScheduleRow);
            selectedScheduleRow = null;

            if (document.getElementById("scheduleBody").getElementsByTagName("tr").length == 0) {
                addScheduleRow();
            }

            setStatus(result.message);
        }
    );
}



document.getElementById("CUST_CODE").onkeydown = function (e) {
    return custKeyDown(e);
};

document.getElementById("CUST_CODE").onkeyup = function (e) {
    e = e || window.event;

    var key = e.keyCode || e.which;

    if (key == 13 || key == 38 || key == 40) {
        return false;
    }

    clearTimeout(custTimer);

    custTimer = setTimeout(function () {
        searchCustomer();
    }, 250);
};

document.getElementById("CUST_CODE").onblur = function () {
    setTimeout(function () {
        hideCustSuggest();
    }, 250);
};

document.getElementById("ITEM_SEARCH").onkeydown = function (e) {
    return itemSearchKeyDown(e);
};

document.getElementById("ITEM_SEARCH").onkeyup = function (e) {
    e = e || window.event;

    var key = e.keyCode || e.which;

    if (key == 13 || key == 38 || key == 40) {
        return false;
    }

    clearTimeout(itemSearchTimer);

    itemSearchTimer = setTimeout(function () {
        searchItemLocal();
    }, 150);
};

document.getElementById("ITEM_SEARCH").onblur = function () {
    setTimeout(function () {
        hideItemSearchSuggest();
    }, 250);
};

document.getElementById("btnRefreshItem").onclick = function () {
    loadItems();
};

document.getElementById("btnAddSchedule").onclick = function () {
    addScheduleRow();
};

document.getElementById("btnDeleteSchedule").onclick = function () {
    deleteScheduleRow();
};

document.getElementById("btnSaveSchedule").onclick = function () {
    saveSchedule();
};

document.getElementById("btnReloadSchedule").onclick = function () {
    loadSchedule();
};

var btnClose = document.getElementById("btnClose");

if (btnClose) {
    btnClose.onclick = function () {
        window.close();
    };
}

document.getElementById("CUST_CODE").focus();
</script>

</body>
</html>