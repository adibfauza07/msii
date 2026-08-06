<?php
// 1. Panggil file koneksi database SQL Server
require_once __DIR__ . '/../config/database_p1.php';

// Inisialisasi variabel
$message = '';
$status = '';
$lastSopDate = null;
$lastSopMonthYearStr = '';
$historyData = []; 

// 2. MENGAMBIL TANGGAL TERAKHIR ADJUSTING
$sqlSop = "SELECT TOP 1 SOP_SDATE, SOP_FINISHED FROM SOP WHERE SOP_FINISHED = 'T' ORDER BY SOP_SDATE DESC";
$stmtSop = sqlsrv_query($conn, $sqlSop);

if ($stmtSop && $rowSop = sqlsrv_fetch_array($stmtSop, SQLSRV_FETCH_ASSOC)) {
    if ($rowSop['SOP_SDATE'] instanceof DateTime) {
        $lastSopDate = $rowSop['SOP_SDATE'];
    } else {
        $lastSopDate = new DateTime($rowSop['SOP_SDATE']);
    }
    $lastSopMonthYearStr = $lastSopDate->format('m / Y');
}

// 3. LOGIKA KETIKA TOMBOL "JALANKAN REPOSTING" DITEKAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['repost_date'])) {
    
    $inputDateStr = $_POST['repost_date']; 
    $selectedDate = new DateTime($inputDateStr);
    
    // VALIDASI: Pastikan user memilih Tanggal 1 di kalender
    if ($selectedDate->format('d') !== '01') {
        $status = 'error';
        $message = "<strong>Proses Ditolak!</strong><br>Anda harus memilih <strong>Tanggal 1</strong> pada kalender untuk melakukan Reposting.";
    } else {
        if ($lastSopDate) {
            // Validasi kecocokan Bulan dan Tahun
            if ($selectedDate->format('Y-m') !== $lastSopDate->format('Y-m')) {
                $status = 'error';
                $message = "Bulan/Tahun Reposting tidak sama dengan Adjusting terakhir.<br>Tanggal terakhir Adjusting : " . $lastSopMonthYearStr;
            } else {
                
                // EKSEKUSI STORED PROCEDURE
                $spDateParam = $selectedDate->format('j-M-Y'); 
                $sqlRepost = "SET NOCOUNT ON; EXEC reposting ?";
                
                // --- PERBAIKAN UTAMA ---
                set_time_limit(0); 
                ini_set('memory_limit', '1024M'); 
                
                // Menyuruh PHP mengabaikan pesan PRINT (Informasi SQLSTATE 01000) dari SQL Server
                sqlsrv_configure('WarningsReturnAsErrors', 0); 
                
                $options = array("QueryTimeout" => 0); 
                
                $stmtRepost = sqlsrv_query($conn, $sqlRepost, array($spDateParam), $options);
                
                if ($stmtRepost) {
                    $status = 'success';
                    $message = "Berhasil melakukan Reposting untuk periode " . $selectedDate->format('m / Y') . "!";
                    
                    // PENARIKAN DATA RIWAYAT OTOMATIS
                    // Abaikan output PRINT dari Stored Procedure, langsung tarik dari tabel TRANS
                    $periodMonth = $selectedDate->format('m');
                    $periodYear = $selectedDate->format('Y');
                    
                    $sqlHistory = "SELECT TOP 100 i.ITEM_CODE, t.TRAN_DOC, t.TRAN_DATE AS REPOST_DATE
                                   FROM TRANS t
                                   INNER JOIN INV_TRAN_VIEW v ON t.TRAN_ID = v.TRAN_ID
                                   INNER JOIN ITEMS i ON v.ITEM_ID = i.ITEM_ID
                                   WHERE MONTH(t.TRAN_DATE) = ? AND YEAR(t.TRAN_DATE) = ?
                                   ORDER BY t.TRAN_DATE DESC";
                                   
                    $stmtHistory = sqlsrv_query($conn, $sqlHistory, array($periodMonth, $periodYear));
                    
                    if ($stmtHistory !== false) {
                        while ($row = sqlsrv_fetch_array($stmtHistory, SQLSRV_FETCH_ASSOC)) {
                            $historyData[] = $row;
                        }
                    }

                } else {
                    $status = 'error';
                    $message = "Terjadi kesalahan saat mengeksekusi Reposting.<br>" . print_r(sqlsrv_errors(), true);
                }
            }
        } else {
            $status = 'error';
            $message = "Data Adjusting terakhir (SOP_SDATE) tidak ditemukan di database.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Halaman Reposting</title>
    <style>
        body { font-family: "Arial", sans-serif; background-color: #f4f6f9; padding: 20px; color: #333; }
        .wrapper { max-width: 800px; margin: 0 auto; }
        
        .container { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 30px; }
        h2 { margin-top: 0; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; font-size: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; font-size: 13px; }
        .form-group input[type="date"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        
        .btn { background-color: #007bff; color: white; border: none; padding: 12px 15px; border-radius: 4px; cursor: pointer; font-size: 14px; width: 100%; font-weight: bold; }
        .btn:hover { background-color: #0056b3; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-size: 13px; text-align: center; line-height: 1.5; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        .history-container { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .history-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        .history-table th, .history-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .history-table th { background-color: #f8f9fa; font-weight: bold; text-align: center; }
        .history-table tr:nth-child(even) { background-color: #f9f9f9; }
        .history-table tr:hover { background-color: #f1f1f1; }
        .text-center { text-align: center !important; }
    </style>
</head>
<body>

    <div class="wrapper">
        <div class="container">
            <h2>Proses Reposting</h2>

            <?php if ($message !== ''): ?>
                <div class="alert <?php echo ($status === 'error') ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="repost_date">Pilih Tanggal Reposting (Wajib Tanggal 1):</label>
                    <input type="date" id="repost_date" name="repost_date" required>
                </div>
                
                <button type="submit" class="btn" onclick="this.innerHTML='Sedang Memproses... Mohon Tunggu';">Jalankan Reposting</button>
            </form>
        </div>

        <div class="history-container">
            <h2>Riwayat Dokumen Terproses</h2>
            <table class="history-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th style="width: 25%;">Item Code</th>
                        <th style="width: 40%;">Nomor Dokumen (Tran Doc)</th>
                        <th style="width: 30%;">Tanggal Reposting</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historyData)): ?>
                        <tr>
                            <td colspan="4" class="text-center">Silakan jalankan Reposting untuk melihat daftar dokumen yang diproses.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($historyData as $log): 
                            $logDate = '';
                            if (isset($log['REPOST_DATE'])) {
                                if ($log['REPOST_DATE'] instanceof DateTime) {
                                    $logDate = $log['REPOST_DATE']->format('d-M-Y H:i:s');
                                } else {
                                    $logDate = date('d-M-Y H:i:s', strtotime($log['REPOST_DATE']));
                                }
                            } else {
                                $logDate = date('d-M-Y H:i:s'); 
                            }
                            
                            $itemCode = isset($log['ITEM_CODE']) ? $log['ITEM_CODE'] : '-';
                            $tranDoc = isset($log['TRAN_DOC']) ? $log['TRAN_DOC'] : '-';
                        ?>
                            <tr>
                                <td class="text-center"><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($itemCode); ?></td>
                                <td><?php echo htmlspecialchars($tranDoc); ?></td>
                                <td class="text-center"><?php echo $logDate; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>