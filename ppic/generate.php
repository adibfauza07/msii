<?php
require '../config/database.php';
require '../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// ambil data POST
$part_code = $_POST['part_code'];
$part_no   = $_POST['part_no'];
$part_name = $_POST['part_name'];
$pb        = $_POST['qty_polibag'];
$bx        = $_POST['qty_box'];
$warna     = $_POST['warna'];

// text QR
$dataQR = "CODE: $part_code\nNO: $part_no\nPB: $pb\nBOX: $bx\nWARNA: $warna";

// lokasi file QR
$qrFile = "../assets/qr_showa/" . $part_code . "_" . time() . ".png";

// buat QR
$qr = QrCode::create($dataQR)->setSize(250);
$writer = new PngWriter();
$writer->write($qr)->saveToFile($qrFile);

// simpan path relatif untuk ditampilkan
$qr_relative = "../assets/qr_showa/" . basename($qrFile);

// simpan database
$sql = "
INSERT INTO barcode_showa
(part_code, part_no, part_name, qty_polibag, qty_box, warna, qr_path)
VALUES (?, ?, ?, ?, ?, ?, ?)
";

$params = [$part_code, $part_no, $part_name, $pb, $bx, $warna, $qr_relative];
q($sql, $params);

echo "<h2>QR Code Berhasil Dibuat</h2>";
echo "<img src='$qr_relative' width='200'><br><br>";

echo "<a href='list.php'>Lihat Data</a>";
?>
