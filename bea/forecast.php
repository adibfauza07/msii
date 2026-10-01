<?php
if (session_id() == "") {
    session_start();
}

// Konfigurasi Database disamakan ke /config/database.php
require_once __DIR__ . '/config/database.php';

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$today = date("Y-m-d");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordering System - Dashboard</title>

    <style>
        /* CSS Khusus untuk memastikan Layout selaras dengan AdminLTE & Bootstrap */
        .box { background: #ffffff; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; border-top: 3px solid #d2d6de; }
        .box.box-primary { border-top-color: #3c8dbc; }
        .box.box-success { border-top-color: #00a65a; }
        .box.box-warning { border-top-color: #f39c12; }
        .box-header { padding: 10px 15px; border-bottom: 1px solid #f4f4f4; }
        .box-title { font-size: 16px; margin: 0; font-weight: bold; display: inline-block; }
        .box-body { padding: 15px; }
        
        .form-control { width: 100%; border-radius: 4px; border: 1px solid #ccc; height: 30px; padding: 4px 10px; font-size: 13px; box-shadow: none; transition: border-color 0.15s ease-in-out; }
        .form-control:focus { border-color: #3c8dbc; outline: 0; }
        .form-control[readonly] { background-color: #e6e6e6; cursor: not-allowed; }
        
        .btn { border-radius: 4px; font-size: 12px; font-weight: bold; padding: 5px 12px; cursor: pointer; border: 1px solid transparent; transition: background-color 0.2s; display: inline-block; text-decoration: none; }
        .btn-success { background-color: #00a65a; color: #fff; border-color: #008d4c; }
        .btn-success:hover { background-color: #008d4c; }
        .btn-primary { background-color: #3c8dbc; color: #fff; border-color: #367fa9; }
        .btn-primary:hover { background-color: #286090; }
        .btn-default { background-color: #f4f4f4; color: #444; border-color: #ddd; }
        .btn-danger { background-color: #dd4b39; color: #fff; border-color: #d73925; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { font-weight: 600; margin-bottom: 5px; display: inline-block; font-size: 12px; color: #333; }
        
        /* Styling Tabs */
        .nav-tabs-custom { margin-bottom: 20px; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,0.1); border-radius: 3px; }
        .nav-tabs { border-bottom-color: #f4f4f4; margin: 0; padding: 0; list-style: none; display: flex; background: #fff;}
        .nav-tabs > li { margin-bottom: -1px; }
        .nav-tabs > li > a { color: #444; border-radius: 0; padding: 12px 20px; display: block; text-decoration: none; border-top: 3px solid transparent; border-right: 1px solid #f4f4f4; font-weight: bold; font-size: 14px;}
        .nav-tabs > li > a:hover { color: #222; background: #f4f4f4; }
        .nav-tabs > li.active > a { border-top-color: #3c8dbc; border-bottom: none; background-color: #ecf0f5; color: #3c8dbc; }
        
        .tab-content { background: #ecf0f5; padding: 15px; }
        .tab-pane { display: none; animation: fadeIn 0.3s ease-in-out; }
        .tab-pane.active { display: block; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Styling Grid Tabel Asli dari User */
        .layout-grid { display: grid; grid-template-columns: 1.3fr 1fr; gap: 15px; }
        .grid-table { width: 100%; border-collapse: collapse; background: #fff; table-layout: fixed; }
        .grid-table th, .grid-table td { border: 1px solid #888; padding: 4px; height: 25px; font-size: 12px; white-space: nowrap; overflow: hidden; }
        .grid-table th { background: #d9d6ce; font-weight: bold; text-align: left; }
        .grid-table tbody tr { cursor: pointer; }
        .grid-table tr:focus { outline: 2px solid #0033ff; }
        .grid-table input { width: 100%; height: 21px; border: 1px solid #999; box-sizing: border-box; font-size: 12px; padding: 0 4px;}
        .grid-table input[readonly] { background: #e6e6e6; }
        
        .num { text-align: right; }
        .selected { background: red; color: #fff; }
        .selected input { color: #000; }
        
        .O_selected { background: #2f70c9; color: #ffffff; }
        .O_selected input { color: #000; }

        .status { color: navy; font-weight: bold; min-height: 18px; margin: 8px 0; }
        .small-info { font-size: 11px; color: navy; font-weight: bold; margin-bottom: 5px; }
        .toolbar { margin-bottom: 10px; }

        /* Autocomplete List */
        .autocomplete-wrap { position: relative; display: block; }
        .autocomplete-list {
            position: absolute; top: 100%; left: 0; width: 100%; max-height: 230px;
            overflow-y: auto; background: #fff; border: 1px solid #444;
            z-index: 9999; display: none; box-shadow: 2px 2px 5px rgba(0,0,0,0.25);
        }
        .autocomplete-item { padding: 5px 7px; border-bottom: 1px solid #ddd; cursor: pointer; line-height: 16px; color: #333; font-size: 12px;}
        .autocomplete-item:hover, .autocomplete-item.active { background: #2f70c9; color: #fff; }
        .autocomplete-main { font-weight: bold; }
        .autocomplete-sub { color: #666; font-size: 11px; }
        .autocomplete-item:hover .autocomplete-sub, .autocomplete-item.active .autocomplete-sub { color: #e0e0e0; }

        /* Modal Order Delivery */
        .modal-bg { display: none; position: absolute; z-index: 999999; left: 0; top: 0; background: transparent; }
        .modal-box { width: 780px; max-height: 420px; overflow: auto; background: #ecf0f5; border: 2px solid #333; padding: 10px; box-shadow: 3px 3px 8px rgba(0,0,0,0.4); }
        .modal-title { font-weight: bold; font-size: 16px; margin-bottom: 8px; }
        .btn-list-delivery { width: 100%; height: 21px; font-size: 11px; padding: 0; cursor: pointer; }
    </style>
</head>
<body style="padding: 15px;">

<div class="row">
    <div class="col-md-12">
        
        <!-- WRAPPER TAB -->
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li class="tab-li active"><a href="#" onclick="openTab(event, 'forecast')"><i class="fa fa-line-chart"></i> FORECAST</a></li>
                <li class="tab-li"><a href="#" onclick="openTab(event, 'order')"><i class="fa fa-shopping-cart"></i> ORDER</a></li>
                <li class="tab-li"><a href="#" onclick="openTab(event, 'schedule')"><i class="fa fa-calendar"></i> SCHEDULE</a></li>
            </ul>
            
            <div class="tab-content">

                <!-- ==========================================
                     TAB 1: FORECAST
                =========================================== -->
                <div id="forecast" class="tab-pane active">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">Laporan Forecast Plant 2</h3>
                        </div>
                        <div class="box-body">
                            
                            <input type="hidden" id="F_CUST_ID" value="">
                            <input type="hidden" id="F_PRICE_ID" value="">

                            <div class="layout-grid">
                                <!-- PANEL KIRI -->
                                <div class="left-panel">
                                    <div class="row">
                                        <div class="col-md-3 form-group">
                                            <label>CUST CODE</label>
                                            <div class="autocomplete-wrap">
                                                <input type="text" id="F_CUST_CODE" class="form-control" autocomplete="off">
                                                <div id="F_custSuggest" class="autocomplete-list"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label>CUST NAME</label>
                                            <input type="text" id="F_CUST_COMP" class="form-control" readonly>
                                        </div>
                                        <div class="col-md-3 form-group" style="padding-top: 23px;">
                                            <button type="button" class="btn btn-default btn-block" id="F_btnRefreshItem">REFRESH ITEM</button>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12 form-group">
                                            <label>CARI ITEM CODE / NAME</label>
                                            <div class="autocomplete-wrap">
                                                <input type="text" id="F_ITEM_SEARCH" class="form-control" autocomplete="off" placeholder="Ketik part code / part name / part no">
                                                <div id="F_itemSearchSuggest" class="autocomplete-list" style="width: 100%;"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="status" id="F_LabelStatus">Ready.</div>

                                    <div style="max-height: 250px; overflow-y: auto;">
                                        <table class="grid-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:110px;">PART_CODE</th>
                                                    <th style="width:120px;">PRCD</th>
                                                    <th style="width:120px;">PART_NO</th>
                                                    <th>PART_NAME</th>
                                                </tr>
                                            </thead>
                                            <tbody id="F_itemBody">
                                                <tr><td colspan="4">Pilih customer dulu.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- PANEL KANAN -->
                                <div class="right-panel">
                                    <div class="small-info" id="F_selectedPartInfo">Belum pilih part.</div>
                                    
                                    <div class="toolbar">
                                        <button type="button" class="btn btn-default" id="F_btnAddMonth">+</button>
                                        <button type="button" class="btn btn-default" id="F_btnDeleteMonth">-</button>
                                        <button type="button" class="btn btn-success" id="F_btnSaveForecast">SIMPAN</button>
                                        <button type="button" class="btn btn-default" id="F_btnReloadForecast">REFRESH</button>
                                    </div>

                                    <div style="max-height: 330px; overflow-y: auto;">
                                        <table class="grid-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:130px;">MONTH</th>
                                                    <th style="text-align: right;">FORE_QTY</th>
                                                </tr>
                                            </thead>
                                            <tbody id="F_forecastBody">
                                                <tr><td colspan="2">Belum ada data.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ==========================================
                     TAB 2: ORDER
                =========================================== -->
                <div id="order" class="tab-pane">
                    <div class="box box-success">
                        <div class="box-header with-border">
                            <h3 class="box-title">Input Order Plant 2</h3>
                            <div class="box-tools pull-right">
                                <button type="button" class="btn btn-primary btn-sm" id="O_btnNew">NEW</button>
                                <button type="button" class="btn btn-success btn-sm" id="O_btnSave">SIMPAN ORDER</button>
                                <button type="button" class="btn btn-danger btn-sm" id="O_btnDeleteHeader">HAPUS HEADER</button>
                                <button type="button" class="btn btn-default btn-sm" id="O_btnCancel">BATAL</button>
                            </div>
                        </div>
                        <div class="box-body">
                            
                            <input type="hidden" id="O_ORDR_ID" value="">
                            <input type="hidden" id="O_CUST_ID" value="">

                            <div class="row">
                                <div class="col-md-2 form-group">
                                    <label>C.CODE</label>
                                    <div class="autocomplete-wrap">
                                        <input type="text" id="O_CUST_CODE" class="form-control" autocomplete="off">
                                        <div id="O_custSuggest" class="autocomplete-list"></div>
                                    </div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>COMPANY</label>
                                    <input type="text" id="O_CUST_COMP" class="form-control" readonly>
                                </div>
                                <div class="col-md-1 form-group">
                                    <label>CURR</label>
                                    <input type="text" id="O_ORDR_CURR" class="form-control" readonly>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>ORDER PO/CARI</label>
                                    <div class="autocomplete-wrap">
                                        <input type="text" id="O_ORDR_PO" class="form-control" autocomplete="off">
                                        <div id="O_poSuggest" class="autocomplete-list"></div>
                                    </div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>DATE</label>
                                    <input type="date" id="O_ORDR_DATE" class="form-control" value="<?php echo h($today); ?>">
                                </div>
                            </div>

                            <div class="row" style="align-items: center; display: flex;">
                                <div class="col-md-4 form-group">
                                    <input type="text" id="O_ORDR_REM" class="form-control" placeholder="Remark">
                                </div>
                                <div class="col-md-8 form-group" style="padding-top: 5px;">
                                    <label style="margin-right: 15px;"><input type="checkbox" id="O_ORDR_REPLACEMENT"> Replacement</label>
                                    <label><input type="checkbox" id="O_ORDR_CLOSE"> Closed</label>
                                </div>
                            </div>

                            <div class="toolbar" style="margin-top: 10px;">
                                <button type="button" class="btn btn-default btn-sm" id="O_btnAddRow">TAMBAH BARIS</button>
                                <button type="button" class="btn btn-danger btn-sm" id="O_btnDeleteDetail">HAPUS DETAIL</button>
                            </div>
                            
                            <div class="status" id="O_LabelStatus">Ready.</div>

                            <div style="overflow-x: auto;">
                                <table class="grid-table">
                                    <thead>
                                        <tr>
                                            <th style="width:35px; text-align:center;">#</th>
                                            <th style="width:110px;">Part Code</th>
                                            <th style="width:250px;">Part Name</th>
                                            <th style="width:90px; text-align:right;">Price</th>
                                            <th style="width:80px; text-align:right;">Qty</th>
                                            <th style="width:80px; text-align:right;">Bal.</th>
                                            <th style="width:80px; text-align:right;">Delv.</th>
                                            <th style="width:110px;">Remark</th>
                                            <th style="width:100px; text-align:right;">Amount</th>
                                            <th style="width:60px; text-align:center;">Closed</th>
                                        </tr>
                                    </thead>
                                    <tbody id="O_orderBody"></tbody>
                                </table>
                            </div>
                            
                            

                        </div>
                    </div>
                </div>

                <!-- ==========================================
                     TAB 3: SCHEDULE
                =========================================== -->
                <div id="schedule" class="tab-pane">
                    <div class="box box-warning">
                        <div class="box-header with-border">
                            <h3 class="box-title">Delivery Schedule Plant 2</h3>
                        </div>
                        <div class="box-body">

                            <input type="hidden" id="S_CUST_ID" value="">
                            <input type="hidden" id="S_PRICE_ID" value="">

                            <div class="layout-grid">
                                <!-- PANEL KIRI -->
                                <div class="left-panel">
                                    <div class="row">
                                        <div class="col-md-3 form-group">
                                            <label>CUST CODE</label>
                                            <div class="autocomplete-wrap">
                                                <input type="text" id="S_CUST_CODE" class="form-control" autocomplete="off">
                                                <div id="S_custSuggest" class="autocomplete-list"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label>CUST NAME</label>
                                            <input type="text" id="S_CUST_COMP" class="form-control" readonly>
                                        </div>
                                        <div class="col-md-3 form-group" style="padding-top: 23px;">
                                            <button type="button" class="btn btn-default btn-block" id="S_btnRefreshItem">REFRESH ITEM</button>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12 form-group">
                                            <label>CARI ITEM CODE / NAME</label>
                                            <div class="autocomplete-wrap">
                                                <input type="text" id="S_ITEM_SEARCH" class="form-control" autocomplete="off" placeholder="Ketik part code / part name / part no">
                                                <div id="S_itemSearchSuggest" class="autocomplete-list" style="width: 100%;"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="status" id="S_LabelStatus">Ready.</div>

                                    <div style="max-height: 250px; overflow-y: auto;">
                                        <table class="grid-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:95px;">PART_CODE</th>
                                                    <th style="width:110px;">PRCD</th>
                                                    <th style="width:140px;">PART_NO</th>
                                                    <th>PART_NAME</th>
                                                    <th style="width:85px; text-align:right;">DELIVERY</th>
                                                    <th style="width:75px; text-align:right;">BAL</th>
                                                </tr>
                                            </thead>
                                            <tbody id="S_itemBody">
                                                <tr><td colspan="6">Pilih customer dulu.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- PANEL KANAN -->
                                <div class="right-panel">
                                    <div class="small-info" id="S_selectedPartInfo">Belum pilih part.</div>
                                    
                                    <div class="toolbar">
                                        <button type="button" class="btn btn-default" id="S_btnAddSchedule">+</button>
                                        <button type="button" class="btn btn-default" id="S_btnDeleteSchedule">-</button>
                                        <button type="button" class="btn btn-success" id="S_btnSaveSchedule">SIMPAN</button>
                                        <button type="button" class="btn btn-default" id="S_btnReloadSchedule">REFRESH</button>
                                    </div>

                                    <div style="max-height: 330px; overflow-y: auto;">
                                        <table class="grid-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:140px;">DELS_DATE</th>
                                                    <th style="text-align:right;">DELS_QTY</th>
                                                </tr>
                                            </thead>
                                            <tbody id="S_scheduleBody">
                                                <tr><td colspan="2">Belum ada data.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     GLOBAL UTILS JAVASCRIPT
========================================================================== -->
<!-- Area Sugesti Material Order -->
                            <div id="O_itemSuggest" class="autocomplete-list" style="width: 520px;"></div>
                            
                            <!-- Delivery Modal (Hidden by Default) -->
                            <div id="O_deliveryModal" class="modal-bg">
                                <div class="modal-box">
                                    <button type="button" class="btn btn-danger btn-sm pull-right" onclick="closeDeliveryModal()">CLOSE</button>
                                    <div class="modal-title">LIST DELIVERY</div>
                                    <div id="O_deliveryInfo" style="margin-bottom:8px;color:navy;font-weight:bold;"></div>
                                    <table class="grid-table">
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
                                        <tbody id="O_deliveryBody">
                                            <tr><td colspan="7">Belum ada data.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- End Modal -->

<script>
    function openTab(evt, tabName) {
        evt.preventDefault(); 
        
        var tabPanes = document.getElementsByClassName("tab-pane");
        for (var i = 0; i < tabPanes.length; i++) {
            tabPanes[i].classList.remove("active");
        }

        var tabLis = document.getElementsByClassName("tab-li");
        for (var i = 0; i < tabLis.length; i++) {
            tabLis[i].classList.remove("active");
        }

        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.parentElement.classList.add("active");
    }

    function enc(v) { return encodeURIComponent(v == null ? "" : v); }
    function htmlEncode(value) { return String(value == null ? "" : value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
    function ajaxPost(url, data, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", url, true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function () { if (xhr.readyState == 4) callback(xhr.status, xhr.responseText); };
        xhr.send(data);
    }
</script>


<!-- =========================================================================
     JAVASCRIPT FORECAST (Isolated)
========================================================================== -->
<script>
(function() {
    function f_byId(id) { return document.getElementById("F_" + id); }

    var custTimer = null;
    var custRows = [];
    var custIndex = -1;

    var selectedItemRow = null;
    var selectedForecastRow = null;
    var forecastLoadSeq = 0;

    var itemRowsAll = [];
    var itemRowsCache = [];
    var itemSuggestRows = [];
    var itemSuggestIndex = -1;
    var itemSearchTimer = null;

    function setStatus(text) { f_byId("LabelStatus").innerHTML = text; }

    function hideCustSuggest() {
        var box = f_byId("custSuggest");
        if (box) { box.style.display = "none"; box.innerHTML = ""; }
        custRows = []; custIndex = -1;
    }

    function setActiveCust(index) {
        var box = f_byId("custSuggest");
        var items = box.getElementsByClassName("autocomplete-item");
        if (!items || items.length == 0) { custIndex = -1; return; }
        if (index < 0) { index = items.length - 1; }
        if (index >= items.length) { index = 0; }
        for (var i = 0; i < items.length; i++) { items[i].className = "autocomplete-item"; }
        items[index].className = "autocomplete-item active";
        custIndex = index;
        if (items[index].offsetTop < box.scrollTop) { box.scrollTop = items[index].offsetTop; } 
        else if ((items[index].offsetTop + items[index].offsetHeight) > (box.scrollTop + box.clientHeight)) { box.scrollTop = items[index].offsetTop + items[index].offsetHeight - box.clientHeight; }
    }

    function chooseCust(index) {
        if (index < 0 || index >= custRows.length) return;
        var r = custRows[index];
        f_byId("CUST_ID").value = r.CUST_ID;
        f_byId("CUST_CODE").value = r.CUST_CODE;
        f_byId("CUST_COMP").value = r.CUST_COMP;
        f_byId("PRICE_ID").value = "";
        f_byId("selectedPartInfo").innerHTML = "Belum pilih part.";
        f_byId("forecastBody").innerHTML = '<tr><td colspan="2">Belum ada data.</td></tr>';
        hideCustSuggest();
        loadItems();
    }

    function showCustSuggest(rows) {
        var box = f_byId("custSuggest");
        box.innerHTML = "";
        custRows = rows || [];
        custIndex = -1;
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
        var q = f_byId("CUST_CODE").value;
        if (q.length < 1) { hideCustSuggest(); return; }
        ajaxPost("ajax_forecast_customer.php", "q=" + enc(q), function (status, responseText) {
            if (status != 200) { hideCustSuggest(); return; }
            try { var result = JSON.parse(responseText); if (!result.success) hideCustSuggest(); else showCustSuggest(result.rows); } 
            catch (e) { hideCustSuggest(); }
        });
    }

    function custKeyDown(e) {
        e = e || window.event;
        var key = e.keyCode || e.which;
        var box = f_byId("custSuggest");
        if (!box || box.style.display != "block") return true;
        if (key == 40) { e.preventDefault ? e.preventDefault() : e.returnValue = false; setActiveCust(custIndex + 1); return false; }
        if (key == 38) { e.preventDefault ? e.preventDefault() : e.returnValue = false; setActiveCust(custIndex - 1); return false; }
        if (key == 13) { e.preventDefault ? e.preventDefault() : e.returnValue = false; if (custIndex < 0 && custRows.length > 0) custIndex = 0; chooseCust(custIndex); return false; }
        return true;
    }

    function loadItems() {
        var custId = f_byId("CUST_ID").value;
        if (custId == "") { alert("Pilih customer dulu."); f_byId("CUST_CODE").focus(); return; }
        setStatus("Loading item forecast...");
        ajaxPost("ajax_forecast_items.php", "CUST_ID=" + enc(custId), function (status, responseText) {
            if (status != 200) { alert("HTTP Error: " + status); setStatus("Load item gagal."); return; }
            try { var result = JSON.parse(responseText); if (!result.success) { alert(result.message); setStatus("Load item gagal."); return; } renderItems(result.rows); setStatus("Item loaded: " + result.rows.length); } 
            catch (e) { alert("Response bukan JSON:\n\n" + responseText); setStatus("Load item gagal."); }
        });
    }

    function getItemRows() { return f_byId("itemBody").getElementsByTagName("tr"); }
    function getSelectedItemIndex() { var rows = getItemRows(); for (var i = 0; i < rows.length; i++) { if (rows[i] === selectedItemRow) return i; } return -1; }
    function moveItemSelection(direction) {
        var rows = getItemRows(); if (!rows || rows.length == 0) return;
        var index = getSelectedItemIndex();
        if (index < 0) index = 0; else index = index + direction;
        if (index < 0) index = 0;
        if (index >= rows.length) index = rows.length - 1;
        selectItemRow(rows[index]); loadForecast();
        if (rows[index].scrollIntoView) rows[index].scrollIntoView({ block: "nearest" });
        rows[index].focus();
    }

    function focusForecastGrid() {
        var body = f_byId("forecastBody");
        var rows = body.getElementsByTagName("tr");
        if (!rows || rows.length == 0) { addForecastRow(); rows = body.getElementsByTagName("tr"); }
        if (rows.length > 0) {
            var lastIndex = rows.length - 1; var lastRow = rows[lastIndex]; selectForecastRow(lastRow);
            var qtyInput = getForecastInput(lastRow, "FORE_QTY"); var monthInput = getForecastInput(lastRow, "FORE_MONTH");
            if (qtyInput) { qtyInput.focus(); qtyInput.select(); } else if (monthInput) { monthInput.focus(); monthInput.select(); }
            if (lastRow.scrollIntoView) lastRow.scrollIntoView({ block: "nearest" });
        }
    }

    function itemGridKeyDown(e) {
        e = e || window.event; var key = e.keyCode || e.which;
        if (key == 40) { e.preventDefault ? e.preventDefault() : e.returnValue = false; moveItemSelection(1); return false; }
        if (key == 38) { e.preventDefault ? e.preventDefault() : e.returnValue = false; moveItemSelection(-1); return false; }
        if (key == 13) { e.preventDefault ? e.preventDefault() : e.returnValue = false; loadForecast(); return false; }
        if (key == 9)  { e.preventDefault ? e.preventDefault() : e.returnValue = false; focusForecastGrid(); return false; }
        return true;
    }

    function hideItemSearchSuggest() {
        var box = f_byId("itemSearchSuggest");
        if (box) { box.style.display = "none"; box.innerHTML = ""; }
        itemSuggestRows = []; itemSuggestIndex = -1;
    }

    function setActiveItemSuggest(index) {
        var box = f_byId("itemSearchSuggest");
        var items = box.getElementsByClassName("autocomplete-item");
        if (!items || items.length == 0) { itemSuggestIndex = -1; return; }
        if (index < 0) index = items.length - 1;
        if (index >= items.length) index = 0;
        for (var i = 0; i < items.length; i++) items[i].className = "autocomplete-item";
        items[index].className = "autocomplete-item active";
        itemSuggestIndex = index;
        if (items[index].offsetTop < box.scrollTop) box.scrollTop = items[index].offsetTop;
        else if ((items[index].offsetTop + items[index].offsetHeight) > (box.scrollTop + box.clientHeight)) box.scrollTop = items[index].offsetTop + items[index].offsetHeight - box.clientHeight;
    }

    function findItemRowByPriceId(priceId) {
        var rows = f_byId("itemBody").getElementsByTagName("tr");
        for (var i = 0; i < rows.length; i++) { if (rows[i].getAttribute("data-price-id") == priceId) return rows[i]; }
        return null;
    }

    function chooseItemSuggest(index) {
        if (index < 0 || index >= itemSuggestRows.length) return;
        var r = itemSuggestRows[index];
        var row = findItemRowByPriceId(r.PRICE_ID);
        if (!row) return;
        f_byId("ITEM_SEARCH").value = r.PART_CODE + " - " + r.PART_NAME;
        hideItemSearchSuggest();
        selectItemRow(row); loadForecast(); row.focus();
        if (row.scrollIntoView) row.scrollIntoView({ block: "nearest" });
    }

    function showItemSearchSuggest(rows) {
        var box = f_byId("itemSearchSuggest"); box.innerHTML = "";
        itemSuggestRows = rows || []; itemSuggestIndex = -1;
        if (!rows || rows.length == 0) { box.style.display = "none"; return; }
        for (var i = 0; i < rows.length; i++) {
            (function (r, idx) {
                var div = document.createElement("div"); div.className = "autocomplete-item";
                div.innerHTML = '<div class="autocomplete-main">' + htmlEncode(r.PART_CODE) + " - " + htmlEncode(r.PART_NAME) + '</div><div class="autocomplete-sub">PRCD: ' + htmlEncode(r.PRICE_CODE) + ' | PART NO: ' + htmlEncode(r.PART_NO) + ' | PRICE_ID: ' + htmlEncode(r.PRICE_ID) + '</div>';
                div.onmouseover = function () { setActiveItemSuggest(idx); };
                div.onclick = function () { chooseItemSuggest(idx); };
                box.appendChild(div);
            })(rows[i], i);
        }
        box.style.display = "block";
        setActiveItemSuggest(0);
    }

    function searchItemLocal() {
        var q = f_byId("ITEM_SEARCH").value.toLowerCase();
        var result = [];
        if (q.length < 1) { hideItemSearchSuggest(); itemRowsCache = itemRowsAll; renderItemGrid(itemRowsAll, true); setStatus("Item loaded: " + itemRowsAll.length); return; }
        for (var i = 0; i < itemRowsAll.length; i++) {
            var r = itemRowsAll[i];
            var text = (String(r.PART_CODE || "") + " " + String(r.PART_NAME || "") + " " + String(r.PART_NO || "") + " " + String(r.PRICE_CODE || "")).toLowerCase();
            if (text.indexOf(q) >= 0) result.push(r);
            if (result.length >= 15) break;
        }
        itemRowsCache = result;
        renderItemGrid(result, true); 
        showItemSearchSuggest(result);
        setStatus("Hasil pencarian item: " + result.length + " dari " + itemRowsAll.length);
    }

    function itemSearchKeyDown(e) {
        e = e || window.event; var key = e.keyCode || e.which;
        var box = f_byId("itemSearchSuggest");
        if (!box || box.style.display != "block") {
            if (key == 13) { e.preventDefault ? e.preventDefault() : e.returnValue = false; searchItemLocal(); return false; }
            return true;
        }
        if (key == 40) { e.preventDefault ? e.preventDefault() : e.returnValue = false; setActiveItemSuggest(itemSuggestIndex + 1); return false; }
        if (key == 38) { e.preventDefault ? e.preventDefault() : e.returnValue = false; setActiveItemSuggest(itemSuggestIndex - 1); return false; }
        if (key == 13) { e.preventDefault ? e.preventDefault() : e.returnValue = false; if (itemSuggestIndex < 0 && itemSuggestRows.length > 0) itemSuggestIndex = 0; chooseItemSuggest(itemSuggestIndex); return false; }
        return true;
    }

    function renderItems(rows) {
        itemRowsAll = rows || []; itemRowsCache = itemRowsAll; f_byId("ITEM_SEARCH").value = "";
        renderItemGrid(itemRowsAll); setStatus("Item loaded: " + itemRowsAll.length);
    }

    function renderItemGrid(rows, preventFocus) {
        var body = f_byId("itemBody");
        body.innerHTML = ""; selectedItemRow = null;
        f_byId("PRICE_ID").value = ""; f_byId("selectedPartInfo").innerHTML = "Belum pilih part."; f_byId("forecastBody").innerHTML = '<tr><td colspan="2">Belum ada data.</td></tr>';
        if (!rows || rows.length == 0) { body.innerHTML = '<tr><td colspan="4">Item tidak ditemukan.</td></tr>'; return; }
        for (var i = 0; i < rows.length; i++) {
            (function (r) {
                var tr = document.createElement("tr");
                tr.setAttribute("tabindex", "0"); tr.setAttribute("data-price-id", r.PRICE_ID); tr.setAttribute("data-part-code", r.PART_CODE); tr.setAttribute("data-part-name", r.PART_NAME);
                tr.innerHTML = '<td style="width:110px;">' + htmlEncode(r.PART_CODE) + '</td><td style="width:120px;">' + htmlEncode(r.PRICE_CODE) + '</td><td style="width:120px;">' + htmlEncode(r.PART_NO) + '</td><td>' + htmlEncode(r.PART_NAME) + '</td>';
                tr.onclick = function () { selectItemRow(this); loadForecast(); this.focus(); };
                tr.ondblclick = function () { selectItemRow(this); loadForecast(); focusForecastGrid(); };
                tr.onkeydown = function (e) { return itemGridKeyDown(e); };
                body.appendChild(tr);
            })(rows[i]);
        }
        var firstRow = body.getElementsByTagName("tr")[0];
        if (firstRow && !preventFocus) { selectItemRow(firstRow); loadForecast(); firstRow.focus(); }
    }

    function selectItemRow(row) {
        var rows = f_byId("itemBody").getElementsByTagName("tr");
        for (var i = 0; i < rows.length; i++) rows[i].className = "";
        row.className = "selected"; selectedItemRow = row;
        f_byId("PRICE_ID").value = row.getAttribute("data-price-id");
        f_byId("selectedPartInfo").innerHTML = "PRICE_ID: " + htmlEncode(row.getAttribute("data-price-id")) + " | " + htmlEncode(row.getAttribute("data-part-code")) + " - " + htmlEncode(row.getAttribute("data-part-name"));
    }

    function loadForecast() {
        var priceId = f_byId("PRICE_ID").value;
        if (priceId == "") return;
        var currentSeq = ++forecastLoadSeq;
        ajaxPost("ajax_forecast_load.php", "PRICE_ID=" + enc(priceId), function (status, responseText) {
            if (currentSeq != forecastLoadSeq) return;
            if (status != 200) { alert("HTTP Error: " + status); return; }
            try { var result = JSON.parse(responseText); if (!result.success) { alert(result.message); return; } renderForecast(result.rows); } 
            catch (e) { alert("Response bukan JSON:\n\n" + responseText); }
        });
    }

    function renderForecast(rows) {
        var body = f_byId("forecastBody");
        body.innerHTML = ""; selectedForecastRow = null;
        if (!rows || rows.length == 0) { addForecastRow(); return; }
        for (var i = 0; i < rows.length; i++) addForecastRow(rows[i]);
        var firstRow = body.getElementsByTagName("tr")[0];
        if (firstRow) selectForecastRow(firstRow);
    }

    function selectForecastRow(row) {
        var rows = f_byId("forecastBody").getElementsByTagName("tr");
        for (var i = 0; i < rows.length; i++) rows[i].className = "";
        row.className = "selected"; selectedForecastRow = row;
    }

    function getForecastInput(row, name) {
        var inputs = row.getElementsByTagName("input");
        for (var i = 0; i < inputs.length; i++) { if (inputs[i].getAttribute("data-name") == name) return inputs[i]; }
        return null;
    }

    function getNextMonthValue() {
        var body = f_byId("forecastBody"); var rows = body.getElementsByTagName("tr"); var maxValue = "";
        for (var i = 0; i < rows.length; i++) { var monthInput = getForecastInput(rows[i], "FORE_MONTH"); if (monthInput && monthInput.value != "" && monthInput.value > maxValue) maxValue = monthInput.value; }
        if (maxValue != "") { var parts = maxValue.split("-"); var y = parseInt(parts[0], 10); var m = parseInt(parts[1], 10); m++; if (m > 12) { y++; m = 1; } return y + "-" + (m < 10 ? "0" + m : m); }
        var d = new Date(); var month = d.getMonth() + 1; return d.getFullYear() + "-" + (month < 10 ? "0" + month : month);
    }

    function getForecastRows() { return f_byId("forecastBody").getElementsByTagName("tr"); }
    function getSelectedForecastIndex() { var rows = getForecastRows(); for (var i = 0; i < rows.length; i++) { if (rows[i] === selectedForecastRow) return i; } return -1; }
    function moveForecastSelection(direction, fieldName) {
        var rows = getForecastRows(); if (!rows || rows.length == 0) { addForecastRow(); rows = getForecastRows(); }
        var index = getSelectedForecastIndex();
        if (index < 0) index = 0; else index = index + direction;
        if (direction > 0 && index >= rows.length) { addForecastRow(); rows = getForecastRows(); index = rows.length - 1; }
        if (index < 0) index = 0; if (index >= rows.length) index = rows.length - 1;
        selectForecastRow(rows[index]);
        var input = getForecastInput(rows[index], fieldName);
        if (input) { input.focus(); input.select(); }
        if (rows[index].scrollIntoView) rows[index].scrollIntoView({ block: "nearest" });
    }

    function addForecastRow(data) {
        data = data || {};
        var body = f_byId("forecastBody");
        if (body.getElementsByTagName("td").length == 1 && body.innerHTML.indexOf("Belum ada") >= 0) body.innerHTML = "";
        var tr = document.createElement("tr");
        tr.onclick = function () { selectForecastRow(this); };
        var monthValue = data.FORE_MONTH || getNextMonthValue();
        var qtyValue = data.hasOwnProperty("FORE_QTY") ? data.FORE_QTY : "";
        tr.innerHTML = '<td style="width:130px;"><input type="month" data-name="FORE_MONTH" value="' + htmlEncode(monthValue) + '"></td><td><input type="number" class="num" data-name="FORE_QTY" value="' + htmlEncode(qtyValue) + '"></td>';
        body.appendChild(tr);
        var monthInput = getForecastInput(tr, "FORE_MONTH"); var qtyInput = getForecastInput(tr, "FORE_QTY");
        monthInput.onfocus = function () { selectForecastRow(tr); }; qtyInput.onfocus = function () { selectForecastRow(tr); };
        monthInput.onkeydown = function (e) {
            e = e || window.event; var key = e.keyCode || e.which;
            if (key == 13 || key == 9) { e.preventDefault ? e.preventDefault() : e.returnValue = false; qtyInput.focus(); qtyInput.select(); return false; }
            if (key == 40) { e.preventDefault ? e.preventDefault() : e.returnValue = false; moveForecastSelection(1, "FORE_MONTH"); return false; }
            if (key == 38) { e.preventDefault ? e.preventDefault() : e.returnValue = false; moveForecastSelection(-1, "FORE_MONTH"); return false; }
            return true;
        };
        qtyInput.onkeydown = function (e) {
            e = e || window.event; var key = e.keyCode || e.which;
            if (key == 13) {
                e.preventDefault ? e.preventDefault() : e.returnValue = false;
                saveForecast(function (ok) { if (ok) { addForecastRow(); var rows = f_byId("forecastBody").getElementsByTagName("tr"); var lastRow = rows[rows.length - 1]; selectForecastRow(lastRow); getForecastInput(lastRow, "FORE_MONTH").focus(); } });
                return false;
            }
            if (key == 40) { e.preventDefault ? e.preventDefault() : e.returnValue = false; moveForecastSelection(1, "FORE_QTY"); return false; }
            if (key == 38) { e.preventDefault ? e.preventDefault() : e.returnValue = false; moveForecastSelection(-1, "FORE_QTY"); return false; }
            return true;
        };
        selectForecastRow(tr);
    }

    function collectForecastRows() {
        var trs = f_byId("forecastBody").getElementsByTagName("tr"); var rows = []; var checkMonth = {};
        for (var i = 0; i < trs.length; i++) {
            var monthInput = getForecastInput(trs[i], "FORE_MONTH"); var qtyInput = getForecastInput(trs[i], "FORE_QTY");
            if (!monthInput || !qtyInput) continue;
            var month = monthInput.value; var qtyText = qtyInput.value;
            if (month == "") continue;
            if (qtyText == "") qtyText = "0";
            var qty = parseInt(qtyText, 10);
            if (isNaN(qty) || qty < 0) { alert("FORE_QTY tidak valid pada month " + month); qtyInput.focus(); return false; }
            if (checkMonth[month]) { alert("Month duplicate di grid: " + month); monthInput.focus(); return false; }
            checkMonth[month] = true;
            rows.push({ FORE_MONTH: month, FORE_QTY: qty });
        }
        return rows;
    }

    function saveForecast(callback) {
        var priceId = f_byId("PRICE_ID").value;
        if (priceId == "") { alert("Pilih item dulu."); if (callback) callback(false); return; }
        var rows = collectForecastRows();
        if (rows === false || rows.length == 0) { if (rows !== false) alert("Forecast kosong."); if (callback) callback(false); return; }
        ajaxPost("ajax_forecast_save.php", "PRICE_ID=" + enc(priceId) + "&ROWS_JSON=" + enc(JSON.stringify(rows)), function (status, responseText) {
            if (status != 200) { alert("HTTP Error: " + status); if (callback) callback(false); return; }
            try { var result = JSON.parse(responseText); if (!result.success) { alert(result.message); if (callback) callback(false); return; } setStatus(result.message); if (callback) callback(true); } 
            catch (e) { alert("Response bukan JSON:\n\n" + responseText); if (callback) callback(false); }
        });
    }

    function deleteForecastRow() {
        if (!selectedForecastRow) { alert("Pilih forecast yang mau dihapus."); return; }
        var priceId = f_byId("PRICE_ID").value; var monthInput = getForecastInput(selectedForecastRow, "FORE_MONTH");
        if (!monthInput || monthInput.value == "") { selectedForecastRow.parentNode.removeChild(selectedForecastRow); selectedForecastRow = null; if (f_byId("forecastBody").getElementsByTagName("tr").length == 0) addForecastRow(); return; }
        if (priceId == "") { alert("PRICE_ID kosong."); return; }
        if (!confirm("Hapus forecast month " + monthInput.value + "?")) return;
        ajaxPost("ajax_forecast_delete.php", "PRICE_ID=" + enc(priceId) + "&FORE_MONTH=" + enc(monthInput.value), function (status, responseText) {
            if (status != 200) { alert("HTTP Error: " + status); return; }
            try { var result = JSON.parse(responseText); if (!result.success) { alert(result.message); return; } selectedForecastRow.parentNode.removeChild(selectedForecastRow); selectedForecastRow = null; if (f_byId("forecastBody").getElementsByTagName("tr").length == 0) addForecastRow(); setStatus(result.message); } 
            catch (e) { alert("Response bukan JSON:\n\n" + responseText); }
        });
    }

    f_byId("CUST_CODE").onkeydown = function (e) { return custKeyDown(e); };
    f_byId("CUST_CODE").onkeyup = function (e) { e = e || window.event; var key = e.keyCode || e.which; if (key == 13 || key == 38 || key == 40) return false; clearTimeout(custTimer); custTimer = setTimeout(function () { searchCustomer(); }, 250); };
    f_byId("CUST_CODE").onblur = function () { setTimeout(function () { hideCustSuggest(); }, 250); };
    f_byId("ITEM_SEARCH").onkeydown = function (e) { return itemSearchKeyDown(e); };
    f_byId("ITEM_SEARCH").onkeyup = function (e) { e = e || window.event; var key = e.keyCode || e.which; if (key == 13 || key == 38 || key == 40) return false; clearTimeout(itemSearchTimer); itemSearchTimer = setTimeout(function () { searchItemLocal(); }, 800); };
    f_byId("ITEM_SEARCH").onblur = function () { setTimeout(function () { hideItemSearchSuggest(); }, 250); };
    f_byId("btnRefreshItem").onclick = function () { loadItems(); };
    f_byId("btnAddMonth").onclick = function () { addForecastRow(); };
    f_byId("btnDeleteMonth").onclick = function () { deleteForecastRow(); };
    f_byId("btnSaveForecast").onclick = function () { saveForecast(); };
    f_byId("btnReloadForecast").onclick = function () { loadForecast(); };
})();
</script>

<!-- =========================================================================
     JAVASCRIPT ORDER (Isolated)
========================================================================== -->
<script>
(function() {
    function o_byId(id) { return document.getElementById("O_" + id); }

    var selectedRow = null; var custTimer = null; var itemTimer = null; var poTimer = null; var activeItemInput = null;
    var custSuggestRows = []; var custSuggestIndex = -1;
    var poSuggestRows = []; var poSuggestIndex = -1;
    var itemSuggestRows = []; var itemSuggestIndex = -1;
    var lastOrderPoText = "";

    function setStatus(text) { o_byId("LabelStatus").innerHTML = text; }
    function money(v) { var n = parseFloat(v || "0"); if (isNaN(n)) n = 0; return n.toFixed(2); }
    function numberValue(input) { var v = parseFloat(input.value || "0"); if (isNaN(v)) return 0; return v; }

    function clearForm() {
        o_byId("ORDR_ID").value = ""; o_byId("CUST_ID").value = ""; o_byId("CUST_CODE").value = ""; o_byId("CUST_COMP").value = "";
        o_byId("ORDR_CURR").value = ""; o_byId("ORDR_PO").value = ""; o_byId("ORDR_DATE").value = "<?php echo h($today); ?>";
        o_byId("ORDR_REM").value = ""; o_byId("ORDR_REPLACEMENT").checked = false; o_byId("ORDR_CLOSE").checked = false;
        o_byId("orderBody").innerHTML = ""; selectedRow = null; lastOrderPoText = "";
        hidePoSuggest(); hideCustSuggest(); hideItemSuggest(); addRow(); setStatus("Input order baru.");
    }

    function prepareSearchNewPo() {
        var currentOrderId = o_byId("ORDR_ID").value;
        if (currentOrderId != "") { o_byId("ORDR_ID").value = ""; o_byId("orderBody").innerHTML = ""; selectedRow = null; addRow(); setStatus("Cari order lain..."); }
    }

    function selectRow(row) {
        var rows = o_byId("orderBody").getElementsByTagName("tr");
        for (var i = 0; i < rows.length; i++) rows[i].className = "";
        row.className = "O_selected"; selectedRow = row;
    }

    function getInput(row, name) {
        var inputs = row.getElementsByTagName("input");
        for (var i = 0; i < inputs.length; i++) { if (inputs[i].getAttribute("data-name") == name) return inputs[i]; } return null;
    }

    function getNextDetailLineNo() {
        var rows = o_byId("orderBody").getElementsByTagName("tr"); var maxLine = 0;
        for (var i = 0; i < rows.length; i++) { var linoInput = getInput(rows[i], "ORDP_LINO"); if (linoInput) { var n = parseInt(linoInput.value || "0", 10); if (!isNaN(n) && n > maxLine) maxLine = n; } } return maxLine + 1;
    }

    function updateDisplayRowNumbers() {
        var rows = o_byId("orderBody").getElementsByTagName("tr");
        for (var i = 0; i < rows.length; i++) { var noCell = rows[i].getElementsByTagName("td")[0]; if (noCell && noCell.childNodes.length > 0) noCell.childNodes[0].nodeValue = (i + 1); }
    }
    function renumberRows() { updateDisplayRowNumbers(); }

    function recalcRow(row) {
        var price = numberValue(getInput(row, "ORDP_PRICE")); var qty = parseInt(getInput(row, "ORDP_QTY").value || "0", 10); var delv = parseInt(getInput(row, "ORDP_DQTY").value || "0", 10);
        if (isNaN(qty)) qty = 0; if (isNaN(delv)) delv = 0;
        var balance = qty - delv; if (balance < 0) balance = 0;
        getInput(row, "ORDP_BQTY").value = balance; getInput(row, "AMOUNT").value = money(price * qty);
    }

    function addRow(data) {
        data = data || {}; var tbody = o_byId("orderBody"); var tr = document.createElement("tr");
        tr.onclick = function () { selectRow(this); };
        var displayNo = tbody.getElementsByTagName("tr").length + 1; var lineNo = data.ORDP_LINO || getNextDetailLineNo(); var rowOrdrId = data.ORDR_ID || o_byId("ORDR_ID").value || "";

        tr.innerHTML = '<td style="text-align:center;">' + displayNo + '<input type="hidden" data-name="ORDR_ID" value="' + htmlEncode(rowOrdrId) + '"><input type="hidden" data-name="ORDP_LINO" value="' + htmlEncode(lineNo) + '"><input type="hidden" data-name="PRICE_ID" value="' + htmlEncode(data.PRICE_ID || "") + '"></td>' +
            '<td><input type="text" class="grid-input item-code" data-name="PART_CODE" value="' + htmlEncode(data.PART_CODE || data.ITEM_CODE || "") + '" autocomplete="off"></td>' +
            '<td><input type="text" class="grid-input" data-name="PART_NAME" value="' + htmlEncode(data.PART_NAME || data.ITEM_NAME || "") + '" readonly></td>' +
            '<td><input type="number" class="grid-input num" data-name="ORDP_PRICE" value="' + htmlEncode(data.ORDP_PRICE || data.PRDT_PRICE || "0") + '"></td>' +
            '<td><input type="number" class="grid-input num" data-name="ORDP_QTY" value="' + htmlEncode(data.ORDP_QTY || "0") + '"></td>' +
            '<td><input type="number" class="grid-input num" data-name="ORDP_BQTY" value="' + htmlEncode(data.ORDP_BQTY || "0") + '" readonly></td>' +
            '<td><input type="number" class="grid-input num" data-name="ORDP_DQTY" value="' + htmlEncode(data.ORDP_DQTY || "0") + '" readonly></td>' +
            '<td><input type="hidden" data-name="ORDP_REM" value="' + htmlEncode(data.ORDP_REM || data.ORDP_DESC || "") + '"><button type="button" class="btn-list-delivery btn-default" data-name="BTN_DELIVERY">LIST</button></td>' +
            '<td><input type="text" class="grid-input num" data-name="AMOUNT" value="' + htmlEncode(data.AMOUNT || "0.00") + '" readonly></td>' +
            '<td style="text-align:center;"><input type="checkbox" data-name="ORDP_CLOSE"></td>';

        tbody.appendChild(tr);

        var codeInput = getInput(tr, "PART_CODE"); var priceInput = getInput(tr, "ORDP_PRICE"); var qtyInput = getInput(tr, "ORDP_QTY"); var remInput = getInput(tr, "ORDP_REM");
        var deliveryButton = null; var buttons = tr.getElementsByTagName("button");
        for (var b = 0; b < buttons.length; b++) { if (buttons[b].getAttribute("data-name") == "BTN_DELIVERY") { deliveryButton = buttons[b]; break; } }
        if (deliveryButton) { deliveryButton.onclick = function (e) { e = e || window.event; if (e.stopPropagation) e.stopPropagation(); else e.cancelBubble = true; showDeliveryList(tr); return false; }; }

        codeInput.onkeydown = function (e) { return itemSuggestKeyDown(e); };
        codeInput.onkeyup = function (e) { e = e || window.event; var key = e.keyCode || e.which; if (key == 13 || key == 38 || key == 40) return false; activeItemInput = this; clearTimeout(itemTimer); itemTimer = setTimeout(function () { searchItemAutocomplete(codeInput); }, 250); };
        codeInput.onfocus = function () { activeItemInput = this; };
        codeInput.onblur = function () { setTimeout(function () { hideItemSuggest(); }, 250); };

        priceInput.onkeyup = function () { recalcRow(tr); }; priceInput.onchange = function () { recalcRow(tr); };
        qtyInput.onkeyup = function () { recalcRow(tr); }; qtyInput.onchange = function () { recalcRow(tr); };
        qtyInput.onkeydown = function (e) {
            e = e || window.event; var key = e.keyCode || e.which; var row = this.parentNode.parentNode; var rowIndex = getRowIndex(row);
            if (key == 13) { e.preventDefault ? e.preventDefault() : e.returnValue = false; saveDetailLineFromRow(row, function (ok) { if (!ok) return; var rows = o_byId("orderBody").getElementsByTagName("tr"); if (rowIndex + 1 >= rows.length) addRow(); focusInput(rowIndex + 1, "PART_CODE"); }); return false; }
            return gridKeyDown.call(this, e);
        };
        priceInput.onkeydown = gridKeyDown; if (remInput && remInput.type != "hidden") remInput.onkeydown = gridKeyDown;
        if (parseInt(data.ORDP_CLOSE || 0, 10) == 1) getInput(tr, "ORDP_CLOSE").checked = true;
        recalcRow(tr); selectRow(tr);
    }

    function deleteSelectedRow() {
        if (!selectedRow) { alert("Pilih baris detail yang mau dihapus."); return; }
        var headerOrdrId = o_byId("ORDR_ID").value; var rowOrdrIdInput = getInput(selectedRow, "ORDR_ID"); var linoInput = getInput(selectedRow, "ORDP_LINO"); var dqInput = getInput(selectedRow, "ORDP_DQTY");
        var ordrId = (rowOrdrIdInput && rowOrdrIdInput.value != "") ? rowOrdrIdInput.value : headerOrdrId;
        var lino = linoInput ? linoInput.value : ""; var deliveryQty = dqInput ? parseInt(dqInput.value || "0", 10) : 0;
        if (deliveryQty > 0) { alert("Detail tidak bisa dihapus karena sudah ada DELIVERY.\n\nLine     : " + lino + "\nDelivery : " + deliveryQty); return; }
        if (ordrId == "") { if (!confirm("Hapus baris ini dari layar?")) return; selectedRow.parentNode.removeChild(selectedRow); selectedRow = null; renumberRows(); if (o_byId("orderBody").getElementsByTagName("tr").length == 0) addRow(); setStatus("Baris detail dihapus dari layar."); return; }
        if (lino == "") { alert("Line detail tidak valid."); return; }
        if (!confirm("Hapus detail line " + lino + " dari database?")) return;
        ajaxPost("ajax_input_order_delete_detail.php", "ORDR_ID=" + enc(ordrId) + "&ORDP_LINO=" + enc(lino), function (status, responseText) {
            if (status != 200) { alert("HTTP Error: " + status); setStatus("Hapus detail gagal."); return; }
            try { var result = JSON.parse(responseText); if (!result.success) { alert(result.message); setStatus("Hapus detail gagal."); return; } alert(result.message); selectedRow.parentNode.removeChild(selectedRow); selectedRow = null; renumberRows(); if (o_byId("orderBody").getElementsByTagName("tr").length == 0) addRow(); setStatus(result.message); } 
            catch (e) { alert("Response bukan JSON:\n\n" + responseText); }
        });
    }

    function deleteHeader() {
        var ordrId = o_byId("ORDR_ID").value; var po = o_byId("ORDR_PO").value;
        if (ordrId == "") { alert("Order belum tersimpan / ORDR_ID kosong."); return; }
        if (!confirm("Hapus HEADER order ini?\n\nPO      : " + po + "\nORDR_ID : " + ordrId + "\n\nHeader hanya bisa dihapus kalau detail sudah kosong.")) return;
        ajaxPost("ajax_input_order_delete_header.php", "ORDR_ID=" + enc(ordrId), function (status, responseText) {
            if (status != 200) { alert("HTTP Error: " + status); setStatus("Hapus header gagal."); return; }
            try { var result = JSON.parse(responseText); if (!result.success) { alert(result.message); setStatus("Hapus header gagal."); return; } alert(result.message); clearForm(); } 
            catch (e) { alert("Response bukan JSON:\n\n" + responseText); }
        });
    }

    function getRowIndex(row) { var rows = o_byId("orderBody").getElementsByTagName("tr"); for (var i = 0; i < rows.length; i++) { if (rows[i] === row) return i; } return -1; }
    function focusInput(rowIndex, fieldName) {
        var rows = o_byId("orderBody").getElementsByTagName("tr"); if (rowIndex < 0) return;
        if (rowIndex >= rows.length) { addRow(); rows = o_byId("orderBody").getElementsByTagName("tr"); }
        if (rowIndex < 0 || rowIndex >= rows.length) return;
        var input = getInput(rows[rowIndex], fieldName); if (input) { input.focus(); input.select(); selectRow(rows[rowIndex]); }
    }

    function gridKeyDown(e) {
        e = e || window.event; var key = e.keyCode || e.which; var fieldName = this.getAttribute("data-name"); var row = this.parentNode.parentNode; var rowIndex = getRowIndex(row);
        if (key == 13 || key == 40) { e.preventDefault ? e.preventDefault() : e.returnValue = false; recalcRow(row); focusInput(rowIndex + 1, fieldName); return false; }
        if (key == 38) { e.preventDefault ? e.preventDefault() : e.returnValue = false; recalcRow(row); focusInput(rowIndex - 1, fieldName); return false; }
        return true;
    }

    function setActivePoSuggest(index) {
        var box = o_byId("poSuggest"); var items = box.getElementsByClassName("autocomplete-item");
        if (!items || items.length == 0) { poSuggestIndex = -1; return; }
        if (index < 0) index = items.length - 1; if (index >= items.length) index = 0;
        for (var i = 0; i < items.length; i++) items[i].className = "autocomplete-item";
        items[index].className = "autocomplete-item active"; poSuggestIndex = index;
        if (items[index].offsetTop < box.scrollTop) box.scrollTop = items[index].offsetTop; else if ((items[index].offsetTop + items[index].offsetHeight) > (box.scrollTop + box.clientHeight)) box.scrollTop = items[index].offsetTop + items[index].offsetHeight - box.clientHeight;
    }

    function choosePoSuggest(index) {
        if (index < 0 || index >= poSuggestRows.length) return; var r = poSuggestRows[index];
        o_byId("ORDR_ID").value = ""; o_byId("ORDR_PO").value = r.ORDR_PO; hidePoSuggest(); loadOrderById(r.ORDR_ID);
    }

    function poSuggestKeyDown(e) {
        e = e || window.event; var key = e.keyCode || e.which; var box = o_byId("poSuggest");
        if (!box || box.style.display != "block") { if (key == 13) { e.preventDefault ? e.preventDefault() : e.returnValue = false; loadOrderByPo(o_byId("ORDR_PO").value); return false; } return true; }
        if (key == 40) { e.preventDefault ? e.preventDefault() : e.returnValue = false; setActivePoSuggest(poSuggestIndex + 1); return false; }
        if (key == 38) { e.preventDefault ? e.preventDefault() : e.returnValue = false; setActivePoSuggest(poSuggestIndex - 1); return false; }
        if (key == 13) { e.preventDefault ? e.preventDefault() : e.returnValue = false; if (poSuggestIndex < 0 && poSuggestRows.length > 0) poSuggestIndex = 0; choosePoSuggest(poSuggestIndex); return false; }
        return true;
    }

    function hidePoSuggest() { var box = o_byId("poSuggest"); if (box) { box.style.display = "none"; box.innerHTML = ""; } poSuggestRows = []; poSuggestIndex = -1; }
    function showPoSuggest(rows) {
        var box = o_byId("poSuggest"); box.innerHTML = ""; poSuggestRows = rows || []; poSuggestIndex = -1;
        if (!rows || rows.length == 0) { box.style.display = "none"; return; }
        for (var i = 0; i < rows.length; i++) {
            (function (r, idx) {
                var div = document.createElement("div"); div.className = "autocomplete-item";
                div.innerHTML = '<div class="autocomplete-main">' + htmlEncode(r.ORDR_PO) + '</div><div class="autocomplete-sub">' + htmlEncode(r.ORDR_DATE) + ' | ' + htmlEncode(r.CUST_CODE) + ' | ' + htmlEncode(r.CUST_COMP) + '</div>';
                div.onmouseover = function () { setActivePoSuggest(idx); }; div.onmousedown = function (e) { if (e && e.preventDefault) e.preventDefault(); choosePoSuggest(idx); };
                box.appendChild(div);
            })(rows[i], i);
        }
        box.style.display = "block"; setActivePoSuggest(0);
    }

    function searchPoAutocomplete() {
        var q = o_byId("ORDR_PO").value; if (q.length < 1) { hidePoSuggest(); return; }
        ajaxPost("ajax_input_order_po.php", "q=" + enc(q), function (status, responseText) {
            if (status != 200) { hidePoSuggest(); return; }
            try { var result = JSON.parse(responseText); if (!result.success) hidePoSuggest(); else showPoSuggest(result.rows); } catch (e) { hidePoSuggest(); }
        });
    }

    function loadOrderByPo(po) {
        if (po == "") { alert("ORDER PO belum diisi."); o_byId("ORDR_PO").focus(); return; } setStatus("Loading order PO...");
        ajaxPost("ajax_input_order_get.php", "ORDR_PO=" + enc(po) + "&ORDR_DATE=" + enc(o_byId("ORDR_DATE").value), function (status, responseText) {
            if (status != 200) { alert("HTTP Error: " + status); setStatus("Load order gagal."); return; }
            try { var result = JSON.parse(responseText); if (!result.success) { alert(result.message); setStatus("Load order gagal."); return; } fillOrderFromData(result.header, result.details); setStatus(result.message + " ORDR_ID: " + result.header.ORDR_ID); } catch (e) { alert("Response bukan JSON:\n\n" + responseText); }
        });
    }

    function loadOrderById(ordrId) {
        var idNum = parseInt(ordrId, 10); if (ordrId == "" || isNaN(idNum) || idNum == 0) { alert("ORDR_ID kosong / tidak valid."); return; } setStatus("Loading order...");
        ajaxPost("ajax_input_order_get.php", "ORDR_ID=" + enc(ordrId), function (status, responseText) {
            if (status != 200) { alert("HTTP Error: " + status); setStatus("Load order gagal."); return; }
            try { var result = JSON.parse(responseText); if (!result.success) { alert(result.message); setStatus("Load order gagal."); return; } fillOrderFromData(result.header, result.details); setStatus(result.message + " ORDR_ID: " + result.header.ORDR_ID); } catch (e) { alert("Response bukan JSON:\n\n" + responseText); }
        });
    }

    function fillOrderFromData(header, details) {
        o_byId("ORDR_ID").value = header.ORDR_ID || ""; o_byId("CUST_ID").value = header.CUST_ID || ""; o_byId("CUST_CODE").value = header.CUST_CODE || ""; o_byId("CUST_COMP").value = header.CUST_COMP || ""; o_byId("ORDR_CURR").value = header.ORDR_CURR || ""; o_byId("ORDR_PO").value = header.ORDR_PO || ""; o_byId("ORDR_DATE").value = header.ORDR_DATE || ""; o_byId("ORDR_REM").value = header.ORDR_REM || ""; o_byId("ORDR_REPLACEMENT").checked = parseInt(header.ORDR_REPLACEMENT || 0, 10) == 1; o_byId("ORDR_CLOSE").checked = parseInt(header.ORDR_CLOSE || 0, 10) == 1;
        o_byId("orderBody").innerHTML = ""; selectedRow = null;
        for (var i = 0; i < details.length; i++) addRow(details[i]); if (details.length == 0) addRow();
        lastOrderPoText = header.ORDR_PO || "";
    }

    function setActiveItemSuggest(index) {
        var box = o_byId("itemSuggest"); var items = box.getElementsByClassName("autocomplete-item");
        if (!items || items.length == 0) { itemSuggestIndex = -1; return; }
        if (index < 0) index = items.length - 1; if (index >= items.length) index = 0;
        for (var i = 0; i < items.length; i++) items[i].className = "autocomplete-item";
        items[index].className = "autocomplete-item active"; itemSuggestIndex = index;
        if (items[index].offsetTop < box.scrollTop) box.scrollTop = items[index].offsetTop; else if ((items[index].offsetTop + items[index].offsetHeight) > (box.scrollTop + box.clientHeight)) box.scrollTop = items[index].offsetTop + items[index].offsetHeight - box.clientHeight;
    }

    function chooseItemSuggest(index) {
        if (index < 0 || index >= itemSuggestRows.length) return; if (!activeItemInput) return;
        var r = itemSuggestRows[index]; var row = activeItemInput.parentNode.parentNode; var duplicateRow = findDuplicatePriceId(r.PRICE_ID, row);
        if (duplicateRow) { alert("Item ini sudah ada di detail order.\n\nPart Code : " + r.PART_CODE + "\nPart Name : " + r.PART_NAME + "\n\nTidak boleh input item yang sama dua kali."); hideItemSuggest(); selectRow(duplicateRow); var qtyInput = getInput(duplicateRow, "ORDP_QTY"); if (qtyInput) { qtyInput.focus(); qtyInput.select(); } return; }
        getInput(row, "PRICE_ID").value = r.PRICE_ID; getInput(row, "PART_CODE").value = r.PART_CODE; getInput(row, "PART_NAME").value = r.PART_NAME; getInput(row, "ORDP_PRICE").value = r.PRDT_PRICE || "0";
        recalcRow(row); hideItemSuggest(); getInput(row, "ORDP_QTY").focus(); getInput(row, "ORDP_QTY").select();
    }

    function itemSuggestKeyDown(e) {
        e = e || window.event; var key = e.keyCode || e.which; var box = o_byId("itemSuggest");
        if (!box || box.style.display != "block") return true;
        if (key == 40) { e.preventDefault ? e.preventDefault() : e.returnValue = false; setActiveItemSuggest(itemSuggestIndex + 1); return false; }
        if (key == 38) { e.preventDefault ? e.preventDefault() : e.returnValue = false; setActiveItemSuggest(itemSuggestIndex - 1); return false; }
        if (key == 13) { e.preventDefault ? e.preventDefault() : e.returnValue = false; if (itemSuggestIndex < 0 && itemSuggestRows.length > 0) itemSuggestIndex = 0; chooseItemSuggest(itemSuggestIndex); return false; }
        return true;
    }

    function hideItemSuggest() { var box = o_byId("itemSuggest"); if (box) { box.style.display = "none"; box.innerHTML = ""; } itemSuggestRows = []; itemSuggestIndex = -1; }
    function showItemSuggest(input, rows) {
        var box = o_byId("itemSuggest"); box.innerHTML = ""; itemSuggestRows = rows || []; itemSuggestIndex = -1; activeItemInput = input;
        if (!rows || rows.length == 0) { box.style.display = "none"; return; }
        var rect = input.getBoundingClientRect(); box.style.left = (rect.left + window.pageXOffset) + "px"; box.style.top = (rect.bottom + window.pageYOffset) + "px";
        for (var i = 0; i < rows.length; i++) {
            (function (r, idx) {
                var div = document.createElement("div"); div.className = "autocomplete-item";
                div.innerHTML = '<div class="autocomplete-main">' + htmlEncode(r.PART_CODE) + ' - ' + htmlEncode(r.PART_NAME) + '</div><div class="autocomplete-sub">PRICE_ID: ' + htmlEncode(r.PRICE_ID) + ' | PRICE: ' + htmlEncode(r.PRDT_PRICE) + ' | PERIOD: ' + htmlEncode(r.PRDT_START) + ' s/d ' + htmlEncode(r.PRDT_END) + '</div>';
                div.onmouseover = function () { setActiveItemSuggest(idx); }; div.onmousedown = function (e) { if (e && e.preventDefault) e.preventDefault(); chooseItemSuggest(idx); };
                box.appendChild(div);
            })(rows[i], i);
        }
        box.style.display = "block"; setActiveItemSuggest(0);
    }

    function searchItemAutocomplete(input) {
        var q = input.value; var orderDate = o_byId("ORDR_DATE").value; var custId = o_byId("CUST_ID").value;
        if (q.length < 2) { hideItemSuggest(); return; }
        if (custId == "") { alert("Customer belum dipilih. Pilih customer dulu."); o_byId("CUST_CODE").focus(); hideItemSuggest(); return; }
        if (orderDate == "") { alert("ORDER DATE belum diisi."); o_byId("ORDR_DATE").focus(); hideItemSuggest(); return; }
        ajaxPost("ajax_input_order_item.php", "q=" + enc(q) + "&ORDR_DATE=" + enc(orderDate) + "&CUST_ID=" + enc(custId), function (status, responseText) {
            if (status != 200) { hideItemSuggest(); return; }
            try { var result = JSON.parse(responseText); if (!result.success) { hideItemSuggest(); if (result.message) setStatus(result.message); return; } showItemSuggest(input, result.rows); } catch (e) { hideItemSuggest(); }
        });
    }

    function setActiveCustSuggest(index) {
        var box = o_byId("custSuggest"); var items = box.getElementsByClassName("autocomplete-item");
        if (!items || items.length == 0) { custSuggestIndex = -1; return; }
        if (index < 0) index = items.length - 1; if (index >= items.length) index = 0;
        for (var i = 0; i < items.length; i++) items[i].className = "autocomplete-item";
        items[index].className = "autocomplete-item active"; custSuggestIndex = index;
        if (items[index].offsetTop < box.scrollTop) box.scrollTop = items[index].offsetTop; else if ((items[index].offsetTop + items[index].offsetHeight) > (box.scrollTop + box.clientHeight)) box.scrollTop = items[index].offsetTop + items[index].offsetHeight - box.clientHeight;
    }

    function chooseCustSuggest(index) {
        if (index < 0 || index >= custSuggestRows.length) return; var r = custSuggestRows[index];
        o_byId("CUST_ID").value = r.CUST_ID; o_byId("CUST_CODE").value = r.CUST_CODE; o_byId("CUST_COMP").value = r.CUST_COMP; o_byId("ORDR_CURR").value = r.CURR_CODE;
        hideCustSuggest(); o_byId("ORDR_PO").focus(); o_byId("ORDR_PO").select();
    }

    function custSuggestKeyDown(e) {
        e = e || window.event; var key = e.keyCode || e.which; var box = o_byId("custSuggest");
        if (!box || box.style.display != "block") return true;
        if (key == 40) { e.preventDefault ? e.preventDefault() : e.returnValue = false; setActiveCustSuggest(custSuggestIndex + 1); return false; }
        if (key == 38) { e.preventDefault ? e.preventDefault() : e.returnValue = false; setActiveCustSuggest(custSuggestIndex - 1); return false; }
        if (key == 13) { e.preventDefault ? e.preventDefault() : e.returnValue = false; if (custSuggestIndex < 0 && custSuggestRows.length > 0) custSuggestIndex = 0; chooseCustSuggest(custSuggestIndex); return false; }
        return true;
    }

    function hideCustSuggest() { var box = o_byId("custSuggest"); if (box) { box.style.display = "none"; box.innerHTML = ""; } custSuggestRows = []; custSuggestIndex = -1; }
    function showCustSuggest(rows) {
        var box = o_byId("custSuggest"); box.innerHTML = ""; custSuggestRows = rows || []; custSuggestIndex = -1;
        if (!rows || rows.length == 0) { box.style.display = "none"; return; }
        for (var i = 0; i < rows.length; i++) {
            (function (r, idx) {
                var div = document.createElement("div"); div.className = "autocomplete-item";
                div.innerHTML = '<div class="autocomplete-main">' + htmlEncode(r.CUST_CODE) + '</div><div class="autocomplete-sub">' + htmlEncode(r.CUST_COMP) + ' | ' + htmlEncode(r.CURR_CODE) + '</div>';
                div.onmouseover = function () { setActiveCustSuggest(idx); }; div.onmousedown = function (e) { if (e && e.preventDefault) e.preventDefault(); chooseCustSuggest(idx); };
                box.appendChild(div);
            })(rows[i], i);
        }
        box.style.display = "block"; setActiveCustSuggest(0);
    }

    function searchCustAutocomplete() {
        var q = o_byId("CUST_CODE").value; if (q.length < 1) { hideCustSuggest(); return; }
        ajaxPost("ajax_input_order_customer.php", "q=" + enc(q), function (status, responseText) {
            if (status != 200) { hideCustSuggest(); return; }
            try { var result = JSON.parse(responseText); if (!result.success) hideCustSuggest(); else showCustSuggest(result.rows); } catch (e) { hideCustSuggest(); }
        });
    }

    function collectRows() {
        var rows = o_byId("orderBody").getElementsByTagName("tr"); var data = [];
        for (var i = 0; i < rows.length; i++) {
            var priceId = getInput(rows[i], "PRICE_ID").value; var qty = parseInt(getInput(rows[i], "ORDP_QTY").value || "0", 10);
            if (priceId == "" && qty == 0) continue;
            data.push({ ORDP_LINO: getInput(rows[i], "ORDP_LINO").value, PRICE_ID: priceId, PART_CODE: getInput(rows[i], "PART_CODE").value, PART_NAME: getInput(rows[i], "PART_NAME").value, ORDP_PRICE: getInput(rows[i], "ORDP_PRICE").value, ORDP_QTY: getInput(rows[i], "ORDP_QTY").value, ORDP_DQTY: getInput(rows[i], "ORDP_DQTY").value, ORDP_BQTY: getInput(rows[i], "ORDP_BQTY").value, ORDP_REM: getInput(rows[i], "ORDP_REM").value, ORDP_CLOSE: getInput(rows[i], "ORDP_CLOSE").checked ? 1 : 0 });
        } return data;
    }

    function validateBeforeSave() {
        if (o_byId("CUST_ID").value == "") { alert("Customer belum dipilih."); o_byId("CUST_CODE").focus(); return false; }
        if (o_byId("ORDR_PO").value == "") { alert("ORDER PO belum diisi."); o_byId("ORDR_PO").focus(); return false; }
        if (o_byId("ORDR_DATE").value == "") { alert("DATE belum diisi."); o_byId("ORDR_DATE").focus(); return false; }
        var rows = collectRows(); if (rows.length == 0) { alert("Detail order masih kosong."); return false; }
        for (var i = 0; i < rows.length; i++) { if (rows[i].PRICE_ID == "") { alert("PRICE_ID kosong pada line " + rows[i].ORDP_LINO); return false; } if (parseInt(rows[i].ORDP_QTY || "0", 10) <= 0) { alert("QTY harus lebih dari 0 pada line " + rows[i].ORDP_LINO); return false; } } return true;
    }

    function saveDetailLineFromRow(row, callback) {
        var custId = o_byId("CUST_ID").value; var ordrPo = o_byId("ORDR_PO").value; var ordrDate = o_byId("ORDR_DATE").value;
        if (custId == "") { alert("Customer belum dipilih."); o_byId("CUST_CODE").focus(); if (callback) callback(false); return; }
        if (ordrPo == "") { alert("ORDER PO belum diisi."); o_byId("ORDR_PO").focus(); if (callback) callback(false); return; }
        if (ordrDate == "") { alert("ORDER DATE belum diisi."); o_byId("ORDR_DATE").focus(); if (callback) callback(false); return; }
        var priceId = getInput(row, "PRICE_ID").value; var lino = getInput(row, "ORDP_LINO").value; var qty = parseInt(getInput(row, "ORDP_QTY").value || "0", 10);
        if (priceId == "") { alert("Pilih Part Code dulu pada line " + lino); getInput(row, "PART_CODE").focus(); if (callback) callback(false); return; }
        if (isNaN(qty) || qty <= 0) { alert("QTY harus lebih dari 0 pada line " + lino); getInput(row, "ORDP_QTY").focus(); if (callback) callback(false); return; }
        recalcRow(row);
        var data = "ORDR_ID=" + enc(o_byId("ORDR_ID").value) + "&CUST_ID=" + enc(o_byId("CUST_ID").value) + "&ORDR_PO=" + enc(o_byId("ORDR_PO").value) + "&ORDR_DATE=" + enc(o_byId("ORDR_DATE").value) + "&ORDR_CURR=" + enc(o_byId("ORDR_CURR").value) + "&ORDR_REM=" + enc(o_byId("ORDR_REM").value) + "&ORDR_REPLACEMENT=" + enc(o_byId("ORDR_REPLACEMENT").checked ? 1 : 0) + "&ORDR_CLOSE=" + enc(o_byId("ORDR_CLOSE").checked ? 1 : 0) + "&ORDP_LINO=" + enc(getInput(row, "ORDP_LINO").value) + "&PRICE_ID=" + enc(getInput(row, "PRICE_ID").value) + "&ORDP_PRICE=" + enc(getInput(row, "ORDP_PRICE").value) + "&ORDP_QTY=" + enc(getInput(row, "ORDP_QTY").value) + "&ORDP_DQTY=" + enc(getInput(row, "ORDP_DQTY").value) + "&ORDP_BQTY=" + enc(getInput(row, "ORDP_BQTY").value) + "&ORDP_REM=" + enc(getInput(row, "ORDP_REM").value) + "&ORDP_CLOSE=" + enc(getInput(row, "ORDP_CLOSE").checked ? 1 : 0);
        setStatus("Menyimpan detail line " + lino + "...");
        ajaxPost("ajax_input_order_save_line.php", data, function (status, responseText) {
            if (status != 200) { alert("HTTP Error: " + status); setStatus("Simpan detail gagal."); if (callback) callback(false); return; }
            try { var result = JSON.parse(responseText); if (!result.success) { alert(result.message); setStatus("Simpan detail gagal."); if (callback) callback(false); return; } o_byId("ORDR_ID").value = result.ORDR_ID; getInput(row, "ORDR_ID").value = result.ORDR_ID; getInput(row, "ORDP_BQTY").value = result.ORDP_BQTY; setStatus(result.message + " ORDR_ID: " + result.ORDR_ID); if (callback) callback(true); } 
            catch (e) { alert("Response bukan JSON:\n\n" + responseText); setStatus("Simpan detail gagal."); if (callback) callback(false); }
        });
    }

    window.closeDeliveryModal = function() { o_byId("deliveryModal").style.display = "none"; }
    function showDeliveryList(row) {
        var ordrIdInput = getInput(row, "ORDR_ID"); var linoInput = getInput(row, "ORDP_LINO"); var codeInput = getInput(row, "PART_CODE"); var nameInput = getInput(row, "PART_NAME");
        var ordrId = (ordrIdInput && ordrIdInput.value != "") ? ordrIdInput.value : o_byId("ORDR_ID").value;
        var lino = linoInput ? linoInput.value : ""; var partCode = codeInput ? codeInput.value : ""; var partName = nameInput ? nameInput.value : "";
        if (ordrId == "") { alert("ORDR_ID kosong. Load / simpan order dulu."); return; }
        if (lino == "") { alert("Line detail kosong."); return; }
        o_byId("deliveryInfo").innerHTML = "ORDR_ID: " + htmlEncode(ordrId) + " | LINE: " + htmlEncode(lino) + " | PART: " + htmlEncode(partCode) + " - " + htmlEncode(partName);
        o_byId("deliveryBody").innerHTML = '<tr><td colspan="7">Loading...</td></tr>';
        var modal = o_byId("deliveryModal"); var codeRect = codeInput.getBoundingClientRect(); modal.style.display = "block";
        var leftPos = codeRect.left + window.pageXOffset; var topPos  = codeRect.bottom + window.pageYOffset + 6; var modalWidth = 800; var maxLeft = window.pageXOffset + window.innerWidth - modalWidth - 20;
        if (leftPos > maxLeft) leftPos = maxLeft; if (leftPos < 10) leftPos = 10;
        modal.style.left = leftPos + "px"; modal.style.top  = topPos + "px";
        ajaxPost("ajax_input_order_delivery_list.php", "ORDR_ID=" + enc(ordrId) + "&ORDP_LINO=" + enc(lino), function (status, responseText) {
            if (status != 200) { o_byId("deliveryBody").innerHTML = '<tr><td colspan="7">HTTP Error: ' + status + '</td></tr>'; return; }
            try { var result = JSON.parse(responseText); if (!result.success) { o_byId("deliveryBody").innerHTML = '<tr><td colspan="7">' + htmlEncode(result.message) + '</td></tr>'; return; } if (!result.rows || result.rows.length == 0) { o_byId("deliveryBody").innerHTML = '<tr><td colspan="7">Belum ada delivery untuk line ini.</td></tr>'; return; } var html = ""; var total = 0; for (var i = 0; i < result.rows.length; i++) { var r = result.rows[i]; var qty = parseInt(r.DELIVERY || "0", 10); if (isNaN(qty)) qty = 0; total += qty; html += "<tr><td>" + htmlEncode(r.DI_DATE) + "</td><td>" + htmlEncode(r.DI_NO) + "</td><td style=\"text-align:right;\">" + htmlEncode(r.DELIVERY) + "</td><td>" + htmlEncode(r.CUST_CODE) + "</td><td>" + htmlEncode(r.CUST_COMP) + "</td><td>" + htmlEncode(r.ITEM_CODE) + "</td><td>" + htmlEncode(r.ITEM_NAME) + "</td></tr>"; } html += '<tr style="font-weight:bold;background:#eeeeee;"><td colspan="2" style="text-align:right;">TOTAL</td><td style="text-align:right;">' + total + '</td><td colspan="4"></td></tr>'; o_byId("deliveryBody").innerHTML = html; } 
            catch (e) { o_byId("deliveryBody").innerHTML = '<tr><td colspan="7">Response bukan JSON:<br>' + htmlEncode(responseText) + '</td></tr>'; }
        });
    }

    function saveOrder() {
        if (!validateBeforeSave()) return; if (!confirm("Simpan order ini?")) return; var rows = collectRows();
        var data = "ORDR_ID=" + enc(o_byId("ORDR_ID").value) + "&CUST_ID=" + enc(o_byId("CUST_ID").value) + "&ORDR_PO=" + enc(o_byId("ORDR_PO").value) + "&ORDR_DATE=" + enc(o_byId("ORDR_DATE").value) + "&ORDR_CURR=" + enc(o_byId("ORDR_CURR").value) + "&ORDR_REM=" + enc(o_byId("ORDR_REM").value) + "&ORDR_REPLACEMENT=" + enc(o_byId("ORDR_REPLACEMENT").checked ? 1 : 0) + "&ORDR_CLOSE=" + enc(o_byId("ORDR_CLOSE").checked ? 1 : 0) + "&ROWS_JSON=" + enc(JSON.stringify(rows));
        setStatus("Menyimpan order...");
        ajaxPost("ajax_input_order_save.php", data, function (status, responseText) {
            if (status != 200) { alert("HTTP Error: " + status); setStatus("Simpan gagal."); return; }
            try { var result = JSON.parse(responseText); if (!result.success) { alert(result.message); setStatus("Simpan gagal."); return; } o_byId("ORDR_ID").value = result.ORDR_ID; alert(result.message); setStatus("Order berhasil disimpan. ORDR_ID: " + result.ORDR_ID); } 
            catch (e) { alert("Response bukan JSON:\n\n" + responseText); setStatus("Simpan gagal."); }
        });
    }

    function findDuplicatePriceId(priceId, currentRow) { var rows = o_byId("orderBody").getElementsByTagName("tr"); for (var i = 0; i < rows.length; i++) { if (rows[i] === currentRow) continue; var priceInput = getInput(rows[i], "PRICE_ID"); if (priceInput && priceInput.value == priceId) return rows[i]; } return null; }

    o_byId("CUST_CODE").onkeydown = function (e) { return custSuggestKeyDown(e); };
    o_byId("CUST_CODE").onkeyup = function (e) { e = e || window.event; var key = e.keyCode || e.which; if (key == 13 || key == 38 || key == 40) return false; clearTimeout(custTimer); custTimer = setTimeout(function () { searchCustAutocomplete(); }, 250); };
    o_byId("CUST_CODE").onblur = function () { setTimeout(function () { hideCustSuggest(); }, 250); };
    o_byId("ORDR_PO").onkeydown = function (e) { return poSuggestKeyDown(e); };
    o_byId("ORDR_PO").onkeyup = function (e) { e = e || window.event; var key = e.keyCode || e.which; if (key == 13 || key == 38 || key == 40) return false; prepareSearchNewPo(); clearTimeout(poTimer); poTimer = setTimeout(function () { searchPoAutocomplete(); }, 200); };
    o_byId("ORDR_PO").onfocus = function () { this.select(); };
    o_byId("ORDR_PO").onblur = function () { setTimeout(function () { hidePoSuggest(); }, 250); };

    o_byId("btnNew").onclick = function () { if (confirm("Buat input order baru?")) clearForm(); };
    o_byId("btnAddRow").onclick = function () { addRow(); };
    o_byId("btnDeleteDetail").onclick = function () { deleteSelectedRow(); };
    o_byId("btnDeleteHeader").onclick = function () { deleteHeader(); };
    o_byId("btnSave").onclick = function () { saveOrder(); };
    o_byId("btnCancel").onclick = function () { if (confirm("Batal input order?")) window.close(); };

    clearForm();
})();
</script>

<!-- =========================================================================
     JAVASCRIPT SCHEDULE (Isolated)
========================================================================== -->
<script>
(function() {
    function s_byId(id) { return document.getElementById("S_" + id); }

    var custTimer = null; var custRows = []; var custIndex = -1;
    var selectedItemRow = null; var selectedScheduleRow = null; var scheduleLoadSeq = 0;
    var itemRowsAll = []; var itemRowsCache = []; var itemSuggestRows = []; var itemSuggestIndex = -1; var itemSearchTimer = null;

    function setStatus(text) { s_byId("LabelStatus").innerHTML = text; }

    function hideCustSuggest() { var box = s_byId("custSuggest"); if (box) { box.style.display = "none"; box.innerHTML = ""; } custRows = []; custIndex = -1; }
    function setActiveCust(index) {
        var box = s_byId("custSuggest"); var items = box.getElementsByClassName("autocomplete-item");
        if (!items || items.length == 0) { custIndex = -1; return; }
        if (index < 0) index = items.length - 1; if (index >= items.length) index = 0;
        for (var i = 0; i < items.length; i++) items[i].className = "autocomplete-item";
        items[index].className = "autocomplete-item active"; custIndex = index;
        if (items[index].offsetTop < box.scrollTop) box.scrollTop = items[index].offsetTop; else if ((items[index].offsetTop + items[index].offsetHeight) > (box.scrollTop + box.clientHeight)) box.scrollTop = items[index].offsetTop + items[index].offsetHeight - box.clientHeight;
    }
    function chooseCust(index) {
        if (index < 0 || index >= custRows.length) return; var r = custRows[index];
        s_byId("CUST_ID").value = r.CUST_ID; s_byId("CUST_CODE").value = r.CUST_CODE; s_byId("CUST_COMP").value = r.CUST_COMP;
        s_byId("PRICE_ID").value = ""; s_byId("ITEM_SEARCH").value = ""; s_byId("selectedPartInfo").innerHTML = "Belum pilih part."; s_byId("scheduleBody").innerHTML = '<tr><td colspan="2">Belum ada data.</td></tr>';
        hideCustSuggest(); loadItems();
    }
    function showCustSuggest(rows) {
        var box = s_byId("custSuggest"); box.innerHTML = ""; custRows = rows || []; custIndex = -1;
        if (!rows || rows.length == 0) { box.style.display = "none"; return; }
        for (var i = 0; i < rows.length; i++) {
            (function (r, idx) {
                var div = document.createElement("div"); div.className = "autocomplete-item";
                div.innerHTML = '<div class="autocomplete-main">' + htmlEncode(r.CUST_CODE) + '</div><div class="autocomplete-sub">' + htmlEncode(r.CUST_COMP) + '</div>';
                div.onmouseover = function () { setActiveCust(idx); }; div.onclick = function () { chooseCust(idx); };
                box.appendChild(div);
            })(rows[i], i);
        }
        box.style.display = "block"; setActiveCust(0);
    }
    function searchCustomer() {
        var q = s_byId("CUST_CODE").value; if (q.length < 1) { hideCustSuggest(); return; }
        ajaxPost("ajax_schedule_customer.php", "q=" + enc(q), function (status, responseText) {
            if (status != 200) { hideCustSuggest(); return; }
            try { var result = JSON.parse(responseText); if (!result.success) hideCustSuggest(); else showCustSuggest(result.rows); } catch (e) { hideCustSuggest(); }
        });
    }
    function custKeyDown(e) {
        e = e || window.event; var key = e.keyCode || e.which; var box = s_byId("custSuggest");
        if (!box || box.style.display != "block") return true;
        if (key == 40) { e.preventDefault ? e.preventDefault() : e.returnValue = false; setActiveCust(custIndex + 1); return false; }
        if (key == 38) { e.preventDefault ? e.preventDefault() : e.returnValue = false; setActiveCust(custIndex - 1); return false; }
        if (key == 13) { e.preventDefault ? e.preventDefault() : e.returnValue = false; if (custIndex < 0 && custRows.length > 0) custIndex = 0; chooseCust(custIndex); return false; }
        return true;
    }

    function loadItems() {
        var custId = s_byId("CUST_ID").value; if (custId == "") { alert("Pilih customer dulu."); s_byId("CUST_CODE").focus(); return; }
        setStatus("Loading item schedule...");
        ajaxPost("ajax_schedule_items.php", "CUST_ID=" + enc(custId), function (status, responseText) {
            if (status != 200) { alert("HTTP Error: " + status); setStatus("Load item gagal."); return; }
            try { var result = JSON.parse(responseText); if (!result.success) { alert(result.message); setStatus("Load item gagal."); return; } renderItems(result.rows); } catch (e) { alert("Response bukan JSON:\n\n" + responseText); setStatus("Load item gagal."); }
        });
    }

    function getItemRows() { return s_byId("itemBody").getElementsByTagName("tr"); }
    function getSelectedItemIndex() { var rows = getItemRows(); for (var i = 0; i < rows.length; i++) { if (rows[i] === selectedItemRow) return i; } return -1; }
    function moveItemSelection(direction) {
        var rows = getItemRows(); if (!rows || rows.length == 0) return; var index = getSelectedItemIndex();
        if (index < 0) index = 0; else index = index + direction; if (index < 0) index = 0; if (index >= rows.length) index = rows.length - 1;
        selectItemRow(rows[index]); loadSchedule();
        if (rows[index].scrollIntoView) rows[index].scrollIntoView({ block: "nearest" }); rows[index].focus();
    }
    function focusScheduleGrid() {
        var body = s_byId("scheduleBody"); var rows = body.getElementsByTagName("tr");
        if (!rows || rows.length == 0) { addScheduleRow(); rows = body.getElementsByTagName("tr"); }
        if (rows.length > 0) {
            var lastRow = rows[rows.length - 1]; selectScheduleRow(lastRow);
            var qtyInput = getScheduleInput(lastRow, "DELS_QTY"); var dateInput = getScheduleInput(lastRow, "DELS_DATE");
            if (qtyInput) { qtyInput.focus(); qtyInput.select(); } else if (dateInput) { dateInput.focus(); dateInput.select(); }
            if (lastRow.scrollIntoView) lastRow.scrollIntoView({ block: "nearest" });
        }
    }
    function itemGridKeyDown(e) {
        e = e || window.event; var key = e.keyCode || e.which;
        if (key == 40) { e.preventDefault ? e.preventDefault() : e.returnValue = false; moveItemSelection(1); return false; }
        if (key == 38) { e.preventDefault ? e.preventDefault() : e.returnValue = false; moveItemSelection(-1); return false; }
        if (key == 13) { e.preventDefault ? e.preventDefault() : e.returnValue = false; loadSchedule(); return false; }
        if (key == 9)  { e.preventDefault ? e.preventDefault() : e.returnValue = false; focusScheduleGrid(); return false; }
        return true;
    }

    function renderItems(rows) { itemRowsAll = rows || []; itemRowsCache = itemRowsAll; s_byId("ITEM_SEARCH").value = ""; renderItemGrid(itemRowsAll); setStatus("Item loaded: " + itemRowsAll.length); }
    function renderItemGrid(rows, preventFocus) {
        var body = s_byId("itemBody"); body.innerHTML = ""; selectedItemRow = null;
        s_byId("PRICE_ID").value = ""; s_byId("selectedPartInfo").innerHTML = "Belum pilih part."; s_byId("scheduleBody").innerHTML = '<tr><td colspan="2">Belum ada data.</td></tr>';
        if (!rows || rows.length == 0) { body.innerHTML = '<tr><td colspan="6">Item tidak ditemukan.</td></tr>'; return; }
        for (var i = 0; i < rows.length; i++) {
            (function (r) {
                var tr = document.createElement("tr"); tr.setAttribute("tabindex", "0"); tr.setAttribute("data-price-id", r.PRICE_ID); tr.setAttribute("data-part-code", r.ITEM_CODE); tr.setAttribute("data-part-name", r.ITEM_NAME);
                tr.innerHTML = '<td style="width:95px;">' + htmlEncode(r.ITEM_CODE) + '</td><td style="width:110px;">' + htmlEncode(r.PRICE_CODE) + '</td><td style="width:140px;">' + htmlEncode(r.ITEM_NO) + '</td><td>' + htmlEncode(r.ITEM_NAME) + '</td><td style="width:85px;" class="num">' + htmlEncode(r.DELIVERY_QTY) + '</td><td style="width:75px;" class="num">' + htmlEncode(r.BAL2) + '</td>';
                tr.onclick = function () { selectItemRow(this); loadSchedule(); this.focus(); }; tr.ondblclick = function () { selectItemRow(this); loadSchedule(); focusScheduleGrid(); }; tr.onkeydown = function (e) { return itemGridKeyDown(e); };
                body.appendChild(tr);
            })(rows[i]);
        }
        var firstRow = body.getElementsByTagName("tr")[0];
        if (firstRow && !preventFocus) { selectItemRow(firstRow); loadSchedule(); firstRow.focus(); }
    }

    function selectItemRow(row) {
        var rows = s_byId("itemBody").getElementsByTagName("tr"); for (var i = 0; i < rows.length; i++) rows[i].className = "";
        row.className = "selected"; selectedItemRow = row; s_byId("PRICE_ID").value = row.getAttribute("data-price-id");
        s_byId("selectedPartInfo").innerHTML = "PRICE_ID: " + htmlEncode(row.getAttribute("data-price-id")) + " | " + htmlEncode(row.getAttribute("data-part-code")) + " - " + htmlEncode(row.getAttribute("data-part-name"));
    }

    function hideItemSearchSuggest() { var box = s_byId("itemSearchSuggest"); if (box) { box.style.display = "none"; box.innerHTML = ""; } itemSuggestRows = []; itemSuggestIndex = -1; }
    function setActiveItemSuggest(index) {
        var box = s_byId("itemSearchSuggest"); var items = box.getElementsByClassName("autocomplete-item");
        if (!items || items.length == 0) { itemSuggestIndex = -1; return; }
        if (index < 0) index = items.length - 1; if (index >= items.length) index = 0;
        for (var i = 0; i < items.length; i++) items[i].className = "autocomplete-item";
        items[index].className = "autocomplete-item active"; itemSuggestIndex = index;
    }
    function findItemRowByPriceId(priceId) { var rows = s_byId("itemBody").getElementsByTagName("tr"); for (var i = 0; i < rows.length; i++) { if (rows[i].getAttribute("data-price-id") == priceId) return rows[i]; } return null; }
    function chooseItemSuggest(index) {
        if (index < 0 || index >= itemSuggestRows.length) return; var r = itemSuggestRows[index]; var row = findItemRowByPriceId(r.PRICE_ID); if (!row) return;
        s_byId("ITEM_SEARCH").value = r.ITEM_CODE + " - " + r.ITEM_NAME; hideItemSearchSuggest(); selectItemRow(row); loadSchedule(); row.focus();
        if (row.scrollIntoView) row.scrollIntoView({ block: "nearest" });
    }
    function showItemSearchSuggest(rows) {
        var box = s_byId("itemSearchSuggest"); box.innerHTML = ""; itemSuggestRows = rows || []; itemSuggestIndex = -1;
        if (!rows || rows.length == 0) { box.style.display = "none"; return; }
        for (var i = 0; i < rows.length; i++) {
            (function (r, idx) {
                var div = document.createElement("div"); div.className = "autocomplete-item";
                div.innerHTML = '<div class="autocomplete-main">' + htmlEncode(r.ITEM_CODE) + " - " + htmlEncode(r.ITEM_NAME) + '</div><div class="autocomplete-sub">PRCD: ' + htmlEncode(r.PRICE_CODE) + ' | PART NO: ' + htmlEncode(r.ITEM_NO) + ' | PRICE_ID: ' + htmlEncode(r.PRICE_ID) + ' | BAL: ' + htmlEncode(r.BAL2) + '</div>';
                div.onmouseover = function () { setActiveItemSuggest(idx); }; div.onclick = function () { chooseItemSuggest(idx); }; box.appendChild(div);
            })(rows[i], i);
        }
        box.style.display = "block"; setActiveItemSuggest(0);
    }
    function searchItemLocal() {
        var q = s_byId("ITEM_SEARCH").value.toLowerCase(); var result = [];
        if (q.length < 1) { hideItemSearchSuggest(); itemRowsCache = itemRowsAll; renderItemGrid(itemRowsAll, true); setStatus("Item loaded: " + itemRowsAll.length); return; }
        for (var i = 0; i < itemRowsAll.length; i++) {
            var r = itemRowsAll[i]; var text = (String(r.ITEM_CODE || "") + " " + String(r.ITEM_NAME || "") + " " + String(r.ITEM_NO || "") + " " + String(r.PRICE_CODE || "")).toLowerCase();
            if (text.indexOf(q) >= 0) result.push(r); if (result.length >= 10) break;
        }
        itemRowsCache = result; renderItemGrid(result, true); showItemSearchSuggest(result); setStatus("Hasil pencarian item: " + result.length + " dari " + itemRowsAll.length);
    }
    function itemSearchKeyDown(e) {
        e = e || window.event; var key = e.keyCode || e.which; var box = s_byId("itemSearchSuggest");
        if (!box || box.style.display != "block") { if (key == 13) { e.preventDefault ? e.preventDefault() : e.returnValue = false; searchItemLocal(); if (itemSuggestRows.length > 0) chooseItemSuggest(0); return false; } return true; }
        if (key == 40) { e.preventDefault ? e.preventDefault() : e.returnValue = false; setActiveItemSuggest(itemSuggestIndex + 1); return false; }
        if (key == 38) { e.preventDefault ? e.preventDefault() : e.returnValue = false; setActiveItemSuggest(itemSuggestIndex - 1); return false; }
        if (key == 13) { e.preventDefault ? e.preventDefault() : e.returnValue = false; if (itemSuggestIndex < 0 && itemSuggestRows.length > 0) itemSuggestIndex = 0; chooseItemSuggest(itemSuggestIndex); return false; }
        return true;
    }

    function loadSchedule() {
        var priceId = s_byId("PRICE_ID").value; if (priceId == "") return; var currentSeq = ++scheduleLoadSeq;
        ajaxPost("ajax_schedule_load.php", "PRICE_ID=" + enc(priceId), function (status, responseText) {
            if (currentSeq != scheduleLoadSeq) return; if (status != 200) { alert("HTTP Error: " + status); return; }
            try { var result = JSON.parse(responseText); if (!result.success) { alert(result.message); return; } renderSchedule(result.rows); } catch (e) { alert("Response bukan JSON:\n\n" + responseText); }
        });
    }

    function renderSchedule(rows) {
        var body = s_byId("scheduleBody"); body.innerHTML = ""; selectedScheduleRow = null;
        if (!rows || rows.length == 0) { addScheduleRow(); return; }
        for (var i = 0; i < rows.length; i++) addScheduleRow(rows[i]);
        var firstRow = body.getElementsByTagName("tr")[0]; if (firstRow) selectScheduleRow(firstRow);
    }

    function selectScheduleRow(row) {
        var rows = s_byId("scheduleBody").getElementsByTagName("tr"); for (var i = 0; i < rows.length; i++) rows[i].className = "";
        row.className = "selected"; selectedScheduleRow = row;
    }

    function getScheduleInput(row, name) { var inputs = row.getElementsByTagName("input"); for (var i = 0; i < inputs.length; i++) { if (inputs[i].getAttribute("data-name") == name) return inputs[i]; } return null; }
    function getNextScheduleDate() {
        var body = s_byId("scheduleBody"); var rows = body.getElementsByTagName("tr"); var maxValue = "";
        for (var i = 0; i < rows.length; i++) { var dateInput = getScheduleInput(rows[i], "DELS_DATE"); if (dateInput && dateInput.value != "" && dateInput.value > maxValue) maxValue = dateInput.value; }
        if (maxValue != "") { var d = new Date(maxValue + "T00:00:00"); d.setDate(d.getDate() + 1); var y = d.getFullYear(); var m = d.getMonth() + 1; var day = d.getDate(); return y + "-" + (m < 10 ? "0" + m : m) + "-" + (day < 10 ? "0" + day : day); }
        var now = new Date(); var yy = now.getFullYear(); var mm = now.getMonth() + 1; var dd = now.getDate(); return yy + "-" + (mm < 10 ? "0" + mm : mm) + "-" + (dd < 10 ? "0" + dd : dd);
    }
    function getScheduleRows() { return s_byId("scheduleBody").getElementsByTagName("tr"); }
    function getSelectedScheduleIndex() { var rows = getScheduleRows(); for (var i = 0; i < rows.length; i++) { if (rows[i] === selectedScheduleRow) return i; } return -1; }
    function moveScheduleSelection(direction, fieldName) {
        var rows = getScheduleRows(); if (!rows || rows.length == 0) { addScheduleRow(); rows = getScheduleRows(); }
        var index = getSelectedScheduleIndex(); if (index < 0) index = 0; else index = index + direction;
        if (direction < 0 && index < 0) { addScheduleRow(); rows = getScheduleRows(); index = 0; } else if (index >= rows.length) { index = rows.length - 1; }
        if (index < 0) index = 0; if (index >= rows.length) index = rows.length - 1;
        selectScheduleRow(rows[index]); var input = getScheduleInput(rows[index], fieldName); if (input) { input.focus(); input.select(); }
        if (rows[index].scrollIntoView) rows[index].scrollIntoView({ block: "nearest" });
    }

    function addScheduleRow(data) {
        data = data || {}; var body = s_byId("scheduleBody");
        if (body.getElementsByTagName("td").length == 1 && body.innerHTML.indexOf("Belum ada") >= 0) body.innerHTML = "";
        var tr = document.createElement("tr"); tr.onclick = function () { selectScheduleRow(this); };
        var dateValue = data.DELS_DATE || getNextScheduleDate(); var qtyValue = data.hasOwnProperty("DELS_QTY") ? data.DELS_QTY : "";
        tr.innerHTML = '<td style="width:140px;"><input type="date" data-name="DELS_DATE" value="' + htmlEncode(dateValue) + '"></td><td><input type="number" class="num" data-name="DELS_QTY" value="' + htmlEncode(qtyValue) + '"></td>';
        if (body.firstChild) body.insertBefore(tr, body.firstChild); else body.appendChild(tr);
        var dateInput = getScheduleInput(tr, "DELS_DATE"); var qtyInput = getScheduleInput(tr, "DELS_QTY");
        dateInput.onfocus = function () { selectScheduleRow(tr); }; qtyInput.onfocus = function () { selectScheduleRow(tr); };
        dateInput.onkeydown = function (e) {
            e = e || window.event; var key = e.keyCode || e.which;
            if (key == 13 || key == 9) { e.preventDefault ? e.preventDefault() : e.returnValue = false; qtyInput.focus(); qtyInput.select(); return false; }
            if (key == 40) { e.preventDefault ? e.preventDefault() : e.returnValue = false; moveScheduleSelection(1, "DELS_DATE"); return false; }
            if (key == 38) { e.preventDefault ? e.preventDefault() : e.returnValue = false; moveScheduleSelection(-1, "DELS_DATE"); return false; } return true;
        };
        qtyInput.onkeydown = function (e) {
            e = e || window.event; var key = e.keyCode || e.which;
            if (key == 13 || key == 9) {
                e.preventDefault ? e.preventDefault() : e.returnValue = false;
                saveSchedule(function (ok) { if (ok) { addScheduleRow(); var rows = s_byId("scheduleBody").getElementsByTagName("tr"); var firstRow = rows[0]; selectScheduleRow(firstRow); getScheduleInput(firstRow, "DELS_DATE").focus(); } }); return false;
            }
            if (key == 40) { e.preventDefault ? e.preventDefault() : e.returnValue = false; moveScheduleSelection(1, "DELS_QTY"); return false; }
            if (key == 38) { e.preventDefault ? e.preventDefault() : e.returnValue = false; moveScheduleSelection(-1, "DELS_QTY"); return false; } return true;
        };
        selectScheduleRow(tr);
    }

    function collectScheduleRows() {
        var trs = s_byId("scheduleBody").getElementsByTagName("tr"); var rows = []; var checkDate = {};
        for (var i = 0; i < trs.length; i++) {
            var dateInput = getScheduleInput(trs[i], "DELS_DATE"); var qtyInput = getScheduleInput(trs[i], "DELS_QTY");
            if (!dateInput || !qtyInput) continue;
            var dateValue = dateInput.value; var qtyText = qtyInput.value;
            if (dateValue == "") continue; if (qtyText == "") qtyText = "0";
            var qty = parseInt(qtyText, 10);
            if (isNaN(qty) || qty < 0) { alert("DELS_QTY tidak valid pada date " + dateValue); qtyInput.focus(); return false; }
            if (checkDate[dateValue]) { alert("Tanggal duplicate di grid: " + dateValue); dateInput.focus(); return false; }
            checkDate[dateValue] = true; rows.push({ DELS_DATE: dateValue, DELS_QTY: qty, DELS_C1: 0, DELS_C2: 0 });
        } return rows;
    }

    function saveSchedule(callback) {
        var priceId = s_byId("PRICE_ID").value; if (priceId == "") { alert("Pilih item dulu."); if (callback) callback(false); return; }
        var rows = collectScheduleRows(); if (rows === false) { if (callback) callback(false); return; } if (rows.length == 0) { alert("Schedule kosong."); if (callback) callback(false); return; }
        ajaxPost("ajax_schedule_save.php", "PRICE_ID=" + enc(priceId) + "&ROWS_JSON=" + enc(JSON.stringify(rows)), function (status, responseText) {
            if (status != 200) { alert("HTTP Error: " + status); if (callback) callback(false); return; }
            try { var result = JSON.parse(responseText); if (!result.success) { alert(result.message); if (callback) callback(false); return; } setStatus(result.message); if (callback) callback(true); } catch (e) { alert("Response bukan JSON:\n\n" + responseText); if (callback) callback(false); }
        });
    }

    function deleteScheduleRow() {
        if (!selectedScheduleRow) { alert("Pilih schedule yang mau dihapus."); return; }
        var priceId = s_byId("PRICE_ID").value; var dateInput = getScheduleInput(selectedScheduleRow, "DELS_DATE");
        if (!dateInput || dateInput.value == "") { selectedScheduleRow.parentNode.removeChild(selectedScheduleRow); selectedScheduleRow = null; if (s_byId("scheduleBody").getElementsByTagName("tr").length == 0) addScheduleRow(); return; }
        if (priceId == "") { alert("PRICE_ID kosong."); return; }
        if (!confirm("Hapus schedule date " + dateInput.value + "?")) return;
        ajaxPost("ajax_schedule_delete.php", "PRICE_ID=" + enc(priceId) + "&DELS_DATE=" + enc(dateInput.value), function (status, responseText) {
            if (status != 200) { alert("HTTP Error: " + status); return; }
            try { var result = JSON.parse(responseText); if (!result.success) { alert(result.message); return; } selectedScheduleRow.parentNode.removeChild(selectedScheduleRow); selectedScheduleRow = null; if (s_byId("scheduleBody").getElementsByTagName("tr").length == 0) addScheduleRow(); setStatus(result.message); } catch (e) { alert("Response bukan JSON:\n\n" + responseText); return; }
        });
    }

    s_byId("CUST_CODE").onkeydown = function (e) { return custKeyDown(e); };
    s_byId("CUST_CODE").onkeyup = function (e) { e = e || window.event; var key = e.keyCode || e.which; if (key == 13 || key == 38 || key == 40) return false; clearTimeout(custTimer); custTimer = setTimeout(function () { searchCustomer(); }, 250); };
    s_byId("CUST_CODE").onblur = function () { setTimeout(function () { hideCustSuggest(); }, 250); };
    s_byId("ITEM_SEARCH").onkeydown = function (e) { return itemSearchKeyDown(e); };
    s_byId("ITEM_SEARCH").onkeyup = function (e) { e = e || window.event; var key = e.keyCode || e.which; if (key == 13 || key == 38 || key == 40) return false; clearTimeout(itemSearchTimer); itemSearchTimer = setTimeout(function () { searchItemLocal(); }, 800); };
    s_byId("ITEM_SEARCH").onblur = function () { setTimeout(function () { hideItemSearchSuggest(); }, 250); };
    s_byId("btnRefreshItem").onclick = function () { loadItems(); };
    s_byId("btnAddSchedule").onclick = function () { addScheduleRow(); };
    s_byId("btnDeleteSchedule").onclick = function () { deleteScheduleRow(); };
    s_byId("btnSaveSchedule").onclick = function () { saveSchedule(); };
    s_byId("btnReloadSchedule").onclick = function () { loadSchedule(); };
})();
</script>

</body>
</html>