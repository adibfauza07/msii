<?php
/**
 * Customer autocomplete endpoint (optional fallback)
 * Compatible: PHP 5.4 + SQL Server 2008 + sqlsrv
 * Accepts GET or POST: q=<keyword>
 */
require_once __DIR__ . "/../config/global.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

$db = null;
if (isset($conn)) {
    $db = $conn;
} elseif (isset($connection)) {
    $db = $connection;
} elseif (isset($dbconn)) {
    $db = $dbconn;
}

if (!$db || !function_exists('sqlsrv_query')) {
    http_response_code(500);
    echo json_encode(array('error' => 'Koneksi database/sqlsrv tidak tersedia.'));
    exit;
}

function customerJsonSafeText($value)
{
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }

    if (@preg_match('//u', $text)) {
        return $text;
    }

    if (function_exists('iconv')) {
        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $text);
        if ($converted !== false) {
            return $converted;
        }
    }

    if (function_exists('utf8_encode')) {
        return @utf8_encode($text);
    }

    return $text;
}

$q = '';
if (isset($_GET['q'])) {
    $q = trim((string)$_GET['q']);
} elseif (isset($_POST['q'])) {
    $q = trim((string)$_POST['q']);
}

if ($q === '') {
    echo json_encode(array());
    exit;
}

$like = '%' . $q . '%';
$startLike = $q . '%';

$sql = "
    SELECT TOP 20
        CUST_ID,
        CUST_CODE,
        CUST_COMP,
        CUST_ABBR
    FROM dbo.CUST
    WHERE CUST_CODE LIKE ?
       OR CUST_COMP LIKE ?
       OR CUST_ABBR LIKE ?
    ORDER BY
        CASE WHEN CUST_CODE LIKE ? THEN 0 ELSE 1 END,
        CUST_CODE
";

$params = array($like, $like, $like, $startLike);
$stmt = sqlsrv_query($db, $sql, $params);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(array('error' => 'Query customer gagal.'));
    exit;
}

$data = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = array(
        'CUST_ID'   => isset($row['CUST_ID']) ? $row['CUST_ID'] : null,
        'CUST_CODE' => customerJsonSafeText(isset($row['CUST_CODE']) ? $row['CUST_CODE'] : ''),
        'CUST_COMP' => customerJsonSafeText(isset($row['CUST_COMP']) ? $row['CUST_COMP'] : ''),
        'CUST_ABBR' => customerJsonSafeText(isset($row['CUST_ABBR']) ? $row['CUST_ABBR'] : '')
    );
}

sqlsrv_free_stmt($stmt);
$json = json_encode($data);
if ($json === false) {
    http_response_code(500);
    echo json_encode(array('error' => 'Encoding JSON customer gagal.'));
    exit;
}

echo $json;
?>
