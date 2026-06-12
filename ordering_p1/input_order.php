<?php
require_once __DIR__ . "/../config/database_ordering.php";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$today = date("Y-m-d");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Input Order Plant 2</title>

    <style>
        body {
            margin: 0;
            background: #d3d0c8;
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000000;
        }

        .form-box {
            width: 1120px;
            min-height: 600px;
            margin: 20px auto;
            border: 1px solid #555555;
            background: #d3d0c8;
            box-sizing: border-box;
        }

        .title-bar {
            background: green;
            color: white;
            font-size: 30px;
            font-style: italic;
            font-weight: bold;
            padding: 4px 14px;
            letter-spacing: 1px;
        }

        .content {
            padding: 10px;
        }

        .row {
            display: flex;
            align-items: center;
            margin-bottom: 7px;
        }

        .field-group {
            display: flex;
            align-items: center;
            margin-right: 8px;
        }

        label {
            margin-right: 4px;
            font-size: 12px;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        select {
            height: 23px;
            border: 1px solid #777777;
            padding: 2px 4px;
            box-sizing: border-box;
            font-size: 12px;
        }

        input[readonly] {
            background: #e6e6e6;
        }

        .cust-code {
            width: 75px;
        }

        .company {
            width: 270px;
        }

        .curr {
            width: 55px;
        }

        .po {
            width: 230px;
        }

        .date {
            width: 130px;
        }

        .small-input {
            width: 120px;
        }

        button {
            height: 26px;
            min-width: 75px;
            font-size: 12px;
            cursor: pointer;
            margin-right: 4px;
        }

        .toolbar {
            margin: 12px 0 8px 0;
        }

        .status {
            color: navy;
            min-height: 18px;
            margin: 8px 0;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #777777;
            padding: 2px;
            height: 24px;
            box-sizing: border-box;
            overflow: hidden;
            white-space: nowrap;
            font-size: 12px;
        }

        th {
            background: #d9d6ce;
            text-align: left;
            font-weight: normal;
        }

        .col-no {
            width: 35px;
            text-align: center;
        }

        .col-code {
            width: 110px;
        }

        .col-name {
            width: 280px;
        }

        .col-price {
            width: 90px;
            text-align: right;
        }

        .col-qty,
        .col-bal,
        .col-delv {
            width: 80px;
            text-align: right;
        }

        .col-remark {
            width: 110px;
        }

        .col-amount {
            width: 100px;
            text-align: right;
        }

        .col-closed {
            width: 60px;
            text-align: center;
        }

        .grid-input {
            width: 100%;
            height: 21px;
            border: 1px solid #999999;
            padding: 1px 3px;
            box-sizing: border-box;
            font-size: 12px;
        }

        .grid-input[readonly] {
            background: #efefef;
        }

        .num {
            text-align: right;
        }

        .selected {
            background: #2f70c9;
            color: #ffffff;
        }

        .selected input {
            color: #000000;
        }

        .autocomplete-wrap {
            position: relative;
            display: inline-block;
        }

        .autocomplete-list {
            position: absolute;
            top: 24px;
            left: 0;
            width: 520px;
            max-height: 240px;
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
        }

        .autocomplete-item:hover,
        .autocomplete-item.active {
            background: #2f70c9;
            color: #ffffff;
        }

        .autocomplete-main {
            font-weight: bold;
        }

        .autocomplete-sub {
            color: #555555;
            font-size: 11px;
        }

        .autocomplete-item:hover .autocomplete-sub,
        .autocomplete-item.active .autocomplete-sub {
            color: #ffffff;
        }

        #itemSuggest {
            position: absolute;
            width: 620px;
            max-height: 260px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid #444444;
            z-index: 99999;
            display: none;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.25);
        }

        .btn-list-delivery {
            width: 100%;
            height: 21px;
            font-size: 11px;
            padding: 0;
            margin: 0;
            cursor: pointer;
        }

        .modal-bg {
            display: none;
            position: absolute;
            z-index: 999999;
            left: 0;
            top: 0;
            width: auto;
            height: auto;
            background: transparent;
        }

        .modal-box {
            width: 780px;
            max-height: 420px;
            overflow: auto;
            background: #d3d0c8;
            border: 2px solid #333333;
            margin: 0;
            padding: 10px;
            box-shadow: 3px 3px 8px rgba(0,0,0,0.4);
        }

        .modal-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .modal-close {
            float: right;
            width: 70px;
            height: 25px;
        }

        .delivery-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .delivery-table th,
        .delivery-table td {
            border: 1px solid #777777;
            padding: 4px;
            font-size: 12px;
            height: 24px;
        }

        .delivery-table th {
            background: #d9d6ce;
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="form-box">
    <div class="title-bar">CUSTOMER ORDER</div>

    <div class="content">
        <input type="hidden" id="ORDR_ID" value="">
        <input type="hidden" id="CUST_ID" value="">

        <div class="row">
            <div class="field-group">
                <label>C.CODE</label>
                <div class="autocomplete-wrap">
                    <input type="text" id="CUST_CODE" class="cust-code" autocomplete="off">
                    <div id="custSuggest" class="autocomplete-list"></div>
                </div>
            </div>

            <div class="field-group">
                <label>COMPANY</label>
                <input type="text" id="CUST_COMP" class="company" readonly>
            </div>

            <div class="field-group">
                <label>CUR</label>
                <input type="text" id="ORDR_CURR" class="curr" readonly>
            </div>

            <div class="field-group">
                <label>ORDER PO/CARI</label>
                <div class="autocomplete-wrap">
                    <input type="text" id="ORDR_PO" class="po" autocomplete="off">
                    <div id="poSuggest" class="autocomplete-list"></div>
                </div>
            </div>

            <div class="field-group">
                <label>DATE</label>
                <input type="date" id="ORDR_DATE" class="date" value="<?php echo h($today); ?>">
            </div>
        </div>

        <div class="row">
            <input type="text" id="ORDR_REM" class="small-input" placeholder="Remark">

            <label style="margin-left:10px;">
                <input type="checkbox" id="ORDR_REPLACEMENT"> Replacement
            </label>

            <label style="margin-left:15px;">
                <input type="checkbox" id="ORDR_CLOSE"> Closed
            </label>
        </div>

        <div class="toolbar">
            <button type="button" id="btnNew">NEW</button>
            <button type="button" id="btnAddRow">TAMBAH BARIS</button>
            <button type="button" id="btnDeleteDetail">HAPUS DETAIL</button>
            <button type="button" id="btnDeleteHeader">HAPUS HEADER</button>
            <button type="button" id="btnSave">SIMPAN ORDER</button>
            <button type="button" id="btnCancel">BATAL</button>
        </div>

        <div class="status" id="LabelStatus"></div>

        <table>
            <thead>
                <tr>
                    <th class="col-no">#</th>
                    <th class="col-code">Part Code</th>
                    <th class="col-name">Part Name</th>
                    <th class="col-price">Price</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-bal">Bal.</th>
                    <th class="col-delv">Delv.</th>
                    <th class="col-remark">Remark</th>
                    <th class="col-amount">Amount</th>
                    <th class="col-closed">Closed</th>
                </tr>
            </thead>
            <tbody id="orderBody"></tbody>
        </table>

        <div id="itemSuggest"></div>
    </div>
</div>

<div id="deliveryModal" class="modal-bg">
    <div class="modal-box">
        <button type="button" class="modal-close" onclick="closeDeliveryModal()">CLOSE</button>
        <div class="modal-title">LIST DELIVERY</div>

        <div id="deliveryInfo" style="margin-bottom:8px;color:navy;font-weight:bold;"></div>

        <table class="delivery-table">
            <thead>
                <tr>
                    <th style="width:90px;">DI Date</th>
                    <th style="width:140px;">DI No</th>
                    <th style="width:90px;text-align:right;">Delivery</th>
                    <th style="width:90px;">Cust Code</th>
                    <th>Customer</th>
                    <th style="width:100px;">Item Code</th>
                    <th>Item Name</th>
                </tr>
            </thead>
            <tbody id="deliveryBody">
                <tr>
                    <td colspan="7">Belum ada data.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
var selectedRow = null;
var custTimer = null;
var itemTimer = null;
var poTimer = null;
var activeItemInput = null;

var custSuggestRows = [];
var custSuggestIndex = -1;

var poSuggestRows = [];
var poSuggestIndex = -1;

var itemSuggestRows = [];
var itemSuggestIndex = -1;

var lastOrderPoText = "";

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

function money(v) {
    var n = parseFloat(v || "0");

    if (isNaN(n)) {
        n = 0;
    }

    return n.toFixed(2);
}

function numberValue(input) {
    var v = parseFloat(input.value || "0");

    if (isNaN(v)) {
        return 0;
    }

    return v;
}

function clearForm() {
    document.getElementById("ORDR_ID").value = "";
    document.getElementById("CUST_ID").value = "";
    document.getElementById("CUST_CODE").value = "";
    document.getElementById("CUST_COMP").value = "";
    document.getElementById("ORDR_CURR").value = "";
    document.getElementById("ORDR_PO").value = "";
    document.getElementById("ORDR_DATE").value = "<?php echo h($today); ?>";
    document.getElementById("ORDR_REM").value = "";
    document.getElementById("ORDR_REPLACEMENT").checked = false;
    document.getElementById("ORDR_CLOSE").checked = false;

    document.getElementById("orderBody").innerHTML = "";
    selectedRow = null;
    lastOrderPoText = "";

    hidePoSuggest();
    hideCustSuggest();
    hideItemSuggest();

    addRow();
    setStatus("Input order baru.");
}

function prepareSearchNewPo() {
    var currentOrderId = document.getElementById("ORDR_ID").value;

    /*
        Saat order lama sedang terbuka lalu user mengetik PO lain,
        ORDR_ID lama harus dibuang supaya search tidak terkunci ke order lama.
    */
    if (currentOrderId != "") {
        document.getElementById("ORDR_ID").value = "";
        document.getElementById("orderBody").innerHTML = "";
        selectedRow = null;
        addRow();

        setStatus("Cari order lain...");
    }
}

function selectRow(row) {
    var rows = document.getElementById("orderBody").getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        rows[i].className = "";
    }

    row.className = "selected";
    selectedRow = row;
}

function getInput(row, name) {
    var inputs = row.getElementsByTagName("input");

    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].getAttribute("data-name") == name) {
            return inputs[i];
        }
    }

    return null;
}

function getNextDetailLineNo() {
    var rows = document.getElementById("orderBody").getElementsByTagName("tr");
    var maxLine = 0;

    for (var i = 0; i < rows.length; i++) {
        var linoInput = getInput(rows[i], "ORDP_LINO");

        if (linoInput) {
            var n = parseInt(linoInput.value || "0", 10);

            if (!isNaN(n) && n > maxLine) {
                maxLine = n;
            }
        }
    }

    return maxLine + 1;
}

function updateDisplayRowNumbers() {
    var rows = document.getElementById("orderBody").getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        var noCell = rows[i].getElementsByTagName("td")[0];

        if (noCell && noCell.childNodes.length > 0) {
            noCell.childNodes[0].nodeValue = (i + 1);
        }
    }
}

function renumberRows() {
    updateDisplayRowNumbers();
}

function recalcRow(row) {
    var price = numberValue(getInput(row, "ORDP_PRICE"));
    var qty = parseInt(getInput(row, "ORDP_QTY").value || "0", 10);
    var delv = parseInt(getInput(row, "ORDP_DQTY").value || "0", 10);

    if (isNaN(qty)) {
        qty = 0;
    }

    if (isNaN(delv)) {
        delv = 0;
    }

    var balance = qty - delv;

    if (balance < 0) {
        balance = 0;
    }

    getInput(row, "ORDP_BQTY").value = balance;
    getInput(row, "AMOUNT").value = money(price * qty);
}

function addRow(data) {
    data = data || {};

    var tbody = document.getElementById("orderBody");
    var tr = document.createElement("tr");

    tr.onclick = function () {
        selectRow(this);
    };

    var displayNo = tbody.getElementsByTagName("tr").length + 1;
    var lineNo = data.ORDP_LINO || getNextDetailLineNo();
    var rowOrdrId = data.ORDR_ID || document.getElementById("ORDR_ID").value || "";

    tr.innerHTML =
        '<td class="col-no">' +
            displayNo +
            '<input type="hidden" data-name="ORDR_ID" value="' + htmlEncode(rowOrdrId) + '">' +
            '<input type="hidden" data-name="ORDP_LINO" value="' + htmlEncode(lineNo) + '">' +
            '<input type="hidden" data-name="PRICE_ID" value="' + htmlEncode(data.PRICE_ID || "") + '">' +
        '</td>' +

        '<td class="col-code">' +
            '<input type="text" class="grid-input item-code" data-name="PART_CODE" value="' + htmlEncode(data.PART_CODE || data.ITEM_CODE || "") + '" autocomplete="off">' +
        '</td>' +

        '<td class="col-name">' +
            '<input type="text" class="grid-input" data-name="PART_NAME" value="' + htmlEncode(data.PART_NAME || data.ITEM_NAME || "") + '" readonly>' +
        '</td>' +

        '<td class="col-price">' +
            '<input type="number" class="grid-input num" data-name="ORDP_PRICE" value="' + htmlEncode(data.ORDP_PRICE || data.PRDT_PRICE || "0") + '">' +
        '</td>' +

        '<td class="col-qty">' +
            '<input type="number" class="grid-input num" data-name="ORDP_QTY" value="' + htmlEncode(data.ORDP_QTY || "0") + '">' +
        '</td>' +

        '<td class="col-bal">' +
            '<input type="number" class="grid-input num" data-name="ORDP_BQTY" value="' + htmlEncode(data.ORDP_BQTY || "0") + '" readonly>' +
        '</td>' +

        '<td class="col-delv">' +
            '<input type="number" class="grid-input num" data-name="ORDP_DQTY" value="' + htmlEncode(data.ORDP_DQTY || "0") + '" readonly>' +
        '</td>' +

        '<td class="col-remark">' +
            '<input type="hidden" data-name="ORDP_REM" value="' + htmlEncode(data.ORDP_REM || data.ORDP_DESC || "") + '">' +
            '<button type="button" class="btn-list-delivery" data-name="BTN_DELIVERY">LIST</button>' +
        '</td>' +

        '<td class="col-amount">' +
            '<input type="text" class="grid-input num" data-name="AMOUNT" value="' + htmlEncode(data.AMOUNT || "0.00") + '" readonly>' +
        '</td>' +

        '<td class="col-closed">' +
            '<input type="checkbox" data-name="ORDP_CLOSE">' +
        '</td>';

    tbody.appendChild(tr);

    var codeInput = getInput(tr, "PART_CODE");
    var priceInput = getInput(tr, "ORDP_PRICE");
    var qtyInput = getInput(tr, "ORDP_QTY");
    var remInput = getInput(tr, "ORDP_REM");

    var deliveryButton = null;
    var buttons = tr.getElementsByTagName("button");

    for (var b = 0; b < buttons.length; b++) {
        if (buttons[b].getAttribute("data-name") == "BTN_DELIVERY") {
            deliveryButton = buttons[b];
            break;
        }
    }

    if (deliveryButton) {
        deliveryButton.onclick = function (e) {
            e = e || window.event;

            if (e.stopPropagation) {
                e.stopPropagation();
            } else {
                e.cancelBubble = true;
            }

            showDeliveryList(tr);
            return false;
        };
    }

    codeInput.onkeydown = function (e) {
        return itemSuggestKeyDown(e);
    };

    codeInput.onkeyup = function (e) {
        e = e || window.event;

        var key = e.keyCode || e.which;

        if (key == 13 || key == 38 || key == 40) {
            return false;
        }

        activeItemInput = this;

        clearTimeout(itemTimer);
        itemTimer = setTimeout(function () {
            searchItemAutocomplete(codeInput);
        }, 250);
    };

    codeInput.onfocus = function () {
        activeItemInput = this;
    };

    codeInput.onblur = function () {
        setTimeout(function () {
            hideItemSuggest();
        }, 250);
    };

    priceInput.onkeyup = function () {
        recalcRow(tr);
    };

    priceInput.onchange = function () {
        recalcRow(tr);
    };

    qtyInput.onkeyup = function () {
        recalcRow(tr);
    };

    qtyInput.onchange = function () {
        recalcRow(tr);
    };

    qtyInput.onkeydown = function (e) {
        e = e || window.event;

        var key = e.keyCode || e.which;
        var row = this.parentNode.parentNode;
        var rowIndex = getRowIndex(row);

        if (key == 13) {
            e.preventDefault ? e.preventDefault() : e.returnValue = false;

            saveDetailLineFromRow(row, function (ok) {
                if (!ok) {
                    return;
                }

                var rows = document.getElementById("orderBody").getElementsByTagName("tr");

                if (rowIndex + 1 >= rows.length) {
                    addRow();
                }

                focusInput(rowIndex + 1, "PART_CODE");
            });

            return false;
        }

        return gridKeyDown(e);
    };

    priceInput.onkeydown = gridKeyDown;

    if (remInput && remInput.type != "hidden") {
        remInput.onkeydown = gridKeyDown;
    }

    if (parseInt(data.ORDP_CLOSE || 0, 10) == 1) {
        getInput(tr, "ORDP_CLOSE").checked = true;
    }

    recalcRow(tr);
    selectRow(tr);
}

function deleteSelectedRow() {
    if (!selectedRow) {
        alert("Pilih baris detail yang mau dihapus.");
        return;
    }

    var headerOrdrId = document.getElementById("ORDR_ID").value;
    var rowOrdrIdInput = getInput(selectedRow, "ORDR_ID");
    var linoInput = getInput(selectedRow, "ORDP_LINO");
    var dqInput = getInput(selectedRow, "ORDP_DQTY");

    var ordrId = "";

    if (rowOrdrIdInput && rowOrdrIdInput.value != "") {
        ordrId = rowOrdrIdInput.value;
    } else {
        ordrId = headerOrdrId;
    }

    var lino = linoInput ? linoInput.value : "";
    var deliveryQty = dqInput ? parseInt(dqInput.value || "0", 10) : 0;

    if (deliveryQty > 0) {
        alert(
            "Detail tidak bisa dihapus karena sudah ada DELIVERY.\n\n" +
            "Line     : " + lino + "\n" +
            "Delivery : " + deliveryQty
        );
        return;
    }

    if (ordrId == "") {
        if (!confirm("Hapus baris ini dari layar?")) {
            return;
        }

        selectedRow.parentNode.removeChild(selectedRow);
        selectedRow = null;
        renumberRows();

        if (document.getElementById("orderBody").getElementsByTagName("tr").length == 0) {
            addRow();
        }

        setStatus("Baris detail dihapus dari layar.");
        return;
    }

    if (lino == "") {
        alert("Line detail tidak valid.");
        return;
    }

    if (!confirm("Hapus detail line " + lino + " dari database?")) {
        return;
    }

    ajaxPost(
        "ajax_input_order_delete_detail.php",
        "ORDR_ID=" + enc(ordrId) + "&ORDP_LINO=" + enc(lino),
        function (status, responseText) {
            if (status != 200) {
                alert("HTTP Error: " + status);
                setStatus("Hapus detail gagal.");
                return;
            }

            var result;

            try {
                result = JSON.parse(responseText);
            } catch (e) {
                alert("Response bukan JSON:\n\n" + responseText);
                setStatus("Hapus detail gagal.");
                return;
            }

            if (!result.success) {
                alert(result.message);
                setStatus("Hapus detail gagal.");
                return;
            }

            alert(result.message);

            selectedRow.parentNode.removeChild(selectedRow);
            selectedRow = null;
            renumberRows();

            if (document.getElementById("orderBody").getElementsByTagName("tr").length == 0) {
                addRow();
            }

            setStatus(result.message);
        }
    );
}

function deleteHeader() {
    var ordrId = document.getElementById("ORDR_ID").value;
    var po = document.getElementById("ORDR_PO").value;

    if (ordrId == "") {
        alert("Order belum tersimpan / ORDR_ID kosong.");
        return;
    }

    if (!confirm(
        "Hapus HEADER order ini?\n\n" +
        "PO      : " + po + "\n" +
        "ORDR_ID : " + ordrId + "\n\n" +
        "Header hanya bisa dihapus kalau detail sudah kosong."
    )) {
        return;
    }

    ajaxPost(
        "ajax_input_order_delete_header.php",
        "ORDR_ID=" + enc(ordrId),
        function (status, responseText) {
            if (status != 200) {
                alert("HTTP Error: " + status);
                setStatus("Hapus header gagal.");
                return;
            }

            var result;

            try {
                result = JSON.parse(responseText);
            } catch (e) {
                alert("Response bukan JSON:\n\n" + responseText);
                setStatus("Hapus header gagal.");
                return;
            }

            if (!result.success) {
                alert(result.message);
                setStatus("Hapus header gagal.");
                return;
            }

            alert(result.message);
            clearForm();
        }
    );
}

function getRowIndex(row) {
    var rows = document.getElementById("orderBody").getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        if (rows[i] === row) {
            return i;
        }
    }

    return -1;
}

function focusInput(rowIndex, fieldName) {
    var rows = document.getElementById("orderBody").getElementsByTagName("tr");

    if (rowIndex < 0) {
        return;
    }

    if (rowIndex >= rows.length) {
        addRow();
        rows = document.getElementById("orderBody").getElementsByTagName("tr");
    }

    if (rowIndex < 0 || rowIndex >= rows.length) {
        return;
    }

    var input = getInput(rows[rowIndex], fieldName);

    if (input) {
        input.focus();
        input.select();
        selectRow(rows[rowIndex]);
    }
}

function gridKeyDown(e) {
    e = e || window.event;

    var key = e.keyCode || e.which;
    var fieldName = this.getAttribute("data-name");
    var row = this.parentNode.parentNode;
    var rowIndex = getRowIndex(row);

    if (key == 13 || key == 40) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        recalcRow(row);
        focusInput(rowIndex + 1, fieldName);
        return false;
    }

    if (key == 38) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        recalcRow(row);
        focusInput(rowIndex - 1, fieldName);
        return false;
    }

    return true;
}

function setActivePoSuggest(index) {
    var box = document.getElementById("poSuggest");
    var items = box.getElementsByClassName("autocomplete-item");

    if (!items || items.length == 0) {
        poSuggestIndex = -1;
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
    poSuggestIndex = index;

    if (items[index].offsetTop < box.scrollTop) {
        box.scrollTop = items[index].offsetTop;
    } else if ((items[index].offsetTop + items[index].offsetHeight) > (box.scrollTop + box.clientHeight)) {
        box.scrollTop = items[index].offsetTop + items[index].offsetHeight - box.clientHeight;
    }
}

function choosePoSuggest(index) {
    if (index < 0 || index >= poSuggestRows.length) {
        return;
    }

    var r = poSuggestRows[index];

    document.getElementById("ORDR_ID").value = "";
    document.getElementById("ORDR_PO").value = r.ORDR_PO;

    hidePoSuggest();

    /*
        r.ORDR_ID boleh negatif.
        loadOrderById sekarang sudah menerima ORDR_ID negatif.
    */
    loadOrderById(r.ORDR_ID);
}

function poSuggestKeyDown(e) {
    e = e || window.event;

    var key = e.keyCode || e.which;
    var box = document.getElementById("poSuggest");

    if (!box || box.style.display != "block") {
        if (key == 13) {
            e.preventDefault ? e.preventDefault() : e.returnValue = false;
            loadOrderByPo(document.getElementById("ORDR_PO").value);
            return false;
        }

        return true;
    }

    if (key == 40) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        setActivePoSuggest(poSuggestIndex + 1);
        return false;
    }

    if (key == 38) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        setActivePoSuggest(poSuggestIndex - 1);
        return false;
    }

    if (key == 13) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;

        if (poSuggestIndex < 0 && poSuggestRows.length > 0) {
            poSuggestIndex = 0;
        }

        choosePoSuggest(poSuggestIndex);
        return false;
    }

    return true;
}

function hidePoSuggest() {
    var box = document.getElementById("poSuggest");

    if (box) {
        box.style.display = "none";
        box.innerHTML = "";
    }

    poSuggestRows = [];
    poSuggestIndex = -1;
}

function showPoSuggest(rows) {
    var box = document.getElementById("poSuggest");
    box.innerHTML = "";

    poSuggestRows = rows || [];
    poSuggestIndex = -1;

    if (!rows || rows.length == 0) {
        box.style.display = "none";
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        (function (r, idx) {
            var div = document.createElement("div");
            div.className = "autocomplete-item";

            div.innerHTML =
                '<div class="autocomplete-main">' + htmlEncode(r.ORDR_PO) + '</div>' +
                '<div class="autocomplete-sub">' +
                    htmlEncode(r.ORDR_DATE) + ' | ' +
                    htmlEncode(r.CUST_CODE) + ' | ' +
                    htmlEncode(r.CUST_COMP) +
                '</div>';

            div.onmouseover = function () {
                setActivePoSuggest(idx);
            };

            div.onmousedown = function (e) {
                if (e && e.preventDefault) {
                    e.preventDefault();
                }

                choosePoSuggest(idx);
            };

            box.appendChild(div);
        })(rows[i], i);
    }

    box.style.display = "block";
    setActivePoSuggest(0);
}

function searchPoAutocomplete() {
    var q = document.getElementById("ORDR_PO").value;

    if (q.length < 1) {
        hidePoSuggest();
        return;
    }

    /*
        Jangan filter CUST_ID lama.
        Supaya bisa cari PO customer lain tanpa keluar form.
    */
    ajaxPost(
        "ajax_input_order_po.php",
        "q=" + enc(q),
        function (status, responseText) {
            if (status != 200) {
                hidePoSuggest();
                return;
            }

            var result;

            try {
                result = JSON.parse(responseText);
            } catch (e) {
                hidePoSuggest();
                return;
            }

            if (!result.success) {
                hidePoSuggest();
                return;
            }

            showPoSuggest(result.rows);
        }
    );
}

function loadOrderByPo(po) {
    if (po == "") {
        alert("ORDER PO belum diisi.");
        document.getElementById("ORDR_PO").focus();
        return;
    }

    setStatus("Loading order PO...");

    ajaxPost(
        "ajax_input_order_get.php",
        "ORDR_PO=" + enc(po) +
        "&ORDR_DATE=" + enc(document.getElementById("ORDR_DATE").value),
        function (status, responseText) {
            if (status != 200) {
                alert("HTTP Error: " + status);
                setStatus("Load order gagal.");
                return;
            }

            var result;

            try {
                result = JSON.parse(responseText);
            } catch (e) {
                alert("Response bukan JSON:\n\n" + responseText);
                setStatus("Load order gagal.");
                return;
            }

            if (!result.success) {
                alert(result.message);
                setStatus("Load order gagal.");
                return;
            }

            fillOrderFromData(result.header, result.details);
            setStatus(result.message + " ORDR_ID: " + result.header.ORDR_ID);
        }
    );
}

function loadOrderById(ordrId) {
    var idNum = parseInt(ordrId, 10);

    /*
        ORDR_ID di database kamu nilainya negatif.
        Jadi jangan pakai <= 0.
        Yang tidak valid hanya kosong, 0, atau bukan angka.
    */
    if (ordrId == "" || isNaN(idNum) || idNum == 0) {
        alert("ORDR_ID kosong / tidak valid.");
        return;
    }

    setStatus("Loading order...");

    ajaxPost(
        "ajax_input_order_get.php",
        "ORDR_ID=" + enc(ordrId),
        function (status, responseText) {
            if (status != 200) {
                alert("HTTP Error: " + status);
                setStatus("Load order gagal.");
                return;
            }

            var result;

            try {
                result = JSON.parse(responseText);
            } catch (e) {
                alert("Response bukan JSON:\n\n" + responseText);
                setStatus("Load order gagal.");
                return;
            }

            if (!result.success) {
                alert(result.message);
                setStatus("Load order gagal.");
                return;
            }

            fillOrderFromData(result.header, result.details);
            setStatus(result.message + " ORDR_ID: " + result.header.ORDR_ID);
        }
    );
}

function fillOrderFromData(header, details) {
    document.getElementById("ORDR_ID").value = header.ORDR_ID || "";
    document.getElementById("CUST_ID").value = header.CUST_ID || "";
    document.getElementById("CUST_CODE").value = header.CUST_CODE || "";
    document.getElementById("CUST_COMP").value = header.CUST_COMP || "";
    document.getElementById("ORDR_CURR").value = header.ORDR_CURR || "";
    document.getElementById("ORDR_PO").value = header.ORDR_PO || "";
    document.getElementById("ORDR_DATE").value = header.ORDR_DATE || "";
    document.getElementById("ORDR_REM").value = header.ORDR_REM || "";
    document.getElementById("ORDR_REPLACEMENT").checked = parseInt(header.ORDR_REPLACEMENT || 0, 10) == 1;
    document.getElementById("ORDR_CLOSE").checked = parseInt(header.ORDR_CLOSE || 0, 10) == 1;

    document.getElementById("orderBody").innerHTML = "";
    selectedRow = null;

    for (var i = 0; i < details.length; i++) {
        addRow(details[i]);
    }

    if (details.length == 0) {
        addRow();
    }

    lastOrderPoText = header.ORDR_PO || "";
}

function setActiveItemSuggest(index) {
    var box = document.getElementById("itemSuggest");
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

function chooseItemSuggest(index) {
    if (index < 0 || index >= itemSuggestRows.length) {
        return;
    }

    if (!activeItemInput) {
        return;
    }

    var r = itemSuggestRows[index];
    var row = activeItemInput.parentNode.parentNode;
    var duplicateRow = findDuplicatePriceId(r.PRICE_ID, row);

    if (duplicateRow) {
        alert(
            "Item ini sudah ada di detail order.\n\n" +
            "Part Code : " + r.PART_CODE + "\n" +
            "Part Name : " + r.PART_NAME + "\n\n" +
            "Tidak boleh input item yang sama dua kali."
        );

        hideItemSuggest();
        selectRow(duplicateRow);

        var qtyInput = getInput(duplicateRow, "ORDP_QTY");
        if (qtyInput) {
            qtyInput.focus();
            qtyInput.select();
        }

        return;
    }

    getInput(row, "PRICE_ID").value = r.PRICE_ID;
    getInput(row, "PART_CODE").value = r.PART_CODE;
    getInput(row, "PART_NAME").value = r.PART_NAME;
    getInput(row, "ORDP_PRICE").value = r.PRDT_PRICE || "0";

    recalcRow(row);
    hideItemSuggest();

    getInput(row, "ORDP_QTY").focus();
    getInput(row, "ORDP_QTY").select();
}

function itemSuggestKeyDown(e) {
    e = e || window.event;

    var key = e.keyCode || e.which;
    var box = document.getElementById("itemSuggest");

    if (!box || box.style.display != "block") {
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

function hideItemSuggest() {
    var box = document.getElementById("itemSuggest");

    if (box) {
        box.style.display = "none";
        box.innerHTML = "";
    }

    itemSuggestRows = [];
    itemSuggestIndex = -1;
}

function showItemSuggest(input, rows) {
    var box = document.getElementById("itemSuggest");
    box.innerHTML = "";

    itemSuggestRows = rows || [];
    itemSuggestIndex = -1;
    activeItemInput = input;

    if (!rows || rows.length == 0) {
        box.style.display = "none";
        return;
    }

    var rect = input.getBoundingClientRect();

    box.style.left = (rect.left + window.pageXOffset) + "px";
    box.style.top = (rect.bottom + window.pageYOffset) + "px";

    for (var i = 0; i < rows.length; i++) {
        (function (r, idx) {
            var div = document.createElement("div");
            div.className = "autocomplete-item";

            div.innerHTML =
                '<div class="autocomplete-main">' + htmlEncode(r.PART_CODE) + ' - ' + htmlEncode(r.PART_NAME) + '</div>' +
                '<div class="autocomplete-sub">' +
                    'PRICE_ID: ' + htmlEncode(r.PRICE_ID) +
                    ' | PRICE: ' + htmlEncode(r.PRDT_PRICE) +
                    ' | PERIOD: ' + htmlEncode(r.PRDT_START) + ' s/d ' + htmlEncode(r.PRDT_END) +
                '</div>';

            div.onmouseover = function () {
                setActiveItemSuggest(idx);
            };

            div.onmousedown = function (e) {
                if (e && e.preventDefault) {
                    e.preventDefault();
                }

                chooseItemSuggest(idx);
            };

            box.appendChild(div);
        })(rows[i], i);
    }

    box.style.display = "block";
    setActiveItemSuggest(0);
}

function searchItemAutocomplete(input) {
    var q = input.value;
    var orderDate = document.getElementById("ORDR_DATE").value;
    var custId = document.getElementById("CUST_ID").value;

    if (q.length < 2) {
        hideItemSuggest();
        return;
    }

    if (custId == "") {
        alert("Customer belum dipilih. Pilih customer dulu.");
        document.getElementById("CUST_CODE").focus();
        hideItemSuggest();
        return;
    }

    if (orderDate == "") {
        alert("ORDER DATE belum diisi.");
        document.getElementById("ORDR_DATE").focus();
        hideItemSuggest();
        return;
    }

    ajaxPost(
        "ajax_input_order_item.php",
        "q=" + enc(q) +
        "&ORDR_DATE=" + enc(orderDate) +
        "&CUST_ID=" + enc(custId),
        function (status, responseText) {
            if (status != 200) {
                hideItemSuggest();
                return;
            }

            var result;

            try {
                result = JSON.parse(responseText);
            } catch (e) {
                hideItemSuggest();
                return;
            }

            if (!result.success) {
                hideItemSuggest();

                if (result.message) {
                    setStatus(result.message);
                }

                return;
            }

            showItemSuggest(input, result.rows);
        }
    );
}

function setActiveCustSuggest(index) {
    var box = document.getElementById("custSuggest");
    var items = box.getElementsByClassName("autocomplete-item");

    if (!items || items.length == 0) {
        custSuggestIndex = -1;
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
    custSuggestIndex = index;

    if (items[index].offsetTop < box.scrollTop) {
        box.scrollTop = items[index].offsetTop;
    } else if ((items[index].offsetTop + items[index].offsetHeight) > (box.scrollTop + box.clientHeight)) {
        box.scrollTop = items[index].offsetTop + items[index].offsetHeight - box.clientHeight;
    }
}

function chooseCustSuggest(index) {
    if (index < 0 || index >= custSuggestRows.length) {
        return;
    }

    var r = custSuggestRows[index];

    document.getElementById("CUST_ID").value = r.CUST_ID;
    document.getElementById("CUST_CODE").value = r.CUST_CODE;
    document.getElementById("CUST_COMP").value = r.CUST_COMP;
    document.getElementById("ORDR_CURR").value = r.CURR_CODE;

    hideCustSuggest();

    document.getElementById("ORDR_PO").focus();
    document.getElementById("ORDR_PO").select();
}

function custSuggestKeyDown(e) {
    e = e || window.event;

    var key = e.keyCode || e.which;
    var box = document.getElementById("custSuggest");

    if (!box || box.style.display != "block") {
        return true;
    }

    if (key == 40) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        setActiveCustSuggest(custSuggestIndex + 1);
        return false;
    }

    if (key == 38) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;
        setActiveCustSuggest(custSuggestIndex - 1);
        return false;
    }

    if (key == 13) {
        e.preventDefault ? e.preventDefault() : e.returnValue = false;

        if (custSuggestIndex < 0 && custSuggestRows.length > 0) {
            custSuggestIndex = 0;
        }

        chooseCustSuggest(custSuggestIndex);
        return false;
    }

    return true;
}

function hideCustSuggest() {
    var box = document.getElementById("custSuggest");

    if (box) {
        box.style.display = "none";
        box.innerHTML = "";
    }

    custSuggestRows = [];
    custSuggestIndex = -1;
}

function showCustSuggest(rows) {
    var box = document.getElementById("custSuggest");
    box.innerHTML = "";

    custSuggestRows = rows || [];
    custSuggestIndex = -1;

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
                '<div class="autocomplete-sub">' + htmlEncode(r.CUST_COMP) + ' | ' + htmlEncode(r.CURR_CODE) + '</div>';

            div.onmouseover = function () {
                setActiveCustSuggest(idx);
            };

            div.onmousedown = function (e) {
                if (e && e.preventDefault) {
                    e.preventDefault();
                }

                chooseCustSuggest(idx);
            };

            box.appendChild(div);
        })(rows[i], i);
    }

    box.style.display = "block";
    setActiveCustSuggest(0);
}

function searchCustAutocomplete() {
    var q = document.getElementById("CUST_CODE").value;

    if (q.length < 1) {
        hideCustSuggest();
        return;
    }

    ajaxPost("ajax_input_order_customer.php", "q=" + enc(q), function (status, responseText) {
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

function collectRows() {
    var rows = document.getElementById("orderBody").getElementsByTagName("tr");
    var data = [];

    for (var i = 0; i < rows.length; i++) {
        var priceId = getInput(rows[i], "PRICE_ID").value;
        var qty = parseInt(getInput(rows[i], "ORDP_QTY").value || "0", 10);

        if (priceId == "" && qty == 0) {
            continue;
        }

        data.push({
            ORDP_LINO: getInput(rows[i], "ORDP_LINO").value,
            PRICE_ID: priceId,
            PART_CODE: getInput(rows[i], "PART_CODE").value,
            PART_NAME: getInput(rows[i], "PART_NAME").value,
            ORDP_PRICE: getInput(rows[i], "ORDP_PRICE").value,
            ORDP_QTY: getInput(rows[i], "ORDP_QTY").value,
            ORDP_DQTY: getInput(rows[i], "ORDP_DQTY").value,
            ORDP_BQTY: getInput(rows[i], "ORDP_BQTY").value,
            ORDP_REM: getInput(rows[i], "ORDP_REM").value,
            ORDP_CLOSE: getInput(rows[i], "ORDP_CLOSE").checked ? 1 : 0
        });
    }

    return data;
}

function validateBeforeSave() {
    if (document.getElementById("CUST_ID").value == "") {
        alert("Customer belum dipilih.");
        document.getElementById("CUST_CODE").focus();
        return false;
    }

    if (document.getElementById("ORDR_PO").value == "") {
        alert("ORDER PO belum diisi.");
        document.getElementById("ORDR_PO").focus();
        return false;
    }

    if (document.getElementById("ORDR_DATE").value == "") {
        alert("DATE belum diisi.");
        document.getElementById("ORDR_DATE").focus();
        return false;
    }

    var rows = collectRows();

    if (rows.length == 0) {
        alert("Detail order masih kosong.");
        return false;
    }

    for (var i = 0; i < rows.length; i++) {
        if (rows[i].PRICE_ID == "") {
            alert("PRICE_ID kosong pada line " + rows[i].ORDP_LINO);
            return false;
        }

        if (parseInt(rows[i].ORDP_QTY || "0", 10) <= 0) {
            alert("QTY harus lebih dari 0 pada line " + rows[i].ORDP_LINO);
            return false;
        }
    }

    return true;
}

function saveDetailLineFromRow(row, callback) {
    var custId = document.getElementById("CUST_ID").value;
    var ordrPo = document.getElementById("ORDR_PO").value;
    var ordrDate = document.getElementById("ORDR_DATE").value;

    if (custId == "") {
        alert("Customer belum dipilih.");
        document.getElementById("CUST_CODE").focus();

        if (callback) callback(false);
        return;
    }

    if (ordrPo == "") {
        alert("ORDER PO belum diisi.");
        document.getElementById("ORDR_PO").focus();

        if (callback) callback(false);
        return;
    }

    if (ordrDate == "") {
        alert("ORDER DATE belum diisi.");
        document.getElementById("ORDR_DATE").focus();

        if (callback) callback(false);
        return;
    }

    var priceId = getInput(row, "PRICE_ID").value;
    var lino = getInput(row, "ORDP_LINO").value;
    var qty = parseInt(getInput(row, "ORDP_QTY").value || "0", 10);

    if (priceId == "") {
        alert("Pilih Part Code dulu pada line " + lino);
        getInput(row, "PART_CODE").focus();

        if (callback) callback(false);
        return;
    }

    if (isNaN(qty) || qty <= 0) {
        alert("QTY harus lebih dari 0 pada line " + lino);
        getInput(row, "ORDP_QTY").focus();

        if (callback) callback(false);
        return;
    }

    recalcRow(row);

    var data =
        "ORDR_ID=" + enc(document.getElementById("ORDR_ID").value) +
        "&CUST_ID=" + enc(document.getElementById("CUST_ID").value) +
        "&ORDR_PO=" + enc(document.getElementById("ORDR_PO").value) +
        "&ORDR_DATE=" + enc(document.getElementById("ORDR_DATE").value) +
        "&ORDR_CURR=" + enc(document.getElementById("ORDR_CURR").value) +
        "&ORDR_REM=" + enc(document.getElementById("ORDR_REM").value) +
        "&ORDR_REPLACEMENT=" + enc(document.getElementById("ORDR_REPLACEMENT").checked ? 1 : 0) +
        "&ORDR_CLOSE=" + enc(document.getElementById("ORDR_CLOSE").checked ? 1 : 0) +

        "&ORDP_LINO=" + enc(getInput(row, "ORDP_LINO").value) +
        "&PRICE_ID=" + enc(getInput(row, "PRICE_ID").value) +
        "&ORDP_PRICE=" + enc(getInput(row, "ORDP_PRICE").value) +
        "&ORDP_QTY=" + enc(getInput(row, "ORDP_QTY").value) +
        "&ORDP_DQTY=" + enc(getInput(row, "ORDP_DQTY").value) +
        "&ORDP_BQTY=" + enc(getInput(row, "ORDP_BQTY").value) +
        "&ORDP_REM=" + enc(getInput(row, "ORDP_REM").value) +
        "&ORDP_CLOSE=" + enc(getInput(row, "ORDP_CLOSE").checked ? 1 : 0);

    setStatus("Menyimpan detail line " + lino + "...");

    ajaxPost("ajax_input_order_save_line.php", data, function (status, responseText) {
        if (status != 200) {
            alert("HTTP Error: " + status);
            setStatus("Simpan detail gagal.");

            if (callback) callback(false);
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            alert("Response bukan JSON:\n\n" + responseText);
            setStatus("Simpan detail gagal.");

            if (callback) callback(false);
            return;
        }

        if (!result.success) {
            alert(result.message);
            setStatus("Simpan detail gagal.");

            if (callback) callback(false);
            return;
        }

        document.getElementById("ORDR_ID").value = result.ORDR_ID;
        getInput(row, "ORDR_ID").value = result.ORDR_ID;
        getInput(row, "ORDP_BQTY").value = result.ORDP_BQTY;

        setStatus(result.message + " ORDR_ID: " + result.ORDR_ID);

        if (callback) callback(true);
    });
}

function closeDeliveryModal() {
    document.getElementById("deliveryModal").style.display = "none";
}

function showDeliveryList(row) {
    var ordrIdInput = getInput(row, "ORDR_ID");
    var linoInput = getInput(row, "ORDP_LINO");
    var codeInput = getInput(row, "PART_CODE");
    var nameInput = getInput(row, "PART_NAME");

    var ordrId = "";

    if (ordrIdInput && ordrIdInput.value != "") {
        ordrId = ordrIdInput.value;
    } else {
        ordrId = document.getElementById("ORDR_ID").value;
    }

    var lino = linoInput ? linoInput.value : "";
    var partCode = codeInput ? codeInput.value : "";
    var partName = nameInput ? nameInput.value : "";

    if (ordrId == "") {
        alert("ORDR_ID kosong. Load / simpan order dulu.");
        return;
    }

    if (lino == "") {
        alert("Line detail kosong.");
        return;
    }

    document.getElementById("deliveryInfo").innerHTML =
        "ORDR_ID: " + htmlEncode(ordrId) +
        " | LINE: " + htmlEncode(lino) +
        " | PART: " + htmlEncode(partCode) +
        " - " + htmlEncode(partName);

    document.getElementById("deliveryBody").innerHTML =
        '<tr><td colspan="7">Loading...</td></tr>';

    var modal = document.getElementById("deliveryModal");
    var codeRect = codeInput.getBoundingClientRect();

    modal.style.display = "block";

    var leftPos = codeRect.left + window.pageXOffset;
    var topPos  = codeRect.bottom + window.pageYOffset + 6;

    var modalWidth = 800;
    var maxLeft = window.pageXOffset + window.innerWidth - modalWidth - 20;

    if (leftPos > maxLeft) {
        leftPos = maxLeft;
    }

    if (leftPos < 10) {
        leftPos = 10;
    }

    modal.style.left = leftPos + "px";
    modal.style.top  = topPos + "px";

    ajaxPost(
        "ajax_input_order_delivery_list.php",
        "ORDR_ID=" + enc(ordrId) + "&ORDP_LINO=" + enc(lino),
        function (status, responseText) {
            if (status != 200) {
                document.getElementById("deliveryBody").innerHTML =
                    '<tr><td colspan="7">HTTP Error: ' + status + '</td></tr>';
                return;
            }

            var result;

            try {
                result = JSON.parse(responseText);
            } catch (e) {
                document.getElementById("deliveryBody").innerHTML =
                    '<tr><td colspan="7">Response bukan JSON:<br>' + htmlEncode(responseText) + '</td></tr>';
                return;
            }

            if (!result.success) {
                document.getElementById("deliveryBody").innerHTML =
                    '<tr><td colspan="7">' + htmlEncode(result.message) + '</td></tr>';
                return;
            }

            if (!result.rows || result.rows.length == 0) {
                document.getElementById("deliveryBody").innerHTML =
                    '<tr><td colspan="7">Belum ada delivery untuk line ini.</td></tr>';
                return;
            }

            var html = "";
            var total = 0;

            for (var i = 0; i < result.rows.length; i++) {
                var r = result.rows[i];
                var qty = parseInt(r.DELIVERY || "0", 10);

                if (isNaN(qty)) {
                    qty = 0;
                }

                total += qty;

                html +=
                    "<tr>" +
                        "<td>" + htmlEncode(r.DI_DATE) + "</td>" +
                        "<td>" + htmlEncode(r.DI_NO) + "</td>" +
                        '<td style="text-align:right;">' + htmlEncode(r.DELIVERY) + "</td>" +
                        "<td>" + htmlEncode(r.CUST_CODE) + "</td>" +
                        "<td>" + htmlEncode(r.CUST_COMP) + "</td>" +
                        "<td>" + htmlEncode(r.ITEM_CODE) + "</td>" +
                        "<td>" + htmlEncode(r.ITEM_NAME) + "</td>" +
                    "</tr>";
            }

            html +=
                '<tr style="font-weight:bold;background:#eeeeee;">' +
                    '<td colspan="2" style="text-align:right;">TOTAL</td>' +
                    '<td style="text-align:right;">' + total + '</td>' +
                    '<td colspan="4"></td>' +
                '</tr>';

            document.getElementById("deliveryBody").innerHTML = html;
        }
    );
}

function saveOrder() {
    if (!validateBeforeSave()) {
        return;
    }

    if (!confirm("Simpan order ini?")) {
        return;
    }

    var rows = collectRows();

    var data =
        "ORDR_ID=" + enc(document.getElementById("ORDR_ID").value) +
        "&CUST_ID=" + enc(document.getElementById("CUST_ID").value) +
        "&ORDR_PO=" + enc(document.getElementById("ORDR_PO").value) +
        "&ORDR_DATE=" + enc(document.getElementById("ORDR_DATE").value) +
        "&ORDR_CURR=" + enc(document.getElementById("ORDR_CURR").value) +
        "&ORDR_REM=" + enc(document.getElementById("ORDR_REM").value) +
        "&ORDR_REPLACEMENT=" + enc(document.getElementById("ORDR_REPLACEMENT").checked ? 1 : 0) +
        "&ORDR_CLOSE=" + enc(document.getElementById("ORDR_CLOSE").checked ? 1 : 0) +
        "&ROWS_JSON=" + enc(JSON.stringify(rows));

    setStatus("Menyimpan order...");

    ajaxPost("ajax_input_order_save.php", data, function (status, responseText) {
        if (status != 200) {
            alert("HTTP Error: " + status);
            setStatus("Simpan gagal.");
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            alert("Response bukan JSON:\n\n" + responseText);
            setStatus("Simpan gagal.");
            return;
        }

        if (!result.success) {
            alert(result.message);
            setStatus("Simpan gagal.");
            return;
        }

        document.getElementById("ORDR_ID").value = result.ORDR_ID;
        alert(result.message);
        setStatus("Order berhasil disimpan. ORDR_ID: " + result.ORDR_ID);
    });
}

function findDuplicatePriceId(priceId, currentRow) {
    var rows = document.getElementById("orderBody").getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        if (rows[i] === currentRow) {
            continue;
        }

        var priceInput = getInput(rows[i], "PRICE_ID");

        if (priceInput && priceInput.value == priceId) {
            return rows[i];
        }
    }

    return null;
}

document.getElementById("CUST_CODE").onkeydown = function (e) {
    return custSuggestKeyDown(e);
};

document.getElementById("CUST_CODE").onkeyup = function (e) {
    e = e || window.event;

    var key = e.keyCode || e.which;

    if (key == 13 || key == 38 || key == 40) {
        return false;
    }

    clearTimeout(custTimer);

    custTimer = setTimeout(function () {
        searchCustAutocomplete();
    }, 250);
};

document.getElementById("CUST_CODE").onblur = function () {
    setTimeout(function () {
        hideCustSuggest();
    }, 250);
};

document.getElementById("ORDR_PO").onkeydown = function (e) {
    return poSuggestKeyDown(e);
};

document.getElementById("ORDR_PO").onkeyup = function (e) {
    e = e || window.event;

    var key = e.keyCode || e.which;

    if (key == 13 || key == 38 || key == 40) {
        return false;
    }

    prepareSearchNewPo();

    clearTimeout(poTimer);

    poTimer = setTimeout(function () {
        searchPoAutocomplete();
    }, 200);
};

document.getElementById("ORDR_PO").onfocus = function () {
    this.select();
};

document.getElementById("ORDR_PO").onblur = function () {
    setTimeout(function () {
        hidePoSuggest();
    }, 250);
};

document.getElementById("btnNew").onclick = function () {
    if (confirm("Buat input order baru?")) {
        clearForm();
    }
};

document.getElementById("btnAddRow").onclick = function () {
    addRow();
};

document.getElementById("btnDeleteDetail").onclick = function () {
    deleteSelectedRow();
};

document.getElementById("btnDeleteHeader").onclick = function () {
    deleteHeader();
};

document.getElementById("btnSave").onclick = function () {
    saveOrder();
};

document.getElementById("btnCancel").onclick = function () {
    if (confirm("Batal input order?")) {
        window.close();
    }
};

clearForm();
</script>

</body>
</html>