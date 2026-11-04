<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Pack ID per Customer</title>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .card { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px; }
        .flex-row { display: flex; gap: 15px; margin-bottom: 15px; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; flex: 1; }
        label { font-size: 13px; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="date"] { padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; }
        
        button { padding: 9px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-load { background: #6c757d; color: #fff; }
        .btn-update { background: #007bff; color: #fff; }
        .btn-update:disabled { background: #b8c4d1; cursor: not-allowed; }
        
        /* CSS untuk tombol report */
        .btn-report { background: #28a745; color: #fff; } 
        .btn-report:hover { background: #218838; }
        
        /* CSS Tambahan untuk tombol Pack In-Out */
        .btn-report-inout { background: #17a2b8; color: #fff; }
        .btn-report-inout:hover { background: #138496; }
        
        /* Wrapper agar tombol report berjejer rapi di sebelah kanan */
        .report-group { margin-left: auto; display: flex; gap: 10px; }
        
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f1f1f1; }
        .diff { background-color: #fff3cd; color: #856404; font-weight: bold; padding: 2px 5px; border-radius: 3px; }
        .same { background-color: #d4edda; color: #155724; font-weight: bold; padding: 2px 5px; border-radius: 3px; }
        .row-diff { background-color: #fdfaf0; }
        
        .col-calc { background-color: #f8f9fa; }
        .val-number { text-align: right; }
        .val-bold { font-weight: bold; }
        .val-blue { color: #0056b3; font-weight: bold; }
    </style>
</head>
<body>

    <div class="card">
        <h3>Filter Data Customer & Tanggal</h3>
        <div class="flex-row">
            <div class="form-group" style="flex: 0.5;">
                <label>Tanggal Awal</label>
                <input type="date" id="tgl_awal" value=""> 
            </div>
            <div class="form-group" style="flex: 0.5;">
                <label>Tanggal Akhir</label>
                <input type="date" id="tgl_akhir" value="">
            </div>
            <div class="form-group">
                <label>Pilih Customer (Ketik untuk cari)</label>
                <input type="text" id="cust_input" placeholder="Contoh: D1015">
                <input type="hidden" id="cust_code_hidden">
            </div>
            <div class="form-group">
                <label>Pilih Kemasan Baru (Tabel PACK)</label>
                <input type="text" id="pack_input" placeholder="Ketik kode/nama kemasan">
                <input type="hidden" id="pack_id_hidden">
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="button" id="btn_load" class="btn-load">🔍 Load Data</button>
            <button type="button" id="btn_update" class="btn-update" disabled>⚙️ Update Pack ID</button>
            
            <!-- Grouping Tombol Report agar di rata kanan bersama-sama -->
            <div class="report-group">
                <button type="button" id="btn_report" class="btn-report">📊 Panggil Report Matrik</button>
                <button type="button" id="btn_report_inout" class="btn-report-inout">📦 Panggil Report Pack In-Out</button>
            </div>
        </div>
    </div>

    <div class="card">
        <h4>Preview Data</h4>
        <div id="info_text" style="font-size: 13px; margin-bottom: 10px; color: #b05700; font-weight: bold;"></div>
        <table>
            <thead>
                <tr>
                    <th>Tgl DI</th>
                    <th>Part (Item Code / Name)</th> 
                    <th>Qty (DIPA_QTY)</th>
                    <th class="col-calc">Std Pack Box</th>
                    <th class="col-calc">DIPA_PQTY</th> 
                    <th class="col-calc">Bulat (Box)</th>
                    <th>PACK_ID Skrg</th>
                    <th>Kode Pack Skrg</th>
                    <th>Nama Pack Skrg</th>
                    <th>Status Perbedaan</th>
                </tr>
            </thead>
            <tbody id="table_body">
                <tr><td colspan="10" style="text-align: center;">Belum ada data di-load.</td></tr>
            </tbody>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script>
    $(document).ready(function() {
        
        var date = new Date();
        var firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
        var tglAwalDefault = firstDay.toISOString().split('T')[0];
        var tglAkhirDefault = date.toISOString().split('T')[0];
        
        if(!$("#tgl_awal").val()) $("#tgl_awal").val(tglAwalDefault);
        if(!$("#tgl_akhir").val()) $("#tgl_akhir").val(tglAkhirDefault);

        var rawData = [];

        $("#cust_input").autocomplete({
            source: function(req, res) {
                $.post("ajax_customer_autocomplete.php", { q: req.term }, function(data) {
                    res($.map(data, function(item) {
                        return {
                            label: item.CUST_CODE + " - " + item.CUST_COMP,
                            value: item.CUST_CODE + " - " + item.CUST_COMP,
                            code: item.CUST_CODE
                        };
                    }));
                }, "json");
            },
            minLength: 1,
            select: function(e, ui) {
                $("#cust_code_hidden").val(ui.item.code);
            }
        });

        $("#pack_input").autocomplete({
            source: function(req, res) {
                $.post("ajax_pack_autocomplete.php", { q: req.term }, function(data) {
                    res($.map(data, function(item) {
                        var packName = item.PACK_NAME ? " - " + item.PACK_NAME : "";
                        return {
                            label: item.PACK_CODE + packName,
                            value: item.PACK_CODE + packName,
                            id: item.PACK_ID
                        };
                    }));
                }, "json");
            },
            minLength: 1,
            select: function(e, ui) {
                $("#pack_id_hidden").val(ui.item.id);
                renderTable(); 
            }
        });

        $("#btn_load").on("click", function() {
            var tglAwal = $("#tgl_awal").val();
            var tglAkhir = $("#tgl_akhir").val();
            var custCode = $("#cust_code_hidden").val() || $("#cust_input").val().split(" - ")[0].trim();
            
            if (!custCode) { alert("Pilih customer!"); return; }
            if (!tglAwal || !tglAkhir) { alert("Isi tanggal!"); return; }

            $("#table_body").html('<tr><td colspan="10" style="text-align: center;">Memuat data...</td></tr>');
            
            $.ajax({
                url: "ajax_get_customer_parts.php",
                type: "POST",
                dataType: "json",
                data: {
                    cust_code: custCode,
                    tgl_awal: tglAwal,
                    tgl_akhir: tglAkhir
                },
                success: function(res) {
                    if (!res.status) {
                        alert(res.message);
                        $("#table_body").html('<tr><td colspan="10" style="text-align:center;color:red;">' + res.message + '</td></tr>');
                        return;
                    }
                    rawData = res.data;
                    renderTable();
                },
                error: function(xhr) {
                    alert("Error Server:\n\n" + xhr.responseText);
                }
            });
        });

        function renderTable() {
            var $tbody = $("#table_body").empty();
            
            if (rawData.length === 0) {
                $tbody.append('<tr><td colspan="10" style="text-align:center;">Tidak ada data ditemukan.</td></tr>');
                $("#btn_update").prop("disabled", true);
                $("#info_text").text("");
                return;
            }

            var newPackId = parseInt($("#pack_id_hidden").val()) || 0;
            var countDiff = 0;
            
            var totalBulatBox = 0; 
            var totalDipaPqty = 0; 

            $.each(rawData, function(i, row) {
                var currPackId = row.PACK_ID !== null ? parseInt(row.PACK_ID) : 0;
                var isDiff = (newPackId > 0 && currPackId !== newPackId);
                
                if (isDiff) countDiff++;

                var diffLabel = "-";
                if (newPackId > 0) {
                    diffLabel = isDiff ? '<span class="diff">BERBEDA</span>' : '<span class="same">SESUAI</span>';
                }
                
                var rowClass = isDiff ? ' class="row-diff"' : '';
                var displayPackId = row.PACK_ID ? row.PACK_ID : '-';
                var displayPackCode = row.PACK_CODE ? row.PACK_CODE : '-';
                var displayPackName = row.PACK_NAME ? row.PACK_NAME : '-';

                var itemCode = row.ITEM_CODE ? row.ITEM_CODE : "-";
                var itemName = row.ITEM_NAME ? row.ITEM_NAME : "-";
                var displayItemCodeName = itemCode + " / " + itemName;

                var dipaQty = row.DIPA_QTY ? parseFloat(row.DIPA_QTY) : 0;
                var stdPackBox = row.STD_PACK_BOX ? parseFloat(row.STD_PACK_BOX) : 0; 
                var dipaPqty = row.DIPA_PQTY ? parseFloat(row.DIPA_PQTY) : 0;
                
                totalDipaPqty += dipaPqty;
                
                var roundedBox = 0;
                var displayRoundedBox = "-";

                if(stdPackBox > 0) {
                    roundedBox = Math.ceil(dipaQty / stdPackBox); 
                    displayRoundedBox = roundedBox;
                    totalBulatBox += roundedBox;
                }

                var htmlRow = '<tr' + rowClass + '>' +
                                  '<td>' + row.DI_DATE + '</td>' +
                                  '<td>' + displayItemCodeName + '</td>' + 
                                  '<td class="val-number">' + dipaQty + '</td>' +
                                  '<td class="val-number val-bold">' + stdPackBox + '</td>' +
                                  '<td class="val-number">' + dipaPqty + '</td>' + 
                                  '<td class="val-number val-blue">' + displayRoundedBox + '</td>' +
                                  '<td>' + displayPackId + '</td>' +
                                  '<td>' + displayPackCode + '</td>' +
                                  '<td>' + displayPackName + '</td>' +
                                  '<td>' + diffLabel + '</td>' +
                              '</tr>';

                $tbody.append(htmlRow);
            });

            var htmlTotal = '<tr style="background-color: #e2e8f0; font-weight: bold; font-size: 14px;">' +
                                '<td colspan="4" style="text-align: right; text-transform: uppercase;">Total Keseluruhan:</td>' + 
                                '<td class="val-number">' + totalDipaPqty + '</td>' +
                                '<td class="val-number val-blue">' + totalBulatBox + '</td>' +
                                '<td colspan="4"></td>' +
                            '</tr>';
            $tbody.append(htmlTotal);

            if (newPackId > 0 && countDiff > 0) {
                $("#info_text").text("Ditemukan " + countDiff + " baris yang kemasannya berbeda. Siap di-update.");
                $("#btn_update").prop("disabled", false);
            } else if (newPackId > 0 && countDiff === 0) {
                $("#info_text").text("Semua baris sudah menggunakan kemasan yang dipilih.");
                $("#btn_update").prop("disabled", true);
            } else {
                $("#info_text").text("");
                $("#btn_update").prop("disabled", true);
            }
        }

        $("#btn_update").on("click", function() {
            var tglAwal = $("#tgl_awal").val();
            var tglAkhir = $("#tgl_akhir").val();
            var custCode = $("#cust_code_hidden").val() || $("#cust_input").val().split(" - ")[0].trim();
            var packId = $("#pack_id_hidden").val();

            if (!confirm("Yakin ingin mengupdate semua baris yang BERBEDA pada rentang tanggal ini menjadi kemasan yang baru?")) {
                return;
            }

            $.ajax({
                url: "ajax_update_pack_by_customer.php",
                type: "POST",
                dataType: "json",
                data: {
                    cust_code: custCode,
                    pack_id: packId,
                    tgl_awal: tglAwal,
                    tgl_akhir: tglAkhir
                },
                success: function(res) {
                    alert(res.message);
                    if (res.success) {
                        $("#btn_load").click(); 
                    }
                },
                error: function(xhr) {
                    alert("Error Server:\n\n" + xhr.responseText);
                }
            });
        });

        // Event Tombol Panggil Report Matrik
        $("#btn_report").on("click", function() {
            window.open("report_matrik.php", "_blank"); 
        });

        // Event Tombol Panggil Report Pack In-Out (Baru)
        $("#btn_report_inout").on("click", function() {
            window.open("pack_inout.php", "_blank"); 
        });

    });
    </script>
</body>
</html>