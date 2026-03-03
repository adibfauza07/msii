<?php
// ====================================================
//  REPORT LIST ALL USULAN (PLANT 2)
//  Versi 4.6: Perbaikan Tumpang Tindih (MultiCell XY)
// ====================================================

// 1. Load Koneksi Database
require_once '../config/database.php'; // Koneksi P2

// 2. Load Library TCPDF
require_once '../assets/tcpdf_min/tcpdf.php'; 

// === 3. Ambil Parameter filter ===
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$cust_id    = isset($_GET['cust_id']) ? $_GET['cust_id'] : '';
$kode_usul  = isset($_GET['kode_usul']) ? $_GET['kode_usul'] : '';
$request_by = isset($_GET['request_by']) ? $_GET['request_by'] : '';
$remark     = isset($_GET['remark']) ? $_GET['remark'] : '';

$filter_text = "Periode: " . date('d/m/Y', strtotime($start_date)) . " s/d " . date('d/m/Y', strtotime($end_date));

// === 4. Query utama ===
$sql = "
SELECT 
    U.KODE_USUL, U.ISSUE_DATE, C.CUST_COMP, I.ITEM_NO, I.ITEM_NAME, U.PJ, U.ISI_REVISI, U.REMARKS
FROM 
    USULAN_PERUBAHAN U
LEFT JOIN 
    CUST C ON C.CUST_ID = U.CUST_ID
LEFT JOIN 
    ITEMS I ON I.ITEM_ID = U.ITEM_ID
WHERE 
    U.ISSUE_DATE BETWEEN ? AND ?";
$params = [$start_date, $end_date];

if (!empty($cust_id)) {
    $sql .= " AND U.CUST_ID = ?";
    $params[] = $cust_id;
    $filter_text .= ", Customer";
}
if (!empty($kode_usul)) {
    $sql .= " AND U.KODE_USUL LIKE ?";
    $params[] = "%".$kode_usul."%";
    $filter_text .= ", Kode";
}
if (!empty($request_by)) {
    $sql .= " AND U.PJ = ?";
    $params[] = $request_by;
    $filter_text .= ", Request";
}
if (!empty($remark)) {
    $sql .= " AND U.REMARKS = ?";
    $params[] = $remark;
    $filter_text .= ", Remark";
}

$sql .= " ORDER BY U.ISSUE_DATE DESC, U.KODE_USUL DESC";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die("Error Query: <pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$data_rows = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data_rows[] = $row;
}

// === 5. Buat Class PDF kustom (TCPDF) ===
class MYPDF extends TCPDF {
    
    public $filterText = ''; 
    public $headerTitles = [];
    public $headerWidths = [];

    //Page header
    public function Header() {
        $this->SetFont('helvetica', 'B', 11);
        $this->Cell(0, 5, 'PT. IMC TEKNO INDONESIA', 0, 1, 'L');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 5, 'DCC DEPT', 0, 1, 'L');
        
        $this->SetFont('helvetica', '', 9);
        $this->SetY(10); 
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
        
        $this->Ln(5); 
        
        $this->SetFont('helvetica', 'B', 15);
        $this->Cell(0, 10, 'LIST ALL USULAN PERUBAHAN', 0, 1, 'C');
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor(217, 83, 79); 
        $this->Cell(0, 7, 'PLANT 2', 0, 1, 'C');
        
        $this->SetFont('helvetica', 'I', 9);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 5, $this->filterText, 0, 1, 'C');
        $this->Ln(5);
        
        $this->SetFont('helvetica', 'B', 7); 
        $this->SetFillColor(230, 230, 230);
        $this->SetTextColor(0);
        $this->SetLineWidth(0.3);
        
        $y_start = $this->GetY();
        $x_start = $this->GetX(); 

        $this->setCellPaddings(1, 1, 1, 1); 

        for ($i = 0; $i < count($this->headerTitles); $i++) {
            // (DIUBAH) Hapus SetXY
            // $this->SetXY($x_start, $y_start); 
            
            // (DIUBAH) Masukkan X dan Y langsung ke MultiCell
            $this->MultiCell(
                $this->headerWidths[$i], 
                7, 
                $this->headerTitles[$i], 
                1, 
                'C', 
                true, 
                0, // $ln=0 (pindah ke kanan)
                $x_start, // $x (POSISI X EKSPLISIT)
                $y_start, // $y (POSISI Y EKSPLISIT)
                true, 0, false, true, 7, 'M'
            ); 
            $x_start += $this->headerWidths[$i];
        }
        $this->Ln(7); // Pindah baris
    }

    //Page footer
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }

    // Fungsi untuk menggambar baris data
    public function DrawRow($data, $widths, $aligns) {
        $row_height = 0;
        
        $this->SetFont('helvetica', '', 7); 
        $this->setCellPaddings(1, 1, 1, 1);
        
        for ($i = 0; $i < count($data); $i++) {
            $text = $data[$i];
            $width = $widths[$i];
            
            $text_width = $width - $this->cell_padding['L'] - $this->cell_padding['R'];
            
            $lines = $this->getNumLines($text, $text_width); 
            
            $height = $lines * 3.5; 
            
            if ($height > $row_height) {
                $row_height = $height;
            }
        }
        $row_height = max($row_height, 5); 

        $this->CheckPageBreak($row_height);

        $y_start = $this->GetY();
        $x_start = $this->GetX();
        
        for ($i = 0; $i < count($data); $i++) {
            // (DIUBAH) Hapus SetXY
            // $this->SetXY($x_start, $y_start);
            
            // (DIUBAH) Masukkan X dan Y langsung ke MultiCell
            $this->MultiCell(
                $widths[$i],
                $row_height,
                $data[$i],
                1,
                $aligns[$i],
                0,
                0, // $ln=0 (pindah ke kanan)
                $x_start, // $x (POSISI X EKSPLISIT)
                $y_start, // $y (POSISI Y EKSPLISIT)
                true, 0, false, true, $row_height, 'M'
            );
            
            $x_start += $widths[$i];
        }
        $this->Ln($row_height);
    }
}

// === 6. Buat PDF ===
$pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false); 
$pdf->SetCreator('ERP System');
$pdf->SetAuthor('DCC Dept');
$pdf->SetTitle('List All Usulan Perubahan P2');
$pdf->SetMargins(10, 40, 10);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->filterText = $filter_text;

$pdf->headerTitles = ['No', 'Kode Usul', 'Issue Date', 'Customer', 'Part No', 'Part Name', 'Request By', 'Isi Revisi', 'Remark'];
$pdf->headerWidths = [10, 20, 20, 45, 30, 45, 20, 57, 30]; // Total 277mm
$col_aligns = ['C', 'L', 'L', 'L', 'L', 'L', 'L', 'L', 'L'];

$pdf->AddPage();

// === 7. Loop Data dan Gambar Baris ===
if (empty($data_rows)) {
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(array_sum($pdf->headerWidths), 10, 'Tidak ada data yang ditemukan.', 1, 1, 'C');
} else {
    $no = 1;
    foreach ($data_rows as $row) {
        $data = [
            $no++,
            htmlspecialchars($row['KODE_USUL']),
            ($row['ISSUE_DATE'] ? $row['ISSUE_DATE']->format('Y-m-d') : '-'),
            htmlspecialchars($row['CUST_COMP']),
            htmlspecialchars($row['ITEM_NO']),
            htmlspecialchars($row['ITEM_NAME']),
            htmlspecialchars($row['PJ']),
            trim($row['ISI_REVISI']), 
            trim($row['REMARKS'])
        ];
        
        $pdf->DrawRow($data, $pdf->headerWidths, $col_aligns);
    }
}

// === 8. Output PDF ===
$pdf->Output('List_All_Usulan_P2.pdf', 'I');

exit();
?>