<?php
/*
    price_epro_import_progress_php54_FIXED_DELIMITER.php
    PHP 5.4 + SQL Server 2008
    Table: dbo.PRICE_EPRO

    Perbaikan:
    - Nama file CSV bebas.
    - Tidak tergantung posisi kolom kalau header ada.
    - Sistem baca header:
        Invoice No.      -> INV_NO
        Delivery Date    -> INV_DATE
        Pur.Org          -> LOC
        Item Code        -> ITEM_NO
        Item Description -> ITEM_NAME
        PO No.           -> PO
        Quantity         -> QTY
        Unit Price       -> PRICE_EPRO
    - Jika header tidak ditemukan, fallback posisi:
        B=INV_NO, D=LOC, E=ITEM_NO, F=ITEM_NAME, G=INV_DATE, J=PO, K=QTY, M=PRICE_EPRO
    - Baris kosong/header/total otomatis skip.
    - Progress bar upload + import.
*/

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);

/* =========================
   LOAD CONFIG DATABASE
   ========================= */
$configs = array(
    __DIR__ . '/../config/db_plant1.php',
    __DIR__ . '/config/db_plant1.php',
    __DIR__ . '/../config/db_plant2.php',
    __DIR__ . '/config/db_plant2.php'
);

$configLoaded = false;

foreach ($configs as $cfg) {
    if (file_exists($cfg)) {
        require_once $cfg;
        $configLoaded = true;
        break;
    }
}

if (!$configLoaded) {
    die('Config database tidak ditemukan. Cek path db_plant1.php / db_plant2.php.');
}

if (!isset($conn) || $conn === false) {
    die('Koneksi SQL Server gagal.');
}

/* =========================
   HELPER
   ========================= */
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function jsonOut($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

function sqlErrorText() {
    $errs = sqlsrv_errors();

    if (!$errs) {
        return 'Unknown SQL Error';
    }

    $msg = array();

    foreach ($errs as $e) {
        $msg[] = '[' . $e['SQLSTATE'] . '] ' . $e['code'] . ' - ' . $e['message'];
    }

    return implode("\n", $msg);
}

function q($sql, $params) {
    global $conn;

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die('<pre>SQL ERROR: ' . h(sqlErrorText()) . "\n\nSQL:\n" . h($sql) . '</pre>');
    }

    return $stmt;
}

function qSafe($sql, $params, &$err) {
    global $conn;

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $err = sqlErrorText();
        return false;
    }

    return $stmt;
}

function nval($v) {
    if ($v === null || $v === '') {
        return 0;
    }

    $s = trim((string)$v);
    $s = str_replace(',', '', $s);
    $s = str_replace(' ', '', $s);

    return (float)$s;
}

function intvalClean($v) {
    if ($v === null || $v === '') {
        return 0;
    }

    $s = trim((string)$v);
    $s = str_replace(',', '', $s);
    $s = str_replace(' ', '', $s);

    return (int)$s;
}

function cleanStr($s, $len) {
    $s = trim((string)$s);
    return substr($s, 0, $len);
}

function cleanHeader($s) {
    $s = strtoupper(trim((string)$s));
    $s = str_replace("\xEF\xBB\xBF", '', $s);
    $s = str_replace(array('.', ' ', '-', '/', '#'), '_', $s);
    while (strpos($s, '__') !== false) {
        $s = str_replace('__', '_', $s);
    }
    return trim($s, '_');
}

function validDateParts($y, $m, $d) {
    if ($y < 1753 || $y > 9999) {
        return false;
    }

    return checkdate((int)$m, (int)$d, (int)$y);
}

function toSqlDate($v) {
    $v = trim((string)$v);

    if ($v == '') {
        return null;
    }

    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $v, $m)) {
        $p = explode('-', $m[1]);

        if (validDateParts((int)$p[0], (int)$p[1], (int)$p[2])) {
            return $m[1];
        }

        return null;
    }

    if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $v, $m)) {
        $y = (int)$m[1];
        $mo = (int)$m[2];
        $d = (int)$m[3];

        if (validDateParts($y, $mo, $d)) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }

        return null;
    }

    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $v, $m)) {
        $y = (int)$m[1];
        $mo = (int)$m[2];
        $d = (int)$m[3];

        if (validDateParts($y, $mo, $d)) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }

        return null;
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $v, $m)) {
        $p1 = (int)$m[1];
        $p2 = (int)$m[2];
        $y  = (int)$m[3];

        /* File Epson: mm/dd/yyyy */
        if ($p1 > 12) {
            $mo = $p2;
            $d  = $p1;
        } else {
            $mo = $p1;
            $d  = $p2;
        }

        if (validDateParts($y, $mo, $d)) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }

        return null;
    }

    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $v, $m)) {
        $p1 = (int)$m[1];
        $p2 = (int)$m[2];
        $y  = (int)$m[3];

        if ($p1 > 12) {
            $mo = $p2;
            $d  = $p1;
        } else {
            $mo = $p1;
            $d  = $p2;
        }

        if (validDateParts($y, $mo, $d)) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }

        return null;
    }

    if (is_numeric($v) && (float)$v > 20000 && (float)$v < 60000) {
        $unix = ((float)$v - 25569) * 86400;
        $date = gmdate('Y-m-d', $unix);
        $p = explode('-', $date);

        if (validDateParts((int)$p[0], (int)$p[1], (int)$p[2])) {
            return $date;
        }

        return null;
    }

    $t = strtotime($v);

    if ($t !== false) {
        $date = date('Y-m-d', $t);
        $p = explode('-', $date);

        if (validDateParts((int)$p[0], (int)$p[1], (int)$p[2])) {
            return $date;
        }
    }

    return null;
}

function fmtDateView($v) {
    if ($v instanceof DateTime) {
        return $v->format('Y-m-d');
    }

    if ($v == '') {
        return '';
    }

    $t = strtotime((string)$v);

    if ($t !== false) {
        return date('Y-m-d', $t);
    }

    return (string)$v;
}

function detectDelimiter($file) {
    /*
        Deteksi delimiter harus membaca beberapa baris non-kosong.
        File Epson sering punya baris kosong di awal.
        Kalau hanya baca baris pertama, delimiter bisa salah menjadi "|"
        dan semua kolom terbaca sebagai 1 kolom.
    */
    $fh = fopen($file, 'r');

    if (!$fh) {
        return ',';
    }

    $delims = array(',' => 0, ';' => 0, "\t" => 0, '|' => 0);
    $checked = 0;

    while (($line = fgets($fh)) !== false) {
        $line = trim($line);

        if ($line == '') {
            continue;
        }

        foreach ($delims as $d => $v) {
            $delims[$d] += substr_count($line, $d);
        }

        $checked++;

        if ($checked >= 20) {
            break;
        }
    }

    fclose($fh);

    $best = ',';
    $bestCount = -1;

    foreach ($delims as $d => $count) {
        if ($count > $bestCount) {
            $best = $d;
            $bestCount = $count;
        }
    }

    if ($bestCount <= 0) {
        return ',';
    }

    return $best;
}

function normalizeCsvRow($row) {
    $out = array();

    for ($i = 0; $i < 30; $i++) {
        $out[$i] = isset($row[$i]) ? trim($row[$i]) : '';
    }

    return $out;
}

function isBlankCsvRow($row) {
    if (!is_array($row) || count($row) == 0) {
        return true;
    }

    foreach ($row as $v) {
        if (trim((string)$v) != '') {
            return false;
        }
    }

    return true;
}

function isTotalOrFooterRow($row) {
    $joined = strtoupper(implode('|', $row));

    if (strpos($joined, 'TOTAL INVOICE') !== false ||
        strpos($joined, 'GRAND TOTAL') !== false ||
        strpos($joined, 'SUB TOTAL') !== false) {
        return true;
    }

    return false;
}

function isHeaderRow($row) {
    $joined = strtoupper(implode('|', $row));

    if (
        strpos($joined, 'INVOICE') !== false &&
        strpos($joined, 'ITEM') !== false &&
        strpos($joined, 'QUANTITY') !== false &&
        strpos($joined, 'UNIT PRICE') !== false
    ) {
        return true;
    }

    return false;
}

function buildHeaderMap($row) {
    $map = array();

    for ($i = 0; $i < count($row); $i++) {
        $key = cleanHeader($row[$i]);

        if ($key != '') {
            $map[$key] = $i;
        }
    }

    $out = array();

    if (isset($map['INVOICE_NO'])) {
        $out['INV_NO'] = $map['INVOICE_NO'];
    } elseif (isset($map['INV_NO'])) {
        $out['INV_NO'] = $map['INV_NO'];
    }

    if (isset($map['DELIVERY_DATE'])) {
        $out['INV_DATE'] = $map['DELIVERY_DATE'];
    } elseif (isset($map['INVOICE_DATE'])) {
        $out['INV_DATE'] = $map['INVOICE_DATE'];
    } elseif (isset($map['INV_DATE'])) {
        $out['INV_DATE'] = $map['INV_DATE'];
    }

    if (isset($map['PUR_ORG'])) {
        $out['LOC'] = $map['PUR_ORG'];
    } elseif (isset($map['LOC'])) {
        $out['LOC'] = $map['LOC'];
    }

    if (isset($map['ITEM_CODE'])) {
        $out['ITEM_NO'] = $map['ITEM_CODE'];
    } elseif (isset($map['ITEM_NO'])) {
        $out['ITEM_NO'] = $map['ITEM_NO'];
    }

    if (isset($map['ITEM_DESCRIPTION'])) {
        $out['ITEM_NAME'] = $map['ITEM_DESCRIPTION'];
    } elseif (isset($map['ITEM_NAME'])) {
        $out['ITEM_NAME'] = $map['ITEM_NAME'];
    }

    if (isset($map['PO_NO'])) {
        $out['PO'] = $map['PO_NO'];
    } elseif (isset($map['PO'])) {
        $out['PO'] = $map['PO'];
    }

    if (isset($map['QUANTITY'])) {
        $out['QTY'] = $map['QUANTITY'];
    } elseif (isset($map['QTY'])) {
        $out['QTY'] = $map['QTY'];
    }

    if (isset($map['UNIT_PRICE'])) {
        $out['PRICE_EPRO'] = $map['UNIT_PRICE'];
    } elseif (isset($map['PRICE_EPRO'])) {
        $out['PRICE_EPRO'] = $map['PRICE_EPRO'];
    } elseif (isset($map['PRICE'])) {
        $out['PRICE_EPRO'] = $map['PRICE'];
    }

    return $out;
}

function defaultMap() {
    return array(
        'INV_NO'     => 1,   // B = Invoice No.
        'LOC'        => 3,   // D = Pur.Org
        'ITEM_NO'    => 4,   // E = Item Code
        'ITEM_NAME'  => 5,   // F = Item Description
        'INV_DATE'   => 6,   // G = Delivery Date
        'PO'         => 9,   // J = PO No.
        'QTY'        => 10,  // K = Quantity
        'PRICE_EPRO' => 12   // M = Unit Price
    );
}

function colVal($row, $map, $key) {
    $idx = isset($map[$key]) ? $map[$key] : -1;

    if ($idx < 0) {
        return '';
    }

    return isset($row[$idx]) ? $row[$idx] : '';
}

function mapCsvRowToEpro($row, $map) {
    $row = normalizeCsvRow($row);

    $rawDate = colVal($row, $map, 'INV_DATE');

    return array(
        'INV_NO'     => cleanStr(colVal($row, $map, 'INV_NO'), 50),
        'LOC'        => cleanStr(colVal($row, $map, 'LOC'), 4),
        'ITEM_NO'    => cleanStr(colVal($row, $map, 'ITEM_NO'), 9),
        'ITEM_NAME'  => cleanStr(colVal($row, $map, 'ITEM_NAME'), 50),
        'INV_DATE'   => toSqlDate($rawDate),
        'RAW_DATE'   => trim((string)$rawDate),
        'PO'         => cleanStr(colVal($row, $map, 'PO'), 16),
        'QTY'        => intvalClean(colVal($row, $map, 'QTY')),
        'PRICE_EPRO' => nval(colVal($row, $map, 'PRICE_EPRO'))
    );
}

/* =========================
   PROGRESS SESSION
   ========================= */
function progressReset($fileName) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['epro_progress'] = array(
        'file' => $fileName,
        'status' => 'starting',
        'current' => 0,
        'total' => 0,
        'percent' => 0,
        'message' => 'Mulai import...',
        'insert_update' => 0,
        'skip' => 0,
        'error' => ''
    );

    session_write_close();
}

function progressUpdate($status, $current, $total, $message, $ok, $skip, $error) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    $percent = 0;

    if ($total > 0) {
        $percent = round(($current / $total) * 100);

        if ($percent > 100) {
            $percent = 100;
        }
    }

    $_SESSION['epro_progress']['status'] = $status;
    $_SESSION['epro_progress']['current'] = $current;
    $_SESSION['epro_progress']['total'] = $total;
    $_SESSION['epro_progress']['percent'] = $percent;
    $_SESSION['epro_progress']['message'] = $message;
    $_SESSION['epro_progress']['insert_update'] = $ok;
    $_SESSION['epro_progress']['skip'] = $skip;
    $_SESSION['epro_progress']['error'] = $error;

    session_write_close();
}

function getCsvTotalRows($file) {
    $total = 0;
    $fh = fopen($file, 'r');

    if (!$fh) {
        return 0;
    }

    while (($line = fgets($fh)) !== false) {
        $total++;
    }

    fclose($fh);
    return $total;
}

/* =========================
   DATABASE PROCESS
   ========================= */
function clearData() {
    q("DELETE FROM dbo.PRICE_EPRO", array());
    return 'Semua data PRICE_EPRO berhasil dihapus.';
}

function saveEproRow($d, &$err) {
    $sql = "
        IF EXISTS (
            SELECT 1
            FROM dbo.PRICE_EPRO
            WHERE ISNULL(INV_NO,'') = ?
              AND ISNULL(ITEM_NO,'') = ?
              AND ISNULL(PO,'') = ?
        )
        BEGIN
            UPDATE dbo.PRICE_EPRO
            SET LOC = ?,
                ITEM_NAME = ?,
                INV_DATE = ?,
                QTY = ?,
                PRICE_EPRO = ?
            WHERE ISNULL(INV_NO,'') = ?
              AND ISNULL(ITEM_NO,'') = ?
              AND ISNULL(PO,'') = ?
        END
        ELSE
        BEGIN
            INSERT INTO dbo.PRICE_EPRO
            (
                INV_NO,
                LOC,
                ITEM_NO,
                ITEM_NAME,
                INV_DATE,
                PO,
                QTY,
                PRICE_EPRO
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?
            )
        END
    ";

    $params = array(
        $d['INV_NO'],
        $d['ITEM_NO'],
        $d['PO'],

        $d['LOC'],
        $d['ITEM_NAME'],
        $d['INV_DATE'],
        $d['QTY'],
        $d['PRICE_EPRO'],

        $d['INV_NO'],
        $d['ITEM_NO'],
        $d['PO'],

        $d['INV_NO'],
        $d['LOC'],
        $d['ITEM_NO'],
        $d['ITEM_NAME'],
        $d['INV_DATE'],
        $d['PO'],
        $d['QTY'],
        $d['PRICE_EPRO']
    );

    $stmt = qSafe($sql, $params, $err);

    if ($stmt === false) {
        return false;
    }

    return true;
}

function importCsv($file, $originalName) {
    global $conn;

    progressReset($originalName);

    $delimiter = detectDelimiter($file);
    $totalRows = getCsvTotalRows($file);

    progressUpdate('upload_done', 0, $totalRows, 'Upload selesai, mulai baca CSV...', 0, 0, '');

    $fh = fopen($file, 'r');

    if (!$fh) {
        progressUpdate('error', 0, $totalRows, 'File CSV tidak bisa dibuka.', 0, 0, 'File CSV tidak bisa dibuka.');
        return array(false, 'File CSV tidak bisa dibuka.');
    }

    sqlsrv_begin_transaction($conn);

    $rowNo = 0;
    $ok = 0;
    $skip = 0;
    $commitEvery = 500;
    $map = defaultMap();
    $mapInfo = 'fallback posisi B,D,E,F,G,J,K,M';

    while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
        $rowNo++;

        if (isBlankCsvRow($row)) {
            $skip++;
            continue;
        }

        if (isHeaderRow($row)) {
            $headerMap = buildHeaderMap($row);

            if (isset($headerMap['INV_NO']) &&
                isset($headerMap['INV_DATE']) &&
                isset($headerMap['LOC']) &&
                isset($headerMap['ITEM_NO']) &&
                isset($headerMap['ITEM_NAME']) &&
                isset($headerMap['PO']) &&
                isset($headerMap['QTY']) &&
                isset($headerMap['PRICE_EPRO'])) {

                $map = $headerMap;
                $mapInfo = 'header CSV otomatis';
            }

            $skip++;
            progressUpdate('importing', $rowNo, $totalRows, 'Header ditemukan, mapping: ' . $mapInfo, $ok, $skip, '');
            continue;
        }

        if (isTotalOrFooterRow($row)) {
            $skip++;
            continue;
        }

        $d = mapCsvRowToEpro($row, $map);

        if ($d['INV_NO'] == '' && $d['ITEM_NO'] == '' && $d['PO'] == '') {
            $skip++;
            continue;
        }

        if ($d['INV_NO'] == '' || $d['ITEM_NO'] == '') {
            $skip++;
            progressUpdate(
                'importing',
                $rowNo,
                $totalRows,
                'Skip baris ' . $rowNo . ' karena bukan detail invoice.',
                $ok,
                $skip,
                ''
            );
            continue;
        }

        if ($d['RAW_DATE'] != '' && $d['INV_DATE'] === null) {
            $skip++;
            progressUpdate(
                'importing',
                $rowNo,
                $totalRows,
                'Skip baris ' . $rowNo . ' karena tanggal tidak valid: ' . $d['RAW_DATE'],
                $ok,
                $skip,
                ''
            );
            continue;
        }

        $err = '';
        $res = saveEproRow($d, $err);

        if (!$res) {
            sqlsrv_rollback($conn);
            fclose($fh);

            $msg = 'Import gagal baris ' . $rowNo . ': ' . $err . "\nFile: " . $originalName;
            progressUpdate('error', $rowNo, $totalRows, $msg, $ok, $skip, $msg);

            return array(false, $msg);
        }

        $ok++;

        if (($rowNo % 50) == 0) {
            progressUpdate(
                'importing',
                $rowNo,
                $totalRows,
                'Import database: ' . $rowNo . ' / ' . $totalRows . ' baris',
                $ok,
                $skip,
                ''
            );
        }

        if (($ok % $commitEvery) == 0) {
            sqlsrv_commit($conn);
            sqlsrv_begin_transaction($conn);
        }
    }

    fclose($fh);
    sqlsrv_commit($conn);

    if ($ok == 0) {
        $msg = 'Import selesai tetapi tidak ada data yang masuk.' .
            "\nFile: " . $originalName .
            "\nBerhasil: 0, Skip: " . $skip .
            "\nDelimiter terdeteksi: " . $delimiter .
            "\nMapping: " . $mapInfo .
            "\nKemungkinan delimiter/format CSV tidak sesuai atau kolom INV_NO / ITEM_NO kosong.";

        progressUpdate('done', $totalRows, $totalRows, $msg, $ok, $skip, '');

        return array(true, $msg);
    }

    $msg = 'Upload berhasil. File: ' . $originalName .
        "\nImport CSV selesai. Berhasil: " . $ok .
        ', Skip: ' . $skip .
        ', Delimiter: ' . $delimiter .
        ', Mapping: ' . $mapInfo;

    progressUpdate('done', $totalRows, $totalRows, $msg, $ok, $skip, '');

    return array(true, $msg);
}

function loadEproRows($keyword) {
    $keyword = trim((string)$keyword);

    if ($keyword != '') {
        $like = '%' . $keyword . '%';

        $stmt = q("
            SELECT TOP 500
                INV_NO,
                LOC,
                ITEM_NO,
                ITEM_NAME,
                INV_DATE,
                PO,
                QTY,
                PRICE_EPRO
            FROM dbo.PRICE_EPRO
            WHERE INV_NO LIKE ?
               OR LOC LIKE ?
               OR ITEM_NO LIKE ?
               OR ITEM_NAME LIKE ?
               OR PO LIKE ?
            ORDER BY INV_DATE DESC, INV_NO, ITEM_NO
        ", array($like, $like, $like, $like, $like));
    } else {
        $stmt = q("
            SELECT TOP 500
                INV_NO,
                LOC,
                ITEM_NO,
                ITEM_NAME,
                INV_DATE,
                PO,
                QTY,
                PRICE_EPRO
            FROM dbo.PRICE_EPRO
            ORDER BY INV_DATE DESC, INV_NO, ITEM_NO
        ", array());
    }

    $rows = array();

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }

    return $rows;
}

/* =========================
   AJAX ROUTER
   ========================= */
$selfUrl = basename(__FILE__);
$action = isset($_POST['action']) ? $_POST['action'] : '';

if (isset($_GET['progress']) && $_GET['progress'] == '1') {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    $p = isset($_SESSION['epro_progress']) ? $_SESSION['epro_progress'] : array(
        'file' => '',
        'status' => 'idle',
        'current' => 0,
        'total' => 0,
        'percent' => 0,
        'message' => '',
        'insert_update' => 0,
        'skip' => 0,
        'error' => ''
    );

    jsonOut($p);
}

if ($action == 'ajax_import_csv') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != UPLOAD_ERR_OK) {
        progressUpdate('error', 0, 0, 'File CSV belum dipilih.', 0, 0, 'File CSV belum dipilih.');

        jsonOut(array(
            'ok' => false,
            'message' => 'File CSV belum dipilih.'
        ));
    }

    $originalName = isset($_FILES['csv_file']['name']) ? $_FILES['csv_file']['name'] : '';
    $res = importCsv($_FILES['csv_file']['tmp_name'], $originalName);

    jsonOut(array(
        'ok' => $res[0],
        'message' => $res[1]
    ));
}

/* =========================
   NORMAL PAGE ACTION
   ========================= */
$message = '';

if ($action == 'clear_data') {
    if (isset($_POST['confirm_clear']) && $_POST['confirm_clear'] == 'YES') {
        $message = clearData();
    } else {
        $message = 'Clear data dibatalkan.';
    }
}

$keyword = isset($_GET['q']) ? $_GET['q'] : '';
$rows = loadEproRows($keyword);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PRICE EPRO IMPORT CSV</title>

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            margin: 0;
            background: #f4f6f8;
        }

        .topbar {
            background: #060078;
            color: #fff;
            padding: 10px 15px;
            font-size: 15px;
            font-weight: bold;
            text-align: center;
        }

        .wrap {
            margin: 15px;
            background: #fff;
            border: 1px solid #ccc;
            padding: 12px;
        }

        h3 {
            margin: 0 0 10px 0;
        }

        .msg {
            padding: 8px 10px;
            background: #d9edf7;
            border: 1px solid #bce8f1;
            margin-bottom: 10px;
            white-space: pre-wrap;
        }

        .box {
            padding: 8px;
            background: #fafafa;
            border: 1px solid #ddd;
            margin-bottom: 10px;
        }

        .toolbar {
            margin-bottom: 10px;
            padding: 8px;
            background: #eee;
            border: 1px solid #ddd;
        }

        .btn {
            border: 0;
            padding: 5px 9px;
            cursor: pointer;
            border-radius: 3px;
            font-size: 12px;
            color: #fff;
            text-decoration: none;
            display: inline-block;
        }

        .btn-blue {
            background: #337ab7;
        }

        .btn-green {
            background: #058b57;
        }

        .btn-red {
            background: #e51c4a;
        }

        input[type=text] {
            height: 24px;
            border: 1px solid #bfc7d1;
            padding: 2px 4px;
            font-size: 12px;
        }

        .scroll {
            max-height: 650px;
            overflow: auto;
            border: 1px solid #ccc;
        }

        table.grid {
            border-collapse: collapse;
            width: 100%;
        }

        table.grid th {
            background: #111;
            color: #fff;
            padding: 5px;
            border: 1px solid #444;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        table.grid td {
            border: 1px solid #ddd;
            padding: 4px;
            background: #fff;
        }

        table.grid tr:nth-child(even) td {
            background: #f3f3f3;
        }

        .text-right {
            text-align: right;
        }

        .note {
            background: #fffbe6;
            border: 1px solid #f0d98c;
            padding: 8px;
            margin-bottom: 10px;
        }

        .small {
            color: #666;
            font-size: 11px;
        }

        .progress-wrap {
            display: none;
            margin-top: 10px;
            border: 1px solid #aaa;
            background: #fff;
            padding: 8px;
        }

        .progress-outer {
            width: 100%;
            height: 24px;
            border: 1px solid #777;
            background: #eee;
            overflow: hidden;
            margin-bottom: 6px;
        }

        .progress-inner {
            width: 0%;
            height: 24px;
            background: #058b57;
            color: #fff;
            line-height: 24px;
            text-align: center;
            font-weight: bold;
        }

        .progress-status {
            white-space: pre-wrap;
            color: #333;
            font-size: 12px;
        }
    </style>

    <script>
        var selfUrl = '<?php echo h($selfUrl); ?>';
        var progressTimer = null;

        function confirmClear() {
            if (!confirm('Yakin hapus SEMUA data PRICE_EPRO?')) {
                return false;
            }

            if (!confirm('Konfirmasi sekali lagi. Data akan dihapus permanen. Lanjutkan?')) {
                return false;
            }

            document.getElementById('confirm_clear').value = 'YES';
            return true;
        }

        function setProgress(percent, text) {
            var wrap = document.getElementById('progressWrap');
            var bar = document.getElementById('progressBar');
            var status = document.getElementById('progressStatus');

            wrap.style.display = 'block';

            if (percent < 0) percent = 0;
            if (percent > 100) percent = 100;

            bar.style.width = percent + '%';
            bar.innerHTML = percent + '%';
            status.innerHTML = text;
        }

        function startImportProgressPolling() {
            if (progressTimer) {
                clearInterval(progressTimer);
            }

            progressTimer = setInterval(function () {
                var xhr = new XMLHttpRequest();

                xhr.open('GET', selfUrl + '?progress=1&_=' + new Date().getTime(), true);

                xhr.onreadystatechange = function () {
                    if (xhr.readyState == 4 && xhr.status == 200) {
                        var p = null;

                        try {
                            p = JSON.parse(xhr.responseText);
                        } catch (e) {
                            return;
                        }

                        var txt = '';
                        txt += 'File: ' + (p.file || '') + "\n";
                        txt += 'Status: ' + (p.status || '') + "\n";
                        txt += 'Import: ' + (p.current || 0) + ' / ' + (p.total || 0) + ' baris' + "\n";
                        txt += 'Insert/Update: ' + (p.insert_update || 0) + ', Skip: ' + (p.skip || 0) + "\n";
                        txt += p.message || '';

                        setProgress(parseInt(p.percent || 0, 10), txt);

                        if (p.status == 'done' || p.status == 'error') {
                            clearInterval(progressTimer);
                            progressTimer = null;
                        }
                    }
                };

                xhr.send(null);
            }, 700);
        }

        function uploadCsvAjax() {
            var input = document.getElementById('csv_file');

            if (!input.value) {
                alert('Pilih file CSV dulu.');
                return false;
            }

            if (!confirm('Upload dan import CSV sekarang?')) {
                return false;
            }

            var fd = new FormData();
            fd.append('action', 'ajax_import_csv');
            fd.append('csv_file', input.files[0]);

            var xhr = new XMLHttpRequest();

            setProgress(0, 'Mulai upload...');
            startImportProgressPolling();

            xhr.upload.onprogress = function (e) {
                if (e.lengthComputable) {
                    var percent = Math.round((e.loaded / e.total) * 100);
                    setProgress(percent, 'Uploading file ke server... ' + percent + '%');
                }
            };

            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4) {
                    var res = null;

                    try {
                        res = JSON.parse(xhr.responseText);
                    } catch (e) {
                        setProgress(100, 'Response bukan JSON. Kemungkinan error PHP:\n\n' + xhr.responseText);
                        if (progressTimer) {
                            clearInterval(progressTimer);
                            progressTimer = null;
                        }
                        return;
                    }

                    if (res.ok) {
                        setProgress(100, res.message + "\n\nRefresh data...");
                        setTimeout(function () {
                            window.location = selfUrl;
                        }, 1200);
                    } else {
                        setProgress(100, res.message);
                        if (progressTimer) {
                            clearInterval(progressTimer);
                            progressTimer = null;
                        }
                    }
                }
            };

            xhr.open('POST', selfUrl, true);
            xhr.send(fd);

            return false;
        }
    </script>
</head>

<body>

<div class="topbar">PRICE EPRO IMPORT CSV</div>

<div class="wrap">
    <h3>Upload CSV ke dbo.PRICE_EPRO</h3>

    <?php if ($message != '') { ?>
        <div class="msg"><?php echo h($message); ?></div>
    <?php } ?>

    <div class="note">
        Mapping otomatis dari header CSV:
        <b>Invoice No.</b>, <b>Pur.Org</b>,
        <b>Item Code</b>, <b>Item Description</b>,
        <b>Delivery Date</b>, <b>PO No.</b>,
        <b>Quantity</b>, <b>Unit Price</b>.
        <br>
        Jika header tidak ada, fallback posisi:
        <b>B</b>=INV_NO,
        <b>D</b>=LOC,
        <b>E</b>=ITEM_NO,
        <b>F</b>=ITEM_NAME,
        <b>G</b>=INV_DATE,
        <b>J</b>=PO,
        <b>K</b>=QTY,
        <b>M</b>=PRICE_EPRO.
        <br>
        Nama file CSV bebas. Baris kosong, header, subtotal, dan Total invoice otomatis di-skip.
        <br>
        Update key: <b>INV_NO + ITEM_NO + PO</b>.
    </div>

    <div class="box">
        <form id="uploadForm" method="post" enctype="multipart/form-data" action="<?php echo h($selfUrl); ?>" style="display:inline;" onsubmit="return uploadCsvAjax();">
            File CSV:
            <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv">
            <button type="submit" class="btn btn-green">Upload CSV</button>
        </form>

        <form method="post" action="<?php echo h($selfUrl); ?>" style="display:inline;" onsubmit="return confirmClear();">
            <input type="hidden" name="action" value="clear_data">
            <input type="hidden" name="confirm_clear" id="confirm_clear" value="">
            <button type="submit" class="btn btn-red">Clear Data</button>
        </form>

        <div id="progressWrap" class="progress-wrap">
            <div class="progress-outer">
                <div id="progressBar" class="progress-inner">0%</div>
            </div>
            <div id="progressStatus" class="progress-status"></div>
        </div>
    </div>

    <div class="toolbar">
        <form method="get" action="<?php echo h($selfUrl); ?>" style="display:inline;">
            Cari:
            <input type="text" name="q" value="<?php echo h($keyword); ?>" style="width:280px;">
            <button type="submit" class="btn btn-blue">Search</button>
            <a href="<?php echo h($selfUrl); ?>" class="btn btn-blue">Reset</a>
        </form>
        <span class="small">Menampilkan TOP 500 data.</span>
    </div>

    <h3>Data PRICE_EPRO</h3>

    <div class="scroll">
        <table class="grid">
            <thead>
                <tr>
                    <th>INV_NO</th>
                    <th>LOC</th>
                    <th>ITEM_NO</th>
                    <th>ITEM_NAME</th>
                    <th>INV_DATE</th>
                    <th>PO</th>
                    <th>QTY</th>
                    <th>PRICE_EPRO</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rows) == 0) { ?>
                    <tr>
                        <td colspan="8">Data belum ada.</td>
                    </tr>
                <?php } ?>

                <?php foreach ($rows as $r) { ?>
                    <tr>
                        <td><?php echo h(isset($r['INV_NO']) ? $r['INV_NO'] : ''); ?></td>
                        <td><?php echo h(isset($r['LOC']) ? $r['LOC'] : ''); ?></td>
                        <td><?php echo h(isset($r['ITEM_NO']) ? $r['ITEM_NO'] : ''); ?></td>
                        <td><?php echo h(isset($r['ITEM_NAME']) ? $r['ITEM_NAME'] : ''); ?></td>
                        <td><?php echo h(fmtDateView(isset($r['INV_DATE']) ? $r['INV_DATE'] : '')); ?></td>
                        <td><?php echo h(isset($r['PO']) ? $r['PO'] : ''); ?></td>
                        <td class="text-right"><?php echo h(isset($r['QTY']) ? $r['QTY'] : ''); ?></td>
                        <td class="text-right"><?php echo h(isset($r['PRICE_EPRO']) ? $r['PRICE_EPRO'] : ''); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
