<?php
/*
    price_approval_master.php
    PHP 5.4 + SQL Server 2008
    Tabel: dbo.PRICE_APPROVAL

    Fitur:
    - Upload Excel .xlsx ke tabel SQL
    - Insert / Update berdasarkan PART_CODE
    - Edit langsung di grid
    - Save tanpa 404 karena AJAX POST ke file ini sendiri
    - Enter = save baris aktif
    - Panah atas/bawah/kiri/kanan = pindah cell
*/

/* =========================
   ERROR SETTING
   ========================= */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   LOAD CONFIG DATABASE
   ========================= */
$configs = array(
    __DIR__ . '/../config/db_plant1.php',
    
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
    die('Config database tidak ditemukan. Cek path config db_plant2.php.');
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

function nval($v) {
    if ($v === null || $v === '') {
        return 0;
    }

    $s = trim((string)$v);
    $s = str_replace(',', '', $s);

    return (float)$s;
}

function cleanPartCode($s) {
    $s = trim((string)$s);
    return substr($s, 0, 8);
}

function cleanPartNo($s) {
    $s = trim((string)$s);
    return substr($s, 0, 9);
}

function cleanRemark($s) {
    $s = trim((string)$s);
    return substr($s, 0, 30);
}

function toSqlDate($v) {
    $v = trim((string)$v);

    if ($v == '') {
        return null;
    }

    /* Excel serial date */
    if (is_numeric($v) && (float)$v > 20000) {
        $unix = ((float)$v - 25569) * 86400;
        return gmdate('Y-m-d', $unix);
    }

    /* yyyy-mm-dd */
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return $v;
    }

    /* mm/dd/yyyy atau dd/mm/yyyy */
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $v, $m)) {
        $a = (int)$m[1];
        $b = (int)$m[2];
        $y = (int)$m[3];

        /* default ikut input HTML/browser: mm/dd/yyyy jika angka pertama <=12 */
        if ($a <= 12) {
            return sprintf('%04d-%02d-%02d', $y, $a, $b);
        } else {
            return sprintf('%04d-%02d-%02d', $y, $b, $a);
        }
    }

    $t = strtotime($v);

    if ($t !== false) {
        return date('Y-m-d', $t);
    }

    return null;
}

function fmtDateInput($v) {
    if ($v instanceof DateTime) {
        return $v->format('Y-m-d');
    }

    if ($v == '') {
        return '';
    }

    $t = strtotime((string)$v);

    if ($t === false) {
        return '';
    }

    return date('Y-m-d', $t);
}

function q($sql, $params) {
    global $conn;

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die('<pre>SQL ERROR: ' . h(sqlErrorText()) . "\n\nSQL:\n" . h($sql) . '</pre>');
    }

    return $stmt;
}

function qJson($sql, $params) {
    global $conn;

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        jsonOut(array(
            'ok' => false,
            'message' => sqlErrorText()
        ));
    }

    return $stmt;
}

/* =========================
   DATABASE ACTION
   ========================= */
function savePriceApproval($dateApproval, $partCode, $partNo, $priceApproval, $remark) {
    $dateApproval = toSqlDate($dateApproval);
    $partCode = cleanPartCode($partCode);
    $partNo = cleanPartNo($partNo);
    $priceApproval = nval($priceApproval);
    $remark = cleanRemark($remark);

    if ($partCode == '') {
        return array(false, 'PART_CODE wajib diisi.');
    }

    $sql = "
        IF EXISTS (
            SELECT 1
            FROM dbo.PRICE_APPROVAL
            WHERE PART_CODE = ?
        )
        BEGIN
            UPDATE dbo.PRICE_APPROVAL
            SET DATE_APPROVAL = ?,
                PART_NO = ?,
                PRICE_APPROVAL = ?,
                REMARK = ?
            WHERE PART_CODE = ?
        END
        ELSE
        BEGIN
            INSERT INTO dbo.PRICE_APPROVAL
            (
                DATE_APPROVAL,
                PART_CODE,
                PART_NO,
                PRICE_APPROVAL,
                REMARK
            )
            VALUES
            (
                ?, ?, ?, ?, ?
            )
        END
    ";

    $params = array(
        $partCode,
        $dateApproval,
        $partNo,
        $priceApproval,
        $remark,
        $partCode,

        $dateApproval,
        $partCode,
        $partNo,
        $priceApproval,
        $remark
    );

    qJson($sql, $params);

    return array(true, 'Data berhasil disimpan.');
}

function deletePriceApproval($partCode) {
    $partCode = cleanPartCode($partCode);

    if ($partCode == '') {
        return array(false, 'PART_CODE kosong.');
    }

    qJson("
        DELETE FROM dbo.PRICE_APPROVAL
        WHERE PART_CODE = ?
    ", array($partCode));

    return array(true, 'Data berhasil dihapus.');
}


function clearAllPriceApproval() {
    qJson("
        DELETE FROM dbo.PRICE_APPROVAL
    ", array());

    return array(true, 'Semua data PRICE_APPROVAL berhasil dihapus.');
}

function loadPriceApproval($keyword) {
    $keyword = trim((string)$keyword);

    if ($keyword != '') {
        $like = '%' . $keyword . '%';

        $stmt = q("
            SELECT TOP 500
                DATE_APPROVAL,
                PART_CODE,
                PART_NO,
                PRICE_APPROVAL,
                REMARK
            FROM dbo.PRICE_APPROVAL
            WHERE PART_CODE LIKE ?
               OR PART_NO LIKE ?
               OR REMARK LIKE ?
            ORDER BY PART_CODE
        ", array($like, $like, $like));
    } else {
        $stmt = q("
            SELECT TOP 500
                DATE_APPROVAL,
                PART_CODE,
                PART_NO,
                PRICE_APPROVAL,
                REMARK
            FROM dbo.PRICE_APPROVAL
            ORDER BY PART_CODE
        ", array());
    }

    $rows = array();

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }

    return $rows;
}

/* =========================
   XLSX READER SEDERHANA
   Tidak perlu PHPExcel.
   Syarat: PHP extension ZipArchive aktif.
   ========================= */
function xlsxColumnIndex($cellRef) {
    preg_match('/^([A-Z]+)/', $cellRef, $m);
    $letters = isset($m[1]) ? $m[1] : 'A';
    $num = 0;

    for ($i = 0; $i < strlen($letters); $i++) {
        $num = $num * 26 + (ord($letters[$i]) - 64);
    }

    return $num - 1;
}

function readXlsxSimple($file) {
    if (!class_exists('ZipArchive')) {
        return array(false, 'Extension ZipArchive belum aktif di PHP.');
    }

    $zip = new ZipArchive();

    if ($zip->open($file) !== true) {
        return array(false, 'File XLSX tidak bisa dibuka.');
    }

    $sharedStrings = array();
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

    if ($sharedXml !== false) {
        $xml = simplexml_load_string($sharedXml);

        if ($xml) {
            foreach ($xml->si as $si) {
                $txt = '';

                if (isset($si->t)) {
                    $txt = (string)$si->t;
                } else {
                    foreach ($si->r as $r) {
                        $txt .= (string)$r->t;
                    }
                }

                $sharedStrings[] = $txt;
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

    if ($sheetXml === false) {
        $zip->close();
        return array(false, 'Sheet pertama tidak ditemukan.');
    }

    $xml = simplexml_load_string($sheetXml);
    $data = array();

    if (!$xml) {
        $zip->close();
        return array(false, 'XML sheet tidak valid.');
    }

    foreach ($xml->sheetData->row as $row) {
        $rowData = array();

        foreach ($row->c as $c) {
            $attr = $c->attributes();
            $ref = (string)$attr['r'];
            $type = isset($attr['t']) ? (string)$attr['t'] : '';
            $idx = xlsxColumnIndex($ref);
            $val = '';

            if (isset($c->v)) {
                $val = (string)$c->v;
            }

            if ($type == 's') {
                $si = (int)$val;
                $val = isset($sharedStrings[$si]) ? $sharedStrings[$si] : '';
            } elseif ($type == 'inlineStr') {
                if (isset($c->is->t)) {
                    $val = (string)$c->is->t;
                }
            }

            $rowData[$idx] = $val;
        }

        if (count($rowData) > 0) {
            $max = max(array_keys($rowData));
            $normal = array();

            for ($i = 0; $i <= $max; $i++) {
                $normal[$i] = isset($rowData[$i]) ? $rowData[$i] : '';
            }

            $data[] = $normal;
        }
    }

    $zip->close();

    return array(true, $data);
}

function normalizeHeader($s) {
    $s = strtoupper(trim((string)$s));
    $s = str_replace(array(' ', '-', '.', '/'), '_', $s);
    return $s;
}

function uploadExcelAction() {
    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] != UPLOAD_ERR_OK) {
        return 'File Excel belum dipilih.';
    }

    $tmp = $_FILES['excel_file']['tmp_name'];
    $name = $_FILES['excel_file']['name'];

    if (!preg_match('/\.xlsx$/i', $name)) {
        return 'File wajib format .xlsx';
    }

    $res = readXlsxSimple($tmp);

    if (!$res[0]) {
        return $res[1];
    }

    $data = $res[1];

    if (count($data) < 2) {
        return 'Data Excel kosong.';
    }

    $header = $data[0];
    $map = array();

    for ($i = 0; $i < count($header); $i++) {
        $map[normalizeHeader($header[$i])] = $i;
    }

    $colDate = isset($map['DATE_APPROVAL']) ? $map['DATE_APPROVAL'] : (isset($map['DATE']) ? $map['DATE'] : 0);
    $colPartCode = isset($map['PART_CODE']) ? $map['PART_CODE'] : 1;
    $colPartNo = isset($map['PART_NO']) ? $map['PART_NO'] : 2;
    $colPrice = isset($map['PRICE_APPROVAL']) ? $map['PRICE_APPROVAL'] : (isset($map['PRICE']) ? $map['PRICE'] : 3);
    $colRemark = isset($map['REMARK']) ? $map['REMARK'] : 4;

    sqlsrv_begin_transaction($GLOBALS['conn']);

    $ok = 0;
    $skip = 0;

    for ($r = 1; $r < count($data); $r++) {
        $row = $data[$r];

        $dateApproval = isset($row[$colDate]) ? $row[$colDate] : '';
        $partCode = isset($row[$colPartCode]) ? $row[$colPartCode] : '';
        $partNo = isset($row[$colPartNo]) ? $row[$colPartNo] : '';
        $price = isset($row[$colPrice]) ? $row[$colPrice] : '';
        $remark = isset($row[$colRemark]) ? $row[$colRemark] : '';

        if (trim((string)$partCode) == '') {
            $skip++;
            continue;
        }

        $dateApproval = toSqlDate($dateApproval);
        $partCode = cleanPartCode($partCode);
        $partNo = cleanPartNo($partNo);
        $price = nval($price);
        $remark = cleanRemark($remark);

        $sql = "
            IF EXISTS (SELECT 1 FROM dbo.PRICE_APPROVAL WHERE PART_CODE = ?)
            BEGIN
                UPDATE dbo.PRICE_APPROVAL
                SET DATE_APPROVAL = ?,
                    PART_NO = ?,
                    PRICE_APPROVAL = ?,
                    REMARK = ?
                WHERE PART_CODE = ?
            END
            ELSE
            BEGIN
                INSERT INTO dbo.PRICE_APPROVAL
                (
                    DATE_APPROVAL,
                    PART_CODE,
                    PART_NO,
                    PRICE_APPROVAL,
                    REMARK
                )
                VALUES
                (
                    ?, ?, ?, ?, ?
                )
            END
        ";

        $params = array(
            $partCode,
            $dateApproval,
            $partNo,
            $price,
            $remark,
            $partCode,

            $dateApproval,
            $partCode,
            $partNo,
            $price,
            $remark
        );

        $stmt = sqlsrv_query($GLOBALS['conn'], $sql, $params);

        if ($stmt === false) {
            sqlsrv_rollback($GLOBALS['conn']);
            return 'Import gagal baris ' . ($r + 1) . ': ' . sqlErrorText();
        }

        $ok++;
    }

    sqlsrv_commit($GLOBALS['conn']);

    return 'Import selesai. Berhasil: ' . $ok . ', Skip: ' . $skip;
}

/* =========================
   AJAX ROUTER
   ========================= */
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action == 'ajax_save') {
    $dateApproval = isset($_POST['date_approval']) ? $_POST['date_approval'] : '';
    $partCode = isset($_POST['part_code']) ? $_POST['part_code'] : '';
    $partNo = isset($_POST['part_no']) ? $_POST['part_no'] : '';
    $priceApproval = isset($_POST['price_approval']) ? $_POST['price_approval'] : '';
    $remark = isset($_POST['remark']) ? $_POST['remark'] : '';

    $res = savePriceApproval($dateApproval, $partCode, $partNo, $priceApproval, $remark);

    jsonOut(array(
        'ok' => $res[0],
        'message' => $res[1],
        'part_code' => cleanPartCode($partCode)
    ));
}

if ($action == 'ajax_delete') {
    $partCode = isset($_POST['part_code']) ? $_POST['part_code'] : '';
    $res = deletePriceApproval($partCode);

    jsonOut(array(
        'ok' => $res[0],
        'message' => $res[1]
    ));
}


if ($action == 'ajax_clear_all') {
    $res = clearAllPriceApproval();

    jsonOut(array(
        'ok' => $res[0],
        'message' => $res[1]
    ));
}

/* =========================
   PAGE ACTION
   ========================= */
$message = '';

if ($action == 'upload_excel') {
    $message = uploadExcelAction();
}

$keyword = isset($_GET['q']) ? $_GET['q'] : '';
$rows = loadPriceApproval($keyword);

$selfUrl = basename(__FILE__);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PRICE APPROVAL MASTER</title>

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
        }

        .toolbar {
            margin-bottom: 10px;
            padding: 8px;
            background: #eee;
            border: 1px solid #ddd;
        }

        input[type=text],
        input[type=date],
        input[type=number] {
            height: 22px;
            border: 1px solid #bfc7d1;
            padding: 2px 4px;
            font-size: 12px;
            box-sizing: border-box;
            width: 100%;
        }

        input:focus {
            outline: 2px solid #6aa9ff;
            background: #fffbe6;
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

        .scroll {
            max-height: 650px;
            overflow: auto;
            border: 1px solid #ccc;
        }

        .btn {
            border: 0;
            padding: 5px 9px;
            cursor: pointer;
            border-radius: 3px;
            font-size: 12px;
        }

        .btn-save {
            background: #058b57;
            color: #fff;
        }

        .btn-del {
            background: #e51c4a;
            color: #fff;
        }

        .btn-load {
            background: #337ab7;
            color: #fff;
        }

        .status {
            font-size: 11px;
            color: #666;
            padding-left: 5px;
        }

        .text-right {
            text-align: right;
        }

        .info {
            background: #d9edf7;
            border: 1px solid #bce8f1;
            padding: 8px;
            margin-bottom: 8px;
        }

        .upload-box {
            margin-bottom: 10px;
            padding: 8px;
            background: #fafafa;
            border: 1px solid #ddd;
        }
    </style>
</head>

<body>

<div class="topbar">PRICE APPROVAL MASTER</div>

<div class="wrap">
    <h3>Data PRICE_APPROVAL</h3>

    <?php if ($message != '') { ?>
        <div class="msg"><?php echo h($message); ?></div>
    <?php } ?>

    <div class="info">
        Navigasi:
        <b>Enter</b> = save baris aktif,
        <b>Panah Atas/Bawah/Kiri/Kanan</b> = pindah cell,
        <b>Ctrl+S</b> = save baris aktif.
        Semua proses save/delete POST ke file ini sendiri, jadi tidak memanggil file lain.
    </div>

    <div class="upload-box">
        <form method="post" enctype="multipart/form-data" action="<?php echo h($selfUrl); ?>">
            <input type="hidden" name="action" value="upload_excel">
            Upload Excel .xlsx:
            <input type="file" name="excel_file" accept=".xlsx">
            <button type="submit" class="btn btn-load">Upload Insert/Update</button>
            <span class="status">Header disarankan: DATE_APPROVAL, PART_CODE, PART_NO, PRICE_APPROVAL, REMARK</span>
        </form>
    </div>

    <div class="toolbar">
        <form method="get" action="<?php echo h($selfUrl); ?>" style="display:inline;">
            Cari:
            <input type="text" name="q" value="<?php echo h($keyword); ?>" style="width:250px;">
            <button type="submit" class="btn btn-load">Search</button>
            <a href="<?php echo h($selfUrl); ?>" class="btn btn-load" style="text-decoration:none;">Reset</a>
            <button type="button" class="btn btn-del" onclick="clearAllData()">Clear Data</button>
        </form>
    </div>

    <div class="scroll">
        <table class="grid" id="priceGrid">
            <thead>
                <tr>
                    <th style="width:120px;">DATE_APPROVAL</th>
                    <th style="width:110px;">PART_CODE</th>
                    <th style="width:120px;">PART_NO</th>
                    <th style="width:120px;">PRICE_APPROVAL</th>
                    <th>REMARK</th>
                    <th style="width:120px;">Action</th>
                </tr>
            </thead>

            <tbody>
                <tr data-row="new">
                    <td><input type="date" class="cell date_approval" data-col="0" value=""></td>
                    <td><input type="text" class="cell part_code" data-col="1" maxlength="8" value=""></td>
                    <td><input type="text" class="cell part_no" data-col="2" maxlength="9" value=""></td>
                    <td><input type="text" class="cell price_approval text-right" data-col="3" value=""></td>
                    <td><input type="text" class="cell remark" data-col="4" maxlength="30" value=""></td>
                    <td>
                        <button type="button" class="btn btn-save" onclick="saveRow(this)">Save</button>
                        <span class="status">new</span>
                    </td>
                </tr>

                <?php foreach ($rows as $r) { ?>
                    <?php
                        $dateVal = fmtDateInput($r['DATE_APPROVAL']);
                        $partCode = isset($r['PART_CODE']) ? $r['PART_CODE'] : '';
                        $partNo = isset($r['PART_NO']) ? $r['PART_NO'] : '';
                        $price = isset($r['PRICE_APPROVAL']) ? $r['PRICE_APPROVAL'] : '';
                        $remark = isset($r['REMARK']) ? $r['REMARK'] : '';
                    ?>
                    <tr data-row="<?php echo h($partCode); ?>">
                        <td><input type="date" class="cell date_approval" data-col="0" value="<?php echo h($dateVal); ?>"></td>
                        <td><input type="text" class="cell part_code" data-col="1" maxlength="8" value="<?php echo h($partCode); ?>"></td>
                        <td><input type="text" class="cell part_no" data-col="2" maxlength="9" value="<?php echo h($partNo); ?>"></td>
                        <td><input type="text" class="cell price_approval text-right" data-col="3" value="<?php echo h($price); ?>"></td>
                        <td><input type="text" class="cell remark" data-col="4" maxlength="30" value="<?php echo h($remark); ?>"></td>
                        <td>
                            <button type="button" class="btn btn-save" onclick="saveRow(this)">Save</button>
                            <button type="button" class="btn btn-del" onclick="deleteRow(this)">Del</button>
                            <span class="status"></span>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<script>
var ajaxUrl = '<?php echo h($selfUrl); ?>';

function ajaxPost(data, callback) {
    var xhr = new XMLHttpRequest();

    xhr.open('POST', ajaxUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4) {
            var res = null;

            try {
                res = JSON.parse(xhr.responseText);
            } catch (e) {
                alert('Response bukan JSON. Kemungkinan error PHP:\n\n' + xhr.responseText);
                return;
            }

            callback(res);
        }
    };

    xhr.send(data);
}

function enc(v) {
    return encodeURIComponent(v == null ? '' : v);
}

function getRowFromElement(el) {
    while (el && el.tagName != 'TR') {
        el = el.parentNode;
    }

    return el;
}

function getRowData(tr) {
    return {
        date_approval: tr.getElementsByClassName('date_approval')[0].value,
        part_code: tr.getElementsByClassName('part_code')[0].value,
        part_no: tr.getElementsByClassName('part_no')[0].value,
        price_approval: tr.getElementsByClassName('price_approval')[0].value,
        remark: tr.getElementsByClassName('remark')[0].value
    };
}

function setStatus(tr, text) {
    var s = tr.getElementsByClassName('status');
    if (s.length > 0) {
        s[0].innerHTML = text;
    }
}

function saveRow(btnOrInput) {
    var tr = getRowFromElement(btnOrInput);
    var d = getRowData(tr);

    if (d.part_code == '') {
        alert('PART_CODE wajib diisi.');
        tr.getElementsByClassName('part_code')[0].focus();
        return false;
    }

    setStatus(tr, 'saving...');

    var post =
        'action=ajax_save' +
        '&date_approval=' + enc(d.date_approval) +
        '&part_code=' + enc(d.part_code) +
        '&part_no=' + enc(d.part_no) +
        '&price_approval=' + enc(d.price_approval) +
        '&remark=' + enc(d.remark);

    ajaxPost(post, function (res) {
        if (res.ok) {
            tr.setAttribute('data-row', res.part_code);
            setStatus(tr, 'saved');
        } else {
            setStatus(tr, 'error');
            alert(res.message);
        }
    });

    return true;
}

function deleteRow(btn) {
    var tr = getRowFromElement(btn);
    var d = getRowData(tr);

    if (d.part_code == '') {
        tr.parentNode.removeChild(tr);
        return;
    }

    if (!confirm('Hapus PART_CODE ' + d.part_code + ' ?')) {
        return;
    }

    var post = 'action=ajax_delete&part_code=' + enc(d.part_code);

    ajaxPost(post, function (res) {
        if (res.ok) {
            tr.parentNode.removeChild(tr);
        } else {
            alert(res.message);
        }
    });
}

function clearAllData() {
    if (!confirm('Yakin hapus SEMUA data di tabel PRICE_APPROVAL?')) {
        return;
    }

    if (!confirm('Konfirmasi sekali lagi: semua data akan dihapus permanen. Lanjutkan?')) {
        return;
    }

    ajaxPost('action=ajax_clear_all', function (res) {
        if (res.ok) {
            alert(res.message);
            window.location = ajaxUrl;
        } else {
            alert(res.message);
        }
    });
}

function getAllCells() {
    return document.getElementById('priceGrid').getElementsByClassName('cell');
}

function focusCell(rowIndex, colIndex) {
    var tbody = document.getElementById('priceGrid').getElementsByTagName('tbody')[0];
    var rows = tbody.getElementsByTagName('tr');

    if (rowIndex < 0) {
        rowIndex = 0;
    }

    if (rowIndex >= rows.length) {
        rowIndex = rows.length - 1;
    }

    var inputs = rows[rowIndex].getElementsByClassName('cell');

    if (colIndex < 0) {
        colIndex = 0;
    }

    if (colIndex >= inputs.length) {
        colIndex = inputs.length - 1;
    }

    inputs[colIndex].focus();

    if (inputs[colIndex].select) {
        inputs[colIndex].select();
    }
}

function getCellPosition(input) {
    var tr = getRowFromElement(input);
    var tbody = document.getElementById('priceGrid').getElementsByTagName('tbody')[0];
    var rows = tbody.getElementsByTagName('tr');

    var rowIndex = 0;
    for (var i = 0; i < rows.length; i++) {
        if (rows[i] == tr) {
            rowIndex = i;
            break;
        }
    }

    var colIndex = parseInt(input.getAttribute('data-col'), 10);

    return {
        row: rowIndex,
        col: colIndex,
        tr: tr
    };
}

document.onkeydown = function (e) {
    e = e || window.event;

    var target = e.target || e.srcElement;

    if (!target || target.className.indexOf('cell') < 0) {
        return true;
    }

    var pos = getCellPosition(target);

    /* Ctrl + S */
    if (e.ctrlKey && (e.keyCode == 83)) {
        if (e.preventDefault) e.preventDefault();
        e.returnValue = false;
        saveRow(target);
        return false;
    }

    /* Enter */
    if (e.keyCode == 13) {
        if (e.preventDefault) e.preventDefault();
        e.returnValue = false;
        saveRow(target);
        focusCell(pos.row + 1, pos.col);
        return false;
    }

    /* Panah kiri */
    if (e.keyCode == 37) {
        focusCell(pos.row, pos.col - 1);
        return false;
    }

    /* Panah atas */
    if (e.keyCode == 38) {
        focusCell(pos.row - 1, pos.col);
        return false;
    }

    /* Panah kanan */
    if (e.keyCode == 39) {
        focusCell(pos.row, pos.col + 1);
        return false;
    }

    /* Panah bawah */
    if (e.keyCode == 40) {
        focusCell(pos.row + 1, pos.col);
        return false;
    }

    return true;
};
</script>

</body>
</html>
