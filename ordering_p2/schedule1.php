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

        .top-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
            margin-bottom: 15px;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border: 1px solid #555;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            font-weight: bold;
            margin-bottom: 4px;
            color: #fff;
        }

        input[type="text"],
        input[type="number"],
        select {
            height: 24px;
            border: 1px solid #777;
            padding: 2px 4px;
            box-sizing: border-box;
            font-size: 12px;
        }

        input[readonly] { background: #e6e6e6; }

        button {
            height: 26px;
            padding: 0 15px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            background: #e2e2e2;
            border: 1px solid #555;
        }
        button:hover { background: #fff; }

        .table-container {
            width: 100%;
            overflow-x: auto;
            overflow-y: auto;
            max-height: 65vh;
            background: #d3d0c8;
            border: 1px solid #555;
        }

        table.item-table {
            width: auto;
            min-width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th, td {
            border: 1px solid #888;
            padding: 2px 4px;
            height: 25px;
            font-size: 11px;
            white-space: nowrap;
        }

        th {
            background: #d9d6ce;
            font-weight: bold;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .num { text-align: right; }

        /* Freeze first few columns for better UX */
        .item-table th:nth-child(1), .item-table td:nth-child(1) { position: sticky; left: 0; background: #f4f6f9; z-index: 5; }
        .item-table th:nth-child(4), .item-table td:nth-child(4) { position: sticky; left: 80px; background: #f4f6f9; z-index: 5; border-right: 2px solid #555;}
        
        /* Adjust z-index for frozen headers */
        .item-table th:nth-child(1), .item-table th:nth-child(4) { z-index: 15; background: #d9d6ce; }

        .matrix-input {
            width: 40px;
            height: 20px;
            border: 1px solid #0056b3;
            background: #e6f7ff;
            text-align: right;
            font-weight: bold;
            color: #000;
        }
        .matrix-input:focus {
            background: #fff;
            border: 2px solid #ff9900;
            outline: none;
        }

        .status { color: #ffeb3b; font-weight: bold; margin-left: auto; align-self: center; }

        .autocomplete-wrap { position: relative; display: inline-block; }
        .autocomplete-list {
            position: absolute; top: 24px; left: 0; width: 420px; max-height: 230px;
            overflow-y: auto; background: #fff; border: 1px solid #444;
            z-index: 9999; display: none; box-shadow: 2px 2px 5px rgba(0,0,0,0.25);
        }
        .autocomplete-item { padding: 5px 7px; border-bottom: 1px solid #ddd; cursor: pointer; line-height: 16px; }
        .autocomplete-item:hover, .autocomplete-item.active { background: #2f70c9; color: #fff; }
        .autocomplete-main { font-weight: bold; color: inherit; }
        .autocomplete-sub { color: #555; font-size: 11px; }
        
        .info-text { margin-top: 10px; font-size: 11px; color: #fff; }
    </style>
</head>
<body>

<div class="form-box">
    <div class="title">DELIVERY SCHEDULE MATRIX</div>

    <input type="hidden" id="CUST_ID" value="">

    <!-- TOOLBAR ATAS -->
    <div class="top-toolbar">
        <div class="form-group">
            <label>CUST CODE</label>
            <div class="autocomplete-wrap">
                <input type="text" id="CUST_CODE" style="width:100px;" autocomplete="off">
                <div id="custSuggest" class="autocomplete-list"></div>
            </div>
        </div>

        <div class="form-group">
            <label>CUST NAME</label>
            <input type="text" id="CUST_COMP" style="width:250px;" readonly>
        </div>

        <div class="form-group">
            <label>TAHUN</label>
            <select id="filterYear">
                <?php for ($y = $currentYear - 1; $y <= $currentYear + 2; $y++) {
                    $sel = ($y === $currentYear) ? 'selected' : '';
                    echo "<option value=\"$y\" $sel>$y</option>";
                } ?>
            </select>
        </div>

        <div class="form-group">
            <label>BULAN</label>
            <select id="filterMonth">
                <?php for ($m = 1; $m <= 12; $m++) {
                    $sel = ($m === $currentMonth) ? 'selected' : '';
                    $monthName = date("F", mktime(0, 0, 0, $m, 1));
                    echo "<option value=\"$m\" $sel>$monthName</option>";
                } ?>
            </select>
        </div>

        <div class="form-group">
            <label>CARI ITEM</label>
            <input type="text" id="ITEM_SEARCH" style="width:180px;" placeholder="Ketik part no/name...">
        </div>

        <div class="form-group">
            <button type="button" id="btnReloadData">RELOAD DATA</button>
        </div>

        <div class="status" id="LabelStatus">Siap digunakan.</div>
    </div>

    <!-- TABEL GABUNGAN (MASTER + MATRIX) -->
    <div class="table-container">
        <table class="item-table">
            <thead id="matrixHead">
                <tr><th style="padding:20px; text-align:left;">Pilih customer untuk memuat matrix...</th></tr>
            </thead>
            <tbody id="matrixBody">
            </tbody>
        </table>
    </div>
    
    <div class="info-text">
        <i>* Hapus angka (kosongkan) dengan Backspace/Delete. Tekan <b>ENTER / PANAH KANAN</b> untuk menyimpan otomatis dan pindah ke tanggal berikutnya. Gunakan <b>PANAH ATAS / BAWAH</b> untuk pindah item.</i>
    </div>
</div>

<script>
var custTimer = null;
var custRows = [];
var custIndex = -1;
var itemRowsAll = [];

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

// ================= CUSTOMER AUTOCOMPLETE =================
function hideCustSuggest() {
    var box = document.getElementById("custSuggest");
    if (box) { box.style.display = "none"; box.innerHTML = ""; }
    custIndex = -1;
}

function setActiveCust(index) {
    var box = document.getElementById("custSuggest");
    var items = box.getElementsByClassName("autocomplete-item");
    if (!items || items.length == 0) return;
    if (index < 0) index = items.length - 1;
    if (index >= items.length) index = 0;
    for (var i = 0; i < items.length; i++) items[i].className = "autocomplete-item";
    items[index].className = "autocomplete-item active";
    custIndex = index;
}

function chooseCust(index) {
    if (index < 0 || index >= custRows.length) return;
    var r = custRows[index];
    document.getElementById("CUST_ID").value = r.CUST_ID;
    document.getElementById("CUST_CODE").value = r.CUST_CODE;
    document.getElementById("CUST_COMP").value = r.CUST_COMP;
    hideCustSuggest();
    loadItemsAndSchedule(); // Load all immediately
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
    box.style.display = "block"; setActiveCust(0);
}

document.getElementById("CUST_CODE").onkeyup = function (e) {
    var key = e.keyCode || e.which;
    if (key == 13 && custIndex >= 0) { chooseCust(custIndex); return false; }
    if (key == 13 || key == 38 || key == 40) return false;
    clearTimeout(custTimer);
    custTimer = setTimeout(function () {
        var q = document.getElementById("CUST_CODE").value;
        if (q.length < 1) { hideCustSuggest(); return; }
        ajaxPost("ajax_schedule_customer.php", "q=" + enc(q), function (status, res) {
            if (status == 200) {
                try { var result = JSON.parse(res); if (result.success) showCustSuggest(result.rows); else hideCustSuggest(); } catch(e){}
            }
        });
    }, 250);
};

// ================= LOAD & RENDER GABUNGAN =================
function loadItemsAndSchedule() {
    var custId = document.getElementById("CUST_ID").value;
    if (custId == "") { alert("Pilih customer dulu."); return; }
    
    setStatus("Loading items...");
    
    // 1. Load Data Item
    ajaxPost("ajax_schedule_items.php", "CUST_ID=" + enc(custId), function (status, res) {
        if (status != 200) { setStatus("Load item gagal."); return; }
        try { 
            var result = JSON.parse(res); 
            if (result.success) {
                itemRowsAll = result.rows;
                renderCombinedTable(itemRowsAll);
                setStatus("Item loaded. Memuat Schedule Matrix...");
                
                // 2. Load schedule dengan sistem looping ke endpoint lama Mas Dian
                loadAllMatrixSchedulesLoop(itemRowsAll);
            }
        } catch (e) { setStatus("Error parsing items."); }
    });
}

function renderCombinedTable(rows) {
    var y = parseInt(document.getElementById("filterYear").value);
    var m = parseInt(document.getElementById("filterMonth").value);
    var daysInMonth = new Date(y, m, 0).getDate();

    // -- HEADER --
    var headHtml = '<tr>';
    headHtml += '<th style="min-width:80px;">PART_CODE</th>';
    headHtml += '<th style="min-width:85px;">PRCD</th>';
    headHtml += '<th style="min-width:120px;">PART_NO</th>';
    headHtml += '<th style="min-width:200px;">PART_NAME</th>';
    headHtml += '<th style="min-width:60px;">DELV</th>';
    headHtml += '<th style="min-width:60px;">BAL</th>';
    
    for (var d = 1; d <= daysInMonth; d++) {
        headHtml += '<th style="min-width:45px;">' + d + '</th>';
    }
    headHtml += '</tr>';
    document.getElementById("matrixHead").innerHTML = headHtml;

    // -- BODY --
    var bodyHtml = '';
    if (!rows || rows.length == 0) {
        bodyHtml = '<tr><td colspan="' + (6 + daysInMonth) + '" style="text-align:center;">Item tidak ditemukan.</td></tr>';
    } else {
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            bodyHtml += '<tr id="row_' + r.PRICE_ID + '" data-price-id="' + r.PRICE_ID + '">';
            bodyHtml += '<td>' + htmlEncode(r.ITEM_CODE) + '</td>';
            bodyHtml += '<td>' + htmlEncode(r.PRICE_CODE) + '</td>';
            bodyHtml += '<td>' + htmlEncode(r.ITEM_NO) + '</td>';
            bodyHtml += '<td>' + htmlEncode(r.ITEM_NAME) + '</td>';
            bodyHtml += '<td class="num">' + htmlEncode(r.DELIVERY_QTY) + '</td>';
            bodyHtml += '<td class="num">' + htmlEncode(r.BAL2) + '</td>';
            
            // Kolom Input Tanggal 1-31
            for(var d = 1; d <= daysInMonth; d++) {
                bodyHtml += '<td><input type="text" class="matrix-input" data-day="' + d + '" data-price-id="' + r.PRICE_ID + '" id="in_' + r.PRICE_ID + '_' + d + '"></td>';
            }
            bodyHtml += '</tr>';
        }
    }
    document.getElementById("matrixBody").innerHTML = bodyHtml;

    attachNavigationEvents();
}

function loadAllMatrixSchedulesLoop(rows) {
    var currentY = parseInt(document.getElementById("filterYear").value);
    var currentM = parseInt(document.getElementById("filterMonth").value);
    
    // Looping menggunakan endpoint lama
    for (var i = 0; i < rows.length; i++) {
        (function(priceId) {
            ajaxPost("ajax_schedule_load.php", "PRICE_ID=" + enc(priceId), function(status, res) {
                if (status == 200) {
                    try {
                        var result = JSON.parse(res);
                        if (result.success && result.rows) {
                            for(var j = 0; j < result.rows.length; j++) {
                                var row = result.rows[j];
                                var dStr = row.DELS_DATE; 
                                if (!dStr) continue;

                                var ry = 0, rm = 0, rd = 0;
                                // Hapus jam/waktu jika SQL mengirim format date time
                                var dateOnly = dStr.split(" ")[0]; 

                                // Deteksi format tanggal pintar (YYYY-MM-DD atau MM/DD/YYYY)
                                if (dateOnly.indexOf("-") > -1) { 
                                    var parts = dateOnly.split("-");
                                    ry = parseInt(parts[0], 10);
                                    rm = parseInt(parts[1], 10);
                                    rd = parseInt(parts[2], 10);
                                } else if (dateOnly.indexOf("/") > -1) { 
                                    var parts = dateOnly.split("/");
                                    rm = parseInt(parts[0], 10);
                                    rd = parseInt(parts[1], 10);
                                    ry = parseInt(parts[2], 10);
                                }

                                if (ry === currentY && rm === currentM && rd >= 1 && rd <= 31) {
                                    var input = document.getElementById("in_" + priceId + "_" + rd);
                                    if (input) {
                                        var qty = parseInt(row.DELS_QTY, 10);
                                        input.value = (qty > 0) ? qty : "";
                                    }
                                }
                            }
                        }
                    } catch(e) {}
                }
            });
        })(rows[i].PRICE_ID);
    }
    setStatus("Data schedule berhasil dimuat.");
}

// ================= SIMPAN & NAVIGASI KEYBOARD =================
function attachNavigationEvents() {
    var inputs = document.getElementsByClassName("matrix-input");
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].onkeydown = function(e) {
            var key = e.keyCode || e.which;
            var priceId = this.getAttribute("data-price-id");
            var currentDay = parseInt(this.getAttribute("data-day"));
            var tr = document.getElementById("row_" + priceId);

            // ENTER, KANAN, TAB: Save & Pindah ke Hari berikutnya
            if (key == 13 || key == 39 || key == 9) {
                e.preventDefault();
                saveScheduleSilent(priceId, function() {
                    var next = document.getElementById("in_" + priceId + "_" + (currentDay + 1));
                    if (next) { next.focus(); next.select(); }
                });
                return false;
            }

            // KIRI: Pindah ke Hari sebelumnya
            if (key == 37) { 
                e.preventDefault();
                var prev = document.getElementById("in_" + priceId + "_" + (currentDay - 1));
                if (prev) { prev.focus(); prev.select(); }
                return false;
            }

            // ATAS: Pindah ke Item sebelumnya (Hari sama)
            if (key == 38) {
                e.preventDefault();
                var prevTr = tr.previousElementSibling;
                if (prevTr && prevTr.id) {
                    var upPriceId = prevTr.getAttribute("data-price-id");
                    var upInput = document.getElementById("in_" + upPriceId + "_" + currentDay);
                    if (upInput) { upInput.focus(); upInput.select(); }
                }
                return false;
            }

            // BAWAH: Pindah ke Item sesudahnya (Hari sama)
            if (key == 40) {
                e.preventDefault();
                var nextTr = tr.nextElementSibling;
                if (nextTr && nextTr.id) {
                    var downPriceId = nextTr.getAttribute("data-price-id");
                    var downInput = document.getElementById("in_" + downPriceId + "_" + currentDay);
                    if (downInput) { downInput.focus(); downInput.select(); }
                }
                return false;
            }
        };
        
        // Simpan ketika user klik di luar kotak (blur) lalu merubah isi
        inputs[i].onchange = function() {
            saveScheduleSilent(this.getAttribute("data-price-id"));
        };
    }
}

// Fitur simpan khusus 1 baris item 
function saveScheduleSilent(priceId, callback) {
    var y = parseInt(document.getElementById("filterYear").value);
    var m = parseInt(document.getElementById("filterMonth").value);
    var mStr = (m < 10 ? "0" + m : "" + m);
    var daysInMonth = new Date(y, m, 0).getDate();

    var scheduleData = [];
    for (var i = 1; i <= daysInMonth; i++) {
        var input = document.getElementById("in_" + priceId + "_" + i);
        if (input) {
            var valStr = input.value.trim();
            var val = parseInt(valStr, 10);
            if (valStr === "" || isNaN(val) || val < 0) val = 0;

            var dStr = (i < 10 ? "0" + i : "" + i);
            var fullDate = y + "-" + mStr + "-" + dStr;

            scheduleData.push({
                DELS_DATE: fullDate,
                DELS_QTY: val,
                DELS_C1: 0,
                DELS_C2: 0
            });
        }
    }

    ajaxPost(
        "ajax_schedule_save.php",
        "PRICE_ID=" + enc(priceId) + "&ROWS_JSON=" + enc(JSON.stringify(scheduleData)),
        function (status, responseText) {
            if (status == 200) {
                try {
                    var result = JSON.parse(responseText);
                    if (result.success) setStatus("Tersimpan otomatis.");
                    else setStatus("Gagal simpan: " + result.message);
                } catch (e) {}
            }
            if (callback) callback();
        }
    );
}

// ================= LISTENER UTAMA =================
document.getElementById("filterYear").onchange = function() { if (document.getElementById("CUST_ID").value !== "") loadItemsAndSchedule(); };
document.getElementById("filterMonth").onchange = function() { if (document.getElementById("CUST_ID").value !== "") loadItemsAndSchedule(); };
document.getElementById("btnReloadData").onclick = function () { if (document.getElementById("CUST_ID").value !== "") loadItemsAndSchedule(); };

// Filter lokal table item
document.getElementById("ITEM_SEARCH").onkeyup = function (e) {
    var q = this.value.toLowerCase();
    var tbody = document.getElementById("matrixBody");
    var trs = tbody.getElementsByTagName("tr");
    for (var i = 0; i < trs.length; i++) {
        var text = trs[i].innerText.toLowerCase();
        trs[i].style.display = (text.indexOf(q) > -1) ? "" : "none";
    }
};

document.getElementById("CUST_CODE").focus();
</script>
</body>
</html>