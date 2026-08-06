<?php
// 1. Panggil file koneksi database SQL Server (biasanya file koneksi sudah mengaktifkan session_start())
require_once __DIR__ . '/../config/database_p1.php';

// Pastikan session aktif untuk membaca data user yang sedang login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inisialisasi variabel untuk pesan notifikasi
$message = '';
$status = '';

// --- DETEKSI PLANT BERDASARKAN USER / SESSION ---
// Kita cek apakah user yang aktif saat ini adalah 'plan1'
// (Menyesuaikan dengan kotak hijau 'plan1' di menu samping aplikasimu)
$currentUsername = isset($_SESSION['username']) ? strtolower($_SESSION['username']) : '';
$activePlant = isset($_SESSION['active_plant']) ? strtolower($_SESSION['active_plant']) : '';

// Kondisi Plant 1 aktif jika username mengandung kata 'plan1' atau session plant bernilai 'p1'
$isPlant1 = ($currentUsername === 'plan1' || $activePlant === 'p1' || strpos($currentUsername, 'plan1') !== false);


// 2. LOGIKA KETIKA TOMBOL "JALANKAN POSTING BULANAN" DITEKAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['posting_date'])) {
    
    // Perlindungan Ganda: Tolak proses jika ini adalah Plant 1
    if ($isPlant1) {
        $status = 'error';
        $message = "Akses Ditolak! Posting Bulanan tidak dapat dilakukan menggunakan akun Plant 1.";
    } else {
        // Ambil inputan dari form type="month" (format YYYY-MM)
        $inputDateStr = $_POST['posting_date'] . '-01'; 
        
        try {
            $selectedDate = new DateTime($inputDateStr);
            $spDateParam = $selectedDate->format('Y-m-d');
            
            $sqlPosting = "EXEC sp_posting_new ?";
            $stmtPosting = sqlsrv_query($conn, $sqlPosting, array($spDateParam));
            
            if ($stmtPosting) {
                $status = 'success';
                $bulanTahun = $selectedDate->format('F Y'); 
                $message = "Berhasil melakukan Posting Bulanan untuk periode <b>$bulanTahun</b>! Stok akhir telah di-balance.";
            } else {
                $status = 'error';
                $message = "Terjadi kesalahan saat mengeksekusi Posting Bulanan.<br>" . print_r(sqlsrv_errors(), true);
            }
        } catch (Exception $e) {
            $status = 'error';
            $message = "Format tanggal tidak valid: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Halaman Posting Bulanan</title>
    <style>
        body { font-family: "Arial", sans-serif; background-color: #f4f6f9; padding: 30px; }
        .container { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); max-width: 450px; margin: 0 auto; text-align: center; }
        h2 { margin-top: 0; color: #333; font-size: 20px; margin-bottom: 10px; }
        p.desc { font-size: 13px; color: #666; margin-bottom: 25px; line-height: 1.5; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; font-size: 13px; }
        .form-group input[type="month"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        .warning-text { color: #d9534f; font-weight: bold; display: block; margin-top: 15px; font-size: 12px;}
        
        .btn { background-color: #28a745; color: white; border: none; padding: 12px 15px; border-radius: 4px; cursor: pointer; font-size: 14px; width: 100%; font-weight: bold; transition: background-color 0.3s; }
        .btn:hover { background-color: #218838; }
        .btn:disabled { background-color: #cccccc; cursor: not-allowed; color: #666666; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-size: 13px; text-align: center; line-height: 1.5; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
    </style>
</head>
<body>

    <div class="container">
        <h2>Proses Posting Bulanan (Balance Stok)</h2>
        <p class="desc">
            Pilih bulan yang ingin Anda proses. Sistem akan menarik semua transaksi mutasi stok khusus pada bulan tersebut untuk menyeimbangkan saldo akhir.
        </p>

        <!-- --- PERINGATAN KHUSUS PLAN1 --- -->
        <?php if ($isPlant1): ?>
            <div class="alert alert-warning">
                <strong>Peringatan!</strong><br>
                Saat ini Anda login menggunakan akun <b>plan1</b>.<br><br>
                Untuk melakukan Posting Bulanan, harap login ulang menggunakan akun <b>Plant 2</b>.
            </div>
        <?php endif; ?>
        
        <!-- Area Pesan Notifikasi -->
        <?php if ($message !== ''): ?>
            <div class="alert <?php echo ($status === 'error') ? 'alert-error' : 'alert-success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Form Tombol Posting -->
        <form method="POST" action="">
            <div class="form-group">
                <label for="posting_date">Pilih Bulan Posting:</label>
                
                <!-- Input kalender dimatikan jika user adalah plan1 -->
                <input type="month" id="posting_date" name="posting_date" value="<?php echo date('Y-m'); ?>" required <?php echo $isPlant1 ? 'disabled' : ''; ?>>
            </div>
            
            <!-- Tombol dimatikan jika user adalah plan1 -->
            <button type="submit" class="btn" <?php echo $isPlant1 ? 'disabled' : "onclick=\"return confirm('Anda yakin ingin menjalankan Posting Bulanan untuk periode yang dipilih?');\""; ?>>
                Jalankan Posting Bulanan
            </button>
            
            <span class="warning-text">Catatan: Pastikan seluruh transaksi pada bulan tersebut sudah selesai diinput!</span>
        </form>
    </div>

</body>
</html>