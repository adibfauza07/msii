<<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "auth.php";
require_once "../config/database_p2.php";

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

$sql = "SELECT * FROM DEPT ORDER BY DEP_NAME ASC";

$query = sqlsrv_query($conn, $sql);

if ($query === false) {
    die(print_r(sqlsrv_errors(), true));
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <title>Department</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">

        <h3>Data Department</h3>

        <div>

            <a href="dashboard.php" class="btn btn-secondary">
                Dashboard
            </a>

            <a href="tambah_department.php" class="btn btn-primary">
                Tambah Department
            </a>

        </div>

    </div>

    <table class="table table-bordered table-striped bg-white">

        <thead>

            <tr>
                <th width="60">No</th>
                <th width="120">Kode</th>
                <th>Nama Department</th>
                <th width="80">Level</th>
                <th width="180">Aksi</th>
            </tr>

        </thead>

        <tbody>

        <?php

        $no = 1;

        while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {

        ?>

            <tr>

                <td><?php echo $no++; ?></td>

                <td>
                    <?php echo rtrim($row['DEP_CODE']); ?>
                </td>

                <td>
                    <?php echo rtrim($row['DEP_NAME']); ?>
                </td>

                <td>
                    <?php echo $row['DEP_LEVEL']; ?>
                </td>

                <td>

                    <a href="edit_department.php?id=<?php echo $row['DEP_ID']; ?>"
                       class="btn btn-sm btn-primary">

                        Edit

                    </a>

                    <a href="hapus_department.php?id=<?php echo $row['DEP_ID']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Yakin hapus department ini?')">

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