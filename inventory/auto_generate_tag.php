<?php
require_once __DIR__ . '/../config/database_p1.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_generate_tag'])) {
    $dateFrom = $_POST['date_from'];
    $dateTo   = $_POST['date_to'];
    $locCode  = $_POST['loc_code'];
    $ittyCode = isset($_POST['itty_code']) ? $_POST['itty_code'] : '';

    try {
        set_time_limit(0);
        // Memanggil Stored Procedure dengan 4 Parameter
        $sql = "EXEC [dbo].[sp_auto_tag_belum_opname] ?, ?, ?, ?";
        $params = array($dateFrom, $dateTo, $locCode, $ittyCode);
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt) {
            $status = 'success';
            $jenisTxt = ($ittyCode == '') ? 'Semua Jenis Barang' : 'Jenis Barang ' . $ittyCode;
            $message = "Berhasil! Tag Opname otomatis dibuat untuk semua barang <b>BELUM OPNAME</b> di lokasi <b>$locCode</b> ($jenisTxt).";
            sqlsrv_free_stmt($stmt);
        } else {
            $status = 'error';
            $errors = sqlsrv_errors();
            $errorMsg = "";
            if ($errors != null) { foreach ($errors as $error) { $errorMsg .= $error['message'] . "<br>"; } }
            $message = "Gagal membuat Auto-Tag.<br>Detail Error: " . $errorMsg;
        }
    } catch (Exception $e) { $status = 'error'; $message = "Kesalahan Sistem: " . $e->getMessage(); }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Auto Generate Tag - Belum Opname</title>
    <style>
        body { font-family: "Arial", sans-serif; background-color: #f4f6f9; padding: 30px; }
        .container { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        h2 { margin-top: 0; color: #333; font-size: 19px; border-bottom: 1px solid #ddd; padding-bottom: 10px;}
        .desc { font-size: 13px; color: #555; margin-bottom: 20px;}
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { background-color: #17a2b8; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-size: 14px; width: 100%; font-weight: bold; margin-top: 10px;}
        .btn:hover { background-color: #138496; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-size: 13px; line-height: 1.5; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>

    <div class="container">
        <h2>Auto-Generate Tag (Belum Opname)</h2>
        <p class="desc">Sistem akan mencari barang berstatus "BELUM OPNAME" dan otomatis membuatkan Tag baru (7 Digit).</p>
        
        <?php if ($message !== ''): ?>
            <div class="alert <?php echo ($status === 'error') ? 'alert-error' : 'alert-success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" onsubmit="return confirm('Anda yakin ingin meng-generate Tag fisik otomatis?');">
            <div class="form-group">
                <label>Tanggal Stok Awal:</label>
                <input type="date" name="date_from" required value="<?php echo date('Y-m-d', strtotime('first day of last month')); ?>">
            </div>
            <div class="form-group">
                <label>Tanggal Stok Akhir (Tanggal Opname):</label>
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
            
            <button type="submit" name="btn_generate_tag" class="btn">Buat Tag Otomatis</button>
        </form>
    </div>

</body>
</html>