<?php
// Kompatibilitas PHP 5.4: Gunakan pemeriksaan session yang aman
if (!isset($_SESSION)) {
    session_start();
}

require_once __DIR__ . "/../config/global.php";

$sqlTrty = "SELECT * FROM TRTY WHERE TRTY_CODE IN ('04','06','21','01','40') ORDER BY TRTY_CODE";
$stmtTrty = sqlsrv_query($conn, $sqlTrty);

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
        <div class="page-header mb-3 border-bottom pb-2 d-flex justify-content-between align-items-center">
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
                            <input type="text" class="form-control form-control-sm bg-light" id="tranid_view" disabled value="Otomatis sistem">
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

                    <div class="mt-3">
                        <button type="button" class="btn btn-primary btn-sm px-4 fw-bold" id="submit_btn">New Data</button>
                        <button type="button" class="btn btn-danger btn-sm px-3 fw-bold" id="cancel_btn">Cancel</button>
                    </div>
                </div>

                <!-- Kolom Kanan: Scanner -->
                <div class="col-md-6" id="divdata" style="display:none;">
                    <div class="mb-2">
                        <label class="form-label fw-bold text-primary">Scan QRCode Material Incoming (Auto):</label>
                        <textarea rows="2" class="form-control qr-input border-primary shadow-sm" id="qrcode" placeholder="Arahkan scanner ke sini..." autocomplete="off"></textarea>
                    </div>

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
                    <input type="hidden" id="totaldetail" value="0">
                </div>
            </div>
        </form>

        <hr class="mt-4 mb-3 border-2 border-primary border-opacity-25">

        <!-- BAGIAN BAWAH: RIWAYAT TRANSAKSI INCOMING EXCESS (CRUD TABLE) -->
        <div id="history_section">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-secondary m-0"><i class="bi bi-clock-history me-1"></i>Riwayat Transaksi Incoming Excess</h6>
                
                <div class="d-flex gap-2">
                    <select id="filter_tahun" class="form-select form-select-sm w-auto">
                        <option value="<?php echo date('Y'); ?>" selected><?php echo date('Y'); ?></option>
                        <option value="<?php echo date('Y', strtotime('-1 year')); ?>"><?php echo date('Y', strtotime('-1 year')); ?></option>
                        <option value="<?php echo date('Y', strtotime('-2 year')); ?>"><?php echo date('Y', strtotime('-2 year')); ?></option>
                    </select>
                    
                    <select id="filter_limit" class="form-select form-select-sm w-auto">
                        <option value="5" selected>5 Baris</option>
                        <option value="10">10 Baris</option>
                        <option value="50">50 Baris</option>
                    </select>

                    <div class="input-group input-group-sm" style="width: 200px;">
                        <input type="text" id="search_history" class="form-control" placeholder="Cari TRAN_ID / Doc No..." autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button"><i class="bi bi-search"></i></button>
                    </div>
                    <input type="hidden" id="current_page" value="0">
                </div>
            </div>

            <!-- Tabel Riwayat -->
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-bordered table-striped table-hover table-compact" id="history_table">
                    <thead class="table-dark text-center sticky-top">
                        <tr>
                            <th width="10%">TRAN_ID</th>
                            <th width="15%">TRAN_DATE</th>
                            <th width="10%">TRTY_CODE</th>
                            <th width="20%">TRAN_DOC</th>
                            <th width="25%">MERCHANT</th>
                            <th width="20%">ACTION</th>
                        </tr>
                    </thead>
                    <tbody id="history_listdata">
                        <tr><td colspan="6" class="text-center text-muted py-4">Memuat data riwayat...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<audio id="audio-success" src="assets/sounds/beep-success.mp3" preload="auto"></audio>
<audio id="audio-error" src="assets/sounds/beep-error.mp3" preload="auto"></audio>

<script>
function playSound(type) {
    var audioEl = document.getElementById(type === 'success' ? 'audio-success' : 'audio-error');
    if (audioEl) {
        audioEl.currentTime = 0; 
        audioEl.play().catch(function(error) { console.log("Audio failed: " + error); });
    }
}

$(document).ready(function() {
    var baseUrl = '<?php echo "http://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/"; ?>';
    var scannedItems = [];

    var $qr = $('#qrcode');
    var $alert = $('#alert-msg');
    var scanTimer; 
    var colorResetTimer;

    $('.select2').select2({ width: '100%' });
    $('#merchant').select2({
        placeholder: 'Pilih Merchant',
        allowClear: true,
        width: '100%',
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
            },
            cache: true
        }
    });

    function loadHistoryTable() {
        var tahun = $('#filter_tahun').val();
        var limit = $('#filter_limit').val();
        var searching = $('#search_history').val();
        var page = $('#current_page').val();

        $.ajax({
            type: 'POST',
            url: baseUrl + 'in_list_excess.php',
            data: { tahun: tahun, limit: limit, searching: searching, page: page },
            success: function(res) {
                $('#history_listdata').html(res);
            }
        });
    }

    loadHistoryTable();

    $('#filter_tahun, #filter_limit').change(function() {
        $('#current_page').val(0);
        loadHistoryTable();
    });

    var historyTimer;
    $('#search_history').on('keyup', function() {
        $('#current_page').val(0);
        clearTimeout(historyTimer);
        historyTimer = setTimeout(function() {
            loadHistoryTable();
        }, 400);
    });

    function showAlert(type, text) {
        $alert.removeClass('alert-success alert-danger')
              .addClass('alert-' + type)
              .html('<strong>' + (type === 'success' ? 'Info:' : 'Peringatan!') + '</strong> ' + text)
              .show(); 
        setTimeout(function() { $alert.hide(); }, 2000);
    }

    function setInputFeedback(status) {
        clearTimeout(colorResetTimer);
        $qr.removeClass('scan-success scan-error').val('').attr('placeholder', 'Arahkan scanner ke sini...');
        
        if (status === 'success') {
            $qr.addClass('scan-success').attr('placeholder', 'Sukses!');
        } else if (status === 'error') {
            $qr.addClass('scan-error').attr('placeholder', 'Gagal!');
        }

        colorResetTimer = setTimeout(function() {
            $qr.removeClass('scan-success scan-error').attr('placeholder', 'Arahkan scanner ke sini...');
        }, 500);
    }

    function renderDetailTable() {
        var html = '';
        if (scannedItems.length === 0) {
            html = '<tr><td colspan="5" class="text-center text-muted">BELUM ADA DETAIL</td></tr>';
            $('#totaldetail').val(0);
        } else {
            $.each(scannedItems, function(index, item) {
                var lineNo = index + 1;
                html += '<tr>';
                html += '<td style="vertical-align:middle;">' + lineNo + '</td>';
                html += '<td style="vertical-align:middle;">' + item.itemId + '</td>';
                html += '<td style="vertical-align:middle;">' + parseFloat(item.qty).toFixed(2) + '</td>';
                html += '<td style="vertical-align:middle;">' + item.qrId + '</td>';
                html += '<td style="text-align:center;padding:1px;vertical-align:middle;">';
                html += '<button type="button" data-index="' + index + '" class="btn btn-danger btn-bold btn-minier removedetail" title="Delete"><i class="bi bi-trash"></i> Hapus</button>';
                html += '</td>';
                html += '</tr>';
            });
            $('#totaldetail').val(scannedItems.length);
        }
        $('#listdata').html(html);
    }

    // TOMBOL "New Data" / "Selesai / Final"
    $('#submit_btn').click(function(e) {
        e.preventDefault();
        var currentVal = $(this).text();

        if (currentVal === 'New Data') {
            if ($('#trandate').val() === '') { showAlert('danger', 'Isi tanggal transaksi.'); return; }
            if ($('#trty').val() === '') { showAlert('danger', 'Pilih jenis transaksi.'); return; }

            playSound('success');
            $('#submit_btn').text('Selesai / Final').removeClass('btn-primary').addClass('btn-success');
            $('#trandate, #trty, #merchant').prop('disabled', true);
            $('#divdata').fadeIn();
            $qr.focus();
            showAlert('success', 'Form siap! Silakan scan QR Code.');

        } else {
            if (scannedItems.length === 0) {
                playSound('error');
                showAlert('danger', 'Belum ada detail item yang di-scan! Minimal scan 1 item sebelum menyelesaikan transaksi.');
                $qr.focus(); 
                return;
            }

            var tranid = $('#tranid').val();
            var sts = $('#sts').val();

            var postData = {
                tranid: tranid,
                sts: sts,
                trandate: $('#trandate').val(),
                trty: $('#trty').val(),
                merchant: $('#merchant').val(),
                details: JSON.stringify(scannedItems)
            };

            $.ajax({
                type: 'POST',
                url: baseUrl + 'in_excess_save_final.php',
                data: postData,
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        playSound('success');
                        showAlert('success', 'Transaksi berhasil disimpan secara final!');
                        loadHistoryTable();
                        setTimeout(function() { 
                            location.reload(); 
                        }, 1500);
                    } else {
                        playSound('error');
                        showAlert('danger', 'Gagal menyimpan: ' + res.message);
                    }
                }
            });
        }
    });

    $('#cancel_btn').click(function(e) {
        e.preventDefault();
        location.reload();
    });

    function processQRCodeLocal(str) {
        if (str.trim() === '') return;
        $qr.val('').prop('readonly', true);

        var parts = str.split('|');
        var qrId = '', poId = '', qty = '0', itemId = '1';

        if (parts.length >= 7) {
            qrId   = parts[0] ? parts[0].trim() : '';
            poId   = parts[1] ? parts[1].trim() : '';
            qty    = parts[4] ? parts[4].trim() : '0';
            itemId = parts[6] ? parts[6].trim() : '1';
        } else if (parts.length >= 5) {
            qrId   = parts[0] ? parts[0].trim() : '';
            poId   = parts[1] ? parts[1].trim() : '';
            qty    = parts[2] ? parts[2].trim() : '0';
            itemId = parts[4] ? parts[4].trim() : '1';
        } else {
            playSound('error');
            showAlert('danger', 'Gagal: Format QR tidak dikenali.');
            setInputFeedback('error'); 
            $qr.prop('readonly', false).focus();
            return;
        }

        var isDuplicate = false;
        $.each(scannedItems, function(i, item) {
            if (item.qrId === qrId) {
                isDuplicate = true;
                return false;
            }
        });

        if (isDuplicate) {
            playSound('error');
            showAlert('danger', 'QR Code ini sudah pernah di-scan!');
            setInputFeedback('error');
            $qr.prop('readonly', false).focus();
            return;
        }

        scannedItems.push({
            qrId: qrId,
            poId: poId,
            qty: qty,
            itemId: itemId
        });

        playSound('success');
        setInputFeedback('success');
        renderDetailTable();
        $qr.prop('readonly', false).focus();
    }

    $qr.on('keypress', function(e) {
        if (e.which === 13) { 
            e.preventDefault();
            var str = $(this).val();
            clearTimeout(scanTimer);
            processQRCodeLocal(str);
        }
    });

    $qr.on('input', function() {
        if ($(this).hasClass('scan-success') || $(this).hasClass('scan-error')) {
            $(this).removeClass('scan-success scan-error').attr('placeholder', 'Arahkan scanner ke sini...');
            clearTimeout(colorResetTimer);
        }

        var str = $(this).val();
        clearTimeout(scanTimer);
        scanTimer = setTimeout(function() {
            if ($qr.val().trim() !== '') {
                var parts = str.split('|');
                if (parts.length >= 5) {
                    processQRCodeLocal(str);
                }
            }
        }, 200); 
    });

    $(document).on('click', '.removedetail', function() {
        var index = $(this).data('index');
        if (confirm("Hapus item detail baris ini?")) {
            scannedItems.splice(index, 1);
            renderDetailTable();
            $qr.focus();
        }
    });

    // EDIT TRANSAKSI
    $(document).on('click', '.edit', function(e) {
        e.preventDefault();
        var tranid = $(this).attr('tranid');
        $('#tranid').val(tranid);
        $('#sts').val('Edit');

        $.ajax({
            type: "POST",
            url: baseUrl + 'in_excess_get_by_id.php',
            data: { tranid: tranid },
            dataType: 'json',
            success: function(res) {
                if (res.response === 'true') {
                    var d = res.message[0];
                    $('#trandate').val(d.adate);
                    $('#trty').val(d.trty).trigger('change');
                    $('#trandoc').val(d.docno);
                    
                    if (d.supcode) {
                        var newOption = new Option(d.supcode + ' | ' + d.supname, d.supcode, true, true);
                        $('#merchant').append(newOption).trigger('change');
                    }

                    // Muat detail ke array lokal
                    scannedItems = res.details || [];
                    renderDetailTable();

                    $('#submit_btn').text('Selesai / Final').removeClass('btn-primary').addClass('btn-success');
                    $('#divdata').fadeIn();
                    $('#tranid_view').val(tranid);
                    $('html, body').animate({ scrollTop: 0 }, 'fast');
                }
            }
        });
    });

    // DELETE TRANSAKSI
    $(document).on('click', '.remove', function(e) {
        e.preventDefault();
        var tranid = $(this).attr('tranid');
        if (confirm("Apakah data Incoming Excess TRAN_ID: " + tranid + " akan dihapus permanen?")) {
            $.ajax({
                type: "POST",
                url: baseUrl + 'in_excess_hapus.php',
                data: { tranid: tranid },
                success: function(msg) {
                    if (msg.trim() === 'true') {
                        showAlert('success', 'Data berhasil dihapus.');
                        loadHistoryTable();
                    } else {
                        showAlert('danger', 'Gagal menghapus data.');
                    }
                }
            });
        }
    });

    // CETAK SLIP
    $(document).on('click', '.cetak', function(e) {
        e.preventDefault();
        var tranid = $(this).attr('tranid');
        var destination = baseUrl + 'slip.php?tranid=' + tranid;
        
        var height = 600; var width = 800;
        var leftPosition = (window.screen.width / 2) - (width / 2);
        var topPosition = (window.screen.height / 2) - (height / 2);
        
        window.open(destination, "PrintSlip", "status=no,height=" + height + ",width=" + width + ",resizable=yes,left=" + leftPosition + ",top=" + topPosition + ",toolbar=no,menubar=no,scrollbars=yes");
    });
});
</script>

</body>
</html>