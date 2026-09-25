<?php
require_once __DIR__ . "/../config/database_ordering.php";

$pesan = "";
$selected_doc = isset($_GET['doc']) ? $_GET['doc'] : '';
$edit_id = isset($_GET['edit']) ? $_GET['edit'] : '';

// ==========================================
// PROSES CRUD: INSERT, UPDATE, DELETE DETAIL
// ==========================================

// 1. INSERT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_detail'])) {
    $no_trans = $_POST['no_trans']; 
    $jenis_bc = $_POST['jenis_bc'];
    $bc_date  = $_POST['bc_date'];
    $nomor_bc = $_POST['nomor_bc'];

    $sqlInsert = "INSERT INTO dbo.BC_TRANS (NO_TRANS, JENIS_BC, NOMOR_BC, BC_DATE) VALUES (?, ?, ?, ?)";
    $stmt = sqlsrv_query($conn, $sqlInsert, array($no_trans, $jenis_bc, $nomor_bc, $bc_date));

    if ($stmt) {
        header("Location: ?doc=" . urlencode($no_trans) . "&msg=add_success");
        exit;
    } else {
        $pesan = "<div class='alert alert-danger'>Gagal menyimpan data detail.</div>";
    }
}

// 2. UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_detail'])) {
    $id       = $_POST['id'];
    $no_trans = $_POST['no_trans'];
    $jenis_bc = $_POST['jenis_bc'];
    $bc_date  = $_POST['bc_date'];
    $nomor_bc = $_POST['nomor_bc'];

    $sqlUpdate = "UPDATE dbo.BC_TRANS SET JENIS_BC = ?, NOMOR_BC = ?, BC_DATE = ? WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sqlUpdate, array($jenis_bc, $nomor_bc, $bc_date, $id));

    if ($stmt) {
        header("Location: ?doc=" . urlencode($no_trans) . "&msg=update_success");
        exit;
    } else {
        $pesan = "<div class='alert alert-danger'>Gagal memperbarui data.</div>";
    }
}

// 3. DELETE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_detail'])) {
    $id = $_POST['id_hapus'];
    $no_trans = $_POST['no_trans_hapus'];

    $sqlDelete = "DELETE FROM dbo.BC_TRANS WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sqlDelete, array($id));

    if ($stmt) {
        header("Location: ?doc=" . urlencode($no_trans) . "&msg=del_success");
        exit;
    } else {
        $pesan = "<div class='alert alert-danger'>Gagal menghapus data.</div>";
    }
}

// Menangkap Pesan Sukses
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'add_success') $pesan = "<div class='alert alert-success'>Data BC berhasil ditambahkan!</div>";
    if ($_GET['msg'] == 'update_success') $pesan = "<div class='alert alert-success'>Data BC berhasil diperbarui!</div>";
    if ($_GET['msg'] == 'del_success') $pesan = "<div class='alert alert-success'>Data BC berhasil dihapus!</div>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Detail INVENTORY BC (Kosong di Atas)</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f0f0f0; }
        .header-title { background-color: #000080; color: white; padding: 10px; font-weight: bold; margin-bottom: 15px; }
        .grid-container { display: flex; gap: 20px; align-items: flex-start; }
        .table-wrapper { 
            background-color: white; border: 1px solid #ccc; height: 400px; 
            overflow-y: auto; overflow-x: auto; box-shadow: inset 1px 1px 3px rgba(0,0,0,0.1); 
        }
        .kiri, .kanan { flex: 1; min-width: 450px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border: 1px solid #c0c0c0; padding: 5px 8px; text-align: left; white-space: nowrap; }
        th { background-color: #e4e4e4; position: sticky; top: 0; z-index: 10; }
        
        tr.master-row { cursor: pointer; }
        tr.master-row:hover { background-color: #e8f0fe; }
        tr.selected { background-color: #0078D7 !important; color: white; }
        .has-bc { border-left: 4px solid #28a745; } 

        input[type="text"], input[type="date"], select { 
            width: 100%; padding: 4px; box-sizing: border-box; border: 1px solid #999; 
        }
        button { padding: 4px 8px; cursor: pointer; font-size: 12px; border: none; color: white; margin-right: 2px;}
        .btn-simpan { background-color: #28a745; }
        .btn-edit { background-color: #ffc107; color: black; }
        .btn-hapus { background-color: #dc3545; }
        .btn-batal { background-color: #6c757d; text-decoration: none; padding: 4px 8px; font-size: 12px; color: white;}
        
        .alert { padding: 10px; margin-bottom: 10px; font-size: 14px;}
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

    <div class="header-title">ORDERING SYSTEM - INVENTORY TRANS & BC_TRANS</div>
    <?= $pesan; ?>

    <div class="grid-container">
        
        <!-- ================= KOLOM KIRI: MASTER ================= -->
        <div class="kiri">
            <strong>Data Inventory (Tabel TRANS)</strong> <br>
            <small style="color: #666;">(Yang belum diisi BC tampil paling atas)</small>
            <div class="table-wrapper" style="margin-top: 5px;">
                <table id="tabelMaster">
                    <thead>
                        <tr>
                            <th>Tgl Trans (TRAN_DATE)</th>
                            <th>Doc Trans (TRAN_DOC)</th>
                            <th>Tipe (TRTY_CODE)</th>
                            <th>Status BC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // ==========================================
                        // PERUBAHAN QUERY ADA DI SINI (ORDER BY)
                        // ==========================================
                        $sqlMaster = "
                            SELECT TOP 100 
                                t.TRAN_DATE, t.TRAN_DOC, t.TRTY_CODE,
                                (SELECT COUNT(*) FROM dbo.BC_TRANS b WHERE b.NO_TRANS = t.TRAN_DOC) as TotalBC
                            FROM dbo.TRANS t
                            WHERE t.TRTY_CODE IN ('01', '02', '05', '06', '11', '13', '15', '16', '44', '59')
                            ORDER BY 
                                (SELECT COUNT(*) FROM dbo.BC_TRANS b WHERE b.NO_TRANS = t.TRAN_DOC) ASC, 
                                t.TRAN_DATE DESC";
                        
                        $stmtMaster = sqlsrv_query($conn, $sqlMaster);

                        if ($stmtMaster !== false) {
                            while ($row = sqlsrv_fetch_array($stmtMaster, SQLSRV_FETCH_ASSOC)) {
                                $tran_doc = $row['TRAN_DOC'];
                                $tran_date = is_object($row['TRAN_DATE']) ? $row['TRAN_DATE']->format('d-m-Y') : date('d-m-Y', strtotime($row['TRAN_DATE']));
                                
                                $isSelected = ($tran_doc === $selected_doc) ? "selected" : "";
                                $bcStatusClass = ($row['TotalBC'] > 0) ? "has-bc" : ""; 
                                
                                // Memberikan warna merah muda sedikit jika masih kosong agar lebih kentara
                                $bgKosong = ($row['TotalBC'] == 0 && !$isSelected) ? "background-color: #fff3f3;" : "";
                                $statusText = ($row['TotalBC'] > 0) ? "Terisi" : "<span style='color:red;'>Kosong</span>";

                                echo "<tr class='master-row {$isSelected} {$bcStatusClass}' style='{$bgKosong}' data-href='?doc=".urlencode($tran_doc)."' onclick=\"window.location='?doc=".urlencode($tran_doc)."'\">
                                        <td>{$tran_date}</td>
                                        <td>" . htmlspecialchars($tran_doc) . "</td>
                                        <td style='text-align:center;'>" . htmlspecialchars($row['TRTY_CODE']) . "</td>
                                        <td>{$statusText}</td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' style='text-align:center;'>Gagal memuat data master.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ================= KOLOM KANAN: DETAIL ================= -->
        <div class="kanan">
            <strong>Detail BC_TRANS</strong> 
            <span style="font-size: 12px; color: #555;">
                <?= $selected_doc ? "(Doc: " . htmlspecialchars($selected_doc) . ")" : "" ?>
            </span>
            <div class="table-wrapper" style="margin-top: 5px;">
                <table>
                    <thead>
                        <tr>
                            <th>JENIS_BC</th>
                            <th>BC_DATE</th>
                            <th>NOMOR_BC</th>
                            <th style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($selected_doc !== '') {
                            $sqlDetail = "SELECT id, JENIS_BC, BC_DATE, NO_TRANS, NOMOR_BC FROM dbo.BC_TRANS WHERE NO_TRANS = ?";
                            $stmtDetail = sqlsrv_query($conn, $sqlDetail, array($selected_doc));
                            $hasDetail = false;

                            $opsi_jenis_bc = ['BC.2.3', 'BC.2.5', 'BC.2.6.1', 'BC.2.6.2', 'BC.2.7', 'BC.3.0', 'BC.4.1'];

                            if ($stmtDetail !== false && sqlsrv_has_rows($stmtDetail)) {
                                $hasDetail = true;
                                while ($rowD = sqlsrv_fetch_array($stmtDetail, SQLSRV_FETCH_ASSOC)) {
                                    $id_bc = $rowD['id'];
                                    $bc_date_val = is_object($rowD['BC_DATE']) ? $rowD['BC_DATE']->format('Y-m-d') : date('Y-m-d', strtotime($rowD['BC_DATE']));
                                    $bc_date_disp = is_object($rowD['BC_DATE']) ? $rowD['BC_DATE']->format('d-m-Y') : date('d-m-Y', strtotime($rowD['BC_DATE']));

                                    if ($edit_id == $id_bc) {
                                        ?>
                                        <form method="POST" action="">
                                            <tr style="background-color: #ffffe0;">
                                                <td>
                                                    <select name="jenis_bc" required>
                                                        <?php foreach($opsi_jenis_bc as $opt) { 
                                                            $sel = ($rowD['JENIS_BC'] == $opt) ? "selected" : "";
                                                            echo "<option value='{$opt}' {$sel}>{$opt}</option>";
                                                        } ?>
                                                    </select>
                                                </td>
                                                <td><input type="date" name="bc_date" value="<?= $bc_date_val ?>" required></td>
                                                <td><input type="text" name="nomor_bc" value="<?= htmlspecialchars($rowD['NOMOR_BC']) ?>" required></td>
                                                <td>
                                                    <input type="hidden" name="id" value="<?= $id_bc ?>">
                                                    <input type="hidden" name="no_trans" value="<?= htmlspecialchars($selected_doc) ?>">
                                                    <button type="submit" name="update_detail" class="btn-simpan">Update</button>
                                                    <a href="?doc=<?= urlencode($selected_doc) ?>" class="btn-batal">Batal</a>
                                                </td>
                                            </tr>
                                        </form>
                                        <?php
                                    } else {
                                        echo "<tr>
                                                <td>" . htmlspecialchars($rowD['JENIS_BC']) . "</td>
                                                <td>{$bc_date_disp}</td>
                                                <td>" . htmlspecialchars($rowD['NOMOR_BC']) . "</td>
                                                <td>
                                                    <div style='display: flex; gap: 5px;'>
                                                        <a href='?doc=".urlencode($selected_doc)."&edit={$id_bc}'><button type='button' class='btn-edit'>Edit</button></a>
                                                        <form method='POST' action='' style='margin:0;' onsubmit=\"return confirm('Yakin ingin menghapus data BC ini?');\">
                                                            <input type='hidden' name='id_hapus' value='{$id_bc}'>
                                                            <input type='hidden' name='no_trans_hapus' value='".htmlspecialchars($selected_doc)."'>
                                                            <button type='submit' name='hapus_detail' class='btn-hapus'>Hapus</button>
                                                        </form>
                                                    </div>
                                                </td>
                                              </tr>";
                                    }
                                }
                            }

                            if (!$hasDetail) {
                                ?>
                                <form method="POST" action="">
                                    <tr style="background-color: #e6f7ff;">
                                        <td>
                                            <select name="jenis_bc" required>
                                                <option value="">-- Pilih --</option>
                                                <?php foreach($opsi_jenis_bc as $opt) { 
                                                    echo "<option value='{$opt}'>{$opt}</option>";
                                                } ?>
                                            </select>
                                        </td>
                                        <td><input type="date" name="bc_date" required></td>
                                        <td><input type="text" name="nomor_bc" required></td>
                                        <td>
                                            <input type="hidden" name="no_trans" value="<?= htmlspecialchars($selected_doc) ?>">
                                            <button type="submit" name="simpan_detail" class="btn-simpan">+ Simpan</button>
                                        </td>
                                    </tr>
                                </form>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='4' style='text-align:center; padding: 20px; color:#888;'>Klik Dokumen Transaksi di sebelah kiri untuk mengelola Detail BC.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ================= JAVASCRIPT ================= -->
    <script>
        document.addEventListener('keydown', function(event) {
            if (event.target.tagName.toLowerCase() === 'input' || event.target.tagName.toLowerCase() === 'select') {
                return; 
            }

            let currentSelected = document.querySelector('tr.master-row.selected');
            if (!currentSelected) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                let nextRow = currentSelected.nextElementSibling;
                if (nextRow && nextRow.classList.contains('master-row')) {
                    window.location.href = nextRow.getAttribute('data-href');
                }
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                let prevRow = currentSelected.previousElementSibling;
                if (prevRow && prevRow.classList.contains('master-row')) {
                    window.location.href = prevRow.getAttribute('data-href');
                }
            }
        });
        
        window.onload = function() {
            let activeRow = document.querySelector('tr.master-row.selected');
            if(activeRow) {
                activeRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        };
    </script>

</body>
</html>