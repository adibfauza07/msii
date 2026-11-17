<?php
if (session_id() == "") {
    session_start();
}

require_once dirname(__DIR__) . "/config/db_plant1.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
}

function getv($name, $default = "") {
    return isset($_GET[$name]) ? trim((string)$_GET[$name]) : $default;
}

function sql_error_text() {
    return print_r(sqlsrv_errors(), true);
}

// Format tanggal seperti di screenshot: 14-August-2026
function fmt_date_icl($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-F-Y");
    }
    if ($value == "" || $value === null) {
        return "";
    }
    $ts = strtotime((string)$value);
    return $ts === false ? "" : date("d-F-Y", $ts);
}

$rcvId = intval(getv("id", "0"));

if ($rcvId <= 0) {
    die("Data Receive belum dipilih atau ID tidak valid.");
}

// =========================================================================
// QUERY DIPERBARUI: Menggunakan SUM() dan GROUP BY agar item kembar 
// langsung dijumlahkan Quantity-nya oleh Database.
// =========================================================================
$sql = "
    SELECT
        R.RCV_ID,
        R.RCV_NO,
        R.RCV_DATE,
        R.RCV_DONO,
        R.RCV_PIC,
        S.SUP_CODE,
        S.SUP_COMP,
        I.ITEM_CODE,
        I.ITEM_NAME,
        I.ITEM_UNIT,
        SUM(RD.RCVD_QTY) AS RCVD_QTY
    FROM dbo.RECEIVE R
    INNER JOIN dbo.RECEIVE_DETAIL RD ON R.RCV_ID = RD.RCV_ID
    INNER JOIN dbo.ITEMS I ON RD.ITEM_ID = I.ITEM_ID
    INNER JOIN dbo.SUPPLIER S ON R.SUP_ID = S.SUP_ID
    WHERE R.RCV_ID = ?
    GROUP BY 
        R.RCV_ID,
        R.RCV_NO,
        R.RCV_DATE,
        R.RCV_DONO,
        R.RCV_PIC,
        S.SUP_CODE,
        S.SUP_COMP,
        I.ITEM_CODE,
        I.ITEM_NAME,
        I.ITEM_UNIT
    ORDER BY I.ITEM_CODE ASC
";

$stmt = sqlsrv_query($conn, $sql, array($rcvId));

if ($stmt === false) {
    die("<pre>Query print ICL OTO error:\n" . sql_error_text() . "</pre>");
}

$rows = array();
$head = null;
$totalQty = 0;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($head === null) {
        $head = $r;
    }
    $totalQty += floatval($r["RCVD_QTY"]);
    $rows[] = $r;
}

if ($head === null) {
    die("Error: Data transaksi tidak ditemukan.");
}

// Batasi maksimal 13 baris per halaman
$linesPerPage = 13; 
$pages = array_chunk($rows, $linesPerPage);
$totalPages = count($pages);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>INCOMING CHECK LIST - <?php echo h($head["RCV_NO"]); ?></title>
    <style>
        /* Pengaturan Dasar Font dan Margin (Tampilan Web) */
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px; 
            color: #000;
            margin: 0;
            padding: 15px;
            background-color: #f9f9f9;
        }

        .page-container {
            width: 100%;
            max-width: 850px; 
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            box-sizing: border-box;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .company-info h3 {
            margin: 0 0 3px 0;
            font-size: 16px;
            font-weight: bold;
        }
        .company-info p {
            margin: 0;
            line-height: 1.3;
            font-size: 12px;
        }
        .time-info {
            font-size: 12px;
            margin-top: 5px;
            text-align: right;
        }

        .doc-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .info-table td {
            border: 1px solid #000;
            padding: 5px 8px;
            vertical-align: top;
        }
        .info-table .col-label {
            font-weight: bold;
            background: #fcfcfc;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 14px; 
        }
        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 6px 8px; 
        }
        .items-table th {
            background-color: #f5f5f5;
            text-align: center;
            font-weight: bold;
        }
        .tengah { text-align: center; }
        .kanan { text-align: right; }

        .signature-section {
            display: flex;
            justify-content: space-around;
            text-align: center;
            margin-bottom: 10px;
            font-size: 12px;
        }
        .signature-box { width: 30%; }
        .signature-space { margin-top: 50px; } 

        .footer-notes {
            font-size: 11px;
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
        }
        .doc-control {
            margin-top: 2px;
            font-size: 11px;
            text-align: left;
        }

        /* Tabel Legend Khusus ICL */
        .legend-table { 
            width: 100%; 
            font-size: 11px; 
            border-collapse: collapse; 
            line-height: 1.2; 
            margin-top: 5px; 
        }
        .legend-table td { 
            padding: 2px 0; 
            vertical-align: top; 
        }

       /* --- SETTING KHUSUS SAAT DIPRINT --- */
        @media print {
            @page {
                margin: 12mm 5mm 2mm 5mm; 
            }
            .no-print { display: none !important; }
            
            body { 
                padding: 0; 
                margin: 0;
                background-color: #fff;
                font-size: 12px;
                line-height: 1.1; 
                -webkit-print-color-adjust: exact;
            }
            
            .page-container { 
                box-shadow: none; 
                padding: 0; 
                width: 100%; 
                max-width: 100%; 
                page-break-inside: avoid;
                page-break-after: always;
                margin-bottom: 0;
            }
            .page-container:last-of-type {
                page-break-after: auto; 
            }
            
            .header-section { margin-bottom: 2px; }
            .doc-title { margin-bottom: 2px; }
            
            .info-table { margin-bottom: 3px; font-size: 13px; } 
            .items-table { margin-bottom: 3px; font-size: 13px; }
            
            .info-table td, .items-table th, .items-table td {
                padding: 2px 4px; 
            }
            
            .signature-section { margin-bottom: 2px; }
            .signature-space { margin-top: 20px; } 
            
            .footer-notes { margin-top: 0px; font-size: 10px; }
            .doc-control { margin-top: 0px; font-size: 10px; }
            .legend-table { font-size: 10px; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.close()" class="btn" style="padding: 6px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; color: #333; border-radius: 4px;">&laquo; Tutup</button>
        <button onclick="window.print()" class="btn" style="padding: 6px 15px; cursor: pointer; margin-left: 10px; font-weight: bold; border: 1px solid #333; background: #f0f0f0; border-radius: 4px;">Print ICL</button>
    </div>

    <?php 
    // LOOPING UNTUK SETIAP HALAMAN
    foreach ($pages as $pageIndex => $pageRows): 
        $pageNumber = $pageIndex + 1;
        $isLastPage = ($pageNumber == $totalPages);
    ?>
    <div class="page-container">
        
        <div class="header-section">
            <div class="company-info">
                 <h3>PT. IMC TEKNO INDONESIA PLANT 1</h3>
                <p>Kawasan Berikat Kota Bukit Indah<br>
                ST-4D Block A-II No.29, Purwakarta,<br>       
                Jawa Barat 41181, Indonesia.
                </p>
            </div>
            <div class="time-info">
                Page <?php echo $pageNumber; ?> of <?php echo $totalPages; ?>
            </div>
        </div>

        <div class="doc-title">
            INCOMING CHECK LIST
        </div>

        <table class="info-table">
            <tr>
                <td class="col-label" style="width: 15%;">Date</td>
                <td class="col-value" style="width: 35%;">: <strong><?php echo h(fmt_date_icl($head["RCV_DATE"])); ?></strong></td>
                <td class="col-label" style="width: 15%;">ICL Number</td>
                <td class="col-value" style="width: 35%;">: <strong><?php echo h($head["RCV_NO"]); ?></strong></td>
            </tr>
            <tr>
                <td class="col-label">Supplier</td>
                <td class="col-value">: <?php echo h($head["SUP_COMP"]); ?></td>
                <td class="col-label">DO Number</td>
                <td class="col-value">: <?php echo h($head["RCV_DONO"]); ?></td>
            </tr>
            <tr>
                <td class="col-label">Dept</td>
                <td class="col-value" colspan="3">: <?php echo h($head["RCV_PIC"]); ?></td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 12%;">CODE</th>
                    <th rowspan="2" style="width: 30%;">NAME</th>
                    <th rowspan="2" style="width: 8%;">UNIT</th>
                    <th rowspan="2" style="width: 10%;">Incoming<br>QTY</th>
                    <th colspan="2" style="width: 10%;">JUDGEMENT</th>
                    <th rowspan="2" style="width: 15%;">PROBLEM</th>
                    <th rowspan="2" style="width: 15%;">RECOMENDATION</th>
                </tr>
                <tr>
                    <th style="width: 5%;">OK</th>
                    <th style="width: 5%;">HOLD</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($pageRows as $r) {
                    $qty = number_format(floatval($r["RCVD_QTY"]), 2, ".", ",");
                ?>
                    <tr>
                        <td><?php echo h($r["ITEM_CODE"]); ?></td>
                        <td><?php echo h($r["ITEM_NAME"]); ?></td>
                        <td class="tengah" style="text-transform: capitalize;"><?php echo h($r["ITEM_UNIT"]); ?></td>
                        <td class="kanan"><?php echo $qty; ?></td>
                        <td></td> <td></td> <td></td> <td></td> 
                    </tr>
                <?php } ?>
                
                <?php if ($isLastPage): ?>
                    <tr style="font-weight:bold; background-color:#f9f9f9;">
                        <td colspan="3" class="kanan">TOTAL :</td>
                        <td class="kanan"><?php echo number_format($totalQty, 2, ".", ","); ?></td>
                        <td colspan="4"></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="kanan" style="font-style: italic;">
                            Bersambung ke halaman berikutnya...
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Signature dipindahkan ke bawah dan disesuaikan style nya -->
        <div class="signature-section">
            <div class="signature-box">
                Checker
                <div class="signature-space">
                    (........................................)
                </div>
            </div>
            <div class="signature-box">
                Receiver
                <div class="signature-space">
                    (........................................)
                </div>
            </div>
        </div>

        <div class="footer-notes">
            <table class="legend-table">
                <tr>
                    <td style="width: 6%;">White</td>
                    <td style="width: 16%;">: Acc + Finnace</td>
                    <td style="width: 6%;">Green</td>
                    <td style="width: 16%;">: Checker</td>
                    <td style="width: 16%;">R : Raw Material</td>
                    <td style="width: 16%;">M : Machine</td>
                </tr>
                <tr>
                    <td>Yellow</td>
                    <td>: Purchasing</td>
                    <td>Pink</td>
                    <td>: Receiver</td>
                    <td>V : Vendor</td>
                    <td>A : ATK</td>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>P : Packing</td>
                    <td>O : Other</td>
                </tr>
            </table>
        </div>
        
        <div class="doc-control">
            FM.CO.01-41 (Revisi 5 : Tgl.1 Mar 26)
        </div>

    </div>
    <?php endforeach; ?>

</body>
</html>