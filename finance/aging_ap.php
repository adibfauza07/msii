<?php 
include 'layout.php'; 
require_once '../config/database_aging.php';

// Proteksi Fungsi q()
if (!function_exists('q')) {
    function q($sql, $params = array()) {
        global $conn;
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) { die("<pre>" . print_r(sqlsrv_errors(), true) . "</pre>"); }
        return $stmt;
    }
}

// Query Utama Aging AP
// Menggunakan Join yang sudah kita perbaiki sebelumnya (id_supplier_cat)
$sql = "SELECT T.id_ap, S.SUP_COMP, T.invoice_date, T.invoice_number, 
               T.faktur_pajak, T.curr_code, T.amount, T.due_date, B.AccountName, 
               K.SupplierName as NamaKategori, T.deskripsi, DATEDIFF(day, GETDATE(), T.due_date) as sisa_hari
        FROM TRANS_AP T
        LEFT JOIN SUPPLIER S ON T.SUP_ID = S.SUP_ID
        LEFT JOIN MasterBiayaAP B ON T.id_biaya = B.id_biaya
        LEFT JOIN MasterKategoriAP K ON T.id_supplier_cat = K.id_supplier
        WHERE T.is_paid = 0
        ORDER BY T.due_date ASC";
$query = q($sql);
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
    /* Styling agar Select2 menyatu dengan Bootstrap 5 */
    .select2-container--bootstrap-5 .select2-selection {
        border-color: #dee2e6;
    }
</style>

<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between mb-4">
        <h4 class="fw-bold">AGING AP (HUTANG SUPPLIER)</h4>
        
        <div class="btn-group">
            <a href="export_excel_ap.php" target="_blank" class="btn btn-success shadow-sm fw-bold me-1">
                <i class="bi bi-file-earmark-excel"></i> EXCEL
            </a>
            <a href="print_aging_ap.php" target="_blank" class="btn btn-danger shadow-sm fw-bold me-1">
                <i class="bi bi-printer"></i> PDF
            </a>
            <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalInputAP">
                <i class="bi bi-plus-lg"></i> INPUT INVOICE BARU
            </button>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
    <div class="card-body bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary">Filter Supplier</label>
        <select id="filterSupplier" class="form-select select2-filter">
            <option value="">- Semua Supplier -</option>
            <?php
            // Ambil Nama Supplier Unik dari Database
            $qFSup = q("SELECT DISTINCT SUP_CODE, SUP_COMP FROM SUPPLIER ORDER BY SUP_COMP ASC");
            // Contoh pada Dropdown Filter Supplier
while($fs = sqlsrv_fetch_array($qFSup, SQLSRV_FETCH_ASSOC)) {
    // Gunakan SUP_COMP sebagai value untuk pencarian yang akurat di tabel
    echo "<option value='".htmlspecialchars($fs['SUP_COMP'])."'>".$fs['SUP_CODE']." - ".$fs['SUP_COMP']."</option>";
}
            ?>
        </select>
    </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary">Filter No. Invoice</label>
        <input type="text" id="filterInvoice" class="form-control form-control-sm border-danger" placeholder="Ketik nomor invoice...">
    </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary">Filter Kategori</label>
        <select id="filterKategori" class="form-select select2-filter">
            <option value="">- Semua Kategori -</option>
            <?php
            // Ambil Nama Kategori Unik dari Database
            $qFKat = q("SELECT DISTINCT SupplierCode, SupplierName FROM MasterKategoriAP ORDER BY SupplierName ASC");
            while($fk = sqlsrv_fetch_array($qFKat, SQLSRV_FETCH_ASSOC)) {
                echo "<option value='".$fk['SupplierCode'].$fk['SupplierName']."'>".$fk['SupplierCode']." - ".$fk['SupplierName']."</option>";
            }
            ?>
        </select>
    </div>
     <div class="col-md-3">
                <div class="d-grid gap-2 d-md-flex">
                    <button type="button" id="btnApplyFilter" class="btn btn-primary fw-bold flex-fill">
                        <i class="bi bi-search"></i> FILTER
                    </button>
                    <button type="button" id="btnResetFilter" class="btn btn-secondary fw-bold flex-fill">
                        <i class="bi bi-arrow-counterclockwise"></i> RESET
                    </button>
                </div>
            </div>
</div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelAgingAP" class="table table-hover table-sm border align-middle">
                    <thead class="table-danger text-center">
                        <tr>
                            <th>No.</th>
                            <th>Supplier</th>
                            <th>Inv. Date</th>
                            <th>Inv. Number</th>
                            <th>Faktur Pajak</th>
                            <th>Curr</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Kategori</th>
                            <th>Deskripsi</th>
                            <th>Sisa Due</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php 
                        $no = 1;
                        while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): 
                            $sisa = (int)$row['sisa_hari'];
                        ?>
                        <tr id="row-<?= $row['id_ap'] ?>">
                            <td class="text-center"><?= $no++ ?></td>
                            <td class="fw-bold"><?= htmlspecialchars(trim($row['SUP_COMP'])) ?></td>
                            <td class="text-center"><?= ($row['invoice_date']) ? $row['invoice_date']->format('d/m/Y') : '-' ?></td>
                            <td><?= htmlspecialchars($row['invoice_number'] ) ?></td>
                            <td><?= htmlspecialchars($row['faktur_pajak'] ) ?></td>
                            <td class="text-center"><?= $row['curr_code'] ?></td>
                            <td class="text-end"><?= number_format($row['amount'], 2) ?></td>
                            <td class="text-center"><?= ($row['due_date']) ? $row['due_date']->format('d/m/Y') : '-' ?></td>
                            <td><?= htmlspecialchars($row['NamaKategori'] ) ?></td>
                            <td><?= htmlspecialchars($row['deskripsi'] ) ?></td>
                            <td class="text-center fw-bold <?= ($sisa <= 0) ? 'text-danger' : 'text-primary' ?>">
                                <?= ($sisa <= 0) ? "OVERDUE" : $sisa . " HARI" ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-warning" onclick="openEditAP(<?= $row['id_ap'] ?>)">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteAP(<?= $row['id_ap'] ?>, '<?= $row['invoice_number'] ?>')">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'modals_ap.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#tabelAgingAP').DataTable({
        "pageLength": 10,
        "dom": 'lrtip', 
        "order": [[11, "asc"]]
    });

    $('.select2-filter').select2({ theme: 'bootstrap-5', width: '100%' });
    $('.select2-modal').select2({ theme: 'bootstrap-5', dropdownParent: $('#modalInputAP'), width: '100%' });
    $('.select2-edit').select2({ theme: 'bootstrap-5', dropdownParent: $('#modalEditAP'), width: '100%' });

    // ==========================================
    // LOGIC TOMBOL FILTER (VERSI LEBIH AMAN)
    // ==========================================
    // Perbaikan Logic Tombol Filter
$('#btnApplyFilter').click(function() {
    // Mengambil nilai dari elemen input/select
    var valSup = $('#filterSupplier').val(); // Mengambil value dari Select2
    var valInv = $('#filterInvoice').val();  // Mengambil value dari input text
    var valKat = $('#filterKategori').val(); // Mengambil value dari Select2

    // 1. Filter Supplier (Kolom indeks 1)
    // Jika menggunakan Select2, pastikan value yang dicari sesuai dengan teks di tabel
    table.column(1).search(valSup ? valSup : '', true, false);

    // 2. Filter No. Invoice (Kolom indeks 3)
    table.column(3).search(valInv ? valInv : '', true, false);

    // 3. Filter Kategori (Kolom indeks 9)
    table.column(9).search(valKat ? valKat : '', true, false);

    // Menjalankan perintah refresh tabel dengan filter baru
    table.draw();
});
    // ==========================================
    // LOGIC TOMBOL RESET
    // ==========================================
    $('#btnResetFilter').click(function() {
        $('#filterSupplier').val(null).trigger('change');
        $('#filterInvoice').val('');
        $('#filterKategori').val(null).trigger('change');
        table.search('').columns().search('').draw();
    });
});

// Function Modal & Delete tetap sama
function openEditAP(id) {
    $('#modalEditAP form')[0].reset();
    $('.select2-edit').val(null).trigger('change');

    $.ajax({
        url: 'get_ap_detail.php',
        type: 'GET',
        data: { id: id },
        success: function(res) {
            try {
                const data = JSON.parse(res);
                if(data) {
                    $('#edit_id_ap').val(data.id_ap);
                    $('#edit_invoice_number').val(data.invoice_number);
                    $('#edit_invoice_date').val(data.invoice_date);
                    $('#edit_due_date').val(data.due_date);
                    $('#edit_amount').val(data.amount);
                    $('#edit_curr_code').val(data.curr_code);
                    $('#edit_faktur_pajak').val(data.faktur_pajak);
                    $('#edit_is_paid').prop('checked', data.is_paid == 1);
                    
                    $('#edit_sup_id').val(data.SUP_ID).trigger('change');
                    $('#edit_id_kat_ap').val(data.id_supplier_cat).trigger('change');
                    $('#edit_deskripsi').val(data.deskripsi);

                    if(data.id_biaya) { $('#edit_id_biaya').val(data.id_biaya).trigger('change'); } 
                    else { $('#edit_id_biaya').val("").trigger('change'); }
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalEditAP'));
                    modal.show();
                } else { alert("Data AP tidak ditemukan."); }
            } catch (e) { console.error(e); alert("Gagal memproses data JSON."); }
        },
        error: function() { alert("Gagal koneksi server."); }
    });
}

function deleteAP(id, invNum) {
    if (confirm("Hapus permanen invoice AP " + invNum + "?")) {
        window.location.href = "delete_ap.php?id=" + id;
    }
}
</script>