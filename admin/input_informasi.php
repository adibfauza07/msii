<?php
// ==========================================
// 1. ENDPOINT AJAX (AUTOCOMPLETE & GET DETAIL)
// ==========================================
if (isset($_GET['action'])) {
    error_reporting(0); 
    require_once __DIR__ . "/../config/global.php";
    
    // A. Aksi Autocomplete
    if ($_GET['action'] == 'autocomplete') {
        $term = isset($_GET['term']) ? $_GET['term'] : '';
        $result = array();
        
        if ($term !== '') {
            $sql = "SELECT id, kode_dokumen, info_subject FROM tbl_informasi_internal WHERE kode_dokumen LIKE ? OR info_subject LIKE ?";
            $params = array("%$term%", "%$term%");
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $result[] = array(
                        'id' => $row['id'],
                        'value' => $row['kode_dokumen'], 
                        'label' => $row['kode_dokumen'] . ' - ' . $row['info_subject']
                    );
                }
            }
        }
        ob_clean(); 
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    // B. Aksi Get Detail untuk Edit Form
    if ($_GET['action'] == 'get_detail' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $sql = "SELECT * FROM tbl_informasi_internal WHERE id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($id));
        
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Format tanggal agar sesuai dengan input type="date"
            $row['info_date'] = $row['info_date'] ? $row['info_date']->format('Y-m-d') : '';
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode($row);
            exit;
        }
    }
}

// ==========================================
// 2. PROSES SIMPAN / UPDATE DATA FORM
// ==========================================
require_once __DIR__ . "/../config/global.php";
$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btnSave'])) {
    
    $id                = $_POST['id']; // Hidden input
    $info_date         = $_POST['info_date'];
    $info_to           = $_POST['info_to'];
    $info_cc           = $_POST['info_cc'];
    $info_from         = $_POST['info_from'];
    $info_subject      = $_POST['info_subject'];
    $approved_by       = $_POST['approved_by'];
    $checked_by        = $_POST['checked_by'];
    $prepared_by       = $_POST['prepared_by'];
    $message_body      = $_POST['message_body'];
    $acknowledged_dept = $_POST['acknowledged_dept'];
    $acknowledged_by   = $_POST['acknowledged_by'];

    if (empty($id)) {
        // --- MODE INSERT (DATA BARU) ---
        $kode_dokumen = "INF-" . date("ymd") . "-" . strtoupper(substr(uniqid(), -4));
        $tsql = "INSERT INTO tbl_informasi_internal 
                (kode_dokumen, info_date, info_to, info_cc, info_from, info_subject, approved_by, checked_by, prepared_by, message_body, acknowledged_dept, acknowledged_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
        $params = array(
            $kode_dokumen, $info_date, $info_to, $info_cc, $info_from, $info_subject, 
            $approved_by, $checked_by, $prepared_by, $message_body, 
            $acknowledged_dept, $acknowledged_by
        );

        $stmt = sqlsrv_query($conn, $tsql, $params);
        if ($stmt) {
            $msg = "<div class='alert alert-success'>Data baru berhasil disimpan dengan Kode: <b>{$kode_dokumen}</b></div>";
        } else {
            $msg = "<div class='alert alert-danger'>Gagal menyimpan data baru.</div>";
        }
    } else {
        // --- MODE UPDATE (EDIT DATA) ---
        $tsql = "UPDATE tbl_informasi_internal SET 
                    info_date=?, info_to=?, info_cc=?, info_from=?, info_subject=?, 
                    approved_by=?, checked_by=?, prepared_by=?, message_body=?, 
                    acknowledged_dept=?, acknowledged_by=? 
                 WHERE id=?";
                 
        $params = array(
            $info_date, $info_to, $info_cc, $info_from, $info_subject, 
            $approved_by, $checked_by, $prepared_by, $message_body, 
            $acknowledged_dept, $acknowledged_by, $id
        );

        $stmt = sqlsrv_query($conn, $tsql, $params);
        if ($stmt) {
            $msg = "<div class='alert alert-info'>Data berhasil di-update.</div>";
        } else {
            $msg = "<div class='alert alert-danger'>Gagal meng-update data.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input & Search Form Internal Information</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    
    <style>
        .ui-autocomplete { max-height: 250px; overflow-y: auto; overflow-x: hidden; font-size: 14px; z-index: 9999; }
        .ui-menu-item .ui-menu-item-wrapper { padding: 8px 10px; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">

    <!-- PENCARIAN & ACTION -->
    <div class="card shadow-sm mb-4 border-secondary">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Pencarian, Edit & Cetak Dokumen</h5>
        </div>
        <div class="card-body">
            <label class="form-label fw-bold">Cari Berdasarkan Kode atau Subject</label>
            <div class="input-group mb-2">
                <input type="text" id="search_dokumen" class="form-control" placeholder="Ketik kode INF-... atau subject">
                
                <button class="btn btn-secondary" type="button" id="btnLihat">Buka / Preview</button>
                <button class="btn btn-primary" type="button" id="btnPrint">🖨️ Print</button>
                <button class="btn btn-danger" type="button" id="btnPdf">📄 Export PDF</button>
            </div>
            <small class="text-muted text-success fw-bold">* Jika Anda memilih data dari pencarian, otomatis form di bawah akan terisi dan masuk mode Edit.</small>
        </div>
    </div>

    <!-- FORM (BISA UNTUK INPUT & EDIT) -->
    <div class="card shadow-sm border-primary" id="formContainer">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0" id="formTitle">Buat Informasi Internal Baru</h5>
            <button type="button" class="btn btn-sm btn-light fw-bold" id="btnCancelEdit" style="display:none;">Batalkan Edit (Buat Baru)</button>
        </div>
        <div class="card-body">
            <?php echo $msg; ?>
            <form method="POST" action="" id="mainForm">
                
                <!-- HIDDEN INPUT ID UNTUK UPDATE -->
                <input type="hidden" name="id" id="form_id" value="">

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Date</label>
                        <input type="date" name="info_date" id="info_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">To</label>
                        <input type="text" name="info_to" id="info_to" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Cc</label>
                        <input type="text" name="info_cc" id="info_cc" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">From</label>
                        <input type="text" name="info_from" id="info_from" class="form-control" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Subject</label>
                    <input type="text" name="info_subject" id="info_subject" class="form-control" required>
                </div>

                <hr>
                <h6>Signatures</h6>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Approved By</label>
                        <input type="text" name="approved_by" id="approved_by" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Checked By</label>
                        <input type="text" name="checked_by" id="checked_by" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Prepared By</label>
                        <input type="text" name="prepared_by" id="prepared_by" class="form-control">
                    </div>
                </div>
                
                <hr>
                <div class="mb-3">
                    <label class="form-label fw-bold">Message / Isi Informasi</label>
                    <textarea name="message_body" id="message_body" rows="6" class="form-control" required></textarea>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Mengetahui (Dept)</label>
                        <input type="text" name="acknowledged_dept" id="acknowledged_dept" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Mengetahui (Nama)</label>
                        <input type="text" name="acknowledged_by" id="acknowledged_by" class="form-control">
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" name="btnSave" id="btnSubmitForm" class="btn btn-primary px-4 fw-bold">Simpan Form</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(document).ready(function() {
    var selectedId = null;

    // Inisialisasi Autocomplete
    $("#search_dokumen").autocomplete({
        source: "input_informasi.php?action=autocomplete",
        minLength: 2,
        select: function(event, ui) {
            selectedId = ui.item.id;
            
            // Ketika dipilih, panggil AJAX untuk mengambil isi data full
            $.getJSON("input_informasi.php?action=get_detail&id=" + selectedId, function(data) {
                // Isi form dengan data yang didapat
                $("#form_id").val(data.id);
                $("#info_date").val(data.info_date);
                $("#info_to").val(data.info_to);
                $("#info_cc").val(data.info_cc);
                $("#info_from").val(data.info_from);
                $("#info_subject").val(data.info_subject);
                $("#approved_by").val(data.approved_by);
                $("#checked_by").val(data.checked_by);
                $("#prepared_by").val(data.prepared_by);
                $("#message_body").val(data.message_body);
                $("#acknowledged_dept").val(data.acknowledged_dept);
                $("#acknowledged_by").val(data.acknowledged_by);

                // Ubah tampilan menjadi Mode Edit
                $("#formContainer").removeClass("border-primary").addClass("border-success");
                $("#formTitle").parent().removeClass("bg-primary").addClass("bg-success");
                $("#formTitle").html("Edit Informasi Internal: " + data.kode_dokumen);
                
                $("#btnSubmitForm").text("Update Form").removeClass("btn-primary").addClass("btn-success");
                $("#btnCancelEdit").show();
            });
        }
    });

    // Aksi Tombol Batal Edit
    $("#btnCancelEdit").click(function(){
        // Kosongkan ID dan reset form
        $("#form_id").val('');
        $("#mainForm")[0].reset();
        selectedId = null;
        $("#search_dokumen").val('');

        // Kembalikan tampilan ke Mode Insert
        $("#formContainer").removeClass("border-success").addClass("border-primary");
        $("#formTitle").parent().removeClass("bg-success").addClass("bg-primary");
        $("#formTitle").html("Buat Informasi Internal Baru");
        
        $("#btnSubmitForm").text("Simpan Form").removeClass("btn-success").addClass("btn-primary");
        $(this).hide();
    });

    // Validasi Pilihan untuk Tombol Action (Preview/Print/PDF)
    function checkSelection() {
        if (!selectedId) {
            alert("Silakan ketik dan pilih dokumen dari daftar dropdown terlebih dahulu.");
            return false;
        }
        return true;
    }

    $("#btnLihat").click(function() {
        if (checkSelection()) window.location.href = "print_informasi.php?id=" + selectedId;
    });

    $("#btnPrint").click(function() {
        if (checkSelection()) window.location.href = "print_informasi.php?id=" + selectedId + "&trigger=print";
    });

    $("#btnPdf").click(function() {
        if (checkSelection()) window.location.href = "print_informasi.php?id=" + selectedId + "&trigger=pdf";
    });
});
</script>
</body>
</html>