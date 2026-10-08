<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../config/global.php";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material Incoming Scan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 11px; }
        .main-card { max-width: 1100px; margin: 30px auto; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table-compact th, .table-compact td { padding: 4px 8px !important; vertical-align: middle; font-size: 11px; }
        
        .qr-input { 
            font-size: 16px; 
            letter-spacing: 1px; 
            transition: all 0.1s ease-in-out; 
            height: 55px; 
        }
        
        .scan-success { 
            background-color: #d4edda !important; 
            border-color: #c3e6cb !important; 
            text-align: center; 
        }
        .scan-success::placeholder { 
            color: #155724 !important; 
            font-size: 32px !important; 
            font-weight: bold; 
        }

        .scan-error { 
            background-color: #f8d7da !important; 
            border-color: #f5c6cb !important; 
            text-align: center; 
        }
        .scan-error::placeholder { 
            color: #721c24 !important; 
            font-size: 32px !important; 
            font-weight: bold; 
        }

        .ui-autocomplete {
            position: absolute;
            z-index: 9999 !important;
            max-height: 250px;
            overflow-y: auto;
            overflow-x: hidden;
            background-color: #ffffff !important;
            border: 1px solid #ccc;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        #camera-reader {
            width: 100%;
            border-radius: 8px;
            overflow: hidden;
            background: #000;
        }
        #camera-reader video {
            object-fit: cover;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card p-3 main-card border-0">
        <h4 class="fw-bold mb-3 border-bottom pb-2 text-primary">
            <i class="bi bi-box-arrow-in-down me-2"></i>Incoming Material (Scan QR) - Plant: <?php echo strtoupper(isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'P1'); ?>
        </h4>

        <div id="alert-msg" class="alert" style="display: none; padding: 10px;"></div>

        <div class="row mb-3">
            <div class="col-md-12">
                <label class="fw-bold text-secondary mb-1">Pindai Barcode / QR Code:</label>
                <div class="input-group">
                    <input type="text" id="qrcode" class="form-control qr-input border-primary shadow-sm" placeholder="Arahkan scanner fisik atau klik kamera..." autocomplete="off" autofocus>
                    <button class="btn btn-primary px-3 fs-5" type="button" id="btn-open-camera" title="Buka Kamera Video Langsung">
                        <i class="bi bi-camera-video-fill"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2 mt-4">
            <h6 class="fw-bold text-secondary m-0"><i class="bi bi-list-check me-1"></i>Riwayat & Pencarian Berdasarkan RCV No</h6>
            <div class="input-group" style="width: 250px;">
                <input type="text" id="search_history" class="form-control form-control-sm" placeholder="Cari RCV No, Item Code..." autocomplete="off">
                <button class="btn btn-outline-secondary btn-sm" type="button"><i class="bi bi-search"></i></button>
            </div>
        </div>

        <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
            <table class="table table-bordered table-striped table-compact" id="scanned_table">
                <thead class="table-dark text-center sticky-top">
                    <tr>
                        <th width="5%">No</th>
                        <th width="15%">Item Code</th>
                        <th width="25%">Item Name</th>
                        <th width="10%">Qty</th>
                        <th width="18%">QR Code</th>
                        <th width="12%">RCV No</th>
                        <th width="15%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr id="empty-row"><td colspan="7" class="text-center text-muted py-3">Memuat data riwayat...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Live Scanner -->
<div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-camera-video me-1"></i> Kamera Live Scanning</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2 text-center">
                <div id="camera-reader"></div>
                <small class="text-muted d-block mt-2">Arahkan kamera ke QR code, sistem akan membaca secara instan.</small>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
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
        audioEl.play().catch(function(error) { console.log("Audio: " + error); });
    }
}

$(document).ready(function() {
    var baseUrl = '<?php echo ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/"; ?>';
    var activePlant = '<?php echo isset($_SESSION['active_plant']) ? strtolower(trim($_SESSION['active_plant'])) : "p1"; ?>';

    var $qr = $('#qrcode');
    var $alert = $('#alert-msg');
    var searchTimer;
    var scanTimer; 
    var colorResetTimer; 

    function loadHistory(searchQuery = '') {
        $.ajax({
            url: baseUrl + 'get_incoming_history.php',
            type: 'POST',
            data: { search: searchQuery, page: 1 },
            dataType: 'json',
            success: function(res) {
                var $tbody = $('#scanned_table tbody');
                $tbody.empty();
                
                if (res.status === 'success' && res.data.length > 0) {
                    $.each(res.data, function(index, item) {
                        var reportFile = (activePlant === 'p2' || activePlant === 'plan2' || activePlant === 'plant2') ? 'cetak_icl2.php' : 'cetak_icl.php';
                        var printUrl = baseUrl + reportFile + '?rcv_id=' + encodeURIComponent(item.rcv_id);
                        
                        var btnCetak = '<button type="button" class="btn btn-primary btn-xs px-2 py-0 btn-cetak" data-url="'+printUrl+'" title="Print ICL"><i class="bi bi-printer"></i> Cetak</button>';
                        var btnHapus = '<button type="button" class="btn btn-danger btn-xs px-2 py-0 ms-1 btn-delete" data-qr="'+item.qrcode_id+'" title="Hapus Data"><i class="bi bi-trash"></i></button>';
                        
                        var tr = '<tr>' +
                            '<td class="text-center">' + (index + 1) + '</td>' +
                            '<td class="text-center fw-bold">' + item.item_code + '</td>' +
                            '<td class="text-start">' + item.item_name + '</td>' +
                            '<td class="text-end fw-bold text-success pe-2">' + item.qty + '</td>' +
                            '<td class="text-center font-monospace text-primary">' + item.qrcode_id + '</td>' +
                            '<td class="text-center fw-bold text-dark">' + item.rcv_no + '</td>' +
                            '<td class="text-center">' + btnCetak + btnHapus + '</td>' +
                        '</tr>';
                        $tbody.append(tr);
                    });
                } else {
                    $tbody.html('<tr><td colspan="7" class="text-center text-muted py-3">Tidak ada data ditemukan.</td></tr>');
                }
            }
        });
    }

    loadHistory();
    $qr.focus(); 

    $('#search_history').autocomplete({
        source: baseUrl + 'search_item.php', 
        minLength: 2,
        select: function(event, ui) {
            $(this).val(ui.item.value);
            loadHistory(ui.item.value);
            return false;
        }
    });

    $('#search_history').on('keyup', function() {
        var query = $(this).val();
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() { loadHistory(query); }, 400);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#search_history, .btn-cetak, .btn-delete, #btn-open-camera, #cameraModal, a, button, .ui-menu-item').length) {
            $qr.focus();
        }
    });

    function setInputFeedback(status) {
        clearTimeout(colorResetTimer);
        $qr.removeClass('scan-success scan-error').val('').attr('placeholder', 'Scan QR Code...');
        
        if (status === 'success') {
            $qr.addClass('scan-success').attr('placeholder', 'Sukses!');
        } else if (status === 'error') {
            $qr.addClass('scan-error').attr('placeholder', 'Gagal!');
        }

        colorResetTimer = setTimeout(function() {
            $qr.removeClass('scan-success scan-error').attr('placeholder', 'Scan QR Code...');
        }, 500);
    }

    function showAlert(type, text) {
        $alert.removeClass('alert-success alert-danger')
              .addClass('alert-' + type)
              .html('<strong>' + (type === 'success' ? 'Info:' : 'Peringatan!') + '</strong> ' + text)
              .show(); 
              
        setTimeout(function() { $alert.hide(); }, 2000); 
    }

    function processQRCode(rawString) {
        if (!rawString || rawString.trim() === '') return;

        $qr.val('').prop('readonly', true); 

        var qrParts = rawString.split('|');
        if (qrParts.length < 5) {
            playSound('error');
            showAlert('danger', 'Format QR tidak sesuai spesifikasi.');
            setInputFeedback('error'); 
            $qr.prop('readonly', false).focus();
            return false;
        }

        $.ajax({
            url: baseUrl + 'proses_receive_qr.php',
            type: 'POST',
            data: { qrcode: rawString },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    playSound('success');
                    showAlert('success', res.message);
                    setInputFeedback('success'); 
                    loadHistory($('#search_history').val()); 
                } else {
                    playSound('error');
                    showAlert('danger', res.message);
                    setInputFeedback('error'); 
                }
            },
            error: function() {
                playSound('error');
                showAlert('danger', 'Terjadi kesalahan sistem/jaringan.');
                setInputFeedback('error'); 
            },
            complete: function() {
                $qr.prop('readonly', false).focus(); 
            }
        });
    }

    $qr.on('keypress', function(e) {
        if (e.which === 13) { 
            e.preventDefault(); 
            var qrValue = $(this).val();
            clearTimeout(scanTimer);
            processQRCode(qrValue);
        }
    });

    $qr.on('input', function() {
        if ($(this).hasClass('scan-success') || $(this).hasClass('scan-error')) {
            $(this).removeClass('scan-success scan-error').attr('placeholder', 'Scan QR Code...');
            clearTimeout(colorResetTimer);
        }
        
        var qrValue = $(this).val();
        clearTimeout(scanTimer);
        
        scanTimer = setTimeout(function() {
            if ($qr.val().trim() !== '') {
                processQRCode(qrValue);
            }
        }, 50); 
    });

    // ==========================================
    // LOGIKA LIVE STREAM CAMERA
    // ==========================================
    var html5QrCode = null;
    var cameraModalEl = document.getElementById('cameraModal');
    var cameraModal = new bootstrap.Modal(cameraModalEl);

    $('#btn-open-camera').on('click', function() {
        cameraModal.show();
    });

    cameraModalEl.addEventListener('shown.bs.modal', function () {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            cameraModal.hide();
            alert("Akses kamera video streaming membutuhkan koneksi HTTPS.");
            return;
        }

        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("camera-reader");
        }
        
        var config = { 
            fps: 20, 
            qrbox: { width: 260, height: 260 },
            aspectRatio: 1.0 
        };

        html5QrCode.start(
            { facingMode: "environment" }, 
            config, 
            function (decodedText) {
                // Begitu barcode/QR masuk frame, langsung tutup dan eksekusi
                html5QrCode.stop().then(function() {
                    cameraModal.hide();
                    processQRCode(decodedText);
                }).catch(function() {
                    cameraModal.hide();
                    processQRCode(decodedText);
                });
            },
            function (errorMessage) {
                // Frame scanning loop
            }
        ).catch(function(err) {
            cameraModal.hide();
            showAlert('danger', 'Gagal menyalakan video kamera: ' + err);
        });
    });

    cameraModalEl.addEventListener('hidden.bs.modal', function () {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().then(function() {
                html5QrCode.clear();
            }).catch(function(err) {
                console.error("Gagal stop kamera:", err);
            });
        }
        $qr.focus();
    });

    // ==========================================
    // CETAK ICL & HAPUS
    // ==========================================
    $(document).on('click', '.btn-cetak', function(e) {
        e.preventDefault();
        var printUrl = $(this).data('url');
        var width = 850;
        var height = 600;
        var left = (window.screen.width / 2) - (width / 2);
        var top = (window.screen.height / 2) - (height / 2);
        window.open(printUrl, "CetakICL", "status=no,height=" + height + ",width=" + width + ",resizable=yes,left=" + left + ",top=" + top + ",toolbar=no,menubar=no,scrollbars=yes");
        $qr.focus();
    });

    $(document).on('click', '.btn-delete', function(e) {
        e.preventDefault();
        var qrId = $(this).data('qr');
        var pwd = prompt("PERINGATAN: Menghapus data pemasukan.\n\nMasukkan password otorisasi untuk QR: " + qrId);
        
        if (pwd === null || pwd.trim() === "") {
            $qr.focus();
            return; 
        }

        $.ajax({
            url: baseUrl + 'delete_receive.php',
            type: 'POST',
            data: { qrcode_id: qrId, password: pwd },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    playSound('success');
                    showAlert('success', res.message);
                    loadHistory($('#search_history').val());
                } else {
                    playSound('error');
                    showAlert('danger', res.message);
                }
            },
            complete: function() { $qr.focus(); }
        });
    });
});

</script>

</body>
</html>