<?php
// C:\xampp\htdocs\msii\finance\consumtion_tally.php
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');
@set_time_limit(0);

$config1 = __DIR__ . '/config/database_aging.php';
$config2 = __DIR__ . '/../config/database_aging.php';
if (file_exists($config1)) { require_once $config1; } 
elseif (file_exists($config2)) { require_once $config2; } 
else { die('Config not found.'); }

$login_user = isset($_SESSION['db_user']) ? strtolower(trim($_SESSION['db_user'])) : '';
$active_plant = isset($_SESSION['active_plant']) ? strtolower(trim($_SESSION['active_plant'])) : '';

// AKSES P1 & P2
$allow_p1 = ($login_user == 'plant1' || $active_plant == 'p1');
$allow_p2 = ($login_user == 'plant2' || $active_plant == 'p2');

if (!$allow_p1 && !$allow_p2) {
    echo "<script>alert('Akses hanya untuk Plant 1 / Plant 2.'); window.location.href='dashboard.php';</script>";
    exit;
}

// Tentukan plant aktif untuk label dan tombol kembali.
if ($active_plant == 'p1' || $active_plant == 'p2') {
    $current_plant_code = $active_plant;
} elseif ($login_user == 'plant1') {
    $current_plant_code = 'p1';
} else {
    $current_plant_code = 'p2';
}

$current_plant_label = ($current_plant_code == 'p1') ? 'P1' : 'P2';
$back_import_url = ($current_plant_code == 'p1')
    ? 'tally_import.php'
    : 'tally_import_p2.php?tab=consumpt';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function x($s) { return htmlspecialchars(trim((string)$s), ENT_QUOTES, 'UTF-8'); }
function qx($sql, $params = array()) { return q($sql, $params); }

// Ambil kode ITEM sebanyak 8 karakter paling kiri
function itemCode8($value) {
    return substr(trim((string)$value), 0, 8);
}

// Normalisasi kolom ITEM
function normalizeItemRows8($rows) {
    $out = array();
    foreach ($rows as $r) {
        if (is_array($r)) {
            $itemValue = getRowValueInsensitive($r, array('ITEM_MATERIAL', 'ITEM_CODE', 'ITEM'), '');
            setRowValueDual($r, 'ITEM_MATERIAL', itemCode8($itemValue));
        }
        $out[] = $r;
    }
    return $out;
}

function rate5($value) {
    return number_format(round((float)$value, 5), 5, '.', '');
}

function fetchAllRows($stmt) {
    $rows = array();
    if (!$stmt) return $rows;
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }
    return $rows;
}

function normalizeSqlDate($value) {
    if ($value instanceof DateTime) { return $value->format('Y-m-d'); }
    $s = trim((string)$value);
    if ($s === '') { return date('Y-m-d'); }
    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $s, $m)) { return $m[1] . '-' . $m[2] . '-' . $m[3]; }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) { return $m[1]; }
    $time = strtotime(str_replace('/', '-', $s));
    if ($time !== false) { return date('Y-m-d', $time); }
    return date('Y-m-d');
}

function getRowValueInsensitive($row, $names, $default = '') {
    if (!is_array($row)) return $default;
    foreach ($names as $name) {
        if (isset($row[$name])) return $row[$name];
    }
    $lower = array_change_key_case($row, CASE_LOWER);
    foreach ($names as $name) {
        $key = strtolower($name);
        if (isset($lower[$key])) return $lower[$key];
    }
    return $default;
}

function setRowValueDual(&$row, $upperKey, $value) {
    $row[$upperKey] = $value;
    $row[strtolower($upperKey)] = $value;
}

function extractItemCodeFromText($text) {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (preg_match('/^([A-Za-z0-9]+(?:-[A-Za-z0-9]+)+)/', $text, $m)) { return strtoupper(trim($m[1])); }
    if (preg_match('/^([A-Za-z0-9]{4,})/', $text, $m)) { return strtoupper(trim($m[1])); }
    return '';
}

// -----------------------------------------------------------------------------
// TALLY EXPORT XML LOGIC
// -----------------------------------------------------------------------------
function sendToTally($xml, $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    $res = curl_exec($ch);
    if ($res === false) { $res = 'CURL ERROR: ' . curl_error($ch); }
    curl_close($ch);
    return $res;
}

function tallyResponseCounts($res) {
    $tags = array('CREATED', 'ALTERED', 'DELETED', 'COMBINED', 'IGNORED', 'ERRORS', 'CANCELLED', 'CANCELED');
    $counts = array();
    foreach ($tags as $tag) {
        $counts[strtolower($tag)] = 0;
        if (preg_match('/<' . $tag . '>\s*(-?\d+)\s*<\/' . $tag . '>/i', (string)$res, $m)) {
            $counts[strtolower($tag)] = (int)$m[1];
        }
    }
    return $counts;
}

function responseOk($res) {
    if (stripos((string)$res, 'CURL ERROR') !== false) return false;
    if (stripos((string)$res, '<LINEERROR>') !== false) return false;
    $c = tallyResponseCounts($res);
    $errors = isset($c['errors']) ? (int)$c['errors'] : 0;
    $ignored = isset($c['ignored']) ? (int)$c['ignored'] : 0;
    $cancelled = 0;
    if (isset($c['cancelled'])) $cancelled += (int)$c['cancelled'];
    if (isset($c['canceled'])) $cancelled += (int)$c['canceled'];
    $created = isset($c['created']) ? (int)$c['created'] : 0;
    $altered = isset($c['altered']) ? (int)$c['altered'] : 0;
    return ($errors === 0 && $ignored === 0 && $cancelled === 0 && ($created > 0 || $altered > 0));
}

function tallyLineError($res) {
    if (preg_match_all('/<LINEERROR>(.*?)<\/LINEERROR>/is', (string)$res, $m)) {
        return trim(strip_tags(html_entity_decode(implode(' | ', $m[1]), ENT_QUOTES, 'UTF-8')));
    }
    if (stripos((string)$res, 'CURL ERROR') !== false) return trim((string)$res);
    return '';
}

function tallyFailureReason($res) {
    $lineError = tallyLineError($res);
    if ($lineError !== '') return $lineError;

    $c = tallyResponseCounts($res);
    $parts = array();
    if (!empty($c['errors'])) $parts[] = 'ERRORS=' . (int)$c['errors'];
    if (!empty($c['ignored'])) $parts[] = 'IGNORED=' . (int)$c['ignored'];
    if (!empty($c['cancelled'])) $parts[] = 'CANCELLED=' . (int)$c['cancelled'];
    if (!empty($c['canceled'])) $parts[] = 'CANCELED=' . (int)$c['canceled'];
    if (!empty($c['created'])) $parts[] = 'CREATED=' . (int)$c['created'];
    if (!empty($c['altered'])) $parts[] = 'ALTERED=' . (int)$c['altered'];

    if (!empty($parts)) return 'Gagal (' . implode(', ', $parts) . ').';
    return 'Respons Tally tidak menunjukkan voucher berhasil dibuat.';
}

function saveDebugXml($name, $xml) {
    $dir = __DIR__ . '/debug_tally_xml_consumpt';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $file = $dir . '/' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$name) . '.xml';
    @file_put_contents($file, $xml);
    return $file;
}

function ensureTallyServerTable() { qx("IF OBJECT_ID('dbo.Tally_Server_Master', 'U') IS NULL CREATE TABLE dbo.Tally_Server_Master (ID INT IDENTITY(1,1) PRIMARY KEY, ServerName VARCHAR(100) NULL, TallyIP VARCHAR(100) NOT NULL, TallyPort VARCHAR(10) NOT NULL, IsDefault BIT NOT NULL DEFAULT 0)", array()); }
function seedTallyServers() { ensureTallyServerTable(); $cek = qx("SELECT COUNT(*) AS JML FROM dbo.Tally_Server_Master", array()); $r = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC); if ($r && (int)$r['JML'] == 0) { qx("INSERT INTO dbo.Tally_Server_Master (ServerName, TallyIP, TallyPort, IsDefault) VALUES ('Localhost', '127.0.0.1', '9002', 0), ('dianero99', 'dianero99', '9002', 1)", array()); } }
function loadTallyServers() { seedTallyServers(); return fetchAllRows(qx("SELECT ID, ServerName, TallyIP, TallyPort, IsDefault FROM dbo.Tally_Server_Master ORDER BY IsDefault DESC", array())); }
function testTallyConnection($ip, $port) { $fp = @fsockopen($ip, $port, $errno, $errstr, 3); if ($fp) { fclose($fp); return 'OK: Tally port terbuka di ' . $ip . ':' . $port; } return 'ERROR: Tidak bisa konek ke ' . $ip . ':' . $port . ' - ' . $errstr; }

function buildConsumptXml($rows) {
    $vouchersXml = '';
    foreach ($rows as $r) {
        $rl = array_change_key_case($r, CASE_LOWER);
        
        $unik = isset($rl['uniqueid']) ? trim((string)$rl['uniqueid']) : '';
        $vchNo = isset($rl['vch_no']) ? trim((string)$rl['vch_no']) : '';
        
        // Hitung H-1 Akhir Bulan berdasarkan tanggal transaksi (tanggal asli dari DB)
        $origDate = isset($rl['date']) ? normalizeSqlDate($rl['date']) : date('Y-m-d');
        $dObj = new DateTime($origDate);
        $dObj->modify('last day of this month')->modify('-1 day');
        $forcedRawDate = $dObj->format('Y-m-d'); 
        
        $dateTally = date('Ymd', strtotime($forcedRawDate)); 
        
        $itemCode = isset($rl['item_material']) ? trim((string)$rl['item_material']) : '';
        
        $qty = isset($rl['cons_qty']) ? trim((string)$rl['cons_qty']) : '0';
        $rate = isset($rl['cons_rate']) ? trim((string)$rl['cons_rate']) : '0';
        $rateFixed5 = rate5($rate);
        $amount = isset($rl['cons_amount']) ? trim((string)$rl['cons_amount']) : '0';
        $amountFixed2 = round((float)$amount, 2);
        
        $narration = isset($rl['narration']) ? trim((string)$rl['narration']) : '';
        
        $guid = 'udi-IMCconsumpt-' . $unik . '-' . $vchNo;

        // XML MENGGUNAKAN ACTION="Alter" UNTUK MEMPERBARUI DATA YANG SUDAH ADA DI TALLY
        $vouchersXml .= '
          <VOUCHER REMOTEID="'.x($guid).'" VCHTYPE="Consumption Material" ACTION="Alter">
            <GUID>'.x($guid).'</GUID>
            <DATE>'.x($dateTally).'</DATE>
            <EFFECTIVEDATE>'.x($dateTally).'</EFFECTIVEDATE>
            <VOUCHERTYPENAME>Consumption Material</VOUCHERTYPENAME>
            <ISINVOICE>Yes</ISINVOICE>
            <VOUCHERNUMBER>'.x($vchNo).'</VOUCHERNUMBER>
            <NARRATION>'.x($narration).'</NARRATION>
            <INVENTORYENTRIESIN.LIST>
              <STOCKITEMNAME>'.x($itemCode).'</STOCKITEMNAME>
              <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>
              <RATE>'.x($rateFixed5).'</RATE>
              <AMOUNT>'.x($amountFixed2).'</AMOUNT>
              <ACTUALQTY>'.x($qty).'</ACTUALQTY>
              <BILLEDQTY>'.x($qty).'</BILLEDQTY>
              <BATCHALLOCATIONS.LIST>
                <GODOWNNAME>Main Location</GODOWNNAME>
                <AMOUNT>'.x($amountFixed2).'</AMOUNT>
                <ACTUALQTY>'.x($qty).'</ACTUALQTY>
                <BILLEDQTY>'.x($qty).'</BILLEDQTY>
              </BATCHALLOCATIONS.LIST>
            </INVENTORYENTRIESIN.LIST>
          </VOUCHER>';
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
          '.$vouchersXml.'
        </TALLYMESSAGE>
      </REQUESTDATA>
    </IMPORTDATA>
  </BODY>
</ENVELOPE>';
}

/* REQUEST HANDLER */
$tally_servers = loadTallyServers();
$defaultSrv = isset($tally_servers[0]) ? $tally_servers[0] : array();

$tally_ip = isset($defaultSrv['TallyIP']) ? $defaultSrv['TallyIP'] : '127.0.0.1';
$tally_port = isset($defaultSrv['TallyPort']) ? $defaultSrv['TallyPort'] : '9002';

$action = isset($_POST['action']) ? $_POST['action'] : '';

$fromDate = isset($_POST['from_date']) ? $_POST['from_date'] : date('Y-m-01');
$toDate = isset($_POST['to_date']) ? $_POST['to_date'] : date('Y-m-d');

$message = '';
$resultRows = array();
$summary = null;
$failedRows = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tally_ip = isset($_POST['tally_ip']) ? trim($_POST['tally_ip']) : $tally_ip;
    $tally_port = isset($_POST['tally_port']) ? trim($_POST['tally_port']) : $tally_port;
}

if ($action == 'test') {
    $message = testTallyConnection($tally_ip, $tally_port);
} elseif ($action == 'preview') {
    $stmt = qx("EXECUTE sp_GenerateTallyConsumtion ?, ?", array($fromDate, $toDate));
    $resultRows = fetchAllRows($stmt);
    // NORMALISASI ITEM
    $resultRows = normalizeItemRows8($resultRows);
    $message = 'Preview data Consumption selesai. ITEM ditampilkan 8 karakter (ITEM_CODE). Total: ' . count($resultRows);
} elseif ($action == 'import_table') {
    qx("DELETE FROM dbo.tally_consumtion", array());
    $stmt = qx("EXECUTE sp_GenerateTallyConsumtion ?, ?", array($fromDate, $toDate));
    $rows = fetchAllRows($stmt);
    
    // NORMALISASI ITEM
    $rows = normalizeItemRows8($rows);
    
    foreach ($rows as $r) {
        $rl = array_change_key_case($r, CASE_LOWER);
        
        $unik = isset($rl['uniqueid']) ? trim($rl['uniqueid']) : '';
        $vchNo = isset($rl['vch_no']) ? trim($rl['vch_no']) : '';
        
        // Ubah tanggal transaksi menjadi H-1 di akhir bulan sesuai bulan transaksi tersebut
        $origDate = isset($rl['date']) ? normalizeSqlDate($rl['date']) : date('Y-m-d');
        $dObj = new DateTime($origDate);
        $dObj->modify('last day of this month')->modify('-1 day');
        $date = $dObj->format('Y-m-d'); 
        
        $item = isset($rl['item_material']) ? trim($rl['item_material']) : '';
        $qty = isset($rl['cons_qty']) ? (float)$rl['cons_qty'] : 0;
        
        $rate = isset($rl['cons_rate']) ? rate5($rl['cons_rate']) : '0.00000';
        $amount = isset($rl['cons_amount']) ? (float)$rl['cons_amount'] : 0;
        $narration = isset($rl['narration']) ? trim($rl['narration']) : '';

        qx("INSERT INTO dbo.tally_consumtion (UNIQUEID, VCH_NO, [DATE], ITEM_MATERIAL, CONS_QTY, CONS_RATE, CONS_AMOUNT, NARRATION) VALUES (?, ?, ?, ?, CAST(? AS DECIMAL(38,4)), CAST(? AS DECIMAL(38,5)), CAST(? AS DECIMAL(38,2)), ?)", array(
            $unik, $vchNo, $date, $item, $qty, $rate, $amount, $narration
        ));
    }
    $message = 'Berhasil simpan ke tabel tally_consumtion. Total: ' . count($rows) . ' baris.';
    $stmt = qx("SELECT UNIQUEID, VCH_NO, [DATE], ITEM_MATERIAL, CONS_QTY, CONS_RATE, CONS_AMOUNT, NARRATION FROM dbo.tally_consumtion ORDER BY [DATE], VCH_NO", array());
    $resultRows = fetchAllRows($stmt);
} elseif ($action == 'export') {
    qx("UPDATE dbo.tally_consumtion SET ITEM_MATERIAL = LEFT(LTRIM(RTRIM(ITEM_MATERIAL)), 8) WHERE ITEM_MATERIAL IS NOT NULL", array());
    $stmt = qx("SELECT UNIQUEID, VCH_NO, [DATE], ITEM_MATERIAL, CONS_QTY, CONS_RATE, CONS_AMOUNT, NARRATION FROM dbo.tally_consumtion ORDER BY [DATE], VCH_NO", array());
    $resultRows = fetchAllRows($stmt);

    if (count($resultRows) == 0) {
        $message = 'Tabel tally_consumtion kosong. Klik Import ke Tabel dulu.';
    } else {
        $url = 'http://' . $tally_ip . ':' . $tally_port;
        $successCount = 0;
        $failedRows = array();

        foreach ($resultRows as $index => $row) {
            $rl = array_change_key_case($row, CASE_LOWER);
            $unik = isset($rl['uniqueid']) ? trim((string)$rl['uniqueid']) : '';
            $vchNo = isset($rl['vch_no']) ? trim((string)$rl['vch_no']) : '';
            $date = isset($rl['date']) ? normalizeSqlDate($rl['date']) : '';
            $itemCode = isset($rl['item_material']) ? trim($rl['item_material']) : '';
            $qty = isset($rl['cons_qty']) ? $rl['cons_qty'] : 0;

            $xml = buildConsumptXml(array($row));
            $res = sendToTally($xml, $url);
            $counts = tallyResponseCounts($res);

            if (responseOk($res)) {
                $successCount++;
                continue;
            }

            $debugKey = $vchNo . '_' . $unik . '_' . ($index + 1);
            $debugXml = saveDebugXml('CONSUMPT_FAILED_XML_' . $debugKey, $xml);
            $debugResponse = saveDebugXml('CONSUMPT_FAILED_RESPONSE_' . $debugKey, $res);
            $countText = 'CREATED=' . (int)$counts['created'] . ', ALTERED=' . (int)$counts['altered'] . ', IGNORED=' . (int)$counts['ignored'] . ', ERRORS=' . (int)$counts['errors'];

            $failedRows[] = array(
                'no' => $index + 1,
                'uniqueid' => $unik,
                'vch_no' => $vchNo,
                'date' => $date,
                'item_material' => $itemCode,
                'qty' => $qty,
                'error' => tallyFailureReason($res),
                'counts' => $countText,
                'debug_xml' => $debugXml,
                'debug_response' => $debugResponse
            );
        }

        $failedCount = count($failedRows);
        $status = ($failedCount === 0) ? 'Sukses' : (($successCount > 0) ? 'Sebagian' : 'Gagal');

        $summary = array(
            'total' => count($resultRows),
            'success' => $successCount,
            'failed' => $failedCount,
            'status' => $status,
            'debug' => $failedCount > 0 ? (__DIR__ . '/debug_tally_xml_consumpt') : '-',
            'error' => $failedCount > 0 ? 'Lihat tabel Detail Data Tidak Masuk di bawah.' : '',
        );

        $message = 'Kirim UPDATE (Alter) ke Tally selesai. Berhasil diupdate: ' . $successCount . ', Gagal diupdate: ' . $failedCount . ', total: ' . count($resultRows);
    }
} else {
    $stmt = qx("EXECUTE sp_GenerateTallyConsumtion ?, ?", array($fromDate, $toDate));
    $resultRows = fetchAllRows($stmt);
    // NORMALISASI ITEM SAAT LOAD PERTAMA
    $resultRows = normalizeItemRows8($resultRows);
}
?>
<?php include 'layout.php'; ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0">UPDATE (ALTER) CONSUMPTION MATERIAL TALLY <?php echo h($current_plant_label); ?></h3>
        <a href="<?php echo h($back_import_url); ?>" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <?php if ($message != '') { ?>
        <div class="alert alert-info"><?php echo h($message); ?></div>
    <?php } ?>

    <div class="alert alert-danger py-2">
        <strong>PENTING:</strong> Script ini saat ini diatur untuk <b>ALTER (UPDATE)</b> data yang sudah ada di Tally berdasarkan VCH_NO dan UNIQUEID. Tanggal akan otomatis di-set ke H-1 Akhir Bulan.
    </div>

    <div id="exportProgressBox" class="card shadow-sm mb-3" style="display:none;">
        <div class="card-header fw-bold">Progress Update Consumption</div>
        <div class="card-body">
            <div class="progress" style="height:26px;">
                <div id="exportProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:5%">Preparing...</div>
            </div>
            <div id="exportProgressText" class="mt-2 text-muted">Mohon tunggu, sedang memproses update data...</div>
        </div>
    </div>

    <form method="post" id="frmProd">
        <input type="hidden" name="action" id="action" value="">
        
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Settings Tally Server</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Pilih Server Tally</label>
                        <select id="server_combo" class="form-control" onchange="pilihServerTally()">
                            <option value="">-- pilih server tally --</option>
                            <?php foreach ($tally_servers as $srv) { ?>
                                <?php
                                $srvId = isset($srv['ID']) ? $srv['ID'] : '';
                                $srvIp = isset($srv['TallyIP']) ? $srv['TallyIP'] : '';
                                $srvPort = isset($srv['TallyPort']) ? $srv['TallyPort'] : '';
                                $srvName = isset($srv['ServerName']) ? $srv['ServerName'] : '';
                                $selectedServer = ($srvIp == $tally_ip && $srvPort == $tally_port) ? 'selected' : '';
                                ?>
                                <option value="<?php echo h($srvIp . '|' . $srvPort . '|' . $srvId); ?>" <?php echo $selectedServer; ?>>
                                    <?php echo h($srvName . ' - ' . $srvIp . ':' . $srvPort); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tally IP Address</label>
                        <input type="text" name="tally_ip" id="tally_ip" class="form-control" value="<?php echo h($tally_ip); ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">Port</label>
                        <input type="text" name="tally_port" id="tally_port" class="form-control" value="<?php echo h($tally_port); ?>">
                    </div>

                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="button" class="btn btn-secondary" onclick="setAction('test')">Test Conn</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Filter Data Consumption</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Dari Tanggal</label>
                        <input type="date" name="from_date" class="form-control" value="<?php echo h($fromDate); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Sampai Tanggal</label>
                        <input type="date" name="to_date" class="form-control" value="<?php echo h($toDate); ?>">
                    </div>
                    <div class="col-md-8 d-flex align-items-end gap-2">
                        <button type="button" class="btn btn-success" onclick="setAction('preview')">Preview Data</button>
                        <button type="button" class="btn btn-info" onclick="setAction('import_table')">Import ke Tally_Consumpt</button>
                        <button type="button" class="btn btn-danger" onclick="confirmExport()">Update ke Tally (Alter)</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <?php if ($summary !== null) { ?>
        <?php
        $summaryColor = 'danger';
        if ($summary['status'] == 'Sukses') $summaryColor = 'success';
        elseif ($summary['status'] == 'Sebagian') $summaryColor = 'warning';
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var box = document.getElementById('exportProgressBox');
            var bar = document.getElementById('exportProgressBar');
            var txt = document.getElementById('exportProgressText');

            if (box && bar) {
                box.style.display = 'block';
                bar.className = 'progress-bar bg-<?php echo h($summaryColor); ?>';
                bar.style.width = '100%';
                bar.innerHTML = '100% Selesai';
                if (txt) txt.innerHTML = 'Update selesai. Status: <?php echo h($summary['status']); ?>.';
            }
        });
        </script>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Summary Update Consumption</div>
            <div class="card-body">
                <table class="table table-bordered table-sm w-auto">
                    <tr><th>Total Voucher</th><td><?php echo h($summary['total']); ?></td></tr>
                    <tr><th>Berhasil Diupdate</th><td><span class="badge bg-success"><?php echo h($summary['success']); ?></span></td></tr>
                    <tr><th>Gagal Diupdate</th><td><span class="badge bg-<?php echo $summary['failed'] > 0 ? 'danger' : 'secondary'; ?>"><?php echo h($summary['failed']); ?></span></td></tr>
                    <tr><th>Status</th><td><span class="badge bg-<?php echo h($summaryColor); ?>"><?php echo h($summary['status']); ?></span></td></tr>
                    <?php if ($summary['failed'] > 0) { ?>
                        <tr><th>Folder Debug</th><td><?php echo h(str_replace(__DIR__, '', $summary['debug'])); ?></td></tr>
                    <?php } ?>
                </table>

                <?php if ($summary['failed'] > 0) { ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <strong><?php echo h($summary['failed']); ?> voucher gagal diupdate.</strong>
                        Detail VCH No., UNIQUEID, ITEM_CODE, dan pesan Tally ditampilkan di bawah.
                    </div>
                <?php } else { ?>
                    <div class="alert alert-success mt-3 mb-0">
                        <strong>Semua voucher berhasil diupdate di Tally.</strong>
                    </div>
                <?php } ?>
            </div>
        </div>

        <?php if (!empty($failedRows)) { ?>
            <div class="card shadow-sm mb-3 border-danger">
                <div class="card-header fw-bold text-danger">Detail Data Gagal Diupdate ke Tally</div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height:500px;">
                        <table class="table table-bordered table-striped table-sm align-middle">
                            <thead class="table-danger sticky-top">
                                <tr>
                                    <th>No.</th>
                                    <th>Date</th>
                                    <th>VCH No.</th>
                                    <th>UNIQUEID</th>
                                    <th>ITEM_CODE</th>
                                    <th>Qty</th>
                                    <th>Informasi Tally</th>
                                    <th>Response Count</th>
                                    <th>Debug XML</th>
                                    <th>Debug Response</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($failedRows as $fr) { ?>
                                    <tr>
                                        <td><?php echo h($fr['no']); ?></td>
                                        <td><?php echo h($fr['date']); ?></td>
                                        <td><strong><?php echo h($fr['vch_no']); ?></strong></td>
                                        <td><?php echo h($fr['uniqueid']); ?></td>
                                        <td><b><?php echo h($fr['item_material']); ?></b></td>
                                        <td class="text-end"><?php echo h($fr['qty']); ?></td>
                                        <td style="min-width:280px;"><?php echo h($fr['error']); ?></td>
                                        <td><?php echo h($fr['counts']); ?></td>
                                        <td><small><?php echo h(str_replace(__DIR__, '', $fr['debug_xml'])); ?></small></td>
                                        <td><small><?php echo h(str_replace(__DIR__, '', $fr['debug_response'])); ?></small></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php } ?>
    <?php } ?>

    <div class="card shadow-sm">
        <div class="card-header fw-bold">Data Consumption Material</div>
        <div class="card-body">
            <div class="table-responsive" style="max-height:500px;">
                <table class="table table-bordered table-striped table-sm">
                    <thead class="table-dark sticky-top">
                        <tr>
                            <th>UNIQUEID (A)</th>
                            <th>VCH-NO (B)</th>
                            <th>DATE (C)</th>
                            <th>ITEM_CODE (D)</th>
                            <th>Cons. QTY (E)</th>
                            <th>Cons. RATE (F)</th>
                            <th>Cons. AMOUNT (G)</th>
                            <th>NARRATION (H)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultRows as $r) { 
                            $rl = array_change_key_case($r, CASE_LOWER);
                        ?>
                        <tr>
                            <td><?php echo h(isset($rl['uniqueid']) ? $rl['uniqueid'] : ''); ?></td>
                            <td><b><?php echo h(isset($rl['vch_no']) ? $rl['vch_no'] : ''); ?></b></td>
                            <td>
                                <?php 
                                $origUI = isset($rl['date']) ? normalizeSqlDate($rl['date']) : '';
                                if($origUI !== '') {
                                    $dObjUI = new DateTime($origUI);
                                    $dObjUI->modify('last day of this month')->modify('-1 day');
                                    echo h($dObjUI->format('Y-m-d'));
                                }
                                ?>
                            </td>
                            <td><b><?php echo h(isset($rl['item_material']) ? $rl['item_material'] : ''); ?></b></td>
                            <td class="text-end"><?php echo h(isset($rl['cons_qty']) ? $rl['cons_qty'] : ''); ?></td>
                            <td class="text-end"><?php echo h(isset($rl['cons_rate']) ? rate5($rl['cons_rate']) : '0.00000'); ?></td>
                            <td class="text-end"><?php echo h(isset($rl['cons_amount']) ? $rl['cons_amount'] : '0.00'); ?></td>
                            <td><?php echo h(isset($rl['narration']) ? $rl['narration'] : ''); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
function setAction(a) {
    document.getElementById('action').value = a;
    document.getElementById('frmProd').submit();
}

function pilihServerTally() {
    var combo = document.getElementById('server_combo');
    if (!combo || combo.value == '') return;
    var p = combo.value.split('|');
    document.getElementById('tally_ip').value = p[0];
    document.getElementById('tally_port').value = p[1];
}

function showExportProgress() {
    var box = document.getElementById('exportProgressBox');
    var bar = document.getElementById('exportProgressBar');
    var txt = document.getElementById('exportProgressText');

    if (box) box.style.display = 'block';
    var pct = 5;
    if (bar) {
        bar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-warning';
        bar.style.width = pct + '%';
        bar.innerHTML = pct + '%';
    }
    if (txt) txt.innerHTML = 'Mohon tunggu, sedang memproses update data Consumption ke Tally Server...';

    window._progressTimer = setInterval(function () {
        if (pct < 90) {
            pct += 5;
            if (bar) {
                bar.style.width = pct + '%';
                bar.innerHTML = pct + '%';
            }
            if (txt) {
                txt.innerHTML = 'Sedang mengirim data ke Tally... Mohon jangan tutup browser (' + pct + '%)';
            }
        }
    }, 800);
}

function confirmExport() {
    if (confirm('YAKIN INGIN MENGUBAH DATA DI TALLY? Proses ini akan meng-ALTER (mengedit) voucher yang sudah ada dan memperbarui tanggalnya menjadi H-1 di Akhir Bulan.')) {
        showExportProgress();
        setTimeout(function () {
            setAction('export');
        }, 200);
    }
}
</script>

</body>
</html>