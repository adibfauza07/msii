<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_pica'])) {
    $no_tr = $_POST['no_tr'];
    $pica_date = $_POST['pica_date'];
    $customer = $_POST['customer'];
    $supplier = $_POST['supplier'];
    $problem_title = $_POST['problem_title'];
    
    $sql_header = "INSERT INTO PICA_HEADER (NoTR, PicaDate, Customer, Supplier, ProblemTitle) 
                   VALUES (?, ?, ?, ?, ?); SELECT SCOPE_IDENTITY() as PicaID;";
    $params_header = array($no_tr, $pica_date, $customer, $supplier, $problem_title);
    
    $stmt_header = sqlsrv_query($conn, $sql_header, $params_header);
    
    if ($stmt_header) {
        sqlsrv_next_result($stmt_header);
        $row = sqlsrv_fetch_array($stmt_header);
        $pica_id = $row['PicaID'];

        $main_problem = $_POST['main_problem'];
        $data_support = $_POST['data_support'];
        $why1 = $_POST['why1']; $why2 = $_POST['why2']; $why3 = $_POST['why3']; $why4 = $_POST['why4']; $why5 = $_POST['why5'];

        $sql_why = "INSERT INTO PICA_5WHY (PicaID, MainProblem, DataSupport, Why1, Why2, Why3, Why4, Why5) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $params_why = array($pica_id, $main_problem, $data_support, $why1, $why2, $why3, $why4, $why5);
        sqlsrv_query($conn, $sql_why, $params_why);

        // --- PERUBAHAN: Query Insert disesuaikan dengan 2 kolom baru ---
        $obj_target = $_POST['obj_target'];
        $activity = $_POST['activity'];
        $dept = $_POST['dept'];
        $pic = $_POST['pic'];
        $status_remark = $_POST['status_remark'];

        $sql_action = "INSERT INTO PICA_ACTION (PicaID, ObjectiveTarget, Activity, Dept, PIC, StatusRemark) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        $params_action = array($pica_id, $obj_target, $activity, $dept, $pic, $status_remark);
        sqlsrv_query($conn, $sql_action, $params_action);

        echo "<div class='alert alert-success shadow-sm'><i class='bi bi-check-circle'></i> Data PICA berhasil disimpan!</div>";
    } else {
        $errors = sqlsrv_errors();
        $pesan_error = "";
        if ($errors != null) {
            foreach ($errors as $error) { $pesan_error .= "<b>Error:</b> " . $error['message'] . "<br>"; }
        }
        echo "<div class='alert alert-danger shadow-sm'><i class='bi bi-exclamation-triangle'></i> Gagal menyimpan data! <br><hr>Detail Sistem:<br>".$pesan_error."</div>";
    }
}
?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Form Input PICA Baru</h5>
    </div>
    <div class="card-body bg-white">
        <form method="POST" action="">
            <h6 class="text-primary border-bottom pb-2 mt-2">1. Informasi Umum</h6>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold">No TR</label>
                    <input type="text" name="no_tr" class="form-control" placeholder="Contoh: 25/II/PPIC-2026" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold">Tanggal</label>
                    <input type="date" name="pica_date" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold">Customer</label>
                    <select name="customer" class="form-select select2-search" required>
                        <option value=""></option>
                        <option value="IDBM">IDBM</option>
                        <option value="CUSTOMER A">CUSTOMER A</option>
                    </select>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">Supplier</label>
                    <select name="supplier" class="form-select select2-search" required>
                        <option value=""></option>
                        <option value="IMC TEKNO INDONESIA">IMC TEKNO INDONESIA</option>
                        <option value="SUPPLIER A">SUPPLIER A</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">Judul Masalah (Problem)</label>
                    <input type="text" name="problem_title" class="form-control" placeholder="Contoh: Part No pada kemasan berbeda" required>
                </div>
            </div>

            <h6 class="text-primary border-bottom pb-2 mt-4">2. Analisa Masalah (5 Why)</h6>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">Main Problem</label>
                    <textarea name="main_problem" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">Data Support</label>
                    <textarea name="data_support" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="row mb-4 g-2">
                <div class="col-md"><label class="form-label text-muted small fw-bold">1st Why</label><input type="text" name="why1" class="form-control"></div>
                <div class="col-md"><label class="form-label text-muted small fw-bold">2nd Why</label><input type="text" name="why2" class="form-control"></div>
                <div class="col-md"><label class="form-label text-muted small fw-bold">3rd Why</label><input type="text" name="why3" class="form-control"></div>
                <div class="col-md"><label class="form-label text-muted small fw-bold">4th Why</label><input type="text" name="why4" class="form-control"></div>
                <div class="col-md"><label class="form-label text-muted small fw-bold">5th Why</label><input type="text" name="why5" class="form-control"></div>
            </div>

            <h6 class="text-primary border-bottom pb-2 mt-4">3. Tindakan Perbaikan (Corrective Action)</h6>
            <div class="row mb-4">
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Objective Target</label>
                    <textarea name="obj_target" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Activity</label>
                    <textarea name="activity" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold">Dept</label>
                    <input type="text" name="dept" class="form-control" placeholder="Contoh: Prod">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold">P.I.C</label>
                    <input type="text" name="pic" class="form-control" placeholder="Contoh: Member WH">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold">Status (Remark)</label>
                    <select name="status_remark" class="form-select">
                        <option value="Open">Open</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>
            </div>

            <hr>
            <button type="submit" name="simpan_pica" class="btn btn-success px-5 py-2">
                <i class="bi bi-save"></i> Simpan PICA
            </button>
        </form>
    </div>
</div>