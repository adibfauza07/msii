<?php
/*
 * delete_empty_itemcode.php (FINAL - TALLY NATIVE VALIDATION)
 * Menampilkan dan mencoba MENGHAPUS Stock Item (Part No kosong).
 * Memanfaatkan sistem keamanan bawaan Tally untuk menolak item yang memiliki transaksi.
 */

@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');
@set_time_limit(0);

function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function xmlValue($value) { return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8'); }

function normalizeSpace($value) {
    $value = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
    $value = str_replace("\xC2\xA0", ' ', $value);
    return preg_replace('/\s+/u', ' ', trim($value));
}

function isValidXml10CodePoint($codePoint) {
    $codePoint = (int)$codePoint;
    return $codePoint === 0x09 || $codePoint === 0x0A || $codePoint === 0x0D ||
           ($codePoint >= 0x20 && $codePoint <= 0xD7FF) ||
           ($codePoint >= 0xE000 && $codePoint <= 0xFFFD) ||
           ($codePoint >= 0x10000 && $codePoint <= 0x10FFFF);
}

function removeInvalidXmlCharacterReferences($value) {
    return preg_replace_callback('/&#(?:(?:x|X)([0-9A-Fa-f]+)|([0-9]+));/', function ($matches) {
        $codePoint = isset($matches[1]) && $matches[1] !== '' ? hexdec($matches[1]) : (isset($matches[2]) ? (int)$matches[2] : 0);
        return isValidXml10CodePoint($codePoint) ? $matches[0] : ' ';
    }, (string)$value);
}

function stripInvalidXml10Characters($value) {
    $value = removeInvalidXmlCharacterReferences((string)$value);
    $clean = @preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);
    return (string)($clean === null ? preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) : $clean);
}

function convertTallyResponseToUtf8($value) {
    $value = preg_replace('/^\xEF\xBB\xBF/', '', (string)$value); 
    if (@preg_match('//u', $value) !== 1) {
        $converted = false;
        if (function_exists('iconv')) $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        if (!$converted && function_exists('mb_convert_encoding')) $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        if (!$converted && function_exists('utf8_encode')) $converted = @utf8_encode($value);
        if ($converted) $value = $converted;
    }
    return trim(stripInvalidXml10Characters(str_replace("\xC2\xA0", ' ', $value)));
}

function sendToTally($xml, $url) {
    if (!function_exists('curl_init')) return 'CURL ERROR: cURL belum aktif.';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml; charset=UTF-8', 'Connection: Keep-Alive'));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    if ($response === false) $response = 'CURL ERROR: ' . curl_error($ch);
    curl_close($ch);
    return (string)$response;
}

// 1. XML UNTUK MENGAMBIL DATA
function buildStockItemListRequest($companyName, $username, $password) {
    $authXml = '';
    if (trim((string)$companyName) !== '') $authXml .= "\n        <SVCURRENTCOMPANY>" . xmlValue($companyName) . "</SVCURRENTCOMPANY>";
    if (trim((string)$username) !== '') $authXml .= "\n        <SVUSERNAME>" . xmlValue($username) . "</SVUSERNAME>";
    if (trim((string)$password) !== '') $authXml .= "\n        <SVPASSWORD>" . xmlValue($password) . "</SVPASSWORD>";

    return '<?xml version="1.0" encoding="UTF-8"?>
<ENVELOPE>
  <HEADER>
    <VERSION>1</VERSION>
    <TALLYREQUEST>Export</TALLYREQUEST>
    <TYPE>Collection</TYPE>
    <ID>PHP Stock Item Report</ID>
  </HEADER>
  <BODY>
    <DESC>
      <STATICVARIABLES>
        <SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>' . $authXml . '
      </STATICVARIABLES>
      <TDL>
        <TDLMESSAGE>
          <COLLECTION NAME="PHP Stock Item Report" ISMODIFY="No" ISINITIALIZE="Yes">
            <TYPE>Stock Item</TYPE>
            <NATIVEMETHOD>Name</NATIVEMETHOD>
            <NATIVEMETHOD>Parent</NATIVEMETHOD>
            <NATIVEMETHOD>BaseUnits</NATIVEMETHOD>
            <METHOD>MyPartNo : $PartNo</METHOD>
          </COLLECTION>
        </TDLMESSAGE>
      </TDL>
    </DESC>
  </BODY>
</ENVELOPE>';
}

function buildSingleDeleteRequest($companyName, $itemName, $username, $password) {
    $authXml = '';
    if (trim((string)$companyName) !== '') $authXml .= "\n        <SVCURRENTCOMPANY>" . xmlValue($companyName) . "</SVCURRENTCOMPANY>";
    if (trim((string)$username) !== '') $authXml .= "\n        <SVUSERNAME>" . xmlValue($username) . "</SVUSERNAME>";
    if (trim((string)$password) !== '') $authXml .= "\n        <SVPASSWORD>" . xmlValue($password) . "</SVPASSWORD>";

    $staticVarsXml = $authXml !== '' ? "\n      <STATICVARIABLES>$authXml\n      </STATICVARIABLES>" : '';

    return '<?xml version="1.0" encoding="UTF-8"?>
<ENVELOPE>
  <HEADER>
    <VERSION>1</VERSION>
    <TALLYREQUEST>Import</TALLYREQUEST>
    <TYPE>Data</TYPE>
    <ID>All Masters</ID>
  </HEADER>
  <BODY>
    <DESC>' . $staticVarsXml . '
    </DESC>
    <DATA>
      <TALLYMESSAGE xmlns:UDF="TallyUDF">
        <STOCKITEM NAME="' . xmlValue($itemName) . '" ACTION="Delete" />
      </TALLYMESSAGE>
    </DATA>
  </BODY>
</ENVELOPE>';
}

function directChildText($node, $names) {
    $wanted = array_flip(array_map('strtoupper', $names));
    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_ELEMENT_NODE && isset($wanted[strtoupper($child->localName ?: $child->nodeName)])) {
            return normalizeSpace($child->textContent);
        }
    }
    return '';
}

function parseStockItems($response) {
    $out = array('ok' => false, 'items' => array(), 'error' => '', 'raw_response' => '');
    if ($response === '' || stripos($response, 'CURL ERROR') !== false) {
        $out['error'] = $response ?: 'Respons kosong.'; return $out;
    }
    
    $response = convertTallyResponseToUtf8($response);
    
    if (stripos($response, 'Authentication Failed') !== false) {
        $out['error'] = 'Koneksi ke Tally ditolak: Username atau Password salah.';
        return $out;
    }

    $old = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    
    if (!@$doc->loadXML($response, LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
        $errors = libxml_get_errors();
        $out['error'] = 'Gagal membaca XML: ' . (isset($errors[0]) ? trim($errors[0]->message) : 'Format tidak valid');
        $out['raw_response'] = substr($response, 0, 1000) . '...'; 
        libxml_clear_errors(); libxml_use_internal_errors($old);
        return $out;
    }
    libxml_clear_errors(); libxml_use_internal_errors($old);

    $xpath = new DOMXPath($doc);
    $nodes = $xpath->query('//*[translate(local-name(), "abcdefghijklmnopqrstuvwxyz", "ABCDEFGHIJKLMNOPQRSTUVWXYZ") = "STOCKITEM"]');
    
    foreach ($nodes as $node) {
        $name = $node->hasAttribute('NAME') ? normalizeSpace($node->getAttribute('NAME')) : directChildText($node, array('NAME'));
        if ($name !== '') {
            $out['items'][] = array(
                'name' => $name,
                'part_no' => directChildText($node, array('MYPARTNO', 'PARTNO')),
                'parent' => directChildText($node, array('PARENT')),
                'base_units' => directChildText($node, array('BASEUNITS'))
            );
        }
    }
    $out['ok'] = true;
    return $out;
}

function parseDeleteResponse($response, $itemName) {
    $out = array('deleted' => 0, 'errors' => 0, 'error_msgs' => array());
    $doc = new DOMDocument();
    if (@$doc->loadXML($response)) {
        $xpath = new DOMXPath($doc);
        $delNode = $xpath->query('//DELETED');
        if ($delNode->length > 0) $out['deleted'] = (int)$delNode->item(0)->textContent;
        
        $errNode = $xpath->query('//ERRORS');
        if ($errNode->length > 0) {
            $out['errors'] = (int)$errNode->item(0)->textContent;
        }
        
        $lineErrors = $xpath->query('//LINEERROR');
        foreach ($lineErrors as $err) {
            $out['error_msgs'][] = "[Gagal: " . $itemName . "] " . $err->textContent;
        }
    }
    return $out;
}

// Variables
$tallyIp = isset($_POST['tally_ip']) ? trim($_POST['tally_ip']) : '192.168.0.4';
$tallyPort = isset($_POST['tally_port']) ? trim($_POST['tally_port']) : '9002';
$companyName = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';
$tallyUser = isset($_POST['tally_user']) ? trim($_POST['tally_user']) : '';
$tallyPass = isset($_POST['tally_pass']) ? trim($_POST['tally_pass']) : '';
$action = isset($_POST['action']) ? $_POST['action'] : '';
$selectedItems = isset($_POST['selected_items']) && is_array($_POST['selected_items']) ? $_POST['selected_items'] : array();

$emptyItemCodeItems = array();
$totalItems = 0;
$error = '';
$raw_debug = '';
$deleteResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = 'http://' . $tallyIp . ':' . $tallyPort;
    
    // PROSES DELETE
    if ($action === 'delete' && count($selectedItems) > 0) {
        $deleteResult = array('deleted' => 0, 'errors' => 0, 'error_msgs' => array());
        
        foreach ($selectedItems as $itemName) {
            $deleteXml = buildSingleDeleteRequest($companyName, $itemName, $tallyUser, $tallyPass);
            $deleteResponse = sendToTally($deleteXml, $url);
            
            $parsedRes = parseDeleteResponse($deleteResponse, $itemName);
            $deleteResult['deleted'] += $parsedRes['deleted'];
            $deleteResult['errors'] += $parsedRes['errors'];
            if (!empty($parsedRes['error_msgs'])) {
                $deleteResult['error_msgs'] = array_merge($deleteResult['error_msgs'], $parsedRes['error_msgs']);
            }
            
            usleep(100000); // Jeda 0.1 detik
        }
    }

    // AMBIL DATA TERBARU
    $xmlRequest = buildStockItemListRequest($companyName, $tallyUser, $tallyPass);
    $xmlResponse = sendToTally($xmlRequest, $url);
    $parsed = parseStockItems($xmlResponse);

    if (!$parsed['ok']) {
        $error = $parsed['error'];
        $raw_debug = $parsed['raw_response'];
    } else {
        $totalItems = count($parsed['items']);
        foreach ($parsed['items'] as $item) {
            if ($item['part_no'] === '') {
                $emptyItemCodeItems[] = $item;
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Manajemen Stock Item (Part No Kosong)</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f6f8; color: #222; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.1); margin-bottom: 20px; }
        .row { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 10px;}
        .field { display: flex; flex-direction: column; }
        label { font-weight: bold; margin-bottom: 5px; font-size: 13px; }
        input[type="text"], input[type="password"] { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 9px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-primary { background: #0d6efd; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        table { border-collapse: collapse; width: 100%; font-size: 13px; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #2f455a; color: #fff; position: sticky; top: 0; z-index: 10;}
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert-error { background: #f8d7da; color: #842029; border: 1px solid #f5c6cb; }
        .alert-success { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; color: #fff; }
        .badge-warning { background: #ffc107; color: #000; }
        .checkbox-cell { text-align: center; width: 40px; }
        input[type="checkbox"] { transform: scale(1.2); cursor: pointer; }
    </style>
</head>
<body>

<h2>Manajemen Stock Item (Tanpa Part No)</h2>

<form method="post" id="mainForm">
    <div class="card">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>Terjadi Kesalahan:</strong> <?php echo h($error); ?>
                <?php if ($raw_debug): ?>
                    <br><br><strong>Debug:</strong><pre><?php echo h($raw_debug); ?></pre>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($deleteResult !== null): ?>
            <div class="alert <?php echo $deleteResult['errors'] > 0 ? 'alert-warning' : 'alert-success'; ?>">
                <strong>Hasil Proses Tally:</strong><br>
                - Berhasil Dihapus: <?php echo $deleteResult['deleted']; ?> item.<br>
                - Ditolak (Ada Transaksi/BOM): <?php echo $deleteResult['errors']; ?> item.<br>
                
                <?php if (!empty($deleteResult['error_msgs'])): ?>
                    <br><strong>Daftar Item yang Ditolak Tally:</strong>
                    <div style="max-height: 150px; overflow-y: auto; background: rgba(255,255,255,0.5); padding: 5px; margin-top: 5px; border-radius: 4px;">
                        <ul style="margin: 0; padding-left: 20px; font-size: 12px; color: #b02a37;">
                            <?php foreach ($deleteResult['error_msgs'] as $msg): ?>
                                <li><?php echo h($msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="field">
                <label>Tally IP Server</label>
                <input type="text" name="tally_ip" value="<?php echo h($tallyIp); ?>" required style="width: 120px;">
            </div>
            <div class="field">
                <label>Port</label>
                <input type="text" name="tally_port" value="<?php echo h($tallyPort); ?>" required style="width: 70px;">
            </div>
            <div class="field">
                <label>Nama Company (Kosong = Aktif)</label>
                <input type="text" name="company_name" value="<?php echo h($companyName); ?>" style="width: 220px;">
            </div>
        </div>
        
        <div class="row" style="padding-top: 5px; border-top: 1px dashed #ddd; margin-top: 5px;">
            <div class="field">
                <label>Username Tally</label>
                <input type="text" name="tally_user" value="<?php echo h($tallyUser); ?>" style="width: 150px;">
            </div>
            <div class="field">
                <label>Password Tally</label>
                <input type="password" name="tally_pass" value="<?php echo h($tallyPass); ?>" style="width: 150px;">
            </div>
            <input type="hidden" name="action" id="actionInput" value="preview">
            
            <div class="field" style="justify-content: flex-end;">
                <button type="button" class="btn-primary" onclick="submitForm('preview')">Preview Data</button>
            </div>
            <?php if (count($emptyItemCodeItems) > 0): ?>
            <div class="field" style="justify-content: flex-end;">
                <button type="button" class="btn-danger" onclick="submitForm('delete')">Kirim Perintah Hapus ke Tally</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error): ?>
    <div class="card">
        <p>
            Ditemukan <span class="badge badge-warning"><?php echo count($emptyItemCodeItems); ?></span> item tanpa Part No.<br>
            <em>Catatan: Centang semua dan klik Hapus. Tally secara otomatis hanya akan menghapus item yang benar-benar tidak memiliki riwayat transaksi/BOM sama sekali.</em>
        </p>
        
        <?php if (!empty($emptyItemCodeItems)): ?>
        <div style="max-height: 600px; overflow: auto;">
            <table>
                <thead>
                    <tr>
                        <th class="checkbox-cell">
                            <input type="checkbox" id="selectAll" title="Pilih Semua">
                        </th>
                        <th>No.</th>
                        <th>Nama Stock Item</th>
                        <th>Group (Parent)</th>
                        <th>Base Unit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($emptyItemCodeItems as $item): ?>
                    <tr>
                        <td class="checkbox-cell">
                            <input type="checkbox" name="selected_items[]" value="<?php echo h($item['name']); ?>" class="item-checkbox">
                        </td>
                        <td><?php echo $no++; ?></td>
                        <td><strong><?php echo h($item['name']); ?></strong></td>
                        <td><?php echo h($item['parent']); ?></td>
                        <td><?php echo h($item['base_units']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</form>

<script>
var selectAll = document.getElementById('selectAll');
if (selectAll) {
    selectAll.addEventListener('change', function(e) {
        var checkboxes = document.querySelectorAll('.item-checkbox');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = e.target.checked;
        }
    });
}

function submitForm(action) {
    if (action === 'delete') {
        var checkedCount = document.querySelectorAll('.item-checkbox:checked').length;
        
        if (checkedCount === 0) {
            alert('Pilih minimal satu item untuk dihapus.');
            return;
        }
        
        if (!confirm('PERINGATAN!\n\nAnda akan mengirimkan perintah hapus untuk ' + checkedCount + ' item.\nTally akan menyaring otomatis dan menolak item yang terikat transaksi.\n\nLanjutkan?')) {
            return;
        }
    }
    
    document.getElementById('actionInput').value = action;
    document.getElementById('mainForm').submit();
}
</script>

</body>
</html>