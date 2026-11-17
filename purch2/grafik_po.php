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
    <title>Grafik Performance PO - PT. IMC Tekno Indonesia</title>

    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    
    <!-- TAMBAHKAN CHART.JS UNTUK GRAFIK -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        #filter_year { width: 120px; text-align: center; } /* Dropdown Tahun */

        /* ── Buttons ── */
        .btn {
            display: inline-block;
            padding: 6px 18px;
            font-size: 12px;
            font-weight: 700;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 6px;
        }
        .btn-search { background: #1a3c5e; color: #fff; }
        .btn-search:hover { background: #245a8a; }
        .btn-print { background: #555; color: #fff; }
        .btn-print:hover { background: #333; }
        .btn-clear { background: #ccc; color: #333; }
        .btn-clear:hover { background: #aaa; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        /* ── Loading ── */
        #loading {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.7);
            z-index: 99999;
            text-align: center;
            padding-top: 120px;
        }
        #loading span {
            font-size: 16px;
            font-weight: 700;
            color: #1a3c5e;
        }

        #status-box {
            display: none;
            max-width: 1200px;
            margin: 12px auto 0;
            padding: 8px 12px;
            border: 1px solid #d59b36;
            background: #fff8df;
            color: #7a4d00;
            line-height: 1.5;
        }
        #status-box.show { display: block; }

        /* ── Report Container ── */
        #report-container {
            max-width: 1200px;
            margin: 16px auto;
            background: #fff;
            padding: 20px 24px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            min-height: 400px;
        }

        /* ── Report Header ── */
        .report-header {
            text-align: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #1a3c5e;
        }
        .report-header h1 {
            font-size: 18px;
            font-weight: 700;
            color: #1a3c5e;
            letter-spacing: 1px;
        }
        .report-header h2 {
            font-size: 14px;
            font-weight: 700;
            color: #333;
            margin-top: 2px;
        }
        .report-header .period {
            font-size: 12px;
            font-weight: bold;
            color: #1a3c5e;
            margin-top: 4px;
        }
        .report-header .supplier-info {
            font-size: 12px;
            color: #555;
            margin-top: 2px;
        }

        /* ── No Data ── */
        #no-data {
            display: none;
            text-align: center;
            padding: 60px 20px;
            color: #999;
            font-size: 14px;
        }
        #no-data .icon {
            font-size: 48px;
            margin-bottom: 12px;
            display: block;
        }

        /* ── Chart Container ── */
        .chart-wrapper {
            position: relative;
            height: 60vh;
            width: 100%;
            display: none; /* Disembunyikan sebelum search */
        }

        /* ── Autocomplete ── */
        .ui-autocomplete {
            font-size: 12px !important;
            max-height: 250px;
            overflow-y: auto;
            overflow-x: hidden;
            border-radius: 0 0 4px 4px;
            border: 1px solid #bbb;
            border-top: none;
            background: #fff;
            z-index: 99999 !important;
        }
        .ui-autocomplete .ui-menu-item {
            padding: 5px 10px;
            cursor: pointer;
        }
        .ui-autocomplete .ui-menu-item.ui-state-focus,
        .ui-autocomplete .ui-menu-item:hover {
            background: #1a3c5e;
            color: #fff;
            margin: 0;
            border: none;
        }

        /* ── Print ── */
        @media print {
            body { background: #fff !important; }
            #filter-section, #loading, #status-box { display: none !important; }
            #report-container {
                margin: 0 !important;
                padding: 10px 15px !important;
                border: none !important;
                box-shadow: none !important;
                max-width: 100% !important;
            }
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
            <td class="label">Tahun</td>
            <td>
                <select id="filter_year"></select> <!-- Generate via JS -->
            </td>
        </tr>
        <tr>
            <td></td>
            <td style="padding-top:8px;">
                <button type="button" class="btn btn-search" id="btn-search" onclick="doSearch()">&#9654; View Chart</button>
                <button type="button" class="btn btn-print" id="btn-print" onclick="window.print()" disabled>&#9113; Print</button>
                <button type="button" class="btn btn-clear" onclick="doClear()">&#10005; Clear</button>
            </td>
        </tr>
    </table>
</div>

<!-- ═══════════ LOADING ═══════════ -->
<div id="loading"><span>Memuat data grafik...</span></div>

<div id="status-box"></div>

<!-- ═══════════ REPORT CONTAINER ═══════════ -->
<div id="report-container">
    <div class="report-header">
        <h1>PT. IMC TEKNO INDONESIA</h1>
        <h2 id="report-title">YEARLY SUPPLIER PERFORMANCE (%)</h2>
        <div class="plant-info" id="plant-text"></div>
        <div class="period" id="period-text"></div>
        <div class="supplier-info" id="supplier-text"></div>
    </div>
    
    <div id="no-data">
        <span class="icon">&#9776;</span>
        Pilih supplier dan tahun, kemudian klik "View Chart".
    </div>
    
    <!-- TEMPAT GRAFIK DITAMPILKAN -->
    <div class="chart-wrapper" id="chart-wrapper">
        <canvas id="performanceChart"></canvas>
    </div>
</div>

<!-- ═══════════ SCRIPT ═══════════ -->
<script>
(function($) {

    // Variable global untuk menyimpan instance Chart.js
    var myChart = null;

    // Generate Dropdown Tahun (5 tahun ke belakang s/d 1 tahun ke depan)
    var currentYear = new Date().getFullYear();
    var yearSelect = $("#filter_year");
    for (var y = currentYear + 1; y >= currentYear - 5; y--) {
        yearSelect.append($("<option />").val(y).text(y));
    }
    yearSelect.val(currentYear); // Set default ke tahun ini

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
        minLength: 1,
        delay: 300
    });

    $("#sup_comp").on("blur", function() {
        if ($(this).val() === "") { $("#sup_code").val(""); }
    });

    $("#plant").on("change", function() {
        $("#sup_comp").val("");
        $("#sup_code").val("");
        doClearChart();
    });

    $("#sup_comp").on("input", function() {
        $("#sup_code").val("");
    });

    function plantLabel(value) {
        if (value === "p1") return "Plant 1";
        if (value === "p2") return "Plant 2";
        return "Gabungan P1 & P2";
    }

    function doClearChart() {
        if (myChart != null) {
            myChart.destroy();
        }
        $("#chart-wrapper").hide();
        $("#no-data").show().html('<span class="icon">&#9776;</span>Pilih supplier dan tahun, kemudian klik "View Chart".');
        $("#btn-print").prop("disabled", true);
        $("#plant-text").text("");
        $("#period-text").text("");
        $("#supplier-text").text("");
    }

    /* ── Search ── */
    window.doSearch = function() {
        var year          = $("#filter_year").val();
        var supCode       = $.trim($("#sup_code").val());
        var supComp       = $.trim($("#sup_comp").val());
        var selectedPlant = $("#plant").val();

        if (supCode === "" && supComp === "") {
            alert("Sangat disarankan memilih Supplier spesifik untuk melihat performa.");
        }

        $("#loading").show();
        $("#btn-search").prop("disabled", true);

        $.ajax({
            // URL INI HARUS DIBUAT BARU DI BACKEND UNTUK GROUPING PER BULAN
            url: "get_po_performance_yearly.php", 
            type: "POST",
            data: {
                plant:    selectedPlant,
                year:     year,
                sup_code: supCode
            },
            dataType: "json",
            success: function(resp) {
                $("#loading").hide();
                $("#btn-search").prop("disabled", false);
                
                if (!resp || resp.status !== "success") {
                    alert(resp && resp.message ? resp.message : "Respons server tidak valid.");
                    return;
                }
                renderChart(resp.data || [], year, supComp, selectedPlant);
            },
            error: function(xhr, status, err) {
                $("#loading").hide();
                $("#btn-search").prop("disabled", false);
                alert("Error: " + (err || status));
            }
        });
    };

    /* ── Render Chart ── */
    function renderChart(data, year, supComp, plant) {
        // Hancurkan chart lama jika ada
        if (myChart != null) {
            myChart.destroy();
        }

        $("#plant-text").text("Plant: " + plantLabel(plant));
        $("#period-text").text("TAHUN: " + year);
        $("#supplier-text").text(supComp !== "" ? "Supplier: " + supComp : "Supplier: SEMUA").show();

        if (data.length === 0) {
            $("#chart-wrapper").hide();
            $("#no-data").show().html('<span class="icon">&#9776;</span>Tidak ada transaksi pada tahun ini.');
            $("#btn-print").prop("disabled", true);
            return;
        }

        // Siapkan array data 12 bulan (Index 0 = Jan, Index 11 = Dec)
        var labels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        var percentData = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        var tooltipDetails = Array(12).fill({po: 0, recv: 0});

        // Mapping response dari DB ke dalam array bulan
        // Asumsi data dari backend: { month: 1, po_qty: 1000, recv_qty: 800 }
        for (var i = 0; i < data.length; i++) {
            var row = data[i];
            var monthIndex = parseInt(row.month) - 1; // Jika backend mengirim angka bulan 1-12
            
            var poQty = parseFloat(row.po_qty) || 0;
            var recvQty = parseFloat(row.recv_qty) || 0;
            
            var pct = 0;
            if (poQty > 0) {
                pct = ((recvQty / poQty) * 100).toFixed(2);
            }

            if (monthIndex >= 0 && monthIndex <= 11) {
                percentData[monthIndex] = parseFloat(pct);
                tooltipDetails[monthIndex] = { po: poQty, recv: recvQty };
            }
        }

        $("#no-data").hide();
        $("#chart-wrapper").show();
        $("#btn-print").prop("disabled", false);

        var ctx = document.getElementById('performanceChart').getContext('2d');
        myChart = new Chart(ctx, {
            type: 'bar', // Bisa diganti 'line'
            data: {
                labels: labels,
                datasets: [{
                    label: '% Receive Performance',
                    data: percentData,
                    backgroundColor: 'rgba(26, 60, 94, 0.7)',
                    borderColor: 'rgba(26, 60, 94, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var idx = context.dataIndex;
                                var val = context.parsed.y;
                                var detail = tooltipDetails[idx];
                                return [
                                    'Performance: ' + val + '%',
                                    'Total PO: ' + detail.po.toLocaleString(),
                                    'Total Receive: ' + detail.recv.toLocaleString()
                                ];
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100, // Karena ini persentase
                        title: {
                            display: true,
                            text: 'Persentase (%)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Bulan'
                        }
                    }
                }
            }
        });
    }

    window.doClear = function() {
        $("#plant").val("all");
        $("#sup_comp").val("");
        $("#sup_code").val("");
        $("#filter_year").val(currentYear);
        doClearChart();
    };

    $("#sup_comp").on("keydown", function(e) {
        if (e.keyCode === 13) { e.preventDefault(); doSearch(); }
    });

})(jQuery);
</script>

</body>
</html>