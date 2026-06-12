<?php
// C:\xampp\htdocs\msii\finance\import_sop_tally.php
// PHP 5.4 compatible
// Test Import Stock Opname / SOP ke Tally
//
// Query source:
// SELECT Nomor, Tanggal, ITEM_CODE, ITEM_NAME, QTY
// FROM dbo.Tally_SOP
//
// Tujuan test:
// - cek apakah STOCKITEMNAME dan QTY bisa masuk ke Tally
// - tanpa ledger, tanpa amount, tanpa rate

@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');
@set_time_limit(0);

$config1 = __DIR__ . '/config/database_aging.php';
$config2 = __DIR__ . '/../config/database_aging.php';

if (file_exists($config1)) {
    require_once $config1;
} elseif (file_exists($config2)) {
    require_once $config2;
} else {
    die('File config database_aging.php tidak ditemukan.');
}


/* =========================
   AKSES KHUSUS P2
   ========================= */

$login_user = isset($_SESSION['db_user']) ? strtolower(trim($_SESSION['db_user'])) : '';
$active_plant = isset($_SESSION['active_plant']) ? strtolower(trim($_SESSION['active_plant'])) : '';

if (!($login_user == 'plant2' || $active_plant == 'p2')) {
    echo "<script>
        alert('Menu Import SOP To Tally hanya untuk Plant P2.');
        window.location.href = 'dashboard.php';
    </script>";
    exit;
}

/* =========================
   HELPER
   ========================= */

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function x($s) {
    return htmlspecialchars(trim((string)$s), ENT_QUOTES, 'UTF-8');
}

function qx($sql, $params = array()) {
    return q($sql, $params);
}

function fetchAllRows($stmt) {
    $rows = array();

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }

    return $rows;
}

function rawVal($v) {
    if ($v instanceof DateTime) {
        return $v->format('d-M-Y');
    }

    return trim((string)$v);
}

function fmtTallyDateYmd($v) {
    if ($v instanceof DateTime) {
        return $v->format('Ymd');
    }

    $s = trim((string)$v);

    if ($s == '') {
        return date('Ymd');
    }

    $t = strtotime($s);

    if ($t !== false) {
        return date('Ymd', $t);
    }

    $digits = preg_replace('/[^0-9]/', '', $s);

    if (strlen($digits) == 8) {
        return $digits;
    }

    return $s;
}

function qtyKgs($v) {
    $s = trim((string)$v);

    if ($s == '') {
        return '0.0000 KGS';
    }

    if (preg_match('/(-?[0-9]+(?:[.,][0-9]+)?)/', $s, $m)) {
        $n = str_replace(',', '.', $m[1]);
        return number_format((float)$n, 4, '.', '') . ' KGS';
    }

    return $s;
}

function stockItemNameFinal($row) {
    // Untuk test: default kirim ITEM_CODE.
    // Jika checkbox "ITEM_CODE + ITEM_NAME" dipakai, fungsi ini akan dipanggil dari build XML.
    $code = isset($row['ITEM_CODE']) ? trim((string)$row['ITEM_CODE']) : '';
    $name = isset($row['ITEM_NAME']) ? trim((string)$row['ITEM_NAME']) : '';

    if ($code != '' && $name != '') {
        if (stripos($name, $code) === 0) {
            return $name;
        }

        return $code . ' ' . $name;
    }

    if ($name != '') {
        return $name;
    }

    return $code;
}

function groupRows($rows) {
    $groups = array();

    foreach ($rows as $r) {
        $key = trim((string)$r['Nomor']);

        if (!isset($groups[$key])) {
            $groups[$key] = array(
                'header' => $r,
                'rows' => array()
            );
        }

        $groups[$key]['rows'][] = $r;
    }

    return $groups;
}

function sendToTally($xml, $url) {
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $res = curl_exec($ch);

    if ($res === false) {
        $res = 'CURL ERROR: ' . curl_error($ch);
    }

    curl_close($ch);

    return $res;
}

function responseOk($res) {
    if (strpos($res, '<CREATED>1</CREATED>') !== false) return true;
    if (strpos($res, '<ALTERED>1</ALTERED>') !== false) return true;
    if (strpos($res, '<ERRORS>0</ERRORS>') !== false && strpos($res, '<LINEERROR>') === false) return true;
    return false;
}

function tallyLineError($res) {
    if (preg_match_all('/<LINEERROR>(.*?)<\\/LINEERROR>/is', (string)$res, $m)) {
        $msg = implode(' | ', $m[1]);
        $msg = html_entity_decode($msg, ENT_QUOTES, 'UTF-8');
        $msg = strip_tags($msg);
        return trim($msg);
    }

    if (stripos((string)$res, 'CURL ERROR') !== false) {
        return trim((string)$res);
    }

    return '';
}

function groupItemCodes($group) {
    $items = array();

    foreach ($group['rows'] as $r) {
        $code = isset($r['ITEM_CODE']) ? trim((string)$r['ITEM_CODE']) : '';
        if ($code != '') {
            $items[] = $code;
        }
    }

    return implode(', ', $items);
}

function saveDebugXml($voucherNo, $xml) {
    $dir = __DIR__ . '/debug_tally_xml_sop';

    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $safeVoucher = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$voucherNo);
    $file = $dir . '/' . $safeVoucher . '.xml';

    @file_put_contents($file, $xml);

    return $file;
}

/* =========================
   SETTING
   ========================= */

function ensureSettingTable() {
    qx("
        IF OBJECT_ID('dbo.Tally_Export_Setting', 'U') IS NULL
        BEGIN
            CREATE TABLE dbo.Tally_Export_Setting
            (
                SettingName varchar(50) NOT NULL PRIMARY KEY,
                TallyIP varchar(50) NULL,
                TallyPort varchar(10) NULL,
                SqlQuery text NULL,
                XmlTemplate text NULL,
                UpdatedAt datetime NULL
            )
        END
    ", array());
}

function defaultSqlQuery() {
    return "SELECT
    Nomor,
    Tanggal,
    ITEM_CODE,
    ITEM_NAME,
    QTY
FROM dbo.Tally_SOP
ORDER BY Nomor, ITEM_CODE";
}

function getSetting() {
    ensureSettingTable();

    $stmt = qx("SELECT TOP 1 * FROM dbo.Tally_Export_Setting WHERE SettingName = 'SOP_P2_TEST_IMPORT'", array());
    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$r) {
        qx("
            INSERT INTO dbo.Tally_Export_Setting
            (
                SettingName,
                TallyIP,
                TallyPort,
                SqlQuery,
                XmlTemplate,
                UpdatedAt
            )
            VALUES
            (
                'SOP_P2_TEST_IMPORT',
                'serplan1',
                '9002',
                ?,
                '',
                GETDATE()
            )
        ", array(defaultSqlQuery()));

        $stmt = qx("SELECT TOP 1 * FROM dbo.Tally_Export_Setting WHERE SettingName = 'SOP_P2_TEST_IMPORT'", array());
        $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }

    return $r;
}

function saveSetting($ip, $port, $sql) {
    ensureSettingTable();

    qx("
        UPDATE dbo.Tally_Export_Setting
        SET TallyIP = ?,
            TallyPort = ?,
            SqlQuery = ?,
            UpdatedAt = GETDATE()
        WHERE SettingName = 'SOP_P2_TEST_IMPORT'
    ", array($ip, $port, $sql));
}


function ensureTallyServerTable() {
    qx("
        IF OBJECT_ID('dbo.Tally_Server_Master', 'U') IS NULL
        BEGIN
            CREATE TABLE dbo.Tally_Server_Master
            (
                ID INT IDENTITY(1,1) PRIMARY KEY,
                ServerName VARCHAR(100) NULL,
                TallyIP VARCHAR(100) NOT NULL,
                TallyPort VARCHAR(10) NOT NULL,
                IsDefault BIT NOT NULL DEFAULT 0,
                CreatedAt DATETIME NOT NULL DEFAULT GETDATE()
            )
        END
    ", array());
}

function seedTallyServers() {
    ensureTallyServerTable();

    $cek = qx("SELECT COUNT(*) AS JML FROM dbo.Tally_Server_Master", array());
    $r = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC);

    if ($r && (int)$r['JML'] == 0) {
        qx("
            INSERT INTO dbo.Tally_Server_Master
            (ServerName, TallyIP, TallyPort, IsDefault)
            VALUES
            ('Localhost', '127.0.0.1', '9002', 0),
            ('dianero99', 'dianero99', '9002', 1)
        ", array());
    }
}

function loadTallyServers() {
    seedTallyServers();

    $stmt = qx("
        SELECT
            ID,
            ServerName,
            TallyIP,
            TallyPort,
            IsDefault
        FROM dbo.Tally_Server_Master
        ORDER BY IsDefault DESC, ServerName, TallyIP
    ", array());

    return fetchAllRows($stmt);
}

function saveTallyServer($serverName, $ip, $port, $isDefault) {
    seedTallyServers();

    $serverName = trim((string)$serverName);
    $ip = trim((string)$ip);
    $port = trim((string)$port);

    if ($serverName == '') {
        $serverName = $ip . ':' . $port;
    }

    if ($ip == '' || $port == '') {
        return 'Nama/IP/Port Tally belum lengkap.';
    }

    if ($isDefault) {
        qx("UPDATE dbo.Tally_Server_Master SET IsDefault = 0", array());
    }

    qx("
        INSERT INTO dbo.Tally_Server_Master
        (ServerName, TallyIP, TallyPort, IsDefault)
        VALUES (?, ?, ?, ?)
    ", array($serverName, $ip, $port, $isDefault ? 1 : 0));

    return 'Server Tally berhasil disimpan.';
}

function deleteTallyServer($id) {
    seedTallyServers();

    $id = (int)$id;
    if ($id <= 0) {
        return 'ID server tidak valid.';
    }

    qx("DELETE FROM dbo.Tally_Server_Master WHERE ID = ?", array($id));
    return 'Server Tally berhasil dihapus.';
}

function testTallyConnection($ip, $port) {
    $fp = @fsockopen($ip, $port, $errno, $errstr, 3);

    if ($fp) {
        fclose($fp);
        return 'OK: Tally port terbuka di ' . $ip . ':' . $port;
    }

    return 'ERROR: Tidak bisa konek ke ' . $ip . ':' . $port . ' - ' . $errstr;
}

/* =========================
   BUILD XML SOP
   ========================= */

function buildSopXml($group, $useFullItemName) {
    $h = $group['header'];

    $nomor = rawVal($h['Nomor']);
    $date = fmtTallyDateYmd($h['Tanggal']);
    $guid = 'udi-sop-' . $nomor . '-' . $date;

    $itemsXml = '';

    foreach ($group['rows'] as $r) {
        if ($useFullItemName) {
            $stockItem = stockItemNameFinal($r);
        } else {
            // Test murni ITEM_CODE.
            $stockItem = rawVal($r['ITEM_CODE']);
        }

      $qty = rawVal($r['QTY']);

        $itemsXml .= '
        <ALLINVENTORYENTRIES.LIST>
          <STOCKITEMNAME>'.x($stockItem).'</STOCKITEMNAME>
          <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>
          <ACTUALQTY>'.x($qty).'</ACTUALQTY>

          <BATCHALLOCATIONS.LIST>
            <GODOWNNAME>Main Location</GODOWNNAME>
            <BATCHNAME>Primary Batch</BATCHNAME>
            <DESTINATIONGODOWNNAME>Main Location</DESTINATIONGODOWNNAME>
            <ACTUALQTY>'.x($qty).'</ACTUALQTY>
          </BATCHALLOCATIONS.LIST>
        </ALLINVENTORYENTRIES.LIST>';
    }

    return '<?xml version="1.0" encoding="UTF-8"?>
<ENVELOPE>
  <HEADER>
    <TALLYREQUEST>Import Data</TALLYREQUEST>
  </HEADER>
  <BODY>
    <IMPORTDATA>
      <REQUESTDESC>
        <REPORTNAME>Vouchers</REPORTNAME>
      </REQUESTDESC>
      <REQUESTDATA>
        <TALLYMESSAGE xmlns:UDF="TallyUDF">
          <VOUCHER REMOTEID="'.x($guid).'" VCHTYPE="Stock Opname" ACTION="Create" OBJVIEW="Invoice Voucher View">
            <GUID>'.x($guid).'</GUID>
            <DATE>'.x($date).'</DATE>
            <VOUCHERNUMBER>'.x($nomor).'</VOUCHERNUMBER>
            <VOUCHERTYPENAME>Stock Opname</VOUCHERTYPENAME>
            <PERSISTEDVIEW>Invoice Voucher View</PERSISTEDVIEW>
            <DIFFACTUALQTY>Yes</DIFFACTUALQTY>
            <ISMSTFROMSYNC>No</ISMSTFROMSYNC>
            <ASORIGINAL>No</ASORIGINAL>
            <ISDELETED>No</ISDELETED>
            <ISINVOICE>No</ISINVOICE>
'.$itemsXml.'
          </VOUCHER>
        </TALLYMESSAGE>
      </REQUESTDATA>
    </IMPORTDATA>
  </BODY>
</ENVELOPE>';
}

/* =========================
   REQUEST
   ========================= */

$setting = getSetting();

$tally_ip = isset($setting['TallyIP']) ? $setting['TallyIP'] : 'serplan1';
$tally_port = isset($setting['TallyPort']) ? $setting['TallyPort'] : '9002';
$sql_query = isset($setting['SqlQuery']) ? $setting['SqlQuery'] : defaultSqlQuery();

$tally_servers = loadTallyServers();

$action = isset($_POST['action']) ? $_POST['action'] : '';
$message = '';
$resultRows = array();
$exportDetails = array();
$summary = null;

$batch_limit = isset($_POST['batch_limit']) ? (int)$_POST['batch_limit'] : 10;
if ($batch_limit <= 0) {
    $batch_limit = 10;
}

$use_full_item_name = isset($_POST['use_full_item_name']) ? 1 : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tally_ip = isset($_POST['tally_ip']) ? trim($_POST['tally_ip']) : $tally_ip;
    $tally_port = isset($_POST['tally_port']) ? trim($_POST['tally_port']) : $tally_port;
    $sql_query = isset($_POST['sql_query']) ? $_POST['sql_query'] : $sql_query;
}

if ($action == 'save_server') {
    $serverName = isset($_POST['server_name']) ? trim($_POST['server_name']) : '';
    $isDefault = isset($_POST['is_default_server']) ? 1 : 0;

    $message = saveTallyServer($serverName, $tally_ip, $tally_port, $isDefault);
    $tally_servers = loadTallyServers();

    if ($isDefault) {
        saveSetting($tally_ip, $tally_port, $sql_query);
    }
} elseif ($action == 'delete_server') {
    $serverId = isset($_POST['server_id_delete']) ? (int)$_POST['server_id_delete'] : 0;
    $message = deleteTallyServer($serverId);
    $tally_servers = loadTallyServers();
} elseif ($action == 'reset_sql') {
    $sql_query = defaultSqlQuery();
    saveSetting($tally_ip, $tally_port, $sql_query);
    $message = 'SQL default SOP berhasil di-reset.';
} elseif ($action == 'save') {
    saveSetting($tally_ip, $tally_port, $sql_query);
    $message = 'Setting berhasil disimpan.';
} elseif ($action == 'test') {
    $message = testTallyConnection($tally_ip, $tally_port);
} elseif ($action == 'preview' || $action == 'export') {
    $stmt = qx($sql_query, array());
    $resultRows = fetchAllRows($stmt);
    $groups = groupRows($resultRows);

    if ($action == 'preview') {
        $message = 'Preview data selesai. Total row: ' . count($resultRows) . ', voucher: ' . count($groups);
    } else {
        saveSetting($tally_ip, $tally_port, $sql_query);

        $url = 'http://' . $tally_ip . ':' . $tally_port;

        $success = 0;
        $failed = 0;
        $processed = 0;
        $totalVoucher = count($groups);
        $totalToProcess = ($batch_limit < $totalVoucher) ? $batch_limit : $totalVoucher;

        foreach ($groups as $key => $group) {
            if ($processed >= $batch_limit) {
                break;
            }

            $counterNo = $processed + 1;
            $remainingBefore = $totalToProcess - $processed;

            $xml = buildSopXml($group, $use_full_item_name);
            $voucherNoDebug = isset($group['header']['Nomor']) ? $group['header']['Nomor'] : '';
            $debugFile = saveDebugXml($voucherNoDebug, $xml);

            $res = sendToTally($xml, $url);
            $ok = responseOk($res);

            if ($ok) {
                $success++;
            } else {
                $failed++;
            }

            $exportDetails[] = array(
                'no' => $counterNo,
                'remaining_before' => $remainingBefore,
                'remaining_after' => $remainingBefore - 1,
                'counter_text' => $ok ? ($counterNo . ' sukses') : ($counterNo . ' gagal'),
                'voucher' => $voucherNoDebug,
                'items' => count($group['rows']),
                'item_codes' => groupItemCodes($group),
                'ok' => $ok,
                'error_msg' => tallyLineError($res),
                'debug_file' => $debugFile,
                'response' => $res
            );

            $processed++;
        }

        $summary = array(
            'rows' => count($resultRows),
            'voucher' => count($groups),
            'target' => $totalToProcess,
            'processed' => $processed,
            'remaining' => count($groups) - $processed,
            'success' => $success,
            'failed' => $failed
        );

        $message = 'Import SOP selesai. Diproses: ' . $processed . ', sukses: ' . $success . ', gagal: ' . $failed;
    }
}

if ($action == '') {
    $stmt = qx($sql_query, array());
    $resultRows = fetchAllRows($stmt);
}

?>
<?php include 'layout.php'; ?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0">EXPORT STOCK OPNAME TO TALLY P2</h3>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">Kembali</a>
    </div>

    <?php if ($message != '') { ?>
        <div class="alert alert-info"><?php echo h($message); ?></div>
    <?php } ?>

    <div class="alert alert-warning">
        Test ini hanya import Stock Opname: Nomor, Tanggal, ITEM_CODE, ITEM_NAME, QTY.
        Tidak ada ledger, rate, amount. Debug XML: <b>/msii/finance/debug_tally_xml_sop/</b>.
        Jika gagal, summary akan menampilkan ITEM_CODE dan LINEERROR dari Tally.
        IP/Port Tally bisa disimpan ke master dan dipilih dari combo box.
        Counter menampilkan hitungan mundur, contoh 900 → 899, dan status 1 sukses, 2 sukses, dst.
    </div>

    <div id="exportProgressBox" class="card shadow-sm mb-3" style="display:none;">
        <div class="card-header fw-bold">Progress Import SOP</div>
        <div class="card-body">
            <div class="progress" style="height:26px;">
                <div id="exportProgressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                     role="progressbar" style="width:5%">Preparing...</div>
            </div>
            <div id="exportProgressText" class="mt-2 text-muted">Mohon tunggu, sedang import ke Tally...</div>
        </div>
    </div>

    <form method="post" id="frmSop">
        <input type="hidden" name="action" id="action" value="">

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Settings</div>
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Pilih Server Tally</label>
                        <select id="server_combo" class="form-control" onchange="pilihServerTally()">
                            <option value="">-- pilih server tally --</option>
                            <?php foreach ($tally_servers as $srv) { ?>
                                <?php
                                $srvId = isset($srv['ID']) ? $srv['ID'] : '';
                                $srvName = isset($srv['ServerName']) ? $srv['ServerName'] : '';
                                $srvIp = isset($srv['TallyIP']) ? $srv['TallyIP'] : '';
                                $srvPort = isset($srv['TallyPort']) ? $srv['TallyPort'] : '';
                                $srvDefault = isset($srv['IsDefault']) ? (int)$srv['IsDefault'] : 0;
                                $selectedServer = ($srvIp == $tally_ip && $srvPort == $tally_port) ? 'selected' : '';
                                ?>
                                <option value="<?php echo h($srvIp . '|' . $srvPort . '|' . $srvId); ?>" <?php echo $selectedServer; ?>>
                                    <?php echo h(($srvDefault ? '[DEFAULT] ' : '') . $srvName . ' - ' . $srvIp . ':' . $srvPort); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Nama Server</label>
                        <input type="text" name="server_name" id="server_name" class="form-control" placeholder="contoh: dianero99">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">Tally IP Address</label>
                        <input type="text" name="tally_ip" id="tally_ip" class="form-control" value="<?php echo h($tally_ip); ?>">
                    </div>

                    <div class="col-md-1">
                        <label class="form-label fw-bold">Port</label>
                        <input type="text" name="tally_port" id="tally_port" class="form-control" value="<?php echo h($tally_port); ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">Batch Limit</label>
                        <input type="number" name="batch_limit" id="batch_limit" class="form-control" value="<?php echo h($batch_limit); ?>">
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <label class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" name="use_full_item_name" value="1" <?php echo $use_full_item_name ? 'checked' : ''; ?>>
                            <span class="form-check-label">Pakai ITEM_CODE + ITEM_NAME</span>
                        </label>
                    </div>

                    <div class="col-md-12 d-flex align-items-center gap-2 flex-wrap">
                        <label class="form-check mb-0 me-2">
                            <input type="checkbox" class="form-check-input" name="is_default_server" value="1">
                            <span class="form-check-label">Jadikan Default</span>
                        </label>

                        <button type="button" class="btn btn-info" onclick="setAction('save_server')">Save IP/Port</button>
                        <button type="button" class="btn btn-outline-danger" onclick="deleteSelectedServer()">Delete Server</button>
                        <button type="button" class="btn btn-secondary" onclick="setAction('test')">Test Connection</button>
                        <button type="button" class="btn btn-success" onclick="setAction('save')">Save Setting</button>
                        <button type="button" class="btn btn-warning" onclick="setAction('reset_sql')">Reset SQL Default</button>
                        <button type="button" class="btn btn-primary" onclick="setAction('preview')">Preview Query</button>
                        <button type="button" class="btn btn-danger" onclick="confirmExport()">Import SOP to Tally</button>

                        <input type="hidden" name="server_id_delete" id="server_id_delete" value="">
                    </div>

                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">SQL Query</div>
            <div class="card-body">
                <textarea name="sql_query" class="form-control" rows="8" style="font-family:Consolas,monospace;"><?php echo h($sql_query); ?></textarea>
            </div>
        </div>
    </form>

    <?php if ($summary !== null) { ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var box = document.getElementById('exportProgressBox');
            var bar = document.getElementById('exportProgressBar');
            var txt = document.getElementById('exportProgressText');

            if (box && bar) {
                box.style.display = 'block';
                bar.className = 'progress-bar';
                bar.style.width = '100%';
                bar.innerHTML = '100%';
                if (txt) txt.innerHTML = 'Import selesai: <?php echo h($summary['processed']); ?> dari <?php echo h($summary['target']); ?> voucher. Sukses: <?php echo h($summary['success']); ?>, Gagal: <?php echo h($summary['failed']); ?>.';
            }
        });
        </script>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Summary Import SOP</div>
            <div class="card-body">
                <table class="table table-bordered table-sm w-auto">
                    <tr><th>Total Row</th><td><?php echo h($summary['rows']); ?></td></tr>
                    <tr><th>Total Voucher</th><td><?php echo h($summary['voucher']); ?></td></tr>
                    <tr><th>Target Proses</th><td><?php echo h($summary['target']); ?></td></tr>
                    <tr><th>Diproses</th><td><?php echo h($summary['processed']); ?></td></tr>
                    <tr><th>Sisa</th><td><?php echo h($summary['remaining']); ?></td></tr>
                    <tr><th>Sukses</th><td><?php echo h($summary['success']); ?></td></tr>
                    <tr><th>Gagal</th><td><?php echo h($summary['failed']); ?></td></tr>
                </table>

                <div class="table-responsive" style="max-height:350px;">
                    <table class="table table-bordered table-striped table-sm">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>No</th>
                                <th>Sisa Sebelum</th>
                                <th>Sisa Sesudah</th>
                                <th>Counter</th>
                                <th>Nomor</th>
                                <th>Items</th>
                                <th>ITEM_CODE</th>
                                <th>Status</th>
                                <th>Error Tally</th>
                                <th>Debug XML</th>
                                <th>Response Tally</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exportDetails as $d) { ?>
                                <tr>
                                    <td class="text-end"><?php echo h($d['no']); ?></td>
                                    <td class="text-end"><?php echo h($d['remaining_before']); ?></td>
                                    <td class="text-end"><?php echo h($d['remaining_after']); ?></td>
                                    <td><?php echo h($d['counter_text']); ?></td>
                                    <td><?php echo h($d['voucher']); ?></td>
                                    <td class="text-end"><?php echo h($d['items']); ?></td>
                                    <td style="min-width:220px;"><?php echo h($d['item_codes']); ?></td>
                                    <td><?php echo $d['ok'] ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">GAGAL</span>'; ?></td>
                                    <td style="min-width:260px;"><?php echo h($d['error_msg']); ?></td>
                                    <td><?php echo h(str_replace(__DIR__, '', $d['debug_file'])); ?></td>
                                    <td><pre style="white-space:pre-wrap;max-width:700px;"><?php echo h($d['response']); ?></pre></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    <?php } ?>

    <div class="card shadow-sm">
        <div class="card-header fw-bold">Preview Data Query</div>
        <div class="card-body">
            <?php if (count($resultRows) == 0) { ?>
                <div class="alert alert-secondary">Data tidak ada.</div>
            <?php } else { ?>
                <div class="table-responsive" style="max-height:600px;">
                    <table class="table table-bordered table-striped table-sm">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <?php foreach (array_keys($resultRows[0]) as $col) { ?>
                                    <th><?php echo h($col); ?></th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultRows as $r) { ?>
                                <tr>
                                    <?php foreach ($r as $v) { ?>
                                        <td>
                                            <?php
                                            if ($v instanceof DateTime) {
                                                echo h($v->format('Y-m-d'));
                                            } else {
                                                echo h($v);
                                            }
                                            ?>
                                        </td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>

</div>

<script>
function setAction(a) {
    document.getElementById('action').value = a;
    document.getElementById('frmSop').submit();
}

function pilihServerTally() {
    var combo = document.getElementById('server_combo');
    if (!combo || combo.value == '') return;

    var p = combo.value.split('|');
    document.getElementById('tally_ip').value = p[0];
    document.getElementById('tally_port').value = p[1];

    if (document.getElementById('server_id_delete')) {
        document.getElementById('server_id_delete').value = p[2] || '';
    }
}

function deleteSelectedServer() {
    var combo = document.getElementById('server_combo');

    if (!combo || combo.value == '') {
        alert('Pilih server Tally dulu.');
        return;
    }

    var p = combo.value.split('|');
    document.getElementById('server_id_delete').value = p[2] || '';

    if (confirm('Hapus server Tally yang dipilih?')) {
        setAction('delete_server');
    }
}

function showExportProgress() {
    var box = document.getElementById('exportProgressBox');
    var bar = document.getElementById('exportProgressBar');
    var txt = document.getElementById('exportProgressText');

    if (box) box.style.display = 'block';

    var pct = 5;

    if (bar) {
        bar.style.width = pct + '%';
        bar.innerHTML = pct + '%';
    }

    if (txt) txt.innerHTML = 'Import sedang berjalan: mulai dari counter 1 sampai selesai. Jangan tutup browser.';

    var liveCounter = 1;

    window._progressTimer = setInterval(function () {
        if (pct < 90) {
            pct += 5;
            liveCounter++;
            if (bar) {
                bar.style.width = pct + '%';
                bar.innerHTML = pct + '%';
            }
            if (txt) {
                txt.innerHTML = 'Import sedang berjalan... counter sekitar ' + liveCounter + ' diproses. Jangan tutup browser.';
            }
        }
    }, 700);
}

function confirmExport() {
    if (confirm('Import SOP ke Tally sekarang?')) {
        showExportProgress();
        setTimeout(function () {
            setAction('export');
        }, 200);
    }
}
</script>

</body>
</html>
