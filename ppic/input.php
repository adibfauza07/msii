<?php
if (session_id() == "") {
    session_start();
}

/*
    INPUT BARCODE SHOWA
    Generate label tetap pakai save.php lama.
    Perbaikan ini hanya untuk dropdown/list part agar data baru dari data_barcode_showa muncul.
*/

require_once dirname(__DIR__) . "/config/db_plant2.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

/*
    Ambil PART dari SQL Server.
    Sumber list: data_barcode_showa
*/
$sql = "
    SELECT
        id,
        ISNULL(part_code, '') AS part_code,
        ISNULL(part_no, '') AS part_no,
        ISNULL(part_name, '') AS part_name,
        ISNULL(qty_polibag, 0) AS qty_polibag,
        ISNULL(qty_box, 0) AS qty_box
    FROM dbo.data_barcode_showa
    WHERE
        ISNULL(part_code, '') <> ''
        OR ISNULL(part_no, '') <> ''
        OR ISNULL(part_name, '') <> ''
    ORDER BY
        part_no ASC,
        part_code ASC,
        part_name ASC
";

$data = sqlsrv_query($conn, $sql);

if ($data === false) {
    die("<pre>Query data_barcode_showa gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$partList = array();

while ($r = sqlsrv_fetch_array($data, SQLSRV_FETCH_ASSOC)) {
    $partList[] = array(
        "id"          => isset($r["id"]) ? (int)$r["id"] : 0,
        "part_code"   => trim((string)$r["part_code"]),
        "part_no"     => trim((string)$r["part_no"]),
        "part_name"   => trim((string)$r["part_name"]),
        "qty_polibag" => isset($r["qty_polibag"]) ? (int)$r["qty_polibag"] : 0,
        "qty_box"     => isset($r["qty_box"]) ? (int)$r["qty_box"] : 0
    );
}

$totalPart = count($partList);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Input Barcode Showa</title>

    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/select2.min.css">

    <script src="../assets/jquery.min.js"></script>
    <script src="../assets/select2.min.js"></script>

    <style>
        body {
            background-color: #f4f7f6;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
        }

        .form-card {
            max-width: 900px;
            margin: 30px auto;
            background: #ffffff;
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
            color: #333333;
            font-weight: 700;
        }

        .part-count {
            margin-top: 6px;
            font-size: 12px;
            color: #555555;
        }

        .form-group-row {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .form-group-row label {
            width: 220px;
            margin-bottom: 0;
            font-weight: 600;
            color: #495057;
        }

        .form-group-row .input-container {
            flex: 1;
        }

        .select2-container {
            width: 100% !important;
        }

        .select2-container--default .select2-selection--single {
            height: 38px;
            padding: 5px;
            border: 1px solid #ced4da;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 24px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }

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

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 18px;
        }
    </style>
</head>

<body>

<div class="container">

    <div class="form-card">
        <div class="mb-3">
            <a href="dashboard_home.php" class="btn btn-primary btn-sm shadow-sm border-1 border-secondary">
                &larr; Kembali ke Menu
            </a>
        </div>

        <div class="form-header">
            <h3>Input Barcode Showa</h3>
            <div class="part-count">
                Total master part terbaca:
                <b><?php echo h($totalPart); ?></b>
                item
            </div>
        </div>

        <?php if ($totalPart == 0) { ?>
            <div class="warning-box">
                Data master Showa belum terbaca dari table <b>data_barcode_showa</b>.
                Cek apakah data sudah tersimpan di table tersebut.
            </div>
        <?php } ?>

        <!-- GENERATE TETAP PAKAI SAVE.PHP LAMA -->
        <form action="save.php" method="post" autocomplete="off">

            <div class="form-group-row">
                <label>Pilih Part No</label>
                <div class="input-container">
                    <select name="part_id" id="part_id" class="form-control" required>
                        <option value="">-- Pilih Part No --</option>

                        <?php for ($i = 0; $i < count($partList); $i++) { ?>
                            <?php $r = $partList[$i]; ?>
                            <option
                                value="<?php echo h($r["id"]); ?>"
                                data-code="<?php echo h($r["part_code"]); ?>"
                                data-no="<?php echo h($r["part_no"]); ?>"
                                data-name="<?php echo h($r["part_name"]); ?>"
                                data-qty-pb="<?php echo h($r["qty_polibag"]); ?>"
                                data-qty-bx="<?php echo h($r["qty_box"]); ?>"
                            >
                                <?php echo h($r["part_no"]); ?> - <?php echo h($r["part_name"]); ?>
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
                Simpan &amp; Generate Label
            </button>

        </form>
    </div>
</div>

<script>
$(document).ready(function () {
    var $part = $("#part_id");
    var $jenis = $("#jenis");

    $part.select2({
        width: "100%",
        placeholder: "-- Pilih Part No --",
        allowClear: true
    });

    function getSelectedOption() {
        return $part.find(":selected");
    }

    function getAttr($opt, name) {
        var value = $opt.attr(name);

        if (typeof value === "undefined" || value === null) {
            return "";
        }

        return value;
    }

    function clearPart() {
        $("#part_code").val("");
        $("#part_no").val("");
        $("#part_name").val("");
        $("#qty").val("");
    }

    function updateQty() {
        var $opt = getSelectedOption();
        var jenis = $jenis.val();

        if (!$opt.val()) {
            $("#qty").val("");
            return;
        }

        if (jenis === "polibag") {
            $("#qty").val(getAttr($opt, "data-qty-pb"));
        } else {
            $("#qty").val(getAttr($opt, "data-qty-bx"));
        }
    }

    function updatePartInfo() {
        var $opt = getSelectedOption();

        if (!$opt.val()) {
            clearPart();
            return;
        }

        $("#part_code").val(getAttr($opt, "data-code"));
        $("#part_no").val(getAttr($opt, "data-no"));
        $("#part_name").val(getAttr($opt, "data-name"));

        updateQty();
    }

    $part.on("change", function () {
        updatePartInfo();
    });

    $jenis.on("change", function () {
        updateQty();
    });

    /*
        Tanggal default hari ini.
    */
    if ($("#tanggal").val() == "") {
        var today = new Date();
        var yyyy = today.getFullYear();
        var mm = String(today.getMonth() + 1);
        var dd = String(today.getDate());

        if (mm.length < 2) {
            mm = "0" + mm;
        }

        if (dd.length < 2) {
            dd = "0" + dd;
        }

        $("#tanggal").val(yyyy + "-" + mm + "-" + dd);
    }
});
</script>

</body>
</html>