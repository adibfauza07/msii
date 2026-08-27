<?php
require_once 'config.php';

function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

$message = "";

// ==========================================================
// 1. PROSES INSERT & AUTO-GENERATE CODE
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    
    $dept_id   = isset($_POST['department_id']) ? strtoupper(trim($_POST['department_id'])) : '';
    $item_name = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
    $uom       = isset($_POST['uom']) ? trim($_POST['uom']) : '';

    if (!empty($dept_id) && !empty($item_name) && !empty($uom)) {
        
        // LOGIKA AUTO-GENERATE ITEM CODE
        // Cari kode terakhir di Master_Item yang berawalan departemen tersebut (misal: LIKE 'FIN%')
        $sql_last_code = "SELECT TOP 1 item_code FROM Master_Item WHERE item_code LIKE ? ORDER BY item_code DESC";
        $stmt_last = sqlsrv_query($conn, $sql_last_code, array($dept_id . '%'));
        
        $next_num = 1; // Default angka mulai dari 1
        
        if ($stmt_last && sqlsrv_has_rows($stmt_last)) {
            $row_last = sqlsrv_fetch_array($stmt_last, SQLSRV_FETCH_ASSOC);
            $last_code = $row_last['item_code'];
            
            // Hapus huruf departemen dari kode lama, sisakan angkanya saja, lalu ubah ke integer
            $last_num = (int) str_replace($dept_id, '', $last_code);
            $next_num = $last_num + 1; // Tambah 1
        }
        
        // Gabungkan kembali Dept ID dengan Angka yang diformat 4 digit (contoh: FIN + 0001)
        $new_item_code = $dept_id . str_pad($next_num, 4, "0", STR_PAD_LEFT);
        
        if ($stmt_last !== false) sqlsrv_free_stmt($stmt_last);

        // Eksekusi Insert
        $insert_sql = "INSERT INTO Master_Item (item_code, item_name, department_id, uom, is_active) VALUES (?, ?, ?, ?, 1)";
        $params = array($new_item_code, $item_name, $dept_id, $uom);
        
        $stmt_insert = sqlsrv_query($conn, $insert_sql, $params);
        
        if ($stmt_insert) {
            $message = "<div class='alert alert-success text-sm'>Item berhasil disimpan dengan kode <strong>" . h($new_item_code) . "</strong>.</div>";
        } else {
            $message = "<div class='alert alert-danger text-sm'>Gagal menyimpan item: " . print_r(sqlsrv_errors(), true) . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger text-sm'>Semua kolom wajib diisi!</div>";
    }
}

// ==========================================================
// 2. AMBIL DATA UNTUK DITAMPILKAN
// ==========================================================
// Ambil List Departemen untuk Dropdown (DISTINCT karena Dept ID bisa ada di P1 dan P2)
$sql_dept = "SELECT DISTINCT department_id, department_name FROM Master_Department ORDER BY department_id ASC";
$stmt_dept = sqlsrv_query($conn, $sql_dept);

// Ambil Data Master Item untuk Tabel
$sql_list = "SELECT item_code, item_name, department_id, uom, is_active FROM Master_Item ORDER BY department_id ASC, item_code ASC";
$stmt_list = sqlsrv_query($conn, $sql_list);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Item - ERP Budgeting</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: Tahoma, Arial, sans-serif; font-size: 13px; padding: 20px; }
        .card-header { font-weight: bold; font-size: 14px; }
        .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body>

    <div class="container-fluid">
        <div class="row">
            <!-- KOLOM FORM (KIRI) -->
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-box"></i> Tambah Master Item
                    </div>
                    <div class="card-body">
                        <?php echo $message; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="form-group">
                                <label>Pilih Departemen <span class="text-danger">*</span></label>
                                <select name="department_id" class="form-control" required>
                                    <option value="">-- Pilih Departemen --</option>
                                    <?php 
                                    if ($stmt_dept !== false) {
                                        while ($row_dept = sqlsrv_fetch_array($stmt_dept, SQLSRV_FETCH_ASSOC)) {
                                            echo '<option value="' . h($row_dept['department_id']) . '">' . h($row_dept['department_id']) . ' - ' . h($row_dept['department_name']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <small class="form-text text-muted">Kode item akan di-generate otomatis berdasarkan departemen (Misal: FIN0001).</small>
                            </div>

                            <div class="form-group">
                                <label>Nama Barang / ATK / Sparepart <span class="text-danger">*</span></label>
                                <input type="text" name="item_name" class="form-control" placeholder="Contoh: Tinta Epson L3210" required maxlength="150" autocomplete="off">
                            </div>
                            
                            <div class="form-group">
                                <label>Satuan (UOM) <span class="text-danger">*</span></label>
                                <select name="uom" class="form-control" required>
                                    <option value="">-- Pilih Satuan --</option>
                                    <option value="Pcs">Pcs (Pieces)</option>
                                    <option value="Box">Box</option>
                                    <option value="Ltr">Ltr (Liter)</option>
                                    <option value="Unit">Unit</option>
                                    <option value="Rim">Rim</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-success btn-block mt-4">
                                <i class="fas fa-save"></i> Simpan Item Baru
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- KOLOM TABEL DATA (KANAN) -->
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <i class="fas fa-list"></i> Daftar Barang & ATK
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="5%" class="text-center">No</th>
                                        <th width="15%">Item Code</th>
                                        <th>Nama Barang</th>
                                        <th width="10%" class="text-center">Dept</th>
                                        <th width="10%" class="text-center">Satuan</th>
                                        <th width="10%" class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    if ($stmt_list !== false) {
                                        while ($row = sqlsrv_fetch_array($stmt_list, SQLSRV_FETCH_ASSOC)): 
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $no++; ?></td>
                                        <td><strong><span class="text-primary"><?php echo h($row['item_code']); ?></span></strong></td>
                                        <td><?php echo h($row['item_name']); ?></td>
                                        <td class="text-center"><span class="badge badge-dark"><?php echo h($row['department_id']); ?></span></td>
                                        <td class="text-center"><?php echo h($row['uom']); ?></td>
                                        <td class="text-center">
                                            <?php if ($row['is_active'] == 1): ?>
                                                <span class="badge badge-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Non-Aktif</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php 
                                        endwhile; 
                                    } else {
                                        echo "<tr><td colspan='6' class='text-center text-danger'>Belum ada data barang.</td></tr>";
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

</body>
</html>

<?php
if (isset($stmt_dept) && $stmt_dept !== false) sqlsrv_free_stmt($stmt_dept);
if (isset($stmt_list) && $stmt_list !== false) sqlsrv_free_stmt($stmt_list);
if ($conn !== false) sqlsrv_close($conn);
?>