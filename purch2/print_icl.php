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

function fmt_date($value) {
    if ($value instanceof DateTime) {
        return $value->format("d-M-Y");
    }

    if ($value == "" || $value === null) {
        return "";
    }

    $ts = strtotime((string)$value);
    if ($ts === false) return "";

    return date("d-M-Y", $ts);
}

function num($v, $dec = 2) {
    return number_format(floatval($v), $dec, ".", ",");
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
        S.SUP_CODE,
        S.SUP_COMP,
        I.ITEM_CODE,
        I.ITEM_NAME,
        I.ITEM_UNIT,
        SUM(RD.RCVD_QTY) AS RCVD_QTY,
        P.PO_NUM
    FROM dbo.RECEIVE R
    INNER JOIN dbo.RECEIVE_DETAIL RD ON R.RCV_ID = RD.RCV_ID
    INNER JOIN dbo.ITEMS I ON RD.ITEM_ID = I.ITEM_ID
    INNER JOIN dbo.PO P ON RD.PO_ID = P.PO_ID
    INNER JOIN dbo.SUPPLIER S ON R.SUP_ID = S.SUP_ID
    WHERE R.RCV_ID = ?
    GROUP BY 
        R.RCV_ID, 
        R.RCV_NO, 
        R.RCV_DATE, 
        R.RCV_DONO,
        S.SUP_CODE, 
        S.SUP_COMP,
        I.ITEM_CODE, 
        I.ITEM_NAME, 
        I.ITEM_UNIT, 
        P.PO_NUM
    ORDER BY I.ITEM_CODE
";

$stmt = sqlsrv_query($conn, $sql, array($rcvId));

if ($stmt === false) {
    die("<pre>Query print ICL error:\n" . sql_error_text() . "</pre>");
}

$rows = array();
$head = null;
$totalQty = 0;

// Fetch data yang sudah di-sum oleh SQL
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($head === null) {
        $head = $r;
    }
    $rows[] = $r;
    $totalQty += floatval($r["RCVD_QTY"]);
}

if ($head === null) {
    die("Data Receive tidak ditemukan atau tidak memiliki detail item.");
}

// =========================================================================
// LOGIKA PAGINASI DINAMIS (Mencegah ruang kosong & keluar garis)
// =========================================================================
$maxRowsWithoutFooter = 26; // Tabel full memanjang jika tidak ada TTD
$maxRowsWithFooter = 16;    // Tabel lebih pendek jika ada TTD di bawahnya

$pages = array();
$totalRows = count($rows);
$i = 0;

while ($i < $totalRows) {
    $remaining = $totalRows - $i;
    
    if ($remaining <= $maxRowsWithFooter) {
        // Cukup untuk masuk 1 halaman sekalian TTD
        $chunk = array_slice($rows, $i, $remaining);
        $pages[] = array(
            'rows' => $chunk,
            'has_footer' => true,
            'empty_fill' => $maxRowsWithFooter - $remaining,
            'start_index' => $i
        );
        $i += $remaining;
    } else if ($remaining > $maxRowsWithFooter && $remaining <= $maxRowsWithoutFooter) {
        // Bisa ditarik full 1 halaman, tapi TTD harus pindah ke halaman berikutnya
        $chunk = array_slice($rows, $i, $maxRowsWithoutFooter);
        $pages[] = array(
            'rows' => $chunk,
            'has_footer' => false,
            'empty_fill' => $maxRowsWithoutFooter - count($chunk),
            'start_index' => $i
        );
        $i += count($chunk);
    } else {
        // Lebih dari kapasitas, potong penuh 1 halaman tanpa TTD
        $chunk = array_slice($rows, $i, $maxRowsWithoutFooter);
        $pages[] = array(
            'rows' => $chunk,
            'has_footer' => false,
            'empty_fill' => 0,
            'start_index' => $i
        );
        $i += $maxRowsWithoutFooter;
    }
}

// Jika halaman terakhir nge-pas full tabel (tidak ada TTD), buat 1 halaman kosong khusus TTD
if (count($pages) == 0 || !$pages[count($pages) - 1]['has_footer']) {
    $pages[] = array(
        'rows' => array(),
        'has_footer' => true,
        'empty_fill' => $maxRowsWithFooter,
        'start_index' => $totalRows
    );
}
$totalPages = count($pages);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cetak ICL - <?php echo h($head["RCV_NO"]); ?></title>

    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #999999;
            font-family: "Times New Roman", serif;
            font-size: 12px;
            color: #000000;
        }

        .toolbar {
            position: fixed;
            top: 8px;
            left: 8px;
            z-index: 999;
        }

        .btn {
            font-family: Arial, sans-serif;
            font-size: 12px;
            border: 1px solid #777777;
            background: #eeeeee;
            padding: 4px 12px;
            cursor: pointer;
        }

        .page {
            width: 194mm; 
            height: 275mm; 
            margin: 4px auto; 
            background: #ffffff;
            padding: 8mm;
            box-sizing: border-box;
            border: 1px solid #000000;
            position: relative;
            page-break-after: always;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .header-company {
            font-size: 16px;
            font-weight: bold;
            text-align: left;
        }

        .title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin-top: 4mm;
            margin-bottom: 4mm;
            letter-spacing: 1px;
            text-decoration: underline;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .info-table {
            margin-bottom: 4mm;
            font-size: 12px;
        }
        
        .info-table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .detail-wrapper {
            flex-grow: 1; 
        }

        .detail {
            border-bottom: 1px solid #000000; 
        }

        .detail th {
            border: 1px solid #000000;
            font-size: 12px;
            text-align: center;
            padding: 6px;
            background-color: #f0f0f0;
        }

        .detail td {
            border-left: 1px solid #000000;
            border-right: 1px solid #000000;
            padding: 3px 5px;
            height: 23px; 
            vertical-align: middle;
        }

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }

        .footer-section {
            margin-top: auto; 
        }

        .footer-table td {
            border: 1px solid #000000;
            padding: 4px 5px;
            background-color: #f9f9f9;
        }

        .sign-table {
            margin-top: 5mm;
            width: 100%;
            text-align: center;
        }
        
        .sign-table td {
            width: 33.33%;
            vertical-align: top;
        }

        .sign-space {
            height: 20mm;
        }

        .page-no {
            position: absolute;
            right: 8mm;
            top: 8mm;
            font-size: 11px;
        }

        @media print {
            html, body {
                background: #ffffff;
            }
            .toolbar {
                display: none;
            }
            .page {
                margin: 0;
                border: none;
                width: 100%;
                height: 277mm; 
                page-break-after: always;
            }
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
        $pageData = $pages[$p];
        $pageRows = $pageData['rows'];
        $hasFooter = $pageData['has_footer'];
        $emptyFill = $pageData['empty_fill'];
        $startIndex = $pageData['start_index'];
    ?>

    <div class="page">
        <div class="page-no">Page <?php echo ($p + 1); ?> of <?php echo $totalPages; ?></div>

        <div class="header-company">PT. IMC TEKNO INDONESIA</div>

        <div class="title">INCOMING CHECK LIST (ICL)</div>

        <table class="info-table">
            <tr>
                <td style="width: 15%;"><b>ICL No.</b></td>
                <td style="width: 2%;">:</td>
                <td style="width: 43%;"><?php echo h($head["RCV_NO"]); ?></td>
                
                <td style="width: 15%;"><b>Supplier</b></td>
                <td style="width: 2%;">:</td>
                <td style="width: 23%;"><?php echo h($head["SUP_COMP"]); ?></td>
            </tr>
            <tr>
                <td><b>Date</b></td>
                <td>:</td>
                <td><?php echo h(fmt_date($head["RCV_DATE"])); ?></td>
                
                <td><b>Sup. Code</b></td>
                <td>:</td>
                <td><?php echo h($head["SUP_CODE"]); ?></td>
            </tr>
            <tr>
                <td><b>D.O No.</b></td>
                <td>:</td>
                <td colspan="4"><?php echo h($head["RCV_DONO"]); ?></td>
            </tr>
        </table>

        <div class="detail-wrapper">
            <table class="detail">
                <thead>
                    <tr>
                        <th style="width:5%;">No</th>
                        <th style="width:15%;">Item Code</th>
                        <th style="width:35%;">Description</th>
                        <th style="width:20%;">PO Number</th>
                        <th style="width:15%;">Quantity</th>
                        <th style="width:10%;">Unit</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                        foreach ($pageRows as $idx => $r) {
                            $qty = floatval($r["RCVD_QTY"]);
                    ?>
                        <tr>
                            <td class="center"><?php echo ($startIndex + $idx + 1); ?></td>
                            <td><?php echo h($r["ITEM_CODE"]); ?></td>
                            <td><?php echo h($r["ITEM_NAME"]); ?></td>
                            <td class="center"><?php echo h($r["PO_NUM"]); ?></td>
                            <td class="right"><?php echo h(num($qty, 2)); ?></td>
                            <td class="center"><?php echo h($r["ITEM_UNIT"]); ?></td>
                        </tr>
                    <?php } ?>

                    <?php
                        // Membuat baris kosong agar tabel menyentuh bawah
                        for ($i = 0; $i < $emptyFill; $i++) {
                    ?>
                        <tr>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php if ($hasFooter) { ?>
        <div class="footer-section">
            <table class="footer-table">
                <tr>
                    <td style="width:75%;" class="right bold">Total Quantity</td>
                    <td style="width:15%;" class="right bold"><?php echo h(num($totalQty, 2)); ?></td>
                    <td style="width:10%;"></td>
                </tr>
            </table>

            <table class="sign-table">
                <tr>
                    <td>
                        <b>Prepared By,</b><br>
                        <div class="sign-space"></div>
                        (.......................................)<br>
                        Store / Receiving
                    </td>
                    <td>
                        <b>Checked By,</b><br>
                        <div class="sign-space"></div>
                        (.......................................)<br>
                        PPIC / Purchasing
                    </td>
                    <td>
                        <b>Acknowledged By,</b><br>
                        <div class="sign-space"></div>
                        (.......................................)<br>
                        Manager / Director
                    </td>
                </tr>
            </table>
        </div>
        <?php } else { ?>
            <div class="footer-section">
                <table style="border-top: 1px solid #000; width: 100%;"><tr><td></td></tr></table>
            </div>
        <?php } ?>
    </div>
<?php } ?>

</body>
</html>