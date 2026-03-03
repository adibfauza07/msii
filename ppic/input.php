<?php
// SECURITY PROTECT
require_once "../middleware/Auth.php";       // wajib login
require_once "../middleware/RoleCheck.php";  // cek hak akses
only(['p2']);                           // hanya plant 1 & 2 bisa akses

// Koneksi Database sesuai server PLAN1/PLAN2
require_once "../config/database.php";

// ambil PART dari SQL Server
$sql = "SELECT id, part_code, part_no, part_name, qty_polibag, qty_box 
        FROM data_barcode_showa 
        ORDER BY part_no ASC";

$data = sqlsrv_query($conn, $sql);
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../assets/bootstrap.min.css">
<link rel="stylesheet" href="../assets/select2.min.css">
<script src="../assets/jquery.min.js"></script>
<script src="../assets/select2.min.js"></script>
</head>
<body class="p-4">

<!-- BACK BUTTON -->
<a href="dashboard_ppic.php" class="btn btn-secondary" style="margin-bottom:15px;">
    ← Kembali ke Menu 
</a>    

<h3>Input Barcode Showa</h3>

<form action="save.php" method="post">

    <label>Pilih Part No</label>
    <select name="part_id" id="part_id" class="form-control" required>
        <option value="">-- Pilih Part No --</option>
        <?php while($r = sqlsrv_fetch_array($data, SQLSRV_FETCH_ASSOC)) { ?>
            <option 
                value="<?= $r['id'] ?>"
                data-code="<?= $r['part_code'] ?>"
                data-no="<?= $r['part_no'] ?>"
                data-name="<?= $r['part_name'] ?>"
                data-qty_pb="<?= $r['qty_polibag'] ?>"
                data-qty_bx="<?= $r['qty_box'] ?>"
            >
                <?= $r['part_no'] ?> - <?= $r['part_name'] ?>
            </option>
        <?php } ?>
    </select>

    <br>

    <label>Jenis Label</label>
    <select id="jenis" name="jenis" class="form-control" required>
        <option value="polibag">Polibag</option>
        <option value="box">Box</option>
    </select>

    <br>

    <label>Part Code</label>
    <input type="text" name="part_code" id="part_code" class="form-control" readonly>

    <label>Part No</label>
    <input type="text" name="part_no" id="part_no" class="form-control" readonly>

    <label>Part Name / Description</label>
    <input type="text" name="part_name" id="part_name" class="form-control" readonly>

    <label>Qty (pcs)</label>
    <input type="number" name="qty" id="qty" class="form-control" required>

    <label>Lot Produksi</label>
    <input type="text" name="lot" class="form-control">

    <label>Tanggal</label>
    <input type="text" name="tanggal" class="form-control" placeholder="misal 2025-02-01">

    <br>
    <button class="btn btn-primary">Simpan & Generate Label</button>

</form>

<script>
$(document).ready(function(){
    $("#part_id").select2();

    function updateQty() {
        let opt = $("#part_id").find(":selected");
        let jenis = $("#jenis").val();

        if (!opt.val()) return;

        if (jenis === "polibag") {
            $("#qty").val(opt.data("qty_pb"));
        } else {
            $("#qty").val(opt.data("qty_bx"));
        }
    }

    $("#part_id").change(function(){
        let opt = $(this).find(":selected");

        $("#part_code").val(opt.data("code"));
        $("#part_no").val(opt.data("no"));
        $("#part_name").val(opt.data("name"));

        updateQty();
    });

    $("#jenis").change(function(){
        updateQty();
    });
});
</script>

</body>
</html>
