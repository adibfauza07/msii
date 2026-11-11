<?php
if (session_id() == "") {
    session_start();
}

// Sesuaikan dengan koneksi database plant yang Anda gunakan
require_once dirname(__DIR__) . "/config/db_plant2.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function fmt_date_view($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-M-Y");
    }
    if ($value == "" || $value === null) return "";
    $ts = strtotime((string)$value);
    if ($ts === false) return "";
    return date("d-M-Y", $ts);
}

// Mengambil parameter short_no dari get (misal: ?no=261100)
$shortNo = isset($_GET['no']) ? trim($_GET['no']) : '';

// Jika tidak ada nomor, kita bisa berikan pesan error
if ($shortNo == "") {
    die("Nomor ICL / Schedule tidak ditemukan.");
}

// 1. QUERY HEADER (Mengambil Data Receive & Supplier)
$sqlH = "
    SELECT TOP 1 
        R.RCV_NO, 
        R.RCV_DATE, 
        R.RCV_PIC, 
        S.SUP_COMP
    FROM dbo.RECEIVE R
    LEFT JOIN dbo.SUPPLIER S ON R.SUP_ID = S.SUP_ID
    WHERE R.RCV_NO LIKE ?
";
$stmtH = sqlsrv_query($conn, $sqlH, array($shortNo . "%"));
$header = array("RCV_NO" => "", "RCV_DATE" => "", "RCV_PIC" => "", "SUP_COMP" => "");

if ($stmtH !== false) {
    $rh = sqlsrv_fetch_array($stmtH, SQLSRV_FETCH_ASSOC);
    if ($rh) {
        $header["RCV_NO"] = trim((string)$rh["RCV_NO"]);
        $header["RCV_DATE"] = fmt_date_view($rh["RCV_DATE"]);
        $header["RCV_PIC"] = trim((string)$rh["RCV_PIC"]);
        $header["SUP_COMP"] = trim((string)$rh["SUP_COMP"]);
    }
}

// 2. QUERY DETAIL ITEM (Tabel sch_receive_detail)
$sqlD = "
    SELECT 
        I.ITEM_CODE, 
        I.ITEM_NAME, 
        I.ITEM_UNIT, 
        D.RCVD_QTY
    FROM dbo.sch_receive_detail D
    INNER JOIN dbo.RECEIVE R ON D.RCV_ID = R.RCV_ID
    LEFT JOIN dbo.ITEMS I ON D.ITEM_ID = I.ITEM_ID
    WHERE R.RCV_NO LIKE ?
    ORDER BY I.ITEM_CODE
";
$stmtD = sqlsrv_query($conn, $sqlD, array($shortNo . "%"));
$details = array();
$totalQty = 0;

if ($stmtD !== false) {
    while ($rd = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) {
        $details[] = $rd;
        $totalQty += floatval($rd["RCVD_QTY"]);
    }
}

// Jika data dept (PIC) kosong, berikan default text
$deptName = $header["RCV_PIC"] != "" ? $header["RCV_PIC"] : "PPIC";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Schedule Material - <?php echo h($header["RCV_NO"]); ?></title>
    <style>
        /* Setup Print Area Kertas A4 */
        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 13px;
            color: #000;
            background-color: #fff;
            margin: 0;
            padding: 0;
            line-height: 1.3;
        }

        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }

        /* HEADER COMPANY */
        .company-name {
            font-weight: bold;
            font-size: 15px;
            text-decoration: underline;
            text-transform: uppercase;
        }
        
        .report-title {
            font-weight: bold;
            font-size: 16px;
            margin-top: 5px;
            letter-spacing: 0.5px;
        }

        .company-address {
            margin-top: 5px;
            font-size: 12px;
        }

        /* INFORMASI DOKUMEN */
        .info-table {
            width: 100%;
            margin-top: 15px;
            margin-bottom: 5px;
            border-collapse: collapse;
            font-weight: bold;
            font-size: 13px;
        }
        .info-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        /* TABEL DATA */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            font-size: 12px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 3px 5px;
        }
        .data-table th {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .row-total td {
            border: none !important;
            font-weight: bold;
            padding-top: 5px;
        }

        /* FOOTER / TTD */
        .signature-area {
            margin-top: 25px;
            font-weight: bold;
            font-size: 13px;
        }

        .form-code {
            margin-top: 20px;
            font-size: 12px;
        }

        /* Menyembunyikan tombol saat di-print */
        @media print {
            .no-print {
                display: none !important;
            }
        }
        
        .btn-print {
            padding: 8px 15px;
            background-color: #007bff;
            color: #fff;
            border: none;
            cursor: pointer;
            margin-bottom: 20px;
            font-family: Arial, sans-serif;
            font-size: 14px;
            border-radius: 4px;
        }
        .btn-print:hover { background-color: #0056b3; }
    </style>
</head>
<body>

<div class="container">
    
    <!-- Tombol Print (Akan hilang saat kertas dicetak) -->
    <button class="btn-print no-print" onclick="window.print()">🖨️ Cetak Halaman</button>

    <!-- KOP SURAT -->
    <div class="company-name">PT.IMC TEKNO INDONESIA PLANT 1</div>
    <div class="report-title">SCHEDULE MATERIAL</div>
    <div class="company-address">
                    Kawasan Berikat Kota Bukit Indah<br>
                    ST-4D Block A-II No.29, Purwakarta,<br>
                    Jawa Barat 41181, Indonesia.<br>
    </div>

    <!-- INFO DOKUMEN -->
    <table class="info-table">
        <tr>
            <td style="width: 100px;">Schedule Date</td>
            <td style="width: 10px;">:</td>
            <td><?php echo h($header["RCV_DATE"]); ?></td>
            <td style="width: 80px;"></td>
            <td style="width: 10px;"></td>
            <td></td>
        </tr>
        <tr>
            <td>Supplier</td>
            <td>:</td>
            <td><?php echo h($header["SUP_COMP"]); ?></td>
            <td>ICL Number</td>
            <td>:</td>
            <td><?php echo h($header["RCV_NO"]); ?></td>
        </tr>
        <tr>
            <td>Dept</td>
            <td>:</td>
            <td><?php echo h($deptName); ?></td>
            <td colspan="3"></td>
        </tr>
    </table>

    <!-- TABEL UTAMA -->
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 12%;">CODE</th>
                <th rowspan="2">NAME</th>
                <th rowspan="2" style="width: 5%;">UNIT</th>
                <th rowspan="2" style="width: 10%;">Incoming<br>QTY</th>
                <th colspan="2" style="width: 12%;">JUDGEMENT</th>
                <th rowspan="2" style="width: 15%;">PROBLEM</th>
                <th rowspan="2" style="width: 17%;">RECOMENDATION</th>
            </tr>
            <tr>
                <th style="width: 6%;">OK</th>
                <th style="width: 6%;">HOLD</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if (count($details) > 0) {
                foreach ($details as $row) { 
                    $qty = floatval($row["RCVD_QTY"]);
            ?>
                <tr>
                    <td style="text-align: center;"><?php echo h($row["ITEM_CODE"]); ?></td>
                    <td><?php echo h($row["ITEM_NAME"]); ?></td>
                    <td style="text-align: center;"><?php echo h($row["ITEM_UNIT"]); ?></td>
                    <td style="text-align: right;"><?php echo number_format($qty, 0, ".", ","); ?></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            <?php 
                } 
            } else { 
            ?>
                <!-- Jika kosong, cetak baris kosong untuk menjaga layout -->
                <tr>
                    <td>&nbsp;</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            <?php } ?>
            
            <!-- BARIS TOTAL (Tanpa Border) -->
            <tr class="row-total">
                <td colspan="3" style="text-align: right; letter-spacing: 2px;">TOTAL :</td>
                <td style="text-align: right;"><?php echo number_format($totalQty, 0, ".", ","); ?></td>
                <td colspan="4"></td>
            </tr>
        </tbody>
    </table>

    <!-- TANDA TANGAN -->
    <div class="signature-area">
        Yang Membuat Schedule<br><br><br><br><br>
        (_______________________)
    </div>

    <!-- KODE FORM -->
    <div class="form-code">
        FM.CO.00-131
    </div>

</div>

<!-- Auto Print Saat Halaman Terbuka (Opsional) -->
<script>
    window.onload = function() {
        // Hapus tanda komentar pada baris di bawah ini jika ingin dialog Print langsung muncul otomatis
        // window.print();
    };
</script>

</body>
</html>