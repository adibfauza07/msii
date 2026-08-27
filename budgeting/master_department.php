<?php
// Pastikan config.php sudah meng-handle session_start() dan koneksi $conn
require_once 'config.php';

// Fungsi sanitasi output untuk mencegah celah XSS (Cross-Site Scripting)
function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

$message = "";

// ==========================================================
// 1. PROSES INSERT DATA (CREATE)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    
    // Sanitasi input bergaya PHP 5.4
    $plant_id  = isset($_POST['plant_id']) ? strtoupper(trim($_POST['plant_id'])) : '';
    $dept_id   = isset($_POST['department_id']) ? strtoupper(trim($_POST['department_id'])) : '';
    $dept_name = isset($_POST['department_name']) ? trim($_POST['department_name']) : '';

    // Array validasi: memastikan Plant mutlak hanya P1 atau P2
    $allowed_plants = array('P1', 'P2');

    if (!empty($plant_id) && !empty($dept_id) && !empty($dept_name)) {
        
        // Cek Keamanan Back-End: Pastikan plant_id valid
        if (!in_array($plant_id, $allowed_plants)) {
            $message = "<div class='alert alert-danger text-sm'>Pilihan Plant tidak valid! Harap gunakan P1 atau P2.</div>";
        } else {
            
            // Cek apakah ID Departemen sudah terdaftar di PLANT YANG SAMA
            $check_sql = "SELECT department_id FROM Master_Department WHERE department_id = ? AND plant_id = ?";
            $check_stmt = sqlsrv_query($conn, $check_sql, array($dept_id, $plant_id));

            if ($check_stmt && sqlsrv_has_rows($check_stmt)) {
                $message = "<div class='alert alert-warning text-sm'>ID Departemen <strong>" . h($dept_id) . "</strong> sudah terdaftar di Plant <strong>" . h($plant_id) . "</strong>!</div>";
            } else {
                // Parameterized Query untuk Mencegah SQL Injection
                $insert_sql = "INSERT INTO Master_Department (department_id, department_name, plant_id, is_active) VALUES (?, ?, ?, 1)";
                $params = array($dept_id, $dept_name, $plant_id);
                
                $stmt = sqlsrv_query($conn, $insert_sql, $params);
                
                if ($stmt) {
                    $message = "<div class='alert alert-success text-sm'>Departemen <strong>" . h($dept_name) . "</strong> berhasil ditambahkan untuk <strong>" . h($plant_id) . "</strong>.</div>";
                } else {
                    $message = "<div class='alert alert-danger text-sm'>Gagal menyimpan data: " . print_r(sqlsrv_errors(), true) . "</div>";
                }
            }
            if ($check_stmt !== false) sqlsrv_free_stmt($check_stmt);
        }
    } else {
        $message = "<div class='alert alert-danger text-sm'>Semua kolom (Lokasi Plant, ID Departemen, dan Nama) wajib diisi!</div>";
    }
}

// ==========================================================
// 2. AMBIL DATA UNTUK DITAMPILKAN DI TABEL (READ)
// ==========================================================
$sql_list = "SELECT department_id, department_name, plant_id, is_active FROM Master_Department ORDER BY plant_id ASC, department_id ASC";
$stmt_list = sqlsrv_query($conn, $sql_list);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Department - ERP Budgeting</title>
    <!-- Bootstrap 4.6 CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { 
            background-color: #f4f6f9; 
            font-family: Tahoma, Arial, sans-serif; 
            font-size: 13px; 
            padding: 20px; 
        }
        .card-header { font-weight: bold; font-size: 14px; }
        .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body>

    <div class="container-fluid">
        <div class="row">
            <!-- ============================================== -->
            <!-- KOLOM FORM INPUT (KIRI) -->
            <!-- ============================================== -->
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-plus"></i> Tambah Departemen
                    </div>
                    <div class="card-body">
                        <?php echo $message; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add">
                            
                            <!-- Combo Box Lokasi Plant (Hanya P1 atau P2) -->
                            <div class="form-group">
                                <label>Lokasi Plant <span class="text-danger">*</span></label>
                                <select name="plant_id" class="form-control" required>
                                    <option value="">-- Pilih Plant --</option>
                                    <option value="P1">Plant 1 (P1)</option>
                                    <option value="P2">Plant 2 (P2)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>ID Departemen (Kode) <span class="text-danger">*</span></label>
                                <input type="text" name="department_id" class="form-control" placeholder="Contoh: FIN, HRD, IT" required maxlength="10" autocomplete="off">
                            </div>
                            
                            <div class="form-group">
                                <label>Nama Departemen <span class="text-danger">*</span></label>
                                <input type="text" name="department_name" class="form-control" placeholder="Contoh: Finance Dept" required maxlength="100" autocomplete="off">
                            </div>
                            
                            <button type="submit" class="btn btn-success btn-block mt-4">
                                <i class="fas fa-save"></i> Simpan Data
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- KOLOM TABEL DATA (KANAN) -->
            <!-- ============================================== -->
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <i class="fas fa-list"></i> Daftar Master Departemen
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="5%" class="text-center">No</th>
                                        <th width="15%" class="text-center">Lokasi Plant</th>
                                        <th width="20%">ID / Kode</th>
                                        <th>Nama Departemen</th>
                                        <th width="15%" class="text-center">Status</th>
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
                                        <td class="text-center">
                                            <!-- Beri warna berbeda antara P1 dan P2 agar mudah dilihat -->
                                            <?php if (strtoupper($row['plant_id']) === 'P1'): ?>
                                                <span class="badge badge-primary" style="font-size: 12px;">P1</span>
                                            <?php else: ?>
                                                <span class="badge badge-info" style="font-size: 12px;">P2</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo h($row['department_id']); ?></strong></td>
                                        <td><?php echo h($row['department_name']); ?></td>
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
                                        echo "<tr><td colspan='5' class='text-center text-danger'>Gagal memuat data.</td></tr>";
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
// Bebaskan resource memori di akhir script
if (isset($stmt_list) && $stmt_list !== false) {
    sqlsrv_free_stmt($stmt_list);
}
if ($conn !== false) {
    sqlsrv_close($conn);
}
?>