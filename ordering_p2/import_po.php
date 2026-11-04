<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Import PO All Customer</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Theme style (AdminLTE) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- jQuery UI CSS -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    
    <style>
        .ui-autocomplete { z-index: 9999 !important; max-height: 200px; overflow-y: auto; overflow-x: hidden; }
        .table-responsive { max-height: 300px; } 
        /* Efek hover dan klik pada tabel staging */
        #tblStaging tbody tr:hover { background-color: #f4f6f9; }
        #tblStaging tbody tr { transition: background-color 0.2s; }
    </style>
</head>
<body class="hold-transition sidebar-collapse layout-top-nav">
<div class="wrapper">

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Import PO All Customer</h1>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                
                <!-- TOOLBAR & STAGING SECTION -->
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <form id="uploadForm" enctype="multipart/form-data">
                                    <input type="file" name="fileExcel" id="fileExcel" accept=".xls,.xlsx" style="display:none;" onchange="$('#uploadForm').submit();">
                                    <button type="button" class="btn btn-success btn-block" onclick="$('#fileExcel').click();">
                                        <i class="fas fa-file-excel mr-2"></i> LOAD FROM EXCEL
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-user"></i>&nbsp; CUSTOMER</span>
                                    </div>
                                    <input type="text" class="form-control" id="customerSearch" placeholder="Cari Customer...">
                                    <input type="hidden" id="custId">
                                    <input type="hidden" id="currCode">
                                </div>
                            </div>
                            <div class="col-md-3 text-right">
                                <button type="button" id="btnImport" class="btn btn-primary btn-block">
                                    <i class="fas fa-upload mr-2"></i> IMPORT PO
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- TABEL STAGING -->
                    <div class="card-body table-responsive p-0">
                        <table id="tblStaging" class="table table-bordered table-head-fixed text-nowrap text-sm">
                            <thead>
                                <tr>
                                    <th>ORDR_PO</th>
                                    <th>ITEM_NO</th>
                                    <th>ITEM_CODE</th>
                                    <th>ORDR_DATE</th>
                                    <th>ORDP_QTY</th>
                                    <th>ORDR_CURR</th>
                                    <th>ORDP_PRICE</th>
                                    <th>ORDR_START</th>
                                    <th>ORDR_END</th>
                                    <th>STATUS</th>
                                    <th>ERROR_MSG</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data dimuat via AJAX -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="card-footer">
                        <button type="button" id="btnClear" class="btn btn-danger">
                            <i class="fas fa-trash mr-2"></i> CLEAR DATA
                        </button>
                    </div>
                </div>

                <!-- CEK PO SECTION -->
                <div class="card card-info card-outline mt-4">
                    <div class="card-header">
                        <h3 class="card-title text-bold text-center w-100">CEK PO</h3>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table id="tblCekPO" class="table table-bordered table-hover table-head-fixed text-nowrap text-sm">
                            <thead>
                                <tr>
                                    <th>ORDR_PO</th>
                                    <th>ORDR_DATE</th>
                                    <th>ORDR_CURR</th>
                                    <th>ITEM_CODE</th>
                                    <th>ITEM_NO</th>
                                    <th>ITEM_NAME</th>
                                    <th>ORDER</th>
                                    <th>DELIVERY</th>
                                    <th>BALANCE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data dimuat via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<!-- REQUIRED SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
$(document).ready(function() {
    loadStaging();
    loadCekPO(); // Load semua data awal

    // Efek highlight baris warna kuning saat diklik
    $(document).on('click', '#tblStaging tbody tr', function() {
        $('#tblStaging tbody tr').removeClass('bg-warning');
        $(this).addClass('bg-warning');
    });

    // Autocomplete Customer
    $("#customerSearch").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "ac_customer.php",
                type: "POST",
                dataType: "json",
                data: { q: request.term },
                success: function(data) {
                    response($.map(data, function(item) {
                        return {
                            label: item.CUST_COMP + " (" + item.CUST_CODE + ")",
                            value: item.CUST_COMP,
                            id: item.CUST_ID,
                            curr: item.CURR_CODE
                        };
                    }));
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            $("#custId").val(ui.item.id);
            $("#currCode").val(ui.item.curr);
            
            $.post("update_customer.php", { cust_id: ui.item.id, curr_code: ui.item.curr }, function(res) {
                alert("Customer diganti ke: " + ui.item.value);
                loadStaging();
            });
        }
    });

    // Upload Excel
    $("#uploadForm").on('submit', function(e){
        e.preventDefault();
        var formData = new FormData(this);
        $.ajax({
            url: "upload_excel.php",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            beforeSend: function() {
                // Munculkan loading saat proses upload berjalan
                $("#tblStaging tbody").html('<tr><td colspan="11" class="text-center text-success py-4"><h5><i class="fas fa-spinner fa-spin"></i> Sedang Membaca Excel & Menghitung Harga...</h5></td></tr>');
            },
            success: function(res) {
                alert(res);
                loadStaging();
                $('#fileExcel').val(''); 
            },
            error: function() {
                alert("Terjadi kesalahan jaringan atau server saat upload.");
                loadStaging();
            }
        });
    });

    // Hapus Data Staging
    $("#btnClear").click(function() {
        if(confirm("Hapus SEMUA data staging?")) {
            $.post("action.php", { action: 'clear' }, function(res) {
                alert(res);
                loadStaging();
                loadCekPO(); 
            });
        }
    });

    // Import PO
    $("#btnImport").click(function() {
        // Tampilkan loading di cek PO saat proses import berjalan
        $("#tblCekPO tbody").html('<tr><td colspan="9" class="text-center text-primary py-4"><h5><i class="fas fa-spinner fa-spin"></i> Memproses Import PO ke Database...</h5></td></tr>');
        
        $.post("action.php", { action: 'import' }, function(res) {
            alert(res);
            loadStaging();
            loadCekPO(); 
        });
    });
});

// Fungsi Memuat Staging dengan Efek Loading & Error Handle
function loadStaging() {
    $("#tblStaging tbody").html('<tr><td colspan="11" class="text-center text-primary py-4"><h5><i class="fas fa-spinner fa-spin"></i> Memuat Data Staging...</h5></td></tr>');
    $.ajax({
        url: "load_data.php?type=staging",
        type: "GET",
        success: function(data) { 
            $("#tblStaging tbody").html(data); 
        },
        error: function(xhr) {
            $("#tblStaging tbody").html('<tr><td colspan="11" class="text-center text-danger"><b>Gagal Memuat Staging:</b><br>' + xhr.responseText + '</td></tr>');
        }
    });
}

// Fungsi Memuat CEK PO dengan Efek Loading & Error Handle
function loadCekPO(po_number = '') {
    $("#tblCekPO tbody").html('<tr><td colspan="9" class="text-center text-info py-4"><h5><i class="fas fa-spinner fa-spin"></i> Memuat Data PO...</h5></td></tr>');
    $.ajax({
        url: "load_data.php?type=cek_po&po=" + po_number,
        type: "GET",
        success: function(data) { 
            $("#tblCekPO tbody").html(data); 
        },
        error: function(xhr) {
            $("#tblCekPO tbody").html('<tr><td colspan="9" class="text-center text-danger"><b>Gagal Memuat CEK PO:</b><br>' + xhr.responseText + '</td></tr>');
        }
    });
}
</script>
</body>
</html>