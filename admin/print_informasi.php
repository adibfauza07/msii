<?php
require_once __DIR__ . "/../config/global.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$trigger = isset($_GET['trigger']) ? $_GET['trigger'] : '';

$tsql = "SELECT * FROM tbl_informasi_internal WHERE id = ?";
$stmt = sqlsrv_query($conn, $tsql, array($id));

if ($stmt === false || !sqlsrv_has_rows($stmt)) {
    die("Data tidak ditemukan. Silakan kembali dan lakukan pencarian ulang.");
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Format tanggal
$date_obj = $row['info_date'];
$date_str = $date_obj ? $date_obj->format('j-M-y') : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Informasi Internal - <?php echo htmlspecialchars($row['kode_dokumen']); ?></title>
    
    <!-- Library HTML to PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 0;
            background: #e2e8f0;
            color: #000;
        }

        /* Container Toolbar */
        .toolbar {
            text-align: center;
            padding: 15px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .btn {
            padding: 10px 20px; font-size: 14px; font-weight: bold; margin: 0 5px; cursor: pointer; border: none; border-radius: 4px; color: white;
        }
        .btn-preview { background-color: #4b5563; }
        .btn-print { background-color: #2563eb; }
        .btn-pdf { background-color: #dc2626; }
        
        /* Area Kertas PDF/Print */
        .page-a4 {
            width: 210mm;
            min-height: 297mm; /* Menggunakan min-height agar bisa menyesuaikan jika teks panjang */
            padding: 10mm;
            margin: 0 auto;
            background: white;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        /* Garis Tepi Utama */
        .main-border {
            border: 2px solid black;
            flex-grow: 1; /* Border otomatis mengisi sisa tinggi kertas, mencegah konten keluar (nyebrang) */
            min-height: 272mm; 
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        /* Header (PT & Judul) */
        .header-section {
            border-bottom: 2px solid black;
            padding: 5px 10px 10px 10px;
        }
        .company-name {
            font-size: 10pt;
            font-weight: bold;
        }
        .doc-title {
            font-size: 32pt;
            text-align: center;
            letter-spacing: 1px;
            margin: 10px 0 0 0;
        }

        /* Sub Header (Tabel Info & Tabel TTD) */
        .sub-header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid black;
            padding: 10px;
        }
        
        .info-table {
            border-collapse: collapse;
            font-size: 11pt;
        }
        .info-table td {
            border: 1px solid black;
            padding: 2px 6px;
        }
        .info-table .col-label { width: 50px; }
        .info-table .col-value { width: 180px; }

        .sign-table {
            border-collapse: collapse;
        }
        .sign-table td {
            border: 1px solid black;
            width: 85px;
            height: 60px;
            text-align: center;
            vertical-align: top;
            padding-top: 5px;
            position: relative;
        }
        .sign-title {
            font-size: 10pt;
            font-weight: bold;
            text-decoration: underline;
        }
        .sign-name {
            position: absolute;
            bottom: 3px;
            left: 0;
            width: 100%;
            font-size: 9pt;
        }

        /* Area Konten Surat */
        .content-section {
            padding: 20px 30px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            font-size: 11pt; /* Font sedikit disesuaikan agar fit/pas */
        }
        .subject-line {
            font-weight: bold;
            margin-bottom: 20px;
        }
        .message-body {
            margin-left: 30px; /* Menjorok ke dalam sedikit */
            margin-right: 15px;
            text-align: left; /* PERBAIKAN: Rata Kiri Saja */
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word; /* Mencegah kalimat nyambung melewati batas kanan */
        }

        /* Bagian Mengetahui (Kiri Bawah di dalam border) */
        .mengetahui-section {
            margin-left: 30px;
            margin-top: 40px; /* Jarak antara teks terakhir dan tulisan Mengetahui */
            margin-bottom: 10px;
        }
        .mengetahui-title {
            margin-bottom: 50px; /* Jarak untuk tanda tangan (ruang kosong) */
        }
        .mengetahui-dept {
            text-decoration: underline;
            font-weight: bold;
        }

        /* Footer Kode Dokumen (Diluar border) */
        .footer-code {
            font-size: 10pt;
            margin-top: 5px;
            margin-left: 2px;
        }
        
        /* Kode Unik Referensi di luar frame atas */
        .doc-code {
            position: absolute;
            top: 4mm;
            right: 10mm;
            font-size: 9pt;
            color: #555;
            font-family: Arial, sans-serif;
        }

        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .page-a4 { box-shadow: none; margin: 0; padding: 10mm; width: 100%; min-height: 297mm; height: auto; }
        }
    </style>
</head>
<body>

<!-- Toolbar Action -->
<div class="toolbar no-print">
    <a href="input_informasi.php" class="btn btn-preview" style="text-decoration: none;">&larr; Kembali</a>
    <button onclick="window.print()" class="btn btn-print">🖨️ Cetak / Print</button>
    <button onclick="generatePDF()" class="btn btn-pdf">📄 Download PDF</button>
</div>

<!-- Frame Kertas A4 -->
<div class="page-a4" id="document-content">
    
    <!-- Teks Kode Auto-Generate di luar kotak -->
    <div class="doc-code">Ref: <?php echo htmlspecialchars($row['kode_dokumen']); ?></div>
    
    <!-- Kotak Garis Tepi Utama -->
    <div class="main-border">
        
        <!-- Header -->
        <div class="header-section">
            <div class="company-name">PT. IMC TEKNO INDONESIA</div>
            <div class="doc-title">INFORMASI INTERNAL</div>
        </div>

        <!-- Tabel Info & TTD -->
        <div class="sub-header">
            <table class="info-table">
                <tr>
                    <td class="col-label">Date</td>
                    <td class="col-value"><?php echo htmlspecialchars($date_str); ?></td>
                </tr>
                <tr>
                    <td class="col-label">To</td>
                    <td class="col-value"><?php echo htmlspecialchars($row['info_to']); ?></td>
                </tr>
                <tr>
                    <td class="col-label">Cc</td>
                    <td class="col-value"><?php echo htmlspecialchars($row['info_cc']); ?></td>
                </tr>
                <tr>
                    <td class="col-label">From</td>
                    <td class="col-value"><?php echo htmlspecialchars($row['info_from']); ?></td>
                </tr>
            </table>

            <table class="sign-table">
                <tr>
                    <td>
                        <div class="sign-title">Approved</div>
                        <div class="sign-name"><?php echo htmlspecialchars($row['approved_by']); ?></div>
                    </td>
                    <td>
                        <div class="sign-title">Checked</div>
                        <div class="sign-name"><?php echo htmlspecialchars($row['checked_by']); ?></div>
                    </td>
                    <td>
                        <div class="sign-title">Prepared</div>
                        <div class="sign-name"><?php echo htmlspecialchars($row['prepared_by']); ?></div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Isi Konten -->
        <div class="content-section">
            <div class="subject-line">Subject : <?php echo htmlspecialchars($row['info_subject']); ?></div>
            
            <!-- Teks sekarang di set Rata Kiri dengan word-wrap -->
            <div class="message-body"><?php echo htmlspecialchars($row['message_body']); ?></div>

            <div class="mengetahui-section">
                <div class="mengetahui-title">Mengetahui</div>
                <div class="mengetahui-dept"><?php echo htmlspecialchars($row['acknowledged_dept']); ?></div>
                <div class="mengetahui-name">(<?php echo htmlspecialchars($row['acknowledged_by']); ?>)</div>
            </div>
        </div>

    </div> <!-- End main-border -->

    <!-- Form Code Kiri Bawah -->
    <div class="footer-code">FM. HR. 00-022</div>

</div> <!-- End page-a4 -->

<script>
function generatePDF() {
    var element = document.getElementById('document-content');
    var filename = '<?php echo htmlspecialchars($row['kode_dokumen']); ?>.pdf';
    
    var opt = {
        margin:       0, 
        filename:     filename,
        image:        { type: 'jpeg', quality: 1 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(element).save();
}

window.onload = function() {
    var triggerAction = '<?php echo $trigger; ?>';
    if (triggerAction === 'print') {
        window.print();
    } else if (triggerAction === 'pdf') {
        setTimeout(function() { generatePDF(); }, 500);
    }
};
</script>

</body>
</html>