<?php
// Kompatibilitas PHP 5.4: Gunakan pemeriksaan session yang aman
if (!isset($_SESSION)) {
    session_start();
}

// Pastikan koneksi global dimuat
require_once __DIR__ . "/../config/global.php";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Material Outgoing - Warehouse System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 11px; }
        .main-card { max-width: 1150px; margin: 20px auto; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table-compact th, .table-compact td { padding: 4px 8px !important; vertical-align: middle; font-size: 11px; }
        
        /* Modifikasi Input Default & Animasi Cepat */
        .qr-input { 
            font-size: 14px; 
            letter-spacing: 1px; 
            transition: all 0.1s ease-in-out;
        }

        /* Efek Form Scan (Sukses) */
        .scan-success { 
            background-color: #d4edda !important; 
            border-color: #c3e6cb !important; 
            text-align: center; 
        }
        .scan-success::placeholder { 
            color: #155724 !important; 
            font-size: 28px !important; 
            font-weight: bold; 
            line-height: 2;
        }

        /* Efek Form Scan (Gagal) */
        .scan-error { 
            background-color: #f8d7da !important; 
            border-color: #f5c6cb !important; 
            text-align: center; 
        }
        .scan-error::placeholder { 
            color: #721c24 !important; 
            font-size: 28px !important; 
            font-weight: bold; 
            line-height: 2;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card p-3 main-card border-0">
        <h4 class="fw-bold mb-3 border-bottom pb-2 text-primary">
            <i class="bi bi-box-arrow-up-right me-2"></i>Material Outgoing (Pengeluaran)
        </h4>

        <div id="alert-msg" class="alert" style="display: none; padding: 8px;"></div>

        <!-- BAGIAN ATAS: FORM INPUT & SCAN -->
        <form id="formOutgoing">
            <input type="hidden" id="tranid" name="tranid" value="">
            <input type="hidden" id="sts" name="sts" value="New">

            <div class="row">
                <!-- Kolom Kiri: Form Header Transaksi -->
                <div class="col-md-6 border-end">
                    <div class="mb-2 row">
                        <label class="col-sm-4 col-form-label fw-bold">Tran_Id</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control form-control-sm bg-light" id="tranid_view" disabled placeholder="Otomatis sistem">
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
                                <option value="02">02 | Return Mat'l to Supplier</option>
                                <option value="03">03 | Mat'l out for production</option>
                                <option value="05">05 | Mat'l out to sub cont.</option>
                                <option value="07">07 | Material usage for production</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2 row">
                        <label class="col-sm-4 col-form-label fw-bold">Doc. No (Icl.No)</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control form-control-sm bg-light" id="trandoc" name="trandoc" placeholder="Auto Generate (+1)" disabled>
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

                    <!-- Dokumen Bea Cukai Section -->
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
                                    <option value="23">BC 2.3</option>
                                    <option value="40">BC 4.0</option>
                                    <option value="30">BC 3.0</option>
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
                        <button type="button" class="btn btn-primary btn-sm px-4 fw-bold" id="submit_btn" value="New Data">New Data</button>
                        <button type="button" class="btn btn-danger btn-sm px-3 fw-bold" id="cancel_btn">Cancel</button>
                    </div>
                </div>

                <!-- Kolom Kanan: Area Scan QR Code & Tabel Detail Sementara -->
                <div class="col-md-6" id="divdata" style="display:none;">
                    <div class="mb-2">
                        <label class="form-label fw-bold text-danger">Scan QRCode Material (Auto):</label>
                        <textarea rows="2" class="form-control qr-input border-danger shadow-sm" id="qrcode" name="qrcode" placeholder="Arahkan scanner ke sini..." autocomplete="off"></textarea>
                    </div>

                    <!-- Hidden Inputs terisi otomatis oleh JavaScript -->
                    <input type="hidden" id="qrcodeid" name="qrcodeid">
                    <input type="hidden" id="itemid" name="itemid">
                    <input type="hidden" id="poid" name="poid">
                    <input type="hidden" id="rcvdqty" name="rcvdqty" value="0">

                    <h6 class="fw-bold text-secondary mt-3">Detail Item Outgoing (Sementara)</h6>
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

        <hr class="mt-4 mb-3 border-2 border-primary border-opacity-25">

        <!-- BAGIAN BAWAH: RIWAYAT TRANSAKSI OUTGOING -->
        <div id="history_section">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-secondary m-0"><i class="bi bi-clock-history me-1"></i>Riwayat Transaksi Outgoing</h6>
                
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
    var activePlant = '<?php echo isset($_SESSION['active_plant']) ? strtolower(trim($_SESSION['active_plant'])) : "p1"; ?>';

    var $qr = $('#qrcode');
    var $alert = $('#alert-msg');
    var scanTimer; 
    var colorResetTimer;

    // Inisialisasi Select2 Umum & Merchant
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
            url: baseUrl + 'out_list_outgoing.php',
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

    $('#cbc').change(function() {
        if ($(this).is(':checked')) {
            $('#tbc').val('Y'); $('#beacukai').slideDown();
        } else {
            $('#tbc').val(''); $('#beacukai').slideUp();
        }
    });

    // ==========================================
    // FUNGSI ALERT & FEEDBACK 
    // ==========================================
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

    function showAlert(type, text) {
        $alert.removeClass('alert-success alert-danger')
              .addClass('alert-' + type)
              .html('<strong>' + (type === 'success' ? 'Info:' : 'Peringatan!') + '</strong> ' + text)
              .show(); 
              
        setTimeout(function() { $alert.hide(); }, 1500);
    }

    function loadDetailList() {
        var tranid = $('#tranid').val();
        if (!tranid) return;
        $.ajax({
            type: 'POST',
            url: baseUrl + 'out_list_detail.php',
            data: { tranid: tranid },
            success: function(res) {
                $('#listdata').html(res);
            }
        });
    }

    $('#submit_btn').click(function(e) {
        e.preventDefault();
        var currentVal = $(this).text();

        if (currentVal === 'New Data') {
            if ($('#trandate').val() === '') { showAlert('danger', 'Isi tanggal transaksi.'); return; }
            if ($('#trty').val() === '') { showAlert('danger', 'Pilih jenis transaksi.'); return; }

            $.ajax({
                type: 'POST',
                url: baseUrl + 'out_new_data.php',
                data: $('#formOutgoing').serialize(),
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        playSound('success');
                        $('#tranid').val(res.tranid);
                        $('#tranid_view').val(res.tranid);
                        $('#trandoc').val(res.trandoc);
                        $('#trandoc_hidden').val(res.trandoc);

                        $('#submit_btn').text('Submit').removeClass('btn-primary').addClass('btn-success');
                        $('#trandate, #trty, #merchant, #cbc, #bcty, #bcno, #bcdate').prop('disabled', true);
                        
                        $('#divdata').fadeIn();
                        $qr.focus();
                        showAlert('success', 'Header dibuat! Silakan scan QR Code.');
                        
                        loadHistoryTable();
                    } else {
                        playSound('error');
                        showAlert('danger', 'Error: ' + res.message);
                    }
                }
            });
        } else {
            if ($('#totaldetail').val() == 0 || !$('#totaldetail').val()) {
                playSound('error');
                showAlert('danger', 'Belum ada detail item yang di-scan.');
                $qr.focus(); return;
            }

            $('#formOutgoing').find(':disabled').prop('disabled', false);
            var serializedData = $('#formOutgoing').serialize();
            $('#trandate, #trty, #merchant, #cbc, #bcty, #bcno, #bcdate, #submit_btn').prop('disabled', true);

            $.ajax({
                type: 'POST',
                url: baseUrl + 'out_save_data.php',
                data: serializedData,
                success: function(msg) {
                    if (msg.trim() === 'true') {
                        playSound('success');
                        showAlert('success', 'Data transaksi berhasil disimpan!');
                        loadHistoryTable(); 
                        setTimeout(function() { location.reload(); }, 1500); 
                    } else {
                        playSound('error');
                        showAlert('danger', 'Gagal menyimpan data transaksi secara final.');
                        $('#submit_btn').prop('disabled', false);
                    }
                }
            });
        }
    });

    $('#cancel_btn').click(function(e) {
        e.preventDefault();
        location.reload();
    });

    // ==========================================
    // LOGIKA SCAN SUPER INSTAN (ENTER KEY DETECTION)
    // ==========================================
    function processQRCode(str) {
        if (str.trim() === '') return;

        // Kosongkan form seketika dan kunci pakai 'readonly' (bukan 'disabled' agar fokus tidak hilang)
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
            return false;
        }

        $('#qrcodeid').val(qrId);
        $('#poid').val(poId);
        $('#rcvdqty').val(qty);
        $('#itemid').val(itemId);

        var tranid = $('#tranid').val();

        // Cek duplikasi QR
        $.ajax({
            type: 'POST',
            url: baseUrl + 'out_check_qr.php',
            data: { qrcodeid: qrId, tranid: tranid },
            success: function(msg) {
                if (msg.trim() === 'false') {
                    playSound('error');
                    showAlert('danger', 'QR Code sudah ada di transaksi lain!');
                    setInputFeedback('error'); 
                    $qr.prop('readonly', false).focus();
                } else {
                    // Eksekusi Simpan Detail
                    $.ajax({
                        type: 'POST',
                        url: baseUrl + 'out_simpan_detail.php',
                        data: {
                            tranid: tranid,
                            itemid: itemId,
                            poid: poId,
                            rcvdqty: qty,
                            qrcodeid: qrId
                        },
                        dataType: 'json',
                        success: function(res) {
                            if (res.status === 'success') {
                                playSound('success');
                                setInputFeedback('success'); // Tampil tulisan Hijau 'Sukses!'
                                loadDetailList();
                            } else {
                                playSound('error');
                                showAlert('danger', res.message || 'Gagal menyimpan item detail.');
                                setInputFeedback('error'); // Tampil tulisan Merah 'Gagal!'
                            }
                        },
                        error: function() {
                            playSound('error');
                            showAlert('danger', 'Gagal menghubungi server penyimpanan.');
                            setInputFeedback('error');
                        },
                        complete: function() {
                            // Lepas readonly dan kembalikan fokus
                            $qr.prop('readonly', false).focus();
                        }
                    });
                }
            },
            error: function() {
                playSound('error');
                showAlert('danger', 'Koneksi ke server terputus.');
                setInputFeedback('error');
                $qr.prop('readonly', false).focus();
            }
        });
    }

    // Pemicu Utama: Tombol Enter dari Scanner
    $qr.on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            var str = $(this).val();
            clearTimeout(scanTimer); // Batalkan timer cadangan
            processQRCode(str);      // Langsung eksekusi tanpa jeda milidetik!
        }
    });

    // Pemicu Cadangan (Jika Scanner tidak mengirim Enter, walau jarang terjadi)
    $qr.on('input', function() {
        if ($(this).hasClass('scan-success') || $(this).hasClass('scan-error')) {
            $(this).removeClass('scan-success scan-error').attr('placeholder', 'Arahkan scanner ke sini...');
            clearTimeout(colorResetTimer);
        }

        var str = $(this).val();
        clearTimeout(scanTimer);
        
        // PERBAIKAN: Waktu jeda diperpanjang jadi 800ms untuk antisipasi scanner lambat/lag
        scanTimer = setTimeout(function() {
            if ($qr.val().trim() !== '') {
                // PERBAIKAN: Cek apakah QR sudah lengkap. 
                // Syarat lengkap adalah minimal ada 4 buah tanda pipa (|) atau panjang array >= 5
                var parts = str.split('|');
                if (parts.length >= 5) {
                    processQRCode(str);
                }
            }
        }, 800); 
    });

    $(document).on('click', '.removedetail', function() {
        var tranid = $(this).attr('tranid');
        var lineno = $(this).attr('lineno');
        if (confirm("Hapus item detail baris " + lineno + "?")) {
            $.ajax({
                type: 'POST',
                url: baseUrl + 'out_hapus_detail.php',
                data: { tranid: tranid, lineno: lineno },
                success: function() { loadDetailList(); $qr.focus(); }
            });
        }
    });

    $(document).on('click', '.remove', function(e) {
        e.preventDefault();
        var tranid = $(this).attr('tranid');
        if (confirm("Apakah data outgoing TRAN_ID: " + tranid + " akan dihapus permanen?")) {
            $.ajax({
                type: "POST",
                url: baseUrl + 'out_hapus.php',
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

    $(document).on('click', '.edit', function(e) {
        e.preventDefault();
        var tranid = $(this).attr('tranid');
        $('#tranid').val(tranid);
        $('#sts').val('Edit');

        $.ajax({
            type: "POST",
            url: baseUrl + 'out_get_by_id.php',
            data: { tranid: tranid },
            dataType: 'json',
            success: function(res) {
                if (res.response === 'true') {
                    var d = res.message[0];
                    $('#trandate').val(d.adate);
                    $('#trty').val(d.trty).trigger('change');
                    $('#trandoc').val(d.docno);
                    $('#trandoc_hidden').val(d.docno);
                    
                    if (d.supcode) {
                        var newOption = new Option(d.supcode + ' | ' + d.supname, d.supcode, true, true);
                        $('#merchant').append(newOption).trigger('change');
                    }

                    $('#submit_btn').text('Submit').removeClass('btn-primary').addClass('btn-success');
                    $('#divdata').fadeIn();
                    $('#tranid_view').val(tranid);
                    $('html, body').animate({ scrollTop: 0 }, 'fast');
                    
                    loadDetailList();
                }
            }
        });
    });

    $(document).on('click', '.cetak', function(e) {
        e.preventDefault();
        var tranid = $(this).attr('tranid');
        var trty = $(this).attr('trty'); 
        
        var mappingTemplate = {
            '02': 'slip', 
            '03': 'slip', 
            '05': 'spb',  
            '07': 'spb'   
        };
        
        var templateType = mappingTemplate[trty] ? mappingTemplate[trty] : 'slip';
        var reportFile = '';
        
        if (activePlant === 'p2' || activePlant === 'plan2' || activePlant === 'plant2') {
            reportFile = templateType + '2.php';
        } else {
            reportFile = templateType + '.php';
        }
        
        var destination = baseUrl + reportFile + '?tranid=' + tranid;
        
        var height = 600; var width = 800;
        var leftPosition = (window.screen.width / 2) - (width / 2);
        var topPosition = (window.screen.height / 2) - (height / 2);
        
        window.open(destination, "PrintSlip", "status=no,height=" + height + ",width=" + width + ",resizable=yes,left=" + leftPosition + ",top=" + topPosition + ",toolbar=no,menubar=no,scrollbars=yes");
    });
});
</script>

</body>
</html>