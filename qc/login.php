<?php
// FILE: msii/qc/login.php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

define('LOGIN_PAGE', true);
$error = "";

// Jika tombol Login ditekan
if (isset($_POST['btnMasuk'])) {
    $temp_username = trim($_POST['username']);
    $temp_password = $_POST['password'];
    $selected_plant = $_POST['plant']; 
    $is_login_process = true;

    $conn = false;

    // --- LOGIKA PEMILIHAN SERVER ---
    if ($selected_plant == 'p1') {
        // PERBAIKAN: Suntikkan session sementara sebelum memanggil config P1
        $_SESSION['db_user'] = $temp_username;
        $_SESSION['db_pass'] = $temp_password;
        $_SESSION['active_plant'] = 'p1';

        $db_file = __DIR__ . '/../config/database_p1.php';
        if (file_exists($db_file)) {
            require_once $db_file; // Akan terhubung menggunakan session di atas
        } else {
            $error = "Config P1 tidak ditemukan!";
        }

        // Jika koneksi gagal (password memang salah), bersihkan session agar tidak nyangkut
        if (!$conn) {
            unset($_SESSION['db_user']);
            unset($_SESSION['db_pass']);
            unset($_SESSION['active_plant']);
        }
    } 
    elseif ($selected_plant == 'p2') {
        // PLANT 2: Koneksi Manual
        // !!! GANTI IP INI DENGAN IP SERVER PLANT 2 !!!
        $serverName = "192.168.0.9"; 
        
        $connectionOptions = array(
            "Database" => "msdata",
            "Uid" => $temp_username,
            "PWD" => $temp_password,
            "CharacterSet" => "UTF-8"
        );
        
        // Coba konek
        $conn = sqlsrv_connect($serverName, $connectionOptions);
    }

    // --- CEK HASIL KONEKSI ---
    if ($conn) {
        // LOGIN SUKSES
        $_SESSION['db_user'] = $temp_username;
        $_SESSION['db_pass'] = $temp_password;
        $_SESSION['active_plant'] = $selected_plant; 
        
        // Khusus P2, kita set parameter tambahan untuk database.php nanti
        if ($selected_plant == 'p2') {
            $_SESSION['erp_user'] = $temp_username;
            $_SESSION['erp_pass'] = $temp_password;
            $_SESSION['server_sql'] = "192.168.0.9"; // Simpan IP P2
        }

        // Redirect ke dashboard
        header("Location: dashboard_qc.php");
        exit();
    } else {
        // LOGIN GAGAL
        if (empty($error)) {
            $error = "Login Gagal. Cek Username, Password, atau Pilihan Plant.";
            if(extension_loaded('sqlsrv')) {
                $e = sqlsrv_errors();
                if($e) $error .= " (SQL: " . $e[0]['message'] . ")";
            }
        }
    }
}

// Jika sudah login, langsung ke dashboard
if (isset($_SESSION['db_user'])) {
    header("Location: dashboard_qc.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login QC System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #1f2a36 0%, #2c3e50 100%);
            height: 100vh; 
            display: flex; align-items: center; justify-content: center; 
            margin: 0; font-family: 'Segoe UI', sans-serif;
        }
        .card-login { 
            width: 100%; max-width: 400px; border: none; 
            border-radius: 10px; box-shadow: 0 15px 35px rgba(0,0,0,0.6); 
            overflow: hidden;
        }
        .card-header { 
            background: #2c3e50; color: white; text-align: center; padding: 30px 20px; 
            border-bottom: 5px solid #e74c3c; 
        }
        .btn-login { 
            background: #e74c3c; color: white; font-weight: 600; border: none; transition: 0.3s;
        }
        .btn-login:hover { background: #c0392b; color: white; }
        .form-control:focus { box-shadow: none; border-color: #e74c3c; }
        .input-group-text { border: none; background: #f8f9fa; }
    </style>
</head>
<body>

<div class="card card-login">
    <div class="card-header">
        <h3 class="mb-1 fw-bold"><i class="bi bi-shield-check text-danger"></i> QC SYSTEM</h3>
        <span class="badge bg-danger bg-opacity-25 text-danger border border-danger">KAKOTORA INTEGRATED</span>
    </div>
    <div class="card-body p-4 bg-white">
        <?php if($error): ?>
            <div class="alert alert-danger py-2 small shadow-sm border-0 mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            
            <div class="mb-3">
                <label class="form-label small fw-bold text-secondary">PILIH (PLANT)</label>
                <select name="plant" class="form-select bg-light border-0 fw-bold text-dark" required>
                    <option value="p1">🏢 PLANT 1 </option>
                    <option value="p2">🏭 PLANT 2 </option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold text-secondary">USERNAME</label>
                <div class="input-group shadow-sm">
                    <span class="input-group-text"><i class="bi bi-person text-secondary"></i></span>
                    <input type="text" name="username" class="form-control border-0 bg-light" placeholder="Masukkan Username" required autofocus>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label small fw-bold text-secondary">PASSWORD</label>
                <div class="input-group shadow-sm">
                    <span class="input-group-text"><i class="bi bi-key text-secondary"></i></span>
                    <input type="password" name="password" class="form-control border-0 bg-light" placeholder="Masukkan Password" required>
                </div>
            </div>

            <div class="d-grid mt-4 gap-2">
                <button type="submit" name="btnMasuk" class="btn btn-login py-2 shadow-sm">
                    MASUK SEKARANG <i class="bi bi-arrow-right-circle ms-2"></i>
                </button>
                <a href="/msii" class="btn btn-light border py-2 shadow-sm fw-bold text-secondary">
                    <i class="bi bi-house-door me-1"></i> KEMBALI KE MENU UTAMA
                </a>
            </div>
        </form>
    </div>
    <div class="card-footer text-center bg-white border-0 py-3 small text-muted">
        &copy; <?php echo date('Y'); ?> QC Department System
    </div>
</div>

</body>
</html>