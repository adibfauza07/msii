<?php
/**
 * PENGGUNAAN TCPDF (Sesuai dengan struktur folder assets Anda)
 * Pastikan file ini (misal: iso.php) berada di root folder 'msii' 
 * sejajar dengan folder 'assets'.
 */

// Memanggil library TCPDF dari folder assets/tcpdf_min
//require_once('assets/tcpdf_min/tcpdf.php');
require_once(__DIR__ . '/../assets/tcpdf_min/tcpdf.php');

// Membuat instance PDF baru
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Mengatur informasi dokumen
$pdf->SetCreator('IMS System');
$pdf->SetAuthor('Senior Auditor IMS');
$pdf->SetTitle('Laporan Analisis Kasus Audit IMS');

// Menghilangkan header dan footer default TCPDF agar terlihat lebih bersih
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Mengatur margin (Kiri, Atas, Kanan)
$pdf->SetMargins(15, 20, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Menambahkan halaman baru
$pdf->AddPage();

// Konten HTML (Struktur disesuaikan sepenuhnya untuk engine render TCPDF)
// Catatan: TCPDF tidak mendukung Flexbox (display: flex), sehingga layout grid/fishbone 
// diubah menggunakan tabel 100% agar rapi saat dicetak.
$html_content = <<<EOD
<style>
    body {
        font-family: helvetica;
        font-size: 10pt;
        color: #2c3e50;
        line-height: 1.5;
    }
    .header-box {
        background-color: #1a5276;
        color: #ffffff;
        text-align: center;
        padding: 10px;
    }
    h1 { margin: 0; font-size: 15pt; }
    h2 { 
        color: #1a5276; 
        font-size: 12pt;
        border-bottom: 2px solid #1a5276;
    }
    h3 { 
        color: #34495e; 
        font-size: 11pt;
    }
    .table-main {
        border-collapse: collapse;
        width: 100%;
    }
    th {
        background-color: #ecf0f1;
        border: 1px solid #bdc3c7;
        font-weight: bold;
        padding: 5px;
    }
    td {
        border: 1px solid #bdc3c7;
        padding: 5px;
    }
    .clause-tag {
        background-color: #e74c3c;
        color: #ffffff;
        font-weight: bold;
        font-size: 8pt;
    }
    .recommendation {
        background-color: #e8f8f5;
        border: 1px solid #a3e4d7;
        padding: 10px;
    }
    .why-why {
        background-color: #f9ebea;
        border-left: 4px solid #e74c3c;
        padding: 10px;
    }
</style>

<div class="header-box">
    <h1>LAPORAN TEMUAN & ANALISIS KASUS AUDIT IMS</h1>
    <span style="font-size: 9pt;">ISO 9001:2015 | IATF 16949:2016 | ISO 14001:2015</span>
</div>

<h2>1. Skenario Kasus (Deskripsi Ketidaksesuaian)</h2>
<p>Pada saat audit di area perakitan (Assembly Line 2), ditemukan bahwa komponen setir untuk model kemudi kiri (Left-Hand Drive / LHD) tercampur dan terpasang pada sasis kemudi kanan (Right-Hand Drive / RHD). Saat operator menyadari kesalahan tersebut, mereka memindahkan unit ke area loading dock untuk dilakukan <i>rework</i> (pembongkaran). Proses <i>rework</i> yang tidak standar ini menyebabkan tumpahan oli pelumas steering mengalir ke saluran drainase air hujan pabrik, yang bermuara langsung ke lingkungan luar tanpa melewati IPAL (Instalasi Pengolahan Air Limbah).</p>

<h2>2. Analisis Temuan dan Pemetaan Klausul</h2>
<table class="table-main" cellpadding="5">
    <thead>
        <tr>
            <th width="25%"><b>Referensi Klausul</b></th>
            <th width="45%"><b>Deskripsi Temuan (Non-Conformity)</b></th>
            <th width="30%"><b>Dampak Operasional / Lingkungan</b></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td width="25%">
                <span class="clause-tag"> IATF 16949 - Cl. 8.7.1.4 </span><br>
                <span class="clause-tag"> ISO 9001 - Cl. 8.5.1 </span>
            </td>
            <td width="45%">Pencampuran part LHD ke unit RHD. Proses <i>rework</i> dilakukan tanpa mengikuti Standar Instruksi Kerja (WI) yang disetujui, dan dilakukan di area yang tidak diizinkan.</td>
            <td width="30%">Cacat produk kritis berpotensi lolos ke pelanggan (Safety hazard). Penurunan efisiensi produksi.</td>
        </tr>
        <tr>
            <td width="25%">
                <span class="clause-tag"> ISO 14001 - Cl. 8.2 </span><br>
                <span class="clause-tag"> ISO 14001 - Cl. 6.1.2 </span>
            </td>
            <td width="45%">Tumpahan oli pelumas (B3) masuk ke saluran drainase air hujan akibat proses <i>rework</i> di luar area kendali, tanpa adanya tindakan tanggap darurat (Spill Kit tidak digunakan).</td>
            <td width="30%">Pencemaran lingkungan luar area pabrik, pelanggaran regulasi pemerintah mengenai limbah B3, risiko denda.</td>
        </tr>
    </tbody>
</table>

<h2>3. Analisis Akar Masalah (Root Cause Analysis)</h2>
<h3>A. Fishbone Diagram (Diagram Ishikawa)</h3>
<table width="100%" cellpadding="5">
    <tr>
        <td width="48%" style="border: 1px solid #bdc3c7; background-color: #f8f9fa;">
            <div style="color: #1a5276; font-weight: bold; margin-bottom: 5px;">Manusia (Man)</div>
            <ul>
                <li>Operator kurang teliti membedakan part LHD dan RHD.</li>
                <li>Operator tidak terlatih menangani tumpahan B3.</li>
            </ul>
        </td>
        <td width="4%"></td>
        <td width="48%" style="border: 1px solid #bdc3c7; background-color: #f8f9fa;">
            <div style="color: #1a5276; font-weight: bold; margin-bottom: 5px;">Metode (Method)</div>
            <ul>
                <li>SOP Rework tidak mencakup instruksi mitigasi tumpahan cairan.</li>
                <li>Tidak ada verifikasi visual sebelum perakitan.</li>
            </ul>
        </td>
    </tr>
    <tr><td colspan="3" height="5"></td></tr>
    <tr>
        <td width="48%" style="border: 1px solid #bdc3c7; background-color: #f8f9fa;">
            <div style="color: #1a5276; font-weight: bold; margin-bottom: 5px;">Mesin / Alat (Machine)</div>
            <ul>
                <li>Jig perakitan menerima part LHD & RHD (Tidak ada Poka-Yoke).</li>
            </ul>
        </td>
        <td width="4%"></td>
        <td width="48%" style="border: 1px solid #bdc3c7; background-color: #f8f9fa;">
            <div style="color: #1a5276; font-weight: bold; margin-bottom: 5px;">Material</div>
            <ul>
                <li>Part LHD dan RHD memiliki dimensi & warna yang identik.</li>
            </ul>
        </td>
    </tr>
    <tr><td colspan="3" height="5"></td></tr>
    <tr>
        <td width="100%" style="border: 1px solid #bdc3c7; background-color: #f8f9fa;">
            <div style="color: #1a5276; font-weight: bold; margin-bottom: 5px;">Lingkungan (Environment)</div>
            <ul>
                <li>Area <i>rework</i> darurat (loading dock) berada tepat di sebelah saluran drainase air hujan tanpa <i>secondary containment</i>.</li>
            </ul>
        </td>
    </tr>
</table>

<h3>B. Why-Why Analysis (5 Whys)</h3>
<div class="why-why">
    <b>Masalah Utama:</b> Part salah terpasang, menyebabkan tumpahan limbah B3 saat rework.
    <ul>
        <li><b>Why 1:</b> Mengapa terjadi tumpahan limbah ke saluran drainase?<br><i>Karena proses rework dilakukan di loading dock yang dekat dengan drainase.</i></li>
        <li><b>Why 2:</b> Mengapa rework dilakukan di loading dock?<br><i>Karena area perakitan penuh dan tidak ada area khusus rework di SOP.</i></li>
        <li><b>Why 3:</b> Mengapa unit harus di-rework?<br><i>Karena part kemudi kiri (LHD) terpasang pada unit kemudi kanan (RHD).</i></li>
        <li><b>Why 4:</b> Mengapa part LHD bisa terpasang di unit RHD?<br><i>Karena operator mengambil part dari bin yang salah, dan mesin press menerima part tersebut.</i></li>
        <li><b>Why 5 (Root Cause):</b> Mengapa mesin menerima part tersebut?<br><i>Tidak ada sistem anti-salah (Poka-Yoke) pada jig perakitan untuk membedakan profil LHD dan RHD.</i></li>
    </ul>
</div>

<h2>4. Rekomendasi & Tindakan Perbaikan (8D)</h2>
<div class="recommendation">
    <b>Tindakan Penahanan Langsung (Containment / D3):</b>
    <ul>
        <li>Line Stop sementara dan menyortir 100% unit shift terkait.</li>
        <li>Gunakan <i>Spill Kit</i> seketika untuk menyerap oli dan menutup akses drainase.</li>
        <li>Sedot (vacuum) saluran drainase sebelum oli mencapai badan air.</li>
    </ul>
    <b>Tindakan Perbaikan Sistemik (Systemic Corrective Action / D5 & D6):</b>
    <ul>
        <li><b>Engineering (Poka-Yoke):</b> Modifikasi jig perakitan dengan sensor profil (pin guide) sehingga sasis RHD menolak komponen LHD secara mekanis.</li>
        <li><b>Sistem Dokumen:</b> Revisi SOP <i>Rework</i> (Cl. 8.7.1.4); wajibkan aktivitas fluida di area epoxy bertanggul (secondary containment).</li>
        <li><b>Lingkungan (ISO 14001):</b> Tempatkan <i>Spill Kit</i> di titik berisiko dan <i>refreshment training</i> (Cl. 7.2) Tanggap Darurat Tumpahan Kimia.</li>
    </ul>
</div>
EOD;

// Print text menggunakan writeHTMLCell() atau writeHTML()
$pdf->writeHTML($html_content, true, false, true, false, '');

// Output PDF 
// 'I' = Menampilkan PDF langsung di Browser
// 'D' = Memaksa user untuk mendownload PDF
// 'F' = Menyimpan file PDF langsung ke server
$pdf->Output('Laporan_Analisis_Klausul_IMS.pdf', 'I');
?>