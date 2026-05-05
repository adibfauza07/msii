<?php
// ==========================================
// KONEKSI DINAMIS UNTUK PRINT
// ==========================================
if (session_status() == PHP_SESSION_NONE) { session_start(); }
// Tangkap parameter plant dari URL (karena dari AJAX dikirim lewat URL)
$active_plant = isset($_GET['plant']) ? $_GET['plant'] : (isset($_SESSION['active_plant']) ? $_SESSION['active_plant'] : 'p1');

if ($active_plant == 'p2') {
    if(!isset($_SESSION['erp_user'])) $_SESSION['erp_user'] = $_SESSION['db_user'];
    if(!isset($_SESSION['erp_pass'])) $_SESSION['erp_pass'] = $_SESSION['db_pass'];
    $_SESSION['server_sql'] = "192.168.0.9"; 
    require_once __DIR__ . '/../config/database.php';
} else {
    require_once __DIR__ . '/../config/database_p1.php';
}
// ==========================================

// ... (Biarkan sisa kode di bawahnya tetap sama) ...
// 2. Cek FPDF
$fpdf_path = '../lib/fpdf.php';
if (!file_exists($fpdf_path)) {
    die("<h3 style='color:red;'>Error: Library FPDF tidak ditemukan!</h3>
         <p>Silakan download 'fpdf.php' dari <strong>fpdf.org</strong> dan letakkan di folder <strong>/lib/</strong> di server Anda.</p>");
}
require_once $fpdf_path;

// 3. Validasi parameter URL
if (!isset($_GET['kode_usul'])) {
    die("<h3 style='color:red;'>Error: 'kode_usul' tidak diberikan!</h3>");
}
$kode_usul_cetak = $_GET['kode_usul'];

// 4. Query Data (Menggunakan query yang sama dengan edit_usulan.php)
$sql = "
SELECT 
    U.*, 
    I.ITEM_NO, 
    I.ITEM_NAME,
    C.CUST_COMP
FROM 
    USULAN_PERUBAHAN U
LEFT JOIN 
    ITEMS I ON U.ITEM_ID = I.ITEM_ID
LEFT JOIN
    CUST C ON U.CUST_ID = C.CUST_ID
WHERE 
    U.KODE_USUL = ?
";
$params = [$kode_usul_cetak];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
     die("Error Query: <pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) {
    die("<h3 style='color:red;'>Data tidak ditemukan untuk kode: " . htmlspecialchars($kode_usul_cetak) . "</h3>");
}

// 5. Helper Function & Class PDF
function safeDate($dateObj) {
    if ($dateObj && method_exists($dateObj, 'format')) {
        return $dateObj->format('d/m/Y');
    }
    return '-';
}

function safeText($text) {
    // Konversi encoding (PENTING untuk FPDF)
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', isset($text) ? $text : '');
}

// ====================================================
//  CLASS PDF CUSTOM UNTUK LAYOUT CRYSTAL REPORT
// ====================================================
class PDF_Usulan extends FPDF
{
    private $kodeUsul = '';

    function SetKodeUsul($kode) {
        $this->kodeUsul = $kode;
    }

    // Header Halaman
    function Header()
    {
        // Info Perusahaan
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(95, 5, 'PT. IMC TEKNO INDONESIA', 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(95, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 1, 'R'); // FIX
        $this->SetFont('Arial', '', 10);
        $this->Cell(95, 5, 'DCC DEPT', 0, 1, 'L'); // FIX
        
        // Kode Usul (dari screenshot)
        $this->SetFont('Arial', '', 10); // FIX
        $this->Cell(95, 5, $this->kodeUsul, 0, 1, 'L'); // FIX
        $this->Ln(1); // FIX

        // Judul Utama
        $this->SetFont('Arial', 'B', 16); // FIX
        $this->SetFillColor(210, 210, 210); // Latar abu-abu // FIX
        $this->Cell(0, 10, 'USULAN PERUBAHAN PLANT 2', 1, 1, 'C', true); // Ubah ke PLANT 1 // FIX
        $this->Ln(2); // FIX

        // Garis kotak luar
        $this->Rect(10, 10, 190, 277); // Kotak luar halaman // FIX
        $this->Rect(10, 10, 190, 32);  // Kotak header // FIX
    }

    // Footer Halaman
    function Footer()
    {
        $this->SetY(-15); // FIX
        $this->SetFont('Arial', 'B', 9); // FIX
        // Teks footer dari screenshot
        $this->Cell(0, 5, 'FM.DC.001-03 (REV 03, 13 NOV 2024)', 0, 1, 'L'); // FIX
    }

    // Fungsi untuk menggambar field data (Label + Garis + Data)
    function DataField($label, $data, $y_pos, $x_label = 15, $x_data = 45)
    {
        $this->SetFont('Arial', '', 10); // FIX
        $this->SetXY($x_label, $y_pos); // FIX
        $this->Cell(30, 5, $label, 0, 0, 'L'); // FIX
        $this->Cell(5, 5, ':', 0, 0, 'C'); // FIX
        
        $this->SetFont('Arial', 'B', 10); // FIX
        $this->SetXY($x_data, $y_pos); // FIX
        $this->MultiCell(150, 5, $data, 0, 'L'); // Ganti Cell jadi MultiCell // FIX
        
        // Garis bawah
        $this->Line($x_data, $y_pos + 5, 195, $y_pos + 5); // FIX
    }
    
    // Fungsi untuk membuat Judul Bagian (e.g. < DOKUMEN TERKAIT >)
    function SectionTitle($label)
    {
        $this->Ln(1); // (DIKECILKAN) Dari Ln(3) ke Ln(1)
        $this->SetFont('Arial', 'B', 10); // FIX
        $this->SetFillColor(230, 230, 230); // Abu-abu muda // FIX
        $this->Cell(0, 7, "< " . $label . " >", 1, 1, 'C', true); // FIX
        $this->Ln(1); // (DIKECILKAN) Dari Ln(3) ke Ln(1)
    }

    // Fungsi untuk menggambar 1 checkbox
    function CheckBox($label, $isChecked, $x, $y)
    {
        $this->SetFont('Arial', '', 10); // FIX
        $this->SetXY($x, $y); // FIX
        
        // Gambar kotak
        $this->Rect($x, $y, 4, 4);  // FIX
        if ($isChecked) {
            // Jika checked, gambar 'X'
            $this->SetFont('Arial', 'B', 10); // FIX
            $this->Cell(4, 4, 'X', 0, 0, 'C'); // FIX
        } else {
            $this->Cell(4, 4, '', 0, 0, 'L'); // FIX
        }
        $this->SetFont('Arial', '', 10); // FIX
        $this->SetX($x + 5); // Jarak 5mm // FIX
        $this->Cell(40, 4, $label, 0, 0, 'L'); // FIX
    }
    
    // (FIX) Fungsi DrawNotesBox diganti total
    // Fungsi untuk menggambar bagian Catatan (kiri) dan Diajukan (kanan)
    function DrawNotesAndSubmissionSection() // (FIX) Parameter $prepared_by dihapus
    {
        $this->SetFont('Arial', '', 9);
        $y_start = $this->GetY();
        $h_section = 20; // Tinggi total
        $h_title = 6; // Tinggi header "Diajukan oleh"
        
        // ----- Kiri: Catatan -----
        $x_catatan = 15;
        $w_catatan = 105; // Lebar
        $this->Rect($x_catatan, $y_start, $w_catatan, $h_section); // Kotak Catatan
        
        // Teks statis
        $this->SetXY($x_catatan + 1, $y_start + 1);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(20, 5, "Catatan:", 0, 1, 'L');
        $this->SetFont('Arial', '', 9);
        $this->SetXY($x_catatan + 1, $y_start + 5);
        $this->MultiCell($w_catatan - 2, 4, 
            "Untuk pengajuan perubahan beberapa jenis dokumen dalam waktu sama, " .
            "bisa dengan membuat lampiran Gimbsho."
        , 0, 'L');
        
        // ----- Kanan: Diajukan oleh -----
        $x_diajukan = $x_catatan + $w_catatan; // 15 + 105 = 120
        $w_diajukan = 75; // Lebar sisa (total 180)

        // Outer box
        $this->Rect($x_diajukan, $y_start, $w_diajukan, $h_section); 
        // Title box
        $this->Rect($x_diajukan, $y_start, $w_diajukan, $h_title);
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY($x_diajukan, $y_start + 1);
        $this->Cell($w_diajukan, 5, "Diajukan oleh", 0, 1, 'C');

        // Sub-box "Checked"
        $w_sub_box = $w_diajukan / 2; // 37.5
        $h_sub_box = $h_section - $h_title; // 14
        $this->Rect($x_diajukan, $y_start + $h_title, $w_sub_box, $h_sub_box);
        // Sub-box "Prepared"
        $this->Rect($x_diajukan + $w_sub_box, $y_start + $h_title, $w_sub_box, $h_sub_box);
        
        // Teks "Checked" (di bawah)
        $this->SetFont('Arial', '', 8);
        $this->SetXY($x_diajukan, $y_start + $h_section - 5);
        $this->Cell($w_sub_box, 5, "Checked", 0, 0, 'C');
        
        // Teks "Prepared" (di bawah)
        $this->SetXY($x_diajukan + $w_sub_box, $y_start + $h_section - 5);
        $this->Cell($w_sub_box, 5, "Prepared", 0, 0, 'C');

        // Data "Prepared" (di tengah)
        // (FIX) Baris ini dikosongkan/dihapus sesuai request
        // $this->SetFont('Arial', 'B', 9);
        // $this->SetXY($x_diajukan + $w_sub_box, $y_start + $h_title + 3); // Posisi Y di tengah
        // $this->Cell($w_sub_box, 5, $prepared_by, 0, 0, 'C');

        // Set Y ke posisi setelah kotak catatan
        $this->SetY($y_start + $h_section + 1); // (DIKECILKAN) Dari +3 ke +1
    }

    // Fungsi untuk menggambar bagian Tanda Tangan
    function DrawSignatureSection()
    {
        $this->SectionTitle("DISETUJUI"); // FIX
        
        $y_start_sig = $this->GetY(); // FIX
        $h_sig_box = 30; // Tinggi kotak tanda tangan
        $w_sig_box = 92.5; // Lebar (185 / 2)
        $h_title_box = 6; // Tinggi kotak judul

        // Kotak Kiri: Production Engineering
        $this->SetFont('Arial', 'B', 9); // FIX
        $this->Rect(15, $y_start_sig, $w_sig_box, $h_title_box); // Judul Kiri // FIX
        $this->Cell($w_sig_box, $h_title_box, "Production Engineering", 0, 0, 'C'); // FIX
        
        $this->Rect(15, $y_start_sig, $w_sig_box, $h_sig_box); // Kotak Kiri Luar // FIX
        $this->Rect(15, $y_start_sig + $h_title_box, $w_sig_box / 2, $h_sig_box - $h_title_box); // Approve // FIX
        $this->Rect(15 + ($w_sig_box / 2), $y_start_sig + $h_title_box, $w_sig_box / 2, $h_sig_box - $h_title_box); // Checked // FIX
        
        $this->SetFont('Arial', '', 8); // FIX
        $this->SetXY(15, $y_start_sig + $h_sig_box - 5); // FIX
        $this->Cell($w_sig_box / 2, 5, "Approve", 0, 0, 'C'); // FIX
        $this->Cell($w_sig_box / 2, 5, "Checked", 0, 0, 'C'); // FIX


        // Kotak Kanan: MR / DCC
        $this->SetFont('Arial', 'B', 9); // FIX
        $this->Rect(15 + $w_sig_box, $y_start_sig, $w_sig_box, $h_title_box); // Judul Kanan // FIX
        $this->SetXY(15 + $w_sig_box, $y_start_sig); // FIX
        $this->Cell($w_sig_box, $h_title_box, "DCC", 0, 1, 'C'); // FIX

        $this->Rect(15 + $w_sig_box, $y_start_sig, $w_sig_box, $h_sig_box); // Kotak Kanan Luar // FIX
        $this->Rect(15 + $w_sig_box, $y_start_sig + $h_title_box, $w_sig_box / 2, $h_sig_box - $h_title_box); // Approve // FIX
        $this->Rect(15 + $w_sig_box + ($w_sig_box / 2), $y_start_sig + $h_title_box, $w_sig_box / 2, $h_sig_box - $h_title_box); // Checked // FIX

        $this->SetFont('Arial', '', 8); // FIX
        $this->SetXY(15 + $w_sig_box, $y_start_sig + $h_sig_box - 5); // FIX
        $this->Cell($w_sig_box / 2, 5, "Approve", 0, 0, 'C'); // FIX
        $this->Cell($w_sig_box / 2, 5, "Checked", 0, 0, 'C'); // FIX
        
        $this->SetY($y_start_sig + $h_sig_box + 1); // (DIKECILKAN) Dari +2 ke +1
    }
    
    // Fungsi untuk menggambar Gambar Matriks
    function DrawMatrixImage($imagePath)
    {
        // (FIX) Perbaikan teks
        $this->SectionTitle(" MATRIK PERUBAHAN "); 
        
        // Cek jika file gambar ada
        // Path relatif dari /qc_p1/report_form_pdf.php ke /assets/image.png
        $actualImagePath = __DIR__ . '/../assets/image.png';

        if (file_exists($actualImagePath)) {
            
            // Ukuran maks di A4 (lebar 190mm - margin 10x2 = 170mm)
            $maxWidth = 185; // Lebar maks dalam kotak
            
            // Posisi Y
            $current_y = $this->GetY(); // FIX
            
            // Lebar gambar (buat 185mm)
            $newWidth = $maxWidth; 
            
            // (DIKECILKAN) Mengurangi tinggi gambar agar pas 1 halaman
            $newHeight = 55; // Coba set tinggi manual (sebelumnya 70)
            
            // Cek apakah cukup ruang, jika tidak, tambah halaman
            // (FIX) Cek dikurangi (15 untuk teks NOTE)
            if ($current_y + $newHeight + 15 > $this->h - $this->bMargin) { 
                $this->AddPage(); // FIX
                $current_y = 45; // Reset Y di halaman baru (setelah header)
                $this->SetY($current_y); // FIX
                $this->SectionTitle("MATREK PERUBAHAN"); // Judul lagi // FIX
                $current_y = $this->GetY(); // FIX
            }

            $this->Image($actualImagePath, 12.5, $current_y, $newWidth, $newHeight); // FIX
            $this->SetY($current_y + $newHeight + 1); // (DIKECILKAN) Dari +3 ke +1
            
            // Note: (dari screenshot)
            $this->SetFont('Arial', '', 8); // FIX
            $this->SetX(15); // FIX
            $this->Cell(0, 4, "NOTE :", 0, 1, 'L'); // FIX
            $this->SetX(15); // FIX
            $this->Cell(0, 4, "O : Perlu Revisi", 0, 1, 'L'); // FIX
            $this->SetX(15); // FIX
            $this->Cell(0, 4, "X : Tidak Perlu Revisi", 0, 1, 'L'); // FIX

        } else {
            $this->SetFont('Arial', 'B', 10); // FIX
            $this->SetTextColor(255, 0, 0); // Merah // FIX
            $this->Cell(0, 10, "Error: Gambar Matriks tidak ditemukan di " . $actualImagePath, 1, 1, 'C'); // FIX
            $this->SetTextColor(0, 0, 0); // FIX
        }
    }
}

// 6. Mulai Buat PDF
$pdf = new PDF_Usulan('P', 'mm', 'A4'); // P = Portrait
$pdf->AliasNbPages();
$pdf->SetKodeUsul(safeText($data['KODE_USUL']));
$pdf->AddPage();
$pdf->SetAutoPageBreak(false, 15); // AutoPageBreak di-false agar bisa atur manual
$pdf->SetMargins(10, 10, 10);

// === GAMBAR FIELD DATA ===
// (DIKECILKAN) Jarak antar field dikurangi dari 7mm ke 6.5mm
$pdf->DataField('Issue Date', safeDate($data['ISSUE_DATE']), 45);
$pdf->DataField('Customer', safeText($data['CUST_COMP']), 51.5);
$pdf->DataField('Document Name', safeText($data['JUDUL_DOK']), 58);
$pdf->DataField('Part Name', safeText($data['ITEM_NAME']), 64.5);
$pdf->DataField('Part No', safeText($data['ITEM_NO']), 71);
$pdf->DataField('Request By', safeText($data['PJ']), 77.5);

// Revisi
$revisi_text = '';
if ($data['BARU']) $revisi_text = 'BARU';
if ($data['REVISI']) $revisi_text = 'REVISI (No: ' . $data['REVISI_1'] . ')';
$pdf->DataField('Revision', $revisi_text, 84);

// (PENTING) Ganti 'Reason' dengan 'Isi Revisi' dan tambahkan 'Alasan Revisi'
$pdf->DataField('Isi Revisi', safeText($data['ISI_REVISI']), 90.5); 
$pdf->DataField('Alasan Revisi', safeText($data['ALASAN_REVISI']), 97); 

$pdf->DataField('PLAN DATE', safeDate($data['PLANT_DATE']), 103.5); 
$pdf->DataField('TARGET DATE', safeDate($data['TARGET_DATE']), 110); 

// === BAGIAN DOKUMEN TERKAIT ===
$pdf->SectionTitle("DOKUMEN TERKAIT PERUBAHAN");

$y_chk_start = $pdf->GetY();
// Kolom 1
$pdf->CheckBox('Inspection STD', $data['IS_STD'], 15, $y_chk_start);
$pdf->CheckBox('Work Instruction', $data['WI'], 15, $y_chk_start + 7);
// Kolom 2
$pdf->CheckBox('Check Point', $data['CHECK_POINT'], 65, $y_chk_start);
$pdf->CheckBox('Packing Standard', $data['STD_PACK'], 65, $y_chk_start + 7);
// Kolom 3
$pdf->CheckBox('FMEA', $data['FMEA'], 115, $y_chk_start);
$pdf->CheckBox('QCPC', $data['QCPC'], 115, $y_chk_start + 7);
// Kolom 4
$pdf->CheckBox('Setting Parameter', $data['SETTING_PAR'], 155, $y_chk_start);
$pdf->CheckBox('Other', $data['OTHER'], 155, $y_chk_start + 7);

$pdf->SetY($y_chk_start + 14); // (DIKECILKAN) Dari +15 ke +14

// === BAGIAN CATATAN (Sesuai Screenshot) ===
// (FIX) Memanggil fungsi baru dan mengirimkan data 'PJ'
$pdf->DrawNotesAndSubmissionSection(); // (FIX) Parameter dihapus

// === BAGIAN TANDA TANGAN ===
$pdf->DrawSignatureSection();

// === BAGIAN MATRIKS GAMBAR ===
$pdf->DrawMatrixImage('../assets/image.png');

// 7. Output PDF
$pdf->Output('I', 'Usulan_' . $kode_usul_cetak . '.pdf');

?>