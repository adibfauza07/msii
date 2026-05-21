<?php
require_once __DIR__ . "/../config/db_plant2.php";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Forecast Plant 2</title>

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
            min-height: 470px;
            margin: 15px auto;
            background: #078b83;
            border: 1px solid #333;
            box-sizing: border-box;
            padding: 10px;
        }

        .title {
            font-size: 30px;
            font-weight: bold;
            letter-spacing: 8px;
            margin-bottom: 15px;
        }

        label {
            font-weight: bold;
            display: block;
            margin-top: 6px;
        }

        input[type="text"],
        input[type="number"],
        input[type="month"] {
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
            width: 110px;
        }

        .item-table .col-prcd {
            width: 120px;
        }

        .item-table .col-no {
            width: 120px;
        }

        .item-table .col-name {
            width: auto;
        }

        .item-table tbody tr {
            cursor: pointer;
        }

        .item-table tr:focus {
            outline: 2px solid #0033ff;
        }

        .forecast-table .col-month {
            width: 130px;
        }

        .forecast-table .col-qty {
            width: 100px;
            text-align: right;
        }

        .forecast-table input {
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
    <div class="title">FORECAST</div>

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
    <button type="button" id="btnClose">CLOSE</button>
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
                    </tr>
                </thead>
                <tbody id="itemBody">
                    <tr>
                        <td colspan="4">Pilih customer dulu.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="right-panel">
            <div class="small-info" id="selectedPartInfo">Belum pilih part.</div>

            <table class="forecast-table">
                <thead>
                    <tr>
                        <th class="col-month">MONTH</th>
                        <th class="col-qty">FORE_QTY</th>
                    </tr>
                </thead>
                <tbody id="forecastBody">
                    <tr>
                        <td colspan="2">Belum ada data.</td>
                    </tr>
                </tbody>
            </table>

            <div class="toolbar">
                <button type="button" id="btnAddMonth">+</button>
                <button type="button" id="btnDeleteMonth">-</button>
                <button type="button" id="btnSaveForecast">SIMPAN</button>
                <button type="button" id="btnReloadForecast">REFRESH</button>
            </div>
        </div>
    </div>
</div>

<script>
var custTimer = null;
var custRows = [];
var custIndex = -1;

var selectedItemRow = null;
var selectedForecastRow = null;
var forecastLoadSeq = 0;

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
    document.getElementById("selectedPartInfo").innerHTML = "Belum pilih part.";
    document.getElementById("forecastBody").innerHTML = '<tr><td colspan="2">Belum ada data.</td></tr>';

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

    ajaxPost("ajax_forecast_customer.php", "q=" + enc(q), function (status, responseText) {
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

    setStatus("Loading item forecast...");

    ajaxPost("ajax_forecast_items.php", "CUST_ID=" + enc(custId), function (status, responseText) {
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
        setStatus("Item loaded: " + result.rows.length);
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
    loadForecast();

    if (rows[index].scrollIntoView) {
        rows[index].scrollIntoView({ block: "nearest" });
    }

    rows[index].focus();
}

function focusForecastGrid() {
    var body = document.getElementById("forecastBody");
    var rows = body.getElementsByTagName("tr");

    if (!rows || rows.length == 0) {
        addForecastRow();
        rows = body.getElementsByTagName("tr");
    }

    if (rows.length > 0) {
        /*
            TAB dari grid kiri langsung ke line paling bawah grid kanan.
        */
        var lastIndex = rows.length - 1;
        var lastRow = rows[lastIndex];

        selectForecastRow(lastRow);

        var qtyInput = getForecastInput(lastRow, "FORE_QTY");
        var monthInput = getForecastInput(lastRow, "FORE_MONTH");

        if (qtyInput) {
            qtyInput.focus();
            qtyInput.select();
        } else if (monthInput) {
            monthInput.focus();
            monthInput.select();
        }

        if (lastRow.scrollIntoView) {
            lastRow.scrollIntoView({
                block: "nearest"
            });
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
        loadForecast();
        return false;
    }

    if (key == 9) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        focusForecastGrid();
        return false;
    }

    return true;
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

    if (items[index].offsetTop < box.scrollTop) {
        box.scrollTop = items[index].offsetTop;
    } else if ((items[index].offsetTop + items[index].offsetHeight) > (box.scrollTop + box.clientHeight)) {
        box.scrollTop = items[index].offsetTop + items[index].offsetHeight - box.clientHeight;
    }
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

    document.getElementById("ITEM_SEARCH").value =
        r.PART_CODE + " - " + r.PART_NAME;

    hideItemSearchSuggest();

    selectItemRow(row);
    loadForecast();

    row.focus();

    if (row.scrollIntoView) {
        row.scrollIntoView({
            block: "nearest"
        });
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
                    htmlEncode(r.PART_CODE) + " - " + htmlEncode(r.PART_NAME) +
                '</div>' +
                '<div class="autocomplete-sub">' +
                    'PRCD: ' + htmlEncode(r.PRICE_CODE) +
                    ' | PART NO: ' + htmlEncode(r.PART_NO) +
                    ' | PRICE_ID: ' + htmlEncode(r.PRICE_ID) +
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
            String(r.PART_CODE || "") + " " +
            String(r.PART_NAME || "") + " " +
            String(r.PART_NO || "") + " " +
            String(r.PRICE_CODE || "")
        ).toLowerCase();

        if (text.indexOf(q) >= 0) {
            result.push(r);
        }

        /*
            Grid kiri dibatasi maksimal 10 item.
        */
        if (result.length >= 15) {
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
    document.getElementById("forecastBody").innerHTML = '<tr><td colspan="2">Belum ada data.</td></tr>';

    if (!rows || rows.length == 0) {
        body.innerHTML = '<tr><td colspan="4">Item tidak ditemukan.</td></tr>';
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        (function (r) {
            var tr = document.createElement("tr");

            tr.setAttribute("tabindex", "0");
            tr.setAttribute("data-price-id", r.PRICE_ID);
            tr.setAttribute("data-part-code", r.PART_CODE);
            tr.setAttribute("data-part-name", r.PART_NAME);

            tr.innerHTML =
                '<td class="col-code">' + htmlEncode(r.PART_CODE) + '</td>' +
                '<td class="col-prcd">' + htmlEncode(r.PRICE_CODE) + '</td>' +
                '<td class="col-no">' + htmlEncode(r.PART_NO) + '</td>' +
                '<td class="col-name">' + htmlEncode(r.PART_NAME) + '</td>';

            tr.onclick = function () {
                selectItemRow(this);
                loadForecast();
                this.focus();
            };

            tr.ondblclick = function () {
                selectItemRow(this);
                loadForecast();
                focusForecastGrid();
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
        loadForecast();
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

function loadForecast() {
    var priceId = document.getElementById("PRICE_ID").value;

    if (priceId == "") {
        return;
    }

    var currentSeq = ++forecastLoadSeq;

    ajaxPost("ajax_forecast_load.php", "PRICE_ID=" + enc(priceId), function (status, responseText) {
        if (currentSeq != forecastLoadSeq) {
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

        renderForecast(result.rows);
    });
}

function renderForecast(rows) {
    var body = document.getElementById("forecastBody");

    body.innerHTML = "";
    selectedForecastRow = null;

    if (!rows || rows.length == 0) {
        addForecastRow();
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        addForecastRow(rows[i]);
    }

    var firstRow = body.getElementsByTagName("tr")[0];

    if (firstRow) {
        selectForecastRow(firstRow);
    }
}

function selectForecastRow(row) {
    var rows = document.getElementById("forecastBody").getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        rows[i].className = "";
    }

    row.className = "selected";
    selectedForecastRow = row;
}

function getForecastInput(row, name) {
    var inputs = row.getElementsByTagName("input");

    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].getAttribute("data-name") == name) {
            return inputs[i];
        }
    }

    return null;
}

function getNextMonthValue() {
    var body = document.getElementById("forecastBody");
    var rows = body.getElementsByTagName("tr");
    var maxValue = "";

    for (var i = 0; i < rows.length; i++) {
        var monthInput = getForecastInput(rows[i], "FORE_MONTH");

        if (monthInput && monthInput.value != "" && monthInput.value > maxValue) {
            maxValue = monthInput.value;
        }
    }

    if (maxValue != "") {
        var parts = maxValue.split("-");
        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10);

        m++;

        if (m > 12) {
            y++;
            m = 1;
        }

        return y + "-" + (m < 10 ? "0" + m : m);
    }

    var d = new Date();
    var month = d.getMonth() + 1;

    return d.getFullYear() + "-" + (month < 10 ? "0" + month : month);
}

function getForecastRows() {
    return document.getElementById("forecastBody").getElementsByTagName("tr");
}

function getSelectedForecastIndex() {
    var rows = getForecastRows();

    for (var i = 0; i < rows.length; i++) {
        if (rows[i] === selectedForecastRow) {
            return i;
        }
    }

    return -1;
}

function moveForecastSelection(direction, fieldName) {
    var rows = getForecastRows();

    if (!rows || rows.length == 0) {
        return;
    }

    var index = getSelectedForecastIndex();

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

    selectForecastRow(rows[index]);

    var input = getForecastInput(rows[index], fieldName);

    if (input) {
        input.focus();
        input.select();
    }

    if (rows[index].scrollIntoView) {
        rows[index].scrollIntoView({ block: "nearest" });
    }
}

function addForecastRow(data) {
    data = data || {};

    var body = document.getElementById("forecastBody");

    if (body.getElementsByTagName("td").length == 1 && body.innerHTML.indexOf("Belum ada") >= 0) {
        body.innerHTML = "";
    }

    var tr = document.createElement("tr");

    tr.onclick = function () {
        selectForecastRow(this);
    };

    var monthValue = data.FORE_MONTH || getNextMonthValue();
    var qtyValue = "";

    if (data.hasOwnProperty("FORE_QTY")) {
        qtyValue = data.FORE_QTY;
    }

    tr.innerHTML =
        '<td class="col-month">' +
            '<input type="month" data-name="FORE_MONTH" value="' + htmlEncode(monthValue) + '">' +
        '</td>' +
        '<td class="col-qty">' +
            '<input type="number" class="num" data-name="FORE_QTY" value="' + htmlEncode(qtyValue) + '">' +
        '</td>';

    body.appendChild(tr);

    var monthInput = getForecastInput(tr, "FORE_MONTH");
    var qtyInput = getForecastInput(tr, "FORE_QTY");

    monthInput.onfocus = function () {
        selectForecastRow(tr);
    };

    qtyInput.onfocus = function () {
        selectForecastRow(tr);
    };

    monthInput.onkeydown = function (e) {
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
            moveForecastSelection(1, "FORE_MONTH");
            return false;
        }

        if (key == 38) {
            e.preventDefault ? e.preventDefault() : e.returnValue = false;
            moveForecastSelection(-1, "FORE_MONTH");
            return false;
        }

        return true;
    };

    qtyInput.onkeydown = function (e) {
        e = e || window.event;

        var key = e.keyCode || e.which;

        if (key == 13) {
            e.preventDefault ? e.preventDefault() : e.returnValue = false;

            saveForecast(function (ok) {
                if (ok) {
                    addForecastRow();

                    var rows = document.getElementById("forecastBody").getElementsByTagName("tr");
                    var lastRow = rows[rows.length - 1];

                    selectForecastRow(lastRow);
                    getForecastInput(lastRow, "FORE_MONTH").focus();
                }
            });

            return false;
        }

        if (key == 40) {
            e.preventDefault ? e.preventDefault() : e.returnValue = false;
            moveForecastSelection(1, "FORE_QTY");
            return false;
        }

        if (key == 38) {
            e.preventDefault ? e.preventDefault() : e.returnValue = false;
            moveForecastSelection(-1, "FORE_QTY");
            return false;
        }

        return true;
    };

    selectForecastRow(tr);
}

function collectForecastRows() {
    var body = document.getElementById("forecastBody");
    var trs = body.getElementsByTagName("tr");
    var rows = [];
    var checkMonth = {};

    for (var i = 0; i < trs.length; i++) {
        var monthInput = getForecastInput(trs[i], "FORE_MONTH");
        var qtyInput = getForecastInput(trs[i], "FORE_QTY");

        if (!monthInput || !qtyInput) {
            continue;
        }

        var month = monthInput.value;
        var qtyText = qtyInput.value;

        if (month == "") {
            continue;
        }

        if (qtyText == "") {
            qtyText = "0";
        }

        var qty = parseInt(qtyText, 10);

        if (isNaN(qty) || qty < 0) {
            alert("FORE_QTY tidak valid pada month " + month);
            qtyInput.focus();
            return false;
        }

        if (checkMonth[month]) {
            alert("Month duplicate di grid: " + month);
            monthInput.focus();
            return false;
        }

        checkMonth[month] = true;

        rows.push({
            FORE_MONTH: month,
            FORE_QTY: qty
        });
    }

    return rows;
}

function saveForecast(callback) {
    var priceId = document.getElementById("PRICE_ID").value;

    if (priceId == "") {
        alert("Pilih item dulu.");
        if (callback) {
            callback(false);
        }
        return;
    }

    var rows = collectForecastRows();

    if (rows === false) {
        if (callback) {
            callback(false);
        }
        return;
    }

    if (rows.length == 0) {
        alert("Forecast kosong.");
        if (callback) {
            callback(false);
        }
        return;
    }

    ajaxPost(
        "ajax_forecast_save.php",
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

function deleteForecastRow() {
    if (!selectedForecastRow) {
        alert("Pilih forecast yang mau dihapus.");
        return;
    }

    var priceId = document.getElementById("PRICE_ID").value;
    var monthInput = getForecastInput(selectedForecastRow, "FORE_MONTH");

    if (!monthInput || monthInput.value == "") {
        selectedForecastRow.parentNode.removeChild(selectedForecastRow);
        selectedForecastRow = null;
        return;
    }

    if (priceId == "") {
        alert("PRICE_ID kosong.");
        return;
    }

    if (!confirm("Hapus forecast month " + monthInput.value + "?")) {
        return;
    }

    ajaxPost(
        "ajax_forecast_delete.php",
        "PRICE_ID=" + enc(priceId) + "&FORE_MONTH=" + enc(monthInput.value),
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

            selectedForecastRow.parentNode.removeChild(selectedForecastRow);
            selectedForecastRow = null;

            if (document.getElementById("forecastBody").getElementsByTagName("tr").length == 0) {
                addForecastRow();
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

document.getElementById("btnAddMonth").onclick = function () {
    addForecastRow();
};

document.getElementById("btnDeleteMonth").onclick = function () {
    deleteForecastRow();
};

document.getElementById("btnSaveForecast").onclick = function () {
    saveForecast();
};

document.getElementById("btnReloadForecast").onclick = function () {
    loadForecast();
};

document.getElementById("btnClose").onclick = function () {
    window.close();
};

document.getElementById("CUST_CODE").focus();
</script>

</body>
</html>