<?php
// FILE: master_spec.php
require_once __DIR__ . '/../config/global.php';

if ($conn === false) {
    die("<div class='alert alert-danger p-3 m-3 font-weight-bold'>Koneksi database tidak tersedia.</div>");
}

$pesan = "";
$active_plant_session = isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1';
$nama_plant = ($active_plant_session == 'p2') ? 'PLANT 2' : 'PLANT 1';
$badge_color = ($active_plant_session == 'p2') ? 'bg-success' : 'bg-primary';

// ==========================================
// 1. PROSES HAPUS MASTER PART (PROTEKSI GANDA)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'delete' && !empty($_GET['partno']) && isset($_GET['dimensi'])) {
    $part_to_delete = trim($_GET['partno']);
    $dimensi_to_delete = trim($_GET['dimensi']);
    
    sqlsrv_begin_transaction($conn);
    try {
        sqlsrv_query($conn, "SET ARITHABORT ON; SET ANSI_WARNINGS ON;");

        // Cek relasi menggunakan PartNo DAN Dimensi
        $sqlCheck = "SELECT COUNT(*) as total FROM SPC_Inspection_Header WHERE PartNo = ? AND Dimensi = ?";
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($part_to_delete, $dimensi_to_delete));
        $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);

        if ($rowCheck['total'] > 0) {
            throw new Exception("Spesifikasi untuk Dimensi [$dimensi_to_delete] pada Part [$part_to_delete] sudah terpakai di transaksi SPC! Hapus dulu transaksi SPC terkait.");
        }

        $sqlDel = "DELETE FROM Master_Part_Spec WHERE PartNo = ? AND Dimensi = ?";
        $stmtDel = sqlsrv_query($conn, $sqlDel, array($part_to_delete, $dimensi_to_delete));
        
        if ($stmtDel === false) {
            $err = sqlsrv_errors();
            throw new Exception("Gagal menghapus Master Part: " . (isset($err[0]['message']) ? $err[0]['message'] : 'Unknown'));
        }

        sqlsrv_commit($conn);
        echo "<script>alert('Master Spec [" . $part_to_delete . " - " . $dimensi_to_delete . "] berhasil dihapus.'); window.location.href='dashboard_qc.php?page=master_spec';</script>";
        exit();

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $pesan = "<div class='alert alert-danger alert-dismissible fade show shadow-sm'>
                    <i class='bi bi-exclamation-triangle-fill me-2'></i> <strong>Gagal Hapus:</strong> " . $e->getMessage() . "
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                  </div>";
    }
}

// ==========================================
// 2. PROSES UPDATE MASTER SPEC
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btnUpdateSpec'])) {
    $partNo = trim($_POST['part_no']);
    $dimensi = trim($_POST['dimensi']);
    $target = (float)$_POST['target_dimensi'];
    $usl = (float)$_POST['usl'];
    $lsl = (float)$_POST['lsl'];
    
    // Update wajib berpasangan PartNo + Dimensi
    $sqlUpdate = "UPDATE Master_Part_Spec SET Target_Dimensi = ?, USL = ?, LSL = ? WHERE PartNo = ? AND Dimensi = ?";
    $stmtUp = sqlsrv_query($conn, $sqlUpdate, array($target, $usl, $lsl, $partNo, $dimensi));
    
    if ($stmtUp) {
        $pesan = "<div class='alert alert-success alert-dismissible fade show shadow-sm'>
                    <i class='bi bi-check-circle-fill me-2'></i> Spesifikasi untuk Part <b>$partNo</b> ($dimensi) berhasil diperbarui!
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                  </div>";
    } else {
        $pesan = "<div class='alert alert-danger alert-dismissible fade show shadow-sm'>
                    <i class='bi bi-exclamation-triangle-fill me-2'></i> Gagal memperbarui spesifikasi.
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                  </div>";
    }
}

// Ambil daftar master part
$sqlList = "SELECT * FROM Master_Part_Spec ORDER BY PartNo ASC, Dimensi ASC";
$stmtList = sqlsrv_query($conn, $sqlList);
?>

<!-- KONTEN MASTER SPEC -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="mb-0 fw-bold"><i class="bi bi-sliders text-primary me-2"></i>Master Spesifikasi Part SPC</h4>
        
        <div class="d-flex gap-2">
            <a href="dashboard_qc.php?page=spc" class="btn btn-sm btn-secondary fw-bold shadow-sm">
                <i class="bi bi-arrow-left"></i> Kembali ke Form SPC
            </a>
        </div>

        <span class="badge <?php echo $badge_color; ?> fs-6 px-3 py-2 shadow-sm rounded-pill text-nowrap">
            <i class="bi bi-building me-1"></i> <?php echo $nama_plant; ?>
        </span>
    </div>

    <?php echo $pesan; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-primary">Daftar Batas Toleransi per Dimensi</h6>
            <small class="text-muted"><i class="bi bi-info-circle"></i> 1 Part Number dapat memiliki banyak Dimensi.</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th width="15%">Part Number</th>
                            <th width="20%">Part Name</th>
                            <th width="15%">Dimensi</th>
                            <th>Target Ideal</th>
                            <th>LSL (Bawah)</th>
                            <th>USL (Atas)</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($stmtList): ?>
                            <?php while($row = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)): 
                                $dimensi_val = isset($row['Dimensi']) ? $row['Dimensi'] : '-';
                            ?>
                            <tr>
                                <td class="fw-bold text-primary"><?php echo htmlspecialchars($row['PartNo']); ?></td>
                                <td class="text-start"><?php echo htmlspecialchars($row['PartName']); ?></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($dimensi_val); ?></td>
                                <td><?php echo $row['Target_Dimensi']; ?></td>
                                <td class="text-danger fw-bold"><?php echo $row['LSL']; ?></td>
                                <td class="text-danger fw-bold"><?php echo $row['USL']; ?></td>
                                <td>
                                    <!-- Tombol Trigger Modal Edit -->
                                    <button type="button" class="btn btn-sm btn-warning fw-bold text-dark me-1 mb-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal" 
                                            data-partno="<?php echo htmlspecialchars($row['PartNo']); ?>"
                                            data-partname="<?php echo htmlspecialchars($row['PartName']); ?>"
                                            data-dimensi="<?php echo htmlspecialchars($dimensi_val); ?>"
                                            data-target="<?php echo $row['Target_Dimensi']; ?>"
                                            data-lsl="<?php echo $row['LSL']; ?>"
                                            data-usl="<?php echo $row['USL']; ?>">
                                        <i class="bi bi-pencil-square"></i> Atur Spec
                                    </button>

                                    <!-- Tombol Hapus Master Part -->
                                    <a href="dashboard_qc.php?page=master_spec&action=delete&partno=<?php echo urlencode($row['PartNo']); ?>&dimensi=<?php echo urlencode($dimensi_val); ?>" 
                                       class="btn btn-sm btn-danger fw-bold mb-1" 
                                       onclick="return confirm('Yakin hapus Master Spec untuk Dimensi [<?php echo $dimensi_val; ?>] pada Part [<?php echo $row['PartNo']; ?>]?');">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDIT SPESIFIKASI -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-gear-fill me-2"></i>Edit Spesifikasi Toleransi Part</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Part Number</label>
                        <input type="text" id="modal_partno" name="part_no" class="form-control form-control-sm bg-light fw-bold" readonly>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Part Name</label>
                        <input type="text" id="modal_partname" class="form-control form-control-sm bg-light" readonly>
                    </div>
                    <div class="mb-3 border-bottom pb-2">
                        <label class="form-label small fw-bold text-primary">Dimensi yang Diukur</label>
                        <input type="text" id="modal_dimensi" name="dimensi" class="form-control form-control-sm bg-light fw-bold text-primary" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-success">Target Dimensi (Ideal)</label>
                        <input type="number" step="0.001" id="modal_target" name="target_dimensi" class="form-control form-control-sm" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-danger">LSL (Batas Bawah)</label>
                            <input type="number" step="0.001" id="modal_lsl" name="lsl" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-danger">USL (Batas Atas)</label>
                            <input type="number" step="0.001" id="modal_usl" name="usl" class="form-control form-control-sm" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="btnUpdateSpec" class="btn btn-sm btn-primary fw-bold">Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    .table th { font-size: 12px; text-transform: uppercase; background-color: #f8f9fa; text-align: center; }
    .table td { vertical-align: middle; font-size: 13px; text-align: center; }
</style>

<script>
var editModal = document.getElementById('editModal');
editModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    document.getElementById('modal_partno').value = button.getAttribute('data-partno');
    document.getElementById('modal_partname').value = button.getAttribute('data-partname');
    document.getElementById('modal_dimensi').value = button.getAttribute('data-dimensi');
    document.getElementById('modal_target').value = button.getAttribute('data-target');
    document.getElementById('modal_lsl').value = button.getAttribute('data-lsl');
    document.getElementById('modal_usl').value = button.getAttribute('data-usl');
});
</script>