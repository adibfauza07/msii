<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "auth.php";
require_once "../config/database_p2.php";

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

/* =========================
   KHUSUS ADMIN
========================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";

/* =========================
   LOOKUP DEPARTMENT DARI TABLE DEPT
========================= */
$sql_dept = "
SELECT
    DEP_ID,
    DEP_CODE,
    DEP_NAME
FROM DEPT
ORDER BY DEP_NAME ASC
";

$q_dept = sqlsrv_query($conn, $sql_dept);

if ($q_dept === false) {
    die(print_r(sqlsrv_errors(), true));
}

$departments = array();

while ($d = sqlsrv_fetch_array($q_dept, SQLSRV_FETCH_ASSOC)) {
    $departments[] = array(
        'id'   => $d['DEP_ID'],
        'code' => rtrim($d['DEP_CODE']),
        'name' => rtrim($d['DEP_NAME'])
    );
}

/* =========================
   SIMPAN / UPDATE USER
========================= */
if (isset($_POST['simpan'])) {

    $id           = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $username     = trim($_POST['username']);
    $password     = trim($_POST['password']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $department   = trim($_POST['department']);
    $role         = trim($_POST['role']);
    $status_user  = trim($_POST['status_user']);

    if (
        $username == "" ||
        $nama_lengkap == "" ||
        $department == "" ||
        $role == "" ||
        $status_user == ""
    ) {
        $error = "Data wajib diisi lengkap.";
    } else {

        if ($id > 0) {

            if ($password != "") {
                $sql = "
                UPDATE dbo.it_users
                SET
                    username = ?,
                    password = ?,
                    nama_lengkap = ?,
                    department = ?,
                    role = ?,
                    status_user = ?
                WHERE id = ?
                ";

                $params = array(
                    $username,
                    $password,
                    $nama_lengkap,
                    $department,
                    $role,
                    $status_user,
                    $id
                );
            } else {
                $sql = "
                UPDATE dbo.it_users
                SET
                    username = ?,
                    nama_lengkap = ?,
                    department = ?,
                    role = ?,
                    status_user = ?
                WHERE id = ?
                ";

                $params = array(
                    $username,
                    $nama_lengkap,
                    $department,
                    $role,
                    $status_user,
                    $id
                );
            }

        } else {

            if ($password == "") {
                $error = "Password wajib diisi.";
            } else {
                $sql = "
                INSERT INTO dbo.it_users
                (
                    username,
                    password,
                    nama_lengkap,
                    department,
                    role,
                    status_user
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?
                )
                ";

                $params = array(
                    $username,
                    $password,
                    $nama_lengkap,
                    $department,
                    $role,
                    $status_user
                );
            }
        }

        if ($error == "") {
            $q = sqlsrv_query($conn, $sql, $params);

            if ($q === false) {
                $error = print_r(sqlsrv_errors(), true);
            } else {
                header("Location: user.php?success=1");
                exit();
            }
        }
    }
}

/* =========================
   HAPUS USER
========================= */
if (isset($_GET['hapus'])) {

    $hapus_id = intval($_GET['hapus']);

    if ($hapus_id == $_SESSION['user_id']) {
        header("Location: user.php?error=self_delete");
        exit();
    }

    $sql = "
    DELETE FROM dbo.it_users
    WHERE id = ?
    ";

    $q = sqlsrv_query($conn, $sql, array($hapus_id));

    if ($q === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    header("Location: user.php?success=delete");
    exit();
}

/* =========================
   EDIT USER
========================= */
$edit = array();

if (isset($_GET['edit'])) {

    $edit_id = intval($_GET['edit']);

    $sql = "
    SELECT *
    FROM dbo.it_users
    WHERE id = ?
    ";

    $q_edit = sqlsrv_query($conn, $sql, array($edit_id));

    if ($q_edit === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $edit = sqlsrv_fetch_array($q_edit, SQLSRV_FETCH_ASSOC);
}

/* =========================
   LIST USER
========================= */
$sql_user = "
SELECT *
FROM dbo.it_users
ORDER BY id DESC
";

$q_user = sqlsrv_query($conn, $sql_user);

if ($q_user === false) {
    die(print_r(sqlsrv_errors(), true));
}

if (isset($_GET['success'])) {
    $success = "Data user berhasil diproses.";
}

if (isset($_GET['error']) && $_GET['error'] == "self_delete") {
    $error = "User login tidak boleh dihapus.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Management User</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
      rel="stylesheet">

<style>
body {
    background: #eef2f7;
    font-family: Arial;
}

.card {
    border-radius: 10px;
}
</style>
</head>

<body>

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">
        <h2>Management User</h2>

        <a href="dashboard.php" class="btn btn-secondary">
            Kembali Dashboard
        </a>
    </div>

    <?php if ($error != "") { ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php } ?>

    <?php if ($success != "") { ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php } ?>

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">
            <?php echo !empty($edit) ? "Edit User" : "Tambah User"; ?>
        </div>

        <div class="card-body">

            <form method="POST">

                <input type="hidden"
                       name="id"
                       value="<?php echo isset($edit['id']) ? $edit['id'] : ''; ?>">

                <div class="row mb-3">

                    <div class="col-md-4">
                        <label>Username</label>
                        <input type="text"
                               name="username"
                               class="form-control"
                               required
                               value="<?php echo isset($edit['username']) ? htmlspecialchars($edit['username']) : ''; ?>">
                    </div>

                    <div class="col-md-4">
                        <label>Password</label>
                        <input type="text"
                               name="password"
                               class="form-control"
                               <?php echo empty($edit) ? 'required' : ''; ?>
                               placeholder="<?php echo !empty($edit) ? 'Kosongkan jika tidak diganti' : ''; ?>">
                    </div>

                    <div class="col-md-4">
                        <label>Nama Lengkap</label>
                        <input type="text"
                               name="nama_lengkap"
                               class="form-control"
                               required
                               value="<?php echo isset($edit['nama_lengkap']) ? htmlspecialchars($edit['nama_lengkap']) : ''; ?>">
                    </div>

                </div>

                <div class="row mb-3">

                    <div class="col-md-4">
                        <label>Department</label>

                        <select name="department"
                                class="form-control"
                                required>

                            <option value="">-- Pilih Department --</option>

                            <?php foreach ($departments as $dept) { ?>

                                <option value="<?php echo htmlspecialchars($dept['name']); ?>"
                                    <?php
                                    echo (
                                        isset($edit['department']) &&
                                        trim($edit['department']) == trim($dept['name'])
                                    )
                                    ? 'selected'
                                    : '';
                                    ?>>

                                    <?php echo htmlspecialchars($dept['code'] . ' - ' . $dept['name']); ?>

                                </option>

                            <?php } ?>

                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>Role</label>

                        <select name="role"
                                class="form-control"
                                required>

                            <option value="user"
                                <?php echo (isset($edit['role']) && $edit['role'] == 'user') ? 'selected' : ''; ?>>
                                user
                            </option>

                            <option value="admin"
                                <?php echo (isset($edit['role']) && $edit['role'] == 'admin') ? 'selected' : ''; ?>>
                                admin
                            </option>

                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>Status</label>

                        <select name="status_user"
                                class="form-control"
                                required>

                            <option value="active"
                                <?php echo (isset($edit['status_user']) && $edit['status_user'] == 'active') ? 'selected' : ''; ?>>
                                active
                            </option>

                            <option value="inactive"
                                <?php echo (isset($edit['status_user']) && $edit['status_user'] == 'inactive') ? 'selected' : ''; ?>>
                                inactive
                            </option>

                        </select>
                    </div>

                </div>

                <button type="submit"
                        name="simpan"
                        class="btn btn-primary">

                    <?php echo !empty($edit) ? "Update User" : "Simpan User"; ?>

                </button>

                <?php if (!empty($edit)) { ?>
                    <a href="user.php" class="btn btn-secondary">
                        Batal
                    </a>
                <?php } ?>

            </form>

        </div>
    </div>

    <div class="card">

        <div class="card-header">
            Data User
        </div>

        <div class="card-body">

            <table class="table table-bordered table-striped">

                <thead>
                    <tr>
                        <th>No</th>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php
                $no = 1;

                while ($row = sqlsrv_fetch_array($q_user, SQLSRV_FETCH_ASSOC)) {
                ?>
                    <tr>
                        <td><?php echo $no++; ?></td>

                        <td><?php echo htmlspecialchars($row['username']); ?></td>

                        <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>

                        <td><?php echo htmlspecialchars($row['department']); ?></td>

                        <td><?php echo htmlspecialchars($row['role']); ?></td>

                        <td><?php echo htmlspecialchars($row['status_user']); ?></td>

                        <td>
                            <?php
                            if (
                                isset($row['created_at']) &&
                                $row['created_at'] instanceof DateTime
                            ) {
                                echo $row['created_at']->format('Y-m-d H:i');
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>

                        <td>
                            <a href="user.php?edit=<?php echo $row['id']; ?>"
                               class="btn btn-warning btn-sm">
                                Edit
                            </a>

                            <a href="user.php?hapus=<?php echo $row['id']; ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Yakin hapus user ini?')">
                                Hapus
                            </a>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>

            </table>

        </div>
    </div>

</div>

</body>
</html>