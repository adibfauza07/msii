<?php
require_once dirname(__DIR__) . "/config/db_plant2.php";
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
            z-index: 1;          /* <-- tetap rendah */
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
        .filter-table input[type="text"] {
            border: 1px solid #bbb;
            padding: 5px 8px;
            font-size: 12px;
            border-radius: 3px;
            outline: none;
        }
        .filter-table input[type="text"]:focus {
            border-color: #1a3c5e;
            box-shadow: 0 0 0 2px rgba(26,60,94,0.15);
        }
        #sup_comp { width: 280px; }
        #start_date, #end_date { width: 120px; }

        /* ── Datepicker trigger button ── */
        .dp-wrap {
            display: inline-block;
            position: relative;
        }
        .dp-btn {
            display: inline-block;
            width: 28px;
            height: 28px;
            background: #1a3c5e;
            border: 1px solid #14304d;
            border-radius: 3px;
            margin-left: 2px;
            vertical-align: middle;
            cursor: pointer;
            text-align: center;
            line-height: 26px;
            color: #fff;
            font-size: 14px;
        }
        .dp-btn:hover { background: #245a8a; }

        /* ══════════════════════════════════════════════════
           FIX UTAMA: datepicker z-index harus di atas semua
           ══════════════════════════════════════════════════ */
        .ui-datepicker {
            z-index: 99999 !important;
        }

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
            font-size: 11px;
            color: #555;
            margin-top: 4px;
        }
        .report-header .supplier-info {
            font-size: 11px;
            color: #555;
            margin-top: 2px;
        }
        .record-count {
            text-align: right;
            font-size: 11px;
            color: #888;
            margin-bottom: 8px;
        }

        /* ── Table ── */
        #report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        #report-table thead th {
            background: #1a3c5e;
            color: #fff;
            font-weight: 700;
            padding: 7px 6px;
            text-align: center;
            border: 1px solid #14304d;
            white-space: nowrap;
        }
        #report-table tbody td {
            padding: 5px 6px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        #report-table tbody tr:nth-child(even) { background: #f8f9fb; }
        #report-table tbody tr:hover { background: #eef3f8; }
        #report-table .num {
            text-align: right;
            font-family: 'Courier New', Courier, monospace;
            white-space: nowrap;
        }
        #report-table .center { text-align: center; }
        #report-table .col-no   { width: 35px; text-align: center; }
        #report-table .col-po   { width: 90px; }
        #report-table .col-date { width: 75px; text-align: center; }
        #report-table .col-code { width: 100px; }
        #report-table .col-name { min-width: 180px; }
        #report-table .col-unit { width: 40px; text-align: center; }
        #report-table .col-cur  { width: 40px; text-align: center; }
        #report-table .col-qty  { width: 85px; }

        /* ── Total Row ── */
        #report-table tfoot td {
            background: #e8edf3;
            font-weight: 700;
            padding: 7px 6px;
            border: 1px solid #bbb;
        }
        #report-table tfoot .num {
            font-family: 'Courier New', Courier, monospace;
            text-align: right;
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
            #filter-section, #loading { display: none !important; }
            #report-container {
                margin: 0 !important;
                padding: 10px 15px !important;
                border: none !important;
                box-shadow: none !important;
                max-width: 100% !important;
            }
            .report-header { border-bottom: 2px solid #000 !important; }
            .report-header h1, .report-header h2 { color: #000 !important; }
            #report-table thead th {
                background: #ddd !important;
                color: #000 !important;
                border-color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            #report-table tbody td { border-color: #000 !important; }
            #report-table tbody tr:nth-child(even),
            #report-table tbody tr:hover { background: #fff !important; }
            #report-table tfoot td {
                background: #eee !important;
                border-color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .record-count { display: none !important; }
            @page { size: landscape; margin: 1cm; }
        }
    </style>
</head>
<body>

<!-- ═══════════ FILTER ═══════════ -->
<div id="filter-section">
    <table class="filter-table">
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
                <th class="col-po">PO NUM</th>
                <th class="col-date">PO DATE</th>
                <th class="col-code">CODE</th>
                <th class="col-name">ITEM NAME</th>
                <th class="col-unit">UNIT</th>
                <th class="col-cur">CUR</th>
                <th class="col-qty">PO QTY</th>
                <th class="col-qty">RECV QTY</th>
                <th class="col-qty">SISA QTY</th>
            </tr>
        </thead>
        <tbody id="report-body"></tbody>
        <tfoot>
            <tr>
                <td colspan="7" style="text-align:right;padding-right:10px;">TOTAL</td>
                <td class="num" id="total-po-qty">0.00</td>
                <td class="num" id="total-recv-qty">0.00</td>
                <td class="num" id="total-sisa-qty">0.00</td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- ═══════════ SCRIPT ═══════════ -->
<script>
(function($) {

    var MONTHS = [
        "JANUARY","FEBRUARY","MARCH","APRIL","MAY","JUNE",
        "JULY","AUGUST","SEPTEMBER","OCTOBER","NOVEMBER","DECEMBER"
    ];

    /* ── Default: bulan berjalan ── */
    var now = new Date();
    var firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    var lastDay  = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    /* ══════════════════════════════════════════
       FIX: showOn:"button" + tombol kalender manual
       ══════════════════════════════════════════ */
    var dpOptions = {
        dateFormat: "yy-mm-dd",
        changeMonth: true,
        changeYear: true,
        showButtonPanel: false,
        showOn: "button",
        buttonText: "",
        buttonImage: ""
    };

    /* Inisialisasi datepicker START */
    $("#start_date").datepicker(dpOptions);
    /* Sembunyikan button default jQuery UI, gunakan tombol custom */
    $("#start_date").next(".ui-datepicker-trigger").hide();

    /* Inisialisasi datepicker END */
    $("#end_date").datepicker(dpOptions);
    $("#end_date").next(".ui-datepicker-trigger").hide();

    /* Tombol kalender custom membuka datepicker */
    $("#btn_dp_start").on("click", function() {
        $("#start_date").datepicker("show");
    });
    $("#btn_dp_end").on("click", function() {
        $("#end_date").datepicker("show");
    });

    /* Set nilai default */
    $("#start_date").datepicker("setDate", firstDay);
    $("#end_date").datepicker("setDate", lastDay);

    /* ── Supplier Autocomplete ── */
    $("#sup_comp").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "search_sup.php",
                type: "POST",
                data: { q: request.term },
                dataType: "json",
                success: function(data) {
                    if (!data || data.length === 0) { response([]); return; }
                    var items = [];
                    for (var i = 0; i < data.length; i++) {
                        items.push({
                            label: data[i].SUP_CODE + " - " + data[i].SUP_COMP,
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

    /* ── Format Number ── */
    function fmtNum(n) {
        var num = parseFloat(n);
        if (isNaN(num)) return "0.00";
        var parts = num.toFixed(2).split(".");
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        return parts.join(".");
    }

    /* ── Search ── */
    window.doSearch = function() {
        var startDate = $.trim($("#start_date").val());
        var endDate   = $.trim($("#end_date").val());
        var supCode   = $.trim($("#sup_code").val());
        var supComp   = $.trim($("#sup_comp").val());

        if (startDate === "" || endDate === "") {
            alert("Silakan pilih periode tanggal terlebih dahulu.");
            return;
        }

        $("#loading").show();
        $("#btn-search").prop("disabled", true);

        $.ajax({
            url: "get_po_outstanding.php",
            type: "POST",
            data: {
                start_date: startDate,
                end_date:   endDate,
                sup_code:   supCode
            },
            dataType: "json",
            success: function(resp) {
                $("#loading").hide();
                $("#btn-search").prop("disabled", false);
                if (!resp || !resp.data) {
                    showNoData(startDate, endDate, supComp);
                    return;
                }
                renderReport(resp.data, startDate, endDate, supComp);
            },
            error: function(xhr, status, err) {
                $("#loading").hide();
                $("#btn-search").prop("disabled", false);
                alert("Error: " + (err || status));
            }
        });
    };

    /* ── Render ── */
    function renderReport(data, startDate, endDate, supComp) {
        if (data.length === 0) {
            showNoData(startDate, endDate, supComp);
            return;
        }

        var sd = new Date(startDate);
        var ed = new Date(endDate);
        var monthName = MONTHS[sd.getMonth()];

        if (sd.getMonth() === ed.getMonth() && sd.getFullYear() === ed.getFullYear()) {
            $("#report-title").text("OUTSTANDING PO MONTHLY " + monthName + " " + sd.getFullYear());
        } else {
            $("#report-title").text("OUTSTANDING PO");
        }

        var sdStr = padZ(sd.getDate()) + "/" + padZ(sd.getMonth()+1) + "/" + sd.getFullYear();
        var edStr = padZ(ed.getDate()) + "/" + padZ(ed.getMonth()+1) + "/" + ed.getFullYear();
        $("#period-text").text("Period: " + sdStr + " - " + edStr);
        $("#supplier-text").text(supComp !== "" ? "Supplier: " + supComp : "Supplier: SEMUA").show();

        var html = "";
        var totalPO = 0, totalRecv = 0, totalSisa = 0;

        for (var i = 0; i < data.length; i++) {
            var r = data[i];
            var poQty   = parseFloat(r.PO_QTY)      || 0;
            var rcvQty  = parseFloat(r.RECEIVE_QTY)  || 0;
            var sisaQty = parseFloat(r.SISA_QTY)     || 0;
            totalPO   += poQty;
            totalRecv += rcvQty;
            totalSisa += sisaQty;

            html += "<tr>"
                + "<td class='center'>" + (i + 1) + "</td>"
                + "<td>" + esc(r.PO_NUM) + "</td>"
                + "<td class='center'>" + esc(r.PO_DATE) + "</td>"
                + "<td>" + esc(r.ITEM_CODE) + "</td>"
                + "<td>" + esc(r.ITEM_NAME) + "</td>"
                + "<td class='center'>" + esc(r.ITEM_UNIT) + "</td>"
                + "<td class='center'>" + esc(r.PO_CUR) + "</td>"
                + "<td class='num'>" + fmtNum(poQty) + "</td>"
                + "<td class='num'>" + fmtNum(rcvQty) + "</td>"
                + "<td class='num'>" + fmtNum(sisaQty) + "</td>"
                + "</tr>";
        }

        $("#report-body").html(html);
        $("#total-po-qty").text(fmtNum(totalPO));
        $("#total-recv-qty").text(fmtNum(totalRecv));
        $("#total-sisa-qty").text(fmtNum(totalSisa));

        $("#report-table").show();
        $("#no-data").hide();
        $("#record-count").text("Showing " + data.length + " record(s)").show();
        $("#btn-print").prop("disabled", false);
    }

    function showNoData(startDate, endDate, supComp) {
        var sd = new Date(startDate);
        var ed = new Date(endDate);
        var sdStr = padZ(sd.getDate()) + "/" + padZ(sd.getMonth()+1) + "/" + sd.getFullYear();
        var edStr = padZ(ed.getDate()) + "/" + padZ(ed.getMonth()+1) + "/" + ed.getFullYear();
        $("#report-title").text("OUTSTANDING PO MONTHLY");
        $("#period-text").text("Period: " + sdStr + " - " + edStr);
        $("#supplier-text").text(supComp !== "" ? "Supplier: " + supComp : "Supplier: SEMUA").show();
        $("#report-table").hide();
        $("#no-data").show();
        $("#record-count").hide();
        $("#btn-print").prop("disabled", true);
    }

    window.doPrint = function() { window.print(); };

    window.doClear = function() {
        $("#sup_comp").val("");
        $("#sup_code").val("");
        var n = new Date();
        $("#start_date").datepicker("setDate", new Date(n.getFullYear(), n.getMonth(), 1));
        $("#end_date").datepicker("setDate", new Date(n.getFullYear(), n.getMonth() + 1, 0));
        $("#report-table").hide();
        $("#no-data").hide();
        $("#record-count").hide();
        $("#report-title").text("OUTSTANDING PO MONTHLY");
        $("#period-text").text("");
        $("#supplier-text").text("").hide();
        $("#btn-print").prop("disabled", true);
    };

    function padZ(n) { return n < 10 ? "0" + n : "" + n; }

    function esc(s) {
        if (!s) return "";
        return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
    }

    $("#sup_comp").on("keydown", function(e) {
        if (e.keyCode === 13) { e.preventDefault(); doSearch(); }
    });

})(jQuery);
</script>

</body>
</html>