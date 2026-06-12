<?php
// /msii/ppic/label_print.php
// PHP 5.4 + SQL Server
// PACK/BAG = 85mm x 50mm, A4 isi 10 label.
// BOX      = 90mm x 60mm, A4 isi 8 label.
// UL       = gambar /msii/assets/ul_h1724.png.
// SUZUKI   = kanan atas pakai ITEM_NO.
// AUTOTECH = pakai BOX template khusus.
// KOITO    = template khusus, SPESIAL & SPESIAL2 50px bold.
// LOT NO   = 11 kotak, isi otomatis dari field TYPE.
// Qty      = PACK pakai STD_PACK, BOX pakai STD_PACK_BOX.

@ini_set('max_execution_time', 120);



require_once "../config/database_ppic.php";

function h_print($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function getv_print($row, $key, $default) {
    if (isset($row[$key]) && $row[$key] !== null) {
        return $row[$key];
    }
    return $default;
}

function code39_svg($text, $className, $heightPx) {
    $map = array(
        '0'=>'nnnwwnwnn','1'=>'wnnwnnnnw','2'=>'nnwwnnnnw','3'=>'wnwwnnnnn',
        '4'=>'nnnwwnnnw','5'=>'wnnwwnnnn','6'=>'nnwwwnnnn','7'=>'nnnwnnwnw',
        '8'=>'wnnwnnwnn','9'=>'nnwwnnwnn','A'=>'wnnnnwnnw','B'=>'nnwnnwnnw',
        'C'=>'wnwnnwnnn','D'=>'nnnnwwnnw','E'=>'wnnnwwnnn','F'=>'nnwnwwnnn',
        'G'=>'nnnnnwwnw','H'=>'wnnnnwwnn','I'=>'nnwnnwwnn','J'=>'nnnnwwwnn',
        'K'=>'wnnnnnnww','L'=>'nnwnnnnww','M'=>'wnwnnnnwn','N'=>'nnnnwnnww',
        'O'=>'wnnnwnnwn','P'=>'nnwnwnnwn','Q'=>'nnnnnnwww','R'=>'wnnnnnwwn',
        'S'=>'nnwnnnwwn','T'=>'nnnnwnwwn','U'=>'wwnnnnnnw','V'=>'nwwnnnnnw',
        'W'=>'wwwnnnnnn','X'=>'nwnnwnnnw','Y'=>'wwnnwnnnn','Z'=>'nwwnwnnnn',
        '-'=>'nwnnnnwnw','.'=>'wwnnnnwnn',' '=>'nwwnnnwnn','*'=>'nwnnwnwnn',
        '$'=>'nwnwnwnnn','/'=>'nwnwnnnwn','+'=>'nwnnnwnwn','%'=>'nnnwnwnwn'
    );

    $text = strtoupper(trim((string)$text));
    $clean = '';

    for ($i = 0; $i < strlen($text); $i++) {
        $ch = $text[$i];
        if (isset($map[$ch])) {
            $clean .= $ch;
        }
    }

    if ($clean == '') {
        $clean = '0';
    }

    $code = '*' . $clean . '*';

    $narrow = 2;
    $wide   = 5;
    $gap    = 2;

    $x = 0;
    $rects = '';

    for ($c = 0; $c < strlen($code); $c++) {
        $ch = $code[$c];
        $pattern = $map[$ch];

        for ($i = 0; $i < 9; $i++) {
            $isBar = ($i % 2 == 0);
            $w = ($pattern[$i] == 'w') ? $wide : $narrow;

            if ($isBar) {
                $rects .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="' . $heightPx . '" fill="#000000" />';
            }

            $x += $w;
        }

        $x += $gap;
    }

    $totalWidth = $x;

    return '<svg xmlns="http://www.w3.org/2000/svg"'
        . ' class="barcode-svg ' . h_print($className) . '"'
        . ' viewBox="0 0 ' . $totalWidth . ' ' . $heightPx . '"'
        . ' preserveAspectRatio="none">'
        . $rects
        . '</svg>';
}

function get_type_lot_print($row) {
    $lot = '';

    if (isset($row['TYPE']) && trim((string)$row['TYPE']) !== '') {
        $lot = trim((string)$row['TYPE']);
    } elseif (isset($row['SPESIAL3']) && trim((string)$row['SPESIAL3']) !== '') {
        $lot = trim((string)$row['SPESIAL3']);
    } elseif (isset($row['LOT_NO']) && trim((string)$row['LOT_NO']) !== '') {
        $lot = trim((string)$row['LOT_NO']);
    } elseif (isset($row['LOTNO']) && trim((string)$row['LOTNO']) !== '') {
        $lot = trim((string)$row['LOTNO']);
    }

    $lot = preg_replace('/\s+/', '', $lot);
    return $lot;
}

function lot_box_html($lot) {
    $lot = trim((string)$lot);
    $chars = str_split($lot);

    $html = "<table class='lot-table' cellpadding='0' cellspacing='0'><tr>";

    for ($i = 0; $i < 11; $i++) {
        $isi = isset($chars[$i]) ? $chars[$i] : '';
        $html .= "<td>" . h_print($isi) . "</td>";
    }

    $html .= "</tr></table>";
    return $html;
}

function koito_lot_box_html($lot) {
    $lot = trim((string)$lot);
    $chars = str_split($lot);

    $html = "<table class='koito-lot-table' cellpadding='0' cellspacing='0'><tr>";

    for ($i = 0; $i < 11; $i++) {
        $isi = isset($chars[$i]) ? $chars[$i] : '';
        $html .= "<td>" . h_print($isi) . "</td>";
    }

    $html .= "</tr></table>";
    return $html;
}

function get_label_types_print_page() {
    return array(
        'STD_PACK' => array('nama' => 'STANDAR - PACK', 'mode' => 'BAG', 'sp' => 'sp_CetakLabel_BAG'),
        'STD_BOX'  => array('nama' => 'STANDAR - BOX',  'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        'STD_INITIAL_LH_PACK' => array('nama' => 'STANDAR INITIAL LH - PACK', 'mode' => 'BAG', 'sp' => 'sp_CetakLabel_BAG'),
        'STD_INITIAL_LH_BOX'  => array('nama' => 'STANDAR INITIAL LH - BOX',  'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        'STD_PAKAI_UL_PACK' => array('nama' => 'STANDAR PAKAI UL - PACK', 'mode' => 'BAG', 'sp' => 'sp_CetakLabel_BAG'),
        'STD_PAKAI_UL_BOX'  => array('nama' => 'STANDAR PAKAI UL - BOX',  'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        'STD_PAKAI_INITIAL_PACK' => array('nama' => 'STANDAR PAKAI INITIAL - PACK', 'mode' => 'BAG', 'sp' => 'sp_CetakLabel_BAG'),
        'STD_PAKAI_INITIAL_BOX'  => array('nama' => 'STANDAR PAKAI INITIAL - BOX',  'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        'STD_ITEM_RL_PACK' => array('nama' => 'STANDAR ITEM R/L - PACK', 'mode' => 'BAG', 'sp' => 'sp_CetakLabel_BAG'),
        'STD_ITEM_RL_BOX'  => array('nama' => 'STANDAR ITEM R/L - BOX',  'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        'SUZUKI_PACK' => array('nama' => 'SUZUKI - PACK', 'mode' => 'BAG', 'sp' => 'sp_CetakLabel_BAG'),
        'SUZUKI_BOX'  => array('nama' => 'SUZUKI - BOX',  'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        // AUTOTECH tetap pakai kode AUTOTECH_PACK, tapi mode BOX.
        'AUTOTECH_PACK' => array('nama' => 'AUTOTECH - BOX', 'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        'KOITO_PACK' => array('nama' => 'KOITO - PACK', 'mode' => 'BAG', 'sp' => 'sp_CetakLabel_BAG'),
        'KOITO_BOX'  => array('nama' => 'KOITO - BOX',  'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        'AUTOLIV_PACK' => array('nama' => 'AUTOLIV - PACK', 'mode' => 'BAG', 'sp' => 'sp_CetakLabel_BAG'),

        'CABININDO_PACK' => array('nama' => 'CABININDO - PACK', 'mode' => 'BAG', 'sp' => 'sp_CetakLabel_BAG'),
        'CABININDO_BOX'  => array('nama' => 'CABININDO - BOX',  'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        'SANKEIKID_PACK' => array('nama' => 'SANKEIKID - PACK', 'mode' => 'BAG', 'sp' => 'sp_CetakLabel_BAG'),
        'SANKEIKID_BOX'  => array('nama' => 'SANKEIKID - BOX',  'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        'SANKEIKID_SIM_PACK' => array('nama' => 'SANKEIKID SIM - PACK', 'mode' => 'BAG', 'sp' => 'sp_CetakLabel_BAG'),
        'SANKEIKID_SIM_BOX'  => array('nama' => 'SANKEIKID SIM - BOX',  'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        'STANLEY_PACK' => array('nama' => 'STANLEY - PACK', 'mode' => 'BAG', 'sp' => 'sp_CetakLabel_BAG'),
        'STANLEY_BOX'  => array('nama' => 'STANLEY - BOX',  'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        'STANLEY_800_PACK' => array('nama' => 'STANLEY 800 - PACK', 'mode' => 'BAG', 'sp' => 'sp_CetakLabel_BAG'),
        'STANLEY_800_BOX'  => array('nama' => 'STANLEY 800 - BOX', 'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        'KATSUYAMA_BOX' => array('nama' => 'KATSUYAMA - BOX', 'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        'SIIX_PACK' => array('nama' => 'SIIX - PACK', 'mode' => 'BAG', 'sp' => 'sp_CetakLabel_BAG'),
        'SIIX_BOX'  => array('nama' => 'SIIX - BOX',  'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        'HIROSE_PACK' => array('nama' => 'HIROSE - PACK', 'mode' => 'BAG', 'sp' => 'sp_CetakLabel_BAG'),
        'HIROSE_BOX'  => array('nama' => 'HIROSE - BOX',  'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX'),

        'YAZAKI_BOX' => array('nama' => 'YAZAKI - BOX', 'mode' => 'BOX', 'sp' => 'sp_CetakLabel_BOX')
    );
}

$wo    = isset($_GET['wo']) ? trim($_GET['wo']) : '';
$jenis = isset($_GET['jenis']) ? strtoupper(trim($_GET['jenis'])) : 'STD_PACK';

if ($wo === '') {
    die("WO_NUMBER kosong.");
}

$labelTypes = get_label_types_print_page();

if (!isset($labelTypes[$jenis])) {
    die("Jenis label tidak dikenal: " . h_print($jenis));
}

$cfg       = $labelTypes[$jenis];
$namaLabel = $cfg['nama'];
$mode      = $cfg['mode'];
$spName    = $cfg['sp'];

// ================================
// SETTING AUTO CENTER A4
// ================================
$pageW = 210;
$pageH = 297;

if ($mode == 'BOX') {
    $labelClass  = 'label-box';
    $perPage     = 8;

    $labelW      = 90;
    $labelH      = 60;
    $cols        = 2;
    $rowsPerPage = 4;

    $gapX        = 10;
    $gapY        = 7;
} else {
    $labelClass  = 'label-bag';
    $perPage     = 10;

    $labelW      = 85;
    $labelH      = 50;
    $cols        = 2;
    $rowsPerPage = 5;

    $gapX        = 10;
    $gapY        = 7;
}

$totalLabelW = ($cols * $labelW) + (($cols - 1) * $gapX);
$totalLabelH = ($rowsPerPage * $labelH) + (($rowsPerPage - 1) * $gapY);

$leftM = ($pageW - $totalLabelW) / 2;
$topM  = ($pageH - $totalLabelH) / 2;

// + geser kanan/turun, - geser kiri/naik.
$shiftX = 0;
$shiftY = 0;

$leftM = $leftM + $shiftX;
$topM  = $topM + $shiftY;

$sql = "EXEC dbo." . $spName . " @WO_NUMBER = ?";
$stmt = sqlsrv_query($conn, $sql, array($wo));

if ($stmt === false) {
    echo "<h3>Query label error</h3>";
    echo "<pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
    exit;
}

$rows = array();

while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $r;
}

if (count($rows) == 0) {
    echo "<h3>Data label tidak ditemukan.</h3>";
    echo "<p>WO Number: <b>" . h_print($wo) . "</b></p>";
    echo "<p>Jenis: <b>" . h_print($namaLabel) . "</b></p>";
    echo "<p>Cek STD_PACK / STD_PACK_BOX dan data WO.</p>";
    exit;
}

$judul = "Cetak Label " . $namaLabel . " - " . $wo;
$totalRows = count($rows);
$totalPage = ceil($totalRows / $perPage);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo h_print($judul); ?></title>

    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #999;
            font-family: "Arial Narrow", Arial, sans-serif;
            color: #000;
        }

        table {
            border-collapse: collapse;
        }

        td {
            font-family: "Arial Narrow", Arial, sans-serif;
            font-weight: bold;
            vertical-align: top;
        }

        .toolbar {
            background: #222;
            color: #fff;
            padding: 8px 10px;
            font-size: 12px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            font-family: Arial, sans-serif;
        }

        .toolbar button {
            padding: 4px 12px;
            cursor: pointer;
            margin-right: 5px;
            font-weight: bold;
        }

        .toolbar a {
            color: #fff;
            text-decoration: none;
            margin-left: 10px;
        }

        .paper {
            width: 210mm;
            height: 297mm;
            margin: 38px auto 12px auto;
            background: #fff;
            position: relative;
            box-sizing: border-box;
            overflow: hidden;
        }

        .paper + .paper {
            page-break-before: always;
            break-before: page;
        }

        .label {
            position: absolute;
            box-sizing: border-box;
            padding: 0;
            overflow: visible;
            background: #fff;
        }

        .label-wrap {
            width: 100%;
            border: 1px solid #000;
            box-sizing: border-box;
            overflow: hidden;
            background: #fff;
        }

        .label-bag .label-wrap {
            height: 48mm;
        }

        .label-box .label-wrap {
            height: 57mm;
        }

        .label-head {
            border-bottom: 1px solid #000;
            padding: 1px 4px 0 4px;
            box-sizing: border-box;
        }

        .label-head-lh {
            padding: 0 !important;
        }

        .company {
            font-weight: bold;
            line-height: 1.05;
        }

        .subtitle {
            font-weight: bold;
            line-height: 1.05;
        }

        .lh-head-table,
        .ul-head-table {
            width: 100%;
            height: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .lh-head-left,
        .ul-head-left {
            padding: 1px 4px 0 4px;
            box-sizing: border-box;
            vertical-align: top;
        }

        .lh-head-right {
            width: 30%;
            text-align: center;
            vertical-align: middle !important;
            font-weight: bold;
            font-family: "Times New Roman", serif;
            border-left: 1px solid #000;
            white-space: nowrap;
            padding: 0 !important;
            overflow: hidden;
            text-overflow: clip;
        }

        .suzuki-head-right {
            width: 30%;
            text-align: center;
            vertical-align: middle !important;
            font-weight: bold;
            font-family: "Arial Narrow", Arial, sans-serif;
            border-left: 1px solid #000;
            white-space: nowrap;
            padding: 0 !important;
            overflow: hidden;
            text-overflow: clip;
        }

        .ul-head-right {
            width: 34%;
            text-align: center;
            vertical-align: middle !important;
            border-left: 1px solid #000;
            white-space: nowrap;
            padding: 0 !important;
            overflow: hidden;
            line-height: normal !important;
        }

        .ul-logo-box {
            width: 100%;
            height: 100%;
            display: table;
            table-layout: fixed;
            box-sizing: border-box;
            padding: 0.3mm 1mm;
        }

        .ul-logo-inner {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
        }

        .ul-img-full {
            display: inline-block;
            width: auto;
            max-width: 92%;
            vertical-align: middle;
        }

        .ul-fallback {
            display: none;
            font-family: Arial, sans-serif;
            font-weight: bold;
            font-size: 10px;
            line-height: 1;
        }

        .detail-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .detail-table td {
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            border-left: 0;
            border-top: 0;
            padding: 0 4px;
            line-height: 1.02;
            overflow: hidden;
            box-sizing: border-box;
        }

        .detail-table td:last-child {
            border-right: 0;
        }

        .cap {
            width: 32%;
            white-space: nowrap;
        }

        .value {
            width: 68%;
            white-space: normal;
        }

        .lot-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .lot-table td {
            border: 1px solid #000 !important;
            text-align: center;
            padding: 0;
            line-height: 1;
        }

        .qty-center {
            text-align: center;
            vertical-align: middle !important;
            font-weight: bold;
        }

        .wo-cell {
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
        }

        .sign-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .sign-table td {
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            border-left: 0;
            border-top: 0;
            text-align: center;
            font-weight: bold;
            overflow: hidden;
        }

        .sign-table td:last-child {
            border-right: 0;
        }

        .sign-table tr:last-child td {
            border-bottom: 0;
        }

        .footer-label {
            font-family: "Arial Narrow", Arial, sans-serif;
            font-weight: bold;
            line-height: 1;
            padding: 1px 2px 0 2px;
            box-sizing: border-box;
            height: 2.5mm;
            overflow: hidden;
        }

        .footer-no {
            float: right;
            padding-right: 2px;
        }

        /* PACK/BAG */
        .label-bag .company {
            font-size: 12px;
        }

        .label-bag .subtitle {
            font-size: 10px;
        }

        .label-bag .label-head {
            height: 6.2mm;
        }

        .label-bag .lh-head-right {
            font-size: 14px;
            letter-spacing: 0;
            height: 6.2mm;
            line-height: 6.2mm;
        }

        .label-bag .suzuki-head-right {
            font-size: 14px;
            height: 6.2mm;
            line-height: 6.2mm;
        }

        .label-bag .ul-head-right {
            height: 6.2mm;
        }

        .label-bag .ul-logo-box {
            height: 6.2mm;
        }

        .label-bag .ul-img-full {
            max-height: 3.8mm;
        }

        .label-bag .detail-table td {
            font-size: 11px;
            height: 3.55mm;
        }

        .label-bag .part-name-row td {
            height: 4.35mm;
        }

        .label-bag .mat-row td {
            height: 4.1mm;
        }

        .label-bag .lot-table td {
            height: 3.1mm;
            font-size: 9px;
        }

        .label-bag .qty-center {
            font-size: 11px !important;
        }

        .label-bag .wo-cell {
            font-size: 10px !important;
        }

        .label-bag .sign-title td {
            font-size: 10px;
            height: 3.3mm;
            vertical-align: middle;
        }

        .label-bag .sign-blank td {
            font-size: 10px;
            height: 7.7mm;
            vertical-align: middle;
        }

        .label-bag .footer-label {
            font-size: 5px;
            height: 2mm;
        }

        /* BOX */
        .label-box .company {
            font-size: 13px;
        }

        .label-box .subtitle {
            font-size: 11px;
        }

        .label-box .label-head {
            height: 7.2mm;
        }

        .label-box .lh-head-right {
            font-size: 17px;
            letter-spacing: 0;
            height: 7.2mm;
            line-height: 7.2mm;
        }

        .label-box .suzuki-head-right {
            font-size: 17px;
            height: 7.2mm;
            line-height: 7.2mm;
        }

        .label-box .ul-head-right {
            height: 7.2mm;
        }

        .label-box .ul-logo-box {
            height: 7.2mm;
        }

        .label-box .ul-img-full {
            max-height: 4.8mm;
        }

        .label-box .detail-table td {
            font-size: 11px;
            height: 4.45mm;
        }

        .label-box .part-name-row td {
            height: 5.5mm;
        }

        .label-box .mat-row td {
            height: 5.0mm;
        }

        .label-box .lot-table td {
            height: 4.0mm;
            font-size: 10px;
        }

        .label-box .qty-center {
            font-size: 12px !important;
        }

        .label-box .wo-cell {
            font-size: 10px !important;
        }

        .label-box .sign-title td {
            font-size: 11px;
            height: 4.2mm;
            vertical-align: middle;
        }

        .label-box .sign-blank td {
            font-size: 11px;
            height: 8.4mm;
            vertical-align: middle;
        }

        .label-box .footer-label {
            font-size: 6px;
            height: 2.3mm;
        }

        /* =========================
           KOITO TEMPLATE
           ========================= */
        .koito-wrap {
            width: 100%;
            height: 100%;
            border: 1px solid #000;
            box-sizing: border-box;
            overflow: visible;
            background: #fff;
            font-family: "Arial Narrow", Arial, sans-serif;
            font-weight: bold;
            position: relative;
        }

        .koito-table {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .koito-table td {
            border: 1px solid #000;
            padding: 0 3px;
            vertical-align: middle;
            font-weight: bold;
            box-sizing: border-box;
            overflow: hidden;
        }

        .koito-title {
            line-height: 1.05;
        }

        .koito-company {
            font-weight: bold;
        }

        .koito-subtitle {
            font-weight: bold;
        }

        .koito-cap {
            white-space: nowrap;
        }

        .koito-val {
            white-space: nowrap;
        }

        .koito-right-lh {
            text-align: center;
            vertical-align: middle !important;
            font-family: "Arial Black", Arial, sans-serif;
            font-weight: 900 !important;
            font-size: 50px !important;
            line-height: 1 !important;
            letter-spacing: 0;
            white-space: nowrap;
        }

        .koito-kanban-head {
            text-align: center;
            vertical-align: middle !important;
            font-family: "Arial Narrow", Arial, sans-serif;
            font-weight: 900;
            white-space: nowrap;
        }

        .koito-right-bottom {
            text-align: center;
            vertical-align: middle !important;
            font-family: "Arial Black", Arial, sans-serif;
            font-weight: 900 !important;
            font-size: 50px !important;
            line-height: 1 !important;
            letter-spacing: 0;
            white-space: nowrap;
        }

        .koito-lot-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .koito-lot-table td {
            border: 1px solid #000 !important;
            padding: 0;
            text-align: center;
            line-height: 1;
        }

        .koito-qty {
            text-align: center;
            font-weight: bold;
        }

        .koito-sign {
            text-align: center;
            font-weight: bold;
        }

        .koito-footer {
            position: absolute;
            left: 2mm;
            bottom: -2mm;
            font-family: "Arial Narrow", Arial, sans-serif;
            font-weight: bold;
            line-height: 1;
            background: #fff;
            padding: 0 1px 0 0;
        }

        .koito-footer-no {
            position: absolute;
            right: 2mm;
            bottom: -2mm;
            font-family: "Arial Narrow", Arial, sans-serif;
            font-weight: bold;
            line-height: 1;
            background: #fff;
            padding: 0 0 0 1px;
        }

        .label-bag .koito-company {
            font-size: 12px;
        }

        .label-bag .koito-subtitle {
            font-size: 10px;
        }

        .label-bag .koito-table td {
            font-size: 11px;
            height: 3.6mm;
        }

        .label-bag .koito-kanban-head {
            font-size: 12px;
        }

        .label-bag .koito-right-lh,
        .label-bag .koito-right-bottom {
            font-size: 18px !important;
            line-height: 1 !important;
            font-weight: 900 !important;
        }

        .label-bag .koito-lot-table td {
            height: 3.4mm;
            font-size: 10px;
        }

        .label-bag .koito-sign-head td {
            height: 3.4mm;
            font-size: 11px;
        }

        .label-bag .koito-sign-blank td {
            height: 7.8mm;
        }

        .label-bag .koito-footer,
        .label-bag .koito-footer-no {
            font-size: 5px;
        }

        .label-box .koito-company {
            font-size: 13px;
        }

        .label-box .koito-subtitle {
            font-size: 11px;
        }

        .label-box .koito-table td {
            font-size: 12px;
            height: 4.4mm;
        }

        .label-box .koito-kanban-head {
            font-size: 13px;
        }

        .label-box .koito-right-lh,
        .label-box .koito-right-bottom {
            font-size: 18px !important;
            line-height: 1 !important;
            font-weight: 900 !important;
        }

        .label-box .koito-lot-table td {
            height: 4.1mm;
            font-size: 11px;
        }

        .label-box .koito-sign-head td {
            height: 4.2mm;
            font-size: 12px;
        }

        .label-box .koito-sign-blank td {
            height: 9.6mm;
        }

        .label-box .koito-footer,
        .label-box .koito-footer-no {
            font-size: 6px;
        }

        /* =========================
           AUTOTECH BOX TEMPLATE
           ========================= */
        .autotech-wrap {
            width: 100%;
            height: 57mm;
            border: 1px solid #000;
            box-sizing: border-box;
            overflow: hidden;
            background: #fff;
            position: relative;
            font-family: "Arial Narrow", Arial, sans-serif;
            font-weight: bold;
        }

        .autotech-lh-box {
            position: absolute;
            right: 2mm;
            top: 1mm;
            width: 18mm;
            height: 3.8mm;
            border: 1px solid #000;
            text-align: center;
            line-height: 3.8mm;
            font-size: 12px;
            font-weight: bold;
        }

        .autotech-main {
            padding: 4mm 2mm 0 2mm;
            box-sizing: border-box;
        }

        .autotech-row-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 12px;
        }

        .autotech-row-table td {
            border: 0 !important;
            padding: 0 1mm;
            line-height: 4.8mm;
            height: 4.8mm;
            font-weight: bold;
        }

        .autotech-label {
            width: 26mm;
            white-space: nowrap;
        }

        .autotech-colon {
            width: 3mm;
            text-align: center;
        }

        .autotech-value {
            white-space: nowrap;
            overflow: hidden;
        }

        .autotech-barcode-part {
            width: 52mm;
            height: 8mm;
            margin: 1mm 0 2.5mm 0;
            max-width: 52mm;
        }

        .autotech-detail {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 12px;
        }

        .autotech-detail td {
            border: 0 !important;
            padding: 0 1mm;
            line-height: 4mm;
            height: 4mm;
            font-weight: bold;
        }

        .autotech-bottom {
            margin-top: 1mm;
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .autotech-sign-area {
            display: table-cell;
            width: 50mm;
            vertical-align: top;
        }

        .autotech-sign-table {
            width: 50mm;
            height: 13mm;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 11px;
        }

        .autotech-sign-table td {
            border: 1px solid #000 !important;
            text-align: center;
            font-weight: bold;
        }

        .autotech-qty-area {
            display: table-cell;
            vertical-align: top;
            padding-left: 10mm;
            font-size: 12px;
            font-weight: bold;
        }

        .autotech-qty-text {
            margin-bottom: 1mm;
        }

        .autotech-barcode-qty {
            width: 25mm;
            height: 7mm;
            max-width: 25mm;
        }

        .barcode-svg {
            display: block;
            background: #fff;
            shape-rendering: crispEdges;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            html, body {
                width: 210mm;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff;
                overflow: visible;
            }

            .toolbar {
                display: none !important;
            }

            .paper {
                width: 210mm;
                height: 297mm;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff;
                overflow: hidden;
                page-break-after: auto !important;
                break-after: auto !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .paper + .paper {
                page-break-before: always !important;
                break-before: page !important;
            }

            .paper:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }

            .barcode-svg {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
        }
		/* =========================
   AUTOLIV PACK TEMPLATE
   ========================= */
.autoliv-wrap {
    width: 100%;
    height: 48mm;
    border: 1px solid #000;
    box-sizing: border-box;
    overflow: hidden;
    background: #fff;
    font-family: "Times New Roman", serif;
    font-weight: normal;
    padding: 1.2mm 2mm;
}

.autoliv-row {
    position: relative;
    height: 8.7mm;
    margin-bottom: 1.2mm;
}

.autoliv-title {
    font-size: 12px;
    line-height: 1;
    font-weight: normal;
}

.autoliv-value-right {
    position: absolute;
    right: 0;
    top: 0;
    font-size: 12px;
    line-height: 1;
    text-align: right;
    font-weight: normal;
}

.autoliv-barcode {
    width: 43mm;
    height: 5.8mm;
    margin-top: 0.5mm;
}

.autoliv-barcode-long {
    width: 58mm;
    height: 6.2mm;
    margin-top: 0.5mm;
}

.autoliv-barcode-qty {
    width: 13mm;
    height: 6mm;
    margin-top: 0.5mm;
}

.autoliv-batch-row {
    height: 5mm;
}

.label-bag .autoliv-wrap {
    height: 48mm;
}

.label-box .autoliv-wrap {
    height: 57mm;
}
    </style>
</head>
<body>

<div class="toolbar">
    <button onclick="window.print()">Print</button>
    <button onclick="window.close()">Close</button>

    <b><?php echo h_print($judul); ?></b>
    &nbsp; | &nbsp;
    Mode: <?php echo h_print($mode); ?>
    &nbsp; | &nbsp;
    Per A4: <?php echo h_print($perPage); ?> label
    &nbsp; | &nbsp;
    Total: <?php echo h_print($totalRows); ?> label
    &nbsp; | &nbsp;
    Page: <?php echo h_print($totalPage); ?>

    <a href="label_plant2.php">Kembali</a>
</div>

<?php
$globalIndex = 0;

for ($pageNo = 1; $pageNo <= $totalPage; $pageNo++):
?>
<div class="paper">
<?php
    $start = ($pageNo - 1) * $perPage;
    $end   = $start + $perPage;

    for ($idx = $start; $idx < $end; $idx++):
        if (!isset($rows[$idx])) {
            break;
        }

        $row = $rows[$idx];
        $globalIndex++;

        $noLabel    = getv_print($row, 'NO_LABEL', $globalIndex);
        $totalLabel = getv_print($row, 'TOTAL_LABEL', $totalRows);

        $customer = getv_print($row, 'CUST_COMP', '');
        if ($customer == '') {
            $customer = getv_print($row, 'CUST_ALIAS', '');
        }

        $itemCode = getv_print($row, 'ITEM_CODE', '');
        $itemName = getv_print($row, 'ITEM_NAME', '');
        $itemNo   = getv_print($row, 'ITEM_NO', '');
        $maker    = getv_print($row, 'MAKER', '');
        $matName  = getv_print($row, 'MAT_NAME', '');
        $woNumber = getv_print($row, 'WO_NUMBER', $wo);
        $lotNo    = get_type_lot_print($row);

        $specialText = trim((string)getv_print($row, 'SPESIAL', ''));

        $isLHLabel     = (strpos($jenis, 'LH') !== false && $specialText !== '');
        $isULLabel     = (strpos($jenis, 'UL') !== false);
        $isSuzukiLabel = (strpos($jenis, 'SUZUKI') !== false);
        $isAutotechBox = (strpos($jenis, 'AUTOTECH') !== false);
        $isKoitoLabel  = (strpos($jenis, 'KOITO') !== false);
		$isAutolivPack = ($jenis == 'AUTOLIV_PACK');

$autolivSupplierCode = '926992';
$autolivSerial       = '00000';
$autolivPartNo       = trim((string)$itemNo);
$autolivBatch        = '';
        $suzukiText = trim((string)$itemNo);

        // KOITO:
        // SPESIAL  = box atas
        // SPESIAL2 = box bawah
        $koitoSpecialText  = trim((string)getv_print($row, 'SPESIAL', ''));
        $koitoSpecial2Text = trim((string)getv_print($row, 'SPESIAL2', ''));

        $stdPack  = (int)getv_print($row, 'STD_PACK', 0);
        $stdBox   = (int)getv_print($row, 'STD_PACK_BOX', 0);
        $qtyLabel = (int)getv_print($row, 'QTY_LABEL', 0);

        if ($mode == 'BOX') {
            $packingQty = $stdBox;
        } else {
            $packingQty = $stdPack;
        }

        if ($packingQty <= 0) {
            $packingQty = $qtyLabel;
        }

        $qtyText = number_format($packingQty) . ' Pcs';
		// Untuk AUTOLIV barcode quantity
        $autolivQty = (string)$packingQty;

        $posInPage = $idx - $start;
        $rowNo = floor($posInPage / $cols);
        $colNo = $posInPage % $cols;

        $leftPos = $leftM + ($colNo * ($labelW + $gapX));
        $topPos  = $topM  + ($rowNo * ($labelH + $gapY));
?>
    <div class="label <?php echo h_print($labelClass); ?>"
         style="left:<?php echo h_print($leftPos); ?>mm;
                top:<?php echo h_print($topPos); ?>mm;
                width:<?php echo h_print($labelW); ?>mm;
                height:<?php echo h_print($labelH); ?>mm;">

        <?php if ($isAutotechBox): ?>

            <div class="autotech-wrap">
                <?php if ($specialText !== ''): ?>
                    <div class="autotech-lh-box"><?php echo h_print($specialText); ?></div>
                <?php endif; ?>

                <div class="autotech-main">
                    <table class="autotech-row-table">
                        <tr>
                            <td class="autotech-label">Supplier Name</td>
                            <td class="autotech-colon">:</td>
                            <td class="autotech-value">PT. IMC TEKNO INDONESIA</td>
                        </tr>
                        <tr>
                            <td class="autotech-label">Part Number</td>
                            <td class="autotech-colon">:</td>
                            <td class="autotech-value"><?php echo h_print($itemNo); ?></td>
                        </tr>
                    </table>

                    <?php echo code39_svg($itemNo, 'autotech-barcode-part', 34); ?>

                    <table class="autotech-detail">
                        <tr>
                            <td class="autotech-label">Part Name</td>
                            <td class="autotech-colon">:</td>
                            <td class="autotech-value"><?php echo h_print($itemName); ?></td>
                        </tr>
                        <tr>
                            <td class="autotech-label">Part Code</td>
                            <td class="autotech-colon">:</td>
                            <td class="autotech-value"><?php echo h_print($itemCode); ?></td>
                        </tr>
                        <tr>
                            <td class="autotech-label">lot Number</td>
                            <td class="autotech-colon">:</td>
                            <td class="autotech-value"><?php echo h_print($lotNo); ?></td>
                        </tr>
                    </table>

                    <div class="autotech-bottom">
                        <div class="autotech-sign-area">
                            <table class="autotech-sign-table">
                                <tr>
                                    <td>Operator</td>
                                    <td>QC</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </table>
                        </div>

                        <div class="autotech-qty-area">
                            <div class="autotech-qty-text">QTY : <?php echo h_print(number_format($packingQty)); ?></div>
                            <?php echo code39_svg((string)$packingQty, 'autotech-barcode-qty', 26); ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($isKoitoLabel): ?>

            <div class="koito-wrap">
                <table class="koito-table">
                    <colgroup>
                        <col style="width:35%;">
                        <col style="width:45%;">
                        <col style="width:20%;">
                    </colgroup>

                    <tr>
                        <td colspan="2" class="koito-title">
                            <div class="koito-company">PT IMC TEKNO INDONESIA</div>
                            <div class="koito-subtitle">PLASTIC PRECISION INJECTION &amp; ASSY</div>
                        </td>
                        <td rowspan="2" class="koito-right-lh">
                            <?php echo h_print($koitoSpecialText); ?>
                        </td>
                    </tr>

                    <tr>
                        <td class="koito-cap">CUSTOMER</td>
                        <td class="koito-val"><?php echo h_print($customer); ?></td>
                    </tr>

                    <tr>
                        <td class="koito-cap">PART NAME</td>
                        <td class="koito-val"><?php echo h_print($itemName); ?></td>
                        <td class="koito-kanban-head">KANBAN ID</td>
                    </tr>

                    <tr>
                        <td class="koito-cap">PART NO</td>
                        <td class="koito-val"><?php echo h_print($itemNo); ?></td>
                        <td rowspan="2" class="koito-right-bottom">
                            <?php echo h_print($koitoSpecial2Text); ?>
                        </td>
                    </tr>

                    <tr>
                        <td class="koito-cap">PART CODE &amp; WO</td>
                        <td class="koito-val"><?php echo h_print($itemCode); ?></td>
                    </tr>

                    <tr>
                        <td class="koito-cap">MAT'L MAKER</td>
                        <td colspan="2" class="koito-val"><?php echo h_print($maker); ?></td>
                    </tr>

                    <tr>
                        <td class="koito-cap">MAT'L NAME &amp; GRADE</td>
                        <td colspan="2" class="koito-val"><?php echo h_print($matName); ?></td>
                    </tr>

                    <tr>
                        <td class="koito-cap">LOT NO</td>
                        <td colspan="2"><?php echo koito_lot_box_html($lotNo); ?></td>
                    </tr>

                    <tr>
                        <td class="koito-cap">QTY PACKING &amp; STATUS</td>
                        <td colspan="2" class="koito-qty"><?php echo h_print($qtyText); ?></td>
                    </tr>

                    <tr class="koito-sign-head">
                        <td class="koito-sign">OPERATOR</td>
                        <td class="koito-sign">QC</td>
                        <td class="koito-sign">PACKING NO</td>
                    </tr>

                    <tr class="koito-sign-blank">
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>

                <div class="koito-footer">FM.PD.00-14 REV 5 (04/06/08)</div>
                <div class="koito-footer-no"><?php echo h_print($noLabel); ?> /</div>
            </div>

        <?php else: ?>

            <div class="label-wrap">
                <?php if ($isULLabel): ?>
                    <div class="label-head label-head-lh">
                        <table class="ul-head-table">
                            <tr>
                                <td class="ul-head-left">
                                    <div class="company">PT. IMC TEKNO INDONESIA</div>
                                    <div class="subtitle">PLASTIC PRESSION INJECTION &amp; ASSY</div>
                                </td>
                                <td class="ul-head-right">
                                    <div class="ul-logo-box">
                                        <div class="ul-logo-inner">
                                            <img src="/msii/assets/ul_h1724.png"
                                                 class="ul-img-full"
                                                 alt="UL H1724"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';"><span class="ul-fallback">UL H 1724</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>

                <?php elseif ($isSuzukiLabel): ?>
                    <div class="label-head label-head-lh">
                        <table class="lh-head-table">
                            <tr>
                                <td class="lh-head-left">
                                    <div class="company">PT. IMC TEKNO INDONESIA</div>
                                    <div class="subtitle">PLASTIC PRESSION INJECTION &amp; ASSY</div>
                                </td>
                                <td class="suzuki-head-right">
                                    <?php echo h_print($suzukiText); ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                <?php elseif ($isLHLabel): ?>
                    <div class="label-head label-head-lh">
                        <table class="lh-head-table">
                            <tr>
                                <td class="lh-head-left">
                                    <div class="company">PT. IMC TEKNO INDONESIA</div>
                                    <div class="subtitle">PLASTIC PRESSION INJECTION &amp; ASSY</div>
                                </td>
                                <td class="lh-head-right">
                                    <?php echo h_print($specialText); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
				<?php elseif ($isAutolivPack): ?>

    <div class="autoliv-wrap">

        <div class="autoliv-row">
            <div class="autoliv-title">Supplier Code (V)</div>
            <div class="autoliv-value-right"><?php echo h_print($autolivSupplierCode); ?></div>
            <?php echo code39_svg($autolivSupplierCode, 'autoliv-barcode', 28); ?>
        </div>

        <div class="autoliv-row">
            <div class="autoliv-title">Serial (S)</div>
            <div class="autoliv-value-right"><?php echo h_print($autolivSerial); ?></div>
            <?php echo code39_svg($autolivSerial, 'autoliv-barcode', 28); ?>
        </div>

        <div class="autoliv-row">
            <div class="autoliv-title">Part No (P)</div>
            <div class="autoliv-value-right"><?php echo h_print($autolivPartNo); ?></div>
            <?php echo code39_svg($autolivPartNo, 'autoliv-barcode-long', 30); ?>
        </div>

        <div class="autoliv-row">
            <div class="autoliv-title">Quantity (Q)</div>
            <div class="autoliv-value-right"><?php echo h_print(number_format($packingQty)); ?></div>
            <?php echo code39_svg($autolivQty, 'autoliv-barcode-qty', 28); ?>
        </div>

        <div class="autoliv-batch-row">
            <div class="autoliv-title">Batch No (H)</div>
        </div>

    </div>	

                <?php else: ?>
                    <div class="label-head">
                        <div class="company">PT. IMC TEKNO INDONESIA</div>
                        <div class="subtitle">PLASTIC PRESSION INJECTION &amp; ASSY</div>
                    </div>
                <?php endif; ?>

                <table width="100%" class="detail-table">
                    <colgroup>
                        <col style="width:32%;">
                        <col style="width:28%;">
                        <col style="width:20%;">
                        <col style="width:20%;">
                    </colgroup>

                    <tr>
                        <td class="cap">Customer</td>
                        <td class="value" colspan="3"><?php echo h_print($customer); ?></td>
                    </tr>

                    <tr class="part-name-row">
                        <td class="cap">Part Name</td>
                        <td class="value" colspan="3"><?php echo h_print($itemName); ?></td>
                    </tr>

                    <tr>
                        <td class="cap">Part No</td>
                        <td class="value" colspan="3"><?php echo h_print($itemNo); ?></td>
                    </tr>

                    <tr>
                        <td class="cap">Part Code &amp; Wo.No</td>
                        <td colspan="2"><?php echo h_print($itemCode); ?></td>
                        <td class="wo-cell"><?php echo h_print($woNumber); ?></td>
                    </tr>

                    <tr>
                        <td class="cap">Mat'l Maker</td>
                        <td class="value" colspan="3"><?php echo h_print($maker); ?></td>
                    </tr>

                    <tr class="mat-row">
                        <td class="cap">Mat'l Name &amp; Grade</td>
                        <td class="value" colspan="3"><?php echo h_print($matName); ?></td>
                    </tr>

                    <tr>
                        <td class="cap">Lot No</td>
                        <td colspan="3"><?php echo lot_box_html($lotNo); ?></td>
                    </tr>

                    <tr>
                        <td class="cap">Qty/Packing &amp; Status</td>
                        <td colspan="3" class="qty-center"><?php echo h_print($qtyText); ?></td>
                    </tr>
                </table>

                <table class="sign-table">
                    <colgroup>
                        <col style="width:32%;">
                        <col style="width:34%;">
                        <col style="width:34%;">
                    </colgroup>

                    <tr class="sign-title">
                        <td>Operator</td>
                        <td>QC</td>
                        <td>Packing No</td>
                    </tr>

                    <tr class="sign-blank">
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
            </div>

            <div class="footer-label">
                <span>FM.PD.00-14 REV 5 (04/06/08)</span>
                <span class="footer-no"><?php echo h_print($noLabel); ?></span>
            </div>

        <?php endif; ?>
    </div>
<?php
    endfor;
?>
</div>
<?php endfor; ?>

<script>
    // Aktifkan kalau ingin otomatis print:
    // window.onload = function() { window.print(); };
</script>

</body>
</html>