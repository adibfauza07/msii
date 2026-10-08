<?php
// user_management.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/database.php';
if (file_exists(__DIR__ . '/config/log_helper.php')) {
    require_once __DIR__ . '/config/log_helper.php';
}

$editData = null;

// =========================================================
// 1. PROSES HAPUS USER (DELETE)
// =========================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delId = (int)$_GET['id'];

    // Ambil info username sebelum dihapus
    $stmtFind = sqlsrv_query($conn, "SELECT USERNAME FROM dbo.APP_USERS WHERE USER_ID = ?", array($delId));
    if ($stmtFind && $rFind = sqlsrv_fetch_array($stmtFind, SQLSRV_FETCH_ASSOC)) {
        $delUser = trim($rFind['USERNAME']);

        // Cegah admin utama terhapus sendiri
        if (strtolower($delUser) === 'admin') {
            echo "<script>alert('User admin utama tidak boleh dihapus!'); window.location.href='index.php?page=user_management';</script>";
            exit;
        }

        $stmtDel = sqlsrv_query($conn, "DELETE FROM dbo.APP_USERS WHERE USER_ID = ?", array($delId));
        if ($stmtDel) {
            if (function_exists('write_user_log')) {
                write_user_log($conn, 'HAPUS', 'User Management', $delUser, 'Menghapus akun user: ' . $delUser);
            }
            echo "<script>alert('User berhasil dihapus!'); window.location.href='index.php?page=user_management';</script>";
            exit;
        }
    }
}

// =========================================================
// 2. AMBIL DATA JIKA MODE EDIT
// =========================================================
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editId = (int)$_GET['id'];
    $stmtEdit = sqlsrv_query($conn, "SELECT * FROM dbo.APP_USERS WHERE USER_ID = ?", array($editId));
    if ($stmtEdit && $rEdit = sqlsrv_fetch_array($stmtEdit, SQLSRV_FETCH_ASSOC)) {
        $editData = $rEdit;
    }
}

// =========================================================
// 3. PROSES SIMPAN / UPDATE (CREATE & UPDATE)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $userId   = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $fullname = trim($_POST['fullname']);
    $menus    = isset($_POST['menus']) ? implode(',', $_POST['menus']) : '';

    if ($username !== '') {
        // MODE UPDATE (EDIT)
        if ($userId > 0) {
            if ($password !== '') {
                // Update dengan password baru
                $sqlUp = "UPDATE dbo.APP_USERS SET USERNAME = ?, PASSWORD = ?, FULL_NAME = ?, ALLOWED_MENUS = ? WHERE USER_ID = ?";
                $paramsUp = array($username, $password, $fullname, $menus, $userId);
            } else {
                // Password dikosongkan = tidak diubah
                $sqlUp = "UPDATE dbo.APP_USERS SET USERNAME = ?, FULL_NAME = ?, ALLOWED_MENUS = ? WHERE USER_ID = ?";
                $paramsUp = array($username, $fullname, $menus, $userId);
            }

            $stmtUp = sqlsrv_query($conn, $sqlUp, $paramsUp);
            if ($stmtUp) {
                if (function_exists('write_user_log')) {
                    write_user_log($conn, 'EDIT', 'User Management', $username, 'Memperbarui data dan hak akses user: ' . $username);
                }
                echo "<script>alert('Data user berhasil diperbarui!'); window.location.href='index.php?page=user_management';</script>";
                exit;
            } else {
                echo "<script>alert('Gagal memperbarui user.');</script>";
            }
        } 
        // MODE CREATE (TAMBAH BARU)
        else {
            if ($password === '') {
                echo "<script>alert('Password wajib diisi untuk user baru!');</script>";
            } else {
                $sqlIns = "INSERT INTO dbo.APP_USERS (USERNAME, PASSWORD, FULL_NAME, ALLOWED_MENUS) VALUES (?, ?, ?, ?)";
                $stmtIns = sqlsrv_query($conn, $sqlIns, array($username, $password, $fullname, $menus));
                if ($stmtIns) {
                    if (function_exists('write_user_log')) {
                        write_user_log($conn, 'INPUT', 'User Management', $username, 'Menambahkan user baru: ' . $username);
                    }
                    echo "<script>alert('User baru berhasil ditambahkan!'); window.location.href='index.php?page=user_management';</script>";
                    exit;
                } else {
                    echo "<script>alert('Gagal menambah user. Username mungkin sudah digunakan.');</script>";
                }
            }
        }
    }
}

// 4. AMBIL DAFTAR USER DARI DATABASE
$users = array();
$stmtU = sqlsrv_query($conn, "SELECT USER_ID, USERNAME, FULL_NAME, ALLOWED_MENUS FROM dbo.APP_USERS ORDER BY USER_ID ASC");
if ($stmtU) {
    while ($r = sqlsrv_fetch_array($stmtU, SQLSRV_FETCH_ASSOC)) {
        $users[] = $r;
    }
    sqlsrv_free_stmt($stmtU);
}

// Persiapan array menu yang dicentang saat form edit
$activeMenus = array();
if ($editData && !empty($editData['ALLOWED_MENUS'])) {
    $activeMenus = explode(',', $editData['ALLOWED_MENUS']);
}
?>

<div class="row">
    <!-- FORM TAMBAH / EDIT USER -->
    <div class="col-md-5">
        <div class="box <?php echo $editData ? 'box-warning' : 'box-primary'; ?>">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa <?php echo $editData ? 'fa-edit' : 'fa-user-plus'; ?>"></i> 
                    <?php echo $editData ? 'Edit User & Hak Akses' : 'Tambah User & Hak Akses'; ?>
                </h3>
                <?php if ($editData): ?>
                    <a href="index.php?page=user_management" class="btn btn-default btn-xs pull-right"><i class="fa fa-times"></i> Batal Edit</a>
                <?php endif; ?>
            </div>
            <form role="form" method="POST" action="index.php?page=user_management">
                <input type="hidden" name="user_id" value="<?php echo $editData ? (int)$editData['USER_ID'] : 0; ?>">
                
                <div class="box-body">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required autocomplete="off" 
                               value="<?php echo $editData ? htmlspecialchars($editData['USERNAME']) : ''; ?>"
                               <?php echo ($editData && strtolower($editData['USERNAME']) === 'admin') ? 'readonly' : ''; ?>>
                    </div>

                    <div class="form-group">
                        <label>Password <?php echo $editData ? '<small class="text-muted">(Kosongkan jika tidak ingin mengubah)</small>' : ''; ?></label>
                        <input type="password" name="password" class="form-control" <?php echo $editData ? '' : 'required'; ?> placeholder="<?php echo $editData ? 'Ketik password baru...' : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="fullname" class="form-control" 
                               value="<?php echo $editData ? htmlspecialchars($editData['FULL_NAME']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Hak Akses Menu:</label><br>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="menus[]" value="master" <?php echo in_array('master', $activeMenus) ? 'checked' : ''; ?>> Master
                        </label>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="menus[]" value="ordering" <?php echo in_array('ordering', $activeMenus) ? 'checked' : ''; ?>> Ordering
                        </label>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="menus[]" value="purchasing" <?php echo in_array('purchasing', $activeMenus) ? 'checked' : ''; ?>> Purchasing
                        </label><br>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="menus[]" value="inventory" <?php echo in_array('inventory', $activeMenus) ? 'checked' : ''; ?>> Inventory Trans
                        </label>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="menus[]" value="proses" <?php echo in_array('proses', $activeMenus) ? 'checked' : ''; ?>> Proses (Prod)
                        </label><br>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="menus[]" value="accounting" <?php echo in_array('accounting', $activeMenus) ? 'checked' : ''; ?>> Accounting
                        </label>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="menus[]" value="laporan" <?php echo in_array('laporan', $activeMenus) ? 'checked' : ''; ?>> Laporan Bea Cukai
                        </label>
                    </div>
                </div>

                <div class="box-footer">
                    <button type="submit" name="save_user" class="btn <?php echo $editData ? 'btn-warning' : 'btn-primary'; ?> btn-sm">
                        <i class="fa fa-save"></i> <?php echo $editData ? 'Update User' : 'Simpan User'; ?>
                    </button>
                    <?php if ($editData): ?>
                        <a href="index.php?page=user_management" class="btn btn-default btn-sm">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- TABEL DAFTAR PENGGUNA SISTEM -->
    <div class="col-md-7">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-users"></i> Daftar Pengguna Sistem</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped table-hover" style="font-size: 12px;">
                    <thead>
                        <tr style="background-color: #f4f4f4;">
                            <th style="width: 40px;" class="text-center">No</th>
                            <th style="width: 100px;">Username</th>
                            <th>Nama Lengkap</th>
                            <th>Menu Diizinkan</th>
                            <th style="width: 110px;" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted" style="padding: 15px;">Belum ada user terdaftar.</td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($users as $u): ?>
                            <tr>
                                <td class="text-center"><?php echo $no++; ?></td>
                                <td><strong><i class="fa fa-user"></i> <?php echo htmlspecialchars($u['USERNAME']); ?></strong></td>
                                <td><?php echo htmlspecialchars($u['FULL_NAME']); ?></td>
                                <td><code><?php echo htmlspecialchars($u['ALLOWED_MENUS']); ?></code></td>
                                <td class="text-center">
                                    <!-- Tombol Edit -->
                                    <a href="index.php?page=user_management&action=edit&id=<?php echo (int)$u['USER_ID']; ?>" 
                                       class="btn btn-warning btn-xs" title="Edit User">
                                        <i class="fa fa-pencil"></i>
                                    </a>

                                    <!-- Tombol Hapus (admin utama diproteksi) -->
                                    <?php if (strtolower(trim($u['USERNAME'])) !== 'admin'): ?>
                                        <a href="index.php?page=user_management&action=delete&id=<?php echo (int)$u['USER_ID']; ?>" 
                                            class="btn btn-danger btn-xs" 
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus user: <?php echo htmlspecialchars($u['USERNAME']); ?>?');" 
                                            title="Hapus User">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-default btn-xs" disabled title="Admin utama tidak bisa dihapus"><i class="fa fa-trash text-muted"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>