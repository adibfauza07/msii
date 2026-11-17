<?php
require_once __DIR__ . '/../config/database_p1.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_adjust'])) {
    $dateFrom = $_POST['date_from'];
    $dateTo   = $_POST['date_to'];
    $locCode  = $_POST['loc_code'];
    $ittyCode = isset($_POST['itty_code']) ? $_POST['itty_code'] : '';

    try {
        set_time_limit(0);
        
        // Memanggil Stored Procedure dengan 4 Parameter
        $sql = "EXEC [dbo].[sp_proses_adjustment_otomatis] ?, ?, ?, ?";
        $params = array($dateFrom, $dateTo, $locCode, $ittyCode);
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt) {
            $status = 'success';
            $jenisTxt = ($ittyCode == '') ? 'Semua Jenis Barang' : 'Jenis Barang ' . htmlspecialchars($ittyCode);
            $message = "Berhasil! Data Penyesuaian (Adjustment 61 In & 62 Out) untuk lokasi <b>$locCode</b> ($jenisTxt) periode $dateFrom s/d $dateTo telah ter-generate ke dalam mutasi harian.<br><br><i>Langkah selanjutnya: Silakan ke menu <b>Posting</b> untuk memposting bulan terkait agar saldo akhir (BAL) ter-update permanen.</i>";
            sqlsrv_free_stmt($stmt);
        } else {
            $status = 'error';
            $errors = sqlsrv_errors();
            $errorMsg = "";
            if ($errors != null) { 
                foreach ($errors as $error) { 
                    $errorMsg .= $error['message'] . "<br>"; 
                } 
            }
            $message = "Gagal memproses Adjustment.<br>Detail Error: " . $errorMsg;
        }
    } catch (Exception $e) { 
        $status = 'error'; 
        $message = "Kesalahan Sistem: " . $e->getMessage(); 
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Auto Adjustment Stok</title>
    <style>
        body { font-family: "Arial", sans-serif; background-color: #f4f6f9; padding: 30px; }
        .container { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        h2 { margin-top: 0; color: #333; font-size: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px;}
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { background-color: #007bff; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-size: 14px; width: 100%; font-weight: bold; margin-top: 10px;}
        .btn:hover { background-color: #0056b3; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-size: 13px; line-height: 1.5; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>

    <div class="container">
        <h2>Penyesuaian Stok (Auto Adjustment)</h2>
        <p style="font-size: 13px; color: #555;">Sistem akan mencari seluruh selisih (minus/plus) antara Opname Awal dan Akhir, lalu membuat dokumen mutasi otomatis <b>(Kode 61 & 62)</b>.</p>
        
        <?php if ($message !== ''): ?>
            <div class="alert <?php echo ($status === 'error') ? 'alert-error' : 'alert-success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" onsubmit="return confirm('Anda yakin ingin meng-generate dokumen Adjustment (61/62) secara otomatis? Pastikan Stock Opname sudah final.');">
            <div class="form-group">
                <label>Tanggal Stok Awal:</label>
                <input type="date" name="date_from" required value="<?php echo date('Y-m-d', strtotime('first day of last month')); ?>">
            </div>
            <div class="form-group">
                <label>Tanggal Stok Akhir (Tgl Tarik Laporan):</label>
                <input type="date" name="date_to" required value="<?php echo date('Y-m-d', strtotime('first day of this month')); ?>">
            </div>
            
            <div class="form-group">
                <label>Pilih Lokasi Gudang:</label>
                <select name="loc_code" required>
                    <option value="">-- Pilih Lokasi --</option>
                    <?php
                    $sqlLoc = "SELECT LOC_ID, LOC_CODE, LOC_NAME FROM dbo.LOC ORDER BY LOC_CODE ASC";
                    $stmtLoc = sqlsrv_query($conn, $sqlLoc);
                    if ($stmtLoc) {
                        while ($rowLoc = sqlsrv_fetch_array($stmtLoc, SQLSRV_FETCH_ASSOC)) {
                            echo '<option value="' . htmlspecialchars($rowLoc['LOC_CODE']) . '">' . htmlspecialchars($rowLoc['LOC_CODE']) . ' - ' . htmlspecialchars($rowLoc['LOC_NAME']) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Pilih Jenis Barang (ITTY):</label>
                <select name="itty_code">
                    <option value="">-- Semua Jenis Barang --</option>
                    <?php
                    $sqlItty = "SELECT ITTY_CODE, ITTY_DESC FROM dbo.ITTY ORDER BY ITTY_CODE ASC";
                    $stmtItty = sqlsrv_query($conn, $sqlItty);
                    if ($stmtItty) {
                        while ($rowItty = sqlsrv_fetch_array($stmtItty, SQLSRV_FETCH_ASSOC)) {
                            echo '<option value="' . htmlspecialchars($rowItty['ITTY_CODE']) . '">' . htmlspecialchars($rowItty['ITTY_CODE']) . ' - ' . htmlspecialchars($rowItty['ITTY_DESC']) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            
            <button type="submit" name="btn_adjust" class="btn">Proses Auto Adjustment</button>
        </form>
    </div>

</body>
</html>