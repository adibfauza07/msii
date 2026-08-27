<?php
require_once 'config.php';

function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

$message = "";

// ==========================================================
// PROSES INSERT QUOTATION BARU
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_quote') {
    
    $vendor_id   = isset($_POST['vendor_id']) ? trim($_POST['vendor_id']) : '';
    $item_code   = isset($_POST['item_code']) ? trim($_POST['item_code']) : '';
    $unit_price  = isset($_POST['unit_price']) ? (float)$_POST['unit_price'] : 0;
    $valid_from  = isset($_POST['valid_from']) ? trim($_POST['valid_from']) : '';
    $valid_until = isset($_POST['valid_until']) ? trim($_POST['valid_until']) : '';

    if (!empty($vendor_id) && !empty($item_code) && $unit_price > 0 && !empty($valid_from) && !empty($valid_until)) {
        
        // Non-aktifkan penawaran lama untuk item dan vendor yang sama agar tidak bentrok
        $update_old = "UPDATE Quotation SET is_active = 0 WHERE vendor_id = ? AND item_code = ?";
        sqlsrv_query($conn, $update_old, array($vendor_id, $item_code));

        // Insert Quotation Baru
        $insert_sql = "INSERT INTO Quotation (vendor_id, item_code, unit_price, valid_from, valid_until, is_active) 
                       VALUES (?, ?, ?, ?, ?, 1)";
        $params = array($vendor_id, $item_code, $unit_price, $valid_from, $valid_until);
        
        $stmt_insert = sqlsrv_query($conn, $insert_sql, $params);
        
        if ($stmt_insert) {
            $message = "<div class='alert alert-success'>Quotation / Harga Vendor berhasil disimpan dan dikunci.</div>";
            sqlsrv_free_stmt($stmt_insert);
        } else {
            $message = "<div class='alert alert-danger'>Gagal menyimpan quotation: " . print_r(sqlsrv_errors(), true) . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Semua kolom wajib diisi dengan benar (Harga > 0).</div>";
    }
}

// ==========================================================
// AMBIL DATA UNTUK DROPDOWN & TABEL
// ==========================================================
$sql_vendor = "SELECT vendor_id, vendor_name FROM Master_Vendor WHERE is_active = 1 ORDER BY vendor_name ASC";
$stmt_vendor = sqlsrv_query($conn, $sql_vendor);

// PERBAIKAN BUG DISINI
$sql_item = "SELECT item_code, item_name, uom FROM Master_Item WHERE is_active = 1 ORDER BY item_name ASC";
$stmt_item = sqlsrv_query($conn, $sql_item); 

// Query List Quotation dengan JOIN (Kompatibel SQL Server 2008)
$sql_list = "
    SELECT q.quote_id, v.vendor_name, i.item_code, i.item_name, q.unit_price, q.valid_from, q.valid_until, q.is_active
    FROM Quotation q
    JOIN Master_Vendor v ON q.vendor_id = v.vendor_id
    JOIN Master_Item i ON q.item_code = i.item_code
    ORDER BY q.created_at DESC
";
$stmt_list = sqlsrv_query($conn, $sql_list);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Quotation - ERP Budgeting</title>
    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Select2 CSS untuk Auto-complete -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <style>
        body { background-color: #f4f6f9; font-family: Tahoma, sans-serif; font-size: 13px; padding: 20px; }
        .card-header { font-weight: bold; font-size: 14px; }
        .table th, .table td { vertical-align: middle; }
        
        /* Penyesuaian tinggi Select2 agar setara dengan form-control bootstrap 4 */
        .select2-container .select2-selection--single {
            height: 38px !important;
            border: 1px solid #ced4da !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
        }
    </style>
</head>
<body>

    <div class="container-fluid">
        <div class="row">
            
            <!-- KOLOM FORM INPUT (KIRI) -->
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-file-invoice-dollar"></i> Input Quotation Harga Vendor
                    </div>
                    <div class="card-body">
                        <?php echo $message; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_quote">
                            
                            <div class="form-group">
                                <label>Pilih Vendor / Supplier <span class="text-danger">*</span></label>
                                <!-- Tambahkan class "select2-search" disini -->
                                <select name="vendor_id" class="form-control select2-search" required>
                                    <option value="">-- Pilih Vendor --</option>
                                    <?php 
                                    if ($stmt_vendor !== false) {
                                        while ($v = sqlsrv_fetch_array($stmt_vendor, SQLSRV_FETCH_ASSOC)) {
                                            echo '<option value="'.h($v['vendor_id']).'">'.h($v['vendor_id']).' - '.h($v['vendor_name']).'</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Pilih Barang / Item <span class="text-danger">*</span></label>
                                <!-- Tambahkan class "select2-search" disini -->
                                <select name="item_code" class="form-control select2-search" required>
                                    <option value="">-- Pilih Barang --</option>
                                    <?php 
                                    if ($stmt_item !== false) {
                                        while ($i = sqlsrv_fetch_array($stmt_item, SQLSRV_FETCH_ASSOC)) {
                                            echo '<option value="'.h($i['item_code']).'">'.h($i['item_code']).' - '.h($i['item_name']).' ('.h($i['uom']).')</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Harga Satuan (Unit Price Rp) <span class="text-danger">*</span></label>
                                <input type="number" name="unit_price" class="form-control" placeholder="0" min="0" step="any" required>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Berlaku Dari <span class="text-danger">*</span></label>
                                    <input type="date" name="valid_from" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Berlaku Sampai <span class="text-danger">*</span></label>
                                    <input type="date" name="valid_until" class="form-control" value="<?php echo date('Y-12-31'); ?>" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success btn-block mt-3">
                                <i class="fas fa-save"></i> Kunci Quotation Harga
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- KOLOM TABEL DATA (KANAN) ... (Bagian tabel tetap sama seperti aslinya) -->
            <div class="col-md-8">
                <!-- Kode tabel yang ada di dalam col-md-8 tetap dipertahankan persis seperti aslinya. -->
                <!-- Saya persingkat blok ini untuk fokus pada solusi Javascript di bawah. -->
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <i class="fas fa-list"></i> Daftar Quotation Harga Vendor Aktif
                    </div>
                    <!-- ... isi tabel ... -->
                </div>
            </div>

        </div>
    </div>

    <!-- JS Dependencies (Wajib diload di urutan ini) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- Inisialisasi Select2 -->
    <script>
        $(document).ready(function() {
            // Aktifkan Select2 pada elemen dengan class 'select2-search'
            $('.select2-search').select2({
                width: '100%', // Menyesuaikan lebar dengan form-control Bootstrap
                placeholder: function(){
                    $(this).data('placeholder');
                },
                allowClear: true // Menambah tombol silang untuk menghapus pilihan
            });
        });
    </script>

</body>
</html>

<?php
if (isset($stmt_vendor) && $stmt_vendor !== false) sqlsrv_free_stmt($stmt_vendor);
if (isset($stmt_item) && $stmt_item !== false) sqlsrv_free_stmt($stmt_item);
if (isset($stmt_list) && $stmt_list !== false) sqlsrv_free_stmt($stmt_list);
if ($conn !== false) sqlsrv_close($conn);
?>