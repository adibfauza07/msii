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
    private $plantName = ''; // Property baru untuk Judul Dinamis

    function SetKodeUsul($kode) {
        $this->kodeUsul = $kode;
    }

    // Fungsi baru untuk menerima nama Plant
    function SetPlantName($name) {
        $this->plantName = $name;
    }

    // Header Halaman
    function Header()
    {
        // Info Perusahaan
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(95, 5, 'PT. IMC TEKNO INDONESIA', 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(95, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 1, 'R'); 
        $this->SetFont('Arial', '', 10);
        $this->Cell(95, 5, 'DCC DEPT', 0, 1, 'L'); 
        
        // Kode Usul
        $this->SetFont('Arial', '', 10); 
        $this->Cell(95, 5, $this->kodeUsul, 0, 1, 'L'); 
        $this->Ln(1); 

        // Judul Utama (SEKARANG DINAMIS)
        $this->SetFont('Arial', 'B', 16); 
        $this->SetFillColor(210, 210, 210); 
        $this->Cell(0, 10, 'USULAN PERUBAHAN ' . $this->plantName, 1, 1, 'C', true); 
        $this->Ln(2); 

        // Garis kotak luar
        $this->Rect(10, 10, 190, 277); 
        $this->Rect(10, 10, 190, 32);  
    }

    // Footer Halaman
    function Footer()
    {
        $this->SetY(-15); 
        $this->SetFont('Arial', 'B', 9); 
        $this->Cell(0, 5, 'FM.DC.001-03 (REV 03, 13 NOV 2024)', 0, 1, 'L'); 
    }

    // Fungsi untuk menggambar field data (Label + Garis + Data)
    function DataField($label, $data, $y_pos, $x_label = 15, $x_data = 45)
    {
        $this->SetFont('Arial', '', 10); 
        $this->SetXY($x_label, $y_pos); 
        $this->Cell(30, 5, $label, 0, 0, 'L'); 
        $this->Cell(5, 5, ':', 0, 0, 'C'); 
        
        $this->SetFont('Arial', 'B', 10); 
        $this->SetXY($x_data, $y_pos); 
        $this->MultiCell(150, 5, $data, 0, 'L'); 
        
        // Garis bawah
        $this->Line($x_data, $y_pos + 5, 195, $y_pos + 5); 
    }
    
    // Fungsi untuk membuat Judul Bagian (e.g. < DOKUMEN TERKAIT >)
    function SectionTitle($label)
    {
        $this->Ln(1); 
        $this->SetFont('Arial', 'B', 10); 
        $this->SetFillColor(230, 230, 230); 
        $this->Cell(0, 7, "< " . $label . " >", 1, 1, 'C', true); 
        $this->Ln(1); 
    }

    // Fungsi untuk menggambar 1 checkbox
    function CheckBox($label, $isChecked, $x, $y)
    {
        $this->SetFont('Arial', '', 10); 
        $this->SetXY($x, $y); 
        
        // Gambar kotak
        $this->Rect($x, $y, 4, 4);  
        if ($isChecked) {
            // Jika checked, gambar 'X'
            $this->SetFont('Arial', 'B', 10); 
            $this->Cell(4, 4, 'X', 0, 0, 'C'); 
        } else {
            $this->Cell(4, 4, '', 0, 0, 'L'); 
        }
        $this->SetFont('Arial', '', 10); 
        $this->SetX($x + 5); 
        $this->Cell(40, 4, $label, 0, 0, 'L'); 
    }
    
    // Fungsi untuk menggambar bagian Catatan (kiri) dan Diajukan (kanan)
    function DrawNotesAndSubmissionSection() 
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
        $x_diajukan = $x_catatan + $w_catatan; 
        $w_diajukan = 75; 

        // Outer box
        $this->Rect($x_diajukan, $y_start, $w_diajukan, $h_section); 
        // Title box
        $this->Rect($x_diajukan, $y_start, $w_diajukan, $h_title);
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY($x_diajukan, $y_start + 1);
        $this->Cell($w_diajukan, 5, "Diajukan oleh", 0, 1, 'C');

        // Sub-box "Checked"
        $w_sub_box = $w_diajukan / 2; 
        $h_sub_box = $h_section - $h_title; 
        $this->Rect($x_diajukan, $y_start + $h_title, $w_sub_box, $h_sub_box);
        // Sub-box "Prepared"
        $this->Rect($x_diajukan + $w_sub_box, $y_start + $h_title, $w_sub_box, $h_sub_box);
        
        // Teks "Checked"
        $this->SetFont('Arial', '', 8);
        $this->SetXY($x_diajukan, $y_start + $h_section - 5);
        $this->Cell($w_sub_box, 5, "Checked", 0, 0, 'C');
        
        // Teks "Prepared" 
        $this->SetXY($x_diajukan + $w_sub_box, $y_start + $h_section - 5);
        $this->Cell($w_sub_box, 5, "Prepared", 0, 0, 'C');

        // Set Y ke posisi setelah kotak catatan
        $this->SetY($y_start + $h_section + 1); 
    }

    // Fungsi untuk menggambar bagian Tanda Tangan
    function DrawSignatureSection()
    {
        $this->SectionTitle("DISETUJUI"); 
        
        $y_start_sig = $this->GetY(); 
        $h_sig_box = 30; 
        $w_sig_box = 92.5; 
        $h_title_box = 6; 

        // Kotak Kiri: Production Engineering
        $this->SetFont('Arial', 'B', 9); 
        $this->Rect(15, $y_start_sig, $w_sig_box, $h_title_box); 
        $this->Cell($w_sig_box, $h_title_box, "Production Engineering", 0, 0, 'C'); 
        
        $this->Rect(15, $y_start_sig, $w_sig_box, $h_sig_box); 
        $this->Rect(15, $y_start_sig + $h_title_box, $w_sig_box / 2, $h_sig_box - $h_title_box); 
        $this->Rect(15 + ($w_sig_box / 2), $y_start_sig + $h_title_box, $w_sig_box / 2, $h_sig_box - $h_title_box); 
        
        $this->SetFont('Arial', '', 8); 
        $this->SetXY(15, $y_start_sig + $h_sig_box - 5); 
        $this->Cell($w_sig_box / 2, 5, "Approve", 0, 0, 'C'); 
        $this->Cell($w_sig_box / 2, 5, "Checked", 0, 0, 'C'); 


        // Kotak Kanan: MR / DCC
        $this->SetFont('Arial', 'B', 9); 
        $this->Rect(15 + $w_sig_box, $y_start_sig, $w_sig_box, $h_title_box); 
        $this->SetXY(15 + $w_sig_box, $y_start_sig); 
        $this->Cell($w_sig_box, $h_title_box, "DCC", 0, 1, 'C'); 

        $this->Rect(15 + $w_sig_box, $y_start_sig, $w_sig_box, $h_sig_box); 
        $this->Rect(15 + $w_sig_box, $y_start_sig + $h_title_box, $w_sig_box / 2, $h_sig_box - $h_title_box); 
        $this->Rect(15 + $w_sig_box + ($w_sig_box / 2), $y_start_sig + $h_title_box, $w_sig_box / 2, $h_sig_box - $h_title_box); 

        $this->SetFont('Arial', '', 8); 
        $this->SetXY(15 + $w_sig_box, $y_start_sig + $h_sig_box - 5); 
        $this->Cell($w_sig_box / 2, 5, "Approve", 0, 0, 'C'); 
        $this->Cell($w_sig_box / 2, 5, "Checked", 0, 0, 'C'); 
        
        $this->SetY($y_start_sig + $h_sig_box + 1); 
    }
    
    // Fungsi untuk menggambar Gambar Matriks
    function DrawMatrixImage($imagePath)
    {
        $this->SectionTitle(" MATRIK PERUBAHAN "); 
        
        $actualImagePath = __DIR__ . '/../assets/image.png';

        if (file_exists($actualImagePath)) {
            $maxWidth = 185; 
            $current_y = $this->GetY(); 
            $newWidth = $maxWidth; 
            $newHeight = 55; 
            
            if ($current_y + $newHeight + 15 > $this->h - $this->bMargin) { 
                $this->AddPage(); 
                $current_y = 45; 
                $this->SetY($current_y); 
                $this->SectionTitle("MATREK PERUBAHAN"); 
                $current_y = $this->GetY(); 
            }

            $this->Image($actualImagePath, 12.5, $current_y, $newWidth, $newHeight); 
            $this->SetY($current_y + $newHeight + 1); 
            
            $this->SetFont('Arial', '', 8); 
            $this->SetX(15); 
            $this->Cell(0, 4, "NOTE :", 0, 1, 'L'); 
            $this->SetX(15); 
            $this->Cell(0, 4, "O : Perlu Revisi", 0, 1, 'L'); 
            $this->SetX(15); 
            $this->Cell(0, 4, "X : Tidak Perlu Revisi", 0, 1, 'L'); 

        } else {
            $this->SetFont('Arial', 'B', 10); 
            $this->SetTextColor(255, 0, 0); 
            $this->Cell(0, 10, "Error: Gambar Matriks tidak ditemukan di " . $actualImagePath, 1, 1, 'C'); 
            $this->SetTextColor(0, 0, 0); 
        }
    }
}

// 6. Mulai Buat PDF
$pdf = new PDF_Usulan('P', 'mm', 'A4'); // P = Portrait
$pdf->AliasNbPages();
$pdf->SetKodeUsul(safeText($data['KODE_USUL']));

// --- SET NAMA PLANT DINAMIS UNTUK JUDUL ---
$nama_plant_judul = ($active_plant == 'p2') ? 'PLANT 2' : 'PLANT 1';
$pdf->SetPlantName($nama_plant_judul);
// ------------------------------------------

$pdf->AddPage();
$pdf->SetAutoPageBreak(false, 15); 
$pdf->SetMargins(10, 10, 10);

// === GAMBAR FIELD DATA ===
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

$pdf->DataField('Isi Revisi', safeText($data['ISI_REVISI']), 90.5); 
$pdf->DataField('Alasan Revisi', safeText($data['ALASAN_REVISI']), 97); 

$pdf->DataField('PLAN DATE', safeDate($data['PLANT_DATE']), 103.5); 
$pdf->DataField('ACTUAL DATE', safeDate($data['TARGET_DATE']), 110); 

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

$pdf->SetY($y_chk_start + 14); 

// === BAGIAN CATATAN ===
$pdf->DrawNotesAndSubmissionSection(); 

// === BAGIAN TANDA TANGAN ===
$pdf->DrawSignatureSection();

// === BAGIAN MATRIKS GAMBAR ===
$pdf->DrawMatrixImage('../assets/image.png');

// 7. Output PDF
$pdf->Output('I', 'Usulan_' . $kode_usul_cetak . '.pdf');

?>