<?php
require_once 'config.php';

// Fungsi sanitasi standar PHP 5.4 (Mencegah XSS)
function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

$message = "";

// ==========================================================
// 1. PROSES TAMBAH VENDOR BARU (CREATE)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_vendor') {
    
    $vendor_name    = isset($_POST['vendor_name']) ? trim($_POST['vendor_name']) : '';
    $address        = isset($_POST['address']) ? trim($_POST['address']) : '';
    $contact_person = isset($_POST['contact_person']) ? trim($_POST['contact_person']) : '';
    $phone          = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $email          = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (!empty($vendor_name)) {
        
        // --- LOGIKA AUTO GENERATE VENDOR ID (Format: VND-XXXX) ---
        $prefix = 'VND-';
        $sql_last_id = "SELECT TOP 1 vendor_id FROM Master_Vendor WHERE vendor_id LIKE ? ORDER BY vendor_id DESC";
        $stmt_last = sqlsrv_query($conn, $sql_last_id, array($prefix . '%'));
        
        $new_sequence = 1;
        if ($stmt_last && sqlsrv_has_rows($stmt_last)) {
            $row_last = sqlsrv_fetch_array($stmt_last, SQLSRV_FETCH_ASSOC);
            $last_id = $row_last['vendor_id'];
            // Ambil 4 digit terakhir (setelah 'VND-'), convert ke int, tambah 1
            $last_seq = (int)substr($last_id, 4);
            $new_sequence = $last_seq + 1;
        }
        
        // Pad dengan 0 agar selalu 4 digit (0001, 0012, dst)
        $vendor_id = $prefix . str_pad($new_sequence, 4, '0', STR_PAD_LEFT);
        // ---------------------------------------------------------

        // Gunakan Parameterized Query untuk mencegah SQL Injection
        $sql_insert = "INSERT INTO Master_Vendor (vendor_id, vendor_name, address, contact_person, phone, email, is_active) 
                       VALUES (?, ?, ?, ?, ?, ?, 1)";
        $params = array($vendor_id, $vendor_name, $address, $contact_person, $phone, $email);
        
        $stmt_insert = sqlsrv_query($conn, $sql_insert, $params);
        
        if ($stmt_insert) {
            $message = "<div class='alert alert-success'><i class='fas fa-check'></i> Vendor <strong>".h($vendor_name)."</strong> berhasil ditambahkan dengan ID <strong>".h($vendor_id)."</strong>.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Gagal menyimpan data: " . print_r(sqlsrv_errors(), true) . "</div>";
        }
        
    } else {
        $message = "<div class='alert alert-warning'>Nama Vendor wajib diisi!</div>";
    }
}

// ==========================================================
// 2. PROSES NONAKTIFKAN VENDOR (SOFT DELETE)
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $vendor_id = trim($_GET['id']);
    
    $sql_delete = "UPDATE Master_Vendor SET is_active = 0 WHERE vendor_id = ?";
    $stmt_delete = sqlsrv_query($conn, $sql_delete, array($vendor_id));
    
    if ($stmt_delete) {
        $message = "<div class='alert alert-success'><i class='fas fa-info-circle'></i> Vendor <strong>".h($vendor_id)."</strong> berhasil dinonaktifkan.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Gagal menonaktifkan vendor.</div>";
    }
}

// ==========================================================
// 3. AMBIL DATA VENDOR AKTIF (READ)
// ==========================================================
$sql_list = "SELECT vendor_id, vendor_name, address, contact_person, phone, email 
             FROM Master_Vendor 
             WHERE is_active = 1 
             ORDER BY vendor_id DESC"; // Tampilkan yang terbaru di atas
$stmt_list = sqlsrv_query($conn, $sql_list);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Vendor - ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: Tahoma, sans-serif; font-size: 13px; padding: 20px; }
        .card-header { font-weight: bold; font-size: 14px; }
        .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        
        <!-- KOLOM FORM INPUT (KIRI) -->
        <div class="col-md-3">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <i class="fas fa-truck"></i> Input Master Vendor
                </div>
                <div class="card-body">
                    <?php echo $message; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_vendor">
                        
                        <div class="form-group">
                            <label>Kode / ID Vendor</label>
                            <input type="text" class="form-control font-weight-bold text-primary bg-light" value="[ AUTO GENERATED ]" readonly disabled>
                            <small class="text-muted">Sistem akan membuatkan ID otomatis.</small>
                        </div>

                        <div class="form-group">
                            <label>Nama Vendor / Supplier <span class="text-danger">*</span></label>
                            <input type="text" name="vendor_name" class="form-control" required placeholder="Mis: PT. Teknologi Jaya" maxlength="150">
                        </div>
                        
                        <div class="form-group">
                            <label>Contact Person (PIC)</label>
                            <input type="text" name="contact_person" class="form-control" placeholder="Nama PIC" maxlength="100">
                        </div>

                        <div class="form-group">
                            <label>No. Telepon</label>
                            <input type="text" name="phone" class="form-control" placeholder="021-XXXX / 0812XXXX" maxlength="50">
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" placeholder="email@vendor.com" maxlength="100">
                        </div>

                        <div class="form-group">
                            <label>Alamat Lengkap</label>
                            <textarea name="address" class="form-control" rows="3" placeholder="Alamat lengkap vendor..." maxlength="255"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-success btn-block mt-3">
                            <i class="fas fa-save"></i> Simpan Vendor Baru
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- KOLOM TABEL DATA (KANAN) -->
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <i class="fas fa-list"></i> Daftar Vendor Aktif
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th width="5%" class="text-center">No</th>
                                    <th width="12%">ID Vendor</th>
                                    <th width="20%">Nama Vendor</th>
                                    <th width="23%">Informasi Kontak</th>
                                    <th width="30%">Alamat</th>
                                    <th width="10%" class="text-center"><i class="fas fa-cog"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                if ($stmt_list !== false) {
                                    $has_data = false;
                                    while ($row = sqlsrv_fetch_array($stmt_list, SQLSRV_FETCH_ASSOC)) {
                                        $has_data = true;
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td><strong><?php echo h($row['vendor_id']); ?></strong></td>
                                    <td><?php echo h($row['vendor_name']); ?></td>
                                    <td>
                                        <?php if(!empty($row['contact_person'])) echo "<i class='fas fa-user text-secondary'></i> " . h($row['contact_person']) . "<br>"; ?>
                                        <?php if(!empty($row['phone'])) echo "<i class='fas fa-phone text-secondary'></i> " . h($row['phone']) . "<br>"; ?>
                                        <?php if(!empty($row['email'])) echo "<i class='fas fa-envelope text-secondary'></i> " . h($row['email']); ?>
                                    </td>
                                    <td><small><?php echo nl2br(h($row['address'])); ?></small></td>
                                    <td class="text-center">
                                        <a href="?action=delete&id=<?php echo urlencode(trim($row['vendor_id'])); ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Yakin ingin menonaktifkan vendor ini? Data historis tidak akan hilang.');" title="Nonaktifkan Vendor">
                                           <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php 
                                    }
                                    if (!$has_data) {
                                        echo "<tr><td colspan='6' class='text-center text-muted py-3'>Belum ada data vendor yang aktif.</td></tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center text-danger'>Gagal memuat data: " . h(print_r(sqlsrv_errors(), true)) . "</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
if (isset($stmt_list) && $stmt_list !== false) sqlsrv_free_stmt($stmt_list);
if ($conn !== false) sqlsrv_close($conn);
?>