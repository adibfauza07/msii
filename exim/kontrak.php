<?php
require_once __DIR__ . '/../config/database_p1.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kontrak Subcont</title>

    <style>
        body {
            margin: 0;
            background: #d3d0c8;
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
        }

        .form-box {
            width: 920px;
            margin: 20px auto;
            background: #d3d0c8;
            border: 1px solid #666;
            padding: 10px;
            box-sizing: border-box;
        }

        .title {
            font-weight: bold;
            margin-bottom: 10px;
        }

        .row {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }

        .row label {
            width: 85px;
            font-weight: bold;
        }

        input[type="text"] {
            height: 20px;
            border: 1px solid #333;
            padding: 2px 5px;
            font-size: 12px;
            box-sizing: border-box;
        }

        .search-input {
            width: 235px;
            background: #fff;
        }

        .status {
            min-height: 18px;
            color: navy;
            margin: 4px 0;
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
            height: 20px;
            font-size: 12px;
            box-sizing: border-box;
            overflow: visible;
            white-space: nowrap;
        }

        th {
            background: #d9d6ce;
            font-weight: normal;
            text-align: left;
        }

        .grid-wrap {
            border: 1px solid #777;
            background: #fff;
            overflow: auto;
            margin-bottom: 8px;
        }

        .master-wrap {
            height: 95px;
        }

        .detail-wrap {
            height: 230px;
        }
		


        .selected {
            background: #2f70c9;
            color: #fff;
        }

        .col-po { width: 140px; }
        .col-date { width: 90px; }
        .col-kontrak { width: 180px; }
        .col-cur { width: 55px; }
        .col-close { width: 70px; }
        .col-sup { width: 290px; }

        .col-code { width: 105px; }
        .col-name { width: 250px; }
        .col-qty { width: 90px; text-align: right; }
        .col-price { width: 90px; text-align: right; }
        .col-unit { width: 65px; }
        .col-req { width: 85px; }

        .num {
            text-align: right;
        }

        .sub-box {
            margin: 4px 0 4px 305px;
            width: 450px;
            border: 1px solid #777;
            background: #d3d0c8;
            padding: 4px;
            overflow: visible;
        }

        .sub-box table {
            margin-bottom: 6px;
            overflow: visible;
        }

        .sub-code { width: 110px; }
        .sub-qty { width: 80px; text-align: right; }
        .sub-bc { width: 120px; }
        .sub-bc2 { width: 120px; }

        .sub-button {
            height: 24px;
            font-size: 12px;
            margin-left: 4px;
        }

        .autocomplete-wrap {
            position: relative;
            display: inline-block;
        }

        .autocomplete-list {
            position: absolute;
            top: 22px;
            left: 0;
            width: 580px;
            max-height: 230px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #555;
            z-index: 9999;
            display: none;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.25);
        }

        .autocomplete-item {
            padding: 5px 7px;
            cursor: pointer;
            border-bottom: 1px solid #ddd;
            line-height: 16px;
        }

        .autocomplete-item:hover {
            background: #2f70c9;
            color: #fff;
        }

        .small {
            font-size: 11px;
            color: #555;
        }

        .autocomplete-item:hover .small {
            color: #fff;
        }

        .mat-input {
            width: 100%;
            border: none;
            height: 18px;
            font-size: 12px;
            box-sizing: border-box;
            background: #fff;
        }

        .mat-wrap {
            position: relative;
            overflow: visible;
        }

        .mat-list {
            position: absolute;
            top: 20px;
            left: 0;
            width: 400px;
            max-height: 160px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #555;
            z-index: 99999;
            display: none;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.25);
        }

        .mat-item {
            padding: 4px 6px;
            cursor: pointer;
            border-bottom: 1px solid #ddd;
            background: #fff;
            color: #000;
            line-height: 16px;
        }

        .mat-item:hover {
            background: #2f70c9;
            color: #fff;
        }
    </style>
</head>

<body>

<div class="form-box">
    <div class="title">Kontrak Subcont</div>

    <div class="row">
        <label>PO NOMOR</label>

        <div class="autocomplete-wrap">
            <input type="text" id="SEARCH_PO" class="search-input" autocomplete="off">
            <div id="poSuggest" class="autocomplete-list"></div>
        </div>
    </div>

    <input type="hidden" id="PO_ID" value="">

    <div class="status" id="LabelStatus"></div>

    <div class="grid-wrap master-wrap">
        <table>
            <thead>
                <tr>
                    <th class="col-po">PO NOMOR</th>
                    <th class="col-date">PO_DATE</th>
                    <th class="col-kontrak">KONTRAK NUM</th>
                    <th class="col-cur">PO_CUR</th>
                    <th class="col-close">PO_CLOSE</th>
                    <th class="col-sup">SUP_COMP</th>
                </tr>
            </thead>

            <tbody id="masterBody">
                <tr>
                    <td colspan="6">Ketik PO NOMOR untuk mencari data.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="grid-wrap detail-wrap">
        <table>
            <thead>
                <tr>
                    <th class="col-code">CODE</th>
                    <th class="col-name">NAME</th>
                    <th class="col-qty">POD_QTY</th>
                    <th class="col-price">POD_PRICE</th>
                    <th class="col-unit">POD_UNIT</th>
                    <th class="col-req">REQ_ID</th>
                </tr>
            </thead>

            <tbody id="detailBody">
                <tr>
                    <td colspan="6">Belum ada detail.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
var poTimer = null;
var matTimer = null;
var currentPoId = "";

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

function hideSuggest() {
    var box = document.getElementById("poSuggest");
    box.style.display = "none";
    box.innerHTML = "";
}

function clearDetail() {
    document.getElementById("detailBody").innerHTML =
        '<tr><td colspan="6">Belum ada detail.</td></tr>';
}

function selectMasterRow(row) {
    var rows = document.getElementById("masterBody").getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        rows[i].className = "";
    }

    row.className = "selected";
}

function selectDetailRow(row) {
    var rows = document.getElementById("detailBody").getElementsByTagName("tr");

    for (var i = 0; i < rows.length; i++) {
        if (rows[i].getAttribute("data-detail") == "1") {
            rows[i].className = "";
        }
    }

    row.className = "selected";
}

function searchPoAutocomplete() {
    var q = document.getElementById("SEARCH_PO").value;

    if (q.length < 1) {
        hideSuggest();
        return;
    }

    ajaxPost("ajax_search_po_subcont.php", "q=" + enc(q), function(status, responseText) {
        if (status != 200) {
            hideSuggest();
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch(e) {
            hideSuggest();
            return;
        }

        if (!result.success) {
            hideSuggest();
            return;
        }

        showSuggest(result.rows);
    });
}

function showSuggest(rows) {
    var box = document.getElementById("poSuggest");
    box.innerHTML = "";

    if (!rows || rows.length == 0) {
        hideSuggest();
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        (function(r) {
            var div = document.createElement("div");
            div.className = "autocomplete-item";

            div.innerHTML =
                '<div><b>' + htmlEncode(r.PO_NUM) + '</b></div>' +
                '<div class="small">' +
                    htmlEncode(r.PO_DATE) + ' | ' +
                    htmlEncode(r.PO_CUR) + ' | ' +
                    htmlEncode(r.SUP_COMP) +
                '</div>';

            div.onclick = function () {
                document.getElementById("SEARCH_PO").value = r.PO_NUM;
                hideSuggest();
                loadMasterByPo(r.PO_NUM);
            };

            box.appendChild(div);
        })(rows[i]);
    }

    box.style.display = "block";
}

function loadMasterByPo(poNum) {
    if (poNum == "") {
        return;
    }

    setStatus("Mengambil data PO...");
    clearDetail();

    ajaxPost("ajax_get_kontrak_subcont.php", "po_num=" + enc(poNum), function(status, responseText) {
        if (status != 200) {
            alert("HTTP Error: " + status);
            setStatus("Ambil data gagal.");
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch(e) {
            alert("Response bukan JSON:\n\n" + responseText);
            setStatus("Ambil data gagal.");
            return;
        }

        if (!result.success) {
            alert(result.message);
            document.getElementById("masterBody").innerHTML =
                '<tr><td colspan="6">Data tidak ditemukan.</td></tr>';
            clearDetail();
            setStatus("Data tidak ditemukan.");
            return;
        }

        fillMaster(result.rows);
        setStatus("Data ditemukan.");
    });
}

function fillMaster(rows) {
    var tbody = document.getElementById("masterBody");
    tbody.innerHTML = "";

    if (!rows || rows.length == 0) {
        tbody.innerHTML = '<tr><td colspan="6">Data tidak ditemukan.</td></tr>';
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        (function(r) {
            var tr = document.createElement("tr");

            tr.innerHTML =
                '<td class="col-po">' + htmlEncode(r.PO_NUM) + '</td>' +
                '<td class="col-date">' + htmlEncode(r.PO_DATE) + '</td>' +
                '<td class="col-kontrak">' + htmlEncode(r.PO_TO) + '</td>' +
                '<td class="col-cur">' + htmlEncode(r.PO_CUR) + '</td>' +
                '<td class="col-close">' + htmlEncode(r.PO_CLOSE) + '</td>' +
                '<td class="col-sup">' + htmlEncode(r.SUP_COMP) + '</td>';

            tr.onclick = function() {
                selectMasterRow(this);
                currentPoId = r.PO_ID;
                document.getElementById("PO_ID").value = r.PO_ID;
                loadDetail(r.PO_ID);
            };

            tbody.appendChild(tr);
        })(rows[i]);
    }

    currentPoId = rows[0].PO_ID;
    document.getElementById("PO_ID").value = rows[0].PO_ID;
    tbody.getElementsByTagName("tr")[0].className = "selected";
    loadDetail(rows[0].PO_ID);
}

function loadDetail(poId) {
    if (poId == "") {
        clearDetail();
        return;
    }

    setStatus("Mengambil detail...");

    ajaxPost("ajax_get_kontrak_subcont_detail.php", "po_id=" + enc(poId), function(status, responseText) {
        if (status != 200) {
            alert("HTTP Error: " + status);
            setStatus("Ambil detail gagal.");
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch(e) {
            alert("Response bukan JSON:\n\n" + responseText);
            setStatus("Ambil detail gagal.");
            return;
        }

        if (!result.success) {
            alert(result.message);
            clearDetail();
            setStatus("Ambil detail gagal.");
            return;
        }

        fillDetail(result.rows);
        setStatus("Detail ditemukan: " + result.rows.length + " baris.");
    });
}

function fillDetail(rows) {
    var tbody = document.getElementById("detailBody");
    tbody.innerHTML = "";

    if (!rows || rows.length == 0) {
        tbody.innerHTML = '<tr><td colspan="6">Detail kosong.</td></tr>';
        return;
    }

    for (var i = 0; i < rows.length; i++) {
        (function(r) {
            var tr = document.createElement("tr");
            tr.setAttribute("data-detail", "1");

            tr.innerHTML =
                '<td class="col-code">' + htmlEncode(r.ITEM_CODE) + '</td>' +
                '<td class="col-name">' + htmlEncode(r.ITEM_NAME) + '</td>' +
                '<td class="col-qty num">' + htmlEncode(r.POD_QTY) + '</td>' +
                '<td class="col-price num">' + htmlEncode(r.POD_PRICE) + '</td>' +
                '<td class="col-unit">' + htmlEncode(r.POD_UNIT) + '</td>' +
                '<td class="col-req">' + htmlEncode(r.REQ_ID) + '</td>';

            tr.onclick = function() {
                selectDetailRow(this);
            };

            tbody.appendChild(tr);

            var subTr = document.createElement("tr");
            subTr.setAttribute("data-sub", "1");

            var html = '';
            html += '<td colspan="6">';
            html += '<div class="sub-box">';
            html += '<table>';
            html += '<tr>';
            html += '<th class="sub-code">MAT_CODE</th>';
            html += '<th class="sub-qty">MAT_QTY</th>';
            html += '<th class="sub-bc">NOMOR_BC_IN</th>';
            html += '<th class="sub-bc2">NOMOR BC 2.6.1</th>';
            html += '</tr>';

            if (r.subdetail && r.subdetail.length > 0) {
                for (var x = 0; x < r.subdetail.length; x++) {
                    var s = r.subdetail[x];

                    html += '<tr>';
                    html += '<td>';
                    html += '<div class="mat-wrap">';
                    html += '<input type="text" class="mat-input mat-code" value="' + htmlEncode(s.MAT_CODE) + '" data-po-id="' + htmlEncode(r.PO_ID) + '" data-item-id="' + htmlEncode(r.ITEM_ID) + '" autocomplete="off">';
                    html += '<input type="hidden" class="mat-id" value="' + htmlEncode(s.MAT_ID) + '">';
                    html += '<div class="mat-list"></div>';
                    html += '</div>';
                    html += '</td>';
                    html += '<td><input type="text" class="mat-input mat-qty num" value="' + htmlEncode(s.MAT_QTY) + '"></td>';
                    html += '<td><input type="text" class="mat-input mat-bc" value="' + htmlEncode(s.NOMOR_BC) + '"></td>';
                    html += '<td><input type="text" class="mat-input mat-bc-mat" value="' + htmlEncode(s.NOMOR_BC_MAT) + '"></td>';
                    html += '</tr>';
                }
            } else {
                html += '<tr>';
                html += '<td>';
                html += '<div class="mat-wrap">';
                html += '<input type="text" class="mat-input mat-code" data-po-id="' + htmlEncode(r.PO_ID) + '" data-item-id="' + htmlEncode(r.ITEM_ID) + '" autocomplete="off">';
                html += '<input type="hidden" class="mat-id">';
                html += '<div class="mat-list"></div>';
                html += '</div>';
                html += '</td>';
                html += '<td><input type="text" class="mat-input mat-qty"></td>';
                html += '<td><input type="text" class="mat-input mat-bc"></td>';
                html += '<td><input type="text" class="mat-input mat-bc-mat"></td>';
                html += '</tr>';
            }

            html += '</table>';
           html += '<button type="button" class="sub-button" onclick="addSubRow(this)">+ TAMBAH BARIS</button>';
html += '<button type="button" class="sub-button">UPDATE BAL BC</button>';
html += '<button type="button" class="sub-button">UPDATE BAL 2.6.2</button>';
            html += '</div>';
            html += '</td>';

            subTr.innerHTML = html;
            tbody.appendChild(subTr);
        })(rows[i]);
    }

    bindMaterialAutocomplete();
}

function bindMaterialAutocomplete() {
    var inputs = document.getElementsByClassName("mat-code");

    for (var i = 0; i < inputs.length; i++) {
        inputs[i].onkeyup = function () {
            var input = this;

            clearTimeout(matTimer);

            matTimer = setTimeout(function () {
                searchMaterial(input);
            }, 250);
        };

        inputs[i].onfocus = function () {
            searchMaterial(this);
        };

        inputs[i].onblur = function () {
            var input = this;

            setTimeout(function () {
                hideMaterialList(input);
            }, 300);
        };
    }
}

function hideMaterialList(input) {
    var wrap = input.parentNode;
    var list = wrap.getElementsByClassName("mat-list")[0];

    if (list) {
        list.style.display = "none";
        list.innerHTML = "";
    }
}

function searchMaterial(input) {
    var q = input.value;
    var wrap = input.parentNode;
    var list = wrap.getElementsByClassName("mat-list")[0];

    if (!list) {
        return;
    }

    if (q.length < 1) {
        list.style.display = "none";
        list.innerHTML = "";
        return;
    }

    ajaxPost("ajax_search_material_subcont.php", "q=" + enc(q), function(status, responseText) {
        if (status != 200) {
            return;
        }

        var result;

        try {
            result = JSON.parse(responseText);
        } catch(e) {
            alert("Response material bukan JSON:\n\n" + responseText);
            return;
        }

        if (!result.success) {
            alert(result.message);
            return;
        }

        if (!result.rows || result.rows.length == 0) {
            list.style.display = "none";
            list.innerHTML = "";
            return;
        }

        list.innerHTML = "";

        for (var i = 0; i < result.rows.length; i++) {
            (function(r) {
                var div = document.createElement("div");
                div.className = "mat-item";

                div.innerHTML =
                    '<b>' + htmlEncode(r.ITEM_CODE) + '</b><br>' +
                    htmlEncode(r.ITEM_NAME);

                div.onmousedown = function () {
                    input.value = r.ITEM_CODE;

                    var hidden = wrap.getElementsByClassName("mat-id")[0];
                    if (hidden) {
                        hidden.value = r.ITEM_ID;
                    }

                    list.style.display = "none";
                    list.innerHTML = "";
                };

                list.appendChild(div);
            })(result.rows[i]);
        }

        list.style.display = "block";
    });
}

function addSubRow(btn) {
    var subBox = btn.parentNode;
    var table = subBox.getElementsByTagName("table")[0];

    var tr = document.createElement("tr");

    tr.innerHTML =
        '<td>' +
            '<div class="mat-wrap">' +
                '<input type="text" class="mat-input mat-code" autocomplete="off">' +
                '<input type="hidden" class="mat-id">' +
                '<div class="mat-list"></div>' +
            '</div>' +
        '</td>' +

        '<td>' +
            '<input type="text" class="mat-input mat-qty num">' +
        '</td>' +

        '<td>' +
            '<div class="bc-wrap">' +
                '<input type="text" class="mat-input mat-bc" autocomplete="off">' +
                '<div class="bc-list"></div>' +
            '</div>' +
        '</td>' +

        '<td>' +
            '<input type="text" class="mat-input mat-bc-mat">' +
        '</td>';

    table.appendChild(tr);

    bindMaterialAutocomplete();

    if (typeof bindBcAutocomplete == "function") {
        bindBcAutocomplete();
    }

    var input = tr.getElementsByClassName("mat-code")[0];

    if (input) {
        input.focus();
    }
}

document.getElementById("SEARCH_PO").oninput = function () {
    clearTimeout(poTimer);

    poTimer = setTimeout(function () {
        searchPoAutocomplete();
    }, 300);
};

document.getElementById("SEARCH_PO").onkeyup = function (e) {
    e = e || window.event;

    if (e.keyCode == 13) {
        hideSuggest();
        loadMasterByPo(document.getElementById("SEARCH_PO").value);
        return false;
    }
};

document.getElementById("SEARCH_PO").onblur = function () {
    setTimeout(function () {
        hideSuggest();
    }, 300);

};
</script>

</body>
</html>