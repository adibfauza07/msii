<?php
require_once __DIR__ . '/../config/database_p1.php';

// === Ambil data jenis trial ===
$jenis_list = [];
$resJenis = sqlsrv_query($conn, "SELECT ID, JENIS_TRIAL FROM TRIAL_PE_JENIS ORDER BY JENIS_TRIAL");
while ($row = sqlsrv_fetch_array($resJenis, SQLSRV_FETCH_ASSOC)) {
    $jenis_list[] = $row;
}

// === Ambil data Judge Trial ===
$judge_list = [];
$resJudge = sqlsrv_query($conn, "SELECT ID, JUDGE_TRIAL FROM JUDGE_TRIAL ORDER BY JUDGE_TRIAL");
while ($row = sqlsrv_fetch_array($resJudge, SQLSRV_FETCH_ASSOC)) {
    $judge_list[] = $row;
}

// === Auto-generate TRIAL_CODE (integer) ===
$resLast = sqlsrv_query($conn, "SELECT TOP 1 TRIAL_CODE FROM TRIAL_PE ORDER BY TRIAL_CODE DESC");
$nextNum = 1;
if ($resLast && ($last = sqlsrv_fetch_array($resLast, SQLSRV_FETCH_ASSOC))) {
    $nextNum = intval($last['TRIAL_CODE']) + 1;
}
$autoTrialCode = $nextNum;

// === Simpan data TRIAL_PE ===
/* ============================================================
   SIMPAN DATA TRIAL_PE (FINAL FIX)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    function val($k, $d = "") {
        return isset($_POST[$k]) ? $_POST[$k] : $d;
    }

    /* =============== UPLOAD FOTO (OPSIONAL) ==================== */
    $foto_name = null;
    if (!empty($_FILES['foto']['name'])) {
        $dir = "../assets/foto_trial/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $foto_name = time() . "_" . basename($_FILES['foto']['name']);
        move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $foto_name);
    }

    /* ============================================================
       FIX TERPENTING!!!
       Ambil CUST_ID & MAT_USING (MAT_ID INT) dari MASTER berdasarkan PART_CODE
    ============================================================ */
    $part_code = val('PART_CODE');

    $sqlMaster = "
        SELECT TOP 1
            icvt.CUST_ID,
            std.MAT_CODE,
            mat.ITEM_ID AS MAT_ID
        FROM ITEM_CUST_VIEW_TRIAL icvt
        INNER JOIN TRIAL_PE_STD std ON std.ITEM_CODE = icvt.PART_CODE
        INNER JOIN ITEMS mat ON mat.ITEM_CODE = std.MAT_CODE
        WHERE icvt.PART_CODE = ?
    ";

    $stmtM = sqlsrv_query($conn, $sqlMaster, array($part_code));
    $master = sqlsrv_fetch_array($stmtM, SQLSRV_FETCH_ASSOC);

    // Jika tidak ketemu, fallback ke POST (tetapi tetap INT)
    $cust_id   = $master ? intval($master['CUST_ID']) : intval(val('CUST_ID'));
    $mat_id    = $master ? intval($master['MAT_ID'])  : intval(val('MAT_USING'));

    /* ============================================================
       SIAP INSERT — TANPA TRIAL_CODE karena IDENTITY
    ============================================================ */

    $sql = "INSERT INTO TRIAL_PE (
        DATE, 
        PART_CODE, 
        CUST_ID, 
        QUANTITY_TRIAL, 
        TRIAL_REASON, 
        TRIAL_TIMES,
        MAT_USING, 
        MAT_DRYING_TIME, 
        MOLD_SET_UP, 
        MOLD_SET_DOWN, 
        TRIAL_DURATION,
        QE_COMMENT, 
        PE_COMMENT, 
        JUDGE_ID, 
        PIC, 
        WEIGHT_RUNNER, 
        PREPARED,
        CHECKED, 
        APPROVED, 
        QTY_OK, 
        QTY_NG, 
        CYCLE_TIME_ACT, 
        MAC_NO, 
        JENIS_ID,
        TONAGE, 
        CORRECTIVE_ACTION, 
        ANALYSYS, 
        foto
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $params = [
        val('DATE'),                        // 1
        $part_code,                         // 2 PART_CODE
        $cust_id,                           // 3 CUST_ID INT
        floatval(val('QUANTITY_TRIAL')),    // 4
        val('TRIAL_REASON'),                // 5
        val('TRIAL_TIMES'),                 // 6
        $mat_id,                            // 7 MAT_USING = MAT_ID (INT)  *FIX*
        intval(val('MAT_DRYING_TIME')),     // 8
        intval(val('MOLD_SET_UP')),         // 9
        intval(val('MOLD_SET_DOWN')),       // 10
        val('TRIAL_DURATION'),              // 11
        val('QE_COMMENT'),                  // 12
        val('PE_COMMENT'),                  // 13
        intval(val('JUDGE_ID')),            // 14
        val('PIC'),                         // 15
        floatval(val('WEIGHT_RUNNER')),     // 16
        val('PREPARED'),                    // 17
        val('CHECKED'),                     // 18
        val('APPROVED'),                    // 19
        intval(val('QTY_OK')),              // 20
        intval(val('QTY_NG')),              // 21
        floatval(val('CYCLE_TIME_ACT')),    // 22
        intval(val('MAC_NO')),              // 23
        intval(val('JENIS_ID')),            // 24
        intval(val('TONAGE')),              // 25
        val('CORRECTIVE_ACTION'),           // 26
        val('ANALYSYS'),                    // 27
        $foto_name                          // 28
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        // Ambil TRIAL_CODE baru
        $res = sqlsrv_query($conn, "SELECT SCOPE_IDENTITY() AS ID");
        $idRow = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
        $newID = intval($idRow["ID"]);

        echo "<script>
            alert('Data trial berhasil disimpan!');
            window.location='input_trial_pe.php?code=$newID';
        </script>";
        exit;
    } else {
        echo "<pre>Gagal menyimpan data trial:\n" . print_r(sqlsrv_errors(), true) . "</pre>";
    }
}


?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Input Trial Produk PE</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="../assets/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
<!-- Bootstrap JS bundle (untuk modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
body { background:#f7f9fc; font-family:"Segoe UI",Arial,sans-serif;}
.card { border:none; border-radius:1rem; box-shadow:0 3px 10px rgba(0,0,0,0.1);}
label { font-weight:500;}
button:disabled { opacity:.5; cursor:not-allowed; }
</style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3>Form Input Trial Produk (Product Engineering)</h3>
    <a href="dashboard_pe.php" class="btn btn-outline-secondary btn-sm">Kembali</a>
  </div>

  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" id="CURRENT_CODE">

    <div class="card p-4 mb-3">
      <div class="row g-3">

        <!-- ===================== FORM KIRI ====================== -->
        <div class="col-md-8">
          <div class="row g-3">

            <div class="col-md-6">
              <label>Kode Trial</label>
              <input type="text" name="TRIAL_CODE" id="TRIAL_CODE" class="form-control" readonly value="<?= $autoTrialCode ?>">
            </div>

            <div class="col-md-6">
              <label>Tanggal</label>
              <input type="date" name="DATE" class="form-control">
            </div>

            <div class="col-md-6">
              <label>Kode Part</label>
              <input type="text" name="PART_CODE" id="PART_CODE" class="form-control" autocomplete="off">
            </div>

            <div class="col-md-6">
              <label>Nama Part</label>
              <input type="text" id="PART_NAME" class="form-control" readonly>
            </div>

            <div class="col-md-6">
              <label>Customer</label>
              <input type="text" id="CUST_COMP" class="form-control" readonly>
              <input type="hidden" name="CUST_ID" id="CUST_ID">
            </div>

            <div class="col-md-6">
              <label>Material</label>
              <input type="hidden" name="MAT_USING" id="MAT_USING">
              <input type="text" id="MAT_NAME" class="form-control" readonly>
            </div>

            <div class="col-md-6">
              <label>Qty Trial</label>
              <input type="number" name="QUANTITY_TRIAL" class="form-control">
            </div>

            <div class="col-md-6">
              <label>Alasan Trial</label>
              <input type="text" name="TRIAL_REASON" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Trial Ke</label>
              <input type="number" name="TRIAL_TIMES" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Drying Time</label>
              <input type="number" name="MAT_DRYING_TIME" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Mold Set Up</label>
              <input type="number" name="MOLD_SET_UP" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Mold Set Down</label>
              <input type="number" name="MOLD_SET_DOWN" class="form-control">
            </div>

            <div class="col-md-4">
              <label>Durasi Trial</label>
              <input type="text" name="TRIAL_DURATION" class="form-control">
            </div>

            <div class="col-md-4">
              <label>Cycle Time Actual</label>
              <input type="text" name="CYCLE_TIME_ACT" class="form-control">
            </div>

            <div class="col-md-4">
              <label>No Mesin</label>
              <input type="text" name="MAC_NO" class="form-control">
            </div>

            <div class="col-md-6">
              <label>QE Comment</label>
              <textarea name="QE_COMMENT" class="form-control"></textarea>
            </div>

            <div class="col-md-6">
              <label>PE Comment</label>
              <textarea name="PE_COMMENT" class="form-control"></textarea>
            </div>

            <div class="col-md-3">
              <label>PIC</label>
              <input type="text" name="PIC" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Weight Runner</label>
              <input type="text" name="WEIGHT_RUNNER" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Prepared By</label>
              <input type="text" name="PREPARED" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Checked By</label>
              <input type="text" name="CHECKED" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Approved By</label>
              <input type="text" name="APPROVED" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Qty OK</label>
              <input type="number" name="QTY_OK" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Qty NG</label>
              <input type="number" name="QTY_NG" class="form-control">
            </div>

            <div class="col-md-3">
              <label>Tonnage</label>
              <input type="text" name="TONAGE" class="form-control">
            </div>

            <div class="col-md-6">
              <label>Corrective Action</label>
              <textarea name="CORRECTIVE_ACTION" class="form-control"></textarea>
            </div>

            <div class="col-md-6">
              <label>Analisis</label>
              <textarea name="ANALYSYS" class="form-control"></textarea>
            </div>

            <div class="col-md-6">
              <label>Jenis Trial</label>
              <select name="JENIS_ID" class="form-select">
                <option value="">-- Pilih Jenis Trial --</option>
                <?php foreach($jenis_list as $j){ echo "<option value='{$j['ID']}'>{$j['JENIS_TRIAL']}</option>"; } ?>
              </select>
            </div>

            <div class="col-md-6">
              <label>Judge</label>
              <select name="JUDGE_ID" class="form-select">
                <option value="">-- Pilih Hasil Trial --</option>
                <?php foreach($judge_list as $j) echo "<option value='{$j['ID']}'>{$j['JUDGE_TRIAL']}</option>"; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label>Upload Foto</label>
              <input type="file" name="foto" class="form-control" accept="image/*">
            </div>

          </div>
        </div>

        <!-- ================= PANEL STD + ACT GRID KANAN ================== -->
        <div class="col-md-4">

          <!-- STD -->
          <div class="card p-3 mb-3" style="background:#eef5ff;">
            <h5>Standard Parameter (STD)</h5>

            <div class="mb-2">
              <label>Weight Part STD</label>
              <input type="text" id="STD_WEIGHT_PART" class="form-control" readonly>
            </div>

            <div class="mb-2">
              <label>Weight Runner STD</label>
              <input type="text" id="STD_WEIGHT_RUNNER" class="form-control" readonly>
            </div>

            <div class="mb-2">
              <label>Cycle Time STD</label>
              <input type="text" id="STD_CYCLE_TIME" class="form-control" readonly>
            </div>

            <div class="mb-2">
              <label>Tonnage STD</label>
              <input type="text" id="STD_TONAGE" class="form-control" readonly>
            </div>

            <div class="mb-2">
              <label>Cavity STD</label>
              <input type="text" id="STD_CAVITY" class="form-control" readonly>
            </div>
          </div>

          <!-- ACT GRID -->
          <div class="card p-3" style="background:#e8fff1;">
            <h5>Actual Parameter (ACT)</h5>

            <button type="button" class="btn btn-success btn-sm mb-2" id="addACTbtn">
              + Tambah Actual
            </button>

            <table class="table table-bordered table-sm" id="ACT_TABLE">
              <thead class="table-light">
                <tr>
                  <th>No</th>
                  <th>Weight Actual</th>
                  <th>Cavity</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <!-- diisi via JS -->
              </tbody>
            </table>
          </div>

        </div>

      </div>
    </div>

    <!-- ===================== BUTTON BAWAH ======================= -->
    <div class="text-center mb-4">

      <button type="button" class="btn btn-dark" id="loadBtn">Load</button>
      <button type="button" class="btn btn-secondary" id="firstBtn">⏮ First</button>
      <button type="button" class="btn btn-secondary" id="prevBtn">← Prev</button>
      <button type="button" class="btn btn-secondary" id="nextBtn">Next →</button>
      <button type="button" class="btn btn-secondary" id="lastBtn">Last ⏭</button>

      <button type="button" class="btn btn-success" id="newBtn">➕ Tambah Data</button>

      <button type="submit" class="btn btn-primary px-5">Simpan Data</button>
    </div>

  </form>
</div>

<!-- MODAL ADD / EDIT ACT -->
<div class="modal fade" id="ACTmodal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Input Actual</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <input type="hidden" id="ACT_ID">

        <div class="mb-2">
          <label>Weight Part Actual</label>
          <input type="number" step="0.001" id="ACT_W" class="form-control">
        </div>

        <div class="mb-2">
          <label>Cavity</label>
          <input type="number" id="ACT_C" class="form-control">
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-success" id="ACTsaveBtn">Simpan</button>
      </div>

    </div>
  </div>
</div>

<script>
/**********************************************************
 * BAGIAN 2 — FILL FORM + NAVIGASI RECORD (TRIAL_CODE)
 **********************************************************/

let MIN_CODE = "";
let MAX_CODE = "";

/* === Ambil nilai MIN & MAX TRIAL_CODE === */
function loadMinMax() {
    $.get("load_record.php", {mode: "minmax"}, function(r){
        if (r.status === "ok") {
            MIN_CODE = r.min_code;
            MAX_CODE = r.max_code;
        }
    }, "json");
}
loadMinMax();

/* === Update tombol navigasi sesuai posisi === */
function updateNav(code) {
    $("#firstBtn, #prevBtn").prop("disabled", code == MIN_CODE);
    $("#nextBtn, #lastBtn").prop("disabled", code == MAX_CODE);
}

/**********************************************************
 * FILL FORM UTAMA TRIAL_PE
 **********************************************************/
function fillForm(rec) {

    $("#CURRENT_CODE").val(rec.TRIAL_CODE);
    $("#TRIAL_CODE").val(rec.TRIAL_CODE);

    $("input[name='DATE']").val(rec.DATE);
    $("input[name='PART_CODE']").val(rec.PART_CODE);
    $("#PART_NAME").val(rec.PART_NAME || "");
    $("#CUST_COMP").val(rec.CUST_COMP || "");
    $("#CUST_ID").val(rec.CUST_ID);
    $("#MAT_USING").val(rec.MAT_USING);
    $("#MAT_NAME").val(rec.MAT_NAME || "");

    $("input[name='QUANTITY_TRIAL']").val(rec.QUANTITY_TRIAL);
    $("input[name='TRIAL_REASON']").val(rec.TRIAL_REASON);
    $("input[name='TRIAL_TIMES']").val(rec.TRIAL_TIMES);
    $("input[name='MAT_DRYING_TIME']").val(rec.MAT_DRYING_TIME);
    $("input[name='MOLD_SET_UP']").val(rec.MOLD_SET_UP);
    $("input[name='MOLD_SET_DOWN']").val(rec.MOLD_SET_DOWN);
    $("input[name='TRIAL_DURATION']").val(rec.TRIAL_DURATION);
    $("input[name='CYCLE_TIME_ACT']").val(rec.CYCLE_TIME_ACT);

    $("textarea[name='QE_COMMENT']").val(rec.QE_COMMENT);
    $("textarea[name='PE_COMMENT']").val(rec.PE_COMMENT);

    $("input[name='PIC']").val(rec.PIC);
    $("input[name='WEIGHT_RUNNER']").val(rec.WEIGHT_RUNNER);
    $("input[name='PREPARED']").val(rec.PREPARED);
    $("input[name='CHECKED']").val(rec.CHECKED);
    $("input[name='APPROVED']").val(rec.APPROVED);

    $("input[name='QTY_OK']").val(rec.QTY_OK);
    $("input[name='QTY_NG']").val(rec.QTY_NG);
    $("input[name='MAC_NO']").val(rec.MAC_NO);

    $("select[name='JENIS_ID']").val(rec.JENIS_ID);
    $("input[name='TONAGE']").val(rec.TONAGE);

    $("textarea[name='CORRECTIVE_ACTION']").val(rec.CORRECTIVE_ACTION);
    $("textarea[name='ANALYSYS']").val(rec.ANALYSYS);
    $("select[name='JUDGE_ID']").val(rec.JUDGE_ID);

    updateNav(rec.TRIAL_CODE);

    // === STD berdasarkan PART CODE ===
    loadSTD(rec.PART_CODE);

    // === ACT GRID berdasarkan TRIAL CODE ===
    loadACTgrid(rec.TRIAL_CODE);
}

/**********************************************************
 * NAVIGASI BUTTON
 **********************************************************/

// LOAD LAST RECORD
$("#loadBtn").click(function(){
    $.get("load_record.php", {mode: "load"}, function(r){
        if (r.status === "ok") fillForm(r.record);
        else alert("Tidak ada data!");
    },"json");
});

// FIRST
$("#firstBtn").click(function(){
    $.get("load_record.php", {mode: "first"}, function(r){
        if (r.status === "ok") fillForm(r.record);
    },"json");
});

// LAST
$("#lastBtn").click(function(){
    $.get("load_record.php", {mode: "last"}, function(r){
        if (r.status === "ok") fillForm(r.record);
    },"json");
});

// NEXT
$("#nextBtn").click(function(){
    let code = $("#CURRENT_CODE").val() || 0;
    $.get("load_record.php", {mode: "next", code: code}, function(r){
        if (r.status === "ok") fillForm(r.record);
    },"json");
});

// PREVIOUS
$("#prevBtn").click(function(){
    let code = $("#CURRENT_CODE").val() || 0;
    $.get("load_record.php", {mode: "prev", code: code}, function(r){
        if (r.status === "ok") fillForm(r.record);
    },"json");
});


/**********************************************************
 * LOAD DATA STD (STANDARD PARAMETER)
 **********************************************************/
function loadSTD(part_code){
    if (!part_code) return;

    $.getJSON("get_std.php", {code: part_code}, function(std){

        $("#STD_WEIGHT_PART").val(std.WEIGHT_PART_STD || "");
        $("#STD_WEIGHT_RUNNER").val(std.WEIGHT_RUNNER_STD || "");
        $("#STD_CYCLE_TIME").val(std.CYCLE_TIME_STD || "");
        $("#STD_TONAGE").val(std.TONAGE_STD || "");
        $("#STD_CAVITY").val(std.CAVITY_STD || "");

    });
}


/**********************************************************
 * LOAD ACT GRID (MULTI ROW)
 **********************************************************/
function loadACTgrid(trial_code){
    $.getJSON("get_act_list.php", {trial: trial_code}, function(list){

        let tbody = $("#ACT_TABLE tbody");
        tbody.empty();

        if (list.length === 0) {
            tbody.append("<tr><td colspan='4' class='text-center text-muted'>Belum ada data Actual</td></tr>");
            return;
        }

        list.forEach((r, i) => {
            tbody.append(`
                <tr>
                    <td>${i+1}</td>
                    <td>${r.Weight_Part_Actual}</td>
                    <td>${r.CAVITY}</td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editACT(${r.ID}, ${r.Weight_Part_Actual}, ${r.CAVITY})">Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteACT(${r.ID})">Hapus</button>
                    </td>
                </tr>
            `);
        });

    });
}
/**********************************************************
 * AUTOCOMPLETE PART
 **********************************************************/
$("#PART_CODE").autocomplete({
    source: function(req, res){
        $.getJSON("search_part.php", {term: req.term}, function(data){
            res(data);
        });
    },
    minLength: 2,
    select: function(event, ui){

        $("#PART_CODE").val(ui.item.part_code);
        $("#PART_NAME").val(ui.item.part_name);
        $("#CUST_COMP").val(ui.item.cust_comp);
        $("#CUST_ID").val(ui.item.cust_id);
        $("#MAT_USING").val(ui.item.mat_code);
        $("#MAT_NAME").val(ui.item.mat_name);

        // Load STD berdasarkan part
        loadSTD(ui.item.part_code);

        return false;
    }
});


/**********************************************************
 * ACT GRID – ADD / EDIT / DELETE
 **********************************************************/

// ========== ADD NEW ACT ==========

$("#addACTbtn").click(function() {
    $("#ACT_ID").val(0);
    $("#ACT_W").val("");
    $("#ACT_C").val("");

    let modal = new bootstrap.Modal(document.getElementById('ACTmodal'));
    modal.show();
});


// ========== OPEN EDIT ACT ==========

function editACT(id, w, c){
    $("#ACT_ID").val(id);
    $("#ACT_W").val(w);
    $("#ACT_C").val(c);

    let modal = new bootstrap.Modal(document.getElementById('ACTmodal'));
    modal.show();
}


// ========== SAVE ACTUAL ==========
$("#ACTsaveBtn").click(function(){

    let id = $("#ACT_ID").val();
    let trial = $("#TRIAL_CODE").val();
    let w = $("#ACT_W").val();
    let c = $("#ACT_C").val();

    if (!trial) {
        alert("Trial Code belum ada!");
        return;
    }

    $.post("save_act.php", {
        id: id,
        trial: trial,
        weight: w,
        cavity: c
    }, function(r){

        if (r.status === "ok") {
            $("#ACTmodal").modal("hide");
            loadACTgrid(trial);
        } else {
            alert("Gagal menyimpan data ACT!");
        }

    }, "json");
});


// ========== DELETE ACT ==========
function deleteACT(id){

    if (!confirm("Hapus data actual ini?")) return;

    $.post("delete_act.php", {id: id}, function(r){

        if (r.status === "ok") {
            loadACTgrid($("#TRIAL_CODE").val());
        } else {
            alert("Gagal menghapus data!");
        }

    }, "json");
}


/**********************************************************
 * TOMBOL TAMBAH DATA BARU (NEW RECORD)
 **********************************************************/
$("#newBtn").click(function(){

    $.getJSON("load_record.php", {mode:"last"}, function(r){

        let newCode = 1;
        if (r.status === "ok") newCode = parseInt(r.record.TRIAL_CODE) + 1;

        // kosongkan seluruh form utama
        $("input[type='text'], input[type='number'], input[type='date']").val("");
        $("textarea").val("");
        $("select").val("");

        // kosongkan STD
        $("#STD_WEIGHT_PART").val("");
        $("#STD_WEIGHT_RUNNER").val("");
        $("#STD_CYCLE_TIME").val("");
        $("#STD_TONAGE").val("");
        $("#STD_CAVITY").val("");

        // kosongkan GRID ACT
        $("#ACT_TABLE tbody").html("<tr><td colspan='4' class='text-center text-muted'>Belum ada data Actual</td></tr>");

        // set kode trial baru
        $("#TRIAL_CODE").val(newCode);
        $("#CURRENT_CODE").val("");

        // disable navigasi
        $("#firstBtn, #prevBtn, #nextBtn, #lastBtn").prop("disabled", true);

        alert("Form siap untuk input data baru.");
    });
});


</script>
</body>
</html>
