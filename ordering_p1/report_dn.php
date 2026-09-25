<?php
require_once __DIR__ . "/../config/database_ordering.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function fmt_date_id($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-M-Y");
    }
    if ($value == "" || $value === null) {
        return "";
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return "";
    }
    return date("d-M-Y", $ts);
}

function fmt_datetime_header() {
    return date("d/m/y H:i:s");
}

$di_id = isset($_GET["DI_ID"]) ? intval($_GET["DI_ID"]) : 0;
if ($di_id <= 0) {
    die("DI_ID tidak valid.");
}

$sql = "
    SET NOCOUNT ON;
    EXEC dbo.SP_RPT_DELIVERYSHEETwPO1 ?
";

$stmt = sqlsrv_query($conn, $sql, array($di_id));
if ($stmt === false) {
    die("<pre>Query SP_RPT_DELIVERYSHEETwPO1 gagal:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$rows = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
}

if (count($rows) == 0) {
    die("Data delivery note tidak ditemukan untuk DI_ID: " . h($di_id));
}

$head = $rows[0];
$diDate = isset($head["DI_DATE"]) ? $head["DI_DATE"] : "";
$dsNo   = isset($head["DI_DSNO"]) ? $head["DI_DSNO"] : "";
$invNo  = isset($head["DI_INVNO"]) ? $head["DI_INVNO"] : "";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Note - <?php echo h($dsNo); ?></title>
    
    <!-- Menggunakan JsBarcode agar hasil barcode jauh lebih pendek (CODE 128) dan 100% akurat saat di-scan -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
    
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        body { margin: 0; background: #9a9a9a; font-family: "Courier New", monospace; font-size: 12px; color: #000; }
        .print-bar { width: 210mm; margin: 10px auto; text-align: right; }
        .print-bar button { padding: 6px 14px; font-size: 11px; cursor: pointer; }
        .paper { width: 210mm; min-height: 297mm; margin: 20px auto; background: #fff; border: 1px solid #000; padding: 7mm; box-sizing: border-box; overflow: hidden; }
        
        .top-row { display: grid; grid-template-columns: 40% 30% 30%; align-items: start; width: 100%; }
        
        .company { font-family: "Times New Roman", Times, serif; font-size: 12px; line-height: 15px; }
        .company-name { font-weight: bold; font-size: 14px; margin-bottom: 2px; font-family: Arial, sans-serif; }
        
        .title { text-align: center; font-family: Arial, sans-serif; font-size: 20px; font-weight: normal; margin-top: 12px; }
        
        /* Layout khusus untuk menampung format SVG barcode */
        .barcode-row { text-align: center; margin-top: 15px; }
        .barcode-row svg { max-width: 100%; height: auto; }

        .right-head { font-size: 11px; line-height: 16px; padding-top: 5px; }
        .right-topline { display: flex; justify-content: space-between; margin-bottom: 10px; }
        
        .info-line { display: grid; grid-template-columns: 80px 1fr; align-items: baseline; margin-bottom: 2px; }
        .info-line .label { text-align: right; padding-right: 5px; }
        .info-line .value { text-align: right; }

        .separator { border-top: 1px solid #000; margin-top: 8px; width: 100%; }
        
        @media print {
            body { background: #fff; }
            .print-bar { display: none; }
            .paper { width: 196mm; min-height: 281mm; margin: 0; border: none; padding: 0; overflow: hidden; }
            .company, .right-head { font-size: 11px; }
        }
    </style>
</head>
<body>
<div class="print-bar">
    <button onclick="window.print()">PRINT</button>
    <button onclick="window.close()">CLOSE</button>
</div>
<div class="paper">
    
    <div class="top-row">
        <div class="company">
            <div class="company-name">P.T. IMC TEKNO INDONESIA</div>
            Kawasan Berikat KOTA BUKIT INDAH ST 1E<br>
            Blok A-II Lot No. 29E, Purwakarta, Jawa Barat<br>
            41181, INDONESIA<br>
            Phone (0264) 351441
        </div>
        
        <div class="title">DELIVERY NOTE</div>
        
        <div class="right-head">
            <div class="right-topline">
                <div><?php echo h(fmt_datetime_header()); ?></div>
                <div>FM.CO.00-05</div>
            </div>
            
            <div class="info-line"><div class="label">Date :</div><div class="value"><?php echo h(fmt_date_id($diDate)); ?></div></div>
            <div class="info-line"><div class="label">DS.No :</div><div class="value"><?php echo h($dsNo); ?></div></div>
            <div class="info-line"><div class="label">INV.No :</div><div class="value"><?php echo h($invNo); ?></div></div>
        </div>
    </div>
    
    <!-- Area SVG tempat JsBarcode merender kodenya -->
    <div class="barcode-row">
        <svg id="barcode"></svg>
    </div>
    
    <!-- Garis pembatas -->
    <div class="separator"></div>

</div>

<!-- Script untuk men-generate barcode secara otomatis -->
<script>
    JsBarcode("#barcode", "<?php echo h($dsNo); ?>", {
        format: "CODE128", // Format ini sangat optimal untuk menyingkat lebar karakter campuran
        width: 1.6,        // Semakin kecil angkanya, semakin rapat dan pendek barcodenya
        height: 45,        // Mengatur tinggi garis barcode
        displayValue: false, // Angka/teks tidak dimunculkan lagi di bawah garis barcode
        margin: 0
    });
</script>
</body>
</html>