<?php
require_once __DIR__ . "/../config/global.php";

$sop_id       = null;
$sop_date     = '-';
$sop_rem      = 'Tidak ada SOP aktif';
$history_html = '';
$scan_count   = 0;

// PERBAIKAN: Mengambil data login plant dari session 'active_plant' agar dinamis
$current_plan = isset($_SESSION['active_plant']) ? strtoupper($_SESSION['active_plant']) : 'PLAN1';

if ($conn !== false) {
    // Ambil SOP Aktif... (kode selanjutnya tetap sama)
    // Ambil SOP Aktif
    $sqlSop = "SELECT TOP 1 SOP_ID, SOP_SDATE, SOP_REM FROM SOP WHERE SOP_FINISHED = 'F' ORDER BY SOP_SDATE DESC";
    $stmtSop = sqlsrv_query($conn, $sqlSop);

    if ($stmtSop !== false && sqlsrv_has_rows($stmtSop)) {
        $rowSop = sqlsrv_fetch_array($stmtSop, SQLSRV_FETCH_ASSOC);
        $sop_id = $rowSop['SOP_ID'];
        
        if ($rowSop['SOP_SDATE'] instanceof DateTime) {
            $sop_date = $rowSop['SOP_SDATE']->format('d-M-Y');
        } else {
            $sop_date = htmlspecialchars((string)$rowSop['SOP_SDATE'], ENT_QUOTES, 'UTF-8');
        }
        $sop_rem = htmlspecialchars((string)$rowSop['SOP_REM'], ENT_QUOTES, 'UTF-8');
        sqlsrv_free_stmt($stmtSop);

        // Ambil Riwayat Scan
        $sqlHistory = "
            SELECT TOP 50 
                TAGS.TAG_NO, 
                TAGS.QRCODE_ID, 
                TAGS.TAG_QTY, 
                ISNULL(ITEMS.ITEM_CODE, '-') AS ITEM_CODE, 
                ISNULL(ITEMS.ITEM_NAME, 'Unknown Item') AS ITEM_NAME
            FROM TAGS
            INNER JOIN SOP ON TAGS.SOP_ID = SOP.SOP_ID
            LEFT JOIN ITEMS ON TAGS.ITEM_ID = ITEMS.ITEM_ID
            WHERE SOP.SOP_ID = ?
            ORDER BY TAGS.TAG_NO DESC
        ";
        $stmtHist = sqlsrv_query($conn, $sqlHistory, array($sop_id));
        
        if ($stmtHist !== false) {
            while ($rowH = sqlsrv_fetch_array($stmtHist, SQLSRV_FETCH_ASSOC)) {
                $scan_count++;
                $h_tag  = htmlspecialchars($rowH['TAG_NO'], ENT_QUOTES, 'UTF-8');
                $h_qr   = htmlspecialchars($rowH['QRCODE_ID'], ENT_QUOTES, 'UTF-8');
                $h_code = htmlspecialchars($rowH['ITEM_CODE'], ENT_QUOTES, 'UTF-8');
                $h_name = htmlspecialchars($rowH['ITEM_NAME'], ENT_QUOTES, 'UTF-8');
                $h_qty  = (float)$rowH['TAG_QTY'];
                
                $history_html .= '<tr>';
                $history_html .= '<td>' . $scan_count . '</td>';
                $history_html .= '<td class="fw-bold text-success">' . $h_tag . '</td>';
                $history_html .= '<td class="fw-bold text-primary">' . $h_qr . '</td>';
                $history_html .= '<td>' . $h_code . '</td>';
                $history_html .= '<td class="text-start">' . $h_name . '</td>';
                $history_html .= '<td>' . $h_qty . '</td>';
                $history_html .= '<td>-</td>';
                $history_html .= '<td><span class="badge bg-secondary"><i class="bi bi-clock-history"></i> Tersimpan</span></td>';
                // Tambahan Tombol Hapus (Action)
                $history_html .= '<td><button type="button" class="btn btn-danger btn-sm px-2 py-0 btn-delete" data-tag="' . $h_tag . '" title="Hapus Data"><i class="bi bi-trash"></i></button></td>';
                $history_html .= '</tr>';
            }
            sqlsrv_free_stmt($stmtHist);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem SOP - QR Code Scanner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; }
        .main-container { max-width: 1050px; margin: 30px auto; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .table-responsive { max-height: 400px; overflow-y: auto; }
        
        /* Modifikasi Input Kecepatan Tinggi & Animasi Flag */
        .qr-input { 
            font-size: 1.3rem; 
            padding: 15px; 
            text-align: center; 
            letter-spacing: 2px; 
            transition: all 0.1s ease-in-out;
        }
        .qr-input:disabled { background-color: #e9ecef; cursor: not-allowed; }
        
        .scan-success { 
            background-color: #d4edda !important; 
            border-color: #c3e6cb !important; 
            color: #155724 !important; 
        }
        .scan-success::placeholder { 
            color: #155724 !important; 
            font-weight: bold; 
            font-size: 1.5rem;
        }

        .scan-error { 
            background-color: #f8d7da !important; 
            border-color: #f5c6cb !important; 
            color: #721c24 !important; 
        }
        .scan-error::placeholder { 
            color: #721c24 !important; 
            font-weight: bold; 
            font-size: 1.5rem;
        }
    </style>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
</head>
<body>

<div class="container main-container">
    <div class="card p-4">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <h3 class="fw-bold m-0 pt-1 text-dark">
                <i class="bi bi-upc-scan me-2"></i>Scan QR (<?php echo htmlspecialchars($current_plan, ENT_QUOTES, 'UTF-8'); ?>)
            </h3>
            <div class="bg-light border border-secondary border-opacity-25 rounded px-3 py-2 text-end shadow-sm">
                <div class="text-primary fw-bold" style="font-size: 0.95rem;">
                    <i class="bi bi-calendar-event me-1"></i> SOP Date: <?php echo $sop_date; ?>
                </div>
                <div class="text-secondary" style="font-size: 0.85rem; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo $sop_rem; ?>">
                    <i class="bi bi-journal-text me-1"></i> <?php echo $sop_rem; ?>
                </div>
            </div>
        </div>

        <div id="alert-message" class="alert" style="display: none; padding: 10px;" role="alert"></div>

        <div class="mb-3">
            <label for="loc_id" class="form-label fw-bold">1. Pilih Lokasi Gudang (PRD / WHS):</label>
            <select id="loc_id" name="loc_id" class="form-select shadow-sm">
                <option value="">-- Memuat Lokasi... --</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="qr_code" class="form-label fw-bold text-danger">2. Input / Scan QR Code (Auto):</label>
            <!-- Default dalam kondisi disabled jika lokasi belum terpilih -->
            <input type="text" id="qr_code" name="qr_code" class="form-control border-danger shadow-sm qr-input" autocomplete="off" placeholder="Arahkan scanner ke sini..." disabled>
        </div>
        
        <div class="text-muted small mt-1">
            <i class="bi bi-info-circle"></i> Sistem akan memproses otomatis setelah QR terdeteksi.
        </div>
    </div>

    <div class="card p-0 overflow-hidden">
        <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-check me-2"></i>Riwayat Scan</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 text-center align-middle" id="scan_history_table">
                <thead class="table-light sticky-top">
                    <tr>
                        <th width="5%">No</th>
                        <th width="10%">Tag No</th>
                        <th width="17%">QR Code ID</th>
                        <th width="12%">Item Code</th>
                        <th width="20%">Item Name</th>
                        <th width="6%">Qty</th>
                        <th width="5%">Unit</th>
                        <th width="15%">Status</th>
                        <th width="10%">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($history_html != ''): ?>
                        <?php echo $history_html; ?>
                    <?php else: ?>
                        <tr id="empty_row">
                            <td colspan="9" class="text-muted py-4">Belum ada data scan di sesi ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
    var $loc      = $('#loc_id');
    var $qr       = $('#qr_code');
    var $alert    = $('#alert-message');
    var scanCount = <?php echo $scan_count; ?>; 
    var scanTimer; 
    var colorResetTimer;

    // AJAX Fetch Locations
    $.ajax({
        url: 'get_locations.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            $loc.empty();
            if(response.status === 'success') {
                $loc.append('<option value="">-- Pilih Lokasi --</option>');
                var defaultLocId = '';
                $.each(response.data, function(index, loc) {
                    var isSelected = '';
                    if (loc.LOC_CODE === 'WHS') {
                        isSelected = ' selected';
                        defaultLocId = loc.LOC_ID;
                    }
                    $loc.append('<option value="' + loc.LOC_ID + '"' + isSelected + '>' + loc.LOC_CODE + ' - ' + loc.LOC_NAME + '</option>');
                });
                if (defaultLocId !== '') {
                    $loc.val(defaultLocId).trigger('change');
                }
            } else {
                $loc.append('<option value="">Gagal memuat lokasi</option>');
                showAlert('danger', response.message);
            }
        }
    });

    $loc.change(function() {
        if($(this).val() !== '') {
            $qr.prop('disabled', false).focus();
            $alert.hide();
        } else {
            // Gunakan disabled HANYA jika memang form tidak boleh diisi (lokasi kosong)
            $qr.prop('disabled', true).val('');
        }
    });

    $(document).click(function(e) {
        if ($loc.val() !== '' && !$(e.target).closest('select').length && !$(e.target).closest('.btn').length) {
            $qr.focus();
        }
    });

    // ==========================================
    // FUNGSI ALERT & VISUAL FLAG INSTAN
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
        }, 500); // Mereset form sangat cepat ke warna abu (0.5 detik)
    }

    function showAlert(type, text) {
        $alert.removeClass('alert-success alert-danger alert-info')
              .addClass('alert-' + type).html(text).show();
        setTimeout(function() { $alert.hide(); }, 1500);
    }

    // ==========================================
    // LOGIKA SCAN SUPER INSTAN (ENTER KEY DETECTION)
    // ==========================================
    function processQRCode(qrText) {
        if(qrText.trim() === '') return false;
        
        var locId = $loc.val();
        
        // Gunakan READONLY alih-alih disabled agar tidak stuck/hang
        $qr.val('').prop('readonly', true);
        
        var qrParts = qrText.split('|');
        var qrId = '-', uiQty = '-', uiUnit = '-';

        // Smart Parsing 
        if (qrParts.length >= 7) {
            qrId = qrParts[0]; uiQty = qrParts[4]; uiUnit = qrParts[5];
        } else if (qrParts.length >= 5) {
            qrId = qrParts[0]; uiQty = qrParts[2]; uiUnit = qrParts[3];
        } else {
            playSound('error');
            showAlert('danger', 'Gagal: Format QR salah! Pemisah segmen tidak dikenali.');
            setInputFeedback('error');
            $qr.prop('readonly', false).focus();
            return false;
        }

        $.ajax({
            url: 'proses_opname.php', 
            type: 'POST',
            data: { qr_text: qrText, loc_id: locId },
            dataType: 'json',
            success: function(response) {
                if(response.status === 'success') {
                    playSound('success');
                    setInputFeedback('success'); // Tampil warna Hijau
                    
                    appendGridRow(
                        response.tag_no, 
                        qrId, 
                        response.item_code, 
                        response.item_name, 
                        response.qty,   
                        response.unit,  
                        '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Sukses</span>',
                        response.tag_no // Kirim tag no untuk action hapus
                    );
                } else {
                    playSound('error');
                    showAlert('danger', response.message);
                    setInputFeedback('error'); // Tampil warna Merah
                    
                    appendGridRow(
                        '-', qrId, '-', 'Gagal / Ditolak', uiQty, uiUnit, 
                        '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Ditolak</span>',
                        '-' 
                    );
                }
            },
            error: function() {
                playSound('error');
                showAlert('danger', 'Terjadi kesalahan jaringan atau server putus.');
                setInputFeedback('error');
            },
            complete: function() {
                // Lepaskan penguncian dan kembalikan fokus kursor
                $qr.prop('readonly', false).focus();
            }
        });
    }

    // PEMICU UTAMA: Tekanan "Enter" dari Scanner pabrik
    $qr.on('keypress', function(e) {
        if (e.which === 13) { 
            e.preventDefault(); 
            var str = $(this).val();
            clearTimeout(scanTimer);
            processQRCode(str);
        }
    });

    // Pemicu Cadangan (Dipercepat ke 50ms)
    $qr.on('input', function() {
        if ($(this).hasClass('scan-success') || $(this).hasClass('scan-error')) {
            $(this).removeClass('scan-success scan-error').attr('placeholder', 'Arahkan scanner ke sini...');
            clearTimeout(colorResetTimer);
        }
        
        var str = $(this).val();
        clearTimeout(scanTimer);
        
        scanTimer = setTimeout(function() {
            if ($qr.val().trim() !== '') {
                processQRCode(str);
            }
        }, 50); 
    });

    // ==========================================
    // LOGIKA HAPUS DENGAN PASSWORD
    // ==========================================
    $(document).on('click', '.btn-delete', function(e) {
        e.preventDefault();
        var tagNo = $(this).data('tag');
        
        if (tagNo === '-' || !tagNo) {
            showAlert('danger', 'Data gagal/sementara tidak dapat dihapus.');
            $qr.focus();
            return;
        }

        var pwd = prompt("PERINGATAN: Menghapus data scan.\n\nMasukkan password otorisasi untuk Tag No: " + tagNo);
        
        if (pwd === null) { 
            $qr.focus(); return; // Batal ditekan
        }
        
        // Cek hardcode password sesuai instruksi
        if (pwd !== "q9tj9") {
            playSound('error');
            showAlert('danger', 'Password salah! Penghapusan dibatalkan.');
            $qr.focus();
            return;
        }

        var $row = $(this).closest('tr');

        // Pastikan Anda memiliki file "delete_opname.php" yang menangani request ini
        $.ajax({
            url: 'delete_opname.php', 
            type: 'POST',
            data: { tag_no: tagNo },
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    playSound('success');
                    showAlert('success', 'Data Tag ' + tagNo + ' berhasil dihapus.');
                    $row.fadeOut(300, function() { $(this).remove(); });
                } else {
                    playSound('error');
                    showAlert('danger', res.message || 'Gagal menghapus data.');
                }
            },
            error: function() {
                playSound('error');
                showAlert('danger', 'Terjadi kesalahan sistem saat menghapus data.');
            },
            complete: function() {
                $qr.focus();
            }
        });
    });

    function appendGridRow(tagNo, qrId, itemCode, itemName, qty, unit, statusHtml, deleteTag) {
        if ($('#empty_row').length) { $('#empty_row').hide(); }
        scanCount++;
        
        // Buat logic penentuan tombol action
        var actionHtml = '-';
        if (deleteTag !== '-') {
            actionHtml = '<button type="button" class="btn btn-danger btn-sm px-2 py-0 btn-delete" data-tag="' + deleteTag + '" title="Hapus Data"><i class="bi bi-trash"></i></button>';
        }
        
        var newRow = '<tr>' +
            '<td>' + scanCount + '</td>' +
            '<td class="fw-bold text-success">' + tagNo + '</td>' +
            '<td class="fw-bold text-primary">' + qrId + '</td>' +
            '<td>' + itemCode + '</td>' +
            '<td class="text-start">' + itemName + '</td>' +
            '<td>' + qty + '</td>' +
            '<td>' + unit + '</td>' +
            '<td>' + statusHtml + '</td>' +
            '<td>' + actionHtml + '</td>' +
        '</tr>';
        
        $('#scan_history_table tbody').prepend(newRow);
    }
});
</script>

</body>
</html>