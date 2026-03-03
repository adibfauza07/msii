<?php
// 1. Koneksi ke Database PLANT 1
require_once '../config/Database_p1.php'; 

// 2. Cek FPDF
$fpdf_path = '../lib/fpdf.php';
if (!file_exists($fpdf_path)) {
    die("<h3 style='color:red;'>Error: Library FPDF tidak ditemukan!</h3>");
}
require_once $fpdf_path;

if (!isset($_GET['kode_usul'])) {
    die("<h3 style='color:red;'>Error: 'kode_usul' tidak diberikan!</h3>");
}
$kode_usul_cetak = $_GET['kode_usul'];

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

function safeDate($dateObj) {
    if ($dateObj && method_exists($dateObj, 'format')) {
        return $dateObj->format('d/m/Y');
    }
    return '-';
}

function safeText($text) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', isset($text) ? $text : '');
}

class PDF_Usulan extends FPDF
{
    private $kodeUsul = '';

    function SetKodeUsul($kode) {
        $this->kodeUsul = $kode;
    }

    function Header()
    {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(95, 5, 'P.T. IMC TEKNO INDONESIA', 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(95, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 1, 'R'); 
        $this->SetFont('Arial', '', 10);
        $this->Cell(95, 5, 'DCC DEPT', 0, 1, 'L'); 
        
        $this->SetFont('Arial', '', 10); 
        $this->Cell(95, 5, $this->kodeUsul, 0, 1, 'L'); 
        $this->Ln(1); 

        $this->SetFont('Arial', 'B', 16); 
        $this->SetFillColor(210, 210, 210); 
        // UBAH JUDUL KE PLANT 1
        $this->Cell(0, 10, 'USULAN PERUBAHAN PLANT 1', 1, 1, 'C', true); 
        $this->Ln(2); 

        $this->Rect(10, 10, 190, 277); 
        $this->Rect(10, 10, 190, 32);  
    }

    function Footer()
    {
        $this->SetY(-15); 
        $this->SetFont('Arial', 'B', 9); 
        $this->Cell(0, 5, 'FM.DC.001-03 (REV 03, 13 NOV 2024)', 0, 1, 'L'); 
    }

    function DataField($label, $data, $y_pos, $x_label = 15, $x_data = 45)
    {
        $this->SetFont('Arial', '', 10); 
        $this->SetXY($x_label, $y_pos); 
        $this->Cell(30, 5, $label, 0, 0, 'L'); 
        $this->Cell(5, 5, ':', 0, 0, 'C'); 
        
        $this->SetFont('Arial', 'B', 10); 
        $this->SetXY($x_data, $y_pos); 
        $this->MultiCell(150, 5, $data, 0, 'L'); 
        
        $this->Line($x_data, $y_pos + 5, 195, $y_pos + 5); 
    }
    
    function SectionTitle($label)
    {
        $this->Ln(1); 
        $this->SetFont('Arial', 'B', 10); 
        $this->SetFillColor(230, 230, 230); 
        $this->Cell(0, 7, "< " . $label . " >", 1, 1, 'C', true); 
        $this->Ln(1); 
    }

    function CheckBox($label, $isChecked, $x, $y)
    {
        $this->SetFont('Arial', '', 10); 
        $this->SetXY($x, $y); 
        
        $this->Rect($x, $y, 4, 4);  
        if ($isChecked) {
            $this->SetFont('Arial', 'B', 10); 
            $this->Cell(4, 4, 'X', 0, 0, 'C'); 
        } else {
            $this->Cell(4, 4, '', 0, 0, 'L'); 
        }
        $this->SetFont('Arial', '', 10); 
        $this->SetX($x + 5); 
        $this->Cell(40, 4, $label, 0, 0, 'L'); 
    }
    
    function DrawNotesAndSubmissionSection() 
    {
        $this->SetFont('Arial', '', 9);
        $y_start = $this->GetY();
        $h_section = 20; 
        $h_title = 6; 
        
        $x_catatan = 15;
        $w_catatan = 105; 
        $this->Rect($x_catatan, $y_start, $w_catatan, $h_section); 
        
        $this->SetXY($x_catatan + 1, $y_start + 1);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(20, 5, "Catatan:", 0, 1, 'L');
        $this->SetFont('Arial', '', 9);
        $this->SetXY($x_catatan + 1, $y_start + 5);
        $this->MultiCell($w_catatan - 2, 4, 
            "Untuk pengajuan perubahan beberapa jenis dokumen dalam waktu sama, " .
            "bisa dengan membuat lampiran Gimbsho."
        , 0, 'L');
        
        $x_diajukan = $x_catatan + $w_catatan; 
        $w_diajukan = 75; 

        $this->Rect($x_diajukan, $y_start, $w_diajukan, $h_section); 
        $this->Rect($x_diajukan, $y_start, $w_diajukan, $h_title);
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY($x_diajukan, $y_start + 1);
        $this->Cell($w_diajukan, 5, "Diajukan oleh", 0, 1, 'C');

        $w_sub_box = $w_diajukan / 2; 
        $h_sub_box = $h_section - $h_title; 
        $this->Rect($x_diajukan, $y_start + $h_title, $w_sub_box, $h_sub_box);
        $this->Rect($x_diajukan + $w_sub_box, $y_start + $h_title, $w_sub_box, $h_sub_box);
        
        $this->SetFont('Arial', '', 8);
        $this->SetXY($x_diajukan, $y_start + $h_section - 5);
        $this->Cell($w_sub_box, 5, "Checked", 0, 0, 'C');
        
        $this->SetXY($x_diajukan + $w_sub_box, $y_start + $h_section - 5);
        $this->Cell($w_sub_box, 5, "Prepared", 0, 0, 'C');

        $this->SetY($y_start + $h_section + 1); 
    }

    function DrawSignatureSection()
    {
        $this->SectionTitle("DISETUJUI"); 
        
        $y_start_sig = $this->GetY(); 
        $h_sig_box = 30; 
        $w_sig_box = 92.5; 
        $h_title_box = 6; 

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


        $this->SetFont('Arial', 'B', 9); 
        $this->Rect(15 + $w_sig_box, $y_start_sig, $w_sig_box, $h_title_box); 
        $this->SetXY(15 + $w_sig_box, $y_start_sig); 
        $this->Cell($w_sig_box, $h_title_box, "MR / DCC", 0, 1, 'C'); 

        $this->Rect(15 + $w_sig_box, $y_start_sig, $w_sig_box, $h_sig_box); 
        $this->Rect(15 + $w_sig_box, $y_start_sig + $h_title_box, $w_sig_box / 2, $h_sig_box - $h_title_box); 
        $this->Rect(15 + $w_sig_box + ($w_sig_box / 2), $y_start_sig + $h_title_box, $w_sig_box / 2, $h_sig_box - $h_title_box); 

        $this->SetFont('Arial', '', 8); 
        $this->SetXY(15 + $w_sig_box, $y_start_sig + $h_sig_box - 5); 
        $this->Cell($w_sig_box / 2, 5, "Approve", 0, 0, 'C'); 
        $this->Cell($w_sig_box / 2, 5, "Checked", 0, 0, 'C'); 
        
        $this->SetY($y_start_sig + $h_sig_box + 1); 
    }
    
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

$pdf = new PDF_Usulan('P', 'mm', 'A4'); 
$pdf->AliasNbPages();
$pdf->SetKodeUsul(safeText($data['KODE_USUL']));
$pdf->AddPage();
$pdf->SetAutoPageBreak(false, 15); 
$pdf->SetMargins(10, 10, 10);

$pdf->DataField('Issue Date', safeDate($data['ISSUE_DATE']), 45);
$pdf->DataField('Customer', safeText($data['CUST_COMP']), 51.5);
$pdf->DataField('Document Name', safeText($data['JUDUL_DOK']), 58);
$pdf->DataField('Part Name', safeText($data['ITEM_NAME']), 64.5);
$pdf->DataField('Part No', safeText($data['ITEM_NO']), 71);
$pdf->DataField('Request By', safeText($data['PJ']), 77.5);

$revisi_text = '';
if ($data['BARU']) $revisi_text = 'BARU';
if ($data['REVISI']) $revisi_text = 'REVISI (No: ' . $data['REVISI_1'] . ')';
$pdf->DataField('Revision', $revisi_text, 84);

$pdf->DataField('Isi Revisi', safeText($data['ISI_REVISI']), 90.5); 
$pdf->DataField('Alasan Revisi', safeText($data['ALASAN_REVISI']), 97); 

$pdf->DataField('PLAN DATE', safeDate($data['PLANT_DATE']), 103.5); 
$pdf->DataField('TARGET DATE', safeDate($data['TARGET_DATE']), 110); 

$pdf->SectionTitle("DOKUMEN TERKAIT PERUBAHAN");

$y_chk_start = $pdf->GetY();
$pdf->CheckBox('Inspection STD', $data['IS_STD'], 15, $y_chk_start);
$pdf->CheckBox('Work Instruction', $data['WI'], 15, $y_chk_start + 7);
$pdf->CheckBox('Check Point', $data['CHECK_POINT'], 65, $y_chk_start);
$pdf->CheckBox('Packing Standard', $data['STD_PACK'], 65, $y_chk_start + 7);
$pdf->CheckBox('FMEA', $data['FMEA'], 115, $y_chk_start);
$pdf->CheckBox('QCPC', $data['QCPC'], 115, $y_chk_start + 7);
$pdf->CheckBox('Setting Parameter', $data['SETTING_PAR'], 155, $y_chk_start);
$pdf->CheckBox('Other', $data['OTHER'], 155, $y_chk_start + 7);

$pdf->SetY($y_chk_start + 14); 

$pdf->DrawNotesAndSubmissionSection(); 
$pdf->DrawSignatureSection();
$pdf->DrawMatrixImage('../assets/image.png');

$pdf->Output('I', 'Usulan_P1_' . $kode_usul_cetak . '.pdf');
?>