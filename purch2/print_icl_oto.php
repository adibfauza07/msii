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

function num($v, $dec = 2) {
    return number_format(floatval($v), $dec, ".", "");
}

$rcvId = intval(getv("id", "0"));

if ($rcvId <= 0) {
    die("Data Receive belum dipilih atau ID tidak valid.");
}

// Mengambil Data Header dan Detail Receive
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
    ORDER BY I.ITEM_CODE
";

$stmt = sqlsrv_query($conn, $sql, array($rcvId));

if ($stmt === false) {
    die("<pre>Query print ICL OTO error:\n" . sql_error_text() . "</pre>");
}

$rows = array();
$head = null;

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($head === null) {
        $head = $r;
    }
    $rows[] = $r;
}

if ($head === null) {
    die("Data Receive tidak ditemukan atau tidak memiliki detail item.");
}

$totalQty = 0;
foreach ($rows as $r) {
    $totalQty += floatval($r["RCVD_QTY"]);
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
        /* Kunci semua elemen agar menggunakan font Arial secara mutlak */
        * { font-family: Arial, Helvetica, sans-serif; }
        
        @page { size: A4 portrait; margin: 10mm; }
        html, body { 
            margin: 0; padding: 0; 
            background: #e0e0e0; 
            font-size: 13px; color: #000; 
        }
        
        .toolbar { position: fixed; top: 10px; left: 10px; z-index: 999; }
        .btn { font-size: 12px; border: 1px solid #777; background: #eee; padding: 6px 15px; cursor: pointer; }
        
        .page { 
            width: 210mm; min-height: 297mm; 
            margin: 10mm auto; background: #fff; 
            padding: 10mm; box-sizing: border-box; 
            position: relative; page-break-after: always; 
            display: flex; flex-direction: column;
        }
        
        /* HEADER SECTION */
        .header-container { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .header-left { width: 70%; }
        .header-right { width: 30%; display: flex; justify-content: flex-end; align-items: flex-start; }
        
        .company-name { font-size: 16px; font-weight: bold; text-decoration: underline; margin-bottom: 2px; }
        .doc-title { font-size: 18px; font-weight: bold; margin-bottom: 2px; }
        .address { font-size: 13px; line-height: 1.3; }
        
        .sign-box { border-collapse: collapse; width: 150px; text-align: center; }
        .sign-box td, .sign-box th { border: 1px solid #000; padding: 3px; font-size: 11px; font-weight: bold; }
        .sign-box td { height: 35px; }

        /* INFO SECTION */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; font-size: 13px; font-weight: bold; }
        .info-table td { padding: 2px 0; vertical-align: top; }
        
        /* DATA TABLE */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 4px 5px; font-size: 13px; }
        .data-table th { font-weight: bold; text-align: center; vertical-align: middle; }
        
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }

        /* FOOTER LEGEND */
        .footer-wrapper { margin-top: 15px; } /* Footer akan otomatis naik mengikuti batas tabel */
        .legend-container { display: flex; font-size: 12px; margin-top: 10px; justify-content: space-between;}
        .legend-col { line-height: 1.4; }
        .legend-col span { display: inline-block; width: 50px; }
        
        .doc-version { font-size: 12px; margin-top: 10px; }

        @media print { 
            html, body { background: #fff; } 
            .toolbar { display: none; } 
            .page { margin: 0; padding: 5mm; border: none; width: 100%; height: auto; page-break-after: always; } 
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button class="btn" onclick="window.print()">PRINT</button>
    <button class="btn" onclick="window.close()">CLOSE</button>
</div>

<?php for ($p = 0; $p < $totalPages; $p++) { ?>
    <?php
        $pageRows = $pages[$p];
        $isLastPage = ($p == $totalPages - 1);
    ?>
    <div class="page">
        
        <!-- HEADER -->
        <div class="header-container">
            <div class="header-left">
                <div class="company-name">PT.IMC TEKNO INDONESIA PLANT 2</div>
                <div class="doc-title">INCOMING CHECK LIST</div>
                <div class="address">
                    Kawasan Industri Kota Bukit Indah<br>
                    Blok A-III No.15E Dangdeur Bungursari<br>
                    Kab. Purwakarta, Jawa Barat 41181<br>
                    Phone : (0264)351440
                </div>
            </div>
            <div class="header-right">
                <table class="sign-box">
                    <tr>
                        <th style="width:50%">CHECKER</th>
                        <th style="width:50%">RECEIVER</th>
                    </tr>
                    <tr><td></td><td></td></tr>
                    <tr><td style="height:15px;"></td><td style="height:15px;"></td></tr>
                </table>
            </div>
        </div>

        <!-- INFO -->
        <table class="info-table">
            <tr>
                <td style="width: 10%;">Date</td>
                <td style="width: 2%;">:</td>
                <td style="width: 48%;"><?php echo h(fmt_date_icl($head["RCV_DATE"])); ?></td>
                <td style="width: 13%;"></td>
                <td style="width: 2%;"></td>
                <td style="width: 25%;"></td>
            </tr>
            <tr>
                <td>Supplier</td>
                <td>:</td>
                <td><?php echo h($head["SUP_COMP"]); ?></td>
                <td>ICL Number</td>
                <td>:</td>
                <td><?php echo h($head["RCV_NO"]); ?></td>
            </tr>
            <tr>
                <td>Dept</td>
                <td>:</td>
                <td><?php echo h($head["RCV_PIC"]); ?></td>
                <td>DO Number</td>
                <td>:</td>
                <td><?php echo h($head["RCV_DONO"]); ?></td>
            </tr>
        </table>

        <!-- DATA TABLE -->
        <table class="data-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 14%;">CODE</th>
                    <th rowspan="2" style="width: 32%;">NAME</th>
                    <th rowspan="2" style="width: 6%;">UNIT</th>
                    <th rowspan="2" style="width: 12%;">Incoming<br>QTY</th>
                    <th colspan="2" style="width: 10%;">JUDGEMENT</th>
                    <th rowspan="2" style="width: 13%;">PROBLEM</th>
                    <th rowspan="2" style="width: 13%;">RECOMENDATION</th>
                </tr>
                <tr>
                    <th style="width: 5%; font-size:11px;">OK</th>
                    <th style="width: 5%; font-size:11px;">HOLD</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    foreach ($pageRows as $r) {
                        $qty = floatval($r["RCVD_QTY"]);
                ?>
                    <tr>
                        <td><?php echo h($r["ITEM_CODE"]); ?></td>
                        <td><?php echo h($r["ITEM_NAME"]); ?></td>
                        <td class="center"><?php echo h($r["ITEM_UNIT"]); ?></td>
                        <td class="center"><?php echo h(num($qty, 2)); ?></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php } ?>
                
                <?php if ($isLastPage) { ?>
                    <tr>
                        <td colspan="3" class="center bold">TOTAL :</td>
                        <td class="center"><?php echo h(num($totalQty, 2)); ?></td>
                        <td colspan="4"></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <!-- FOOTER WRAPPER -->
        <div class="footer-wrapper">
            <!-- LEGEND (Muncul di setiap halaman) -->
            <div class="legend-container">
                <div class="legend-col">
                    <span>White</span>: Acc + Finnace<br>
                    <span>Yellow</span>: Purchasing
                </div>
                <div class="legend-col">
                    <span style="width:40px;">Green</span>: Checker<br>
                    <span style="width:40px;">Pink</span>: Receiver
                </div>
                <div class="legend-col">
                    R : Raw Material<br>
                    V : Vendor<br>
                    P : Packing
                </div>
                <div class="legend-col">
                    M : Machine<br>
                    A : ATK<br>
                    O : Other
                </div>
            </div>
            
            <div class="doc-version">
                FM.CO.01-41 (Revisi 5 : Tgl. 1 Mar 23)
            </div>
        </div>

    </div>
<?php } ?>
</body>
</html>