<?php
/*
 * cek_partno_kosong.php
 * Hanya mencari dan menampilkan Stock Item yang Part No-nya kosong,
 * berdasarkan grup duplikat (8 karakter awal yang sama) yang memiliki Part No.
 * TIDAK ADA fungsi Alter/Update di script ini.
 */

@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');
@set_time_limit(0);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function xmlValue($value) {
    return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
}

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
    return preg_replace_callback(
        '/&#(?:(?:x|X)([0-9A-Fa-f]+)|([0-9]+));/',
        function ($matches) {
            $codePoint = isset($matches[1]) && $matches[1] !== ''
                ? hexdec($matches[1])
                : (isset($matches[2]) ? (int)$matches[2] : 0);
            return isValidXml10CodePoint($codePoint) ? $matches[0] : ' ';
        },
        (string)$value
    );
}

function stripInvalidXml10Characters($value) {
    $value = removeInvalidXmlCharacterReferences((string)$value);
    $clean = @preg_replace(
        '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
        '',
        $value
    );
    return (string)(
        $clean === null
            ? preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value)
            : $clean
    );
}

function convertTallyResponseToUtf8($value) {
    $value = preg_replace('/^\xEF\xBB\xBF/', '', (string)$value);
    if (@preg_match('//u', $value) !== 1) {
        $converted = false;
        if (function_exists('iconv')) {
            $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        }
        if (!$converted && function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }
        if (!$converted && function_exists('utf8_encode')) {
            $converted = @utf8_encode($value);
        }
        if ($converted) {
            $value = $converted;
        }
    }
    return trim(stripInvalidXml10Characters(str_replace("\xC2\xA0", ' ', $value)));
}

function sendToTally($xml, $url) {
    if (!function_exists('curl_init')) {
        return 'CURL ERROR: cURL belum aktif.';
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: text/xml; charset=UTF-8',
        'Connection: Keep-Alive'
    ));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);

    $response = curl_exec($ch);
    if ($response === false) {
        $response = 'CURL ERROR: ' . curl_error($ch);
    }

    curl_close($ch);
    return (string)$response;
}

function buildStockItemListRequest($companyName, $username, $password) {
    $staticVarsXml = "\n        <SVEXPORTFORMAT>\$\$SysName:XML</SVEXPORTFORMAT>";
    if (trim((string)$companyName) !== '') {
        $staticVarsXml .= "\n        <SVCURRENTCOMPANY>" . xmlValue($companyName) . "</SVCURRENTCOMPANY>";
    }
    if (trim((string)$username) !== '') {
        $staticVarsXml .= "\n        <SVUSERNAME>" . xmlValue($username) . "</SVUSERNAME>";
    }
    if (trim((string)$password) !== '') {
        $staticVarsXml .= "\n        <SVPASSWORD>" . xmlValue($password) . "</SVPASSWORD>";
    }

    return '<?xml version="1.0" encoding="UTF-8"?>
<ENVELOPE>
  <HEADER>
    <VERSION>1</VERSION>
    <TALLYREQUEST>Export</TALLYREQUEST>
    <TYPE>Collection</TYPE>
    <ID>PHP Stock Item Duplicate Print</ID>
  </HEADER>
  <BODY>
    <DESC>
      <STATICVARIABLES>' . $staticVarsXml . '
      </STATICVARIABLES>
      <TDL>
        <TDLMESSAGE>
          <COLLECTION NAME="PHP Stock Item Duplicate Print" ISMODIFY="No" ISINITIALIZE="Yes">
            <TYPE>Stock Item</TYPE>
            <NATIVEMETHOD>Name</NATIVEMETHOD>
            <METHOD>MyPartNo : $PartNo</METHOD>
          </COLLECTION>
        </TDLMESSAGE>
      </TDL>
    </DESC>
  </BODY>
</ENVELOPE>';
}

function directChildText($node, $names) {
    $wanted = array_flip(array_map('strtoupper', $names));
    foreach ($node->childNodes as $child) {
        if (
            $child->nodeType === XML_ELEMENT_NODE &&
            isset($wanted[strtoupper($child->localName ?: $child->nodeName)])
        ) {
            return normalizeSpace($child->textContent);
        }
    }
    return '';
}

function getEightCharacterPrefix($name) {
    $name = normalizeSpace($name);
    if (preg_match('/^([0-9-]{8})(?=\s|$)/', $name, $matches)) {
        return $matches[1];
    }
    return '';
}

function parseItemsToReview($response) {
    $out = array(
        'ok' => false,
        'items_to_review' => array(),
        'error' => '',
        'raw_response' => ''
    );

    if ($response === '' || stripos($response, 'CURL ERROR') !== false) {
        $out['error'] = $response !== '' ? $response : 'Respons Tally kosong.';
        return $out;
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
        $out['error'] = 'Gagal membaca XML: ' . (
            isset($errors[0]) ? trim($errors[0]->message) : 'Format XML tidak valid.'
        );
        $out['raw_response'] = substr($response, 0, 1000);
        libxml_clear_errors();
        libxml_use_internal_errors($old);
        return $out;
    }

    libxml_clear_errors();
    libxml_use_internal_errors($old);

    $xpath = new DOMXPath($doc);
    $nodes = $xpath->query(
        '//*[translate(local-name(), "abcdefghijklmnopqrstuvwxyz", "ABCDEFGHIJKLMNOPQRSTUVWXYZ") = "STOCKITEM"]'
    );

    $groups = array();

    foreach ($nodes as $node) {
        $name = $node->hasAttribute('NAME')
            ? normalizeSpace($node->getAttribute('NAME'))
            : directChildText($node, array('NAME'));

        if ($name === '') continue;

        $code = getEightCharacterPrefix($name);
        if ($code === '') continue;

        if (!isset($groups[$code])) {
            $groups[$code] = array();
        }

        $groups[$code][] = array(
            'name' => $name,
            'part_no' => directChildText($node, array('MYPARTNO', 'PARTNO'))
        );
    }

    // Hanya cari item yang kosong Part No untuk ditampilkan
    foreach ($groups as $code => $items) {
        if (count($items) < 2) continue;

        $filled_part_nos = array();
        $empty_items = array();

        foreach ($items as $item) {
            if (trim((string)$item['part_no']) === '') {
                $empty_items[] = $item['name'];
            } else {
                $filled_part_nos[] = $item['part_no'];
            }
        }

        if (!empty($filled_part_nos) && !empty($empty_items)) {
            // Ambil Part No pertama yang terisi sebagai informasi
            $target_part_no = $filled_part_nos[0]; 

            foreach ($empty_items as $empty_name) {
                $out['items_to_review'][] = array(
                    'prefix' => $code,
                    'name' => $empty_name,
                    'reference_part_no' => $target_part_no
                );
            }
        }
    }

    $out['ok'] = true;
    return $out;
}

$tallyIp = isset($_POST['tally_ip']) ? trim($_POST['tally_ip']) : '192.168.0.1';
$tallyPort = isset($_POST['tally_port']) ? trim($_POST['tally_port']) : '9002';
$companyName = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';
$tallyUser = isset($_POST['tally_user']) ? trim($_POST['tally_user']) : '';
$tallyPass = isset($_POST['tally_pass']) ? trim($_POST['tally_pass']) : '';

$itemsToReview = array();
$error = '';
$rawDebug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = 'http://' . $tallyIp . ':' . $tallyPort;
    
    $xmlRequest = buildStockItemListRequest($companyName, $tallyUser, $tallyPass);
    $xmlResponse = sendToTally($xmlRequest, $url);
    $parsed = parseItemsToReview($xmlResponse);

    if (!$parsed['ok']) {
        $error = $parsed['error'];
        $rawDebug = $parsed['raw_response'];
    } else {
        $itemsToReview = $parsed['items_to_review'];
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cek Part No Stock Item Kosong</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; background: #eef1f4; color: #111; font-family: Arial, sans-serif; }
        .control-panel { max-width: 1000px; margin: 0 auto 18px; padding: 18px; background: #fff; border-radius: 8px; box-shadow: 0 1px 5px rgba(0, 0, 0, .12); }
        .control-panel h2 { margin: 0 0 15px; font-size: 21px; }
        .form-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .field { display: flex; flex-direction: column; gap: 5px; }
        label { font-size: 12px; font-weight: bold; }
        input { height: 36px; padding: 7px 9px; border: 1px solid #bbb; border-radius: 4px; }
        button { height: 36px; padding: 0 15px; border: 0; border-radius: 4px; color: #fff; background: #0d6efd; font-weight: bold; cursor: pointer; }
        .message { margin-top: 14px; padding: 10px 12px; border-radius: 4px; font-size: 14px; }
        .message.error { color: #842029; background: #f8d7da; border: 1px solid #f5c2c7; }
        .message.success { color: #0f5132; background: #d1e7dd; border: 1px solid #badbcc; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; background: #fff;}
        th, td { border: 1px solid #ccc; padding: 8px 10px; text-align: left; }
        th { background: #f4f4f4; }
    </style>
</head>
<body>

<div class="control-panel">
    <h2>Cari Data Stock Item dengan Part No Kosong</h2>

    <form method="post">
        <div class="form-row">
            <div class="field">
                <label>Tally IP Server</label>
                <input type="text" name="tally_ip" value="<?php echo h($tallyIp); ?>" required style="width: 140px;">
            </div>
            <div class="field">
                <label>Port</label>
                <input type="text" name="tally_port" value="<?php echo h($tallyPort); ?>" required style="width: 85px;">
            </div>
            <div class="field">
                <label>Nama Company (kosong = aktif)</label>
                <input type="text" name="company_name" value="<?php echo h($companyName); ?>" style="width: 230px;">
            </div>
            <div class="field">
                <label>Username Tally</label>
                <input type="text" name="tally_user" value="<?php echo h($tallyUser); ?>" style="width: 160px;">
            </div>
            <div class="field">
                <label>Password Tally</label>
                <input type="password" name="tally_pass" value="<?php echo h($tallyPass); ?>" style="width: 150px;">
            </div>
            
            <button type="submit">Cari Data</button>
        </div>
    </form>

    <?php if ($error !== ''): ?>
        <div class="message error">
            <strong>Terjadi kesalahan:</strong> <?php echo h($error); ?>
            <?php if ($rawDebug !== ''): ?>
                <pre><?php echo h($rawDebug); ?></pre>
            <?php endif; ?>
        </div>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($itemsToReview)): ?>
        <div class="message success">Tidak ditemukan data. Semua Stock Item dalam grup duplikat sudah memiliki Part No yang lengkap.</div>
    <?php endif; ?>

    <?php if (!empty($itemsToReview)): ?>
        <h3 style="margin-top: 25px;">Ditemukan <?php echo count($itemsToReview); ?> Item tanpa Part No</h3>
        <table>
            <thead>
                <tr>
                    <th style="width:50px;">No</th>
                    <th style="width:120px;">Prefix Code</th>
                    <th>Nama Stock Item (Tally)</th>
                    <th style="width:200px;">Part No Referensi (Kembaran)</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($itemsToReview as $item): ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><strong><?php echo h($item['prefix']); ?></strong></td>
                        <td><?php echo h($item['name']); ?></td>
                        <td style="color: blue; font-weight: bold;"><?php echo h($item['reference_part_no']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>