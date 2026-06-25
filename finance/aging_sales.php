
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

// Query Utama Aging Sales
$sql = "SELECT T.id_sales, S.CUST_COMP, T.invoice_date, T.invoice_number, 
               T.faktur_pajak, T.curr_code, T.amount, T.due_date, B.AccountName, 
               K.SalesName, DATEDIFF(day, GETDATE(), T.due_date) as sisa_hari
        FROM TRANS_SALES T
        LEFT JOIN CUST S ON T.CUST_ID = S.CUST_ID
        LEFT JOIN MasterBiayaSales B ON T.id_biaya = B.id_biaya
        LEFT JOIN MasterKategoriSales K ON T.id_kategori_sales = K.id_kategori_sales
        WHERE T.is_paid = 0
        ORDER BY T.due_date ASC";
$query = q($sql);
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
    /* Sedikit perbaikan agar Select2 terlihat rapi di Bootstrap 5 */
    .select2-container--bootstrap-5 .select2-selection {
        border-color: #dee2e6; /* Warna border default bootstrap */
    }
</style>

<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between mb-4">
        <h4 class="fw-bold">AGING SALES (CUSTOMER)</h4>
        
       <div class="btn-group">
            <a href="export_excel_sales.php" id="btnExportExcel" target="_blank" class="btn btn-success shadow-sm fw-bold me-1">
                <i class="bi bi-file-earmark-excel"></i> EXCEL
            </a>
            <a href="print_aging_sales.php" id="btnExportPdf" target="_blank" class="btn btn-danger shadow-sm fw-bold me-1">
                <i class="bi bi-printer"></i> PDF
            </a>
            
            <button class="btn btn-info shadow-sm fw-bold me-1 text-white" data-bs-toggle="modal" data-bs-target="#modalKategoriSales">
                <i class="bi bi-tags-fill"></i> + KATEGORI
            </button>

            <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalInputSales">
                <i class="bi bi-plus-lg"></i> INPUT INVOICE BARU
            </button>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
    <div class="card-body bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary">Filter Customer</label>
                <select id="filterCustomer" class="form-select select2-filter">
                    <option value="">- Semua Customer -</option>
                    <?php
                    $qFCust = q("SELECT DISTINCT CUST_CODE, CUST_COMP FROM CUST ORDER BY CUST_COMP ASC");
while($fc = sqlsrv_fetch_array($qFCust, SQLSRV_FETCH_ASSOC)) {
    // Gunakan CUST_COMP sebagai value untuk pencarian yang akurat di tabel
    echo "<option value='".htmlspecialchars($fc['CUST_COMP'])."'>".$fc['CUST_CODE']." - ".$fc['CUST_COMP']."</option>";
}
                    ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary">Filter No. Invoice</label>
                <input type="text" id="filterInvoice" class="form-control" placeholder="Ketik nomor invoice...">
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary">Filter Kategori</label>
                <select id="filterKategori" class="form-select select2-filter">
                    <option value="">- Semua Kategori -</option>
                    <?php
                    $qFKat = q("SELECT DISTINCT SalesCode, SalesName FROM MasterKategoriSales ORDER BY SalesName ASC");
                    while($fk = sqlsrv_fetch_array($qFKat, SQLSRV_FETCH_ASSOC)) {
                       $kategori_bersih = trim($fk['SalesName']);
        echo "<option value='".$fk['SalesCode'].$kategori_bersih."'>".$fk['SalesCode']." - ".$kategori_bersih."</option>";
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
                <table id="tabelAgingSales" class="table table-hover table-sm border align-middle">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>No.</th>
                            <th>Customer</th>
                            <th>Inv. Date</th>
                            <th>Inv. Number</th>
                            <th>Faktur Pajak</th>
                            <th>Curr</th>
                            <th>Amount</th>
                            <th>Due Date</th>

                            <th>Kategori</th>
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
                        <tr id="row-<?= $row['id_sales'] ?>">
                            <td class="text-center"><?= $no++ ?></td>
                            <td class="fw-bold"><?= htmlspecialchars(trim($row['CUST_COMP'])) ?></td>
                            <td class="text-center"><?= ($row['invoice_date']) ? $row['invoice_date']->format('d/m/Y') : '-' ?></td>
                            <td><?= htmlspecialchars(trim($row['invoice_number'])) ?></td>
                            <td><?= htmlspecialchars($row['faktur_pajak']) ?></td>
                            <td class="text-center"><?= $row['curr_code'] ?></td>
                            <td class="text-end"><?= number_format($row['amount'], 2) ?></td>
                            <td class="text-center"><?= ($row['due_date']) ? $row['due_date']->format('d/m/Y') : '-' ?></td>

                            <td><?= htmlspecialchars(trim($row['SalesName'])) ?></td>
                            <td class="text-center fw-bold <?= ($sisa <= 0) ? 'text-danger' : 'text-primary' ?>">
                                <?= ($sisa <= 0) ? "OVERDUE" : $sisa . " HARI" ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-warning" onclick="openEditSales(<?= $row['id_sales'] ?>)">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteSales(<?= $row['id_sales'] ?>, '<?= $row['invoice_number'] ?>')">
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

<?php include 'modals_sales.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#tabelAgingSales').DataTable({
        "pageLength": 10,
        "dom": 'lrtip', 
        "order": [[10, "asc"]] 
    });

    // Inisialisasi Select2
    $('.select2-filter').select2({ theme: 'bootstrap-5', width: '100%' });
    $('.select2-modal').select2({ theme: 'bootstrap-5', dropdownParent: $('#modalInputSales'), width: '100%' });
    $('.select2-edit').select2({ theme: 'bootstrap-5', dropdownParent: $('#modalEditSales'), width: '100%' });

    // ==========================================
    // LOGIC TOMBOL FILTER (VERSI LEBIH AMAN)
    // ==========================================
    $('#btnApplyFilter').click(function() {
        // Ambil value
        var valCust = $('#filterCustomer').val(); // (Atau filterSupplier untuk AP)
        var valInv  = $('#filterInvoice').val();
        var valKat  = $('#filterKategori').val();

// 1. FILTER DROPDOWN (Customer/Supplier) - Kolom 1
        if (valCust) {
            // Gunakan escapeRegex untuk menangani simbol ( ) . + * agar tidak error
            // Tambahkan ^ dan $ agar pencarian PERSIS (Exact Match)
            var regex = '^' + $.fn.dataTable.util.escapeRegex(valCust) + '$';
            table.column(1).search(regex, true, false);
        } else {
            table.column(1).search('');
        }

        // 2. FILTER TEXT (Invoice) - Kolom 3
        table.column(3).search(valInv);

        // 3. FILTER DROPDOWN (Kategori) - Kolom 9
        if (valKat) {
            var regexKat = '^' + $.fn.dataTable.util.escapeRegex(valKat) + '$';
            table.column(9).search(regexKat, true, false);
        } else {
            table.column(9).search('');
        }

        // Eksekusi
        table.draw();
    });

    // ==========================================
    // LOGIC TOMBOL RESET
    // ==========================================
    $('#btnResetFilter').click(function() {
        $('#filterCustomer').val(null).trigger('change');
        $('#filterInvoice').val('');
        $('#filterKategori').val(null).trigger('change');
        table.search('').columns().search('').draw();
    });
});

// Function Modal & Delete tetap sama
function openEditSales(id) {
    $('#modalEditSales form')[0].reset();
    $('.select2-edit').val(null).trigger('change'); 

    $.ajax({
        url: 'get_sales_detail.php',
        type: 'GET',
        data: { id: id },
        success: function(res) {
            try {
                const data = JSON.parse(res);
                if(data) {
                    $('#edit_id_sales').val(data.id_sales);
                    $('#edit_invoice_number').val(data.invoice_number);
                    $('#edit_invoice_date').val(data.invoice_date);
                    $('#edit_due_date').val(data.due_date);
                    $('#edit_amount').val(data.amount);
                    $('#edit_curr_code').val(data.curr_code);
                    $('#edit_faktur_pajak').val(data.faktur_pajak);
                    $('#edit_is_paid').prop('checked', data.is_paid == 1);
                    
                    $('#edit_cust_id').val(data.CUST_ID).trigger('change');
                    $('#edit_id_kat_sales').val(data.id_kategori_sales).trigger('change');
                    
                    if(data.id_biaya) { $('#edit_id_biaya').val(data.id_biaya).trigger('change'); } 
                    else { $('#edit_id_biaya').val("").trigger('change'); }

                    var modal = new bootstrap.Modal(document.getElementById('modalEditSales'));
                    modal.show();
                }
            } catch (e) { console.error(e); alert("Gagal ambil data."); }
        }
    });
}

function deleteSales(id, invNum) {
    if (confirm("Hapus permanen invoice Sales " + invNum + "?")) {
        window.location.href = "delete_sales.php?id=" + id;
    }
}
</script>