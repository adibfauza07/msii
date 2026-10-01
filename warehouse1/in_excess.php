<?php
// Kompatibilitas PHP 5.4: Gunakan pemeriksaan session yang aman
if (!isset($_SESSION)) {
    session_start();
}

// Pastikan koneksi global dimuat[cite: 1]
require_once __DIR__ . "/../config/global.php";

// Ambil list TRTY khusus untuk Incoming Excess ('04','06','21','01','40')[cite: 5]
$sqlTrty = "SELECT * FROM TRTY WHERE TRTY_CODE IN ('04','06','21','01','40') ORDER BY TRTY_CODE";
$stmtTrty = sqlsrv_query($conn, $sqlTrty);

// Ambil list BCTY[cite: 5]
$sqlBcty = "SELECT * FROM BCTY ORDER BY BCTY_ID";
$stmtBcty = sqlsrv_query($conn, $sqlBcty);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Material Incoming Excess - Warehouse System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 11px; }
        .main-card { max-width: 1150px; margin: 20px auto; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table-compact th, .table-compact td { padding: 4px 8px !important; vertical-align: middle; font-size: 11px; }
        .qr-input { font-size: 14px; letter-spacing: 1px; transition: all 0.1s ease-in-out; }
        .scan-success { background-color: #d4edda !important; border-color: #c3e6cb !important; text-align: center; }
        .scan-error { background-color: #f8d7da !important; border-color: #f5c6cb !important; text-align: center; }
    </style>
</head>
<body>

<div class="container">
    <div class="card p-3 main-card border-0">
        <div class="page-header mb-3 border-bottom pb-2">
            <h4 class="fw-bold text-primary m-0">
                <i class="bi bi-box-arrow-in-down-right me-2"></i>Material Incoming Excess
            </h4>
            <small class="text-muted">Plant 1</small>
        </div>

        <div id="alert-msg" class="alert" style="display: none; padding: 8px;"></div>

        <form id="formIncoming">
            <input type="hidden" id="tranid" name="tranid" value="">
            <input type="hidden" id="sts" name="sts" value="New">

            <div class="row">
                <!-- Kolom Kiri: Form Header Transaksi -->
                <div class="col-md-6 border-end">
                    <div class="mb-2 row">
                        <label class="col-sm-4 col-form-label fw-bold">Tran_Id</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control form-control-sm bg-light" id="tranid_view" disabled>
                        </div>
                    </div>

                    <div class="mb-2 row">
                        <label class="col-sm-4 col-form-label fw-bold">Tran_Adate</label>
                        <div class="col-sm-8">
                            <input type="date" class="form-control form-control-sm" id="trandate" name="trandate" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="mb-2 row">
                        <label class="col-sm-4 col-form-label fw-bold">Jenis Transaksi</label>
                        <div class="col-sm-8">
                            <select id="trty" name="trty" class="form-select form-select-sm select2">
                                <option value="">-- Pilih Jenis Transaksi --</option>
                                <?php while($row = sqlsrv_fetch_array($stmtTrty, SQLSRV_FETCH_ASSOC)) { ?>
                                    <option value="<?=$row['TRTY_CODE']?>"><?=$row['TRTY_CODE']?> | <?=$row['TRTY_DESC']?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2 row">
                        <label class="col-sm-4 col-form-label fw-bold">Icl. No</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control form-control-sm bg-light" id="trandoc" name="trandoc" placeholder="Auto Generate" disabled>
                            <input type="hidden" id="trandoc_hidden" name="trandoc" value="">
                        </div>
                    </div>

                    <div class="mb-2 row">
                        <label class="col-sm-4 col-form-label fw-bold">Merchant</label>
                        <div class="col-sm-8">
                            <select id="merchant" name="merchant" class="form-control form-select-sm" style="width: 100%;">
                                <option value="">Pilih Merchant</option>
                            </select>
                        </div>
                    </div>

                    <!-- Dokumen Bea Cukai Section[cite: 6] -->
                    <div class="mb-2 row">
                        <div class="col-sm-4">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" id="cbc">
                                <label class="form-check-label fw-bold text-danger" for="cbc">Doc. BC</label>
                            </div>
                            <input type="hidden" id="tbc" name="tbc" value="">
                        </div>
                    </div>

                    <div id="beacukai" style="display:none;" class="p-2 border rounded bg-light mb-2">
                        <div class="mb-1 row">
                            <label class="col-sm-4 col-form-label">Bc. Type</label>
                            <div class="col-sm-8">
                                <select id="bcty" name="bcty" class="form-select form-select-sm">
                                    <option value="">-- Pilih Tipe BC --</option>
                                    <?php while($row = sqlsrv_fetch_array($stmtBcty, SQLSRV_FETCH_ASSOC)) { ?>
                                        <option value="<?=$row['BCTY_ID']?>"><?=$row['BCTY_ID']?> | <?=$row['BCTY_NAME']?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-1 row">
                            <label class="col-sm-4 col-form-label">Bc. No</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control form-control-sm" id="bcno" name="bcno">
                            </div>
                        </div>
                        <div class="mb-1 row">
                            <label class="col-sm-4 col-form-label">Bc. Date</label>
                            <div class="col-sm-8">
                                <input type="date" class="form-control form-control-sm" id="bcdate" name="bcdate">
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-primary btn-sm px-4 fw-bold" id="submit_btn">New Data</button>
                        <button type="button" class="btn btn-danger btn-sm px-3 fw-bold" id="cancel_btn">Cancel</button>
                    </div>
                </div>

                <!-- Kolom Kanan: Scanner[cite: 1] -->
                <div class="col-md-6" id="divdata" style="display:none;">
                    <div class="mb-2">
                        <label class="form-label fw-bold text-primary">Scan QRCode Material Incoming:</label>
                        <textarea rows="2" class="form-control qr-input border-primary shadow-sm" id="qrcode" placeholder="Arahkan scanner ke sini..." autocomplete="off"></textarea>
                    </div>

                    <input type="hidden" id="qrcodeid" name="qrcodeid">
                    <input type="hidden" id="itemid" name="itemid">
                    <input type="hidden" id="poid" name="poid">
                    <input type="hidden" id="rcvdqty" name="rcvdqty">

                    <h6 class="fw-bold text-secondary mt-3">Detail Item Incoming (Sementara)</h6>
                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-bordered table-striped table-compact" id="detail_table">
                            <thead class="table-dark text-center sticky-top">
                                <tr>
                                    <th width="10%">LINE</th>
                                    <th width="20%">ITEM_ID</th>
                                    <th width="20%">IT_QTY</th>
                                    <th width="35%">QRCODE_ID</th>
                                    <th width="15%">ACTION</th>
                                </tr>
                            </thead>
                            <tbody id="listdata">
                                <tr><td colspan="5" class="text-center text-muted">BELUM ADA DETAIL</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    var baseUrl = '<?php echo "http://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/"; ?>';
    
    $('.select2').select2({ width: '100%' });
    $('#merchant').select2({ // Inisialisasi Select2 untuk supplier[cite: 1]
        placeholder: 'Pilih Merchant',
        allowClear: true,
        ajax: {
            url: baseUrl + 'search_sup.php',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) {
                return {
                    results: $.map(data, function (item) {
                        return { id: item.SUP_CODE, text: item.SUP_CODE + ' | ' + item.SUP_COMP };
                    })
                };
            }
        }
    });

    $('#cbc').change(function() { // Toggle form bea cukai[cite: 6]
        if ($(this).is(':checked')) {
            $('#tbc').val('Y'); $('#beacukai').slideDown();
        } else {
            $('#tbc').val(''); $('#beacukai').slideUp();
        }
    });

    function showAlert(type, text) { // Feedback visual[cite: 1]
        $('#alert-msg').removeClass('alert-success alert-danger').addClass('alert-' + type)
              .html('<strong>Info:</strong> ' + text).show(); 
        setTimeout(function() { $('#alert-msg').hide(); }, 2000);
    }

    // CREATE HEADER TRANSAKSI
    $('#submit_btn').click(function(e) {
        e.preventDefault();
        var currentVal = $(this).text();

        if (currentVal === 'New Data') {
            $.ajax({
                type: 'POST',
                url: baseUrl + 'in_excess_process_header.php', // Endpoint baru
                data: $('#formIncoming').serialize(),
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        $('#tranid').val(res.tranid);
                        $('#tranid_view').val(res.tranid);
                        $('#trandoc').val(res.trandoc);
                        $('#trandoc_hidden').val(res.trandoc);

                        $('#submit_btn').text('Submit').removeClass('btn-primary').addClass('btn-success');
                        $('#trandate, #trty, #merchant, #cbc').prop('disabled', true);
                        $('#divdata').fadeIn();
                        $('#qrcode').focus();
                        showAlert('success', 'Header dibuat! Silakan scan QR Code.');
                    } else {
                        showAlert('danger', 'Error: ' + res.message);
                    }
                }
            });
        }
    });

    // PROSES SCAN QR CODE OTOMATIS[cite: 1]
    $('#qrcode').on('keypress', function(e) {
        if (e.which === 13) { // Deteksi tombol enter dari scanner
            e.preventDefault();
            var str = $(this).val();
            if (str.trim() === '') return;
            $(this).val('').prop('readonly', true);

            var parts = str.split('|');
            if (parts.length >= 5) {
                var qrId = parts[0].trim();
                var poId = parts[1].trim();
                var qty = parts[2] ? parts[2].trim() : '0'; // index qty disesuaikan dengan CI set_qrcode_id()[cite: 6]
                var itemId = parts[6] ? parts[6].trim() : (parts[4] ? parts[4].trim() : '1');

                $.ajax({
                    type: 'POST',
                    url: baseUrl + 'in_excess_process_detail.php', // Endpoint detail baru
                    data: {
                        tranid: $('#tranid').val(),
                        itemid: itemId,
                        poid: poId,
                        rcvdqty: qty,
                        qrcodeid: qrId
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            showAlert('success', 'Scan Berhasil!');
                            // Panggil fungsi loadDetailList() di sini
                        } else {
                            showAlert('danger', res.message);
                        }
                    },
                    complete: function() { $('#qrcode').prop('readonly', false).focus(); }
                });
            }
        }
    });
});
</script>
</body>
</html>