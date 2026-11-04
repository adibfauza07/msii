<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . "/config/db_plant2.php";

if ($conn === false) {
    die("Koneksi database gagal.");
}

/* =========================================================
 * AMBIL NAMA PERUSAHAAN (GLOBAL UNTUK JUDUL REPORT)
 * ========================================================= */
$companyName = ""; 
if (isset($_SESSION['company_name'])) {
    $companyName = $_SESSION['company_name'];
} else if (isset($conn) && $conn !== false) {
    $sqlCompany = "SELECT TOP 1 CO_COMPANY FROM dbo.COMPANY";
    $stmtCompany = sqlsrv_query($conn, $sqlCompany);
    if ($stmtCompany && $rowCompany = sqlsrv_fetch_array($stmtCompany, SQLSRV_FETCH_ASSOC)) {
        if (!empty($rowCompany['CO_COMPANY'])) {
            $companyName = $rowCompany['CO_COMPANY'];
            $_SESSION['company_name'] = $companyName;
        }
    }
}

// Fallback jika session kosong dan database juga gagal/kosong
if (empty($companyName)) {
    $companyName = "PT. IMC TEKNO INDONESIA";
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

function money($v, $dec = 2) {
    return number_format(floatval($v), $dec, ".", ",");
}

function terbilang_en_simple($number) {
    $number = round(floatval($number), 2);
    $whole = floor($number);
    $cents = round(($number - $whole) * 100);

    $ones = array(
        0 => "zero", 1 => "one", 2 => "two", 3 => "three", 4 => "four",
        5 => "five", 6 => "six", 7 => "seven", 8 => "eight", 9 => "nine",
        10 => "ten", 11 => "eleven", 12 => "twelve", 13 => "thirteen",
        14 => "fourteen", 15 => "fifteen", 16 => "sixteen",
        17 => "seventeen", 18 => "eighteen", 19 => "nineteen"
    );

    $tens = array(
        2 => "twenty", 3 => "thirty", 4 => "forty", 5 => "fifty",
        6 => "sixty", 7 => "seventy", 8 => "eighty", 9 => "ninety"
    );

    $convert = function($n) use (&$convert, $ones, $tens) {
        $n = intval($n);

        if ($n < 20) {
            return $ones[$n];
        }

        if ($n < 100) {
            $t = floor($n / 10);
            $r = $n % 10;
            return $tens[$t] . ($r > 0 ? "-" . $ones[$r] : "");
        }

        if ($n < 1000) {
            $h = floor($n / 100);
            $r = $n % 100;
            return $ones[$h] . " hundred" . ($r > 0 ? " " . $convert($r) : "");
        }

        if ($n < 1000000) {
            $th = floor($n / 1000);
            $r = $n % 1000;
            return $convert($th) . " thousand" . ($r > 0 ? " " . $convert($r) : "");
        }

        if ($n < 1000000000) {
            $m = floor($n / 1000000);
            $r = $n % 1000000;
            return $convert($m) . " million" . ($r > 0 ? " " . $convert($r) : "");
        }

        return (string)$n;
    };

    if ($cents > 0) {
        return $convert($whole) . " and " . str_pad($cents, 2, "0", STR_PAD_LEFT) . " /100 only";
    } else {
        return $convert($whole) . " only";
    }
}

$poId = intval(getv("po_id", "0"));
$poNum = getv("po_num", "");

if ($poId <= 0 && $poNum == "") {
    die("PO belum dipilih.");
}

$where = "";
$params = array();

if ($poId > 0) {
    $where = "WHERE dbo.PO.PO_ID = ?";
    $params[] = $poId;
} else {
    $where = "WHERE dbo.PO.PO_NUM = ?";
    $params[] = $poNum;
}

$sql = "
    SELECT
        dbo.PO.PO_ID,
        dbo.PO.PO_DATE,
        dbo.PO.PO_NUM,
        dbo.PO.PO_TERM,
        dbo.PO.PO_TERMDEL,
        dbo.PO.PO_REM,
        dbo.PO.PO_DATEDO,
        dbo.PO.PO_CUR AS CURR_CODE,
        dbo.PO.PO_TODEF,

        dbo.SUPPLIER.SUP_CODE,
        dbo.SUPPLIER.SUP_COMP,
        dbo.SUPPLIER.SUP_ADDR1,
        dbo.SUPPLIER.SUP_ADDR2,
        dbo.SUPPLIER.SUP_CITY,
        dbo.SUPPLIER.SUP_PHONE,
        dbo.SUPPLIER.SUP_FAX,
        dbo.SUPPLIER.SUP_CONTA,

        CASE dbo.PO.PO_TODEF
            WHEN 0 THEN SUPPLIER_1.SUP_COMP
            ELSE (SELECT TOP 1 CO_COMPANY FROM dbo.COMPANY)
        END AS INV_COMP,

        CASE dbo.PO.PO_TODEF
            WHEN 0 THEN SUPPLIER_1.SUP_ADDR1
            ELSE (SELECT TOP 1 CO_ADDR1 FROM dbo.COMPANY)
        END AS INV_ADDR1,

        CASE dbo.PO.PO_TODEF
            WHEN 0 THEN SUPPLIER_1.SUP_ADDR2
            ELSE (SELECT TOP 1 CO_ADDR2 FROM dbo.COMPANY)
        END AS INV_ADDR2,

        CASE dbo.PO.PO_TODEF
            WHEN 0 THEN SUPPLIER_1.SUP_CITY
            ELSE (SELECT TOP 1 CO_CITY FROM dbo.COMPANY)
        END AS INV_CITY,

        CASE dbo.PO.PO_TODEF
            WHEN 0 THEN SUPPLIER_1.SUP_PHONE
            ELSE (SELECT TOP 1 CO_PHONE FROM dbo.COMPANY)
        END AS INV_PHONE,

        CASE dbo.PO.PO_TODEF
            WHEN 0 THEN SUPPLIER_1.SUP_FAX
            ELSE (SELECT TOP 1 CO_FAX FROM dbo.COMPANY)
        END AS INV_FAX,

        CASE dbo.PO.PO_TODEF
            WHEN 0 THEN SUPPLIER_1.SUP_CONTA
            ELSE ''
        END AS INV_CONTA,

        dbo.ITEMS.ITEM_CODE,
        dbo.ITEMS.ITEM_NAME,
        dbo.ITEMS.ITEM_NO,
        PODV.QTY,
        PODV.POD_UNIT,
        PODV.POD_PRICE,
        PODV.POD_DUE

    FROM dbo.PO
    INNER JOIN dbo.PO_DETAIL_DUE_VIEW AS PODV
        ON dbo.PO.PO_ID = PODV.PO_ID
    INNER JOIN dbo.ITEMS
        ON PODV.ITEM_ID = dbo.ITEMS.ITEM_ID
    INNER JOIN dbo.SUPPLIER
        ON dbo.PO.SUP_ID = dbo.SUPPLIER.SUP_ID
    LEFT OUTER JOIN dbo.SUPPLIER AS SUPPLIER_1
        ON dbo.PO.PO_TO = SUPPLIER_1.SUP_ID
    $where
    ORDER BY
        dbo.ITEMS.ITEM_CODE,
        PODV.POD_DUE
";

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("<pre>Query print PO error:\n" . sql_error_text() . "</pre>");
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
    die("Data PO tidak ditemukan.");
}

$currency = trim((string)$head["CURR_CODE"]);
$total = 0;
$totalQty = 0;

foreach ($rows as $r) {
    $qty = floatval($r["QTY"]);
    $total += $qty * floatval($r["POD_PRICE"]);
    $totalQty += $qty;
}

// --------------------------------------------------------------------------
// LOGIK CUKAI (TAX YES / NO)
// --------------------------------------------------------------------------
$useTax = getv("use_tax", "yes"); // Secara lalai, cukai diaktifkan ("yes")
$taxRate = 0.11;
$tax = 0;

if ($useTax === "yes") {
    $tax = $total * $taxRate;
}

$grandTotal = $total + $tax;

// --------------------------------------------------------------------------
// LIMIT HALAMAN DITINGKATKAN AGAR LEBIH MUAT BANYAK
// --------------------------------------------------------------------------
$limitNormal = 38; 
$limitLast   = 20;

$pages = array();
$currentIndex = 0;
$totalRows = count($rows);

if ($totalRows == 0) {
    $pages[] = array();
}

while ($currentIndex < $totalRows) {
    $remaining = $totalRows - $currentIndex;
    
    if ($remaining <= $limitLast) {
        $pages[] = array_slice($rows, $currentIndex, $remaining);
        $currentIndex += $remaining;
    } 
    else {
        $take = min($limitNormal, $remaining);
        $pages[] = array_slice($rows, $currentIndex, $take);
        $currentIndex += $take;
    }
}

if (count($pages) > 0 && count($pages[count($pages)-1]) > $limitLast) {
    $pages[] = array(); 
}

$totalPages = count($pages);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print PO - <?php echo h($head["PO_NUM"]); ?></title>

    <style>
        @page {
            size: A4 portrait;
            margin: 0; 
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #e2e2e2;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
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
            margin-right: 4px;
        }

        .page {
            width: 210mm; 
            height: 297mm; 
            margin: 10px auto;
            background: #ffffff;
            padding: 8mm 12mm 10mm 12mm; 
            box-sizing: border-box;
            position: relative;
            display: flex;
            flex-direction: column;
            page-break-after: always;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        td, th {
            border: 1px solid #000000;
            vertical-align: top;
            padding: 2px 4px;
        }

        .no-border td { border: none !important; }

        .bold { font-weight: bold; }
        .center { text-align: center; }
        .right { text-align: right; }
        .left { text-align: left; }

        .label {
            font-size: 10px;
            display: block;
            margin-bottom: 2px;
        }

        .header-title-wrapper {
            position: relative;
            margin-bottom: 3px;
        }

        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .page-no {
            position: absolute;
            right: 0;
            top: 0;
            font-size: 10px;
        }

        .rohs {
            text-align: center;
            font-family: "Times New Roman", Times, serif;
            font-size: 26px;
            font-weight: bold;
            letter-spacing: 1px;
            padding: 4px 0 !important;
        }

        .detail-table {
            flex-grow: 1;
            margin-top: -1px; 
        }

        .detail-table th {
            text-align: center;
            font-weight: normal;
        }

        .detail-table td {
            height: 14px;
        }
        
        .item-row td {
            border-top: none !important;
            border-bottom: none !important;
        }
        
        .detail-table tr.stretcher td {
            height: 100%;
            border-top: none !important;
            border-bottom: none !important;
            padding: 0;
        }

        .footer-part td { border-top: 1px solid #000 !important; }

        .signature-table td {
            padding: 0;
        }
        
        .print-code {
            font-size: 10px;
            margin-top: 2px;
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
                padding-top: 10mm; 
                page-break-after: always;
            }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button class="btn" onclick="window.print()">PRINT</button>
    <button class="btn" onclick="window.close()">CLOSE</button>
    
    <!-- Butang pilihan Tax -->
    <?php if ($useTax === 'yes') { ?>
        <button class="btn" onclick="window.location.href='?po_id=<?php echo $poId; ?>&po_num=<?php echo urlencode($poNum); ?>&use_tax=no'">TANPA TAX</button>
    <?php } else { ?>
        <button class="btn" onclick="window.location.href='?po_id=<?php echo $poId; ?>&po_num=<?php echo urlencode($poNum); ?>&use_tax=yes'">PAKAI TAX</button>
    <?php } ?>
</div>

<?php for ($p = 0; $p < $totalPages; $p++) { ?>
    <?php
        $pageRows = $pages[$p];
        $isLastPage = ($p == $totalPages - 1);
        $targetLines = $isLastPage ? $limitLast : $limitNormal;
    ?>

    <div class="page">
        <div class="header-title-wrapper">
            <div class="title">PURCHASE ORDER</div>
            <div class="page-no">Page <?php echo ($p + 1); ?> of <?php echo $totalPages; ?></div>
        </div>

        <table class="top-info">
            <tr>
                <td style="width:48%;" rowspan="3">
                    <span class="label">Invoice To</span><br>
                    <span class="bold"><?php echo h($head["INV_COMP"]); ?></span><br>
                    <?php echo h($head["INV_ADDR1"]); ?><br>
                    <?php echo h($head["INV_ADDR2"]); ?><br>
                    <?php echo h($head["INV_CITY"]); ?><br>
                    Phone: <?php echo h($head["INV_PHONE"]); ?>, Fax: <?php echo h($head["INV_FAX"]); ?><br>
                    NPWP: 01.071.426.9-052.000
                </td>
                <td style="width:26%;">
                    <span class="label">Order No.</span>
                    <span class="bold"><?php echo h($head["PO_NUM"]); ?></span>
                </td>
                <td style="width:26%;">
                    <span class="label">Dated</span>
                    <span class="bold"><?php echo h(fmt_date($head["PO_DATE"])); ?></span>
                </td>
            </tr>

            <tr>
                <td>
                    <span class="label">Currency</span>
                    <span class="bold"><?php echo h($currency); ?></span>
                </td>
                <td>
                    <span class="label">Terms of Payment</span>
                    <?php echo h($head["PO_TERM"]); ?>
                </td>
            </tr>

            <tr>
                <td>
                    <span class="label">Supplier's Ref.</span>
                    <span class="bold"><?php echo h($head["PO_NUM"]); ?></span>
                </td>
                <td>
                    <span class="label">Other References</span>
                    &nbsp;
                </td>
            </tr>

            <tr>
                <td rowspan="4">
                    <span class="label">Supplier</span><br>
                    <span class="bold"><?php echo h($head["SUP_COMP"]); ?></span><br>
                    <?php echo h($head["SUP_ADDR1"]); ?><br>
                    <?php echo h($head["SUP_ADDR2"]); ?><br>
                    <?php echo h($head["SUP_CITY"]); ?><br>
                    Phone: <?php echo h($head["SUP_PHONE"]); ?>, Fax: <?php echo h($head["SUP_FAX"]); ?><br>
                    Attn: <?php echo h($head["SUP_CONTA"]); ?>
                </td>
                <td>
                    <span class="label">Dispatch through</span>
                    &nbsp;
                </td>
                <td>
                    <span class="label">Destination</span>
                    &nbsp;
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <span class="label">Term of Delivery</span>
                    <?php echo h($head["PO_TERMDEL"]); ?>
                </td>
            </tr>

            <tr>
                <td colspan="2" style="height: 35px;">
                    <span class="label">Remark</span>
                    <?php echo nl2br(h($head["PO_REM"])); ?>
                </td>
            </tr>

            <tr>
                <td colspan="2" class="rohs">ROHS FREE</td>
            </tr>
        </table>

        <table class="detail-table">
            <thead>
                <tr>
                    <th style="width:48%;">Description of Goods</th>
                    <th style="width:12%;">Qty.</th>
                    <th style="width:14%;">Rate.</th>
                    <th style="width:10%;">per</th>
                    <th style="width:16%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $printed = 0;
                    $lastUnit = ""; 
                    foreach ($pageRows as $r) {
                        $qty = floatval($r["QTY"]);
                        $price = floatval($r["POD_PRICE"]);
                        $amount = $qty * $price;
                        $unit = trim($r["POD_UNIT"]);
                        $lastUnit = $unit;
                        $printed++;
                ?>
                    <tr class="item-row">
                        <td>
                            <?php echo h(trim($r["ITEM_CODE"]) . " " . trim($r["ITEM_NAME"])); ?>
                        </td>
                        <td class="right"><?php echo h(num($qty, 2)) . " " . h($unit); ?></td>
                        <td class="right"><?php echo h(money($price, 2)); ?></td>
                        <td class="right"><?php echo h($unit); ?></td>
                        <td class="right"><?php echo h($currency . " " . money($amount, 2)); ?></td>
                    </tr>
                <?php } ?>

                <?php
                    for ($i = $printed; $i < $targetLines; $i++) {
                ?>
                    <tr class="item-row">
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                <?php } ?>
                
                <tr class="stretcher">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>

                <?php if ($isLastPage) { ?>
                    <tr class="footer-part">
                        <td class="right bold">Total</td>
                        <td class="right bold"><?php echo h(num($totalQty, 2)) . " " . h($lastUnit); ?></td>
                        <td></td>
                        <td></td>
                        <td class="right bold"><?php echo h($currency . " " . money($total, 2)); ?></td>
                    </tr>
                    
                    <?php if ($useTax === 'yes') { ?>
                    <tr>
                        <td colspan="4" class="center bold">Tax</td>
                        <td class="right bold"><?php echo h(money($tax, 2)); ?></td>
                    </tr>
                    <?php } ?>
                    
                    <tr>
                        <td colspan="4" class="center bold">Amount</td>
                        <td class="right bold"><?php echo h(money($grandTotal, 2)); ?></td>
                    </tr>
                    <tr>
                        <td colspan="5">
                            <span class="label" style="margin-bottom:0;">Chargeable (In words)</span>
                            <span class="bold"><?php echo h(terbilang_en_simple($grandTotal)); ?></span>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <?php if ($isLastPage) { ?>
            <table class="signature-table" style="border: none; margin-top: 5px;">
                <tr>
                    <td style="width: 25%; border: 1px solid #000; height: 95px; position: relative;">
                        <div style="text-align: center; margin-top: 2px;">Supplier Confirmed</div>
                        <div style="position: absolute; bottom: 5px; width: 100%; text-align: center;">(Chop &amp; Sign)</div>
                    </td>
                    <td style="width: 40%; border: none; vertical-align: bottom; padding-left: 5px; padding-bottom: 5px;">
                        ( Please sign and send back by fax )
                    </td>
                    <td style="width: 35%; border: 1px solid #000; height: 95px; position: relative;">
                        <div style="text-align: right; margin-top: 2px; padding-right: 5px;">for <span class="bold"><?php echo htmlspecialchars($companyName); ?></span></div>
                        <div style="position: absolute; bottom: 5px; width: 100%; text-align: center;">
                            <b>( Koichi Hiraoka )</b><br>
                            President Director
                        </div>
                    </td>
                </tr>
            </table>
        <?php } ?>

        <div class="print-code">FM.CO.00-64</div>
    </div>
<?php } ?>

</body>
</html>