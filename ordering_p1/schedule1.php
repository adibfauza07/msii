<?php
require_once __DIR__ . "/../config/database_ordering.php";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$currentYear = (int)date('Y');
$currentMonth = (int)date('n');
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
            width: 98%;
            max-width: 1400px;
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
            color: #fff;
            text-transform: uppercase;
        }

        label {
            font-weight: bold;
            display: block;
            margin-top: 6px;
            color: #fff;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        select {
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
            height: 26px;
            min-width: 80px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            margin-right: 3px;
            background: #e2e2e2;
            border: 1px solid #555;
        }
        button:hover { background: #fff; }

        .layout {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .left-panel {
            width: 100%;
        }

        .right-panel {
            width: 100%;
            background: #d3d0c8;
            border: 1px solid #555;
            padding: 10px;
            overflow-x: auto;
            box-sizing: border-box;
        }

        .cust-code { width: 90px; }
        .cust-name { width: 340px; }
        .item-search { width: 450px; }

        .status {
            color: #ffeb3b;
            font-weight: bold;
            min-height: 18px;
            margin: 8px 0;
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
            height: 25px;
            font-size: 11px;
            box-sizing: border-box;
            white-space: nowrap;
            overflow: hidden;
        }

        th {
            background: #d9d6ce;
            font-weight: bold;
            text-align: center;
        }

        .num { text-align: right; }

        .item-table .col-code { width: 85px; }
        .item-table .col-prcd { width: 95px; }
        .item-table .col-no { width: 140px; }
        .item-table .col-name { width: auto; }
        .item-table .col-delivery { width: 90px; text-align: right; }
        .item-table .col-bal { width: 90px; text-align: right; }

        .item-table tbody tr { cursor: pointer; }
        .item-table tr:focus { outline: 2px solid #0033ff; }

        .schedule-table { min-width: 1200px; }
        .schedule-table th.col-desc { width: 150px; text-align: left; background: #e2e8f0; }
        .schedule-table th.col-day { width: 60px; } 
        
        .schedule-table input.matrix-input {
            width: 100%;
            height: 22px;
            border: 1px solid #0056b3;
            background: #e6f7ff;
            text-align: right;
            font-weight: bold;
            color: #000;
            padding-right: 3px;
        }
        .schedule-table input.matrix-input:focus {
            background: #fff;
            border: 2px solid #ff9900;
            outline: none;
        }

        .selected { background: #ffeb3b !important; color: #000; }
        .selected td { background: inherit; }

        .autocomplete-wrap { position: relative; display: inline-block; }
        .autocomplete-list {
            position: absolute;
            top: 24px; left: 0;
            width: 420px; max-height: 230px;
            overflow-y: auto; background: #fff; border: 1px solid #444;
            z-index: 9999; display: none; box-shadow: 2px 2px 5px rgba(0,0,0,0.25);
        }
        .autocomplete-item { padding: 5px 7px; border-bottom: 1px solid #ddd; cursor: pointer; line-height: 16px; }
        .autocomplete-item:hover, .autocomplete-item.active { background: #2f70c9; color: #fff; }
        .autocomplete-main { font-weight: bold; color: inherit; }
        .autocomplete-sub { color: #555; font-size: 11px; }
        .autocomplete-item:hover .autocomplete-sub, .autocomplete-item.active .autocomplete-sub { color: #e2e2e2; }

        .toolbar { margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; }
        .small-info { font-size: 14px; color: #0056b3; font-weight: bold; margin-bottom: 10px; }
    </style>
</head>

<body>

<div class="form-box">
    <div class="title">DELIVERY SCHEDULE MATRIX</div>

    <input type="hidden" id="CUST_ID" value="">
    <input type="hidden" id="PRICE_ID" value="">

    <div class="layout">
        <!-- PANEL ATAS (MASTER DATA) -->
        <div class="left-panel">
            <label>CUST CODE</label>
            <div class="autocomplete-wrap">
                <input type="text" id="CUST_CODE" class="cust-code" autocomplete="off">
                <div id="custSuggest" class="autocomplete-list"></div>
            </div>

            <label>CUST NAME</label>
            <input type="text" id="CUST_COMP" class="cust-name" readonly>

            <div style="margin-top: 10px;">
                <button type="button" id="btnRefreshItem">REFRESH ITEM</button>
            </div>

            <label style="margin-top: 15px;">CARI ITEM CODE / NAME</label>
            <div class="autocomplete-wrap">
                <input type="text" id="ITEM_SEARCH" class="item-search" autocomplete="off" placeholder="Ketik part code / part name / part no">
                <div id="itemSearchSuggest" class="autocomplete-list" style="width: 500px;"></div>
            </div>

            <div class="status" id="LabelStatus">Siap digunakan.</div>

            <!-- TABEL ITEM KOMPLIT -->
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
                        <td colspan="6" style="text-align: center;">Pilih customer dulu.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- PANEL BAWAH (MATRIX SCHEDULE) -->
        <div class="right-panel">
            <div class="small-info" id="selectedPartInfo">Belum ada part yang dipilih.</div>

            <div class="toolbar">
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label style="color:#000; margin:0;">Tahun:</label>
                    <select id="filterYear">
                        <?php for ($y = $currentYear - 1; $y <= $currentYear + 2; $y++) {
                            $sel = ($y === $currentYear) ? 'selected' : '';
                            echo "<option value=\"$y\" $sel>$y</option>";
                        } ?>
                    </select>
                    
                    <label style="color:#000; margin:0;">Bulan:</label>
                    <select id="filterMonth">
                        <?php for ($m = 1; $m <= 12; $m++) {
                            $sel = ($m === $currentMonth) ? 'selected' : '';
                            $monthName = date("F", mktime(0, 0, 0, $m, 1));
                            echo "<option value=\"$m\" $sel>$monthName</option>";
                        } ?>
                    </select>
                </div>
                <div>
                    <button type="button" id="btnReloadSchedule">RELOAD DB</button>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="schedule-table">
                    <thead id="matrixHead">
                        <tr><th style="padding:20px;">Memuat Matrix...</th></tr>
                    </thead>
                    <tbody id="matrixBody">
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 10px; font-size: 11px; color: #555;">
                <i>* Hapus angka (kosongkan) dengan Backspace/Delete. Tekan <b>ENTER</b> di dalam kolom untuk menyimpan otomatis dan langsung pindah ke tanggal berikutnya.</i>
            </div>
        </div>
    </div>
</div>

<script>
var custTimer = null;
var custRows = [];
var custIndex = -1;

var selectedItemRow = null;

var itemRowsAll = [];
var itemRowsCache = [];
var itemSearchTimer = null;

var currentScheduleData = [];

function enc(v) { return encodeURIComponent(v == null ? "" : v); }
function htmlEncode(value) {
    return String(value == null ? "" : value)
        .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

function ajaxPost(url, data, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", url, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4) { callback(xhr.status, xhr.responseText); }
    };
    xhr.send(data);
}

function setStatus(text) { document.getElementById("LabelStatus").innerHTML = text; }

// ==============================================================
// CUSTOMER & ITEM AUTOCOMPLETE 
// ==============================================================
function hideCustSuggest() {
    var box = document.getElementById("custSuggest");
    if (box) { box.style.display = "none"; box.innerHTML = ""; }
    custRows = []; custIndex = -1;
}

function setActiveCust(index) {
    var box = document.getElementById("custSuggest");
    var items = box.getElementsByClassName("autocomplete-item");
    if (!items || items.length == 0) { custIndex = -1; return; }
    if (index < 0) index = items.length - 1;
    if (index >= items.length) index = 0;
    for (var i = 0; i < items.length; i++) items[i].className = "autocomplete-item";
    items[index].className = "autocomplete-item active";
    custIndex = index;
    if (items[index].offsetTop < box.scrollTop) box.scrollTop = items[index].offsetTop;
    else if ((items[index].offsetTop + items[index].offsetHeight) > (box.scrollTop + box.clientHeight)) 
        box.scrollTop = items[index].offsetTop + items[index].offsetHeight - box.clientHeight;
}

function chooseCust(index) {
    if (index < 0 || index >= custRows.length) return;
    var r = custRows[index];
    document.getElementById("CUST_ID").value = r.CUST_ID;
    document.getElementById("CUST_CODE").value = r.CUST_CODE;
    document.getElementById("CUST_COMP").value = r.CUST_COMP;
    document.getElementById("PRICE_ID").value = "";
    document.getElementById("ITEM_SEARCH").value = "";
    document.getElementById("selectedPartInfo").innerHTML = "Belum ada part yang dipilih.";
    document.getElementById("matrixHead").innerHTML = "<tr><th>Pilih part terlebih dahulu.</th></tr>";
    document.getElementById("matrixBody").innerHTML = "";
    hideCustSuggest();
    loadItems();
}

function showCustSuggest(rows) {
    var box = document.getElementById("custSuggest");
    box.innerHTML = "";
    custRows = rows || []; custIndex = -1;
    if (!rows || rows.length == 0) { box.style.display = "none"; return; }
    for (var i = 0; i < rows.length; i++) {
        (function (r, idx) {
            var div = document.createElement("div");
            div.className = "autocomplete-item";
            div.innerHTML = '<div class="autocomplete-main">' + htmlEncode(r.CUST_CODE) + '</div><div class="autocomplete-sub">' + htmlEncode(r.CUST_COMP) + '</div>';
            div.onmouseover = function () { setActiveCust(idx); };
            div.onclick = function () { chooseCust(idx); };
            box.appendChild(div);
        })(rows[i], i);
    }
    box.style.display = "block";
    setActiveCust(0);
}

function searchCustomer() {
    var q = document.getElementById("CUST_CODE").value;
    if (q.length < 1) { hideCustSuggest(); return; }
    ajaxPost("ajax_schedule_customer.php", "q=" + enc(q), function (status, responseText) {
        if (status != 200) { hideCustSuggest(); return; }
        try { var result = JSON.parse(responseText); if (result.success) showCustSuggest(result.rows); else hideCustSuggest(); } 
        catch (e) { hideCustSuggest(); }
    });
}

function loadItems() {
    var custId = document.getElementById("CUST_ID").value;
    if (custId == "") { alert("Pilih customer dulu."); document.getElementById("CUST_CODE").focus(); return; }
    setStatus("Loading item schedule...");
    ajaxPost("ajax_schedule_items.php", "CUST_ID=" + enc(custId), function (status, responseText) {
        if (status != 200) { setStatus("Load item gagal."); return; }
        try { 
            var result = JSON.parse(responseText); 
            if (result.success) { renderItems(result.rows); } 
            else { setStatus("Load item gagal."); }
        } catch (e) { setStatus("Load item gagal."); }
    });
}

function renderItems(rows) {
    itemRowsAll = rows || [];
    itemRowsCache = itemRowsAll;
    document.getElementById("ITEM_SEARCH").value = "";
    renderItemGrid(itemRowsAll);
    setStatus("Item loaded: " + itemRowsAll.length);
}

function renderItemGrid(rows, preventFocus) {
    var body = document.getElementById("itemBody");
    body.innerHTML = ""; selectedItemRow = null;
    document.getElementById("PRICE_ID").value = "";
    document.getElementById("selectedPartInfo").innerHTML = "Belum ada part yang dipilih.";
    document.getElementById("matrixHead").innerHTML = "<tr><th style='padding:20px;'>Pilih Part/Item untuk memuat matrix.</th></tr>";
    document.getElementById("matrixBody").innerHTML = "";

    if (!rows || rows.length == 0) {
        body.innerHTML = '<tr><td colspan="6" style="text-align:center;">Item tidak ditemukan.</td></tr>';
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
            };
            
            tr.onkeydown = function (e) {
                var key = e.keyCode || e.which;
                if (key == 40) { e.preventDefault(); moveItemSelection(1); return false; }
                if (key == 38) { e.preventDefault(); moveItemSelection(-1); return false; }
                if (key == 13) { e.preventDefault(); loadSchedule(); return false; }
            };

            body.appendChild(tr);
        })(rows[i]);
    }

    if (!preventFocus && body.getElementsByTagName("tr").length > 0) {
        var firstRow = body.getElementsByTagName("tr")[0];
        selectItemRow(firstRow);
        loadSchedule();
    }
}

function selectItemRow(row) {
    var rows = document.getElementById("itemBody").getElementsByTagName("tr");
    for (var i = 0; i < rows.length; i++) rows[i].className = "";
    row.className = "selected";
    selectedItemRow = row;

    document.getElementById("PRICE_ID").value = row.getAttribute("data-price-id");
    document.getElementById("selectedPartInfo").innerHTML =
        htmlEncode(row.getAttribute("data-part-code")) + " | " + htmlEncode(row.getAttribute("data-part-name"));
}

function moveItemSelection(direction) {
    var rows = document.getElementById("itemBody").getElementsByTagName("tr");
    if (!rows || rows.length == 0) return;
    
    var index = -1;
    for (var i = 0; i < rows.length; i++) { if (rows[i] === selectedItemRow) index = i; }
    index = (index < 0) ? 0 : index + direction;
    if (index < 0) index = 0;
    if (index >= rows.length) index = rows.length - 1;

    selectItemRow(rows[index]);
    loadSchedule();
    if (rows[index].scrollIntoView) rows[index].scrollIntoView({ block: "nearest" });
    rows[index].focus();
}

// Fitur Search Lokal Item + AUTO-SELECT jika sisa 1
document.getElementById("ITEM_SEARCH").onkeyup = function (e) {
    var key = e.keyCode || e.which;
    if (key == 13 || key == 38 || key == 40) return false;
    clearTimeout(itemSearchTimer);
    
    itemSearchTimer = setTimeout(function () {
        var q = document.getElementById("ITEM_SEARCH").value.toLowerCase();
        var result = [];
        if (q.length < 1) { 
            renderItemGrid(itemRowsAll, true); 
            setStatus("Item loaded: " + itemRowsAll.length); 
            return; 
        }
        
        for (var i = 0; i < itemRowsAll.length; i++) {
            var text = (String(itemRowsAll[i].ITEM_CODE) + " " + String(itemRowsAll[i].ITEM_NAME) + " " + String(itemRowsAll[i].ITEM_NO)).toLowerCase();
            if (text.indexOf(q) >= 0) result.push(itemRowsAll[i]);
        }
        
        renderItemGrid(result, true);
        
        // JIKA HASIL PENCARIAN SISA 1, OTOMATIS PILIH DAN TAMPIL SCHEDULE
        if (result.length === 1) {
            var firstRow = document.getElementById("itemBody").getElementsByTagName("tr")[0];
            if (firstRow && firstRow.getAttribute("data-price-id")) {
                selectItemRow(firstRow);
                loadSchedule();
            }
        }
        
        setStatus("Hasil pencarian item: " + result.length);
    }, 400);
};

// ==============================================================
// MATRIX SCHEDULE RENDER & SAVE 
// ==============================================================

function loadSchedule() {
    var priceId = document.getElementById("PRICE_ID").value;
    if (priceId == "") return;

    ajaxPost("ajax_schedule_load.php", "PRICE_ID=" + enc(priceId), function (status, responseText) {
        if (status != 200) { alert("HTTP Error: " + status); return; }
        try {
            var result = JSON.parse(responseText);
            if (result.success) {
                currentScheduleData = result.rows || [];
                renderMatrix();
            } else {
                alert(result.message);
            }
        } catch (e) { alert("Response bukan JSON."); }
    });
}

function syncMatrixToCache() {
    var y = parseInt(document.getElementById("filterYear").value);
    var m = parseInt(document.getElementById("filterMonth").value);
    var mStr = (m < 10 ? "0" + m : "" + m);
    var daysInMonth = new Date(y, m, 0).getDate();

    for (var i = 1; i <= daysInMonth; i++) {
        var input = document.querySelector('input[data-day="' + i + '"]');
        if (input) {
            var valStr = input.value.trim();
            var val = parseInt(valStr, 10);
            var dStr = (i < 10 ? "0" + i : "" + i);
            var fullDate = y + "-" + mStr + "-" + dStr;

            currentScheduleData = currentScheduleData.filter(function(r) { return r.DELS_DATE !== fullDate; });

            if (valStr === "" || isNaN(val) || val < 0) {
                val = 0; 
            }

            currentScheduleData.push({
                DELS_DATE: fullDate,
                DELS_QTY: val,
                DELS_C1: 0,
                DELS_C2: 0
            });
        }
    }
}

function renderMatrix() {
    var y = parseInt(document.getElementById("filterYear").value);
    var m = parseInt(document.getElementById("filterMonth").value);
    var daysInMonth = new Date(y, m, 0).getDate();

    // Build Header
    var headHtml = '<tr><th class="col-desc">Deskripsi</th>';
    for (var i = 1; i <= daysInMonth; i++) {
        headHtml += '<th class="col-day">' + i + '</th>';
    }
    headHtml += '</tr>';
    document.getElementById("matrixHead").innerHTML = headHtml;

    // Build Body (Input Row)
    var mName = document.getElementById("filterMonth").options[document.getElementById("filterMonth").selectedIndex].text;
    var bodyHtml = '<tr><td style="font-weight:bold; background:#f4f6f9;">DELS_QTY <br><span style="font-size:10px; color:#555;">' + mName + ' ' + y + '</span></td>';
    for (var i = 1; i <= daysInMonth; i++) {
        bodyHtml += '<td><input type="text" class="matrix-input num" data-day="' + i + '" autocomplete="off"></td>';
    }
    bodyHtml += '</tr>';
    document.getElementById("matrixBody").innerHTML = bodyHtml;

    // Fill existing data
    var mStr = (m < 10 ? "0" + m : "" + m);
    for (var j = 0; j < currentScheduleData.length; j++) {
        var dStr = currentScheduleData[j].DELS_DATE; 
        if (dStr) {
            var parts = dStr.split("-");
            if (parts.length === 3) {
                var ry = parseInt(parts[0], 10);
                var rm = parseInt(parts[1], 10);
                var rd = parseInt(parts[2], 10);
                
                if (ry === y && rm === m) {
                    var inputEl = document.querySelector('input[data-day="' + rd + '"]');
                    if (inputEl) {
                        var qty = parseInt(currentScheduleData[j].DELS_QTY, 10);
                        inputEl.value = (qty > 0) ? qty : ""; 
                    }
                }
            }
        }
    }
    
    // Event Keyboard (Enter, Kanan, Kiri, Tab)
    var matrixInputs = document.getElementsByClassName("matrix-input");
    for (var i = 0; i < matrixInputs.length; i++) {
        matrixInputs[i].onkeydown = function(e) {
            var key = e.keyCode || e.which;
            var currentDay = parseInt(this.getAttribute("data-day"));
            
            // Enter / Tab / Right = Simpan otomatis tanggal ini & Pindah ke tanggal berikutnya
            if (key == 13 || key == 39 || key == 9) {
                e.preventDefault();
                
                // Simpan otomatis ke database lewat AJAX
                saveScheduleSilent(function() {
                    var next = document.querySelector('input[data-day="' + (currentDay + 1) + '"]');
                    if (next) { 
                        next.focus(); 
                        next.select(); 
                    }
                });
                return false;
            }
            
            // Left = Pindah ke tanggal sebelumnya
            if (key == 37) { 
                e.preventDefault();
                var prev = document.querySelector('input[data-day="' + (currentDay - 1) + '"]');
                if (prev) { 
                    prev.focus(); 
                    prev.select(); 
                }
                return false;
            }
        };
    }
}

// Fungsi Simpan Otomatis di Background (Silent Save) saat Enter/Pindah Kolom
function saveScheduleSilent(callback) {
    var priceId = document.getElementById("PRICE_ID").value;
    if (priceId == "") { if (callback) callback(); return; }

    syncMatrixToCache();
    var rows = currentScheduleData;

    ajaxPost(
        "ajax_schedule_save.php",
        "PRICE_ID=" + enc(priceId) + "&ROWS_JSON=" + enc(JSON.stringify(rows)),
        function (status, responseText) {
            if (status == 200) {
                try {
                    var result = JSON.parse(responseText);
                    if (result.success) {
                        setStatus("Tersimpan otomatis.");
                    } else {
                        setStatus("Gagal simpan: " + result.message);
                    }
                } catch (e) {}
            }
            if (callback) callback();
        }
    );
}

// ==============================================================
// EVENT LISTENERS UMUM
// ==============================================================

document.getElementById("filterYear").onchange = function() { syncMatrixToCache(); renderMatrix(); };
document.getElementById("filterMonth").onchange = function() { syncMatrixToCache(); renderMatrix(); };

document.getElementById("btnRefreshItem").onclick = function () { loadItems(); };
document.getElementById("btnReloadSchedule").onclick = function () { loadSchedule(); };

document.getElementById("CUST_CODE").onkeyup = function (e) {
    var key = e.keyCode || e.which;
    if (key == 13 || key == 38 || key == 40) return false;
    clearTimeout(custTimer);
    custTimer = setTimeout(function () { searchCustomer(); }, 250);
};

document.getElementById("CUST_CODE").focus();
</script>

</body>
</html>