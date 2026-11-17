<?php
if (session_id() === '') {
    session_start();
}

require_once dirname(__DIR__) . "/config/global.php";

if (!isset($_SESSION['db_user']) || trim((string)$_SESSION['db_user']) === '') {
    die('<div style="padding:24px;color:#b91c1c;background:#fff;font-family:Arial,sans-serif;">Silakan login terlebih dahulu.</div>');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outstanding PO Monthly - PT. IMC Tekno Indonesia</title>

    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    
    <!-- LIBRARY CHART.JS -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- PLUGIN DATALABELS UNTUK ANGKA DI ATAS BAR -->
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            background: #f0f0f0;
            color: #222;
        }

        /* ── Filter Section ── */
        #filter-section {
            background: #fff;
            border-bottom: 2px solid #1a3c5e;
            padding: 14px 20px;
            position: relative;
            z-index: 1; 
        }
        .filter-table { border-collapse: collapse; }
        .filter-table td {
            padding: 4px 8px;
            vertical-align: middle;
        }
        .filter-table td.label {
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
            padding-right: 6px;
            color: #1a3c5e;
        }
        .filter-table input[type="text"],
        .filter-table select {
            border: 1px solid #bbb;
            padding: 5px 8px;
            font-size: 12px;
            border-radius: 3px;
            outline: none;
        }
        .filter-table input[type="text"]:focus,
        .filter-table select:focus {
            border-color: #1a3c5e;
            box-shadow: 0 0 0 2px rgba(26,60,94,0.15);
        }
        #plant { width: 190px; }
        #sup_comp { width: 280px; }
        #start_date, #end_date { width: 120px; }

        /* ── Datepicker trigger button ── */
        .dp-wrap { display: inline-block; position: relative; }
        .dp-btn {
            display: inline-block; width: 28px; height: 28px; background: #1a3c5e;
            border: 1px solid #14304d; border-radius: 3px; margin-left: 2px;
            vertical-align: middle; cursor: pointer; text-align: center;
            line-height: 26px; color: #fff; font-size: 14px;
        }
        .dp-btn:hover { background: #245a8a; }
        .ui-datepicker { z-index: 99999 !important; }

        /* ── Buttons ── */
        .btn {
            display: inline-block; padding: 6px 18px; font-size: 12px;
            font-weight: 700; border: none; border-radius: 4px;
            cursor: pointer; margin-right: 6px;
        }
        .btn-search { background: #1a3c5e; color: #fff; }
        .btn-search:hover { background: #245a8a; }
        .btn-print { background: #555; color: #fff; }
        .btn-print:hover { background: #333; }
        .btn-clear { background: #ccc; color: #333; }
        .btn-clear:hover { background: #aaa; }
        .btn-chart { background: #059669; color: #fff; } /* Warna Hijau Emerald */
        .btn-chart:hover { background: #047857; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        /* ── Loading ── */
        #loading {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.7); z-index: 99999;
            text-align: center; padding-top: 120px;
        }
        #loading span { font-size: 16px; font-weight: 700; color: #1a3c5e; }

        /* ── Modal Chart ── */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); z-index: 100000; align-items: center; justify-content: center;
        }
        .modal-content {
            background: #fff; width: 90%; max-width: 900px; border-radius: 8px;
            padding: 20px 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.3); position: relative;
        }
        .close-btn {
            position: absolute; top: 12px; right: 18px; font-size: 26px;
            cursor: pointer; color: #666; font-weight: bold; line-height: 1;
        }
        .close-btn:hover { color: #d32f2f; }
        .chart-container { height: 450px; width: 100%; margin-top: 15px; }

        /* ── Report Container & Table ── */
        #report-container {
            max-width: 1200px; margin: 16px auto; background: #fff; padding: 20px 24px;
            border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); min-height: 400px;
        }
        .report-header { text-align: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #1a3c5e; }
        .report-header h1 { font-size: 18px; font-weight: 700; color: #1a3c5e; letter-spacing: 1px; }
        .report-header h2 { font-size: 14px; font-weight: 700; color: #333; margin-top: 2px; }
        .report-header .period { font-size: 11px; color: #555; margin-top: 4px; }
        .report-header .supplier-info { font-size: 11px; color: #555; margin-top: 2px; }
        .record-count { text-align: right; font-size: 11px; color: #888; margin-bottom: 8px; }

        #report-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        #report-table thead th { background: #1a3c5e; color: #fff; font-weight: 700; padding: 7px 6px; text-align: center; border: 1px solid #14304d; white-space: nowrap; }
        #report-table tbody td { padding: 5px 6px; border: 1px solid #ddd; vertical-align: top; }
        #report-table tbody tr:nth-child(even) { background: #f8f9fb; }
        #report-table tbody tr:hover { background: #eef3f8; }
        #report-table .num { text-align: right; font-family: 'Courier New', Courier, monospace; white-space: nowrap; }
        #report-table .center { text-align: center; }
        #report-table tfoot td { background: #e8edf3; font-weight: 700; padding: 7px 6px; border: 1px solid #bbb; }
        #report-table tfoot .num { font-family: 'Courier New', Courier, monospace; text-align: right; }

        #no-data { display: none; text-align: center; padding: 60px 20px; color: #999; font-size: 14px; }
        #no-data .icon { font-size: 48px; margin-bottom: 12px; display: block; }

        /* ── Autocomplete ── */
        .ui-autocomplete { font-size: 12px !important; max-height: 250px; overflow-y: auto; overflow-x: hidden; border-radius: 0 0 4px 4px; border: 1px solid #bbb; border-top: none; background: #fff; z-index: 99999 !important; }
        .ui-autocomplete .ui-menu-item { padding: 5px 10px; cursor: pointer; }
        .ui-autocomplete .ui-menu-item:hover { background: #1a3c5e; color: #fff; }

        @media print {
            body { background: #fff !important; }
            #filter-section, #loading, .modal-overlay { display: none !important; }
            #report-container { margin: 0 !important; padding: 10px 15px !important; border: none !important; box-shadow: none !important; max-width: 100% !important; }
            @page { size: landscape; margin: 1cm; }
        }
    </style>
</head>
<body>

<!-- ═══════════ FILTER ═══════════ -->
<div id="filter-section">
    <table class="filter-table">
        <tr>
            <td class="label">Plant</td>
            <td>
                <select id="plant">
                    <option value="all">Gabungan P1 &amp; P2</option>
                    <option value="p1">Plant 1</option>
                    <option value="p2">Plant 2</option>
                </select>
            </td>
        </tr>
        <tr>
            <td class="label">Supplier</td>
            <td>
                <input type="text" id="sup_comp" placeholder="Ketik kode / nama supplier...">
                <input type="hidden" id="sup_code" value="">
                <span style="font-size:10px;color:#888;margin-left:6px;">* Kosongkan = Semua Supplier</span>
            </td>
        </tr>
        <tr>
            <td class="label">Periode</td>
            <td>
                <div class="dp-wrap">
                    <input type="text" id="start_date" placeholder="Dari">
                    <span class="dp-btn" id="btn_dp_start">&#128197;</span>
                </div>
                <span style="margin:0 6px;color:#888;">s/d</span>
                <div class="dp-wrap">
                    <input type="text" id="end_date" placeholder="Sampai">
                    <span class="dp-btn" id="btn_dp_end">&#128197;</span>
                </div>
            </td>
        </tr>
        <tr>
            <td></td>
            <td style="padding-top:8px;">
                <button type="button" class="btn btn-search" id="btn-search" onclick="doSearch()">&#9654; Search</button>
                <button type="button" class="btn btn-print" id="btn-print" onclick="doPrint()" disabled>&#9113; Print</button>
                <button type="button" class="btn btn-clear" onclick="doClear()">&#10005; Clear</button>
                
                <!-- TOMBOL CHART BARU -->
                <button type="button" class="btn btn-chart" onclick="openChartModal()">&#128202; Grafik Tahunan</button>
            </td>
        </tr>
    </table>
</div>

<!-- ═══════════ LOADING ═══════════ -->
<div id="loading"><span>Memuat data...</span></div>

<!-- ═══════════ REPORT ═══════════ -->
<div id="report-container">
    <div class="report-header">
        <h1>PT. IMC TEKNO INDONESIA</h1>
        <h2 id="report-title">OUTSTANDING PO MONTHLY</h2>
        <div class="plant-info" id="plant-text"></div>
        <div class="period" id="period-text"></div>
        <div class="supplier-info" id="supplier-text"></div>
    </div>
    <div class="record-count" id="record-count" style="display:none;"></div>
    <div id="no-data">
        <span class="icon">&#9776;</span>
        Tidak ada data outstanding PO untuk periode ini.
    </div>
    <table id="report-table" style="display:none;">
        <thead>
            <tr>
                <th class="col-no">NO</th>
                <th style="width:48px;">PLANT</th>
                <th class="col-po">PO NUM</th>
                <th class="col-date">PO DATE</th>
                <th class="col-code">CODE</th>
                <th class="col-name">ITEM NAME</th>
                <th class="col-unit">UNIT</th>
                <th class="col-cur">CUR</th>
                <th class="col-qty">PO QTY</th>
                <th class="col-qty">RECV QTY</th>
                <th class="col-qty" style="width:65px;">% RECV</th>
                <th class="col-qty">SISA QTY</th>
            </tr>
        </thead>
        <tbody id="report-body"></tbody>
        <tfoot>
            <tr>
                <td colspan="8" style="text-align:right;padding-right:10px;">TOTAL</td>
                <td class="num" id="total-po-qty">0.00</td>
                <td class="num" id="total-recv-qty">0.00</td>
                <td class="num" id="total-pct-recv">0.00 %</td>
                <td class="num" id="total-sisa-qty">0.00</td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- ═══════════ MODAL CHART ═══════════ -->
<div id="modal-chart" class="modal-overlay">
    <div class="modal-content">
        <span class="close-btn" onclick="closeChartModal()">&times;</span>
        <h2 style="color:#1a3c5e; font-size:16px; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            &#128202; Performance % Filter By Supplier (Tahunan)
        </h2>
        
        <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px;">
            <label style="font-weight: bold; color: #333;">Pilih Tahun:</label>
            <select id="chart_year" style="padding: 6px; border-radius: 4px; border: 1px solid #bbb; font-size:12px;"></select>
            <button type="button" class="btn btn-search" onclick="loadChartData()">Tampilkan</button>
            <span id="chart-sup-label" style="margin-left: 15px; font-weight: bold; color: #059669;"></span>
            
            <!-- LABEL RATA-RATA TAHUNAN -->
            <span id="chart-avg-label" style="margin-left: 15px; font-weight: bold; color: #d32f2f; font-size: 14px;"></span>
        </div>

        <div class="chart-container">
            <canvas id="performanceChart"></canvas>
        </div>
    </div>
</div>

<!-- ═══════════ SCRIPT ═══════════ -->
<script>
(function($) {

    var MONTHS = ["JANUARY","FEBRUARY","MARCH","APRIL","MAY","JUNE","JULY","AUGUST","SEPTEMBER","OCTOBER","NOVEMBER","DECEMBER"];
    var now = new Date();
    var firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    var lastDay  = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    var dpOptions = { dateFormat: "yy-mm-dd", changeMonth: true, changeYear: true, showButtonPanel: false, showOn: "button", buttonText: "", buttonImage: "" };

    $("#start_date").datepicker(dpOptions).next(".ui-datepicker-trigger").hide();
    $("#end_date").datepicker(dpOptions).next(".ui-datepicker-trigger").hide();

    $("#btn_dp_start").on("click", function() { $("#start_date").datepicker("show"); });
    $("#btn_dp_end").on("click", function() { $("#end_date").datepicker("show"); });

    $("#start_date").datepicker("setDate", firstDay);
    $("#end_date").datepicker("setDate", lastDay);

    /* ── Supplier Autocomplete ── */
    $("#sup_comp").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "search_sup_outstanding_multi.php",
                type: "POST",
                data: { q: request.term, plant: $("#plant").val() },
                dataType: "json",
                success: function(data) {
                    if (!data || data.length === 0) { response([]); return; }
                    var items = [];
                    for (var i = 0; i < data.length; i++) {
                        items.push({
                            label: "[" + (data[i].PLANT_SHORT || "") + "] " + data[i].SUP_CODE + " - " + data[i].SUP_COMP,
                            value: data[i].SUP_COMP,
                            code:  data[i].SUP_CODE
                        });
                    }
                    response(items);
                },
                error: function() { response([]); }
            });
        },
        select: function(event, ui) {
            $("#sup_code").val(ui.item.code);
            $("#sup_comp").val(ui.item.value);
            return false;
        },
        minLength: 1, delay: 300
    });

    $("#sup_comp").on("blur", function() { if ($(this).val() === "") { $("#sup_code").val(""); } });

    $("#plant").on("change", function() {
        $("#sup_comp").val(""); $("#sup_code").val("");
        $("#report-table, #no-data, #record-count").hide();
        $("#btn-print").prop("disabled", true);
        $("#plant-text").text("Plant: " + plantLabel($(this).val()));
        $("#supplier-text").text("").hide();
    });

    $("#sup_comp").on("input", function() { $("#sup_code").val(""); });

    function fmtNum(n) {
        var num = parseFloat(n);
        if (isNaN(num)) return "0.00";
        var parts = num.toFixed(2).split(".");
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        return parts.join(".");
    }
    function padZ(n) { return n < 10 ? "0" + n : "" + n; }
    function esc(s) {
        if (s === null || typeof s === "undefined") return "";
        return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
    }
    function plantLabel(value) {
        if (value === "p1") return "Plant 1";
        if (value === "p2") return "Plant 2";
        return "Gabungan P1 & P2";
    }

    /* ── Search Laporan Utama (Table) ── */
    window.doSearch = function() {
        var startDate = $.trim($("#start_date").val());
        var endDate   = $.trim($("#end_date").val());
        var supCode       = $.trim($("#sup_code").val());
        var supComp       = $.trim($("#sup_comp").val());
        var selectedPlant = $("#plant").val();

        if (startDate === "" || endDate === "") {
            alert("Silakan pilih periode tanggal terlebih dahulu.");
            return;
        }

        $("#loading").show(); $("#btn-search").prop("disabled", true);

        $.ajax({
            url: "get_po_outstanding_multi.php",
            type: "POST",
            data: { plant: selectedPlant, start_date: startDate, end_date: endDate, sup_code: supCode },
            dataType: "json",
            success: function(resp) {
                $("#loading").hide(); $("#btn-search").prop("disabled", false);
                if (!resp || resp.status !== "success") { alert(resp && resp.message ? resp.message : "Respons server tidak valid."); return; }
                renderReport(resp.data || [], startDate, endDate, supComp, selectedPlant);
            },
            error: function(xhr, status, err) {
                $("#loading").hide(); $("#btn-search").prop("disabled", false);
                alert("Error: " + (err || status));
            }
        });
    };

    function renderReport(data, startDate, endDate, supComp, plant) {
        if (data.length === 0) { showNoData(startDate, endDate, supComp, plant); return; }

        var sd = new Date(startDate), ed = new Date(endDate);
        if (sd.getMonth() === ed.getMonth() && sd.getFullYear() === ed.getFullYear()) {
            $("#report-title").text("OUTSTANDING PO MONTHLY " + MONTHS[sd.getMonth()] + " " + sd.getFullYear());
        } else {
            $("#report-title").text("OUTSTANDING PO");
        }

        $("#plant-text").text("Plant: " + plantLabel(plant));
        $("#period-text").text("Period: " + padZ(sd.getDate()) + "/" + padZ(sd.getMonth()+1) + "/" + sd.getFullYear() + " - " + padZ(ed.getDate()) + "/" + padZ(ed.getMonth()+1) + "/" + ed.getFullYear());
        $("#supplier-text").text(supComp !== "" ? "Supplier: " + supComp : "Supplier: SEMUA").show();

        var html = "", totalPO = 0, totalRecv = 0, totalSisa = 0;

        for (var i = 0; i < data.length; i++) {
            var r = data[i];
            var poQty   = parseFloat(r.PO_QTY) || 0;
            var rcvQty  = parseFloat(r.RECEIVE_QTY !== undefined ? r.RECEIVE_QTY : (r.RECV_QTY !== undefined ? r.RECV_QTY : (r.RCV_QTY !== undefined ? r.RCV_QTY : 0))) || 0;
            var sisaQty = parseFloat(r.SISA_QTY) || 0;
            var pctRecv = (poQty > 0) ? ((rcvQty / poQty) * 100) : 0;

            totalPO += poQty; totalRecv += rcvQty; totalSisa += sisaQty;

            html += "<tr><td class='center'>" + (i + 1) + "</td><td class='center' style='font-weight:bold;color:#6d28d9;'>" + esc(r.PLANT_SHORT) + "</td><td>" + esc(r.PO_NUM) + "</td><td class='center'>" + esc(r.PO_DATE) + "</td><td>" + esc(r.ITEM_CODE) + "</td><td>" + esc(r.ITEM_NAME) + "</td><td class='center'>" + esc(r.ITEM_UNIT) + "</td><td class='center'>" + esc(r.PO_CUR) + "</td><td class='num'>" + fmtNum(poQty) + "</td><td class='num' style='color:green;'>" + fmtNum(rcvQty) + "</td><td class='num'>" + fmtNum(pctRecv) + " %</td><td class='num' style='color:red;'>" + fmtNum(sisaQty) + "</td></tr>";
        }

        var totalPct = (totalPO > 0) ? ((totalRecv / totalPO) * 100) : 0;
        $("#report-body").html(html);
        $("#total-po-qty").text(fmtNum(totalPO)); $("#total-recv-qty").text(fmtNum(totalRecv)); $("#total-pct-recv").text(fmtNum(totalPct) + " %"); $("#total-sisa-qty").text(fmtNum(totalSisa));

        $("#report-table, #record-count").show(); $("#no-data").hide();
        $("#record-count").text("Showing " + data.length + " record(s)");
        $("#btn-print").prop("disabled", false);
    }

    function showNoData(startDate, endDate, supComp, plant) {
        var sd = new Date(startDate), ed = new Date(endDate);
        $("#report-title").text("OUTSTANDING PO MONTHLY");
        $("#plant-text").text("Plant: " + plantLabel(plant));
        $("#period-text").text("Period: " + padZ(sd.getDate()) + "/" + padZ(sd.getMonth()+1) + "/" + sd.getFullYear() + " - " + padZ(ed.getDate()) + "/" + padZ(ed.getMonth()+1) + "/" + ed.getFullYear());
        $("#supplier-text").text(supComp !== "" ? "Supplier: " + supComp : "Supplier: SEMUA").show();
        $("#report-table, #record-count").hide(); $("#no-data").show(); $("#btn-print").prop("disabled", true);
    }

    window.doPrint = function() { window.print(); };

    window.doClear = function() {
        $("#plant").val("all"); $("#sup_comp").val(""); $("#sup_code").val("");
        $("#start_date").datepicker("setDate", new Date(now.getFullYear(), now.getMonth(), 1));
        $("#end_date").datepicker("setDate", new Date(now.getFullYear(), now.getMonth() + 1, 0));
        $("#report-table, #no-data, #record-count").hide();
        $("#report-title").text("OUTSTANDING PO MONTHLY");
        $("#plant-text, #period-text").text(""); $("#supplier-text").text("").hide();
        $("#btn-print").prop("disabled", true);
    };

    /* =======================================================
       LOGIK & FUNGSI MODAL CHART PERFORMANCE TAHUNAN
       ======================================================= */
    var myChart = null;

    window.openChartModal = function() {
        var supCode = $.trim($("#sup_code").val());
        var supComp = $.trim($("#sup_comp").val());

        if (supCode === "") {
            alert("Silakan pilih / ketik Supplier terlebih dahulu untuk melihat grafik performance tahunannya.");
            return;
        }

        // Isi Option Dropdown Tahun jika kosong
        if ($('#chart_year option').length === 0) {
            var currYear = new Date().getFullYear();
            for (var y = currYear + 1; y >= currYear - 5; y--) {
                $('#chart_year').append($("<option />").val(y).text(y));
            }
            $('#chart_year').val(currYear);
        }

        $("#chart-sup-label").text(supComp);
        $("#modal-chart").css("display", "flex");
        
        // Langsung load data begitu modal terbuka
        loadChartData();
    };

    window.closeChartModal = function() {
        $("#modal-chart").hide();
    };

    window.loadChartData = function() {
        var year    = $("#chart_year").val();
        var supCode = $.trim($("#sup_code").val());
        var plant   = $("#plant").val();

        $("#loading").show();

        $.ajax({
            url: "get_po_performance_yearly.php", 
            type: "POST",
            data: { plant: plant, year: year, sup_code: supCode },
            dataType: "json",
            success: function(resp) {
                $("#loading").hide();
                if (!resp || resp.status !== "success") {
                    alert(resp && resp.message ? resp.message : "Gagal memuat data grafik.");
                    return;
                }
                renderChart(resp.data || [], year);
            },
            error: function(xhr, status, err) {
                $("#loading").hide();
                alert("Error: " + (err || status));
            }
        });
    };

    function renderChart(data, year) {
        if (myChart != null) { myChart.destroy(); }

        var labels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        var percentData = [null, null, null, null, null, null, null, null, null, null, null, null];
        var tooltipDetails = Array(12).fill({po: 0, recv: 0});
        
        var yearlyPO = 0;
        var yearlyRecv = 0;

        for (var i = 0; i < data.length; i++) {
            var row = data[i];
            var monthIndex = parseInt(row.month) - 1; 
            
            var poQty = parseFloat(row.po_qty) || 0;
            var recvQty = parseFloat(row.recv_qty) || 0;
            var pct = null;
            
            if (poQty > 0) {
                pct = ((recvQty / poQty) * 100).toFixed(2);
                yearlyPO += poQty;
                yearlyRecv += recvQty;
            }

            if (monthIndex >= 0 && monthIndex <= 11) {
                percentData[monthIndex] = pct !== null ? parseFloat(pct) : null;
                tooltipDetails[monthIndex] = { po: poQty, recv: recvQty };
            }
        }

        var yearlyAvg = 0;
        if (yearlyPO > 0) {
            yearlyAvg = ((yearlyRecv / yearlyPO) * 100).toFixed(2);
        }
        $("#chart-avg-label").text(" | Rata-rata Tahunan: " + yearlyAvg + "%");

        // Daftarkan plugin ChartDataLabels secara global sebelum init
        Chart.register(ChartDataLabels);

        var ctx = document.getElementById('performanceChart').getContext('2d');
        myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        type: 'line',
                        label: 'Trend %',
                        data: percentData,
                        borderColor: 'red',
                        borderWidth: 2,
                        fill: false,
                        tension: 0,
                        pointBackgroundColor: 'red',
                        spanGaps: true,
                        datalabels: { display: false } // Jangan tampilkan angka dobel di garis
                    },
                    {
                        type: 'bar',
                        label: '% Receive vs PO (' + year + ')',
                        data: percentData,
                        backgroundColor: 'rgba(5, 150, 105, 0.8)',
                        borderColor: '#047857',
                        borderWidth: 1,
                        borderRadius: 4,
                        datalabels: {
                            display: true,
                            color: '#222',
                            anchor: 'end',
                            align: 'top',
                            font: { weight: 'bold', size: 11 },
                            formatter: function(value) {
                                return value !== null ? value + '%' : '';
                            }
                        }
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: { top: 25 } // Jarak agar angka tidak terpotong
                },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var idx = context.dataIndex;
                                var val = context.parsed.y;
                                var detail = tooltipDetails[idx];
                                return [
                                    'Performance: ' + (val !== null ? val : 0) + '%',
                                    'Total PO Qty: ' + detail.po.toLocaleString(),
                                    'Total Rcv Qty: ' + detail.recv.toLocaleString()
                                ];
                            }
                        }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        max: 110, // Max scale dilebihkan untuk ruang teks angka
                        title: { display: true, text: 'Persentase (%)' } 
                    }
                }
            }
        });
    }

})(jQuery);
</script>

</body>
</html>