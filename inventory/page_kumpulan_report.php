<?php
// Pastikan file ini di-include dari dashboard utama
require_once __DIR__ . '/../config/database_p1.php';
?>

<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-dark text-white fw-bold text-center py-2">
                <i class="bi bi-folder-fill"></i> KUMPULAN REPORT
            </div>
            <div class="card-body bg-light">
                <form id="formKumpulanReport">
                    <div class="mb-3">
                        <label class="fw-bold small mb-1">Pilih Jenis Laporan:</label>
                        <select id="jenis_laporan" class="form-select border-primary shadow-sm" onchange="aturFormTanggal()">
                            <option value="trans_list">Transaction List</option>
                            <option value="trans_summary">Transaction Summary</option>
                            <option value="daily_stock">Daily Stock Report</option>
                            <option value="stock_analisys_opname">Stock Analysis Opname</option>
                        </select>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6" id="col_start_date">
                            <label id="label_start_date" class="fw-bold small mb-1">Dari Tanggal:</label>
                            <input type="date" id="start_date" class="form-control shadow-sm" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="col-6" id="col_end_date">
                            <label class="fw-bold small mb-1">Sampai Tanggal:</label>
                            <input type="date" id="end_date" class="form-control shadow-sm" value="<?php echo date('Y-m-t'); ?>">
                        </div>
                    </div>

                    <hr>
                    
                    <div class="d-flex gap-2">
                        <button type="button" onclick="cetakReport()" class="btn btn-primary w-100 fw-bold">
                            <i class="bi bi-printer-fill"></i> PRINT PREVIEW
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Fungsi untuk menyembunyikan "Sampai Tanggal" jika memilih Daily Stock
function aturFormTanggal() {
    var jenis = document.getElementById('jenis_laporan').value;
    if (jenis === 'daily_stock') {
        document.getElementById('col_end_date').style.display = 'none';
        document.getElementById('label_start_date').innerText = 'Tanggal (As Per):';
    } else {
        document.getElementById('col_end_date').style.display = 'block';
        document.getElementById('label_start_date').innerText = 'Dari Tanggal:';
    }
}

function cetakReport() {
    var jenis = document.getElementById('jenis_laporan').value;
    var start = document.getElementById('start_date').value;
    var end   = document.getElementById('end_date').value;

    if (!start) { alert('Tanggal wajib diisi!'); return; }

    if (jenis === 'trans_summary') {
        window.open('print_transaction_summary.php?start_date=' + start + '&end_date=' + end, '_blank');
    } else if (jenis === 'trans_list') {
        window.open('print_transaction_list.php?start_date=' + start + '&end_date=' + end, '_blank');
    } else if (jenis === 'daily_stock') {
        window.open('print_daily_stock.php?date=' + start, '_blank');
    } else if (jenis === 'stock_analisys_opname') {
        if (!end) { alert('Sampai Tanggal wajib diisi!'); return; }
        window.open('print_stock_analisys_opname.php?start_date=' + start + '&end_date=' + end, '_blank');
    }
}

window.onload = aturFormTanggal;
</script>