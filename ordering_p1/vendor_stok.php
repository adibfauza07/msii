<?php
// Pastikan file koneksi menggunakan driver sqlsrv ($conn)
// Mengarah ke database msData sesuai USE [msData]
require_once __DIR__ . "/../config/database_ordering.php";

// ==============================================================================
// 1. BACK-END LOGIC (API HANDLER) PHP 5.4
// ==============================================================================
if (isset($_REQUEST['action'])) {
    header("Content-Type: application/json");
    $action = $_REQUEST['action'];
    
    // Pengecekan koneksi
    if ($conn === false) {
        echo json_encode(array("success" => false, "message" => "Koneksi database gagal.", "errors" => sqlsrv_errors()));
        exit();
    }

// Aksi Import CSV
    if ($action === 'import_csv') {
        if (!isset($_FILES['csv_file']['tmp_name']) || empty($_FILES['csv_file']['tmp_name'])) {
            echo json_encode(array("success" => false, "message" => "File CSV tidak ditemukan."));
            exit();
        }

        // FIX 1: Paksa PHP mengenali line endings dari berbagai OS (Windows/Mac/Linux)
        ini_set('auto_detect_line_endings', TRUE);

        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");
        
        if ($handle !== FALSE) {
            // FIX 2: Deteksi delimiter otomatis (Koma atau Titik Koma)
            $firstLine = fgets($handle);
            $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';
            
            // Kembalikan pointer file ke baris pertama setelah menggunakan fgets
            rewind($handle); 
            
            // Lewati baris pertama (Header Excel)
            fgetcsv($handle, 1000, $delimiter); 
            
            // Persiapkan Query (Parameterized Query untuk SQL Server 2008)
            $sql = "INSERT INTO [dbo].[stock_upload] (item_epson, stock) VALUES (?, ?)";
            
            $item_epson = '';
            $stock = 0;
            
            // Binding parameter (wajib menggunakan referensi & untuk PHP 5.4 sqlsrv)
            $stmt = sqlsrv_prepare($conn, $sql, array(&$item_epson, &$stock));
            
            if ($stmt === false) {
                echo json_encode(array("success" => false, "message" => "Gagal menyiapkan statement.", "errors" => sqlsrv_errors()));
                exit();
            }

            $successCount = 0;
            $previewData = array(); 

            // Looping isi CSV dengan delimiter yang sudah terdeteksi
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                
                // Pastikan baris memiliki minimal kolom F (Index 5)
                if (count($data) >= 6) {
                    
                    // Ambil Kolom C (Index 2) dan Kolom F (Index 5)
                    $raw_item = trim($data[2]);
                    $item_epson = substr($raw_item, 0, 9); // Pengamanan Truncation SQL Server
                    
                    // Hilangkan karakter non-numerik jika ada format angka di Excel (misal: "1.000")
                    $stock = (int) preg_replace('/[^0-9-]/', '', trim($data[5])); 
                    
                    if ($item_epson !== '') {
                        if (sqlsrv_execute($stmt)) {
                            $successCount++;
                            
                            // Sanitasi XSS untuk dikembalikan ke UI
                            $previewData[] = array(
                                "item_epson" => htmlspecialchars($item_epson, ENT_QUOTES, 'UTF-8'),
                                "stock"      => htmlspecialchars((string)$stock, ENT_QUOTES, 'UTF-8')
                            );
                        }
                    }
                }
            }
            fclose($handle);
            
            echo json_encode(array(
                "success" => true, 
                "message" => "Import Selesai. $successCount baris berhasil dimasukkan ke tabel stock_upload.",
                "data"    => $previewData
            ));
        } else {
            echo json_encode(array("success" => false, "message" => "Gagal membuka file CSV."));
        }
        exit();
    }

    }
?>

<!-- ============================================================================== -->
<!-- 2. FRONT-END INTERFACE -->
<!-- ============================================================================== -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Upload Vendor Stok (C & F)</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f9; padding: 20px; font-size: 13px; }
        .container { background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 800px; margin: auto; }
        h3 { margin-top: 0; color: #333; border-bottom: 2px solid #ccc; padding-bottom: 10px; }
        
        .upload-box { border: 2px solid #0056b3; padding: 15px; border-radius: 5px; background: #fdfdfd; margin-bottom: 20px; }
        input[type="file"] { padding: 5px; }
        button { padding: 8px 15px; background: #0056b3; color: white; border: none; border-radius: 3px; cursor: pointer; font-weight: bold; }
        button:hover { background: #004494; }
        button:disabled { background: #999; cursor: not-allowed; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #0056b3; color: white; position: sticky; top: 0; }
        .grid-container { max-height: 400px; overflow-y: auto; border: 1px solid #ccc; background: #fff;}
        
        #alertBox { padding: 10px; margin-bottom: 15px; display: none; font-weight: bold; border-radius: 3px; }
        .success { background: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .error { background: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
    </style>
    <!-- Kompabilitas jQuery lama -->
    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
</head>
<body>

<div class="container">
    <h3>Modul Import Stok Vendor</h3>
    <div id="alertBox"></div>

    <div class="upload-box">
        <label style="font-weight: bold; margin-right: 15px;">Pilih File CSV:</label>
        <input type="file" id="csv_file" accept=".csv">
        <button type="button" id="btnUpload">Upload & Simpan ke stock_upload</button>
    </div>

    <h4 style="margin-bottom: 5px;">Data View (Preview Hasil Simpan)</h4>
    <div class="grid-container">
        <table id="gridPreview">
            <thead>
                <tr>
                    <th style="width: 50px; text-align: center;">No</th>
                    <th>Item Epson (Kolom C)</th>
                    <th>Vendor Stock (Kolom F)</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="3" style="text-align:center;">Pilih file CSV dan klik Upload untuk memproses.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    function showMessage(type, text) {
        $('#alertBox').removeClass('success error').addClass(type).text(text).fadeIn();
        setTimeout(function() { $('#alertBox').fadeOut(); }, 6000);
    }

    $('#btnUpload').click(function() {
        var fileInput = $('#csv_file')[0];
        if (fileInput.files.length === 0) {
            showMessage('error', 'Pilih file CSV terlebih dahulu.');
            return;
        }

        // Siapkan objek FormData untuk mengirim file via AJAX
        var formData = new FormData();
        formData.append('action', 'import_csv');
        formData.append('csv_file', fileInput.files[0]);

        var btn = $(this);
        btn.prop('disabled', true).text('Sedang Memproses...');

        $.ajax({
            url: 'vendor_stok.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            beforeSend: function() {
                $('#gridPreview tbody').html('<tr><td colspan="3" style="text-align:center;">Membaca dan menyimpan data ke database...</td></tr>');
            },
            success: function(res) {
                if(res.success) {
                    showMessage('success', res.message);
                    $('#csv_file').val(''); // Reset input file
                    
                    // Render View Table
                    var html = '';
                    if (res.data && res.data.length > 0) {
                        $.each(res.data, function(index, row) {
                            html += '<tr>' +
                                        '<td style="text-align:center;">' + (index + 1) + '</td>' +
                                        '<td>' + row.item_epson + '</td>' +
                                        '<td>' + row.stock + '</td>' +
                                    '</tr>';
                        });
                    } else {
                        html = '<tr><td colspan="3" style="text-align:center;">File CSV kosong atau format tidak sesuai.</td></tr>';
                    }
                    $('#gridPreview tbody').html(html);
                } else {
                    showMessage('error', res.message);
                    $('#gridPreview tbody').html('<tr><td colspan="3" style="text-align:center; color:red;">Gagal memuat data.</td></tr>');
                }
            },
            error: function() {
                showMessage('error', 'Terjadi kesalahan komunikasi AJAX ke server.');
                $('#gridPreview tbody').html('<tr><td colspan="3" style="text-align:center; color:red;">Error sistem.</td></tr>');
            },
            complete: function() {
                btn.prop('disabled', false).text('Upload & Simpan ke stock_upload');
            }
        });
    });
});
</script>
</body>
</html>