<?php 
// Pastikan session berjalan (Opsional, karena biasanya layout.php sudah menanganinya)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// include database sesuai strukturmu
include '../config/database_p1.php'; 


// 1. Fungsi untuk mengubah angka bulan menjadi angka Romawi
function getRomawi($bulan) {
    $romawi = array("", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII");
    return $romawi[(int)$bulan];
}

// 2. Ambil informasi tanggal saat ini
$tahun_sekarang = date('Y');
$bulan_sekarang = date('n'); // Format 1-12
$bulan_romawi = getRomawi($bulan_sekarang);

// 3. Ambil kode Plant dari session (default P1 jika tidak ada)
$kode_plant = isset($_SESSION['active_plant']) ? strtoupper($_SESSION['active_plant']) : 'P1';

// 4. Susun Prefix (Awalan) Nomor Kontrol
// Contoh Hasil: 4M-IMC-P2-VI-2026-
$prefix_no = "4M-IMC-" . $kode_plant . "-" . $bulan_romawi . "-" . $tahun_sekarang . "-";

// 5. Cari nomor urut terakhir di database berdasarkan prefix tersebut
$sql_cek_no = "SELECT TOP 1 CONTROL_NO FROM PROSES_CHANGE WHERE CONTROL_NO LIKE ? ORDER BY CONTROL_NO DESC";
$params = array($prefix_no . "%");
$stmt_cek = sqlsrv_query($conn, $sql_cek_no, $params);

$next_urut = 1; // Default mulai dari 1 jika belum ada data

if ($stmt_cek && sqlsrv_has_rows($stmt_cek)) {
    $row = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC);
    $last_no = trim($row['CONTROL_NO']); // Contoh: 4M-IMC-P2-VI-2026-0017
    
    // Ambil 4 karakter terakhir dan ubah ke integer
    $last_urut = (int) substr($last_no, -4);
    $next_urut = $last_urut + 1; // Tambahkan 1
}

// 6. Format nomor urut menjadi 4 digit (contoh: 1 menjadi 0001)
$format_urut = str_pad($next_urut, 4, "0", STR_PAD_LEFT);

// 7. Gabungkan prefix dan nomor urut final
$auto_control_no = $prefix_no . $format_urut;

// ==========================================
// 1. QUERY UNTUK DROPDOWN DEPARTEMEN
// ==========================================
$sql_dept = "SELECT DEPT_ID, DEPT FROM PROSES_DEPT ORDER BY DEPT ASC";
$stmt_dept = sqlsrv_query($conn, $sql_dept);

// Validasi jika query error (opsional untuk mempermudah tracking)
if ($stmt_dept === false) {
    die("Gagal mengambil data Departemen: " . print_r(sqlsrv_errors(), true));
}

// ==========================================
// 2. QUERY UNTUK DROPDOWN CUSTOMER
// ==========================================
// Kita filter hanya customer yang tidak tidak aktif (CUST_INACTIVE = 0)
$sql_cust = "SELECT CUST_ID, CUST_COMP, CUST_ALIAS FROM CUST WHERE CUST_INACTIVE = 0 OR CUST_INACTIVE IS NULL ORDER BY CUST_COMP ASC";
$stmt_cust = sqlsrv_query($conn, $sql_cust);

if ($stmt_cust === false) {
    die("Gagal mengambil data Customer: " . print_r(sqlsrv_errors(), true));
}

// ==========================================
// 3. QUERY UNTUK DROPDOWN STATUS
// ==========================================
$sql_status = "SELECT STATUS FROM PROSES_STATUS";
$stmt_status = sqlsrv_query($conn, $sql_status);

if ($stmt_status === false) {
    die("Gagal mengambil data Status: " . print_r(sqlsrv_errors(), true));
}

// Include layout master ERP
include 'layout.php'; 
?>

<div class="container-fluid mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h3 class="fw-bold mb-0 text-dark">Proses Change Information Sheet</h3>
            <span class="text-muted small">Form Input 4M Change (PCIS)</span>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-light border shadow-sm btn-sm fw-bold">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm fw-bold">
                <i class="bi bi-plus-lg"></i> Tambah Baru
            </button>
            <button type="submit" form="formPCIS" class="btn btn-sm fw-bold text-white shadow-sm" style="background: #2c3e50;">
                <i class="bi bi-save"></i> Simpan Data
            </button>
        </div>
    </div>

    <form id="formPCIS" action="proses_simpan_pcis.php" method="POST">
        <div class="row g-4">
            
            <div class="col-md-7">
                <div class="card shadow-sm border-0 bg-white">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">Informasi Utama</h6>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">CONTROL NO</label>
                                <input type="text" class="form-control bg-light" name="CONTROL_NO" readonly value="<?php echo $auto_control_no; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">CONTROL DATE</label>
                                <input type="date" class="form-control" name="CONTROL_DATE1" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">TO</label>
                                <input type="text" class="form-control" name="TO_PCIS" value="ALL DEPARTEMENT">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">CC</label>
                                <input type="text" class="form-control" name="CC" value="ALL DEPARTEMENT">
                            </div>
                        </div>

                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">Detail Item & Perubahan</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Customer</label>
<select class="form-select" name="CUST_ID" id="cust_id" required>
    <option value="">- Pilih Customer -</option>
    <?php 
    // Looping data customer dari database
    while ($row_cust = sqlsrv_fetch_array($stmt_cust, SQLSRV_FETCH_ASSOC)) {
        // Ambil ID sebagai value, dan Nama Perusahaan (Comp) sebagai teks tampilan
        echo "<option value='".$row_cust['CUST_ID']."'>".$row_cust['CUST_COMP']." (".$row_cust['CUST_ALIAS'].")</option>";
    }
    ?>
</select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Part Name</label>
                                <select class="form-select" name="ITEM_ID" id="item_id">
                                    <option value="">- Pilih Part Name -</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Reason / Purpose</label>
                            <textarea class="form-control" name="REASON" rows="3" placeholder="Masukkan alasan perubahan..."></textarea>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">BEFORE CHANGE</label>
                                <textarea class="form-control bg-light" name="BEF_CHANGE" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">AFTER CHANGE</label>
                                <textarea class="form-control bg-light" name="AFT_CHANGE" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="card shadow-sm border-0 bg-white mb-4 border-top border-primary border-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">Request By & Person In Charge</h6>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Request By</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="INTERNAL" value="1" checked>
                                    <label class="form-check-label small">Internal</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="CUSTOMER" value="1" checked>
                                    <label class="form-check-label small">Customer</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="SUPPLIER" value="1">
                                    <label class="form-check-label small">Supplier</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">PIC Name</label>
                            <input type="text" class="form-control" name="PIC_NAME" placeholder="Nama PIC">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Departemen PIC</label>
<select class="form-select" name="DEPT_ID" required>
    <option value="">- Pilih Departemen -</option>
    <?php 
    // Looping data departemen dari database
    while ($row_dept = sqlsrv_fetch_array($stmt_dept, SQLSRV_FETCH_ASSOC)) {
        echo "<option value='".$row_dept['DEPT_ID']."'>".$row_dept['DEPT']."</option>";
    }
    ?>
</select>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 bg-white">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">Schedule & Status</h6>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Process Change Date</label>
                                <input type="date" class="form-control" name="SCH_CHANGE">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">Start Date</label>
                                <input type="date" class="form-control" name="START_CHANGE">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">Closing Date</label>
                                <input type="date" class="form-control" name="CLOSE_CHANGE">
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label small fw-bold text-muted">Status Dokumen</label>
<select class="form-select shadow-sm" name="STATUS">
    <option value="">- Set Status -</option>
    <?php 
    // Looping data status dokumen
    while ($row_status = sqlsrv_fetch_array($stmt_status, SQLSRV_FETCH_ASSOC)) {
        echo "<option value='".$row_status['STATUS']."'>".$row_status['STATUS']."</option>";
    }
    ?>
</select>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </form>
</div>