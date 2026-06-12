<?php

// ======================================================================
// KONEKSI DATABASE PLANT SESUAI LOGIN
// ======================================================================
require_once "../config/database_ppic.php";

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
$lot = isset($_GET['lot']) ? $_GET['lot'] : ""; // Menangkap lot dari URL
// Menangkap parameter 'tgl' dari URL
// Jika tanggal di URL kosong atau tidak ada, berikan tanda strip (-) atau tanggal hari ini
$tgl = (isset($_GET['tgl']) && $_GET['tgl'] !== "") ? $_GET['tgl'] : "";

// ambil data dari database
$sql = "SELECT * FROM barcode_showa WHERE id = ?";
$res = sqlsrv_query($conn, $sql, [$id]);
$row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

// variabel data
$part_code  = $row['part_code'];
$part_no    = $row['part_no'];
$part_desc  = $row['part_name'];
$qty        = (int)$row['qty_polibag'];

// Jika lot di URL kosong atau tidak ada, ganti dengan tanda strip (-)
$lot = (isset($_GET['lot']) && $_GET['lot'] !== "") ? $_GET['lot'] : "";

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
function drawLabel($pdf, $x, $y, $w, $h, $qrText, $part_no, $part_desc, $qty, $lot, $tgl)
{
    // 1. Setting Garis Border
    $pdf->SetDrawColor(0, 0, 0); 
    $pdf->SetLineWidth(0.4); 
    $pdf->Rect($x, $y, $w, $h, 'D');

    // 2. Header
    $headerH = 8;
    $pdf->Line($x, $y + $headerH, $x + $w, $y + $headerH);
    $pdf->SetFont('helvetica','B',7);
    $pdf->SetXY($x, $y + 1);
    $pdf->Cell($w, 3, 'V03800', 0, 2, 'C');
    $pdf->Cell($w, 3, 'IMC TEKNO INDONESIA PT.', 0, 0, 'C');

    // 3. QR Code & Garis Vertikal Pemisah
    $qrSize = 18;
    $qrX = $x + 3;
    $qrY = $y + $headerH + 3;
    $pdf->write2DBarcode($qrText, 'QRCODE,H', $qrX, $qrY, $qrSize, $qrSize, [], 'N');

    $lineX = $qrX + $qrSize + 2;
    $pdf->Line($lineX, $y + $headerH, $lineX, $y + $h);

    // 4. Area Teks Utama
    $textX = $lineX + 3;
    $currentY = $y + $headerH + 1; // Mulai tepat di bawah header
    $lineGap = 4;

    // --- Baris Part No ---
    $pdf->SetFont('helvetica','',8);
    $pdf->SetXY($textX, $currentY);
    $pdf->Cell(0, 3, 'Part No', 0, 2, 'L');
    $pdf->SetFont('helvetica','B',11);
    $pdf->Cell(0, 5, $part_no, 0, 2, 'L');

    // --- Baris Part Description & Lot Produksi ---
    $currentY = $pdf->GetY() + 1;
    $pdf->SetFont('helvetica','',7);
    $pdf->SetXY($textX, $currentY);
    $pdf->Cell(35, 3, 'Part Description', 0, 0, 'L');
    $pdf->Cell(0, 3, 'Lot Produksi', 0, 1, 'L');

    $pdf->SetFont('helvetica','B',9);
    $pdf->SetX($textX);
    $pdf->Cell(35, 4, $part_desc, 0, 0, 'L');
    $pdf->Cell(0, 4, $lot, 0, 1, 'L'); // Mencetak variabel $lot

    // --- Baris QTY & Tanggal ---
    $currentY = $pdf->GetY() + 1;
    $pdf->SetFont('helvetica','',7);
    $pdf->SetXY($textX, $currentY);
    $pdf->Cell(35, 3, 'QTY', 0, 0, 'L');
    $pdf->Cell(0, 3, 'Tanggal', 0, 1, 'L');

// --- Baris QTY & Tanggal ---
// ... (kode sebelumnya) ...

$pdf->SetFont('helvetica','B',10);
$pdf->SetX($textX);
$pdf->Cell(35, 5, number_format($qty, 0, '', '') . ' pcs', 0, 0, 'L');
//$pdf->Cell(0, 5, $tgl, 0, 0, 'L'); // Sekarang menggunakan variabel manual dari input
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
            $lot,
            $tgl // Tambahkan ini agar variabel tgl masuk ke fungsi
        );
    }
}

//===========================
// 5. OUTPUT PDF
//===========================
$pdf->Output("label_$part_no.pdf", "I");
