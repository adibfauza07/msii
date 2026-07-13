<?php
// C:\xampp\htdocs\msii\finance\import_prod_tally.php
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
// Prioritas: session active_plant, lalu login user.
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
    : 'tally_import_p2.php?tab=prod';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function x($s) { return htmlspecialchars(trim((string)$s), ENT_QUOTES, 'UTF-8'); }
function qx($sql, $params = array()) { return q($sql, $params); }

// Format DEST_RATE konsisten 5 digit di belakang koma.
function rate5($value) {
    return number_format(round((float)$value, 5), 5, '.', '');
}

// PERBAIKAN BUG PHP 5.4 (fetchRows tidak bisa di-loop langsung jika hasilnya false)
function fetchAllRows($stmt) {
    $rows = array();
    if (!$stmt) return $rows;
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }
    return $rows;
}

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

function responseOk($res) {
    if (strpos($res, '<CREATED>1</CREATED>') !== false) return true;
    if (strpos($res, '<ALTERED>1</ALTERED>') !== false) return true;
    if (strpos($res, '<ERRORS>0</ERRORS>') !== false && strpos($res, '<LINEERROR>') === false) return true;
    return false;
}

function tallyLineError($res) {
    if (preg_match_all('/<LINEERROR>(.*?)<\/LINEERROR>/is', (string)$res, $m)) { return trim(strip_tags(html_entity_decode(implode(' | ', $m[1]), ENT_QUOTES, 'UTF-8'))); }
    if (stripos((string)$res, 'CURL ERROR') !== false) return trim((string)$res);
    return '';
}

function saveDebugXml($name, $xml) {
    $dir = __DIR__ . '/debug_tally_xml_prod';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $file = $dir . '/' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$name) . '.xml';
    @file_put_contents($file, $xml);
    return $file;
}

/* TALLY SERVER SETTINGS */
function ensureTallyServerTable() { qx("IF OBJECT_ID('dbo.Tally_Server_Master', 'U') IS NULL CREATE TABLE dbo.Tally_Server_Master (ID INT IDENTITY(1,1) PRIMARY KEY, ServerName VARCHAR(100) NULL, TallyIP VARCHAR(100) NOT NULL, TallyPort VARCHAR(10) NOT NULL, IsDefault BIT NOT NULL DEFAULT 0)", array()); }
function seedTallyServers() { ensureTallyServerTable(); $cek = qx("SELECT COUNT(*) AS JML FROM dbo.Tally_Server_Master", array()); $r = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC); if ($r && (int)$r['JML'] == 0) { qx("INSERT INTO dbo.Tally_Server_Master (ServerName, TallyIP, TallyPort, IsDefault) VALUES ('Localhost', '127.0.0.1', '9002', 0), ('dianero99', 'dianero99', '9002', 1)", array()); } }
function loadTallyServers() { seedTallyServers(); return fetchAllRows(qx("SELECT ID, ServerName, TallyIP, TallyPort, IsDefault FROM dbo.Tally_Server_Master ORDER BY IsDefault DESC", array())); }
function testTallyConnection($ip, $port) { $fp = @fsockopen($ip, $port, $errno, $errstr, 3); if ($fp) { fclose($fp); return 'OK: Tally port terbuka di ' . $ip . ':' . $port; } return 'ERROR: Tidak bisa konek ke ' . $ip . ':' . $port . ' - ' . $errstr; }

/* FUNGSI UNTUK MENGAMBIL MASTER YANG BELUM ADA DI TALLY 
   Agar Voucher Production tidak error saat anak BOM belum terdaftar 
*/
function getMissingMaterials($tally_ip, $tally_port) {
    // 1. Ambil daftar kode material unik dari tabel Tally_Prod
    $stmt = qx("SELECT DISTINCT ITEM AS NAME FROM dbo.Tally_Prod WHERE ITEM <> ''", array());
    $dbMaterials = fetchAllRows($stmt);
    
    // 2. Buat XML untuk mengecek ke Tally
    $checkXml = '<?xml version="1.0" encoding="UTF-8"?>
<ENVELOPE>
  <HEADER>
    <TALLYREQUEST>Import Data</TALLYREQUEST>
  </HEADER>
  <BODY>
    <IMPORTDATA>
      <REQUESTDESC>
        <REPORTNAME>All Masters</REPORTNAME>
      </REQUESTDESC>
      <REQUESTDATA>
        <TALLYMESSAGE xmlns:UDF="TallyUDF">';
    
    foreach ($dbMaterials as $mat) {
        $matName = trim((string)$mat['NAME']);
        if ($matName === '') continue;
        // FIX: Ditambahkan <PARENT>Finished Goods</PARENT>
        $checkXml .= '
          <STOCKITEM NAME="'.x($matName).'" ACTION="Create">
            <NAME.LIST>
              <NAME>'.x($matName).'</NAME>
            </NAME.LIST>
            <PARENT>Finished Goods</PARENT>
          </STOCKITEM>';
    }
    
    $checkXml .= '
        </TALLYMESSAGE>
      </REQUESTDATA>
    </IMPORTDATA>
  </BODY>
</ENVELOPE>';

    // FIX: URL Assignment dan Pemanggilan Curl
    $url = 'http://' . $tally_ip . ':' . $tally_port;
    $tally_error = sendToTally($checkXml, $url);
    
    // 4. Cek apakah ada error "does not exist"
    $missing = array();
    if (preg_match_all('/does not exist.*?\'([^\']+?)\'/i', (string)$tally_error, $m)) {
        $missing[] = $m[1];
    }
    
    return $missing;
}

/* BUILD XML MASTER YANG BELUM ADA */
function buildMasterXml($missingMats) {
    if (empty($missingMats)) return '';
    
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<ENVELOPE>
  <HEADER>
    <TALLYREQUEST>Import Data</TALLYREQUEST>
  </HEADER>
  <BODY>
    <IMPORTDATA>
      <REQUESTDESC>
        <REPORTNAME>All Masters</REPORTNAME>
      </REQUESTDESC>
      <REQUESTDATA>
        <TALLYMESSAGE xmlns:UDF="TallyUDF">';
    
    foreach ($missingMats as $matName) {
        // FIX: Ditambahkan <PARENT>Finished Goods</PARENT>
        $xml .= '
          <STOCKITEM NAME="'.x($matName).'" ACTION="Create">
            <NAME.LIST>
              <NAME>'.x($matName).'</NAME>
            </NAME.LIST>
            <PARENT>Finished Goods</PARENT>
          </STOCKITEM>';
    }
    
    $xml .= '
        </TALLYMESSAGE>
      </REQUESTDATA>
    </IMPORTDATA>
  </BODY>
</ENVELOPE>';
    return $xml;
}

/* BUILD XML PRODUCTION */
function buildProdXml($rows) {
    $vouchersXml = '';
    foreach ($rows as $r) {
        $rl = array_change_key_case($r, CASE_LOWER);
        
        $unik = isset($rl['uniqueid']) ? trim((string)$rl['uniqueid']) : '';
        $vchNo = isset($rl['vch_no']) ? trim((string)$rl['vch_no']) : '';
        $date = isset($rl['prod_date']) ? trim((string)$rl['prod_date']) : '';
        $itemName = isset($rl['item']) ? trim((string)$rl['item']) : '';
        $qty = isset($rl['dest_qty']) ? trim((string)$rl['dest_qty']) : '0';
        $rate = isset($rl['dest_rate']) ? trim((string)$rl['dest_rate']) : '0';
        $rateFixed5 = rate5($rate);
        $amount = isset($rl['dest_amount']) ? trim((string)$rl['dest_amount']) : '0';
        $narration = isset($rl['narration']) ? trim((string)$rl['narration']) : '';
        
        $amountNeg = round((float)$amount, 2) * -1;
        $guid = 'udi-IMCPRODUKSI-' . $unik . '-' . $vchNo;

        $vouchersXml .= '
          <VOUCHER REMOTEID="'.x($guid).'" VCHTYPE="Production" ACTION="Create">
            <GUID>'.x($guid).'</GUID>
            <DATE>'.x($date).'</DATE>
            <EFFECTIVEDATE>'.x($date).'</EFFECTIVEDATE>
            <VOUCHERTYPENAME>Production</VOUCHERTYPENAME>
            <ISINVOICE>Yes</ISINVOICE>
            <VOUCHERNUMBER>'.x($vchNo).'</VOUCHERNUMBER>
            <NARRATION>'.x($narration).'</NARRATION>
            <INVENTORYENTRIESIN.LIST>
              <STOCKITEMNAME>'.x($itemName).'</STOCKITEMNAME>
              <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>
              <RATE>'.x($rateFixed5).'</RATE>
              <AMOUNT>'.x($amountNeg).'</AMOUNT>
              <ACTUALQTY>'.x($qty).'</ACTUALQTY>
              <BILLEDQTY>'.x($qty).'</BILLEDQTY>
              <BATCHALLOCATIONS.LIST>
                <GODOWNNAME>Main Location</GODOWNNAME>
                <BATCHNAME>Primary Batch</BATCHNAME>
                <DESTINATIONGODOWNNAME>Main Location</DESTINATIONGODOWNNAME>
                <AMOUNT>'.x($amountNeg).'</AMOUNT>
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

// FIX: Perbaikan Typo Array Key Server Default
 $tally_ip = isset($defaultSrv['TallyIP']) ? $defaultSrv['TallyIP'] : '127.0.0.1';
 $tally_port = isset($defaultSrv['TallyPort']) ? $defaultSrv['TallyPort'] : '9002';

 $action = isset($_POST['action']) ? $_POST['action'] : '';
 $fromDate = isset($_POST['from_date']) ? $_POST['from_date'] : date('Y-m-01');
 $toDate = isset($_POST['to_date']) ? $_POST['to_date'] : date('Y-m-d');
 $message = '';
 $resultRows = array();
 $summary = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // FIX: Perbaikan Typo Post Variable
    $tally_ip = isset($_POST['tally_ip']) ? trim($_POST['tally_ip']) : $tally_ip;
    $tally_port = isset($_POST['tally_port']) ? trim($_POST['tally_port']) : $tally_port;
}

if ($action == 'test') {
    $message = testTallyConnection($tally_ip, $tally_port);
} elseif ($action == 'preview') {
    $stmt = qx("EXECUTE sp_GenerateTallyProd ?, ?", array($fromDate, $toDate));
    $resultRows = fetchAllRows($stmt);
    $message = 'Preview data Production selesai. Total: ' . count($resultRows);
} elseif ($action == 'import_table') {
    qx("DELETE FROM dbo.Tally_Prod", array());
    $stmt = qx("EXECUTE sp_GenerateTallyProd ?, ?", array($fromDate, $toDate));
    $rows = fetchAllRows($stmt);
    foreach ($rows as $r) {
        $rl = array_change_key_case($r, CASE_LOWER);
        qx("INSERT INTO dbo.Tally_Prod (UNIQUEID, VCH_NO, PROD_DATE, ITEM, DEST_QTY, DEST_RATE, DEST_AMOUNT, NARRATION) VALUES (?, ?, ?, ?, ?, CAST(? AS DECIMAL(38,5)), ?, ?)", array(
            isset($rl['uniqueid']) ? $rl['uniqueid'] : '',
            isset($rl['vch_no']) ? $rl['vch_no'] : '',
            isset($rl['prod_date']) ? $rl['prod_date'] : '',
            isset($rl['item']) ? $rl['item'] : '',
            isset($rl['dest_qty']) ? $rl['dest_qty'] : 0,
            isset($rl['dest_rate']) ? rate5($rl['dest_rate']) : '0.00000',
            isset($rl['dest_amount']) ? $rl['dest_amount'] : 0,
            isset($rl['narration']) ? $rl['narration'] : ''
        ));
    }
    $message = 'Berhasil simpan ke tabel Tally_Prod. Total: ' . count($rows);
    $resultRows = $rows;
} elseif ($action == 'export') {
    $stmt = qx("SELECT UNIQUEID, VCH_NO, PROD_DATE, ITEM, DEST_QTY, DEST_RATE, DEST_AMOUNT, NARRATION FROM dbo.Tally_Prod ORDER BY PROD_DATE, VCH_NO", array());
    $resultRows = fetchAllRows($stmt);
    if (count($resultRows) == 0) {
        $message = 'Tabel Tally_Prod kosong. Klik Import ke Tabel dulu.';
    } else {
        $url = 'http://' . $tally_ip . ':' . $tally_port;
        $proceedToProd = true;
        
        // STEP 1: CEK DAN KIRIM MASTER YANG BELUM ADA
        $missingMats = getMissingMaterials($tally_ip, $tally_port);
        
        if (!empty($missingMats)) {
            $masterXml = buildMasterXml($missingMats);
            $masterDebugFile = saveDebugXml('PROD_Master_Missing', $masterXml);
            $masterRes = sendToTally($masterXml, $url);
            
            if (!responseOk($masterRes)) {
                $proceedToProd = false;
                $summary = array(
                    'total' => count($resultRows), 
                    'status' => 'Gagal', 
                    'debug' => $masterDebugFile, 
                    'error' => 'GAGAL KIRIM MASTER: Material berikut belum ada di Tally: ' . implode(', ', $missingMats),
                    'response' => $masterRes
                );
                $message = 'GAGAL: Beberapa Material Anak belum ada di Master Tally. System sudah mencoba membuatkan secara otomatis, namun gagal.';
            }
        }
        
        // FIX: Langkah 2 harus dieksekusi jika langkah 1 berhasil / jika material sudah komplit
        if ($proceedToProd) {
            // STEP 2: KIRIM VOUCHER PRODUCTION
            $xml = buildProdXml($resultRows);
            $debugFile = saveDebugXml('PROD_Export', $xml);
            $res = sendToTally($xml, $url);
            $ok = responseOk($res);
            
            $summary = array(
                'total' => count($resultRows), 
                'status' => $ok ? 'Sukses' : 'Gagal', 
                'debug' => $debugFile, 
                'error' => tallyLineError($res), 
                'response' => $res
            );
            $message = 'Kirim ke Tally selesai. Status: ' . ($ok ? 'SUKSES' : 'GAGAL');
        }
    }
} else {
    $stmt = qx("EXECUTE sp_GenerateTallyProd ?, ?", array($fromDate, $toDate));
    $resultRows = fetchAllRows($stmt);
}
?>
<?php include 'layout.php'; ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0">EXPORT PRODUCTION TO TALLY <?php echo h($current_plant_label); ?></h3>
        <a href="<?php echo h($back_import_url); ?>" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <?php if ($message != '') { ?>
        <div class="alert alert-info"><?php echo h($message); ?></div>
    <?php } ?>

    <div id="exportProgressBox" class="card shadow-sm mb-3" style="display:none;">
        <div class="card-header fw-bold">Progress Import Production</div>
        <div class="card-body">
            <div class="progress" style="height:26px;">
                <div id="exportProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:5%">Preparing...</div>
            </div>
            <div id="exportProgressText" class="mt-2 text-muted">Mohon tunggu, sedang memproses data...</div>
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
            <div class="card-header fw-bold">Filter Data Production</div>
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
                        <button type="button" class="btn btn-info" onclick="setAction('import_table')">Import ke Tally_Prod</button>
                        <button type="button" class="btn btn-danger" onclick="confirmExport()">Send to Tally</button>
                    </div>
                </div>
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
                bar.className = 'progress-bar bg-<?php echo $summary['status'] == 'Sukses' ? 'success' : 'danger'; ?>';
                bar.style.width = '100%';
                bar.innerHTML = '100% Selesai';
                if (txt) txt.innerHTML = 'Import selesai. Status: <?php echo h($summary['status']); ?>.';
            }
        });
        </script>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Summary Import Production</div>
            <div class="card-body">
                <table class="table table-bordered table-sm w-auto">
                    <tr><th>Total Voucher</th><td><?php echo h($summary['total']); ?></td></tr>
                    <tr><th>Status</th><td><span class="badge bg-<?php echo $summary['status'] == 'Sukses' ? 'success' : 'danger'; ?>"><?php echo h($summary['status']); ?></span></td></tr>
                    <tr><th>Debug File</th><td><?php echo h(str_replace(__DIR__, '', $summary['debug'])); ?></td></tr>
                </table>

                <?php if($summary['status'] == 'Gagal') { ?>
                    <div class="alert alert-danger mt-3">
                        <strong>Error dari Tally:</strong><br>
                        <pre style="white-space:pre-wrap;"><?php echo h($summary['error']); ?></pre>
                    </div>
                <?php } else { ?>
                    <div class="alert alert-success mt-3">
                        <strong>Berhasil dikirim ke Tally.</strong>
                    </div>
                <?php } ?>
            </div>
        </div>
    <?php } ?>

    <div class="card shadow-sm">
        <div class="card-header fw-bold">Data Production</div>
        <div class="card-body">
            <div class="table-responsive" style="max-height:500px;">
                <table class="table table-bordered table-striped table-sm">
                    <thead class="table-dark sticky-top">
                        <tr>
                            <th>UNIQUEID</th>
                            <th>VCH-NO</th>
                            <th>DATE</th>
                            <th>ITEM</th>
                            <th>DEST. QTY</th>
                            <th>DEST. RATE</th>
                            <th>DEST. AMOUNT</th>
                            <th>NARRATION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultRows as $r) { 
                            $rl = array_change_key_case($r, CASE_LOWER);
                        ?>
                        <tr>
                            <td><?php echo h(isset($rl['uniqueid']) ? $rl['uniqueid'] : ''); ?></td>
                            <td><b><?php echo h(isset($rl['vch_no']) ? $rl['vch_no'] : ''); ?></b></td>
                            <td><?php echo h(isset($rl['prod_date']) ? $rl['prod_date'] : ''); ?></td>
                            <td><?php echo h(isset($rl['item']) ? $rl['item'] : ''); ?></td>
                            <td class="text-end"><?php echo h(isset($rl['dest_qty']) ? $rl['dest_qty'] : ''); ?></td>
                            <td class="text-end"><?php echo h(isset($rl['dest_rate']) ? rate5($rl['dest_rate']) : '0.00000'); ?></td>
                            <td class="text-end"><?php echo h(isset($rl['dest_amount']) ? $rl['dest_amount'] : ''); ?></td>
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
        bar.className = 'progress-bar progress-bar-striped progress-bar-animated';
        bar.style.width = pct + '%';
        bar.innerHTML = pct + '%';
    }
    if (txt) txt.innerHTML = 'Mohon tunggu, sedang memproses data Production ke Tally Server...';

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
    if (confirm('Kirim seluruh data Production ke Tally sekarang? (System otomatis cek & buat Master Material yang belum ada di Tally terlebih dahulu)')) {
        showExportProgress();
        setTimeout(function () {
            setAction('export');
        }, 200);
    }
}
</script>

</body>
</html>