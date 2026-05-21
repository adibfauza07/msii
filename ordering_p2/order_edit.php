<?php
require_once __DIR__ . "/../config/db_plant2.php";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$po = isset($_GET["po"]) ? trim($_GET["po"]) : "";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order Edit Plant 2</title>

    <style>
        body {
            margin: 0;
            background: #d3d0c8;
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
        }

        .form-box {
            width: 900px;
            margin: 20px auto;
            background: #d3d0c8;
            border: 1px solid #666;
            padding: 12px;
            box-sizing: border-box;
        }

        .title {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .row {
            display: flex;
            align-items: center;
            margin-bottom: 7px;
        }

        .row label {
            width: 90px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        input[type="text"],
        input[type="number"] {
            height: 22px;
            border: 1px solid #888;
            padding: 2px 5px;
            box-sizing: border-box;
            font-size: 12px;
        }

        .header-input {
            width: 360px;
            background: #e6e6e6;
        }

        .search-input {
            width: 280px;
            background: #fff;
        }

        button {
            height: 26px;
            min-width: 75px;
            font-size: 12px;
            cursor: pointer;
            margin-right: 4px;
        }

        .toolbar {
            margin-top: 10px;
            margin-bottom: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #777;
            padding: 3px;
            font-size: 12px;
            height: 24px;
            box-sizing: border-box;
            overflow: hidden;
            white-space: nowrap;
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

        .col-order,
        .col-delivery,
        .col-balance {
            width: 90px;
            text-align: right;
        }

        .col-ket {
            width: 180px;
        }

        .grid-input {
            width: 100%;
            border: none;
            background: transparent;
            font-size: 12px;
            height: 20px;
            box-sizing: border-box;
        }

        .grid-input:disabled {
            color: #000;
            background: transparent;
        }

        .grid-input.editable:not(:disabled) {
            background: #ffffff;
            border: 1px solid #777;
        }

        .num {
            text-align: right;
        }

        .readonly-cell {
            background: #efefef;
        }

        .selected {
            background: #2f70c9;
            color: #fff;
        }

        .selected input {
            color: #fff;
        }

        .selected input.editable:not(:disabled) {
            color: #000;
            background: #fff;
        }

        .status {
            color: navy;
            margin: 8px 0;
            min-height: 18px;
        }

        .bottom-buttons {
            margin-top: 12px;
            text-align: center;
        }

        #btnEdit {
            display: none;
        }

        .autocomplete-wrap {
            position: relative;
            display: inline-block;
        }

        .autocomplete-list {
            position: absolute;
            top: 24px;
            left: 0;
            width: 560px;
            max-height: 230px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid #555;
            z-index: 9999;
            display: none;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.25);
        }

        .autocomplete-item {
            padding: 5px 7px;
            cursor: pointer;
            border-bottom: 1px solid #dddddd;
            font-size: 12px;
            line-height: 16px;
        }

        .autocomplete-item:hover {
            background: #2f70c9;
            color: #ffffff;
        }

        .autocomplete-po {
            font-weight: bold;
        }

        .autocomplete-cust {
            font-size: 11px;
            color: #555555;
        }

        .autocomplete-item:hover .autocomplete-cust {
            color: #ffffff;
        }
    </style>
</head>

<body>

<div class="form-box">
    <div class="title">Order Edit Plant 2</div>

    <div class="row">
        <label>PO NUMBER</label>

        <div class="autocomplete-wrap">
            <input type="text" id="SEARCH_PO" class="search-input" value="<?php echo h($po); ?>" autocomplete="off">
            <div id="poSuggest" class="autocomplete-list"></div>
        </div>

        <button type="button" id="btnCari">CARI</button>
    </div>

    <input type="hidden" id="ORDR_ID" value="">

    <div class="row">
        <label>PO NUMBER</label>
        <input type="text" id="ORDR_PO" class="header-input" readonly>
    </div>

    <div class="row">
        <label>PO DATE</label>
        <input type="text" id="ORDR_DATE" class="header-input" readonly>
    </div>

    <div class="row">
        <label>CUSTOMER</label>
        <input type="text" id="CUST_COMP" class="header-input" style="width:520px;" readonly>
    </div>

    <div class="row">
        <label>CUST CODE</label>
        <input type="text" id="CUST_CODE" class="header-input" style="width:180px;" readonly>
    </div>

    <div class="toolbar">
        <button type="button" id="btnRefresh">REFRESH</button>
    </div>

    <div class="status" id="LabelStatus"></div>

    <table>
        <thead>
            <tr>
                <th class="col-no">#</th>
                <th class="col-code">CODE</th>
                <th class="col-name">NAME</th>
                <th class="col-order">ORDER</th>
                <th class="col-delivery">DELIVERY</th>
                <th class="col-balance">BALANCE</th>
                <th class="col-ket">KETERANGAN</th>
            </tr>
        </thead>
        <tbody id="orderBody">
            <tr>
                <td colspan="7">Belum ada data.</td>
            </tr>
        </tbody>
    </table>

    <div class="bottom-buttons">
        <button type="button" id="btnEdit">RUBAH</button>
        <button type="button" id="btnSave" disabled>SIMPAN</button>
        <button type="button" id="btnCancel" disabled>BATAL</button>
    </div>
</div>

<script>
var originalRows = [];
var editMode = false;
var poSearchTimer = null;
var currentOrdrId = "";

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

function setCurrentOrderId(value) {
    value = value == null ? "" : String(value);

    currentOrdrId = value;
    document.getElementById("ORDR_ID").value = value;
}

function getCurrentOrderId() {
    var ordrId = document.getElementById("ORDR_ID").value;

    if (ordrId != "") {
        return ordrId;
    }

    if (currentOrdrId != "") {
        document.getElementById("ORDR_ID").value = currentOrdrId;
        return currentOrdrId;
    }

    var rows = document.getElementById("orderBody").getElementsByTagName("tr");

    if (rows.length > 0) {
        var rowOrdrId = getRowInput(rows[0], "ORDR_ID");

        if (rowOrdrId && rowOrdrId.value != "") {
            setCurrentOrderId(rowOrdrId.value);
            return rowOrdrId.value;
        }
    }

    return "";
}

function hidePoSuggest() {
    var box = document.getElementById("poSuggest");

    if (box) {
        box.style.display = "none";
        box.innerHTML = "";
    }
}

function showPoSuggest(rows) {
    var box = document.getElementById("poSuggest");

    if (!box) {
        return;
    }

    box.innerHTML = "";

    if (!rows || rows.length == 0) {
        box.style.display = "none";
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        (function (r) {
            var div = document.createElement("div");
            div.className = "autocomplete-item";

            div.innerHTML =
                '<div class="autocomplete-po">' + htmlEncode(r.ORDR_PO) + '</div>' +
                '<div class="autocomplete-cust">' +
                    htmlEncode(r.ORDR_DATE) + ' | ' +
                    htmlEncode(r.CUST_CODE) + ' | ' +
                    htmlEncode(r.CUST_COMP) +
                '</div>';

            div.onclick = function () {
                document.getElementById("SEARCH_PO").value = r.ORDR_PO;
                setCurrentOrderId(r.ORDR_ID || "");
                hidePoSuggest();
                loadOrder();
            };

            box.appendChild(div);
        })(rows[i]);
    }

    box.style.display = "block";
}

function searchPoAutocomplete() {
    var q = document.getElementById("SEARCH_PO").value;

    if (q.length < 2) {
        hidePoSuggest();
        return;
    }

    ajaxPost("ajax_search_order_po.php", "q=" + enc(q), function (status, responseText) {
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
    });
}

function clearForm() {
    setCurrentOrderId("");

    document.getElementById("ORDR_PO").value = "";
    document.getElementById("ORDR_DATE").value = "";
    document.getElementById("CUST_COMP").value = "";
    document.getElementById("CUST_CODE").value = "";

    document.getElementById("orderBody").innerHTML =
        '<tr><td colspan="7">Belum ada data.</td></tr>';

    originalRows = [];
    setEditMode(false);
}

function setEditMode(value) {
    editMode = value;

    document.getElementById("btnEdit").style.display = "none";
    document.getElementById("btnSave").disabled = !value;
    document.getElementById("btnCancel").disabled = !value;

    var inputs = document.getElementById("orderBody").getElementsByTagName("input");

    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].className.indexOf("editable") >= 0) {
            inputs[i].disabled = !value;
        } else {
            inputs[i].disabled = true;
        }
    }
}

function selectRow(row) {
    var rows = document.getElementById("orderBody").getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        rows[i].className = "";
    }

    row.className = "selected";
}

function getRowInput(row, name) {
    var inputs = row.getElementsByTagName("input");

    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].getAttribute("data-name") == name) {
            return inputs[i];
        }
    }

    return null;
}

function recalcRow(row) {
    var orderInput = getRowInput(row, "ORDP_QTY");
    var deliveryInput = getRowInput(row, "ORDP_DQTY");
    var balanceInput = getRowInput(row, "ORDP_BQTY");

    var orderQty = parseInt(orderInput.value || "0", 10);
    var deliveryQty = parseInt(deliveryInput.value || "0", 10);

    if (isNaN(orderQty)) {
        orderQty = 0;
    }

    if (isNaN(deliveryQty)) {
        deliveryQty = 0;
    }

    var balance = orderQty - deliveryQty;

    if (balance < 0) {
        balance = 0;
    }

    balanceInput.value = balance;
}

function validateRow(row) {
    var linoInput = getRowInput(row, "ORDP_LINO");
    var orderInput = getRowInput(row, "ORDP_QTY");
    var deliveryInput = getRowInput(row, "ORDP_DQTY");
    var balanceInput = getRowInput(row, "ORDP_BQTY");

    var lineNo = linoInput.value;
    var orderQty = parseInt(orderInput.value || "0", 10);
    var deliveryQty = parseInt(deliveryInput.value || "0", 10);
    var balanceQty = parseInt(balanceInput.value || "0", 10);

    if (isNaN(orderQty) || orderQty < 0) {
        alert("ORDER tidak valid pada line " + lineNo);
        orderInput.focus();
        return false;
    }

    if (isNaN(deliveryQty) || deliveryQty < 0) {
        alert("DELIVERY tidak valid pada line " + lineNo);
        deliveryInput.focus();
        return false;
    }

    if (isNaN(balanceQty) || balanceQty < 0) {
        alert("BALANCE tidak valid pada line " + lineNo);
        balanceInput.focus();
        return false;
    }

    if (orderQty < deliveryQty) {
        alert(
            "ORDER tidak boleh lebih kecil dari DELIVERY.\n\n" +
            "Line     : " + lineNo + "\n" +
            "ORDER    : " + orderQty + "\n" +
            "DELIVERY : " + deliveryQty
        );

        orderInput.focus();
        return false;
    }

    return true;
}

function validateAllRows() {
    var rows = document.getElementById("orderBody").getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        var linoInput = getRowInput(rows[i], "ORDP_LINO");

        if (!linoInput) {
            continue;
        }

        if (!validateRow(rows[i])) {
            return false;
        }
    }

    return true;
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

function focusGridInputByName(rowIndex, fieldName) {
    var rows = document.getElementById("orderBody").getElementsByTagName("tr");

    if (rowIndex < 0 || rowIndex >= rows.length) {
        return;
    }

    var input = getRowInput(rows[rowIndex], fieldName);

    if (input && !input.disabled) {
        input.focus();
        input.select();
        selectRow(rows[rowIndex]);
    }
}

function gridInputKeyDown(e) {
    e = e || window.event;

    var key = e.keyCode || e.which;
    var fieldName = this.getAttribute("data-name");
    var row = this.parentNode.parentNode;
    var rowIndex = getRowIndex(row);

    if (key == 13 || key == 40) {
        if (fieldName == "ORDP_QTY" || fieldName == "ORDP_DQTY" || fieldName == "ORDP_BQTY" || fieldName == "ORDP_DESC") {
            e.preventDefault ? e.preventDefault() : e.returnValue = false;

            if (fieldName == "ORDP_QTY" || fieldName == "ORDP_DQTY") {
                recalcRow(row);
            }

            focusGridInputByName(rowIndex + 1, fieldName);
            return false;
        }
    }

    if (key == 38) {
        if (fieldName == "ORDP_QTY" || fieldName == "ORDP_DQTY" || fieldName == "ORDP_BQTY" || fieldName == "ORDP_DESC") {
            e.preventDefault ? e.preventDefault() : e.returnValue = false;

            if (fieldName == "ORDP_QTY" || fieldName == "ORDP_DQTY") {
                recalcRow(row);
            }

            focusGridInputByName(rowIndex - 1, fieldName);
            return false;
        }
    }
}

function fillHeader(h) {
    var ordrId = "";

    if (h.ORDR_ID !== undefined && h.ORDR_ID !== null && h.ORDR_ID !== "") {
        ordrId = h.ORDR_ID;
    } else if (h.ordr_id !== undefined && h.ordr_id !== null && h.ordr_id !== "") {
        ordrId = h.ordr_id;
    } else if (h.ORDER_ID !== undefined && h.ORDER_ID !== null && h.ORDER_ID !== "") {
        ordrId = h.ORDER_ID;
    }

    setCurrentOrderId(ordrId);

    document.getElementById("ORDR_PO").value = h.ORDR_PO || "";
    document.getElementById("ORDR_DATE").value = h.ORDR_DATE || "";
    document.getElementById("CUST_COMP").value = h.CUST_COMP || "";
    document.getElementById("CUST_CODE").value = h.CUST_CODE || "";

    if (h.ORDR_PO) {
        document.getElementById("SEARCH_PO").value = h.ORDR_PO;
    }
}

function fillGrid(rows) {
    var tbody = document.getElementById("orderBody");
    tbody.innerHTML = "";

    if (!rows || rows.length == 0) {
        tbody.innerHTML = '<tr><td colspan="7">Detail order kosong.</td></tr>';
        originalRows = [];
        setEditMode(false);
        return;
    }

    originalRows = JSON.parse(JSON.stringify(rows));

    if (getCurrentOrderId() == "" && rows.length > 0 && rows[0].ORDR_ID) {
        setCurrentOrderId(rows[0].ORDR_ID);
    }

    for (var i = 0; i < rows.length; i++) {
        var r = rows[i];

        var tr = document.createElement("tr");
        tr.onclick = function () {
            selectRow(this);
        };

        tr.innerHTML =
            '<td class="col-no">' +
                '<input type="hidden" data-name="ORDR_ID" value="' + htmlEncode(r.ORDR_ID) + '">' +
                '<input type="hidden" data-name="ORDP_LINO" value="' + htmlEncode(r.ORDP_LINO) + '">' +
                htmlEncode(r.ORDP_LINO) +
            '</td>' +

            '<td class="col-code readonly-cell">' +
                '<input type="text" class="grid-input" data-name="CODE" value="' + htmlEncode(r.CODE) + '" disabled>' +
            '</td>' +

            '<td class="col-name readonly-cell">' +
                '<input type="text" class="grid-input" data-name="NAME" value="' + htmlEncode(r.NAME) + '" disabled>' +
            '</td>' +

            '<td class="col-order">' +
                '<input type="number" class="grid-input num editable" data-name="ORDP_QTY" value="' + htmlEncode(r.ORDP_QTY) + '" disabled>' +
            '</td>' +

            '<td class="col-delivery">' +
                '<input type="number" class="grid-input num editable" data-name="ORDP_DQTY" value="' + htmlEncode(r.ORDP_DQTY) + '" disabled>' +
            '</td>' +

            '<td class="col-balance">' +
                '<input type="number" class="grid-input num editable" data-name="ORDP_BQTY" value="' + htmlEncode(r.ORDP_BQTY) + '" disabled>' +
            '</td>' +

            '<td class="col-ket">' +
                '<input type="text" class="grid-input editable" data-name="ORDP_DESC" value="' + htmlEncode(r.ORDP_DESC) + '" disabled>' +
            '</td>';

        tbody.appendChild(tr);

        var orderInput = getRowInput(tr, "ORDP_QTY");
        var deliveryInput = getRowInput(tr, "ORDP_DQTY");
        var balanceInput = getRowInput(tr, "ORDP_BQTY");
        var descInput = getRowInput(tr, "ORDP_DESC");

        orderInput.onkeyup = function () {
            recalcRow(this.parentNode.parentNode);
        };

        orderInput.onchange = function () {
            recalcRow(this.parentNode.parentNode);
        };

        deliveryInput.onkeyup = function () {
            recalcRow(this.parentNode.parentNode);
        };

        deliveryInput.onchange = function () {
            recalcRow(this.parentNode.parentNode);
        };

        orderInput.onkeydown = gridInputKeyDown;
        deliveryInput.onkeydown = gridInputKeyDown;
        balanceInput.onkeydown = gridInputKeyDown;
        descInput.onkeydown = gridInputKeyDown;
    }

    setEditMode(true);
    setStatus("Data order ditemukan. ORDR_ID: " + getCurrentOrderId() + " - langsung bisa diedit.");
}

function loadOrder(callback) {
    var po = document.getElementById("SEARCH_PO").value;

    if (po == "") {
        alert("PO NUMBER belum diisi.");
        document.getElementById("SEARCH_PO").focus();

        if (callback) {
            callback(false);
        }

        return;
    }

    setStatus("Mencari order...");

    ajaxPost("ajax_get_order.php", "ORDR_PO=" + enc(po), function (status, responseText) {
        if (status != 200) {
            alert("HTTP Error: " + status);
            setStatus("Cari order gagal.");

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
            setStatus("Cari order gagal.");

            if (callback) {
                callback(false);
            }

            return;
        }

        if (!result.success) {
            alert(result.message);
            clearForm();
            setStatus("Cari order gagal.");

            if (callback) {
                callback(false);
            }

            return;
        }

        fillHeader(result.header);
        fillGrid(result.details);

        if (getCurrentOrderId() == "") {
            alert(
                "ORDR_ID masih kosong setelah cari PO.\n\n" +
                "Cek ajax_get_order.php, header wajib mengirim ORDR_ID."
            );

            setStatus("Cari order gagal. ORDR_ID kosong.");

            if (callback) {
                callback(false);
            }

            return;
        }

        if (callback) {
            callback(true);
        }
    });
}

function collectRows() {
    var rows = document.getElementById("orderBody").getElementsByTagName("tr");
    var data = [];

    for (var i = 0; i < rows.length; i++) {
        var lino = getRowInput(rows[i], "ORDP_LINO");

        if (!lino) {
            continue;
        }

        var orderQty = getRowInput(rows[i], "ORDP_QTY");
        var deliveryQty = getRowInput(rows[i], "ORDP_DQTY");
        var balanceQty = getRowInput(rows[i], "ORDP_BQTY");
        var desc = getRowInput(rows[i], "ORDP_DESC");

        data.push({
            ORDP_LINO: lino.value,
            ORDP_QTY: orderQty.value,
            ORDP_DQTY: deliveryQty.value,
            ORDP_BQTY: balanceQty.value,
            ORDP_DESC: desc.value
        });
    }

    return data;
}

function saveOrder() {
    var ordrId = getCurrentOrderId();

    if (ordrId == "") {
        alert(
            "ORDR_ID kosong.\n\n" +
            "Klik CARI ulang dulu sampai PO Number, Customer, dan detail muncul."
        );
        return;
    }

    if (!validateAllRows()) {
        return;
    }

    if (!confirm("Simpan perubahan order ini?")) {
        return;
    }

    var rowsData = collectRows();

    setStatus("Menyimpan order... ORDR_ID: " + ordrId);

    ajaxPost(
        "ajax_save_order.php",
        "ORDR_ID=" + enc(ordrId) + "&ROWS_JSON=" + enc(JSON.stringify(rowsData)),
        function (status, responseText) {
            if (status != 200) {
                alert("HTTP Error: " + status);
                setStatus("Simpan order gagal.");
                return;
            }

            var result;

            try {
                result = JSON.parse(responseText);
            } catch (e) {
                alert("Response bukan JSON:\n\n" + responseText);
                setStatus("Simpan order gagal.");
                return;
            }

            if (!result.success) {
                alert(result.message);
                setStatus("Simpan order gagal.");
                return;
            }

            alert(result.message);
            loadOrder();
        }
    );
}

function cancelEdit() {
    if (!confirm("Batalkan perubahan?")) {
        return;
    }

    fillGrid(originalRows);
    setStatus("Perubahan dibatalkan. Data kembali seperti awal, masih bisa diedit.");
}

document.getElementById("btnCari").onclick = function () {
    hidePoSuggest();
    loadOrder();
};

document.getElementById("btnRefresh").onclick = function () {
    hidePoSuggest();
    loadOrder();
};

document.getElementById("btnEdit").onclick = function () {
    setEditMode(true);
};

document.getElementById("btnSave").onclick = function () {
    saveOrder();
};

document.getElementById("btnCancel").onclick = function () {
    cancelEdit();
};

document.getElementById("SEARCH_PO").onkeyup = function (e) {
    e = e || window.event;

    if (e.keyCode == 13) {
        hidePoSuggest();
        loadOrder();
        return false;
    }

    clearTimeout(poSearchTimer);

    poSearchTimer = setTimeout(function () {
        searchPoAutocomplete();
    }, 250);
};

document.getElementById("SEARCH_PO").onblur = function () {
    setTimeout(function () {
        hidePoSuggest();
    }, 250);
};

<?php if ($po != "") { ?>
window.onload = function () {
    loadOrder();
};
<?php } ?>
</script>

</body>
</html>