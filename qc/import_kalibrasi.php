<?php
/**
 * Script Import Master Alat Ukur Dinamis dengan Grup
 * PHP 5.4 & SQL Server 2008 (sqlsrv)
 */
require_once __DIR__ . '/../config/database.php';

if (isset($_POST['import']) && isset($_FILES['file_csv'])) {
    
    // Mulai Database Transaction untuk keamanan rollback jika gagal
    if (sqlsrv_begin_transaction($conn) === false) {
         die("Gagal memulai transaksi: " . print_r(sqlsrv_errors(), true));
    }

    $file = fopen($_FILES['file_csv']['tmp_name'], 'r');
    $currentGroup = 'Umum'; // Grup default jika baris pertama langsung data
    $success = true;

    // Membaca file CSV baris demi baris (Efisien memori untuk PHP 5.4)
    while (($data = fgetcsv($file, 10000, ",")) !== FALSE) {
        
        $kolomNo       = trim($data[0]); // Kolom A di Excel (NO)
        $kolomNamaAlat = trim($data[1]); // Kolom B di Excel (NAMA ALAT)
        $kolomSeri     = isset($data[2]) ? trim($data[2]) : ''; // Kolom C di Excel
        
        // 1. LOGIKA PENDETEKSI GRUP (State Tracker)
        // Jika kolom NO kosong, tetapi kolom NAMA ALAT ada isinya (seperti "01. DIGIMATIC CALIPER")
        if (empty($kolomNo) && !empty($kolomNamaAlat)) {
            // Kita gunakan Regex (Regular Expression) untuk menghapus awalan angka dan titik (misal "01. ")
            $currentGroup = preg_replace('/^[0-9]+\.\s*/', '', $kolomNamaAlat);
            continue; // Skip baris ini karena ini cuma judul grup, lanjut baca ke baris data di bawahnya
        }

        // 2. VALIDASI DATA RIIL
        // Jika kolom NO kosong atau formatnya bukan angka/titik (misal: "1.1"), lewati baris tersebut
        if (empty($kolomNo) || strpos($kolomNo, '.') === false) {
            continue; 
        }

        // 3. PARAMETERIZED QUERY (Keamanan Prioritas SQL Server 2008)
        $tsql = "INSERT INTO MasterAlatUkur 
                 (NoUrut, GrupAlat, NamaAlat, SeriNo, SeriNoIMC, Model, RangeUkur, Resolution, Brand, TipeKalibrasi) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        // Mengambil data kolom lain (Pastikan index array sesuai urutan kolom CSV Anda)
        $params = array(
            $kolomNo,
            $currentGroup,                     // Value grup dari pelacakan state di atas
            $kolomNamaAlat,
            $kolomSeri,                        // Seri No
            isset($data[3]) ? trim($data[3]) : '', // Seri No IMC
            isset($data[4]) ? trim($data[4]) : '', // Model
            isset($data[5]) ? trim($data[5]) : '', // Range
            isset($data[9]) ? trim($data[9]) : '', // Resolution (sesuaikan indexnya)
            isset($data[14]) ? trim($data[14]) : '', // Brand (sesuaikan indexnya)
            'External'                         // Hardcode Tipe Kalibrasi
        );

        $stmt = sqlsrv_query($conn, $tsql, $params);

        if ($stmt === false) {
            $success = false;
            echo "Error pada data {$kolomNo}: " . print_r(sqlsrv_errors(), true) . "<br>";
            break; 
        }
    }
    
    fclose($file);

    // 4. PENYIMPANAN AKHIR
    if ($success) {
        sqlsrv_commit($conn);
        echo "<script>alert('Semua data beserta Grup Alat berhasil di-import!'); window.location.href='jadwal_kalibrasi.php';</script>";
    } else {
        sqlsrv_rollback($conn);
        echo "<script>alert('Terjadi kesalahan. Data dikembalikan (Rollback).');</script>";
    }
}
?>

<!-- HTML User Interface (Standar Klasik) -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Import Master Data - ERP</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #ecf0f1; padding: 40px; }
        .upload-box { background: #fff; padding: 25px; border-radius: 5px; max-width: 500px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-upload { margin-top: 15px; padding: 10px 20px; background: #27ae60; color: #fff; border: none; border-radius: 3px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="upload-box">
        <h3>Import Master Alat Ukur (CSV)</h3>
        <p style="font-size: 13px; color: #7f8c8d;">Catatan: Sistem akan otomatis membaca baris seperti "01. DIGIMATIC CALIPER" dan menjadikannya sebagai Grup Alat.</p>
        
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="file" name="file_csv" accept=".csv" required style="width: 100%; padding: 10px; border: 1px dashed #ccc;">
            <button type="submit" name="import" class="btn-upload">Mulai Import Data</button>
        </form>
    </div>
</body>
</html>