<?php
// msii/pe/master_std.php
require_once 'MiddleWare/Auth.php'; 
require_once 'MiddleWare/RoleCheck.php';
require_once '../config/database_p1.php';

// =========================================================================
// 1. LOGIKA BACKEND (TAMBAH, EDIT, HAPUS)
// =========================================================================

// --- A. Logika Hapus Data ---
if (isset($_GET['delete'])) {
    $hapus_id = trim($_GET['delete']);
    $stmtDel = sqlsrv_query($conn, "DELETE FROM TRIAL_PE_STD WHERE ITEM_CODE = ?", array($hapus_id));
    if ($stmtDel) {
        echo "<script>alert('Data Master {$hapus_id} berhasil dihapus!'); window.location='master_std.php';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal menghapus data.');</script>";
    }
}

// --- B. Logika Tambah Data Master ---
if (isset($_POST['btn_tambah_master'])) {
    $item_code = trim($_POST['item_code']);
    $weight    = floatval($_POST['weight']);
    $runner    = floatval($_POST['runner']);
    $cycle     = floatval($_POST['cycle']);
    $cavity    = intval($_POST['cavity']);

    // Cek apakah kode part sudah ada di Master STD agar tidak duplikat
    $cek = sqlsrv_query($conn, "SELECT ITEM_CODE FROM TRIAL_PE_STD WHERE ITEM_CODE = ?", array($item_code));
    if (sqlsrv_has_rows($cek)) {
        echo "<script>alert('Gagal! Part Code {$item_code} sudah memiliki parameter standar.'); window.history.back();</script>";
        exit;
    }

    $sqlInsert = "INSERT INTO TRIAL_PE_STD (ITEM_CODE, WEIGHT_PART_STD, WEIGHT_RUNNER_STD, CYCLE_TIME_STD, CAVITY_STD) 
                  VALUES (?, ?, ?, ?, ?)";
    $stmtIns = sqlsrv_query($conn, $sqlInsert, array($item_code, $weight, $runner, $cycle, $cavity));
    
    if ($stmtIns) {
        echo "<script>alert('Berhasil menambah Master Standard Baru!'); window.location='master_std.php';</script>";
        exit;
    }
}

// --- C. Logika Edit Data Master ---
if (isset($_POST['btn_edit_master'])) {
    $item_code = trim($_POST['edit_item_code']); // Sifatnya Read-Only, hanya sebagai penanda (WHERE)
    $weight    = floatval($_POST['edit_weight']);
    $runner    = floatval($_POST['edit_runner']);
    $cycle     = floatval($_POST['edit_cycle']);
    $cavity    = intval($_POST['edit_cavity']);

    $sqlUpdate = "UPDATE TRIAL_PE_STD SET WEIGHT_PART_STD = ?, WEIGHT_RUNNER_STD = ?, CYCLE_TIME_STD = ?, CAVITY_STD = ? 
                  WHERE ITEM_CODE = ?";
    $stmtUpd = sqlsrv_query($conn, $sqlUpdate, array($weight, $runner, $cycle, $cavity, $item_code));
    
    if ($stmtUpd) {
        echo "<script>alert('Berhasil memperbarui Master Standard untuk {$item_code}!'); window.location='master_std.php';</script>";
        exit;
    }
}

// --- D. Logika Pencarian Auto-Dropdown Part ---
if (isset($_GET['ajax_search_part'])) {
    header('Content-Type: application/json');
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';
    $res = [];
    
    if (!empty($term)) {
        // Mencari maksimal 15 part berdasarkan Kode atau Nama Part
        $stmt = sqlsrv_query($conn, "SELECT TOP 15 ITEM_CODE, ITEM_NAME FROM ITEMS WHERE ITEM_CODE LIKE ? OR ITEM_NAME LIKE ?", ["%$term%", "%$term%"]);
        if($stmt) {
            while($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
                $res[] = [
                    'label' => $r['ITEM_CODE'] . ' - ' . $r['ITEM_NAME'], // Teks yang tampil di daftar
                    'value' => $r['ITEM_CODE'] // Nilai yang akan dimasukkan ke kotak saat diklik
                ];
            }
        }
    }
    echo json_encode($res);
    exit;
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Standard Trial - PE System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
    
    <style>
        body { background-color: #f4f7f6; font-family: "Segoe UI", Roboto, Arial, sans-serif; overflow-x: hidden; }
        
        #sidebar { width: 250px; height: 100vh; background: #1f2a36; color: white; position: fixed; top: 0; left: 0; z-index: 1050; display: flex; flex-direction: column; box-shadow: 3px 0 10px rgba(0,0,0,0.2); }
        #sidebar .brand { padding: 22px 20px; font-size: 18px; font-weight: 700; background: #1a232d; text-align: center; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link { color: #aab0b6; padding: 12px 20px; font-size: 14.5px; border-left: 4px solid transparent; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: #2c3e50; color: #fff !important; border-left-color: #3498db; }
        .nav-link i { margin-right: 10px; font-size: 1.1rem; }
        .menu-label { padding: 20px 20px 8px 20px; font-size: 11px; text-transform: uppercase; color: #5b6e80; font-weight: 800; letter-spacing: 1px; }
        .sidebar-footer { margin-top: auto; padding: 15px 20px; background: #161e27; border-top: 1px solid rgba(255,255,255,0.05); }

        #content { padding-left: 250px; transition: all 0.3s; }
        .top-header { background: white; padding: 15px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
        
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: white; margin-bottom: 25px; padding: 25px; }
        .table-wrapper { border-radius: 12px; overflow: hidden; }
        .table thead th { background-color: #1f2a36; color: #ffffff; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 15px; border: none; font-weight: 600; vertical-align: middle; }
        .table tbody td { padding: 15px; vertical-align: middle; border-bottom: 1px solid #f0f2f5; font-size: 0.95rem; color: #495057; }
        .table tbody tr:hover { background-color: #f8f9fa; }

        /* CSS Khusus untuk Autocomplete Dropdown agar tampil di atas Modal */
        .ui-autocomplete {
            position: absolute;
            z-index: 9999 !important; 
            background-color: #ffffff;
            border: 1px solid #ced4da;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 5px 0;
        }
        .ui-menu-item .ui-menu-item-wrapper { padding: 8px 15px; font-size: 0.9rem; cursor: pointer; }
        .ui-menu-item .ui-menu-item-wrapper:hover,
        .ui-menu-item .ui-menu-item-wrapper.ui-state-active {
            background-color: #3498db !important;
            color: #ffffff !important;
            border: none;
            margin: 0;
        }
    </style>
</head>
<body>

<div class="d-flex w-100">

    <?php include 'includes/sidebar.php'; ?>

    <div id="content" class="w-100 pb-5">
        
        <div class="top-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0 fw-bold text-dark" style="letter-spacing: -0.5px;">
                <i class="bi bi-database-gear text-primary me-2"></i> Master Standard Trial
            </h4>
            <div class="text-muted small"><i class="bi bi-calendar3"></i> <?= date('d F Y') ?></div>
        </div>

        <div class="container-fluid px-4">
            <div class="card-custom table-wrapper">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Database Standard Part</h5>
                        <small class="text-muted">Kelola parameter standar untuk setiap part number di sini.</small>
                    </div>
                    <!-- Memanggil Modal Tambah Master -->
                    <button class="btn btn-primary btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                        <i class="bi bi-plus-lg"></i> Tambah Master
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tableMasterStd">
                        <thead class="text-center">
                            <tr>
                                <th width="5%">NO</th>
                                <th width="25%">ITEM CODE & NAME</th>
                                <th width="15%">WEIGHT PART</th>
                                <th width="15%">RUNNER</th>
                                <th width="15%">CYCLE TIME</th>
                                <th width="10%">CAVITY</th>
                                <th width="15%">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT S.*, I.ITEM_NAME 
                                    FROM TRIAL_PE_STD S 
                                    LEFT JOIN ITEMS I ON S.ITEM_CODE = I.ITEM_CODE 
                                    ORDER BY S.ITEM_CODE ASC";
                            $q = sqlsrv_query($conn, $sql);
                            $no = 1;

                            if ($q === false) {
                                echo "<tr><td colspan='7' class='text-center text-danger'>Error memuat data database.</td></tr>";
                            } else {
                                while($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)){
                                    $part_name = $r['ITEM_NAME'] ? htmlspecialchars($r['ITEM_NAME']) : 'Nama Part Tidak Ditemukan';
                                    
                                    echo "<tr>
                                            <td class='text-center fw-bold text-muted'>{$no}</td>
                                            <td>
                                                <span class='fw-bold text-primary fs-6'>{$r['ITEM_CODE']}</span><br>
                                                <small class='text-secondary'>{$part_name}</small>
                                            </td>
                                            <td class='text-center fw-semibold'>{$r['WEIGHT_PART_STD']} g</td>
                                            <td class='text-center fw-semibold'>{$r['WEIGHT_RUNNER_STD']} g</td>
                                            <td class='text-center fw-semibold'>{$r['CYCLE_TIME_STD']} s</td>
                                            <td class='text-center fw-bold fs-6'>{$r['CAVITY_STD']}</td>
                                            <td class='text-center'>
                                                <!-- Tombol Edit memanggil Modal & Menyimpan Data di Atribut -->
                                                <button class='btn btn-sm btn-outline-primary me-1 btn-edit' 
                                                        data-bs-toggle='modal' 
                                                        data-bs-target='#modalEdit'
                                                        data-code='{$r['ITEM_CODE']}'
                                                        data-wp='{$r['WEIGHT_PART_STD']}'
                                                        data-wr='{$r['WEIGHT_RUNNER_STD']}'
                                                        data-ct='{$r['CYCLE_TIME_STD']}'
                                                        data-cav='{$r['CAVITY_STD']}'
                                                        title='Edit Master'>
                                                    <i class='bi bi-pencil-square'></i> Edit
                                                </button>
                                                <!-- Tombol Hapus memanggil link GET -->
                                                <a href='?delete={$r['ITEM_CODE']}' class='btn btn-sm btn-outline-danger' title='Hapus Master' onclick='return confirm(\"Yakin ingin menghapus parameter standar untuk Part: {$r['ITEM_CODE']}?\")'>
                                                    <i class='bi bi-trash'></i>
                                                </a>
                                            </td>
                                          </tr>";
                                    $no++;
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div> 
    </div> 
</div> 

<!-- ==============================================================
     2. MODAL TAMBAH DATA MASTER
     ============================================================== -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-labelledby="modalTambahLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h1 class="modal-title fs-5" id="modalTambahLabel"><i class="bi bi-node-plus"></i> Tambah Master Standard</h1>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
          <div class="modal-body">
              <div class="mb-3">
                  <label class="form-label fw-bold small text-muted">ITEM CODE (PART CODE)</label>
                  <input type="text" name="item_code" id="input_item_code" class="form-control" placeholder="Ketik Kode atau Nama Part..." autocomplete="off" required>
                  <small class="text-info">*Pastikan kode sesuai dengan yang ada di Database Part.</small>
              </div>
              <div class="row g-2">
                  <div class="col-6 mb-2">
                      <label class="form-label fw-bold small text-muted">WEIGHT PART (g)</label>
                      <input type="number" step="0.01" name="weight" class="form-control" placeholder="0.00" required>
                  </div>
                  <div class="col-6 mb-2">
                      <label class="form-label fw-bold small text-muted">RUNNER WEIGHT (g)</label>
                      <input type="number" step="0.01" name="runner" class="form-control" placeholder="0.00" required>
                  </div>
                  <div class="col-6">
                      <label class="form-label fw-bold small text-muted">CYCLE TIME (s)</label>
                      <input type="number" step="0.1" name="cycle" class="form-control" placeholder="0.0" required>
                  </div>
                  <div class="col-6">
                      <label class="form-label fw-bold small text-muted">CAVITY</label>
                      <input type="number" name="cavity" class="form-control" placeholder="0" required>
                  </div>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" name="btn_tambah_master" class="btn btn-primary fw-bold"><i class="bi bi-save"></i> Simpan Data</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- ==============================================================
     3. MODAL EDIT DATA MASTER
     ============================================================== -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-labelledby="modalEditLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-warning text-dark">
        <h1 class="modal-title fs-5 fw-bold" id="modalEditLabel"><i class="bi bi-pencil-square"></i> Edit Master Standard</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
          <div class="modal-body">
              <div class="mb-3">
                  <label class="form-label fw-bold small text-muted">ITEM CODE (PART CODE)</label>
                  <input type="text" name="edit_item_code" id="edit_item_code" class="form-control bg-light fw-bold" readonly required>
                  <small class="text-danger">*Kode Part tidak dapat diubah. Hapus dan buat baru jika salah.</small>
              </div>
              <div class="row g-2">
                  <div class="col-6 mb-2">
                      <label class="form-label fw-bold small text-muted">WEIGHT PART (g)</label>
                      <input type="number" step="0.01" name="edit_weight" id="edit_weight" class="form-control" required>
                  </div>
                  <div class="col-6 mb-2">
                      <label class="form-label fw-bold small text-muted">RUNNER WEIGHT (g)</label>
                      <input type="number" step="0.01" name="edit_runner" id="edit_runner" class="form-control" required>
                  </div>
                  <div class="col-6">
                      <label class="form-label fw-bold small text-muted">CYCLE TIME (s)</label>
                      <input type="number" step="0.1" name="edit_cycle" id="edit_cycle" class="form-control" required>
                  </div>
                  <div class="col-6">
                      <label class="form-label fw-bold small text-muted">CAVITY</label>
                      <input type="number" name="edit_cavity" id="edit_cavity" class="form-control" required>
                  </div>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" name="btn_edit_master" class="btn btn-warning fw-bold"><i class="bi bi-save"></i> Perbarui Data</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- ==============================================================
     JAVASCRIPT LIBRARIES & DATATABLES INITIALIZATION
     ============================================================== -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {

    // 3. Script untuk Auto-Dropdown Pencarian Part
    $("#input_item_code").autocomplete({
        source: "?ajax_search_part=1", // Memanggil logika PHP di Langkah 1
        minLength: 2, // Muncul setelah mengetik minimal 2 huruf
        select: function(event, ui) {
            $("#input_item_code").val(ui.item.value); // Masukkan kode part ke kotak
            return false;
        }
    });

    // 1. Menyalakan Fitur DataTables
    $('#tableMasterStd').DataTable({
        "pageLength": 10,
        "ordering": false,
        "lengthChange": false, 
        "language": {
            "search": "Pencarian Part:",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ Master",
            "infoEmpty": "Tidak ada data master",
            "emptyTable": "<div class='text-center text-muted py-4'><i class='bi bi-inboxes fs-1 d-block mb-2 text-secondary'></i>Belum ada data Master Standard.</div>",
            "paginate": { "next": "Selanjutnya", "previous": "Sebelumnya" }
        }
    });

    // 2. JavaScript untuk memindahkan data dari tabel ke Modal Edit
    $('.btn-edit').on('click', function() {
        // Ambil data dari atribut tombol (data-code, data-wp, dll)
        const code = $(this).data('code');
        const wp = $(this).data('wp');
        const wr = $(this).data('wr');
        const ct = $(this).data('ct');
        const cav = $(this).data('cav');

        // Isi ke dalam kotak input di Modal Edit
        $('#edit_item_code').val(code);
        $('#edit_weight').val(wp);
        $('#edit_runner').val(wr);
        $('#edit_cycle').val(ct);
        $('#edit_cavity').val(cav);
    });
});
</script>
</body>
</html>