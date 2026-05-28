<?php


require_once "../config/db_plant2.php.php";

// TCPDF library
if (file_exists('../tcpdf/tcpdf.php')) {
    require_once('../tcpdf/tcpdf.php');
} elseif (file_exists('../vendor/autoload.php')) {
    require_once('../vendor/autoload.php');
} else {
    die("<b>Error Fatal:</b> File library TCPDF tidak ditemukan.");
}

// Ambil ID
$id = isset($_GET['id']) ? $_GET['id'] : 0;
if(empty($id)) die("Error: Parameter ID tidak ditemukan.");

// Ambil data
$sql = "SELECT * FROM barcode_showa WHERE id = ?";
$res = sqlsrv_query($conn, $sql, array($id));
$row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
if(!$row) die("Error: Data barcode tidak ditemukan.");

// Data variabel
$part_code = isset($row['part_code']) ? $row['part_code'] : '';
$part_no   = isset($row['part_no']) ? $row['part_no'] : '';
$part_desc = isset($row['part_name']) ? $row['part_name'] : '';
$qty       = isset($row['qty']) ? (int)$row['qty'] : 0;
$lot       = isset($row['lot']) ? $row['lot'] : '';
$tanggal   = isset($row['tanggal']) ? $row['tanggal'] : date('Y-m-d');

// PDF setup
$pdf = new TCPDF('L','mm','A4',true,'UTF-8',false);
$pdf->SetMargins(0,0,0);
$pdf->SetAutoPageBreak(false,0);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();

$labelW = 85;
$labelH = 40;
$marginLeft = 7;
$marginTop  = 10;
$cols = 3;
$rows = 4;
$hGap = 13;
$vGap = 6;

$qrText = $part_no;

// Fungsi drawLabel
function drawLabel($pdf, $x, $y, $w, $h, $qrText, $part_no, $part_desc, $qty, $lot, $tanggal) {
    $pdf->SetDrawColor(0,0,0);
    $pdf->SetLineWidth(0.4);
    $pdf->Rect($x,$y,$w,$h,'D');

    $headerH = 8;
    $pdf->Line($x,$y+$headerH,$x+$w,$y+$headerH);

    $pdf->SetFont('helvetica','B',7);
    $pdf->SetXY($x,$y+1);
    $pdf->Cell($w,3,'V03800',0,2,'C');
    $pdf->Cell($w,3,'IMC TEKNO INDONESIA PT.',0,0,'C');

    $qrSize = 20;
    $qrX = $x + 3;
    $qrY = $y + $headerH + 4;
    $pdf->write2DBarcode($qrText,'QRCODE,H',$qrX,$qrY,$qrSize,$qrSize,array(),'N');

    $lineX = $qrX + $qrSize + 2;
    $pdf->Line($lineX,$y+$headerH,$lineX,$y+$h);

    $textX = $lineX + 4;
    $textY = $y + $headerH + 1;
    $lineH = 4;

    $pdf->SetFont('helvetica','',9);
    $pdf->SetXY($textX,$textY);
    $pdf->Cell(0,$lineH,'Part No',0,2,'L');
    $pdf->SetFont('helvetica','B',12);
    $pdf->Cell(0,$lineH+1,$part_no,0,2,'L');

    // Part Description + Lot + Tanggal
    $pdf->SetFont('helvetica','',8);
    $pdf->Cell(40,$lineH,'Part Description',0,0,'L');
    $pdf->Cell(35,$lineH,'Lot Produksi',0,0,'L');
    $pdf->Cell(35,$lineH,'Tanggal',0,2,'L');

    $pdf->SetFont('helvetica','B',10);
    $pdf->Cell(40,$lineH,$part_desc,0,0,'L');
    $pdf->Cell(35,$lineH,$lot,0,0,'L');
    $pdf->Cell(35,$lineH,$tanggal,0,2,'L');

    // QTY
    $pdf->SetFont('helvetica','',9);
    $pdf->Cell(40,$lineH,'QTY',0,0,'L');
    $pdf->SetFont('helvetica','B',11);
    $pdf->Cell(35,$lineH,number_format($qty,0,'','').' pcs',0,2,'L');
}

// Loop 3x4
for($r=0;$r<$rows;$r++){
    for($c=0;$c<$cols;$c++){
        $x = $marginLeft + $c*($labelW+$hGap);
        $y = $marginTop + $r*($labelH+$vGap);
        drawLabel($pdf,$x,$y,$labelW,$labelH,$qrText,$part_no,$part_desc,$qty,$lot,$tanggal);
    }
}

// Output
$pdf->Output("label_$part_no.pdf","I");
?>