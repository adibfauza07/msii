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

if ($action == 'reset_sql') {
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

        foreach ($groups as $key => $group) {
            if ($processed >= $batch_limit) {
                break;
            }

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
                'voucher' => $voucherNoDebug,
                'items' => count($group['rows']),
                'ok' => $ok,
                'debug_file' => $debugFile,
                'response' => $res
            );

            $processed++;
        }

        $summary = array(
            'rows' => count($resultRows),
            'voucher' => count($groups),
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
    </div>

    <form method="post" id="frmSop">
        <input type="hidden" name="action" id="action" value="">

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Settings</div>
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tally IP Address</label>
                        <input type="text" name="tally_ip" id="tally_ip" class="form-control" value="<?php echo h($tally_ip); ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">Tally Port</label>
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

                    <div class="col-md-12 d-flex gap-2">
                        <button type="button" class="btn btn-secondary" onclick="setAction('test')">Test Connection</button>
                        <button type="button" class="btn btn-success" onclick="setAction('save')">Save</button>
                        <button type="button" class="btn btn-warning" onclick="setAction('reset_sql')">Reset SQL Default</button>
                        <button type="button" class="btn btn-primary" onclick="setAction('preview')">Preview Query</button>
                        <button type="button" class="btn btn-danger" onclick="confirmExport()">Import SOP to Tally</button>
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
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Summary Import SOP</div>
            <div class="card-body">
                <table class="table table-bordered table-sm w-auto">
                    <tr><th>Total Row</th><td><?php echo h($summary['rows']); ?></td></tr>
                    <tr><th>Total Voucher</th><td><?php echo h($summary['voucher']); ?></td></tr>
                    <tr><th>Diproses</th><td><?php echo h($summary['processed']); ?></td></tr>
                    <tr><th>Sisa</th><td><?php echo h($summary['remaining']); ?></td></tr>
                    <tr><th>Sukses</th><td><?php echo h($summary['success']); ?></td></tr>
                    <tr><th>Gagal</th><td><?php echo h($summary['failed']); ?></td></tr>
                </table>

                <div class="table-responsive" style="max-height:350px;">
                    <table class="table table-bordered table-striped table-sm">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>Nomor</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th>Debug XML</th>
                                <th>Response Tally</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exportDetails as $d) { ?>
                                <tr>
                                    <td><?php echo h($d['voucher']); ?></td>
                                    <td class="text-end"><?php echo h($d['items']); ?></td>
                                    <td><?php echo $d['ok'] ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">GAGAL</span>'; ?></td>
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

function confirmExport() {
    if (confirm('Import SOP ke Tally sekarang?')) {
        setAction('export');
    }
}
</script>

</body>
</html>
