<?php
// ====================================================
//  REPORT LIST OPEN USULAN (PLANT 1) - NEW FORMAT
//  Layout meniru Screenshot (Mirip Crystal Report)
// ====================================================

require_once '../config/Database_p1.php'; 
require_once '../assets/tcpdf_min/tcpdf.php'; 

// === 1. Ambil Parameter ===
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$cust_id    = isset($_GET['cust_id']) ? $_GET['cust_id'] : '';
$kode_usul  = isset($_GET['kode_usul']) ? $_GET['kode_usul'] : '';
$request_by = isset($_GET['request_by']) ? $_GET['request_by'] : '';
$remark     = isset($_GET['remark']) ? $_GET['remark'] : '';

// === 2. Query Data Lengkap (Termasuk Checkbox & Judul Dok) ===
$sql = "
SELECT 
    U.KODE_USUL, U.ISSUE_DATE, U.JUDUL_DOK, U.ISI_REVISI, U.REVISI_1,
    U.PJ, U.PLANT_DATE, U.TARGET_DATE,
    I.ITEM_NO, I.ITEM_NAME,
    U.QCPC, U.IS_STD, U.FMEA, U.WI, U.STD_PACK, U.CHECK_POINT
FROM 
    USULAN_PERUBAHAN U
LEFT JOIN 
    ITEMS I ON I.ITEM_ID = U.ITEM_ID
WHERE 
    (U.ISSUE_DATE BETWEEN ? AND ?)
    AND (U.REMARKS NOT LIKE '%CLOSE%' OR U.REMARKS IS NULL) 
";

$params = [$start_date, $end_date];

if (!empty($cust_id)) { $sql .= " AND U.CUST_ID = ?"; $params[] = $cust_id; }
if (!empty($kode_usul)) { $sql .= " AND U.KODE_USUL LIKE ?"; $params[] = "%".$kode_usul."%"; }
if (!empty($request_by)) { $sql .= " AND U.PJ = ?"; $params[] = $request_by; }
if (!empty($remark)) { $sql .= " AND U.REMARKS = ?"; $params[] = $remark; }

$sql .= " ORDER BY U.ISSUE_DATE DESC, U.KODE_USUL DESC";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) die(print_r(sqlsrv_errors(), true));

$data_rows = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data_rows[] = $row;
}

// === 3. Class PDF Custom ===
class MYPDF extends TCPDF {
    
    // Header Halaman Sesuai Screenshot
    public function Header() {
        $this->SetFont('helvetica', 'B', 11);
        $this->SetXY(10, 10);
        $this->Cell(100, 5, 'P.T. IMC TEKNO INDONESIA', 0, 1, 'L');
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(100, 5, 'DCC DEPT', 0, 0, 'L');
        
        // Judul Tengah
        $this->SetXY(0, 15);
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 5, 'LIST USULAN PERUBAHAN', 0, 0, 'C');
        
        // Page Number Kanan Atas
        $this->SetXY(-30, 10);
        $this->SetFont('helvetica', '', 9);
        $this->Cell(20, 5, 'Page '.$this->getAliasNumPage().' of '.$this->getAliasNbPages(), 0, 1, 'R');
        
        $this->Ln(15); // Jarak ke Tabel
        
        // === GAMBAR HEADER TABEL ===
        $this->SetFont('helvetica', 'B', 7); // Font Kecil untuk Header Tabel
        $this->SetLineWidth(0.1);
        
        $x = 10;
        $y = $this->GetY();
        $h_row1 = 5; // Tinggi baris header 1 (Item Doc Support)
        $h_row2 = 8; // Tinggi baris header 2 (Sub kolom)
        $h_full = $h_row1 + $h_row2; // Tinggi header normal (merged)
        
        // 1. No
        $this->MultiCell(10, $h_full, 'No', 1, 'C', 0, 0, $x, $y, true, 0, false, true, $h_full, 'M'); $x += 10;
        
        // 2. Request By / Item Name
        $this->MultiCell(65, $h_full, 'Request By   Item Name', 1, 'L', 0, 0, $x, $y, true, 0, false, true, $h_full, 'M'); $x += 65;
        
        // 3. Judul Dokumen
        $this->MultiCell(35, $h_full, 'Judul Dokumen', 1, 'L', 0, 0, $x, $y, true, 0, false, true, $h_full, 'M'); $x += 35;
        
        // 4. Usulan Perubahan
        $this->MultiCell(55, $h_full, 'Usulan Perubahan', 1, 'L', 0, 0, $x, $y, true, 0, false, true, $h_full, 'M'); $x += 55;
        
        // 5. Revisi
        $this->MultiCell(10, $h_full, 'Revisi', 1, 'C', 0, 0, $x, $y, true, 0, false, true, $h_full, 'M'); $x += 10;
        
        // 6. ITEM DOCUMENT SUPPORT (Merged Cell Header)
        $w_check = 36; // 6 kotak x 6mm
        $this->Cell($w_check, $h_row1, 'ITEM DOCUMENT SUPPORT', 1, 0, 'C');
        
        // 7. Plan Date (Merged Vertical)
        $this->MultiCell(17, $h_full, "PLAN\nDATE", 1, 'C', 0, 0, $x + $w_check, $y, true, 0, false, true, $h_full, 'M');
        
        // 8. Finish Date (Merged Vertical)
        $this->MultiCell(17, $h_full, "FINISH\nDATE", 1, 'C', 0, 0, $x + $w_check + 17, $y, true, 0, false, true, $h_full, 'M');
        
        // 9. Status (Merged Vertical)
        $this->MultiCell(17, $h_full, 'STATUS', 1, 'C', 0, 0, $x + $w_check + 17 + 17, $y, true, 0, false, true, $h_full, 'M');
        
        // --- Sub Header Checkboxes ---
        $this->SetXY($x, $y + $h_row1);
        $w_sub = 6; // Lebar per kolom checkbox
        $this->Cell($w_sub, $h_row2, 'QCPC', 1, 0, 'C');
        $this->Cell($w_sub, $h_row2, 'IS', 1, 0, 'C');
        $this->Cell($w_sub, $h_row2, 'FMEA', 1, 0, 'C');
        $this->Cell($w_sub, $h_row2, 'WI', 1, 0, 'C');
        $this->MultiCell($w_sub, $h_row2, "STD\nPACK", 1, 'C', 0, 0, '', '', true, 0, false, true, $h_row2, 'M');
        $this->MultiCell($w_sub, $h_row2, "CHECK\nPONT", 1, 'C', 0, 0, '', '', true, 0, false, true, $h_row2, 'M');
        
        $this->Ln(8); // Pindah baris setelah header selesai
    }
    
    public function Footer() {
        // Kosongkan footer default garis hitam
    }

    // Fungsi Gambar Checkbox Merah
    public function DrawCheckbox($isChecked, $x, $y, $w, $h) {
        $this->SetLineStyle(array('width' => 0.3, 'color' => array(255, 0, 0))); // Merah
        // Posisi kotak di tengah sel
        $box_size = 3;
        $x_center = $x + ($w - $box_size) / 2;
        $y_center = $y + ($h - $box_size) / 2;
        
        $this->Rect($x_center, $y_center, $box_size, $box_size);
        
        if ($isChecked) {
            // Jika checked, bisa diarsir atau disilang (di screenshot kotak kosong merah)
            // Kita biarkan kosong merah sesuai screenshot
        }
        
        $this->SetLineStyle(array('width' => 0.1, 'color' => array(0, 0, 0))); // Reset Hitam
    }
}

// === 4. Inisialisasi PDF ===
$pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(10, 28, 10); // Top margin disesuaikan agar pas di bawah header
$pdf->SetAutoPageBreak(TRUE, 10);
$pdf->SetFont('helvetica', '', 8);
$pdf->AddPage();

// === 5. Render Data ===
$no = 1;

foreach ($data_rows as $row) {
    // Siapkan Data
    $issue_date = $row['ISSUE_DATE'] ? $row['ISSUE_DATE']->format('d-M-Y') : '-';
    $plan_date = $row['PLANT_DATE'] ? $row['PLANT_DATE']->format('d-M-y') : '-';
    $finish_date = $row['TARGET_DATE'] ? $row['TARGET_DATE']->format('d-M-y') : '-';
    
    // Format Kolom 2 (HTML Multi-line)
    // Request By, Issue Date, Part Name, Kode Usul, Part No
    $col2_html = '<b>' . htmlspecialchars($row['PJ']) . '</b><br>' .
                 'Issue Date : ' . $issue_date . '<br>' .
                 htmlspecialchars($row['ITEM_NAME']) . '<br>' .
                 '<span style="color:blue; text-decoration:underline;">' . htmlspecialchars($row['KODE_USUL']) . '</span>   ' . 
                 '<span style="color:blue;">' . htmlspecialchars($row['ITEM_NO']) . '</span>';

    // Hitung Tinggi Baris Otomatis berdasarkan konten terbanyak
    $pdf->startTransaction();
    $start_page = $pdf->getPage();
    
    // Simulasi tulis untuk dapat tinggi
    $w2 = 65; // Lebar kolom 2
    $w3 = 35; // Lebar kolom 3
    $w4 = 55; // Lebar kolom 4
    
    $h2 = $pdf->getStringHeight($w2, $col2_html, false, true, '', 1);
    $h3 = $pdf->getStringHeight($w3, $row['JUDUL_DOK']);
    $h4 = $pdf->getStringHeight($w4, $row['ISI_REVISI']);
    
    $row_height = max($h2, $h3, $h4, 18); // Min tinggi 18mm biar rapi
    
    $pdf->rollbackTransaction(true);
    
    // Cek Page Break manual agar tidak potong baris
    if ($pdf->GetY() + $row_height > $pdf->getPageHeight() - 10) {
        $pdf->AddPage();
    }

    $y_curr = $pdf->GetY();
    $x_curr = 10;

    // 1. No
    $pdf->MultiCell(10, $row_height, $no++, 1, 'C', 0, 0, $x_curr, $y_curr); $x_curr += 10;
    
    // 2. Request Info (HTML)
    $pdf->writeHTMLCell($w2, $row_height, $x_curr, $y_curr, $col2_html, 1, 0, 0, true, 'L'); $x_curr += $w2;
    
    // 3. Judul Dok
    $pdf->MultiCell($w3, $row_height, $row['JUDUL_DOK'], 1, 'L', 0, 0, $x_curr, $y_curr); $x_curr += $w3;
    
    // 4. Usulan
    $pdf->MultiCell($w4, $row_height, $row['ISI_REVISI'], 1, 'L', 0, 0, $x_curr, $y_curr); $x_curr += $w4;
    
    // 5. Revisi
    $pdf->MultiCell(10, $row_height, $row['REVISI_1'], 1, 'C', 0, 0, $x_curr, $y_curr); $x_curr += 10;
    
    // 6. Checkboxes (Looping 6 kolom)
    $checks = ['QCPC', 'IS_STD', 'FMEA', 'WI', 'STD_PACK', 'CHECK_POINT'];
    $w_chk = 6;
    foreach ($checks as $field) {
        // Gambar Border Cell Dulu
        $pdf->Cell($w_chk, $row_height, '', 1, 0);
        
        // Gambar Kotak Merah di dalamnya
        $isChecked = ($row[$field] == 1);
        // Posisi X mundur sedikit karena Cell sudah memajukan kursor
        // Kita pakai GetX() - width
        $pdf->DrawCheckbox($isChecked, $pdf->GetX() - $w_chk, $y_curr, $w_chk, $row_height);
    }
    $x_curr += ($w_chk * 6); // Update X manual karena loop cell sudah memajukan
    
    // 7. Plan Date
    $pdf->MultiCell(17, $row_height, $plan_date, 1, 'C', 0, 0, $x_curr, $y_curr); $x_curr += 17;
    
    // 8. Finish Date
    $pdf->MultiCell(17, $row_height, $finish_date, 1, 'C', 0, 0, $x_curr, $y_curr); $x_curr += 17;
    
    // 9. Status (OPEN Merah)
    $pdf->SetTextColor(255, 0, 0);
    $pdf->SetFont('', 'B');
    $pdf->MultiCell(17, $row_height, "OPEN", 1, 'C', 0, 0, $x_curr, $y_curr, true, 0, false, true, $row_height, 'M');
    $pdf->SetTextColor(0);
    $pdf->SetFont('', '');
    
    $pdf->Ln(); // Pindah Baris
}

$pdf->Output('List_Open_Usulan_P1.pdf', 'I');
?>