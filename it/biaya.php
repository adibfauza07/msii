<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "auth.php";
require_once "../config/database_p2.php";

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

$project_id = isset($_GET['project_id'])
    ? intval($_GET['project_id'])
    : 0;

if ($project_id <= 0) {
    die("Project belum dipilih.");
}

/* =========================
   DATA PROJECT
========================= */
$sqlProject = "SELECT *
               FROM it_projects
               WHERE id = ?";

$qProject = sqlsrv_query(
    $conn,
    $sqlProject,
    array($project_id)
);

$project = sqlsrv_fetch_array(
    $qProject,
    SQLSRV_FETCH_ASSOC
);

if (!$project) {
    die("Project tidak ditemukan.");
}

/* =========================
   SIMPAN / UPDATE
========================= */
if (isset($_POST['simpan'])) {

    $id = isset($_POST['id'])
        ? $_POST['id']
        : '';

    $tanggal = $_POST['tanggal'];
    $kategori = $_POST['kategori'];
    $keterangan = $_POST['keterangan'];
    $nominal = $_POST['nominal'];

    if ($id == '') {

        $sql = "INSERT INTO it_project_costs
                (
                    project_id,
                    tanggal,
                    kategori,
                    keterangan,
                    nominal
                )
                VALUES
                (
                    ?, ?, ?, ?, ?
                )";

        $params = array(
            $project_id,
            $tanggal,
            $kategori,
            $keterangan,
            $nominal
        );

    } else {

        $sql = "UPDATE it_project_costs SET
                    tanggal = ?,
                    kategori = ?,
                    keterangan = ?,
                    nominal = ?
                WHERE id = ?
                AND project_id = ?";

        $params = array(
            $tanggal,
            $kategori,
            $keterangan,
            $nominal,
            $id,
            $project_id
        );
    }

    $q = sqlsrv_query(
        $conn,
        $sql,
        $params
    );

    if ($q === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    header("Location: biaya.php?project_id=" . $project_id);
    exit();
}

/* =========================
   HAPUS
========================= */
if (isset($_GET['hapus'])) {

    $id = intval($_GET['hapus']);

    $sql = "DELETE FROM it_project_costs
            WHERE id = ?
            AND project_id = ?";

    $q = sqlsrv_query(
        $conn,
        $sql,
        array($id, $project_id)
    );

    if ($q === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    header("Location: biaya.php?project_id=" . $project_id);
    exit();
}

/* =========================
   EDIT
========================= */
$edit = array();

if (isset($_GET['edit'])) {

    $id = intval($_GET['edit']);

    $sql = "SELECT *
            FROM it_project_costs
            WHERE id = ?
            AND project_id = ?";

    $qEdit = sqlsrv_query(
        $conn,
        $sql,
        array($id, $project_id)
    );

    $edit = sqlsrv_fetch_array(
        $qEdit,
        SQLSRV_FETCH_ASSOC
    );
}

/* =========================
   LIST BIAYA
========================= */
$sql = "SELECT *
        FROM it_project_costs
        WHERE project_id = ?
        ORDER BY tanggal DESC, id DESC";

$query = sqlsrv_query(
    $conn,
    $sql,
    array($project_id)
);

if ($query === false) {
    die(print_r(sqlsrv_errors(), true));
}

/* =========================
   TOTAL BIAYA
========================= */
$sqlTotal = "SELECT
                ISNULL(SUM(nominal),0)
                AS total_biaya
             FROM it_project_costs
             WHERE project_id = ?";

$qTotal = sqlsrv_query(
    $conn,
    $sqlTotal,
    array($project_id)
);

$total = sqlsrv_fetch_array(
    $qTotal,
    SQLSRV_FETCH_ASSOC
);

$total_biaya = $total['total_biaya'];

$budget_project = $project['budget'];

$sisa_budget = $budget_project - $total_biaya;

/* =========================
   PERSEN BUDGET
========================= */
$persen_biaya = 0;

if ($budget_project > 0) {

    $persen_biaya =
        ($total_biaya / $budget_project) * 100;
}
?>

<!DOCTYPE html>
<html>

<head>

    <title>Budget & Biaya Project</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between mb-3">

        <div>

            <h3>Budget & Biaya Project</h3>

            <strong>Project:</strong>
            <?php echo $project['nama_software']; ?>

            <br>

            <strong>Department:</strong>
            <?php echo $project['department']; ?>

        </div>

        <div>

            <a href="project.php"
               class="btn btn-secondary">

                Kembali

            </a>

        </div>

    </div>

    <!-- SUMMARY -->
    <div class="row mb-3">

        <div class="col-md-4">

            <div class="alert alert-primary">

                <strong>Budget Project</strong>

                <br>

                Rp <?php echo number_format(
                    $budget_project,
                    0,
                    ',',
                    '.'
                ); ?>

            </div>

        </div>

        <div class="col-md-4">

            <div class="alert alert-danger">

                <strong>Total Biaya</strong>

                <br>

                Rp <?php echo number_format(
                    $total_biaya,
                    0,
                    ',',
                    '.'
                ); ?>

            </div>

        </div>

        <div class="col-md-4">

            <div class="alert <?php
                echo ($sisa_budget < 0)
                    ? 'alert-warning'
                    : 'alert-success';
            ?>">

                <strong>Sisa Budget</strong>

                <br>

                Rp <?php echo number_format(
                    $sisa_budget,
                    0,
                    ',',
                    '.'
                ); ?>

                <?php if ($sisa_budget < 0) { ?>

                    <br>

                    <small>
                        Budget Minus
                    </small>

                <?php } ?>

            </div>

        </div>

    </div>

    <!-- PROGRESS BAR -->
    <div class="card p-3 mb-4">

        <strong>Penggunaan Budget</strong>

        <div class="progress mt-2">

            <div class="progress-bar bg-danger"
                 style="width: <?php echo $persen_biaya; ?>%">

                <?php echo round(
                    $persen_biaya,
                    1
                ); ?>%

            </div>

        </div>

    </div>

    <!-- FORM -->
    <div class="card mb-4">

        <div class="card-header">

            <?php
            echo !empty($edit)
                ? "Edit Biaya"
                : "Tambah Biaya";
            ?>

        </div>

        <div class="card-body">

            <form method="POST">

                <input type="hidden"
                       name="id"
                       value="<?php
                       echo isset($edit['id'])
                            ? $edit['id']
                            : '';
                       ?>">

                <div class="row mb-3">

                    <!-- TANGGAL -->
                    <div class="col-md-3">

                        <label>Tanggal</label>

                        <input type="date"
                               name="tanggal"
                               class="form-control"
                               required
                               value="<?php

                               if (
                                   isset($edit['tanggal']) &&
                                   $edit['tanggal']
                               ) {

                                   echo $edit['tanggal']
                                        ->format('Y-m-d');

                               } else {

                                   echo date('Y-m-d');
                               }

                               ?>">

                    </div>

                    <!-- KATEGORI -->
                    <div class="col-md-3">

                        <label>Kategori</label>

                        <input type="text"
                               name="kategori"
                               class="form-control"
                               list="kategori_list"
                               required
                               placeholder="Pilih / ketik kategori"
                               value="<?php

                               echo isset($edit['kategori'])
                                    ? $edit['kategori']
                                    : '';

                               ?>">

                        <datalist id="kategori_list">

                            <option value="Software">
                            <option value="Hardware">
                            <option value="Maintenance">
                            <option value="Renewal">
                            <option value="License">
                            <option value="Antivirus">
                            <option value="Internet">
                            <option value="Printer">
                            <option value="CCTV">
                            <option value="Network">
                            <option value="Office Equipment">
                            <option value="Operational">
                            <option value="Training">
                            <option value="Repair">
                            <option value="Support">
                            <option value="Upgrade">
                            <option value="Project">
                            <option value="Consumable">
                            <option value="Sparepart">
                            <option value="Lain-lain">

                        </datalist>

                    </div>

                    <!-- NOMINAL -->
                    <div class="col-md-3">

                        <label>Nominal</label>

                        <input type="number"
                               name="nominal"
                               class="form-control"
                               required
                               value="<?php

                               echo isset($edit['nominal'])
                                    ? $edit['nominal']
                                    : '';

                               ?>">

                    </div>

                    <!-- BUTTON -->
                    <div class="col-md-3">

                        <label>&nbsp;</label>

                        <br>

                        <button type="submit"
                                name="simpan"
                                class="btn btn-primary">

                            <?php
                            echo !empty($edit)
                                ? "Update"
                                : "Simpan";
                            ?>

                        </button>

                        <?php if (!empty($edit)) { ?>

                            <a href="biaya.php?project_id=<?php echo $project_id; ?>"
                               class="btn btn-warning">

                                Batal

                            </a>

                        <?php } ?>

                    </div>

                </div>

                <!-- KETERANGAN -->
                <div class="mb-3">

                    <label>Keterangan</label>

                    <textarea name="keterangan"
                              class="form-control"
                              rows="3"><?php

                        echo isset($edit['keterangan'])
                            ? $edit['keterangan']
                            : '';

                    ?></textarea>

                </div>

            </form>

        </div>

    </div>

    <!-- TABLE -->
    <table class="table table-bordered table-striped bg-white">

        <thead>

            <tr>

                <th>No</th>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Keterangan</th>
                <th>Nominal</th>
                <th width="150">Aksi</th>

            </tr>

        </thead>

        <tbody>

        <?php

        $no = 1;

        while (
            $row = sqlsrv_fetch_array(
                $query,
                SQLSRV_FETCH_ASSOC
            )
        ) {

        ?>

            <tr>

                <td>
                    <?php echo $no++; ?>
                </td>

                <td>

                    <?php

                    if ($row['tanggal']) {

                        echo $row['tanggal']
                             ->format('Y-m-d');
                    }

                    ?>

                </td>

                <td>
                    <?php echo $row['kategori']; ?>
                </td>

                <td>
                    <?php echo $row['keterangan']; ?>
                </td>

                <td>

                    Rp <?php echo number_format(
                        $row['nominal'],
                        0,
                        ',',
                        '.'
                    ); ?>

                </td>

                <td>

                    <a href="biaya.php?project_id=<?php
                        echo $project_id;
                    ?>&edit=<?php
                        echo $row['id'];
                    ?>"
                       class="btn btn-sm btn-warning">

                        Edit

                    </a>

                    <a href="biaya.php?project_id=<?php
                        echo $project_id;
                    ?>&hapus=<?php
                        echo $row['id'];
                    ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Hapus biaya ini?')">

                        Hapus

                    </a>

                </td>

            </tr>

        <?php } ?>

        </tbody>

    </table>

</div>

</body>
</html>