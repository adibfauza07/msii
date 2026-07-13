<?php
// 1. AMBIL ID DARI URL
$id = isset($_GET['id']) ? $_GET['id'] : 0;

// 2. PROSES UPDATE DATA KETIKA TOMBOL "UPDATE PICA" DITEKAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pica'])) {
    $no_tr = $_POST['no_tr'];
    $pica_date = $_POST['pica_date'];
    $customer = $_POST['customer'];
    $supplier = $_POST['supplier'];
    $problem_title = $_POST['problem_title'];
    
    // Update Header
    $sql_header = "UPDATE PICA_HEADER SET NoTR=?, PicaDate=?, Customer=?, Supplier=?, ProblemTitle=? WHERE PicaID=?";
    sqlsrv_query($conn, $sql_header, array($no_tr, $pica_date, $customer, $supplier, $problem_title, $id));

    // Proses Upload File (Hanya jika user memilih file baru)
    $support_file_query = "";
    $params_why = array();
    
    $main_problem = $_POST['main_problem'];
    $data_support = $_POST['data_support'];
    $why1 = $_POST['why1']; $why2 = $_POST['why2']; $why3 = $_POST['why3']; $why4 = $_POST['why4']; $why5 = $_POST['why5'];

    if (isset($_FILES['support_file']) && $_FILES['support_file']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/';
        $file_ext = strtolower(pathinfo($_FILES['support_file']['name'], PATHINFO_EXTENSION));
        $support_file_name = time() . '_' . rand(100,999) . '.' . $file_ext;
        move_uploaded_file($_FILES['support_file']['tmp_name'], $upload_dir . $support_file_name);
        
        // Jika ada file baru, update juga kolom filenya
        $sql_why = "UPDATE PICA_5WHY SET MainProblem=?, DataSupport=?, Why1=?, Why2=?, Why3=?, Why4=?, Why5=?, DataSupportFile=? WHERE PicaID=?";
        $params_why = array($main_problem, $data_support, $why1, $why2, $why3, $why4, $why5, $support_file_name, $id);
    } else {
        // Jika tidak ada file baru, biarkan file lama
        $sql_why = "UPDATE PICA_5WHY SET MainProblem=?, DataSupport=?, Why1=?, Why2=?, Why3=?, Why4=?, Why5=? WHERE PicaID=?";
        $params_why = array($main_problem, $data_support, $why1, $why2, $why3, $why4, $why5, $id);
    }
    sqlsrv_query($conn, $sql_why, $params_why);

    // Update Action (Dengan kolom Dept dan PIC yang sudah dipisah)
    $obj_target = $_POST['obj_target'];
    $activity = $_POST['activity'];
    $dept = $_POST['dept'];
    $pic = $_POST['pic'];
    $status_remark = $_POST['status_remark'];
    
    $sql_action = "UPDATE PICA_ACTION SET ObjectiveTarget=?, Activity=?, Dept=?, PIC=?, StatusRemark=? WHERE PicaID=?";
    sqlsrv_query($conn, $sql_action, array($obj_target, $activity, $dept, $pic, $status_remark, $id));

    echo "<div class='alert alert-success shadow-sm'><i class='bi bi-check-circle'></i> Data PICA berhasil diupdate!</div>";
}

// 3. AMBIL DATA LAMA DARI DATABASE UNTUK DITAMPILKAN DI FORM
$sql_data = "SELECT h.*, CONVERT(varchar, h.PicaDate, 23) as Tanggal, 
             w.MainProblem, w.DataSupport, w.Why1, w.Why2, w.Why3, w.Why4, w.Why5, w.DataSupportFile,
             a.ObjectiveTarget, a.Activity, a.Dept, a.PIC, a.StatusRemark
             FROM PICA_HEADER h
             LEFT JOIN PICA_5WHY w ON h.PicaID = w.PicaID
             LEFT JOIN PICA_ACTION a ON h.PicaID = a.PicaID
             WHERE h.PicaID = ?";
$stmt_data = sqlsrv_query($conn, $sql_data, array($id));
$dt = sqlsrv_fetch_array($stmt_data, SQLSRV_FETCH_ASSOC);

if (!$dt) { echo "<div class='alert alert-danger'>Data tidak ditemukan!</div>"; return; }
?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Data PICA #<?php echo $id; ?></h5>
    </div>
    <div class="card-body bg-white">
        <form method="POST" action="" enctype="multipart/form-data">
            <h6 class="text-primary border-bottom pb-2 mt-2">1. Informasi Umum</h6>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold">No TR</label>
                    <input type="text" name="no_tr" class="form-control" value="<?php echo htmlspecialchars($dt['NoTR']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold">Tanggal</label>
                    <input type="date" name="pica_date" class="form-control" value="<?php echo isset($dt['Tanggal']) ? $dt['Tanggal'] : ''; ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold">Customer</label>
                    <select name="customer" class="form-select select2-search" required>
                        <option value=""></option>
                        <?php
                        $sql_cust = "SELECT CUST_CODE, CUST_COMP FROM CUST WHERE CUST_INACTIVE = 0 ORDER BY CUST_COMP ASC";
                        $stmt_cust = sqlsrv_query($conn, $sql_cust);
                        if ($stmt_cust) {
                            while ($row_cust = sqlsrv_fetch_array($stmt_cust, SQLSRV_FETCH_ASSOC)) {
                                $cust_code = htmlspecialchars($row_cust['CUST_CODE']);
                                $cust_name = htmlspecialchars($row_cust['CUST_COMP']);
                                // Jika CUST_CODE sama dengan yang ada di database, tandai sebagai "selected"
                                $selected = ($dt['Customer'] == $cust_code) ? "selected" : "";
                                echo "<option value='{$cust_code}' {$selected}>{$cust_code} - {$cust_name}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">Supplier</label>
                    <input type="text" name="supplier" class="form-control bg-light" value="PT IMC Tekno Indonesia" readonly required>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">Judul Masalah</label>
                    <input type="text" name="problem_title" class="form-control" value="<?php echo htmlspecialchars($dt['ProblemTitle']); ?>" required>
                </div>
            </div>

            <h6 class="text-primary border-bottom pb-2 mt-4">2. Analisa Masalah (5 Why)</h6>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold">Main Problem</label>
                    <textarea name="main_problem" class="form-control" rows="3"><?php echo htmlspecialchars($dt['MainProblem']); ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold">Data Support (Teks)</label>
                    <textarea name="data_support" class="form-control" rows="3"><?php echo htmlspecialchars($dt['DataSupport']); ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold">Data Support (Upload File)</label>
                    <input type="file" name="support_file" class="form-control mb-1" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx">
                    <?php if(!empty($dt['DataSupportFile'])): ?>
                        <small class="text-success"><i class="bi bi-paperclip"></i> File tersimpan: <?php echo $dt['DataSupportFile']; ?></small><br>
                    <?php endif; ?>
                    <small class="text-muted" style="font-size: 11px;">Biarkan kosong jika tidak ingin mengubah file lama.</small>
                </div>
            </div>
            
            <div class="row mb-4 g-2">
                <div class="col-md"><label class="form-label text-muted small fw-bold">1st Why</label><input type="text" name="why1" class="form-control" value="<?php echo htmlspecialchars($dt['Why1']); ?>"></div>
                <div class="col-md"><label class="form-label text-muted small fw-bold">2nd Why</label><input type="text" name="why2" class="form-control" value="<?php echo htmlspecialchars($dt['Why2']); ?>"></div>
                <div class="col-md"><label class="form-label text-muted small fw-bold">3rd Why</label><input type="text" name="why3" class="form-control" value="<?php echo htmlspecialchars($dt['Why3']); ?>"></div>
                <div class="col-md"><label class="form-label text-muted small fw-bold">4th Why</label><input type="text" name="why4" class="form-control" value="<?php echo htmlspecialchars($dt['Why4']); ?>"></div>
                <div class="col-md"><label class="form-label text-muted small fw-bold">5th Why</label><input type="text" name="why5" class="form-control" value="<?php echo htmlspecialchars($dt['Why5']); ?>"></div>
            </div>

            <h6 class="text-primary border-bottom pb-2 mt-4">3. Tindakan Perbaikan</h6>
            <div class="row mb-4">
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Objective Target</label>
                    <textarea name="obj_target" class="form-control" rows="2"><?php echo htmlspecialchars($dt['ObjectiveTarget']); ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Activity</label>
                    <textarea name="activity" class="form-control" rows="2"><?php echo htmlspecialchars($dt['Activity']); ?></textarea>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold">Dept</label>
                    <input type="text" name="dept" class="form-control" value="<?php echo isset($dt['Dept']) ? htmlspecialchars($dt['Dept']) : ''; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold">P.I.C</label>
                    <input type="text" name="pic" class="form-control" value="<?php echo isset($dt['PIC']) ? htmlspecialchars($dt['PIC']) : ''; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold">Status (Remark)</label>
                    <select name="status_remark" class="form-select">
                        <option value="Open" <?php if($dt['StatusRemark']=='Open') echo 'selected'; ?>>Open</option>
                        <option value="Closed" <?php if($dt['StatusRemark']=='Closed') echo 'selected'; ?>>Closed</option>
                    </select>
                </div>
            </div>

            <hr>
            <!-- INI ADALAH TOMBOL UPDATE YANG KAMU MINTA -->
            <button type="submit" name="update_pica" class="btn btn-warning px-5 py-2 fw-bold text-dark">
                <i class="bi bi-save"></i> Update PICA
            </button>
            <a href="?page=list_pica" class="btn btn-secondary px-4 py-2 ms-2">Kembali ke Daftar</a>
        </form>
    </div>
</div>