<?php
// report_ovh_per_machine.php
// Kompatibilitas: PHP 5.4 & SQL Server 2008
if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}

$configPath = __DIR__ . "/../config/global.php";
if (file_exists($configPath)) { 
    require_once $configPath; 
}

$currentYear = (int)date('Y');
$currentMonth = (int)date('n');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Report Jadwal & Aktual OVH Per Mesin - Molding</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; background-color: #f4f6f9; margin: 0; padding: 15px; }
        .panel { background-color: #fff; border: 1px solid #d1d5db; border-radius: 4px; padding: 15px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .panel-title { font-weight: bold; color: #0056b3; margin-bottom: 15px; border-bottom: 2px solid #0056b3; padding-bottom: 6px; font-size: 14px; }
        
        .form-group { display: inline-block; margin-right: 15px; vertical-align: middle; }
        label { font-weight: bold; margin-right: 5px; color: #333; }
        select, input[type="text"] { padding: 4px 8px; border: 1px solid #ccc; border-radius: 3px; font-size: 12px; height: 28px; outline: none; box-sizing: border-box; }
        button { padding: 4px 15px; font-weight: bold; cursor: pointer; border: none; border-radius: 3px; height: 28px; color: #fff; background-color: #0056b3; transition: background 0.2s;}
        button:hover { background-color: #004494; }
        .btn-print { background-color: #28a745; }
        .btn-print:hover { background-color: #218838; }

        .table-container { width: 100%; overflow: auto; max-height: 65vh; border: 1px solid #bbb; background-color: #fff; }
        table.report-table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        table.report-table th, table.report-table td { border: 1px solid #ccc; padding: 6px 8px; box-sizing: border-box; }
        table.report-table th { background-color: #e2e8f0; font-weight: bold; text-align: center; position: sticky; top: 0; z-index: 10; }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .group-header { background-color: #1e293b !important; color: #fff !important; font-weight: bold; text-align: left !important; font-size: 12px; }
        .row-plan td:not(.fix-mc):not(.fix-item) { color: #0284c7; }
        .row-act td:not(.fix-mc):not(.fix-item) { color: #166534; background-color: #f0fdf4; }
        .badge-plan { background-color: #fef08a; color: #b45309; padding: 2px 5px; border-radius: 3px; font-weight: bold; border: 1px solid #eab308; display: inline-block; font-size: 10px; }
        .badge-act { background-color: #d4edda; color: #155724; border: 1px solid #28a745; padding: 2px 6px; border-radius: 3px; font-weight: bold; font-size: 10px; }

        /* CSS KHUSUS CETAK / PRINT PDF */
        @media print {
            body { background-color: #fff; padding: 0; font-size: 10px; }
            .panel { display: none !important; }
            .table-container { 
                max-height: none !important; 
                overflow: visible !important; 
                border: none !important; 
                box-shadow: none !important; 
                width: 100% !important;
            }
            table.report-table { 
                width: 100% !important; 
                page-break-inside: auto; 
            }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
            @page { 
                size: landscape; 
                margin: 10mm; 
            }
        }
    </style>
</head>
<body>

<div class="panel">
    <div class="panel-title"><i class="fa fa-file-text-o"></i> Laporan Rekapitulasi Plan & Tanggal Aktual OVH (Berdasarkan Centang Matrix)</div>
    
    <div class="form-group">
        <label for="cbTahun">Tahun:</label>
        <select id="cbTahun">
            <?php for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++) {
                $sel = ($y === $currentYear) ? 'selected' : '';
                echo "<option value=\"$y\" $sel>$y</option>";
            } ?>
        </select>
    </div>
    
    <div class="form-group">
        <label for="cbBulan">Bulan:</label>
        <select id="cbBulan">
            <?php 
            $namaBulan = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            for ($m = 1; $m <= 12; $m++) {
                $sel = ($m === $currentMonth) ? 'selected' : '';
                echo "<option value=\"$m\" $sel>" . $namaBulan[$m-1] . " ($m)</option>";
            } ?>
        </select>
    </div>
    
    <button id="btnLoadReport"><i class="fa fa-search"></i> Generate Report</button>
    <button type="button" class="btn-print" onclick="window.print();"><i class="fa fa-print"></i> Print / PDF</button>
</div>

<div class="table-container">
    <table class="report-table" id="tblReportOvh">
        <thead>
            <tr>
                <th width="4%">No</th>
                <th width="7%">Mesin</th>
                <th width="22%">Item / Mold Detail</th>
                <th width="8%" class="text-right">Last Shoot</th>
                <th width="8%">Tipe</th>
                <th width="9%" class="text-right">Total Bulan Ini</th>
                <th width="9%" class="text-right">Akumulasi Akhir</th>
                <th width="23%">Tanggal Target Plan vs Tanggal Aktual (Centang)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="8" class="text-center" style="padding: 40px; color: #666;">
                    Silakan pilih Periode Tahun & Bulan, lalu klik <b>Generate Report</b>.
                </td>
            </tr>
        </tbody>
    </table>
</div>

<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
$(document).ready(function() {
    function formatNumber(num) {
        if(num === 0 || num === "0" || num === null || isNaN(num)) return "0";
        return parseFloat(num).toLocaleString('id-ID');
    }

    $('#btnLoadReport').click(function() {
        var btn = $(this);
        var thn = $('#cbTahun').val();
        var bln = $('#cbBulan').val();
        var tbody = $('#tblReportOvh tbody');
        var stdLimit = 25000;
        var namaBulanText = $('#cbBulan option:selected').text().split(' ')[0];

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');
        tbody.html('<tr><td colspan="8" class="text-center" style="padding: 40px;"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Sedang merekap data Report Plan & Aktual...</td></tr>');

        $.ajax({
            url: 'ajax_get_matrix_ovh.php', 
            type: 'POST', 
            dataType: 'json',
            data: { tahun: thn, bulan: bln, cust_code: '' },
            success: function(res) {
                if(res.status === 'success') {
                    var html = '';
                    var hasData = false;

                    $.each(res.data, function(custName, items) {
                        var customerRowsHtml = '';
                        var custItemNo = 1;

                        $.each(items, function(itemKey, row) {
                            var initialLastShoot = parseFloat(row.last_shoot) || 0;
                            var sumPlanOvh = 0, sumActOvh = 0;
                            var targetDatePlan = '-';
                            var checkedActualDates = [];
                            
                            var cumulativePlan = initialLastShoot;
                            var nextTargetPlan = (Math.floor(initialLastShoot / stdLimit) + 1) * stdLimit;

                            if (row.days) {
                                for (var d = 1; d <= 31; d++) {
                                    var dPlan = (row.days[d]) ? parseFloat(row.days[d].plan_ovh) || 0 : 0;
                                    var dAct  = (row.days[d]) ? parseFloat(row.days[d].act_ovh) || 0 : 0;
                                    
                                    if (dPlan > 0) {
                                        sumPlanOvh += dPlan;
                                        cumulativePlan += dPlan;
                                        if (cumulativePlan >= nextTargetPlan && targetDatePlan === '-') {
                                            targetDatePlan = d + ' ' + namaBulanText + ' ' + thn;
                                        }
                                    }
                                    if (dAct > 0) {
                                        sumActOvh += dAct;
                                    }
                                }
                            }

                            if (row.checked_dates) {
                                $.each(row.checked_dates, function(dayNum, val) {
                                    if (val == '1' || val === true) {
                                        checkedActualDates.push(dayNum + ' ' + namaBulanText + ' ' + thn);
                                    }
                                });
                            }

                            if (targetDatePlan !== '-' || checkedActualDates.length > 0) {
                                hasData = true;
                                var grandTotalPlan = initialLastShoot + sumPlanOvh;
                                var grandTotalAct  = initialLastShoot + sumActOvh;
                                var actDateDisplay = checkedActualDates.length > 0 ? checkedActualDates.join(', ') : '<span style="color:#888; font-style:italic;">Belum dicentang</span>';

                                customerRowsHtml += '<tr class="row-plan">';
                                customerRowsHtml += '<td class="text-center" rowspan="2">' + (custItemNo++) + '</td>';
                                customerRowsHtml += '<td class="text-center" rowspan="2"><b>' + row.mc_no + '</b></td>';
                                customerRowsHtml += '<td rowspan="2" style="white-space:normal; line-height:1.3;"><b>' + row.item_code + '</b><br><small>' + row.item_name + '</small></td>';
                                customerRowsHtml += '<td class="text-right" rowspan="2" style="vertical-align:middle; background:#fff3cd;"><b>' + formatNumber(initialLastShoot) + '</b></td>';
                                customerRowsHtml += '<td><i class="fa fa-file-text-o"></i> Plan Shots</td>';
                                customerRowsHtml += '<td class="text-right"><b>' + formatNumber(sumPlanOvh) + '</b></td>';
                                customerRowsHtml += '<td class="text-right"><b>' + formatNumber(grandTotalPlan) + '</b></td>';
                                customerRowsHtml += '<td class="text-center">';
                                if (targetDatePlan !== '-') {
                                    customerRowsHtml += '<span class="badge-plan"><i class="fa fa-wrench"></i> Plan: ' + targetDatePlan + '</span>';
                                } else {
                                    customerRowsHtml += '-';
                                }
                                customerRowsHtml += '</td>';
                                customerRowsHtml += '</tr>';

                                customerRowsHtml += '<tr class="row-act">';
                                customerRowsHtml += '<td><i class="fa fa-industry"></i> Actual Shots</td>';
                                customerRowsHtml += '<td class="text-right"><b>' + formatNumber(sumActOvh) + '</b></td>';
                                customerRowsHtml += '<td class="text-right"><b>' + formatNumber(grandTotalAct) + '</b></td>';
                                customerRowsHtml += '<td class="text-center">';
                                customerRowsHtml += '<span class="badge-act"><i class="fa fa-check-square-o"></i> Aktual: ' + actDateDisplay + '</span>';
                                customerRowsHtml += '</td>';
                                customerRowsHtml += '</tr>';
                            }
                        });

                        if (customerRowsHtml !== '') {
                            html += '<tr><td colspan="8" class="group-header"><i class="fa fa-building"></i> CUSTOMER: ' + custName + '</td></tr>';
                            html += customerRowsHtml;
                        }
                    });

                    if(!hasData) {
                        html = '<tr><td colspan="8" class="text-center" style="padding: 30px; color:#d9534f;"><b>Tidak ada data OVH Plan atau Aktual yang dicentang untuk periode ini.</b></td></tr>';
                    }
                    tbody.html(html);
                } else {
                    tbody.html('<tr><td colspan="8" class="text-center" style="color:#d9534f; padding: 20px;"><b>Gagal memuat data:</b> ' + res.message + '</td></tr>');
                }
            },
            error: function() {
                tbody.html('<tr><td colspan="8" class="text-center" style="color:#d9534f; padding: 20px;"><b>Error server.</b> Gagal terhubung ke database.</td></tr>');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fa fa-search"></i> Generate Report');
            }
        });
    });
});
</script>
</body>
</html>