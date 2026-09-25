<?php
if (session_id() == "") {
    session_start();
}

require_once dirname(__DIR__) . "/config/db_plant2.php";

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

// Mengambil Data Header dan Detail Receive (Kembali ke Query Asli ICL OTO)
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
        RD.RCVD_QTY
    FROM dbo.RECEIVE R
    INNER JOIN dbo.RECEIVE_DETAIL RD ON R.RCV_ID = RD.RCV_ID
    INNER JOIN dbo.ITEMS I ON RD.ITEM_ID = I.ITEM_ID
    INNER JOIN dbo.SUPPLIER S ON R.SUP_ID = S.SUP_ID
    WHERE R.RCV_ID = ?
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
<html>
<head>
    <meta charset="utf-8">
    <title>INCOMING CHECK LIST - <?php echo h($head["RCV_NO"]); ?></title>
    <style>
        /* Menggunakan font Arial secara mutlak */
        * { font-family: Arial, Helvetica, sans-serif; }
        
        body { 
            font-size: 11px; color: #000; padding: 20px; background: #f0f0f0; margin: 0;
        }
        
        .page-container {
            background: #fff; width: 210mm; min-height: 140mm; 
            margin: 0 auto 20px auto; padding: 15px 20px; box-sizing: border-box;
            position: relative; overflow: hidden;
        }

        .header-container { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 5px; }
        .company-name { font-size: 14px; font-weight: bold; text-decoration: underline; margin-bottom: 2px; line-height: 1.1;}
        .doc-title { font-size: 14px; font-weight: bold; margin-bottom: 2px; line-height: 1.1;}
        .company-addr { font-size: 10px; line-height: 1; color: #000;}
        .page-info { font-size: 10px; font-weight: bold; text-align: right; margin-bottom: 2px; line-height: 1;}

        .sign-box { border-collapse: collapse; font-size: 9px; text-align: center; }
        .sign-box th, .sign-box td { border: 1px solid #000; padding: 2px; width: 70px; }
        .sign-box td { height: 35px; }

        .meta-table { width: 100%; font-size: 11px; font-weight: bold; margin-bottom: 5px; border-collapse: collapse; }
        .meta-table td { padding: 0; line-height: 1.1; vertical-align: top;}

        .icl-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 5px; }
        .icl-table th, .icl-table td { border: 1px solid #000; padding: 2px 4px; }
        .icl-table th { font-weight: bold; text-align: center; vertical-align: middle; font-size: 10px; background-color: #f8f9fa; }
        
        .legend-table { width: 100%; font-size: 10px; border-collapse: collapse; line-height: 1.2; margin-top: 5px; }
        .legend-table td { padding: 1px 0; vertical-align: top; }
        .footer-no { margin-top: 15px; font-size: 11px; }

        .no-print { text-align: center; margin-bottom: 20px; }
        .btn { padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background: #fff; font-weight: bold; margin: 0 5px; }
        
        @media print {
            /* Kunci ke ukuran Kertas Continuous 1/2 A4 */
            @page { 
                size: 210mm 140mm; 
                margin: 5mm 8mm; 
            }
            body { background: #fff; padding: 0; margin: 0; }
            .no-print { display: none; }
            
            .page-container { 
                width: 100%; height: 130mm; min-height: 130mm; 
                padding: 0; margin: 0; border: none; box-shadow: none; 
            }
            
            .page-break { page-break-after: always; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn" onclick="window.close()">&laquo; Tutup</button>
        <button class="btn" onclick="window.print()">Print ICL</button>
    </div>

    <?php 
    // LOOPING UNTUK SETIAP HALAMAN
    foreach ($pages as $pageIndex => $pageRows): 
        $pageNumber = $pageIndex + 1;
        $isLastPage = ($pageNumber == $totalPages);
    ?>

    <div class="page-container <?php echo !$isLastPage ? 'page-break' : ''; ?>">
        
        <div class="header-container">
            <div class="header-left">
                <div class="company-name">PT. IMC TEKNO INDONESIA PLANT 1</div>
                <div class="doc-title">INCOMING CHECK LIST</div>
                <div class="company-addr">
                    Kawasan Industri Kota Bukit Indah<br>
                    Blok A-III No.15E Dangdeur Bungursari<br>
                    Kab. Purwakarta, Jawa Barat 41181<br>
                    Phone : (0264)351440
                </div>
            </div>
            <div class="header-right">
                <div>
                    <div class="page-info">Page <?php echo $pageNumber; ?> of <?php echo $totalPages; ?></div>
                    <table class="sign-box">
                        <tr><th>CHECKER</th><th>RECEIVER</th></tr>
                        <tr><td></td><td></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <table class="meta-table">
            <tr>
                <td style="width: 8%;">Date</td>
                <td style="width: 2%;">:</td>
                <td style="width: 45%; font-weight: normal;"><?php echo h(fmt_date_icl($head["RCV_DATE"])); ?></td>
                <td style="width: 12%;">ICL Number</td>
                <td style="width: 2%;">:</td>
                <td style="width: 31%; font-weight: normal;"><?php echo h($head["RCV_NO"]); ?></td>
            </tr>
            <tr>
                <td>Supplier</td>
                <td>:</td>
                <td style="font-weight: normal;"><?php echo h($head["SUP_COMP"]); ?></td>
                <td>DO Number</td>
                <td>:</td>
                <td style="font-weight: normal;"><?php echo h($head["RCV_DONO"]); ?></td>
            </tr>
            <tr>
                <td>Dept</td>
                <td>:</td>
                <td style="font-weight: normal;"><?php echo h($head["RCV_PIC"]); ?></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </table>

        <table class="icl-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 12%;">CODE</th>
                    <th rowspan="2" style="width: 35%;">NAME</th>
                    <th rowspan="2" style="width: 5%;">UNIT</th>
                    <th rowspan="2" style="width: 10%;">Incoming<br>QTY</th>
                    <th colspan="2" style="width: 10%;">JUDGEMENT</th>
                    <th rowspan="2" style="width: 14%;">PROBLEM</th>
                    <th rowspan="2" style="width: 14%;">RECOMENDATION</th>
                </tr>
                <tr>
                    <th style="width: 5%;">OK</th>
                    <th style="width: 5%;">HOLD</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // CETAK ISI BARANG DI HALAMAN INI
                foreach ($pageRows as $r) {
                    $qty = number_format(floatval($r["RCVD_QTY"]), 2, ".", ",");
                    echo "<tr>
                            <td>" . h($r["ITEM_CODE"]) . "</td>
                            <td>" . h($r["ITEM_NAME"]) . "</td>
                            <td style='text-align: center; text-transform: capitalize;'>" . h($r["ITEM_UNIT"]) . "</td>
                            <td style='text-align: right;'>" . $qty . "</td>
                            <td></td> <td></td> <td></td> <td></td> 
                          </tr>";
                }
                ?>
            </tbody>
            
            <tfoot>
                <?php if ($isLastPage): ?>
                    <tr>
                        <td colspan="3" style="text-align: right; font-weight: bold; border: 1px solid #000; padding: 2px 4px;">TOTAL :</td>
                        <td style="text-align: right; border: 1px solid #000; padding: 2px 4px;"><?php echo number_format($totalQty, 2, ".", ","); ?></td>
                        <td colspan="4" style="border: 1px solid #000;"></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: right; font-style: italic; border: 1px solid #000; padding: 2px 4px;">
                            Bersambung ke halaman berikutnya...
                        </td>
                    </tr>
                <?php endif; ?>
            </tfoot>
        </table>

        <!-- FOOTER WRAPPER -->
        <div class="footer-wrapper">
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

            <div class="footer-no">
                FM.CO.01-41 (Revisi 5 : Tgl.1 Mar 26)
            </div>
        </div>

    </div>

    <?php endforeach; ?>

</body>
</html>