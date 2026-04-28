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
    <title>Input Barcode Showa</title>
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/select2.min.css">
    <script src="../assets/jquery.min.js"></script>
    <script src="../assets/select2.min.js"></script>
    
    <style>
        body {
            background-color: #f4f7f6;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }
        .form-card {
            max-width: 900px;
            margin: 30px auto;
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        .form-header {
            border-bottom: 2px solid #007bff;
            margin-bottom: 30px;
            padding-bottom: 15px;
        }
        .form-header h3 {
            margin: 0;
            color: #333;
            font-weight: 700;
        }
        /* Penataan Label dan Input agar Sejajar (Presisi) */
        .form-group-row {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .form-group-row label {
            width: 220px; /* Lebar label dikunci agar input sejajar vertikal */
            margin-bottom: 0;
            font-weight: 600;
            color: #495057;
        }
        .form-group-row .input-container {
            flex: 1;
        }
        /* Styling khusus Select2 agar tingginya pas */
        .select2-container--default .select2-selection--single {
            height: 38px;
            padding: 5px;
            border: 1px solid #ced4da;
        }
        /* Input Readonly */
        .form-control[readonly] {
            background-color: #e9ecef;
            opacity: 1;
        }
        .btn-submit {
            height: 50px;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="container">
      
<div class="form-card">
<div class="mb-3">
    <a href="dashboard_ppic.php" class="btn btn-primary btn-sm shadow-sm border-1 border-secondary">
        <i class="fa fa-arrow-left"></i> ← Kembali ke Menu
    </a>
</div>
    <div class="form-header">
        <h3>Input Barcode Showa</h3>
        </div>

        <form action="save.php" method="post">

            <div class="form-group-row">
                <label>Pilih Part No</label>
                <div class="input-container">
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
                </div>
            </div>

            <div class="form-group-row">
                <label>Jenis Label</label>
                <div class="input-container">
                    <select id="jenis" name="jenis" class="form-control" required>
                        <option value="polibag">Polibag</option>
                        <option value="box">Box</option>
                    </select>
                </div>
            </div>

            <div class="form-group-row">
                <label>Part Code</label>
                <div class="input-container">
                    <input type="text" name="part_code" id="part_code" class="form-control" readonly>
                </div>
            </div>

            <div class="form-group-row">
                <label>Part No</label>
                <div class="input-container">
                    <input type="text" name="part_no" id="part_no" class="form-control" readonly>
                </div>
            </div>

            <div class="form-group-row">
                <label>Part Name / Description</label>
                <div class="input-container">
                    <input type="text" name="part_name" id="part_name" class="form-control" required>
                </div>
            </div>

            <div class="form-group-row">
                <label>Qty (pcs)</label>
                <div class="input-container">
                    <input type="number" name="qty" id="qty" class="form-control" required>
                </div>
            </div>

            <div class="form-group-row">
                <label>Lot Produksi</label>
                <div class="input-container">
                    <input type="text" name="lot" class="form-control" placeholder="Input nomor lot (opsional)...">
                </div>
            </div>

            <div class="form-group-row">
                <label>Tanggal</label>
                <div class="input-container">
                    <input type="date" name="tanggal" id="tanggal" class="form-control">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-submit">
                Simpan & Generate Label
            </button>

        </form>
    </div>
</div>

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