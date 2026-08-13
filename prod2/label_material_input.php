<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Input Label Material</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap.min.css">
    
    <style>
        body { background: #f5f5f5; font-size: 12px; padding-top: 20px; padding-bottom: 40px; }
        .panel-primary > .panel-heading { background-color: #2c3e50; border-color: #2c3e50; }
        .panel-info > .panel-heading { background-color: #d9edf7; color: #31708f; font-weight: bold; }
        
        .ac-wrap { position: relative; }
        .ac-dropdown {
            position: absolute; z-index: 99999; background: #fff;
            border: 1px solid #ccc; border-top: none;
            max-height: 200px; overflow-y: auto; overflow-x: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,.15); display: none; width: 100%;
        }
        .ac-item { padding: 8px 10px; font-size: 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; }
        .ac-item:hover, .ac-item.ac-active { background: #d9edf7; }
        .ac-item strong { background: #fcf8c8; }
        .ac-loading, .ac-empty { padding: 8px 10px; font-size: 11px; color: #777; }
        
        table.dataTable { font-size: 11px; }
        .btn-xs { font-size: 10px; padding: 2px 6px; }
    </style>
</head>
<body>

<div class="container" style="max-width: 900px;">
    
    <!-- PANEL FORM INPUT -->
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title"><span class="glyphicon glyphicon-print"></span> Cetak Label Material</h3>
        </div>
        <div class="panel-body">
            <form id="formLabel" action="label_material_print.php" method="POST" target="_blank">
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group ac-wrap">
                            <label for="item_code">Item Code</label>
                            <input type="text" class="form-control" id="item_code" name="item_code" placeholder="Ketik Item Code..." autocomplete="off" required>
                            <div class="ac-dropdown" id="ac_dropdown_item"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="material_name">Material Name</label>
                            <input type="text" class="form-control" id="material_name" name="material_name" placeholder="Otomatis terisi..." required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group ac-wrap">
                            <label for="material_grade">Material Grade</label>
                            <input type="text" class="form-control" id="material_grade" name="material_grade" placeholder="Cari Grade / Kosongkan..." autocomplete="off">
                            <div class="ac-dropdown" id="ac_dropdown_grade"></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group ac-wrap">
                            <label for="colour">Colour</label>
                            <input type="text" class="form-control" id="colour" name="colour" placeholder="Cari Warna / Kosongkan..." autocomplete="off">
                            <div class="ac-dropdown" id="ac_dropdown_colour"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="receive_date">Receive Date</label>
                            <input type="date" class="form-control" id="receive_date" name="receive_date" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="issue_date">Issue Date</label>
                            <input type="date" class="form-control" id="issue_date" name="issue_date">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="expired_date">Expired Date</label>
                            <input type="date" class="form-control" id="expired_date" name="expired_date">
                        </div>
                    </div>
                </div>

                <hr>
                <div class="text-right">
                    <button type="button" class="btn btn-default pull-left" onclick="window.close();">
                        <span class="glyphicon glyphicon-arrow-left"></span> Kembali ke Dashboard
                    </button>
                    
                    <button type="reset" class="btn btn-default">Reset</button>
                    <button type="submit" class="btn btn-success"><span class="glyphicon glyphicon-print"></span> Cetak Label</button>
                </div>
            </form>
        </div>
    </div>

    <!-- PANEL DATATABLES RIWAYAT -->
    <div class="panel panel-info">
        <div class="panel-heading">
            <h3 class="panel-title"><span class="glyphicon glyphicon-time"></span> Riwayat Cetak Label (Dari Database)</h3>
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table id="tableHistory" class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Waktu Cetak</th>
                            <th>Item Code</th>
                            <th>Material Name</th>
                            <th>Grade</th>
                            <th>Colour</th>
                            <th style="width: 80px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data akan diisi oleh DataTables via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- jQuery dan DataTables JS -->
<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap.min.js"></script>

<script>
(function ($) {
    "use strict";

    /* =========================================================================
       1. FUNGSI DATATABLES & DATABASE
       ========================================================================= */
    
    var table = $('#tableHistory').DataTable({
        ajax: 'get_label_history.php',
        order: [[0, 'desc']], 
        columns: [
            { data: 'print_time' },
            { data: 'item_code' },
            { data: 'material_name' },
            { data: 'material_grade' },
            { data: 'colour' },
            { 
                data: null,
                orderable: false,
                render: function (data, type, row) {
                    return '<button class="btn btn-xs btn-primary btn-reprint" data-row=\'' + JSON.stringify(row) + '\'><span class="glyphicon glyphicon-print"></span> Print</button>';
                }
            }
        ],
        language: {
            url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" 
        }
    });

    // Proses menyimpan ke database setiap klik tombol submit
    $('#formLabel').on('submit', function() {
        var formData = $(this).serialize();
        
        $.ajax({
            url: 'save_label_history.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                // Refresh tabel setelah berhasil simpan
                table.ajax.reload(null, false); 
            }
        });
        // Form tetap berjalan memanggil label_material_print.php
    });

    // Tombol Cetak Ulang pada tabel riwayat
    $('#tableHistory').on('click', '.btn-reprint', function() {
        var dataRow = $(this).data('row');
        var $virtualForm = $('<form>', {
            action: 'label_material_print.php',
            method: 'POST',
            target: '_blank'
        });

        $.each(dataRow, function(key, val) {
            if (key !== 'id' && key !== 'print_time' && val !== null) {
                $('<input>').attr({ type: 'hidden', name: key, value: val }).appendTo($virtualForm);
            }
        });

        $virtualForm.appendTo('body').submit().remove();
    });

    /* =========================================================================
       2. FUNGSI AUTOCOMPLETE
       ========================================================================= */
    function setupAutocomplete(inputId, dropdownId, url, type) {
        var $input = $("#" + inputId);
        var $dropdown = $("#" + dropdownId);
        var items = [];
        var selIdx = -1;
        var xhr = null;
        var debounce = null;

        $input.on("input focus", function () {
            clearTimeout(debounce);
            var q = $(this).val().trim();
            if (q.length < 2) { hide(); return; }
            debounce = setTimeout(function () { fetch(q); }, 300);
        });

        $input.on("keydown", function (e) {
            if ($dropdown.is(":hidden")) return;
            if (e.keyCode === 40) { e.preventDefault(); selIdx = Math.min(selIdx + 1, items.length - 1); render(); } 
            else if (e.keyCode === 38) { e.preventDefault(); selIdx = Math.max(selIdx - 1, 0); render(); } 
            else if (e.keyCode === 13) { e.preventDefault(); if (selIdx >= 0) pick(items[selIdx]); } 
            else if (e.keyCode === 27) { hide(); }
        });

        $(document).on("mousedown", function (e) {
            if (!$(e.target).closest($input).length && !$(e.target).closest($dropdown).length) hide();
        });

        function fetch(q) {
            if (xhr && xhr.readyState < 4) xhr.abort();
            $dropdown.html('<div class="ac-loading">Mencari...</div>').show();

            xhr = $.ajax({
                url: url,
                data: { q: q },
                dataType: "json",
                cache: false,
                success: function (data) {
                    items = [];
                    if (Array.isArray(data)) {
                        for (var i = 0; i < data.length; i++) {
                            if (type === 'item' && data[i].ITEM_CODE) {
                                items.push({ value: data[i].ITEM_CODE, name: data[i].ITEM_NAME, label: data[i].ITEM_CODE + " - " + (data[i].ITEM_NAME || "") });
                            } else if (type === 'grade' && data[i].GRADE) {
                                items.push({ value: data[i].GRADE, label: data[i].GRADE });
                            } else if (type === 'colour' && data[i].COLOUR) {
                                items.push({ value: data[i].COLOUR, label: data[i].COLOUR });
                            }
                        }
                    }
                    selIdx = -1;
                    if (items.length > 0) render(); 
                    else $dropdown.html('<div class="ac-empty">Tidak ditemukan</div>');
                },
                error: function() {
                    $dropdown.html('<div class="ac-empty" style="color:red;">Gagal mengambil data</div>');
                }
            });
        }

        function render() {
            var q = $input.val().trim().toLowerCase();
            var html = "";
            for (var i = 0; i < items.length; i++) {
                var cls = (i === selIdx) ? " ac-active" : "";
                var label = highlight(items[i].label, q);
                html += '<div class="ac-item' + cls + '" data-i="' + i + '">' + label + '</div>';
            }
            $dropdown.html(html).show();

            $dropdown.find(".ac-item").on("mousedown", function (e) {
                e.preventDefault();
                var idx = parseInt($(this).attr("data-i"), 10);
                if (idx >= 0 && idx < items.length) pick(items[idx]);
            });
        }

        function highlight(text, q) {
            if (!q) return $("<span>").text(text).html();
            var lower = text.toLowerCase();
            var idx = lower.indexOf(q);
            if (idx === -1) return $("<span>").text(text).html();
            return $("<span>").text(text.substring(0, idx)).html() +
                   "<strong>" + $("<span>").text(text.substring(idx, idx + q.length)).html() + "</strong>" +
                   $("<span>").text(text.substring(idx + q.length)).html();
        }

        function pick(item) {
            $input.val(item.value);
            if (type === 'item') {
                $("#material_name").val(item.name);
            }
            hide();
        }

        function hide() {
            $dropdown.hide().empty();
            selIdx = -1;
        }
    }

    setupAutocomplete("item_code", "ac_dropdown_item", "ac_item.php", "item");
    setupAutocomplete("material_grade", "ac_dropdown_grade", "ac_grade.php", "grade");
    setupAutocomplete("colour", "ac_dropdown_colour", "ac_colour.php", "colour");

})(jQuery);
</script>
</body>
</html>