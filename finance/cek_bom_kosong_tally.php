<?php
// C:\xampp\htdocs\msii\finance\cek_bom_kosong_tally.php
// PHP 5.4 compatible
// Cek part dari SQL Server yang Stock Item-nya belum memiliki komponen BOM di Tally.

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
        alert('Menu Cek BOM Tally hanya untuk Plant P1.');
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
    if (!$stmt) return $rows;

    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }
    return $rows;
}

function jsonResponse($data) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    echo json_encode($data);
    exit;
}

/* =========================
   TALLY SERVER SETTINGS
   ========================= */
function ensureTallyServerTable() {
    qx("IF OBJECT_ID('dbo.Tally_Server_Master', 'U') IS NULL
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
        END", array());
}

function seedTallyServers() {
    ensureTallyServerTable();

    $stmt = qx("SELECT COUNT(*) AS JML FROM dbo.Tally_Server_Master", array());
    $r = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : false;

    if ($r && (int)$r['JML'] == 0) {
        qx("INSERT INTO dbo.Tally_Server_Master
            (ServerName, TallyIP, TallyPort, IsDefault)
            VALUES
            ('Localhost', '127.0.0.1', '9002', 0),
            ('dianero99', 'dianero99', '9002', 1)", array());
    }
}

function loadTallyServers() {
    seedTallyServers();
    $stmt = qx("SELECT ID, ServerName, TallyIP, TallyPort, IsDefault
                FROM dbo.Tally_Server_Master
                ORDER BY IsDefault DESC, ServerName", array());
    return fetchAllRows($stmt);
}

function testTallyConnection($ip, $port) {
    $fp = @fsockopen($ip, $port, $errno, $errstr, 3);
    if ($fp) {
        fclose($fp);
        return array('ok' => true, 'message' => 'OK: Tally port terbuka di ' . $ip . ':' . $port);
    }

    return array(
        'ok' => false,
        'message' => 'Tidak bisa konek ke ' . $ip . ':' . $port . ' - ' . $errstr
    );
}

/* =========================
   TABEL HASIL CHECK
   ========================= */
function ensureBomCheckTable() {
    qx("IF OBJECT_ID('[msData].[dbo].[Tally_BOM_Check_Result]', 'U') IS NULL
        BEGIN
            CREATE TABLE [msData].[dbo].[Tally_BOM_Check_Result]
            (
                ID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                PartNo VARCHAR(50) NULL,
                PartName NVARCHAR(500) NOT NULL,
                CheckStatus VARCHAR(30) NOT NULL DEFAULT 'PENDING',
                BomCount INT NOT NULL DEFAULT 0,
                ComponentCount INT NOT NULL DEFAULT 0,
                ErrorMessage NVARCHAR(2000) NULL,
                CheckedAt DATETIME NULL
            );

            CREATE INDEX IX_Tally_BOM_Check_Status
            ON [msData].[dbo].[Tally_BOM_Check_Result] (CheckStatus, ID);
        END", array());
}

function seedBomCheckRows() {
    ensureBomCheckTable();

    qx("DELETE FROM [msData].[dbo].[Tally_BOM_Check_Result]", array());

    qx("INSERT INTO [msData].[dbo].[Tally_BOM_Check_Result]
        (PartNo, PartName, CheckStatus, BomCount, ComponentCount)
        SELECT
            MAX(
                CASE
                    WHEN LTRIM(RTRIM(ISNULL([Col_E], ''))) <> ''
                        THEN LTRIM(RTRIM([Col_E]))
                    ELSE LEFT(LTRIM(RTRIM([Col_B])), 8)
                END
            ) AS PartNo,
            LTRIM(RTRIM([Col_B])) AS PartName,
            'PENDING' AS CheckStatus,
            0 AS BomCount,
            0 AS ComponentCount
        FROM [msData].[dbo].[Tally_BOM]
        WHERE LTRIM(RTRIM(ISNULL([Col_B], ''))) <> ''
        GROUP BY LTRIM(RTRIM([Col_B]))", array());
}

function getCheckSummary() {
    ensureBomCheckTable();

    $stmt = qx("SELECT
                    COUNT(*) AS Total,
                    SUM(CASE WHEN CheckStatus = 'PENDING' THEN 1 ELSE 0 END) AS Pending,
                    SUM(CASE WHEN CheckStatus = 'ADA_BOM' THEN 1 ELSE 0 END) AS AdaBom,
                    SUM(CASE WHEN CheckStatus = 'BOM_KOSONG' THEN 1 ELSE 0 END) AS BomKosong,
                    SUM(CASE WHEN CheckStatus = 'PART_TIDAK_ADA' THEN 1 ELSE 0 END) AS PartTidakAda,
                    SUM(CASE WHEN CheckStatus = 'ERROR' THEN 1 ELSE 0 END) AS ErrorCount
                FROM [msData].[dbo].[Tally_BOM_Check_Result]", array());

    $r = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : false;

    if (!$r) {
        return array(
            'total' => 0,
            'pending' => 0,
            'ada_bom' => 0,
            'bom_kosong' => 0,
            'part_tidak_ada' => 0,
            'error' => 0,
            'checked' => 0,
            'percent' => 0
        );
    }

    $total = (int)$r['Total'];
    $pending = (int)$r['Pending'];
    $checked = $total - $pending;
    $percent = $total > 0 ? round(($checked / $total) * 100, 1) : 0;

    return array(
        'total' => $total,
        'pending' => $pending,
        'ada_bom' => (int)$r['AdaBom'],
        'bom_kosong' => (int)$r['BomKosong'],
        'part_tidak_ada' => (int)$r['PartTidakAda'],
        'error' => (int)$r['ErrorCount'],
        'checked' => $checked,
        'percent' => $percent
    );
}

/* =========================
   REQUEST / RESPONSE TALLY
   ========================= */
function buildStockItemCheckXml($partName, $company) {
    $companyXml = '';
    if (trim((string)$company) !== '') {
        $companyXml = '<SVCURRENTCOMPANY>' . x($company) . '</SVCURRENTCOMPANY>';
    }

    /*
     * PERBAIKAN: Tally memerlukan tag FETCH tanpa bintang 
     * untuk men-trigger pencetakan list komponen
     */
    return '<?xml version="1.0" encoding="UTF-8"?>
<ENVELOPE>
  <HEADER>
    <VERSION>1</VERSION>
    <TALLYREQUEST>EXPORT</TALLYREQUEST>
    <TYPE>OBJECT</TYPE>
    <SUBTYPE>Stock Item</SUBTYPE>
    <ID TYPE="Name">' . x($partName) . '</ID>
  </HEADER>
  <BODY>
    <DESC>
      <STATICVARIABLES>
        <SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>
        ' . $companyXml . '
      </STATICVARIABLES>
      <FETCHLIST>
        <FETCH>Name</FETCH>
        <FETCH>BaseUnits</FETCH>
        <FETCH>ComponentList</FETCH>
        <FETCH>MultiComponentList</FETCH>
        <FETCH>ComponentList.*</FETCH>
        <FETCH>MultiComponentList.*</FETCH>
        <FETCH>MultiComponentList.*.*</FETCH>
        <FETCH>*.*</FETCH>
      </FETCHLIST>
    </DESC>
  </BODY>
</ENVELOPE>';
}

function sendToTally($xml, $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml; charset=utf-8'));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $res = curl_exec($ch);
    if ($res === false) {
        $res = 'CURL ERROR: ' . curl_error($ch);
    }

    curl_close($ch);
    return (string)$res;
}

function extractTallyError($response) {
    $response = (string)$response;

    if (stripos($response, 'CURL ERROR:') !== false) {
        return trim($response);
    }

    if (preg_match_all('/<LINEERROR(?:\s[^>]*)?>(.*?)<\/LINEERROR>/is', $response, $m)) {
        $messages = array();
        foreach ($m[1] as $msg) {
            $messages[] = trim(strip_tags(html_entity_decode($msg, ENT_QUOTES, 'UTF-8')));
        }
        return implode(' | ', $messages);
    }

    if (preg_match('/<STATUS(?:\s[^>]*)?>\s*0\s*<\/STATUS>/i', $response)) {
        return 'Tally mengembalikan STATUS 0.';
    }

    return '';
}

function countXmlOpeningTag($response, $tagName) {
    $tagName = preg_quote($tagName, '/');
    preg_match_all('/<' . $tagName . '(?:\s[^>]*)?>/i', (string)$response, $m);
    return isset($m[0]) ? count($m[0]) : 0;
}

function countAnyXmlOpeningTag($response, $tagNames) {
    $max = 0;
    foreach ($tagNames as $tagName) {
        $count = countXmlOpeningTag($response, $tagName);
        if ($count > $max) $max = $count;
    }
    return $max;
}

function inspectBomResponse($response) {
    $response = (string)$response;
    $error = extractTallyError($response);

    if (stripos($response, 'CURL ERROR:') !== false) {
        return array(
            'status' => 'ERROR',
            'bom_count' => 0,
            'component_count' => 0,
            'error' => $error
        );
    }

    $stockFound = preg_match('/<STOCKITEM(?:\s|>)/i', $response) ? true : false;

    if (!$stockFound) {
        return array(
            'status' => 'PART_TIDAK_ADA',
            'bom_count' => 0,
            'component_count' => 0,
            'error' => $error !== '' ? $error : 'Stock Item tidak ditemukan di Tally.'
        );
    }

    /*
     * PERBAIKAN: Membaca tag dengan format LIST maupun tag normal
     */
    $bomCount = countAnyXmlOpeningTag($response, array(
        'MULTICOMPONENTLIST',
        'COMPONENTLIST',
        'BOMLIST',
        'MULTICOMPONENTLIST.LIST',
        'COMPONENTLIST.LIST',
        'BOMLIST.LIST'
    ));

    $componentCount = countAnyXmlOpeningTag($response, array(
        'MULTICOMPONENTITEMLIST',
        'COMPONENTITEMLIST',
        'BOMCOMPONENTLIST',
        'MULTICOMPONENTITEMLIST.LIST',
        'COMPONENTITEMLIST.LIST',
        'BOMCOMPONENTLIST.LIST'
    ));

    // STOCKITEMNAME berada di dalam baris komponen BOM pada response master.
    $stockItemNameCount = countXmlOpeningTag($response, 'STOCKITEMNAME');
    if ($stockItemNameCount > $componentCount) {
        $componentCount = $stockItemNameCount;
    }

    // Sebagian versi menampilkan nama BOM melalui method tanpa wrapper list.
    $bomNameCount = countAnyXmlOpeningTag($response, array(
        'COMPONENTLISTNAME',
        'BOMNAME'
    ));
    if ($bomNameCount > $bomCount) {
        $bomCount = $bomNameCount;
    }

    if ($componentCount > 0) {
        return array(
            'status' => 'ADA_BOM',
            'bom_count' => $bomCount > 0 ? $bomCount : 1,
            'component_count' => $componentCount,
            'error' => ''
        );
    }

    return array(
        'status' => 'BOM_KOSONG',
        'bom_count' => $bomCount,
        'component_count' => 0,
        'error' => ''
    );
}

function checkOnePart($partName, $partNo, $url, $company) {
    $xml = buildStockItemCheckXml($partName, $company);
    $response = sendToTally($xml, $url);
    $result = inspectBomResponse($response);

    // Fallback: bila nama lengkap tidak ditemukan, coba kode Part No sebagai identifier.
    if ($result['status'] == 'PART_TIDAK_ADA' && trim((string)$partNo) !== '' && trim((string)$partNo) !== trim((string)$partName)) {
        $xml2 = buildStockItemCheckXml($partNo, $company);
        $response2 = sendToTally($xml2, $url);
        $result2 = inspectBomResponse($response2);

        if ($result2['status'] != 'PART_TIDAK_ADA') {
            return $result2;
        }
    }

    return $result;
}

function updateCheckRow($id, $result) {
    qx("UPDATE [msData].[dbo].[Tally_BOM_Check_Result]
        SET CheckStatus = ?,
            BomCount = ?,
            ComponentCount = ?,
            ErrorMessage = ?,
            CheckedAt = GETDATE()
        WHERE ID = ?", array(
            $result['status'],
            (int)$result['bom_count'],
            (int)$result['component_count'],
            $result['error'],
            (int)$id
        ));
}

/* =========================
   SERVER & INPUT DEFAULT
   ========================= */
$tally_servers = loadTallyServers();
$defaultSrv = count($tally_servers) > 0 ? $tally_servers[0] : array();

$tally_ip = isset($defaultSrv['TallyIP']) ? trim((string)$defaultSrv['TallyIP']) : '127.0.0.1';
$tally_port = isset($defaultSrv['TallyPort']) ? trim((string)$defaultSrv['TallyPort']) : '9002';
$tally_company = isset($_SESSION['tally_bom_check_company']) ? trim((string)$_SESSION['tally_bom_check_company']) : '';

if (isset($_POST['tally_ip']) && trim((string)$_POST['tally_ip']) !== '') {
    $tally_ip = trim((string)$_POST['tally_ip']);
}
if (isset($_POST['tally_port']) && trim((string)$_POST['tally_port']) !== '') {
    $tally_port = trim((string)$_POST['tally_port']);
}
if (isset($_POST['tally_company'])) {
    $tally_company = trim((string)$_POST['tally_company']);
    $_SESSION['tally_bom_check_company'] = $tally_company;
}

/* =========================
   AJAX HANDLER
   ========================= */
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $ajaxAction = isset($_POST['action']) ? trim((string)$_POST['action']) : '';

    if ($ajaxAction == 'test') {
        $test = testTallyConnection($tally_ip, $tally_port);
        jsonResponse($test);
    }

    if ($ajaxAction == 'start') {
        $test = testTallyConnection($tally_ip, $tally_port);
        if (!$test['ok']) {
            jsonResponse(array('ok' => false, 'message' => $test['message']));
        }

        seedBomCheckRows();
        $summary = getCheckSummary();

        if ($summary['total'] == 0) {
            jsonResponse(array(
                'ok' => false,
                'message' => 'Tabel [msData].[dbo].[Tally_BOM] kosong atau Col_B tidak berisi data.',
                'summary' => $summary
            ));
        }

        jsonResponse(array(
            'ok' => true,
            'message' => 'Daftar part berhasil disiapkan. Proses pengecekan dimulai.',
            'summary' => $summary
        ));
    }

    if ($ajaxAction == 'batch') {
        ensureBomCheckTable();

        $batchSize = isset($_POST['batch_size']) ? (int)$_POST['batch_size'] : 8;
        if ($batchSize < 1) $batchSize = 1;
        if ($batchSize > 20) $batchSize = 20;

        $stmt = qx("SELECT TOP (" . $batchSize . ")
                        ID, PartNo, PartName
                    FROM [msData].[dbo].[Tally_BOM_Check_Result]
                    WHERE CheckStatus = 'PENDING'
                    ORDER BY ID", array());
        $rows = fetchAllRows($stmt);

        $url = 'http://' . $tally_ip . ':' . $tally_port;
        $processed = 0;

        foreach ($rows as $row) {
            $result = checkOnePart(
                isset($row['PartName']) ? $row['PartName'] : '',
                isset($row['PartNo']) ? $row['PartNo'] : '',
                $url,
                $tally_company
            );

            updateCheckRow($row['ID'], $result);
            $processed++;
        }

        $summary = getCheckSummary();

        jsonResponse(array(
            'ok' => true,
            'processed' => $processed,
            'done' => $summary['pending'] == 0,
            'summary' => $summary
        ));
    }

    jsonResponse(array('ok' => false, 'message' => 'Action AJAX tidak dikenal.'));
}

/* =========================
   DOWNLOAD CSV
   ========================= */
if (isset($_GET['download']) && $_GET['download'] == 'csv') {
    ensureBomCheckTable();

    $stmt = qx("SELECT PartNo, PartName, CheckStatus, BomCount, ComponentCount,
                        ErrorMessage, CheckedAt
                FROM [msData].[dbo].[Tally_BOM_Check_Result]
                WHERE CheckStatus IN ('BOM_KOSONG', 'PART_TIDAK_ADA', 'ERROR')
                ORDER BY
                    CASE CheckStatus
                        WHEN 'BOM_KOSONG' THEN 1
                        WHEN 'PART_TIDAK_ADA' THEN 2
                        ELSE 3
                    END,
                    PartNo, PartName", array());
    $rows = fetchAllRows($stmt);

    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="cek_bom_kosong_tally_' . date('Ymd_His') . '.csv"');
    }

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array('Part No', 'Part Name', 'Status', 'Jumlah BOM', 'Jumlah Komponen', 'Error', 'Checked At'));

    foreach ($rows as $row) {
        $checkedAt = isset($row['CheckedAt']) && $row['CheckedAt'] instanceof DateTime
            ? $row['CheckedAt']->format('Y-m-d H:i:s')
            : (isset($row['CheckedAt']) ? (string)$row['CheckedAt'] : '');

        fputcsv($out, array(
            isset($row['PartNo']) ? $row['PartNo'] : '',
            isset($row['PartName']) ? $row['PartName'] : '',
            isset($row['CheckStatus']) ? $row['CheckStatus'] : '',
            isset($row['BomCount']) ? $row['BomCount'] : 0,
            isset($row['ComponentCount']) ? $row['ComponentCount'] : 0,
            isset($row['ErrorMessage']) ? $row['ErrorMessage'] : '',
            $checkedAt
        ));
    }

    fclose($out);
    exit;
}

/* =========================
   NORMAL PAGE DATA
   ========================= */
ensureBomCheckTable();
$summary = getCheckSummary();

$statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : 'MASALAH';
$allowedFilters = array('MASALAH', 'BOM_KOSONG', 'PART_TIDAK_ADA', 'ERROR', 'ADA_BOM', 'PENDING', 'SEMUA');
if (!in_array($statusFilter, $allowedFilters)) $statusFilter = 'MASALAH';

$whereSql = "WHERE CheckStatus IN ('BOM_KOSONG', 'PART_TIDAK_ADA', 'ERROR')";
if ($statusFilter == 'BOM_KOSONG') $whereSql = "WHERE CheckStatus = 'BOM_KOSONG'";
if ($statusFilter == 'PART_TIDAK_ADA') $whereSql = "WHERE CheckStatus = 'PART_TIDAK_ADA'";
if ($statusFilter == 'ERROR') $whereSql = "WHERE CheckStatus = 'ERROR'";
if ($statusFilter == 'ADA_BOM') $whereSql = "WHERE CheckStatus = 'ADA_BOM'";
if ($statusFilter == 'PENDING') $whereSql = "WHERE CheckStatus = 'PENDING'";
if ($statusFilter == 'SEMUA') $whereSql = '';

$resultStmt = qx("SELECT TOP (5000)
                    ID, PartNo, PartName, CheckStatus, BomCount,
                    ComponentCount, ErrorMessage, CheckedAt
                  FROM [msData].[dbo].[Tally_BOM_Check_Result]
                  " . $whereSql . "
                  ORDER BY
                    CASE CheckStatus
                        WHEN 'BOM_KOSONG' THEN 1
                        WHEN 'PART_TIDAK_ADA' THEN 2
                        WHEN 'ERROR' THEN 3
                        WHEN 'PENDING' THEN 4
                        ELSE 5
                    END,
                    PartNo, PartName", array());
$resultRows = fetchAllRows($resultStmt);

?>
<?php include 'layout.php'; ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0">CEK PART YANG BOM-NYA KOSONG DI TALLY</h3>
        <a href="import_bom_tally.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="alert alert-info">
        Halaman ini mengambil daftar part dari <code>[msData].[dbo].[Tally_BOM]</code>,
        mengecek Stock Item satu per satu ke Tally, lalu menampilkan part dengan
        <b>BOM kosong</b>, <b>Stock Item tidak ditemukan</b>, atau <b>error koneksi</b>.
        Pengecekan memakai recursive fetch agar daftar komponen BOM ikut terbaca dari Tally.
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-bold">Settings dan Proses Check</div>
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

                <div class="col-md-2">
                    <label class="form-label fw-bold">Tally IP</label>
                    <input type="text" id="tally_ip" class="form-control" value="<?php echo h($tally_ip); ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold">Port</label>
                    <input type="text" id="tally_port" class="form-control" value="<?php echo h($tally_port); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Company Tally</label>
                    <input type="text" id="tally_company" class="form-control"
                           value="<?php echo h($tally_company); ?>"
                           placeholder="Kosongkan untuk company yang sedang aktif">
                </div>

                <div class="col-md-12 d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-secondary" onclick="testConnection()">
                        Test Connection
                    </button>
                    <button type="button" class="btn btn-danger" id="btnStart" onclick="startCheck()">
                        Mulai Cek BOM Tally
                    </button>
                    <a href="?download=csv" class="btn btn-success">
                        Export Masalah ke CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div id="messageBox" class="alert" style="display:none;"></div>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-bold">Progress</div>
        <div class="card-body">
            <div class="progress" style="height:28px;">
                <div id="progressBar" class="progress-bar progress-bar-striped"
                     role="progressbar" style="width:<?php echo h($summary['percent']); ?>%;">
                    <?php echo h($summary['percent']); ?>%
                </div>
            </div>
            <div id="progressText" class="mt-2 text-muted">
                Checked <?php echo h($summary['checked']); ?> dari <?php echo h($summary['total']); ?> part.
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-2 col-sm-6">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted">Total Part</div>
                <div id="sumTotal" class="fs-3 fw-bold"><?php echo h($summary['total']); ?></div>
            </div></div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted">Pending</div>
                <div id="sumPending" class="fs-3 fw-bold"><?php echo h($summary['pending']); ?></div>
            </div></div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted">Ada BOM</div>
                <div id="sumAdaBom" class="fs-3 fw-bold text-success"><?php echo h($summary['ada_bom']); ?></div>
            </div></div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted">BOM Kosong</div>
                <div id="sumBomKosong" class="fs-3 fw-bold text-danger"><?php echo h($summary['bom_kosong']); ?></div>
            </div></div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted">Part Tidak Ada</div>
                <div id="sumPartTidakAda" class="fs-3 fw-bold text-warning"><?php echo h($summary['part_tidak_ada']); ?></div>
            </div></div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted">Error</div>
                <div id="sumError" class="fs-3 fw-bold text-danger"><?php echo h($summary['error']); ?></div>
            </div></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-bold">Hasil Check</span>
            <div class="d-flex gap-2 flex-wrap">
                <select class="form-select form-select-sm" style="width:auto;" onchange="changeStatusFilter(this.value)">
                    <option value="MASALAH" <?php echo $statusFilter == 'MASALAH' ? 'selected' : ''; ?>>Semua Masalah</option>
                    <option value="BOM_KOSONG" <?php echo $statusFilter == 'BOM_KOSONG' ? 'selected' : ''; ?>>BOM Kosong</option>
                    <option value="PART_TIDAK_ADA" <?php echo $statusFilter == 'PART_TIDAK_ADA' ? 'selected' : ''; ?>>Part Tidak Ada</option>
                    <option value="ERROR" <?php echo $statusFilter == 'ERROR' ? 'selected' : ''; ?>>Error</option>
                    <option value="ADA_BOM" <?php echo $statusFilter == 'ADA_BOM' ? 'selected' : ''; ?>>Ada BOM</option>
                    <option value="PENDING" <?php echo $statusFilter == 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                    <option value="SEMUA" <?php echo $statusFilter == 'SEMUA' ? 'selected' : ''; ?>>Semua Data</option>
                </select>
                <input type="text" id="tableSearch" class="form-control form-control-sm"
                       style="width:240px;" placeholder="Cari part no / part name..."
                       onkeyup="filterTable()">
            </div>
        </div>

        <div class="card-body">
            <?php if (count($resultRows) == 0) { ?>
                <div class="alert alert-secondary mb-0">
                    Belum ada hasil untuk filter ini. Klik <b>Mulai Cek BOM Tally</b>.
                </div>
            <?php } else { ?>
                <div class="table-responsive" style="max-height:650px;">
                    <table class="table table-bordered table-striped table-sm" id="resultTable">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th style="width:55px;">No</th>
                                <th>Part No</th>
                                <th>Part Name</th>
                                <th>Status</th>
                                <th class="text-end">BOM</th>
                                <th class="text-end">Komponen</th>
                                <th>Error / Keterangan</th>
                                <th>Checked At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($resultRows as $row) { ?>
                                <?php
                                $status = isset($row['CheckStatus']) ? $row['CheckStatus'] : '';
                                $badge = 'secondary';
                                if ($status == 'ADA_BOM') $badge = 'success';
                                if ($status == 'BOM_KOSONG') $badge = 'danger';
                                if ($status == 'PART_TIDAK_ADA') $badge = 'warning';
                                if ($status == 'ERROR') $badge = 'dark';
                                if ($status == 'PENDING') $badge = 'secondary';

                                $checkedAt = '';
                                if (isset($row['CheckedAt']) && $row['CheckedAt'] instanceof DateTime) {
                                    $checkedAt = $row['CheckedAt']->format('Y-m-d H:i:s');
                                } elseif (isset($row['CheckedAt'])) {
                                    $checkedAt = (string)$row['CheckedAt'];
                                }
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td><b><?php echo h(isset($row['PartNo']) ? $row['PartNo'] : ''); ?></b></td>
                                    <td><?php echo h(isset($row['PartName']) ? $row['PartName'] : ''); ?></td>
                                    <td><span class="badge bg-<?php echo h($badge); ?>"><?php echo h($status); ?></span></td>
                                    <td class="text-end"><?php echo h(isset($row['BomCount']) ? $row['BomCount'] : 0); ?></td>
                                    <td class="text-end"><?php echo h(isset($row['ComponentCount']) ? $row['ComponentCount'] : 0); ?></td>
                                    <td><?php echo h(isset($row['ErrorMessage']) ? $row['ErrorMessage'] : ''); ?></td>
                                    <td><?php echo h($checkedAt); ?></td>
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
var checkRunning = false;

function getFormData(action) {
    return 'action=' + encodeURIComponent(action) +
           '&tally_ip=' + encodeURIComponent(document.getElementById('tally_ip').value) +
           '&tally_port=' + encodeURIComponent(document.getElementById('tally_port').value) +
           '&tally_company=' + encodeURIComponent(document.getElementById('tally_company').value) +
           '&batch_size=8';
}

function ajaxPost(action, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.pathname + '?ajax=1', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

    xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;

        if (xhr.status !== 200) {
            callback({ok:false, message:'HTTP Error ' + xhr.status});
            return;
        }

        try {
            callback(JSON.parse(xhr.responseText));
        } catch (e) {
            callback({ok:false, message:'Response bukan JSON: ' + xhr.responseText.substring(0, 500)});
        }
    };

    xhr.send(getFormData(action));
}

function showMessage(message, ok) {
    var box = document.getElementById('messageBox');
    box.style.display = 'block';
    box.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
    box.innerHTML = message;
}

function updateSummary(s) {
    document.getElementById('sumTotal').innerHTML = s.total;
    document.getElementById('sumPending').innerHTML = s.pending;
    document.getElementById('sumAdaBom').innerHTML = s.ada_bom;
    document.getElementById('sumBomKosong').innerHTML = s.bom_kosong;
    document.getElementById('sumPartTidakAda').innerHTML = s.part_tidak_ada;
    document.getElementById('sumError').innerHTML = s.error;

    var bar = document.getElementById('progressBar');
    bar.style.width = s.percent + '%';
    bar.innerHTML = s.percent + '%';
    bar.className = 'progress-bar progress-bar-striped' + (s.pending > 0 ? ' progress-bar-animated' : '');

    document.getElementById('progressText').innerHTML =
        'Checked ' + s.checked + ' dari ' + s.total + ' part. Pending: ' + s.pending + '.';
}

function pilihServerTally() {
    var combo = document.getElementById('server_combo');
    if (!combo || combo.value === '') return;

    var p = combo.value.split('|');
    document.getElementById('tally_ip').value = p[0] || '';
    document.getElementById('tally_port').value = p[1] || '';
}

function testConnection() {
    showMessage('Sedang test koneksi...', true);
    ajaxPost('test', function (r) {
        showMessage(r.message || 'Selesai.', !!r.ok);
    });
}

function startCheck() {
    if (checkRunning) return;

    if (!confirm('Mulai cek seluruh part dari tabel Tally_BOM? Hasil check sebelumnya akan diganti.')) {
        return;
    }

    checkRunning = true;
    document.getElementById('btnStart').disabled = true;
    showMessage('Menyiapkan daftar part...', true);

    ajaxPost('start', function (r) {
        if (!r.ok) {
            checkRunning = false;
            document.getElementById('btnStart').disabled = false;
            showMessage(r.message || 'Gagal memulai check.', false);
            return;
        }

        updateSummary(r.summary);
        showMessage(r.message, true);
        processNextBatch();
    });
}

function processNextBatch() {
    if (!checkRunning) return;

    ajaxPost('batch', function (r) {
        if (!r.ok) {
            checkRunning = false;
            document.getElementById('btnStart').disabled = false;
            showMessage(r.message || 'Gagal memproses batch.', false);
            return;
        }

        updateSummary(r.summary);

        if (r.done) {
            checkRunning = false;
            document.getElementById('btnStart').disabled = false;
            showMessage('Check BOM selesai. Halaman akan dimuat ulang untuk menampilkan hasil.', true);
            setTimeout(function () {
                window.location.href = window.location.pathname + '?status=MASALAH';
            }, 1000);
            return;
        }

        setTimeout(processNextBatch, 150);
    });
}

function changeStatusFilter(status) {
    window.location.href = window.location.pathname + '?status=' + encodeURIComponent(status);
}

function filterTable() {
    var input = document.getElementById('tableSearch');
    var filter = input.value.toUpperCase();
    var table = document.getElementById('resultTable');
    if (!table) return;

    var rows = table.getElementsByTagName('tr');
    for (var i = 1; i < rows.length; i++) {
        var text = rows[i].textContent || rows[i].innerText;
        rows[i].style.display = text.toUpperCase().indexOf(filter) > -1 ? '' : 'none';
    }
}
</script>

</body>
</html>