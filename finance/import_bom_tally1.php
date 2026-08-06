<?php
// C:\xampp\htdocs\msii\finance\import_bom_tally.php
// PHP 5.4 compatible
// Import BOM ke Tally via XML Direct

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
   AKSES KHUSUS P1
   ========================= */
 $login_user = isset($_SESSION['db_user']) ? strtolower(trim($_SESSION['db_user'])) : '';
 $active_plant = isset($_SESSION['active_plant']) ? strtolower(trim($_SESSION['active_plant'])) : '';

if (!($login_user == 'plant1' || $active_plant == 'p1')) {
    echo "<script>
        alert('Menu Import BOM To Tally hanya untuk Plant P1.');
        window.location.href = 'dashboard.php';
    </script>";
    exit;
}

/* =========================
   HELPER
   ========================= */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function x($s) { return htmlspecialchars(trim((string)$s), ENT_QUOTES, 'UTF-8'); }
function qx($sql, $params = array()) { return q($sql, $params); }

function fetchAllRows($stmt) {
    $rows = array();
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }
    return $rows;
}


// Ambil nilai kolom BOM, mendukung alias A..N maupun Col_A..Col_N
function getBomField($row, $letter) {
    $rl = array_change_key_case($row, CASE_LOWER);
    $key = strtolower((string)$letter);
    $colKey = 'col_' . $key;

    if (array_key_exists($key, $rl)) return $rl[$key];
    if (array_key_exists($colKey, $rl)) return $rl[$colKey];
    return null;
}

// Simpan hasil SP ke SQL Server.
// Data lama Tally_BOM diganti dengan hasil preview terbaru.
function saveBomToSqlServer($rows) {
    if (!is_array($rows) || count($rows) == 0) return 0;

    qx("DELETE FROM [msData].[dbo].[Tally_BOM]", array());

    $sql = "INSERT INTO [msData].[dbo].[Tally_BOM]
            ([Col_A], [Col_B], [Col_C], [Col_D], [Col_E], [Col_F], [Col_G],
             [Col_H], [Col_I], [Col_J], [Col_K], [Col_L], [Col_M], [Col_N])
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $saved = 0;

    foreach ($rows as $r) {
        $colBRaw = trim((string)getBomField($r, 'b'));
        $colJ = trim((string)getBomField($r, 'j'));

        // Ambil item code 8 karakter pertama dari Col_B.
        $colBCode = substr($colBRaw, 0, 8);

        // Buang tanda strip pemisah setelah item code.
        // Contoh: 010476-0 - Valve Lid  ->  010476-0 Valve Lid
        // Tanda strip di dalam item code 010476-0 tetap dipertahankan.
        $colBDesc = trim(substr($colBRaw, 8));
        $colBDesc = ltrim($colBDesc, "- \t\n\r\0\x0B");
        $colB = $colBCode;
        if ($colBDesc !== '') {
            $colB .= ' ' . $colBDesc;
        }

        // Col_E = item code 8 karakter dari Col_B
        $colE = $colBCode;

        // Col_J hanya disimpan 8 karakter paling kiri
        $colJ8 = substr($colJ, 0, 8);

        qx($sql, array(
            getBomField($r, 'a'),
            $colB,
            getBomField($r, 'c'),
            getBomField($r, 'd'),
            $colE,
            getBomField($r, 'f'),
            getBomField($r, 'g'),
            getBomField($r, 'h'),
            getBomField($r, 'i'),
            $colJ8,
            getBomField($r, 'k'),
            getBomField($r, 'l'),
            getBomField($r, 'm'),
            getBomField($r, 'n')
        ));

        $saved++;
    }

    return $saved;
}

function sendToTally($xml, $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // BOM butuh timeout lebih lama
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
    if (preg_match_all('/<LINEERROR>(.*?)<\/LINEERROR>/is', (string)$res, $m)) {
        $msg = implode(' | ', $m[1]);
        return trim(strip_tags(html_entity_decode($msg, ENT_QUOTES, 'UTF-8')));
    }
    if (stripos((string)$res, 'CURL ERROR') !== false) return trim((string)$res);
    return '';
}

function saveDebugXml($name, $xml) {
    $dir = __DIR__ . '/debug_tally_xml_bom';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$name);
    $file = $dir . '/' . $safeName . '.xml';
    @file_put_contents($file, $xml);
    return $file;
}

/* =========================
   TALLY SERVER SETTINGS
   ========================= */
function ensureTallyServerTable() {
    qx("IF OBJECT_ID('dbo.Tally_Server_Master', 'U') IS NULL
        BEGIN
            CREATE TABLE dbo.Tally_Server_Master
            (ID INT IDENTITY(1,1) PRIMARY KEY, ServerName VARCHAR(100) NULL, TallyIP VARCHAR(100) NOT NULL, TallyPort VARCHAR(10) NOT NULL, IsDefault BIT NOT NULL DEFAULT 0, CreatedAt DATETIME NOT NULL DEFAULT GETDATE())
        END", array());
}

function seedTallyServers() {
    ensureTallyServerTable();
    $cek = qx("SELECT COUNT(*) AS JML FROM dbo.Tally_Server_Master", array());
    $r = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC);
    if ($r && (int)$r['JML'] == 0) {
        qx("INSERT INTO dbo.Tally_Server_Master (ServerName, TallyIP, TallyPort, IsDefault) VALUES ('Localhost', '127.0.0.1', '9002', 0), ('dianero99', 'dianero99', '9002', 1)", array());
    }
}

function loadTallyServers() {
    seedTallyServers();
    $stmt = qx("SELECT ID, ServerName, TallyIP, TallyPort, IsDefault FROM dbo.Tally_Server_Master ORDER BY IsDefault DESC, ServerName", array());
    return fetchAllRows($stmt);
}

function deleteTallyServer($id) {
    $id = (int)$id;
    if ($id <= 0) return 'ID tidak valid.';
    qx("DELETE FROM dbo.Tally_Server_Master WHERE ID = ?", array($id));
    return 'Server berhasil dihapus.';
}

function testTallyConnection($ip, $port) {
    $fp = @fsockopen($ip, $port, $errno, $errstr, 3);
    if ($fp) { fclose($fp); return 'OK: Tally port terbuka di ' . $ip . ':' . $port; }
    return 'ERROR: Tidak bisa konek ke ' . $ip . ':' . $port . ' - ' . $errstr;
}

/* =========================
   BUILD XML BOM
   ========================= */
function buildBomXml($rows) {
    $parents = array();

    foreach ($rows as $r) {
        $rl = array_change_key_case($r, CASE_LOWER);
        
        // FLEKSIBEL: Bisa baca dari SP (alias 'b') atau dari Tabel (alias 'col_b')
        $parentName = isset($rl['b']) ? trim((string)$rl['b']) : (isset($rl['col_b']) ? trim((string)$rl['col_b']) : '');
        $matName = isset($rl['j']) ? trim((string)$rl['j']) : (isset($rl['col_j']) ? trim((string)$rl['col_j']) : '');
        
        if ($parentName !== '' && $matName !== '') {
            if (!isset($parents[$parentName])) {
                $unitG = isset($rl['g']) ? trim((string)$rl['g']) : (isset($rl['col_g']) ? trim((string)$rl['col_g']) : 'PCS');
                $compH = isset($rl['h']) ? trim((string)$rl['h']) : (isset($rl['col_h']) ? trim((string)$rl['col_h']) : 'Default');
                $compI = isset($rl['i']) ? trim((string)$rl['i']) : (isset($rl['col_i']) ? trim((string)$rl['col_i']) : '1000');
                
                $parents[$parentName] = array(
                    'unit' => $unitG !== '' ? $unitG : 'PCS',
                    'comp_list_name' => $compH !== '' ? $compH : 'Default',
                    'comp_basic_qty' => $compI !== '' ? $compI : '1000',
                    'items' => array()
                );
            }
            
            $qty = isset($rl['k']) ? trim((string)$rl['k']) : (isset($rl['col_k']) ? trim((string)$rl['col_k']) : '0');
            if ($qty === '') $qty = '0';
            
            $nature = isset($rl['m']) ? trim((string)$rl['m']) : (isset($rl['col_m']) ? trim((string)$rl['col_m']) : 'Component');
            $godown = isset($rl['n']) ? trim((string)$rl['n']) : (isset($rl['col_n']) ? trim((string)$rl['col_n']) : 'Main Location');
            
            if ($nature === '') $nature = 'Component';
            if ($godown === '') $godown = 'Main Location';
            
            $parents[$parentName]['items'][] = array(
                'name' => $matName,
                'qty' => $qty,
                'nature' => $nature,
                'godown' => $godown
            );
        }
    }

    $parentsXml = '';
    
    foreach ($parents as $pName => $pData) {
        $itemsListXml = '';
        foreach ($pData['items'] as $item) {
            $itemsListXml .= '
                <MULTICOMPONENTITEMLIST.LIST>
                  <STOCKITEMNAME>'.x($item['name']).'</STOCKITEMNAME>
                  <ACTUALQTY>'.x($item['qty']).'</ACTUALQTY>
                  <NATUREOFITEM>'.x($item['nature']).'</NATUREOFITEM>
                  <GODOWNNAME>'.x($item['godown']).'</GODOWNNAME>
                </MULTICOMPONENTITEMLIST.LIST>';
        }

        // Tanpa ACTION="Create" -> Aman untuk Update BOM yang sudah ada
        $parentsXml .= '
          <STOCKITEM NAME="'.x($pName).'">
            <NAME.LIST>
              <NAME>'.x($pName).'</NAME>
            </NAME.LIST>
            <BASEUNITS>'.x($pData['unit']).'</BASEUNITS>
            <MULTICOMPONENTLIST.LIST>
              <COMPONENTLISTNAME>'.x($pData['comp_list_name']).'</COMPONENTLISTNAME>
              <COMPONENTBASICQTY>'.x($pData['comp_basic_qty']).'</COMPONENTBASICQTY>
              '.$itemsListXml.'
            </MULTICOMPONENTLIST.LIST>
          </STOCKITEM>';
    }

    return '<?xml version="1.0" encoding="UTF-8"?>
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
        <TALLYMESSAGE xmlns:UDF="TallyUDF">
          '.$parentsXml.'
        </TALLYMESSAGE>
      </REQUESTDATA>
    </IMPORTDATA>
  </BODY>
</ENVELOPE>';
}

/* =========================
   REQUEST HANDLER
   ========================= */
 $tally_servers = loadTallyServers();
 $defaultSrv = $tally_servers[0];

 $tally_ip = isset($defaultSrv['TallyIP']) ? $defaultSrv['TallyIP'] : '127.0.0.1';
 $tally_port = isset($defaultSrv['TallyPort']) ? $defaultSrv['TallyPort'] : '9002';

 $action = isset($_POST['action']) ? $_POST['action'] : '';
 $message = '';
 $resultRows = array();
 $exportDetails = array();
 $summary = null;
 $xmlPreview = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tally_ip = isset($_POST['tally_ip']) ? trim($_POST['tally_ip']) : $tally_ip;
    $tally_port = isset($_POST['tally_port']) ? trim($_POST['tally_port']) : $tally_port;
}

if ($action == 'delete_server') {
    $serverId = isset($_POST['server_id_delete']) ? (int)$_POST['server_id_delete'] : 0;
    $message = deleteTallyServer($serverId);
    $tally_servers = loadTallyServers();
} elseif ($action == 'test') {
    $message = testTallyConnection($tally_ip, $tally_port);
} elseif ($action == 'preview') {
    // Ambil data dari SP langsung untuk preview
    $stmt = qx("EXECUTE sp_GenerateTallyBOM", array());
    $resultRows = fetchAllRows($stmt);
    $message = 'Preview data BOM selesai. Total row: ' . count($resultRows);
} elseif ($action == 'send_sql') {
    // Jalankan ulang SP lalu simpan hasilnya ke tabel Tally_BOM
    $stmt = qx("EXECUTE sp_GenerateTallyBOM", array());
    $resultRows = fetchAllRows($stmt);

    if (count($resultRows) == 0) {
        $message = 'Data preview kosong. Tidak ada data yang dikirim ke SQL Server.';
    } else {
        $savedRows = saveBomToSqlServer($resultRows);
        $message = 'Send to SQL Server berhasil. Total row tersimpan: ' . $savedRows .
                   '. Strip pemisah setelah item code pada Col_B dibuang, Col_E diisi 8 karakter kiri Col_B, dan Col_J dipotong menjadi 8 karakter.';
    }
} elseif ($action == 'preview_xml') {
    $stmt = qx("EXECUTE sp_GenerateTallyBOM", array());
    $resultRows = fetchAllRows($stmt);
    $xmlPreview = buildBomXml($resultRows);
    $message = 'XML Preview berhasil di-generate.';
} elseif ($action == 'export') {
    // Ambil data dari tabel Tally_BOM yang sudah diimport sebelumnya
    $stmt = qx("SELECT Col_A, Col_B, Col_C, Col_D, Col_E, Col_F, Col_G, Col_H, Col_I, Col_J, Col_K, Col_L, Col_M, Col_N FROM dbo.Tally_BOM ORDER BY Col_B, Col_J", array());
    $resultRows = fetchAllRows($stmt);

    if (count($resultRows) == 0) {
        $message = 'Tabel Tally_BOM kosong. Klik Import ke Tabel dulu di halaman Tally Import P2 tab BOM.';
    } else {
        $url = 'http://' . $tally_ip . ':' . $tally_port;
        $xml = buildBomXml($resultRows);
        
        $debugFile = saveDebugXml('BOM_Export_All', $xml);
        $res = sendToTally($xml, $url);
        $ok = responseOk($res);

        $summary = array(
            'total_items' => count($resultRows),
            'status' => $ok ? 'Sukses' : 'Gagal',
            'debug_file' => $debugFile
        );

        $exportDetails[] = array(
            'ok' => $ok,
            'error_msg' => tallyLineError($res),
            'debug_file' => $debugFile,
            'response' => $res
        );

        $message = 'Import BOM selesai. Status: ' . ($ok ? 'SUKSES' : 'GAGAL');
    }
} else {
    // Default load data
    $stmt = qx("EXECUTE sp_GenerateTallyBOM", array());
    $resultRows = fetchAllRows($stmt);
}

?>
<?php include 'layout.php'; ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0">EXPORT BOM TO TALLY P1</h3>
        <a href="tally_import_p2.php?tab=bom" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Kembali ke Tabel BOM
        </a>
    </div>

    <?php if ($message != '') { ?>
        <div class="alert alert-info"><?php echo h($message); ?></div>
    <?php } ?>

    <div class="alert alert-warning">
        <b>Alur Kerja:</b> 
        1. Klik <b>Preview Data</b> untuk cek data dari Database. 
        2. Klik <b>Send to SQL Server</b> untuk mengganti isi tabel <code>Tally_BOM</code> dengan hasil preview terbaru. 
        3. Klik <b>Preview XML</b> untuk lihat bentuk XML yang akan dikirim. 
        4. Klik <b>Send BOM to Tally</b> untuk mengirim data dari tabel <code>Tally_BOM</code> langsung ke Tally Server. 
        <br>Debug XML disimpan di: <b>/msii/finance/debug_tally_xml_bom/</b>
    </div>

    <!-- PROGRESS BAR -->
    <div id="exportProgressBox" class="card shadow-sm mb-3" style="display:none;">
        <div class="card-header fw-bold">Progress Import BOM</div>
        <div class="card-body">
            <div class="progress" style="height:26px;">
                <div id="exportProgressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                     role="progressbar" style="width:5%">Preparing...</div>
            </div>
            <div id="exportProgressText" class="mt-2 text-muted">Mohon tunggu, sedang memproses XML dan mengirim ke Tally Server...</div>
        </div>
    </div>

    <form method="post" id="frmBom">
        <input type="hidden" name="action" id="action" value="">
        <input type="hidden" name="server_id_delete" id="server_id_delete" value="">

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
                        <button type="button" class="btn btn-outline-danger" onclick="deleteSelectedServer()">Del Server</button>
                        <button type="button" class="btn btn-secondary" onclick="setAction('test')">Test Conn</button>
                    </div>

                    <div class="col-md-12 d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-success" onclick="setAction('preview')">Preview Data</button>
                        <button type="button" class="btn btn-primary" onclick="confirmSendSql()">Send to SQL Server</button>
                        <button type="button" class="btn btn-warning" onclick="setAction('preview_xml')">Preview XML</button>
                        <button type="button" class="btn btn-danger" onclick="confirmExport()">Send BOM to Tally</button>
                        <a href="cek_bom_kosong_tally.php" class="btn btn-dark">Cek BOM Kosong</a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <?php if ($xmlPreview !== '') { ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold bg-secondary text-white">XML Preview (Yang Akan Dikirim)</div>
            <div class="card-body">
                <pre style="background:#f8f9fa; padding:15px; border:1px solid #ccc; max-height:400px; overflow:auto; font-size:12px;"><?php echo h($xmlPreview); ?></pre>
            </div>
        </div>
    <?php } ?>

    <?php if ($summary !== null) { ?>
        <!-- AKTIFKAN PROGRESS BAR JIKA SUDAH SELESAI DI-PROSES -->
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var box = document.getElementById('exportProgressBox');
            var bar = document.getElementById('exportProgressBar');
            var txt = document.getElementById('exportProgressText');

            if (box && bar) {
                box.style.display = 'block';
                bar.className = 'progress-bar';
                bar.style.width = '100%';
                bar.innerHTML = '100% Selesai';
                if (txt) txt.innerHTML = 'Import selesai. Status: <?php echo h($summary['status']); ?>.';
            }
        });
        </script>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Summary Import BOM</div>
            <div class="card-body">
                <table class="table table-bordered table-sm w-auto">
                    <tr><th>Total Row BOM</th><td><?php echo h($summary['total_items']); ?></td></tr>
                    <tr><th>Status</th><td><span class="badge bg-<?php echo $summary['status'] == 'Sukses' ? 'success' : 'danger'; ?>"><?php echo h($summary['status']); ?></span></td></tr>
                    <tr><th>Debug File</th><td><?php echo h(str_replace(__DIR__, '', $summary['debug_file'])); ?></td></tr>
                </table>

                <?php foreach ($exportDetails as $d) { ?>
                    <?php if (!$d['ok']) { ?>
                        <div class="alert alert-danger mt-3">
                            <strong>Error dari Tally:</strong><br>
                            <pre style="white-space:pre-wrap;"><?php echo h($d['error_msg']); ?></pre>
                            <strong>Full Response:</strong>
                            <pre style="white-space:pre-wrap; max-height:300px; overflow:auto;"><?php echo h($d['response']); ?></pre>
                        </div>
                    <?php } else { ?>
                        <div class="alert alert-success mt-3">
                            <strong>Berhasil dikirim ke Tally.</strong>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    <?php } ?>

    <div class="card shadow-sm">
        <div class="card-header fw-bold">Preview Data BOM</div>
        <div class="card-body">
            <?php if (count($resultRows) == 0) { ?>
                <div class="alert alert-secondary">Data tidak ada.</div>
            <?php } else { ?>
                <div class="table-responsive" style="max-height:500px;">
                    <table class="table table-bordered table-striped table-sm">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>NO</th>
                                <th>PART NAME (B)</th>
                                <th>BASEUNIT (G)</th>
                                <th>COMP LIST (H)</th>
                                <th>COMP QTY (I)</th>
                                <th>MAT NAME (J)</th>
                                <th>ACTUALQTY (K)</th>
                                <th>UNIT MAT (L)</th>
                                <th>NATURE (M)</th>
                                <th>GODOWN (N)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Konversi key ke huruf kecil semua agar aman dari case-sensitive SQLSRV
                            foreach ($resultRows as $r) { 
                                $rl = array_change_key_case($r, CASE_LOWER);
                            ?>
                                <tr>
                                    <td class="text-center"><?php echo h(isset($rl['a']) ? $rl['a'] : ''); ?></td>
                                    <td><b><?php echo h(isset($rl['b']) ? $rl['b'] : ''); ?></b></td>
                                    <td><?php echo h(isset($rl['g']) ? $rl['g'] : ''); ?></td>
                                    <td><?php echo h(isset($rl['h']) ? $rl['h'] : ''); ?></td>
                                    <td class="text-end"><?php echo h(isset($rl['i']) ? $rl['i'] : ''); ?></td>
                                    <td><?php echo h(isset($rl['j']) ? $rl['j'] : ''); ?></td>
                                    <td class="text-end"><?php echo h(isset($rl['k']) ? $rl['k'] : ''); ?></td>
                                    <td><?php echo h(isset($rl['l']) ? $rl['l'] : ''); ?></td>
                                    <td><?php echo h(isset($rl['m']) ? $rl['m'] : ''); ?></td>
                                    <td><?php echo h(isset($rl['n']) ? $rl['n'] : ''); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div> <!-- Penutup card-body -->
    </div> <!-- Penutup card shadow-sm -->

</div> <!-- Penutup container-fluid -->

<script>
function setAction(a) {
    document.getElementById('action').value = a;
    document.getElementById('frmBom').submit();
}

function confirmSendSql() {
    if (confirm('Kirim hasil preview ke tabel [msData].[dbo].[Tally_BOM]? Data lama akan diganti.')) {
        setAction('send_sql');
    }
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
    if (!combo || combo.value == '') { alert('Pilih server Tally dulu.'); return; }
    var p = combo.value.split('|');
    document.getElementById('server_id_delete').value = p[2] || '';
    if (confirm('Hapus server Tally ini?')) { setAction('delete_server'); }
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

    if (txt) txt.innerHTML = 'Mohon tunggu, sedang memproses XML dan mengirim ke Tally Server...';

    // Animasi progress bar berjalan sampai 90%
    window._progressTimer = setInterval(function () {
        if (pct < 90) {
            pct += 5;
            if (bar) {
                bar.style.width = pct + '%';
                bar.innerHTML = pct + '%';
            }
            if (txt) {
                txt.innerHTML = 'Sedang mengirim data BOM ke Tally... Mohon jangan tutup browser (' + pct + '%)';
            }
        }
    }, 800); // Update setiap 0.8 detik
}

function confirmExport() {
    if (confirm('Kirim seluruh data BOM ke Tally sekarang? (Pastikan tabel Tally_BOM sudah terisi dari halaman sebelumnya)')) {
        showExportProgress();
        // Beri jeda 200ms agar UI sempat render progress bar sebelum form submit
        setTimeout(function () {
            setAction('export');
        }, 200);
    }
}
</script>

</body>
</html>