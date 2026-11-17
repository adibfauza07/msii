<?php
/**
 * Item autocomplete endpoint
 * PHP 5.4 + SQL Server 2008 + sqlsrv
 ac_item.php
 */
ob_start();
require_once __DIR__ . "/../config/global.php";

// Prevent accidental output from global.php from corrupting JSON.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

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
    echo json_encode(array('error' => 'Koneksi SQL Server tidak tersedia.'));
    exit;
}

$q = '';
if (isset($_GET['q'])) {
    $q = trim((string) $_GET['q']);
} elseif (isset($_POST['q'])) {
    $q = trim((string) $_POST['q']);
}

if ($q === '') {
    echo json_encode(array());
    exit;
}

function ac_item_utf8($value)
{
    $text = trim((string) $value);
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

$like = '%' . $q . '%';
$startLike = $q . '%';

$sql = "
    SELECT TOP 20
        ITEM_CODE,
        ITEM_NAME
    FROM dbo.ITEMS
    WHERE ITEM_CODE LIKE ?
       OR ITEM_NAME LIKE ?
    ORDER BY
        CASE WHEN ITEM_CODE LIKE ? THEN 0 ELSE 1 END,
        ITEM_CODE
";

$params = array($like, $like, $startLike);
$stmt = sqlsrv_query($db, $sql, $params);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(array('error' => 'Query item autocomplete gagal.'));
    exit;
}

$data = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $data[] = array(
        'ITEM_CODE' => ac_item_utf8(isset($row['ITEM_CODE']) ? $row['ITEM_CODE'] : ''),
        'ITEM_NAME' => ac_item_utf8(isset($row['ITEM_NAME']) ? $row['ITEM_NAME'] : '')
    );
}
sqlsrv_free_stmt($stmt);

$json = json_encode($data);
if ($json === false) {
    http_response_code(500);
    echo json_encode(array('error' => 'JSON item autocomplete gagal.'));
    exit;
}

echo $json;
exit;
