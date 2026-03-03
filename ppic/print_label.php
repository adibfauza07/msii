<?php
// ======================================================================
// SECURITY (WAJIB LOGIN & HANYA PPIC P1/P2)
// ======================================================================
require_once "../middleware/Auth.php";
require_once "../middleware/RoleCheck.php";
only(['p1','p2']); // hanya PPIC

// ======================================================================
// KONEKSI DATABASE PLANT SESUAI LOGIN
// ======================================================================
require_once "../config/database.php";

// ======================================================================
// FIX: LOAD LIBRARY TCPDF (TAMBAHAN BARU)
// ======================================================================
// Cek folder library. Asumsi folder 'tcpdf' ada di luar folder 'ppic' (sejajar)
// Path: C:\xampp\htdocs\msii\tcpdf\tcpdf.php
if (file_exists('../tcpdf/tcpdf.php')) {
    require_once('../tcpdf/tcpdf.php');
} 
// Opsi cadangan jika pakai Composer
elseif (file_exists('../vendor/autoload.php')) {
    require_once('../vendor/autoload.php');
} 
// Jika file tidak ketemu sama sekali
else {
    die("<b>Error Fatal:</b> File library TCPDF tidak ditemukan.<br>
         Pastikan folder <code>tcpdf</code> ada di: <code>" . realpath(__DIR__ . '/../') . "\\tcpdf\\tcpdf.php</code>");
}

//===========================
// 1. Ambil ID dari URL
//===========================
$id = isset($_GET['id']) ? $_GET['id'] : 0; // Tambah validasi sedikit biar aman

if(empty($id)) {
    die("Error: Parameter ID tidak ditemukan di URL.");
}

// ambil data dari database
$sql = "SELECT * FROM barcode_showa WHERE id = ?";
// ... LANJUTKAN KODE ASLIMU DI BAWAH SINI ...


//===========================
// 1. Ambil ID dari URL
//===========================
$id = $_GET['id'];

// ambil data dari database
$sql = "SELECT * FROM barcode_showa WHERE id = ?";
$res = sqlsrv_query($conn, $sql, [$id]);
$row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

// variabel data
$part_code  = $row['part_code'];
$part_no    = $row['part_no'];
$part_desc  = $row['part_name'];
$qty        = (int)$row['qty_polibag'];

// LOT (boleh kosong)
$lot = isset($_GET['lot']) ? $_GET['lot'] : "";


//===========================
// 2. SETTING PDF
//===========================
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(0,0,0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();

// ukuran label
$labelW = 85;  // 8.5 cm
$labelH = 40;  // 4.0 cm

$marginLeft = 7;
$marginTop  = 10;

$cols = 3;
$rows = 4;

$pageW = $pdf->getPageWidth();
$pageH = $pdf->getPageHeight();

//$hGap = ($pageW - 2*$marginLeft - $cols*$labelW) / ($cols - 1);
//$vGap = ($pageH - 2*$marginTop  - $rows*$labelH) / ($rows - 1);
$hGap = 13;
$vGap = 6;

// QR Code = hanya part_no
$qrText = $part_no;


//===========================
//===========================
// 3. FUNGSI GAMBAR LABEL
//===========================
function drawLabel($pdf, $x, $y, $w, $h, $qrText, $part_no, $part_desc, $qty, $lot)
{
    // --- [FIX MULAI DARI SINI] ---
    
    // 1. Paksa Warna Garis jadi Hitam Pekat
    $pdf->SetDrawColor(0, 0, 0); 

    // 2. Paksa Ketebalan Garis (0.3mm atau 0.4mm sudah cukup tebal & jelas)
    // Jika tidak di-set, TCPDF kadang meresetnya jadi sangat tipis setelah loop pertama
    $pdf->SetLineWidth(0.4); 

    // 3. Gambar BORDER LUAR dengan parameter 'D' (Draw) agar eksplisit
    $pdf->Rect($x, $y, $w, $h, 'D');

    // 4. HEADER GARIS
    // Kita set ulang ketebalan kalau-kalau ingin memastikan, tapi di atas sudah cukup.
    $headerH = 8;
    $pdf->Line($x, $y + $headerH, $x + $w, $y + $headerH);
    
    // --- [FIX SELESAI] ---


    // HEADER TEKS
    $pdf->SetFont('helvetica','B',7);
    $pdf->SetXY($x, $y + 1);
    $pdf->Cell($w, 3, 'V03800', 0, 2, 'C');
    $pdf->Cell($w, 3, 'IMC TEKNO INDONESIA PT.', 0, 0, 'C');

    // QR CODE
    $qrSize = 20;
    $qrX = $x + 3;
    $qrY = $y + $headerH + 4;

    $pdf->write2DBarcode($qrText, 'QRCODE,H', $qrX, $qrY, $qrSize, $qrSize, [], 'N');

    // GARIS VERTIKAL
    $lineX = $qrX + $qrSize + 2;
    $pdf->Line($lineX, $y + $headerH, $lineX, $y + $h);

    // AREA TEKS
    $textX = $lineX + 4;
    $textY = $y + $headerH + 1;
    $lineH = 4;

    // Part No
    $pdf->SetFont('helvetica','',9);
    $pdf->SetXY($textX, $textY);
    $pdf->Cell(0, $lineH, 'Part No', 0, 2, 'L');

    $pdf->SetFont('helvetica','B',12);
    $pdf->Cell(0, $lineH + 1, $part_no, 0, 2, 'L');

    // ================================
    // PART DESCRIPTION + LOT PRODUKSI
    // ================================
    // Simpan posisi baris Part Description
    $descY = $pdf->GetY();

    // PART DESCRIPTION LABEL
    $pdf->SetFont('helvetica','',8);
    $pdf->SetXY($textX, $descY);
    $pdf->Cell(0, $lineH, 'Part Description        Lot Produksi', 0, 2, 'L');

    // POSISI TEPAT SETELAH LABEL "Part Description"
    $descValueY = $pdf->GetY();

    // Tampilkan nilai Part Description
    $pdf->SetFont('helvetica','B',10);
    $pdf->SetXY($textX, $descValueY);
    $pdf->Cell(40, $lineH, $part_desc, 0, 0, 'L');

   // ================================
    // LOT PRODUKSI (HARUS SEJAJAR DENGAN PART DESCRIPTION)
    // ================================
    $lotX = $textX + 45-15;   // geser kiri 15mm
    $pdf->SetXY($lotX, $descValueY);
    $pdf->Cell(40, $lineH, '', 0, 2, 'L');

    // Value Lot
    $pdf->SetXY($lotX, $pdf->GetY());
    $pdf->Cell(40, $lineH, $lot, 0, 2, 'L');

    // ===========================================
    // TANGGAL (ADA → nilai boleh kosong)
    // ===========================================
    $pdf->SetXY($lotX, $pdf->GetY() - 1);
    $pdf->Cell(0, $lineH, '', 0, 2, 'L');

    

    // ================================
    // QTY — dinaikkan agar jelas
    // ================================
    $pdf->SetFont('helvetica','',9);
    $pdf->SetXY($textX, $pdf->GetY() - 5);
    $pdf->Cell(0, $lineH, 'QTY                      Tanggal', 0, 2, 'L');

    $pdf->SetFont('helvetica','B',11);
    $pdf->Cell(0, $lineH + 2, number_format($qty, 0, '', '') . ' pcs', 0, 2, 'L');
}



//===========================
// 4. GAMBAR 12 LABEL (3×4)
//===========================
for($r = 0; $r < $rows; $r++){
    for($c = 0; $c < $cols; $c++){
        $x = $marginLeft + $c * ($labelW + $hGap);
        $y = $marginTop + $r * ($labelH + $vGap);

        drawLabel(
            $pdf, 
            $x, $y, 
            $labelW, $labelH,
            $qrText,
            $part_no,
            $part_desc,
            $qty,
            $lot
        );
    }
}

//===========================
// 5. OUTPUT PDF
//===========================
$pdf->Output("label_$part_no.pdf", "I");
